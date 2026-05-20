<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Calculators;

use realestateinvestment\classes\Inputs\DepreciationInput;

final class DepreciationCalculator
{
    /**
     * @return array<int, array{degressive: float, linear: float, special7b: float, furniture: float, parking: float, total: float}>
     */
    public function calculate(DepreciationInput $input, int $startYear, int $endYear, int $saleMonth): array
    {
        $basis = max($input->buildingBasis, 0);
        $bookValue = $basis;
        $special7bCarry = 0.0;
        $results = [];

        for($year = $startYear; $year <= $endYear; $year++) {
            $months = $this->activeMonths($year, $input->startYear, $input->startMonth, $endYear, $saleMonth);
            $special7bActive = $this->isSpecial7bActive($input, $year);
            if(!$input->special7bReducesBookValueImmediately && !$special7bActive && $special7bCarry > 0.0) {
                $bookValue = max($bookValue - $special7bCarry, 0);
                $special7bCarry = 0.0;
            }

            $linear = $basis * $input->linearRate * $months / 12;
            $degressive = $input->degressiveActive ? $bookValue * $input->degressiveRate * $months / 12 : 0.0;
            $baseDepreciation = $input->degressiveActive && $input->autoSwitchToLinear ? max($degressive, $linear) : ($input->degressiveActive ? $degressive : $linear);
            $specialBasis = max($input->special7bBasis, 0);
            $regularDepreciation = min($baseDepreciation, $bookValue);
            $special7bCapacity = max($bookValue - $special7bCarry - $regularDepreciation, 0);
            $special7b = $special7bActive ? min($specialBasis * $input->special7bRate, $special7bCapacity) : 0.0;
            $furniture = $input->furnitureBasis * $input->furnitureRate * $months / 12;
            $parking = $this->parkingDepreciation($input, $year, $months, $endYear, $saleMonth);
            $total = $regularDepreciation + $special7b + $furniture + $parking;

            $bookValueReduction = $regularDepreciation + ($input->special7bReducesBookValueImmediately ? $special7b : 0.0);
            $bookValue = max($bookValue - $bookValueReduction, 0);
            if($special7bActive && !$input->special7bReducesBookValueImmediately) {
                $special7bCarry += $special7b;
            }
            $results[$year] = [
                'degressive' => $degressive,
                'linear' => $linear,
                'special7b' => $special7b,
                'furniture' => $furniture,
                'parking' => $parking,
                'total' => $total,
            ];
        }

        return $results;
    }

    private function isSpecial7bActive(DepreciationInput $input, int $year): bool
    {
        return $input->special7bActive
            && $year >= $input->startYear
            && ($year - $input->startYear) < $input->special7bYears;
    }

    private function parkingDepreciation(DepreciationInput $input, int $year, int $fallbackMonths, int $endYear, int $saleMonth): float
    {
        if($input->parkingDepreciationItems === []) {
            return $input->parkingBasis * $input->parkingRate * $fallbackMonths / 12;
        }

        $total = 0.0;
        foreach($input->parkingDepreciationItems as $item) {
            $months = $this->activeMonths(
                $year,
                (int)($item['startYear'] ?? $input->startYear),
                (int)($item['startMonth'] ?? $input->startMonth),
                $endYear,
                $saleMonth,
            );
            $total += max((float)($item['basis'] ?? 0), 0) * max((float)($item['rate'] ?? 0), 0) * $months / 12;
        }
        return $total;
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
}
