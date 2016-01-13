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

    class IndexationLib
    {
        public function words($str)
        {
            return explode(
                ' ',
                lib('utils')->sanitize($str)
            );
        }

        public function make($model, $field)
        {
            $db = Model::IndexedData();

            $modelTable = $model->_db->table;
            $modelDb    = $model->_db->db;

            $id = $model->id;

            $words = $this->words($model->$field);

            foreach ($words as $word) {
                $row = [
                    'db'        => $modelDb,
                    'table'     => $modelTable,
                    'field'     => $field,
                    'model_id'  => (int) $id,
                    'model'     => $model->toArray(),
                    'word'      => $word
                ];

                $db->create($row)->save();
            }
        }

        public function search($rec, $strict = true, $table = null, $field = null, $id = null, $db = null)
        {
            $collection = [];

            $words = $this->words($rec);

            foreach ($words as $word) {
                if ($strict) {
                    $rows = $this->query($table, $field, $id, $db)->where([
                        'word', '=', $word
                    ])->cursor();
                } else {
                    $rows = $this->query($table, $field, $id, $db)->where([
                        'word', 'LIKE', "%$word%"
                    ])->cursor();
                }

                foreach ($rows as $row) {
                    $collection[] = $row['model'];
                }
            }

            return $collection;
        }

        public function query($table, $field, $id, $db)
        {
            $db = is_null($db) ? SITE_NAME : $db;

            $query = Model::IndexedData()->where([
                'db', '=', $db
            ]);

            if (!is_null($table)) {
                $query = $query->where([
                    'table', '=', $table
                ]);
            }

            if (!is_null($field)) {
                $query = $query->where([
                    'field', '=', $field
                ]);
            }

            if (!is_null($id)) {
                $query = $query->where([
                    'model_id', '=', (int) $id
                ]);
            }

            return $query;
        }
    }
