<?php
/**
 * ===========================================
 * 内容解析器 - 合并的Markdown解析器
 * ===========================================


 * 📋 处理优先级
 *   - 图片解析 (优先级5) - 最早处理
 *   - 结构解析 (优先级10) - 处理标题、列表等
 *   - 格式化解析 (优先级12) - 处理文本格式
 *   - 文本解析 (优先级15) - 最后处理段落
 *
 * @author wangdaodao
 * @version 2.0.0
 * @date 2025-11-20
 */

if (!defined('ABSPATH')) exit;

/**
 * 统一的内容解析器 - 合并所有解析功能
 */
class Content_Parser_Unified {

    /**
     * 占位符存储 (用于格式化解析器)
     */
    private $placeholders = [];

    /**
     * 短代码占位符存储
     */
    private $shortcode_placeholders = [];

    /**
     * 已使用的标题ID (用于结构解析器)
     */
    private static $used_heading_ids = [];

    /**
     * 解析内容 - 按优先级顺序处理所有解析任务
     */
    public function parse($content) {
        if (empty($content)) {
            return $content;
        }

        // 初始化存储
        $this->placeholders = [];
        $this->shortcode_placeholders = [];
        self::$used_heading_ids = [];

        // 0. 短代码隔离 (最高优先级) - 首先保护短代码内容（尤其是 [code] 短代码）
        $content = $this->isolate_shortcodes($content);

        // 1. 代码块隔离 (最高优先级) - 首先保护代码块内容，避免被其他解析器处理
        $content = $this->isolate_code_blocks($content);

        // 2. 图片解析 (优先级5) - 处理Markdown图片语法
        $content = $this->parse_markdown_images($content);

        // 3. 结构解析 (优先级10) - 处理标题、列表、引用等结构元素
        $content = $this->parse_structure_elements($content);

        // 4. 格式化解析 (优先级12) - 处理文本格式化
        $content = $this->parse_inline_formats($content);

        // 5. 文本解析 (优先级15) - 处理段落和换行
        $content = $this->parse_text_elements($content);

        // 6. 恢复代码块 (最后) - 将占位符替换回完整的HTML代码块
        $content = $this->restore_code_blocks($content);

        // 7. 恢复短代码 (最后) - 将短代码占位符恢复，以便后续 do_shortcode() 处理
        $content = $this->restore_shortcodes($content);

        return $content;
    }

    // ============ 图片解析功能 (原Image_Content_Parser) ============

    /**
     * 解析标准Markdown图片语法
     */
    private function parse_markdown_images($content) {
        $pattern = '/!\[([^\]]*)\]\(\s*([^\s\)]+)(?:\s+["\']([^"\']*)["\'])?\s*\)/';

        return preg_replace_callback($pattern, function($matches) {
            $alt = isset($matches[1]) ? trim($matches[1]) : '';
            $url = isset($matches[2]) ? trim($matches[2]) : '';
            $title = isset($matches[3]) ? trim($matches[3]) : '';

            if (empty($url)) {
                return $matches[0]; // 返回原始语法
            }

            return $this->generate_image_html($url, $alt, $title);
        }, $content);
    }

    /**
     * 生成标准化的图片HTML
     */
    private function generate_image_html($url, $alt = '', $title = '') {
        // 验证URL
        $url = esc_url($url);
        if (empty($url)) {
            return '';
        }

        // 构建基本HTML属性
        $attrs = [
            'src' => $url,
            'alt' => esc_attr($alt),
            'loading' => 'lazy',
            'class' => 'markdown-image' // 添加专门的 Markdown 图片类
        ];

        // 添加标题属性
        if (!empty($title)) {
            $attrs['title'] = esc_attr($title);
        }

        // 构建img标签
        $img_html = '<img';
        foreach ($attrs as $key => $value) {
            $img_html .= ' ' . $key . '="' . $value . '"';
        }
        $img_html .= ' />';

        // 包裹灯箱链接
        $html = '<a href="' . esc_url($url) . '" rel="lightbox" title="' . esc_attr($alt) . '">';
        $html .= $img_html;
        $html .= '</a>';

        return $html;
    }

    // ============ 结构解析功能 (原Structure_Markdown_Parser) ============

    /**
     * 处理结构内容
     */
    private function parse_structure_elements($content) {
        // 一次性分割内容，避免多次explode
        $lines = explode("\n", $content);

        // 按结构重要性顺序处理，避免语法冲突
        $lines = $this->parse_tables_from_lines($lines);
        $lines = $this->parse_task_lists_from_lines($lines);
        $lines = $this->parse_headings_from_lines($lines);
        $lines = $this->parse_lists_from_lines($lines);
        $lines = $this->parse_blockquotes_from_lines($lines);
        $lines = $this->parse_horizontal_rules_from_lines($lines);

        return implode("\n", $lines);
    }

