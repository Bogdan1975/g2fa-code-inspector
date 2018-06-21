<?php
/**
 * Created by PhpStorm.
 * User: Bogdan Shapoval <it.targus@gmail.com>
 * Date: 30.03.2018
 * Time: 16:10
 */

namespace Targus\G2faCodeInspector\Service;

use Symfony\Component\Security\Core\User\UserInterface;
use Targus\G2faCodeInspector\Interfaces\CheckerDefinerInterface;
use Targus\G2faCodeInspector\Interfaces\CodeCheckerInterface;


/**
 * Class GADefiner
 * @package Targus\G2faCodeInspector\Service
 */
class GADefiner implements CheckerDefinerInterface
{
    /**
     * @var GAChecker
     */
    private $gaChecker;

    public function __construct(GAChecker $gaChecker)
    {
        $this->gaChecker = $gaChecker;
    }

    /**
     * @param UserInterface $user
     * @param mixed         $entity
     * @param string        $method
     * @param array         $payload
     *
     * @return CodeCheckerInterface|null
     */
    public function defineChecker(UserInterface $user, $entity, $method = 'PUT', array $payload = []): ?CodeCheckerInterface
    {
        return $this->gaChecker;
    }

}
