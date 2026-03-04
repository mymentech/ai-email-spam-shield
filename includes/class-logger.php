<?php
/**
 * Logger — handles database table creation, log insertion, and pruning.
 *
 * @package AI_Email_Spam_Shield
 */

namespace AI_Email_Spam_Shield;

defined( 'ABSPATH' ) || exit;

class Logger {

    const TABLE_NAME = 'ai_spam_logs';

    /**
     * Runs on plugin activation: creates the DB table and schedules cron.
     */
    public static function activate(): void {
        self::create_table();
        self::schedule_pruning();
    }

    /**
     * Creates wp_ai_spam_logs using dbDelta.
     */
    public static function create_table(): void {
        global $wpdb;

        $table   = $wpdb->prefix . self::TABLE_NAME;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            email_subject VARCHAR(255) NOT NULL DEFAULT '',
            email_sender VARCHAR(255) NOT NULL DEFAULT '',
            ai_score DECIMAL(5,4) DEFAULT NULL,
            rule_score DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
            final_score DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
            blocked TINYINT(1) NOT NULL DEFAULT 0,
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_blocked (blocked),
            KEY idx_created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'aiess_db_version', AIESS_VERSION );
    }

    /**
     * Schedule daily log pruning via WP-Cron.
     */
    public static function schedule_pruning(): void {
        if ( ! wp_next_scheduled( 'aiess_prune_logs' ) ) {
            wp_schedule_event( time(), 'daily', 'aiess_prune_logs' );
        }
    }

    /**
     * Prune log entries older than 30 days.
     */
    public static function prune_old_logs(): void {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < %s",
                gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
            )
        );
    }

    /**
     * Prepare sanitized data array for DB insert.
     * Static and pure — no DB access, fully testable.
     *
     * @param string      $subject
     * @param string      $sender
     * @param float|null  $ai_score
     * @param float       $rule_score
     * @param float       $final_score
     * @param bool        $blocked
     * @param string      $ip
     * @return array
     */
    public static function prepare_log_data(
        string $subject,
        string $sender,
        ?float $ai_score,
        float $rule_score,
        float $final_score,
        bool $blocked,
        string $ip
    ): array {
        return array(
            'email_subject' => sanitize_text_field( $subject ),
            'email_sender'  => sanitize_email( $sender ),
            'ai_score'      => ( null === $ai_score ) ? null : round( $ai_score, 4 ),
            'rule_score'    => round( $rule_score, 4 ),
            'final_score'   => round( $final_score, 4 ),
            'blocked'       => $blocked ? 1 : 0,
            'ip_address'    => sanitize_text_field( $ip ),
        );
    }

    /**
     * Insert a log record into the database.
     *
     * @param array $data  Prepared data from prepare_log_data().
     */
    public static function insert( array $data ): void {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        if ( null === $data['ai_score'] ) {
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$table}
                     (email_subject, email_sender, ai_score, rule_score, final_score, blocked, ip_address)
                     VALUES (%s, %s, NULL, %f, %f, %d, %s)",
                    $data['email_subject'],
                    $data['email_sender'],
                    $data['rule_score'],
                    $data['final_score'],
                    $data['blocked'],
                    $data['ip_address']
                )
            );
        } else {
            $wpdb->insert(
                $table,
                $data,
                array( '%s', '%s', '%f', '%f', '%f', '%d', '%s' )
            );
        }
    }

    /**
     * Get paginated log entries with optional blocked/allowed filter.
     *
     * @param int         $page      Current page (1-based).
     * @param int         $per_page  Rows per page.
     * @param string|null $filter    'blocked', 'allowed', or null for all.
     * @return array{ rows: array, total: int }
     */
    public static function get_logs( int $page = 1, int $per_page = 20, ?string $filter = null ): array {
        global $wpdb;
        $table  = $wpdb->prefix . self::TABLE_NAME;
        $offset = ( $page - 1 ) * $per_page;

        $where = '';
        if ( 'blocked' === $filter ) {
            $where = 'WHERE blocked = 1';
        } elseif ( 'allowed' === $filter ) {
            $where = 'WHERE blocked = 0';
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );
        // phpcs:enable

        return array(
            'rows'  => $rows ?: array(),
            'total' => $total,
        );
    }

    /**
     * Delete all log entries.
     */
    public static function clear_all(): void {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        $wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Get summary stats for the dashboard widget.
     *
     * @return array{ total_scanned: int, blocked_today: int, blocked_week: int }
     */
    public static function get_stats(): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        $today = gmdate( 'Y-m-d' );

        return array(
            'total_scanned' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ), // phpcs:ignore
            'blocked_today' => (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE blocked = 1 AND DATE(created_at) = %s",
                    $today
                )
            ),
            'blocked_week'  => (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE blocked = 1 AND created_at >= %s",
                    gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
                )
            ),
        );
    }
}
