<?php
declare(strict_types=1);

namespace realestateinvestment\tests;

use PHPUnit\Framework\TestCase;
use realestateinvestment\classes\Calculators\InvestmentCalculator;
use realestateinvestment\classes\Inputs\RealEstateInvestmentScenario;

final class FundingBalanceTest extends TestCase
{
    public function testFundingGapExactCoverageAndSurplusPreserveTheSameWealth(): void
    {
        foreach([90000, 100000, 110000] as $principal) {
            $result = $this->calculate($this->scenario($principal));
            $first = $result['yearlyRows'][0];
            self::assertEqualsWithDelta(max(100000 - $principal, 0), $first['oneTimeCashCosts'], 0.000001);
            self::assertEqualsWithDelta(max($principal - 100000, 0), $first['financingSurplus'], 0.000001);
            self::assertEqualsWithDelta($principal - 100000, $first['netCashflowBeforeTax'], 0.000001);
            self::assertEqualsWithDelta(0, $result['metrics']['netWorthEffect'], 0.000001);
            self::assertEqualsWithDelta(0, $result['metrics']['equityNetGain'], 0.000001);
            self::assertEqualsWithDelta(100000, $result['summary']['totalCosts'], 0.000001);
            self::assertEqualsWithDelta($principal, $result['summary']['initialDebt'], 0.000001);
        }
    }

    public function testSurplusAppearsOnlyOnceAndDoesNotBecomeTaxableIncomeOrEquity(): void
    {
        $scenario = $this->scenario();
        $scenario['settings']['initialEquityAmount'] = 1500;
        $result = $this->calculate($scenario);
        self::assertEqualsWithDelta(10000, $result['summary']['financingSurplus'], 0.000001);
        self::assertEqualsWithDelta(10000, array_sum(array_column($result['yearlyRows'], 'financingSurplus')), 0.000001);
        foreach($result['yearlyRows'] as $index => $row) {
            self::assertEqualsWithDelta($index === 0 ? 10000 : 0, $row['financingSurplus'], 0.000001);
            self::assertEqualsWithDelta(0, $row['rentalTaxableIncome'], 0.000001);
            self::assertEqualsWithDelta(0, $row['taxEffect'], 0.000001);
            self::assertEqualsWithDelta(110000, $row['remainingDebt'], 0.000001);
        }
        self::assertEqualsWithDelta(1500, $result['metrics']['initialEquityAmount'], 0.000001);
        self::assertEqualsWithDelta(8500, $result['metrics']['equityCapitalCalls'], 0.000001);
        self::assertEqualsWithDelta(0, $result['metrics']['equityNetGain'], 0.000001);
        $breakdown = array_column($result['calculationBreakdown'], null, 'label');
        self::assertEqualsWithDelta(10000, $breakdown['Kreditüberschuss']['result'], 0.000001);
        self::assertEqualsWithDelta(10000, $breakdown['CF vor Steuer Kaufjahr']['result'], 0.000001);
        self::assertStringContainsString('Kreditüberschuss', $breakdown['CF vor Steuer Kaufjahr']['formula']);
    }

    public function testMixedFundingReservesOnlyFinancedCostsAndKeepsNominalGainInvariant(): void
    {
        foreach([95000, 104500, 107500, 110000] as $principal) {
            for($flags = 0; $flags < 8; $flags++) {
                $scenario = $this->scenario($principal);
                $scenario['tax']['marginalTaxRatePercent'] = 42;
                $scenario['acquisitionCosts']['otherFinancingCosts'] = 500;
                $scenario['acquisitionCosts']['disagioAmount'] = 4000;
                $scenario['acquisitionCosts']['disagioPaymentYear'] = 2028;
                $scenario['acquisitionCosts']['disagioFinanced'] = (bool)($flags & 4);
                $scenario['constructionInterest']['yearlyEntries'] = [
                    ['year' => 2026, 'amount' => 1000, 'deductible' => true, 'financed' => (bool)($flags & 1)],
                    ['year' => 2027, 'amount' => 2000, 'deductible' => true, 'financed' => (bool)($flags & 2)],
                ];
                $result = $this->calculate($scenario);
                $reserved = ($flags & 1 ? 1000 : 0) + ($flags & 2 ? 2000 : 0) + ($flags & 4 ? 4000 : 0);
                $balance = $principal - 100500 - $reserved;
                self::assertEqualsWithDelta(max($balance, 0), $result['summary']['financingSurplus'], 0.000001);
                self::assertEqualsWithDelta(max(-$balance, 0), $result['yearlyRows'][0]['oneTimeCashCosts'], 0.000001);
                self::assertEqualsWithDelta(107500, $result['summary']['totalCosts'], 0.000001);
                self::assertEqualsWithDelta(3150, array_sum(array_column($result['yearlyRows'], 'taxEffect')), 0.000001);
                self::assertEqualsWithDelta(-4350, $result['metrics']['netWorthEffect'], 0.000001);
                self::assertEqualsWithDelta(-4350, $result['metrics']['equityNetGain'], 0.000001);
                self::assertEqualsWithDelta($principal, $result['yearlyRows'][array_key_last($result['yearlyRows'])]['remainingDebt'], 0.000001);
            }
        }
    }

