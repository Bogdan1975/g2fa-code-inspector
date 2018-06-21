<?php
/**
 * Created by PhpStorm.
 * User: Bogdan Shapoval <it.targus@gmail.com>
 * Date: 22.03.2018
 * Time: 16:29
 */

namespace Targus\G2faCodeInspector\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Security\Core\User\UserInterface;
use Targus\G2faCodeInspector\Annotations\Check;
use Targus\G2faCodeInspector\Annotations\Operation;
use Targus\G2faCodeInspector\Exceptions\Exception;
use Targus\G2faCodeInspector\Interfaces\CheckerDefinerInterface;


/**
 * Class Inspector
 * @package Targus\G2faCodeInspector\Service
 */
class Inspector
{
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
     * @var ContainerInterface
     */
    private $sc;

    /**
     * @var array
     */
    private $config;


    /**
     * Inspector constructor.
     *
     * @param ContainerInterface     $sc
     * @param EntityManagerInterface $em
     * @param ReflectionHelper       $helper
     * @param array                  $config
     */
    public function __construct(ContainerInterface $sc, EntityManagerInterface $em, ReflectionHelper $helper, $config)
    {
        $this->sc = $sc;
        $this->em = $em;
        $this->config = $config;

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
     * @param mixed         $entity
     * @param UserInterface $user
     * @param array         $context
     * @param string        $code
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function resolve($entity, UserInterface $user, array $context, $code)
    {
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
            /** @var Check $propertyAnnotation */
            $propertyAnnotation = self::$reflectionHelper->getPropertyAnnotation($propertyReflection, Check::class);
            if (!$propertyAnnotation) {
                continue;
            }
            switch ($operation) {
                case 'GET':
                    $operationMeta = $propertyAnnotation->get;
                    break;
                case 'PUT':
                    $operationMeta = $propertyAnnotation->put;
                    break;
                case 'POST':
                    $operationMeta = $propertyAnnotation->post;
                    break;
                default:
                    return true;
            }
            /** @var $operationMeta Operation */
            $operationExpr = $operationMeta->condition ?? $propertyAnnotation->condition ?? false;
            $expressionLanguage = new ExpressionLanguage();
            $needToCheck = $operationExpr ? $expressionLanguage->evaluate($operationExpr, ['user' => $user, 'this' => $entity, 'entity' => $entity]) : true;
            if (!$needToCheck) {
                continue;
            }
            $secretExpr = $operationMeta->secret ?? $propertyAnnotation->secret;
            $secret = $expressionLanguage->evaluate($secretExpr, ['user' => $user]);

            $definerId = $operationMeta->definer ?? $propertyAnnotation->definer ?? $this->config['defaultDefiner'];
            /** @var CheckerDefinerInterface $definer */
            $definer = $this->sc->get($definerId);
            if (!$definer) {
                throw new Exception("Undefined service '{$definerId}'");
            }
            $checker = $definer->defineChecker($user, $entity, $operation, ['secret' => $secret]);
            if (!$checker) {
                continue;
            }

            if (!$checker->verify($code, $user, $entity, $operation, ['secret' => $secret])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param        $user
     * @param string $code
     * @param array  $controller
     *
     * @return bool
     * @throws Exception
     * @throws \ReflectionException
     */
    public function resolveController($user, $code, array $controller)
    {
        $methodReflection = self::$reflectionHelper->getMethodReflection($controller['class'], $controller['method']);
        /** @var Check $propertyAnnotation */
        $methodAnnotation = self::$reflectionHelper->getMethodAnnotation($methodReflection, Check::class);
        if (null === $methodAnnotation) {
            $methodAnnotation = new Check();
        }

        $operationExpr = $methodAnnotation->condition ?? true;
        $expressionLanguage = new ExpressionLanguage();
        $needToCheck = $operationExpr ? $expressionLanguage->evaluate($operationExpr, ['user' => $user]) : true;
        if (!$needToCheck) {
            return true;
        }
        $secretExpr = $methodAnnotation->secret;
        $secret = $secretExpr ? $expressionLanguage->evaluate($secretExpr, ['user' => $user]) : null;

        $definerId = $methodAnnotation->definer ?? $this->config['defaultDefiner'];
        /** @var CheckerDefinerInterface $definer */
        $definer = $this->sc->get($definerId);
        if (!$definer) {
            throw new Exception("Undefined service '{$definerId}'");
        }
        $checker = $definer->defineChecker($user, null, null, ['secret' => $secret]);
        if (!$checker) {
            return true;
        }

        if (!$checker->verify($code, $user, null, null, ['secret' => $secret])) {
            return false;
        }

        return true;
    }

}
