<?php

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
