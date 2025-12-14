<?php
if (!defined('ABSPATH')) exit;

/**
 * ===========================================
 * Paper WP 性能优化 - 简化版本
 * 移除复杂的类结构，只保留核心优化功能
 * ===========================================
 */

/**
 * 初始化性能优化功能
 */
function paper_wp_init_performance_optimization(): void {
    // 基础资源移除
    add_action('wp_enqueue_scripts', 'paper_wp_remove_unnecessary_resources', 100);
    add_action('init', 'paper_wp_disable_unnecessary_features');

    // 字体和样式优化
    add_action('wp_head', 'paper_wp_optimize_fonts', 2);

    // 脚本优化
    add_filter('script_loader_tag', 'paper_wp_add_defer_to_scripts', 10, 2);

    // Service Worker支持
    add_filter('query_vars', 'paper_wp_add_sw_query_var');
    add_action('template_redirect', 'paper_wp_serve_sw_file');
    add_action('wp_footer', 'paper_wp_register_service_worker');
}

/**
 * 移除不必要的资源
 */
function paper_wp_remove_unnecessary_resources(): void {
    // 检查编辑器设置，自动移除块编辑器样式
    if (Paper_Settings_Manager::is_enabled('paper_wp_editor_settings', 'disable_default_editor')) {
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('wc-blocks-style');
    }

    // 移除经典主题样式和全局样式
    wp_dequeue_style('classic-theme-styles');
    wp_dequeue_style('global-styles');

    // 对于非管理员用户，移除admin bar相关资源
    if (!current_user_can('manage_options')) {
        wp_dequeue_style('dashicons');
        wp_dequeue_style('admin-bar');
        wp_dequeue_script('admin-bar');
        wp_dequeue_script('hoverintent-js');
    }
}

/**
 * 禁用不必要的WordPress功能
 */
/**
 * 禁用不必要的WordPress功能
 */
