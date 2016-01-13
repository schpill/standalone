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

    class OfferinLib
    {
        public function save($data = null)
        {
            $data = is_null($data) ? $_REQUEST : $data;

            unset($data['t']);
            unset($data['lang']);
            unset($data['ACTION']);
            unset($data['authToken']);
            unset($data['token']);

            $offerin = Model::Offerin()->create($data)->save();
            // $offerin = Model::Offerin()->find(7);

            return $this->makeOffersOut($offerin);
        }

        public function makeOffersOut($offerin)
        {
            $collection = [];
            $errors     = [];
            $infos      = [];
            $resellers  = [];

            $minStartDelay  = Config::get('offerin.delay', 115) * 60;

            $model      = lib('model')->getStaticModel((int) $offerin->segment_id);
            $isCalendar = isAke($model, 'is_calendar', true);

            $now        = time();
            $isPro      = false;

            if (!isset($offerin->sellzone_id)) {
                $offerin->sellzone_id = 1;
                $offerin->save();
            }

            if (isset($offerin->company_id)) {
                $address    = Model::Companyaddress()->findOrFail((int) $offerin->address_id);
                $isPro      = true;
            } else {
                $address    = Model::Accountaddress()->findOrFail((int) $offerin->address_id);
            }

            if (true === $isPro) {
                if (!isset($offerin->company_id)) {
                    $offerin->company_id = (int) $address->company_id;
                    $offerin = $offerin->save();
                }
            } else {
                if (!isset($offerin->account_id)) {
                    $offerin->account_id = (int) $address->account_id;
                    $offerin = $offerin->save();
                }
            }

            $locationOffer = lib('utils')->remember(
                'get.location.address.' . $address->id,
                function ($address) {return getLocation($address);},
                $isPro ? Model::Companyaddress()->getAge() : Model::Accountaddress()->getAge(),
                [$address]
            );

            if (empty($locationOffer)) {
                $coords = lib('geo')->getCoords($address->address . ' ' . $address->zip . ' ' . $address->city, 250);
                setLocation($address, $coords['lng'], $coords['lat']);
                $locationOffer = ['lng' => $coords['lng'],'lat' => $coords['lat']];
            }

            $segment_id = (int) $offerin->segment_id;

            $family = repo('segment')->getFamilyfromItem((int) $segment_id);

            if (!empty($family)) {
                $market     = current($family);
                $market_id  = (int) $market['id'];
            } else {
                $market_id  = $segment_id;
            }

            $offer = $offerin->toArray();

            if (isset($offer['date']) && isset($offer['time'])) {
                list($y, $m, $d) = explode('-', $offer['date'], 3);
                list($h, $i, $s) = explode(':', $offer['time'], 3);

                $start = mktime($h, $i, $s, $m, $d, $y);
            } else {
                $start = $offerin->created_at;
            }

            if ($start < $now) {
                $errors['starttime_error'] = 'Start time [' . date('Y-m-d H:i:s', $start) . '] is before now.';
            }

            $delaySeconds   = $start - $now;
            $delai          = (double) round($delaySeconds / 3600, 2);

            if ($delaySeconds < $minStartDelay) {
                $errors['delay_incorrect'] = 'The delay is incorrect [' . $delaySeconds . ' seconds]. The delay me be at less egual to ' . $minStartDelay . ' seconds.';

                return ['resellers' => $resellers, 'offers' => $collection, 'errors' => $errors, 'infos' => $infos];
            }

            $langue = $offerin->langue;

            if (!strlen($langue) || empty($langue) || strtolower($langue) == 'non') {
                $langue = false;
            }

            if ($langue) {
                $langues = lib('forms')->getLanguagesFromSegmentId($segment_id);

                if (!in_array($langue, $langues)) {
                    $langue = false;
                }
            }

            $this->cleanToPay($offerin->segment_id);

            $products = Model::Productdata()
            ->where(['segment_id', '=', (int) $offerin->segment_id])
            ->where(['sellzone_id', '=', (int) $offerin->sellzone_id])
            ->cursor();

            foreach ($products as $product) {
                $item = [];
                $reseller   = Model::Reseller()->model($product['reseller']);
                $company   = Model::Company()->model($product['company']);

                // $company    = Model::Company()->where(['reseller_id', '=', (int) $product['reseller_id']])->first(true);

                if ($reseller && $company) {
                    // $statusId = (int) lib('status')->getId('reseller', 'REGISTER');

                    if (false === $product['status']) {
                        $infos[$reseller->id]['status'] = true;
                        continue;
                    }

                    $infos[$reseller->id] = [];

                    $options = $product['options'];

                    $hasAgenda = isAke($options, 'has_agenda', false);
                    // $hasAgenda = lib('option')->get('agenda.partage', $reseller, false);

                    $item['reseller_calendar']  = $hasAgenda;
                    $item['reseller_id']        = $reseller->id;
                    $item['segment_id']         = $segment_id;

                    if (true === $isPro) {
                        $item['company_id'] = $offer['company_id'];
                    } else {
                        $item['account_id'] = $offer['account_id'];
                    }

                    $locationReseller   = lib('utils')->remember('has.locations.companies.' . $product['reseller_id'], function ($reseller_id) {
                        $company = Model::Company()->where(['reseller_id', '=', (int) $reseller_id])->first(true);
                            $coords = lib('geo')->getCoordsMap($company->address . ' ' . $company->zip . ' ' . $company->city);

                            $loc = ['lng' => $coords['lng'], 'lat' => $coords['lat']];
                        return $loc;
                    }, Model::Company()->getAge(), [$product['reseller_id']]);

                    $quantity = (double) $this->getQuantity($offer);

                    /* si une langue est demandée on vérifie si le revendeur la parle, sinon, on passe au suivant */
                    if ($langue) {
                        // $speak = lib('option')->get('langue.' . $market_id . '.' . $langue, $reseller, false);
                        $speak = isAke($options['languages'], $langue, false);

                        if (false === $speak) {
                            $infos[$reseller->id]['language'] = true;
                            continue;
                        }
                    }

                    $distanceMaxOffer       = isset($offer['distance_max']) ? (double) $offer['distance_max'] : 0;
                    // $distanceMaxReseller    = (double) lib('option')->get('zone.intervention.' . $market_id, $reseller, 0);
                    $distanceMaxReseller    = (double) isAke($options, 'distance_max', 0);

                    $distance = distanceKmMiles(
                        $locationOffer['lng'],
                        $locationOffer['lat'],
                        $locationReseller['lng'],
                        $locationReseller['lat']
                    );

                    $km = (double) $distance['km'];

                    $item['distance'] = $km;

                    if (0 < $distanceMaxOffer || 0 < $distanceMaxReseller) {
                        if ($distanceMaxOffer < $km && $distanceMaxOffer > 0) {
                            $infos[$reseller->id]['distance_reseller'] = true;
                            continue;
                        }

                        if ($distanceMaxReseller < $km && $distanceMaxReseller > 0) {
                            $infos[$reseller->id]['distance_buyer'] = true;
                            continue;
                        }
                    }

                    // $delai_presta = lib('option')->get('delai.intervention.' . $market_id, $reseller, 0);
                    $delai_presta = isAke($options, 'delai_min', 0);

                    $item['date_max_offer'] = (int) $start - 3600;

                    if (0 < $delai_presta) {
                        if ($delai < $delai_presta) {
                            $infos[$reseller->id]['delay_reseller'] = true;

                            continue;
                        } else {
                            $item['date_max_offer'] = (int) $start - ($delai_presta * 3600);
                        }
                    }

                    if ($hasAgenda) {
                        $item['date_max_offer'] = $item['date_max_offer'] > ($now + 3600) ? $now + 3600 : $item['date_max_offer'];
                    } else {
                        $item['date_max_offer'] = $item['date_max_offer'] > ($now + 1800) ? $now + 1800 : $item['date_max_offer'];
                    }

                    $makePrice = $amount = (double) $this->makePrice($offer, $product['product']);

                    $minAmount = isAke($options, 'amount_min', 0);

                    if ($minAmount > $amount) {
                        $infos[$reseller->id]['amount_reseller'] = true;

                        continue;
                    }

                    $tva                        = isAke($product['product'], 'tva', 1);
                    $discount_default_quantity  = isAke($product['product'], 'discount_default_quantity', 0);
                    $discount_default_amount    = (double) isAke($product['product'], 'discount_default_amount', 0);
                    $discount_price_quantity    = isAke($product['product'], 'discount_price_quantity', 0);
                    $discount_price_amount      = (double) isAke($product['product'], 'discount_price_amount', 0);

                    $fixed_costs_default        = (double) isAke($product['product'], 'fixed_costs_default', 0);
                    $travel_costs_default       = (double) isAke($product['product'], 'travel_costs_default', 0);
                    $shipping_costs_default     = (double) isAke($product['product'], 'shipping_costs_default', 0);

                    $item['fixed_costs']        = $fixed_costs_default;
                    $item['travel_costs']       = $travel_costs_default;
                    $item['shipping_costs']     = $shipping_costs_default;

                    $item['discount']           = 0;
                    $item['tva_id']             = (int) $tva;

                    if ($discount_default_quantity > 0 && $discount_default_amount > 0) {
                        if ($quantity >= $discount_default_quantity) {
                            $discount = (double) round(($amount * $discount_default_amount) / 100, 2);

                            $amount -= $discount;

                            $item['discount'] = $discount;
                        }
                    } else {
                        if ($discount_price_quantity > 0 && $discount_price_amount > 0) {
                            if ($amount >= $discount_price_quantity) {
                                $discount = (double) round(($amount * $discount_price_amount) / 100, 2);

                                $amount -= $discount;

                                $item['discount'] = $discount;
                            }
                        }
                    }

                    if (0 < $fixed_costs_default) {
                        $amount += $fixed_costs_default;
                    }

                    if (0 < $shipping_costs_default) {
                        $amount += $shipping_costs_default;
                    }

                    if (0 < $travel_costs_default) {
                        $travel_costs = (double) $travel_costs_default * $km;
                        $amount += $travel_costs;
                        $item['travel_costs'] = $travel_costs;
                    }

                    $optionsOffer   = $this->extractOptions($offer);

                    if (!empty($optionsOffer)) {
                        foreach ($optionsOffer as $opt) {
                            $value = isAke($offer, $opt, 0);

                            if ($value > 0) {
                                $price = isAke($product['product'], $this->transform($opt), 0);

                                $amountOption = (double) $price * $quantity;

                                $amount += $amountOption;

                                $item[$this->transform($opt)] = $amountOption;
                            }
                        }
                    }

                    $resellers[] = $reseller->id;

                    $item['amount']         = (double) $amount;
                    $item['offerin_id']     = (int) $offer['id'];

                    if (true === $isCalendar) {
                        if (isset($product['product']['taux_horaire'])) {
                            $duration = (double) $makePrice / $product['product']['taux_horaire'];
                        } else {
                            $duration = $quantity;
                        }

                        $end = $start + (3600 * $duration);
                        $item['duration']   = $end - $start;

                        $employees = $this->getEmployeesCan((int) $start, (int) $end, (int) $product['reseller_id']);

                        if (empty($employees)) {
                            $infos[$reseller->id]['no_employee'] = true;
                            Model::offerouttrash()->create($item)->save();

                            continue;
                        }

                        $item['reselleremployees']  = $employees;
                        $item['start']              = $start;
                        $item['end']                = $end;
                    } else {
                        $employees = Model::Reselleremployee()
                        ->where(['reseller_id', '=', (int) $reseller->id])
                        ->select('id')->cursor()->toArray();

                        $item['reselleremployees'] = $employees;
                    }

                    $item['status_id']      = (int) lib('status')->getId('offerout', 'SHOWN');

                    $offerOut               = Model::Offerout()->create($item)->save();

                    $item['offerout_id']    = $offerOut->id;

                    $collection[] = $item;
                }
            }

            $return = [];

            $return['resellers']    = $resellers;
            $return['offers']       = $collection;

            if (!empty($errors)) {
                $return['errors']   = $errors;
            }

            if (!empty($infos)) {
                $return['infos']    = $infos;
            }

            $offerin->resellers = $resellers;

            if (true === $isCalendar) {
                $offerin->start = $start;
            }

            $offerin->save();

            return $return;
        }

        public function flexLater($offerin_id, $whenStart = 0, $news = [], $tuples = [])
        {
            $offerin = Model::Offerin()->findOrFail((int) $offerin_id);

            $outs = array_merge(
                Model::offerouttrash()->where(['offerin_id', '=', (int) $offerin_id])->cursor()->toArray(),
                Model::offerout()->where(['offerin_id', '=', (int) $offerin_id])->where(['start', '=', (int) $offerin->start])->cursor()->toArray()
            );

            $whenStart = is_null($whenStart) ? 0 : $whenStart;

            $start = 0 == $whenStart || !is_integer($whenStart) ? $offerin->start : $whenStart;

            $ofCount = Model::offerout()->where(['offerin_id', '=', (int) $offerin_id])->where(['start', '=', (int) $offerin->start])->cursor()->count();

            if (count($offerin->resellers) == $ofCount) {
                return [];
                return ['error' => 'no_more_reseller'];
            }

            if ($whenStart - $offerin->start > (72 * 3600)) {
                return $news;
            }

            if (!is_null($start)) {
                for ($i = ($start + 1800); $i <= $start + 7200; $i += 1800) {
                    foreach ($outs as $out) {
                        if (!in_array($out['reseller_id'], $tuples)) {
                            $tmpStart   = $i;
                            $tmpEnd     = $tmpStart + $out['duration'];
                            $employees  = $this->getEmployeesCan((int) $tmpStart, (int) $tmpEnd, (int) $out['reseller_id']);

                            if (!empty($employees)) {
                                unset($out['id']);
                                unset($out['created_at']);
                                unset($out['updated_at']);
                                $out['reselleremployees']   = $employees;
                                $out['start']               = $tmpStart;
                                $out['end']                 = $tmpEnd;
                                $out['status_id']           = (int) lib('status')->getId('offerout', 'SHOWN');
                                $offerOut                   = Model::Offerout()->create($out)->save();
                                $out['offerout_id']         = $offerOut->id;

                                $news[] = $out;

                                $tuples[] = $out['reseller_id'];
                            }
                        }
                    }
                }
            }

            return count($news) > $ofCount ? $news : $this->flexLater($offerin_id, $i, $news, $tuples);
        }

        public function flexEarlier($offerin_id, $whenStart = 0, $news = [], $tuples = [])
        {
            $offerin = Model::Offerin()->findOrFail((int) $offerin_id);

            $outs = array_merge(
                Model::offerouttrash()->where(['offerin_id', '=', (int) $offerin_id])->cursor()->toArray(),
                Model::offerout()->where(['offerin_id', '=', (int) $offerin_id])->where(['start', '=', (int) $offerin->start])->cursor()->toArray()
            );

            $whenStart = is_null($whenStart) ? 0 : $whenStart;

            $start = 0 == $whenStart || !is_integer($whenStart) ? $offerin->start : $whenStart;

            $ofCount = Model::offerout()->where(['offerin_id', '=', (int) $offerin_id])->where(['start', '=', (int) $offerin->start])->cursor()->count();

            if (count($offerin->resellers) == $ofCount) {
                return [];
                return ['error' => 'no_more_reseller'];
            }

            if ($offerin->start - $start > (72 * 3600)) {
                return $news;
            }

            if (!is_null($start)) {
                for ($i = ($start - 1800); $i >= $start - 7200; $i -= 1800) {
                    foreach ($outs as $out) {
                        if (!in_array($out['reseller_id'], $tuples)) {
                            $tmpStart   = $i;
                            $tmpEnd     = $tmpStart + $out['duration'];
                            $employees  = $this->getEmployeesCan((int) $tmpStart, (int) $tmpEnd, (int) $out['reseller_id']);

                            if (!empty($employees)) {
                                unset($out['id']);
                                unset($out['created_at']);
                                unset($out['updated_at']);
                                $out['reselleremployees']   = $employees;
                                $out['start']               = $tmpStart;
                                $out['end']                 = $tmpEnd;
                                $out['status_id']           = (int) lib('status')->getId('offerout', 'SHOWN');
                                $offerOut                   = Model::Offerout()->create($out)->save();
                                $out['offerout_id']         = $offerOut->id;

                                $news[] = $out;

                                $tuples[] = $out['reseller_id'];
                            }
                        }
                    }
                }
            }

            return count($news) > $ofCount ? $news : $this->flexEarlier($offerin_id, $i, $news, $tuples);
        }

        private function makePrice($offer, $product)
        {
            $quantity   = isAke($offer, 'quantity', false);
            $price      = isAke($product, 'price', false);

            if (false !== $quantity) {
                return (double) $quantity * $price;
            } else {
                $amount = 0;
                $quantities = $this->extractValues($offer, 'quantity');

                foreach ($quantities as $tmp) {
                    $val    = (double) isAke($offer, $tmp, 0);
                    $price  = (double) isAke($product, str_replace('quantity', 'price', $tmp), 0);

                    $value  = (double) $val * $price;

                    $amount += $value;
                }

                return $amount;
            }
        }

        private function getQuantity($offer)
        {
            $quantity   = isAke($offer, 'quantity', false);

            if (false !== $quantity) {
                return (double) $quantity;
            } else {
                $return = 0;

                $quantities = $this->extractValues($offer, 'quantity');

                foreach ($quantities as $tmp) {
                    $val    = (double) isAke($offer, $tmp, 0);
                    $return += $val;
                }

                return $return;
            }
        }

        private function extractValues($item, $pattern)
        {
            $collection = [];

            foreach ($item as $k => $c) {
                if (fnmatch('*' . $pattern . '_*', $k)) {
                    $collection[] = $k;
                }
            }

            return $collection;
        }

        private function extractOptions($item)
        {
            return $this->extractValues($item, 'options');
        }

        private function transform($k)
        {
            if (fnmatch('options*', $k)) {
                list($d, $what, $name) = explode('_', $k, 3);

                return $what . '_' . $d . '_' . $name;
            } else {
                return $k;
            }
        }

        public function get($id)
        {
            $offer = Model::Offerin()->findOrFail((int) $id);

            return $offer->toArray();
        }

        public function getEmployeesCan($start, $end, $reseller_id)
        {
            if (!is_integer($start) || !is_integer($end) || !is_integer($reseller_id)) {
                throw new Exception('All arguments of this method must be of type integer');
            }

            if ($end < $start) {
                throw new Exception('End must be greater than start.');
            }

            $collection = [];

            $date       = lib('time')->createFromTimestamp((int) $start);
            $midnight   = (int) $date->startOfDay()->getTimestamp();

            $day        = (string) $date->frenchDay();

            $row        = Model::Schedule()
            ->where(['day', '=', (string) $day])
            ->where(['reseller_id', '=', (int) $reseller_id])
            ->first(true);

            $reseller   = Model::findOrFail((int) $reseller_id);

            if ($row) {
                $amStart    = lib('agenda')->transform((string) $row->am_start, (int) $midnight);
                $amEnd      = lib('agenda')->transform((string) $row->am_end, (int) $midnight);
                $pmStart    = lib('agenda')->transform((string) $row->pm_start, (int) $midnight);
                $pmEnd      = lib('agenda')->transform((string) $row->pm_end, (int) $midnight);

                $continue   = true;

                if ($start > $pmEnd) {
                    $continue = false;
                } elseif ($start > $amEnd && $start < $pmStart) {
                    $continue = false;
                } elseif ($start < $amStart) {
                    $continue = false;
                }

                if (true === $continue) {
                    if ($end > $pmEnd) {
                        $continue = false;
                    } elseif ($end > $amEnd && $end < $pmStart) {
                        $continue = false;
                    }
                }

                if (true === $continue) {
                    $employees  = Model::Reselleremployee()->where(['reseller_id', '=', (int) $reseller_id])->cursor();

                    foreach ($employees as $employee) {
                        $hasAppointment = lib('agenda')->hasAppointments((int) $start, (int) $end, (int) $employee['id']);
                        $hasVacations   = lib('agenda')->hasVacations((int) $start, (int) $end, (int) $employee['id']);

                        if (false === $hasAppointment && false === $hasVacations) {
                            $collection[] = (int) $employee['id'];
                        }
                    }
                }
            }

            return $collection;
        }

        public function cleanToPay($segment_id)
        {
            /* on erase les offres out avec un statut TO_PAY depuis au moins 15 minutes */
            $offers = Model::Offerout()
            ->where(['updated_at', '<', time() - 900])
            ->where(['segment_id', '=', (int) $segment_id])
            ->where(['status_id', '=', (int) lib('status')->getId('offerout', 'TO_PAY')])
            ->cursor();

            foreach ($offers as $offer) {
                lib('offerout')->erase((int) $offer['id']);
            }
        }

        public function refresh($offerin_id)
        {
            $collection = [];

            $offers = Model::Offerout()
            ->where(['offerin_id', '=', (int) $offerin_id])
            ->cursor();

            foreach ($offers as $offer) {
                $collection[] = lib('offerout')->refresh((int) $offer['id'])->toArray();
            }

            return $collection;
        }

        public function consolidate($reseller_id = null)
        {
            $q = Model::Product();

            if (!is_null($reseller_id)) {
                $q->where(['reseller_id', '=', (int) $reseller_id]);
            }

            $products = $q->cursor();

            foreach ($products as $product) {
                if (isset($product['reseller_id']) && isset($product['segment_id']) && isset($product['sellzone_id'])) {
                    $row = Model::Productdata()->firstOrCreate([
                        'reseller_id'   => (int) $product['reseller_id'],
                        'segment_id'    => (int) $product['segment_id'],
                        'sellzone_id'   => (int) $product['sellzone_id'],
                    ]);

                    unset($product['_id']);

                    $row->product = $product;

                    $family = repo('segment')->getFamilyfromItem((int) $product['segment_id']);

                    if (!empty($family)) {
                        $market     = current($family);
                        $market_id  = (int) $market['id'];
                    } else {
                        $market_id  = $segment_id;
                    }

                    $company    = Model::Company()->where(['reseller_id', '=', (int) $product['reseller_id']])->first(false);
                    $reseller   = Model::Reseller()->find((int) $product['reseller_id'], false);
                    $segment    = Model::Segment()->find((int) $product['segment_id'], false);

                    $location   = lib('utils')->remember('has.locations.companies.' . $product['reseller_id'], function ($reseller_id) {
                        $company    = Model::Company()->where(['reseller_id', '=', (int) $reseller_id])->first(true);

                        if ($company) {
                            $coords     = lib('geo')->getCoordsMap($company->address . ' ' . $company->zip . ' ' . $company->city);
                            $loc        = ['lng' => $coords['lng'], 'lat' => $coords['lat']];
                        }

                        return $loc;
                    }, Model::Company()->getAge(), [$product['reseller_id']]);

                    $status = (int) lib('status')->getId('reseller', 'REGISTER') == $reseller['status_id'];

                    $row->company   = $company;
                    $row->segment   = $segment;
                    $row->status    = $status;
                    $row->location  = $location;
                    $row->reseller  = $reseller;

                    $options = [];

                    $resellerObj = Model::Reseller()->model($reseller);

                    $hasAgenda              = lib('option')->get('agenda.partage', $resellerObj, false);
                    $distanceMaxReseller    = (double) lib('option')->get('zone.intervention.' . $market_id, $resellerObj, 0);
                    $delai_presta           = lib('option')->get('delai.intervention.' . $market_id, $resellerObj, 0);
                    $minAmount              = lib('option')->get('montant.intervention.' . $market_id, $resellerObj, 0);

                    $options['has_agenda']      = $hasAgenda;
                    $options['distance_max']    = $distanceMaxReseller;
                    $options['delai_min']       = $delai_presta;
                    $options['amount_min']      = $minAmount;
                    $options['languages']       = [];

                    $languages = [
                        'anglais',
                        'espagnol',
                        'allemand',
                        'italien',
                        'néerlandais',
                        'portugais',
                        'russe',
                        'japonais',
                        'chinois',
                    ];

                    foreach ($languages as $language) {
                        $speak = lib('option')->get('langue.' . $market_id . '.' . $language, $resellerObj, false);
                        $options['languages'][$language] = $speak;
                    }

                    $row->options = $options;

                    $row->save();
                }
            }
        }
    }
