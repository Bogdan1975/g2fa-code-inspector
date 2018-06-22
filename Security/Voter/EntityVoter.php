<?php

namespace Targus\G2faCodeInspector\Security\Voter;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;
use Targus\G2faCodeInspector\Service\Inspector;

/**
 * Class EntityVoter
 * @package Targus\G2faCodeInspector\Security\Voter
 */
class EntityVoter extends Voter
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var array
     */
    private $config;

    /**
     * @var Inspector
     */
    private $inspector;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    public function __construct(RequestStack $stack, EntityManagerInterface $em, TokenStorageInterface $tokenStorage, Inspector $inspector, $config)
    {
        $this->request = $stack->getCurrentRequest();
        $this->em = $em;
        $this->tokenStorage = $tokenStorage;
        $this->inspector = $inspector;
        $this->config = $config;
    }

    protected function supports($attribute, $subject)
    {
        $isUser = $this->tokenStorage->getToken()->getUser() instanceof UserInterface;
        $isApiPlatform = (bool)$this->request->attributes->get('_api_normalization_context');
        return $isUser && $isApiPlatform;
    }

    /**
     * @param string $attribute
     * @param mixed $subject
     * @param TokenInterface $token
     * @return bool
     * @throws \Exception
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();
        $subject = $this->request->attributes->get('data');
        $context = $this->request->attributes->get('_api_normalization_context');
        $code = $this->request->headers->get($this->config['headerName']);
        if ($context) {
            return $this->inspector->resolve($subject, $user, $context, $code);
        }

        return true;
    }
}
