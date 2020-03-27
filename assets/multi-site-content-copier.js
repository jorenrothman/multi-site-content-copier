(function() {
    var ajaxURL = msccAjax.ajaxurl;
    var actionName = 'mscc_create_post';

    var $ = jQuery;

    $(document).ready(function() {
        $('#mscc-submit').click(function(e) {
            e.preventDefault();

            var _this = $(this);
            var $notifications = $('#mscc-notifications');
            var siteID = $('#mscc-location-selector').val();
            var postID = $('#mscc-post-id').val();
            var nonce = $(this).data('nonce');

            $.ajax({
                type: 'post',
                dataType: 'json',
                url: ajaxURL,
                data: {
                    action: actionName,
                    post_id: postID,
                    site_id: siteID,
                    nonce: nonce,
                },
                beforeSend: function() {
                    $notifications.css({
                        display: 'none',
                        color: '#444',
                    });

                    _this.text('Copying Page');
                },
                success: function(response) {
                    _this.text('Copy Page');

                    if (response.success) {
                        $notifications.css({
                            display: 'block',
                            color: '#444',
                        });
                    } else {
                        $notifications.css({
                            display: 'block',
                            color: '#D8000C',
                        });
                    }

                    $notifications.text(response.data.message);
                    console.log(response);
                },

                error: function(error) {
                    _this.text('Copy Page');
                    console.log(error);
                },
            });
        });
    });
})();
