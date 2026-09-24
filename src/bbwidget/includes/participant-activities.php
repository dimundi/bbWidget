<?php
defined('ABSPATH') || exit;

class BBW_Participant_Activities {
    public static function table() { global $wpdb; return $wpdb->prefix . 'bb_participant_activities'; }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            bbParticipantId bigint(20) unsigned NOT NULL,
            activityType enum('walk','run','bike','iron_teacher') NOT NULL,
            PRIMARY KEY  (bbParticipantId,activityType)
        ) ENGINE=InnoDB $charset;");
        if ($wpdb->last_error) { return; }
        foreach (array(BBW_Participants::table(), BBW_Import::results_table()) as $legacy) {
            if (!self::migrate_column($legacy)) { return; }
        }
        update_option('bbw_participant_activities_schema_version', '1', false);
    }

    public static function migrate_column($legacy) {
        global $wpdb;
        // Table names come from the plugin, never from request data.
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $legacy)) { return false; }
        if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($legacy)))) { return true; }
        $column = $wpdb->get_row("SHOW COLUMNS FROM `$legacy` LIKE 'activityType'");
        if ($wpdb->last_error) { return false; }
        if (!$column) { return true; }
        $table = self::table();
        $copied = $wpdb->query("INSERT INTO $table (bbParticipantId, activityType)
            SELECT bbParticipantId, activityType FROM `$legacy` WHERE activityType IS NOT NULL
            ON DUPLICATE KEY UPDATE activityType=VALUES(activityType)");
        if ($copied === false) { return false; }
        $missing = $wpdb->get_var("SELECT COUNT(*) FROM `$legacy` l LEFT JOIN $table a
            ON a.bbParticipantId=l.bbParticipantId AND BINARY a.activityType=BINARY l.activityType
            WHERE l.activityType IS NOT NULL AND a.bbParticipantId IS NULL");
        if ($wpdb->last_error || (int) $missing !== 0) { return false; }
        return $wpdb->query("ALTER TABLE `$legacy` DROP COLUMN activityType") !== false;
    }
}
