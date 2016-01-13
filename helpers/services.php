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

    class ServicesLib
    {
        public function search($word, $coords, $distance = 5, $limit = 25, $offset = 0)
        {
            $collection = $tmp = $idEtabs = [];

            $nb = $incr = 0;

            $services = rdb('geo', 'service')->select('id')
            ->where(['family', 'LIKE', '%' . $word . '%'])
            ->where(['code', 'LIKE', '%' . $word . '%'], 'OR')
            ->where(['label', 'LIKE', '%' . $word . '%'], 'OR')
            ->exec(true);

            foreach ($services as $service) {
                $sEtabs = $service->pivots(rdb('geo', 'etablissement')->model())->exec();

                foreach ($sEtabs as $sEtab) {
                    $idEtabs[] = $sEtab['etablissement_id'];
                }
            }

            $idEtabs = array_unique($idEtabs);

            $db = Model::Location();

            $odm = $db->getOdm();

            $coll = $odm->selectCollection($db->collection);
            $coll->ensureIndex([
                'value'             => '2d',
                'object_motor'      => 1,
                'object_database'   => 1,
                'object_table'      => 1
            ]);

            $filter = [
                "value" => [
                    '$within' => [
                        '$center'=> [
                            [
                                floatval($coords['lng']),
                                floatval($coords['lat'])
                            ],
                            floatval($distance / 111.12)
                        ]
                    ]
                ],
                'object_motor'     => 'dbredis',
                'object_database'  => 'geo',
                'object_table'     => 'etablissement'
            ];

            $results = $coll->find($filter);

            foreach ($results as $result) {
                if (Arrays::in($result['object_id'], $idEtabs)) {
                    $etab = rdb('geo', 'etablissement')->find($result['object_id']);
                    $distances = distanceKmMiles($coords['lng'], $coords['lat'], $etab->lng, $etab->lat);
                    $distance = $distances['km'];

                    $item = $etab->assoc();
                    $item['distance'] = $distance;
                    $collection[] = $item;
                }
            }

            $collection = $this->orderBy($collection, 'distance');

            if ($limit == 0) {
                return $collection;
            } else {
                return array_slice($collection, $offset, $limit);
            }
        }

        public function orderBy($tab, $fieldOrder, $orderDirection = 'ASC')
        {
            $sortFunc = function($key, $direction) {
                return function ($a, $b) use ($key, $direction) {
                    if ('ASC' == $direction) {
                        return $a[$key] > $b[$key];
                    } else {
                        return $a[$key] < $b[$key];
                    }
                };
            };

            if (Arrays::is($fieldOrder) && !Arrays::is($orderDirection)) {
                $t = array();

                foreach ($fieldOrder as $tmpField) {
                    array_push($t, $orderDirection);
                }

                $orderDirection = $t;
            }

            if (!Arrays::is($fieldOrder) && Arrays::is($orderDirection)) {
                $orderDirection = Arrays::first($orderDirection);
            }

            if (Arrays::is($fieldOrder) && Arrays::is($orderDirection)) {
                for ($i = 0 ; $i < count($fieldOrder) ; $i++) {
                    usort($tab, $sortFunc($fieldOrder[$i], $orderDirection[$i]));
                }
            } else {
                usort($tab, $sortFunc($fieldOrder, $orderDirection));
            }

            return $tab;
        }
    }
