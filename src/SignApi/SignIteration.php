<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\SignApi;

/**
 * One signing iteration inside jobDescription.iterationData:
 *
 *   {
 *     "invitees": [ { ...SignUser... } ],
 *     "category": "APPROVAL",
 *     "iterationNumber": 0
 *   }
 *
 * Iterations are executed in order of iterationNumber: iteration N must complete
 * before iteration N+1 is invited.
 */
class SignIteration
{
    /**
     * @param SignUser[] $invitees
     */
    public function __construct(
        private readonly array $invitees,
        private readonly SignCategory $category,
        private readonly int $iterationNumber,
    ) {
    }

    /**
     * @return SignUser[]
     */
    public function getInvitees(): array
    {
        return $this->invitees;
    }

    public function getCategory(): SignCategory
    {
        return $this->category;
    }

    public function getIterationNumber(): int
    {
        return $this->iterationNumber;
    }

    /**
     * Parses and validates an iteration from a decoded JSON array.
     *
     * @param array<string, mixed> $data
     *
     * @throws SignException on any validation failure (HTTP 400)
     */
    public static function fromArray(array $data): self
    {
        $categoryValue = SignUtils::requireString($data, 'category');
        $category = SignCategory::tryFrom($categoryValue);
        if ($category === null) {
            $valid = array_map(static fn (SignCategory $c): string => $c->value, SignCategory::cases());
            throw new SignException(sprintf('The "category" field must be one of: %s.', implode(', ', $valid)));
        }
        $iterationNumber = SignUtils::requireInt($data, 'iterationNumber');

        $rawInvitees = SignUtils::requireArray($data, 'invitees');
        if ($rawInvitees === []) {
            throw new SignException('The "invitees" field must not be empty.');
        }

        $invitees = [];
        foreach ($rawInvitees as $invitee) {
            if (!is_array($invitee)) {
                throw new SignException('Each entry in "invitees" must be an object.');
            }
            $invitees[] = SignUser::fromArray($invitee);
        }

        return new self(
            invitees: $invitees,
            category: $category,
            iterationNumber: $iterationNumber,
        );
    }
}
