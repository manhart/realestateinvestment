<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Calculators;

use realestateinvestment\classes\Inputs\ConstructionInterestInput;
use realestateinvestment\classes\Inputs\DepreciationInput;
use realestateinvestment\classes\Inputs\RealEstateInvestmentScenario;
use realestateinvestment\classes\Inputs\TaxInput;
use realestateinvestment\classes\Results\InvestmentCalculationResult;
use realestateinvestment\classes\Results\YearlyCalculationResult;
use realestateinvestment\classes\Support\OfficialSourceRegistry;

final class InvestmentCalculator
{
    public function __construct(
        private readonly LoanCalculator $loanCalculator = new LoanCalculator(),
        private readonly DepreciationCalculator $depreciationCalculator = new DepreciationCalculator(),
        private readonly TaxCalculator $taxCalculator = new TaxCalculator(),
        private readonly MetricScaleEvaluator $scaleEvaluator = new MetricScaleEvaluator(),
        private readonly ScenarioValidator $validator = new ScenarioValidator(),
        private readonly SpecialAfa7bCalculator $specialAfa7bCalculator = new SpecialAfa7bCalculator(),
    ) {}

    public function calculate(RealEstateInvestmentScenario $scenario): InvestmentCalculationResult
    {
        $startYear = $scenario->property->purchaseYear;
        $endYear = $scenario->property->saleYear;
        $purchasePrice = $this->purchasePrice($scenario);
        $initialDebt = $this->initialDebt($scenario);
        $costAllocation = $this->costAllocation($scenario);
        $acquisition = $this->acquisitionCosts($scenario, $purchasePrice, $initialDebt);
        $constructionInterestTotal = $this->constructionInterestTotal($scenario->constructionInterest);
        $totalCosts = $purchasePrice + $acquisition['withoutFinancing'] + $acquisition['financing'] + $constructionInterestTotal;
        $nonFinancedOneTimeCosts = max($purchasePrice + $acquisition['withoutFinancing'] + $acquisition['financing'] - $initialDebt, 0);

        $scenario->depreciation->buildingBasis = $scenario->depreciation->buildingBasis > 0
            ? $scenario->depreciation->buildingBasis
            : $this->buildingDepreciationBasis($costAllocation, $acquisition['withoutFinancing']);
        $scenario->depreciation->parkingDepreciationItems = $this->parkingDepreciationItems($scenario);
        $scenario->depreciation->parkingBasis = array_sum(array_map(static fn(array $item): float => (float)$item['basis'], $scenario->depreciation->parkingDepreciationItems));
        $scenario->depreciation->furnitureBasis = $scenario->depreciation->furnitureBasis > 0
            ? $scenario->depreciation->furnitureBasis
            : $costAllocation['furnitureCosts'];
        $scenario->depreciation->special7bArea = $scenario->depreciation->special7bArea > 0
            ? $scenario->depreciation->special7bArea
            : $scenario->property->livingArea;
        $scenario->depreciation->special7bBasis = $scenario->depreciation->special7bBasis > 0
            ? $scenario->depreciation->special7bBasis
            : $scenario->depreciation->buildingBasis;

        $special7b = $this->specialAfa7bCalculator->calculate($scenario->depreciation, $scenario->property->livingArea);
        $scenario->depreciation->special7bBasis = $special7b['assessmentBasis'];
        $scenario->depreciation->special7bCap = $special7b['cap'];

        $warnings = array_merge(
            $this->validator->validate($scenario, $totalCosts, $initialDebt),
            $special7b['warnings'],
        );
        $loanYears = $this->loanCalculator->calculate($scenario->loans, $startYear, $endYear, $scenario->property->saleMonth);
        $depreciationYears = $this->depreciationCalculator->calculate($scenario->depreciation, $startYear, $endYear, $scenario->property->saleMonth);

        $rows = [];
        for($year = $startYear; $year <= $endYear; $year++) {
            $row = new YearlyCalculationResult($year);
            $row->rentedMonths = $this->activeMonths($year, $scenario->property->rentStartYear, $scenario->property->rentStartMonth, $endYear, $scenario->property->saleMonth);
            $row->rent = $this->rentForYear($scenario, $year, $row->rentedMonths);
            $row->expenses = $this->expensesForYear($scenario, $year, $row->rentedMonths);
            $row->deductibleOperatingExpenses = $this->deductibleOperatingExpensesForYear($scenario, $year, $row->rentedMonths);
            $row->operatingCashflow = $row->rent - $row->expenses;

            $loanYear = $loanYears[$year] ?? ['interest' => 0.0, 'repayment' => 0.0, 'annuity' => 0.0, 'remainingDebt' => 0.0];
            $row->interest = $loanYear['interest'];
            $row->repayment = $loanYear['repayment'];
            $row->annuity = $loanYear['annuity'];
            $row->remainingDebt = $loanYear['remainingDebt'];

            $depreciation = $depreciationYears[$year] ?? ['degressive' => 0.0, 'linear' => 0.0, 'special7b' => 0.0, 'furniture' => 0.0, 'parking' => 0.0, 'total' => 0.0];
            $row->depreciationDegressive = $depreciation['degressive'];
            $row->depreciationLinear = $depreciation['linear'];
            $row->depreciation7b = $depreciation['special7b'];
            $row->depreciationFurniture = $depreciation['furniture'];
            $row->depreciationParking = $depreciation['parking'];
            $row->depreciationTotal = $depreciation['total'];

            $row->constructionInterest = $this->constructionInterestForYear($scenario->constructionInterest, $year);
            $row->deductibleExpenses = $this->constructionInterestDeductibleForYear($scenario->constructionInterest, $year);
            if($year === $startYear && $scenario->acquisitionCosts->financingCostsDeductible) {
                $row->deductibleExpenses += $acquisition['financing'];
            }
            $row->advertisingCostsWithoutDepreciation = $row->deductibleOperatingExpenses + $row->interest + $row->deductibleExpenses;
            $row->taxDeductionsTotal = $row->advertisingCostsWithoutDepreciation + $row->depreciationTotal;
            $row->rentalTaxableIncome = $row->rent - $row->taxDeductionsTotal;
            $taxableIncomeBeforeInvestment = $this->taxableIncomeBeforeInvestmentForYear($scenario->tax, $year, $startYear);
            $tax = $this->taxCalculator->calculate($scenario->tax, $row->rentalTaxableIncome, $taxableIncomeBeforeInvestment);
            $row->taxableIncomeBefore = $tax['taxableBefore'];
            $row->taxableIncomeAfter = $tax['taxableAfter'];
            $row->tariffIncomeTaxBefore = $tax['tariffIncomeTaxBefore'];
            $row->tariffIncomeTaxAfter = $tax['tariffIncomeTaxAfter'];
            $row->solidaritySurchargeBefore = $tax['solidaritySurchargeBefore'];
            $row->solidaritySurchargeAfter = $tax['solidaritySurchargeAfter'];
            $row->churchTaxBefore = $tax['churchTaxBefore'];
            $row->churchTaxAfter = $tax['churchTaxAfter'];
            $row->incomeTaxBefore = $tax['taxBefore'];
            $row->incomeTaxAfter = $tax['taxAfter'];
            $row->taxEffect = $tax['taxEffect'];
            $row->effectiveTaxRate = abs($row->rentalTaxableIncome) > 0.005 ? abs($row->taxEffect / $row->rentalTaxableIncome) : 0.0;

            $row->oneTimeCashCosts = $year === $startYear ? $nonFinancedOneTimeCosts : 0.0;
            $cashConstructionInterest = $this->constructionInterestCashForYear($scenario->constructionInterest, $year);
            $row->netCashflowBeforeTax = $row->rent - $row->expenses - $row->annuity - $cashConstructionInterest - $row->oneTimeCashCosts;
            $row->netCashflowAfterTax = $row->netCashflowBeforeTax + $row->taxEffect;

            if($year === $endYear) {
                $row->salePrice = $this->salePrice($scenario, $purchasePrice);
                $sellingCosts = $row->salePrice * $scenario->sale->sellingCostsRate + $scenario->sale->sellingCostsAmount;
                $row->saleProceedsAfterDebt = $row->salePrice - $row->remainingDebt - $sellingCosts - $scenario->sale->prepaymentPenaltyAmount;
            }
            $row->netCashflowIncludingSale = $row->netCashflowAfterTax + $row->saleProceedsAfterDebt;

            $row->debtYield = $initialDebt > 0 ? $row->operatingCashflow / $initialDebt : 0.0;
            $row->dscr = $row->annuity > 0 ? $row->operatingCashflow / $row->annuity : 0.0;
            $rows[] = $row;
        }

        $metrics = $this->metrics($rows, $initialDebt, $scenario->settings->discountRate, $scenario->settings->initialEquityAmount, max($endYear - $startYear, 1));
        $firstOperatingRow = $this->firstOperatingRow($rows);
        $firstTaxEffectRow = $this->firstTaxEffectRow($rows);
        if($firstOperatingRow && $firstOperatingRow->dscr < 1) {
            $warnings[] = ['level' => 'warning', 'message' => 'DSCR liegt im ersten Vermietungsjahr unter 1.'];
        }
        if($firstOperatingRow && $firstOperatingRow->debtYield < 0.05) {
            $warnings[] = ['level' => 'warning', 'message' => 'Debt Yield liegt im ersten Vermietungsjahr unter 5 %.'];
        }
        if($metrics['npv'] < 0) {
            $warnings[] = ['level' => 'warning', 'message' => 'NPV ist negativ; die Alternativrendite wird rechnerisch nicht erreicht.'];
        }
        $warnings[] = ['level' => 'info', 'message' => 'Liquiditätsorientierter FV bewertet negative Cashflows nicht mit Opportunitätskosten und ist daher optimistischer.'];

        $scales = [
            'leverageEfficiency' => $this->scaleEvaluator->evaluateNominalLeverage($metrics['leverageEfficiency']),
            'npvLeverageEfficiency' => $this->scaleEvaluator->evaluateNpvLeverage($metrics['npvLeverageEfficiency']),
            'fvLeverageConservative' => $this->scaleEvaluator->evaluateNominalLeverage($metrics['fvLeverageConservative']),
            'fvLeverageLiquidity' => $this->scaleEvaluator->evaluateNominalLeverage($metrics['fvLeverageLiquidity']),
            'debtYield' => $firstOperatingRow ? $this->scaleEvaluator->evaluateDebtYield($firstOperatingRow->debtYield) : ['label' => 'n/a', 'variant' => 'secondary'],
            'dscr' => $firstOperatingRow ? $this->scaleEvaluator->evaluateDscr($firstOperatingRow->dscr) : ['label' => 'n/a', 'variant' => 'secondary'],
        ];

        return new InvestmentCalculationResult(
            $this->summary($scenario, $purchasePrice, $acquisition, $costAllocation, $totalCosts, $initialDebt, $firstOperatingRow, $metrics, $special7b),
            $metrics,
            $scales,
            $this->scaleEvaluator->legend(),
            $warnings,
            $rows,
            $this->formulas(),
            $this->calculationBreakdown($scenario, $purchasePrice, $acquisition, $costAllocation, $totalCosts, $initialDebt, $firstOperatingRow, $firstTaxEffectRow, $rows, $metrics, $special7b),
        );
    }

