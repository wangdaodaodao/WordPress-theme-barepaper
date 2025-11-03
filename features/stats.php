<?php
if (!defined('ABSPATH')) exit;

// ============================================================================
// 在线用户统计系统（全新重构）
// ============================================================================

/**
 * 创建在线用户表
 */
function paper_wp_create_online_users_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'paper_online_users';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned DEFAULT '0',
        visitor_hash varchar(64) NOT NULL,
        last_active int(11) unsigned NOT NULL,
        created_at int(11) unsigned NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY visitor_hash (visitor_hash),
        KEY user_id (user_id),
        KEY last_active (last_active)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * 迁移旧版本的在线用户表结构
 */
function paper_wp_migrate_online_users_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'paper_online_users';
    
    // 检查表是否存在
    if (!paper_wp_stats_table_exists('paper_online_users')) {
        return ['success' => false, 'message' => '表不存在'];
    }
    
    // 获取当前表结构
    $columns = $wpdb->get_col("DESCRIBE $table_name");
    
    $has_user_hash = in_array('user_hash', $columns);
    $has_visitor_hash = in_array('visitor_hash', $columns);
    $has_last_seen = in_array('last_seen', $columns);
    $has_last_active = in_array('last_active', $columns);
    $has_created_at = in_array('created_at', $columns);
    
    $operations = [];
    
    // 情况1：有 user_hash 但没有 visitor_hash，需要重命名
    if ($has_user_hash && !$has_visitor_hash) {
        // 先检查索引
        $indexes = $wpdb->get_results("SHOW INDEX FROM $table_name WHERE Key_name = 'user_hash'");
        
        // 删除旧索引（如果存在）
        if (!empty($indexes)) {
            $result = $wpdb->query("ALTER TABLE $table_name DROP INDEX user_hash");
            if ($result === false && $wpdb->last_error) {
                return ['success' => false, 'message' => '删除旧索引失败: ' . $wpdb->last_error];
            }
        }
        
        // 重命名列
        $result = $wpdb->query("ALTER TABLE $table_name CHANGE COLUMN user_hash visitor_hash varchar(64) NOT NULL");
        if ($result === false) {
            return ['success' => false, 'message' => '重命名列失败: ' . $wpdb->last_error];
        }
        $operations[] = '重命名 user_hash -> visitor_hash';
        
        // 添加新索引
        $result = $wpdb->query("ALTER TABLE $table_name ADD UNIQUE KEY visitor_hash (visitor_hash)");
        if ($result === false && $wpdb->last_error && strpos($wpdb->last_error, 'Duplicate key') === false) {
            // 索引已存在不算错误
            return ['success' => false, 'message' => '添加索引失败: ' . $wpdb->last_error];
        }
    }
    
    // 情况2：有 last_seen (datetime) 但没有 last_active (int)，需要转换
    if ($has_last_seen && !$has_last_active) {
        // 先添加新列
        $result = $wpdb->query("ALTER TABLE $table_name 
            ADD COLUMN last_active int(11) unsigned NOT NULL DEFAULT 0 AFTER visitor_hash
        ");
        if ($result === false) {
            return ['success' => false, 'message' => '添加 last_active 列失败: ' . $wpdb->last_error];
        }
        
        // 迁移数据
        $result = $wpdb->query("UPDATE $table_name SET last_active = UNIX_TIMESTAMP(last_seen) WHERE last_active = 0");
        if ($result === false) {
            return ['success' => false, 'message' => '迁移 last_seen 数据失败: ' . $wpdb->last_error];
        }
        
        // 删除旧列
        $result = $wpdb->query("ALTER TABLE $table_name DROP COLUMN last_seen");
        if ($result === false) {
            return ['success' => false, 'message' => '删除 last_seen 列失败: ' . $wpdb->last_error];
        }
        $operations[] = '转换 last_seen (datetime) -> last_active (int)';
    }
    
    // 情况3：缺少 created_at 列
    if (!$has_created_at) {
        $after_column = $has_last_active ? 'last_active' : 'visitor_hash';
        $default_time = time();
        $result = $wpdb->query("ALTER TABLE $table_name 
            ADD COLUMN created_at int(11) unsigned NOT NULL DEFAULT $default_time AFTER $after_column
        ");
        if ($result === false) {
            return ['success' => false, 'message' => '添加 created_at 列失败: ' . $wpdb->last_error];
        }
        
        // 如果有 created_at (datetime)，迁移数据
        if (in_array('created_at', $wpdb->get_col("DESCRIBE $table_name"))) {
            $wpdb->query("UPDATE $table_name SET created_at = UNIX_TIMESTAMP(created_at) WHERE created_at < 1000000000");
        }
        $operations[] = '添加 created_at 列';
    }
    
    // 删除不需要的旧列
    $old_columns = ['user_ip', 'user_agent'];
    foreach ($old_columns as $old_col) {
        if (in_array($old_col, $wpdb->get_col("DESCRIBE $table_name"))) {
            $wpdb->query("ALTER TABLE $table_name DROP COLUMN $old_col");
            $operations[] = "删除旧列 $old_col";
        }
    }
    
    // 确保索引存在
    $indexes = $wpdb->get_col("SHOW INDEX FROM $table_name WHERE Key_name = 'visitor_hash'");
    if (empty($indexes)) {
        $result = $wpdb->query("ALTER TABLE $table_name ADD UNIQUE KEY visitor_hash (visitor_hash)");
        if ($result === false && strpos($wpdb->last_error, 'Duplicate key') === false) {
            return ['success' => false, 'message' => '添加索引失败: ' . $wpdb->last_error];
        }
    }
    
    // 确保其他索引存在
    $needed_indexes = [
        'user_id' => 'KEY user_id (user_id)',
        'last_active' => 'KEY last_active (last_active)'
    ];
    
    foreach ($needed_indexes as $index_name => $index_def) {
        $existing = $wpdb->get_col("SHOW INDEX FROM $table_name WHERE Key_name = '$index_name'");
        if (empty($existing)) {
            $wpdb->query("ALTER TABLE $table_name ADD $index_def");
        }
    }
    
    if (empty($operations)) {
        return ['success' => true, 'message' => '表结构已是最新版本，无需迁移'];
    }
    
    return ['success' => true, 'message' => '迁移完成: ' . implode(', ', $operations)];
}

/**
 * 创建管理员会话表
 */
function paper_wp_create_admin_sessions_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'paper_admin_sessions';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        session_start int(11) unsigned NOT NULL,
        last_update int(11) unsigned NOT NULL,
        total_seconds int(11) unsigned DEFAULT '0',
        PRIMARY KEY (id),
        UNIQUE KEY user_id (user_id),
        KEY session_start (session_start)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * 检查表是否存在
 */
function paper_wp_stats_table_exists($table_suffix) {
    global $wpdb;
    $table_name = $wpdb->prefix . $table_suffix;
    return $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) == $table_name;
}

/**
 * 初始化统计表
 */
function paper_wp_init_stats_tables() {
    static $initialized = false;
    if ($initialized) {
        return;
    }
    
    if (!paper_wp_stats_table_exists('paper_online_users')) {
        paper_wp_create_online_users_table();
    } else {
        // 表已存在，检查是否需要迁移
        $migration_result = paper_wp_migrate_online_users_table();
        // 只在开发模式下记录错误
        if (defined('WP_DEBUG') && WP_DEBUG && !$migration_result['success']) {
            error_log('Paper WP Stats: 表迁移失败 - ' . $migration_result['message']);
        }
    }
    
    if (!paper_wp_stats_table_exists('paper_admin_sessions')) {
        paper_wp_create_admin_sessions_table();
    }
    
    $initialized = true;
}

/**
 * 获取真实客户端IP（考虑代理和负载均衡）
 */
function paper_wp_get_real_client_ip() {
    // 优先级：HTTP_X_FORWARDED_FOR > HTTP_X_REAL_IP > REMOTE_ADDR
    // 但要注意安全，只取第一个IP（避免伪造）
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
        // 验证IP格式
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $ip;
        }
    }
    
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = trim($_SERVER['HTTP_X_REAL_IP']);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * 生成访客唯一标识
 * 在本地开发环境（localhost），需要添加更多差异化因素来区分不同浏览器
 */
