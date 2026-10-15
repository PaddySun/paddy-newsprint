<?php
/**
 * Title: 档案馆（全量文章索引）
 * Slug: paddysun-newsprint/blog-archive
 * Categories: paddysun-newsprint
 * Description: 档案馆页版面：栏目导览（分类网格）+ 热门版签与月度字数双栏 + 按年份分组的全量文章清单（年历，滚动懒渲染）；整段结果 transient 缓存，SQL 走批量聚合，多语言环境自动退回 WP API 取数。配「档案馆」页面模板（/blog）使用。
 *
 * @package Paddysun_Newsprint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 无标题文章兜底为「无题」（与其它版面同名函数共用，定义留守卫）。
if ( ! function_exists( 'paddysun_ns_entry_title' ) ) {
	function paddysun_ns_entry_title( $title ) {
		return '' === $title || null === $title ? __( '无题', 'paddysun-newsprint' ) : $title;
	}
}

// 结果层缓存：固定键 + 值内版本比对，版本推进原地覆盖（无孤儿行）；生产站有
// Redis Object Cache 时 transient 存于 Redis。键含 locale，切语言不串页。
$np_arch_key  = 'paddysun_ns_arch_' . sanitize_key( get_locale() );
$np_arch_data = get_transient( $np_arch_key );
if ( is_array( $np_arch_data ) && isset( $np_arch_data['v'], $np_arch_data['html'] )
	&& (int) $np_arch_data['v'] === paddysun_ns_index_version() ) {
	echo $np_arch_data['html'];
	return;
}

ob_start();

global $wpdb;

// 版签总目页入口：优先指向使用「版签总目」模板的页面。
$np_tags_page_id = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT p.ID FROM {$wpdb->posts} p
		 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_page_template' AND pm.meta_value = %s
		 WHERE p.post_type = 'page' AND p.post_status = 'publish'
		 ORDER BY p.ID ASC LIMIT 1",
		'template-tags'
	)
);
$tags_url = $np_tags_page_id ? get_permalink( $np_tags_page_id ) : home_url( '/tags/' );

/* ------------------------------------------------------------
 * 块一：栏目导览 —— 按文章数降序最多 16 个分类 + 最近更新时间。
 * ---------------------------------------------------------- */
$default_cat_id = (int) get_option( 'default_category' );
$blog_cats      = get_terms(
	array(
		'taxonomy'   => 'category',
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => 16,
	)
);
if ( is_wp_error( $blog_cats ) ) {
	$blog_cats = array();
}
$blog_cats = array_values(
	array_filter(
		$blog_cats,
		static function ( $cat ) use ( $default_cat_id ) {
			return (int) $cat->term_id !== $default_cat_id;
		}
	)
);

// 每个分类词的直接最新发文时间（含子分类在展示层归并，一条 GROUP BY 消 N+1）。
$np_cat_latest_raw = $wpdb->get_results(
	"SELECT tt.term_id AS tid, MAX(p.post_date) AS latest
	 FROM {$wpdb->term_relationships} tr
	 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'
	 INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_type = 'post' AND p.post_status = 'publish'
	 GROUP BY tt.term_id"
);
$np_term_latest = array();
foreach ( (array) $np_cat_latest_raw as $np_row ) {
	$np_term_latest[ (int) $np_row->tid ] = $np_row->latest;
}
$cat_latest = array();
foreach ( $blog_cats as $cat ) {
	$family_latest = array( $np_term_latest[ $cat->term_id ] ?? '' );
	foreach ( get_term_children( $cat->term_id, 'category' ) as $child_id ) {
		$family_latest[] = $np_term_latest[ $child_id ] ?? '';
	}
	$family_latest = array_filter( $family_latest );
	if ( $family_latest ) {
		$cat_latest[ $cat->term_id ] = mysql2date( 'Y-m-d', max( $family_latest ) );
	}
}

/* ------------------------------------------------------------
 * 块二左：热门版签（最多使用的前 8 个）。
 * ---------------------------------------------------------- */
