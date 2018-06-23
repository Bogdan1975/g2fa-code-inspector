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
    /**
     * @param string        $code
     * @param UserInterface $user
     * @param mixed         $entity
     * @param string        $method
     * @param array         $payload
     *
     * @return bool
     */
    public function verify(?string $code, ?UserInterface $user, $entity = null, string $method = null, array $payload = []): bool;
}
