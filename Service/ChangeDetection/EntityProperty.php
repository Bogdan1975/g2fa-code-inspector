<?php
/**
 * Created by PhpStorm.
 * User: Targus
 * Date: 14.12.2016
 * Time: 12:26
 */

namespace Targus\G2faCodeInspector\Service\ChangeDetection;

class EntityProperty
{
    const VALUE_TYPE_SCALAR = 'scalar';
    const VALUE_TYPE_ASSOCIATION_ENTITY = 'assoc_entity';
    const VALUE_TYPE_ASSOCIATION_COLLECTION = 'assoc_collection';

    private static $types = [
        self::VALUE_TYPE_SCALAR,
        self::VALUE_TYPE_ASSOCIATION_ENTITY,
        self::VALUE_TYPE_ASSOCIATION_COLLECTION,
    ];

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $type;

    /**
     * @var mixed | Entity | EntityCollection
     */
    private $value;

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return EntityProperty
     */
    public function setName(string $name): EntityProperty
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return EntityProperty
     * @throws Exception
     */
    public function setType($type): EntityProperty
    {
        if (!in_array($type, self::$types, true)) {
            throw new Exception('Unknown value type');
        }
        if ($this->getValue() !== null) {
            throw new Exception('You cannot set type for not empty value');
        }
        $this->type = $type;
        if ($type === self::VALUE_TYPE_ASSOCIATION_COLLECTION) {
            $this->setValue(new EntityCollection());
        }

        return $this;
    }

    /**
     * @return mixed|Entity|EntityCollection
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param mixed|Entity|EntityCollection $value
     * @return EntityProperty
     * @throws Exception
     */
    public function setValue($value): EntityProperty
    {
        if ($this->getType() === null) {
            throw new Exception('Set type first');
        }
        switch ($this->getType()) {
            case self::VALUE_TYPE_SCALAR :
                break;
            case self::VALUE_TYPE_ASSOCIATION_ENTITY:
                if (!$value instanceof Entity && $value !== null) {
                    throw new Exception('Value must be a SnapshotEntity type');
                }
                break;
            case self::VALUE_TYPE_ASSOCIATION_COLLECTION:
                if (!$value instanceof EntityCollection) {
                    throw new Exception('Value must be a SnapshotEntityCollection type');
                }
                break;
            default:
                throw new Exception('Unknown value type');
        }
        $this->value = $value;

        return $this;
    }

    /**
     * @param Entity $entity
     * @return EntityProperty
     * @throws Exception
     */
    public function addToCollection(Entity $entity): EntityProperty
    {
        if ($this->getValue() !== null && !$this->getValue() instanceof EntityCollection) {
            throw new Exception('Current value is not "Association collection type"');
        }
        if ($this->getType() === null) {
            $this->setType(self::VALUE_TYPE_ASSOCIATION_COLLECTION);
        }
        if ($this->getValue() === null) {
            $this->setValue(new EntityCollection());
        }
        if ($entity->getId() === null) {
            throw new Exception('Realated entity have no ID');
        }
        $this->value[$entity->getId()] = $entity;

        return $this;
    }

}