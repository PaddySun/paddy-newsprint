![Paddy Newsprint 品牌 Logo](https://cos.paddysun.top/brd/paddy-newsprint-logo-orange.png)

# Paddy Newsprint

![版本 0.10.12](https://img.shields.io/badge/version-0.10.12-6b1f1f)
![WordPress 7.1+](https://img.shields.io/badge/WordPress-7.1%2B-21759b?logo=wordpress&logoColor=white)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)
![许可证 GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)

报纸风格的中文 WordPress 块主题：完整头版、双耳报头、三栏内容与适合长文的阅读排印。主题自身的字体、图标、图片回退和脚本本地加载；这份 README 的品牌图片与 Shields.io 徽章是公开文档外部资源，不会随主题注入站点。

## 下载与安装

- [GitHub 仓库](https://github.com/PaddySun/paddy-newsprint)
- 国内下载预留地址：`https://cos.paddysun.top/paddy-newsprint/rel/paddy-newsprint-0.10.12.zip`。COS 由站长上传；上传前此地址不代表文件已可用。
- 安装包文件名：`paddy-newsprint-0.10.12.zip`；包内目录：`paddysun-newsprint/`。
- SHA-256 与本地安装验证结果将在候选生成后记录于本仓库 `RELEASE-0.10.12.md`。不要将 GitHub 的仓库源码 ZIP 当作可直接安装的主题包。

在 WordPress 后台选择「外观 → 主题 → 上传主题」，安装并启用主题。升级前备份自己的站点；数据库中已定制的模板不会因主题文件更新自动覆盖。

## 主要功能

- **报纸版面**：工具条、完整／紧凑报头、双耳、简报、长读、侧栏及本期要目。
- **长文阅读**：本地衬线字体、引用、脚注、表格、目录、代码与图表展开／折叠。
- **公式与图表**：按内容加载 KaTeX、Mermaid、highlight.js，失败时保留可读回退。
- **媒体回退**：失效图片／视频替换为本地作品图，保留作品名、alt 和提示，不新增“作品介绍”外链。
- **Markdown**：原文存档、复制和 REST 单篇导出，保留权限、nonce、导出开关与可见性边界。
- **机器可读内容**：`/llms.txt` 索引、`/llms-full.txt` 全文、SEO/schema 输出与既有 SEO 插件让位机制。

SEO/schema、Markdown 存档与 REST、llms 是保留的产品功能，不需要另装强制插件。本轮为自主分发，不是 WordPress.org 上架版本或官方审核通过声明。

## 0.10.12 更新

- 简报采用最多 64 个汉字／词单位及 192 字符双预算，只调整渲染，不改保存正文与摘要。
- 超长连续拉丁站名使用局部字号约束，不截断站名，不使用全局 `break-all`。
- 两个阅读入口统一排印；媒体回退不再提供作品介绍按钮。
- 补入站长已验收的主题预览图，修正公开作者链接。
- 补齐实际第三方版权及许可证通知，核验字体来源与 roughjs 锁定依赖，强化公开构建和哈希校验。

## 兼容性与验证边界

WordPress **7.1+**，PHP **7.4+**。真实 PHP 7.4.33 与 8.3.30 的语法、核心摘要渲染及指定业务边界已验证；这不表示所有插件组合均已测试。站长已确认极长／普通标题、简报、阅读入口和媒体回退效果。

仓库中的导航、备案、服务状态及按钮示例是占位内容，安装后请按站点情况编辑。主题不导入个人数据库或媒体。

## 许可证与第三方来源

主题原声明保持 **GPL-2.0-or-later**。0.10.12 对 GPL 覆盖部分行使 **GPLv3 选项**；不是 GPLv3-only。DOMPurify 采用其 **Apache-2.0 分支**，原双许可全文仍保留。

- [分发许可路线](paddysun-newsprint/DISTRIBUTION-LICENSE.md)
- [GPLv3 全文](paddysun-newsprint/LICENSE-GPL-3.0.txt)
- [组件及文件哈希清单](paddysun-newsprint/assets/resource-licenses.json)
- [第三方通知目录](paddysun-newsprint/assets/vendor/licenses/)

`points-on-curve` 0.2.0 按上游完整包的 **MIT** 许可分发，完整许可已保留。其 `flatness` 函数注明适配自 [Offset Bézier Curves](https://seant23.wordpress.com/2010/11/12/offset-bezier-curves/)；在此保留来源说明，不将该网页描述成另一份已取得的许可，也不修改上游 MIT 声明。

## 作者

![Paddy 个人 88×31](https://cos.paddysun.top/88x31/paddy-88x31-clouds.webp)

[Paddy](https://www.paddysun.top/about-us) · [主题展示页](https://www.paddysun.top/paddy-newsprint)
