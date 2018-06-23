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
 * Class ControllerVoter
 * @package Targus\G2faCodeInspector\Security\Voter
 */
class ControllerVoter extends Voter
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
        return $attribute === $this->config['grantAttributeName'];
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
        if (!$user instanceof UserInterface) {
            $user = null;
        }

        $code = $this->request->headers->get($this->config['headerName']);
        $controllerString = $this->request->attributes->get('_controller');
        if (!$controllerString) {
            return false;
        }
        $tmp = explode('::', $controllerString);
        if (!is_array($tmp) || !(count($tmp) === 2)) {
            return false;
        }
        $controller = [
            'class'      => $tmp[0],
            'method'     => $tmp[1],
            'httpMethod' => $this->request->getMethod(),
        ];

        return $this->inspector->resolveController($user, $code, $controller);
    }
}
