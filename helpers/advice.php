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

    class AdviceLib
    {
        public function add($positive, $negative, $table, $table_id, $account_id)
        {
            $oo = Model::Offreout()->find((int) $table_id);

            if ($oo) {
                $advice = Model::Advice()->firstOrcreate([
                    'table'         => $table,
                    'table_id'      => $table_id,
                    'account_id'    => $account_id,
                    'reseller_id'   => (int) $oo->reseller_id,
                ]);

                $advice->positive = $positive;
                $advice->negative = $negative;

                $advice->save();

                return $advice->id;
            }

            return false;
        }

        public function addRate($rate, $adviceratecategory_id, $advice_id)
        {
            $advice = Model::Advicerate()->firstOrcreate([
                'advice_id'             => $advice_id,
                'adviceratecategory_id' => $adviceratecategory_id
            ]);

            $advice->rate = $rate;

            $advice->save();

            return $advice->id;
        }

        public function getCategories($table, $reseller_id)
        {
            $isResto = Model::Restodata()->where(['reseller_id', '=', (int) $reseller_id])->cursor()->count() > 0;

            if ($isResto) {
                return Model::Adviceratecategory()
                ->select('name')
                ->where(['table', '=', $table])
                ->where(['segment_id', '=', 413])
                ->order('order')
                ->exec();
            } else {
                return [];
            }
        }

        public function getCustomQuestions($reseller_id)
        {
            return Model::Customquestion()
            ->select('name,required')
            ->where(['reseller_id', '=', (int) $reseller_id])
            ->order('order')
            ->exec();
        }

        public function customAnswer($customquestion_id, $account_id, $rate)
        {
            $answer = Model::Customanswer()->firstOrcreate([
                'customquestion_id' => $customquestion_id,
                'account_id'        => $account_id
            ]);

            $answer->rate = $rate;

            $answer->save();

            return $answer->id;
        }

        public function offreout($reseller_id)
        {
            $collection = [];

            $advices = Model::Advice()
            ->where(['table', '=', 'offreout'])
            ->where(['reseller_id', '=', (int) $reseller_id])
            ->where(['created_at', '>', strtotime(Config::get('advice.maxperiod', '-3 month'))])
            ->cursor();

            foreach ($advices as $advice) {
                $account = Model::Account()->find((int) $advice['account_id']);

                if ($account) {
                    $row                = [];
                    $row['id']          = $advice['id'];
                    $row['positive']    = $advice['positive'];
                    $row['negative']    = $advice['negative'];
                    $row['account_id']  = $account->id;
                    $row['firstname']   = $account->firstname;
                    $rates              = Model::Advicerate()
                    ->where(['advice_id', '=', (int) $advice['id']])
                    ->cursor();

                    $row['rates'] = [];

                    foreach ($rates as $rate) {
                        $category = Model::Adviceratecategory()->find((int) $rate['adviceratecategory_id']);

                        if ($category) {
                            $row['rates'][] = [
                                'rate'          => $rate['rate'],
                                'name'          => $category['name'],
                                'order'         => $category['order'],
                                'ponderation'   => $category['ponderation']
                            ];
                        }
                    }

                    if (!empty($row['rates'])) {
                        $row['rates'] = array_values(
                            lib('collection', [$row['rates']])
                            ->sortBy('order')
                            ->toArray()
                        );
                    }

                    $collection[] = $row;
                }
            }

            // return $collection;

            return empty($collection) ? [
                [
                    'id'            => 1,
                    'account_id'    => 28,
                    'firstname'     => 'Pierre-Emmanuel',
                    'positive'      => "Un accueil digne d'un 4 étoiles.",
                    'negative'      => "Ma table était en plein courant d'air. Pas de pain en rab...",
                    'rates'         => [
                        ['name' => 'Qualité', 'rate' => 6, 'ponderation' => 1],
                        ['name' => 'Accueil', 'rate' => 8, 'ponderation' => 1],
                        ['name' => 'Service', 'rate' => 8, 'ponderation' => 1]
                    ]
                ],
                [
                    'id'            => 2,
                    'account_id'    => 30,
                    'firstname'     => 'Nicolas',
                    'positive'      => "Très bon sommelier.",
                    'negative'      => "Pas de parking à proximité.",
                    'rates'         => [
                        ['name' => 'Qualité', 'rate' => 8, 'ponderation'    => 1],
                        ['name' => 'Accueil', 'rate' => 10, 'ponderation'   => 1],
                        ['name' => 'Service', 'rate' => 6, 'ponderation'    => 1]
                    ]
                ]
            ] : $collection;
        }
    }
