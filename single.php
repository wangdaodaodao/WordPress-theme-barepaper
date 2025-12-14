<?php
/*
 * barepaper WordPress 主题单篇文章文件
 * 显示完整文章内容、广告和评论
 *
 * @author wangdaodao
 * @version 3.1.0
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


            <?php
                $paper_wp_ad_settings = get_option('paper_wp_ad_settings');
                if (!empty($paper_wp_ad_settings['show_post_bottom_ad']) && !empty($paper_wp_ad_settings['post_bottom_ad_code'])) {
                    echo '<div class="article-footer">' . wp_kses_post($paper_wp_ad_settings['post_bottom_ad_code']) . '</div>';
                }
            ?>
        </div>
        </article>

        <div id="blog_post_info_block" role="contentinfo">
            <?php $paper_wp_settings = get_option('paper_wp_theme_settings'); ?>
            <div id="EntryTag" class="detail-post-tags">
                <?php
                // Display Categories
                $categories = get_the_category();
                if (!empty($categories)) {
                    $category_links = [];
                    foreach ($categories as $category) {
                        $category_links[] = '<a href="' . esc_url(get_category_link($category->term_id)) . '">' . esc_html($category->name) . '</a>';
                    }
                    echo '<p class="categories">分类: ' . implode(', ', $category_links) . '</p>';
                }

                // Display Tags
                $post_tags = get_the_tags();
                if (!empty($post_tags)) {
                    $tag_links = [];
                    foreach ($post_tags as $tag) {
                        $tag_links[] = '<a href="' . get_tag_link($tag->term_id) . '">' . $tag->name . '</a>';
                    }
                    echo '<p class="tags">标签: ' . implode(', ', $tag_links) . '</p>';
                }
                ?>
            </div>


            <?php 
            $effects_settings = get_option('paper_wp_effects_settings');
            if (!empty($effects_settings['enable_sponsor'])) : 
                $wechat_qr = !empty($effects_settings['sponsor_wechat_qr']) ? $effects_settings['sponsor_wechat_qr'] : '';
                $alipay_qr = !empty($effects_settings['sponsor_alipay_qr']) ? $effects_settings['sponsor_alipay_qr'] : '';
                
                // 只有至少一个二维码存在时才显示模块
                if ($wechat_qr || $alipay_qr) :
            ?>
            <div class="sponsor-qr">
                <div class="sponsor-title">欢迎关注微信公众号或者赞助</div>
                <div class="sponsor-icons">
                    <?php if ($alipay_qr) : ?>
                    <div class="alipay">
                        <img src="<?php echo get_template_directory_uri(); ?>/images/alipay.svg" alt="支付宝" width="32" height="32">
                        <div class="qrshow-alipay qr-popup">
                            <img src="<?php echo esc_url($alipay_qr); ?>" alt="支付宝二维码">
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($wechat_qr) : ?>
                    <div class="wechat">
                        <img src="<?php echo get_template_directory_uri(); ?>/images/wechat.svg" alt="微信" width="32" height="32">
                        <div class="qrshow-wechat qr-popup">
                            <img src="<?php echo esc_url($wechat_qr); ?>" alt="微信二维码">
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php 
                endif;
            endif; 
            ?>

            <hr class="article-meta-hr">
            <div class="article-meta-right">
                <ul class="post-meta">
                    <li>
                        <svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" width="14" height="14" style="fill: currentColor; vertical-align: middle; ">
                            <path d="M512 64C264.6 64 64 264.6 64 512s200.6 448 448 448 448-200.6 448-448S759.4 64 512 64zm0 820c-205.4 0-372-166.6-372-372s166.6-372 372-372 372 166.6 372 372-166.6 372-372 372z"></path>
                            <path d="M686.7 638.6L544.1 535.5V288c0-4.4-3.6-8-8-8H488c-4.4 0-8 3.6-8 8v275.4c0 2.6 1.2 5 3.3 6.5l165.4 120.6c3.6 2.6 8.6 1.8 11.2-1.7l28.6-39c2.6-3.7 1.8-8.7-1.8-11.2z"></path>
                        </svg>
                        <time datetime="<?php the_time('c'); ?>" itemprop="datePublished"><?php the_time('Y-m-d H:i:s'); ?></time>
                    </li>
                    <li>
                        <svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" width="14" height="14" style="fill: currentColor; vertical-align: middle; ">
                            <path d="M880 112H144c-17.7 0-32 14.3-32 32v736c0 17.7 14.3 32 32 32h736c17.7 0 32-14.3 32-32V144c0-17.7-14.3-32-32-32zm-40 728H184V184h656v656z"></path>
                            <path d="M492 400h184c4.4 0 8-3.6 8-8v-48c0-4.4-3.6-8-8-8H492c-4.4 0-8 3.6-8 8v48c0 4.4 3.6 8 8 8zm0 144h184c4.4 0 8-3.6 8-8v-48c0-4.4-3.6-8-8-8H492c-4.4 0-8 3.6-8 8v48c0 4.4 3.6 8 8 8zm0 144h184c4.4 0 8-3.6 8-8v-48c0-4.4-3.6-8-8-8H492c-4.4 0-8 3.6-8 8v48c0 4.4 3.6 8 8 8zM340 368a40 40 0 1 0 80 0 40 40 0 1 0-80 0zm0 144a40 40 0 1 0 80 0 40 40 0 1 0-80 0zm0 144a40 40 0 1 0 80 0 40 40 0 1 0-80 0z"></path>
                        </svg>
                        <?php the_category(', '); ?>
                    </li>
                    <li itemprop="interactionCount">
                        <svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" width="14" height="14" style="fill: currentColor; vertical-align: middle; ">
                            <path d="M573 421c-23.1 0-41 17.9-41 40s17.9 40 41 40c21.1 0 39-17.9 39-40s-17.9-40-39-40zm-280 0c-23.1 0-41 17.9-41 40s17.9 40 41 40c21.1 0 39-17.9 39-40s-17.9-40-39-40z"></path>
                            <path d="M894 345c-48.1-66-115.3-110.1-189-130v.1c-17.1-19-36.4-36.5-58-52.1-163.7-119-393.5-82.7-513 81-96.3 133-92.2 311.9 6 439l.8 132.6c0 3.2.5 6.4 1.5 9.4 5.3 16.9 23.3 26.2 40.1 20.9L309 806c33.5 11.9 68.1 18.7 102.5 20.6l-.5.4c89.1 64.9 205.9 84.4 313 49l127.1 41.4c3.2 1 6.5 1.6 9.9 1.6 17.7 0 32-14.3 32-32V753c88.1-119.6 90.4-284.9 1-408zM323 735l-12-5-99 31-1-104-8-9c-84.6-103.2-90.2-251.9-11-361 96.4-132.2 281.2-161.4 413-66 132.2 96.1 161.5 280.6 66 412-80.1 109.9-223.5 150.5-348 102zm505-17l-8 10 1 104-98-33-12 5c-56 20.8-115.7 22.5-171 7l-.2-.1C613.7 788.2 680.7 742.2 729 676c76.4-105.3 88.8-237.6 44.4-350.4l.6.4c23 16.5 44.1 37.1 62 62 72.6 99.6 68.5 235.2-8 330z"></path>
                        </svg>
                        <a itemprop="discussionUrl" href="<?php comments_link(); ?>"><?php comments_number('暂无评论', '1 条评论', '% 条评论'); ?></a>
                    </li>
                    <li>
                        <svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" width="14" height="14" style="fill: currentColor; vertical-align: middle; ">
                            <path d="M854.6 288.6L639.4 73.4c-6-6-14.1-9.4-22.6-9.4H192c-17.7 0-32 14.3-32 32v832c0 17.7 14.3 32 32 32h640c17.7 0 32-14.3 32-32V311.3c0-8.5-3.4-16.7-9.4-22.7zM790.2 326H602V137.8L790.2 326zm1.8 562H232V136h302v216a42 42 0 0 0 42 42h216v494zM402 549c0 5.4 4.4 9.5 9.8 9.5h32.4c5.4 0 9.8-4.2 9.8-9.4V420.6h85.3c5.4 0 9.8-4.4 9.8-9.8v-29.9c0-5.4-4.4-9.8-9.8-9.8h-225c-5.4 0-9.8 4.4-9.8 9.8v29.9c0 5.4 4.4 9.8 9.8 9.8h85.9V549z"></path>
                        </svg><span><?php echo get_post_word_count(); ?></span>
                    </li>
                    <li>
                        <svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" width="14" height="14" style="fill: currentColor; vertical-align: middle; ">
                            <path d="M508 512m-112 0a112 112 0 1 0 224 0 112 112 0 1 0-224 0Z"></path>
                            <path d="M942.2 486.2C847.4 286.5 704.1 186 512 186c-192.2 0-335.4 100.5-430.2 300.3-7.7 16.2-7.7 35.2 0 51.5C176.6 737.5 319.9 838 512 838c192.2 0 335.4-100.5 430.2-300.3 7.7-16.2 7.7-35 0-51.5zM508 688c-97.2 0-176-78.8-176-176s78.8-176 176-176 176 78.8 176 176-78.8 176-176 176z"></path>
                        </svg><span><?php echo paper_wp_get_post_views(get_the_ID()); ?></span>
                    </li>
                    <li id="recommend-post-button" class="meta-recommend" role="button" tabindex="0" data-post-id="<?php echo get_the_ID(); ?>">
                        <svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" width="14" height="14" style="fill: currentColor; vertical-align: middle; "><path d="M923 283.6a260.04 260.04 0 0 0-56.9-82.8 264.4 264.4 0 0 0-84-55.5A265.34 265.34 0 0 0 679.7 125c-49.3 0-97.4 13.5-139.2 39-10 6.1-19.5 12.8-28.5 20.1-9-7.3-18.5-14-28.5-20.1-41.8-25.5-89.9-39-139.2-39-35.5 0-69.9 6.8-102.4 20.3-31.4 13-59.7 31.7-84 55.5a258.44 258.44 0 0 0-56.9 82.8c-13.9 32.3-21 66.6-21 101.9 0 33.3 6.8 68 20.3 103.3 11.3 29.5 27.5 60.1 48.2 91 32.8 48.9 77.9 99.9 133.9 151.6 92.8 85.7 184.7 144.9 188.6 147.3l23.7 15.2c10.5 6.7 24 6.7 34.5 0l23.7-15.2c3.9-2.5 95.7-61.6 188.6-147.3 56-51.7 101.1-102.7 133.9-151.6 20.7-30.9 37-61.5 48.2-91 13.5-35.3 20.3-70 20.3-103.3.1-35.3-7-69.6-20.9-101.9z"></path></svg><span class="recommend-text">点赞</span> (<span class="recommend-count"><?php echo get_post_meta(get_the_ID(), '_post_recommend_count', true) ?: '0'; ?></span>)
                    </li>
                </ul>
            </div>

            <div id="post_next_prev">
                <ul class="post-near">
                    <?php
                    $prev_post = get_previous_post();
                    if (!empty($prev_post)) : ?>
                        <li>上一篇: <a href="<?php echo get_permalink($prev_post->ID); ?>" title="<?php echo esc_attr($prev_post->post_title); ?>"><?php echo esc_html($prev_post->post_title); ?></a></li>
                    <?php else : ?>
                        <li>上一篇: <span>没有了</span></li>
                    <?php endif; ?>

                    <?php
                    $next_post = get_next_post();
                    if (!empty($next_post)) : ?>
                        <li>下一篇: <a href="<?php echo get_permalink($next_post->ID); ?>" title="<?php echo esc_attr($next_post->post_title); ?>"><?php echo esc_html($next_post->post_title); ?></a></li>
                    <?php else : ?>
                        <li>下一篇: <span>没有了</span></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

    <?php comments_template(); ?>

    <?php endwhile; endif; ?>
</div>

<?php get_sidebar(); ?>

<?php get_footer(); ?>
