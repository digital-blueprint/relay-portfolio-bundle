<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Workflow\Signing;

use Dbp\Relay\BasePersonBundle\API\PersonProviderInterface;
use Dbp\Relay\CoreBundle\Rest\Options;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\FilterTreeBuilder;
use Dbp\Relay\PortfolioBundle\SignApi\SignException;

/**
 * The signing API identifies internal users by email address.
 * Resolve these addresses to internal user IDs before persisting workflow identities.
 */
final class SigningEmailResolver
{
    public function __construct(private readonly PersonProviderInterface $personProvider)
    {
    }

    /** @return array{personId: string, displayName: string} */
    public function resolve(string $email): array
    {
        $email = trim($email);
        $persons = $this->personProvider->getPersons(1, 2, [
            Options::FILTER => FilterTreeBuilder::create()->equals('localData.email', $email)->createFilter(),
        ]);
        if ($persons === []) {
            throw new SignException(sprintf('No internal person found for email "%s".', $email));
        }
        if (count($persons) > 1) {
            throw new SignException(sprintf('Multiple internal persons found for email "%s".', $email));
        }
        $person = reset($persons);
        $personId = $person->getIdentifier();
        if ($personId === null) {
            throw new \RuntimeException('Person lookup returned no identifier.');
        }

        $displayName = trim(trim($person->getGivenName() ?? '').' '.trim($person->getFamilyName() ?? ''));

        return ['personId' => $personId, 'displayName' => $displayName !== '' ? $displayName : $email];
    }
}
