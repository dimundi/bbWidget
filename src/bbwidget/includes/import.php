<?php
defined('ABSPATH') || exit;

class BBW_Import {
    public static function mapping_table() { global $wpdb; return $wpdb->prefix . 'bb_import_participants'; }
    public static function results_table() { global $wpdb; return $wpdb->prefix . 'bb_irb_results'; }
    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $mapping = self::mapping_table();
        $results = self::results_table();
        dbDelta("CREATE TABLE $mapping (
            sourceParticipantId bigint(20) unsigned NOT NULL,
            bbParticipantId bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (sourceParticipantId),
            UNIQUE KEY participant (bbParticipantId)
        ) ENGINE=InnoDB $charset;");
        if ($wpdb->last_error) { return; }
        dbDelta("CREATE TABLE $results (
            bbIrbResultId bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            bbParticipantId bigint(20) unsigned NOT NULL,
            walkDistance int(10) unsigned NOT NULL DEFAULT 0,
            runDistance int(10) unsigned NOT NULL DEFAULT 0,
            bikeDistance int(10) unsigned NOT NULL DEFAULT 0,
            irbNick varchar(255) NOT NULL DEFAULT '',
            irbGroupName varchar(255) NOT NULL DEFAULT '',
            email varchar(254) DEFAULT NULL,
            PRIMARY KEY  (bbIrbResultId),
            UNIQUE KEY participant (bbParticipantId)
        ) ENGINE=InnoDB $charset;");
        if (!$wpdb->last_error) { update_option('bbw_import_schema_version', '2', false); }
    }
    private static function uint($value, $min = 1, $max = PHP_INT_MAX) {
        return is_int($value) && $value >= $min && $value <= $max;
    }
    public static function decode($json) {
        $data = json_decode($json, true, 64);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) { return new WP_Error('json', 'Plik nie zawiera poprawnego JSON-a.'); }
        foreach (array('schemaVersion' => 1, 'source' => 'bb-api', 'scope' => 'all_editions_active_participants', 'distanceUnit' => 'm') as $key => $value) {
            if (($data[$key] ?? null) !== $value) { return new WP_Error('format', 'Nieobsługiwany format eksportu: ' . $key . '.'); }
        }
        if (!isset($data['editions'], $data['participants']) || !is_array($data['editions']) || !is_array($data['participants']) || !array_is_list($data['editions']) || !array_is_list($data['participants']) || ($data['participantCount'] ?? null) !== count($data['participants'])) {
            return new WP_Error('count', 'Brakuje listy edycji lub uczestników albo liczba uczestników jest niezgodna.');
        }
        $editions = array(); $numbers = array();
        foreach ($data['editions'] as $edition) {
            if (!is_array($edition) || !self::uint($edition['sourceEditionId'] ?? null) || !self::uint($edition['editionNumber'] ?? null, 1, 4294967295) || isset($editions[$edition['sourceEditionId']]) || isset($numbers[$edition['editionNumber']])) {
                return new WP_Error('edition', 'Nieprawidłowa lub powtórzona edycja w pliku.');
            }
            $editions[$edition['sourceEditionId']] = $edition['editionNumber'];
            $numbers[$edition['editionNumber']] = true;
        }
        $ids = array(); $starts = array();
        foreach ($data['participants'] as $index => &$row) {
            $source_id = is_array($row) && is_scalar($row['sourceParticipantId'] ?? null) ? (string) $row['sourceParticipantId'] : 'brak lub nieprawidłowa wartość';
            $error = 'Uczestnik sourceParticipantId=' . $source_id . ': ';
            if (!is_array($row) || !self::uint($row['sourceParticipantId'] ?? null) || isset($ids[$row['sourceParticipantId']])) { return new WP_Error('id', $error . 'nieprawidłowy lub powtórzony identyfikator.'); }
            $ids[$row['sourceParticipantId']] = true;
            if (!self::uint($row['sourceEditionId'] ?? null) || !isset($editions[$row['sourceEditionId']])) { return new WP_Error('edition', $error . 'brak edycji w eksporcie.'); }
            if (($row['active'] ?? null) !== true || !isset($row['irbEnabled']) || !is_bool($row['irbEnabled'])) { return new WP_Error('flags', $error . 'oczekiwano aktywnego uczestnika i flagi IRB.'); }
            if (!is_string($row['activityType'] ?? null) || !isset(BBW_Packages::activities()[$row['activityType']])) { return new WP_Error('activity', $error . 'nieznany typ aktywności. Uzupełnij dane źródłowe przed importem.'); }
            foreach (array('sourceProductId', 'startNumber') as $key) {
                if (!array_key_exists($key, $row) || ($row[$key] !== null && !self::uint($row[$key], 1, $key === 'startNumber' ? 4294967295 : PHP_INT_MAX))) { return new WP_Error('number', $error . 'nieprawidłowe pole ' . $key . '.'); }
            }
            if ($row['startNumber'] !== null) {
                $start = $row['sourceEditionId'] . ':' . $row['startNumber'];
                $starts[$start] = ($starts[$start] ?? 0) + 1;
            }
            foreach (array('fullName', 'irbNick', 'irbGroupName') as $key) {
                if (!array_key_exists($key, $row) || ($row[$key] !== null && !is_string($row[$key])) || mb_strlen($row[$key] ?? '') > 255) { return new WP_Error('text', $error . 'nieprawidłowe pole ' . $key . '.'); }
                $row[$key] = $row[$key] ?? '';
            }
            if (!array_key_exists('email', $row) || ($row['email'] !== null && (!is_string($row['email']) || strlen($row['email']) > 254 || ($row['email'] !== '' && !is_email($row['email']))))) { return new WP_Error('email', $error . 'nieprawidłowy e-mail.'); }
            $row['email'] = $row['email'] ?: null;
            foreach (array('walkDistance', 'runDistance', 'bikeDistance') as $key) {
                if (!self::uint($row[$key] ?? null, 0, 4294967295)) { return new WP_Error('distance', $error . 'dystans musi być liczbą całkowitą w metrach (0 lub więcej).'); }
            }
        }
        unset($row);
        // Clear every occurrence, including the first and any third or later duplicate.
        foreach ($data['participants'] as &$row) {
            if ($row['startNumber'] !== null && $starts[$row['sourceEditionId'] . ':' . $row['startNumber']] > 1) {
                $row['startNumber'] = null;
            }
        }
        unset($row);
        return $data;
    }
    public static function group_key($row) {
        return $row['sourceEditionId'] . '_' . $row['activityType'];
    }
    public static function preview($data) {
        global $wpdb;
        $edition_numbers = array_column($data['editions'], 'editionNumber', 'sourceEditionId');
        $local_editions = $wpdb->get_results('SELECT bbeditionId, editionNumber FROM ' . BBW_Editions::table(), ARRAY_A);
        $edition_ids = array_column($local_editions, 'bbeditionId', 'editionNumber');
        $imported = array_fill_keys($wpdb->get_col('SELECT sourceParticipantId FROM ' . self::mapping_table()), true);
        $groups = array(); $skipped = 0;
        foreach ($data['participants'] as $row) {
            if (isset($imported[$row['sourceParticipantId']])) { $skipped++; continue; }
            $key = self::group_key($row);
            if (!isset($groups[$key])) {
                $number = $edition_numbers[$row['sourceEditionId']];
                $groups[$key] = array('editionNumber' => $number, 'bbeditionId' => (int) ($edition_ids[$number] ?? 0), 'sourceProductId' => $row['sourceProductId'], 'activityType' => $row['activityType'], 'count' => 0, 'irb' => 0);
            }
            $groups[$key]['count']++; $groups[$key]['irb'] += (int) $row['irbEnabled'];
        }
        return array('groups' => $groups, 'skipped' => $skipped, 'new' => count($data['participants']) - $skipped);
    }
    public static function import($data) {
        global $wpdb;
        // Validate again immediately before writing; preview is not authorization to trust posted IDs.
        $data = self::decode(wp_json_encode($data));
        if (is_wp_error($data)) { return $data; }
        $lock = 'bbw_import_' . md5(DB_NAME . ':' . $wpdb->prefix);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== 1) { return new WP_Error('busy', 'Trwa inny import. Spróbuj ponownie.'); }
        try {
            $preview = self::preview($data);
            foreach ($preview['groups'] as $key => $group) {
                if (!$group['bbeditionId']) { throw new RuntimeException('Najpierw dodaj edycję ' . $group['editionNumber'] . '.'); }
            }
            if ($wpdb->query('START TRANSACTION') === false) { throw new RuntimeException('Nie można rozpocząć zapisu.'); }
            $count = 0; $results = 0; $skipped = 0;
            foreach ($data['participants'] as $row) {
                if ($wpdb->get_var($wpdb->prepare('SELECT bbParticipantId FROM ' . self::mapping_table() . ' WHERE sourceParticipantId=%d', $row['sourceParticipantId']))) { $skipped++; continue; }
                $group = $preview['groups'][self::group_key($row)];
                $participant = array_intersect_key($row, array_flip(array('fullName', 'irbNick', 'irbGroupName', 'email', 'startNumber')));
                $participant += array('bbeditionId' => $group['bbeditionId'], 'bbRacePackageId' => null, 'active' => 1, 'irbEnabled' => (int) $row['irbEnabled']);
                if ($row['startNumber'] !== null && $wpdb->get_var($wpdb->prepare('SELECT bbParticipantId FROM ' . BBW_Participants::table() . ' WHERE bbeditionId=%d AND startNumber=%d', $group['bbeditionId'], $row['startNumber']))) { throw new RuntimeException('Uczestnik sourceParticipantId=' . $row['sourceParticipantId'] . ': numer ' . $row['startNumber'] . ' jest już zajęty w edycji ' . $group['editionNumber'] . '. Nie zapisano żadnych danych.'); }
                if ($wpdb->insert(BBW_Participants::table(), $participant) === false) { throw new RuntimeException('Nie udało się zapisać uczestnika źródłowego #' . $row['sourceParticipantId'] . '. Nie zapisano żadnych danych.'); }
                $id = (int) $wpdb->insert_id;
                if ($wpdb->insert(BBW_Participant_Activities::table(), array('bbParticipantId' => $id, 'activityType' => $row['activityType'])) === false) { throw new RuntimeException('Nie udało się zapisać aktywności uczestnika sourceParticipantId=' . $row['sourceParticipantId'] . '.'); }
                if ($wpdb->insert(self::mapping_table(), array('sourceParticipantId' => $row['sourceParticipantId'], 'bbParticipantId' => $id)) === false) { throw new RuntimeException('Nie udało się zapisać powiązania importu.'); }
                if ($row['irbEnabled']) {
                    $result = array_intersect_key($row, array_flip(array('walkDistance', 'runDistance', 'bikeDistance', 'irbNick', 'irbGroupName', 'email')));
                    $result['bbParticipantId'] = $id;
                    if ($wpdb->insert(self::results_table(), $result) === false) { throw new RuntimeException('Nie udało się zapisać podsumowania IRB.'); }
                    $results++;
                }
                $count++;
            }
            if ($wpdb->query('COMMIT') === false) { throw new RuntimeException('Nie udało się zakończyć zapisu.'); }
            return array('imported' => $count, 'results' => $results, 'skipped' => $skipped);
        } catch (RuntimeException $error) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('import', $error->getMessage());
        } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
    }
    private static function transient_key($token) { return 'bbw_import_' . get_current_user_id() . '_' . $token; }
    private static function url() { return admin_url('admin.php?page=bbwidget-participants&view=import'); }
    public static function handle() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień.', '', array('response' => 403)); }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { wp_die('Niedozwolona metoda.', '', array('response' => 405)); }
        check_admin_referer('bbw_import');
        $token = isset($_POST['token']) && is_string($_POST['token']) ? sanitize_key(wp_unslash($_POST['token'])) : '';
        $state = $token ? get_transient(self::transient_key($token)) : false;
        if (isset($_POST['confirm'])) {
            if (!$state || empty($state['data']) || isset($state['done'])) { wp_die('Podgląd wygasł lub import został już zakończony. Wczytaj plik ponownie.'); }
            $result = self::import($state['data']);
            if (is_wp_error($result)) { $state['error'] = $result->get_error_message(); }
            else { $state = array('done' => $result); }
        } else {
            $token = strtolower(wp_generate_password(32, false));
            $file = $_FILES['import_file'] ?? array();
            if (!isset($file['error'], $file['tmp_name'], $file['size']) || $file['error'] !== UPLOAD_ERR_OK || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name']) || $file['size'] > 5 * 1024 * 1024) {
                $state = array('error' => 'Wybierz plik JSON o rozmiarze do 5 MB. Sprawdź też limit przesyłania plików serwera.');
            } else {
                $data = self::decode(file_get_contents($file['tmp_name']));
                $state = is_wp_error($data) ? array('error' => $data->get_error_message()) : array('data' => $data);
            }
        }
        set_transient(self::transient_key($token), $state, 30 * MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg('token', $token, self::url())); exit;
    }
    public static function render() {
        if (!current_user_can('manage_options')) { return; }
        global $wpdb;
        $token = isset($_GET['token']) && is_string($_GET['token']) ? sanitize_key(wp_unslash($_GET['token'])) : '';
        $state = $token ? get_transient(self::transient_key($token)) : false;
        echo '<div class="wrap bbw-admin"><a class="bbw-back" href="' . esc_url(admin_url('admin.php?page=bbwidget-participants')) . '">← Wróć do uczestników</a><h1>Import uczestników z JSON</h1>';
        if ($token && !$state) { echo '<div class="notice notice-warning"><p>Podgląd wygasł. Wczytaj plik ponownie.</p></div>'; }
        if (!empty($state['error'])) { echo '<div class="notice notice-error"><p>' . esc_html($state['error']) . '</p></div>'; }
        if (isset($state['done'])) {
            $done = $state['done'];
            echo '<section class="bbw-card"><h2>Import zakończony</h2><p>Dodano uczestników: <strong>' . esc_html($done['imported']) . '</strong>. Podsumowania IRB: <strong>' . esc_html($done['results']) . '</strong>. Pominięto wcześniej zaimportowanych: <strong>' . esc_html($done['skipped']) . '</strong>.</p><p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=bbwidget-participants&edition=0')) . '">Zobacz uczestników</a></p></section>';
        } elseif (isset($state['data'])) {
            $data = $state['data']; $preview = self::preview($data);
            echo '<p>Do dodania: <strong>' . esc_html($preview['new']) . '</strong>. Już zaimportowani: <strong>' . esc_html($preview['skipped']) . '</strong> — zostaną pominięci, bez zmiany ich danych.</p><p>Unikalne numery startowe zostaną zachowane. Powtórzone numery w tej samej edycji zostaną wyczyszczone u wszystkich uczestników z tym numerem. Import nie tworzy kont ani zamówień. Podsumowania IRB zapisujemy tylko dla uczestników z włączonym IRB.</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('bbw_import');
            echo '<input type="hidden" name="action" value="bbw_import"><input type="hidden" name="token" value="' . esc_attr($token) . '"><input type="hidden" name="confirm" value="1"><div class="bbw-table-scroll"><table class="widefat striped bbw-editions"><thead><tr><th>Edycja</th><th>Aktywność</th><th>Uczestnicy / IRB</th><th>Edycja w bazie</th></tr></thead><tbody>';
            $ready = $preview['new'] > 0;
            foreach ($preview['groups'] as $key => $group) {
                echo '<tr><td>' . esc_html($group['editionNumber']) . '. edycja</td><td>' . esc_html(BBW_Packages::activities()[$group['activityType']]) . '</td><td>' . esc_html($group['count'] . ' / ' . $group['irb']) . '</td><td>';
                if (!$group['bbeditionId']) {
                    $ready = false;
                    echo 'Najpierw dodaj tę edycję.';
                } else { echo 'Gotowa'; }
                echo '</td></tr>';
            }
            echo '</tbody></table></div><p><button class="button button-primary"' . disabled(!$ready, true, false) . '>Importuj</button> <a class="button" href="' . esc_url(self::url()) . '">Wybierz inny plik</a></p></form>';
            if (!$ready && $preview['new']) { echo '<p>Uzupełnij brakujące <a href="' . esc_url(admin_url('admin.php?page=bbwidget-editions')) . '">edycje</a>, a następnie odśwież podgląd.</p>'; }
        } else {
            echo '<section class="bbw-card"><h2>1. Wybierz eksport z API</h2><p>Wczytaj plik JSON z aktywnymi uczestnikami wszystkich edycji. Najpierw zobaczysz podsumowanie edycji i aktywności. Import archiwum nie wymaga pakietów startowych.</p><form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('bbw_import');
            echo '<input type="hidden" name="action" value="bbw_import"><p><label for="import_file">Plik JSON (do 5 MB)</label><br><input required id="import_file" name="import_file" type="file" accept=".json,application/json"></p>';
            submit_button('Pokaż podgląd'); echo '</form></section>';
        }
        echo '</div>';
    }
}
