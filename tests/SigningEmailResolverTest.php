<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\BasePersonBundle\API\PersonProviderInterface;
use Dbp\Relay\BasePersonBundle\Entity\Person;
use Dbp\Relay\CoreBundle\Rest\Options;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\FilterTreeBuilder;
use Dbp\Relay\PortfolioBundle\SignApi\SignException;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\SigningEmailResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SigningEmailResolverTest extends TestCase
{
    #[DataProvider('names')]
    public function testResolve(?string $givenName, ?string $familyName, string $displayName): void
    {
        $person = new Person();
        $person->setIdentifier('person-id');
        $person->setGivenName($givenName);
        $person->setFamilyName($familyName);
        $provider = $this->createMock(PersonProviderInterface::class);
        $provider->expects(self::once())->method('getPersons')->with(1, 2, [
            Options::FILTER => FilterTreeBuilder::create()->equals('localData.email', 'a@example.com')->createFilter(),
        ])->willReturn([$person]);

        self::assertSame(
            ['personId' => 'person-id', 'displayName' => $displayName],
            (new SigningEmailResolver($provider))->resolve(' a@example.com '),
        );
    }

    public static function names(): iterable
    {
        yield [' Anna ', ' Example ', 'Anna Example'];
        yield [null, 'Example', 'Example'];
        yield [null, null, 'a@example.com'];
    }

    #[DataProvider('invalidResults')]
    public function testInvalidResults(array $persons, \Exception $expected): void
    {
        $provider = $this->createMock(PersonProviderInterface::class);
        $provider->method('getPersons')->willReturn($persons);
        $this->expectExceptionObject($expected);
        (new SigningEmailResolver($provider))->resolve('a@example.com');
    }

    public static function invalidResults(): iterable
    {
        yield [[], new SignException('No internal person found for email "a@example.com".')];
        yield [[new Person(), new Person()], new SignException('Multiple internal persons found for email "a@example.com".')];
        yield [[new Person()], new \RuntimeException('Person lookup returned no identifier.')];
    }

    public function testProviderErrorPropagates(): void
    {
        $error = new \RuntimeException('Lookup failed');
        $provider = $this->createMock(PersonProviderInterface::class);
        $provider->method('getPersons')->willThrowException($error);
        $this->expectExceptionObject($error);
        (new SigningEmailResolver($provider))->resolve('a@example.com');
    }
}
