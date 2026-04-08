<?php
/**
 * Taxonomia: Categoria de Arquivos
 * Arquivo: functions/taxonomias/taxonomia-arquivos.php
 * Observações: registra a taxonomia `categoria_arquivo` ligada ao post type `arquivos`.
 * - Hierarchical: true (comportamento de categoria)
 * - show_ui: true (aparece no admin)
 * - show_in_rest: true (disponível via REST para aplicações autenticadas)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

add_action( 'init', 'ct_register_taxonomy_categoria_arquivo' );
function ct_register_taxonomy_categoria_arquivo() {
	$labels = array(
		'name'              => 'Categorias',
		'singular_name'     => 'Categoria',
		'search_items'      => 'Procurar Categorias',
		'all_items'         => 'Todas as Categorias',
		'parent_item'       => 'Categoria pai',
		'parent_item_colon' => 'Categoria pai:',
		'edit_item'         => 'Editar Categoria',
		'update_item'       => 'Atualizar Categoria',
		'add_new_item'      => 'Adicionar Nova Categoria',
		'new_item_name'     => 'Nome da nova categoria',
		'menu_name'         => 'Categorias de Projetos',
	);

	$args = array(
		'hierarchical'      => true,
		'labels'            => $labels,
		'show_ui'           => true,
		'show_admin_column' => true,
		'query_var'         => false,
		'rewrite'           => false,
		'show_in_rest'      => true,
		'public'            => false,
	);

	register_taxonomy( 'categoria_arquivo', array( 'projetos' ), $args );
}

// Taxonomia: Tags para Arquivos (não-hierárquica)
add_action( 'init', 'ct_register_taxonomy_tag_arquivo' );
function ct_register_taxonomy_tag_arquivo() {
	$labels = array(
		'name'                       => 'Tags',
		'singular_name'              => 'Tag',
		'search_items'               => 'Procurar Tags',
		'popular_items'              => 'Tags populares',
		'all_items'                  => 'Todas as Tags',
		'edit_item'                  => 'Editar Tag',
		'update_item'                => 'Atualizar Tag',
		'add_new_item'               => 'Adicionar Nova Tag',
		'new_item_name'              => 'Nome da nova tag',
		'separate_items_with_commas' => 'Separe as tags com vírgulas',
		'add_or_remove_items'        => 'Adicionar ou remover tags',
		'choose_from_most_used'      => 'Escolher entre as mais usadas',
		'menu_name'                  => 'Tags de Arquivo',
	);

	$args = array(
		'hierarchical'          => false,
		'labels'                => $labels,
		'show_ui'               => true,
		'show_admin_column'     => true,
		'update_count_callback' => '_update_post_term_count',
		'query_var'             => 'tag_arquivo',
		'rewrite'               => array( 'slug' => 'tag_arquivo' ),
		'show_in_rest'          => true,
		'public'                => false,
	);

	register_taxonomy( 'tag_arquivo', array( 'projetos' ), $args );
}

