<?php
/*
 * barepaper WordPress 主题书单页面模板
 * 显示书单页面的内容 (已重构，恢复分组功能并添加缓存)
 *
 * @author wangdaodao
 * @version 2.2.0
 * @date 2025-10-18
 */
/*
Template Name: Booklist
*/
?>
<?php get_header(); ?>
<div class="col-mb-12 col-8" id="main" role="main">
    <?php while ( have_posts() ) : the_post(); ?>
        <article class="post" itemscope itemtype="http://schema.org/BlogPosting">
        <h1 class="post-title" itemprop="name headline">
        </h1>
            <div class="book-content post-content" itemprop="articleBody">
                <?php
                $content = get_the_content();

                // 1. 显示短代码之前的所有描述性内容
                // 使用 get_shortcode_regex() 来确保正确匹配
                $shortcode_pattern = get_shortcode_regex(['book', 'books']);
                $description_content = preg_split('/(' . $shortcode_pattern . ')/', $content, 2, PREG_SPLIT_NO_EMPTY);
                if (!empty($description_content[0])) {
                    echo apply_filters('the_content', $description_content[0]);
                }

                // 2. 查找所有的 [book] 短代码
                $pattern = get_shortcode_regex(['book']);
                $matches = [];
                preg_match_all("/$pattern/s", $content, $matches);
                $all_book_shortcodes = $matches[0];

                // 3. 按状态对短代码字符串进行分组
                $books_by_status = [
                    'reading' => [],
                    'read'    => [],
                    'wish'    => [],
                ];

                foreach ($all_book_shortcodes as $shortcode) {
                    $status = 'read'; // 默认状态
                    // 从短代码字符串中解析出 status 属性
                    if (preg_match('/status="([^\"]+)"/', $shortcode, $status_match)) {
                        $status = $status_match[1];
                    }
                    // 将短代码字符串放入对应的分组
                    if (array_key_exists($status, $books_by_status)) {
                        $books_by_status[$status][] = $shortcode;
                    } else {
                        $books_by_status['read'][] = $shortcode; // 如果状态无效，归为已读
                    }
                }

                // 4. 定义分组标题和顺序
                $status_order = [
                    'reading' => '在读',
                    'read'    => '已读',
                    'wish'    => '想读',
                ];

                // 5. 遍历分组，将每组的短代码拼接起来，并使用 do_shortcode 渲染
                foreach ($status_order as $status => $title) {
                    if (!empty($books_by_status[$status])) {
                        // 获取该分组的书籍数量
                        $book_count = count($books_by_status[$status]);
                        // 在标题中添加数量统计 - 修改为括号格式
                        $title_with_count = $title . '（' . $book_count . '本）';

                        // 生成豆瓣读书风格的HTML结构
                        echo '<div class="movielist">';
                        echo '<h2>' . esc_html($title_with_count) . '</h2>';
                        echo '<ul>';

                        // 逐个处理每本书籍
                        foreach ($books_by_status[$status] as $book_shortcode) {
                            echo do_shortcode($book_shortcode);
                        }

                        // 添加空的li元素来填充布局（每行7个，填充到7的倍数）
                        $total_books = count($books_by_status[$status]);
                        $empty_slots = (7 - ($total_books % 7)) % 7;
                        for ($i = 0; $i < $empty_slots; $i++) {
                            echo '<li class="empty"></li>';
                        }

                        echo '</ul>';
                        echo '</div>';
                    }
                }
                ?>
            </div>
        </article>
    <?php endwhile; ?>
</div><!-- end #main-->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