function paper_wp_disable_unnecessary_features(): void {
    // 直接移除不需要的链接和功能
    remove_action('wp_head', 'rest_output_link_wp_head', 10);
    remove_action('wp_head', 'rest_output_link_header', 11);
    remove_action('wp_head', 'wp_oembed_add_discovery_links', 10);
    remove_action('wp_head', 'wp_oembed_add_host_js', 10);
    remove_action('rest_api_init', 'wp_oembed_register_route');
    add_filter('embed_oembed_discover', '__return_false');

    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0);
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'feed_links_extra', 3);

    // 禁用XML-RPC
    add_filter('xmlrpc_enabled', '__return_false');

    // 禁用修订版本 (保留0个，即完全禁用)
    add_filter('wp_revisions_to_keep', '__return_zero');

    // 禁用代码标点转换 (wptexturize)
    add_filter('run_wptexturize', '__return_false');

    // 移除 Google Fonts DNS 预解析
    add_filter('wp_resource_hints', function($urls, $relation_type) {
        if ('dns-prefetch' === $relation_type) {
            return array_filter($urls, function($url) {
                return strpos($url, 'fonts.googleapis.com') === false && strpos($url, 'fonts.gstatic.com') === false;
            });
        }
        return $urls;
    }, 10, 2);

    // 禁用Emoji (移除条件判断，强制执行)
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    add_filter('tiny_mce_plugins', function($plugins) {
        return is_array($plugins) ? array_diff($plugins, ['wpemoji']) : [];
    });
    add_filter('wp_resource_hints', function($urls, $relation_type) {
        if ('dns-prefetch' === $relation_type) {
            return array_filter($urls, fn($url) => strpos($url, 's.w.org/images/core/emoji/') === false);
        }
        return $urls;
    }, 10, 2);

    // 移除定制器支持脚本
    remove_action('wp_footer', 'wp_customize_support_script', 20);

    // 使用data属性替代内联脚本
    add_filter('body_class', function($classes) {
        $supports_postmessage = !empty($_SERVER['HTTP_HOST']) && !empty($_SERVER['REQUEST_METHOD']);
        $classes[] = $supports_postmessage ? 'has-customize-support' : 'no-customize-support';
        return $classes;
    }, 999);

    add_action('wp_head', function() {
        $supports_postmessage = !empty($_SERVER['HTTP_HOST']) && !empty($_SERVER['REQUEST_METHOD']);
        $customize_support = $supports_postmessage ? 'true' : 'false';
        echo '<script data-customize-support="' . esc_attr($customize_support) . '" data-postmessage="' . (function_exists('wp_is_customize_preview') ? 'true' : 'false') . '"></script>';
    }, 999);

    // 移除不必要的脚本
    add_action('wp_footer', function() {
        echo '<script>(function(){var scripts=document.querySelectorAll("script");for(var i=0;i<scripts.length;i++){var text=scripts[i].textContent||scripts[i].innerHTML;if(text&&(text.indexOf("_wp_unfiltered_html_comment")!==-1||text.indexOf("customize-support")!==-1)){scripts[i].remove();}}})();</script>';
    }, 999);

    // 对于非管理员用户，禁用admin bar
    if (!is_admin()) {
        add_filter('show_admin_bar', function($show) {
            return current_user_can('manage_options') ? $show : false;
        });
    }



}

    // 1. 移除静态资源版本号 (安全/隐私)
    // 防止泄露WordPress版本，且在某些配置下提高缓存兼容性
    $remove_ver = function($src) {
        if (strpos($src, 'ver=')) {
            $src = remove_query_arg('ver', $src);
        }
        return $src;
    };
    add_filter('style_loader_src', $remove_ver, 9999);
    add_filter('script_loader_src', $remove_ver, 9999);

    // 2. 禁用自我Pingback (性能)
    // 防止文章内部链接触发Pingback，减少服务器负载
    add_action('pre_ping', function(&$links) {
        $home = get_option('home');
        foreach ($links as $l => $link) {
            if (0 === strpos($link, $home)) {
                unset($links[$l]);
            }
        }
    });

    // 3. 彻底移除 s.w.org DNS预解析 (隐私)
    add_filter('wp_resource_hints', function($urls, $relation_type) {
        if ('dns-prefetch' === $relation_type) {
            // 移除 s.w.org 和其他可能的外部预解析
            $urls = array_filter($urls, function($url) {
                return strpos($url, 's.w.org') === false && 
                       strpos($url, 'fonts.googleapis.com') === false && 
                       strpos($url, 'fonts.gstatic.com') === false;
            });
        }
        return $urls;
    }, 20, 2);

    // 4. 外部链接自动新窗口打开 (UX/SEO)
    add_filter('the_content', 'paper_wp_external_links_target_blank', 999);


/**
 * 外部链接自动添加 target="_blank" 和 rel="noopener noreferrer"
 */
function paper_wp_external_links_target_blank($content) {
    if (empty($content)) return $content;

    // 使用 DOMDocument 处理，避免正则误伤
    $dom = new DOMDocument();
    // 抑制 HTML5 标签警告
    libxml_use_internal_errors(true);
    
    // 确保编码正确
    if (!$dom->loadHTML('<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
        libxml_clear_errors();
        return $content;
    }

    $links = $dom->getElementsByTagName('a');
    $site_url = parse_url(home_url(), PHP_URL_HOST);
    $changed = false;

    foreach ($links as $link) {
        $href = $link->getAttribute('href');
        $current_rel = $link->getAttribute('rel');
        
        // 跳过锚点和相对路径
        if (empty($href) || strpos($href, '#') === 0 || strpos($href, '/') === 0) {
            continue;
        }
        
        // 跳过已经有 lightbox 的链接（灯箱图片）
        if (strpos($current_rel, 'lightbox') !== false) {
            continue;
        }

        $host = parse_url($href, PHP_URL_HOST);
        
        // 如果是外部链接
        if ($host && $host !== $site_url) {
            $link->setAttribute('target', '_blank');
            $link->setAttribute('rel', 'noopener noreferrer nofollow'); // 添加 nofollow 对 SEO 更友好
            $changed = true;
        }
    }

    if ($changed) {
        $new_content = $dom->saveHTML();
        // 移除 DOMDocument 添加的 XML 头和包裹标签
        $new_content = str_replace(['<?xml encoding="utf-8" ?>', '<html>', '<body>', '</html>', '</body>'], '', $new_content);
        libxml_clear_errors();
        return trim($new_content);
    }
    
    libxml_clear_errors();
    return $content;
}

/**
 * 字体和基础样式优化
 */
function paper_wp_optimize_fonts(): void {
    echo '<style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-display: swap;
        }
        img {
            max-width: 100%;
            height: auto;
            loading: lazy;
        }
        * {
            box-sizing: border-box;
        }
        html {
            scroll-behavior: smooth;
        }
        * {
            animation-duration: 0.1s !important;
            transition-duration: 0.1s !important;
        }
    </style>';
}

