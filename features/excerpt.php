<?php
if (!defined('ABSPATH')) exit;

/**
 * 获取文章摘录（智能截取 - 纯文本）
 * @param int $post_id   文章ID
 * @param int $num_words 摘录的单词数量
 * @return string
 */
function paper_wp_get_smart_excerpt($post_id, $num_words = 100) {
    $post = get_post($post_id);
    if (!$post) {
        return '';
    }

    // 优先使用手动摘录
    if (has_excerpt($post)) {
        return wp_trim_words(get_the_excerpt($post), $num_words, '...');
    }

    // 使用内容生成摘录
    $content = wp_strip_all_tags(strip_shortcodes(get_the_content(null, false, $post)));
    return wp_trim_words($content, $num_words, '...');
}

/**
 * [核心优化函数] 移除内容中的特定模块、转换格式并处理段落间距。
 * 使用 DOMDocument 精确处理HTML结构，将换行逻辑集中于此。
 *
 * @param string $content HTML内容
 * @return string 清理和格式化后的内容
 */
function paper_wp_remove_ai_summary($content) {
    if (empty(trim($content)) || !class_exists('DOMDocument')) {
        return $content;
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML('<?xml encoding="UTF-8"><div>' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    // 1. 定义要完全移除的元素的XPath查询规则
    $selectors_to_remove = [
        '//*[contains(concat(" ", normalize-space(@class), " "), " ai-summary ")]',
        '//*[contains(concat(" ", normalize-space(@class), " "), " ai-summary-block ")]',
        '//comment()[contains(., "AI Summary")]',
        '//pre', // 移除 <pre> 代码块
        '//table', // 移除所有表格
        '//*[contains(concat(" ", normalize-space(@class), " "), " shortcode-video-wrapper ")]',
        '//*[contains(concat(" ", normalize-space(@class), " "), " shortcode-code-container ")]',
        '//*[contains(concat(" ", normalize-space(@class), " "), " paper-music-player ")]',
        '//*[contains(@id, "aplayer")]',
        '//*[contains(concat(" ", normalize-space(@class), " "), " shortcode-error ")]',
    ];

    foreach ($selectors_to_remove as $selector) {
        $nodes = $xpath->query($selector);
        foreach ($nodes as $node) {
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }
    }

    // 2. 将非 markdown 的标题、引用等块级元素转换为带间距标记的段落
    // 注意：保留 markdown 生成的标题和引用块，它们已经有正确的 class 和空行标记
    $selectors_to_flatten = [
        '//h1[not(contains(@class, "markdown-heading"))]',
        '//h2[not(contains(@class, "markdown-heading"))]',
        '//h3[not(contains(@class, "markdown-heading"))]',
        '//h4[not(contains(@class, "markdown-heading"))]',
        '//h5[not(contains(@class, "markdown-heading"))]',
        '//h6[not(contains(@class, "markdown-heading"))]',
        '//blockquote[not(contains(@class, "markdown-blockquote"))]',
        '//div[contains(@class, "custom-container")]'
    ];
    foreach ($selectors_to_flatten as $selector) {
        $nodes = $xpath->query($selector);
        foreach ($nodes as $node) {
            if ($node->parentNode) {
                $p = $dom->createElement('p', trim($node->textContent));

                // **核心间距逻辑**: 检查该元素前是否有代表"空一行"的换行符文本节点
                $prev_sibling = $node->previousSibling;
                if ($prev_sibling && $prev_sibling->nodeType === XML_TEXT_NODE && strpos($prev_sibling->nodeValue, "\n\n") !== false) {
                    $p->setAttribute('class', 'has-space-before');
                }
                
                $node->parentNode->replaceChild($p, $node);
            }
        }
    }

    // 3. 处理段落内的单换行 (文字段落内的换行 -> <br>)
    // 注意：只处理没有 markdown-paragraph class 的段落，保留 markdown 处理后的段落格式
    $paragraphs = $xpath->query('//p');
    foreach ($paragraphs as $p) {
        $fragment = $dom->createDocumentFragment();
        $needs_br = false;
        
        foreach ($p->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text = $child->nodeValue;
                if (strpos($text, "\n") !== false) {
                    $text_parts = explode("\n", $text);
                    foreach ($text_parts as $i => $part) {
                        if ($i > 0) {
                            $fragment->appendChild($dom->createElement('br'));
                        }
                        if (!empty($part)) {
                            $fragment->appendChild($dom->createTextNode($part));
                        }
                    }
                    $needs_br = true;
                } else {
                    $fragment->appendChild($dom->createTextNode($text));
                }
            } else {
                $fragment->appendChild($child->cloneNode(true));
            }
        }
        
        if ($needs_br) {
            while ($p->hasChildNodes()) {
                $p->removeChild($p->firstChild);
            }
            $p->appendChild($fragment);
        }
    }
    
    // 提取body内的HTML
    $body_node = $dom->getElementsByTagName('body')->item(0);
    $cleaned_html = '';
    if ($body_node) {
        foreach ($body_node->childNodes as $node) {
            $cleaned_html .= $dom->saveHTML($node);
        }
    } else {
        // 如果没有body节点，从div节点获取（因为我们包装在div中）
        $div_node = $dom->getElementsByTagName('div')->item(0);
        if ($div_node) {
            foreach ($div_node->childNodes as $node) {
                $cleaned_html .= $dom->saveHTML($node);
            }
        } else {
            $cleaned_html = $dom->saveHTML();
            $cleaned_html = preg_replace(['/^<\?xml[^>]*\?>/i', '/^<!DOCTYPE[^>]*>/i', '/<\/?html[^>]*>/i', '/<\/?body[^>]*>/i', '/<\/?head[^>]*>.*?<\/head>/is'], '', $cleaned_html);
        }
    }
    
    if (empty(trim($cleaned_html))) {
        return $content;
    }
    
    // 4. 最终清理
    // 保留带有特殊class的段落和标题元素，只删除真正空的段落
    $cleaned_html = preg_replace('/<p(?!\s+[^>]*class=["\'][^"\']*(?:has-space-before|markdown-paragraph|has-minimal-space-before)[^"\']*["\'])[^>]*>\s*(?:<br\s*\/?>\s*)*\s*<\/p>/is', '', $cleaned_html);
    
    // 清理多余空白，保留单个换行以支持CSS相邻选择器
    $cleaned_html = preg_replace(['/>\s*\n\s*\n\s*</', '/>\s+</'], ['><', '><'], $cleaned_html);
    
    return trim($cleaned_html);
}


/**
 * [主函数] 获取文章摘录内容（保留HTML格式）
 *
 * @param int|null $post_id 文章ID，null表示当前文章。
 * @return string 摘录的HTML内容。
 */
function paper_wp_get_smart_html_excerpt($post_id = null, $is_first = false) {
    global $post;

    $current_post = $post_id ? get_post($post_id) : $post;
    if (!$current_post) {
        return '';
    }

    // 获取原始内容
    $content = get_the_content(null, false, $current_post);
    if (empty($content)) {
        return '';
    }

    // 应用 the_content 过滤器，处理 markdown 和 shortcode
    // 临时禁用 wpautop，因为 markdown 解析器已经处理了段落格式
    $wpautop_priority = has_filter('the_content', 'wpautop');
    if ($wpautop_priority !== false) {
        remove_filter('the_content', 'wpautop', $wpautop_priority);
        $content = apply_filters('the_content', $content);
        add_filter('the_content', 'wpautop', $wpautop_priority);
    } else {
        $content = apply_filters('the_content', $content);
    }

    // 移除 WordPress "more" 标签生成的链接（包含 #more- 的链接）
    $content = preg_replace('/<a[^>]*href="[^"]*#more-\d+"[^>]*>.*?<\/a>/is', '', $content);
    $content = preg_replace('/<span[^>]*aria-label="[^"]*继续阅读[^"]*"[^>]*>.*?<\/span>/is', '', $content);
    $content = preg_replace('/<span[^>]*>.*?更多.*?<\/span>/is', '', $content);
    $content = preg_replace('/<span[^>]*>.*?\(更多[^\)]*\)<\/span>/is', '', $content);

    // 1. 清理和格式化内容
    $cleaned_content = paper_wp_remove_ai_summary($content);
    if (empty(trim($cleaned_content))) {
        $cleaned_content = $content;
    }
    
    // 2. 根据主题设置截断内容
    $word_limit = Paper_Settings_Manager::get_field('paper_wp_theme_settings', 'excerpt_word_limit', 500);
    if (!is_numeric($word_limit) || intval($word_limit) <= 0) {
        $word_limit = 500;
    } else {
        $word_limit = intval($word_limit);
    }
    $truncated_content = paper_wp_truncate_html($cleaned_content, $word_limit);

    // 3. 根据主题设置处理图片显示
    $image_mode = Paper_Settings_Manager::get_field('paper_wp_theme_settings', 'excerpt_image_mode', 'all');
    $final_content = paper_wp_process_excerpt_images($truncated_content, $content, $image_mode);

    // 4. 为内容中的链接和图片添加额外处理
    $final_content = paper_wp_finalize_excerpt_links($final_content, $current_post->ID, $is_first);

    return $final_content;
}

/**
 * 按字符数截断HTML内容，并确保标签闭合。
 * @param string $html HTML内容
 * @param int $length 字符限制
 * @return string 截断后的HTML
 */
function paper_wp_truncate_html($html, $length) {
    // 确保长度是有效的正整数
    $length = max(1, intval($length));
    
    // 如果输入为空，直接返回
    if (empty($html)) {
        return $html;
    }
    
    $text_length = mb_strlen(trim(wp_strip_all_tags($html)), 'UTF-8');
    if ($text_length <= $length) {
        return $html;
    }

    $result = '';
    $current_length = 0;
    $html = preg_replace('/<\?xml[^>]*\?>/i', '', $html);
    $parts = preg_split('/(<[^>]+>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    
    foreach ($parts as $part) {
        if (empty($part)) {
            continue;
        }
        if (preg_match('/^<[^>]+>$/u', $part)) {
            $result .= $part;
        } else {
            $trimmed_part = trim($part);
            if (empty($trimmed_part)) {
                $result .= $part;
                continue;
            }
            $part_len = mb_strlen($trimmed_part, 'UTF-8');
            if ($current_length + $part_len > $length) {
                $remaining = $length - $current_length;
                if ($remaining > 0) {
                    $result .= mb_substr($trimmed_part, 0, $remaining, 'UTF-8');
                }
                break;
            }
            $result .= $part;
            $current_length += $part_len;
        }
    }
    
    return paper_wp_close_html_tags($result);
}

/**
 * 确保HTML标签完整闭合
 *
 * @param string $html HTML内容
 * @return string 标签完整闭合的HTML内容
 */
function paper_wp_close_html_tags($html) {
    if (empty($html) || !class_exists('DOMDocument')) {
        return $html;
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $div = $dom->getElementsByTagName('div')->item(0);
    $closed_html = '';
    if ($div) {
        foreach ($div->childNodes as $node) {
            $closed_html .= $dom->saveHTML($node);
        }
    }
    return $closed_html;
}

/**
 * 处理摘录中的图片显示。
 * @param string $truncated_content 截断后的内容
 * @param string $full_content      完整的原始内容（用于提取图片）
 * @param string $mode              图片模式: 'all', 'first', 'random', 'none'
 * @return string 处理后的内容
 */
function paper_wp_process_excerpt_images($truncated_content, $full_content, $mode) {
    // 移除不完整的代码块（只有复制按钮但没有 pre 标签的）
    // 匹配 code-block-container 并检查内部是否有 <pre> 标签
    $truncated_content = preg_replace_callback(
        '/<div[^>]*class="[^"]*code-block-container[^"]*"[^>]*>.*?<\/div>/is',
        function($matches) {
            // 如果代码块容器中没有 <pre> 标签，返回空字符串（删除）
            if (stripos($matches[0], '<pre') === false) {
                return '';
            }
            // 否则保留原内容
            return $matches[0];
        },
        $truncated_content
    );
    
    // 移除首页预览中的 markdown 分割线
    $truncated_content = preg_replace('/<hr[^>]*class="[^"]*markdown-hr[^"]*"[^>]*\/?>/is', '', $truncated_content);
    
    // 如果模式是显示所有图片，返回处理后的内容
    if ($mode === 'all') {
        return $truncated_content;
    }

    // 移除截断内容中的所有图片和figure标签
    $content_stripped = preg_replace('/<figure[^>]*>.*?<\/figure>|<img[^>]*>/is', '', $truncated_content);
    
    // 移除可能残留的空链接标签 (通常是包裹图片的链接)
    $content_stripped = preg_replace('/<a[^>]*>\s*<\/a>/is', '', $content_stripped);
    
    // 清理移除图片后留下的空标签 (p, div)
    // 匹配空的内容，或者只包含空白字符/换行符/br标签的容器
    $content_stripped = preg_replace('/<(p|div)[^>]*>\s*(?:<br\s*\/?>\s*)*<\/\\1>/is', '', $content_stripped);

    // 清理多余的 <br> 标签 (将连续的多个 br 替换为单个，并移除开头和结尾的 br)
    $content_stripped = preg_replace('/(?:<br\s*\/?>\s*){2,}/i', '<br>', $content_stripped);
    $content_stripped = preg_replace('/^(?:\s*<br\s*\/?>\s*)+|(?:\s*<br\s*\/?>\s*)+$/i', '', $content_stripped);

    // 如果模式是不显示图片，返回清理后的内容
    if ($mode === 'none') {
        return $content_stripped;
    }

    // 获取所有图片用于 'first' 或 'random' 模式
    preg_match_all('/<img[^>]+>/i', $full_content, $all_images);
    
    // 如果没有找到图片，返回清理后的内容
    if (empty($all_images[0])) {
        return $content_stripped;
    }

    // 过滤掉太小的图片（宽度或高度小于 200px）
    $valid_images = [];
    foreach ($all_images[0] as $img_tag) {
        if (paper_wp_is_image_large_enough($img_tag)) {
            $valid_images[] = $img_tag;
        }
    }
    
    // 如果没有符合条件的图片，返回清理后的内容
    if (empty($valid_images)) {
        return $content_stripped;
    }

    $image_to_display = '';
    if ($mode === 'first') {
        $image_to_display = $valid_images[0];
    } elseif ($mode === 'random') {
        $image_to_display = $valid_images[array_rand($valid_images)];
    }

    return $image_to_display . $content_stripped;
}

/**
 * 检查图片是否足够大（用于首页预览）
 * @param string $img_tag 图片HTML标签
 * @return bool 如果图片足够大返回true，否则返回false
 */
function paper_wp_is_image_large_enough($img_tag) {
    // 最小尺寸要求
    // 宽度不能小于容器宽度（设定为 500 以保证清晰度）
    // 高度要大于 200
    $min_width = 500;
    $min_height = 200;
    
    // 1. 检查 width 属性
    if (preg_match('/width=["\']?(\d+)["\']?/i', $img_tag, $match)) {
        $width = intval($match[1]);
        if ($width < $min_width) {
            return false;
        }
    }
    
    // 2. 检查 height 属性
    if (preg_match('/height=["\']?(\d+)["\']?/i', $img_tag, $match)) {
        $height = intval($match[1]);
        if ($height < $min_height) {
            return false;
        }
    }
    
    // 3. 其他情况默认允许
    // (包括没有尺寸属性的图片，假设它们是大图)
    return true;
}

/**
 * 为摘录中的图片和链接做最后处理
 * @param string $html HTML内容
 * @param int $post_id 文章ID
 * @param bool $is_first 是否是列表中的第一篇文章（用于LCP优化）
 * @return string 处理后的HTML
 */
function paper_wp_finalize_excerpt_links($html, $post_id, $is_first = false) {
    $post_url = get_permalink($post_id);
    $post_title = get_the_title($post_id);

    return preg_replace_callback('/<img[^>]*>/i', function($matches) use ($post_url, $post_title, $is_first) {
        $img_tag = $matches[0];
        
        // 移除 width 和 height 属性，让 CSS 统一控制尺寸
        $img_tag = preg_replace('/\s+width=["\']?\d+["\']?/i', '', $img_tag);
        $img_tag = preg_replace('/\s+height=["\']?\d+["\']?/i', '', $img_tag);
        
        // 移除 style 属性，防止内联样式（如 object-fit: contain）覆盖预览图的统一样式
        $img_tag = preg_replace('/\s+style=["\'][^"\']*["\']/i', '', $img_tag);
        
        if (strpos($img_tag, 'class=') === false) {
            $img_tag = str_ireplace('<img', '<img class="excerpt-image"', $img_tag);
        } else {
            $img_tag = preg_replace('/class=(["\'])(.*?)\1/', 'class=$1$2 excerpt-image$1', $img_tag);
        }

        // LCP 优化：如果是第一篇文章的图片，添加 fetchpriority="high"
        if ($is_first) {
            $img_tag = str_replace('<img', '<img fetchpriority="high"', $img_tag);
        }

        return '<a href="' . esc_url($post_url) . '" title="' . esc_attr($post_title) . '" class="excerpt-image-link">' . $img_tag . '</a>';
    }, $html);
}

/**
 * 获取文章内容的前半部分（保留HTML格式）- 向后兼容
 * @param int|null $post_id 文章ID
 * @param null $ratio (已弃用)
 * @return string 摘录内容
 */
function paper_wp_get_half_content($post_id = null, $ratio = null, $is_first = false) {
    return paper_wp_get_smart_html_excerpt($post_id, $is_first);
}