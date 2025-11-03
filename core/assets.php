<?php
/**
 * 核心资源管理模块
 * @author wangdaodao
 * @version 3.1.0
 */

if (!defined('ABSPATH')) exit;
function paper_wp_scripts() {
    // 缓存设置获取，避免重复查询数据库
    static $performance_settings = null;
    static $effects_settings = null;
    static $paper_wp_settings = null;
    
    if ($performance_settings === null) {
        $performance_settings = get_option('paper_wp_performance_settings', []);
        $effects_settings = get_option('paper_wp_effects_settings', []);
        $paper_wp_settings = get_option('paper_wp_theme_settings', []);
    }
    
    // 功能正在开发中
    // $enable_css_async = !empty($performance_settings['enable_css_async_loading']);
    // add_action('wp_head', $enable_css_async ? 'paper_wp_load_css_async' : 'paper_wp_load_css_sync', 2);
    
    add_action('wp_head', 'paper_wp_inline_critical_css', 1);
    add_action('wp_head', 'paper_wp_load_css_sync', 2);

    // 条件加载脚本，减少不必要的资源加载
    if (is_front_page() || is_single()) {
        wp_enqueue_script('jinrishici', get_template_directory_uri() . '/js/jinrishici.js', [], BAREPAPER_VERSION, true);
    }

    // 内联脚本优化 - 减少DOM操作
    // 功能正在开发中
    // add_action('wp_head', function() use ($effects_settings) {
    //     if (!empty($effects_settings['show_theme_toggle'])) { ... }
    // }, 1);

    // 效果脚本条件加载
    // 功能正在开发中
    // if (!empty($effects_settings['show_ribbons_effect'])) {
    //     wp_enqueue_script('ribbons', ...);
    // }
    // if (!empty($effects_settings['show_cursor_effect'])) {
    //     wp_enqueue_script('cursor', ...);
    // }

    // 功能正在开发中
    // if (!empty($paper_wp_settings['show_recent_album'])) {
    //     wp_enqueue_style('paper-sidebar-album', ...);
    //     wp_enqueue_script('paper-sidebar-album', ...);
    // }

    // 功能正在开发中
    // if (!empty($effects_settings['show_theme_toggle'])) {
    //     wp_enqueue_script('theme-toggle', ...);
    // }

    // 音乐播放器和统计脚本加载条件 - 提前检查，避免依赖$post变量
    $should_load_music_scripts = false;

    // 条件1: 是音乐页面模板
    if (is_page_template('template-music.php')) {
        $should_load_music_scripts = true;
    }

    // 条件2: 当前页面包含music短代码（在wp_head之后$post已经可用）
    global $post;
    if ($post && has_shortcode($post->post_content, 'music')) {
        $should_load_music_scripts = true;
    }

    // 条件3: 当前页面包含9ku短代码（也需要音乐播放器）
    if ($post && has_shortcode($post->post_content, '9ku')) {
        $should_load_music_scripts = true;
    }

    if ($should_load_music_scripts) {
        wp_enqueue_script('aplayer-js', get_template_directory_uri() . '/js/APlayer.min.js', ['jquery'], BAREPAPER_VERSION, true);
        wp_enqueue_script('meting-js', get_template_directory_uri() . '/js/Meting.min.js', ['aplayer-js'], BAREPAPER_VERSION, true);

        // 加载音乐统计追踪脚本
        wp_enqueue_script('music-stats-tracker', get_template_directory_uri() . '/js/music-stats-tracker.js', ['jquery', 'aplayer-js'], BAREPAPER_VERSION, true);
        wp_localize_script('music-stats-tracker', 'musicStatsData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('music_stats_nonce')
        ]);
    }

    // 处理其他页面脚本
    if (is_singular()) {
        global $post;
        $content = $post ? $post->post_content : '';
        $post_id = $post ? $post->ID : 0;

        $has_code_content = has_shortcode($content, 'code') || strpos($content, '```') !== false;
        if ($has_code_content) {
            wp_enqueue_script('paper-copy-handler', get_template_directory_uri() . '/js/copy-handler.js', ['jquery'], BAREPAPER_VERSION, true);
        }

        if (is_singular('post')) {
            wp_enqueue_script('recommendation', get_template_directory_uri() . '/js/recommendation.js', ['jquery'], BAREPAPER_VERSION, true);
            wp_localize_script('recommendation', 'paper_wp_ajax', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('paper_wp_recommend_nonce')]);
            wp_enqueue_script('paper-view-counter', get_template_directory_uri() . '/js/view-counter.js', ['jquery'], BAREPAPER_VERSION, true);
            wp_localize_script('paper-view-counter', 'view_counter_ajax', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('paper_wp_view_nonce'), 'post_id' => $post_id]);
        }

        $paper_wp_settings = get_option('paper_wp_theme_settings', []);
        if (!empty($paper_wp_settings['show_sponsor_module'])) {
            // 功能正在开发中
            // wp_enqueue_script('sponsor-qr', get_template_directory_uri() . '/js/sponsor-qr.js', ['jquery'], BAREPAPER_VERSION, true);
        }
    }

    if (is_page_template('template-booklist.php')) {
        wp_enqueue_script('booklist-animation', get_template_directory_uri() . '/js/booklist-animation.js', [], BAREPAPER_VERSION, true);
        wp_enqueue_style('booklist', get_template_directory_uri() . '/css/booklist.css', [], BAREPAPER_VERSION);
    }

    // 在线状态实时更新脚本 - 在所有页面加载
    wp_enqueue_script('paper-online-status', get_template_directory_uri() . '/js/online-status.js', ['jquery'], BAREPAPER_VERSION, true);
    wp_localize_script('paper-online-status', 'paperWpOnlineConfig', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('paper_wp_online_nonce')
    ]);
}

