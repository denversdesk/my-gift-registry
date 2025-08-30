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

        // Event types AJAX handlers
        add_action('wp_ajax_mgr_add_event_type', array($this, 'ajax_add_event_type'));
        add_action('wp_ajax_mgr_update_event_type', array($this, 'ajax_update_event_type'));
        add_action('wp_ajax_mgr_delete_event_type', array($this, 'ajax_delete_event_type'));
        add_action('wp_ajax_mgr_get_event_types', array($this, 'ajax_get_event_types'));
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

        // Event Types Management submenu
        add_submenu_page(
            'my-gift-registry',
            __('Event Types', 'my-gift-registry'),
            __('Event Types', 'my-gift-registry'),
            'manage_options',
            'my-gift-registry-event-types',
            array($this, 'event_types_page')
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
        if (!in_array($hook, array('toplevel_page_my-gift-registry', 'gift-registry_page_my-gift-registry-event-types', 'gift-registry_page_my-gift-registry-recommended'))) {
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
                    <a href="?page=my-gift-registry-event-types" class="nav-tab">
                        <?php _e('Event Types', 'my-gift-registry'); ?>
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

        // Get available event types from database
        $active_event_types = $db_handler->get_active_event_types();
        $event_types = array();
        foreach ($active_event_types as $event_type) {
            $event_types[$event_type->slug] = $event_type->name;
        }

        // Ensure some default fallback if no event types exist
        if (empty($event_types)) {
            $event_types = array(
                'wedding' => __('Wedding', 'my-gift-registry'),
                'birthday' => __('Birthday', 'my-gift-registry'),
            );
        }

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

    /**
     * Event Types Management Admin Page
     */
    public function event_types_page() {
        $db_handler = new My_Gift_Registry_DB_Handler();
        $event_types = $db_handler->get_all_event_types();

        ?>
        <div class="wrap">
            <h1><?php _e('Event Types Management', 'my-gift-registry'); ?></h1>

            <div class="mgr-event-types-container">
                <!-- Add New Event Type Form -->
                <div class="mgr-add-event-type">
                    <h2><?php _e('Add New Event Type', 'my-gift-registry'); ?></h2>

                    <form id="mgr-add-event-type-form">
                        <?php wp_nonce_field('mgr_event_type_nonce', 'mgr_event_type_nonce'); ?>

                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="event_type_slug"><?php _e('Slug', 'my-gift-registry'); ?> *</label></th>
                                <td>
                                    <input type="text" id="event_type_slug" name="slug" class="regular-text" required>
                                    <p class="description"><?php _e('Unique identifier (lowercase, no spaces)', 'my-gift-registry'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="event_type_name"><?php _e('Name', 'my-gift-registry'); ?> *</label></th>
                                <td>
                                    <input type="text" id="event_type_name" name="name" class="regular-text" required>
                                    <p class="description"><?php _e('Display name', 'my-gift-registry'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="event_type_description"><?php _e('Description', 'my-gift-registry'); ?></label></th>
                                <td>
                                    <textarea id="event_type_description" name="description" rows="3" class="large-text"></textarea>
                                    <p class="description"><?php _e('Optional description (for internal use)', 'my-gift-registry'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="event_type_sort_order"><?php _e('Sort Order', 'my-gift-registry'); ?></label></th>
                                <td>
                                    <input type="number" id="event_type_sort_order" name="sort_order" value="0" min="0">
                                    <p class="description"><?php _e('Order in which event types are displayed (lower numbers first)', 'my-gift-registry'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php _e('Add Event Type', 'my-gift-registry'); ?></button>
                            <span id="mgr-add-event-type-status"></span>
                        </p>
                    </form>
                </div>

                <!-- Existing Event Types List -->
                <div class="mgr-event-types-list">
                    <h2><?php _e('Existing Event Types', 'my-gift-registry'); ?></h2>

                    <div id="mgr-event-types-table-container">
                        <table class="wp-list-table widefat fixed striped" id="mgr-event-types-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Name', 'my-gift-registry'); ?></th>
                                    <th><?php _e('Slug', 'my-gift-registry'); ?></th>
                                    <th><?php _e('Description', 'my-gift-registry'); ?></th>
                                    <th><?php _e('Sort Order', 'my-gift-registry'); ?></th>
                                    <th><?php _e('Status', 'my-gift-registry'); ?></th>
                                    <th><?php _e('Actions', 'my-gift-registry'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="mgr-event-types-tbody">
                                <?php foreach ($event_types as $event_type): ?>
                                    <tr data-id="<?php echo esc_attr($event_type->id); ?>">
                                        <td class="column-name">
                                            <strong><?php echo esc_html($event_type->name); ?></strong>
                                        </td>
                                        <td class="column-slug"><?php echo esc_html($event_type->slug); ?></td>
                                        <td class="column-description"><?php echo esc_html($event_type->description); ?></td>
                                        <td class="column-sort-order"><?php echo esc_html($event_type->sort_order); ?></td>
                                        <td class="column-status">
                                            <span class="event-type-status status-<?php echo $event_type->is_active ? 'active' : 'inactive'; ?>">
                                                <?php echo $event_type->is_active ? __('Active', 'my-gift-registry') : __('Inactive', 'my-gift-registry'); ?>
                                            </span>
                                        </td>
                                        <td class="column-actions">
                                            <button type="button" class="button button-small mgr-edit-event-type" data-id="<?php echo esc_attr($event_type->id); ?>"><?php _e('Edit', 'my-gift-registry'); ?></button>
                                            <?php if ($event_type->is_active): ?>
                                                <button type="button" class="button button-small mg-rdelete-event-type" data-id="<?php echo esc_attr($event_type->id); ?>"><?php _e('Deactivate', 'my-gift-registry'); ?></button>
                                            <?php else: ?>
                                                <button type="button" class="button button-small button-primary mgr-activate-event-type" data-id="<?php echo esc_attr($event_type->id); ?>"><?php _e('Activate', 'my-gift-registry'); ?></button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Event Type Modal -->
        <div id="mgr-edit-event-type-modal" class="mgr-modal" style="display: none;">
            <div class="mgr-modal-overlay"></div>
            <div class="mgr-modal-content">
                <div class="mgr-modal-header">
                    <h3><?php _e('Edit Event Type', 'my-gift-registry'); ?></h3>
                    <button type="button" class="mgr-modal-close">&times;</button>
                </div>

                <form id="mgr-edit-event-type-form">
                    <input type="hidden" id="edit_event_type_id" name="id">

                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="edit_event_type_slug"><?php _e('Slug', 'my-gift-registry'); ?> *</label></th>
                            <td>
                                <input type="text" id="edit_event_type_slug" name="slug" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edit_event_type_name"><?php _e('Name', 'my-gift-registry'); ?> *</label></th>
                            <td>
                                <input type="text" id="edit_event_type_name" name="name" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edit_event_type_description"><?php _e('Description', 'my-gift-registry'); ?></label></th>
                            <td>
                                <textarea id="edit_event_type_description" name="description" rows="3" class="large-text"></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="edit_event_type_sort_order"><?php _e('Sort Order', 'my-gift-registry'); ?></label></th>
                            <td>
                                <input type="number" id="edit_event_type_sort_order" name="sort_order" value="0" min="0">
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php _e('Update Event Type', 'my-gift-registry'); ?></button>
                        <button type="button" class="button mgr-modal-close"><?php _e('Cancel', 'my-gift-registry'); ?></button>
                        <span id="mgr-edit-event-type-status"></span>
                    </p>
                </form>
            </div>
        </div>

        <script type="text/javascript">
            // JavaScript for event types management will be added here
        </script>
        <?php
    }

    /**
     * AJAX handler for adding event type
     */
    public function ajax_add_event_type() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['mgr_event_type_nonce'], 'mgr_event_type_nonce')) {
            wp_send_json_error(__('Security check failed.', 'my-gift-registry'));
            return;
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'my-gift-registry'));
            return;
        }

        $db_handler = new My_Gift_Registry_DB_Handler();
        $result = $db_handler->add_event_type($_POST);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => __('Event type added successfully!', 'my-gift-registry'),
                'id' => $result
            ));
        }
    }

    /**
     * AJAX handler for updating event type
     */
    public function ajax_update_event_type() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['mgr_event_type_nonce'], 'mgr_event_type_nonce')) {
            wp_send_json_error(__('Security check failed.', 'my-gift-registry'));
            return;
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'my-gift-registry'));
            return;
        }

        $db_handler = new My_Gift_Registry_DB_Handler();
        $id = intval($_POST['id']);
        $result = $db_handler->update_event_type($id, $_POST);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => __('Event type updated successfully!', 'my-gift-registry')
            ));
        }
    }

    /**
     * AJAX handler for deleting event type
     */
    public function ajax_delete_event_type() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['mgr_event_type_nonce'], 'mgr_event_type_nonce')) {
            wp_send_json_error(__('Security check failed.', 'my-gift-registry'));
            return;
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'my-gift-registry'));
            return;
        }

        $db_handler = new My_Gift_Registry_DB_Handler();
        $id = intval($_POST['id']);
        $result = $db_handler->delete_event_type($id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => __('Event type deactivated successfully!', 'my-gift-registry')
            ));
        }
    }

    /**
     * AJAX handler for getting event types
     */
    public function ajax_get_event_types() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['mgr_event_type_nonce'], 'mgr_event_type_nonce')) {
            wp_send_json_error(__('Security check failed.', 'my-gift-registry'));
            return;
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'my-gift-registry'));
            return;
        }

        $db_handler = new My_Gift_Registry_DB_Handler();
        $event_types = $db_handler->get_active_event_types();

        wp_send_json_success(array('event_types' => $event_types));
    }
}