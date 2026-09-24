<?php
/**
 * Plugin Name: BB Widget
 * Description: Funkcje Biegu Belfrów.
 * Version: 0.1.0
 * Text Domain: bbwidget
 */

defined('ABSPATH') || exit;

add_shortcode('bbwidget', function () {
    return '<p>Hello world — BB Widget działa!</p>';
});

add_action('admin_menu', function () {
    add_menu_page('BB Widget', 'BB Widget', 'manage_options', 'bbwidget', function () {
        if (!current_user_can('manage_options')) {
            return;
        }
        echo '<div class="wrap"><h1>BB Widget</h1><p>Hello world — BB Widget działa!</p></div>';
    }, 'dashicons-universal-access-alt');
});
