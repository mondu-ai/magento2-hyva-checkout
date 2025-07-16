<?php

declare(strict_types=1);

namespace Mondu\MonduPaymentHyva\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Phrase;
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
     * Returns configured Mondu payment description with privacy policy link.
     *
     * @param string $methodCode
     * @return Phrase|string
     */
    public function getDescription(string $methodCode): Phrase|string
    {
        $privacyText = __(
            'Information on the processing of your personal data by Mondu GmbH can be found '
            . "<a href='https://www.mondu.ai/de/datenschutzgrundverordnung-kaeufer/' target='_blank'>here.</a>"
        );

        $description = $this->scopeConfig->getValue(
            "payment/{$methodCode}/description",
            ScopeInterface::SCOPE_STORE
        );

        return $description ? __($description) . '<br><br>' . $privacyText : $privacyText;
    }
}
