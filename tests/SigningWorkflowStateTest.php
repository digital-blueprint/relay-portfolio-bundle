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
            documentToSignFileId: 'original',
            documentToSignFilename: 'contract.pdf',
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
        $state->iterations[0]->signedDocumentFileId = 'first-result';
        $state->iterations[0]->signedDocumentFilename = 'first-signed.pdf';
        $state->iterations[1]->signedDocumentFileId = 'final-result';
        $state->iterations[1]->signedDocumentFilename = 'final-signed.pdf';
        $state->status = SignJobState::FINISHED_SUCCESS;
        $state->retrievedAt = new \DateTimeImmutable('2026-11-03T12:00:00.123456+01:00');

        $data = json_decode(json_encode($state->toArray(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        self::assertEquals($state, SigningWorkflowState::fromArray($data));

        $state = $this->createState();
        $state->status = SignJobState::FINISHED_WF_CANCELLED;
        $state->cancelledBy = 'owner@example.com';
        self::assertEquals($state, SigningWorkflowState::fromArray($state->toArray()));
    }

    public function testOptionalFields(): void
    {
        $data = $this->createState()->toArray();
        foreach (['attachments', 'description', 'referenceId', 'expirationDate', 'retrievedAt', 'cancelledBy'] as $field) {
            unset($data[$field]);
        }
        $state = SigningWorkflowState::fromArray($data);
        self::assertSame([], $state->attachments);
        foreach (['description', 'referenceId', 'expirationDate', 'retrievedAt', 'cancelledBy'] as $field) {
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
            $iteration->signedDocumentFileId = 'result-'.$index;
            $iteration->signedDocumentFilename = 'signed-'.$index.'.pdf';
            self::assertSame(['fileId' => 'result-'.$index, 'filename' => 'signed-'.$index.'.pdf'], $state->getCurrentDocument());
            self::assertSame($state->iterations[$index + 1] ?? null, $state->getCurrentIteration());
        }
        self::assertSame('original', $state->documentToSignFileId);
        $state->iterations[1]->signedDocumentFilename = null;
        self::assertSame('contract.pdf', $state->getCurrentDocument()['filename']);
    }

    public function testFinishedJobsHaveNoCurrentIteration(): void
    {
        $state = $this->createState();
        foreach (SignJobState::cases() as $status) {
            if ($status->isFinal()) {
                $state->status = $status;
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
