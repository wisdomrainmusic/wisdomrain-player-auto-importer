<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class WRPAI_Loader {

    public function init() {

        // Include classes
        require_once WRPAI_PATH . 'admin/class-wrpai-admin-menu.php';
        require_once WRPAI_PATH . 'includes/class-wrpai-csv-parser.php';
        require_once WRPAI_PATH . 'includes/class-wrpai-import-runner.php';
        require_once WRPAI_PATH . 'includes/class-wrpai-audio-generator.php';
        require_once WRPAI_PATH . 'includes/class-wrpai-pdf-generator.php';
        require_once WRPAI_PATH . 'includes/class-wrpai-logger.php';

        // Init admin menu
        new WRPAI_Admin_Menu();
    }
}
