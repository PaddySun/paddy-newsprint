<?php
/**
 * Title: 广告位 · 首页横幅
 * Slug: paddysun-newsprint/ad-band
 * Categories: paddysun-newsprint
 * Description: 首页横幅广告位（订阅栏与本期要目之间）：出厂占位，后台「外观 → 广告位」填码后输出受信代码。未启用槽位时整块不输出。
 *
 * @package Paddysun_Newsprint
 */

?>
<!-- wp:html -->
<?php echo paddysun_ns_ad_slot( 'home' ); ?>
<!-- /wp:html -->
