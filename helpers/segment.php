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

    class SegmentLib
    {
        public function getAffiliation($reseller_id, $array = true)
        {
            $metiers = Model::Segmentreseller()
            ->where(['reseller_id', '=', (int) $reseller_id])
            ->with('segment');

            $out = $marches = [];

            foreach($metiers as $metier) {
                if (isset($metier['segment'])) {
                    switch ($metier['segment']['id']) {
                        case 413:
                            $marches[413] = [
                                'id'    => 413,
                                'name'  => $metier['segment']['name']
                            ];

                            $opt_data = lib('forms')->getOptionsMacroData(413);

                            if (isAke($opt_data, 'affiliation_resto', null) == 1) {
                                $marches[413]['name'] = $marches[413]['name'] . ' ' . 'resto';
                            } elseif (isAke($opt_data, 'affiliation_snack', null) == 1) {
                                $marches[413]['name'] = $marches[413]['name'] . ' ' . 'snack';
                            } elseif (isAke($opt_data, 'affiliation_vin', null) == 1) {
                                $marches[413]['name'] = $marches[413]['name'] . ' ' . 'vin';
                            }

                            break;

                        default:
                            $seg = repo('segment')->getFamilyFromItem($metier['segment']['id']);

                            if (isset($seg[0]['id'])) {
                                $marches[$seg[0]['id']] = $seg[0];
                            }
                    }
                }
            }

            $out['txt'] = '';
            $vir = '';

            foreach ($marches as $marche) {
                $out['txt'] = $vir . $marche['name'];
                $vir = ', ';
            }

            $out['tab'] = $marches;

            return $array ? $out['tab'] : $out['txt'];
        }

        public function getAffilZl($reseller_id)
        {
            $row = Model::Restodata()->where(['reseller_id', '=', (int) $reseller_id])->first(true);

            if (!$row) {
                return $this->getAffiliation($reseller_id, false);
            }

            if (isAke($row['options'], 'affiliation_resto', null) == 1) {
                return 'resto';
            } elseif (isAke($row['options'], 'affiliation_snack', null) == 1) {
                return 'snack';
            } elseif (isAke($row['options'], 'affiliation_vin', null) == 1) {
                return 'vin';
            }
        }
    }
