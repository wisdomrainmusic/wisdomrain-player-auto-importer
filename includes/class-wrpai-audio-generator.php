<?php

if ( ! defined('ABSPATH') ) exit;

class WRPAI_Audio_Generator {

    public function create_audio_player( $title, $tracks ) {

        // 1) PLAYER OLUŞTUR
        $player_id = wp_insert_post([
            'post_type'   => 'wrap_audio_player',
            'post_title'  => sanitize_text_field($title),
            'post_status' => 'publish'
        ]);

        if ( is_wp_error($player_id) ) {
            return 0;
        }

        // 2) OLUŞAN TRACK ID’LERİNİ TUTALIM
        $track_ids = [];

        foreach ( $tracks as $t ) {

            $track_id = wp_insert_post([
                'post_type'   => 'wrap_audio_track',
                'post_title'  => sanitize_text_field($t['title']),
                'post_status' => 'publish'
            ]);

            if ( ! is_wp_error($track_id) ) {

                // Track Meta Güncelle
                update_post_meta($track_id, '_wra_track_audio_mp3', $t['mp3']);
                update_post_meta($track_id, '_wra_track_lang', $t['lang']);
                update_post_meta($track_id, '_wra_track_buy', $t['buy']); // buy link

                $track_ids[] = $track_id;
            }
        }

        // 3) PLAYER’A TRACK ID’LERİNİ EKLE
        update_post_meta($player_id, '_wra_player_tracks', implode(',', $track_ids));

        return $player_id;
    }
}
