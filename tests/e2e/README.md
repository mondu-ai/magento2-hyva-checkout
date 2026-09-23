# Mondu Hyvä Checkout — E2E

Playwright end-to-end check of the Mondu payment flow in Hyvä Checkout.

## What it asserts

1. The payment step loads no Mondu widget SDK (`widget.js`, `#mondu_sdk_min`, `window.monduCheckout`).
2. Placing a Mondu invoice order redirects to the Mondu hosted checkout.
3. Confirming on the hosted checkout returns the buyer to the Magento success page.

## Preconditions on the target Magento

- Hyvä theme + Hyvä Checkout active; Magento in **production mode**
  (developer mode on PHP 8.4/8.5 turns deprecations into exceptions and breaks the
  Magewire checkout, the payment step never renders).
- Mondu core (`Mondu_Mondu`) configured in sandbox with the 5 methods enabled.
- A customer with a **German default address** exists (Mondu methods target DE/AT).
- The product at `E2E_PRODUCT_URL` is a simple, in-stock product.

## Run

```bash
npm install
E2E_BASE_URL=https://your-shop.example \
E2E_EMAIL=buyer@example.com \
E2E_PASSWORD=secret \
E2E_PRODUCT_URL=/joust-duffle-bag.html \
npx playwright test
```
