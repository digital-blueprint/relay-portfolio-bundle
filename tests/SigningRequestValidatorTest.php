<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\PortfolioBundle\SignApi\SignException;
use Dbp\Relay\PortfolioBundle\SignApi\SignJobDescription;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\SigningRequestValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SigningRequestValidatorTest extends TestCase
{
    private function request(): array
    {
        return SigningTestUtils::jobDescription();
    }

    public function testSupportedRequestAndOptionalMetadata(): void
    {
        $validator = new SigningRequestValidator();
        $data = $this->request();
        self::assertNull($validator->validate(SignJobDescription::fromArray($data))->getMetaData());

        $data['metaData'] = [
            'description' => 'Please sign', 'referenceId' => 'CASE-123',
            'expirationDate' => (new \DateTimeImmutable('+1 day'))->format('Y-m-d\TH:i:s.vO'),
        ];
        $job = $validator->validate(SignJobDescription::fromArray($data));
        self::assertSame('CASE-123', $job->getMetaData()->getReferenceId());
        self::assertSame('Guest', $job->getIterationData()[1]->getInvitees()[0]->getExternalUserName());
        $data['metaData'] = [];
        self::assertNull($validator->validate(SignJobDescription::fromArray($data))->getMetaData()->getExpirationDate());
        $data['metaData'] = ['expirationDate' => '2000-01-01T12:00:00.000+0100'];
        self::assertSame('2000-01-01T12:00:00.000+0100', $validator->validate(SignJobDescription::fromArray($data))->getMetaData()->getExpirationDate());
    }

    public function testIterationNumbersDetermineOrder(): void
    {
        $data = $this->request();
        $data['iterationData'] = array_reverse($data['iterationData']);
        $job = (new SigningRequestValidator())->validate(SignJobDescription::fromArray($data));
        self::assertSame([0, 1], array_map(static fn ($iteration): int => $iteration->getIterationNumber(), $job->getIterationData()));
    }

    #[DataProvider('unsupportedInputs')]
    public function testUnsupportedInput(array $path, mixed $value): void
    {
        $data = $this->request();
        $field = &$data;
        foreach ($path as $key) {
            $field = &$field[$key];
        }
        $field = $value;
        unset($field);
        try {
            (new SigningRequestValidator())->validate(SignJobDescription::fromArray($data));
            self::fail('Unsupported input should be rejected.');
        } catch (SignException $error) {
            self::assertSame(400, $error->getStatusCode());
        }
    }

    public static function unsupportedInputs(): iterable
    {
        yield 'position' => [['positionType'], 'SIGNATURE_FIELD'];
        yield 'invisible position' => [['positionType'], 'INVISIBLE'];
        yield 'placeholder position' => [['positionType'], 'ON_PLACEHOLDER'];
        yield 'direct position' => [['positionType'], 'DIRECT_POSITION'];
        yield 'owner classifier' => [['constituent', 'classifier'], 'USERNAME'];
        yield 'external owner' => [['constituent', '@class'], 'com.example.api.ExternalUser'];
        yield 'empty email' => [['constituent', 'name'], ''];
        yield 'invalid email' => [['iterationData', 0, 'invitees', 0, 'name'], 'not-an-email'];
        yield 'unsupported user class' => [['iterationData', 0, 'invitees', 0, '@class'], 'com.example.api.UserForRole'];
        yield 'internal employee' => [['iterationData', 1, 'invitees', 0, '@class'], 'com.example.api.User'];
        yield 'external field on internal signer' => [['iterationData', 0, 'invitees', 0, 'externalUserName'], 'Guest'];
        yield 'missing iterations' => [['iterationData'], []];
        yield 'object instead of list' => [['iterationData'], ['first' => []]];
        yield 'no invitee' => [['iterationData', 0, 'invitees'], []];
        yield 'two invitees' => [['iterationData', 0, 'invitees'], [[], []]];
        yield 'invalid invitee' => [['iterationData', 0, 'invitees'], ['person']];
        yield 'invalid iteration' => [['iterationData', 0], 'iteration'];
        yield 'duplicate iteration number' => [['iterationData', 1, 'iterationNumber'], 0];
        yield 'negative iteration number' => [['iterationData', 0, 'iterationNumber'], -1];
        yield 'unknown category' => [['iterationData', 0, 'category'], 'UNKNOWN'];
        yield 'qualified signature' => [['iterationData', 0, 'category'], 'QSIG'];
        yield 'wrong employee category' => [['iterationData', 1, 'category'], 'APPROVAL'];
        yield 'null metadata' => [['metaData'], null];
        yield 'null deadline' => [['metaData'], ['expirationDate' => null]];
        yield 'empty deadline' => [['metaData'], ['expirationDate' => '']];
        yield 'relative deadline' => [['metaData'], ['expirationDate' => 'tomorrow']];
        yield 'null byte in deadline' => [['metaData'], ['expirationDate' => "2099-01-01T12:00:00.000+0100\0"]];
        yield 'invalid calendar date' => [['metaData'], ['expirationDate' => '2099-02-30T12:00:00.000+0100']];
    }
}
