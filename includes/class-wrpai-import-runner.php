<?php

if ( ! defined('ABSPATH') ) exit;

class WRPAI_Import_Runner {

    protected $audio_generator;
    protected $pdf_generator;

    public function __construct() {
        $this->audio_generator = new WRPAI_Audio_Generator();
        $this->pdf_generator   = new WRPAI_PDF_Generator();
    }

    public function run($rows) {

        $grouped = array();

        // Group by group_id
        foreach ($rows as $row) {
            $gid = $row['group_id'];
            if (!isset($grouped[$gid])) {
                $grouped[$gid] = array();
            }
            $grouped[$gid][] = $row;
        }

        $result = array(
            'groups' => count($grouped),
            'audio'  => 0,
            'pdf'    => 0
        );

        // Process each group
        foreach ($grouped as $gid => $items) {

            // Separate formats
            $audio_items = array_filter($items, function($r){
                return strtolower($r['format']) == 'audio book';
            });

            $pdf_items = array_filter($items, function($r){
                return strtolower($r['format']) == 'pdf' || strtolower($r['format']) == 'epub';
            });

            // Run generators
            if (!empty($audio_items)) {
                $this->audio_generator->create_audio_post($audio_items);
                $result['audio']++;
            }

            if (!empty($pdf_items)) {
                $this->pdf_generator->create_pdf_post($pdf_items);
                $result['pdf']++;
            }
        }

        return $result;
    }
}