    private function purchasePrice(RealEstateInvestmentScenario $scenario): float
    {
        $parking = 0.0;
        foreach($scenario->parkingUnits as $unit) {
            if($unit->includedInPurchasePrice) {
                $parking += $unit->purchasePrice;
            }
        }
        return $scenario->property->apartmentPurchasePrice + $scenario->property->otherPurchasePrice + $scenario->property->furniturePurchasePrice + $parking;
    }

    private function initialDebt(RealEstateInvestmentScenario $scenario): float
    {
        return array_sum(array_map(static fn($loan): float => max($loan->principal, 0), $scenario->loans));
    }

    private function acquisitionCosts(RealEstateInvestmentScenario $scenario, float $purchasePrice, float $initialDebt): array
    {
        $input = $scenario->acquisitionCosts;
        $realEstatePurchasePrice = max($purchasePrice - $scenario->property->furniturePurchasePrice, 0);
        $realEstateTransferTax = $realEstatePurchasePrice * $input->realEstateTransferTax;
        $notary = $realEstatePurchasePrice * $input->notaryRate;
        $landRegisterPurchase = $realEstatePurchasePrice * $input->landRegisterPurchaseRate;
        $broker = $realEstatePurchasePrice * $input->brokerRate;
        $landRegisterLien = $initialDebt * $input->landRegisterLienRate;
        $withoutFinancing = $realEstateTransferTax + $notary + $landRegisterPurchase + $broker + $input->otherAcquisitionCosts;
        $financing = $landRegisterLien + $input->otherFinancingCosts;

        return [
            'realEstatePurchasePrice' => $realEstatePurchasePrice,
            'realEstateTransferTax' => $realEstateTransferTax,
            'notary' => $notary,
            'landRegisterPurchase' => $landRegisterPurchase,
            'broker' => $broker,
            'otherAcquisitionCosts' => $input->otherAcquisitionCosts,
            'landRegisterLien' => $landRegisterLien,
            'otherFinancingCosts' => $input->otherFinancingCosts,
            'withoutFinancing' => $withoutFinancing,
            'financing' => $financing,
            'total' => $withoutFinancing + $financing,
        ];
    }

    private function costAllocation(RealEstateInvestmentScenario $scenario): array
    {
        $propertySplitBase = $scenario->property->apartmentPurchasePrice + $scenario->property->otherPurchasePrice;
        $propertyLand = $propertySplitBase * $scenario->property->landShare;
        $propertyBuilding = $propertySplitBase * $scenario->property->buildingShare;
        $parkingLand = 0.0;
        $parkingBuilding = 0.0;
        $parkingTotal = 0.0;

        foreach($scenario->parkingUnits as $unit) {
            if($unit->includedInPurchasePrice) {
                $parkingTotal += $unit->purchasePrice;
                $parkingLand += $unit->purchasePrice * $unit->landShare;
                $parkingBuilding += $unit->purchasePrice * $unit->buildingShare;
            }
        }

        return [
            'propertySplitBase' => $propertySplitBase,
            'apartmentPurchasePrice' => $scenario->property->apartmentPurchasePrice,
            'otherPurchasePrice' => $scenario->property->otherPurchasePrice,
            'furnitureCosts' => $scenario->property->furniturePurchasePrice,
            'propertyLandCosts' => $propertyLand,
            'propertyBuildingCosts' => $propertyBuilding,
            'parkingTotal' => $parkingTotal,
            'parkingLandCosts' => $parkingLand,
            'parkingBuildingCosts' => $parkingBuilding,
            'landCosts' => $propertyLand + $parkingLand,
            'buildingCosts' => $propertyBuilding + $parkingBuilding,
        ];
    }