/**
 * 为脚本添加defer属性
 */
function paper_wp_add_defer_to_scripts(string $tag, string $handle): string {
    // 排除jQuery核心库和后台脚本
    if (is_admin() || in_array($handle, ['jquery-core', 'jquery-migrate'], true)) {
        return $tag;
    }

    // 如果已经有defer属性，不重复添加
    if (strpos($tag, ' defer ') !== false || strpos($tag, ' defer=') !== false) {
        return $tag;
    }

    return str_replace(' src=', ' defer src=', $tag);
}

/**
 * Service Worker相关函数
 */
function paper_wp_add_sw_query_var(array $vars): array {
    $vars[] = 'paper_wp_sw';
    return $vars;
}

function paper_wp_serve_sw_file(): void {
    if (get_query_var('paper_wp_sw')) {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');

        $theme_version = wp_get_theme()->get('Version');
        $sw_version = get_option('paper_wp_sw_version', '1.0');
        $cache_name = "paper-wp-cache-v{$theme_version}-{$sw_version}";
        $offline_url = home_url('/offline/');

        $sw_content = <<<JS
const CACHE_NAME = "{$cache_name}";
const OFFLINE_URL = "{$offline_url}";

// 安装事件：预缓存离线页面
self.addEventListener("install", event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.add(OFFLINE_URL);
        })
    );
    self.skipWaiting();
});

// 激活事件：清理旧缓存
self.addEventListener("activate", event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// 请求拦截：区分策略
self.addEventListener("fetch", event => {
    const { request } = event;
    const url = new URL(request.url);

    // 忽略非 GET 请求和后台/API请求
    if (request.method !== "GET" || url.pathname.startsWith('/wp-admin/') || url.pathname.startsWith('/wp-json/') || url.pathname.includes('customize.php')) {
        return;
    }

    // 策略1: 页面导航 (HTML) -> Network First (网络优先)
    // 确保用户看到最新内容，网络失败时回退到缓存或离线页
    if (request.mode === 'navigate') {
        event.respondWith(
            (async () => {
                try {
                    const networkResponse = await fetch(request);
                    if (networkResponse.ok) {
                        const cache = await caches.open(CACHE_NAME);
                        cache.put(request, networkResponse.clone());
                    }
                    return networkResponse;
                } catch (error) {
                    const cache = await caches.open(CACHE_NAME);
                    const cachedResponse = await cache.match(request);
                    return cachedResponse || await cache.match(OFFLINE_URL);
                }
            })()
        );
    }
    // 策略2: 静态资源 (CSS, JS, Images) -> Stale-While-Revalidate (缓存优先，后台更新)
    // 优先从缓存读取以保证速度，同时在后台发起网络请求更新缓存
    else {
        event.respondWith(
            (async () => {
                const cache = await caches.open(CACHE_NAME);
                const cachedResponse = await cache.match(request);
                
                const fetchPromise = fetch(request).then(networkResponse => {
                    if (networkResponse.ok) {
                        cache.put(request, networkResponse.clone());
                    }
                    return networkResponse;
                }).catch(() => {
                    // 忽略网络错误
                });

                return cachedResponse || fetchPromise;
            })()
        );
    }
});
JS;

        echo $sw_content;
        exit;
    }
}

function paper_wp_register_service_worker(): void {
    // Service Worker功能已禁用
}

