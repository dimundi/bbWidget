<?php
defined('ABSPATH') || exit;

class BBW_Participants {
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'bb_participants';
    }
    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            bbParticipantId bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            bbeditionId bigint(20) unsigned NOT NULL,
            bbRacePackageId bigint(20) unsigned DEFAULT NULL,
            startNumber int(10) unsigned DEFAULT NULL,
            active tinyint(1) NOT NULL DEFAULT 0,
            fullName varchar(255) NOT NULL DEFAULT '',
            irbEnabled tinyint(1) NOT NULL DEFAULT 0,
            irbNick varchar(255) NOT NULL DEFAULT '',
            email varchar(254) DEFAULT NULL,
            irbGroupName varchar(255) NOT NULL DEFAULT '',
            registeredByUserId bigint(20) unsigned DEFAULT NULL,
            managedByUserId bigint(20) unsigned DEFAULT NULL,
            orderItemId bigint(20) unsigned DEFAULT NULL,
            PRIMARY KEY  (bbParticipantId),
            KEY edition (bbeditionId),
            UNIQUE KEY edition_start (bbeditionId,startNumber),
            KEY package (bbRacePackageId),
            KEY registeredBy (registeredByUserId),
            KEY managedBy (managedByUserId),
            KEY orderItem (orderItemId)
        ) $charset;");
        if ($wpdb->last_error) { return; }
        // dbDelta does not reliably detect changes to column nullability.
        $column = $wpdb->get_row("SHOW COLUMNS FROM $table LIKE 'bbRacePackageId'");
        if ($column && $column->Null === 'NO') {
            $wpdb->query("ALTER TABLE $table MODIFY bbRacePackageId bigint(20) unsigned DEFAULT NULL");
        }
        if (!$wpdb->last_error) { update_option('bbw_participants_schema_version', '2', false); }
    }
    public static function save($input, $id = 0) {
        global $wpdb;
        $table = self::table();
        $old = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE bbParticipantId = %d", $id), ARRAY_A) : null;
        if ($id && !$old) { return new WP_Error('missing', 'Nie znaleziono uczestnika.'); }
        $keys = array('bbeditionId', 'bbRacePackageId', 'active', 'fullName', 'irbEnabled', 'irbNick', 'email', 'irbGroupName', 'registeredByUserId', 'managedByUserId');
        foreach ($keys as $key) {
            if (isset($input[$key]) && !is_scalar($input[$key])) { return new WP_Error('invalid', 'Nieprawidłowa wartość pola.'); }
        }
        $edition = (string) ($input['bbeditionId'] ?? '');
        $package = (string) ($input['bbRacePackageId'] ?? '');
        if (!ctype_digit($edition) || !$wpdb->get_var($wpdb->prepare('SELECT bbeditionId FROM ' . BBW_Editions::table() . ' WHERE bbeditionId = %d', $edition))) {
            return new WP_Error('edition', 'Wybierz istniejącą edycję.');
        }
        if ($package !== '' && (!ctype_digit($package) || !$wpdb->get_var($wpdb->prepare('SELECT bbRacePackageId FROM ' . BBW_Packages::table() . ' WHERE bbRacePackageId = %d AND bbeditionId = %d', $package, $edition)))) {
            return new WP_Error('package', 'Wybierz pakiet należący do wybranej edycji.');
        }
        $data = array('bbeditionId' => (int) $edition, 'bbRacePackageId' => $package === '' ? null : (int) $package, 'active' => empty($input['active']) ? 0 : 1, 'irbEnabled' => empty($input['irbEnabled']) ? 0 : 1);
        foreach (array('fullName', 'irbNick', 'irbGroupName') as $key) {
            $data[$key] = trim(sanitize_text_field((string) ($input[$key] ?? '')));
            if (mb_strlen($data[$key]) > 255) { return new WP_Error('length', 'Imię i nazwisko, nick oraz grupa mogą mieć maksymalnie 255 znaków.'); }
        }
        if ($data['irbEnabled'] && $data['irbNick'] === '') { return new WP_Error('nick', 'Podaj nick uczestnika biorącego udział w IRB.'); }
        $email = trim((string) ($input['email'] ?? ''));
        if ($email !== '' && (strlen($email) > 254 || !is_email($email))) { return new WP_Error('email', 'Podaj poprawny adres e-mail lub pozostaw pole puste.'); }
        $data['email'] = $email === '' ? null : $email;
        foreach (array('registeredByUserId', 'managedByUserId') as $key) {
            $user = (string) ($input[$key] ?? '');
            if ($user !== '' && $user !== '0' && (!ctype_digit($user) || !get_user_by('id', (int) $user))) { return new WP_Error('user', 'Wybierz istniejące konto użytkownika.'); }
            $data[$key] = $user === '' || $user === '0' ? null : (int) $user;
        }
        // Serialize number assignment within this edition; never trust a posted startNumber.
        $lock = 'bbw_start_' . md5(DB_NAME . ':' . $table . ':' . $edition);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== 1) {
            return new WP_Error('busy', 'Spróbuj ponownie zapisać uczestnika.');
        }
        try {
            $current = $id ? $wpdb->get_row($wpdb->prepare("SELECT startNumber, bbeditionId FROM $table WHERE bbParticipantId = %d", $id), ARRAY_A) : null;
            $data['startNumber'] = $current && $current['startNumber'] !== null ? (int) $current['startNumber'] : null;
            if ($data['startNumber'] !== null && (int) $current['bbeditionId'] !== (int) $edition && $wpdb->get_var($wpdb->prepare("SELECT bbParticipantId FROM $table WHERE bbeditionId = %d AND startNumber = %d", $edition, $data['startNumber']))) {
                return new WP_Error('number_used', 'Ten numer startowy jest już zajęty w wybranej edycji.');
            }
            if ($data['active'] && $data['startNumber'] === null) {
                $maximum = $wpdb->get_var($wpdb->prepare("SELECT MAX(startNumber) FROM $table WHERE bbeditionId = %d", $edition));
                $data['startNumber'] = max(99, (int) $maximum) + 1;
                if ($data['startNumber'] > 4294967295) { return new WP_Error('number_limit', 'Brak dostępnych numerów startowych.'); }
            }
            $result = $id ? $wpdb->update($table, $data, array('bbParticipantId' => $id)) : $wpdb->insert($table, $data);
            if ($result === false) { return new WP_Error('database', 'Nie udało się zapisać uczestnika.'); }
            return $id ?: (int) $wpdb->insert_id;
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }
    }
    public static function handle_save() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień.', '', array('response' => 403)); }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { wp_die('Niedozwolona metoda.', '', array('response' => 405)); }
        check_admin_referer('bbw_save_participant');
        $input = wp_unslash($_POST);
        if (isset($input['bbParticipantId']) && (!is_scalar($input['bbParticipantId']) || !ctype_digit((string) $input['bbParticipantId']))) { wp_die('Nieprawidłowy identyfikator.', '', array('response' => 400)); }
        $id = absint($input['bbParticipantId'] ?? 0);
        $result = self::save($input, $id);
        $args = array('page' => 'bbwidget-participants');
        if (is_wp_error($result)) {
            $token = strtolower(wp_generate_password(20, false));
            $values = array('active' => 0, 'irbEnabled' => 0);
            foreach ($input as $key => $value) { if (is_scalar($value) && $key !== '_wpnonce') { $values[$key] = sanitize_text_field((string) $value); } }
            set_transient('bbw_participant_' . get_current_user_id() . '_' . $token, array('values' => $values, 'message' => $result->get_error_message()), 300);
            $args['form_error'] = $token;
            if ($id) { $args['edit'] = $id; } else { $args['view'] = 'new'; }
        } else { $args['saved'] = 1; }
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }
    private static function text_field($row, $key, $label, $type = 'text') {
        echo '<div class="bbw-field"><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label><input id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" type="' . esc_attr($type) . '" value="' . esc_attr($row[$key] ?? '') . '"' . ($type === 'number' ? ' min="1" max="4294967295" step="1"' : ' maxlength="' . ($type === 'email' ? '254' : '255') . '"') . '></div>';
    }
    public static function render() {
        if (!current_user_can('manage_options')) { return; }
        global $wpdb;
        $table = self::table();
        $edition_table = BBW_Editions::table();
        $package_table = BBW_Packages::table();
        $url = admin_url('admin.php?page=bbwidget-participants');
        $editions = $wpdb->get_results("SELECT bbeditionId, editionNumber, isCurrent FROM $edition_table ORDER BY editionNumber DESC", ARRAY_A);
        $current_edition = 0;
        foreach ($editions as $item) { if ($item['isCurrent']) { $current_edition = (int) $item['bbeditionId']; break; } }
        $id = isset($_GET['edit']) && is_scalar($_GET['edit']) ? absint($_GET['edit']) : 0;
        $form = isset($_GET['edit']) || (isset($_GET['view']) && $_GET['view'] === 'new');
        echo '<div class="wrap bbw-admin">';
        if (!$form) {
            echo '<h1 class="wp-heading-inline">Uczestnicy</h1> <a class="page-title-action" href="' . esc_url(add_query_arg('view', 'new', $url)) . '">Dodaj uczestnika</a><hr class="wp-header-end">';
            if (isset($_GET['saved'])) { echo '<div class="notice notice-success is-dismissible"><p>Uczestnik został zapisany.</p></div>'; }
            $edition = isset($_GET['edition']) && is_scalar($_GET['edition']) ? absint($_GET['edition']) : $current_edition;
            $search = isset($_GET['search']) && is_string($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
            echo '<form method="get" class="bbw-participant-filter"><input type="hidden" name="page" value="bbwidget-participants"><label for="bbw-edition-filter">Edycja</label> <select id="bbw-edition-filter" name="edition"><option value="0">Wszystkie edycje</option>';
            foreach ($editions as $item) { echo '<option value="' . esc_attr($item['bbeditionId']) . '" ' . selected($edition, $item['bbeditionId'], false) . '>' . esc_html($item['editionNumber']) . '. edycja</option>'; }
            echo '</select> <label for="bbw-search">Szukaj</label> <input type="search" id="bbw-search" name="search" value="' . esc_attr($search) . '" placeholder="Nazwisko, nick, e-mail lub numer"> <button class="button">Filtruj</button></form>';
            $where = ' WHERE 1=1';
            if ($edition) { $where .= $wpdb->prepare(' AND p.bbeditionId = %d', $edition); }
            if ($search !== '') {
                $like = '%' . $wpdb->esc_like($search) . '%';
                $where .= $wpdb->prepare(' AND (p.fullName LIKE %s OR p.irbNick LIKE %s OR p.email LIKE %s OR p.startNumber = %d)', $like, $like, $like, ctype_digit($search) ? (int) $search : 0);
            }
            $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table p $where");
            $pages = max(1, (int) ceil($count / 25));
            $page = isset($_GET['paged']) && is_scalar($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
            $page = min($page, $pages);
            $limit = $wpdb->prepare(' LIMIT %d OFFSET %d', 25, ($page - 1) * 25);
            $rows = $wpdb->get_results("SELECT p.*, e.editionNumber, r.name AS packageName FROM $table p LEFT JOIN $edition_table e ON e.bbeditionId=p.bbeditionId LEFT JOIN $package_table r ON r.bbRacePackageId=p.bbRacePackageId $where ORDER BY e.editionNumber DESC, p.bbParticipantId DESC $limit", ARRAY_A);
            echo '<p>Liczba uczestników: <strong>' . esc_html($count) . '</strong></p><div class="bbw-table-scroll"><table class="widefat striped bbw-editions"><thead><tr><th>Uczestnik</th><th>Edycja</th><th>Pakiet</th><th>Numer startowy</th><th>Dopuszczony</th><th>IRB</th><th>Działania</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                $edit = add_query_arg('edit', $row['bbParticipantId'], $url);
                $label = $row['fullName'] ?: ($row['irbNick'] ?: 'Uczestnik #' . $row['bbParticipantId']);
                echo '<tr><td><a class="row-title" href="' . esc_url($edit) . '">' . esc_html($label) . '</a>';
                if ($row['irbNick'] && $row['fullName']) { echo '<br>' . esc_html($row['irbNick']); }
                echo '</td><td>' . esc_html($row['editionNumber']) . '</td><td>' . esc_html($row['packageName'] ?: 'Nie wybrano') . '</td><td>' . esc_html($row['startNumber'] ?: '—') . '</td><td>' . ($row['active'] ? '<span class="bbw-current">Tak</span>' : 'Nie') . '</td><td>' . ($row['irbEnabled'] ? 'Tak' : 'Nie') . '</td><td><a class="button" href="' . esc_url($edit) . '">Edytuj</a></td></tr>';
            }
            if (!$rows) { echo '<tr><td colspan="7">Brak uczestników pasujących do wybranych filtrów.</td></tr>'; }
            echo '</tbody></table></div>';
            if ($pages > 1) {
                $base = add_query_arg(array('edition' => $edition, 'search' => $search, 'paged' => '%#%'), $url);
                echo '<nav class="tablenav" aria-label="Strony listy">' . paginate_links(array('base' => $base, 'format' => '', 'current' => $page, 'total' => $pages, 'prev_text' => '← Poprzednia', 'next_text' => 'Następna →')) . '</nav>';
            }
            echo '</div>'; return;
        }
        echo '<a class="bbw-back" href="' . esc_url($url) . '">← Wróć do listy uczestników</a>';
        $row = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE bbParticipantId=%d", $id), ARRAY_A) : array('bbeditionId' => $current_edition, 'registeredByUserId' => get_current_user_id(), 'managedByUserId' => get_current_user_id());
        if (isset($_GET['edit']) && (!$id || !$row)) { echo '<h1>Nie znaleziono uczestnika</h1></div>'; return; }
        echo '<h1>' . ($id ? 'Edytuj uczestnika' : 'Dodaj uczestnika') . '</h1>';
        $packages = $wpdb->get_results("SELECT p.bbRacePackageId, p.bbeditionId, p.name, e.editionNumber FROM $package_table p JOIN $edition_table e ON e.bbeditionId=p.bbeditionId ORDER BY e.editionNumber DESC, p.name", ARRAY_A);
        if (isset($_GET['form_error']) && is_string($_GET['form_error'])) {
            $key = 'bbw_participant_' . get_current_user_id() . '_' . sanitize_key($_GET['form_error']);
            $error = get_transient($key);
            if ($error) { delete_transient($key); $row = array_merge($row, $error['values']); echo '<div class="notice notice-error" role="alert"><p>' . esc_html($error['message']) . '</p></div>'; }
        }
        echo '<form method="post" class="bbw-edition-form" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bbw_save_participant');
        echo '<input type="hidden" name="action" value="bbw_save_participant"><input type="hidden" name="bbParticipantId" value="' . esc_attr($id) . '"><section class="bbw-card"><h2>Zapis na bieg</h2><div class="bbw-fields"><div class="bbw-field"><label for="bbeditionId">Edycja (wymagana)</label><select id="bbeditionId" name="bbeditionId" required><option value="">Wybierz edycję</option>';
        foreach ($editions as $edition) { echo '<option value="' . esc_attr($edition['bbeditionId']) . '" ' . selected($row['bbeditionId'] ?? '', $edition['bbeditionId'], false) . '>' . esc_html($edition['editionNumber']) . '. edycja</option>'; }
        echo '</select></div><div class="bbw-field"><label for="bbRacePackageId">Pakiet startowy</label><select id="bbRacePackageId" name="bbRacePackageId"><option value="">Uzupełnię później</option>';
        foreach ($packages as $package) { echo '<option data-edition="' . esc_attr($package['bbeditionId']) . '" value="' . esc_attr($package['bbRacePackageId']) . '" ' . selected($row['bbRacePackageId'] ?? '', $package['bbRacePackageId'], false) . '>' . esc_html($package['name'] . ' — ' . $package['editionNumber'] . '. edycja') . '</option>'; }
        echo '</select></div></div></section><section class="bbw-card"><h2>Dane uczestnika</h2><p>Imię, nazwisko i e-mail są opcjonalne. Zapis uczestnika nie tworzy konta do logowania.</p><div class="bbw-fields">';
        self::text_field($row, 'fullName', 'Imię i nazwisko');
        self::text_field($row, 'email', 'E-mail', 'email');
        echo '</div></section><section class="bbw-card"><h2>Dopuszczenie do udziału</h2><p><label><input type="checkbox" name="active" value="1" ' . checked(!empty($row['active']), true, false) . '> Uczestnik dopuszczony do udziału</label></p><p class="description">Ręczna aktywacja nie oznacza opłacenia zamówienia.</p>';
        echo '<p><strong>Numer startowy: ' . esc_html($row['startNumber'] ?? 'jeszcze nienadany') . '</strong></p><p class="description">Numer zostanie nadany przy dopuszczeniu do udziału. Numeracja w każdej edycji zaczyna się od 100.</p>';
        echo '</section><section class="bbw-card"><h2>Indywidualny Ranking Belfrów</h2><p><label><input id="irbEnabled" type="checkbox" name="irbEnabled" value="1" ' . checked(!empty($row['irbEnabled']), true, false) . '> Uczestnik wyraził zgodę na udział w IRB</label></p><div class="bbw-fields">';
        self::text_field($row, 'irbNick', 'Nick (wymagany przy udziale w IRB)');
        self::text_field($row, 'irbGroupName', 'Grupa (opcjonalna)');
        echo '</div></section><section class="bbw-card"><h2>Powiązane konta</h2><div class="bbw-fields">';
        $users = get_users(array('fields' => array('ID', 'display_name', 'user_email'), 'orderby' => 'display_name'));
        foreach (array('registeredByUserId' => 'Konto zapisującego', 'managedByUserId' => 'Konto zarządzającego IRB') as $key => $label) {
            echo '<div class="bbw-field"><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label><select id="' . esc_attr($key) . '" name="' . esc_attr($key) . '"><option value="">Bez powiązanego konta</option>';
            foreach ($users as $user) { echo '<option value="' . esc_attr($user->ID) . '" ' . selected($row[$key] ?? '', $user->ID, false) . '>' . esc_html($user->display_name . ' — ' . $user->user_email) . '</option>'; }
            echo '</select></div>';
        }
        echo '</div><p class="description">Dla danych archiwalnych konta mogą pozostać niepowiązane.</p></section><section class="bbw-card"><h2>Zakup pakietu</h2><p>' . (!empty($row['orderItemId']) ? 'Pozycja zamówienia: #' . esc_html($row['orderItemId']) : 'Brak powiązanego zakupu.') . '</p></section><div class="bbw-actions">';
        submit_button($id ? 'Zapisz zmiany' : 'Dodaj uczestnika', 'primary', 'submit', false);
        echo ' <a class="button" href="' . esc_url($url) . '">Anuluj</a></div></form></div>';
    }
}