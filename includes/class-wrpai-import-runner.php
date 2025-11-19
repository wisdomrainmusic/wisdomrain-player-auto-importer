<?php

if (!defined('ABSPATH')) exit;

class WRPAI_Import_Runner {

    public function run($rows) {

        $groups = [];
        foreach ($rows as $row) {

            // Filtre: Sadece Audio Book satırları işlenecek
            if (trim($row['format']) !== 'Audio Book') {
                continue;
            }

            $gid = trim($row['group_id']);
            if (!isset($groups[$gid])) {
                $groups[$gid] = [];
            }

            $groups[$gid][] = $row;
        }

        $result = [
            'groups' => count($groups),
            'audio'  => 0,
            'pdf'    => 0
        ];

        foreach ($groups as $gid => $items) {

            // --- PLAYER OLUŞTUR ---
            $first = $items[0];

            $player_name = trim($first['product_title']);
            $player_slug = trim($first['slug']);

            if (!function_exists('wrap_audio_core')) {
                continue; // WRAP Audio Engine aktif değil
            }

            // Player oluştur
            $player_id = wrap_audio_core()->players->create_player($player_name, $player_slug);

            if (!$player_id) {
                continue;
            }

            $result['audio']++;

            // --- TRACKLERİ OLUŞTUR ---
            foreach ($items as $row) {

                $lang = strtoupper(trim($row['language']));
                $mp3  = trim($row['file_urls']);
                $title = trim($row['product_title']);

                // Dil kategorisi oluştur (yoksa)
                $cat_id = $this->ensure_language_category($lang);

                // Track oluştur
                $track_id = wrap_audio_core()->tracks->create_track([
                    'title'     => $title,
                    'author'    => '',
                    'category'  => $cat_id,
                    'mp3_url'   => $mp3,
                    'ogg_url'   => '',
                    'image_url' => '',
                    'lang'      => $lang,
                ]);

                if ($track_id) {
                    // Track player içine ekle
                    wrap_audio_core()->players->add_track_to_player($player_id, $track_id);
                }
            }
        }

        return $result;
    }


    private function ensure_language_category($lang)
    {
        // Var mı?
        $existing = wrap_audio_core()->categories->get_category_by_name($lang);
        if ($existing) {
            return $existing->id;
        }

        // Yoksa oluştur
        return wrap_audio_core()->categories->create_category($lang);
    }
}
