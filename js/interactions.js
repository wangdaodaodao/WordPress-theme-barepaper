/**
 * Paper WP 综合交互脚本 (interactions.js)
 * 
 * 作用范围：
 * 1. 全站功能：
 *    - 在线状态统计 (Online Status)
 *    - 图片高级懒加载 (Advanced Lazy Load)
 *    - 图片灯箱效果 (Slimbox2)
 * 2. 文章页/特定页面功能：
 *    - 代码块复制 (Copy Code)
 *    - 文章阅读计数 (View Counter)
 *    - 赞助二维码显示 (Sponsor QR)
 *    - 书单列表动画 (Booklist Animation)
 * 
 * 加载方式：
 * - 由 core/assets.php 中的 paper_wp_scripts() 函数加载
 * - 句柄：'paper-interactions'
 * - 依赖：['jquery']
 * - 加载策略：全站加载 (因为包含在线统计和全局懒加载功能)
 */
jQuery(document).ready(function ($) {
    'use strict';

    // ==========================================
    // 1. Copy Code Functionality
    // ==========================================
    // 使用事件委托，确保动态加载的内容也能绑定事件
    $(document.body).on('click', '.copy-code-btn', function () {
        const btn = $(this);
        const targetId = btn.data('target');
        const preElement = document.getElementById(targetId);

        if (!preElement) {
            console.error('Copy target not found:', targetId);
            return;
        }

        // 获取 <code> 标签内的文本
        const codeElement = preElement.querySelector('code');
        const codeToCopy = codeElement ? codeElement.innerText : preElement.innerText;

        // 优先使用现代、安全的 Clipboard API
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(codeToCopy).then(function () {
                updateButtonState(btn, true);
            }).catch(function (err) {
                console.error('Clipboard API copy failed:', err);
                fallbackCopy(codeToCopy, btn); // 如果失败，则使用后备方案
            });
        } else {
            fallbackCopy(codeToCopy, btn); // 不支持 Clipboard API，直接使用后备方案
        }
    });

    // 内联代码块复制功能
    $(document.body).on('click', '.markdown-inline-code, .markdown-content .markdown-inline-code, .entry-content .markdown-inline-code, .post-content .markdown-inline-code', function () {
        const inlineCode = $(this);
        const codeToCopy = inlineCode.text().trim();

        // 优先使用现代、安全的 Clipboard API
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(codeToCopy).then(function () {
                updateInlineCodeState(inlineCode, '✓ 已复制');
            }).catch(function (err) {
                console.error('Clipboard API copy failed:', err);
                fallbackCopy(codeToCopy, inlineCode); // 如果失败，则使用后备方案
            });
        } else {
            fallbackCopy(codeToCopy, inlineCode); // 不支持 Clipboard API，直接使用后备方案
        }
    });

    /**
     * 更新按钮状态的辅助函数
     * @param {jQuery} btn - The button element.
     * @param {boolean} isSuccess - Whether the copy was successful.
     */
    function updateButtonState(btn, isSuccess) {
        const originalHtml = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M6.9998 6V3C6.9998 2.44772 7.44752 2 7.9998 2H19.9998C20.5521 2 20.9998 2.44772 20.9998 3V17C20.9998 17.5523 20.5521 18 19.9998 18H16.9998V20.9991C16.9998 21.5519 16.5499 22 15.993 22H4.00666C3.45059 22 3 21.5554 3 20.9991L3.0026 7.00087C3.0027 6.44811 3.45264 6 4.00942 6H6.9998ZM5.00242 8L5.00019 20H14.9998V8H5.00242ZM8.9998 6H16.9998V16H18.9998V4H8.9998V6Z"></path></svg>';
        const successHtml = '已复制';
        const errorHtml = '复制失败';

        if (isSuccess) {
            btn.html(successHtml).addClass('copied').prop('disabled', true);
        } else {
            btn.html(errorHtml).addClass('copy-failed').prop('disabled', true);
        }

        setTimeout(function () {
            btn.html(originalHtml).removeClass('copied copy-failed').prop('disabled', false);
        }, 2000);
    }

    /**
     * 更新内联代码状态的辅助函数
     * @param {jQuery} inlineCode - The inline code element.
     * @param {string} text - The text to display in tooltip.
     */
    function updateInlineCodeState(inlineCode, text) {
        inlineCode.addClass('copied');

        setTimeout(function () {
            inlineCode.removeClass('copied');
        }, 2000);
    }

    /**
     * 使用 document.execCommand 的后备复制方法
     * @param {string} text - The text to copy.
     * @param {jQuery} btn - The button element.
     */
    function fallbackCopy(text, btn) {
        const textArea = document.createElement('textarea');
        textArea.value = text;

        // 防止在手机上滚动到页面底部
        textArea.style.position = 'fixed';
        textArea.style.top = '0';
        textArea.style.left = '0';
        textArea.style.opacity = '0';

        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            const successful = document.execCommand('copy');
            if (successful) {
                // Check if it's an inline code element or a button
                if (btn.hasClass('copy-code-btn')) {
                    updateButtonState(btn, true);
                } else {
                    updateInlineCodeState(btn, '✓ 已复制');
                }
            } else {
                if (btn.hasClass('copy-code-btn')) {
                    updateButtonState(btn, false);
                }
            }
        } catch (err) {
            console.error('Fallback copy failed:', err);
            if (btn.hasClass('copy-code-btn')) {
                updateButtonState(btn, false);
            }
        }

        document.body.removeChild(textArea);
    }

    // ==========================================
    // 2. View Counter Functionality
    // ==========================================
    // 从 script 标签的 data 属性读取数据
    var scriptTag = document.querySelector('script[data-ajax-url][data-nonce][data-post-id]');
    if (!scriptTag) {
        // 尝试查找当前脚本标签
        var scripts = document.querySelectorAll('script[src*="interactions.js"]');
        scriptTag = scripts[scripts.length - 1];
    }

    if (scriptTag && scriptTag.dataset.ajaxUrl && scriptTag.dataset.postId) {
        $.ajax({
            type: 'POST',
            url: scriptTag.dataset.ajaxUrl,
            data: {
                action: 'track_post_views',
                nonce: scriptTag.dataset.nonce,
                post_id: scriptTag.dataset.postId,
            },
            success: function (response) {
                // You can optionally handle the response here, e.g., for debugging
                if (response.success) {
                    // console.log('View count updated successfully.');
                } else {
                    // console.error('Failed to update view count: ' + response.data);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                // console.error('AJAX error while updating view count: ' + textStatus + ' - ' + errorThrown);
            }
        });
    }


    // ==========================================
    // 4. Sponsor QR Code (from sponsor-qr.js)
    // ==========================================
    (function () {
        $('.alipay, .wechat').hover(
            function () {
                var type = $(this).hasClass('alipay') ? 'alipay' : 'wechat';
                var qrElement = $('.qrshow-' + type);
                if (qrElement.length > 0) {
                    qrElement.addClass('show');
                }
            },
            function () {
                var type = $(this).hasClass('alipay') ? 'alipay' : 'wechat';
                var qrElement = $('.qrshow-' + type);
                if (qrElement.length > 0) {
                    qrElement.removeClass('show');
                }
            }
        );
    })();

    // ==========================================
    // 5. Slimbox2 Init (from slimbox2-init.js)
    // ==========================================
    (function () {
        var lightboxRel = 'lightbox';
        var thumbnailDataAttr = 'data-thumbnail-src';
        var fullSrcDataAttr = 'data-full-src';

        // 灯箱初始化
        if ($.fn.slimbox) {
            $('a[rel="' + lightboxRel + '"]').slimbox({
                overlayOpacity: 0.8,
                overlayFadeDuration: 300,
                resizeDuration: 400,
                imageFadeDuration: 400,
                captionAnimationDuration: 400
            }, function (el) {
                var $link = $(el);
                var $img = $link.find('img');
                var fullSrc = $img.attr(fullSrcDataAttr);
                var imgSrc = fullSrc || $link.attr('href');
                var title = $link.attr('title') || $img.attr('alt') || '';

                if (fullSrc && $link.attr('href') !== fullSrc) {
                    $link.attr('href', fullSrc);
                }

                return [imgSrc, title];
            });
        }

        // 按需加载策略：只加载缩略图，用户点击时才加载大图
        if ('IntersectionObserver' in window) {
            // 创建Intersection Observer实例
            var imageObserver = new IntersectionObserver(function (entries, observer) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        var img = entry.target;
                        var thumbnailSrc = img.getAttribute(thumbnailDataAttr);

                        // 当图片进入视口时，只加载缩略图
                        if (thumbnailSrc) {
                            var tempImg = new Image();
                            tempImg.onload = function () {
                                // 缩略图加载成功，显示缩略图
                                img.src = thumbnailSrc;
                            };
                            tempImg.src = thumbnailSrc;
                        }

                        // 停止观察这个图片
                        observer.unobserve(img);
                    }
                });
            }, {
                // 提前50px开始加载
                rootMargin: '50px 0px',
                threshold: 0.01
            });

            // 观察所有需要懒加载的图片
            document.querySelectorAll('img[' + thumbnailDataAttr + ']').forEach(function (img) {
                imageObserver.observe(img);
            });
        } else {
            // Intersection Observer不支持时的降级方案
            $('img[' + thumbnailDataAttr + ']').each(function () {
                var $img = $(this);
                var thumbnailSrc = $img.attr(thumbnailDataAttr);

                if (thumbnailSrc) {
                    // 直接加载缩略图
                    var tempImg = new Image();
                    tempImg.onload = function () {
                        $img.attr('src', thumbnailSrc);
                    };
                    tempImg.src = thumbnailSrc;
                }
            });
        }
    })();

});

