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

    class ModelzlLib
    {
        public function calculateBasket($offerInId, $is_calendar = true)
        {
            $now        = time();
            $collection = lib('collection');
            $offerIn    = Model::Offerin()->find($offerInId);

            if ($offerIn) {
                $quantity       = $offerIn->quantity;
                $options        = $offerIn->options;
                $language       = $offerIn->language;
                $distance_maxB  = $offerIn->distance_max;
                $start          = $offerIn->start;
                $tolerance      = $offerIn->tolerance;
                $oresellerid    = $offerIn->reseller_id;

                $start          = is_null($start)           ? $now + (4 * 3600) : $start;
                $tolerance      = is_null($tolerance)       ? 0                 : $tolerance;
                $distance_maxB  = is_null($distance_maxB)   ? 0                 : (double) $distance_maxB;
                $delai          = $start - $now;

                $livraison      = false;

                if (isset($options['shipping_costs'])) {
                    if (isset($options['shipping_costs']['default'])) {
                        $livraison = 'oui' == $options['shipping_costs']['default'] ? true : false;
                    }
                }

                if ($delai <= 0) {
                    return [];
                }

                $delai /= 3600;

                $model = $this->getStaticModel((int) $offerIn->segment_id);

                $segment_id = $offerIn->segment_id;

                if (empty($model)) {
                    return [];
                }

                $modelType = Arrays::get(
                    $model,
                    'formulaire_achat.elements.quantity.type',
                    'min_max'
                );

                if ($modelType == 'catalog') {
                    return $this->catalog($offerIn, $model, $is_calendar);
                }

                if ($modelType == 'list_between') {
                    return $this->listBetween($offerIn, $model, $is_calendar);
                }

                $list = Arrays::get(
                    $model,
                    'formulaire_achat.elements.quantity.values',
                    []
                );

                $unite = Arrays::get(
                    $model,
                    'formulaire_achat.elements.quantity.unite',
                    'heure'
                );

                $optionsPrice = Arrays::get(
                    $model,
                    'formulaire_achat.elements.quantity.options.price',
                    []
                );

                $optionsDiscount = Arrays::get(
                    $model,
                    'formulaire_achat.elements.quantity.options.discount',
                    []
                );

                $optionsFixedCosts = Arrays::get(
                    $model,
                    'formulaire_achat.elements.quantity.options.fixed_costs',
                    []
                );

                $optionsTravelCosts = Arrays::get(
                    $model,
                    'formulaire_achat.elements.quantity.options.travel_costs',
                    []
                );

                $optionsShippingCosts = Arrays::get(
                    $model,
                    'formulaire_achat.elements.quantity.options.shipping_costs',
                    []
                );

                $locOfferIn = getLocation($offerIn);

                $queryproducts   = Model::Product()
                ->where(['segment_id', '=', $segment_id])
                ->where(['sellzone_id', '=', $offerIn->sellzone_id]);

                if (!is_null($oresellerid)) {
                    $queryproducts->where(['reseller_id', '=', (int) $oresellerid]);
                }

                $products = $queryproducts->exec(true);

                foreach ($products as $product) {
                    $price              = $product->price;
                    $fixed_costs        = $product->fixed_costs;
                    $shipping_costs     = $product->shipping_costs;
                    $travel_costs       = $product->travel_costs;
                    $discount           = $product->discount;
                    $delai_presta       = lib('option')->get('delai.intervention', $product, false);
                    $montant_min        = (double) lib('option')->get('montant.intervention', $product, 0);
                    $distance_max       = (double) lib('option')->get('zone.intervention', $product, 0);

                    if (false !== $delai_presta) {
                        if ($delai < $delai_presta) {
                            continue;
                        }
                    }

                    $fixed_costs_free_from_price = 0;

                    if (is_null($fixed_costs) || !is_array($fixed_costs)) {
                        $fixed_costs = 0;
                    } else {
                        if (isset($fixed_costs['default'])) {
                            if (isset($fixed_costs['default']['value'])) {
                                $fixed_costs = (double) $fixed_costs['default']['value'];
                            }

                            if (isset($fixed_costs['default']['free_from_price'])) {
                                $fixed_costs_free_from_price = (double) $fixed_costs['default']['free_from_price'];
                            }
                        } else {
                            $fixed_costs = 0;
                        }
                    }

                    $travel_costs_free_from_price = 0;

                    if (is_null($travel_costs) || !is_array($travel_costs)) {
                        $travel_costs = 0;
                    } else {
                        if (isset($travel_costs['default'])) {
                            if (isset($travel_costs['default']['value'])) {
                                $travel_costs = (double) $travel_costs['default']['value'];
                            }

                            if (isset($travel_costs['default']['free_from_price'])) {
                                $travel_costs_free_from_price = (double) $travel_costs['default']['free_from_price'];
                            }
                        } else {
                            $travel_costs = 0;
                        }
                    }

                    if (true === $livraison) {
                        $shipping_costs_free_from_price = 0;

                        if (is_null($shipping_costs) || !is_array($shipping_costs)) {
                            $shipping_costs = 0;
                        } else {
                            if (isset($shipping_costs['default'])) {
                                if (isset($shipping_costs['default']['value'])) {
                                    $shipping_costs = (double) $shipping_costs['default']['value'];
                                }

                                if (isset($shipping_costs['default']['free_from_price'])) {
                                    $shipping_costs_free_from_price = (double) $shipping_costs['default']['free_from_price'];
                                }
                            } else {
                                $shipping_costs = 0;
                            }
                        }
                    }

                    if (!is_null($discount)) {
                        $discount_quantity = 0;
                        $discount_amount = 0;

                        if (isset($discount['default'])) {
                            if (isset($discount['default']['quantity'])) {
                                $discount_quantity = (double) $discount['default']['quantity'];
                            }

                            if (isset($discount['default']['amount'])) {
                                $discount_amount = (double) $discount['default']['amount'];
                            }
                        }
                    } else {
                        $discount_quantity = 0;
                        $discount_amount = 0;
                    }

                    $reseller = Model::Reseller()->find($product->reseller_id);

                    if ($reseller) {
                        if ($modelType == 'list_between' && !is_array($price)) {
                            continue;
                        }

                        switch ($modelType) {
                            case 'multiple':
                            case 'multiple_one':
                                $one = $modelType == 'multiple_one';
                                if (empty($optionsPrice)) {
                                    list($amount, $item) = $this->calculateAmountWithMultiple(
                                        $quantity,
                                        $price,
                                        $list,
                                        $one
                                    );

                                } else {
                                    if (empty($options) || !is_array($price)) {
                                        return [];
                                    }

                                    list($amount, $item) = $this->calculateOptionsPrice(
                                        $quantity,
                                        $optionsPrice,
                                        $options,
                                        $price,
                                        $one
                                    );
                                }

                                break;

                            case 'list_quantified':
                                $findIndex  = false;
                                $item       = [];

                                foreach ($list as $indexList => $keyList) {
                                    $checkIndex = isset($quantity[$indexList]);
                                    $checkPrice = isset($price[$indexList]);

                                    if ($checkIndex && $checkPrice) {
                                        $amount     = (double) $price[$indexList] * $quantity[$indexList];
                                        $findIndex  = true;

                                        break;
                                    }

                                    if (false === $findIndex) {
                                        $amount = 0;
                                    }
                                }

                                break;

                            case 'list_one':
                                $findIndex  = false;
                                $item       = [];

                                foreach ($list as $indexList => $keyList) {
                                    $checkIndex = isset($quantity[$indexList]);
                                    $checkPrice = isset($price[$indexList]);

                                    if ($checkIndex && $checkPrice) {
                                        $amount     = (double) $price[$indexList];
                                        $findIndex  = true;

                                        break;
                                    }

                                    if (false === $findIndex) {
                                        $amount = 0;
                                    }
                                }

                                break;

                            default:
                                $amount = (double) $price * $quantity;
                                $item   = [];

                                break;
                        }

                        $amount = (double) $amount;

                        if (true == $livraison && $shipping_costs > 0 && $shipping_costs_free_from_price > 0) {
                            if ($shipping_costs_free_from_price <= $amount) {
                                $shipping_costs = 0;
                            }
                        }

                        if (true === $livraison) {
                            $item['shipping_costs'] = $shipping_costs;
                        }

                        if ($travel_costs > 0 && $travel_costs_free_from_price > 0) {
                            if ($travel_costs_free_from_price <= $amount) {
                                $travel_costs = 0;
                            }
                        }

                        if ($fixed_costs > 0 && $fixed_costs_free_from_price > 0) {
                            if ($fixed_costs_free_from_price <= $amount) {
                                $fixed_costs = 0;
                            }
                        }

                        /* on calcule la ristourne sur la quantité si elle existe */
                        $everDiscount = false;

                        if ($quantity >= $discount_quantity && 0 < $discount_amount) {
                            $discountAmount = (double) ($amount * $discount_amount) / 100;
                            $amount         = (double) $amount - $discountAmount;

                            $everDiscount   = true;
                        }

                        /* si pas de ristourne quantité on regarde s'il ya une ristourne de prix */
                        if (!is_null($discount) && is_array($discount)) {
                            $discountPrice = isAke($discount, 'price', false);

                            if (false !== $discountPrice) {
                                $discount_quantity = 0;
                                $discount_amount = 0;

                                if (isset($discountPrice['quantity'])) {
                                    $discount_quantity = (double) $discountPrice['quantity'];
                                }

                                if (isset($discountPrice['amount'])) {
                                    $discount_amount = (double) $discountPrice['amount'];
                                }

                                if ($amount >= $discount_quantity && 0 < $discount_amount) {
                                    $discountAmount = (double) ($amount * $discount_amount) / 100;
                                    $amount         = (double) $amount - $discountAmount;

                                    $everDiscount   = true;
                                }
                            }
                        }

                        /* on peut ajouter les frais fixes et de livraison */
                        $amount         = $amount + $fixed_costs + $shipping_costs;
                        $discountAmount = 0;
                        $hasAgenda      = lib('option')->get('agenda.partage.' . $segment_id, $reseller, false);

                        /* on regarde si le revendeur a spécifié un montant minumum pour accepter une presta */
                        if (0 == $montant_min) {
                            $montant_min = (double) lib('option')->get('montant.intervention.' . $segment_id, $reseller, 0);
                        }

                        /* si tel est le cas on s'assure que le montant minimum est atteint */
                        if (0 < $montant_min) {
                            if ($amount < $montant_min) {
                                continue;
                            }
                        }

                        /* on regarde si le revendeur a spécifié un délai minumum pour accepter une presta */
                        if (false === $delai_presta) {
                            $delai_presta = lib('option')->get('delai.intervention.' . $segment_id, $reseller, false);

                            /* si tel est le cas on s'assure que le délai minimum est respecté */
                            if (false !== $delai_presta) {
                                if ($delai < $delai_presta) {
                                    continue;
                                }
                            }
                        }

                        /* si une langue est demandée on vérifie si le revendeur la parle, sinon, on passe au suivant */
                        if (!is_null($language)) {
                            $speak = lib('option')->get('langue.' . $segment_id . '.' . $language, $reseller, false);

                            if (false === $speak) {
                                continue;
                            }
                        }

                        if (o == $distance_max) {
                            $distance_max = lib('option')->get('zone.intervention.' . $segment_id, $reseller, 0);
                        }

                        $locReseller = getLocation($reseller);

                        $distance = distanceKmMiles(
                            $locOfferIn['lng'],
                            $locOfferIn['lat'],
                            $locReseller['lng'],
                            $locReseller['lat']
                        );

                        $km = (float) $distance['km'];

                        /* si la distance maximale demandée par l'acheteur est supérieure à la distance calculée
                            on ne prend pas cette offre
                         */
                        if ($distance_maxB > 0) {
                            if ($km > $distance_maxB) {
                                continue;
                            }
                        }

                        /* si la distance maximale demandée par le vendeur est supérieure à la distance calculée
                            on ne prend pas cette offre
                         */
                        if ($distance_max > 0) {
                            if ($km > $distance_max) {
                                continue;
                            }
                        }

                        /* on ajoute les frais de déplacement le cas échéant */
                        if (0 < $travel_costs) {
                            $travel_costs = (double) $travel_costs * $km;
                            $amount += $travel_costs;
                        }

                        $item['quantity']       = $quantity;
                        $item['amount']         = $amount;
                        $item['discount']       = $discountAmount;
                        $item['fixed_costs']    = $fixed_costs;
                        $item['travel_costs']   = $travel_costs;
                        $item['distance']       = $km;
                        $item['reseller_id']    = $reseller->id;

                        /* si c'est une presta calendrier et que le revendeur n'a pas de calendrier, on ajoute un attribut à l'item */

                        if (true === $is_calendar && false === $hasAgenda) {
                            $item['reseller_calendar'] = false;
                        }

                        if (true === $is_calendar && false !== $hasAgenda) {
                            $item['reseller_calendar'] = true;

                            $duration   = $this->duration($quantity, $unite, $segment_id, $reseller->id);

                            $startMin   = (int) $start - $tolerance;
                            $startMax   = (int) $start + $tolerance;

                            $endMin     = (int) $startMin + $duration;
                            $endMax     = (int) $startMax + $duration;

                            $availabilities = lib('agenda')->getAvailabilitiesByResellerId(
                                $startMin,
                                $endMax,
                                $reseller->id
                            );

                            if (!empty($availabilities)) {
                                if (!is_null($language)) {
                                    $find = false;

                                    foreach ($availabilities as $availability) {
                                        $employee = Model::Reselleremployee()->find($availability['reselleremployee_id']);

                                        if ($employee) {
                                            $speak = lib('option')->get('langue.' . $language, $employee, false);

                                            if (false !== $speak) {
                                                $item['availability_id']        = $availability['id'];
                                                $item['availability_start']     = $availability['start'];
                                                $item['availability_end']       = $availability['end'];
                                                $item['reselleremployee_id']    = $availability['reselleremployee_id'];
                                                $find = true;

                                                break;
                                            }
                                        }
                                    }

                                    if (false === $find) {
                                        continue;
                                    }
                                } else {
                                    $availability = Arrays::first($availabilities);

                                    $item['availability_id']        = $availability['id'];
                                    $item['availability_start']     = $availability['start'];
                                    $item['availability_end']       = $availability['end'];
                                    $item['reselleremployee_id']    = $availability['reselleremployee_id'];
                                }
                            } else {
                                /* si pas de dispo on passe */
                                continue;
                            }
                        }


                        /* on peut créer l'offre out et ajouter l'offre au retour */
                        $data = $item;

                        $data['offerin_id'] = $offerIn->id;
                        $data['segment_id'] = $segment_id;
                        $data['account_id'] = $offerIn->account_id;
                        $data['status_id']  = (int) lib('status')->getId('offerout', 'SHOWN');

                        $offerOut = Model::Offerout()->create($data)->save();

                        $item['offerout_id'] = $offerOut->id;

                        $collection[] = $item;
                    }
                }
            }

            /* on ordonne les offres par distance décroissante si à domicile sinon par prix */
            if ($collection->count() > 0) {
                if ($livraison) {
                    $collection->sortBy('distance');
                } else {
                    $collection->sortBy('amount');
                }
            }

            return $collection->toArray();
        }

        private function catalog($offerIn, $model, $is_calendar = true)
        {
            $now        = time();
            $collection = lib('collection');

            $quantity       = $offerIn->quantity;
            $options        = $offerIn->options;
            $language       = $offerIn->language;
            $distance_maxB  = $offerIn->distance_max;
            $start          = $offerIn->start;
            $tolerance      = $offerIn->tolerance;
            $oresellerid    = $offerIn->reseller_id;

            $start          = is_null($start)           ? $now + (4 * 3600) : $start;
            $tolerance      = is_null($tolerance)       ? 0                 : $tolerance;
            $distance_maxB  = is_null($distance_maxB)   ? 0                 : (double) $distance_maxB;
            $delai          = $start - $now;

            $livraison      = false;

            $segment_id     = $offerIn->segment_id;

            if (isset($options['shipping_costs'])) {
                if (isset($options['shipping_costs']['default'])) {
                    $livraison = 'oui' == $options['shipping_costs']['default'] ? true : false;
                }
            }

            if ($delai <= 0) {
                return [];
            }

            $delai /= 3600;

            $list = Arrays::get(
                $model,
                'formulaire_achat.elements.quantity.values',
                []
            );

            $unite = Arrays::get(
                $model,
                'formulaire_achat.elements.quantity.unite',
                'heure'
            );

            $optionsPrice = Arrays::get(
                $model,
                'formulaire_achat.elements.quantity.options.price',
                []
            );

            $optionsDiscount = Arrays::get(
                $model,
                'formulaire_achat.elements.quantity.options.discount',
                []
            );

            $optionsFixedCosts = Arrays::get(
                $model,
                'formulaire_achat.elements.quantity.options.fixed_costs',
                []
            );

            $optionsTravelCosts = Arrays::get(
                $model,
                'formulaire_achat.elements.quantity.options.travel_costs',
                []
            );

            $optionsShippingCosts = Arrays::get(
                $model,
                'formulaire_achat.elements.quantity.options.shipping_costs',
                []
            );

            $locOfferIn = getLocation($offerIn);

            $queryproducts   = Model::Product()
            ->where(['segment_id', '=', $segment_id])
            ->where(['sellzone_id', '=', $offerIn->sellzone_id]);

            if (!is_null($oresellerid)) {
                $queryproducts->where(['reseller_id', '=', (int) $oresellerid]);
            }

            $products = $queryproducts->exec(true);

            foreach ($products as $product) {
                $price              = $product->price;
                $fixed_costs        = $product->fixed_costs;
                $shipping_costs     = $product->shipping_costs;
                $travel_costs       = $product->travel_costs;
                $discount           = $product->discount;
                $delai_presta       = lib('option')->get('delai.intervention', $product, false);
                $montant_min        = (double) lib('option')->get('montant.intervention', $product, 0);
                $distance_max       = (double) lib('option')->get('zone.intervention', $product, 0);

                if (false !== $delai_presta) {
                    if ($delai < $delai_presta) {
                        continue;
                    }
                }

                $fixed_costs_free_from_price = 0;

                if (is_null($fixed_costs) || !is_array($fixed_costs)) {
                    $fixed_costs = 0;
                } else {
                    if (isset($fixed_costs['default'])) {
                        if (isset($fixed_costs['default']['value'])) {
                            $fixed_costs = (double) $fixed_costs['default']['value'];
                        }

                        if (isset($fixed_costs['default']['free_from_price'])) {
                            $fixed_costs_free_from_price = (double) $fixed_costs['default']['free_from_price'];
                        }
                    } else {
                        $fixed_costs = 0;
                    }
                }

                $travel_costs_free_from_price = 0;

                if (is_null($travel_costs) || !is_array($travel_costs)) {
                    $travel_costs = 0;
                } else {
                    if (isset($travel_costs['default'])) {
                        if (isset($travel_costs['default']['value'])) {
                            $travel_costs = (double) $travel_costs['default']['value'];
                        }

                        if (isset($travel_costs['default']['free_from_price'])) {
                            $travel_costs_free_from_price = (double) $travel_costs['default']['free_from_price'];
                        }
                    } else {
                        $travel_costs = 0;
                    }
                }

                if (true === $livraison) {
                    $shipping_costs_free_from_price = 0;

                    if (is_null($shipping_costs) || !is_array($shipping_costs)) {
                        $shipping_costs = 0;
                    } else {
                        if (isset($shipping_costs['default'])) {
                            if (isset($shipping_costs['default']['value'])) {
                                $shipping_costs = (double) $shipping_costs['default']['value'];
                            }

                            if (isset($shipping_costs['default']['free_from_price'])) {
                                $shipping_costs_free_from_price = (double) $shipping_costs['default']['free_from_price'];
                            }
                        } else {
                            $shipping_costs = 0;
                        }
                    }
                }

                if (!is_null($discount)) {
                    $discount_quantity = 0;
                    $discount_amount = 0;

                    if (isset($discount['default'])) {
                        if (isset($discount['default']['quantity'])) {
                            $discount_quantity = (double) $discount['default']['quantity'];
                        }

                        if (isset($discount['default']['amount'])) {
                            $discount_amount = (double) $discount['default']['amount'];
                        }
                    }
                } else {
                    $discount_quantity = 0;
                    $discount_amount = 0;
                }

                $reseller = Model::Reseller()->find($product->reseller_id);

                if ($reseller) {
                    foreach ($list as $productIndex => $productInfos) {
                        $buyerHasProduct    = isset($quantity[$productIndex]);
                        $resellerHasProduct = isset($price[$productIndex]);

                        if (!$buyerHasProduct || !$resellerHasProduct) {
                            continue;
                        }

                        if (!strlen($quantity[$productIndex]) || !is_array($price[$productIndex])) {
                            continue;
                        }

                        if (!is_numeric($quantity[$productIndex])) {
                            continue;
                        }

                        $qty = (double) $quantity[$productIndex];

                        $prod = $price[$productIndex];

                        $cost = isAke($prod, 'cost', false);

                        if (false !== $cost) {
                            $item   = [];
                            $amount = $cost * $qty;

                            $amount = (double) $amount;

                            if (true == $livraison && $shipping_costs > 0 && $shipping_costs_free_from_price > 0) {
                                if ($shipping_costs_free_from_price <= $amount) {
                                    $shipping_costs = 0;
                                }
                            }

                            if (true === $livraison) {
                                $item['shipping_costs'] = $shipping_costs;
                            }

                            if ($travel_costs > 0 && $travel_costs_free_from_price > 0) {
                                if ($travel_costs_free_from_price <= $amount) {
                                    $travel_costs = 0;
                                }
                            }

                            if ($fixed_costs > 0 && $fixed_costs_free_from_price > 0) {
                                if ($fixed_costs_free_from_price <= $amount) {
                                    $fixed_costs = 0;
                                }
                            }

                            /* on calcule la ristourne sur la quantité si elle existe */
                            $everDiscount = false;

                            if ($quantity >= $discount_quantity && 0 < $discount_amount) {
                                $discountAmount = (double) ($amount * $discount_amount) / 100;
                                $amount         = (double) $amount - $discountAmount;

                                $everDiscount   = true;
                            }

                            /* si pas de ristourne quantité on regarde s'il ya une ristourne de prix */
                            if (!is_null($discount) && is_array($discount)) {
                                $discountPrice = isAke($discount, 'price', false);

                                if (false !== $discountPrice) {
                                    $discount_quantity = 0;
                                    $discount_amount = 0;

                                    if (isset($discountPrice['quantity'])) {
                                        $discount_quantity = (double) $discountPrice['quantity'];
                                    }

                                    if (isset($discountPrice['amount'])) {
                                        $discount_amount = (double) $discountPrice['amount'];
                                    }

                                    if ($amount >= $discount_quantity && 0 < $discount_amount) {
                                        $discountAmount = (double) ($amount * $discount_amount) / 100;
                                        $amount         = (double) $amount - $discountAmount;

                                        $everDiscount   = true;
                                    }
                                }
                            }

                            /* on peut ajouter les frais fixes et de livraison */
                            $amount         = $amount + $fixed_costs + $shipping_costs;
                            $discountAmount = 0;
                            $hasAgenda      = lib('option')->get('agenda.partage.' . $segment_id, $reseller, false);

                            /* on regarde si le revendeur a spécifié un montant minumum pour accepter une presta */
                            if (0 == $montant_min) {
                                $montant_min = (double) lib('option')->get('montant.intervention.' . $segment_id, $reseller, 0);
                            }

                            /* si tel est le cas on s'assure que le montant minimum est atteint */
                            if (0 < $montant_min) {
                                if ($amount < $montant_min) {
                                    continue;
                                }
                            }

                            /* on regarde si le revendeur a spécifié un délai minumum pour accepter une presta */
                            if (false === $delai_presta) {
                                $delai_presta = lib('option')->get('delai.intervention.' . $segment_id, $reseller, false);

                                /* si tel est le cas on s'assure que le délai minimum est respecté */
                                if (false !== $delai_presta) {
                                    if ($delai < $delai_presta) {
                                        continue;
                                    }
                                }
                            }

                            /* si une langue est demandée on vérifie si le revendeur la parle, sinon, on passe au suivant */
                            if (!is_null($language)) {
                                $speak = lib('option')->get('langue.' . $segment_id . '.' . $language, $reseller, false);

                                if (false === $speak) {
                                    continue;
                                }
                            }

                            if (o == $distance_max) {
                                $distance_max = lib('option')->get('zone.intervention.' . $segment_id, $reseller, 0);
                            }

                            $locReseller = getLocation($reseller);

                            $distance = distanceKmMiles(
                                $locOfferIn['lng'],
                                $locOfferIn['lat'],
                                $locReseller['lng'],
                                $locReseller['lat']
                            );

                            $km = (float) $distance['km'];

                            /* si la distance maximale demandée par l'acheteur est supérieure à la distance calculée
                                on ne prend pas cette offre
                             */
                            if ($distance_maxB > 0) {
                                if ($km > $distance_maxB) {
                                    continue;
                                }
                            }

                            /* si la distance maximale demandée par le vendeur est supérieure à la distance calculée
                                on ne prend pas cette offre
                             */
                            if ($distance_max > 0) {
                                if ($km > $distance_max) {
                                    continue;
                                }
                            }

                            /* on ajoute les frais de déplacement le cas échéant */
                            if (0 < $travel_costs) {
                                $travel_costs = (double) $travel_costs * $km;
                                $amount += $travel_costs;
                            }

                            $item['quantity']       = $quantity;
                            $item['amount']         = $amount;
                            $item['discount']       = $discountAmount;
                            $item['fixed_costs']    = $fixed_costs;
                            $item['travel_costs']   = $travel_costs;
                            $item['distance']       = $km;
                            $item['reseller_id']    = $reseller->id;

                            /* si c'est une presta calendrier et que le revendeur n'a pas de calendrier, on ajoute un attribut à l'item */

                            if (true === $is_calendar && false === $hasAgenda) {
                                $item['reseller_calendar'] = false;
                            }

                            if (true === $is_calendar && false !== $hasAgenda) {
                                $item['reseller_calendar'] = true;

                                $duration   = $this->duration($quantity, $unite, $offerIn->segment_id, $reseller->id);

                                $startMin   = (int) $start - $tolerance;
                                $startMax   = (int) $start + $tolerance;

                                $endMin     = (int) $startMin + $duration;
                                $endMax     = (int) $startMax + $duration;

                                $availabilities = lib('agenda')->getAvailabilitiesByResellerId(
                                    $startMin,
                                    $endMax,
                                    $reseller->id
                                );

                                if (!empty($availabilities)) {
                                    if (!is_null($language)) {
                                        $find = false;

                                        foreach ($availabilities as $availability) {
                                            $employee = Model::Reselleremployee()->find($availability['reselleremployee_id']);

                                            if ($employee) {
                                                $speak = lib('option')->get('langue.' . $language, $employee, false);

                                                if (false !== $speak) {
                                                    $item['availability_id']        = $availability['id'];
                                                    $item['availability_start']     = $availability['start'];
                                                    $item['availability_end']       = $availability['end'];
                                                    $item['reselleremployee_id']    = $availability['reselleremployee_id'];
                                                    $find = true;

                                                    break;
                                                }
                                            }
                                        }

                                        if (false === $find) {
                                            continue;
                                        }
                                    } else {
                                        $availability = Arrays::first($availabilities);

                                        $item['availability_id']        = $availability['id'];
                                        $item['availability_start']     = $availability['start'];
                                        $item['availability_end']       = $availability['end'];
                                        $item['reselleremployee_id']    = $availability['reselleremployee_id'];
                                    }
                                } else {
                                    /* si pas de dispo on passe */
                                    continue;
                                }
                            }


                            /* on peut créer l'offre out et ajouter l'offre au retour */
                            $data = $item;

                            $data['offerin_id'] = $offerIn->id;
                            $data['account_id'] = $offerIn->account_id;
                            $data['segment_id'] = $segment_id;
                            $data['status_id']  = (int) lib('status')->getId('offerout', 'SHOWN');

                            $offerOut = Model::Offerout()->create($data)->save();

                            $item['offerout_id'] = $offerOut->id;

                            $collection[] = $item;
                        }
                    }
                }
            }

            /* on ordonne les offres par distance décroissante si à domicile sinon par prix */
            if ($collection->count() > 0) {
                if ($livraison) {
                    $collection->sortBy('distance');
                } else {
                    $collection->sortBy('amount');
                }
            }

            return $collection->toArray();
        }

        private function listBetween($offerIn, $model, $is_calendar = true)
        {
            $now        = time();
            $collection = lib('collection');

            $quantity       = $offerIn->quantity;
            $options        = $offerIn->options;
            $language       = $offerIn->language;
            $distance_maxB  = $offerIn->distance_max;
            $start          = $offerIn->start;
            $tolerance      = $offerIn->tolerance;
            $oresellerid    = $offerIn->reseller_id;
            $segment_id     = $offerIn->segment_id;

            $start          = is_null($start)           ? $now + (4 * 3600) : $start;
            $tolerance      = is_null($tolerance)       ? 0                 : $tolerance;
            $distance_maxB  = is_null($distance_maxB)   ? 0                 : (double) $distance_maxB;
            $delai          = $start - $now;

            $livraison      = false;

            if (isset($options['shipping_costs'])) {
                if (isset($options['shipping_costs']['default'])) {
                    $livraison = 'oui' == $options['shipping_costs']['default'] ? true : false;
                }
            }

            if ($delai <= 0) {
                return [];
            }

            $delai /= 3600;

            $list = Arrays::get(
                $model,
                'formulaire_achat.elements.quantity.values',
                []
            );

            $unite = Arrays::get(
                $model,
                'formulaire_achat.elements.quantity.unite',
                'heure'
            );

            $optionsPrice = Arrays::get(
                $model,
                'formulaire_achat.elements.quantity.options.price',
                []
            );

            $optionsDiscount = Arrays::get(
                $model,
                'formulaire_achat.elements.quantity.options.discount',
                []
            );

            $optionsFixedCosts = Arrays::get(
                $model,
                'formulaire_achat.elements.quantity.options.fixed_costs',
                []
            );

            $optionsTravelCosts = Arrays::get(
                $model,
                'formulaire_achat.elements.quantity.options.travel_costs',
                []
            );

            $optionsShippingCosts = Arrays::get(
                $model,
                'formulaire_achat.elements.quantity.options.shipping_costs',
                []
            );

            $locOfferIn = getLocation($offerIn);

            $queryproducts   = Model::Product()
            ->where(['segment_id', '=', $segment_id])
            ->where(['sellzone_id', '=', $offerIn->sellzone_id]);

            if (!is_null($oresellerid)) {
                $queryproducts->where(['reseller_id', '=', (int) $oresellerid]);
            }

            $products = $queryproducts->exec(true);

            foreach ($products as $product) {
                $price              = $product->price;
                $fixed_costs        = $product->fixed_costs;
                $shipping_costs     = $product->shipping_costs;
                $travel_costs       = $product->travel_costs;
                $discount           = $product->discount;
                $delai_presta       = lib('option')->get('delai.intervention', $product, false);
                $montant_min        = (double) lib('option')->get('montant.intervention', $product, 0);
                $distance_max       = (double) lib('option')->get('zone.intervention', $product, 0);

                if (false !== $delai_presta) {
                    if ($delai < $delai_presta) {
                        continue;
                    }
                }

                $fixed_costs_free_from_price = 0;

                if (is_null($fixed_costs) || !is_array($fixed_costs)) {
                    $fixed_costs = 0;
                } else {
                    if (isset($fixed_costs['default'])) {
                        if (isset($fixed_costs['default']['value'])) {
                            $fixed_costs = (double) $fixed_costs['default']['value'];
                        }

                        if (isset($fixed_costs['default']['free_from_price'])) {
                            $fixed_costs_free_from_price = (double) $fixed_costs['default']['free_from_price'];
                        }
                    } else {
                        $fixed_costs = 0;
                    }
                }

                $travel_costs_free_from_price = 0;

                if (is_null($travel_costs) || !is_array($travel_costs)) {
                    $travel_costs = 0;
                } else {
                    if (isset($travel_costs['default'])) {
                        if (isset($travel_costs['default']['value'])) {
                            $travel_costs = (double) $travel_costs['default']['value'];
                        }

                        if (isset($travel_costs['default']['free_from_price'])) {
                            $travel_costs_free_from_price = (double) $travel_costs['default']['free_from_price'];
                        }
                    } else {
                        $travel_costs = 0;
                    }
                }

                if (true === $livraison) {
                    $shipping_costs_free_from_price = 0;

                    if (is_null($shipping_costs) || !is_array($shipping_costs)) {
                        $shipping_costs = 0;
                    } else {
                        if (isset($shipping_costs['default'])) {
                            if (isset($shipping_costs['default']['value'])) {
                                $shipping_costs = (double) $shipping_costs['default']['value'];
                            }

                            if (isset($shipping_costs['default']['free_from_price'])) {
                                $shipping_costs_free_from_price = (double) $shipping_costs['default']['free_from_price'];
                            }
                        } else {
                            $shipping_costs = 0;
                        }
                    }
                }

                if (!is_null($discount)) {
                    $discount_quantity = 0;
                    $discount_amount = 0;

                    if (isset($discount['default'])) {
                        if (isset($discount['default']['quantity'])) {
                            $discount_quantity = (double) $discount['default']['quantity'];
                        }

                        if (isset($discount['default']['amount'])) {
                            $discount_amount = (double) $discount['default']['amount'];
                        }
                    }
                } else {
                    $discount_quantity = 0;
                    $discount_amount = 0;
                }

                $reseller = Model::Reseller()->find($product->reseller_id);

                if ($reseller) {
                    $min = isAke($quantity, 'min', false);
                    $max = isAke($quantity, 'max', false);

                    if (false === $min || false === $max) {
                        return [];
                    }

                    foreach ($price as $productIndex => $productInfos) {
                        $cost = isAke($productInfos, 'cost', false);

                        if ($unite == 'minute') {
                            $duration = isAke($productInfos, 'duration', false);

                            if (false === $duration || false === $cost) {
                                continue;
                            }

                            $min        = (int) $min;
                            $max        = (int) $max;
                            $duration   = (int) $duration;
                            $cost       = (double) $cost;

                            $checkContinue = $duration >= $min && $duration <= $max;
                        } else {
                            $cost   = (double) $cost;
                            $min    = (double) $min;
                            $max    = (double) $max;

                            $checkContinue = $cost >= $min && $cost <= $max;
                        }

                        if ($checkContinue) {
                            $item   = [];
                            $amount = $cost;

                            $amount = (double) $amount;

                            if (true == $livraison && $shipping_costs > 0 && $shipping_costs_free_from_price > 0) {
                                if ($shipping_costs_free_from_price <= $amount) {
                                    $shipping_costs = 0;
                                }
                            }

                            if (true === $livraison) {
                                $item['shipping_costs'] = $shipping_costs;
                            }

                            if ($travel_costs > 0 && $travel_costs_free_from_price > 0) {
                                if ($travel_costs_free_from_price <= $amount) {
                                    $travel_costs = 0;
                                }
                            }

                            if ($fixed_costs > 0 && $fixed_costs_free_from_price > 0) {
                                if ($fixed_costs_free_from_price <= $amount) {
                                    $fixed_costs = 0;
                                }
                            }

                            /* on calcule la ristourne sur la quantité si elle existe */
                            $everDiscount = false;

                            if ($quantity >= $discount_quantity && 0 < $discount_amount) {
                                $discountAmount = (double) ($amount * $discount_amount) / 100;
                                $amount         = (double) $amount - $discountAmount;

                                $everDiscount   = true;
                            }

                            /* si pas de ristourne quantité on regarde s'il ya une ristourne de prix */
                            if (!is_null($discount) && is_array($discount)) {
                                $discountPrice = isAke($discount, 'price', false);

                                if (false !== $discountPrice) {
                                    $discount_quantity = 0;
                                    $discount_amount = 0;

                                    if (isset($discountPrice['quantity'])) {
                                        $discount_quantity = (double) $discountPrice['quantity'];
                                    }

                                    if (isset($discountPrice['amount'])) {
                                        $discount_amount = (double) $discountPrice['amount'];
                                    }

                                    if ($amount >= $discount_quantity && 0 < $discount_amount) {
                                        $discountAmount = (double) ($amount * $discount_amount) / 100;
                                        $amount         = (double) $amount - $discountAmount;

                                        $everDiscount   = true;
                                    }
                                }
                            }

                            /* on peut ajouter les frais fixes et de livraison */
                            $amount         = $amount + $fixed_costs + $shipping_costs;
                            $discountAmount = 0;
                            $hasAgenda      = lib('option')->get('agenda.partage.' . $segment_id, $reseller, false);

                            /* on regarde si le revendeur a spécifié un montant minumum pour accepter une presta */
                            if (0 == $montant_min) {
                                $montant_min = (double) lib('option')->get('montant.intervention.' . $segment_id, $reseller, 0);
                            }

                            /* si tel est le cas on s'assure que le montant minimum est atteint */
                            if (0 < $montant_min) {
                                if ($amount < $montant_min) {
                                    continue;
                                }
                            }

                            /* on regarde si le revendeur a spécifié un délai minumum pour accepter une presta */
                            if (false === $delai_presta) {
                                $delai_presta = lib('option')->get('delai.intervention.' . $segment_id, $reseller, false);

                                /* si tel est le cas on s'assure que le délai minimum est respecté */
                                if (false !== $delai_presta) {
                                    if ($delai < $delai_presta) {
                                        continue;
                                    }
                                }
                            }

                            /* si une langue est demandée on vérifie si le revendeur la parle, sinon, on passe au suivant */
                            if (!is_null($language)) {
                                $speak = lib('option')->get('langue.' . $segment_id . '.' . $language, $reseller, false);

                                if (false === $speak) {
                                    continue;
                                }
                            }

                            if (o == $distance_max) {
                                $distance_max = lib('option')->get('zone.intervention.' . $segment_id, $reseller, 0);
                            }

                            $locReseller = getLocation($reseller);

                            $distance = distanceKmMiles(
                                $locOfferIn['lng'],
                                $locOfferIn['lat'],
                                $locReseller['lng'],
                                $locReseller['lat']
                            );

                            $km = (float) $distance['km'];

                            /* si la distance maximale demandée par l'acheteur est supérieure à la distance calculée
                                on ne prend pas cette offre
                             */
                            if ($distance_maxB > 0) {
                                if ($km > $distance_maxB) {
                                    continue;
                                }
                            }

                            /* si la distance maximale demandée par le vendeur est supérieure à la distance calculée
                                on ne prend pas cette offre
                             */
                            if ($distance_max > 0) {
                                if ($km > $distance_max) {
                                    continue;
                                }
                            }

                            /* on ajoute les frais de déplacement le cas échéant */
                            if (0 < $travel_costs) {
                                $travel_costs = (double) $travel_costs * $km;
                                $amount += $travel_costs;
                            }

                            $item['quantity']       = $quantity;
                            $item['amount']         = $amount;
                            $item['discount']       = $discountAmount;
                            $item['fixed_costs']    = $fixed_costs;
                            $item['travel_costs']   = $travel_costs;
                            $item['distance']       = $km;
                            $item['reseller_id']    = $reseller->id;

                            /* si c'est une presta calendrier et que le revendeur n'a pas de calendrier, on ajoute un attribut à l'item */

                            if (true === $is_calendar && false === $hasAgenda) {
                                $item['reseller_calendar'] = false;
                            }

                            if (true === $is_calendar && false !== $hasAgenda) {
                                $item['reseller_calendar'] = true;

                                $duration   = $this->duration($quantity, $unite, $offerIn->segment_id, $reseller->id);

                                $startMin   = (int) $start - $tolerance;
                                $startMax   = (int) $start + $tolerance;

                                $endMin     = (int) $startMin + $duration;
                                $endMax     = (int) $startMax + $duration;

                                $availabilities = lib('agenda')->getAvailabilitiesByResellerId(
                                    $startMin,
                                    $endMax,
                                    $reseller->id
                                );

                                if (!empty($availabilities)) {
                                    if (!is_null($language)) {
                                        $find = false;

                                        foreach ($availabilities as $availability) {
                                            $employee = Model::Reselleremployee()->find($availability['reselleremployee_id']);

                                            if ($employee) {
                                                $speak = lib('option')->get('langue.' . $language, $employee, false);

                                                if (false !== $speak) {
                                                    $item['availability_id']        = $availability['id'];
                                                    $item['availability_start']     = $availability['start'];
                                                    $item['availability_end']       = $availability['end'];
                                                    $item['reselleremployee_id']    = $availability['reselleremployee_id'];
                                                    $find = true;

                                                    break;
                                                }
                                            }
                                        }

                                        if (false === $find) {
                                            continue;
                                        }
                                    } else {
                                        $availability = Arrays::first($availabilities);

                                        $item['availability_id']        = $availability['id'];
                                        $item['availability_start']     = $availability['start'];
                                        $item['availability_end']       = $availability['end'];
                                        $item['reselleremployee_id']    = $availability['reselleremployee_id'];
                                    }
                                } else {
                                    /* si pas de dispo on passe */
                                    continue;
                                }
                            }


                            /* on peut créer l'offre out et ajouter l'offre au retour */
                            $data = $item;

                            $data['offerin_id'] = $offerIn->id;
                            $data['account_id'] = $offerIn->account_id;
                            $data['segment_id'] = $segment_id;
                            $data['status_id']  = (int) lib('status')->getId('offerout', 'SHOWN');

                            $offerOut = Model::Offerout()->create($data)->save();

                            $item['offerout_id'] = $offerOut->id;

                            $collection[] = $item;
                        }
                    }
                }
            }

            /* on ordonne les offres par distance décroissante si à domicile sinon par prix */
            if ($collection->count() > 0) {
                if ($livraison) {
                    $collection->sortBy('distance');
                } else {
                    $collection->sortBy('amount');
                }
            }

            return $collection->toArray();
        }

        private function calculateOptionsPrice($quantity, $optionsPrice, $optionsPriceOfferIn, $prices, $one = false)
        {
            $item   = [];
            $amount = 0;

            foreach ($optionsPrice as $key => $option) {
                $type       = isAke($option, 'type', null);
                $display    = isAke($option, 'display', 'once');
                $default    = isAke($option, 'default', false);

                if (!is_null($type)) {
                    switch ($type) {
                        case 'multiple':
                            if (!is_array($quantity)) {
                                $defaultPrice = isset($prices[$key][0]) ? $prices[$key][0] : 0;

                                foreach ($optionsPriceOfferIn[$key] as $index => $optI) {
                                    $val        = isset($prices[$key][$index]) ? $prices[$key][$index] : 0;
                                    $price      = $quantity * $val;
                                    $amount     += (double) $price;
                                }

                                if (count($optionsPriceOfferIn[$key]) > 1) {
                                    $countLess = count($optionsPriceOfferIn[$key]) - 1;
                                    $amount -= $countLess * $defaultPrice;
                                }
                            } else {
                                foreach ($quantities as $index => $nb) {
                                    $nb = $one ? 1 : $nb;

                                    if ($display == 'once') {
                                        $defaultPrice = isset($prices[$index][$key][0]) ? $prices[$key][0] : 0;

                                        foreach ($optionsPriceOfferIn[$index][$key] as $ind => $optI) {
                                            $val = isset($prices[$index][$key][$ind]) ? $prices[$index][$key][$ind] : 0;
                                            $price = $nb * $val;
                                            $amount += (double) $price;
                                        }

                                        if (count($optionsPriceOfferIn[$index][$key]) > 1) {
                                            $countLess = count($optionsPriceOfferIn[$index][$key]) - 1;
                                            $amount -= $countLess * $defaultPrice;
                                        }
                                    }
                                }
                            }

                            break;
                        case 'list':
                        case 'oui_non':
                        default:
                            if (!is_array($quantity)) {
                                $valOption  = isAke($optionsPriceOfferIn, $key, $default);
                                $val        = isAke($prices[$key], $valOption, 0);
                                $price      = $quantity * $val;
                                $amount     += (double) $price;
                            } else {
                                foreach ($quantities as $index => $nb) {
                                    if ($display == 'once') {
                                        $valOption  = isAke($optionsPriceOfferIn, $key, $default);
                                        $val        = isAke($prices[$index][$key], $valOption, 0);
                                    } elseif ($display == 'each') {
                                        $valOption = $default;
                                        $val = 0;

                                        if (isset($optionsPriceOfferIn[$index])) {
                                            if (isset($optionsPriceOfferIn[$index][$key])) {
                                                $valOption = $optionsPriceOfferIn[$index][$key];
                                            }
                                        }

                                        if (isset($prices[$index])) {
                                            if (isset($prices[$index][$key])) {
                                                $val = $prices[$index][$key];
                                            }
                                        }
                                    }

                                    $price      = $nb * $val;
                                    $amount     += (double) $price;
                                }
                            }

                            break;
                    }
                }
            }

            return [(double) $amount, $item];
        }

        private function calculateAmountWithMultiple($quantities, $prices, $list, $one = false)
        {
            $amount = 0;
            $item = [];
            $item['products'] = [];

            foreach ($quantities as $index => $nb) {
                $nb     = $one ? 1 : $nb;
                $val    = isset($prices[$index])   ? (double) $prices[$index]  : 0;
                $key    = isset($list[$index])     ? (string) $list[$index]    : $index;
                $price  = $nb * $val;
                $amount += (double) $price;
                $item['products'][$key] = ['quantity' => $nb, 'unit_price' => $val, 'total' => (double) $price];
            }

            return [(double) $amount, $item];
        }

        /* retourne la durée en secondes */
        private function duration($quantity, $unite, $segmentId, $resellerId)
        {
            switch ($unite) {
                case 'heure':
                    return $quantity * 3600;
                case 'minute':
                    return $quantity * 60;
                default:
                    $row = Model::Conversion()
                    ->where(['reseller_id', '=', (int) $resellerId])
                    ->where(['to', '=', 'hour'])
                    ->where(['from', '=', (string) $unite])
                    ->where(['segment_id', '=', (int) $segmentId])
                    ->first(true);

                    if ($row) {
                        $value = $row->value;

                        if (!is_array($quantity)) {
                            return $quantity * $value * 3600;
                        } else {
                            $duration = 0;

                            foreach ($quantity as $index => $nb) {
                                $val = isset($value[$index]) ? $value[$index] : 0;
                                $duration += $nb * $val * 3600;
                            }

                            return $duration;
                        }
                    }
            }

            return 0;
        }

        public function conf($key, $value)
        {
            return Model::Config()
            ->firstOrCreate(['key' => $key, 'value' => $value])
            ->id;
        }

        public function getStaticModel($segment_id)
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
    }
