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
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Targus\G2faCodeInspector\Annotations\Check;
use Targus\G2faCodeInspector\Annotations\Operation;
use Targus\G2faCodeInspector\Exceptions\Exception;
use Targus\G2faCodeInspector\Interfaces\CheckerDefinerInterface;
use Targus\G2faCodeInspector\Interfaces\CodeCheckerInterface;
use Targus\G2faCodeInspector\Service\ChangeDetection\ChangeDetector;


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
     * @var ChangeDetector
     */
    private $cd;

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
    public function __construct(ContainerInterface $sc, EntityManagerInterface $em, ReflectionHelper $helper, ChangeDetector $cd,  $config)
    {
        $this->sc = $sc;
        $this->em = $em;
        $this->cd = $cd;
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
     * @return int
     *
     * @throws \Exception
     */
    public function resolve($entity, ?UserInterface $user, array $context, ?string $code)
    {
        $operation = $context['item_operation_name'];
        $changeSet = $this->cd->detectChanges($entity);
        $reflection = self::$reflectionHelper->getClassReflection($entity);
        if ($reflection->implementsInterface(\Doctrine\ORM\Proxy\Proxy::class)) {
            $reflection = self::$reflectionHelper->getParentReflection($reflection);
        }

        $vote = VoterInterface::ACCESS_ABSTAIN;

        foreach (array_keys($changeSet) as $propertyName) {
            $propertyReflection = self::$reflectionHelper->getPropertyReflection($reflection, $propertyName);
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
                    return VoterInterface::ACCESS_ABSTAIN;
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

            /** @var CodeCheckerInterface $checker */
            $checker = $definer->defineChecker($user, $entity, $operation, ['secret' => $secret]);
            if (!$checker) {
                continue;
            }

            $verifyResult = $checker->verify($code, $user, $entity, $operation, ['secret' => $secret]);
            if (!$verifyResult) {
                return VoterInterface::ACCESS_DENIED;
            }
            $vote = VoterInterface::ACCESS_GRANTED;
        }

        return $vote;
    }

    /**
     * @param UserInterface $user
     * @param string        $code
     * @param array         $controller
     *
     * @return bool
     * @throws Exception
     * @throws \ReflectionException
     */
    public function resolveController(?UserInterface $user, ?string $code, array $controller)
    {
        $methodReflection = self::$reflectionHelper->getMethodReflection($controller['class'], $controller['method']);
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

        /** @var CodeCheckerInterface $checker */
        $checker = $definer->defineChecker($user, null, $controller['httpMethod'], ['secret' => $secret]);
        if (!$checker) {
            return true;
        }

        if (!$checker->verify($code, $user, null, $controller['httpMethod'], ['secret' => $secret])) {
            return false;
        }

        return true;
    }

}