// ==========================================
// 6. Online Status (from online-status.js)
// ==========================================
// 当整个页面加载完毕后执行
window.addEventListener('load', function () {
    // 从 script 标签的 data 属性读取数据
    // 注意：合并后可能需要统一 data 属性的来源
    var scriptTag = document.querySelector('script[data-ajax-url][data-online-nonce]');

    if (!scriptTag || !scriptTag.dataset.ajaxUrl || !scriptTag.dataset.onlineNonce) {
        // console.warn('Paper WP: Online status config not found.');
        return;
    }

    // 定义一个函数，用于向服务器发送更新请求
    function updateUserOnlineStatus() {
        // 使用 Fetch API 发送请求
        fetch(scriptTag.dataset.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            // 发送必要的数据
            body: new URLSearchParams({
                'action': 'paper_wp_update_online_status',
                'nonce': scriptTag.dataset.onlineNonce
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 可选：在控制台打印成功信息，用于调试
                    // console.log('User online status updated.');
                } else {
                    console.warn('Paper WP: Failed to update status:', data.data ? data.data.message : 'Unknown error');
                }
            })
            .catch(error => {
                console.error('Paper WP: Error during AJAX request:', error);
            });
    }

    // 首次加载页面时，立即更新一次状态
    updateUserOnlineStatus();

    // 设置一个定时器，每隔 1 分钟（60000毫秒）自动更新一次
    // 配合后端5分钟的在线判断，确保用户在线状态准确
    setInterval(updateUserOnlineStatus, 60000);
});

