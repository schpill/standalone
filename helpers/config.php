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

    class ConfigLib
    {
        public function getId($key, $value)
        {
            return Model::Config()
            ->firstOrCreate(['key' => $key, 'value' => $value])
            ->id;
        }

        public function delete($key)
        {
            return $this->del($key);
        }

        public function has($key)
        {
            $count = Model::Config()->where(['key', '=', $key])->count();

            return $count > 0 ? true : false;
        }

        public function del($key)
        {
            $object = Model::Config()->where(['key', '=', $key])->first(true);

            return $object ? $object->delete() : false;
        }

        public function set($key, $value)
        {
            return Model::Config()->firstOrCreate(['key' => $key, 'value' => $value]);
        }

        public function get($key, $default = null)
        {
            $object = Model::Config()->where(['key', '=', $key])->first(true);

            return $object ? $object->value : $default;
        }

        public function all($object = false)
        {
            return Model::Config()->get($object);
        }

        public function attach($key, $value, $model)
        {
            $conf = Model::Config()
            ->firstOrCreate(['key' => $key, 'value' => $value]);

            $conf->attach($model);
        }
    }
