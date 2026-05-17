<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Inputs;

use realestateinvestment\classes\Support\InputReader;

final class RealEstateInvestmentScenario
{
    /**
     * @param ParkingUnitInput[] $parkingUnits
     * @param LoanInput[] $loans
     */
    public function __construct(
        public string $scenarioName,
        public PropertyInput $property,
        public array $parkingUnits,
        public RentInput $rent,
        public ExpenseInput $expenses,
        public AcquisitionCostInput $acquisitionCosts,
        public ConstructionInterestInput $constructionInterest,
        public array $loans,
        public DepreciationInput $depreciation,
        public TaxInput $tax,
        public SaleInput $sale,
        public CalculationSettings $settings,
    ) {}

    public static function fromArray(array $data): self
    {
        $property = PropertyInput::fromArray((array)($data['property'] ?? []));
        $parkingUnits = array_map(
            static fn(array $row): ParkingUnitInput => ParkingUnitInput::fromArray($row),
            InputReader::list($data, 'parkingUnits'),
        );
        $loans = array_map(
            static fn(array $row): LoanInput => LoanInput::fromArray($row, $property),
            InputReader::list($data, 'loans'),
        );

        return new self(
            InputReader::string($data, 'scenarioName', 'Basisszenario'),
            $property,
            $parkingUnits,
            RentInput::fromArray((array)($data['rent'] ?? [])),
            ExpenseInput::fromArray((array)($data['expenses'] ?? [])),
            AcquisitionCostInput::fromArray((array)($data['acquisitionCosts'] ?? []), $property->state),
            ConstructionInterestInput::fromArray((array)($data['constructionInterest'] ?? [])),
            $loans,
            DepreciationInput::fromArray((array)($data['depreciation'] ?? []), $property),
            TaxInput::fromArray((array)($data['tax'] ?? [])),
            SaleInput::fromArray((array)($data['sale'] ?? [])),
            CalculationSettings::fromArray((array)($data['settings'] ?? [])),
        );
    }
}
