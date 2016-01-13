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

    class OptionLib
    {
        public function set($key, $value, $model)
        {
            if (!is_object($model)) {
                throw new Exception('the third argument must be a model.');
            }

            $option = Model::Opt()->firstOrCreate([
                'key' => (string) $key,
                'object_id' => (int) $model->id,
                'object_database' => (string) $model->_db->db,
                'object_table' => (string) $model->_db->table,
            ]);

            $option->setValue($value)->save();

            return true;
        }

        public function get($key, $model, $default = null)
        {
            if (!is_object($model)) {
                throw new Exception('the second argument must be a model.');
            }

            $cb = function ($key, $model, $default = null) {
                $option = Model::Opt()
                ->where(['key', '=', (string) $key])
                ->where(['object_id', '=', (int) $model->id])
                ->where(['object_database', '=', (string) $model->_db->db])
                ->where(['object_table', '=', (string) $model->_db->table])
                ->first(true);

                return $option ? $option->value : $default;
            };

            return lib('utils')->remember(
                "get.opt.$model->id.$key." . $model->_db->db . '.' . $model->_db->table,
                $cb,
                Model::Opt()->getAge(),
                [$key, $model, $default]
            );
        }

        public function has($key, $model)
        {
            if (!is_object($model)) {
                throw new Exception('the second argument must be a model.');
            }

            $option = Model::Opt()
            ->where(['key', '=', (string) $key])
            ->where(['object_id', '=', (int) $model->id])
            ->where(['object_database', '=', (string) $model->_db->db])
            ->where(['object_table', '=', (string) $model->_db->table])
            ->count();

            return $count > 0 ? true : false;;
        }

        public function delete($key, $model)
        {
            if (!is_object($model)) {
                throw new Exception('the second argument must be a model.');
            }

            $option = Model::Opt()
            ->where(['key', '=', (string) $key])
            ->where(['object_id', '=', (int) $model->id])
            ->where(['object_database', '=', (string) $model->_db->db])
            ->where(['object_table', '=', (string) $model->_db->table])
            ->first(true);

            if ($option) {
                $option->delete();

                return true;
            }

            return false;
        }

        public function del($key, $model)
        {
            return $this->delete($key, $model);
        }

        public function all($model)
        {
            if (!is_object($model)) {
                throw new Exception('the first argument must be a model.');
            }

            $collection = [];

            $options = Model::Opt()
            ->where(['object_id', '=', (int) $model->id])
            ->where(['object_database', '=', (string) $model->_db->db])
            ->where(['object_table', '=', (string) $model->_db->table])
            ->exec();

            foreach ($options as $option) {
                $collection[$option['key']] = $option['value'];
            }

            return $collection;
        }

        public function getOptionsByMarket($segment_id)
        {
            if (!is_integer($segment_id)) {
                throw new Exception("segment_id must be an integer id.");
            }

            $model = [];

            $file = APPLICATION_PATH . DS . 'models' . DS . 'options' . DS . $segment_id . '.php';

            if (File::exists($file)) {
                $model = include($file);
            }

            return $model;
        }
    }