$top_tags = get_terms(
	array(
		'taxonomy'   => 'post_tag',
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => 8,
	)
);
if ( is_wp_error( $top_tags ) ) {
	$top_tags = array();
}

/* ------------------------------------------------------------
 * 块二右 + 块三取数：单语言走直连 SQL；多语言（Polylang/WPML）退回
 * WP API 路径（插件经 filters 过滤 get_posts/get_the_terms，保证
 * 年历与月度统计按语言口径一致；性能由整段 transient 缓存兜底）。
 * ---------------------------------------------------------- */
$np_multilingual = paddysun_ns_is_multilingual();

// 年历条目统一为瘦行（stdClass：ID/post_title/post_name/post_date/post_date_gmt/post_author）。
$np_year_rows = array();
// 每篇文章的标签（pid => array( {tid,tname,tslug} …)，按名序）。
$np_tag_map = array();
// 月度统计（ym => {posts, chars}，近 24 个月，时间升序）。
$month_rows = array();

if ( $np_multilingual ) {
	$np_ml_posts = get_posts(
		array(
			'post_type'           => 'post',
			'posts_per_page'      => -1,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'post_status'         => 'publish',
		)
	);
	$np_month_agg = array();
	foreach ( $np_ml_posts as $np_ml_post ) {
		$np_year_rows[] = (object) array(
			'ID'            => (int) $np_ml_post->ID,
			'post_title'    => $np_ml_post->post_title,
			'post_name'     => $np_ml_post->post_name,
			'post_date'     => $np_ml_post->post_date,
			'post_date_gmt' => $np_ml_post->post_date_gmt,
			'post_author'   => (int) $np_ml_post->post_author,
		);
		$ml_tags = get_the_terms( $np_ml_post, 'post_tag' );
		if ( is_array( $ml_tags ) ) {
			usort(
				$ml_tags,
				static function ( $a, $b ) {
					return strcasecmp( $a->name, $b->name );
				}
			);
			$np_tag_map[ (int) $np_ml_post->ID ] = array_map(
				static function ( $tag ) {
					return (object) array(
						'tid'   => (int) $tag->term_id,
						'tname' => $tag->name,
						'tslug' => $tag->slug,
					);
				},
				$ml_tags
			);
		}
		$np_ym = mysql2date( 'Y-m', $np_ml_post->post_date );
		if ( ! isset( $np_month_agg[ $np_ym ] ) ) {
			$np_month_agg[ $np_ym ] = array(
				'posts' => 0,
				'chars' => 0,
			);
		}
		++$np_month_agg[ $np_ym ]['posts'];
		// 与 SQL 口径一致：CHAR_LENGTH 按字符计数。
		$np_month_agg[ $np_ym ]['chars'] += function_exists( 'mb_strlen' )
			? mb_strlen( $np_ml_post->post_content, 'UTF-8' )
			: strlen( $np_ml_post->post_content );
	}
	krsort( $np_month_agg );
	$np_month_agg = array_slice( $np_month_agg, 0, 24, true );
	ksort( $np_month_agg );
	foreach ( $np_month_agg as $np_ym => $np_agg ) {
		$month_rows[] = (object) array(
			'ym'    => $np_ym,
			'posts' => $np_agg['posts'],
			'chars' => $np_agg['chars'],
		);
	}
	unset( $np_ml_posts, $np_month_agg, $np_ym, $np_agg );
} else {
	// 注意：本查询刻意不走 $wpdb->prepare —— DATE_FORMAT 的 '%Y-%m' 若经
	// prepare 会被 vsprintf 当占位符吞掉。
	$np_month_raw = $wpdb->get_results(
		"SELECT DATE_FORMAT(post_date, '%Y-%m') AS ym, COUNT(ID) AS posts, SUM(CHAR_LENGTH(post_content)) AS chars
		 FROM {$wpdb->posts}
		 WHERE post_type = 'post' AND post_status = 'publish'
		 GROUP BY ym ORDER BY ym DESC LIMIT 24"
	);
	$np_month_raw = is_array( $np_month_raw ) ? array_reverse( $np_month_raw ) : array();
	foreach ( $np_month_raw as $np_row ) {
		$month_rows[] = (object) array(
			'ym'    => $np_row->ym,
			'posts' => (int) $np_row->posts,
			'chars' => (int) $np_row->chars,
		);
	}
	unset( $np_month_raw );

	// 年历瘦列：不捞正文；post_author 供 %author% 固定链接结构拼装真实作者段。
	$np_year_rows = $wpdb->get_results(
		"SELECT ID, post_title, post_name, post_date, post_date_gmt, post_author
		 FROM {$wpdb->posts}
		 WHERE post_type = 'post' AND post_status = 'publish'
		 ORDER BY post_date DESC"
	);
	$np_year_rows = is_array( $np_year_rows ) ? $np_year_rows : array();

	// 每篇文章的标签（全局按名排序，同一文章内即按名序）。
	$np_post_ids = wp_list_pluck( $np_year_rows, 'ID' );
	if ( $np_post_ids ) {
		$np_tag_rows = $wpdb->get_results(
			"SELECT tr.object_id AS pid, t.term_id AS tid, t.name AS tname, t.slug AS tslug
			 FROM {$wpdb->term_relationships} tr
			 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'post_tag'
			 INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
			 WHERE tr.object_id IN (" . implode( ',', array_map( 'intval', $np_post_ids ) ) . ")
			 ORDER BY t.name ASC"
		);
		foreach ( (array) $np_tag_rows as $np_tag_row ) {
			$np_tag_map[ (int) $np_tag_row->pid ][] = $np_tag_row;
		}
		unset( $np_tag_rows );
	}
}

