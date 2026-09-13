<?php
/**
 * Title: 文章头（眉题 / 标题 / 署名行 + 复制 MD）
 * Slug: paddysun-newsprint/article-header
 * Categories: paddysun-newsprint
 * Block Types: core/post-title
 * Description: Typora Newsprint 文章头：分类眉题、底线标题，署名行（日期 · 作者 · 阅读时间）右侧带浅色「复制 MD」小按钮；署名行下方为特色题图（无图自动不输出，0.10.4 站长反馈补）。
 *
 * @package Paddysun_Newsprint
 */

?>
<!-- wp:group {"tagName":"header","className":"np-article__header","layout":{"type":"default"}} -->
<header class="wp-block-group np-article__header">
	<!-- wp:post-terms {"term":"category","className":"np-article__kicker np-first-term"} /-->

	<!-- wp:post-title {"level":1,"className":"np-article__title"} /-->

	<!-- wp:group {"className":"np-article__byline","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
	<div class="wp-block-group np-article__byline">
		<!-- wp:group {"className":"np-article__meta","layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
		<div class="wp-block-group np-article__meta">
			<!-- wp:post-date {"format":"Y年n月j日"} /-->
			<!-- wp:post-author-name /-->
			<!-- wp:post-time-to-read {"averageReadingSpeed":400} /-->
		</div>
		<!-- /wp:group -->

		<!-- wp:buttons {"className":"np-copymd-wrap"} -->
		<div class="wp-block-buttons np-copymd-wrap">
			<!-- wp:button {"className":"np-copymd"} -->
			<div class="wp-block-button np-copymd"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( '复制 MD', 'paddysun-newsprint' ); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->

	<!-- wp:post-featured-image {"sizeSlug":"large","className":"np-article__image"} /-->
</header>
<!-- /wp:group -->
