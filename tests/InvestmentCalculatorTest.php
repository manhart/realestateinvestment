<?php
declare(strict_types=1);

namespace realestateinvestment\tests;

use PHPUnit\Framework\TestCase;
use realestateinvestment\classes\Calculators\InvestmentCalculator;
use realestateinvestment\classes\Inputs\RealEstateInvestmentScenario;
use realestateinvestment\classes\Support\InvestagonScenarioTransformer;
use realestateinvestment\classes\Support\RealEstatePilotScenarioTransformer;

final class InvestmentCalculatorTest extends TestCase
{
    public function testCalculatesCoreMetricsAndYearlyRows(): void
    {
        $result = $this->calculate($this->baseScenario())->toArray();

        self::assertCount(11, $result['yearlyRows']);
        self::assertGreaterThan(300000, $result['summary']['totalCosts']);
        self::assertSame(310000.0, $result['summary']['initialDebt']);
        self::assertArrayHasKey('netWorthEffect', $result['metrics']);
        self::assertArrayHasKey('npv', $result['metrics']);
        self::assertArrayHasKey('futureValueConservative', $result['metrics']);
        self::assertArrayHasKey('futureValueLiquidity', $result['metrics']);
        self::assertGreaterThan($result['metrics']['futureValueConservative'], $result['metrics']['futureValueLiquidity']);
        self::assertSame(6, $result['yearlyRows'][0]['rentedMonths']);
        self::assertSame(12, $result['yearlyRows'][1]['rentedMonths']);
        self::assertGreaterThan(0, $result['yearlyRows'][1]['interest']);
        self::assertGreaterThan(0, $result['yearlyRows'][1]['depreciationTotal']);
        self::assertGreaterThan(0, $result['yearlyRows'][10]['salePrice']);
        self::assertGreaterThan(0, $result['yearlyRows'][10]['saleProceedsAfterDebt']);
    }

    public function testMarginalTaxModeCreatesTaxReliefForVuvLoss(): void
    {
        $scenario = $this->baseScenario();
        $scenario['rent']['apartmentMonthlyRent'] = 500;
        $result = $this->calculate($scenario)->toArray();

        $firstFullYear = $result['yearlyRows'][1];
        self::assertLessThan(0, $firstFullYear['rentalTaxableIncome']);
        self::assertGreaterThan(0, $firstFullYear['taxEffect']);
        self::assertGreaterThan($firstFullYear['netCashflowBeforeTax'], $firstFullYear['netCashflowAfterTax']);
        self::assertEqualsWithDelta(0.42, $firstFullYear['effectiveTaxRate'], 0.0001);
    }

    public function testSection32aRowsExposeSoliChurchTaxAndEffectiveTaxRate(): void
    {
        $scenario = $this->baseScenario();
        $scenario['tax'] = [
            'calculationMethod' => 'section_32a',
            'assessmentType' => 'single',
            'taxYear' => 2026,
            'taxableIncomeBeforeInvestment' => 94439,
            'churchTax' => true,
            'churchTaxState' => 'NW',
            'marginalTaxRatePercent' => 42,
        ];
        $result = $this->calculate($scenario)->toArray();
        $firstYear = $result['yearlyRows'][0];

        self::assertEqualsWithDelta(28528, $firstYear['tariffIncomeTaxBefore'], 0.01);
        self::assertEqualsWithDelta(973.18, $firstYear['solidaritySurchargeBefore'], 0.01);
        self::assertEqualsWithDelta(2567.52, $firstYear['churchTaxBefore'], 0.01);
        self::assertEqualsWithDelta(32068.70, $firstYear['incomeTaxBefore'], 0.01);
        self::assertGreaterThan(0, $firstYear['effectiveTaxRate']);
    }

    public function testCalculationBreakdownExplainsSaleProceedsAndNetWorthEffect(): void
    {
        $result = $this->calculate($this->baseScenario())->toArray();
        $breakdown = array_column($result['calculationBreakdown'], null, 'label');
        $saleRow = $result['yearlyRows'][array_key_last($result['yearlyRows'])];
        $cashflowAfterTaxTotal = array_sum(array_column($result['yearlyRows'], 'netCashflowAfterTax'));
        $cashflowIncludingSaleTotal = array_sum(array_column($result['yearlyRows'], 'netCashflowIncludingSale'));
        $sellingCosts = $saleRow['salePrice'] * 0.01;

        self::assertArrayHasKey('Verkaufspreis 2036', $breakdown);
        self::assertArrayHasKey('Verkaufskosten 2036', $breakdown);
        self::assertArrayHasKey('Erlös nach Schuld Verkaufsjahr', $breakdown);
        self::assertArrayHasKey('Netto-Vermögenseffekt', $breakdown);
        self::assertEqualsWithDelta($saleRow['salePrice'], $breakdown['Verkaufspreis 2036']['result'], 0.01);
        self::assertEqualsWithDelta($sellingCosts, $breakdown['Verkaufskosten 2036']['result'], 0.01);
        self::assertEqualsWithDelta($saleRow['salePrice'] - $saleRow['remainingDebt'] - $sellingCosts, $breakdown['Erlös nach Schuld Verkaufsjahr']['result'], 0.01);
        self::assertEqualsWithDelta($cashflowAfterTaxTotal + $saleRow['saleProceedsAfterDebt'], $breakdown['Netto-Vermögenseffekt']['result'], 0.01);
        self::assertEqualsWithDelta($cashflowIncludingSaleTotal, $breakdown['Kontrollsumme CF inkl. Verkauf']['result'], 0.01);
        self::assertEqualsWithDelta($result['metrics']['netWorthEffect'], $breakdown['Netto-Vermögenseffekt']['result'], 0.01);
        self::assertSame('calc-netto-vermoegenseffekt', $breakdown['Netto-Vermögenseffekt']['key']);
        self::assertNotSame('', $breakdown['Netto-Vermögenseffekt']['description']);
        self::assertSame('calc-npv', $breakdown['NPV']['key']);
        self::assertSame($result['metrics']['npv'], $breakdown['NPV']['result']);
        self::assertSame('calc-fv-konservativ', $breakdown['FV konservativ']['key']);
        self::assertSame($result['metrics']['futureValueConservative'], $breakdown['FV konservativ']['result']);
        self::assertSame('calc-fv-liquiditaetsorientiert', $breakdown['FV liquiditätsorientiert']['key']);
        self::assertSame($result['metrics']['futureValueLiquidity'], $breakdown['FV liquiditätsorientiert']['result']);
    }

