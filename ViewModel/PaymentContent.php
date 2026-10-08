<?php

declare(strict_types=1);

namespace Mondu\MonduPaymentHyva\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

class PaymentContent implements ArgumentInterface
{
    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * Returns the configured description of the Mondu payment method, or '' when none is set.
     *
     * @param string $methodCode
     * @return string
     */
    public function getDescription(string $methodCode): string
    {
        $description = $this->scopeConfig->getValue(
            "payment/{$methodCode}/description",
            ScopeInterface::SCOPE_STORE
        );

        return $description ? (string) __($description) : '';
    }
}
