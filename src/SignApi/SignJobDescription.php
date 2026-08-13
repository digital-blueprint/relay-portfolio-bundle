<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\SignApi;

/**
 * The parsed jobDescription model sent to startProcess:
 *
 *   {
 *     "constituent": { ...SignUser... },
 *     "positionType": "SIGNATURE_PAGE",
 *     "metaData": { ...SignJobMetaData... },
 *     "iterationData": [ { ...SignIteration... }, ... ]
 *   }
 *
 * The constituent "owns" the job; iterationData drives the ordered, iterative
 * signing workflow.
 */
class SignJobDescription
{
    /**
     * @param SignIteration[] $iterationData
     */
    public function __construct(
        private readonly SignUser $constituent,
        private readonly string $positionType,
        private readonly array $iterationData,
        private readonly ?SignJobMetaData $metaData = null,
    ) {
    }

    public function getConstituent(): SignUser
    {
        return $this->constituent;
    }

    public function getPositionType(): string
    {
        return $this->positionType;
    }

    /**
     * @return SignIteration[]
     */
    public function getIterationData(): array
    {
        return $this->iterationData;
    }

    public function getMetaData(): ?SignJobMetaData
    {
        return $this->metaData;
    }

    /**
     * Parses and validates a jobDescription from a decoded JSON array.
     *
     * Requires a valid `constituent`, a non-empty `positionType` and at least one
     * `iterationData` entry. `metaData` is optional.
     *
     * @param array<string, mixed> $data
     *
     * @throws SignException on any validation failure (HTTP 400)
     */
    public static function fromArray(array $data): self
    {
        $constituent = SignUser::fromArray(SignUtils::requireArray($data, 'constituent'));
        $positionType = SignUtils::requireString($data, 'positionType');

        $rawIterations = SignUtils::requireArray($data, 'iterationData');
        if ($rawIterations === []) {
            throw new SignException('The "iterationData" field must not be empty.');
        }
        $iterations = [];
        foreach ($rawIterations as $iteration) {
            if (!is_array($iteration)) {
                throw new SignException('Each entry in "iterationData" must be an object.');
            }
            $iterations[] = SignIteration::fromArray($iteration);
        }

        $metaData = null;
        if (array_key_exists('metaData', $data)) {
            if (!is_array($data['metaData'])) {
                throw new SignException('The "metaData" field must be an object.');
            }
            $metaData = SignJobMetaData::fromArray($data['metaData']);
        }

        return new self(
            constituent: $constituent,
            positionType: $positionType,
            iterationData: $iterations,
            metaData: $metaData,
        );
    }
}
