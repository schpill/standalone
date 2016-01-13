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

    namespace Live;

    use SQLite3;
    use SplFixedArray;
    use Thin\Instance;
    use Thin\File;

    class Store
    {
        private $lite;

        public function __construct($ns = 'core.cache')
        {
            if (!is_dir(STORAGE_PATH . DS . 'kit')) {
                File::mkdir(STORAGE_PATH . DS . 'kit');
            }

            $file = STORAGE_PATH . DS . 'kit' . DS . $ns . '.db';

            $this->lite = new SQLite3($file);

            $q = "CREATE TABLE IF NOT EXISTS content (k VARCHAR PRIMARY KEY, v, ts);";
            $this->raw($q);
        }

        public function raw($q)
        {
            return $this->lite->exec($q);
        }

        public function getLite()
        {
            return $this->lite;
        }

        public static function instance($collection)
        {
            $key    = sha1($collection);
            $has    = Instance::has('liveStore', $key);

            if (true === $has) {
                return Instance::get('liveStore', $key);
            } else {
                return Instance::make('liveStore', $key, new self($collection));
            }
        }

        public function set($key, $value)
        {
            $q = "DELETE FROM content WHERE k = '" . SQLite3::escapeString($key) . "'";
            $this->raw($q);

            $q = "INSERT INTO content (k, v, ts) VALUES ('" . SQLite3::escapeString($key) . "', '" . SQLite3::escapeString(serialize($value)) . "', '" . time() . "')";
            $this->raw($q);

            return $this;
        }

        public function get($key, $default = null)
        {
            $q = "SELECT v FROM content WHERE k = '" . SQLite3::escapeString($key) . "'";
            $res = $this->lite->query($q);

            while ($row = $res->fetchArray()) {
                return unserialize($row['v']);
            }

            return $default;
        }

        public function delete($key)
        {
            $q = "DELETE FROM content WHERE k = '" . SQLite3::escapeString($key) . "'";
            $this->raw($q);

            return true;
        }

        public function del($key)
        {
            return $this->delete($key);
        }

        public function has($key)
        {
            $q = "SELECT k FROM content WHERE k = '" . SQLite3::escapeString($key) . "'";
            $res = $this->lite->query($q);

            while ($row = $res->fetchArray()) {
                return true;
            }

            return false;
        }

        public function age($key)
        {
            $q = "SELECT ts FROM content WHERE k = '" . SQLite3::escapeString($key) . "'";
            $res = $this->lite->query($q);

            while ($row = $res->fetchArray()) {
                return (int) $row['ts'];
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
           return $this->set("$hash.$key", $value);
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

        public function hage($hash, $key)
        {
            return $this->age("$hash.$key");
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

            $q = "SELECT k FROM content WHERE k LIKE '" . SQLite3::escapeString(str_replace('*', '%', $pattern)) . "'";

            $res = $this->lite->query($q);

            while ($row = $res->fetchArray()) {
                $coll[] = $row['k'];
            }

            return SplFixedArray::fromArray($coll);
        }

        public function hkeys($hash)
        {
            $coll = [];

            $q = "SELECT k FROM content WHERE k LIKE '" . SQLite3::escapeString($hash) . ".%'";

            $res = $this->lite->query($q);

            while ($row = $res->fetchArray()) {
                $coll[] = str_replace($hash . '.', '', $row['k']);
            }

            return SplFixedArray::fromArray($coll);
        }
    }
