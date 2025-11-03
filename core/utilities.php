<?php
if (!defined('ABSPATH')) exit;

/**
 * 获取文章阅读数（仅返回数字）
 */
function paper_wp_get_post_views_count($post_id) {
    $count = get_post_meta($post_id, 'post_views_count', true);
    return (int) $count;
}

/**
 * 获取文章阅读数（格式化显示）
 */
function paper_wp_get_post_views($post_id) {
    $count = paper_wp_get_post_views_count($post_id);
    return $count . ' 次阅读';
}

/**
 * 获取文章展示配图
 * 优先返回特色图，其次正文中的首张图片，最后使用默认图
 */
function paper_wp_get_post_cover_image($post_id) {
    $featured_image = get_the_post_thumbnail_url($post_id, 'medium_large');
    if (!empty($featured_image)) {
        return $featured_image;
    }

    $content = get_post_field('post_content', $post_id);
    if (!empty($content) && preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
        return $matches[1];
    }

    return '';
}

/**
 * 渲染排行文章小工具
 */
function paper_wp_render_ranked_posts_widget($title, $query_args, $cache_key, $display_count_callback, $no_data_text = '暂无数据', $title_length = 16, $use_css_truncation = true, $force_refresh = false) {
    ?>
    <section class="widget">
        <h3 class="widget-title"><?php echo esc_html($title); ?></h3>
        <ul class="widget-list">
            <?php
            $posts_html = get_transient($cache_key);
            if (false === $posts_html || !is_string($posts_html) || $force_refresh) {
                // 清除可能损坏的缓存或强制刷新
                if (!is_string($posts_html) || $force_refresh) {
                    delete_transient($cache_key);
                }
                ob_start();
                // 确保$query_args是有效的数组
                if (!is_array($query_args)) {
                    $query_args = [];
                }
                $query = new WP_Query($query_args);
                if ($query->have_posts()) {
                    while ($query->have_posts()) {
                        $query->the_post();
                        $post_title = get_the_title();
                        // 根据参数决定是否使用PHP截断还是CSS截断
                        if (!$use_css_truncation && $title_length > 0) {
                            $post_title = mb_strimwidth($post_title, 0, $title_length, '...');
                        }
                        $count_html = call_user_func($display_count_callback, get_the_ID());
                        ?>
                        <li class="widget-list-item">
                            <a href="<?php the_permalink(); ?>" title="<?php echo esc_attr(get_the_title()); ?>">
                                <span class="post-title"><?php echo esc_html($post_title); ?></span>
                                <?php if (!empty($count_html)): ?>
                                    <span class="post-count">(<?php echo $count_html; ?>)</span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <?php
                    }
                    wp_reset_postdata();
                } else {
                    echo '<li class="widget-no-data">' . esc_html($no_data_text) . '</li>';
                }
                $posts_html = ob_get_clean();
                set_transient($cache_key, $posts_html, HOUR_IN_SECONDS);
            }
            echo $posts_html;
            ?>
        </ul>
    </section>
    <?php
}

/**
 * 格式化数字显示
 */
function paper_wp_format_number($number, $decimals = 0) {
    if (!is_numeric($number)) {
        return $number;
    }

    if ($decimals > 0) {
        return number_format($number, $decimals, '.', '');
    } else {
        return (string)(int)$number;
    }
}

/**
 * 获取相对时间
 */
function paper_wp_get_relative_time($timestamp) {
    $current_time = current_time('timestamp');
    $time_diff = $current_time - $timestamp;

    if ($time_diff < 60) {
        return '刚刚';
    } elseif ($time_diff < 3600) {
        return floor($time_diff / 60) . '分钟前';
    } elseif ($time_diff < 86400) {
        return floor($time_diff / 3600) . '小时前';
    } elseif ($time_diff < 2592000) {
        return floor($time_diff / 86400) . '天前';
    } elseif ($time_diff < 31536000) {
        return floor($time_diff / 2592000) . '个月前';
    } else {
        return floor($time_diff / 31536000) . '年前';
    }
}

