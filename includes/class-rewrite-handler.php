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
        add_action('generate_rewrite_rules', array($this, 'modify_rewrite_rules'), 0);
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

        // Force flush rewrite rules (this will trigger generate_rewrite_rules hook)
        flush_rewrite_rules(true);

        error_log('My Gift Registry: Rewrite rules flushed after plugin activation');
    }

    /**
     * Modify rewrite rules to ensure our wishlist rules take precedence
     */
    public function modify_rewrite_rules($wp_rewrite) {
        // Only proceed if this is not an AJAX request
        if (wp_doing_ajax()) {
            return;
        }

        error_log('My Gift Registry: Modifying rewrite rules for wishlist precedence');

        // Remove any existing WooCommerce wishlist rules that might conflict
        $rules_to_remove = array();
        foreach ($wp_rewrite->rules as $pattern => $query) {
            // Remove rules that match wishlist without our specific pattern
            if (preg_match('/^wishlist/', $pattern) && !preg_match('/^wishlist\/\([^\/]+\)/', $pattern)) {
                $rules_to_remove[] = $pattern;
                error_log('My Gift Registry: Removing conflicting rule: ' . $pattern);
            }
        }

        // Remove the conflicting rules
        foreach ($rules_to_remove as $pattern) {
            unset($wp_rewrite->rules[$pattern]);
        }

        // Add our rule at the very beginning to ensure highest priority
        $new_rules = array(
            '^wishlist/([^/]+)/?$' => 'index.php?my_gift_registry_wishlist=$matches[1]'
        );

        // Merge our rules with existing rules, putting ours first
        $wp_rewrite->rules = array_merge($new_rules, $wp_rewrite->rules);

        // Add rewrite tag for the wishlist slug
        add_rewrite_tag('%my_gift_registry_wishlist%', '([^&]+)');

        error_log('My Gift Registry: Successfully modified rewrite rules with our rule at top priority');
        error_log('My Gift Registry: New rules count: ' . count($wp_rewrite->rules));
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

        error_log('My Gift Registry: Template redirect check - wishlist_slug: ' . $wishlist_slug);
        error_log('My Gift Registry: Current URL: ' . $_SERVER['REQUEST_URI']);
        error_log('My Gift Registry: Query vars: ' . print_r($GLOBALS['wp']->query_vars, true));

        if (!empty($wishlist_slug)) {
            error_log('My Gift Registry: Processing wishlist request for slug: ' . $wishlist_slug);

            // Verify this isn't just an empty wishlist request (let WooCommerce handle that)
            if (empty($wishlist_slug)) {
                error_log('My Gift Registry: Empty wishlist slug, letting WooCommerce handle');
                return;
            }

            // Get the wishlist data
            $db_handler = new My_Gift_Registry_DB_Handler();
            $wishlist = $db_handler->get_wishlist_by_slug($wishlist_slug);

            if ($wishlist) {
                error_log('My Gift Registry: Found wishlist: ' . print_r($wishlist, true));

                // Store wishlist data for use in shortcode
                global $my_gift_registry_current_wishlist;
                $my_gift_registry_current_wishlist = $wishlist;

                // Try to find a page with our shortcode
                $shortcode_page = $this->find_shortcode_page();

                if ($shortcode_page) {
                    error_log('My Gift Registry: Found shortcode page: ' . $shortcode_page->ID . ' - ' . $shortcode_page->post_title);

                    // Check if we're already on that page
                    if (get_the_ID() !== $shortcode_page->ID) {
                        // Redirect to the page with the shortcode, preserving the slug parameter
                        $redirect_url = add_query_arg('my_gift_registry_wishlist', $wishlist_slug, get_permalink($shortcode_page->ID));
                        error_log('My Gift Registry: Redirecting to: ' . $redirect_url);
                        wp_redirect($redirect_url, 301);
                        exit;
                    } else {
                        error_log('My Gift Registry: Already on the correct page');
                    }
                } else {
                    error_log('My Gift Registry: No shortcode page found');

                    // If no dedicated shortcode page found, try to load the current page
                    global $post;
                    if (!is_object($post) || !has_shortcode($post->post_content, 'gift_registry_wishlist')) {
                        error_log('My Gift Registry: Current page does not have shortcode, showing 404');

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
                error_log('My Gift Registry: Wishlist not found for slug: ' . $wishlist_slug);

                // Wishlist not found - show 404
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
                get_template_part(404);
                exit;
            }
        } else {
            error_log('My Gift Registry: No wishlist slug in query vars');
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