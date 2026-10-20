import { expect, Page } from '@playwright/test';

// Required, checked in playwright.config.ts.
const EMAIL = process.env.E2E_EMAIL as string;
const PASSWORD = process.env.E2E_PASSWORD as string;
const PRODUCT_URL = process.env.E2E_PRODUCT_URL ?? '/joust-duffle-bag.html';

export async function settle(page: Page) {
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.waitForTimeout(1500);
}

export async function login(page: Page) {
  await page.goto('/customer/account/login', { waitUntil: 'domcontentloaded' });
  await page.fill('#email', EMAIL);
  await page.fill('#pass', PASSWORD);
  // Hyvä animates the submit button; Enter submits the form reliably.
  await page.locator('#pass').press('Enter');
  await page.waitForURL(/customer\/account/, { timeout: 30_000 });
}

export async function addProductAndOpenCheckout(page: Page) {
  await page.goto(PRODUCT_URL, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1500);
  // native click avoids Hyvä animation "element not stable" flakiness
  await page.evaluate(() => (document.querySelector('#product-addtocart-button') as HTMLElement)?.click());
  await page.waitForTimeout(5000);
  await page.goto('/checkout', { waitUntil: 'domcontentloaded' });
  await settle(page);
}

export async function goToPaymentStep(page: Page) {
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

export async function selectMethod(page: Page, code: string) {
  await page.evaluate((value) => {
    const r = document.querySelector(`input[name="payment-method-option"][value="${value}"]`) as HTMLInputElement | null;
    if (r) { r.checked = true; r.click(); r.dispatchEvent(new Event('change', { bubbles: true })); }
  }, code);
  await settle(page);
}

export async function placeOrder(page: Page) {
  await page.evaluate(() => {
    const btn = [...document.querySelectorAll('button')]
      .find(b => /place order|bestellung aufgeben|jetzt kaufen|kaufen/i.test(b.textContent ?? '')
        && (b as HTMLElement).offsetParent !== null) as HTMLElement | undefined;
    btn?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
  });
}
