<?php

declare(strict_types=1);

namespace Sylius\ShopApiPlugin\Handler;

use SM\Factory\FactoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\ShopApiPlugin\Command\Pay;
use Webmozart\Assert\Assert;

final class ChoosePaymentMethodHandler
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var PaymentMethodRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var FactoryInterface
     */
    private $stateMachineFactory;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param PaymentMethodRepositoryInterface $paymentMethodRepository
     * @param FactoryInterface $stateMachineFactory
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        FactoryInterface $stateMachineFactory
    ) {
        $this->orderRepository = $orderRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->stateMachineFactory = $stateMachineFactory;
    }

    /**
     * @param ChoosePaymentMethod $choosePaymentMethod
     */
    public function handle(Pay $pay)
    {
        $configuration = $this->requestConfigurationFactory->create($this->orderMetadata, $request);

        /** @var OrderInterface $order */
        $order = $this->orderRepository->findOneByTokenValue($tokenValue);

        if (null === $order) {
            throw new NotFoundHttpException(sprintf('Order with token "%s" does not exist.', $tokenValue));
        }

        $request->getSession()->set('sylius_order_id', $order->getId());
        $payment = $order->getLastPayment(PaymentInterface::STATE_NEW);

        if (null === $payment) {
            $url = $this->router->generate('sylius_shop_order_thank_you');

            return new RedirectResponse($url);
        }

        $token = $this->provideTokenBasedOnPayment($payment, $configuration->getParameters()->get('redirect'));

        $view = View::createRedirect($token->getTargetUrl());

        return $this->viewHandler->handle($configuration, $view);

        /** @var OrderInterface $order */
        $order = $this->orderRepository->findOneBy(['tokenValue' => $pay->orderToken()]);

        Assert::notNull($order, 'Order has not been found.');

        $payment = $order->getLastPayment(PaymentInterface::STATE_NEW);

        if (null === $payment) {
            $url = $this->router->generate('sylius_shop_order_thank_you');

            return new RedirectResponse($url);
        }

        $token = $this->provideTokenBasedOnPayment($payment, array());

        $stateMachine = $this->stateMachineFactory->get($cart, OrderCheckoutTransitions::GRAPH);

        Assert::true($stateMachine->can(OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT), 'Order cannot have payment method assigned.');

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $this->paymentMethodRepository->findOneBy(['code' => $choosePaymentMethod->paymentMethod()]);

        Assert::notNull($paymentMethod, 'Payment method has not been found');
        Assert::true(isset($cart->getPayments()[$choosePaymentMethod->paymentIdentifier()]), 'Can not find payment with given identifier.');

        $payment = $cart->getPayments()[$choosePaymentMethod->paymentIdentifier()];

        $payment->setMethod($paymentMethod);
        $stateMachine->apply(OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);
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
            $gatewayConfig->getGatewayName(),
            $payment,
            $redirectOptions['route']
                ?? null,
            $redirectOptions['parameters']
                ?? []
        );

        return $token;
    }
}
