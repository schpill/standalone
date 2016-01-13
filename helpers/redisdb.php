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

    class RedisdbLib
    {
        public function addAggregate($name, $fields)
        {
            return Model::AggregateDefinition()->firstOrCreate(['name' => $name])->setFields($fields)->save();
        }

        public function hasAggregate($name)
        {
            return Model::AggregateDefinition()->where(['name' => $name])->cursor()->count() > 0 ? true : false;
        }
    }
