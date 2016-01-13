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

    class MyzeliftLib
    {
        public function fiche($reseller_id)
        {
            $user = session('user')->getUser();

            if (!$user) {
                $user = [];
            }

            $account_id = (int) isAke($user, 'id', 23);

            $fiche = Model::Restodata()
            ->where(['reseller_id', '=', (int) $reseller_id])
            ->first(true);

            /* c'est un restaurateur */
            if ($fiche) {
                $row = $fiche->toArray();

                $data = [];

                $options = $row['options'];

                $preferences_client_paiement_titre = isAke(
                    $options,
                    'preferences_client_paiement_titre',
                    false
                );

                $data['paiement_cheque_montant_max'] = isAke(
                    $options,
                    'preferences_client_paiement_cheque_montant_max',
                    0
                );

                $data['paiement_carte'] = isAke(
                    $options,
                    'preferences_client_paiement_carte',
                    -1
                );

                $data['paiement_titre_rendu_monnaie'] = isAke(
                    $options,
                    'preferences_client_paiement_titre_rendu_monnaie',
                    $preferences_client_paiement_titre ? 0 : -1
                );

                $data['paiement_titre_max_accepte'] = isAke(
                    $options,
                    'preferences_client_paiement_titre_max_accepte',
                    null
                );

                $data['company'] = $row['company'];

                $data['company']['siret']    = (string) isAke($row['company'], 'siret', '');

                $data['company']['tel']      = (string) str_replace(
                    [" ", ".", "-", "+"],
                    ["", "", "", "00"],
                    isAke($row['company'], 'tel', '')
                );

                if (fnmatch('0*', $data['company']['tel'])) {
                    $data['company']['tel'] = strReplaceFirst('0', '+33', $data['company']['tel']);
                }

                $data['is_favorite']    = lib('favorite')->has('reseller', (int) $reseller_id, (int) $account_id);
                $data['is_resto']       = true;

                $data['nombre_places'] = $options['nombre_places'];
                $data['nombre_places_terrasse'] = $options['nombre_places_terrasse'];

                $horaires = [];

                $schedules = $row['schedules'];

                foreach ($schedules as $typeSchedule => $days) {
                    if (!isset($horaires[$typeSchedule])) {
                        $horaires[$typeSchedule] = [];
                    }

                    foreach ($days as $day => $dataSchedule) {
                        if (!isset($horaires[$typeSchedule][$day])) {
                            $horaires[$typeSchedule][$day] = [];
                        }

                        foreach ($dataSchedule as $when => $hour) {
                            $hour = str_replace('_', 'h', $hour);
                            $horaires[$typeSchedule][$day][$when] = str_replace('h00', 'h', $hour);
                        }
                    }
                }

                $data['horaires'] = $horaires;

                $prefsResto         = array_keys(lib('resto')->extractPreferences($options));
                $activitesResto     = array_keys(lib('resto')->extractActivites($options));
                $labelsResto        = array_keys(lib('resto')->extractLabels($options));
                $thematiquesResto   = array_keys(lib('resto')->extractThematiques($options));
                $guidesResto        = array_keys(lib('resto')->extractGuides($options));

                $optionsMacro       = include(APPLICATION_PATH . DS . 'models/options/413.php');

                $valuesGuides       = array_get($optionsMacro, 'guides.values');
                $valuesActivities   = array_get($optionsMacro, 'activites.values');
                $themes_affil       = array_get($optionsMacro, 'activites.types_affil');

                $valuesThematiques = [];

                $valuesThematiques  = array_merge($valuesThematiques, array_get($optionsMacro, 'thematiques.values_0'));
                $valuesThematiques  = array_merge($valuesThematiques, array_get($optionsMacro, 'thematiques.values_1'));
                $valuesThematiques  = array_merge($valuesThematiques, array_get($optionsMacro, 'thematiques.values_2'));
                $valuesThematiques  = array_merge($valuesThematiques, array_get($optionsMacro, 'thematiques.values_3'));

                $valuesLabels = [];

                $valuesLabels  = array_merge($valuesLabels, array_get($optionsMacro, 'labels.values_0'));
                $valuesLabels  = array_merge($valuesLabels, array_get($optionsMacro, 'labels.values_1'));

                $acts = $themes = $lbls = $prefs = $guides = [];

                foreach ($guidesResto as $guidesId) {
                    $guides[] = $valuesGuides[(int) str_replace('guides_', '', $guidesId)];
                }

                foreach ($activitesResto as $activiteId) {
                    if (fnmatch('*_*_*', $activiteId)) {
                        continue;
                    }

                    $acts[] = $valuesActivities[(int) str_replace('activites_', '', $activiteId)];
                }

                foreach ($thematiquesResto as $thematiquesId) {
                    $ind        = str_replace('thematiques_', 'thematiques.values_', $thematiquesId);
                    $last       = Arrays::end(explode('_', $ind));
                    $ind        = str_replace('_' . $last, '.' . $last, $ind);
                    $ind        = str_replace('thematiques.values.', 'thematiques.values_', $ind);
                    $val        = array_get($optionsMacro, $ind);

                    $themes[]   = $val;
                }

                foreach ($labelsResto as $labelsId) {
                    $ind        = str_replace('labels_', 'labels.values_', $labelsId);
                    $last       = Arrays::end(explode('_', $ind));
                    $ind        = str_replace('_' . $last, '.' . $last, $ind);
                    $ind        = str_replace('labels.values.', 'labels.values_', $ind);
                    $val        = array_get($optionsMacro, $ind);

                    $lbls[]   = $val;
                }

                // foreach ($labelsResto as $labelsId) {
                //     $lbls[] = $valuesLabels[(int) str_replace('thematiques_', '', $labelsId)];
                // }

                foreach ($prefsResto as $index) {
                    $tab = explode('_', $index);

                    if (is_numeric(end($tab))) {
                        if (!isset($prefs[str_replace(['preferences_client_', '_' . end($tab)], '', $index)])) {
                            $prefs[str_replace(['preferences_client_', '_' . end($tab)], '', $index)] = [];
                        }

                        $values = array_get($optionsMacro, str_replace('_' . end($tab), '', $index) . '.values');

                        $prefs[str_replace(['preferences_client_', '_' . end($tab)], '', $index)][] = $values[end($tab)];
                    }
                }

                $prefs['paiement'] = array_merge(
                    isset($prefs['paiement_carte']) ? $prefs['paiement_carte'] : [],
                    isset($prefs['paiement_titre']) ? $prefs['paiement_titre'] : []
                );

                unset($prefs['paiement_carte']);
                unset($prefs['paiement_titre']);

                $prefs['prestations_de_reception'] = array_merge(
                    isset($prefs['prestations_specifiques']) ? $prefs['prestations_specifiques'] : [],
                    isset($prefs['prestations_de_reception']) ? $prefs['prestations_de_reception'] : [],
                    isset($prefs['prestations_de_reception_langue']) ? $prefs['prestations_de_reception_langue'] : []
                );

                unset($prefs['prestations_specifiques']);
                // unset($prefs['prestations_de_reception']);
                unset($prefs['prestations_de_reception_langue']);

                $p = [];

                foreach ($prefs as $k => $v) {
                    $p[] = ['name' => $k, 'data' => $v];
                }

                $prefs = $p;

                $data['activites']      = array_values(array_unique($acts));
                $data['thematiques']    = array_values(array_unique($themes));
                $data['labels']         = array_values(array_unique($lbls));
                $data['preferences']    = $prefs;
                $data['guides']         = array_values(array_unique($guides));
                $data['location']       = $row['loc'];

                $extras = Model::Extradata()->where(['reseller_id', '=', (int) $reseller_id])->cursor()->first();

                if (empty($extras)) {
                    $extras = ['access' => 'Prendre Tram 1 descendre à l\'arrêt Godrans.'];
                }

                $data['extras'] = $extras;

                $myzelift = Model::Myzelift()->where(['reseller_id', '=', (int) $reseller_id])->cursor()->first();

                if (!isset($myzelift['general_intro_1'])) {
                    $myzelift = [
                        'general_intro_1' => 'Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis intro 1',
                        'general_intro_2' => 'Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis intro 2',
                        'general_1' => 'Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis 1',
                        'general_2' => 'Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis 2',
                        'general_3' => 'Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis 3',
                        'general_4' => 'Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis 4',
                        'plus_1' => 'Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis 1',
                        'plus_2' => 'Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis 1',
                        'plus_3' => 'Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis 1',
                        'coeur_1' => 'Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis 1',
                        'coeur_2' => 'Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis 1',
                        'coeur_3' => 'Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis 1',
                    ];
                }

                if (isset($myzelift['images']) && isset($myzelift['data'])) {
                    $zc = [];

                    $zc['images']   = $myzelift['images'];
                    $zc['data']     = $myzelift['data'];

                    $myzelift = $zc;
                }

                $carte  = $row['carte'];

                $nc = $c = [];

                $categories = Model::Catalogcategory()->cursor();

                foreach ($categories as $cat) {
                    $nc[] = [
                        'name' => $cat['name'],
                        'data' => $carte[$cat['id']]
                    ];
                }

                $data['carte'] = $nc;
                $data['myzelift'] = $myzelift;

                return $data;
            } else {
                /* ce n'est pas un restaurateur */
                $tab = Model::Company()
                ->where(['reseller_id', '=', (int) $reseller_id])
                ->first(false);

                $tab['rate']        = 0;
                $tab['is_resto']    = false;

                return $tab;
            }
        }

        public function getActivites($reseller_id)
        {
            $fiche = Model::Restodata()
            ->where(['reseller_id', '=', (int) $reseller_id])
            ->first(true);

            if ($fiche) {
                $row = $fiche->toArray();

                $acts = [];

                $options            = $row['options'];
                $optionsMacro       = include(APPLICATION_PATH . DS . 'models/options/413.php');

                $valuesActivities   = array_get($optionsMacro, 'activites.values');

                $activitesResto     = array_keys(lib('resto')->extractActivites($options));

                foreach ($activitesResto as $activiteId) {
                    if (fnmatch('*_*_*', $activiteId)) {
                        continue;
                    }

                    $acts[] = isset($valuesActivities[(int) str_replace('activites_', '', $activiteId)])
                    ? $valuesActivities[(int) str_replace('activites_', '', $activiteId)]
                    : '';
                }

                return $acts;
            }

            return [];
        }

        public function getRate($reseller_id)
        {
            $fiche = Model::Restodata()
            ->where(['reseller_id', '=', (int) $reseller_id])
            ->first(true);

            if ($fiche) {
                $row = $fiche->toArray();

                return (double) $row['rate'];
            }

            return 0;
        }

        public function getThemesAffil($reseller_id)
        {
            $fiche = Model::Restodata()
            ->where(['reseller_id', '=', (int) $reseller_id])
            ->first(true);

            if ($fiche) {
                $row = $fiche->toArray();

                $themes = [];

                $options            = isAke($row, 'options', []);
                $optionsMacro       = include(APPLICATION_PATH . DS . 'models/options/413.php');
                $themes_affil       = array_get($optionsMacro, 'activites.types_affil');

                $valuesActivities   = array_get($optionsMacro, 'activites.values');

                $activitesResto     = array_keys(lib('resto')->extractActivites($options));

                foreach ($activitesResto as $activiteId) {
                    if (fnmatch('*_*_*', $activiteId)) {
                        continue;
                    }

                    $actFamily      = isset($valuesActivities[(int) str_replace('activites_', '', $activiteId)])
                    ? $valuesActivities[(int) str_replace('activites_', '', $activiteId)]
                    : '';

                    $contextFamily  = isAke($themes_affil, $actFamily, []);

                    foreach ($contextFamily as $contextAct) {
                        if (!in_array($contextAct, $themes)) {
                            $themes[] = $contextAct;
                        }
                    }
                }

                return $themes;
            }

            return [];
        }

        public function isResto($reseller_id)
        {
            $fiche = Model::Restodata()
            ->where(['reseller_id', '=', (int) $reseller_id])
            ->first(true);

            return $fiche ? true : false;
        }

        public function postDevis($data)
        {
            $row = Model::Devis()
            ->create($data)
            ->save();

            return $row->id;
        }

        public function getProducts($reseller_id)
        {
            $count = Model::Myzelift()
            ->where(['reseller_id', '=', (int) $reseller_id])
            ->where(['status', '=', 'ACTIVE'])
            ->cursor()
            ->count();

            $query = Model::FacturationProduct()->reset();

            $univers = lib('zechallenge')->getContext($reseller_id);

            $query->where(['univers', '=', (string) $univers])
            ->where(['platform', '=', 'myZelift']);

            $mensuel = $query->where(['name', '=', 'Abonnement annuel mensualisé'])->first();

            $q = Model::FacturationProduct()->reset();

            if ($count < 1) {
                $product = $q->where(['name', '=', 'Abonnement annuel 1'])->first();
            } elseif ($count < 2) {
                $product = $q->where(['name', '=', 'Abonnement annuel 2'])->first();
            } else {
                $product = $q->where(['name', '=', 'Abonnement annuel 3'])->first();
            }

            return [$product, $mensuel];
        }

        public function data(array $data)
        {
            $reseller_id = isAke($data, 'reseller_id', false);

            if ($reseller_id) {
                $myzelift = Model::Myzelift()->firstOrCreate([
                    'reseller_id' => (int) $reseller_id
                ]);

                $tel    = isAke($data, 'tel', null);
                $mobile = isAke($data, 'mobile', null);

                if (fnmatch('0*', $tel)) {
                    $tel = strReplaceFirst('0', '+33', $tel);
                }

                if (fnmatch('0*', $mobile)) {
                    $mobile = strReplaceFirst('0', '+33', $mobile);
                }

                $data['tel']    = $tel;
                $data['mobile'] = $mobile;

                return $myzelift->fillAndSave($data);
            }

            exception('myzelift', "A valid reseller_id is required to proceed.");
        }

        public function getProductsRealisation($reseller_id)
        {
            $count = Model::Myzelift()
            ->where(['reseller_id', '=', (int) $reseller_id])
            ->where(['status', '=', 'ACTIVE'])
            ->cursor()
            ->count();

            $query = Model::FacturationProduct();

            $univers = lib('zechallenge')->getContext($reseller_id);

            $query  = $query->where(['univers', '=', (string) $univers])
            ->where(['name', 'LIKE', 'Réalisation %'])
            ->where(['platform', '=', 'myZelift'])
            ->order('name')
            ->cursor();

            $collection = [];

            foreach ($query as $row) {
                $collection[] = $row;
            }

            $query->reset();

            return $collection;
        }

        public function contrat(array $data, $disposition = null, $test = false)
        {
            $disposition    = is_null($disposition) ? 'attachment' : $disposition;

            $tpl            = File::read(APPLICATION_PATH . DS . 'templates/contrat_myzelift.html');

            $bucket         = new Bucket(SITE_NAME, 'http://zelift.com/bucket');

            $affiliation    = isAke($data, 'affiliation', 'resto');
            $univers        = isAke($data, 'univers', 'resto');
            $zechallenge_id = isAke($data, 'compte_zechallenge', 1);

            $contrat        = Model::FacturationContrat()
            ->refresh()
            ->where(['platform', '=', 'MyZeLift'])
            ->where(['affiliation', '=', $affiliation])
            ->where(['univers', '=', $univers])
            ->where(['zechallenge_id', '=', (int) $zechallenge_id])
            ->first(true);

            if ($contrat && !$test) {
                $return = true;

                if ($disposition == 'attachment') {
                    if (!fnmatch('http://*', $contrat->url)) {
                        $contrat->delete();

                        $return = false;
                    }

                    if ($return) {
                        return ['url' => $contrat->url, 'error' => false];
                    }
                }

                if ($return) {
                    $pdf = dwn($contrat->url);

                    header('Content-Type: application/pdf');
                    header('Content-Length: ' . strlen($pdf));
                    header('Content-Disposition: ' . $disposition . '; filename="Contrat-ZeChallenge.pdf"');
                    header('Cache-Control: private, max-age=0, must-revalidate');
                    header('Pragma: public');

                    ini_set('zlib.output_compression', '0');

                    die($pdf);
                }
            }

            $contratZC = Model::FacturationContrat()
            ->refresh()
            ->where(['platform', '=', 'ZeChallenge'])
            ->where(['zechallenge_id', '=', (int) $zechallenge_id])
            ->first(true);

            if ($contratZC) {
                $zc = Model::Zechallenge()->find((int) $zechallenge_id);

                if ($zc || $test) {
                    if (!$test) {
                        $univers        = lib('zechallenge')->getContext((int) $zc->reseller_id);
                        $contract       = lib('contrat')->store($univers, 'MyZeLift', $affiliation, $zechallenge_id);
                        $contratZC->end = strtotime('+1 year -1 day');

                        $contratZC->save();

                        if ($univers == 'resto') {
                            $code_contrat = 'RES';
                        } elseif ($univers == 'services') {
                            $code_contrat = 'SER';
                        }

                        $code_contrat .= '-MZ-' . $contract->id;

                        $data['code_contrat'] = $code_contrat;
                    }

                    foreach ($data as $k => $v) {
                        if ($k == 'univers') {
                            $tpl = str_replace("##Univers##", ucfirst($v), $tpl);
                            $tpl = str_replace("##univers##", $v, $tpl);
                        } else {
                            $tpl = str_replace("##$k##", $v, $tpl);
                        }
                    }

                    $pdf = pdfFile($tpl, 'Contrat-MyZeLift', 'portrait');

                    if (!$test) {
                        $url = $bucket->data($pdf, 'pdf');

                        $contract->url = $url;

                        if (!fnmatch('http://*', $url)) {
                            return ['url' => false, 'error' => $contract->url];
                        }

                        $contract->save();
                    }

                    if ($disposition == 'attachment') {
                        return ['url' => $url, 'error' => false];
                    }

                    header('Content-Type: application/pdf');
                    header('Content-Length: ' . strlen($pdf));
                    header('Content-Disposition: ' . $disposition . '; filename="Contrat-MyZeLift.pdf"');
                    header('Cache-Control: private, max-age=0, must-revalidate');
                    header('Pragma: public');

                    ini_set('zlib.output_compression', '0');

                    die($pdf);
                }
            } else {
                if ($disposition == 'attachment') {
                    return ['url' => false, 'error' => 'pas de contrat zechallenge actif.'];
                }
            }
        }

        public function hasPromoZenews($zechallenge_id)
        {
            $row = Model::Myzelift()->where(['zechallenge_id', '=', (int) $zechallenge_id])->first(true);

            if ($row) {
                $start = (int) $row->start_date;

                $sixMonth = strtotime('+6 month -1 day', $start);

                if ($sixMonth >= time()) {
                    return 0.25;
                }
            }

            return 0;
        }

        public function hasPromoUplift($zechallenge_id)
        {
            $row = Model::Myzelift()->where(['zechallenge_id', '=', (int) $zechallenge_id])->first(true);

            if ($row) {
                $start = (int) $row->start_date;

                $sixMonth = strtotime('+6 month -1 day', $start);

                if ($sixMonth >= time()) {
                    return 0.5;
                }
            }

            return 0;
        }
    }
