<?php
/**
 * Title: 读者来信（评论区）
 * Slug: paddysun-newsprint/comments
 * Categories: paddysun-newsprint
 * Block Types: core/comments
 * Description: 极简评论区：来信标题、评论列表（头像 / 作者 / 日期 / 正文 / 回复）、分页、评论表单。
 *
 * @package Paddysun_Newsprint
 */

?>
<!-- wp:comments {"className":"wp-block-comments-query-loop np-comments"} -->
<div class="wp-block-comments wp-block-comments-query-loop np-comments">
	<!-- wp:comments-title {"level":2,"showPostTitle":false} /-->

	<!-- wp:comment-template -->
		<!-- wp:group {"className":"np-comment","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"top"}} -->
		<div class="wp-block-group np-comment">
			<!-- wp:avatar {"size":32} /-->

			<!-- wp:group {"className":"np-comment__body","layout":{"type":"default"}} -->
			<div class="wp-block-group np-comment__body">
				<!-- wp:group {"className":"np-comment__meta","layout":{"type":"flex","flexWrap":"wrap"}} -->
				<div class="wp-block-group np-comment__meta">
					<!-- wp:comment-author-name /-->
					<!-- wp:comment-date /-->
				</div>
				<!-- /wp:group -->

				<!-- wp:comment-content /-->

				<!-- wp:group {"className":"np-comment__actions","layout":{"type":"flex","flexWrap":"nowrap"}} -->
				<div class="wp-block-group np-comment__actions">
					<!-- wp:comment-reply-link /-->
					<!-- wp:comment-edit-link /-->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	<!-- /wp:comment-template -->

	<!-- wp:comments-pagination {"layout":{"type":"flex","justifyContent":"space-between"}} -->
		<!-- wp:comments-pagination-previous /-->
		<!-- wp:comments-pagination-next /-->
	<!-- /wp:comments-pagination -->

	<!-- wp:post-comments-form /-->
</div>
<!-- /wp:comments -->
