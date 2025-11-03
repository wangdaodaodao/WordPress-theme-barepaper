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
    add_action('wp_enqueue_scripts', 'paper_wp_enqueue_frontend_assets');

    // AJAX 处理器
    add_action('wp_ajax_ai_generate', 'paper_wp_ajax_generate_summary_and_slug');
    add_action('wp_ajax_update_slug_with_keywords', 'paper_wp_ajax_update_slug_with_keywords');
}
add_action('init', 'paper_wp_editor_enhancements_init');

/**
 * 前端脚本和样式加载函数
 */
function paper_wp_enqueue_frontend_assets() {
    // 功能已禁用 - 具体逻辑已移除，保留函数调用结构
    // 获取图片代理设置
    // $image_proxy_settings = get_option('paper_wp_image_proxy_settings', []);
    // if (empty($image_proxy_settings['enable_image_proxy'])) { return; }
    // wp_enqueue_script('paper-image-proxy', ...);
    // wp_localize_script('paper-image-proxy', 'paperEditor', ...);
}

/**
 * 统一的编辑器脚本和样式加载函数
 */
function paper_wp_enqueue_editor_assets($hook) {
    // 只在文章编辑页面加载
    if (!in_array($hook, ['post.php', 'post-new.php'])) {
        return;
    }

    // 获取所有需要的设置
    $editor_settings = get_option('paper_wp_editor_settings', []);
    $ai_settings = get_option('paper_wp_ai_settings', []);

    // 加载核心模块
    wp_enqueue_script('editor-core', get_template_directory_uri() . '/js/editor-core.js', ['quicktags', 'jquery'], BAREPAPER_VERSION, true);
    wp_enqueue_script('editor-modal', get_template_directory_uri() . '/js/editor-modal.js', ['editor-core'], BAREPAPER_VERSION, true);

    // 功能已禁用 - 具体逻辑已移除，保留函数调用结构
    // 加载功能模块
    // wp_enqueue_script('editor-doubanbook', get_template_directory_uri() . '/js/editor-doubanbook.js', ['editor-modal'], '3.1.8', true);
    // wp_enqueue_script('editor-markdown', get_template_directory_uri() . '/js/editor-markdown.js', ['editor-modal'], BAREPAPER_VERSION, true);
    // wp_enqueue_script('editor-shortcodes', get_template_directory_uri() . '/js/editor-shortcodes.js', ['editor-modal'], BAREPAPER_VERSION, true);
    // wp_enqueue_script('editor-ai', get_template_directory_uri() . '/js/editor-ai.js', ['editor-modal'], BAREPAPER_VERSION, true);

    // 加载我们新的主JS文件来处理QTags
    wp_enqueue_script(
        'paper-editor-enhancements',
        get_template_directory_uri() . '/js/editor-enhancements.js',
        ['quicktags', 'jquery', 'editor-core', 'editor-modal'], // 移除已禁用功能的依赖
        BAREPAPER_VERSION,
        true
    );

    // 获取图片代理设置
    // 功能已禁用 - 具体逻辑已移除，保留函数调用结构
    // $image_proxy_settings = get_option('paper_wp_image_proxy_settings', []);
    // if (!empty($image_proxy_settings['enable_image_proxy'])) { ... }
    $proxy_domains = [];
    $proxy_keywords = [];

    // 使用 wp_localize_script 传递数据
    wp_localize_script('paper-editor-enhancements', 'paperEditor', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('paper_wp_ai_generate_nonce'),
        'post_id' => get_post() ? get_post()->ID : 0,
        'settings' => [
            'is_enhancement_enabled' => false, // 功能已禁用 - Markdown编辑器支持已禁用
            // 功能已禁用 - 具体逻辑已移除，保留函数调用结构
            'is_ai_enabled' => false, // !empty($ai_settings['ai_summary_enabled']) && !empty($ai_settings['ai_api_key']),
            'is_image_proxy_enabled' => false, // !empty($image_proxy_settings['enable_image_proxy']),
            'image_proxy' => [
                'domains' => $proxy_domains,
                'keywords' => $proxy_keywords,
            ],
        ],
        'i18n' => [
            'enhancement_needed' => '此功能需要启用编辑器增强模式。请前往后台"barepaper主题设置" → "编辑器设置" → 启用"编辑器增强模式"。',
            'ai_not_configured' => 'AI摘要功能未启用或未配置API密钥。请在主题设置中配置AI功能。',
        ]
    ]);

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
    // 功能已禁用 - 具体逻辑已移除，保留函数调用结构
    // $ai_settings = get_option('paper_wp_ai_settings', []);
    // if (empty($ai_settings['ai_summary_enabled'])) { ... }
    // $ai_result = paper_wp_generate_ai_data($content, $ai_settings['ai_api_key']);
    // if (!$ai_result || !empty($ai_result['error'])) { ... }
    // update_post_meta($post_id, '_paper_ai_summary', $ai_result);
    // $new_slug = ...; wp_update_post([...]);
    
    // 返回错误，因为功能已禁用
    wp_send_json_error(['message' => 'AI摘要功能未启用']);
    return;
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

    // 将关键词添加到slug后面
    $new_slug = $current_slug . '-' . $keywords;

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
