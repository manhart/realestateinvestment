<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Inputs;

use realestateinvestment\classes\Support\InputReader;

final class ParkingUnitInput
{
    public function __construct(
        public string $label,
        public float $purchasePrice,
        public float $monthlyRent,
        public float $buildingShare,
        public float $landShare,
        public string $depreciationMode,
        public float $depreciationRate,
        public float $depreciationBasis,
        public int $depreciationStartYear,
        public int $depreciationStartMonth,
        public bool $includedInPurchasePrice,
    ) {}

    public static function fromArray(array $data): self
    {
        $depreciationMode = InputReader::string($data, 'depreciationMode', 'building_basis');
        $depreciationMode = match($depreciationMode) {
            'building' => 'building_basis',
            'custom' => 'custom_linear',
            default => $depreciationMode,
        };
        if(!in_array($depreciationMode, ['building_basis', 'linear_building', 'custom_linear'], true)) {
            $depreciationMode = 'building_basis';
        }
        $depreciationStartMonth = InputReader::int($data, 'depreciationStartMonth');
        if($depreciationStartMonth < 1 || $depreciationStartMonth > 12) {
            $depreciationStartMonth = 0;
        }

        return new self(
            InputReader::string($data, 'label', 'Stellplatz'),
            InputReader::float($data, 'purchasePrice'),
            InputReader::float($data, 'monthlyRent'),
            InputReader::rate($data, 'buildingSharePercent', 80),
            InputReader::rate($data, 'landSharePercent', 20),
            $depreciationMode,
            InputReader::rate($data, 'depreciationRatePercent'),
            InputReader::float($data, 'depreciationBasis'),
            InputReader::int($data, 'depreciationStartYear'),
            $depreciationStartMonth,
            InputReader::bool($data, 'includedInPurchasePrice', true),
        );
    }
}
