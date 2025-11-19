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

        $groups     = [];
        $categories = [];

        foreach ($rows as $row) {

            if (!is_array($row)) {
                continue;
            }

            // Format kontrolü
            $format = isset($row['format']) ? trim((string) $row['format']) : '';
            if ($format === '' || strcasecmp($format, 'Audio Book') !== 0) {
                continue;
            }

            // Grup ID gerekli
            $group_id = isset($row['group_id']) ? trim((string) $row['group_id']) : '';
            if ($group_id === '') {
                continue;
            }

            // Dil kodu normalize → satıra göm
            $lang_code = $this->normalize_language_code($row['language'] ?? '');
            $row['_wrpai_lang_code'] = $lang_code;

            // Kategori oluştur (id => name)
            $this->ensure_category($lang_code, $categories);

            // Gruba ekle
            if (!isset($groups[$group_id])) {
                $groups[$group_id] = [];
            }

            $groups[$group_id][] = $row;
        }

        // Sonuç sayacı
        $result = [
            'groups' => count($groups),
            'audio'  => 0,
            'pdf'    => 0,
        ];

        if (empty($groups)) {
            update_option('wrap_categories', $categories);
            return $result;
        }

        // Mevcut player deposu
        $players = get_option('wrap_players', []);
        if (!is_array($players)) {
            $players = [];
        }

        // Her group_id → bir player
        foreach ($groups as $group_id => $items) {
            $player = $this->build_player($group_id, $items, $categories);

            // 🔹 Artık array key = slug
            $players[$player['slug']] = $player;
            $result['audio']++;
        }

        update_option('wrap_players', $players);
        update_option('wrap_categories', $categories);

        return $result;
    }

    /**
     * Player oluşturur
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
     * CSV satırından track çıkarır
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
     * Slug üretir (string)
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
     * Kategori ekle (id => name)
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
