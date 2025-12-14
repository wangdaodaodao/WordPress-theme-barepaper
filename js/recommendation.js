jQuery(document).ready(function($) {
    $('#recommend-post-button').on('click', function() {
        var button = $(this);
        var postId = button.data('post-id');
        var cooldownKey = 'recommend_cooldown_' + postId;
        var cooldownDuration = 3600 * 1000; // 1 hour in milliseconds

        var lastRecommendedTime = localStorage.getItem(cooldownKey);
        var currentTime = new Date().getTime();

        // Check client-side cooldown
        if (lastRecommendedTime && (currentTime - lastRecommendedTime < cooldownDuration)) {
            button.find('.recommend-text').text('已点赞'); // Change text to '已点赞'
            button.prop('disabled', true); // Disable button
            button.find('.recommend-text, .recommend-count').css('color', '#333 !important'); // Change text color to normal
            return; // Prevent AJAX call
        }

        // Disable button immediately to prevent multiple clicks
        button.prop('disabled', true);

        // 从 script 标签的 data 属性读取数据
        var scriptTag = document.querySelector('script[src*="recommendation.js"]');
        if (!scriptTag || !scriptTag.dataset.ajaxUrl) {
            console.error('Paper WP: Recommendation script config not found.');
            button.prop('disabled', false);
            return;
        }

        $.ajax({
            url: scriptTag.dataset.ajaxUrl, // WordPress AJAX URL
            type: 'POST',
            data: {
                action: 'post_recommend',
                post_id: postId,
                nonce: scriptTag.dataset.nonce
            },
            success: function(response) {
                if (response.success) {
                    button.find('.recommend-text').text('已点赞'); // Change text to '已点赞'
                    button.find('.recommend-count').text(response.data.new_count); // Update count
                    localStorage.setItem(cooldownKey, currentTime); // Set new cooldown
                    button.find('.recommend-text, .recommend-count').css('color', '#333 !important'); // Change text color to normal
                } else {
                    // If server rejects (e.g., server-side cooldown), re-enable button and revert text
                    button.find('.recommend-text').text('点赞'); // Revert text
                    button.prop('disabled', false); // Re-enable button
                    button.find('.recommend-text, .recommend-count').css('color', 'red !important'); // Revert button text color to red
                    // Optionally, update count from server response if it sends current count
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error: ' + status + error);
                button.prop('disabled', false); // Re-enable button on error
                button.find('.recommend-text, .recommend-count').css('color', 'red !important'); // Revert button text color to red
            }
        });
    });

    // Initial check on page load to set button state if already recommended
    $('#recommend-post-button').each(function() {
        var button = $(this);
        var postId = button.data('post-id');
        var cooldownKey = 'recommend_cooldown_' + postId;
        var cooldownDuration = 3600 * 1000; // 1 hour in milliseconds

        var lastRecommendedTime = localStorage.getItem(cooldownKey);
        var currentTime = new Date().getTime();

        if (lastRecommendedTime && (currentTime - lastRecommendedTime < cooldownDuration)) {
            button.find('.recommend-text').text('已点赞');
            button.prop('disabled', true);
            button.find('.recommend-text, .recommend-count').css('color', '#333 !important'); // Change button text color to normal
        }
    });
});
