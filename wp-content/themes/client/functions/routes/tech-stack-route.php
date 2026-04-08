<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST routes for Tech Stack (options key: 'tech_stack')
 * GET  -> public (returns sanitized list)
 * POST -> protected (requires manage_options)
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'client/v1', '/tech-stack', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'client_get_tech_stack',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'client/v1', '/tech-stack', array(
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => 'client_update_tech_stack',
        'permission_callback' => 'client_permission_manage_options_tech_stack',
        'args'                => array(
            'tecnologias' => array( 'required' => false ),
        ),
    ) );
} );


function client_get_tech_stack( WP_REST_Request $request ) {
    $options = get_option( 'tech_stack', array() );
    $out = array();
    if ( ! empty( $options['tecnologias'] ) ) {
        // ensure array and sanitize
        $items = is_array( $options['tecnologias'] ) ? $options['tecnologias'] : array_map( 'trim', explode( ',', (string) $options['tecnologias'] ) );
        foreach ( $items as $it ) {
            $out[] = sanitize_text_field( $it );
        }
    }
    return rest_ensure_response( $out );
}


function client_update_tech_stack( WP_REST_Request $request ) {
    $raw = $request->get_param( 'tecnologias' );
    if ( null === $raw ) {
        return new WP_Error( 'missing_param', 'Campo tecnologias ausente', array( 'status' => 400 ) );
    }

    if ( is_array( $raw ) ) {
        $items = array_map( 'sanitize_text_field', $raw );
    } else {
        $items = array_map( 'sanitize_text_field', array_map( 'trim', explode( ',', (string) $raw ) ) );
    }

    $options = get_option( 'tech_stack', array() );
    $options['tecnologias'] = $items;
    update_option( 'tech_stack', $options );

    // Log update to DB table (client_logs)
    if ( file_exists( __DIR__ . '/../admin/logs/logger.php' ) ) {
        require_once __DIR__ . '/../admin/logs/logger.php';
        client_log_event( 'tech_stack_update', $items, '/client/v1/tech-stack' );
    } else {
        error_log( '[client] tech_stack updated: ' . wp_json_encode( $items ) );
    }

    return rest_ensure_response( array( 'success' => true, 'data' => $items ) );
}


function client_permission_manage_options_tech_stack( WP_REST_Request $request ) {
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

// CORS handling similar to other routes
add_filter( 'rest_post_dispatch', function ( $result, $server, $request ) {
    $route = $request->get_route();
    if ( strpos( $route, '/client/v1/tech-stack' ) !== 0 ) {
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
