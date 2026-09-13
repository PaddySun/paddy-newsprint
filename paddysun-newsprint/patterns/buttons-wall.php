<?php
/**
 * Title: 88×31 按钮墙（我的按钮 + 交换墙）
 * Slug: paddysun-newsprint/buttons-wall
 * Categories: paddysun-newsprint
 * Block Types: core/post-content
 * Post Types: page
 * Description: 复古 88×31 按钮文化两件套：上方「我的按钮」展示站长自荐按钮并附供他站复制的 HTML 片段；下方「按钮墙」收纳交换所得的友站按钮。尺寸由主题 CSS 锁死（88×31 + pixelated），GIF 原样输出。
 *
 * @package Paddysun_Newsprint
 */

$np_snippet_lines = array(
	'<a href="https://example.com/" target="_blank" rel="noopener"><img src="' . esc_url( get_template_directory_uri() . '/assets/img/placeholders/badge-88x31-1.png' ) . '" alt="示例站点" width="88" height="31"></a>',
	'<a href="https://example.com/" target="_blank" rel="noopener"><img src="' . esc_url( get_template_directory_uri() . '/assets/img/placeholders/badge-88x31-2.png' ) . '" alt="示例站点" width="88" height="31"></a>',
	'<a href="https://example.com/" target="_blank" rel="noopener"><img src="' . esc_url( get_template_directory_uri() . '/assets/img/placeholders/badge-88x31-3.png' ) . '" alt="示例站点" width="88" height="31"></a>',
	'<a href="https://example.com/" target="_blank" rel="noopener"><img src="' . esc_url( get_template_directory_uri() . '/assets/img/placeholders/badge-88x31-4.png' ) . '" alt="示例站点" width="88" height="31"></a>',
);

?>
<!-- wp:group {"tagName":"section","className":"np-links__group","layout":{"type":"default"}} -->
<section class="wp-block-group np-links__group">
	<!-- wp:heading {"level":2,"className":"np-section-head"} -->
	<h2 class="wp-block-heading np-section-head"><?php esc_html_e( '我的按钮 · My Buttons', 'paddysun-newsprint' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:group {"className":"np-btnwall","layout":{"type":"default"}} -->
	<div class="wp-block-group np-btnwall">
		<!-- wp:image {"linkDestination":"custom","sizeSlug":"full","className":"np-btnwall__btn"} -->
		<figure class="wp-block-image size-full np-btnwall__btn"><a href="https://example.com/" target="_blank" rel="noopener"><img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/placeholders/badge-88x31-1.png' ); ?>" alt="示例站点 · clouds"/></a></figure>
		<!-- /wp:image -->

		<!-- wp:image {"linkDestination":"custom","sizeSlug":"full","className":"np-btnwall__btn"} -->
		<figure class="wp-block-image size-full np-btnwall__btn"><a href="https://example.com/" target="_blank" rel="noopener"><img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/placeholders/badge-88x31-2.png' ); ?>" alt="示例站点 · dynamic"/></a></figure>
		<!-- /wp:image -->

		<!-- wp:image {"linkDestination":"custom","sizeSlug":"full","className":"np-btnwall__btn"} -->
		<figure class="wp-block-image size-full np-btnwall__btn"><a href="https://example.com/" target="_blank" rel="noopener"><img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/placeholders/badge-88x31-3.png' ); ?>" alt="示例站点 · cyberpunk"/></a></figure>
		<!-- /wp:image -->

		<!-- wp:image {"linkDestination":"custom","sizeSlug":"full","className":"np-btnwall__btn"} -->
		<figure class="wp-block-image size-full np-btnwall__btn"><a href="https://example.com/" target="_blank" rel="noopener"><img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/placeholders/badge-88x31-4.png' ); ?>" alt="示例站点 · fire"/></a></figure>
		<!-- /wp:image -->
	</div>
	<!-- /wp:group -->

	<!-- wp:paragraph {"className":"np-btnwall__note"} -->
	<p class="np-btnwall__note"><?php esc_html_e( '以下片段供友站引用，任选一枚（GIF 上传媒体库后请把图片地址换成实际 URL）：', 'paddysun-newsprint' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:preformatted {"className":"np-snippet"} -->
	<pre class="wp-block-preformatted np-snippet"><?php echo esc_html( implode( "\n", $np_snippet_lines ) ); ?></pre>
	<!-- /wp:preformatted -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","className":"np-links__group","layout":{"type":"default"}} -->
<section class="wp-block-group np-links__group">
	<!-- wp:heading {"level":2,"className":"np-section-head"} -->
	<h2 class="wp-block-heading np-section-head"><?php esc_html_e( '按钮墙 · Button Wall', 'paddysun-newsprint' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph -->
	<p><?php esc_html_e( '与友站交换所得的 88×31 按钮收藏在此，按交换先后排列。', 'paddysun-newsprint' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:group {"className":"np-btnwall","layout":{"type":"default"}} -->
	<div class="wp-block-group np-btnwall">
		<!-- wp:image {"linkDestination":"custom","sizeSlug":"full","className":"np-btnwall__btn"} -->
		<figure class="wp-block-image size-full np-btnwall__btn"><a href="https://creativecommons.org/licenses/by-nc-sa/4.0/deed.zh" target="_blank" rel="noopener"><img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/placeholders/badge-88x31-1.png' ); ?>" alt="CC BY-NC-SA 4.0"/></a></figure>
		<!-- /wp:image -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
