<?php

if ( ! defined('ABSPATH') ) exit;

class WRPAI_Admin_Menu {

    public function __construct() {
        add_action('admin_menu', array($this, 'register_menu'));
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
}
