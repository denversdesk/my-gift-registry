<?php
/**
 * Admin handler for My Gift Registry plugin
 *
 * Handles admin dashboard functionality for recommended products
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class My_Gift_Registry_Admin_Handler {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // AJAX handlers for admin
        add_action('wp_ajax_mgr_search_products', array($this, 'ajax_search_products'));
        add_action('wp_ajax_mgr_save_recommended', array($this, 'ajax_save_recommended'));
        add_action('wp_ajax_mgr_get_recommended', array($this, 'ajax_get_recommended'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Gift Registry', 'my-gift-registry'),
            __('Gift Registry', 'my-gift-registry'),
            'manage_options',
            'my-gift-registry',
            array($this, 'admin_page'),
            'dashicons-gifts',
            26
        );

        add_submenu_page(
            'my-gift-registry',
            __('Recommended Products', 'my-gift-registry'),
            __('Recommended Products', 'my-gift-registry'),
            'manage_options',
            'my-gift-registry-recommended',
            array($this, 'recommended_products_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our plugin pages
        if (!in_array($hook, array('toplevel_page_my-gift-registry', 'gift-registry_page_my-gift-registry-recommended'))) {
            return;
        }

        wp_enqueue_style(
            'mgr-admin-style',
            MY_GIFT_REGISTRY_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            MY_GIFT_REGISTRY_VERSION
        );

        wp_enqueue_script(
            'mgr-admin-script',
            MY_GIFT_REGISTRY_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            MY_GIFT_REGISTRY_VERSION,
            true
        );

        wp_localize_script('mgr-admin-script', 'mgrAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mgr_admin_nonce'),
            'strings' => array(
                'search_placeholder' => __('Search WooCommerce products...', 'my-gift-registry'),
                'select_product' => __('Select this product', 'my-gift-registry'),
                'max_products' => __('Maximum 6 products allowed', 'my-gift-registry'),
                'saving' => __('Saving...', 'my-gift-registry'),
                'saved' => __('Saved!', 'my-gift-registry'),
                'save_error' => __('Error saving products', 'my-gift-registry'),
                'confirm_delete' => __('Remove this product?', 'my-gift-registry'),
                'no_products_found' => __('No products found', 'my-gift-registry')
            )
        ));
    }

    /**
     * Main admin page
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Gift Registry Dashboard', 'my-gift-registry'); ?></h1>

            <div class="mgr-tabs">
                <nav class="nav-tab-wrapper">
                    <a href="?page=my-gift-registry" class="nav-tab nav-tab-active">
                        <?php _e('Dashboard', 'my-gift-registry'); ?>
                    </a>
                    <a href="?page=my-gift-registry-recommended" class="nav-tab">
                        <?php _e('Recommended Products', 'my-gift-registry'); ?>
                    </a>
                </nav>

                <div class="mgr-dashboard-content">
                    <div class="mgr-stats-grid">
                        <?php $this->render_statistics(); ?>
                    </div>

                    <div class="mgr-recent-activity">
                        <h3><?php _e('Recent Activity', 'my-gift-registry'); ?></h3>
                        <?php $this->render_recent_activity(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Recommended products admin page
     */
    public function recommended_products_page() {
        $db_handler = new My_Gift_Registry_DB_Handler();
        $recommended_data = $db_handler->get_all_recommended_products();

        // Available event types
        $event_types = array(
            'wedding' => __('Wedding', 'my-gift-registry'),
            'birthday' => __('Birthday', 'my-gift-registry'),
            'anniversary' => __('Anniversary', 'my-gift-registry'),
            'graduation' => __('Graduation', 'my-gift-registry'),
            'housewarming' => __('Housewarming', 'my-gift-registry'),
            'retirement' => __('Retirement', 'my-gift-registry'),
        );

        ?>
        <div class="wrap">
            <h1><?php _e('Recommended Products by Event Type', 'my-gift-registry'); ?></h1>

            <div class="mgr-tabs">
                <nav class="nav-tab-wrapper">
                    <a href="?page=my-gift-registry" class="nav-tab">
                        <?php _e('Dashboard', 'my-gift-registry'); ?>
                    </a>
                    <a href="?page=my-gift-registry-recommended" class="nav-tab nav-tab-active">
                        <?php _e('Recommended Products', 'my-gift-registry'); ?>
                    </a>
                </nav>

                <div class="mgr-recommended-content">
                    <?php if (!class_exists('WooCommerce')): ?>
                        <div class="notice notice-error">
                            <p><?php _e('WooCommerce is required for product search functionality.', 'my-gift-registry'); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="mgr-event-type-selector">
                        <label for="event-type-select"><?php _e('Select Event Type:', 'my-gift-registry'); ?></label>
                        <select id="event-type-select" class="mgr-event-select">
                            <?php foreach ($event_types as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>">
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mgr-product-management">
                        <!-- Product Search Section -->
                        <div class="mgr-product-search-section">
                            <div class="mgr-search-container">
                                <input type="text"
                                       id="mgr-product-search"
                                       placeholder="<?php _e('Search WooCommerce products...', 'my-gift-registry'); ?>"
                                       class="mgr-search-input">
                                <div id="mgr-search-results" class="mgr-search-results" style="display: none;"></div>
                            </div>
                        </div>

                        <!-- Selected Products Grid -->
                        <div class="mgr-selected-products">
                            <h3><?php _e('Selected Products (Max 6)', 'my-gift-registry'); ?></h3>
                            <div id="mgr-selected-grid" class="mgr-products-grid">
                                <!-- Selected products will be loaded here via JavaScript -->
                            </div>

                            <!-- Save Button -->
                            <div class="mgr-save-section">
                                <button type="button" id="mgr-save-recommended" class="button button-primary button-large">
                                    <?php _e('Save Recommended Products', 'my-gift-registry'); ?>
                                </button>
                                <span id="mgr-save-status"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Current Recommendations Summary -->
                    <div class="mgr-current-recommendations" style="margin-top: 30px;">
                        <h3><?php _e('Current Recommendations Summary', 'my-gift-registry'); ?></h3>
                        <div class="mgr-recommendations-list">
                            <?php foreach ($event_types as $key => $label): ?>
                                <div class="mgr-recommendation-item" data-event-type="<?php echo esc_attr($key); ?>">
                                    <strong><?php echo esc_html($label); ?>:</strong>
                                    <span class="mgr-product-count">
                                        <?php
                                        $product_ids = isset($recommended_data[$key]['product_ids']) ? $recommended_data[$key]['product_ids'] : array();
                                        echo count($product_ids) . ' ' . __('products', 'my-gift-registry');
                                        ?>
                                    </span>
                                    <?php if (!empty($recommended_data[$key]['updated_at'])): ?>
                                        <small class="mgr-updated-date">
                                            (<?php echo __('Last updated:', 'my-gift-registry') . ' ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($recommended_data[$key]['updated_at'])); ?>)
                                        </small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hidden template for products -->
        <script type="text/html" id="mgr-product-template">
            <div class="mgr-product-card" data-product-id="{{product_id}}">
                <input type="hidden" name="mgr_selected_products[]" value="{{product_id}}">
                <div class="mgr-product-remove">×</div>
                <div class="mgr-product-image">
                    <img src="{{image_url}}" alt="{{title}}" onerror="this.src=''">
                </div>
                <div class="mgr-product-info">
                    <h4 class="mgr-product-title">{{title}}</h4>
                    <div class="mgr-product-price">{{price_html}}</div>
                </div>
            </div>
        </script>
        <?php
    }

    /**
     * Render statistics for dashboard
     */
    private function render_statistics() {
        $db_handler = new My_Gift_Registry_DB_Handler();
        $debug = $db_handler->debug_database_status();

        $stats = array(
            array(
                'title' => __('Total Wishlists', 'my-gift-registry'),
                'value' => $debug['wishlist_count'],
                'icon' => 'dashicons-heart'
            ),
            array(
                'title' => __('Total Gifts', 'my-gift-registry'),
                'value' => $debug['gifts_count'],
                'icon' => 'dashicons-gifts'
            ),
            array(
                'title' => __('Total Reservations', 'my-gift-registry'),
                'value' => $debug['reservations_count'],
                'icon' => 'dashicons-cart'
            )
        );

        foreach ($stats as $stat) {
            ?>
            <div class="mgr-stat-card">
                <div class="mgr-stat-icon">
                    <span class="dashicons <?php echo esc_attr($stat['icon']); ?>"></span>
                </div>
                <div class="mgr-stat-content">
                    <h3><?php echo esc_html($stat['title']); ?></h3>
                    <p class="mgr-stat-value"><?php echo esc_html($stat['value']); ?></p>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Render recent activity
     */
    private function render_recent_activity() {
        // This would show recent reservations, new wishlists, etc.
        echo '<p>' . __('Recent activity will be displayed here.', 'my-gift-registry') . '</p>';
    }

    /**
     * AJAX handler for product search
     */
    public function ajax_search_products() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'mgr_admin_nonce')) {
            wp_send_json_error(__('Security check failed.', 'my-gift-registry'));
            return;
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'my-gift-registry'));
            return;
        }

        $search_term = sanitize_text_field($_POST['search_term']);

        if (empty($search_term)) {
            wp_send_json_error(__('Search term is required.', 'my-gift-registry'));
            return;
        }

        $db_handler = new My_Gift_Registry_DB_Handler();
        $products = $db_handler->search_woocommerce_products($search_term, 10); // Limit to 10 results

        wp_send_json_success(array(
            'products' => $products
        ));
    }

    /**
     * AJAX handler for saving recommended products
     */
    public function ajax_save_recommended() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'mgr_admin_nonce')) {
            wp_send_json_error(__('Security check failed.', 'my-gift-registry'));
            return;
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'my-gift-registry'));
            return;
        }

        $event_type = sanitize_text_field($_POST['event_type']);
        $product_ids = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : array();

        if (empty($event_type)) {
            wp_send_json_error(__('Event type is required.', 'my-gift-registry'));
            return;
        }

        // Validate product count
        if (count($product_ids) > 6) {
            wp_send_json_error(__('Maximum 6 products allowed per event type.', 'my-gift-registry'));
            return;
        }

        $db_handler = new My_Gift_Registry_DB_Handler();
        $result = $db_handler->save_recommended_products($event_type, $product_ids);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => __('Recommended products saved successfully!', 'my-gift-registry')
            ));
        }
    }

    /**
     * AJAX handler for getting recommended products
     */
    public function ajax_get_recommended() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'mgr_admin_nonce')) {
            wp_send_json_error(__('Security check failed.', 'my-gift-registry'));
            return;
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'my-gift-registry'));
            return;
        }

        $event_type = sanitize_text_field($_POST['event_type']);

        if (empty($event_type)) {
            wp_send_json_error(__('Event type is required.', 'my-gift-registry'));
            return;
        }

        $db_handler = new My_Gift_Registry_DB_Handler();

        // Get recommended product IDs
        $product_ids = $db_handler->get_recommended_products($event_type);

        if (empty($product_ids)) {
            wp_send_json_success(array(
                'products' => array()
            ));
            return;
        }

        // Get detailed product information
        $products = $db_handler->get_recommended_products_details($product_ids);

        wp_send_json_success(array(
            'products' => $products
        ));
    }
}