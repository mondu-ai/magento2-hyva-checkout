import { test, expect } from '@playwright/test';
import { addProductAndOpenCheckout, goToPaymentStep, login, placeOrder, selectMethod, settle } from './helpers';

/**
 * Net term choice in Hyvä Checkout.
 *
 * Requires the merchant to have more than one term enabled for invoice and exactly
 * one for direct debit (Stores > Configuration > Payment Methods > Mondu > Net terms
 * offered to buyers), and those terms to be ones the Mondu account holds for the
 * buyer's country. The specs read the offered terms from the page rather than
 * hardcoding them.
 */

const NET_TERM = '.mondu-net-term';

test('Invoice offers the enabled terms and the picked one reaches Mondu', async ({ page }) => {
  await login(page);
  await addProductAndOpenCheckout(page);
  await goToPaymentStep(page);
  await selectMethod(page, 'mondu');

  const select = page.locator(`${NET_TERM} select`);
  await expect(select).toBeVisible();

  const offered = await select.locator('option').evaluateAll((options) =>
    options.map((option) => (option as HTMLOptionElement).value)
  );
  expect(offered.length, 'the merchant needs at least two terms enabled for invoice').toBeGreaterThan(1);

  // 30 days is the house default whenever the merchant offers it.
  if (offered.includes('30')) {
    await expect(select).toHaveValue('30');
  }

  // Deliberately not the preselected term: only a term that differs from the default
  // proves the choice travelled to Mondu.
  const preselected = await select.inputValue();
  const picked = offered.find((term) => term !== preselected)!;
  await select.selectOption(picked);
  await settle(page);

  // The choice is stored server side, so it survives a reload of the checkout.
  await page.reload({ waitUntil: 'domcontentloaded' });
  await settle(page);
  await goToPaymentStep(page);
  await selectMethod(page, 'mondu');
  await expect(page.locator(`${NET_TERM} select`)).toHaveValue(picked);

  await placeOrder(page);
  await page.waitForURL('**mondu.ai/**', { timeout: 60_000 });

  // The hosted checkout states the term Mondu is about to authorize.
  await expect(page.locator('body')).toContainText(new RegExp(`${picked} (Tage|Tagen|days)`), { timeout: 30_000 });
});

test('Direct debit with a single enabled term states it instead of asking', async ({ page }) => {
  await login(page);
  await addProductAndOpenCheckout(page);
  await goToPaymentStep(page);
  await selectMethod(page, 'mondusepa');

  const field = page.locator(NET_TERM);
  await expect(field).toBeVisible();
  await expect(field.locator('select')).toHaveCount(0);
  await expect(field.locator('.mondu-net-term-single')).toContainText(/\d+ (Tage|days)/);
});

test('Instalments carry no net term and no Mondu method shows the old privacy notice', async ({ page }) => {
  await login(page);
  await addProductAndOpenCheckout(page);
  await goToPaymentStep(page);

  await selectMethod(page, 'monduinstallment');
  await expect(page.locator(NET_TERM)).toHaveCount(0);

  for (const code of ['mondu', 'mondusepa', 'monduinstallment', 'monduinstallmentbyinvoice', 'mondupaynow']) {
    await selectMethod(page, code);
    await expect(page.locator('body')).not.toContainText(/processing of your personal data|personenbezogenen Daten/);
  }
});
