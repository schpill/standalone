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

    use SplFixedArray;
    use Thin\Instance;
    use Thin\File;
    use Thin\Arrays;
    use Thin\Config as Conf;
    use ArrayAccess as AA;

    use function Thin\s3;

    class Store implements AA
    {
        private $dir;

        public function __construct($ns = 'core.cache')
        {
            $this->dir = str_replace('.', '/', $ns);
        }

        public function getDir()
        {
            return $this->dir;
        }

        public static function instance($collection)
        {
            $key    = sha1($collection);
            $has    = Instance::has('S3Store', $key);

            if (true === $has) {
                return Instance::get('S3Store', $key);
            } else {
                return Instance::make('S3Store', $key, new self($collection));
            }
        }

        public function set($key, $value)
        {
            s3()->put($this->dir . '/' . $key, serialize($value));

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

        public function get($key, $default = null, $cb = null)
        {
            try {
                $val = s3()->get($this->dir . '/' . $key);

                try {
                    $expire = s3()->get($this->dir . '/expire.' . $key);
                    $expiration = (int) unserialize($expire);

                    if (time() > $expiration) {
                        s3()->delete($this->dir . '/' . $key);
                        s3()->delete($this->dir . '/expire.' . $key);

                        if ($cb) {
                            $cb();
                        }

                        return $default;
                    }
                } catch (\Exception $e) {
                    return unserialize($val->read());
                }
            } catch (\Exception $e) {
                return $default;
            }
        }

        public function getOr($key, callable $c, $e = null)
        {
            if ($this->has($k)) {
                return $this->get($k);
            }

            $res = $c();

            if (is_null($e)) {
                $this->set($key, $res);
            } else {
                $this->set($key, $res)->set('expire.' . $key, time() + $e);
            }

            return $res;
        }

        public function delete($key)
        {
            try {
                $val = s3()->get($this->dir . '/' . $key);
                s3()->delete($this->dir . '/' . $key);

                try {
                    $expire = s3()->get($this->dir . '/expire.' . $key);

                    s3()->delete($this->dir . '/expire.' . $key);

                    return true;
                } catch (\Exception $e) {
                    return true;
                }
            } catch (\Exception $e) {
                return false;
            }
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

        public function has($key, $cb = null)
        {
            try {
                $val = s3()->get($this->dir . '/' . $key);

                try {
                    $expire = s3()->get($this->dir . '/expire.' . $key);
                    $expiration = (int) unserialize($expire);

                    if (time() > $expiration) {
                        s3()->delete($this->dir . '/' . $key);
                        s3()->delete($this->dir . '/expire.' . $key);

                        if ($cb) {
                            $cb();
                        }

                        return false;
                    } else {
                        return true;
                    }
                } catch (\Exception $e) {
                    return true;
                }
            } catch (\Exception $e) {
                return false;
            }
        }

        public function readAndDelete($key)
        {
            if ($this->has($key)) {
                $value = $this->get($key);

                $this->delete($key);

                return $value;
            }

            return null;
        }

        public function rename($keyFrom, $keyTo)
        {
            $value = $this->readAndDelete($keyFrom);

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

        public function age($key, $ts = false)
        {
            try {
                return s3()->getTimestamp($this->dir . '/' . $key);
            } catch (\Exception $e) {
                return false;
            }
        }

        public function aged()
        {
            try {
                return s3()->getTimestamp($this->dir);
            } catch (\Exception $e) {
                return false;
            }
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

            if (s3()->has($file)) {
                s3()->delete($file);
            }

            s3()->put($file, serialize($value));

            return $this;
        }

        public function hget($hash, $key, $default = null)
        {
            $file = $this->getHashFile($hash, $key);

            if (s3()->has($file)) {
                return unserialize(s3()->get($file)->read());
            }

            return $default;
        }

        public function hdelete($hash, $key, $cb = null)
        {
            $file = $this->getHashFile($hash, $key);

            if (s3()->has($file)) {
                s3()->delete($file, $cb);

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

            return s3()->has($file);
        }

        public function hage($hash, $key)
        {
            $file = $this->getHashFile($hash, $key);

            if (s3()->has($file)) {
                return s3()->timestamp($file);
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

            $glob = s3()->listContents();

            foreach ($glob as $row) {
                $path = isAke($row, 'path', null);
                $type = isAke($row, 'type', 'dir');

                if (fnmatch('*expire.*', $row) || !strlen($path)) {
                    continue;
                }

                $row = str_replace([$this->dir . DS, '.S3'], '', $row);

                $expire = $this->getFile('expire.' . $row);

                if (s3()->has($expire)) {
                    $expiration = (int) unserialize(s3()->get($expire));

                    if (time() > $expiration) {
                        s3()->delete($this->getFile($row));
                        s3()->delete($expire);
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
                $row = str_replace([$this->dir . DS, '.S3'], '', $row);
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

            $glob =  s3()->listContents();

            foreach ($glob as $row) {
                $path = isAke($row, 'filename', null);
                $type = isAke($row, 'type', 'dir');

                if (fnmatch('*expire.*', $path) || $type != 'file') {
                    continue;
                }

                $coll[] = $path;
            }

            return SplFixedArray::fromArray($coll);
        }

        public function getFile($file)
        {
            return $this->dir . DS . $file . '.S3';
        }

        public function getHashFile($hash, $file)
        {
            return $this->dir . DS . $hash . DS . $file . '.S3';
        }
    }
