<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Workflow\Signing;

use Dbp\Relay\BasePersonBundle\API\PersonProviderInterface;
use Dbp\Relay\PortfolioBundle\Handler\Action;
use Dbp\Relay\PortfolioBundle\Handler\CleanupResult;
use Dbp\Relay\PortfolioBundle\Handler\RenderResult;
use Dbp\Relay\PortfolioBundle\Handler\StatusDisplay;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowActionResult;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowData;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerHelper;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerInterface;
use Dbp\Relay\PortfolioBundle\SignApi\SignException;
use Dbp\Relay\PortfolioBundle\SignApi\SignJobDescription;
use Dbp\Relay\PortfolioBundle\SignApi\SignJobState;
use Dbp\Relay\PortfolioBundle\SignApi\SignUtils;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningInvitee;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningIteration;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningWorkflowState;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Uid\Uuid;

class SigningWorkflowTypeHandler implements WorkflowTypeHandlerInterface
{
    public const TYPE = 'signing';
    public const ACTION_DELETE = 'delete';
    public const ACTION_SAP_CANCEL = 'sap-cancel';
    public const ACTION_SAP_RETRIEVE = 'sap-retrieve';
    public const ACTION_EXPIRE = 'expire';
    public const ACTION_FIRST_SIGNATURE = 'first-signature';
    public const ACTION_SECOND_SIGNATURE = 'second-signature';
    public const ACTION_VIEW_DOCUMENT = 'view-document';
    public const ACTION_VIEW_FIRST_SIGNED_DOCUMENT = 'view-first-signed-document';
    public const ACTION_VIEW_FINAL_DOCUMENT = 'view-final-document';
    public const ACTION_VIEW_ATTACHMENT_PREFIX = 'view-attachment-';
    public const RENDER_DOCUMENT = 'document';
    public const RENDER_SIGNED_DOCUMENT_PREFIX = 'signed-document-';
    public const RENDER_ATTACHMENT_PREFIX = 'attachment-';

    private readonly Translator $translator;

    public function __construct(
        private readonly WorkflowTypeHandlerHelper $helper,
        private readonly SigningRequestValidator $validator,
        private readonly SigningEmailResolver $emailResolver,
        private readonly PersonProviderInterface $persons,
    ) {
        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', __DIR__.'/Resources/translations/messages.en.yaml', 'en');
        $translator->addResource('yaml', __DIR__.'/Resources/translations/messages.de.yaml', 'de');
        $this->translator = $translator;
    }

    public function create(string $workflowId, array $input): array
    {
        $processId = SignUtils::requireString($input, 'signingProcessId');
        if ($processId !== SignService::PROCESS_ID) {
            throw new SignException('Unsupported signing process.');
        }
        $job = $input['jobDescription'] ?? null;
        if (!$job instanceof SignJobDescription) {
            throw new SignException('The "jobDescription" field must be a parsed signing job.');
        }
        $job = $this->validator->validate($job);
        $document = $this->readPdf($input['documentToSign'] ?? null, 'documentToSign', 'contract.pdf');
        $attachments = $input['attachments'] ?? [];
        if (!is_array($attachments) || !array_is_list($attachments)) {
            throw new SignException('Attachments must be a list of uploaded PDFs.');
        }
        foreach ($attachments as $index => $attachment) {
            $attachments[$index] = $this->readPdf($attachment, 'attachments['.$index.']', 'attachment-'.($index + 1).'.pdf');
        }

        // Resolve all internal identities before uploading any files.
        $owner = $this->emailResolver->resolve($job->getConstituent()->getName());
        $iterations = [];
        foreach ($job->getIterationData() as $iteration) {
            $user = $iteration->getInvitees()[0];
            $internal = $iteration->getIterationNumber() === 0 ? $this->emailResolver->resolve($user->getName()) : null;
            $iterations[] = new SigningIteration($iteration->getIterationNumber(), $iteration->getCategory(), [
                new SigningInvitee(
                    id: Uuid::v4()->toRfc4122(),
                    email: $user->getName(),
                    userId: $internal['personId'] ?? null,
                    displayName: $internal['displayName'] ?? ($user->getExternalUserName() ?: $user->getName()),
                    locale: $user->getLocale(),
                ),
            ]);
        }

        $files = $this->helper->getFiles($workflowId);
        $documentId = $files->store($document['filename'], 'application/pdf', $document['contents']);
        foreach ($attachments as &$attachment) {
            $attachment = [
                'fileId' => $files->store($attachment['filename'], 'application/pdf', $attachment['contents']),
                'filename' => $attachment['filename'],
            ];
        }
        unset($attachment);
        $metadata = $job->getMetaData();

        return (new SigningWorkflowState(
            signingProcessId: $processId,
            constituentEmail: $job->getConstituent()->getName(),
            constituentUserId: $owner['personId'],
            documentToSignFileId: $documentId,
            documentToSignFilename: $document['filename'],
            iterations: $iterations,
            attachments: $attachments,
            description: $metadata?->getDescription(),
            referenceId: $metadata?->getReferenceId(),
            expirationDate: $metadata?->getExpirationDate() !== null ? new \DateTimeImmutable($metadata->getExpirationDate()) : null,
            constituentDisplayName: $owner['displayName'],
        ))->toArray();
    }

