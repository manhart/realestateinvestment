<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Inputs;

use realestateinvestment\classes\Support\InputReader;

final class ConstructionInterestYearInput
{
    public function __construct(
        public string $label,
        public int $year,
        public float $amount,
        public bool $deductible,
        public bool $financed,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            InputReader::string($data, 'label', 'Bauzeitzinsen'),
            InputReader::int($data, 'year'),
            InputReader::float($data, 'amount'),
            InputReader::bool($data, 'deductible', true),
            InputReader::bool($data, 'financed'),
        );
    }
}
