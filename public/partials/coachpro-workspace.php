<?php
/** Authenticated Workspace presentation; existing shortcode/content handlers render the app. */
if ( ! defined( 'ABSPATH' ) ) exit;
?><!doctype html>
<html <?php language_attributes(); ?> class="cp-workspace-document">
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <?php wp_head(); ?>
</head>
<body <?php body_class( 'cp-workspace-page' ); ?>>
<?php wp_body_open(); ?>
<?php while ( have_posts() ) : the_post(); ?>
    <?php the_content(); ?>
<?php endwhile; ?>
<?php wp_footer(); ?>
</body>
</html>
