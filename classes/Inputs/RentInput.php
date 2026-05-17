<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Inputs;

use realestateinvestment\classes\Support\InputReader;

final class RentInput
{
    public function __construct(
        public float $apartmentMonthlyRent,
        public float $annualIncrease,
        public int $increaseEveryYears,
        public float $vacancyRate,
        public int $increaseStartYear,
        public bool $monthlyPartialYears,
        public float $otherAnnualIncome,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            InputReader::float($data, 'apartmentMonthlyRent'),
            InputReader::rate($data, 'annualIncreasePercent', 2),
            max(InputReader::int($data, 'increaseEveryYears', 1), 1),
            min(max(InputReader::rate($data, 'vacancyPercent'), 0), 1),
            InputReader::int($data, 'increaseStartYear', 0),
            InputReader::bool($data, 'monthlyPartialYears', true),
            InputReader::float($data, 'otherAnnualIncome'),
        );
    }
}
