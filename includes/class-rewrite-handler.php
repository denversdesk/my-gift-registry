<?php
/**
 * Rewrite handler for My Gift Registry plugin
 *
 * Handles custom rewrite endpoints for wishlist URLs
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class My_Gift_Registry_Rewrite_Handler {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_loaded', array($this, 'register_rewrite_rules'), 10);
        add_filter('query_vars', array($this, 'add_query_vars'), 10);
        add_action('template_redirect', array($this, 'template_redirect'), 10);

        // Register rewrite rules on plugin activation hook
        add_action('my_gift_registry_activate', array($this, 'setup_rewrite_rules'));
    }

    /**
     * Setup rewrite rules (called on plugin activation)
     */
    public function setup_rewrite_rules() {
        error_log('My Gift Registry: Setting up rewrite rules for activation');

        // Register rules
        $this->register_rewrite_rules();

        // Force flush rewrite rules
        flush_rewrite_rules(true);

        error_log('My Gift Registry: Rewrite rules flushed after plugin activation');
    }

    /**
     * Register custom rewrite rules
     */
    public function register_rewrite_rules() {
        // Only proceed if this is not an AJAX request
        if (wp_doing_ajax()) {
            return;
        }

        // Check if WooCommerce is active to avoid conflicts
        if (class_exists('WooCommerce')) {
            // Get WooCommerce wishlist endpoint
            $wishlist_endpoint = get_option('woocommerce_myaccount_wishlist_endpoint', 'wishlist');

            // Only add our rule if it doesn't conflict with WooCommerce
            if ($wishlist_endpoint !== '') {
                // Use a more specific pattern that doesn't conflict with empty WooCommerce wishlist
                add_rewrite_rule(
                    '^wishlist/([^/]+)/?$', // Only matches wishlist/something, not just /wishlist/
                    'index.php?my_gift_registry_wishlist=$matches[1]',
                    'top'
                );

                error_log('My Gift Registry: Registered rewrite rule for gift registry wishlists');
            }
        } else {
            // WooCommerce not active, safely register our rule
            add_rewrite_rule(
                '^wishlist/([^/]+)/?$',
                'index.php?my_gift_registry_wishlist=$matches[1]',
                'top'
            );

            error_log('My Gift Registry: Registered rewrite rule (no WooCommerce conflict)');
        }

        // Add rewrite tag for the wishlist slug
        add_rewrite_tag('%my_gift_registry_wishlist%', '([^&]+)');

        error_log('My Gift Registry: Rewrite rules registration complete');
    }

    /**
     * Add custom query variables
     *
     * @param array $vars
     * @return array
     */
    public function add_query_vars($vars) {
        $vars[] = 'my_gift_registry_wishlist';
        return $vars;
    }

    /**
     * Handle template redirection for wishlist pages
     */
    public function template_redirect() {
        // Check if this is a wishlist request
        $wishlist_slug = get_query_var('my_gift_registry_wishlist');

        if (!empty($wishlist_slug)) {
            // Verify this isn't just an empty wishlist request (let WooCommerce handle that)
            if (empty($wishlist_slug)) {
                return;
            }

            // Get the wishlist data
            $db_handler = new My_Gift_Registry_DB_Handler();
            $wishlist = $db_handler->get_wishlist_by_slug($wishlist_slug);

            if ($wishlist) {
                // Store wishlist data for use in shortcode
                global $my_gift_registry_current_wishlist;
                $my_gift_registry_current_wishlist = $wishlist;

                // Try to find a page with our shortcode
                $shortcode_page = $this->find_shortcode_page();

                if ($shortcode_page) {
                    // Check if we're already on that page
                    if (get_the_ID() !== $shortcode_page->ID) {
                        // Redirect to the page with the shortcode, preserving the slug parameter
                        $redirect_url = add_query_arg('my_gift_registry_wishlist', $wishlist_slug, get_permalink($shortcode_page->ID));
                        wp_redirect($redirect_url, 301);
                        exit;
                    }
                } else {
                    // If no dedicated shortcode page found, try to load the current page
                    global $post;
                    if (!is_object($post) || !has_shortcode($post->post_content, 'gift_registry_wishlist')) {
                        // If the current page doesn't have our shortcode, show 404
                        global $wp_query;
                        $wp_query->set_404();
                        status_header(404);
                        get_template_part(404);
                        exit;
                    }
                }

                // If we get here, the page with the shortcode will load normally
                error_log('My Gift Registry: Loading wishlist page for slug: ' . $wishlist_slug);
            } else {
                // Wishlist not found - show 404
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
                get_template_part(404);
                exit;
            }
        }
    }

    /**
     * Find a page that contains the gift registry shortcode
     *
     * @return WP_Post|null
     */
    private function find_shortcode_page() {
        $args = array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_query' => array(
                array(
                    'key' => '_my_gift_registry_shortcode_page',
                    'value' => '1',
                    'compare' => '='
                )
            )
        );

        $pages = get_posts($args);

        if (!empty($pages)) {
            return $pages[0];
        }

        // Fallback: search for pages containing the shortcode
        $args = array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            's' => '[gift_registry_wishlist]'
        );

        $pages = get_posts($args);

        if (!empty($pages)) {
            // Mark this page for future reference
            update_post_meta($pages[0]->ID, '_my_gift_registry_shortcode_page', '1');
            return $pages[0];
        }

        return null;
    }
}