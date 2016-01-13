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

    class ZechallengeLib
    {
        public function createAccount(array $data)
        {
            if (!isset($data['status'])) {
                $data['status'] = 'WAITING';
            }

            return Model::Zechallenge()->create($data)->save();
        }

        public function hasAccount($reseller_id)
        {
            return Model::Zechallenge()
            ->where(['reseller_id', '=', (int) $reseller_id])
            ->cursor()
            ->count() > 0
            ? true
            : false;
        }

        public function importReseller($reseller_id)
        {
            $company    = Model::Company()->where(['reseller_id', '=', (int) $reseller_id])->cursor()->first();
            $reseller   = Model::Reseller()->find((int) $reseller_id);

            $duplicate = true;

            if ($company && $reseller) {
                if (isset($reseller->inovibackend_id) && is_numeric($reseller->inovibackend_id)) {
                    $ib = Model::Inovibackend()->find((int) $reseller->inovibackend_id);

                    if ($ib) {
                        $duplicate = false;
                    }
                }

                $siret = isAke($company, 'siret', false);

                if ($siret && $duplicate) {
                    if (strlen($siret) == 14) {
                        $ib = Model::Inovibackend()->where(['siret', '=', (string) $siret])->first(true);

                        if ($ib) {
                            $duplicate = false;
                        }
                    }
                }

                if ($duplicate) {
                    $ib = Model::Inovibackend()->duplicate($company);
                }

                foreach ($company as $k => $v) {
                    if ($k != 'id' && $k != 'created_at' && $k != 'updated_at') {
                        $ib->$k = $v;
                    }
                }

                $company_name   = isAke($company, 'company_name', '');
                $website        = isAke($company, 'website', null);
                $ib->url        = $website;
                $ib->phone      = isAke($company, 'tel', null);

                if (!strlen($company_name)) {
                    $ib->company_name = isAke($company, 'name', null);
                }

                $ib->save();

                $reseller->setInovibackendId($ib->id)->save();

                return $ib->id;
            }

            return false;
        }

        public function create($reseller_id)
        {
            $zcid = Model::Zechallenge()->create([
                'reseller_id'   => (int) $reseller_id,
                'status_label'  => 'Prospect validé',
                'status'        => 'WAITING'
            ])->save()->id;

            $reseller   = Model::Reseller()->find((int) $reseller_id);

            if ($reseller) {
                $reseller->setZechallengeId($zcid)->save();
            }

            return $zcid;
        }

        public function getContext($reseller_id)
        {
            $row = Model::Restodata()->where(['reseller_id', '=', (int) $reseller_id])->first(true);

            if ($row) {
                return 'resto';
            } else {
                return 'services';
            }
        }

        public function getMarket($reseller_id, $cat = false)
        {
            $row = Model::Restodata()->where(['reseller_id', '=', (int) $reseller_id])->first(true);

            if ($row) {
                return !$cat ? 'Resto' : 'Restaurant';
            } else {
                return 'Services';
            }
        }

        public function getAffil($reseller_id)
        {
            $row = Model::Restodata()->where(['reseller_id', '=', (int) $reseller_id])->first(true);

            if ($row) {
                $optionsMacro       = include(APPLICATION_PATH . DS . 'models/options/413.php');
                $valuesActivities   = array_get($optionsMacro, 'activites.values');
                $row = $row->toArray();
                $options = isAke($row, 'options', []);

                foreach ($options as $k => $v) {
                    if (fnmatch('activites_*', $k) && !fnmatch('activites_*_*', $k)) {
                        return isset($valuesActivities[(int) str_replace('activites_', '', $k)])
                        ? $valuesActivities[(int) str_replace('activites_', '', $k)]
                        : '';
                    }
                }

                return 'resto';
            } else {
                $segment = Model::Segmentreseller()->where(['reseller_id', '=', (int) $reseller_id])->first(true);

                if ($segment) {
                    $seg = Model::Segment()->find((int) $segment->segment_id);

                    if ($seg) {
                        return $seg->name;
                    }
                }

                return 'services';
            }
        }
    }
