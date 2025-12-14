<?php
if (!defined('ABSPATH')) exit;

// ============================================================================
// 文章统计相关函数
// ============================================================================

/**
 * 获取文章阅读数（仅返回数字）
 */
function paper_wp_get_post_views_count($post_id) {
    $count = get_post_meta($post_id, 'post_views_count', true);
    return (int) $count;
}

/**
 * 获取文章阅读数（格式化显示）
 */
function paper_wp_get_post_views($post_id) {
    $count = paper_wp_get_post_views_count($post_id);
    return $count . ' 次阅读';
}

/**
 * 获取文章展示配图
 * 优先返回特色图，其次正文中的首张图片，最后使用默认图
 */
function paper_wp_get_post_cover_image($post_id) {
    $featured_image = get_the_post_thumbnail_url($post_id, 'medium_large');
    if (!empty($featured_image)) {
        return $featured_image;
    }

    $content = get_post_field('post_content', $post_id);
    if (!empty($content)) {
        // 优化：使用 strpos 快速检查，避免不必要的正则匹配
        if (strpos($content, '<img') !== false) {
            // 使用 DOMDocument 进行更准确的解析
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML('<div>' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $images = $dom->getElementsByTagName('img');
            if ($images->length > 0) {
                $src = $images->item(0)->getAttribute('src');
                if (!empty($src)) {
                    libxml_clear_errors();
                    return $src;
                }
            }
            libxml_clear_errors();
        }
    }

    return '';
}


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
 * 
 * 在首次访问时自动创建所需的数据库表
 * 使用静态变量避免同一请求内重复检查
 */
function paper_wp_init_stats_tables() {
    static $initialized = false;
    if ($initialized) {
        return;
    }
    
    // 创建在线用户表(如果不存在)
    if (!paper_wp_stats_table_exists('paper_online_users')) {
        paper_wp_create_online_users_table();
    }
    
    // 创建管理员会话表(如果不存在)
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
 * 优先级: 用户ID > Cookie ID > IP+UA
 */
function paper_wp_generate_visitor_hash($user_id, $ip, $user_agent) {
    // 已登录用户:使用用户ID
    if ($user_id > 0) {
        return 'u' . $user_id;
    }
    
    // 游客:优先使用Cookie ID(如果存在)
    $cookie_id = $_COOKIE['paper_wp_visitor_id'] ?? '';
    if (!empty($cookie_id)) {
        return 'g' . substr(hash('sha256', $cookie_id), 0, 32);
    }
    
    // 回退方案:使用IP+UserAgent组合
    // 注意:这种方式在用户更换浏览器或清除Cookie后会被识别为新访客
    $unique_string = $ip . '|' . $user_agent;
    return 'g' . substr(hash('sha256', $unique_string), 0, 32);
}

/**
 * 更新用户在线状态
 */
function paper_wp_update_user_online_status($force = false) {
    global $wpdb;
    
    // 确保表已初始化
    paper_wp_init_stats_tables();
    
    $user_id = (int) get_current_user_id();
    // 获取真实IP（考虑代理情况）
    $ip = paper_wp_get_real_client_ip();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // 为游客设置Cookie标识（如果还没有）
    if ($user_id === 0 && empty($_COOKIE['paper_wp_visitor_id']) && !headers_sent()) {
        // 生成唯一标识符并设置为Cookie
        $visitor_id = 'vid_' . bin2hex(random_bytes(16)); // 32字符
        setcookie('paper_wp_visitor_id', $visitor_id, time() + (90 * DAY_IN_SECONDS), '/', '', is_ssl(), true); // 优化：从365天改为90天
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
    
    // 使用 INSERT ... ON DUPLICATE KEY UPDATE 一次性完成插入或更新
    // 这样可以避免先查询再决定的两次数据库操作
    $result = $wpdb->query($wpdb->prepare(
        "INSERT INTO $table_name (user_id, visitor_hash, last_active, created_at)
         VALUES (%d, %s, %d, %d)
         ON DUPLICATE KEY UPDATE 
            user_id = VALUES(user_id),
            last_active = VALUES(last_active)",
        $user_id,
        $visitor_hash,
        $current_time,
        $current_time
    ));
    
    // 检查数据库错误
    if ($result === false) {
        if ($wpdb->last_error) {
            error_log('Paper WP Stats: 在线状态更新失败 - ' . $wpdb->last_error);
        }
        return false;
    }
    
    // 如果操作成功,更新节流缓存
    if ($result !== false) {
        $throttle_key = 'online_update_' . $visitor_hash;
        wp_cache_set($throttle_key, $current_time, 'paper_stats', 30);
    }
    
    return $result !== false;
}

/**
 * 获取当前在线用户数
 */
function paper_wp_get_online_users_count() {
    global $wpdb;
    
    // 确保表已初始化
    paper_wp_init_stats_tables();
    
    $table_name = $wpdb->prefix . 'paper_online_users';
    $cutoff_time = time() - 300; // 5分钟前
    
    // 查询在线人数
    $count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name
         WHERE CAST(last_active AS UNSIGNED) >= %d",
        $cutoff_time
    ));
    
    // 如果查询失败,返回0
    if ($count === false) {
        $count = 0;
    }
    
    // 自动更新最大记录
    if ($count > 0) {
        paper_wp_update_max_online_if_needed($count);
    }
    
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
    }
}

