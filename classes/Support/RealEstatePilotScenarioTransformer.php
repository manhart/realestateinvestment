<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Support;

final class RealEstatePilotScenarioTransformer
{
    public function transform(array $reference, string $scenarioName, string $scenarioGroup): array
    {
        $apartment = $this->firstUnitOfType($reference, 'Wohnung');
        $parkingUnits = array_values(array_filter((array)($reference['units'] ?? []), static fn(array $unit): bool => ($unit['type'] ?? '') === 'Stellplatz'));
        [$purchaseYear, $purchaseMonth] = $this->yearMonth((string)($reference['project']['purchase_date_month'] ?? '2026-01'));
        [$completionYear, $completionMonth] = $this->yearMonth((string)($apartment['completion_month'] ?? $reference['project']['purchase_date_month'] ?? '2026-01'));
        [$rentStartYear, $rentStartMonth] = $this->yearMonth((string)($apartment['usage_from_month'] ?? $apartment['completion_month'] ?? '2026-01'));
        $purchasePrice = (float)($reference['purchase_prices']['total_purchase_price'] ?? 0);
        $observationYears = max((int)($reference['project']['observation_period_years'] ?? 10), 1);
        $buildingDepreciation = $this->depreciationRow($reference, 'Wohnung', 'Degressive AfA');
        $special7b = $this->depreciationRow($reference, 'Wohnung', 'Sonderabschreibung Mietwohnungsneubau');

        return [
            'scenarioName' => $scenarioName,
            'scenarioGroup' => $scenarioGroup,
            'property' => [
                'name' => $scenarioName,
                'address' => $this->address((array)($apartment['address'] ?? $reference['project']['property_address'] ?? [])),
                'state' => 'SN',
                'purchaseYear' => $purchaseYear,
                'purchaseMonth' => $purchaseMonth,
                'completionYear' => $completionYear,
                'completionMonth' => $completionMonth,
                'rentStartYear' => $rentStartYear,
                'rentStartMonth' => $rentStartMonth,
                'saleYear' => $purchaseYear + $observationYears,
                'saleMonth' => 12,
                'livingArea' => (float)($apartment['area_sqm'] ?? 0),
                'apartmentPurchasePrice' => (float)($apartment['purchase_price_breakdown']['total'] ?? 0),
                'otherPurchasePrice' => 0,
                'furniturePurchasePrice' => 0,
                'landSharePercent' => (float)($apartment['purchase_price_breakdown']['land_costs']['share_percent'] ?? 0),
                'buildingSharePercent' => (float)($apartment['purchase_price_breakdown']['construction_costs']['share_percent'] ?? 100),
                'newBuilding' => true,
            ],
            'parkingUnits' => array_map(fn(array $unit): array => $this->parkingUnit($reference, $unit), $parkingUnits),
            'rent' => [
                'apartmentMonthlyRent' => (float)($apartment['rent']['initial_cold_rent_month'] ?? 0),
                'annualIncreasePercent' => 0,
                'increaseEveryYears' => 1,
                'vacancyPercent' => 0,
            ],
            'expenses' => [
                'nonRecoverableHausgeldMonthly' => (float)($apartment['running_costs']['property_management_WEG_month'] ?? 0),
                'maintenanceReserveMonthly' => (float)($apartment['running_costs']['maintenance_reserve_month'] ?? 0),
                'managementMonthly' => (float)($apartment['running_costs']['property_management_SE_month'] ?? 0),
                'annualIncreasePercent' => 0,
                'increaseEveryYears' => 1,
            ],
            'acquisitionCosts' => $this->acquisitionCosts($reference, $purchasePrice),
            'constructionInterest' => [
                'yearlyEntries' => [],
            ],
            'loans' => array_map(fn(array $loan, int $index): array => $this->loan($loan, $index + 1), (array)($reference['loans'] ?? []), array_keys((array)($reference['loans'] ?? []))),
            'depreciation' => [
                'startYear' => $completionYear,
                'startMonth' => $completionMonth,
                'buildingBasis' => (float)($buildingDepreciation['basis_including_acquisition_costs'] ?? 0),
                'degressiveActive' => true,
                'degressiveRatePercent' => (float)($buildingDepreciation['rate_percent'] ?? 5),
                'linearRatePercent' => 2.38,
                'autoSwitchToLinear' => true,
                'special7bActive' => true,
                'special7bApplicationDate' => sprintf('%04d-%02d-01', $purchaseYear, $purchaseMonth),
                'special7bArea' => (float)($apartment['area_sqm'] ?? 0),
                'special7bConstructionCostLimitPerSqm' => 5200,
                'special7bLimitPerSqm' => 4000,
                'special7bRatePercent' => (float)($special7b['rate_percent'] ?? 5),
                'special7bYears' => 4,
                'special7bBasis' => (float)($special7b['basis_including_acquisition_costs'] ?? 0),
                'furnitureBasis' => 0,
                'furnitureRatePercent' => 10,
                'parkingBasis' => 0,
                'parkingRatePercent' => 3,
            ],
            'tax' => [
                'calculationMethod' => 'section_32a',
                'assessmentType' => 'splitting',
                'taxYear' => (int)($reference['tax_before_purchase']['tax_year'] ?? $purchaseYear),
                'taxableIncomeBeforeInvestment' => (float)($reference['tax_before_purchase']['initial_taxable_income'] ?? 0),
                'taxableIncomeAnnualIncreasePercent' => 2,
                'churchTax' => false,
                'churchTaxState' => 'SN',
                'marginalTaxRatePercent' => 42,
            ],
            'sale' => [
                'annualValueIncreasePercent' => 0,
                'sellingCostsPercent' => 0,
                'taxFreeSale' => true,
            ],
            'settings' => [
                'discountRatePercent' => 5,
                'initialEquityAmount' => (float)($reference['financing_need']['equity'] ?? 0),
            ],
        ];
    }

