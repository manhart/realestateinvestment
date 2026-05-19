<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Calculators;

use realestateinvestment\classes\Inputs\LoanInput;

final class LoanCalculator
{
    /**
     * @param LoanInput[] $loans
     * @return array<int, array{interest: float, repayment: float, annuity: float, remainingDebt: float}>
     */
    public function calculate(array $loans, int $startYear, int $endYear, int $saleMonth = 12): array
    {
        $states = $this->initialStates($loans);
        $results = [];
        for($year = $startYear; $year <= $endYear; $year++) {
            $yearResult = $this->calculateYear($loans, $states, $year, $endYear, $saleMonth);
            unset($yearResult['perLoan']);
            $results[$year] = $yearResult;
        }

        return $results;
    }

    /**
     * @param LoanInput[] $loans
     */
    public function initialStates(array $loans): array
    {
        $states = [];
        foreach($loans as $index => $loan) {
            $repaymentRateForAnnuity = $loan->interestOnlyYears > 0
                ? $loan->repaymentRateAfterInterestOnly
                : $loan->initialRepaymentRate;
            $states[$index] = [
                'debt' => max($loan->principal, 0),
                'monthlyAnnuity' => max($loan->principal * ($loan->interestRate + $repaymentRateForAnnuity) / 12, 0),
            ];
        }
        return $states;
    }

    /**
     * @param LoanInput[] $loans
     */
    public function calculateYear(array $loans, array &$states, int $year, int $endYear, int $saleMonth = 12): array
    {
        $yearResult = ['interest' => 0.0, 'repayment' => 0.0, 'annuity' => 0.0, 'remainingDebt' => 0.0, 'perLoan' => []];
        $lastMonth = $year === $endYear ? $saleMonth : 12;

        foreach($loans as $index => $loan) {
            $debt = $states[$index]['debt'];
            $loanInterest = 0.0;
            $loanRepayment = 0.0;
            $loanAnnuity = 0.0;
            for($month = 1; $month <= $lastMonth; $month++) {
                if(!$this->loanIsActive($loan, $year, $month) || $debt <= 0) {
                    continue;
                }

                $monthsSinceStart = (($year - $loan->startYear) * 12) + ($month - $loan->startMonth);
                $annualInterestRate = $this->interestRateForMonth($loan, $monthsSinceStart);
                $monthlyInterest = $debt * $annualInterestRate / 12;
                $interestOnly = $monthsSinceStart < $loan->interestOnlyYears * 12;
                $monthlyRepayment = 0.0;
                $payment = $monthlyInterest;

                if(!$interestOnly) {
                    $monthlyAnnuity = $loan->constantAnnuity
                        ? $states[$index]['monthlyAnnuity']
                        : $debt * ($annualInterestRate + $loan->repaymentRateAfterInterestOnly) / 12;
                    $payment = max($monthlyAnnuity, $monthlyInterest);
                    $monthlyRepayment = min(max($payment - $monthlyInterest, 0), $debt);
                }

                $debt -= $monthlyRepayment;
                $loanInterest += $monthlyInterest;
                $loanRepayment += $monthlyRepayment;
                $loanAnnuity += $monthlyInterest + $monthlyRepayment;

                if($loan->specialRepaymentAmount > 0
                    && $loan->specialRepaymentYear === $year
                    && $loan->specialRepaymentMonth === $month
                ) {
                    $special = min($loan->specialRepaymentAmount, $debt);
                    $debt -= $special;
                    $loanRepayment += $special;
                    $loanAnnuity += $special;
                }

                if($loan->grantAmount > 0
                    && $loan->grantReducesDebt
                    && $loan->grantYear === $year
                    && $loan->grantMonth === $month
                ) {
                    $debt -= min($loan->grantAmount, $debt);
                }
            }
            $states[$index]['debt'] = max($debt, 0);
            $active = $this->loanIsActive($loan, $year, $lastMonth);
            if($active) {
                $yearResult['remainingDebt'] += $states[$index]['debt'];
            }
            $yearResult['interest'] += $loanInterest;
            $yearResult['repayment'] += $loanRepayment;
            $yearResult['annuity'] += $loanAnnuity;
            $yearResult['perLoan'][$index] = [
                'name' => $loan->name,
                'active' => $active,
                'interestRate' => $active ? $this->interestRateForYearEnd($loan, $year, $lastMonth) : 0.0,
                'remainingDebt' => $states[$index]['debt'],
                'interest' => $loanInterest,
                'repayment' => $loanRepayment,
                'annuity' => $loanAnnuity,
            ];
        }

        return $yearResult;
    }

    /**
     * @param LoanInput[] $loans
     */
    public function applyYearEndSpecialRepayment(array $loans, array &$states, int $year, int $endYear, float $amount, int $saleMonth = 12): array
    {
        $targetIndex = $this->highestInterestLoanIndex($loans, $states, $year, $endYear, $saleMonth);
        if($targetIndex === null || $amount <= 0) {
            return ['amount' => 0.0, 'target' => '', 'remainingDebt' => $this->remainingDebt($loans, $states, $year, $endYear, $saleMonth)];
        }

        $specialRepayment = min($amount, max((float)$states[$targetIndex]['debt'], 0));
        $states[$targetIndex]['debt'] -= $specialRepayment;

        return [
            'amount' => $specialRepayment,
            'target' => $loans[$targetIndex]->name,
            'remainingDebt' => $this->remainingDebt($loans, $states, $year, $endYear, $saleMonth),
        ];
    }

    /**
     * @param LoanInput[] $loans
     */
    private function highestInterestLoanIndex(array $loans, array $states, int $year, int $endYear, int $saleMonth): ?int
    {
        $bestIndex = null;
        $bestRate = -1.0;
        $lastMonth = $year === $endYear ? $saleMonth : 12;
        foreach($loans as $index => $loan) {
            if(!$this->loanIsActive($loan, $year, $lastMonth) || (float)$states[$index]['debt'] <= 0) {
                continue;
            }
            $rate = $this->interestRateForYearEnd($loan, $year, $lastMonth);
            if($rate > $bestRate) {
                $bestRate = $rate;
                $bestIndex = $index;
            }
        }
        return $bestIndex;
    }

    /**
     * @param LoanInput[] $loans
     */
    private function remainingDebt(array $loans, array $states, int $year, int $endYear, int $saleMonth): float
    {
        $remainingDebt = 0.0;
        $lastMonth = $year === $endYear ? $saleMonth : 12;
        foreach($loans as $index => $loan) {
            if($this->loanIsActive($loan, $year, $lastMonth)) {
                $remainingDebt += max((float)$states[$index]['debt'], 0);
            }
        }
        return $remainingDebt;
    }

    private function interestRateForYearEnd(LoanInput $loan, int $year, int $month): float
    {
        $monthsSinceStart = (($year - $loan->startYear) * 12) + ($month - $loan->startMonth);
        return $this->interestRateForMonth($loan, max($monthsSinceStart, 0));
    }

    private function loanIsActive(LoanInput $loan, int $year, int $month): bool
    {
        return $year > $loan->startYear || ($year === $loan->startYear && $month >= $loan->startMonth);
    }

    private function interestRateForMonth(LoanInput $loan, int $monthsSinceStart): float
    {
        if($loan->fixedInterestYears > 0 && $monthsSinceStart >= $loan->fixedInterestYears * 12) {
            return $loan->followUpInterestRate;
        }
        return $loan->interestRate;
    }
}
