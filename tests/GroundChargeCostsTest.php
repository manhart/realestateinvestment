<?php
declare(strict_types=1);

namespace realestateinvestment\tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use realestateinvestment\classes\Calculators\InvestmentCalculator;
use realestateinvestment\classes\Inputs\AcquisitionCostInput;
use realestateinvestment\classes\Inputs\RealEstateInvestmentScenario;

final class GroundChargeCostsTest extends TestCase
{
    private function calculate(array $costs, float $principal = 100000): array
    {
        return (new InvestmentCalculator())->calculate(RealEstateInvestmentScenario::fromArray([
            'property' => ['apartmentPurchasePrice' => 100000, 'purchaseYear' => 2026, 'completionYear' => 2026,
                'completionMonth' => 1, 'rentStartYear' => 2026, 'rentStartMonth' => 1, 'saleYear' => 2037],
            'loans' => [['principal' => $principal, 'interestRatePercent' => 0, 'initialRepaymentPercent' => 0]],
            'acquisitionCosts' => $costs + ['realEstateTransferTaxPercent' => 0, 'notaryPercent' => 0,
                'landRegisterPurchasePercent' => 0],
            'depreciation' => ['degressiveActive' => false, 'linearRateOverrideEnabled' => true, 'linearRatePercent' => 0],
            'tax' => ['calculationMethod' => 'marginal_rate', 'taxableIncomeBeforeInvestment' => 80000, 'marginalTaxRatePercent' => 42],
            'sale' => ['annualValueIncreasePercent' => 0],
        ]))->toArray();
    }

    public function testOnlySelectedInputModeContributesToCosts(): void
    {
        foreach(['percent' => 1000, 'total' => 2000, 'itemized' => 1200] as $mode => $amount) {
            $result = $this->calculate([
                'landRegisterLienCostMode' => $mode, 'landRegisterLienPercent' => 1,
                'landRegisterLienTotalAmount' => 2000, 'landRegisterLienNotaryAmount' => 700,
                'landRegisterLienRegistrationAmount' => 500,
            ]);
            $breakdown = $result['summary']['acquisitionBreakdown'];
            self::assertEqualsWithDelta($amount, $breakdown['landRegisterLien'], 0.000001);
            self::assertSame($mode, $breakdown['landRegisterLienCostMode']);
            self::assertEqualsWithDelta(100000 + $amount, $result['summary']['totalCosts'], 0.000001);
            self::assertEqualsWithDelta(80000, $result['summary']['buildingDepreciationBasis'], 0.000001);
            self::assertEqualsWithDelta($amount, $result['yearlyRows'][0]['deductibleExpenses'], 0.000001);
            $rows = array_column($result['calculationBreakdown'], null, 'label');
            self::assertEqualsWithDelta($amount, $rows['Grundschuld-Kosten']['result'], 0.000001);
            self::assertSame($mode === 'itemized' ? 700.0 : null, $breakdown['landRegisterLienNotaryAmount']);
        }
    }

    public function testMissingModePreservesExistingPercentageAndDefault(): void
    {
        self::assertEqualsWithDelta(500, $this->calculate([])['summary']['acquisitionBreakdown']['landRegisterLien'], 0.000001);
        self::assertEqualsWithDelta(1250, $this->calculate(['landRegisterLienPercent' => 1.25])['summary']['acquisitionBreakdown']['landRegisterLien'], 0.000001);
    }

    public function testExplicitZeroDoesNotFallBackToInactiveInputs(): void
    {
        foreach(['percent', 'total', 'itemized'] as $mode) {
            $data = ['landRegisterLienCostMode' => $mode, 'landRegisterLienPercent' => 5,
                'landRegisterLienTotalAmount' => 5000, 'landRegisterLienNotaryAmount' => 5000,
                'landRegisterLienRegistrationAmount' => 5000];
            foreach(match($mode) {
                'percent' => ['landRegisterLienPercent'],
                'total' => ['landRegisterLienTotalAmount'],
                'itemized' => ['landRegisterLienNotaryAmount', 'landRegisterLienRegistrationAmount'],
            } as $field) $data[$field] = 0;
            self::assertSame(0.0, $this->calculate($data)['summary']['acquisitionBreakdown']['landRegisterLien']);
        }
    }

