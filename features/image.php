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
    const LIGHTBOX_REL = 'lightbox';
    const THUMBNAIL_DATA_ATTR = 'data-thumbnail-src';
    const FULL_SRC_DATA_ATTR = 'data-full-src';

    private static $instance = null;
    
    // 缓存上传目录信息
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

        // 上传图片自动重命名
        add_filter('wp_handle_upload_prefilter', [$this, 'rename_uploaded_image']);
    }

    /**
     * 上传图片重命名 - 最佳实践
     * 格式：YmdHis_随机数.扩展名
     * 优势：
     * 1. 解决中文文件名乱码问题
     * 2. 防止文件名冲突
     * 3. 隐藏原始文件名隐私信息
     * 4. 按时间自然排序
     */
    public function rename_uploaded_image($file) {
        $info = pathinfo($file['name']);
        $ext = empty($info['extension']) ? '' : '.' . $info['extension'];
        
        // 生成新文件名：年月日时分秒_随机数 (例如: 20251130125436_123.jpg)
        // 使用 mt_rand() 生成3位随机数，足以防止同一秒内的并发冲突
        $new_name = date('YmdHis') . '_' . mt_rand(100, 999) . $ext;
        
        $file['name'] = $new_name;
        return $file;
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
        wp_enqueue_style('paper-image', get_template_directory_uri() . '/css/image.css', [], '1.0.0');
    }


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
     * 获取性能设置（使用统一设置管理器）
     */
    private static function get_performance_settings() {
        return Paper_Settings_Manager::get_performance_settings();
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
     * 统一的内容图片处理 - 简化的处理逻辑
     */
    public function process_content_images($content) {
        if (empty($content) || is_admin() || strpos($content, '<img') === false) {
            return $content;
        }

        $performance_settings = self::get_performance_settings();
        $enable_responsive_images = !empty($performance_settings['enable_responsive_images']);
        $enable_small_images = !empty($performance_settings['enable_small_images']);

        // 如果没有启用高级功能，只处理基础灯箱
        if (!$enable_responsive_images && !$enable_small_images) {
            return $this->process_lightbox_only($content);
        }

        // DOM处理逻辑
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);

        if (!$dom->loadHTML('<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            libxml_clear_errors();
            return $content;
        }

        $this->process_image_containers($dom);
        $this->process_individual_images($dom, $enable_responsive_images, $enable_small_images);

        $body = $dom->getElementsByTagName('body')->item(0);
        $new_content = $body ? $dom->saveHTML($body) : $dom->saveHTML();

        if (empty($new_content) || strlen($new_content) < strlen($content) * 0.5) {
            return $content;
        }

        $new_content = str_replace(['<?xml encoding="utf-8" ?>', '<html>', '<body>', '</html>', '</body>'], '', $new_content);
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
                
                // 跳过 markdown-image 类的图片
                if (strpos($img_tag, 'markdown-image') !== false) {
                    return $matches[0];
                }
                
                // 检查图片是否已经在链接中
                if (strpos($img_tag, 'post-image') === false) {
                    // 提取src
                    if (preg_match('/src=["\']([^"\']+)["\']/', $img_tag, $src_match)) {
                        $src = $src_match[1];
                        
                        // 提取alt
                        $alt = '';
                        if (preg_match('/alt=["\']([^"\']*)["\']/', $img_tag, $alt_match)) {
                            $alt = esc_attr($alt_match[1]);
                        }
                        // 添加post-image类
                        $img_tag = str_replace('<img ', '<img class="post-image" ', $img_tag);
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
     * 处理图片容器 - 统一添加单图样式
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
                    // 统一添加单图样式，不再检测网格
                    $gallery_class = 'image-gallery image-single';
                    $p->setAttribute('class', trim($current_class . ' ' . $gallery_class));
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

            // 获取当前class并检查是否为大图（包括shortcode的fullimage）
            $current_class = $img->getAttribute('class');
            $is_full_size = strpos($current_class, 'full') !== false || 
                          strpos($current_class, 'fullimage') !== false ||
                          strpos($current_class, 'fullimage-display') !== false;

            // 如果图片已经被 a 标签包裹，则检查是否需要添加灯箱属性
            if ($img->parentNode->nodeName === 'a') {
                $parent_link = $img->parentNode;
                $link_href = $parent_link->getAttribute('href');
                
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
                    // 跳过 markdown-image 类的图片
                    if (strpos($current_class, 'markdown-image') === false) {
                        $new_class = trim($current_class . ' post-image');
                        $img->setAttribute('class', $new_class);
                    }
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
                // 检查是否为 markdown-image
                $is_markdown_image = strpos($current_class, 'markdown-image') !== false;
                
                // Markdown 图片：只添加灯箱链接，不添加 post-image 类和其他优化
                if ($is_markdown_image) {
                    // 确保有 loading 属性
                    if (!$img->hasAttribute('loading')) {
                        $img->setAttribute('loading', 'lazy');
                    }
                    
                    // 创建灯箱链接
                    $alt = $img->getAttribute('alt');
                    $link = $dom->createElement('a');
                    $link->setAttribute('href', $img_src);
                    $link->setAttribute('rel', self::LIGHTBOX_REL);
                    $link->setAttribute('title', $alt);

                    $img->parentNode->replaceChild($link, $img);
                    $link->appendChild($img);
                    continue;
                }
                
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

                // 创建灯箱链接
                $alt = $img->getAttribute('alt');
                $link = $dom->createElement('a');
                $link->setAttribute('href', $img_src);
                $link->setAttribute('rel', self::LIGHTBOX_REL);
                $link->setAttribute('title', $alt);

                $img->parentNode->replaceChild($link, $img);
                $link->appendChild($img);
            }
            // 大图图片不做任何处理，保持原始class
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
    // 只在可能包含图片的页面检查
    if (!is_single() && !is_page() && !is_archive() && !is_home()) {
        return;
    }

    // 检查页面内容是否包含需要灯箱的图片
    $needs_lightbox = false;

    if (is_single() || is_page()) {
        // 对于单篇文章和页面，检查内容
        global $post;
        if ($post) {
            $content = $post->post_content;
            
            // 检查是否包含普通图片（不是大图短代码）
            // 排除大图短代码：如果只有 [fullimage] 短代码，不需要灯箱
            $has_regular_images = false;
            
            // 检查是否有大图短代码
            if (has_shortcode($content, 'fullimage')) {
                // 移除大图短代码后检查是否还有其他图片
                $content_without_fullimage = preg_replace('/\[fullimage[^\]]*\][^\]]*\[\/fullimage\]/i', '', $content);
                $content_without_fullimage = preg_replace('/\[fullimage[^\]]*\]/i', '', $content_without_fullimage);
                
                // 检查是否还有普通图片标签或普通图片短代码
                if (preg_match('/<img[^>]+>/i', $content_without_fullimage) || 
                    preg_match('/!\[.*?\]\(.*?\)/', $content_without_fullimage)) {
                    $has_regular_images = true;
                }
            } else {
                // 没有大图短代码，检查是否有普通图片
                if (preg_match('/<img[^>]+>/i', $content) || 
                    preg_match('/!\[.*?\]\(.*?\)/', $content)) {
                    $has_regular_images = true;
                }
            }
            
            $needs_lightbox = $has_regular_images;
        }
    } else {
        // 对于归档页和首页，可能包含图片，默认加载（但可以通过后续优化进一步检查）
        $needs_lightbox = true;
    }

    // 只有在需要灯箱时才加载脚本
    if (!$needs_lightbox) {
        return;
    }

    // 加载Slimbox2 JS (依赖jQuery)
    wp_enqueue_script(
        'slimbox2',
        get_template_directory_uri() . '/js/slimbox2.js',
        ['jquery'],
        '2.05',
        true
    );

    // slimbox2.css 已合并入 components.css
    // slimbox2-init.js 已合并入 interactions.js
}
add_action('wp_enqueue_scripts', 'paper_wp_enqueue_slimbox2');
