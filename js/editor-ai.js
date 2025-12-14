/**
 * 编辑器AI摘要模块
 * 提供AI摘要生成功能和智能内容处理
 *
 * 功能：
 * - AI摘要生成和插入
 * - 关键词提取和Slug更新
 * - 多编辑器类型支持
 * - 智能内容预处理
 */

(function ($) {
    'use strict';


    // AI摘要Modal模板函数
    function createAIModalHTML() {
        return `
            <div class="ai-modal-body">
                <div id="ai-summary-generate" class="ai-step ai-step-generate">
                    <div class="ai-icon">🤖</div>
                    <p>点击下方按钮生成AI摘要，摘要将自动插入到文章开头。</p>
                    <button id="ai-summary-generate-btn" class="button button-primary">生成AI摘要</button>
                </div>
                <div id="ai-summary-loading" class="ai-step ai-step-loading" style="display: none;">
                    <div class="ai-icon">🤖</div>
                    <div class="ai-loading-text">正在生成AI摘要...</div>
                    <div class="ai-loading-subtext">这可能需要几秒钟时间，请耐心等待</div>
                </div>
                <div id="ai-summary-success" class="ai-step ai-step-success" style="display: none;">
                    <div class="ai-success-notice">
                        <strong>✅ 生成成功！</strong>
                    </div>
                    <div class="ai-content-preview">
                        <div id="ai-summary-content-display"></div>
                        <input type="hidden" id="ai-summary-content">
                    </div>
                    <div class="ai-keywords-info">
                        <strong>关键词：</strong><span id="ai-keywords-display">3个英文</span>
                    </div>
                </div>
                <div id="ai-summary-error" class="ai-step ai-step-error" style="display: none;">
                    <div class="ai-error-icon">❌</div>
                    <div class="ai-error-title">生成失败</div>
                    <div id="ai-summary-error-message" class="ai-error-message"></div>
                </div>
            </div>
            <div class="ai-modal-footer">
                <button id="ai-summary-cancel" class="button">取消</button>
                <button id="ai-summary-confirm" class="button button-primary" disabled>确定</button>
            </div>
        `;
    }

    // AI功能管理器
    window.EditorAI = {
        /**
         * 显示AI摘要Modal
         */
        showModal: function () {
            EditorModal.show('ai-summary-modal', '🤖 AI摘要生成', createAIModalHTML());
        },

        /**
         * 隐藏Modal
         */
        hideModal: function () {
            EditorModal.hide('ai-summary-modal');
        },

        /**
         * 生成AI摘要
         */
        generateSummary: function () {
            // 检查AI设置 - 使用新的本地化数据
            if (typeof window.paperEditor === 'undefined') {
                this.showError('AI摘要功能未正确加载，请刷新页面重试');
                return;
            }

            // 获取当前编辑器内容
            var editorContent = this.getEditorContent();
            if (!editorContent || editorContent.trim().length < 50) {
                this.showError('文章内容太短，请至少输入50个字符的内容再生成摘要。');
                return;
            }

            // 显示加载状态
            $('#ai-summary-loading').show();
            $('#ai-summary-success').hide();
            $('#ai-summary-error').hide();

            // 发送AJAX请求
            $.ajax({
                url: window.paperEditor.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_generate',
                    post_id: window.paperEditor.post_id,
                    content: editorContent,
                    nonce: window.paperEditor.nonce
                },
                success: function (response) {
                    if (response.success) {
                        EditorAI.showSuccess(response.data);
                    } else {
                        const message = response.data.message || '发生未知错误';
                        const details = response.data.details ? '<br><small>详细信息: ' + response.data.details + '</small>' : '';
                        EditorAI.showError('生成失败：' + message + details);
                    }
                },
                error: function (xhr) {
                    EditorAI.showError('网络或服务器错误: ' + xhr.statusText);
                }
            });
        },

        getEditorContent: function () {
            return EditorCore.getContent();
        },

        /**
         * 显示成功状态
         */
        showSuccess: function (data) {
            $('#ai-summary-loading').hide();
            $('#ai-summary-success').show();

            // 如果有新的slug，立即更新永久链接
            if (data.new_slug) {
                this.updatePostSlug(data.new_slug);
                $('#ai-summary-content').data('new-slug', data.new_slug);
            }

            // 直接显示摘要内容
            if (data.summary) {
                $('#ai-summary-content-display').text(data.summary);
                $('#ai-summary-content').val(data.summary);
            }

            // 显示关键词
            $('#ai-keywords-display').text(data.keywords);

            // 启用确定按钮
            $('#ai-summary-confirm').prop('disabled', false);
        },

        /**
         * 显示错误状态
         */
        showError: function (message) {
            $('#ai-summary-loading').hide();
            $('#ai-summary-success').hide();
            $('#ai-summary-error').show();
            $('#ai-summary-error-message').html(message);
        },

        /**
         * 确认插入摘要
         */
        confirmInsert: function () {
            var summaryContent = $('#ai-summary-content').val();
            var newSlug = $('#ai-summary-content').data('new-slug');

            if (summaryContent) {
                // 显示插入状态
                $('#ai-summary-confirm').prop('disabled', true).text('正在插入...');

                // 插入摘要内容到编辑器最前面
                const fullContent = '[ai_summary]' + summaryContent + '[/ai_summary]\n\n';
                this.insertContentAtBeginning(fullContent);

                // 如果有新的slug，同时更新永久链接
                if (newSlug) {
                    this.updatePostSlug(newSlug);
                }

                // 延迟关闭modal
                setTimeout(function () {
                    EditorAI.hideModal();
                }, 100);
            }
        },

        /**
         * 在编辑器内容开头插入内容
         */
        insertContentAtBeginning: function (content) {
            return this._tryClassicEditor(content) ||
                this._tryTinyMCE(content) ||
                this._fallbackInsert(content);
        },

        /**
         * 尝试经典编辑器插入
         */
        _tryClassicEditor: function (content) {
            var contentTextarea = $('#content');
            if (contentTextarea.length > 0) {
                var currentContent = contentTextarea.val();
                contentTextarea.val(content + currentContent);
                return true;
            }
            return false;
        },

        /**
         * 尝试TinyMCE编辑器插入
         */
        _tryTinyMCE: function (content) {
            if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor) {
                var editor = tinyMCE.activeEditor;
                var currentContent = editor.getContent();
                editor.setContent(content + currentContent);
                return true;
            }
            return false;
        },

        /**
         * 备用插入方案
         */
        _fallbackInsert: function (content) {
            return EditorCore.insertContent ? EditorCore.insertContent(content) : false;
        },

        /**
         * 更新文章Slug
         */
        updatePostSlug: function (newSlug) {
            if (!newSlug) return;

            // 更新输入框值（实际保存的值）
            $('#post_name').val(newSlug);

            // 更新显示元素，让用户看到变化
            $('#editable-post-name').text(newSlug);
            $('#editable-post-name-full').text(newSlug);
        }
    };

    // Modal事件绑定
    $(document).on('click', '#ai-summary-generate-btn', function (e) {
        e.preventDefault();
        $('#ai-summary-generate').hide();
        EditorAI.generateSummary();
    });

    $(document).on('click', '#ai-summary-cancel', function (e) {
        e.preventDefault();
        EditorAI.hideModal();
    });

    $(document).on('click', '#ai-summary-confirm', function (e) {
        e.preventDefault();
        EditorAI.confirmInsert();
    });

})(jQuery);
