<?php
/**
 * Created by PhpStorm.
 * User: Targus
 * Date: 22.03.2017
 * Time: 19:49
 *
 * @author Bogdan Shapoval <it.targus@gmail.com>. 22.03.2018
 */

namespace Targus\G2faCodeInspector\Annotations;


class TargusAnnotation implements TargusAnnotationInterface
{
    /**
     * Value property. Common among all derived classes.
     *
     * @var string
     */
    public $value;

    /**
     * Constructor.
     *
     * @param array $data Key-value for properties to be defined in this class.
     */
    public function __construct(array $data)
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Error handler for unknown property accessor in Annotation class.
     *
     * @param string $name Unknown property name.
     *
     * @throws \BadMethodCallException
     */
    public function __get($name)
    {
        throw new \BadMethodCallException(
            sprintf("Unknown property '%s' on annotation '%s'.", $name, get_class($this))
        );
    }

    /**
     * Error handler for unknown property mutator in Annotation class.
     *
     * @param string $name  Unknown property name.
     * @param mixed  $value Property value.
     *
     * @throws \BadMethodCallException
     */
    public function __set($name, $value)
    {
        throw new \BadMethodCallException(
            sprintf("Unknown property '%s' on annotation '%s'.", $name, get_class($this))
        );
    }
}