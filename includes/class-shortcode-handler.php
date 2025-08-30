<?php
/**
 * Shortcode handler for My Gift Registry plugin
 *
 * Handles the [gift_registry_wishlist] shortcode
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class My_Gift_Registry_Shortcode_Handler {
    /**
     * Current wishlist data for OG tags
     */
    private $current_wishlist_for_og = null;

    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('gift_registry_wishlist', array($this, 'render_wishlist'));

        // Add Open Graph tags to wp_head for wishlist pages
        add_action('wp_head', array($this, 'add_open_graph_tags'), 1);

        // Check for wishlist URLs early
        add_action('wp', array($this, 'detect_wishlist_url_early'), 1);
    }

    /**
     * Render the wishlist shortcode
     *
     * @param array $atts
     * @param string $content
     * @return string
     */
    public function render_wishlist($atts, $content = '') {
        // Set default attributes
        $atts = shortcode_atts(array(
            'slug' => '',
        ), $atts, 'gift_registry_wishlist');

        // Get current wishlist from global (set by rewrite handler) or from slug parameter
        global $my_gift_registry_current_wishlist;
        $wishlist = $my_gift_registry_current_wishlist;

        // If no global wishlist, try to get by slug parameter
        if (!$wishlist && !empty($atts['slug'])) {
            $db_handler = new My_Gift_Registry_DB_Handler();
            $wishlist = $db_handler->get_wishlist_by_slug($atts['slug']);
        }

        // If still no wishlist, try to get slug from URL query parameter (for direct access)
        if (!$wishlist) {
            $url_slug = get_query_var('my_gift_registry_wishlist');
            if (!empty($url_slug)) {
                $db_handler = new My_Gift_Registry_DB_Handler();
                $wishlist = $db_handler->get_wishlist_by_slug($url_slug);
            }
        }

        // If still no wishlist, try to get slug from $_GET parameter (direct URL access)
        if (!$wishlist && isset($_GET['my_gift_registry_wishlist'])) {
            $get_slug = sanitize_text_field($_GET['my_gift_registry_wishlist']);
            if (!empty($get_slug)) {
                $db_handler = new My_Gift_Registry_DB_Handler();
                $wishlist = $db_handler->get_wishlist_by_slug($get_slug);
            }
        }

        // Store wishlist for Open Graph tags if we have one and it hasn't been set yet
        if ($wishlist && !$this->current_wishlist_for_og) {
            $this->current_wishlist_for_og = $wishlist;
        }

        // If still no wishlist, show a demo page or error message
        if (!$wishlist) {
            // Check if this is a direct access to a wishlist URL
            $url_slug = get_query_var('my_gift_registry_wishlist');
            if (!empty($url_slug)) {
                return $this->render_error_message(__('This wishlist does not exist.', 'my-gift-registry'));
            }

            // Show demo page with sample data for regular page access
            return $this->render_demo_page();
        }

        // Render the wishlist
        return $this->render_wishlist_html($wishlist);
    }

    /**
     * Render error message
     *
     * @param string $message
     * @return string
     */
    private function render_error_message($message) {
        return '<div class="my-gift-registry-error">' . esc_html($message) . '</div>';
    }

    /**
     * Render demo page with sample wishlist
     *
     * @return string
     */
    private function render_demo_page() {
        $db_handler = new My_Gift_Registry_DB_Handler();
        $demo_wishlist = $db_handler->get_wishlist_by_slug('jessicas-bridal-shower');

        if ($demo_wishlist) {
            return $this->render_wishlist_html($demo_wishlist);
        }

        return $this->render_error_message(__('No sample wishlist found. Please check your database setup.', 'my-gift-registry'));
    }

    /**
     * Render wishlist HTML
     *
     * @param object $wishlist
     * @return string
     */
    private function render_wishlist_html($wishlist) {
        ob_start();

        // Wishlist header with share buttons
        echo '<div class="my-gift-registry-wishlist">';
        echo '<div class="wishlist-header">';
        echo '<h1 class="wishlist-title">' . esc_html(stripslashes($wishlist->title)) . '</h1>';

        if (!empty($wishlist->description)) {
            echo '<p class="wishlist-description">' . esc_html($wishlist->description) . '</p>';
        }

        // Event date and countdown (only if event_date is set)
        echo $this->render_event_date_section($wishlist);

        // Share buttons
        echo $this->render_share_buttons($wishlist);

        // Add Products button (only for wishlist owner)
        echo $this->render_add_products_button($wishlist);

        // Profile picture (if exists)
        if (!empty($wishlist->profile_pic)) {
            echo '<div class="wishlist-profile-pic">';
            echo '<div class="profile-pic">';
            echo '<img src="' . esc_url($wishlist->profile_pic) . '" alt="Profile Picture">';
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';

        // Wishlist items
        if (!empty($wishlist->gifts)) {
            echo '<div class="wishlist-items">';
            foreach ($wishlist->gifts as $gift) {
                echo $this->render_gift_item($gift, $wishlist->slug);
            }
            echo '</div>';
        } else {
            echo '<p class="no-gifts">' . __('No gifts in this wishlist yet.', 'my-gift-registry') . '</p>';
        }

        // Bottom share buttons
        echo $this->render_share_buttons($wishlist);

        echo '</div>';

        // Reservation modal
        echo $this->render_reservation_modal();

        // Product addition modal
        echo $this->render_add_product_modal();

        return ob_get_clean();
    }

    /**
     * Render share buttons
     *
     * @param object $wishlist
     * @return string
     */
    private function render_share_buttons($wishlist) {
        // Use page-based URL (most reliable for subdirectories)
        $page_based_url = home_url('/wishlist/' . $wishlist->slug);
        $direct_url = home_url('/gift-registry-wishlist/?my_gift_registry_wishlist=' . $wishlist->slug);
        $pretty_url = home_url('/mywishlist/' . $wishlist->slug);

        // Use page-based URL for sharing (most likely to work with rewrite rules)
        $current_url = $page_based_url;
        $share_text = __('Check out my gift registry: ', 'my-gift-registry') . stripslashes($wishlist->title);

        ob_start();
        ?>
        <div class="wishlist-share-buttons">
            <h3><?php _e('Share this wishlist', 'my-gift-registry'); ?></h3>
            <div class="share-buttons">
                <button class="share-button copy-link" data-url="<?php echo esc_url($current_url); ?>">
                    <span class="share-icon"><i class="fa fa-link"></i></span>
                    <span class="social-button-text"><?php _e('Copy Link', 'my-gift-registry'); ?></span>
                </button>
                <a href="https://wa.me/?text=<?php echo urlencode($share_text . ' ' . $current_url); ?>"
                   class="share-button whatsapp" target="_blank">
                    <span class="share-icon"><i class="fa fa-whatsapp"></i></span>
                    <span class="social-button-text">WhatsApp</span>
                </a>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($current_url); ?>"
                   class="share-button facebook" target="_blank">
                    <span class="share-icon"><i class="fa fa-facebook"></i></span>
                    <span class="social-button-text">Facebook</span>
                </a>
                <a href="https://www.instagram.com/?url=<?php echo urlencode($current_url); ?>"
                   class="share-button instagram" target="_blank">
                    <span class="share-icon"><i class="fa fa-instagram"></i></span>
                    <span class="social-button-text">Instagram</span>
                </a>
            </div>
            <div class="share-url-info">
                <small>Shareable URL: <code><?php echo esc_html($current_url); ?></code></small>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render gift item
     *
     * @param object $gift
     * @param string $wishlist_slug
     * @return string
     */
    private function render_gift_item($gift, $wishlist_slug) {
        ob_start();
        ?>
        <div class="gift-item <?php echo $gift->is_reserved ? 'reserved' : ''; ?>" data-gift-id="<?php echo esc_attr($gift->id); ?>">
            <div class="gift-image">
                <?php if (!empty($gift->image_url)) : ?>
                    <img src="<?php echo esc_url($gift->image_url); ?>" alt="<?php echo esc_attr($gift->title); ?>">
                <?php else : ?>
                    <div class="no-image">📦</div>
                <?php endif; ?>

                <?php if (!$gift->is_reserved) : ?>
                    <button class="reserve-button" data-gift-id="<?php echo esc_attr($gift->id); ?>">
                        <?php _e('Reserve it', 'my-gift-registry'); ?>
                    </button>
                <?php else : ?>
                    <div class="reserved-badge"><?php _e('Reserved', 'my-gift-registry'); ?></div>
                <?php endif; ?>
            </div>

            <div class="gift-details">
                <h3 class="gift-title"><?php echo esc_html($gift->title); ?></h3>

                <?php if (!empty($gift->description)) : ?>
                    <p class="gift-description"><?php echo esc_html($gift->description); ?></p>
                <?php endif; ?>

                <?php if (!empty($gift->price)) : ?>
                    <p class="gift-price"><?php _e('Estimated price:', 'my-gift-registry'); ?> <?php echo wc_price($gift->price); ?></p>
                <?php endif; ?>

                <?php if (!empty($gift->product_url)) : ?>
                    <p class="gift-link">
                        <a href="<?php echo esc_url($gift->product_url); ?>" target="_blank" class="buy-link">
                            <?php _e('View Product', 'my-gift-registry'); ?> →
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render reservation modal
     *
     * @return string
     */
    private function render_reservation_modal() {
        ob_start();
        ?>
        <div id="reservation-modal" class="reservation-modal" style="display: none;">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h2><?php _e('Reserve this Gift', 'my-gift-registry'); ?></h2>
                    <button class="modal-close">&times;</button>
                </div>

                <div class="modal-body">
                    <div id="reservation-gift-info" class="gift-info">
                        <!-- Gift info will be populated by JavaScript -->
                    </div>

                    <form id="reservation-form" class="reservation-form">
                        <div class="form-group">
                            <label for="reservation-email"><?php _e('Email Address', 'my-gift-registry'); ?> *</label>
                            <input type="email" id="reservation-email" name="email" required>
                        </div>

                        <div class="form-group">
                            <label for="reservation-confirm-email"><?php _e('Confirm Email Address', 'my-gift-registry'); ?> *</label>
                            <input type="email" id="reservation-confirm-email" name="confirm_email" required>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="cancel-button"><?php _e('Cancel', 'my-gift-registry'); ?></button>
                            <button type="submit" class="reserve-submit-button" disabled>
                                <?php _e('Reserve Gift', 'my-gift-registry'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render add products button for wishlist owners
     *
     * @param object $wishlist
     * @return string
     */
    private function render_add_products_button($wishlist) {
        // Only show button if user is logged in and owns the wishlist
        if (!is_user_logged_in()) {
            return '';
        }

        $current_user = wp_get_current_user();
        if ($current_user->ID != $wishlist->user_id) {
            return '';
        }

        ob_start();
        ?>
        <div class="add-products-section">
            <button class="add-products-button" data-wishlist-id="<?php echo esc_attr($wishlist->id); ?>">
                <span class="button-icon">+</span>
                <?php _e('Add Products to Your Wishlist', 'my-gift-registry'); ?>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render add product modal
     *
     * @return string
     */
    private function render_add_product_modal() {
        ob_start();
        ?>
        <div id="add-product-modal" class="add-product-modal" style="display: none;">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h2><?php _e('Add Product to Wishlist', 'my-gift-registry'); ?></h2>
                    <button class="modal-close">&times;</button>
                </div>

                <div class="modal-body">
                    <form id="add-product-form" class="add-product-form">
                        <div class="form-group">
                            <label for="product-search"><?php _e('Search Sendlove.co.zw Products (optional)', 'my-gift-registry'); ?></label>
                            <input type="text" id="product-search" name="product_search" placeholder="<?php _e('Type to search products...', 'my-gift-registry'); ?>">
                            <div id="product-search-results" class="product-search-results" style="display: none;"></div>
                        </div>

                        <div class="form-group">
                            <label for="product-title"><?php _e('Product Title', 'my-gift-registry'); ?> *</label>
                            <input type="text" id="product-title" name="title" required>
                        </div>

                        <div class="form-group">
                            <label for="product-description"><?php _e('Description', 'my-gift-registry'); ?></label>
                            <textarea id="product-description" name="description" rows="3"></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="product-price"><?php _e('Price', 'my-gift-registry'); ?></label>
                                <input type="number" id="product-price" name="price" step="0.01" min="0">
                            </div>
                            <div class="form-group">
                                <label for="product-priority"><?php _e('Priority', 'my-gift-registry'); ?></label>
                                <select id="product-priority" name="priority">
                                    <option value="0"><?php _e('Normal', 'my-gift-registry'); ?></option>
                                    <option value="1"><?php _e('High', 'my-gift-registry'); ?></option>
                                    <option value="2"><?php _e('Very High', 'my-gift-registry'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="product-image-url"><?php _e('Product Image', 'my-gift-registry'); ?></label>
                            <div class="image-upload-section">
                                <div class="image-preview" id="product-image-preview" style="display: none;">
                                    <img id="product-image-thumb" src="" alt="Product Image" style="max-width: 100px; height: auto;">
                                    <button type="button" class="remove-image-button" title="Remove Image">×</button>
                                </div>
                                <input type="hidden" id="product-image-url" name="image_url" value="">
                                <button type="button" class="choose-image-button" id="choose-product-image">
                                    <?php _e('Choose Image', 'my-gift-registry'); ?>
                                </button>
                                <p class="image-help"><?php _e('Upload a new image or select from your media library', 'my-gift-registry'); ?></p>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="product-url"><?php _e('Product URL', 'my-gift-registry'); ?></label>
                            <input type="url" id="product-url" name="product_url">
                        </div>

                        <div class="form-actions">
                            <button type="button" class="cancel-button"><?php _e('Cancel', 'my-gift-registry'); ?></button>
                            <button type="submit" class="add-product-submit-button">
                                <?php _e('Add Product', 'my-gift-registry'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render event date and countdown section
     *
     * @param object $wishlist
     * @return string
     */
    private function render_event_date_section($wishlist) {
        // Only show if event_date is set
        if (empty($wishlist->event_date)) {
            return '';
        }

        // Parse the event date
        $event_date = strtotime($wishlist->event_date);
        if (!$event_date) {
            return '';
        }

        // Format the date for display
        $formatted_date = date_i18n(get_option('date_format'), $event_date);

        // Calculate days until event
        $current_time = current_time('timestamp');
        $days_until = ceil(($event_date - $current_time) / (60 * 60 * 24));

        // Determine the countdown message
        if ($days_until > 0) {
            $countdown_text = sprintf(
                _n('%d day to go', '%d days to go', $days_until, 'my-gift-registry'),
                $days_until
            );
        } elseif ($days_until === 0) {
            $countdown_text = __('Today!', 'my-gift-registry');
        } else {
            $countdown_text = __('Event has passed', 'my-gift-registry');
        }

        ob_start();
        ?>
        <div class="event-date-section" data-event-date="<?php echo esc_attr($wishlist->event_date); ?>">
            <div class="event-date-display">
                Date: <span><?php echo esc_html($formatted_date); ?></span>
                <div class="event-countdown">
                    <?php echo esc_html($countdown_text); ?>
                </div>
            </div>
            
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Detect wishlist URLs early in the WordPress loading process
     */
    public function detect_wishlist_url_early() {
        // Check for our wishlist slug query var early
        $wishlist_slug = get_query_var('my_gift_registry_wishlist');

        if (!empty($wishlist_slug)) {
            // Get the wishlist data early
            $db_handler = new My_Gift_Registry_DB_Handler();
            $wishlist = $db_handler->get_wishlist_by_slug($wishlist_slug);

            if ($wishlist) {
                // Store for use in OG tags
                $this->current_wishlist_for_og = $wishlist;

                // Also store in global for shortcode handler
                global $my_gift_registry_current_wishlist;
                $my_gift_registry_current_wishlist = $wishlist;
            }
        }
    }

    /**
     * Add Open Graph meta tags to the head for wishlist pages
     */
    public function add_open_graph_tags() {
        // Only add OG tags if we have a current wishlist
        if (!$this->current_wishlist_for_og) {
            return;
        }

        $wishlist = $this->current_wishlist_for_og;

        // Build the wishlist URL
        $wishlist_url = home_url('/wishlist/' . $wishlist->slug);

        // Prepare the values with fallbacks
        $og_title = !empty($wishlist->title) ? wp_strip_all_tags(stripslashes($wishlist->title)) : __('Gift Registry', 'my-gift-registry');

        // Create a description from available data
        $og_description_parts = array();
        if (!empty($wishlist->description)) {
            $og_description_parts[] = wp_strip_all_tags(stripslashes($wishlist->description));
        }
        if (!empty($wishlist->full_name)) {
            $og_description_parts[] = __('Created by', 'my-gift-registry') . ' ' . wp_strip_all_tags($wishlist->full_name);
        }
        $og_description = !empty($og_description_parts) ? implode(' | ', $og_description_parts) :
                         sprintf(__('Help make %s special with gifts. View their wishlist!', 'my-gift-registry'), $og_title);

        // Set up the image (profile picture or fallback)
        $og_image = '';
        if (!empty($wishlist->profile_pic)) {
            $og_image = wp_get_attachment_url($wishlist->profile_pic) ?: $wishlist->profile_pic;
        }

        // Fallback to a default gift image if no profile picture
        if (empty($og_image)) {
            // Use a placeholder image or default gift-related image
            $og_image = 'https://via.placeholder.com/1200x630?text=' . urlencode($og_title);
        }

        // Output the Open Graph meta tags
        ?>
        <!-- My Gift Registry Open Graph Tags -->
        <meta property="og:title" content="<?php echo esc_attr($og_title); ?>" />
        <meta property="og:description" content="<?php echo esc_attr($og_description); ?>" />
        <meta property="og:image" content="<?php echo esc_url($og_image); ?>" />
        <meta property="og:url" content="<?php echo esc_url($wishlist_url); ?>" />
        <meta property="og:type" content="website" />
        <meta property="og:site_name" content="<?php echo esc_attr(get_bloginfo('name')); ?>" />

        <!-- Twitter Card Tags (for additional compatibility) -->
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="<?php echo esc_attr($og_title); ?>" />
        <meta name="twitter:description" content="<?php echo esc_attr($og_description); ?>" />
        <meta name="twitter:image" content="<?php echo esc_url($og_image); ?>" />

        <!-- Additional meta tags for better sharing -->
        <meta name="description" content="<?php echo esc_attr($og_description); ?>" />
        <meta name="keywords" content="gift registry, wishlist, <?php echo esc_attr($og_title); ?>" />

        <?php
    }
}