function paper_wp_generate_visitor_hash($user_id, $ip, $user_agent) {
    if ($user_id > 0) {
        return 'u' . $user_id;
    }
    
    // 收集更多差异化的HTTP头信息
    $accept_lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    $accept_encoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
    $accept_charset = $_SERVER['HTTP_ACCEPT_CHARSET'] ?? '';
    $connection = $_SERVER['HTTP_CONNECTION'] ?? '';
    
    // 如果是本地开发环境（IP是localhost），添加更多标识
    $is_localhost = in_array($ip, ['127.0.0.1', '::1', 'localhost']);
    
    if ($is_localhost) {
        // 本地开发环境：使用Cookie中的唯一标识（如果存在）
        // 如果没有，将依赖更多的HTTP头来区分
        $cookie_id = $_COOKIE['paper_wp_visitor_id'] ?? '';
        if (empty($cookie_id)) {
            // 如果没有Cookie标识，使用更多HTTP头组合
            // 这样同一浏览器的不同标签页可能被识别为同一用户（这是合理的）
            // 不同浏览器的HTTP头组合通常不同
            $unique_string = $ip . '|' . $user_agent . '|' . 
                           $accept_lang . '|' . $accept_encoding . '|' . 
                           $accept_charset . '|' . $connection;
        } else {
            // 有Cookie标识，使用它（这样同一浏览器多次访问会被识别为同一用户）
            $unique_string = $ip . '|' . $user_agent . '|' . $cookie_id;
        }
    } else {
        // 生产环境：IP通常不同，使用IP+UserAgent足够区分
        $unique_string = $ip . '|' . $user_agent;
    }
    
    return 'g' . substr(hash('sha256', $unique_string), 0, 32); // 使用32位哈希足够唯一
}

/**
 * 更新用户在线状态
 */
function paper_wp_update_user_online_status($force = false) {
    global $wpdb;
    
    paper_wp_init_stats_tables();
    
    // 确保表存在
    if (!paper_wp_stats_table_exists('paper_online_users')) {
        // 如果表不存在，尝试创建
        paper_wp_create_online_users_table();
        if (!paper_wp_stats_table_exists('paper_online_users')) {
            return false;
        }
    }
    
    $user_id = (int) get_current_user_id();
    // 获取真实IP（考虑代理情况）
    $ip = paper_wp_get_real_client_ip();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // 如果是本地环境且没有Cookie标识，创建一个
    $is_localhost = in_array($ip, ['127.0.0.1', '::1', 'localhost']);
    if ($is_localhost && empty($_COOKIE['paper_wp_visitor_id']) && !headers_sent()) {
        // 生成唯一标识符并设置为Cookie
        $visitor_id = 'vid_' . bin2hex(random_bytes(8));
        setcookie('paper_wp_visitor_id', $visitor_id, time() + (365 * 24 * 60 * 60), '/', '', false, true); // 1年有效期
        $_COOKIE['paper_wp_visitor_id'] = $visitor_id; // 立即在当前请求中可用
    }
    
    $visitor_hash = paper_wp_generate_visitor_hash($user_id, $ip, $user_agent);
    $current_time = time();
    
    // 节流控制：30秒内只更新一次（除非强制更新）
    if (!$force) {
        $throttle_key = 'online_update_' . $visitor_hash;
        $last_update = wp_cache_get($throttle_key, 'paper_stats');
        
        if ($last_update && ($current_time - $last_update) < 30) {
            return true; // 在节流期内，但不算失败
        }
    }
    
    $table_name = $wpdb->prefix . 'paper_online_users';
    
    // 先检查记录是否存在
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name WHERE visitor_hash = %s LIMIT 1",
        $visitor_hash
    ));
    
    $result = false;
    
    if ($exists) {
        // 更新现有记录
        $result = $wpdb->update(
            $table_name,
            [
                'user_id' => $user_id,
                'last_active' => $current_time
            ],
            ['visitor_hash' => $visitor_hash],
            ['%d', '%d'],
            ['%s']
        );
    } else {
        // 插入新记录
        $result = $wpdb->insert(
            $table_name,
            [
                'user_id' => $user_id,
                'visitor_hash' => $visitor_hash,
                'last_active' => $current_time,
                'created_at' => $current_time
            ],
            ['%d', '%s', '%d', '%d']
        );
    }
    
    // 如果操作成功，更新缓存
    if ($result !== false) {
        $throttle_key = 'online_update_' . $visitor_hash;
        wp_cache_set($throttle_key, $current_time, 'paper_stats', 30);
        // 清除所有相关缓存，确保立即生效
        wp_cache_delete('online_count', 'paper_stats');
        wp_cache_delete('footer_stats_data', 'paper_wp_stats'); // 底部统计也会显示在线人数
        return true;
    }
    
    return false;
}

/**
 * 获取在线用户数量
 */
function paper_wp_get_online_users_count() {
    global $wpdb;
    
    $cache_key = 'online_count';
    $cached = wp_cache_get($cache_key, 'paper_stats');
    
    if ($cached !== false) {
        return (int) $cached;
    }
    
    paper_wp_init_stats_tables();
    
    // 确保表存在
    if (!paper_wp_stats_table_exists('paper_online_users')) {
        return 0;
    }
    
    $table_name = $wpdb->prefix . 'paper_online_users';
    $cutoff_time = time() - 900; // 15分钟前（注意：这是"有效期"，不是"等待时间"）
    
    // 确保查询时 last_active 是整数类型（兼容旧数据）
    $count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name 
         WHERE (CASE 
            WHEN last_active REGEXP '^[0-9]+$' THEN CAST(last_active AS UNSIGNED) >= %d
            ELSE UNIX_TIMESTAMP(last_active) >= %d
         END)",
        $cutoff_time,
        $cutoff_time
    ));
    
    // 如果查询失败，返回0
    if ($count === false) {
        $count = 0;
    }
    
    // 自动更新最大记录
    if ($count > 0) {
        paper_wp_update_max_online_if_needed($count);
    }
    
    // 缓存30秒（缩短缓存时间，让新访问者更快显示）
    wp_cache_set($cache_key, $count, 'paper_stats', 30);
    
    return $count;
}

/**
 * 更新最大在线人数记录（如果需要）
 */
function paper_wp_update_max_online_if_needed($current_count) {
    $max_record = get_option('paper_wp_max_online', [
        'count' => 0,
        'timestamp' => 0,
        'date' => ''
    ]);
    
    if ($current_count > $max_record['count']) {
        // 使用 time() 获取 UTC 时间戳，而不是 current_time()
        // current_time('timestamp') 会根据 WordPress 时区返回本地时间戳，可能造成时间偏移
        $timestamp = time();
        $new_record = [
            'count' => $current_count,
            'timestamp' => $timestamp,
            'date' => wp_date('Y-m-d H:i:s', $timestamp) // wp_date 会自动转换为本地时区显示
        ];
        
        update_option('paper_wp_max_online', $new_record);
        wp_cache_delete('max_online', 'paper_stats');
    }
}

/**
 * 获取最大在线人数记录
 */
function paper_wp_get_max_online_record() {
    $cache_key = 'max_online';
    $cached = wp_cache_get($cache_key, 'paper_stats');
    
    if ($cached !== false) {
        return $cached;
    }
    
    $record = get_option('paper_wp_max_online', [
        'count' => 0,
        'timestamp' => 0,
        'date' => ''
    ]);
    
    // 自动检测并修复未来时间戳
    if ($record['timestamp'] > 0 && $record['timestamp'] > (time() + 3600)) {
        // 如果时间戳是未来时间（超过1小时），尝试自动修复
        $timezone = wp_timezone();
        $timezone_offset = $timezone->getOffset(new DateTime());
        $corrected_timestamp = $record['timestamp'] - $timezone_offset;
        
        // 如果修正后仍然不合理，使用当前时间
        if ($corrected_timestamp > (time() + 3600) || $corrected_timestamp < (time() - 86400 * 365)) {
            $corrected_timestamp = time();
        }
        
        $record['timestamp'] = $corrected_timestamp;
        $record['date'] = wp_date('Y-m-d H:i:s', $corrected_timestamp);
        update_option('paper_wp_max_online', $record);
        wp_cache_delete('max_online', 'paper_stats');
    } elseif ($record['timestamp'] > 0 && !empty($record['date'])) {
        // 确保日期格式正确（从时间戳重新生成）
        $corrected_date = wp_date('Y-m-d H:i:s', $record['timestamp']);
        if ($corrected_date !== $record['date']) {
            $record['date'] = $corrected_date;
            update_option('paper_wp_max_online', $record);
        }
    }
    
    wp_cache_set($cache_key, $record, 'paper_stats', 3600);
    
    return $record;
}

/**
 * 修复最大在线人数记录的时间戳（如果有错误）
 */
function paper_wp_fix_max_online_timestamp() {
    $record = get_option('paper_wp_max_online', [
        'count' => 0,
        'timestamp' => 0,
        'date' => ''
    ]);
    
    if ($record['timestamp'] > 0) {
        $current_time = time();
        $old_timestamp = $record['timestamp']; // 保存旧时间戳
        
        // 如果时间戳是未来时间，修复它
        // 检查是否是明显的未来时间（超过当前时间1小时以上）
        if ($old_timestamp > ($current_time + 3600)) {
            // 可能是时区转换错误，尝试修正
            $timezone = wp_timezone();
            $timezone_offset = $timezone->getOffset(new DateTime());
            
            // 方法1：尝试减去时区偏移
            $corrected_timestamp = $old_timestamp - $timezone_offset;
            
            // 如果修正后仍然不合理（仍然是未来时间或太早），使用当前时间
            if ($corrected_timestamp > ($current_time + 3600) || $corrected_timestamp < ($current_time - 86400 * 365)) {
                // 直接使用当前时间作为最大在线人数的时间
                $corrected_timestamp = $current_time;
            }
            
            $record['timestamp'] = $corrected_timestamp;
            $record['date'] = wp_date('Y-m-d H:i:s', $corrected_timestamp);
            update_option('paper_wp_max_online', $record);
            wp_cache_delete('max_online', 'paper_stats');
            
            return ['fixed' => true, 'old_timestamp' => $old_timestamp, 'new_timestamp' => $corrected_timestamp, 'new_date' => $record['date']];
        }
    }
    
    return ['fixed' => false, 'message' => '时间戳检查正常，无需修复'];
}

