<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registra a Options Page "My Tech Stack" no CMB2
 */
add_action( 'cmb2_admin_init', 'cmb2_tech_stack_options_page' );
function cmb2_tech_stack_options_page() {

	$options_page = new_cmb2_box( array(
		'id'           => 'tech_stack_options',
		'title'        => 'My Tech Stack',
		'object_types' => array( 'options-page' ),
		'option_key'   => 'tech_stack',
		'icon_url'     => 'dashicons-editor-code',
		'menu_title'   => 'Tech Stack',
		'position'     => 27,
	) );

	$options_page->add_field( array(
		'name'       => 'Tecnologia',
		'desc'       => 'Adicione as tecnologias da sua stack',
		'id'         => 'tecnologias',
		'type'       => 'text',
		'repeatable' => true,
		'text'       => array(
			'add_row_text' => 'Adicionar Tecnologia',
		),
	) );
}
