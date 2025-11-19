<?php

if ( ! defined('ABSPATH') ) exit;

class WRPAI_CSV_Parser {

    public function parse($file_path) {

        $rows = array();

        if ( ! file_exists($file_path) ) {
            return $rows;
        }

        if ( ($handle = fopen($file_path, 'r')) !== false ) {

            $header = null;

            while ( ($data = fgetcsv($handle, 10000, ",")) !== false ) {

                if ( ! $header ) {
                    // First row is header
                    $header = $data;
                    continue;
                }

                // Row as associative array
                $row = array();
                foreach ($header as $i => $col_name) {
                    $row[$col_name] = isset($data[$i]) ? $data[$i] : '';
                }

                $rows[] = $row;
            }

            fclose($handle);
        }

        return $rows;
    }
}