/**
 * 更新管理员在线状态和在线时间
 */
function paper_wp_update_admin_online_status() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    global $wpdb;
    
    paper_wp_init_stats_tables();
    
    $user_id = (int) get_current_user_id();
    $current_time = time();
    
    $sessions_table = $wpdb->prefix . 'paper_admin_sessions';
    
    // 获取或创建会话
    $session = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $sessions_table WHERE user_id = %d",
        $user_id
    ));
    
    if (!$session) {
        // 新会话
        $wpdb->insert(
            $sessions_table,
            [
                'user_id' => $user_id,
                'session_start' => $current_time,
                'last_update' => $current_time,
                'total_seconds' => 0
            ],
            ['%d', '%d', '%d', '%d']
        );
        
        update_option('paper_wp_admin_last_active_' . $user_id, $current_time);
        wp_cache_delete('admin_online_status', 'paper_stats');
        return;
    }
    
    $last_update = (int) $session->last_update;
    $time_since_update = $current_time - $last_update;
    
    // 如果超过30分钟未活动，视为新会话
    if ($time_since_update > 1800) {
        // 保存当前会话时间到总时间
        $session_duration = min($time_since_update, 1800);
        $current_total = get_option('paper_wp_admin_total_seconds_' . $user_id, 0);
        update_option('paper_wp_admin_total_seconds_' . $user_id, $current_total + $session_duration);
        
        // 开始新会话
        $wpdb->update(
            $sessions_table,
            [
                'session_start' => $current_time,
                'last_update' => $current_time,
                'total_seconds' => 0
            ],
            ['user_id' => $user_id],
            ['%d', '%d', '%d'],
            ['%d']
        );
    } else {
        // 更新会话时间（限制单次最多30分钟）
        $increment = min($time_since_update, 1800);
        $new_total = (int) $session->total_seconds + $increment;
        
        // 如果累积时间超过10分钟，保存到总时间并重置
        if ($new_total >= 600) {
            $current_total = get_option('paper_wp_admin_total_seconds_' . $user_id, 0);
            update_option('paper_wp_admin_total_seconds_' . $user_id, $current_total + $new_total);
            $new_total = 0;
        }
        
        $wpdb->update(
            $sessions_table,
            [
                'last_update' => $current_time,
                'total_seconds' => $new_total
            ],
            ['user_id' => $user_id],
            ['%d', '%d'],
            ['%d']
        );
    }
    
    update_option('paper_wp_admin_last_active_' . $user_id, $current_time);
    wp_cache_delete('admin_online_status', 'paper_stats');
    wp_cache_delete('admin_total_time', 'paper_stats');
}

/**
 * 获取管理员在线状态
 */
function paper_wp_get_admin_online_status() {
    $cache_key = 'admin_online_status';
    $cached = wp_cache_get($cache_key, 'paper_stats');
    
    if ($cached !== false) {
        return $cached;
    }
    
    $admin_users = get_users(['role' => 'administrator', 'fields' => 'ID']);
    $current_time = time();
    $is_online = false;
    $latest_time = 0;
    
    foreach ($admin_users as $admin_id) {
        $last_active = (int) get_option('paper_wp_admin_last_active_' . $admin_id, 0);
        
        if ($last_active > 0) {
            $time_diff = $current_time - $last_active;
            
            if ($time_diff <= 300) { // 5分钟内算在线
                $is_online = true;
            }
            
            if ($last_active > $latest_time) {
                $latest_time = $last_active;
            }
        }
    }
    
    $result = [
        'is_online' => $is_online,
        'last_online' => $latest_time
    ];
    
    wp_cache_set($cache_key, $result, 'paper_stats', 60);
    
    return $result;
}

/**
 * 获取管理员总在线时间（格式化）
 */
function paper_wp_get_admin_total_online_time() {
    $cache_key = 'admin_total_time';
    $cached = wp_cache_get($cache_key, 'paper_stats');
    
    if ($cached !== false) {
        return $cached;
    }
    
    global $wpdb;
    
    paper_wp_init_stats_tables();
    
    $admin_users = get_users(['role' => 'administrator', 'fields' => 'ID']);
    $total_seconds = 0;
    $sessions_table = $wpdb->prefix . 'paper_admin_sessions';
    $current_time = time();
    
    foreach ($admin_users as $admin_id) {
        // 从option获取已保存的总时间
        $saved_total = (int) get_option('paper_wp_admin_total_seconds_' . $admin_id, 0);
        
        // 从会话表获取当前会话信息
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT total_seconds, last_update FROM $sessions_table WHERE user_id = %d",
            $admin_id
        ));
        
        $session_seconds = 0;
        
        if ($session) {
            // 会话表中已累积的时间（还未保存到option）
            $session_seconds = (int) $session->total_seconds;
            
            // 计算从上次更新到现在的时间增量
            $last_update = (int) $session->last_update;
            
            // 确保时间有效且不超过30分钟
            if ($last_update > 0 && $last_update <= $current_time) {
                $time_diff = $current_time - $last_update;
                
                // 如果距离上次更新在30分钟内，说明会话仍在继续
                if ($time_diff > 0 && $time_diff <= 1800) {
                    $session_seconds += $time_diff;
                }
            }
        } else {
            // 没有会话记录，但有最后活跃时间，说明可能是刚登录或会话异常
            $last_active = (int) get_option('paper_wp_admin_last_active_' . $admin_id, 0);
            
            if ($last_active > 0 && $last_active <= $current_time) {
                $time_diff = $current_time - $last_active;
                
                // 如果在5分钟内活跃过，计算这段时间（最多不超过5分钟）
                if ($time_diff > 0 && $time_diff <= 300) {
                    $session_seconds = $time_diff;
                }
            }
        }
        
        $total_seconds += $saved_total + $session_seconds;
    }
    
    // 确保总时间不为负数
    $total_seconds = max(0, $total_seconds);
    
    // 格式化显示
    $formatted = paper_wp_format_seconds($total_seconds);
    
    wp_cache_set($cache_key, $formatted, 'paper_stats', 300);
    
    return $formatted;
}

/**
 * 强制刷新在线人数（用于调试和修复）
 */
function paper_wp_refresh_online_count() {
    // 清除所有相关缓存
    wp_cache_delete('online_count', 'paper_stats');
    wp_cache_delete('footer_stats_data', 'paper_wp_stats');
    
    // 强制重新计算
    return paper_wp_get_online_users_count();
}

/**
 * 修复异常的管理员在线时间数据
 */
function paper_wp_fix_admin_online_time_data() {
    global $wpdb;
    
    paper_wp_init_stats_tables();
    
    $admin_users = get_users(['role' => 'administrator', 'fields' => 'ID']);
    $sessions_table = $wpdb->prefix . 'paper_admin_sessions';
    $current_time = time();
    
    foreach ($admin_users as $admin_id) {
        // 检查并修复 option 中的总时间（不能为负数）
        $saved_total = (int) get_option('paper_wp_admin_total_seconds_' . $admin_id, 0);
        if ($saved_total < 0) {
            update_option('paper_wp_admin_total_seconds_' . $admin_id, 0);
        }
        
        // 检查并修复会话表中的异常数据
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $sessions_table WHERE user_id = %d",
            $admin_id
        ));
        
        if ($session) {
            $last_update = (int) $session->last_update;
            $total_seconds = (int) $session->total_seconds;
            
            // 如果 last_update 异常（未来时间或太久之前），重置会话
            if ($last_update > $current_time || ($current_time - $last_update) > 86400) {
                $wpdb->update(
                    $sessions_table,
                    [
                        'session_start' => $current_time,
                        'last_update' => $current_time,
                        'total_seconds' => 0
                    ],
                    ['user_id' => $admin_id],
                    ['%d', '%d', '%d'],
                    ['%d']
                );
            }
            
            // 如果 total_seconds 为负数，重置为0
            if ($total_seconds < 0) {
                $wpdb->update(
                    $sessions_table,
                    ['total_seconds' => 0],
                    ['user_id' => $admin_id],
                    ['%d'],
                    ['%d']
                );
            }
        }
    }
    
    // 清除缓存
    wp_cache_delete('admin_total_time', 'paper_stats');
}

/**
 * 格式化秒数为可读时间
 */
