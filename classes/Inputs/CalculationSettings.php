<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Inputs;

use realestateinvestment\classes\Support\InputReader;

final class CalculationSettings
{
    public function __construct(
        public float $discountRate,
        public string $roundingMode,
        public float $initialEquityAmount,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            InputReader::rate($data, 'discountRatePercent', 5),
            InputReader::string($data, 'roundingMode', 'commercial'),
            InputReader::float($data, 'initialEquityAmount'),
        );
    }
}
