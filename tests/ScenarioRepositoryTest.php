<?php
declare(strict_types=1);

namespace realestateinvestment\tests;

use PHPUnit\Framework\TestCase;
use realestateinvestment\classes\Support\ScenarioRepository;

final class ScenarioRepositoryTest extends TestCase
{
    private string $workspaceA;
    private string $workspaceB;

    protected function setUp(): void
    {
        $suffix = bin2hex(random_bytes(8));
        $this->workspaceA = str_repeat('a', 32).$suffix;
        $this->workspaceB = str_repeat('b', 32).$suffix;
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(DIR_DATA_ROOT.'/realestateinvestment/workspaces/'.$this->workspaceA);
        $this->removeDirectory(DIR_DATA_ROOT.'/realestateinvestment/workspaces/'.$this->workspaceB);
    }

    public function testWorkspacesAreIsolatedAndListIncludesGroups(): void
    {
        $repository = new ScenarioRepository();
        $scenario = ['scenarioName' => 'Basis', 'scenarioGroup' => 'Objekt A'];

        $saved = $repository->save($this->workspaceA, 'Basis', $scenario);

        self::assertSame('basis', $saved['id']);
        self::assertSame('Objekt A', $repository->list($this->workspaceA)[0]['group']);
        self::assertSame([], $repository->list($this->workspaceB));
        self::assertSame([], $repository->load($this->workspaceB, 'basis'));
        self::assertSame('Basis', $repository->load($this->workspaceA, 'basis')['name']);
    }

    public function testSharedScenarioImportsAsCopyIntoTargetWorkspace(): void
    {
        $repository = new ScenarioRepository();
        $scenario = ['scenarioName' => 'Basis', 'scenarioGroup' => 'Objekt A'];

        $share = $repository->share('Basis', $scenario);
        $imported = $repository->importShare($this->workspaceB, $share['token']);

        self::assertNotSame('basis', $imported['id']);
        self::assertSame('Basis Kopie', $imported['name']);
        self::assertSame('Basis Kopie', $imported['scenario']['scenarioName']);
        self::assertSame([], $repository->list($this->workspaceA));
        self::assertCount(1, $repository->list($this->workspaceB));

        @unlink(DIR_DATA_ROOT.'/realestateinvestment/share/'.$share['token'].'.json');
    }

    public function testRenameMovesScenarioInsteadOfCreatingCopy(): void
    {
        $repository = new ScenarioRepository();
        $scenario = ['scenarioName' => 'Basis', 'scenarioGroup' => 'Objekt A', 'property' => ['name' => 'Objekt']];

        $repository->save($this->workspaceA, 'Basis', $scenario);
        $renamed = $repository->rename($this->workspaceA, 'basis', 'Basis umbenannt');

        self::assertSame('basis-umbenannt', $renamed['id']);
        self::assertSame('Basis umbenannt', $renamed['name']);
        self::assertSame('Basis umbenannt', $renamed['scenario']['scenarioName']);
        self::assertSame([], $repository->load($this->workspaceA, 'basis'));
        self::assertSame('Objekt A', $repository->load($this->workspaceA, 'basis-umbenannt')['group']);
        self::assertCount(1, $repository->list($this->workspaceA));
    }

    public function testDeleteRemovesOnlySelectedWorkspaceScenario(): void
    {
        $repository = new ScenarioRepository();
        $scenario = ['scenarioName' => 'Basis', 'scenarioGroup' => 'Objekt A'];

        $repository->save($this->workspaceA, 'Basis', $scenario);
        $repository->save($this->workspaceB, 'Basis', $scenario);
        $deleted = $repository->delete($this->workspaceA, 'basis');

        self::assertTrue($deleted['deleted']);
        self::assertSame([], $repository->load($this->workspaceA, 'basis'));
        self::assertCount(0, $repository->list($this->workspaceA));
        self::assertSame('Basis', $repository->load($this->workspaceB, 'basis')['name']);
    }

    private function removeDirectory(string $directory): void
    {
        if(!is_dir($directory)) {
            return;
        }
        foreach(glob($directory.'/*') ?: [] as $path) {
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($directory);
    }
}
