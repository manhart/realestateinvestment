<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Calculators;

use realestateinvestment\classes\Inputs\TaxInput;

final class TaxCalculator
{
    public function calculate(TaxInput $tax, float $rentalTaxableIncome, ?float $taxableIncomeBeforeInvestment = null): array
    {
        $taxableBefore = $taxableIncomeBeforeInvestment ?? $tax->taxableIncomeBeforeInvestment;
        $taxableAfter = max($taxableBefore + $rentalTaxableIncome, 0);

        if($tax->calculationMethod === TaxInput::CALCULATION_METHOD_SECTION_32A) {
            $before = $this->section32aTaxBreakdown($tax, $taxableBefore);
            $after = $this->section32aTaxBreakdown($tax, $taxableAfter);

            return [
                'calculationMethod' => $tax->calculationMethod,
                'taxableBefore' => $taxableBefore,
                'taxableAfter' => $taxableAfter,
                'tariffIncomeTaxBefore' => $before['tariffIncomeTax'],
                'tariffIncomeTaxAfter' => $after['tariffIncomeTax'],
                'solidaritySurchargeBefore' => $before['solidaritySurcharge'],
                'solidaritySurchargeAfter' => $after['solidaritySurcharge'],
                'churchTaxBefore' => $before['churchTax'],
                'churchTaxAfter' => $after['churchTax'],
                'taxBefore' => $before['totalTax'],
                'taxAfter' => $after['totalTax'],
                'taxEffect' => $before['totalTax'] - $after['totalTax'],
            ];
        }

        $taxBefore = $this->simpleMarginalTax($taxableBefore, $tax->marginalTaxRate);
        $taxAfter = $this->simpleMarginalTax($taxableAfter, $tax->marginalTaxRate);

        return [
            'calculationMethod' => TaxInput::CALCULATION_METHOD_MARGINAL_RATE,
            'taxableBefore' => $taxableBefore,
            'taxableAfter' => $taxableAfter,
            'tariffIncomeTaxBefore' => $taxBefore,
            'tariffIncomeTaxAfter' => $taxAfter,
            'solidaritySurchargeBefore' => 0.0,
            'solidaritySurchargeAfter' => 0.0,
            'churchTaxBefore' => 0.0,
            'churchTaxAfter' => 0.0,
            'taxBefore' => $taxBefore,
            'taxAfter' => $taxAfter,
            'taxEffect' => $taxBefore - $taxAfter,
        ];
    }

    private function simpleMarginalTax(float $taxableIncome, float $rate): float
    {
        return max($taxableIncome, 0) * max($rate, 0);
    }

    /**
     * @return array{tariffIncomeTax: float, solidaritySurcharge: float, churchTax: float, totalTax: float}
     */
    private function section32aTaxBreakdown(TaxInput $tax, float $taxableIncome): array
    {
        $tariffIncomeTax = $this->section32aIncomeTax($taxableIncome, $tax->assessmentType, $tax->taxYear);
        $solidaritySurcharge = $this->solidaritySurcharge($tariffIncomeTax, $tax->assessmentType, $tax->taxYear);
        $churchTax = $tax->churchTax ? $tariffIncomeTax * $this->churchTaxRate($tax->churchTaxState) : 0.0;

        return [
            'tariffIncomeTax' => $tariffIncomeTax,
            'solidaritySurcharge' => $solidaritySurcharge,
            'churchTax' => $churchTax,
            'totalTax' => $tariffIncomeTax + $solidaritySurcharge + $churchTax,
        ];
    }

    private function section32aIncomeTax(float $taxableIncome, string $assessmentType, int $taxYear): float
    {
        if($assessmentType === 'splitting')
            return 2 * $this->basicSection32aIncomeTax($taxableIncome / 2, $taxYear);

        return $this->basicSection32aIncomeTax($taxableIncome, $taxYear);
    }

    private function basicSection32aIncomeTax(float $taxableIncome, int $taxYear): float
    {
        $x = floor(max($taxableIncome, 0));
        $params = $this->section32aParams($taxYear);

        if($x <= $params['basicAllowance'])
            return 0.0;

        if($x <= $params['zone1Limit']) {
            $y = ($x - $params['basicAllowance']) / 10000;
            return floor(($params['zone1A'] * $y + $params['zone1B']) * $y);
        }

        if($x <= $params['zone2Limit']) {
            $z = ($x - $params['zone1Limit']) / 10000;
            return floor(($params['zone2A'] * $z + $params['zone2B']) * $z + $params['zone2C']);
        }

        if($x <= $params['topRateLimit'])
            return floor($params['topRate'] * $x - $params['topRateOffset']);

        return floor($params['richRate'] * $x - $params['richRateOffset']);
    }

    /**
     * @return array<string, float>
     */
    private function section32aParams(int $taxYear): array
    {
        if($taxYear !== 2026)
            $taxYear = 2026;

        return [
            'basicAllowance' => 12348,
            'zone1Limit' => 17799,
            'zone2Limit' => 69878,
            'topRateLimit' => 277825,
            'zone1A' => 914.51,
            'zone1B' => 1400,
            'zone2A' => 173.10,
            'zone2B' => 2397,
            'zone2C' => 1034.87,
            'topRate' => 0.42,
            'topRateOffset' => 11135.63,
            'richRate' => 0.45,
            'richRateOffset' => 19470.38,
        ];
    }

    private function solidaritySurcharge(float $incomeTax, string $assessmentType, int $taxYear): float
    {
        $freeLimit = $this->solidarityFreeLimit($assessmentType, $taxYear);
        if($incomeTax <= $freeLimit)
            return 0.0;

        return min($incomeTax * 0.055, ($incomeTax - $freeLimit) * 0.119);
    }

    private function solidarityFreeLimit(string $assessmentType, int $taxYear): float
    {
        if($taxYear !== 2026)
            $taxYear = 2026;

        return $assessmentType === 'splitting' ? 40700.0 : 20350.0;
    }

    private function churchTaxRate(string $state): float
    {
        return in_array($state, ['BY', 'BW'], true) ? 0.08 : 0.09;
    }
}
