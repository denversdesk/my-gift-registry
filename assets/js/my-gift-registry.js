/**
 * My Gift Registry JavaScript - Phase 3
 * Handles frontend interactions for wishlist management
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

            // Initialize countdown timers on page load
            this.initializeCountdownTimers();
            $(document).on('click', '#add-product-modal .modal-close, #add-product-modal .modal-overlay, #add-product-modal .cancel-button', this.closeAddProductModal.bind(this));

            // Form submission
            $(document).on('submit', '#reservation-form', this.handleFormSubmit.bind(this));

            // Email validation
            $(document).on('input', '#reservation-email, #reservation-confirm-email', this.validateEmails.bind(this));

            // Share button functionality
            $(document).on('click', '.share-button.copy-link', this.copyLink.bind(this));

            // Phase 4: Add Products functionality
            $(document).on('click', '.add-products-button', this.handleAddProductsClick.bind(this));
            $(document).on('input', '#product-search', this.handleProductSearch.bind(this));
            $(document).on('click', '.product-search-item', this.handleProductSelect.bind(this));
            $(document).on('submit', '#add-product-form', this.handleAddProductSubmit.bind(this));

            // Phase 5: Delete Gift functionality
            $(document).on('click', '.delete-gift-button', this.handleDeleteClick.bind(this));
            $(document).on('click', '#confirm-delete-gift', this.handleConfirmDelete.bind(this));
            $(document).on('click', '.cancel-delete-button', this.handleCancelDelete.bind(this));

            // Delete modal close events
            $(document).on('click', '.delete-gift-modal .modal-close, .delete-gift-modal .modal-overlay, .delete-gift-modal .cancel-delete-button', this.closeDeleteModal.bind(this));

            // Media manager functionality
            $(document).on('click', '.choose-image-button', this.handleChooseImage.bind(this));
            $(document).on('click', '.remove-image-button', this.handleRemoveImage.bind(this));

            // Close product search results when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#product-search, .product-search-results').length) {
                    $('.product-search-results').hide();
                }
            });

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
                    // $submitButton.prop('disabled', false).text(originalText);
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
                this.closeAddProductModal();
                this.closeDeleteModal();
            }
        }

        // Debug: Check for delete buttons
        checkDeleteButtons() {
            setTimeout(() => {
                const deleteButtons = $('.delete-gift-button');
                console.log('My Gift Registry: Delete buttons found:', deleteButtons.length);
                if (deleteButtons.length > 0) {
                    console.log('My Gift Registry: First delete button:', deleteButtons.first().get(0));
                    console.log('My Gift Registry: Delete button data:', deleteButtons.first().data());
                }
            }, 1000);
        }

        // Phase 5: Delete Gift functionality
        handleDeleteClick(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const giftId = $button.data('gift-id');
            const giftTitle = $button.data('gift-title');
            const giftImage = $button.data('gift-image');

            if (!giftId) {
                this.showError('Invalid gift selected.');
                return;
            }

            // Populate delete modal with gift info
            this.populateDeleteModal(giftId, giftTitle, giftImage);

            // Show delete modal
            this.showDeleteModal();
        }

        handleConfirmDelete(e) {
            e.preventDefault();

            const giftId = $('#delete-gift-modal').data('gift-id');
            const nonce = $('#mgr_gift_delete_nonce').val();

            if (!giftId) {
                this.showError('Invalid gift selected.');
                return;
            }

            // Show loading state
            const $button = $(e.currentTarget);
            const originalText = $button.text();
            $button.prop('disabled', true).text('Deleting...');

            // Send AJAX request
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mgr_delete_gift',
                    security: nonce,
                    gift_id: giftId
                },
                success: this.handleDeleteSuccess.bind(this),
                error: this.handleDeleteError.bind(this),
                complete: () => {
                    // Reset button state
                    $button.prop('disabled', false).text(originalText);
                }
            });
        }

        handleCancelDelete(e) {
            e.preventDefault();
            this.closeDeleteModal();
        }

        handleDeleteSuccess(response) {
            if (response.success) {
                // Show success message
                this.showSuccess(response.data.message || 'Gift deleted successfully!');

                // Remove the gift item from the page
                const giftId = $('#delete-gift-modal').data('gift-id');
                $(`.gift-item[data-gift-id="${giftId}"]`).fadeOut(300, function() {
                    $(this).remove();
                });

                // Close delete modal
                this.closeDeleteModal();

                // Reload the page after successful deletion
                setTimeout(() => {
                    location.reload();
                }, 1500);

            } else {
                this.showError(response.data || 'Failed to delete gift.');
            }
        }

        handleDeleteError(xhr, status, error) {
            console.error('Delete error:', error);
            this.showError('An error occurred while deleting the gift. Please try again.');
        }

        populateDeleteModal(giftId, title, image) {
            // Store gift ID in modal
            $('#delete-gift-modal').data('gift-id', giftId);

            // Populate gift title
            $('#delete-gift-title').text(title);

            // Populate gift image
            if (image) {
                $('#delete-gift-image').attr('src', image).show();
                $('#delete-gift-no-image').hide();
            } else {
                $('#delete-gift-image').hide();
                $('#delete-gift-no-image').show();
            }
        }

        showDeleteModal() {
            $('#delete-gift-modal').fadeIn(300);
            $('body').addClass('modal-open');
        }

        closeDeleteModal() {
            $('#delete-gift-modal').fadeOut(300);
            $('body').removeClass('modal-open');
        }

        // Countdown Timer functionality
        initializeCountdownTimers() {
            const $eventSections = $('.event-date-section');

            if ($eventSections.length === 0) {
                return;
            }

            $eventSections.each((index, element) => {
                const $section = $(element);
                const eventDate = $section.data('event-date');

                if (eventDate) {
                    this.startCountdownTimer($section, eventDate);
                }
            });
        }

        startCountdownTimer($section, eventDateString) {
            const updateCountdown = () => {
                const now = new Date().getTime();
                const eventDate = new Date(eventDateString).getTime();
                const timeLeft = eventDate - now;

                const $countdown = $section.find('.event-countdown');

                if (timeLeft > 0) {
                    const days = Math.ceil(timeLeft / (1000 * 60 * 60 * 24));

                    let message;
                    if (days === 1) {
                        message = '1 day to go';
                    } else {
                        message = days + ' days to go';
                    }

                    $countdown.removeClass('past today').text(message);
                } else if (timeLeft === 0 || (timeLeft > -86400000 && timeLeft < 0)) { // Within 24 hours
                    $countdown.removeClass('past').addClass('today').text('Today!');
                } else {
                    $countdown.removeClass('today').addClass('past').text('Event has passed');
                }
            };

            // Update immediately
            updateCountdown();

            // Update every minute
            setInterval(updateCountdown, 60000);
        }

        // Phase 4: Add Products functionality
        handleAddProductsClick(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const wishlistId = $button.data('wishlist-id');

            if (!wishlistId) {
                this.showError('Invalid wishlist selected.');
                return;
            }

            // Store wishlist ID for later use
            $('#add-product-form').data('wishlist-id', wishlistId);

            // Show add product modal
            this.showAddProductModal();
        }

        showAddProductModal() {
            $('#add-product-modal').fadeIn(300);
            $('body').addClass('modal-open');

            // Focus search input
            setTimeout(() => {
                $('#product-search').focus();
            }, 100);
        }

        closeAddProductModal() {
            $('#add-product-modal').fadeOut(300);
            $('body').removeClass('modal-open');

            // Reset form
            setTimeout(() => {
                this.resetAddProductForm();
            }, 300);
        }

        resetAddProductForm() {
            const $form = $('#add-product-form');
            $form[0].reset();
            $form.removeData('wishlist-id');
            $('.product-search-results').hide().empty();
            $('.add-product-submit-button').removeClass('loading').prop('disabled', false);

            // Clear image preview
            this.updateImagePreview('');
        }

        handleProductSearch(e) {
            const searchTerm = $(e.target).val().trim();

            if (searchTerm.length < 2) {
                $('.product-search-results').hide();
                return;
            }

            // Debounce search
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => {
                this.performProductSearch(searchTerm);
            }, 300);
        }

        performProductSearch(searchTerm) {
            const $results = $('.product-search-results');

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'search_products',
                    nonce: this.nonce,
                    search_term: searchTerm
                },
                success: (response) => {
                    if (response.success) {
                        this.displayProductResults(response.data.products);
                    } else {
                        $results.hide();
                    }
                },
                error: () => {
                    $results.hide();
                }
            });
        }

        displayProductResults(products) {
            const $results = $('.product-search-results');

            if (!products || products.length === 0) {
                $results.hide();
                return;
            }

            let html = '';
            products.forEach(product => {
                const imageUrl = product.image_url || '';
                const priceHtml = product.price_html || '';

                html += `
                    <div class="product-search-item" data-product='${JSON.stringify(product).replace(/'/g, "'")}'>
                        ${imageUrl ? `<img src="${imageUrl}" alt="${product.title}">` : '<div class="no-image">📦</div>'}
                        <div class="product-search-item-info">
                            <div class="product-search-item-title">${product.title}</div>
                            ${priceHtml ? `<div class="product-search-item-price">${priceHtml}</div>` : ''}
                        </div>
                    </div>
                `;
            });

            $results.html(html).show();
        }

        handleProductSelect(e) {
            const $item = $(e.currentTarget);
            const productData = JSON.parse($item.attr('data-product').replace(/'/g, "'"));

            // Populate form fields
            $('#product-title').val(productData.title);
            $('#product-description').val(productData.description || '');
            $('#product-price').val(productData.price || '');
            $('#product-url').val(productData.url || '');

            // Update image preview with WooCommerce product image
            this.updateImagePreview(productData.image_url || '');

            // Hide results
            $('.product-search-results').hide();

            // Clear search
            $('#product-search').val('');
        }

        handleAddProductSubmit(e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const $submitButton = $form.find('.add-product-submit-button');

            // Basic validation
            const title = $('#product-title').val().trim();
            if (!title) {
                this.showError('Product title is required.');
                return;
            }

            // Show loading state
            $submitButton.addClass('loading').prop('disabled', true);

            // Get form data
            const formData = {
                action: 'add_product',
                nonce: this.nonce,
                wishlist_id: $form.data('wishlist-id'),
                title: title,
                description: $('#product-description').val().trim(),
                price: $('#product-price').val(),
                priority: $('#product-priority').val(),
                image_url: $('#product-image-url').val().trim(),
                product_url: $('#product-url').val().trim()
            };

            // Send AJAX request
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: formData,
                success: this.handleAddProductSuccess.bind(this),
                error: this.handleAddProductError.bind(this),
                complete: () => {
                    // Reset button state
                    // $submitButton.removeClass('loading').prop('disabled', false);
                }
            });
        }

        handleAddProductSuccess(response) {
            if (response.success) {
                // Show success message
                this.showSuccess(response.data.message || 'Product added successfully!');

                // Close modal after a delay
                setTimeout(() => {
                    this.closeAddProductModal();
                    // Optionally reload the page to show the new product
                    location.reload();
                }, 2000);

            } else {
                this.showError(response.data || 'Failed to add product.');
            }
        }

        handleAddProductError(xhr, status, error) {
            console.error('Add product error:', error);
            this.showError('An error occurred while adding the product. Please try again.');
        }

        // Media Manager functionality
        handleChooseImage(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const $preview = $('#product-image-preview');
            const $thumb = $('#product-image-thumb');
            const $urlInput = $('#product-image-url');

            // Create or reuse media frame
            if (!this.mediaFrame) {
                this.mediaFrame = wp.media({
                    title: 'Select Product Image',
                    button: {
                        text: 'Use this image'
                    },
                    multiple: false,
                    library: {
                        type: 'image',
                        author: wp.media.view.settings.post.id || 0 // Restrict to current user's uploads
                    }
                });

                // Handle image selection
                this.mediaFrame.on('select', () => {
                    const attachment = this.mediaFrame.state().get('selection').first().toJSON();

                    // Update hidden input with image URL
                    $urlInput.val(attachment.url);

                    // Update preview
                    $thumb.attr('src', attachment.url);
                    $preview.show();

                    // Update choose button text
                    $button.text('Replace Image');
                });
            }

            // Open the media frame
            this.mediaFrame.open();
        }

        handleRemoveImage(e) {
            e.preventDefault();

            const $preview = $('#product-image-preview');
            const $thumb = $('#product-image-thumb');
            const $urlInput = $('#product-image-url');
            const $button = $('.choose-image-button');

            // Clear the image URL
            $urlInput.val('');

            // Hide preview
            $preview.hide();

            // Reset button text
            $button.text('Choose Image');

            // Clear thumbnail
            $thumb.attr('src', '');
        }

        updateImagePreview(imageUrl) {
            const $preview = $('#product-image-preview');
            const $thumb = $('#product-image-thumb');
            const $urlInput = $('#product-image-url');
            const $button = $('.choose-image-button');

            if (imageUrl) {
                $urlInput.val(imageUrl);
                $thumb.attr('src', imageUrl);
                $preview.show();
                $button.text('Replace Image');
            } else {
                $urlInput.val('');
                $preview.hide();
                $button.text('Choose Image');
                $thumb.attr('src', '');
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
            email: $('#email').val(),
            event_date: $('#event_date').val(),
            description: $('#description').val(),
            profile_pic: $('#profile_pic').val(),
        };

        // Debug: Log form data
        console.log('Form data being sent:', formData);

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
                        $status.append('<br><a href="' + response.data.wishlist_url + '" target="_self">View Your Wishlist →</a>');
                    }

                    // Optional: redirect after a delay
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

    /**
     * Edit Wishlist Form Handler
     */
    function EditWishlistForm() {
        this.ajaxUrl = myGiftRegistryAjax.ajax_url;
        this.nonce = myGiftRegistryAjax.nonce;
        this.init();
    }

    EditWishlistForm.prototype.init = function() {
        this.bindEvents();
        this.slugTimer = null;

        // Trigger initial slug validation since form is pre-populated
        setTimeout(function() {
            this.validateSlug();
        }.bind(this), 100);
    };

    EditWishlistForm.prototype.bindEvents = function() {
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
        $('#edit-wishlist-form').on('submit', function(e) {
            e.preventDefault();
            self.submitForm();
        });

        // Enable/disable submit button based on form validity
        this.updateSubmitButton();
    };

    EditWishlistForm.prototype.generateSlug = function() {
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

    EditWishlistForm.prototype.validateSlug = function() {
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

    EditWishlistForm.prototype.checkSlugAvailability = function(slug) {
        var $status = $('#slug-status');
        var self = this;

        $.ajax({
            url: this.ajaxUrl,
            type: 'POST',
            data: {
                action: 'validate_slug',
                nonce: this.nonce,
                slug: slug,
                exclude_id: $('#edit-wishlist-form input[name="wishlist_id"]').val()
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

    EditWishlistForm.prototype.updateSubmitButton = function() {
        var isValid = this.isFormValid();
        $('#submit-wishlist').prop('disabled', !isValid);
    };

    EditWishlistForm.prototype.isFormValid = function() {
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

    EditWishlistForm.prototype.submitForm = function() {
        if (!this.isFormValid()) {
            return;
        }

        var $form = $('#edit-wishlist-form');
        var $submitButton = $('#submit-wishlist');
        var $status = $('#form-status');

        // Get form data
        var formData = {
            action: 'update_wishlist',
            nonce: $form.find('input[name="wishlist_nonce"]').val(),
            wishlist_id: $form.find('input[name="wishlist_id"]').val(),
            event_type: $('#event_type').val(),
            event_title: $('#event_title').val(),
            event_date: $('#event_date').val(),
            description: $('#description').val(),
            profile_pic: $('#profile_pic').val(),
            slug: $('#wishlist_slug').val(),
            full_name: $('#full_name').val(),
            email: $('#email').val()
        };

        // Show loading state
        $submitButton.prop('disabled', true).text('Updating...');
        $status.removeClass('success error').addClass('loading').text('Updating your wishlist...');

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
                        $status.append('<br><a href="' + response.data.wishlist_url + '" target="_blank">View Updated Wishlist →</a>');
                    }

                    // Optional: redirect after a delay
                    setTimeout(function() {
                        window.location.href = '?updated=1';
                    }, 3000);

                } else {
                    $status.removeClass('success loading').addClass('error').text('✗ ' + response.data);
                    $submitButton.prop('disabled', false).text('Update Wish List');
                }
            },
            error: function(xhr, status, error) {
                console.error('Wishlist update error:', error);
                $status.removeClass('success loading').addClass('error').text('✗ An error occurred. Please try again.');
                $submitButton.prop('disabled', false).text('Update Wish List');
            }
        });
    };

    /**
     * My Wishlists Manager - Handles delete functionality
     */
    function MyWishlistsManager() {
        this.ajaxUrl = myGiftRegistryAjax.ajax_url;
        this.nonce = myGiftRegistryAjax.nonce;
        this.init();
    }

    MyWishlistsManager.prototype.init = function() {
        this.bindEvents();
    };

    MyWishlistsManager.prototype.bindEvents = function() {
        var self = this;

        // Delete button clicks
        $('.delete-button').on('click', function(e) {
            e.preventDefault();
            var $button = $(this);
            var wishlistId = $button.data('wishlist-id');
            var wishlistTitle = $button.data('wishlist-title');
            self.showDeleteModal(wishlistId, wishlistTitle);
        });

        // Modal close events
        $('#delete-modal .modal-close, #delete-modal .modal-overlay, #delete-modal .cancel-delete').on('click', function() {
            self.closeDeleteModal();
        });

        // Confirm delete
        $('#delete-modal .confirm-delete').on('click', function() {
            var wishlistId = $('#delete-modal').data('wishlist-id');
            self.confirmDelete(wishlistId);
        });

        // ESC key to close modal
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27 && $('#delete-modal').is(':visible')) {
                self.closeDeleteModal();
            }
        });
    };

    MyWishlistsManager.prototype.showDeleteModal = function(wishlistId, wishlistTitle) {
        $('#delete-modal').data('wishlist-id', wishlistId);
        $('#delete-modal .wishlist-title-confirm').text('"' + wishlistTitle + '"');
        $('#delete-modal').fadeIn(300);
        $('body').addClass('modal-open');
    };

    MyWishlistsManager.prototype.closeDeleteModal = function() {
        $('#delete-modal').fadeOut(300);
        $('body').removeClass('modal-open');
        $('#delete-modal').removeData('wishlist-id');
    };

    MyWishlistsManager.prototype.confirmDelete = function(wishlistId) {
        var $modal = $('#delete-modal');
        var $confirmButton = $modal.find('.confirm-delete');

        // Show loading state
        $confirmButton.prop('disabled', true).text('Deleting...');

        var self = this;
        $.ajax({
            url: this.ajaxUrl,
            type: 'POST',
            data: {
                action: 'delete_wishlist',
                nonce: this.nonce,
                wishlist_id: wishlistId
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    self.showMessage('✓ ' + response.data.message, 'success');

                    // Remove the row from the table
                    $('button[data-wishlist-id="' + wishlistId + '"]').closest('tr').fadeOut(300, function() {
                        $(this).remove();

                        // Check if table is empty
                        if ($('.wishlists-table tbody tr').length === 0) {
                            location.reload(); // Reload to show "no wishlists" message
                        }
                    });

                    self.closeDeleteModal();

                    // Reset button state
                    $confirmButton.prop('disabled', false).text('Delete Wishlist');

                } else {
                    self.showMessage('✗ ' + response.data, 'error');
                    $confirmButton.prop('disabled', false).text('Delete Wishlist');
                }
            },
            error: function(xhr, status, error) {
                console.error('Delete error:', error);
                self.showMessage('✗ An error occurred while deleting the wishlist.', 'error');
                $confirmButton.prop('disabled', false).text('Delete Wishlist');
            }
        });
    };

    MyWishlistsManager.prototype.showMessage = function(message, type) {
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
    };

    // Date formatting functionality
    function formatDateForDisplay(dateString) {
        if (!dateString) return '';

        const date = new Date(dateString + 'T00:00:00'); // Add time to ensure proper parsing
        const options = {
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        };

        return date.toLocaleDateString('en-US', options);
    }

    // Update date preview when date changes
    $(document).on('change', '#event_date', function() {
        const dateValue = $(this).val();
        const formattedDate = formatDateForDisplay(dateValue);

        if (formattedDate) {
            $('#formatted-date').text(formattedDate);
            $('#event-date-preview').show();
        } else {
            $('#event-date-preview').hide();
        }
    });

    // Initialize date preview on page load
    $(document).ready(function() {
        const $dateInput = $('#event_date');
        if ($dateInput.length > 0 && $dateInput.val()) {
            const formattedDate = formatDateForDisplay($dateInput.val());
            if (formattedDate) {
                $('#formatted-date').text(formattedDate);
                $('#event-date-preview').show();
            }
        }
    });

    // Media Manager functionality
    let mediaUploader;

    function initializeMediaManager() {
        // Profile picture upload functionality
        $(document).on('click', '#upload-profile-pic', function(e) {
            e.preventDefault();

            const button = $(this);
            const imageInput = $('#profile_pic');
            const imagePreview = $('#profile-pic-image');
            const placeholder = $('#no-image-placeholder');
            const removeButton = $('#remove-profile-pic');

            // If the media uploader already exists, reopen it
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }

            // Create new media uploader
            mediaUploader = wp.media({
                title: 'Choose Profile Picture',
                button: {
                    text: 'Use this image'
                },
                multiple: false,
                library: {
                    type: 'image',
                    author: wp.media.view.settings.post.author // Restrict to user's own uploads
                }
            });

            // When image is selected
            mediaUploader.on('select', function() {
                const attachment = mediaUploader.state().get('selection').first().toJSON();

                // Update hidden input with image URL
                imageInput.val(attachment.url).trigger('change');

                // Update preview
                imagePreview.attr('src', attachment.url).show();
                placeholder.hide();
                removeButton.show();

                // Show success message
                showMessage('Profile picture updated successfully!', 'success');
            });

            // Open the uploader
            mediaUploader.open();
        });

        // Remove profile picture functionality
        $(document).on('click', '#remove-profile-pic', function(e) {
            e.preventDefault();

            const imageInput = $('#profile_pic');
            const imagePreview = $('#profile-pic-image');
            const placeholder = $('#no-image-placeholder');
            const removeButton = $(this);

            // Clear the input
            imageInput.val('');

            // Update preview
            imagePreview.attr('src', '').hide();
            placeholder.show();
            removeButton.hide();

            // Show message
            showMessage('Profile picture removed.', 'success');
        });
    }

    // Message display function for media operations
    function showMessage(message, type = 'success') {
        // Remove existing messages
        $('.media-message').remove();

        const messageClass = type === 'success' ? 'success' : 'error';
        const $message = $(`<div class="media-message ${messageClass}" style="
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 10000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            max-width: 300px;
            background: ${type === 'success' ? '#28a745' : '#dc3545'};
        ">${message}</div>`);

        $('body').append($message);

        // Auto remove after 3 seconds
        setTimeout(() => {
            $message.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Initialize when document is ready
    $(document).ready(function() {
        new MyGiftRegistry();

        // Initialize create wishlist form if it exists
        if ($('#create-wishlist-form').length > 0) {
            new CreateWishlistForm();
            initializeMediaManager();
        }

        // Initialize edit wishlist form if it exists
        if ($('#edit-wishlist-form').length > 0) {
            new EditWishlistForm();
            initializeMediaManager();
        }

        // Initialize my wishlists page if it exists
        if ($('.my-gift-registry-my-wishlists').length > 0) {
            new MyWishlistsManager();
        }
    });

    // Function to replace a <code> tag with a <div> tag within a specified parent
        function replaceCodeWithDiv(parentElementClass) {
            const parentElement = document.querySelector(parentElementClass);

            if (parentElement) {
                const codeTag = parentElement.querySelector('code');

                if (codeTag) {
                    const newDiv = document.createElement('div');
                    // Move all child nodes from the <code> tag to the new <div> tag
                    while (codeTag.firstChild) {
                        newDiv.appendChild(codeTag.firstChild);
                    }
                    // Replace the <code> tag with the new <div> tag
                    parentElement.replaceChild(newDiv, codeTag);
                    console.log(`<code> tag successfully replaced with <div> in ${parentElementClass}.`);
                } else {
                    console.log(`No <code> tag found within ${parentElementClass}.`);
                }
            } else {
                console.log(`No element with class ${parentElementClass} found.`);
            }
        }

        // Initialize My Gift Registry when document is ready
        $(document).ready(function() {
            console.log('My Gift Registry: Initializing...');

            // Initialize My Gift Registry class
            if (typeof myGiftRegistryAjax !== 'undefined') {
                const instance = new MyGiftRegistry();
                console.log('My Gift Registry: Class initialized successfully');
                instance.checkDeleteButtons();
            } else {
                console.error('My Gift Registry: myGiftRegistryAjax object not found!');
            }
        });

        // Ensure the DOM is fully loaded before running the script
        document.addEventListener('DOMContentLoaded', function() {
            // Call the function for the first element
            replaceCodeWithDiv('.my-gift-registry-my-wishlists');

            // Call the function for the second element
            replaceCodeWithDiv('.my-gift-registry-edit-form');

            // Call the function for the third element
            replaceCodeWithDiv('.my-gift-registry-login-required');
        });

})(jQuery);