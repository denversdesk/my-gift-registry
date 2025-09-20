<?php
/**
 * AJAX handler for My Gift Registry plugin
 *
 * Handles AJAX requests for gift reservations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class My_Gift_Registry_Ajax_Handler {

    /**
     * Constructor
     */
    public function __construct() {
        // Public AJAX actions (for logged out users)
        add_action('wp_ajax_nopriv_reserve_gift', array($this, 'reserve_gift'));
        add_action('wp_ajax_reserve_gift', array($this, 'reserve_gift'));

        // Private AJAX actions (for logged in users only)
        add_action('wp_ajax_validate_slug', array($this, 'validate_slug'));
        add_action('wp_ajax_create_wishlist', array($this, 'create_wishlist'));
        add_action('wp_ajax_update_wishlist', array($this, 'update_wishlist'));
        add_action('wp_ajax_delete_wishlist', array($this, 'delete_wishlist'));

        // Phase 4 AJAX actions for product management
        add_action('wp_ajax_search_products', array($this, 'search_products'));
        add_action('wp_ajax_add_product', array($this, 'add_product'));

        // Phase 5 AJAX action for gift deletion
        add_action('wp_ajax_mgr_delete_gift', array($this, 'delete_gift'));
    }

    /**
     * Handle gift reservation AJAX request
     */
    public function reserve_gift() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'my_gift_registry_nonce')) {
            wp_send_json_error(__('Security check failed.', 'my-gift-registry'));
            return;
        }

        // Validate required fields
        $gift_id = intval($_POST['gift_id']);
        $email = sanitize_email($_POST['email']);
        $confirm_email = sanitize_email($_POST['confirm_email']);

        if (empty($gift_id) || empty($email) || empty($confirm_email)) {
            wp_send_json_error(__('All fields are required.', 'my-gift-registry'));
            return;
        }

        // Validate email format
        if (!is_email($email) || !is_email($confirm_email)) {
            wp_send_json_error(__('Please enter valid email addresses.', 'my-gift-registry'));
            return;
        }

        // Check if emails match
        if ($email !== $confirm_email) {
            wp_send_json_error(__('Email addresses do not match.', 'my-gift-registry'));
            return;
        }

        // Check if gift exists and get details
        $db_handler = new My_Gift_Registry_DB_Handler();
        $gift = $db_handler->get_gift_with_wishlist($gift_id);

        if (!$gift) {
            wp_send_json_error(__('Gift not found.', 'my-gift-registry'));
            return;
        }

        // Check if gift is already reserved
        global $wpdb;
        $reservations_table = $wpdb->prefix . 'my_gift_registry_reservations';
        $existing_reservation = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$reservations_table} WHERE gift_id = %d",
                $gift_id
            )
        );

        if ($existing_reservation > 0) {
            wp_send_json_error(__('This gift has already been reserved.', 'my-gift-registry'));
            return;
        }

        // Reserve the gift
        $reservation_success = $db_handler->reserve_gift($gift_id, $email);

        if (!$reservation_success) {
            wp_send_json_error(__('Failed to reserve gift. Please try again.', 'my-gift-registry'));
            return;
        }

        // Log the reservation activity
        $db_handler->log_activity(
            null, // No user ID for anonymous reservations
            'gift_reserved',
            sprintf(__('Gift "%s" from wishlist "%s" was reserved by %s', 'my-gift-registry'), $gift->title, $gift->wishlist_title, $email)
        );

        // Send confirmation email
        $email_handler = new My_Gift_Registry_Email_Handler();
        $email_sent = $email_handler->send_reservation_confirmation($email, $gift);

        // Return success response
        wp_send_json_success(array(
            'message' => __('Gift reserved successfully!', 'my-gift-registry'),
            'email_sent' => $email_sent
        ));
    }

    /**
     * Handle slug validation AJAX request
     */
    public function validate_slug() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'my_gift_registry_nonce')) {
            wp_send_json_error(__('Security check failed.', 'my-gift-registry'));
            return;
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'my-gift-registry'));
            return;
        }

        $slug = sanitize_title($_POST['slug']);
        $exclude_id = isset($_POST['exclude_id']) ? intval($_POST['exclude_id']) : 0;

        if (empty($slug)) {
            wp_send_json_error(__('Slug is required.', 'my-gift-registry'));
            return;
        }

        $db_handler = new My_Gift_Registry_DB_Handler();
        $is_unique = $db_handler->is_slug_unique($slug, $exclude_id);

        if ($is_unique) {
            wp_send_json_success(array(
                'valid' => true,
                'message' => __('Slug is available.', 'my-gift-registry')
            ));
        } else {
            // Generate a unique slug
            $unique_slug = $db_handler->generate_unique_slug($slug, $exclude_id);
            wp_send_json_error(array(
                'message' => __('Slug already exists. Suggested: ', 'my-gift-registry') . $unique_slug,
                'suggested_slug' => $unique_slug
            ));
        }
    }

    /**
     * Handle wishlist creation AJAX request
     */
    public function create_wishlist() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'my_gift_registry_nonce')) {
            wp_send_json_error(__('Security check failed.', 'my-gift-registry'));
            return;
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to create a wishlist.', 'my-gift-registry'));
            return;
        }

        $current_user = wp_get_current_user();

        // Validate required fields
        $event_type = sanitize_text_field($_POST['event_type']);
        $event_title = wp_kses($_POST['event_title'], array()); // Allow basic HTML but strip dangerous tags
        $slug = sanitize_title($_POST['slug']);
        $full_name = sanitize_text_field($_POST['full_name']);
        $email = sanitize_email($_POST['email']);

        // Get new fields
        $event_date = isset($_POST['event_date']) ? sanitize_text_field($_POST['event_date']) : null;
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : null;
        $profile_pic = isset($_POST['profile_pic']) ? esc_url_raw($_POST['profile_pic']) : null;

        if (empty($event_title) || empty($slug) || empty($full_name) || empty($email)) {
            wp_send_json_error(__('All fields are required.', 'my-gift-registry'));
            return;
        }

        // Validate email
        if (!is_email($email)) {
            wp_send_json_error(__('Please enter a valid email address.', 'my-gift-registry'));
            return;
        }

        // Check slug uniqueness
        $db_handler = new My_Gift_Registry_DB_Handler();
        if (!$db_handler->is_slug_unique($slug)) {
            wp_send_json_error(__('This slug is already taken. Please choose a different one.', 'my-gift-registry'));
            return;
        }

        // Prepare wishlist data
        $wishlist_data = array(
            'title' => $event_title,
            'slug' => $slug,
            'event_type' => $event_type,
            'event_date' => $event_date,
            'description' => $description,
            'full_name' => $full_name,
            'profile_pic' => $profile_pic,
            'user_id' => $current_user->ID,
            'user_email' => $email,
        );

        // Create wishlist
        $result = $db_handler->create_wishlist($wishlist_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }

        // Send confirmation email
        $email_handler = new My_Gift_Registry_Email_Handler();
        $wishlist_url = home_url('/wishlist/' . $slug);

        $email_sent = $this->send_wishlist_creation_confirmation($email, $wishlist_data, $wishlist_url);

        // Return success response
        wp_send_json_success(array(
            'message' => __('Wishlist created successfully!', 'my-gift-registry'),
            'wishlist_url' => $wishlist_url,
            'email_sent' => $email_sent
        ));
    }

    /**
     * Send wishlist creation confirmation email
     *
     * @param string $email
     * @param array $wishlist_data
     * @param string $wishlist_url
     * @return bool
     */
    private function send_wishlist_creation_confirmation($email, $wishlist_data, $wishlist_url) {
        $subject = __('Your Wishlist Has Been Created!', 'my-gift-registry');

        $message = $this->get_wishlist_creation_email_content($wishlist_data, $wishlist_url);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        $sent = wp_mail($email, $subject, $message, $headers);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('My Gift Registry: Wishlist creation email sent to: ' . $email . ' - Status: ' . ($sent ? 'Success' : 'Failed'));
        }

        return $sent;
    }

    /**
     * Get wishlist creation email content
     *
     * @param array $wishlist_data
     * @param string $wishlist_url
     * @return string
     */
    private function get_wishlist_creation_email_content($wishlist_data, $wishlist_url) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php _e('Wishlist Created', 'my-gift-registry'); ?></title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #f8f9fa; padding: 20px; text-align: center; border-radius: 5px; margin-bottom: 20px; }
                .content { background-color: #fff; border: 1px solid #ddd; padding: 20px; border-radius: 5px; }
                .button { display: inline-block; background-color: #007cba; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; font-size: 12px; color: #666; margin-top: 30px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php _e('🎉 Your Wishlist Has Been Created!', 'my-gift-registry'); ?></h1>
                </div>

                <div class="content">
                    <h2><?php _e('Wishlist Details', 'my-gift-registry'); ?></h2>
                    <p><strong><?php _e('Event Type:', 'my-gift-registry'); ?></strong> <?php echo esc_html($wishlist_data['event_type']); ?></p>
                    <p><strong><?php _e('Event Title:', 'my-gift-registry'); ?></strong> <?php echo esc_html(stripslashes($wishlist_data['title'])); ?></p>
                    <p><strong><?php _e('Your Name:', 'my-gift-registry'); ?></strong> <?php echo esc_html($wishlist_data['full_name']); ?></p>

                    <h3><?php _e('Share Your Wishlist', 'my-gift-registry'); ?></h3>
                    <p><?php _e('You can now share your wishlist with friends and family using this link:', 'my-gift-registry'); ?></p>
                    <p><a href="<?php echo esc_url($wishlist_url); ?>" class="button"><?php _e('View My Wishlist', 'my-gift-registry'); ?></a></p>

                    <h3><?php _e('Next Steps', 'my-gift-registry'); ?></h3>
                    <p><?php _e('You can now add gifts to your wishlist by logging into your account and accessing the wishlist management area.', 'my-gift-registry'); ?></p>
                </div>

                <div class="footer">
                    <p><?php _e('This email was sent by', 'my-gift-registry'); ?> <?php echo get_bloginfo('name'); ?></p>
                    <p><?php _e('If you have any questions, please contact us.', 'my-gift-registry'); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle wishlist update AJAX request
     */
    public function update_wishlist() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'my_gift_registry_nonce')) {
            wp_send_json_error(__('Security check failed.', 'my-gift-registry'));
            return;
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to update a wishlist.', 'my-gift-registry'));
            return;
        }

        $current_user = wp_get_current_user();
        $wishlist_id = intval($_POST['wishlist_id']);

        // Validate required fields
        $event_type = sanitize_text_field($_POST['event_type']);
        $event_title = wp_kses($_POST['event_title'], array());
        $slug = sanitize_title($_POST['slug']);
        $full_name = sanitize_text_field($_POST['full_name']);
        $email = sanitize_email($_POST['email']);

        // Get new fields
        $event_date = isset($_POST['event_date']) ? sanitize_text_field($_POST['event_date']) : null;
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : null;
        $profile_pic = isset($_POST['profile_pic']) ? esc_url_raw($_POST['profile_pic']) : null;

        if (empty($event_title) || empty($slug) || empty($full_name) || empty($email)) {
            wp_send_json_error(__('All fields are required.', 'my-gift-registry'));
            return;
        }

        // Validate email
        if (!is_email($email)) {
            wp_send_json_error(__('Please enter a valid email address.', 'my-gift-registry'));
            return;
        }

        // Check slug uniqueness (excluding current wishlist)
        $db_handler = new My_Gift_Registry_DB_Handler();
        if (!$db_handler->is_slug_unique($slug, $wishlist_id)) {
            wp_send_json_error(__('This slug is already taken. Please choose a different one.', 'my-gift-registry'));
            return;
        }

        // Prepare wishlist data
        $wishlist_data = array(
            'title' => $event_title,
            'slug' => $slug,
            'event_type' => $event_type,
            'event_date' => $event_date,
            'description' => $description,
            'full_name' => $full_name,
            'profile_pic' => $profile_pic,
            'user_email' => $email,
        );

        // Update wishlist
        $result = $db_handler->update_wishlist($wishlist_id, $current_user->ID, $wishlist_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }

        // Return success response
        wp_send_json_success(array(
            'message' => __('Wishlist updated successfully!', 'my-gift-registry'),
            'wishlist_url' => home_url('/wishlist/' . $slug)
        ));
    }

    /**
     * Handle wishlist delete AJAX request
     */
    public function delete_wishlist() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'my_gift_registry_nonce')) {
            wp_send_json_error(__('Security check failed.', 'my-gift-registry'));
            return;
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to delete a wishlist.', 'my-gift-registry'));
            return;
        }

        $current_user = wp_get_current_user();
        $wishlist_id = intval($_POST['wishlist_id']);

        if (empty($wishlist_id)) {
            wp_send_json_error(__('Invalid wishlist ID.', 'my-gift-registry'));
            return;
        }

        // Delete wishlist
        $db_handler = new My_Gift_Registry_DB_Handler();
        $result = $db_handler->delete_wishlist($wishlist_id, $current_user->ID);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }

        // Return success response
        wp_send_json_success(array(
            'message' => __('Wishlist deleted successfully!', 'my-gift-registry')
        ));
    }

    /**
     * Handle product search AJAX request
     */
    public function search_products() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'my_gift_registry_nonce')) {
            wp_send_json_error(__('Security check failed.', 'my-gift-registry'));
            return;
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to search products.', 'my-gift-registry'));
            return;
        }

        $search_term = sanitize_text_field($_POST['search_term']);
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;

        if (empty($search_term)) {
            wp_send_json_error(__('Search term is required.', 'my-gift-registry'));
            return;
        }

        $db_handler = new My_Gift_Registry_DB_Handler();
        $products = $db_handler->search_woocommerce_products($search_term, $limit);

        wp_send_json_success(array(
            'products' => $products
        ));
    }

    /**
     * Handle add product AJAX request
     */
    public function add_product() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'my_gift_registry_nonce')) {
            wp_send_json_error(__('Security check failed.', 'my-gift-registry'));
            return;
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to add products.', 'my-gift-registry'));
            return;
        }

        $current_user = wp_get_current_user();
        $wishlist_id = intval($_POST['wishlist_id']);

        // Validate required fields
        $title = sanitize_text_field($_POST['title']);
        if (empty($title)) {
            wp_send_json_error(__('Product title is required.', 'my-gift-registry'));
            return;
        }

        if (empty($wishlist_id)) {
            wp_send_json_error(__('Wishlist ID is required.', 'my-gift-registry'));
            return;
        }

        // Check if user owns the wishlist
        $db_handler = new My_Gift_Registry_DB_Handler();
        $wishlist = $db_handler->get_user_wishlist($wishlist_id, $current_user->ID);
        if (!$wishlist) {
            wp_send_json_error(__('Wishlist not found or access denied.', 'my-gift-registry'));
            return;
        }

        // Prepare product data
        $product_data = array(
            'title' => $title,
            'description' => isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : null,
            'image_url' => isset($_POST['image_url']) ? esc_url_raw($_POST['image_url']) : null,
            'product_url' => isset($_POST['product_url']) ? esc_url_raw($_POST['product_url']) : null,
            'price' => isset($_POST['price']) ? floatval($_POST['price']) : null,
            'priority' => isset($_POST['priority']) ? intval($_POST['priority']) : 0,
        );

        // Add product to wishlist
        $result = $db_handler->add_product_to_wishlist($wishlist_id, $product_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }

        // Return success response
        wp_send_json_success(array(
            'message' => __('Product added successfully!', 'my-gift-registry'),
            'product_id' => $result
        ));
    }

    /**
     * Handle gift deletion AJAX request
     */
    public function delete_gift() {
        // Verify nonce for security
        check_ajax_referer('mgr_gift_delete_nonce', 'security');

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to delete gifts.', 'my-gift-registry'));
            return;
        }

        $current_user = wp_get_current_user();
        $gift_id = intval($_POST['gift_id']);

        if (empty($gift_id)) {
            wp_send_json_error(__('Invalid gift ID.', 'my-gift-registry'));
            return;
        }

        // Check if gift exists and get wishlist ownership
        global $wpdb;
        $gifts_table = $wpdb->prefix . 'my_gift_registry_gifts';
        $wishlists_table = $wpdb->prefix . 'my_gift_registry_wishlists';

        $gift_check = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT w.user_id, g.title FROM {$gifts_table} g
                 JOIN {$wishlists_table} w ON g.wishlist_id = w.id
                 WHERE g.id = %d",
                $gift_id
            )
        );

        if (!$gift_check) {
            wp_send_json_error(__('Gift not found.', 'my-gift-registry'));
            return;
        }

        // Check if user owns the wishlist
        if ($gift_check->user_id != $current_user->ID) {
            wp_send_json_error(__('You do not have permission to delete this gift.', 'my-gift-registry'));
            return;
        }

        // Delete the gift
        $result = $wpdb->delete(
            $gifts_table,
            array('id' => $gift_id),
            array('%d')
        );

        if ($result === false) {
            wp_send_json_error(__('Failed to delete gift. Please try again.', 'my-gift-registry'));
            return;
        }

        // Return success response
        wp_send_json_success(array(
            'message' => sprintf(__('Gift "%s" deleted successfully!', 'my-gift-registry'), $gift_check->title)
        ));
    }
}