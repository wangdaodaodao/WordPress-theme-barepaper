<?php
/**
 * 功能模块自动加载系统
 * @author wangdaodao
 * @version 3.0.6
 */

if (!defined('ABSPATH')) exit;

function paper_wp_autoload_features() {
    // 缓存配置加载，避免重复文件读取
    static $config = null;
    if ($config === null) {
        $config = require get_template_directory() . '/core/features.php';
    }
    
    $is_admin = is_admin();

    foreach ($config as $feature => $settings) {
        // 延迟计算启用状态，避免不必要的函数调用
        if (is_callable($settings['enabled'])) {
            if (!$settings['enabled']()) continue;
        } else {
            if (!$settings['enabled']) continue;
        }

        $load_in_admin = $settings['load_in_admin'] ?? true;
        $load_in_frontend = $settings['load_in_frontend'] ?? true;
        
        $should_load = ($is_admin && $load_in_admin) || (!$is_admin && $load_in_frontend);
        
        if (defined('DOING_AJAX') && DOING_AJAX) {
            $should_load = true;
        }

        if ($should_load && file_exists(get_template_directory() . '/' . $settings['file'])) {
            require_once get_template_directory() . '/' . $settings['file'];
        }
    }
}
add_action('after_setup_theme', 'paper_wp_autoload_features', 5);
