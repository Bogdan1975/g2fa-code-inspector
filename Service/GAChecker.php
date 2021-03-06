<?php
/**
 * Created by PhpStorm.
 * User: Bogdan Shapoval <it.targus@gmail.com>
 * Date: 30.03.2018
 * Time: 14:55
 */

namespace Targus\G2faCodeInspector\Service;

use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\Security\Core\User\UserInterface;
use Targus\G2faCodeInspector\Interfaces\CodeCheckerInterface;


/**
 * Class GAChecker
 * @package Targus\G2faCodeInspector\Service
 */
class GAChecker implements CodeCheckerInterface
{
    /** @var Google2FA */
    protected $ga;

    /**
     * @var bool
     */
    protected $oneTimeCode = false; // Use each code once

    /**
     * Window.
     * @var int
     */
    protected $window = 1; // Keys will be valid for 60 seconds

    /**
     * GAChecker constructor.
     *
     * @param $config
     */
    public function __construct($config)
    {
        $this->ga = new Google2FA();

        if (isset($config['oneTimeCode'])) {
            $this->oneTimeCode = $config['oneTimeCode'];
        }
        if (isset($config['window'])) {
            $this->window = $config['window'];
        }
    }

    /**
     * @param string        $code
     * @param UserInterface $user
     * @param mixed         $entity
     * @param string        $method
     * @param array         $payload
     *
     * @return bool
     * @throws \Exception
     */
    public function verify(?string $code, ?UserInterface $user, $entity = null, string $method = null, array $payload = []): bool
    {
        if (!$code) {
            return false;
        }

        if (!isset($payload['secret'])) {
            throw new \InvalidArgumentException('Have no GoogleAuth Secret to process');
        }
        $secret = $payload['secret'];

        if ($this->oneTimeCode) {
            // Restore
            $oldTimestamp = null;

            $timestamp = $this->ga->verifyKeyNewer($secret, $code, $oldTimestamp, $this->window);

            if ($timestamp) {
                // Store
            }
        } else {
            $timestamp = $this->ga->verify($code, $secret, $this->window);
        }

        return (bool)$timestamp;
    }

}
