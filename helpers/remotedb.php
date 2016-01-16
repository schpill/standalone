<?php
    /**
     * Thin is a swift Framework for PHP 5.4+
     *
     * @package    Thin
     * @version    1.0
     * @author     Gerald Plusquellec
     * @license    BSD License
     * @copyright  1996 - 2013 Gerald Plusquellec
     * @link       http://github.com/schpill/thin
     */
    namespace Thin;

    class FRemotedbLib
    {
        private $file, $writeCb;

        public function __construct($file, $write = null)
        {
            $this->file     = $file;
            $this->writeCb  = $write;

            Now::set(
                'remote.collection.' . $this->db . '.' . $this->table,
                lib(
                    'sessy',
                    [
                        $this->db,
                        $this->table,
                        unserialize(
                            $this->read(
                                $this->file
                            )
                        )
                    ]
                )
            );
        }

        public function collection()
        {
            return Now::get(
                'remote.collection.' . $this->db . '.' . $this->table
            );
        }

        public function read($url)
        {
            $data = @file_get_contents($url);

            if (!strlen($data)) {
                return serialize([]);
            }

            return $data;
        }

        public function __destruct()
        {
            if ($this->writeCb) {
                if (is_callable($this->writeCb)) {
                    $write = $this->writeCb;

                    $write(
                        $this->file,
                        serialize(
                            $this->collection()->collection()
                        )
                    );
                }
            }
        }

        public function age()
        {
            return filemtime($this->file);
        }

        public function __call($m, $a)
        {
            return call_user_func_array(
                [$this->collection(), $m],
                $a
            );
        }
    }
