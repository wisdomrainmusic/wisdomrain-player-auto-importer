<?php

if ( ! defined('ABSPATH') ) exit;

class WRPAI_Import_Runner {

    public function run( $rows ) {

        $groups = 0;
        $audios = 0;

        foreach ( $rows as $row ) {

            $title = sanitize_text_field($row['title']);
            $mp3   = esc_url_raw($row['mp3']);
            $buy   = esc_url_raw($row['buy']);
            $lang  = sanitize_text_field($row['lang']);

            $audio_gen = new WRPAI_Audio_Generator();

            $player_id = $audio_gen->create_audio_player($title, [
                [
                    'title' => $title,
                    'mp3'   => $mp3,
                    'buy'   => $buy,
                    'lang'  => $lang
                ]
            ]);

            if ( $player_id ) {
                $audios++;
            }

            $groups++;
        }

        return [
            'groups' => $groups,
            'audio'  => $audios,
            'pdf'    => 0
        ];
    }
}
