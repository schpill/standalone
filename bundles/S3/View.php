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

    use Countable;
    use Iterator;
    use SplFixedArray;
    use S3Array;
    use Thin\Inflector;
    use Thin\Save;

    class View
    {
        private $age, $count, $store, $db, $wheres, $cursor, $orders, $selects, $offset, $limit, $joins, $position = 0;

        public function __construct(Db $db, $name)
        {
            $this->db       = $db;
            $this->wheres   = $db->wheres;
            $this->orders   = $db->orders;
            $this->selects  = $db->selects;
            $this->offset   = $db->offset;
            $this->limit    = $db->limit;
            $this->store    = $db->store;
            $this->joins    = $db->joins;
            $this->age      = $db->getAge();
        }

    }
