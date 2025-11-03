<?php
/**
 * 音乐播放统计管理类
 * 
 * 功能：
 * - 记录歌曲播放次数和时长
 * - 统计用户播放行为（包括管理员、注册用户、游客）
 * - 提供歌曲排行榜和用户排行榜
 * - 支持自定义音乐和平台音乐的统计
 * 
 * @author wangdaodao
 * @version 1.0.0
 * @date 2025-10-31
 */

if (!defined('ABSPATH')) exit;

class PaperMusicStats {
    
    private static $instance = null;
    private $table_name;
    private $user_stats_table;
    
    /**
     * 获取单例实例
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 构造函数
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'music_play_stats';
        $this->user_stats_table = $wpdb->prefix . 'music_user_stats';
        
        $this->init();
    }
    
    /**
     * 初始化
     */
    private function init() {
        // 注册主题激活钩子（用于主题安装时的数据库表创建）
        add_action('after_switch_theme', [$this, 'on_theme_switch']);

        // 创建表（如果不存在）
        add_action('init', [$this, 'maybe_create_tables'], 10);

        // 注册AJAX处理器
        add_action('wp_ajax_record_music_play', [$this, 'ajax_record_play']);
        add_action('wp_ajax_nopriv_record_music_play', [$this, 'ajax_record_play']);

        add_action('wp_ajax_get_music_stats', [$this, 'ajax_get_stats']);
        add_action('wp_ajax_nopriv_get_music_stats', [$this, 'ajax_get_stats']);
    }
    
    /**
     * 主题切换时的处理
     */
    public function on_theme_switch() {
        // 主题切换时强制创建表并设置版本号
        $this->create_tables();
        // 检查并修复表结构
        $this->check_and_fix_table_structure();
        update_option('paper_music_stats_db_version', '1.2');
    }
    
    /**
     * 检查并创建表
     */
    public function maybe_create_tables() {
        global $wpdb;

        // 首先检查表是否存在
        $table1_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");
        $table2_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->user_stats_table}'");

        // 如果表不存在，直接创建
        if (!$table1_exists || !$table2_exists) {
            $this->create_tables();
            update_option('paper_music_stats_db_version', '1.2');
            return;
        }

        // 如果表存在，检查版本号是否需要升级
        $installed_version = get_option('paper_music_stats_db_version', '0');
        $current_version = '1.2';