    public function testEquityMetricsUseInitialEquityAndNegativeCashflowsWithoutChangingDebt(): void
    {
        $scenario = $this->baseScenario();
        $scenario['settings']['initialEquityAmount'] = 50000;
        $result = $this->calculate($scenario)->toArray();
        $negativeCashflows = array_sum(array_map(static fn(array $row): float => min($row['netCashflowAfterTax'], 0), $result['yearlyRows']));
        $positiveCashflows = array_sum(array_map(static fn(array $row): float => max($row['netCashflowAfterTax'], 0), $result['yearlyRows']));
        $saleProceeds = array_sum(array_column($result['yearlyRows'], 'saleProceedsAfterDebt'));
        $expectedCapitalCalls = -$negativeCashflows;
        $expectedDistributions = $positiveCashflows + $saleProceeds;
        $expectedTotalEquity = 50000 + $expectedCapitalCalls;

        self::assertSame(310000.0, $result['summary']['initialDebt']);
        self::assertEqualsWithDelta(50000, $result['summary']['initialEquityAmount'], 0.01);
        self::assertEqualsWithDelta($expectedCapitalCalls, $result['summary']['equityCapitalCalls'], 0.01);
        self::assertEqualsWithDelta($expectedTotalEquity, $result['summary']['totalEquityInvested'], 0.01);
        self::assertEqualsWithDelta($expectedDistributions, $result['summary']['equityDistributions'], 0.01);
        self::assertEqualsWithDelta($expectedDistributions - $expectedTotalEquity, $result['summary']['equityNetGain'], 0.01);
        self::assertEqualsWithDelta($result['summary']['equityDistributions'] / $result['summary']['totalEquityInvested'], $result['summary']['equityMultiple'], 0.0001);
        self::assertEqualsWithDelta($result['summary']['equityNetGain'] / $result['summary']['totalEquityInvested'], $result['summary']['equityTotalReturn'], 0.0001);

        $breakdown = array_column($result['calculationBreakdown'], null, 'label');
        self::assertEqualsWithDelta($result['summary']['totalEquityInvested'], $breakdown['eingesetztes Eigenkapital']['result'], 0.01);
        self::assertEqualsWithDelta($result['summary']['equityNetGain'], $breakdown['Netto-Vermögensgewinn nach EK']['result'], 0.01);
    }

    public function testOpportunityInterestExplainsFutureValueEffect(): void
    {
        $scenario = $this->baseScenario();
        $scenario['settings']['discountRatePercent'] = 5;
        $result = $this->calculate($scenario)->toArray();
        $rows = $result['yearlyRows'];
        $lastIndex = count($rows) - 1;
        $firstExpected = $rows[0]['netCashflowIncludingSale'] * ((1.05 ** $lastIndex) - 1);
        $sumOpportunityInterest = array_sum(array_column($rows, 'opportunityInterest'));

        self::assertEqualsWithDelta($firstExpected, $rows[0]['opportunityInterest'], 0.01);
        self::assertEqualsWithDelta(0, $rows[$lastIndex]['opportunityInterest'], 0.01);
        self::assertEqualsWithDelta($result['metrics']['futureValueConservative'] - $result['metrics']['netWorthEffect'], $sumOpportunityInterest, 0.01);

        $scenario['settings']['discountRatePercent'] = 0;
        $zeroRate = $this->calculate($scenario)->toArray();
        self::assertEqualsWithDelta(0, array_sum(array_column($zeroRate['yearlyRows'], 'opportunityInterest')), 0.01);
    }

    public function testAutoSpecialRepaymentUsesPositiveCashflowForHighestInterestLoan(): void
    {
        $scenario = $this->baseScenario();
        $scenario['rent']['apartmentMonthlyRent'] = 10000;
        $scenario['loans'][] = [
            'name' => 'Teures Darlehen',
            'principal' => 50000,
            'interestRatePercent' => 8,
            'initialRepaymentPercent' => 1,
            'startYear' => 2026,
            'startMonth' => 1,
            'fixedInterestYears' => 10,
            'followUpInterestRatePercent' => 8,
            'constantAnnuity' => true,
            'redeemOnSale' => true,
        ];
        $withoutAuto = $this->calculate($scenario)->toArray();

        $scenario['settings']['autoSpecialRepaymentMode'] = 'positive_cashflow_after_tax';
        $withAuto = $this->calculate($scenario)->toArray();
        $firstYear = $withAuto['yearlyRows'][0];
        $lastIndex = array_key_last($withAuto['yearlyRows']);

        self::assertGreaterThan(0, $firstYear['autoSpecialRepayment']);
        self::assertSame('Teures Darlehen', $firstYear['autoSpecialRepaymentTarget']);
        self::assertEqualsWithDelta($firstYear['netCashflowAfterTaxBeforeAutoSpecialRepayment'] - $firstYear['autoSpecialRepayment'], $firstYear['netCashflowAfterTax'], 0.01);
        self::assertLessThan($withoutAuto['yearlyRows'][$lastIndex]['remainingDebt'], $withAuto['yearlyRows'][$lastIndex]['remainingDebt']);

        $breakdown = array_column($withAuto['calculationBreakdown'], null, 'label');
        self::assertArrayHasKey('Auto-Sondertilgung', $breakdown);
        self::assertSame('calc-auto-sondertilgung', $breakdown['Auto-Sondertilgung']['key']);
    }

