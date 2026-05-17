<?php
declare(strict_types=1);

namespace realestateinvestment\tests;

use PHPUnit\Framework\TestCase;
use realestateinvestment\classes\Calculators\TaxCalculator;
use realestateinvestment\classes\Inputs\TaxInput;

final class TaxCalculatorTest extends TestCase
{
    public function testMarginalRateModeKeepsExistingSimplifiedTaxEffect(): void
    {
        $tax = new TaxInput(100000, TaxInput::CALCULATION_METHOD_MARGINAL_RATE, 'single', false, 'BY', false, true, 0.35, false, 2026);
        $result = (new TaxCalculator())->calculate($tax, -1000);

        self::assertSame(TaxInput::CALCULATION_METHOD_MARGINAL_RATE, $result['calculationMethod']);
        self::assertEqualsWithDelta(35000, $result['taxBefore'], 0.01);
        self::assertEqualsWithDelta(34650, $result['taxAfter'], 0.01);
        self::assertEqualsWithDelta(350, $result['taxEffect'], 0.01);
        self::assertEqualsWithDelta(0, $result['solidaritySurchargeBefore'], 0.01);
    }

    public function testSection32aUses2026BasicTariff(): void
    {
        $tax = new TaxInput(94439, TaxInput::CALCULATION_METHOD_SECTION_32A, 'single', false, 'BY', false, false, 0.42, true, 2026);
        $result = (new TaxCalculator())->calculate($tax, 0);

        self::assertEqualsWithDelta(28528, $result['tariffIncomeTaxBefore'], 0.01);
        self::assertEqualsWithDelta(973.18, $result['solidaritySurchargeBefore'], 0.01);
        self::assertEqualsWithDelta(29501.18, $result['taxBefore'], 0.01);
    }

    public function testSection32aSplittingUsesTwiceHalfIncomeTax(): void
    {
        $tax = new TaxInput(94439, TaxInput::CALCULATION_METHOD_SECTION_32A, 'splitting', false, 'BY', false, false, 0.42, true, 2026);
        $result = (new TaxCalculator())->calculate($tax, 0);

        self::assertEqualsWithDelta(19170, $result['tariffIncomeTaxBefore'], 0.01);
    }

    public function testSection32aSplittingMatchesRealEstatePilotExampleFirstYear(): void
    {
        $tax = new TaxInput(94439, TaxInput::CALCULATION_METHOD_SECTION_32A, 'splitting', false, 'BY', false, false, 0.42, true, 2026);
        $result = (new TaxCalculator())->calculate($tax, -3387.10);

        self::assertEqualsWithDelta(91051.90, $result['taxableAfter'], 0.01);
        self::assertEqualsWithDelta(19170, $result['taxBefore'], 0.01);
        self::assertEqualsWithDelta(18022, $result['taxAfter'], 0.01);
        self::assertEqualsWithDelta(1148, $result['taxEffect'], 0.01);
    }

    public function testSolidaritySurchargeAppliesFreeLimitMilderungszoneAndFullRate(): void
    {
        $calculator = new TaxCalculator();
        $belowLimit = new TaxInput(70000, TaxInput::CALCULATION_METHOD_SECTION_32A, 'single', false, 'BY', false, false, 0.42, true, 2026);
        $milderung = new TaxInput(94439, TaxInput::CALCULATION_METHOD_SECTION_32A, 'single', false, 'BY', false, false, 0.42, true, 2026);
        $fullRate = new TaxInput(140000, TaxInput::CALCULATION_METHOD_SECTION_32A, 'single', false, 'BY', false, false, 0.42, true, 2026);

        self::assertEqualsWithDelta(0, $calculator->calculate($belowLimit, 0)['solidaritySurchargeBefore'], 0.01);
        self::assertEqualsWithDelta(973.18, $calculator->calculate($milderung, 0)['solidaritySurchargeBefore'], 0.01);
        self::assertEqualsWithDelta(2621.52, $calculator->calculate($fullRate, 0)['solidaritySurchargeBefore'], 0.01);
    }

    public function testChurchTaxUsesStateRateOnlyWhenEnabled(): void
    {
        $calculator = new TaxCalculator();
        $by = new TaxInput(94439, TaxInput::CALCULATION_METHOD_SECTION_32A, 'single', true, 'BY', false, false, 0.42, true, 2026);
        $nw = new TaxInput(94439, TaxInput::CALCULATION_METHOD_SECTION_32A, 'single', true, 'NW', false, false, 0.42, true, 2026);
        $disabled = new TaxInput(94439, TaxInput::CALCULATION_METHOD_SECTION_32A, 'single', false, 'NW', false, false, 0.42, true, 2026);

        self::assertEqualsWithDelta(2282.24, $calculator->calculate($by, 0)['churchTaxBefore'], 0.01);
        self::assertEqualsWithDelta(2567.52, $calculator->calculate($nw, 0)['churchTaxBefore'], 0.01);
        self::assertEqualsWithDelta(0, $calculator->calculate($disabled, 0)['churchTaxBefore'], 0.01);
    }
}