    /** @return array{filename: string, contents: string} */
    private function readPdf(mixed $upload, string $field, string $fallbackFilename): array
    {
        if (!$upload instanceof UploadedFile || !$upload->isValid()) {
            throw new SignException(sprintf('The "%s" field must be a successfully uploaded PDF.', $field));
        }
        $contents = $upload->getContent();
        if (!str_starts_with($contents, '%PDF-')) {
            throw new SignException(sprintf('The "%s" field must contain a PDF.', $field));
        }

        return [
            'filename' => basename(str_replace('\\', '/', $upload->getClientOriginalName())) ?: $fallbackFilename,
            'contents' => $contents,
        ];
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getName(WorkflowData $workflow, string $lang): string
    {
        $name = $this->translator->trans('signing_workflow.name', locale: $lang);
        $identity = $this->getSignerIdentity(SigningWorkflowState::fromArray($workflow->getInternalState()), true);

        return $identity !== null ? $name.' — '.$identity : $name;
    }

    public function getDescription(WorkflowData $workflow, string $lang): string
    {
        $state = SigningWorkflowState::fromArray($workflow->getInternalState());
        $description = $state->description ?? $this->translator->trans('signing_workflow.description', locale: $lang);
        foreach (['approver' => false, 'external_signer' => true] as $key => $external) {
            $identity = $this->getSignerIdentity($state, $external);
            if ($identity !== null) {
                $description = trim($description.' '.$this->translator->trans('signing_workflow.'.$key, ['%signer%' => $identity], locale: $lang));
            }
        }
        if ($state->expirationDate !== null) {
            $description = trim($description.' '.$this->translator->trans('signing_workflow.expiration', [
                '%date%' => $state->expirationDate->format('Y-m-d H:i:s T'),
            ], locale: $lang));
        }
        if ($state->referenceId !== null) {
            $description = trim($description.' '.$this->translator->trans('signing_workflow.reference_id', [
                '%referenceId%' => $state->referenceId,
            ], locale: $lang));
        }

        return trim($description.' '.$this->translator->trans('signing_workflow.workflow_id', ['%workflowId%' => $workflow->getId()], locale: $lang));
    }

    private function getSignerIdentity(SigningWorkflowState $state, bool $external): ?string
    {
        foreach ($state->iterations as $iteration) {
            foreach ($iteration->invitees as $invitee) {
                if ($invitee->isExternal() === $external) {
                    $name = trim($invitee->displayName ?? '');

                    return $name !== '' && $name !== $invitee->email ? $name.' ('.$invitee->email.')' : $invitee->email;
                }
            }
        }

        return null;
    }

    public function getStatusDisplay(WorkflowData $workflow, string $lang): StatusDisplay
    {
        $state = SigningWorkflowState::fromArray($workflow->getInternalState());
        if ($state->status === SignJobState::FINISHED_WF_CANCELLED) {
            return new StatusDisplay(
                $this->translator->trans('signing_workflow.state.cancelled', locale: $lang),
                $this->translator->trans(
                    'signing_workflow.state.'.($state->cancelledByEmail !== null ? 'cancelled_by_desc' : 'cancelled_desc'),
                    ['%email%' => $state->cancelledByEmail ?? ''],
                    locale: $lang,
                ),
            );
        }
        $total = count($state->iterations);
        $signed = count(array_filter($state->iterations, static fn (SigningIteration $iteration): bool => $iteration->signedDocumentFileId !== null));
        $key = match ($state->status) {
            SignJobState::FINISHED_SUCCESS => $state->retrievedAt !== null ? 'transferred' : 'waiting_for_sap',
            SignJobState::FINISHED_TIMEOUT => 'expired',
            default => 'pending',
        };

        return new StatusDisplay(
            $state->status->isFinal() && $key === 'pending' ? $state->status->value : $this->translator->trans('signing_workflow.state.'.$key, locale: $lang),
            $this->translator->trans('signing_workflow.state.'.$key.'_desc', [
                '%signed%' => $signed,
                '%total%' => $total,
                '%date%' => $state->retrievedAt?->format('Y-m-d H:i:s T') ?? '',
            ], locale: $lang),
        );
    }

    public function canUse(WorkflowData $workflow): bool
    {
        $personId = $this->persons->getCurrentPersonIdentifier();
        if ($personId === null) {
            return false;
        }
        $state = SigningWorkflowState::fromArray($workflow->getInternalState());
        if ($state->constituentUserId === $personId) {
            return true;
        }
        foreach ($state->iterations as $iteration) {
            foreach ($iteration->invitees as $invitee) {
                if ($invitee->userId === $personId) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getAvailableActions(WorkflowData $workflow, string $lang): array
    {
        if ($workflow->isDeleted()) {
            return [];
        }

        $state = SigningWorkflowState::fromArray($workflow->getInternalState());
        $actions = [
            new Action(
                self::ACTION_VIEW_DOCUMENT,
                $this->translator->trans('signing_workflow.action.view_document', locale: $lang),
                description: $this->translator->trans('signing_workflow.action.file_description', ['%filename%' => $state->getCurrentDocument()['filename']], locale: $lang),
            ),
        ];
        foreach ($state->attachments as $attachment) {
            $actions[] = new Action(
                self::ACTION_VIEW_ATTACHMENT_PREFIX.$attachment['fileId'],
                $this->translator->trans('signing_workflow.action.view_attachment', ['%filename%' => $attachment['filename']], locale: $lang),
                description: $this->translator->trans('signing_workflow.action.file_description', ['%filename%' => $attachment['filename']], locale: $lang),
            );
        }

        foreach ($state->iterations as $iteration) {
            if ($iteration->signedDocumentFileId !== null) {
                $first = $iteration->number === 0;
                $document = $this->getSignedDocument($state, (string) $iteration->number);
                $actions[] = new Action(
                    $first ? self::ACTION_VIEW_FIRST_SIGNED_DOCUMENT : self::ACTION_VIEW_FINAL_DOCUMENT,
                    $this->translator->trans('signing_workflow.action.'.($first ? 'view_first_signed_document' : 'view_final_document'), locale: $lang),
                    description: $this->translator->trans('signing_workflow.action.file_description', ['%filename%' => $document['filename']], locale: $lang),
                );
            }
        }

        if ($workflow->isActive() && !$state->status->isFinal()) {
            $actions[] = new Action(self::ACTION_SAP_CANCEL, $this->translator->trans('signing_workflow.action.sap_cancel', locale: $lang));
            $iteration = $state->getCurrentIteration();
            if ($iteration !== null) {
                $first = $iteration->number === 0;
                $actions[] = new Action(
                    $first ? self::ACTION_FIRST_SIGNATURE : self::ACTION_SECOND_SIGNATURE,
                    $this->translator->trans('signing_workflow.action.'.($first ? 'first_signature' : 'second_signature'), locale: $lang),
                );
            }
            $actions[] = new Action(self::ACTION_EXPIRE, $this->translator->trans('signing_workflow.action.expire', locale: $lang));
        }
        if ($state->status === SignJobState::FINISHED_SUCCESS && $state->retrievedAt === null) {
            $actions[] = new Action(self::ACTION_SAP_RETRIEVE, $this->translator->trans('signing_workflow.action.sap_retrieve', locale: $lang));
        }
        $actions[] = new Action(self::ACTION_DELETE, $this->translator->trans('signing_workflow.action.delete', locale: $lang));

        return $actions;
    }

    public function handleAction(WorkflowData $workflow, string $action, array $payload, string $lang): WorkflowActionResult
    {
        if ($action === self::ACTION_EXPIRE) {
            $state = SigningWorkflowState::fromArray($workflow->getInternalState());
            $state->expirationDate = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

            return $this->expire($state);
        }
        if ($action === self::ACTION_VIEW_FIRST_SIGNED_DOCUMENT || $action === self::ACTION_VIEW_FINAL_DOCUMENT) {
            $number = $action === self::ACTION_VIEW_FIRST_SIGNED_DOCUMENT ? '0' : '1';
            $this->getSignedDocument(SigningWorkflowState::fromArray($workflow->getInternalState()), $number);

            return new WorkflowActionResult(
                internalState: $workflow->getInternalState(),
                url: $this->helper->getSignedRenderUrl($workflow->getId(), self::RENDER_SIGNED_DOCUMENT_PREFIX.$number),
            );
        }
        if ($action === self::ACTION_SAP_RETRIEVE) {
            return $this->recordRetrieval($workflow);
        }
        if ($action === self::ACTION_FIRST_SIGNATURE || $action === self::ACTION_SECOND_SIGNATURE) {
            return $this->signIteration($workflow, $action === self::ACTION_FIRST_SIGNATURE ? 0 : 1);
        }
        if ($action === self::ACTION_SAP_CANCEL) {
            return $this->cancel($workflow, SigningWorkflowState::fromArray($workflow->getInternalState())->constituentEmail);
        }
        if ($action === self::ACTION_VIEW_DOCUMENT) {
            return new WorkflowActionResult(
                internalState: $workflow->getInternalState(),
                url: $this->helper->getSignedRenderUrl($workflow->getId(), self::RENDER_DOCUMENT),
            );
        }
        if (str_starts_with($action, self::ACTION_VIEW_ATTACHMENT_PREFIX)) {
            $fileId = substr($action, strlen(self::ACTION_VIEW_ATTACHMENT_PREFIX));
            $this->getAttachment(SigningWorkflowState::fromArray($workflow->getInternalState()), $fileId);

            return new WorkflowActionResult(
                internalState: $workflow->getInternalState(),
                url: $this->helper->getSignedRenderUrl($workflow->getId(), self::RENDER_ATTACHMENT_PREFIX.$fileId),
            );
        }
        if ($action === self::ACTION_DELETE) {
            return new WorkflowActionResult(internalState: $workflow->getInternalState(), delete: true);
        }
        throw new \RuntimeException(sprintf('Signing action "%s" is not implemented yet.', $action));
    }

    private function signIteration(WorkflowData $workflow, int $number): WorkflowActionResult
    {
        $state = SigningWorkflowState::fromArray($workflow->getInternalState());
        $iteration = $state->getCurrentIteration();
        if (!$workflow->isActive() || $iteration === null || $iteration->number !== $number) {
            throw new BadRequestHttpException('Signature does not belong to the current signing iteration.');
        }

        $document = $state->getCurrentDocument();
        $files = $this->helper->getFiles($workflow->getId());
        // Placeholder: copy the current PDF without applying a real signature.
        $contents = $files->download($document['fileId'])->getFileStream()->getContents();
        $iteration->signedDocumentFileId = $files->store($document['filename'], 'application/pdf', $contents);
        $iteration->signedDocumentFilename = $document['filename'];
        if ($state->getCurrentIteration() === null) {
            $state->status = SignJobState::FINISHED_SUCCESS;
        }

        return new WorkflowActionResult(internalState: $state->toArray());
    }

    public function recordRetrieval(WorkflowData $workflow): WorkflowActionResult
    {
        $state = SigningWorkflowState::fromArray($workflow->getInternalState());
        if ($state->status !== SignJobState::FINISHED_SUCCESS) {
            throw new BadRequestHttpException('The final document is not available for retrieval yet.');
        }
        $state->retrievedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new WorkflowActionResult(
            internalState: $state->toArray(),
            close: $workflow->isActive() ? true : null,
        );
    }

    public function cancel(WorkflowData $workflow, string $email): WorkflowActionResult
    {
        $state = SigningWorkflowState::fromArray($workflow->getInternalState());
        if (!$state->status->isFinal()) {
            $state->cancelledByUserId = $this->emailResolver->resolve($email)['personId'];
            $state->cancelledByEmail = trim($email);
            $state->status = SignJobState::FINISHED_WF_CANCELLED;
        }

        return new WorkflowActionResult(
            internalState: $state->toArray(),
            close: $state->status === SignJobState::FINISHED_WF_CANCELLED && $workflow->isActive() ? true : null,
        );
    }

    public function getExpectedTasks(WorkflowData $workflow): array
    {
        if (!$workflow->isActive()) {
            return [];
        }
        $iteration = SigningWorkflowState::fromArray($workflow->getInternalState())->getCurrentIteration();

        return $iteration !== null ? [$iteration->invitees[0]->id] : [];
    }

    public function getTaskResponse(WorkflowData $workflow, string $taskId, string $lang): array
    {
        if (!in_array($taskId, $this->getExpectedTasks($workflow), true)) {
            throw new NotFoundHttpException('Task does not belong to the current signing iteration.');
        }
        $state = SigningWorkflowState::fromArray($workflow->getInternalState());
        $files = $this->helper->getFiles($workflow->getId());
        $document = $state->getCurrentDocument();
        $attachments = [];
        foreach ($state->attachments as $attachment) {
            $attachments[] = ['filename' => $attachment['filename'], 'url' => $files->createDownloadUrl($attachment['fileId'])];
        }

        return [
            'documents' => [[
                'workflowTrackingId' => $taskId,
                'filename' => $document['filename'],
                'url' => $files->createDownloadUrl($document['fileId']),
            ]],
            'attachments' => $attachments,
        ];
    }

    public function getRenderResponse(WorkflowData $workflow, string $renderId, string $lang): RenderResult
    {
        $state = SigningWorkflowState::fromArray($workflow->getInternalState());
        if ($renderId === self::RENDER_DOCUMENT) {
            $document = $state->getCurrentDocument();
        } elseif (str_starts_with($renderId, self::RENDER_SIGNED_DOCUMENT_PREFIX)) {
            $document = $this->getSignedDocument($state, substr($renderId, strlen(self::RENDER_SIGNED_DOCUMENT_PREFIX)));
        } elseif (str_starts_with($renderId, self::RENDER_ATTACHMENT_PREFIX)) {
            $document = $this->getAttachment($state, substr($renderId, strlen(self::RENDER_ATTACHMENT_PREFIX)));
        } else {
            throw new NotFoundHttpException('Unknown signing render ID.');
        }
        $stream = $this->helper->getFiles($workflow->getId())->download($document['fileId'])->getFileStream();

        return new RenderResult($stream->getContents(), 'application/pdf');
    }

    /** @return array{fileId: string, filename: string} */
    private function getSignedDocument(SigningWorkflowState $state, string $number): array
    {
        foreach ($state->iterations as $iteration) {
            if ((string) $iteration->number === $number && $iteration->signedDocumentFileId !== null) {
                return [
                    'fileId' => $iteration->signedDocumentFileId,
                    'filename' => $iteration->signedDocumentFilename ?? $state->documentToSignFilename,
                ];
            }
        }

        throw new NotFoundHttpException('Signed document is not available for this signing iteration.');
    }

    /** @return array{fileId: string, filename: string} */
    private function getAttachment(SigningWorkflowState $state, string $fileId): array
    {
        foreach ($state->attachments as $attachment) {
            if ($attachment['fileId'] === $fileId) {
                return $attachment;
            }
        }

        throw new NotFoundHttpException('Attachment does not belong to the signing workflow.');
    }

    public function ping(WorkflowData $workflow): ?WorkflowActionResult
    {
        $state = SigningWorkflowState::fromArray($workflow->getInternalState());
        if (!$workflow->isActive() || $state->status->isFinal() || $state->expirationDate === null
            || $state->expirationDate > new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) {
            return null;
        }

        return $this->expire($state);
    }

    private function expire(SigningWorkflowState $state): WorkflowActionResult
    {
        $state->status = SignJobState::FINISHED_TIMEOUT;

        return new WorkflowActionResult(internalState: $state->toArray(), close: true);
    }

    public function cleanup(WorkflowData $workflow): CleanupResult
    {
        $this->helper->getFiles($workflow->getId())->deleteAll();

        return new CleanupResult(done: true, internalState: $workflow->getInternalState());
    }
}
