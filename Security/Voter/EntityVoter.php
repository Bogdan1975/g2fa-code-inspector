<?php

namespace Targus\G2faCodeInspector\Security\Voter;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Targus\G2faCodeInspector\Service\Inspector;

/**
 * Class EntityVoter
 * @package Targus\G2faCodeInspector\Security\Voter
 */
class EntityVoter implements VoterInterface
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

    public function vote(TokenInterface $token, $subject, array $attributes)
    {
        // abstain vote by default in case none of the attributes are supported
        $vote = self::ACCESS_ABSTAIN;

        foreach ($attributes as $attribute) {
            if (!$this->supports($attribute, $subject)) {
                continue;
            }

            $newVote = $this->voteOnAttribute($attribute, $subject, $token);
            if (self::ACCESS_GRANTED === $newVote) {
                // grant access as soon as at least one attribute returns a positive response{
                return self::ACCESS_GRANTED;
            }
            if (self::ACCESS_DENIED === $newVote) {
                $vote = self::ACCESS_DENIED;
            }
        }

        return $vote;
    }

    protected function supports($attribute, $subject)
    {
        if (!$this->request->attributes->has('_api_normalization_context') || !$this->request->attributes->has('data')) {
            return false;
        }
        $context = $this->request->attributes->get('_api_normalization_context');

        return (isset($context['operation_type']) && $context['operation_type'] === 'item' && isset($context['item_operation_name']));
    }

    /**
     * @param string $attribute
     * @param mixed $subject
     * @param TokenInterface $token
     * @return int
     * @throws \Exception
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            $user = null;
        }

        $subject = $this->request->attributes->get('data');
        $context = $this->request->attributes->get('_api_normalization_context');
        $code = $this->request->headers->get($this->config['headerName']);

        return $this->inspector->resolve($subject, $user, $context, $code);
    }
}
