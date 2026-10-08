<?php

declare(strict_types=1);

namespace Mondu\MonduPaymentHyva\Magewire\Checkout\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magewirephp\Magewire\Component;
use Mondu\Mondu\Helpers\PaymentTerms;
use Mondu\Mondu\Model\Payment\AsyncOrderFields;
use Mondu\Mondu\Model\Ui\NetTermConfigProvider;

/**
 * Lets the buyer choose the net term of a Mondu method in Hyvä Checkout.
 *
 * The terms on offer are the ones the storefront checkout of the Mondu module offers:
 * the merchant's enabled terms for the method, narrowed to what the buyer's country
 * allows. The choice is stored on the quote payment under the field the Mondu module
 * sends to the API, so it travels with the order exactly as a Luma checkout one does.
 */
class NetTerm extends Component
{
    /**
     * Term the order is placed on, in days.
     *
     * Untyped on purpose: the select posts it back as a string, which updatedNetTerm()
     * turns into the stored integer, and Magewire assigns it before that hook runs.
     *
     * @var int|string|null
     */
    public $netTerm = null;

    /**
     * Terms the buyer may choose from, in days.
     *
     * @var int[]
     */
    public array $netTerms = [];

    /**
     * An address change can change which terms the country allows.
     *
     * @var array<string, string>
     */
    protected $listeners = [
        'billing_address_saved' => 'loadNetTerms',
        'shipping_address_saved' => 'loadNetTerms',
        'billing_as_shipping_address_updated' => 'loadNetTerms',
    ];

    /**
     * @var array<string, string>
     */
    protected $loader = [
        'netTerm' => 'Saving payment term',
    ];

    /**
     * @param CheckoutSession $checkoutSession
     * @param CartRepositoryInterface $quoteRepository
     * @param NetTermConfigProvider $netTermConfigProvider
     * @param PaymentTerms $paymentTerms
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly NetTermConfigProvider $netTermConfigProvider,
        private readonly PaymentTerms $paymentTerms,
    ) {
    }

    /**
     * Loads the terms on first render.
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @return void
     */
    public function mount(): void
    {
        $this->loadNetTerms();
    }

    /**
     * Reads the terms for the method and country, and keeps the stored term within them.
     *
     * A term the buyer picked survives as long as it is still on offer. Otherwise the
     * preferred one is stored right away, so the order carries the term the buyer saw
     * even when they never touch the field.
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @return void
     */
    public function loadNetTerms(): void
    {
        $quote = $this->checkoutSession->getQuote();
        $this->netTerms = $this->getNetTermsFor($quote);

        $stored = $quote->getPayment()->getAdditionalInformation(AsyncOrderFields::FIELD_NET_TERM);
        $stored = is_numeric($stored) ? (int) $stored : null;

        $this->netTerm = in_array($stored, $this->netTerms, true)
            ? $stored
            : $this->paymentTerms->pickDefault($this->netTerms);

        $this->storeNetTerm($quote, $this->netTerm);
    }

    /**
     * Stores the term the buyer picked.
     *
     * @param mixed $value
     * @return int|null
     */
    public function updatedNetTerm(mixed $value): ?int
    {
        try {
            $quote = $this->checkoutSession->getQuote();

            // Checked against the terms on offer now, not against the list the browser sent
            // back: only a term on offer may reach the API, which refuses the rest with a 422.
            $this->netTerms = $this->getNetTermsFor($quote);
            $netTerm = is_numeric($value) ? (int) $value : null;
            if (!in_array($netTerm, $this->netTerms, true)) {
                $netTerm = $this->paymentTerms->pickDefault($this->netTerms);
            }

            $this->storeNetTerm($quote, $netTerm);
        } catch (LocalizedException $exception) {
            $this->dispatchErrorMessage($exception->getMessage());
            $netTerm = null;
        }

        return $netTerm;
    }

    /**
     * Terms on offer for the quote's Mondu method and the buyer's country.
     *
     * @param CartInterface $quote
     * @return int[]
     */
    private function getNetTermsFor(CartInterface $quote): array
    {
        $method = (string) $quote->getPayment()->getMethod();
        $config = $this->netTermConfigProvider->getConfig();
        $byCountry = $config['monduNetTerms']['byMethod'][$method] ?? [];
        if ($byCountry === []) {
            return [];
        }

        $countryId = $quote->getBillingAddress()->getCountryId()
            ?: $quote->getShippingAddress()->getCountryId();

        return $byCountry[$countryId] ?? $byCountry[NetTermConfigProvider::ANY_COUNTRY] ?? [];
    }

    /**
     * Saves the term on the quote payment, or removes it when none is on offer.
     *
     * @param CartInterface $quote
     * @param int|null $netTerm
     * @return void
     */
    private function storeNetTerm(CartInterface $quote, ?int $netTerm): void
    {
        $payment = $quote->getPayment();
        $current = $payment->getAdditionalInformation(AsyncOrderFields::FIELD_NET_TERM);
        if ((string) $current === (string) $netTerm) {
            return;
        }

        if ($netTerm === null) {
            $payment->unsAdditionalInformation(AsyncOrderFields::FIELD_NET_TERM);
        } else {
            $payment->setAdditionalInformation(AsyncOrderFields::FIELD_NET_TERM, $netTerm);
        }

        $this->quoteRepository->save($quote);
    }
}