        if (version_compare($installed_version, $current_version, '<')) {
            // 如果是版本升级，先备份旧数据（可选）
            $this->backup_old_data();

            $this->create_tables();
            update_option('paper_music_stats_db_version', $current_version);
        }
    }
    
    /**
     * 备份旧数据（可选，用于版本升级）
     */
    private function backup_old_data() {
        global $wpdb;

        // 检查表是否存在且有数据
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");
        if ($table_exists) {
            $data_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
            if ($data_count > 0) {
                // 将旧数据备份到选项中（仅保留最近的备份）
                $backup_data = [
                    'songs' => $wpdb->get_results("SELECT * FROM {$this->table_name}", ARRAY_A),
                    'users' => $wpdb->get_results("SELECT * FROM {$this->user_stats_table}", ARRAY_A),
                    'backup_time' => current_time('mysql'),
                    'version' => get_option('paper_music_stats_db_version', '0')
                ];
                update_option('paper_music_stats_backup', $backup_data);

                // 清空旧表数据
                $wpdb->query("TRUNCATE TABLE {$this->table_name}");
                $wpdb->query("TRUNCATE TABLE {$this->user_stats_table}");
            }
        }
    }

    /**
     * 检查并修复表结构（优化版本）
     */
    private function check_and_fix_table_structure() {
        global $wpdb;

        // 缓存表存在性检查
        static $tables_checked = false;
        static $song_table_exists = false;
        static $user_table_exists = false;

        if (!$tables_checked) {
            $song_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");
            $user_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->user_stats_table}'");
            $tables_checked = true;
        }

        if (!$song_table_exists) {
            return false;
        }

        // 批量检查字段存在性
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_name}");
        $column_names = array_column($columns, 'Field');

        $required_columns = [
            'song_name' => "ALTER TABLE {$this->table_name} ADD COLUMN song_name varchar(500) NOT NULL DEFAULT '' AFTER song_id",
            'song_artist' => "ALTER TABLE {$this->table_name} ADD COLUMN song_artist varchar(500) DEFAULT '' AFTER song_name",
            'song_type' => "ALTER TABLE {$this->table_name} ADD COLUMN song_type varchar(50) NOT NULL DEFAULT 'custom' AFTER song_artist",
            'platform' => "ALTER TABLE {$this->table_name} ADD COLUMN platform varchar(50) DEFAULT '' AFTER song_type",
            'play_count' => "ALTER TABLE {$this->table_name} ADD COLUMN play_count int(11) NOT NULL DEFAULT 0 AFTER platform",
            'total_duration' => "ALTER TABLE {$this->table_name} ADD COLUMN total_duration int(11) NOT NULL DEFAULT 0 AFTER play_count",
            'last_played' => "ALTER TABLE {$this->table_name} ADD COLUMN last_played datetime DEFAULT CURRENT_TIMESTAMP AFTER total_duration",
            'created_at' => "ALTER TABLE {$this->table_name} ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP AFTER last_played",
            'updated_at' => "ALTER TABLE {$this->table_name} ADD COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at"
        ];

        $fixed = false;
        foreach ($required_columns as $column => $sql) {
            if (!in_array($column, $column_names)) {
                $result = $wpdb->query($sql);
                if ($result !== false) {
                    $fixed = true;
                }
            }
        }

        // 检查用户统计表字段
        if ($user_table_exists) {
            $user_columns = $wpdb->get_results("SHOW COLUMNS FROM {$this->user_stats_table}");
            $user_column_names = array_column($user_columns, 'Field');

            if (!in_array('user_name', $user_column_names)) {
                $sql = "ALTER TABLE {$this->user_stats_table} ADD COLUMN user_name varchar(255) DEFAULT '' AFTER user_type";
                $result = $wpdb->query($sql);
                if ($result !== false) {
                    $fixed = true;
                }
            }
        }

        return $fixed;
    }

    /**
     * 创建数据库表（MySQL版本）
     */
    public function create_tables() {
        global $wpdb;

        // 确保升级文件已加载
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }

        // MySQL 版本的 SQL
        $charset_collate = $wpdb->get_charset_collate();

        $sql1 = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            song_id varchar(255) NOT NULL,
            song_name varchar(500) NOT NULL,
            song_artist varchar(500) DEFAULT '',
            song_type varchar(50) NOT NULL DEFAULT 'custom',
            platform varchar(50) DEFAULT '',
            play_count int(11) NOT NULL DEFAULT 0,
            total_duration int(11) NOT NULL DEFAULT 0,
            last_played datetime DEFAULT CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY song_id (song_id),
            KEY song_type (song_type),
            KEY play_count (play_count),
            KEY total_duration (total_duration),
            KEY last_played (last_played)
        ) $charset_collate";

        $sql2 = "CREATE TABLE IF NOT EXISTS {$this->user_stats_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL DEFAULT 0,
            user_identifier varchar(255) NOT NULL,
            user_type varchar(50) NOT NULL DEFAULT 'guest',
            user_name varchar(255) DEFAULT '',
            play_count int(11) NOT NULL DEFAULT 0,
            total_duration int(11) NOT NULL DEFAULT 0,
            last_played datetime DEFAULT CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_identifier (user_identifier),
            KEY user_type (user_type),
            KEY play_count (play_count),
            KEY total_duration (total_duration),
            KEY last_played (last_played)
        ) $charset_collate";

        // 执行创建表操作
        dbDelta($sql1);
        dbDelta($sql2);

        // 验证表是否创建成功
        $table1_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");
        $table2_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->user_stats_table}'");

        return $table1_exists && $table2_exists;
    }
    
    /**
     * AJAX处理 - 记录播放数据
     */
    public function ajax_record_play() {
        global $wpdb;

        // 验证nonce
        if (!check_ajax_referer('music_stats_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => '安全验证失败']);
            return;
        }

        // 获取参数
        $song_id = sanitize_text_field($_POST['song_id'] ?? '');
        $song_name = sanitize_text_field($_POST['song_name'] ?? '');
        $song_artist = sanitize_text_field($_POST['song_artist'] ?? '');
        $song_type = sanitize_text_field($_POST['song_type'] ?? 'custom');
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        $duration = intval($_POST['duration'] ?? 0);
        $is_first_report = isset($_POST['is_first_report']) && $_POST['is_first_report'] === 'true';

        // 参数验证
        if (empty($song_id)) {
            wp_send_json_error(['message' => '参数错误：song_id不能为空']);
            return;
        }

        if ($duration < 1) {
            wp_send_json_error(['message' => '参数错误：播放时长无效']);
            return;
        }

        // 检查表是否存在，如果不存在则创建
        $table1_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");
        $table2_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->user_stats_table}'");
        if (!$table1_exists || !$table2_exists) {
            $this->create_tables();
            // 再次验证表是否创建成功
            $table1_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");
            $table2_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->user_stats_table}'");
            if (!$table1_exists || !$table2_exists) {
                wp_send_json_error(['message' => '数据库表初始化失败，请刷新页面重试']);
                return;
            }
        }

        // 记录歌曲播放统计
        $song_recorded = $this->record_song_play($song_id, $song_name, $song_artist, $song_type, $platform, $duration, $is_first_report);

        // 记录用户播放统计
        $user_recorded = $this->record_user_play($duration, $is_first_report);

        // $wpdb->update() 返回受影响的行数（可能是0），false表示错误
        // $wpdb->insert() 返回插入的行数（通常是1），false表示错误
        if ($song_recorded !== false && $user_recorded !== false) {
            wp_send_json_success(['message' => '记录成功']);
        } else {
            wp_send_json_error(['message' => '记录失败']);
        }
    }
    
    /**
     * 记录歌曲播放统计
     */
    private function record_song_play($song_id, $song_name, $song_artist, $song_type, $platform, $duration, $is_first_report = false) {
        global $wpdb;

        // 验证 song_id 不为空（双重检查）
        if (empty($song_id)) {
            return false;
        }

        // 检查是否已存在
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE song_id = %s",
            $song_id
        ));

        if ($existing) {
            // 更新统计
            $new_play_count = $is_first_report ? $existing->play_count + 1 : $existing->play_count;
            $new_total_duration = $existing->total_duration + $duration;

            $update_data = [
                'play_count' => $new_play_count,
                'total_duration' => $new_total_duration,
                'last_played' => current_time('mysql')
            ];

            // 检查是否需要更新歌曲信息
            if (!empty($song_name) && ($existing->song_name === '未知歌曲' || $existing->song_name === '')) {
                $update_data['song_name'] = $song_name;
            }
            if (!empty($song_artist) && ($existing->song_artist === '未知艺术家' || $existing->song_artist === '')) {
                $update_data['song_artist'] = $song_artist;
            }

            // 动态生成格式字符串，匹配 $update_data 中的字段
            $format_array = [];
            foreach ($update_data as $key => $value) {
                if ($key === 'play_count' || $key === 'total_duration') {
                    $format_array[] = '%d';
                } else {
                    $format_array[] = '%s';
                }
            }

            $result = $wpdb->update(
                $this->table_name,
                $update_data,
                ['song_id' => $song_id],
                $format_array,
                ['%s']
            );

            // $wpdb->update() 返回受影响的行数，0表示没有行被更新（但数据可能已相同），false表示错误
            if ($result === false) {
                return false;
            }
            
            return $result >= 0; // 返回true表示成功（包括0行更新的情况）
        } else {
            // 插入新记录
            $result = $wpdb->insert(
                $this->table_name,
                [
                    'song_id' => $song_id,
                    'song_name' => $song_name ?: '未知歌曲',
                    'song_artist' => $song_artist ?: '未知艺术家',
                    'song_type' => $song_type ?: 'custom',
                    'platform' => $platform ?: '',
                    'play_count' => 1,
                    'total_duration' => $duration,
                    'last_played' => current_time('mysql')
                ],
                ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s']
            );

            if ($result === false) {
                return false;
            }
            
            return true;
        }
    }
    
    /**
     * 记录用户播放统计
     * 
     * @param bool $is_first_report 是否是第一次上报（用于判断是否增加播放次数）
     */
    private function record_user_play($duration, $is_first_report = false) {
        global $wpdb;
        
        // 获取当前用户信息
        $current_user = wp_get_current_user();
        
        if ($current_user->ID) {
            // 已登录用户
            $user_id = $current_user->ID;
            $user_identifier = 'user_' . $user_id;
            $user_type = user_can($user_id, 'administrator') ? 'admin' : 'registered';
            $user_name = $current_user->display_name;
        } else {
            // 游客
            $user_id = 0;
            $user_identifier = $this->get_guest_identifier();
            $user_type = 'guest';
            $user_name = $this->get_guest_display_name();
        }
        
        // 检查是否已存在
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->user_stats_table} WHERE user_identifier = %s",
            $user_identifier
        ));
        
        if ($existing) {
            // 更新统计
            // 只在第一次上报时增加播放次数，后续上报只增加时长
            $new_play_count = $is_first_report ? $existing->play_count + 1 : $existing->play_count;
            
            $result = $wpdb->update(
                $this->user_stats_table,
                [
                    'play_count' => $new_play_count,
                    'total_duration' => $existing->total_duration + $duration,
                    'last_played' => current_time('mysql')
                ],
                ['user_identifier' => $user_identifier],
                ['%d', '%d', '%s'],
                ['%s']
            );

            if ($result === false) {
                return false;
            }
            return $result >= 0; // 返回true表示成功（包括0行更新的情况）
        } else {
            // 插入新记录（第一次必然是新播放）
            $result = $wpdb->insert(
                $this->user_stats_table,
                [
                    'user_id' => $user_id,
                    'user_identifier' => $user_identifier,
                    'user_type' => $user_type,
                    'user_name' => $user_name ?: '',
                    'play_count' => 1,
                    'total_duration' => $duration,
                    'last_played' => current_time('mysql')
                ],
                ['%d', '%s', '%s', '%s', '%d', '%d', '%s']
            );

            if ($result === false) {
                return false;
            }
            return true;
        }
    }
    
    /**
     * 获取游客标识（基于IP和User Agent）
     */
    private function get_guest_identifier() {
        $ip = $this->get_client_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return 'guest_' . md5($ip . $user_agent);
    }

    /**
     * 获取游客显示名称（显示为"游客（IP前两位.*.*）"格式）
     */
    private function get_guest_display_name() {
        $ip = $this->get_client_ip();
        // 获取IP前两位
        $ip_parts = explode('.', $ip);
        $ip_prefix = count($ip_parts) >= 2 ? $ip_parts[0] . '.' . $ip_parts[1] : '未知';

        return '游客（' . $ip_prefix . '.*.*）';
    }
    
    /**
     * 获取客户端IP
     */
    private function get_client_ip() {
        $ip = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        return sanitize_text_field($ip);
    }
    
    /**
     * AJAX处理 - 获取统计数据
     */
    public function ajax_get_stats() {
        $stats = [
            'top_songs' => $this->get_top_songs(10),
            'top_users' => $this->get_top_users(10),
            'overview' => $this->get_overview_stats()
        ];
        
        wp_send_json_success($stats);
    }
    
    /**
     * 获取热门歌曲排行（按总播放时长排序）
     */
    public function get_top_songs($limit = 10, $order_by = 'total_duration') {
        global $wpdb;
        
        // 强制按总播放时长排序
        $order_by = 'total_duration';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT song_id, song_name, song_artist, song_type, platform, 
                    play_count, total_duration, last_played
             FROM {$this->table_name}
             ORDER BY total_duration DESC, play_count DESC
             LIMIT %d",
            $limit
        ));
        
        return $results;
    }
    
    /**
     * 获取用户排行（按总播放时长排序）
     */
    public function get_top_users($limit = 10, $order_by = 'total_duration') {
        global $wpdb;
        
        // 强制按总播放时长排序
        $order_by = 'total_duration';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT user_identifier, user_type, user_name, 
                    play_count, total_duration, last_played
             FROM {$this->user_stats_table}
             ORDER BY total_duration DESC, play_count DESC
             LIMIT %d",
            $limit
        ));
        
        return $results;
    }
    
    
    /**
     * 获取概览统计
     */
    public function get_overview_stats() {
        global $wpdb;
        
        // 总播放次数
        $total_plays = $wpdb->get_var(
            "SELECT SUM(play_count) FROM {$this->table_name}"
        ) ?: 0;
        
        // 总播放时长
        $total_duration = $wpdb->get_var(
            "SELECT SUM(total_duration) FROM {$this->table_name}"
        ) ?: 0;
        
        // 歌曲总数
        $total_songs = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name}"
        ) ?: 0;
        
        // 用户总数
        $total_users = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->user_stats_table}"
        ) ?: 0;
        
        // 今日播放次数
        $today_plays = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(play_count) FROM {$this->table_name} 
             WHERE DATE(last_played) = %s",
            current_time('Y-m-d')
        )) ?: 0;
        
        return [
            'total_plays' => intval($total_plays),
            'total_duration' => intval($total_duration),
            'total_songs' => intval($total_songs),
            'total_users' => intval($total_users),
            'today_plays' => intval($today_plays)
        ];
    }
    
    /**
     * 格式化时长
     */
    public static function format_duration($seconds) {
        if ($seconds < 60) {
            return $seconds . '秒';
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $secs = $seconds % 60;
            return $minutes . '分' . ($secs > 0 ? $secs . '秒' : '');
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return $hours . '小时' . ($minutes > 0 ? $minutes . '分' : '');
        }
    }
    
    /**
     * 渲染统计面板
     */
    public function render_stats_panel() {
        $top_songs = $this->get_top_songs(10);
        $top_users = $this->get_top_users(10);
        $overview = $this->get_overview_stats();

        ob_start();
        ?>
        <div class="music-stats-panel">
            <!-- 简洁标题 -->
            <div class="stats-header">
                <span class="stats-title">音乐统计</span>
                <span class="stats-summary">
                    <span class="stat-inline">
                        <span class="stat-inline-label">时长</span>
                        <span class="stat-inline-value"><?php echo self::format_duration($overview['total_duration']); ?></span>
                    </span>
                    <span class="stat-inline">
                        <span class="stat-inline-label">听众</span>
                        <span class="stat-inline-value"><?php echo number_format($overview['total_users']); ?></span>
                    </span>
                </span>
            </div>
            
            <!-- Tab导航 -->
            <div class="stats-tabs">
                <input type="radio" id="tab-songs" name="stats-tab" checked>
                <label for="tab-songs" class="tab-label">热门歌曲</label>
                
                <input type="radio" id="tab-users" name="stats-tab">
                <label for="tab-users" class="tab-label">听众排行</label>
                
                <div class="tab-content">
                    <!-- 热门歌曲面板 -->
                    <div id="songs-panel" class="tab-panel">
                        <?php if (!empty($top_songs)): ?>
                            <ul class="top-songs-list">
                                <?php foreach ($top_songs as $index => $song): ?>
                                    <li class="top-song-item" style="margin-bottom: 0px;">
                                        <div class="rank-icon" style="margin-right: 4px;">
                                            <?php if ($index < 3): ?>
                                                <span class="medal-icon">
                                                    <?php echo ['🥇', '🥈', '🥉'][$index]; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="medal-icon" style="color: #999;">🏅</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="song-info" style="margin-right: 4px;">
                                            <span class="song-title"><?php
                                                // 处理多个破折号的情况
                                                $song_name = preg_replace('/\s*-+\s*/', ' - ', $song->song_name);
                                                echo esc_html($song_name);
                                            ?></span>
                                            <span class="song-artist"><?php echo esc_html($song->song_artist ?: '未知艺术家'); ?></span>
                                        </div>
                                        <div class="play-stats">
                                            <span class="play-count"><?php echo number_format($song->play_count); ?>次</span>
                                            <span class="play-duration"><?php echo self::format_duration($song->total_duration); ?></span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="no-data">暂无数据，播放歌曲后统计会显示在这里</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- 用户排行面板 -->
                    <div id="users-panel" class="tab-panel">
                        <?php if (!empty($top_users)): ?>
                            <ul class="stats-list">
                                <?php foreach ($top_users as $index => $user): ?>
                                    <li class="stats-item" style="margin-bottom: 0px;">
                                        <div class="rank-icon" style="margin-right: 4px;">
                                            <?php if ($index < 3): ?>
                                                <span class="medal-icon">
                                                    <?php echo ['🥇', '🥈', '🥉'][$index]; ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #999;"><?php echo $index + 1; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="item-info" style="margin-right: 8px;">
                                            <span class="item-title"><?php echo esc_html($user->user_name); ?></span>
                                        </div>
                                        <div class="item-stats">
                                            <span class="play-count"><?php echo number_format($user->play_count); ?>次</span>
                                            <span class="play-duration"><?php echo self::format_duration($user->total_duration); ?></span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="no-data">暂无数据</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// 初始化统计模块
function paper_music_stats_init() {
    return PaperMusicStats::get_instance();
}
add_action('init', 'paper_music_stats_init', 10);
