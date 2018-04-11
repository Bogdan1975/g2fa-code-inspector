<?php
/**
 * Created by PhpStorm.
 * User: Bogdan Shapoval <it.targus@gmail.com>
 * Date: 30.03.2018
 * Time: 16:49
 */

namespace Targus\G2faCodeInspector\Annotations;


use Targus\G2faCodeInspector\Interfaces\CheckerDefinerInterface;

/**
 * Class Operation
 * @package Targus\G2faCodeInspector\Annotations
 *
 * @Annotations()
 * @Target({"PROPERTY","ANNOTATION"})
 */
class Operation extends TargusAnnotation
{
    /**
     * @var string
     */
    public $secret;

    /**
     * @var string|CheckerDefinerInterface
     */
    public $definer;

    /**
     * @var string
     */
    public $condition = "true";

}