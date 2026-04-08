<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registra a Options Page "Sobre mim" no CMB2
 */
add_action( 'cmb2_admin_init', 'cmb2_sobre_mim_options_page' );
function cmb2_sobre_mim_options_page() {

	$options_page = new_cmb2_box( array(
		'id'           => 'sobre_mim_options',
		'title'        => 'Sobre mim',
		'object_types' => array( 'options-page' ),
		'option_key'   => 'sobre_mim',
		'icon_url'     => 'dashicons-admin-users',
		'menu_title'   => 'Sobre mim',
		'position'     => 26,
	) );

	$options_page->add_field( array(
		'name' => 'Descrição',
		'desc' => 'Escreva sobre você',
		'id'   => 'descricao',
		'type' => 'textarea',
	) );

	// Campos de redes sociais
	$options_page->add_field( array(
		'name' => 'GitHub',
		'desc' => 'Link para seu perfil no GitHub',
		'id'   => 'github_link',
		'type' => 'text_url',
	) );

	$options_page->add_field( array(
		'name' => 'LinkedIn',
		'desc' => 'Link para seu perfil no LinkedIn',
		'id'   => 'linkedin_link',
		'type' => 'text_url',
	) );

	$options_page->add_field( array(
		'name' => 'Twitter',
		'desc' => 'Link para seu perfil no Twitter',
		'id'   => 'twitter_link',
		'type' => 'text_url',
	) );

	// Campo de e-mail
	$options_page->add_field( array(
		'name' => 'E-mail',
		'desc' => 'Endereço de e-mail de contato',
		'id'   => 'email',
		'type' => 'text_email',
	) );
}

// Include REST routes for Sobre mim (client/v1/sobre-mim)
if ( file_exists( __DIR__ . '/../routes/sobre-mim-route.php' ) ) {
    require_once __DIR__ . '/../routes/sobre-mim-route.php';
}

// Load admin logs UI in WP admin
if ( is_admin() && file_exists( __DIR__ . '/../admin-logs.php' ) ) {
	require_once __DIR__ . '/../admin-logs.php';
}
