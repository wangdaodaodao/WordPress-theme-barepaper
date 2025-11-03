jQuery(document).ready(function($) {
    // Check if the localized data is available
    if (typeof view_counter_ajax === 'undefined') {
        return;
    }

    $.ajax({
        type: 'POST',
        url: view_counter_ajax.ajax_url,
        data: {
            action: 'track_post_views',
            nonce: view_counter_ajax.nonce,
            post_id: view_counter_ajax.post_id,
        },
        success: function(response) {
            // You can optionally handle the response here, e.g., for debugging
            if (response.success) {
                // console.log('View count updated successfully.');
            } else {
                // console.error('Failed to update view count: ' + response.data);
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            // console.error('AJAX error while updating view count: ' + textStatus + ' - ' + errorThrown);
        }
    });
});