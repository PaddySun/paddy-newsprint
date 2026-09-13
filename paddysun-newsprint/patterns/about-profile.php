<?php
/**
 * Title: 关于页人物版式（简介 + 时间线）
 * Slug: paddysun-newsprint/about-profile
 * Categories: paddysun-newsprint
 * Block Types: core/post-content
 * Post Types: page
 * Description: 关于页人物骨架：media-text 图文简介（右侧头像）+ 年份时间线。简介与时间线为占位文案，站长自填。（0.10.4 站长裁决：宽幅徽章整节移除，需要时从「徽章墙」pattern 自行拼装。）
 *
 * @package Paddysun_Newsprint
 */

?>
<!-- wp:media-text {"mediaPosition":"right","mediaType":"image","mediaWidth":28,"imageFill":false} -->
<div class="wp-block-media-text has-media-on-the-right is-stacked-on-mobile" style="grid-template-columns:auto 28%"><div class="wp-block-media-text__content"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php esc_html_e( 'Hey! 我是……', 'paddysun-newsprint' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( '用两三段话说清你是谁：相信什么、写什么、为什么在这里。（此处为占位文案，替换成你自己的介绍。）', 'paddysun-newsprint' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><?php esc_html_e( '联系方式与社交账号可以放这一段。', 'paddysun-newsprint' ); ?></p>
<!-- /wp:paragraph --></div><figure class="wp-block-media-text__media"><img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/about-placeholder.svg' ); ?>" alt="<?php esc_attr_e( '人物头像占位图', 'paddysun-newsprint' ); ?>"/></figure></div>
<!-- /wp:media-text -->

<!-- wp:group {"tagName":"section","className":"np-links__group","layout":{"type":"default"}} -->
<section class="wp-block-group np-links__group">
	<!-- wp:heading {"level":2,"className":"np-section-head"} -->
	<h2 class="wp-block-heading np-section-head"><?php esc_html_e( '时间线 · Timeline', 'paddysun-newsprint' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:list {"className":"np-about__timeline"} -->
	<ul class="wp-block-list np-about__timeline">
		<!-- wp:list-item -->
		<li><?php esc_html_e( '2022 —— 建站，开始写字。', 'paddysun-newsprint' ); ?></li>
		<!-- /wp:list-item -->
		<!-- wp:list-item -->
		<li><?php esc_html_e( '2024 —— 换了现在的报纸主题。', 'paddysun-newsprint' ); ?></li>
		<!-- /wp:list-item -->
		<!-- wp:list-item -->
		<li><?php esc_html_e( '2026 —— 仍在路上。（整组不需要可直接删除）', 'paddysun-newsprint' ); ?></li>
		<!-- /wp:list-item -->
	</ul>
	<!-- /wp:list -->
</section>
<!-- /wp:group -->
