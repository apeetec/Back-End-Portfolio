<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST routes for Projetos (post type 'projetos')
 * GET  -> public (returns limited sanitized fields)
 * POST -> protected (creates/updates projetos) - requires manage_options
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'client/v1', '/projetos', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'client_get_projetos',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'client/v1', '/projetos', array(
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => 'client_update_projetos',
        'permission_callback' => 'client_permission_manage_options_projetos',
        'args'                => array(),
    ) );
} );


function client_get_projetos( WP_REST_Request $request ) {
    $args = array(
        'post_type' => 'projetos',
        'post_status' => 'publish',
        'numberposts' => 50,
    );
    $posts = get_posts( $args );
    $out = array();
    foreach ( $posts as $p ) {
        $link = get_post_meta( $p->ID, 'projeto_link', true );
        $out[] = array(
            'id' => $p->ID,
            'title' => get_the_title( $p ),
            'excerpt' => wp_kses_post( wp_trim_words( $p->post_content, 40 ) ),
            'thumbnail' => get_the_post_thumbnail_url( $p->ID, 'full' ),
            'link' => $link ? esc_url_raw( $link ) : '',
        );
    }
    return rest_ensure_response( $out );
}


/**
 * Create or update a projeto
 * Expected payload (JSON): { id?: number, title: string, content?: string }
 */
function client_update_projetos( WP_REST_Request $request ) {
    $params = $request->get_params();

    $title = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : '';
    $content = isset( $params['content'] ) ? wp_kses_post( $params['content'] ) : '';

    if ( empty( $title ) ) {
        return new WP_Error( 'missing_title', 'Título é obrigatório', array( 'status' => 400 ) );
    }

    if ( isset( $params['id'] ) && absint( $params['id'] ) ) {
        // update
        $post_id = absint( $params['id'] );
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'projetos' ) {
            return new WP_Error( 'invalid_id', 'Projeto não encontrado', array( 'status' => 404 ) );
        }
        $update = wp_update_post( array(
            'ID' => $post_id,
            'post_title' => $title,
            'post_content' => $content,
        ), true );
        if ( is_wp_error( $update ) ) {
            return $update;
        }
        $res = get_post( $post_id );
    } else {
        // create
        $post_id = wp_insert_post( array(
            'post_type' => 'projetos',
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
        ), true );
        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }
        $res = get_post( $post_id );
    }

    // Log update to DB table (client_logs)
    if ( file_exists( __DIR__ . '/../admin/logs/logger.php' ) ) {
        require_once __DIR__ . '/../admin/logs/logger.php';
        client_log_event( 'projetos_upsert', array( 'id' => $res->ID, 'title' => $res->post_title ), '/client/v1/projetos' );
    } else {
        error_log( sprintf( '[client] projetos upsert id=%d title=%s', $res->ID, $res->post_title ) );
    }

    return rest_ensure_response( array( 'success' => true, 'id' => $res->ID ) );
}


function client_permission_manage_options_projetos( WP_REST_Request $request ) {
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

// CORS handling for projetos route
add_filter( 'rest_post_dispatch', function ( $result, $server, $request ) {
    $route = $request->get_route();
    if ( strpos( $route, '/client/v1/projetos' ) !== 0 ) {
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
