/**
 * Barepaper WordPress Theme - Admin Settings JavaScript
 * 处理主题设置页面的动态功能
 *
 * @version 2.0.0
 * @since 2025-10-17
 */

jQuery(document).ready(function ($) {
    /**
     * 广告示例弹窗功能
     * 点击按钮时动态生成和显示广告示例代码弹窗
     */
    $('#show-ad-examples').on('click', function () {
        var modal = $('#ad-examples-modal');
        if (modal.html() === '') {
            // 动态生成弹窗内容
            var modalContent = '<div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000;">' +
                '<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; max-width: 600px; width: 90%; max-height: 80%; overflow-y: auto;">' +
                '<h3>广告示例代码</h3>' +
                '<div style="margin-bottom: 20px;">' +
                '<h4>图片广告：</h4>' +
                '<textarea readonly rows="5" style="width: 100%; font-family: monospace;"><a href="https://example.com" target="_blank" rel="noopener noreferrer">' +
                '\n  <img src="https://example.com/ad-image.jpg" alt="广告图片" ' +
                '\n       style="width: 100%; max-width: 728px; height: auto; display: block; margin: 0 auto; border: none;" ' +
                '\n       loading="lazy" />' +
                '\n</a></textarea>' +
                '</div>' +
                '<div style="margin-bottom: 20px;">' +
                '<h4>文字广告：</h4>' +
                '<textarea readonly rows="6" style="width: 100%; font-family: monospace;"><div style="text-align: center; padding: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); ' +
                'border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); color: white; margin: 10px 0;">' +
                '\n  <h3 style="margin: 0 0 10px 0; font-size: 18px;">限时优惠！</h3>' +
                '\n  <p style="margin: 0 0 15px 0; font-size: 14px;">专业WordPress主题开发服务</p>' +
                '\n  <a href="https://example.com" target="_blank" rel="noopener noreferrer" ' +
                'style="display: inline-block; padding: 10px 20px; background: #ff6b6b; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">立即了解</a>' +
                '\n</div></textarea>' +
                '</div>' +
                '<div style="margin-bottom: 20px;">' +
                '<h4>Google AdSense：</h4>' +
                '<textarea readonly rows="12" style="width: 100%; font-family: monospace;"><!-- Google AdSense 横幅广告 -->' +
                '\n<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-XXXXXXXXXXXXXXXX" ' +
                '\n        crossorigin="anonymous"></script>' +
                '\n<!-- 顶部横幅广告 -->' +
                '\n<ins class="adsbygoogle"' +
                '\n     style="display:block"' +
                '\n     data-ad-client="ca-pub-XXXXXXXXXXXXXXXX"' +
                '\n     data-ad-slot="XXXXXXXXXX"' +
                '\n     data-ad-format="auto"' +
                '\n     data-full-width-responsive="true"></ins>' +
                '\n<script>' +
                '\n     (adsbygoogle = window.adsbygoogle || []).push({});' +
                '\n</script></textarea>' +
                '</div>' +
                '<button type="button" id="close-ad-examples" class="button" style="float: right;">关闭</button>' +
                '</div>' +
                '</div>';

            modal.html(modalContent).show();

            // 绑定关闭事件
            $('#close-ad-examples').on('click', function () {
                modal.hide();
            });

            // 点击背景关闭
            modal.on('click', function (e) {
                if (e.target === this) {
                    modal.hide();
                }
            });
        } else {
            modal.show();
        }
    });

    // 友情链接管理功能
    $('#add-friend-link').on('click', function () {
        var container = $('#friend-links-list');
        var index = container.children('.friend-link-item').length;
        var newItem = '<div class="friend-link-item" style="display: flex; align-items: center; margin-bottom: 10px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">' +
            '<input type="text" name="paper_wp_friend_links[' + index + '][name]" placeholder="链接名称" style="flex: 1; margin-right: 10px;" />' +
            '<input type="url" name="paper_wp_friend_links[' + index + '][url]" placeholder="链接地址" style="flex: 2; margin-right: 10px;" />' +
            '<input type="text" name="paper_wp_friend_links[' + index + '][description]" placeholder="描述（可选）" style="flex: 2; margin-right: 10px;" />' +
            '<button type="button" class="button remove-friend-link" style="background: #dc3545; color: white; border: none;">删除</button>' +
            '</div>';
        container.append(newItem);
    });

    // 删除友情链接
    $(document).on('click', '.remove-friend-link', function () {
        $(this).closest('.friend-link-item').remove();
    });

    /**
     * 条件字段显示功能
     * 根据依赖字段的状态动态显示或隐藏其他字段
     */
    function toggleConditionalFields() {
        // 其他条件字段逻辑（如果有）
    }

    // 初始检查
    toggleConditionalFields();

    // 监听复选框变化
    // $('#enable_theme_switch').on('change', function() {
    //     toggleConditionalFields();
    // });
});