    /**
     * @return array<int, array{label: string, basis: float, rate: float, mode: string}>
     */
    private function parkingDepreciationItems(RealEstateInvestmentScenario $scenario): array
    {
        if($scenario->depreciation->parkingBasis > 0) {
            return [[
                'label' => 'Stellplätze',
                'basis' => $scenario->depreciation->parkingBasis,
                'rate' => $scenario->depreciation->parkingRate > 0 ? $scenario->depreciation->parkingRate : $scenario->depreciation->linearRate,
                'mode' => 'custom',
            ]];
        }

        $items = [];
        foreach($scenario->parkingUnits as $unit) {
            if(!$unit->includedInPurchasePrice || !$unit->depreciable) {
                continue;
            }

            $basis = $unit->purchasePrice * $unit->buildingShare;
            if($basis <= 0) {
                continue;
            }

            $rate = $unit->depreciationMode === 'custom'
                ? $unit->depreciationRate
                : $scenario->depreciation->linearRate;
            $items[] = [
                'label' => $unit->label,
                'basis' => $basis,
                'rate' => max($rate, 0),
                'mode' => $unit->depreciationMode,
            ];
        }
        return $items;
    }

    private function buildingDepreciationBasis(array $costAllocation, float $acquisitionCostsWithoutFinancing): float
    {
        $realEstateBase = $costAllocation['landCosts'] + $costAllocation['buildingCosts'];
        $buildingRatio = $realEstateBase > 0 ? $costAllocation['buildingCosts'] / $realEstateBase : 0;
        return $costAllocation['buildingCosts'] + $acquisitionCostsWithoutFinancing * $buildingRatio;
    }

    private function activeMonths(int $year, int $startYear, int $startMonth, int $endYear, int $endMonth): int
    {
        if($year < $startYear || $year > $endYear) {
            return 0;
        }
        $from = $year === $startYear ? $startMonth : 1;
        $to = $year === $endYear ? $endMonth : 12;
        return max($to - $from + 1, 0);
    }

    private function rentForYear(RealEstateInvestmentScenario $scenario, int $year, int $months): float
    {
        if($months <= 0) {
            return 0.0;
        }
        $parkingRent = array_sum(array_map(static fn($unit): float => $unit->monthlyRent, $scenario->parkingUnits));
        $monthlyRent = $scenario->rent->apartmentMonthlyRent + $parkingRent;
        $factorYear = $scenario->rent->increaseStartYear > 0 ? max($year - $scenario->rent->increaseStartYear, 0) : $this->yearsSinceFirstFullYear($year, $scenario->property->rentStartYear, $scenario->property->rentStartMonth);
        $steps = intdiv(max($factorYear, 0), $scenario->rent->increaseEveryYears);
        $factor = (1 + $scenario->rent->annualIncrease) ** $steps;
        return ($monthlyRent * $months * $factor + $scenario->rent->otherAnnualIncome) * (1 - $scenario->rent->vacancyRate);
    }

    private function expensesForYear(RealEstateInvestmentScenario $scenario, int $year, int $months): float
    {
        if($months <= 0) {
            return 0.0;
        }
        $years = $this->yearsSinceFirstFullYear($year, $scenario->property->rentStartYear, $scenario->property->rentStartMonth);
        $steps = intdiv(max($years, 0), $scenario->expenses->increaseEveryYears);
        return $scenario->expenses->monthlyTotal() * $months * ((1 + $scenario->expenses->annualIncrease) ** $steps);
    }

    private function deductibleOperatingExpensesForYear(RealEstateInvestmentScenario $scenario, int $year, int $months): float
    {
        if($months <= 0) {
            return 0.0;
        }
        $years = $this->yearsSinceFirstFullYear($year, $scenario->property->rentStartYear, $scenario->property->rentStartMonth);
        $steps = intdiv(max($years, 0), $scenario->expenses->increaseEveryYears);
        return $scenario->expenses->taxDeductibleMonthlyTotal() * $months * ((1 + $scenario->expenses->annualIncrease) ** $steps);
    }

    private function yearsSinceFirstFullYear(int $year, int $startYear, int $startMonth): int
    {
        $firstFullYear = $startMonth === 1 ? $startYear : $startYear + 1;
        return max($year - $firstFullYear, 0);
    }

    private function constructionInterestForYear(ConstructionInterestInput $input, int $year): float
    {
        if($input->hasYearlyEntries()) {
            $sum = 0.0;
            foreach($input->yearlyEntries as $entry) {
                if($entry->year === $year) {
                    $sum += $entry->amount;
                }
            }
            return $sum;
        }

        return 0.0;
    }

    private function constructionInterestDeductibleForYear(ConstructionInterestInput $input, int $year): float
    {
        if(!$input->hasYearlyEntries()) {
            return 0.0;
        }

        $sum = 0.0;
        foreach($input->yearlyEntries as $entry) {
            if($entry->year === $year && $entry->deductible) {
                $sum += $entry->amount;
            }
        }
        return $sum;
    }

    private function constructionInterestCashForYear(ConstructionInterestInput $input, int $year): float
    {
        if(!$input->hasYearlyEntries()) {
            return 0.0;
        }

        $sum = 0.0;
        foreach($input->yearlyEntries as $entry) {
            if($entry->year === $year && !$entry->financed) {
                $sum += $entry->amount;
            }
        }
        return $sum;
    }

    private function constructionInterestTotal(ConstructionInterestInput $input): float
    {
        if($input->hasYearlyEntries()) {
            return array_sum(array_map(static fn($entry): float => $entry->amount, $input->yearlyEntries));
        }

        return 0.0;
    }

    private function constructionInterestDeductibleTotal(ConstructionInterestInput $input): float
    {
        if(!$input->hasYearlyEntries()) {
            return 0.0;
        }

        $sum = 0.0;
        foreach($input->yearlyEntries as $entry) {
            if($entry->deductible) {
                $sum += $entry->amount;
            }
        }
        return $sum;
    }

    private function constructionInterestCashTotal(ConstructionInterestInput $input): float
    {
        if($input->hasYearlyEntries()) {
            $sum = 0.0;
            foreach($input->yearlyEntries as $entry) {
                if(!$entry->financed) {
                    $sum += $entry->amount;
                }
            }
            return $sum;
        }

        return 0.0;
    }

    private function salePrice(RealEstateInvestmentScenario $scenario, float $purchasePrice): float
    {
        $years = max($scenario->property->saleYear - $scenario->property->purchaseYear, 0);
        return $purchasePrice * ((1 + $scenario->sale->annualValueIncrease) ** $years);
    }

    /**
     * @param YearlyCalculationResult[] $rows
     */
    private function metrics(array $rows, float $initialDebt, float $discountRate, float $initialEquityAmount, int $holdingYears): array
    {
        $netWorth = 0.0;
        $npv = 0.0;
        $fvConservative = 0.0;
        $fvLiquidity = 0.0;
        $equityContributions = max($initialEquityAmount, 0);
        $equityCapitalCalls = 0.0;
        $equityDistributions = 0.0;
        $lastIndex = max(count($rows) - 1, 0);

        foreach($rows as $index => $row) {
            $cashflow = $row->netCashflowIncludingSale;
            $netWorth += $cashflow;
            $npv += $cashflow / ((1 + $discountRate) ** $index);
            $fvConservative += $cashflow * ((1 + $discountRate) ** ($lastIndex - $index));
            $fvLiquidity += $cashflow > 0 ? $cashflow * ((1 + $discountRate) ** ($lastIndex - $index)) : $cashflow;

            if($row->netCashflowAfterTax < 0) {
                $equityCapitalCalls += -$row->netCashflowAfterTax;
            } else {
                $equityDistributions += $row->netCashflowAfterTax;
            }
            if($row->saleProceedsAfterDebt < 0) {
                $equityCapitalCalls += -$row->saleProceedsAfterDebt;
            } else {
                $equityDistributions += $row->saleProceedsAfterDebt;
            }
        }

        $totalEquityInvested = $equityContributions + $equityCapitalCalls;
        $equityNetGain = $equityDistributions - $totalEquityInvested;
        $equityMultiple = $totalEquityInvested > 0 ? $equityDistributions / $totalEquityInvested : 0.0;

        return [
            'netWorthEffect' => $netWorth,
            'leverageEfficiency' => $initialDebt > 0 ? $netWorth / $initialDebt : 0.0,
            'npv' => $npv,
            'npvLeverageEfficiency' => $initialDebt > 0 ? $npv / $initialDebt : 0.0,
            'futureValueConservative' => $fvConservative,
            'fvLeverageConservative' => $initialDebt > 0 ? $fvConservative / $initialDebt : 0.0,
            'futureValueLiquidity' => $fvLiquidity,
            'fvLeverageLiquidity' => $initialDebt > 0 ? $fvLiquidity / $initialDebt : 0.0,
            'initialEquityAmount' => $equityContributions,
            'equityCapitalCalls' => $equityCapitalCalls,
            'totalEquityInvested' => $totalEquityInvested,
            'equityDistributions' => $equityDistributions,
            'equityNetGain' => $equityNetGain,
            'equityMultiple' => $equityMultiple,
            'equityTotalReturn' => $totalEquityInvested > 0 ? $equityNetGain / $totalEquityInvested : 0.0,
            'equityAnnualizedReturn' => $equityMultiple > 0 && $holdingYears > 0 ? ($equityMultiple ** (1 / $holdingYears)) - 1 : 0.0,
        ];
    }

