<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Prevent redeclaration if functions are already defined by another loader
if ( function_exists( 'client_logger_init_table' ) ) {
    return;
}

/**
 * DB backed logger and IP ban helper for client routes.
 * Table: {$wpdb->prefix}client_logs
 */
if ( ! function_exists( 'client_logger_init_table' ) ) {
function client_logger_init_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'client_logs';
    $charset_collate = $wpdb->get_charset_collate();

    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name ) {
        return $table_name;
    }

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE {$table_name} (
      id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
      created_at datetime NOT NULL,
      user_id bigint(20) unsigned DEFAULT 0,
      user_login varchar(191) DEFAULT '',
      ip varchar(45) DEFAULT '',
      route varchar(191) DEFAULT '',
      action varchar(191) DEFAULT '',
      payload longtext,
      PRIMARY KEY  (id)
    ) $charset_collate;";

    dbDelta( $sql );
    return $table_name;
}
}

if ( ! function_exists( 'client_log_event' ) ) {
function client_log_event( $action, $payload = array(), $route = '' ) {
    global $wpdb;
    $table = client_logger_init_table();

    $user = wp_get_current_user();
    $data = array(
        'created_at' => current_time( 'mysql' ),
        'user_id'    => get_current_user_id(),
        'user_login' => $user->user_login ?: '',
        'ip'         => isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '',
        'route'      => $route,
        'action'     => $action,
        'payload'    => wp_json_encode( $payload ),
    );

    $format = array( '%s', '%d', '%s', '%s', '%s', '%s', '%s' );

    $ok = $wpdb->insert( $table, $data, $format );
    if ( false === $ok ) {
        error_log( 'client_log_event failed: ' . $wpdb->last_error );
        return false;
    }

    return (int) $wpdb->insert_id;
}
}

// Ban list stored as option 'client_banned_ips' => array of ips
if ( ! function_exists( 'client_get_banned_ips' ) ) {
function client_get_banned_ips() {
    $ips = get_option( 'client_banned_ips', array() );
    if ( ! is_array( $ips ) ) {
        $ips = array();
    }
    return $ips;
}
}

if ( ! function_exists( 'client_ban_ip' ) ) {
function client_ban_ip( $ip ) {
    if ( empty( $ip ) ) {
        return false;
    }
    $ips = client_get_banned_ips();
    if ( in_array( $ip, $ips, true ) ) {
        return true;
    }
    $ips[] = $ip;
    update_option( 'client_banned_ips', $ips );
    return true;
}
}

if ( ! function_exists( 'client_unban_ip' ) ) {
function client_unban_ip( $ip ) {
    $ips = client_get_banned_ips();
    $new = array_values( array_diff( $ips, array( $ip ) ) );
    update_option( 'client_banned_ips', $new );
    return true;
}
}

if ( ! function_exists( 'client_is_ip_banned' ) ) {
function client_is_ip_banned( $ip = null ) {
    if ( null === $ip ) {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
    }
    $ips = client_get_banned_ips();
    return in_array( $ip, $ips, true );
}
}

/**
 * Simple risk score based on number of events from IP in the last X hours.
 * returns int 0..3 (0 none, 1 low, 2 medium, 3 high)
 */
if ( ! function_exists( 'client_get_ip_risk_score' ) ) {
function client_get_ip_risk_score( $ip = null, $hours = 24 ) {
    global $wpdb;
    if ( null === $ip ) {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
    }
    if ( empty( $ip ) ) {
        return 0;
    }
    $table = client_logger_init_table();
    $count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE ip = %s AND created_at >= (NOW() - INTERVAL %d HOUR)", $ip, $hours ) );
    if ( $count > 20 ) return 3;
    if ( $count > 5 ) return 2;
    if ( $count > 0 ) return 1;
    return 0;
}
}
