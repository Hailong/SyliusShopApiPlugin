<?php

declare(strict_types=1);

namespace Sylius\ShopApiPlugin\Controller\Order;

use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use League\Tactician\CommandBus;
use Payum\Core\Payum;
use Payum\Core\Security\GenericTokenFactoryInterface;
use Payum\Core\Security\TokenInterface;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\ShopApiPlugin\Command\Pay;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

final class PayAction
{
    /**
     * @var Payum
     */
    private $payum;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var ViewHandlerInterface
     */
    private $viewHandler;

    /**
     * @var CommandBus
     */
    private $bus;

    /**
     * @param ViewHandlerInterface $viewHandler
     * @param CommandBus $bus
     */
    public function __construct(
        Payum $payum,
        OrderRepositoryInterface $orderRepository,
        ViewHandlerInterface $viewHandler,
        CommandBus $bus
    ) {
        $this->payum = $payum;
        $this->orderRepository = $orderRepository;
        $this->viewHandler = $viewHandler;
        $this->bus = $bus;
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function __invoke(Request $request)
    {
        /** @var OrderInterface $order */
        $order = $this->orderRepository->findOneBy(['tokenValue' => $request->attributes->get('token')]);

        Assert::notNull($order, 'Order has not been found.');

        $payment = $order->getLastPayment(PaymentInterface::STATE_NEW);

        $token = $this->provideTokenBasedOnPayment($payment, array());

        return $this->viewHandler->handle(View::create(array(
            'target_url' => $token->getTargetUrl(),
        )));
    }

    private function getTokenFactory(): GenericTokenFactoryInterface
    {
        return $this->payum->getTokenFactory();
    }

    private function getHttpRequestVerifier(): HttpRequestVerifierInterface
    {
        return $this->payum->getHttpRequestVerifier();
    }

    private function provideTokenBasedOnPayment(PaymentInterface $payment, array $redirectOptions): TokenInterface
    {
        $gatewayName = 'miniprog';

        $token = $this->getTokenFactory()->createCaptureToken(
            $gatewayName,
            $payment,
            'sylius_shop_order_after_pay',
            $redirectOptions['parameters']
                ?? []
        );

        return $token;
    }
}
