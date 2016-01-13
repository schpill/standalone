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

    class ResourceLib
    {
        private static $resources = [];

        public function set($key, $value)
        {
            $resource = isAke(self::$resources, $key, curl_init());

            curl_setopt(
                $resource,
                CURLOPT_PRIVATE,
                serialize($value)
            );

            self::$resources[$key] = $resource;

            return $this;
        }

        public function get($key, $default = null)
        {
            $resource = isAke(self::$resources, $key, curl_init());

            $data = @unserialize(
                curl_getinfo(
                    $resource,
                    CURLINFO_PRIVATE
                )
            );

            if (false !== $data) {
                return $data;
            }

            return $default;
        }

        public function has($key)
        {
            return isset(self::$resources[$key]);
        }

        public function remove($key)
        {
            unset(self::$resources[$key]);

            return $this;
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
            return $this->remove($k);
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

                return $this->remove($key);
            } else {
                return empty($a) ? $this->get($m) : $this->set($m, current($a));
            }
        }
    }
