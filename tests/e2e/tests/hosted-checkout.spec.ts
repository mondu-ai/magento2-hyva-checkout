import { test, expect } from '@playwright/test';
import { addProductAndOpenCheckout, goToPaymentStep, login, placeOrder, selectMethod } from './helpers';

/**
 * The widget is sunset: the Hyvä checkout must not load the Mondu widget SDK,
 * and placing a Mondu order must redirect to the hosted checkout and come back
 * to the Magento success page.
 */

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
