<?php
if (!defined('ABSPATH')) exit;

/**
 * Paper WP 终极缓存系统 (Ultimate Cache System)
 * 结合了高级架构、版本号策略、精细化失效和统一管理
 */
class Paper_WP_Ultimate_Cache {
    private static $instance = null;
    private $cache_hits = 0;
    private $cache_misses = 0;
    private $cache_groups = [];

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 初始化缓存系统
     */
    public function init() {
        $this->register_cache_groups();
        $this->setup_cache_hooks();
        add_action('shutdown', [$this, 'log_cache_stats']);
        add_action('admin_init', [$this, 'add_admin_features']);
    }

    /**
     * 注册缓存组及其配置
     */
    private function register_cache_groups() {
        $this->cache_groups = [
            'posts'      => ['duration' => HOUR_IN_SECONDS * 1],
            'terms'      => ['duration' => HOUR_IN_SECONDS * 2],
            'users'      => ['duration' => MINUTE_IN_SECONDS * 30],
            'options'    => ['duration' => DAY_IN_SECONDS],
            'comments'   => ['duration' => HOUR_IN_SECONDS * 1],
            'preload'    => ['duration' => HOUR_IN_SECONDS * 6],
            'stats'      => ['duration' => MINUTE_IN_SECONDS * 1], // 统计缓存：在线统计、字数统计等（1分钟更新）
            'markdown_files' => ['duration' => 0], // 文件缓存，不依赖数据库过期
        ];
    }

    /**
     * 设置所有缓存清理的钩子
     */
    public function setup_cache_hooks() {
        // 文章相关
        add_action('save_post', [$this, 'clear_single_post_cache']);
        add_action('delete_post', [$this, 'clear_single_post_cache']);

        // 分类/标签相关
        add_action('created_term', [$this, 'clear_term_cache'], 10, 3);
        add_action('edited_term', [$this, 'clear_term_cache'], 10, 3);
        add_action('delete_term', [$this, 'clear_term_cache'], 10, 3);

        // 评论相关
        add_action('wp_insert_comment', [$this, 'clear_comment_cache'], 10, 2);
        add_action('wp_set_comment_status', [$this, 'clear_comment_cache'], 10, 2);
        add_action('edit_comment', [$this, 'clear_comment_cache']);
        add_action('delete_comment', [$this, 'clear_comment_cache']);

        // 用户相关
        add_action('profile_update', [$this, 'clear_user_cache']);
        add_action('user_register', [$this, 'clear_user_cache']);
        add_action('delete_user', [$this, 'clear_user_cache']);

        // 选项相关 (精细化判断)
        add_action('updated_option', [$this, 'clear_option_cache'], 10, 1);

        // 全局清理事件 (文件缓存)
        add_action('switch_theme', [$this, 'flush_file_cache']);
        add_action('activated_plugin', [$this, 'flush_file_cache']);
        add_action('deactivated_plugin', [$this, 'flush_file_cache']);
        add_action('upgrader_process_complete', [$this, 'flush_file_cache']);

        // 缓存预热
        add_action('wp_loaded', [$this, 'preload_common_cache']);
    }

    // --- 核心缓存 API ---

    /**
     * 获取缓存，如果不存在则通过回调生成
     */
    public function get($key, $group = 'default', $callback = null, $args = []) {
        $cache_key = $this->build_versioned_key($key, $group);

        // 1. 尝试从对象缓存获取
        $value = wp_cache_get($cache_key, 'paper_wp');
        if (false !== $value) {
            $this->cache_hits++;
            return $value;
        }

        // 2. 尝试从瞬态缓存获取 (作为持久化备份)
        $value = get_transient($cache_key);
        if (false !== $value) {
            $this->cache_hits++;
            wp_cache_set($cache_key, $value, 'paper_wp', $this->get_cache_duration($group)); // 预热对象缓存
            return $value;
        }

        // 3. 缓存未命中，通过回调生成
        $this->cache_misses++;
        if ($callback && is_callable($callback)) {
            $data = call_user_func_array($callback, $args);
            $this->set($key, $data, $group);
            return $data;
        }

        return false;
    }

    /**
     * 设置缓存
     */
    public function set($key, $data, $group = 'default', $duration = null) {
        $cache_key = $this->build_versioned_key($key, $group);
        $duration = $duration ?? $this->get_cache_duration($group);

        wp_cache_set($cache_key, $data, 'paper_wp', $duration);
        set_transient($cache_key, $data, $duration);
    }

