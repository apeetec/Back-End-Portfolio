<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Simple DB-backed logger for client routes.
 * Table: {$wpdb->prefix}client_logs
 */
// If a newer logger implementation exists in `admin/logs/logger.php`, load it and stop
// to avoid redeclaring functions. This keeps backward compatibility with older includes.
if ( file_exists( __DIR__ . '/admin/logs/logger.php' ) ) {
    require_once __DIR__ . '/admin/logs/logger.php';
    return;
}

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

/**
 * Insert a log entry. Returns insert id or false.
 */
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
        // fallback to error_log if DB insert fails
        error_log( 'client_log_event failed: ' . $wpdb->last_error );
        return false;
    }

    return (int) $wpdb->insert_id;
}
}
