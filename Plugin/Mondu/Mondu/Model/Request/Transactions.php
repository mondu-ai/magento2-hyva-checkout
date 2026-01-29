<?php

declare(strict_types=1);

namespace Mondu\MonduPaymentHyva\Plugin\Mondu\Mondu\Model\Request;

use Hyva\Checkout\Model\ConfigData\HyvaThemes\Checkout as HyvaCheckoutConfig;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\CartInterface;
use Mondu\Mondu\Model\Request\Transactions as RequestTransactions;

class Transactions
{
    /**
     * @param HyvaCheckoutConfig $checkoutConfig
     */
    public function __construct(private readonly HyvaCheckoutConfig $checkoutConfig)
    {
    }

    /**
     * Replaces billing address params based on Hyvä EAV form field mapping.
     *
     * @param RequestTransactions $subject
     * @param array $result
     * @param CartInterface $quote
     * @return array
     */
    public function afterGetBillingAddressParams(
        RequestTransactions $subject,
        array $result,
        CartInterface $quote
    ): array {
        $billingAttrMapping = $this->checkoutConfig->getBillingEavAttributeFormFieldsMapping();
        if (empty($billingAttrMapping)) {
            return $result;
        }

        return $this->extractHyvaAddressParams($quote->getBillingAddress(), $billingAttrMapping);
    }

    /**
     * Replaces shipping address params based on Hyvä EAV form field mapping.
     *
     * @param RequestTransactions $subject
     * @param array $result
     * @param CartInterface $quote
     * @return array
     */
    public function afterGetShippingAddressParams(
        RequestTransactions $subject,
        array $result,
        CartInterface $quote
    ): array {
        $shippingAttrMapping = $this->checkoutConfig->getShippingEavAttributeFormFieldsMapping();
        if (empty($shippingAttrMapping)) {
            return $result;
        }

        return $this->extractHyvaAddressParams($quote->getShippingAddress(), $shippingAttrMapping);
    }

    /**
     * Maps address fields based on Hyvä field mapping and formats for Mondu.
     *
     * @param AddressInterface|null $address
     * @param array $attributeMapping
     * @return array
     */
    private function extractHyvaAddressParams(?AddressInterface $address, array $attributeMapping): array
    {
        if (!$address) {
            return [];
        }

        $params = [];
        $fieldMap = [
            'country_id' => 'country_code',
            'postcode' => 'zip_code',
            'region' => 'state',
            'street.0' => 'address_line1',
            'street.1' => 'address_line2',
            'street.3' => 'address_line3',
            'street.4' => 'address_line4',
        ];

        $street = (array) $address->getStreet();
        foreach (array_keys($attributeMapping) as $key) {
            $value = $this->resolveAddressValue($address, $key, $street);
            if ($value === null || $value === '') {
                continue;
            }

            $params[$fieldMap[$key] ?? $key] = $value;
        }

        if (!empty($params['address_line1']) && $address->getStreetNumber()) {
            $params['address_line1'] .= ', ' . $address->getStreetNumber();
        }

        return $params;
    }

    /**
     * Resolves individual address field value from address object or street array.
     *
     * @param AddressInterface $address
     * @param string $key
     * @param array $street
     * @return mixed
     */
    private function resolveAddressValue(AddressInterface $address, string $key, array $street): mixed
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
