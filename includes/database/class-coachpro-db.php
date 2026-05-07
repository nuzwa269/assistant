<?php
/**
 * Class CoachPro_DB — helper query methods.
 *
 * @package CoachPro_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_DB {

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
        $order_clause = $order  ? "ORDER BY {$order}" : '';
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
