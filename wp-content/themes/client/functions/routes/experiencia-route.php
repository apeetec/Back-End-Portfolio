<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST routes for Experiência e Carreira (options key: 'experiencia_carreira')
 *
 * GET  -> public (returns sanitized public fields)
 * POST -> protected (requires manage_options via Application Passwords or X-WP-Nonce)
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'client/v1', '/experiencia', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'client_get_experiencia',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'client/v1', '/experiencia', array(
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => 'client_update_experiencia',
        'permission_callback' => 'client_permission_manage_options_experiencia',
        'args'                => array(
            'experiencias' => array( 'required' => false ),
        ),
    ) );
} );


/**
 * GET callback - return sanitized experiencias
 */
function client_get_experiencia( WP_REST_Request $request ) {
    $options = get_option( 'experiencia_carreira', array() );

    // Ensure we only expose public-safe fields and sanitize output
    if ( empty( $options['experiencias'] ) || ! is_array( $options['experiencias'] ) ) {
        return rest_ensure_response( array() );
    }

    $out = array();
    foreach ( $options['experiencias'] as $item ) {
        $out[] = array(
            'cargo' => isset( $item['cargo'] ) ? wp_kses_post( $item['cargo'] ) : '',
            'empresa' => isset( $item['empresa'] ) ? sanitize_text_field( $item['empresa'] ) : '',
            'ano' => isset( $item['ano'] ) ? sanitize_text_field( $item['ano'] ) : '',
            'descricao_cargo' => isset( $item['descricao_cargo'] ) ? wp_kses_post( $item['descricao_cargo'] ) : '',
        );
    }

    return rest_ensure_response( $out );
}


/**
 * POST/PUT callback - update experiencias (protected)
 */
function client_update_experiencia( WP_REST_Request $request ) {
    $params = $request->get_param( 'experiencias' );

    if ( null === $params ) {
        return new WP_Error( 'missing_param', 'Campo experiencias ausente', array( 'status' => 400 ) );
    }

    if ( ! is_array( $params ) ) {
        return new WP_Error( 'invalid_param', 'Campo experiencias deve ser um array', array( 'status' => 400 ) );
    }

    $sanitized = array();
    foreach ( $params as $entry ) {
        if ( ! is_array( $entry ) ) {
            continue;
        }
        $sanitized[] = array(
            'cargo' => isset( $entry['cargo'] ) ? wp_kses_post( $entry['cargo'] ) : '',
            'empresa' => isset( $entry['empresa'] ) ? sanitize_text_field( $entry['empresa'] ) : '',
            'ano' => isset( $entry['ano'] ) ? sanitize_text_field( $entry['ano'] ) : '',
            'descricao_cargo' => isset( $entry['descricao_cargo'] ) ? wp_kses_post( $entry['descricao_cargo'] ) : '',
        );
    }

    // Persist
    $options = get_option( 'experiencia_carreira', array() );
    $options['experiencias'] = $sanitized;
    update_option( 'experiencia_carreira', $options );

    // Log update to DB table (client_logs)
    if ( file_exists( __DIR__ . '/../admin/logs/logger.php' ) ) {
        require_once __DIR__ . '/../admin/logs/logger.php';
        client_log_event( 'experiencia_update', $sanitized, '/client/v1/experiencia' );
    } else {
        error_log( '[client] experiencia updated: ' . wp_json_encode( $sanitized ) );
    }

    return rest_ensure_response( array( 'success' => true, 'data' => $sanitized ) );
}


/**
 * Permission callback for experiencia route
 */
function client_permission_manage_options_experiencia( WP_REST_Request $request ) {
    // Accept Application Passwords (Basic) or verify X-WP-Nonce
    $auth_header = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    $php_auth_user = isset( $_SERVER['PHP_AUTH_USER'] ) ? $_SERVER['PHP_AUTH_USER'] : '';
    if ( ! empty( $php_auth_user ) || ( ! empty( $auth_header ) && stripos( $auth_header, 'basic' ) === 0 ) ) {
        return current_user_can( 'manage_options' );
    }

    if ( ! is_user_logged_in() ) {
        return false;
    }

    $nonce = $request->get_header( 'x-wp-nonce' );
    if ( empty( $nonce ) ) {
        $nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) : '';
    }

    if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
        return false;
    }

    return current_user_can( 'manage_options' );
}

// CORS for experiencia route (reuse CLIENT_ALLOWED_ORIGINS behavior)
add_filter( 'rest_post_dispatch', function ( $result, $server, $request ) {
    $route = $request->get_route();
    if ( strpos( $route, '/client/v1/experiencia' ) !== 0 ) {
        return $result;
    }
    $allowed = defined( 'CLIENT_ALLOWED_ORIGINS' ) && is_array( CLIENT_ALLOWED_ORIGINS ) ? CLIENT_ALLOWED_ORIGINS : array( get_site_url() );
    $origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? wp_unslash( $_SERVER['HTTP_ORIGIN'] ) : '';
    if ( in_array( $origin, $allowed, true ) ) {
        $response = rest_ensure_response( $result );
        $response->header( 'Access-Control-Allow-Origin', $origin );
        $response->header( 'Access-Control-Allow-Credentials', 'true' );
        return $response;
    }
    return $result;
}, 10, 3 );

?>
