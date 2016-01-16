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

    use Iterator;
    use IteratorAggregate;

    class GraphCore implements IteratorAggregate
    {
        /**
         * The nodes/vertices in the graph. Should be a numeric sequence of items (no string keys, no gaps).
         * @var array|null
         */
        protected $data;

        /**
         * The edges in the graph, in $to_idx => [$from_idx1, $from_idx2, ...] format
         * @var array
         */
        protected $dag;

        public function __construct($data = null)
        {
            $data = $data ? array_values($data) : [];

            $this->data = $data;
            $this->dag = array_fill_keys(array_keys($data), []);
        }

        /**
         * Add another node/vertex
         * @param $item anything - The item to add to the graph
         */
        public function additem($item)
        {
            $this->data[] = $item;
            $this->dag[] = [];
        }

        /**
         * Add an edge from one vertex to another.
         *
         * When passing actual nodes (as opposed to indexes), uses array_search with strict = true to find
         *
         * @param $from integer|any The index in $data of the node/vertex, or the node/vertex itself, that the edge
         *                          goes from
         * @param $to integer|any - The index in $data of the node/vertex, or the node/vertex itself, that the edge goes to
         */
        public function addedge($from, $to)
        {
            $i = is_numeric($from)  ? $from : array_search($from, $this->data, true);
            $j = is_numeric($to)    ? $to   : array_search($to, $this->data, true);

            if ($i === false) throw new Exception("Couldnt find 'from' item in data when adding edge to Graph");
            if ($j === false) throw new Exception("Couldnt find 'to' item in data when adding edge to Graph");

            if (!isset($this->dag[$j])) $this->dag[$j] = [];

            $this->dag[$j][] = $i;
        }

        /**
         * Sort graph so that each node (a) comes before any nodes (b) where an edge exists from a to b
         * @return array - The nodes
         * @throws Exception - If the graph is cyclic (and so can't be sorted)
         */
        public function sort()
        {
            $data   = $this->data;
            $dag    = $this->dag;
            $sorted = [];

            while (true) {
                $withedges  = array_filter($dag, 'count');
                $starts     = array_diff_key($dag, $withedges);

                if (empty($starts)) break;

                foreach ($starts as $i => $foo) $sorted[] = $data[$i];

                foreach ($withedges as $j => $deps) {
                    $withedges[$j] = array_diff($withedges[$j], array_keys($starts));
                }

                $dag = $withedges;
            }

            if ($dag) {
                $remainder = new GraphCore($data);
                $remainder->dag = $dag;

                throw new GraphCoreCyclicException("DAG has cyclic requirements", $remainder);
            }

            return $sorted;
        }

        public function getIterator()
        {
            return new GraphCoreIterator($this->data, $this->dag);
        }
    }

    /**
     * Exception thrown when the {@link GraphCore} class is unable to resolve sorting the DAG due to cyclic dependencies.
     *
     * @package framework
     * @subpackage manifest
     */
    class GraphCoreCyclicException extends Exception
    {
        public $dag;

        /**
         * @param string $message The Exception message
         * @param GraphCore $dag The remainder of the Directed Acyclic Graph (DAG) after the last successful sort
         */
        public function __construct($message, $dag)
        {
            $this->dag = $dag;
            parent::__construct($message);
        }
    }

    /**
     * @package core
     * @subpackage manifest
     */
    class GraphCoreIterator implements Iterator
    {
        protected $data;
        protected $dag;

        protected $dagkeys;
        protected $i;

        public function __construct($data, $dag)
        {
            $this->data = $data;
            $this->dag = $dag;
            $this->rewind();
        }

        public function key()
        {
            return $this->i;
        }

        public function current()
        {
            $res = [];

            $res['from'] = $this->data[$this->i];

            $res['to'] = [];

            foreach ($this->dag[$this->i] as $to) {
                $res['to'][] = $this->data[$to];
            }

            return $res;
        }

        public function next()
        {
            $this->i = array_shift($this->dagkeys);
        }

        public function rewind()
        {
            $this->dagkeys = array_keys($this->dag);
            $this->next();
        }

        public function valid()
        {
            return $this->i !== null;
        }
    }
