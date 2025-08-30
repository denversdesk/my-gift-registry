<?php
/**
 * Email handler for My Gift Registry plugin
 *
 * Handles email notifications for gift reservations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class My_Gift_Registry_Email_Handler {

    /**
     * Current wishlist for email context
     */
    private $currentWishlist = null;

    /**
     * Send reservation confirmation email
     *
     * @param string $email
     * @param object $gift
     * @return bool
     */
    public function send_reservation_confirmation($email, $gift) {
        $subject = __('Your Gift Reservation Confirmation', 'my-gift-registry');

        // Get wishlist details for recommended products
        $db_handler = new My_Gift_Registry_DB_Handler();
        $wishlist = $db_handler->get_wishlist_by_slug($gift->wishlist_slug);

        // Store wishlist info for use in email content generation
        $this->currentWishlist = $wishlist;

        // Prepare email content
        $message = $this->get_reservation_email_content($email, $gift, $wishlist);

        // Set email headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        // Send the email
        $sent = wp_mail($email, $subject, $message, $headers);

        // Log the email for debugging if needed
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('My Gift Registry - Reservation email sent to: ' . $email . ' - Status: ' . ($sent ? 'Success' : 'Failed'));
        }

        return $sent;
    }

    /**
     * Get reservation email content
     *
     * @param string $email
     * @param object $gift
     * @param object $wishlist
     * @return string
     */
    private function get_reservation_email_content($email, $gift, $wishlist = null) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php _e('Gift Reservation Confirmation', 'my-gift-registry'); ?></title>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f8f9fa; }
                .container { max-width: 650px; margin: 0 auto; background-color: white; }
                .logo-section { text-align: center; padding: 30px 20px 20px; background-color: #ffffff; border-bottom: 1px solid #eee; }
                .logo { max-width: 180px; height: auto; }
                .event-details { background-color: #f8f9fa; padding: 25px 20px; margin-bottom: 10px; }
                .event-details h2 { margin: 0 0 15px 0; color: #6c5ce7; font-size: 18px; }
                .event-details .detail-row { margin-bottom: 8px; }
                .event-details .detail-row strong { color: #495057; display: inline-block; min-width: 120px; }
                .gift-details { background-color: white; padding: 25px; margin-bottom: 20px; border-left: 4px solid #6c5ce7; }
                .gift-details h2 { margin: 0 0 20px 0; color: #6c5ce7; font-size: 18px; }
                .gift-content { display: flex; gap: 20px; }
                .gift-image { flex-shrink: 0; }
                .gift-image img { width: 120px; height: 120px; object-fit: cover; border-radius: 8px; border: 2px solid #e9ecef; }
                .gift-info { flex-grow: 1; }
                .gift-info h3 { margin: 0 0 15px 0; font-size: 20px; color: #333; }
                .gift-info .detail-row { margin-bottom: 8px; }
                .gift-info .detail-row strong { color: #495057; width: 120px; display: inline-block; }
                .gift-info a { color: #007cba; text-decoration: none; font-weight: 600; }
                .gift-info a:hover { text-decoration: underline; }
                .reserver-info { background-color: #fff3cd; padding: 15px; margin-top: 20px; border-radius: 5px; border-left: 4px solid #ffc107; }
                .reserver-info strong { color: #856404; }
                .recommendations { background-color: #f8f9fa; padding: 30px; margin-bottom: 20px; border-top: 4px solid #28a745; }
                .recommendations h2 { color: #28a745; font-size: 22px; margin: 0 0 15px 0; text-align: center; }
                .recommendations p { text-align: center; margin: 0 0 25px 0; font-style: italic; color: #666; }
                .product-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; }
                .product-item { background: white; border-radius: 10px; border: 1px solid #ddd; text-align: center; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
                .product-item img { width: 100%; height: 140px; object-fit: cover; display: block; }
                .product-content { padding: 15px; }
                .product-item h4 { margin: 0 0 10px 0; font-size: 16px; color: #333; font-weight: 600; line-height: 1.4; }
                .product-item .product-price { color: #28a745; font-weight: bold; font-size: 15px; margin: 10px 0; }
                .product-item .view-product-button { display: inline-block; background: #007cba; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: 500; font-size: 14px; }
                .product-item .view-product-button:hover { background: #005a87; }
                .search-link-section { text-align: center; padding: 30px 20px; background-color: #f8f9fa; }
                .search-link { display: inline-block; background-color: #007cba; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; }
                .search-link:hover { background-color: #005a87; }
                .footer { text-align: center; padding: 30px 20px 40px; background-color: white; border-top: 1px solid #eee; }
                .footer p { margin: 5px 0; color: #6c757d; font-size: 12px; }
                @media only screen and (max-width: 600px) {
                    .container { width: 100%; }
                    .gift-content { flex-direction: column; text-align: center; }
                    .product-grid { grid-template-columns: 1fr; gap: 20px; }
                    .product-item { margin-bottom: 20px; }
                    .container { padding: 0; }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <!-- Site Logo Section -->
                <div class="logo-section">
                    <?php
                    $logo_url = get_custom_logo() ? wp_get_attachment_image_url(get_theme_mod('custom_logo'), 'full') : '';
                    if ($logo_url) {
                        ?>
                        <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" class="logo">
                        <?php
                    } else {
                        ?>
                        <img src="<?php echo esc_url(get_site_icon_url()); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" class="logo" style="max-width: 64px;">
                        <?php
                    }
                    ?>
                </div>

                <!-- Event Details Section -->
                <?php if ($wishlist && !empty($wishlist->title)) : ?>
                <div class="event-details">
                    <h2><?php _e('Event Details', 'my-gift-registry'); ?></h2>
                    <div class="detail-row">
                        <strong><?php _e('Event Title:', 'my-gift-registry'); ?></strong>
                        <span><?php echo esc_html($wishlist->title); ?></span>
                    </div>
                    <?php if (!empty($wishlist->event_type)) : ?>
                    <div class="detail-row">
                        <strong><?php _e('Event Type:', 'my-gift-registry'); ?></strong>
                        <span><?php echo esc_html($this->get_event_type_label($wishlist->event_type)); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($wishlist->event_date)) : ?>
                    <div class="detail-row">
                        <strong><?php _e('Event Date:', 'my-gift-registry'); ?></strong>
                        <span><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($wishlist->event_date))); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($wishlist->full_name)) : ?>
                    <div class="detail-row">
                        <strong><?php _e('Created by:', 'my-gift-registry'); ?></strong>
                        <span><?php echo esc_html($wishlist->full_name); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($wishlist->description)) : ?>
                    <div class="detail-row">
                        <strong><?php _e('Description:', 'my-gift-registry'); ?></strong>
                        <span><?php echo esc_html($wishlist->description); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Reserved Gift Details Section -->
                <div class="gift-details">
                    <h2><?php _e('Reserved Gift Details', 'my-gift-registry'); ?></h2>
                    <div class="gift-content">
                        <!-- Gift Image -->
                        <div class="gift-image">
                            <?php
                            // Try to get gift image from WooCommerce product if available
                            $gift_image_url = '';
                            if (function_exists('wc_get_product') && !empty($gift->product_id)) {
                                $product = wc_get_product($gift->product_id);
                                if ($product && $product->get_image_id()) {
                                    $gift_image_url = wp_get_attachment_image_url($product->get_image_id(), 'medium');
                                }
                            }
                            if (!$gift_image_url && !empty($gift->image_url)) {
                                $gift_image_url = $gift->image_url;
                            }
                            if (!$gift_image_url) {
                                $gift_image_url = 'https://via.placeholder.com/120x120?text=No+Image';
                            }
                            ?>
                            <img src="<?php echo esc_url($gift_image_url); ?>" alt="<?php echo esc_attr($gift->title); ?>" style="width: 120px; height: 120px; object-fit: cover; border-radius: 8px;">
                        </div>

                        <!-- Gift Information -->
                        <div class="gift-info">
                            <h3><?php echo esc_html($gift->title); ?></h3>

                            <?php if (!empty($gift->description)) : ?>
                            <div class="detail-row">
                                <strong><?php _e('Description:', 'my-gift-registry'); ?></strong>
                                <span><?php echo esc_html($gift->description); ?></span>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($gift->price)) : ?>
                            <div class="detail-row">
                                <strong><?php _e('Price:', 'my-gift-registry'); ?></strong>
                                <span><?php echo wc_price($gift->price); ?></span>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($gift->product_url)) : ?>
                            <div class="detail-row">
                                <strong><?php _e('Product Link:', 'my-gift-registry'); ?></strong>
                                <a href="<?php echo esc_url($gift->product_url); ?>" target="_blank"><?php _e('View Product', 'my-gift-registry'); ?></a>
                            </div>
                            <?php endif; ?>

                            <!-- Reservation Information -->
                            <div class="reserver-info">
                                <div class="detail-row">
                                    <strong><?php _e('Reserved by:', 'my-gift-registry'); ?></strong>
                                    <span><?php echo esc_html($email); ?></span>
                                </div>
                                <div class="detail-row">
                                    <strong><?php _e('Reserved on:', 'my-gift-registry'); ?></strong>
                                    <span><?php echo current_time(get_option('date_format') . ' ' . get_option('time_format')); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php echo $this->get_recommended_products_section_enhanced($this->currentWishlist); ?>

                <!-- Search Link Section -->
                <div class="search-link-section">
                    <?php
                    $search_term = str_replace(' ', '+', $gift->title);
                    $search_url = home_url('/?post_type=product&s=' . urlencode($search_term));
                    ?>
                    <a href="<?php echo esc_url($search_url); ?>" class="search-link">
                        <?php _e('🔍 Find Similar Products', 'my-gift-registry'); ?>
                    </a>
                </div>

                <!-- Footer -->
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
     * Get recommended products section for email (enhanced version)
     *
     * @param object $wishlist
     * @return string
     */
    private function get_recommended_products_section_enhanced($wishlist) {
        if (!$wishlist || empty($wishlist->event_type)) {
            return ''; // No recommended products section if no event type
        }

        $db_handler = new My_Gift_Registry_DB_Handler();
        $recommended_product_ids = $db_handler->get_recommended_products($wishlist->event_type);

        if (empty($recommended_product_ids)) {
            return ''; // No recommended products section if no products
        }

        $recommended_products = $db_handler->get_recommended_products_details($recommended_product_ids);

        if (empty($recommended_products)) {
            return ''; // No recommended products section if products not found
        }

        ob_start();
        ?>
        <div class="recommendations">
            <h2><?php _e('🎁 Recommended Products', 'my-gift-registry'); ?></h2>
            <p><?php printf(__('Based on your interest in this %s event, here are some suggested gift ideas:', 'my-gift-registry'), esc_html($this->get_event_type_label($wishlist->event_type))); ?></p>
            <div class="product-grid">
                <?php foreach ($recommended_products as $product) : ?>
                    <div class="product-item">
                        <?php
                        $product_image_url = !empty($product['image_url']) ? $product['image_url'] : 'https://via.placeholder.com/200x140?text=No+Image';
                        ?>
                        <img src="<?php echo esc_url($product_image_url); ?>" alt="<?php echo esc_attr($product['title']); ?>" class="product-image">
                        <div class="product-content">
                            <h4><?php echo esc_html($product['title']); ?></h4>
                            <div class="product-price"><?php echo $product['price_html']; ?></div>
                            <a href="<?php echo esc_url($product['permalink']); ?>" target="_blank" class="view-product-button">
                                <?php _e('View Product', 'my-gift-registry'); ?> →
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get featured products section (legacy fallback)
     *
     * @return string
     */
    private function get_featured_products_section_legacy() {
        $db_handler = new My_Gift_Registry_DB_Handler();
        $featured_products = $db_handler->get_featured_woocommerce_products(4);

        if (empty($featured_products)) {
            return '';
        }

        ob_start();
        ?>
        <div class="featured-products">
            <div class="section-header">
                <h3><?php _e('✨ You might also like these featured products', 'my-gift-registry'); ?></h3>
            </div>
            <div class="product-grid">
                <?php foreach ($featured_products as $product) : ?>
                    <div class="product-item">
                        <?php if (!empty($product['image_url'])) : ?>
                            <img src="<?php echo esc_url($product['image_url']); ?>" alt="<?php echo esc_attr($product['title']); ?>" style="max-width: 100%; height: auto;">
                        <?php endif; ?>
                        <h4><?php echo esc_html($product['title']); ?></h4>
                        <p><?php echo $product['price']; ?></p>
                        <a href="<?php echo esc_url($product['url']); ?>" target="_blank">
                            <?php _e('View Product', 'my-gift-registry'); ?> →
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get event type label
     *
     * @param string $event_type
     * @return string
     */
    private function get_event_type_label($event_type) {
        $labels = array(
            // New format (slugs)
            'wedding' => __('Wedding', 'my-gift-registry'),
            'anniversary' => __('Anniversary', 'my-gift-registry'),
            'birthday' => __('Birthday', 'my-gift-registry'),
            'baby-shower' => __('Baby Shower', 'my-gift-registry'),
            'kitchen-party' => __('Kitchen Party', 'my-gift-registry'),
            'graduation' => __('Graduation', 'my-gift-registry'),
            'housewarming' => __('Housewarming', 'my-gift-registry'),
            'retirement' => __('Retirement', 'my-gift-registry'),

            // Legacy format (old capitalized names for backward compatibility)
            'Wedding' => __('Wedding', 'my-gift-registry'),
            'Anniversary' => __('Anniversary', 'my-gift-registry'),
            'Birthday' => __('Birthday', 'my-gift-registry'),
            'Kitchen Party' => __('Kitchen Party', 'my-gift-registry'),
            'Baby Shower' => __('Baby Shower', 'my-gift-registry'),
        );

        return isset($labels[$event_type]) ? $labels[$event_type] : ucfirst($event_type);
    }

    /**
     * Get featured products section for email (legacy method for backward compatibility)
     *
     * @param object $gift (deprecated parameter, kept for backwards compatibility)
     * @return string
     */
    private function get_featured_products_section($gift) {
        return $this->get_featured_products_section_legacy();
    }
}