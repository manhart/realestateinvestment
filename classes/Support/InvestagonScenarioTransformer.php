<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Support;

final class InvestagonScenarioTransformer
{
    public function transform(array $reference, string $scenarioName, string $scenarioGroup): array
    {
        $investment = (array)($reference['investment_information'] ?? []);
        $object = (array)($reference['object'] ?? []);
        $assumptions = (array)($reference['assumptions'] ?? []);
        $allocation = (array)($assumptions['purchase_price_allocation'] ?? []);
        $apartmentAllocation = (array)($allocation['apartment'] ?? []);
        $parkingAllocation = (array)($allocation['parking'] ?? []);
        $depreciation = (array)($reference['depreciation'] ?? []);
        $depreciationBasis = (array)($depreciation['depreciation_basis'] ?? []);
        $purchaseCosts = (array)($investment['purchase_costs'] ?? []);
        $address = (array)($reference['meta']['address'] ?? []);
        $totalDebt = (float)($investment['debt_capital']['amount'] ?? 0);
        $parkingTotal = (float)($investment['purchase_price_parking'] ?? $object['monthly_rent_components']['parking'] ?? 0);
        $parkingCount = $parkingTotal > 0 ? 2 : 0;
        $parkingPurchasePrice = $parkingCount > 0 ? $parkingTotal / $parkingCount : 0.0;
        $parkingRent = $parkingCount > 0 ? (float)($object['monthly_rent_components']['parking'] ?? 0) / $parkingCount : 0.0;
        $rentStart = $this->firstNonZeroMonth((array)($reference['detailed_calculation_table']['income']['rentable_months'] ?? []));
        $saleMonth = $this->lastNonZeroMonth((array)($reference['detailed_calculation_table']['income']['rentable_months'] ?? []));
        $startYear = (int)($reference['detailed_calculation_table']['years'][0] ?? 2026);
        $saleYear = (int)array_slice((array)($reference['detailed_calculation_table']['years'] ?? [$startYear + 10]), -1)[0];

        return [
            'scenarioName' => $scenarioName,
            'scenarioGroup' => $scenarioGroup,
            'property' => [
                'name' => $scenarioName,
                'address' => $this->address($address),
                'state' => 'BY',
                'purchaseYear' => $startYear,
                'purchaseMonth' => $rentStart,
                'completionYear' => $startYear,
                'completionMonth' => $rentStart,
                'rentStartYear' => $startYear,
                'rentStartMonth' => $rentStart,
                'saleYear' => $saleYear,
                'saleMonth' => $saleMonth,
                'livingArea' => (float)($object['living_area_m2'] ?? 0),
                'apartmentPurchasePrice' => (float)($investment['purchase_price_apartment'] ?? 0),
                'otherPurchasePrice' => 0,
                'furniturePurchasePrice' => 0,
                'landSharePercent' => (float)($apartmentAllocation['land_share_percent'] ?? 0),
                'buildingSharePercent' => (float)($apartmentAllocation['building_share_percent'] ?? 100),
                'energyClass' => (string)($reference['meta']['energy_efficiency_class'] ?? ''),
                'newBuilding' => (bool)($reference['meta']['new_build'] ?? true),
                'furnished' => false,
            ],
            'parkingUnits' => $this->parkingUnits($parkingCount, $parkingPurchasePrice, $parkingRent, $parkingAllocation, $startYear, $rentStart),
            'rent' => [
                'apartmentMonthlyRent' => (float)($object['monthly_rent_components']['apartment'] ?? $object['monthly_cold_rent'] ?? 0),
                'annualIncreasePercent' => (float)($assumptions['rent_increase_per_year_percent'] ?? 0),
                'increaseEveryYears' => 1,
                'vacancyPercent' => (float)($assumptions['vacancy_rate_percent'] ?? 0),
                'increaseStartYear' => $startYear,
                'monthlyPartialYears' => true,
                'otherAnnualIncome' => 0,
            ],
            'expenses' => [
                'nonRecoverableHausgeldMonthly' => (float)($object['monthly_house_fee'] ?? 0),
                'maintenanceReserveMonthly' => 0,
                'managementMonthly' => 0,
                'servicePoolMonthly' => 0,
                'furnitureReserveMonthly' => 0,
                'otherMonthlyCosts' => 0,
                'annualIncreasePercent' => 0,
                'increaseEveryYears' => 1,
            ],
            'acquisitionCosts' => [
                'realEstateTransferTaxPercent' => (float)($purchaseCosts['real_estate_transfer_tax']['rate_percent'] ?? 0),
                'notaryPercent' => (float)($purchaseCosts['notary_legal_costs']['rate_percent'] ?? 0),
                'landRegisterPurchasePercent' => (float)($purchaseCosts['land_register_purchase_contract']['rate_percent'] ?? 0),
                'landRegisterLienPercent' => $this->ratePercent((float)($depreciationBasis['financing_purchase_costs_as_income_related_expenses'] ?? 0), $totalDebt),
                'brokerPercent' => 0,
                'otherAcquisitionCosts' => 0,
                'otherFinancingCosts' => 0,
                'notaryLandRegisterTaxTreatment' => 'afa_basis',
                'financingCostsDeductible' => true,
            ],
            'settings' => [
                'initialEquityAmount' => (float)($investment['equity_investment'] ?? 0),
                'discountRatePercent' => 0,
                'autoSpecialRepaymentMode' => 'none',
            ],
            'depreciation' => [
                'startYear' => $startYear,
                'startMonth' => $rentStart,
                'buildingBasis' => 0,
                'buildingBasisOverrideEnabled' => false,
                'degressiveActive' => (bool)($depreciation['degressive_depreciation_enabled'] ?? true),
                'degressiveRatePercent' => (float)($depreciation['degressive_depreciation_percent'] ?? 5),
                'linearRatePercent' => (float)($depreciation['linear_depreciation_percent'] ?? 3),
                'autoSwitchToLinear' => false,
                'special7bActive' => (bool)($depreciation['section_7b_depreciation']['enabled'] ?? true),
                'special7bApplicationDate' => sprintf('%04d-%02d-01', $startYear, $rentStart),
                'special7bArea' => (float)($object['living_area_m2'] ?? 0),
                'special7bConstructionCostLimitPerSqm' => 5200,
                'special7bActualConstructionCostPerSqm' => 0,
                'special7bLimitPerSqm' => 4000,
                'special7bBasis' => (float)($depreciation['section_7b_depreciation']['annual_amount'] ?? 0) / 0.05,
                'special7bRatePercent' => (float)($depreciation['section_7b_depreciation']['rate_percent'] ?? 5),
                'special7bYears' => 4,
                'special7bReducesBookValueImmediately' => false,
                'furnitureBasis' => 0,
                'furnitureRatePercent' => 10,
                'parkingBasis' => 0,
                'parkingRatePercent' => 3,
            ],
            'tax' => [
                'calculationMethod' => 'section_32a',
                'taxableIncomeBeforeInvestment' => (float)($reference['tax_effect_table'][0]['taxable_income_before'] ?? 0),
                'taxableIncomeAnnualIncreasePercent' => 0,
                'marginalTaxRatePercent' => 42,
                'assessmentType' => 'splitting',
                'taxYear' => 2026,
                'churchTax' => false,
                'churchTaxState' => 'BY',
            ],
            'sale' => [
                'annualValueIncreasePercent' => (float)($assumptions['value_increase_per_year_percent'] ?? 0),
                'parkingAnnualValueIncreasePercent' => (float)($assumptions['value_increase_per_year_percent'] ?? 0),
                'includeParkingInSalePrice' => true,
                'sellingCostsPercent' => 0,
                'prepaymentPenaltyAmount' => 0,
                'taxFreeSale' => true,
            ],
            'constructionInterest' => [
                'yearlyEntries' => [],
            ],
            'loans' => $this->loans((array)($reference['loans'] ?? []), $startYear, $rentStart),
        ];
    }