    public function testAutoSpecialRepaymentCanUseOpportunityInterestOnly(): void
    {
        $scenario = $this->baseScenario();
        $scenario['rent']['apartmentMonthlyRent'] = 3000;
        $scenario['settings']['discountRatePercent'] = 5;
        $scenario['settings']['autoSpecialRepaymentMode'] = 'positive_opportunity_interest';
        $result = $this->calculate($scenario)->toArray();
        $lastIndex = count($result['yearlyRows']) - 1;
        $rowIndex = array_find_key(
            $result['yearlyRows'],
            static fn(array $row): bool => $row['netCashflowAfterTaxBeforeAutoSpecialRepayment'] > 0,
        );

        self::assertIsInt($rowIndex);

        $firstPositiveYear = $result['yearlyRows'][$rowIndex];
        $expected = $firstPositiveYear['netCashflowAfterTaxBeforeAutoSpecialRepayment'] * ((1.05 ** ($lastIndex - $rowIndex)) - 1);

        self::assertEqualsWithDelta($expected, $firstPositiveYear['autoSpecialRepayment'], 0.01);
        self::assertLessThan($firstPositiveYear['netCashflowAfterTaxBeforeAutoSpecialRepayment'], $firstPositiveYear['netCashflowAfterTax']);
    }

    public function testTaxableIncomeBeforeInvestmentCanIncreaseAnnually(): void
    {
        $scenario = $this->baseScenario();
        $scenario['tax'] = [
            'calculationMethod' => 'section_32a',
            'assessmentType' => 'splitting',
            'taxYear' => 2026,
            'taxableIncomeBeforeInvestment' => 94439,
            'taxableIncomeAnnualIncreasePercent' => 1.5,
            'churchTax' => false,
            'marginalTaxRatePercent' => 42,
        ];
        $rows = $this->calculate($scenario)->toArray()['yearlyRows'];

        self::assertEqualsWithDelta(94439.00, $rows[0]['taxableIncomeBefore'], 0.01);
        self::assertEqualsWithDelta(95855.58, $rows[1]['taxableIncomeBefore'], 0.01);
        self::assertEqualsWithDelta(97293.42, $rows[2]['taxableIncomeBefore'], 0.01);
        self::assertEqualsWithDelta(109600.31, $rows[10]['taxableIncomeBefore'], 0.01);
    }

    public function testFinancingCostsAreDeductibleInPurchaseYearWhenEnabled(): void
    {
        $scenario = $this->baseScenario();
        $scenario['acquisitionCosts']['landRegisterLienPercent'] = 1;
        $scenario['acquisitionCosts']['otherFinancingCosts'] = 500;
        $scenario['acquisitionCosts']['financingCostsDeductible'] = true;
        $deductible = $this->calculate($scenario)->toArray();

        $scenario['acquisitionCosts']['financingCostsDeductible'] = false;
        $notDeductible = $this->calculate($scenario)->toArray();

        $expectedFinancingCosts = 310000 * 0.01 + 500;
        self::assertEqualsWithDelta($expectedFinancingCosts, $deductible['yearlyRows'][0]['deductibleExpenses'], 0.01);
        self::assertEqualsWithDelta(0, $notDeductible['yearlyRows'][0]['deductibleExpenses'], 0.01);
        self::assertEqualsWithDelta($deductible['yearlyRows'][0]['deductibleOperatingExpenses'] + $deductible['yearlyRows'][0]['interest'] + $expectedFinancingCosts, $deductible['yearlyRows'][0]['advertisingCostsWithoutDepreciation'], 0.01);
        self::assertGreaterThan($notDeductible['yearlyRows'][0]['taxEffect'], $deductible['yearlyRows'][0]['taxEffect']);
    }

    public function testStringFalseDisablesFinancingCostDeduction(): void
    {
        $scenario = $this->baseScenario();
        $scenario['acquisitionCosts']['landRegisterLienPercent'] = 1;
        $scenario['acquisitionCosts']['otherFinancingCosts'] = 500;
        $scenario['acquisitionCosts']['financingCostsDeductible'] = 'false';
        $result = $this->calculate($scenario)->toArray();

        self::assertEqualsWithDelta(0, $result['yearlyRows'][0]['deductibleExpenses'], 0.01);
    }

    public function testDetailedConstructionInterestRowsDriveTaxAndCashflow(): void
    {
        $baseline = $this->baseScenario();
        $baseline['acquisitionCosts']['financingCostsDeductible'] = false;
        $baselineResult = $this->calculate($baseline)->toArray();

        $scenario = $baseline;
        $scenario['constructionInterest']['yearlyEntries'] = [
            ['label' => 'Bauzeitzinsen', 'year' => 2026, 'amount' => 1186.81, 'deductible' => true, 'financed' => false],
            ['label' => 'Bauzeitzinsen', 'year' => 2027, 'amount' => 4596.61, 'deductible' => true, 'financed' => true],
            ['label' => 'Bauzeitzinsen', 'year' => 2028, 'amount' => 4756.86, 'deductible' => false, 'financed' => false],
        ];
        $result = $this->calculate($scenario)->toArray();

        self::assertEqualsWithDelta(1186.81, $result['yearlyRows'][0]['constructionInterest'], 0.01);
        self::assertEqualsWithDelta(4596.61, $result['yearlyRows'][1]['constructionInterest'], 0.01);
        self::assertEqualsWithDelta(4756.86, $result['yearlyRows'][2]['constructionInterest'], 0.01);
        self::assertEqualsWithDelta(1186.81, $result['yearlyRows'][0]['deductibleExpenses'], 0.01);
        self::assertEqualsWithDelta(4596.61, $result['yearlyRows'][1]['deductibleExpenses'], 0.01);
        self::assertEqualsWithDelta(0, $result['yearlyRows'][2]['deductibleExpenses'], 0.01);
        self::assertEqualsWithDelta($baselineResult['yearlyRows'][0]['netCashflowBeforeTax'] - 1186.81, $result['yearlyRows'][0]['netCashflowBeforeTax'], 0.01);
        self::assertEqualsWithDelta($baselineResult['yearlyRows'][1]['netCashflowBeforeTax'], $result['yearlyRows'][1]['netCashflowBeforeTax'], 0.01);
        self::assertEqualsWithDelta($baselineResult['yearlyRows'][2]['netCashflowBeforeTax'] - 4756.86, $result['yearlyRows'][2]['netCashflowBeforeTax'], 0.01);
        self::assertEqualsWithDelta($baselineResult['summary']['totalCosts'] + 10540.28, $result['summary']['totalCosts'], 0.01);
        self::assertEqualsWithDelta(10540.28, $result['summary']['constructionInterestTotal'], 0.01);
        self::assertEqualsWithDelta(5943.67, $result['summary']['constructionInterestCashTotal'], 0.01);
        self::assertEqualsWithDelta(4596.61, $result['summary']['constructionInterestFinancedTotal'], 0.01);
        self::assertEqualsWithDelta($result['summary']['financingCosts'] + 10540.28, $result['summary']['oneTimeFinancingCostsTotal'], 0.01);
        self::assertEqualsWithDelta($result['summary']['totalCosts'], $result['summary']['totalInvestmentCost'], 0.01);
    }

