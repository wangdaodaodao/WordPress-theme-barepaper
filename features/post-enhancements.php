<?php
/**
 * 文章增强功能模块
 * 包含推荐文章和置顶文章功能
 * @author wangdaodao
 * @version 3.1.0
 */

if (!defined('ABSPATH')) exit;

/**
 * 推荐文章功能类
 */
class Paper_WP_Recommended_Posts {
    
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_recommended_post_meta_box'));
        add_action('save_post', array($this, 'save_recommended_post_meta'));
        add_action('wp_ajax_toggle_recommended_post', array($this, 'ajax_toggle_recommended_post'));
        add_action('wp_ajax_nopriv_toggle_recommended_post', array($this, 'ajax_toggle_recommended_post'));
    }
    
    /**
     * 在文章编辑页面添加推荐文章选项
     */
    public function add_recommended_post_meta_box() {
        add_meta_box(
            'paper_wp_recommended_post',
            '推荐设置',
            array($this, 'recommended_post_meta_box_callback'),
            'post',
            'side',
            'high'
        );
    }
    
    /**
     * 推荐文章元框回调函数
     */
    public function recommended_post_meta_box_callback($post) {
        wp_nonce_field('paper_wp_recommended_post_nonce', 'paper_wp_recommended_post_nonce_field');
        $is_recommended = get_post_meta($post->ID, '_paper_wp_recommended', true);
        ?>
        <label for="paper_wp_recommended_checkbox">
            <input type="checkbox" id="paper_wp_recommended_checkbox" name="paper_wp_recommended" value="1" <?php checked($is_recommended, '1'); ?> />
            标记为推荐文章
        </label>
        <p class="description">勾选后，此文章将出现在侧栏的推荐文章模块中。</p>
        <?php
    }
    
    /**
     * 保存推荐文章设置
     */
    public function save_recommended_post_meta($post_id) {
        if (!isset($_POST['paper_wp_recommended_post_nonce_field'])
            || !wp_verify_nonce($_POST['paper_wp_recommended_post_nonce_field'], 'paper_wp_recommended_post_nonce')
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || !current_user_can('edit_post', $post_id)) {
            return;
        }

        update_post_meta($post_id, '_paper_wp_recommended', isset($_POST['paper_wp_recommended']) ? '1' : '0');
        delete_transient('paper_wp_recommended_posts_cache');
    }
    
    /**
     * AJAX切换推荐状态
     */
    public function ajax_toggle_recommended_post() {
        check_ajax_referer('paper_wp_recommended_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('权限不足');
        }
        
        $post_id = intval($_POST['post_id']);
        $new_status = (get_post_meta($post_id, '_paper_wp_recommended', true) === '1') ? '0' : '1';
        
        update_post_meta($post_id, '_paper_wp_recommended', $new_status);
        delete_transient('paper_wp_recommended_posts_cache');
        
        wp_send_json_success([
            'status' => $new_status,
            'message' => $new_status === '1' ? '已设为推荐' : '已取消推荐'
        ]);
    }
}

/**
 * 置顶文章功能类
 */
class Paper_WP_Sticky_Posts {
    
    public function __construct() {
        // 检查功能是否启用
        if (!Paper_Settings_Manager::is_enabled('paper_wp_theme_settings', 'enable_sticky_posts')) {
            return;
        }

        // 后台相关钩子
        add_action('add_meta_boxes', array($this, 'add_sticky_post_meta_box'));
        add_action('save_post', array($this, 'save_sticky_post_meta'));
        add_action('admin_head', array($this, 'hide_default_sticky_option'));
        
        // 前端相关钩子
        add_action('pre_get_posts', array($this, 'modify_main_query'));
        add_filter('posts_results', array($this, 'reorder_posts_with_sticky'), 10, 2);
    }
    
    /**
     * 在文章编辑页面添加置顶选项
     */
    public function add_sticky_post_meta_box() {
        add_meta_box(
            'paper_wp_sticky_post',
            '置顶设置',
            array($this, 'sticky_post_meta_box_callback'),
            'post',
            'side',
            'high'
        );
    }
    
