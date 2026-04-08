<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registra a Options Page "Experiência e Carreira" no CMB2
 */
add_action( 'cmb2_admin_init', 'cmb2_experiencia_carreira_options_page' );
function cmb2_experiencia_carreira_options_page() {

	$options_page = new_cmb2_box( array(
		'id'           => 'experiencia_carreira_options',
		'title'        => 'Experiência e Carreira',
		'object_types' => array( 'options-page' ),
		'option_key'   => 'experiencia_carreira',
		'icon_url'     => 'dashicons-businessman',
		'menu_title'   => 'Experiência',
		'position'     => 24,
	) );

	$group_id = $options_page->add_field( array(
		'id'          => 'experiencias',
		'type'        => 'group',
		'description' => 'Adicione suas experiências profissionais.',
		'repeatable'  => true,
		'options'     => array(
			'group_title'    => 'Experiência {#}',
			'add_button'     => 'Adicionar Experiência',
			'remove_button'  => 'Remover Experiência',
			'sortable'       => true,
			'closed'         => true,
			'remove_confirm' => 'Tem certeza que deseja remover esta experiência?',
		),
	) );

	$options_page->add_group_field( $group_id, array(
		'name' => 'Cargo',
		'desc' => 'Ex: Desenvolvedor Full Stack',
		'id'   => 'cargo',
		'type' => 'text',
	) );

	$options_page->add_group_field( $group_id, array(
		'name' => 'Empresa',
		'desc' => 'Nome da empresa',
		'id'   => 'empresa',
		'type' => 'text',
	) );

	$options_page->add_group_field( $group_id, array(
		'name' => 'Ano',
		'desc' => 'Ex: 2022 - 2024 ou 2023 - Atual',
		'id'   => 'ano',
		'type' => 'text',
	) );

	$options_page->add_group_field( $group_id, array(
		'name' => 'Descrição do Cargo',
		'desc' => 'Descreva suas responsabilidades e atividades',
		'id'   => 'descricao_cargo',
		'type' => 'textarea',
	) );
}
