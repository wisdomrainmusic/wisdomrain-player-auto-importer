<?php

if ( ! defined('ABSPATH') ) exit;

class WRPAI_Admin_Menu {

    public function __construct() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'handle_csv_upload'));
    }

    public function register_menu() {
        add_menu_page(
            'WR Player Auto Import',
            'WR Player Import',
            'manage_options',
            'wrpai-import',
            array($this, 'import_page'),
            'dashicons-database-import',
            26
        );
    }

    public function import_page() {
        include WRPAI_PATH . 'admin/views/import-page.php';
    }

    public function handle_csv_upload() {

        if ( ! isset($_POST['wrpai_nonce']) ) {
            return;
        }

        if ( ! wp_verify_nonce($_POST['wrpai_nonce'], 'wrpai_csv_upload') ) {
            wp_die('Invalid nonce');
        }

        if ( ! current_user_can('manage_options') ) {
            wp_die('Permission denied');
        }

        if ( empty($_FILES['wrpai_csv']['name']) ) {
            return;
        }

        $file = $_FILES['wrpai_csv'];

        // Allow only CSV
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ( $ext !== 'csv' ) {
            wp_die('Only CSV files allowed.');
        }

        // Upload directory
        $upload_dir = wp_upload_dir();
        $wrpai_dir = $upload_dir['basedir'] . '/wrpai';

        if ( ! file_exists($wrpai_dir) ) {
            wp_mkdir_p($wrpai_dir);
        }

        $dest_path = $wrpai_dir . '/' . basename($file['name']);

        if ( move_uploaded_file($file['tmp_name'], $dest_path) ) {

            // ------------ IMPORT BEFORE REDIRECT ------------
            require_once WRPAI_PATH . 'includes/class-wrpai-csv-parser.php';
            require_once WRPAI_PATH . 'includes/class-wrpai-import-runner.php';

            $parser = new WRPAI_CSV_Parser();
            $rows = $parser->parse($dest_path);

            $runner = new WRPAI_Import_Runner();
            $result = $runner->run($rows);
            // -------------------------------------------------

            // Encode results
            $query = http_build_query(array(
                'page'    => 'wrpai-import',
                'status'  => 'uploaded',
                'groups'  => $result['groups'],
                'audio'   => $result['audio'],
                'pdf'     => $result['pdf'],
            ));

            wp_redirect(admin_url('admin.php?' . $query));
            exit;

        } else {
            wp_die('CSV upload failed.');
        }
    }
}
