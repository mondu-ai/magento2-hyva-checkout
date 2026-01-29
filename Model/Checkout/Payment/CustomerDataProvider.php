<?php

declare(strict_types=1);

namespace Mondu\MonduPaymentHyva\Model\Checkout\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\RequestInterface;
use Magento\Quote\Api\Data\CartInterface;
use Mondu\Mondu\Helpers\PaymentMethod;

class CustomerDataProvider
{
    /**
     * @param CheckoutSession $checkoutSession
     * @param RequestInterface $request
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly RequestInterface $request,
    ) {
    }

    /**
     * Returns the customer's email from the quote or guest session.
     *
     * @param CartInterface $quote
     * @return string
     */
    public function getEmail(CartInterface $quote): string
    {
        return $quote->getCustomerEmail() ?: ($this->checkoutSession->getGuestCustomerEmail() ?? '');
    }

    /**
     * Returns the Mondu payment method identifier based on the quote's selected method.
     *
     * @param CartInterface $quote
     * @return string
     */
    public function getPaymentMethod(CartInterface $quote): string
    {
        return match ($quote->getPayment()->getMethod()) {
            'mondusepa' => PaymentMethod::DIRECT_DEBIT,
            'monduinstallment' => PaymentMethod::INSTALLMENT,
            'monduinstallmentbyinvoice' => PaymentMethod::INSTALLMENT_BY_INVOICE,
            default => 'invoice',
        };
    }

    /**
     * Returns the User-Agent header from the request or a default value.
     *
     * @return string
     */
    public function getUserAgent(): string
    {
        return $this->request->getHeader('User-Agent') ?? 'Magento/HyvaCheckout';
    }
}
