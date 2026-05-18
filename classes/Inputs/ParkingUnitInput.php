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
        public bool $depreciable,
        public string $depreciationMode,
        public float $depreciationRate,
        public bool $includedInPurchasePrice,
    ) {}

    public static function fromArray(array $data): self
    {
        $depreciationMode = InputReader::string($data, 'depreciationMode', 'building');
        if(!in_array($depreciationMode, ['building', 'custom'], true)) {
            $depreciationMode = 'building';
        }

        return new self(
            InputReader::string($data, 'label', 'Stellplatz'),
            InputReader::float($data, 'purchasePrice'),
            InputReader::float($data, 'monthlyRent'),
            InputReader::rate($data, 'buildingSharePercent', 80),
            InputReader::rate($data, 'landSharePercent', 20),
            InputReader::bool($data, 'depreciable', true),
            $depreciationMode,
            InputReader::rate($data, 'depreciationRatePercent'),
            InputReader::bool($data, 'includedInPurchasePrice', true),
        );
    }
}
