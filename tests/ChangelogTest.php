<?php
declare(strict_types=1);

namespace realestateinvestment\tests;

use PHPUnit\Framework\TestCase;
use realestateinvestment\classes\RealEstateInvestmentApp;
use realestateinvestment\classes\Support\Changelog;
use realestateinvestment\guis\GUI_Frame\GUI_Frame;
use ReflectionClass;
use ReflectionMethod;
use Template;

final class ChangelogTest extends TestCase
{
    public function testParsesVersionsCategoriesAndEntriesInDocumentOrder(): void
    {
        $notes = Changelog::parse("# Changelog\n\n## [Unreleased]\n### Neu\n- Neue Funktion\n### Korrigiert\n- Fehler behoben\n## [0.1.0] - 2026-10-24\n### Neu\n- Erster Stand\n");

        self::assertSame(['Unreleased', '0.1.0'], array_column($notes, 'version'));
        self::assertNull($notes[0]['date']);
        self::assertSame('2026-10-24', $notes[1]['date']);
        self::assertSame([
            ['title' => 'Neu', 'entries' => ['Neue Funktion']],
            ['title' => 'Korrigiert', 'entries' => ['Fehler behoben']],
        ], $notes[0]['groups']);
        self::assertSame([['title' => 'Neu', 'entries' => ['Erster Stand']]], $notes[1]['groups']);
    }

    public function testWindowsLineEndingsAndUnsupportedText(): void
    {
        $notes = Changelog::parse("# Changelog\r\n- Ohne Version\r\n## [0.1.0] - 2026-10-24\r\n- Ohne Kategorie\r\n### Neu\r\nAbsatz\r\n- Eintrag\r\n");
        self::assertCount(1, $notes);
        self::assertSame([['title' => 'Neu', 'entries' => ['Eintrag']]], $notes[0]['groups']);
        self::assertSame([], Changelog::parse('# Keine Versionen'));
    }

    public function testMissingFileDoesNotBreakThePage(): void
    {
        self::assertSame([], Changelog::read(__DIR__.'/missing-changelog.md'));
        $html = $this->render([]);
        self::assertStringContainsString('Das Changelog ist derzeit nicht verfügbar.', $html);
        self::assertStringNotContainsString('<section class="rei-changelog-release">', $html);
    }

    public function testCurrentVersionMatchesNewestDocumentedVersion(): void
    {
        $notes = Changelog::read(DIR_APP_ROOT.'/CHANGELOG.md');
        self::assertNotEmpty($notes);
        self::assertSame('Unreleased', $notes[0]['version']);
        $versions = array_values(array_filter($notes, static fn(array $note): bool => $note['version'] !== 'Unreleased'));
        self::assertNotEmpty($versions);
        self::assertSame(RealEstateInvestmentApp::VERSION, $versions[0]['version']);
        foreach($versions as $version) {
            self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/D', $version['version']);
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/D', $version['date']);
        }
    }

    public function testTemplateKeepsReleaseAndCategoryEntriesSeparate(): void
    {
        $notes = Changelog::parse("## [Unreleased]\n### Neu\n- Neu A\n- Neu B\n### Korrigiert\n- Fix C\n## [0.1.0] - 2026-10-24\n### Neu\n- Basis D\n");
        $html = $this->render($notes);
        self::assertSame(2, substr_count($html, '<section class="rei-changelog-release">'));
        self::assertSame(3, substr_count($html, '<h4>'));
        foreach(['Neu A', 'Neu B', 'Fix C', 'Basis D'] as $entry) {
            self::assertSame(1, substr_count($html, "<li>$entry</li>"));
        }
        self::assertStringContainsString('Noch nicht veröffentlicht', $html);
        self::assertStringContainsString('Version 0.1.0', $html);
        self::assertStringNotContainsString('Das Changelog ist derzeit nicht verfügbar.', $html);
        self::assertStringNotContainsString('{ENTRY_TEXT}', $html);
    }

    public function testTemplateEscapesChangelogTextInsteadOfExecutingHtml(): void
    {
        $notes = Changelog::parse("## [Unreleased]\n### <img src=x onerror=alert(1)>\n- <script>alert(\"x\")</script> & Text\n");
        $html = $this->render($notes);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        self::assertStringContainsString('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; Text', $html);
        self::assertStringNotContainsString('<script>alert', $html);
        self::assertStringNotContainsString('<img src=x', $html);
    }

    private function render(array $notes): string
    {
        $frame = (new ReflectionClass(GUI_Frame::class))->newInstanceWithoutConstructor();
        $frame->Template = new Template();
        $frame->Template->setContent('stdout', file_get_contents(DIR_APP_ROOT.'/guis/GUI_Frame/tpl_frame.html'));
        (new ReflectionMethod(GUI_Frame::class, 'prepareChangelog'))->invoke($frame, $notes);
        $frame->Template->parse('stdout');
        return $frame->Template->getContent('stdout');
    }
}
