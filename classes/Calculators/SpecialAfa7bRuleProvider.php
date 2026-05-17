<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Calculators;

final class SpecialAfa7bRuleProvider
{
    private const DEFAULT_APPLICATION_DATE = '2023-01-01';

    private const RULES = [
        [
            'validFromApplicationDate' => '2018-09-01',
            'validUntilApplicationDate' => '2022-01-01',
            'constructionCostLimitPerSqm' => 3000.0,
            'specialAfaLimitPerSqm' => 2000.0,
            'specialAfaRate' => 0.05,
            'years' => 4,
        ],
        [
            'validFromApplicationDate' => '2023-01-01',
            'validUntilApplicationDate' => '2029-10-01',
            'constructionCostLimitPerSqm' => 5200.0,
            'specialAfaLimitPerSqm' => 4000.0,
            'specialAfaRate' => 0.05,
            'years' => 4,
        ],
    ];

    public function ruleForDate(string $applicationDate): array
    {
        $date = $this->normalizeDate($applicationDate);
        $defaulted = $date === '';
        $date = $defaulted ? self::DEFAULT_APPLICATION_DATE : $date;

        foreach(self::RULES as $rule) {
            if($date >= $rule['validFromApplicationDate'] && $date < $rule['validUntilApplicationDate']) {
                return $rule + [
                    'applicationDate' => $date,
                    'defaulted' => $defaulted,
                    'matched' => true,
                ];
            }
        }

        return [
            'applicationDate' => $date,
            'defaulted' => $defaulted,
            'matched' => false,
            'constructionCostLimitPerSqm' => 0.0,
            'specialAfaLimitPerSqm' => 0.0,
            'specialAfaRate' => 0.05,
            'years' => 4,
        ];
    }

    private function normalizeDate(string $date): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
    }
}
