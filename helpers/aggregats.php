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

    class AggregatsLib implements \Countable, \Iterator
    {
        private $db, $name, $keys, $position = 0;

        public function __construct($name)
        {
            $this->db   = lib('redys', ['aggregats']);
            $this->name = $name;
            $this->keys = $this->db->hkeys($name);
        }

        public function setAge()
        {
            $this->db->set($this->name . '.age', time());
        }

        public function add(array $data)
        {
            $keyTuple = sha1(serialize($data));

            $tuple = $this->db->get($this->name . '.' . $keyTuple . '.tuples');

            if (!$tuple) {
                $id = $this->nextId();
                $this->db->hset($this->name, $id, $data);
                $this->db->set($this->name . '.' . $keyTuple . '.tuples', $id);
                $this->db->set($this->name . '.' . $id . '.tuples', $keyTuple);

                $this->keys = $this->db->hkeys($this->name);

                $this->setAge();
            }

            return $this;
        }

        public function edit($id, array $data)
        {
            $keyTuple = $this->db->get($this->name . '.' . $id . '.tuples');
            $this->db->del($this->name . '.' . $keyTuple . '.tuples');
            $this->db->del($this->name . '.' . $id . '.tuples');
            $this->db->hdel($this->name, $id);

            $keyTuple = sha1(serialize($data));
            $this->db->hset($this->name, $id, $data);
            $this->db->set($this->name . '.' . $keyTuple . '.tuples', $id);
            $this->db->set($this->name . '.' . $id . '.tuples', $keyTuple);

            $this->keys = $this->db->hkeys($this->name);

            $this->setAge();

            return $this;
        }

        public function del($id)
        {
            $keyTuple = $this->db->get($this->name . '.' . $id . '.tuples');
            $this->db->del($this->name . '.' . $keyTuple . '.tuples');
            $this->db->del($this->name . '.' . $id . '.tuples');
            $this->db->hdel($this->name, $id);

            $this->keys = $this->db->hkeys($this->name);

            $this->setAge();

            return $this;
        }

        public function has($id)
        {
            $tuple = $this->db->get($this->name . '.' . $id . '.tuples');

            return $tuple ? true : false;
        }

        public function count()
        {
            $count = $this->db->hlen($this->name);

            return $count;
        }

        public function rewind()
        {
            $this->position = 0;
        }

        public function current()
        {
            if (isset($this->keys[$this->position])) {
                $row = $this->db->hget($this->name, $this->keys[$this->position]);
                $row['id'] = $this->keys[$this->position];

                return $row;
            }

            return null;
        }

        public function key()
        {
            return $this->position;
        }

        public function next()
        {
            ++$this->position;
        }

        public function previous()
        {
            --$this->position;
        }

        public function valid()
        {
            return isset($this->keys[$this->position]);
        }

        public function first()
        {
            if (!empty($this->keys)) {
                $row = $this->db->hget($this->name, $this->keys[0]);
                $row['id'] = $this->keys[0];

                return $row;
            }

            return null;
        }

        public function last()
        {
            if (!empty($this->keys)) {
                $row = $this->db->hget($this->name, $this->keys[count($this->keys) - 1]);
                $row['id'] = $this->keys[count($this->keys) - 1];

                return $row;
            }

            return null;
        }

        public function fetch()
        {
            $row = $this->current();

            $this->next();

            return $row;
        }

        public function where(callable $closure, $collection = false)
        {
            $coll = [];

            $closureKey = $this->getClosureKey($closure);

            $ageQuery   = $this->db->get('query.' . $closureKey . '.age');
            $ageDb = $this->db->get($this->name . '.age');

            if ($ageQuery) {
                if ($ageQuery >= $ageDb) {
                    $dataQuery = $this->db->get('query.' . $closureKey . '.data');

                    if ($dataQuery) {
                        return $collection ? lib('collection', [$dataQuery]) : $dataQuery;;
                    }
                }
            }

            foreach ($this->keys as $key) {
                $row        = $this->db->hget($this->name, $key);
                $row['id']  = $key;
                $row        = $closure($row);

                if ($row) {
                    $coll[] = $row;
                }
            }

            $this->db->set('query.' . $closureKey . '.age', time());
            $this->db->set('query.' . $closureKey . '.data', $coll);

            return $collection ? lib('collection', [$coll]) : $coll;
        }

        public function find($id, $collection = false)
        {
            $row = $this->where(function ($row) use ($id) {
                if ($row['id'] == $id) {
                    return $row;
                }

                return false;
            }, $collection);

            return empty($row) ? null : $row;
        }

        private function nextId()
        {
            $id = $this->db->get($this->name . '.ids');

            if ($id) {
                $newId = (int) $id + 1;
            } else {
                $newId = 1;
            }

            $this->db->set($this->name . '.ids', $newId);

            return $newId;
        }

        private function getClosureKey(callable $c)
        {
            $str    = 'function (';
            $r      = new \ReflectionFunction($c);
            $params = [];

            foreach ($r->getParameters() as $p) {
                $s = '';

                if ($p->isArray()) {
                    $s .= 'array ';
                } else if($p->getClass()) {
                    $s .= $p->getClass()->name . ' ';
                }

                if ($p->isPassedByReference()){
                    $s .= '&';
                }

                $s .= '$' . $p->name;

                if ($p->isOptional()) {
                    $s .= ' = ' . var_export($p->getDefaultValue(), TRUE);
                }

                $params[] = $s;
            }

            $str .= implode(', ', $params);
            $str .= '){' . PHP_EOL;
            $lines = file($r->getFileName());

            for ($l = $r->getStartLine(); $l < $r->getEndLine(); $l++) {
                $str .= $lines[$l];
            }

            return sha1($this->name . $str);
        }
    }
