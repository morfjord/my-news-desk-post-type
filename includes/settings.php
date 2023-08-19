<?php

/**
 * Plugin settings
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds settings link in plugins list page
 *
 * @param array $links
 *
 * @return void
 */
function mndpt_settings_link($links)
{
    $settings_link = '<a href="options-general.php?page=trippple-options.php">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter("plugin_action_links_$plugin", 'mndpt_settings_link');

/**
 * custom option and settings
 */
function mndpt_settings_init()
{
    // Register a new setting for "trippple_options" page.
    register_setting('trippple_options', 'mndpt_license');

    // Register a new section in the "trippple_options" page.
    add_settings_section(
        'trippple_license_section',
        __('Trippple licenses', 'my-news-desk-post-type' ),
        'trippple_license_section_developers_callback',
        'trippple-options'
    );

    // Register a new field in the "sl_wac_license_section" section, inside the "simplylearn-options" page.
    add_settings_field(
        'mndpt_license',
        __('MyNewsDesk Posts Plugin License', 'my-news-desk-post-type' ),
        'mndpt_license_field_cb',
        'trippple-options',
        'trippple_license_section',
        array(
            'label_for'         => 'mndpt_license',
            'class'             => 'trippple_row',
        )
    );
}

/**
 * Register our sl_settings_init to the admin_init action hook.
 */
add_action('admin_init', 'mndpt_settings_init');


/**
 * Developers section callback function.
 *
 * @param array $args  The settings array, defining title, id, callback.
 */
if (!function_exists('trippple_license_page')) {

    function trippple_license_section_developers_callback($args)
    {
    }
}

/*
 * License field HTML
 * @param array $args
 */
function mndpt_license_field_cb($args)
{
    $options = get_option('mndpt_license');
?>
    <input size="40" id="<?php echo esc_attr($args['label_for']); ?>" value="<?php echo esc_attr($options); ?>" name="<?php echo esc_attr($args['label_for']); ?>">
    <?php
}

if (!function_exists('trippple_license_page')) {

    /**
     * Add the top level menu page.
     */
    function trippple_license_page()
    {
        add_submenu_page(
            'options-general.php',
            'Trippple Licenses',
            'Trippple Licenses',
            'manage_options',
            'trippple-options',
            'trippple_options_page_html'
        );
    }
    add_action('admin_menu', 'trippple_license_page');
}


/**
 * Register our trippple_license_page to the admin_menu action hook.
 */


/**
 * Top level menu callback function
 */
if (!function_exists('trippple_options_page_html')) {

    function trippple_options_page_html()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['settings-updated'])) {
            add_settings_error('trippple_messages', 'trippple_message', __('Settings Saved', 'my-news-desk-post-type' ), 'updated');
        }

    ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('trippple_options');
                do_settings_sections('trippple-options');
                submit_button(__('Save', 'my-news-desk-post-type' ));
                ?>
            </form>
        </div>
<?php
    }
}
