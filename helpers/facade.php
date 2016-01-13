<?php
    /**
     * Thin is a swift Framework for PHP 5.4+
     *
     * @package    Thin
     * @version    1.0
     * @author     Gerald Plusquellec
     * @license    BSD License
     * @copyright  1996 - 2015 Gerald Plusquellec
     * @link       http://github.com/schpill/thin
     */

    namespace Thin;

    class FacadeLib
    {
        public function __construct($class, $to, $namespace = null, $ex = false)
        {
            $check = is_null($namespace) ? $to : '\\' . $namespace . '\\' . $to;

            if (!class_exists($check) && class_exists($class)) {
                if (is_null($namespace)) {
                    $code = 'class ' . $to . ' extends ' . $class . ' {}';
                } else {
                    $code = 'namespace ' . $namespace . '; class ' . $to . ' extends ' . $class . ' {}';
                }

                eval($code);
            } else {
                if ($ex) {
                    throw new Exception($to . ' class ever exists.');
                }
            }
        }
    }