    /**
     * 生成标题锚点ID
     */
    private function generate_heading_id($text) {
        // 清理文本，生成URL友好的ID
        $id = sanitize_title($text);

        // 确保ID唯一性
        $original_id = $id;
        $counter = 1;

        while (isset(self::$used_heading_ids[$id])) {
            $id = $original_id . '-' . $counter;
            $counter++;
        }

        self::$used_heading_ids[$id] = true;
        return $id;
    }

    /**
     * 基于行的表格处理
     */
    private function parse_tables_from_lines($lines) {
        $processed_lines = [];
        $in_table = false;
        $table_rows = [];
        $header_parsed = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // 检查是否是表格行（包含|分隔符）
            if (strpos($trimmed, '|') !== false && !preg_match('/^[-*_]{3,}$/', $trimmed)) {
                // 解析表格行
                $cells = array_map('trim', explode('|', trim($trimmed, '|')));
                $table_rows[] = $cells;

                // 检查是否是分隔行
                if (!$header_parsed && count($table_rows) === 2) {
                    $is_separator_row = true;
                    foreach ($cells as $cell) {
                        if (!preg_match('/^:?-+:?$/', $cell)) {
                            $is_separator_row = false;
                            break;
                        }
                    }

                    if ($is_separator_row) {
                        $header_parsed = true;
                        continue; // 跳过分隔行
                    }
                }

                if (!$in_table) {
                    $in_table = true;
                }
                continue;
            }

            // 非表格行，处理之前的表格
            if ($in_table) {
                if (!empty($table_rows)) {
                    $table_html = $this->generate_table_html($table_rows, $header_parsed);
                    $processed_lines[] = $table_html;
                }
                $in_table = false;
                $table_rows = [];
                $header_parsed = false;
            }

            $processed_lines[] = $line;
        }

        // 处理最后的表格
        if ($in_table && !empty($table_rows)) {
            $table_html = $this->generate_table_html($table_rows, $header_parsed);
            $processed_lines[] = $table_html;
        }

