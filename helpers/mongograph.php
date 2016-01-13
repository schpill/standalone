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

    class MongographLib
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

        public function in($data = [])
        {
            if (!$this->isIn()) {
                $keyFrom    = $this->makeKeyLabel($this->from);
                $keyTo      = $this->makeKeyLabel($this->to);

                $row = [
                    'type'      => $this->ns,
                    'from_id'   => $this->from->id,
                    'from'      => $keyFrom,
                    'to_id'     => $this->to->id,
                    'to'        => $keyTo
                ];

                $edgeRow = Model::GraphJoin()->create($row)->save();

                foreach ($data as $k => $v) {
                    $setter = setter($k);
                    $edgeRow->$setter($v);
                }

                $edgeRow->save();

                $out = isset($this->from->out_graph) ? $this->from->out_graph : [];

                if (!is_array($out)) {
                    $out = [];
                }

                $rowData = ['id' => $edgeRow->id, 'type' => $this->ns];

                foreach ($data as $k => $v) {
                    $rowData[$k] = $v;
                }

                $out[] = $rowData;

                $this->from->out_graph = $out;
                $this->from->save();

                $in = isset($this->to->in_graph) ? $this->to->in_graph : [];

                if (!is_array($in)) {
                    $in = [];
                }

                $rowData = ['id' => $edgeRow->id, 'type' => $this->ns];

                foreach ($data as $k => $v) {
                    $rowData[$k] = $v;
                }

                $in[] = $rowData;

                $this->to->in_graph = $in;
                $this->to->save();
            }
        }

        public function unIn()
        {
            if ($this->isIn()) {
                $keyFrom    = $this->makeKeyLabel($this->from);
                $keyTo      = $this->makeKeyLabel($this->to);

                $edgeRow = Model::GraphJoin()
                ->where(['from', '=', $keyFrom])
                ->where(['to', '=', $keyTo])
                ->where(['type', '=', $this->ns])
                ->where(['from_id', '=', (int) $this->from->id])
                ->where(['to_id', '=', (int) $this->to->id])
                ->first(true);

                if ($edgeRow) {
                    $out = isset($this->from->out_graph) ? $this->from->out_graph : [];

                    if (!is_array($out)) {
                        $out = [];
                    }

                    $newOut = [];

                    foreach ($out as $o) {
                        if ($o['id'] != $edgeRow->id) {
                            $newOut[] = $o;
                        }
                    }

                    $this->from->out_graph = $newOut;
                    $this->from->save();

                    $in = isset($this->to->in_graph) ? $this->to->in_graph : [];

                    if (!is_array($in)) {
                        $in = [];
                    }

                    $newIn = [];

                    foreach ($in as $i) {
                        if ($i['id'] != $edgeRow->id) {
                            $newIn[] = $i;
                        }
                    }

                    $this->to->in_graph = $newIn;
                    $this->to->save();

                    $edgeRow->delete();
                }
            }
        }

        public function out($data = [])
        {
            if (!$this->isOut()) {
                $keyFrom    = $this->makeKeyLabel($this->from);
                $keyTo      = $this->makeKeyLabel($this->to);

                $row = [
                    'type'      => $this->ns,
                    'from_id'   => $this->to->id,
                    'from'      => $keyTo,
                    'to_id'     => $this->from->id,
                    'to'        => $keyFrom
                ];

                $edgeRow = Model::GraphJoin()->create($row)->save();

                foreach ($data as $k => $v) {
                    $setter = setter($k);
                    $edgeRow->$setter($v);
                }

                $relation->save();

                $in = isset($this->from->in_graph) ? $this->from->in_graph : [];

                if (!is_array($in)) {
                    $in = [];
                }

                $rowData = ['id' => $edgeRow->id, 'type' => $this->ns];

                foreach ($data as $k => $v) {
                    $rowData[$k] = $v;
                }

                $in[] = $rowData;

                $this->from->in_graph = $in;
                $this->from->save();

                $out = isset($this->to->out_graph) ? $this->to->out_graph : [];

                if (!is_array($out)) {
                    $out = [];
                }

                $rowData = ['id' => $edgeRow->id, 'type' => $this->ns];

                foreach ($data as $k => $v) {
                    $rowData[$k] = $v;
                }

                $out[] = $rowData;

                $this->to->out_graph = $out;
                $this->to->save();
            }
        }

        public function unOut($data = [])
        {
            if ($this->isOut()) {
                $keyFrom    = $this->makeKeyLabel($this->from);
                $keyTo      = $this->makeKeyLabel($this->to);

                $edgeRow = Model::GraphJoin()
                ->where(['from', '=', $keyTo])
                ->where(['to', '=', $keyFrom])
                ->where(['type', '=', $this->ns])
                ->where(['from_id', '=', (int) $this->to->id])
                ->where(['to_id', '=', (int) $this->from->id])
                ->first(true);

                if ($edgeRow) {

                    $in = isset($this->from->in_graph) ? $this->from->in_graph : [];

                    if (!is_array($in)) {
                        $in = [];
                    }

                    $newIn = [];

                    foreach ($in as $i) {
                        if ($i['id'] != $edgeRow->id) {
                            $newIn[] = $i;
                        }
                    }

                    $this->from->in_graph = $newIn;
                    $this->from->save();

                    $out = isset($this->to->out_graph) ? $this->to->out_graph : [];

                    if (!is_array($out)) {
                        $out = [];
                    }

                    $newOut = [];

                    foreach ($out as $o) {
                        if ($o['id'] != $edgeRow->id) {
                            $newOut[] = $o;
                        }
                    }

                    $this->to->out_graph = $newOut;
                    $this->to->save();

                    $edgeRow->delete();
                }
            }
        }

        public function ins()
        {
            $collection = [];
            $keyTo      = $this->makeKeyLabel($this->to);

            $ins = Model::GraphJoin()
            ->where(['type', '=', $this->ns])
            ->where(['to_id', '=', (int) $this->to->id])
            ->where(['to', '=', $keyTo])
            ->cursor();

            foreach ($ins as $in) {
                $val = $in['from'] . '_' . $in['from_id'];

                if (!in_array($val, $collection)) {
                    $collection[] = $val;
                }
            }

            return $collection;
        }

        public function outs()
        {
            $collection = [];
            $keyTo      = $this->makeKeyLabel($this->to);

            $outs = Model::GraphJoin()
            ->where(['type', '=', $this->ns])
            ->where(['from_id', '=', (int) $this->to->id])
            ->where(['from', '=', $keyTo])
            ->cursor();

            foreach ($outs as $out) {
                $val = $out['from'] . '_' . $out['from_id'];

                if (!in_array($val, $collection)) {
                    $collection[] = $val;
                }
            }

            return $collection;
        }

        public function isIn()
        {
            $keyFrom    = $this->makeKeyLabel($this->from);
            $keyTo      = $this->makeKeyLabel($this->to);

            $count = Model::GraphJoin()
            ->where(['type', '=', $this->ns])
            ->where(['from_id', '=', (int) $this->from->id])
            ->where(['from', '=', $keyFrom])
            ->where(['to_id', '=', (int) $this->to->id])
            ->where(['to', '=', $keyTo])
            ->count();

            return $count > 0;
        }

        public function isOut()
        {
            $keyFrom    = $this->makeKeyLabel($this->from);
            $keyTo      = $this->makeKeyLabel($this->to);

            $count = Model::GraphJoin()
            ->where(['type', '=', $this->ns])
            ->where(['from_id', '=', (int) $this->to->id])
            ->where(['from', '=', $keyTo])
            ->where(['to_id', '=', (int) $this->from->id])
            ->where(['to', '=', $keyFrom])
            ->count();

            return $count > 0;
        }

        public function both()
        {
            $collectionIn = $collectionOut = [];

            $keyTo = $this->makeKeyLabel($this->to);

            $ins = Model::GraphJoin()
            ->where(['type', '=', $this->ns])
            ->where(['to_id', '=', (int) $this->to->id])
            ->where(['to', '=', $keyTo])
            ->cursor();

            foreach ($ins as $in) {
                $val = $in['from'] . '_' . $in['from_id'];

                if (!in_array($val, $collectionIn)) {
                    $collectionIn[] = $val;
                }
            }

            $outs = Model::GraphJoin()
            ->where(['type', '=', $this->ns])
            ->where(['from_id', '=', (int) $this->to->id])
            ->where(['from', '=', $keyTo])
            ->cursor();

            foreach ($outs as $out) {
                $val = $out['to'] . '_' . $out['to_id'];

                if (!in_array($val, $collectionOut)) {
                    $collectionOut[] = $val;
                }
            }

            return array_intersect($collectionIn, $collectionOut);
        }

        public function insOuts()
        {
            $collection = [];

            $members = $this->ins();

            foreach ($members as $member) {
                $object = $this->makeObject($member);

                if ($object) {
                    $i = (new self())->to($object);
                    $collection = array_merge($collection, $i->outs());
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
                    $collection = array_merge($collection, $i->ins());
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
                    $collection = array_merge($collection, $i->ins());
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
                    $collection = array_merge($collection, $i->outs());
                }
            }

            return $collection;
        }

        public function getEdges($object)
        {
            $collection = [];

            $keyTo = $this->makeKeyLabel($object);

            $ins = Model::GraphJoin()
            ->where(['type', '=', $this->ns])
            ->where(['to_id', '=', (int) $object->id])
            ->where(['to', '=', $keyTo])
            ->cursor();

            foreach ($ins as $in) {
                $val = $in['from'] . '_' . $in['from_id'];

                if (!in_array($val, $collection)) {
                    $collection[] = $val;
                }
            }

            $outs = Model::GraphJoin()
            ->where(['type', '=', $this->ns])
            ->where(['from_id', '=', (int) $object->id])
            ->where(['from', '=', $keyTo])
            ->cursor();

            foreach ($outs as $out) {
                $val = $out['to'] . '_' . $out['to_id'];

                if (!in_array($val, $collection)) {
                    $collection[] = $val;
                }
            }

            return $collection;
        }

        public function getPath()
        {
            $path           = [];
            $distance       = PHP_INT_MAX;

            if ($this->isIn() || $this->isOut()) {;
                return ['path' => $path, 'distance' => 0];
            } else {
                $keyFrom = $this->makeKey($this->from);
                $edges  = $this->getEdges($this->to);

                if (!in_array($keyFrom, $edges)) {
                    $edgesF  = $this->getEdges($this->from);

                    if (in_array($keyFrom, $edgesF)) {
                        return ['path' => $path, 'distance' => 0];
                    }

                    $intersect = array_intersect($edges, $edgesF);

                    if (!empty($intersect)) {
                        $path = current($intersect);

                        return ['path' => $path, 'distance' => 1];
                    }
                } else {
                    return ['path' => $path, 'distance' => 0];
                }

                list($path, $distance, $found) = $this->recursiveInOrOut(
                    $edges,
                    (new self())->from($this->from),
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

            $path = array_unique($path);

            if ($distance == PHP_INT_MAX) {
                $path       = [];
                $distance   = false;
            } else {
                $distance = count($path);
            }

            return ['path' => array_reverse($path), 'distance' => $distance];
        }

        private function recursiveInOrOut($edges, $instance, $path, $distance = 1, $found = false)
        {
            foreach ($edges as $edge) {
                $tmpObject = $this->makeObject($edge);

                if ($tmpObject) {
                    $instance = $instance->to($tmpObject);
                    $path[] = $edge;

                    if (!$instance->isIn() && !$instance->isOut()) {
                        $keyFrom = $this->makeKey($instance->getFrom());
                        $objectEdges = $this->getEdges($instance->getFrom());

                        if (!in_array($keyFrom, $objectEdges)) {
                            $edgesF  = $this->getEdges($instance->getTo());

                            if (in_array($keyFrom, $edgesF)) {
                                $found = true;
                                break;
                            }

                            $intersect = array_intersect($objectEdges, $edgesF);

                            if (!empty($intersect)) {
                                array_pop($path);
                                $path[] = current($intersect);

                                $found = true;
                                break;
                            }
                        } else {
                            $found = true;
                            break;
                        }

                        $distance++;

                        list($path, $distance, $found) = $this->recursiveInOrOut(
                            $objectEdges,
                            (new self())->from($tmpObject),
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

        public function getGraph()
        {
            $collection = [];

            $ins = Model::GraphJoin()
            ->where(['type', '=', $this->ns])
            ->cursor();

            foreach ($ins as $in) {
                $valFrom = $in['from'] . '_' . $in['from_id'];
                $valTo = $in['to'] . '_' . $in['to_id'];

                if (!isset($collection[$valTo])) {
                    $collection[$valTo] = [];
                }

                if (!isset($collection[$valFrom])) {
                    $collection[$valFrom] = [];
                }

                if (!in_array($valFrom, $collection[$valTo])) $collection[$valTo][] = $valFrom;
                if (!in_array($valTo, $collection[$valFrom])) $collection[$valFrom][] = $valTo;
            }

            return $collection;
        }

        public function inCount()
        {
            $keyTo = $this->makeKeyLabel($this->to);

            return Model::GraphJoin()
            ->where(['type', '=', $this->ns])
            ->where(['to', '=', $keyTo])
            ->where(['to_id', '=', (int) $this->to->id])
            ->count();
        }

        public function outCount()
        {
            $keyTo = $this->makeKeyLabel($this->to);

            return Model::GraphJoin()
            ->where(['type', '=', $this->ns])
            ->where(['from', '=', $keyTo])
            ->where(['from_id', '=', (int) $this->to->id])
            ->count();
        }

        public function isMutual()
        {
            return $this->isIn() && $this->isOut();
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