/**
 * 清理字符串中的特殊字符
 */
function paper_wp_sanitize_string($string) {
    // 移除HTML标签
    $string = wp_strip_all_tags($string);

    // 移除多余的空白字符
    $string = preg_replace('/\s+/', ' ', $string);

    // 移除控制字符
    $string = preg_replace('/[\x00-\x1F\x7F]/u', '', $string);

    return trim($string);
}

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
    $content = get_the_content(null, false, $post);
    $content = wp_strip_all_tags(strip_shortcodes($content)); // 加上 strip_shortcodes 更安全

    return wp_trim_words($content, $num_words, '...');
}

/**
 * 移除内容中的特定模块（如AI摘要、部分Shortcode），并清理格式以便生成摘录。
 * 使用 DOMDocument 以提高健壮性和可维护性。
 *
 * @param string $content HTML内容
 * @return string 清理后的内容
 */
function paper_wp_remove_ai_summary($content) {
    if (empty(trim($content))) {
        return $content;
    }

    // 如果没有DOMDocument，回退到简单的正则表达式处理
    if (!class_exists('DOMDocument')) {
        // 移除AI摘要相关的div块
        $content = preg_replace('/<div[^>]*class="[^"]*ai-summary[^"]*"[^>]*>.*?<\/div>/is', '', $content);
        $content = preg_replace('/<div[^>]*class="[^"]*ai-summary-block[^"]*"[^>]*>.*?<\/div>/is', '', $content);

        // 移除可能包含ai-summary类的其他元素
        $content = preg_replace('/<[^>]*class="[^"]*ai-summary[^"]*"[^>]*>.*?<\/[^>]+>/is', '', $content);

        // 移除AI摘要相关的注释
        $content = preg_replace('/<!--[^>]*AI[^>]*Summary[^>]*-->/i', '', $content);
        $content = preg_replace('/<!--[^>]*ai-summary[^>]*-->/i', '', $content);

        // 移除代码块和表格
        $content = preg_replace('/<pre[^>]*>.*?<\/pre>/is', '', $content);
        $content = preg_replace('/<code[^>]*>.*?<\/code>/is', '', $content);
        $content = preg_replace('/<table[^>]*>.*?<\/table>/is', '', $content);

        return trim($content);
    }

    // 使用DOMDocument进行更精确的处理
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);

    // 包装内容以确保正确的HTML结构
    $wrapped_content = '<div>' . $content . '</div>';
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $wrapped_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    // 1. 定义要完全移除的元素的XPath查询规则
    $selectors_to_remove = [
        '//*[contains(concat(" ", normalize-space(@class), " "), " ai-summary ")]',                  // AI摘要
        '//*[contains(concat(" ", normalize-space(@class), " "), " ai-summary-block ")]',             // AI摘要块
        '//comment()[contains(., "AI Summary")]',               // AI摘要注释
        '//*[contains(concat(" ", normalize-space(@class), " "), " markdown-inline-code ")]',      // Markdown内联代码
        '//*[contains(concat(" ", normalize-space(@class), " "), " markdown-table ")]',           // Markdown表格
        '//*[contains(concat(" ", normalize-space(@class), " "), " markdown-hr ")]',                 // Markdown分隔线
        '//*[contains(concat(" ", normalize-space(@class), " "), " shortcode-video-wrapper ")]',     // 视频 shortcode
        '//*[contains(concat(" ", normalize-space(@class), " "), " shortcode-code-container ")]',    // 代码 shortcode
        '//*[contains(concat(" ", normalize-space(@class), " "), " paper-music-player ")]',          // 音乐 shortcode
        '//*[contains(@id, "aplayer")]',                         // APlayer 音乐播放器
        '//*[contains(concat(" ", normalize-space(@class), " "), " shortcode-error ")]',             // 错误提示
    ];

    foreach ($selectors_to_remove as $selector) {
        $nodes = $xpath->query($selector);
        foreach ($nodes as $node) {
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }
    }

    // 2. 移除标签，但保留其文本内容 (转换为 <p> 或纯文本)
    // 2.1 处理引用和标题：将其内容转换为段落
    $selectors_to_flatten = [
        '//blockquote[contains(@class, "markdown-blockquote")]',
        '//h1|//h2|//h3|//h4|//h5|//h6',
        '//blockquote[contains(@class, "paper-quote")]'
    ];
    foreach ($selectors_to_flatten as $selector) {
        $nodes = $xpath->query($selector);
        foreach ($nodes as $node) {
            if ($node->parentNode) {
                $p = $dom->createElement('p', trim($node->textContent));
                $node->parentNode->replaceChild($p, $node);
            }
        }
    }

    // 2.2 特殊处理代码块：保留纯代码文本，不显示样式
    $code_block_nodes = $xpath->query('//*[contains(@class, "code-block-container")]');
    foreach ($code_block_nodes as $node) {
        if ($node->parentNode) {
            // 提取代码块中的纯文本内容
            $code_text = trim($node->textContent);
            // 如果代码文本不为空，直接替换为纯文本（不加任何标签）
            if (!empty($code_text)) {
                $text_node = $dom->createTextNode($code_text);
                $node->parentNode->replaceChild($text_node, $node);
            } else {
                // 如果没有代码内容，完全移除
                $node->parentNode->removeChild($node);
            }
        }
    }

    // 2.2 处理警告框和按钮：提取核心文本
    $alert_nodes = $xpath->query('//*[contains(@class, "custom-container")]//*[contains(@class, "content")]');
    foreach ($alert_nodes as $node) {
        if ($node->parentNode && $node->parentNode->parentNode) {
            $p = $dom->createElement('p', trim($node->textContent));
            $node->parentNode->parentNode->replaceChild($p, $node->parentNode);
        }
    }

    $button_nodes = $xpath->query('//a[contains(@class, "paper-btn")]');
    foreach ($button_nodes as $node) {
        if ($node->parentNode && $node->parentNode->parentNode) {
            $p = $dom->createElement('p', trim($node->textContent));
            $node->parentNode->parentNode->replaceChild($p, $node->parentNode);
        }
    }

    // 3. 移除内联格式标签，但保留文本
    $selectors_to_unwrap = [
        '//strong', '//em', '//del', '//mark', '//a[contains(@class, "markdown-")]'
    ];
    foreach ($selectors_to_unwrap as $selector) {
        $nodes = $xpath->query($selector);
        foreach ($nodes as $node) {
            if ($node->parentNode) {
                $textNode = $dom->createTextNode($node->textContent);
                $node->parentNode->replaceChild($textNode, $node);
            }
        }
    }

    // 4. 清理所有元素的 markdown- 相关类名，简化列表等结构
    $all_nodes_with_class = $xpath->query('//*[@class]');
    foreach ($all_nodes_with_class as $node) {
        $classes = $node->getAttribute('class');
        $new_classes = preg_replace('/\b(markdown|shortcode|paper)-[^ \s]*/', '', $classes);
        $new_classes = trim(preg_replace('/\s+/', ' ', $new_classes));
        if (empty($new_classes)) {
            $node->removeAttribute('class');
        } else {
            $node->setAttribute('class', $new_classes);
        }
    }

    // 提取内部HTML内容
    $wrapper = $dom->getElementsByTagName('div')->item(0);
    if ($wrapper) {
        $cleaned_html = '';
        foreach ($wrapper->childNodes as $node) {
            $cleaned_html .= $dom->saveHTML($node);
        }
    } else {
        $cleaned_html = $dom->saveHTML();
    }

    // 5. 最终清理
    $cleaned_html = preg_replace('/<p[^>]*>\s*<\/p>/is', '', $cleaned_html); // 移除空段落
    $cleaned_html = preg_replace('/\n\s*\n\s*\n+/', "\n\n", $cleaned_html); // 清理多余空行
    $cleaned_html = preg_replace('/>\s+</', '><', $cleaned_html); // 移除标签间的空白
    $cleaned_html = trim($cleaned_html);

    return $cleaned_html;
}