    /**
     * 删除单个缓存
     */
    public function delete($key, $group = 'default') {
        $cache_key = $this->build_versioned_key($key, $group);
        wp_cache_delete($cache_key, 'paper_wp');
        delete_transient($cache_key);
    }

    /**
     * 【核心优化】通过递增版本号来使整个组的缓存失效 (使用原子操作)
     */
    public function flush_group($group) {
        // 确保版本号键存在，然后再递增
        $version_key = 'group_version';
        $current_version = wp_cache_get($version_key, $group);
        
        if ($current_version === false) {
            // 如果键不存在，先初始化为1
            wp_cache_add($version_key, 1, $group, 0);
        } else {
            // 使用原子递增操作，避免竞态条件
            if (wp_cache_supports('incr')) {
                // 如果后端支持原子递增，使用wp_cache_incr（完全线程安全）
                $result = wp_cache_incr($version_key, 1, $group);
                // 如果递增失败（键不存在），则设置为1
                if ($result === false) {
                    wp_cache_set($version_key, 1, $group, 0);
                }
            } else {
                // 如果后端不支持原子递增，回退到原有逻辑
                $version = intval($current_version);
                wp_cache_set($version_key, $version + 1, $group, 0);
            }
        }

        $this->log_cache_clear($group, 'all');
        return true;
    }

    /**
     * 获取缓存组列表（公共方法，用于外部访问）
     */
    public function get_cache_groups() {
        return $this->cache_groups;
    }

    /**
     * 获取数据库缓存组列表（排除文件缓存组）
     */
    public function get_database_cache_groups() {
        $groups = array_keys($this->cache_groups);
        // 移除文件缓存组，因为它是文件系统缓存，不是数据库缓存
        return array_filter($groups, function($group) {
            return $group !== 'markdown_files';
        });
    }

    // --- 缓存键和版本管理 ---

    /**
     * 获取一个组的当前版本号
     */
    private function get_group_version($group) {
        $version = wp_cache_get('group_version', $group);
        if (false === $version) {
            // 初始化版本号为1，使用wp_cache_add确保只在键不存在时设置
            $version = 1;
            wp_cache_add('group_version', $version, $group, 0); // 永久有效
        }
        return intval($version);
    }

    /**
     * 构建带版本号的缓存键
     */
    private function build_versioned_key($key, $group) {
        $version = $this->get_group_version($group);
        return $group . '_' . $version . '_' . $key;
    }

    private function get_cache_duration($group) {
        return $this->cache_groups[$group]['duration'] ?? HOUR_IN_SECONDS;
    }

    // --- 精细化缓存失效逻辑 ---

    /**
     * 【精细化】只清理单个文章相关的缓存
     */
    public function clear_single_post_cache($post_id) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        // 删除文章内容缓存
        $this->delete('post_content_' . $post_id, 'posts');
        // 删除文章的Markdown文件缓存
        $this->delete_markdown_file_cache($post_id);
        // 如果文章分类/标签有变动，可以考虑清理terms组
        // $this->flush_group('terms'); // 较为粗暴，但确保一致性

