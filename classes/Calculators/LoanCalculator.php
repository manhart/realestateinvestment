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

        $results = [];
        for($year = $startYear; $year <= $endYear; $year++) {
            $yearResult = ['interest' => 0.0, 'repayment' => 0.0, 'annuity' => 0.0, 'remainingDebt' => 0.0];
            $lastMonth = $year === $endYear ? $saleMonth : 12;

            foreach($loans as $index => $loan) {
                $debt = $states[$index]['debt'];
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
                    $yearResult['interest'] += $monthlyInterest;
                    $yearResult['repayment'] += $monthlyRepayment;
                    $yearResult['annuity'] += $monthlyInterest + $monthlyRepayment;

                    if($loan->specialRepaymentAmount > 0
                        && $loan->specialRepaymentYear === $year
                        && $loan->specialRepaymentMonth === $month
                    ) {
                        $special = min($loan->specialRepaymentAmount, $debt);
                        $debt -= $special;
                        $yearResult['repayment'] += $special;
                        $yearResult['annuity'] += $special;
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
                $yearResult['remainingDebt'] += $states[$index]['debt'];
            }

            $results[$year] = $yearResult;
        }

        return $results;
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