/**
 * 获取文章摘录内容（保留HTML格式）
 *
 * @param int|null $post_id 文章ID，null表示当前文章。
 * @return string 摘录的HTML内容。
 */
function paper_wp_get_smart_html_excerpt($post_id = null) {
    global $post;

    $current_post = $post_id ? get_post($post_id) : $post;
    if (!$current_post) {
        return '';
    }

    // 获取完整内容并应用所有过滤器
    $content = apply_filters('the_content', $current_post->post_content);
    if (empty($content)) {
        return '';
    }

    // 1. 清理不需要在摘录中显示的内容
    $content = paper_wp_remove_ai_summary($content);

    // 2. 根据主题设置截断内容
    $settings = get_option('paper_wp_theme_settings', []);
    $word_limit = isset($settings['excerpt_word_limit']) ? intval($settings['excerpt_word_limit']) : 500;
    if ($word_limit <= 0) $word_limit = 500;

    $truncated_content = paper_wp_truncate_html($content, $word_limit);

    // 3. 根据主题设置处理图片显示
    $image_mode = isset($settings['excerpt_image_mode']) ? $settings['excerpt_image_mode'] : 'all';
    $final_content = paper_wp_process_excerpt_images($truncated_content, $content, $image_mode);

    // 4. 为内容中的链接和图片添加额外处理（如给图片加链接）
    $final_content = paper_wp_finalize_excerpt_links($final_content, $current_post->ID);

    return $final_content;
}