    public function testFinancedConstructionInterestDoesNotCreateCashOutflow(): void
    {
        $baseline = $this->baseScenario();
        $baseline['acquisitionCosts']['financingCostsDeductible'] = false;
        $baselineResult = $this->calculate($baseline)->toArray();

        $scenario = $baseline;
        $scenario['constructionInterest']['yearlyEntries'] = [
            ['label' => 'Bauzeitzinsen', 'year' => 2026, 'amount' => 1186.81, 'deductible' => true, 'financed' => true],
            ['label' => 'Bauzeitzinsen', 'year' => 2027, 'amount' => 4596.61, 'deductible' => true, 'financed' => true],
        ];
        $result = $this->calculate($scenario)->toArray();

        self::assertEqualsWithDelta($baselineResult['yearlyRows'][0]['netCashflowBeforeTax'], $result['yearlyRows'][0]['netCashflowBeforeTax'], 0.01);
        self::assertEqualsWithDelta($baselineResult['yearlyRows'][1]['netCashflowBeforeTax'], $result['yearlyRows'][1]['netCashflowBeforeTax'], 0.01);
        self::assertEqualsWithDelta(5783.42, $result['summary']['constructionInterestTotal'], 0.01);
        self::assertEqualsWithDelta(0, $result['summary']['constructionInterestCashTotal'], 0.01);
        self::assertEqualsWithDelta(5783.42, $result['summary']['constructionInterestFinancedTotal'], 0.01);
    }

    public function testTaxDeductibleOperatingExpensesExcludeReserves(): void
    {
        $scenario = $this->baseScenario();
        $scenario['expenses']['nonRecoverableHausgeldMonthly'] = 180;
        $scenario['expenses']['maintenanceReserveMonthly'] = 80;
        $scenario['expenses']['managementMonthly'] = 35;
        $scenario['expenses']['servicePoolMonthly'] = 20;
        $scenario['expenses']['furnitureReserveMonthly'] = 15;
        $scenario['expenses']['otherMonthlyCosts'] = 10;
        $result = $this->calculate($scenario)->toArray();
        $firstFullYear = $result['yearlyRows'][1];

        self::assertEqualsWithDelta(4080, $firstFullYear['expenses'], 0.01);
        self::assertEqualsWithDelta(2940, $firstFullYear['deductibleOperatingExpenses'], 0.01);
        self::assertEqualsWithDelta($firstFullYear['deductibleOperatingExpenses'] + $firstFullYear['interest'] + $firstFullYear['deductibleExpenses'], $firstFullYear['advertisingCostsWithoutDepreciation'], 0.01);
        self::assertEqualsWithDelta($firstFullYear['advertisingCostsWithoutDepreciation'] + $firstFullYear['depreciationTotal'], $firstFullYear['taxDeductionsTotal'], 0.01);
        self::assertEqualsWithDelta($firstFullYear['rent'] - $firstFullYear['taxDeductionsTotal'], $firstFullYear['rentalTaxableIncome'], 0.01);
    }

    public function testValidationWarningsForWeakFinancingAndMetrics(): void
    {
        $scenario = $this->baseScenario();
        $scenario['loans'][0]['principal'] = 400000;
        $scenario['rent']['apartmentMonthlyRent'] = 300;
        $result = $this->calculate($scenario)->toArray();

        $messages = implode("\n", array_column($result['warnings'], 'message'));
        self::assertStringContainsString('>100 %-Finanzierung', $messages);
        self::assertStringContainsString('DSCR', $messages);
        self::assertStringContainsString('Debt Yield', $messages);
    }

    public function testParkingUnitAffectsPurchasePriceRentAndDepreciationBasis(): void
    {
        $withoutParking = $this->baseScenario();
        $withoutParking['parkingUnits'] = [];
        $withParking = $this->baseScenario();

        $without = $this->calculate($withoutParking)->toArray();
        $with = $this->calculate($withParking)->toArray();

        self::assertGreaterThan($without['summary']['purchasePrice'], $with['summary']['purchasePrice']);
        self::assertGreaterThan($without['yearlyRows'][1]['rent'], $with['yearlyRows'][1]['rent']);
        self::assertGreaterThan($without['yearlyRows'][1]['depreciationTotal'], $with['yearlyRows'][1]['depreciationTotal']);
    }

    public function testParkingDepreciationDefaultsToLinearBuildingRate(): void
    {
        $result = $this->calculate($this->baseScenario())->toArray();

        self::assertEqualsWithDelta(16880, $result['summary']['parkingDepreciationBasis'], 0.01);
        self::assertEqualsWithDelta(0.03, $result['summary']['parkingDepreciationRate'], 0.0001);
        self::assertFalse($result['summary']['parkingDepreciationMixedRates']);
        self::assertEqualsWithDelta(506.40, $result['yearlyRows'][1]['depreciationParking'], 0.01);
    }

