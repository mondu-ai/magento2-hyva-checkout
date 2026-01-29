<?php

declare(strict_types=1);

namespace Mondu\MonduPaymentHyva\Model\Checkout\Payment;

use Exception;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Hyva\Checkout\Model\Magewire\Payment\AbstractPlaceOrderService;
use Hyva\Checkout\Model\Magewire\Payment\DefaultOrderData;
use Hyva\Checkout\Model\Magewire\Payment\PlaceOrderServiceInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\Quote;
use Magewirephp\Magewire\Component;
use Mondu\Mondu\Service\TransactionService;

class MonduPlaceOrderService extends AbstractPlaceOrderService implements PlaceOrderServiceInterface
{
    /**
     * @param CustomerDataProvider $customerDataProvider
     * @param SessionStorage $sessionStorage
     * @param TransactionService $transactionService
     * @param CartManagementInterface $cartManagement
     * @param DefaultOrderData|null $orderData
     */
    public function __construct(
        private readonly CustomerDataProvider $customerDataProvider,
        private readonly SessionStorage $sessionStorage,
        private readonly TransactionService $transactionService,
        CartManagementInterface $cartManagement,
        ?DefaultOrderData $orderData = null,
    ) {
        parent::__construct($cartManagement, $orderData);
    }

    /**
     * Creates Mondu transaction for the given quote and stores the response.
     *
     * @param Quote $quote
     * @throws LocalizedException
     * @return int
     */
    public function placeOrder(Quote $quote): int
    {
        try {
            $response = $this->transactionService->createTransaction([
                'email' => $this->customerDataProvider->getEmail($quote),
                'user-agent' => $this->customerDataProvider->getUserAgent(),
                'payment_method' => $this->customerDataProvider->getPaymentMethod($quote),
            ]);

            if (empty($response['token']) || !empty($response['error'])) {
                throw new LocalizedException(
                    __('Mondu API error: %1', $response['message'] ?? 'Invalid response')
                );
            }
            $this->sessionStorage->saveResponse($response);

            return 0;
        } catch (Exception $e) {
            throw new LocalizedException(__('Failed to initiate Mondu order: %1', $e->getMessage()));
        }
    }

    /**
     * Evaluates Mondu response and returns redirect or widget instructions.
     *
     * @param EvaluationResultFactory $resultFactory
     * @param int|null $orderId
     * @return EvaluationResultInterface
     */
    public function evaluateCompletion(
        EvaluationResultFactory $resultFactory,
        ?int $orderId = null
    ): EvaluationResultInterface {
        $response = $this->sessionStorage->getResponse();
        $this->sessionStorage->clearResponse();

        if ($response && $response['source'] === 'hosted' && !empty($response['hosted_checkout_url'])) {
            return $resultFactory->createRedirect($response['hosted_checkout_url']);
        }

        if ($response && $response['source'] === 'widget') {
            $validation = $resultFactory->createValidation('mondu-widget');
            $validation->withDetails(['token' => $response['token']]);
            return $validation;
        }

        return $resultFactory->createErrorMessage(
            'Unable to proceed: missing hosted checkout URL or invalid response.'
        );
    }

    /**
     * @return bool
     */
    public function canRedirect(): bool
    {
        return true;
    }

    /**
     * Handles exception during order placement and updates Hyvä UI.
     *
     * @param Exception $exception
     * @param Component $component
     * @param Quote $quote
     * @throws LocalizedException
     * @return void
     */
    public function handleException(Exception $exception, Component $component, Quote $quote): void
    {
        $component->getEvaluationResultBatch()->push(
            $component->getEvaluationResultBatch()->getFactory()->createErrorMessage(
                'Order placement failed: ' . $exception->getMessage()
            )
        );
        $component->dispatchBrowserEvent('process-stop');
        throw new LocalizedException(__('Order placement failed: %1', $exception->getMessage()));
    }
}
