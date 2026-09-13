<?php
/**
 * Title: 更正启事（404）
 * Slug: paddysun-newsprint/not-found
 * Categories: paddysun-newsprint
 * Description: 报纸「更正启事」风格的 404：说明、返回头版链接、检索框。
 *
 * @package Paddysun_Newsprint
 */

?>
<!-- wp:group {"className":"np-none","layout":{"type":"default"}} -->
<div class="wp-block-group np-none">
	<!-- wp:heading {"level":1} -->
	<h1 class="wp-block-heading">404</h1>
	<!-- /wp:heading -->

	<!-- wp:paragraph -->
	<p><?php esc_html_e( '更正：您要找的版面不存在，或已被撤稿。', 'paddysun-newsprint' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:paragraph -->
	<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( '← 返回头版', 'paddysun-newsprint' ); ?></a></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:search {"label":"<?php esc_attr_e( '检索', 'paddysun-newsprint' ); ?>","showLabel":false,"placeholder":"<?php esc_attr_e( '检索本刊…', 'paddysun-newsprint' ); ?>","buttonText":"<?php esc_attr_e( '检索', 'paddysun-newsprint' ); ?>","className":"np-searchform"} /-->