    /**
     * 置顶文章元框回调函数
     */
    public function sticky_post_meta_box_callback($post) {
        wp_nonce_field('paper_wp_sticky_post_nonce', 'paper_wp_sticky_post_nonce_field');
        $is_sticky = get_post_meta($post->ID, '_paper_wp_sticky', true);
        ?>
        <label for="paper_wp_sticky_checkbox">
            <input type="checkbox" id="paper_wp_sticky_checkbox" name="paper_wp_sticky" value="1" <?php checked($is_sticky, '1'); ?> />
            置顶此文章
        </label>
        <p class="description">勾选后，此文章将显示在首页顶部，并带有置顶标识。如果有多个置顶文章，只显示最新的一篇。</p>
        <?php
    }
    
    /**
     * 保存置顶文章设置
     */
    public function save_sticky_post_meta($post_id) {
        if (!isset($_POST['paper_wp_sticky_post_nonce_field']) 
            || !wp_verify_nonce($_POST['paper_wp_sticky_post_nonce_field'], 'paper_wp_sticky_post_nonce')
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || !current_user_can('edit_post', $post_id)) {
            return;
        }

        update_post_meta($post_id, '_paper_wp_sticky', isset($_POST['paper_wp_sticky']) ? '1' : '0');
    }
    
    /**
     * 修改主查询，排除置顶文章（置顶文章会通过 posts_results 重新排序）
     */
    public function modify_main_query($query) {
        if (is_admin() || !$query->is_main_query() || !is_home()) {
            return;
        }

        $sticky_posts = get_posts([
            'meta_key' => '_paper_wp_sticky',
            'meta_value' => '1',
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
            'post_status' => 'publish'
        ]);

        if (!empty($sticky_posts)) {
            $query->set('post__not_in', $sticky_posts);

            // 在所有页面都保持一致的文章数量，避免分页错乱
            $posts_per_page = get_option('posts_per_page', 10);
            $query->set('posts_per_page', $posts_per_page);
        }

        $query->set('ignore_sticky_posts', true);
    }
    
    /**
     * 重新排序文章，将置顶文章放在最前面
     */
    public function reorder_posts_with_sticky($posts, $query) {
        if (is_admin() || !$query->is_main_query() || !is_home() || (get_query_var('paged') ?: 1) != 1) {
            return $posts;
        }

        $sticky_posts = get_posts([
            'meta_key' => '_paper_wp_sticky',
            'meta_value' => '1',
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => 'publish'
        ]);

        if (!empty($sticky_posts) && !empty($posts)) {
            $sticky_ids = array_map(function($p) { return $p->ID; }, $sticky_posts);
            $normal_posts = array_filter($posts, function($post) use ($sticky_ids) {
                return !in_array($post->ID, $sticky_ids);
            });
            $posts = array_merge($sticky_posts, array_values($normal_posts));
        }

        return $posts;
    }

    /**
     * 隐藏WordPress默认的置顶选项
     */
    public function hide_default_sticky_option() {
        echo '<style>
            #sticky-span, 
            .edit-post-post-status .components-panel__row:has(input[name="sticky"]),
            .components-panel__row:has(.components-checkbox-control__input[name="sticky"]),
            label[for="sticky"],
            input#sticky { 
                display: none !important; 
            }
        </style>';
        
        // 添加 JS 强制移除，以防 CSS 选择器不匹配
        echo '<script>
            jQuery(document).ready(function($) {
                // 经典编辑器
                $("#sticky-span").remove();
                $("label[for=\'sticky\']").remove();
                $("input#sticky").remove();
                
                // 古腾堡编辑器 (使用定时器检查，因为它是动态加载的)
                var checkStickyInterval = setInterval(function() {
                    var $stickyOption = $(".components-panel__row:has(input[name=\'sticky\'])");
                    if ($stickyOption.length) {
                        $stickyOption.hide();
                        // clearInterval(checkStickyInterval); // 不清除，持续监控以防重新渲染
                    }
                }, 1000);
            });
        </script>';
    }
    
}

// 初始化功能
new Paper_WP_Recommended_Posts();
new Paper_WP_Sticky_Posts();

