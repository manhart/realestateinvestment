<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Inputs;

use realestateinvestment\classes\Support\InputReader;

final class ExpenseInput
{
    public function __construct(
        public float $nonRecoverableHausgeldMonthly,
        public float $maintenanceReserveMonthly,
        public float $managementMonthly,
        public float $servicePoolMonthly,
        public float $furnitureReserveMonthly,
        public float $otherMonthlyCosts,
        public float $annualIncrease,
        public int $increaseEveryYears,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            InputReader::float($data, 'nonRecoverableHausgeldMonthly'),
            InputReader::float($data, 'maintenanceReserveMonthly'),
            InputReader::float($data, 'managementMonthly'),
            InputReader::float($data, 'servicePoolMonthly'),
            InputReader::float($data, 'furnitureReserveMonthly'),
            InputReader::float($data, 'otherMonthlyCosts'),
            InputReader::rate($data, 'annualIncreasePercent', 2),
            max(InputReader::int($data, 'increaseEveryYears', 1), 1),
        );
    }

    public function monthlyTotal(): float
    {
        return $this->nonRecoverableHausgeldMonthly
            + $this->maintenanceReserveMonthly
            + $this->managementMonthly
            + $this->servicePoolMonthly
            + $this->furnitureReserveMonthly
            + $this->otherMonthlyCosts;
    }

    public function taxDeductibleMonthlyTotal(): float
    {
        return $this->nonRecoverableHausgeldMonthly
            + $this->managementMonthly
            + $this->servicePoolMonthly
            + $this->otherMonthlyCosts;
    }
}
