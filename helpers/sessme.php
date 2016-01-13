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

    use ArrayAccess as AA;

    class SessmeLib implements AA
    {
        private $db;
        private static $instances = [];

        public function __construct($ns = 'core')
        {
            $this->db = \Raw\Db::instance('sessme', $ns . '.' . $this->forever());
        }

        public function __set($k, $v)
        {
            return $this->set($k, $v);
        }

        public function __get($k)
        {
            return $this->get($k);
        }

        public function __isset($k)
        {
            return $this->has($k);
        }

        public function __unset($k)
        {
            return $this->delete($k);
        }

        public function set($k, $v)
        {
            $exists = $this->db->where(['key', '=', $k])->first(true);

            if ($exists) {
                $update = $exists->setValue($v)->save();
            } else {
                $new    = $this->db->create(['key' => $k, 'value' => $v])->save();
            }

            return $this;
        }

        public function get($k, $default = null)
        {
            $exists = $this->db->where(['key', '=', $k])->first();

            if ($exists) {
                return $exists['value'];
            }

            return $default;
        }

        public function has($k)
        {
            $exists = $this->db->where(['key', '=', $k])->first(true);

            return $exists ? true : false;
        }

        public function offsetSet($k, $v)
        {
            return $this->set($k, $v);
        }

        public function offsetGet($k)
        {
            return $this->get($k);
        }

        public function offsetExists($k)
        {
            return $this->has($k);
        }

        public function offsetUnset($k)
        {
            return $this->delete($k);
        }

        public function delete($k)
        {
            $exists = $this->db->where(['key', '=', $k])->first(true);

            if ($exists) {
                $exists->delete();
            }

            return $this;
        }

        public function forget($k)
        {
            return $this->delete($k);
        }

        public function remove($k)
        {
            return $this->delete($k);
        }

        public function del($k)
        {
            return $this->delete($k);
        }

        private function forever()
        {
            return sha1(session_id());
        }

        public static function __callStatic($m, $a)
        {
            $i = self::instance();

            return call_user_func_array([$i, $m], $a);
        }

        public function __call($m, $a)
        {
            if (fnmatch('get*', $m)) {
                $k = Inflector::uncamelize(substr($m, 3));

                $default = empty($a) ? null : current($a);

                return $this->get($k, $default);
            } elseif (fnmatch('set*', $m)) {
                $k = Inflector::uncamelize(substr($m, 3));

                return $this->set($k, current($a));
            } elseif (fnmatch('has*', $m)) {
                $k = Inflector::uncamelize(substr($m, 3));

                return $this->has($k);
            } elseif (fnmatch('del*', $m)) {
                $k = Inflector::uncamelize(substr($m, 3));

                return $this->del($k);
            } else {
                if (!empty($a)) {

                    if (count($a) == 1) {
                        return $this->set($m, current($a));
                    }
                }

                $closure = $this->get($m);

                if (fnmatch('*::*', $closure)) {
                    list($c, $f) = explode('::', $closure, 2);

                    $i = lib('caller')->make($c);

                    return call_user_func_array([$i, $f], $a);
                } else {
                    if (is_callable($closure)) {
                        return call_user_func_array($closure, $a);
                    }

                    return $closure;
                }
            }
        }

        public static function instance($ns = 'core')
        {
            $i = isAke(self::$instances, $ns, false);

            if (!$i) {
                $i = new self($ns);

                self::$instances[$ns] = $i;
            }

            return $i;
        }
    }
