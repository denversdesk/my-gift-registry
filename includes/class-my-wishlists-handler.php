<?php
/**
 * My Wishlists Handler for My Gift Registry plugin
 *
 * Handles the [my_wishlists] shortcode
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class My_Gift_Registry_My_Wishlists_Handler {

    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('my_wishlists', array($this, 'render_my_wishlists'));
    }

    /**
     * Render the my wishlists shortcode
     *
     * @param array $atts
     * @return string
     */
    public function render_my_wishlists($atts) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return $this->render_login_required_message();
        }

        // Handle different actions (edit, delete)
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $wishlist_id = isset($_GET['wishlist_id']) ? intval($_GET['wishlist_id']) : 0;

        switch ($action) {
            case 'edit':
                return $this->render_edit_form($wishlist_id);
            case 'delete':
                return $this->render_delete_confirmation($wishlist_id);
            default:
                return $this->render_wishlists_table();
        }
    }

    /**
     * Render login required message
     *
     * @return string
     */
    private function render_login_required_message() {
        // Clear any output buffers and remove filters that might interfere
        if (ob_get_level()) {
            ob_clean();
        }

        // Remove any filters that might wrap content in <code> tags
        remove_filter('the_content', 'wpautop');
        remove_filter('the_content', 'wptexturize');

        ob_start();
        ?>
        <div class="my-gift-registry-login-required">
            <div class="login-message">
                <h3><?php _e('My Wish Lists', 'my-gift-registry'); ?></h3>
                <p><?php _e('You must be logged in to view your wishlists.', 'my-gift-registry'); ?></p>
                <a href="<?php echo get_permalink( get_option('woocommerce_myaccount_page_id') ); ?>?redirect_to=<?php echo get_permalink(); ?>" class="login-button">
                    <?php _e('Log In', 'my-gift-registry'); ?>
                </a>
                <p><?php _e('Don\'t have an account?', 'my-gift-registry'); ?>
                   <a href="<?php echo get_permalink( get_option('woocommerce_myaccount_page_id') ); ?>?redirect_to=<?php echo get_permalink(); ?>"><?php _e('Register here', 'my-gift-registry'); ?></a>
                </p>
            </div>
        </div>
        <?php
        $output = ob_get_clean();

        // Restore the filters we removed
        add_filter('the_content', 'wpautop');
        add_filter('the_content', 'wptexturize');

        return $output;
    }

    /**
     * Render the wishlists table
     *
     * @return string
     */
    private function render_wishlists_table() {
        $current_user = wp_get_current_user();
        $db_handler = new My_Gift_Registry_DB_Handler();
        $wishlists = $db_handler->get_user_wishlists($current_user->ID);

        // Clear any output buffers and remove filters that might interfere
        if (ob_get_level()) {
            ob_clean();
        }

        // Remove any filters that might wrap content in <code> tags
        remove_filter('the_content', 'wpautop');
        remove_filter('the_content', 'wptexturize');

        ob_start();
        ?>
        <div class="my-gift-registry-my-wishlists">
            <div class="page-header">
                <h2><?php _e('My Wish Lists', 'my-gift-registry'); ?></h2>
                <p><?php _e('Manage your gift registries below.', 'my-gift-registry'); ?></p>
                <a href="<?php echo home_url('/create-wishlist'); ?>" class="create-new-button">
                    <?php _e('+ Create New Wishlist', 'my-gift-registry'); ?>
                </a>
            </div>

            <?php if (empty($wishlists)) : ?>
                <div class="no-wishlists">
                    <h3><?php _e('No wishlists found', 'my-gift-registry'); ?></h3>
                    <p><?php _e('You haven\'t created any wishlists yet.', 'my-gift-registry'); ?></p>
                    <a href="<?php echo home_url('/create-wishlist'); ?>" class="create-first-button">
                        <?php _e('Create Your First Wishlist', 'my-gift-registry'); ?>
                    </a>
                </div>
            <?php else : ?>
                <div class="wishlists-table-container">
                    <table class="wishlists-table">
                        <thead>
                            <tr>
                                <th><?php _e('Event Title', 'my-gift-registry'); ?></th>
                                <th><?php _e('Event Type', 'my-gift-registry'); ?></th>
                                <th><?php _e('Event Date', 'my-gift-registry'); ?></th>
                                <th><?php _e('Date Created', 'my-gift-registry'); ?></th>
                                <th><?php _e('Actions', 'my-gift-registry'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($wishlists as $wishlist) : ?>
                                <tr>
                                    <td>
                                        <div class="wishlist-title-cell">
                                            <strong>
                                                <a href="<?php echo home_url('/wishlist/' . $wishlist->slug); ?>" target="_blank">
                                                    <?php echo esc_html(stripslashes($wishlist->title)); ?>
                                                </a>
                                            </strong>
                                            <small class="wishlist-url">
                                                <?php echo home_url('/wishlist/' . $wishlist->slug); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html($wishlist->event_type); ?></td>
                                    <td><?php echo ($wishlist->event_date ? esc_html(date_i18n(get_option('date_format'), strtotime($wishlist->event_date))) : '-'); ?></td>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($wishlist->created_at))); ?></td>
                                    <td>
                                        <div class="wishlist-actions">
                                            <a href="<?php echo add_query_arg(array('action' => 'edit', 'wishlist_id' => $wishlist->id)); ?>"
                                               class="btn btn-sm btn-success edit-button">
                                                <?php _e('Edit', 'my-gift-registry'); ?>
                                            </a>
                                            <button class="btn btn-sm btn-danger delete-button" data-wishlist-id="<?php echo $wishlist->id; ?>"
                                                    data-wishlist-title="<?php echo esc_attr(stripslashes($wishlist->title)); ?>">
                                                <?php _e('Delete', 'my-gift-registry'); ?>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Delete Confirmation Modal -->
            <div id="delete-modal" class="delete-modal" style="display: none;">
                <div class="modal-overlay"></div>
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><?php _e('Delete Wishlist', 'my-gift-registry'); ?></h3>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p><?php _e('Are you sure you want to delete this wishlist?', 'my-gift-registry'); ?></p>
                        <p class="wishlist-title-confirm"></p>
                        <p class="warning-text">
                            <strong><?php _e('This action is permanent and CANNOT be undone.', 'my-gift-registry'); ?></strong>
                        </p>
                        <div class="modal-actions">
                            <button class="cancel-delete"><?php _e('Cancel', 'my-gift-registry'); ?></button>
                            <button class="confirm-delete"><?php _e('Delete Wishlist', 'my-gift-registry'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        $output = ob_get_clean();

        // Restore the filters we removed
        add_filter('the_content', 'wpautop');
        add_filter('the_content', 'wptexturize');

        return $output;
    }

    /**
     * Render the edit form
     *
     * @param int $wishlist_id
     * @return string
     */
    private function render_edit_form($wishlist_id) {
        $current_user = wp_get_current_user();
        $db_handler = new My_Gift_Registry_DB_Handler();
        $wishlist = $db_handler->get_user_wishlist($wishlist_id, $current_user->ID);

        if (!$wishlist) {
            return '<div class="error-message">' . __('Wishlist not found or access denied.', 'my-gift-registry') . '</div>';
        }

        // Get active event types from database
        $active_event_types = $db_handler->get_active_event_types();

        // Clear any output buffers and remove filters that might interfere
        if (ob_get_level()) {
            ob_clean();
        }

        // Remove any filters that might wrap content in <code> tags
        remove_filter('the_content', 'wpautop');
        remove_filter('the_content', 'wptexturize');

        ob_start();
        ?>
        <div class="my-gift-registry-edit-form">
            <div class="form-header">
                <h2><?php _e('Edit Wishlist', 'my-gift-registry'); ?></h2>
                <p><?php _e('Update your wishlist details below.', 'my-gift-registry'); ?></p>
            </div>

            <form id="edit-wishlist-form" class="wishlist-form" method="post">
                <?php wp_nonce_field('my_gift_registry_nonce', 'wishlist_nonce'); ?>
                <input type="hidden" name="wishlist_id" value="<?php echo $wishlist->id; ?>">

                <div class="form-section">
                    <h3><?php _e('Event Details', 'my-gift-registry'); ?></h3>

                    <div class="form-group">
                        <label for="event_type"><?php _e('Event Type', 'my-gift-registry'); ?> *</label>
                        <select id="event_type" name="event_type" required>
                            <option value=""><?php _e('Select Event Type', 'my-gift-registry'); ?></option>
                            <?php foreach ($active_event_types as $event_type): ?>
                                <option value="<?php echo esc_attr($event_type->slug); ?>" <?php selected($wishlist->event_type, $event_type->slug); ?>><?php echo esc_html($event_type->name); ?></option>
                            <?php endforeach; ?>

                            <?php if (empty($active_event_types)): ?>
                                <!-- Fallback options if no event types are available -->
                                <option value="wedding" <?php selected($wishlist->event_type, 'wedding'); ?>><?php _e('Wedding', 'my-gift-registry'); ?></option>
                                <option value="birthday" <?php selected($wishlist->event_type, 'birthday'); ?>><?php _e('Birthday', 'my-gift-registry'); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="event_title"><?php _e('Event Title', 'my-gift-registry'); ?> *</label>
                        <input type="text" id="event_title" name="event_title"
                               value="<?php echo esc_attr(stripslashes($wishlist->title)); ?>" required>
                        <small><?php _e('This will be the title of your gift registry.', 'my-gift-registry'); ?></small>
                    </div>

                    <div class="form-group">
                        <label for="wishlist_slug"><?php _e('URL Slug', 'my-gift-registry'); ?> *</label>
                        <input type="text" id="wishlist_slug" name="wishlist_slug"
                               value="<?php echo esc_attr($wishlist->slug); ?>" required readonly>
                        <small><?php _e('This will be part of your registry URL. Auto-generated from title.', 'my-gift-registry'); ?></small>
                        <div id="slug-status" class="slug-status"></div>
                    </div>

                    <div class="url-preview">
                        <strong><?php _e('Your registry URL will be:', 'my-gift-registry'); ?></strong><br>
                        <span id="url-preview"><?php echo home_url('/wishlist/'); ?><span id="slug-preview"><?php echo esc_html($wishlist->slug); ?></span></span>
                    </div>

                    <div class="form-group">
                        <label for="event_date"><?php _e('Event Date', 'my-gift-registry'); ?></label>
                        <input type="date" id="event_date" name="event_date"
                               value="<?php echo esc_attr($wishlist->event_date); ?>">
                        <div id="event-date-preview" class="date-preview" style="display: none;">
                            <small><strong><?php _e('Preview:', 'my-gift-registry'); ?></strong> <span id="formatted-date"></span></small>
                        </div>
                        <small><?php _e('When is your event taking place?', 'my-gift-registry'); ?></small>
                    </div>

                    <div class="form-group">
                        <label for="description"><?php _e('Description', 'my-gift-registry'); ?></label>
                        <textarea id="description" name="description" rows="4"><?php echo esc_textarea(stripslashes($wishlist->description)); ?></textarea>
                        <small><?php _e('Share details about your event or special requests.', 'my-gift-registry'); ?></small>
                    </div>
                </div>

                <div class="form-section">
                    <h3><?php _e('Your Information', 'my-gift-registry'); ?></h3>

                    <div class="form-group">
                        <label for="full_name"><?php _e('Full Name', 'my-gift-registry'); ?> *</label>
                        <input type="text" id="full_name" name="full_name"
                               value="<?php echo esc_attr($wishlist->full_name); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="profile_pic"><?php _e('Profile Picture', 'my-gift-registry'); ?></label>
                        <div class="profile-pic-upload">
                            <input type="hidden" id="profile_pic" name="profile_pic" value="<?php echo esc_attr($wishlist->profile_pic); ?>">
                            <div class="profile-pic-preview" id="profile-pic-preview">
                                <img id="profile-pic-image" src="<?php echo esc_url($wishlist->profile_pic); ?>" alt=""
                                     style="<?php echo empty($wishlist->profile_pic) ? 'display: none;' : ''; ?> max-width: 150px; height: auto; border-radius: 8px;">
                                <div class="no-image-placeholder" id="no-image-placeholder"
                                     style="<?php echo !empty($wishlist->profile_pic) ? 'display: none;' : ''; ?>">
                                    <div style="font-size: 48px; color: #ddd; margin-bottom: 10px;">📷</div>
                                    <div><?php _e('No image selected', 'my-gift-registry'); ?></div>
                                </div>
                            </div>
                            <div class="profile-pic-buttons">
                                <button type="button" id="upload-profile-pic" class="upload-button">
                                    <?php _e('Choose Image', 'my-gift-registry'); ?>
                                </button>
                                <button type="button" id="remove-profile-pic" class="remove-button" style="<?php echo empty($wishlist->profile_pic) ? 'display: none;' : ''; ?>">
                                    <?php _e('Remove', 'my-gift-registry'); ?>
                                </button>
                            </div>
                        </div>
                        <small><?php _e('Add a photo of yourself or a couple photo for your registry.', 'my-gift-registry'); ?></small>
                    </div>

                    <div class="form-group">
                        <label for="email"><?php _e('Email Address', 'my-gift-registry'); ?> *</label>
                        <input type="email" id="email" name="email"
                               value="<?php echo esc_attr($wishlist->user_email); ?>" required>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="<?php echo remove_query_arg(array('action', 'wishlist_id')); ?>" class="cancel-button">
                        <?php _e('Cancel', 'my-gift-registry'); ?>
                    </a>
                    <button type="submit" id="submit-wishlist" class="submit-button" disabled>
                        <?php _e('Update Wish List', 'my-gift-registry'); ?>
                    </button>
                    <div id="form-status" class="form-status"></div>
                </div>
            </form>
        </div>
        <?php
        $output = ob_get_clean();

        // Restore the filters we removed
        add_filter('the_content', 'wpautop');
        add_filter('the_content', 'wptexturize');

        return $output;
    }

    /**
     * Render delete confirmation (redirects to table with modal)
     *
     * @param int $wishlist_id
     * @return string
     */
    private function render_delete_confirmation($wishlist_id) {
        // This shouldn't be accessed directly - redirect to main page
        wp_redirect(remove_query_arg(array('action', 'wishlist_id')));
        exit;
    }
}