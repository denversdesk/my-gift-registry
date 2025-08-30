/**
 * My Gift Registry Admin JavaScript
 * Handles admin dashboard functionality for recommended products
 */

(function($) {
    'use strict';

    class MGiftRegistryAdmin {
        constructor() {
            this.ajaxUrl = mgrAjax.ajax_url;
            this.nonce = mgrAjax.nonce;
            this.strings = mgrAjax.strings;
            this.selectedProducts = {};
            this.currentEventType = 'wedding';

            this.init();
        }

        init() {
            this.bindEvents();
            this.loadCurrentRecommendations();
            this.initializeTemplate();
        }

        bindEvents() {
            // Event type selector
            $(document).on('change', '#event-type-select', this.handleEventTypeChange.bind(this));

            // Product search
            $(document).on('input', '#mgr-product-search', this.handleProductSearch.bind(this));
            $(document).on('click', '.mgr-search-item', this.handleProductSelect.bind(this));

            // Product removal
            $(document).on('click', '.mgr-product-remove', this.handleProductRemove.bind(this));

            // Save button
            $(document).on('click', '#mgr-save-recommended', this.handleSave.bind(this));

            // Close search on outside click
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#mgr-product-search, .mgr-search-results').length) {
                    $('.mgr-search-results').hide();
                }
            });
        }

        initializeTemplate() {
            this.productTemplate = $('#mgr-product-template').html();
        }

        handleEventTypeChange(e) {
            const newEventType = $(e.target).val();
            if (newEventType !== this.currentEventType) {
                this.currentEventType = newEventType;
                this.loadCurrentRecommendations();
                $('#mgr-product-search').val('').trigger('input'); // Clear and hide search
            }
        }

        loadCurrentRecommendations() {
            // Clear current selection
            this.selectedProducts = {};

            // Get saved recommendations via AJAX
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mgr_get_recommended',
                    nonce: this.nonce,
                    event_type: this.currentEventType
                },
                success: (response) => {
                    if (response.success && response.data.products && response.data.products.length > 0) {
                        // Load saved recommendations
                        response.data.products.forEach(product => {
                            this.selectedProducts[product.id] = product.id;
                        });

                        // Display the products
                        this.displaySelectedProducts(response.data.products);
                    } else {
                        // Show empty state
                        this.displayEmptyState();
                    }
                },
                error: () => {
                    console.error('Failed to load recommendations');
                    this.displayEmptyState();
                }
            });
        }

        loadSelectedProductDetails() {
            const productIds = Object.values(this.selectedProducts);
            if (productIds.length === 0) {
                this.displayEmptyState();
                return;
            }

            // Re-fetch current recommendations with details
            this.loadCurrentRecommendations();
        }

        displaySelectedProducts(products) {
            const $grid = $('#mgr-selected-grid');
            $grid.empty();

            if (products.length === 0) {
                this.displayEmptyState();
                return;
            }

            products.forEach(product => {
                const html = this.productTemplate
                    .replace(/\{\{product_id\}\}/g, product.id)
                    .replace(/\{\{image_url\}\}/g, product.image_url || '')
                    .replace(/\{\{title\}\}/g, product.title || 'Unknown Product')
                    .replace(/\{\{price_html\}\}/g, product.price_html || '$0.00');

                $grid.append(html);
            });

            // Update product count display
            this.updateProductCount();
        }

        displayEmptyState() {
            const $grid = $('#mgr-selected-grid');
            $grid.html(`
                <div class="mgr-product-placeholder">
                    <span class="dashicons dashicons-cart"></span>
                    <p>No products selected yet. Search and add up to 6 recommended products.</p>
                </div>
            `);

            this.updateProductCount();
        }

        updateProductCount() {
            const count = Object.keys(this.selectedProducts).length;
            $('.mgr-product-count[data-event-type="' + this.currentEventType + '"]')
                .text(count + ' products');
        }

        handleProductSearch(e) {
            const query = $(e.target).val().trim();
            const $results = $('.mgr-search-results');

            if (query.length < 2) {
                $results.hide();
                return;
            }

            // Debounce search
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.performProductSearch(query);
            }, 300);
        }

        performProductSearch(query) {
            const $results = $('.mgr-search-results');

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mgr_search_products',
                    nonce: this.nonce,
                    search_term: query
                },
                success: (response) => {
                    if (response.success && response.data.products.length > 0) {
                        this.displaySearchResults(response.data.products);
                    } else {
                        $results.html('<div class="mgr-search-item">' + this.strings.no_products_found + '</div>').show();
                    }
                },
                error: () => {
                    $results.hide();
                    this.showError('Search failed. Please try again.');
                }
            });
        }

        displaySearchResults(products) {
            const $results = $('.mgr-search-results');
            let html = '';

            products.forEach(product => {
                if (!this.selectedProducts[product.id]) { // Don't show already selected products
                    html += `
                        <div class="mgr-search-item" data-product-id="${product.id}">
                            <img src="${product.image_url}" alt="${product.title}" onerror="this.src=''">
                            <div class="mgr-search-item-text">
                                <div class="mgr-search-item-title">${product.title}</div>
                                <div class="mgr-search-item-price">${product.price_html}</div>
                            </div>
                        </div>
                    `;
                }
            });

            if (html) {
                $results.html(html).show();
            } else {
                $results.hide();
            }
        }

        handleProductSelect(e) {
            const $item = $(e.currentTarget);
            const productId = $item.data('product-id');

            if (Object.keys(this.selectedProducts).length >= 6) {
                this.showError(this.strings.max_products);
                $('.mgr-search-results').hide();
                return;
            }

            // Extract product details from search item
            const imageUrl = $item.find('img').attr('src') || '';
            const title = $item.find('.mgr-search-item-title').text().trim() || 'Unknown Product';
            const priceHtml = $item.find('.mgr-search-item-price').html() || '$0.00';

            // Add to selected products
            this.selectedProducts[productId] = productId;

            // Hide search results and clear input
            $('.mgr-search-results').hide();
            $('#mgr-product-search').val('');

            // Append the product card to the grid
            const html = this.productTemplate
                .replace(/\{\{product_id\}\}/g, productId)
                .replace(/\{\{image_url\}\}/g, imageUrl)
                .replace(/\{\{title\}\}/g, title)
                .replace(/\{\{price_html\}\}/g, priceHtml);

            $('#mgr-selected-grid').append(html);

            // Update product count
            this.updateProductCount();
        }

        handleProductRemove(e) {
            e.stopPropagation();

            const $card = $(e.target).closest('.mgr-product-card');
            const productId = $card.data('product-id');

            if (confirm(this.strings.confirm_delete)) {
                delete this.selectedProducts[productId];
                $card.fadeOut('fast', () => {
                    $card.remove();
                    this.updateProductCount();
                });
            }
        }

        handleSave(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const $status = $('#mgr-save-status');
            const productIds = Object.values(this.selectedProducts);

            // Show loading state
            $button.prop('disabled', true).text(this.strings.saving);
            $status.removeClass('success error').text('');

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mgr_save_recommended',
                    nonce: this.nonce,
                    event_type: this.currentEventType,
                    product_ids: productIds
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(response.data.message || this.strings.saved);
                        // Refresh the current recommendations summary
                        this.updateRecommendationsSummary();
                    } else {
                        this.showError(response.data || this.strings.save_error);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Save error:', error);
                    this.showError(this.strings.save_error);
                },
                complete: () => {
                    $button.prop('disabled', false).text(__('Save Recommended Products', 'my-gift-registry'));
                }
            });
        }

        updateRecommendationsSummary() {
            // Update the summary display with new data
            const count = Object.keys(this.selectedProducts).length;
            $('.mgr-product-count[data-event-type="' + this.currentEventType + '"]').text(count + ' products');
            $('.mgr-updated-date[data-event-type="' + this.currentEventType + '"]').text(__('(Last updated: ', 'my-gift-registry') + new Date().toLocaleDateString() + ')');
        }

        showSuccess(message) {
            const $status = $('#mgr-save-status');
            $status.removeClass('error').addClass('success').text(message);

            setTimeout(() => {
                $status.fadeOut('fast', () => {
                    $status.removeClass('success').show();
                });
            }, 3000);
        }

        showError(message) {
            const $status = $('#mgr-save-status');
            $status.removeClass('success').addClass('error').text(message);

            // Don't auto-hide errors
        }
    }

    // Initialize when DOM is ready
    $(document).ready(() => {
        // Check if we're on the recommended products page
        if ($('#mgr-product-search').length > 0) {
            new MGiftRegistryAdmin();
        }
    });

})(jQuery);