    /**
     * @param YearlyCalculationResult[] $rows
     */
    private function firstOperatingRow(array $rows): ?YearlyCalculationResult
    {
        foreach($rows as $row) {
            if($row->rentedMonths === 12) {
                return $row;
            }
        }
        foreach($rows as $row) {
            if($row->rentedMonths > 0) {
                return $row;
            }
        }
        return null;
    }

    /**
     * @param YearlyCalculationResult[] $rows
     */
    private function firstTaxEffectRow(array $rows): ?YearlyCalculationResult
    {
        foreach($rows as $row) {
            if(abs($row->taxEffect) > 0.005 || abs($row->rentalTaxableIncome) > 0.005) {
                return $row;
            }
        }
        return null;
    }

    private function summary(RealEstateInvestmentScenario $scenario, float $purchasePrice, array $acquisition, array $costAllocation, float $totalCosts, float $initialDebt, ?YearlyCalculationResult $firstOperatingRow, array $metrics, array $special7b): array
    {
        $annualRent = $firstOperatingRow?->rent ?? 0.0;
        $monthlyRent = $annualRent / max($firstOperatingRow?->rentedMonths ?? 12, 1);
        $netOperatingIncome = $firstOperatingRow?->operatingCashflow ?? 0.0;
        $constructionInterestTotal = $this->constructionInterestTotal($scenario->constructionInterest);
        $constructionInterestCashTotal = $this->constructionInterestCashTotal($scenario->constructionInterest);
        $oneTimeFinancingCostsTotal = $acquisition['financing'] + $constructionInterestTotal;
        $acquisitionBreakdown = [
            'realEstatePurchasePrice' => $acquisition['realEstatePurchasePrice'],
            'realEstateTransferTax' => $acquisition['realEstateTransferTax'],
            'notary' => $acquisition['notary'],
            'landRegisterPurchase' => $acquisition['landRegisterPurchase'],
            'broker' => $acquisition['broker'],
            'otherAcquisitionCosts' => $acquisition['otherAcquisitionCosts'],
            'landRegisterLien' => $acquisition['landRegisterLien'],
            'otherFinancingCosts' => $acquisition['otherFinancingCosts'],
            'acquisitionCostsWithoutFinancing' => $acquisition['withoutFinancing'],
            'financingCosts' => $acquisition['financing'],
            'totalAcquisitionAndFinancingCosts' => $acquisition['total'],
        ];

        return [
            'purchasePrice' => $purchasePrice,
            'acquisitionCostsWithoutFinancing' => $acquisition['withoutFinancing'],
            'financingCosts' => $acquisition['financing'],
            'acquisitionBreakdown' => $acquisitionBreakdown,
            'costAllocation' => $costAllocation,
            'buildingDepreciationBasis' => $scenario->depreciation->buildingBasis,
            'furnitureDepreciationBasis' => $scenario->depreciation->furnitureBasis,
            'parkingDepreciationBasis' => $scenario->depreciation->parkingBasis,
            'parkingDepreciationRate' => $this->singleParkingDepreciationRate($scenario->depreciation->parkingDepreciationItems),
            'parkingDepreciationMixedRates' => $this->hasMixedParkingDepreciationRates($scenario->depreciation->parkingDepreciationItems),
            'totalCosts' => $totalCosts,
            'constructionInterestTotal' => $constructionInterestTotal,
            'constructionInterestCashTotal' => $constructionInterestCashTotal,
            'constructionInterestFinancedTotal' => max($constructionInterestTotal - $constructionInterestCashTotal, 0),
            'oneTimeFinancingCostsTotal' => $oneTimeFinancingCostsTotal,
            'totalInvestmentCost' => $totalCosts,
            'special7b' => $special7b,
            'initialDebt' => $initialDebt,
            'monthlyRent' => $monthlyRent,
            'grossYield' => $purchasePrice > 0 ? ($annualRent / $purchasePrice) : 0.0,
            'netYield' => $purchasePrice > 0 ? ($netOperatingIncome / $purchasePrice) : 0.0,
            'firstFullYearNetCashflowBeforeTax' => $firstOperatingRow?->netCashflowBeforeTax ?? 0.0,
            'firstFullYearNetCashflowAfterTax' => $firstOperatingRow?->netCashflowAfterTax ?? 0.0,
            'debtYield' => $firstOperatingRow?->debtYield ?? 0.0,
            'dscr' => $firstOperatingRow?->dscr ?? 0.0,
            ...$metrics,
        ];
    }

    private function singleParkingDepreciationRate(array $items): float
    {
        $rates = $this->parkingDepreciationRates($items);
        return count($rates) === 1 ? reset($rates) : 0.0;
    }

    private function hasMixedParkingDepreciationRates(array $items): bool
    {
        return count($this->parkingDepreciationRates($items)) > 1;
    }

    /**
     * @return float[]
     */
    private function parkingDepreciationRates(array $items): array
    {
        $rates = [];
        foreach($items as $item) {
            if((float)($item['basis'] ?? 0) <= 0) {
                continue;
            }
            $rate = round((float)($item['rate'] ?? 0), 8);
            $rates[(string)$rate] = $rate;
        }
        return array_values($rates);
    }

