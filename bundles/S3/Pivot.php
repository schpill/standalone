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

    namespace S3;

    class Pivot
    {
        public $model;

        public function __construct()
        {
            if (func_num_args() == 0) {
                return;
            }

            $row = $attributes = [];

            $args   = func_get_args();
            $db     = SITE_NAME;
            $table  = '';

            foreach ($args as $arg) {
                if (is_object($arg)) {
                    $foreignDb      = $arg->_db->db;
                    $foreignTable   = $arg->_db->table;

                    $field = SITE_NAME == $foreignDb ? '' : $foreignDb . '_';
                    $field .= $foreignTable;
                    $row[$field] = $arg->toArray();
                    $row[$field . '_id'] = $arg->id;

                    if (!strlen($table)) {
                        $table = 'pivot' . $field;
                    } else {
                        $table .= str_replace('_', '', $field);
                    }
                } elseif (is_array($arg)) {
                    $attributes = $arg;
                } elseif (is_string($arg)) {
                    if (fnmatch('*_*', $arg)) {
                        list($db, $table) = explode('_', $arg, 2);
                    } else {
                        $db     = SITE_NAME;
                        $table  = $arg;
                    }
                }
            }

            foreach ($attributes as $k => $v) {
                $row[$k] = $v;
            }

            $this->model = Db::instance($db, $table)->model($row);
        }

        public static function instance()
        {
            return new self;
        }

        public function __call($m, $a)
        {
            if (is_object($this->model)) {
                return call_user_func_array(
                    [$this->model, $m],
                    $a
                );
            }
        }
    }
