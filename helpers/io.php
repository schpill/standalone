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

    use ElephantIO\Client, ElephantIO\Engine\SocketIO\Version1X;

    class IoLib
    {
        private $client;

        public function __construct($server = null)
        {
            $server         = is_null($server) ? 'http://localhost:1337' : $server;
            $this->client   = with(new Client(new Version1X($server)))->initialize();
        }

        public function getClient()
        {
            return $this->client;
        }

        public function close()
        {
            $this->client->close();
        }

        public function __invoke()
        {
            return $this->client;
        }

        public function __call($m, $a)
        {
            return call_user_func_array([$this->client, $m], $a);
        }
    }
