<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Workflow\Signing;

use Dbp\Relay\PortfolioBundle\SignApi\SignCategory;
use Dbp\Relay\PortfolioBundle\SignApi\SignClassifier;
use Dbp\Relay\PortfolioBundle\SignApi\SignException;
use Dbp\Relay\PortfolioBundle\SignApi\SignIteration;
use Dbp\Relay\PortfolioBundle\SignApi\SignJobDescription;
use Dbp\Relay\PortfolioBundle\SignApi\SignPositionType;
use Dbp\Relay\PortfolioBundle\SignApi\SignUser;
use Dbp\Relay\PortfolioBundle\SignApi\SignUserClass;

final class SigningRequestValidator
{
    public function validate(SignJobDescription $job): SignJobDescription
    {
        $this->checkUser($job->getConstituent(), false, 'constituent');

        $iterations = $job->getIterationData();
        if (count($iterations) !== 2) {
            throw new SignException('The "iterationData" field must contain exactly two iterations.');
        }
        foreach ($iterations as $index => $iteration) {
            $path = 'iterationData['.$index.']';
            $number = $iteration->getIterationNumber();
            if ($number !== 0 && $number !== 1) {
                throw new SignException(sprintf('The "%s.iterationNumber" field must be 0 or 1.', $path));
            }
            $invitees = $iteration->getInvitees();
            if (count($invitees) !== 1) {
                throw new SignException(sprintf('The "%s.invitees" field must contain exactly one signer.', $path));
            }
            $this->checkUser($invitees[0], $number === 1, $path.'.invitees[0]');
        }

        // Iteration numbers determine signing order, not the input array order.
        usort($iterations, static fn (SignIteration $a, SignIteration $b): int => $a->getIterationNumber() <=> $b->getIterationNumber());
        if ($job->getPositionType() !== SignPositionType::SIGNATURE_PAGE) {
            throw new SignException('Only positionType "SIGNATURE_PAGE" is supported.');
        }
        foreach ($iterations as $index => $iteration) {
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

        return new SignJobDescription($job->getConstituent(), $job->getPositionType(), $iterations, $job->getMetaData());
    }

    private function checkUser(SignUser $user, bool $external, string $path): void
    {
        if (!$external && ($user->getExternalUserName() !== null || $user->getLocale() !== null)) {
            throw new SignException(sprintf('The "%s" field must not contain external user settings.', $path));
        }
        $class = $external ? SignUserClass::EXTERNAL_USER : SignUserClass::USER;
        if ($user->getClass() !== $class || $user->getClassifier() !== SignClassifier::EMAIL) {
            throw new SignException(sprintf('The "%s" field requires an EMAIL identity of class %s.', $path, $class->value));
        }
        if (filter_var($user->getName(), FILTER_VALIDATE_EMAIL) === false) {
            throw new SignException(sprintf('The "%s.name" field must be a valid email address.', $path));
        }
    }
}
