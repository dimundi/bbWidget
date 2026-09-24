<?php
defined('ABSPATH') || exit;

class BBW_Packages {
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'bb_race_packages';
    }
    public static function activities() {
        return array('walk' => 'Spacer', 'run' => 'Bieg', 'bike' => 'Rower', 'iron_teacher' => 'Iron Teacher');
    }
    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            bbRacePackageId bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            bbeditionId bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            activityType enum('walk','run','bike','iron_teacher') NOT NULL,
            walkDistance int(10) unsigned NOT NULL DEFAULT 0,
            runDistance int(10) unsigned NOT NULL DEFAULT 0,
            bikeDistance int(10) unsigned NOT NULL DEFAULT 0,
            productId bigint(20) unsigned DEFAULT NULL,
            PRIMARY KEY  (bbRacePackageId),
            KEY bbeditionId (bbeditionId),
            UNIQUE KEY productId (productId)
        ) $charset;");
        if (!$wpdb->last_error) { update_option('bbw_packages_schema_version', '1', false); }
    }
    public static function save($input, $id = 0) {
        global $wpdb;
        $table = self::table();
        $old = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE bbRacePackageId = %d", $id), ARRAY_A) : null;
        if ($id && !$old) { return new WP_Error('missing', 'Nie znaleziono pakietu.'); }
        foreach (array('bbeditionId', 'name', 'activityType', 'walkDistance', 'runDistance', 'bikeDistance', 'productId') as $key) {
            if (isset($input[$key]) && !is_scalar($input[$key])) { return new WP_Error('invalid', 'Nieprawidłowa wartość pola.'); }
        }
        $edition = (string) ($input['bbeditionId'] ?? '');
        if (!ctype_digit($edition) || !$wpdb->get_var($wpdb->prepare('SELECT bbeditionId FROM ' . BBW_Editions::table() . ' WHERE bbeditionId = %d', $edition))) {
            return new WP_Error('edition', 'Wybierz istniejącą edycję.');
        }
        $name = trim(sanitize_text_field((string) ($input['name'] ?? '')));
        if ($name === '' || mb_strlen($name) > 255) { return new WP_Error('name', 'Podaj nazwę pakietu (maksymalnie 255 znaków).'); }
        $activity = (string) ($input['activityType'] ?? '');
        if (!isset(self::activities()[$activity])) { return new WP_Error('activity', 'Wybierz typ aktywności.'); }
        $data = array('bbeditionId' => (int) $edition, 'name' => $name, 'activityType' => $activity);
        foreach (array('walkDistance', 'runDistance', 'bikeDistance') as $key) {
            $value = (string) ($input[$key] ?? '0');
            if (!ctype_digit($value) || (float) $value > 4294967295) { return new WP_Error('distance', 'Dystanse podaj w pełnych metrach: 0 lub więcej.'); }
            $data[$key] = (int) $value;
        }
        $product = (string) ($input['productId'] ?? '');
        $data['productId'] = null;
        if ($product !== '' && $product !== '0') {
            if (!ctype_digit($product)) { return new WP_Error('product', 'Wybierz poprawny produkt.'); }
            if (function_exists('wc_get_product')) {
                $object = wc_get_product((int) $product);
                if (!$object || get_post_status((int) $product) === 'trash') { return new WP_Error('product', 'Nie znaleziono produktu WooCommerce.'); }
            } elseif (!$old || (int) $old['productId'] !== (int) $product) {
                return new WP_Error('woocommerce', 'Aby powiązać produkt, najpierw uruchom WooCommerce.');
            }
            if ($wpdb->get_var($wpdb->prepare("SELECT bbRacePackageId FROM $table WHERE productId = %d AND bbRacePackageId <> %d", $product, $id))) {
                return new WP_Error('product_used', 'Ten produkt jest już przypisany do innego pakietu. Wybierz osobny produkt.');
            }
            $data['productId'] = (int) $product;
        }
        $result = $id ? $wpdb->update($table, $data, array('bbRacePackageId' => $id)) : $wpdb->insert($table, $data);
        if ($result === false) { return new WP_Error('database', 'Nie udało się zapisać pakietu.'); }
        return $id ?: (int) $wpdb->insert_id;
    }
    public static function handle_save() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień.', '', array('response' => 403)); }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { wp_die('Niedozwolona metoda.', '', array('response' => 405)); }
        check_admin_referer('bbw_save_package');
        $input = wp_unslash($_POST);
        if (isset($input['bbRacePackageId']) && (!is_scalar($input['bbRacePackageId']) || !ctype_digit((string) $input['bbRacePackageId']))) {
            wp_die('Nieprawidłowy identyfikator.', '', array('response' => 400));
        }
        $id = absint($input['bbRacePackageId'] ?? 0);
        $result = self::save($input, $id);
        $args = array('page' => 'bbwidget-packages');
        if (is_wp_error($result)) {
            $token = strtolower(wp_generate_password(20, false));
            $values = array();
            foreach ($input as $key => $value) {
                if (is_scalar($value) && $key !== '_wpnonce') { $values[$key] = sanitize_text_field((string) $value); }
            }
            set_transient('bbw_package_' . get_current_user_id() . '_' . $token, array('values' => $values, 'message' => $result->get_error_message()), 300);
            $args['form_error'] = $token;
            if ($id) { $args['edit'] = $id; } else { $args['view'] = 'new'; }
        } else { $args['saved'] = 1; }
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }
    private static function product_label($id) {
        if (!$id) { return 'Nie powiązano'; }
        $title = get_the_title($id);
        return ($title ?: 'Produkt') . ' (#' . $id . ')';
    }
    public static function render() {
        if (!current_user_can('manage_options')) { return; }
        global $wpdb;
        $table = self::table();
        $editions_table = BBW_Editions::table();
        $url = admin_url('admin.php?page=bbwidget-packages');
        $id = isset($_GET['edit']) && is_scalar($_GET['edit']) ? absint($_GET['edit']) : 0;
        $form = isset($_GET['edit']) || (isset($_GET['view']) && $_GET['view'] === 'new');
        $editions = $wpdb->get_results("SELECT bbeditionId, editionNumber FROM $editions_table ORDER BY editionNumber DESC", ARRAY_A);
        $activities = self::activities();
        echo '<div class="wrap bbw-admin">';
        if (!$form) {
            echo '<h1 class="wp-heading-inline">Pakiety startowe</h1> <a class="page-title-action" href="' . esc_url(add_query_arg('view', 'new', $url)) . '">Dodaj pakiet</a><hr class="wp-header-end">';
            if (isset($_GET['saved'])) { echo '<div class="notice notice-success is-dismissible"><p>Pakiet został zapisany.</p></div>'; }
            echo '<p>Pakiety przypisane do poszczególnych edycji. Dystanse podane są w metrach.</p>';
            $filter = isset($_GET['edition']) && is_scalar($_GET['edition']) ? absint($_GET['edition']) : 0;
            echo '<form method="get" class="bbw-package-filter"><input type="hidden" name="page" value="bbwidget-packages"><label for="bbw-edition-filter">Edycja </label><select id="bbw-edition-filter" name="edition"><option value="0">Wszystkie edycje</option>';
            foreach ($editions as $edition) { echo '<option value="' . esc_attr($edition['bbeditionId']) . '" ' . selected($filter, $edition['bbeditionId'], false) . '>' . esc_html($edition['editionNumber']) . '. edycja</option>'; }
            echo '</select> <button class="button">Filtruj</button></form>';
            $where = $filter ? $wpdb->prepare(' WHERE p.bbeditionId = %d', $filter) : '';
            $rows = $wpdb->get_results("SELECT p.*, e.editionNumber FROM $table p LEFT JOIN $editions_table e ON e.bbeditionId = p.bbeditionId $where ORDER BY e.editionNumber DESC, p.bbRacePackageId", ARRAY_A);
            echo '<div class="bbw-table-scroll"><table class="widefat striped bbw-editions"><thead><tr><th>Pakiet</th><th>Edycja</th><th>Aktywność</th><th>Spacer (m)</th><th>Bieg (m)</th><th>Rower (m)</th><th>Produkt</th><th>Działania</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                $edit = add_query_arg('edit', $row['bbRacePackageId'], $url);
                echo '<tr><td><a class="row-title" href="' . esc_url($edit) . '">' . esc_html($row['name']) . '</a></td><td>' . esc_html($row['editionNumber']) . '</td><td>' . esc_html($activities[$row['activityType']] ?? $row['activityType']) . '</td>';
                foreach (array('walkDistance', 'runDistance', 'bikeDistance') as $key) { echo '<td>' . esc_html(number_format_i18n((int) $row[$key])) . '</td>'; }
                echo '<td>' . esc_html(self::product_label($row['productId'])) . '</td><td><a class="button" href="' . esc_url($edit) . '">Edytuj</a></td></tr>';
            }
            if (!$rows) { echo '<tr><td colspan="8">Brak pakietów. Dodaj pakiet lub wybierz inną edycję.</td></tr>'; }
            echo '</tbody></table></div></div>';
            return;
        }
        echo '<a class="bbw-back" href="' . esc_url($url) . '">← Wróć do listy pakietów</a>';
        $row = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE bbRacePackageId = %d", $id), ARRAY_A) : array();
        if (isset($_GET['edit']) && (!$id || !$row)) { echo '<h1>Nie znaleziono pakietu</h1></div>'; return; }
        echo '<h1>' . ($id ? 'Edytuj pakiet' : 'Dodaj pakiet') . '</h1>';
        if (!$editions) { echo '<p>Najpierw <a href="' . esc_url(admin_url('admin.php?page=bbwidget-editions&view=new')) . '">dodaj edycję</a>.</p></div>'; return; }
        if (isset($_GET['form_error']) && is_string($_GET['form_error'])) {
            $key = 'bbw_package_' . get_current_user_id() . '_' . sanitize_key($_GET['form_error']);
            $error = get_transient($key);
            if ($error) {
                delete_transient($key);
                $row = array_merge($row, $error['values']);
                echo '<div class="notice notice-error" role="alert"><p>' . esc_html($error['message']) . '</p></div>';
            }
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="bbw-edition-form">';
        wp_nonce_field('bbw_save_package');
        echo '<input type="hidden" name="action" value="bbw_save_package"><input type="hidden" name="bbRacePackageId" value="' . esc_attr($id) . '"><section class="bbw-card"><h2>Podstawowe informacje</h2><div class="bbw-fields">';
        echo '<div class="bbw-field"><label for="bbeditionId">Edycja (wymagana)</label><select id="bbeditionId" name="bbeditionId" required><option value="">Wybierz edycję</option>';
        foreach ($editions as $edition) { echo '<option value="' . esc_attr($edition['bbeditionId']) . '" ' . selected($row['bbeditionId'] ?? '', $edition['bbeditionId'], false) . '>' . esc_html($edition['editionNumber']) . '. edycja</option>'; }
        echo '</select></div><div class="bbw-field"><label for="name">Nazwa pakietu (wymagana)</label><input id="name" name="name" type="text" required maxlength="255" value="' . esc_attr($row['name'] ?? '') . '"></div><div class="bbw-field"><label for="activityType">Aktywność (wymagana)</label><select id="activityType" name="activityType" required><option value="">Wybierz aktywność</option>';
        foreach ($activities as $value => $label) { echo '<option value="' . esc_attr($value) . '" ' . selected($row['activityType'] ?? '', $value, false) . '>' . esc_html($label) . '</option>'; }
        echo '</select></div></div></section><section class="bbw-card"><h2>Dystanse</h2><p>Podaj pełne metry, np. 6000 dla 6 km. Wpisz 0, jeśli pakiet nie obejmuje danej dyscypliny.</p><div class="bbw-fields">';
        foreach (array('walkDistance' => 'Spacer (m)', 'runDistance' => 'Bieg (m)', 'bikeDistance' => 'Rower (m)') as $key => $label) {
            echo '<div class="bbw-field"><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label><input id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" type="number" required min="0" max="4294967295" step="1" value="' . esc_attr($row[$key] ?? 0) . '"></div>';
        }
        echo '</div></section><section class="bbw-card"><h2>Produkt WooCommerce</h2>';
        if (function_exists('wc_get_products')) {
            $products = wc_get_products(array('limit' => -1, 'status' => array('publish', 'private', 'draft', 'pending'), 'orderby' => 'title', 'order' => 'ASC'));
            echo '<div class="bbw-field"><label for="productId">Powiązany produkt</label><select id="productId" name="productId"><option value="">Bez powiązania — uzupełnię później</option>';
            $found = false;
            foreach ($products as $product) {
                $pid = $product->get_id();
                if ((int) ($row['productId'] ?? 0) === $pid) { $found = true; }
                echo '<option value="' . esc_attr($pid) . '" ' . selected($row['productId'] ?? '', $pid, false) . '>' . esc_html(self::product_label($pid)) . '</option>';
            }
            if (!$found && !empty($row['productId'])) { echo '<option selected value="' . esc_attr($row['productId']) . '">' . esc_html(self::product_label($row['productId'])) . '</option>'; }
            echo '</select><p class="description">Wybierz osobny produkt dla tego pakietu i tej edycji.</p></div>';
        } else {
            echo '<p>WooCommerce nie jest aktywny. Możesz zapisać pakiet, a produkt powiązać później.</p><input type="hidden" name="productId" value="' . esc_attr($row['productId'] ?? '') . '">';
        }
        echo '</section><div class="bbw-actions">';
        submit_button($id ? 'Zapisz zmiany' : 'Dodaj pakiet', 'primary', 'submit', false);
        echo ' <a class="button" href="' . esc_url($url) . '">Anuluj</a></div></form></div>';
    }
}