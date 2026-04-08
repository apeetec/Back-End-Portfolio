<?php

// Disable use XML-RPC
add_filter( 'xmlrpc_enabled', '__return_false' );


// Disable X-Pingback to header
add_filter( 'wp_headers', 'disable_x_pingback' );
function disable_x_pingback( $headers ) {
    unset( $headers['X-Pingback'] );
return $headers;
}

// Disable comments
function disable_comments() {
    $post_types = get_post_types();
    foreach ($post_types as $post_type) {
        if(post_type_supports($post_type,'comments')) {
            remove_post_type_support($post_type,'comments');
            remove_post_type_support($post_type,'trackbacks');
        }
    }
}
add_action('admin_init','disable_comments');

function remove_admin_login_header() {
    remove_action('wp_head', '_admin_bar_bump_cb');
}
add_action('get_header', 'remove_admin_login_header');
add_theme_support( 'post-thumbnails' );
show_admin_bar(false);



add_action( 'send_headers', 'add_header_xframeoptions' );
function add_header_xframeoptions() {
    // Comentário: adiciona header para prevenir framing. Mantém em functions.php
    // para evitar problemas de 'headers already sent' se fosse chamado após output.
    header( 'X-Frame-Options: SAMEORIGIN' );
}

// Carregar automaticamente arquivos de post types personalizados
// Coloque aqui arquivos .php dentro de `functions/post-types/` (ex.: senhas.php)
$ct_post_types_dir = get_template_directory() . '/functions/post-types';
if ( is_dir( $ct_post_types_dir ) ) {
    foreach ( glob( $ct_post_types_dir . '/*.php' ) as $ct_file ) {
        require_once $ct_file;
    }
}
// Carregar automaticamente arquivos de taxonomias personalizados
// Coloque aqui arquivos .php dentro de `functions/taxonomias/` (ex.: taxonomy-senhas.php)
$ct_tax_dir = get_template_directory() . '/functions/taxonomias';
if ( is_dir( $ct_tax_dir ) ) {
    foreach ( glob( $ct_tax_dir . '/*.php' ) as $ct_file ) {
        require_once $ct_file;
    }
}
// CBM2 metaboxes para post type 'senhas'
// Coloque aqui arquivos .php dentro de `functions/cmb2/` (ex.: cmb2-senhas.php)
$ct_cmb2_dir = get_template_directory() . '/functions/cmb2';
if ( is_dir( $ct_cmb2_dir ) ) {
    foreach ( glob( $ct_cmb2_dir . '/*.php' ) as $ct_file ) {
        require_once $ct_file;
    }
}
// Carregar automaticamente arquivos de rotas REST personalizadas
// Coloque aqui arquivos .php dentro de `functions/routes/`
$ct_routes_dir = get_template_directory() . '/functions/routes';
if ( is_dir( $ct_routes_dir ) ) {
    foreach ( glob( $ct_routes_dir . '/*.php' ) as $ct_file ) {
        require_once $ct_file;
    }
}



