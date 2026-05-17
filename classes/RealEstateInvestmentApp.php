<?php
declare(strict_types=1);

namespace realestateinvestment\classes;

use pool\classes\Core\Weblication;

final class RealEstateInvestmentApp extends Weblication
{
    public function setup(array $settings = []): static
    {
        parent::setup($settings);
        return $this;
    }
}
