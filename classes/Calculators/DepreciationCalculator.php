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
        $results = [];

        for($year = $startYear; $year <= $endYear; $year++) {
            $months = $this->activeMonths($year, $input->startYear, $input->startMonth, $endYear, $saleMonth);
            $linear = $basis * $input->linearRate * $months / 12;
            $degressive = $input->degressiveActive ? $bookValue * $input->degressiveRate * $months / 12 : 0.0;
            $baseDepreciation = $input->degressiveActive && $input->autoSwitchToLinear ? max($degressive, $linear) : ($input->degressiveActive ? $degressive : $linear);
            $specialBasis = max($input->special7bBasis, 0);
            $special7b = $input->special7bActive && $year >= $input->startYear && ($year - $input->startYear) < $input->special7bYears
                ? $specialBasis * $input->special7bRate
                : 0.0;
            $furniture = $input->furnitureBasis * $input->furnitureRate * $months / 12;
            $parking = $this->parkingDepreciation($input, $months);
            $total = min($baseDepreciation + $special7b, $bookValue) + $furniture + $parking;

            $bookValue = max($bookValue - min($baseDepreciation + $special7b, $bookValue), 0);
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

    private function parkingDepreciation(DepreciationInput $input, int $months): float
    {
        if($input->parkingDepreciationItems === []) {
            return $input->parkingBasis * $input->parkingRate * $months / 12;
        }

        $total = 0.0;
        foreach($input->parkingDepreciationItems as $item) {
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