    /**
     * @param YearlyCalculationResult[] $yearlyRows
     */
    private function calculationBreakdown(RealEstateInvestmentScenario $scenario, float $purchasePrice, array $acquisition, array $costAllocation, float $totalCosts, float $initialDebt, ?YearlyCalculationResult $firstOperatingRow, ?YearlyCalculationResult $firstTaxEffectRow, array $yearlyRows, array $metrics, array $special7b): array
    {
        $constructionInterestTotal = $this->constructionInterestTotal($scenario->constructionInterest);
        $constructionInterestValues = $scenario->constructionInterest->hasYearlyEntries()
            ? implode(' + ', array_map(static fn($entry): string => $entry->year.': '.$entry->amount, $scenario->constructionInterest->yearlyEntries))
            : 'keine Bauzeitzins-Jahreszeilen';
        $deductibleConstructionInterestTotal = $this->constructionInterestDeductibleTotal($scenario->constructionInterest);
        $deductibleFinancingCosts = $scenario->acquisitionCosts->financingCostsDeductible ? $acquisition['financing'] : 0.0;

        $rows = [
            [
                'group' => 'Kaufpreis',
                'label' => 'Gesamtkaufpreis',
                'formula' => 'Wohnung + sonstige Bestandteile + Küche/Möbel + Stellplätze',
                'values' => $scenario->property->apartmentPurchasePrice.' + '.$scenario->property->otherPurchasePrice.' + '.$scenario->property->furniturePurchasePrice.' + '.$costAllocation['parkingTotal'],
                'result' => $purchasePrice,
            ],
            [
                'group' => 'Kostenaufteilung',
                'label' => 'Grundstückskosten',
                'formula' => '(Wohnung + sonstige Bestandteile) × Grundanteil + Stellplatz-Grundanteil',
                'values' => $costAllocation['propertySplitBase'].' × '.($scenario->property->landShare * 100).' % + '.$costAllocation['parkingLandCosts'],
                'result' => $costAllocation['landCosts'],
            ],
            [
                'group' => 'Kostenaufteilung',
                'label' => 'Herstellungskosten/Gebäude',
                'formula' => '(Wohnung + sonstige Bestandteile) × Gebäudeanteil + Stellplatz-Gebäudeanteil',
                'values' => $costAllocation['propertySplitBase'].' × '.($scenario->property->buildingShare * 100).' % + '.$costAllocation['parkingBuildingCosts'],
                'result' => $costAllocation['buildingCosts'],
            ],
            [
                'group' => 'Kaufnebenkosten',
                'label' => 'Bemessungsgrundlage Immobilienkauf',
                'formula' => 'Gesamtkaufpreis - separat ausgewiesene Küche/Möbel',
                'values' => $purchasePrice.' - '.$scenario->property->furniturePurchasePrice,
                'result' => $acquisition['realEstatePurchasePrice'],
            ],
            [
                'group' => 'Kaufnebenkosten',
                'label' => 'Erwerbsnebenkosten',
                'formula' => 'GrESt + Notar + Grundbuch Kauf + Makler + sonstige Erwerbsnebenkosten',
                'values' => $acquisition['realEstateTransferTax'].' + '.$acquisition['notary'].' + '.$acquisition['landRegisterPurchase'].' + '.$acquisition['broker'].' + '.$acquisition['otherAcquisitionCosts'],
                'result' => $acquisition['withoutFinancing'],
                'source' => OfficialSourceRegistry::get('hgb_255_anschaffungskosten'),
            ],
            [
                'group' => 'Finanzierungskosten',
                'label' => 'Finanzierungskosten',
                'formula' => 'Darlehenssumme × Pfandrecht % + sonstige Kreditkosten',
                'values' => $initialDebt.' × '.($scenario->acquisitionCosts->landRegisterLienRate * 100).' % + '.$acquisition['otherFinancingCosts'],
                'result' => $acquisition['financing'],
                'source' => OfficialSourceRegistry::get('estg_9_werbungskosten'),
            ],
            [
                'group' => 'Bauzeitzinsen',
                'label' => 'Bauzeitzinsen brutto',
                'formula' => 'Summe jährliche Bauzeitzins-Zeilen unabhängig von der steuerlichen Behandlung',
                'values' => $constructionInterestValues,
                'result' => $constructionInterestTotal,
                'source' => OfficialSourceRegistry::get('esth_21_vuv'),
            ],
            [
                'group' => 'Werbungskosten',
                'label' => 'BZZ/Kreditkosten WK',
                'formula' => 'abzugsfähige Bauzeitzinsen + abzugsfähige Finanzierungskosten',
                'values' => $deductibleConstructionInterestTotal.' + '.$deductibleFinancingCosts,
                'result' => $deductibleConstructionInterestTotal + $deductibleFinancingCosts,
                'source' => OfficialSourceRegistry::get('esth_21_vuv'),
            ],
            [
                'group' => 'Gesamtkosten',
                'label' => 'Gesamtkosten',
                'formula' => 'Kaufpreis + Erwerbsnebenkosten + Finanzierungskosten + Bauzeitzinsen',
                'values' => $purchasePrice.' + '.$acquisition['withoutFinancing'].' + '.$acquisition['financing'].' + '.$constructionInterestTotal,
                'result' => $totalCosts,
            ],
            [
                'group' => 'AfA',
                'label' => 'Gebäude-AfA-Basis',
                'formula' => 'Herstellungskosten/Gebäude + anteilige Erwerbsnebenkosten',
                'values' => $costAllocation['buildingCosts'].' + anteilig '.$acquisition['withoutFinancing'],
                'result' => $scenario->depreciation->buildingBasis,
                'source' => OfficialSourceRegistry::get('hgb_255_anschaffungskosten'),
            ],
            [
                'group' => 'AfA',
                'label' => '§7b-Bemessungsgrundlage',
                'formula' => 'min(begünstigte AH-Kosten, §7b-Fläche × Förderhöchstgrenze je m²)',
                'values' => 'min('.$special7b['eligibleCosts'].', '.$special7b['area'].' × '.$special7b['limitPerSqm'].')',
                'result' => $special7b['assessmentBasis'],
                'source' => OfficialSourceRegistry::get('esth_7b'),
            ],
        ];

        array_push($rows, ...$this->depreciationBreakdownRows($scenario, $special7b));

        if($firstOperatingRow) {
            $rows[] = $this->rentBreakdownRow($scenario, $firstOperatingRow);
            $rows[] = [
                'group' => 'Liquidität',
                'label' => 'CF nach Steuer erstes volles Jahr',
                'formula' => 'Miete - Ausgaben - Annuität + Steuerwirkung',
                'values' => $firstOperatingRow->rent.' - '.$firstOperatingRow->expenses.' - '.$firstOperatingRow->annuity.' + '.$firstOperatingRow->taxEffect,
                'result' => $firstOperatingRow->netCashflowAfterTax,
            ];
            $rows[] = [
                'group' => 'Laufende Kosten',
                'label' => 'laufende Ausgaben erstes volles Jahr',
                'formula' => 'alle monatlichen Ausgaben inkl. Rücklagen',
                'values' => (string)$firstOperatingRow->expenses,
                'result' => $firstOperatingRow->expenses,
            ];
            $rows[] = [
                'group' => 'Werbungskosten',
                'label' => 'abzugsf. laufende Kosten erstes volles Jahr',
                'formula' => 'Hausgeld nicht umlagefähig + Verwaltung + Service/Sonstiges + sonstige laufende Kosten',
                'values' => (string)$firstOperatingRow->deductibleOperatingExpenses,
                'result' => $firstOperatingRow->deductibleOperatingExpenses,
                'source' => OfficialSourceRegistry::get('estg_9_werbungskosten'),
            ];
            $rows[] = [
                'group' => 'Werbungskosten',
                'label' => 'Werbungskosten ohne AfA erstes volles Jahr',
                'formula' => 'abzugsf. laufende Kosten + Darlehenszinsen + BZZ/Kreditkosten WK',
                'values' => $firstOperatingRow->deductibleOperatingExpenses.' + '.$firstOperatingRow->interest.' + '.$firstOperatingRow->deductibleExpenses,
                'result' => $firstOperatingRow->advertisingCostsWithoutDepreciation,
                'source' => OfficialSourceRegistry::get('esth_21_vuv'),
            ];
            $rows[] = [
                'group' => 'Steuer',
                'label' => 'steuerliche Abzüge gesamt erstes volles Jahr',
                'formula' => 'Werbungskosten ohne AfA + AfA gesamt',
                'values' => $firstOperatingRow->advertisingCostsWithoutDepreciation.' + '.$firstOperatingRow->depreciationTotal,
                'result' => $firstOperatingRow->taxDeductionsTotal,
                'source' => OfficialSourceRegistry::get('estg_21_vuv'),
            ];
            $rows[] = [
                'group' => 'Steuer',
                'label' => 'Einkünfte VuV erstes volles Jahr',
                'formula' => 'Miete - Werbungskosten ohne AfA - AfA gesamt',
                'values' => $firstOperatingRow->rent.' - '.$firstOperatingRow->advertisingCostsWithoutDepreciation.' - '.$firstOperatingRow->depreciationTotal,
                'result' => $firstOperatingRow->rentalTaxableIncome,
                'source' => OfficialSourceRegistry::get('estg_21_vuv'),
            ];
        }

        if($firstTaxEffectRow) {
            array_push($rows, ...$this->taxBreakdownRows($scenario, $firstTaxEffectRow));
        }

        array_push($rows, ...$this->saleAndNetWorthBreakdownRows($scenario, $purchasePrice, $yearlyRows, $metrics));
        array_push($rows, ...$this->equityBreakdownRows($scenario, $metrics));

        $rows[] = [
            'group' => 'Kennzahlen',
            'label' => 'Hebel-/Effizienz',
            'formula' => 'Netto-Vermögenseffekt / Anfangsschuld',
            'values' => $metrics['netWorthEffect'].' / '.$initialDebt,
            'result' => $metrics['leverageEfficiency'],
            'format' => 'percent',
        ];
        $rows[] = [
            'group' => 'Kennzahlen',
            'label' => 'Barwert-Hebel',
            'formula' => 'NPV / Anfangsschuld',
            'values' => $metrics['npv'].' / '.$initialDebt,
            'result' => $metrics['npvLeverageEfficiency'],
            'format' => 'percent',
        ];

        return $rows;
    }

