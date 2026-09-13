<?php
/**
 * Title: 三栏头条（简报栏 | 主稿 | 侧栏）
 * Slug: paddysun-newsprint/lead-trio
 * Categories: paddysun-newsprint
 * Block Types: core/query
 * Description: Broadside 报纸头版三栏：左栏近日简报（4 篇）、中栏题图压底主稿（最新 1 篇，正文 10 段/2200 字先到为准，首字下沉双栏）、右栏 4:5 题图侧栏（固定第 6 篇，offset=5）。
 *
 * @package Paddysun_Newsprint
 */

?>
<!-- wp:group {"className":"np-lead","layout":{"type":"default"}} -->
<div class="wp-block-group np-lead">
	<!-- wp:group {"className":"np-lead__rail","layout":{"type":"default"}} -->
	<div class="wp-block-group np-lead__rail">
		<!-- wp:heading {"level":2,"className":"np-rail-title"} -->
		<h2 class="wp-block-heading np-rail-title"><?php esc_html_e( '近日简报', 'paddysun-newsprint' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:query {"query":{"perPage":4,"pages":0,"offset":1,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false,"taxQuery":null,"parents":[]},"className":"np-briefs","layout":{"type":"default"}} -->
		<div class="wp-block-query np-briefs">
			<!-- wp:post-template {"className":"np-lead-follow","layout":{"type":"default"}} -->
				<!-- wp:group {"tagName":"article","className":"np-brief np-displayed","layout":{"type":"default"}} -->
				<article class="wp-block-group np-brief np-displayed">
					<!-- wp:post-terms {"term":"category","className":"np-kicker np-first-term"} /-->
					<!-- wp:post-title {"level":3,"isLink":true,"className":"np-brief__head"} /-->
					<!-- wp:post-excerpt {"moreText":"","excerptLength":64,"className":"np-brief__sum"} /-->
				</article>
				<!-- /wp:group -->
			<!-- /wp:post-template -->
		</div>
		<!-- /wp:query -->
	</div>
	<!-- /wp:group -->

	<!-- wp:query {"query":{"perPage":1,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false,"taxQuery":null,"parents":[]},"className":"np-lead__main","layout":{"type":"default"}} -->
	<div class="wp-block-query np-lead__main">
		<!-- wp:post-template {"className":"np-lead-main-follow","layout":{"type":"default"}} -->
			<!-- wp:group {"tagName":"article","className":"np-lead__story np-displayed","layout":{"type":"default"}} -->
			<article class="wp-block-group np-lead__story np-displayed">
				<!-- wp:group {"className":"np-lead__hero","layout":{"type":"default"}} -->
				<div class="wp-block-group np-lead__hero">
					<!-- wp:post-featured-image {"isLink":true,"sizeSlug":"large","className":"np-lead__backdrop"} /-->

					<!-- wp:paragraph {"className":"np-lead__label"} -->
					<p class="np-lead__label"><?php esc_html_e( '长读 · The Long Read', 'paddysun-newsprint' ); ?></p>
					<!-- /wp:paragraph -->

					<!-- wp:post-title {"level":2,"isLink":true,"className":"np-lead__title"} /-->
					<!-- wp:post-excerpt {"moreText":"","excerptLength":34,"className":"np-lead__deck"} /-->

					<!-- wp:group {"className":"np-byline np-utility-type","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"center"}} -->
					<div class="wp-block-group np-byline np-utility-type">
						<!-- wp:post-author-name /-->
						<!-- wp:post-date /-->
					</div>
					<!-- /wp:group -->
				</div>
				<!-- /wp:group -->

				<!-- wp:post-content {"className":"np-columns np-lead-body","layout":{"type":"constrained"}} /-->
			</article>
			<!-- /wp:group -->
		<!-- /wp:post-template -->
	</div>
	<!-- /wp:query -->

		<!-- wp:query {"query":{"perPage":1,"pages":0,"offset":5,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false,"taxQuery":null,"parents":[]},"className":"np-lead__aside np-aside","layout":{"type":"default"}} -->
		<div class="wp-block-query np-lead__aside np-aside">
		<!-- wp:post-template {"className":"np-aside-fallback np-lead-follow","layout":{"type":"default"}} -->
			<!-- wp:group {"tagName":"aside","className":"np-aside np-displayed","layout":{"type":"default"}} -->
			<aside class="wp-block-group np-aside np-displayed">
				<!-- wp:post-terms {"term":"category","className":"np-kicker np-first-term"} /-->
				<!-- wp:post-title {"level":3,"isLink":true,"className":"np-aside__title"} /-->
				<!-- wp:post-content {"className":"np-aside-body","layout":{"type":"constrained"}} /-->
				<!-- wp:read-more {"content":"<?php esc_attr_e( '读这篇文章 →', 'paddysun-newsprint' ); ?>","className":"np-aside__more-wrap"} /-->
			</aside>
			<!-- /wp:group -->
		<!-- /wp:post-template -->
	</div>
	<!-- /wp:query -->
</div>
<!-- /wp:group -->
