<?php
/**
 * Plugin Name: Wisdom Rain Player Auto Importer
 * Description: Auto-creates Audio Player and PDF Reader posts from CSV. Imports languages, URLs and generates shortcode outputs.
 * Version: 1.0.0
 * Author: Wisdom Rain
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WRPAI_PATH', plugin_dir_path(__FILE__) );
define( 'WRPAI_URL', plugin_dir_url(__FILE__) );

require_once WRPAI_PATH . 'includes/class-wrpai-loader.php';

function wrpai_run_plugin() {
    $loader = new WRPAI_Loader();
    $loader->init();
}
add_action('plugins_loaded', 'wrpai_run_plugin');
