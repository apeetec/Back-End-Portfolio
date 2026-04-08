<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registra a Options Page "Formação" no CMB2
 */
add_action( 'cmb2_admin_init', 'cmb2_formacao_options_page' );
function cmb2_formacao_options_page() {

	$options_page = new_cmb2_box( array(
		'id'           => 'formacao_options',
		'title'        => 'Formação',
		'object_types' => array( 'options-page' ),
		'option_key'   => 'formacao',
		'icon_url'     => 'dashicons-welcome-learn-more',
		'menu_title'   => 'Formação',
		'position'     => 24,
	) );

	$group_id = $options_page->add_field( array(
		'id'          => 'formacoes',
		'type'        => 'group',
		'description' => 'Adicione suas formações acadêmicas.',
		'repeatable'  => true,
		'options'     => array(
			'group_title'    => 'Formação {#}',
			'add_button'     => 'Adicionar Formação',
			'remove_button'  => 'Remover Formação',
			'sortable'       => true,
			'closed'         => true,
			'remove_confirm' => 'Tem certeza que deseja remover esta formação?',
		),
	) );

	$options_page->add_group_field( $group_id, array(
		'name' => 'Curso',
		'desc' => 'Ex: Bacharelado em Ciência da Computação',
		'id'   => 'curso',
		'type' => 'text',
	) );

	$options_page->add_group_field( $group_id, array(
		'name' => 'Instituição',
		'desc' => 'Nome da instituição de ensino',
		'id'   => 'instituicao',
		'type' => 'text',
	) );

	$options_page->add_group_field( $group_id, array(
		'name' => 'Ano',
		'desc' => 'Ex: 2022 - 2024 ou 2023 - Atual',
		'id'   => 'ano',
		'type' => 'text',
	) );

	$options_page->add_group_field( $group_id, array(
		'name' => 'Descrição do Curso',
		'desc' => 'Descreva suas responsabilidades e atividades',
		'id'   => 'descricao_curso',
		'type' => 'textarea',
	) );
}

// Include REST routes for Formação (client/v1/formacao)
if ( file_exists( __DIR__ . '/../routes/formacao-route.php' ) ) {
    require_once __DIR__ . '/../routes/formacao-route.php';
}
