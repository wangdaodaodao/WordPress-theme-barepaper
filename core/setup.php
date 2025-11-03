<?php
/**
 * 核心设置 - 主题基础配置
 * @author wangdaodao
 * @version 3.0.6
 */

if (!defined('ABSPATH')) exit;

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
    // 功能已禁用 - 具体逻辑已移除，保留函数调用结构
    // $editor_settings = get_option('paper_wp_editor_settings');
    // if (!empty($editor_settings['disable_default_editor'])) {
    //     add_filter('use_block_editor_for_post', '__return_false');
    //     add_filter('use_block_editor_for_post_type', '__return_false', 10, 2);
    // }
}
add_action('admin_init', 'paper_wp_setup_editor');
