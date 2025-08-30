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

        // Prepare email content
        $message = $this->get_reservation_email_content($email, $gift);

        // Get wishlist details for recommended products
        $db_handler = new My_Gift_Registry_DB_Handler();
        $wishlist = $db_handler->get_wishlist_by_slug($gift->wishlist_slug);

        // Store wishlist info for use in email content generation
        $this->currentWishlist = $wishlist;

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
     * @return string
     */
    private function get_reservation_email_content($email, $gift) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php _e('Gift Reservation Confirmation', 'my-gift-registry'); ?></title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #f8f9fa; padding: 20px; text-align: center; border-radius: 5px; margin-bottom: 20px; }
                .gift-details { background-color: #fff; border: 1px solid #ddd; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
                .recommendations { background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; border: 2px solid #28a745; }
                .recommendations .section-header { text-align: center; margin-bottom: 20px; color: #28a745; }
                .recommendations h3 { color: #28a745; font-size: 20px; margin: 0 0 10px 0; }
                .recommendations .section-header p { margin: 0; font-style: italic; color: #666; }
                .featured-products { background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; border: 2px solid #ffc107; }
                .featured-products .section-header { text-align: center; margin-bottom: 20px; color: #ffc107; }
                .product-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
                .product-item { background: white; padding: 15px; border-radius: 5px; border: 1px solid #ddd; text-align: center; }
                .product-item img { max-width: 100%; height: 150px; object-fit: cover; border-radius: 3px; margin-bottom: 10px; }
                .product-item h4 { margin-top: 0; font-size: 16px; color: #333; }
                .product-item .product-price { color: #28a745; font-weight: bold; margin: 5px 0; }
                .product-item a { color: #007cba; text-decoration: none; font-weight: 500; }
                .product-item .view-product-button { display: inline-block; background: #007cba; color: white; padding: 8px 15px; border-radius: 3px; text-decoration: none; font-size: 14px; margin-top: 10px; }
                .product-item .view-product-button:hover { background: #005a87; }
                .search-link { display: inline-block; background-color: #007cba; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                .footer { text-align: center; font-size: 12px; color: #666; margin-top: 30px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php _e('🎉 Gift Reserved Successfully!', 'my-gift-registry'); ?></h1>
                    <p><?php _e('Thank you for reserving a gift from', 'my-gift-registry'); ?> <?php echo esc_html($gift->wishlist_title); ?></p>
                </div>

                <div class="gift-details">
                    <h2><?php _e('Reserved Gift Details', 'my-gift-registry'); ?></h2>
                    <h3><?php echo esc_html($gift->title); ?></h3>

                    <?php if (!empty($gift->description)) : ?>
                        <p><strong><?php _e('Description:', 'my-gift-registry'); ?></strong> <?php echo esc_html($gift->description); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($gift->price)) : ?>
                        <p><strong><?php _e('Estimated Price:', 'my-gift-registry'); ?></strong> <?php echo wc_price($gift->price); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($gift->product_url)) : ?>
                        <p><strong><?php _e('Product Link:', 'my-gift-registry'); ?></strong>
                           <a href="<?php echo esc_url($gift->product_url); ?>" target="_blank"><?php _e('View Product', 'my-gift-registry'); ?></a>
                        </p>
                    <?php endif; ?>

                    <p><strong><?php _e('Reserved by:', 'my-gift-registry'); ?></strong> <?php echo esc_html($email); ?></p>
                    <p><strong><?php _e('Reservation Date:', 'my-gift-registry'); ?></strong> <?php echo current_time(get_option('date_format') . ' ' . get_option('time_format')); ?></p>
                </div>

                <?php echo $this->get_recommended_products_section($this->currentWishlist); ?>

                <div class="search-link-section">
                    <?php
                    $search_term = str_replace(' ', '+', $gift->title);
                    $search_url = home_url('/?post_type=product&s=' . urlencode($search_term));
                    ?>
                    <a href="<?php echo esc_url($search_url); ?>" class="search-link">
                        <?php _e('🔍 Find Similar Products', 'my-gift-registry'); ?>
                    </a>
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
     * Get recommended products section for email
     *
     * @param object $wishlist
     * @return string
     */
    private function get_recommended_products_section($wishlist) {
        if (!$wishlist || empty($wishlist->event_type)) {
            // Fallback to featured products if no event type
            return $this->get_featured_products_section_legacy();
        }

        $db_handler = new My_Gift_Registry_DB_Handler();
        $recommended_product_ids = $db_handler->get_recommended_products($wishlist->event_type);

        if (empty($recommended_product_ids)) {
            // Fallback to featured products if no recommendations set
            return $this->get_featured_products_section_legacy();
        }

        $recommended_products = $db_handler->get_recommended_products_details($recommended_product_ids);

        if (empty($recommended_products)) {
            // Fallback to featured products if recommended products not found
            return $this->get_featured_products_section_legacy();
        }

        ob_start();
        ?>
        <div class="recommendations">
            <div class="section-header">
                <h3><?php _e('🎁 Our Recommended Gift Ideas', 'my-gift-registry'); ?></h3>
                <p><?php printf(__('Based on your interest in this %s gift registry, here are some personalized recommendations:', 'my-gift-registry'), esc_html($this->get_event_type_label($wishlist->event_type))); ?></p>
            </div>
            <div class="product-grid">
                <?php foreach ($recommended_products as $product) : ?>
                    <div class="product-item">
                        <?php if (!empty($product['image_url'])) : ?>
                            <img src="<?php echo esc_url($product['image_url']); ?>" alt="<?php echo esc_attr($product['title']); ?>" style="max-width: 100%; height: auto;">
                        <?php endif; ?>
                        <h4><?php echo esc_html($product['title']); ?></h4>
                        <div class="product-price"><?php echo $product['price_html']; ?></div>
                        <a href="<?php echo esc_url($product['permalink']); ?>" target="_blank" class="view-product-button">
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