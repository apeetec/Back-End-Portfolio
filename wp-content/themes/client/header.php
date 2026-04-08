<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="author" content="Gabriel Batista">
  <meta name="description" content="<?php echo get_bloginfo('description'); ?>">
  
  <title>
    <?php if (is_home()) {
      echo get_bloginfo('name') . ' | ' . get_bloginfo('description');
    } else {
      echo get_the_title() . ' | ' . get_bloginfo('name');
    } ?>
  </title>

  <!-- Favicon -->
  <!-- <link rel="icon" type="image/png" href="<?php bloginfo('template_url'); ?>/img/favicon.png"> -->
  
  <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
  <?php wp_body_open(); ?>