<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST routes for Formação (options key: 'formacao')
 *
 * GET  -> public (returns sanitized list of formacoes)
 * POST -> protected (requires manage_options via Application Passwords or X-WP-Nonce)
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'client/v1', '/formacao', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'client_get_formacao',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'client/v1', '/formacao', array(
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => 'client_update_formacao',
        'permission_callback' => 'client_permission_manage_options_formacao',
        'args'                => array(
            'formacoes' => array( 'required' => false ),
        ),
    ) );
} );


/**
 * GET callback — retorna lista sanitizada de formações
 */
function client_get_formacao( WP_REST_Request $request ) {
    $options = get_option( 'formacao', array() );

    if ( empty( $options['formacoes'] ) || ! is_array( $options['formacoes'] ) ) {
        return rest_ensure_response( array() );
    }

    $out = array();
    foreach ( $options['formacoes'] as $item ) {
        $out[] = array(
            'curso'           => isset( $item['curso'] )           ? sanitize_text_field( $item['curso'] )           : '',
            'instituicao'     => isset( $item['instituicao'] )     ? sanitize_text_field( $item['instituicao'] )     : '',
            'ano'             => isset( $item['ano'] )             ? sanitize_text_field( $item['ano'] )             : '',
            'descricao_curso' => isset( $item['descricao_curso'] ) ? wp_kses_post( $item['descricao_curso'] )        : '',
        );
    }

    return rest_ensure_response( $out );
}


/**
 * POST/PUT callback — substitui toda a lista de formações
 */
function client_update_formacao( WP_REST_Request $request ) {
    $params = $request->get_param( 'formacoes' );

    if ( null === $params ) {
        return new WP_Error( 'missing_param', 'Campo formacoes ausente', array( 'status' => 400 ) );
    }

    if ( ! is_array( $params ) ) {
        return new WP_Error( 'invalid_param', 'Campo formacoes deve ser um array', array( 'status' => 400 ) );
    }

    $sanitized = array();
    foreach ( $params as $entry ) {
        if ( ! is_array( $entry ) ) {
            continue;
        }
        $sanitized[] = array(
            'curso'           => isset( $entry['curso'] )           ? sanitize_text_field( $entry['curso'] )           : '',
            'instituicao'     => isset( $entry['instituicao'] )     ? sanitize_text_field( $entry['instituicao'] )     : '',
            'ano'             => isset( $entry['ano'] )             ? sanitize_text_field( $entry['ano'] )             : '',
            'descricao_curso' => isset( $entry['descricao_curso'] ) ? wp_kses_post( $entry['descricao_curso'] )        : '',
        );
    }

    $options             = get_option( 'formacao', array() );
    $options['formacoes'] = $sanitized;
    update_option( 'formacao', $options );

    // Auditoria
    if ( file_exists( __DIR__ . '/../admin/logs/logger.php' ) ) {
        require_once __DIR__ . '/../admin/logs/logger.php';
        client_log_event( 'formacao_update', $sanitized, '/client/v1/formacao' );
    } else {
        error_log( '[client] formacao updated: ' . wp_json_encode( $sanitized ) );
    }

    return rest_ensure_response( array( 'success' => true, 'data' => $sanitized ) );
}


/**
 * Permission callback — aceita Application Passwords (Basic) ou X-WP-Nonce
 */
function client_permission_manage_options_formacao( WP_REST_Request $request ) {
    $auth_header   = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    $php_auth_user = isset( $_SERVER['PHP_AUTH_USER'] )      ? $_SERVER['PHP_AUTH_USER']      : '';
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


/**
 * CORS — restringe Access-Control-Allow-Origin às origens em CLIENT_ALLOWED_ORIGINS
 */
add_filter( 'rest_post_dispatch', function ( $result, $server, $request ) {
    if ( strpos( $request->get_route(), '/client/v1/formacao' ) !== 0 ) {
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
