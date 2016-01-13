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

    class CacheLib
    {
        public function set($key, $value, $expire = 0)
        {
            $expire = $expire > 0 ? $expire + time() : 0;

            if ($expire > 0) {
                $file = $this->getFile('expires.' . $key . '.5');
                $path = str_replace(DS . Arrays::last(explode(DS, $file)), '', $file);

                File::rmdir($path);

                $this->write('expires.' . $key . '.' . $expire, true);
            }

            $this->write($key, $value);

            return $this;
        }

        public function get($key, $default = null)
        {
            $this->clean($key);

            return $this->read($key, $default);
        }

        public function has($key)
        {
            $this->clean($key);

            return sha1(__file__) != $this->read($key, sha1(__file__));
        }

        public function expire($key, $expire = 0)
        {
            if ($expire > 0 && $this->has($key)) {
                $file = $this->getFile('expires.' . $key . '.5');
                $path = str_replace(DS . Arrays::last(explode(DS, $file)), '', $file);

                File::rmdir($path);
                $expire += time();
                $this->write('expires.' . $key . '.' . $expire, true);

                return true;
            }

            return false;
        }

        public function incr($key, $by = 1)
        {
            $value = (int) $this->read($key, 0);
            $value++;

            $this->write($key, $value);

            return $value;
        }

        public function decr($key, $by = 1)
        {
            $value = (int) $this->read($key, 0);
            $value--;

            $this->write($key, $value);

            return $value;
        }

        public function delete($key)
        {
            return $this->clean($key, true);
        }

        public function del($key)
        {
            return $this->clean($key, true);
        }

        private function clean($key, $force = false)
        {
            $now = time();

            $file = $this->getFile('expires.' . $key . '.5');
            $path = str_replace(DS . Arrays::last(explode(DS, $file)), '', $file);

            if (is_dir($path)) {
                $files = glob($path . DS . '*.php');

                if (!empty($files)) {
                    $when = (int) str_replace([$path . DS, '.php'], '', Arrays::first($files));

                    if ($when < $now || true === $force) {
                        File::rmdir($path);
                        $this->remove($key);
                    }
                }
            } else {
                if (true === $force) {
                    $this->remove($key);
                }
            }

            return $this;
        }

        public function write($name, $data = null)
        {
            $file = $this->getFile($name);

            File::delete($file);

            $res = File::put($file, "<?php\nreturn " . var_export($data, 1) . ';');

            return $this;
        }

        public function read($name, $default = null)
        {
            $file = $this->getFile($name);

            if (File::exists($file)) {
                return include($file);
            }

            return $default;
        }

        public function remove($name)
        {
            File::delete($this->getFile($name));

            return $this;
        }

        public function getFile($name)
        {
            defined('APPLICATION_ENV') || define('APPLICATION_ENV', 'production');

            $path = STORAGE_PATH . DS . 'cache_' . APPLICATION_ENV;

            if (!is_dir($path)) {
                File::mkdir($path);
            }

            $tab = $tmp = explode('.', $name);

            $fileName = end($tmp) . '.php';

            array_pop($tab);

            foreach ($tab as $subPath) {
                $path .= DS . $subPath;

                if (!is_dir($path)) {
                    File::mkdir($path);
                }
            }

            return $path . DS . $fileName;
        }
    }
