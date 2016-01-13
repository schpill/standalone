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

    class UpliftLib
    {
        public function set($key, $value, $model)
        {
            if (!is_object($model)) {
                throw new Exception('the third argument must be a model.');
            }

            $option = Model::Uplift()->firstOrCreate(['key' => $key, 'value' => $value]);
            $option->attach($model, ['key' => $key, 'value' => $value]);

            return true;
        }

        public function get($key, $model, $default = null)
        {
            if (!is_object($model)) {
                throw new Exception('the second argument must be a model.');
            }

            $option = $model->pivots(Model::Uplift())->where(['key', '=', $key])->first(true);

            return $option ? $option->value : $default;
        }

        public function has($key, $model)
        {
            if (!is_object($model)) {
                throw new Exception('the second argument must be a model.');
            }

            $count = $model->pivots(Model::Uplift())->where(['key', '=', $key])->count();

            return $count > 0 ? true : false;
        }

        public function delete($key, $model)
        {
            if (!is_object($model)) {
                throw new Exception('the second argument must be a model.');
            }

            $option = $model->pivots(Model::Uplift())->where(['key', '=', $key])->first(true);

            return $option ? $option->delete() : false;
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

            $options = $model->pivots(Model::Uplift())->orderByKey()->exec();

            foreach ($options as $option) {
                $collection[$option['key']] = $option['value'];
            }

            return $collection;
        }

        public function getProducts($univers, $type = 'info')
        {
            $collection = [];

            $uplifts = Model::FacturationProduct()
            ->where(['name', 'LIKE', '%plift%'])
            ->where(['univers', '=', $univers])
            ->cursor();

            foreach ($uplifts as $uplift) {
                if (fnmatch("*$type*", $uplift['name']) && !fnmatch('*lancement*', $uplift['name'])) {
                    unset($uplift['created_at']);
                    unset($uplift['updated_at']);
                    unset($uplift['category']);
                    unset($uplift['subidcompta']);

                    $uplift['nb_scenario'] = 0;

                    if (fnmatch('*scenario*', $uplift['name'])) {
                        list($segment, $dummy) = explode('scenario', $uplift['name'], 2);

                        if (fnmatch('* *', $segment)) {
                            $tab = explode(' ', $segment);

                            foreach ($tab as $row) {
                                if (is_numeric($row)) {
                                    $uplift['nb_scenario'] = (int) $row;
                                }
                            }
                        }
                    }

                    $collection[] = $uplift;
                }
            }

            return $collection;
        }
    }
