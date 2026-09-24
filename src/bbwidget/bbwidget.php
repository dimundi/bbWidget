<?php
/**
 * Plugin Name: BB Widget
 * Description: Funkcje Biegu Belfrów.
 * Version: 0.4.0
 * Text Domain: bbwidget
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/includes/editions.php';
require_once __DIR__ . '/includes/packages.php';
require_once __DIR__ . '/includes/participants.php';
register_activation_hook(__FILE__, array('BBW_Participants', 'install'));
register_activation_hook(__FILE__, array('BBW_Packages', 'install'));
register_activation_hook(__FILE__, array('BBW_Editions', 'install'));
add_action('admin_init', function () {
    if (current_user_can('manage_options') && get_option('bbw_participants_schema_version') !== '2') { BBW_Participants::install(); }
    if (current_user_can('manage_options') && get_option('bbw_packages_schema_version') !== '1') { BBW_Packages::install(); }
    if (current_user_can('manage_options') && get_option('bbw_schema_version') !== '1') {
        BBW_Editions::install();
    }
});
add_action('admin_menu', function () {
    add_menu_page('Edycje BB', 'BB Widget', 'manage_options', 'bbwidget', array('BBW_Editions', 'render'), 'dashicons-universal-access-alt');
    add_submenu_page('bbwidget', 'Edycje BB', 'Edycje', 'manage_options', 'bbwidget-editions', array('BBW_Editions', 'render'));
    add_submenu_page('bbwidget', 'Pakiety startowe', 'Pakiety startowe', 'manage_options', 'bbwidget-packages', array('BBW_Packages', 'render'));
    add_submenu_page('bbwidget', 'Uczestnicy', 'Uczestnicy', 'manage_options', 'bbwidget-participants', array('BBW_Participants', 'render'));
    remove_submenu_page('bbwidget', 'bbwidget');
});
add_action('admin_post_bbw_save_participant', array('BBW_Participants', 'handle_save'));
add_action('admin_post_bbw_save_package', array('BBW_Packages', 'handle_save'));
add_action('admin_post_bbw_save_edition', array('BBW_Editions', 'handle_save'));
add_action('admin_enqueue_scripts', function ($hook) {
    if (!in_array($_GET['page'] ?? '', array('bbwidget', 'bbwidget-editions', 'bbwidget-packages', 'bbwidget-participants'), true)) { return; }
    if (($_GET['page'] ?? '') === 'bbwidget-participants') { wp_enqueue_script('bbw-participants', plugins_url('assets/participants.js', __FILE__), array(), filemtime(__DIR__ . '/assets/participants.js'), true); }
    wp_enqueue_media();
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_style('bbw-editions', plugins_url('assets/editions.css', __FILE__), array(), filemtime(__DIR__ . '/assets/editions.css'));
    wp_enqueue_script('bbw-editions', plugins_url('assets/editions.js', __FILE__), array('jquery', 'wp-color-picker'), filemtime(__DIR__ . '/assets/editions.js'), true);
});
add_shortcode('bbwidget', function () { return '<p>Hello world — BB Widget działa!</p>'; });