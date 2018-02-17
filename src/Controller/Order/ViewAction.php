<?php

declare(strict_types=1);

namespace Sylius\ShopApiPlugin\Controller\Order;

use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use League\Tactician\CommandBus;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Webmozart\Assert\Assert;
use Sylius\ShopApiPlugin\ViewRepository\CartViewRepositoryInterface;

final class ViewAction
{
    /**
     * @var CartViewRepositoryInterface
     */
    private $cartQuery;

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
     * @param CartViewRepositoryInterface $cartQuery
     * @param TokenStorageInterface $storage
     * @param ViewHandlerInterface $viewHandler
     * @param CommandBus $bus
     */
    public function __construct(
        CartViewRepositoryInterface $cartQuery,
        TokenStorageInterface $storage,
        ViewHandlerInterface $viewHandler,
        CommandBus $bus
    ) {
        $this->cartQuery = $cartQuery;
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

        foreach ($user->getCustomer()->getOrders() as $order) {
            if ($request->attributes->get('token') == $order->getTokenValue()) {
                // $data = [
                //     'token' => $order->getTokenValue(),
                //     'number' => $order->getNumber(),
                //     'checkoutCompletedAt' => $order->getCheckoutCompletedAt(),
                //     'total' => $order->getTotal(),
                //     'state' => $order->getState(),
                //     'paymentState' => $order->getPaymentState(),
                // ];

                // break;
                try {
                    $cartSummaryView = $this->cartQuery->getOneByToken($request->attributes->get('token'));
                    $cartSummaryView->payments['paymentState'] = $order->getPaymentState();

                    return $this->viewHandler->handle(
                        View::create($cartSummaryView, Response::HTTP_OK)
                    );
                } catch (\InvalidArgumentException $exception) {
                    throw new NotFoundHttpException($exception->getMessage());
                }
            }
        }

        return $this->viewHandler->handle(View::create($data));
    }
}
