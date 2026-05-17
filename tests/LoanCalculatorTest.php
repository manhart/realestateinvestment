<?php
declare(strict_types=1);

namespace realestateinvestment\tests;

use PHPUnit\Framework\TestCase;
use realestateinvestment\classes\Calculators\LoanCalculator;
use realestateinvestment\classes\Inputs\LoanInput;

final class LoanCalculatorTest extends TestCase
{
    public function testInterestOnlyLoanUsesRepaymentRateAfterInterestOnlyForConstantAnnuity(): void
    {
        $loan = new LoanInput(
            'Förderdarlehen',
            1,
            150000,
            0.0246,
            0.0,
            2028,
            9,
            3,
            0.0206,
            10,
            0.0246,
            true,
            0,
            0,
            12,
            true,
            false,
            true,
            0,
            0,
            12,
        );

        $result = (new LoanCalculator())->calculate([$loan], 2028, 2032);

        self::assertEqualsWithDelta(1230, $result[2028]['interest'], 0.01);
        self::assertEqualsWithDelta(0, $result[2030]['repayment'], 0.01);
        self::assertEqualsWithDelta(1033.17, $result[2031]['repayment'], 0.01);
        self::assertEqualsWithDelta(6780, $result[2032]['annuity'], 0.01);
        self::assertEqualsWithDelta(145816.04, $result[2032]['remainingDebt'], 0.01);
    }
}
