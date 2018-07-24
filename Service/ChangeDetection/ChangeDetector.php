<?php
/**
 * Created by PhpStorm.
 * User: Targus
 * Date: 14.12.2016
 * Time: 14:54
 */

namespace Targus\G2faCodeInspector\Service\ChangeDetection;


use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\PersistentCollection;

class ChangeDetector
{
    /**
     * @var ObjectManager
     */
    private $em;

    /**
     * @var array
     */
    static public $snapshots;

    public function __construct(ObjectManager $em)
    {
        $this->em = $em;
        self::$snapshots = [];
    }

    /**
     * Detects whether entity is instance of Doctrine entity in static way
     *
     * @param $entity
     * @param EntityManagerInterface $em
     * @return bool
     */
    public static function staticIsDoctrineEntity($entity, EntityManagerInterface $em): bool
    {
        $className = ClassUtils::getClass($entity);
        return !$em->getMetadataFactory()->isTransient($className);
    }

    /**
     * Detects whether entity is instance of Doctrine entity
     *
     * @param $entity
     * @param ObjectManager|null $em
     * @return bool
     */
    public function isDoctrineEntity($entity, ObjectManager $em = null): bool
    {
        if (null === $em) {
            $em = $this->em;
        }

        return self::staticIsDoctrineEntity($entity, $em);
    }

    private static function compileId(ObjectManager $em, $entity, ClassMetadata $metaData = null): string
    {
        if (null === $metaData) {
            $metaData = $em->getClassMetadata(ClassUtils::getClass($entity));
        }
        $identifiers = $metaData->identifier;
        $ids = [];
        foreach ($identifiers as $identifier) {
            $getter = 'get' . ucfirst($identifier);
            if (!method_exists($entity, $getter)) {
                $getter = 'is' . ucfirst($identifier);
                if (!method_exists($entity, $getter)) {
                    throw new Exception("Enity haven't getter for '{$identifier}' identifier field");
                }
            }
            $value = $entity->$getter();
            if (null !== $value) {
                $ids[] = $value;
            }
        }

        return implode('_', $ids);
    }

    public static function deepClone(
        ObjectManager $em,
        $entity,
        $depth = 2,
        $currentLevel = 1,
        $data = null
    ): Entity {
        if ($entity === null) {
            throw new \InvalidArgumentException('Have no entity to process');
        }

        $metaData = $em->getClassMetadata(ClassUtils::getClass($entity));
        $entityId = self::compileId($em, $entity, $metaData);

        $snapshotEntity = new Entity();
        $snapshotEntity->setType(ClassUtils::getClass($entity))->setId($entityId);

        $properties = $metaData->fieldMappings;
        foreach ($properties as $property) {
            $fieldName = $property['fieldName'];
            if ($fieldName === 'id') {
                continue;
            }
            if (null !== $data && is_array($data) && array_key_exists($fieldName, $data)) {
                $value = $data[$fieldName];
            } else {
                $getterName = ucfirst($fieldName);
                $getter = 'get' . $getterName;
                if (!method_exists($entity, $getter)) {
                    $getter = 'is' . $getterName;
                    if (!method_exists($entity, $getter)) {
                        continue;
                    }
                }
                $value = $entity->$getter();
            }
            $snapshotProperty = new EntityProperty();
            $snapshotProperty
                ->setType(EntityProperty::VALUE_TYPE_SCALAR)
                ->setName($fieldName)
                ->setValue($value);
            $snapshotEntity->addProperty($snapshotProperty);
        }

        $associations = $metaData->getAssociationMappings();
        foreach ($associations as $association) {
            $getter = 'get' . ucfirst($association['fieldName']);
            if (!method_exists($entity, $getter)) {
                continue;
            }
            $assocEntity = $entity->$getter();
            if (is_scalar($assocEntity)) {
                continue;
            }
            switch ((int)$association['type']) {
                case ClassMetadataInfo::ONE_TO_ONE:
                case ClassMetadataInfo::MANY_TO_ONE:
                    if ($currentLevel <= $depth) {
                        $newAssoc = $assocEntity ? self::deepClone($em, $assocEntity, $depth, $currentLevel + 1) : null;
                        $snapshotProperty = new EntityProperty();
                        $snapshotProperty
                            ->setType(EntityProperty::VALUE_TYPE_ASSOCIATION_ENTITY)
                            ->setName($association['fieldName'])
                            ->setValue($newAssoc);
                        $snapshotEntity->addProperty($snapshotProperty);
                    }
                    break;
                case ClassMetadataInfo::ONE_TO_MANY:
                case ClassMetadataInfo::MANY_TO_MANY:
                    /**
                     * @var PersistentCollection $assocEntity
                     */
                    if ($currentLevel <= $depth) {
                        $snapshotProperty = new EntityProperty();
                        $snapshotProperty
                            ->setType(EntityProperty::VALUE_TYPE_ASSOCIATION_COLLECTION)
                            ->setName($association['fieldName']);
                        foreach ($assocEntity as $item) {
                            $newItem = self::deepClone($em, $item, $depth, $currentLevel + 1);
                            $snapshotProperty->addToCollection($newItem);
                        }
                        $snapshotEntity->addProperty($snapshotProperty);
                    }
                    break;
                default:
                    break;
            }
        }

        return $snapshotEntity;
    }

