<?php
/**
 * 短代码模块 - 各种内容展示
 *
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * 短代码模块主类
 */
class Shortcodes_Module {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init();
    }

    private function init() {
        if (!$this->is_enabled()) return;
        $this->register_shortcodes();
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
        // 在 wpautop 之后清理 alert 短代码中的空段落标签（优先级较高，确保在其他过滤器之后运行）
        add_filter('the_content', [$this, 'clean_alert_empty_paragraphs'], 99);
    }
    
    /**
     * 清理 alert 短代码中的空段落标签
     * 使用DOMDocument进行更安全和高效的HTML处理
     */
    public function clean_alert_empty_paragraphs($content) {
        // 如果内容为空，直接返回
        if (empty($content)) {
            return $content;
        }

        // 使用DOMDocument进行HTML解析和处理
        $dom = new DOMDocument();

        // 启用内部错误处理，避免解析失败时输出警告
        libxml_use_internal_errors(true);

        // 尝试解析HTML，如果失败则返回原始内容
        $parse_success = $dom->loadHTML(
            '<?xml encoding="utf-8"?><div id="content-wrapper">' . $content . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        if (!$parse_success) {
            libxml_clear_errors();
            return $content;
        }

        // 创建XPath对象用于查询
        $xpath = new DOMXPath($dom);

        // 查找所有空的段落元素
        $emptyParagraphs = $xpath->query(
            '//p[normalize-space()="" or normalize-space()=normalize-space(./comment())]'
        );

        // 移除空段落
        foreach ($emptyParagraphs as $paragraph) {
            if ($this->isParagraphSafeToRemove($paragraph)) {
                $paragraph->parentNode->removeChild($paragraph);
            }
        }

        // 查找并移除WordPress块编辑器的空段落注释
        $blockEditorComments = $xpath->query(
            '//comment()[contains(., "/wp:paragraph")]'
        );

        foreach ($blockEditorComments as $comment) {
            $nextSibling = $comment->nextSibling;
            // 如果注释后面紧跟着空段落，一起移除
            if ($nextSibling && $nextSibling->nodeName === 'p' &&
                trim($nextSibling->textContent) === '') {
                $comment->parentNode->removeChild($comment);
                $comment->parentNode->removeChild($nextSibling);
            }
        }

        // 获取处理后的内容
        $contentWrapper = $dom->getElementById('content-wrapper');
        if ($contentWrapper) {
            $new_content = '';
            foreach ($contentWrapper->childNodes as $node) {
                $new_content .= $dom->saveHTML($node);
            }
        } else {
            $new_content = $dom->saveHTML();
        }

        // 清理并返回结果
        libxml_clear_errors();
        return trim($new_content);
    }

    /**
     * 检查段落是否可以安全移除
     */
    private function isParagraphSafeToRemove(DOMElement $paragraph) {
        // 检查段落是否有重要的属性（如ID、类等）
        if ($paragraph->hasAttribute('id') || $paragraph->hasAttribute('style')) {
            return false;
        }

        // 检查段落内容是否真的为空（忽略空白字符）
        $textContent = trim($paragraph->textContent);
        if (!empty($textContent)) {
            return false;
        }

        // 检查是否包含非文本子节点（如图片、链接等）
        foreach ($paragraph->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                // 如果包含非空元素，不移除
                return false;
            }
        }

        return true;
    }

    private function is_enabled() {
        return true;
    }

    private function register_shortcodes() {
        add_shortcode('video', [$this, 'render_video']);
        add_shortcode('alert', [$this, 'render_alert']);
        add_shortcode('button', [$this, 'render_button']);
        add_shortcode('quote', [$this, 'render_quote']);
        add_shortcode('heading', [$this, 'render_heading']);
        add_shortcode('ai_summary', [$this, 'render_ai_summary']);
        add_shortcode('music', [$this, 'render_music']);
        add_shortcode('book', [$this, 'render_book']);
        add_shortcode('fullimage', [$this, 'render_fullimage']);
        add_shortcode('gallery', [$this, 'render_gallery']);
    }

    public function enqueue_styles() {
        wp_enqueue_style('shortcodes', get_template_directory_uri() . '/css/shortcode.css', [], time() + mt_rand(1, 999999));
    }

    /**
     * 视频短代码
     * 用法: [video src="video-url" poster="poster-url" width="100%" height="auto"]
     */
    public function render_video($atts) {
        $atts = shortcode_atts([
            'src' => '',
            'poster' => '',
            'width' => '100%',
            'height' => 'auto'
        ], $atts);

        if (empty($atts['src'])) {
            return '<p class="shortcode-error">视频地址不能为空</p>';
        }

        $src = esc_url($atts['src']);
        $poster = !empty($atts['poster']) ? 'poster="' . esc_url($atts['poster']) . '"' : '';
        $width = esc_attr($atts['width']);
        $height = esc_attr($atts['height']);

        return '<div class="shortcode-video-wrapper" style="width: ' . $width . '; height: ' . $height . ';">' .
               '<video controls class="shortcode-video" ' . $poster . '>' .
               '<source src="' . $src . '" type="video/mp4">' .
               '您的浏览器不支持 video 标签。' .
               '</video>' .
               '</div>';
    }



    /**
     * 警告框短代码 - 使用4.5.8版本的HTML结构
     * 用法: [alert type="info" title="提示"]内容[/alert]
     */
    public function render_alert($atts, $content = null) {
        $atts = shortcode_atts(['type' => 'info', 'title' => ''], $atts);

        if (is_null($content)) {
            return '';
        }

        $type_map = ['info' => 'info', 'warning' => 'alert', 'success' => 'success', 'error' => 'error'];
        $type_class = $type_map[$atts['type']] ?? 'info';
        $title_html = !empty($atts['title']) ? '<div class="title">' . esc_html($atts['title']) . '</div>' : '';
        $content_class = empty($atts['title']) ? 'content center' : 'content';

        // 先处理内容，移除可能的段落包装
        $processed_content = do_shortcode($content);

        $output = '<div class="custom-container ' . $type_class . '">' . $title_html . '<div class="' . $content_class . '">' . $processed_content . '</div></div>';

        // 空段落清理现在由 clean_alert_empty_paragraphs 方法统一处理
        return $output;
    }

    /**
     * 按钮短代码 - 防止wpautop干扰
     * 用法: [button url="https://example.com" color="primary"]按钮文本[/button]
     */
    public function render_button($atts, $content = null) {
        $atts = shortcode_atts(['url' => '#', 'color' => 'primary'], $atts);

        if (is_null($content)) {
            return '';
        }

        $url = esc_url($atts['url']);
        $color = esc_attr($atts['color']);

        // 验证颜色参数
        $valid_colors = ['primary', 'secondary', 'success', 'warning', 'danger'];
        if (!in_array($color, $valid_colors)) {
            $color = 'primary';
        }

        // 使用span包装来防止wpautop的段落包装
        // 注意：这里不调用do_shortcode，因为内容已经在Markdown处理阶段被处理过了
        return '<span class="paper-btn-wrapper"><a href="' . $url . '" class="paper-btn btn-' . $color . '" target="_blank" rel="noopener noreferrer">' . $content . '</a></span>';
    }

    /**
     * 引用短代码 - 使用4.5.8版本的HTML结构
     * 用法: [quote author="作者名"]引用内容[/quote]
     */
    public function render_quote($atts, $content = null) {
        $atts = shortcode_atts(['author' => ''], $atts);

        if (is_null($content)) {
            return '';
        }

        $author_html = !empty($atts['author']) ? '<cite>—— ' . esc_html($atts['author']) . '</cite>' : '';

        return '<blockquote class="paper-quote">' . do_shortcode($content) . $author_html . '</blockquote>';
    }

    /**
     * 标题短代码
     * 用法: [heading level="3"]标题文本[/heading]
     */
    public function render_heading($atts, $content = null) {
        $atts = shortcode_atts(['level' => '3'], $atts);

        if (is_null($content)) {
            return '';
        }

        $level = max(1, min(6, intval($atts['level'])));

        return '<h' . $level . ' class="shortcode-heading shortcode-heading-' . $level . '">' .
               esc_html($content) .
               '</h' . $level . '>';
    }

    /**
     * AI摘要短代码
     * 用法: [ai_summary]自定义摘要内容[/ai_summary]
     */
    public function render_ai_summary($atts, $content = null) {
        // 检查AI摘要功能是否启用
        if (!Paper_Settings_Manager::is_enabled('paper_wp_ai_settings', 'ai_summary_enabled')) {
            return '<!-- AI摘要功能未启用 -->';
        }

        $post_id = get_the_ID();

        // 如果有自定义内容，使用自定义内容
        if (!empty($content)) {
            $output = '<div class="ai-summary-block"><strong>AI摘要：</strong>' . wp_kses_post($content) . '</div>';
            return $output;
        }

        // 如果没有自定义内容，使用生成的AI摘要
        $ai_data = get_post_meta($post_id, '_paper_ai_summary', true);

        if (!empty($ai_data) && is_array($ai_data) && isset($ai_data['summary'])) {
            $output = '<div class="ai-summary-block"><strong>AI摘要：</strong>' . wp_kses_post($ai_data['summary']) . '</div>';
            return $output;
        } elseif (!empty($ai_data) && is_string($ai_data)) {
            // 向后兼容：旧版本只保存了字符串
            $output = '<div class="ai-summary-block"><strong>AI摘要：</strong>' . wp_kses_post($ai_data) . '</div>';
            return $output;
        }

        return '<!-- AI Summary Shortcode: No summary found for post ' . $post_id . ' -->';
    }

    /**
     * 书籍短代码
     * 用法:
     * [book title="书名" author="作者" cover="封面URL" rating="评分" status="read|reading|wish"]
     */
    public function render_book($atts) {
        $atts = shortcode_atts([
            'title' => '',
            'author' => '',
            'image' => '', // 修改为image参数，与用户使用的参数一致
            'rating' => '',
            'status' => 'read',
            'url' => '',
            'description' => ''
        ], $atts);

        if (empty($atts['title'])) {
            return '<li class="empty"></li>';
        }

        $title = esc_html($atts['title']);
        $image = $atts['image']; // 使用image参数
        $rating = intval($atts['rating']); // 确保是整数
        $url = esc_url($atts['url']);

        // 生成豆瓣读书风格的HTML结构
        $output = '<li>';

        // 如果有链接，整个项都是链接
        if (!empty($url)) {
            $output .= '<a href="' . $url . '" title="' . $title . '" target="_blank">';
        }

        // 书籍封面
        if (!empty($image)) {
            $output .= '<img loading="lazy" src="https://images.weserv.nl/?url=' . esc_url($image) . '" alt="' . $title . '">';
        }
        // 如果没有封面，不显示图片，只显示标题和评分

        // 书籍标题
        $output .= '<span>' . $title . '</span>';

        // 添加评分显示
        if (!empty($atts['rating'])) {
            $rating_val = floatval($atts['rating']);
            
            // 计算星星等级 (1-5)
            // 如果评分大于5，假设是10分制，除以2
            if ($rating_val > 5) {
                $star_rating = round($rating_val / 2);
            } else {
                $star_rating = round($rating_val);
            }
            
            // 确保在 0-5 之间
            $star_rating = max(0, min(5, $star_rating));
            
            $output .= '<em class="rating-content" title="评分：' . $rating_val . '分" data-rating="' . $star_rating . '">';
            $output .= '<i>★</i><i>★</i><i>★</i><i>★</i><i>★</i>';
            $output .= '</em>';
        }

        if (!empty($url)) {
            $output .= '</a>';
        }

        $output .= '</li>';

        return $output;
    }

    /**
     * 音乐短代码
     * 用法:
     * [music id="123456" server="netease" type="song"] - 平台单曲
     * [music url="http://example.com/song.mp3" name="歌曲名" artist="艺术家" type="song"] - 自定义单曲
     * [music url="http://example.com/song.mp3" name="歌曲名" artist="艺术家" type="playlist" playlist="我的歌单"] - 自定义歌单
     * [music url="https://www.9ku.com/play/123456.htm"] - 9ku音乐播放列表
     */
    public function render_music($atts) {
        $atts = shortcode_atts([
            'id' => '',
            'server' => 'netease',
            'type' => 'song',
            'url' => '',
            'name' => '',
            'artist' => '',
            'autoplay' => '0',
            'cover' => '',
            'lrc' => '',
            'playlist' => '',
            'max_songs' => '20'
        ], $atts);

        // 检查是否为9ku音乐URL
        if (!empty($atts['url']) && strpos($atts['url'], '9ku.com') !== false) {
            // 使用9ku专用播放器
            if (function_exists('paper_render_9ku_playlist')) {
                return paper_render_9ku_playlist([$atts]);
            } else {
                // 如果9ku模块未加载，使用原有音乐模块作为降级方案
                error_log('9ku音乐模块未加载，使用降级方案');
            }
        }

        // 获取音乐模块实例
        if (!class_exists('Paper_Music')) {
            return '<p class="shortcode-error">音乐模块未启用</p>';
        }

        $music_instance = Paper_Music::get_instance();

        // 如果是播放列表类型，使用页面级别的分组渲染
        if (!empty($atts['playlist']) && $atts['type'] === 'playlist') {
            return $this->render_playlist_group($atts);
        }

        // 单个音乐直接渲染
        return $music_instance->render_music($atts);
    }

    /**
     * 大图显示短代码 - 跳过图片大小优化，不应用hover效果，支持代理图片
     * 用法:
     * [fullimage src="image.jpg" alt="大图显示" width="800" height="600"]
     * [fullimage]image.jpg|大图显示[/fullimage]
     */
    public function render_fullimage($atts, $content = null) {
        $atts = shortcode_atts([
            'src' => '',
            'alt' => '',
            'class' => '',
            'width' => '',
            'height' => '',
            'style' => ''
        ], $atts);

        // 如果有内容，解析 content 中的 src|alt 格式
        if (!empty($content)) {
            $parts = explode('|', $content, 2);
            $atts['src'] = trim($parts[0] ?? '');
            $atts['alt'] = trim($parts[1] ?? '');
        }

        if (empty($atts['src'])) {
            return '<p class="shortcode-error">图片地址不能为空</p>';
        }

        $image_src = $atts['src'];

        // 构建HTML，使用容器确保布局正确
        $classes = 'fullimage-display ' . esc_attr($atts['class']);
        $img_attrs = 'src="' . esc_url($image_src) . '" alt="' . esc_attr($atts['alt']) . '" class="' . trim($classes) . '"';

        $styles = [];
        if (!empty($atts['style'])) {
            $styles[] = trim($atts['style']);
        }
        
        // 基础样式：确保图片不会溢出容器 (响应式)
        $styles[] = "max-width: 100% !important";
        $styles[] = "height: auto !important"; // 强制高度自适应，防止变形和留白

        // 判断是否同时设置了宽度和高度
        $has_width = !empty($atts['width']);
        $has_height = !empty($atts['height']);
        
        if ($has_width) {
            $img_attrs .= ' width="' . esc_attr($atts['width']) . '"';
            // 确保样式中也包含宽度，使用 !important 防止被全局CSS覆盖
            $w = $atts['width'];
            if (is_numeric($w)) $w .= 'px';
            $styles[] = "width: {$w} !important";
        }
        
        if ($has_height) {
            // 仅在 HTML 属性中保留 height，用于 SEO 和 CLS 优化
            // 不添加到 CSS style 中，以免破坏响应式缩放
            $img_attrs .= ' height="' . esc_attr($atts['height']) . '"';
        }
        
        if (!empty($styles)) {
            $img_attrs .= ' style="' . esc_attr(implode('; ', $styles)) . '"';
        }

        // 使用容器div确保布局安全，不居中
        $html = '<div class="fullimage-container" style="margin: 15px 0; clear: both; text-align: left;">';
        $html .= '<img ' . $img_attrs . ' />';
        $html .= '</div>';
        return $html;
    }

    /**
     * 图片网格短代码 - 支持1-3张图片的布局
     * 用法:
     * [gallery url1]                → 单图大图
     * [gallery url1 url2]           → 2图网格
     * [gallery url1 url2 url3]      → 3图网格
     * 
     * 或使用命名参数:
     * [gallery img1="url1"]
     * [gallery img1="url1" img2="url2"]
     * [gallery img1="url1" img2="url2" img3="url3"]
     */
    public function render_gallery($atts) {
        // 获取所有未命名的参数（位置参数）
        $urls = [];
        
        // 方式1：支持位置参数 [gallery url1 url2 url3]
        if (isset($atts[0])) {
            // 获取所有数字索引的参数
            for ($i = 0; $i < 10; $i++) {
                if (isset($atts[$i]) && !empty($atts[$i])) {
                    $urls[] = $atts[$i];
                }
            }
        }
        
        // 方式2：支持命名参数 [gallery img1="url1" img2="url2"]
        if (empty($urls)) {
            for ($i = 1; $i <= 10; $i++) {
                $key = 'img' . $i;
                if (isset($atts[$key]) && !empty($atts[$key])) {
                    $urls[] = $atts[$key];
                }
            }
        }
        
        $count = count($urls);
        
        // 支持1-3张图片
        if ($count < 1 || $count > 3) {
            return '<p class="shortcode-error">gallery shortcode 只支持1-3张图片</p>';
        }
        
        // 单图特殊处理 - 全宽显示
        if ($count === 1) {
            $url = trim($urls[0]);
            $html = '<div class="gallery-single">';
            $html .= '<a href="' . esc_url($url) . '" rel="lightbox">';
            $html .= '<img src="' . esc_url($url) . '" alt="" loading="lazy" />';
            $html .= '</a>';
            $html .= '</div>';
            return $html;
        }
        
        // 多图 - 网格布局
        $grid_class = 'gallery-grid-' . $count;
        $html = '<div class="' . $grid_class . '">';
        
        foreach ($urls as $url) {
            $url = trim($url);
            if (empty($url)) continue;
            
            // 生成图片HTML（带灯箱链接）
            $html .= '<a href="' . esc_url($url) . '" rel="lightbox">';
            $html .= '<img src="' . esc_url($url) . '" alt="" loading="lazy" />';
            $html .= '</a>';
        }
        
        $html .= '</div>';
        
        return $html;
    }



    /**
     * 渲染播放列表组 - 收集同一播放列表的所有歌曲
     */
    private function render_playlist_group($atts) {
        static $rendered_playlists = [];

        $playlist_name = $atts['playlist'];

        // 如果这个播放列表已经渲染过了，返回空（避免重复渲染）
        if (isset($rendered_playlists[$playlist_name])) {
            return '';
        }

        // 标记为已渲染
        $rendered_playlists[$playlist_name] = true;

        // 获取当前页面的所有音乐短代码
        global $post;
        if (!$post) {
            // 如果没有全局$post，使用当前音乐的设置渲染单个播放器
            $music_instance = Paper_Music::get_instance();
            return $music_instance->render_music($atts);
        }

        $content = $post->post_content;

        // 查找所有音乐短代码
        $pattern = get_shortcode_regex(['music']);
        preg_match_all("/$pattern/s", $content, $matches);

        $playlist_songs = [];

        // 收集同一播放列表的所有歌曲
        foreach ($matches[0] as $shortcode) {
            if (preg_match("/$pattern/", $shortcode, $match)) {
                $song_atts = shortcode_parse_atts($match[3]);

                // 如果是同一个播放列表，收集起来
                if (!empty($song_atts['playlist']) && $song_atts['playlist'] === $playlist_name && $song_atts['type'] === 'playlist') {
                    $playlist_songs[] = $song_atts;
                }
            }
        }

        // 如果只找到一首歌，直接渲染单个播放器
        if (count($playlist_songs) <= 1) {
            $music_instance = Paper_Music::get_instance();
            return $music_instance->render_music($atts);
        }

        // 多个歌曲，渲染播放列表
        $music_instance = Paper_Music::get_instance();
        return $music_instance->render_custom_music_playlist($playlist_songs);
    }
}

/**
 * 初始化短代码模块
 */
function shortcodes_init() {
    return Shortcodes_Module::get_instance();
}

// 启动模块
add_action('init', 'shortcodes_init', 5);
