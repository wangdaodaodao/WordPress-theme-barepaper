<?php
/**
 * 核心管理模块 - 后台设置系统
 * @author wangdaodao
 * @version 3.1.0
 */

if (!defined('ABSPATH')) exit;

function paper_wp_admin_scripts($hook) {
    if ($hook === 'appearance_page_paper-wp-theme-settings') {
        wp_enqueue_script('paper-admin-settings', get_template_directory_uri() . '/js/admin-settings.js', ['jquery'], BAREPAPER_VERSION, true);
        wp_enqueue_style('paper-admin-style', get_template_directory_uri() . '/css/admin-style.css', [], BAREPAPER_VERSION);
    }
}
add_action('admin_enqueue_scripts', 'paper_wp_admin_scripts');
add_action('wp_ajax_paper_wp_check_update', 'paper_wp_check_update_callback');

function paper_wp_check_update_callback() {
    check_ajax_referer('paper_wp_check_update_nonce', 'nonce');
    
    // 实际场景中，这里应该请求远程 API (如 GitHub API) 获取最新版本
    // 示例：从 GitHub 获取最新 Release
    $api_url = 'https://api.github.com/repos/wangdaodaodao/WordPress-theme-barepaper/releases/latest';
    $response = wp_remote_get($api_url, ['user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()]);
    
    if (is_wp_error($response)) {
        wp_send_json_error(['message' => '无法连接到更新服务器']);
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (isset($data['tag_name'])) {
        // GitHub tag 通常是 'v1.0.0' 格式，需要去掉 'v'
        $version = ltrim($data['tag_name'], 'v');
        wp_send_json_success(['version' => $version]);
    } else {
        // 如果无法获取，可以返回一个模拟的最新版本用于测试，或者返回错误
        // wp_send_json_error(['message' => '无法获取版本信息']);
        
        // 临时模拟：假设最新版本是 3.0.7 (仅供演示)
        wp_send_json_success(['version' => '3.0.7']); 
    }
}

add_action('admin_menu', function() {
    add_theme_page('barepaper主题设置', 'barepaper主题设置', 'manage_options', 'paper-wp-theme-settings', 'paper_wp_theme_settings_page');
});
add_action('admin_init', 'paper_wp_settings_init');
add_action('admin_init', 'paper_wp_handle_reset_defaults');

function paper_wp_handle_reset_defaults() {
    if (isset($_GET['paper_reset_defaults']) && $_GET['paper_reset_defaults'] == 1 && isset($_GET['page']) && $_GET['page'] == 'paper-wp-theme-settings') {
        if (check_admin_referer('paper_reset_defaults_nonce')) {
            $config = paper_wp_get_settings_config();
            // 遍历所有配置项进行重置
            foreach ($config as $tab_data) {
                delete_option($tab_data['option_name']);
            }
            wp_redirect(remove_query_arg(['paper_reset_defaults', '_wpnonce']));
            exit;
        }
    }
}

function paper_wp_settings_init() {
    $config = paper_wp_get_settings_config();
    foreach ($config as $tab_key => $tab_data) {
        register_setting($tab_data['group'], $tab_data['option_name'], $tab_data['sanitize_callback']);
        add_settings_section( 
            $tab_data['section']['id'], 
            '',
            function() use ($tab_data) {
                echo '<p>' . esc_html($tab_data['section']['desc']) . '</p>';
                if (isset($tab_data['section']['callback']) && function_exists($tab_data['section']['callback'])) { 
                    call_user_func($tab_data['section']['callback']); 
                }
            }, 
            'paper-wp-theme-settings-' . $tab_key 
        );

        foreach ($tab_data['fields'] as $field_id => $field) {
            $callback = 'paper_wp_field_callback';
            if ($field['type'] === 'custom' && isset($field['callback']) && function_exists($field['callback'])) { $callback = $field['callback']; }
            $args = ['option_name' => $tab_data['option_name'], 'field_id' => $field_id, 'type' => $field['type']];
            if (isset($field['options'])) { $args['options'] = $field['options']; }
            if (isset($field['placeholder'])) { $args['placeholder'] = $field['placeholder']; }
            add_settings_field( $field_id, $field['label'], $callback, 'paper-wp-theme-settings-' . $tab_key, $tab_data['section']['id'], $args );
        }
    }
}

function paper_wp_get_settings_config() {
    return [
        'modules' => [
            'title' => '模块设置', 'group' => 'paper_wp_module_settings_group', 'option_name' => 'paper_wp_theme_settings',
            'sanitize_callback' => 'paper_wp_module_settings_sanitize',
            'section' => ['id' => 'paper_wp_module_display_section', 'title' => '模块显示设置', 'desc' => '控制各个模块的显示与隐藏。'],
            'fields' => [
                'show_reading_ranking' => ['label' => '显示阅读排行榜', 'type' => 'checkbox'], 'show_like_ranking' => ['label' => '显示点赞排行榜', 'type' => 'checkbox'],
                'show_comment_ranking' => ['label' => '显示评论排行榜', 'type' => 'checkbox'], 'show_random_posts' => ['label' => '显示最新文章', 'type' => 'checkbox'], 'show_recent_album' => ['label' => '显示最新图集', 'type' => 'checkbox'],
                'show_recommended_posts' => ['label' => '显示推荐文章', 'type' => 'checkbox'],
                'show_tag_cloud' => ['label' => '显示标签云', 'type' => 'checkbox'], 'show_search' => ['label' => '显示搜索模块', 'type' => 'checkbox'],
                'show_categories' => ['label' => '显示分类模块', 'type' => 'checkbox'], 'show_archives' => ['label' => '显示归档模块', 'type' => 'checkbox'],
                'show_friend_links' => ['label' => '显示友情链接', 'type' => 'checkbox'], 'show_sidebar_links' => ['label' => '显示其他链接模块', 'type' => 'checkbox'],

                'enable_user_agent' => ['label' => '显示评论者设备信息 (User Agent)', 'type' => 'checkbox', 'desc' => '启用后，将在评论区显示评论者的操作系统和浏览器信息。'],
                'enable_sticky_posts' => ['label' => '启用置顶文章功能', 'type' => 'checkbox', 'desc' => '启用后，可以在文章编辑页面设置文章置顶，置顶文章将显示在首页顶部。'],
                'excerpt_word_limit' => ['label' => '首页文章预览字数限制', 'type' => 'text', 'placeholder' => '500', 'desc' => '设置首页文章预览的固定字数限制。所有文章都按此字数截断显示：<br>• 文章字数不超过设置值时显示全部内容<br>• 文章字数超过设置值时按设置的字数截断<br>默认值：500字'],
                'excerpt_image_mode' => ['label' => '首页文章预览图片显示方式', 'type' => 'select', 'options' => [
                    'all' => '显示所有图片',
                    'random' => '随机显示一张图片',
                    'first' => '仅显示第一张图片',
                    'none' => '不显示图片'
                ], 'desc' => '控制首页文章预览中图片的显示方式'],
            ]
        ],
        'effects' => [
            'title' => '效果设置', 'group' => 'paper_wp_effects_settings_group', 'option_name' => 'paper_wp_effects_settings',
            'sanitize_callback' => 'paper_wp_checkbox_sanitize_callback',
            'section' => ['id' => 'paper_wp_effects_display_section', 'title' => '视觉效果设置', 'desc' => '控制视觉效果的启用与禁用。'],
            'fields' => [
                'theme_mode' => ['label' => '默认主题模式', 'type' => 'select', 'options' => [
                    'auto' => '自动 (跟随系统)',
                    'light' => '浅色',
                    'dark' => '深色'
                ], 'desc' => '选择默认的主题显示模式。如果启用了主题切换,用户可以覆盖此设置。'],
                'site_title' => ['label' => '博客标题', 'type' => 'text', 'placeholder' => '留空则使用WordPress站点标题', 'desc' => '自定义博客标题,留空则使用WordPress后台设置的站点标题。'],
                'site_logo' => ['label' => 'Logo URL', 'type' => 'text', 'placeholder' => 'https://example.com/logo.png', 'desc' => '自定义Logo图片URL,留空则显示文字标题。'],
                'site_subtitle' => ['label' => '副标题', 'type' => 'text', 'placeholder' => '留空则使用WordPress副标题', 'desc' => '自定义副标题,显示在标题右侧,留空则使用WordPress后台设置的副标题。'],
                'site_start_date' => ['label' => '建站时间', 'type' => 'text', 'placeholder' => '2024-01-01', 'desc' => '网站建立时间,格式: YYYY-MM-DD (例如: 2024-01-01)。留空则使用第一篇文章的发布时间。用于计算网站运行天数。'],
                'enable_sponsor' => ['label' => '启用赞助模块', 'type' => 'checkbox', 'desc' => '启用后将在文章页面显示赞助二维码。'],
                'sponsor_wechat_qr' => ['label' => '微信公众号/微信二维码URL', 'type' => 'text', 'placeholder' => 'https://example.com/wechat-qr.jpg', 'desc' => '微信公众号或微信收款二维码图片URL。'],
                'sponsor_alipay_qr' => ['label' => '支付宝收款码URL', 'type' => 'text', 'placeholder' => 'https://example.com/alipay-qr.jpg', 'desc' => '支付宝收款二维码图片URL。'],
                'stats_code' => ['label' => '访问统计代码', 'type' => 'textarea', 'desc' => '在此输入第三方统计代码(如百度统计、Google Analytics等),代码将插入到页面head区域。'],
                'footer_html' => ['label' => '页脚HTML代码', 'type' => 'textarea', 'desc' => '在此输入自定义页脚HTML代码,支持HTML标签。代码将显示在页脚区域。'],
            ]
        ],
        'editor' => [
            'title' => '编辑器设置', 'group' => 'paper_wp_editor_settings_group', 'option_name' => 'paper_wp_editor_settings',
            'sanitize_callback' => 'paper_wp_checkbox_sanitize_callback',
            'section' => ['id' => 'paper_wp_editor_section', 'title' => '编辑器设置', 'desc' => '配置编辑器的显示和功能。'],
            'fields' => [
                'disable_default_editor' => ['label' => '启用经典编辑器（推荐）', 'type' => 'checkbox', 'desc' => '启用后，将使用经典编辑器替代块编辑器（Gutenberg），并自动移除块编辑器样式。'],
                'enable_wddmds' => ['label' => '启用Markdown和shortcode语法支持', 'type' => 'checkbox', 'desc' => '启用后，可以在文章编辑器中使用Markdown语法和shortcode短代码功能，提升编辑体验。'],
                'disable_emojis' => ['label' => '禁用 Emoji 功能', 'type' => 'checkbox', 'desc' => '禁用 WordPress Emoji 功能，移除相关脚本和样式，减少资源加载。'],
            ]
        ],
        'ads' => [
            'title' => '广告设置', 'group' => 'paper_wp_ad_settings_group', 'option_name' => 'paper_wp_ad_settings',
            'sanitize_callback' => 'paper_wp_ad_settings_sanitize',
            'section' => ['id' => 'paper_wp_advertisement_section', 'title' => '广告设置', 'desc' => '配置广告的显示和代码。', 'callback' => 'paper_wp_advertisement_section_callback'],
            'fields' => [
                'show_header_ad' => ['label' => '显示顶部广告', 'type' => 'checkbox'], 'header_ad_code' => ['label' => '顶部广告代码', 'type' => 'textarea'],
                'show_post_bottom_ad' => ['label' => '显示文章底部广告', 'type' => 'checkbox'], 'post_bottom_ad_code' => ['label' => '文章底部广告代码', 'type' => 'textarea'],
                'show_sidebar_ad' => ['label' => '显示侧边栏广告', 'type' => 'checkbox'], 'sidebar_ad_code' => ['label' => '侧边栏广告代码', 'type' => 'textarea'],
            ]
        ],
        'friend-links' => [
            'title' => '友情链接', 'group' => 'paper_wp_friend_links_settings_group', 'option_name' => 'paper_wp_friend_links',
            'sanitize_callback' => 'paper_wp_friend_links_settings_sanitize',
            'section' => ['id' => 'paper_wp_friend_links_section', 'title' => '友情链接管理', 'desc' => '管理友情链接的添加、编辑和删除。'],
            'fields' => [ 'friend_links_list' => ['label' => '友情链接列表', 'type' => 'custom', 'callback' => 'paper_wp_friend_links_list_callback'], ]
        ],
        'cache' => [
            'title' => '缓存管理', 'group' => 'paper_wp_cache_settings_group', 'option_name' => 'paper_wp_cache_settings',
            'sanitize_callback' => 'paper_wp_checkbox_sanitize_callback',
            'section' => ['id' => 'paper_wp_cache_section', 'title' => '缓存管理', 'desc' => '管理和清理各种类型的缓存数据。', 'callback' => 'paper_wp_cache_section_callback'],
            'fields' => []
        ],

        'admin' => [
            'title' => '后台管理', 'group' => 'paper_wp_admin_settings_group', 'option_name' => 'paper_wp_admin_settings',
            'sanitize_callback' => 'paper_wp_admin_settings_sanitize',
            'section' => ['id' => 'paper_wp_admin_section', 'title' => '后台管理与优化', 'desc' => '精简 WordPress 后台界面，移除无用功能，提升管理体验。'],
            'fields' => [
                'disable_admin_bar_subscribers' => ['label' => '禁用非管理员工具栏', 'type' => 'checkbox', 'desc' => '启用后，非管理员用户登录后将看不到顶部的黑色工具栏。'],
                'restrict_admin_access' => ['label' => '限制非管理员访问后台', 'type' => 'checkbox', 'desc' => '启用后，非管理员用户访问后台将被重定向到首页。'],
                'disable_dashboard_page' => ['label' => '禁用仪表盘页面', 'type' => 'checkbox', 'desc' => '启用后，将移除"仪表盘"菜单，登录后自动跳转到文章列表页。'],
                'clean_dashboard' => ['label' => '精简仪表盘', 'type' => 'checkbox', 'desc' => '移除仪表盘中的"概览"、"活动"、"快速草稿"、"WordPress新闻"、"站点健康"等模块。'],
                'clean_admin_bar' => ['label' => '精简顶部工具栏', 'type' => 'checkbox', 'desc' => '移除顶部工具栏左上角的 WordPress Logo 和评论图标。'],
                'clean_footer' => ['label' => '移除页脚信息', 'type' => 'checkbox', 'desc' => '移除后台底部的"感谢使用 WordPress"和版本号信息。'],
                'remove_menu_comments' => ['label' => '移除"评论"菜单', 'type' => 'checkbox', 'desc' => '从左侧菜单中移除"评论"选项（如果您使用第三方评论系统，建议开启）。'],
                'remove_menu_tools' => ['label' => '移除"工具"菜单', 'type' => 'checkbox', 'desc' => '从左侧菜单中移除"工具"选项。'],
                
                // 后台性能优化
                'optimize_heartbeat' => ['label' => '优化心跳检测 (Heartbeat)', 'type' => 'checkbox', 'desc' => '将后台心跳检测频率降低至 60 秒，减少服务器压力。'],
                'increase_autosave_interval' => ['label' => '延长自动保存间隔', 'type' => 'checkbox', 'desc' => '将文章自动保存间隔从默认的 1 分钟延长至 5 分钟，减少数据库写入和卡顿。'],
                'hide_admin_notices' => ['label' => '屏蔽后台通知', 'type' => 'checkbox', 'desc' => '隐藏大部分插件和主题的后台通知横幅，让界面更清爽（保留错误和更新提示）。'],
                'disable_file_editor' => ['label' => '禁用文件编辑器', 'type' => 'checkbox', 'desc' => '禁用后台的主题和插件文件编辑器，提高安全性并减少文件系统检查。'],
            ]
        ],
        'about' => [
            'title' => '关于主题', 'group' => 'paper_wp_about_settings_group', 'option_name' => 'paper_wp_about_settings',
            'sanitize_callback' => '',
            'section' => ['id' => 'paper_wp_about_section', 'title' => '关于主题', 'desc' => '', 'callback' => 'paper_wp_about_section_callback'],
            'fields' => []
        ],
    ];
}

function paper_wp_field_callback($args) {
    $options = get_option($args['option_name']); $field_id = esc_attr($args['field_id']); $field_name = esc_attr($args['option_name'] . '[' . $field_id . ']');
    switch ($args['type']) {
        case 'checkbox':
            $checked = isset($options[$field_id]) && $options[$field_id] ? 'checked' : '';
            echo "<label class='switch'>
                <input type='checkbox' id='{$field_id}' name='{$field_name}' value='1' {$checked}>
                <span class='slider'></span>
            </label>";
            $config = paper_wp_get_settings_config();
            foreach ($config as $tab) {
                if (isset($tab['fields'][$field_id]['desc'])) {
                    echo "<div style='margin-top: 5px; font-size: 12px; color: #666; line-height: 1.4;'>" . wp_kses_post($tab['fields'][$field_id]['desc']) . "</div>";
                    break;
                }
            }
            break;
        case 'textarea': 
            $value = isset($options[$field_id]) ? $options[$field_id] : '';
            $value = esc_textarea($value);
            echo "<textarea id='{$field_id}' name='{$field_name}' rows='5' cols='50' class='large-text code'>{$value}</textarea>"; 
            $config = paper_wp_get_settings_config();
            foreach ($config as $tab) {
                if (isset($tab['fields'][$field_id]['desc'])) {
                    echo "<div style='margin-top: 5px; font-size: 12px; color: #666; line-height: 1.4;'>" . wp_kses_post($tab['fields'][$field_id]['desc']) . "</div>";
                    break;
                }
            }
            break;
        case 'select': $value = isset($options[$field_id]) ? $options[$field_id] : ''; echo "<select id='{$field_id}' name='{$field_name}'>"; foreach ($args['options'] as $key => $label) { $selected = $value === $key ? 'selected' : ''; echo "<option value='{$key}' {$selected}>{$label}</option>"; } echo "</select>"; break;
        case 'text': $value = isset($options[$field_id]) ? esc_attr($options[$field_id]) : ''; $placeholder = isset($args['placeholder']) ? 'placeholder="' . esc_attr($args['placeholder']) . '"' : ''; echo "<input type='text' id='{$field_id}' name='{$field_name}' value='{$value}' class='regular-text' {$placeholder} >"; 
            $config = paper_wp_get_settings_config();
            foreach ($config as $tab) {
                if (isset($tab['fields'][$field_id]['desc'])) {
                    echo "<div style='margin-top: 5px; font-size: 12px; color: #666; line-height: 1.4;'>" . wp_kses_post($tab['fields'][$field_id]['desc']) . "</div>";
                    break;
                }
            }
            break;
    }
}

function paper_wp_checkbox_sanitize_callback($input) {
    $output = []; $config = paper_wp_get_settings_config();

    // 处理所有字段类型
    foreach ($config as $tab) {
        foreach ($tab['fields'] as $field_id => $field) {
            if ($field['type'] === 'checkbox') {
                // 复选框：只在选中时保存1
                $output[$field_id] = isset($input[$field_id]) ? 1 : 0;
            } elseif ($field['type'] === 'select') {
                // 下拉选择：验证选项并保存
                if (isset($input[$field_id])) {
                    $value = sanitize_text_field($input[$field_id]);
                    if (isset($field['options']) && array_key_exists($value, $field['options'])) {
                        $output[$field_id] = $value;
                    } else {
                        // 如果值无效，使用第一个选项作为默认值
                        $output[$field_id] = key($field['options']);
                    }
                } else {
                    // 如果未提交，使用第一个选项作为默认值
                    $output[$field_id] = key($field['options']);
                }
            } elseif ($field['type'] === 'text') {
                // 文本框：清理并保存
                $output[$field_id] = isset($input[$field_id]) ? sanitize_text_field($input[$field_id]) : '';
            } elseif ($field['type'] === 'textarea') {
                // 文本区域：清理并保存
                $output[$field_id] = isset($input[$field_id]) ? wp_kses_post($input[$field_id]) : '';
            }
        }
    }

    // 缓存同步：设置变更时清理相关缓存
    if (function_exists('paper_wp_clear_sidebar_cache')) {
        paper_wp_clear_sidebar_cache();
    }

    return $output;
}

function paper_wp_module_settings_sanitize($input) {
    // 获取现有设置，保留未提交的字段
    $existing = Paper_Settings_Manager::get('paper_wp_theme_settings', []);
    $output = $existing;

    // 处理复选框字段
    $checkbox_fields = [
        'show_reading_ranking', 'show_like_ranking', 'show_comment_ranking',
        'show_random_posts', 'show_recent_album', 'show_recommended_posts',
        'show_tag_cloud', 'show_search', 'show_categories', 'show_archives',
        'show_friend_links', 'show_sidebar_links',
        'enable_user_agent', 'enable_sticky_posts'
    ];
    foreach ($checkbox_fields as $field_id) {
        if (isset($input[$field_id])) {
            $output[$field_id] = 1;
        } else {
            // 复选框未选中时，确保移除该字段
            unset($output[$field_id]);
        }
    }
    // 处理文章预览字数限制字段
    if (isset($input['excerpt_word_limit'])) {
        $word_limit = trim($input['excerpt_word_limit']);
        if (is_numeric($word_limit) && intval($word_limit) > 0) {
            $output['excerpt_word_limit'] = intval($word_limit);
        } else {
            // 无效值或空值，使用默认值500
            $output['excerpt_word_limit'] = 500;
        }
    } else {
        // 如果字段未提交，但现有值无效，也设置为默认值
        if (!isset($output['excerpt_word_limit']) || empty($output['excerpt_word_limit']) || !is_numeric($output['excerpt_word_limit']) || intval($output['excerpt_word_limit']) <= 0) {
            $output['excerpt_word_limit'] = 500;
        }
    }

    // 处理图片显示方式字段
    if (isset($input['excerpt_image_mode'])) {
        $mode = sanitize_text_field($input['excerpt_image_mode']);
        $valid_modes = ['all', 'random', 'first', 'none'];
        if (in_array($mode, $valid_modes)) {
            $output['excerpt_image_mode'] = $mode;
        } else {
            $output['excerpt_image_mode'] = 'all'; // 默认值
        }
    }

    // 缓存同步：设置变更时清理相关缓存
    if (function_exists('paper_wp_clear_sidebar_cache')) {
        paper_wp_clear_sidebar_cache();
    }

    return $output;
}

function paper_wp_ad_settings_sanitize($input) {
    $output = [];
    foreach (['show_header_ad', 'show_post_bottom_ad', 'show_sidebar_ad'] as $key) { $output[$key] = isset($input[$key]) ? 1 : 0; }
    foreach (['header_ad_code', 'post_bottom_ad_code', 'sidebar_ad_code'] as $key) { $output[$key] = isset($input[$key]) ? wp_kses_post($input[$key]) : ''; }

    // 缓存同步：设置变更时清理相关缓存
    if (function_exists('paper_wp_clear_sidebar_cache')) {
        paper_wp_clear_sidebar_cache();
    }

    return $output;
}

function paper_wp_friend_links_settings_sanitize($input) {
    $output = [];
    if (isset($input) && is_array($input)) {
        foreach ($input as $link) {
            if (!empty($link['name']) && !empty($link['url'])) {
                $output[] = ['name' => sanitize_text_field($link['name']), 'url' => esc_url_raw($link['url']), 'description' => sanitize_text_field($link['description'] ?? '')];
            }
        }
    }

    // 缓存同步：设置变更时清理相关缓存
    if (function_exists('paper_wp_clear_sidebar_cache')) {
        paper_wp_clear_sidebar_cache();
    }

    return $output;
}



function paper_wp_admin_settings_sanitize($input) {
    $output = [];
    $checkbox_fields = [
        'disable_admin_bar_subscribers', 'restrict_admin_access', 'disable_dashboard_page',
        'clean_dashboard', 'clean_admin_bar', 'clean_footer',
        'remove_menu_comments', 'remove_menu_tools',
        'optimize_heartbeat', 'increase_autosave_interval', 'hide_admin_notices', 'disable_file_editor'
    ];
    foreach ($checkbox_fields as $field) {
        $output[$field] = isset($input[$field]) ? 1 : 0;
    }
    return $output;
}






/**
 * 广告设置页面回调函数
 */
function paper_wp_advertisement_section_callback() {
    echo '<button type="button" id="show-ad-examples" class="button" style="margin-top: 10px;">查看广告示例代码</button><div id="ad-examples-modal" style="display: none;"></div>';
}



/**
 * 执行缓存清理操作 - 简化版本
 */
function paper_wp_execute_cache_clear() {
    try {
        // 使用前台缓存清理逻辑
        paper_wp_clear_cache();

        // 清理WordPress缓存
        wp_cache_flush();

        return '所有缓存清理完成';

    } catch (Exception $e) {
        error_log('Paper WP Cache Clear Error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * 缓存管理页面回调函数 - 简化版本
 */
function paper_wp_cache_section_callback() {
    // 处理缓存清理请求
    if (isset($_POST['paper_wp_clear_cache']) && check_admin_referer('paper_wp_clear_cache_action', 'paper_wp_clear_cache_nonce')) {
        try {
            $message = paper_wp_execute_cache_clear();
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '！</p></div>';
        } catch (Exception $e) {
            echo '<div class="notice notice-error is-dismissible"><p>缓存清理过程中发生错误：' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    ?>
    <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-top: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <h3 style="margin-top: 0; margin-bottom: 15px;">缓存管理</h3>
        <p style="margin-bottom: 15px; color: #646970;">清理所有缓存数据，清理后相关数据会在下次访问时重新生成。</p>

        <form method="post" action="">
            <?php wp_nonce_field('paper_wp_clear_cache_action', 'paper_wp_clear_cache_nonce'); ?>
            <button type="submit" name="paper_wp_clear_cache" value="1" class="button button-primary" onclick="return confirm('确定要清理所有缓存吗？');">
                清理所有缓存
            </button>
        </form>

        <div style="margin-top: 20px; padding: 12px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 2px;">
            <p style="margin: 0; font-size: 13px; color: #1d2327;">
                <strong>提示：</strong>
                <br>• 缓存会自动在设置的时间间隔后过期，无需手动清理
                <br>• 清理缓存后，相关数据会在下次访问时重新生成
                <br>• 建议仅在更新内容后缓存未及时刷新时手动清理
            </p>
        </div>
    </div>
    <?php
}



/**
 * 关于主题页面回调函数
 */
function paper_wp_about_section_callback() {
    $theme = wp_get_theme();
    $current_version = $theme->get('Version');
    ?>
    <div style="max-width: 800px;">
        <h3 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">版本信息</h3>
        <p>
            <strong>当前版本：</strong> <?php echo esc_html($current_version); ?>
            <span id="paper-theme-version-check" style="margin-left: 10px;">
                <button type="button" class="button button-small" id="check-update-btn">检查更新</button>
            </span>
        </p>
        <div id="version-check-message" style="margin-top: 5px; font-size: 13px;"></div>
        
        <h3 style="margin-top: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">问题反馈</h3>
        <p>遇到问题或有建议？欢迎通过以下方式反馈：</p>
        <ul style="margin-left: 20px;">
            <li>在 <a href="https://github.com/wangdaodaodao/WordPress-theme-barepaper/issues" target="_blank">GitHub Issues</a> 提交问题</li>
            <li>访问 <a href="https://blog.062200.xyz/2025/wordpress-theme-barepaper/" target="_blank">主题文档</a> 查看使用说明</li>
        </ul>
        
        <h3 style="margin-top: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">仓库地址</h3>
        <p>
            <strong>Github：</strong> <a href="https://github.com/wangdaodaodao/WordPress-theme-barepaper" target="_blank">https://github.com/wangdaodaodao/WordPress-theme-barepaper</a><br>
            <strong>博客：</strong> <a href="https://blog.062200.xyz/2025/wordpress-theme-barepaper/" target="_blank">https://blog.062200.xyz/2025/wordpress-theme-barepaper/</a>
        </p>
        
        <div style="margin-top: 20px; padding: 15px; background: #f0f7ff; border-left: 4px solid #007cba; border-radius: 4px;">
            <p style="margin: 0 0 10px 0; font-weight: 600; color: #007cba;">💡 支持开源</p>
            <p style="margin: 0; line-height: 1.8; color: #555;">
                如果这个主题对您有帮助，欢迎：<br>
                • 在 <a href="https://github.com/wangdaodaodao/WordPress-theme-barepaper" target="_blank" style="color: #007cba;">GitHub</a> 上给项目一个 Star<br>
                • 分享给更多需要的朋友<br>
                • 通过文章页面的赞助功能支持开发
            </p>
        </div>
        
        <div style="margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px; text-align: center;">
            <p style="margin: 0 0 15px 0; font-weight: 600; color: #333;">📱 关注微信公众号</p>
            <img src="https://files.062200.xyz/2025/12/3197e48cc05ac69ee347895594a28817.jpg" alt="微信公众号二维码" style="max-width: 200px; height: auto; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <p style="margin: 15px 0 0 0; font-size: 13px; color: #666;">扫码关注，获取主题更新和使用技巧</p>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#check-update-btn').click(function() {
            var btn = $(this);
            var msg = $('#version-check-message');
            
            btn.prop('disabled', true).text('检查中...');
            msg.html('');

            // 模拟检查更新 (实际项目中应替换为真实的 API 请求)
            // 这里我们假设从 GitHub API 获取最新 Release 信息
            // 注意：由于 GitHub API 限制和跨域问题，前端直接请求可能不稳定，建议通过后端代理
            // 这里演示通过后端 AJAX 请求的方式（需要注册对应的 AJAX action）
            
            $.post(ajaxurl, {
                action: 'paper_wp_check_update',
                nonce: '<?php echo wp_create_nonce("paper_wp_check_update_nonce"); ?>'
            }, function(response) {
                if (response.success) {
                    var latestVersion = response.data.version;
                    var currentVersion = '<?php echo $current_version; ?>';
                    
                    if (versionCompare(latestVersion, currentVersion) > 0) {
                        msg.html('<span style="color: #d63638;">发现新版本 ' + latestVersion + '！请前往仓库下载更新。</span>');
                    } else {
                        msg.html('<span style="color: #46b450;">当前已是最新版本。</span>');
                    }
                } else {
                    msg.html('<span style="color: #d63638;">检查更新失败：' + (response.data.message || '未知错误') + '</span>');
                }
                btn.prop('disabled', false).text('检查更新');
            }).fail(function() {
                msg.html('<span style="color: #d63638;">网络请求失败，请稍后重试。</span>');
                btn.prop('disabled', false).text('检查更新');
            });
        });

        // 简单的版本号比较函数
        function versionCompare(v1, v2) {
            var v1parts = v1.split('.');
            var v2parts = v2.split('.');
            for (var i = 0; i < Math.max(v1parts.length, v2parts.length); ++i) {
                var val1 = parseInt(v1parts[i] || 0);
                var val2 = parseInt(v2parts[i] || 0);
                if (val1 > val2) return 1;
                if (val1 < val2) return -1;
            }
            return 0;
        }
    });
    </script>
    <?php
}

/**
 * 友情链接列表回调函数
 */
function paper_wp_friend_links_list_callback() {
    $friend_links = get_option('paper_wp_friend_links', []);
    ?>
    <div id="friend-links-container"><div id="friend-links-list">
        <?php if (!empty($friend_links)) : foreach ($friend_links as $index => $link) : ?>
            <div class="friend-link-item" style="display: flex; align-items: center; margin-bottom: 10px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                <input type="text" name="paper_wp_friend_links[<?php echo $index; ?>][name]" value="<?php echo esc_attr($link['name']); ?>" placeholder="链接名称" style="flex: 1; margin-right: 10px;" />
                <input type="url" name="paper_wp_friend_links[<?php echo $index; ?>][url]" value="<?php echo esc_attr($link['url']); ?>" placeholder="链接地址" style="flex: 2; margin-right: 10px;" />
                <input type="text" name="paper_wp_friend_links[<?php echo $index; ?>][description]" value="<?php echo esc_attr($link['description'] ?? ''); ?>" placeholder="描述（可选）" style="flex: 2; margin-right: 10px;" />
                <button type="button" class="button remove-friend-link" style="background: #dc3545; color: white; border: none;">删除</button>
            </div>
        <?php endforeach; endif; ?>
    </div><button type="button" id="add-friend-link" class="button" style="margin-top: 10px;">添加友情链接</button></div>
    <?php
}

/**
 * AI测试按钮回调函数
 */
function paper_wp_ai_test_button_callback() {
    ?>
    <button type="button" id="test-ai-connection" class="button button-secondary" style="margin-top: 10px;">
        <span class="dashicons dashicons-update" style="margin-right: 5px;"></span>
        测试AI连接
    </button>
    <div id="ai-test-result" style="margin-top: 10px; display: none;"></div>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#test-ai-connection').on('click', function() {
            var $button = $(this);
            var $result = $('#ai-test-result');

            // 显示加载状态
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin" style="margin-right: 5px;"></span>测试中...');
            $result.hide().html('');

            // 获取当前设置的值
            var settings = {
                ai_provider: $('select[name="paper_wp_ai_settings[ai_provider]"]').val(),
                ai_api_endpoint: $('input[name="paper_wp_ai_settings[ai_api_endpoint]"]').val(),
                ai_api_key: $('input[name="paper_wp_ai_settings[ai_api_key]"]').val(),
                ai_model: $('input[name="paper_wp_ai_settings[ai_model]"]').val(),
                nonce: '<?php echo wp_create_nonce('paper_wp_ai_test_nonce'); ?>'
            };

            // 发送AJAX请求
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'paper_wp_test_ai_connection',
                    ai_provider: settings.ai_provider,
                    ai_api_endpoint: settings.ai_api_endpoint,
                    ai_api_key: settings.ai_api_key,
                    ai_model: settings.ai_model,
                    nonce: settings.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<div style="color: #28a745; padding: 10px; border: 1px solid #28a745; border-radius: 4px; background: #d4edda;">' +
                            '<strong>✅ 测试成功！</strong><br>' +
                            'AI摘要: ' + response.data.summary + '<br>' +
                            '关键词: ' + response.data.keywords.join(', ') +
                            '</div>');
                    } else {
                        $result.html('<div style="color: #dc3545; padding: 10px; border: 1px solid #dc3545; border-radius: 4px; background: #f8d7da;">' +
                            '<strong>❌ 测试失败！</strong><br>' +
                            response.data.message +
                            '</div>');
                    }
                },
                error: function() {
                    $result.html('<div style="color: #dc3545; padding: 10px; border: 1px solid #dc3545; border-radius: 4px; background: #f8d7da;">' +
                        '<strong>❌ 网络错误！</strong><br>' +
                        '请检查网络连接或稍后重试。' +
                        '</div>');
                },
                complete: function() {
                    // 恢复按钮状态
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-update" style="margin-right: 5px;"></span>测试AI连接');
                    $result.show();
                }
            });
        });
    });
    </script>
    <style>
        .dashicons.spin {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    <?php
}

