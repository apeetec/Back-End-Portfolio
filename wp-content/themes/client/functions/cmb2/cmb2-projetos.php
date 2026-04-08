<?php
add_action('cmb2_admin_init', 'cmb2_arquivos');
function cmb2_arquivos()
{
    // CBM2 padrão documentos
    $cmb = new_cmb2_box(array(
        'id' => 'informacoes_arquivo',
        'title' => 'Informações',
        'object_types' => array('projetos'), // Post type
        // 'show_on_cb' => 'yourprefix_show_if_front_page', // function should return a bool value
        // 'context'    => 'normal',
        // 'priority'   => 'high',
        // 'show_names' => true, // Show field names on the left
        // 'cmb_styles' => false, // false to disable the CMB stylesheet
        'closed' => false, // true to keep the metabox closed by default
        // 'classes'    => 'extra-class', // Extra cmb2-wrap classes
        // 'classes_cb' => 'yourprefix_add_some_classes', // Add classes through a callback.
    ));
    $cmb->add_field(array(
        'name'    => 'Subtítulo',
        'desc'    => 'Subtítulo do projeto',
        'default' => 'Subtítulo padrão',
        'id'      => 'subtitulo_projeto',
        'type'    => 'text',
    ));
    $cmb->add_field(array(
        'name'    => 'Legenda',
        'desc'    => 'Legenda do projeto',
        'default' => 'Legenda padrão',
        'id'      => 'legenda_projeto',
        'type'    => 'text',
    ));
    $cmb->add_field(array(
        'name' => 'Descrição',
        'desc' => 'Descrição do projeto',
        'default' => 'Descrição padrão',
        'id' => 'descricao_projeto',
        'type' => 'textarea'
    ));
    $cmb->add_field( array(
	'name'    => 'Imagem do projeto',
	'desc'    => 'Faça upload ou escolha uma imagem para o projeto.',
	'id'      => 'imagem_projeto',
	'type'    => 'file',
	// Optional:
	'options' => array(
		'url' => false, // Hide the text input for the url
	),
	'text'    => array(
		'add_upload_file_text' => 'Adicionar Arquivo' // Change upload button text. Default: "Add or Upload File"
	),
	// query_args are passed to wp.media's library query.
	'query_args' => array(
		'type' => array(
			'image/gif',
			'image/jpeg',
			'image/png',
		),
	),
	'preview_size' => 'large', // Image size to use when previewing in the admin.
) );
$cmb->add_field( array(
		'name' => 'Link do projeto',
		'desc' => 'Link para o projeto',
		'id'   => 'projeto_link',
		'type' => 'text_url',
) );
}
