<?php
    /**
     * Thin is a swift Framework for PHP 5.4+
     *
     * @package    Thin
     * @version    1.0
     * @author     Gerald Plusquellec
     * @license    BSD License
     * @copyright  1996 - 2016 Gerald Plusquellec
     * @link       http://github.com/schpill/thin
     */

    namespace Thin;

    class CloudLib
    {
        private $cache, $ns, $store;

        public function __construct($ns = 'cloud.cache')
        {
            $this->ns       = $ns;
            $this->cache    = fmr('cloud');
            $this->store    = new Cloud($ns);
        }

        public function __call($m, $a)
        {
            return call_user_func_array([$this->store, $m], $a);
        }

        public function set($key, $value)
        {
            $cacheKey = $this->getKey($key);

            $this->cache->delete('age.' . $cacheKey);
            $this->cache->delete('has.' . $cacheKey);
            $this->cache->set($cacheKey, $value);

            $this->store->set($key, $value);

            return $this;
        }

        public function get($key, $default = null)
        {
            $cacheKey = $this->getKey($key);

            $store = $this->store;
            $cache = $this->cache;

            return $this->cache->getOr($cacheKey, function () use ($cacheKey, $cache, $store, $key, $default) {
                return $store->get($key, $default, function () use ($cacheKey, $cache) {
                    $cache->delete($cacheKey);
                    $cache->delete('age.' . $cacheKey);
                    $cache->delete('has.' . $cacheKey);
                });
            });
        }

        public function getDelete($key, $default = null)
        {
            if ($this->has($key)) {
                $value = $this->get($key, $default);

                $this->delete($key);

                return $value;
            }

            return null;
        }

        public function delete($key)
        {
            $cacheKey = $this->getKey($key);

            $this->cache->delete($cacheKey);
            $this->cache->delete('age.' . $cacheKey);
            $this->cache->delete('has.' . $cacheKey);

            return $this->store->delete($key);
        }


        public function del($key)
        {
            return $this->delete($key);
        }

        public function has($key)
        {
            $cacheKey = $this->getKey($key);

            $store = $this->store;
            $cache = $this->cache;

            return $this->cache->getOr('has.' . $cacheKey, function () use ($store, $key, $cacheKey, $cache) {
                return $store->has($key, function () use ($cacheKey, $cache) {
                    $cache->delete($cacheKey);
                    $cache->delete('age.' . $cacheKey);
                    $cache->delete('has.' . $cacheKey);
                });
            });
        }

        public function getSize($key)
        {
            return strlen($this->get($key));
        }

        public function age($key)
        {
            $cacheKey = $this->getKey($key);

            $store = $this->store;

            return $this->cache->getOr('age.' . $cacheKey, function () use ($store, $key) {
                return $store->age($key);
            });
        }

        public function aged()
        {
            return $this->store->aged();
        }

        public function getAge($key)
        {
            return $age = $this->age($key) ? date('d/m/Y H:i:s', $age) : false;
        }

        public function incr($key, $by = 1)
        {
            $old = $this->get($key, 0);
            $new = $old + $by;

            $this->set($key, $new);

            return $new;
        }

        public function decr($key, $by = 1)
        {
            $old = $this->get($key, 1);
            $new = $old - $by;

            $this->set($key, $new);

            return $new;
        }

        public function hset($hash, $key, $value)
        {
            $cacheKey = $this->getKey($hash . '.' . $key);

            $this->cache->delete($cacheKey);
            $this->cache->delete('age.' . $cacheKey);
            $this->cache->delete('has.' . $cacheKey);

            $this->cache->set($cacheKey, $value);
            $this->store->hset($hash, $key, $value);

            return $this;
        }

        public function hget($hash, $key, $default = null)
        {
            $cacheKey = $this->getKey($hash . '.' . $key);

            $store = $this->store;

            return $this->cache->getOr($cacheKey, function () use ($store, $hash, $key, $default) {
                return $store->hget($hash, $key, $default);
            });
        }

        public function hdelete($hash, $key)
        {
            $cacheKey = $this->getKey($hash . '.' . $key);

            $this->cache->delete($cacheKey);
            $this->cache->delete('age.' . $cacheKey);
            $this->cache->delete('has.' . $cacheKey);

            return $this->store->hdelete($hash, $key);
        }

        public function hdel($hash, $key)
        {
            return $this->hdelete($hash, $key);
        }

        public function hhas($hash, $key)
        {
            $cacheKey = $this->getKey($hash . '.' . $key);

            $store = $this->store;

            return $this->cache->getOr('has.' . $cacheKey, function () use ($store, $hash, $key) {
                return $store->hhas($hash, $key);
            });
        }

        public function hage($hash, $key)
        {
            $cacheKey = $this->getKey($hash . '.' . $key);

            $store = $this->store;

            return $this->cache->getOr('age.' . $cacheKey, function () use ($store, $hash, $key) {
                return $store->hage($hash, $key);
            });
        }

        public function hincr($hash, $key, $by = 1)
        {
            $old = $this->hget($hash, $key, 1);
            $new = $old + $by;

            $this->hset($hash, $key, $new);

            return $new;
        }

        public function hdecr($hash, $key, $by = 1)
        {
            $old = $this->hget($hash, $key, 1);
            $new = $old - $by;

            $this->hset($hash, $key, $new);

            return $new;
        }

        public function getKey($k)
        {
            return 'cloud.' . $this->ns . '.' . $k;
        }
    }
