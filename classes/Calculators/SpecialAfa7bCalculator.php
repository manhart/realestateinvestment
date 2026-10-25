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
        $rule = $this->ruleProvider->ruleForDate($input->special7bApplicationDate, $input->special7bRulePeriod);
        $input->special7bLastYear = $rule['lastAllowanceYear'];
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
        $checks = [];

        if($input->special7bActive) {
            $checks[] = ['label' => '§7b Bauantrag / Förderzeitraum', 'status' => !$rule['matched'] ? 'failed' : ($input->special7bApplicationDate === '' ? 'unknown' : 'fulfilled')];
            foreach(['newDwelling' => 'Neue, begünstigte Wohnung', 'timelyAcquisition' => 'Erwerb bis Ende des Fertigstellungsjahres', 'rentalUse' => 'Entgeltliche Wohnraumvermietung', 'costAllocation' => 'Begünstigte Kosten und Förderfläche sachgerecht zugeordnet'] as $key => $label) {
                $checks[] = ['label' => $label, 'status' => $input->taxEligibility[$key] ?? 'unknown'];
            }
            if($rule['period'] === 'new') $checks[] = ['label' => 'EH40-NH / QNG', 'status' => $input->taxEligibility['qng'] ?? 'unknown'];
            if($rule['lastAllowanceYear'] !== null) $checks[] = ['label' => '§7b Altfälle: erstmaliger Ansatz spätestens 2026', 'status' => $input->startYear <= $rule['lastAllowanceYear'] ? 'fulfilled' : 'failed'];
            $checks[] = ['label' => 'Baukosten-Prüfwert laut eigener Ermittlung', 'status' => $actualConstructionCostPerSqm <= 0 ? 'unknown' : ($actualConstructionCostPerSqm <= $constructionCostLimitPerSqm ? 'fulfilled' : 'failed')];
            $limitsValid = $rule['matched'] && $specialAfaLimitPerSqm <= $rule['specialAfaLimitPerSqm']
                && $constructionCostLimitPerSqm <= $rule['constructionCostLimitPerSqm']
                && $input->special7bRate >= 0 && $input->special7bRate <= 0.05 && $input->special7bYears <= 4;
            $checks[] = ['label' => 'Gesetzliche §7b-Höchstwerte', 'status' => !$rule['matched'] ? 'unknown' : ($limitsValid ? 'fulfilled' : 'failed')];
            $failed = in_array('failed', array_column($checks, 'status'), true);
            if(!$rule['matched'] && $input->special7bConstructionCostLimitPerSqm <= 0 && $input->special7bLimitPerSqm <= 0) {
                $warnings[] = ['level' => 'warning', 'message' => 'Für das Bauantrag-/Bauanzeige-Datum wurde keine §7b-Regel gefunden.'];
            }
            if($area <= 0) {
                $warnings[] = ['level' => 'warning', 'message' => '§7b ist aktiv, aber es ist keine begünstigte Wohnfläche erfasst.'];
            } elseif($eligibleCosts <= 0) {
                $warnings[] = ['level' => 'warning', 'message' => '§7b ist aktiv, aber es sind keine begünstigten AH-Kosten berechnet.'];
            } elseif($actualConstructionCostPerSqm > 0 && $constructionCostLimitPerSqm > 0 && $actualConstructionCostPerSqm > $constructionCostLimitPerSqm) {
                $warnings[] = ['level' => 'warning', 'message' => '§7b wird nicht angewendet, weil die begünstigten Kosten je m² über der Baukostenobergrenze liegen.'];
            } elseif(!$failed) {
                $assessmentBasis = $cap > 0 ? min($eligibleCosts, $cap) : $eligibleCosts;
            }
            foreach($checks as $check) {
                if($check['status'] === 'failed') $warnings[] = ['level' => 'warning', 'message' => $check['label'].': Voraussetzung nicht erfüllt; §7b wird nicht angesetzt.'];
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
            'checks' => $checks,
            'lastAllowanceYear' => $rule['lastAllowanceYear'],
        ];
    }
}