/**
 * 前台文章推荐（点赞）功能类
 */
class Paper_WP_Post_Like {
    
    public function __construct() {
        add_action('wp_ajax_paper_wp_like_post', array($this, 'ajax_like_post'));
        add_action('wp_ajax_nopriv_paper_wp_like_post', array($this, 'ajax_like_post'));
    }
    
    /**
     * AJAX处理点赞
     */
    public function ajax_like_post() {
        // 验证 Nonce
        check_ajax_referer('paper_wp_interactions_nonce', 'nonce');
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if ($post_id > 0) {
            // 检查 Cookie 防止重复点赞
            if (isset($_COOKIE['paper_wp_liked_' . $post_id])) {
                wp_send_json_error('您已经推荐过这篇文章了');
            }
            
            // 获取当前点赞数
            $count = (int) get_post_meta($post_id, '_post_recommend_count', true);
            $count++;
            
            // 更新点赞数
            update_post_meta($post_id, '_post_recommend_count', $count);
            
            // 设置 Cookie (30天过期)
            setcookie('paper_wp_liked_' . $post_id, '1', time() + 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
            
            wp_send_json_success(['count' => $count]);
        }
        
        wp_send_json_error('无效的文章ID');
    }
}

new Paper_WP_Post_Like();

/**
 * 密码保护文章自定义功能
 */
class Paper_WP_Password_Protected {
    
    public function __construct() {
        add_filter('the_password_form', array($this, 'custom_password_form'));
        add_filter('protected_title_format', array($this, 'remove_protected_prefix'));
        add_filter('private_title_format', array($this, 'remove_private_prefix'));
    }
    
    /**
     * 自定义密码保护文章的表单样式
     * 将密码标签、输入框和提交按钮放在同一行
     */
    public function custom_password_form() {
        global $post;
        $label = 'pwbox-' . (empty($post->ID) ? rand() : $post->ID);
        $output = '
        <form action="' . esc_url(site_url('wp-login.php?action=postpass', 'login_post')) . '" method="post" class="post-password-form">
            <p class="password-form-row">
                <label for="' . $label . '">密码：</label>
                <input name="post_password" id="' . $label . '" type="password" spellcheck="false" required size="20" />
                <input type="submit" name="Submit" value="提交" />
            </p>
        </form>
        ';
        return $output;
    }
    
    /**
     * 移除密码保护文章和私密文章标题中的前缀
     * 因为已经有蓝色"私密"或紫色"绝密"标识，不需要在标题中显示文字前缀
     */
    public function remove_protected_prefix($title) {
        return '%s';
    }
    
    public function remove_private_prefix($title) {
        return '%s';
    }
}

new Paper_WP_Password_Protected();

/**
 * 移除邮箱必填限制
 * 
 * WordPress 的 'require_name_email' 选项同时控制姓名和邮箱。
 * 为了实现"姓名必填、邮箱选填"，我们需要先禁用这个选项（让两者都不必填），
 * 然后通过下方的 'preprocess_comment' 钩子单独强制验证姓名。
 */
add_filter('option_require_name_email', '__return_false');

/**
 * 单独强制验证评论作者称呼
 * 
 * 配合上方禁用的 'require_name_email' 选项，
 * 这里手动检查作者字段，确保用户必须填写称呼才能提交。
 */
add_filter('preprocess_comment', function($commentdata) {
    if (empty($commentdata['comment_author'])) {
        wp_die('错误：请填写您的称呼。');
    }
    return $commentdata;
});



/**
 * 自定义评论显示类
 */
class Paper_WP_Walker_Comment extends Walker_Comment {
    public function start_lvl(&$output, $depth = 0, $args = array()) {
        $GLOBALS['comment_depth'] = $depth + 1;
        $output .= '<div class="comment-children"><ol class="comment-list">';
    }

    public function end_lvl(&$output, $depth = 0, $args = array()) {
        $GLOBALS['comment_depth'] = $depth + 1;
        $output .= '</ol></div>';
    }

