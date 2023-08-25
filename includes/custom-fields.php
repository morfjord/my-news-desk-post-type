<?php

// Hook into WordPress' init action hook
add_action('init', 'mndpt_add_custom_field');

// Function to add a custom field
function mndpt_add_custom_field() {
    // Check if ACF is active
    if (function_exists('acf_add_local_field')) {
        // Define the custom field
        $field = array(
            'key' => 'field_photo_url',
            'name' => 'photo',
            'type' => 'url',
            'label' => 'Photo URL',
            'instructions' => 'Enter the URL of the photo',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => array(
                'width' => '',
                'class' => '',
                'id' => '',
            ),
        );

        // Define the field group
        $field_group = array(
            'key' => 'group_photo_url',
            'title' => 'Photo Details',
            'fields' => array($field),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'mynewsdeskpost', // Replace with your custom post type
                    ),
                ),
            ),
        );

        // Add the field group
        acf_add_local_field_group($field_group);
    }
}

?>