/**
 * 获取最大在线人数记录
 */
function paper_wp_get_max_online_record() {
    $record = get_option('paper_wp_max_online', [
        'count' => 0,
        'timestamp' => 0,
        'date' => ''
    ]);
    
    // 一次性检查并修复时间戳异常
    if ($record['timestamp'] > 0) {
        $current_time = time();
        
        // 检查是否是未来时间(超过1小时)
        if ($record['timestamp'] > ($current_time + 3600)) {
            // 尝试时区修正
            $timezone = wp_timezone();
            $timezone_offset = $timezone->getOffset(new DateTime());
            $corrected_timestamp = $record['timestamp'] - $timezone_offset;
            
            // 如果修正后仍然异常,使用当前时间
            if ($corrected_timestamp > ($current_time + 3600) || $corrected_timestamp < ($current_time - 86400 * 365)) {
                $corrected_timestamp = $current_time;
            }
            
            $record['timestamp'] = $corrected_timestamp;
            $record['date'] = wp_date('Y-m-d H:i:s', $corrected_timestamp);
            update_option('paper_wp_max_online', $record);
        } 
        // 检查日期格式是否正确
        elseif (!empty($record['date'])) {
            $expected_date = wp_date('Y-m-d H:i:s', $record['timestamp']);
            if ($expected_date !== $record['date']) {
                $record['date'] = $expected_date;
                update_option('paper_wp_max_online', $record);
            }
        }
    }
    
    return $record;
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
        return;
    }
    
    $last_update = (int) $session->last_update;
    $time_since_update = $current_time - $last_update;
    
    /**
     * 会话管理逻辑说明:
     * 
     * 1. 如果超过30分钟未活动 -> 视为会话结束,开始新会话
     *    - 将旧会话时间(最多30分钟)保存到总时间
     *    - 重置会话计时器
     * 
     * 2. 如果在30分钟内活动 -> 继续当前会话
     *    - 累加活动时间到会话总时间
     *    - 每累积10分钟,自动保存到总时间并重置(防止数据丢失)
     * 
     * 设计目的:
     * - 30分钟阈值: 区分连续工作和间断工作
     * - 10分钟阈值: 定期保存数据,避免异常退出时丢失过多数据
     */
    
    // 如果超过30分钟未活动，视为新会话
    if ($time_since_update > 1800) {
        // 保存旧会话时间到总时间(限制最多30分钟,避免异常情况)
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
        // 继续当前会话,更新会话时间（限制单次增量最多30分钟,防止时间异常）
        $increment = min($time_since_update, 1800);
        $new_total = (int) $session->total_seconds + $increment;
        
        // 如果累积时间超过10分钟，保存到总时间并重置(定期保存,防止数据丢失)
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
 * 获取博客总字数 (高性能版)
 * 直接使用 SQL 计算字符长度,避免将大量内容加载到 PHP 内存
 */
function paper_wp_get_total_word_count() {
    global $wpdb;
    $cache_key = 'total_words';
    $group = 'stats';

    // 先尝试从缓存获取
    $cached_count = wp_cache_get($cache_key, $group);
    if ($cached_count !== false) {
        return (int) $cached_count;
    }

    // 使用 SQL 直接计算字符长度 (CHAR_LENGTH)
    // 注意: 这不是精确的中文分词字数,但对于全站统计来说误差可接受
    // 且性能比在 PHP 中处理高出几个数量级
    $word_count = (int) $wpdb->get_var(
        "SELECT SUM(CHAR_LENGTH(post_content)) 
         FROM {$wpdb->posts} 
         WHERE post_type = 'post' AND post_status = 'publish'"
    );
    
    // 设置缓存,24小时过期
    wp_cache_set($cache_key, $word_count, $group, DAY_IN_SECONDS);
    
    return $word_count;
}

/**
 * 获取总阅读量（所有文章阅读数之和）
 */
function paper_wp_get_total_views() {
    global $wpdb;
    $cache_key = 'total_views';
    $group = 'stats';
    
    // 先尝试从缓存获取
    $cached_count = wp_cache_get($cache_key, $group);
    if ($cached_count !== false) {
        return (int) $cached_count;
    }
    
    // 使用 SQL 直接计算所有文章阅读数之和
    $total_views = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT SUM(CAST(meta_value AS UNSIGNED)) 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = %s",
            'post_views_count'
        )
    );
    
    // 设置缓存,5分钟过期
    wp_cache_set($cache_key, $total_views, $group, 300);
    
    return $total_views;
}

