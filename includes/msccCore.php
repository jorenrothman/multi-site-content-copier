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

    $destinationPostID = msccCreatePostOnSubSite($postID, $siteID);

    msccCopyMetaData($postID, $siteID, $destinationPostID);

    msccCopyTerms($postID, $destinationPostID, $siteID);

    msccSetFeaturedImage($postID, $destinationPostID, $siteID);

    $info = get_blog_details([
        'blog_id' => $siteID
    ]);

    $response['message'] = 'Page Copied To ' . $info->blogname;

    wp_send_json_success($response);
}

add_action('wp_ajax_mscc_create_post', 'msccCreatePost');
add_action('wp_ajax_nopriv_mscc_create_post', 'msccCreatePost');
