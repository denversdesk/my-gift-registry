<?php
/**
 * Plugin Name: My Gift Registry
 * Plugin URI: https://yoursite.com/my-gift-registry
 * Description: A comprehensive gift registry plugin for WordPress with wishlist management and reservation system.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: my-gift-registry
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MY_GIFT_REGISTRY_VERSION', '1.0.0');
define('MY_GIFT_REGISTRY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MY_GIFT_REGISTRY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MY_GIFT_REGISTRY_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Main plugin class
class My_Gift_Registry {

    /**
     * Single instance of the plugin
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->includes();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Include required files
     */
    private function includes() {
        // Include core classes
        require_once MY_GIFT_REGISTRY_PLUGIN_DIR . 'includes/class-db-handler.php';
        require_once MY_GIFT_REGISTRY_PLUGIN_DIR . 'includes/class-rewrite-handler.php';
        require_once MY_GIFT_REGISTRY_PLUGIN_DIR . 'includes/class-shortcode-handler.php';
        require_once MY_GIFT_REGISTRY_PLUGIN_DIR . 'includes/class-create-wishlist-handler.php';
        require_once MY_GIFT_REGISTRY_PLUGIN_DIR . 'includes/class-my-wishlists-handler.php';
        require_once MY_GIFT_REGISTRY_PLUGIN_DIR . 'includes/class-ajax-handler.php';
        require_once MY_GIFT_REGISTRY_PLUGIN_DIR . 'includes/class-email-handler.php';
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $db_handler = new My_Gift_Registry_DB_Handler();
        $db_handler->create_tables();

        // Flush rewrite rules
        $rewrite_handler = new My_Gift_Registry_Rewrite_Handler();
        $rewrite_handler->register_rewrite_rules();
        flush_rewrite_rules();

        // Set default options
        add_option('my_gift_registry_version', MY_GIFT_REGISTRY_VERSION);

        // Force flush rewrite rules on next admin init
        set_transient('my_gift_registry_flush_rewrite_rules', true);
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Load text domain for internationalization
        load_plugin_textdomain('my-gift-registry', false, dirname(MY_GIFT_REGISTRY_PLUGIN_BASENAME) . '/languages/');

        // Initialize handlers
        new My_Gift_Registry_Rewrite_Handler();
        new My_Gift_Registry_Shortcode_Handler();
        new My_Gift_Registry_Create_Wishlist_Handler();
        new My_Gift_Registry_My_Wishlists_Handler();
        new My_Gift_Registry_Ajax_Handler();
    }

    /**
     * Admin initialization
     */
    public function admin_init() {
        // Check if we need to flush rewrite rules
        if (get_transient('my_gift_registry_flush_rewrite_rules')) {
            delete_transient('my_gift_registry_flush_rewrite_rules');
            flush_rewrite_rules();
        }

        // Check for database setup request
        if (isset($_GET['my_gift_registry_setup_db']) && current_user_can('manage_options')) {
            $db_handler = new My_Gift_Registry_DB_Handler();
            $db_handler->ensure_sample_data();

            // Redirect back to avoid re-triggering
            wp_redirect(remove_query_arg('my_gift_registry_setup_db'));
            exit;
        }

        // Check for debug request
        if (isset($_GET['my_gift_registry_debug']) && current_user_can('manage_options')) {
            $db_handler = new My_Gift_Registry_DB_Handler();
            $debug_info = $db_handler->debug_database_status();

            echo '<h2>My Gift Registry - Database Debug Info</h2>';
            echo '<pre>';
            print_r($debug_info);
            echo '</pre>';

            // Add manual table creation button
            if (empty($debug_info['wishlist_table_exists'])) {
                echo '<h3>Fix Database Issues</h3>';
                echo '<p>The wishlist table is missing. Click the button below to create it manually:</p>';
                echo '<a href="' . add_query_arg('my_gift_registry_create_table', '1') . '" class="button button-primary">Create Wishlist Table</a>';
            } else {
                echo '<a href="' . add_query_arg('my_gift_registry_setup_db', '1') . '" class="button button-primary">Setup Sample Data</a>';
            }

            echo '<h3>Rewrite Rules</h3>';
            echo '<p>If <code>/mywishlist/jessicas-bridal-shower</code> shows 404, try these steps in order:</p>';
            echo '<ol>';
            echo '<li><a href="' . add_query_arg('my_gift_registry_debug_rewrites', '1') . '" class="button button-secondary">1. Debug Current Rules</a></li>';
            echo '<li><a href="' . add_query_arg('my_gift_registry_register_rules', '1') . '" class="button button-secondary">2. Register & Flush Rules</a></li>';
            echo '<li><a href="' . add_query_arg('my_gift_registry_add_rule_direct', '1') . '" class="button button-secondary">3. Add Rule Directly</a></li>';
            echo '<li><a href="' . add_query_arg('my_gift_registry_flush_rules', '1') . '" class="button button-secondary">4. Just Flush Rules</a></li>';
            echo '</ol>';
            echo '<p>Then try accessing: <code>/mywishlist/jessicas-bridal-shower</code></p>';

            echo '<h3>✅ Working Solution: Direct URL Access</h3>';
            echo '<p><strong>This method always works regardless of rewrite rules:</strong></p>';
            echo '<p><code>' . home_url('/gift-registry-wishlist/?my_gift_registry_wishlist=jessicas-bridal-shower') . '</code></p>';
            echo '<a href="' . home_url('/gift-registry-wishlist/?my_gift_registry_wishlist=jessicas-bridal-shower') . '" target="_blank" class="button button-primary">🚀 Test Direct Access</a>';

            echo '<h4>Share URLs</h4>';
            echo '<p>Use these URLs for sharing your wishlists:</p>';
            echo '<ul>';
            echo '<li><strong>Jessica\'s Bridal Shower:</strong><br><code>' . home_url('/gift-registry-wishlist/?my_gift_registry_wishlist=jessicas-bridal-shower') . '</code></li>';
            echo '</ul>';

            echo '<h4>How to Create New Wishlists</h4>';
            echo '<p>To create new wishlists, you can manually add them to the database or create a custom admin interface. For now, the sample wishlist demonstrates all functionality.</p>';

            echo '<br><br><a href="' . remove_query_arg('my_gift_registry_debug') . '">← Back</a></p>';
            exit;
        }

        // Check for manual table creation request
        if (isset($_GET['my_gift_registry_create_table']) && current_user_can('manage_options')) {
            $db_handler = new My_Gift_Registry_DB_Handler();
            $result = $db_handler->create_wishlist_table_manually();

            if ($result) {
                echo '<div style="background: #d4edda; color: #155724; padding: 20px; margin: 20px; border: 1px solid #c3e6cb; border-radius: 4px;">';
                echo '<h3>✅ Success!</h3>';
                echo '<p>Wishlist table created successfully!</p>';
                echo '<a href="' . add_query_arg('my_gift_registry_setup_db', '1') . '" class="button button-primary">Add Sample Data</a>';
                echo '</div>';
            } else {
                echo '<div style="background: #f8d7da; color: #721c24; padding: 20px; margin: 20px; border: 1px solid #f5c6cb; border-radius: 4px;">';
                echo '<h3>❌ Failed</h3>';
                echo '<p>Could not create wishlist table. Check the error logs.</p>';
                echo '</div>';
            }

            echo '<p><a href="' . remove_query_arg('my_gift_registry_create_table') . '">← Back</a></p>';
            exit;
        }

        // Check for rewrite rules flush request
        if (isset($_GET['my_gift_registry_flush_rules']) && current_user_can('manage_options')) {
            flush_rewrite_rules();

            echo '<div style="background: #d4edda; color: #155724; padding: 20px; margin: 20px; border: 1px solid #c3e6cb; border-radius: 4px;">';
            echo '<h3>✅ Rewrite Rules Flushed!</h3>';
            echo '<p>Rewrite rules have been flushed successfully.</p>';
            echo '<p>Now try accessing: <code>/mywishlist/jessicas-bridal-shower</code></p>';
            echo '</div>';

            echo '<p><a href="' . remove_query_arg('my_gift_registry_flush_rules') . '">← Back</a></p>';
            exit;
        }

        // Check for rewrite rules debug request
        if (isset($_GET['my_gift_registry_debug_rewrites']) && current_user_can('manage_options')) {
            global $wp_rewrite;

            echo '<h2>My Gift Registry - Rewrite Rules Debug</h2>';
            echo '<h3>Current Rewrite Rules:</h3>';
            echo '<pre>';
            foreach ($wp_rewrite->rules as $pattern => $query) {
                if (strpos($pattern, 'mywishlist') !== false) {
                    echo "$pattern => $query\n";
                }
            }
            echo '</pre>';

            if (empty($wp_rewrite->rules)) {
                echo '<p style="color: red;"><strong>No rewrite rules found!</strong></p>';
            }

            echo '<h3>Plugin Rewrite Rules:</h3>';
            echo '<p><strong>Multiple URL patterns are now registered:</strong></p>';
            echo '<ul>';
            echo '<li><code>/wishlist/jessicas-bridal-shower</code> → Page-based (most reliable)</li>';
            echo '<li><code>/mywishlist/jessicas-bridal-shower</code> → Direct approach</li>';
            echo '<li><code>/dokan/mywishlist/jessicas-bridal-shower</code> → Subdirectory-aware</li>';
            echo '</ul>';

            echo '<h4>Test URLs:</h4>';
            echo '<p><strong>Page-based (Recommended):</strong></p>';
            echo '<code>' . home_url('/wishlist/jessicas-bridal-shower') . '</code>';
            echo '<br><br>';

            echo '<p><strong>Direct approach:</strong></p>';
            echo '<code>' . home_url('/mywishlist/jessicas-bridal-shower') . '</code>';

            echo '<h4>Alternative: Manual .htaccess (Always Works)</h4>';
            echo '<p>If WordPress rewrite rules still don\'t work, add these lines to your .htaccess file:</p>';
            echo '<textarea readonly style="width:100%; height:80px; font-family:monospace;">';
            echo 'RewriteRule ^wishlist/([^/]+)/?$ /dokan/gift-registry-wishlist/?my_gift_registry_wishlist=$1 [QSA,L]' . "\n";
            echo 'RewriteRule ^mywishlist/([^/]+)/?$ /dokan/gift-registry-wishlist/?my_gift_registry_wishlist=$1 [QSA,L]' . "\n";
            echo '</textarea>';
            echo '<p><em>Add these lines after the existing WordPress rewrite rules in your .htaccess file.</em></p>';

            echo '<h4>✅ Direct Access URLs (Always Work)</h4>';
            echo '<p>These URLs work regardless of rewrite rule status:</p>';
            echo '<code>' . home_url('/gift-registry-wishlist/?my_gift_registry_wishlist=jessicas-bridal-shower') . '</code>';
            echo '<br><br>';
            echo '<a href="' . home_url('/gift-registry-wishlist/?my_gift_registry_wishlist=jessicas-bridal-shower') . '" target="_blank" class="button button-primary">🧪 Test Direct Access</a>';

            echo '<br><a href="' . remove_query_arg('my_gift_registry_debug_rewrites') . '">← Back</a></p>';
            exit;
        }

        // Check for manual rewrite rule registration request
        if (isset($_GET['my_gift_registry_register_rules']) && current_user_can('manage_options')) {
            // Force register the rules
            $rewrite_handler = new My_Gift_Registry_Rewrite_Handler();
            $rewrite_handler->register_rewrite_rules();

            // Force flush
            flush_rewrite_rules(true);

            // Also try to save to database directly
            global $wp_rewrite;
            $wp_rewrite->flush_rules(true);

            echo '<div style="background: #d4edda; color: #155724; padding: 20px; margin: 20px; border: 1px solid #c3e6cb; border-radius: 4px;">';
            echo '<h3>✅ Rewrite Rules Force Registered!</h3>';
            echo '<p>Rewrite rules have been force registered and flushed.</p>';
            echo '<p>Now try accessing: <code>/mywishlist/jessicas-bridal-shower</code></p>';
            echo '<a href="' . add_query_arg('my_gift_registry_debug_rewrites', '1') . '" class="button button-primary">Check Rules</a>';
            echo '</div>';

            echo '<p><a href="' . remove_query_arg('my_gift_registry_register_rules') . '">← Back</a></p>';
            exit;
        }

        // Check for direct rule addition (bypass normal registration)
        if (isset($_GET['my_gift_registry_add_rule_direct']) && current_user_can('manage_options')) {
            global $wp_rewrite;

            // Get the current URL structure
            $home_path = parse_url(home_url(), PHP_URL_PATH);
            $base_path = $home_path ? trim($home_path, '/') . '/' : '';
            $pattern = $base_path . 'mywishlist/([^/]*)/?';

            // Add rule directly to the rules array
            $wp_rewrite->rules[$pattern] = 'index.php?my_gift_registry_wishlist=$matches[1]';

            // Save to database
            update_option('rewrite_rules', $wp_rewrite->rules);

            echo '<div style="background: #d4edda; color: #155724; padding: 20px; margin: 20px; border: 1px solid #c3e6cb; border-radius: 4px;">';
            echo '<h3>✅ Rule Added Directly!</h3>';
            echo '<p>Added rule: <code>' . $pattern . ' => index.php?my_gift_registry_wishlist=$matches[1]</code></p>';
            echo '<a href="' . add_query_arg('my_gift_registry_debug_rewrites', '1') . '" class="button button-primary">Check Rules</a>';
            echo '</div>';

            echo '<p><a href="' . remove_query_arg('my_gift_registry_add_rule_direct') . '">← Back</a></p>';
            exit;
        }
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Check if any of the plugin shortcodes are present on the page
        global $post;
        $has_wishlist_shortcode = is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'gift_registry_wishlist');
        $has_create_form_shortcode = is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'create_wishlist_form');
        $has_my_wishlists_shortcode = is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'my_wishlists');

        if ($has_wishlist_shortcode || $has_create_form_shortcode || $has_my_wishlists_shortcode) {
            wp_enqueue_style(
                'my-gift-registry-style',
                MY_GIFT_REGISTRY_PLUGIN_URL . 'assets/css/my-gift-registry.css',
                array(),
                MY_GIFT_REGISTRY_VERSION
            );

            // Enqueue WordPress media scripts for media manager
            if ($has_create_form_shortcode || $has_my_wishlists_shortcode) {
                wp_enqueue_media();
                wp_enqueue_script('jquery-ui-datepicker');
            }

            wp_enqueue_script(
                'my-gift-registry-script',
                MY_GIFT_REGISTRY_PLUGIN_URL . 'assets/js/my-gift-registry-phase3.js',
                array('jquery'),
                MY_GIFT_REGISTRY_VERSION,
                true
            );

            // Localize script for AJAX
            wp_localize_script('my-gift-registry-script', 'myGiftRegistryAjax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('my_gift_registry_nonce'),
                'strings' => array(
                    'reserve_gift' => __('Reserve this Gift', 'my-gift-registry'),
                    'email_required' => __('Email is required', 'my-gift-registry'),
                    'emails_mismatch' => __('Emails do not match', 'my-gift-registry'),
                    'reserving' => __('Reserving...', 'my-gift-registry'),
                    'reserved' => __('Reserved!', 'my-gift-registry'),
                    'delete_confirm' => __('Are you sure you want to delete this wishlist?', 'my-gift-registry'),
                    'deleting' => __('Deleting...', 'my-gift-registry'),
                    'deleted' => __('Wishlist deleted successfully!', 'my-gift-registry'),
                )
            ));
        }
    }
}

// Initialize the plugin
My_Gift_Registry::get_instance();