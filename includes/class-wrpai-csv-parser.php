<?php

if (!defined('ABSPATH')) exit;

class WRPAI_CSV_Parser {

    public function parse($file_path) {

        $rows = [];
        $handle = fopen($file_path, 'r');

        if (!$handle) return $rows;

        $header = fgetcsv($handle, 0, ',');

        while (($data = fgetcsv($handle, 0, ',')) !== FALSE) {
            $row = [];

            foreach ($header as $i => $col) {
                $key = trim($col);
                $val = isset($data[$i]) ? trim($data[$i]) : '';
                $row[$key] = $val;
            }

            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }
}