// ==========================================
// 7. Advanced Lazy Load (from advanced-lazy-load.js)
// ==========================================
(function () {
    'use strict';

    /**
     * 高级懒加载系统
     * 支持Intersection Observer API，提供更好的性能和用户体验
     */
    class AdvancedLazyLoad {
        constructor() {
            this.observer = null;
            this.config = {
                rootMargin: '50px 0px',
                threshold: 0.1
            };
            this.init();
        }

        init() {
            // 检查浏览器是否支持Intersection Observer
            if ('IntersectionObserver' in window) {
                this.setupObserver();
            } else {
                // 降级方案：直接加载所有图片
                this.loadAllImages();
            }
        }

        setupObserver() {
            this.observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.loadImage(entry.target);
                        this.observer.unobserve(entry.target);
                    }
                });
            }, this.config);

            // 观察所有需要懒加载的图片
            const lazyImages = document.querySelectorAll('[data-lazy="true"], img[loading="lazy"]');
            lazyImages.forEach(img => {
                this.observer.observe(img);
            });
        }

        loadImage(img) {
            const originalSrc = img.getAttribute('data-original') || img.src;
            const smallSrc = img.getAttribute('data-thumbnail');

            // 如果已经有小图，先加载小图，再加载大图
            if (smallSrc && smallSrc !== originalSrc) {
                this.loadWithPlaceholder(img, smallSrc, originalSrc);
            } else {
                img.src = originalSrc;
            }

            // 移除懒加载属性
            img.removeAttribute('data-lazy');
            img.removeAttribute('data-original');
            img.removeAttribute('data-thumbnail');
        }

        loadWithPlaceholder(img, placeholderSrc, originalSrc) {
            // 先加载占位图
            const placeholder = new Image();
            placeholder.onload = () => {
                img.src = placeholderSrc;

                // 延迟加载原图
                setTimeout(() => {
                    const original = new Image();
                    original.onload = () => {
                        img.src = originalSrc;
                        img.classList.add('lazy-loaded');
                    };
                    original.src = originalSrc;
                }, 100);
            };
            placeholder.src = placeholderSrc;
        }



        // 销毁观察器
        destroy() {
            if (this.observer) {
                this.observer.disconnect();
            }
        }
    }

    // 初始化懒加载系统
    document.addEventListener('DOMContentLoaded', function () {
        window.advancedLazyLoad = new AdvancedLazyLoad();
    });

    /**
     * 图片预加载系统
     */
    class ImagePreloader {
        constructor() {
            this.cache = new Map();
        }

        // 预加载重要图片
        preloadCriticalImages() {
            const criticalImages = [
                // 添加需要预加载的关键图片URL
                // '/wp-content/themes/barepaper-v7.0.0/images/logo.svg',
                // '/wp-content/themes/barepaper-v7.0.0/images/loading.gif'
            ];

            criticalImages.forEach(url => {
                this.preloadImage(url);
            });
        }

        preloadImage(url) {
            if (!this.cache.has(url)) {
                const img = new Image();
                img.src = url;
                this.cache.set(url, img);
            }
        }
    }

    // 初始化预加载系统
    window.imagePreloader = new ImagePreloader();

})();

