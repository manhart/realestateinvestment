<?php
declare(strict_types=1);

namespace realestateinvestment;

use realestateinvestment\classes\RealEstateInvestmentApp;
use realestateinvestment\guis\GUI_Frame\GUI_Frame;
use Throwable;

require_once __DIR__.'/config/bootstrap.php';

$App = RealEstateInvestmentApp::getInstance();

try {
    $App->setup([
        'application.name' => 'realestateinvestment',
        'application.title' => 'Immobilien-Kapitalanlage-Rechner',
        'application.launchModule' => GUI_Frame::class,
    ]);

    $App->render();
}
catch(Throwable $e) {
    throw $e;
}
