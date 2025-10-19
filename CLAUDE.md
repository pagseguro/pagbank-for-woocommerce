# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PagBank for WooCommerce is a WordPress plugin that integrates PagBank payment gateway with WooCommerce, supporting credit card, Pix, and boleto (bank slip) payment methods. The plugin is designed for Brazilian e-commerce with marketplace support (Dokan and WCFM) and includes split payment functionality.

## Technology Stack

- **Backend**: PHP 7.4-8.3 with WordPress/WooCommerce
- **Frontend**: TypeScript with Vite
- **Package Management**: pnpm for Node.js, Composer for PHP
- **Development Environment**: Docker Compose with WordPress, MariaDB, and PHPMyAdmin

## Development Commands

### Setup
```bash
# Initial project setup (after docker compose up)
pnpm setup

# Install PHP dependencies
pnpm composer install

# Start Docker environment
docker compose up
```

Access the development site at http://localhost

**Development Services:**
- WordPress: http://localhost
- PHPMyAdmin: http://localhost:8080
- Mailpit (Email testing): http://localhost:8025

### Build & Development
```bash
# Build production bundle (runs composer install --no-dev first)
pnpm build

# Development mode with watch
pnpm dev

# TypeScript compilation only
tsc
```

### Linting
```bash
# Lint both PHP and TypeScript
pnpm lint

# Lint PHP only (uses Docker)
pnpm lint:core

# Fix PHP code style issues
pnpm lint:fix:core

# Lint TypeScript/JavaScript only
pnpm lint:ui
```

### Testing
```bash
# Run PHP unit tests (uses Docker)
pnpm test

# Run specific test
./scripts/phpunit.sh path/to/test.php
```

### WordPress CLI
```bash
# Execute WP-CLI commands
pnpm wp <command>
```

## Architecture

### Directory Structure

```
src/
├── core/                          # PHP backend code
│   ├── Gateways/                  # Payment gateway implementations
│   │   ├── CreditCardPaymentGateway.php
│   │   ├── PixPaymentGateway.php
│   │   └── BoletoPaymentGateway.php
│   ├── Presentation/              # WP hooks, AJAX handlers, field rendering
│   │   ├── PaymentGateways.php    # Gateway registration
│   │   ├── PaymentGatewaysFields.php
│   │   ├── Connect.php            # PagBank OAuth connection
│   │   ├── ConnectAjaxApi.php
│   │   ├── WebhookHandler.php
│   │   ├── Api.php                # PagBank API client
│   │   ├── ApiHelpers.php
│   │   └── Helpers.php
│   └── Marketplace/               # Marketplace integrations
│       └── WcfmIntegration.php
├── templates/                     # PHP template files for checkout/admin
└── ui/                           # TypeScript frontend code
    └── entries/
        ├── admin/
        │   └── admin-settings.ts  # Admin settings page scripts
        └── public/
            ├── checkout-credit-card.ts
            └── order.ts
```

### Key Architecture Patterns

1. **Payment Gateway Structure**: Three gateway classes extend `WC_Payment_Gateway_CC` or `WC_Payment_Gateway`:
   - `CreditCardPaymentGateway`: Supports tokenization, installments, recurring payments
   - `PixPaymentGateway`: Generates QR codes for instant payment
   - `BoletoPaymentGateway`: Generates bank slips

2. **Singleton Pattern**: Presentation layer classes use `get_instance()` static method for initialization

3. **API Integration**: `Api.php` handles all PagBank API communication with OAuth2 token management

4. **Webhook Processing**: `WebhookHandler.php` processes payment status updates from PagBank

5. **Frontend Build**: Vite bundles TypeScript into separate entry points for admin and public checkout pages

6. **Marketplace Split Payments**: Each vendor must configure their PagBank marketplace identifier in their store settings

### PSR-4 Autoloading

PHP classes use the namespace `PagBank_WooCommerce\` mapped to `src/core/`

### Plugin Entry Point

`pagbank-for-woocommerce.php` initializes all singleton instances and declares WooCommerce HPOS compatibility

## Code Standards

### PHP
- WordPress Coding Standards (WPCS) enforced via PHPCS
- WooCommerce Coding Standards
- Configuration in `phpcs.xml.dist`
- Target PHP 7.4 compatibility (defined in composer.json platform config)

### TypeScript/JavaScript
- ESLint with TypeScript plugin
- Prettier for formatting
- Import ordering enforced (import-helpers plugin)
- Configuration in `.eslintrc.cjs`

## Important Dependencies

### PHP
- `jakeasmith/http_build_url`: URL manipulation
- `giggsey/libphonenumber-for-php`: Phone validation
- `nesbot/carbon`: Date handling
- `wilkques/pkce-php`: OAuth PKCE flow for PagBank connection

### TypeScript
- `autonumeric`: Currency input formatting
- `card-validator`: Credit card validation
- `axios`: HTTP client
- `date-fns`: Date utilities

## Testing Notes

- PHPUnit configured in `phpunit.xml` with bootstrap at `tests/bootstrap.php`
- Tests run inside Docker container via `scripts/phpunit.sh`
- No existing test suite structure visible; tests directory appears empty

## Build Output

- Vite outputs to `dist/` directory
- Three entry points: admin-settings, checkout-credit-card, order
- Shared chunks in `dist/ui/shared/`
- Auto-zip plugin creates distribution package

## WooCommerce Integration Points

- **Subscriptions Support**: Handles recurring payments for WooCommerce Subscriptions
- **HPOS Compatibility**: Declared compatible with High-Performance Order Storage
- **Payment Tokens**: Supports saving credit cards for future use
- **Refunds**: Online refund support (total and partial)
- **Order Status Sync**: Webhook automatically updates order status

## Marketplace Support

The plugin supports multi-vendor marketplaces (Dokan/WCFM) with payment splitting. Each vendor must:
1. Access their PagBank account → Vendas → Identificador para Marketplace
2. Configure the identifier in WooCommerce vendor settings
3. Products from vendors without identifiers won't be available at checkout

## Brazilian Market Dependency

Requires "Brazilian Market on WooCommerce" plugin for CPF/CNPJ fields and address formatting (neighborhood field required)

## Development Environment Details

- WordPress runs on PHP 8.3 with Xdebug automatically installed
- Database: MariaDB
- PHPMyAdmin available on port 8080
- Plugin mounted as volume for hot-reload
- Custom PHP settings in `wordpress.ini`
