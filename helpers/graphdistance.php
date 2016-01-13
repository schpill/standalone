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

    class GraphdistanceLib
    {
        public $visited             = [];
        public $distance            = [];
        public $previousNode        = [];
        public $startnode           = null;
        public $map                 = [];
        public $infiniteDistance    = 0;
        public $numberOfNodes       = 0;
        public $bestPath            = 0;
        public $matrixWidth         = 0;

        public function __construct(&$ourMap, $infiniteDistance)
        {
            $this->infiniteDistance = $infiniteDistance;
            $this->map = &$ourMap;
            $this->numberOfNodes = count($ourMap);
            $this->bestPath = 0;
        }

        public function findShortestPath($start, $to = null)
        {
            $this->startnode = $start;

            for ($i = 0; $i < $this->numberOfNodes; $i++) {
                if ($i == $this->startnode) {
                    $this->visited[$i] = true;
                    $this->distance[$i] = 0;
                } else {
                    $this->visited[$i] = false;
                    $this->distance[$i] = isset($this->map[$this->startnode][$i])
                        ? $this->map[$this->startnode][$i]
                        : $this->infiniteDistance;
                }

                $this->previousNode[$i] = $this->startnode;
            }

            $maxTries   = $this->numberOfNodes;
            $tries      = 0;

            while (in_array(false,$this->visited,true) && $tries <= $maxTries) {
                $this->bestPath = $this->findBestPath(
                    $this->distance,
                    array_keys(
                        $this->visited,
                        false,
                        true
                    )
                );

                if ($to !== null && $this->bestPath === $to) {
                    break;
                }

                $this->updateDistanceAndPrevious($this->bestPath);
                $this->visited[$this->bestPath] = true;
                $tries++;
            }
        }

        public function findBestPath($ourDistance, $ourNodesLeft)
        {
            $bestPath = $this->infiniteDistance;
            $bestNode = 0;

            for ($i = 0, $m = count($ourNodesLeft); $i < $m; $i++) {
                if ($ourDistance[$ourNodesLeft[$i]] < $bestPath) {
                    $bestPath = $ourDistance[$ourNodesLeft[$i]];
                    $bestNode = $ourNodesLeft[$i];
                }
            }

            return $bestNode;
        }

        public function updateDistanceAndPrevious($obp)
        {
            for ($i = 0; $i < $this->numberOfNodes; $i++) {
                if ((isset($this->map[$obp][$i])) && (!($this->map[$obp][$i] == $this->infiniteDistance) || ($this->map[$obp][$i] == 0 )) && (($this->distance[$obp] + $this->map[$obp][$i]) < $this->distance[$i])) {

                    $this->distance[$i]     = $this->distance[$obp] + $this->map[$obp][$i];
                    $this->previousNode[$i] = $obp;
                }
            }
        }

        public function printMap(&$map)
        {
            $placeholder = ' %' . strlen($this->infiniteDistance) . 'd';
            $foo = '';

            for ($i = 0, $im = count($map); $i < $im; $i++) {
                for ($k = 0, $m = $im; $k < $m; $k++) {
                    $foo .= sprintf($placeholder, isset($map[$i][$k]) ? $map[$i][$k] : $this->infiniteDistance);
                }

                $foo .= "\n";
            }

            return $foo;
        }

        public function getResults($to = null)
        {
            $ourShortestPath = [];
            $foo = '';

            for ($i = 0; $i < $this->numberOfNodes; $i++) {
                if($to !== null && $to !== $i) {
                    continue;
                }

                $ourShortestPath[$i] = [];
                $endNode = null;
                $currNode = $i;
                $ourShortestPath[$i][] = $i;

                while ($endNode === null || $endNode != $this->startnode) {
                    $ourShortestPath[$i][] = $this->previousNode[$currNode];
                    $endNode = $this->previousNode[$currNode];
                    $currNode = $this->previousNode[$currNode];
                }

                $ourShortestPath[$i] = array_reverse($ourShortestPath[$i]);

                if ($to === null || $to === $i) {
                    if ($this->distance[$i] >= $this->infiniteDistance) {
                        $foo .= sprintf("no route from %d to %d. \n", $this->startnode, $i);
                    } else {
                        $foo .= sprintf(
                            '%d => %d = %d [%d]: (%s).' . "\n",
                            $this->startnode,
                            $i,
                            $this->distance[$i],
                            count($ourShortestPath[$i]),
                            implode('-', $ourShortestPath[$i])
                        );
                    }

                    $foo .= str_repeat('-', 20) . "\n";

                    if ($to === $i) {
                        break;
                    }
                }
            }

            return $foo;
        }
    }

    /*
        example =>

        // I is the infinite distance.
        define('I',1000);

        // Size of the matrix
        $matrixWidth = 20;

        // $points is an array in the following format: (node1, node2, distance-between-them)
        $points = array(
            array(0,1,4),
            array(0,2,I),
            array(1,2,5),
            array(1,3,5),
            array(2,3,5),
            array(3,4,5),
            array(4,5,5),
            array(4,5,5),
            array(2,10,30),
            array(2,11,40),
            array(5,19,20),
            array(10,11,20),
            array(12,13,20),
        );

        $ourMap = [];

        // Read in the points and push them into the map

        for ($i = 0, $m = count($points); $i < $m; $i++) {
            $x = $points[$i][0];
            $y = $points[$i][1];
            $c = $points[$i][2];
            $ourMap[$x][$y] = $c;
            $ourMap[$y][$x] = $c;
        }

        // ensure that the distance from a node to itself is always zero
        // Purists may want to edit this bit out.

        for ($i = 0; $i < $matrixWidth; $i++) {
            for ($k = 0; $k < $matrixWidth; $k++) {
                if ($i == $k) {
                    $ourMap[$i][$k] = 0;
                }
            }
        }

        // initialize the algorithm class
        $distanceGraph = lib("distancegraph", [$ourMap, I, $matrixWidth]);

        // $distanceGraph->findShortestPath(0,13); to find only path from field 0 to field 13...
        $distanceGraph->findShortestPath(0);

        // Display the results

        echo '<pre>';
        echo "the map looks like:\n\n";
        echo $distanceGraph->printMap($ourMap);
        echo "\n\nthe shortest paths from point 0:\n";
        echo $distanceGraph->getResults();
        echo '</pre>';

    */
