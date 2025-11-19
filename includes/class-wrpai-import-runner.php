<?php

if (!defined('ABSPATH')) exit;

class WRPAI_Import_Runner {

    /** @var array<string, string> */
    private $language_names = [
        'DE' => 'German',
        'EN' => 'English',
        'ES' => 'Spanish',
        'FR' => 'French',
        'IT' => 'Italian',
        'PT' => 'Portuguese',
        'RU' => 'Russian',
        'TR' => 'Turkish',
        'UN' => 'Unknown',
    ];

    /**
     * CSV import çalıştırıcı
     *
     * @param array $rows
     * @return array{groups:int,audio:int,pdf:int}
     */
    public function run($rows) {

        if (!is_array($rows)) {
            $rows = [];
        }

        // Audio için: grup + kategori
        $groups     = []; // WRAP audio player grupları
        $categories = [];

        // PDF için: group_id => satır listesi
        $pdf_groups = [];

        foreach ($rows as $row) {

            if (!is_array($row)) {
                continue;
            }

            $format = isset($row['format']) ? trim((string) $row['format']) : '';
            if ($format === '') {
                continue;
            }

            // Ortak group_id kontrolü
            $group_id = isset($row['group_id']) ? trim((string) $row['group_id']) : '';
            if ($group_id === '') {
                continue;
            }

            /*
             * 1) AUDIO BOOK → WRAP AUDIO PLAYER
             */
            if (strcasecmp($format, 'Audio Book') === 0) {

                // Dil kodu normalize → satıra göm
                $lang_code = $this->normalize_language_code($row['language'] ?? '');
                $row['_wrpai_lang_code'] = $lang_code;

                // Kategori oluştur (id => name)
                $this->ensure_category($lang_code, $categories);

                // Audio grubuna ekle
                if (!isset($groups[$group_id])) {
                    $groups[$group_id] = [];
                }
                $groups[$group_id][] = $row;

                continue;
            }

            /*
             * 2) PDF → WRPR PDF READER
             */
            if (strcasecmp($format, 'PDF') === 0) {

                if (!isset($pdf_groups[$group_id])) {
                    $pdf_groups[$group_id] = [];
                }
                $pdf_groups[$group_id][] = $row;

                continue;
            }

            // Diğer formatlar yok sayılır
        }

        // Sonuç sayacı
        $result = [
            'groups' => count($groups),  // audio group sayısı (eski anahtar korunuyor)
            'audio'  => 0,
            'pdf'    => 0,
        ];

        /*
         * --------------- 1) AUDIO IMPORT (WRAP) ---------------
         */
        if (!empty($groups)) {
            // Mevcut player deposu
            $players = get_option('wrap_players', []);
            if (!is_array($players)) {
                $players = [];
            }

            // Her group_id → bir player
            foreach ($groups as $group_id => $items) {
                $player = $this->build_player($group_id, $items, $categories);

                // Array key = slug (delete vb. düzgün çalışıyor)
                $players[$player['slug']] = $player;
                $result['audio']++;
            }

            update_option('wrap_players', $players);
            update_option('wrap_categories', $categories);
        }

        /*
         * --------------- 2) PDF IMPORT (WRPR) – FINAL VERSION ---------------
         *
         * CSV'de format = "PDF" olan satırlardan:
         * - wrpr_readers option'ına reader kaydı
         * - her reader için books dizisi üretir.
         *
         * PDF Player, Audio Player gibi dil filtresi kullandığı için:
         * - Her book.language = tam metin ("English")
         * - Her book.category = dil kodu ("en")
         * - Reader.categories = unique dil listesi
         * - reader_id hem array key'i hem de 'id' alanı
         */
        if (!empty($pdf_groups)) {

            $readers = get_option('wrpr_readers', []);
            if (!is_array($readers)) {
                $readers = [];
            }

            foreach ($pdf_groups as $group_id => $items) {

                // Başlık (reader adı)
                $first = reset($items);
                $title = isset($first['product_title'])
                    ? sanitize_text_field($first['product_title'])
                    : (string) $group_id;

                // Reader için stabil base string
                $slug_base   = $title . '-' . $group_id;
                $reader_slug = sanitize_title($slug_base);

                // Reader ID – hem key hem de value['id']
                $reader_id = 'wrpr_' . abs(crc32(sanitize_title($slug_base)));

                // Books + kategori listeleri
                $books          = [];
                $unique_langs   = [];   // "English", "German"...
                $unique_cat_ids = [];   // "en", "de"...

                foreach ($items as $row) {

                    // Dil kodu
                    $raw_lang  = strtoupper(trim((string) ($row['language'] ?? 'UN')));
                    $lang_name = $this->language_names[$raw_lang] ?? $raw_lang; // "English"
                    $cat_id    = strtolower($raw_lang); // "en"

                    // Benzersiz listelere ekle
                    if (!in_array($lang_name, $unique_langs, true)) {
                        $unique_langs[] = $lang_name;
                    }
                    if (!in_array($cat_id, $unique_cat_ids, true)) {
                        $unique_cat_ids[] = $cat_id;
                    }

                    // Book nesnesi (PDF Player için tam uyumlu)
                    $books[] = [
                        'title'     => sanitize_text_field($row['product_title'] ?? ''),
                        'author'    => '',
                        'language'  => $lang_name,      // dropdown için
                        'category'  => $cat_id,         // JS filtre için
                        'image_url' => '',
                        'pdf_url'   => esc_url_raw($row['file_urls'] ?? ''),
                        'buy_link'  => esc_url_raw($row['buy_link'] ?? ''),
                    ];
                }

                // FINAL READER OBJESI
                $readers[$reader_id] = [
                    'id'         => $reader_id,
                    'name'       => $title,
                    'slug'       => $reader_slug,
                    'books'      => $books,
                    'categories' => $unique_langs,   // dil listesi (opsiyonel ama faydalı)
                    'cat_ids'    => $unique_cat_ids, // shorthand (opsiyonel)
                ];

                $result['pdf']++;
            }

            update_option('wrpr_readers', $readers);
        }

        return $result;
    }