    public function testParkingDepreciationCanUseCustomRatePerParkingUnit(): void
    {
        $scenario = $this->baseScenario();
        $scenario['parkingUnits'][0]['depreciationMode'] = 'custom';
        $scenario['parkingUnits'][0]['depreciationRatePercent'] = 5.26;

        $result = $this->calculate($scenario)->toArray();

        self::assertEqualsWithDelta(0.0526, $result['summary']['parkingDepreciationRate'], 0.0001);
        self::assertEqualsWithDelta(887.89, $result['yearlyRows'][1]['depreciationParking'], 0.01);
    }

    public function testParkingDepreciationCanBeMixedOrDisabled(): void
    {
        $scenario = $this->baseScenario();
        $scenario['parkingUnits'][] = [
            'label' => 'Außenstellplatz',
            'purchasePrice' => 10000,
            'monthlyRent' => 40,
            'buildingSharePercent' => 100,
            'landSharePercent' => 0,
            'depreciable' => true,
            'depreciationMode' => 'custom',
            'depreciationRatePercent' => 5.26,
            'includedInPurchasePrice' => true,
        ];

        $mixed = $this->calculate($scenario)->toArray();
        self::assertTrue($mixed['summary']['parkingDepreciationMixedRates']);
        self::assertEqualsWithDelta(1061.33, $mixed['yearlyRows'][1]['depreciationParking'], 0.01);

        $scenario['parkingUnits'][0]['depreciable'] = false;
        $scenario['parkingUnits'][1]['buildingSharePercent'] = 0;
        $scenario['parkingUnits'][1]['landSharePercent'] = 100;
        $disabled = $this->calculate($scenario)->toArray();
        self::assertEqualsWithDelta(0, $disabled['summary']['parkingDepreciationBasis'], 0.01);
        self::assertEqualsWithDelta(0, $disabled['yearlyRows'][1]['depreciationParking'], 0.01);
    }

    public function testCostAllocationSeparatesLandBuildingAndFurniture(): void
    {
        $scenario = $this->baseScenario();
        $scenario['property']['otherPurchasePrice'] = 10000;
        $scenario['property']['furniturePurchasePrice'] = 15000;
        $scenario['property']['furnished'] = true;
        $result = $this->calculate($scenario)->toArray();
        $allocation = $result['summary']['costAllocation'];

        self::assertEqualsWithDelta(345000, $result['summary']['purchasePrice'], 0.01);
        self::assertEqualsWithDelta(66000, $allocation['landCosts'], 0.01);
        self::assertEqualsWithDelta(264000, $allocation['buildingCosts'], 0.01);
        self::assertEqualsWithDelta(15000, $allocation['furnitureCosts'], 0.01);
        self::assertEqualsWithDelta(15000, $result['summary']['furnitureDepreciationBasis'], 0.01);
        self::assertEqualsWithDelta(875, $result['yearlyRows'][0]['depreciationFurniture'], 0.01);
        self::assertGreaterThan($allocation['propertyBuildingCosts'], $result['summary']['buildingDepreciationBasis']);
        self::assertLessThan($allocation['buildingCosts'], $result['summary']['buildingDepreciationBasis']);
    }

    public function testFurnitureIsExcludedFromPercentageAcquisitionCostBase(): void
    {
        $scenario = $this->baseScenario();
        $scenario['parkingUnits'] = [];
        $scenario['property']['apartmentPurchasePrice'] = 300000;
        $scenario['property']['otherPurchasePrice'] = 0;
        $scenario['property']['furniturePurchasePrice'] = 15000;
        $scenario['property']['furnished'] = true;
        $scenario['property']['landSharePercent'] = 20;
        $scenario['property']['buildingSharePercent'] = 80;
        $scenario['acquisitionCosts']['realEstateTransferTaxPercent'] = 5.5;
        $scenario['acquisitionCosts']['notaryPercent'] = 1.5;
        $scenario['acquisitionCosts']['landRegisterPurchasePercent'] = 0.5;
        $scenario['acquisitionCosts']['brokerPercent'] = 2.0;

        $result = $this->calculate($scenario)->toArray();
        $breakdown = $result['summary']['acquisitionBreakdown'];

        self::assertEqualsWithDelta(315000, $result['summary']['purchasePrice'], 0.01);
        self::assertEqualsWithDelta(300000, $breakdown['realEstatePurchasePrice'], 0.01);
        self::assertEqualsWithDelta(16500, $breakdown['realEstateTransferTax'], 0.01);
        self::assertEqualsWithDelta(4500, $breakdown['notary'], 0.01);
        self::assertEqualsWithDelta(1500, $breakdown['landRegisterPurchase'], 0.01);
        self::assertEqualsWithDelta(6000, $breakdown['broker'], 0.01);
        self::assertEqualsWithDelta(262800, $result['summary']['buildingDepreciationBasis'], 0.01);
        self::assertEqualsWithDelta(15000, $result['summary']['furnitureDepreciationBasis'], 0.01);
    }

    public function testAcquisitionBreakdownContainsIndividualCosts(): void
    {
        $scenario = $this->baseScenario();
        $scenario['acquisitionCosts']['brokerPercent'] = 2;
        $scenario['acquisitionCosts']['otherAcquisitionCosts'] = 1000;
        $scenario['acquisitionCosts']['otherFinancingCosts'] = 500;
        $result = $this->calculate($scenario)->toArray();
        $breakdown = $result['summary']['acquisitionBreakdown'];

        self::assertEqualsWithDelta(11200, $breakdown['realEstateTransferTax'], 0.01);
        self::assertEqualsWithDelta(4800, $breakdown['notary'], 0.01);
        self::assertEqualsWithDelta(1600, $breakdown['landRegisterPurchase'], 0.01);
        self::assertEqualsWithDelta(6400, $breakdown['broker'], 0.01);
        self::assertEqualsWithDelta(1550, $breakdown['landRegisterLien'], 0.01);
        self::assertEqualsWithDelta(25000, $breakdown['acquisitionCostsWithoutFinancing'], 0.01);
        self::assertEqualsWithDelta(2050, $breakdown['financingCosts'], 0.01);
        self::assertEqualsWithDelta(27050, $breakdown['totalAcquisitionAndFinancingCosts'], 0.01);
    }