/**
 * 按字符数截断HTML内容，并确保标签闭合。
 */
function paper_wp_truncate_html($html, $length) {
    // 此处可以复用您原来的 paper_wp_truncate_content_by_chars 函数
    // 它已经写得很好了，包括了标签闭合的处理
    return paper_wp_truncate_content_by_chars($html, $length);
}

/**
 * 处理摘录中的图片显示。
 * @param string $truncated_content 截断后的内容
 * @param string $full_content      完整的内容（用于提取图片）
 * @param string $mode              图片模式: 'all', 'first', 'random', 'none'
 * @return string 处理后的内容
 */
function paper_wp_process_excerpt_images($truncated_content, $full_content, $mode) {
    if ($mode === 'none') {
        return preg_replace('/<figure[^>]*>.*?<\/figure>|<img[^>]*>/is', '', $truncated_content);
    }

    if ($mode === 'all') {
        // 'all' 模式下，截断的内容里有什么图片就显示什么
        return $truncated_content;
    }

    // 对于 'first' 或 'random' 模式，需要从完整内容中找图
    preg_match_all('/<img[^>]+>/i', $full_content, $all_images);
    if (empty($all_images[0])) {
        // 原文中没有图片，直接返回截断的文本
        return preg_replace('/<figure[^>]*>.*?<\/figure>|<img[^>]*>/is', '', $truncated_content);
    }

    $image_to_display = '';
    if ($mode === 'first') {
        $image_to_display = $all_images[0][0];
    } elseif ($mode === 'random') {
        $rand_key = array_rand($all_images[0]);
        $image_to_display = $all_images[0][$rand_key];
    }

    // 先移除截断内容中可能存在的图片，然后将选中的图片插入到最前面
    $content_no_images = preg_replace('/<figure[^>]*>.*?<\/figure>|<img[^>]*>/is', '', $truncated_content);

    return $image_to_display . $content_no_images;
}

/**
 * 为摘录中的图片和链接做最后处理
 */
