/**
 * My Gift Registry JavaScript
 * Handles frontend interactions for wishlist display and gift reservations
 */

(function($) {
    'use strict';

    class MyGiftRegistry {
        constructor() {
            this.ajaxUrl = myGiftRegistryAjax.ajax_url;
            this.nonce = myGiftRegistryAjax.nonce;
            this.strings = myGiftRegistryAjax.strings;
            this.init();
        }

        init() {
            this.bindEvents();
            this.setupModal();
        }

        bindEvents() {
            // Reserve button clicks
            $(document).on('click', '.reserve-button', this.handleReserveClick.bind(this));

            // Modal close events
            $(document).on('click', '.modal-close, .modal-overlay, .cancel-button', this.closeModal.bind(this));

            // Form submission
            $(document).on('submit', '#reservation-form', this.handleFormSubmit.bind(this));

            // Email validation
            $(document).on('input', '#reservation-email, #reservation-confirm-email', this.validateEmails.bind(this));

            // Share button functionality
            $(document).on('click', '.share-button.copy-link', this.copyLink.bind(this));

            // Prevent modal close when clicking modal content
            $(document).on('click', '.modal-content', function(e) {
                e.stopPropagation();
            });

            // ESC key to close modal
            $(document).on('keydown', this.handleKeyDown.bind(this));
        }

        setupModal() {
            // Create modal if it doesn't exist
            if ($('#reservation-modal').length === 0) {
                const modalHtml = `
                    <div id="reservation-modal" class="reservation-modal" style="display: none;">
                        <div class="modal-overlay"></div>
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2>${this.strings.reserve_gift}</h2>
                                <button class="modal-close">&times;</button>
                            </div>
                            <div class="modal-body">
                                <div id="reservation-gift-info" class="gift-info"></div>
                                <form id="reservation-form" class="reservation-form">
                                    <div class="form-group">
                                        <label for="reservation-email">Email Address *</label>
                                        <input type="email" id="reservation-email" name="email" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="reservation-confirm-email">Confirm Email Address *</label>
                                        <input type="email" id="reservation-confirm-email" name="confirm_email" required>
                                    </div>
                                    <div class="form-actions">
                                        <button type="button" class="cancel-button">Cancel</button>
                                        <button type="submit" class="reserve-submit-button" disabled>
                                            ${this.strings.reserve_gift}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                `;
                $('body').append(modalHtml);
            }
        }

        handleReserveClick(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const giftId = $button.data('gift-id');

            if (!giftId) {
                this.showError('Invalid gift selected.');
                return;
            }

            // Get gift information
            const $giftItem = $button.closest('.gift-item');
            const giftTitle = $giftItem.find('.gift-title').text();
            const giftImage = $giftItem.find('.gift-image img').attr('src');
            const giftDescription = $giftItem.find('.gift-description').text();

            // Populate modal with gift info
            this.populateModal(giftId, giftTitle, giftImage, giftDescription);

            // Show modal
            this.showModal();
        }

        populateModal(giftId, title, image, description) {
            const $giftInfo = $('#reservation-gift-info');
            const $form = $('#reservation-form');

            // Store gift ID in form
            $form.data('gift-id', giftId);

            let html = `<h3>${title}</h3>`;

            if (image) {
                html += `<img src="${image}" alt="${title}">`;
            }

            if (description) {
                html += `<p>${description}</p>`;
            }

            $giftInfo.html(html);
        }

        showModal() {
            $('#reservation-modal').fadeIn(300);
            $('body').addClass('modal-open');

            // Focus first input
            setTimeout(() => {
                $('#reservation-email').focus();
            }, 100);
        }

        closeModal() {
            $('#reservation-modal').fadeOut(300);
            $('body').removeClass('modal-open');

            // Reset form
            setTimeout(() => {
                this.resetForm();
            }, 300);
        }

        resetForm() {
            const $form = $('#reservation-form');
            $form[0].reset();
            $form.removeData('gift-id');
            $('#reservation-gift-info').empty();
            $('.reserve-submit-button').prop('disabled', true);
        }

        validateEmails() {
            const email = $('#reservation-email').val();
            const confirmEmail = $('#reservation-confirm-email').val();
            const $submitButton = $('.reserve-submit-button');

            if (email && confirmEmail && email === confirmEmail && this.isValidEmail(email)) {
                $submitButton.prop('disabled', false);
            } else {
                $submitButton.prop('disabled', true);
            }
        }

        isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        handleFormSubmit(e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const giftId = $form.data('gift-id');
            const email = $('#reservation-email').val();
            const confirmEmail = $('#reservation-confirm-email').val();

            // Additional validation
            if (!giftId || !email || !confirmEmail) {
                this.showError(this.strings.email_required);
                return;
            }

            if (email !== confirmEmail) {
                this.showError(this.strings.emails_mismatch);
                return;
            }

            // Show loading state
            const $submitButton = $('.reserve-submit-button');
            const originalText = $submitButton.text();
            $submitButton.prop('disabled', true).text(this.strings.reserving);

            // Send AJAX request
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'reserve_gift',
                    nonce: this.nonce,
                    gift_id: giftId,
                    email: email,
                    confirm_email: confirmEmail
                },
                success: this.handleReservationSuccess.bind(this),
                error: this.handleReservationError.bind(this),
                complete: () => {
                    // Reset button state
                    $submitButton.prop('disabled', false).text(originalText);
                }
            });
        }

        handleReservationSuccess(response) {
            if (response.success) {
                // Show success message
                this.showSuccess(response.data.message || this.strings.reserved);

                // Update the gift item to show as reserved
                const giftId = $('#reservation-form').data('gift-id');
                const $giftItem = $(`.gift-item[data-gift-id="${giftId}"]`);

                if ($giftItem.length) {
                    $giftItem.addClass('reserved');
                    $giftItem.find('.reserve-button').remove();
                    $giftItem.find('.gift-image').append(`<div class="reserved-badge">Reserved</div>`);
                }

                // Close modal after a delay
                setTimeout(() => {
                    this.closeModal();
                }, 2000);

            } else {
                this.showError(response.data || 'Reservation failed.');
            }
        }

        handleReservationError(xhr, status, error) {
            console.error('Reservation error:', error);
            this.showError('An error occurred while processing your reservation. Please try again.');
        }

        copyLink(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const url = $button.data('url') || window.location.href;

            // Use modern clipboard API if available
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(() => {
                    this.showCopyFeedback($button, 'Copied!');
                });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = url;
                document.body.appendChild(textArea);
                textArea.select();

                try {
                    document.execCommand('copy');
                    this.showCopyFeedback($button, 'Copied!');
                } catch (err) {
                    console.error('Fallback copy failed:', err);
                    // Fallback: open share dialog or show URL
                    window.open(`mailto:?body=${encodeURIComponent(url)}`);
                }

                document.body.removeChild(textArea);
            }
        }

        showCopyFeedback($button, message) {
            const originalText = $button.html();
            $button.html(`<span class="share-icon">✓</span> ${message}`);

            setTimeout(() => {
                $button.html(originalText);
            }, 2000);
        }

        handleKeyDown(e) {
            if (e.keyCode === 27) { // ESC key
                this.closeModal();
            }
        }

        showSuccess(message) {
            this.showMessage(message, 'success');
        }

        showError(message) {
            this.showMessage(message, 'error');
        }

        showMessage(message, type) {
            // Remove existing messages
            $('.my-gift-registry-message').remove();

            const messageClass = type === 'success' ? 'success' : 'error';
            const $message = $(`<div class="my-gift-registry-message ${messageClass}">${message}</div>`);

            // Add styles for the message
            $message.css({
                'position': 'fixed',
                'top': '20px',
                'right': '20px',
                'padding': '15px 20px',
                'border-radius': '8px',
                'color': 'white',
                'font-weight': '500',
                'z-index': '10000',
                'box-shadow': '0 4px 12px rgba(0,0,0,0.3)',
                'max-width': '300px',
                'background': type === 'success' ? '#28a745' : '#dc3545'
            });

            $('body').append($message);

            // Auto remove after 5 seconds
            setTimeout(() => {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    }

    /**
     * Create Wishlist Form Handler
     */
    function CreateWishlistForm() {
        this.ajaxUrl = myGiftRegistryAjax.ajax_url;
        this.nonce = myGiftRegistryAjax.nonce;
        this.init();
    }

    CreateWishlistForm.prototype.init = function() {
        this.bindEvents();
        this.slugTimer = null;
    };

    CreateWishlistForm.prototype.bindEvents = function() {
        var self = this;

        // Real-time slug generation
        $('#event_title').on('input', function() {
            self.generateSlug();
        });

        // Manual slug editing
        $('#wishlist_slug').on('input', function() {
            self.validateSlug();
        });

        // Form submission
        $('#create-wishlist-form').on('submit', function(e) {
            e.preventDefault();
            self.submitForm();
        });

        // Enable/disable submit button based on form validity
        this.updateSubmitButton();
    };

CreateWishlistForm.prototype.generateSlug = function() {
    var title = $('#event_title').val();
    if (title.length === 0) {
        $('#wishlist_slug').val('');
        $('#slug-preview').text('');
        this.updateSubmitButton();
        return;
    }

    // Generate slug from title
    var slug = title.toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '') // Remove special characters
        .replace(/\s+/g, '-') // Replace spaces with hyphens
        .replace(/-+/g, '-') // Replace multiple hyphens with single
        .replace(/^-|-$/g, ''); // Remove leading/trailing hyphens

    $('#wishlist_slug').val(slug);
    $('#slug-preview').text(slug);

    // Validate the generated slug
    this.validateSlug();
};

CreateWishlistForm.prototype.validateSlug = function() {
    var slug = $('#wishlist_slug').val();
    var $status = $('#slug-status');

    if (slug.length === 0) {
        $status.removeClass('success error loading').text('');
        this.updateSubmitButton();
        return;
    }

    // Clear previous timer
    if (this.slugTimer) {
        clearTimeout(this.slugTimer);
    }

    $status.removeClass('success error').addClass('loading').text('Checking availability...');

    // Debounce the validation
    var self = this;
    this.slugTimer = setTimeout(function() {
        self.checkSlugAvailability(slug);
    }, 500);
};

CreateWishlistForm.prototype.checkSlugAvailability = function(slug) {
    var $status = $('#slug-status');
    var self = this;

    $.ajax({
        url: this.ajaxUrl,
        type: 'POST',
        data: {
            action: 'validate_slug',
            nonce: this.nonce,
            slug: slug
        },
        success: function(response) {
            if (response.success) {
                $status.removeClass('error loading').addClass('success').text('✓ Slug is available');
            } else {
                $status.removeClass('success loading').addClass('error').text('✗ ' + response.data.message);

                // If there's a suggested slug, offer to use it
                if (response.data.suggested_slug) {
                    if (confirm('Slug "' + slug + '" is not available. Would you like to use "' + response.data.suggested_slug + '" instead?')) {
                        $('#wishlist_slug').val(response.data.suggested_slug);
                        $('#slug-preview').text(response.data.suggested_slug);
                        self.validateSlug();
                        return;
                    }
                }
            }

            self.updateSubmitButton();
        },
        error: function() {
            $status.removeClass('success loading').addClass('error').text('✗ Error checking availability');
            self.updateSubmitButton();
        }
    });
};

CreateWishlistForm.prototype.updateSubmitButton = function() {
    var isValid = this.isFormValid();
    $('#submit-wishlist').prop('disabled', !isValid);
};

CreateWishlistForm.prototype.isFormValid = function() {
    var eventType = $('#event_type').val();
    var eventTitle = $('#event_title').val();
    var slug = $('#wishlist_slug').val();
    var fullName = $('#full_name').val();
    var email = $('#email').val();

    // Check required fields
    if (!eventType || !eventTitle || !slug || !fullName || !email) {
        return false;
    }

    // Check email format
    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        return false;
    }

    // Check if slug is available
    var $status = $('#slug-status');
    if ($status.hasClass('error') || $status.hasClass('loading')) {
        return false;
    }

    return true;
};

