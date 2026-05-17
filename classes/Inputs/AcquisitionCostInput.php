<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Inputs;

use realestateinvestment\classes\Support\InputReader;

final class AcquisitionCostInput
{
    public function __construct(
        public float $realEstateTransferTax,
        public float $notaryRate,
        public float $landRegisterPurchaseRate,
        public float $landRegisterLienRate,
        public float $brokerRate,
        public float $otherAcquisitionCosts,
        public float $otherFinancingCosts,
        public bool $financingCostsDeductible,
    ) {}

    public static function fromArray(array $data, string $state = 'BY'): self
    {
        $defaultTransferTax = $state === 'BY' ? 3.5 : 5.0;
        return new self(
            InputReader::rate($data, 'realEstateTransferTaxPercent', $defaultTransferTax),
            InputReader::rate($data, 'notaryPercent', 1.5),
            InputReader::rate($data, 'landRegisterPurchasePercent', 0.5),
            InputReader::rate($data, 'landRegisterLienPercent', 0.5),
            InputReader::rate($data, 'brokerPercent'),
            InputReader::float($data, 'otherAcquisitionCosts'),
            InputReader::float($data, 'otherFinancingCosts'),
            InputReader::bool($data, 'financingCostsDeductible', true),
        );
    }
}