        return $processed_lines;
    }

    /**
     * 生成表格HTML
     */
    private function generate_table_html($rows, $has_header) {
        if (empty($rows)) return '';

        $html = '<table class="markdown-table">';

        $start_row = $has_header ? 1 : 0;

        // 处理表头
        if ($has_header && isset($rows[0])) {
            $html .= '<thead><tr>';
            foreach ($rows[0] as $cell) {
                $cell_content = $this->parse_inline_markdown($cell);
                $html .= '<th class="markdown-table-header">' . $cell_content . '</th>';
            }
            $html .= '</tr></thead>';
        }

        // 处理表体
        $html .= '<tbody>';
        for ($i = $start_row; $i < count($rows); $i++) {
            if ($has_header && $i === 1) continue; // 跳过分隔行

            $html .= '<tr>';
            foreach ($rows[$i] as $cell) {
                $cell_content = $this->parse_inline_markdown($cell);
                $html .= '<td class="markdown-table-cell">' . $cell_content . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * 基于行的任务列表处理
     */
    private function parse_task_lists_from_lines($lines) {
        $processed_lines = [];
        $in_task_list = false;
        $task_items = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // 检查是否是任务列表项
            if (preg_match('/^[-*+]\s+\[([ x])\]\s+(.+)$/i', $trimmed, $matches)) {
                if (!$in_task_list) {
                    $in_task_list = true;
                    $task_items = [];
                }

                $checked = strtolower($matches[1]) === 'x';
                $text = trim($matches[2]);
                $checkbox_class = $checked ? 'checked' : 'unchecked';

                $task_items[] = '<li class="markdown-task-item ' . $checkbox_class . '">' .
                               '<input type="checkbox" ' . ($checked ? 'checked' : '') . ' disabled class="markdown-task-checkbox"> ' .
                               '<span class="markdown-task-text">' . $this->parse_inline_markdown($text) . '</span>' .
                               '</li>';
                continue;
            }

            // 非任务列表行，关闭之前的任务列表
            if ($in_task_list) {
                if (!empty($task_items)) {
                    $task_html = '<ul class="markdown-task-list">' . implode('', $task_items) . '</ul>';
                    $processed_lines[] = $task_html;
                }
                $in_task_list = false;
                $task_items = [];
            }

            $processed_lines[] = $line;
        }

        // 处理最后的可能任务列表
        if ($in_task_list && !empty($task_items)) {
            $task_html = '<ul class="markdown-task-list">' . implode('', $task_items) . '</ul>';
            $processed_lines[] = $task_html;
        }

        return $processed_lines;
    }

    /**
     * 基于行的标题处理
     */
    private function parse_headings_from_lines($lines) {
        $processed_lines = [];

        foreach ($lines as $line) {
            // 检查是否是标题行
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $matches)) {
                $level = strlen($matches[1]);
                $text = $this->parse_inline_markdown(trim($matches[2]));
                $id = $this->generate_heading_id(strip_tags($text));

                $processed_lines[] = "<h{$level} id=\"{$id}\" class=\"markdown-heading markdown-h{$level}\">{$text}</h{$level}>";
            } else {
                $processed_lines[] = $line;
            }
        }

        return $processed_lines;
    }

    /**
     * 基于行的列表处理
     */
    private function parse_lists_from_lines($lines) {
        $processed_lines = [];
        $current_list_type = null;
        $current_list_items = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // 检查是否是列表项
            $is_unordered = preg_match('/^[-*+]\s+(.+)$/', $trimmed, $unordered_matches);
            $is_ordered = preg_match('/^\d+\.\s+(.+)$/', $trimmed, $ordered_matches);

            if ($is_unordered || $is_ordered) {
                $list_type = $is_unordered ? 'ul' : 'ol';
                $matches = $is_unordered ? $unordered_matches : $ordered_matches;
                $item_content = isset($matches[1]) ? trim($matches[1]) : '';

                // 如果是不同类型的列表，先关闭之前的列表
                if ($current_list_type !== null && $current_list_type !== $list_type) {
                    $this->close_current_list($processed_lines, $current_list_items, $current_list_type);
                    $current_list_items = [];
                }

                $current_list_type = $list_type;
                $current_list_items[] = '<li class="markdown-list-item">' . $this->parse_inline_markdown($item_content) . '</li>';
                continue;
            }

            // 非列表行，关闭当前列表
            if ($current_list_type !== null) {
                $this->close_current_list($processed_lines, $current_list_items, $current_list_type);
                $current_list_type = null;
                $current_list_items = [];
            }

            $processed_lines[] = $line;
        }

        // 处理最后的列表
        if ($current_list_type !== null) {
            $this->close_current_list($processed_lines, $current_list_items, $current_list_type);
        }

        return $processed_lines;
    }

    /**
     * 关闭当前列表
     */
    private function close_current_list(&$processed_lines, $items, $type) {
        if (!empty($items)) {
            $html = '<' . $type . ' class="markdown-list">' . implode('', $items) . '</' . $type . '>';
            $processed_lines[] = $html;
        }
    }

    /**
     * 基于行的引用块处理
     */
    private function parse_blockquotes_from_lines($lines) {
        $processed_lines = [];
        $in_blockquote = false;
        $blockquote_lines = [];

        foreach ($lines as $line) {
            // 检查是否是引用行
            if (preg_match('/^>\s?(.*)$/', $line, $matches)) {
                if (!$in_blockquote) {
                    $in_blockquote = true;
                    $blockquote_lines = [];
                }
                $blockquote_lines[] = $matches[1];
                continue;
            }

            // 非引用行，处理之前的引用块
            if ($in_blockquote) {
                if (!empty($blockquote_lines)) {
                    $blockquote_content = $this->process_blockquote_content($blockquote_lines);
                    $processed_lines[] = '<blockquote class="markdown-blockquote">' . $blockquote_content . '</blockquote>';
                }
                $in_blockquote = false;
                $blockquote_lines = [];
            }

            $processed_lines[] = $line;
        }

        // 处理最后的引用块
        if ($in_blockquote && !empty($blockquote_lines)) {
            $blockquote_content = $this->process_blockquote_content($blockquote_lines);
            $processed_lines[] = '<blockquote class="markdown-blockquote">' . $blockquote_content . '</blockquote>';
        }

        return $processed_lines;
    }

    /**
     * 处理引用块内容
     */
    private function process_blockquote_content($lines) {
        if (empty($lines)) return '';

        // 处理嵌套引用：移除行首的引用标记
        $processed_lines = [];
        foreach ($lines as $line) {
            $processed_line = preg_replace('/^>+\s?/', '', $line);
            $processed_lines[] = $processed_line;
        }

        // 将连续的非空行合并成段落，空行分隔段落
        $paragraphs = [];
        $current_paragraph = [];

        foreach ($processed_lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                // 空行，结束当前段落
                if (!empty($current_paragraph)) {
                    $paragraphs[] = implode("\n", $current_paragraph);
                    $current_paragraph = [];
                }
            } else {
                $current_paragraph[] = $line;
            }
        }

        // 处理最后一个段落
        if (!empty($current_paragraph)) {
            $paragraphs[] = implode("\n", $current_paragraph);
        }

        // 处理每个段落的内联Markdown语法
        $processed_paragraphs = [];
        foreach ($paragraphs as $paragraph) {
            $processed_paragraphs[] = $this->parse_inline_markdown($paragraph);
        }

        // 用段落标签包装
        if (count($processed_paragraphs) === 1) {
            return $processed_paragraphs[0];
        } else {
            return implode("</p>\n<p>", $processed_paragraphs);
        }
    }

    /**
     * 基于行的水平分隔线处理
     */
    private function parse_horizontal_rules_from_lines($lines) {
        $processed_lines = [];

        foreach ($lines as $line) {
            // 检查是否是分隔线
            if (preg_match('/^[-*_]{3,}$/', trim($line))) {
                $processed_lines[] = '<hr class="markdown-hr">';
            } else {
                $processed_lines[] = $line;
            }
        }

        return $processed_lines;
    }

    /**
     * 处理行内的Markdown语法
     */
    private function parse_inline_markdown($text) {
        if (empty($text)) return $text;

        // 粗体
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        // 斜体
        $text = preg_replace('/(?<!\*)\*([^*\n]+?)\*(?!\*)/', '<em>$1</em>', $text);
        // 删除线
        $text = preg_replace('/~~(.+?)~~/', '<del>$1</del>', $text);
        // 内联代码
        $text = preg_replace_callback('/`([^`]+)`/', function($m) {
            return '<code>' . esc_html($m[1]) . '</code>';
        }, $text);
        // 链接
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function($m) {
            return '<a href="' . esc_url($m[2]) . '" target="_blank" rel="noopener noreferrer">' . esc_html($m[1]) . '</a>';
        }, $text);

        return $text;
    }

    // ============ 短代码保护功能 ============

    /**
     * 隔离短代码 - 保护短代码不被Markdown解析器处理
     */
    private function isolate_shortcodes($content) {
        // 使用WordPress的get_shortcode_regex来匹配短代码
        // 如果不可用，使用简化的正则表达式
        
        // 优先保护 [code] 短代码，因为它最容易与Markdown语法冲突
        $content = $this->isolate_code_shortcodes($content);
        
        // 然后保护其他常见短代码
        $shortcodes = ['alert', 'button', 'quote', 'video', 'music', 'book', 'fullimage', 'ai_summary', 'heading'];
        foreach ($shortcodes as $tag) {
            $content = $this->isolate_specific_shortcode($content, $tag);
        }
        
        return $content;
    }
    
    /**
     * 隔离 [code] 短代码
     */
    private function isolate_code_shortcodes($content) {
        // 匹配 [code]...[/code] 和 [code attr="val"]...[/code]
        $pattern = '/\[code(?:\s+[^\]]+)?\].*?\[\/code\]/s';
        
        return preg_replace_callback($pattern, function($matches) {
            $placeholder = '<!--SHORTCODE_CODE_' . uniqid() . '-->';
            $this->shortcode_placeholders[$placeholder] = $matches[0];
            return $placeholder;
        }, $content);
    }
    
    /**
     * 隔离特定短代码
     */
    private function isolate_specific_shortcode($content, $tag) {
        // 匹配自闭合短代码 [tag attr="val"]
        $self_closing = '/\[' . preg_quote($tag, '/') . '(?:\s+[^\]]+)?\]/';
        $content = preg_replace_callback($self_closing, function($matches) {
            $placeholder = '<!--SHORTCODE_' . uniqid() . '-->';
            $this->shortcode_placeholders[$placeholder] = $matches[0];
            return $placeholder;
        }, $content);
        
        // 匹配带结束标签的短代码 [tag]...[/tag] 和 [tag attr="val"]...[/tag]
        $with_closing = '/\[' . preg_quote($tag, '/') . '(?:\s+[^\]]+)?\].*?\[\/' . preg_quote($tag, '/') . '\]/s';
        $content = preg_replace_callback($with_closing, function($matches) {
            $placeholder = '<!--SHORTCODE_' . uniqid() . '-->';
            $this->shortcode_placeholders[$placeholder] = $matches[0];
            return $placeholder;
        }, $content);
        
        return $content;
    }

    /**
     * 恢复短代码 - 将占位符替换回原始短代码
     */
    private function restore_shortcodes($content) {
        foreach ($this->shortcode_placeholders as $placeholder => $shortcode) {
            $content = str_replace($placeholder, $shortcode, $content);
        }
        return $content;
    }

    // ============ 格式化解析功能 (原Formatting_Markdown_Parser) ============


    /**
     * 隔离代码块
     */
    private function isolate_code_blocks($content) {
        // 将内容按行分割处理
        $lines = explode("\n", str_replace("\r", "", $content));
        $processed_lines = [];
        $in_code_block = false;
        $code_block_content = '';
        $code_block_lang = '';
        $code_block_start_marker = '';

        foreach ($lines as $line) {
            // 检查是否是代码块开始标记
            if (!$in_code_block) {
                $matched = false;
                $matches = [];

                // 支持标准的Markdown代码块格式
                if (preg_match('/^```\s*(\w*)$/', trim($line), $matches)) {
                    $matched = true;
                    $marker = '```';
                } elseif (preg_match('/^" `\s*(\w*)$/', trim($line), $matches)) {
                    $matched = true;
                    $marker = '"`';
                }

                if ($matched) {
                    $in_code_block = true;
                    $code_block_lang = $matches[1] ?? '';
                    $code_block_start_marker = $marker;
                    $code_block_content = '';
                    continue;
                }
            } else {
                // 检查是否是代码块结束标记
                $end_marker = $this->get_code_block_end_marker($code_block_start_marker);
                if (trim($line) === $end_marker) {
                    // 生成完整的HTML代码块
                    $html_output = $this->generate_code_block_html($code_block_lang, $code_block_content);

                    // 创建占位符
                    $placeholder = '<!--CODEBLOCK_' . uniqid() . '-->';

                    // 存储占位符和对应的HTML
                    $this->placeholders[$placeholder] = $html_output;

                    // 在内容中使用占位符
                    $processed_lines[] = $placeholder;

                    $in_code_block = false;
                    continue;
                }
            }

            // 处理代码块内容或普通行
            if ($in_code_block) {
                $code_block_content .= $line . "\n";
            } else {
                $processed_lines[] = $line;
            }
        }

        // 处理未闭合的代码块
        if ($in_code_block) {
            $html_output = '<pre><code>' . esc_html($code_block_content) . '</code></pre>';
            $placeholder = '<!--CODEBLOCK_' . uniqid() . '-->';
            $this->placeholders[$placeholder] = $html_output;
            $processed_lines[] = $placeholder;
        }

        return implode("\n", $processed_lines);
    }

    /**
     * 生成代码块HTML
     */
    private function generate_code_block_html($lang, $content) {
        // 处理语言标识符
        $final_lang = $lang;
        $final_code = trim($content);

        // 如果没有语言标识符，检查第一行是否是注释
        if (empty($final_lang)) {
            $code_lines = explode("\n", $final_code);
            $first_line = trim($code_lines[0] ?? '');
            if (preg_match('/^(\/\/|#|\/\*|\*|<!--)\s*(.+)/', $first_line, $comment_match)) {
                // 移除注释行
                array_shift($code_lines);
                $final_code = implode("\n", $code_lines);
            }
        }

        // 生成HTML结构
        $language_class = !empty($final_lang) ? esc_attr($final_lang) : 'markup';
        $code_id = 'code-block-' . uniqid();

        $html = '<div class="code-block-container">' .
                '<button class="copy-code-btn" data-target="' . $code_id . '" aria-label="复制代码">' .
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M6.9998 6V3C6.9998 2.44772 7.44752 2 7.9998 2H19.9998C20.5521 2 20.9998 2.44772 20.9998 3V17C20.9998 17.5523 20.5521 18 19.9998 18H16.9998V20.9991C16.9998 21.5519 16.5499 22 15.993 22H4.00666C3.45059 22 3 21.5554 3 20.9991L3.0026 7.00087C3.0027 6.44811 3.45264 6 4.00942 6H6.9998ZM5.00242 8L5.00019 20H14.9998V8H5.00242ZM8.9998 6H16.9998V16H18.9998V4H8.9998V6Z"></path></svg>' .
            '</button>' .
                '<pre id="' . $code_id . '" class="line-numbers language-' . $language_class . '"><code class="language-' . $language_class . '">' . esc_html($final_code) . '</code></pre>' .
                '</div>';

        // 使用WordPress钩子允许自定义HTML结构
        return apply_filters('my_markdown_parser_code_block_html', $html, $final_lang, $final_code, $code_id);
    }

    /**
     * 获取代码块结束标记
     */
    private function get_code_block_end_marker($start_marker) {
        $marker_map = [
            '「' => '」',
        ];

        return $marker_map[$start_marker] ?? $start_marker;
    }

    /**
     * 恢复代码块
     */
    private function restore_code_blocks($content) {
        foreach ($this->placeholders as $placeholder => $html) {
            $content = str_replace($placeholder, $html, $content);
        }
        return $content;
    }

    /**
     * 处理内联格式化语法
     */
    private function parse_inline_formats($content) {
        // 定义所有内联格式的正则表达式和回调函数
        $patterns = [
            // 内联代码（优先级最高）
            '/`([^`\n]+)`/' => function($matches) {
                return '<code class="markdown-inline-code">' . esc_html($matches[1]) . '</code>';
            },

            // 粗体
            '/\*\*(.+?)\*\*/' => function($matches) {
                return '<strong class="markdown-bold">' . $matches[1] . '</strong>';
            },

            // 斜体
            '/(?<!\*)\*([^*\n]+?)\*(?!\*)/' => function($matches) {
                return '<em class="markdown-italic">' . $matches[1] . '</em>';
            },

            // 删除线
            '/~~(.+?)~~/' => function($matches) {
                return '<del class="markdown-strikethrough">' . $matches[1] . '</del>';
            },

            // 高亮
            '/==(.+?)==/' => function($matches) {
                return '<mark class="markdown-highlight">' . $matches[1] . '</mark>';
            },

            // 超链接
            '/\[([^\]]+)\]\(([^)]+)\)/' => function($matches) {
                $text = esc_html($matches[1]);
                $url = esc_url($matches[2]);
                return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer" class="markdown-link">' . $text . '</a>';
            },
        ];

        // 使用 preg_replace_callback_array 一次性处理所有格式
        return preg_replace_callback_array($patterns, $content);
    }

    // ============ 文本解析功能 (原Text_Markdown_Parser) ============

    /**
     * 处理文本内容
     */
    private function parse_text_elements($content) {
        // 处理段落和换行 - 基于HTML块分割
        $content = $this->process_paragraphs_from_html($content);

        // 清理多余空白
        $content = $this->clean_whitespace($content);

        return $content;
    }

    /**
     * 处理段落 - 基于HTML块分割而不是Markdown语法
     */
    private function process_paragraphs_from_html($content) {
        // 将内容按HTML块级标签分割，但保留分隔符之间的空白
        $parts = $this->split_content_by_html_blocks($content);

        $processed = [];

        foreach ($parts as $index => $part) {
            if ($this->is_html_block($part)) {
                // HTML块保持原样
                // 检查前面是否有空白（换行），如果有，需要添加标记类
                $has_double_newline_before = false;
                $has_single_newline_before = false;

                if ($index > 0) {
                    $prev_part = $parts[$index - 1];
                    // 如果前一个部分不是HTML块，检查其末尾的换行
                    if (!$this->is_html_block($prev_part)) {
                        if (preg_match('/\n\s*\n\s*$/', $prev_part)) {
                            $has_double_newline_before = true;
                        } elseif (preg_match('/\n\s*$/', $prev_part)) {
                            $has_single_newline_before = true;
                        }
                    } else {
                        // 如果前一个部分也是HTML块，查找两个HTML块之间的空白部分
                        for ($i = $index - 1; $i >= 0; $i--) {
                            $check_part = $parts[$i];
                            if (!$this->is_html_block($check_part)) {
                                // 这是一个空白部分
                                if (preg_match('/\n\s*\n/', $check_part)) {
                                    $has_double_newline_before = true;
                                } elseif (preg_match('/\n/', $check_part)) {
                                    $has_single_newline_before = true;
                                }
                                break;
                            }
                        }
                    }
                }

                // 如果有双换行，添加一个标记类，以便CSS识别
                if ($has_double_newline_before) {
                    $part = $this->add_spacing_marker_to_html_block($part, true);
                } elseif ($has_single_newline_before) {
                    $part = $this->add_spacing_marker_to_html_block($part, false);
                }

                $processed[] = $part;
            } else {
                // 纯文本片段进行段落处理
                // 检查文本块末尾的换行，以便后续HTML块能正确识别间距
                $has_double_newline_before_next = false;
                $has_single_newline_before_next = false;

                if ($index < count($parts) - 1) {
                    // 检查下一个部分是否是HTML块
                    $next_part = $parts[$index + 1];
                    if ($this->is_html_block($next_part)) {
                        // 检查文本块末尾是否有双换行（空一行）
                        if (preg_match('/\n\s*\n\s*$/', $part)) {
                            $has_double_newline_before_next = true;
                        }
                        // 检查是否有单换行
                        elseif (preg_match('/\n\s*$/', $part)) {
                            $has_single_newline_before_next = true;
                        }
                    }
                }

                $processed_part = $this->process_text_block_paragraphs($part, $has_double_newline_before_next, $has_single_newline_before_next);
                if (!empty($processed_part)) {
                    $processed[] = $processed_part;
                }
            }
        }

        // 确保段落和HTML块之间没有多余的换行
        return preg_replace('/>\s*\n\s*</', '><', implode("\n", $processed));
    }

    /**
     * 为HTML块添加间距标记类
     */
    private function add_spacing_marker_to_html_block($html_block, $has_double_newline = false) {
        // 为标题和引用块添加标记类，表示前面有换行
        if (preg_match('/^<(h[1-6]|blockquote)([^>]*)>/', $html_block, $matches)) {
            $tag = $matches[1];
            $attrs = $matches[2];

            // 检查是否已有class属性
            if (preg_match('/class="([^"]*)"/', $attrs, $class_match)) {
                $classes = $class_match[1];
                if ($has_double_newline) {
                    $classes .= ' has-space-before';
                } else {
                    $classes .= ' has-minimal-space-before';
                }
                $attrs = preg_replace('/class="[^"]*"/', 'class="' . $classes . '"', $attrs);
            } else {
                if ($has_double_newline) {
                    $attrs .= ' class="has-space-before"';
                } else {
                    $attrs .= ' class="has-minimal-space-before"';
                }
            }

            return preg_replace('/^<(h[1-6]|blockquote)([^>]*)>/', '<' . $tag . $attrs . '>', $html_block);
        }

        // 对于div（代码块容器等），也需要处理
        if (preg_match('/^<div([^>]*)>/', $html_block, $matches)) {
            $attrs = $matches[1];

            if (preg_match('/class="([^"]*)"/', $attrs, $class_match)) {
                $classes = $class_match[1];
                if ($has_double_newline) {
                    $classes .= ' has-space-before';
                } else {
                    $classes .= ' has-minimal-space-before';
                }
                $attrs = preg_replace('/class="[^"]*"/', 'class="' . $classes . '"', $attrs);
            } else {
                if ($has_double_newline) {
                    $attrs .= ' class="has-space-before"';
                } else {
                    $attrs .= ' class="has-minimal-space-before"';
                }
            }

            return preg_replace('/^<div([^>]*)>/', '<div' . $attrs . '>', $html_block);
        }

        return $html_block;
    }

    /**
     * 将内容按HTML块级标签分割
     */
    private function split_content_by_html_blocks($content) {
        // 定义分割模式：匹配各种HTML块级标签和占位符
        $split_pattern = '/(
            <!--CODEBLOCK_[a-zA-Z0-9]+--> |    # Formatting解析器占位符
            <h[1-6][^>]*>[\s\S]*?<\/h[1-6]> |  # 标题标签
            <ul[^>]*>[\s\S]*?<\/ul> |            # 无序列表
            <ol[^>]*>[\s\S]*?<\/ol> |            # 有序列表
            <blockquote[^>]*>[\s\S]*?<\/blockquote> |  # 引用块
            <pre[^>]*>[\s\S]*?<\/pre> |          # 代码块
            <table[^>]*>[\s\S]*?<\/table> |      # 表格
            <hr[^>]*> |                         # 分隔线
            <div[^>]*>[\s\S]*?<\/div> |         # 代码块容器等
            <p[^>]*>[\s\S]*?<\/p>               # 段落（如果已存在）
        )/x';

        // 使用preg_split分割，保留分隔符
        $parts = preg_split($split_pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        // 过滤掉完全为空的部分
        $parts = array_filter($parts, function($part) {
            return $part !== '';
        });

        return array_values($parts);
    }

    /**
     * 判断是否是HTML块
     */
    private function is_html_block($part) {
        $part = trim($part);

        // 检查是否以HTML标签开头
        if (preg_match('/^<h[1-6]/', $part)) return true;
        if (preg_match('/^<ul/', $part)) return true;
        if (preg_match('/^<ol/', $part)) return true;
        if (preg_match('/^<blockquote/', $part)) return true;
        if (preg_match('/^<pre/', $part)) return true;
        if (preg_match('/^<table/', $part)) return true;
        if (preg_match('/^<hr/', $part)) return true;
        if (preg_match('/^<div/', $part)) return true;
        if (preg_match('/^<p/', $part)) return true;

        // 检查是否是Formatting_Markdown_Parser生成的占位符
        if (preg_match('/^<!--CODEBLOCK_[a-zA-Z0-9]+-->$/', $part)) return true;

        return false;
    }

    /**
     * 处理普通文本块的段落
     */
    private function process_text_block_paragraphs($text_block, $has_double_newline_at_end = false, $has_single_newline_at_end = false) {
        // 检查文本块末尾是否有双换行
        $ends_with_double_newline = preg_match('/\n\s*\n\s*$/', $text_block);
        $ends_with_single_newline = preg_match('/\n\s*$/', $text_block) && !$ends_with_double_newline;

        // 移除末尾的换行
        if ($ends_with_double_newline || $has_double_newline_at_end) {
            $text_block = preg_replace('/\n\s*\n\s*$/', '', $text_block);
        } elseif ($ends_with_single_newline || $has_single_newline_at_end) {
            $text_block = preg_replace('/\n\s*$/', '', $text_block);
        }

        // 移除开头的空白
        $text_block = ltrim($text_block);

        if (empty($text_block)) {
            return '';
        }

        // 按照标准Markdown：双换行创建段落，单换行转换为<br>
        $placeholder = '<!--PARAGRAPH_BREAK-->';
        $text_with_placeholders = preg_replace('/\n\s*\n+/', $placeholder, $text_block);

        // 按占位符分割段落
        $paragraphs = explode($placeholder, $text_with_placeholders);

        $processed = [];
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (!empty($paragraph)) {
                // 处理段落内的单行换行，转换为<br>标签
                $paragraph = $this->process_line_breaks($paragraph);
                $processed[] = '<p class="markdown-paragraph">' . $paragraph . '</p>';
            }
        }

        $result = implode("\n", $processed);
        return $result;
    }

    /**
     * 处理行内换行
     */
    private function process_line_breaks($text) {
        // 将单换行转换为<br>标签
        $lines = explode("\n", $text);
        $processed_lines = [];

        foreach ($lines as $index => $line) {
            $line = rtrim($line);

            if (empty($line) && $index > 0 && $index < count($lines) - 1) {
                continue;
            }

            if (!empty($line)) {
                $processed_lines[] = $line;
            } elseif ($index === 0) {
                continue;
            } elseif ($index === count($lines) - 1) {
                continue;
            }
        }

        // 用<br>标签连接非空行
        return implode('<br>', $processed_lines);
    }

    /**
     * 清理空白字符
     */
    private function clean_whitespace($content) {
        // 清理行首行尾空白
        $content = preg_replace('/^\s+|\s+$/m', '', $content);

        // 清理多余的空行
        $content = preg_replace('/\n{3,}/', "\n\n", $content);

        return $content;
    }

    // ============ 公共方法 ============

    /**
     * 获取解析器信息
     */
    public function get_info() {
        return [
            'name' => 'Unified Content Parser',
            'version' => '2.0.0',
            'description' => 'Unified parser for all Markdown content: images, structure, formatting, and text',
            'supported_syntax' => [
                // 图片
                '![alt](url)' => '<img src="url" alt="alt" loading="lazy" />',
                '![alt](url "title")' => '<img src="url" alt="alt" title="title" loading="lazy" />',

                // 结构
                '# Heading' => '<h1>Heading</h1>',
                '- Item' => '<ul><li>Item</li></ul>',
                '1. Item' => '<ol><li>Item</li></ol>',
                '> Quote' => '<blockquote>Quote</blockquote>',
                '---' => '<hr>',
                '| Header | Header |' => '<table><thead><tr><th>Header</th><th>Header</th></tr></thead></table>',
                '- [x] Task' => '<ul><li><input type="checkbox" checked disabled> Task</li></ul>',

                // 格式化
                '**bold**' => '<strong>bold</strong>',
                '*italic*' => '<em>italic</em>',
                '~~strikethrough~~' => '<del>strikethrough</del>',
                '`code`' => '<code>code</code>',
                '==highlight==' => '<mark>highlight</mark>',
                '[link text](url)' => '<a href="url">link text</a>',
                '```code block```' => '<pre><code>code block</code></pre>',

                // 文本
                'paragraphs' => 'Double newlines to <p> tags',
                'line_breaks' => 'Single newlines to <br> tags within paragraphs'
            ]
        ];
    }
}