        $this->log_cache_clear('posts', $post_id);
    }

    public function clear_term_cache($term_id, $tt_id, $taxonomy) {
        $this->delete('term_archive_' . $term_id, 'terms');
        $this->log_cache_clear('terms', $term_id);
    }

    public function clear_comment_cache($comment_id) {
        $comment = get_comment($comment_id);
        if ($comment) {
            $this->delete('post_comments_' . $comment->comment_post_ID, 'comments');
            // 清理侧栏缓存（因为评论会影响评论排行）
            if (function_exists('paper_wp_clear_sidebar_cache')) {
                paper_wp_clear_sidebar_cache();
            }
        }
    }

    public function clear_user_cache($user_id) {
        $this->delete('user_profile_' . $user_id, 'users');
    }

    public function clear_option_cache($option_name) {
        if (strpos($option_name, 'paper_wp_') === 0) {
            $this->flush_group('options');
        }
    }

    // --- 文件缓存管理 ---

    /**
     * 【统一管理】删除特定文章的Markdown文件缓存
     */
    public function delete_markdown_file_cache($post_id) {
        $cache_dir = get_template_directory() . '/cache';
        if (!is_dir($cache_dir)) return;

        // 文件名模式可以根据实际生成规则来定
        $pattern = $cache_dir . '/md_cache_'. $post_id . '_*.html';
        $files = glob($pattern);
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) unlink($file);
            }
        }
        $this->log_cache_clear('markdown_files', $post_id);
    }

    /**
     * 【统一管理】清空所有文件缓存（性能优化版本）
     */
    public function flush_file_cache() {
        $cache_dir = get_template_directory() . '/cache';
        if (!is_dir($cache_dir)) {
            return;
        }

        // 使用更高效的方式删除文件
        // 先获取所有HTML文件，然后批量删除
        $files = glob($cache_dir . '/*.html', GLOB_NOSORT);
        
        if ($files && is_array($files)) {
            // 对于大量文件，分批处理以避免内存问题
            $batch_size = 100;
            $total_files = count($files);
            
            for ($i = 0; $i < $total_files; $i += $batch_size) {
                $batch = array_slice($files, $i, $batch_size);
                foreach ($batch as $file) {
                    if (is_file($file) && is_writable($file)) {
                        @unlink($file);
                    }
                }
                
                // 每处理一批后，释放内存
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }
            
            // 清除文件系统缓存
            clearstatcache();
        }
        
        $this->log_cache_clear('markdown_files', 'all');
    }

    // --- 缓存预热、统计和管理后台 ---

    public function preload_common_cache() {
        // 预热常用数据
        if (!wp_cache_get('site_name', 'preload')) {
            $this->set('site_name', get_bloginfo('name'), 'preload');
        }
    }

    public function log_cache_clear($group, $object_id) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Paper WP Cache: Cleared {$group} cache for object {$object_id}");
        }
    }

    public function log_cache_stats() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $total = $this->cache_hits + $this->cache_misses;
            $hit_rate = $total > 0 ? round(($this->cache_hits / $total) * 100, 2) : 0;
            error_log("Paper WP Cache Stats: Hits: {$this->cache_hits}, Misses: {$this->cache_misses}, Hit Rate: {$hit_rate}%");
        }
    }

    public function get_cache_stats() {
        $total = $this->cache_hits + $this->cache_misses;
        return [
            'hits' => $this->cache_hits,
            'misses' => $this->cache_misses,
            'total' => $total,
            'hit_rate' => $total > 0 ? round(($this->cache_hits / $total) * 100, 2) : 0
        ];
    }

    public function add_admin_features() {
        add_action('admin_menu', [$this, 'add_cache_admin_page']);
        // 注册admin-post钩子来处理表单提交
        add_action('admin_post_paper_wp_clear_cache', [$this, 'handle_cache_clear_request']);
    }

    public function add_cache_admin_page() {
        add_options_page(
            'Paper WP 缓存管理',
            '缓存管理',
            'manage_options',
            'paper-wp-cache',
            [$this, 'render_cache_admin_page']
        );
    }

    public function render_cache_admin_page() {
        $stats = $this->get_cache_stats();
        ?>
        <div class="wrap">
            <h1>Paper WP 缓存管理系统</h1>

            <div class="card">
                <h2>缓存统计</h2>
                <p>缓存命中: <strong><?php echo $stats['hits']; ?></strong></p>
                <p>缓存未命中: <strong><?php echo $stats['misses']; ?></strong></p>
                <p>总请求数: <strong><?php echo $stats['total']; ?></strong></p>
                <p>命中率: <strong><?php echo $stats['hit_rate']; ?>%</strong></p>
            </div>

            <div class="card">
                <h2>缓存管理</h2>
                <!-- 使用admin-post.php处理表单提交 -->
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="paper_wp_clear_cache">
                    <?php wp_nonce_field('paper_wp_clear_cache_action'); ?>
                    <p>
                        <button type="submit" name="clear_all_cache" class="button button-primary" value="1">清理所有缓存</button>
                        <button type="submit" name="clear_file_cache" class="button" value="1">仅清理文件缓存</button>
                    </p>
                </form>
            </div>

            <?php if (isset($_GET['cache_cleared'])): ?>
            <div class="notice notice-success is-dismissible">
                <p>缓存已成功清理！</p>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handle_cache_clear_request() {
        // 安全检查
        if (!current_user_can('manage_options') || !check_admin_referer('paper_wp_clear_cache_action')) {
            wp_die('权限不足或安全验证失败');
        }

        if (isset($_POST['clear_file_cache'])) {
            // 仅清理文件缓存
            $this->flush_file_cache();
            $message = '文件缓存清理完成';
        } else {
            // 清理所有缓存
            // 只清理数据库缓存组（排除markdown_files，因为它是文件缓存）
            $database_groups = $this->get_database_cache_groups();
            foreach ($database_groups as $group) {
                $this->flush_group($group);
            }
            
            // 清理文件缓存
            $this->flush_file_cache();
            
            // 清理侧栏缓存（确保排行榜缓存被清理）
            if (function_exists('paper_wp_clear_sidebar_cache')) {
                paper_wp_clear_sidebar_cache();
            }
            
            // 清理WordPress原生对象缓存
            wp_cache_flush();
            
            $message = '所有缓存清理完成';
        }

        // 记录操作日志
        if (function_exists('paper_wp_log_cache_operation')) {
            $action = isset($_POST['clear_file_cache']) ? 'file' : 'all';
            paper_wp_log_cache_operation($action, $message);
        }

        // 重定向回设置页面
        wp_redirect(admin_url('options-general.php?page=paper-wp-cache&cache_cleared=1'));
        exit;
    }
}