function paper_wp_theme_activation(): void {
    add_rewrite_rule('^sw\.js$', 'index.php?paper_wp_sw=true', 'top');
    flush_rewrite_rules();
}

/**
 * 初始化性能优化
 */
add_action('after_setup_theme', 'paper_wp_init_performance_optimization');
add_action('after_switch_theme', 'paper_wp_theme_activation');

/**
 * 修改主页文章预览字数
 * 使用后台设置的值，如果没有设置则使用默认值500
 */
add_filter('excerpt_length', function() {
    $limit = Paper_Settings_Manager::get_field('paper_wp_theme_settings', 'excerpt_word_limit', 500);
    // 正确处理空值或无效值，确保使用默认值500
    if (!is_numeric($limit) || intval($limit) <= 0) {
        $limit = 500;
    }
    return intval($limit);
}, 999);

/**
 * 获取文章字数统计 (正确区分中英文)
 * @return string
 */
function get_post_word_count(): string {
    $content = get_post_field('post_content', get_the_ID());
    
    if (empty($content)) {
        return '0字';
    }
    
    // 清理内容
    $content = strip_shortcodes($content);
    $content = wp_strip_all_tags($content);
    
    if (empty($content)) {
        return '0字';
    }
    
    // 中文字符统计
    preg_match_all('/[\x{4e00}-\x{9fa5}]/u', $content, $chinese_matches);
    $chinese_words = count($chinese_matches[0]);
    
    // 英文单词统计
    preg_match_all("/[a-zA-Z0-9'-]+/", $content, $english_matches);
    $english_words = count($english_matches[0]);
    
    $total_words = $chinese_words + $english_words;
    
    return number_format($total_words) . '字';
}

/**
 * ===========================================
 * Paper WP 简单缓存系统 (from simple-cache.php)
 * 使用WordPress原生缓存，移除复杂的缓存类
 * ===========================================
 */

/**
 * 获取缓存，如果不存在则通过回调生成
 */
function paper_wp_cache_get($key, $group = 'default', $callback = null, $args = []) {
    $cache_key = $group . '_' . $key;
    $value = get_transient($cache_key);

    if (false === $value && $callback) {
        $value = call_user_func_array($callback, $args);
        set_transient($cache_key, $value, HOUR_IN_SECONDS);
    }

    return $value;
}

/**
 * 设置缓存
 */
function paper_wp_cache_set($key, $data, $group = 'default', $duration = null) {
    $cache_key = $group . '_' . $key;
    $duration = $duration ?: HOUR_IN_SECONDS;
    set_transient($cache_key, $data, $duration);
}

/**
 * 删除缓存
 */
function paper_wp_cache_delete($key, $group = 'default') {
    $cache_key = $group . '_' . $key;
    delete_transient($cache_key);
}

/**
 * 清理指定缓存组的所有缓存
 */
function paper_wp_cache_flush_group($group = 'default') {
    global $wpdb;
    $pattern = '_transient_' . $group . '_%';
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $pattern
    ));
}

/**
 * 获取缓存统计信息
 */
function paper_wp_cache_get_stats() {
    global $wpdb;

    // 获取缓存命中和未命中统计（如果有的话）
    $hits = get_option('paper_wp_cache_hits', 0);
    $misses = get_option('paper_wp_cache_misses', 0);

    $total = $hits + $misses;
    $hit_rate = $total > 0 ? round(($hits / $total) * 100, 2) : 0;

    // 获取缓存大小估算
    $cache_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_paper_wp_%'");

    return [
        'hits' => $hits,
        'misses' => $misses,
        'hit_rate' => $hit_rate,
        'total_entries' => $cache_count,
        'size_estimate' => '未知' // 简化实现
    ];
}

/**
 * 记录缓存操作日志
 */
function paper_wp_log_cache_operation($group, $message) {
    $log_entry = [
        'time' => time(),
        'group' => $group,
        'message' => $message,
        'user' => wp_get_current_user()->display_name ?: '系统'
    ];

    $logs = get_option('paper_wp_cache_operation_logs', []);
    array_unshift($logs, $log_entry); // 添加到开头
    $logs = array_slice($logs, 0, 50); // 保留最近50条记录

    update_option('paper_wp_cache_operation_logs', $logs);
}

