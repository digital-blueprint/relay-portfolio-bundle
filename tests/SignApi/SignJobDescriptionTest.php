<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests\SignApi;

use Dbp\Relay\PortfolioBundle\SignApi\SignCategory;
use Dbp\Relay\PortfolioBundle\SignApi\SignException;
use Dbp\Relay\PortfolioBundle\SignApi\SignJobDescription;
use Dbp\Relay\PortfolioBundle\SignApi\SignUserClass;
use PHPUnit\Framework\TestCase;

class SignJobDescriptionTest extends TestCase
{
    private const USER_CLASS = 'com.example.api.User';
    private const EXTERNAL_USER_CLASS = 'com.example.api.ExternalUser';

    /**
     * @return array<string, mixed>
     */
    private function canonical(): array
    {
        return [
            'constituent' => [
                'classifier' => 'EMAIL',
                'name' => 'owner@example.com',
                '@class' => self::USER_CLASS,
            ],
            'positionType' => 'SIGNATURE_PAGE',
            'metaData' => [
                'expirationDate' => '2026-09-01T22:59:00.000+0100',
                'description' => 'Please sign',
                'referenceId' => 'CASE-123',
            ],
            'iterationData' => [
                [
                    'invitees' => [[
                        '@class' => self::USER_CLASS,
                        'classifier' => 'EMAIL',
                        'name' => 'approver@example.com',
                        'roleName' => 'signer',
                    ]],
                    'category' => 'APPROVAL',
                    'iterationNumber' => 0,
                ],
                [
                    'invitees' => [[
                        '@class' => self::EXTERNAL_USER_CLASS,
                        'classifier' => 'EMAIL',
                        'name' => 'external@example.com',
                        'externalUserName' => 'New Employee',
                        'locale' => 'de',
                        'roleName' => 'Extern',
                    ]],
                    'category' => 'EXTERNAL_APPROVAL',
                    'iterationNumber' => 1,
                ],
            ],
        ];
    }

    public function testParsesCanonicalJob(): void
    {
        $job = SignJobDescription::fromArray($this->canonical());

        $this->assertSame('owner@example.com', $job->getConstituent()->getName());
        $this->assertSame(SignUserClass::USER, $job->getConstituent()->getClass());
        $this->assertSame('SIGNATURE_PAGE', $job->getPositionType());

        $this->assertNotNull($job->getMetaData());
        $this->assertSame('CASE-123', $job->getMetaData()->getReferenceId());
        $this->assertSame('Please sign', $job->getMetaData()->getDescription());
        $this->assertSame('2026-09-01T22:59:00.000+0100', $job->getMetaData()->getExpirationDate());

        $iterations = $job->getIterationData();
        $this->assertCount(2, $iterations);

        $this->assertSame(SignCategory::APPROVAL, $iterations[0]->getCategory());
        $this->assertSame(0, $iterations[0]->getIterationNumber());
        $this->assertSame(SignUserClass::USER, $iterations[0]->getInvitees()[0]->getClass());

        $this->assertSame(SignCategory::EXTERNAL_APPROVAL, $iterations[1]->getCategory());
        $this->assertSame(1, $iterations[1]->getIterationNumber());
        $external = $iterations[1]->getInvitees()[0];
        $this->assertSame(SignUserClass::EXTERNAL_USER, $external->getClass());
        $this->assertSame('New Employee', $external->getExternalUserName());
        $this->assertSame('de', $external->getLocale());
    }

    public function testMetaDataIsOptional(): void
    {
        $data = $this->canonical();
        unset($data['metaData']);

        $job = SignJobDescription::fromArray($data);
        $this->assertNull($job->getMetaData());
    }

    public function testMissingConstituentThrows(): void
    {
        $data = $this->canonical();
        unset($data['constituent']);

        $this->expectException(SignException::class);
        SignJobDescription::fromArray($data);
    }

    public function testMissingPositionTypeThrows(): void
    {
        $data = $this->canonical();
        unset($data['positionType']);

        $this->expectException(SignException::class);
        SignJobDescription::fromArray($data);
    }

    public function testEmptyIterationDataThrows(): void
    {
        $data = $this->canonical();
        $data['iterationData'] = [];

        $this->expectException(SignException::class);
        SignJobDescription::fromArray($data);
    }

    public function testIterationMissingNumberThrows(): void
    {
        $data = $this->canonical();
        unset($data['iterationData'][0]['iterationNumber']);

        $this->expectException(SignException::class);
        SignJobDescription::fromArray($data);
    }

    public function testInviteePropagatesUserValidation(): void
    {
        $data = $this->canonical();
        // Break the invitee's classifier requirement (empty @class).
        $data['iterationData'][0]['invitees'][0]['@class'] = '';

        $this->expectException(SignException::class);
        SignJobDescription::fromArray($data);
    }
}
