# Sponsorship System

A WordPress plugin built for an animal-rescue charity to manage **animal sponsorships paid through PayPal**. It stores sponsorships and payment transactions in dedicated database tables, keeps sponsors as a custom post type, emails sponsors updates about their animals, and gives admins reporting dashboards.

## Features

- **PayPal IPN processing** – `sp_process_paypal_ipn()` turns one-time and monthly (subscription) IPNs into sponsorship records and transaction rows, with idempotent upserts by `txn_id` / `subscr_id`.
- **Custom tables** – `{prefix}sponsorships` and `{prefix}sponsorship_transactions`, created on activation via `dbDelta`.
- **Sponsor accounts** – finds or creates the Sponsor CPT entry and a WordPress user (custom `sponser` role) for each payer and emails them their login.
- **Admin screens** – Sponsorships, Sponsors and Transactions lists with search, filters and pagination.
- **Messages module** – send updates to everyone sponsoring a given animal (or all monthly sponsors), with an HTML email preview.
- **Reporting module** – sponsored vs non-sponsored animals by year, month, plan and category, with multi-currency totals.

## Structure

```
sponsorship-system/
├── sponsorship-system.php           # Bootstrap, config, tables, admin lists, IPN core
└── modules/
    ├── messages/
    │   ├── messages.php             # Admin menu + screen router
    │   ├── home-screen.php          # Message list + "send to all"
    │   ├── message-single-screen.php# Messages for one sponsored animal
    │   ├── sponsor-message-email.php# Email sender
    │   ├── preview-email.php        # AJAX email preview (admins only)
    │   └── currency_symbols_by_code.json
    └── reporting/
        ├── reporting.php            # Reporting admin pages
        ├── filters-form.php         # Sponsored report filters
        ├── filters-form-non-spon.php# Non-sponsored report filters
        ├── js/script.js
        └── currency_symbols_by_code.json
```

## Setup

1. Copy the folder to `wp-content/plugins/` and activate it (tables are created on activation).
2. Update the constants at the top of `sponsorship-system.php` (`SP_ADMIN_EMAIL`, `SP_FROM_EMAIL`, `SP_FROM_NAME`, `SP_ROLE`).
3. Requires **Advanced Custom Fields** and the `sponsors` / `messages` post types used by the theme.
4. In your PayPal IPN callback page template, **verify the IPN with PayPal first**, then call:

```php
$result = sp_process_paypal_ipn( file_get_contents( 'php://input' ), $_POST );
wp_send_json( $result );
```

## Security notes

- All database queries use `$wpdb->prepare()`.
- Admin message forms are protected with nonces; input is sanitized (`sanitize_text_field`, `wp_kses_post`, `absint`).
- Only IPNs with `payment_status = Completed` activate a sponsorship.

## Credits

Developed by **Asad Ali** while at Hashe Computer Solutions.
