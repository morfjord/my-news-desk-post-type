<?php

/*
Plugin Name: MyNewsDesk PostType Viewer
Description: Adds MyNewsDesk Posts to Wordpress.
Version: 1.0.6
Author: Trippple
Author URI: https://trippple.no/
Text Domain: my-news-desk-post-type
Domain Path: /languages
*/

//create custom post type
require_once 'includes/mndpt-admin-notices.php';
function mndpt_create_posttype()
{
    register_post_type(
        'mynewsdesk',
        // CPT Options
        array(
            'labels' => array(
                'name' => __('MyNewsDesk', 'my-news-desk-post-type'),
                'singular_name' => __('MyNewsDesk', 'my-news-desk-post-type')
            ),
            'public' => true,
            'has_archive' => true,
            'rewrite' => array('slug' => 'mynewsdesk'),
            'show_in_rest' => true,
            'taxonomies' => array('category'),
            'supports' => array('title', 'editor', 'thumbnail'), // Added 'thumbnail' support
        )
    );
}
add_action('init', 'mndpt_create_posttype');


/**
 * Add Mynewsdesk settings page
 *
 * @return void
 */
function mndpt_setting_page()
{
    add_submenu_page(
        'edit.php?post_type=mynewsdesk',
        __('MyNewsDesk Settings', 'my-news-desk-post-type'),
        __('MyNewsDesk Settings', 'my-news-desk-post-type'),
        'manage_options',
        'books-shortcode-ref',
        'mndpt_setting_page_callback'
    );
}

add_action('admin_menu', 'mndpt_setting_page');

/**
 * Display callback for the submenu page.
 */
function mndpt_setting_page_callback()
{
?>
    <div class="wrap">
        <h1><?php _e('MyNewsDesk Settings', 'my-news-desk-post-type'); ?></h1>
        <p><?php _e('Sync Mynewsdesk posts', 'my-news-desk-post-type'); ?></p>
        <form action="/wp-admin/admin-post.php" method="post">
            <input type="hidden" name="action" value="sync_mynewsdesk_posts">
            <input type="submit" value="Sync" class="page-title-action">
        </form>
    </div>
<?php
}

add_action('admin_post_sync_mynewsdesk_posts', 'mndpt_sync_posts');
function mndpt_sync_posts()
{
    global $wpdb;
    $query = "SELECT COUNT(*) as total_count FROM $wpdb->posts WHERE post_type = %s";
    $args = ['mynewsdesk'];
    $result = $wpdb->get_results($wpdb->prepare($query, $args));
    $db_posts_count = (int)$result[0]->total_count;
    $total_count = (int)get_option('mndpt_posts_count', 0);
    $offset = (int)get_option('mndpt_api_offset', 0);
    $limit = 20;


    $api_url = "https://www.mynewsdesk.com/services/pressroom/list/iYHZThux7m-sAL941mXGjA.xml?limit=$limit&offset=$offset";
    $api_data = file_get_contents($api_url);
    $xml = simplexml_load_string($api_data) or die("Error: Cannot create object");
    $total_posts = (int)$xml['total-count'][0];

    update_option('mndpt_posts_count', $total_posts);
    if ((int)$db_posts_count < $total_posts && $offset <= $total_count) {
        $offset = $offset + $limit;
    } else {
        $offset = 0;
    }
    update_option('mndpt_api_offset', $offset);
    $posts = $xml->item;
    $posts_created = 0;
    foreach ($posts as $key => $post) {
        if (false  === mndpt_posts_exists($post)) {
            $post_attributes = mndpt_get_post_attributes($post);
            $post_id = wp_insert_post($post_attributes);
            mndpt_set_post_image($post_id, $post);
            $posts_created++;
        }
    }
    new MNDPT_Admin_Notice("$posts_created new posts added.");
    wp_redirect(admin_url('/edit.php?post_type=mynewsdesk'));

    exit;
}

/**
 * Checks if post already exists in DB
 *
 * @param [type] $post
 *
 * @return bool
 */
