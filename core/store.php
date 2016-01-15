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

    use SplFixedArray;
    use ArrayAccess as AA;

    class StoreCore implements AA
    {
        private $dir;
        public $transactions = 0;

        public function __construct($ns = 'core.cache')
        {
            $dir = STORAGE_PATH . DS . 'store';

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $this->dir = $dir . DS . $ns;

            if (!is_dir($this->dir)) {
                File::mkdir($this->dir);
            }
        }

        public function beginTransaction()
        {
            $last   = Arrays::last(explode(DS, $this->dir));
            $target = str_replace(DS . $last, DS . 'copy.' . $last, $this->dir);

            File::cpdir($this->dir, $target);

            $this->dir = $target;

            return $this;
        }

        public function commit()
        {
            $target = str_replace('copy.', '', $this->dir);

            File::rmdir($target);
            File::cpdir($this->dir, $target);
            File::rmdir($this->dir);

            $this->dir = $target;
        }

        public function rollback()
        {
            $target = str_replace('copy.', '', $this->dir);

            File::rmdir($this->dir);

            $this->dir = $target;
        }

        public function getDir()
        {
            return $this->dir;
        }

        public static function instance($collection)
        {
            $key    = sha1($collection);
            $has    = Instance::has('coreStore', $key);

            if (true === $has) {
                return Instance::get('coreStore', $key);
            } else {
                return Instance::make('coreStore', $key, new self($collection));
            }
        }

        public function set($key, $value)
        {
            $file = $this->getFile($key);

            File::delete($file);

            File::put($file, serialize($value));

            return $this;
        }

        public function setnx($key, $value)
        {
            if (!$this->has($key)) {
                $this->set($key, $value);

                return true;
            }

            return false;
        }

        public function __set($key, $value)
        {
            return $this->set($key, $value);
        }

        public function offsetSet($key, $value)
        {
            return $this->set($key, $value);
        }

        public function expire($key, $ttl = 60)
        {
            return $this->set('expire.' . $key, time() + $ttl);
        }

        public function setExpire($key, $value, $ttl = 60)
        {
            return $this->set($key, $value)->set('expire.' . $key, time() + $ttl);
        }

        public function setExp($key, $value, $ttl = 60)
        {
            return $this->setExpire($key, $value, $ttl);
        }

        public function __get($key)
        {
            return $this->get($key, null);
        }

        public function offsetGet($key)
        {
            return $this->get($key, null);
        }

        public function get($key, $default = null)
        {
            $file   = $this->getFile($key);

            if (File::exists($file)) {
                $expire = $this->getFile('expire.' . $key);

                if (File::exists($expire)) {
                    $expiration = (int) unserialize(File::read($expire));

                    if (time() > $expiration) {
                        File::delete($file);
                        File::delete($expire);

                        return $default;
                    }
                }

                return unserialize(File::read($file));
            }

            return $default;
        }

        public function getOr($k, callable $c)
        {
            if ($this->has($k)) {
                return $this->get($k);
            }

            $res = $c();

            $this->set($k, $res);

            return $res;
        }

        public function watch($k, callable $exists = null, callable $notExists = null)
        {
            if ($this->has($k)) {
                if (is_callable($exists)) {
                    return $exists($this->get($k));
                }
            } else {
                if (is_callable($notExists)) {
                    return $notExists();
                }
            }

            return false;
        }

        public function delete($key)
        {
            $file = $this->getFile($key);

            if (File::exists($file)) {
                $expire = $this->getFile('expire.' . $key);

                File::delete($file);

                if (File::exists($expire)) {
                    File::delete($expire);
                }

                return true;
            }

            return false;
        }

        public function __unset($key)
        {
            return $this->delete($key);
        }

        public function offsetUnset($key)
        {
            return $this->delete($key);
        }

        public function del($key)
        {
            return $this->delete($key);
        }

        public function __isset($key)
        {
            return $this->has($key);
        }

        public function offsetExists($key)
        {
            return $this->has($key);
        }

        public function has($key)
        {
            $file = $this->getFile($key);

            if (File::exists($file)) {
                $expire = $this->getFile('expire.' . $key);

                if (File::exists($expire)) {
                    $expiration = (int) unserialize(File::read($expire));

                    if (time() > $expiration) {
                        File::delete($file);
                        File::delete($expire);

                        return false;
                    }
                }
            }

            return File::exists($file);
        }

        public function readAndDelete($key, $default = null)
        {
            if ($this->has($key)) {
                $value = $this->get($key);

                $this->delete($key);

                return $value;
            }

            return $default;
        }

        public function rename($keyFrom, $keyTo, $default = null)
        {
            $value = $this->readAndDelete($keyFrom, $default);

            return $this->set($keyTo, $value);
        }

        public function copy($keyFrom, $keyTo)
        {
            return $this->set($keyTo, $this->get($keyFrom));
        }

        public function getSize($key)
        {
            return strlen($this->get($key));
        }

        public function length($key)
        {
            return strlen($this->get($key));
        }

        public function age($key, $ts = false)
        {
            $file = $this->getFile($key);

            if (File::exists($file)) {
                return $ts ? lib('time')->createFromTimestamp(filemtime($file)) : filemtime($file);
            }

            return false;
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
            $old = $this->get($key, 0);
            $new = $old - $by;

            $this->set($key, $new);

            return $new;
        }

        public function increment($key, $by = 1)
        {
            return $this->incr($key, $by);
        }

        public function decrement($key, $by = 1)
        {
            return $this->decr($key, $by);
        }

        public function hset($hash, $key, $value)
        {
            $file = $this->getHashFile($hash, $key);

            if (File::exists($file)) {
                File::delete($file);
            }

            File::put($file, serialize($value));

            return $this;
        }

        public function hsetnx($hash, $key, $value)
        {
            if (!$this->hexists($hash, $key)) {
                $this->hset($hash, $key, $value);

                return true;
            }

            return false;
        }

        public function hget($hash, $key, $default = null)
        {
            $file = $this->getHashFile($hash, $key);

            if (File::exists($file)) {
                return unserialize(File::read($file));
            }

            return $default;
        }

        public function hstrlen($hash, $key)
        {
            if ($value = $this->hget($hash, $key)) {
                return strlen($value);
            }

            return 0;
        }

        public function hgetOr($hash, $k, callable $c)
        {
            if ($this->hhas($hash, $k)) {
                return $this->hget($hash, $k);
            }

            $res = $c();

            $this->hset($hash, $k, $res);

            return $res;
        }

        public function hwatch($hash, $k, callable $exists = null, callable $notExists = null)
        {
            if ($this->hhas($hash, $k)) {
                if (is_callable($exists)) {
                    return $exists($this->hget($hash, $k));
                }
            } else {
                if (is_callable($notExists)) {
                    return $notExists();
                }
            }

            return false;
        }

        public function hReadAndDelete($hash, $key, $default = null)
        {
            if ($this->hhas($hash, $key)) {
                $value = $this->hget($hash, $key);

                $this->hdelete($hash, $key);

                return $value;
            }

            return $default;
        }

        public function hdelete($hash, $key)
        {
            $file = $this->getHashFile($hash, $key);

            if (File::exists($file)) {
                File::delete($file);

                return true;
            }

            return false;
        }

        public function hdel($hash, $key)
        {
            return $this->hdelete($hash, $key);
        }

        public function hhas($hash, $key)
        {
            $file = $this->getHashFile($hash, $key);

            return File::exists($file);
        }

        public function hexists($hash, $key)
        {
            return $this->hhas($hash, $key);
        }

        public function hage($hash, $key)
        {
            $file = $this->getHashFile($hash, $key);

            if (File::exists($file)) {
                return filemtime($file);
            }

            return false;
        }

        public function gethage($hash, $key)
        {
            return $age = $this->hage($hash, $key) ? date('d/m/Y H:i:s', $age) : false;
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

        public function keys($pattern = '*')
        {
            $glob = glob($this->dir . DS . $pattern, GLOB_NOSORT);

            foreach ($glob as $row) {
                if (fnmatch('*expire.*', $row)) {
                    continue;
                }

                $row = str_replace([$this->dir . DS, '.store'], '', $row);

                $expire = $this->getFile('expire.' . $row);

                if (File::exists($expire)) {
                    $expiration = (int) unserialize(File::read($expire));

                    if (time() > $expiration) {
                        File::delete($this->getFile($row));
                        File::delete($expire);
                    } else {
                        yield $row;
                    }
                } else {
                    yield $row;
                }
            }
        }

        public function clean()
        {
            $glob = glob($this->dir . DS . 'expire.', GLOB_NOSORT);

            foreach ($glob as $row) {
                $row            = str_replace([$this->dir . DS, '.store'], '', $row);
                list($d, $key)  = explode('expire.', $row, 2);
                $expire         = $this->getFile($row);

                $expiration = (int) unserialize(File::read($expire));

                if (time() > $expiration) {
                    $file = $this->getFile($key);

                    File::delete($expire);
                    File::delete($file);
                }
            }
        }

        public function hgetall($hash)
        {
            $glob = glob($this->dir . DS . 'hash.' . $hash . DS . '*.store', GLOB_NOSORT);

            foreach ($glob as $row) {
                if (fnmatch('*expire.*', $row)) {
                    continue;
                }

                $key = str_replace('.store', '', Arrays::last(explode(DS, $row)));

                yield $key;
                yield unserialize(File::read($row));
            }
        }

        public function hvals($hash)
        {
            $glob = glob($this->dir . DS . 'hash.' . $hash . DS . '*.store', GLOB_NOSORT);

            foreach ($glob as $row) {
                if (fnmatch('*expire.*', $row)) {
                    continue;
                }

                yield unserialize(File::read($row));
            }
        }

        public function hlen($hash)
        {
            $i = 0;
            $glob = glob($this->dir . DS . 'hash.' . $hash . DS . '*.store', GLOB_NOSORT);

            foreach ($glob as $row) {
                if (fnmatch('*expire.*', $row)) {
                    continue;
                }

                $i++;
            }

            return $i;
        }

        public function hremove($hash)
        {
            $glob = glob($this->dir . DS . 'hash.' . $hash . DS . '*.store', GLOB_NOSORT);

            foreach ($glob as $row) {
                File::delete($row);
            }

            return true;
        }

        public function hkeys($hash)
        {
            $glob = glob($this->dir . DS . 'hash.' . $hash . DS . '*.store', GLOB_NOSORT);

            foreach ($glob as $row) {
                if (fnmatch('*expire.*', $row)) {
                    continue;
                }

                $key = str_replace('.store', '', Arrays::last(explode(DS, $row)));

                yield $key;
            }
        }

        public function getFile($file)
        {
            return $this->dir . DS . $file . '.store';
        }

        public function getHashFile($hash, $file)
        {
            $hash = 'hash.' . $hash;

            if (!is_dir($this->dir . DS . $hash)) {
                File::mkdir($this->dir . DS . $hash);
            }

            return $this->dir . DS . $hash . DS . $file . '.store';
        }

        public static function cleanCache($dir = null)
        {
            $dir = is_null($dir) ? STORAGE_PATH . DS . 'store' : $dir;

            $dirs = glob($dir . DS . '*', GLOB_NOSORT);

            foreach ($dirs as $dir) {
                if (is_dir($dir)) {
                    if (fnmatch('*/sessme.*', $dir) || fnmatch('*/me.*', $dir) || fnmatch('*/temporary.*', $dir)) {
                        $age = filemtime($dir);

                        $diff = time() - $age;

                        Cli::show("$diff", 'INFO');

                        if ($diff > 3600 || fnmatch('*/temporary.*', $dir)) {
                            File::rmdir($dir);
                            Cli::show("delete $dir", 'COMMENT');
                        }
                    }
                }
            }
        }

        public function sadd($key, $value)
        {
            $tab = $this->get($key, []);
            $tab[] = $value;

            return $this->set($key, $tab);
        }

        public function scard($key)
        {
            $tab = $this->get($key, []);

            return count($tab);
        }

        public function sinter()
        {
            $tab = [];

            foreach (func_get_args() as $key) {
                $tab = array_intersect($tab, $this->get($key, []));
            }

            return $tab;
        }

        public function sunion()
        {
            $tab = [];

            foreach (func_get_args() as $key) {
                $tab = array_merge($tab, $this->get($key, []));
            }

            return $tab;
        }

        public function sinterstore()
        {
            $args = func_get_args();

            $destination = array_shift($args);

            $tab = [];

            foreach ($args as $key) {
                $tab = array_intersect($tab, $this->get($key, []));
            }

            return $this->set($destination, $tab);
        }

        public function sunionstore()
        {
            $args = func_get_args();

            $destination = array_shift($args);

            $tab = [];

            foreach ($args as $key) {
                $tab = array_merge($tab, $this->get($key, []));
            }

            return $this->set($destination, $tab);
        }

        public function sismember($hash, $key)
        {
            return in_array($key, $this->get($hash, []));
        }

        public function smembers($key)
        {
            return $this->get($key, []);
        }

        public function srem($hash, $key)
        {
            $tab = $this->get($hash, []);

            $new = [];

            $exists = false;

            foreach ($tab as $row) {
                if ($row != $key) {
                    $new[] = $row;
                } else {
                    $exists = true;
                }
            }

            if ($exists) {
                $this->set($hash, $new);

                return true;
            }

            return false;
        }

        public function smove($from, $to, $key)
        {
            if ($this->sismember($from, $key)) {
                $this->srem($from, $key);

                if (!$this->sismember($to, $key)) {
                    $this->sadd($to, $key);
                }

                return true;
            }

            return false;
        }
    }
