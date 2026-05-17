<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Inputs;

use realestateinvestment\classes\Support\InputReader;

final class PropertyInput
{
    public function __construct(
        public string $name,
        public string $address,
        public string $state,
        public int $purchaseYear,
        public int $purchaseMonth,
        public int $completionYear,
        public int $completionMonth,
        public int $rentStartYear,
        public int $rentStartMonth,
        public int $saleYear,
        public int $saleMonth,
        public float $livingArea,
        public float $apartmentPurchasePrice,
        public float $otherPurchasePrice,
        public float $furniturePurchasePrice,
        public float $landShare,
        public float $buildingShare,
        public string $energyClass,
        public bool $newBuilding,
        public bool $furnished,
    ) {}

    public static function fromArray(array $data): self
    {
        $purchaseYear = InputReader::int($data, 'purchaseYear', 2026);
        return new self(
            InputReader::string($data, 'name', 'Immobilie'),
            InputReader::string($data, 'address'),
            InputReader::string($data, 'state', 'BY'),
            $purchaseYear,
            InputReader::month($data, 'purchaseMonth'),
            InputReader::int($data, 'completionYear', $purchaseYear),
            InputReader::month($data, 'completionMonth'),
            InputReader::int($data, 'rentStartYear', $purchaseYear),
            InputReader::month($data, 'rentStartMonth'),
            InputReader::int($data, 'saleYear', $purchaseYear + 10),
            InputReader::month($data, 'saleMonth', 12),
            InputReader::float($data, 'livingArea'),
            InputReader::float($data, 'apartmentPurchasePrice'),
            InputReader::float($data, 'otherPurchasePrice'),
            InputReader::float($data, 'furniturePurchasePrice'),
            InputReader::rate($data, 'landSharePercent', 20),
            InputReader::rate($data, 'buildingSharePercent', 80),
            InputReader::string($data, 'energyClass'),
            InputReader::bool($data, 'newBuilding', true),
            InputReader::bool($data, 'furnished'),
        );
    }
}
