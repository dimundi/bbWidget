<?php
defined('ABSPATH') || exit;

class BBW_Editions {
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'bb_editions';
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            bbeditionId bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            editionNumber int(10) unsigned NOT NULL,
            isCurrent tinyint(1) NOT NULL DEFAULT 0,
            startDate date DEFAULT NULL,
            endDate date DEFAULT NULL,
            registrationStartDate date DEFAULT NULL,
            registrationEndDate date DEFAULT NULL,
            primaryColor char(7) DEFAULT NULL,
            accentColor char(7) DEFAULT NULL,
            backgroundColor char(7) DEFAULT NULL,
            logo bigint(20) unsigned DEFAULT NULL,
            PRIMARY KEY  (bbeditionId),
            UNIQUE KEY editionNumber (editionNumber)
        ) $charset;");
        if (!$wpdb->last_error) { update_option('bbw_schema_version', '1', false); }
    }

    public static function validate($input) {
        $data = array();
        foreach (array('editionNumber', 'isCurrent', 'startDate', 'endDate', 'registrationStartDate', 'registrationEndDate', 'primaryColor', 'accentColor', 'backgroundColor', 'logo') as $key) {
            if (isset($input[$key]) && !is_scalar($input[$key])) {
                return new WP_Error('invalid', 'Nieprawidłowa wartość pola.');
            }
        }
        $number = (string) ($input['editionNumber'] ?? '');
        if (!ctype_digit($number) || (int) $number < 1 || (float) $number > 4294967295) {
            return new WP_Error('number', 'Podaj dodatni numer edycji.');
        }
        $data['editionNumber'] = (int) $number;
        $data['isCurrent'] = !empty($input['isCurrent']) ? 1 : 0;
        foreach (array('startDate', 'endDate', 'registrationStartDate', 'registrationEndDate') as $key) {
            $value = (string) ($input[$key] ?? '');
            if ($value !== '') {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                if (!$date || $date->format('Y-m-d') !== $value || (int) substr($value, 0, 4) < 1000) {
                    return new WP_Error('date', 'Podaj poprawne daty.');
                }
            }
            $data[$key] = $value === '' ? null : $value;
        }
        foreach (array(array('startDate', 'endDate'), array('registrationStartDate', 'registrationEndDate')) as $pair) {
            if ($data[$pair[0]] && $data[$pair[1]] && $data[$pair[0]] > $data[$pair[1]]) {
                return new WP_Error('date_order', 'Data zakończenia nie może poprzedzać daty rozpoczęcia.');
            }
        }
        foreach (array('primaryColor', 'accentColor', 'backgroundColor') as $key) {
            $value = (string) ($input[$key] ?? '');
            if ($value !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/D', $value)) {
                return new WP_Error('color', 'Kolor musi mieć format #RRGGBB.');
            }
            $data[$key] = $value === '' ? null : strtolower($value);
        }
        $logo = (string) ($input['logo'] ?? '');
        if ($logo !== '' && $logo !== '0' && (!ctype_digit($logo) || !wp_attachment_is_image((int) $logo))) {
            return new WP_Error('logo', 'Wybierz obrazek z biblioteki mediów.');
        }
        $data['logo'] = $logo === '' || $logo === '0' ? null : (int) $logo;
        return $data;
    }

    public static function save($input, $id = 0) {
        global $wpdb;
        $data = self::validate($input);
        if (is_wp_error($data)) { return $data; }
        $table = self::table();
        if ($id && !$wpdb->get_var($wpdb->prepare("SELECT bbeditionId FROM $table WHERE bbeditionId = %d", $id))) {
            return new WP_Error('missing', 'Nie znaleziono edycji.');
        }
        if ($wpdb->get_var($wpdb->prepare("SELECT bbeditionId FROM $table WHERE editionNumber = %d AND bbeditionId <> %d", $data['editionNumber'], $id))) {
            return new WP_Error('duplicate', 'Edycja o tym numerze już istnieje.');
        }
        $result = $id ? $wpdb->update($table, $data, array('bbeditionId' => $id)) : $wpdb->insert($table, $data);
        if ($result === false) { return new WP_Error('database', 'Nie udało się zapisać edycji.'); }
        return $id ?: (int) $wpdb->insert_id;
    }

    public static function handle_save() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień.', '', array('response' => 403)); }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { wp_die('Niedozwolona metoda.', '', array('response' => 405)); }
        check_admin_referer('bbw_save_edition');
        $input = wp_unslash($_POST);
        if (isset($input['bbeditionId']) && (!is_scalar($input['bbeditionId']) || !ctype_digit((string) $input['bbeditionId']))) {
            wp_die('Nieprawidłowy identyfikator.', '', array('response' => 400, 'back_link' => true));
        }
        $result = self::save($input, absint($input['bbeditionId'] ?? 0));
        if (is_wp_error($result)) {
            $token = strtolower(wp_generate_password(20, false));
            $values = array('isCurrent' => 0);
            foreach ($input as $key => $value) {
                if (is_scalar($value) && $key !== '_wpnonce') { $values[$key] = sanitize_text_field((string) $value); }
            }
            set_transient('bbw_form_' . get_current_user_id() . '_' . $token, array('values' => $values, 'message' => $result->get_error_message()), 300);
            $args = array('page' => 'bbwidget-editions', 'form_error' => $token);
            if (!empty($input['bbeditionId'])) { $args['edit'] = absint($input['bbeditionId']); }
            else { $args['view'] = 'new'; }
            wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
            exit;
        }
        wp_safe_redirect(add_query_arg(array('page' => 'bbwidget-editions', 'saved' => 1), admin_url('admin.php')));
        exit;
    }

    private static function period($start, $end) {
        $format = function ($date) { return $date ? date_i18n('d.m.Y', strtotime($date)) : '—'; };
        return (!$start && !$end) ? 'Nie uzupełniono' : $format($start) . ' – ' . $format($end);
    }

    private static function field($key, $label, $type, $row, $hint = '') {
        $color = strpos($key, 'Color') !== false;
        echo '<div class="bbw-field"><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label>';
        echo '<input id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" type="' . esc_attr($type) . '" value="' . esc_attr($row[$key] ?? '') . '"' . ($key === 'editionNumber' ? ' required min="1" max="4294967295" step="1"' : '') . ($color ? ' class="bbw-color" placeholder="#RRGGBB" maxlength="7"' : '') . ($hint ? ' aria-describedby="' . esc_attr($key) . '-hint"' : '') . '>';
        if ($hint) { echo '<p class="description" id="' . esc_attr($key) . '-hint">' . esc_html($hint) . '</p>'; }
        echo '</div>';
    }

    public static function render() {
        if (!current_user_can('manage_options')) { return; }
        global $wpdb;
        $table = self::table();
        $list_url = admin_url('admin.php?page=bbwidget-editions');
        $id = isset($_GET['edit']) && is_scalar($_GET['edit']) ? absint($_GET['edit']) : 0;
        $form_view = isset($_GET['edit']) || (isset($_GET['view']) && $_GET['view'] === 'new');
        echo '<div class="wrap bbw-admin">';
        if (!$form_view) {
            echo '<h1 class="wp-heading-inline">Edycje Biegu Belfrów</h1> <a class="page-title-action" href="' . esc_url(add_query_arg('view', 'new', $list_url)) . '">Dodaj edycję</a><hr class="wp-header-end">';
            if (isset($_GET['saved'])) { echo '<div class="notice notice-success is-dismissible"><p>Edycja została zapisana.</p></div>'; }
            echo '<p>Wybierz edycję, aby zmienić jej daty, kolory lub logo.</p>';
            $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY editionNumber DESC", ARRAY_A);
            echo '<div class="bbw-table-scroll"><table class="widefat striped bbw-editions"><thead><tr><th scope="col">Edycja</th><th scope="col">Termin wydarzenia</th><th scope="col">Termin zapisów</th><th scope="col">Bieżąca edycja</th><th scope="col">Działania</th></tr></thead><tbody>';
            foreach ($rows as $edition) {
                $edit_url = add_query_arg('edit', $edition['bbeditionId'], $list_url);
                echo '<tr><td><a class="row-title" href="' . esc_url($edit_url) . '">' . esc_html($edition['editionNumber']) . '. edycja</a></td><td>' . esc_html(self::period($edition['startDate'], $edition['endDate'])) . '</td><td>' . esc_html(self::period($edition['registrationStartDate'], $edition['registrationEndDate'])) . '</td><td>' . ($edition['isCurrent'] ? '<span class="bbw-current">Tak</span>' : 'Nie') . '</td><td><a class="button" aria-label="Edytuj edycję ' . esc_attr($edition['editionNumber']) . '" href="' . esc_url($edit_url) . '">Edytuj</a></td></tr>';
            }
            if (!$rows) { echo '<tr><td colspan="5">Nie ma jeszcze edycji. Użyj przycisku „Dodaj edycję”.</td></tr>'; }
            echo '</tbody></table></div></div>';
            return;
        }
        $row = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE bbeditionId = %d", $id), ARRAY_A) : array();
        echo '<a class="bbw-back" href="' . esc_url($list_url) . '">← Wróć do listy edycji</a>';
        if (isset($_GET['edit']) && (!$id || !$row)) {
            echo '<h1>Nie znaleziono edycji</h1></div>';
            return;
        }
        $title = $id ? 'Edytuj ' . $row['editionNumber'] . '. edycję' : 'Dodaj edycję';
        $error = null;
        if (isset($_GET['form_error']) && is_string($_GET['form_error'])) {
            $key = 'bbw_form_' . get_current_user_id() . '_' . sanitize_key($_GET['form_error']);
            // Tokens are lowercase so the lookup is stable after sanitizing.
            $error = get_transient($key);
            if ($error) { delete_transient($key); $row = array_merge($row, $error['values']); }
        }
        echo '<h1>' . esc_html($title) . '</h1><p>Wymagany jest tylko numer edycji. Pozostałe dane możesz uzupełnić później.</p>';
        if ($error) { echo '<div class="notice notice-error" role="alert"><p>' . esc_html($error['message']) . '</p></div>'; }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="bbw-edition-form">';
        wp_nonce_field('bbw_save_edition');
        echo '<input type="hidden" name="action" value="bbw_save_edition"><input type="hidden" name="bbeditionId" value="' . esc_attr($id) . '">';
        echo '<section class="bbw-card" aria-labelledby="bbw-basic"><h2 id="bbw-basic">Podstawowe informacje</h2>';
        self::field('editionNumber', 'Numer edycji (wymagany)', 'number', $row, 'Np. 7 dla siódmej edycji.');
        echo '<p><label><input type="checkbox" name="isCurrent" value="1" ' . checked(!empty($row['isCurrent']), true, false) . '> Oznacz jako bieżącą edycję</label></p></section>';
        echo '<section class="bbw-card" aria-labelledby="bbw-dates"><h2 id="bbw-dates">Terminy</h2><div class="bbw-fields">';
        self::field('startDate', 'Rozpoczęcie wydarzenia', 'date', $row);
        self::field('endDate', 'Zakończenie wydarzenia', 'date', $row);
        self::field('registrationStartDate', 'Rozpoczęcie zapisów', 'date', $row);
        self::field('registrationEndDate', 'Zakończenie zapisów', 'date', $row);
        echo '</div></section><section class="bbw-card" aria-labelledby="bbw-look"><h2 id="bbw-look">Kolory i logo</h2><div class="bbw-fields">';
        self::field('primaryColor', 'Kolor główny', 'text', $row, 'Przyciski, linki i wyróżnienia.');
        self::field('accentColor', 'Kolor pomocniczy', 'text', $row, 'Dodatkowe akcenty.');
        self::field('backgroundColor', 'Kolor tła', 'text', $row, 'Tło strony.');
        echo '</div><h3>Logo edycji</h3><input type="hidden" id="bbw-logo" name="logo" value="' . esc_attr($row['logo'] ?? '') . '"><div id="bbw-logo-preview">';
        if (!empty($row['logo'])) { echo wp_get_attachment_image((int) $row['logo'], 'thumbnail'); }
        echo '</div><button type="button" class="button" id="bbw-logo-select">Wybierz / wgraj logo</button> <button type="button" class="button" id="bbw-logo-remove">Usuń logo</button></section>';
        echo '<div class="bbw-actions">';
        submit_button($id ? 'Zapisz zmiany' : 'Dodaj edycję', 'primary', 'submit', false);
        echo ' <a class="button" href="' . esc_url($list_url) . '">Anuluj</a></div></form></div>';
    }
}