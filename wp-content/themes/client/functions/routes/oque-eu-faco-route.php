<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'client/v1', '/oque-eu-faco', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'client_get_o_que_eu_faco',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'client/v1', '/oque-eu-faco', array(
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => 'client_update_o_que_eu_faco',
        'permission_callback' => 'client_permission_manage_options_oque_eu_faco',
        'args'                => array(
            'descricao'         => array( 'required' => false, 'sanitize_callback' => 'wp_kses_post' ),
            'tags_tecnologias' => array( 
                'required' => false, 
                'sanitize_callback' => function ( $value ) {
                    $tags = explode( ',', $value );
                    return array_map( 'sanitize_text_field', $tags );
                }
            ),
        ),
    ) );
} );


/**
 * GET callback
 */
function client_get_o_que_eu_faco( WP_REST_Request $request ) {
    $options = get_option( 'o_que_eu_faco', array() );
    return rest_ensure_response( $options );
}


/**
 * POST/PUT callback to update option values
 */
function client_update_o_que_eu_faco( WP_REST_Request $request ) {
    // Explicit sanitization/validation
    $descricao = $request->get_param( 'descricao' );
    $tags_raw  = $request->get_param( 'tags_tecnologias' );

    $options = get_option( 'o_que_eu_faco', array() );

    if ( null !== $descricao ) {
        $options['descricao'] = wp_kses_post( $descricao );
    }

    if ( null !== $tags_raw ) {
        if ( is_array( $tags_raw ) ) {
            $tags = array_map( 'sanitize_text_field', $tags_raw );
        } else {
            $tags = array_map( 'sanitize_text_field', array_map( 'trim', explode( ',', (string) $tags_raw ) ) );
        }
        $options['tags_tecnologias'] = $tags;
    }

    update_option( 'o_que_eu_faco', $options );

    // Log update
    // Log update to DB table (client_logs)
    if ( file_exists( __DIR__ . '/../admin/logs/logger.php' ) ) {
        require_once __DIR__ . '/../admin/logs/logger.php';
        client_log_event( 'o_que_eu_faco_update', $options, '/client/v1/o-que-eu-faco' );
    } else {
        error_log( '[client] o_que_eu_faco updated: ' . wp_json_encode( $options ) );
    }

    return rest_ensure_response( array( 'success' => true, 'data' => $options ) );
}


/**
 * CORS: limit Access-Control-Allow-Origin to allowed origins for this route.
 * See sobre-mim-route.php for instructions about `CLIENT_ALLOWED_ORIGINS`.
 */
add_filter( 'rest_post_dispatch', function ( $result, $server, $request ) {
    $route = $request->get_route();
    if ( strpos( $route, '/client/v1/o-que-eu-faco' ) !== 0 ) {
        return $result;
    }

    $allowed = defined( 'CLIENT_ALLOWED_ORIGINS' ) && is_array( CLIENT_ALLOWED_ORIGINS )
        ? CLIENT_ALLOWED_ORIGINS
        : array( get_site_url() );

    $origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? wp_unslash( $_SERVER['HTTP_ORIGIN'] ) : '';
    if ( in_array( $origin, $allowed, true ) ) {
        $response = rest_ensure_response( $result );
        $response->header( 'Access-Control-Allow-Origin', $origin );
        $response->header( 'Access-Control-Allow-Credentials', 'true' );
        return $response;
    }

    return $result;
}, 10, 3 );

/**
 * Permission callback that accepts either Application Passwords (Basic auth)
 * or verifies X-WP-Nonce for cookie-based browser requests.
 */
function client_permission_manage_options_oque_eu_faco( WP_REST_Request $request ) {
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

?>