<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Calculators;

use InvalidArgumentException;
use realestateinvestment\classes\Inputs\DepreciationInput;

final class DepreciationCalculator
{
    public function calculate(DepreciationInput $input, int $startYear, int $endYear, int $saleMonth): array
    {
        if($input->usefulLifeYears <= 0 || $input->linearRate < 0 || $input->furnitureRate < 0) {
            throw new InvalidArgumentException('AfA: Nutzungsdauer muss positiv sein; AfA-Sätze dürfen nicht negativ sein.');
        }
        $basis = max($input->buildingBasis, 0);
        $regularBookValue = $basis;
        $specialCarry = 0.0;
        $furnitureRemaining = max($input->furnitureBasis, 0);
        $parkingItems = $input->parkingDepreciationItems ?: [[
            'label' => 'Stellplätze', 'basis' => $input->parkingBasis, 'rate' => $input->parkingRate,
            'startYear' => $input->startYear, 'startMonth' => $input->startMonth,
        ]];
        $parkingRemaining = array_map(static fn(array $item): float => max($item['basis'], 0), $parkingItems);
        $linearMethod = !$input->degressiveActive;
        $linearAnnualAmount = $basis * $input->linearRate;
        $specialSettled = false;
        $results = [];

        // Include elapsed depreciation even when the requested report starts later.
        $firstYear = min($startYear, $input->startYear);
        foreach($parkingItems as $item) $firstYear = min($firstYear, $item['startYear']);
        for($year = $firstYear; $year <= $endYear; $year++) {
            $months = $this->activeMonths($year, $input->startYear, $input->startMonth, $endYear, $saleMonth);
            $elapsedMonths = max(($year - $input->startYear) * 12 - $input->startMonth + 1, 0);
            $remainingLife = max($input->usefulLifeYears - $elapsedMonths / 12, 0);
            $specialWindow = $input->special7bActive && $year >= $input->startYear
                && $year < $input->startYear + 4;
            if($input->special7bActive && $specialCarry > 0 && $year >= $input->startYear + 4 && !$specialSettled) {
                $regularBookValue = max($regularBookValue - $specialCarry, 0);
                $specialCarry = 0.0;
                if($linearMethod) $linearAnnualAmount = $remainingLife > 0 ? $regularBookValue / $remainingLife : $regularBookValue;
                $specialSettled = true;
            }
            $bookValueStart = max($regularBookValue - $specialCarry, 0);
            $degressiveCandidate = $input->degressiveActive ? $regularBookValue * $input->degressiveRate * $months / 12 : 0.0;
            $linearCandidate = ($remainingLife > 0 ? $bookValueStart / $remainingLife : $bookValueStart) * $months / 12;
            if(!$linearMethod && $input->autoSwitchToLinear && $months > 0 && $linearCandidate > $degressiveCandidate + 0.0000001) {
                $linearMethod = true;
                $linearAnnualAmount = $remainingLife > 0 ? $bookValueStart / $remainingLife : $bookValueStart;
            }
            $regular = min(max($linearMethod ? $linearAnnualAmount * $months / 12 : $degressiveCandidate, 0), $bookValueStart);
            $specialActive = $specialWindow && $year - $input->startYear < $input->special7bYears
                && ($input->special7bLastYear === null || $year <= $input->special7bLastYear);
            $special = $specialActive ? min(max($input->special7bBasis, 0) * $input->special7bRate, max($bookValueStart - $regular, 0)) : 0.0;
            $regularBookValue = max($regularBookValue - $regular, 0);
            $specialCarry += $special;
            $furniture = min(max($input->furnitureBasis * $input->furnitureRate * $months / 12, 0), $furnitureRemaining);
            $furnitureRemaining = max($furnitureRemaining - $furniture, 0);
            $parking = 0.0;
            $parkingDetails = [];
            foreach($parkingItems as $index => $item) {
                $itemMonths = $this->activeMonths($year, $item['startYear'], $item['startMonth'], $endYear, $saleMonth);
                $amount = min(max($item['basis'] * $item['rate'] * $itemMonths / 12, 0), $parkingRemaining[$index]);
                $parkingRemaining[$index] = max($parkingRemaining[$index] - $amount, 0);
                $parking += $amount;
                $parkingDetails[] = $item + ['months' => $itemMonths, 'amount' => $amount, 'remaining' => $parkingRemaining[$index]];
            }
            if($year < $startYear) continue;
            $results[$year] = [
                'degressive' => $linearMethod ? 0.0 : $regular,
                'linear' => $linearMethod ? $regular : 0.0,
                'special7b' => $special, 'furniture' => $furniture, 'parking' => $parking,
                'total' => $regular + $special + $furniture + $parking,
                'method' => $months > 0 ? ($linearMethod ? 'linear' : 'degressive') : 'none',
                'months' => $months, 'bookValueStart' => $bookValueStart,
                'bookValueEnd' => max($regularBookValue - $specialCarry, 0),
                'remainingLifeYears' => $remainingLife,
                'degressiveCandidate' => $degressiveCandidate, 'linearCandidate' => $linearCandidate,
                'linearAnnualAmount' => $linearAnnualAmount, 'special7bBasis' => $input->special7bBasis,
                'furnitureRemaining' => $furnitureRemaining, 'parkingItems' => $parkingDetails,
            ];
        }
        return $results;
    }

    private function activeMonths(int $year, int $startYear, int $startMonth, int $endYear, int $endMonth): int
    {
        if($year < $startYear || $year > $endYear) return 0;
        return max(($year === $endYear ? $endMonth : 12) - ($year === $startYear ? $startMonth : 1) + 1, 0);
    }
}
