<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\CoreBundle\Locale\Locale;
use Dbp\Relay\CoreBundle\TestUtils\DataProviderTester;
use Dbp\Relay\PortfolioBundle\ApiPlatform\TaskItem;
use Dbp\Relay\PortfolioBundle\ApiPlatform\TaskProvider;
use Dbp\Relay\PortfolioBundle\Authorization\AuthorizationService;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerHelper;
use Dbp\Relay\PortfolioBundle\Service\WorkflowService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TaskProviderTest extends AbstractTestCase
{
    private DataProviderTester $tester;
    private WorkflowTypeHandlerHelper $handlerHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $workflowService = $this->container->get(WorkflowService::class);
        $authorizationService = $this->container->get(AuthorizationService::class);
        $locale = $this->container->get(Locale::class);
        $this->handlerHelper = $this->container->get(WorkflowTypeHandlerHelper::class);
        $uriSigner = $this->container->get(UriSigner::class);
        $requestStack = $this->container->get(RequestStack::class);
        $provider = new TaskProvider($workflowService, $authorizationService, $locale, $uriSigner, $requestStack);
        $this->tester = DataProviderTester::create($provider, TaskItem::class, ['PortfolioTask:output']);
    }

    public function testGetItemNotFound(): void
    {
        $this->assertNull($this->tester->getItem('no-such-task'));
    }

    public function testGetItemBuildsDto(): void
    {
        $workflow = $this->testEntityManager->addWorkflow('wf-1', DummyWorkflowTypeHandler::TYPE);
        $this->testEntityManager->addTask('t-1', $workflow);

        /** @var TaskItem $item */
        $item = $this->tester->getItem('t-1');
        $this->assertNotNull($item);
        $this->assertSame('t-1', $item->getIdentifier());
        $this->assertSame('wf-1', $item->getWorkflowId());
        $this->assertSame(['info' => 'computed from wf-1'], $item->getData());
    }

    public function testGetCollectionRequiresWorkflowId(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->tester->getCollection();
    }

    public function testGetCollectionReturnsTasksForWorkflow(): void
    {
        $workflow = $this->testEntityManager->addWorkflow('wf-1', DummyWorkflowTypeHandler::TYPE);
        $this->testEntityManager->addTask('t-1', $workflow);
        $this->testEntityManager->addTask('t-2', $workflow);

        // A second workflow whose tasks must not appear in the results
        $other = $this->testEntityManager->addWorkflow('wf-2', DummyWorkflowTypeHandler::TYPE);
        $this->testEntityManager->addTask('t-other', $other);

        $items = $this->tester->getCollection(['workflowId' => 'wf-1']);
        $this->assertCount(2, $items);
    }

    // -------------------------------------------------------------------------
    // canUse() enforcement
    // -------------------------------------------------------------------------

    public function testGetItemCanUseReturnsFalseGivesNull(): void
    {
        $workflow = $this->testEntityManager->addWorkflow('wf-1', DummyWorkflowTypeHandler::TYPE);
        $this->testEntityManager->addTask('t-1', $workflow);

        $handler = $this->container->get(DummyWorkflowTypeHandler::class);
        $handler->canUse = false;

        $this->assertNull($this->tester->getItem('t-1'));
    }

    public function testGetCollectionCanUseReturnsFalseGives404(): void
    {
        $this->testEntityManager->addWorkflow('wf-1', DummyWorkflowTypeHandler::TYPE);

        $handler = $this->container->get(DummyWorkflowTypeHandler::class);
        $handler->canUse = false;

        $this->expectException(NotFoundHttpException::class);
        $this->tester->getCollection(['workflowId' => 'wf-1']);
    }

    // -------------------------------------------------------------------------
    // Signed URL fallback
    // -------------------------------------------------------------------------

    /**
     * Creates a TaskProvider whose AuthorizationService always denies ROLE_USER,
     * paired with a RequestStack carrying the given Request.
     */
    private function makeProviderDeniedWithRequest(Request $request): DataProviderTester
    {
        $workflowService = $this->container->get(WorkflowService::class);
        $locale = $this->container->get(Locale::class);

        // Mock AuthorizationService so getCanUse() returns false and checkCanUse() throws
        $authorizationService = $this->createMock(AuthorizationService::class);
        $authorizationService->method('getCanUse')->willReturn(false);
        $authorizationService->method('checkCanUse')->willThrowException(new AccessDeniedHttpException());

        $uriSigner = $this->container->get(UriSigner::class);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $provider = new TaskProvider($workflowService, $authorizationService, $locale, $uriSigner, $requestStack);

        return DataProviderTester::create($provider, TaskItem::class, ['PortfolioTask:output']);
    }

    public function testSignedUrlAllowsAccessWithoutUserRole(): void
    {
        $workflow = $this->testEntityManager->addWorkflow('wf-signed', DummyWorkflowTypeHandler::TYPE);
        $this->testEntityManager->addTask('t-signed', $workflow);

        $signedUrl = $this->handlerHelper->getSignedTaskUrl('t-signed');
        $tester = $this->makeProviderDeniedWithRequest(Request::create($signedUrl));

        /** @var TaskItem $item */
        $item = $tester->getItem('t-signed');
        $this->assertNotNull($item);
        $this->assertSame('t-signed', $item->getIdentifier());
    }

    public function testUnsignedRequestWithoutRoleThrows403(): void
    {
        $workflow = $this->testEntityManager->addWorkflow('wf-unsigned', DummyWorkflowTypeHandler::TYPE);
        $this->testEntityManager->addTask('t-unsigned', $workflow);

        $tester = $this->makeProviderDeniedWithRequest(
            Request::create('https://example.com/portfolio/tasks/t-unsigned')
        );

        $this->expectException(AccessDeniedHttpException::class);
        $tester->getItem('t-unsigned');
    }

    public function testSignedUrlForDifferentTaskIdIsRejected(): void
    {
        $workflow = $this->testEntityManager->addWorkflow('wf-tamper', DummyWorkflowTypeHandler::TYPE);
        $this->testEntityManager->addTask('t-real', $workflow);
        $this->testEntityManager->addTask('t-other', $workflow);

        // Obtain a valid signed URL for t-real, extract its query params
        $signedForReal = $this->handlerHelper->getSignedTaskUrl('t-real');
        parse_str((string) parse_url($signedForReal, PHP_URL_QUERY), $params);

        // Use those params on a request targeting t-other — signature must not match
        $tamperedUrl = 'https://example.com/portfolio/tasks/t-other?'.http_build_query($params);
        $tester = $this->makeProviderDeniedWithRequest(Request::create($tamperedUrl));

        $this->expectException(AccessDeniedHttpException::class);
        $tester->getItem('t-other');
    }

    public function testSignedUrlBypassesCanUse(): void
    {
        $workflow = $this->testEntityManager->addWorkflow('wf-canuse', DummyWorkflowTypeHandler::TYPE);
        $this->testEntityManager->addTask('t-canuse', $workflow);

        // canUse() returns false, but signed URL should still allow access
        $handler = $this->container->get(DummyWorkflowTypeHandler::class);
        $handler->canUse = false;

        $signedUrl = $this->handlerHelper->getSignedTaskUrl('t-canuse');
        $tester = $this->makeProviderDeniedWithRequest(Request::create($signedUrl));

        /** @var TaskItem $item */
        $item = $tester->getItem('t-canuse');
        $this->assertNotNull($item);
        $this->assertSame('t-canuse', $item->getIdentifier());
    }

    public function testExpiredSignedUrlIsRejected(): void
    {
        $workflow = $this->testEntityManager->addWorkflow('wf-expired', DummyWorkflowTypeHandler::TYPE);
        $this->testEntityManager->addTask('t-expired', $workflow);

        // The test kernel uses secret 'something' — sign with it but use a past timestamp
        $uriSigner = new UriSigner('something');
        $expiredUrl = $uriSigner->sign(
            'https://example.com/portfolio/tasks/t-expired',
            time() - 1,
        );

        $tester = $this->makeProviderDeniedWithRequest(Request::create($expiredUrl));

        $this->expectException(AccessDeniedHttpException::class);
        $tester->getItem('t-expired');
    }
}