function mndpt_posts_exists($post)
{
    global $wpdb;
    $args = array(
        'mynewsdesk_id',
        strval($post->id)
    );
    $query = "SELECT * FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s";
    $result = $wpdb->get_var($wpdb->prepare($query, $args));
    return $result != null;
}

/**
 * Generates array for wp_insert_post
 *
 * @param [type] $post
 *
 * @return void
 */
function mndpt_get_post_attributes($post)
{
    return array(
        'ID' => 0,
        'post_author' => 1,
        'post_date_gmt' => strval($post->published_at['datetime']),
        'post_content' => strval($post->body),
        'post_title' => strval($post->header),
        'post_excerpt' => strval($post->summary),
        'post_status' => 'publish',
        'post_type' => 'mynewsdesk',
        'post_category' => mndpt_get_categories($post),
        'tax_input' => array(
            'tags' => mndpt_get_tags($post),
        ),
        'meta_input' => array(
            'mynewsdesk_url' => strval($post->url),
            'mynewsdesk_id' => strval($post->id),
            'mynewsdesk_type_of_media' => strval($post->type_of_media),
            'mynewsdesk_language' => strval($post->language),
            'mynewsdesk_source_id' => strval($post->source_id),
            'mynewsdesk_source_name' => strval($post->source_name),
            'mynewsdesk_pressroom_name' => strval($post->pressroom_name),
            'mynewsdesk_pressroom' => strval($post->pressroom),
            'mynewsdesk_pressroom_id' => strval($post->pressroom_id),
            'mynewsdesk_organization_number' => strval($post->organization_number),
            'mynewsdesk_image' => strval($post->image),
            'mynewsdesk_image_caption' => strval($post->image_caption),
        )
    );
}

//Ny endringer Martin
function mndpt_set_post_image($post_id, $image_url) {
    // Download the image
    $response = wp_remote_get($image_url);
    $image_data = wp_remote_retrieve_body($response);

    // Get the filename
    $filename = basename($image_url);

    // Save the image to the uploads directory
    $upload = wp_upload_bits($filename, null, $image_data);
    if ($upload['error']) {
        return false; // Return false if there was an error
    }

    // Create an attachment
    $attachment = array(
        'post_mime_type' => 'image/jpeg', // Set the MIME type
        'post_title' => $filename,
        'post_content' => '',
        'post_status' => 'inherit',
    );
    $attach_id = wp_insert_attachment($attachment, $upload['file'], $post_id);

    // Generate attachment metadata
    $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
    wp_update_attachment_metadata($attach_id, $attach_data);

    // Set the featured image
    set_post_thumbnail($post_id, $attach_id);
	
	// Update the ACF field 'field_64e10451161c6' Remember that ACF need to have a photo_my_new feild - this is a temperay fix
if ($post_id && $photo_url) {
    update_field('field_64e10451161c6', $photo_url, $post_id);
    error_log('ACF field updated with photo URL: ' . $photo_url);
}

	
	
    return true;
}





add_filter('cron_schedules', 'mndpt_add_cron_interval');
function mndpt_add_cron_interval($schedules)
{
    $schedules['five_minutes'] = array(
        'interval' => 300,
        'display'  => esc_html__('Every Five Minutes', 'my-news-desk-post-type'),
    );
    return $schedules;
}

add_action('mndpt_cron_hook', 'mndpt_sync_posts');
if (!wp_next_scheduled('mndpt_cron_hook')) {
    wp_schedule_event(time(), 'five_minutes', 'mndpt_cron_hook');
}

$plugin = plugin_basename(__FILE__);
require_once 'includes/settings.php';

$license = get_option('mndpt_license');

if ($license) {
    if (mndpt_license_valid($license)) {

        require 'kernl-update-checker/kernl-update-checker.php';
        $kernlUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
            'https://kernl.us/api/v1/updates/618d08208dd96e48b8e0330a/',
            __FILE__,
            'my-news-desk-post-type'
        );

        $kernlUpdateChecker->license = $license;
    } else {

        add_action('admin_notices', 'mndpt_invalid_license');
    }
} else {
    add_action('admin_notices', 'mndpt_enter_license');
}