    /**
     * Compare two snapshots
     *
     * Result example:
     * array (
     *       'price' => array (
     *           'old' => '700.00',
     *           'new' => 12345,
     *       ),
     *       'shop' => array (
     *           'name' => array (
     *               'old' => 'Yara test shop',
     *               'new' => 'AAA',
     *           ),
     *       ),
     *       'bulkDiscounts' => array (
     *           'removed' => array (
     *               0 => 95,
     *           ),
     *           'added' => array (
     *               0 => 98,
     *           ),
     *           'changed' => array (
     *               12 => array (
     *                   'discount' => array (
     *                       'old' => '700.00',
     *                       'new' => 900,
     *                   ),
     *               ),
     *           ),
     *       ),
     *   )
     *
     * @param Entity $newSnapshot
     * @param Entity $oldSnapshot
     * @param int $depth
     * @param bool $onlyLinks
     * @param int $currentLevel
     * @return array
     * @throws Exception
     */
    public static function compareSnapshots(
        Entity $newSnapshot,
        Entity $oldSnapshot,
        $depth = 2,
        $onlyLinks = true,
        $currentLevel = 1,
        $stack = []
    ): array {
        if ($newSnapshot->getType() !== $oldSnapshot->getType()) {
            throw new Exception('Entities wich snapshots are compared have different types');
        }
        if ($newSnapshot->getId() !== $oldSnapshot->getId()) {
            throw new Exception('Entities wich snapshots are compared have different IDs');
        }
        $type = $newSnapshot->getType();
        $id = $newSnapshot->getId();
        if (!array_key_exists($type, $stack)) {
            $stack[$type] = [];
        }
        if (!in_array($id, $stack[$type])) {
            $stack[$type][] = $id;
        } else {
            return [];
        }
        $difference = [];
        foreach ($newSnapshot->getProperties() as $property) {
            $oldProperty = $oldSnapshot->getProperty($property->getName());
            if ($oldProperty === null) {
                throw new Exception("Old snapshot haven't property '{$$property->getName()}'");
            }
            if ($oldProperty->getType() !== $property->getType()) {
                throw new Exception("Property '{$$property->getName()}' have different types in provided snapshots");
            }
            $oldValue = $oldProperty->getValue();
            $newValue = $property->getValue();
            switch ($property->getType()) {
                case EntityProperty::VALUE_TYPE_SCALAR :
                    if (($oldValue instanceof \DateTimeInterface) && ($newValue instanceof \DateTimeInterface)) {
                        $diff = ($oldValue != $newValue);
                    } else {
                        $oldVal = (is_object($oldValue) || is_array($oldValue)) ? json_encode($oldValue) : $oldValue;
                        $newVal = (is_object($newValue) || is_array($newValue)) ? json_encode($newValue) : $newValue;
                        $diff = ($oldVal !== $newVal);
                    }
                    if ($diff) {
                        $difference[$property->getName()] = [
                            'old' => $oldValue,
                            'new' => $newValue,
                        ];
                    }
                    break;
                case EntityProperty::VALUE_TYPE_ASSOCIATION_ENTITY:
                    $oldId = $oldValue ? $oldValue->getId() : null;
                    $newId = $newValue ? $newValue->getId() : null;
                    if ($oldId !== $newId) {
                        $difference[$property->getName()] = [
                            'old' => $oldValue,
                            'new' => $newValue,
                        ];
                    }
                    if (!$onlyLinks && $currentLevel < $depth && $newId !== null && $oldId !== null && $oldId === $newId) {
                        $propDiff = self::compareSnapshots(
                            $newValue,
                            $oldValue,
                            $depth,
                            $onlyLinks,
                            $currentLevel + 1,
                            $stack
                        );
                        if (count($propDiff)) {
                            if (!array_key_exists($property->getName(), $difference)) {
                                $difference[$property->getName()] = [
                                    'old'    => $oldValue,
                                    'new'    => $newValue,
                                    'fields' => [],
                                ];
                            }
                            foreach ($propDiff as $name => $diff) {
//                                $difference[$property->getName()]['fields'][$name] = [
//                                    'old' => $diff['old'],
//                                    'new' => $diff['new'],
//                                ];
                                $difference[$property->getName()]['fields'][$name] = $diff;
                            }
                        }
                    }
                    break;
                case EntityProperty::VALUE_TYPE_ASSOCIATION_COLLECTION:
                    /**
                     * @var EntityCollection $oldCollection
                     */
                    $oldCollection = $oldValue;
                    /**
                     * @var EntityCollection $newCollection
                     */
                    $newCollection = $newValue;
                    // Find removed relations
                    $inOldOnly = [];
                    $inNewOnly = [];
                    $inBoth = [];
                    foreach ($oldCollection as $oldRelation) {
                        $finded = false;
                        foreach ($newCollection as $newRelation) {
                            if ($oldRelation->getId() === $newRelation->getId()) {
                                $finded = true;
                                break;
                            }
                        }
                        if ($finded) {
                            $inBoth[] = $oldRelation->getId();
                        } else {
                            $inOldOnly[] = $oldRelation;
                        }
                    }
                    foreach ($newCollection as $newRelation) {
                        if (!in_array($newRelation->getId(), $inBoth, true)) {
                            $inNewOnly[] = $newRelation;
                        }
                    }
                    $diff = [];
                    if (count($inOldOnly)) {
                        $diff['removed'] = $inOldOnly;
                    }
                    if (count($inNewOnly)) {
                        $diff['added'] = $inNewOnly;
                    }
                    if (!$onlyLinks) {
                        $changed = [];
                        foreach ($inBoth as $relId) {
                            $relDiff = self::compareSnapshots(
                                $newCollection[$relId],
                                $oldCollection[$relId],
                                $depth,
                                $onlyLinks,
                                $currentLevel + 1,
                                $stack
                            );
                            if (count($relDiff)) {
                                $changed[$relId] = $relDiff;
                            }
                        }
                        if (count($changed)) {
                            $diff['changed'] = $changed;
                        }
                    }
                    if (count($diff)) {
                        $difference[$property->getName()] = $diff;
                    }
                    break;
                default:
                    throw new Exception('Unknown value type');
            }
        }

        return $difference;
    }