function paper_wp_format_seconds($seconds) {
    $seconds = max(0, (int) $seconds); // 确保不为负数
    
    if ($seconds >= 86400) {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        return $days . '天' . ($hours > 0 ? $hours . '小时' : '');
    } elseif ($seconds >= 3600) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return $hours . '小时' . ($minutes > 0 ? $minutes . '分钟' : '');
    } elseif ($seconds >= 60) {
        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;
        return $minutes . '分钟' . ($secs > 0 ? $secs . '秒' : '');
    } else {
        return $seconds . '秒';
    }
}

/**
 * 获取博客总字数（优化版：改进正则表达式性能）
 */
function paper_wp_get_total_word_count() {
    global $wpdb;
    $cache_key = 'total_words';
    $group = 'stats';

    $word_count_callback = function() use ($wpdb) {
        $word_count = 0;
        $batch_size = 100;
        $offset = 0;
        
        // 预编译正则表达式以提升性能
        $chinese_pattern = '/[\x{4e00}-\x{9fa5}]/u';
        $english_pattern = "/[a-zA-Z0-9'-]+/";

        while (true) {
            $posts = $wpdb->get_col($wpdb->prepare(
                "SELECT post_content FROM {$wpdb->posts}
                 WHERE post_type = 'post' AND post_status = 'publish'
                 LIMIT %d OFFSET %d",
                $batch_size,
                $offset
            ));

            if (empty($posts)) {
                break;
            }

            foreach ($posts as $content) {
                if (empty($content)) {
                    continue; // 跳过空内容
                }
                
                $content = strip_shortcodes($content);
                $content = wp_strip_all_tags($content);

                if (empty($content)) {
                    continue; // 跳过清理后为空的内容
                }

                // 中文字符统计（使用preg_match_all的count参数）
                preg_match_all($chinese_pattern, $content, $chinese_matches);
                $chinese_words = count($chinese_matches[0]);

                // 英文单词统计
                preg_match_all($english_pattern, $content, $english_matches);
                $english_words = count($english_matches[0]);

                $word_count += $chinese_words + $english_words;
            }

            $offset += $batch_size;
        }

        return $word_count;
    };

    return paper_wp_cache_get($cache_key, $group, $word_count_callback, []);
}

/**
 * 获取底部统计数据（优化版：添加完整缓存）
 */
function paper_wp_get_footer_stats_data() {
    // 使用缓存系统缓存整个footer数据，60秒刷新（在线人数需要更新）
    $cache_key = 'footer_stats_data';
    $cached_data = wp_cache_get($cache_key, 'paper_wp_stats');
    
    if (false !== $cached_data) {
        return $cached_data;
    }

    static $footer_settings = null;
    if ($footer_settings === null) {
        $footer_settings = get_option('paper_wp_footer_stats_settings', []);
    }

    // 计算博客运行时间
    $install_timestamp = get_option('install_date');
    if ($install_timestamp) {
        $blog_start_date = wp_date('Y-m-d H:i:s', $install_timestamp);
    } else {
        $first_post = get_posts(['numberposts' => 1, 'orderby' => 'date', 'order' => 'ASC', 'post_type' => 'post', 'post_status' => 'publish']);
        $blog_start_date = $first_post ? $first_post[0]->post_date : wp_date('Y-m-d H:i:s');
    }

    $timezone = wp_timezone();
    $start_datetime = new DateTime($blog_start_date, $timezone);
    $current_datetime = new DateTime('now', $timezone);
    $interval = $start_datetime->diff($current_datetime);

    if ($interval->y > 0) {
        $running_time = $interval->y . '年' . ($interval->m > 0 ? $interval->m . '个月' : '');
    } elseif ($interval->m > 0) {
        $running_time = $interval->m . '个月' . ($interval->d > 0 ? $interval->d . '天' : '');
    } elseif ($interval->d > 0) {
        $running_time = $interval->d . '天';
    } elseif ($interval->h > 0) {
        $running_time = $interval->h . '小时';
    } elseif ($interval->i > 0) {
        $running_time = $interval->i . '分钟';
    } else {
        $running_time = '刚刚创建';
    }

    $total_posts = wp_count_posts()->publish;
    $total_words_wan = number_format(paper_wp_get_total_word_count() / 10000, 2);

    // 获取管理员在线状态
    $admin_online_status = paper_wp_get_admin_online_status();
    if ($admin_online_status['is_online']) {
        $online_text = '在线中';
    } else {
        $last_online = $admin_online_status['last_online'];
        
        // 如果 last_online 为0，说明从未记录或已清除，显示"从未在线"
        if ($last_online <= 0) {
            $online_text = '从未在线';
        } else {
            $last_online_datetime = new DateTime('@' . $last_online);
            $last_online_datetime->setTimezone($timezone);
            $interval = $current_datetime->diff($last_online_datetime);
            
            // 如果时间差小于1秒，显示"刚刚离线"
            if ($interval->days == 0 && $interval->h == 0 && $interval->i == 0 && $interval->s < 1) {
                $online_text = '刚刚离线';
            } elseif ($interval->days > 0) {
                $online_text = $interval->days . '天之前在线';
            } elseif ($interval->h >= 1) {
                $online_text = $interval->h . '小时之前在线';
            } else {
                $parts = [];
                if ($interval->i > 0) $parts[] = $interval->i . '分钟';
                if ($interval->s > 0 || empty($parts)) $parts[] = $interval->s . '秒';
                $online_text = implode('', $parts) . '之前在线';
            }
        }
    }

    $powered_by_text = $footer_settings['powered_by_text'] ?? '2024-2025';
    $stats_text_template = $footer_settings['stats_text'] ?? '在 {years}内发布{posts}篇文章，持续输出 {words} 万字';
    $online_text_template = $footer_settings['online_text'] ?? '{time}';

    $stats_text = str_replace(['{years}', '{posts}', '{words}'], [$running_time, number_format($total_posts), $total_words_wan], $stats_text_template);
    $final_online_text = str_replace('{time}', $online_text, $online_text_template);

    // 获取在线用户数量
    $online_users_count = paper_wp_get_online_users_count();

    // 获取历史最大记录
    $max_online_record = paper_wp_get_max_online_record();
    $max_online_count = $max_online_record['count'] ?? 0;
    $days_since_max = !empty($max_online_record['date']) ? $max_online_record['date'] : '从未';

    $admin_total_online_time = paper_wp_get_admin_total_online_time();

    $data = [
        'final_online_text' => $final_online_text,
        'stats_text' => $stats_text,
        'admin_total_online_time' => $admin_total_online_time,
        'online_users_count' => $online_users_count,
        'max_online_count' => $max_online_count,
        'days_since_max' => $days_since_max,
        'powered_by_text' => $powered_by_text,
    ];
    
    // 缓存30秒（缩短缓存时间，让在线人数更新更快）
    wp_cache_set($cache_key, $data, 'paper_wp_stats', 30);
    
    return $data;
}

/**
 * 渲染底部统计信息
 */
function paper_wp_render_footer_stats() {
    $data = paper_wp_get_footer_stats_data();

    ?>
    <a href="<?php echo home_url(); ?>">&copy;<?php bloginfo('name'); ?></a><?php echo esc_html($data['final_online_text']); ?>，
    总共 <?php echo esc_html($data['admin_total_online_time']); ?>
    <br>现在共有 <?php echo intval($data['online_users_count']); ?> 人在线<?php if ($data['max_online_count'] > 0): ?>， 最多 <?php echo intval($data['max_online_count']); ?> 人，发生在<?php echo esc_html($data['days_since_max']); ?><?php endif; ?>
    <br><a target="_blank" rel="noopener noreferrer" href="#"><?php echo esc_html($data['powered_by_text']); ?></a>
    <?php
}

/**
 * 清理过期的在线用户记录
 */
function paper_wp_cleanup_online_users_table() {
    global $wpdb;
    
    if (!paper_wp_stats_table_exists('paper_online_users')) {
        return;
    }
    
    $table_name = $wpdb->prefix . 'paper_online_users';
    $cutoff_time = time() - 3600; // 1小时前
    
    // 批量删除过期记录
    $batch_size = 500;
    $total_deleted = 0;
    
    while (true) {
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE last_active < %d LIMIT %d",
            $cutoff_time,
            $batch_size
        ));
        
        if ($deleted === false || $deleted === 0) {
            break;
        }
        
        $total_deleted += $deleted;
        
        // 限制单次最多删除5000条
        if ($total_deleted >= 5000 || $deleted < $batch_size) {
            break;
        }
    }
    
    if ($total_deleted > 0) {
        wp_cache_delete('online_count', 'paper_stats');
    }
}

/**
 * 清理字数缓存
 */
function paper_wp_clear_word_count_cache() {
    paper_wp_cache_delete('total_words', 'stats');
}

/**
 * 清理统计相关缓存
 */
