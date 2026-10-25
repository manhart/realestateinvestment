<?php
declare(strict_types=1);

namespace realestateinvestment\tests;

use PHPUnit\Framework\TestCase;
use realestateinvestment\classes\Calculators\DepreciationCalculator;
use realestateinvestment\classes\Inputs\DepreciationInput;
use realestateinvestment\classes\Inputs\PropertyInput;

final class DepreciationCalculatorTest extends TestCase
{
    private function input(array $data = []): DepreciationInput
    {
        return DepreciationInput::fromArray($data + [
            'startYear' => 2025, 'startMonth' => 1, 'buildingBasis' => 100000,
            'degressiveActive' => true, 'autoSwitchToLinear' => true,
        ], PropertyInput::fromArray(['completionYear' => 2025]));
    }

    public function testStatutoryRatesDoNotAdoptImportedComparisonRates(): void
    {
        foreach([1924 => [0.025, 40], 1925 => [0.02, 50], 2022 => [0.02, 50], 2023 => [0.03, 33]] as $year => [$rate, $life]) {
            $input = DepreciationInput::fromArray(['linearRatePercent' => 2.29], PropertyInput::fromArray(['completionYear' => $year]));
            self::assertSame($rate, $input->linearRate);
            self::assertEquals($life, $input->usefulLifeYears);
            self::assertSame(0.0229, $input->enteredLinearRate);
        }
        $manual = $this->input(['linearRateOverrideEnabled' => true, 'linearRatePercent' => 4, 'usefulLifeYears' => 25]);
        self::assertSame(0.04, $manual->linearRate);
        self::assertSame(25.0, $manual->usefulLifeYears);
    }

    public function testSwitchUsesRemainingLifeAndIsPermanent(): void
    {
        $rows = (new DepreciationCalculator())->calculate($this->input(), 2025, 2085, 12);
        self::assertSame('degressive', $rows[2038]['method']);
        self::assertSame('linear', $rows[2039]['method']);
        self::assertEqualsWithDelta($rows[2039]['bookValueStart'] / 19, $rows[2039]['linear'], 0.00001);
        foreach(array_slice($rows, 14, null, true) as $row) self::assertSame('linear', $row['method']);
        self::assertEqualsWithDelta(100000, array_sum(array_column($rows, 'total')), 0.000001);
        self::assertSame(0.0, $rows[2085]['total']);
    }

    public function testEachAssetStopsAtItsOwnBasisIncludingPartialYears(): void
    {
        $input = $this->input(['startMonth' => 10, 'furnitureBasis' => 9900, 'furnitureRatePercent' => 10]);
        $input->parkingDepreciationItems = [
            ['label' => 'A', 'basis' => 10000.0, 'rate' => 0.03, 'startYear' => 2025, 'startMonth' => 10],
            ['label' => 'B', 'basis' => 5000.0, 'rate' => 0.0526, 'startYear' => 2030, 'startMonth' => 3],
        ];
        $rows = (new DepreciationCalculator())->calculate($input, 2025, 2085, 12);
        self::assertEqualsWithDelta(9900, array_sum(array_column($rows, 'furniture')), 0.000001);
        self::assertEqualsWithDelta(15000, array_sum(array_column($rows, 'parking')), 0.000001);
        self::assertEqualsWithDelta(742.5, $rows[2035]['furniture'], 0.000001);
        self::assertSame(0.0, $rows[2036]['furniture']);
        self::assertEqualsWithDelta(10000, array_sum(array_map(static fn(array $row): float => $row['parkingItems'][0]['amount'], $rows)), 0.000001);
        self::assertEqualsWithDelta(5000, array_sum(array_map(static fn(array $row): float => $row['parkingItems'][1]['amount'], $rows)), 0.000001);
        foreach($rows as $row) self::assertEqualsWithDelta($row['degressive'] + $row['linear'] + $row['special7b'] + $row['furniture'] + $row['parking'], $row['total'], 0.000001);
    }

    public function testBmfDecliningBalanceExampleKeepsSeparateStreamsForFourYears(): void
    {
        $input = $this->input(['buildingBasis' => 1000000, 'special7bActive' => true, 'special7bBasis' => 1000000]);
        $rows = (new DepreciationCalculator())->calculate($input, 2025, 2029, 12);
        foreach([2025 => 50000, 2026 => 47500, 2027 => 45125, 2028 => 42868.75, 2029 => 30725.3125] as $year => $expected) {
            self::assertEqualsWithDelta($expected, $rows[$year]['degressive'], 0.000001);
            self::assertEqualsWithDelta($year < 2029 ? 50000 : 0, $rows[$year]['special7b'], 0.000001);
        }
    }

    public function testLinearAfaIsRebasedAfterSpecialDepreciation(): void
    {
        $input = $this->input(['buildingBasis' => 1000000, 'degressiveActive' => false, 'special7bActive' => true, 'special7bBasis' => 1000000]);
        $rows = (new DepreciationCalculator())->calculate($input, 2025, 2029, 12);
        self::assertEqualsWithDelta(30000, $rows[2025]['linear'], 0.000001);
        self::assertEqualsWithDelta(680000 / 29, $rows[2029]['linear'], 0.000001);
    }

    public function testOldPeriodEndsClaimsIn2026ButRebasesOnlyAfterFourYears(): void
    {
        $input = $this->input(['startYear' => 2024, 'degressiveActive' => false, 'special7bActive' => true, 'special7bBasis' => 100000]);
        $input->special7bLastYear = 2026;
        $rows = (new DepreciationCalculator())->calculate($input, 2024, 2028, 12);
        self::assertEqualsWithDelta(5000, $rows[2026]['special7b'], 0.000001);
        self::assertEqualsWithDelta(0, $rows[2027]['special7b'], 0.000001);
        self::assertEqualsWithDelta(3000, $rows[2027]['linear'], 0.000001);
        self::assertEqualsWithDelta(73000 / 29, $rows[2028]['linear'], 0.000001);
    }

    public function testShortReportStillAccountsForPreviousDepreciation(): void
    {
        $calculator = new DepreciationCalculator();
        $full = $calculator->calculate($this->input(['furnitureBasis' => 10000]), 2025, 2040, 12);
        $late = $calculator->calculate($this->input(['furnitureBasis' => 10000]), 2037, 2040, 12);
        self::assertSame($full[2037], $late[2037]);
    }

    public function testUnusedSpecialAllowanceDoesNotRebaseLinearAfa(): void
    {
        $input = $this->input(['degressiveActive' => false, 'special7bActive' => true, 'special7bBasis' => 100000, 'special7bYears' => 0]);
        $rows = (new DepreciationCalculator())->calculate($input, 2025, 2030, 12);
        self::assertSame(3000.0, $rows[2029]['linear']);
    }
}
