<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Inputs;

use realestateinvestment\classes\Support\InputReader;

final class DepreciationInput
{
    public function __construct(
        public int $startYear,
        public int $startMonth,
        public float $buildingBasis,
        public bool $buildingBasisOverrideEnabled,
        public bool $degressiveActive,
        public float $degressiveRate,
        public float $linearRate,
        public bool $autoSwitchToLinear,
        public bool $special7bActive,
        public string $special7bApplicationDate,
        public float $special7bArea,
        public float $special7bConstructionCostLimitPerSqm,
        public float $special7bActualConstructionCostPerSqm,
        public float $special7bLimitPerSqm,
        public float $special7bRate,
        public int $special7bYears,
        public float $special7bBasis,
        public float $special7bCap,
        public bool $special7bReducesBookValueImmediately,
        public float $furnitureBasis,
        public float $furnitureRate,
        public float $parkingBasis,
        public float $parkingRate,
        public array $parkingDepreciationItems = [],
    ) {}

    public static function fromArray(array $data, PropertyInput $property): self
    {
        return new self(
            InputReader::int($data, 'startYear', $property->completionYear),
            InputReader::month($data, 'startMonth', $property->completionMonth),
            InputReader::float($data, 'buildingBasis'),
            InputReader::bool($data, 'buildingBasisOverrideEnabled'),
            InputReader::bool($data, 'degressiveActive', true),
            InputReader::rate($data, 'degressiveRatePercent', 5),
            InputReader::rate($data, 'linearRatePercent', 3),
            InputReader::bool($data, 'autoSwitchToLinear', true),
            InputReader::bool($data, 'special7bActive'),
            InputReader::string($data, 'special7bApplicationDate'),
            InputReader::float($data, 'special7bArea'),
            InputReader::float($data, 'special7bConstructionCostLimitPerSqm'),
            InputReader::float($data, 'special7bActualConstructionCostPerSqm'),
            InputReader::float($data, 'special7bLimitPerSqm'),
            InputReader::rate($data, 'special7bRatePercent', 5),
            max(InputReader::int($data, 'special7bYears', 4), 0),
            InputReader::float($data, 'special7bBasis'),
            InputReader::float($data, 'special7bCap'),
            InputReader::bool($data, 'special7bReducesBookValueImmediately'),
            InputReader::float($data, 'furnitureBasis'),
            InputReader::rate($data, 'furnitureRatePercent', 10),
            InputReader::float($data, 'parkingBasis'),
            InputReader::rate($data, 'parkingRatePercent', 3),
        );
    }
}
