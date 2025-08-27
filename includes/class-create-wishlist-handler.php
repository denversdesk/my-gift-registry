<?php
/**
 * Create Wishlist Form Handler for My Gift Registry plugin
 *
 * Handles the [create_wishlist_form] shortcode
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class My_Gift_Registry_Create_Wishlist_Handler {

    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('create_wishlist_form', array($this, 'render_create_wishlist_form'));
    }

    /**
     * Render the create wishlist form shortcode
     *
     * @param array $atts
     * @return string
     */
    public function render_create_wishlist_form($atts) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return $this->render_login_required_message();
        }

        // Render the form
        return $this->render_wishlist_form();
    }

    /**
     * Render login required message
     *
     * @return string
     */
    private function render_login_required_message() {
        ob_start();
        ?>
        <div class="my-gift-registry-login-required">
            <div class="login-message">
                <h3><?php _e('Create Your Wishlist', 'my-gift-registry'); ?></h3>
                <p><?php _e('You must be logged in to create a wishlist.', 'my-gift-registry'); ?></p>
                <a href="<?php echo wp_login_url(get_permalink()); ?>" class="login-button">
                    <?php _e('Log In', 'my-gift-registry'); ?>
                </a>
                <p><?php _e('Don\'t have an account?', 'my-gift-registry'); ?>
                   <a href="<?php echo wp_registration_url(); ?>"><?php _e('Register here', 'my-gift-registry'); ?></a>
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the wishlist creation form
     *
     * @return string
     */
    private function render_wishlist_form() {
        $current_user = wp_get_current_user();

        ob_start();
        ?>
        <div class="my-gift-registry-create-form">
            <div class="form-header">
                <h2><?php _e('Create Your Gift Registry', 'my-gift-registry'); ?></h2>
                <p><?php _e('Fill out the form below to create your personalized gift registry.', 'my-gift-registry'); ?></p>
            </div>

            <form id="create-wishlist-form" class="wishlist-form" method="post">
                <?php wp_nonce_field('my_gift_registry_nonce', 'wishlist_nonce'); ?>

                <div class="form-section">
                    <h3><?php _e('Event Details', 'my-gift-registry'); ?></h3>

                    <div class="form-group">
                        <label for="event_type"><?php _e('Event Type', 'my-gift-registry'); ?> *</label>
                        <select id="event_type" name="event_type" required>
                            <option value=""><?php _e('Select Event Type', 'my-gift-registry'); ?></option>
                            <option value="Wedding"><?php _e('Wedding', 'my-gift-registry'); ?></option>
                            <option value="Anniversary"><?php _e('Anniversary', 'my-gift-registry'); ?></option>
                            <option value="Birthday"><?php _e('Birthday', 'my-gift-registry'); ?></option>
                            <option value="Kitchen Party"><?php _e('Kitchen Party', 'my-gift-registry'); ?></option>
                            <option value="Baby Shower"><?php _e('Baby Shower', 'my-gift-registry'); ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="event_title"><?php _e('Event Title', 'my-gift-registry'); ?> *</label>
                        <input type="text" id="event_title" name="event_title"
                               placeholder="<?php _e('Jessica\'s Bridal Shower', 'my-gift-registry'); ?>" required>
                        <small><?php _e('This will be the title of your gift registry.', 'my-gift-registry'); ?></small>
                    </div>

                    <div class="form-group">
                        <label for="wishlist_slug"><?php _e('URL Slug', 'my-gift-registry'); ?> *</label>
                        <input type="text" id="wishlist_slug" name="wishlist_slug" required readonly>
                        <small><?php _e('This will be part of your registry URL. Auto-generated from title.', 'my-gift-registry'); ?></small>
                        <div id="slug-status" class="slug-status"></div>
                    </div>

                    <div class="url-preview">
                        <strong><?php _e('Your registry URL will be:', 'my-gift-registry'); ?></strong><br>
                        <span id="url-preview"><?php echo home_url('/wishlist/'); ?><span id="slug-preview"></span></span>
                    </div>

                    <div class="form-group">
                        <label for="event_date"><?php _e('Event Date', 'my-gift-registry'); ?></label>
                        <input type="date" id="event_date" name="event_date">
                        <div id="event-date-preview" class="date-preview" style="display: none;">
                            <small><strong><?php _e('Preview:', 'my-gift-registry'); ?></strong> <span id="formatted-date"></span></small>
                        </div>
                        <small><?php _e('When is your event taking place?', 'my-gift-registry'); ?></small>
                    </div>

                    <div class="form-group">
                        <label for="description"><?php _e('Description', 'my-gift-registry'); ?></label>
                        <textarea id="description" name="description" rows="4"
                                  placeholder="<?php _e('Add a personal message about your event...', 'my-gift-registry'); ?>"></textarea>
                        <small><?php _e('Share details about your event or special requests.', 'my-gift-registry'); ?></small>
                    </div>
                </div>

                <div class="form-section">
                    <h3><?php _e('Your Information', 'my-gift-registry'); ?></h3>

                    <div class="form-group">
                        <label for="full_name"><?php _e('Full Name', 'my-gift-registry'); ?> *</label>
                        <input type="text" id="full_name" name="full_name"
                               value="<?php echo esc_attr($current_user->display_name); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="profile_pic"><?php _e('Profile Picture', 'my-gift-registry'); ?></label>
                        <div class="profile-pic-upload">
                            <input type="hidden" id="profile_pic" name="profile_pic">
                            <div class="profile-pic-preview" id="profile-pic-preview">
                                <img id="profile-pic-image" src="" alt="" style="display: none; max-width: 150px; height: auto; border-radius: 8px;">
                                <div class="no-image-placeholder" id="no-image-placeholder">
                                    <div style="font-size: 48px; color: #ddd; margin-bottom: 10px;">📷</div>
                                    <div><?php _e('No image selected', 'my-gift-registry'); ?></div>
                                </div>
                            </div>
                            <div class="profile-pic-buttons">
                                <button type="button" id="upload-profile-pic" class="upload-button">
                                    <?php _e('Choose Image', 'my-gift-registry'); ?>
                                </button>
                                <button type="button" id="remove-profile-pic" class="remove-button" style="display: none;">
                                    <?php _e('Remove', 'my-gift-registry'); ?>
                                </button>
                            </div>
                        </div>
                        <small><?php _e('Add a photo of yourself or a couple photo for your registry.', 'my-gift-registry'); ?></small>
                    </div>

                    <div class="form-group">
                        <label for="email"><?php _e('Email Address', 'my-gift-registry'); ?> *</label>
                        <input type="email" id="email" name="email"
                               value="<?php echo esc_attr($current_user->user_email); ?>" required>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" id="submit-wishlist" class="submit-button" disabled>
                        <?php _e('Create Wish List', 'my-gift-registry'); ?>
                    </button>
                    <div id="form-status" class="form-status"></div>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}