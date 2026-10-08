<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests\SignApi;

use Dbp\Relay\PortfolioBundle\SignApi\SignJobDescription;
use Dbp\Relay\PortfolioBundle\SignApi\SignJobState;
use Dbp\Relay\PortfolioBundle\SignApi\SignJobStateResponse;
use Dbp\Relay\PortfolioBundle\SignApi\SignServiceInterface;
use Dbp\Relay\PortfolioBundle\SignApi\SignUser;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Uid\Uuid;

#[AutoconfigureTag('dbp.relay.portfolio.sign_service', ['process_id' => 'foobar42'])]
#[AutoconfigureTag('dbp.relay.portfolio.sign_service', ['process_id' => 'test_process'])]
class TestSignService implements SignServiceInterface
{
    /** @var array<string, array{processId: string, document: string}> */
    private array $jobs = [];

    public function startProcess(string $processId, SignJobDescription $jobDescription, string $documentToSign, array $attachments): string
    {
        $instanceId = Uuid::v4()->toRfc4122();
        $this->jobs[$instanceId] = ['processId' => $processId, 'document' => $documentToSign];

        return $instanceId;
    }

    public function resolveProcessId(string $processInstanceId): ?string
    {
        return $this->jobs[$processInstanceId]['processId'] ?? null;
    }

    public function getJobState(string $processInstanceId, string $nameClassifier): SignJobStateResponse
    {
        return new SignJobStateResponse(SignJobState::ACTIVE);
    }

    public function cancelJob(string $processInstanceId, SignUser $requestedBy): SignJobState
    {
        return SignJobState::FINISHED_WF_CANCELLED;
    }

    public function getDocument(string $processInstanceId): ?string
    {
        return $this->jobs[$processInstanceId]['document'] ?? null;
    }
}
