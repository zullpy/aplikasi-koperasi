<?php
/**
 * satuan_helper.php
 * Helper untuk normalisasi dan pencocokan satuan (grosir & eceran)
 * Menangani variasi penulisan seperti:
 * pack/pak, pcs/pc/buah, bungkus/bks, ball/bal, dus/box/karton, dsb.
 */

if (!function_exists('normalizeSatuan')) {
    function normalizeSatuan($satuan)
    {
        if ($satuan === null || $satuan === '') {
            return '';
        }
        $s = strtolower(trim((string)$satuan));
        $s = preg_replace('/[^a-z0-9]/', '', $s);

        $aliasMap = [
            // Pak / Pack
            'pack'    => 'pak',
            'pck'     => 'pak',
            'pk'      => 'pak',
            'pak'     => 'pak',
            'paks'    => 'pak',
            'packs'   => 'pak',

            // Pcs / Pc / Buah / Biji / Lembar
            'pc'      => 'pcs',
            'pcs'     => 'pcs',
            'piece'   => 'pcs',
            'pieces'  => 'pcs',
            'bh'      => 'pcs',
            'buah'    => 'pcs',
            'biji'    => 'pcs',
            'bj'      => 'pcs',
            'lembar'  => 'pcs',
            'lbr'     => 'pcs',

            // Bungkus
            'bungkus' => 'bungkus',
            'bks'     => 'bungkus',
            'bgks'    => 'bungkus',

            // Ball / Bal
            'ball'    => 'ball',
            'bal'     => 'ball',

            // Dus / Box / Karton
            'dus'     => 'dus',
            'box'     => 'dus',
            'bx'      => 'dus',
            'karton'  => 'karton',
            'krt'     => 'karton',
            'ctn'     => 'karton',
            'ctns'    => 'karton',

            // Gulung / Golong
            'gulung'  => 'gulung',
            'golong'  => 'gulung',
            'glg'     => 'gulung',

            // Ikat
            'ikat'    => 'ikat',
            'ikt'     => 'ikat',

            // Botol
            'botol'   => 'botol',
            'btl'     => 'botol',

            // Kaleng / Can
            'kaleng'  => 'kaleng',
            'klg'     => 'kaleng',
            'can'     => 'kaleng',

            // Jerigen / Jrg
            'jerigen' => 'jerigen',
            'jrg'     => 'jerigen',
            'drigen'  => 'jerigen',

            // Zak / Sak / Karung
            'zak'     => 'zak',
            'sak'     => 'sak',
            'karung'  => 'karung',
            'krg'     => 'karung',

            // Kilogram / Gram / Liter
            'kg'       => 'kg',
            'kilo'     => 'kg',
            'kilogram' => 'kg',
            'gr'       => 'gr',
            'gram'     => 'gr',
            'g'        => 'gr',
            'l'        => 'liter',
            'ltr'      => 'liter',
            'liter'    => 'liter',

            // Lusin
            'lusin'    => 'lusin',
            'lsn'      => 'lusin',
            'doz'      => 'lusin',
            'dozen'    => 'lusin',

            // Roll
            'roll'     => 'roll',
            'rol'      => 'roll',
        ];

        return $aliasMap[$s] ?? $s;
    }
}

if (!function_exists('isSatuanEceranMatch')) {
    /**
     * Memeriksa apakah $satuanInput merupakan satuan eceran yang cocok dengan $satuanEceran
     * dan bukan satuan grosir.
     */
    function isSatuanEceranMatch($satuanInput, $satuanEceran, $satuanGrosir = null)
    {
        $in = normalizeSatuan($satuanInput);
        $ec = normalizeSatuan($satuanEceran);
        $gr = normalizeSatuan($satuanGrosir);

        if ($ec === '') {
            return false;
        }

        // Jika satuan input cocok dengan satuan eceran
        if ($in === $ec) {
            // Pastikan jika satuan grosir dan eceran kebetulan beda, input bukan grosir
            if ($gr === '' || $in !== $gr) {
                return true;
            }
        }

        return false;
    }
}
