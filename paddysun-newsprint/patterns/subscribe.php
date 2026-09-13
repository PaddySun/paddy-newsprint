<?php
/**
 * Title: 订阅框（墨底 RSS / llms.txt）
 * Slug: paddysun-newsprint/subscribe
 * Categories: paddysun-newsprint
 * Description: Broadside newsletter 框改造：墨底双规线，左侧眉题 / 标题 / 说明，右侧 RSS 与 llms.txt 按钮。
 *
 * @package Paddysun_Newsprint
 */

?>
<!-- wp:group {"tagName":"section","className":"np-subscribe","layout":{"type":"default"}} -->
<section class="wp-block-group np-subscribe">
	<!-- wp:group {"className":"np-subscribe__body","layout":{"type":"default"}} -->
	<div class="wp-block-group np-subscribe__body">
		<!-- wp:paragraph {"className":"np-subscribe__eyebrow"} -->
		<p class="np-subscribe__eyebrow"><?php esc_html_e( '订阅 · Subscribe', 'paddysun-newsprint' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"level":2,"className":"np-subscribe__name"} -->
		<h2 class="wp-block-heading np-subscribe__name"><?php esc_html_e( '订阅本刊', 'paddysun-newsprint' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"np-subscribe__blurb"} -->
		<p class="np-subscribe__blurb"><?php esc_html_e( 'RSS 可用任意阅读器订阅；llms.txt 为机器读者而设，AI 可循此索引取阅全刊。', 'paddysun-newsprint' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:buttons {"className":"np-subscribe__links","layout":{"type":"flex","orientation":"vertical"}} -->
	<div class="wp-block-buttons np-subscribe__links">
		<!-- wp:button {"className":"np-subscribe__link"} -->
		<div class="wp-block-button np-subscribe__link"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( get_feed_link() ); ?>"><?php esc_html_e( 'RSS 订阅', 'paddysun-newsprint' ); ?></a></div>
		<!-- /wp:button -->

		<!-- wp:button {"className":"np-subscribe__link"} -->
		<div class="wp-block-button np-subscribe__link"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>">llms.txt</a></div>
		<!-- /wp:button -->

		<!-- wp:button {"className":"np-subscribe__link"} -->
		<div class="wp-block-button np-subscribe__link"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/llms-full.txt' ) ); ?>">llms-full.txt</a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</section>
<!-- /wp:group -->
