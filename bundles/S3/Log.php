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

    use Thin\Instance;
    use Thin\Arrays;

    class Log
    {
        private $db, $ns;

        public function __construct($ns)
        {
            $this->ns = $ns;
            $this->db = Db::instance('core', 'log');
        }

        public function write($status, $message)
        {
            $this->db->create([
                'ns'        => $this->ns,
                'date'      => date('Y-m-d H:i:s'),
                'status'    => strtoupper($status),
                'message'   => $message
            ])->save();

            return $this;
        }

        public function exception($e)
        {
            return $this->write('error', $this->exceptionLine($e));
        }

        protected function exceptionLine($e)
        {
            return $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
        }

        public function __call($method, $parameters)
        {
            return $this->write($method, Arrays::first($parameters));
        }

        public static function instance($ns)
        {
            $key    = sha1($ns);
            $has    = Instance::has('S3Log', $key);

            if (true === $has) {
                return Instance::get('S3Log', $key);
            } else {
                return Instance::make('S3Log', $key, new self($ns));
            }
        }
    }
