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

    class EphemereLib
    {
        private $dir;

        public function __construct($ns = 'core')
        {
            $native = Config::get('dir.ephemere', '/tmp');

            $this->dir = $native . DS . $ns;

            if (!is_dir($this->dir)) {
                File::mkdir($this->dir);
            }
        }

        public function set($k, $v, $expire = null)
        {
            $file = $this->dir . DS . $k . '.eph';

            File::delete($k);

            File::put($file, serialize($v));

            $expire = is_null($expire) ? strtotime('+10 year') : time() + $expire;

            touch($file, $expire);

            return $this;
        }

        public function setExpireAt($k, $v, $timestamp)
        {
            $file = $this->dir . DS . $k . '.eph';

            File::delete($k);

            File::put($file, serialize($v));

            touch($file, $timestamp);

            return $this;
        }

        public function add($k, $v, $expire = null)
        {
            return $this->set($k, $v, $expire);
        }

        public function setExp($k, $v, $expire)
        {
            return $this->set($k, $v, $expire);
        }

        public function setExpire($k, $v, $expire)
        {
            return $this->set($k, $v, $expire);
        }

        public function expire($k, $expire)
        {
            $v = $this->get($k);

            return $this->set($k, $v, $expire);
        }

        public function expireAt($k, $timestamp)
        {
            $v = $this->get($k);

            return $this->set($k, $v, $timestamp);
        }

        public function get($k, $d = null)
        {
            $file = $this->dir . DS . $k . '.eph';

            if (file_exists($file)) {
                $age = filemtime($file);

                if ($age >= time()) {
                    return unserialize(File::read($file));
                } else {
                    File::delete($file);
                }
            }

            return $d;
        }

        public function getOr($k, callable $c, $e = null)
        {
            if ($this->has($k)) {
                return $this->get($k);
            }

            $res = $c();

            $this->set($k, $res, $e);

            return $res;
        }

        public function session($k, $v = 'dummyget', $e = null)
        {
            $user       = session('front')->getUser();
            $isLogged   = !is_null($user);

            $key = $isLogged ? sha1(lng() . '.' . forever() . '1.' . $k) :  sha1(lng() . '.' . forever() . '0.' . $k);

            return 'dummyget' == $v ? $this->get($key) : $this->set($key, $v, $e);
        }

        public function aged($k, callable $c, $a)
        {
            $k = sha1($this->dir) . '.' . $k;

            return ageCache($k, $c, $a);
        }

        public function has($k)
        {
            $file = $this->dir . DS . $k . '.eph';

            if (file_exists($file)) {
                $age = filemtime($file);

                if ($age >= time()) {
                    return true;
                } else {
                    File::delete($file);
                }
            }

            return false;
        }

        public function age($k)
        {
            $file = $this->dir . DS . $k . '.eph';

            if (file_exists($file)) {
                $age = filemtime($file);

                if ($age >= time()) {
                    return $age;
                } else {
                    File::delete($file);
                }
            }

            return null;
        }

        public function delete($k)
        {
            $file = $this->dir . DS . $k . '.eph';

            if (file_exists($file)) {
                File::delete($file);

                return true;
            }

            return false;
        }

        public function del($k)
        {
            return $this->delete($k);
        }

        public function remove($k)
        {
            return $this->delete($k);
        }

        public function forget($k)
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

        public function increment($k, $by = 1)
        {
            return $this->incr($k, $by);
        }

        public function decr($k, $by = 1)
        {
            $old = $this->get($k, 0);
            $new = $old - $by;

            $this->set($k, $new);

            return $new;
        }

        public function decrement($k, $by = 1)
        {
            return $this->decr($k, $by);
        }

        public function keys($pattern = '*')
        {
            $keys = glob($this->dir . DS . $pattern . '.eph');

            $collection = [];

            foreach ($keys as $key) {
                $k = str_replace([$this->dir . DS, '.eph'], '', $key);

                $collection[] = $k;
            }

            return $collection;
        }

        public function flush($pattern = '*')
        {
            $keys = glob($this->dir . DS . $pattern . '.eph');

            $affected = 0;

            foreach ($keys as $key) {
                File::delete($key);
                $affected++;
            }

            return $affected;
        }

        public function clean($pattern = '*')
        {
            $keys = glob($this->dir . DS . $pattern . '.eph');

            $affected = 0;

            foreach ($keys as $key) {
                $age = filemtime($key);

                if ($age < time()) {
                    File::delete($key);
                    $affected++;
                }
            }

            return $affected;
        }
    }