    /**
     * @param YearlyCalculationResult[] $yearlyRows
     */
    private function saleAndNetWorthBreakdownRows(RealEstateInvestmentScenario $scenario, float $purchasePrice, array $yearlyRows, array $metrics): array
    {
        if(!$yearlyRows) {
            return [];
        }

        $saleRow = $yearlyRows[array_key_last($yearlyRows)];
        $yearsSincePurchase = max($scenario->property->saleYear - $scenario->property->purchaseYear, 0);
        $sellingCosts = $saleRow->salePrice * $scenario->sale->sellingCostsRate + $scenario->sale->sellingCostsAmount;
        $cashflowAfterTaxTotal = array_sum(array_map(static fn(YearlyCalculationResult $row): float => $row->netCashflowAfterTax, $yearlyRows));
        $cashflowIncludingSaleTotal = array_sum(array_map(static fn(YearlyCalculationResult $row): float => $row->netCashflowIncludingSale, $yearlyRows));

        return [[
            'group' => 'Verkauf & Nettoeffekt',
            'label' => 'Verkaufspreis '.$saleRow->year,
            'formula' => 'Gesamtkaufpreis × (1 + Wertsteigerung)^Jahre seit Kauf',
            'values' => $purchasePrice.' × (1 + '.$this->percent($scenario->sale->annualValueIncrease).')^'.$yearsSincePurchase,
            'result' => $saleRow->salePrice,
        ], [
            'group' => 'Verkauf & Nettoeffekt',
            'label' => 'Verkaufskosten '.$saleRow->year,
            'formula' => 'Verkaufspreis × Verkaufskosten % + Verkaufskosten absolut',
            'values' => $saleRow->salePrice.' × '.$this->percent($scenario->sale->sellingCostsRate).' + '.$scenario->sale->sellingCostsAmount,
            'result' => $sellingCosts,
        ], [
            'group' => 'Verkauf & Nettoeffekt',
            'label' => 'Erlös nach Schuld Verkaufsjahr',
            'formula' => 'Verkaufspreis - Restschuld - Verkaufskosten - Vorfälligkeit',
            'values' => $saleRow->salePrice.' - '.$saleRow->remainingDebt.' - '.$sellingCosts.' - '.$scenario->sale->prepaymentPenaltyAmount,
            'result' => $saleRow->saleProceedsAfterDebt,
        ], [
            'group' => 'Verkauf & Nettoeffekt',
            'label' => 'CF inkl. Verkauf '.$saleRow->year,
            'formula' => 'CF nach Steuer im Verkaufsjahr + Erlös nach Schuld',
            'values' => $saleRow->netCashflowAfterTax.' + '.$saleRow->saleProceedsAfterDebt,
            'result' => $saleRow->netCashflowIncludingSale,
        ], [
            'group' => 'Verkauf & Nettoeffekt',
            'label' => 'Summe CF nach Steuer',
            'formula' => 'Summe CF nach Steuer aller Jahre ohne Verkaufserlös',
            'values' => 'Summe aus Jahresübersicht',
            'result' => $cashflowAfterTaxTotal,
        ], [
            'group' => 'Verkauf & Nettoeffekt',
            'label' => 'Netto-Vermögenseffekt',
            'formula' => 'Summe CF inkl. Verkauf = Summe CF nach Steuer + Erlös nach Schuld',
            'values' => $cashflowAfterTaxTotal.' + '.$saleRow->saleProceedsAfterDebt,
            'result' => $metrics['netWorthEffect'],
        ], [
            'group' => 'Verkauf & Nettoeffekt',
            'label' => 'Kontrollsumme CF inkl. Verkauf',
            'formula' => 'Summe der Tabellenspalte CF inkl. Verkauf',
            'values' => 'Summe aus Jahresübersicht',
            'result' => $cashflowIncludingSaleTotal,
        ]];
    }

    private function equityBreakdownRows(RealEstateInvestmentScenario $scenario, array $metrics): array
    {
        if($metrics['totalEquityInvested'] <= 0) {
            return [];
        }

        return [[
            'group' => 'Eigenkapital',
            'label' => 'Anfangs-Eigenkapital',
            'formula' => 'vom Anleger zu Beginn bereitgestellte eigene Liquidität',
            'values' => (string)$scenario->settings->initialEquityAmount,
            'result' => $metrics['initialEquityAmount'],
        ], [
            'group' => 'Eigenkapital',
            'label' => 'Kapitalnachschüsse',
            'formula' => 'Summe negativer CF nach Steuer; nicht finanzierte Bauzeitzinsen sind darin enthalten',
            'values' => 'Summe abs(min(CF nach Steuer, 0))',
            'result' => $metrics['equityCapitalCalls'],
        ], [
            'group' => 'Eigenkapital',
            'label' => 'eingesetztes Eigenkapital',
            'formula' => 'Anfangs-Eigenkapital + Kapitalnachschüsse',
            'values' => $metrics['initialEquityAmount'].' + '.$metrics['equityCapitalCalls'],
            'result' => $metrics['totalEquityInvested'],
        ], [
            'group' => 'Eigenkapital',
            'label' => 'Netto-Endvermögen',
            'formula' => 'positive CF nach Steuer + Verkaufserlös nach Schuld',
            'values' => 'Summe positiver Rückflüsse',
            'result' => $metrics['equityDistributions'],
        ], [
            'group' => 'Eigenkapital',
            'label' => 'Netto-Vermögensgewinn nach EK',
            'formula' => 'Netto-Endvermögen - eingesetztes Eigenkapital',
            'values' => $metrics['equityDistributions'].' - '.$metrics['totalEquityInvested'],
            'result' => $metrics['equityNetGain'],
        ], [
            'group' => 'Eigenkapital',
            'label' => 'EK-Multiple',
            'formula' => 'Netto-Endvermögen / eingesetztes Eigenkapital',
            'values' => $metrics['equityDistributions'].' / '.$metrics['totalEquityInvested'],
            'result' => $metrics['equityMultiple'],
            'format' => 'decimal',
        ], [
            'group' => 'Eigenkapital',
            'label' => 'EK-Rendite gesamt',
            'formula' => 'Netto-Vermögensgewinn nach EK / eingesetztes Eigenkapital',
            'values' => $metrics['equityNetGain'].' / '.$metrics['totalEquityInvested'],
            'result' => $metrics['equityTotalReturn'],
            'format' => 'percent',
        ], [
            'group' => 'Eigenkapital',
            'label' => 'annualisierte EK-Rendite',
            'formula' => 'EK-Multiple^(1 / Haltedauer Jahre) - 1',
            'values' => (string)$metrics['equityMultiple'],
            'result' => $metrics['equityAnnualizedReturn'],
            'format' => 'percent',
        ]];
    }

