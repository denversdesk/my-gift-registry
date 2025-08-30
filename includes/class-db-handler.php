<?php
/**
 * Database handler for My Gift Registry plugin
 *
 * Handles custom database tables for wishlists and reservations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class My_Gift_Registry_DB_Handler {

    /**
     * Wishlist table name
     */
    private $wishlist_table;

    /**
     * Reservations table name
     */
    private $reservations_table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wishlist_table = $wpdb->prefix . 'my_gift_registry_wishlists';
        $this->reservations_table = $wpdb->prefix . 'my_gift_registry_reservations';
    }

    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Wishlist table
        $wishlist_sql = "CREATE TABLE {$this->wishlist_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            slug varchar(255) NOT NULL,
            title varchar(255) NOT NULL,
            event_type varchar(50) DEFAULT NULL,
            event_date date DEFAULT NULL,
            description text,
            full_name varchar(255) DEFAULT NULL,
            profile_pic varchar(500) DEFAULT NULL,
            user_id int(11) DEFAULT NULL,
            user_email varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY user_id (user_id),
            KEY event_type (event_type),
            KEY event_date (event_date)
        ) $charset_collate;";

        // Gift items table (related to wishlists)
        $gifts_table = $wpdb->prefix . 'my_gift_registry_gifts';
        $gifts_sql = "CREATE TABLE {$gifts_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            wishlist_id int(11) NOT NULL,
            title varchar(255) NOT NULL,
            description text,
            image_url varchar(500),
            product_url varchar(500),
            price decimal(10,2),
            priority int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY wishlist_id (wishlist_id)
        ) $charset_collate;";

        // Recommended products table
        $recommended_products_table = $wpdb->prefix . 'my_gift_registry_recommended_products';
        $recommended_sql = "CREATE TABLE {$recommended_products_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            product_ids text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY event_type (event_type)
        ) $charset_collate;";

        // Reservations table
        $reservations_sql = "CREATE TABLE {$this->reservations_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            gift_id int(11) NOT NULL,
            email varchar(255) NOT NULL,
            reserved_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY gift_id (gift_id),
            KEY email (email)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Create tables one by one with error checking
        $result1 = dbDelta($wishlist_sql);
        error_log('My Gift Registry: Wishlist table creation result: ' . print_r($result1, true));

        $result2 = dbDelta($gifts_sql);
        error_log('My Gift Registry: Gifts table creation result: ' . print_r($result2, true));

        $result3 = dbDelta($recommended_sql);
        error_log('My Gift Registry: Recommended products table creation result: ' . print_r($result3, true));

        $result4 = dbDelta($reservations_sql);
        error_log('My Gift Registry: Reservations table creation result: ' . print_r($result4, true));

        // Check if tables exist after creation
        $wishlist_exists = $this->table_exists($this->wishlist_table);
        $gifts_exists = $this->table_exists($gifts_table);
        $recommended_exists = $this->table_exists($recommended_products_table);
        $reservations_exists = $this->table_exists($this->reservations_table);

        error_log('My Gift Registry: Table existence after creation - Wishlist: ' . ($wishlist_exists ? 'Yes' : 'No') . ', Gifts: ' . ($gifts_exists ? 'Yes' : 'No') . ', Recommended: ' . ($recommended_exists ? 'Yes' : 'No') . ', Reservations: ' . ($reservations_exists ? 'Yes' : 'No'));

        // Insert sample data for testing only if wishlist table exists
        if ($wishlist_exists) {
            $this->insert_sample_data();
        } else {
            error_log('My Gift Registry: Skipping sample data insertion because wishlist table does not exist');
        }
    }

    /**
     * Insert sample data for testing
     */
    private function insert_sample_data() {
        global $wpdb;

        // Check if sample data already exists
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM {$this->wishlist_table}");
        if ($existing > 0) {
            return;
        }

        // Sample wishlist
        $result = $wpdb->insert(
            $this->wishlist_table,
            array(
                'slug' => 'jessicas-bridal-shower',
                'title' => 'Jessica\'s Bridal Shower',
                'description' => 'Help Jessica start her new life with these amazing gifts!',
                'user_email' => 'jessica@example.com'
            )
        );

        if ($result === false) {
            error_log('My Gift Registry: Failed to insert sample wishlist. Error: ' . $wpdb->last_error);
            return;
        }

        $wishlist_id = $wpdb->insert_id;

        if (!$wishlist_id) {
            error_log('My Gift Registry: No wishlist ID returned after insert');
            return;
        }

        // Sample gifts
        $sample_gifts = array(
            array(
                'title' => 'KitchenAid Stand Mixer',
                'description' => 'Professional 5-quart stand mixer in beautiful silver',
                'image_url' => 'https://example.com/images/kitchenaid-mixer.jpg',
                'product_url' => 'https://example.com/products/kitchenaid-stand-mixer',
                'price' => 349.99,
                'priority' => 1
            ),
            array(
                'title' => 'Le Creuset Dutch Oven',
                'description' => 'Beautiful cherry red 7.25-quart Dutch oven, perfect for cooking',
                'image_url' => 'https://example.com/images/le-creuset-dutch-oven.jpg',
                'product_url' => 'https://example.com/products/le-creuset-dutch-oven',
                'price' => 299.99,
                'priority' => 2
            ),
            array(
                'title' => 'Nespresso Coffee Machine',
                'description' => 'Elegant coffee maker with milk frother',
                'image_url' => 'https://example.com/images/nespresso-machine.jpg',
                'product_url' => 'https://example.com/products/nespresso-coffee-machine',
                'price' => 199.99,
                'priority' => 3
            )
        );

        $gifts_table = $wpdb->prefix . 'my_gift_registry_gifts';

        foreach ($sample_gifts as $gift) {
            $gift_data = array_merge($gift, array('wishlist_id' => $wishlist_id));
            $result = $wpdb->insert($gifts_table, $gift_data);

            if ($result === false) {
                error_log('My Gift Registry: Failed to insert sample gift "' . $gift['title'] . '". Error: ' . $wpdb->last_error);
            }
        }
    }

    /**
     * Ensure sample data exists (public method for manual creation)
     */
    public function ensure_sample_data() {
        $this->create_tables();
    }

    /**
     * Manually create wishlist table (for debugging)
     */
    public function create_wishlist_table_manually() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->wishlist_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            slug varchar(255) NOT NULL,
            title varchar(255) NOT NULL,
            event_type varchar(50) DEFAULT NULL,
            event_date date DEFAULT NULL,
            description text,
            full_name varchar(255) DEFAULT NULL,
            profile_pic varchar(500) DEFAULT NULL,
            user_id int(11) DEFAULT NULL,
            user_email varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY user_id (user_id),
            KEY event_type (event_type),
            KEY event_date (event_date)
        ) $charset_collate;";

        $result = $wpdb->query($sql);
        error_log('My Gift Registry: Manual wishlist table creation result: ' . ($result === false ? 'Failed - ' . $wpdb->last_error : 'Success'));

        return $result !== false;
    }

    /**
     * Debug method to check database status
     */
    public function debug_database_status() {
        global $wpdb;

        $gifts_table = $wpdb->prefix . 'my_gift_registry_gifts';

        $debug = array(
            'wishlist_table_exists' => $this->table_exists($this->wishlist_table),
            'gifts_table_exists' => $this->table_exists($gifts_table),
            'reservations_table_exists' => $this->table_exists($this->reservations_table),
            'wishlist_count' => $this->table_exists($this->wishlist_table) ? $wpdb->get_var("SELECT COUNT(*) FROM {$this->wishlist_table}") : 0,
            'gifts_count' => $this->table_exists($gifts_table) ? $wpdb->get_var("SELECT COUNT(*) FROM {$gifts_table}") : 0,
            'reservations_count' => $this->table_exists($this->reservations_table) ? $wpdb->get_var("SELECT COUNT(*) FROM {$this->reservations_table}") : 0,
        );

        return $debug;
    }

    /**
     * Check if table exists
     */
    private function table_exists($table_name) {
        global $wpdb;
        $result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        return !empty($result);
    }

    /**
     * Check if slug is unique
     *
     * @param string $slug
     * @param int $exclude_id
     * @return bool
     */
    public function is_slug_unique($slug, $exclude_id = 0) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wishlist_table} WHERE slug = %s",
            $slug
        );

        if ($exclude_id > 0) {
            $query = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->wishlist_table} WHERE slug = %s AND id != %d",
                $slug,
                $exclude_id
            );
        }

        $count = $wpdb->get_var($query);
        return $count == 0;
    }

    /**
     * Generate unique slug
     *
     * @param string $base_slug
     * @param int $exclude_id
     * @return string
     */
    public function generate_unique_slug($base_slug, $exclude_id = 0) {
        $slug = sanitize_title($base_slug);
        $original_slug = $slug;
        $counter = 1;

        while (!$this->is_slug_unique($slug, $exclude_id)) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Create new wishlist
     *
     * @param array $wishlist_data
     * @return int|WP_Error
     */
    public function create_wishlist($wishlist_data) {
        global $wpdb;

        // Validate required fields
        if (empty($wishlist_data['title']) || empty($wishlist_data['slug'])) {
            return new WP_Error('missing_fields', __('Title and slug are required.', 'my-gift-registry'));
        }

        // Check user authentication
        if (empty($wishlist_data['user_id'])) {
            return new WP_Error('no_user', __('User authentication required.', 'my-gift-registry'));
        }

        // Ensure slug is unique
        $wishlist_data['slug'] = $this->generate_unique_slug($wishlist_data['slug']);

        // Prepare data for insertion
        $insert_data = array(
            'title' => wp_kses($wishlist_data['title'], array()), // Allow basic text without HTML
            'slug' => $wishlist_data['slug'],
            'event_type' => isset($wishlist_data['event_type']) ? sanitize_text_field($wishlist_data['event_type']) : null,
            'event_date' => isset($wishlist_data['event_date']) ? sanitize_text_field($wishlist_data['event_date']) : null,
            'description' => isset($wishlist_data['description']) ? sanitize_textarea_field($wishlist_data['description']) : null,
            'full_name' => isset($wishlist_data['full_name']) ? sanitize_text_field($wishlist_data['full_name']) : null,
            'profile_pic' => isset($wishlist_data['profile_pic']) ? esc_url_raw($wishlist_data['profile_pic']) : null,
            'user_id' => intval($wishlist_data['user_id']),
            'user_email' => isset($wishlist_data['user_email']) ? sanitize_email($wishlist_data['user_email']) : null,
        );

        // Insert wishlist
        $result = $wpdb->insert(
            $this->wishlist_table,
            $insert_data,
            array(
                    '%s', // title
                    '%s', // slug
                    '%s', // event_type
                    '%s', // event_date
                    '%s', // description
                    '%s', // full_name
                    '%s', // profile_pic
                    '%d', // user_id
                    '%s', // user_email
                )
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create wishlist.', 'my-gift-registry'));
        }

        $wishlist_id = $wpdb->insert_id;

        return $wishlist_id;
    }

    /**
     * Get wishlists by user ID
     *
     * @param int $user_id
     * @return array
     */
    public function get_user_wishlists($user_id) {
        global $wpdb;

        $wishlists = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->wishlist_table} WHERE user_id = %d ORDER BY created_at DESC",
                $user_id
            )
        );

        return $wishlists;
    }

    /**
     * Get wishlist by ID and user ID (for security - ensure user owns the wishlist)
     *
     * @param int $wishlist_id
     * @param int $user_id
     * @return object|null
     */
    public function get_user_wishlist($wishlist_id, $user_id) {
        global $wpdb;

        $wishlist = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->wishlist_table} WHERE id = %d AND user_id = %d",
                $wishlist_id,
                $user_id
            )
        );

        return $wishlist;
    }

    /**
     * Update wishlist
     *
     * @param int $wishlist_id
     * @param int $user_id
     * @param array $wishlist_data
     * @return bool|WP_Error
     */
    public function update_wishlist($wishlist_id, $user_id, $wishlist_data) {
        global $wpdb;

        // Verify ownership
        $existing_wishlist = $this->get_user_wishlist($wishlist_id, $user_id);
        if (!$existing_wishlist) {
            return new WP_Error('not_found', __('Wishlist not found or access denied.', 'my-gift-registry'));
        }

        // Validate required fields
        if (empty($wishlist_data['title'])) {
            return new WP_Error('missing_fields', __('Title is required.', 'my-gift-registry'));
        }

        // Ensure slug is unique (excluding current wishlist)
        $wishlist_data['slug'] = $this->generate_unique_slug($wishlist_data['slug'], $wishlist_id);

        // Prepare data for update
        $update_data = array(
            'title' => wp_kses($wishlist_data['title'], array()),
            'slug' => $wishlist_data['slug'],
            'event_type' => isset($wishlist_data['event_type']) ? sanitize_text_field($wishlist_data['event_type']) : null,
            'event_date' => isset($wishlist_data['event_date']) ? sanitize_text_field($wishlist_data['event_date']) : null,
            'description' => isset($wishlist_data['description']) ? sanitize_textarea_field($wishlist_data['description']) : null,
            'full_name' => isset($wishlist_data['full_name']) ? sanitize_text_field($wishlist_data['full_name']) : null,
            'profile_pic' => isset($wishlist_data['profile_pic']) ? esc_url_raw($wishlist_data['profile_pic']) : null,
            'user_email' => isset($wishlist_data['user_email']) ? sanitize_email($wishlist_data['user_email']) : null,
            'updated_at' => current_time('mysql')
        );

        // Define the correct format specifiers for the update data
        $update_formats = array(
            '%s', // title
            '%s', // slug
            '%s', // event_type
            '%s', // event_date
            '%s', // description
            '%s', // full_name
            '%s', // profile_pic
            '%s', // user_email
            '%s'  // updated_at
        );

        // Define the correct format specifiers for the WHERE clause
        $where_formats = array(
            '%d', // id
            '%d'  // user_id
        );

        // Update wishlist
        $result = $wpdb->update(
            $this->wishlist_table,
            $update_data,
            array('id' => $wishlist_id, 'user_id' => $user_id),
            $update_formats,
            $where_formats
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update wishlist.', 'my-gift-registry'));
        }

        return true;
    }

    /**
     * Delete wishlist
     *
     * @param int $wishlist_id
     * @param int $user_id
     * @return bool|WP_Error
     */
    public function delete_wishlist($wishlist_id, $user_id) {
        global $wpdb;

        // Verify ownership
        $wishlist = $this->get_user_wishlist($wishlist_id, $user_id);
        if (!$wishlist) {
            return new WP_Error('not_found', __('Wishlist not found or access denied.', 'my-gift-registry'));
        }

        // Delete wishlist
        $result = $wpdb->delete(
            $this->wishlist_table,
            array('id' => $wishlist_id, 'user_id' => $user_id),
            array('%d', '%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to delete wishlist.', 'my-gift-registry'));
        }

        return true;
    }

    /**
     * Get wishlist by slug
     *
     * @param string $slug
     * @return object|null
     */
    public function get_wishlist_by_slug($slug) {
        global $wpdb;

        $wishlist = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->wishlist_table} WHERE slug = %s",
                $slug
            )
        );

        if ($wishlist) {
            // Get associated gifts
            $wishlist->gifts = $this->get_wishlist_gifts($wishlist->id);
        }

        return $wishlist;
    }

    /**
     * Get wishlist gifts
     *
     * @param int $wishlist_id
     * @return array
     */
    public function get_wishlist_gifts($wishlist_id) {
        global $wpdb;

        $gifts_table = $wpdb->prefix . 'my_gift_registry_gifts';

        $gifts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT g.*, CASE WHEN r.id IS NOT NULL THEN 1 ELSE 0 END as is_reserved
                 FROM {$gifts_table} g
                 LEFT JOIN {$this->reservations_table} r ON g.id = r.gift_id
                 WHERE g.wishlist_id = %d
                 ORDER BY g.priority ASC, g.created_at ASC",
                $wishlist_id
            )
        );

        return $gifts;
    }

    /**
     * Reserve gift
     *
     * @param int $gift_id
     * @param string $email
     * @return bool
     */
    public function reserve_gift($gift_id, $email) {
        global $wpdb;

        $result = $wpdb->insert(
            $this->reservations_table,
            array(
                'gift_id' => $gift_id,
                'email' => $email
            ),
            array('%d', '%s')
        );

        return $result !== false;
    }

    /**
     * Get gift with wishlist
     *
     * @param int $gift_id
     * @return object|null
     */
    public function get_gift_with_wishlist($gift_id) {
        global $wpdb;

        $gifts_table = $wpdb->prefix . 'my_gift_registry_gifts';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT g.*, w.title as wishlist_title, w.slug as wishlist_slug
                 FROM {$gifts_table} g
                 JOIN {$this->wishlist_table} w ON g.wishlist_id = w.id
                 WHERE g.id = %d",
                $gift_id
            )
        );
    }

    /**
     * Get featured WooCommerce products
     *
     * @param int $limit
     * @return array
     */
    public function get_featured_woocommerce_products($limit = 4) {
        if (!class_exists('WooCommerce')) {
            return array();
        }

        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_query' => array(
                array(
                    'key' => '_featured',
                    'value' => 'yes'
                )
            )
        );

        $products = get_posts($args);

        $product_data = array();
        foreach ($products as $product) {
            $wc_product = wc_get_product($product->ID);
            $product_data[] = array(
                'id' => $product->ID,
                'title' => $product->post_title,
                'url' => get_permalink($product->ID),
                'price' => $wc_product->get_price_html(),
                'image_url' => get_the_post_thumbnail_url($product->ID, 'medium')
            );
        }

        return $product_data;
    }

    /**
     * Search WooCommerce products for autocomplete
     *
     * @param string $search_term
     * @param int $limit
     * @return array
     */
    public function search_woocommerce_products($search_term, $limit = 10) {
        if (!class_exists('WooCommerce')) {
            return array();
        }

        if (empty($search_term)) {
            return array();
        }

        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            's' => $search_term,
            'orderby' => 'title',
            'order' => 'ASC'
        );

        $products = get_posts($args);

        $product_data = array();
        foreach ($products as $product) {
            $wc_product = wc_get_product($product->ID);
            $product_data[] = array(
                'id' => $product->ID,
                'title' => $product->post_title,
                'description' => $product->post_content ? wp_trim_words($product->post_content, 20) : '',
                'url' => get_permalink($product->ID),
                'price' => $wc_product->get_price(),
                'price_html' => $wc_product->get_price_html(),
                'image_url' => get_the_post_thumbnail_url($product->ID, 'medium'),
                'sku' => $wc_product->get_sku()
            );
        }

        return $product_data;
    }

    /**
     * Add product to wishlist
     *
     * @param int $wishlist_id
     * @param array $product_data
     * @return int|WP_Error
     */
    public function add_product_to_wishlist($wishlist_id, $product_data) {
        global $wpdb;

        // Validate required fields
        if (empty($product_data['title'])) {
            return new WP_Error('missing_title', __('Product title is required.', 'my-gift-registry'));
        }

        if (empty($wishlist_id)) {
            return new WP_Error('missing_wishlist', __('Wishlist ID is required.', 'my-gift-registry'));
        }

        // Verify wishlist exists
        $wishlist = $this->get_wishlist_by_slug($wishlist_id) ?: $this->get_wishlist_by_id($wishlist_id);
        if (!$wishlist) {
            return new WP_Error('invalid_wishlist', __('Wishlist not found.', 'my-gift-registry'));
        }

        $gifts_table = $wpdb->prefix . 'my_gift_registry_gifts';

        // Prepare data for insertion
        $insert_data = array(
            'wishlist_id' => intval($wishlist_id),
            'title' => sanitize_text_field($product_data['title']),
            'description' => isset($product_data['description']) ? sanitize_textarea_field($product_data['description']) : null,
            'image_url' => isset($product_data['image_url']) ? esc_url_raw($product_data['image_url']) : null,
            'product_url' => isset($product_data['product_url']) ? esc_url_raw($product_data['product_url']) : null,
            'price' => isset($product_data['price']) ? floatval($product_data['price']) : null,
            'priority' => isset($product_data['priority']) ? intval($product_data['priority']) : 0,
        );

        // Insert product
        $result = $wpdb->insert(
            $gifts_table,
            $insert_data,
            array(
                '%d', // wishlist_id
                '%s', // title
                '%s', // description
                '%s', // image_url
                '%s', // product_url
                '%f', // price
                '%d'  // priority
            )
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to add product to wishlist.', 'my-gift-registry'));
        }

        $gift_id = $wpdb->insert_id;

        return $gift_id;
    }

    /**
     * Get wishlist by ID (alternative method)
     *
     * @param int $wishlist_id
     * @return object|null
     */
    private function get_wishlist_by_id($wishlist_id) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->wishlist_table} WHERE id = %d",
                $wishlist_id
            )
        );
    }

    /**
     * Save recommended products for an event type
     *
     * @param string $event_type
     * @param array $product_ids
     * @return bool|WP_Error
     */
    public function save_recommended_products($event_type, $product_ids) {
        global $wpdb;

        if (empty($event_type)) {
            return new WP_Error('missing_event_type', __('Event type is required.', 'my-gift-registry'));
        }

        if (count($product_ids) > 6) {
            return new WP_Error('too_many_products', __('Maximum 6 products allowed per event type.', 'my-gift-registry'));
        }

        // Validate product IDs exist (optional - could be performance intensive)
        if (class_exists('WooCommerce')) {
            foreach ($product_ids as $product_id) {
                $product = wc_get_product($product_id);
                if (!$product) {
                    return new WP_Error('invalid_product', __('Product ID ' . $product_id . ' does not exist.', 'my-gift-registry'));
                }
            }
        }

        $recommended_table = $wpdb->prefix . 'my_gift_registry_recommended_products';

        // Prepare data
        $data = array(
            'event_type' => sanitize_text_field($event_type),
            'product_ids' => wp_json_encode($product_ids),
            'updated_at' => current_time('mysql')
        );

        // Check if entry exists
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$recommended_table} WHERE event_type = %s",
                $event_type
            )
        );

        if ($existing) {
            // Update existing record
            $result = $wpdb->update(
                $recommended_table,
                $data,
                array('event_type' => $event_type),
                array('%s', '%s', '%s'),
                array('%s')
            );
        } else {
            // Insert new record
            $result = $wpdb->insert(
                $recommended_table,
                $data,
                array('%s', '%s', '%s')
            );
        }

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to save recommended products.', 'my-gift-registry'));
        }

        return true;
    }

    /**
     * Get recommended products for an event type
     *
     * @param string $event_type
     * @return array
     */
    public function get_recommended_products($event_type) {
        global $wpdb;

        if (empty($event_type)) {
            return array();
        }

        $recommended_table = $wpdb->prefix . 'my_gift_registry_recommended_products';

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT product_ids FROM {$recommended_table} WHERE event_type = %s",
                $event_type
            )
        );

        if (!$result) {
            return array();
        }

        $product_ids = json_decode($result, true);
        return is_array($product_ids) ? $product_ids : array();
    }

    /**
     * Get all recommended products data for admin interface
     *
     * @return array
     */
    public function get_all_recommended_products() {
        global $wpdb;

        $recommended_table = $wpdb->prefix . 'my_gift_registry_recommended_products';

        $results = $wpdb->get_results("SELECT * FROM {$recommended_table} ORDER BY event_type ASC");

        $data = array();
        foreach ($results as $row) {
            $product_ids = json_decode($row->product_ids, true);
            $data[$row->event_type] = array(
                'product_ids' => is_array($product_ids) ? $product_ids : array(),
                'updated_at' => $row->updated_at
            );
        }

        return $data;
    }

    /**
     * Get detailed product data for recommended products
     *
     * @param array $product_ids
     * @return array
     */
    public function get_recommended_products_details($product_ids) {
        if (!is_array($product_ids) || empty($product_ids) || !class_exists('WooCommerce')) {
            return array();
        }

        $products = array();
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $products[] = array(
                    'id' => $product_id,
                    'title' => $product->get_name(),
                    'price' => $product->get_price(),
                    'price_html' => $product->get_price_html(),
                    'image_url' => get_the_post_thumbnail_url($product_id, 'thumbnail'),
                    'permalink' => get_permalink($product_id)
                );
            }
        }

        return $products;
    }
}