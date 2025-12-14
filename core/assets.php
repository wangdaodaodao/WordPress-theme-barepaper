<?php
/**
 * 核心资源管理模块
 * @author wangdaodao
 * @version 3.1.0
 */

if (!defined('ABSPATH')) exit;

function paper_wp_scripts() {
    $effects_settings = Paper_Settings_Manager::get('paper_wp_effects_settings', []);
    $paper_wp_settings = Paper_Settings_Manager::get_theme_settings();

    // 使用同步CSS加载
    add_action('wp_head', 'paper_wp_load_css_sync', 2);



    // 主题切换兼容性脚本 - 优化为更简洁的版本
    add_action('wp_head', function() use ($effects_settings) {
        // 主题切换功能现在总是启用
        $theme_mode = $effects_settings['theme_mode'] ?? 'auto';
        
        echo '<script>!function(){try{var t=localStorage.getItem("typecho-theme-preference"),n=localStorage.getItem("barepaper_theme_preference");t&&!n&&(localStorage.setItem("barepaper_theme_preference",t),localStorage.removeItem("typecho-theme-preference")),(t||n)&&("light"===t||"dark"===t||"light"===n||"dark"===n)&&document.documentElement.setAttribute("data-theme",t||n)}catch(e){}}();';
        echo 'window.paperWpSettings={enable_theme_switch:"1",theme_mode:"' . esc_attr($theme_mode) . '"};</script>';
    }, 1);

    // 效果脚本条件加载 - 合并为effects.js
    // 主题切换功能总是启用，所以总是加载effects.js
    $should_load_effects = true;

    if ($should_load_effects) {
        wp_enqueue_script('effects', get_template_directory_uri() . '/js/effects.js', ['jquery'], BAREPAPER_VERSION, true);
    }

    if (!empty($paper_wp_settings['show_recent_album'])) {
        // sidebar.css 已合并入 layout.css (core css)，无需单独加载
        wp_enqueue_script('paper-sidebar-album', get_template_directory_uri() . '/js/sidebar-album.js', [], BAREPAPER_VERSION, true);
    }



    // 统一加载 interactions.js，它现在包含了多个小脚本的功能
    // 需要加载的情况：
    // 1. 文章页 (代码复制, 推荐, 赞助)
    // 2. 启用在线状态统计 (全站)
    // 3. 包含代码块或特定短代码
    
    $should_load_interactions = false;
    
    if (is_singular()) {
        $should_load_interactions = true; // 文章页总是加载，用于推荐、赞助等
    }
    
    if ($should_load_interactions) {
        wp_enqueue_script('paper-interactions', get_template_directory_uri() . '/js/interactions.js', ['jquery'], BAREPAPER_VERSION, true);
    }
}

