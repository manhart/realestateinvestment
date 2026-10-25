<?php
declare(strict_types=1);

namespace realestateinvestment\tests;

use PHPUnit\Framework\TestCase;
use realestateinvestment\classes\Calculators\InvestmentCalculator;
use realestateinvestment\classes\Calculators\MetricScaleEvaluator;
use realestateinvestment\classes\Calculators\SpecialAfa7bRuleProvider;
use realestateinvestment\classes\Inputs\RealEstateInvestmentScenario;

final class TaxAssumptionEvaluatorTest extends TestCase
{
    private function scenario(): array
    {
        return [
            'property' => ['purchaseYear' => 2026, 'purchaseMonth' => 1, 'completionYear' => 2028, 'completionMonth' => 9,
                'rentStartYear' => 2028, 'rentStartMonth' => 9, 'saleYear' => 2040, 'saleMonth' => 12,
                'apartmentPurchasePrice' => 300000, 'livingArea' => 50],
            'depreciation' => ['startYear' => 2028, 'startMonth' => 9, 'special7bActive' => true,
                'special7bApplicationDate' => '2024-01-01', 'special7bActualConstructionCostPerSqm' => 5000],
            'sale' => ['taxFreeSale' => true],
        ];
    }

    private function calculate(array $scenario): array
    {
        return (new InvestmentCalculator())->calculate(RealEstateInvestmentScenario::fromArray($scenario))->toArray();
    }

    public function testUnknownFactsAreNotSilentlyCertifiedButActiveSwitchAllowsForecast(): void
    {
        $result = $this->calculate($this->scenario());
        self::assertGreaterThan(0, $result['yearlyRows'][2]['depreciation7b']);
        self::assertContains('unknown', array_column($result['taxReview']['checks'], 'status'));
        self::assertSame('assumptions', $result['taxReview']['status']);
    }

    public function testFailedUserPrerequisiteExcludesSpecialDepreciation(): void
    {
        foreach(['newDwelling', 'qng', 'timelyAcquisition', 'rentalUse', 'costAllocation'] as $key) {
            $scenario = $this->scenario();
            $scenario['depreciation']['taxEligibility'][$key] = 'failed';
            $result = $this->calculate($scenario);
            self::assertSame(0.0, $result['summary']['special7b']['assessmentBasis']);
            self::assertEqualsWithDelta(0, array_sum(array_column($result['yearlyRows'], 'depreciation7b')), 0.000001);
        }
    }

    public function testRuleRequiresRealDateOrExplicitPeriodSelection(): void
    {
        $scenario = $this->scenario();
        $scenario['depreciation']['special7bApplicationDate'] = '';
        self::assertSame(0.0, $this->calculate($scenario)['summary']['special7b']['assessmentBasis']);
        $scenario['depreciation']['special7bRulePeriod'] = 'new';
        self::assertGreaterThan(0, $this->calculate($scenario)['summary']['special7b']['assessmentBasis']);
        $scenario['depreciation']['special7bApplicationDate'] = '2024-02-31';
        self::assertSame(0.0, $this->calculate($scenario)['summary']['special7b']['assessmentBasis']);
    }

    public function testDateAndCostBoundariesCannotBeBypassedByManualLimits(): void
    {
        $provider = new SpecialAfa7bRuleProvider();
        foreach(['2018-09-01', '2021-12-31', '2023-01-01', '2029-09-30'] as $date) self::assertTrue($provider->ruleForDate($date)['matched']);
        foreach(['2018-08-31', '2022-01-01', '2022-12-31', '2029-10-01', '2025-02-29'] as $date) self::assertFalse($provider->ruleForDate($date)['matched']);
        foreach([['special7bActualConstructionCostPerSqm' => 5200.01], ['special7bLimitPerSqm' => 5000], ['special7bConstructionCostLimitPerSqm' => 6000], ['special7bRatePercent' => 6], ['special7bYears' => 5]] as $invalid) {
            $scenario = $this->scenario();
            $scenario['depreciation'] = array_replace($scenario['depreciation'], $invalid);
            self::assertSame(0.0, $this->calculate($scenario)['summary']['special7b']['assessmentBasis']);
        }
        $scenario = $this->scenario();
        $scenario['depreciation']['special7bActualConstructionCostPerSqm'] = 5200;
        self::assertGreaterThan(0, $this->calculate($scenario)['summary']['special7b']['assessmentBasis']);
    }

