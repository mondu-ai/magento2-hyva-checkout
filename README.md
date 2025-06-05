# Mondu_MonduPaymentHyva

This module provides Hyvä Checkout compatibility for the official Mondu payment module (`Mondu_Mondu`) in Magento 2.

## Requirements

- Magento CE 2.4.7 or higher
- PHP 8.3 or 8.4
- [Hyvä Checkout](https://docs.hyva.io/checkout/index.html) (license required)
- [Mondu Magento 2 Payment Module](https://github.com/mondu-ai/magento2-checkout)

## Installation via Composer (Recommended)

```bash
composer require mondu/magento2-hyva-payment
```

> This module depends on **Hyvä Checkout**, which in turn requires **Hyvä Theme**.
> During installation, you will be prompted to authenticate with Hyvä’s private GitLab repository.
> 
> Refer to the official documentation for setup instructions:
> - [Hyvä Theme Installation Guide](https://docs.hyva.io/hyva-themes/getting-started/index.html#for-licensees)
> - [Hyvä Checkout Installation Guide](https://docs.hyva.io/checkout/hyva-checkout/getting-started/index.html#installation)

## Magento Admin Configuration

### 1. Enable Hyvä Theme

Ensure **Hyvä Default** is enabled under:  
`Content → Design → Configuration`

### 2. Configure Hyvä Checkout

Navigate to:  
`Stores → Configuration → Hyvä Themes → Checkout → General → Checkout`  
Set to **Hyvä Default**

### 3. Configure Mondu Payment

Navigate to:  
`Stores → Configuration → Sales → Payment Methods → Mondu`

- Enter your **API key**
- Enable required Mondu payment methods

## Manual Installation

To install this module manually:

1. Download the latest release from the GitHub repository:  
   [https://github.com/mondu-ai/magento2-hyva-checkout](https://github.com/mondu-ai/magento2-hyva-checkout)
2. Unzip the archive.
3. Create the directory:  
   `app/code/Mondu/MonduPaymentHyva`
4. Copy the extracted files into the created directory.
5. Run Magento commands:
   ```bash
   php bin/magento setup:upgrade
   php bin/magento setup:di:compile
   php bin/magento setup:static-content:deploy
   php bin/magento cache:flush
   ```
