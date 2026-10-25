<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Calculators;

final class SpecialAfa7bRuleProvider
{
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

    public function ruleForDate(string $applicationDate, string $period = ''): array
    {
        $date = $this->normalizeDate($applicationDate);
        foreach(self::RULES as $index => $rule) {
            if(($date !== '' && $date >= $rule['validFromApplicationDate'] && $date < $rule['validUntilApplicationDate'])
                || ($applicationDate === '' && $period === ($index === 0 ? 'old' : 'new'))) {
                return $rule + [
                    'applicationDate' => $date,
                    'defaulted' => false,
                    'matched' => true,
                    'period' => $index === 0 ? 'old' : 'new',
                    'lastAllowanceYear' => $index === 0 ? 2026 : null,
                ];
            }
        }

        return [
            'applicationDate' => $date,
            'defaulted' => false,
            'matched' => false,
            'period' => '',
            'lastAllowanceYear' => null,
            'constructionCostLimitPerSqm' => 0.0,
            'specialAfaLimitPerSqm' => 0.0,
            'specialAfaRate' => 0.05,
            'years' => 4,
        ];
    }

    private function normalizeDate(string $date): string
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date ? $date : '';
    }
}
