<?php
/**
 * Title: 规线清单（归档 / 检索 / 索引）
 * Slug: paddysun-newsprint/post-list
 * Categories: paddysun-newsprint
 * Block Types: core/query
 * Description: 日期在左、标题与摘要在右的规线清单，继承当前查询（归档、检索、博客索引通用），含分页与空结果提示。
 *
 * @package Paddysun_Newsprint
 */

?>
<!-- wp:query {"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true,"taxQuery":null,"parents":[]},"className":"np-entries","layout":{"type":"default"}} -->
<div class="wp-block-query np-entries">
	<!-- wp:post-template {"layout":{"type":"default"}} -->
		<!-- wp:group {"tagName":"article","className":"np-entry","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"top"}} -->
		<article class="wp-block-group np-entry">
			<!-- wp:post-date {"format":"Y-m-d","className":"np-entry__date"} /-->

			<!-- wp:group {"className":"np-entry__body","layout":{"type":"default"}} -->
			<div class="wp-block-group np-entry__body">
				<!-- wp:post-title {"level":2,"isLink":true,"className":"np-entry__title"} /-->
				<!-- wp:post-excerpt {"moreText":"","excerptLength":50,"className":"np-entry__excerpt"} /-->
			</div>
			<!-- /wp:group -->
		</article>
		<!-- /wp:group -->
	<!-- /wp:post-template -->

	<!-- wp:query-pagination {"className":"np-pagination","layout":{"type":"flex","justifyContent":"space-between"}} -->
		<!-- wp:query-pagination-previous {"label":"<?php esc_attr_e( '« 较新', 'paddysun-newsprint' ); ?>"} /-->
		<!-- wp:query-pagination-next {"label":"<?php esc_attr_e( '较早 »', 'paddysun-newsprint' ); ?>"} /-->
	<!-- /wp:query-pagination -->

	<!-- wp:query-no-results -->
		<!-- wp:group {"className":"np-none","layout":{"type":"default"}} -->
		<div class="wp-block-group np-none">
			<!-- wp:heading {"level":2} -->
			<h2 class="wp-block-heading"><?php esc_html_e( '本版暂无稿目', 'paddysun-newsprint' ); ?></h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph -->
			<p><?php esc_html_e( '该版面下还没有文章，换个关键词或分类试试。', 'paddysun-newsprint' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
	<!-- /wp:query-no-results -->
</div>
<!-- /wp:query -->
