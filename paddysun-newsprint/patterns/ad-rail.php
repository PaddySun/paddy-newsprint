<?php
/**
 * Title: 广告位 · 文章页侧栏（方形，桌面端专属）
 * Slug: paddysun-newsprint/ad-rail
 * Categories: paddysun-newsprint
 * Description: 仅桌面端（≥1240px）固定于右栏：有悬浮目录时贴其下方（整体较目录右移），未开启时替代目录位；手机/平板不输出，页末位由「读者来信上方」槽位承担。出厂占位，后台填码后输出受信代码。
 *
 * @package Paddysun_Newsprint
 */

?>
<!-- wp:html -->
<?php echo paddysun_ns_ad_slot( 'article' ); ?>
<!-- /wp:html -->
