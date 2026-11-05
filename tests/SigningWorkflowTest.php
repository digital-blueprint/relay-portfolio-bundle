<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\BasePersonBundle\API\PersonProviderInterface;
use Dbp\Relay\BasePersonBundle\Entity\Person;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowData;
use Dbp\Relay\PortfolioBundle\Persistence\WorkflowPersistence;
use Dbp\Relay\PortfolioBundle\Service\WorkflowFileService;
use Dbp\Relay\PortfolioBundle\Service\WorkflowService;
use Dbp\Relay\PortfolioBundle\SignApi\SignController;
use Dbp\Relay\PortfolioBundle\SignApi\SignJobState;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\SigningWorkflowTypeHandler;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\SignService;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningWorkflowState;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

class SigningWorkflowTest extends AbstractTestCase
{
    private WorkflowService $workflows;
    private SignService $sign;
    private SignController $controller;
    private ?string $currentPersonId = 'owner-id';
    private bool $lookupFails = false;

    protected function setUp(): void
    {
        parent::setUp();
        $provider = $this->createMock(PersonProviderInterface::class);
        $provider->method('getCurrentPersonIdentifier')->willReturnCallback(fn (): ?string => $this->currentPersonId);
        $provider->method('getPersons')->willReturnCallback(function (int $page, int $limit, array $options): array {
            if ($this->lookupFails) {
                throw new \RuntimeException('Directory failed');
            }
            $persons = [];
            foreach (['owner' => 'Owner', 'approver' => 'Approver'] as $account => $givenName) {
                if ($options['filter']->apply(['localData.email' => $account.'@example.com'])) {
                    $person = new Person();
                    $person->setIdentifier($account.'-id');
                    $person->setGivenName($givenName);
                    $person->setFamilyName('Example');
                    $persons[] = $person;
                }
            }

            return $persons;
        });
        $this->container->set(PersonProviderInterface::class, $provider);
        $this->workflows = $this->container->get(WorkflowService::class);
        $this->sign = $this->container->get(SignService::class);
        $this->controller = $this->container->get(SignController::class);
    }

    private function request(?array $job = null, array $files = []): Request
    {
        $request = new Request(
            request: ['jobDescription' => json_encode($job ?? SigningTestUtils::jobDescription(), JSON_THROW_ON_ERROR)],
            files: $files ?: ['documentToSign' => SigningTestUtils::pdf()],
        );
        $request->headers->set('Authorization', 'Basic '.base64_encode('svc_user:svc_pass'));

        return $request;
    }

    private function create(): WorkflowPersistence
    {
        $response = $this->controller->startProcess('process49', $this->request());
        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());