/**
 * 获取缓存操作日志
 */
function paper_wp_get_cache_operation_log($limit = 10) {
    $logs = get_option('paper_wp_cache_operation_logs', []);
    return array_slice($logs, 0, $limit);
}

/**
 * 清理所有paper_wp相关的缓存
 */
function paper_wp_clear_cache() {
    // 清理所有paper_wp相关的transient
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_paper_wp_%'");
}

/**
 * 清理侧栏缓存
 * 当文章、分类、标签或评论更新时触发
 */
function paper_wp_clear_sidebar_cache() {
    // 核心缓存键列表
    $transient_keys = [
        'paper_wp_tags_cloud',
        'paper_wp_categories_list',
        'paper_wp_archives_list',
        'paper_wp_sidebar_recent_album',
        'paper_wp_latest_posts_cache',
        'paper_wp_recommended_posts_cache',
        'paper_wp_reading_ranking',
        'paper_wp_like_ranking',
        'paper_wp_comment_ranking'
    ];

    $transient_keys = apply_filters('paper_wp_sidebar_cache_keys', $transient_keys);

    // 批量清理 transient 和缓存
    foreach ($transient_keys as $key) {
        delete_transient($key);
        wp_cache_delete($key, 'transient');
        wp_cache_delete('_transient_' . $key, 'options');
        wp_cache_delete('_transient_timeout_' . $key, 'options');
    }

    // 多站点支持
    if (is_multisite()) {
        foreach ($transient_keys as $key) {
            wp_cache_delete('_site_transient_' . $key, 'options');
            wp_cache_delete('_site_transient_timeout_' . $key, 'options');
        }
    }

    // 缓存插件支持
    if (function_exists('w3tc_flush_all')) {
        w3tc_flush_all();
    }
    if (function_exists('wp_super_cache_flush_all')) {
        wp_super_cache_flush_all();
    }

    // 记录日志
    if (function_exists('paper_wp_log_cache_operation')) {
        paper_wp_log_cache_operation('sidebar', '侧栏缓存清理完成');
    }
}
add_action('create_term', 'paper_wp_clear_sidebar_cache');
add_action('edit_terms', 'paper_wp_clear_sidebar_cache');
add_action('delete_term', 'paper_wp_clear_sidebar_cache');
add_action('save_post', 'paper_wp_clear_sidebar_cache');
add_action('delete_post', 'paper_wp_clear_sidebar_cache');
// 评论相关操作也需要清理侧栏缓存（影响评论排行）
add_action('wp_insert_comment', 'paper_wp_clear_sidebar_cache', 20);
add_action('wp_set_comment_status', 'paper_wp_clear_sidebar_cache', 20);
add_action('edit_comment', 'paper_wp_clear_sidebar_cache', 20);
add_action('delete_comment', 'paper_wp_clear_sidebar_cache', 20);

/**
 * ===========================================
 * Paper WP 数据库维护系统 (from database-optimization.php)
 * 整合到性能优化模块中
 * ===========================================
 */

/**
 * 初始化数据库维护
 */
