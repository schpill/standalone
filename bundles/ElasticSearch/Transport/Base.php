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

    namespace ElasticSearch\Transport;

    use Thin\Arrays;

    abstract class Base
    {
        /**
         * What host to connect to for server
         * @var string
         */
        protected $host = "";

        /**
         * Port to connect on
         * @var int
         */
        protected $port = 9200;

        /**
         * ElasticSearch index
         * @var string
         */
        protected $index;

        /**
         * ElasticSearch document type
         * @var string
         */
        protected $type;

        /**
         * Default constructor, just set host and port
         * @param string $host
         * @param int $port
         */
        public function __construct($host, $port)
        {
            $this->host = $host;
            $this->port = $port;
        }

        /**
         * Method for indexing a new document
         *
         * @param array|object $document
         * @param mixed $id
         * @param array $options
         */
        abstract public function index($document, $id = false, array $options = []);

        /**
         * Perform a request against the given path/method/payload combination
         * Example:
         * $es->request('/_status');
         *
         * @param string|array $path
         * @param string $method
         * @param array|bool $payload
         * @return
         */
        abstract public function request($path, $method = "GET", $payload = false);

        /**
         * Delete a document by its id
         * @param mixed $id
         */
        abstract public function delete($id = false);

        /**
         * Perform a search based on query
         * @param array|string $query
         */
        abstract public function search($query);

        /**
         * Search
         *
         * @return array
         * @param mixed $query String or array to use as criteria for delete
         * @param array $options Parameters to pass to delete action
         * @throws \Elasticsearch\Exception
         */
        public function deleteByQuery($query, array $options = [])
        {
            throw new \Elasticsearch\Exception(__FUNCTION__ . ' not implemented for ' . __CLASS__);
        }

        /**
         * Set what index to act against
         * @param string $index
         */
        public function setIndex($index)
        {
            $this->index = $index;
        }

        /**
         * Set what document types to act against
         * @param string $type
         */
        public function setType($type)
        {
            $this->type = $type;
        }

        /**
         * Build a callable url
         *
         * @return string
         * @param array|bool $path
         * @param array $options Query parameter options to pass
         */
        protected function buildUrl($path = false, array $options = [])
        {
            $isAbsolute = (Arrays::is($path) ? $path[0][0] : $path[0]) === '/';
            $url        = $isAbsolute ? '' : "/" . $this->index;

            if ($path && Arrays::i($path) && count($path) > 0) $url .= "/" . implode("/", array_filter($path));

            if (substr($url, -1) == "/") $url = substr($url, 0, -1);

            if (count($options) > 0) $url .= "?" . http_build_query($options, '', '&');

            return $url;
        }
    }
