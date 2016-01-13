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

    class Node
    {
        private $db_i, $db_o, $name, $inner, $outer, $id, $node;

        public function __construct($node, $inner = 'membership', $outer = 'members')
        {
            $this->db_i     = call_user_func_array([$node->db(), 'instance'], ['node', $inner]);
            $this->db_o     = call_user_func_array([$node->db(), 'instance'], ['node', $outer]);
            $this->name     = $node->db()->db . '.' . $node->db()->table;
            $this->inner    = $inner;
            $this->outer    = $outer;
            $this->id       = $node->id;
            $this->node     = $node;
        }

        public function __toString()
        {
            return $this->name . '.' . $this->id;
        }

        public function delete()
        {
            $inners = $this->db_i->where(['inner_id', '=', $this->name . '.' . $this->id])->models();
            $outers = $this->db_o->where(['outer_id', '=', $this->name . '.' . $this->id])->models();

            foreach ($inners as $inner) {
                $inner->delete();
            }

            foreach ($outers as $outer) {
                $outer->delete();
            }
        }

        public function __call($m, $a)
        {
            if ($m == $this->inner) {
                $i = new Edge($this, $this->db_i, $this->db_o, 'inner');
            } elseif ($m == $this->outer) {
                $i = new Edge($this, $this->db_i, $this->db_o, 'outer');
            } else {
                throw new Exception("Method $m not allowed.");
            }

            return $i;
        }

        public function inner()
        {
            return $this->inner;
        }

        public function outer()
        {
            return $this->outer;
        }

        public function node()
        {
            return $this->node;
        }
    }

