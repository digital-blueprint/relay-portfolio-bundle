<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\SignApi;

/**
 * A user / constituent object, e.g.:
 *
 *   {
 *     "classifier": "EMAIL",
 *     "name": "max.test@example.com",
 *     "@class": "...api.User"
 *   }
 *
 * Used both as the job "constituent" (owner) and as the user body of cancelJob.
 * External signers carry additional optional fields (externalUserName, locale,
 * roleName).
 *
 * The `@class` field is resolved to a {@see SignUserClass} by its suffix; all
 * known user classes are supported.
 */
class SignUser
{
    public function __construct(
        private readonly SignUserClass $class,
        private readonly SignClassifier $classifier,
        private readonly string $name,
        private readonly ?string $roleName = null,
        private readonly ?string $externalUserName = null,
        private readonly ?string $locale = null,
    ) {
    }

    public function getClass(): SignUserClass
    {
        return $this->class;
    }

    public function getClassifier(): SignClassifier
    {
        return $this->classifier;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRoleName(): ?string
    {
        return $this->roleName;
    }

    public function getExternalUserName(): ?string
    {
        return $this->externalUserName;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * Parses and validates a user object from a decoded JSON array.
     *
     * Requires `@class` (of a supported user class), `classifier` and `name`
     * to be present strings.
     *
     * @param array<string, mixed> $data
     *
     * @throws SignException on any validation failure (HTTP 400)
     */
    public static function fromArray(array $data): self
    {
        $classValue = SignUtils::requireString($data, '@class');
        $class = SignUserClass::fromClassString($classValue);
        if ($class === null) {
            throw new SignException('The "@class" field must be a supported user class.');
        }

        $classifierValue = SignUtils::requireString($data, 'classifier');
        $classifier = SignClassifier::tryFrom($classifierValue);
        if ($classifier === null) {
            $valid = array_map(static fn (SignClassifier $c): string => $c->value, SignClassifier::cases());
            throw new SignException(sprintf('The "classifier" field must be one of: %s.', implode(', ', $valid)));
        }
        // Some clients prepend a space to the email — trim it here.
        $name = trim(SignUtils::requireString($data, 'name'));

        return new self(
            class: $class,
            classifier: $classifier,
            name: $name,
            roleName: SignUtils::optionalString($data, 'roleName'),
            externalUserName: SignUtils::optionalString($data, 'externalUserName'),
            locale: SignUtils::optionalString($data, 'locale'),
        );
    }
}
