<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\CoreBundle\Locale\Locale;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerHelper;
use Dbp\Relay\PortfolioBundle\Render\RenderController;
use Dbp\Relay\PortfolioBundle\Service\WorkflowService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RenderControllerTest extends AbstractTestCase
{
    private RenderController $controller;
    private WorkflowTypeHandlerHelper $handlerHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $workflowService = $this->container->get(WorkflowService::class);
        $uriSigner = $this->container->get(UriSigner::class);
        $locale = $this->container->get(Locale::class);
        $this->handlerHelper = $this->container->get(WorkflowTypeHandlerHelper::class);

        $this->controller = new RenderController($workflowService, $uriSigner, $locale);
    }

    public function testValidSignedRequestReturnsHtml(): void
    {
        $this->testEntityManager->addWorkflow('wf-1', DummyWorkflowTypeHandler::TYPE);

        $signedUrl = $this->handlerHelper->getSignedRenderUrl('wf-1', 'hello');
        $response = ($this->controller)(Request::create($signedUrl));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/html; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('render hello for wf-1', (string) $response->getContent());
    }

    public function testMissingSignatureIsDenied(): void
    {
        $this->testEntityManager->addWorkflow('wf-1', DummyWorkflowTypeHandler::TYPE);

        $request = Request::create('https://example.com/portfolio/_render?workflowId=wf-1&renderId=hello');

        $this->expectException(AccessDeniedHttpException::class);
        ($this->controller)($request);
    }

    public function testTamperedSignatureIsDenied(): void
    {
        $this->testEntityManager->addWorkflow('wf-1', DummyWorkflowTypeHandler::TYPE);

        $signedUrl = $this->handlerHelper->getSignedRenderUrl('wf-1', 'hello');
        // Change the renderId after signing — signature must no longer match
        $tampered = str_replace('renderId=hello', 'renderId=evil', $signedUrl);

        $this->expectException(AccessDeniedHttpException::class);
        ($this->controller)(Request::create($tampered));
    }

    public function testExpiredSignedUrlIsDenied(): void
    {
        $this->testEntityManager->addWorkflow('wf-1', DummyWorkflowTypeHandler::TYPE);

        // The test kernel uses secret 'something' — sign with a past expiration
        $uriSigner = new UriSigner('something');
        $expiredUrl = $uriSigner->sign(
            'https://example.com/portfolio/_render?workflowId=wf-1&renderId=hello',
            time() - 1,
        );

        $this->expectException(AccessDeniedHttpException::class);
        ($this->controller)(Request::create($expiredUrl));
    }

    public function testUnknownWorkflowGives404(): void
    {
        $signedUrl = $this->handlerHelper->getSignedRenderUrl('no-such-wf', 'hello');

        $this->expectException(NotFoundHttpException::class);
        ($this->controller)(Request::create($signedUrl));
    }

    public function testSoftDeletedWorkflowGives404(): void
    {
        $this->testEntityManager->addWorkflow('wf-deleted', DummyWorkflowTypeHandler::TYPE, deletedAt: new \DateTimeImmutable());

        $signedUrl = $this->handlerHelper->getSignedRenderUrl('wf-deleted', 'hello');

        $this->expectException(NotFoundHttpException::class);
        ($this->controller)(Request::create($signedUrl));
    }

    public function testMissingWorkflowIdParamGives400(): void
    {
        // Sign a URL that has a valid signature but no workflowId param
        $uriSigner = $this->container->get(UriSigner::class);
        $signedUrl = $uriSigner->sign('https://example.com/portfolio/_render?renderId=hello');

        $this->expectException(BadRequestHttpException::class);
        ($this->controller)(Request::create($signedUrl));
    }
}
