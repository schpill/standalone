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

    class PivotLib
    {
        public function attach($model1, $model2, $args = [])
        {
            if (!is_object($model1)) {
                throw new Exception('the first argument must be a model.');
            }

            if (!is_object($model2)) {
                throw new Exception('the second argument must be a model.');
            }

            if (!strlen($model1->id)) {
                throw new Exception("attach method requires a valid model 1 [$model1->id].");
            }

            if (!strlen($model2->id)) {
                throw new Exception("attach method requires a valid model 2 [$model2->id].");
            }

            $m1Table = $model1->db()->table;
            $m2Table = $model2->db()->table;

            $names = [(string) $m1Table, (string) $m2Table];

            asort($names);

            $from   = current($names);
            $to     = end($names);

            if ($from == $m1Table) {
                $from_id    = $model1->id;
                $from_db    = $model1->db()->db;

                $to_id      = $model2->id;
                $to_db      = $model2->db()->db;
            } else {
                $from_id    = $model2->id;
                $from_db    = $model2->db()->db;
                $to_id      = $model1->id;
                $to_db      = $model1->db()->db;
            }

            $pivot = Model::Pivot()->firstOrCreate([
                'from_table'    => (string) $from,
                'from_db'       => (string) $from_db,
                'to_db'         => (string) $to_db,
                'to_table'      => (string) $to,
                'from_id'       => (int) $from_id,
                'to_id'         => (int) $to_id,
            ]);

            if (!empty($args)) {
                foreach ($args as $k => $v) {
                    $pivot->$k = $this->cleanInt($v);
                }

                $pivot->save();
            }

            return $pivot;
        }

        private function cleanInt($v)
        {
            if (is_numeric($v)) {
                if (!fnmatch('*.*', $v) && !fnmatch('*,*', $v)) {
                    $v = (int) $v;
                } else {
                    $v = (double) $v;
                }
            }

            return $v;
        }

        public function detach($model1, $model2)
        {
            if (!is_object($model1)) {
                throw new Exception('the first argument must be a model.');
            }

            if (!is_object($model2)) {
                throw new Exception('the second argument must be a model.');
            }

            if (!strlen($model1->id)) {
                throw new Exception("attach method requires a valid model 1.");
            }

            if (!strlen($model2->id)) {
                throw new Exception("attach method requires a valid model 2.");
            }

            $m1Table = $model1->db()->table;
            $m2Table = $model2->db()->table;

            $names = [(string) $m1Table, (string) $m2Table];

            asort($names);

            $from   = current($names);
            $to     = end($names);

            if ($from == $m1Table) {
                $from_id    = $model1->id;
                $from_db    = $model1->db()->db;

                $to_id      = $model2->id;
                $to_db      = $model2->db()->db;
            } else {
                $from_id    = $model2->id;
                $from_db    = $model2->db()->db;
                $to_id      = $model1->id;
                $to_db      = $model1->db()->db;
            }

            $pivot = Model::Pivot()
            ->where(['from_id', '=', (int) $from_id])
            ->where(['to_id', '=', (int) $to_id])
            ->where(['from_db', '=', (string) $from_db])
            ->where(['to_db', '=', (string) $to_db])
            ->where(['from_table', '=', (string) $from])
            ->where(['to_table', '=', (string) $to])
            ->first(true);

            if ($pivot) {
                $pivot->delete();

                return true;
            }

            return false;
        }

        public function exists($model1, $model2)
        {
            if (!is_object($model1)) {
                throw new Exception('the first argument must be a model.');
            }

            if (!is_object($model2)) {
                throw new Exception('the second argument must be a model.');
            }

            if (!strlen($model1->id)) {
                throw new Exception("detach method requires a valid model 1.");
            }

            if (!strlen($model2->id)) {
                throw new Exception("detach method requires a valid model 2.");
            }

            $m1Table = $model1->db()->table;
            $m2Table = $model2->db()->table;

            $names = [(string) $m1Table, (string) $m2Table];

            asort($names);

            $from   = current($names);
            $to     = end($names);

            if ($from == $m1Table) {
                $from_id    = $model1->id;
                $from_db    = $model1->db()->db;

                $to_id      = $model2->id;
                $to_db      = $model2->db()->db;
            } else {
                $from_id    = $model2->id;
                $from_db    = $model2->db()->db;
                $to_id      = $model1->id;
                $to_db      = $model1->db()->db;
            }

            $count = Model::Pivot()
            ->where(['from_id', '=', (int) $from_id])
            ->where(['to_id', '=', (int) $to_id])
            ->where(['from_db', '=', (string) $from_db])
            ->where(['to_db', '=', (string) $to_db])
            ->where(['from_table', '=', (string) $from])
            ->where(['to_table', '=', (string) $to])
            ->count();

            return $count > 0 ? true : false;
        }

        public function retrieve($model, $pivot)
        {
            if (!is_object($model)) {
                throw new Exception('the first argument must be a model.');
            }

            if (!strlen($model->id)) {
                throw new Exception("attach method requires a valid model 1.");
            }

            $names = [(string) $model->db()->table, (string) $pivot];

            asort($names);

            $from   = current($names);
            $to     = end($names);

            if ($from == $model->db()->table) {
                $rows = Model::Pivot()
                ->where(['from_table', '=', (string) $from])
                ->where(['from_id', '=', (int) $model->id])
                ->where(['from_db', '=', (string) $model->db()->db])
                ->where(['to_table', '=', (string) $pivot])
                ->exec(true);
            } else {
                $rows = Model::Pivot()
                ->where(['from_table', '=', (string) $from])
                ->where(['to_id', '=', (int) $model->id])
                ->where(['to_db', '=', (string) $model->db()->db])
                ->where(['to_table', '=', (string) $model->db()->table])
                ->exec(true);
            }

            $collection = lib('collection');

            foreach ($rows as $row) {
                $tab = $row->toArray();

                unset($tab['to_db']);
                unset($tab['to_table']);
                unset($tab['to_id']);

                unset($tab['from_db']);
                unset($tab['from_table']);
                unset($tab['from_id']);

                unset($tab['id']);
                unset($tab['created_at']);
                unset($tab['updated_at']);

                if ($from == $model->db()->table) {
                    $object = rdb((string) $row->to_db, (string) $row->to_table)->find((int) $row->to_id)->toArray();
                } else {
                    $object = rdb((string) $row->from_db, (string) $row->from_table)->find((int) $row->from_id)->toArray();
                }

                if (!empty($tab)) {
                    $object['pivot'] = $tab;
                }

                $collection[] = $object;
            }

            return $collection;
        }


        public function delete($model, $pivot)
        {
            if (!is_object($model)) {
                throw new Exception('the first argument must be a model.');
            }

            if (!strlen($model->id)) {
                throw new Exception("attach method requires a valid model.");
            }

            $names = [$model->db()->table, $pivot];

            asort($names);

            $from   = current($names);
            $to     = end($names);

            if ($from == $model->db()->table) {
                $rows = Model::Pivot()
                ->where(['from_table', '=', (string) $from])
                ->where(['from_id', '=', (int) $model->id])
                ->where(['from_db', '=', (string) $model->db()->db])
                ->where(['to_table', '=', (string) $pivot])
                ->exec(true);
            } else {
                $rows = Model::Pivot()
                ->where(['from_table', '=', (string) $from])
                ->where(['to_id', '=', (int) $model->id])
                ->where(['to_db', '=', (string) $model->db()->db])
                ->where(['to_table', '=', (string) $model->db()->table])
                ->exec(true);
            }

            return $rows->delete();
        }

        public function has($model, $pivot)
        {
            if (is_object($pivot)) {
                if ($pivot instanceof \Dbredis\Db) {
                    $pivot = $pivot->table;
                } else {
                    $pivot = $pivot->db()->table;
                }
            }

            if (!is_object($model)) {
                throw new Exception('the first argument must be a model.');
            }

            if (!strlen($model->id)) {
                throw new Exception("attach method requires a valid model 1.");
            }

            $names = [(string) $model->db()->table, (string) $pivot];

            asort($names);

            $from   = current($names);
            $to     = end($names);

            if ($from == $model->db()->table) {
                $count = Model::Pivot()
                ->where(['from_table', '=', (string) $from])
                ->where(['from_id', '=', (int) $model->id])
                ->where(['from_db', '=', (string) $model->db()->db])
                ->where(['to_table', '=', (string) $pivot])
                ->count();
            } else {
                $count = Model::Pivot()
                ->where(['from_table', '=', (string) $pivot])
                ->where(['to_id', '=', (int) $model->id])
                ->where(['to_db', '=', (string) $model->db()->db])
                ->where(['to_table', '=', (string) $to])
                ->count();
            }

            return $count > 0 ? true : false;
        }

        public function __call($m, $a)
        {
            if (fnmatch('retrieve*', $m) && strlen($m) > strlen('retrieve')) {
                $pivot = Inflector::lower(str_replace('retrieve', '', $m));

                return call_user_func_array([$this, 'retrieve'], [current($a), (string) $pivot]);
            }

            if (fnmatch('get*', $m) && strlen($m) > strlen('get')) {
                $pivot = Inflector::lower(str_replace('get', '', $m));

                $res = call_user_func_array([$this, 'retrieve'], [current($a), (string) $pivot]);

                $last = $m[strlen($m) - 1];

                if ('s' == $last) {
                    return $res;
                }

                $row = $res->first();

                if (!$row) {
                    $obj    = current($a);
                    $field  = $pivot . '_id';
                    $table  = ucfirst($pivot);
                    $row    = Model::$table()->find((int) $obj->$field, false);
                }

                return $row;
            }
        }
    }
