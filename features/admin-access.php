<?php
/**
 * 后台管理与优化功能
 * 包含：访问控制、界面精简、移除无用组件、后台性能优化
 * @author wangdaodao
 */

if (!defined('ABSPATH')) exit;

/**
 * ===========================================
 * 1. 访问控制部分
 * ===========================================
 */

/**
 * 禁用 Admin Bar
 * 仅对非管理员用户生效
 */
function paper_wp_disable_admin_bar() {
    if (Paper_Settings_Manager::is_enabled('paper_wp_admin_settings', 'disable_admin_bar_subscribers')) {
        if (!current_user_can('administrator')) {
            show_admin_bar(false);
        }
    }
}
add_action('after_setup_theme', 'paper_wp_disable_admin_bar');

/**
 * 限制后台访问
 * 非管理员用户访问后台将被重定向到首页
 */
function paper_wp_restrict_admin_access() {
    if (Paper_Settings_Manager::is_enabled('paper_wp_admin_settings', 'restrict_admin_access')) {
        // 允许 AJAX 请求
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        
        // 允许管理员访问
        if (current_user_can('administrator')) {
            return;
        }

        // 重定向到首页
        wp_redirect(home_url());
        exit;
    }
}
add_action('admin_init', 'paper_wp_restrict_admin_access');


/**
 * ===========================================
 * 2. 界面精简部分
 * ===========================================
 */

/**
 * 移除仪表盘小工具
 */
function paper_wp_clean_dashboard_widgets() {
    if (!Paper_Settings_Manager::is_enabled('paper_wp_admin_settings', 'clean_dashboard')) return;

    global $wp_meta_boxes;
    
    // 移除 "概览" (Right Now)
    unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_right_now']);
    // 移除 "活动" (Activity)
    unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_activity']);
    // 移除 "快速草稿" (Quick Press)
    unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_quick_press']);
    // 移除 "WordPress 新闻" (Primary)
    unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_primary']);
    // 移除 "站点健康" (Site Health)
    unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_site_health']);
    
    // 移除欢迎面板
    remove_action('welcome_panel', 'wp_welcome_panel');
}
add_action('wp_dashboard_setup', 'paper_wp_clean_dashboard_widgets', 999);

/**
 * 移除 Admin Bar 上的元素
 */
function paper_wp_clean_admin_bar($wp_admin_bar) {
    if (!Paper_Settings_Manager::is_enabled('paper_wp_admin_settings', 'clean_admin_bar')) return;

    $wp_admin_bar->remove_node('wp-logo');      // 移除 WordPress Logo
    $wp_admin_bar->remove_node('comments');     // 移除评论图标
    // $wp_admin_bar->remove_node('new-content');  // 保留"新建"菜单，方便发布内容
}
add_action('admin_bar_menu', 'paper_wp_clean_admin_bar', 999);

/**
 * 移除后台页脚信息
 */
function paper_wp_clean_admin_footer() {
    if (Paper_Settings_Manager::is_enabled('paper_wp_admin_settings', 'clean_footer')) {
        add_filter('admin_footer_text', '__return_empty_string');
        add_filter('update_footer', '__return_empty_string', 11);
    }
}
add_action('admin_init', 'paper_wp_clean_admin_footer');

/**
 * 移除左侧菜单项
 */
function paper_wp_clean_admin_menu() {
    if (Paper_Settings_Manager::is_enabled('paper_wp_admin_settings', 'remove_menu_comments')) {
        remove_menu_page('edit-comments.php'); // 移除评论
    }
    
    if (Paper_Settings_Manager::is_enabled('paper_wp_admin_settings', 'remove_menu_tools')) {
        remove_menu_page('tools.php'); // 移除工具
    }
}
add_action('admin_menu', 'paper_wp_clean_admin_menu', 999);

/**
 * 禁用仪表盘页面
 * 移除菜单并重定向到文章列表
 */
function paper_wp_disable_dashboard_page() {
    if (!Paper_Settings_Manager::is_enabled('paper_wp_admin_settings', 'disable_dashboard_page')) return;

    // 移除菜单
    remove_menu_page('index.php');

    // 如果当前访问的是仪表盘页面，重定向到文章列表
    global $pagenow;
    if ($pagenow == 'index.php') {
        wp_redirect(admin_url('edit.php'));
        exit;
    }
}
add_action('admin_menu', 'paper_wp_disable_dashboard_page', 999); // 移除菜单
add_action('admin_init', 'paper_wp_disable_dashboard_page');      // 执行重定向

/**
 * ===========================================
 * 3. 后台性能优化部分
 * ===========================================
 */

/**
 * 优化心跳检测 (Heartbeat)
 * 将频率降低至 60 秒
 */
function paper_wp_optimize_heartbeat_settings($settings) {
    if (Paper_Settings_Manager::is_enabled('paper_wp_admin_settings', 'optimize_heartbeat')) {
        $settings['interval'] = 60; // 60秒
    }
    return $settings;
}
add_filter('heartbeat_settings', 'paper_wp_optimize_heartbeat_settings');

/**
 * 延长自动保存间隔
 * 改为 300 秒 (5分钟)
 */
function paper_wp_change_autosave_interval() {
    if (Paper_Settings_Manager::is_enabled('paper_wp_admin_settings', 'increase_autosave_interval')) {
        define('AUTOSAVE_INTERVAL', 300);
    }
}
add_action('init', 'paper_wp_change_autosave_interval');

/**
 * 屏蔽后台通知
 * 隐藏大部分非错误类的通知
 */
function paper_wp_hide_admin_notices() {
    if (Paper_Settings_Manager::is_enabled('paper_wp_admin_settings', 'hide_admin_notices')) {
        echo '<style>
            .notice.is-dismissible:not(.notice-error):not(.notice-warning):not(.updated) { display: none !important; }
            /* 针对一些特定插件的广告横幅 */
            div.updated.fade, div.updated.notice { display: none; }
            /* 保留核心更新提示 */
            .update-nag { display: block !important; }
        </style>';
    }
}
add_action('admin_head', 'paper_wp_hide_admin_notices');

/**
 * 禁用文件编辑器
 */
function paper_wp_disable_file_editor() {
    if (Paper_Settings_Manager::is_enabled('paper_wp_admin_settings', 'disable_file_editor')) {
        if (!defined('DISALLOW_FILE_EDIT')) {
            define('DISALLOW_FILE_EDIT', true);
        }
    }
}
add_action('init', 'paper_wp_disable_file_editor');