/**
 * 主题设置页面
 */
function paper_wp_theme_settings_page() {
    $config = paper_wp_get_settings_config();
    $active_tab = isset($_GET['tab']) && array_key_exists($_GET['tab'], $config) ? $_GET['tab'] : 'modules';
    ?>


    <div class="wrap">
        <div class="paper-admin-header" style="display: flex; justify-content: flex-end; align-items: center; margin-bottom: 10px; padding-right: 20px;">
            <?php if (!empty($config[$active_tab]['fields'])) : ?>
            <div class="paper-admin-actions">
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=paper-wp-theme-settings&paper_reset_defaults=1'), 'paper_reset_defaults_nonce'); ?>" class="button" onclick="return confirm('确定要恢复所有设置到默认状态吗？此操作将清空所有配置，且不可逆！');" style="margin-right: 10px;">恢复默认设置</a>
                <button type="submit" form="paper-settings-form" class="button button-primary">保存设置</button>
            </div>
            <?php endif; ?>
        </div>
        
        <h2 class="nav-tab-wrapper">
            <?php foreach ($config as $tab_key => $tab_data) : ?>
                <a href="?page=paper-wp-theme-settings&tab=<?php echo esc_attr($tab_key); ?>" class="nav-tab <?php echo $active_tab == $tab_key ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html($tab_data['title']); ?>
                </a>
            <?php endforeach; ?>
        </h2>
        <form id="paper-settings-form" action="options.php" method="post">
            <?php
            settings_fields($config[$active_tab]['group']);
            do_settings_sections('paper-wp-theme-settings-' . $active_tab);
            ?>
        </form>
    </div>
    <?php
}




