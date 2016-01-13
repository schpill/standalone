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

    use Thin\Fluent;

    class Schema
    {
        private $db, $columns = [], $commands = [], $scopes = [];
        private static $instances = [];

        public static function create(Db $db, callable $callback)
        {
            $i = self::instance($db);

            $i = call_user_func_array($callback, [$i]);

            $i->save();
        }

        public static function instance(Db $db)
        {
            $collection = "$db->db.$db->table";

            $i = isAke(self::$instances, $collection, false);

            if (!$i) {
                $i = new self($db);
                self::$instances[$collection] = $i;
            }

            return $i;
        }

        public function __construct(Db $db)
        {
            $this->db = $db;
        }

        public function dropIfExists()
        {
            if (!$this->isEmpty()) {
                $this->drop();
            }

            return $this;
        }

        public function drop()
        {
            $this->db = $this->db->drop();

            return $this;
        }

        public function rename($from, $to)
        {
            $this->db = $this->db->rename($to);

            return $this;
        }

        public function getColumnListing()
        {
            return array_keys($this->db->store->get('schema', []));
        }

        public function hasColumn($column)
        {
            $fields = $this->getColumnListing();

            $column = strtolower($column);

            return in_array($column, array_map('strtolower', $fields));
        }

        public function hasColumns(array $columns)
        {
            $fields = $this->getColumnListing();

            $tableColumns = array_map('strtolower', $fields);

            foreach ($columns as $column) {
                if (!in_array(strtolower($column), $tableColumns)) {
                    return false;
                }
            }

            return true;
        }

        public function isEmpty()
        {
            return is_null($this->db->first());
        }

        public function addColumn($type, $name, array $parameters = [])
        {
            $attributes = array_merge(
                compact('type', 'name'),
                $parameters
            );

            $this->columns[] = $column = new Fluent($attributes);

            return $column;
        }

        public function addCommand($name, callable $closure)
        {
            $closure = lib('utils')->serializeClosure($closure);

            $this->commands[$name] = $closure;

            return $this;
        }

        public function addScope($name, callable $closure)
        {
            $closure = lib('utils')->serializeClosure($closure);

            $this->scopes[$name] = $closure;

            return $this;
        }

        public static function hasTable($collection)
        {
            if (fnmatch('*.*', $collection)) {
                list($db, $table) = explode('.', $collection, 2);
            } else {
                $db    = SITE_NAME;
                $table = $collection;
            }

            return !self::instance(Db::instance($db, $table))->isEmpty();
        }

        public function __call($m, $a)
        {
            $args = array_merge(
                [$m],
                $a
            );

            return call_user_func_array(
                [$this, 'addColumn'],
                $args
            );
        }

        public function save()
        {
            $fields = [];

            foreach ($this->columns as $column) {
                if ($column instanceof Fluent) {
                    $attributes = $column->getAttributes();
                    $name = isAke($attributes, 'name', false);

                    if ($name) {
                        unset($attributes['name']);

                        $fields[$name] = $attributes;
                    }
                }
            };

            $this->db->store->set('schema', $fields);
            $this->db->store->set('commands', $this->commands);
            $this->db->store->set('scopes', $this->scopes);

            return $fields;
        }
    }
