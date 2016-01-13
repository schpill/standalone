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

    namespace Live;

    use Thin\Inflector;
    use Thin\Exception;

    class Query
    {
        private $orm, $db, $table, $collection, $limit, $offset, $joins = [], $selects = [], $wheres = [], $orders = [], $groupBy;

        public function from($what)
        {
            if (fnmatch("*.*", $what)) {
                list($this->db, $this->table) = explode('.', $what, 2);
            } else {
                $this->db       = SITE_NAME;
                $this->table    = $what;
            }

            $this->collection = $this->db . '.' . $this->table;
            $this->orm = Db::instance($this->db, $this->table);

            return $this;
        }

        public function get()
        {
            $this->hydrate();

            return $this->orm->get();
        }

        public function hydrate()
        {
            $this->orm->wheres     = $this->wheres;
            $this->orm->selects    = $this->selects;
            $this->orm->orders     = $this->orders;
            $this->orm->offset     = $this->offset;
            $this->orm->limit      = $this->limit;
            $this->orm->groupBy    = $this->groupBy;

            return $this;
        }

        public function count()
        {
            $this->hydrate();

            return $this->orm->get()->count();
        }

        public function sum($field)
        {
            $this->hydrate();

            return $this->orm->get()->sum($field);
        }

        public function avg($field)
        {
            $this->hydrate();

            return $this->orm->get()->avg($field);
        }

        public function min($field)
        {
            $this->hydrate();

            return $this->orm->get()->min($field);
        }

        public function max($field)
        {
            $this->hydrate();

            return $this->orm->get()->max($field);
        }

        public function rand($amount = 1)
        {
            $this->hydrate();

            return $this->orm->get()->rand($amount);
        }

        public function first($model = false)
        {
            $this->hydrate();

            return $this->orm->get()->first($model);
        }

        public function last($model = false)
        {
            $this->hydrate();

            return $this->orm->get()->last($model);
        }

        public function where()
        {
            $rags = func_get_args();

            if (func_num_args() == 4) {
                $field = $args[0];
                $operator = $args[1];
                $value = $args[2];
                $op = $args[3];
            } elseif (func_num_args() == 3) {
                $field = $args[0];
                $operator = $args[1];
                $value = $args[2];
                $op = 'AND';
            } else {
                throw new Exception('A wrong number of arguments.');
            }

            $condition = [$field, $operator, $value];

            if (strtoupper($op) == 'AND') {
                $op = '&&';
            } elseif (strtoupper($op) == 'OR') {
                $op = '||';
            } elseif (strtoupper($op) == 'XOR') {
                $op = '|';
            }

            $this->wheres[sha1(serialize(func_get_args()))] = [$condition, $op];

            return $this;
        }

        public function order($fieldOrder, $orderDirection = 'ASC')
        {
            $this->orders[$fieldOrder] = $orderDirection;

            return $this;
        }

        public function groupBy($field)
        {
            $this->groupBy = $field;

            return $this;
        }

        public function limit($limit, $offset = 0)
        {
            if (null !== $limit) {
                if (!is_numeric($limit) || $limit != (int) $limit) {
                    throw new \InvalidArgumentException('The limit is not valid.');
                }

                $limit = (int) $limit;
            }

            if (null !== $offset) {
                if (!is_numeric($offset) || $offset != (int) $offset) {
                    throw new \InvalidArgumentException('The offset is not valid.');
                }

                $offset = (int) $offset;
            }

            $this->limit    = $limit;
            $this->offset   = $offset;

            return $this;
        }

        public function offset($offset = 0)
        {
            if (null !== $offset) {
                if (!is_numeric($offset) || $offset != (int) $offset) {
                    throw new \InvalidArgumentException('The offset is not valid.');
                }

                $offset = (int) $offset;
            }

            $this->offset = $offset;

            return $this;
        }

        public function select($what)
        {
            /* polymorphism */
            if (is_string($what)) {
                if (fnmatch('*,*', $what)) {
                    $what = str_replace(' ', '', $what);
                    $what = explode(',', $what);
                }
            }

            if (is_array($what)) {
                foreach ($what as $seg) {
                    if (!in_array($seg, $this->selects)) {
                        $this->selects[] = $seg;
                    }
                }
            } else {
                if (!in_array($what, $this->selects)) {
                    $this->selects[] = $what;
                }
            }

            return $this;
        }

        public function join($table, $field = null, $db = null)
        {
            $db     = is_null($db) ? $this->db : $db;
            $field  = is_null($field) ? $table . '_id' : $field;

            $this->joins[] = [$table, $field, $db];

            return $this;
        }
    }
