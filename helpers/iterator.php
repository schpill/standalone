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

    class IteratorLib implements \Iterator, \Countable
    {
        private $file, $callback, $position = 0, $count = 0, $assoc = false;

        public function __construct(array $array, $callback = null)
        {
            $path = CACHE_PATH . DS . Utils::UUID();

            File::mkdir($path);

            File::put($file, "<?php\nreturn " . var_export($array, 1) . ';');

            $this->assoc    = Arrays::isAssoc($array);
            $this->file     = $file;
            $this->callback = $callback;
            $this->count    = count($array);
        }

        public function __destruct()
        {
            File::delete($this->file);
        }

        public function count()
        {
            return $this->count;
        }

        public function next()
        {
            ++$this->position;
        }

        public function previous()
        {
            --$this->position;
        }

        public function rewind()
        {
            $this->position = 0;
        }

        public function valid()
        {
            return $this->count >= $this->position + 1;
        }

        public function current()
        {
            $tab = include($this->file);

            if ($this->assoc) {
                $value = array_slice($tab, $this->position, $this->position + 1);
            } else {
                $value = isset($tab[$this->position]) ? $tab[$this->position] : null;
            }

            if ($this->callback) {
                $value = call_user_func($this->callback, $value);
            }

            return $value;
        }

        public function fetch()
        {
            $row = $this->current();

            $this->next();

            return $row;
        }

        public function key()
        {
            return $this->assoc ? key(array_slice($tab, $this->position, $this->position + 1)) : $this->position;
        }
    }
