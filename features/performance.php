<?php
if (!defined('ABSPATH')) exit;

/**
 * ===========================================
 * Paper WP 性能优化器类
 *
 * @version 2.0
 * @author  Gemini
 * ===========================================
 */
class Paper_WP_Performance_Optimizer {
    
    /** @var string 性能设置在数据库中的选项名 */
    private const OPTION_NAME = 'paper_wp_performance_settings';

    /** @var array|null 优化配置数组 */
    private $config;

    /** @var self|null 单例实例 */
    private static $instance = null;

    /**
     * 构造函数设为私有，防止直接创建对象
     */
    private function __construct() {
        // 构造函数只负责初始化，不执行具体逻辑
    }

    /**
     * 防止实例被克隆
     */
    private function __clone() {}

    /**
     * 防止反序列化
     */
    public function __wakeup() {}

    /**
     * 获取单例实例
     * @return self
     */
    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 运行优化器 - 在主题环境准备好后调用
     */
    public function run(): void {
        // 延迟加载配置，仅在需要时读取数据库
        if ($this->config === null) {
            $this->config = $this->get_optimization_config();
        }
        $this->init_hooks();
    }

    /**
     * 资源优化钩子
     */
    private function init_hooks(): void {
        // 资源优化钩子
        add_action('wp_head', [$this, 'add_resource_hints'], 1);
        add_action('wp_head', [$this, 'optimize_fonts'], 2);
        add_action('wp_footer', [$this, 'register_service_worker']);

        // Service Worker钩子
        add_filter('query_vars', [$this, 'add_sw_query_var']);
        add_action('template_redirect', [$this, 'serve_sw_file']);

        // 数据库优化钩子
        add_action('init', [$this, 'optimize_db_queries']);
        
        // 脚本优化钩子
        if ($this->config['defer_scripts']) {
            add_filter('script_loader_tag', [$this, 'add_defer_attribute_to_scripts'], 10, 2);
        }

        // 资源移除钩子
        add_action('wp_enqueue_scripts', [$this, 'remove_unnecessary_resources'], 100);
        
        // 功能禁用钩子
        add_action('init', [$this, 'disable_emojis']);
        add_filter('xmlrpc_enabled', '__return_false');
    }

    /**
     * 资源提示优化 (Resource Hints)
     * 优化: 移除了对本地域名无效的 dns-prefetch。DNS预取应用于第三方域名。
     */
    public function add_resource_hints(): void {
        // 示例: 如果你使用了CDN或外部字体服务，可以像这样添加预连接
        // echo '<link rel="preconnect" href="https://cdn.example.com" crossorigin>';
        // echo '<link rel="dns-prefetch" href="//cdn.example.com">';
        // 对于低性能服务器，目标是减少外部依赖，所以这里通常为空。
    }

    /**
     * 低性能服务器字体优化
     */
    public function optimize_fonts(): void {
        // 低性能服务器：完全使用本地字体，避免外部请求
        echo '<style>
            /* 低性能服务器字体优化 */
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                font-display: swap;
            }

            /* 减少布局偏移 */
            img {
                max-width: 100%;
                height: auto;
                loading: lazy;
            }

            /* 优化滚动性能 */
            * {
                box-sizing: border-box;
            }

            html {
                scroll-behavior: smooth;
            }

