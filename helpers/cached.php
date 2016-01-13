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

    class CachedLib
    {
        function make($k, callable $c, $maxAge = null, $args = [])
        {
            $dir = '/home/php/storage/cache';

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $hash = sha1($k);

            $f = substr($hash, 0, 2);
            $s = substr($hash, 2, 2);

            $dir .= DS . $f;

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $dir .= DS . $s;

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $file = $dir . DS . $k;

            if (file_exists($file)) {
                if (is_null($maxAge)) {
                    return unserialize(File::read($file));
                } else {
                    if (filemtime($file) >= time()) {
                        return unserialize(File::read($file));
                    } else {
                        File::delete($file);
                    }
                }
            }

            $data = call_user_func_array($c, $args);

            File::put($file, serialize($data));

            if (!is_null($maxAge)) {
                if ($maxAge < 1444000000) {
                    $maxAge = ($maxAge * 60) + time();
                }

                touch($file, $maxAge);
            }

            return $data;
        }

        public function age($k, callable $c, $maxAge = null, $args = [])
        {
            $dir = '/homehome/php/storage/aged';

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $hash = sha1($k);

            $f = substr($hash, 0, 2);
            $s = substr($hash, 2, 2);

            $dir .= DS . $f;

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $dir .= DS . $s;

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $file = $dir . DS . $k;

            if (file_exists($file)) {
                if (is_null($maxAge)) {
                    return unserialize(File::read($file));
                } else {
                    if (filemtime($file) >= $maxAge) {
                        return unserialize(File::read($file));
                    } else {
                        File::delete($file);
                    }
                }
            }

            $data = call_user_func_array($c, $args);

            File::put($file, serialize($data));

            if (!is_null($maxAge)) {
                if ($maxAge < 1000000000) {
                    $maxAge = ($maxAge * 60) + time();
                }

                touch($file, $maxAge);
            }

            return $data;
        }

        public function url($url, $age = null)
        {
            return $this->make('url.' . sha1($url), function () use ($url) {
                return lib('geo')->dwn($url);
            }, $age);
        }

        public function set($k, $v, $age = null)
        {
            $k = '_cache_.' . $k;

            return $this->make('k.' . sha1($k), function () use ($v) {
                return $v;
            }, $age);
        }

        public function forget($k)
        {
            $k = '_cache_.' . $k;

            $dir = '/home/php/storage/cache';

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $hash = sha1($k);

            $f = substr($hash, 0, 2);
            $s = substr($hash, 2, 2);

            $dir .= DS . $f;

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $dir .= DS . $s;

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $file = $dir . DS . $k;

            File::delete($file);

            return !file_exists($file);
        }

        public function has($k)
        {
            $k = '_cache_.' . $k;
            $dir = '/home/php/storage/cache';

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $hash = sha1($k);

            $f = substr($hash, 0, 2);
            $s = substr($hash, 2, 2);

            $dir .= DS . $f;

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $dir .= DS . $s;

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $file = $dir . DS . $k;

            return file_exists($file);
        }

        public function sessionSet($k, $v = null)
        {
            $key = sha1(session_id() . $k);

            redis()->set($key, $v);
            redis()->expire($key, 84600);
        }

        public function sessionGet($k, $d = null)
        {
            $key = sha1(session_id() . $k);
            $value = redis()->get($key);

            return $value ? $value : $d;
        }
    }
