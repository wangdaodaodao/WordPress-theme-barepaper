<?php
if (!defined('ABSPATH')) exit;

/**
 * ===========================================
 * Paper WP 数据库维护系统 (V3.0 - 安全优化版)
 * ===========================================
 *
 * 🎯 模块目标
 *   - 安全地维护数据库健康状态
 *   - 清理过期和无用数据
 *   - 提供数据库性能监控
 *
 * 🖼️ 功能特性
 *   - 安全的每日数据清理
 *   - 数据库统计信息更新
 *   - 性能监控和管理通知
 *   - 完全移除危险的查询修改
 *
 * ⚠️ 安全说明
 *   - 移除了所有危险的SQL查询修改
 *   - 移除了破坏性的缓存优化
 *   - 只保留安全的数据清理功能
 *
 * @author wangdaodao
 * @version 3.0.0 (Safe Maintenance Version)
 * @date 2025-10-29
 */
class Paper_WP_Database_Maintenance {
    private static $instance = null;

    // 常量定义
    const OPTION_LAST_MAINTENANCE = 'paper_wp_last_maintenance';

    // 可配置的时间间隔（天数）
    private $cleanup_intervals = [
        'revisions' => 30,      // 修订版本清理间隔
        'auto_drafts' => 7,     // 自动草稿清理间隔
        'trash_comments' => 30  // 垃圾评论清理间隔
    ];

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // 允许通过过滤器自定义清理间隔
        $this->cleanup_intervals = apply_filters('paper_wp_cleanup_intervals', $this->cleanup_intervals);
        $this->init();
    }

    /**
     * 初始化数据库维护系统
     */
    public function init() {
        // 定期清理优化 - 只保留安全的数据清理
        add_action('paper_wp_daily_cleanup', [$this, 'daily_maintenance']);

        // 管理通知
        add_action('admin_init', [$this, 'add_admin_notices']);
    }

    /**
     * 每日维护任务 - 只保留安全的数据清理
     */
    public function daily_maintenance() {
        global $wpdb;

        // 清理过期的transients - 使用更精确的JOIN查询
        // 先删除过期的timeout记录及其对应的transient记录
        $expired_timeouts = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options}
                 WHERE option_name LIKE %s AND option_value < %d",
                '_transient_timeout_%',
                current_time('timestamp')
            )
        );

        if (!empty($expired_timeouts)) {
            // 构建对应的transient名称
            $transient_names = array_map(function($timeout_name) {
                return str_replace('_transient_timeout_', '_transient_', $timeout_name);
            }, $expired_timeouts);

            // 批量删除过期的数据
            $placeholders = str_repeat('%s,', count($expired_timeouts) + count($transient_names)) . '%s';
            $all_names = array_merge($expired_timeouts, $transient_names);

            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name IN ($placeholders)",
                $all_names
            ));
        }

        // 清理旧的修订版本 - 使用可配置的时间间隔
        $revisions_interval = intval($this->cleanup_intervals['revisions']);
        if ($revisions_interval > 0) {
            $revisions = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_date < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $revisions_interval
            ));
            if (!empty($revisions)) {
                foreach ($revisions as $revision_id) {
                    wp_delete_post_revision($revision_id);
                }
            }
        }

        // 清理旧的自动草稿 - 使用可配置的时间间隔
        $drafts_interval = intval($this->cleanup_intervals['auto_drafts']);
        if ($drafts_interval > 0) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft' AND post_date < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $drafts_interval
            ));
        }

        // 清理垃圾评论 - 使用可配置的时间间隔
        $comments_interval = intval($this->cleanup_intervals['trash_comments']);
        if ($comments_interval > 0) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->comments} WHERE comment_approved = 'trash' AND comment_date < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $comments_interval
            ));
        }

        // 更新数据库统计信息 - 使用安全的ANALYZE TABLE而不是OPTIMIZE TABLE
        $wpdb->query("ANALYZE TABLE {$wpdb->posts}, {$wpdb->postmeta}, {$wpdb->options}");

        // 记录维护时间
        update_option(self::OPTION_LAST_MAINTENANCE, current_time('timestamp'));
    }

    /**
     * 添加管理通知
     */
    public function add_admin_notices() {
        // 检查是否需要显示维护提醒（每30天提醒一次）
        $last_maintenance = get_option(self::OPTION_LAST_MAINTENANCE, 0);
        $current_time = current_time('timestamp');

        if ($current_time - $last_maintenance > 30 * 24 * 3600 && current_user_can('manage_options')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-info is-dismissible">
                    <p><strong>Paper WP 数据库维护提醒：</strong>建议定期检查数据库健康状态。系统已自动执行每日维护任务。</p>
                </div>';
            });
        }
    }

    /**
     * 获取数据库性能统计
     */
    public function get_database_stats() {
        global $wpdb;

        $stats = [
            'total_posts' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'"),
            'total_comments' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = '1'"),
            'total_options' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options}"),
            'database_size' => $this->get_database_size(),
            'last_maintenance' => get_option(self::OPTION_LAST_MAINTENANCE, 0)
        ];

        return $stats;
    }

    /**
     * 获取数据库大小
     *
     * 注意：在某些共享主机环境中，数据库用户可能没有权限访问information_schema
     * 这时会返回'未知'，这是正常现象，不影响其他功能
     */
    private function get_database_size() {
        global $wpdb;

        $size = $wpdb->get_var("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
        ");

        return $size ? $size . ' MB' : '未知 (权限不足)';
    }
}

/**
 * 初始化数据库维护系统
 */
function paper_wp_init_database_maintenance() {
    Paper_WP_Database_Maintenance::get_instance()->init();
}
add_action('after_setup_theme', 'paper_wp_init_database_maintenance');

/**
 * 注册每日清理任务
 */
function paper_wp_schedule_daily_cleanup() {
    if (!wp_next_scheduled('paper_wp_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'paper_wp_daily_cleanup');
    }
}
add_action('wp', 'paper_wp_schedule_daily_cleanup');

/**
 * 主题停用时清理计划任务
 */
function paper_wp_clear_scheduled_events() {
    wp_clear_scheduled_hook('paper_wp_daily_cleanup');
}
add_action('switch_theme', 'paper_wp_clear_scheduled_events');
