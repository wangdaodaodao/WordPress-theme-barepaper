<?php
/**
 * ========================================
 * BarePaper WordPress Theme
 * ========================================
 * 核心设置 - 主题基础配置与工具
 * 包含：设置管理器、功能自动加载、主题初始化、工具函数
 * @author wangdaodao
 * @version 3.1.0
 */

if (!defined('ABSPATH')) exit;

/**
 * ===========================================
 * 1. 设置管理器 (Paper_Settings_Manager)
 * ===========================================
 */
class Paper_Settings_Manager {
    
    /**
     * 设置缓存
     */
    private static $cache = [];
    
    /**
     * 获取设置（带缓存）
     */
    public static function get($option_name, $default = []) {
        if (!isset(self::$cache[$option_name])) {
            self::$cache[$option_name] = get_option($option_name, $default);
        }
        return self::$cache[$option_name];
    }
    
    /**
     * 获取设置中的特定字段
     */
    public static function get_field($option_name, $key, $default = null) {
        $settings = self::get($option_name, []);
        return $settings[$key] ?? $default;
    }
    
    /**
     * 检查设置字段是否启用（非空）
     */
    public static function is_enabled($option_name, $key) {
        return !empty(self::get_field($option_name, $key));
    }
    
    /**
     * 更新设置（同时更新缓存）
     */
    public static function update($option_name, $value) {
        $result = update_option($option_name, $value);
        if ($result) {
            self::$cache[$option_name] = $value;
        }
        return $result;
    }
    
    /**
     * 清除缓存
     */
    public static function clear_cache($option_name = null) {
        if ($option_name === null) {
            self::$cache = [];
        } else {
            unset(self::$cache[$option_name]);
        }
    }
    
    /**
     * 批量获取多个设置
     */
    public static function get_multiple(array $option_names) {
        $results = [];
        foreach ($option_names as $name) {
            $results[$name] = self::get($name);
        }
        return $results;
    }
    
    /**
     * 常用设置的便捷方法
     */
    
    // 主题设置
    public static function get_theme_settings() {
        return self::get('paper_wp_theme_settings', []);
    }
    
    // 编辑器设置
    public static function get_editor_settings() {
        return self::get('paper_wp_editor_settings', []);
    }
    
    // AI设置
    public static function get_ai_settings() {
        return self::get('paper_wp_ai_settings', []);
    }
    
    // 性能设置
    public static function get_performance_settings() {
        return self::get('paper_wp_performance_settings', []);
    }
    
    // 代理设置
    public static function get_proxy_settings() {
        return self::get('paper_wp_proxy_settings', []);
    }
}

/**
 * 便捷函数：获取设置
 */
function paper_get_setting($option_name, $default = []) {
    return Paper_Settings_Manager::get($option_name, $default);
}

/**
 * 便捷函数：获取设置字段
 */
function paper_get_setting_field($option_name, $key, $default = null) {
    return Paper_Settings_Manager::get_field($option_name, $key, $default);
}

/**
 * 便捷函数：检查设置是否启用
 */
function paper_is_setting_enabled($option_name, $key) {
    return Paper_Settings_Manager::is_enabled($option_name, $key);
}

/**
 * ===========================================
 * 2. 功能模块自动加载 (Autoload)
 * ===========================================
 */
