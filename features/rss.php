<?php
if (!defined('ABSPATH')) exit;

/**
 * ===========================================
 * RSS Feed 编码修复功能
 * ===========================================
 */

/**
 * 强制RSS Feed使用UTF-8编码
 */
function paper_wp_fix_rss_encoding() {
    if (is_feed()) {
        header('Content-Type: application/rss+xml; charset=UTF-8', true);
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('the_excerpt_rss', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('the_content_feed', 'wpautop');
        remove_filter('the_excerpt_rss', 'wpautop');
        add_filter('the_title_rss', 'paper_wp_force_utf8_encoding');
        add_filter('the_content_feed', 'paper_wp_force_utf8_encoding');
        add_filter('the_excerpt_rss', 'paper_wp_force_utf8_encoding');
        add_filter('comment_text_rss', 'paper_wp_force_utf8_encoding');
    }
}
add_action('template_redirect', 'paper_wp_fix_rss_encoding');

/**
 * 强制UTF-8编码处理函数
 * 检测并转换非UTF-8内容，确保中文字符正确显示
 *
 * @param string $content 需要处理的RSS内容
 * @return string 处理后的UTF-8内容
 */
function paper_wp_force_utf8_encoding($content) {
    // 检测是否已经是UTF-8编码
    if (!seems_utf8($content)) {
        // 如果不是UTF-8，尝试转换
        $content = utf8_encode($content);
    }

    // 处理特殊的Unicode实体编码（如 &35828; 转换为 &#35828;）
    $content = preg_replace_callback('/&(\d+);/', function($matches) {
        $code = intval($matches[1]);
        // 检查是否是有效的Unicode代码点
        if ($code > 0 && $code <= 0x10FFFF) {
            return '&#' . $code . ';';
        }
        return $matches[0];
    }, $content);

    // 处理可能的双重编码问题
    // 多次解码以确保完全解码
    $decoded = $content;
    for ($i = 0; $i < 3; $i++) {
        $new_decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($new_decoded === $decoded) {
            break; // 没有更多变化，停止解码
        }
        $decoded = $new_decoded;
    }
    $content = $decoded;

    // 清理HTML实体，避免双重编码
    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $content = htmlspecialchars_decode($content, ENT_QUOTES);

    // 移除不可见字符和控制字符，但保留中文字符
    $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $content);

    return $content;
}

/**
 * RSS Feed数据库连接优化
 * 确保数据库查询使用正确的字符集
 */
function paper_wp_rss_database_optimization() {
    if (is_feed()) {
        global $wpdb;

        // 设置数据库连接字符集为utf8mb4（如果支持）
        if (method_exists($wpdb, 'set_charset')) {
            $wpdb->set_charset($wpdb->dbh, 'utf8mb4');
        }

        // 或者使用SQL命令设置
        if ($wpdb->has_cap('utf8mb4')) {
            $wpdb->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        } else {
            $wpdb->query("SET NAMES utf8 COLLATE utf8_unicode_ci");
        }

        // 禁用feed缓存，确保内容编码正确
        add_filter('wp_feed_cache_transient_lifetime', '__return_zero');
    }
}
add_action('template_redirect', 'paper_wp_rss_database_optimization', 9);

/**
 * RSS内容预处理 - 额外的编码安全措施
 */
function paper_wp_rss_content_preprocessing() {
    if (is_feed()) {
        // 确保feed标题正确编码
        add_filter('wp_title_rss', function($title) {
            return paper_wp_force_utf8_encoding($title);
        });

        // 确保feed描述正确编码
        add_filter('get_bloginfo_rss', function($info, $show) {
            if (in_array($show, ['name', 'description', 'language'])) {
                return paper_wp_force_utf8_encoding($info);
            }
            return $info;
        }, 10, 2);
    }
}
add_action('template_redirect', 'paper_wp_rss_content_preprocessing', 10);

/**
 * RSS内容清理 - 移除不适合feed的HTML元素和属性
 * 清理AI摘要、特定样式块、多余class等不必要的元素
 */
function paper_wp_rss_content_cleanup($content) {
    if (is_feed()) {
        // 移除AI摘要相关div块
        $content = preg_replace('/<div[^>]*class="[^"]*ai-summary[^"]*"[^>]*>.*?<\/div>/is', '', $content);
        $content = preg_replace('/<div[^>]*class="[^"]*ai-summary-block[^"]*"[^>]*>.*?<\/div>/is', '', $content);

        // 移除特定样式块（可根据需要添加更多规则）
        $content = preg_replace('/<div[^>]*class="[^"]*hidden[^"]*"[^>]*>.*?<\/div>/is', '', $content);
        $content = preg_replace('/<div[^>]*class="[^"]*no-feed[^"]*"[^>]*>.*?<\/div>/is', '', $content);

        // 清理多余的HTML class属性
        // 移除段落标签上的markdown相关class
        $content = str_replace('<p class="markdown-paragraph">', '<p>', $content);
        $content = str_replace('<p class="markdown-p">', '<p>', $content);

        // 移除其他常见的编辑器生成的不必要class
        $content = preg_replace('/<p class="[^"]*editor[^"]*">/i', '<p>', $content);
        $content = preg_replace('/<p class="[^"]*wp-block[^"]*">/i', '<p>', $content);
        $content = preg_replace('/<p class="[^"]*block[^"]*">/i', '<p>', $content);

        // 移除内联样式（可选）
        $content = preg_replace('/\s*style="[^"]*"/i', '', $content);

        // 移除其他不必要的属性（可选）
        $content = preg_replace('/\s*data-[^=]*="[^"]*"/i', '', $content);

        // 清理多余的空行和空白
        $content = preg_replace('/\n\s*\n/', "\n", $content);
        $content = trim($content);
    }
    return $content;
}
add_filter('the_content_feed', 'paper_wp_rss_content_cleanup', 9); // 较早执行，确保在其他处理前清理

