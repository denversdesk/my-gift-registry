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
                .featured-products { background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
                .product-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
                .product-item { background: white; padding: 15px; border-radius: 5px; border: 1px solid #ddd; }
                .product-item h4 { margin-top: 0; }
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

                <?php echo $this->get_featured_products_section($gift); ?>

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
     * Get featured products section for email
     *
     * @param object $gift
     * @return string
     */
    private function get_featured_products_section($gift) {
        $db_handler = new My_Gift_Registry_DB_Handler();
        $featured_products = $db_handler->get_featured_woocommerce_products(4);

        if (empty($featured_products)) {
            return '';
        }

        ob_start();
        ?>
        <div class="featured-products">
            <h3><?php _e('✨ You might also like these featured products', 'my-gift-registry'); ?></h3>
            <div class="product-grid">
                <?php foreach ($featured_products as $product) : ?>
                    <div class="product-item">
                        <?php if (!empty($product['image'])) : ?>
                            <img src="<?php echo esc_url($product['image']); ?>" alt="<?php echo esc_attr($product['title']); ?>" style="max-width: 100%; height: auto;">
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
}