    public function start_el(&$output, $comment, $depth = 0, $args = array(), $id = 0) {
        $depth++;
        $GLOBALS['comment_depth'] = $depth;
        $GLOBALS['comment'] = $comment;

        $tag = 'li';
        $add_below = 'comment';
        $classes = array('comment');
        
        if (!empty($args['has_children'])) $classes[] = 'comment-parent';
        if ($comment->comment_parent > 0) $classes[] = 'comment-child';
        if (get_comment_ID() % 2) $classes[] = 'comment-odd';
        else $classes[] = 'comment-even';
        
        $is_author = ($comment->user_id > 0 && $comment->user_id == get_post_field('post_author', $comment->comment_post_ID));
        if ($is_author) {
            $classes[] = 'comment-by-author';
        }

        $output .= '<' . $tag . ' id="comment-' . get_comment_ID() . '" class="' . implode(' ', $classes) . '">';
        
        // Media Object
        $output .= '<div class="media">';
        
        // Left: Avatar
        $output .= '<div class="media-left">';
        
        // 彻底禁用 Gravatar，所有用户统一使用默认 SVG 头像
        // 提升性能并保护隐私
        $svg = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiIgZmlsbD0iI2NjYyI+PHBhdGggZD0iTTE2IDRhNCA0IDAgMSAwIDAgOCA0IDQgMCAwIDAgMC04em0wIDEwYy00LjQyIDAtOCAxLjc5LTggNHYyaDE2di0yYzAtMi4yMS0zLjU4LTQtOC00eiIvPjwvc3ZnPg==';
        $avatar = '<img alt="" src="' . $svg . '" class="avatar avatar-32 photo avatar-default" height="32" width="32" />';
        
        $output .= $avatar;
        $output .= '</div>';
        
        // Body: Author & Content
        $output .= '<div class="media-body">';
        
        // Author Name
        $output .= '<p class="author_name">';
        $output .= get_comment_author_link($comment);
        if ($is_author) {
            $output .= ' <span class="badge-author">作者</span>';
        }
        $output .= '</p>';
        
        // Comment Content
        if ('0' == $comment->comment_approved) {
            $output .= '<p><em>您的评论正在等待审核。</em></p>';
        }
        $output .= get_comment_text();
        
        $output .= '</div>'; // End .media-body
        $output .= '</div>'; // End .media
        
        // Metadata Row
        $output .= '<div class="comment-metadata">';
        
        // Time
        $output .= '<span class="comment-pub-time">';
        $output .= get_comment_date('Y-m-d H:i');
        $output .= '</span>';
        
        // UA / OS
        if (class_exists('Paper_User_Agent')) {
            $agent = $comment->comment_agent;
            $os = Paper_User_Agent::get_os($agent);
            $browser = Paper_User_Agent::get_browser_name($agent);
            
            if ($browser && $browser['title'] !== 'Other Browser') {
                $b_code = strtolower($browser['code']);
                $output .= '<span class="ua ua_' . esc_attr($b_code) . '">';
                // $output .= '<i class="fa fa-' . esc_attr($b_code) . '"></i> '; // 暂时不加图标，除非确认有 FontAwesome
                $output .= esc_html($browser['title']);
                $output .= '</span>';
            }
            
            if ($os && $os['title'] !== 'Other System') {
                $os_code = strtolower($os['code']);
                $output .= '<span class="os os_' . esc_attr($os_code) . '">';
                // $output .= '<i class="fa fa-' . esc_attr($os_code) . '"></i> ';
                $output .= esc_html($os['title']);
                $output .= '</span>';
            }
        }
        
        // Reply Button
        $reply_link = get_comment_reply_link(array_merge($args, array(
            'add_below' => 'comment',
            'depth'     => $depth,
            'max_depth' => $args['max_depth'],
            'reply_text'=> '回复'
        )));
        
        if ($reply_link) {
            $output .= '<span class="comment-btn-reply">';
            $output .= '<i class="fa fa-reply"></i> ' . $reply_link;
            $output .= '</span>';
        }
        
        $output .= '</div>'; // End .comment-metadata
    }

    public function end_el(&$output, $comment, $depth = 0, $args = array()) {
        $output .= "</li>\n";
    }
}