/**
 * RSS正则过滤 - 专门处理Markdown格式和p标签
 * 过滤掉内容中的Markdown符号和多余的p标签
 */
function paper_wp_rss_regex_filter($content) {
    if (is_feed()) {
        // 移除Markdown标题符号
        $content = preg_replace('/#{1,6}\s*/', '', $content);
        
        // 移除Markdown引用符号
        $content = preg_replace('/^>\s*/m', '', $content);
        
        // 移除Markdown列表符号
        $content = preg_replace('/^[\s]*[-*+]\s*/m', '', $content);
        $content = preg_replace('/^[\s]*\d+\.\s*/m', '', $content);
        
        // 移除Markdown代码块标记
        $content = preg_replace('/```[^`]*```/s', '', $content);
        $content = preg_replace('/`[^`]*`/', '', $content);
        
        // 移除Markdown粗体和斜体符号
        $content = preg_replace('/\*\*(.*?)\*\*/', '$1', $content);
        $content = preg_replace('/\*(.*?)\*/', '$1', $content);
        $content = preg_replace('/_(.*?)_/', '$1', $content);
        
        // 移除Markdown删除线
        $content = preg_replace('/~~(.*?)~~/', '$1', $content);
        
        // 移除Markdown链接格式
        $content = preg_replace('/\[(.*?)\]\((.*?)\)/', '$1', $content);
        
        // 移除Markdown图片格式
        $content = preg_replace('/!\[(.*?)\]\((.*?)\)/', '$1', $content);
        
        // 清理多余的p标签
        $content = preg_replace('/<p[^>]*>\s*<\/p>/', '', $content); // 空p标签
        $content = preg_replace('/<p>\s*<\/p>/', '', $content); // 简单空p标签
        
        // 清理连续的p标签
        $content = preg_replace('/<\/p>\s*<p>/', "\n", $content);

        // 清理多余的br标签
        $content = preg_replace('/(<br\s*\/?>\s*)+/', '<br />', $content);
        $content = preg_replace('/(<br \/>\s*){2,}/', '<br />', $content);

        // 清理多余的换行和空白
        $content = preg_replace('/\n\s*\n\s*\n+/', "\n\n", $content);
        $content = preg_replace('/^\s+|\s+$/m', '', $content);

        // 确保内容有适当的段落结构
        $content = trim($content);
    }
    return $content;
}
add_filter('the_content_feed', 'paper_wp_rss_regex_filter', 8); // 在清理前执行，优先处理Markdown格式

/**
 * RSS版权信息和原文链接
 * 在feed内容末尾添加版权声明和返回原文链接
 */
function paper_wp_rss_copyright_and_links($content) {
    if (is_feed()) {
        global $post;

        // 版权信息
        $copyright_info = '<hr><p>&copy; ' . date('Y') . ' ' . get_bloginfo('name') . '. All rights reserved.</p>';

        // 阅读原文链接
        $read_more_link = '';
        if ($post && is_single($post->ID)) {
            $read_more_link = '<p><a href="' . get_permalink($post->ID) . '">阅读原文</a></p>';
        }

        // 网站链接
        $site_link = '<p><a href="' . home_url() . '">' . get_bloginfo('name') . '</a></p>';

        $content .= $copyright_info . $read_more_link . $site_link;
    }
    return $content;
}
add_filter('the_content_feed', 'paper_wp_rss_copyright_and_links', 11); // 在清理后添加版权信息

/**
 * RSS调试信息（开发环境）
 * 在feed末尾添加编码信息，便于调试
 */
function paper_wp_rss_debug_info($content) {
    if (is_feed() && defined('WP_DEBUG') && WP_DEBUG) {
        $encoding_info = '<!-- RSS Encoding: UTF-8 | Database Charset: ' . DB_CHARSET . ' | WordPress Version: ' . get_bloginfo('version') . ' -->';
        $content .= "\n" . $encoding_info;
    }
    return $content;
}
add_filter('the_content_feed', 'paper_wp_rss_debug_info');

/**
 * ===========================================
 * RSS功能增强（可选）
 * ===========================================
 */

/**
 * 添加自定义RSS命名空间（如果需要）
 * 支持更多的RSS扩展功能
 */
function paper_wp_add_rss_namespace() {
    if (is_feed()) {
        echo 'xmlns:content="http://purl.org/rss/1.0/modules/content/" ';
        echo 'xmlns:dc="http://purl.org/dc/elements/1.1/" ';
        echo 'xmlns:media="http://search.yahoo.com/mrss/" ';
    }
}
// add_action('rss2_ns', 'paper_wp_add_rss_namespace'); // 如需要可取消注释