function paper_wp_clear_stats_cache() {
    // 使用缓存系统的flush_group来清理整个stats组
    if (function_exists('paper_wp_cache_flush_group')) {
        paper_wp_cache_flush_group('stats');
    }
    
    // 清理WordPress对象缓存
    wp_cache_delete('footer_stats_data', 'paper_wp_stats');
    wp_cache_delete('online_count', 'paper_stats');
    wp_cache_delete('max_online', 'paper_stats');
    wp_cache_delete('admin_online_status', 'paper_stats');
    wp_cache_delete('admin_total_time', 'paper_stats');
    
    // 清理字数缓存
    paper_wp_clear_word_count_cache();
}

/**
 * 处理管理员登录
 */
function paper_wp_handle_admin_login($user_login, $user) {
    if (!user_can($user, 'manage_options')) {
        return;
    }
    
    global $wpdb;
    
    paper_wp_init_stats_tables();
    
    $user_id = (int) $user->ID;
    $current_time = time();
    
    // 删除可能存在的游客记录（如果之前是游客访问）
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $visitor_hash = paper_wp_generate_visitor_hash(0, $ip, $user_agent);
    
    $online_table = $wpdb->prefix . 'paper_online_users';
    $wpdb->delete($online_table, ['visitor_hash' => $visitor_hash], ['%s']);
    
    // 初始化管理员会话
    update_option('paper_wp_admin_last_active_' . $user_id, $current_time);
    wp_cache_delete('admin_online_status', 'paper_stats');
}

/**
 * 处理管理员登出
 */
function paper_wp_handle_admin_logout($user_id) {
    if (!user_can($user_id, 'manage_options')) {
        return;
    }
    
    global $wpdb;
    
    paper_wp_init_stats_tables();
    
    $sessions_table = $wpdb->prefix . 'paper_admin_sessions';
    $current_time = time();
    
    // 获取当前会话
    $session = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $sessions_table WHERE user_id = %d",
        $user_id
    ));
    
    if ($session) {
        // 计算并保存最终会话时间
        $last_update = (int) $session->last_update;
        $time_since_update = $current_time - $last_update;
        
        if ($time_since_update > 0 && $time_since_update <= 1800) {
            $final_increment = min($time_since_update, 1800);
            $session_seconds = (int) $session->total_seconds + $final_increment;
            
            // 保存到总时间
            $current_total = get_option('paper_wp_admin_total_seconds_' . $user_id, 0);
            update_option('paper_wp_admin_total_seconds_' . $user_id, $current_total + $session_seconds);
        } elseif ($session->total_seconds > 0) {
            // 保存已累积的会话时间
            $current_total = get_option('paper_wp_admin_total_seconds_' . $user_id, 0);
            update_option('paper_wp_admin_total_seconds_' . $user_id, $current_total + $session->total_seconds);
        }
        
        // 删除会话记录
        $wpdb->delete($sessions_table, ['user_id' => $user_id], ['%d']);
    }
    
    // 更新最后活跃时间为退出时间（用于显示"X分钟前离线"）
    update_option('paper_wp_admin_last_active_' . $user_id, $current_time);
    
    // 清理缓存
    wp_cache_delete('admin_online_status', 'paper_stats');
    wp_cache_delete('admin_total_time', 'paper_stats');
    wp_cache_delete('footer_stats_data', 'paper_wp_stats');
}

// ============================================================================
// 注册钩子
// ============================================================================

// 更新用户在线状态
add_action('wp', 'paper_wp_update_user_online_status');

// 更新管理员在线状态
add_action('wp', 'paper_wp_update_admin_online_status');
add_action('admin_init', 'paper_wp_update_admin_online_status');

// 管理员登录/登出处理
add_action('wp_login', 'paper_wp_handle_admin_login', 10, 2);
add_action('wp_logout', 'paper_wp_handle_admin_logout');

// 文章变化时清理缓存
add_action('save_post', 'paper_wp_clear_word_count_cache');
add_action('delete_post', 'paper_wp_clear_word_count_cache');
add_action('publish_post', 'paper_wp_clear_word_count_cache');
add_action('trash_post', 'paper_wp_clear_word_count_cache');

// 定时任务 - 清理在线用户表
add_action('paper_wp_cleanup_online_users_event', 'paper_wp_cleanup_online_users_table');

if (!wp_next_scheduled('paper_wp_cleanup_online_users_event')) {
    wp_schedule_event(time(), 'hourly', 'paper_wp_cleanup_online_users_event');
}

add_action('switch_theme', function() {
    wp_clear_scheduled_hook('paper_wp_cleanup_online_users_event');
});

// 在模板加载后确保更新在线状态（优先级较高，尽早执行）
add_action('template_redirect', function() {
    // 立即更新在线状态，不等待页面加载完成
    paper_wp_update_user_online_status();
    // 同时清除缓存，确保当前页面的在线人数是最新的
    wp_cache_delete('online_count', 'paper_stats');
    wp_cache_delete('footer_stats_data', 'paper_wp_stats');
}, 5);

// 主题激活时初始化表并修复数据
add_action('after_setup_theme', function() {
    paper_wp_init_stats_tables();
    // 修复可能存在的异常数据
    paper_wp_fix_admin_online_time_data();
    // 清除在线人数缓存，强制重新计算
    wp_cache_delete('online_count', 'paper_stats');
}, 1);