    private function firstUnitOfType(array $reference, string $type): array
    {
        foreach((array)($reference['units'] ?? []) as $unit) {
            if(($unit['type'] ?? '') === $type) {
                return $unit;
            }
        }
        return [];
    }

    private function parkingUnit(array $reference, array $unit): array
    {
        [$startYear, $startMonth] = $this->yearMonth((string)($unit['completion_month'] ?? '2026-01'));
        $depreciation = $this->depreciationRow($reference, (string)($unit['unit_id'] ?? ''), 'Neubau');

        return [
            'label' => (string)($unit['unit_id'] ?? 'Stellplatz'),
            'purchasePrice' => (float)($unit['purchase_price_breakdown']['total'] ?? 0),
            'monthlyRent' => (float)($unit['rent']['initial_cold_rent_month'] ?? 0),
            'buildingSharePercent' => (float)($unit['purchase_price_breakdown']['construction_costs']['share_percent'] ?? 100),
            'landSharePercent' => (float)($unit['purchase_price_breakdown']['land_costs']['share_percent'] ?? 0),
            'depreciable' => true,
            'depreciationMode' => 'custom',
            'depreciationRatePercent' => (float)($depreciation['rate_percent'] ?? 3),
            'depreciationStartYear' => (int)($depreciation['depreciation_start_year'] ?? $startYear),
            'depreciationStartMonth' => $startMonth,
            'includedInPurchasePrice' => true,
        ];
    }

    private function acquisitionCosts(array $reference, float $purchasePrice): array
    {
        $costs = (array)($reference['acquisition_costs'] ?? []);
        $transferTax = $this->sumCosts($costs, 'Grunderwerbsteuer');
        $notary = $this->sumCosts($costs, 'Notar');
        $landRegister = $this->sumCosts($costs, 'Kosten Grundbuch');
        $immediateDeductible = $this->hasImmediateDeductibleNotaryLandRegister($costs);

        return [
            'realEstateTransferTaxPercent' => $this->ratePercent($transferTax, $purchasePrice),
            'notaryPercent' => $this->ratePercent($notary, $purchasePrice),
            'landRegisterPurchasePercent' => $this->ratePercent($landRegister, $purchasePrice),
            'landRegisterLienPercent' => 0,
            'brokerPercent' => 0,
            'otherAcquisitionCosts' => 0,
            'otherFinancingCosts' => 0,
            'financingCostsDeductible' => true,
            'notaryLandRegisterTaxTreatment' => $immediateDeductible ? 'immediate_deductible' : 'afa_basis',
        ];
    }

    private function loan(array $loan, int $priority): array
    {
        [$startYear, $startMonth] = $this->yearMonth((string)($loan['full_payout_month'] ?? '2026-01'));
        $schedule = (array)($loan['initial_repayment_schedule'] ?? []);
        $initial = (array)($schedule[0] ?? []);
        $after = (array)($schedule[1] ?? $initial);

        return [
            'name' => (string)($loan['name'] ?? 'Darlehen'),
            'priority' => $priority,
            'principal' => (float)($loan['amount'] ?? 0),
            'interestRatePercent' => (float)($loan['nominal_interest_percent'] ?? 0),
            'initialRepaymentPercent' => (float)($initial['repayment_percent'] ?? 0),
            'startYear' => $startYear,
            'startMonth' => $startMonth,
            'interestOnlyYears' => (int)($initial['duration_years'] ?? 0),
            'repaymentRateAfterInterestOnlyPercent' => (float)($after['repayment_percent'] ?? $initial['repayment_percent'] ?? 0),
            'fixedInterestYears' => (int)($loan['fixed_interest_years'] ?? 10),
            'followUpInterestRatePercent' => (float)($loan['nominal_interest_after_fixed_period_percent'] ?? $loan['nominal_interest_percent'] ?? 0),
            'constantAnnuity' => true,
            'redeemOnSale' => true,
        ];
    }

    private function depreciationRow(array $reference, string $unitTypeOrId, string $methodOrName): array
    {
        foreach((array)($reference['depreciation'] ?? []) as $row) {
            $unitMatches = ($row['unit_id'] ?? '') === $unitTypeOrId || (($unitTypeOrId === 'Wohnung') && str_starts_with((string)($row['unit_id'] ?? ''), 'WE'));
            if($unitMatches && (($row['method'] ?? '') === $methodOrName || ($row['name'] ?? '') === $methodOrName)) {
                return $row;
            }
        }
        return [];
    }

    private function sumCosts(array $costs, string $name): float
    {
        return array_sum(array_map(static fn(array $row): float => ($row['name'] ?? '') === $name ? (float)($row['amount'] ?? 0) : 0.0, $costs));
    }

    private function ratePercent(float $amount, float $base): float
    {
        return $base > 0 ? round($amount / $base * 100, 4) : 0.0;
    }

    private function hasImmediateDeductibleNotaryLandRegister(array $costs): bool
    {
        foreach($costs as $row) {
            if(in_array($row['name'] ?? '', ['Notar', 'Kosten Grundbuch'], true) && ($row['tax_treatment'] ?? '') === 'Werbungskosten') {
                return true;
            }
        }
        return false;
    }

    private function address(array $address): string
    {
        return trim(implode(', ', array_filter([
            $address['street'] ?? '',
            trim((string)($address['postal_code'] ?? '').' '.(string)($address['city'] ?? '')),
        ])));
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function yearMonth(string $value): array
    {
        if(!preg_match('/^(\d{4})-(\d{2})$/', $value, $matches)) {
            return [2026, 1];
        }
        return [(int)$matches[1], max(min((int)$matches[2], 12), 1)];
    }
}
