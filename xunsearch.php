<?php
/**
 * @package XunSearch
 */
/*
Plugin Name: XunSearch
Plugin URI: https://www.github.com/davin.bao/wp-xunsearch
Description: XunSearch for wordpress
Version: 0.1.00
Author: Davin.bao
Author URI: http://www.tech06.com/
License: GPLv2 or later
Text Domain: xunsearch
*/
if ( !function_exists( 'add_action' ) ) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}
define( 'XUN_SEARCH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once( XUN_SEARCH_PLUGIN_DIR . 'class.xunsearch.php' );

$xunSearch = new XunSearch();