<?php
/**
 * Created by PhpStorm.
 * User: Bogdan Shapoval <it.targus@gmail.com>
 * Date: 30.03.2018
 * Time: 16:09
 */

namespace Targus\G2faCodeInspector\Interfaces;


use Symfony\Component\Security\Core\User\UserInterface;

interface CheckerDefinerInterface
{

    public function defineChecker(UserInterface $user, $entity, $method = 'PUT', array $payload = []): ?CodeCheckerInterface;
}