<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Workflow\Signing;

use Dbp\Relay\PortfolioBundle\Handler\WorkflowData;
use Dbp\Relay\PortfolioBundle\Persistence\WorkflowPersistence;
use Dbp\Relay\PortfolioBundle\Service\WorkflowFileService;
use Dbp\Relay\PortfolioBundle\Service\WorkflowService;
use Dbp\Relay\PortfolioBundle\SignApi\SignCategory;
use Dbp\Relay\PortfolioBundle\SignApi\SignClassifier;
use Dbp\Relay\PortfolioBundle\SignApi\SignException;
use Dbp\Relay\PortfolioBundle\SignApi\SignJobDescription;
use Dbp\Relay\PortfolioBundle\SignApi\SignJobState;
use Dbp\Relay\PortfolioBundle\SignApi\SignJobStateResponse;
use Dbp\Relay\PortfolioBundle\SignApi\SignServiceInterface;
use Dbp\Relay\PortfolioBundle\SignApi\SignUser;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningIteration;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningIterationKind;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningWorkflowState;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

#[AutoconfigureTag('dbp.relay.portfolio.sign_service', ['process_id' => self::PROCESS_ID])]
class SignService implements SignServiceInterface
{
    public const PROCESS_ID = 'process49';

    public function __construct(
        private readonly WorkflowService $workflows,
        private readonly SigningWorkflowTypeHandler $handler,
        private readonly WorkflowFileService $files,
    ) {
    }

    public function startProcess(string $processId, SignJobDescription $jobDescription, UploadedFile $documentToSign, array $attachments): string
    {
        if ($processId !== self::PROCESS_ID) {
            throw new SignException('Unsupported signing process.', Response::HTTP_NOT_FOUND);
        }

        return $this->workflows->createWorkflow(SigningWorkflowTypeHandler::TYPE, [
            'signingProcessId' => $processId,
            'jobDescription' => $jobDescription,
            'documentToSign' => $documentToSign,
            'attachments' => $attachments,
        ])->getId();
    }

    public function resolveProcessId(string $processInstanceId): ?string
    {
        return $this->findWorkflow($processInstanceId) !== null ? self::PROCESS_ID : null;
    }

    public function getJobState(string $processInstanceId, string $nameClassifier): SignJobStateResponse
    {
        if ($nameClassifier !== 'EMAIL') {
            throw new SignException('Only nameClassifier "EMAIL" is supported.');
        }
        $workflow = $this->findWorkflow($processInstanceId);
        if ($workflow === null) {
            throw SignException::jobNotFound($processInstanceId);
        }

        $state = SigningWorkflowState::fromArray($workflow->getInternalState());

        return new SignJobStateResponse(
            state: $this->getSigningStatus($state),
            processId: $state->job->signingProcessId,
            processInstanceId: $workflow->getId(),
            jobMetaData: array_filter([
                'description' => $state->job->description,
                'referenceId' => $state->job->referenceId,
                'expirationDate' => $state->job->expirationDate?->format('Y-m-d\TH:i:s.vP'),
            ], static fn (?string $value): bool => $value !== null),
            creationDate: $workflow->getCreatedAt(),
            currentIteration: $state->getCurrentIteration()?->number,
            iterations: array_map(static fn (SigningIteration $iteration): array => [
                'numberOfSignatures' => 1,
                'invitees' => [[
                    'classifier' => SignClassifier::EMAIL->value,
                    'name' => $iteration->participant->email,
                ]],
                'category' => match ($iteration->kind) {
                    SigningIterationKind::INTERNAL_APPROVAL => SignCategory::APPROVAL->value,
                    SigningIterationKind::EXTERNAL_SIGNING => SignCategory::EXTERNAL_APPROVAL->value,
                },
                'iterationNumber' => $iteration->number,
            ], $state->job->iterations),
        );
    }

    public function cancelJob(string $processInstanceId, SignUser $requestedBy): SignJobState
    {
        if ($this->findWorkflow($processInstanceId) === null) {
            throw SignException::jobNotFound($processInstanceId);
        }

        if ($requestedBy->getClassifier() !== SignClassifier::EMAIL) {
            throw new SignException('Only classifier "EMAIL" is supported.');
        }

        $result = $this->workflows->updateWorkflow($processInstanceId, fn (WorkflowData $workflow) => $this->handler->cancel($workflow, $requestedBy->getName()));

        return $this->getSigningStatus(SigningWorkflowState::fromArray($result->getInternalState()));
    }

    public function getDocument(string $processInstanceId): ?string
    {
        $workflow = $this->findWorkflow($processInstanceId);
        if ($workflow === null) {
            throw SignException::jobNotFound($processInstanceId);
        }
        $state = SigningWorkflowState::fromArray($workflow->getInternalState());
        if (!$state->isCompleted()) {
            return null;
        }
        $document = $state->getCurrentDocument();
        $contents = $this->files->download($processInstanceId, $document['fileId'])->getFileStream()->getContents();
        $this->workflows->updateWorkflow($processInstanceId, fn (WorkflowData $workflow) => $this->handler->recordRetrieval($workflow));

        return $contents;
    }

    private function getSigningStatus(SigningWorkflowState $state): SignJobState
    {
        return match (true) {
            $state->state->cancelledAt !== null => SignJobState::FINISHED_WF_CANCELLED,
            $state->state->expiredAt !== null => SignJobState::FINISHED_TIMEOUT,
            $state->isCompleted() => SignJobState::FINISHED_SUCCESS,
            default => SignJobState::ACTIVE,
        };
    }

    private function findWorkflow(string $id): ?WorkflowPersistence
    {
        if (!Uuid::isValid($id)) {
            return null;
        }
        $workflow = $this->workflows->getWorkflow($id);
        if ($workflow === null || $workflow->getType() !== SigningWorkflowTypeHandler::TYPE) {
            return null;
        }
        $state = SigningWorkflowState::fromArray($workflow->getInternalState());

        return $state->job->signingProcessId === self::PROCESS_ID ? $workflow : null;
    }
}
