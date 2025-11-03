/**
 * ===========================================
 * 编辑器Modal系统模块
 * ===========================================
 *
 * 🎛️ 功能说明
 *   - 统一的Modal窗口管理
 *   - 样式和行为标准化
 *   - 事件委托处理
 *   - 状态消息显示
 *
 * 📋 核心功能
 *   - show(): 显示Modal窗口
 *   - hide(): 隐藏Modal窗口
 *   - showStatus(): 显示状态消息
 *
 * 🎨 样式特性
 *   - 响应式设计
 *   - 统一的视觉风格
 *   - 加载、成功、错误状态
 *
 * 🔗 依赖关系
 *   - jQuery (必需)
 *   - editor-core.js (推荐)
 *
 * 📁 文件位置
 *   assets/js/editor-modal.js
 *
 * @author wangdaodao
 * @version 1.0.0
 * @date 2025-10-23
 */

(function($) {
    'use strict';

    // Modal样式（内联注入，避免额外CSS文件）
    const modalStyles = `
        .editor-modal-wrapper {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6); z-index: 100001;
            display: none;
        }
        .editor-modal-content {
            position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            background: white; padding: 20px; border-radius: 8px;
            max-width: 650px; width: 90%; max-height: 80%;
            overflow-y: auto;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .editor-modal-content h3 {
            margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #ddd;
        }
        .editor-modal-close {
            position: absolute; top: 15px; right: 15px;
            background: none; border: none; font-size: 20px;
            cursor: pointer; color: #666; line-height: 1;
        }
        .editor-modal-close:hover {
            color: #333;
        }
        .editor-status-message {
            padding: 10px; border-radius: 4px; margin-bottom: 15px;
            font-weight: bold; text-align: center;
        }
        .editor-status-message.loading {
            background: #e3f2fd; color: #1976d2; border: 1px solid #bbdefb;
        }
        .editor-status-message.success {
            background: #e8f5e8; color: #2e7d32; border: 1px solid #c8e6c9;
        }
        .editor-status-message.error {
            background: #ffebee; color: #c62828; border: 1px solid #ffcdd2;
        }
    `;

    // 注入样式
    if (!$('#editor-modal-styles').length) {
        $('head').append('<style id="editor-modal-styles">' + modalStyles + '</style>');
    }

    // Modal管理器
    window.EditorModal = {
        /**
         * 显示Modal
         * @param {string} modalId - Modal的ID
         * @param {string} title - Modal标题
         * @param {string} content - Modal内容HTML
         */
        show: function(modalId, title, content) {
            let modal = $('#' + modalId);

            if (modal.length === 0) {
                // 创建新的Modal
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
                // 更新现有Modal的内容
                modal.find('h3').text(title);
                modal.find('.editor-modal-body').html(content);
            }

            modal.fadeIn(200);
        },

        /**
         * 隐藏Modal
         * @param {string} modalId - Modal的ID
         */
        hide: function(modalId) {
            $('#' + modalId).fadeOut(200);
        },

        /**
         * 显示状态消息
         * @param {string} modalId - Modal的ID
         * @param {string} message - 消息内容
         * @param {string} type - 消息类型 (loading/success/error)
         */
        showStatus: function(modalId, message, type = 'info') {
            const modal = $('#' + modalId);
            let statusDiv = modal.find('.editor-status-message');

            if (statusDiv.length === 0) {
                modal.find('.editor-modal-body').prepend('<div class="editor-status-message"></div>');
                statusDiv = modal.find('.editor-status-message');
            }

            statusDiv.removeClass('loading success error info').addClass(type);
            statusDiv.html('<span>' + message + '</span>').show();
        }
    };

    // 全局事件委托
    $(document).on('click', function(e) {
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

    console.log('编辑器Modal系统已加载');

})(jQuery);
