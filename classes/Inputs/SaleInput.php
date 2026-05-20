<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Inputs;

use realestateinvestment\classes\Support\InputReader;

final class SaleInput
{
    public function __construct(
        public float $annualValueIncrease,
        public float $parkingAnnualValueIncrease,
        public bool $includeParkingInSalePrice,
        public float $sellingCostsRate,
        public float $sellingCostsAmount,
        public float $prepaymentPenaltyAmount,
        public bool $taxableSale,
        public bool $speculationPeriodReached,
        public bool $taxFreeSale,
    ) {}

    public static function fromArray(array $data): self
    {
        $annualValueIncreasePercent = InputReader::float($data, 'annualValueIncreasePercent', 2);
        $parkingAnnualValueIncreasePercent = array_key_exists('parkingAnnualValueIncreasePercent', $data)
            ? InputReader::float($data, 'parkingAnnualValueIncreasePercent', $annualValueIncreasePercent)
            : $annualValueIncreasePercent;

        return new self(
            $annualValueIncreasePercent / 100,
            $parkingAnnualValueIncreasePercent / 100,
            InputReader::bool($data, 'includeParkingInSalePrice', true),
            InputReader::rate($data, 'sellingCostsPercent'),
            InputReader::float($data, 'sellingCostsAmount'),
            InputReader::float($data, 'prepaymentPenaltyAmount'),
            InputReader::bool($data, 'taxableSale'),
            InputReader::bool($data, 'speculationPeriodReached', true),
            InputReader::bool($data, 'taxFreeSale', true),
        );
    }
}
