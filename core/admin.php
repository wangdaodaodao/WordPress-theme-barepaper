<?php
/**
 * 核心管理模块 - 后台设置系统
 * @author wangdaodao
 * @version 3.0.6
 */

if (!defined('ABSPATH')) exit;

function paper_wp_admin_scripts($hook) {
    if ($hook === 'appearance_page_paper-wp-theme-settings') {
        wp_enqueue_script('paper-admin-settings', get_template_directory_uri() . '/js/admin-settings.js', ['jquery'], BAREPAPER_VERSION, true);
    }
}
add_action('admin_enqueue_scripts', 'paper_wp_admin_scripts');

add_action('admin_menu', function() {
    add_theme_page('barepaper主题设置', 'barepaper主题设置', 'manage_options', 'paper-wp-theme-settings', 'paper_wp_theme_settings_page');
});
add_action('admin_init', 'paper_wp_settings_init');

function paper_wp_settings_init() {
    $config = paper_wp_get_settings_config();
    foreach ($config as $tab_key => $tab_data) {
        // 只有当sanitize_callback存在时才注册设置
        if (!empty($tab_data['sanitize_callback'])) {
            register_setting($tab_data['group'], $tab_data['option_name'], $tab_data['sanitize_callback']);
        } else {
            // 对于没有sanitize_callback的tab，注册一个空的设置组
            register_setting($tab_data['group'], $tab_data['option_name']);
        }
        add_settings_section( 
            $tab_data['section']['id'], 
            $tab_data['section']['title'],
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
                'show_poetry_recommendation' => ['label' => '显示诗词推荐', 'type' => 'checkbox'], 'show_sponsor_module' => ['label' => '显示赞助模块', 'type' => 'checkbox'],
                'show_blog_stats' => ['label' => '显示博客统计', 'type' => 'checkbox'],
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
                'show_ribbons_effect' => ['label' => '显示背景丝带效果', 'type' => 'checkbox'], 'show_cursor_effect' => ['label' => '显示鼠标点击特效', 'type' => 'checkbox'],
                'show_theme_toggle' => ['label' => '启用主题切换功能', 'type' => 'checkbox'],
            ]
        ],
        'editor' => [
            'title' => '编辑器设置', 'group' => 'paper_wp_editor_settings_group', 'option_name' => 'paper_wp_editor_settings',
            'sanitize_callback' => 'paper_wp_checkbox_sanitize_callback',
            'section' => ['id' => 'paper_wp_editor_section', 'title' => '编辑器设置', 'desc' => '配置编辑器的显示和功能。'],
            'fields' => [
                'disable_default_editor' => ['label' => '启用经典编辑器（推荐）', 'type' => 'checkbox'], 'enable_wddmds' => ['label' => '启用WDD-tinymce插件（支持Markdown语法）', 'type' => 'checkbox'],
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
        'performance' => [
            'title' => '性能优化', 'group' => 'paper_wp_performance_settings_group', 'option_name' => 'paper_wp_performance_settings',
            'sanitize_callback' => 'paper_wp_checkbox_sanitize_callback',
            'section' => ['id' => 'paper_wp_performance_section', 'title' => '性能优化设置', 'desc' => '配置性能优化相关的功能。', 'callback' => 'paper_wp_performance_section_callback'],
            'fields' => [
                'enable_service_worker' => ['label' => '启用Service Worker缓存', 'type' => 'checkbox', 'desc' => '启用Service Worker进行资源缓存，提升页面加载速度<br><strong>推荐：</strong>高性能服务器 ✅ | 中等性能服务器 ✅ | 低性能服务器 ❌'],
                'enable_css_async_loading' => ['label' => '启用CSS异步加载', 'type' => 'checkbox', 'desc' => '异步加载CSS文件，避免阻塞页面渲染，提升首屏速度<br><strong>推荐：</strong>高性能服务器 ✅ | 中等性能服务器 ✅ | 低性能服务器 ❌'],
                'enable_resource_inline' => ['label' => '启用资源内联', 'type' => 'checkbox', 'desc' => '将CSS和JS内联到HTML中，减少HTTP请求数量<br><strong>推荐：</strong>高性能服务器 ❌ | 中等性能服务器 ✅ | 低性能服务器 ✅'],
                'enable_aggressive_cache' => ['label' => '启用激进缓存', 'type' => 'checkbox', 'desc' => '延长缓存时间到72小时，减少数据库查询<br><strong>推荐：</strong>高性能服务器 ❌ | 中等性能服务器 ✅ | 低性能服务器 ✅'],
                'enable_query_optimization' => ['label' => '启用数据库查询优化', 'type' => 'checkbox', 'desc' => '限制查询数量和字段，优化数据库性能<br><strong>推荐：</strong>高性能服务器 ❌ | 中等性能服务器 ✅ | 低性能服务器 ✅'],
            ]
        ],
        'cache' => [
            'title' => '缓存管理', 'group' => 'paper_wp_cache_settings_group', 'option_name' => 'paper_wp_cache_settings',
            'sanitize_callback' => 'paper_wp_checkbox_sanitize_callback',
            'section' => ['id' => 'paper_wp_cache_section', 'title' => '缓存管理', 'desc' => '管理和清理各种类型的缓存数据。', 'callback' => 'paper_wp_cache_section_callback'],
            'fields' => []
        ],
        'ai-summary' => [
            'title' => 'AI摘要', 'group' => 'paper_wp_ai_settings_group', 'option_name' => 'paper_wp_ai_settings',
            'sanitize_callback' => 'paper_wp_ai_settings_sanitize',
            'section' => ['id' => 'paper_wp_ai_section', 'title' => 'AI摘要设置', 'desc' => '配置AI摘要功能的开关和相关设置。'],
            'fields' => [
                'ai_summary_enabled' => ['label' => '启用AI摘要功能', 'type' => 'checkbox'],
                'ai_provider' => ['label' => 'AI服务提供商', 'type' => 'select', 'options' => [
                    'openai' => 'OpenAI',
                    'aliyun' => '阿里云DashScope',
                    'google' => 'Google Gemini',
                    'custom' => '自定义API'
                ]],
                'ai_api_endpoint' => ['label' => 'API端点地址', 'type' => 'text', 'placeholder' => 'https://api.openai.com/v1'],
                'ai_api_key' => ['label' => 'API Key', 'type' => 'text'],
                'ai_model' => ['label' => 'AI模型', 'type' => 'text', 'placeholder' => 'gpt-3.5-turbo'],
                'ai_test_button' => ['label' => '测试AI连接', 'type' => 'custom', 'callback' => 'paper_wp_ai_test_button_callback'],
            ]
        ],
        'image-proxy' => [
            'title' => '图片代理', 'group' => 'paper_wp_image_proxy_settings_group', 'option_name' => 'paper_wp_image_proxy_settings',
            'sanitize_callback' => 'paper_wp_image_proxy_settings_sanitize',
            'section' => ['id' => 'paper_wp_image_proxy_section', 'title' => '图片代理设置', 'desc' => '配置图片代理功能，绕过防盗链限制。', 'callback' => 'paper_wp_image_proxy_section_callback'],
            'fields' => [
                'enable_image_proxy' => ['label' => '启用图片代理功能', 'type' => 'checkbox', 'desc' => '启用后，符合条件的外部图片将通过代理服务器加载，避免防盗链限制'],
                'proxy_domains' => ['label' => '代理域名列表', 'type' => 'textarea', 'desc' => '每行一个域名，这些域名的图片会被代理。例如：<br>hoopchina.com.cn<br>example.com'],
                'proxy_keywords' => ['label' => '代理关键词', 'type' => 'textarea', 'desc' => '包含这些关键词的URL会被代理。每行一个关键词。例如：<br>x-oss-process<br>防盗链图片'],
                'exclude_domains' => ['label' => '排除域名列表', 'type' => 'textarea', 'desc' => '这些域名的图片不会被代理，即使符合其他条件。每行一个域名'],
            ]
        ],
        'about' => [
            'title' => '关于主题', 'group' => 'paper_wp_about_group', 'option_name' => 'paper_wp_about',
            'sanitize_callback' => '',
            'section' => ['id' => 'paper_wp_about_section', 'title' => '主题信息', 'desc' => '关于barepaper主题的相关信息。', 'callback' => 'paper_wp_about_section_callback'],
            'fields' => []
        ],

    ];
}

function paper_wp_field_callback($args) {
    $options = get_option($args['option_name']); $field_id = esc_attr($args['field_id']); $field_name = esc_attr($args['option_name'] . '[' . $field_id . ']');
    
    // 定义默认开启的模块（仅用于modules tab）
    $default_enabled_modules = ['show_random_posts', 'show_tag_cloud', 'show_search', 'show_categories', 'show_archives'];
    $is_default_enabled = in_array($field_id, $default_enabled_modules);
    
    // 所有tab的字段都禁用（缓存管理tab没有字段，友情链接tab是custom类型，需要特殊处理）
    // 但是模块设置tab中的默认开启模块允许正常切换
    $disabled_attr = 'disabled';
    $is_cache_tab = ($args['option_name'] === 'paper_wp_cache_settings'); // 缓存管理tab没有字段
    $is_custom_field = ($args['type'] === 'custom'); // custom类型字段不添加disabled
    $is_modules_tab_enabled_field = ($args['option_name'] === 'paper_wp_theme_settings' && $is_default_enabled); // 模块设置tab中的默认开启模块允许正常切换
    
    if ($is_cache_tab || $is_custom_field || $is_modules_tab_enabled_field) {
        $disabled_attr = '';
    }
    
    switch ($args['type']) {
        case 'checkbox':
            // 获取默认值（如果选项中不存在，使用默认值）
            $default_value = ($args['option_name'] === 'paper_wp_theme_settings' && $is_default_enabled) ? 1 : 0;
            $current_value = isset($options[$field_id]) ? $options[$field_id] : $default_value;
            
            // 设置默认值（如果选项不存在且是modules tab）
            if (!isset($options[$field_id]) && $args['option_name'] === 'paper_wp_theme_settings') {
                if ($is_default_enabled) {
                    $options[$field_id] = 1;
                    update_option($args['option_name'], $options);
                } else {
                    $options[$field_id] = 0;
                    update_option($args['option_name'], $options);
                }
                $current_value = $options[$field_id];
            }
            
            // 如果字段被禁用，强制显示为未选中状态（灰色）
            if ($disabled_attr && !$is_custom_field) {
                $checked = '';
                $current_value = 0; // 禁用状态下强制为0
            } else {
                $checked = $current_value ? 'checked' : '';
            }
            
            echo "<label class='switch" . ($disabled_attr && !$is_custom_field ? ' switch-disabled' : '') . "'>
                <input type='checkbox' id='{$field_id}' name='{$field_name}' value='1' {$checked} {$disabled_attr}>
                <span class='slider'></span>
            </label>";
            if ($disabled_attr && !$is_custom_field) {
                echo "<input type='hidden' name='{$field_name}' value='0'>";
            }
            $config = paper_wp_get_settings_config();
            foreach ($config as $tab) {
                if (isset($tab['fields'][$field_id]['desc'])) {
                    echo "<div style='margin-top: 5px; font-size: 12px; color: #666; line-height: 1.4;'>" . wp_kses_post($tab['fields'][$field_id]['desc']) . "</div>";
                    break;
                }
            }
            break;
        case 'textarea': 
            // 如果字段被禁用，广告代码输入框显示为空
            if ($disabled_attr && (strpos($field_id, 'ad_code') !== false || strpos($field_id, 'proxy') !== false)) {
                $value = '';
            } else {
                $value = isset($options[$field_id]) ? esc_textarea($options[$field_id]) : '';
            }
            echo "<textarea id='{$field_id}' name='{$field_name}' rows='5' cols='50' class='large-text code' {$disabled_attr}>{$value}</textarea>"; 
            if ($disabled_attr) {
                echo "<input type='hidden' name='{$field_name}' value=''>";
            }
            $config = paper_wp_get_settings_config();
            foreach ($config as $tab) {
                if (isset($tab['fields'][$field_id]['desc'])) {
                    echo "<div style='margin-top: 5px; font-size: 12px; color: #666; line-height: 1.4;'>" . wp_kses_post($tab['fields'][$field_id]['desc']) . "</div>";
                    break;
                }
            }
            break;
        case 'select': 
            // 如果字段被禁用，AI摘要模块的下拉框显示为空
            if ($disabled_attr && $args['option_name'] === 'paper_wp_ai_settings') {
                $value = '';
            } else {
                $value = isset($options[$field_id]) ? $options[$field_id] : '';
            }
            echo "<select id='{$field_id}' name='{$field_name}' {$disabled_attr}>";
            // 如果禁用且是AI摘要模块，显示空选项
            if ($disabled_attr && $args['option_name'] === 'paper_wp_ai_settings') {
                echo "<option value='' selected>--</option>";
            } else {
                foreach ($args['options'] as $key => $label) { 
                    $selected = $value === $key ? 'selected' : ''; 
                    echo "<option value='{$key}' {$selected}>{$label}</option>"; 
                }
            }
            echo "</select>";
            if ($disabled_attr) {
                echo "<input type='hidden' name='{$field_name}' value=''>";
            }
            break;
        case 'text': 
            // 如果字段被禁用，AI摘要模块的输入框显示为空
            if ($disabled_attr && $args['option_name'] === 'paper_wp_ai_settings') {
                $value = '';
            } else {
                $value = isset($options[$field_id]) ? esc_attr($options[$field_id]) : '';
            }
            $placeholder = isset($args['placeholder']) ? 'placeholder="' . esc_attr($args['placeholder']) . '"' : ''; 
            echo "<input type='text' id='{$field_id}' name='{$field_name}' value='{$value}' class='regular-text' {$placeholder} {$disabled_attr}>";
            if ($disabled_attr) {
                echo "<input type='hidden' name='{$field_name}' value=''>";
            }
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
    // 获取当前已保存的设置，不接受用户修改
    // 通过检查$_POST来确定是哪个tab，因为字段都已disabled，input主要包含hidden字段的值
    
    // 检查$_POST中的option_group来确定是哪个tab
    $option_name = null;
    
    // 检查effects设置
    if (isset($_POST['paper_wp_effects_settings'])) {
        $option_name = 'paper_wp_effects_settings';
    }
    // 检查editor设置
    elseif (isset($_POST['paper_wp_editor_settings'])) {
        $option_name = 'paper_wp_editor_settings';
    }
    // 检查performance设置
    elseif (isset($_POST['paper_wp_performance_settings'])) {
        $option_name = 'paper_wp_performance_settings';
    }
    // 检查cache设置
    elseif (isset($_POST['paper_wp_cache_settings'])) {
        $option_name = 'paper_wp_cache_settings';
    }
    
    // 如果确定了option_name，返回当前保存的值
    if ($option_name) {
        $current_settings = get_option($option_name, []);
        return $current_settings;
    }
    
    // 如果无法确定，尝试从input的键推断（虽然字段disabled，hidden字段仍会传递值）
    if (!empty($input)) {
        if (isset($input['show_ribbons_effect']) || isset($input['show_cursor_effect']) || isset($input['show_theme_toggle'])) {
            return get_option('paper_wp_effects_settings', []);
        }
        if (isset($input['disable_default_editor']) || isset($input['enable_wddmds'])) {
            return get_option('paper_wp_editor_settings', []);
        }
        if (isset($input['enable_service_worker']) || isset($input['enable_css_async_loading']) || isset($input['enable_resource_inline'])) {
            return get_option('paper_wp_performance_settings', []);
        }
    }
    
    // 默认返回空数组
    return [];
}

function paper_wp_module_settings_sanitize($input) {
    // 获取当前已保存的设置
    $current_settings = get_option('paper_wp_theme_settings', []);
    $output = [];
    
    // 定义默认开启的模块
    $default_enabled_modules = ['show_random_posts', 'show_tag_cloud', 'show_search', 'show_categories', 'show_archives'];
    
    // 处理复选框字段
    $checkbox_fields = [
        'show_reading_ranking', 'show_like_ranking', 'show_comment_ranking',
        'show_random_posts', 'show_recent_album', 'show_recommended_posts',
        'show_tag_cloud', 'show_search', 'show_categories', 'show_archives',
        'show_friend_links', 'show_sidebar_links', 'show_poetry_recommendation',
        'show_sponsor_module', 'show_blog_stats', 'enable_sticky_posts'
    ];
    
    foreach ($checkbox_fields as $field_id) {
        // 默认开启的模块允许用户修改，其他模块强制为0
        if (in_array($field_id, $default_enabled_modules)) {
            // 允许用户修改，接受用户输入
            $output[$field_id] = isset($input[$field_id]) ? 1 : 0;
        } else {
            // 其他模块强制为0
            $output[$field_id] = 0;
        }
    }
    
    // 处理文章预览字数限制字段 - 保持现有值或使用默认值
    if (isset($current_settings['excerpt_word_limit'])) {
        $output['excerpt_word_limit'] = $current_settings['excerpt_word_limit'];
    } else {
        $output['excerpt_word_limit'] = 500; // 默认值
    }

    // 处理图片显示方式字段 - 保持现有值或使用默认值
    if (isset($current_settings['excerpt_image_mode'])) {
        $output['excerpt_image_mode'] = $current_settings['excerpt_image_mode'];
    } else {
        $output['excerpt_image_mode'] = 'all'; // 默认值
    }
    
    return $output;
}

function paper_wp_ad_settings_sanitize($input) {
    // 获取当前已保存的设置，不接受用户修改
    $current_settings = get_option('paper_wp_ad_settings', []);
    
    // 如果没有已保存的设置，返回默认空值
    if (empty($current_settings)) {
        $current_settings = [];
        foreach (['show_header_ad', 'show_post_bottom_ad', 'show_sidebar_ad'] as $key) {
            $current_settings[$key] = 0;
        }
        foreach (['header_ad_code', 'post_bottom_ad_code', 'sidebar_ad_code'] as $key) {
            $current_settings[$key] = '';
        }
    }
    
    return $current_settings;
}

function paper_wp_friend_links_settings_sanitize($input) {
    // 获取当前已保存的设置，不接受用户修改
    $current_settings = get_option('paper_wp_friend_links', []);
    return $current_settings;
}

function paper_wp_ai_settings_sanitize($input) {
    // 获取当前已保存的设置，不接受用户修改
    $current_settings = get_option('paper_wp_ai_settings', []);
    
    // 如果没有已保存的设置，返回默认值
    if (empty($current_settings)) {
        $current_settings = [
            'ai_summary_enabled' => 0,
            'ai_provider' => 'openai',
            'ai_api_endpoint' => 'https://api.openai.com/v1',
            'ai_api_key' => '',
            'ai_model' => 'gpt-3.5-turbo',
            'ai_auto_publish' => 0,
        ];
    }
    
    return $current_settings;
}

function paper_wp_image_proxy_settings_sanitize($input) {
    // 获取当前已保存的设置，不接受用户修改
    $current_settings = get_option('paper_wp_image_proxy_settings', []);
    
    // 如果没有已保存的设置，返回默认值
    if (empty($current_settings)) {
        $current_settings = [
            'enable_image_proxy' => 0,
            'proxy_domains' => '',
            'proxy_keywords' => '',
            'exclude_domains' => '',
        ];
    }
    
    return $current_settings;
}



/**
 * 广告设置页面回调函数
 */
function paper_wp_advertisement_section_callback() {
    echo '<button type="button" id="show-ad-examples" class="button" style="margin-top: 10px;">查看广告示例代码</button><div id="ad-examples-modal" style="display: none;"></div>';
}

/**
 * 图片代理设置页面回调函数
 */
function paper_wp_image_proxy_section_callback() {
    ?>
    <div style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin: 15px 0;">
        <details>
            <summary style="cursor: pointer; font-weight: bold; color: #007cba;">📋 图片代理功能说明 (点击展开)</summary>
            <div style="margin-top: 10px; padding: 15px; background: white; border-radius: 4px; font-size: 13px; line-height: 1.5;">
                <h5 style="color: #007cba; margin-bottom: 10px;">🖼️ 图片代理工作原理</h5>

                <div style="margin-bottom: 15px;">
                    <h6 style="color: #23282d; margin: 0 0 5px 0;">🔄 代理机制</h6>
                    <p style="margin: 0; color: #666;">当外部图片服务器设置了防盗链限制时，本地服务器作为中间代理，从外部服务器获取图片并返回给用户，从而绕过防盗链检测。</p>
                </div>

                <div style="margin-bottom: 15px;">
                    <h6 style="color: #23282d; margin: 0 0 5px 0;">🎯 匹配规则</h6>
                    <p style="margin: 0; color: #666;">系统按以下优先级检查图片是否需要代理：<br>
                    1. 检查是否在排除域名列表中（不代理）<br>
                    2. 检查是否在代理域名列表中（代理）<br>
                    3. 检查URL是否包含代理关键词（代理）</p>
                </div>

                <div style="margin-bottom: 15px;">
                    <h6 style="color: #23282d; margin: 0 0 5px 0;">⚙️ 配置说明</h6>
                    <ul style="margin: 0; color: #666;">
                        <li><strong>代理域名：</strong>指定需要代理的图片域名</li>
                        <li><strong>代理关键词：</strong>URL包含这些关键词的图片会被代理</li>
                        <li><strong>排除域名：</strong>即使符合其他条件也不代理的域名</li>
                    </ul>
                </div>

                <h5 style="color: #28a745; margin: 15px 0 10px 0;">✅ 使用建议</h5>
                <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px;">
                    <p style="margin: 0 0 8px 0;"><strong>代理域名示例：</strong></p>
                    <code style="background: #e9ecef; padding: 2px 4px; border-radius: 3px;">hoopchina.com.cn</code><br>
                    <code style="background: #e9ecef; padding: 2px 4px; border-radius: 3px;">i10.hoopchina.com.cn</code><br>
                    <code style="background: #e9ecef; padding: 2px 4px; border-radius: 3px;">img.hoopchina.com.cn</code><br><br>

                    <p style="margin: 0 0 8px 0;"><strong>代理关键词示例：</strong></p>
                    <code style="background: #e9ecef; padding: 2px 4px; border-radius: 3px;">x-oss-process</code><br>
                    <code style="background: #e9ecef; padding: 2px 4px; border-radius: 3px;">防盗链</code><br><br>

                    <p style="margin: 0 0 8px 0;"><strong>完整URL示例：</strong></p>
                    <code style="background: #e9ecef; padding: 2px 4px; border-radius: 3px; word-break: break-all;">https://i10.hoopchina.com.cn/news-editor/e8c2405849756952cc7176c6afe6d9e9_w_2268_h_4032_.jpeg?x-oss-process=image/resize,w_800/format,webp</code><br><br>

                    <p style="margin: 0 0 8px 0;"><strong>代理后的URL：</strong></p>
                    <code style="background: #e9ecef; padding: 2px 4px; border-radius: 3px; word-break: break-all;">https://您的域名/proxy-image.php?url=https://i10.hoopchina.com.cn/news-editor/e8c2405849756952cc7176c6afe6d9e9_w_2268_h_4032_.jpeg?x-oss-process=image/resize,w_800/format,webp</code>
                </div>

                <h5 style="color: #dc3545; margin: 15px 0 10px 0;">⚠️ 注意事项</h5>
                <ul style="margin: 0; padding-left: 20px; color: #856404;">
                    <li>代理功能会增加服务器负载，建议只对必要的图片启用</li>
                    <li>代理的图片会被缓存1小时，提升重复访问性能</li>
                    <li>确保代理脚本有足够的执行权限</li>
                </ul>
            </div>
        </details>
    </div>
    <?php
}

/**
 * 执行缓存清理操作
 */
function paper_wp_execute_cache_clear($group) {
    static $cache_functions_available = null;

    // 缓存函数可用性检查结果，避免重复检查
    if ($cache_functions_available === null) {
        $cache_functions_available = [
            'flush_group' => function_exists('paper_wp_cache_flush_group'),
            'clear_sidebar' => function_exists('paper_wp_clear_sidebar_cache'),
            'log_operation' => function_exists('paper_wp_log_cache_operation'),
            'cache_class' => class_exists('Paper_WP_Ultimate_Cache')
        ];
    }

    if (!$cache_functions_available['flush_group']) {
        throw new Exception('缓存清理功能不可用');
    }

    $group_names = [
        'posts' => '文章缓存',
        'terms' => '分类标签缓存',
        'users' => '用户缓存',
        'options' => '选项缓存',
        'comments' => '评论缓存',
        'preload' => '预加载缓存',
        'stats' => '统计缓存',
        'sidebar' => '侧栏缓存',
        'all' => '所有缓存',
        'file' => '文件缓存'
    ];

    try {
        switch ($group) {
            case 'all':
                // 获取缓存实例（复用检查结果）
                $cache_instance = $cache_functions_available['cache_class'] ?
                    Paper_WP_Ultimate_Cache::get_instance() : null;

                // 动态获取数据库缓存组（使用公共方法，避免访问私有属性）
                if ($cache_instance && method_exists($cache_instance, 'get_database_cache_groups')) {
                    $database_groups = $cache_instance->get_database_cache_groups();
                } else {
                    // 回退到硬编码列表
                    $database_groups = ['posts', 'terms', 'users', 'options', 'comments', 'preload', 'stats'];
                }

                // 批量清理数据库缓存组
                foreach ($database_groups as $cache_group) {
                    paper_wp_cache_flush_group($cache_group);
                }

                // 清理文件缓存
                if ($cache_instance) {
                    $cache_instance->flush_file_cache();
                }

                // 清理侧栏缓存（确保排行榜缓存被清理）
                if ($cache_functions_available['clear_sidebar']) {
                    paper_wp_clear_sidebar_cache();
                }

                // 清理WordPress缓存
                wp_cache_flush();

                $message = '所有缓存清理完成';
                break;

            case 'file':
                if (!$cache_functions_available['cache_class']) {
                    throw new Exception('缓存系统不可用，无法清理文件缓存');
                }
                Paper_WP_Ultimate_Cache::get_instance()->flush_file_cache();
                $message = '文件缓存清理完成';
                break;

            case 'sidebar':
                if (!$cache_functions_available['clear_sidebar']) {
                    throw new Exception('侧栏缓存清理功能不可用');
                }
                paper_wp_clear_sidebar_cache();
                $message = '侧栏缓存清理完成';
                break;

            default:
                // 清理指定缓存组
                paper_wp_cache_flush_group($group);

                // 对于非侧栏组的清理，也清理侧栏缓存（因为依赖关系）
                if ($cache_functions_available['clear_sidebar'] && $group !== 'sidebar') {
                    paper_wp_clear_sidebar_cache();
                }

                $group_name = $group_names[$group] ?? $group;
                $message = $group_name . '清理完成';
                break;
        }

        // 记录操作日志
        if ($cache_functions_available['log_operation']) {
            paper_wp_log_cache_operation($group, $message);
        }

        return $message;

    } catch (Exception $e) {
        error_log('Paper WP Cache Clear Error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * 缓存管理页面回调函数
 */
function paper_wp_cache_section_callback() {
    // 功能已禁用 - 显示开发提示
    ?>
    <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 20px; margin-top: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <h3 style="margin-top: 0; margin-bottom: 15px; color: #856404;">⚠️ 开发提示</h3>
        <p style="margin-bottom: 15px; color: #856404; font-size: 14px; line-height: 1.6;">
            <strong>缓存管理功能</strong><br>
            此功能正在开发测试中，等更新。<br>
            后续版本将会开放此功能。
        </p>
        <div style="background: #fff; border: 1px solid #ffc107; border-radius: 4px; padding: 15px; margin-top: 15px;">
            <p style="margin: 0; font-size: 13px; color: #856404;">
                <strong>提示：</strong>缓存功能相关的代码逻辑已保留但被占位处理，函数名和调用结构完整保留，便于后续开发扩展。
            </p>
        </div>
    </div>
    
    <?php
    // 功能已禁用 - 缓存管理界面已移除，仅显示开发提示
}

/**
 * 关于主题信息页面回调函数
 */
function paper_wp_about_section_callback() {
    // 获取主题版本信息
    $theme = wp_get_theme();
    $current_version = $theme->get('Version') ? $theme->get('Version') : BAREPAPER_VERSION;
    $latest_version = $current_version; // 这里可以设置为最新版本，或者从API获取
    
    ?>
    <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-top: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <div style="margin-bottom: 20px;">
            <div style="display: flex; align-items: flex-start; margin-bottom: 15px;">
                <div style="flex: 0 0 120px; font-weight: 600; color: #1d2327; padding-top: 5px;">版本信息</div>
                <div style="flex: 1; color: #50575e; line-height: 1.6;">
                    当前版本（<?php echo esc_html($current_version); ?>）/ 最新版本（<?php echo esc_html($latest_version); ?>）
                </div>
            </div>
            
            <div style="display: flex; align-items: flex-start; margin-bottom: 15px;">
                <div style="flex: 0 0 120px; font-weight: 600; color: #1d2327; padding-top: 5px;">BUG反馈</div>
                <div style="flex: 1; color: #50575e; line-height: 1.6;">
                    微信号XXXX、Github提交issue、博客https://wdd.pp.ua/blog/下方留言均可
                </div>
            </div>
            
            <div style="display: flex; align-items: flex-start; margin-bottom: 15px;">
                <div style="flex: 0 0 120px; font-weight: 600; color: #1d2327; padding-top: 5px;">仓库地址</div>
                <div style="flex: 1; color: #50575e; line-height: 1.6;">
                    Github：<a href="https://github.com/wangdaodaodao/WordPress-theme-barepaper" target="_blank" style="color: #2271b1; text-decoration: none;">https://github.com/wangdaodaodao/WordPress-theme-barepaper</a><br>
                    博客：<a href="https://wdd.pp.ua/blog/theme-make/" target="_blank" style="color: #2271b1; text-decoration: none;">https://wdd.pp.ua/blog/</a><br>
                    <span style="color: #646970; font-size: 13px; margin-top: 8px; display: block;">目前的主题还在开发中。<br>仓库内的版本永远是最新版本，如您觉得插件给你带来了帮助，欢迎star！祝您早日达成自己的目标！</span>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * 性能优化设置页面回调函数
 */
function paper_wp_performance_section_callback() {
    ?>
    <!-- 详细功能说明 -->
    <div style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin: 15px 0;">
        <details>
            <summary style="cursor: pointer; font-weight: bold; color: #007cba;">📋 详细功能说明 (点击展开)</summary>
            <div style="margin-top: 10px; padding: 15px; background: white; border-radius: 4px; font-size: 13px; line-height: 1.5;">
                <h5 style="color: #007cba; margin-bottom: 10px;">🔧 核心优化策略详解</h5>

                <div style="margin-bottom: 15px;">
                    <h6 style="color: #23282d; margin: 0 0 5px 0;">📱 Service Worker缓存</h6>
                    <p style="margin: 0; color: #666;">启用Service Worker进行离线缓存，支持PWA功能。缓存关键CSS、JS和图片文件，提升重复访问速度，减少服务器负载。适合有离线需求的站点。</p>
                </div>

                <div style="margin-bottom: 15px;">
                    <h6 style="color: #23282d; margin: 0 0 5px 0;">🎨 CSS异步加载</h6>
                    <p style="margin: 0; color: #666;">使用非阻塞方式加载CSS文件，避免CSS阻塞页面渲染。提升首屏显示速度，改善用户体验。适合页面内容较多、CSS文件较大的站点。</p>
                </div>

                <div style="margin-bottom: 15px;">
                    <h6 style="color: #23282d; margin: 0 0 5px 0;">📦 资源内联</h6>
                    <p style="margin: 0; color: #666;">将关键CSS和JS代码直接嵌入HTML中，减少HTTP请求数量。显著提升首次加载速度，但会增加HTML文件大小。适合网络延迟高、请求数量多的环境。</p>
                </div>

                <div style="margin-bottom: 15px;">
                    <h6 style="color: #23282d; margin: 0 0 5px 0;">⚡ 激进缓存</h6>
                    <p style="margin: 0; color: #666;">将缓存时间延长到72小时，大幅减少数据库查询和计算开销。提升响应速度，降低服务器负载。但内容更新会有延迟，适合内容更新不频繁的站点。</p>
                </div>

                <div style="margin-bottom: 20px;">
                    <h6 style="color: #23282d; margin: 0 0 5px 0;">🗄️ 数据库查询优化</h6>
                    <p style="margin: 0; color: #666;">限制和优化数据库查询，减少查询数量和数据传输。提升数据库性能，降低查询时间。适合查询频繁、数据量大的站点。</p>
                </div>

                <h5 style="color: #dc3545; margin-bottom: 10px;">⚠️ 重要注意事项</h5>
                <ul style="margin: 0; padding-left: 20px; color: #856404;">
                    <li><strong>功能冲突：</strong>资源内联与CSS异步加载不能同时启用，否则会导致样式错乱</li>
                    <li><strong>缓存延迟：</strong>激进缓存会延迟内容更新，编辑文章后可能需要等待缓存过期</li>
                    <li><strong>测试验证：</strong>开启优化后请测试网站功能和性能，确保一切正常</li>
                    <li><strong>服务器适配：</strong>根据服务器性能选择合适的优化组合，避免过度优化</li>
                </ul>

                <h5 style="color: #28a745; margin: 15px 0 10px 0;">🎯 配置建议</h5>
                <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px;">
                    <p style="margin: 0 0 8px 0;"><strong>高性能服务器：</strong>Service Worker + CSS异步加载</p>
                    <p style="margin: 0 0 8px 0;"><strong>中等性能服务器：</strong>资源内联 + 激进缓存 + 查询优化</p>
                    <p style="margin: 0;"><strong>低性能服务器：</strong>资源内联 + 激进缓存 + 查询优化</p>
                </div>
            </div>
        </details>
    </div>
    <?php
}

/**
 * 友情链接列表回调函数
 */
function paper_wp_friend_links_list_callback() {
    // 功能已禁用 - 友情链接功能已禁用
    ?>
    <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 15px; margin-top: 10px;">
        <p style="margin: 0; color: #856404; font-size: 14px;">
            <strong>⚠️ 开发中</strong><br>
            友情链接功能正在开发测试中，等更新。
        </p>
    </div>
    <?php
    // 原有的友情链接管理界面已移除，保留结构供参考
    return;
    
    /*
    $friend_links = get_option('paper_wp_friend_links', []);
    ?>
    <div id="friend-links-container"><div id="friend-links-list">
        <?php if (!empty($friend_links)) : foreach ($friend_links as $index => $link) : ?>
            <div class="friend-link-item" style="display: flex; align-items: center; margin-bottom: 10px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                <input type="text" name="paper_wp_friend_links[<?php echo $index; ?>][name]" value="<?php echo esc_attr($link['name']); ?>" placeholder="链接名称" style="flex: 1; margin-right: 10px;" disabled />
                <input type="url" name="paper_wp_friend_links[<?php echo $index; ?>][url]" value="<?php echo esc_attr($link['url']); ?>" placeholder="链接地址" style="flex: 2; margin-right: 10px;" disabled />
                <input type="text" name="paper_wp_friend_links[<?php echo $index; ?>][description]" value="<?php echo esc_attr($link['description'] ?? ''); ?>" placeholder="描述（可选）" style="flex: 2; margin-right: 10px;" disabled />
                <button type="button" class="button remove-friend-link" style="background: #dc3545; color: white; border: none;" disabled>删除</button>
            </div>
        <?php endforeach; endif; ?>
    </div><button type="button" id="add-friend-link" class="button" style="margin-top: 10px;" disabled>添加友情链接</button></div>
    <?php
    */
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

    <style>
    /* 开关样式 */
    .switch {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 24px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 24px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }

    input:checked + .slider {
        background-color: #007cba;
    }

    input:checked + .slider:before {
        transform: translateX(20px);
    }

    /* 禁用状态的开关样式 - 灰色未开启 */
    .switch-disabled input:disabled + .slider {
        background-color: #e0e0e0;
        cursor: not-allowed;
        opacity: 0.6;
    }

    .switch-disabled input:disabled + .slider:before {
        background-color: #f5f5f5;
    }

    .switch-disabled input:disabled:checked + .slider {
        background-color: #e0e0e0;
    }

    .switch-disabled input:disabled:checked + .slider:before {
        transform: translateX(0px);
        background-color: #f5f5f5;
    }

    /* 禁用状态的文本输入框样式 */
    textarea:disabled, input[type="text"]:disabled, select:disabled {
        background-color: #f5f5f5;
        color: #999;
        cursor: not-allowed;
        opacity: 0.6;
    }
    </style>

    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <h2 class="nav-tab-wrapper">
            <?php foreach ($config as $tab_key => $tab_data) : ?>
                <a href="?page=paper-wp-theme-settings&tab=<?php echo esc_attr($tab_key); ?>" class="nav-tab <?php echo $active_tab == $tab_key ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html($tab_data['title']); ?>
                </a>
            <?php endforeach; ?>
        </h2>
        <form action="options.php" method="post">
            <?php
            settings_fields($config[$active_tab]['group']);
            do_settings_sections('paper-wp-theme-settings-' . $active_tab);
            
            // 只有在有字段需要保存的tab才显示保存按钮（缓存管理tab没有字段，不需要保存按钮）
            if (!empty($config[$active_tab]['fields'])) {
                submit_button('保存设置');
            }
            ?>
        </form>
    </div>
    <?php
}
