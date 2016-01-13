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

    use SQLite3;
    use ArrayAccess as AA;

    class MemoryLib implements AA
    {
        private $ns;

        /**
         *
         * @method __construct
         *
         * @param  string
         * @param  array
         */
        public function __construct($ns = 'core', array $data = [])
        {
            $this->ns = $ns;
            $db = new SQLite3(':memory:');

            Now::set("memorylite.link.$ns", $db);

            $q = "CREATE TABLE IF NOT EXISTS cachedb (data_key VARCHAR PRIMARY KEY, data_value);";
            $res = $this->db->exec($q);

            if (!empty($data)) {
                foreach ($data as $k => $v) {
                    $this->set($k, $v);
                }
            }
        }

        /**
         *
         * @method __set
         *
         * @param  [type]
         * @param  [type]
         */
        public function __set($k, $v)
        {
            return $this->set($k, $v);
        }

        /**
         *
         * @method __get
         *
         * @param  [type]
         *
         * @return [type]
         */
        public function __get($k)
        {
            if ($k == 'db') {
                $nowKey = 'memorylite.link.' . $this->ns;

                return Now::get($nowKey);
            }

            return $this->get($k);
        }

        /**
         *
         * @method __isset
         *
         * @param  [type]
         *
         * @return boolean
         */
        public function __isset($k)
        {
            return $this->has($k);
        }

        /**
         *
         * @method __unset
         *
         * @param  [type]
         */
        public function __unset($k)
        {
            return $this->del($k);
        }

        /**
         *
         * @method set
         *
         * @param  [type]
         * @param  [type]
         */
        public function set($k, $v)
        {
            $k = $this->ns . '.' . $k;
            $query = "DELETE FROM cachedb WHERE data_key = '$k'";
            $this->db->exec($query);

            $query = "INSERT INTO cachedb (data_key, data_value) VALUES('$k', '" . SQLite3::escapeString(serialize($v)) . "');";

            $this->db->exec($query);

            return $this;
        }

        /**
         *
         * @method offsetSet
         *
         * @param  [type]
         * @param  [type]
         *
         * @return [type]
         */
        public function offsetSet($k, $v)
        {
            return $this->set($k, $v);
        }

        /**
         *
         * @method get
         *
         * @param  [type]
         * @param  [type]
         *
         * @return [type]
         */
        public function get($k, $default = null)
        {
            $k = $this->ns . '.' . $k;
            $query = "SELECT data_value FROM cachedb WHERE data_key = '$k'";
            $result = $this->db->query($query);

            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                return unserialize($row['data_value']);
            }

            return $default;
        }

        public function keys($pattern = '*')
        {
            $collection = [];

            $pattern = $this->ns . '.' . str_replace('*', '%', $pattern);

            $query = "SELECT data_key FROM cachedb WHERE data_key LIKE '" . SQLite3::escapeString($pattern) . "'";

            $result = $this->db->query($query);

            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $collection[] = str_replace($this->ns . '.', '', $row['data_key']);
            }

            return $collection;
        }

        /**
         *
         * @method offsetGet
         *
         * @param  [type]
         *
         * @return [type]
         */
        public function offsetGet($k)
        {
            return $this->get($k);
        }

        /**
         *
         * @method has
         *
         * @param  [type]
         *
         * @return boolean
         */
        public function has($k)
        {
            $k = $this->ns . '.' . $k;
            $query = "SELECT COUNT(data_key) AS nb FROM cachedb WHERE data_key = '$k'";
            $result = $this->db->query($query);

            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                return $row['nb'] > 0;
            }

            return false;
        }

        /**
         *
         * @method offsetExists
         *
         * @param  [type]
         *
         * @return [type]
         */
        public function offsetExists($k)
        {
            return $this->has($k);
        }

        /**
         *
         * @method offsetUnset
         *
         * @param  [type]
         *
         * @return [type]
         */
        public function offsetUnset($k)
        {
            return $this->delete($k);
        }

        /**
         *
         * @method delete
         *
         * @param  [type]
         *
         * @return [type]
         */
        public function delete($k)
        {
            $k = $this->ns . '.' . $k;
            $query = "DELETE FROM cachedb WHERE data_key = '$k'";
            $this->db->exec($query);

            return $this;
        }

        /**
         *
         * @method forget
         *
         * @param  [type]
         *
         * @return [type]
         */
        public function forget($k)
        {
            return $this->delete($k);
        }

        /**
         *
         * @method remove
         *
         * @param  [type]
         *
         * @return [type]
         */
        public function remove($k)
        {
            return $this->delete($k);
        }

        /**
         *
         * @method del
         *
         * @param  [type]
         *
         * @return [type]
         */
        public function del($k)
        {
            return $this->delete($k);
        }

        public function incr($k, $by = 1)
        {
            $old = $this->get($k, 0);
            $new = $old + $by;

            $this->set($k, $new);

            return $new;
        }

        public function decr($k, $by = 1)
        {
            $old = $this->get($k, 1);
            $new = $old - $by;

            $this->set($k, $new);

            return $new;
        }

        /**
         *
         * @method fill
         *
         * @param  array
         *
         * @return [type]
         */
        public function fill(array $data)
        {
            foreach ($data as $k => $v) {
                $this->set($k, $v);
            }

            return $this;
        }

        /**
         *
         * @method __call
         *
         * @param  [type]
         * @param  [type]
         *
         * @return [type]
         */
        public function __call($m, $a)
        {
            if (fnmatch('get*', $m)) {
                $key = Inflector::uncamelize(substr($m, 3));
                $default = empty($a) ? null : current($a);

                return $this->get($key, $default);
            } elseif (fnmatch('set*', $m)) {
                $key = Inflector::uncamelize(substr($m, 3));

                return $this->set($key, current($a));

            } elseif (fnmatch('has*', $m)) {
                $key = Inflector::uncamelize(substr($m, 3));

                return $this->has($key);

            } elseif (fnmatch('del*', $m)) {
                $key = Inflector::uncamelize(substr($m, 3));

                return $this->delete($key);
            } else {
                $closure = $this->get($m);

                if (is_string($closure) && fnmatch('*::*', $closure)) {
                    list($c, $f) = explode('::', $closure, 2);

                    try {
                        $i = lib('caller')->make($c);

                        return call_user_func_array([$i, $f], $a);
                    } catch (\Exception $e) {
                        $default = empty($a) ? null : current($a);

                        return empty($closure) ? $default : $closure;
                    }
                } else {
                    if (is_callable($closure)) {
                        return call_user_func_array($closure, $a);
                    }

                    if (!empty($a) && empty($closure)) {
                        if (count($a) == 1) {
                            return $this->set($m, current($a));
                        }
                    }

                    $default = empty($a) ? null : current($a);

                    return empty($closure) ? $default : $closure;
                }
            }
        }
    }
