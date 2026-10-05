import { test, expect, Page } from '@playwright/test';

/**
 * The widget is sunset: the Hyvä checkout must not load the Mondu widget SDK,
 * and placing a Mondu order must redirect to the hosted checkout and come back
 * to the Magento success page.
 */

// Required, checked in playwright.config.ts.
const EMAIL = process.env.E2E_EMAIL as string;
const PASSWORD = process.env.E2E_PASSWORD as string;
const PRODUCT_URL = process.env.E2E_PRODUCT_URL ?? '/joust-duffle-bag.html';

async function settle(page: Page) {
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.waitForTimeout(1500);
}

async function login(page: Page) {
  await page.goto('/customer/account/login', { waitUntil: 'domcontentloaded' });
  await page.fill('#email', EMAIL);
  await page.fill('#pass', PASSWORD);
  // Hyvä animates the submit button; Enter submits the form reliably.
  await page.locator('#pass').press('Enter');
  await page.waitForURL(/customer\/account/, { timeout: 30_000 });
}

async function addProductAndOpenCheckout(page: Page) {
  await page.goto(PRODUCT_URL, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1500);
  // native click avoids Hyvä animation "element not stable" flakiness
  await page.evaluate(() => (document.querySelector('#product-addtocart-button') as HTMLElement)?.click());
  await page.waitForTimeout(5000);
  await page.goto('/checkout', { waitUntil: 'domcontentloaded' });
  await settle(page);
}

async function goToPaymentStep(page: Page) {
  // logged-in customer: the saved German address is pre-filled, pick shipping and advance
  await page.evaluate(() => {
    const r = (document.querySelector('#shipping-method-flatrate')
      || document.querySelector('input[name="shipping-method-option"]')) as HTMLInputElement | null;
    if (r) { r.checked = true; r.click(); r.dispatchEvent(new Event('change', { bubbles: true })); }
  });
  await settle(page);

  for (let i = 0; i < 4; i++) {
    if (await page.locator('input[name="payment-method-option"]').count() > 0) break;
    await page.evaluate(() => {
      const btn = [...document.querySelectorAll('button[data-route="payment"]')]
        .find(b => (b as HTMLElement).offsetParent !== null) as HTMLElement | undefined;
      btn?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
    });
    await page.locator('input[name="payment-method-option"]').first()
      .waitFor({ state: 'visible', timeout: 18_000 }).catch(() => {});
    await settle(page);
  }
  await expect(page.locator('input[name="payment-method-option"]').first()).toBeVisible();
}

async function selectMethod(page: Page, code: string) {
  await page.evaluate((value) => {
    const r = document.querySelector(`input[name="payment-method-option"][value="${value}"]`) as HTMLInputElement | null;
    if (r) { r.checked = true; r.click(); r.dispatchEvent(new Event('change', { bubbles: true })); }
  }, code);
  await settle(page);
}

async function placeOrder(page: Page) {
  await page.evaluate(() => {
    const btn = [...document.querySelectorAll('button')]
      .find(b => /place order|bestellung aufgeben|jetzt kaufen|kaufen/i.test(b.textContent ?? '')
        && (b as HTMLElement).offsetParent !== null) as HTMLElement | undefined;
    btn?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
  });
}

test('Mondu order goes through hosted checkout without loading the widget SDK', async ({ page }) => {
  const widgetRequests: string[] = [];
  page.on('request', (request) => {
    const url = new URL(request.url());
    if (url.hostname.includes('mondu') && url.pathname.endsWith('/widget.js')) widgetRequests.push(url.href);
  });

  await login(page);
  await addProductAndOpenCheckout(page);
  await goToPaymentStep(page);
  await selectMethod(page, 'mondu');

  expect(await page.locator('#mondu_sdk_min').count()).toBe(0);
  expect(await page.evaluate(() => typeof (window as any).monduCheckout)).toBe('undefined');

  await placeOrder(page);
  await page.waitForURL('**mondu.ai/**', { timeout: 60_000 });

  const confirmButton = page.getByRole('button', { name: /zahlen mit|pay with|bestätigen|confirm|submit/i }).first();
  await confirmButton.waitFor({ state: 'visible', timeout: 30_000 });
  await confirmButton.click({ force: true });

  await page.waitForURL(/checkout\/onepage\/success/, { timeout: 90_000 });
  expect(widgetRequests).toEqual([]);
});
