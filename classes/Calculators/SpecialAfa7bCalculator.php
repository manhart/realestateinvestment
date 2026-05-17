<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Calculators;

use realestateinvestment\classes\Inputs\DepreciationInput;

final class SpecialAfa7bCalculator
{
    public function __construct(
        private readonly SpecialAfa7bRuleProvider $ruleProvider = new SpecialAfa7bRuleProvider(),
    ) {}

    public function calculate(DepreciationInput $input, float $livingArea): array
    {
        $rule = $this->ruleProvider->ruleForDate($input->special7bApplicationDate);
        $area = $input->special7bArea > 0 ? $input->special7bArea : max($livingArea, 0);
        $constructionCostLimitPerSqm = $input->special7bConstructionCostLimitPerSqm > 0
            ? $input->special7bConstructionCostLimitPerSqm
            : (float)$rule['constructionCostLimitPerSqm'];
        $actualConstructionCostPerSqm = max($input->special7bActualConstructionCostPerSqm, 0);
        $specialAfaLimitPerSqm = $input->special7bLimitPerSqm > 0
            ? $input->special7bLimitPerSqm
            : (float)$rule['specialAfaLimitPerSqm'];
        $eligibleCosts = max($input->special7bBasis, 0);
        $cap = $area > 0 && $specialAfaLimitPerSqm > 0 ? $area * $specialAfaLimitPerSqm : max($input->special7bCap, 0);
        $constructionCostCap = $area > 0 && $constructionCostLimitPerSqm > 0 ? $area * $constructionCostLimitPerSqm : 0.0;
        $costsPerSqm = $area > 0 ? $eligibleCosts / $area : 0.0;
        $warnings = [];
        $assessmentBasis = 0.0;

        if($input->special7bActive) {
            if(!$rule['matched'] && $input->special7bConstructionCostLimitPerSqm <= 0 && $input->special7bLimitPerSqm <= 0) {
                $warnings[] = ['level' => 'warning', 'message' => 'Für das Bauantrag-/Bauanzeige-Datum wurde keine §7b-Regel gefunden.'];
            }
            if($area <= 0) {
                $warnings[] = ['level' => 'warning', 'message' => '§7b ist aktiv, aber es ist keine begünstigte Wohnfläche erfasst.'];
            } elseif($eligibleCosts <= 0) {
                $warnings[] = ['level' => 'warning', 'message' => '§7b ist aktiv, aber es sind keine begünstigten AH-Kosten berechnet.'];
            } elseif($actualConstructionCostPerSqm > 0 && $constructionCostLimitPerSqm > 0 && $actualConstructionCostPerSqm > $constructionCostLimitPerSqm) {
                $warnings[] = ['level' => 'warning', 'message' => '§7b wird nicht angewendet, weil die begünstigten Kosten je m² über der Baukostenobergrenze liegen.'];
            } else {
                $assessmentBasis = $cap > 0 ? min($eligibleCosts, $cap) : $eligibleCosts;
            }
        }

        return [
            'active' => $input->special7bActive,
            'applicationDate' => (string)$rule['applicationDate'],
            'ruleDefaulted' => (bool)$rule['defaulted'],
            'ruleMatched' => (bool)$rule['matched'],
            'area' => $area,
            'constructionCostLimitPerSqm' => $constructionCostLimitPerSqm,
            'actualConstructionCostPerSqm' => $actualConstructionCostPerSqm,
            'limitPerSqm' => $specialAfaLimitPerSqm,
            'constructionCostCap' => $constructionCostCap,
            'cap' => $cap,
            'eligibleCosts' => $eligibleCosts,
            'costsPerSqm' => $costsPerSqm,
            'assessmentBasis' => $assessmentBasis,
            'assessmentBasisPerSqm' => $area > 0 ? $assessmentBasis / $area : 0.0,
            'annualAmount' => $assessmentBasis * $input->special7bRate,
            'warnings' => $warnings,
        ];
    }
}
