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

        // Only allow CSV
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if ( strtolower($file_ext) !== 'csv' ) {
            wp_die('Only CSV files are allowed.');
        }

        // Prepare upload directory
        $upload_dir = wp_upload_dir();
        $wrpai_dir  = $upload_dir['basedir'] . '/wrpai';

        if ( ! file_exists($wrpai_dir) ) {
            wp_mkdir_p($wrpai_dir);
        }

        $dest_path = $wrpai_dir . '/' . basename($file['name']);

        if ( move_uploaded_file($file['tmp_name'], $dest_path) ) {

            // After upload, run import
            add_action('admin_notices', function() use ($dest_path) {

                require_once WRPAI_PATH . 'includes/class-wrpai-csv-parser.php';
                require_once WRPAI_PATH . 'includes/class-wrpai-import-runner.php';

                $parser = new WRPAI_CSV_Parser();
                $rows   = $parser->parse($dest_path);

                $runner = new WRPAI_Import_Runner();
                $result = $runner->run($rows);

                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>Import tamamlandı:</strong><br>';
                echo 'Gruplar: ' . $result['groups'] . '<br>';
                echo 'Audio Player oluşturulan: ' . $result['audio'] . '<br>';
                echo 'PDF Reader oluşturulan: ' . $result['pdf'];
                echo '</p></div>';
            });

            // Redirect for success message
            wp_redirect(admin_url('admin.php?page=wrpai-import&wrpai_status=uploaded'));
            exit;

        } else {
            wp_die('CSV upload failed.');
        }
    }
}
