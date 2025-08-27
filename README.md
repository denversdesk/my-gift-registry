# My Gift Registry - Phase 1 & 2

A WordPress plugin for creating and managing gift registries with wishlist functionality.

## Features Implemented

### ✅ Phase 1 Complete

- **Plugin Setup**: Standard WordPress plugin structure with proper activation/deactivation hooks
- **Custom Database Tables**: Three tables for wishlists, gifts, and reservations with sample data
- **Rewrite Endpoint**: `/mywishlist/{slug}` URL structure for clean wishlist URLs
- **Shortcode**: `[gift_registry_wishlist]` for displaying wishlists
- **Frontend Display**: Beautiful responsive design with gift cards, images, and details
- **Share Functionality**: Copy link, WhatsApp, Facebook, and Instagram sharing options
- **AJAX Reservation System**: Modal popup for reserving gifts with email validation
- **Email Confirmations**: HTML emails with gift details and featured WooCommerce products
- **Security**: Nonce verification and WordPress best practices
- **Responsive Design**: Mobile-friendly layout with modern CSS

### ✅ Phase 2 Complete

- **Create Wishlist Form**: `[create_wishlist_form]` shortcode for logged-in users
- **Form Fields**: Event type dropdown, event title, auto-generated SEO slug, full name, email
- **Real-time Slug Generation**: Auto-generate slug from title with uniqueness validation
- **AJAX Validation**: Check slug availability in real-time
- **Form Submission**: Create wishlist via AJAX with confirmation email
- **Security**: User authentication, nonce verification, input validation
- **Enhanced Database**: Extended wishlist table with new fields (event_type, full_name, etc.)

## Installation

1. Upload the `my-gift-registry` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin
3. The plugin will automatically create the necessary database tables

## Usage

### Creating a Wishlist Display Page

1. Create a new page in WordPress
2. Add the shortcode: `[gift_registry_wishlist]`
3. Publish the page

### Creating a Wishlist Creation Page

1. Create a new page in WordPress
2. Add the shortcode: `[create_wishlist_form]`
3. Publish the page

### Accessing Wishlists

- Visit: `yoursite.com/mywishlist/jessicas-bridal-shower` (uses sample data)
- The shortcode will automatically detect the slug from the URL and display the appropriate wishlist

### Testing the Plugin

The plugin includes sample data with one wishlist:
- **Slug**: `jessicas-bridal-shower`
- **Title**: Jessica's Bridal Shower
- **Gifts**: 3 sample gifts with images, descriptions, and prices

### Email Testing

When reserving a gift, the plugin will send a confirmation email including:
- Gift details
- Featured WooCommerce products (if WooCommerce is installed)
- Search link for similar products

When creating a wishlist, users receive a confirmation email with:
- Wishlist details and link
- Next steps for adding gifts

## File Structure

```
my-gift-registry/
├── my-gift-registry.php          # Main plugin file
├── includes/
│   ├── class-db-handler.php      # Database operations
│   ├── class-rewrite-handler.php # URL rewriting
│   ├── class-shortcode-handler.php # Shortcode functionality
│   ├── class-ajax-handler.php    # AJAX processing
│   └── class-email-handler.php   # Email notifications
├── assets/
│   ├── css/
│   │   └── my-gift-registry.css  # Frontend styles
│   └── js/
│       └── my-gift-registry.js   # Frontend JavaScript
└── README.md
```

## Database Tables

### `wp_my_gift_registry_wishlists`
- Stores wishlist information (title, description, user details)

### `wp_my_gift_registry_gifts`
- Stores individual gift items (title, description, price, images, etc.)

### `wp_my_gift_registry_reservations`
- Stores gift reservations (gift ID, email, timestamp)

## Shortcode Parameters

- `[gift_registry_wishlist]` - Display current wishlist (detects slug from URL)
- `[gift_registry_wishlist slug="custom-slug"]` - Display specific wishlist

## AJAX Endpoints

- `wp_ajax_reserve_gift` - Handle gift reservations
- `wp_ajax_nopriv_reserve_gift` - Handle reservations for non-logged-in users

## Security Features

- Nonce verification on all AJAX requests
- Email validation and sanitization
- SQL injection prevention with prepared statements
- XSS protection with output escaping

## Browser Support

- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+

## Future Phases (Not Included)

- Phase 2: Wishlist creation form
- Phase 3: Admin settings and management
- Phase 4: User dashboard and analytics

## Development Notes

- Follows WordPress Coding Standards
- Modular, object-oriented architecture
- Proper error handling and logging
- Responsive design with modern CSS Grid
- Accessibility considerations

## Troubleshooting

1. **Rewrite rules not working**: Go to Settings > Permalinks and save to flush rewrite rules
2. **Emails not sending**: Check WordPress email configuration and spam folders
3. **AJAX errors**: Check browser console for JavaScript errors and verify nonce values

## License

GPL v2 or later