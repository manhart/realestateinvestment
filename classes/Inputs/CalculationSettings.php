<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Inputs;

use realestateinvestment\classes\Support\InputReader;

final class CalculationSettings
{
    public const AUTO_SPECIAL_REPAYMENT_NONE = 'none';
    public const AUTO_SPECIAL_REPAYMENT_POSITIVE_CASHFLOW = 'positive_cashflow_after_tax';

    public function __construct(
        public float $discountRate,
        public string $roundingMode,
        public float $initialEquityAmount,
        public string $autoSpecialRepaymentMode,
    ) {}

    public static function fromArray(array $data): self
    {
        $autoSpecialRepaymentMode = InputReader::string($data, 'autoSpecialRepaymentMode', self::AUTO_SPECIAL_REPAYMENT_NONE);
        if(!in_array($autoSpecialRepaymentMode, [
            self::AUTO_SPECIAL_REPAYMENT_NONE,
            self::AUTO_SPECIAL_REPAYMENT_POSITIVE_CASHFLOW,
        ], true)) {
            $autoSpecialRepaymentMode = self::AUTO_SPECIAL_REPAYMENT_NONE;
        }

        return new self(
            InputReader::rate($data, 'discountRatePercent', 5),
            InputReader::string($data, 'roundingMode', 'commercial'),
            InputReader::float($data, 'initialEquityAmount'),
            $autoSpecialRepaymentMode,
        );
    }
}
