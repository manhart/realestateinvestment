<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Support;

use InvalidArgumentException;
use RuntimeException;

final class ScenarioRepository
{
    public function save(string $workspaceKey, string $name, array $scenario, ?string $existingId = null): array
    {
        $this->assertWorkspaceKey($workspaceKey);
        $name = trim($name);
        if($name === '') {
            throw new InvalidArgumentException('Bitte einen Szenarionamen eingeben.');
        }
        $id = $this->scenarioId($name);
        $file = $this->filePath($workspaceKey, $id);
        $oldFile = $existingId ? $this->filePath($workspaceKey, $this->scenarioId($existingId), false) : null;
        if($oldFile && !is_file($oldFile)) {
            throw new InvalidArgumentException('Das aktive Szenario existiert nicht mehr. Bitte als Kopie speichern.');
        }
        if($file !== $oldFile && is_file($file)) {
            throw new InvalidArgumentException('Ein Szenario mit diesem Namen existiert bereits in diesem Workspace.');
        }
        $group = $this->scenarioGroup($scenario);
        $scenario['scenarioName'] = $name;
        $scenario['scenarioGroup'] = $group;
        $updatedAt = date('c');
        $json = json_encode([
            'id' => $id,
            'name' => $name,
            'group' => $group,
            'scenario' => $scenario,
            'updatedAt' => $updatedAt,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $this->writeScenario($file, $json, $file !== $oldFile);
        if($oldFile && $oldFile !== $file && !unlink($oldFile)) {
            unlink($file);
            throw new RuntimeException('Die alte Szenario-Datei konnte nicht entfernt werden.');
        }

        return ['id' => $id, 'name' => $name, 'group' => $group, 'updatedAt' => $updatedAt];
    }

    private function writeScenario(string $file, string $json, bool $createOnly): void
    {
        if($createOnly) {
            $stream = @fopen($file, 'x');
            if(!$stream) {
                throw new RuntimeException('Das Szenario konnte nicht neu angelegt werden. Name oder Schreibrechte prüfen.');
            }
            try {
                if(fwrite($stream, $json) !== strlen($json)) {
                    throw new RuntimeException('Das Szenario konnte nicht vollständig gespeichert werden.');
                }
            } catch(\Throwable $error) {
                fclose($stream);
                unlink($file);
                throw $error;
            }
            fclose($stream);
            return;
        }

        // Replace only after a complete write, leaving the original intact on failure.
        $temporaryFile = tempnam(dirname($file), '.scenario-');
        if(!$temporaryFile) {
            throw new RuntimeException('Das Szenario konnte nicht zum Speichern vorbereitet werden.');
        }
        try {
            if(file_put_contents($temporaryFile, $json, LOCK_EX) !== strlen($json) || !rename($temporaryFile, $file)) {
                throw new RuntimeException('Das Szenario konnte nicht gespeichert werden.');
            }
        } finally {
            if(is_file($temporaryFile)) unlink($temporaryFile);
        }
    }

    public function load(string $workspaceKey, string $id): array
    {
        $this->assertWorkspaceKey($workspaceKey);
        $file = $this->filePath($workspaceKey, $this->scenarioId($id), false);
        if(!is_file($file)) {
            return [];
        }
        $data = json_decode((string)file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        return is_array($data) ? $data : [];
    }

    public function rename(string $workspaceKey, string $id, string $newName): array
    {
        $data = $this->load($workspaceKey, $id);
        if(!$data) return [];
        $scenario = $data['scenario'];
        $scenario['scenarioName'] = $newName;
        return $this->save($workspaceKey, $newName, $scenario, $id) + ['scenario' => $scenario];
    }

    public function delete(string $workspaceKey, string $id): array
    {
        $this->assertWorkspaceKey($workspaceKey);
        $id = $this->scenarioId($id);
        $file = $this->filePath($workspaceKey, $id, false);
        if(!is_file($file)) {
            return ['deleted' => false, 'id' => $id];
        }

        if(!unlink($file)) {
            throw new RuntimeException('Szenario-Datei konnte nicht gelöscht werden.');
        }

        return ['deleted' => true, 'id' => $id];
    }

    public function list(string $workspaceKey): array
    {
        $this->assertWorkspaceKey($workspaceKey);
        $rows = [];
        foreach(glob($this->workspaceDirectory($workspaceKey, false).'/*.json') ?: [] as $file) {
            $data = json_decode((string)file_get_contents($file), true);
            if(is_array($data)) {
                $rows[] = [
                    'id' => $data['id'] ?? basename($file, '.json'),
                    'name' => $data['name'] ?? basename($file, '.json'),
                    'group' => $data['group'] ?? $this->scenarioGroup((array)($data['scenario'] ?? [])),
                    'updatedAt' => $data['updatedAt'] ?? null,
                ];
            }
        }
        usort($rows, static fn(array $a, array $b): int => strcmp((string)($b['updatedAt'] ?? ''), (string)($a['updatedAt'] ?? '')));
        return $rows;
    }

    public function share(string $name, array $scenario): array
    {
        $token = bin2hex(random_bytes(24));
        $group = $this->scenarioGroup($scenario);
        $scenario['scenarioGroup'] = $group;
        $file = $this->shareDirectory().'/'.$token.'.json';

        file_put_contents($file, json_encode([
            'token' => $token,
            'name' => $name,
            'group' => $group,
            'scenario' => $scenario,
            'createdAt' => date('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return ['token' => $token];
    }

    public function importShare(string $workspaceKey, string $token): array
    {
        $this->assertWorkspaceKey($workspaceKey);
        $this->assertToken($token);

        $file = $this->shareDirectory(false).'/'.$token.'.json';
        if(!is_file($file)) {
            return [];
        }

        $data = json_decode((string)file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        if(!is_array($data) || !is_array($data['scenario'] ?? null)) {
            return [];
        }

        $name = $this->copyName((string)($data['name'] ?? 'Szenario'), $workspaceKey);
        $scenario = $data['scenario'];
        $scenario['scenarioName'] = $name;
        return $this->save($workspaceKey, $name, $scenario) + ['scenario' => $scenario];
    }

    private function copyName(string $name, string $workspaceKey): string
    {
        $base = trim($name) !== '' ? trim($name) : 'Szenario';
        $candidate = $base.' Kopie';
        $suffix = 2;
        while(is_file($this->filePath($workspaceKey, $this->scenarioId($candidate), false))) {
            $candidate = $base.' Kopie '.$suffix;
            $suffix++;
        }
        return $candidate;
    }

    private function filePath(string $workspaceKey, string $id, bool $createDirectory = true): string
    {
        return $this->workspaceDirectory($workspaceKey, $createDirectory).'/'.$id.'.json';
    }

    private function workspaceDirectory(string $workspaceKey, bool $create = true): string
    {
        $this->assertWorkspaceKey($workspaceKey);
        $directory = DIR_DATA_ROOT.'/realestateinvestment/workspaces/'.$workspaceKey.'/scenarios';
        if($create && !is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Workspace-Verzeichnis konnte nicht erstellt werden.');
        }
        return $directory;
    }

    private function shareDirectory(bool $create = true): string
    {
        $directory = DIR_DATA_ROOT.'/realestateinvestment/share';
        if($create && !is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Share-Verzeichnis konnte nicht erstellt werden.');
        }
        return $directory;
    }

    private function scenarioGroup(array $scenario): string
    {
        $group = trim((string)($scenario['scenarioGroup'] ?? ''));
        return $group !== '' ? substr($group, 0, 120) : 'Ohne Gruppe';
    }

    private function assertWorkspaceKey(string $workspaceKey): void
    {
        if(!preg_match('/^[a-zA-Z0-9_-]{32,80}$/', $workspaceKey)) {
            throw new InvalidArgumentException('Ungültiger Workspace-Key.');
        }
    }

    private function assertToken(string $token): void
    {
        if(!preg_match('/^[a-f0-9]{48}$/', $token)) {
            throw new InvalidArgumentException('Ungültiger Share-Token.');
        }
    }

    private function scenarioId(string $name): string
    {
        $id = strtolower((string)preg_replace('/[^a-zA-Z0-9_-]+/', '-', $name));
        $id = (string)preg_replace('/-+/', '-', $id);
        $id = trim($id, '-_');
        return $id !== '' ? $id : 'scenario';
    }
}