function mndpt_license_valid($key)
{
    $kernl_license_url = "https://kernl.us/api/v2/public/license/validate?license={$key}";
    $response = wp_remote_get($kernl_license_url);
    return wp_remote_retrieve_response_code($response) === 200;
}

function mndpt_invalid_license()
{
    $screen = get_current_screen();
    if ($screen->id !== 'plugins') return;
    $menu_url = menu_page_url('trippple-options', false);

?>
    <div class="notice notice-error is-dismissible">
        <p>
            <a href="<?php esc_attr_e($menu_url, 'my-news-desk-post-type'); ?>">
                <?php _e('Invalid License! Please check your MyNewsDesk Post Viewer License!', 'my-news-desk-post-type') ?>
            </a>
        </p>
    </div>
<?php
}

/**
 * Undocumented function
 *
 * @return void
 */
function mndpt_enter_license()
{
    $screen = get_current_screen();
    if ($screen->id !== 'plugins') return;
    $menu_url = menu_page_url('trippple-options', false);

?>
    <div class="notice notice-error is-dismissible">
        <p>
            <a href="<?php esc_attr_e($menu_url, 'my-news-desk-post-type'); ?>">
                <?php _e('Please enter your MyNewsDesk Post Viewer License !', 'my-news-desk-post-type') ?>
            </a>
        </p>
    </div>
    <?php
}

/**
 * Adds canonical tag
 *
 * @return void
 */
function mndpt_add_canonical_tag()
{
    if (get_post_type() === 'mynewsdesk') {
        $id = get_the_ID();
        $url = get_post_meta($id, 'mynewsdesk_url', true);
        if ($url) {
    ?>
            <link rel="canonical" href="<?php esc_attr_e($url); ?>" />
    <?php
        }
    }
}
add_action('wp_head', 'mndpt_add_canonical_tag');

add_filter('use_block_editor_for_post_type', 'mndpt_prefix_disable_gutenberg', 10, 2);
/**
 * Disabled gutenberg editor for mynewsdek posttype
 *
 * @param [type] $current_status
 * @param [type] $post_type
 *
 * @return void
 */
function mndpt_prefix_disable_gutenberg($current_status, $post_type)
{
    if ($post_type === 'mynewsdesk') return false;
    return $current_status;
}

/**
 * Added metabox in post type to show MyNewsDesk post url.
 *
 * @return void
 */
function mndpt_register_meta_boxes()
{
    add_meta_box('mdpt-meta-box', __('MyNewsDesk Post Url', 'my-news-desk-post-type'), 'mndpt_my_display_callback', 'mynewsdesk', 'side');
}
add_action('add_meta_boxes', 'mndpt_register_meta_boxes');

/**
 * Meta box display callback.
 *
 * @param WP_Post $post Current post object.
 */
function mndpt_my_display_callback($post)
{
    $url = get_post_meta($post->ID, 'mynewsdesk_url', true);
    ?>
    <a href="<?php esc_attr_e($url) ?>" target="_blank"><?php esc_html_e($url) ?></a>
<?php
}

add_filter('pre_get_posts', 'mndpt_query_post_type');
function mndpt_query_post_type($query)
{
    if (is_category()) {
        $post_type = get_query_var('post_type');
        if ($post_type)
            $post_type = $post_type;
        else
            $post_type = array('nav_menu_item', 'post', 'mynewsdesk'); // don't forget nav_menu_item to allow menus to work!
        $query->set('post_type', $post_type);
        return $query;
    }
}
add_action('init', 'mndpt_create_topics_nonhierarchical_taxonomy', 0);

