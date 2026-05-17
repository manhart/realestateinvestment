<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Results;

final class InvestmentCalculationResult
{
    /**
     * @param YearlyCalculationResult[] $yearlyResults
     */
    public function __construct(
        public array $summary,
        public array $metrics,
        public array $scales,
        public array $scaleLegend,
        public array $warnings,
        public array $yearlyResults,
        public array $formulas,
        public array $calculationBreakdown = [],
    ) {}

    public function toArray(): array
    {
        return [
            'summary' => $this->summary,
            'metrics' => $this->metrics,
            'scales' => $this->scales,
            'scaleLegend' => $this->scaleLegend,
            'warnings' => $this->warnings,
            'yearlyRows' => array_map(static fn(YearlyCalculationResult $row): array => $row->toArray(), $this->yearlyResults),
            'formulas' => $this->formulas,
            'calculationBreakdown' => $this->calculationBreakdown,
        ];
    }
}
