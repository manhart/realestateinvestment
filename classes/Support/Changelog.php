<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Support;

final class Changelog
{
    public static function read(string $filename): array
    {
        if(!is_file($filename) || !is_readable($filename)) return [];

        $content = file_get_contents($filename);
        return $content === false ? [] : self::parse($content);
    }

    public static function parse(string $content): array
    {
        $releases = [];
        $releaseIndex = null;
        $groupIndex = null;

        // Only version headings, category headings and single-line list items are supported.
        foreach(explode("\n", $content) as $line) {
            $line = rtrim($line, "\r");
            if(preg_match('/^## \[(Unreleased|\d+\.\d+\.\d+)\](?: - (\d{4}-\d{2}-\d{2}))?$/D', $line, $matches)) {
                $releases[] = ['version' => $matches[1], 'date' => $matches[2] ?? null, 'groups' => []];
                $releaseIndex = array_key_last($releases);
                $groupIndex = null;
            } elseif($releaseIndex !== null && str_starts_with($line, '### ')) {
                $releases[$releaseIndex]['groups'][] = ['title' => substr($line, 4), 'entries' => []];
                $groupIndex = array_key_last($releases[$releaseIndex]['groups']);
            } elseif($releaseIndex !== null && $groupIndex !== null && str_starts_with($line, '- ')) {
                $releases[$releaseIndex]['groups'][$groupIndex]['entries'][] = substr($line, 2);
            }
        }

        return $releases;
    }
}
