# BarePaper

[English](#english) | [中文](#中文)

---

## 中文

### 简介

BarePaper 是一个简洁、高性能的 WordPress 主题，专为个人博客和内容创作者设计。主题采用极简设计理念，注重阅读体验和性能优化，提供丰富的自定义选项和实用功能。

### 主要特性

#### 🎨 外观与体验
- **响应式设计** - 完美适配桌面、平板和移动设备
- **深色/浅色主题** - 支持自动跟随系统或手动切换
- **自定义品牌** - 支持自定义博客标题、Logo 和副标题
- **灵活布局** - 经典的侧边栏布局，清晰的内容层次

#### ✍️ 编辑器增强
- **Markdown 支持** - 完整的 Markdown 语法解析
- **短代码系统** - 丰富的短代码功能（音乐、视频、代码块等）
- **经典编辑器** - 优化的经典编辑器体验
- **代码高亮** - 支持多种编程语言的语法高亮

#### 📊 统计与分析
- **实时在线统计** - 显示当前在线人数和历史最高记录
- **访问统计** - 记录总访问数(PV)和总阅读量
- **运行时间** - 自动计算并显示网站运行天数
- **管理员在线** - 追踪管理员在线状态和总在线时长

#### 🚀 性能优化
- **智能缓存** - 多级缓存系统，提升页面加载速度
- **资源优化** - CSS/JS 延迟加载和合并
- **数据库优化** - 优化查询，减少数据库负载
- **图片优化** - 响应式图片和懒加载支持

#### 🎯 内容功能
- **文章推荐** - 点赞和推荐文章功能
- **置顶文章** - 支持文章置顶显示
- **友情链接** - 内置友情链接管理
- **评论增强** - User Agent 显示，展示评论者设备信息
- **赞助模块** - 可自定义微信/支付宝收款码

#### ⚙️ 高级设置
- **模块化设计** - 所有功能模块化，按需加载
- **自定义代码** - 支持自定义统计代码和页脚 HTML
- **广告管理** - 多位置广告代码管理
- **后台优化** - 精简后台界面，移除无用功能

### 系统要求

- WordPress 5.0 或更高版本
- PHP 7.4 或更高版本
- MySQL 5.6 或更高版本

### 安装方法

1. 下载主题压缩包
2. 登录 WordPress 后台
3. 进入 `外观` → `主题` → `添加`
4. 点击 `上传主题`，选择下载的压缩包
5. 安装完成后点击 `启用`

### 快速配置

#### 基础设置
1. 进入 `外观` → `BarePaper 主题设置`
2. 在 `效果设置` 中配置：
   - 博客标题和副标题
   - 主题模式（浅色/深色/自动）
   - 建站时间（用于计算运行天数）

#### 模块设置
在 `模块设置` 中启用/禁用侧边栏模块：
- 阅读排行榜
- 点赞排行榜
- 最新文章
- 标签云
- 搜索模块
- 友情链接

#### 编辑器设置
1. 启用经典编辑器（推荐）
2. 启用 Markdown 和 shortcode 语法支持
3. 可选：禁用 Emoji 功能以提升性能

### 主要功能说明

#### 赞助模块
在 `效果设置` 中配置：
1. 启用赞助模块
2. 设置微信公众号/微信二维码 URL
3. 设置支付宝收款码 URL

赞助二维码将显示在文章底部。

#### 统计功能
主题自动统计以下数据：
- 网站运行时间
- 总访问数 (PV)
- 总阅读量
- 当前在线人数
- 管理员在线状态

统计信息默认显示在页脚，可通过自定义页脚 HTML 替换。

#### 友情链接
1. 进入 `友情链接` 设置页
2. 添加链接名称和 URL
3. 在 `模块设置` 中启用 `显示友情链接`

### 目录结构

```
BarePaper/
├── core/              # 核心功能
│   ├── admin.php      # 后台设置
│   ├── assets.php     # 资源加载
│   └── setup.php      # 主题初始化
├── features/          # 功能模块
│   ├── stats.php      # 统计功能
│   ├── performance.php # 性能优化
│   ├── wddmd.php      # Markdown 解析
│   └── ...
├── css/               # 样式文件
├── js/                # JavaScript 文件
├── images/            # 图片资源
├── comments.php       # 评论模板
├── footer.php         # 页脚模板
├── header.php         # 页眉模板
├── index.php          # 首页模板
├── sidebar.php        # 侧边栏模板
├── single.php         # 文章页模板
└── functions.php      # 主题函数
```

### 常见问题

**Q: 如何修改建站时间？**  
A: 进入 `效果设置`，在 `建站时间` 字段填写日期，格式为 `YYYY-MM-DD`（如 2024-01-01）。留空则自动使用第一篇文章的发布时间。

**Q: 如何自定义页脚内容？**  
A: 在 `效果设置` 的 `页脚HTML代码` 中输入自定义内容。留空则显示默认的统计信息。

**Q: 主题支持哪些短代码？**  
A: 主题支持音乐、视频、代码块、图片等多种短代码。在编辑器中点击 `shortcode语法` 按钮查看完整列表。

**Q: 如何禁用某些功能模块？**  
A: 进入 `模块设置`，取消勾选不需要的模块即可。

### 更新日志

#### v3.1.0 (2025-12-04)

- 代码清理和性能优化

### 技术支持

- **GitHub**: [https://github.com/wangdaodaodao/WordPress-theme-barepaper](https://github.com/wangdaodaodao/WordPress-theme-barepaper)
- **文档**: [https://blog.062200.xyz/2025/wordpress-theme-barepaper/](https://blog.062200.xyz/2025/wordpress-theme-barepaper/)
- **问题反馈**: [GitHub Issues](https://github.com/wangdaodaodao/WordPress-theme-barepaper/issues)

### 开源协议

本主题采用开源协议发布，欢迎使用、修改和分享。

### 致谢

感谢所有为这个项目做出贡献的开发者和用户。

---

## English

### Introduction

BarePaper is a clean, high-performance WordPress theme designed for personal blogs and content creators. The theme embraces minimalist design principles, focusing on reading experience and performance optimization while providing rich customization options and practical features.

### Key Features

#### 🎨 Appearance & Experience
- **Responsive Design** - Perfect adaptation for desktop, tablet, and mobile devices
- **Dark/Light Theme** - Auto-follow system or manual switching
- **Custom Branding** - Support for custom blog title, logo, and subtitle
- **Flexible Layout** - Classic sidebar layout with clear content hierarchy

#### ✍️ Editor Enhancements
- **Markdown Support** - Complete Markdown syntax parsing
- **Shortcode System** - Rich shortcode features (music, video, code blocks, etc.)
- **Classic Editor** - Optimized classic editor experience
- **Code Highlighting** - Syntax highlighting for multiple programming languages

#### 📊 Statistics & Analytics
- **Real-time Online Stats** - Display current online users and historical peak
- **Visit Statistics** - Track total visits (PV) and total views
- **Runtime** - Auto-calculate and display website running days
- **Admin Online** - Track admin online status and total online time

#### 🚀 Performance Optimization
- **Smart Caching** - Multi-level caching system for faster page loading
- **Resource Optimization** - CSS/JS lazy loading and merging
- **Database Optimization** - Optimized queries to reduce database load
- **Image Optimization** - Responsive images and lazy loading support

#### 🎯 Content Features
- **Post Recommendations** - Like and recommend post features
- **Sticky Posts** - Support for sticky post display
- **Friend Links** - Built-in friend links management
- **Enhanced Comments** - User Agent display showing commenter device info
- **Sponsor Module** - Customizable WeChat/Alipay QR codes

#### ⚙️ Advanced Settings
- **Modular Design** - All features are modular and load on demand
- **Custom Code** - Support for custom statistics code and footer HTML
- **Ad Management** - Multi-position ad code management
- **Backend Optimization** - Streamlined admin interface, removed unnecessary features

### System Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

### Installation

1. Download the theme package
2. Log in to WordPress admin panel
3. Go to `Appearance` → `Themes` → `Add New`
4. Click `Upload Theme`, select the downloaded package
5. Click `Activate` after installation

### Quick Setup

#### Basic Settings
1. Go to `Appearance` → `BarePaper Theme Settings`
2. Configure in `Effects Settings`:
   - Blog title and subtitle
   - Theme mode (light/dark/auto)
   - Site start date (for calculating running days)

#### Module Settings
Enable/disable sidebar modules in `Module Settings`:
- Reading rankings
- Like rankings
- Recent posts
- Tag cloud
- Search module
- Friend links

#### Editor Settings
1. Enable classic editor (recommended)
2. Enable Markdown and shortcode syntax support
3. Optional: Disable Emoji feature for better performance

### Feature Guide

#### Sponsor Module
Configure in `Effects Settings`:
1. Enable sponsor module
2. Set WeChat public account/WeChat QR code URL
3. Set Alipay QR code URL

Sponsor QR codes will be displayed at the bottom of posts.

#### Statistics Features
The theme automatically tracks:
- Website running time
- Total visits (PV)
- Total views
- Current online users
- Admin online status

Statistics are displayed in the footer by default and can be replaced with custom footer HTML.

#### Friend Links
1. Go to `Friend Links` settings page
2. Add link name and URL
3. Enable `Show Friend Links` in `Module Settings`

### Directory Structure

```
BarePaper/
├── core/              # Core functions
│   ├── admin.php      # Admin settings
│   ├── assets.php     # Asset loading
│   └── setup.php      # Theme initialization
├── features/          # Feature modules
│   ├── stats.php      # Statistics
│   ├── performance.php # Performance optimization
│   ├── wddmd.php      # Markdown parser
│   └── ...
├── css/               # Stylesheets
├── js/                # JavaScript files
├── images/            # Image assets
├── comments.php       # Comments template
├── footer.php         # Footer template
├── header.php         # Header template
├── index.php          # Index template
├── sidebar.php        # Sidebar template
├── single.php         # Single post template
└── functions.php      # Theme functions
```

### FAQ

**Q: How to modify the site start date?**  
A: Go to `Effects Settings`, fill in the date in `Site Start Date` field, format: `YYYY-MM-DD` (e.g., 2024-01-01). Leave blank to auto-use the first post's publish date.

**Q: How to customize footer content?**  
A: Enter custom content in `Footer HTML Code` under `Effects Settings`. Leave blank to show default statistics.

**Q: What shortcodes does the theme support?**  
A: The theme supports various shortcodes for music, video, code blocks, images, etc. Click the `shortcode syntax` button in the editor to view the complete list.

**Q: How to disable certain feature modules?**  
A: Go to `Module Settings` and uncheck the modules you don't need.

### Changelog

#### v3.1.0 (2025-12-04)

- Code cleanup and performance optimization

### Support

- **GitHub**: [https://github.com/wangdaodaodao/WordPress-theme-barepaper](https://github.com/wangdaodaodao/WordPress-theme-barepaper)
- **Documentation**: [https://blog.062200.xyz/2025/wordpress-theme-barepaper/](https://blog.062200.xyz/2025/wordpress-theme-barepaper/)
- **Issue Tracker**: [GitHub Issues](https://github.com/wangdaodaodao/WordPress-theme-barepaper/issues)

### License

This theme is released under an open-source license. Feel free to use, modify, and share.

### Acknowledgments

Thanks to all developers and users who have contributed to this project.
