/**
 * 编辑器增强功能 - QTags按钮管理 (v1.0.0)
 *
 * 此文件负责WordPress编辑器中QTags工具栏的按钮注册和管理。
 * 通过wp_localize_script从PHP获取配置数据，实现条件性按钮显示。
 *
 * 功能分工：
 * - Markdown语法帮助按钮
 * - 短代码语法帮助按钮
 * - 豆瓣书籍插入按钮
 * - AI摘要生成按钮
 * - WordPress媒体按钮位置调整
 *
 * @author wangdaodao
 * @version 1.0.0
 * @date 2025-10-30
 * @requires jQuery
 * @requires QTags (WordPress QuickTags)
 * @see features/editor-functions.php PHP配置和本地化数据
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // 从 wp_localize_script 获取数据
        if (typeof window.paperEditor === 'undefined') {
            return;
        }

        const settings = window.paperEditor.settings;
        const i18n = window.paperEditor.i18n;

        // 检查是否在后台编辑器页面
        const isAdminEditor = typeof QTags !== 'undefined' && $('#wp-content-wrap').length > 0;

        if (isAdminEditor) {
            // 后台编辑器功能
            // 注册Markdown语法帮助按钮
            QTags.addButton('wdd_markdown_help', 'Markdown语法', function() {
                if (settings.is_enhancement_enabled) {
                    if (window.EditorMarkdown) EditorMarkdown.showModal();
                } else {
                    alert(i18n ? i18n.enhancement_needed : '此功能需要启用编辑器增强模式');
                }
            });

            // 注册短代码帮助按钮
            QTags.addButton('wdd_wddmd_menu', '短代码语法', function() {
                if (settings.is_enhancement_enabled) {
                    if (window.EditorShortcodes) EditorShortcodes.showModal();
                } else {
                    alert(i18n ? i18n.enhancement_needed : '此功能需要启用编辑器增强模式');
                }
            });

            // 注册插入书籍按钮
            QTags.addButton('wdd_book_insert', '插入书籍', function() {
                if (settings.is_enhancement_enabled) {
                    if (window.EditorDoubanBook) EditorDoubanBook.showModal();
                } else {
                    alert(i18n ? i18n.enhancement_needed : '此功能需要启用编辑器增强模式');
                }
            });

            // 注册AI摘要按钮
            QTags.addButton('wdd_ai_summary_btn', 'AI摘要生成', function() {
                if (settings.is_ai_enabled) {
                    if (window.EditorAI) EditorAI.showModal();
                } else {
                    alert(i18n ? i18n.ai_not_configured : 'AI摘要功能未启用或未配置API密钥');
                }
            });

            // 将WordPress媒体按钮移动到QTags工具栏最前面
            $(document).on('tinymce-editor-init', function() {
                const mediaButtons = $('#wp-content-media-buttons');
                const qtToolbar = $('#ed_toolbar');

                if (mediaButtons.length && qtToolbar.length) {
                    const originalButton = mediaButtons.find('.button').first();
                    if (!originalButton.length) return;

                    // 创建新按钮
                    const mediaButtonHtml = $('<button />', {
                        type: 'button',
                        id: 'insert-media-button-moved',
                        'class': originalButton.attr('class'),
                        'data-editor': 'content',
                        title: '添加媒体',
                        html: originalButton.html()
                    });

                    // 绑定点击事件
                    mediaButtonHtml.on('click', function(e) {
                        e.preventDefault();
                        $('#insert-media-button').trigger('click');
                    });

                    // 添加到工具栏并隐藏旧的
                    qtToolbar.prepend(mediaButtonHtml);
                    mediaButtons.hide();
                }
            });
        }

        // 图片代理功能：自动转换外部图片URL（前后台都需要）
        initializeImageProxy();
    });

    /**
     * 初始化图片代理功能
     * 自动将符合条件的外部图片URL转换为代理URL
     */
    function initializeImageProxy() {
        // 检查是否启用了图片代理
        if (!window.paperEditor || !window.paperEditor.settings || !window.paperEditor.settings.is_image_proxy_enabled) {
            return;
        }

        // 获取代理设置
        const proxySettings = window.paperEditor.settings.image_proxy || {};
        const proxyDomains = proxySettings.domains || [];
        const proxyKeywords = proxySettings.keywords || [];

        if (proxyDomains.length === 0 && proxyKeywords.length === 0) {
            return;
        }

        /**
         * 检查URL是否需要代理
         */
        function shouldProxyImage(url) {
            if (!url || typeof url !== 'string') return false;

            try {
                const urlObj = new URL(url);
                const hostname = urlObj.hostname;

                // 检查域名白名单
                for (const domain of proxyDomains) {
                    if (hostname === domain || hostname.endsWith('.' + domain)) {
                        return true;
                    }
                }

                // 检查关键词
                const urlString = url.toLowerCase();
                for (const keyword of proxyKeywords) {
                    if (urlString.includes(keyword.toLowerCase())) {
                        return true;
                    }
                }

                return false;
            } catch (e) {
                return false;
            }
        }

        /**
         * 将图片URL转换为代理URL
         */
        function getProxyUrl(originalUrl) {
            // 使用WordPress主题目录路径
            const themeUrl = window.paperEditor && window.paperEditor.theme_url ?
                window.paperEditor.theme_url :
                (window.location.origin + '/wp-content/themes/barepaper-v7.1.0');
            return themeUrl + '/features/proxy-image.php?url=' + encodeURIComponent(originalUrl);
        }

        /**
         * 处理单个图片元素
         */
        function processImage(img) {
            const src = img.getAttribute('src');

            if (src && shouldProxyImage(src)) {
                const proxyUrl = getProxyUrl(src);
                img.setAttribute('src', proxyUrl);
                // 添加data-original-src属性以备不时之需
                img.setAttribute('data-original-src', src);
            }
        }

        /**
         * 处理页面中的所有图片
         */
        function processAllImages() {
            // 处理所有img标签
            const images = document.querySelectorAll('img');
            images.forEach(processImage);

            // 处理CSS背景图片（如果需要）
            // 这里可以扩展为处理background-image等
        }

        // 页面加载完成后处理图片
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', processAllImages);
        } else {
            processAllImages();
        }

        // 使用MutationObserver监听动态添加的图片
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        // 处理新添加的图片元素
                        if (node.tagName === 'IMG') {
                            processImage(node);
                        }
                        // 处理新添加元素中的图片
                        const images = node.querySelectorAll ? node.querySelectorAll('img') : [];
                        images.forEach(processImage);
                    }
                });
            });
        });

        // 开始观察
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
})(jQuery);