            /* 低性能服务器：禁用复杂动画 */
            * {
                animation-duration: 0.1s !important;
                transition-duration: 0.1s !important;
            }
        </style>';
    }

    /**
     * 注册 Service Worker
     */
    public function register_service_worker(): void {
        // 功能正在开发中
        // if ($this->config['enable_service_worker'] && !is_admin() && is_ssl()) {
        //     echo '<script>...</script>';
        // }
    }
    
    /**
     * 添加 Service Worker 的查询变量
     * @param array $vars
     * @return array
     */
    public function add_sw_query_var(array $vars): array {
        $vars[] = 'paper_wp_sw';
        return $vars;
    }
    
    /**
     * 处理 Service Worker 文件请求 (sw.js)
     */
    public function serve_sw_file(): void {
        // 功能正在开发中
        // if (get_query_var('paper_wp_sw')) {
        //     header('Content-Type: application/javascript; charset=utf-8');
        //     $sw_content = <<<JS ... JS;
        //     echo $sw_content;
        //     exit;
        // }
    }
    
    /**
     * 激活主题时添加重写规则（静态方法）
     */
    public static function add_sw_rewrite_rule(): void {
        add_rewrite_rule('^sw\.js$', 'index.php?paper_wp_sw=true', 'top');
    }
    
    /**
     * 数据库查询优化
     */
    public function optimize_db_queries(): void {
        // 功能正在开发中
        // if ($this->config['enable_query_optimization']) {
        //     add_filter('pre_get_posts', [$this, 'aggressive_query_optimization']);
        //     add_filter('get_terms_args', [$this, 'aggressive_terms_optimization'], 10, 2);
        //     add_filter('pre_get_users', [$this, 'aggressive_user_optimization']);
        // }
    }

    /**
     * 激进的文章查询优化
     * 优化: 使用 pre_get_posts 钩子，这是修改主查询的正确方式。
     * 修正: 移除了会破坏分页的 `LIMIT 10`。改为确保查询使用后台设置的文章数，防止被其他插件篡改。
     * @param WP_Query $query
     */
    public function aggressive_query_optimization(WP_Query $query): void {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        // 对于非文章/页面详情页（如首页、分类、标签页），强制使用WP的默认设置
        if (!$query->is_singular()) {
            $query->set('posts_per_page', get_option('posts_per_page'));
            // 减少不必要的字段查询，提升速度
            $query->set('update_post_meta_cache', false);
            $query->set('update_post_term_cache', false);
        }
    }
    
    /**
     * 激进的分类法查询优化
     * @param array $args
     * @return array
     */
    public function aggressive_terms_optimization(array $args): array {
        $args['number'] = 20;
        $args['hide_empty'] = true;
        $args['cache_domain'] = 'paper_wp_aggressive';
        return $args;
    }

    /**
     * 激进的用户查询优化
     * @param WP_User_Query $query
     * @return WP_User_Query
     */
    public function aggressive_user_optimization(WP_User_Query $query): WP_User_Query {
        $query->query_vars['number'] = 10;
        $query->query_vars['fields'] = ['ID', 'display_name'];
        return $query;
    }

    /**
     * 为脚本添加 defer 属性
     * @param string $tag
     * @param string $handle
     * @return string
     */
    public function add_defer_attribute_to_scripts(string $tag, string $handle): string {
        // 排除 jQuery 核心库和后台脚本
        if (is_admin() || in_array($handle, ['jquery-core', 'jquery-migrate'], true)) {
            return $tag;
        }
        return str_replace(' src=', ' defer src=', $tag);
    }

    /**
     * 获取优化配置
     * @return array
     */
    private function get_optimization_config(): array {
        $settings = get_option(self::OPTION_NAME, []);

        // 功能正在开发中
        // 所有性能优化选项默认关闭
        return [
            'defer_scripts'             => true, // 默认开启 defer
            'enable_service_worker'     => false,
            'enable_query_optimization' => false,
            'remove_block_styles'       => !empty($settings['remove_block_styles']),
            'remove_classic_styles'     => !empty($settings['remove_classic_styles']),
            'disable_emojis'            => !empty($settings['disable_emojis']),
        ];
    }
    
    /**
     * 移除不必要的资源
     */
    public function remove_unnecessary_resources(): void {
        if ($this->config['remove_block_styles']) {
            wp_dequeue_style('wp-block-library');
            wp_dequeue_style('wp-block-library-theme');
            wp_dequeue_style('wc-blocks-style');
        }
        if ($this->config['remove_classic_styles']) {
            wp_dequeue_style('classic-theme-styles');
        }
        wp_dequeue_style('global-styles');
    }

    /**
     * 禁用 Emoji
     */
    public function disable_emojis(): void {
        if (!$this->config['disable_emojis']) return;

        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        add_filter('tiny_mce_plugins', function($plugins) {
            return is_array($plugins) ? array_diff($plugins, ['wpemoji']) : [];
        });
        add_filter('wp_resource_hints', function($urls, $relation_type) {
            if ('dns-prefetch' === $relation_type) {
                return array_filter($urls, fn($url) => strpos($url, 's.w.org/images/core/emoji/') === false);
            }
            return $urls;
        }, 10, 2);
    }
}

/**
 * ===========================================
 * 全局函数和初始化
 * ===========================================
 */

/**
 * 初始化性能优化器
 */
function paper_wp_init_performance_optimizer(): void {
    Paper_WP_Performance_Optimizer::get_instance()->run();
}
add_action('after_setup_theme', 'paper_wp_init_performance_optimizer');

/**
 * 主题切换时设置 Service Worker 重写规则
 */
function paper_wp_theme_activation(): void {
    Paper_WP_Performance_Optimizer::add_sw_rewrite_rule();
    flush_rewrite_rules(); // 仅在激活时执行，是正确的做法
}
add_action('after_switch_theme', 'paper_wp_theme_activation');

/**
 * 修改主页文章预览字数
 * 优化: 2000 个字符太长了，可能导致内存问题和页面加载缓慢。调整为150个字符。
 */
add_filter('excerpt_length', fn() => 2000, 999);

/**
 * 获取文章字数统计 (保持不变，但注意这是字符数)
 * @return string
 */
function get_post_word_count(): string {
    $content = get_post_field('post_content', get_the_ID());
    // 注意: mb_strlen 计算的是字符数，不是单词数
    return mb_strlen(strip_tags($content), 'UTF-8') . '字';
}
