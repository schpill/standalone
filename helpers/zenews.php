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

    class ZenewsLib
    {
        public function getActus($context = null, $filter = 0, $reseller_id = 0)
        {
            $context = is_null($context) ? 'resto' : $context;
            $actus = [];

            $q = Model::Zenews()
            ->where(['status', '=', 'ACTIVE'])
            ->where(['context', '=', $context]);

            if (0 < $filter) {
                if (fnmatch('*,*', $filter)) {
                    $q = $q->where(['zenewscategory_id', 'IN', $filter]);
                } else {
                    $q = $q->where(['zenewscategory_id', '=', (int) $filter]);
                }
            }

            if (0 < $reseller_id) {
                $q = $q->where(['reseller_id', '=', (int) $reseller_id]);
            }

            $news = $q->orderByUpdatedAt('DESC')->cursor();

            foreach ($news as $new) {
                unset($new['_id']);

                $reseller_id = $new['reseller_id'];

                $item = [];

                if ($context == 'resto') {
                    $fiche = Model::Restodata()
                    ->where(['reseller_id', '=', (int) $reseller_id])
                    ->first(true);

                    $row = $fiche->toArray();

                    $options = isAke($row, 'options', []);

                    $optionsMacro       = include(APPLICATION_PATH . DS . 'models/options/413.php');
                    $valuesActivities   = array_get($optionsMacro, 'activites.values');

                    $activitesResto = array_keys(lib('resto')->extractActivites($options));

                    $item['activites'] = [];

                    foreach ($activitesResto as $activiteId) {
                        if (fnmatch('*_*_*', $activiteId)) {
                            continue;
                        }

                        $item['activites'][] = $valuesActivities[(int) str_replace('activites_', '', $activiteId)];
                    }
                }

                $company = Model::Company()
                ->where(['reseller_id', '=', (int) $reseller_id])
                ->cursor()
                ->first();

                $likes = Model::Zenewslike()->where([
                    'zenews_id', '=', (int) $new['id']
                ])->cursor()->count();

                $user = session('user')->getUser();

                if (!$user) {
                    $user = [];
                }

                $account_id = (int) isAke($user, 'id', 23);

                $like = Model::Zenewslike()
                ->where(['zenews_id', '=', (int) $new['id']])
                ->where(['account_id', '=', (int) $account_id])
                ->cursor()
                ->count();

                $hasLike = $like > 0 ? true : false;

                $item['has_like']   = $hasLike;
                $item['likes']      = $likes;
                $item['actu']       = $new;
                $item['company']    = $company;

                $actus[] = $item;
            }

            return $actus;
        }

        public function like($zenews_id, $account_id)
        {
            Model::Zenewslike()->firstOrCreate([
                'zenews_id' => (int) $zenews_id,
                'account_id' => (int) $account_id
            ]);

            return true;
        }

        public function unlike($zenews_id, $account_id)
        {
            $like = Model::Zenewslike()
            ->where(['zenews_id', '=', (int) $zenews_id])
            ->where(['account_id', '=', (int) $account_id])
            ->first(true);

            if ($like) {
                $like->delete();

                return true;
            }

            return false;
        }

        public function hasLike($zenews_id, $account_id)
        {
            $like = Model::Zenewslike()
            ->where(['zenews_id', '=', (int) $zenews_id])
            ->where(['account_id', '=', (int) $account_id])
            ->cursor()
            ->count();

            return $like > 0 ? true : false;
        }

        public function getProducts($reseller_id)
        {
            $univers = lib('zechallenge')->getContext((int) $reseller_id);

            return Model::FacturationProduct()->where(['univers', '=', (string) $univers])
            ->where(['platform', '=', 'zeNews'])
            ->order('name')
            ->cursor()->toArray();
        }

        public function post(array $data)
        {
            $id = isAke($data, 'id', false);

            if (!$id) {
                return Model::Zenews()->create($data)->save();
            } else {
                unset($data['id']);
                $row = Model::Zenews()->find((int) $id);

                if ($row) {
                    return $row->fillAndSave($data);
                }

                return false;
            }
        }
    }
