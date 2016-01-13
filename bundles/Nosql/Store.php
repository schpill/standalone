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

    namespace Nosql;

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
            $dir = Conf::get('dir.nosql.store', STORAGE_PATH . DS . 'nosql');

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
            $has    = Instance::has('nosqlStore', $key);

            if (true === $has) {
                return Instance::get('nosqlStore', $key);
            } else {
                return Instance::make('nosqlStore', $key, new self($collection));
            }
        }

        public function set($key, $value)
        {
            $data = $this->read('data', []);
            $ages = $this->read('ages', []);

            if (!is_array($data)) {
                $data = [];
            }

            if (!is_array($ages)) {
                $ages = [];
            }

            $data[$key] = $value;
            $ages[$key] = time();

            $this->write('data', $data);
            $this->write('ages', $ages);

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
            $data = $this->read('data', []);

            return isAke($data, $key, $default);
        }

        public function delete($key)
        {
            $data = $this->read('data', []);

            if (isset($data[$key])) {
                unset($data[$key]);

                $this->write('data', $data);

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
            $data = $this->read('data', []);

            return isset($data[$key]);
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
            $ages = $this->read('ages', []);

            if (isset($ages[$key])) {
                return $ts ? lib('time')->createFromTimestamp($ages[$key]) : $ages[$key];
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
            $this->set("$hash.$key", $value);

            return $this;
        }

        public function hget($hash, $key, $default = null)
        {
            return $this->get("$hash.$key", $default);
        }

        public function hdelete($hash, $key)
        {
            return $this->delete("$hash.$key");
        }

        public function hdel($hash, $key)
        {
            return $this->hdelete($hash, $key);
        }

        public function hhas($hash, $key)
        {
            return $this->has("$hash.$key");
        }

        public function hage($hash, $key, $ts = false)
        {
            return $this->age("$hash.$key", $ts);
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

            $glob = $this->read('data', []);
            $ages = $this->read('ages', []);

            foreach ($glob as $row => $val) {
                if (fnmatch('*expire.*', $row) || !fnmatch($pattern, $row)) {
                    continue;
                }

                $expire = isAke($glob, 'expire.' . $row, false);

                if ($expire) {
                    $expiration = (int) $expire;

                    if (time() > $expiration) {
                        unset($glob[$row]);
                        unset($ages[$row]);
                        unset($data['expire.' . $row]);
                        $this->write('data', $glob);
                        $this->write('ages', $data);
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
            $glob = $this->read('data', []);
            $ages = $this->read('ages', []);

            foreach ($glob as $row => $val) {
                if (!fnmatch('*expire.*', $row)) {
                    continue;
                }

                $expiration = (int) $val;

                if (time() > $expiration) {
                    $key = str_replace('expire.', '', $row);
                    unset($glob[$key]);
                    unset($ages[$key]);
                    unset($data[$row]);
                    $this->write('data', $glob);
                    $this->write('ages', $data);
                }
            }
        }

        public function hkeys($hash, $pattern = '*')
        {
            return $this->keys("$hash.$pattern");
        }

        public function write($file, $data, $try = 1)
        {
            $file = $this->dir . DS . $file . '.php';

            $data = serialize($data);

            $fp = fopen($file, 'w');

            if (!flock($fp, LOCK_EX)) {
                if ($try < 100) {
                    usleep(50000);

                    return $this->write($file, $data, $try++);
                } else {
                    throw new Exception("The file '$file' can not be locked.");
                }
            }

            $result = fwrite($fp, $data);

            flock($fp, LOCK_UN);

            fclose($fp);

            umask(0000);

            chmod($file, 0777);

            return $result !== false;
        }

        public function read($file, $default = null, $mode = 'rb', $try = 1)
        {
            $file = $this->dir . DS . $file . '.php';

            if (File::exists($file)) {
                $fp   = fopen($file, 'rb');

                if (!flock($fp, LOCK_EX)) {
                    if ($try < 100) {
                        usleep(50000);

                        return $this->read($file, $default, $mode, $try++);
                    } else {
                        throw new Exception("The file '$file' can not be locked.");
                    }
                }

                $data = unserialize(File::read($file));

                flock($fp, LOCK_UN);

                fclose($fp);

                return $data;
            }

            return $default;
        }
    }
