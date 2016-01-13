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

    class AggregateLib
    {
        public function add($name, $method)
        {
            return Model::Aggregatedefinition()->firstOrCreate(['name' => $name])->setMethod($method)->save();
        }

        public function has($name)
        {
            return Model::Aggregatedefinition()->where(['name' => $name])->cursor()->count() > 0 ? true : false;
        }

        public function delete($name)
        {
            if ($this->has($name)) {
                Model::Aggregatedefinition()->where(['name' => $name])->first(true)->delete();

                return true;
            }

            return false;
        }

        public function exec($name = null)
        {
            set_time_limit(0);

            $i = new Aggregats;

            $i->setup();

            $q = Model::Aggregatedefinition();

            if (!is_null($name)) {
                $q->where(['name' => $name]);
            }

            $rows = $q->cursor();

            foreach ($rows as $row) {
                $method = isAke($row, 'method', false);

                if (false !== $method) {
                    call_user_func_array([$i, $method], []);
                }
            }
        }
    }