    private function parkingUnits(int $count, float $purchasePrice, float $monthlyRent, array $allocation, int $startYear, int $startMonth): array
    {
        $units = [];
        for($i = 1; $i <= $count; $i++) {
            $units[] = [
                'label' => 'Stellplatz überdacht '.$i,
                'purchasePrice' => $purchasePrice,
                'monthlyRent' => $monthlyRent,
                'buildingSharePercent' => (float)($allocation['building_share_percent'] ?? 100),
                'landSharePercent' => (float)($allocation['land_share_percent'] ?? 0),
                'depreciationMode' => 'building_basis',
                'depreciationRatePercent' => 3,
                'depreciationStartYear' => $startYear,
                'depreciationStartMonth' => $startMonth,
                'includedInPurchasePrice' => true,
            ];
        }
        return $units;
    }

    private function loans(array $loans, int $startYear, int $startMonth): array
    {
        return array_map(static fn(array $loan, int $index): array => [
            'name' => (string)($loan['name'] ?? 'Darlehen'),
            'priority' => $index + 1,
            'principal' => (float)($loan['amount'] ?? 0),
            'interestRatePercent' => (float)($loan['interest_rate_percent'] ?? 0),
            'initialRepaymentPercent' => (float)($loan['initial_repayment_rate_percent'] ?? 0),
            'startYear' => $startYear,
            'startMonth' => $startMonth,
            'interestOnlyYears' => 0,
            'repaymentRateAfterInterestOnlyPercent' => (float)($loan['initial_repayment_rate_percent'] ?? 0),
            'fixedInterestYears' => 0,
            'followUpInterestRatePercent' => (float)($loan['interest_rate_percent'] ?? 0),
            'grantAmount' => (float)($loan['repayment_grant'] ?? 0),
            'grantYear' => 0,
            'constantAnnuity' => true,
            'redeemOnSale' => true,
            'specialRepaymentAmount' => 0,
            'specialRepaymentYear' => 0,
        ], $loans, array_keys($loans));
    }

    private function firstNonZeroMonth(array $monthlyCounts): int
    {
        foreach($monthlyCounts as $months) {
            $months = (int)$months;
            if($months > 0 && $months < 12) {
                return 13 - $months;
            }
        }
        return 1;
    }

    private function lastNonZeroMonth(array $monthlyCounts): int
    {
        $months = (int)end($monthlyCounts);
        return $months > 0 && $months < 12 ? $months : 12;
    }

    private function ratePercent(float $amount, float $base): float
    {
        return $base > 0 ? $amount / $base * 100 : 0.0;
    }

    private function address(array $address): string
    {
        return trim(implode(', ', array_filter([
            $address['street'] ?? '',
            trim((string)($address['postal_code'] ?? '').' '.(string)($address['city'] ?? '')),
        ])));
    }
}
