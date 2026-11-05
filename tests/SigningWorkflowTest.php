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
        self::assertSame(SignJobState::ACTIVE, (new SignService($this->workflows, $this->container->get(SigningWorkflowTypeHandler::class), $this->container->get(WorkflowFileService::class)))->getJobState($id, 'EMAIL')->getState());
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
        $handler = $this->container->get(SigningWorkflowTypeHandler::class);
        self::assertSame('Filename: contract.pdf', $handler->getAvailableActions($this->data($workflow), 'en')[1]->getDescription());
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

    private function cancelRequest(string $email = 'approver@example.com', string $classifier = 'EMAIL'): Request
    {
        $user = SigningTestUtils::jobDescription()['constituent'];
        $user['name'] = $email;
        $user['classifier'] = $classifier;
        $request = new Request(content: json_encode($user, JSON_THROW_ON_ERROR));
        $request->headers->set('Authorization', 'Basic '.base64_encode('svc_user:svc_pass'));

        return $request;
    }

    public function testCancellationPersistsPersonIdAndClosesWorkflowAndRemovesTasks(): void
    {
        $workflow = $this->create();
        $id = $workflow->getId();
        $state = SigningWorkflowState::fromArray($workflow->getInternalState());
        $taskId = $state->iterations[0]->invitees[0]->id;
        $response = $this->controller->cancelJob($id, $this->cancelRequest());
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(['state' => 'FINISHED_WF_CANCELLED'], json_decode($response->getContent(), true));

        $em = $this->container->get('doctrine.orm.dbp_relay_portfolio_bundle_entity_manager');
        $em->clear();
        $workflow = $this->workflows->getWorkflow($id);
        self::assertFalse($workflow->isActive());
        self::assertNotNull($workflow->getClosedAt());
        $closedAt = $workflow->getClosedAt();
        self::assertSame('approver-id', $workflow->getInternalState()['cancelledByUserId']);
        self::assertSame('approver@example.com', $workflow->getInternalState()['cancelledByEmail']);
        self::assertSame(SignJobState::FINISHED_WF_CANCELLED, $this->sign->getJobState($id, 'EMAIL')->getState());
        $handler = $this->container->get(SigningWorkflowTypeHandler::class);
        $display = $handler->getStatusDisplay($this->data($workflow), 'en');
        self::assertSame('Cancelled in SAP', $display->getLabel());
        self::assertSame('In SAP abgebrochen', $handler->getStatusDisplay($this->data($workflow), 'de')->getLabel());
        self::assertSame('Der Signaturworkflow wurde in SAP abgebrochen (von approver@example.com).', $handler->getStatusDisplay($this->data($workflow), 'de')->getDescription());
        self::assertNull($this->workflows->getTask($taskId));
        self::assertNotNull($this->container->get(WorkflowFileService::class)->get($id, $state->documentToSignFileId));

        self::assertSame(200, $this->controller->cancelJob($id, $this->cancelRequest('owner@example.com'))->getStatusCode());
        self::assertSame($closedAt, $workflow->getClosedAt());
        self::assertSame('approver-id', $workflow->getInternalState()['cancelledByUserId']);
        self::assertSame('approver@example.com', $workflow->getInternalState()['cancelledByEmail']);
    }

    public function testCancellationRejectsNonEmailClassifier(): void
    {
        $workflow = $this->create();
        $state = $workflow->getInternalState();
        $response = $this->controller->cancelJob($workflow->getId(), $this->cancelRequest('approver-id', 'ID'));
        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['error' => 'Only classifier "EMAIL" is supported.'], json_decode($response->getContent(), true));
        self::assertSame($state, $workflow->getInternalState());
    }

    public function testViewAttachmentActionsUseSignedUrlsWithoutChangingSigningState(): void
    {
        $response = $this->controller->startProcess('process49', $this->request(files: [
            'documentToSign' => SigningTestUtils::pdf(),
            'attachments' => [SigningTestUtils::pdf('supporting.pdf')],
        ]));
        self::assertSame(201, $response->getStatusCode());
        $workflow = $this->workflows->getWorkflow((string) $response->getContent());
        $state = $workflow->getInternalState();
        $handler = $this->container->get(SigningWorkflowTypeHandler::class);
        $action = $handler->getAvailableActions($this->data($workflow), 'en')[2];
        self::assertSame('View attachment: supporting.pdf', $action->getLabel());
        self::assertSame('Filename: supporting.pdf', $action->getDescription());

        $result = $this->workflows->handleAction($workflow->getId(), $action->getId(), [], 'en');
        self::assertTrue($this->container->get(\Symfony\Component\HttpFoundation\UriSigner::class)->check($result->getUrl()));
        parse_str(parse_url($result->getUrl(), PHP_URL_QUERY), $query);
        self::assertSame(SigningWorkflowTypeHandler::RENDER_ATTACHMENT_PREFIX.$state['attachments'][0]['fileId'], $query['renderId']);
        $render = $this->workflows->getRenderResponse($workflow, $query['renderId'], 'en');
        self::assertSame('application/pdf', $render->getContentType());
        self::assertSame(SigningTestUtils::pdf()->getContent(), $render->getHtml());
        self::assertSame($state, $workflow->getInternalState());
    }

    public function testRejectsViewingDocumentAsAttachment(): void
    {
        $workflow = $this->create();
        $fileId = $workflow->getInternalState()['documentToSignFileId'];
        $this->expectException(NotFoundHttpException::class);
        $this->container->get(SigningWorkflowTypeHandler::class)->handleAction(
            $this->data($workflow), SigningWorkflowTypeHandler::ACTION_VIEW_ATTACHMENT_PREFIX.$fileId, [], 'en',
        );
    }

    public function testRejectsRenderingDocumentAsAttachment(): void
    {
        $workflow = $this->create();
        $fileId = $workflow->getInternalState()['documentToSignFileId'];
        $this->expectException(NotFoundHttpException::class);
        $this->workflows->getRenderResponse($workflow, SigningWorkflowTypeHandler::RENDER_ATTACHMENT_PREFIX.$fileId, 'en');
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

    public function testSignatureActionsAdvanceTasksAndCloseOnRetrieval(): void
    {
        $workflow = $this->create();
        $state = SigningWorkflowState::fromArray($workflow->getInternalState());
        $firstTask = $state->iterations[0]->invitees[0]->id;
        $secondTask = $state->iterations[1]->invitees[0]->id;
        $handler = $this->container->get(SigningWorkflowTypeHandler::class);
        $actionIds = fn (): array => array_map(static fn ($action): string => $action->getId(), $handler->getAvailableActions($this->data($workflow), 'en'));
        self::assertContains(SigningWorkflowTypeHandler::ACTION_FIRST_SIGNATURE, $actionIds());
        self::assertNotContains(SigningWorkflowTypeHandler::ACTION_VIEW_FIRST_SIGNED_DOCUMENT, $actionIds());
        self::assertNotContains(SigningWorkflowTypeHandler::ACTION_VIEW_FINAL_DOCUMENT, $actionIds());
        self::assertNotContains(SigningWorkflowTypeHandler::ACTION_SECOND_SIGNATURE, $actionIds());
        $this->workflows->handleAction($workflow->getId(), SigningWorkflowTypeHandler::ACTION_FIRST_SIGNATURE, [], 'en');
        $state = SigningWorkflowState::fromArray($workflow->getInternalState());
        $firstFileId = $state->iterations[0]->signedDocumentFileId;
        self::assertNotSame($state->documentToSignFileId, $firstFileId);
        self::assertSame(SignJobState::ACTIVE, $state->status);
        self::assertNotContains(SigningWorkflowTypeHandler::ACTION_SAP_RETRIEVE, $actionIds());
        self::assertTrue($workflow->isActive());
        self::assertSame(404, $this->controller->getDocument($workflow->getId(), $this->request())->getStatusCode());
        self::assertNull($workflow->getInternalState()['retrievedAt']);
        self::assertNull($this->workflows->getTask($firstTask));
        self::assertNotNull($this->workflows->getTask($secondTask));
        self::assertContains(SigningWorkflowTypeHandler::ACTION_VIEW_FIRST_SIGNED_DOCUMENT, $actionIds());
        self::assertNotContains(SigningWorkflowTypeHandler::ACTION_VIEW_FINAL_DOCUMENT, $actionIds());
        self::assertNotContains(SigningWorkflowTypeHandler::ACTION_FIRST_SIGNATURE, $actionIds());
        self::assertContains(SigningWorkflowTypeHandler::ACTION_SECOND_SIGNATURE, $actionIds());
        $this->workflows->handleAction($workflow->getId(), SigningWorkflowTypeHandler::ACTION_SECOND_SIGNATURE, [], 'en');
        $state = SigningWorkflowState::fromArray($workflow->getInternalState());
        self::assertNotSame($firstFileId, $state->iterations[1]->signedDocumentFileId);
        $files = $this->container->get(WorkflowFileService::class);
        foreach ([$firstFileId, $state->iterations[1]->signedDocumentFileId] as $fileId) {
            self::assertSame(SigningTestUtils::pdf()->getContent(), $files->download($workflow->getId(), $fileId)->getFileStream()->getContents());
        }
        foreach ([SigningWorkflowTypeHandler::ACTION_VIEW_FIRST_SIGNED_DOCUMENT, SigningWorkflowTypeHandler::ACTION_VIEW_FINAL_DOCUMENT] as $number => $action) {
            self::assertContains($action, $actionIds());
            $result = $this->workflows->handleAction($workflow->getId(), $action, [], 'en');
            self::assertTrue($this->container->get(\Symfony\Component\HttpFoundation\UriSigner::class)->check($result->getUrl()));
            parse_str(parse_url($result->getUrl(), PHP_URL_QUERY), $query);
            self::assertSame(SigningWorkflowTypeHandler::RENDER_SIGNED_DOCUMENT_PREFIX.$number, $query['renderId']);
            self::assertSame(SigningTestUtils::pdf()->getContent(), $this->workflows->getRenderResponse($workflow, $query['renderId'], 'en')->getHtml());
        }
        self::assertNull($this->workflows->getTask($secondTask));
        self::assertNotContains(SigningWorkflowTypeHandler::ACTION_SECOND_SIGNATURE, $actionIds());
        self::assertTrue($workflow->isActive());
        self::assertNull($workflow->getClosedAt());
        self::assertSame(SignJobState::FINISHED_SUCCESS, $this->sign->getJobState($workflow->getId(), 'EMAIL')->getState());
        self::assertSame('Waiting for SAP to retrieve', $handler->getStatusDisplay($this->data($workflow), 'en')->getLabel());
        self::assertStringContainsString('Workflow ID: '.$workflow->getId(), $handler->getStatusDisplay($this->data($workflow), 'en')->getDescription());
        $this->workflows->getRenderResponse($workflow, SigningWorkflowTypeHandler::RENDER_DOCUMENT, 'en');
        self::assertNull($workflow->getInternalState()['retrievedAt']);
        self::assertContains(SigningWorkflowTypeHandler::ACTION_SAP_RETRIEVE, $actionIds());
        $this->workflows->handleAction($workflow->getId(), SigningWorkflowTypeHandler::ACTION_SAP_RETRIEVE, [], 'en');
        self::assertNotNull($workflow->getInternalState()['retrievedAt']);
        self::assertNotContains(SigningWorkflowTypeHandler::ACTION_SAP_RETRIEVE, $actionIds());
        self::assertFalse($workflow->isActive());
        self::assertNotNull($workflow->getClosedAt());
        self::assertSame('Transferred to SAP', $handler->getStatusDisplay($this->data($workflow), 'en')->getLabel());
        $response = $this->controller->getDocument($workflow->getId(), $this->request());
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertSame($files->download($workflow->getId(), $state->iterations[1]->signedDocumentFileId)->getFileStream()->getContents(), $response->getContent());
        $id = $workflow->getId();
        $this->container->get('doctrine.orm.dbp_relay_portfolio_bundle_entity_manager')->clear();
        $workflow = $this->workflows->getWorkflow($id);
        $closedAt = $workflow->getClosedAt();
        $retrievedAt = $workflow->getInternalState()['retrievedAt'];
        self::assertNotNull($retrievedAt);
        self::assertSame('Transferred to SAP', $handler->getStatusDisplay($this->data($workflow), 'en')->getLabel());
        self::assertSame('An SAP übertragen', $handler->getStatusDisplay($this->data($workflow), 'de')->getLabel());
        self::assertSame(200, $this->controller->getDocument($id, $this->request())->getStatusCode());
        self::assertGreaterThan(new \DateTimeImmutable($retrievedAt), new \DateTimeImmutable($workflow->getInternalState()['retrievedAt']));
        self::assertSame($closedAt, $workflow->getClosedAt());
        self::assertFalse($workflow->isActive());
        self::assertContains(SigningWorkflowTypeHandler::ACTION_VIEW_FIRST_SIGNED_DOCUMENT, $actionIds());
        self::assertContains(SigningWorkflowTypeHandler::ACTION_VIEW_FINAL_DOCUMENT, $actionIds());
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
        self::assertSame(SignJobState::ACTIVE, $this->sign->getJobState($workflow->getId(), 'EMAIL')->getState());
        $handler = $this->container->get(SigningWorkflowTypeHandler::class);
        $actions = $handler->getAvailableActions($this->data($workflow), 'en');
        self::assertSame([SigningWorkflowTypeHandler::ACTION_DELETE, SigningWorkflowTypeHandler::ACTION_VIEW_DOCUMENT, SigningWorkflowTypeHandler::ACTION_SAP_CANCEL, SigningWorkflowTypeHandler::ACTION_FIRST_SIGNATURE], array_map(static fn ($action): string => $action->getId(), $actions));
        self::assertSame('SAP cancel', $actions[2]->getLabel());
        self::assertStringContainsString('0 / 2', $handler->getStatusDisplay($this->data($workflow), 'en')->getDescription());
        $this->workflows->handleAction($workflow->getId(), SigningWorkflowTypeHandler::ACTION_SAP_CANCEL, [], 'en');
        self::assertFalse($workflow->isActive());
        self::assertSame('owner@example.com', $workflow->getInternalState()['cancelledByEmail']);
        self::assertSame('owner-id', $workflow->getInternalState()['cancelledByUserId']);
        self::assertSame(SignJobState::FINISHED_WF_CANCELLED, $this->sign->getJobState($workflow->getId(), 'EMAIL')->getState());
        self::assertSame([], $this->workflows->getTasksForWorkflow($workflow->getId(), 1, 10));
        $this->workflows->softDeleteWorkflow($workflow->getId());
        self::assertNull($this->sign->resolveProcessId($workflow->getId()));
        $this->workflows->cleanupAll();
        $this->assertNothingCreated();
    }
}