    public function testNotaryAndLandRegisterCanBeImmediateDeductibleAcquisitionCosts(): void
    {
        $scenario = $this->baseScenario();
        $scenario['parkingUnits'] = [];
        $scenario['acquisitionCosts']['notaryPercent'] = 1.3;
        $scenario['acquisitionCosts']['landRegisterPurchasePercent'] = 0.7;
        $scenario['acquisitionCosts']['financingCostsDeductible'] = false;

        $basis = $this->calculate($scenario)->toArray();

        $scenario['acquisitionCosts']['notaryLandRegisterTaxTreatment'] = 'immediate_deductible';
        $deductible = $this->calculate($scenario)->toArray();

        self::assertEqualsWithDelta(6000, $deductible['summary']['acquisitionBreakdown']['immediateDeductibleAcquisitionCosts'], 0.01);
        self::assertEqualsWithDelta(10500, $deductible['summary']['acquisitionBreakdown']['depreciationRelevantAcquisitionCostsWithoutFinancing'], 0.01);
        self::assertEqualsWithDelta(6000, $deductible['yearlyRows'][0]['deductibleExpenses'], 0.01);
        self::assertLessThan($basis['summary']['buildingDepreciationBasis'], $deductible['summary']['buildingDepreciationBasis']);
        self::assertGreaterThan($basis['yearlyRows'][0]['taxEffect'], $deductible['yearlyRows'][0]['taxEffect']);
    }

    public function testMarkkleebergReferenceCaseMatchesCoreGoldenMasterRows(): void
    {
        $reference = json_decode((string)file_get_contents(DIR_DATA_ROOT.'/realestateinvestment/_examples/real_estate_pilot_markkleeberg_we_2_02_extracted.json'), true, 512, JSON_THROW_ON_ERROR);
        $result = $this->calculate($this->markkleebergScenario())->toArray();
        $rows = array_column($result['yearlyRows'], null, 'year');
        $taxRows = array_column($reference['expected_results']['tax_savings_vv_income_table'], null, 'year');
        $effects = array_column($reference['expected_results']['tax_effects_table'], null, 'year');
        $overall = array_column($reference['expected_results']['overall_summary_table'], null, 'year');

        foreach([2026, 2027, 2028] as $year) {
            self::assertEqualsWithDelta($taxRows[$year]['rental_income'], $rows[$year]['rent'], 0.01, 'Miete '.$year);
            self::assertEqualsWithDelta($taxRows[$year]['deductible_running_costs'], $rows[$year]['deductibleOperatingExpenses'], 0.01, 'abzugsfähige laufende Kosten '.$year);
            self::assertEqualsWithDelta($taxRows[$year]['interest'], $rows[$year]['interest'], 0.01, 'Zinsen '.$year);
            self::assertEqualsWithDelta($taxRows[$year]['depreciation_total'], $rows[$year]['depreciationTotal'], 0.01, 'AfA gesamt '.$year);
            self::assertEqualsWithDelta($taxRows[$year]['income_from_rent_and_lease'], $rows[$year]['rentalTaxableIncome'], 0.01, 'Einkünfte VuV '.$year);
            self::assertEqualsWithDelta($effects[$year]['tax_saving'], $rows[$year]['taxEffect'], 1.00, 'Steuerwirkung '.$year);
            self::assertEqualsWithDelta($overall[$year]['residual_debt'], $rows[$year]['remainingDebt'], 0.01, 'Restschuld '.$year);
        }

        self::assertEqualsWithDelta(7352.50, $rows[2026]['deductibleExpenses'], 0.01);
        self::assertEqualsWithDelta(367.40, $rows[2026]['depreciationParking'], 0.01);
        self::assertEqualsWithDelta(8704.00, $rows[2027]['depreciation7b'], 0.01);
        self::assertEqualsWithDelta(4356.61, $rows[2027]['depreciationDegressive'], 0.01);
        self::assertEqualsWithDelta(629.84, $rows[2027]['depreciationParking'], 0.01);
        self::assertEqualsWithDelta(2164.22, $rows[2028]['repayment'], 0.01);
        self::assertEqualsWithDelta(973.08, $rows[2028]['expenses'], 0.01);
    }

    public function testInvestagonStraubingReferenceCaseMapsCoreInputsAndRows(): void
    {
        $reference = $this->investagonReference();
        $scenario = $this->investagonStraubingScenario();
        $result = $this->calculate($scenario)->toArray();
        $rows = array_column($result['yearlyRows'], null, 'year');
        $annualRows = array_column($reference['annual_overview_table'], null, 'year');

        self::assertSame('Wohnung 01 MFH Haus B (Default)', $scenario['scenarioName']);
        self::assertSame('Straubing: Schlesische Strasse 107', $scenario['scenarioGroup']);
        self::assertSame('BY', $scenario['property']['state']);
        self::assertCount(2, $scenario['parkingUnits']);
        self::assertEqualsWithDelta(17900, $scenario['parkingUnits'][0]['purchasePrice'], 0.01);
        self::assertEqualsWithDelta(60, $scenario['parkingUnits'][0]['monthlyRent'], 0.01);
        self::assertFalse($scenario['parkingUnits'][0]['depreciable']);
        self::assertEqualsWithDelta(257702.55, $result['summary']['buildingDepreciationBasis'], 0.01);
        self::assertEqualsWithDelta(167960, $result['summary']['special7b']['assessmentBasis'], 0.01);

        foreach([2026, 2027, 2036] as $year) {
            self::assertEqualsWithDelta($annualRows[$year]['income'], $rows[$year]['rent'], 1.00, 'Miete '.$year);
            self::assertEqualsWithDelta(abs($annualRows[$year]['expenses']), $rows[$year]['expenses'], 1.00, 'Ausgaben '.$year);
            self::assertEqualsWithDelta(abs($annualRows[$year]['annuity']), $rows[$year]['annuity'], 1.00, 'Annuität '.$year);
        }

        self::assertSame(8, $rows[2026]['rentedMonths']);
        self::assertSame(4, $rows[2036]['rentedMonths']);
        self::assertEqualsWithDelta(8398, $rows[2026]['depreciation7b'], 0.01);
        self::assertEqualsWithDelta(19160, $rows[2026]['tariffIncomeTaxBefore'], 0.01);
        self::assertGreaterThan(500, abs($reference['detailed_calculation_table']['asset_development']['loan_amount_year_end'][10] - $rows[2036]['remainingDebt']));
    }