function mndpt_create_topics_nonhierarchical_taxonomy()
{

    // Labels part for the GUI

    $labels = array(
        'name' => _x( 'Tags', 'Tags taxonomy general name', 'my-news-desk-post-type' ),
        'singular_name' => _x('Tag', 'taxonomy singular name', 'my-news-desk-post-type' ),
        'search_items' =>  __( 'Search Tags', 'my-news-desk-post-type'),
        'popular_items' => __('Popular Tags', 'my-news-desk-post-type' ),
        'all_items' => __('All Tags', 'my-news-desk-post-type' ),
        'parent_item' => null,
        'parent_item_colon' => null,
        'edit_item' => __('Edit Tag', 'my-news-desk-post-type' ),
        'update_item' => __('Update Tag', 'my-news-desk-post-type' ),
        'add_new_item' => __('Add New Tag', 'my-news-desk-post-type' ),
        'new_item_name' => __('New Tag Name', 'my-news-desk-post-type' ),
        'separate_items_with_commas' => __('Separate tags with commas', 'my-news-desk-post-type' ),
        'add_or_remove_items' => __('Add or remove tags', 'my-news-desk-post-type' ),
        'choose_from_most_used' => __('Choose from the most used tags', 'my-news-desk-post-type' ),
        'menu_name' => __('Tags', 'my-news-desk-post-type' ),
    );

    // Now register the non-hierarchical taxonomy like tag

    register_taxonomy('tags', 'mynewsdesk', array(
        'hierarchical' => false,
        'labels' => $labels,
        'show_ui' => true,
        'show_in_rest' => true,
        'show_admin_column' => true,
        'update_count_callback' => '_update_post_term_count',
        'query_var' => true,
        'rewrite' => array('slug' => 'tag'),
    ));
}

/**
 * Get post tags
 *
 * @param $post
 * @return array
 */
function mndpt_get_tags( $post )
{
    $tags = array();

    if($post->tags){
        $post_tags = json_decode(json_encode($post->tags), true);

        foreach((array)$post_tags['tag'] as $tag ){
            $term_id = term_exists($tag, 'tags');
            if( null === $term_id){
                $term = wp_insert_term($tag,'tags');
                $term_id = $term['term_id'];
            }
            $tags[] = $tag;
        }
    }

    return $tags;
}

/**
 * Get mynewsdesk post tags as categories
 *
 * @param $post
 * @return array
 */

// Include the custom fields file
include plugin_dir_path(__FILE__) . 'include/custom-fields.php';


// Assuming $post_id is the ID of the post that was just created or updated
if ($post_id) {
    $photo_url = 'URL_OF_THE_PHOTO'; // Replace with the actual photo URL
    update_field('photo', $photo_url, $post_id);
}


// After setting the featured image
if ($post_id && $photo_url) {
    update_field('photo_my_news', $photo_url, $post_id);
}





function mndpt_get_categories($post)
{
    $tags = array();
    if ($post->subjects) {
        $post_tags = json_decode(json_encode($post->subjects), true);
        foreach ((array)$post_tags['subject'] as $subject) {
            $term = term_exists($subject, 'category');
            if (null === $term) {
                $category_id = wp_insert_category(
                                    array(
                                        'cat_name' => $subject,
                                    )
                                );
            }else{
                $category_id = $term['term_id'];
            }
            $tags[] = $category_id;
        }
    }
    return $tags;
}

// Add new column for featured image
function mndpt_add_image_column($columns) {
    if (isset($_GET['post_type']) && $_GET['post_type'] == 'mynewsdesk') {
        $columns['featured_image'] = 'Featured Image';
    }
    return $columns;
}
add_filter('manage_posts_columns', 'mndpt_add_image_column');

// Display the featured image in the new column
function mndpt_display_image_column($column_name, $post_id) {
    if ($column_name == 'featured_image') {
        $post_thumbnail_id = get_post_thumbnail_id($post_id);
        $post_thumbnail_img = wp_get_attachment_image_src($post_thumbnail_id, 'thumbnail');
        echo '<img src="' . $post_thumbnail_img[0] . '" width="50" height="50" />';
    }
}
add_action('manage_posts_custom_column', 'mndpt_display_image_column', 10, 2);