function paper_wp_handle_post_recommend() {
    check_ajax_referer('paper_wp_recommend_nonce', 'nonce');
    $post_id = intval($_POST['post_id']);
    if (!$post_id || !get_post($post_id)) {
        wp_send_json_error(['message' => '无效的文章ID']);
    }
    $ip = $_SERVER['REMOTE_ADDR'];
    $cooldown_key = 'recommend_' . $post_id . '_' . $ip;
    if (get_transient($cooldown_key)) {
        wp_send_json_error(['message' => '您已经点赞过了，请稍后再试']);
    }
    $count = get_post_meta($post_id, '_post_recommend_count', true) ?: 0;
    $count++;
    update_post_meta($post_id, '_post_recommend_count', $count);
    set_transient($cooldown_key, true, 3600);
    wp_send_json_success(['new_count' => $count]);
}

function paper_wp_handle_track_post_views() {
    check_ajax_referer('paper_wp_view_nonce', 'nonce');
    $post_id = intval($_POST['post_id']);
    if (!$post_id || get_post_status($post_id) !== 'publish') {
        wp_send_json_error(['message' => '无效的文章ID']);
    }
    $current_views = get_post_meta($post_id, 'post_views_count', true) ?: 0;
    $new_views = $current_views + 1;
    update_post_meta($post_id, 'post_views_count', $new_views);
    wp_send_json_success(['message' => '阅读计数已更新', 'new_views' => $new_views]);
}

add_action('wp_ajax_track_post_views', 'paper_wp_handle_track_post_views');
add_action('wp_ajax_nopriv_track_post_views', 'paper_wp_handle_track_post_views');
add_action('wp_ajax_post_recommend', 'paper_wp_handle_post_recommend');
add_action('wp_ajax_nopriv_post_recommend', 'paper_wp_handle_post_recommend');
add_action('wp_enqueue_scripts', 'paper_wp_scripts');

function paper_wp_improve_script_loading($tag, $handle, $src) {
    $critical_scripts = ['jquery', 'jquery-core', 'jquery-migrate'];
    if (in_array($handle, $critical_scripts)) {
        return $tag;
    }
    $defer_scripts = ['scroll-to-top', 'recommendation', 'paper-view-counter', 'sponsor-qr'];
    if (in_array($handle, $defer_scripts)) {
        return str_replace('<script ', '<script defer ', $tag);
    }
    return $tag;
}
add_filter('script_loader_tag', 'paper_wp_improve_script_loading', 10, 3);

function paper_wp_ensure_jquery() {
    if (!wp_script_is('jquery', 'enqueued')) {
        wp_enqueue_script('jquery');
    }
}
add_action('wp_enqueue_scripts', 'paper_wp_ensure_jquery', 1);