function paper_wp_finalize_excerpt_links($html, $post_id) {
    $post_url = get_permalink($post_id);
    $post_title = get_the_title($post_id);

    // 为所有图片添加样式类和文章链接
    $html = preg_replace_callback('/<img[^>]*>/i', function($matches) use ($post_url, $post_title) {
        $img_tag = $matches[0];

        // 检查是否已经有 class 属性
        if (strpos($img_tag, 'class=') === false) {
            $img_tag = str_ireplace('<img', '<img class="excerpt-image"', $img_tag);
        } else {
            $img_tag = preg_replace('/class=(["\'])(.*?)\1/', 'class=$1$2 excerpt-image$1', $img_tag);
        }

        // 检查是否已被链接包裹
        // (此部分可以简化，或假设摘录中的图片都需要被链接包裹)
        return '<a href="' . esc_url($post_url) . '" title="' . esc_attr($post_title) . '" class="excerpt-image-link">' . $img_tag . '</a>';
    }, $html);

    return $html;
}

/**
 * 获取文章内容的前半部分（保留HTML格式）- 向后兼容
 * 注意：此函数现在是 paper_wp_get_smart_html_excerpt 的一个兼容性包装器。
 * $ratio 参数已不再精确控制比例，而是触发默认的摘录逻辑。
 */
function paper_wp_get_half_content($post_id = null, $ratio = null) {
    // 简单地调用新的、更强大的函数
    // $ratio 参数被忽略，因为新的逻辑由主题选项统一控制
    return paper_wp_get_smart_html_excerpt($post_id);
}



/**
 * 按字数截断HTML内容
 *
 * @param string $content HTML内容
 * @param int $word_limit 字数限制
 * @return string 截断后的HTML内容
 */
function paper_wp_truncate_content_by_chars($content, $word_limit) {
    // 计算内容的纯文本字数
    $plain_text = wp_strip_all_tags($content);
    $total_chars = mb_strlen($plain_text, 'UTF-8');

    // 如果内容字数不超过限制，返回全部内容
    if ($total_chars <= $word_limit) {
        return $content;
    }

    // 按字数截断内容，确保不截断HTML标签
    $result = '';
    $current_length = 0;
    $in_tag = false;
    $tag_buffer = '';

    $chars = preg_split('//u', $content, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($chars as $char) {
        if ($char === '<') {
            $in_tag = true;
            $tag_buffer = '<';
        } elseif ($char === '>') {
            $in_tag = false;
            $tag_buffer .= '>';
            $result .= $tag_buffer;
            $tag_buffer = '';
        } elseif ($in_tag) {
            $tag_buffer .= $char;
        } else {
            $current_length++;
            $result .= $char;
            if ($current_length >= $word_limit) {
                break;
            }
        }
    }

    // 如果还有未完成的标签，补全
    if ($in_tag && !empty($tag_buffer)) {
        $result .= $tag_buffer;
    }

    // 清理截断后的HTML，确保标签完整
    $result = paper_wp_close_html_tags($result);

    return trim($result);
}

/**
 * 确保HTML标签完整闭合
 *
 * @param string $html HTML内容
 * @return string 标签完整闭合的HTML内容
 */
function paper_wp_close_html_tags($html) {
    // 使用DOMDocument来确保HTML标签完整
    if (class_exists('DOMDocument')) {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);

        // 包装内容
        $wrapped_html = '<div>' . $html . '</div>';
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $wrapped_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        // 获取内部HTML
        $div = $dom->getElementsByTagName('div')->item(0);
        if ($div) {
            $html = '';
            foreach ($div->childNodes as $node) {
                $html .= $dom->saveHTML($node);
            }
            return trim($html);
        }
    }

    // 如果DOMDocument不可用，返回原内容
    return $html;
}

/**
 * 获取站点地图URL（智能判断环境）
 * 根据服务器是否支持重写规则返回合适的URL格式
 */
function paper_wp_get_sitemap_url() {
    // 使用更通用的方法检查permalink结构
    if (get_option('permalink_structure')) {
        return home_url('/sitemap.xml');
    } else {
        return home_url('/?paper_sitemap=index');
    }
}
