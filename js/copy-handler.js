jQuery(document).ready(function($) {
    'use strict';

    // 使用事件委托，确保动态加载的内容也能绑定事件
    $(document.body).on('click', '.copy-code-btn', function() {
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
            navigator.clipboard.writeText(codeToCopy).then(function() {
                updateButtonState(btn, '已复制!');
            }).catch(function(err) {
                console.error('Clipboard API copy failed:', err);
                fallbackCopy(codeToCopy, btn); // 如果失败，则使用后备方案
            });
        } else {
            fallbackCopy(codeToCopy, btn); // 不支持 Clipboard API，直接使用后备方案
        }
    });

    // 内联代码块复制功能
    $(document.body).on('click', '.markdown-inline-code, .markdown-content .markdown-inline-code, .entry-content .markdown-inline-code, .post-content .markdown-inline-code', function() {
        const inlineCode = $(this);
        const codeToCopy = inlineCode.text().trim();

        // 优先使用现代、安全的 Clipboard API
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(codeToCopy).then(function() {
                updateInlineCodeState(inlineCode, '✓ 已复制');
            }).catch(function(err) {
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
     * @param {string} text - The text to display.
     */
    function updateButtonState(btn, text) {
        const originalText = '复制';
        btn.text(text).addClass('copied').prop('disabled', true);

        setTimeout(function() {
            btn.text(originalText).removeClass('copied').prop('disabled', false);
        }, 2000);
    }

    /**
     * 更新内联代码状态的辅助函数
     * @param {jQuery} inlineCode - The inline code element.
     * @param {string} text - The text to display in tooltip.
     */
    function updateInlineCodeState(inlineCode, text) {
        inlineCode.addClass('copied');

        setTimeout(function() {
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
                updateButtonState(btn, '已复制!');
            } else {
                updateButtonState(btn, '复制失败');
            }
        } catch (err) {
            console.error('Fallback copy failed:', err);
            updateButtonState(btn, '复制失败');
        }

        document.body.removeChild(textArea);
    }
});
