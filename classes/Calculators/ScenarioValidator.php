<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Calculators;

use realestateinvestment\classes\Inputs\RealEstateInvestmentScenario;

final class ScenarioValidator
{
    public function validate(RealEstateInvestmentScenario $scenario, float $totalCosts, float $initialDebt): array
    {
        $warnings = [];
        $property = $scenario->property;

        if($property->apartmentPurchasePrice <= 0) {
            $warnings[] = ['level' => 'danger', 'message' => 'Kaufpreis muss größer als 0 sein.'];
        }
        if($property->saleYear <= $property->purchaseYear) {
            $warnings[] = ['level' => 'danger', 'message' => 'Verkaufsjahr muss nach dem Kaufjahr liegen.'];
        }
        if($this->ym($property->rentStartYear, $property->rentStartMonth) < $this->ym($property->purchaseYear, $property->purchaseMonth)) {
            $warnings[] = ['level' => 'danger', 'message' => 'Mietbeginn darf nicht vor dem Kaufdatum liegen.'];
        }
        if($this->ym($property->completionYear, $property->completionMonth) > $this->ym($property->rentStartYear, $property->rentStartMonth)) {
            $warnings[] = ['level' => 'warning', 'message' => 'Fertigstellung liegt nach Mietbeginn.'];
        }
        if(abs(($property->landShare + $property->buildingShare) - 1.0) > 0.0001) {
            $warnings[] = ['level' => 'danger', 'message' => 'Summe aus Grundanteil und Gebäudeanteil muss 100 % ergeben.'];
        }
        if($scenario->rent->vacancyRate < 0 || $scenario->rent->vacancyRate > 1) {
            $warnings[] = ['level' => 'danger', 'message' => 'Leerstand muss zwischen 0 und 100 % liegen.'];
        }
        if($scenario->acquisitionCosts->realEstateTransferTax < 0 || $scenario->acquisitionCosts->realEstateTransferTax > 0.10) {
            $warnings[] = ['level' => 'danger', 'message' => 'Grunderwerbsteuer muss zwischen 0 und 10 % liegen.'];
        }
        if($scenario->settings->discountRate < 0) {
            $warnings[] = ['level' => 'danger', 'message' => 'Diskontsatz darf nicht negativ sein.'];
        }
        foreach($scenario->loans as $loan) {
            if($loan->interestRate < 0 || $loan->initialRepaymentRate < 0 || $loan->principal < 0) {
                $warnings[] = ['level' => 'danger', 'message' => "Darlehen {$loan->name}: Zinssatz, Tilgung und Kredithöhe dürfen nicht negativ sein."];
            }
        }
        if($totalCosts > 0 && $initialDebt > $totalCosts) {
            $warnings[] = ['level' => 'warning', 'message' => 'Kreditbeträge übersteigen die Gesamtkosten (>100 %-Finanzierung).'];
        }

        return $warnings;
    }

    private function ym(int $year, int $month): int
    {
        return $year * 12 + $month;
    }
}
