<?php
/**
 * Created by PhpStorm.
 * User: Bogdan Shapoval <it.targus@gmail.com>
 * Date: 30.03.2018
 * Time: 16:09
 */

namespace Targus\G2faCodeInspector\Interfaces;

use Symfony\Component\Security\Core\User\UserInterface;


/**
 * Interface CheckerDefinerInterface
 * @package Targus\G2faCodeInspector\Interfaces
 */
interface CheckerDefinerInterface
{
    /**
     * @param UserInterface $user
     * @param mixed         $entity
     * @param string        $method
     * @param array         $payload
     *
     * @return CodeCheckerInterface|null
     */
    public function defineChecker(?UserInterface $user, $entity = null, string $method = null, array $payload = []): ?CodeCheckerInterface;
}
