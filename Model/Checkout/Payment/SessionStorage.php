<?php

declare(strict_types=1);

namespace Mondu\MonduPaymentHyva\Model\Checkout\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;

class SessionStorage
{
    private const RESPONSE_KEY = 'mondu_response';

    public function __construct(private readonly CheckoutSession $checkoutSession)
    {
    }

    public function saveResponse(array $response): void
    {
        $this->checkoutSession->setData(self::RESPONSE_KEY, $response);
    }

    public function getResponse(): ?array
    {
        return $this->checkoutSession->getData(self::RESPONSE_KEY);
    }

    public function clearResponse(): void
    {
        $this->checkoutSession->unsData(self::RESPONSE_KEY);
    }
}
