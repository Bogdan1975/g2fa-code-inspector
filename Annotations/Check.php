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
use Targus\G2faCodeInspector\Interfaces\CheckerDefinerInterface;

/**
 * Class Check
 * @package Targus\G2faCodeInspector
 *
 * @author Bogdan Shapoval <it.targus@gmail.com>. 14.07.2017
 *
 * @Annotation()
 * @Target({"PROPERTY", "METHOD"})
 */
class Check extends TargusAnnotation
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
    public $condition = null;

    /**
     * @var Operation
     */
    public $get = null;

    /**
     * @var Operation
     */
    public $put = null;

    /**
     * @var Operation
     */
    public $post = null;


}