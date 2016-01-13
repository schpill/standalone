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

    class SettingLib
    {
        public function set($key, $value, $model)
        {
            if (!is_object($model)) {
                throw new Exception('the third argument must be a model.');
            }

            $setting = Model::Setting()->firstOrCreate([
                'key' => (string) $key,
                'object_id' => (int) $model->id,
                'object_database' => (string) $model->_db->db,
                'object_table' => (string) $model->_db->table,
            ]);

            $setting->setValue($value)->save();

            return true;
        }

        public function get($key, $model, $default = null)
        {
            if (!is_object($model)) {
                throw new Exception('the second argument must be a model.');
            }

            $setting = Model::Setting()
            ->where(['key', '=', (string) $key])
            ->where(['object_id', '=', (int) $model->id])
            ->where(['object_database', '=', (string) $model->_db->db])
            ->where(['object_table', '=', (string) $model->_db->table])
            ->first(true);

            return $setting ? $setting->value : $default;
        }

        public function has($key, $model)
        {
            if (!is_object($model)) {
                throw new Exception('the second argument must be a model.');
            }

            $setting = Model::Setting()
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

            $setting = Model::Setting()
            ->where(['key', '=', (string) $key])
            ->where(['object_id', '=', (int) $model->id])
            ->where(['object_database', '=', (string) $model->_db->db])
            ->where(['object_table', '=', (string) $model->_db->table])
            ->first(true);

            if ($setting) {
                $setting->delete();

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

            $settings = Model::Setting()
            ->where(['object_id', '=', (int) $model->id])
            ->where(['object_database', '=', (string) $model->_db->db])
            ->where(['object_table', '=', (string) $model->_db->table])
            ->exec();

            foreach ($settings as $setting) {
                $collection[$setting['key']] = $setting['value'];
            }

            return $collection;
        }
    }
