<?php
defined('ABSPATH') || exit;

class BBW_Shortcodes {
    private static $items = array();

    // Register each BB Widget shortcode here so its documentation stays with its implementation.
    public static function register($tag, $callback, $title, $description, $example = '') {
        add_shortcode($tag, $callback);
        self::$items[$tag] = array(
            'title' => $title,
            'description' => $description,
            'example' => $example ?: '[' . $tag . ']',
        );
    }

    public static function render() {
        if (!current_user_can('manage_options')) { return; }
        echo '<div class="wrap bbw-admin"><h1>Shortcodes</h1><p>Wstaw wybrany kod w bloku „Krótki kod” na stronie. Poniżej znajdziesz działające przykłady shortcode’ów BB Widget.</p>';
        foreach (self::$items as $tag => $item) {
            $id = 'bbw-shortcode-' . sanitize_html_class($tag);
            echo '<section class="bbw-card"><h2>' . esc_html($item['title']) . '</h2><p>' . esc_html($item['description']) . '</p>';
            echo '<p><label for="' . esc_attr($id) . '"><strong>Kod do wstawienia</strong></label></p><div class="bbw-shortcode-code"><input class="regular-text code" id="' . esc_attr($id) . '" type="text" readonly value="' . esc_attr($item['example']) . '"> <button class="button" type="button" data-bbw-copy="' . esc_attr($id) . '">Kopiuj kod</button> <span role="status" aria-live="polite"></span></div>';
            echo '<h3>Działający przykład</h3><div class="bbw-shortcode-example">';
            echo do_shortcode($item['example']);
            echo '</div></section>';
        }
        if (!self::$items) { echo '<p>Brak dostępnych shortcode’ów.</p>'; }
        echo '</div>';
    }
}

BBW_Shortcodes::register('bbwidget', function () { return '<p>Hello world — BB Widget działa!</p>'; },
    'Test działania BB Widget', 'Prosty komunikat sprawdzający, czy wtyczka obsługuje shortcode’y.');
