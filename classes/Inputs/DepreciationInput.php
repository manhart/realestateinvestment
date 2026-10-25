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
        public bool $linearRateOverrideEnabled = false,
        public float $usefulLifeYears = 33,
        public float $enteredLinearRate = 0.03,
        public array $taxEligibility = [],
        public string $special7bRulePeriod = '',
        public ?int $special7bLastYear = null,
        public float $special7bEligibleCostsOverride = 0,
    ) {}

    public static function fromArray(array $data, PropertyInput $property): self
    {
        [$standardRate, $standardLife] = match(true) {
            $property->completionYear >= 2023 => [3, 33],
            $property->completionYear >= 1925 => [2, 50],
            default => [2.5, 40],
        };
        $override = InputReader::bool($data, 'linearRateOverrideEnabled');
        $eligibility = [];
        foreach(['newDwelling', 'qng', 'rentalUse', 'timelyAcquisition', 'degressive', 'costAllocation'] as $key) {
            $value = $data['taxEligibility'][$key] ?? 'unknown';
            $eligibility[$key] = in_array($value, ['fulfilled', 'failed'], true) ? $value : 'unknown';
        }
        return new self(
            InputReader::int($data, 'startYear', $property->completionYear),
            InputReader::month($data, 'startMonth', $property->completionMonth),
            InputReader::float($data, 'buildingBasis'),
            InputReader::bool($data, 'buildingBasisOverrideEnabled'),
            InputReader::bool($data, 'degressiveActive', true),
            InputReader::rate($data, 'degressiveRatePercent', 5),
            $override ? InputReader::rate($data, 'linearRatePercent', $standardRate) : $standardRate / 100,
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
            [],
            $override,
            $override ? InputReader::float($data, 'usefulLifeYears', $standardLife) : $standardLife,
            InputReader::rate($data, 'linearRatePercent', $standardRate),
            $eligibility,
            InputReader::string($data, 'special7bRulePeriod'),
            null,
            InputReader::float($data, 'special7bEligibleCostsOverride'),
        );
    }
}
