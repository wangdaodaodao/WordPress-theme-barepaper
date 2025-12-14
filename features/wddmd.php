<?php
/**
 * ===========================================
 * Markdown 和 Shortcode 解析器调度中心
 * ===========================================
 *
 * 📌 核心作用
 *   这个文件是 Markdown 功能的"总开关"和"调度器"
 *   负责加载解析器并在文章显示时自动转换 Markdown 语法为 HTML
 *
 * 🔧 工作流程
 *   1. 检查后台设置是否启用 Markdown 功能
 *   2. 加载统一内容解析器 (content-parser.php)
 *   3. 注册内容过滤器,在文章输出前处理内容
 *   4. 将 Markdown 语法转换为 HTML
 *   5. 处理短代码 (如 [alert]、[button] 等)
 *   6. 加载 Markdown 样式文件
 * 
 * @author wangdaodao
 * @version 3.0.0
 * @date 2025-10-23
 */

if (!defined('ABSPATH')) exit;

/**
 * WDDMD 接口层 - 解析器调度中心
 */
class WDDMD_Core {

    private static $instance = null;
    private $parser = null;

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
        $this->init();
    }

    /**
     * 初始化
     */
    private function init() {
        // 检查功能是否启用
        if (!$this->is_enabled()) {
            return;
        }

        // 加载解析器
        $this->load_parser();

        // 注册内容过滤器（在wpautop之前运行）
        add_filter('the_content', [$this, 'process_content'], 1);

        // 加载样式
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
    }

    /**
     * 加载解析器
     */
    private function load_parser() {
        $parser_file = get_template_directory() . '/features/content-parser.php';
        if (file_exists($parser_file)) {
            require_once $parser_file;
            if (class_exists('Content_Parser_Unified')) {
                $this->parser = new Content_Parser_Unified();
            }
        }
    }

    /**
     * 检查功能是否启用
     * 
     * 从后台"编辑器设置"中读取"启用Markdown和shortcode语法支持"选项
     * 只有启用后才会加载解析器和注册内容过滤器
     * 
     * @return bool 是否启用Markdown解析功能
     */
    private function is_enabled() {
        return Paper_Settings_Manager::is_enabled('paper_wp_editor_settings', 'enable_wddmds');
    }

    /**
     * 处理内容 - Markdown解析和短代码处理
     */
    public function process_content($content) {
        if (is_admin() || !$this->is_enabled() || !$this->parser) {
            return $content;
        }

        // 解码HTML实体以正确处理Markdown语法
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5);

        // 确保内容不为空
        if (empty($content)) {
            return $content;
        }

        // 先应用Markdown解析
        $content = $this->parser->parse($content);

        // 然后处理短代码
        $content = do_shortcode($content);

        return $content;
    }

    /**
     * 加载样式
     * 
     * 加载 Markdown 渲染所需的样式文件(image-markdown.css)
     * 该样式文件包含:
     * - Markdown 图片的响应式布局
     * - 代码块的语法高亮样式
     * - 表格、引用块等元素的美化样式
     * - 任务列表的复选框样式
     * 
     * 使用主题版本号作为缓存版本,确保主题更新时自动刷新样式
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'markdown', 
            get_template_directory_uri() . '/css/markdown.css', 
            [], 
            BAREPAPER_VERSION
        );
    }
}

/**
 * 初始化WDDMD接口层
 */
function wddmd_init() {
    return WDDMD_Core::get_instance();
}

// 启动模块
add_action('init', 'wddmd_init', 5);