    public function testAutomaticSpecialRepaymentDoesNotUseLoanSurplus(): void
    {
        foreach([0, 100] as $monthlyRent) {
            $scenario = $this->scenario();
            $scenario['settings']['autoSpecialRepaymentMode'] = 'positive_cashflow_after_tax';
            $scenario['rent']['apartmentMonthlyRent'] = $monthlyRent;
            $result = $this->calculate($scenario);
            $first = $result['yearlyRows'][0];
            self::assertEqualsWithDelta(10000, $first['financingSurplus'], 0.000001);
            self::assertEqualsWithDelta($monthlyRent * 12, $first['autoSpecialRepayment'], 0.000001);
            self::assertEqualsWithDelta(10000, $first['netCashflowAfterTax'], 0.000001);
            self::assertEqualsWithDelta(110000 - $monthlyRent * 12, $first['remainingDebt'], 0.000001);
        }
    }

    public function testAdditionalConstructionInterestStillReducesGainAfterTax(): void
    {
        $scenario = $this->scenario(120000);
        $scenario['tax']['marginalTaxRatePercent'] = 42;
        $baseline = $this->calculate($scenario);
        foreach([false, true] as $financed) {
            $scenario['constructionInterest']['yearlyEntries'] = [
                ['year' => 2027, 'amount' => 1000, 'deductible' => true, 'financed' => $financed],
            ];
            $result = $this->calculate($scenario);
            self::assertEqualsWithDelta($baseline['metrics']['netWorthEffect'] - 580, $result['metrics']['netWorthEffect'], 0.000001);
            self::assertEqualsWithDelta(420, array_sum(array_column($result['yearlyRows'], 'taxEffect')), 0.000001);
            self::assertEqualsWithDelta(101000, $result['summary']['totalCosts'], 0.000001);
        }
    }

    public function testFundingTimingCanChangeDiscountedValuesButNotNominalGain(): void
    {
        $scenario = $this->scenario();
        $scenario['settings']['discountRatePercent'] = 5;
        $scenario['constructionInterest']['yearlyEntries'] = [
            ['year' => 2027, 'amount' => 1000, 'deductible' => false, 'financed' => true],
        ];
        $financed = $this->calculate($scenario);
        $scenario['constructionInterest']['yearlyEntries'][0]['financed'] = false;
        $ownFunds = $this->calculate($scenario);
        self::assertEqualsWithDelta($financed['metrics']['netWorthEffect'], $ownFunds['metrics']['netWorthEffect'], 0.000001);
        self::assertEqualsWithDelta(1000 - 1000 / 1.05, $ownFunds['metrics']['npv'] - $financed['metrics']['npv'], 0.000001);
        self::assertGreaterThan($financed['metrics']['futureValueConservative'], $ownFunds['metrics']['futureValueConservative']);
    }

    private function calculate(array $scenario): array
    {
        return (new InvestmentCalculator())->calculate(RealEstateInvestmentScenario::fromArray($scenario))->toArray();
    }

    private function scenario(float $principal = 110000): array
    {
        return [
            'property' => ['apartmentPurchasePrice' => 100000, 'purchaseYear' => 2026, 'purchaseMonth' => 1,
                'completionYear' => 2026, 'completionMonth' => 1, 'rentStartYear' => 2026, 'rentStartMonth' => 1,
                'saleYear' => 2037, 'saleMonth' => 12],
            'acquisitionCosts' => ['realEstateTransferTaxPercent' => 0, 'notaryPercent' => 0,
                'landRegisterPurchasePercent' => 0, 'landRegisterLienPercent' => 0],
            'loans' => [['principal' => $principal, 'interestRatePercent' => 0, 'initialRepaymentPercent' => 0,
                'startYear' => 2026, 'startMonth' => 1]],
            'rent' => ['apartmentMonthlyRent' => 0],
            'depreciation' => ['degressiveActive' => false, 'linearRateOverrideEnabled' => true, 'linearRatePercent' => 0],
            'tax' => ['calculationMethod' => 'marginal_rate', 'taxableIncomeBeforeInvestment' => 80000, 'marginalTaxRatePercent' => 0],
            'sale' => ['annualValueIncreasePercent' => 0],
            'settings' => ['discountRatePercent' => 0, 'autoSpecialRepaymentMode' => 'none'],
        ];
    }
}