    private function rentBreakdownRow(RealEstateInvestmentScenario $scenario, YearlyCalculationResult $row): array
    {
        $parkingRent = array_sum(array_map(static fn($unit): float => $unit->monthlyRent, $scenario->parkingUnits));
        $monthlyRent = $scenario->rent->apartmentMonthlyRent + $parkingRent;
        $factorYear = $scenario->rent->increaseStartYear > 0
            ? max($row->year - $scenario->rent->increaseStartYear, 0)
            : $this->yearsSinceFirstFullYear($row->year, $scenario->property->rentStartYear, $scenario->property->rentStartMonth);
        $steps = intdiv(max($factorYear, 0), $scenario->rent->increaseEveryYears);
        $factor = (1 + $scenario->rent->annualIncrease) ** $steps;

        return [
            'group' => 'Miete',
            'label' => 'Miete '.$row->year,
            'formula' => '(Monatsmiete × Monate × Mietsteigerungsfaktor + sonstige Einnahmen) × (1 - Leerstand)',
            'values' => '('.$monthlyRent.' × '.$row->rentedMonths.' × '.$factor.' + '.$scenario->rent->otherAnnualIncome.') × (1 - '.$this->percent($scenario->rent->vacancyRate).')',
            'result' => $row->rent,
        ];
    }

    private function taxBreakdownRows(RealEstateInvestmentScenario $scenario, YearlyCalculationResult $row): array
    {
        $yearIndex = max($row->year - $scenario->property->purchaseYear, 0);
        $rows = [[
            'group' => 'Steuer',
            'label' => 'zvE vor Investition '.$row->year,
            'formula' => 'Start-zvE × (1 + zvE-Steigerung)^Jahrindex',
            'values' => $scenario->tax->taxableIncomeBeforeInvestment.' × (1 + '.$this->percent($scenario->tax->taxableIncomeAnnualIncrease).')^'.$yearIndex,
            'result' => $row->taxableIncomeBefore,
        ], [
            'group' => 'Steuer',
            'label' => 'zvE nach Investition '.$row->year,
            'formula' => 'zvE vor Investition + Einkünfte VuV',
            'values' => $row->taxableIncomeBefore.' + '.$row->rentalTaxableIncome,
            'result' => $row->taxableIncomeAfter,
        ]];

        if($scenario->tax->calculationMethod !== TaxInput::CALCULATION_METHOD_SECTION_32A) {
            $taxableLoss = $row->rentalTaxableIncome < 0 ? -$row->rentalTaxableIncome : 0.0;

            return array_merge($rows, [[
                'group' => 'Steuer',
                'label' => 'ESt vor Investition '.$row->year,
                'formula' => 'zvE vor Investition × Grenzsteuersatz',
                'values' => $row->taxableIncomeBefore.' × '.$this->percent($scenario->tax->marginalTaxRate),
                'result' => $row->incomeTaxBefore,
            ], [
                'group' => 'Steuer',
                'label' => 'ESt nach Investition '.$row->year,
                'formula' => 'zvE nach Investition × Grenzsteuersatz',
                'values' => $row->taxableIncomeAfter.' × '.$this->percent($scenario->tax->marginalTaxRate),
                'result' => $row->incomeTaxAfter,
            ], [
                'group' => 'Steuer',
                'label' => 'Steuerwirkung '.$row->year,
                'formula' => 'ESt vor Investition - ESt nach Investition',
                'values' => $row->incomeTaxBefore.' - '.$row->incomeTaxAfter,
                'result' => $row->taxEffect,
            ], [
                'group' => 'Steuer',
                'label' => 'Steuerersparnis per Grenzsteuersatz '.$row->year,
                'formula' => 'steuerlicher Verlust × Grenzsteuersatz',
                'values' => $taxableLoss.' × '.$this->percent($scenario->tax->marginalTaxRate),
                'result' => $taxableLoss * $scenario->tax->marginalTaxRate,
            ]]);
        }

        return array_merge($rows, [[
            'group' => 'Steuer',
            'label' => 'ESt vor Investition '.$row->year,
            'formula' => 'Einkommensteuertarif nach §32a EStG',
            'values' => 'zvE '.$row->taxableIncomeBefore.', '.$scenario->tax->assessmentType.', '.$scenario->tax->taxYear,
            'result' => $row->tariffIncomeTaxBefore,
            'source' => OfficialSourceRegistry::get('estg_32a'),
        ], [
            'group' => 'Steuer',
            'label' => 'ESt nach Investition '.$row->year,
            'formula' => 'Einkommensteuertarif nach §32a EStG',
            'values' => 'zvE '.$row->taxableIncomeAfter.', '.$scenario->tax->assessmentType.', '.$scenario->tax->taxYear,
            'result' => $row->tariffIncomeTaxAfter,
            'source' => OfficialSourceRegistry::get('estg_32a'),
        ], [
            'group' => 'Steuer',
            'label' => 'Soli vor Investition '.$row->year,
            'formula' => 'automatisch: min(ESt × 5,5 %, max(ESt - Freigrenze, 0) × 11,9 %)',
            'values' => $row->tariffIncomeTaxBefore.', Freigrenze '.$this->solidarityFreeLimit($scenario->tax->assessmentType),
            'result' => $row->solidaritySurchargeBefore,
            'source' => OfficialSourceRegistry::get('solzg_2026'),
        ], [
            'group' => 'Steuer',
            'label' => 'Soli nach Investition '.$row->year,
            'formula' => 'automatisch: min(ESt × 5,5 %, max(ESt - Freigrenze, 0) × 11,9 %)',
            'values' => $row->tariffIncomeTaxAfter.', Freigrenze '.$this->solidarityFreeLimit($scenario->tax->assessmentType),
            'result' => $row->solidaritySurchargeAfter,
            'source' => OfficialSourceRegistry::get('solzg_2026'),
        ], [
            'group' => 'Steuer',
            'label' => 'Kirchensteuer vor Investition '.$row->year,
            'formula' => 'ESt × Kirchensteuersatz',
            'values' => $row->tariffIncomeTaxBefore.' × '.$this->churchTaxStateLabel($scenario->tax->churchTaxState),
            'result' => $row->churchTaxBefore,
            'source' => OfficialSourceRegistry::get('kirchensteuer'),
        ], [
            'group' => 'Steuer',
            'label' => 'Kirchensteuer nach Investition '.$row->year,
            'formula' => 'ESt × Kirchensteuersatz',
            'values' => $row->tariffIncomeTaxAfter.' × '.$this->churchTaxStateLabel($scenario->tax->churchTaxState),
            'result' => $row->churchTaxAfter,
            'source' => OfficialSourceRegistry::get('kirchensteuer'),
        ], [
            'group' => 'Steuer',
            'label' => 'Steuer gesamt vor Investition '.$row->year,
            'formula' => 'ESt + Soli + Kirchensteuer',
            'values' => $row->tariffIncomeTaxBefore.' + '.$row->solidaritySurchargeBefore.' + '.$row->churchTaxBefore,
            'result' => $row->incomeTaxBefore,
        ], [
            'group' => 'Steuer',
            'label' => 'Steuer gesamt nach Investition '.$row->year,
            'formula' => 'ESt + Soli + Kirchensteuer',
            'values' => $row->tariffIncomeTaxAfter.' + '.$row->solidaritySurchargeAfter.' + '.$row->churchTaxAfter,
            'result' => $row->incomeTaxAfter,
        ], [
            'group' => 'Steuer',
            'label' => 'Steuerwirkung '.$row->year,
            'formula' => 'Steuer gesamt vor Investition - Steuer gesamt nach Investition',
            'values' => $row->incomeTaxBefore.' - '.$row->incomeTaxAfter,
            'result' => $row->taxEffect,
        ]]);
    }