// AJAX处理
function paper_wp_ajax_update_online_status() {
    if (!check_ajax_referer('paper_wp_online_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => '安全验证失败']);
        return;
    }

    paper_wp_update_user_online_status();
    wp_send_json_success(['message' => '在线状态已更新']);
}
add_action('wp_ajax_paper_wp_update_online_status', 'paper_wp_ajax_update_online_status');
add_action('wp_ajax_nopriv_paper_wp_update_online_status', 'paper_wp_ajax_update_online_status');

function paper_wp_ajax_get_online_status() {
    if (!check_ajax_referer('paper_wp_online_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => '安全验证失败']);
        return;
    }

    $online_status = paper_wp_get_admin_online_status();
    wp_send_json_success($online_status);
}
add_action('wp_ajax_paper_wp_get_online_status', 'paper_wp_ajax_get_online_status');
add_action('wp_ajax_nopriv_paper_wp_get_online_status', 'paper_wp_ajax_get_online_status');

/**
 * 在线统计测试页面
 */
function paper_wp_stats_test_page() {
    if (!current_user_can('manage_options')) {
        wp_die('权限不足');
    }
    
    global $wpdb;
    
    // 处理操作
    $action = $_GET['action'] ?? '';
    $message = '';
    
    // 处理POST请求（手动更新）
    if (isset($_POST['force_update']) && isset($_POST['update_nonce'])) {
        if (wp_verify_nonce($_POST['update_nonce'], 'force_update_online_status')) {
            $update_result = paper_wp_update_user_online_status(true);
            if ($update_result) {
                $message = '<div class="notice notice-success"><p>在线状态已强制更新成功！</p></div>';
                // 刷新页面数据
                wp_redirect(add_query_arg(['updated' => '1'], admin_url('admin.php?page=paper-wp-stats-test')));
                exit;
            } else {
                $error_msg = $wpdb->last_error ?: '更新失败，请检查数据库';
                $message = '<div class="notice notice-error"><p>更新失败：' . esc_html($error_msg) . '</p></div>';
            }
        }
    }
    
    if ($action === 'refresh') {
        wp_cache_delete('online_count', 'paper_stats');
        wp_cache_delete('footer_stats_data', 'paper_wp_stats');
        $result = paper_wp_refresh_online_count();
        $message = '<div class="notice notice-success"><p>缓存已清除，在线人数已刷新：' . $result . ' 人</p></div>';
    } elseif ($action === 'fix') {
        paper_wp_init_stats_tables();
        // 强制迁移表结构
        $migration_result = paper_wp_migrate_online_users_table();
        if ($migration_result['success']) {
            $message = '<div class="notice notice-success"><p>数据表已初始化并迁移完成：' . esc_html($migration_result['message']) . '</p></div>';
        } else {
            $message = '<div class="notice notice-error"><p>迁移失败：' . esc_html($migration_result['message']) . '</p></div>';
        }
    } elseif ($action === 'migrate') {
        // 单独执行迁移
        $migration_result = paper_wp_migrate_online_users_table();
        if ($migration_result['success']) {
            $message = '<div class="notice notice-success"><p>迁移成功：' . esc_html($migration_result['message']) . '</p></div>';
        } else {
            $error_details = $wpdb->last_error ? ' | 数据库错误: ' . $wpdb->last_error : '';
            $message = '<div class="notice notice-error"><p>迁移失败：' . esc_html($migration_result['message']) . $error_details . '</p></div>';
        }
    } elseif ($action === 'fix_time') {
        // 修复时间戳错误
        $fix_result = paper_wp_fix_max_online_timestamp();
        if ($fix_result['fixed']) {
            $old_date = wp_date('Y-m-d H:i:s', $fix_result['old_timestamp']);
            $message = '<div class="notice notice-success"><p>时间戳已修复！<br>旧时间：' . esc_html($old_date) . '<br>新时间：' . esc_html($fix_result['new_date']) . '</p></div>';
            // 清除缓存确保立即生效
            wp_cache_delete('max_online', 'paper_stats');
            wp_cache_delete('footer_stats_data', 'paper_wp_stats');
        } else {
            $message = '<div class="notice notice-info"><p>' . esc_html($fix_result['message']) . '</p></div>';
        }
    } elseif ($action === 'update') {
        $update_result = paper_wp_update_user_online_status(true); // 强制更新
        if ($update_result) {
            $message = '<div class="notice notice-success"><p>在线状态已更新成功</p></div>';
        } else {
            $error_msg = $wpdb->last_error ?: '未知错误';
            $message = '<div class="notice notice-error"><p>更新失败：' . esc_html($error_msg) . '</p></div>';
        }
    } elseif ($action === 'fix_timestamps') {
        // 批量修复所有无效时间戳
        if ($online_table_exists) {
            $fixed_count = 0;
            $current_time = time();
            
            $all_records = $wpdb->get_results("SELECT id, last_active, created_at FROM $online_table", ARRAY_A);
            
            foreach ($all_records as $record) {
                $needs_fix = false;
                $new_last_active = $record['last_active'];
                $new_created_at = $record['created_at'];
                
                // 修复 last_active
                if (!is_numeric($record['last_active']) || $record['last_active'] <= 0) {
                    $new_last_active = $current_time;
                    $needs_fix = true;
                } else {
                    $new_last_active = (int)$record['last_active'];
                }
                
                // 修复 created_at
                if (!is_numeric($record['created_at']) || $record['created_at'] <= 0) {
                    $new_created_at = $new_last_active > 0 ? $new_last_active : $current_time;
                    $needs_fix = true;
                } else {
                    $new_created_at = (int)$record['created_at'];
                }
                
                if ($needs_fix) {
                    $wpdb->update(
                        $online_table,
                        [
                            'last_active' => $new_last_active,
                            'created_at' => $new_created_at
                        ],
                        ['id' => $record['id']],
                        ['%d', '%d'],
                        ['%d']
                    );
                    $fixed_count++;
                }
            }
            
            wp_cache_delete('online_count', 'paper_stats');
            $message = '<div class="notice notice-success"><p>已修复 ' . $fixed_count . ' 条记录的时间戳</p></div>';
        }
    } elseif ($action === 'clear_data') {
        // 清空所有在线用户数据
        if ($online_table_exists) {
            $deleted = $wpdb->query("TRUNCATE TABLE $online_table");
            if ($deleted !== false) {
                wp_cache_delete('online_count', 'paper_stats');
                wp_cache_delete('footer_stats_data', 'paper_wp_stats');
                // 同时重置最大在线记录
                update_option('paper_wp_max_online', ['count' => 0, 'timestamp' => 0, 'date' => '']);
                wp_cache_delete('max_online', 'paper_stats');
                $message = '<div class="notice notice-success"><p>所有在线用户数据已清空！包括最大在线人数记录。</p></div>';
            } else {
                $message = '<div class="notice notice-error"><p>清空失败：' . esc_html($wpdb->last_error) . '</p></div>';
            }
        }
    }
    
    // 检查表是否存在
    $online_table = $wpdb->prefix . 'paper_online_users';
    $sessions_table = $wpdb->prefix . 'paper_admin_sessions';
    $online_table_exists = paper_wp_stats_table_exists('paper_online_users');
    $sessions_table_exists = paper_wp_stats_table_exists('paper_admin_sessions');
    
    // 获取在线人数
    $online_count = paper_wp_get_online_users_count();
    
    // 获取当前用户信息（使用和更新函数相同的逻辑）
    $current_user_id = get_current_user_id();
    $current_ip = paper_wp_get_real_client_ip(); // 使用统一的IP获取函数
    $current_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $current_hash = paper_wp_generate_visitor_hash($current_user_id, $current_ip, $current_user_agent);
    
    // 检查当前用户是否在在线表中
    $user_record = null;
    if ($online_table_exists) {
        $user_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $online_table WHERE visitor_hash = %s",
            $current_hash
        ));
    }
    
    // 如果用户不在表中且表存在，尝试立即更新（用于测试）
    if (!$user_record && $online_table_exists && empty($action)) {
        // 静默尝试一次更新，但不显示消息
        paper_wp_update_user_online_status(true);
        // 重新查询
        $user_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $online_table WHERE visitor_hash = %s",
            $current_hash
        ));
    }
    
    // 修复用户记录中的无效时间戳（如果存在）
    if ($user_record) {
        // 检查并修复 last_active
        if (!is_numeric($user_record->last_active) || $user_record->last_active <= 0) {
            $user_record->last_active = time();
            $wpdb->update(
                $online_table,
                ['last_active' => $user_record->last_active],
                ['id' => $user_record->id],
                ['%d'],
                ['%d']
            );
        }
        // 检查并修复 created_at
        if (!is_numeric($user_record->created_at) || $user_record->created_at <= 0) {
            $user_record->created_at = $user_record->last_active; // 使用 last_active 作为默认值
            $wpdb->update(
                $online_table,
                ['created_at' => $user_record->created_at],
                ['id' => $user_record->id],
                ['%d'],
                ['%d']
            );
        }
    }
    
    // 获取数据库错误信息（如果有）
    $db_error = $wpdb->last_error;
    $db_query = $wpdb->last_query;
    
    // 检查当前表结构
    $table_structure = [];
    $table_columns = [];
    if ($online_table_exists) {
        $table_structure = $wpdb->get_results("DESCRIBE $online_table", ARRAY_A);
        if (!empty($table_structure)) {
            $table_columns = array_column($table_structure, 'Field');
        }
    }
    
    // 获取数据库中的在线用户记录
    $all_records = [];
    if ($online_table_exists) {
        $all_records = $wpdb->get_results(
            "SELECT * FROM $online_table ORDER BY last_active DESC LIMIT 20",
            ARRAY_A
        );
        
        // 修复记录中的无效时间戳（批量修复）
        foreach ($all_records as &$record) {
            $needs_fix = false;
            
            // 修复 last_active
            if (!is_numeric($record['last_active']) || $record['last_active'] <= 0) {
                $record['last_active'] = time();
                $needs_fix = true;
            }
            
            // 修复 created_at
            if (!is_numeric($record['created_at']) || $record['created_at'] <= 0) {
                $record['created_at'] = $record['last_active'];
                $needs_fix = true;
            }
            
            // 如果有修复，更新数据库
            if ($needs_fix) {
                $wpdb->update(
                    $online_table,
                    [
                        'last_active' => (int)$record['last_active'],
                        'created_at' => (int)$record['created_at']
                    ],
                    ['id' => $record['id']],
                    ['%d', '%d'],
                    ['%d']
                );
            }
        }
        unset($record); // 解除引用
    }
    
    // 获取缓存信息
    $cache_info = [
        'online_count' => wp_cache_get('online_count', 'paper_stats'),
        'throttle' => wp_cache_get('online_update_' . $current_hash, 'paper_stats'),
    ];
    
    ?>
    <div class="wrap">
        <h1>在线统计测试页面</h1>
        <p style="color: #666; font-size: 13px;">页面版本：v2.0 | 最后更新：<?php echo date('Y-m-d H:i:s'); ?></p>
        
        <?php echo $message; ?>
        
        <div class="card" style="max-width: 1200px; margin-top: 20px;">
            <h2>快速操作</h2>
            <p>
                <a href="?page=paper-wp-stats-test&action=update" class="button">更新我的在线状态</a>
                <a href="?page=paper-wp-stats-test&action=refresh" class="button">清除缓存并刷新</a>
                <a href="?page=paper-wp-stats-test&action=clear_data" class="button" style="background: #dc3232; border-color: #dc3232; color: #fff;" onclick="return confirm('确定要清空所有在线用户数据吗？此操作不可恢复！');">🗑️ 清空所有数据（危险操作）</a>
                <a href="?page=paper-wp-stats-test&action=migrate" class="button button-primary" style="background: #d63638; border-color: #d63638;">🔧 迁移表结构（修复错误）</a>
                <a href="?page=paper-wp-stats-test&action=fix_time" class="button" style="background: #f0b849; border-color: #f0b849;">⏰ 修复最大在线时间戳</a>
                <a href="?page=paper-wp-stats-test&action=fix_timestamps" class="button" style="background: #00a0d2; border-color: #00a0d2;">🔧 批量修复无效时间戳</a>
                <a href="?page=paper-wp-stats-test&action=fix" class="button">初始化数据表</a>
            </p>
        </div>
        
        <div class="card" style="max-width: 1200px; margin-top: 20px;">
            <h2>系统检查</h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>检查项</th>
                        <th>状态</th>
                        <th>说明</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>在线用户表</td>
                        <td><?php echo $online_table_exists ? '<span style="color: green;">✓ 存在</span>' : '<span style="color: red;">✗ 不存在</span>'; ?></td>
                        <td><?php echo $online_table_exists ? '表名: ' . $online_table : '需要初始化数据表'; ?></td>
                    </tr>
                    <tr>
                        <td>管理员会话表</td>
                        <td><?php echo $sessions_table_exists ? '<span style="color: green;">✓ 存在</span>' : '<span style="color: red;">✗ 不存在</span>'; ?></td>
                        <td><?php echo $sessions_table_exists ? '表名: ' . $sessions_table : '需要初始化数据表'; ?></td>
                    </tr>
                    <tr>
                        <td>当前在线人数</td>
                        <td><strong><?php echo $online_count; ?></strong></td>
                        <td>
                            15分钟内有活动的用户数
                            <br><small style="color: #666;">
                                注意：用户访问后<strong>立即显示为在线</strong>，不需要等待。
                                <br>"15分钟"是指有效期，即用户如果15分钟内没有新活动会自动离线。
                            </small>
                        </td>
                    </tr>
                    <tr>
                        <td>数据库写入权限</td>
                        <td>
                            <?php
                            if ($online_table_exists) {
                                $test_result = $wpdb->query("SELECT 1 FROM $online_table LIMIT 1");
                                if ($test_result !== false) {
                                    echo '<span style="color: green;">✓ 正常</span>';
                                } else {
                                    echo '<span style="color: red;">✗ 异常</span>';
                                    if ($wpdb->last_error) {
                                        echo '<br><small style="color: red;">错误：' . esc_html($wpdb->last_error) . '</small>';
                                    }
                                }
                            } else {
                                echo '<span style="color: orange;">- 未检查</span>';
                            }
                            ?>
                        </td>
                        <td>能够读取/写入数据库表</td>
                    </tr>
                    <?php if ($db_error) : ?>
                    <tr>
                        <td>数据库错误</td>
                        <td colspan="2"><span style="color: red;"><?php echo esc_html($db_error); ?></span></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td>表结构检查</td>
                        <td>
                            <?php
                            if ($online_table_exists && !empty($table_columns)) {
                                $has_visitor_hash = in_array('visitor_hash', $table_columns);
                                $has_user_hash = in_array('user_hash', $table_columns);
                                $has_last_active = in_array('last_active', $table_columns);
                                $has_created_at = in_array('created_at', $table_columns);
                                $has_last_seen = in_array('last_seen', $table_columns);
                                
                                $issues = [];
                                if ($has_user_hash && !$has_visitor_hash) {
                                    $issues[] = '存在旧列: user_hash';
                                }
                                if (!$has_visitor_hash) {
                                    $issues[] = '缺少: visitor_hash';
                                }
                                if (!$has_last_active) {
                                    $issues[] = '缺少: last_active';
                                }
                                if (!$has_created_at) {
                                    $issues[] = '缺少: created_at';
                                }
                                if ($has_last_seen) {
                                    $issues[] = '存在旧列: last_seen (应删除)';
                                }
                                
                                if (empty($issues)) {
                                    echo '<span style="color: green;">✓ 结构正确</span>';
                                    echo '<br><small style="color: #666;">列: ' . implode(', ', $table_columns) . '</small>';
                                } else {
                                    echo '<span style="color: red;">✗ 需要修复</span>';
                                    echo '<br><small style="color: red;">' . implode('; ', $issues) . '</small>';
                                }
                            } else {
                                echo '<span style="color: orange;">- 无法检查</span>';
                                if (!$online_table_exists) {
                                    echo '<br><small>表不存在</small>';
                                } else {
                                    echo '<br><small>无法获取表结构</small>';
                                }
                            }
                            ?>
                        </td>
                        <td>检查表结构是否符合新版本要求<br>需要列: visitor_hash, last_active, created_at</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="card" style="max-width: 1200px; margin-top: 20px;">
            <h2>当前用户信息</h2>
            <table class="widefat">
                <tbody>
                    <tr>
                        <th>用户ID</th>
                        <td><?php echo $current_user_id; ?> <?php echo $current_user_id > 0 ? '(已登录)' : '(游客)'; ?></td>
                    </tr>
                    <tr>
                        <th>访客标识</th>
                        <td><code><?php echo esc_html($current_hash); ?></code></td>
                    </tr>
                    <tr>
                        <th>IP地址</th>
                        <td><?php echo esc_html($current_ip); ?></td>
                    </tr>
                    <tr>
                        <th>User Agent</th>
                        <td><?php echo esc_html(substr($current_user_agent, 0, 100)); ?></td>
                    </tr>
                    <?php if (in_array($current_ip, ['127.0.0.1', '::1', 'localhost'])) : ?>
                    <tr>
                        <th>本地环境标识（调试）</th>
                        <td>
                            <small>
                                Cookie ID: <?php echo esc_html($_COOKIE['paper_wp_visitor_id'] ?? '无'); ?><br>
                                Accept-Language: <?php echo esc_html($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '无'); ?><br>
                                Accept-Encoding: <?php echo esc_html($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '无'); ?><br>
                                Accept-Charset: <?php echo esc_html($_SERVER['HTTP_ACCEPT_CHARSET'] ?? '无'); ?><br>
                                Connection: <?php echo esc_html($_SERVER['HTTP_CONNECTION'] ?? '无'); ?><br>
                                <strong>用于生成Hash的字符串（前100字符）：</strong><br>
                                <code><?php 
                                    $accept_lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
                                    $accept_encoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
                                    $accept_charset = $_SERVER['HTTP_ACCEPT_CHARSET'] ?? '';
                                    $connection = $_SERVER['HTTP_CONNECTION'] ?? '';
                                    $cookie_id = $_COOKIE['paper_wp_visitor_id'] ?? '';
                                    $debug_string = $current_ip . '|' . $current_user_agent . '|' . 
                                                 $accept_lang . '|' . $accept_encoding . '|' . 
                                                 $accept_charset . '|' . $connection . 
                                                 ($cookie_id ? '|' . $cookie_id : '');
                                    echo esc_html(substr($debug_string, 0, 150));
                                ?></code>
                            </small>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>是否在在线表中</th>
                        <td>
                            <?php if ($user_record) : ?>
                                <span style="color: green;">✓ 是</span>
                                <br>最后活动时间: <?php 
                                    $last_active_ts = is_numeric($user_record->last_active) ? (int)$user_record->last_active : strtotime($user_record->last_active);
                                    echo $last_active_ts > 0 ? date('Y-m-d H:i:s', $last_active_ts) : '无效时间';
                                ?>
                                <br>创建时间: <?php 
                                    $created_at_ts = is_numeric($user_record->created_at) ? (int)$user_record->created_at : strtotime($user_record->created_at);
                                    echo $created_at_ts > 0 ? date('Y-m-d H:i:s', $created_at_ts) : '无效时间';
                                ?>
                            <?php else : ?>
                                <span style="color: red;">✗ 否</span>
                                <?php if ($online_table_exists) : ?>
                                    <br><small>可能在节流期内（30秒）或数据未写入</small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>节流缓存</th>
                        <td>
                            <?php if ($cache_info['throttle']) : ?>
                                上次更新: <?php echo date('Y-m-d H:i:s', $cache_info['throttle']); ?>
                                (<?php echo time() - $cache_info['throttle']; ?>秒前)
                            <?php else : ?>
                                <span style="color: orange;">无缓存</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>手动更新</th>
                        <td>
                            <form method="post" action="" style="display: inline;">
                                <input type="hidden" name="force_update" value="1">
                                <?php wp_nonce_field('force_update_online_status', 'update_nonce'); ?>
                                <button type="submit" class="button button-secondary">立即更新我的在线状态</button>
                            </form>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="card" style="max-width: 1200px; margin-top: 20px;">
            <h2>在线人数判断逻辑</h2>
            <table class="widefat">
                <tbody>
                    <tr>
                        <th>当前时间戳</th>
                        <td><?php echo time(); ?> (<?php echo date('Y-m-d H:i:s'); ?>)</td>
                    </tr>
                    <tr>
                        <th>15分钟前时间戳</th>
                        <td><?php $cutoff = time() - 900; echo $cutoff; ?> (<?php echo date('Y-m-d H:i:s', $cutoff); ?>)</td>
                    </tr>
                    <tr>
                        <th>判断条件</th>
                        <td>
                            <code>last_active >= <?php echo $cutoff; ?></code>
                            <br><small style="color: #666;">
                                <strong>说明：</strong>
                                <br>• "15分钟"是指用户的有效期（用户访问后，如果在15分钟内没有新活动，会自动离线）
                                <br>• 用户刚访问时会立即更新 last_active 为当前时间，所以会<strong style="color: green;">立即显示为在线</strong>
                                <br>• 不需要等15分钟！只要访问就会立即算在线
                                <br>• 如果15分钟内没有新的活动，会自动从在线列表中移除
                            </small>
                        </td>
                    </tr>
                    <tr>
                        <th>数据库查询结果</th>
                        <td>
                            <?php
                            if ($online_table_exists) {
                                $query_result = $wpdb->get_results($wpdb->prepare(
                                    "SELECT id, user_id, visitor_hash, last_active, created_at 
                                     FROM $online_table 
                                     WHERE last_active >= %d 
                                     ORDER BY last_active DESC",
                                    $cutoff
                                ), ARRAY_A);
                                
                                echo '<strong>符合条件的人数：' . count($query_result) . '</strong>';
                                if (!empty($query_result)) {
                                    echo '<br><br>详细列表：';
                                    echo '<table class="widefat" style="margin-top: 10px;">';
                                    echo '<thead><tr><th>ID</th><th>用户</th><th>最后活跃时间</th><th>距现在</th><th>是否在线</th></tr></thead>';
                                    echo '<tbody>';
                                    foreach ($query_result as $r) {
                                        // 确保时间戳是整数
                                        $r_last_active = is_numeric($r['last_active']) ? (int)$r['last_active'] : strtotime($r['last_active']);
                                        $seconds_ago = $r_last_active > 0 ? time() - $r_last_active : 999999;
                                        $is_active = $r_last_active > 0 && $seconds_ago <= 900;
                                        echo '<tr>';
                                        echo '<td>' . $r['id'] . '</td>';
                                        echo '<td>' . ($r['user_id'] > 0 ? '用户 ' . $r['user_id'] : '游客') . '</td>';
                                        echo '<td>' . ($r_last_active > 0 ? date('Y-m-d H:i:s', $r_last_active) : '无效时间') . '</td>';
                                        echo '<td>' . ($seconds_ago < 999999 ? $seconds_ago . '秒 (' . round($seconds_ago / 60, 1) . '分钟)' : '无效') . '</td>';
                                        echo '<td><span style="color: ' . ($is_active ? 'green' : 'red') . ';">' . ($is_active ? '✓ 在线' : '✗ 离线') . '</span></td>';
                                        echo '</tr>';
                                    }
                                    echo '</tbody></table>';
                                }
                            }
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="card" style="max-width: 1200px; margin-top: 20px;">
            <h2>在线用户记录 (最近20条)</h2>
            <?php if (!empty($all_records)) : ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户ID</th>
                            <th>访客标识</th>
                            <th>最后活动</th>
                            <th>创建时间</th>
                            <th>状态</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_records as $record) : 
                            // 确保时间戳是整数
                            $record_last_active = is_numeric($record['last_active']) ? (int)$record['last_active'] : strtotime($record['last_active']);
                            $is_active = $record_last_active > 0 && (time() - $record_last_active) <= 900;
                            $is_current = $record['visitor_hash'] === $current_hash;
                            $seconds_ago = $record_last_active > 0 ? time() - $record_last_active : 999999;
                        ?>
                            <tr style="<?php echo $is_current ? 'background-color: #fff9c4;' : ''; ?>">
                                <td><?php echo $record['id']; ?></td>
                                <td><?php echo $record['user_id'] > 0 ? $record['user_id'] : '游客'; ?></td>
                                <td><code><?php echo esc_html(substr($record['visitor_hash'], 0, 20)); ?>...</code></td>
                                <td><?php 
                                    $last_active_ts = is_numeric($record['last_active']) ? (int)$record['last_active'] : strtotime($record['last_active']);
                                    echo $last_active_ts > 0 ? date('Y-m-d H:i:s', $last_active_ts) : '无效时间';
                                ?></td>
                                <td><?php 
                                    $created_at_ts = is_numeric($record['created_at']) ? (int)$record['created_at'] : strtotime($record['created_at']);
                                    echo $created_at_ts > 0 ? date('Y-m-d H:i:s', $created_at_ts) : '无效时间';
                                ?></td>
                                <td>
                                    <?php 
                                    // 确保时间戳是整数
                                    $record_last_active = is_numeric($record['last_active']) ? (int)$record['last_active'] : strtotime($record['last_active']);
                                    if ($record_last_active > 0) {
                                        $is_active = (time() - $record_last_active) <= 900;
                                        $seconds_ago = time() - $record_last_active;
                                    } else {
                                        $is_active = false;
                                        $seconds_ago = 999999;
                                    }
                                    ?>
                                    <?php if ($is_active) : ?>
                                        <span style="color: green;">在线 (<?php echo $seconds_ago; ?>秒前，约<?php echo round($seconds_ago / 60, 1); ?>分钟)</span>
                                    <?php else : ?>
                                        <span style="color: gray;">离线 (<?php echo $seconds_ago < 999999 ? $seconds_ago . '秒前，约' . round($seconds_ago / 60, 1) . '分钟' : '时间无效'; ?>)</span>
                                    <?php endif; ?>
                                    <?php if ($is_current) : ?>
                                        <strong> [当前用户]</strong>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>暂无在线用户记录</p>
            <?php endif; ?>
        </div>
        
        <div class="card" style="max-width: 1200px; margin-top: 20px;">
            <h2>最大在线人数记录详情</h2>
            <table class="widefat">
                <tbody>
                    <?php
                    $max_record = paper_wp_get_max_online_record();
                    $current_timestamp = time();
                    ?>
                    <tr>
                        <th>最大在线人数</th>
                        <td><strong><?php echo $max_record['count']; ?></strong></td>
                    </tr>
                    <tr>
                        <th>记录时间戳</th>
                        <td>
                            <?php echo $max_record['timestamp']; ?>
                            <?php if ($max_record['timestamp'] > 0): ?>
                                <br><small>
                                    当前时间戳: <?php echo $current_timestamp; ?><br>
                                    差值: <?php 
                                    $diff = $max_record['timestamp'] - $current_timestamp;
                                    echo $diff > 0 ? '<span style="color: red;">+' . $diff . '秒（未来时间，错误！）</span>' : $diff . '秒';
                                    ?>
                                </small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>显示的日期</th>
                        <td>
                            <?php echo !empty($max_record['date']) ? esc_html($max_record['date']) : '无'; ?>
                            <?php if ($max_record['timestamp'] > 0): ?>
                                <br><small>
                                    从时间戳转换: <?php echo wp_date('Y-m-d H:i:s', $max_record['timestamp']); ?>
                                    <?php if ($max_record['timestamp'] > ($current_timestamp + 3600)): ?>
                                        <br><span style="color: red;">⚠️ 时间戳异常（未来时间），建议点击"修复时间戳错误"按钮</span>
                                    <?php endif; ?>
                                </small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>操作</th>
                        <td>
                            <a href="?page=paper-wp-stats-test&action=fix_time" class="button" style="background: #f0b849; border-color: #f0b849;">⏰ 修复时间戳错误</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="card" style="max-width: 1200px; margin-top: 20px;">
            <h2>缓存信息</h2>
            <table class="widefat">
                <tbody>
                    <tr>
                        <th>在线人数缓存</th>
                        <td>
                            <?php if ($cache_info['online_count'] !== false) : ?>
                                <?php echo $cache_info['online_count']; ?> (缓存中)
                            <?php else : ?>
                                <span style="color: orange;">未缓存</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>WordPress缓存状态</th>
                        <td>
                            <?php
                            $cache_type = '未知';
                            if (function_exists('wp_cache_get')) {
                                if (function_exists('wp_using_ext_object_cache')) {
                                    $cache_type = wp_using_ext_object_cache() ? '外部对象缓存 (如 Redis/Memcached)' : 'WordPress默认缓存';
                                } else {
                                    $cache_type = 'WordPress默认缓存';
                                }
                            }
                            echo $cache_type;
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="card" style="max-width: 1200px; margin-top: 20px;">
            <h2>调试信息</h2>
            <details>
                <summary>点击展开调试信息</summary>
                <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto;">
