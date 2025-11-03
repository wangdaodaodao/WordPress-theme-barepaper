<?php
/*
 * barepaper WordPress 主题单篇文章文件
 * 显示完整文章内容、广告和评论
 *
 * 相关样式文件: assets/css/post.css (文章内容样式)
 *               assets/css/comments.css (评论样式)
 *
 * @author wangdaodao
 * @version 1.0.4
 * @date 2025-10-16
 */
?>
<?php get_header(); ?>

<div class="col-mb-12 col-8" id="main" role="main">
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
    <article class="post" itemscope itemtype="http://schema.org/BlogPosting">
        <h1 class="post-title" itemprop="name headline">
            <a itemprop="url" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h1>

        <div class="post-content" itemprop="articleBody">
            <?php the_content(); ?>

            <!-- 广告移到这里防止错位 -->
            <?php
                // 功能正在开发中
                // $paper_wp_ad_settings = get_option( 'paper_wp_ad_settings' );
                // if ( isset( $paper_wp_ad_settings['show_post_bottom_ad'] ) && ... ) {
                //     echo '<div class="article-footer">' . wp_kses_post( $paper_wp_ad_settings['post_bottom_ad_code'] ) . '</div>';
                // }
            ?>
        </div>
        </article>



        <?php get_template_part( 'article-meta' ); ?>

    <?php comments_template(); ?>

    <?php endwhile; endif; ?>
</div><!-- end #main-->

<?php get_sidebar(); ?>

<?php get_footer(); ?>