// ==========================================
// 8. Post Like/Recommend Functionality
// ==========================================
(function ($) {
    // 获取脚本标签上的配置数据
    var scriptTag = document.querySelector('script[data-interactions-nonce]');
    if (!scriptTag) {
        // 尝试查找当前脚本标签（如果合并后属性在当前脚本上）
        var scripts = document.querySelectorAll('script[src*="interactions.js"]');
        scriptTag = scripts[scripts.length - 1];
    }

    if (!scriptTag || !scriptTag.dataset.interactionsNonce) {
        return;
    }

    var nonce = scriptTag.dataset.interactionsNonce;
    var ajaxUrl = scriptTag.dataset.ajaxUrl;

    $(document).on('click', '#recommend-post-button', function (e) {
        e.preventDefault();
        var btn = $(this);
        var postId = btn.data('post-id');
        var countSpan = btn.find('.recommend-count');
        var textSpan = btn.find('.recommend-text');

        if (btn.hasClass('liked')) {
            alert('您已经推荐过这篇文章了');
            return;
        }

        // 检查Cookie
        if (document.cookie.indexOf('paper_wp_liked_' + postId) !== -1) {
            btn.addClass('liked');
            textSpan.text('已点赞');
            return;
        }

        btn.prop('disabled', true);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'paper_wp_like_post',
                post_id: postId,
                nonce: nonce
            },
            success: function (response) {
                if (response.success) {
                    countSpan.text(response.data.count);
                    btn.addClass('liked');
                    textSpan.text('已推荐');
                } else {
                    alert(response.data);
                    btn.prop('disabled', false);
                }
            },
            error: function () {
                alert('网络错误，请稍后重试');
                btn.prop('disabled', false);
            }
        });
    });

    // 初始化时检查Cookie状态
    $('#recommend-post-button').each(function () {
        var btn = $(this);
        var postId = btn.data('post-id');
        if (document.cookie.indexOf('paper_wp_liked_' + postId) !== -1) {
            btn.addClass('liked');
            btn.find('.recommend-text').text('已点赞');
        }
    });
})(jQuery);

// ==========================================
// 9. Comment Redirect Functionality
// ==========================================
// 评论提交后重定向到评论区域而不是页面顶部
jQuery(document).ready(function ($) {
    // 检查URL中是否包含评论哈希
    if (window.location.hash && window.location.hash.indexOf('comment-') !== -1) {
        var hash = window.location.hash;
        var $target = $(hash);

        if ($target.length) {
            // 稍微延迟滚动，确保页面完全加载
            setTimeout(function () {
                $('html, body').animate({
                    scrollTop: $target.offset().top - 100 // 减去一些偏移量，避免被顶部导航遮挡
                }, 500);
            }, 300);
        }
    }
});
