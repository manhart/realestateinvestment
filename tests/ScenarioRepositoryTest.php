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

    public function testSavingActiveScenarioUpdatesNameGroupAndInputsWithoutCopy(): void
    {
        $repository = new ScenarioRepository();
        $saved = $repository->save($this->workspaceA, 'Basis', ['scenarioName' => 'Basis', 'scenarioGroup' => 'A']);
        $updated = $repository->save($this->workspaceA, 'Neue Variante', ['scenarioName' => 'Neue Variante', 'scenarioGroup' => 'B', 'rent' => ['apartmentMonthlyRent' => 750]], $saved['id']);

        self::assertSame('neue-variante', $updated['id']);
        self::assertSame([], $repository->load($this->workspaceA, 'basis'));
        self::assertCount(1, $repository->list($this->workspaceA));
        $loaded = $repository->load($this->workspaceA, $updated['id']);
        self::assertSame('B', $loaded['group']);
        self::assertSame('Neue Variante', $loaded['scenario']['scenarioName']);
        self::assertSame(750, $loaded['scenario']['rent']['apartmentMonthlyRent']);
        self::assertArrayHasKey('updatedAt', $updated);
    }

    public function testSavingSameActiveIdUpdatesItsFile(): void
    {
        $repository = new ScenarioRepository();
        $saved = $repository->save($this->workspaceA, 'Basis', []);
        $repository->save($this->workspaceA, 'Basis', ['scenarioGroup' => 'B'], $saved['id']);

        self::assertCount(1, $repository->list($this->workspaceA));
        self::assertSame('B', $repository->load($this->workspaceA, 'basis')['group']);
    }

    public function testCreatingCopyPreservesOriginalInputs(): void
    {
        $repository = new ScenarioRepository();
        $repository->save($this->workspaceA, 'Basis', ['value' => 1]);
        $repository->save($this->workspaceA, 'Basis Kopie', ['value' => 2]);

        self::assertCount(2, $repository->list($this->workspaceA));
        self::assertSame(1, $repository->load($this->workspaceA, 'basis')['scenario']['value']);
        self::assertSame(2, $repository->load($this->workspaceA, 'basis-kopie')['scenario']['value']);
    }

    public function testCreationAndRenameRejectSlugCollisionsWithoutOverwriting(): void
    {
        $repository = new ScenarioRepository();
        $repository->save($this->workspaceA, 'Basis A', ['value' => 1]);
        $repository->save($this->workspaceA, 'Andere', ['value' => 2]);

        foreach([null, 'andere'] as $existingId) {
            try {
                $repository->save($this->workspaceA, 'Basis-A', ['value' => 999], $existingId);
                self::fail('A colliding name must not overwrite an existing scenario.');
            } catch(\InvalidArgumentException $exception) {
                self::assertStringContainsString('existiert bereits', $exception->getMessage());
            }
            self::assertSame(1, $repository->load($this->workspaceA, 'basis-a')['scenario']['value']);
            self::assertSame(2, $repository->load($this->workspaceA, 'andere')['scenario']['value']);
            self::assertCount(2, $repository->list($this->workspaceA));
        }
    }

    public function testCannotUpdateScenarioFromAnotherWorkspaceOrMissingId(): void
    {
        $repository = new ScenarioRepository();
        $repository->save($this->workspaceA, 'Basis', ['value' => 1]);
        try {
            $repository->save($this->workspaceB, 'Neue Variante', ['value' => 2], 'basis');
            self::fail('An active ID must exist in the current workspace.');
        } catch(\InvalidArgumentException $exception) {
            self::assertStringContainsString('existiert nicht mehr', $exception->getMessage());
        }
        self::assertSame([], $repository->list($this->workspaceB));
        self::assertSame(1, $repository->load($this->workspaceA, 'basis')['scenario']['value']);
    }

    public function testEmptyScenarioNameIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ScenarioRepository())->save($this->workspaceA, '  ', []);
    }

    public function testEncodingFailurePreservesOriginalWhenUpdatingOrRenaming(): void
    {
        $repository = new ScenarioRepository();
        $repository->save($this->workspaceA, 'Basis', ['value' => 1]);
        foreach(['Basis', 'Neuer Name'] as $name) {
            try {
                $repository->save($this->workspaceA, $name, ['invalid' => "\xB1"], 'basis');
                self::fail('Invalid JSON input must not produce a successful save.');
            } catch(\JsonException) {
                self::assertSame(1, $repository->load($this->workspaceA, 'basis')['scenario']['value']);
                self::assertCount(1, $repository->list($this->workspaceA));
            }
        }
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
