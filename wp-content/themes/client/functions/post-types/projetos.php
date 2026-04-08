<?php
/**
 * Post Type: Projetos
 * Arquivo: functions/post-types/projetos.php
 * Observações: registra o post type 'projetos' para armazenar projetos internos.
 * - Público: false (não expõe projetos públicos por padrão)
 * - UI: true (aparece no admin)
 * - REST: true (para uso via API autenticada, se necessário)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

add_action( 'init', 'ct_register_post_type_projetos' );
function ct_register_post_type_projetos() {
	$labels = array(
		'name'                  => 'Projetos',
		'singular_name'         => 'Projeto',
		'menu_name'             => 'Projetos',
		'name_admin_bar'        => 'Projeto',
		'add_new'               => 'Adicionar novo',
		'add_new_item'          => 'Adicionar novo Projeto',
		'new_item'              => 'Novo Projeto',
		'edit_item'             => 'Editar Projeto',
		'view_item'             => 'Ver Projeto',
		'all_items'             => 'Todos os Projetos',
		'search_items'          => 'Procurar Projetos',
		'parent_item_colon'     => 'Projeto pai:',
		'not_found'             => 'Nenhum projeto encontrado.',
		'not_found_in_trash'    => 'Nenhum projeto encontrado na lixeira.'
	);

	$args = array(
		'labels'                => $labels,
		'public'                => false, // Não expor front-end por omissão
		'show_ui'               => true,
		'show_in_menu'          => true,
		'menu_position'         => 23,
		'menu_icon'             => 'dashicons-portfolio',
		'supports'              => array( 'title', 'editor', 'thumbnail', 'custom-fields', 'revisions' ),
		'capability_type'       => 'post',
		'map_meta_cap'          => true,
		'has_archive'           => false,
		'hierarchical'          => false,
		'rewrite'               => false,
		'query_var'             => false,
		'show_in_rest'          => true, // REST habilitado para uso interno com autenticação
	);

	register_post_type( 'projetos', $args );
}

