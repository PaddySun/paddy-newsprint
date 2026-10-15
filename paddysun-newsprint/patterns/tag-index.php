<?php
/**
 * Title: 版签总目（按栏目分组的标签索引）
 * Slug: paddysun-newsprint/tag-index
 * Categories: paddysun-newsprint
 * Description: 全站标签的统计与展示索引：统计行 + 以一级分类为组的标签清单（组内按篇数排序，行内排布）；不属于任何栏目的标签归入「散签」。两条批量聚合 SQL 取数（多语言环境退回 WP API），整段结果 transient 缓存。配「版签总目」页面模板（/tags）使用。
 *
 * @package Paddysun_Newsprint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 结果层缓存：固定键 + 值内版本比对，版本推进原地覆盖（无孤儿行）；生产站有
// Redis Object Cache 时 transient 存于 Redis。键含 locale，切语言不串页。
$np_tags_idx_key  = 'paddysun_ns_tagsidx_' . sanitize_key( get_locale() );
$np_tags_idx_data = get_transient( $np_tags_idx_key );
if ( is_array( $np_tags_idx_data ) && isset( $np_tags_idx_data['v'], $np_tags_idx_data['html'] )
	&& (int) $np_tags_idx_data['v'] === paddysun_ns_index_version() ) {
	echo $np_tags_idx_data['html'];
	return;
}

ob_start();

global $wpdb;

$tag_terms = get_terms(
	array(
		'taxonomy'   => 'post_tag',
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => 0,
	)
);

if ( is_wp_error( $tag_terms ) || empty( $tag_terms ) ) :
	?>
<!-- wp:group {"className":"np-none","layout":{"type":"default"}} -->
<div class="wp-block-group np-none">
	<!-- wp:heading {"level":2} -->
	<h2 class="wp-block-heading"><?php esc_html_e( '尚无版签', 'paddysun-newsprint' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph -->
	<p><?php esc_html_e( '给文章加上标签后，这里会出现标签总目。', 'paddysun-newsprint' ); ?></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
	<?php
else :
	$tags_by_id = array();
	foreach ( $tag_terms as $tag_term ) {
		$tags_by_id[ (int) $tag_term->term_id ] = $tag_term;
	}

	// 一级分类（组）：term_order 顺序，排除默认「未分类」。
	$default_cat_id = (int) get_option( 'default_category' );
	$top_sections   = get_terms(
		array(
			'taxonomy'   => 'category',
			'parent'     => 0,
			'hide_empty' => true,
			'orderby'    => 'term_order',
		)
	);
	if ( is_wp_error( $top_sections ) ) {
		$top_sections = array();
	}
	$top_sections = array_values(
		array_filter(
			$top_sections,
			static function ( $section ) use ( $default_cat_id ) {
				return (int) $section->term_id !== $default_cat_id;
			}
		)
	);

	// 词 → 所属一级分类（含子分类归并，等价 tax_query 的 include_children）。
	$term_owner = array();
	foreach ( $top_sections as $top_section ) {
		$term_owner[ (int) $top_section->term_id ] = (int) $top_section->term_id;
		foreach ( get_term_children( $top_section->term_id, 'category' ) as $child_id ) {
			$term_owner[ (int) $child_id ] = (int) $top_section->term_id;
		}
	}

	// 关系取数：单语言两条批量 SQL；多语言（Polylang/WPML）退回 WP API 路径
	// （插件经 filters 过滤 get_posts/get_objects_in_term，保证按语言口径；
	// 性能由整段 transient 缓存兜底）。
	$np_cat_posts = array();
	$np_tag_pids  = array();
	$np_tagged_pids = array();

	if ( paddysun_ns_is_multilingual() ) {
		foreach ( $top_sections as $top_section ) {
			$np_sec_pids = get_posts(
				array(
					'post_type'           => 'post',
					'posts_per_page'      => -1,
					'fields'              => 'ids',
					'no_found_rows'       => true,
					'ignore_sticky_posts' => true,
					'post_status'         => 'publish',
					'tax_query'           => array(
						array(
							'taxonomy' => 'category',
							'field'    => 'term_id',
							'terms'    => (int) $top_section->term_id,
						),
					),
				)
			);
			if ( ! empty( $np_sec_pids ) ) {
				$np_cat_posts[ (int) $top_section->term_id ] = array_fill_keys( array_map( 'intval', $np_sec_pids ), true );
			}
		}
		foreach ( $tag_terms as $np_ml_tag ) {
			$np_ml_pids = get_objects_in_term( (int) $np_ml_tag->term_id, 'post_tag' );
			if ( is_wp_error( $np_ml_pids ) || empty( $np_ml_pids ) ) {
				continue;
			}
			$np_ml_pids = array_map( 'intval', $np_ml_pids );
			$np_tag_pids[ (int) $np_ml_tag->term_id ] = $np_ml_pids;
			foreach ( $np_ml_pids as $np_ml_pid ) {
				$np_tagged_pids[ $np_ml_pid ] = true;
			}
		}
		$np_tagged_count = count( $np_tagged_pids );
	} else {
		// 批量取「发布文章 → 分类」与「发布文章 → 标签」两组关系，替代逐词/逐栏目查询。
		$np_rel_sql = "SELECT tr.object_id AS pid, tt.term_id AS tid
			FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = %s
			INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_type = 'post' AND p.post_status = 'publish'";

		$np_cat_rows = $wpdb->get_results( $wpdb->prepare( $np_rel_sql, 'category' ) );
		$np_tag_rows = $wpdb->get_results( $wpdb->prepare( $np_rel_sql, 'post_tag' ) );

		foreach ( (array) $np_cat_rows as $np_rel ) {
			$owner = $term_owner[ (int) $np_rel->tid ] ?? 0;
			if ( $owner ) {
				$np_cat_posts[ $owner ][ (int) $np_rel->pid ] = true;
			}
		}
		foreach ( (array) $np_tag_rows as $np_rel ) {
			$np_tag_pids[ (int) $np_rel->tid ][] = (int) $np_rel->pid;
		}
		// 覆盖篇数（去重）：挂有任意标签的发布文章数。
		$np_tagged_count = count( array_unique( wp_list_pluck( (array) $np_tag_rows, 'pid' ) ) );
	}

	// 归组：标签跟随其文章所属的第一个一级分类（组按 term_order 优先），未落入任何栏目为散签。
	$np_sort_by_count = static function ( $a, $b ) {
		return $b->count <=> $a->count;
	};

	$assigned_ids = array();
	$tag_groups   = array();
	foreach ( $top_sections as $top_section ) {
		$group_posts = $np_cat_posts[ (int) $top_section->term_id ] ?? array();
		if ( empty( $group_posts ) ) {
			continue;
		}
		$group_tags = array();
		foreach ( $np_tag_pids as $tag_id => $pids ) {
			if ( isset( $assigned_ids[ $tag_id ] ) || ! isset( $tags_by_id[ $tag_id ] ) ) {
				continue;
			}
			foreach ( $pids as $pid ) {
				if ( isset( $group_posts[ $pid ] ) ) {
					$assigned_ids[ $tag_id ] = true;
					$group_tags[]             = $tags_by_id[ $tag_id ];
					break;
				}
			}
		}
		if ( ! empty( $group_tags ) ) {
			usort( $group_tags, $np_sort_by_count );
			$tag_groups[] = array(
				'section' => $top_section,
				'tags'    => $group_tags,
			);
		}
	}

	$loose_tags = array_values(
		array_filter(
			$tag_terms,
			static function ( $tag_term ) use ( $assigned_ids ) {
				return ! isset( $assigned_ids[ (int) $tag_term->term_id ] );
			}
		)
	);
	if ( ! empty( $loose_tags ) ) {
		usort( $loose_tags, $np_sort_by_count );
		$tag_groups[] = array(
			'section' => null,
			'tags'    => $loose_tags,
		);
	}
	?>
<div class="np-taggroups">
	<p class="np-taggroups__stats">
		<?php
		printf(
			/* translators: 1: 标签总数，2: 被标签覆盖的文章篇数 */
			esc_html__( '共 %1$s 个版签 · 覆盖 %2$s 篇', 'paddysun-newsprint' ),
			esc_html( number_format_i18n( count( $tag_terms ) ) ),
			esc_html( number_format_i18n( $np_tagged_count ) )
		);
		?>
	</p>

	<?php foreach ( $tag_groups as $tag_group ) : ?>
		<section class="np-taggroup">
			<h2 class="np-taggroup__heading">
				<?php if ( $tag_group['section'] ) : ?>
					<a href="<?php echo esc_url( get_term_link( $tag_group['section'] ) ); ?>"><?php echo esc_html( $tag_group['section']->name ); ?></a>
				<?php else : ?>
					<?php esc_html_e( '散签', 'paddysun-newsprint' ); ?>
				<?php endif; ?>
			</h2>

			<ul class="np-taggroup__list">
				<?php foreach ( $tag_group['tags'] as $tag_term ) : ?>
					<li class="np-taggroup__item">
						<a class="np-chip" href="<?php echo esc_url( get_term_link( $tag_term ) ); ?>">#<?php echo esc_html( $tag_term->name ); ?></a>
						<span class="np-chip np-taggroup__count"><?php echo esc_html( number_format_i18n( (int) $tag_term->count ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endforeach; ?>
</div>
	<?php
endif;

$np_tags_idx_html = ob_get_clean();
set_transient(
	$np_tags_idx_key,
	array(
		'v'    => paddysun_ns_index_version(),
		'html' => $np_tags_idx_html,
	),
	10 * MINUTE_IN_SECONDS
);
echo $np_tags_idx_html;