function paper_wp_load_css_sync() {
    $css_files = [
        'variables' => get_template_directory_uri() . '/css/variables.css',
        'reset' => get_template_directory_uri() . '/css/reset.css',
        'grid' => get_template_directory_uri() . '/css/grid.css',
        'base' => get_template_directory_uri() . '/css/base.css',
        'header' => get_template_directory_uri() . '/css/header.css',
        'post' => get_template_directory_uri() . '/css/post.css',
        'comments' => get_template_directory_uri() . '/css/comments.css',
        'sidebar' => get_template_directory_uri() . '/css/sidebar.css',
        'components' => get_template_directory_uri() . '/css/components.css',
        'utilities' => get_template_directory_uri() . '/css/utilities.css',
        'responsive' => get_template_directory_uri() . '/css/responsive.css'
    ];
    
    foreach ($css_files as $handle => $url) {
        $ver = BAREPAPER_VERSION;
        echo "<link rel='stylesheet' href='{$url}?ver={$ver}'>";
    }
}

function paper_wp_get_cached_tags() {
    $cache_key = 'paper_wp_tags_cloud';
    $cached_tags = get_transient($cache_key);
    if (false === $cached_tags) {
        $cached_tags = get_terms([
            'taxonomy' => 'post_tag',
            'orderby' => 'count',
            'order' => 'DESC',
            'number' => 50,
            'hide_empty' => true
        ]);
        set_transient($cache_key, $cached_tags, 24 * HOUR_IN_SECONDS);
    }
    return $cached_tags;
}

function paper_wp_get_cached_categories() {
    $cache_key = 'paper_wp_categories_list';
    $cached_cats = get_transient($cache_key);
    if (false === $cached_cats) {
        $cached_cats = get_categories([
            'orderby' => 'name',
            'show_count' => false,
            'hide_empty' => true,
            'number' => 50
        ]);
        set_transient($cache_key, $cached_cats, 48 * HOUR_IN_SECONDS);
    }
    return $cached_cats;
}

function paper_wp_get_cached_archives() {
    $cache_key = 'paper_wp_archives_list';
    $cached_archives = get_transient($cache_key);
    if (false === $cached_archives) {
        $cached_archives = wp_get_archives([
            'type' => 'monthly',
            'show_post_count' => true,
            'echo' => false,
            'limit' => 24
        ]);
        // 手动处理输出格式，确保数字和月份在同一行且样式统一
        $cached_archives = preg_replace('/<a([^>]+)>([^<]+)<\/a>&nbsp;\(([^)]+)\)/', '<a$1>$2 ($3)</a>', $cached_archives);
        set_transient($cache_key, $cached_archives, 72 * HOUR_IN_SECONDS);
    }
    return $cached_archives;
}

