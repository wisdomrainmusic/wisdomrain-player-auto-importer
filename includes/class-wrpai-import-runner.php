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

        $slug = $this->generate_slug($group_id, $name);

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

        return [
            'title'    => $title,
            'author'   => '',
            'category' => $language,
            'img'      => '',
            'mp3'      => $file_urls,
            'ogg'      => '',
            'buy'      => $buy_link,
            'lyrics'   => '',
        ];
    }

    /**
     * group_id temel alınarak benzersiz slug üretir.
     *
     * @param string $group_id
     * @param string $fallback
     * @return string
     */
    private function generate_slug($group_id, $fallback) {
        $base = sanitize_title($group_id);

        if ($base === '' && $fallback !== '') {
            $base = sanitize_title($fallback);
        }

        if ($base === '') {
            $base = 'audio-book-' . wp_unique_id();
        }

        return $base;
    }
}
