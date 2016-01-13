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

    class IocLib
    {
        public static function instance($context = null)
        {
            $context = is_null($context) ? 'core' : $context;

            $has    = Instance::has('ioc', $context);

            if (true === $has) {
                return Instance::get('ioc', $context);
            } else {
                $instance = lib('container');

                return Instance::make('ioc', $context, $instance);
            }
        }

        public static function __callStatic($m, $a)
        {
            $instance = static::instance();

            return call_user_func_array([$instance, $m], $a);
        }
    }
