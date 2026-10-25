<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Inputs;

use InvalidArgumentException;
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
        public float $disagioAmount = 0.0,
        public ?int $disagioPaymentYear = null,
        public bool $disagioFinanced = false,
        public bool $disagioDeductible = true,
        public string $landRegisterLienCostMode = 'percent',
        public float $landRegisterLienTotalAmount = 0,
        public float $landRegisterLienNotaryAmount = 0,
        public float $landRegisterLienRegistrationAmount = 0,
    ) {}

    public static function fromArray(array $data, string $state = 'BY'): self
    {
        $lienMode = InputReader::string($data, 'landRegisterLienCostMode', 'percent');
        $lienTotal = InputReader::float($data, 'landRegisterLienTotalAmount');
        $lienNotary = InputReader::float($data, 'landRegisterLienNotaryAmount');
        $lienRegistration = InputReader::float($data, 'landRegisterLienRegistrationAmount');
        $activeAmounts = match($lienMode) {
            'percent' => [InputReader::float($data, 'landRegisterLienPercent', 0.5)],
            'total' => [$lienTotal],
            'itemized' => [$lienNotary, $lienRegistration],
            default => throw new InvalidArgumentException('Grundschuld-Kosten: unbekannte Eingabeart.'),
        };
        foreach($activeAmounts as $amount) {
            if($amount < 0) throw new InvalidArgumentException('Grundschuld-Kosten dürfen nicht negativ sein.');
        }
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
            InputReader::float($data, 'disagioAmount'),
            isset($data['disagioPaymentYear']) ? InputReader::int($data, 'disagioPaymentYear') : null,
            InputReader::bool($data, 'disagioFinanced'),
            InputReader::bool($data, 'disagioDeductible', true),
            $lienMode,
            $lienTotal,
            $lienNotary,
            $lienRegistration,
        );
    }

    public function disagioYear(int $purchaseYear): int
    {
        return $this->disagioPaymentYear ?? $purchaseYear;
    }
}
