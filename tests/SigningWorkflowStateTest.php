<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningExecutionState;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningIdentity;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningIteration;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningIterationKind;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningIterationState;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningJob;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningParticipant;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningWorkflowState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SigningWorkflowStateTest extends TestCase
{
    private function createState(): SigningWorkflowState
    {
        return new SigningWorkflowState(
            job: new SigningJob(
                signingProcessId: 'process49',
                constituent: new SigningIdentity('owner@example.com', 'user-owner', 'Owner'),
                originalDocument: ['fileId' => 'original', 'filename' => 'contract.pdf'],
                iterations: [
                    new SigningIteration(0, SigningIterationKind::INTERNAL_APPROVAL, new SigningParticipant('a@example.com', 'user-a', displayName: 'Approver')),
                    new SigningIteration(1, SigningIterationKind::EXTERNAL_SIGNING, new SigningParticipant('guest@example.com', displayName: 'Guest', locale: 'de')),
                ],
                attachments: [['fileId' => 'attachment', 'filename' => 'supporting.pdf']],
                description: 'Please sign',
                referenceId: 'CASE-123',
                expirationDate: new \DateTimeImmutable('2026-12-01T22:59:00.123+0100'),
            ),
            state: new SigningExecutionState([
                new SigningIterationState(0, 'approver'),
                new SigningIterationState(1, 'guest'),
            ]),
        );
    }

    public function testJsonRoundTrip(): void
    {
        $state = $this->createState();
        $state->state->getIteration(0)->signedDocument = ['fileId' => 'first-result', 'filename' => 'first-signed.pdf'];
        $state->state->getIteration(1)->signedDocument = ['fileId' => 'final-result', 'filename' => 'final-signed.pdf'];
        $state->state->lastRetrievedAt = new \DateTimeImmutable('2026-11-03T12:00:00.123+01:00');

        $data = json_decode(json_encode($state->toArray(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('signingStatus', $data);
        self::assertArrayNotHasKey('status', $data);
        self::assertSame(1, $data['version']);
        self::assertSame(['version', 'job', 'state'], array_keys($data));
        self::assertSame(['email' => 'owner@example.com', 'userId' => 'user-owner', 'displayName' => 'Owner'], $data['job']['constituent']);
        self::assertArrayNotHasKey('constituentEmail', $data);
        self::assertArrayNotHasKey('constituentUserId', $data);
        self::assertArrayNotHasKey('constituentDisplayName', $data);
        self::assertNull($data['state']['cancelledBy']);
        self::assertSame($state->job->originalDocument, $data['job']['originalDocument']);
        self::assertArrayNotHasKey('documentToSignFileId', $data);
        self::assertArrayNotHasKey('retrievedAt', $data);
        self::assertSame($state->state->iterations[0]->signedDocument, $data['state']['iterations'][0]['signedDocument']);
        self::assertSame('internal_approval', $data['job']['iterations'][0]['kind']);
        self::assertSame('approver', $data['state']['iterations'][0]['taskId']);
        self::assertArrayNotHasKey('tasks', $data['state']['iterations'][0]);
        self::assertArrayNotHasKey('participantIndex', $data['state']['iterations'][0]);
        self::assertArrayNotHasKey('signedDocument', $data['job']['iterations'][0]);
        self::assertArrayNotHasKey('tasks', $data['job']['iterations'][0]);
        self::assertArrayNotHasKey('taskId', $data['job']['iterations'][0]['participant']);
        self::assertArrayNotHasKey('participants', $data['job']['iterations'][0]);
        self::assertArrayNotHasKey('kind', $data['state']['iterations'][0]);
        self::assertArrayNotHasKey('participant', $data['state']['iterations'][0]);
        self::assertEquals($state, SigningWorkflowState::fromArray($data));
        self::assertTrue(SigningWorkflowState::fromArray($data)->isCompleted());

        $state = $this->createState();
        $state->state->cancelledBy = new SigningIdentity('owner@example.com', 'user-owner', 'Owner');
        $state->state->cancelledAt = new \DateTimeImmutable('2026-11-03T12:00:00.123+01:00');
        self::assertEquals($state, SigningWorkflowState::fromArray($state->toArray()));
        self::assertSame($state->state->cancelledBy->toArray(), $state->toArray()['state']['cancelledBy']);
        self::assertArrayNotHasKey('cancelledByEmail', $state->toArray());
        self::assertArrayNotHasKey('cancelledByUserId', $state->toArray());

        $state = $this->createState();
        $state->state->expiredAt = new \DateTimeImmutable('2026-11-03T12:00:00.123+01:00');
        self::assertEquals($state, SigningWorkflowState::fromArray($state->toArray()));
    }

    public function testOptionalFields(): void
    {
        $data = $this->createState()->toArray();
        foreach (['attachments', 'description', 'referenceId', 'expirationDate'] as $field) {
            unset($data['job'][$field]);
        }
        foreach (['lastRetrievedAt', 'cancelledAt', 'expiredAt', 'cancelledBy'] as $field) {
            unset($data['state'][$field]);
        }
        $state = SigningWorkflowState::fromArray($data);
        self::assertSame([], $state->job->attachments);
        foreach (['description', 'referenceId', 'expirationDate'] as $field) {
            self::assertNull($state->job->$field);
        }
        foreach (['lastRetrievedAt', 'cancelledAt', 'expiredAt', 'cancelledBy'] as $field) {
            self::assertNull($state->state->$field);
        }
    }

    public function testIterationProgress(): void
    {
        $state = $this->createState();
        $job = $state->job->toArray();
        self::assertSame(['fileId' => 'original', 'filename' => 'contract.pdf'], $state->getCurrentDocument());
        self::assertSame($state->state->iterations[0], $state->getCurrentIteration());
        self::assertFalse($state->job->iterations[0]->participant->isExternal());
        self::assertTrue($state->job->iterations[1]->participant->isExternal());
        self::assertFalse($state->isCompleted());
        self::assertFalse($state->isFinal());

        foreach ($state->state->iterations as $index => $iteration) {
            $iteration->signedDocument = ['fileId' => 'result-'.$index, 'filename' => 'signed-'.$index.'.pdf'];
            self::assertSame(['fileId' => 'result-'.$index, 'filename' => 'signed-'.$index.'.pdf'], $state->getCurrentDocument());
            self::assertSame($state->state->iterations[$index + 1] ?? null, $state->getCurrentIteration());
            self::assertSame($index === 1, $state->isCompleted());
            self::assertSame($index === 1, $state->isFinal());
        }
        self::assertSame('original', $state->job->originalDocument['fileId']);
        self::assertSame($job, $state->job->toArray());
    }

    #[DataProvider('terminalTimestamps')]
    public function testTerminalTimestampOverridesDocuments(string $field): void
    {
        $state = $this->createState();
        $state->state->$field = new \DateTimeImmutable();
        self::assertTrue($state->isFinal());
        self::assertFalse($state->isCompleted());
        self::assertNull($state->getCurrentIteration());
        foreach ($state->state->iterations as $iteration) {
            $iteration->signedDocument = $state->job->originalDocument;
        }
        self::assertFalse($state->isCompleted());
        self::assertNull($state->getCurrentIteration());
    }

    public static function terminalTimestamps(): iterable
    {
        yield ['cancelledAt'];
        yield ['expiredAt'];
    }

    public function testPastDeadlineDoesNotDeriveRecordedExpiration(): void
    {
        $state = $this->createState();
        $state = SigningTestUtils::withJob($state, ['expirationDate' => (new \DateTimeImmutable('-1 day'))->format('Y-m-d\TH:i:s.vP')]);
        self::assertFalse($state->isFinal());
        self::assertSame($state->state->iterations[0], $state->getCurrentIteration());
        self::assertNull($state->state->expiredAt);
    }

    #[DataProvider('unsupportedStepCounts')]
    public function testUnsupportedStepCountsAreRejected(int $count): void
    {
        $state = $this->createState();
        $data = $state->toArray();
        if ($count > 2) {
            $data['job']['iterations'][] = $data['job']['iterations'][1];
            $data['state']['iterations'][] = $data['state']['iterations'][1];
        } else {
            $data['job']['iterations'] = array_slice($data['job']['iterations'], 0, $count);
            $data['state']['iterations'] = array_slice($data['state']['iterations'], 0, $count);
        }
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Signing requires exactly two job steps and their execution records.');
        SigningWorkflowState::fromArray($data);
    }

    public static function unsupportedStepCounts(): iterable
    {
        yield 'no steps' => [0];
        yield 'one step' => [1];
        yield 'three steps' => [3];
    }

    public function testSparseStepDefinitionsAreRejected(): void
    {
        $data = $this->createState()->toArray();
        $data['job']['iterations'][2] = $data['job']['iterations'][1];
        unset($data['job']['iterations'][1]);
        $this->expectException(\InvalidArgumentException::class);
        SigningWorkflowState::fromArray($data);
    }

    public function testLastDocumentAloneDoesNotMeanCompleted(): void
    {
        $state = $this->createState();
        $state->state->getIteration(1)->signedDocument = $state->job->originalDocument;
        self::assertFalse($state->isCompleted());
        self::assertSame($state->state->iterations[0], $state->getCurrentIteration());
    }

    public function testExecutionOrderDoesNotDetermineSigningOrder(): void
    {
        $data = $this->createState()->toArray();
        $data['state']['iterations'] = array_reverse($data['state']['iterations']);
        $workflow = SigningWorkflowState::fromArray($data);

        self::assertSame(0, $workflow->getCurrentIteration()->number);
        $workflow->state->getIteration(0)->signedDocument = ['fileId' => 'first-result', 'filename' => 'first.pdf'];
        self::assertSame(1, $workflow->getCurrentIteration()->number);
        self::assertSame('first-result', $workflow->getCurrentDocument()['fileId']);
        self::assertFalse($workflow->isCompleted());
        $workflow->state->getIteration(1)->signedDocument = ['fileId' => 'final-result', 'filename' => 'final.pdf'];
        self::assertTrue($workflow->isCompleted());
        self::assertSame('final-result', $workflow->getCurrentDocument()['fileId']);
    }

    #[DataProvider('invalidReferences')]
    public function testInvalidReferencesAreRejected(array $path, mixed $value): void
    {
        $data = $this->createState()->toArray();
        $target = &$data;
        foreach ($path as $segment) {
            $target = &$target[$segment];
        }
        $target = $value;
        unset($target);

        $this->expectException(\InvalidArgumentException::class);
        SigningWorkflowState::fromArray($data);
    }

    public static function invalidReferences(): iterable
    {
        yield 'missing execution iterations' => [['state', 'iterations'], []];
        yield 'duplicate execution number' => [['state', 'iterations', 1, 'number'], 0];
        yield 'unknown execution number' => [['state', 'iterations', 1, 'number'], 2];
        yield 'duplicate definition number' => [['job', 'iterations', 1, 'number'], 0];
        yield 'wrong first step kind' => [['job', 'iterations', 0, 'kind'], 'external_signing'];
        yield 'wrong second step kind' => [['job', 'iterations', 1, 'kind'], 'internal_approval'];
        yield 'external first participant' => [['job', 'iterations', 0, 'participant', 'userId'], null];
        yield 'internal second participant' => [['job', 'iterations', 1, 'participant', 'userId'], 'user-guest'];
        yield 'duplicate task ID' => [['state', 'iterations', 1, 'taskId'], 'approver'];
        yield 'empty task ID' => [['state', 'iterations', 0, 'taskId'], ' '];
    }

    public function testJobDefinitionIsImmutable(): void
    {
        $workflow = $this->createState();
        $this->expectException(\Error::class);
        $workflow->job->description = 'Changed';
    }

    #[DataProvider('unsupportedVersions')]
    public function testUnsupportedVersion(mixed $version): void
    {
        $data = $this->createState()->toArray();
        $data['version'] = $version;
        $this->expectException(\InvalidArgumentException::class);
        SigningWorkflowState::fromArray($data);
    }

    public static function unsupportedVersions(): iterable
    {
        yield [2];
        yield [null];
        yield ['1'];
    }
}
