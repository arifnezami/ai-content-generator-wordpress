<?php
/*
Plugin Name: AI Content Generator for WP
Description: Generate blog posts based on a user-provided title. Powered by AI.
Version: 1.0
Author: Arif Nezami
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Enqueue JavaScript and CSS
function content_generator_enqueue_scripts() {
   wp_enqueue_script('content-generator-js', plugin_dir_url(__FILE__) . 'content-generator.js', array('jquery'), '1.0.0', true);

    wp_localize_script('content-generator-js', 'ContentGenerator', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
    ));
}
add_action('admin_enqueue_scripts', 'content_generator_enqueue_scripts');

// Add a content generation box
function content_generator_add_meta_box() {
    add_meta_box(
        'content-generator',
        'Content Generator',
        'content_generator_meta_box_callback',
        'post',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'content_generator_add_meta_box');

function content_generator_meta_box_callback($post) {
    ?>
    <div>
        <input type="text" id="content-generator-title" placeholder="Enter your blog title" style="width: 80%;">
        <button type="button" id="content-generator-generate" class="button button-primary">Create</button>
        <button type="button" id="content-generator-insert" class="button">Insert to Body</button>
    </div>
    <div id="content-generator-output" style="margin-top: 20px;"></div>
    <?php
}

// AJAX handler for content generation

function content_generator_generate_content() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Unauthorized', 403);
    }

    $title = sanitize_text_field($_POST['title'] ?? '');

    if (empty($title)) {
        wp_send_json_error('Title is required.');
    }

    
    $openaiApiKey = get_option('content_generator_api_key');
    if (empty($openaiApiKey)) {
        wp_send_json_error('OpenAI API key is not set.');
    }
    
   
    $model = 'gpt-3.5-turbo';
    $userMessage = "Create a blog post on the topic ".$title; // This is your variable message content
    
    // Data to be sent in the request
    $data = [
        "model" => $model,
        "messages" => [
            [
                "role" => "user",
                "content" => $userMessage
            ]
        ]
    ];
    

    
    // Set up headers and body for the HTTP POST request
    $args = [
        'body'        => wp_json_encode($data),
        'headers'     => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $openaiApiKey
        ],
        'timeout'     => 60, // Response timeout
        'redirection' => 5, // Number of allowed redirections
        'blocking'    => true, // If set to false, the request returns immediately and does not wait for the remote server's response
        'httpversion' => '1.1', // Set HTTP version
        'sslverify'   => true // Enable SSL certificate verification
    ];
    
    // Make the HTTP POST request
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);
    
    // Check for errors
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        echo "Something went wrong.";
    } else {
        // Print the body of the response
      //  echo wp_remote_retrieve_body($response);
    }
    


    if (!$response) {
        wp_send_json_error('Failed to connect to OpenAI API');
    }

    $response = wp_remote_retrieve_body($response);
    
    $data = wp_json_decode($response, true);
    
    
    if (isset($data['choices'][0]['message']['content'])) {
        $content = trim($data['choices'][0]['message']['content']);
        wp_send_json_success(array('content' => $data['choices'][0]['message']['content']));
    } else {
        wp_send_json_error('Failed to generate content'.$data);
        print_r( $response );
    }
}


add_action('wp_ajax_content_generator_generate_content', 'content_generator_generate_content');

// Add a menu item under Settings
function content_generator_admin_menu() {
    add_options_page(
        'AI Content for WordPress Settings', 
        'AI Content for WordPress', 
        'manage_options', 
        'content-generator', 
        'content_generator_settings_page'
    );
}
add_action('admin_menu', 'content_generator_admin_menu');

// Settings page content
function content_generator_settings_page() {
    ?>
    <div class="wrap">
        <h1>AI Content for WordPress</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('content-generator-options');
            do_settings_sections('content-generator');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register and define the settings
function content_generator_admin_settings() {
    register_setting('content-generator-options', 'content_generator_api_key');
    add_settings_section(
        'content-generator-main',
        'OpenAI API Settings',
        'content_generator_section_text',
        'content-generator'
    );
    add_settings_field(
        'content_generator_api_key',
        'OpenAI API Key',
        'content_generator_api_key_input',
        'content-generator',
        'content-generator-main'
    );
}
add_action('admin_init', 'content_generator_admin_settings');

function content_generator_section_text() {
    echo '<p>Enter your OpenAI API key here.</p>';
}

function content_generator_api_key_input() {
    $api_key = get_option('content_generator_api_key');
    echo "<input id='content_generator_api_key' name='content_generator_api_key' size='40' type='text' value='" . esc_attr($api_key) . "' />";
}

