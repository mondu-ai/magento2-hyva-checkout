<?php

declare(strict_types=1);

namespace Mondu\MonduPaymentHyva\Model\Checkout\Payment;

use Mondu\Mondu\Helpers\ABTesting\ABTesting;
use Mondu\Mondu\Helpers\OrderHelper;
use Mondu\Mondu\Model\Request\Factory as RequestFactory;

class TransactionProcessor
{
    public function __construct(
        private readonly ABTesting $abTesting,
        private readonly RequestFactory $requestFactory,
    ) {
    }

    public function process(array $data): array
    {
        $result = $this->requestFactory
            ->create(RequestFactory::TRANSACTIONS_REQUEST_METHOD)
            ->process($data);
        $response = $this->abTesting->formatApiResult($result);

        if (!$response['error'] && isset($result['body']['order'])) {
            $this->handleDeclinedOrder($result['body']['order'], $response);
        }

        return $response;
    }

    private function handleDeclinedOrder(array $order, array &$response): void
    {
        if (($order['state'] ?? '') === OrderHelper::DECLINED) {
            $response['error'] = 1;
            $response['message'] = __('Order has been declined');
        }
    }
}
