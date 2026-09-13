<?php
/**
 * Title: 本期要目（分类框线网格）
 * Slug: paddysun-newsprint/sections
 * Categories: paddysun-newsprint
 * Block Types: core/terms-query
 * Description: 「本期要目」：按分类分块的框线网格，每块列出该分类最新 3 篇。分类由主题自动按最近更新排序并排除「未分类」；在「分类查询」区块里手动勾选分类可覆盖。
 *
 * @package Paddysun_Newsprint
 */

?>
<!-- wp:heading {"level":2,"className":"np-section-head"} -->
<h2 class="wp-block-heading np-section-head"><?php esc_html_e( '本期要目 · Inside This Edition', 'paddysun-newsprint' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:terms-query {"termQuery":{"perPage":6,"taxonomy":"category","order":"desc","orderBy":"count","include":[],"hideEmpty":true,"showNested":false,"inherit":false},"className":"np-sections","layout":{"type":"default"}} -->
<div class="wp-block-terms-query np-sections">
	<!-- wp:term-template {"layout":{"type":"default"}} -->
		<!-- wp:group {"tagName":"section","className":"np-section","layout":{"type":"default"}} -->
		<section class="wp-block-group np-section">
			<!-- wp:term-name {"level":3,"isLink":true,"className":"np-section__name"} /-->

			<!-- wp:query {"query":{"perPage":3,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false,"taxQuery":null,"parents":[]},"className":"np-section__posts","layout":{"type":"default"}} -->
			<div class="wp-block-query np-section__posts">
				<!-- wp:post-template {"layout":{"type":"default"}} -->
					<!-- wp:group {"className":"np-section__link","layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"top"}} -->
					<div class="wp-block-group np-section__link">
						<!-- wp:post-title {"level":4,"isLink":true,"className":"np-section__post"} /-->
						<!-- wp:post-date {"format":"m-d","className":"np-section__date"} /-->
					</div>
					<!-- /wp:group -->
				<!-- /wp:post-template -->
			</div>
			<!-- /wp:query -->
		</section>
		<!-- /wp:group -->
	<!-- /wp:term-template -->
</div>
<!-- /wp:terms-query -->
