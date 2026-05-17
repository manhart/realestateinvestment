<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Inputs;

use realestateinvestment\classes\Support\InputReader;

final class LoanInput
{
    public function __construct(
        public string $name,
        public int $priority,
        public float $principal,
        public float $interestRate,
        public float $initialRepaymentRate,
        public int $startYear,
        public int $startMonth,
        public int $interestOnlyYears,
        public float $repaymentRateAfterInterestOnly,
        public int $fixedInterestYears,
        public float $followUpInterestRate,
        public bool $constantAnnuity,
        public float $grantAmount,
        public int $grantYear,
        public int $grantMonth,
        public bool $grantReducesDebt,
        public bool $grantTaxable,
        public bool $redeemOnSale,
        public float $specialRepaymentAmount,
        public int $specialRepaymentYear,
        public int $specialRepaymentMonth,
    ) {}

    public static function fromArray(array $data, PropertyInput $property): self
    {
        return new self(
            InputReader::string($data, 'name', 'Darlehen'),
            InputReader::int($data, 'priority', 1),
            InputReader::float($data, 'principal'),
            InputReader::rate($data, 'interestRatePercent', 4),
            InputReader::rate($data, 'initialRepaymentPercent', 2),
            InputReader::int($data, 'startYear', $property->purchaseYear),
            InputReader::month($data, 'startMonth', $property->purchaseMonth),
            max(InputReader::int($data, 'interestOnlyYears'), 0),
            InputReader::rate($data, 'repaymentRateAfterInterestOnlyPercent', InputReader::float($data, 'initialRepaymentPercent', 2)),
            max(InputReader::int($data, 'fixedInterestYears', 10), 0),
            InputReader::rate($data, 'followUpInterestRatePercent', InputReader::float($data, 'interestRatePercent', 4)),
            InputReader::bool($data, 'constantAnnuity', true),
            InputReader::float($data, 'grantAmount'),
            InputReader::int($data, 'grantYear'),
            InputReader::month($data, 'grantMonth', 12),
            InputReader::bool($data, 'grantReducesDebt', true),
            InputReader::bool($data, 'grantTaxable'),
            InputReader::bool($data, 'redeemOnSale', true),
            InputReader::float($data, 'specialRepaymentAmount'),
            InputReader::int($data, 'specialRepaymentYear'),
            InputReader::month($data, 'specialRepaymentMonth', 12),
        );
    }
}