    public static function staticMakeSnapshot(ObjectManager $em, $entity, $depth = 2, string $id = '')
    {
        if ($entity === null || !self::staticIsDoctrineEntity($entity, $em)) {
            throw new \InvalidArgumentException('Have no entity to process');
        }
        if ($id === '') {
            $id = self::compileId($em, $entity);
            if ($id === '') {
                throw new \InvalidArgumentException('Have no identifier to save entity');
            }
            $id = str_replace('\\', '_', ClassUtils::getClass($entity)) . '_' . $id;
        }

        $entity = self::deepClone($em, $entity, $depth);

        self::$snapshots[$id] = [
            'depth'  => $depth,
            'entity' => $entity,
        ];
    }

    public function makeSnapshot($entity, $depth = 2, string $id = '')
    {
        self::staticMakeSnapshot($this->em, $entity, $depth, $id);
    }

    public static function staticDetectChanges(ObjectManager $em, $entity, $onlyLinks = true, string $id = '', $depth = null)
    {
        if ($entity === null  || !self::staticIsDoctrineEntity($entity, $em)) {
            throw new \InvalidArgumentException('Have no entity to process');
        }
        if ($id === '') {
            $id = self::compileId($em, $entity);
            if ($id === '') {
                throw new \InvalidArgumentException('Have no identifier to save entity');
            }
            $id = str_replace('\\', '_', ClassUtils::getClass($entity)) . '_' . $id;
        }

        if (!isset(self::$snapshots[$id])) {
            $uow = $em->getUnitOfWork();
            $data = $uow->getOriginalEntityData($entity);
            self::$snapshots[$id] = [
                'depth'  => $depth,
                'entity' => self::deepClone($em, $entity, $depth, 1, $data),
            ];
        }

        if (!$depth || $depth > self::$snapshots[$id]['depth']) {
            $depth = self::$snapshots[$id]['depth'];
        }

        $oldEntitySnapshot = self::$snapshots[$id]['entity'];
        $newEntitySnapshot = self::deepClone($em, $entity, $depth);

        $difference = self::compareSnapshots($newEntitySnapshot, $oldEntitySnapshot, $depth, $onlyLinks);

        return $difference;
    }

    public function detectChanges($entity, $onlyLinks = true, string $id = '', $depth = null): array
    {
        return self::staticDetectChanges($this->em, $entity, $onlyLinks, $id, $depth);
    }
}