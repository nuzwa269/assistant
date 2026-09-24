<?php
/**
 * Class CoachPro_DB — helper query methods.
 *
 * @package CoachPro_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_DB {

    private static $transaction_depth = 0;
    private static $transaction_error = null;
    private static $cache_users = array();
    private static $engines_checked = false;

    /** Run related writes together. Lock the WP user row even before meta exists. */
    public static function transaction( callable $callback, int $user_id = 0 ) {
        global $wpdb;
        $outer = 0 === self::$transaction_depth;
        if ( $outer ) {
            if (!self::$engines_checked) {
                foreach (array($wpdb->users, $wpdb->usermeta) as $table) {
                    $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table));
                    if ('InnoDB' !== $engine) return new WP_Error('storage_engine', 'CoachPro requires InnoDB for WordPress users and usermeta tables.', array('status'=>503));
                }
                self::$engines_checked = true;
            }
            self::$transaction_error = null;
            self::$cache_users = array();
            if ( false === $wpdb->query( 'START TRANSACTION' ) ) return self::write_error();
        }
        self::$transaction_depth++;
        try {
            if ( $user_id ) {
                self::$cache_users[$user_id] = true;
                $exists = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE ID = %d FOR UPDATE", $user_id ) );
                if ( ! $exists ) throw new RuntimeException( 'User not found.' );
                wp_cache_delete( $user_id, 'user_meta' );
            }
            $result = $callback();
            if ( is_wp_error( $result ) ) self::$transaction_error = $result;
        } catch ( Throwable $error ) {
            $result = self::write_error();
            self::$transaction_error = $result;
            error_log( 'CoachPro transaction failed: ' . $error->getMessage() );
        }
        self::$transaction_depth--;
        if ( $outer ) {
            if ( self::$transaction_error ) {
                $wpdb->query( 'ROLLBACK' );
                $result = self::$transaction_error;
            } elseif ( false === $wpdb->query( 'COMMIT' ) ) {
                $wpdb->query( 'ROLLBACK' );
                $result = self::write_error();
            }
            foreach ( self::$cache_users as $id => $unused ) wp_cache_delete( $id, 'user_meta' );
        }
        return $result;
    }

    public static function write_error() {
        return new WP_Error( 'database_error', 'Unable to save changes. Please try again.', array( 'status' => 500 ) );
    }

    public static function set_meta( int $user_id, string $key, $value ) {
        if ( (string) get_user_meta( $user_id, $key, true ) === (string) $value ) return true;
        return false === update_user_meta( $user_id, $key, $value ) ? self::write_error() : true;
    }

    public static function pagination( WP_REST_Request $request ) : array {
        $limit = max( 1, min( 200, (int) ( $request->get_param( 'per_page' ) ?: 100 ) ) );
        $page = max( 1, (int) $request->get_param( 'page' ) );
        return array( $limit, ( $page - 1 ) * $limit );
    }

    /** Fail writes explicitly so the enclosing REST transaction can roll back. */
    public static function __callStatic( $method, $args ) {
        global $wpdb;
        if ( ! in_array( $method, array('insert', 'update', 'delete', 'replace', 'query'), true ) ) throw new BadMethodCallException('Unsupported database operation.');
        $result = call_user_func_array( array($wpdb, $method), $args );
        if ( false === $result ) throw new RuntimeException('Database write failed.');
        return $result;
    }

    /**
     * Return the prefixed table name.
     */
    public static function table( string $name ) : string {
        global $wpdb;
        return $wpdb->prefix . 'coachpro_' . $name;
    }

    /**
     * Generic paginated select.
     *
     * @param string $table  Unprefixed table name suffix (e.g. 'projects').
     * @param array  $where  Column => value pairs (all AND-ed, = comparison).
     * @param string $order  e.g. 'created_at DESC'
     * @param int    $limit
     * @param int    $offset
     * @return array
     */
    public static function get_rows( string $table, array $where = array(), string $order = 'created_at DESC', int $limit = 100, int $offset = 0 ) : array {
        global $wpdb;
        $t      = self::table( $table );
        $wheres = array();
        $values = array();

        foreach ( $where as $col => $val ) {
            if ( is_null( $val ) ) {
                $wheres[] = "`{$col}` IS NULL";
            } else {
                $wheres[] = "`{$col}` = %s";
                $values[]  = $val;
            }
        }

        $where_clause = $wheres ? 'WHERE ' . implode( ' AND ', $wheres ) : '';

        // Validate $order against a whitelist pattern to prevent SQL injection.
        // Only allow: column_name ASC|DESC (e.g. "created_at DESC", "user_id ASC")
        $safe_order = $order && preg_match( '/^[a-zA-Z0-9_]+\s+(ASC|DESC)$/i', trim( $order ) ) ? trim( $order ) : '';
        $direction = false !== stripos($safe_order, "DESC") ? "DESC" : "ASC";
        $order_clause = $safe_order ? "ORDER BY {$safe_order}, id {$direction}" : "ORDER BY id ASC";

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT * FROM `{$t}` {$where_clause} {$order_clause} LIMIT %d OFFSET %d";
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $values[] = $limit;
        $values[] = $offset;

        return $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Get a single row by primary key (id column).
     */
    public static function get_row( string $table, string $id ) : ?array {
        global $wpdb;
        $t = self::table( $table );
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE id = %s LIMIT 1", $id ), ARRAY_A );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $row ?: null;
    }

    /**
     * Count rows matching $where.
     */
    public static function count( string $table, array $where = array() ) : int {
        global $wpdb;
        $t      = self::table( $table );
        $wheres = array();
        $values = array();

        foreach ( $where as $col => $val ) {
            if ( is_null( $val ) ) {
                $wheres[] = "`{$col}` IS NULL";
            } else {
                $wheres[] = "`{$col}` = %s";
                $values[]  = $val;
            }
        }

        $where_clause = $wheres ? 'WHERE ' . implode( ' AND ', $wheres ) : '';
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT COUNT(*) FROM `{$t}` {$where_clause}";
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $values ) {
            return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
        return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }
}
