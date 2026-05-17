<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Calculators;

final class MetricScaleEvaluator
{
    public function legend(): array
    {
        return [
            [
                'key' => 'nominalLeverage',
                'title' => 'Hebel-/FV-Hebel-Kennzahlen',
                'description' => 'Endwert im Verhältnis zur Anfangsschuld. Bei der liquiditätsorientierten FV-Variante strenger lesen.',
                'items' => [
                    ['range' => 'unter 10 %', 'label' => 'schwach', 'variant' => 'danger', 'description' => 'Sehr geringe Vermögenswirkung pro Euro Fremdkapital.'],
                    ['range' => '10-15 %', 'label' => 'mäßig', 'variant' => 'warning', 'description' => 'Positiv, aber mit wenig Reserve.'],
                    ['range' => '15-20 %', 'label' => 'brauchbar', 'variant' => 'secondary', 'description' => 'Grundsätzlich nutzbar, aber nicht besonders stark.'],
                    ['range' => '20-25 %', 'label' => 'gut', 'variant' => 'primary', 'description' => 'Solider Vermögenseffekt im Verhältnis zur Anfangsschuld.'],
                    ['range' => '25-30 %', 'label' => 'stark', 'variant' => 'success', 'description' => 'Deutlich attraktiver Hebeleffekt.'],
                    ['range' => 'über 30 %', 'label' => 'sehr stark', 'variant' => 'success', 'description' => 'Sehr hoher Endwertbeitrag pro Euro Anfangsschuld.'],
                ],
            ],
            [
                'key' => 'npvLeverage',
                'title' => 'Barwert-Hebel',
                'description' => 'Heutiger Wert im Verhältnis zur Anfangsschuld, nachdem die Alternativrendite bereits berücksichtigt ist.',
                'items' => [
                    ['range' => 'unter 0 %', 'label' => 'schlechter als Alternativanlage', 'variant' => 'danger', 'description' => 'Die gewählte Alternativrendite wird rechnerisch nicht erreicht.'],
                    ['range' => '0-3 %', 'label' => 'knapp positiv', 'variant' => 'warning', 'description' => 'Positiv, aber mit geringer Sicherheitsmarge.'],
                    ['range' => '3-6 %', 'label' => 'mäßig', 'variant' => 'secondary', 'description' => 'Leichter Mehrwert gegenüber der Alternativanlage.'],
                    ['range' => '6-10 %', 'label' => 'brauchbar bis ordentlich', 'variant' => 'primary', 'description' => 'Ordentlicher Barwertbeitrag.'],
                    ['range' => '10-15 %', 'label' => 'gut', 'variant' => 'success', 'description' => 'Guter Mehrwert nach Opportunitätskosten.'],
                    ['range' => 'über 15 %', 'label' => 'stark', 'variant' => 'success', 'description' => 'Starker Barwertbeitrag nach Alternativrendite.'],
                ],
            ],
            [
                'key' => 'debtYield',
                'title' => 'Debt Yield',
                'description' => 'Reinertrag im Verhältnis zum Darlehensbetrag. Steuern, AfA, Tilgung und Verkauf gehören nicht hinein.',
                'items' => [
                    ['range' => 'unter 5 %', 'label' => 'sehr niedrig', 'variant' => 'danger', 'description' => 'Sehr geringe operative Tragfähigkeit.'],
                    ['range' => '5-7 %', 'label' => 'schwach', 'variant' => 'warning', 'description' => 'Operativ schwache Schuldendeckung.'],
                    ['range' => '7-8 %', 'label' => 'unterer Grenzbereich', 'variant' => 'warning', 'description' => 'Knapp vor dem Mindestbereich.'],
                    ['range' => '8-10 %', 'label' => 'akzeptabler Mindestbereich', 'variant' => 'secondary', 'description' => 'Mindestbereich für operative Tragfähigkeit.'],
                    ['range' => '10-12 %', 'label' => 'solide', 'variant' => 'primary', 'description' => 'Solide operative Ertragskraft.'],
                    ['range' => 'über 12 %', 'label' => 'stark', 'variant' => 'success', 'description' => 'Starke operative Ertragskraft.'],
                ],
            ],
            [
                'key' => 'dscr',
                'title' => 'DSCR',
                'description' => 'Reinertrag im Verhältnis zum Kapitaldienst aus Zinsen und Tilgung.',
                'items' => [
                    ['range' => 'unter 1,00', 'label' => 'nicht gedeckt', 'variant' => 'danger', 'description' => 'Der Kapitaldienst wird operativ nicht gedeckt.'],
                    ['range' => '1,00-1,10', 'label' => 'gerade bis knapp', 'variant' => 'warning', 'description' => 'Kaum Puffer im laufenden Betrieb.'],
                    ['range' => '1,10-1,20', 'label' => 'knapp', 'variant' => 'warning', 'description' => 'Besser, aber weiterhin eng.'],
                    ['range' => '1,20-1,25', 'label' => 'Mindestbereich', 'variant' => 'secondary', 'description' => 'Unterer Mindestbereich für Schuldendienstdeckung.'],
                    ['range' => '1,25-1,50', 'label' => 'solide', 'variant' => 'primary', 'description' => 'Solide Deckung des Kapitaldienstes.'],
                    ['range' => '1,50-2,00', 'label' => 'stark', 'variant' => 'success', 'description' => 'Starke Schuldendienstdeckung.'],
                    ['range' => 'über 2,00', 'label' => 'sehr stark', 'variant' => 'success', 'description' => 'Sehr hoher operativer Puffer.'],
                ],
            ],
        ];
    }

    public function evaluateNominalLeverage(float $value): array
    {
        return $this->evaluate($value, [
            [0.10, 'schwach', 'danger'],
            [0.15, 'mäßig', 'warning'],
            [0.20, 'brauchbar', 'secondary'],
            [0.25, 'gut', 'primary'],
            [0.30, 'stark', 'success'],
            [INF, 'sehr stark', 'success'],
        ]);
    }

    public function evaluateNpvLeverage(float $value): array
    {
        return $this->evaluate($value, [
            [0.00, 'schlechter als Alternativanlage', 'danger'],
            [0.03, 'knapp positiv', 'warning'],
            [0.06, 'mäßig', 'secondary'],
            [0.10, 'brauchbar bis ordentlich', 'primary'],
            [0.15, 'gut', 'success'],
            [INF, 'stark', 'success'],
        ]);
    }

    public function evaluateDebtYield(float $value): array
    {
        return $this->evaluate($value, [
            [0.05, 'sehr niedrig', 'danger'],
            [0.07, 'schwach', 'warning'],
            [0.08, 'unterer Grenzbereich', 'warning'],
            [0.10, 'akzeptabler Mindestbereich', 'secondary'],
            [0.12, 'solide', 'primary'],
            [INF, 'stark', 'success'],
        ]);
    }

    public function evaluateDscr(float $value): array
    {
        return $this->evaluate($value, [
            [1.00, 'Kapitaldienst wird nicht gedeckt', 'danger'],
            [1.10, 'gerade bis knapp', 'warning'],
            [1.20, 'knapp', 'warning'],
            [1.25, 'Mindestbereich', 'secondary'],
            [1.50, 'solide', 'primary'],
            [2.00, 'stark', 'success'],
            [INF, 'sehr stark', 'success'],
        ]);
    }

    private function evaluate(float $value, array $thresholds): array
    {
        foreach($thresholds as [$limit, $label, $variant]) {
            if($value < $limit) {
                return ['label' => $label, 'variant' => $variant];
            }
        }
        return ['label' => 'n/a', 'variant' => 'secondary'];
    }
}
