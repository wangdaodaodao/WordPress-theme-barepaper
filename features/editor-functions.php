<?php
if (!defined('ABSPATH')) exit;

/**
 * 编辑器增强功能主文件
 * 整合所有编辑器相关的钩子注册和脚本加载
 *
 * @author wangdaodao
 * @version 1.0.0
 * @date 2025-10-30
 */

/**
 * 主初始化函数，用于注册所有编辑器相关的钩子
 */
function paper_wp_editor_enhancements_init() {
    // 脚本和样式加载
    add_action('admin_enqueue_scripts', 'paper_wp_enqueue_editor_assets');

    // AJAX 处理器
    add_action('wp_ajax_ai_generate', 'paper_wp_ajax_generate_summary_and_slug');
    add_action('wp_ajax_update_slug_with_keywords', 'paper_wp_ajax_update_slug_with_keywords');
    add_action('wp_ajax_paper_wp_test_ai_connection', 'paper_wp_ajax_test_ai_connection');
}
add_action('init', 'paper_wp_editor_enhancements_init');


/**
 * 统一的编辑器脚本和样式加载函数
 */
function paper_wp_enqueue_editor_assets($hook) {
    // 只在文章编辑页面加载
    if (!in_array($hook, ['post.php', 'post-new.php'])) {
        return;
    }

    // 强制启用经典编辑器
    add_filter('use_block_editor_for_post', '__return_false', 10);
    add_filter('use_block_editor_for_post_type', '__return_false', 10);

    // 获取所有需要的设置
    $editor_settings = Paper_Settings_Manager::get_editor_settings();
    $ai_settings = Paper_Settings_Manager::get_ai_settings();

    // 加载编辑器模块，确保依赖关系正确
    wp_enqueue_script('jquery');
    wp_enqueue_script('quicktags');

    // 核心编辑器模块（包含基础功能、Modal、Markdown、短代码）
    wp_enqueue_script('editor-core', get_template_directory_uri() . '/js/editor-core.js', ['jquery', 'quicktags'], BAREPAPER_VERSION, false);

    // AI摘要模块
    wp_enqueue_script('editor-ai', get_template_directory_uri() . '/js/editor-ai.js', ['editor-core'], BAREPAPER_VERSION, false);



    // 在PHP中直接添加QTags按钮
    add_action('admin_print_footer_scripts', function() {
        ?>
        <script type="text/javascript">
        if (typeof QTags !== 'undefined') {
            QTags.addButton('wdd_markdown_help', 'Markdown语法', function() {
                if (window.EditorMarkdown) {
                    EditorMarkdown.showModal();
                } else {
                    alert('Markdown帮助模块未加载');
                }
            });

            QTags.addButton('wdd_wddmd_menu', 'shortcode语法', function() {
                if (window.EditorShortcodes) {
                    EditorShortcodes.showModal();
                } else {
                    alert('短代码帮助模块未加载');
                }
            });



            QTags.addButton('wdd_ai_summary_btn', '插入AI摘要', function() {
                if (window.EditorAI) {
                    EditorAI.showModal();
                } else {
                    alert('AI摘要模块未加载');
                }
            });
        }
        </script>
        <?php
    });



    // 使用 wp_localize_script 传递数据
    $localized_data = [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('paper_wp_ai_generate_nonce'),
        'post_id' => get_post() ? get_post()->ID : 0,
        'settings' => [
            'is_enhancement_enabled' => !empty($editor_settings['enable_wddmds']),
            'is_ai_enabled' => !empty($ai_settings['ai_summary_enabled']) && !empty($ai_settings['ai_api_key']),
            'is_image_proxy_enabled' => false, // 图片代理功能已移除
        ],
        'i18n' => [
            'enhancement_needed' => '此功能需要启用编辑器增强模式。请前往后台"barepaper主题设置" → "编辑器设置" → 启用"编辑器增强模式"。',
            'ai_not_configured' => 'AI摘要功能未启用或未配置API密钥。请在主题设置中配置AI功能。',
        ]
    ];

    wp_localize_script('editor-core', 'paperEditor', $localized_data);

    // 加载样式
    wp_enqueue_style('editor-qtags-styles', get_template_directory_uri() . '/css/editor.css', [], BAREPAPER_VERSION);
}

/**
 * ===========================================
 * AI摘要手动生成模块 (v2.0)
 * ===========================================
 */

/**
 * AJAX请求安全验证辅助函数
 */
function paper_wp_verify_ajax_request($nonce_action, $capability = 'edit_posts') {
    if (!check_ajax_referer($nonce_action, 'nonce', false)) {
        return new WP_Error('nonce_error', '安全验证失败', ['status' => 403]);
    }
    if (!current_user_can($capability)) {
        return new WP_Error('permission_error', '权限不足', ['status' => 403]);
    }
    return true;
}

