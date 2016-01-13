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

    class BourseLib
    {
        public function getResellersByOffer(array $offers)
        {
            $resellers = [];

            foreach ($offers as $offer) {
                $zip    = isAke($offer, 'zip', false);
                $market = isAke($offer, 'market', false);

                if (false !== $zip && false !== $market) {
                    $marketResellers    = $this->getResellersByMarket($market);
                    $zipResellers       = $this->getResellersByZip($zip);
                    $customZipResellers = $this->getResellersByCustomZip($zip);

                    $resellers1         = $this->intersect($marketResellers, $zipResellers);
                    $resellers2         = $this->intersect($marketResellers, $customZipResellers);

                    $resellers          = $this->unTuple($resellers1, $resellers2);
                }
            }

            return $resellers;
        }

        public function getEmployeesToNotif(array $resellers, array $offers)
        {
            $collection = $tuples = [];

            foreach ($offers as $offer) {
                $articles = Model::Articlein()->where(['offerin_id', '=', $offer['id']])->exec();

                foreach ($articles as $article) {
                    $item_id = isAke($article, 'item_id', 0);

                    /* on ne traite que les items rattachés à un arbre */
                    if (0 < $item_id) {
                        $family = repo('segment')->getFamilyfromItem($item_id);

                        foreach ($family as $segment) {
                            foreach ($resellers as $reseller) {
                                $id = isAke($reseller, 'id', false);

                                if (false !== $id) {
                                    $relations = Model::Segmentreselleremployee()
                                    ->where(['segment_id', '=', $segment['id']])
                                    ->exec();

                                    if (!empty($relations)) {
                                        foreach ($relations as $relation) {
                                            $re = Model::Reselleremployee()->find($relation['reselleremployee_id']);

                                            if ($re) {
                                                if ($re->reseller_id == $id) {
                                                    $item = [];
                                                    $item['offerin_id']             = $offer['id'];
                                                    $item['reselleremployee_id']    = $relation['reselleremployee_id'];
                                                    $item['reseller_id']            = $id;
                                                    $item['to']['email']            = $re->email_pro;
                                                    $item['to']['lastname']         = $re->lastname;
                                                    $item['to']['firstname']        = $re->firstname;

                                                    $hash = sha1(serialize($item));

                                                    if (!Arrays::in($hash, $tuples)) {
                                                        array_push($collection, $item);
                                                        array_push($tuples, $hash);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            return $collection;
        }

        public function push($employees)
        {
            $mailSend = [];

            foreach ($employees as $employee) {
                $check = Model::Offerinpush()
                ->where(['offerin_id', '=', $employee['offerin_id']])
                ->where(['reselleremployee_id', '=', $employee['reselleremployee_id']])
                ->count();

                if (0 == $check) {
                    $subject    = "Nouvelle offre d'achat";
                    $to         = $employee['to']['email'];
                    $toName     = $employee['to']['firstname'] . ' ' . $employee['to']['lastname'];
                    $from       = 'vendeur@zelift.com';
                    $fromName   = 'ZeLift';
                    $link       = str_replace('https://', 'https://vendeur.', URLSITE) . 'bourse/offer/' . $employee['offerin_id'];
                    $message    = 'Bonjour ' . $toName . ',<br><br>Une nouvelle offre d\'achat correspondant à vos critères vient d\'être émise sur ZeLift.<br><br>Vous pouvez la consulter en suivant <a href="'.$link.'">ce lien</a>.<br><br>Cordialement,<br><br><a href="mailto:support@zelift.com">L\'équipe ZeLift</a>';

                    lib('queue')->pushlib('mail', 'send', [$to, $from, $subject, $message, null, $toName, $fromName]);

                    array_push($mailSend, $to);

                    Model::Offerinpush()->firstOrCreate([
                        'offerin_id'            =>  $employee['offerin_id'],
                        'reselleremployee_id'   =>  $employee['reselleremployee_id'],
                    ]);
                }
            }

            if (!empty($mailSend)) {
                lib('queue')->background();
            }

            return $mailSend;
        }

        public function intersect(array $a, array $b)
        {
            $collection = $ids = [];

            foreach ($a as $row) {
                $id = isAke($row, 'id', false);

                if (false !== $id) {
                    array_push($ids, $id);
                }
            }

            foreach ($b as $row) {
                $id = isAke($row, 'id', false);

                if (false !== $id) {
                    if (Arrays::in($id, $ids)) {
                        array_push($collection, $row);
                    }
                }
            }

            return $collection;
        }

        public function getResellersByCustomZip($zip)
        {
            $collection = [];

            $customCities = Model::Cityzone()->where(['zip', '=', (string) $zip])->exec();

            if (!empty($customCities)) {
                foreach ($customCities as $customCity) {
                    $resellerId = isAke($customCity, 'reseller_id', false);

                    if (false !== $resellerId) {
                        $reseller = Model::Reseller()->find($resellerId);

                        if ($reseller) {
                            array_push($collection, $reseller->assoc());
                        }
                    }
                }
            }

            return $this->unTuple($collection);
        }

        public function getResellersByMarket($market)
        {
            $collection = [];
            $relations = Model::Segmentreseller()->where(['market', '=', $market])->exec();

            foreach ($relations as $relation) {
                $resellerId = isAke($relation, 'reseller_id', false);

                if (false !== $resellerId) {
                    $reseller = Model::Reseller()->find($resellerId);

                    if ($reseller) {
                        array_push($collection, $reseller->assoc());
                    }
                }
            }

            return $this->unTuple($collection);
        }

        public function getResellersByZip($zip)
        {
            $region = $department = null;
            $resellersCity = $resellersRegion = $resellersDepartment = [];

            $city = Model::City()->where(['zip', '=', (string) $zip])->first(true);

            if ($city instanceof ZeliftCityModel) {
                $department = $city->department();
                $region = $city->region();

                $zones = Model::Customzonereseller()
                ->where(['type', '=', 'city'])
                ->where(['type_id', '=', $city->id])
                ->exec();dd($zones);

                $resellersCity = $this->getResellersByZones($zones);
            } else {
                $dpt = substr($zip, 0, 2);
                $department = Model::Department()->where(['code', '=', (string) $dpt])->first(true);

                if ($department) {
                    $region = $department->region();
                }
            }

            if ($region instanceof ZeliftRegionModel) {
                $zones = Model::Customzonereseller()
                ->where(['type', '=', 'region'])
                ->where(['type_id', '=', $region->id])
                ->exec();

                $resellersRegion = $this->getResellersByZones($zones);
            }

            if ($department instanceof ZeliftDepartmentModel) {
                $zones = Model::Customzonereseller()
                ->where(['type', '=', 'department'])
                ->where(['type_id', '=', $department->id])
                ->exec();

                $resellersDepartment = $this->getResellersByZones($zones);
            }

            return $this->unTuple($resellersCity, $resellersRegion, $resellersDepartment);
        }

        public function unTuple()
        {
            $collection = $tuples = [];
            $args = func_get_args();

            foreach ($args as $tab) {
                foreach ($tab as $row) {
                    $id = isAke($row, 'id', false);

                    if (false !== $id) {
                        if (!Arrays::in($id, $tuples)) {
                            array_push($tuples, $id);
                            array_push($collection, $row);
                        }
                    }
                }
            }

            return $collection;
        }

        private function getResellersByZones($zones)
        {
            $collection = [];

            foreach ($zones as $zone) {
                $z = Model::zonereseller()->where(['customzonereseller_id', '=', $zone['id']])->first(true);

                if ($z) {
                    $reseller = $z->reseller(true);

                    if ($reseller) {
                        array_push($collection, $reseller->assoc());
                    }
                }
            }

            return $collection;
        }

        public function getOfferIn($reseller_id, $offerin_id)
        {
            $collection = [];

            $offers = Model::Offerin()->inCache(true)
            ->where(['id', '=', $offerin_id])
            ->exec();

            foreach ($offers as $offer) {
                $item = [];
                $articles = Model::Articlein()->where(['offerin_id', '=', $offer['id']])->exec();

                $item['id'] = $offer['id'];
                $item['date_creation'] = $offer['created_at'];
                $item['date_expiration'] = $offer['expiration'];
                $zip = isAke($offer, 'zip', false);

                if (false !== $zip) {
                    $item['buyer']['zip'] = $zip;
                    $company = Model::Company()->find($offer['company_id']);

                    if ($company) {
                        $item['buyer']['city'] = $company->city;

                        $auth = $this->resellerHasAuth($articles, $reseller_id);
                        $item['auth'] = $auth;
                        $item['buyer']['is_pro'] = $company->is_pro;

                        if ($auth === true) {
                            $item['buyer']['name'] = $company->name;
                        }
                    }

                    $market = jmodel('segment')->find($offer['market']);

                    if ($market) {
                        $item['market']['id'] = $market->id;
                        $item['market']['name'] = $market->name;

                        $data = repo('segment')->getData($market->id);
                        $icon = isAke($data, 'icon', null);
                        $item['market']['icon'] = $icon;
                    }

                    $item['categories'] = $this->getCategories($articles);

                    foreach ($articles as $article) {
                        $it = $article;
                        $seg = jmodel('segment')->find($article['item_id']);

                        if ($seg) {
                            $it['family']       = repo('segment')->getFamilyfromItem($article['item_id']);
                            $it['name']         = $seg->name;
                            $item['articles'][] = $it;
                        }
                    }

                    array_push($collection, $item);
                }
            }

            return current($collection);
        }

        public function getOffersInByCategoryCount($reseller_id)
        {
            $tab = $this->getOffersInByCategory($reseller_id);

            return isAke($tab, 'total', 0);
        }

        public function getOfferInByCategory($reseller_id, $offerin_id, $category_id)
        {
            $db = Model::Offerin();

            $ageDb = $db->getAge();

            $cache = $db->getCache('get.offerbycategory.in');

            $keyAge     = 'age.' . sha1(serialize(func_get_args()));
            $keyData    = 'data.' . sha1(serialize(func_get_args()));

            $cacheAge = $cache->get($keyAge, false);

            if (false !== $cacheAge) {
                if ($ageDb < $cacheAge) {
                    $cacheData = $cache->get($keyData, false);

                    if (false !== $cacheData) {
                        return unserialize($cacheData);
                    }
                }
            }

            $collection = $return = $cats = [];

            $offers = Model::Offerin()->inCache(true)
            ->where(['id', '=', $offerin_id])
            ->exec();

            if (!empty($offers)) {
                foreach ($offers as $offer) {
                    $item = $offer;
                    $item['date_creation']      = $offer['created_at'];
                    $item['date_expiration']    = $offer['expiration'];

                    $articles = Model::Articlein()->where(['offerin_id', '=', $offer['id']])->exec();

                    if (!empty($articles)) {
                        foreach ($articles as $article) {
                            $item_id = isAke($article, 'item_id', 0);

                            if (0 < $item_id) {
                                $family             = repo('segment')->getFamilyfromItem($item_id);
                                $seg                = jmodel('segment')->find($item_id);
                                $cat                = isset($family[1]) ? $family[1] : false;
                                $article['family']  = $family;
                                $article['name']    = $seg->name;
                            } else {
                                $cat           = [];
                                $cat['id']     = 0;
                                $cat['name']   = 'autre';
                                $cat['icon']   = 'fa fa-cubes';
                            }

                            if (false !== $cat) {
                                if ($cat['id'] == $category_id) {
                                    $cats[$cat['name']] = $cat;

                                    $c  = isAke($collection, $cat['name'], []);
                                    $co = isAke($c, 'offer_' . $offer['id'], $item);
                                    $ca = isAke($co, 'articles', []);

                                    $article['qty']     = (float) $article['qty'];
                                    $article['item_id'] = (int) $article['item_id'];

                                    $ca[] = $article;
                                    $co['articles'] = $ca;

                                    $collection[$cat['name']]['offer_' . $offer['id']] = $co;
                                }
                            }
                        }
                    }
                }
            }

            foreach ($offers as $offer) {
                foreach ($collection as $c => $cat) {
                    $of = isAke($cat, 'offer_' . $offer['id'], false);

                    if (false !== $of) {
                        $cTab = isAke($cats, $c, []);
                        $of['category_name']    = isAke($cTab, 'name', $c);
                        $of['category_icon']    = isAke($cTab, 'icon', '');
                        $of['category_id']      = isAke($cTab, 'id', 0);

                        $a = $this->hasAnswered($offer['id'], $of['category_id'], $reseller_id);

                        if (false === $a) {
                            $of['is_answered'] = false;
                        } else {
                            $of['is_answered'] = true;
                            $of['answerer'] = is_object($a) ? $a->assoc() : [];
                        }

                        $return[] = $of;
                    }
                }
            }

            $cache->set($keyData, serialize(Arrays::first($return)));
            $cache->set($keyAge, time());

            return Arrays::first($return);
        }

        public function getOffersInByCategory($reseller_id, $options = [])
        {
            $db = Model::Offerin();

            $ageDb = $db->getAge();

            $cache = $db->getCache('get.offersbycategories.in');

            $keyAge     = 'age.' . sha1($reseller_id . serialize($options));
            $keyData    = 'data.' . sha1($reseller_id . serialize($options));

            $cacheAge = $cache->get($keyAge, false);

            if (false !== $cacheAge) {
                if ($ageDb < $cacheAge) {
                    $cacheData = $cache->get($keyData, false);

                    if (false !== $cacheData) {
                        return unserialize($cacheData);
                    }
                }
            }

            $tab        = $this->getOffersIn($reseller_id, $options);
            $offers     = isAke($tab, 'offers', []);
            $collection = $return = $cats = [];

            if (!empty($offers)) {
                foreach ($offers as $offer) {
                    $item = $offer;

                    // unset($item['categories']);
                    unset($item['articles']);

                    $articles = isAke($offer, 'articles', []);

                    if (!empty($articles)) {
                        foreach ($articles as $article) {
                            $item_id = isAke($article, 'item_id', 0);

                            if (0 < $item_id) {
                                $family = repo('segment')->getFamilyfromItem($item_id);
                                $cat    = isset($family[1]) ? $family[1] : false;
                            } else {
                                $cat           = [];
                                $cat['id']     = 0;
                                $cat['name']   = 'autre';
                                $cat['icon']   = 'fa fa-cubes';
                            }

                            if (false !== $cat) {
                                $cats[$cat['name']] = $cat;

                                $c  = isAke($collection, $cat['name'], []);
                                $co = isAke($c, 'offer_' . $offer['id'], $item);
                                $ca = isAke($co, 'articles', []);

                                $article['qty']     = (float) $article['qty'];
                                $article['item_id'] = (int) $article['item_id'];

                                $ca[] = $article;
                                $co['articles'] = $ca;

                                $collection[$cat['name']]['offer_' . $offer['id']] = $co;
                            }
                        }
                    }
                }
            }

            foreach ($offers as $offer) {
                foreach ($collection as $c => $cat) {
                    $of = isAke($cat, 'offer_' . $offer['id'], false);

                    if (false !== $of) {
                        $cTab = isAke($cats, $c, []);
                        $of['category_name']    = isAke($cTab, 'name', $c);
                        $of['category_icon']    = isAke($cTab, 'icon', '');
                        $of['category_id']      = isAke($cTab, 'id', 0);

                        $a = $this->hasAnswered($offer['id'], $of['category_id'], $reseller_id);

                        if (false === $a) {
                            $of['is_answered'] = false;
                        } else {
                            $of['is_answered'] = true;
                            $of['answerer'] = is_object($a) ? $a->assoc() : [];
                        }

                        $return[] = $of;
                    }
                }
            }

            $total = count($return);

            $offset = isAke($options, 'offset', false);
            $limit  = isAke($options, 'limit', false);

            if (false !== $offset && false !== $limit) {
                $return = array_slice($return, $offset, $limit);
            }

            $cache->set($keyData, serialize(['total' => $total, 'offers' => $return]));
            $cache->set($keyAge, time());

            return ['total' => $total, 'offers' => $return];
        }

        public function hasAnswered($offerin_id, $category_id, $reseller_id)
        {
            $a = Model::Offerout()
            ->where(['reseller_id', '=', $reseller_id])
            ->where(['offerin_id', '=', $offerin_id])
            ->where(['category', '=', $category_id])->first(true);

            return !empty($a) ? Model::ResellerEmployee()->find($a->reselleremployee_id) : false;

            // Model::offerincategory()->firstOrCreate([
            //     'reseller_id' => $reseller_id,
            //     'offerin_id' => $offerin_id,
            //     'category' => $category,
            // ]);
        }

        public function getOffersIn($reseller_id, $options = [])
        {
            $collection = [];

            $db = Model::Offerin();

            $ageDb = $db->getAge();

            $cache = $db->getCache('get.offers.in');

            $keyAge     = 'age.' . sha1($reseller_id . serialize($options));
            $keyData    = 'data.' . sha1($reseller_id . serialize($options));

            $cacheAge = $cache->get($keyAge, false);

            if (false !== $cacheAge) {
                if ($ageDb < $cacheAge) {
                    $cacheData = $cache->get($keyData, false);

                    if (false !== $cacheData) {
                        return unserialize($cacheData);
                    }
                }
            }

            $offers = $db->inCache(true)
            ->where(['status_id', '=', getStatus('OK')])
            ->where(['expiration', '>', time()])
            ->order('created_at', 'DESC')
            ->exec();

            $total  = count($offers);

            foreach ($offers as $offer) {
                $item       = [];
                $articles   = Model::Articlein()->where(['offerin_id', '=', $offer['id']])->exec();

                $item['id']                 = $offer['id'];
                $item['date_creation']      = $offer['created_at'];
                $item['date_expiration']    = $offer['expiration'];
                $zip                        = isAke($offer, 'zip', false);

                if (false !== $zip) {
                    $item['buyer']['zip'] = $zip;
                    $company = Model::Company()->find($offer['company_id']);

                    if ($company) {
                        $item['buyer']['city']      = $company->city;

                        $auth                       = $this->resellerHasAuth($articles, $reseller_id);
                        $item['auth']               = $auth;
                        $item['buyer']['is_pro']    = $company->is_pro;
                        $item['buyer']['name']      = $company->name;
                    }

                    $market = jmodel('segment')->find($offer['market']);

                    if ($market) {
                        $item['market']['id']       = $market->id;
                        $item['market']['name']     = $market->name;

                        $data                       = repo('segment')->getData($market->id);
                        $icon                       = isAke($data, 'icon', null);
                        $item['market']['icon']     = $icon;
                    }

                    // $item['categories'] = $this->getCategories($articles);

                    foreach ($articles as $article) {
                        $it     = $article;
                        $seg    = jmodel('segment')->find($article['item_id']);

                        if ($seg) {
                            $it['name']         = $seg->name;
                            $item['articles'][] = $it;
                        } else {
                            if ($article['item_id'] == 0) {
                                $item['articles'][] = $it;
                            }
                        }
                    }

                    array_push($collection, $item);
                }
            }

            $cache->set($keyData, serialize(['total' => $total, 'offers' => $collection]));
            $cache->set($keyAge, time());

            return ['total' => $total, 'offers' => $collection];
        }

        private function getCategories($articles)
        {
            $collection = $tuples = [];

            foreach ($articles as $article) {
                $item_id = isAke($article, 'item_id', 0);

                if (0 < $item_id) {
                    $family = repo('segment')->getFamilyfromItem($item_id);
                    $cat    = isset($family[1]) ? $family[1] : false;

                    if (false !== $cat) {
                        $item = [];
                        $item['id']     = $cat['id'];
                        $item['name']   = $cat['name'];
                        $item['icon']   = $cat['icon'];

                        if (!Arrays::in($cat['id'], $tuples)) {
                            array_push($collection, $item);
                            array_push($tuples, $cat['id']);
                        }
                    }
                } else {
                    $item           = [];
                    $item['id']     = 0;
                    $item['name']   = 'autre';
                    $item['icon']   = 'fa fa-cubes';

                    if (!Arrays::in('o', $tuples)) {
                        array_push($collection, $item);
                        array_push($tuples, 'o');
                    }
                }
            }

            return $collection;
        }

        private function resellerHasAuth($articles, $reseller)
        {
            foreach ($articles as $article) {
                $item_id = isAke($article, 'item_id', 0);

                if (0 < $item_id) {
                    $family = repo('segment')->getFamilyfromItem($item_id);
                    $cat    = isset($family[1]) ? $family[1] : false;

                    if (false !== $cat) {
                        $check = Model::Segmentreseller()
                        ->where(['reseller_id', '=', $reseller])
                        ->where(['segment_id', '=', $cat['id']])
                        ->count();

                        if ($check > 0) {
                            return true;
                        }
                    }
                }
            }

            return false;
        }

        public function setOfferOutPrice($offerout_id, $reselleremployee_id, array $articles)
        {
            $reselleremployee   = Model::Reselleremployee()->inCache(true)->find($reselleremployee_id);
            $offer              = Model::Offerout()->inCache(true)->find($offerout_id);

            if ($reselleremployee && $offer) {
                $can = habilitationBelongsToEmployee(
                    getHabilitation('ENVOYER_OFFRE_OUT'),
                    $reselleremployee_id,
                    $reselleremployee->reseller_id
                );

                if ($can) {
                    foreach ($articles as $article) {
                        $idA    = isAke($article, 'id', false);

                        if (false === $idA) {
                            return 'id article inexistant';
                        }

                        $a = Model::Articleout()->inCache(true)->find($idA);

                        if ($a) {
                            $comment    = isAke($article, 'comment', '');
                            $price      = isAke($article, 'price', 0);

                            $a->setComment($comment)->setPrice($price)->save();

                        } else {
                            return 'article inexistant';
                        }
                    }

                    $offer = $offer->setStatusId(getStatus('SENT'))->save();

                    return $offer->id;
                } else {
                    return 'mauvaise habilitation';
                }
            } else {
                if ($reselleremployee) {
                    return 'offre inexistante';
                }

                if ($offer) {
                    return 'utilisateur inexistant';
                }
            }
        }

        public function setOfferOut($offerin_id, $category, $reselleremployee_id, array $articles)
        {
            $reselleremployee   = Model::Reselleremployee()->find($reselleremployee_id);
            $offerin            = Model::Offerin()->find($offerin_id);

            if ($reselleremployee && $offerin) {
                $check = Model::Offerout()
                ->where(['reselleremployee_id', '=', $reselleremployee_id])
                ->where(['offerin_id', '=', $offerin_id])
                ->where(['category', '=', $category])
                ->count();

                if ($check > 0) {
                    return 0;
                }

                $offer = Model::Offerout()->create([
                    'status_id'             => getStatus('NOT_COMPLETED'),
                    'reseller_id'           => $reselleremployee->reseller_id,
                    'reselleremployee_id'   => $reselleremployee_id,
                    'offerin_id'            => $offerin_id,
                    'category'              => $category,
                    'zip'                   => $offerin->zip,
                    'company_id'            => $offerin->company_id,
                    'people_id'             => $offerin->people_id,
                    'date_offerin'          => $offerin->date,
                    'expiration_offerin'    => $offerin->expiration,
                    'global'                => $offerin->global,
                    'statusofferin'         => $offerin->status_id,
                    'market'                => $offerin->market,
                    'universe'              => $offerin->universe
                ])->save();

                foreach ($articles as $articlein_id) {
                    $article = Model::Articlein()->find($articlein_id);

                    if ($article) {
                        if ($article->offerin_id == $offerin_id) {
                            $a = $article->assoc();

                            unset($a['id']);
                            unset($a['created_at']);
                            unset($a['updated_at']);
                            unset($a['deleted_at']);

                            $a['offerout_id']   = $offer->id;
                            $a['price']         = 0;

                            if ($a['item_id'] > 0) {
                                $seg    = jmodel('segment')->find($a['item_id']);

                                if ($seg) {
                                    $a['name'] = $seg->name;
                                }
                            }

                            Model::Articleout()->create($a)->save();
                        } else {
                            $offer->delete();

                            return 'Article inexistant';
                        }
                    } else {
                        $offer->delete();

                        return 'Article inexistant';
                    }
                }

                return $offer->id;
            } else {
                return 'offre inconnue';
            }
        }

        public function getOfferOut($reseller_id, $offerout_id)
        {
            $collection = [];

            $offers = Model::Offerout()
            ->where(['id', '=', $offerout_id])
            ->exec();

            foreach ($offers as $offer) {
                $item = [];
                $articles = Model::Articleout()->where(['offerout_id', '=', $offer['id']])->exec();

                $item['id']                         = $offer['id'];
                $item['status_id']                  = $offer['status_id'];
                $item['date_creation']              = $offer['created_at'];
                $item['date_expiration_offerin']    = $offer['expiration_offerin'];
                $zip                                = isAke($offer, 'zip', false);

                if (false !== $zip) {
                    $item['buyer']['zip']   = $zip;
                    $company                = Model::Company()->find($offer['company_id']);

                    if ($company) {
                        $item['buyer']['city']      = $company->city;
                        $item['buyer']['is_pro']    = $company->is_pro;
                        $item['buyer']['is_pro']    = $company->is_pro;
                        $item['buyer']['name']      = $company->name;
                    }

                    $market = jmodel('segment')->find($offer['market']);

                    if ($market) {
                        $item['market']['id']   = $market->id;
                        $item['market']['name'] = $market->name;

                        $data                   = repo('segment')->getData($market->id);
                        $icon                   = isAke($data, 'icon', null);
                        $item['market']['icon'] = $icon;
                    }

                    $item['category_id'] = $offer['category'];

                    if ($offer['category'] > 0) {
                        $segCat                 = jmodel('segment')->find($offer['category']);

                        $item['category_name']  = $segCat->name;
                        $data                   = repo('segment')->getData($segCat->id);
                        $icon                   = isAke($data, 'icon', null);
                        $item['category_icon']  = $icon;
                    } else {
                        $item['category_name']  = 'autre';
                        $item['category_icon']  = 'fa fa-cubes';
                    }

                    foreach ($articles as $article) {
                        $it = $article;
                        $seg = jmodel('segment')->find($article['item_id']);

                        if ($seg) {
                            $it['family']       = repo('segment')->getFamilyfromItem($article['item_id']);
                            $it['name']         = $seg->name;
                            $item['articles'][] = $it;
                        } else {
                            if ($article['item_id'] == 0) {
                                $item['articles'][] = $article;
                            }
                        }
                    }

                    array_push($collection, $item);
                }
            }

            return current($collection);
        }
    }
