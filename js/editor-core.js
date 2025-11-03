/**
 * ===========================================
 * 编辑器核心功能模块
 * ===========================================
 *
 * 🎯 功能说明
 *   - 编辑器基础工具函数
 *   - 通用事件处理
 *   - 状态管理
 *   - 编辑器环境检查
 *
 * 📋 核心功能
 *   - isEditorReady(): 检查QTags是否可用
 *   - insertContent(): 安全插入内容到编辑器
 *   - showMessage(): 显示状态消息
 *
 * 🔗 依赖关系
 *   - jQuery (必需)
 *   - WordPress QTags (编辑器环境)
 *
 * 📁 文件位置
 *   assets/js/editor-core.js
 *
 * @author wangdaodao
 * @version 1.0.0
 * @date 2025-10-23
 */

(function($) {
    'use strict';

    // 确保在DOM加载完毕后执行
    $(document).ready(function() {
        // 检查QTags是否可用
        if (typeof QTags === 'undefined') {
            console.warn('QTags未加载，编辑器功能可能受限');
            return;
        }

        console.log('编辑器核心模块已加载');
    });

    // 通用工具函数
    window.EditorCore = {
        /**
         * 检查编辑器环境
         */
        isEditorReady: function() {
            return typeof QTags !== 'undefined';
        },

        /**
         * 安全地插入内容到编辑器
         */
        insertContent: function(content) {
            if (typeof QTags !== 'undefined') {
                QTags.insertContent(content);
                return true;
            }
            console.error('QTags不可用，无法插入内容');
            return false;
        },

        /**
         * 显示状态消息
         */
        showMessage: function(message, type = 'info') {
            // 可以在这里添加统一的提示系统
            console.log(`[${type.toUpperCase()}] ${message}`);
        }
    };

})(jQuery);
