<?php

if (!defined('ABSPATH')) exit;

class WRPAI_Import_Runner {

    /**
     * Çalıştırıcı: CSV satırlarını işler ve Audio Player verilerini wrap_players option'ına yazar.
     *
     * @param array $rows
     * @return array
     */
    public function run($rows) {
        $groups = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $format = isset($row['format']) ? trim($row['format']) : '';
            if ($format !== 'Audio Book') {
                continue;
            }

            $group_id = isset($row['group_id']) ? trim($row['group_id']) : '';
            if ($group_id === '') {
                continue;
            }

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

        $name = isset($first['product_title']) ? trim($first['product_title']) : '';
        if ($name === '') {
            $name = $group_id;
        }

        $slug = $this->generate_player_slug($name, $group_id);

        $player = [
            'id'          => $slug,
            'name'        => $name,
            'category'    => 'Audio Book',
            'description' => '',
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
        $title = isset($row['product_title']) ? trim($row['product_title']) : '';
        $language = isset($row['language']) ? trim($row['language']) : '';
        $file_urls = isset($row['file_urls']) ? trim($row['file_urls']) : '';
        $buy_link = isset($row['buy_link']) ? trim($row['buy_link']) : '';

        $lang_code = strtoupper($language);
        if ($lang_code === '') {
            $lang_code = 'UNKNOWN';
        }

        $cat_id = strtolower($lang_code);

        $lang_names = [
            'DE' => 'German',
            'EN' => 'English',
            'ES' => 'Spanish',
            'FR' => 'French',
            'IT' => 'Italian',
            'PT' => 'Portuguese',
            'RU' => 'Russian',
            'TR' => 'Turkish',
            'UNKNOWN' => 'Unknown',
        ];

        $cat_name = isset($lang_names[$lang_code]) ? $lang_names[$lang_code] : $lang_code;

        $cats = get_option('wrap_categories', []);
        if (!is_array($cats)) {
            $cats = [];
        }

        if (!isset($cats[$cat_id])) {
            $cats[$cat_id] = [
                'id'   => $cat_id,
                'name' => $cat_name,
            ];
            update_option('wrap_categories', $cats);
        }

        return [
            'title'    => $title,
            'author'   => '',
            'category' => $cat_id,
            'img'      => '',
            'mp3'      => $file_urls,
            'ogg'      => '',
            'buy'      => $buy_link,
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
}
