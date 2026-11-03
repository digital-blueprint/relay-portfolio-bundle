<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Workflow\Signing;

use Dbp\Relay\PortfolioBundle\SignApi\SignCategory;
use Dbp\Relay\PortfolioBundle\SignApi\SignClassifier;
use Dbp\Relay\PortfolioBundle\SignApi\SignException;
use Dbp\Relay\PortfolioBundle\SignApi\SignJobDescription;
use Dbp\Relay\PortfolioBundle\SignApi\SignUser;
use Dbp\Relay\PortfolioBundle\SignApi\SignUserClass;
use Dbp\Relay\PortfolioBundle\SignApi\SignUtils;

final class SigningRequestValidator
{
    public function validate(array $data): SignJobDescription
    {
        $this->checkFields($data, ['constituent', 'positionType', 'iterationData', 'metaData'], 'jobDescription');
        $this->checkUser(SignUtils::requireArray($data, 'constituent'), false, 'constituent');

        $iterations = SignUtils::requireArray($data, 'iterationData');
        if (!array_is_list($iterations) || count($iterations) !== 2) {
            throw new SignException('The "iterationData" field must contain exactly two iterations.');
        }
        foreach ($iterations as $index => $iteration) {
            $path = 'iterationData['.$index.']';
            if (!is_array($iteration)) {
                throw new SignException(sprintf('The "%s" field must be an object.', $path));
            }
            $this->checkFields($iteration, ['invitees', 'category', 'iterationNumber'], $path);
            $number = SignUtils::requireInt($iteration, 'iterationNumber');
            if ($number !== 0 && $number !== 1) {
                throw new SignException(sprintf('The "%s.iterationNumber" field must be 0 or 1.', $path));
            }
            $invitees = SignUtils::requireArray($iteration, 'invitees');
            if (!array_is_list($invitees) || count($invitees) !== 1 || !is_array($invitees[0])) {
                throw new SignException(sprintf('The "%s.invitees" field must contain exactly one signer.', $path));
            }
            $this->checkUser($invitees[0], $number === 1, $path.'.invitees[0]');
        }

        // Iteration numbers determine signing order, not the input array order.
        usort($iterations, static fn (array $a, array $b): int => $a['iterationNumber'] <=> $b['iterationNumber']);
        $data['iterationData'] = $iterations;
        if (array_key_exists('metaData', $data)) {
            $this->checkFields(SignUtils::requireArray($data, 'metaData'), ['description', 'referenceId', 'expirationDate'], 'metaData');
        }
        $job = SignJobDescription::fromArray($data);
        if ($job->getPositionType() !== 'SIGNATURE_PAGE') {
            throw new SignException('Only positionType "SIGNATURE_PAGE" is supported.');
        }
        foreach ($job->getIterationData() as $index => $iteration) {
            $category = $index === 0 ? SignCategory::APPROVAL : SignCategory::EXTERNAL_APPROVAL;
            if ($iteration->getIterationNumber() !== $index || $iteration->getCategory() !== $category) {
                throw new SignException('Signing requires iteration 0 with APPROVAL and iteration 1 with EXTERNAL_APPROVAL.');
            }
        }
        if (($expiration = $job->getMetaData()?->getExpirationDate()) !== null) {
            try {
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.vO', $expiration);
            } catch (\ValueError) {
                $date = false;
            }
            if ($date === false || $date->format('Y-m-d\TH:i:s.vO') !== $expiration) {
                throw new SignException('The "metaData.expirationDate" field must use yyyy-MM-ddTHH:mm:ss.SSS±ZZZZ.');
            }
        }

        return $job;
    }

    private function checkUser(array $data, bool $external, string $path): void
    {
        $fields = ['@class', 'classifier', 'name'];
        if ($external) {
            $fields = [...$fields, 'externalUserName', 'locale'];
        }
        $this->checkFields($data, $fields, $path);
        $user = SignUser::fromArray($data);
        $class = $external ? SignUserClass::EXTERNAL_USER : SignUserClass::USER;
        if ($user->getClass() !== $class || $user->getClassifier() !== SignClassifier::EMAIL) {
            throw new SignException(sprintf('The "%s" field requires an EMAIL identity of class %s.', $path, $class->value));
        }
        if (filter_var($user->getName(), FILTER_VALIDATE_EMAIL) === false) {
            throw new SignException(sprintf('The "%s.name" field must be a valid email address.', $path));
        }
    }

    /** @param list<string> $allowed */
    private function checkFields(array $data, array $allowed, string $path): void
    {
        foreach (array_keys($data) as $field) {
            if (!in_array($field, $allowed, true)) {
                throw new SignException(sprintf('Unsupported field "%s.%s".', $path, $field));
            }
        }
    }
}
