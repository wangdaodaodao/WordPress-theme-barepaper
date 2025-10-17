# Barepaper - 极简WordPress主题

<div align="center">
  <h2 align="center">
    Barepaper
  </h2>
  <p align="center">专 注 于 内 容 的 极 简 WordPress 主 题
  </p>

  [演示](https://wdd.pp.ua/blog/) | [文档](https://github.com/wangdaodaodao/WordPress-theme-barepaper#readme)

  ![Theme Version](https://img.shields.io/badge/version-2.0.0-blue.svg)
  ![WordPress](https://img.shields.io/badge/wordpress-5.0+-blue.svg)
  ![PHP](https://img.shields.io/badge/php-7.4+-blue.svg)
  ![License](https://img.shields.io/badge/license-GPL--2.0+-green.svg)

</div>

一个**极简至上**的WordPress主题，专注于内容本身，没有花哨的动画效果，没有复杂的交互功能。
采用现代化的极简设计理念，让文字和图片成为视觉焦点，提供纯净、快速、高质量的阅读体验。
内置Markdown支持、广告系统、友情链接等实用功能，开箱即用，无需复杂配置。

## ✨ 特性

### 🚀 **开箱即用，无需复杂配置**
- **一键安装** - 下载即用，无需额外插件或复杂设置
- **智能默认** - 所有功能开箱即用，预设最佳配置
- **自动优化** - 内置性能优化，页面加载速度更快

### 📝 **自带好用的Markdown支持**
- **原生解析** - 无需额外插件，直接支持Markdown语法
- **代码展示** - 支持多种代码块样式展示
- **即时预览** - 编辑器实时预览Markdown效果
- **语法扩展** - 支持表格、任务列表、脚注等高级语法

### 🎨 **极简设计，专注内容**
- **简洁界面** - 去除冗余元素，让内容成为焦点
- **响应式布局** - 完美适配桌面、平板、手机等设备
- **深色模式** - 支持自动切换深色/浅色主题
- **现代化UI** - 使用CSS变量和Flexbox布局

### 📢 **强大的广告系统**
- **多位置广告** - 支持顶部、底部、侧边栏广告位
- **智能加载** - 按需加载广告，提升页面性能
- **样式优化** - 广告样式美观，与主题完美融合
- **代码示例** - 内置完整的广告代码示例

### 🔗 **友情链接管理系统**
- **可视化管理** - 后台一键添加、编辑、删除友情链接
- **自动排序** - 支持自定义排序和分组
- **样式美观** - 多种显示样式可选
- **SEO友好** - 自动添加rel属性和title属性

### 📊 **丰富的博客排行模块**
- **阅读排行** - 自动统计和展示热门文章
- **点赞排行** - 基于用户互动的热门内容
- **评论排行** - 展示讨论最热烈的文章
- **随机文章** - 发现更多优质内容
- **标签云** - 可视化展示热门标签
- **归档列表** - 按时间线浏览历史文章

### 🎵 **多媒体内容支持**
- **音乐播放器** - 集成APlayer，支持网易云、QQ音乐等平台
- **视频播放器** - 原生HTML5视频播放，支持多种格式
- **音频支持** - 支持多种音频格式播放
- **媒体优化** - 自动压缩和懒加载，提升加载速度

### ⚡ **性能与安全**
- **SEO优化** - 完整的结构化数据和元标签
- **性能优化** - 代码分割、缓存策略、资源压缩
- **安全加固** - 输入过滤、XSS防护、CSRF保护
- **快速加载** - 优化资源加载顺序，提升用户体验

## 🚀 快速开始

### 安装方法

1. **下载主题**
   ```bash
   git clone https://github.com/yourusername/barepaper.git
   ```

2. **上传到WordPress**
   - 将 `barepaper` 文件夹上传到 `wp-content/themes/` 目录
   - 或者直接在WordPress后台上传ZIP文件

3. **激活主题**
   - 进入WordPress后台 `外观 > 主题`
   - 找到 "Barepaper" 主题并激活

4. **基础配置**
   - 进入 `外观 > Barepaper主题设置`
   - 配置基本信息和功能选项

## 📖 使用指南

### Markdown语法

主题原生支持Markdown语法，无需额外插件：

```markdown
# 一级标题
## 二级标题

**粗体文本** *斜体文本*

- 无序列表
1. 有序列表

[链接文本](URL)
![图片描述](图片URL)

```javascript
// 代码块
function hello() {
  console.log("Hello World!");
}
```

> 引用文本
```

### 短代码使用

#### 警告框
```php
[alert type="success" title="成功"]操作成功完成[/alert]
[alert type="error" title="错误"]操作失败[/alert]
[alert type="warning" title="警告"]请注意事项[/alert]
[alert type="info" title="信息"]重要提示[/alert]
```

#### 音乐播放器
```php
[music server="netease" id="123456"]网易云音乐[/music]
[music server="qq" id="123456"]QQ音乐[/music]
```

#### 视频播放器
```php
[video src="video.mp4" poster="poster.jpg"]视频标题[/video]
```

#### 按钮和引用
```php
[button url="https://wdd.pp.ua/blog/" color="primary"]点击访问[/button]
[quote author="王导导"]引用内容[/quote]
```

### 主题设置

进入 `外观 > Barepaper主题设置` 可以配置：

- **模块设置** - 控制侧边栏模块的显示/隐藏
- **效果设置** - 开启背景特效、鼠标特效等
- **编辑器设置** - 配置Markdown和经典编辑器
- **广告设置** - 配置顶部、底部、侧边栏广告



## 🔧 技术栈

- **前端框架** - 原生CSS + JavaScript
- **CSS预处理** - 使用CSS变量和现代特性
- **JavaScript库** - jQuery + 原生ES6+
- **图标字体** - Font Awesome / Glyphicons
- **代码展示** - 原生代码块样式
- **音乐播放器** - APlayer + MetingJS

## 📁 文件结构

```
barepaper/
├── css/                    # 样式文件
│   ├── style.css          # 主样式文件
│   ├── editor.css         # 编辑器样式
│   └── components.css     # 组件样式
├── js/                     # JavaScript文件
│   ├── admin-settings.js  # 后台设置脚本
│   ├── sponsor-qr.js      # 赞助二维码脚本
│   ├── recommendation.js  # 点赞功能脚本
│   └── view-counter.js    # 阅读统计脚本
├── template-parts/         # 模板部件
│   ├── article-meta.php   # 文章元数据
│   └── *.php             # 其他部件文件
├── functions.php          # 主题功能文件
├── index.php             # 首页模板
├── single.php            # 文章页模板
├── sidebar.php           # 侧边栏模板
├── header.php            # 头部模板
├── footer.php            # 底部模板
├── style.css             # 主题信息
├── screenshot.png        # 主题截图
└── README.md             # 说明文档
```


https://wdd.pp.ua/blog/
## 🤝 贡献

如果你在使用中发现 bug 可以通过 [Issue](https://wdd.pp.ua/blog/) 进行反馈，我会及时关注。

## 📸 案例

你可以在 [演示站点](https://wdd.pp.ua/blog/) 中看到正在使用该主题的博客。
同时，如果你在使用该主题，并且希望展示给大家，可以在 [这里](https://wdd.pp.ua/blog/) 提交申请，我会不定时更新。

## 💝 赞赏

如果您觉得该主题做的还不错，想对我微小的工作一点激励，欢迎赞赏支持。

<div align="center">

![赞赏二维码](./template-parts/wechat-qr.jpg)

</div>

## 📄 许可证

本项目采用 GPL-2.0+ 许可证 - 查看 [LICENSE](LICENSE) 文件了解详情


---

**Made with ❤️ by [王导导]**

如果这个主题对你有帮助，请给个 ⭐ Star 支持一下！
