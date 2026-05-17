<?php
declare(strict_types=1);

namespace realestateinvestment\guis\GUI_InvestmentCalculator;

use pool\classes\Core\Input\Input;
use pool\classes\GUI\GUI_Module;
use realestateinvestment\classes\Calculators\InvestmentCalculator;
use realestateinvestment\classes\Inputs\RealEstateInvestmentScenario;
use realestateinvestment\classes\Support\ScenarioRepository;

final class GUI_InvestmentCalculator extends GUI_Module
{
    protected array $templates = [
        'stdout' => 'tpl_investment_calculator.html',
    ];

    protected int $superglobals = Input::POST;

    protected function prepare(): void
    {
        $this->Template->setVar('MODULE_ID', $this->getName());
    }

    protected function registerAjaxCalls(): void
    {
        $this->registerAjaxMethod('calculateScenario', $this->calculateScenario(...));
        $this->registerAjaxMethod('saveScenario', $this->saveScenario(...));
        $this->registerAjaxMethod('renameScenario', $this->renameScenario(...));
        $this->registerAjaxMethod('deleteScenario', $this->deleteScenario(...));
        $this->registerAjaxMethod('loadScenario', $this->loadScenario(...));
        $this->registerAjaxMethod('listScenarios', $this->listScenarios(...));
        $this->registerAjaxMethod('shareScenario', $this->shareScenario(...));
        $this->registerAjaxMethod('importSharedScenario', $this->importSharedScenario(...));
        $this->registerAjaxMethod('compareScenarios', $this->compareScenarios(...));
    }

    protected function calculateScenario(array $scenario): array
    {
        $input = RealEstateInvestmentScenario::fromArray($scenario);
        return (new InvestmentCalculator())->calculate($input)->toArray();
    }

    protected function saveScenario(string $workspaceKey, string $name, array $scenario): array
    {
        return (new ScenarioRepository())->save($workspaceKey, $name, $scenario);
    }

    protected function renameScenario(string $workspaceKey, string $id, string $newName): array
    {
        return (new ScenarioRepository())->rename($workspaceKey, $id, $newName);
    }

    protected function deleteScenario(string $workspaceKey, string $id): array
    {
        return (new ScenarioRepository())->delete($workspaceKey, $id);
    }

    protected function loadScenario(string $workspaceKey, string $id): array
    {
        return (new ScenarioRepository())->load($workspaceKey, $id);
    }

    protected function listScenarios(string $workspaceKey): array
    {
        return ['data' => (new ScenarioRepository())->list($workspaceKey)];
    }

    protected function shareScenario(string $name, array $scenario): array
    {
        return (new ScenarioRepository())->share($name, $scenario);
    }

    protected function importSharedScenario(string $workspaceKey, string $token): array
    {
        return (new ScenarioRepository())->importShare($workspaceKey, $token);
    }

    protected function compareScenarios(array $scenarios): array
    {
        $calculator = new InvestmentCalculator();
        $rows = [];
        foreach($scenarios as $scenario) {
            if(!is_array($scenario)) {
                continue;
            }
            $result = $calculator->calculate(RealEstateInvestmentScenario::fromArray($scenario))->toArray();
            $summary = $result['summary'];
            $rows[] = [
                'scenarioName' => $scenario['scenarioName'] ?? 'Szenario',
                'netWorthEffect' => $summary['netWorthEffect'],
                'leverageEfficiency' => $summary['leverageEfficiency'],
                'npv' => $summary['npv'],
                'npvLeverageEfficiency' => $summary['npvLeverageEfficiency'],
                'futureValueConservative' => $summary['futureValueConservative'],
                'fvLeverageConservative' => $summary['fvLeverageConservative'],
                'futureValueLiquidity' => $summary['futureValueLiquidity'],
                'fvLeverageLiquidity' => $summary['fvLeverageLiquidity'],
                'dscr' => $summary['dscr'],
                'debtYield' => $summary['debtYield'],
            ];
        }
        return ['data' => $rows];
    }
}
