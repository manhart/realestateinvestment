<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Inputs;

use realestateinvestment\classes\Support\InputReader;

final class ConstructionInterestInput
{
    /**
     * @param ConstructionInterestYearInput[] $yearlyEntries
     */
    public function __construct(
        public array $yearlyEntries,
    ) {}

    public static function fromArray(array $data): self
    {
        $yearlyEntries = array_values(array_filter(array_map(
            static fn(array $row): ConstructionInterestYearInput => ConstructionInterestYearInput::fromArray($row),
            InputReader::list($data, 'yearlyEntries'),
        ), static fn(ConstructionInterestYearInput $row): bool => $row->year > 0 && $row->amount > 0));

        return new self(
            $yearlyEntries,
        );
    }

    public function hasYearlyEntries(): bool
    {
        return $this->yearlyEntries !== [];
    }
}