/**
 * AJAX处理器：生成AI摘要和Slug
 */
function paper_wp_ajax_generate_summary_and_slug() {
    $verification = paper_wp_verify_ajax_request('paper_wp_ai_generate_nonce');
    if (is_wp_error($verification)) {
        wp_send_json_error(['message' => $verification->get_error_message()], $verification->get_error_data()['status']);
        return;
    }

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $editor_content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';

    if (!$post_id) {
        wp_send_json_error(['message' => '无效的文章ID']);
        return;
    }

    // 如果没有提供编辑器内容，则使用数据库中的内容
    if (empty($editor_content)) {
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(['message' => '文章不存在']);
            return;
        }
        $content = $post->post_content;
    } else {
        // 使用编辑器中的当前内容
        $content = $editor_content;
    }

    // 获取AI设置
    $ai_settings = Paper_Settings_Manager::get_ai_settings();
    if (empty($ai_settings['ai_summary_enabled'])) {
        wp_send_json_error(['message' => 'AI摘要功能未启用，请先在主题设置中启用AI摘要功能']);
        return;
    }
    if (empty($ai_settings['ai_api_key'])) {
        wp_send_json_error(['message' => '请先在主题设置中配置AI API Key']);
        return;
    }

    // 调用AI生成数据
    $ai_result = paper_wp_generate_ai_data($content, $ai_settings['ai_api_key']);

    if (!$ai_result || !empty($ai_result['error'])) {
        wp_send_json_error([
            'message' => 'AI生成失败，请检查API配置或稍后重试。',
            'details' => $ai_result['error'] ?? '未知API错误'
        ]);
        return;
    }

    // 调试：记录AI原始响应
    error_log('AI Summary Debug - Raw AI result: ' . print_r($ai_result, true));

    if (empty($ai_result['summary'])) {
        wp_send_json_error(['message' => 'AI未能返回有效的摘要内容。']);
        return;
    }

    // 保存摘要到数据库（新的数据结构包含summary和seo_description）
    update_post_meta($post_id, '_paper_ai_summary', $ai_result);

    // 关键词是可选的，如果存在则更新链接
    $new_slug = '';
    if (!empty($ai_result['keywords'])) {
        $new_slug = implode('-', array_slice($ai_result['keywords'], 0, 3)); // 使用3个关键词（与AI提示词一致）
        $new_slug = sanitize_title($new_slug);

        // 确保slug不为空
        if (!empty($new_slug)) {
            // 获取当前文章以确保slug唯一性
            $current_post = get_post($post_id);
            $unique_slug = wp_unique_post_slug($new_slug, $post_id, $current_post->post_status, $current_post->post_type, 0);

            // 更新文章slug
            $update_result = wp_update_post([
                'ID' => $post_id,
                'post_name' => $unique_slug
            ]);

            // 如果更新失败，返回错误信息
            if (!$update_result) {
                error_log('AI Summary: Failed to update post slug for post ' . $post_id);
                wp_send_json_error(['message' => '文章永久链接更新失败，但摘要已成功生成。请手动设置URL slug。']);
                return;
            } else {
                $new_slug = $unique_slug; // 使用实际的唯一slug
            }
        }
    }

    // 成功返回
    wp_send_json_success([
        'summary' => $ai_result['summary'],
        'seo_description' => $ai_result['seo_description'],
        'keywords' => $ai_result['keywords'],
        'new_slug' => $new_slug
    ]);
}

/**
 * AJAX处理器：更新文章Slug添加关键词
 */
function paper_wp_ajax_update_slug_with_keywords() {
    $verification = paper_wp_verify_ajax_request('paper_wp_ai_generate_nonce');
    if (is_wp_error($verification)) {
        wp_send_json_error(['message' => $verification->get_error_message()], $verification->get_error_data()['status']);
        return;
    }

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $keywords = isset($_POST['keywords']) ? sanitize_text_field($_POST['keywords']) : '';

    if (!$post_id) {
        wp_send_json_error(['message' => '无效的文章ID']);
        return;
    }

    if (empty($keywords)) {
        wp_send_json_error(['message' => '关键词不能为空']);
        return;
    }

    $post = get_post($post_id);
    if (!$post) {
        wp_send_json_error(['message' => '文章不存在']);
        return;
    }

    // 获取当前slug
    $current_slug = $post->post_name;

    // 如果当前slug为空，使用文章标题作为基础
    if (empty($current_slug)) {
        $current_slug = sanitize_title($post->post_title);
    }

    // 直接使用关键词作为新的slug
    $new_slug = $keywords;

    // 确保slug的唯一性
    $new_slug = wp_unique_post_slug($new_slug, $post_id, $post->post_status, $post->post_type, 0);

    // 更新文章slug
    $result = wp_update_post([
        'ID' => $post_id,
        'post_name' => $new_slug
    ]);

    if ($result) {
        wp_send_json_success([
            'new_slug' => $new_slug,
            'message' => '永久链接已成功更新'
        ]);
    } else {
        wp_send_json_error(['message' => '更新永久链接失败']);
    }
}

