<?php
    /**
     * Thin is a swift Framework for PHP 5.4+
     *
     * @package    Thin
     * @version    1.0
     * @author     Gerald Plusquellec
     * @license    BSD License
     * @copyright  1996 - 2016 Gerald Plusquellec
     * @link       http://github.com/schpill/thin
     */

    namespace Thin;

    use MongoId;

    class MongoLib
    {
        private $collection;

        protected $operators = [
            '=', '<', '>', '<=', '>=', '<>', '!=',
            'like', 'not like', 'between', 'ilike',
            '&', '|', '^', '<<', '>>',
            'rlike', 'regexp', 'not regexp',
            'exists', 'type', 'mod', 'where', 'all', 'size', 'regex', 'text', 'slice', 'elemmatch',
            'geowithin', 'geointersects', 'near', 'nearsphere', 'geometry',
            'maxdistance', 'center', 'centersphere', 'box', 'polygon', 'uniquedocs',
        ];

        protected $conversion = [
            '='  => '=',
            '!=' => '$ne',
            '<>' => '$ne',
            '<'  => '$lt',
            '<=' => '$lte',
            '>'  => '$gt',
            '>=' => '$gte',
        ];

        public function __construct($collection = 'db')
        {
            $this->collection = $collection;
        }

        public function em($collection = null)
        {
            $collection = is_null($collection) ? $this->collection : $collection;

            return dbm($collection);
        }

        public function find($id)
        {
            $id = is_object($id) ? $id->{'$id'} : $id;

            try {
                $object = $this->em()
                ->findOne(
                    array(
                        "_id" => new MongoId($id)
                    )
                );

                return $object;
            } catch (\Exception $e) {
                return null;
            }
        }
    }
