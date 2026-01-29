<?php

declare(strict_types=1);

namespace Mondu\MonduPaymentHyva\Model\Checkout\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;

class SessionStorage
{
    private const RESPONSE_KEY = 'mondu_response';

    /**
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(private readonly CheckoutSession $checkoutSession)
    {
    }

    /**
     * Saves Mondu API response in session.
     *
     * @param array $response
     * @return void
     */
    public function saveResponse(array $response): void
    {
        $this->checkoutSession->setData(self::RESPONSE_KEY, $response);
    }

    /**
     * Returns Mondu API response from session if available.
     *
     * @return array|null
     */
    public function getResponse(): ?array
    {
        return $this->checkoutSession->getData(self::RESPONSE_KEY);
    }

    /**
     * Clears stored Mondu response from session.
     *
     * @return void
     */
    public function clearResponse(): void
    {
        $this->checkoutSession->unsData(self::RESPONSE_KEY);
    }
}