/**
 * AJAX处理器：测试AI连接
 */
function paper_wp_ajax_test_ai_connection() {
    $verification = paper_wp_verify_ajax_request('paper_wp_ai_test_nonce', 'manage_options');
    if (is_wp_error($verification)) {
        wp_send_json_error(['message' => $verification->get_error_message()], $verification->get_error_data()['status']);
        return;
    }

    // 获取POST数据
    $ai_provider = sanitize_text_field($_POST['ai_provider'] ?? 'openai');
    $ai_api_endpoint = esc_url_raw($_POST['ai_api_endpoint'] ?? 'https://api.openai.com/v1');
    $ai_api_key = sanitize_text_field($_POST['ai_api_key'] ?? '');
    $ai_model = sanitize_text_field($_POST['ai_model'] ?? 'gpt-3.5-turbo');

    // 验证必要参数
    if (empty($ai_api_key)) {
        wp_send_json_error(['message' => 'API Key不能为空']);
        return;
    }

    // 构建测试提示词
    $test_prompt = '请简单回复"Hello World"，不要包含任何额外内容。';

    // 临时覆盖AI设置进行测试
    $original_settings = Paper_Settings_Manager::get('paper_wp_ai_settings', []);
    $test_settings = [
        'ai_provider' => $ai_provider,
        'ai_api_endpoint' => $ai_api_endpoint,
        'ai_api_key' => $ai_api_key,
        'ai_model' => $ai_model
    ];

    // 临时更新设置
    update_option('paper_wp_ai_settings', $test_settings);

    // 调用AI API进行测试
    $test_result = paper_wp_call_ai_api($test_prompt, $ai_api_key);

    // 恢复原始设置
    update_option('paper_wp_ai_settings', $original_settings);

    // 检查结果
    if (is_array($test_result) && isset($test_result['error'])) {
        wp_send_json_error(['message' => $test_result['error']]);
        return;
    }

    if (!$test_result) {
        wp_send_json_error(['message' => 'AI API调用失败，请检查配置']);
        return;
    }

    // 解析响应
    $parsed_result = paper_wp_parse_ai_response($test_result);

    if (!$parsed_result) {
        wp_send_json_error(['message' => 'AI返回的数据格式无效']);
        return;
    }

    // 测试成功
    wp_send_json_success([
        'summary' => 'Hello World',
        'keywords' => ['test', 'connection', 'success'],
        'message' => 'AI连接测试成功！'
    ]);
}

/**
 * ===========================================
 * 发布并查看按钮功能
 * ===========================================
 */

/**
 * 在发布按钮旁边添加"发布并查看"按钮
 */
function paper_wp_add_publish_and_view_button() {
    global $post;
    
    if (!$post) {
        return;
    }
    
    // 添加自定义按钮样式和脚本
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // 在发布按钮后添加"发布并查看"按钮
        var publishButton = $('#publish');
        if (publishButton.length) {
            var publishAndViewButton = $('<input>')
                .attr({
                    'type': 'button',
                    'name': 'publish_and_view',
                    'id': 'publish-and-view',
                    'class': 'button button-primary button-large',
                    'value': '<?php echo esc_js($post->post_status === 'publish' ? '更新并查看' : '发布并查看'); ?>'
                })
                .css({
                    'margin-left': '5px'
                });
            
            publishButton.after(publishAndViewButton);
            
            // 点击事件处理
            publishAndViewButton.on('click', function(e) {
                e.preventDefault();
                
                // 设置隐藏字段标记需要跳转
                if ($('#publish_and_view_flag').length === 0) {
                    $('<input>').attr({
                        type: 'hidden',
                        id: 'publish_and_view_flag',
                        name: 'publish_and_view_flag',
                        value: '1'
                    }).appendTo('#post');
                }
                
                // 触发发布按钮点击
                publishButton.click();
            });
        }
    });
    </script>
    <?php
}
add_action('admin_footer-post.php', 'paper_wp_add_publish_and_view_button');
add_action('admin_footer-post-new.php', 'paper_wp_add_publish_and_view_button');

/**
 * 处理发布并查看的跳转逻辑
 */
function paper_wp_handle_publish_and_view_redirect($location, $post_id) {
    // 检查是否是"发布并查看"操作
    if (isset($_POST['publish_and_view_flag']) && $_POST['publish_and_view_flag'] === '1') {
        // 获取文章永久链接
        $permalink = get_permalink($post_id);
        if ($permalink) {
            return $permalink;
        }
    }
    
    return $location;
}
add_filter('redirect_post_location', 'paper_wp_handle_publish_and_view_redirect', 10, 2);
