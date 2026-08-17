<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Sanitasi HTML isi surat hasil editor (Quill) SEBELUM disimpan, karena
 * halaman cetak merendernya mentah ({!! !!}). Allowlist sengaja sempit:
 * hanya elemen dan gaya yang dihasilkan toolbar ringkas + tabel data diri.
 * HTMLPurifier dipilih (bukan allowlist DOMDocument tulisan tangan) karena
 * ia memperbaiki HTML rusak dan menangkal obfuscation atribut event
 * (onclick dkk.) maupun skema javascript:.
 */
class PembersihHtmlSurat
{
    private static ?HTMLPurifier $purifier = null;

    public static function bersihkan(string $html): string
    {
        if (self::$purifier === null) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('HTML.Allowed', implode(',', [
                'p[style|class]', 'br', 'strong', 'b', 'em', 'i', 'u', 's',
                'span[style|class]', 'div[style|class]',
                'table[style|class]', 'tbody', 'tr', 'td[style|colspan|rowspan]',
                'ol', 'ul', 'li[class]',
            ]));
            $config->set('CSS.AllowedProperties', ['text-align', 'font-size']);
            $config->set('Attr.AllowedClasses', [
                'data-diri',
                'ql-align-center', 'ql-align-right', 'ql-align-justify',
                'ql-size-small', 'ql-size-large', 'ql-size-huge',
            ]);
            // Tanpa cache berkas: definisi dibangun per proses, cukup untuk
            // skala trafik surat dan aman di tes (:memory:).
            $config->set('Cache.DefinitionImpl', null);

            self::$purifier = new HTMLPurifier($config);
        }

        return self::$purifier->purify($html);
    }
}
