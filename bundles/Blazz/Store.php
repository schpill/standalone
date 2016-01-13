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

    namespace Blazz;

    use SplFixedArray;
    use Thin\Instance;
    use Thin\File;
    use Thin\Arrays;
    use Thin\Config as Conf;
    use ArrayAccess as AA;

    class Store implements AA
    {
        private $dir;
        public $transactions = 0;

        public function __construct($ns = 'core.cache')
        {
            $dir = Conf::get('dir.blazz.store', STORAGE_PATH . DS . 'blazz');

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
            $last = Arrays::last(explode(DS, $this->dir));
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
            $has    = Instance::has('rawStore', $key);

            if (true === $has) {
                return Instance::get('rawStore', $key);
            } else {
                return Instance::make('rawStore', $key, new self($collection));
            }
        }

        public function set($key, $value)
        {
            $file = $this->getFile($key);

            File::delete($file);

            File::put($file, serialize($value));

            return $this;
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

        public function age($key)
        {
            $file = $this->getFile($key);

            if (File::exists($file)) {
                return filemtime($file);
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
            $old = $this->get($key, 1);
            $new = $old - $by;

            $this->set($key, $new);

            return $new;
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

        public function hget($hash, $key, $default = null)
        {
            $file = $this->getHashFile($hash, $key);

            if (File::exists($file)) {
                return unserialize(File::read($file));
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

        public function hage($hash, $key)
        {
            $file = $this->getHashFile($hash, $key);

            if (File::exists($file)) {
                return filemtime($file);
            }

            return false;
        }

        public function getHage($hash, $key)
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
            $coll = [];

            $glob = glob($this->dir . DS . $pattern, GLOB_NOSORT);

            foreach ($glob as $row) {
                if (fnmatch('*expire.*', $row)) {
                    continue;
                }

                $row = str_replace([$this->dir . DS, '.raw'], '', $row);

                $expire = $this->getFile('expire.' . $row);

                if (File::exists($expire)) {
                    $expiration = (int) unserialize(File::read($expire));

                    if (time() > $expiration) {
                        File::delete($this->getFile($row));
                        File::delete($expire);
                    } else {
                        $coll[] = $row;
                    }
                } else {
                    $coll[] = $row;
                }
            }

            return SplFixedArray::fromArray($coll);
        }

        public function clean()
        {
            $glob = glob($this->dir . DS . 'expire.', GLOB_NOSORT);

            foreach ($glob as $row) {
                $row = str_replace([$this->dir . DS, '.raw'], '', $row);
                list($d, $key) = explode('expire.', $row, 2);
                $expire = $this->getFile($row);

                $expiration = (int) unserialize(File::read($expire));

                if (time() > $expiration) {
                    $file = $this->getFile($key);
                    File::delete($expire);
                    File::delete($file);
                }
            }
        }

        public function hkeys($hash, $pattern = '*')
        {
            $coll = [];

            $glob = glob($this->dir . DS . $hash . DS . $pattern, GLOB_NOSORT);

            foreach ($glob as $row) {
                if (fnmatch('*expire.*', $row)) {
                    continue;
                }

                $row = str_replace([$this->dir . DS . $hash .DS, '.raw'], '', $row);
                $coll[] = $row;
            }

            return SplFixedArray::fromArray($coll);
        }

        public function getFile($file)
        {
            return $this->dir . DS . $file . '.raw';
        }

        public function getHashFile($hash, $file)
        {
            if (!is_dir($this->dir . DS . $hash)) {
                File::mkdir($this->dir . DS . $hash);
            }

            return $this->dir . DS . $hash . DS . $file . '.raw';
        }
    }
