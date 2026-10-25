<?php
declare(strict_types=1);

namespace realestateinvestment\guis\GUI_Frame;

use pool\classes\GUI\Builtin\GUI_CustomFrame;
use pool\includes\Resources;
use realestateinvestment\classes\Support\Changelog;
use Template;

final class GUI_Frame extends GUI_CustomFrame
{
    protected array $templates = [
        'stdout' => 'tpl_frame.html',
    ];

    public function prepareContent(): void
    {
        parent::prepareContent();
        $this->Template->setVar('APP_VERSION', $this->Weblication->getVersion(), Template::CONVERT_HTMLSPECIALCHARS);
        $this->prepareChangelog(Changelog::read(DIR_APP_ROOT.'/CHANGELOG.md'));
    }

    private function prepareChangelog(array $releases): void
    {
        if(!$releases) $this->Template->newBlock('CHANGELOG_EMPTY');

        foreach($releases as $release) {
            $this->Template->newBlock('CHANGELOG_RELEASE')?->setVars([
                'RELEASE_TITLE' => $release['version'] === 'Unreleased' ? 'Noch nicht veröffentlicht' : 'Version '.$release['version'],
                'RELEASE_DATE' => $release['date'] ? ' · '.$release['date'] : '',
            ], Template::CONVERT_HTMLSPECIALCHARS);

            foreach($release['groups'] as $group) {
                $this->Template->newBlock('CHANGELOG_CATEGORY')?->setVar('CATEGORY_TITLE', $group['title'], Template::CONVERT_HTMLSPECIALCHARS);
                foreach($group['entries'] as $entry) {
                    $this->Template->newBlock('CHANGELOG_ENTRY')?->setVar('ENTRY_TEXT', $entry, Template::CONVERT_HTMLSPECIALCHARS);
                }
            }
        }
        $this->Template->leaveBlock();
    }

    public function loadFiles(): static
    {
        parent::loadFiles();

        Resources\CSS_bootstrap::addResourceTo($this->getHeadData(), IS_PRODUCTION);
        Resources\CSS_bootstrap_icons::addResourceTo($this->getHeadData(), IS_PRODUCTION);
        Resources\JS__bootstrap::addResourceTo($this->getHeadData(), IS_PRODUCTION, resource: Resources\JS__bootstrap::_bundle);
        Resources\CSS_tabulator::addResourceTo($this->getHeadData(), IS_PRODUCTION, resource: Resources\dir\Dir_tabulator::BS5);
        Resources\JS__tabulator::addResourceTo($this->getHeadData(), IS_PRODUCTION);

        $this->getHeadData()->addJavaScript($this->Weblication->findJavaScript('url.js', '', true));
        $this->getHeadData()->addStyleSheet($this->Weblication->findStyleSheet('app.css'));
        $this->addScriptFileAtTheEnd($this->Weblication->findJavaScript('app.js'));

        return $this;
    }
}
