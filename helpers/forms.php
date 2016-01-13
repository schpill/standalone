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

    use Illuminate\Events\Dispatcher;
    use Illuminate\Container\Container as MyContainer;

    class FormsLib
    {
        protected $dispatcher;

        public function getLanguagesFromSegmentId($sid)
        {
            $family = repo('segment')->getFamilyfromItem((int) $sid);

            $market = current($family);

            $options = $this->getOptionsFromMarket($market['id']);

            return Arrays::get($options, 'langue.values', []);
        }

        public function getModel($segment_id)
        {
            if (!is_integer($segment_id)) {
                throw new Exception("segment_id must be an integer id.");
            }

            $file = APPLICATION_PATH . DS . 'models' . DS . 'static' . DS . $segment_id . '.php';

            if (File::exists($file)) {
                $model = include($file);

                return $model;
            }

            return [];
        }

        public function getType($segment_id)
        {
            $model = $this->getModel($segment_id);

            return Arrays::get(
                $model,
                'formulaire_achat.elements.quantity.type',
                'min_max'
            );
        }

        public function getListValues($segment_id)
        {
            $model = $this->getModel($segment_id);

            return Arrays::get(
                $model,
                'formulaire_achat.elements.quantity.values',
                []
            );
        }

        public function getOptions($segment_id, $type = null)
        {
            $model = $this->getModel($segment_id);
            $type = is_null($type) ? 'price' : $type;

            return Arrays::get(
                $model,
                'formulaire_achat.elements.quantity.options.' . $type,
                []
            );
        }

        public function getOptionsFromMarkets(array $market_ids)
        {
            $collection = [];

            foreach ($market_ids as $market_id) {
                $collection = array_merge($collection, $this->getOptionsFromMarket($market_id));
            }

            return $collection;
        }

        public function getOptionsFromMarket($market_id)
        {
            if (!is_integer($market_id)) {
                throw new Exception("market_id must be an integer id.");
            }

            $file = APPLICATION_PATH . DS . 'models' . DS . 'options' . DS . $market_id . '.php';

            if (File::exists($file)) {
                $options = include($file);

                return $options;
            }

            return [];
        }

        public function postItemVendeur()
        {
            $user = session('user')->getUser();

            if ($user) {
                if (Arrays::is($user)) {
                    if (!isset($user['employee'])) {
                        return false;
                    }

                    if (!isset($user['settings'])) {
                        return false;
                    }

                    if (!isset($user['settings']['employee_index'])) {
                        return false;
                    }

                    if (!isset($user['employee'][$user['settings']['employee_index']])) {
                        return false;
                    }

                    if (!isset($user['employee'][$user['settings']['employee_index']]['id'])) {
                        return false;
                    }

                    // if (!isset($user['employee'][$user['settings']['employee_index']]['reseller_id'])) {
                    //     return false;
                    // }

                    // $reselleremployee_id    = $user['employee'][$user['settings']['employee_index']]['id'];
                    $reseller_id            = $user['employee'][$user['settings']['employee_index']]['reseller_id'];

                    $reseller               = Model::Reseller()->find((int) $reseller_id);
                    // $reselleremployee       = Model::Reselleremployee()->find((int) $reselleremployee_id);

                    if ($reseller) {
                    // if ($reseller && $reselleremployee) {
                        $data = $_POST;
                        $data['reseller_id'] = (int) $reseller->id;
                        $sellzone = lib('pivot')->getSellzone($reseller);
                        $data['sellzone_id'] = (int) $sellzone['id'];

                        $item = Model::Product()
                        ->where(['reseller_id', '=', (int) $reseller->id])
                        ->where(['segment_id', '=', (int) $data['segment_id']])
                        ->first(true);

                        if ($item) {
                            foreach ($data as $k => $v) {
                                if (!strlen($v)) {
                                    $item->$k = 0;
                                } else {
                                    $item->$k = $this->cleanInt($v);
                                }
                            }

                            $item->save();
                        } else {
                            $newData = [];

                            foreach ($data as $k => $v) {
                                if (!strlen($v)) {
                                    $newData[$k] = 0;
                                } else {
                                    $newData[$k] = $this->cleanInt($v);
                                }
                            }

                            Model::Product()->create($newData)->save();
                        }

                        lib('option')->set('options.item.' . $data['segment_id'], time(), $reseller);

                        return true;
                    }
                }
            }

            return false;
        }

        private function cleanInt($v)
        {
            if (is_numeric($v)) {
                if (!fnmatch('*.*', $v) && !fnmatch('*,*', $v)) {
                    $v = (int) $v;
                } else {
                    $v = (double) $v;
                }
            }

            return $v;
        }

        public function getItemVendeur($segment_id)
        {
            $user = session('user')->getUser();

            if ($user) {
                if (Arrays::is($user)) {
                    if (!is_integer($segment_id)) {
                        return [];
                    }

                    if (!isset($user['employee'])) {
                        return false;
                    }

                    if (!isset($user['settings'])) {
                        return false;
                    }

                    if (!isset($user['settings']['employee_index'])) {
                        return false;
                    }

                    if (!isset($user['employee'][$user['settings']['employee_index']])) {
                        return false;
                    }

                    // if (!isset($user['employee'][$user['settings']['employee_index']]['id'])) {
                    //     return false;
                    // }

                    if (!isset($user['employee'][$user['settings']['employee_index']]['reseller_id'])) {
                        return false;
                    }

                    // $reselleremployee_id    = $user['employee'][$user['settings']['employee_index']]['id'];
                    $reseller_id            = $user['employee'][$user['settings']['employee_index']]['reseller_id'];

                    $reseller           = Model::Reseller()->find((int) $reseller_id);
                    // $reselleremployee   = Model::Reselleremployee()->find((int) $reselleremployee_id);

                    if ($reseller) {
                    // if ($reseller && $reselleremployee) {
                        $item = Model::Product()
                        ->where(['reseller_id', '=', (int) $reseller->id])
                        ->where(['segment_id', '=', (int) $segment_id])
                        ->first(true);

                        if ($item) {
                            $tab = $item->toArray();
                            unset($tab['id']);
                            unset($tab['created_at']);
                            unset($tab['updated_at']);
                            unset($tab['reseller_id']);

                            return $tab;
                        }
                    }
                }
            }

            return [];
        }

        public function postOptionsMacro()
        {
            $type       = isAke($_POST, 'type', false);
            $segment_id = isAke($_POST, 'segment_id', false);

            $segment = Model::Segment()->find((int) $segment_id);

            if ($segment) {
                if (Inflector::lower($segment->name) == 'restaurant') {
                    return $this->options_macro_resto();
                } else {
                    if (false !== $type) {
                        $type = Inflector::lower($type);

                        return $this->$type();
                    }
                }
            }

            vd('segment introuvable');
            return false;
        }

        private function options_macro_resto()
        {
            $user = session('user')->getUser();

            if ($user) {
                if (is_array($user)) {
                    if (!isset($user['employee'])) {
                        vd('session invalide');
                        return false;
                    }

                    if (!isset($user['settings'])) {
                        vd('session invalide');
                        return false;
                    }

                    if (!isset($user['settings']['employee_index'])) {
                        vd('session invalide');
                        return false;
                    }

                    if (!isset($user['employee'][$user['settings']['employee_index']])) {
                        vd('session invalide');
                        return false;
                    }

                    // if (!isset($user['employee'][$user['settings']['employee_index']]['id'])) {
                    //     return false;
                    // }

                    if (!isset($user['employee'][$user['settings']['employee_index']]['reseller_id'])) {
                        vd('session invalide');
                        return false;
                    }

                    // $reselleremployee_id    = $user['employee'][$user['settings']['employee_index']]['id'];
                    $reseller_id = $user['employee'][$user['settings']['employee_index']]['reseller_id'];

                    $reseller = Model::Reseller()->find((int) $reseller_id);
                    // $reselleremployee = Model::Reselleremployee()->find((int) $reselleremployee_id);

                    if ($reseller) {
                    // if ($reseller && $reselleremployee) {
                        $segment_id = isAke($_POST, 'segment_id', false);
                        $sellzone   = lib('pivot')->getSellzone($reseller);

                        if (!$sellzone) {
                            $sellzone = Model::Sellzone()->find((int) $reseller->sellzone_id, false);
                        }

                        if (false !== $segment_id && !empty($sellzone)) {
                            $segment = Model::Segment()->find((int) $segment_id);

                            if ($segment) {
                                $form = $this->getOptionsFromMarket((int) $segment_id);

                                if (!empty($form)) {
                                    $options = Model::Optionsrestaurant()
                                    ->where(['reseller_id', '=', (int) $reseller->id])
                                    ->where(['sellzone_id', '=', (int) $sellzone['id']])
                                    ->first(true);

                                    if ($options) {
                                        $options->delete();
                                    }

                                    $data = $_POST;
                                    $data['sellzone_id'] = (int) $sellzone['id'];
                                    $data['reseller_id'] = (int) $reseller->id;

                                    $new = Model::Optionsrestaurant()->create($data)->save();
                                    lib('option')->set('options.macro.' . $segment_id, time(), $reseller);

                                    return true;
                                }
                            } else {
                                vd('pas de segment');
                                return false;
                            }
                        } else {
                            if (false !== $segment_id) {
                                vd('pas de sellzone');
                                return false;
                            } else {
                                vd('pas de segment_id');
                                return false;
                            }
                        }
                    } else {
                        vd('pas de reseller');
                        return false;
                    }
                }
            }

            vd('session invalide');
            return false;
        }

        private function options_macro()
        {
            $user = session('user')->getUser();

            if ($user) {
                if (is_array($user)) {
                    if (!isset($user['employee'])) {
                        return false;
                    }

                    if (!isset($user['settings'])) {
                        return false;
                    }

                    if (!isset($user['settings']['employee_index'])) {
                        return false;
                    }

                    if (!isset($user['employee'][$user['settings']['employee_index']])) {
                        return false;
                    }

                    // if (!isset($user['employee'][$user['settings']['employee_index']]['id'])) {
                    //     return false;
                    // }

                    if (!isset($user['employee'][$user['settings']['employee_index']]['reseller_id'])) {
                        return false;
                    }

                    // $reselleremployee_id    = $user['employee'][$user['settings']['employee_index']]['id'];
                    $reseller_id = $user['employee'][$user['settings']['employee_index']]['reseller_id'];

                    $reseller = Model::Reseller()->find((int) $reseller_id);
                    // $reselleremployee = Model::Reselleremployee()->find((int) $reselleremployee_id);

                    if ($reseller) {
                    // if ($reseller && $reselleremployee) {
                        $segment_id = isAke($_POST, 'segment_id', false);

                        if (false !== $segment_id) {
                            $segment = Model::Segment()->find((int) $segment_id);

                            if ($segment) {
                                $form = $this->getOptionsFromMarket((int) $segment_id);

                                if (!empty($form)) {
                                    if (isset($form['offres_devis'])) {
                                        $offres_devis = isAke($_POST, 'offres_devis', true);

                                        if (!is_bool($offres_devis)) {
                                            $offres_devis = 1 == $offres_devis ? true : false;
                                        }

                                        lib('option')->set('offres.devis.' . $segment_id, (bool) $offres_devis, $reseller);
                                    }

                                    if (isset($form['agenda_partage'])) {
                                        $agenda_partage = isAke($_POST, 'agenda_partage', true);

                                        if (!is_bool($agenda_partage)) {
                                            $agenda_partage = 1 == $agenda_partage ? true : false;
                                        }

                                        lib('option')->set('agenda.partage.' . $segment_id, (bool) $agenda_partage, $reseller);
                                    }

                                    if (isset($form['delai_intervention'])) {
                                        $delai_intervention = isAke($_POST, 'delai_intervention', 0);
                                        lib('option')->set('delai.intervention.' . $segment_id, (int) $delai_intervention, $reseller);
                                    }

                                    if (isset($form['montant_intervention'])) {
                                        $montant_intervention = isAke($_POST, 'montant_intervention', 0);
                                        lib('option')->set('montant.intervention.' . $segment_id, (int) $montant_intervention, $reseller);
                                    }

                                    if (isset($form['zone_intervention'])) {
                                        $zone_intervention = isAke($_POST, 'zone_intervention', 0);
                                        lib('option')->set('zone.intervention.' . $segment_id, (int) $zone_intervention, $reseller);
                                    }

                                    if (isset($form['langue'])) {
                                        if (isset($form['langue']['values'])) {
                                            $langues = Arrays::get($form, 'langue.values', []);

                                            foreach ($langues as $ind => $key) {
                                                $val = isAke($_POST, 'langue_' . $ind, false);

                                                if (!is_bool($val)) {
                                                    $val = 1 == $val ? true : false;
                                                }

                                                lib('option')->set('langue.' . $segment_id . '.' . $key, (bool) $val, $reseller);
                                            }
                                        }
                                    }

                                    if (isset($form['agenda_horaires'])) {
                                        $lundi_am_start     = isAke($_POST, 'agenda_horaires_lundi_am_start', 'ferme');
                                        $lundi_am_end       = isAke($_POST, 'agenda_horaires_lundi_am_end', 'ferme');
                                        $lundi_pm_start     = isAke($_POST, 'agenda_horaires_lundi_pm_start', 'ferme');
                                        $lundi_pm_end       = isAke($_POST, 'agenda_horaires_lundi_pm_end', 'ferme');

                                        $mardi_am_start     = isAke($_POST, 'agenda_horaires_mardi_am_start', 'ferme');
                                        $mardi_am_end       = isAke($_POST, 'agenda_horaires_mardi_am_end', 'ferme');
                                        $mardi_pm_start     = isAke($_POST, 'agenda_horaires_mardi_pm_start', 'ferme');
                                        $mardi_pm_end       = isAke($_POST, 'agenda_horaires_mardi_pm_end', 'ferme');

                                        $mercredi_am_start  = isAke($_POST, 'agenda_horaires_mercredi_am_start', 'ferme');
                                        $mercredi_am_end    = isAke($_POST, 'agenda_horaires_mercredi_am_end', 'ferme');
                                        $mercredi_pm_start  = isAke($_POST, 'agenda_horaires_mercredi_pm_start', 'ferme');
                                        $mercredi_pm_end    = isAke($_POST, 'agenda_horaires_mercredi_pm_end', 'ferme');

                                        $jeudi_am_start     = isAke($_POST, 'agenda_horaires_jeudi_am_start', 'ferme');
                                        $jeudi_am_end       = isAke($_POST, 'agenda_horaires_jeudi_am_end', 'ferme');
                                        $jeudi_pm_start     = isAke($_POST, 'agenda_horaires_jeudi_pm_start', 'ferme');
                                        $jeudi_pm_end       = isAke($_POST, 'agenda_horaires_jeudi_pm_end', 'ferme');

                                        $vendredi_am_start  = isAke($_POST, 'agenda_horaires_vendredi_am_start', 'ferme');
                                        $vendredi_am_end    = isAke($_POST, 'agenda_horaires_vendredi_am_end', 'ferme');
                                        $vendredi_pm_start  = isAke($_POST, 'agenda_horaires_vendredi_pm_start', 'ferme');
                                        $vendredi_pm_end    = isAke($_POST, 'agenda_horaires_vendredi_pm_end', 'ferme');

                                        $samedi_am_start    = isAke($_POST, 'agenda_horaires_samedi_am_start', 'ferme');
                                        $samedi_am_end      = isAke($_POST, 'agenda_horaires_samedi_am_end', 'ferme');
                                        $samedi_pm_start    = isAke($_POST, 'agenda_horaires_samedi_pm_start', 'ferme');
                                        $samedi_pm_end      = isAke($_POST, 'agenda_horaires_samedi_pm_end', 'ferme');

                                        $dimanche_am_start  = isAke($_POST, 'agenda_horaires_dimanche_am_start', 'ferme');
                                        $dimanche_am_end    = isAke($_POST, 'agenda_horaires_dimanche_am_end', 'ferme');
                                        $dimanche_pm_start  = isAke($_POST, 'agenda_horaires_dimanche_pm_start', 'ferme');
                                        $dimanche_pm_end    = isAke($_POST, 'agenda_horaires_dimanche_pm_end', 'ferme');

                                        $days = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

                                        foreach ($days as $day) {
                                            $var_ams    = $day . '_am_start';
                                            $amstart    = $$var_ams;

                                            $var_ame    = $day . '_am_end';
                                            $amend      = $$var_ame;

                                            $var_pms    = $day . '_pm_start';
                                            $pmstart    = $$var_pms;

                                            $var_pme    = $day . '_pm_end';
                                            $pmend      = $$var_pme;

                                            $amstart    = str_replace(':', '_', $amstart);
                                            $amend      = str_replace(':', '_', $amend);

                                            $pmstart    = str_replace(':', '_', $pmstart);
                                            $pmend      = str_replace(':', '_', $pmend);

                                            $schedule = Model::Schedule()->firstOrCreate([
                                                'day' => (string) $day,
                                                'reseller_id' => (int) $reseller->id,
                                            ]);

                                            if (!strlen($amstart)) {
                                                $amstart = 'ferme';
                                            }

                                            if (!strlen($amend)) {
                                                $amend = 'ferme';
                                            }

                                            if (!strlen($pmstart)) {
                                                $pmstart = 'ferme';
                                            }

                                            if (!strlen($pmend)) {
                                                $pmend = 'ferme';
                                            }

                                            $pmend  = '00_00' == $pmend ? '23_59' : $pmend;

                                            $schedule->am_start = (string) $amstart;
                                            $schedule->am_end   = (string) $amend;

                                            $schedule->pm_start = (string) $pmstart;
                                            $schedule->pm_end   = (string) $pmend;

                                            $schedule->save();
                                        }
                                    }
                                }

                                lib('option')->set('options.macro.' . $segment_id, time(), $reseller);

                                return true;
                            }
                        }
                    }
                }
            }

            return false;
        }

        public function getOptionsMacroDataResto($segment, $user)
        {
            $segment_id = (int) $segment->id;

            if ($user) {
                if (Arrays::is($user)) {
                    if (!is_integer($segment_id)) {
                        return [];
                    }

                    if (!isset($user['employee'])) {
                        return false;
                    }

                    if (!isset($user['settings'])) {
                        return false;
                    }

                    if (!isset($user['settings']['employee_index'])) {
                        return false;
                    }

                    if (!isset($user['employee'][$user['settings']['employee_index']])) {
                        return false;
                    }

                    // if (!isset($user['employee'][$user['settings']['employee_index']]['id'])) {
                    //     return false;
                    // }

                    if (!isset($user['employee'][$user['settings']['employee_index']]['reseller_id'])) {
                        return false;
                    }

                    // $reselleremployee_id    = $user['employee'][$user['settings']['employee_index']]['id'];
                    $reseller_id            = $user['employee'][$user['settings']['employee_index']]['reseller_id'];

                    $reseller = Model::Reseller()->find((int) $reseller_id);
                    // $reselleremployee = Model::Reselleremployee()->find((int) $reselleremployee_id);

                    if ($reseller) {
                    // if ($reseller && $reselleremployee) {
                        $sellzone = lib('pivot')->getSellzone($reseller);

                        if (is_integer($segment_id) && !empty($sellzone)) {
                            if ($segment) {
                                $form = $this->getOptionsFromMarket((int) $segment_id);

                                $options = Model::Optionsrestaurant()->refresh()
                                ->where(['reseller_id', '=', (int) $reseller->id])
                                ->where(['sellzone_id', '=', (int) $sellzone['id']])
                                ->first(true);

                                if ($options) {
                                    return $options->toArray();
                                }
                            }
                        }
                    }
                }
            }

            return [];
        }

        public function getOptionsMacroData($segment_id)
        {
            $user       = session('user')->getUser();
            $segment    = Model::Segment()->find((int) $segment_id);

            if ($segment) {
                if (Inflector::lower($segment->name) == 'restaurant') {
                    return $this->getOptionsMacroDataResto($segment, $user);
                }
            }

            if ($user) {
                if (Arrays::is($user)) {
                    if (!is_integer($segment_id)) {
                        return [];
                    }

                    if (!isset($user['employee'])) {
                        return false;
                    }

                    if (!isset($user['settings'])) {
                        return false;
                    }

                    if (!isset($user['settings']['employee_index'])) {
                        return false;
                    }

                    if (!isset($user['employee'][$user['settings']['employee_index']])) {
                        return false;
                    }

                    // if (!isset($user['employee'][$user['settings']['employee_index']]['id'])) {
                    //     return false;
                    // }

                    if (!isset($user['employee'][$user['settings']['employee_index']]['reseller_id'])) {
                        return false;
                    }

                    // $reselleremployee_id    = $user['employee'][$user['settings']['employee_index']]['id'];
                    $reseller_id            = $user['employee'][$user['settings']['employee_index']]['reseller_id'];

                    $reseller = Model::Reseller()->find((int) $reseller_id);
                    // $reselleremployee = Model::Reselleremployee()->find((int) $reselleremployee_id);

                    if ($reseller) {
                    // if ($reseller && $reselleremployee) {
                        if (is_integer($segment_id)) {
                            if ($segment) {
                                $form = $this->getOptionsFromMarket((int) $segment_id);
                                $returnForm = [];

                                if (!empty($form)) {
                                    if (isset($form['offres_devis'])) {
                                        $offres_devis = lib('option')->get('offres.devis.' . $segment_id, $reseller, 1);

                                        if (is_bool($offres_devis)) {
                                            $offres_devis = true === $offres_devis ? 1 : 0;
                                        } elseif (empty($offres_devis)) {
                                            $offres_devis = 0;
                                        }

                                        $returnForm['offres_devis'] = $offres_devis;
                                    }

                                    if (isset($form['agenda_partage'])) {
                                        $agenda_partage = lib('option')->get('agenda.partage.' . $segment_id, $reseller, 1);

                                        if (is_bool($agenda_partage)) {
                                            $agenda_partage = true === $agenda_partage ? 1 : 0;
                                        } elseif (empty($agenda_partage)) {
                                            $agenda_partage = 0;
                                        }

                                        $returnForm['agenda_partage'] = $agenda_partage;
                                    }

                                    if (isset($form['delai_intervention'])) {
                                        $delai_intervention = lib('option')->get('delai.intervention.' . $segment_id, $reseller, 0);

                                        $returnForm['delai_intervention'] = $delai_intervention;
                                    }

                                    if (isset($form['montant_intervention'])) {
                                        $montant_intervention = lib('option')->get('montant.intervention.' . $segment_id, $reseller, 0);

                                        $returnForm['montant_intervention'] = $montant_intervention;
                                    }

                                    if (isset($form['zone_intervention'])) {
                                        $zone_intervention = lib('option')->get('zone.intervention.' . $segment_id, $reseller, 0);

                                        $returnForm['zone_intervention'] = $zone_intervention;
                                    }

                                    if (isset($form['langue'])) {
                                        if (isset($form['langue']['values'])) {
                                            $langues = Arrays::get($form, 'langue.values', []);

                                            foreach ($langues as $ind => $key) {
                                                $val = lib('option')->get('langue.' . $segment_id . '.' . $key, $reseller, false);

                                                if (is_bool($val)) {
                                                    $val = true === $val ? 1 : 0;
                                                } elseif (empty($val)) {
                                                    $val = 0;
                                                }

                                                $returnForm['langue_' . $ind] = $val;
                                            }
                                        }
                                    }

                                    if (isset($form['agenda_horaires'])) {
                                        $days = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

                                        foreach ($days as $day) {
                                            $index_am_start = str_replace('##day##', $day, 'agenda_horaires_##day##_am_start');
                                            $index_am_end   = str_replace('##day##', $day, 'agenda_horaires_##day##_am_end');

                                            $index_pm_start = str_replace('##day##', $day, 'agenda_horaires_##day##_pm_start');
                                            $index_pm_end   = str_replace('##day##', $day, 'agenda_horaires_##day##_pm_end');

                                            $schedule       = Model::Schedule()
                                            ->where(['day', '=', (string) $day])
                                            ->where(['reseller_id', '=', (int) $reseller->id])
                                            ->first(true);

                                            if ($schedule) {
                                                $am_start   = $schedule->am_start;
                                                $am_end     = $schedule->am_end;

                                                $pm_start   = $schedule->pm_start;
                                                $pm_end     = $schedule->pm_end;

                                                if ($am_start) {
                                                    if ('ferme' == $am_start) {
                                                        $returnForm[$index_am_start] = '';
                                                    } else {
                                                        $returnForm[$index_am_start] = str_replace('_', ':', $am_start);
                                                    }
                                                } else {
                                                    $returnForm[$index_am_start] = '';
                                                }

                                                if ($am_end) {
                                                    if ('ferme' == $am_end) {
                                                        $returnForm[$index_am_end] = '';
                                                    } else {
                                                        $returnForm[$index_am_end] = str_replace('_', ':', $am_end);
                                                    }
                                                } else {
                                                    $returnForm[$index_am_end] = '';
                                                }

                                                if ($pm_start) {
                                                    if ('ferme' == $pm_start) {
                                                        $returnForm[$index_pm_start] = '';
                                                    } else {
                                                        $returnForm[$index_pm_start] = str_replace('_', ':', $pm_start);
                                                    }
                                                } else {
                                                    $returnForm[$index_pm_start] = '';
                                                }

                                                if ($pm_end) {
                                                    if ('ferme' == $pm_end) {
                                                        $returnForm[$index_pm_end] = '';
                                                    } else {
                                                        $returnForm[$index_pm_end] = str_replace('_', ':', str_replace('23_59', '00_00', $pm_end));
                                                    }
                                                } else {
                                                    $returnForm[$index_pm_end] = '';
                                                }
                                            } else {
                                                if ($day == 'dimanche' || $day == 'samedi') {
                                                    $returnForm[$index_am_start]    = '';
                                                    $returnForm[$index_am_end]      = '';
                                                    $returnForm[$index_pm_start]    = '';
                                                    $returnForm[$index_pm_end]      = '';
                                                } else {
                                                    $returnForm[$index_am_start]    = '8:00';
                                                    $returnForm[$index_am_end]      = '12:00';
                                                    $returnForm[$index_pm_start]    = '14:00';
                                                    $returnForm[$index_pm_end]      = '18:00';
                                                }
                                            }
                                        }
                                    }
                                }

                                return $returnForm;
                            }
                        }
                    }
                }
            }

            return [];
        }

        public function getEventDispatcher()
        {
            return $this->dispatcher;
        }

        public function setEventDispatcher($dispatcher = null)
        {
            $dispatcher         = is_null($dispatcher) ? new Dispatcher(new MyContainer) : $dispatcher;
            $this->dispatcher   = $dispatcher;

            return $this;
        }
    }