    private function taxableIncomeBeforeInvestmentForYear(TaxInput $tax, int $year, int $startYear): float
    {
        $yearIndex = max($year - $startYear, 0);
        return $tax->taxableIncomeBeforeInvestment * ((1 + $tax->taxableIncomeAnnualIncrease) ** $yearIndex);
    }

    private function depreciationBreakdownRows(RealEstateInvestmentScenario $scenario, array $special7b): array
    {
        $year = $scenario->depreciation->startYear;
        $months = $this->activeMonths($year, $scenario->depreciation->startYear, $scenario->depreciation->startMonth, $scenario->property->saleYear, $scenario->property->saleMonth);
        $buildingBasis = max($scenario->depreciation->buildingBasis, 0);
        $linear = $buildingBasis * $scenario->depreciation->linearRate * $months / 12;
        $degressive = $scenario->depreciation->degressiveActive ? $buildingBasis * $scenario->depreciation->degressiveRate * $months / 12 : 0.0;
        $buildingDepreciation = $scenario->depreciation->degressiveActive && $scenario->depreciation->autoSwitchToLinear
            ? max($degressive, $linear)
            : ($scenario->depreciation->degressiveActive ? $degressive : $linear);
        $special = $scenario->depreciation->special7bActive && $scenario->depreciation->special7bYears > 0
            ? max($special7b['assessmentBasis'], 0) * $scenario->depreciation->special7bRate
            : 0.0;
        $furniture = $scenario->depreciation->furnitureBasis * $scenario->depreciation->furnitureRate * $months / 12;
        $parking = $this->parkingDepreciationForMonths($scenario->depreciation->parkingDepreciationItems, $months);
        $total = min($buildingDepreciation + $special, $buildingBasis) + $furniture + $parking;

        return [
            [
                'group' => 'AfA',
                'label' => 'AfA degressiv '.$year,
                'formula' => 'Gebäude-AfA-Basis × degressiver AfA-Satz × AfA-Monate / 12',
                'values' => $buildingBasis.' × '.$this->percent($scenario->depreciation->degressiveRate).' × '.$months.' / 12',
                'result' => $degressive,
            ],
            [
                'group' => 'AfA',
                'label' => 'AfA linear '.$year,
                'formula' => 'Gebäude-AfA-Basis × linearer AfA-Satz × AfA-Monate / 12',
                'values' => $buildingBasis.' × '.$this->percent($scenario->depreciation->linearRate).' × '.$months.' / 12',
                'result' => $linear,
            ],
            [
                'group' => 'AfA',
                'label' => 'AfA §7b '.$year,
                'formula' => '§7b-Bemessungsgrundlage × §7b-Satz, ohne zeitanteilige Kürzung',
                'values' => $special7b['assessmentBasis'].' × '.$this->percent($scenario->depreciation->special7bRate),
                'result' => $special,
            ],
            [
                'group' => 'AfA',
                'label' => 'AfA Möbel/Küche '.$year,
                'formula' => 'Möbel/Küche-AfA-Basis × AfA-Satz × AfA-Monate / 12',
                'values' => $scenario->depreciation->furnitureBasis.' × '.$this->percent($scenario->depreciation->furnitureRate).' × '.$months.' / 12',
                'result' => $furniture,
            ],
            [
                'group' => 'AfA',
                'label' => 'AfA Stellplatz '.$year,
                'formula' => 'Σ Stellplatz-AfA-Basis × jeweiliger AfA-Satz × AfA-Monate / 12',
                'values' => $this->parkingDepreciationValues($scenario->depreciation->parkingDepreciationItems, $months),
                'result' => $parking,
            ],
            [
                'group' => 'AfA',
                'label' => 'AfA gesamt '.$year,
                'formula' => 'verwendete Gebäude-AfA + §7b + Möbel/Küche + Stellplatz',
                'values' => $buildingDepreciation.' + '.$special.' + '.$furniture.' + '.$parking,
                'result' => $total,
            ],
        ];
    }

    private function parkingDepreciationForMonths(array $items, int $months): float
    {
        $total = 0.0;
        foreach($items as $item) {
            $total += max((float)($item['basis'] ?? 0), 0) * max((float)($item['rate'] ?? 0), 0) * $months / 12;
        }
        return $total;
    }

    private function parkingDepreciationValues(array $items, int $months): string
    {
        if($items === []) {
            return 'keine Stellplatz-AfA-Basis';
        }

        return implode(' + ', array_map(fn(array $item): string => ($item['label'] ?? 'Stellplatz').': '.((float)($item['basis'] ?? 0)).' × '.$this->percent((float)($item['rate'] ?? 0)).' × '.$months.' / 12', $items));
    }

    private function percent(float $rate): string
    {
        return round($rate * 100, 4).' %';
    }

    private function solidarityFreeLimit(string $assessmentType): float
    {
        return $assessmentType === 'splitting' ? 40700.0 : 20350.0;
    }

    private function churchTaxStateLabel(string $state): string
    {
        $names = [
            'BY' => 'Bayern',
            'BW' => 'Baden-Württemberg',
            'BE' => 'Berlin',
            'BB' => 'Brandenburg',
            'HB' => 'Bremen',
            'HH' => 'Hamburg',
            'HE' => 'Hessen',
            'MV' => 'Mecklenburg-Vorpommern',
            'NI' => 'Niedersachsen',
            'NW' => 'Nordrhein-Westfalen',
            'RP' => 'Rheinland-Pfalz',
            'SL' => 'Saarland',
            'SN' => 'Sachsen',
            'ST' => 'Sachsen-Anhalt',
            'SH' => 'Schleswig-Holstein',
            'TH' => 'Thüringen',
        ];
        $rate = in_array($state, ['BY', 'BW'], true) ? '8 %' : '9 %';

        return ($names[$state] ?? $state).' '.$rate;
    }

    private function formulas(): array
    {
        return [
            'Netto-Vermögenseffekt = Summe Netto-Cashflows nach Steuer + Verkaufserlös nach Restschuld',
            'Hebel-/Effizienz = Netto-Vermögenseffekt / Anfangsschuld',
            'NPV = Σ CF_t / (1 + r)^t',
            'Barwert-Hebel-/Effizienz = NPV / Anfangsschuld',
            'FV konservativ = Σ CF_t × (1 + r)^(T - t)',
            'FV liquiditätsorientiert = Σ positive CF_t × (1 + r)^(T - t) + Σ negative CF_t',
            'eingesetztes Eigenkapital = Anfangs-Eigenkapital + Kapitalnachschüsse aus negativen CF nach Steuer',
            'EK-Multiple = Netto-Endvermögen / eingesetztes Eigenkapital',
            'EK-Rendite gesamt = Netto-Vermögensgewinn nach EK / eingesetztes Eigenkapital',
            'Miete = (Monatsmiete × Monate × Mietsteigerungsfaktor + sonstige Einnahmen) × (1 - Leerstand)',
            '§7b-Bemessungsgrundlage = min(begünstigte Anschaffungs-/Herstellungskosten, Wohnfläche × Förderhöchstgrenze je m²)',
            'Werbungskosten ohne AfA = abzugsfähige laufende Kosten + Darlehenszinsen + BZZ/Kreditkosten WK',
            'steuerliche Abzüge gesamt = Werbungskosten ohne AfA + AfA gesamt',
            'Einkünfte VuV = Miete - Werbungskosten ohne AfA - AfA gesamt',
            'DSCR = Reinertrag / Kapitaldienst',
            'Debt Yield = Reinertrag / Darlehensbetrag',
        ];
    }
}
