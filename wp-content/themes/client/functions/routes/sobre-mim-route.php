<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST routes for the "Sobre mim" options (get + update).
 *
 * Endpoint namespace: /wp-json/client/v1/sobre-mim
 *
 * GET  -> public, returns current options array stored by CMB2 (option_key: 'sobre_mim')
 * POST -> protected (requires `manage_options`) to update the fields below.
 *
 * Allowed fields (JSON body / form data):
 *  - descricao
 *  - github_link
 *  - linkedin_link
 *  - twitter_link
 *  - email
 *
 * Authentication: any method that results in a WP user with `manage_options` capability.
 * Recommended for scripts: use Application Passwords (`username:app-password`) or cookie-based auth.
 */

add_action( 'rest_api_init', function () {
    register_rest_route( 'client/v1', '/sobre-mim', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'client_get_sobre_mim',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'client/v1', '/sobre-mim', array(
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => 'client_update_sobre_mim',
        'permission_callback' => 'client_permission_manage_options_sobre_mim',
        'args'                => array(
            'descricao'    => array( 'required' => false, 'sanitize_callback' => 'wp_kses_post' ),
            'github_link'  => array( 'required' => false, 'sanitize_callback' => 'esc_url_raw' ),
            'linkedin_link'=> array( 'required' => false, 'sanitize_callback' => 'esc_url_raw' ),
            'twitter_link' => array( 'required' => false, 'sanitize_callback' => 'esc_url_raw' ),
            'email'        => array( 'required' => false, 'sanitize_callback' => 'sanitize_email' ),
        ),
    ) );
} );

/**
 * CORS: limit Access-Control-Allow-Origin to allowed origins for these routes.
 *
 * To allow other origins in development, define `CLIENT_ALLOWED_ORIGINS` as an array
 * in wp-config.php, e.g.:
 * define( 'CLIENT_ALLOWED_ORIGINS', [ 'http://localhost:3000' ] );
 */
add_filter( 'rest_post_dispatch', function ( $result, $server, $request ) {
    $route = $request->get_route();
    // only apply to our routes
    if ( strpos( $route, '/client/v1/sobre-mim' ) !== 0 ) {
        return $result;
    }

    $allowed = defined( 'CLIENT_ALLOWED_ORIGINS' ) && is_array( CLIENT_ALLOWED_ORIGINS )
        ? CLIENT_ALLOWED_ORIGINS
        : array( get_site_url() );

    $origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? wp_unslash( $_SERVER['HTTP_ORIGIN'] ) : '';
    if ( in_array( $origin, $allowed, true ) ) {
        // Ensure we return a WP_REST_Response so we can add headers
        $response = rest_ensure_response( $result );
        $response->header( 'Access-Control-Allow-Origin', $origin );
        $response->header( 'Access-Control-Allow-Credentials', 'true' );
        return $response;
    }

    return $result;
}, 10, 3 );

/**
 * GET callback
 */
function client_get_sobre_mim( WP_REST_Request $request ) {
    $options = get_option( 'sobre_mim', array() );
    return rest_ensure_response( $options );
}

/**
 * POST/PUT callback to update option values
 */
function client_update_sobre_mim( WP_REST_Request $request ) {
    // Explicitly fetch and sanitize each field rather than trusting args
    $descricao     = $request->get_param( 'descricao' );
    $github_link   = $request->get_param( 'github_link' );
    $linkedin_link = $request->get_param( 'linkedin_link' );
    $twitter_link  = $request->get_param( 'twitter_link' );
    $email         = $request->get_param( 'email' );

    $options = get_option( 'sobre_mim', array() );

    // Sanitize description (allow safe HTML)
    if ( null !== $descricao ) {
        $options['descricao'] = wp_kses_post( $descricao );
    }

    // Validate and sanitize URLs
    $urls = array( 'github_link' => $github_link, 'linkedin_link' => $linkedin_link, 'twitter_link' => $twitter_link );
    foreach ( $urls as $k => $v ) {
        if ( null === $v ) {
            continue;
        }
        $san = esc_url_raw( $v );
        if ( ! empty( $san ) && ! filter_var( $san, FILTER_VALIDATE_URL ) ) {
            return new WP_Error( 'invalid_url', sprintf( 'Campo %s não é uma URL válida', $k ), array( 'status' => 400 ) );
        }
        $options[ $k ] = $san;
    }

    // Sanitize and validate email
    if ( null !== $email ) {
        $san_email = sanitize_email( $email );
        if ( ! empty( $san_email ) && ! is_email( $san_email ) ) {
            return new WP_Error( 'invalid_email', 'Email inválido', array( 'status' => 400 ) );
        }
        $options['email'] = $san_email;
    }

    // Update option
    update_option( 'sobre_mim', $options );

    // Log update to DB table (client_logs)
    if ( file_exists( __DIR__ . '/../admin/logs/logger.php' ) ) {
        require_once __DIR__ . '/../admin/logs/logger.php';
        client_log_event( 'sobre_mim_update', $options, '/client/v1/sobre-mim' );
    } else {
        error_log( '[client] sobre_mim updated: ' . wp_json_encode( $options ) );
    }

    return rest_ensure_response( array( 'success' => true, 'data' => $options ) );
}

/**
 * Permission callback that accepts either Application Passwords (Basic auth)
 * or verifies X-WP-Nonce for cookie-based browser requests.
 */
function client_permission_manage_options_sobre_mim( WP_REST_Request $request ) {
    // If authenticated via Basic auth (Application Password), allow if capability present
    $auth_header = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    $php_auth_user = isset( $_SERVER['PHP_AUTH_USER'] ) ? $_SERVER['PHP_AUTH_USER'] : '';
    if ( ! empty( $php_auth_user ) || ( ! empty( $auth_header ) && stripos( $auth_header, 'basic' ) === 0 ) ) {
        return current_user_can( 'manage_options' );
    }

    // Otherwise require logged-in user and valid WP REST nonce
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
