/**
 * Paper WP 在线状态实时更新脚本
 * 实现前端定期更新用户在线状态
 *
 * @version 1.1.0
 * @since 3.0.11
 */

// 当整个页面加载完毕后执行
window.addEventListener('load', function() {

    // 定义一个函数，用于向服务器发送更新请求
    function updateUserOnlineStatus() {
        // 检查配置是否存在
        if (typeof paperWpOnlineConfig === 'undefined') {
            console.warn('Paper WP: Online status config not found.');
            return;
        }

        // 使用 Fetch API 发送请求
        fetch(paperWpOnlineConfig.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            // 发送必要的数据
            body: new URLSearchParams({
                'action': 'paper_wp_update_online_status',
                'nonce': paperWpOnlineConfig.nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 可选：在控制台打印成功信息，用于调试
                // console.log('User online status updated.');
            } else {
                console.warn('Paper WP: Failed to update status:', data.data ? data.data.message : 'Unknown error');
            }
        })
        .catch(error => {
            console.error('Paper WP: Error during AJAX request:', error);
        });
    }

    // 首次加载页面时，立即更新一次状态
    updateUserOnlineStatus();

    // 设置一个定时器，每隔 2 分钟（120000毫秒）自动更新一次
    // 这样即使用户停留在同一个页面，服务器也能知道他/她仍在线
    setInterval(updateUserOnlineStatus, 120000);

});
