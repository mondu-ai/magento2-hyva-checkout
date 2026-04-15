# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Magento 2 module (`Mondu_MonduPaymentHyva`) that adds Hyva Checkout compatibility for the Mondu payment module (`Mondu_Mondu`). It bridges Mondu's B2B payment methods (invoice, SEPA, installment, installment by invoice, pay now) with Hyva's Magewire-based checkout.

**Package:** `mondu/magento2-hyva-payment` (v1.0.4)
**Namespace:** `Mondu\MonduPaymentHyva`
**Dependencies:** PHP >=8.2, Magento CE 2.4.7+, `mondu/magento2-payment` >=2.5.0, `hyva-themes/magento2-hyva-checkout` ^1.3

## Common Commands

```bash
# Install after changes
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento cache:flush
```

No test suite, linter, or build pipeline exists in this module.

## Architecture

### Payment Flow

1. Customer selects a Mondu method in Hyva Checkout UI
2. Mondu SDK loads dynamically via `mondu.phtml` (listens to `checkout:payment:method-activate` browser event)
3. `MonduPlaceOrderService::placeOrder()` creates a Mondu transaction via `TransactionService`
4. Plugin intercepts address data, remapping Hyva EAV form fields to Mondu's address format
5. Response stored in session (`SessionStorage`)
6. `evaluateCompletion()` routes to either hosted checkout redirect or widget-based token validation

### Key Components

- **`Magewire/Checkout/Payment/Mondu.php`** — Magewire component that injects the SDK URL from `ConfigProvider`. Loaded in the checkout layout container `hyva.checkout.api-v1.after`.

- **`Model/Checkout/Payment/MonduPlaceOrderService.php`** — Core service implementing `PlaceOrderServiceInterface`. Handles transaction creation, completion evaluation (hosted redirect vs. widget), and error handling with Magewire browser events (`process-stop`).

- **`Plugin/Mondu/Mondu/Model/Request/Transactions.php`** — After-plugins on `afterGetBillingAddressParams` / `afterGetShippingAddressParams`. Translates Hyva EAV attribute mappings to Mondu's address field names (`country_id` → `country_code`, `postcode` → `zip_code`, etc.). Appends `street_number` to `address_line1` when present.

- **`Model/Checkout/Payment/CustomerDataProvider.php`** — Extracts email (guest vs. registered), payment method mapping, and user-agent from the quote/request.

- **`Model/Checkout/Payment/SessionStorage.php`** — Session wrapper storing Mondu API responses under key `mondu_response`.

- **`ViewModel/PaymentContent.php`** — Provides payment method descriptions from store config with appended privacy policy link.

### DI Configuration

- **`etc/di.xml`** — Registers the Transactions plugin (`hyva_mondu_context_plugin`)
- **`etc/frontend/di.xml`** — Maps all 5 Mondu payment method codes (`mondu`, `mondusepa`, `monduinstallment`, `monduinstallmentbyinvoice`, `mondupaynow`) to `MonduPlaceOrderService` via `PlaceOrderServiceProvider`

### Frontend

Templates use Magewire reactive bindings (`wire:model`) and Tailwind CSS. Mondu methods are identified by `str_starts_with($methodCode, 'mondu')`. The SDK script tag is injected once (deduped by `id="mondu_sdk_min"`) and registered with Magento's CSP.

## Conventions

- All PHP classes use `declare(strict_types=1)` and constructor property promotion
- No custom routes, events, or observers — integration is via DI plugins and Magewire components
- Address field mapping lives in the Transactions plugin, not in the place order service
