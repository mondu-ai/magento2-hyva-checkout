<?php

declare(strict_types=1);

namespace Mondu\MonduPaymentHyva\Magewire\Checkout\Payment;

use Magewirephp\Magewire\Component;
use Mondu\Mondu\Model\Ui\ConfigProvider;

class Mondu extends Component
{
    public string $sdkUrl;

    public function __construct(private readonly ConfigProvider $configProvider)
    {
        $this->sdkUrl = $this->configProvider->getSdkUrl();
    }
}
