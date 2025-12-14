<?php
/**
 * barepaper WordPress 主题函数文件
 * @author wangdaodao
 * @version 3.1.0
 */

if (!defined('ABSPATH')) exit;

define('BAREPAPER_VERSION', '3.1.0');

// 加载核心文件
require_once get_template_directory() . '/core/setup.php';     // 核心设置与初始化 (含设置管理、自动加载、工具函数)
require_once get_template_directory() . '/core/assets.php';    // 资源管理
require_once get_template_directory() . '/core/admin.php';     // 后台管理