CreateWishlistForm.prototype.submitForm = function() {
    if (!this.isFormValid()) {
        return;
    }

    var $form = $('#create-wishlist-form');
    var $submitButton = $('#submit-wishlist');
    var $status = $('#form-status');

    // Get form data
    var formData = {
        action: 'create_wishlist',
        nonce: $form.find('input[name="wishlist_nonce"]').val(),
        event_type: $('#event_type').val(),
        event_title: $('#event_title').val(),
        slug: $('#wishlist_slug').val(),
        full_name: $('#full_name').val(),
        email: $('#email').val()
    };

    // Show loading state
    $submitButton.prop('disabled', true).text('Creating...');
    $status.removeClass('success error').addClass('loading').text('Creating your wishlist...');

    var self = this;
    $.ajax({
        url: this.ajaxUrl,
        type: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                $status.removeClass('error loading').addClass('success').text('✓ ' + response.data.message);

                // Show success message with link to wishlist
                if (response.data.wishlist_url) {
                    $status.append('<br><a href="' + response.data.wishlist_url + '" target="_blank">View Your Wishlist →</a>');
                }

                // Optional: redirect to "My Wish Lists" page after a delay
                setTimeout(function() {
                    // You can add redirect logic here if you have a "My Wish Lists" page
                    // window.location.href = '/my-wish-lists/';
                }, 3000);

            } else {
                $status.removeClass('success loading').addClass('error').text('✗ ' + response.data);
                $submitButton.prop('disabled', false).text('Create Wish List');
            }
        },
        error: function(xhr, status, error) {
            console.error('Wishlist creation error:', error);
            $status.removeClass('success loading').addClass('error').text('✗ An error occurred. Please try again.');
            $submitButton.prop('disabled', false).text('Create Wish List');
        }
    });
};

})(jQuery);