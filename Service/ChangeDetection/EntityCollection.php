<?php
/**
 * Created by PhpStorm.
 * User: Targus
 * Date: 14.12.2016
 * Time: 12:27
 */

namespace Targus\G2faCodeInspector\Service\ChangeDetection;


class EntityCollection implements \ArrayAccess, \Iterator
{
    /**
     * @var Entity[]
     */
    private $container;

    public function __construct()
    {
        $this->container = [];
    }

    /**
     * @param mixed $offset
     * @param Entity $value
     */
    public function offsetSet($offset, $value)
    {
        if ($offset === null) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->container[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->container[$offset]);
    }

    /**
     * @param mixed $offset
     * @return null|Entity
     */
    public function offsetGet($offset)
    {
        return $this->container[$offset] ?? null;
    }

    public function rewind()
    {
        reset($this->container);
    }

    /**
     * @return Entity
     */
    public function current(): Entity
    {
        return current($this->container);
    }

    /**
     * @return Entity
     */
    public function key(): Entity
    {
        return key($this->container);
    }

    /**
     * @return mixed
     */
    public function next()
    {
        return next($this->container);
    }

    /**
     * @return bool
     */
    public function valid(): bool
    {
        $key = key($this->container);
        return ($key !== null && $key !== false);
    }
}