/**
 * 获取总访问数(PV)
 */
function paper_wp_get_total_visits() {
    $total_visits = get_option('paper_wp_total_visits', 0);
    return (int) $total_visits;
}

/**
 * 更新总访问数
 */
function paper_wp_update_total_visits() {
    // 1. 检查 Cookie (24小时内访问过)
    if (isset($_COOKIE['paper_wp_visited'])) {
        return;
    }

    // 2. 检查 IP 缓存 (防止禁用 Cookie 的刷量，24小时)
    $visitor_ip = paper_wp_get_real_client_ip(); // 优化：统一使用真实IP获取函数
    $transient_key = 'visit_ip_' . md5($visitor_ip);
    
    if (get_transient($transient_key)) {
        // 如果 IP 在缓存中，说明 24 小时内访问过，补种 Cookie 并返回
        if (!headers_sent()) {
            setcookie('paper_wp_visited', '1', time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
        }
        return;
    }

    // 3. 更新访问数
    $current_visits = get_option('paper_wp_total_visits', 0);
    update_option('paper_wp_total_visits', $current_visits + 1, false);
    
    // 4. 设置标记
    // 设置 Cookie
    if (!headers_sent()) {
        setcookie('paper_wp_visited', '1', time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
    }
    // 设置 IP 缓存 (24小时)
    set_transient($transient_key, 1, DAY_IN_SECONDS);
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
        $footer_settings = Paper_Settings_Manager::get('paper_wp_footer_stats_settings', []);
    }


    // 计算博客运行时间
    // 优先使用效果设置中的建站时间,否则使用第一篇文章的发布时间
    $effects_settings = Paper_Settings_Manager::get('paper_wp_effects_settings', []);
    $site_start_date = !empty($effects_settings['site_start_date']) ? $effects_settings['site_start_date'] : '';
    
    // 如果没有设置建站时间,使用第一篇文章的发布时间
    if (empty($site_start_date)) {
        global $wpdb;
        $first_post_date = $wpdb->get_var(
            "SELECT post_date FROM {$wpdb->posts} 
             WHERE post_type = 'post' AND post_status = 'publish' 
             ORDER BY post_date ASC LIMIT 1"
        );
        
        if ($first_post_date) {
            $site_start_date = $first_post_date;
        } else {
            // 如果没有文章,使用当前时间
            $site_start_date = current_time('Y-m-d H:i:s');
        }
    }
    
    try {
        // 尝试解析用户输入的日期
        $start_datetime = new DateTime($site_start_date, wp_timezone());
    } catch (Exception $e) {
        // 如果解析失败,使用第一篇文章时间作为后备
        global $wpdb;
        $first_post_date = $wpdb->get_var(
            "SELECT post_date FROM {$wpdb->posts} 
             WHERE post_type = 'post' AND post_status = 'publish' 
             ORDER BY post_date ASC LIMIT 1"
        );
        $start_datetime = new DateTime($first_post_date ?: current_time('Y-m-d H:i:s'), wp_timezone());
    }
    
    // 覆盖 install_timestamp 以供后续使用 (转换为Unix时间戳)
    $install_timestamp = $start_datetime->getTimestamp();

    $timezone = wp_timezone();
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

    $powered_by_text = $footer_settings['powered_by_text'] ?? '2017-2025';
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
        'install_timestamp' => $install_timestamp ?: strtotime($blog_start_date), // 添加开始时间戳供前端使用
        'total_visits' => paper_wp_get_total_visits(), // 总访问数
        'total_views' => paper_wp_get_total_views(), // 总阅读量
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
    <div class="footer-stats" style="display: flex; flex-direction: column; gap: 4px; align-items: center; line-height: 1.4;">
        
        <!-- 运行时间 -->
        <div class="footer-runtime">
            本站悄然运行了 <span id="site-runtime" data-start="<?php echo intval($data['install_timestamp']); ?>">加载中...</span>
        </div>

        <!-- 核心数据：访问数、阅读量、在线人数 -->
        <div class="footer-data" style="display: flex; gap: 15px; flex-wrap: wrap; justify-content: center; align-items: center;">
            <span class="stat-item" style="display: inline-flex; align-items: center;">
                <svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" width="14" height="14" style="fill: currentColor; vertical-align: middle;">
                    <path d="M565.2 521.9c76.2-23 132.1-97.6 132.1-186.2 0-106.9-81.3-193.5-181.6-193.5s-181.6 86.6-181.6 193.5c0 87.6 54.6 161.6 129.5 185.4-142.2 23.1-250.8 146.5-250.8 295.3 0 2.9 0 5.8 0.1 8.7 0.9 31.5 26.5 56.7 58.1 56.7h482.1c31.2 0 57-24.6 58-55.8 0.1-3.2 0.2-6.4 0.2-9.6-0.1-147.1-106.2-269.4-246.1-294.5z"></path>
                </svg>
                <?php echo number_format(intval($data['total_visits']), 0, '', ''); ?>
            </span>
            
            <span class="stat-item" style="display: inline-flex; align-items: center;">
                <svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" width="14" height="14" style="fill: currentColor; vertical-align: middle;">
                    <path d="M508 512m-112 0a112 112 0 1 0 224 0 112 112 0 1 0-224 0Z"></path>
                    <path d="M942.2 486.2C847.4 286.5 704.1 186 512 186c-192.2 0-335.4 100.5-430.2 300.3-7.7 16.2-7.7 35.2 0 51.5C176.6 737.5 319.9 838 512 838c192.2 0 335.4-100.5 430.2-300.3 7.7-16.2 7.7-35 0-51.5zM508 688c-97.2 0-176-78.8-176-176s78.8-176 176-176 176 78.8 176 176-78.8 176-176 176z"></path>
                </svg>
                <?php echo number_format(intval($data['total_views']), 0, '', ''); ?>
            </span>

            <span class="stat-item">
                当前 <?php echo intval($data['online_users_count']); ?> 人在线
                <?php if ($data['max_online_count'] > 0): ?>
                    <span style="opacity: 0.8; font-size: 0.9em;">(最高 <?php echo intval($data['max_online_count']); ?> 人，发生在 <?php echo esc_html($data['days_since_max']); ?>)</span>
                <?php endif; ?>
            </span>
        </div>

        <!-- 版权与状态 -->
        <div class="footer-copyright" style="opacity: 0.8; font-size: 0.9em;">
            <a href="<?php echo home_url(); ?>"><?php bloginfo('name'); ?></a><button id="theme-toggle-emoji" title="切换主题" style="background: none; border: none; cursor: pointer; font-size: 10px; margin-left: 0; padding: 0; border-radius: 2px; transition: background-color 0.3s ease; vertical-align: middle;">☀️</button>
            <!-- <span style="margin: 0 0px;"></span> -->
            <?php echo esc_html($data['final_online_text']); ?> (总共 <?php echo esc_html($data['admin_total_online_time']); ?>)
            <span style="margin: 0 5px;"></span>
            <a target="_blank" rel="noopener noreferrer" href="#">&copy;<?php echo esc_html($data['powered_by_text']); ?></a>
        </div>
        
    </div>
    <?php
}

/**
 * 清理过期的在线用户记录
 * 清理15分钟前的记录（在线判定5分钟的3倍）
 */
function paper_wp_cleanup_online_users_table() {
    global $wpdb;
    
    if (!paper_wp_stats_table_exists('paper_online_users')) {
        return;
    }
    
    $table_name = $wpdb->prefix . 'paper_online_users';
    $cutoff_time = time() - 900; // 15分钟前（优化：从1小时改为15分钟）
    
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
    
    // 记录清理结果（仅在有删除时）
    if ($total_deleted > 0) {
        error_log("Paper WP Stats: 清理了 {$total_deleted} 条过期在线记录");
    }
}

/**
 * 清理字数缓存
 */
function paper_wp_clear_word_count_cache() {

}

/**
 * 清理归档页面缓存
 */
function paper_wp_clear_archive_cache() {
    // 清理归档统计缓存
    delete_transient('barepaper_archive_stats');

    // 清理热力图数据缓存
    delete_transient('paper_wp_heatmap_data');

    // 清理归档文章列表缓存
    delete_transient('paper_wp_archive_list');
}

/**
 * 清理统计相关缓存
 */
function paper_wp_clear_stats_cache() {
    // 使用缓存系统的flush_group来清理整个stats组
    if (function_exists('paper_wp_cache_flush_group')) {
        paper_wp_cache_flush_group('stats');
    }
    

    
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
    $ip = paper_wp_get_real_client_ip(); // 优化：统一使用真实IP获取函数
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $visitor_hash = paper_wp_generate_visitor_hash(0, $ip, $user_agent);
    
    $online_table = $wpdb->prefix . 'paper_online_users';
    $wpdb->delete($online_table, ['visitor_hash' => $visitor_hash], ['%s']);
    
    // 初始化管理员会话
    update_option('paper_wp_admin_last_active_' . $user_id, $current_time);
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

// 文章发布时清理归档页面缓存
add_action('publish_post', 'paper_wp_clear_archive_cache');
add_action('save_post', 'paper_wp_clear_archive_cache');
add_action('delete_post', 'paper_wp_clear_archive_cache');
add_action('trash_post', 'paper_wp_clear_archive_cache');

// 定时任务: 每小时清理一次过期数据（优化：从每天改为每小时）
if (!wp_next_scheduled('paper_wp_hourly_cleanup_event')) {
    wp_schedule_event(time(), 'hourly', 'paper_wp_hourly_cleanup_event');
}

add_action('paper_wp_hourly_cleanup_event', 'paper_wp_cleanup_online_users_table');

// 主题切换时清除定时任务
add_action('switch_theme', function() {
    wp_clear_scheduled_hook('paper_wp_hourly_cleanup_event');
    wp_clear_scheduled_hook('paper_wp_daily_cleanup_event'); // 清除旧的hook
    wp_clear_scheduled_hook('paper_wp_cleanup_online_users_event'); // 清除更旧的hook
});

// 在模板加载后确保更新在线状态（优先级较高，尽早执行）
add_action('template_redirect', function() {
    // 立即更新在线状态，不等待页面加载完成
    paper_wp_update_user_online_status();
}, 5);

// 主题激活时初始化表
add_action('after_setup_theme', function() {
    paper_wp_init_stats_tables();
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

// 更新总访问数 - 每次页面加载时
add_action('wp', 'paper_wp_update_total_visits');
