<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registra a Options Page "O que eu faço" no CMB2
 */
add_action( 'cmb2_admin_init', 'cmb2_o_que_eu_faco_options_page' );
function cmb2_o_que_eu_faco_options_page() {

	$options_page = new_cmb2_box( array(
		'id'           => 'o_que_eu_faco_options',
		'title'        => 'O que eu faço',
		'object_types' => array( 'options-page' ),
		'option_key'   => 'o_que_eu_faco',
		'icon_url'     => 'dashicons-hammer',
		'menu_title'   => 'O que eu faço',
		'position'     => 25,
	) );

	$options_page->add_field( array(
		'name' => 'Descrição',
		'desc' => 'Descreva o que você faz profissionalmente',
		'id'   => 'descricao',
		'type' => 'textarea',
	) );

	$options_page->add_field( array(
		'name'    => 'Tags de Tecnologias',
		'desc'    => 'Insira as tecnologias que você domina (separadas por vírgula)',
		'id'      => 'tags_tecnologias',
		'type'    => 'text',
		'repeatable' => true,
		'text'    => array(
			'add_row_text' => 'Adicionar Tecnologia',
		),
	) );
}

// Include REST routes for O que eu faço (client/v1/o-que-eu-faco)
if ( file_exists( __DIR__ . '/../routes/oque-eu-faco-route.php' ) ) {
	require_once __DIR__ . '/../routes/oque-eu-faco-route.php';
}