    public function testScaleLegendIsReturnedFromCalculationResult(): void
    {
        $result = $this->calculate($this->baseScenario())->toArray();
        $legend = $result['scaleLegend'];
        $groups = array_column($legend, 'title', 'key');

        self::assertArrayHasKey('nominalLeverage', $groups);
        self::assertArrayHasKey('npvLeverage', $groups);
        self::assertArrayHasKey('debtYield', $groups);
        self::assertArrayHasKey('dscr', $groups);

        $nominalItems = $legend[array_search('nominalLeverage', array_column($legend, 'key'), true)]['items'];
        $npvItems = $legend[array_search('npvLeverage', array_column($legend, 'key'), true)]['items'];

        self::assertSame('stark', $nominalItems[4]['label']);
        self::assertSame('25-30 %', $nominalItems[4]['range']);
        self::assertSame('schlechter als Alternativanlage', $npvItems[0]['label']);
        self::assertSame('unter 0 %', $npvItems[0]['range']);
    }

    public function testSpecial7bUsesCurrentSqmCap(): void
    {
        $scenario = $this->baseScenario();
        $scenario['property']['livingArea'] = 39.34;
        $scenario['depreciation']['special7bActive'] = true;
        $scenario['depreciation']['special7bApplicationDate'] = '2023-01-01';
        $scenario['depreciation']['buildingBasis'] = 180000;
        $result = $this->calculate($scenario)->toArray();
        $special = $result['summary']['special7b'];

        self::assertEqualsWithDelta(180000, $special['eligibleCosts'], 0.01);
        self::assertEqualsWithDelta(157360, $special['cap'], 0.01);
        self::assertEqualsWithDelta(204568, $special['constructionCostCap'], 0.01);
        self::assertEqualsWithDelta(157360, $special['assessmentBasis'], 0.01);
        self::assertEqualsWithDelta(7868, $special['annualAmount'], 0.01);
    }

    public function testSpecial7bUsesOldSqmCap(): void
    {
        $scenario = $this->baseScenario();
        $scenario['property']['livingArea'] = 39.34;
        $scenario['depreciation']['special7bActive'] = true;
        $scenario['depreciation']['special7bApplicationDate'] = '2019-01-01';
        $scenario['depreciation']['buildingBasis'] = 100000;
        $result = $this->calculate($scenario)->toArray();

        self::assertEqualsWithDelta(78680, $result['summary']['special7b']['cap'], 0.01);
        self::assertEqualsWithDelta(78680, $result['summary']['special7b']['assessmentBasis'], 0.01);
    }

    public function testSpecial7bUsesEligibleCostsBelowCap(): void
    {
        $scenario = $this->baseScenario();
        $scenario['property']['livingArea'] = 39.34;
        $scenario['depreciation']['special7bActive'] = true;
        $scenario['depreciation']['special7bApplicationDate'] = '2023-01-01';
        $scenario['depreciation']['buildingBasis'] = 70000;
        $result = $this->calculate($scenario)->toArray();

        self::assertEqualsWithDelta(70000, $result['summary']['special7b']['assessmentBasis'], 0.01);
    }

    public function testSpecial7bWarnsAndSkipsWhenConstructionCostLimitIsExceeded(): void
    {
        $scenario = $this->baseScenario();
        $scenario['property']['livingArea'] = 39.34;
        $scenario['depreciation']['special7bActive'] = true;
        $scenario['depreciation']['special7bApplicationDate'] = '2023-01-01';
        $scenario['depreciation']['buildingBasis'] = 210000;
        $scenario['depreciation']['special7bActualConstructionCostPerSqm'] = 5300;
        $result = $this->calculate($scenario)->toArray();

        self::assertEqualsWithDelta(0, $result['summary']['special7b']['assessmentBasis'], 0.01);
        foreach($result['yearlyRows'] as $row) {
            self::assertEqualsWithDelta(0, $row['depreciation7b'], 0.01);
        }
        self::assertStringContainsString('Baukostenobergrenze', implode("\n", array_column($result['warnings'], 'message')));
    }

