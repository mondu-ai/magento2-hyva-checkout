# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Magento 2 module (`Mondu_MonduPaymentHyva`) that adds Hyva Checkout compatibility for the Mondu payment module (`Mondu_Mondu`). It bridges Mondu's B2B payment methods (invoice, SEPA, installment, installment by invoice, pay now) with Hyva's Magewire-based checkout.

**Package:** `mondu/magento2-hyva-payment` (v1.1.0)
**Namespace:** `Mondu\MonduPaymentHyva`
**Dependencies:** PHP >=8.2, Magento CE 2.4.7+, `mondu/magento2-payment` >=2.9.4 (the release with the net term selection, PT-3964), `hyva-themes/magento2-hyva-checkout` ^1.3

## Common Commands

```bash
# Install after changes
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento cache:flush
```

No unit tests, linter, or build pipeline exist in this module. A Playwright E2E check of the hosted checkout flow lives in `tests/e2e/` (see its README).

## Architecture

### Payment Flow

1. Customer selects a Mondu method in Hyva Checkout UI
2. `MonduPlaceOrderService::placeOrder()` creates a Mondu transaction via `TransactionService`
3. Plugin intercepts address data, remapping Hyva EAV form fields to Mondu's address format
4. Response stored in session (`SessionStorage`)
5. `evaluateCompletion()` redirects to `hosted_checkout_url`; if the response has none, it returns an error message

### Key Components

- **`Model/Checkout/Payment/MonduPlaceOrderService.php`** — Core service implementing `PlaceOrderServiceInterface`. Handles transaction creation, completion evaluation (hosted checkout redirect), and error handling with Magewire browser events (`process-stop`).

- **`Plugin/Mondu/Mondu/Model/Request/Transactions.php`** — After-plugins on `afterGetBillingAddressParams` / `afterGetShippingAddressParams`. Translates Hyva EAV attribute mappings to Mondu's address field names (`country_id` → `country_code`, `postcode` → `zip_code`, etc.). Appends `street_number` to `address_line1` when present.

- **`Model/Checkout/Payment/CustomerDataProvider.php`** — Extracts email (guest vs. registered), payment method mapping, and user-agent from the quote/request.

- **`Model/Checkout/Payment/SessionStorage.php`** — Session wrapper storing Mondu API responses under key `mondu_response`.

- **`ViewModel/PaymentContent.php`** — Provides the payment method description from store config (no privacy notice; it was dropped from the Mondu module as well).

- **`Magewire/Checkout/Payment/NetTerm.php`** — Magewire component of the invoice, direct debit and pay now method blocks. Offers the net terms the Mondu module's `NetTermConfigProvider` allows for the method and the buyer's country (a select, or plain text for a single term), preselects via `PaymentTerms::pickDefault()`, and stores the choice on the quote payment as `mondu_net_term`, the field the Mondu module sends as `net_term`. Re-reads the terms on the address events.

### DI Configuration

- **`etc/di.xml`** — Registers the Transactions plugin (`hyva_mondu_context_plugin`)
- **`etc/frontend/di.xml`** — Maps all 5 Mondu payment method codes (`mondu`, `mondusepa`, `monduinstallment`, `monduinstallmentbyinvoice`, `mondupaynow`) to `MonduPlaceOrderService` via `PlaceOrderServiceProvider`

### Frontend

Templates use Magewire reactive bindings (`wire:model`) and Tailwind CSS. Mondu methods are identified by `str_starts_with($methodCode, 'mondu')`. No Mondu JavaScript is loaded on the storefront: the buyer completes payment on the Mondu hosted checkout page.

## Conventions

- All PHP classes use `declare(strict_types=1)` and constructor property promotion
- No custom routes, events, or observers: integration is via DI plugins, the place order service and the net term Magewire component
- Address field mapping lives in the Transactions plugin, not in the place order service
