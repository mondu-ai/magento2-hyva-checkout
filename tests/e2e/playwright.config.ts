import { defineConfig, devices } from '@playwright/test';

/**
 * E2E for the Mondu Hyvä Checkout payment flow.
 *
 * Preconditions on the target Magento (see README.md):
 *  - Hyvä theme + Hyvä Checkout active, Magento in production mode.
 *  - Mondu core configured (sandbox) with the 5 payment methods enabled.
 *  - A customer with a German default address exists (env E2E_EMAIL / E2E_PASSWORD).
 *  - The product E2E_PRODUCT_URL is a simple, in-stock product.
 */
export default defineConfig({
  testDir: './tests',
  timeout: 180_000,
  expect: { timeout: 20_000 },
  fullyParallel: false,
  workers: 1,
  retries: 1,
  reporter: [['list']],
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'https://m2-ivan-local.casa-kuhl.de',
    ignoreHTTPSErrors: true,
    locale: 'de-DE',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
});