function paper_wp_autoload_features() {
    // 缓存配置加载，避免重复文件读取
    static $config = null;
    if ($config === null) {
        $config = [
            'rss' => [
                'enabled' => true,
                'file' => 'features/rss.php',
                'description' => 'RSS Feed优化：解决中文编码问题，确保UTF-8正确显示',
                'load_in_admin' => false,
                'load_in_frontend' => true
            ],
            'performance' => [
                'enabled' => true,
                'file' => 'features/performance.php',
                'description' => '性能优化：缓存(含简单缓存系统)、资源提示、数据库优化等',
                'load_in_admin' => true,
                'load_in_frontend' => true
            ],
            'stats' => [
                'enabled' => true,
                'file' => 'features/stats.php',
                'description' => '博客统计功能：显示运行时间、文章篇数、总字数等',
                'load_in_admin' => true,
                'load_in_frontend' => true
            ],
            'image' => [
                'enabled' => true,
                'file' => 'features/image.php',
                'description' => '图片处理功能：智能尺寸容器、响应式图片、灯箱功能',
                'load_in_admin' => false,
                'load_in_frontend' => true
            ],

            'post-enhancements' => [
                'enabled' => true,
                'file' => 'features/post-enhancements.php',
                'description' => '文章增强功能：推荐文章、置顶文章等',
                'load_in_admin' => true,
                'load_in_frontend' => true
            ],
            'wddmd' => [
                'enabled' => function() {
                    return Paper_Settings_Manager::is_enabled('paper_wp_editor_settings', 'enable_wddmds');
                },
                'file' => 'features/wddmd.php',
                'description' => 'WDDMD模块化Markdown解析器：支持完整的Markdown语法解析，包括文本、结构、格式化和图片处理',
                'load_in_admin' => true,
                'load_in_frontend' => true
            ],
            'shortcodes' => [
                'enabled' => true,
                'file' => 'features/shortcodes.php',
                'description' => '短代码模块：统一管理所有短代码功能，包括音乐、视频、代码块等',
                'load_in_admin' => true,
                'load_in_frontend' => true
            ],
            'editor-functions' => [
                'enabled' => true,
                'file' => 'features/editor-functions.php',
                'description' => '编辑器增强功能主文件：整合所有编辑器相关的钩子注册和脚本加载',
                'load_in_admin' => true,
                'load_in_frontend' => false
            ],
            'excerpt' => [
                'enabled' => true,
                'file' => 'features/excerpt.php',
                'description' => '文章摘录功能：智能截取、HTML格式化、换行处理、图片处理等',
                'load_in_admin' => false,
                'load_in_frontend' => true
            ],

            'user-agent' => [
                'enabled' => function() {
                    return Paper_Settings_Manager::is_enabled('paper_wp_theme_settings', 'enable_user_agent');
                },
                'file' => 'features/user-agent.php',
                'description' => 'User Agent解析功能：显示评论者的操作系统和浏览器信息',
                'load_in_admin' => true,
                'load_in_frontend' => true
            ]
        ];
    }
    
    $is_admin = is_admin();

    foreach ($config as $feature => $settings) {
        // 检查功能是否启用
        $enabled = is_callable($settings['enabled']) ? $settings['enabled']() : $settings['enabled'];
        if (!$enabled) continue;

        $load_in_admin = $settings['load_in_admin'] ?? true;
        $load_in_frontend = $settings['load_in_frontend'] ?? true;
        
        $should_load = ($is_admin && $load_in_admin) || (!$is_admin && $load_in_frontend) 
            || (defined('DOING_AJAX') && DOING_AJAX);

        if ($should_load) {
            $file_path = get_template_directory() . '/' . $settings['file'];
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
}
add_action('after_setup_theme', 'paper_wp_autoload_features', 5);

/**
 * ===========================================
 * 3. 主题初始化 (Setup)
 * ===========================================
 */
function paper_wp_setup() {
    register_nav_menus(array(
        'primary' => __('主导航菜单', 'barepaper'),
    ));
    add_theme_support('post-thumbnails');
    add_theme_support('title-tag');
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list'));
}
add_action('after_setup_theme', 'paper_wp_setup');

function paper_wp_setup_editor() {
    if (Paper_Settings_Manager::is_enabled('paper_wp_editor_settings', 'disable_default_editor')) {
        add_filter('use_block_editor_for_post', '__return_false');
        add_filter('use_block_editor_for_post_type', '__return_false', 10, 2);
    }
}
add_action('admin_init', 'paper_wp_setup_editor');

/**
 * 统一移除分页中的多余元素
 */
add_filter('navigation_markup_template', function($template, $class) {
    if ($class === 'pagination') {
        // 移除 screen-reader-text 标题和各种"更多"提示
        $patterns = [
            '/<h2[^>]*class="[^"]*screen-reader-text[^"]*"[^>]*>.*?<\/h2>/is',
            '/<span[^>]*aria-label="[^"]*继续阅读[^"]*"[^>]*>.*?<\/span>/is',
            '/<span[^>]*>.*?更多.*?<\/span>/is',
            '/<span[^>]*>.*?\(更多[^\)]*\)<\/span>/is'
        ];
        $template = preg_replace($patterns, '', $template);
    }
    return $template;
}, 10, 2);

add_filter('paginate_links_output', function($output) {
    // 移除分页链接中的"更多"相关元素
    $patterns = [
        '/<a[^>]*>.*?更多.*?<\/a>/is',
        '/<span[^>]*aria-label="[^"]*继续阅读[^"]*"[^>]*>.*?<\/span>/is'
    ];
    return preg_replace($patterns, '', $output);
}, 10, 1);

/**
 * 自定义分页输出 - 始终显示上一页和下一页，但在第一页和最后一页时禁用
 */
function paper_wp_custom_posts_pagination($args = []) {
    global $wp_query;
    
    if ($wp_query->max_num_pages <= 1) {
        return;
    }
    
    $paged = get_query_var('paged') ? absint(get_query_var('paged')) : 1;
    $max = intval($wp_query->max_num_pages);
    
    $defaults = [
        'prev_text' => '&laquo; 上一页',
        'next_text' => '下一页 &raquo;',
    ];
    $args = wp_parse_args($args, $defaults);
    
    $prev_text = $args['prev_text'];
    $next_text = $args['next_text'];
    
    $output = '<nav class="navigation pagination" role="navigation" aria-label="文章分页">';
    $output .= '<div class="nav-links">';
    
    // 上一页按钮
    if ($paged > 1) {
        $prev_page = $paged - 1;
        if ($prev_page == 1) {
            $prev_link = get_pagenum_link(0);
        } else {
            $prev_link = get_pagenum_link($prev_page);
        }
        $output .= '<a class="prev page-numbers" href="' . esc_url($prev_link) . '">' . $prev_text . '</a>';
    } else {
        $output .= '<span class="prev page-numbers disabled" aria-disabled="true">' . $prev_text . '</span>';
    }
    
    // 页码链接
    for ($i = 1; $i <= $max; $i++) {
        if ($i == $paged) {
            $output .= '<span class="page-numbers current" aria-current="page">' . $i . '</span>';
        } else {
            if ($i == 1) {
                $page_link = get_pagenum_link(0);
            } else {
                $page_link = get_pagenum_link($i);
            }
            $output .= '<a class="page-numbers" href="' . esc_url($page_link) . '">' . $i . '</a>';
        }
    }
    
    // 下一页按钮
    if ($paged < $max) {
        $next_link = get_pagenum_link($paged + 1);
        $output .= '<a class="next page-numbers" href="' . esc_url($next_link) . '">' . $next_text . '</a>';
    } else {
        $output .= '<span class="next page-numbers disabled" aria-disabled="true">' . $next_text . '</span>';
    }
    
    $output .= '</div>';
    $output .= '</nav>';
    
    echo $output;
}

/**
 * ===========================================
 * 4. 通用工具函数库 (Utilities)
 * ===========================================
 */

/**
 * 格式化数字显示
 */
function paper_wp_format_number($number, $decimals = 0) {
    if (!is_numeric($number)) {
        return $number;
    }
    return $decimals > 0 ? number_format($number, $decimals, '.', '') : (string)(int)$number;
}

/**
 * 获取相对时间
 */
function paper_wp_get_relative_time($timestamp) {
    $current_time = current_time('timestamp');
    $time_diff = $current_time - $timestamp;

    if ($time_diff < 60) {
        return '刚刚';
    } elseif ($time_diff < 3600) {
        return floor($time_diff / 60) . '分钟前';
    } elseif ($time_diff < 86400) {
        return floor($time_diff / 3600) . '小时前';
    } elseif ($time_diff < 2592000) {
        return floor($time_diff / 86400) . '天前';
    } elseif ($time_diff < 31536000) {
        return floor($time_diff / 2592000) . '个月前';
    } else {
        return floor($time_diff / 31536000) . '年前';
    }
}

/**
 * 清理字符串中的特殊字符
 */
function paper_wp_sanitize_string($string) {
    // 移除HTML标签
    $string = wp_strip_all_tags($string);

    // 移除多余的空白字符
    $string = preg_replace('/\s+/', ' ', $string);

    // 移除控制字符
    $string = preg_replace('/[\x00-\x1F\x7F]/u', '', $string);

    return trim($string);
}

/**
 * 获取站点地图URL（智能判断环境）
 * 根据服务器是否支持重写规则返回合适的URL格式
 */
function paper_wp_get_sitemap_url() {
    return get_option('permalink_structure') 
        ? home_url('/sitemap.xml') 
        : home_url('/?paper_sitemap=index');
}
