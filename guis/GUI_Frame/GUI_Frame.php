<?php
declare(strict_types=1);

namespace realestateinvestment\guis\GUI_Frame;

use pool\classes\GUI\Builtin\GUI_CustomFrame;
use pool\includes\Resources;

final class GUI_Frame extends GUI_CustomFrame
{
    protected array $templates = [
        'stdout' => 'tpl_frame.html',
    ];

    public function loadFiles(): static
    {
        parent::loadFiles();

        Resources\CSS_bootstrap::addResourceTo($this->getHeadData(), IS_PRODUCTION);
        Resources\JS__bootstrap::addResourceTo($this->getHeadData(), IS_PRODUCTION, resource: Resources\JS__bootstrap::_bundle);
        Resources\CSS_tabulator::addResourceTo($this->getHeadData(), IS_PRODUCTION, resource: Resources\dir\Dir_tabulator::BS5);
        Resources\JS__tabulator::addResourceTo($this->getHeadData(), IS_PRODUCTION);

        $this->getHeadData()->addJavaScript($this->Weblication->findJavaScript('url.js', '', true));
        $this->getHeadData()->addStyleSheet($this->Weblication->findStyleSheet('app.css'));
        $this->addScriptFileAtTheEnd($this->Weblication->findJavaScript('app.js'));

        return $this;
    }
}