    public function testSavedCappedAmountIsNotReusedAsActualAcquisitionCosts(): void
    {
        $scenario = $this->scenario();
        $scenario['depreciation']['special7bBasis'] = 1;
        $result = $this->calculate($scenario);
        self::assertEqualsWithDelta($result['summary']['objectBuildingDepreciationBasis'], $result['summary']['special7b']['eligibleCosts'], 0.000001);
        self::assertEqualsWithDelta(200000, $result['summary']['special7b']['assessmentBasis'], 0.000001);
        $scenario['depreciation']['special7bEligibleCostsOverride'] = 100000;
        self::assertEqualsWithDelta(100000, $this->calculate($scenario)['summary']['special7b']['assessmentBasis'], 0.000001);
    }

    public function testTaxFreeSaleDoesNotMeanThe7bBindingPeriodHasEnded(): void
    {
        $scenario = $this->scenario();
        $scenario['property']['saleYear'] = 2037;
        $result = $this->calculate($scenario);
        self::assertSame('incomplete', $result['taxReview']['status']);
        self::assertSame(2037, $result['taxReview']['bindingEndYear']);
        self::assertSame((new MetricScaleEvaluator())->evaluateNominalLeverage($result['metrics']['leverageEfficiency']), $result['scales']['leverageEfficiency']);
        self::assertStringContainsString('Rückforderung', implode(' ', $result['taxReview']['notes']));
        $scenario['property']['saleYear'] = 2038;
        self::assertSame('assumptions', $this->calculate($scenario)['taxReview']['status']);
    }

    public function testUnmodelledSaleAndAcquisitionCostTaxAreExplicit(): void
    {
        foreach([['sale' => ['taxFreeSale' => false]], ['property' => ['saleYear' => 2036, 'saleMonth' => 1]], ['acquisitionCosts' => ['notaryLandRegisterTaxTreatment' => 'immediate_deductible']]] as $change) {
            $result = $this->calculate(array_replace_recursive($this->scenario(), $change));
            self::assertSame('incomplete', $result['taxReview']['status']);
        }
    }

    public function testTaxReviewDoesNotOverrideMetricRatings(): void
    {
        $scenario = $this->scenario();
        $baseline = $this->calculate($scenario);
        $scenario['sale']['taxFreeSale'] = false;
        $incomplete = $this->calculate($scenario);

        self::assertSame('assumptions', $baseline['taxReview']['status']);
        self::assertSame('incomplete', $incomplete['taxReview']['status']);
        self::assertSame($baseline['metrics'], $incomplete['metrics']);
        self::assertSame($baseline['scales'], $incomplete['scales']);
        self::assertNotEmpty($incomplete['taxReview']['warnings']);
    }

    public function testIneligibleDecliningBalanceUsesLinearAfaInstead(): void
    {
        $scenario = $this->scenario();
        $scenario['depreciation']['taxEligibility']['degressive'] = 'failed';
        $rows = $this->calculate($scenario)['yearlyRows'];
        self::assertSame(0.0, $rows[2]['depreciationDegressive']);
        self::assertGreaterThan(0, $rows[2]['depreciationLinear']);
    }

    public function testOldPeriodCannotStartAfter2026(): void
    {
        $scenario = $this->scenario();
        $scenario['depreciation']['special7bApplicationDate'] = '2020-01-01';
        $scenario['depreciation']['special7bActualConstructionCostPerSqm'] = 2500;
        self::assertSame(0.0, $this->calculate($scenario)['summary']['special7b']['assessmentBasis']);
    }
}
