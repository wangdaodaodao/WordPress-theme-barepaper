<?php
/*
 * barepaper WordPress 主题文章元数据模板文件
 * 显示文章标签、分类、赞助信息和导航
 *
 * 相关样式文件: assets/css/post.css
 * 包含样式: 文章标签、分类、赞助模块、元信息、导航等
 *
 * @author wangdaodao
 * @version 1.0.4
 * @date 2025-10-16
 */
?>

<div id="blog_post_info_block" role="contentinfo">
    <?php $paper_wp_settings = get_option( 'paper_wp_theme_settings' ); ?>
    <div id="EntryTag" class="detail-post-tags">
        <?php
        // Display Categories
        $categories = get_the_category();
        if ( ! empty( $categories ) ) {
            echo '<p class="categories">分类: ';
            $category_links = array();
            foreach ( $categories as $category ) {
                $category_links[] = '<a href="' . esc_url( get_category_link( $category->term_id ) ) . '">' . esc_html( $category->name ) . '</a>';
            }
            echo implode(', ', $category_links);
            echo '</p>';
        }

        // Display Tags
        $post_tags = get_the_tags();
        if ( ! empty( $post_tags ) ) {
            echo '<p class="tags">标签: ';
            $tag_links = array();
            foreach ( $post_tags as $tag ) {
                $tag_links[] = '<a href="' . get_tag_link( $tag->term_id ) . '">' . $tag->name . '</a>';
            }
            echo implode(', ', $tag_links);
            echo '</p>';
        }
        ?>
    </div>

    <?php if ( isset( $paper_wp_settings['show_sponsor_module'] ) && $paper_wp_settings['show_sponsor_module'] ) : ?>
    <?php
    // 功能已禁用 - 具体逻辑已移除，保留函数调用结构
    // <div class="sponsor-qr">...</div>
    ?>
    <?php endif; ?>

    <hr class="article-meta-hr">

    <div class="article-meta-right">
        <ul class="post-meta">
            <li><?php the_author_posts_link(); ?> </li>
            <li><time datetime="<?php the_time('c'); ?>"><?php the_time('Y-m-d H:i:s'); ?></time></li>

            <li itemprop="interactionCount">
                <a itemprop="discussionUrl" href="<?php comments_link(); ?>"><?php comments_number('暂无评论', '1 条评论', '% 条评论'); ?></a>
            </li>
            <li><?php echo get_post_word_count(); ?></li>
            <li><?php echo paper_wp_get_post_views(get_the_ID()); ?></li>
            <li><button id="recommend-post-button" data-post-id="<?php echo get_the_ID(); ?>"><span class="recommend-text">点赞</span> (<span class="recommend-count"><?php echo get_post_meta(get_the_ID(), '_post_recommend_count', true) ?: '0'; ?></span>)</button></li>
        </ul>
    </div>



    <div id="post_next_prev">
        <!-- Prev/Next Navigation -->
        <ul class="post-near">
            <?php
            $prev_post = get_previous_post();
            if ( ! empty( $prev_post ) ) : ?>
                <li>上一篇: <a href="<?php echo get_permalink( $prev_post->ID ); ?>" title="<?php echo esc_attr( $prev_post->post_title ); ?>"><?php echo esc_html( $prev_post->post_title ); ?></a></li>
            <?php else: ?>
                <li>上一篇: <span>没有了</span></li>
            <?php endif; ?>

            <?php
            $next_post = get_next_post();
            if ( ! empty( $next_post ) ) : ?>
                <li>下一篇: <a href="<?php echo get_permalink( $next_post->ID ); ?>" title="<?php echo esc_attr( $next_post->post_title ); ?>"><?php echo esc_html( $next_post->post_title ); ?></a></li>
            <?php else: ?>
                <li>下一篇: <span>没有了</span></li>
            <?php endif; ?>
        </ul>
    </div>
</div>