function paper_wp_clear_sidebar_cache() {
    // 侧栏缓存键列表 - 核心缓存键
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

    // 加上过滤器，使其变得可扩展
    $transient_keys = apply_filters('paper_wp_sidebar_cache_keys', $transient_keys);

    // 预构建缓存键变体，避免在循环中重复字符串操作
    $cache_key_variants = [];
    foreach ($transient_keys as $key) {
        $cache_key_variants[$key] = [
            '_transient_' . $key,
            '_transient_timeout_' . $key,
            $key
        ];
    }

    // 批量清理 transient（减少数据库查询）
    foreach ($transient_keys as $key) {
        delete_transient($key);
    }

    // 批量清理对象缓存（减少缓存操作）
    foreach ($cache_key_variants as $variants) {
        foreach ($variants as $cache_key) {
            wp_cache_delete($cache_key, 'options');
            wp_cache_delete($cache_key, 'transient');
            wp_cache_delete($cache_key, 'default');
        }
    }

    // 多站点支持（条件检查优化）
    static $is_multisite_checked = false;
    static $is_multisite = false;

    if (!$is_multisite_checked) {
        $is_multisite = is_multisite();
        $is_multisite_checked = true;
    }

    if ($is_multisite) {
        foreach ($cache_key_variants as $variants) {
            foreach ($variants as $cache_key) {
                wp_cache_delete($cache_key, 'site-transient');
                wp_cache_delete('_site_transient_' . substr($cache_key, 11), 'options');
                wp_cache_delete('_site_transient_timeout_' . substr($cache_key, 11), 'options');
            }
        }
    }

    // 缓存插件支持（静态检查，避免重复函数存在检查）
    static $cache_plugins_checked = false;
    static $cache_plugins_available = [];

    if (!$cache_plugins_checked) {
        $cache_plugins_available = [
            'w3tc' => function_exists('w3tc_flush_all'),
            'wp_super_cache' => function_exists('wp_super_cache_flush_all')
        ];
        $cache_plugins_checked = true;
    }

    if ($cache_plugins_available['w3tc']) {
        w3tc_flush_all();
    }

    if ($cache_plugins_available['wp_super_cache']) {
        wp_super_cache_flush_all();
    }

    // 记录清理操作日志（条件检查优化）
    static $log_function_available = null;
    if ($log_function_available === null) {
        $log_function_available = function_exists('paper_wp_log_cache_operation');
    }

    if ($log_function_available) {
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

function paper_wp_inline_critical_css() {
    // 压缩后的关键CSS（移除注释、空格和换行符）
    $critical_css = "*{box-sizing:border-box}:root{--color-bg:#fff;--color-text:#333;--color-text-muted:#666;--color-link:#007cba;--color-border:#eee;--color-bg-secondary:#f3f3f3}[data-theme=dark]{--color-bg:#1a1a1a;--color-text:#7b7b7b;--color-text-muted:#7b7b7b;--color-link:#7b7b7b;--color-border:#54585b;--color-bg-secondary:#0d0d0d}html{background:#fff!important;color:#333!important}html[data-theme=dark]{background:#1a1a1a!important;color:#7b7b7b!important}html,body{margin:0;padding:0;width:100%;height:100%;background:var(--color-bg)!important;color:var(--color-text)!important}body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica Neue,Arial,sans-serif;font-size:16px;line-height:1.6}.container{max-width:1200px;margin:0 auto;padding:0 20px}#header{background:var(--color-bg);border-bottom:1px solid var(--color-border);padding:20px 0}.site-name h1{font-size:28px;margin:0;font-weight:700}.site-name h1 a{text-decoration:none;color:var(--color-text)}.nav-list{display:flex;list-style:none;margin:0;padding:0;gap:20px}.nav-list a{text-decoration:none;color:var(--color-text-muted);padding:10px 0}.nav-list a:hover{color:var(--color-link)}#body{padding:40px 0}.post{padding:15px 0 20px}.post .post-title{margin:.83em 0;font-size:1.3em;font-weight:400;color:var(--color-link)!important}.post .post-title a{color:var(--color-link)!important;text-decoration:none}.post-content{line-height:1.5}h1,h2,h3,h4,h5,h6{margin-top:0;margin-bottom:20px;font-weight:600;line-height:1.3}p{margin:0 0 20px 0}a{color:var(--color-link);text-decoration:none}a:hover{text-decoration:underline}.content-image{max-width:100%;height:auto;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.12)}.container,.row [class*=col-]{-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}.container{margin-left:auto;margin-right:auto;padding-left:10px;padding-right:10px}.row{margin-right:-10px;margin-left:-10px}.row [class*=col-]{float:left;min-height:1px;padding-right:10px;padding-left:10px}.col-12{width:100%}.col-9{width:75%}.col-8{width:66.66667%}.col-3{width:25%}.col-mb-12{width:100%}.clearfix:before,.clearfix:after,.row:before,.row:after{content:' ';display:table}.clearfix:after,.row:after{clear:both}.header-ad{width:100%;margin:0 0 500px 0;padding:0 20px;overflow:hidden}@media (max-width:767px){body{font-size:14px}.container{padding:0 15px}#header{padding:15px 0}.nav-list{gap:15px}}";
    echo '<style>' . $critical_css . '</style>';
}

function paper_wp_load_css_async() {
    // 功能正在开发中
    // $performance_settings = get_option('paper_wp_performance_settings', []);
    // $enable_async_css = !empty($performance_settings['enable_css_async_loading']);
    // if ($enable_async_css) { ... }
}