$month_max = 0;
foreach ( $month_rows as $row ) {
	$month_max = max( $month_max, (int) $row->chars );
}

// 标签链接：用取数结果里的 slug 构造瘦词对象，避免逐词查库。
$np_tag_links = array();
foreach ( $np_tag_map as $np_tag_list ) {
	foreach ( $np_tag_list as $np_tag_row ) {
		$np_tag_id = (int) $np_tag_row->tid;
		if ( isset( $np_tag_links[ $np_tag_id ] ) ) {
			continue;
		}
		$np_tag_obj = new WP_Term(
			(object) array(
				'term_id'  => $np_tag_id,
				'name'     => $np_tag_row->tname,
				'slug'     => $np_tag_row->tslug,
				'taxonomy' => 'post_tag',
				'filter'   => 'raw',
			)
		);
		$np_tag_links[ $np_tag_id ] = get_term_link( $np_tag_obj );
	}
}

// 固定链接：瘦对象覆盖常规与 %author% 结构；%category% 段需要对象词表缓存，
// 预热一次完整文章缓存即可（多语言路径本身已走 WP API，直接按 ID 取）。
$np_permalink_structure = (string) get_option( 'permalink_structure' );
if ( $np_multilingual ) {
	$np_permalink_slim_ok = false;
} else {
	$np_permalink_slim_ok = ( false === strpos( $np_permalink_structure, '%category%' ) );
	if ( ! $np_permalink_slim_ok && $np_post_ids ) {
		_prime_post_caches( $np_post_ids, true, false );
	}
}

// 按年份归堆，保持时间倒序。
$blog_years = array();
foreach ( $np_year_rows as $np_year_row ) {
	$blog_year                  = mysql2date( 'Y', $np_year_row->post_date );
	$blog_years[ $blog_year ][] = $np_year_row;
}

$has_intro = ( ! empty( $blog_cats ) || ! empty( $top_tags ) || ! empty( $month_rows ) );

if ( empty( $blog_years ) && ! $has_intro ) :
	?>
