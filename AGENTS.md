# AGENTS.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## JTL Shop

This is a JTL-Shop plugin - a shop system written in PHP.
The source code for JTL-Shop can be found at '../..' (relative to this plugin directory).
Documentation for JTL-Shop for plugin developers is at https://jtl-devguide.readthedocs.io/projects/jtl-shop/de/latest/index.html .
Use the JTL-Shop source code (at '../..') to understand how it works and how it interacts with plugins.

## Project Overview

This is the Axytos Payment Plugin for JTL Shop - a payment integration that provides "pay later" functionality through the Axytos payment provider. The plugin is built for JTL Shop version 5.0.0+ and implements a full payment workflow including precheck, confirmation, invoice creation, shipping notifications, and order management.

## Common Development Commands

### Code Quality
- **Lint code**: `vendor/bin/phpcs --standard=phpcs.xml`
- **Apply PSR-12 coding standards**: Plugin follows PSR-12 with 4-space indentation

### Dependencies
- **Install dependencies**: `composer install`
- **Update dependencies**: `composer update`

Note: No automated testing framework is currently configured in this codebase.

## High-Level Architecture

### Core Components

1. **Bootstrap.php** - Main plugin bootstrap class that:
   - Registers event hooks for order status updates and payment processing
   - Handles admin notifications and frontend agreement links
   - Manages cron job registration for payment updates
   - Coordinates between JTL Shop core and the payment method

2. **AxytosPaymentMethod** - Main payment method implementation:
   - Extends JTL's base `Method` class
   - Handles the complete payment workflow: precheck → confirmation → invoice → shipping
   - Manages encrypted API key storage and plugin settings
   - Implements payment validation and order status management

3. **ApiClient** - HTTP client for Axytos API:
   - Handles all communication with Axytos sandbox/production APIs
   - Manages authentication via X-API-Key header
   - Implements endpoints: precheck, orderConfirm, createInvoice, updateShippingStatus, cancelOrder

4. **DataFormatter** - Data transformation layer:
   - Converts JTL order objects to Axytos API format
   - Handles address formatting, product categorization, and pricing calculations
   - Ensures data consistency between precheck and confirm calls (critical requirement)

### Payment Flow

1. **Precheck Phase** (`preparePaymentProcess`):
   - Customer selects Axytos payment method
   - Order data is formatted and sent to Axytos for approval
   - Order data is cached to ensure identical data in confirm phase
   - Customer is redirected based on approval/rejection

2. **Confirmation Phase** (`handleNotification`):
   - Order is saved to database with generated order number
   - Cached order data is sent to Axytos for final confirmation
   - Order status is updated to "in processing"
   - Order attributes are stored for tracking

3. **Post-Order Management**:
   - Invoice creation when order is shipped (`orderWasShipped`)
   - Shipping status updates to Axytos
   - Order cancellation and reactivation support

### Admin Interface

- **API Setup Tab**: Configuration of API key and sandbox/production mode
- **Tools Tab**: Administrative tools for order management
- Settings are encrypted using JTL's XTEA encryption service

### Key Integration Points

- **Order Status Hooks**: `HOOK_BESTELLUNGEN_XML_BESTELLSTATUS` for shipping notifications
- **Frontend Hooks**: `HOOK_SMARTY_OUTPUTFILTER` for agreement link injection
- **Cron Jobs**: Background processing for payment status updates (via `CronHelper`)
- **Localization**: Multi-language support (German/English) defined in `info.xml`

### Data Consistency Requirements

The plugin implements a critical workaround for a JTL Shop bug where order data changes between precheck and confirmation phases. The solution:
1. Cache order data after precheck formatting
2. Reuse identical cached data in confirmation phase
3. Log warnings if cached data is missing and regeneration is required

This ensures Axytos receives identical data in both API calls, which is a strict requirement of their system.

## Sister Project

A sister project exists at `/Users/mat/work/axytos-wp/wp-content/plugins/axytos-woocommerce` - an Axytos payment plugin for WooCommerce with the same functionality. In the long run, there should be feature parity between both plugins.