<?php
/**
 * barepaper WordPress 主题函数文件
 * @author wangdaodao
 * @version 3.1.0
 */

if (!defined('ABSPATH')) exit;

define('BAREPAPER_VERSION', '1.6.10');

// 核心模块加载
require_once get_template_directory() . '/core/setup.php';
require_once get_template_directory() . '/core/assets.php';
require_once get_template_directory() . '/core/admin.php';
require_once get_template_directory() . '/autoload.php';