<!-- wp:group {"className":"np-none","layout":{"type":"default"}} -->
<div class="wp-block-group np-none">
	<!-- wp:heading {"level":2} -->
	<h2 class="wp-block-heading"><?php esc_html_e( '本版暂无稿目', 'paddysun-newsprint' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph -->
	<p><?php esc_html_e( '还没有发表过文章，先去写一篇吧。', 'paddysun-newsprint' ); ?></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
	<?php
else :
	?>
<!-- wp:html -->
<div class="np-blogindex">

	<?php if ( ! empty( $blog_cats ) ) : ?>
		<section class="np-blogsec">
			<h2 class="np-blogsec__heading"><?php esc_html_e( '栏目导览', 'paddysun-newsprint' ); ?></h2>
			<ul class="np-blogcats">
				<?php foreach ( $blog_cats as $cat ) : ?>
					<li class="np-blogcat">
						<a class="np-blogcat__name" href="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a>
						<span class="np-blogcat__meta">
							<span class="np-blogcat__count">
								<?php
								printf(
									/* translators: %s: 该分类下的文章篇数 */
									esc_html__( '%s 篇', 'paddysun-newsprint' ),
									esc_html( number_format_i18n( (int) $cat->count ) )
								);
								?>
							</span>
							<?php if ( ! empty( $cat_latest[ $cat->term_id ] ) ) : ?>
								<span class="np-blogcat__latest"><?php printf( esc_html__( '最近 %s 更新', 'paddysun-newsprint' ), esc_html( $cat_latest[ $cat->term_id ] ) ); ?></span>
							<?php endif; ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $top_tags ) || ! empty( $month_rows ) ) : ?>
		<section class="np-blogsec np-blogduo">
			<?php if ( ! empty( $top_tags ) ) : ?>
				<div class="np-blogduo__col">
					<h2 class="np-blogsec__heading">
						<a href="<?php echo esc_url( $tags_url ); ?>"><?php esc_html_e( '热门版签', 'paddysun-newsprint' ); ?></a>
					</h2>
					<ul class="np-blogtoptags">
						<?php foreach ( $top_tags as $top_tag ) : ?>
							<li class="np-blogtoptags__item">
								<a class="np-chip" href="<?php echo esc_url( get_term_link( $top_tag ) ); ?>">#<?php echo esc_html( $top_tag->name ); ?></a>
								<span class="np-chip np-blogtoptags__count"><?php echo esc_html( number_format_i18n( (int) $top_tag->count ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
					<p class="np-blogsec__more"><a href="<?php echo esc_url( $tags_url ); ?>"><?php esc_html_e( '全部版签 →', 'paddysun-newsprint' ); ?></a></p>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $month_rows ) ) : ?>
				<div class="np-blogduo__col">
					<h2 class="np-blogsec__heading"><?php esc_html_e( '月度字数', 'paddysun-newsprint' ); ?></h2>
					<div class="np-blogchart" role="img" aria-label="<?php esc_attr_e( '近 24 个月每月更新字数条状图', 'paddysun-newsprint' ); ?>">
						<?php foreach ( $month_rows as $row ) : ?>
							<?php
							$bar_tip = $row->ym . ' · '
								. number_format_i18n( (int) $row->chars ) . ' ' . __( '字', 'paddysun-newsprint' )
								. ' / ' . number_format_i18n( (int) $row->posts ) . ' ' . __( '篇', 'paddysun-newsprint' );
							?>
							<span class="np-blogchart__bar" data-tip="<?php echo esc_attr( $bar_tip ); ?>" style="height: <?php echo esc_attr( $month_max ? max( 4, (int) round( (int) $row->chars / $month_max * 100 ) ) : 4 ); ?>%"></span>
						<?php endforeach; ?>
					</div>
					<p class="np-blogchart__caption">
						<?php
						$month_posts_total = 0;
						$month_chars_total = 0;
						foreach ( $month_rows as $row ) {
							$month_posts_total += (int) $row->posts;
							$month_chars_total += (int) $row->chars;
						}
						?>
						<span class="np-blogchart__seg np-blogchart__seg--name"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
						<span class="np-blogchart__sep" aria-hidden="true">/</span>
						<span class="np-blogchart__seg"><?php printf( esc_html__( '从 %1$s 到 %2$s', 'paddysun-newsprint' ), esc_html( $month_rows[0]->ym ), esc_html( $month_rows[ count( $month_rows ) - 1 ]->ym ) ); ?></span>
						<span class="np-blogchart__sep" aria-hidden="true">/</span>
						<span class="np-blogchart__seg"><?php printf( esc_html__( '更新了 %s 篇文章', 'paddysun-newsprint' ), esc_html( number_format_i18n( $month_posts_total ) ) ); ?></span>
						<span class="np-blogchart__sep" aria-hidden="true">/</span>
						<span class="np-blogchart__seg">
							<?php
							/* translators: %s: 字数 */
							printf( esc_html__( '共计 %s 字', 'paddysun-newsprint' ), esc_html( number_format_i18n( $month_chars_total ) ) );
							?>
						</span>
					</p>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php foreach ( $blog_years as $blog_year => $np_year_items ) : ?>
		<section class="np-blogyear">
			<header class="np-blogyear__head">
				<h2 class="np-blogyear__title"><?php echo esc_html( $blog_year ); ?></h2>
				<span class="np-blogyear__count">
					<?php
					printf(
						/* translators: %s: 当年文章篇数 */
						esc_html__( '共 %s 篇', 'paddysun-newsprint' ),
						esc_html( number_format_i18n( count( $np_year_items ) ) )
					);
					?>
				</span>
			</header>

			<ol class="np-blogyear__list">
				<?php foreach ( $np_year_items as $np_item ) : ?>
					<?php
					if ( $np_multilingual ) {
						$np_item_href = get_permalink( (int) $np_item->ID );
					} else {
						$np_post_obj = new WP_Post(
							(object) array(
								'ID'            => (int) $np_item->ID,
								'post_title'    => $np_item->post_title,
								'post_name'     => $np_item->post_name,
								'post_date'     => $np_item->post_date,
								'post_date_gmt' => $np_item->post_date_gmt,
								'post_author'   => (int) $np_item->post_author,
								'post_type'     => 'post',
								'post_status'   => 'publish',
								'filter'        => 'raw',
							)
						);
						$np_item_href = get_permalink( $np_post_obj );
					}
					$np_item_tags = isset( $np_tag_map[ (int) $np_item->ID ] ) ? array_slice( $np_tag_map[ (int) $np_item->ID ], 0, 3 ) : array();
					$np_item_date = mysql2date( 'Y-m-d', $np_item->post_date );
					?>
					<li class="np-blogitem">
						<a class="np-blogitem__title" href="<?php echo esc_url( $np_item_href ); ?>"><?php echo esc_html( paddysun_ns_entry_title( $np_item->post_title ) ); ?></a>
						<span class="np-blogitem__meta">
							<time class="np-blogitem__date" datetime="<?php echo esc_attr( $np_item_date ); ?>"><?php echo esc_html( $np_item_date ); ?></time>
							<?php if ( ! empty( $np_item_tags ) ) : ?>
								<span class="np-blogitem__tags">
									<?php foreach ( $np_item_tags as $np_item_tag ) : ?>
										<a class="np-chip" href="<?php echo esc_url( $np_tag_links[ (int) $np_item_tag->tid ] ); ?>">#<?php echo esc_html( $np_item_tag->tname ); ?></a>
									<?php endforeach; ?>
								</span>
							<?php endif; ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>
	<?php endforeach; ?>
</div>
	<?php
endif;
?>
<!-- /wp:html -->
<?php
$np_arch_html = ob_get_clean();
set_transient(
	$np_arch_key,
	array(
		'v'    => paddysun_ns_index_version(),
		'html' => $np_arch_html,
	),
	10 * MINUTE_IN_SECONDS
);
echo $np_arch_html;
