<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Inputs;

use realestateinvestment\classes\Support\InputReader;

final class TaxInput
{
    public const CALCULATION_METHOD_MARGINAL_RATE = 'marginal_rate';
    public const CALCULATION_METHOD_SECTION_32A = 'section_32a';

    public function __construct(
        public float $taxableIncomeBeforeInvestment,
        public string $calculationMethod,
        public string $assessmentType,
        public bool $churchTax,
        public string $churchTaxState,
        public bool $solidaritySurcharge,
        public bool $useMarginalTaxRate,
        public float $marginalTaxRate,
        public bool $calculateIncomeTax,
        public int $taxYear,
        public float $taxableIncomeAnnualIncrease = 0.0,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            InputReader::float($data, 'taxableIncomeBeforeInvestment', 80000),
            InputReader::string($data, 'calculationMethod', self::defaultCalculationMethod($data)),
            InputReader::string($data, 'assessmentType', 'single'),
            InputReader::bool($data, 'churchTax'),
            InputReader::string($data, 'churchTaxState', 'BY'),
            InputReader::bool($data, 'solidaritySurcharge'),
            InputReader::bool($data, 'useMarginalTaxRate', true),
            InputReader::rate($data, 'marginalTaxRatePercent', 42),
            InputReader::bool($data, 'calculateIncomeTax'),
            InputReader::int($data, 'taxYear', 2026),
            InputReader::rate($data, 'taxableIncomeAnnualIncreasePercent'),
        );
    }

    private static function defaultCalculationMethod(array $data): string
    {
        if(($data['calculateIncomeTax'] ?? false) && !($data['useMarginalTaxRate'] ?? true))
            return self::CALCULATION_METHOD_SECTION_32A;

        return self::CALCULATION_METHOD_MARGINAL_RATE;
    }
}
