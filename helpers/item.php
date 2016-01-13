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

    class ItemLib implements AA
    {
        private static $data = [];
        private $ns;

        public function __construct($ns = 'core', array $data = [])
        {
            $this->ns = $ns;

            if (!isset(self::$data[$ns])) {
                self::$data[$ns] = [];

                if (!empty($data)) {
                    $this->fill($data);
                }
            }
        }

        public function getInstance($ns = 'core', array $data = [])
        {
            if (!isset(self::$data[$ns])) {
                self::$data[$ns] = new self($ns, $dat);
            }

            return self::$data[$ns];
        }

        public function __set($k, $v = null)
        {
            if (is_array($k)) {
                foreach ($k as $innerKey => $innerValue) {
                    array_set(self::$data[$this->ns], $innerKey, $innerValue);
                }
            } else {
                array_set(self::$data[$this->ns], $k, $v);
            }

            return $this;
        }

        public function __get($k)
        {
            return array_get(self::$data[$this->ns], $k, null);
        }

        public function __isset($k)
        {
            return array_has(self::$data[$this->ns], $k);
        }

        public function __unset($k)
        {
            array_forget(self::$data[$this->ns], $k);

            return $this;
        }

        public function set($k, $v)
        {
            if (is_array($k)) {
                foreach ($k as $innerKey => $innerValue) {
                    array_set(self::$data[$this->ns], $innerKey, $innerValue);
                }
            } else {
                array_set(self::$data[$this->ns], $k, $v);
            }

            return $this;
        }

        public function offsetSet($k, $v)
        {
            if (is_array($k)) {
                foreach ($k as $innerKey => $innerValue) {
                    array_set(self::$data[$this->ns], $innerKey, $innerValue);
                }
            } else {
                array_set(self::$data[$this->ns], $k, $v);
            }

            return $this;
        }

        public function get($k, $default = null)
        {
            return array_get(self::$data[$this->ns], $k, $default);
        }

        public function offsetGet($k)
        {
            return array_get(self::$data[$this->ns], $k, null);
        }

        public function has($k)
        {
            return array_has(self::$data[$this->ns], $k);
        }

        public function offsetExists($k)
        {
            return array_has(self::$data[$this->ns], $k);
        }

        public function offsetUnset($k)
        {
            array_forget(self::$data[$this->ns], $k);

            return $this;
        }

        public function delete($k)
        {
            array_forget(self::$data[$this->ns], $k);

            return $this;
        }

        public function forget($k)
        {
            array_forget(self::$data[$this->ns], $k);

            return $this;
        }

        public function remove($k)
        {
            array_forget(self::$data[$this->ns], $k);

            return $this;
        }

        public function del($k)
        {
            array_forget(self::$data[$this->ns], $k);

            return $this;
        }

        public function fill(array $data)
        {
            foreach ($data as $k => $v) {
                array_set(self::$data[$this->ns], $k, $v);
            }

            return $this;
        }

        public function all()
        {
            return self::$data[$this->ns];
        }

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
