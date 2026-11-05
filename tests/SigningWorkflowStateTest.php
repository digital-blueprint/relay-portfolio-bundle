<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\PortfolioBundle\SignApi\SignCategory;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningInvitee;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningIteration;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningWorkflowState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SigningWorkflowStateTest extends TestCase
{
    private function createState(): SigningWorkflowState
    {
        return new SigningWorkflowState(
            signingProcessId: 'process49',
            constituentEmail: 'owner@example.com',
            constituentUserId: 'user-owner',
            documentToSign: ['fileId' => 'original', 'filename' => 'contract.pdf'],
            iterations: [
                new SigningIteration(0, SignCategory::APPROVAL, [new SigningInvitee('approver', 'a@example.com', 'user-a', displayName: 'Approver')]),
                new SigningIteration(1, SignCategory::EXTERNAL_APPROVAL, [new SigningInvitee('guest', 'guest@example.com', displayName: 'Guest', locale: 'de')]),
            ],
            attachments: [['fileId' => 'attachment', 'filename' => 'supporting.pdf']],
            description: 'Please sign',
            referenceId: 'CASE-123',
            expirationDate: new \DateTimeImmutable('2026-12-01T22:59:00.123+0100'),
        );
    }

    public function testJsonRoundTrip(): void
    {
        $state = $this->createState();
        $state->iterations[0]->signedDocument = ['fileId' => 'first-result', 'filename' => 'first-signed.pdf'];
        $state->iterations[1]->signedDocument = ['fileId' => 'final-result', 'filename' => 'final-signed.pdf'];
        $state->lastRetrievedAt = new \DateTimeImmutable('2026-11-03T12:00:00.123+01:00');

        $data = json_decode(json_encode($state->toArray(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('signingStatus', $data);
        self::assertArrayNotHasKey('status', $data);
        self::assertSame(1, $data['version']);
        self::assertSame($state->documentToSign, $data['documentToSign']);
        self::assertArrayNotHasKey('documentToSignFileId', $data);
        self::assertArrayNotHasKey('retrievedAt', $data);
        self::assertSame($state->iterations[0]->signedDocument, $data['iterations'][0]['signedDocument']);
        self::assertArrayNotHasKey('signedDocumentFileId', $data['iterations'][0]);
        self::assertEquals($state, SigningWorkflowState::fromArray($data));
        self::assertTrue(SigningWorkflowState::fromArray($data)->isCompleted());

        $state = $this->createState();
        $state->cancelledByEmail = 'owner@example.com';
        $state->cancelledByUserId = 'user-owner';
        $state->cancelledAt = new \DateTimeImmutable('2026-11-03T12:00:00.123+01:00');
        self::assertEquals($state, SigningWorkflowState::fromArray($state->toArray()));

        $state = $this->createState();
        $state->expiredAt = new \DateTimeImmutable('2026-11-03T12:00:00.123+01:00');
        self::assertEquals($state, SigningWorkflowState::fromArray($state->toArray()));
    }

    public function testOptionalFields(): void
    {
        $data = $this->createState()->toArray();
        foreach (['attachments', 'description', 'referenceId', 'expirationDate', 'lastRetrievedAt', 'cancelledAt', 'expiredAt', 'cancelledByEmail', 'cancelledByUserId'] as $field) {
            unset($data[$field]);
        }
        $state = SigningWorkflowState::fromArray($data);
        self::assertSame([], $state->attachments);
        foreach (['description', 'referenceId', 'expirationDate', 'lastRetrievedAt', 'cancelledAt', 'expiredAt', 'cancelledByEmail', 'cancelledByUserId'] as $field) {
            self::assertNull($state->$field);
        }
    }

    public function testIterationProgress(): void
    {
        $state = $this->createState();
        self::assertSame(['fileId' => 'original', 'filename' => 'contract.pdf'], $state->getCurrentDocument());
        self::assertSame($state->iterations[0], $state->getCurrentIteration());
        self::assertFalse($state->iterations[0]->invitees[0]->isExternal());
        self::assertTrue($state->iterations[1]->invitees[0]->isExternal());
        self::assertFalse($state->isCompleted());
        self::assertFalse($state->isFinal());

        foreach ($state->iterations as $index => $iteration) {
            $iteration->signedDocument = ['fileId' => 'result-'.$index, 'filename' => 'signed-'.$index.'.pdf'];
            self::assertSame(['fileId' => 'result-'.$index, 'filename' => 'signed-'.$index.'.pdf'], $state->getCurrentDocument());
            self::assertSame($state->iterations[$index + 1] ?? null, $state->getCurrentIteration());
            self::assertSame($index === 1, $state->isCompleted());
            self::assertSame($index === 1, $state->isFinal());
        }
        self::assertSame('original', $state->documentToSign['fileId']);
    }

    #[DataProvider('terminalTimestamps')]
    public function testTerminalTimestampOverridesDocuments(string $field): void
    {
        $state = $this->createState();
        $state->$field = new \DateTimeImmutable();
        self::assertTrue($state->isFinal());
        self::assertFalse($state->isCompleted());
        self::assertNull($state->getCurrentIteration());
        foreach ($state->iterations as $iteration) {
            $iteration->signedDocument = $state->documentToSign;
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
        $state->expirationDate = new \DateTimeImmutable('-1 day');
        self::assertFalse($state->isFinal());
        self::assertSame($state->iterations[0], $state->getCurrentIteration());
        self::assertNull($state->expiredAt);
    }

    public function testEmptyIterationsAreNotCompleted(): void
    {
        $state = $this->createState();
        $state->iterations = [];
        self::assertFalse($state->isCompleted());
        self::assertFalse($state->isFinal());
    }

    public function testLastDocumentAloneDoesNotMeanCompleted(): void
    {
        $state = $this->createState();
        $state->iterations[1]->signedDocument = $state->documentToSign;
        self::assertFalse($state->isCompleted());
        self::assertSame($state->iterations[0], $state->getCurrentIteration());
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
