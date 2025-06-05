<?php

declare(strict_types=1);

namespace Mondu\MonduPaymentHyva\Model\Checkout\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\RequestInterface;
use Magento\Quote\Model\Quote;

class CustomerDataProvider
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly RequestInterface $request,
    ) {
    }

    public function getEmail(Quote $quote): string
    {
        return $quote->getCustomerEmail() ?: ($this->checkoutSession->getGuestCustomerEmail() ?? '');
    }

    public function getPaymentMethod(Quote $quote): string
    {
        return match ($quote->getPayment()->getMethod()) {
            'mondusepa' => 'direct_debit',
            'monduinstallment' => 'installment',
            'monduinstallmentbyinvoice' => 'installment_by_invoice',
            default => 'invoice',
        };
    }

    public function getUserAgent(): string
    {
        return $this->request->getHeader('User-Agent') ?? 'Magento/HyvaCheckout';
    }
}
