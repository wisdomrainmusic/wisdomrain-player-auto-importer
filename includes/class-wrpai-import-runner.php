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
     * Çalıştırıcı: CSV satırlarını işler ve Audio Player verilerini wrap_players option'ına yazar.
     *
     * @param array $rows
     * @return array
     */
    public function run($rows) {
        if (!is_array($rows)) {
            $rows = [];
        }

        $groups = [];
        $categories = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $format = isset($row['format']) ? trim((string) $row['format']) : '';
            if ($format === '' || strcasecmp($format, 'Audio Book') !== 0) {
                continue;
            }

            $group_id = isset($row['group_id']) ? trim((string) $row['group_id']) : '';
            if ($group_id === '') {
                continue;
            }

            $lang_code = $this->normalize_language_code(isset($row['language']) ? $row['language'] : '');
            $this->ensure_category($lang_code, $categories);

            $row['_wrpai_lang_code'] = $lang_code;

            if (!isset($groups[$group_id])) {
                $groups[$group_id] = [];
            }

            $groups[$group_id][] = $row;
        }

        $result = [
            'groups' => count($groups),
            'audio'  => 0,
            'pdf'    => 0,
        ];

        if (empty($groups)) {
            update_option('wrap_categories', $categories);
            return $result;
        }

        $players = get_option('wrap_players', []);
        if (!is_array($players)) {
            $players = [];
        }

        foreach ($groups as $group_id => $items) {
            $player = $this->build_player($group_id, $items);
            $players[$player['id']] = $player;
            $result['audio']++;
        }

        update_option('wrap_players', $players);
        update_option('wrap_categories', $categories);

        return $result;
    }

    /**
     * Belirli bir grup için player yapısını oluşturur.
     *
     * @param string $group_id
     * @param array  $items
     * @return array
     */
    private function build_player($group_id, $items) {
        $first = reset($items);

        $title = isset($first['product_title']) ? sanitize_text_field($first['product_title']) : '';
        if ($title === '') {
            $title = sanitize_text_field($group_id);
        }

        $slug = $this->generate_player_slug($title, $group_id);

        $player = [
            'id'          => $slug,
            'name'        => $title,
            'description' => '',
            'category'    => '',
            'language'    => '',
            'settings'    => [ 'autoplay' => false ],
            'track_ids'   => [],
            'tracks'      => [],
        ];

        foreach ($items as $row) {
            $player['tracks'][] = $this->build_track($row);
        }

        return $player;
    }

    /**
     * CSV satırından track verisini hazırlar.
     *
     * @param array $row
     * @return array
     */
    private function build_track($row) {
        $lang_code = isset($row['_wrpai_lang_code']) ? $row['_wrpai_lang_code'] : $this->normalize_language_code(isset($row['language']) ? $row['language'] : '');
        $cat_id = strtolower($lang_code);

        $title = isset($row['product_title']) ? sanitize_text_field($row['product_title']) : '';
        $mp3 = isset($row['file_urls']) ? esc_url_raw($row['file_urls']) : '';
        $buy = isset($row['buy_link']) ? esc_url_raw($row['buy_link']) : '';

        return [
            'title'    => $title,
            'author'   => '',
            'category' => $cat_id,
            'img'      => '',
            'mp3'      => $mp3,
            'ogg'      => '',
            'buy'      => $buy,
            'lyrics'   => '',
        ];
    }

    /**
     * Player shortcode'unda kullanılacak güvenli slug üretir.
     *
     * @param string $title
     * @param string $group_id
     * @return string
     */
    private function generate_player_slug($title, $group_id) {
        $title = is_string($title) ? $title : '';
        $group_id = is_scalar($group_id) ? (string) $group_id : '';

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
     * CSV dil kodunu normalize eder.
     *
     * @param string $language
     * @return string
     */
    private function normalize_language_code($language) {
        $lang_code = strtoupper(trim((string) $language));
        if ($lang_code === '') {
            $lang_code = 'UN';
        }

        return $lang_code;
    }

    /**
     * wrap_categories dizisine dil kategorisini ekler.
     *
     * @param string $lang_code
     * @param array  $categories
     * @return void
     */
    private function ensure_category($lang_code, array &$categories) {
        $cat_id = strtolower($lang_code);
        if ($cat_id === '') {
            $cat_id = 'un';
        }

        $cat_name = isset($this->language_names[$lang_code]) ? $this->language_names[$lang_code] : $lang_code;

        if (!isset($categories[$cat_id])) {
            $categories[$cat_id] = [
                'id'   => $cat_id,
                'name' => $cat_name,
            ];
        }
    }
}
