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

    namespace Node;

    use Thin\Exception;

    class Edge
    {
        private $node, $dbi, $dbo, $type;

        public function __construct(Node $node, $dbi, $dbo, $type)
        {
            $this->node     = $node->node();
            $this->dbi      = $dbi->reset();
            $this->dbo      = $dbo->reset();
            $this->type     = $type;
            $this->inner    = $node->inner();
            $this->outer    = $node->outer();
        }

        public function add($node)
        {
            $row = $this->dbi->create([
                'inner_id'      => $this->node->db()->db . '.' . $this->node->db()->table . '.' . $this->node->id,
                'outer_id'      => $node->db()->db . '.' . $node->db()->table . '.' . $node->id,
                'outer'         => $node->toArray(),
                'inner'         => $this->node->toArray()
            ])->save();

            $row = $this->dbo->create([
                'outer_id'  => $this->node->db()->db . '.' . $this->node->db()->table . '.' . $this->node->id,
                'inner_id'  => $node->db()->db . '.' . $node->db()->table . '.' . $node->id,
                'inner'     => $node->toArray(),
                'outer'     => $this->node->toArray()
            ])->save();

            return $this;
        }

        public function delete($node)
        {
            $row = $this->dbi
            ->where(['inner_id', '=', $this->node->db()->db . '.' . $this->node->db()->table . '.' . $this->node->id])
            ->where(['outer_id', '=', $node->db()->db . '.' . $node->db()->table . '.' . $node->id])
            ->first(true);

            if ($row) {
                $row->delete();
            }

            $row = $this->dbo
            ->where(['inner_id', '=', $node->db()->db . '.' . $node->db()->table . '.' . $node->id])
            ->where(['outer_id', '=', $this->node->db()->db . '.' . $this->node->db()->table . '.' . $this->node->id])
            ->first(true);

            if ($row) {
                $row->delete();
            }

            return false;
        }

        public function has($node)
        {
            if ($this->type == 'inner') {
                $row = $this->dbi
                ->where(['inner_id', '=', $this->node->db()->db . '.' . $this->node->db()->table . '.' . $this->node->id])
                ->where(['outer_id', '=', $node->db()->db . '.' . $node->db()->table . '.' . $node->id])
                ->first(true);

                if ($row) {
                    return true;
                }
            } elseif ($this->type == 'outer') {
                $row = $this->dbo
                ->where(['inner_id', '=', $node->db()->db . '.' . $node->db()->table . '.' . $node->id])
                ->where(['outer_id', '=', $this->node->db()->db . '.' . $this->node->db()->table . '.' . $this->node->id])
                ->first(true);

                if ($row) {
                    return true;
                }
            }

            return false;
        }

        public function all()
        {
            if ($this->type == 'inner') {
                return $this->dbi->where(['inner_id', '=', $this->node->db()->db . '.' . $this->node->db()->table . '.' . $this->node->id])->cursor();
            } elseif ($this->type == 'outer') {
                return $this->dbo->where(['outer_id', '=', $this->node->db()->db . '.' . $this->node->db()->table . '.' . $this->node->id])->cursor();
            }
        }

        public function without($node)
        {
            if ($this->type == 'inner') {
                return $this->dbi
                ->where(['inner_id', '=', $this->node->db()->db . '.' . $this->node->db()->table . '.' . $this->node->id])
                ->where(['outer_id', '!=', $node->db()->db . '.' . $node->db()->table . '.' . $node->id])
                ->cursor();
            } elseif ($this->type == 'outer') {
                return $this->dbo
                ->where(['outer_id', '=', $this->node->db()->db . '.' . $this->node->db()->table . '.' . $this->node->id])
                ->where(['inner_id', '!=', $node->db()->db . '.' . $node->db()->table . '.' . $node->id])
                ->cursor();
            }
        }

        public function union($node)
        {
            if ($this->type == 'inner') {
                return $this->dbi
                ->where(['inner_id', '=', $this->node->db()->db . '.' . $this->node->db()->table . '.' . $this->node->id])
                ->where(['inner_id', '=', $node->db()->db . '.' . $node->db()->table . '.' . $node->id], 'OR')
                ->cursor();
            } elseif ($this->type == 'outer') {
                return $this->dbo
                ->where(['outer_id', '=', $this->node->db()->db . '.' . $this->node->db()->table . '.' . $this->node->id])
                ->where(['outer_id', '=', $node->db()->db . '.' . $node->db()->table . '.' . $node->id], 'OR')
                ->cursor();
            }
        }

        public function intersect($node)
        {
            if ($this->type == 'inner') {
                $tab1 = $tab2 = [];

                $cursor = $this->dbi
                ->where(['inner_id', '=', $this->node->db()->db . '.' . $this->node->db()->table . '.' . $this->node->id])
                ->cursor();

                foreach ($cursor as $row) {
                    $tab1[] = $row['outer_id'];
                }

                $cursor = $this->dbi
                ->where(['inner_id', '=', $node->db()->db . '.' . $node->db()->table . '.' . $node->id])
                ->cursor();

                foreach ($cursor as $row) {
                    $tab2[] = $row['outer_id'];
                }

                $ids = array_intersect($tab1, $tab2);

                return $this->dbi->where(['outer_id', 'IN', implode(',', $ids)])->cursor();

            } elseif ($this->type == 'outer') {
                $tab1 = $tab2 = [];

                $cursor = $this->dbo
                ->where(['outer_id', '=', $this->node->db()->db . '.' . $this->node->db()->table . '.' . $this->node->id])
                ->cursor();

                foreach ($cursor as $row) {
                    $tab1[] = $row['inner_id'];
                }

                $cursor = $this->dbo
                ->where(['outer_id', '=', $node->db()->db . '.' . $node->db()->table . '.' . $node->id])
                ->cursor();

                foreach ($cursor as $row) {
                    $tab2[] = $row['inner_id'];
                }

                $ids = array_intersect($tab1, $tab2);

                return $this->dbo->where(['inner_id', 'IN', implode(',', $ids)])->cursor();
            }
        }

        public function __call($m, $a)
        {
            if ($m == $this->inner) {
                $all = $this->all();
                $ids = [];

                foreach ($all as $row) {
                    $ids[] = $row['outer_id'];
                }

                return $this->dbi->where(['inner_id', 'IN', implode(',', $ids)])->cursor();

            } elseif ($m == $this->outer) {
                $all = $this->all();
                $ids = [];

                foreach ($all as $row) {
                    $ids[] = $row['inner_id'];
                }

                return $this->dbo->where(['outer_id', 'IN', implode(',', $ids)])->cursor();
            } else {
                throw new Exception("Method $m not allowed.");
            }
        }
    }
