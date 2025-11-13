<?php

declare(strict_types=1);

namespace Mondu\MonduPaymentHyva\Preference\Mondu\Mondu\Model\Request;

use Hyva\Checkout\Model\ConfigData\HyvaThemes\Checkout as HyvaCheckoutConfig;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Cart\CartTotalRepository;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Mondu\Mondu\Helpers\BuyerParams\BuyerParamsInterface;
use Mondu\Mondu\Helpers\Logger\Logger as MonduFileLogger;
use Mondu\Mondu\Helpers\OrderHelper;
use Mondu\Mondu\Helpers\PaymentMethod;
use Mondu\Mondu\Helpers\Request\UrlBuilder;
use Mondu\Mondu\Model\Request\Transactions as OriginalTransactions;

class Transactions extends OriginalTransactions
{
    private string $fallbackEmail;

    public function __construct(
        Curl $curl,
        private readonly BuyerParamsInterface $buyerParams,
        private readonly CartTotalRepository $cartTotalRepository,
        private readonly CheckoutSession $checkoutSession,
        private readonly UrlBuilder $monduUrlBuilder,
        private readonly MonduFileLogger $monduFileLogger,
        private readonly OrderHelper $orderHelper,
        private readonly Resolver $store,
        private readonly UrlInterface $urlBuilder,
        private readonly HyvaCheckoutConfig $checkoutConfig
    ) {
        parent::__construct(
            $curl,
            $buyerParams,
            $cartTotalRepository,
            $checkoutSession,
            $monduUrlBuilder,
            $monduFileLogger,
            $orderHelper,
            $store,
            $urlBuilder
        );
    }

    public function request($_params = []): array
    {
        try {
            if ($_params['email']) {
                $this->fallbackEmail = $_params['email'];
            }
            $params = $this->getRequestParams();

            if (in_array(
                $_params['payment_method'],
                [PaymentMethod::DIRECT_DEBIT, PaymentMethod::INSTALLMENT, PaymentMethod::INSTALLMENT_BY_INVOICE])
            ) {
                $params['payment_method'] = $_params['payment_method'];
            }

            $params = json_encode($params);

            $url = $this->_configProvider->getApiUrl('orders');

            $this->curl->addHeader('X-Mondu-User-Agent', $_params['user-agent']);

            $result = $this->sendRequestWithParams('post', $url, $params);
            $data = json_decode($result, true);
            $this->_checkoutSession->setMonduid($data['order']['uuid'] ?? null);

            if (!isset($data['order']['uuid'])) {
                return [
                    'error' => 1,
                    'body' => json_decode($result, true),
                    'message' => __('Error placing an order Please try again later.')
                ];
            } else {
                return [
                    'error' => 0,
                    'body' => json_decode($result, true),
                    'message' => __('Success')
                ];
            }
        } catch (\Exception $e) {
            $this->monduFileLogger->error('Error while creating an order', [
                'message' => $e->getMessage(),
                'trace' => $e->getTrace()
            ]);
            return [
                'error' => 1,
                'body' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function getBuyerParams(Quote $quote): array
    {
        $params = [];
        if (($billing = $quote->getBillingAddress()) !== null) {
            $params = [
                'is_registered' => (bool) $quote->getCustomer()->getId(),
                'external_reference_id' => $quote->getCustomerId() ? (string) $quote->getCustomerId() : null,
                'email' => $billing->getEmail() ??
                        $quote->getShippingAddress()->getEmail() ??
                        $quote->getCustomerEmail() ??
                        $this->fallbackEmail,
                'company_name' => $billing->getCompany(),
                'first_name' => $billing->getFirstname(),
                'last_name' => $billing->getLastname(),
                'phone' => $billing->getTelephone()
            ];

            $params = $this->buyerParams->getBuyerParams($params, $quote);
        }
        return $params;
    }

    private function resolveAddressValue(Address $address, string $key, array $street): mixed
    {
        if (str_starts_with($key, 'street.')) {
            $index = (int) explode('.', $key)[1];
            return $street[$index] ?? null;
        }

        $method = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
        if (method_exists($address, $method)) {
            return $address->$method();
        }

        return null;
    }
}
