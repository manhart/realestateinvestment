<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Inputs;

use realestateinvestment\classes\Support\InputReader;

final class AcquisitionCostInput
{
    public const NOTARY_LAND_REGISTER_AFA_BASIS = 'afa_basis';
    public const NOTARY_LAND_REGISTER_IMMEDIATE_DEDUCTIBLE = 'immediate_deductible';

    public function __construct(
        public float $realEstateTransferTax,
        public float $notaryRate,
        public float $landRegisterPurchaseRate,
        public float $landRegisterLienRate,
        public float $brokerRate,
        public float $otherAcquisitionCosts,
        public float $otherFinancingCosts,
        public bool $financingCostsDeductible,
        public string $notaryLandRegisterTaxTreatment,
    ) {}

    public static function fromArray(array $data, string $state = 'BY'): self
    {
        $defaultTransferTax = $state === 'BY' ? 3.5 : 5.0;
        $notaryLandRegisterTaxTreatment = InputReader::string($data, 'notaryLandRegisterTaxTreatment', self::NOTARY_LAND_REGISTER_AFA_BASIS);
        if(!in_array($notaryLandRegisterTaxTreatment, [self::NOTARY_LAND_REGISTER_AFA_BASIS, self::NOTARY_LAND_REGISTER_IMMEDIATE_DEDUCTIBLE], true)) {
            $notaryLandRegisterTaxTreatment = self::NOTARY_LAND_REGISTER_AFA_BASIS;
        }

        return new self(
            InputReader::rate($data, 'realEstateTransferTaxPercent', $defaultTransferTax),
            InputReader::rate($data, 'notaryPercent', 1.5),
            InputReader::rate($data, 'landRegisterPurchasePercent', 0.5),
            InputReader::rate($data, 'landRegisterLienPercent', 0.5),
            InputReader::rate($data, 'brokerPercent'),
            InputReader::float($data, 'otherAcquisitionCosts'),
            InputReader::float($data, 'otherFinancingCosts'),
            InputReader::bool($data, 'financingCostsDeductible', true),
            $notaryLandRegisterTaxTreatment,
        );
    }
}
