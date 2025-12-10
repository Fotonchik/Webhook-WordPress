<?php
/**
 * Принимает лиды через REST API, сохраняет их как Custom Post Type и отправляет вебхук в n8n.
 */

// Безопасность: check
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function lwp_register_custom_post_type() {
    $labels = array(
        'name'               => 'Leads',
        'singular_name'      => 'Lead',
        'menu_name'          => 'Leads',
        'add_new'            => 'Add New Lead',
        'add_new_item'       => 'Add New Lead',
        'edit_item'          => 'Edit Lead',
        'new_item'           => 'New Lead',
        'view_item'          => 'View Lead',
        'search_items'       => 'Search Leads',
        'not_found'          => 'No leads found',
        'not_found_in_trash' => 'No leads found in Trash',
    );

    $args = array(
        'labels'            => $labels,
        'public'            => false, 
        'show_ui'           => true,  
        'show_in_menu'      => true,
        'menu_icon'         => 'dashicons-id-alt',
        'capability_type'   => 'post',
        'supports'          => array( 'title', 'editor', 'custom-fields' ),
        'has_archive'       => false,
    );

    register_post_type( 'lead', $args );
}
add_action( 'init', 'lwp_register_custom_post_type' );

function lwp_register_rest_route() {
    register_rest_route( 'leads/v1', '/submit', array(
        'methods'  => 'POST',
        'callback' => 'lwp_handle_lead_submission',
        'permission_callback' => '__return_true', // Заглушка
    ) );
}
add_action( 'rest_api_init', 'lwp_register_rest_route' );

/**
 * Обработчик отправки лида
 */
function lwp_handle_lead_submission( WP_REST_Request $request ) {
    $response = array();
    $params = $request->get_json_params();

    $name = sanitize_text_field($params['name'] ?? '');
    $email = sanitize_email($params['email'] ?? '');
    $phone = sanitize_text_field($params['phone'] ?? '');

    $errors = array();
    if ( empty( $name ) ) {
        $errors[] = 'Name is required.';
    }
    if ( ! is_email( $email ) ) {
        $errors[] = 'Valid email is required.';
    }
    if ( empty( $phone ) ) {
        $errors[] = 'Phone is required.';
    }

    if ( ! empty( $errors ) ) {
        return new WP_REST_Response( array( 'success' => false, 'errors' => $errors ), 400 );
    }

    $lead_data = array(
        'post_title'   => $name . ' - ' . $email,
        'post_content' => "Phone: $phone\nEmail: $email",
        'post_status'  => 'publish',
        'post_type'    => 'lead',
        'meta_input'   => array(
            'lead_email' => $email,
            'lead_phone' => $phone,
            'lead_name'  => $name,
        ),
    );

    // Сохранение
    $post_id = wp_insert_post( $lead_data, true );

    if ( is_wp_error( $post_id ) ) {
        return new WP_REST_Response( array( 'success' => false, 'message' => 'Failed to save lead.' ), 500 );
    }

    // n8n
    $webhook_url = 'https://your-n8n-instance.com/webhook/wordpress-lead'; // Заглушка
    $webhook_payload = array(
        'id' => $post_id,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'source' => 'wordpress_webhook_plugin',
        'timestamp' => current_time( 'mysql' ),
    );

    // POST
    $webhook_response = wp_remote_post( $webhook_url, array(
        'body'    => json_encode( $webhook_payload ),
        'headers' => array( 'Content-Type' => 'application/json' ),
        'timeout' => 15,
    ) );

    // Лог
    if ( is_wp_error( $webhook_response ) ) {
        $webhook_sent = false;
        $log_message = 'Webhook delivery failed: ' . $webhook_response->get_error_message();
    } else {
        $response_code = wp_remote_retrieve_response_code( $webhook_response );
        $webhook_sent = ( $response_code >= 200 && $response_code < 300 );
        $log_message = $webhook_sent ? 'Webhook delivered successfully.' : 'Webhook delivery failed with code: ' . $response_code;
    }

    update_post_meta( $post_id, 'webhook_sent', $webhook_sent );
    update_post_meta( $post_id, 'webhook_log', $log_message );

    $response_data = array(
        'success' => true,
        'message' => 'Lead saved successfully.',
        'lead_id' => $post_id,
        'webhook_delivered' => $webhook_sent,
    );

    // Check
    if ( ! $webhook_sent ) {
        $response_data['webhook_warning'] = $log_message;
    }

    return new WP_REST_Response( $response_data, 200 );
}

function lwp_add_custom_columns( $columns ) {
    $new_columns = array(
        'cb'      => $columns['cb'],
        'title'   => $columns['title'],
        'email'   => 'Email',
        'phone'   => 'Phone',
        'webhook' => 'Webhook Status',
        'date'    => $columns['date'],
    );
    return $new_columns;
}
add_filter( 'manage_lead_posts_columns', 'lwp_add_custom_columns' );

function lwp_display_custom_columns( $column, $post_id ) {
    switch ( $column ) {
        case 'email':
            echo get_post_meta( $post_id, 'lead_email', true );
            break;
        case 'phone':
            echo get_post_meta( $post_id, 'lead_phone', true );
            break;
        case 'webhook':
            // Check
            $sent = get_post_meta( $post_id, 'webhook_sent', true );
            echo $sent ? 'Sent' : 'Failed';
            break;
    }
}
add_action('manage_lead_posts_custom_column', 'lwp_display_custom_columns', 10, 2);