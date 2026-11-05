<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Workflow\Signing\State;

use Dbp\Relay\PortfolioBundle\SignApi\SignCategory;

final class SigningIteration
{
    /**
     * @param list<SigningInvitee>                         $invitees
     * @param array{fileId: string, filename: string}|null $signedDocument
     */
    public function __construct(
        public int $number,
        public SignCategory $category,
        public array $invitees,
        public ?array $signedDocument = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['number'],
            SignCategory::from($data['category']),
            array_map(SigningInvitee::fromArray(...), $data['invitees']),
            $data['signedDocument'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'category' => $this->category->value,
            'invitees' => array_map(static fn (SigningInvitee $invitee): array => $invitee->toArray(), $this->invitees),
            'signedDocument' => $this->signedDocument,
        ];
    }
}