function paper_wp_init_database_maintenance() {
    // 定期清理优化
    add_action('paper_wp_daily_cleanup', 'paper_wp_daily_database_maintenance');

    // 管理通知
    add_action('admin_init', 'paper_wp_database_maintenance_notices');
    
    // 注册每日清理任务
    if (!wp_next_scheduled('paper_wp_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'paper_wp_daily_cleanup');
    }
}
add_action('init', 'paper_wp_init_database_maintenance');

/**
 * 主题切换时清理计划任务
 */
add_action('switch_theme', function() {
    wp_clear_scheduled_hook('paper_wp_daily_cleanup');
});

/**
 * 每日维护任务 - 只保留安全的数据清理
 */
function paper_wp_daily_database_maintenance() {
    global $wpdb;

    // 1. 清理过期的transients
    $expired_timeouts = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options}
             WHERE option_name LIKE %s AND option_value < %d",
            '_transient_timeout_%',
            time()
        )
    );

    if (!empty($expired_timeouts)) {
        $transient_names = array_map(fn($n) => str_replace('_transient_timeout_', '_transient_', $n), $expired_timeouts);
        $all_names = array_merge($expired_timeouts, $transient_names);
        $placeholders = str_repeat('%s,', count($all_names) - 1) . '%s';
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name IN ($placeholders)",
            $all_names
        ));
    }

    // 2. 清理旧的修订版本 (默认保留30天)
    $cleanup_intervals = apply_filters('paper_wp_cleanup_intervals', [
        'revisions' => 30,
        'auto_drafts' => 7,
        'trash_comments' => 30
    ]);

    $revisions_interval = max(0, intval($cleanup_intervals['revisions']));
    if ($revisions_interval > 0) {
        $revisions = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_date < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $revisions_interval
        ));
        foreach ($revisions as $revision_id) {
            wp_delete_post_revision($revision_id);
        }
    }

    // 3. 清理旧的自动草稿和垃圾评论
    foreach (['auto_drafts' => 'posts', 'trash_comments' => 'comments'] as $key => $table) {
        $interval = max(0, intval($cleanup_intervals[$key]));
        if ($interval > 0) {
            $status_field = $table === 'posts' ? 'post_status' : 'comment_approved';
            $date_field = $table === 'posts' ? 'post_date' : 'comment_date';
            $status_value = $table === 'posts' ? 'auto-draft' : 'trash';
            
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->$table} WHERE {$status_field} = %s AND {$date_field} < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $status_value, $interval
            ));
        }
    }

    // 4. 清理自定义统计表 (作为 GC 的安全网)
    // 清理超过 24 小时的在线用户记录
    $table_online = $wpdb->prefix . 'paper_online_users';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_online'") === $table_online) {
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_online WHERE last_active < %d",
            time() - DAY_IN_SECONDS
        ));
    }

    // 清理超过 30 天的管理员会话记录
    $table_sessions = $wpdb->prefix . 'paper_admin_sessions';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_sessions'") === $table_sessions) {
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_sessions WHERE last_update < %d",
            time() - 30 * DAY_IN_SECONDS
        ));
    }

    // 5. 更新数据库统计信息
    $wpdb->query("ANALYZE TABLE {$wpdb->posts}, {$wpdb->postmeta}, {$wpdb->options}");

    // 记录维护时间
    update_option('paper_wp_last_maintenance', time());
}

/**
 * 添加管理通知
 */
function paper_wp_database_maintenance_notices() {
    // 检查是否需要显示维护提醒（每30天提醒一次）
    $last_maintenance = get_option('paper_wp_last_maintenance', 0);
    $current_time = time();

    if ($current_time - $last_maintenance > 30 * 24 * 3600 && current_user_can('manage_options')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-info is-dismissible">
                <p><strong>Paper WP 数据库维护提醒：</strong>建议定期检查数据库健康状态。系统已自动执行每日维护任务。</p>
            </div>';
        });
    }
}

/**
 * 获取数据库性能统计
 */
function paper_wp_get_database_stats() {
    global $wpdb;

    $stats = [
        'total_posts' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'"),
        'total_comments' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = '1'"),
        'total_options' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options}"),
        'database_size' => paper_wp_get_database_size(),
        'last_maintenance' => get_option('paper_wp_last_maintenance', 0)
    ];

    return $stats;
}

/**
 * 获取数据库大小
 */
function paper_wp_get_database_size() {
    global $wpdb;

    $size = $wpdb->get_var("
        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2)
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
    ");

    return $size ? $size . ' MB' : '未知 (权限不足)';
}

/**
 * AJAX处理：重置SW缓存
 */
function paper_wp_ajax_reset_sw_cache() {
    check_ajax_referer('paper_wp_reset_sw_cache', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('权限不足');
    }
    
    // 生成新的版本号 (时间戳)
    $new_version = time();
    update_option('paper_wp_sw_version', $new_version);
    
    wp_send_json_success(['version' => $new_version]);
}
add_action('wp_ajax_paper_wp_reset_sw_cache', 'paper_wp_ajax_reset_sw_cache');
