<?php
/**
 * 推荐文章功能模块
 * @author wangdaodao
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * 添加推荐文章功能
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
        if (!isset($_POST['paper_wp_recommended_post_nonce_field']) || 
            !wp_verify_nonce($_POST['paper_wp_recommended_post_nonce_field'], 'paper_wp_recommended_post_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $is_recommended = isset($_POST['paper_wp_recommended']) ? '1' : '0';
        update_post_meta($post_id, '_paper_wp_recommended', $is_recommended);
        
        // 清除推荐文章缓存
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
        $current_status = get_post_meta($post_id, '_paper_wp_recommended', true);
        $new_status = $current_status === '1' ? '0' : '1';
        
        update_post_meta($post_id, '_paper_wp_recommended', $new_status);
        delete_transient('paper_wp_recommended_posts_cache');
        
        wp_send_json_success(array(
            'status' => $new_status,
            'message' => $new_status === '1' ? '已设为推荐' : '已取消推荐'
        ));
    }
}



// 初始化推荐文章功能
new Paper_WP_Recommended_Posts();
