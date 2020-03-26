<?php

function msccInitPlugin()
{
    add_action('add_meta_boxes', 'msccAddMetaBox');
}

add_action('admin_init', 'msccInitPlugin');

function msccAddMetaBox($post)
{
    add_meta_box('mscc_meta_box', 'Multi Site Content Copier', 'msscBuildMetaBox', ['post', 'page'], 'side', 'low');
}

function msscBuildMetaBox()
{
    // our code here

    $options = msccGetSelectOptions();
    $nonce = wp_create_nonce('mscc_copy_post');

?>

    <div class="inside">

        <label for="mscc-location-selector">Select destination</label>
        <p>
            <input type="hidden" name="mscc-post-id" id="mscc-post-id" value="<?= get_the_id(); ?>">
            <select id="mscc-location-selector" name="mscc-option" style="width: 100%">
                <option value="0" selected>Select A Site</option>
                <?php
                foreach ($options as $option) : ?>
                    <option value="<?= $option['id'] ?>"><?= $option['siteName'] ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="notifications" id="mscc-notifications" style="display: none"></p>
        <p>
            <button data-nonce="<?= $nonce ?>" id="mscc-submit" type="button" class="button button-primary button-large">Copy Page</button>
        </p>
    </div>
<?php
}

// add_action('save_post', 'msccMetaBoxCallback');

function msccCreatePost()
{
    $response = [
        'message' => ''
    ];

    $originalSite = get_current_site();
    $originalSiteID = $originalSite->id;
    $postID = (int) filter_input(INPUT_POST, 'post_id', FILTER_SANITIZE_NUMBER_INT);
    $siteID = (int) filter_input(INPUT_POST, 'site_id', FILTER_SANITIZE_NUMBER_INT);
    $nonce = filter_input(INPUT_POST, 'nonce', FILTER_SANITIZE_STRING);

    if (!wp_verify_nonce($nonce, 'mscc_copy_post')) {
        $response['message'] = 'Oops something went wrong';

        wp_send_json_error($response);
    }

    if ($siteID === 0 || $siteID === -1) {
        $response['message'] = 'Please select a site';

        wp_send_json_error($response);
    }

    $post = get_post($postID, ARRAY_A);

    $postMeta = msccGetPostMetaData($postID);

    unset($post['ID']);
    $post['post_status'] = 'draft';

    switch_to_blog($siteID);

    $destinationPostID = wp_insert_post($post);


    foreach ($postMeta as $meta) {
        $key = $meta['meta_key'];

        if ($key === '_edit_last' || $key === '_edit_lock') {
            continue;
        }

        if (is_serialized($meta['meta_value'])) {
            $meta['meta_value'] = maybe_unserialize($meta['meta_value']);
        }

        if (is_array($meta['meta_value'])) {
            $length = sizeof($meta['meta_value']);

            for ($i = 0; $i < $length; $i++) {
                $meta['meta_value'][$i] = msccImageHelper($meta['meta_value'][$i], $siteID, $destinationPostID);
            }
        } else {
            $meta['meta_value'] = msccImageHelper($meta['meta_value'], $siteID, $destinationPostID);
        }



        add_post_meta($destinationPostID, $key, $meta['meta_value'], true);
    }

    $info = get_blog_details([
        'blog_id' => $siteID
    ]);

    $response['message'] = 'Page Copied To ' . $info->blogname;

    wp_send_json_success($response);
}

add_action('wp_ajax_mscc_create_post', 'msccCreatePost');
add_action('wp_ajax_nopriv_mscc_create_post', 'msccCreatePost');
