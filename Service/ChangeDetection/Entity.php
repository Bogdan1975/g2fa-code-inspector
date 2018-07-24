<?php
/**
 * Created by PhpStorm.
 * User: Targus
 * Date: 14.12.2016
 * Time: 12:23
 */

namespace Targus\G2faCodeInspector\Service\ChangeDetection;

class Entity
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $type;

    /**
     * @var EntityProperty[]
     */
    private $properties;

    public function __construct()
    {
        $this->properties = [];
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     * @return Entity
     */
    public function setId($id): Entity
    {
        $this->id = $id;

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
     * @return Entity
     */
    public function setType(string $type): Entity
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @param string $name
     * @return null|EntityProperty
     */
    public function getProperty(string $name)
    {
        return $this->properties[$name] ?? null;
    }

    /**
     * @return EntityProperty[]
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @param EntityProperty[] $properties
     * @return Entity
     * @throws Exception
     */
    public function setProperties(array $properties): Entity
    {
        foreach ($properties as $property) {
            if (!$property instanceof EntityProperty) {
                throw new Exception('Properties must be an array of SnapshotEnityProperty instances');
            }
        }
        $this->properties = $properties;

        return $this;
    }

    /**
     * @param EntityProperty $property
     * @return Entity
     * @throws Exception
     */
    public function addProperty(EntityProperty $property = null): Entity
    {
        if ($property->getName() === null) {
            throw new Exception('Attempt to add property without name');
        }
        if (array_key_exists($property->getName(), $this->properties)) {
            throw new Exception('Attempt to add property with existing name');
        }
        $this->properties[$property->getName()] = $property;

        return $this;
    }
}