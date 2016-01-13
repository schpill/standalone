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

    /**
     * Class Tree
     *
     * This class represents abstract tree model for inheritance
     *
     */
    class TreeLib
    {
        private $tree = [];

        public function isRoot(array $row)
        {
            $parent = isAke($row, 'parent', false);

            if (!$parent) {
                return true;
            }

            return false;
        }

        public function add(array $row, $parent = null)
        {
            $id = isAke($row, 'id', sha1(serialize($row)));
            $row['id'] = $id;

            if (is_null($parent)) {
                $this->tree[$id] = $row;
            } else {
                if (is_array($parent)) {
                    $this->del($parent, false);

                    if (!isset($parent['children'])) {
                        $parent['children'] = [];
                    }

                    $parent['children'][] = $row;

                    $parent_id = isAke($parent, 'id', sha1(serialize($parent)));

                    $parent['id'] = $parent_id;
                    $this->tree[$parent_id] = $parent;

                    $row['parent'] = $parent;
                    $id = isAke($row, 'id', sha1(serialize($row)));
                    $row['id'] = $id;

                    $this->tree[$id] = $row;
                }
            }

            return !is_array($parent) ? $row : [$row, $parent];
        }

        public function del(array $row, $delChildren = true)
        {
            $children   = isAke($row, 'children', false);
            $row_id     = isAke($row, 'id', false);

            if ($children && $delChildren) {
                foreach ($children as $child) {
                    $id = isAke($child, 'id', false);

                    if ($id) {
                        unset($this->tree[$id]);
                    }
                }
            }

            if ($row_id) {
                unset($this->tree[$row_id]);
            }

            return $this;
        }

        public function hasChildren(array $row)
        {
            $children = isAke($row, 'children', false);

            return is_array($children) && !empty($children);
        }

        public function hasParent(array $row)
        {
            $parent = isAke($row, 'parent', false);

            return is_array($parent) && !empty($parent);
        }

        public function getParent(array $row)
        {
            return isAke($row, 'parent', null);
        }

        public function getChildren(array $row)
        {
            return isAke($row, 'children', []);
        }

        public function getFamily(array $row)
        {
            $collection = [];

            while ($row = isAke($row, 'parent', false)) {
                $collection[] = $row;
            }

            return array_reverse($collection);
        }
    }