        return $this->workflows->getWorkflow((string) $response->getContent());
    }

    private function data(WorkflowPersistence $workflow): WorkflowData
    {
        return new WorkflowData($workflow->getId(), $workflow->getType(), $workflow->isActive(), $workflow->getInternalState(), $workflow->getCreatedAt());
    }

    private function assertNothingCreated(): void
    {
        self::assertSame([], $this->workflows->getWorkflows(0, 10));
        $blob = $this->container->get('dbp.relay.portfolio.blob_api');
        self::assertSame([], iterator_to_array($blob->getFiles(1, 10)));
        self::assertSame(0, (int) $this->container->get('doctrine.orm.dbp_relay_portfolio_bundle_entity_manager')->getConnection()->fetchOne('SELECT COUNT(*) FROM portfolio_tasks'));
    }

    public function testCreatesWorkflowAndOwnsUploadedFiles(): void
    {
        $job = SigningTestUtils::jobDescription();
        $job['metaData'] = ['description' => 'Please sign', 'referenceId' => 'CASE-123', 'expirationDate' => '2000-01-01T12:00:00.000+0100'];
        $response = $this->controller->startProcess('process49', $this->request($job, [
            'documentToSign' => SigningTestUtils::pdf('work-contract.pdf'),
            'attachments' => [SigningTestUtils::pdf('supporting.pdf')],
        ]));
        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));
        $id = (string) $response->getContent();
        self::assertTrue(Uuid::isValid($id));
        $workflow = $this->workflows->getWorkflow($id);
        $state = SigningWorkflowState::fromArray($workflow->getInternalState());
        self::assertTrue($workflow->isActive());
        self::assertSame('owner-id', $state->constituentUserId);
        self::assertSame('Owner Example', $state->constituentDisplayName);
        self::assertSame('approver-id', $state->iterations[0]->invitees[0]->userId);
        self::assertSame('Approver Example', $state->iterations[0]->invitees[0]->displayName);
        self::assertNull($state->iterations[1]->invitees[0]->userId);
        self::assertSame('Guest', $state->iterations[1]->invitees[0]->displayName);
        self::assertSame('CASE-123', $state->referenceId);
        self::assertSame('2000', $state->expirationDate->format('Y'));
        $files = $this->container->get(WorkflowFileService::class);
        foreach ([$state->documentToSignFileId => 'work-contract.pdf', $state->attachments[0]['fileId'] => 'supporting.pdf'] as $fileId => $filename) {
            $file = $files->get($id, $fileId);
            self::assertSame('portfolio/workflows/'.$id, $file->getPrefix());
            self::assertSame($filename, $file->getFileName());
            self::assertSame('application/pdf', $file->getMimeType());
            self::assertSame(file_get_contents(SigningTestUtils::pdf()->getPathname()), $files->download($id, $fileId)->getFileStream()->getContents());
        }
        $task = $this->workflows->getTask($state->iterations[0]->invitees[0]->id);
        self::assertNotNull($task);
        self::assertNull($this->workflows->getTask($state->iterations[1]->invitees[0]->id));
        self::assertTrue(Uuid::isValid($state->iterations[1]->invitees[0]->id));
        self::assertNotSame($state->iterations[0]->invitees[0]->id, $state->iterations[1]->invitees[0]->id);
        $taskData = $this->workflows->getTaskResponse($task, 'en');
        self::assertCount(1, $taskData['documents']);
        self::assertCount(1, $taskData['attachments']);
        self::assertSame('work-contract.pdf', $taskData['documents'][0]['filename']);
        self::assertStringStartsWith('http', $taskData['documents'][0]['url']);
        self::assertStringStartsWith('http', $taskData['attachments'][0]['url']);
    }

    public function testPollingReadsPersistedState(): void
    {
        $id = $this->create()->getId();
        $em = $this->container->get('doctrine.orm.dbp_relay_portfolio_bundle_entity_manager');
        $em->clear();
        self::assertSame('process49', $this->sign->resolveProcessId($id));
        self::assertSame(SignJobState::ACTIVE, (new SignService($this->workflows))->getJobState($id, 'EMAIL')->getState());
        $workflow = $this->workflows->getWorkflow($id);
        $state = SigningWorkflowState::fromArray($workflow->getInternalState());
        $state->status = SignJobState::FINISHED_FAILED;
        $workflow->setInternalState($state->toArray());
        $em->flush();
        self::assertSame(['state' => 'FINISHED_FAILED'], json_decode($this->controller->getJobState($id, 'EMAIL', $this->request())->getContent(), true));
        self::assertSame(400, $this->controller->getJobState($id, 'ID', $this->request())->getStatusCode());
        self::assertNull($this->sign->resolveProcessId(Uuid::v4()->toRfc4122()));
        $other = $this->workflows->createWorkflow('dummy', []);
        self::assertNull($this->sign->resolveProcessId($other->getId()));
        $state->signingProcessId = 'another-process';
        $workflow->setInternalState($state->toArray());
        $em->flush();
        self::assertNull($this->sign->resolveProcessId($id));
    }

    public function testViewDocumentUsesCurrentPdfWithoutChangingSigningState(): void
    {
        $workflow = $this->create();
        $state = $workflow->getInternalState();
        $result = $this->workflows->handleAction($workflow->getId(), SigningWorkflowTypeHandler::ACTION_VIEW_DOCUMENT, [], 'en');
        self::assertTrue($this->container->get(\Symfony\Component\HttpFoundation\UriSigner::class)->check($result->getUrl()));
        self::assertSame($state, $workflow->getInternalState());
        $render = $this->workflows->getRenderResponse($workflow, SigningWorkflowTypeHandler::RENDER_DOCUMENT, 'en');
        self::assertSame('application/pdf', $render->getContentType());
        self::assertSame(SigningTestUtils::pdf()->getContent(), $render->getHtml());

        $signedPdf = "%PDF-1.4\n%Signed contract\n%%EOF";
        $fileId = $this->container->get(WorkflowFileService::class)->store($workflow->getId(), 'signed.pdf', 'application/pdf', $signedPdf);
        $state = SigningWorkflowState::fromArray($state);
        $state->iterations[0]->signedDocumentFileId = $fileId;
        $workflow->setInternalState($state->toArray());
        self::assertSame($signedPdf, $this->workflows->getRenderResponse($workflow, SigningWorkflowTypeHandler::RENDER_DOCUMENT, 'en')->getHtml());
    }

    #[DataProvider('deletableStates')]
    public function testDeleteActionHidesWorkflowAndCleansUpFiles(bool $closed): void
    {
        $response = $this->controller->startProcess('process49', $this->request(files: [
            'documentToSign' => SigningTestUtils::pdf(),
            'attachments' => [SigningTestUtils::pdf('supporting.pdf')],
        ]));
        self::assertSame(201, $response->getStatusCode());
        $workflow = $this->workflows->getWorkflow((string) $response->getContent());
        $taskId = SigningWorkflowState::fromArray($workflow->getInternalState())->iterations[0]->invitees[0]->id;
        if ($closed) {
            $workflow->close();
        }
        $em = $this->container->get('doctrine.orm.dbp_relay_portfolio_bundle_entity_manager');
        $em->flush();
        $handler = $this->container->get(SigningWorkflowTypeHandler::class);
        $data = $this->data($workflow);
        self::assertContains(SigningWorkflowTypeHandler::ACTION_DELETE, array_map(static fn ($action): string => $action->getId(), $handler->getAvailableActions($data, 'en')));

        $this->workflows->handleAction($workflow->getId(), SigningWorkflowTypeHandler::ACTION_DELETE, [], 'en');
        self::assertTrue($workflow->isDeleted());
        self::assertNull($this->workflows->getWorkflow($workflow->getId()));
        self::assertNull($this->workflows->getTask($taskId));
        self::assertNull($this->sign->resolveProcessId($workflow->getId()));
        $this->workflows->cleanupAll();
        self::assertNull($em->find(WorkflowPersistence::class, $workflow->getId()));
        $this->assertNothingCreated();
    }

    public static function deletableStates(): iterable
    {
        yield 'active' => [false];
        yield 'closed' => [true];
    }

    public function testCannotDeleteWorkflowTwice(): void
    {
        $workflow = $this->create();
        $this->workflows->handleAction($workflow->getId(), SigningWorkflowTypeHandler::ACTION_DELETE, [], 'en');

        $this->expectException(NotFoundHttpException::class);
        $this->workflows->handleAction($workflow->getId(), SigningWorkflowTypeHandler::ACTION_DELETE, [], 'en');
    }

    public function testVisibilityUsesPersonIds(): void
    {
        $workflow = $this->create();
        foreach (['owner-id' => true, 'approver-id' => true, 'guest@example.com' => false, 'unrelated-id' => false] as $personId => $allowed) {
            $this->currentPersonId = $personId;
            self::assertSame($allowed, $this->workflows->canUse($workflow));
        }
        $this->currentPersonId = null;
        self::assertFalse($this->workflows->canUse($workflow));
    }

    public function testTasksFollowTheCurrentIterationWithoutClosingOnSuccess(): void
    {
        $workflow = $this->create();
        $state = SigningWorkflowState::fromArray($workflow->getInternalState());
        $firstTask = $state->iterations[0]->invitees[0]->id;
        $secondTask = $state->iterations[1]->invitees[0]->id;
        $state->iterations[0]->signedDocumentFileId = $state->documentToSignFileId;
        $workflow->setInternalState($state->toArray());
        $this->container->get('doctrine.orm.dbp_relay_portfolio_bundle_entity_manager')->flush();
        $this->workflows->pingAll();
        self::assertNull($this->workflows->getTask($firstTask));
        self::assertNotNull($this->workflows->getTask($secondTask));
        $state->iterations[1]->signedDocumentFileId = $state->documentToSignFileId;
        $state->status = SignJobState::FINISHED_SUCCESS;
        $workflow->setInternalState($state->toArray());
        $this->container->get('doctrine.orm.dbp_relay_portfolio_bundle_entity_manager')->flush();
        $this->workflows->pingAll();
        self::assertNull($this->workflows->getTask($secondTask));
        self::assertTrue($workflow->isActive());
        self::assertSame(SignJobState::FINISHED_SUCCESS, $this->sign->getJobState($workflow->getId(), 'EMAIL')->getState());
    }

    #[DataProvider('invalidJobs')]
    public function testInvalidRequestsLeaveNoWorkflowOrFiles(array $path, mixed $value): void
    {
        $job = SigningTestUtils::jobDescription();
        $target = &$job;
        foreach ($path as $key) {
            $target = &$target[$key];
        }
        $target = $value;
        unset($target);
        self::assertSame(400, $this->controller->startProcess('process49', $this->request($job))->getStatusCode());
        $this->assertNothingCreated();
    }

    public static function invalidJobs(): iterable
    {
        yield 'unsupported signing category' => [['iterationData', 0, 'category'], 'QSIG'];
        yield 'unknown owner' => [['constituent', 'name'], 'unknown@example.com'];
        yield 'unknown internal signer' => [['iterationData', 0, 'invitees', 0, 'name'], 'unknown@example.com'];
    }

    public function testRejectsUnsupportedFileParts(): void
    {
        foreach ([
            ['documentToSign' => [SigningTestUtils::pdf(), SigningTestUtils::pdf()]],
            ['documentToSign' => SigningTestUtils::pdf(), 'attachments' => [[SigningTestUtils::pdf()]]],
            ['documentToSign' => SigningTestUtils::pdf(), 'secondDocument' => SigningTestUtils::pdf()],
        ] as $files) {
            self::assertSame(400, $this->controller->startProcess('process49', $this->request(files: $files))->getStatusCode());
            $this->assertNothingCreated();
        }
    }

    public function testAcceptsAnUnbracketedSingleAttachment(): void
    {
        self::assertSame(201, $this->controller->startProcess('process49', $this->request(files: [
            'documentToSign' => SigningTestUtils::pdf(), 'attachments' => SigningTestUtils::pdf('attachment.pdf'),
        ]))->getStatusCode());
    }

    public function testRejectsNonPdfBeforeUploadingAnything(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'not-pdf-');
        file_put_contents($path, 'Not a PDF');
        try {
            $upload = new UploadedFile($path, 'bad.pdf', 'application/pdf', null, true);
            self::assertSame(400, $this->controller->startProcess('process49', $this->request(files: [
                'documentToSign' => SigningTestUtils::pdf(), 'attachments' => [$upload],
            ]))->getStatusCode());
            $this->assertNothingCreated();
        } finally {
            unlink($path);
        }
    }

    public function testDirectoryFailurePropagatesWithoutCreatingAnything(): void
    {
        $this->lookupFails = true;
        try {
            $this->controller->startProcess('process49', $this->request());
            self::fail('Directory failure should propagate.');
        } catch (\RuntimeException $error) {
            self::assertSame('Directory failed', $error->getMessage());
        }
        $this->assertNothingCreated();
    }

    public function testNoDummyCompletionOrDocumentAndCleanupRemovesFiles(): void
    {
        $workflow = $this->create();
        self::assertSame(404, $this->controller->getDocument($workflow->getId(), $this->request())->getStatusCode());
        $cancel = new Request(content: json_encode(SigningTestUtils::jobDescription()['constituent']));
        $cancel->headers->set('Authorization', 'Basic '.base64_encode('svc_user:svc_pass'));
        self::assertSame(501, $this->controller->cancelJob($workflow->getId(), $cancel)->getStatusCode());
        self::assertSame(SignJobState::ACTIVE, $this->sign->getJobState($workflow->getId(), 'EMAIL')->getState());
        $handler = $this->container->get(SigningWorkflowTypeHandler::class);
        self::assertSame([SigningWorkflowTypeHandler::ACTION_DELETE, SigningWorkflowTypeHandler::ACTION_VIEW_DOCUMENT], array_map(static fn ($action): string => $action->getId(), $handler->getAvailableActions($this->data($workflow), 'en')));
        self::assertStringContainsString('0 / 2', $handler->getStatusDisplay($this->data($workflow), 'en')->getDescription());
        $this->workflows->softDeleteWorkflow($workflow->getId());
        self::assertNull($this->sign->resolveProcessId($workflow->getId()));
        $this->workflows->cleanupAll();
        $this->assertNothingCreated();
    }
}
