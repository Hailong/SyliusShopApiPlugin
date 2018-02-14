<?php

declare(strict_types=1);

namespace Sylius\ShopApiPlugin\Controller\Order;

use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use League\Tactician\CommandBus;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Webmozart\Assert\Assert;

final class ListAction
{
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var ViewHandlerInterface
     */
    private $viewHandler;

    /**
     * @var CommandBus
     */
    private $bus;

    /**
     * @param TokenStorageInterface $storage
     * @param ViewHandlerInterface $viewHandler
     * @param CommandBus $bus
     */
    public function __construct(
        TokenStorageInterface $storage,
        ViewHandlerInterface $viewHandler,
        CommandBus $bus
    ) {
        $this->tokenStorage = $storage;
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
        $token = $this->tokenStorage->getToken();

        if ($token instanceof TokenInterface) {
            $user = $token->getUser();
        }

        $data = [];
        $orders = $user->getCustomer()->getOrders();

        foreach ($orders as $order) {
            // skip the current shopping cart
            if ($order->getState() == OrderInterface::STATE_CART) {
                continue;
            }

            $data[] = [
                'token' => $order->getTokenValue(),
                'number' => $order->getNumber(),
                'checkoutCompletedAt' => $order->getCheckoutCompletedAt(),
                'total' => $order->getTotal(),
                'state' => $order->getState(),
            ];
        }

        return $this->viewHandler->handle(View::create($data));
    }
}
