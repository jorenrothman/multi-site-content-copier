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

function msccCreatePostOnSubSite($postID, $siteID)
{
    $post = get_post($postID, ARRAY_A);

    unset($post['ID']);
    $post['post_status'] = 'draft';

    switch_to_blog($siteID);

    $destinationPostID = wp_insert_post($post);

    restore_current_blog();

    return $destinationPostID;
}

function msccCopyMetaData($postID, $siteID, $destinationPostID)
{
    $postMeta = msccGetPostMetaData($postID);

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

    restore_current_blog();
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

function msccCopyTerms($postID, $destinationPostID, $destinationSiteID)
{
    restore_current_blog();

    $taxonomies = msccGetPostTaxonomies($postID);
    $terms = msccGetPostTermsByTaxonomies($postID, $taxonomies);

    msccCreateCategoriesOtherSite($terms, $destinationPostID, $destinationSiteID);

    msccSetPostTerms($terms, $destinationPostID, $destinationSiteID);
}

function msccGetPostTaxonomies($postID)
{
    $post = get_post($postID);

    $taxonomies = get_object_taxonomies($post);

    return $taxonomies;
}

function msccGetPostTermsByTaxonomies($postID, $taxonomies)
{
    if (!is_array($taxonomies) || empty($taxonomies)) {
        return [];
    }

    $allTerms = [];

    foreach ($taxonomies as $taxonomy) {
        $terms = get_the_terms($postID, $taxonomy);

        if (!$terms) {
            continue;
        }

        $allTerms[$taxonomy] = $terms;
    }

    return $allTerms;
}

function msccCreateCategoriesOtherSite($termsToCompare, $postID, $siteID)
{
    switch_to_blog($siteID);

    $taxonomies = msccGetPostTaxonomies($postID);
    $terms = msccGetPostTermsByTaxonomies($postID, $taxonomies);

    foreach ($termsToCompare as $taxonomyToCompare => $values) {
        $valueSize = sizeof($values);

        if (!array_key_exists($taxonomyToCompare, $terms)) {
            for ($i = 0; $valueSize > $i; $i++) {
                $value = $values[$i];

                $termID = wp_insert_term($value->name, $taxonomyToCompare, [
                    'description' => $value->description,
                    'slug' => $value->slug,
                ]);

                if (is_wp_error($termID)) {
                    $termObject = get_term_by('name', $value->name, $taxonomyToCompare);

                    $termID = $termObject->term_id;
                }

                msccSetPostTerms($termID, $taxonomyToCompare, $postID);
            }

            continue;
        }

        $termTemp = $terms[$taxonomyToCompare];

        $termSize = sizeof($termTemp);

        for ($i = 0; $valueSize > $i; $i++) {
            $value = $values[$i];

            for ($j = 0; $termSize > $j; $j++) {
                $term = $termTemp[$i];

                if ($term->slug !== $value->slug) {
                    $termID = wp_insert_term($value->name, $taxonomyToCompare, [
                        'description' => $value->description,
                        'slug' => $value->slug,
                    ]);

                    msccSetPostTerms($termID, $taxonomyToCompare, $postID);
                }
            }
        }
    }

    restore_current_blog();
}

function msccSetPostTerms($termID, $taxonomy, $postID)
{
    wp_set_post_terms($postID, $termID, $taxonomy, true);
}
