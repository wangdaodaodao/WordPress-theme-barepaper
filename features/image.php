<?php
/**
 * 图片处理模块 - 灯箱、响应式、懒加载、代理支持
 *
 * @version 3.1.0
 */

if (!defined('ABSPATH')) exit;

/**
 * 图片处理独立模块类
 */
class Paper_Image {

    // 常量定义 - 避免魔法字符串
    const STYLE_HANDLE = 'paper-image';
    const LIGHTBOX_REL = 'lightbox';
    const THUMBNAIL_DATA_ATTR = 'data-thumbnail-src';
    const FULL_SRC_DATA_ATTR = 'data-full-src';

    private static $instance = null;
    
    // 缓存设置，避免重复读取
    private static $performance_settings = null;
    private static $proxy_settings = null;
    private static $upload_dir = null;

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
     * 初始化模块
     */
    private function init() {
        // 检查功能是否启用
        if (!$this->is_enabled()) {
            return;
        }

        // 加载样式
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);

        // 图片优化钩子 - 只保留附件属性优化，移除内容处理（由process_content_images统一处理）
        add_filter('wp_get_attachment_image_attributes', [$this, 'optimize_image_attributes'], 10, 2);

        // 统一的内容图片处理
        add_filter('the_content', [$this, 'process_content_images'], 9);
        add_filter('the_excerpt', [$this, 'process_content_images'], 11);
    }

    /**
     * 检查功能是否启用
     */
    private function is_enabled() {
        // 图片处理功能现在直接启用
        return true;
    }

    /**
     * 加载样式
     */
    public function enqueue_styles() {
        wp_enqueue_style(self::STYLE_HANDLE, get_template_directory_uri() . '/css/image.css', [], '1.0.0');
    }

    /**
     * 调试信息
     */
    public function debug_info() {
        // 调试功能已移除
    }

    /**
     * ===========================================
     * 图片优化 - 统一的内容处理
     * ===========================================
     */

    /**
     * 优化图片属性 - 改进alt属性和加载性能
     */
    public function optimize_image_attributes($attr, $attachment) {
        // 改进alt属性
        if (empty($attr['alt']) || in_array($attr['alt'], ['图片测试1', '图片测试2', '测试图片'])) {
            // 尝试从附件元数据获取更好的alt文本
            $alt_text = get_post_meta($attachment->ID, '_wp_attachment_image_alt', true);
            if (empty($alt_text)) {
                // 如果没有alt文本，使用图片标题或描述
                $alt_text = $attachment->post_title ?: $attachment->post_content;
                if (empty($alt_text)) {
                    $alt_text = '网站图片'; // 默认alt文本
                }
            }
            $attr['alt'] = esc_attr($alt_text);
        }

        // 添加loading="lazy"属性（如果还没有的话）
        if (!isset($attr['loading'])) {
            $attr['loading'] = 'lazy';
        }

        // 添加decoding="async"属性以提高性能
        if (!isset($attr['decoding'])) {
            $attr['decoding'] = 'async';
        }

        return $attr;
    }

    /**
     * 获取性能设置（带缓存）
     */
    private static function get_performance_settings() {
        if (self::$performance_settings === null) {
            self::$performance_settings = get_option('paper_wp_performance_settings', []);
        }
        return self::$performance_settings;
    }
    
    /**
     * 获取代理设置（带缓存）
     */
    private static function get_proxy_settings() {
        if (self::$proxy_settings === null) {
            self::$proxy_settings = get_option('paper_wp_image_proxy_settings', []);
        }
        return self::$proxy_settings;
    }
    
    /**
     * 获取上传目录（带缓存）
     */
    private static function get_upload_dir() {
        if (self::$upload_dir === null) {
            self::$upload_dir = wp_upload_dir();
        }
        return self::$upload_dir;
    }
    
    /**
     * 统一的内容图片处理 - 仅处理HTML图片，Markdown语法由image-parser.php处理
     */
    public function process_content_images($content) {
        // 如果内容为空或在后台，则不处理
        if (empty($content) || is_admin()) {
            return $content;
        }

        // 快速检查是否包含图片标签，如果不包含则直接返回
        if (strpos($content, '<img') === false) {
            return $content;
        }

        // 获取后台设置（使用缓存）
        $performance_settings = self::get_performance_settings();
        $enable_responsive_images = !empty($performance_settings['enable_responsive_images']);
        $enable_small_images = !empty($performance_settings['enable_small_images']);

        // 如果两个功能都未启用，只做最基本的灯箱处理
        if (!$enable_responsive_images && !$enable_small_images) {
            // 仅处理灯箱链接，不需要DOM解析
            return $this->process_lightbox_only($content);
        }

        // 使用 DOMDocument 解析 HTML - 增强健壮性
        $dom = new DOMDocument();

        // 启用内部错误处理，避免解析失败时输出警告
        libxml_use_internal_errors(true);

        // 尝试解析HTML，如果失败则返回原始内容
        $parse_success = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // 如果解析失败，返回原始内容
        if (!$parse_success) {
            libxml_clear_errors();
            return $content;
        }

        // 解析成功，继续处理
        $this->process_image_containers($dom);
        $this->process_individual_images($dom, $enable_responsive_images, $enable_small_images);

        // 保存处理后的 HTML - 使用更高效的xpath方式获取body内容
        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body) {
            $new_content = '';
            foreach ($body->childNodes as $node) {
                $new_content .= $dom->saveHTML($node);
            }
        } else {
            $new_content = $dom->saveHTML();
        }

        // 验证处理结果，如果为空或过短则返回原始内容
        if (empty($new_content) || strlen($new_content) < strlen($content) * 0.5) {
            return $content;
        }

        // 移除 loadHTML 添加的额外标签
        $new_content = str_replace(['<?xml encoding="utf-8" ?>', '<html>', '<body>', '</html>', '</body>'], '', $new_content);

        // 清理libxml错误
        libxml_clear_errors();

        return trim($new_content);
    }
    
    /**
     * 仅处理灯箱链接（不需要DOM解析，性能更好）
     */
    private function process_lightbox_only($content) {
        // 使用正则表达式快速添加灯箱链接（仅对没有链接的图片）
        // 但需要确保图片在image-single容器中以便应用尺寸控制
        return preg_replace_callback(
            '/(<p[^>]*class="[^"]*image-gallery[^"]*image-single[^"]*"[^>]*>)?(<img[^>]+>)(?!\s*<\/a>)/',
            function($matches) {
                $container = isset($matches[1]) ? $matches[1] : '';
                $img_tag = $matches[2];
                
                // 检查图片是否为大图（shortcode生成的），大图不受尺寸限制
                if (strpos($img_tag, 'fullimage') !== false || strpos($img_tag, 'fullimage-display') !== false) {
                    return $matches[0]; // 保持原样
                }
                
                // 检查图片是否已经在链接中
                if (strpos($img_tag, 'post-image') === false) {
                    // 提取src
                    if (preg_match('/src=["\']([^"\']+)["\']/', $img_tag, $src_match)) {
                        $src = $src_match[1];
                        // 处理代理URL（如果需要）
                        $src = $this->process_proxy_url($src);
                        
                        // 提取alt
                        $alt = '';
                        if (preg_match('/alt=["\']([^"\']*)["\']/', $img_tag, $alt_match)) {
                            $alt = esc_attr($alt_match[1]);
                        }
                        // 添加post-image类
                        $img_tag = str_replace('<img ', '<img class="post-image" ', $img_tag);
                        // 更新src（如果使用了代理）
                        $img_tag = preg_replace('/src=["\'][^"\']+["\']/', 'src="' . esc_url($src) . '"', $img_tag);
                        // 添加灯箱链接
                        $link_html = '<a href="' . esc_url($src) . '" rel="' . self::LIGHTBOX_REL . '" title="' . $alt . '">' . $img_tag . '</a>';
                        
                        // 如果没有容器，需要添加
                        if (empty($container)) {
                            return '<p class="image-gallery image-single">' . $link_html . '</p>';
                        } else {
                            return $container . $link_html;
                        }
                    }
                }
                return $matches[0];
            },
            $content
        );
    }

    /**
     * 处理图片容器 - 添加相册样式（优化：合并逻辑，减少DOM查询）
     */
    private function process_image_containers($dom) {
        // 一次性获取所有图片，避免重复查询
        $all_images = $dom->getElementsByTagName('img');
        $processed_paragraphs = [];
        
        // 第一遍：处理段落中的图片
        foreach ($all_images as $img) {
            $parent = $img->parentNode;
            // 检查图片是否为大图（shortcode生成的）
            $img_class = $img->getAttribute('class');
            $is_full_image = strpos($img_class, 'fullimage') !== false || 
                           strpos($img_class, 'fullimage-display') !== false;
            
            if ($parent->nodeName === 'p') {
                $p = $parent;
                // 避免重复处理同一个段落
                if (isset($processed_paragraphs[spl_object_hash($p)])) {
                    continue;
                }
                $processed_paragraphs[spl_object_hash($p)] = true;
                
                // 检查段落中是否包含fullimage类的图片
                $has_full_image = false;
                $images_in_p = $p->getElementsByTagName('img');
                foreach ($images_in_p as $p_img) {
                    $p_img_class = $p_img->getAttribute('class');
                    if (strpos($p_img_class, 'fullimage') !== false || 
                        strpos($p_img_class, 'fullimage-display') !== false) {
                        $has_full_image = true;
                        break;
                    }
                }

                // 如果包含fullimage图片，跳过添加相册样式（大图不受尺寸限制）
                if ($has_full_image) {
                    continue;
                }

                $current_class = $p->getAttribute('class');
                // 只有当没有image-gallery类时才添加
                if (strpos($current_class, 'image-gallery') === false) {
                    $p->setAttribute('class', trim($current_class . ' image-gallery image-single'));
                }
            } else if (!$is_full_image) {
                // 不在段落中且不是大图的图片，也需要确保在image-single容器中
                // 这个会在第二遍循环中处理
            }
        }

        // 第二遍：处理不在段落中的图片 - 包裹在单图容器中
        // 必须从后往前遍历，因为我们会修改 DOM 结构
        // 重新获取图片列表，因为DOM可能已改变
        $all_images = $dom->getElementsByTagName('img');
        for ($i = $all_images->length - 1; $i >= 0; $i--) {
            $img = $all_images->item($i);
            $parent = $img->parentNode;
            
            // 检查是否为大图（shortcode生成的），大图不需要尺寸限制
            $img_class = $img->getAttribute('class');
            $is_full_image = strpos($img_class, 'fullimage') !== false || 
                           strpos($img_class, 'fullimage-display') !== false;

            // 如果图片不在段落中，且不在a标签中，且父元素没有image-gallery类，且不是大图
            if ($parent->nodeName !== 'p' && $parent->nodeName !== 'a' && !$is_full_image) {
                $parent_class = $parent->getAttribute('class');
                // 如果父元素是fullimage-container（shortcode生成的），不处理
                if (strpos($parent_class, 'fullimage-container') === false && 
                    strpos($parent_class, 'image-gallery') === false) {
                    $container = $dom->createElement('p');
                    $container->setAttribute('class', 'image-gallery image-single');

                    // 替换图片节点
                    $parent->replaceChild($container, $img);
                    $container->appendChild($img);
                }
            }
        }
    }

    /**
     * 处理单个图片 - 添加灯箱、srcset、懒加载
     */
    private function process_individual_images($dom, $enable_responsive_images, $enable_small_images) {
        $images = $dom->getElementsByTagName('img');

        // 必须从后往前遍历，因为我们会修改 DOM 结构
        for ($i = $images->length - 1; $i >= 0; $i--) {
            $img = $images->item($i);

            $img_src = $img->getAttribute('src');
            
            // 跳过特殊图片（优化：只检查src，避免昂贵的saveHTML调用）
            if (empty($img_src) || $this->is_special_image_quick($img_src, $img)) {
                continue;
            }

            // 处理代理URL（如果需要）
            $img_src = $this->process_proxy_url($img_src);
            $img->setAttribute('src', $img_src);

            // 获取当前class并检查是否为大图（包括shortcode的fullimage）
            $current_class = $img->getAttribute('class');
            $is_full_size = strpos($current_class, 'full') !== false || 
                          strpos($current_class, 'fullimage') !== false ||
                          strpos($current_class, 'fullimage-display') !== false;

            // 如果图片已经被 a 标签包裹，则检查是否需要添加灯箱属性
            if ($img->parentNode->nodeName === 'a') {
                $parent_link = $img->parentNode;
                $link_href = $parent_link->getAttribute('href');
                
                // 处理链接的代理URL（如果需要）
                if (!empty($link_href)) {
                    $link_href = $this->process_proxy_url($link_href);
                    $parent_link->setAttribute('href', $link_href);
                }

                // 检查链接是否指向图片（真正的灯箱链接）
                $is_image_link = $this->is_image_url($link_href);

                if ($is_image_link && !$is_full_size) {
                    // 确保有rel="lightbox"属性
                    if (!$parent_link->hasAttribute('rel') || $parent_link->getAttribute('rel') !== self::LIGHTBOX_REL) {
                        $parent_link->setAttribute('rel', self::LIGHTBOX_REL);
                    }

                    // 小图优化 - 为已存在的灯箱链接设置完整大图URL
                    if ($enable_small_images) {
                        $small_src = $this->generate_small_image_url($link_href);
                        if ($small_src && $small_src !== $link_href) {
                            // 存储缩略图和大图URL，由JavaScript控制加载和灯箱切换
                            $img->setAttribute(self::THUMBNAIL_DATA_ATTR, $small_src);
                            $img->setAttribute(self::FULL_SRC_DATA_ATTR, $link_href);
                        }
                    }
                    
                    // 添加样式类（确保在image-single容器中）
                    $new_class = trim($current_class . ' post-image');
                    $img->setAttribute('class', $new_class);
                } else if (!$is_image_link && !$is_full_size) {
                    // 如果链接不指向图片，移除父链接，为图片创建正确的灯箱链接
                    $parent_link->parentNode->replaceChild($img->cloneNode(true), $parent_link);
                    // 重新处理这个图片（会进入下面的else分支）
                    $i++; // 补偿索引，因为我们添加了一个新元素
                    continue;
                }
                // 如果是大图（fullimage），不做任何处理，保持原始状态
                continue;
            }

            // 普通图片处理：确保在image-single容器中并应用尺寸控制
            if (!$is_full_size) {
                // 普通图片：添加样式类、懒加载、响应式等优化
                $img->setAttribute('class', trim(($current_class ? $current_class . ' ' : '') . 'post-image'));

                if (!$img->hasAttribute('loading')) {
                    $img->setAttribute('loading', 'lazy');
                }

                // 生成并添加 srcset (如果启用)
                if ($enable_responsive_images && !$img->hasAttribute('srcset')) {
                    $srcset = $this->generate_image_srcset($img_src);
                    if (!empty($srcset)) {
                        $img->setAttribute('srcset', $srcset);
                        $img->setAttribute('sizes', '(max-width: 600px) 100vw, (max-width: 1200px) 50vw, 33vw');
                    }
                }

                // 小图优化 - 懒加载实现
                if ($enable_small_images) {
                    $small_src = $this->generate_small_image_url($img_src);
                    if ($small_src && $small_src !== $img_src) {
                        $img->setAttribute('src', 'data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
                        $img->setAttribute(self::THUMBNAIL_DATA_ATTR, $small_src);
                        $img->setAttribute(self::FULL_SRC_DATA_ATTR, $img_src);
                    }
                }

                // 创建灯箱链接（使用已处理过的代理URL）
                $alt = $img->getAttribute('alt');
                $link = $dom->createElement('a');
                $link->setAttribute('href', $img_src); // 使用已经处理过代理的URL
                $link->setAttribute('rel', self::LIGHTBOX_REL);
                $link->setAttribute('title', $alt);

                $img->parentNode->replaceChild($link, $img);
                $link->appendChild($img);
            }
            // 大图图片不做任何处理，保持原始class（但已经处理过代理URL）
        }
    }

    /**
     * 检查是否为特殊图片（快速版本，使用DOM节点）
     */
    private function is_special_image_quick($src, $img_node) {
        // 检查src中的关键词
        $special_keywords = ['svg', 'icon', 'alipay.svg', 'wechat.svg', 'moon.svg', 'sun.svg', 'top.svg'];
        foreach ($special_keywords as $keyword) {
            if (strpos($src, $keyword) !== false) {
                return true;
            }
        }

        // 检查img标签的class属性（直接使用DOM，避免字符串操作）
        $img_class = $img_node->getAttribute('class');
        $special_classes = ['booklist', 'movielist', 'qr-popup', 'menu-icon', 'theme-icon'];
        foreach ($special_classes as $class) {
            if (strpos($img_class, $class) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 生成响应式图片srcset
     */
    private function generate_image_srcset($image_url) {
        // 只为WordPress上传的图片生成srcset
        $upload_dir = self::get_upload_dir();
        if (strpos($image_url, $upload_dir['baseurl']) === 0) {
            $attachment_id = attachment_url_to_postid($image_url);
            if ($attachment_id) {
                return wp_get_attachment_image_srcset($attachment_id);
            }
        }
        return '';
    }

    /**
     * 严格验证域名是否在白名单中（支持通配符）
     */
    private function is_domain_allowed($host, $allowed_domains) {
        if (empty($host)) {
            return false;
        }
        
        foreach ($allowed_domains as $allowed_domain) {
            if (empty($allowed_domain)) {
                continue;
            }
            
            // 完全匹配最快
            if ($host === $allowed_domain) {
                return true;
            }
            
            // 支持通配符 *.domain.com 格式
            if (str_starts_with($allowed_domain, '*.')) {
                $base_domain = substr($allowed_domain, 2); // 移除 *.
                if ($host === $base_domain || str_ends_with($host, '.' . $base_domain)) {
                    return true;
                }
            } elseif (str_ends_with($host, '.' . $allowed_domain)) {
                // 子域名匹配：host是 allowed_domain 的子域名
                return true;
            }
        }
        return false;
    }

    /**
     * 检查URL是否指向图片
     */
    private function is_image_url($url) {
        if (empty($url)) return false;

        // 检查URL扩展名
        $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'];
        $path_info = pathinfo(parse_url($url, PHP_URL_PATH));
        $extension = strtolower($path_info['extension'] ?? '');

        if (in_array($extension, $image_extensions)) {
            return true;
        }

        // 检查Content-Type（如果有缓存）
        // 这里可以扩展为检查HTTP头，但为性能考虑暂时使用扩展名检查

        return false;
    }

    /**
     * 生成小尺寸图片URL - 渐进式增强策略
     */
    private function generate_small_image_url($original_url) {
        // 只处理WordPress媒体库的图片，确保安全
        $upload_dir = self::get_upload_dir();
        if (strpos($original_url, $upload_dir['baseurl']) !== 0) {
            return $original_url; // 只处理本地图片
        }

        $attachment_id = attachment_url_to_postid($original_url);
        if (!$attachment_id) {
            return $original_url;
        }

        // 尝试获取中等尺寸的图片
        $medium_image = wp_get_attachment_image_src($attachment_id, 'medium');
        if ($medium_image && $medium_image[0] !== $original_url) {
            return $medium_image[0];
        }

        // 如果没有中等尺寸，返回原始URL
        return $original_url;
    }
    
    /**
     * 处理图片URL代理（如果需要）
     */
    private function process_proxy_url($src) {
        $proxy_settings = self::get_proxy_settings();
        if (empty($proxy_settings['enable_image_proxy'])) {
            return $src;
        }
        
        // 优化：如果是本地图片，不需要代理
        $upload_dir = self::get_upload_dir();
        if (strpos($src, $upload_dir['baseurl']) === 0) {
            return $src; // 本地图片不需要代理
        }
        
        $parsed_url = parse_url($src);
        $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        
        if (empty($host)) {
            return $src; // 无效的URL
        }
        
        // 检查是否在代理域名列表中（优化：统一域名分割格式）
        if (!empty($proxy_settings['proxy_domains'])) {
            $proxy_domains = preg_split('/[\n\r,]+/', $proxy_settings['proxy_domains']);
            foreach ($proxy_domains as $proxy_domain) {
                $proxy_domain = trim($proxy_domain);
                if (!empty($proxy_domain) && $this->is_domain_allowed($host, [$proxy_domain])) {
                    // 使用代理URL（proxy-image.php 在 features 目录下）
                    $proxy_url = home_url('/wp-content/themes/barepaper-v7.1.0/features/proxy-image.php?url=' . urlencode($src));
                    return $proxy_url;
                }
            }
        }

        return $src;
    }
}

/**
 * 初始化图片处理模块
 */
function paper_image_init() {
    return Paper_Image::get_instance();
}

// 启动模块
add_action('init', 'paper_image_init', 5);





/**
 * ===========================================
 * Slimbox2灯箱插件加载
 * ===========================================
 */
function paper_wp_enqueue_slimbox2() {
    // 在所有可能包含图片的页面加载Slimbox2灯箱功能
    if (is_single() || is_page() || is_archive() || is_home()) {
        // 加载Slimbox2 CSS
        wp_enqueue_style(
            'slimbox2',
            get_template_directory_uri() . '/css/slimbox2.css',
            [],
            '2.05'
        );

        // 加载Slimbox2 JS (依赖jQuery)
        wp_enqueue_script(
            'slimbox2',
            get_template_directory_uri() . '/js/slimbox2.js',
            ['jquery'],
            '2.05',
            true
        );

        // 使用wp_add_inline_script确保依赖关系正确
        $init_script = "
            jQuery(document).ready(function($) {
                // 优化的灯箱初始化：使用自定义linkMapper确保始终使用完整大图URL
                $('a[rel=\"" . Paper_Image::LIGHTBOX_REL . "\"]').slimbox({
                    overlayOpacity: 0.8,
                    overlayFadeDuration: 300,
                    resizeDuration: 400,
                    imageFadeDuration: 400,
                    captionAnimationDuration: 400
                }, function(el) {
                    // 自定义linkMapper：返回完整大图URL和标题
                    var \$link = \$(el);
                    var \$img = \$link.find('img');
                    var fullSrc = \$img.attr('" . Paper_Image::FULL_SRC_DATA_ATTR . "');
                    var imgSrc = fullSrc || \$link.attr('href'); // 优先使用完整大图URL
                    var title = \$link.attr('title') || \$img.attr('alt') || '';
                    
                    // 如果有完整大图URL，同时更新链接的href（用于直接访问）
                    if (fullSrc && \$link.attr('href') !== fullSrc) {
                        \$link.attr('href', fullSrc);
                    }
                    
                    return [imgSrc, title];
                });

                // 按需加载策略：只加载缩略图，用户点击时才加载大图
                if ('IntersectionObserver' in window) {
                    // 创建Intersection Observer实例
                    var imageObserver = new IntersectionObserver(function(entries, observer) {
                        entries.forEach(function(entry) {
                            if (entry.isIntersecting) {
                                var img = entry.target;
                                var thumbnailSrc = img.getAttribute('" . Paper_Image::THUMBNAIL_DATA_ATTR . "');

                                // 当图片进入视口时，只加载缩略图
                                if (thumbnailSrc) {
                                    var tempImg = new Image();
                                    tempImg.onload = function() {
                                        // 缩略图加载成功，显示缩略图
                                        img.src = thumbnailSrc;
                                    };
                                    tempImg.src = thumbnailSrc;
                                }

                                // 停止观察这个图片
                                observer.unobserve(img);
                            }
                        });
                    }, {
                        // 提前50px开始加载
                        rootMargin: '50px 0px',
                        threshold: 0.01
                    });

                    // 观察所有需要懒加载的图片
                    document.querySelectorAll('img[" . Paper_Image::THUMBNAIL_DATA_ATTR . "]').forEach(function(img) {
                        imageObserver.observe(img);
                    });
                } else {
                    // Intersection Observer不支持时的降级方案
                    $('img[" . Paper_Image::THUMBNAIL_DATA_ATTR . "]').each(function() {
                        var \$img = \$(this);
                        var thumbnailSrc = \$img.attr('" . Paper_Image::THUMBNAIL_DATA_ATTR . "');

                        if (thumbnailSrc) {
                            // 直接加载缩略图
                            var tempImg = new Image();
                            tempImg.onload = function() {
                                \$img.attr('src', thumbnailSrc);
                            };
                            tempImg.src = thumbnailSrc;
                        }
                    });
                }
            });
        ";
        wp_add_inline_script('slimbox2', $init_script);
    }
}
add_action('wp_enqueue_scripts', 'paper_wp_enqueue_slimbox2');