/**
 * 初始化缓存系统
 */
function paper_wp_init_ultimate_cache() {
    Paper_WP_Ultimate_Cache::get_instance()->init();
}
add_action('after_setup_theme', 'paper_wp_init_ultimate_cache');

/**
 * 便捷缓存函数 - 全局可用
 */
function paper_wp_cache_get($key, $group = 'default', $callback = null, $args = []) {
    return Paper_WP_Ultimate_Cache::get_instance()->get($key, $group, $callback, $args);
}

function paper_wp_cache_set($key, $data, $group = 'default', $duration = null) {
    return Paper_WP_Ultimate_Cache::get_instance()->set($key, $data, $group, $duration);
}

function paper_wp_cache_delete($key, $group = 'default') {
    return Paper_WP_Ultimate_Cache::get_instance()->delete($key, $group);
}

function paper_wp_cache_flush_group($group) {
    return Paper_WP_Ultimate_Cache::get_instance()->flush_group($group);
}

/**
 * 获取缓存统计信息
 */
function paper_wp_cache_get_stats() {
    return Paper_WP_Ultimate_Cache::get_instance()->get_cache_stats();
}

/**
 * 清理所有缓存组
 */
function paper_wp_cache_flush_all() {
    $cache = Paper_WP_Ultimate_Cache::get_instance();
    
    // 只清理数据库缓存组（排除markdown_files文件缓存组）
    $database_groups = $cache->get_database_cache_groups();
    foreach ($database_groups as $group) {
        $cache->flush_group($group);
    }
    
    // 清理文件缓存
    $cache->flush_file_cache();
    
    // 清理侧栏缓存
    if (function_exists('paper_wp_clear_sidebar_cache')) {
        paper_wp_clear_sidebar_cache();
    }
    
    // 清理WordPress原生对象缓存
    wp_cache_flush();
    
    return true;
}

/**
 * 记录缓存清理操作日志（优化版本）
 */
function paper_wp_log_cache_operation($group, $message) {
    static $current_user_login = null;

    // 缓存当前用户信息，避免重复查询
    if ($current_user_login === null) {
        $current_user = wp_get_current_user();
        $current_user_login = $current_user->user_login ?? 'system';
    }

    // 仅在调试模式下记录到错误日志
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf(
            '[%s] Paper WP Cache: %s (Group: %s, User: %s)',
            current_time('Y-m-d H:i:s'),
            $message,
            $group,
            $current_user_login
        ));
    }

    // 批量记录到WordPress选项中（减少数据库写入频率）
    static $log_buffer = [];
    static $last_save_time = 0;

    $log_buffer[] = [
        'time' => current_time('timestamp'),
        'group' => $group,
        'message' => $message,
        'user' => $current_user_login
    ];

    // 每5秒或缓冲区达到10条记录时批量保存
    $current_time = time();
    if (count($log_buffer) >= 10 || ($current_time - $last_save_time) >= 5) {
        $existing_log = get_option('paper_wp_cache_operation_log', []);
        $existing_log = array_merge($existing_log, $log_buffer);

        // 只保留最近50条记录
        if (count($existing_log) > 50) {
            $existing_log = array_slice($existing_log, -50);
        }

        update_option('paper_wp_cache_operation_log', $existing_log);

        // 清空缓冲区
        $log_buffer = [];
        $last_save_time = $current_time;
    }
}

/**
 * 获取缓存清理操作日志
 */
function paper_wp_get_cache_operation_log($limit = 20) {
    $log = get_option('paper_wp_cache_operation_log', []);
    return array_slice(array_reverse($log), 0, $limit);
}
