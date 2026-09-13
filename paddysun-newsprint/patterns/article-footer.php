<?php
/**
 * Title: 文章尾（标签 / 上下篇）
 * Slug: paddysun-newsprint/article-footer
 * Categories: paddysun-newsprint
 * Block Types: core/post-navigation-link
 * Description: 双规线之下：标签与上下篇导航。「复制 MD」按钮在文章头的署名行右侧。
 *
 * @package Paddysun_Newsprint
 */

?>
<!-- wp:group {"tagName":"footer","className":"np-article__footer","layout":{"type":"default"}} -->
<footer class="wp-block-group np-article__footer">
	<!-- wp:post-terms {"term":"post_tag","separator":" ","className":"np-tags"} /-->

	<!-- wp:group {"tagName":"nav","ariaLabel":"<?php esc_attr_e( '上下篇', 'paddysun-newsprint' ); ?>","className":"np-postnav","layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"top"}} -->
	<nav class="wp-block-group np-postnav" aria-label="<?php esc_attr_e( '上下篇', 'paddysun-newsprint' ); ?>">
		<!-- wp:post-navigation-link {"type":"next","label":"<?php esc_attr_e( '较新一篇', 'paddysun-newsprint' ); ?>","showTitle":true,"className":"np-postnav__next"} /-->
		<!-- wp:post-navigation-link {"type":"previous","label":"<?php esc_attr_e( '较早一篇', 'paddysun-newsprint' ); ?>","showTitle":true,"className":"np-postnav__prev"} /-->
	</nav>
	<!-- /wp:group -->
</footer>
<!-- /wp:group -->
