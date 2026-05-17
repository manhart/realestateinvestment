<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Support;

final class OfficialSourceRegistry
{
    /**
     * @return array{label: string, url: string}
     */
    public static function get(string $key): array
    {
        return self::all()[$key] ?? ['label' => 'Quelle', 'url' => ''];
    }

    private static function all(): array
    {
        return [
            'estg_9_werbungskosten' => [
                'label' => '§ 9 EStG',
                'url' => 'https://www.gesetze-im-internet.de/estg/__9.html',
            ],
            'estg_21_vuv' => [
                'label' => '§ 21 EStG',
                'url' => 'https://www.gesetze-im-internet.de/estg/__21.html',
            ],
            'estg_32a' => [
                'label' => '§ 32a EStG',
                'url' => 'https://www.gesetze-im-internet.de/estg/__32a.html',
            ],
            'solzg_2026' => [
                'label' => 'SolzG 2026',
                'url' => 'https://esth.bundesfinanzministerium.de/lsth/2026/B-Anhaenge/Anhang-27/I/inhalt.html',
            ],
            'kirchensteuer' => [
                'label' => 'Kirchensteuer',
                'url' => 'https://www.kirchensteuer-wirkt.de/hoehe-kirchensteuer',
            ],
            'esth_21_vuv' => [
                'label' => 'EStH § 21',
                'url' => 'https://esth.bundesfinanzministerium.de/esth/2024/A-Einkommensteuergesetz/II-Einkommen-2-24b/8-Die-einzelnen-Einkunftsarten-13-24b/f-Vermietung-und-Verpachtung-21/Paragraf-21/inhalt.html',
            ],
            'hgb_255_anschaffungskosten' => [
                'label' => '§ 255 HGB',
                'url' => 'https://www.gesetze-im-internet.de/hgb/__255.html',
            ],
            'esth_7b' => [
                'label' => 'EStH § 7b',
                'url' => 'https://esth.bundesfinanzministerium.de/esth/2024/A-Einkommensteuergesetz/II-Einkommen-2-24b/3-Gewinn-4-7i/Paragraf-7b/inhalt.html',
            ],
        ];
    }
}