    public function testSavedTheOneScenarioApplies7bAssessmentCap(): void
    {
        $file = DIR_DATA_ROOT.'/realestateinvestment/scenarios/the-one-1-tilgung.json';
        if(!is_file($file)) {
            $file = DIR_DATA_ROOT.'/realestateinvestment/scenarios/the-one-we-1-22-1-tilgung.json';
        }
        $data = json_decode((string)file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $result = $this->calculate($data['scenario'])->toArray();

        self::assertEqualsWithDelta(39.34, $result['summary']['special7b']['area'], 0.01);
        self::assertEqualsWithDelta(157360, $result['summary']['special7b']['cap'], 0.01);
        self::assertEqualsWithDelta(204568, $result['summary']['special7b']['constructionCostCap'], 0.01);
        self::assertEqualsWithDelta(157360, $result['summary']['special7b']['assessmentBasis'], 0.01);
        self::assertEqualsWithDelta(4000, $result['summary']['special7b']['assessmentBasisPerSqm'], 0.01);
        self::assertEqualsWithDelta(5035.52, $result['yearlyRows'][2]['depreciationDegressive'], 0.01);
        self::assertEqualsWithDelta(7868, $result['yearlyRows'][2]['depreciation7b'], 0.01);
        self::assertEqualsWithDelta(330, $result['yearlyRows'][2]['depreciationFurniture'], 0.01);
        self::assertEqualsWithDelta(13233.52, $result['yearlyRows'][2]['depreciationTotal'], 0.01);
        $breakdown = array_column($result['calculationBreakdown'], null, 'label');
        self::assertEqualsWithDelta(5035.52, $breakdown['AfA degressiv 2028']['result'], 0.01);
        self::assertEqualsWithDelta(3021.31, $breakdown['AfA linear 2028']['result'], 0.01);
        self::assertEqualsWithDelta(7868, $breakdown['AfA §7b 2028']['result'], 0.01);
        self::assertEqualsWithDelta(330, $breakdown['AfA Möbel/Küche 2028']['result'], 0.01);
        self::assertEqualsWithDelta(0, $breakdown['AfA Stellplatz 2028']['result'], 0.01);
        self::assertEqualsWithDelta(13233.52, $breakdown['AfA gesamt 2028']['result'], 0.01);
        self::assertGreaterThan($breakdown['ESt nach Investition 2026']['result'], $breakdown['ESt vor Investition 2026']['result']);
        self::assertEqualsWithDelta($breakdown['ESt vor Investition 2026']['result'] - $breakdown['ESt nach Investition 2026']['result'], $breakdown['Steuerwirkung 2026']['result'], 0.01);
        self::assertEqualsWithDelta(7868, $result['yearlyRows'][3]['depreciation7b'], 0.01);
        self::assertEqualsWithDelta(7868, $result['yearlyRows'][4]['depreciation7b'], 0.01);
        self::assertEqualsWithDelta(7868, $result['yearlyRows'][5]['depreciation7b'], 0.01);
    }

    private function calculate(array $scenario): \realestateinvestment\classes\Results\InvestmentCalculationResult
    {
        return (new InvestmentCalculator())->calculate(RealEstateInvestmentScenario::fromArray($scenario));
    }

    private function markkleebergScenario(): array
    {
        $reference = json_decode((string)file_get_contents(DIR_DATA_ROOT.'/realestateinvestment/_examples/real_estate_pilot_markkleeberg_we_2_02_extracted.json'), true, 512, JSON_THROW_ON_ERROR);
        return (new RealEstatePilotScenarioTransformer())->transform($reference, 'Markkleeberg WE 2-02', 'Markkleeberg WE 2-02');
    }

    private function investagonStraubingScenario(): array
    {
        return (new InvestagonScenarioTransformer())->transform($this->investagonReference(), 'Wohnung 01 MFH Haus B (Default)', 'Straubing: Schlesische Strasse 107');
    }

    private function investagonReference(): array
    {
        return json_decode((string)file_get_contents(DIR_DATA_ROOT.'/realestateinvestment/_examples/investagon_straubing_schlesische_107_extracted.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    private function baseScenario(): array
    {
        return [
            'scenarioName' => 'Basisszenario',
            'property' => [
                'name' => 'Musterwohnung',
                'state' => 'BY',
                'purchaseYear' => 2026,
                'purchaseMonth' => 1,
                'completionYear' => 2026,
                'completionMonth' => 6,
                'rentStartYear' => 2026,
                'rentStartMonth' => 7,
                'saleYear' => 2036,
                'saleMonth' => 12,
                'livingArea' => 72,
                'apartmentPurchasePrice' => 300000,
                'otherPurchasePrice' => 0,
                'landSharePercent' => 20,
                'buildingSharePercent' => 80,
                'newBuilding' => true,
            ],
            'parkingUnits' => [[
                'label' => 'Stellplatz',
                'purchasePrice' => 20000,
                'monthlyRent' => 80,
                'buildingSharePercent' => 80,
                'landSharePercent' => 20,
                'depreciable' => true,
                'includedInPurchasePrice' => true,
            ]],
            'rent' => [
                'apartmentMonthlyRent' => 1200,
                'annualIncreasePercent' => 2,
                'increaseEveryYears' => 1,
                'vacancyPercent' => 2,
            ],
            'expenses' => [
                'nonRecoverableHausgeldMonthly' => 180,
                'maintenanceReserveMonthly' => 80,
                'managementMonthly' => 35,
                'annualIncreasePercent' => 2,
                'increaseEveryYears' => 1,
            ],
            'acquisitionCosts' => [
                'realEstateTransferTaxPercent' => 3.5,
                'notaryPercent' => 1.5,
                'landRegisterPurchasePercent' => 0.5,
                'landRegisterLienPercent' => 0.5,
                'brokerPercent' => 0,
            ],
            'constructionInterest' => [
                'yearlyEntries' => [],
            ],
            'loans' => [[
                'name' => 'Bankdarlehen',
                'priority' => 1,
                'principal' => 310000,
                'interestRatePercent' => 4,
                'initialRepaymentPercent' => 2,
                'startYear' => 2026,
                'startMonth' => 1,
                'fixedInterestYears' => 10,
                'followUpInterestRatePercent' => 5,
                'constantAnnuity' => true,
                'redeemOnSale' => true,
            ]],
            'depreciation' => [
                'startYear' => 2026,
                'startMonth' => 6,
                'degressiveActive' => true,
                'degressiveRatePercent' => 5,
                'linearRatePercent' => 3,
                'autoSwitchToLinear' => true,
                'special7bActive' => false,
            ],
            'tax' => [
                'taxableIncomeBeforeInvestment' => 80000,
                'marginalTaxRatePercent' => 42,
                'useMarginalTaxRate' => true,
            ],
            'sale' => [
                'annualValueIncreasePercent' => 2,
                'sellingCostsPercent' => 1,
                'taxFreeSale' => true,
            ],
            'settings' => [
                'discountRatePercent' => 5,
                'roundingMode' => 'commercial',
            ],
        ];
    }
}