    /**
     * Player oluşturur (AUDIO / WRAP)
     *
     * @param string $group_id
     * @param array  $items
     * @param array  $categories
     * @return array
     */
    private function build_player($group_id, $items, array &$categories) {

        $first = reset($items);

        $title = isset($first['product_title']) ? sanitize_text_field($first['product_title']) : (string) $group_id;

        // String slug üret
        $slug = $this->generate_player_slug($title, $group_id);

        // Sayısal id (admin edit tracks ekranı için)
        $numeric_id = abs(crc32($slug));

        $player = [
            'id'          => $numeric_id,   // int
            'slug'        => $slug,         // string, shortcode ve admin listesi bunu kullanıyor
            'name'        => $title,
            'description' => '',
            'category'    => '',
            'language'    => '',
            'settings'    => ['autoplay' => false],
            'track_ids'   => [],
            'tracks'      => [],
        ];

        foreach ($items as $row) {

            // DİL → _wrpai_lang_code üzerinden
            $lang_code = strtoupper(trim((string) ($row['_wrpai_lang_code'] ?? 'EN')));
            $cat_id    = strtolower($lang_code);

            // Kategori yoksa oluştur (güvenlik için bir kez daha)
            if (!isset($categories[$cat_id])) {
                $categories[$cat_id] = [
                    'id'   => $cat_id,
                    'name' => $this->language_names[$lang_code] ?? $lang_code,
                ];
            }

            // Track ekle
            $player['tracks'][] = $this->build_track($row, $cat_id);
        }

        return $player;
    }

    /**
     * CSV satırından track çıkarır (AUDIO)
     *
     * @param array  $row
     * @param string $cat_id
     * @return array
     */
    private function build_track($row, $cat_id) {

        return [
            'title'    => sanitize_text_field($row['product_title'] ?? ''),
            'author'   => '',
            'category' => $cat_id,                                   // 'en', 'de', 'fr'...
            'img'      => '',
            'mp3'      => esc_url_raw($row['file_urls'] ?? ''),
            'ogg'      => '',
            'buy'      => esc_url_raw($row['buy_link'] ?? ''),
            'lyrics'   => '',
        ];
    }

    /**
     * Slug üretir (string) – AUDIO player için
     *
     * @param string $title
     * @param string $group_id
     * @return string
     */
    private function generate_player_slug($title, $group_id) {

        $base = $title . '-' . $group_id;

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $base);
            if ($converted !== false) {
                $base = $converted;
            }
        }

        $base = preg_replace('/[^a-zA-Z0-9\s-]/', '', $base);
        $base = str_replace(' ', '-', $base);

        $slug = sanitize_title(strtolower($base));

        if (empty($slug)) {
            $slug = 'wr-player-' . (int) $group_id;
        }

        if (empty($slug)) {
            $slug = 'wr-player-' . wp_unique_id();
        }

        return $slug;
    }

    /**
     * Dil normalize
     *
     * @param string $language
     * @return string
     */
    private function normalize_language_code($language) {
        $lang = strtoupper(trim((string) $language));
        return ($lang === '') ? 'UN' : $lang;
    }

    /**
     * Kategori ekle (id => name) – AUDIO için
     *
     * @param string $lang_code
     * @param array  $categories
     * @return void
     */
    private function ensure_category($lang_code, array &$categories) {

        $cat_id   = strtolower($lang_code);
        $cat_name = $this->language_names[$lang_code] ?? $lang_code;

        if (!isset($categories[$cat_id])) {
            $categories[$cat_id] = [
                'id'   => $cat_id,
                'name' => $cat_name,
            ];
        }
    }
}
