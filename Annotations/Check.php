<?php
/**
 * Created by PhpStorm.
 * User: Targus
 * Date: 14.07.2017
 * Time: 18:57
 *
 * @author Bogdan Shapoval <it.targus@gmail.com>. 14.07.2017
 */

namespace Targus\G2faCodeInspector\Annotations;


use Doctrine\Common\Annotations\Annotation;

/**
 * Class Check
 * @package Targus\G2faCodeInspector
 *
 * @author Bogdan Shapoval <it.targus@gmail.com>. 14.07.2017
 *
 * @Annotation()
 * @Target({"PROPERTY"})
 */
class Check extends TargusAnnotation
{

    /**
     * @var string
     */
    public $secret;

    /**
     * @var string
     */
    public $get = false;

    /**
     * @var string
     */
    public $put = false;

    /**
     * @var string
     */
    public $post = false;

//    public function __construct($data)
//    {
//        $a = 5;
//    }

    /**
     * Merge with other Field annotation (inheritance)
     *
     * @param Field $obj
     */
    public function merge(Field $obj)
    {
        $this->type = $this->type ?? $obj->type;
        $this->array = $this->array ?? $obj->array;
        $this->preserveKeys = $this->preserveKeys ?? $obj->preserveKeys;
        $this->sourceName = $this->sourceName ?? $obj->sourceName;
        $this->required = $this->required ?? $obj->required;
        $this->nullable = $this->nullable ?? $obj->nullable;
        $this->default = $this->default ?? $obj->default;
        $this->enum = $this->enum ?? $obj->enum;
        $this->profiles = $this->profiles ?? $obj->profiles;
        $this->exclude = $this->exclude ?? $obj->exclude;
        $this->inputDateTimeFormat = $this->inputDateTimeFormat ?? $obj->inputDateTimeFormat;
        $this->outputDateTimeFormat = $this->outputDateTimeFormat ?? $obj->outputDateTimeFormat;
    }

}