    public function testAbsoluteAmountsStayConstantWhenDebtChanges(): void
    {
        foreach(['total', 'itemized'] as $mode) {
            $data = ['landRegisterLienCostMode' => $mode, 'landRegisterLienTotalAmount' => 1200,
                'landRegisterLienNotaryAmount' => 700, 'landRegisterLienRegistrationAmount' => 500];
            foreach([0, 100000, 200000] as $principal) {
                self::assertEqualsWithDelta(1200, $this->calculate($data, $principal)['summary']['acquisitionBreakdown']['landRegisterLien'], 0.000001);
            }
        }
        self::assertEqualsWithDelta(1000, $this->calculate([], 200000)['summary']['acquisitionBreakdown']['landRegisterLien'], 0.000001);
    }

    public function testDeductionSwitchDoesNotChangeCashCostsOrDisagioDeduction(): void
    {
        $data = ['landRegisterLienCostMode' => 'itemized', 'landRegisterLienNotaryAmount' => 700,
            'landRegisterLienRegistrationAmount' => 500, 'otherFinancingCosts' => 250,
            'disagioAmount' => 500, 'disagioDeductible' => true];
        $on = $this->calculate($data + ['financingCostsDeductible' => true]);
        $off = $this->calculate($data + ['financingCostsDeductible' => false]);
        self::assertEqualsWithDelta(1950, $on['yearlyRows'][0]['deductibleExpenses'], 0.000001);
        self::assertEqualsWithDelta(500, $off['yearlyRows'][0]['deductibleExpenses'], 0.000001);
        self::assertSame($on['summary']['totalCosts'], $off['summary']['totalCosts']);
        self::assertSame($on['yearlyRows'][0]['netCashflowBeforeTax'], $off['yearlyRows'][0]['netCashflowBeforeTax']);
        self::assertEqualsWithDelta(1450 * 0.42, $on['yearlyRows'][0]['taxEffect'] - $off['yearlyRows'][0]['taxEffect'], 0.000001);
    }

    public function testOnlyActiveValuesAreValidatedAndInactiveValuesAreRetained(): void
    {
        $data = ['landRegisterLienCostMode' => 'total', 'landRegisterLienPercent' => -1,
            'landRegisterLienTotalAmount' => 1000, 'landRegisterLienNotaryAmount' => -5,
            'landRegisterLienRegistrationAmount' => 300];
        $input = AcquisitionCostInput::fromArray($data);
        self::assertSame(-0.01, $input->landRegisterLienRate);
        self::assertSame(-5.0, $input->landRegisterLienNotaryAmount);
        self::assertEqualsWithDelta(1000, $this->calculate($data)['summary']['acquisitionBreakdown']['landRegisterLien'], 0.000001);
        foreach([
            ['landRegisterLienCostMode' => 'invalid'],
            ['landRegisterLienCostMode' => 'percent', 'landRegisterLienPercent' => -1],
            ['landRegisterLienCostMode' => 'total', 'landRegisterLienTotalAmount' => -1],
            ['landRegisterLienCostMode' => 'itemized', 'landRegisterLienNotaryAmount' => -1],
            ['landRegisterLienCostMode' => 'itemized', 'landRegisterLienRegistrationAmount' => -1],
        ] as $invalid) {
            try {
                AcquisitionCostInput::fromArray($invalid);
                self::fail('Invalid active ground-charge input was accepted.');
            } catch(InvalidArgumentException $exception) {
                self::assertStringContainsString('Grundschuld-Kosten', $exception->getMessage());
            }
        }
    }

    public function testMovingOtherCostsToTotalPreservesEveryAnnualResultAndMetric(): void
    {
        $before = $this->calculate(['landRegisterLienPercent' => 0, 'otherFinancingCosts' => 2885.8, 'disagioAmount' => 16050]);
        $after = $this->calculate(['landRegisterLienCostMode' => 'total', 'landRegisterLienTotalAmount' => 2885.8,
            'landRegisterLienPercent' => 0, 'otherFinancingCosts' => 0, 'disagioAmount' => 16050]);
        self::assertSame($before['metrics'], $after['metrics']);
        self::assertSame($before['yearlyRows'], $after['yearlyRows']);
        self::assertSame($before['summary']['totalCosts'], $after['summary']['totalCosts']);
    }
}
