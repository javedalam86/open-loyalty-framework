<?php
/**
 * Copyright © 2017 Divante, Inc. All rights reserved.
 * See LICENSE for license details.
 */
namespace OpenLoyalty\Bundle\UserBundle\EventListener;

use Broadway\EventDispatcher\EventDispatcher;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use OpenLoyalty\Bundle\UserBundle\Entity\Customer;
use OpenLoyalty\Bundle\UserBundle\Entity\User;
use OpenLoyalty\Bundle\UserBundle\Exception\SellerIsNotActiveException;
use OpenLoyalty\Bundle\UserBundle\Service\UserManager;
use OpenLoyalty\Component\Customer\Domain\CustomerId;
use OpenLoyalty\Component\Customer\Domain\SystemEvent\CustomerLoggedInSystemEvent;
use OpenLoyalty\Component\Customer\Domain\SystemEvent\CustomerSystemEvents;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Role\RoleInterface;

/**
 * Class AuthenticationListener.
 */
class AuthenticationListener
{
    /**
     * @var UserManager
     */
    protected $userManager;

    /**
     * @var EventDispatcher
     */
    protected $dispatcher;

    /**
     * AuthenticationListener constructor.
     *
     * @param UserManager     $userManager
     * @param EventDispatcher $dispatcher
     */
    public function __construct(UserManager $userManager, EventDispatcher $dispatcher)
    {
        $this->userManager = $userManager;
        $this->dispatcher = $dispatcher;
    }

    public function onJWTCreated(JWTCreatedEvent $event)
    {
        $user = $event->getUser();

        $payload = $event->getData();
        $roles = $user->getRoles();
        $roleNames = array_map(function (RoleInterface $role) {
            return $role->getRole();
        }, $roles);
        $payload['roles'] = $roleNames;
        if ($user instanceof User) {
            $payload['id'] = $user->getId();
        }
        $payload['lastLoginAt'] = $user->getLastLoginAt() ? $user->getLastLoginAt()->format(\DateTime::ISO8601) : null;

        $event->setData($payload);
    }

    public function onAuthenticationSuccessResponse(AuthenticationSuccessEvent $event)
    {
        $user = $event->getUser();
        $data = $event->getData();

        if ($user instanceof User) {
            $user->setLastLoginAt(new \DateTime());
            $this->userManager->updateUser($user);
            $this->dispatcher->dispatch(
                CustomerSystemEvents::CUSTOMER_LOGGED_IN,
                [new CustomerLoggedInSystemEvent(new CustomerId($user->getId()))]
            );
        }

        if ($user instanceof Customer && $user->getTemporaryPasswordSetAt()) {
            $data['error'] = 'password change needed';
        }

        $event->setData($data);
    }

    public function onAuthenticationFailureResponse(AuthenticationFailureEvent $event)
    {
        $exception = $event->getException();
        $previous = $exception->getPrevious();
        if ($previous instanceof SellerIsNotActiveException) {
            $event->setResponse(new JsonResponse([
                'message' => $exception->getMessage(),
            ], 400));
        }
    }
}