// 使用 data 属性而不是内联脚本 - 统一处理所有脚本
function paper_wp_add_script_data_attributes($tag, $handle, $src) {
    $ajax_url = esc_attr(admin_url('admin-ajax.php'));
    $attributes = [];
    
    switch ($handle) {
        case 'paper-interactions':
            global $post;
            $attributes[] = 'data-ajax-url="' . $ajax_url . '"';
            $attributes[] = 'data-nonce="' . esc_attr(wp_create_nonce('paper_wp_view_nonce')) . '"';
            $attributes[] = 'data-post-id="' . esc_attr($post ? $post->ID : 0) . '"';
            
            // Online Status Data
            $attributes[] = 'data-online-nonce="' . esc_attr(wp_create_nonce('paper_wp_online_nonce')) . '"';
            
            // Interactions Nonce (Like/Recommend)
            $attributes[] = 'data-interactions-nonce="' . esc_attr(wp_create_nonce('paper_wp_interactions_nonce')) . '"';
            
            // Slimbox Data
            if (class_exists('Paper_Image')) {
                $lightbox_rel = esc_attr(Paper_Image::LIGHTBOX_REL);
                $thumbnail_attr = esc_attr(Paper_Image::THUMBNAIL_DATA_ATTR);
                $full_src_attr = esc_attr(Paper_Image::FULL_SRC_DATA_ATTR);
            } else {
                $lightbox_rel = 'lightbox';
                $thumbnail_attr = 'data-thumbnail-src';
                $full_src_attr = 'data-full-src';
            }
            $attributes[] = 'data-lightbox-rel="' . $lightbox_rel . '"';
            $attributes[] = 'data-thumbnail-data-attr="' . $thumbnail_attr . '"';
            $attributes[] = 'data-full-src-data-attr="' . $full_src_attr . '"';
            break;
            
        case 'effects':
            // 丝带和鼠标特效已移除
            break;
    }
    
    if (!empty($attributes)) {
        $tag = str_replace('<script ', '<script ' . implode(' ', $attributes) . ' ', $tag);
    }
    
    return $tag;
}
add_filter('script_loader_tag', 'paper_wp_add_script_data_attributes', 10, 3);

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
    $defer_scripts = ['scroll-to-top', 'recommendation', 'paper-interactions', 'effects', 'aplayer-js', 'meting-js'];
    if (in_array($handle, $defer_scripts)) {
        $tag = str_replace('<script ', '<script defer ', $tag);
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

/**
 * 获取核心CSS文件列表
 */
function paper_wp_get_core_css_files() {
    static $css_files = null;
    if ($css_files === null) {
        $base_url = get_template_directory_uri() . '/css/';
        $css_files = [
            'variables' => $base_url . 'variables.css',
            'reset' => $base_url . 'reset.css',
            'grid' => $base_url . 'grid.css',
            'core' => $base_url . 'core.css',
            'layout' => $base_url . 'layout.css', 
            'post' => $base_url . 'post.css',
            'comments' => $base_url . 'comments.css',

            'responsive' => $base_url . 'responsive.css'
        ];
    }
    return $css_files;
}

/**
 * 获取异步CSS加载的JavaScript
 */
function paper_wp_get_async_css_js() {
    return <<<JS
(function() {
    var supportsOnload = 'onload' in document.createElement('link');
    if (!supportsOnload) {
        var links = document.querySelectorAll('link[rel="preload"][as="style"]');
        for (var i = 0; i < links.length; i++) {
            links[i].rel = 'stylesheet';
            links[i].removeAttribute('onload');
        }
    }
    window.addEventListener('load', function() {
        setTimeout(function() {
            var preloadedLinks = document.querySelectorAll('link[rel="preload"][as="style"]');
            for (var i = 0; i < preloadedLinks.length; i++) {
                var link = preloadedLinks[i];
                if (link.rel === 'preload') {
                    link.rel = 'stylesheet';
                    link.removeAttribute('onload');
                }
            }
        }, 100);
    });
})();
JS;
}

/**
 * 统一的CSS加载函数
 */
function paper_wp_load_css($async = false) {
    $css_files = paper_wp_get_core_css_files();
    $ver = BAREPAPER_VERSION;

    if ($async) {
        // 异步加载：variables.css 同步，其他异步
        echo "<link rel='stylesheet' href='{$css_files['variables']}?ver={$ver}'>";
        unset($css_files['variables']);

        foreach ($css_files as $handle => $url) {
            $id = 'css-' . $handle;
            echo "<link rel='preload' href='{$url}?ver={$ver}' as='style' onload=\"this.onload=null;this.rel='stylesheet'\" id='{$id}'>";
            echo "<noscript><link rel='stylesheet' href='{$url}?ver={$ver}'></noscript>";
        }
        echo '<script>' . paper_wp_get_async_css_js() . '</script>';
    } else {
        // 同步加载
        foreach ($css_files as $url) {
            echo "<link rel='stylesheet' href='{$url}?ver={$ver}'>";
        }
    }

    // 条件加载编辑器CSS
    if (paper_wp_should_load_editor_css()) {
        $editor_url = get_template_directory_uri() . '/css/editor.css';
        if ($async) {
            echo "<link rel='preload' href='{$editor_url}?ver={$ver}' as='style' onload=\"this.onload=null;this.rel='stylesheet'\" id='css-editor'>";
            echo "<noscript><link rel='stylesheet' href='{$editor_url}?ver={$ver}'></noscript>";
        } else {
            echo "<link rel='stylesheet' href='{$editor_url}?ver={$ver}'>";
        }
    }
}

/**
 * 检查是否需要加载编辑器CSS
 */
function paper_wp_should_load_editor_css() {
    if (!is_singular()) return false;

    global $post;
    if (!$post) return false;

    return has_shortcode($post->post_content, 'code') ||
           strpos($post->post_content, '```') !== false ||
           has_shortcode($post->post_content, 'alert');
}

function paper_wp_load_css_sync() {
    paper_wp_load_css(false);
}

function paper_wp_load_css_async() {
    paper_wp_load_css(true);
}
