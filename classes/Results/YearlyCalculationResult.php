<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Results;

final class YearlyCalculationResult
{
    public function __construct(
        public int $year,
        public int $rentedMonths = 0,
        public float $rent = 0.0,
        public float $expenses = 0.0,
        public float $operatingCashflow = 0.0,
        public float $interest = 0.0,
        public float $repayment = 0.0,
        public float $annuity = 0.0,
        public float $remainingDebt = 0.0,
        public float $depreciationDegressive = 0.0,
        public float $depreciationLinear = 0.0,
        public float $depreciation7b = 0.0,
        public float $depreciationFurniture = 0.0,
        public float $depreciationParking = 0.0,
        public float $depreciationTotal = 0.0,
        public float $deductibleOperatingExpenses = 0.0,
        public float $deductibleExpenses = 0.0,
        public float $advertisingCostsWithoutDepreciation = 0.0,
        public float $taxDeductionsTotal = 0.0,
        public float $rentalTaxableIncome = 0.0,
        public float $taxableIncomeBefore = 0.0,
        public float $taxableIncomeAfter = 0.0,
        public float $tariffIncomeTaxBefore = 0.0,
        public float $tariffIncomeTaxAfter = 0.0,
        public float $solidaritySurchargeBefore = 0.0,
        public float $solidaritySurchargeAfter = 0.0,
        public float $churchTaxBefore = 0.0,
        public float $churchTaxAfter = 0.0,
        public float $incomeTaxBefore = 0.0,
        public float $incomeTaxAfter = 0.0,
        public float $taxEffect = 0.0,
        public float $effectiveTaxRate = 0.0,
        public float $netCashflowBeforeTax = 0.0,
        public float $netCashflowAfterTax = 0.0,
        public float $salePrice = 0.0,
        public float $saleProceedsAfterDebt = 0.0,
        public float $netCashflowIncludingSale = 0.0,
        public float $constructionInterest = 0.0,
        public float $oneTimeCashCosts = 0.0,
        public float $debtYield = 0.0,
        public float $dscr = 0.0,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
