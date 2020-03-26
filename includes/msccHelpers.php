<?php

function msccLoadScripts()
{
    wp_register_script('mscc_js', WP_PLUGIN_URL . '/multi-site-content-copier/assets/multi-site-content-copier.js', ['jquery']);
    wp_localize_script('mscc_js', 'msccAjax', ['ajaxurl' => admin_url('admin-ajax.php')]);

    wp_enqueue_script('jquery');
    wp_enqueue_script('mscc_js');
}

add_action('init', 'msccLoadScripts');

function msccGetAllSites()
{
    $sites = get_sites([
        'site__not_in' => get_current_blog_id()
    ]);

    return $sites;
}

function msccGetSelectOptions()
{
    $sites = msccGetAllSites();
    $data = [];

    foreach ($sites as $site) {
        $blogDetails = get_blog_details(array('blog_id' => $site->blog_id));

        array_push($data, [
            'id' => $site->blog_id,
            'siteName' => $blogDetails->blogname
        ]);
    }

    return $data;
}

function msccGetPostData($postID, $output = ARRAY_A)
{
    global $wpdb;

    return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}posts WHERE ID = {$postID}", ARRAY_A);
}

function msccGetPostMetaData($postID, $output = ARRAY_A)
{
    global $wpdb;

    return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}postmeta WHERE post_id = {$postID}", ARRAY_A);
}

function msccImageHelper($id, $siteID, $destinationPostID)
{
    restore_current_blog();
    $postFromMeta = get_post($id, ARRAY_A);
    switch_to_blog($siteID);

    if ($postFromMeta !== null) {
        if ($postFromMeta['post_type'] === 'attachment') {
            $imagePost = msccCopyImage($postFromMeta['ID'], $destinationPostID, $siteID);

            return $imagePost;
        }
    }

    return $id;
}

function msccCopyImage($imageID, $destinationPost, $destinationSite)
{
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');

    restore_current_blog();

    $image = get_post($imageID, ARRAY_A);

    $fileName = basename($image['guid']);

    $uploadDir = wp_upload_dir();

    $sourceLocation = $uploadDir['path'] . '/' . $fileName;

    switch_to_blog($destinationSite);

    $insertedImage = null;

    $uploadFile = true;

    if (msccDoesFileExits($fileName)) {
        $images = get_posts([
            'post_type' => 'attachment'
        ]);

        $destinationUploadDir = wp_upload_dir();

        $foundImage = array_filter($images, function ($image) use ($destinationUploadDir, $fileName) {
            $url = $destinationUploadDir['url'] . '/' . $fileName;
            return $image->guid === $url;
        });

        if (is_array($foundImage) && !empty($foundImage)) {
            $uploadFile = false;

            $foundImage = reset($foundImage);

            $insertedImage = $foundImage->ID;
        }
    }

    if ($uploadFile) {
        $result = wp_upload_bits($fileName, null, file_get_contents($sourceLocation));

        $attachmentArgs = array_merge($image, [
            'guid' => $result['url'],
        ]);

        unset($attachmentArgs['ID']);
        unset($attachmentArgs['post_parent']);

        $insertedImage = wp_insert_attachment($attachmentArgs, $result['file'], $destinationPost);

        $metaResult = wp_generate_attachment_metadata($insertedImage, $result['file']);

        wp_update_attachment_metadata($insertedImage, $metaResult);
    }

    return $insertedImage;
}

function msccDoesFileExits($filename)
{
    $uploadDir = wp_upload_dir();

    return is_file($uploadDir['path'] . '/' . $filename);
}
