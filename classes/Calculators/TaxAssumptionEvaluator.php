<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Calculators;

use realestateinvestment\classes\Inputs\RealEstateInvestmentScenario;
use realestateinvestment\classes\Support\OfficialSourceRegistry;

final class TaxAssumptionEvaluator
{
    public function degressiveChecks(RealEstateInvestmentScenario $scenario): array
    {
        $input = $scenario->depreciation;
        if(!$input->degressiveActive) return [];
        $property = $scenario->property;
        $contractMonth = $property->purchaseYear * 12 + $property->purchaseMonth;
        $dateStatus = $contractMonth < 2023 * 12 + 10 || $contractMonth > 2029 * 12 + 9 ? 'failed' : 'fulfilled';
        return [
            ['label' => 'Degressive AfA: Kaufvertragszeitraum (monatsbezogen)', 'status' => $dateStatus],
            ['label' => 'Degressive AfA: neuer Wohnungsbau / rechtzeitiger wirtschaftlicher Erwerb', 'status' => !$property->newBuilding || $property->purchaseYear > $property->completionYear ? 'failed' : ($input->taxEligibility['degressive'] ?? 'unknown')],
            ['label' => 'Degressive AfA: gesetzlicher Satz 5 %', 'status' => abs($input->degressiveRate - 0.05) < 0.0000001 ? 'fulfilled' : 'failed'],
        ];
    }

    public function evaluate(RealEstateInvestmentScenario $scenario, array $special7b, array $degressiveChecks): array
    {
        $input = $scenario->depreciation;
        $checks = array_merge($degressiveChecks, $special7b['checks']);
        $notes = [];
        $warnings = [];
        $incomplete = false;
        if($input->special7bActive) $notes[] = 'Bauantragsdatum, Förderfläche und wirtschaftlicher Erwerb sind eigene Angaben. Ältere importierte Bauantragsdaten können aus dem Kaufdatum vorbelegt worden sein und müssen selbst geprüft werden.';
        if($input->linearRateOverrideEnabled) $checks[] = ['label' => 'Individueller AfA-Satz / Nutzungsdauer: Expertenannahme', 'status' => 'unknown'];
        if(!$input->linearRateOverrideEnabled && abs($input->enteredLinearRate - $input->linearRate) > 0.0000001) {
            $warnings[] = ['level' => 'info', 'message' => 'Gespeicherter linearer Satz '.($input->enteredLinearRate * 100).' % wird im Automatikmodus durch '.($input->linearRate * 100).' % ersetzt. Die Quelldatei bleibt unverändert.'];
        }
        foreach($degressiveChecks as $check) {
            if($check['status'] === 'failed') $warnings[] = ['level' => 'warning', 'message' => $check['label'].': nicht erfüllt; stattdessen lineare Gebäude-AfA.'];
        }
        $property = $scenario->property;
        $heldMonths = ($property->saleYear - $property->purchaseYear) * 12 + $property->saleMonth - $property->purchaseMonth;
        if(!$scenario->sale->taxFreeSale || $scenario->sale->taxableSale || !$scenario->sale->speculationPeriodReached || $heldMonths <= 120) {
            $incomplete = true;
            $notes[] = 'Verkaufssteuer ist nicht berechnet. Die §23-Frist benötigt im Grenzfall die taggenauen bindenden Kaufverträge; die Steuerfrei-Auswahl ist kein Nachweis.';
        }
        $bindingEndYear = $input->startYear + 9;
        $hasSpecialAllowance = $special7b['assessmentBasis'] > 0 && $input->special7bRate > 0 && $input->special7bYears > 0
            && ($special7b['lastAllowanceYear'] === null || $input->startYear <= $special7b['lastAllowanceYear']);
        if($hasSpecialAllowance && $scenario->sale->taxFreeSale && $property->saleYear <= $bindingEndYear) {
            $incomplete = true;
            $notes[] = 'Mögliche §7b-Rückgängigmachung: Verkauf innerhalb des separat betrachteten Bindungszeitraums bis Ende '.$bindingEndYear.'. Rückforderung, AfA-Berichtigung und Zinsen sind nicht berechnet. Der Zeitraum ist aus dem angegebenen AfA-Beginn abgeleitet und ersetzt nicht den wirtschaftlichen Erwerbszeitpunkt.';
        }
        if($scenario->acquisitionCosts->notaryLandRegisterTaxTreatment === 'immediate_deductible') {
            $incomplete = true;
            $notes[] = 'Erwerbs-Notar und Grundbuch für den Eigentümerwechsel gehören grundsätzlich zu den Anschaffungskosten. Der gewählte Sofortabzug ist steuerlich nicht freigegeben; grundpfandrechtsbezogene Kosten separat als Kreditkosten erfassen.';
        }
        if($scenario->acquisitionCosts->disagioAmount > 0 && $scenario->acquisitionCosts->disagioDeductible) {
            $notes[] = 'Disagio-Sofortabzug ist eine Nutzerannahme: tatsächlicher Zahlungsabfluss und Marktüblichkeit sind nicht geprüft. Eine steuerliche Verteilung wird nicht berechnet.';
        }
        $notes[] = $scenario->tax->calculationMethod === 'section_32a'
            ? '§32a-Standardtarif '.$scenario->tax->taxYear.' für alle Prognosejahre. Keine vollständige Steuererklärung: insbesondere Progressionsvorbehalt, Kinder-/Soli-Besonderheiten und Kirchensteuer-Sonderausgabenwirkung sind nicht modelliert.'
            : 'Grenzsteuersatz-Modus ist eine vereinfachte Steuerwirkungsrechnung, keine tatsächliche Einkommensteuerveranlagung.';
        $open = count(array_filter($checks, static fn(array $check): bool => $check['status'] === 'unknown'));
        if($open) $warnings[] = ['level' => 'info', 'message' => $open.' steuerliche Voraussetzungen sind offen. Aktivierte AfA-Schalter rechnen insoweit unter Vorbehalt Ihrer eigenen Angaben.'];
        if($incomplete) $warnings[] = ['level' => 'warning', 'message' => 'Die Steuerprognose ist unvollständig. Verkaufssteuer, mögliche §7b-Rückforderung oder Kostenannahmen bitte im Block „Steuerliche Modellannahmen“ prüfen. Endvermögenskennzahlen sind nur vorläufig.'];
        return [
            'status' => $incomplete ? 'incomplete' : 'assumptions',
            'label' => $incomplete ? 'Unvollständige Steuerprognose' : 'Prognose unter Nutzerannahmen',
            'checks' => $checks, 'notes' => $notes, 'warnings' => $warnings,
            'bindingEndYear' => $bindingEndYear,
            'linearRatePercent' => $input->linearRate * 100, 'usefulLifeYears' => $input->usefulLifeYears,
            'sources' => array_map(OfficialSourceRegistry::get(...), ['estg_7', 'bmf_7b', 'estg_23']),
        ];
    }
}
