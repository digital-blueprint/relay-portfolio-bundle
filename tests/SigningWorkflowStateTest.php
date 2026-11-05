<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\PortfolioBundle\SignApi\SignCategory;
use Dbp\Relay\PortfolioBundle\SignApi\SignJobState;
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
        $state->signingStatus = SignJobState::FINISHED_SUCCESS;
        $state->lastRetrievedAt = new \DateTimeImmutable('2026-11-03T12:00:00.123+01:00');

        $data = json_decode(json_encode($state->toArray(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(SignJobState::FINISHED_SUCCESS->value, $data['signingStatus']);
        self::assertArrayNotHasKey('status', $data);
        self::assertSame(1, $data['version']);
        self::assertSame($state->documentToSign, $data['documentToSign']);
        self::assertArrayNotHasKey('documentToSignFileId', $data);
        self::assertArrayNotHasKey('retrievedAt', $data);
        self::assertSame($state->iterations[0]->signedDocument, $data['iterations'][0]['signedDocument']);
        self::assertArrayNotHasKey('signedDocumentFileId', $data['iterations'][0]);
        self::assertEquals($state, SigningWorkflowState::fromArray($data));

        $state = $this->createState();
        $state->signingStatus = SignJobState::FINISHED_WF_CANCELLED;
        $state->cancelledByEmail = 'owner@example.com';
        $state->cancelledByUserId = 'user-owner';
        $state->cancelledAt = new \DateTimeImmutable('2026-11-03T12:00:00.123+01:00');
        self::assertEquals($state, SigningWorkflowState::fromArray($state->toArray()));
    }

    public function testOptionalFields(): void
    {
        $data = $this->createState()->toArray();
        foreach (['attachments', 'description', 'referenceId', 'expirationDate', 'lastRetrievedAt', 'cancelledAt', 'cancelledByEmail', 'cancelledByUserId'] as $field) {
            unset($data[$field]);
        }
        $state = SigningWorkflowState::fromArray($data);
        self::assertSame([], $state->attachments);
        foreach (['description', 'referenceId', 'expirationDate', 'lastRetrievedAt', 'cancelledAt', 'cancelledByEmail', 'cancelledByUserId'] as $field) {
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

        foreach ($state->iterations as $index => $iteration) {
            $iteration->signedDocument = ['fileId' => 'result-'.$index, 'filename' => 'signed-'.$index.'.pdf'];
            self::assertSame(['fileId' => 'result-'.$index, 'filename' => 'signed-'.$index.'.pdf'], $state->getCurrentDocument());
            self::assertSame($state->iterations[$index + 1] ?? null, $state->getCurrentIteration());
        }
        self::assertSame('original', $state->documentToSign['fileId']);
    }

    public function testFinishedJobsHaveNoCurrentIteration(): void
    {
        $state = $this->createState();
        foreach (SignJobState::cases() as $signingStatus) {
            if ($signingStatus->isFinal()) {
                $state->signingStatus = $signingStatus;
                self::assertNull($state->getCurrentIteration());
            }
        }
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
