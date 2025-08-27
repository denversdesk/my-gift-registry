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
    }

    /**
     * Register custom rewrite rules
     */
    public function register_rewrite_rules() {
        // Only proceed if this is not an AJAX request
        if (wp_doing_ajax()) {
            return;
        }

        // Method 1: Simple approach that works with subdirectories
        add_rewrite_rule(
            '^wishlist/([^/]*)/?$',
            'index.php?pagename=gift-registry-wishlist&my_gift_registry_wishlist=$matches[1]',
            'top'
        );

        // Method 2: Direct approach for /mywishlist URLs
        add_rewrite_rule(
            '^mywishlist/([^/]*)/?$',
            'index.php?my_gift_registry_wishlist=$matches[1]',
            'top'
        );

        // Method 3: Subdirectory-aware approach
        $home_path = parse_url(home_url(), PHP_URL_PATH);
        if ($home_path && trim($home_path, '/') === 'dokan') {
            add_rewrite_rule(
                '^dokan/mywishlist/([^/]*)/?$',
                'index.php?my_gift_registry_wishlist=$matches[1]',
                'top'
            );
        }

        // Add rewrite tag for the wishlist slug
        add_rewrite_tag('%my_gift_registry_wishlist%', '([^&]+)');

        // Debug logging
        error_log('My Gift Registry: Registered multiple rewrite rules');
        error_log('My Gift Registry: Page-based: ^wishlist/([^/]*)/?$ => index.php?pagename=gift-registry-wishlist&my_gift_registry_wishlist=$matches[1]');
        error_log('My Gift Registry: Direct: ^mywishlist/([^/]*)/?$ => index.php?my_gift_registry_wishlist=$matches[1]');
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
            // Get the wishlist data
            $db_handler = new My_Gift_Registry_DB_Handler();
            $wishlist = $db_handler->get_wishlist_by_slug($wishlist_slug);

            if ($wishlist) {
                // Store wishlist data for use in shortcode
                global $my_gift_registry_current_wishlist;
                $my_gift_registry_current_wishlist = $wishlist;

                // Check if current page has the shortcode
                global $post;
                if (!is_object($post) || !has_shortcode($post->post_content, 'gift_registry_wishlist')) {
                    // Find a page with the shortcode and redirect
                    $shortcode_page = $this->find_shortcode_page();

                    if ($shortcode_page) {
                        // Redirect to the page with the shortcode, preserving the query parameter
                        $redirect_url = add_query_arg('my_gift_registry_wishlist', $wishlist_slug, get_permalink($shortcode_page->ID));
                        wp_redirect($redirect_url);
                        exit;
                    } else {
                        // If no page found, show 404
                        global $wp_query;
                        $wp_query->set_404();
                        status_header(404);
                        get_template_part(404);
                        exit;
                    }
                }
                // If current page has the shortcode, let it load normally
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