/**
 * 编辑器核心模块
 * 提供基础编辑器功能、Modal窗口、Markdown帮助、短代码帮助等功能
 *
 * 功能：
 * - 编辑器就绪状态检查和内容操作
 * - Modal窗口系统（显示/隐藏/状态管理）
 * - Markdown语法帮助界面
 * - 短代码快速插入界面
 * - 编辑器增强功能初始化
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        if (typeof QTags === 'undefined') {
            console.warn('QTags not loaded');
            return;
        }

        // 初始化编辑器增强功能
        initEditorEnhancements();
    });

    // 基础编辑器工具函数
    window.EditorCore = {
        isEditorReady: function () {
            return typeof QTags !== 'undefined';
        },

        insertContent: function (content) {
            if (typeof QTags !== 'undefined') {
                QTags.insertContent(content);
                return true;
            }
            return false;
        },

        getContent: function () {
            // 经典编辑器
            const classic = $('#content');
            if (classic.length) return classic.val();

            // TinyMCE编辑器
            if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor) {
                return tinyMCE.activeEditor.getContent({ format: 'raw' });
            }

            return '';
        },

        showMessage: function (message, type) {
            console.log('[' + (type || 'info').toUpperCase() + '] ' + message);
        }
    };

    // Modal管理器
    window.EditorModal = {
        show: function (modalId, title, content) {
            let modal = $('#' + modalId);
            if (modal.length === 0) {
                const modalHtml = `
                    <div id="${modalId}" class="editor-modal-wrapper">
                        <div class="editor-modal-content">
                            <h3>${title}</h3>
                            <div class="editor-modal-body">${content}</div>
                            <button class="editor-modal-close" title="关闭">&times;</button>
                        </div>
                    </div>
                `;
                $('body').append(modalHtml);
                modal = $('#' + modalId);
            } else {
                modal.find('h3').text(title);
                modal.find('.editor-modal-body').html(content);
            }
            modal.fadeIn(200);
        },

        hide: function (modalId) {
            $('#' + modalId).fadeOut(200);
        },

        showStatus: function (modalId, message, type = 'info') {
            const modal = $('#' + modalId);
            let statusDiv = modal.find('.editor-status-message');
            if (statusDiv.length === 0) {
                statusDiv = $('<div class="editor-status-message"></div>').prependTo(modal.find('.editor-modal-body'));
            }
            statusDiv.removeClass('loading success error info').addClass(type).html(`<span>${message}</span>`).show();
        }
    };

    // Markdown帮助Modal内容
    const markdownModalContent = `
        <div class="editor-markdown-container">
            <div class="editor-markdown-grid editor-markdown-two-column">
                <div class="editor-markdown-column">
                    <div class="editor-markdown-section">
                        <h4>📝 标题</h4>
                        <pre><code># 一级标题
## 二级标题
### 三级标题
#### 四级标题</code></pre>
                    </div>

                    <div class="editor-markdown-section">
                        <h4>✨ 文本格式</h4>
                        <pre><code>**粗体文本**
*斜体文本*
~~删除线~~
==高亮==</code></pre>
                    </div>

                    <div class="editor-markdown-section">
                        <h4>📋 列表</h4>
                        <pre><code>- 无序列表
  - 子列表

1. 有序列表
2. 有序列表

- [ ] 任务
- [x] 完成</code></pre>
                    </div>
                    
                    <div class="editor-markdown-section">
                        <h4>➖ 分隔线</h4>
                        <pre><code>---
***</code></pre>
                    </div>
                </div>

                <div class="editor-markdown-column">
                    <div class="editor-markdown-section">
                        <h4>🔗 链接和图片</h4>
                        <pre><code>[链接](https://url.com)
![图片](https://img.jpg)</code></pre>
                    </div>

                    <div class="editor-markdown-section">
                        <h4>💬 引用和代码</h4>
                        <pre><code>> 引用文本

\`行内代码\`

\`\`\`js
// 代码块
console.log('Hi');
\`\`\`</code></pre>
                    </div>

                    <div class="editor-markdown-section">
                        <h4>📊 表格</h4>
                        <pre><code>| 标题1 | 标题2 |
|-------|-------|
| 内容1 | 内容2 |</code></pre>
                    </div>
                </div>
            </div>
        </div>
    `;

    // 短代码帮助Modal内容
    const shortcodesModalContent = `
        <div class="editor-shortcodes-container">
            <div class="editor-shortcodes-grid">
                <div class="editor-shortcodes-section">
                    <h4>💬 提示框</h4>
                    <div class="editor-shortcodes-buttons">
                        <button class="editor-shortcode-btn button" data-shortcode='[alert type="success" title="成功"]这是成功提示[/alert]'>成功</button>
                        <button class="editor-shortcode-btn button" data-shortcode='[alert type="warning" title="警告"]这是警告信息[/alert]'>警告</button>
                        <button class="editor-shortcode-btn button" data-shortcode='[alert type="error" title="错误"]这是错误提示[/alert]'>错误</button>
                        <button class="editor-shortcode-btn button" data-shortcode='[alert type="info" title="信息"]这是信息提示[/alert]'>信息</button>
                    </div>
                </div>

                <div class="editor-shortcodes-section">
                    <h4>📝 引用</h4>
                    <div class="editor-shortcodes-buttons">
                        <button class="editor-shortcode-btn button" data-shortcode='[quote author="作者名"]引用内容[/quote]'>插入引用</button>
                    </div>
                </div>

                <div class="editor-shortcodes-section">
                    <h4>🔘 按钮 (支持多种颜色)</h4>
                    <div class="editor-shortcodes-buttons">
                        <button class="editor-shortcode-btn button" data-shortcode='[button url="#" color="primary"]主要按钮[/button]'>主要按钮</button>
                        <button class="editor-shortcode-btn button" data-shortcode='[button url="#" color="secondary"]次要按钮[/button]'>次要按钮</button>
                        <button class="editor-shortcode-btn button" data-shortcode='[button url="#" color="success"]成功按钮[/button]'>成功按钮</button>
                        <button class="editor-shortcode-btn button" data-shortcode='[button url="#" color="warning"]警告按钮[/button]'>警告按钮</button>
                        <button class="editor-shortcode-btn button" data-shortcode='[button url="#" color="danger"]危险按钮[/button]'>危险按钮</button>
                    </div>
                    <div class="editor-shortcodes-note" style="margin-top: 8px; font-size: 12px; color: #666;">
                        支持颜色: primary(蓝), secondary(灰), success(绿), warning(橙), danger(红)
                    </div>
                </div>

                <div class="editor-shortcodes-section">
                    <h4>🎵 音乐播放器</h4>
                    <div class="editor-shortcodes-buttons">
                        <button class="editor-shortcode-btn button" data-shortcode='[music id="请替换ID" server="netease" type="song"]'>网易云单曲</button>
                        <button class="editor-shortcode-btn button" data-shortcode='[music id="请替换歌单ID" server="netease" type="playlist"]'>网易云歌单</button>
                        <button class="editor-shortcode-btn button" data-shortcode='[music id="请替换ID" server="qq" type="song"]'>QQ音乐单曲</button>
                        <button class="editor-shortcode-btn button" data-shortcode='[music url="#" name="#" artist="#" type="song"]'>自定义单曲</button>
                        <button class="editor-shortcode-btn button" data-shortcode='[music url="#" name="#" artist="#" type="playlist" playlist="歌单名"]'>自定义歌单</button>
                    </div>
                </div>

                <div class="editor-shortcodes-section">
                    <h4>🎬 视频播放器</h4>
                    <div class="editor-shortcodes-buttons">
                        <button class="editor-shortcode-btn button" data-shortcode='[video src="#"][/video]'>插入视频</button>
                    </div>
                </div>

                <div class="editor-shortcodes-section">
                    <h4>🤖 AI 功能</h4>
                    <div class="editor-shortcodes-buttons">
                        <button class="editor-shortcode-btn button" data-shortcode='[ai_summary]自定义摘要内容[/ai_summary]'>自定义摘要</button>
                    </div>
                </div>

                <div class="editor-shortcodes-section">
                    <h4>📚 书籍展示</h4>
                    <div class="editor-shortcodes-buttons">
                        <button class="editor-shortcode-btn button" data-shortcode='[book url="https://book.douban.com/subject/xxxx/" title="书名" image="封面图URL" rating="5" status="wish"]'>想读</button>
                        <button class="editor-shortcode-btn button" data-shortcode='[book url="https://book.douban.com/subject/xxxx/" title="书名" image="封面图URL" rating="5" status="reading"]'>在读</button>
                        <button class="editor-shortcode-btn button" data-shortcode='[book url="https://book.douban.com/subject/xxxx/" title="书名" image="封面图URL" rating="5" status="read"]'>已读</button>
                    </div>
                </div>

                <div class="editor-shortcodes-section">
                    <h4>🖼️ 图片展示</h4>
                    <div class="editor-shortcodes-buttons">
                        <button class="editor-shortcode-btn button" data-shortcode='[gallery https://图片地址.jpg]'>单图大图</button>
                        <button class="editor-shortcode-btn button" data-shortcode='[gallery https://图片1.jpg https://图片2.jpg]'>2图网格</button>
                        <button class="editor-shortcode-btn button" data-shortcode='[gallery https://图片1.jpg https://图片2.jpg https://图片3.jpg]'>3图网格</button>
                    </div>
                    <div class="editor-shortcodes-note" style="margin-top: 8px; font-size: 12px; color: #666;">
                        单图：全宽显示 | 2图/3图：网格布局，自动等高裁剪
                    </div>
                </div>
            </div>

            <div class="editor-shortcodes-note">
                <p><strong>使用方法：</strong>点击上方按钮即可将对应的短代码插入到编辑器中。短代码将在文章发布时自动转换为相应的HTML内容。</p>
            </div>
        </div>
    `;

    // Markdown帮助管理器
    window.EditorMarkdown = {
        showModal: function () {
            EditorModal.show('editor-markdown-modal', 'Markdown语法语法', markdownModalContent);
        }
    };

    // 短代码帮助管理器
    window.EditorShortcodes = {
        showModal: function () {
            EditorModal.show('editor-shortcodes-modal', '短代码语法', shortcodesModalContent);
        },

        insertShortcode: function (shortcode) {
            if (shortcode) {
                EditorCore.insertContent(shortcode);
                EditorModal.hide('editor-shortcodes-modal');
            }
        }
    };

    // 编辑器增强功能初始化
    function initEditorEnhancements() {
        // QTags按钮已在PHP中通过admin_print_footer_scripts添加
        // 这里预留用于其他编辑器增强逻辑
    }

    // 全局事件委托
    $(document).on('click', function (e) {
        const $target = $(e.target);

        // 点击关闭按钮
        if ($target.hasClass('editor-modal-close')) {
            $target.closest('.editor-modal-wrapper').fadeOut(200);
            return;
        }

        // 点击Modal背景关闭
        if ($target.hasClass('editor-modal-wrapper')) {
            $target.fadeOut(200);
            return;
        }
    });

    // 短代码按钮事件绑定
    $(document).on('click', '.editor-shortcode-btn', function (e) {
        e.preventDefault();
        const shortcode = $(this).data('shortcode');
        EditorShortcodes.insertShortcode(shortcode);
    });

    console.log('编辑器核心模块已加载');

})(jQuery);