<?php
echo "PHP版本: " . PHP_VERSION . "\n";
echo "WordPress版本: " . get_bloginfo('version') . "\n";
echo "当前时间: " . date('Y-m-d H:i:s') . "\n";
echo "Unix时间戳: " . time() . "\n";
echo "数据库前缀: " . $wpdb->prefix . "\n";
echo "数据库字符集: " . $wpdb->get_charset_collate() . "\n";
echo "\n";

if ($online_table_exists) {
    $table_info = $wpdb->get_results("SHOW CREATE TABLE $online_table", ARRAY_A);
    if (!empty($table_info)) {
        echo "在线用户表结构:\n";
        echo $table_info[0]['Create Table'] . "\n\n";
    }
}

echo "Hook注册:\n";
echo "- wp: paper_wp_update_user_online_status\n";
echo "- template_redirect -> wp_footer: paper_wp_update_user_online_status\n";
?>
                </pre>
            </details>
        </div>
    </div>
    
    <style>
        .wrap h1 { margin-bottom: 20px; }
        .card { background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 20px; }
        .card h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .widefat th, .widefat td { padding: 10px; }
        code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
    <?php
}

// 添加测试页面到管理菜单
add_action('admin_menu', function() {
    add_submenu_page(
        'tools.php',
        '在线统计测试',
        '在线统计测试',
        'manage_options',
        'paper-wp-stats-test',
        'paper_wp_stats_test_page'
    );
}, 20); // 使用较高的优先级确保菜单被添加

// 如果菜单没有显示，也可以通过直接访问URL测试
// URL格式: /wp-admin/admin.php?page=paper-wp-stats-test
