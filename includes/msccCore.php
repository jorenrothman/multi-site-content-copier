<?php

function msccInitPlugin()
{
    add_action('add_meta_boxes', 'msccAddMetaBox');
}

add_action('admin_init', 'msccInitPlugin');

function msccAddMetaBox($post)
{
    add_meta_box('mscc_meta_box', 'Multi Site Content Copier', 'msscBuildMetaBox', null, 'side', 'low');
}

function msscBuildMetaBox()
{
    // our code here

    $options = msccGetSelectOptions();

?>

    <div class="inside">
        <label for="mscc-location-selector">Select destination</label>
        <p>
            <select id="mscc-location-selector" style="width: 100%">
                <?php
                foreach ($options as $option) : ?>
                    <option value="<?= $option['id'] ?>"><?= $option['siteName'] ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <input type="submit" class="button button-primary button-large" value="Copy Page" />
        </p>
    </div>
<?php
}

add_action('save_post', 'msccMetaBoxCallback');

function msccMetaBoxCallback($postID)
{
    var_dump($postID);

    die();
}
