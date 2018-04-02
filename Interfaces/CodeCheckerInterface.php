<?php
/**
 * Created by PhpStorm.
 * User: Bogdan Shapoval <it.targus@gmail.com>
 * Date: 30.03.2018
 * Time: 14:54
 */

namespace Targus\G2faCodeInspector\Interfaces;


use Symfony\Component\Security\Core\User\UserInterface;

interface CodeCheckerInterface
{
    public function verify($code, UserInterface $user, $entity, $method = 'PUT', array $payload = []): bool;
}