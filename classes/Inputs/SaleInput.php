<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Inputs;

use realestateinvestment\classes\Support\InputReader;

final class SaleInput
{
    public function __construct(
        public float $annualValueIncrease,
        public float $sellingCostsRate,
        public float $sellingCostsAmount,
        public float $prepaymentPenaltyAmount,
        public bool $taxableSale,
        public bool $speculationPeriodReached,
        public bool $taxFreeSale,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            InputReader::rate($data, 'annualValueIncreasePercent', 2),
            InputReader::rate($data, 'sellingCostsPercent'),
            InputReader::float($data, 'sellingCostsAmount'),
            InputReader::float($data, 'prepaymentPenaltyAmount'),
            InputReader::bool($data, 'taxableSale'),
            InputReader::bool($data, 'speculationPeriodReached', true),
            InputReader::bool($data, 'taxFreeSale', true),
        );
    }
}
