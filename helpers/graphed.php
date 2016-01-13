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

    class GraphedLib
    {
        private $from, $to, $fromLabel, $toLabel, $ns;

        public function __construct($ns = 'belong')
        {
            $this->ns = $ns;
        }

        public function setNs($ns)
        {
            $this->ns = $ns;

            return $this;
        }

        public function from($object)
        {
            $this->from = $object;

            return $this;
        }

        public function to($object)
        {
            $this->to = $object;

            return $this;
        }

        public function fromLabel($object)
        {
            $this->fromLabel = $object;

            return $this;
        }

        public function toLabel($object)
        {
            $this->toLabel = $object;

            return $this;
        }

        public function getGraph()
        {
            $collection = ['in' => [], 'out' => []];

            $keys = redis()->keys("g:*:i:{$this->ns}");

            foreach ($keys as $key) {
                if (fnmatch('*:d:*', $key) || fnmatch('*:data:*', $key)) {
                    continue;
                }

                $to = Utils::cut('g:', ':i:', $key);
                $froms = redis()->smembers($key);
                $graph = [$to => $froms];
                $collection['in'][] = $graph;
            }

            $keys = redis()->keys("g:*:o:{$this->ns}");

            foreach ($keys as $key) {
                if (fnmatch('*:d:*', $key) || fnmatch('*:data:*', $key)) {
                    continue;
                }

                $from = Utils::cut('g:', ':o:', $key);
                $tos = redis()->smembers($key);
                $graph = [$from => $tos];
                $collection['out'][] = $graph;
            }

            return $collection;
        }

        public function in($data = [])
        {
            $keyFrom    = $this->makeKey($this->from);
            $keyTo      = $this->makeKey($this->to);

            if (!$this->isIn()) {
                redis()->sadd("g:{$keyTo}:i:{$this->ns}", $keyFrom);
                redis()->sadd("g:{$keyFrom}:o:{$this->ns}", $keyTo);
            }

            if (!empty($data)) {
                redis()->hset("g:d:i:{$this->ns}", "{$keyTo}:{$keyFrom}", serialize($data));
                redis()->hset("g:d:o:{$this->ns}", "{$keyFrom}:{$keyTo}", serialize($data));
            }
        }

        public function out($data = [])
        {
            $keyFrom    = $this->makeKey($this->from);
            $keyTo      = $this->makeKey($this->to);

            if (!$this->isOut()) {
                redis()->sadd("g:{$keyFrom}:i:{$this->ns}", $keyTo);
                redis()->sadd("g:{$keyTo}:o:{$this->ns}", $keyFrom);
            }

            if (!empty($data)) {
                redis()->hset("g:d:i:{$this->ns}", "{$keyFrom}:{$keyTo}", serialize($data));
                redis()->hset("g:d:o:{$this->ns}", "{$keyTo}:{$keyFrom}", serialize($data));
            }
        }

        public function unIn()
        {
            if ($this->isIn()) {
                $keyFrom    = $this->makeKey($this->from);
                $keyTo      = $this->makeKey($this->to);

                redis()->srem("g:{$keyTo}:i:{$this->ns}", $keyFrom);
                redis()->srem("g:{$keyFrom}:o:{$this->ns}", $keyTo);

                redis()->hdel("g:d:i:{$this->ns}", "{$keyTo}:{$keyFrom}");
                redis()->hdel("g:d:o:{$this->ns}", "{$keyFrom}:{$keyTo}");
            }
        }

        public function unOut()
        {
            if ($this->isOut()) {
                $keyFrom    = $this->makeKey($this->from);
                $keyTo      = $this->makeKey($this->to);

                redis()->srem("g:{$keyFrom}:i:{$this->ns}", $keyTo);
                redis()->srem("g:{$keyTo}:o:{$this->ns}", $keyFrom);

                redis()->hdel("g:d:i:{$this->ns}", "{$keyFrom}:{$keyTo}");
                redis()->hdel("g:d:o:{$this->ns}", "{$keyTo}:{$keyFrom}");
            }
        }

        public function ins()
        {
            $keyTo = $this->makeKey($this->to);

            return redis()->smembers("g:{$keyTo}:i:{$this->ns}");
        }

        public function inLabelCount()
        {
            return count($this->insLabel());
        }

        public function outLabelCount()
        {
            return count($this->outsLabel());
        }

        public function insLabel()
        {
            $collection = [];

            $keyTo = $this->makeKeyLabel($this->toLabel);

            $keys = redis()->keys("g:{$this->keyTo}*:i:{$this->ns}");

            foreach ($keys as $key) {
                $collection = array_merge($collection, redis()->smembers($key));
            }

            return array_unique($collection);
        }

        public function outsLabel()
        {
            $collection = [];

            $keyTo = $this->makeKeyLabel($this->toLabel);

            $keys = redis()->keys("g:{$this->keyTo}*:o:{$this->ns}");

            foreach ($keys as $key) {
                $collection = array_merge($collection, redis()->smembers($key));
            }

            return array_unique($collection);
        }

        public function outs()
        {
            $keyTo = $this->makeKey($this->to);

            return redis()->smembers("g:{$keyTo}:o:{$this->ns}");
        }

        public function isIn()
        {
            $keyFrom    = $this->makeKey($this->from);
            $keyTo      = $this->makeKey($this->to);

            return redis()->sismember("g:{$keyTo}:i:{$this->ns}", $keyFrom);
        }

        public function isOut()
        {
            $keyFrom    = $this->makeKey($this->from);
            $keyTo      = $this->makeKey($this->to);

            return redis()->sismember("g:{$keyTo}:o:{$this->ns}", $keyFrom);
        }

        public function isMutual()
        {
            return $this->isIn() && $this->isOut();
        }

        public function inCount()
        {
            $keyTo = $this->makeKey($this->to);

            return redis()->scard("g:{$keyTo}:i:{$this->ns}");
        }

        public function outCount()
        {
            $keyTo = $this->makeKey($this->to);

            return redis()->scard("g:{$keyTo}:o:{$this->ns}");
        }

        public function commonIn($objects = [])
        {
            $objects[] = $this->to;

            $keys = [];

            foreach ($objects as $object) {
                $key    = $this->makeKey($object);
                $keys[] = "g:{$key}:i:{$this->ns}";
            }

            return call_user_func_array(
                [redis(), 'sinter'],
                $keys
            );
        }

        public function commonOut($objects = [])
        {
            $objects[] = $this->to;

            $keys = [];

            foreach ($objects as $object) {
                $key    = $this->makeKey($object);
                $keys[] = "g:{$key}:o:{$this->ns}";
            }

            return call_user_func_array(
                [redis(), 'sinter'],
                $keys
            );
        }

        public function both()
        {
            $keyTo = $this->makeKey($this->to);

            return redis()->sinter("g:{$keyTo}:o:{$this->ns}", "g:{$keyTo}:i:{$this->ns}");
        }

        public function insOuts()
        {
            $collection = [];

            $members = $this->ins();

            foreach ($members as $member) {
                $object = $this->makeObject($member);

                if ($object) {
                    $i = (new self())->to($object);
                    $collection[] = $i->outs();
                }
            }

            return $collection;
        }

        public function insIns()
        {
            $collection = [];

            $members = $this->ins();

            foreach ($members as $member) {
                $object = $this->makeObject($member);

                if ($object) {
                    $i = (new self())->to($object);
                    $collection[] = $i->ins();
                }
            }

            return $collection;
        }

        public function outsIns()
        {
            $collection = [];

            $members = $this->outs();

            foreach ($members as $member) {
                $object = $this->makeObject($member);

                if ($object) {
                    $i = (new self())->to($object);
                    $collection[] = $i->ins();
                }
            }

            return $collection;
        }

        public function outsOuts()
        {
            $collection = [];

            $members = $this->outs();

            foreach ($members as $member) {
                $object = $this->makeObject($member);

                if ($object) {
                    $i = (new self())->to($object);
                    $collection[] = $i->outs();
                }
            }

            return $collection;
        }

        public function getEdges($key)
        {
            $collection = [];

            $keys = redis()->keys('g:*:i:' . $this->ns);

            foreach ($keys as $k) {
                if (fnmatch('*:d:*', $k) || fnmatch('*:data:*', $k)) {
                    continue;
                }

                $m = redis()->smembers($k);

                if (in_array($key, $m) && !in_array(str_replace(['g:', ':i:' . $this->ns], '', $k), $collection)) {
                    $collection[] = str_replace(['g:', ':i:' . $this->ns], '', $k);
                }
            }

            $keys = redis()->keys('g:*:o:' . $this->ns);

            foreach ($keys as $k) {
                if (fnmatch('*:d:*', $k) || fnmatch('*:data:*', $k)) {
                    continue;
                }

                $m = redis()->smembers($k);

                if (in_array($key, $m) && !in_array(str_replace(['g:', ':o:' . $this->ns], '', $k), $collection)) {
                    $collection[] = str_replace(['g:', ':o:' . $this->ns], '', $k);
                }
            }

            return $collection;
        }

        public function hasEdge()
        {
            return $this->inCount() > 0 || $this->outCount() > 0;
        }

        public function hasEdgeIn()
        {
            return $this->inCount() > 0;
        }

        public function hasEdgeOut()
        {
            return $this->outCount() > 0;
        }

        public function shortestPath()
        {
            $path           = [];
            $distance       = PHP_INT_MAX;

            if ($this->isIn() || $this->isOut()) {;
                return ['path' => $path, 'distance' => 0];
            } else {
                $keyTo = $this->makeKey($this->to);
                $edges = $this->getEdges($keyTo);

                list($path, $distance, $found) = $this->recursiveInOrOut(
                    $edges,
                    (new self())->to($this->from),
                    $path
                );

                if (!$found) {
                    $path       = [];
                    $distance   = false;
                }
            }

            if (empty($path)) {
                $distance = false;
            }

            if ($distance == PHP_INT_MAX) {
                $path       = [];
                $distance   = false;
            }

            return ['path' => array_reverse($path), 'distance' => $distance];
        }

        private function recursiveInOrOut($edges, $instance, $path, $distance = 1, $found = false)
        {
            foreach ($edges as $edge) {
                $tmpObject = $this->makeObject($edge);

                if ($tmpObject) {
                    $instance = $instance->from($tmpObject);
                    $path[] = $edge;

                    if (!$instance->isIn() && !$instance->isOut()) {
                        $keyTo = $this->makeKey($instance->getTo());
                        $objectEdges = $this->getEdges($keyTo);
                        $distance++;


                        list($path, $distance, $found) = $this->recursiveInOrOut(
                            $objectEdges,
                            (new self())->to($tmpObject),
                            $path,
                            $distance
                        );

                        if (!$found) {
                            array_pop($path);
                            $distance--;
                        }
                    } else {
                        $found = true;
                        break;
                    }
                }
            }

            return [$path, $distance, $found];
        }

        public function getTo()
        {
            return $this->to;
        }

        public function getFrom()
        {
            return $this->from;
        }

        public function getToLabel()
        {
            return $this->toLabel;
        }

        public function getFromLabel()
        {
            return $this->fromLabel;
        }

        public function getNs()
        {
            return $this->ns;
        }

        public function makeKey($object)
        {
            return $object->db()->db . '_' . $object->db()->table . '_' . $object->id;
        }

        public function makeKeyLabel($object)
        {
            return $object->db()->db . '_' . $object->db()->table;
        }

        public function makeObject($key)
        {
            list($db, $table, $id) = explode('_', $key, 3);

            return rdb($db, $table)->find($id);
        }

        public function makeModel($key)
        {
            list($db, $table) = explode('_', $key, 2);

            return rdb($db, $table)->model();
        }
    }
