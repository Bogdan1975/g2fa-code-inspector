<?php
/**
 * Created by PhpStorm.
 * User: Bogdan Shapoval <it.targus@gmail.com>
 * Date: 22.03.2018
 * Time: 16:29
 */

namespace Targus\G2faCodeInspector\Service;

use Doctrine\ORM\EntityManagerInterface;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Security\Core\User\UserInterface;
use Targus\G2faCodeInspector\Annotations\Check;

class Inspector
{
    /** @var Google2FA */
    protected $ga;

    /**
     * @var PropertyInfoExtractor
     */
    private static $propertyInfoExtractor;

    /**
     * @var Inspector
     */
    private static $instance;

    /**
     * @var ReflectionHelper
     */
    private static $reflectionHelper;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var bool
     */
    protected $oneTimeCode = false; // Use each code once

    /**
     * Window.
     * @var int
     */
    protected $window = 1; // Keys will be valid for 60 seconds

    public function __construct(EntityManagerInterface $em, ReflectionHelper $helper, $config)
    {
        $this->em = $em;

        $this->ga = new Google2FA();

        if (isset($config['oneTimeCode'])) {
            $this->oneTimeCode = $config['oneTimeCode'];
        }
        if (isset($config['window'])) {
            $this->window = $config['window'];
        }

        // a full list of extractors is shown further below
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();

        // array of PropertyListExtractorInterface
        $listExtractors = array($reflectionExtractor);

        // array of PropertyTypeExtractorInterface
        $typeExtractors = array($phpDocExtractor, $reflectionExtractor);

        // array of PropertyDescriptionExtractorInterface
        $descriptionExtractors = array($phpDocExtractor);

        // array of PropertyAccessExtractorInterface
        $accessExtractors = array($reflectionExtractor);

        $propertyInfo = new PropertyInfoExtractor(
            $listExtractors,
            $typeExtractors,
            $descriptionExtractors,
            $accessExtractors
        );
        self::$propertyInfoExtractor = $propertyInfo;


        self::$instance = $this;

        self::$reflectionHelper = $helper;
    }

    /**
     * @param $entity
     * @param UserInterface $user
     * @param array $context
     * @param $code
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function resolve($entity, UserInterface $user, array $context, $code) {
        if (!array_key_exists('operation_type', $context) || $context['operation_type'] !== 'item') {
            return true;
        }
        if (!array_key_exists('item_operation_name', $context)) {
            return true;
        }
        $operation = $context['item_operation_name'];
        $uof = $this->em->getUnitOfWork();
        $uof->computeChangeSets();
        $changeSet = $uof->getEntityChangeSet($entity);
        foreach (array_keys($changeSet) as $propertyName) {
            $propertyReflection = self::$reflectionHelper->getPropertyReflection($entity, $propertyName);
            $propertyAnnontation = self::$reflectionHelper->getPropertyAnnotation($propertyReflection, Check::class);
            if (!$propertyAnnontation) {
                continue;
            }
            switch ($operation) {
                case 'GET':
                    $operatiomExpr = $propertyAnnontation->get;
                    break;
                case 'PUT':
                    $operatiomExpr = $propertyAnnontation->put;
                    break;
                case 'POST':
                    $operatiomExpr = $propertyAnnontation->post;
                    break;
                default:
                    return true;
            }
            $expressionLanguage = new ExpressionLanguage();
            $needToCheck = $operatiomExpr ? $expressionLanguage->evaluate($operatiomExpr, ['user' => $user, 'this' => $entity]) : false;
            if (!$needToCheck) {
                return true;
            }
            $secretExpr = $propertyAnnontation->secret;
            $secret = $expressionLanguage->evaluate($secretExpr, ['user' => $user]);

            if (!$this->verify($code, $secret)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $code
     * @param string $secret
     * @param int    $oldTimestamp
     *
     * @return bool
     */
    private function verify(string $code, string $secret = null, int $oldTimestamp = null)
    {
        if ($this->oneTimeCode) {
            $timestamp = $this->ga->verifyKeyNewer($secret, $code, $oldTimestamp, $this->window);

            if ($timestamp) {
                // store
            }
        } else {
            $timestamp = $this->ga->verify($code, $secret, $this->window);
        }

        return (bool)$timestamp;
    }

}