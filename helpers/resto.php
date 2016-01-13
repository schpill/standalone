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

    class RestoLib
    {
        public static $datas = [];

        public function addProduct($reseller_id, $segment_id, $data = [], $nonAuto = [])
        {
            $count = Model::Catalog()
            ->where(['is_resto', '=', 1])
            ->where(['reseller_id', '=', (int) $reseller_id])
            ->where(['segment_id', '=', (int) $segment_id])
            ->where(['name', '=', isAke($data, 'name', 'plat')])
            ->where(['description', '=', isAke($data, 'description', 'description')])
            ->cursor()
            ->count();

            if ($count > 0) {
                return 0;
            }

            $product = Model::Catalog()->firstOrCreate([
                'is_resto'      => 1,
                'reseller_id'   => (int) $reseller_id,
                'segment_id'    => (int) $segment_id,
                'description'   => (string) isAke($data, 'description', 'description'),
                'name'          => (string) isAke($data, 'name', 'plat')
            ]);

            foreach ($data as $k => $v) {
                $product->$k = $v;
            }

            $product->is_challenge = 1;

            $product = $product->save();

            if (!empty($nonAuto)) {
                $this->assocProductWithTypeNonAuto((int) $product->id, $nonAuto);
            }

            return (int) $product->id;
        }

        public function editProduct($reseller_id, $catalog_id, $data = [], $nonAuto = [])
        {
            $product = Model::Catalog()
            ->where(['reseller_id', '=', (int) $reseller_id])
            ->where(['id', '=', (int) $catalog_id])
            ->first(true);

            if ($product) {
                foreach ($data as $k => $v) {
                    $product->$k = $v;
                }

                $product = $product->save();

                if (!empty($nonAuto)) {
                    $this->assocProductWithTypeNonAuto((int) $product->id, $nonAuto);
                }

                return (int) $product->id;
            } else {
                return false;
            }
        }

        public function isChallenge($val, $catalog_id)
        {
            $product = Model::Catalog()->find((int) $catalog_id);

            if ($product) {
                $product->is_challenge = $val;
                $product->challenge_update = time();
                $product = $product->save();

                return (int) $product->id;
            }

            return false;
        }

        public function getProductsFromGeo($resto_geo_id)
        {
            $collection = [];

            if (!is_integer($resto_geo_id)) {
                return $collection;
            }

            $ids        = repo('segment')->getAllFamilyIds((int) $resto_geo_id);
            $ids[]      = $resto_geo_id;

            $segments   = Model::Mealgeo()
            ->where(['resto_geo_id', 'IN', implode(',', $ids)])
            ->cursor();

            foreach ($segments as $segment) {
                $products = Model::Catalog()
                ->where(['is_resto', '=', 1])
                ->where(['is_challenge', '=', 1])
                ->where(['segment_id', '=', (int) $segment['segment_id']])
                ->cursor();

                foreach ($products as $product) {
                    unset($product['_id']);
                    $collection[] = $product;
                }
            }

            return $collection;
        }

        public function getProductsFromType($resto_type_id)
        {
            $collection = [];

            if (!is_integer($resto_type_id)) {
                return $collection;
            }

            $ids        = repo('segment')->getAllFamilyIds((int) $resto_type_id);
            $ids[]      = $resto_type_id;

            $segments   = Model::Mealtype()->where(['resto_type_id', 'IN', implode(',', $ids)])->cursor();

            foreach ($segments as $segment) {
                $products = Model::Catalog()
                ->where(['is_resto', '=', 1])
                ->where(['is_challenge', '=', 1])
                ->where(['segment_id', '=', (int) $segment['segment_id']])
                ->cursor();

                foreach ($products as $product) {
                    unset($product['_id']);
                    $collection[] = $product;
                }
            }

            return $collection;
        }

        public function assocProductWithTypeNonAuto($catalog_id, $nonauto_ids = [])
        {
            Model::Mealnonauto()->where(['catalog_id', '=', (int) $catalog_id])->get(true)->delete();

            foreach ($nonauto_ids as $nonauto_id) {
                $row = Model::Mealnonauto()->create([
                    'catalog_id'        => (int) $catalog_id,
                    'resto_nonauto_id'  => (int) $nonauto_id
                ])->save();
            }

            return true;
        }

        public function getAssocNonAutoByCatalogId($catalog_id, $segment_id)
        {
            if (is_null($catalog_id)) {
                return [];
            }

            $father         = repo('segment')->getFather($segment_id);
            $datasSegment   = repo('segment')->getData($segment_id);

            if (fnmatch('*aurant*', strtolower($father['name']))) {
                $context = 'resto';
            } elseif (fnmatch('*nack*', strtolower($father['name']))) {
                $context = 'snack';
            } elseif (fnmatch('*vin*', strtolower($father['name']))) {
                $context = 'vin';
            } elseif (fnmatch('*envie*', strtolower($father['name']))) {
                $context = 'envies';
            } else {
                $context = isAke($datasSegment, 'context', 'resto');
            }

            if ($context == 'snack') {
                $family             = repo('segment')->getFamily($segment_id);
                $seg                = isset($family[1]) ? $family[1] : [];
                $isPetitesenvies    = fnmatch('*nvies*', isAke($seg, 'name', ''));

                if ($isPetitesenvies) $context = 'envies';
            }

            $ids = [];

            $cursor = Model::Mealnonauto()->where(['catalog_id', '=', (int) $catalog_id])->cursor();

            foreach ($cursor as $row) {
                $fatherSegment = repo('segment')->getFather((int) $row['resto_nonauto_id']);

                if (fnmatch('*aurant*', strtolower($fatherSegment['name']))) {
                    $contextSegment = 'resto';
                } elseif (fnmatch('*nack*', strtolower($fatherSegment['name']))) {
                    $contextSegment = 'snack';
                } elseif (fnmatch('*vin*', strtolower($fatherSegment['name']))) {
                    $contextSegment = 'vin';
                } elseif (fnmatch('*nvies*', strtolower($fatherSegment['name']))) {
                    $contextSegment = 'envies';
                }

                if ($contextSegment != $context) continue;

                $ids[] = (int) $row['resto_nonauto_id'];
            }

            return $ids;
        }

        public function getProductsFromTypeNonAuto($resto_nonauto_id)
        {
            $collection = [];

            if (!is_integer($resto_nonauto_id)) {
                return $collection;
            }

            $ids        = repo('segment')->getAllFamilyIds((int) $resto_nonauto_id);
            $ids[]      = $resto_nonauto_id;

            $assocs     = Model::Mealnonauto()->where(['resto_nonauto_id', 'IN', implode(',', $ids)])->exec(true);

            foreach ($assocs as $assoc) {
                $collection[] = $assoc->catalog();
            }

            return $collection;
        }

        public function getOpenSchedules($reseller_id)
        {
            $collection = [];
            $reseller = Model::Reseller()->find((int) $reseller_id);

            $days = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
            $when = ['am_start', 'am_end', 'pm_start', 'pm_end'];

            if ($reseller) {
                if (!empty($reseller->sellzone_id)) {
                    $sellzone = ['id' => $reseller->sellzone_id];
                } else {
                    $sellzone = lib('pivot')->getSellzone($reseller);
                }

                if (!empty($sellzone)) {
                    $sellzone_id = isAke($sellzone, 'id', false);

                    if (false !== $sellzone_id) {
                        $options = Model::Optionsrestaurant()
                        ->where(['reseller_id', '=', (int) $reseller->id])
                        ->where(['sellzone_id', '=', (int) $sellzone['id']])
                        ->first(true);

                        if ($options) {
                            $pattern = 'horaires_ouverture_##day##_##when##';

                            foreach ($days as $day) {
                                if (!isset($collection[$day])) {
                                    $collection[$day] = [];
                                }

                                foreach ($when as $moment) {
                                    $key = str_replace(['##day##', '##when##'], [$day, $moment], $pattern);
                                    $collection[$day][$moment] = str_replace(':', '_', $options->$key);
                                }
                            }
                        }
                    }
                }
            }

            return $collection;
        }

        public function getServicesSchedules($reseller_id, $type = 'sur_place')
        {
            $keyCache       = 'getServicesSchedules.' . $reseller_id . '.' . $type;
            $keyCacheAge    = 'age.getServicesSchedules.' . $reseller_id . '.' . $type;
            $maxAge         = Model::Optionsrestaurant()->getAge();

            $cached = redis()->get($keyCache);
            $cachedAge = redis()->get($keyCacheAge);

            $getInCache = $cachedAge ? $maxAge < $cachedAge : false;

            if ($getInCache && $cached) {
                return unserialize($cached);
            } else {
                $collection = [];
                $reseller = Model::Reseller()->find((int) $reseller_id);

                $days = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
                $when = ['am_start', 'am_end', 'pm_start', 'pm_end'];

                if ($reseller) {
                    if (!empty($reseller->sellzone_id)) {
                        $sellzone = ['id' => $sellzone_id];
                    } else {
                        $sellzone = lib('pivot')->getSellzone($reseller);
                    }

                    if (!empty($sellzone)) {
                        $sellzone_id = isAke($sellzone, 'id', false);

                        if (false !== $sellzone_id) {
                            $options = Model::Optionsrestaurant()
                            ->where(['reseller_id', '=', (int) $reseller->id])
                            ->where(['sellzone_id', '=', (int) $sellzone['id']])
                            ->first(true);

                            if ($options) {
                                $pattern = 'type_restauration_' . strtolower($type) . '_horaires_services_##day##_##when##';

                                foreach ($days as $day) {
                                    if (!isset($collection[$day])) {
                                        $collection[$day] = [];
                                    }

                                    foreach ($when as $moment) {
                                        $key = str_replace(['##day##', '##when##'], [$day, $moment], $pattern);
                                        $collection[$day][$moment] = str_replace(':', '_', $options->$key);
                                    }
                                }
                            }
                        }
                    }
                }

                redis()->set($keyCache, serialize($collection));
                redis()->set($keyCacheAge, time());

                return $collection;
            }
        }

        public function getInfosZone($sellzone_id = 1)
        {
            $k = 'getInfosZone.' . $sellzone_id . '.' . Model::Restodata()->getAge();
            $ir = redis()->get($k);

            if (!$ir) {
                $rds = Model::Restodata()->where(['sellzone_id', '=', (int) $sellzone_id])->cursor();

                $ir = [];

                foreach ($rds as $resto) {
                    $reseller_id                                = isAke($resto, 'reseller_id', 1);
                    $ir[$reseller_id]['types_auto']             = $resto['auto']['dispos'];
                    $ir[$reseller_id]['types_non_auto']         = $resto['non_auto']['dispos'];
                    $ir[$reseller_id]['specialites']            = $resto['specialites']['dispos'];
                    $ir[$reseller_id]['specialites_families']   = $resto['specialites']['families'];
                    $ir[$reseller_id]['plats_families']         = $resto['plats']['families'];
                    $ir[$reseller_id]['pois']                   = $resto['pois']['dispos'];
                    $ir[$reseller_id]['pois_families']          = $resto['pois']['families'];
                    $ir[$reseller_id]['plats_catalog']          = array_keys($resto['plats']['catalog']);
                }

                redis()->set($k, serialize($ir));
            } else {
                $ir = unserialize($ir);
            }

            return $ir;
        }

        public function getPlatsByZone($sellzone_id = 1)
        {
            $segments = [];

            $datas = $this->getInfosZone($sellzone_id);

            foreach ($datas as $reseller_id => $tab) {
                $plats_families = isAke($tab, 'plats_families', []);
                $plats_catalog  = isAke($tab, 'plats_catalog', []);

                foreach ($plats_families as $id) {
                    if (!isset($segments[$id])) {
                        $segments[$id] = 1;
                    } else {
                        $segments[$id]++;
                    }
                }

                foreach ($plats_catalog as $id) {
                    if (!isset($segments[$id])) {
                        $segments[$id] = 1;
                    } else {
                        $segments[$id]++;
                    }
                }
            }

            return $segments;
        }

        public function getSpecialitesByZone($sellzone_id = 1)
        {
            $segments = [];

            $datas = $this->getInfosZone($sellzone_id);

            foreach ($datas as $reseller_id => $tab) {
                $specialites = isAke($tab, 'specialites', []);
                $specialites_families  = isAke($tab, 'specialites_families', []);

                foreach ($specialites_families as $id) {
                    if (!isset($segments[$id])) {
                        $segments[$id] = 1;
                    } else {
                        $segments[$id]++;
                    }
                }

                foreach ($specialites as $id) {
                    if (!isset($segments[$id])) {
                        $segments[$id] = 1;
                    } else {
                        $segments[$id]++;
                    }
                }
            }

            return $segments;
        }

        public function globalSearch($q, $sellzone_id = 1, $limit = 10, $offset = 0)
        {
            $plats      = $this->searchPlats($q, $sellzone_id);
            $snackings  = $this->searchSnackings($q, $sellzone_id);
            $vins       = $this->searchVins($q, $sellzone_id);

            $collection = [];

            foreach ($plats as $plat) {
                $collection[] = $plat;
            }

            if (!empty($snackings)) {
                foreach ($snackings as $snacking) {
                    $collection[] = $snacking;
                }
            }

            if (!empty($vins)) {
                foreach ($vins as $vin) {
                    $collection[] = $vin;
                }
            }


            $collection = $this->orderSearch($collection, $q);

            return array_slice($collection, $offset, $limit);
        }

        public function searchPetitsCreux($q, $sellzone_id = 1, $limit = 10, $offset = 0)
        {
            $plats = $this->getPlatsByZone($sellzone_id);
            $keyCache = 'restos.researches.petitscreux.' . sha1(serialize(func_get_args()));

            $collection = redis()->get($keyCache);

            if (!$collection) {
                $subColl = [];

                $father = Model::Segment()
                ->where(['segmenttype_id', '=', 4])
                ->where(['name', '=', 'Resto'])
                ->first(true);

                if ($father) {
                    $children = repo('segment')->getAllFamily(1941);

                    foreach ($children as $k => $child) {
                        $isSearchable       = repo('segment')->isSearchable((int) $child['id']);
                        $isItem             = repo('segment')->isItem((int) $child['id']);

                        $child['is_item']   = $isItem;

                        $comp = Inflector::lower(
                            Inflector::unaccent($q)
                        );

                        $value = Inflector::lower(
                            Inflector::unaccent($child['name'])
                        );

                        $check = fnmatch("*$comp*", $value);

                        if ($check) {
                            if ($isItem) {
                                unset($child['hash']);
                                unset($child['updated_at']);
                                unset($child['created_at']);

                                $child['nb_etablissement'] = isset($plats[$child['id']]) ? $plats[$child['id']] : 0;
                                // $child['nb_etablissement'] = Model::Catalog()
                                // ->where(['segment_id', '=', (int) $child['id']])
                                // ->cursor()
                                // ->count();

                                $child['arbre_type'] = 'petit_creux';
                                $subColl[] = $child;
                            } else {
                                if ($child['segment_id'] != $father->id) {
                                    if ($isSearchable) {
                                        unset($child['hash']);
                                        unset($child['updated_at']);
                                        unset($child['created_at']);

                                        $sons = repo('segment')
                                        ->getAllFamily((int) $child['id']);

                                        $total = 0;

                                        foreach ($sons as $son) {
                                            $total += isset($plats[$son['id']]) ? $plats[$son['id']] : 0;
                                            // $total += Model::Catalog()
                                            // ->where(['segment_id', '=', (int) $son['id']])
                                            // ->cursor()
                                            // ->count();
                                        }

                                        $child['nb_etablissement'] = $total;
                                        $child['arbre_type'] = 'petit_creux';
                                        $subColl[] = $child;
                                    }
                                }
                            }
                        }
                    }

                    $collection = $this->orderSearch($subColl, $q);
                    redis()->set($keyCache, serialize($collection));
                    redis()->expire($keyCache, 84600);
                }
            } else {
                $collection = unserialize($collection);
            }

            if (!$collection) {
                $collection = [];
            }

            return array_slice($collection, $offset, $limit);
        }

        public function searchPlats($q, $sellzone_id = 1, $limit = 10, $offset = 0)
        {
            $plats = $this->getPlatsByZone($sellzone_id);
            $keyCache = 'restos.researches.plates.' . sha1(serialize(func_get_args()));

            $collection = redis()->get($keyCache);

            if (!$collection) {
                $subColl = [];

                $father = Model::Segment()
                ->where(['segmenttype_id', '=', 4])
                ->where(['name', '=', 'Resto'])
                ->first(true);

                if ($father) {
                    $children = repo('segment')->getAllFamily((int) $father->id);

                    foreach ($children as $k => $child) {
                        $isSearchable       = repo('segment')->isSearchable((int) $child['id']);
                        $isItem             = repo('segment')->isItem((int) $child['id']);

                        $child['is_item']   = $isItem;

                        $comp   = Inflector::lower(
                            Inflector::unaccent($q)
                        );

                        $value  = Inflector::lower(
                            Inflector::unaccent($child['name'])
                        );

                        $check = fnmatch("*$comp*", $value);

                        if ($check) {
                            if ($isItem) {
                                unset($child['hash']);
                                unset($child['updated_at']);
                                unset($child['created_at']);

                                $child['nb_etablissement'] = isset($plats[$child['id']]) ? $plats[$child['id']] : 0;
                                // $child['nb_etablissement'] = Model::Catalog()
                                // ->where(['segment_id', '=', (int) $child['id']])
                                // ->cursor()
                                // ->count();

                                $child['arbre_type'] = 'resto';
                                $subColl[] = $child;
                            } else {
                                if ($child['segment_id'] != $father->id) {
                                    if ($isSearchable) {
                                        unset($child['hash']);
                                        unset($child['updated_at']);
                                        unset($child['created_at']);

                                        $sons = repo('segment')
                                        ->getAllFamily((int) $child['id']);

                                        $total = 0;

                                        foreach ($sons as $son) {
                                            $total += isset($plats[$son['id']]) ? $plats[$son['id']] : 0;
                                            // $total += Model::Catalog()
                                            // ->where(['segment_id', '=', (int) $son['id']])
                                            // ->cursor()
                                            // ->count();
                                        }

                                        $child['nb_etablissement'] = $total;
                                        $child['arbre_type'] = 'resto';
                                        $subColl[] = $child;
                                    }
                                }
                            }
                        }
                    }

                    $collection = $this->orderSearch($subColl, $q);
                    redis()->set($keyCache, serialize($collection));
                    redis()->expire($keyCache, 84600);
                }
            } else {
                $collection = unserialize($collection);
            }

            if (!$collection) {
                $collection = [];
            }

            return array_slice($collection, $offset, $limit);
        }

        public function searchVins($q, $sellzone_id = 1, $limit = 10, $offset = 0)
        {
            $plats = $this->getPlatsByZone($sellzone_id);
            $keyCache = 'restos.researchers.vinsetchampagnes.' . sha1(serialize(func_get_args()));

            $collection = redis()->get($keyCache);

            if (!$collection) {
                $subColl = [];

                $father = Model::Segment()->find(2026);

                if ($father) {
                    $children = repo('segment')->getAllFamily((int) $father->id);

                    foreach ($children as $k => $child) {
                        $isSearchable   = repo('segment')->isSearchable((int) $child['id']);
                        $isItem         = repo('segment')->isItem((int) $child['id']);

                        $child['is_item'] = $isItem;

                        $comp   = Inflector::lower(
                            Inflector::unaccent($q)
                        );

                        $value  = Inflector::lower(
                            Inflector::unaccent($child['name'])
                        );

                        $check = fnmatch("*$comp*", $value);

                        if ($check) {
                            if ($isItem) {
                                unset($child['hash']);
                                unset($child['updated_at']);
                                unset($child['created_at']);
                                $child['nb_etablissement'] = isset($plats[$child['id']]) ? $plats[$child['id']] : 0;
                                // $child['nb_etablissement'] = Model::Catalog()
                                // ->where(['segment_id', '=', $child['id']])
                                // ->cursor()
                                // ->count();
                                $child['arbre_type'] = 'vin';
                                $subColl[] = $child;
                            } else {
                                if ($child['segment_id'] != $father->id) {
                                    if ($isSearchable) {
                                        unset($child['hash']);
                                        unset($child['updated_at']);
                                        unset($child['created_at']);

                                        $sons = repo('segment')->getAllFamily((int) $child['id']);

                                        $total = 0;

                                        foreach ($sons as $son) {
                                            $total += isset($plats[$son['id']]) ? $plats[$son['id']] : 0;
                                            // $total += Model::Catalog()->where(['segment_id', '=', (int) $son['id']])->cursor()->count();
                                        }

                                        $child['nb_etablissement'] = $total;
                                        $child['arbre_type'] = 'vin';

                                        $subColl[] = $child;
                                    }
                                }
                            }
                        }
                    }

                    $collection = $this->orderSearch($subColl, $q);
                    redis()->set($keyCache, serialize($collection));
                    redis()->expire($keyCache, 84600);
                }
            } else {
                $collection = unserialize($collection);
            }

            if (!$collection) {
                $collection = [];
            }

            return array_slice($collection, $offset, $limit);
        }

        public function searchSnackings($q, $sellzone_id = 1, $limit = 10, $offset = 0)
        {
            $plats = $this->getPlatsByZone($sellzone_id);
            $keyCache = 'restos.researchers.snacks.' . sha1(serialize(func_get_args()));

            $collection = redis()->get($keyCache);

            if (!$collection) {
                $subColl = [];

                $father = Model::Segment()
                ->where(['segmenttype_id', '=', 4])
                ->where(['name', '=', 'Snack'])
                ->first(true);

                if ($father) {
                    $children = repo('segment')->getAllFamily((int) $father->id);

                    foreach ($children as $k => $child) {
                        $isSearchable = repo('segment')->isSearchable((int) $child['id']);
                        $isItem = repo('segment')->isItem((int) $child['id']);

                        $child['is_item'] = $isItem;

                        $comp   = Inflector::lower(
                            Inflector::unaccent($q)
                        );

                        $value  = Inflector::lower(
                            Inflector::unaccent($child['name'])
                        );

                        $check = fnmatch("*$comp*", $value);

                        if ($check) {
                            if ($isItem) {
                                unset($child['hash']);
                                unset($child['updated_at']);
                                unset($child['created_at']);
                                $child['nb_etablissement'] = isset($plats[$child['id']]) ? $plats[$child['id']] : 0;
                                // $child['nb_etablissement'] = Model::Catalog()->where(['segment_id', '=', $child['id']])->cursor()->count();
                                $child['arbre_type'] = 'snack';
                                $subColl[] = $child;
                            } else {
                                if ($child['segment_id'] != $father->id) {
                                    if ($isSearchable) {
                                        unset($child['hash']);
                                        unset($child['updated_at']);
                                        unset($child['created_at']);

                                        $sons = repo('segment')->getAllFamily((int) $child['id']);

                                        $total = 0;

                                        foreach ($sons as $son) {
                                            $total += isset($plats[$son['id']]) ? $plats[$son['id']] : 0;
                                            // $total += Model::Catalog()->where(['segment_id', '=', (int) $son['id']])->cursor()->count();
                                        }

                                        $child['nb_etablissement'] = $total;
                                        $child['arbre_type'] = 'snack';

                                        $subColl[] = $child;
                                    }
                                }
                            }
                        }
                    }

                    $collection = $this->orderSearch($subColl, $q);
                    redis()->set($keyCache, serialize($collection));
                    redis()->expire($keyCache, 84600);
                }
            } else {
                $collection = unserialize($collection);
            }

            if (!$collection) {
                $collection = [];
            }

            return array_slice($collection, $offset, $limit);
        }

        public function searchSpecialities($q, $sellzone_id = 1, $limit = 10, $offset = 0)
        {
            $spes = $this->getSpecialitesByZone($sellzone_id);

            $keyCache = 'resto.researches.specialities.' . sha1(serialize(func_get_args()));

            $collection = redis()->get('tt');

            if (!$collection) {
                $subColl = [];

                $father = Model::Segment()->find(392);

                if ($father) {
                    $children = repo('segment')->getAllFamily((int) $father->id);

                    foreach ($children as $k => $child) {
                        $isSearchable   = repo('segment')->isSearchable((int) $child['id']);
                        $isItem         = repo('segment')->isItem((int) $child['id']);

                        $child['is_item'] = $isItem;

                        $comp = Inflector::lower(
                            Inflector::unaccent($q)
                        );

                        $value = Inflector::lower(
                            Inflector::unaccent($child['name'])
                        );

                        $check = fnmatch("*$comp*", $value);

                        if ($check) {
                            if ($isItem) {
                                $ids = [];

                                $segments = Model::Mealgeo()->where(['resto_geo_id', '=', (int) $child['id']])->cursor();

                                $total = 0;

                                foreach ($segments as $segment) {
                                    // $ids[] = $segment['segment_id'];
                                    $total += isset($spes[$segment['segment_id']]) ? $spes[$segment['segment_id']] : 0;
                                }

                                // if (empty($ids)) {
                                //     $child['nb_etablissement'] = 0;
                                // } else {
                                //     $child['nb_etablissement'] = Model::Catalog()->where(['segment_id', 'IN', implode(',', $ids)])->cursor()->count();
                                // }

                                unset($child['hash']);
                                unset($child['updated_at']);
                                unset($child['created_at']);

                                $subColl[] = $child;
                            } else {
                                if ($child['segment_id'] != $father->id) {
                                    if ($isSearchable) {
                                        $ids = [];

                                        $total = 0;

                                        $segments = Model::Mealgeo()
                                        ->where(['resto_geo_id', '=', (int) $child['id']])
                                        ->cursor();

                                        foreach ($segments as $segment) {
                                            // $ids[] = $segment['segment_id'];
                                            $total += isset($spes[$segment['segment_id']]) ? $spes[$segment['segment_id']] : 0;
                                        }

                                        // if (empty($ids)) {
                                        //     $total = 0;
                                        // } else {
                                        //     $total = Model::Catalog()->where(['segment_id', 'IN', implode(',', $ids)])->cursor()->count();
                                        // }

                                        unset($child['hash']);
                                        unset($child['updated_at']);
                                        unset($child['created_at']);

                                        $sons = repo('segment')->getAllFamily((int) $child['id']);

                                        $total = 0;

                                        foreach ($sons as $son) {
                                            $ids = [];

                                            $segments = Model::Mealgeo()->where(['resto_geo_id', '=', (int) $son['id']])->cursor()->cursor();

                                            foreach ($segments as $segment) {
                                                $total += isset($spes[$segment['segment_id']]) ? $spes[$segment['segment_id']] : 0;
                                                // $ids[] = $segment['segment_id'];
                                            }

                                            // if (!empty($ids)) {
                                            //     $total += Model::Catalog()->where(['segment_id', 'IN', implode(',', $ids)])->cursor()->count();
                                            // }
                                        }

                                        $child['nb_etablissement'] = $total;

                                        $subColl[] = $child;
                                    }
                                }
                            }
                        }
                    }

                    $collection = $this->orderSearch($subColl, $q);
                    redis()->set($keyCache, serialize($collection));
                    redis()->expire($keyCache, 84600);
                }

            } else {
                $collection = unserialize($collection);
            }

            if (!$collection) {
                $collection = [];
            }

            return array_slice($collection, $offset, $limit);
        }

        /**
         * [orderSearch description]
         * @param  [type] $collection [description]
         * @param  [type] $pattern    [description]
         * @return [type]             [description]
         */
        private function orderSearch($collection, $pattern)
        {
            if (empty($collection)) {
                return $collection;
            }

            $newCollection = $lengths = $byItem = [];

            foreach ($collection as $item) {
                if (!isset($lengths[strlen($item['name'])])) {
                    $lengths[strlen($item['name'])] = [];
                }

                $lengths[strlen($item['name'])][] = $item;
            }

            asort($lengths);

            foreach ($lengths as $length => $subColl) {
                foreach ($subColl as $k => $segment) {
                    $comp = Inflector::lower(
                        Inflector::unaccent($pattern)
                    );

                    $value = Inflector::lower(
                        Inflector::unaccent($segment['name'])
                    );

                    $check = fnmatch("$comp*", $value);

                    if ($check) {
                        $newCollection[] = $segment;
                        unset($lengths[$length][$k]);
                    }
                }
            }

            foreach ($lengths as $length => $subColl) {
                foreach ($subColl as $k => $segment) {
                    $newCollection[] = $segment;
                }
            }

            return $newCollection;
        }

        /**
         * [filter description]
         * @param  [type] $filter [description]
         * @return [type]         [description]
         */
        public function filter($filter)
        {
            $account_id = isAke($filter, 'account_id', false);

            if (false === $account_id) {
                $user = session('user')->getUser();
                $filter['account_id'] = (int) $user['id'];
            }

            $found = $this->findResto($filter);

            $spedispos = $found['specialites_dispo'];

            // foreach ($found['plats_dispo'] as $p) {
            //     $geos = Model::Mealgeo()->where(['segment_id', '=', $p])->cursor();

            //     dd($geos->first());
            // }
            // $spedispos = [];

            // foreach ($found['specialites_dispo'] as $idspe) {
            //     $getids = unserialize(redis()->get('geos.spes.' . $idspe));

            //     $keep = false;

            //     foreach ($found['plats_dispo'] as $idPlat) {
            //         if (in_array($idPlat, $getids)) {
            //             $keep = true;

            //             break;
            //         }
            //     }

            //     if ($keep) {
            //         $spedispos[] = $idspe;
            //     }
            // }

            $merged = array_values(
                array_unique(
                    array_merge(
                        $spedispos,
                        $found['families_specialites_dispo'],
                        $found['pois_dispo'],
                        $found['families_pois_dispo'],
                        $found['plats_dispo'],
                        $found['families_plats_dispo'],
                        $found['types_auto_dispo'],
                        $found['types_non_auto_dispo']
                    )
                )
            );

            asort($merged);

            $merged = array_values($merged);

            $optionsMacro       = include(APPLICATION_PATH . DS . 'models/options/413.php');
            $valuesActivities   = array_get($optionsMacro, 'activites.values');

            asort($found['preferences_dispo']);

            $found['preferences_dispo'] = array_values($found['preferences_dispo']);

            $adp = [];

            foreach ($found['activites_dispo'] as $ad) {
                $idap   = Arrays::last(explode('_', $ad));
                $adp[]  = isset($valuesActivities[$idap]) ? $valuesActivities[$idap] : $ad;
            }

            asort($adp);

            $found['activites_dispo']   = array_values(array_unique($adp));

            asort($found['thematiques_dispo']);

            $found['thematiques_dispo'] = array_values($found['thematiques_dispo']);

            asort($found['families_plats_dispo']);

            $found['families_plats_dispo'] = array_values($found['families_plats_dispo']);

            asort($found['plats_dispo']);

            $found['plats_dispo'] = array_values($found['plats_dispo']);

            asort($found['families_pois_dispo']);

            $found['families_pois_dispo'] = array_values($found['families_pois_dispo']);

            asort($found['pois_dispo']);

            $found['pois_dispo'] = array_values($found['pois_dispo']);

            asort($found['types_non_auto_dispo']);

            $found['types_non_auto_dispo'] = array_values($found['types_non_auto_dispo']);

            asort($found['types_auto_dispo']);

            $found['types_auto_dispo'] = array_values($found['types_auto_dispo']);

            $results = [
                'total'                         => $found['total'],
                'distances'                     => isAke($found, 'distances', ['min' => 0, 'max' => 0]),
                'specialites_dispo'             => $spedispos,
                'pois_dispo'                    => $found['pois_dispo'],
                'families_pois_dispo'           => $found['families_pois_dispo'],
                'families_specialites_dispo'    => $found['families_specialites_dispo'],
                'plats_dispo'                   => $found['plats_dispo'],
                'families_plats_dispo'          => $found['families_plats_dispo'],
                'types_auto_dispo'              => $found['types_auto_dispo'],
                'types_non_auto_dispo'          => $found['types_non_auto_dispo'],
                'thematiques_dispo'             => $found['thematiques_dispo'],
                'labels_dispo'                  => $found['labels_dispo'],
                'activites_dispo'               => $found['activites_dispo'],
                'preferences_dispo'             => $found['preferences_dispo'],
            ];

            $idr = [];

            foreach ($found['restaurants'] as $resto) {
                $reseller_id = $idr[] = $resto['id'];
            }

            $rows = Model::Suggestion()->cursor();

            $suggestions = [];

            foreach ($rows as $row) {
                $ids = [];

                foreach ($row['segments'] as $s) {
                    $ids[] = $s['id'];
                }

                $plates = isAke($row, 'plats', []);

                foreach ($plates as $p) {
                    if (fnmatch('*:*', $p)) {
                        list($p, $ir) = explode(':', $p, 2);

                        if (in_array($ir, $idr)) {
                            $ids[] = $p;
                        }
                    } else {
                        $ids[] = $p;
                    }
                }

                foreach ($ids as $sId) {
                    if (in_array($sId, $merged)) {
                        $family = repo('segment')->getFamily((int) $row['segment_id']);
                        $merged[] = $suggestions[] = (int) $row['segment_id'];

                        foreach ($family as $child) {
                            $merged[] = $suggestions[] = (int) $child['id'];
                        }

                        break;
                    }
                }
            }

            $suggestions    = array_unique($suggestions);
            $merged         = array_unique($merged);

            asort($merged);

            $merged = array_values($merged);

            asort($suggestions);

            $suggestions = array_values($suggestions);

            $results['suggestions']     = $suggestions;
            $results['segments_dispos'] = $merged;

            if ($found['total'] < 1) {
                $results['segments_dispos'] =
                $results['suggestions'] =
                $results['families_plats_dispo'] =
                $results['specialites_dispo'] =
                $results['pois_dispo'] =
                $results['families_pois_dispo'] =
                $results['families_specialites_dispo'] =
                $results['types_auto_dispo'] =
                $results['types_non_auto_dispo'] = [];
            }

            // asort(self::$datas['plats_' . $reseller_id]);
            // self::$datas['plats_' . $reseller_id] = array_values(self::$datas['plats_' . $reseller_id]);

            // if ($found['total'] == 1)   $results['segments_dispos'] = $results['plats_dispo'] = self::$datas['plats_' . $reseller_id];

            // $back = [
            //     'segments_dispos'   => $merged,
            //     'total'             => $found['total'],
            //     'distances'         => $results['distances'],
            //     'thematiques_dispo' => $results['thematiques_dispo'],
            //     'preferences_dispo' => $results['preferences_dispo'],
            //     'labels_dispo'      => $results['labels_dispo'],
            //     'activites_dispo'   => $results['activites_dispo'],
            // ];

            // return $back;
            return $results;
        }

        /**
         * [getOffersOut description]
         * @param  [type] $query [description]
         * @return [type]        [description]
         */
        public function getOffersOut($query)
        {
            $account_id = isAke($query, 'account_id', false);

            if (false === $account_id) {
                $user = session('user')->getUser();
                $query['account_id'] = (int) $user['id'];
            }

            return $this->findResto($query, true);
        }

        /**
         * [findResto description]
         * @param  array    $filter [description]
         * @param  boolean  $out    [description]
         * @return [type]          [description]
         */
        public function findResto($filter, $out = false)
        {
            $collection =
            $final_plats_dispo =
            $final_families_plats_dispo =
            $final_pois_dispo =
            $final_families_pois_dispo =
            $final_thematiques_dispo =
            $final_labels_dispo =
            $final_activites_dispo =
            $final_preferences_dispo =
            $final_specialites_dispo =
            $final_families_specialites_dispo =
            $final_types_auto_dispo =
            $final_types_non_auto_dispo = [];

            $distanceMin = 999;
            $distanceMax = 0;

            $now = time();

            $typesAuto      = isAke($filter, 'types_auto', []);
            $typesnonAuto   = isAke($filter, 'types_non_auto', []);
            $food           = isAke($filter, 'food', []);
            $specialities   = isAke($filter, 'specialities', []);
            $suggestions    = isAke($filter, 'suggestions', []);
            $geo            = isAke($filter, 'geo', isAke($filter, 'location', []));
            $account_id     = isAke($filter, 'account_id', 26);
            $sellzone_id    = isAke($filter, 'sellzone_id', 1);
            $nb_customer    = isAke($filter, 'nb_customer', 1);
            $context        = isAke($filter, 'context', 'resto');
            $distance       = isAke($filter, 'distance', 0);
            $is_now         = isAke($filter, 'now', 0);
            $mode_conso     = isAke($filter, 'mode_conso', 2);
            $budget         = isAke($filter, 'budget', 0);
            $poi            = isAke($filter, 'poi', false);
            $date           = isAke($filter, 'date', date('Y-m-d'));
            $hour           = isAke($filter, 'time', date('H') . ':' . date('i'));
            $themesFilter   = isAke($filter, 'themes', []);

            list($y, $m, $d)    = explode('-', $date);
            list($h, $i)        = explode(':', $hour);

            $start = mktime($h, $i, 0, $m, $d, $y);

            $when = lib('time')->createFromTimestamp((int) $start);

            $jour = $when->frenchDay();

            Save::set('filter.' . $this->session_id(), $filter);

            switch ($context) {
                case 'all':
                    $father = 'all';
                    $type_conso = 'sans_reservation';
                    $distance = 0.5;
                    break;
                case 'resto':
                    $father = 2165;
                    break;
                case 'vin':
                    $father = 2026;
                    break;
                case 'petit_creux':
                    $father = 2164;
                    break;
                case 'snack':
                    $father = 2164;
                    break;
            }

            if (!empty($suggestions)) {
                $spe_sugg = $auto_sugg = $non_auto_sugg = [];

                if (count($suggestions) == 1) {
                    $suggs = Model::Suggestion()->where(['segment_id', '=', (int) $suggestions[0]])->cursor();
                } else {
                    $suggs = Model::Suggestion()->where(['segment_id', 'IN', implode(',', $suggestions)])->cursor();
                }

                foreach ($suggs as $sugg) {
                    $segs = isAke($sugg, 'segments', []);

                    foreach ($segs as $segSugg) {
                        if (fnmatch('*geo*', $segSugg['type'])) {
                            $spe_sugg[] = (int) $segSugg['id'];
                        }

                        if (fnmatch('*non*', $segSugg['type'])) {
                            $non_auto_sugg[] = (int) $segSugg['id'];
                        }

                        if (fnmatch('*_type*', $segSugg['type'])) {
                            $auto_sugg[] = (int) $segSugg['id'];
                        }
                    }
                }
            }

            // dd($spe_sugg);

            $optionsMacro = include(APPLICATION_PATH . DS . 'models/options/413.php');

            $valuesActivities   = array_get($optionsMacro, 'activites.values');
            $themes_affil       = array_get($optionsMacro, 'activites.types_affil');

            /* Thématiques */
            $thematiquesResto   = array_get($optionsMacro, 'thematiques', []);
            $thematiquesSnack   = array_get($optionsMacro, 'thematiques_snack', []);
            $thematiquesPc      = array_get($optionsMacro, 'thematiques_petit_creux', []);
            $thematiquesVin     = array_get($optionsMacro, 'thematiques_vin', []);

            $thematiquesModel = [];

            foreach ($thematiquesResto as $k => $v) {
                if (fnmatch('*values*', $k)) {
                    $ind = str_replace('values_', '', $k);

                    foreach ($v as $i => $label) {
                        $key = "thematiques_" . $ind . "_$i";
                        $thematiquesModel[$key] = $label;
                    }
                }
            }

            foreach ($thematiquesSnack as $k => $v) {
                if (fnmatch('*values*', $k)) {
                    $ind = str_replace('values_', '', $k);

                    foreach ($v as $i => $label) {
                        $key = "thematiques_snack_" . $ind . "_$i";
                        $thematiquesModel[$key] = $label;
                    }
                }
            }

            foreach ($thematiquesPc as $k => $v) {
                if (fnmatch('*values*', $k)) {
                    $ind = str_replace('values_', '', $k);

                    foreach ($v as $i => $label) {
                        $key = "thematiques_petit_creux_" . $ind . "_$i";
                        $thematiquesModel[$key] = $label;
                    }
                }
            }

            foreach ($thematiquesVin as $k => $v) {
                if (fnmatch('*values*', $k)) {
                    $ind = str_replace('values_', '', $k);

                    foreach ($v as $i => $label) {
                        $key = "thematiques_vin_" . $ind . "_$i";
                        $thematiquesModel[$key] = $label;
                    }
                }
            }

            /* Guides */
            $guidesResto   = array_get($optionsMacro, 'guides', []);
            $guidesSnack   = array_get($optionsMacro, 'guides_snack', []);
            $guidesPc      = array_get($optionsMacro, 'guides_petit_creux', []);
            $guidesVin     = array_get($optionsMacro, 'guides_vin', []);

            $guidesModel = [];

            foreach ($guidesResto as $k => $v) {
                if (fnmatch('*values*', $k)) {
                    $ind = str_replace('values_', '', $k);

                    foreach ($v as $i => $label) {
                        $key = "guides_" . $ind . "_$i";
                        $guidesModel[$key] = $label;
                    }
                }
            }

            foreach ($guidesSnack as $k => $v) {
                if (fnmatch('*values*', $k)) {
                    $ind = str_replace('values_', '', $k);

                    foreach ($v as $i => $label) {
                        $key = "guides_snack_" . $ind . "_$i";
                        $guidesModel[$key] = $label;
                    }
                }
            }

            foreach ($guidesPc as $k => $v) {
                if (fnmatch('*values*', $k)) {
                    $ind = str_replace('values_', '', $k);

                    foreach ($v as $i => $label) {
                        $key = "guides_petit_creux_" . $ind . "_$i";
                        $guidesModel[$key] = $label;
                    }
                }
            }

            foreach ($guidesVin as $k => $v) {
                if (fnmatch('*values*', $k)) {
                    $ind = str_replace('values_', '', $k);

                    foreach ($v as $i => $label) {
                        $key = "guides_vin_" . $ind . "_$i";
                        $guidesModel[$key] = $label;
                    }
                }
            }

            /* Labels */
            $labelsResto   = array_get($optionsMacro, 'labels', []);
            $labelsSnack   = array_get($optionsMacro, 'labels_snack', []);
            $labelsPc      = array_get($optionsMacro, 'labels_petit_creux', []);
            $labelsVin     = array_get($optionsMacro, 'labels_vin', []);

            $labelsModel = [];

            foreach ($labelsResto as $k => $v) {
                if (fnmatch('*values*', $k)) {
                    $ind = str_replace('values_', '', $k);

                    foreach ($v as $i => $label) {
                        $key = "labels_" . $ind . "_$i";
                        $labelsModel[$key] = $label;
                    }
                }
            }

            foreach ($labelsSnack as $k => $v) {
                if (fnmatch('*values*', $k)) {
                    $ind = str_replace('values_', '', $k);

                    foreach ($v as $i => $label) {
                        $key = "labels_snack_" . $ind . "_$i";
                        $labelsModel[$key] = $label;
                    }
                }
            }

            foreach ($labelsPc as $k => $v) {
                if (fnmatch('*values*', $k)) {
                    $ind = str_replace('values_', '', $k);

                    foreach ($v as $i => $label) {
                        $key = "labels_petit_creux_" . $ind . "_$i";
                        $labelsModel[$key] = $label;
                    }
                }
            }

            foreach ($labelsVin as $k => $v) {
                if (fnmatch('*values*', $k)) {
                    $ind = str_replace('values_', '', $k);

                    foreach ($v as $i => $label) {
                        $key = "labels_vin_" . $ind . "_$i";
                        $labelsModel[$key] = $label;
                    }
                }
            }

            list($h, $i)        = explode(':', $hour, 2);
            list($y, $m, $d)    = explode('-', $date, 3);

            $ts = mktime($h, $i, 0, $m, $d, $y);

            $minTime = time() - 1800;

            if ($ts < $minTime) {
                return [
                    'total'                         => 0,
                    'restaurants'                   => [],
                    'specialites_dispo'             => [],
                    'families_specialites_dispo'    => [],
                    'pois_dispo'                    => [],
                    'families_pois_dispo'           => [],
                    'preferences_dispo'             => [],
                    'types_auto_dispo'              => [],
                    'types_non_auto_dispo'          => [],
                    'plats_dispo'                   => [],
                    'families_plats_dispo'          => [],
                    'labels_dispo'                  => [],
                    'thematiques_dispo'             => [],
                    'activites_dispo'               => []
                ];
            }

            $mode_conso     = $mode_conso == 0 ? 2 : $mode_conso;

            $last_minute    = $is_now > 0;

            $numberRestos = 0;

            switch ($mode_conso) {
                case 1:
                    $type_conso = 'en_livraison';

                    break;
                case 2:
                    $type_conso = 'sur_place';

                    break;
                case 3:
                    $type_conso = 'a_emporter';

                    break;
                case 4:
                    $type_conso = 'sans_reservation';

                    break;
            }

            $sz = Model::Sellzone()->find((int) $sellzone_id);

            if (!$sz) {
                $sz = Model::Sellzone()->find(1);
            }

            // $lon = isAke($geo, 'lon', (double) $sz->longitude);
            // $lat = isAke($geo, 'lat', (double) $sz->latitude);

            $lon = isAke($geo, 'lon', 0);
            $lat = isAke($geo, 'lat', 0);

            if (false !== $poi) {
                $segPoi = Model::Segment()->find($poi);

                if ($segPoi) {
                    $dataPoi = repo('segment')->getData($poi);

                    $lat = isAke($dataPoi, 'latitude', (double) $sz->latitude);
                    $lon = isAke($dataPoi, 'longitude', (double) $sz->longitude);

                    // $distance = 0;
                }
            }

            $prefs          = array_keys($this->extractPreferences($filter));
            $labels         = array_keys($this->extractLabels($filter));
            $thematiques    = array_keys($this->extractThematiques($filter));
            $activites      = array_keys($this->extractActivites($filter));

            $cached = false;

            if ($cached) {
                return unserialize($cached);
            } else {
                $restos = Model::Restodata()->where([
                    'sellzone_id', '=', (int) $sz->id
                ])->cursor();

                $ir = [];

                // $statusZcValid = ['ACTIVE', 'WAITING'];
                $statusZcValid = ['ACTIVE'];

                foreach ($restos as $resto) {
                    $reseller_id = isAke($resto, 'reseller_id', false);

                    if (false === $reseller_id) {
                        continue;
                    }

                    $has_favorite = lib('favorite')->has('reseller', $reseller_id, $account_id);

                    $status_zechallenge = isAke($resto, 'status_zechallenge', 'WAITING');

                    if (!in_array($status_zechallenge, $statusZcValid)) {
                        continue;
                    }

                    if (empty($resto['all_plats'])) {
                        continue;
                    }

                    foreach ($resto['plats']['fathers'] as $idp => $idf) {
                        if ('all' != $father) {
                            if ($idf != $father) {
                                unset($resto['all_plats'][$idp]);
                            }
                        }
                    }

                    $plats_dispo =
                    $thematiques_dispo =
                    $labels_dispo =
                    $activites_dispo =
                    $preferences_dispo =
                    $specialites_dispo =
                    $types_auto_dispo =
                    $types_non_auto_dispo =
                    $choosePlates = [];

                    $ir[$reseller_id] = [];

                    $reseller = Model::Reseller()->model($resto['reseller']);

                    if ($reseller) {
                        Now::set('resto.reseller_id', $reseller->id);
                        $prefsResto         = array_keys($this->extractPreferences($resto['options']));
                        $activitesResto     = array_keys($this->extractActivites($resto['options']));
                        $labelsResto        = array_keys($this->extractLabels($resto['options']));
                        $thematiquesResto   = array_keys($this->extractThematiques($resto['options']));

                        $themes = [];

                        foreach ($resto['options'] as $ko => $vo) {
                            if (fnmatch('thematiques_*', $ko) && 1 == $vo) {
                                if ($context == 'all') {
                                    $themes[] = $ko;
                                } else {
                                    if ($context == 'resto') {
                                        if (!fnmatch('*vin*', $ko) && !fnmatch('*snack*', $ko) && !fnmatch('*petit_creux*', $ko)) {
                                            $themes[] = $ko;
                                        }
                                    } elseif ($context == 'vin') {
                                        if (fnmatch('*vin*', $ko)) {
                                            $themes[] = $ko;
                                        }
                                    } elseif ($context == 'snack') {
                                        if (fnmatch('*snack*', $ko)) {
                                            $themes[] = $ko;
                                        }
                                    } elseif ($context == 'petit_creux') {
                                        if (fnmatch('*petit_creux*', $ko)) {
                                            $themes[] = $ko;
                                        }
                                    }
                                }
                            }
                        }

                        if (!empty($themes)) {
                            $ths = [];

                            foreach ($themes as $th) {
                                $ths[] = isAke($thematiquesModel, $th, $th);
                            }

                            $themes = $ths;
                        }

                        $continue = true;

                        if (!empty($themesFilter)) {
                            foreach ($themesFilter as $tf) {
                                if (!in_array($tf, $themes)) {
                                    $continue = false;

                                    break;
                                }
                            }
                        }

                        if (!$continue) {
                            continue;
                        }

                        /* Guides */
                        $guides = [];

                        foreach ($resto['options'] as $ko => $vo) {
                            if (fnmatch('guides_*', $ko) && 1 == $vo) {
                                if ($context == 'all') {
                                    $themes[] = $ko;
                                } else {
                                    if ($context == 'resto') {
                                        if (!fnmatch('*vin*', $ko) && !fnmatch('*snack*', $ko) && !fnmatch('*petit_creux*', $ko)) {
                                            $guides[] = $ko;
                                        }
                                    } elseif ($context == 'vin') {
                                        if (fnmatch('*vin*', $ko)) {
                                            $guides[] = $ko;
                                        }
                                    } elseif ($context == 'snack') {
                                        if (fnmatch('*snack*', $ko)) {
                                            $guides[] = $ko;
                                        }
                                    } elseif ($context == 'petit_creux') {
                                        if (fnmatch('*petit_creux*', $ko)) {
                                            $guides[] = $ko;
                                        }
                                    }
                                }
                            }
                        }

                        if (!empty($guides)) {
                            $ths = [];

                            foreach ($guides as $th) {
                                $ths[] = isAke($guidesModel, $th, $th);
                            }

                            $guides = $ths;
                        }

                        /* Labels */
                        $labels = [];

                        foreach ($resto['options'] as $ko => $vo) {
                            if (fnmatch('labels_*', $ko) && 1 == $vo) {
                                if ($context == 'all') {
                                    $labels[] = $ko;
                                } else {
                                    if ($context == 'resto') {
                                        if (!fnmatch('*vin*', $ko) && !fnmatch('*snack*', $ko) && !fnmatch('*petit_creux*', $ko)) {
                                            $labels[] = $ko;
                                        }
                                    } elseif ($context == 'vin') {
                                        if (fnmatch('*vin*', $ko)) {
                                            $labels[] = $ko;
                                        }
                                    } elseif ($context == 'snack') {
                                        if (fnmatch('*snack*', $ko)) {
                                            $labels[] = $ko;
                                        }
                                    } elseif ($context == 'petit_creux') {
                                        if (fnmatch('*petit_creux*', $ko)) {
                                            $labels[] = $ko;
                                        }
                                    }
                                }
                            }
                        }

                        if (!empty($labels)) {
                            $ths = [];

                            foreach ($labels as $th) {
                                $ths[] = isAke($guidesModel, $th, $th);
                            }

                            $labels = $ths;
                        }

                        $continueBudget = true;

                        $specialites_dispo          = $resto['specialites']['dispos'];
                        $specialites_families_dispo = $resto['specialites']['families'];
                        $types_auto_dispo           = $resto['auto']['dispos'];
                        $types_non_auto_dispo       = $resto['non_auto']['dispos'];

                        if (!empty($suggestions)) {
                            $continue = false;

                            if (!empty($spe_sugg) && !$continue) {
                                foreach ($spe_sugg as $segsugId) {
                                    if (in_array($segsugId, $specialites_dispo) || in_array($segsugId, $specialites_families_dispo)) {
                                        $continue = true;

                                        break;
                                    }
                                }
                            }

                            if (!empty($auto_sugg) && !$continue) {
                                foreach ($auto_sugg as $segsugId) {
                                    if (in_array($segsugId, $types_auto_dispo)) {
                                        $continue = true;

                                        break;
                                    }
                                }
                            }

                            if (!empty($non_auto_sugg) && !$continue) {
                                foreach ($non_auto_sugg as $segsugId) {
                                    if (in_array($segsugId, $types_non_auto_dispo)) {
                                        $continue = true;

                                        break;
                                    }
                                }
                            }

                            if (!$continue) {
                                continue;
                            }
                        }

                        if (!empty($specialities)) {
                            $continue = true;

                            foreach ($specialities as $idSpe) {
                                if (!in_array($idSpe, $specialites_dispo) && !in_array($idSpe, $specialites_families_dispo)) {
                                    $continue = false;

                                    break;
                                }
                            }

                            if (!$continue) {
                                continue;
                            }
                        }

                        if (!empty($typesAuto)) {
                            $continue = true;

                            foreach ($typesAuto as $idta) {
                                if (!in_array($idta, $types_auto_dispo)) {
                                    $continue = false;

                                    break;
                                }
                            }

                            if (!$continue) {
                                continue;
                            }
                        }

                        if (!empty($typesnonAuto)) {
                            $continue = true;

                            foreach ($typesnonAuto as $idta) {
                                if (!in_array($idta, $types_non_auto_dispo)) {
                                    $continue = false;

                                    break;
                                }
                            }

                            if (!$continue) {
                                continue;
                            }
                        }

                        if (!empty($prefs)) {
                            $continue = true;

                            foreach ($prefs as $prefId) {
                                if (!in_array($prefId, $prefsResto)) {
                                    $continue = false;

                                    break;
                                }
                            }

                            if (!$continue) {
                                continue;
                            }
                        }

                        if (!empty($context) && $context != 'all') {
                            $continue = false;

                            foreach ($activitesResto as $activiteId) {
                                if (fnmatch('*_*_*', $activiteId)) {
                                    continue;
                                }

                                $actFamily = isset($valuesActivities[(int) str_replace('activites_', '', $activiteId)])
                                ? $valuesActivities[(int) str_replace('activites_', '', $activiteId)]
                                : '';

                                $contextFamily = isAke($themes_affil, $actFamily, []);

                                foreach ($contextFamily as $contextAct) {
                                    if ($contextAct == $context) {
                                        $continue = true;

                                        break;
                                    }
                                }

                                if ($continue) {
                                    break;
                                }
                            }

                            if (!$continue) {
                                continue;
                            }
                        }

                        $total_price = 0;

                        if (false !== $poi) {
                            if (!in_array($poi, $resto['pois']['dispos'])) {
                                continue;
                            } else {
                                if ($distance > 0) {
                                    $km = $resto['pois']['distances'][$poi];

                                    if ($distance < $km) {
                                        continue;
                                    }
                                }
                            }
                        }

                        if (!empty($food)) {
                            $choosePlates =
                            $plats_disponibles =
                            $hasId =
                            $hasfamily =
                            $familyPlates =
                            $plats_disponibles =
                            $collectionFoods = [];

                            foreach ($food as $idPlat) {
                                $item               = [];
                                $data               = repo('segment')->getData((int) $idPlat);
                                $ordre              = isAke($data, 'ordre', 1);
                                $item['ordre']      = $ordre;
                                $item['food_id']    = (int) $idPlat;
                                $collectionFoods[]  = $item;
                            }

                            $foodcoll = lib('collection', [$collectionFoods])
                            ->sortBy('ordre')
                            ->toArray();

                            foreach ($foodcoll as $foodItem) {
                                $idPlat = $foodItem['food_id'];
                                $ordre  = $foodItem['ordre'];

                                foreach ($resto['all_plats'] as $tabPlats) {
                                    foreach ($tabPlats as $tabPlat) {
                                        $assoc      = $resto['assocs'][$tabPlat['id']];
                                        $family     = $resto['plats']['families'];
                                        $isFamily   = in_array($idPlat, $family);

                                        if ($tabPlat['segment_id'] != $idPlat && !in_array($idPlat, $family)) {
                                            continue;
                                        }

                                        $addPlate = true;

                                        $price = (double) $tabPlat['price'];

                                        if (0 < $budget && !$isFamily) {
                                            if ($price > $budget) {
                                                $addPlate = false;
                                            }
                                        }

                                        // if (!empty($specialities) && !$isFamily) {
                                        //     $specialites_plat = $assoc['geo'];

                                        //     foreach ($specialities as $idSpe) {
                                        //         if (!in_array($idSpe, $specialites_plat)) {vd($reseller->id, $idPlat);
                                        //             $addPlate = false;

                                        //             break;
                                        //         }
                                        //     }
                                        // }

                                        // if (!empty($typesAuto) && !$isFamily) {
                                        //     $types_auto_plat = $assoc['auto'];

                                        //     foreach ($typesAuto as $idta) {
                                        //         if (!in_array($idta, $types_auto_plat)) {
                                        //             $addPlate = false;

                                        //             break;
                                        //         }
                                        //     }
                                        // }

                                        // if (!empty($typesnonAuto) && !$isFamily) {
                                        //     $types_non_auto_plat = $assoc['non_auto'];

                                        //     foreach ($typesnonAuto as $idta) {
                                        //         if (!in_array($idta, $types_non_auto_plat)) {
                                        //             $addPlate = false;

                                        //             break;
                                        //         }
                                        //     }
                                        // }

                                        if ($addPlate) {
                                            $hasId[$idPlat] = true;

                                            if (!$isFamily) {
                                                $total_price += (double) $price * $nb_customer;
                                                $datasPlat = $resto['datas'][$tabPlat['segment_id']];

                                                $prefix = isAke($datasPlat, 'prefixe', '');

                                                $prefix = strlen($prefix) ? $prefix . ' ' : $prefix;

                                                $plate = [
                                                    'id'            => (int) $tabPlat['segment_id'],
                                                    'catalog_id'    => (int) $tabPlat['id'],
                                                    'price'         => $price,
                                                    'total_price'   => $price * $nb_customer,
                                                    'catalog_name'  => $tabPlat['price'],
                                                    'name'          => $prefix . $resto['names'][$tabPlat['segment_id']],
                                                    'ordre'         => $ordre,
                                                ];

                                                $choosePlates[] = $plate;

                                                $plate = [
                                                    'id'            => (int) $tabPlat['segment_id'],
                                                    'catalog_id'    => (int) $tabPlat['id'],
                                                    'price'         => $price,
                                                    'total_price'   => $price * $nb_customer,
                                                    'catalog_name'  => $tabPlat['price'],
                                                    'name'          => $prefix . $resto['names'][$tabPlat['segment_id']],
                                                    'ordre'         => $ordre,
                                                ];

                                                $plats_disponibles[] = $plate;
                                            } else {
                                                if (!isset($familyPlates[$idPlat])) {
                                                    $familyPlates[$idPlat] = [];
                                                }

                                                $hasfamily[$idPlat] = true;
                                                $platesChildren = redis()->get('getAllchildren.' . $idPlat . '.' . Model::Segment()->getAge());

                                                if (!$platesChildren) {
                                                    $platesChildren = repo('segment')
                                                    ->getAllchildren(
                                                        repo('segment')->getChildren($idPlat),
                                                        $idPlat
                                                    );

                                                    redis()->set('getAllchildren.' . $idPlat . '.' . Model::Segment()->getAge(), serialize($platesChildren));
                                                } else {
                                                    $platesChildren = unserialize($platesChildren);
                                                }

                                                foreach ($platesChildren as $platechild) {
                                                    $cisFamily = in_array($platechild['id'], $family);
                                                    $chas = isset($resto['all_plats'][$platechild['id']]);

                                                    if (!$cisFamily && $chas) {
                                                        $infChild   = current($resto['all_plats'][$platechild['id']]);
                                                        $assoc      = $resto['assocs'][$infChild['id']];

                                                        $addPlateChild = true;

                                                        $price = (double) $infChild['price'];

                                                        if (0 < $budget) {
                                                            if ($price > $budget) {
                                                                $addPlateChild = false;
                                                            }
                                                        }

                                                        if ($addPlateChild) {
                                                            if (isset($resto['datas'][$infChild['segment_id']])) {
                                                                $datasPlat = $resto['datas'][$infChild['segment_id']];

                                                                $prefix = isAke($datasPlat, 'prefixe', '');

                                                                $prefix = strlen($prefix) ? $prefix . ' ' : $prefix;
                                                            } else {
                                                                $prefix = '';
                                                            }

                                                            $plate = [
                                                                'id'            => (int) $infChild['segment_id'],
                                                                'catalog_id'    => (int) $infChild['id'],
                                                                'price'         => $price,
                                                                'total_price'   => $price * $nb_customer,
                                                                'catalog_name'  => $resto['plats']['catalog_names'][$infChild['id']],
                                                                'name'          => $prefix . $resto['names'][$infChild['segment_id']],
                                                            ];

                                                            $familyPlates[$idPlat][] = $plate;

                                                            $plats_disponibles[] = $plate;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }

                            $continue = count($food) == count($hasId);

                            if (!$continue) {
                                continue;
                            } else {
                                if (count($hasfamily) == count($food) && (!empty($specialities) || !empty($typesAuto) || !empty($typesnonAuto))) {
                                    foreach ($resto['all_plats'] as $tabPlats) {
                                        foreach ($tabPlats as $tabPlat) {
                                            $assoc = $resto['assocs'][$tabPlat['id']];
                                            $addPlate = true;

                                            $price = (double) $tabPlat['price'];

                                            if (0 < $budget) {
                                                if ($price > $budget) {
                                                    $addPlate = false;
                                                }
                                            }

                                            // if (!empty($specialities)) {
                                            //     $specialites_plat = $assoc['geo'];

                                            //     foreach ($specialities as $idSpe) {
                                            //         if (!in_array($idSpe, $specialites_plat)) {
                                            //             $addPlate = false;

                                            //             break;
                                            //         }
                                            //     }
                                            // }

                                            // if (!empty($typesAuto)) {
                                            //     $types_auto_plat = $assoc['auto'];

                                            //     foreach ($typesAuto as $idta) {
                                            //         if (!in_array($idta, $types_auto_plat)) {
                                            //             $addPlate = false;

                                            //             break;
                                            //         }
                                            //     }
                                            // }

                                            // if (!empty($typesnonAuto)) {
                                            //     $types_non_auto_plat = $assoc['non_auto'];

                                            //     foreach ($typesnonAuto as $idta) {
                                            //         if (!in_array($idta, $types_non_auto_plat)) {
                                            //             $addPlate = false;

                                            //             break;
                                            //         }
                                            //     }
                                            // }

                                            if ($addPlate) {
                                                $plate = [
                                                    'id'            => (int) $tabPlat['segment_id'],
                                                    'catalog_id'    => (int) $tabPlat['id'],
                                                    'price'         => $price,
                                                    'total_price'   => $price * $nb_customer,
                                                    'catalog_name'  => $resto['plats']['catalog_names'][$tabPlat['id']],
                                                    'name'          => $resto['names'][$tabPlat['segment_id']],
                                                ];

                                                $plats_disponibles[] = $plate;
                                            }
                                        }
                                    }
                                } elseif (!empty($suggestions)) {
                                    $allSuggestPlates = [];
                                    $assocsSuggIds = [];

                                    if (!empty($spe_sugg)) {
                                        $segs = isAke(isAke($resto, 'specialites', []), 'plats', []);

                                        foreach ($spe_sugg as $idta) {
                                            $segsPlats = isset($segs[$idta])
                                            ? array_values(array_unique($segs[$idta]))
                                            : [];

                                            if (!isset($assocsSuggIds[$idta])) {
                                                $assocsSuggIds[$idta] = [];
                                            }

                                            foreach ($segsPlats as $idSuggPlat) {
                                                if (!in_array($idSuggPlat, $allSuggestPlates)) {
                                                    $allSuggestPlates[] = $assocsSuggIds[$idta][] = $idSuggPlat;
                                                }
                                            }
                                        }
                                    }

                                    if (!empty($auto_sugg)) {
                                        $segs = isAke(isAke($resto, 'auto', []), 'plats', []);

                                        foreach ($auto_sugg as $idta) {
                                            $segsPlats = isset($segs[$idta])
                                            ? array_values(array_unique($segs[$idta]))
                                            : [];

                                            if (!isset($assocsSuggIds[$idta])) {
                                                $assocsSuggIds[$idta] = [];
                                            }

                                            foreach ($segsPlats as $idSuggPlat) {
                                                if (!in_array($idSuggPlat, $allSuggestPlates)) {
                                                    $allSuggestPlates[] = $assocsSuggIds[$idta][] = $idSuggPlat;
                                                }
                                            }
                                        }
                                    }

                                    if (!empty($non_auto_sugg)) {
                                        $segs = isAke(isAke($resto, 'non_auto', []), 'plats', []);

                                        foreach ($non_auto_sugg as $idta) {
                                            $segsPlats = isset($segs[$idta])
                                            ? array_values(array_unique($segs[$idta]))
                                            : [];

                                            if (!isset($assocsSuggIds[$idta])) {
                                                $assocsSuggIds[$idta] = [];
                                            }

                                            foreach ($segsPlats as $idSuggPlat) {
                                                if (!in_array($idSuggPlat, $allSuggestPlates)) {
                                                    $allSuggestPlates[] = $assocsSuggIds[$idta][] = $idSuggPlat;
                                                }
                                            }
                                        }
                                    }

                                    foreach ($resto['all_plats'] as $tabPlats) {
                                        foreach ($tabPlats as $tabPlat) {
                                            $addPlate = true;
                                            $check = in_array($tabPlat['segment_id'], $allSuggestPlates);

                                            if ($check) {
                                                $price = (double) $tabPlat['price'];

                                                if (0 < $budget) {
                                                    if ($price > $budget) {
                                                        $addPlate = false;
                                                    }
                                                }

                                                if ($addPlate) {
                                                        $plate = [
                                                        'is_suggest'    => true,
                                                        'id'            => (int) $tabPlat['segment_id'],
                                                        'catalog_id'    => (int) $tabPlat['id'],
                                                        'price'         => $price,
                                                        'total_price'   => $price * $nb_customer,
                                                        'catalog_name'  => $resto['plats']['catalog_names'][$tabPlat['id']],
                                                        'name'          => $resto['names'][$tabPlat['segment_id']],
                                                    ];

                                                    $plats_disponibles[] = $plate;
                                                }
                                            }

                                            // $assoc      = $resto['assocs'][$tabPlat['id']];
                                            // $addPlate   = false;

                                            // $price = (double) $tabPlat['price'];

                                            // if (0 < $budget) {
                                            //     if ($price > $budget) {
                                            //         $addPlate = false;
                                            //     }
                                            // }

                                            // $check = false;

                                            // if (!empty($spe_sugg) && !$check) {
                                            //     $specialites_plat = $assoc['geo'];

                                            //     foreach ($spe_sugg as $idSpe) {
                                            //         if (in_array($idSpe, $specialites_plat)) {
                                            //             $check = true;
                                            //             $addPlate = true;
                                            //             break;
                                            //         }
                                            //     }
                                            // }

                                            // if (!empty($auto_sugg) && !$check) {
                                            //     $types_auto_plat = $assoc['auto'];

                                            //     foreach ($auto_sugg as $idta) {
                                            //         if (in_array($idta, $types_auto_plat)) {
                                            //             $check = true;
                                            //             $addPlate = true;
                                            //             break;
                                            //         }
                                            //     }
                                            // }

                                            // if (!empty($non_auto_sugg) && !$check) {
                                            //     $types_non_auto_plat = $assoc['non_auto'];

                                            //     $segs = isAke(isAke($resto, 'non_auto', []), 'plats', []);

                                            //     foreach ($non_auto_sugg as $idta) {
                                            //         $segsPlats = isset($segs[$idta])
                                            //         ? array_values(array_unique($segs[$idta]))
                                            //         : [];

                                            //         if (in_array($tabPlat['id'], $segsPlats)) {
                                            //             $check = true;
                                            //             $addPlate = true;
                                            //             break;
                                            //         }
                                            //     }
                                            // }

                                            // if ($addPlate) {
                                            //     $plate = [
                                            //         'id'            => (int) $tabPlat['segment_id'],
                                            //         'catalog_id'    => (int) $tabPlat['id'],
                                            //         'price'         => $price,
                                            //         'total_price'   => $price * $nb_customer,
                                            //         'catalog_name'  => $resto['plats']['catalog_names'][$tabPlat['id']],
                                            //         'name'          => $resto['names'][$tabPlat['segment_id']],
                                            //     ];

                                            //     $plats_disponibles[] = $plate;
                                            // }
                                        }
                                    }
                                }
                            }
                        } else {
                            $plats_disponibles = [];

                            foreach ($resto['all_plats'] as $tabPlats) {
                                foreach ($tabPlats as $tabPlat) {
                                    // $data       = repo('segment')->getData((int) $tabPlat['segment_id']);
                                    $data       = isAke($tabPlat, 'data', []);
                                    $ordre      = isAke($data, 'ordre', 1);
                                    $assoc      = $resto['assocs'][$tabPlat['id']];
                                    $addPlate   = true;

                                    $price = (double) $tabPlat['price'];

                                    if (0 < $budget) {
                                        if ($price > $budget) {
                                            $addPlate = false;
                                        }
                                    }

                                    if (!empty($specialities)) {
                                        $specialites_plat = $assoc['geo'];

                                        foreach ($specialities as $idSpe) {
                                            if (!in_array($idSpe, $specialites_plat)) {
                                                $addPlate = false;

                                                break;
                                            }
                                        }
                                    }

                                    if (!empty($typesAuto) && $addPlate) {
                                        $types_auto_plat = $assoc['auto'];

                                        foreach ($typesAuto as $idta) {
                                            if (!in_array($idta, $types_auto_plat)) {
                                                $addPlate = false;

                                                break;
                                            }
                                        }
                                    }

                                    if (!empty($typesnonAuto) && $addPlate) {
                                        $types_non_auto_plat = $assoc['non_auto'];

                                        foreach ($typesnonAuto as $idta) {
                                            if (!in_array($idta, $types_non_auto_plat)) {
                                                $addPlate = false;

                                                break;
                                            }
                                        }
                                    }

                                    if ($addPlate) {
                                        $plate = [
                                            'id'            => (int) $tabPlat['segment_id'],
                                            'catalog_id'    => (int) $tabPlat['id'],
                                            'price'         => $price,
                                            'total_price'   => $price * $nb_customer,
                                            'catalog_name'  => $tabPlat['price'],
                                            'name'          => $resto['names'][$tabPlat['segment_id']],
                                            'ordre'         => (int) $ordre
                                        ];

                                        $plats_disponibles[] = $plate;
                                    }
                                }
                            }
                        }

                        $type_conso = 'sans_reservation' == $type_conso ? 'sur_place' : $type_conso;

                        $schedules = isAke($resto['schedules'], $type_conso, []);

                        list($canServe, $service, $last_minute, $fermeMidi, $fermeSoir, $rStock) = $this->checkCanServe(
                            (int) $reseller->id,
                            (int) $nb_customer,
                            $schedules,
                            $date,
                            $hour,
                            $resto['options'],
                            $type_conso,
                            $last_minute
                        );

                        if ($canServe) {
                            $jours      = isAke($resto, 'jours', []);
                            $services   = isAke($resto, 'services', []);

                            $hasAllPrefs        = true;
                            $hasAllActivites    = true;

                            $locationReseller   = isAke($resto, 'loc', ['lng' => 0, 'lat' => 0]);

                            if (0 == $lon && 0 == $lat) {
                                $km = $distanceMin = $distanceMax = 0;
                            } else {
                                if (false !== $lon && false !== $lat) {
                                    $distances = distanceKmMiles(
                                        $lon,
                                        $lat,
                                        $locationReseller['lng'],
                                        $locationReseller['lat']
                                    );
                                } else {
                                    $distances = distanceKmMiles(
                                        $sz->longitude,
                                        $sz->latitude,
                                        $locationReseller['lng'],
                                        $locationReseller['lat']
                                    );
                                }

                                $km = (double) $distances['km'];

                                if ($km < $distanceMin) {
                                    $distanceMin = $km;
                                }

                                if ($km > $distanceMax) {
                                    $distanceMax = $km;
                                }
                            }

                            if ($hasAllPrefs && $hasAllActivites) {
                                $checkDistance = true;

                                if (0 < $distance) {
                                    $checkDistance = $km <= $distance;
                                }

                                if ($checkDistance) {
                                    $activity = null;
                                    $activitiesResto = [];

                                    if (is_array($activitesResto)) {
                                        if (!empty($activitesResto)) {
                                            $firstActivite = current($activitesResto);

                                            $activity = isset ($valuesActivities[(int) str_replace('activites_', '', $firstActivite)])
                                            ? $valuesActivities[(int) str_replace('activites_', '', $firstActivite)]
                                            : null;
                                        }

                                        foreach ($activitesResto as $activiteId) {
                                            if (fnmatch('*_*_*', $activiteId)) {
                                                continue;
                                            }

                                            $activitiesResto[] = isset ($valuesActivities[(int) str_replace('activites_', '', $activiteId)])
                                            ? $valuesActivities[(int) str_replace('activites_', '', $activiteId)]
                                            : '';
                                        }

                                        asort($activitiesResto);

                                        $activitiesResto = array_values(array_unique($activitiesResto));

                                        $activity = implode(',', $activitiesResto);
                                    }

                                    $uplifts = $resto['uplifts'];

                                    $company = Model::Company()->model($resto['company']);

                                    $average_price = isAke($resto, 'average_price', 1);

                                    if (!empty($choosePlates)) {
                                        $jpds = [];

                                        foreach ($choosePlates as $jpd) {
                                            $contraintesJour = isset($jours[$jpd['catalog_id']]) ? $jours[$jpd['catalog_id']] : [];
                                            $contraintesService = isset($services[$jpd['catalog_id']]) ? $services[$jpd['catalog_id']] : [];

                                            $add = true;

                                            // if (!empty($contraintesJour)) {
                                            //     foreach ($contraintesJour as $contrainteJour) {
                                            //         if ($contrainteJour != $jour) {
                                            //             $add = false;

                                            //             break;
                                            //         }
                                            //     }
                                            // }

                                            // if (!empty($contraintesService) && $add) {
                                            //     foreach ($contraintesService as $contrainteService) {
                                            //         $contrainteService = (int) $contrainteService;

                                            //         if ($contrainteService != $service) {
                                            //             $add = false;

                                            //             break;
                                            //         }
                                            //     }
                                            // }

                                            if (!empty($contraintesJour)) {
                                                $add = in_array($jour, $contraintesJour);
                                            }

                                            if (!empty($contraintesService) && $add) {
                                                $add = in_array($service, $contraintesService);
                                            }

                                            if ($add) {
                                                $jpds[] = $jpd;
                                            }
                                        }

                                        if (empty($jpds)) {
                                            continue;
                                        }

                                        $ocp = $choosePlates = $jpds;

                                        $choosePlates = $this->analyzeUplifts(
                                            $uplifts,
                                            $choosePlates,
                                            $date,
                                            $hour,
                                            $nb_customer,
                                            $total_price
                                        );

                                        $composed = isAke($resto, 'composed', []);

                                        $hasCompo = false;

                                        $composedId = [];

                                        if (!empty($composed)) {
                                            $newC = [];

                                            $c = [];

                                            foreach ($composed as $ids => $cmps) {
                                                $c[$ids] = [];

                                                foreach ($cmps as $cmp) {
                                                    if ($cmp['type'] == 'entree') {
                                                        $c[$ids][0][] = ['oldtype' => 'entree', 'type' => 'Entrée', 'name' => $cmp['name']];
                                                    }

                                                    if ($cmp['type'] == 'plat') {
                                                        $c[$ids][1][] = ['oldtype' => 'plat', 'type' => 'Plat', 'name' => $cmp['name']];
                                                    }

                                                    if ($cmp['type'] == 'fromage') {
                                                        $c[$ids][2][] = ['oldtype' => 'fromage', 'type' => 'Fromage', 'name' => $cmp['name']];
                                                    }

                                                    if ($cmp['type'] == 'dessert') {
                                                        $c[$ids][3][] = ['oldtype' => 'dessert', 'type' => 'Dessert', 'name' => $cmp['name']];
                                                    }

                                                    if ($cmp['type'] == 'boisson') {
                                                        $c[$ids][4][] = ['oldtype' => 'boisson', 'type' => 'Boisson', 'name' => $cmp['name']];
                                                    }
                                                }

                                                ksort($c[$ids]);
                                            }

                                            $composed = $c;

                                            foreach ($choosePlates as $cp) {
                                                $segs = isset($composed[$cp['id']])
                                                ? $composed[$cp['id']]
                                                : [];

                                                if (!empty($segs)) {
                                                    $composedId[] = $cp['id'];
                                                }

                                                if (!empty($segs) && !$hasCompo) {
                                                    $hasCompo = true;
                                                }

                                                $act = '';

                                                foreach ($segs as $seg) {
                                                    if (is_array($seg)) {
                                                        $seg1 = array_shift($seg);

                                                        $act .= ucfirst($seg1['type']) . ' : ' . $seg1['name'] . ', ';

                                                        foreach ($seg as $subSeg) {
                                                            $act .= $subSeg['name'] . ', ';
                                                        }
                                                    }
                                                }

                                                $act = substr($act, 0, -2);

                                                $cp['description'] = $act;

                                                $newC[] = $cp;
                                            }

                                            $choosePlates = $newC;

                                            $newC = [];

                                            $tups = [];

                                            foreach ($choosePlates as $cp) {
                                                foreach ($composed as $ids => $cmps) {
                                                    $segs = isset($composed[$cp['id']])
                                                    ? $composed[$cp['id']]
                                                    : [];

                                                    $menu = [];

                                                    foreach ($segs as $seg) {
                                                        if (is_array($seg)) {
                                                            foreach ($seg as $subSeg) {
                                                                if (!isset($menu[$subSeg['oldtype']])) {
                                                                    $menu[$subSeg['oldtype']] = [];
                                                                }

                                                                $menu[$subSeg['oldtype']][] = $subSeg['name'];
                                                            }
                                                        }
                                                    }
                                                }

                                                $cp['menu'] = $menu;

                                                if (!in_array($cp['id'], $tups)) {
                                                    $newC[] = $cp;
                                                    $tups[] = $cp['id'];
                                                }
                                            }

                                            $choosePlates = $newC;
                                        }

                                        if (!empty($plats_disponibles)) {
                                            $jpds = [];

                                            foreach ($plats_disponibles as $jpd) {
                                                $contraintesJour = isset($jours[$jpd['catalog_id']]) ? $jours[$jpd['catalog_id']] : [];
                                                $contraintesService = isset($services[$jpd['catalog_id']]) ? $services[$jpd['catalog_id']] : [];

                                                $add = true;

                                                // if (!empty($contraintesJour)) {
                                                //     foreach ($contraintesJour as $contrainteJour) {
                                                //         if ($contrainteJour != $jour) {
                                                //             $add = false;

                                                //             break;
                                                //         }
                                                //     }
                                                // }

                                                // if (!empty($contraintesService) && $add) {
                                                //     foreach ($contraintesService as $contrainteService) {
                                                //         $contrainteService = (int) $contrainteService;

                                                //         if ($contrainteService != $service) {
                                                //             $add = false;

                                                //             break;
                                                //         }
                                                //     }
                                                // }

                                                if (!empty($contraintesJour)) {
                                                    $add = in_array($jour, $contraintesJour);
                                                }

                                                if (!empty($contraintesService) && $add) {
                                                    $add = in_array($service, $contraintesService);
                                                }


                                                if ($add) {
                                                    $jpds[] = $jpd;
                                                }
                                            }

                                            $plats_disponibles = $jpds;
                                            $plats_disponibles = $this->analyzeUplifts(
                                                $uplifts,
                                                $plats_disponibles,
                                                $date,
                                                $hour,
                                                $nb_customer,
                                                $total_price
                                            );

                                            $pds = $tups = [];

                                            foreach ($plats_disponibles as $pd) {
                                                if (!in_array($pd['id'], $tups)) {
                                                    $pds[] = $pd;
                                                    $tups[] = $pd['id'];
                                                }
                                            }

                                            $plats_disponibles = $pds;
                                        }

                                        if (!empty($familyPlates)) {
                                            // dd($choosePlates);
                                            foreach ($familyPlates as $idFamily => $tab) {
                                                $pprices = [];

                                                $item = $tuples = [];

                                                $item['id'] = $idFamily;

                                                $datasPlat = $resto['datas'][$item['id']];

                                                $prefix = isAke($datasPlat, 'prefixe', '');

                                                $prefix = strlen($prefix) ? $prefix . ' ' : $prefix;

                                                $item['name']   = $prefix . $resto['names'][$item['id']];
                                                $item['plats']  = [];

                                                $count = 0;

                                                foreach ($tab as $ptab) {
                                                    if (!in_array($ptab['id'], $tuples)) {
                                                        $count++;
                                                        $tuples[] = $ptab['id'];
                                                        $pprices[] = $ptab['price'];
                                                        $item['plats'][] = $this->infoPlates($resto, $ptab['id']);
                                                    }
                                                }

                                                if ($count > 0) {
                                                    $item['min_price'] = min($pprices);
                                                    $item['max_price'] = max($pprices);
                                                    $item['nb'] = $count;

                                                    // dd($item);
                                                    $choosePlates[] = $item;
                                                }
                                            }
                                        }

                                        if (!empty($specialities)) {
                                            foreach ($specialities as $sp) {
                                                $pprices = [];

                                                $item = $tuples = [];
                                                $item['id']     = $sp;

                                                $datasPlat = $resto['datas'][$item['id']];

                                                $prefix = isAke($datasPlat, 'prefixe', '');

                                                $prefix = strlen($prefix) ? $prefix . ' ' : $prefix;

                                                $item['name']   = $prefix . $resto['names'][$item['id']];

                                                $count = 0;

                                                $item['plats'] = [];

                                                $platesR = isAke($resto['specialites']['plats'], $sp, []);

                                                foreach ($platesR as $platesRId) {
                                                    $catId = $resto['plats']['catalog'][$platesRId];
                                                    $price = $resto['plats']['prices'][$catId];

                                                    if (!in_array($platesRId, $tuples)) {
                                                        $count++;
                                                        $tuples[] = $platesRId;
                                                        $pprices[] = $price;
                                                        $item['plats'][] = $this->infoPlates($resto, $platesRId);
                                                    }
                                                }

                                                if ($count > 0) {
                                                    $item['min_price'] = min($pprices);
                                                    $item['max_price'] = max($pprices);
                                                    $item['nb'] = $count;

                                                    // dd($item);
                                                    $choosePlates[] = $item;
                                                }
                                            }
                                        }

                                        if (!empty($typesAuto)) {
                                            foreach ($typesAuto as $sp) {
                                                $pprices = [];
                                                $item = $tuples = [];
                                                $item['id']     = $sp;

                                                $datasPlat = $resto['datas'][$item['id']];

                                                $prefix = isAke($datasPlat, 'prefixe', '');

                                                $prefix = strlen($prefix) ? $prefix . ' ' : $prefix;

                                                $item['name']   = $prefix . $resto['names'][$item['id']];
                                                $item['plats']  = [];

                                                $count = 0;

                                                $platesR = isAke($resto['auto']['plats'], $sp, []);

                                                foreach ($platesR as $platesRId) {
                                                    $catId = $resto['plats']['catalog'][$platesRId];
                                                    $price = $resto['plats']['prices'][$catId];

                                                    if (!in_array($platesRId, $tuples)) {
                                                        $count++;
                                                        $tuples[] = $platesRId;
                                                        $pprices[] = $price;
                                                        $item['plats'][] = $this->infoPlates($resto, $platesRId);
                                                    }
                                                }

                                                if ($count > 0) {
                                                    $item['min_price'] = min($pprices);
                                                    $item['max_price'] = max($pprices);
                                                    $item['nb'] = $count;

                                                    // dd($item);
                                                    $choosePlates[] = $item;
                                                }
                                            }
                                        }

                                        if (!empty($typesnonAuto)) {
                                            foreach ($typesnonAuto as $sp) {
                                                $pprices = [];

                                                $item = $tuples = [];
                                                $item['id']     = $sp;

                                                $datasPlat = $resto['datas'][$item['id']];

                                                $prefix = isAke($datasPlat, 'prefixe', '');

                                                $prefix = strlen($prefix) ? $prefix . ' ' : $prefix;
                                                $item['name']   = $prefix . $resto['names'][$item['id']];
                                                $item['plats']  = [];

                                                $count = 0;

                                                $platesR = isAke($resto['non_auto']['plats'], $sp, []);

                                                foreach ($platesR as $platesRId) {
                                                    $catId = $resto['plats']['catalog'][$platesRId];
                                                    $price = $resto['plats']['prices'][$catId];

                                                    if (!in_array($platesRId, $tuples)) {
                                                        $count++;
                                                        $tuples[] = $platesRId;
                                                        $pprices[] = $price;
                                                        $item['plats'][] = $this->infoPlates($resto, $platesRId);
                                                    }
                                                }

                                                if ($count > 0) {
                                                    $item['min_price'] = min($pprices);
                                                    $item['max_price'] = max($pprices);
                                                    $item['nb'] = $count;

                                                    // dd($item);
                                                    $choosePlates[] = $item;
                                                }
                                            }
                                        }

                                        $cps = [];

                                        $gfs        = isAke($resto, 'grandfathers', []);
                                        $aps        = isAke($resto, 'all_plats', []);
                                        $cats       = isAke(isAke($resto, 'plats', []), 'catalog', []);
                                        $catNames   = isAke(isAke($resto, 'plats', []), 'catalog_names', []);

                                        foreach ($choosePlates as $cp) {
                                            if (!isset($cp['plats']) && in_array($cp['id'], $composedId)) {
                                                if (!isset($gfs[$cp['id']])) {
                                                    $family = repo('segment')->getFamily($cp['id']);
                                                    $gfs[$cp['id']] = end($family);
                                                }

                                                if (isset($gfs[$cp['id']])) {
                                                    $gp = $gfs[$cp['id']];

                                                    $idCat = isset($cats[$cp['id']]) ? $cats[$cp['id']] : false;

                                                    $cn = $gp['name'];

                                                    if ($idCat) {
                                                        $cn = isset($catNames[$idCat]) ? $catNames[$idCat] : $cp['name'];
                                                    }

                                                    if (!isset($cps[$cp['name']])) {
                                                        $cps[$cp['name']] = [
                                                            'nb' => 0,
                                                            'min_price' => 1000000,
                                                            'max_price' => 0,
                                                            'id' => $gp['id'],
                                                            'name' => $cp['name'],
                                                            'plats' => []
                                                        ];
                                                    }

                                                    $price              = $cp['min_price'];
                                                    $cp['price']        = $price;
                                                    $cp['catalog_name'] = $cn;
                                                    $cp['catalog_id']   = $idCat;

                                                    unset($cp['min_price'], $cp['max_price'], $cp['nb'], $cp['ordre']);

                                                    $i = isset($aps[$cp['id']]) ? $aps[$cp['id']] : [];

                                                    if (!empty($i)) {
                                                        $i = current($i);

                                                        if (!isset($cp['description'])) {
                                                            $cp['description'] = isAke($i, 'description', '');
                                                        } else {
                                                            if (empty($cp['description'])) {
                                                                $cp['description'] = isAke($i, 'description', '');
                                                            }
                                                        }

                                                        if (!isset($cp['accompagnement'])) {
                                                            $cp['accompagnement'] = isAke($i, 'accompagnement', '');
                                                        } else {
                                                            if (empty($cp['accompagnement'])) {
                                                                $cp['accompagnement'] = isAke($i, 'accompagnement', '');
                                                            }
                                                        }
                                                    }

                                                    $cps[$cp['name']]['plats'][] = $cp;
                                                    $cps[$cp['name']]['nb']++;
                                                    $cps[$cp['name']]['min_price'] = min($cps[$cp['name']]['min_price'], $price);
                                                    $cps[$cp['name']]['max_price'] = max($cps[$cp['name']]['max_price'], $price);
                                                }
                                            } else {
                                                $cps[$cp['name']] = [
                                                    'nb' => $cp['nb'],
                                                    'min_price' => $cp['min_price'],
                                                    'max_price' => $cp['max_price'],
                                                    'id' => $cp['id'],
                                                    'name' => $cp['name'],
                                                ];
                                            }
                                        }

                                        $choosePlates = true === $hasCompo ? array_values($cps) : $choosePlates;

                                        if (!$hasCompo) {
                                            $plats = isAke($resto, 'plats', []);
                                            $catalog = isAke($plats, 'catalog', []);

                                            $cp = [];

                                            foreach($choosePlates as $cplate) {
                                                $hasPlates = isset($cplate['plats']);

                                                if ($hasPlates) {
                                                    $idc = isset($catalog[$cplate['id']]) ? $catalog[$cplate['id']] : false;

                                                    $description = isset(isAke($resto, 'descriptions', [])[$cplate['id']])
                                                    ? isAke($resto, 'descriptions', [])[$cplate['id']] : '';

                                                    $accompagnement = isset(isAke($resto, 'accompagnements', [])[$cplate['id']])
                                                    ? isAke($resto, 'accompagnements', [])[$cplate['id']] : '';

                                                    if (false !== $idc) {
                                                        $name = isset($plats['catalog_names'][$idc]) ? $plats['catalog_names'][$idc] : $cplate['name'];
                                                        $cplate['name']             = $cplate['name'];
                                                        $cplate['catalog_name']     = $name;
                                                        $cplate['catalog_id']       = $idc;
                                                        $cplate['description']      = $description;
                                                        $cplate['accompagnement']   = $accompagnement;
                                                    }
                                                } else {
                                                    $cplate['plats'] = [];

                                                    $accompagnements = isAke($resto, 'accompagnements', []);
                                                    $descriptions = isAke($resto, 'descriptions', []);

                                                    $prices = $resto['plats']['prices'];

                                                    foreach ($ocp as $olp) {
                                                        if ($olp['id'] == $cplate['id']) {
                                                            $pl = [];
                                                            $idc = $olp['catalog_id'];

                                                            $description = isset($descriptions[$idc]) ? $descriptions[$idc] : '';
                                                            $accompagnement = isset($accompagnements[$idc]) ? $accompagnements[$idc] : '';
                                                            $name = isset($plats['catalog_names'][$idc]) ? $plats['catalog_names'][$idc] : $cplate['name'];

                                                            $price = isset($prices[$idc]) ? $prices[$idc] : 0;

                                                            $pl['id']               = $cplate['id'];
                                                            $pl['name']             = $name;
                                                            $pl['catalog_name']     = $name;
                                                            $pl['catalog_id']       = $idc;
                                                            $pl['description']      = $description;
                                                            $pl['accompagnement']   = $accompagnement;
                                                            $pl['price']            = (float) $price;

                                                            $cplate['plats'][] = $pl;
                                                        }
                                                    }
                                                }

                                                $cp[] = $cplate;
                                            }

                                            $choosePlates = $cp;

                                            $keyCache = sha1($reseller->id.$this->session_id().'platschoisis');

                                            redis()->set($keyCache, serialize($cp));
                                        }

                                        $conditions_acompte_amount = isAke($resto['options'], 'conditions_acompte_amount', false);

                                        $conditions_delai_annulation = isAke($resto['options'], 'conditions_delai_annulation', 24);

                                        if ($conditions_acompte_amount) {
                                            $conditions_acompte_amount = "$conditions_acompte_amount €";
                                        } else {
                                            $conditions_acompte_amount = '0 €';
                                        }

                                        $conditions_delai_annulation = "$conditions_delai_annulation h";

                                        $itemAdd = [
                                            'id'            => (int) $reseller->id,
                                            'name'          => $company->name,
                                            'stock'         => $rStock,
                                            'themes'        => $themes,
                                            'context'       => $context,
                                            'activity'      => $activity,
                                            'activities'    => $activitiesResto,
                                            'address'       => $company->address . ', ' . $company->city,
                                            'city'          => $company->city,
                                            'plats_choisis' => $choosePlates,
                                            'plats_ranges'  => isAke($resto, 'plats_ranges', []),
                                            'distance'      => (int) ($km * 1000),
                                            'last_minute'   => $last_minute,
                                            'themes_resto'  => isAke($resto, 'themes', []),
                                            'rate'          => (double) $resto['rate'],
                                            'has_news'      => isAke($resto, 'has_news', false),
                                            'has_myzelift'  => isAke($resto, 'has_myzelift', false),
                                            'has_favorite'  => $has_favorite,
                                            'conditions_acompte'=> isAke($resto['options'], 'conditions_acompte', false),
                                            'conditions_acompte_amount'=> $conditions_acompte_amount,
                                            'conditions_delai_annulation'=> $conditions_delai_annulation,
                                            'average_price' => $average_price,
                                            'service'       => $service
                                        ];

                                        if (!empty($plats_disponibles)) {
                                            $coll = lib('collection', [$plats_disponibles]);

                                            $min_price  = $coll->min('min_price');
                                            $max_price  = $coll->max('max_price');
                                            $nb_plats   = $coll->sum('nb');

                                            $ups = $tuplesUp = [];

                                            foreach ($plats_disponibles as $tmpRow) {
                                                $upliftRow = isAke($tmpRow, 'uplift', false);

                                                if (false !== $upliftRow) {
                                                    if (!is_array($upliftRow)) {
                                                        if (!in_array($upliftRow['id'], $tuplesUp)) {
                                                            $ups[] = $upliftRow;
                                                            $tuplesUp[] = $upliftRow['id'];
                                                        }
                                                    }
                                                }
                                            }

                                            $pds = $tups = [];

                                            foreach ($plats_disponibles as $pd) {
                                                if (!in_array($pd['id'], $tups)) {
                                                    $pds[] = $pd;
                                                    $tups[] = $pd['id'];
                                                }
                                            }

                                            if (!empty($suggestions) && !empty($food)) {
                                                $ndps = [];

                                                foreach ($plats_disponibles as $pdis) {
                                                    $idPdis = $pdis['id'];

                                                    foreach ($assocsSuggIds as $idSugg => $arraySugg) {
                                                        if (!in_array($idPdis, $arraySugg)) {
                                                            $ndps[] = $pdis;
                                                        }
                                                    }
                                                }

                                                $plats_disponibles = $ndps;

                                                if (!empty($spe_sugg)) {
                                                    foreach ($spe_sugg as $sp) {
                                                        $pprices = [];

                                                        $item = $tuples = [];
                                                        $item['id']     = $sp;

                                                        $datasPlat = $resto['datas'][$item['id']];

                                                        $prefix = isAke($datasPlat, 'prefixe', '');

                                                        $prefix = strlen($prefix) ? $prefix . ' ' : $prefix;
                                                        $item['name']   = $prefix . $resto['names'][$item['id']];

                                                        $item['plats'] = [];

                                                        $count = 0;

                                                        $platesR = isAke($resto['specialites']['plats'], $sp, []);

                                                        foreach ($platesR as $platesRId) {
                                                            $catId = $resto['plats']['catalog'][$platesRId];
                                                            $price = $resto['plats']['prices'][$catId];

                                                            if (!in_array($platesRId, $tuples)) {
                                                                $count++;
                                                                $tuples[] = $platesRId;
                                                                $pprices[] = $price;
                                                                $item['plats'][] = $this->infoPlates($resto, $platesRId);
                                                            }
                                                        }

                                                        if ($count > 0) {
                                                            $item['min_price'] = min($pprices);
                                                            $item['max_price'] = max($pprices);

                                                            $item['nb'] = $count;

                                                            $itemPlates = lib('collection', [isAke($item, 'plats', [])])->sortBy('ordre')->toArray();
                                                            $item['plats'] = $itemPlates;

                                                            $choosePlates[] = $item;
                                                        }
                                                    }
                                                }

                                                if (!empty($auto_sugg)) {
                                                    foreach ($auto_sugg as $sp) {
                                                        $pprices = [];

                                                        $item = $tuples = [];
                                                        $item['id']     = $sp;

                                                        $datasPlat = $resto['datas'][$item['id']];

                                                        $prefix = isAke($datasPlat, 'prefixe', '');

                                                        $prefix = strlen($prefix) ? $prefix . ' ' : $prefix;
                                                        $item['name']   = $prefix . $resto['names'][$item['id']];
                                                        $item['plats']  = [];

                                                        $count = 0;

                                                        $platesR = isAke($resto['auto']['plats'], $sp, []);

                                                        foreach ($platesR as $platesRId) {
                                                            $catId = $resto['plats']['catalog'][$platesRId];
                                                            $price = $resto['plats']['prices'][$catId];

                                                            if (!in_array($platesRId, $tuples)) {
                                                                $count++;
                                                                $tuples[] = $platesRId;
                                                                $pprices[] = $price;
                                                                $item['plats'][] = $this->infoPlates($resto, $platesRId);
                                                            }
                                                        }

                                                        if ($count > 0) {
                                                            $item['min_price'] = min($pprices);
                                                            $item['max_price'] = max($pprices);
                                                            $item['nb'] = $count;

                                                            $itemPlates = lib('collection', [isAke($item, 'plats', [])])->sortBy('ordre')->toArray();
                                                            $item['plats'] = $itemPlates;

                                                            $choosePlates[] = $item;
                                                        }
                                                    }
                                                }

                                                if (!empty($non_auto_sugg)) {
                                                    foreach ($non_auto_sugg as $sp) {
                                                        $pprices = [];

                                                        $item = $tuples = [];
                                                        $item['id']     = $sp;

                                                        $datasPlat = $resto['datas'][$item['id']];

                                                        $prefix = isAke($datasPlat, 'prefixe', '');

                                                        $prefix = strlen($prefix) ? $prefix . ' ' : $prefix;
                                                        $item['name']   = $prefix . $resto['names'][$item['id']];
                                                        $item['plats']  = [];

                                                        $count = 0;

                                                        $platesR = isAke($resto['non_auto']['plats'], $sp, []);

                                                        foreach ($platesR as $platesRId) {
                                                            $catId = $resto['plats']['catalog'][$platesRId];
                                                            $price = $resto['plats']['prices'][$catId];

                                                            if (!in_array($platesRId, $tuples)) {
                                                                $count++;
                                                                $tuples[] = $platesRId;
                                                                $pprices[] = $price;
                                                                $item['plats'][] = $this->infoPlates($resto, $platesRId);
                                                            }
                                                        }

                                                        if ($count > 0) {
                                                            $item['min_price'] = min($pprices);
                                                            $item['max_price'] = max($pprices);
                                                            $item['nb'] = $count;

                                                            $itemPlates = lib('collection', [isAke($item, 'plats', [])])->sortBy('ordre')->toArray();
                                                            $item['plats'] = $itemPlates;

                                                            $choosePlates[] = $item;
                                                        }
                                                    }
                                                }
                                            }

                                            $itemAdd['plats_disponibles'] = [
                                                'nb'        => $nb_plats,
                                                'max_price' => $max_price,
                                                'min_price' => $min_price
                                            ];

                                            if ($nb_plats != count($choosePlates)) {
                                                $nc = [];

                                                foreach ($choosePlates as $cp) {
                                                    $nc[] = $cp['id'];
                                                    $plats = isAke($cp, 'plats', []);

                                                    if (!empty($plats)) {
                                                        foreach ($plats as $pl) {
                                                            $nc[] = $pl['id'];
                                                        }
                                                    }
                                                }

                                                foreach ($plats_disponibles as $tmpRow) {
                                                    if (!in_array($tmpRow['id'], $nc)) {
                                                        $i = $this->infoPlates($resto, $tmpRow['id']);

                                                        $choosePlates[] = [
                                                            'id'        =>  $tmpRow['id'],
                                                            'name'      =>  $tmpRow['name'],
                                                            'min_price' =>  $i['price'],
                                                            'max_price' =>  $i['price'],
                                                            'ordre'     =>  isAke($i, 'ordre', 1),
                                                            'nb'        =>  isAke($tmpRow, 'nb', 1),
                                                        ];
                                                    }
                                                }

                                                if (!empty($suggestions)) {
                                                    $choosePlates = $this->analyseSuggestions(
                                                        $choosePlates,
                                                        $suggestions,
                                                        $resto
                                                    );
                                                }

                                                $c = lib('collection', [$choosePlates])->sortBy('name')->toArray();
                                                $choosePlates = array_values($c);

                                                $itemAdd['plats_choisis'] = $choosePlates;
                                                $keyCache = sha1($reseller->id . $this->session_id() . 'platschoisis');

                                                redis()->set($keyCache, serialize($choosePlates));
                                            } else {
                                                $c = lib('collection', [$choosePlates])->sortBy('name')->toArray();
                                                $choosePlates = array_values($c);
                                                $itemAdd['plats_choisis'] = $choosePlates;
                                                $keyCache = sha1($reseller->id . $this->session_id() . 'platschoisis');

                                                redis()->set($keyCache, serialize($choosePlates));
                                            }

                                            $itemAdd['uplifts'] = isAke(self::$datas, 'uplifts_' . $reseller->id, $ups);
                                        }

                                        $collection[] = $itemAdd;
                                    } else {
                                        $jpds = [];

                                        foreach ($plats_disponibles as $jpd) {
                                            $contraintesJour = isset($jours[$jpd['catalog_id']]) ? $jours[$jpd['catalog_id']] : [];
                                            $contraintesService = isset($services[$jpd['catalog_id']]) ? $services[$jpd['catalog_id']] : [];

                                            $add = true;

                                            if (!empty($contraintesJour)) {
                                                $add = in_array($jour, $contraintesJour);
                                            }

                                            if (!empty($contraintesService) && $add) {
                                                $add = in_array($service, $contraintesService);
                                            }

                                            if ($add) {
                                                $jpds[] = $jpd;
                                            }
                                        }

                                        $plats_disponibles = $jpds;

                                        $plats_disponibles = $this->analyzeUplifts(
                                            $uplifts,
                                            $plats_disponibles,
                                            $date,
                                            $hour,
                                            $nb_customer,
                                            $total_price
                                        );

                                        if (!empty($specialities)) {
                                            foreach ($specialities as $sp) {
                                                $spfather = $sp;
                                                $pprices = [];

                                                $item = $tuples = [];

                                                $item['id'] = $sp;

                                                $datasPlat = $resto['datas'][$item['id']];

                                                $prefix = isAke($datasPlat, 'prefixe', '');

                                                $prefix = strlen($prefix) ? $prefix . ' ' : $prefix;
                                                $spfatherName = $item['name'] = $prefix . $resto['names'][$item['id']];

                                                $item['plats'] = [];

                                                $count = 0;

                                                $platesR = isAke($resto['specialites']['plats'], $sp, []);

                                                if (!empty($platesR)) {
                                                    foreach ($platesR as $platesRId) {
                                                        $catId = $resto['plats']['catalog'][$platesRId];
                                                        $price = $resto['plats']['prices'][$catId];

                                                        if (!in_array($platesRId, $tuples)) {
                                                            $addplate = true;

                                                            if (0 < $budget) {
                                                                $addplate = $budget >= $price;
                                                            }

                                                            if ($addplate) {
                                                                $count++;
                                                                $tuples[] = $platesRId;
                                                                $pprices[] = $price;

                                                                $item['plats'][] = $this->infoPlates($resto, $platesRId);
                                                            }
                                                        }
                                                    }

                                                    if ($count > 0) {
                                                        $item['min_price'] = min($pprices);
                                                        $item['max_price'] = max($pprices);
                                                        $item['nb'] = $count;

                                                        $itemPlates = lib('collection', [isAke($item, 'plats', [])])->sortBy('ordre')->toArray();
                                                        $item['plats'] = array_values($itemPlates);

                                                        $choosePlates[] = $item;
                                                    }
                                                } else {
                                                    $fatherItem = $item;
                                                    $children = repo('segment')->getAllFamily($sp);

                                                    foreach ($children as $ch) {
                                                        $sp = $ch['id'];
                                                        // $pprices = [];

                                                        // $item = $tuples = [];

                                                        // $item['id'] = $sp;

                                                        // $datasPlat = $resto['datas'][$item['id']];

                                                        // $prefix = isAke($datasPlat, 'prefixe', '');

                                                        // $prefix = strlen($prefix) ? $prefix . ' ' : $prefix;
                                                        // $item['name']   = $prefix . $resto['names'][$item['id']];

                                                        // $item['plats'] = [];

                                                        // $count = 0;

                                                        $platesR = isAke($resto['specialites']['plats'], $sp, []);

                                                        if (!empty($platesR)) {
                                                            foreach ($platesR as $platesRId) {
                                                                $catId = $resto['plats']['catalog'][$platesRId];
                                                                $price = $resto['plats']['prices'][$catId];

                                                                if (!in_array($catId, $tuples)) {
                                                                    $addplate = true;

                                                                    if (0 < $budget) {
                                                                        $addplate = $budget >= $price;
                                                                    }

                                                                    if ($addplate) {
                                                                        $count++;
                                                                        $tuples[] = $catId;
                                                                        $pprices[] = $price;

                                                                        $item['plats'][] = $this->infoPlates($resto, $platesRId);
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }

                                                    if ($count > 0) {
                                                        $item['min_price'] = min($pprices);
                                                        $item['max_price'] = max($pprices);
                                                        $item['nb'] = $count;
                                                        $item['father'] = $spfather;
                                                        $item['father_name'] = $spfatherName;

                                                        $itemPlates = lib('collection', [isAke($item, 'plats', [])])
                                                        ->sortBy('ordre')
                                                        ->toArray();

                                                        $item['plats'] = array_values($itemPlates);

                                                        $choosePlates[] = $item;
                                                    }
                                                }
                                            }
                                        }

                                        if (empty($choosePlates) && !empty($specialities) && 0 < $budget) {
                                            continue;
                                        }

                                        $suggs = 0;

                                        if (!empty($spe_sugg)) {
                                            $first = true;

                                            foreach ($spe_sugg as $sp) {
                                                $pprices = [];

                                                $item = $tuples = [];
                                                $item['id']     = $sp;

                                                $datasPlat = $resto['datas'][$item['id']];

                                                $prefix = isAke($datasPlat, 'prefixe', '');

                                                $prefix = strlen($prefix) ? $prefix . ' ' : $prefix;
                                                $item['name'] = $prefix . isAke($resto['names'], $item['id'], '');

                                                $item['plats'] = [];

                                                $count = 0;

                                                $platesR = isAke($resto['specialites']['plats'], $sp, []);

                                                foreach ($platesR as $platesRId) {
                                                    $catId = $resto['plats']['catalog'][$platesRId];
                                                    $price = $resto['plats']['prices'][$catId];

                                                    if (!in_array($platesRId, $tuples)) {
                                                        $count++;
                                                        $tuples[] = $platesRId;
                                                        $pprices[] = $price;
                                                        $item['plats'][] = $this->infoPlates($resto, $platesRId);
                                                    }
                                                }

                                                if ($count > 0) {
                                                    $item['min_price'] = min($pprices);
                                                    $item['max_price'] = max($pprices);

                                                    $item['nb'] = $count;

                                                    $itemPlates = lib('collection', [isAke($item, 'plats', [])])->sortBy('ordre')->toArray();
                                                    $item['plats'] = $itemPlates;

                                                    $choosePlates[] = $item;

                                                    if ($first) {
                                                        $suggs++;
                                                        $first = false;
                                                    }
                                                }
                                            }
                                        }

                                        if (!empty($typesAuto)) {
                                            foreach ($typesAuto as $sp) {
                                                $pprices = [];

                                                $item = $tuples = [];
                                                $item['id']     = $sp;

                                                $datasPlat = $resto['datas'][$item['id']];

                                                $prefix = isAke($datasPlat, 'prefixe', '');

                                                $prefix = strlen($prefix) ? $prefix . ' ' : $prefix;
                                                $item['name']   = $prefix . $resto['names'][$item['id']];
                                                $item['plats']  = [];

                                                $count = 0;

                                                $platesR = isAke($resto['auto']['plats'], $sp, []);

                                                foreach ($platesR as $platesRId) {
                                                    $catId = $resto['plats']['catalog'][$platesRId];
                                                    $price = $resto['plats']['prices'][$catId];

                                                    if (!in_array($platesRId, $tuples)) {
                                                        $count++;
                                                        $tuples[]           = $platesRId;
                                                        $pprices[]          = $price;
                                                        $item['plats'][]    = $this->infoPlates($resto, $platesRId);
                                                    }
                                                }

                                                if ($count > 0) {
                                                    $item['min_price'] = min($pprices);
                                                    $item['max_price'] = max($pprices);
                                                    $item['nb'] = $count;

                                                    $itemPlates = lib('collection', [isAke($item, 'plats', [])])->sortBy('ordre')->toArray();
                                                    $item['plats'] = $itemPlates;

                                                    $choosePlates[] = $item;
                                                }
                                            }
                                        }

                                        if (!empty($auto_sugg)) {
                                            $first = true;

                                            foreach ($auto_sugg as $sp) {
                                                $pprices = [];

                                                $item = $tuples = [];
                                                $item['id']     = $sp;

                                                $datasPlat = $resto['datas'][$item['id']];

                                                $prefix = isAke($datasPlat, 'prefixe', '');

                                                $prefix = strlen($prefix) ? $prefix . ' ' : $prefix;
                                                $item['name']   = $prefix . $resto['names'][$item['id']];
                                                $item['plats']  = [];

                                                $count = 0;

                                                $platesR = isAke($resto['auto']['plats'], $sp, []);

                                                foreach ($platesR as $platesRId) {
                                                    $catId = $resto['plats']['catalog'][$platesRId];
                                                    $price = $resto['plats']['prices'][$catId];

                                                    if (!in_array($platesRId, $tuples)) {
                                                        $count++;
                                                        $tuples[] = $platesRId;
                                                        $pprices[] = $price;
                                                        $item['plats'][] = $this->infoPlates($resto, $platesRId);
                                                    }
                                                }

                                                if ($count > 0) {
                                                    $item['min_price'] = min($pprices);
                                                    $item['max_price'] = max($pprices);
                                                    $item['nb'] = $count;

                                                    $itemPlates = lib(
                                                        'collection',
                                                        [isAke($item, 'plats', [])]
                                                    )->sortBy('ordre')->toArray();

                                                    $item['plats'] = $itemPlates;

                                                    $choosePlates[] = $item;

                                                    if ($first) {
                                                        $suggs++;
                                                        $first = false;
                                                    }
                                                }
                                            }
                                        }

                                        if (!empty($non_auto_sugg)) {
                                            $first = true;

                                            foreach ($non_auto_sugg as $sp) {
                                                $pprices = [];

                                                $item = $tuples = [];
                                                $item['id']     = $sp;

                                                $datasPlat = $resto['datas'][$item['id']];

                                                $prefix = isAke($datasPlat, 'prefixe', '');

                                                $prefix = strlen($prefix) ? $prefix . ' ' : $prefix;
                                                $item['name']   = $prefix . $resto['names'][$item['id']];
                                                $item['plats']  = [];

                                                $count = 0;

                                                $platesR = isAke($resto['non_auto']['plats'], $sp, []);

                                                foreach ($platesR as $platesRId) {
                                                    $catId = $resto['plats']['catalog'][$platesRId];
                                                    $price = $resto['plats']['prices'][$catId];

                                                    if (!in_array($platesRId, $tuples)) {
                                                        $count++;
                                                        $tuples[] = $platesRId;
                                                        $pprices[] = $price;
                                                        $item['plats'][] = $this->infoPlates($resto, $platesRId);
                                                    }
                                                }

                                                if ($count > 0) {
                                                    $item['min_price'] = min($pprices);
                                                    $item['max_price'] = max($pprices);
                                                    $item['nb'] = $count;

                                                    $itemPlates = lib('collection', [isAke($item, 'plats', [])])->sortBy('ordre')->toArray();
                                                    $item['plats'] = $itemPlates;

                                                    $choosePlates[] = $item;

                                                    if ($first) {
                                                        $suggs++;
                                                        $first = false;
                                                    }
                                                }
                                            }
                                        }

                                        if (!empty($typesnonAuto)) {
                                            foreach ($typesnonAuto as $sp) {
                                                $pprices = [];

                                                $item = $tuples = [];
                                                $item['id']     = $sp;

                                                $datasPlat = $resto['datas'][$item['id']];

                                                $prefix = isAke($datasPlat, 'prefixe', '');

                                                $prefix         = strlen($prefix) ? $prefix . ' ' : $prefix;
                                                $item['name']   = $prefix . $resto['names'][$item['id']];
                                                $item['plats']  = [];

                                                $count = 0;

                                                $platesR = isAke($resto['non_auto']['plats'], $sp, []);

                                                foreach ($platesR as $platesRId) {
                                                    $catId = $resto['plats']['catalog'][$platesRId];
                                                    $price = $resto['plats']['prices'][$catId];

                                                    if (!in_array($platesRId, $tuples)) {
                                                        $count++;
                                                        $tuples[]           = $platesRId;
                                                        $pprices[]          = $price;
                                                        $item['plats'][]    = $this->infoPlates($resto, $platesRId);
                                                    }
                                                }

                                                if ($count > 0) {
                                                    $item['min_price'] = min($pprices);
                                                    $item['max_price'] = max($pprices);
                                                    $item['nb'] = $count;

                                                    $itemPlates = lib('collection', [isAke($item, 'plats', [])])->sortBy('ordre')->toArray();
                                                    $item['plats'] = array_values($itemPlates);

                                                    $choosePlates[] = $item;
                                                }
                                            }
                                        }

                                        if (!empty($familyPlates)) {
                                            foreach ($familyPlates as $idFamily => $tab) {
                                                $pprices = [];

                                                $item = $tuples = [];
                                                $item['id']     = $idFamily;

                                                $datasPlat = $resto['datas'][$item['id']];

                                                $prefix = isAke($datasPlat, 'prefixe', '');

                                                $prefix = strlen($prefix) ? $prefix . ' ' : $prefix;
                                                $item['name']   = $prefix . $resto['names'][$item['id']];
                                                $item['plats']  = [];

                                                $count = 0;

                                                foreach ($tab as $ptab) {
                                                    if (!in_array($ptab['id'], $tuples)) {
                                                        $count++;
                                                        $tuples[] = $ptab['id'];
                                                        $pprices[] = $ptab['price'];
                                                        $item['plats'][] = $this->infoPlates($resto, $ptab['id']);
                                                    }
                                                }

                                                if ($count > 0) {
                                                    $item['min_price'] = min($pprices);
                                                    $item['max_price'] = max($pprices);
                                                    $item['nb'] = $count;

                                                    $itemPlates = lib('collection', [isAke($item, 'plats', [])])->sortBy('ordre')->toArray();
                                                    $item['plats'] = array_values($itemPlates);

                                                    $choosePlates[] = $item;
                                                }
                                            }
                                        }

                                        if (!empty($plats_disponibles)) {
                                            $pds = $tups = [];

                                            foreach ($plats_disponibles as $pd) {
                                                if (!in_array($pd['id'], $tups)) {
                                                    $pds[] = $pd;
                                                    $tups[] = $pd['id'];
                                                }
                                            }

                                            $plats_disponibles = $pds;

                                            $coll = lib('collection', [$plats_disponibles]);

                                            $min_price  = $coll->min('min_price');
                                            $max_price  = $coll->max('max_price');
                                            $nb_plats   = $coll->sum('nb');

                                            $ups = $tuplesUp = [];

                                            foreach ($plats_disponibles as $tmpRow) {
                                                $upliftRow = isAke($tmpRow, 'uplift', false);

                                                if (false !== $upliftRow) {
                                                    if (!in_array($upliftRow['id'], $tuplesUp)) {
                                                        $ups[]      = $upliftRow;
                                                        $tuplesUp[] = $upliftRow['id'];
                                                    }
                                                }
                                            }

                                            $conditions_acompte_amount = isAke(
                                                $resto['options'],
                                                'conditions_acompte_amount',
                                                false
                                            );

                                            $conditions_delai_annulation = isAke(
                                                $resto['options'],
                                                'conditions_delai_annulation',
                                                24
                                            );

                                            if ($conditions_acompte_amount) {
                                                $conditions_acompte_amount = "$conditions_acompte_amount €";
                                            } else {
                                                $conditions_acompte_amount = '0 €';
                                            }

                                            $conditions_delai_annulation = "$conditions_delai_annulation h";

                                            $item = [
                                                'id'                => (int) $reseller->id,
                                                'name'              => $company->name,
                                                'address'           => $company->address . ', ' . $company->city,
                                                'city'              => $company->city,
                                                'plats_ranges'      => isAke($resto, 'plats_ranges', []),
                                                'plats_disponibles' => [
                                                    'nb'            => $nb_plats,
                                                    'max_price'     => $max_price,
                                                    'min_price'     => $min_price
                                                ],
                                                'uplifts'           => isAke(
                                                    self::$datas,
                                                    'uplifts_' . $reseller->id,
                                                    $ups
                                                ),
                                                'last_minute'       => $last_minute,
                                                'rate'              => (double) $resto['rate'],
                                                'average_price'     => isAke($resto, 'average_price', 1),
                                                'themes_resto'      => isAke($resto, 'themes', []),
                                                'has_news'          => isAke($resto, 'has_news', false),
                                                'has_myzelift'      => isAke($resto, 'has_myzelift', false),
                                                'has_favorite'      => $has_favorite,
                                                'conditions_acompte'=> isAke(
                                                    $resto['options'],
                                                    'conditions_acompte',
                                                    false
                                                ),
                                                'conditions_acompte_amount'=> $conditions_acompte_amount,
                                                'conditions_delai_annulation'=> $conditions_delai_annulation,
                                                'context'           => $context,
                                                'themes'            => $themes,
                                                'activity'          => $activity,
                                                'activities'        => $activitiesResto,
                                                'service'           => $service,
                                                'stock'             => $rStock,
                                                'distance'          => (int) ($km * 1000)
                                            ];

                                            $addItem = true;

                                            if (!empty($suggestions) && empty($food)) {
                                                $addItem = $suggs == count($suggestions);
                                            }

                                            if (!empty($choosePlates)) {
                                                if (!empty($suggestions)) {
                                                    $choosePlates = $this->analyseSuggestions(
                                                        $choosePlates,
                                                        $suggestions,
                                                        $resto
                                                    );
                                                }

                                                $item['plats_choisis'] = $choosePlates;

                                                $keyCache = sha1($reseller->id . $this->session_id() . 'platschoisis');

                                                redis()->set($keyCache, serialize($choosePlates));
                                            }

                                            if ($addItem) {
                                                $collection[] = $item;
                                            }
                                        }
                                    }

                                    $numberRestos++;

                                    $ir[$reseller_id]['all_plats']      = $resto['all_plats'];
                                    $ir[$reseller_id]['labels']         = $labels;
                                    $ir[$reseller_id]['guides']         = $guides;
                                    $ir[$reseller_id]['thematiques']    = $thematiquesResto;
                                    $ir[$reseller_id]['preferences']    = $prefsResto;
                                    $ir[$reseller_id]['activites']      = $activitesResto;

                                    foreach ($labels as $labelResto) {
                                        if (!in_array($labelResto, $final_labels_dispo)) {
                                            $final_labels_dispo[] = $labelResto;
                                        }
                                    }

                                    foreach ($themes as $thematiqueResto) {
                                        if (!in_array($thematiqueResto, $final_thematiques_dispo)) {
                                            $final_thematiques_dispo[] = $thematiqueResto;
                                        }
                                    }

                                    foreach ($prefsResto as $pr) {
                                        if (!in_array($pr, $final_preferences_dispo)) {
                                            $final_preferences_dispo[] = $pr;
                                        }
                                    }

                                    foreach ($activitesResto as $id) {
                                        if (!in_array($id, $final_activites_dispo)) {
                                            $final_activites_dispo[] = $id;
                                        }
                                    }

                                    $ir[$reseller_id]['activities']             = $activitiesResto;
                                    $ir[$reseller_id]['types_auto']             = $resto['auto']['dispos'];
                                    $ir[$reseller_id]['types_non_auto']         = $resto['non_auto']['dispos'];
                                    $ir[$reseller_id]['specialites']            = $resto['specialites']['dispos'];
                                    $ir[$reseller_id]['specialites_families']   = $resto['specialites']['families'];
                                    $ir[$reseller_id]['plats_families']         = $resto['plats']['families'];
                                    $ir[$reseller_id]['pois']                   = $resto['pois']['dispos'];
                                    $ir[$reseller_id]['pois_families']          = $resto['pois']['families'];
                                    $ir[$reseller_id]['plats_catalog']          = array_keys($resto['plats']['catalog']);

                                    $final_types_auto_dispo = array_merge(
                                        $final_types_auto_dispo,
                                        $resto['auto']['dispos']
                                    );

                                    $final_types_non_auto_dispo = array_merge(
                                        $final_types_non_auto_dispo,
                                        $resto['non_auto']['dispos']
                                    );

                                    $final_specialites_dispo = array_merge(
                                        $final_specialites_dispo,
                                        $resto['specialites']['dispos']
                                    );

                                    $final_pois_dispo = array_merge(
                                        $final_pois_dispo,
                                        $resto['pois']['dispos']
                                    );

                                    $final_families_pois_dispo = array_merge(
                                        $final_families_pois_dispo,
                                        $resto['pois']['families']
                                    );

                                    $final_plats_dispo = array_merge(
                                        $final_plats_dispo,
                                        array_keys($resto['plats']['catalog'])
                                    );

                                    self::$datas['plats_' . $reseller_id] = array_values(
                                        array_unique(
                                            array_keys($resto['plats']['catalog'])
                                        )
                                    );

                                    $final_families_plats_dispo = array_merge(
                                        $final_families_plats_dispo,
                                        $resto['plats']['families']
                                    );

                                    $final_families_specialites_dispo = array_merge(
                                        $final_families_specialites_dispo,
                                        $resto['specialites']['families']
                                    );
                                }
                            }
                        }
                    }
                }

                if ($out) {
                    $collection = array_values(
                        lib('collection', [$collection])->sortBy('distance')->toArray()
                    );

                    $sid = $this->session_id();

                    if (!isset($service)) {
                        $service = 1;
                    }

                    Save::setExpire(
                        'offer.in.' . $filter['account_id'] . '.' . $sid,
                        serialize([
                            'jour'          => $jour,
                            'service'       => $service,
                            'filter'        => $filter,
                            'restaurants'   => $collection
                        ]),
                        24 * 3600
                    );

                    Save::setExpire('infos.data.resto.' . $account_id . '.' . $sid, serialize($ir), 24 * 3600);

                    return [
                        'restaurants'   => $collection,
                        'distances'     => [
                            'min'       => (int) ($distanceMin * 1000),
                            'max'       => (int) ($distanceMax * 1000)
                        ]
                    ];
                }

                $results =  [
                    'total'                         => count($collection),
                    'distances'                     => [
                        'min' => (int) ($distanceMin * 1000),
                        'max' => (int) ($distanceMax * 1000)
                    ],
                    'restaurants'                   => $collection,
                    'pois_dispo'                    => array_values(array_unique($final_pois_dispo)),
                    'families_pois_dispo'           => array_values(array_unique($final_families_pois_dispo)),
                    'specialites_dispo'             => array_values(array_unique($final_specialites_dispo)),
                    'families_specialites_dispo'    => array_values(array_unique($final_families_specialites_dispo)),
                    'preferences_dispo'             => array_values(array_unique($final_preferences_dispo)),
                    'types_auto_dispo'              => array_values(array_unique($final_types_auto_dispo)),
                    'types_non_auto_dispo'          => array_values(array_unique($final_types_non_auto_dispo)),
                    'plats_dispo'                   => array_values(array_unique($final_plats_dispo)),
                    'families_plats_dispo'          => array_values(array_unique($final_families_plats_dispo)),
                    'labels_dispo'                  => array_values(array_unique($final_labels_dispo)),
                    'thematiques_dispo'             => array_values(array_unique($final_thematiques_dispo)),
                    'activites_dispo'               => array_values(array_unique($final_activites_dispo))
                ];

                // redis()->set($keyCache, serialize($results));
                // redis()->expire($keyCache, 120);

                return $results;
            }
        }

        /**
         * [infoPlates description]
         * @param  array    $resto [description]
         * @param  int      $id    [description]
         * @return array    [description]
         */
        private function infoPlates(array $resto, $id)
        {
            $catId          = $resto['plats']['catalog'][$id];
            $price          = $resto['plats']['prices'][$catId];
            $name           = $resto['plats']['names'][$id];
            $ordre          = $resto['plats']['ordre'][$id];
            $catalog_name   = $resto['plats']['catalog_names'][$catId];

            $description = isset(isAke($resto, 'descriptions', [])[$catId])
            ? isAke($resto, 'descriptions', [])[$catId] : null;

            $accompagnement = isset(isAke($resto, 'accompagnements', [])[$catId])
            ? isAke($resto, 'accompagnements', [])[$catId] : null;

            return [
                'id'                => $id,
                'name'              => $catalog_name,
                'description'       => $description,
                'accompagnement'    => $accompagnement,
                'catalog_id'        => $catId,
                'catalog_name'      => $catalog_name,
                'price'             => $price,
                'ordre'             => $ordre,
            ];
        }

        /**
         * [analyzeUplifts description]
         * @param  [type] $uplifts      [description]
         * @param  [type] $choosePlates [description]
         * @param  [type] $date         [description]
         * @param  [type] $hour         [description]
         * @param  [type] $nb_customer  [description]
         * @param  [type] $total_price  [description]
         * @return [type]               [description]
         */
        private function analyzeUplifts($uplifts, $choosePlates, $date, $hour, $nb_customer, $total_price)
        {
            $plats = [];

            list($y, $m, $d)    = explode('-', $date);
            list($h, $i)        = explode(':', $hour);

            $start      = mktime($h, $i, 0, $m, $d, $y);

            $when       = lib('time')->createFromTimestamp((int) $start);
            $midnight   = (int) $when->startOfDay()->getTimestamp();

            $day        = (string) $when->frenchDay();

            $modifyPrice = [];

            // $c = [];

            // foreach ($choosePlates as $cp) {
            //     $c[$cp['id']] = $cp;
            // }

            // $choosePlates = array_values($c);

            $transform = [];

            foreach ($choosePlates as $choosePlate) {
                if (!isset($transform[$choosePlate['name']])) {
                    $transform[$choosePlate['name']]                = [];
                    $transform[$choosePlate['name']]['id']          = $choosePlate['id'];
                    $transform[$choosePlate['name']]['nb']          = 0;
                    $transform[$choosePlate['name']]['min_price']   = $choosePlate['price'];
                    $transform[$choosePlate['name']]['max_price']   = $choosePlate['price'];
                    $transform[$choosePlate['name']]['ordre']       = isAke($choosePlate, 'ordre', 1);
                } else {
                    $transform[$choosePlate['name']]['min_price'] =
                    $choosePlate['price'] < $transform[$choosePlate['name']]['min_price']
                    ? $choosePlate['price']
                    : $transform[$choosePlate['name']]['min_price'];

                    $transform[$choosePlate['name']]['max_price'] =
                    $choosePlate['price'] > $transform[$choosePlate['name']]['max_price']
                    ? $choosePlate['price']
                    : $transform[$choosePlate['name']]['max_price'];
                }

                $transform[$choosePlate['name']]['nb']++;
            }

            $choosePlates = $transform;

            if (empty($uplifts)) {
                $coll = [];

                foreach ($choosePlates as $k => $choosePlate) {
                    $choosePlate['name'] = $k;
                    $coll[] = $choosePlate;
                }

                return $coll;
            }

            foreach ($choosePlates as $k => $choosePlate) {
                foreach ($uplifts as $uplift) {
                    $scenario_jour      = $this->hasKey($uplift, 'scenario_jour', []);
                    $scenario_heure     = $this->hasKey($uplift, 'scenario_heure', []);
                    $scenario_montant   = $this->hasKey($uplift, 'scenario_montant', 0);
                    $scenario_quantité  = $this->hasKey($uplift, 'scenario_quantité', 0);
                    $scenario_item      = $this->hasKey($uplift, 'scenario_item', false);
                    $scenario_client    = $this->hasKey($uplift, 'scenario_client', false);
                    $scenario_location  = $this->hasKey($uplift, 'scenario_location', false);
                    $scenario_coupon    = $this->hasKey($uplift, 'scenario_coupon', false);

                    if (!empty($scenario_heure)) {
                        $has = [];

                        foreach ($scenario_heure as $tabHeure) {
                            $startUp    = isAke($tabHeure, 'start', false);
                            $endUp      = isAke($tabHeure, 'end', false);

                            if (false !== $startUp && false !== $endUp) {
                                $startUp    = lib('agenda')->transform($startUp, (int) $midnight);
                                $endUp      = lib('agenda')->transform($endUp, (int) $midnight);

                                if ($start >= $startUp && $start <= $endUp) {
                                    $has[] = true;
                                }
                            }
                        }

                        if (empty($has)) {
                            continue;
                        }
                    }

                    if (0 < $scenario_montant) {
                        if ($scenario_montant > $total_price) {
                            continue;
                        }
                    }

                    if (0 < $scenario_quantité) {
                        if ($scenario_quantité > $nb_customer) {
                            continue;
                        }
                    }

                    $typeId = $uplift['uplifttype_id'];

                    if (in_array($typeId, [3, 4, 5, 6])) {
                        if (in_array($typeId, [3, 5])) {
                            if ($typeId == 3) {
                                $choosePlate['uplift'] = [
                                    'label'     => 'Remise de ' . $uplift['value'] . '€',
                                    'name'      => '-' . $uplift['value'] . '€',
                                    'text1'     => isAke($uplift, 'text1', ''),
                                    'text2'     => isAke($uplift, 'text2', ''),
                                    'type_id'   => $typeId,
                                    'id'        => $uplift['id'],
                                    'type'      => $uplift['type'],
                                    'duration'  => $uplift['duration'],
                                    'value'     => $uplift['value']
                                ];

                                $choosePlate['name'] = $k;
                                $plats[] = $choosePlate;
                            } else {
                                $choosePlate['uplift'] = [
                                    'label'     => 'Remise de ' . $uplift['value'] . '%',
                                    'name'      => '-' . $uplift['value'] . '%',
                                    'text1'     => isAke($uplift, 'text1', ''),
                                    'text2'     => isAke($uplift, 'text2', ''),
                                    'type_id'   => $typeId,
                                    'id'        => $uplift['id'],
                                    'type'      => $uplift['type'],
                                    'duration'  => $uplift['duration'],
                                    'value'     => $uplift['value']

                                ];

                                $choosePlate['name'] = $k;
                                $plats[] = $choosePlate;
                            }
                        } else {
                            if ($typeId == 4) {
                                $choosePlate['uplift'] = [
                                    'label'     => 'Remise de ' . $uplift['value'] . '€',
                                    'name'      => '-' . $uplift['value'] . '€',
                                    'text1'     => isAke($uplift, 'text1', ''),
                                    'text2'     => isAke($uplift, 'text2', ''),
                                    'type_id'   => $typeId,
                                    'id'        => $uplift['id'],
                                    'type'      => $uplift['type'],
                                    'duration'  => $uplift['duration'],
                                    'value'     => $uplift['value']

                                ];

                                $choosePlate['max_price_with_uplift'] = floatval($choosePlate['max_price'] - floatval($uplift['value']));
                                $choosePlate['max_price_total_with_uplift'] = floatval($choosePlate['max_price_with_uplift'] * $nb_customer);

                                $choosePlate['min_price_with_uplift'] = floatval($choosePlate['min_price'] - floatval($uplift['value']));
                                $choosePlate['min_price_total_with_uplift'] = floatval($choosePlate['min_price_with_uplift'] * $nb_customer);
                                $choosePlate['name'] = $k;

                                $plats[] = $choosePlate;

                                break;
                            } else {
                                $choosePlate['uplift'] = [
                                    'label'     => 'Remise de ' . $uplift['value'] . '%',
                                    'name'      => '-' . $uplift['value'] . '%',
                                    'text1'     => isAke($uplift, 'text1', ''),
                                    'text2'     => isAke($uplift, 'text2', ''),
                                    'type_id'   => $typeId,
                                    'id'        => $uplift['id'],
                                    'type'      => $uplift['type'],
                                    'duration'  => $uplift['duration'],
                                    'value'     => $uplift['value']
                                ];

                                $amount = $choosePlate['min_price'] - round((($choosePlate['min_price'] * floatval($uplift['value'])) / 100), 2);
                                $choosePlate['min_price_with_uplift'] = floatval($amount);
                                $choosePlate['min_price_total_with_uplift'] = floatval($amount) * $nb_customer;

                                $amount = $choosePlate['max_price'] - round((($choosePlate['max_price'] * floatval($uplift['value'])) / 100), 2);
                                $choosePlate['max_price_with_uplift'] = floatval($amount);
                                $choosePlate['max_price_total_with_uplift'] = floatval($amount) * $nb_customer;
                                $choosePlate['name'] = $k;

                                $plats[] = $choosePlate;

                                break;
                            }
                        }
                    } else {
                        if (1 == $typeId) {
                            $choosePlate['uplift'] = [
                                'label'     => $uplift['value'],
                                'text1'     => isAke($uplift, 'text1', ''),
                                'text2'     => isAke($uplift, 'text2', ''),
                                'type_id'   => $typeId,
                                'id'        => $uplift['id'],
                                'name'      => $uplift['value'],
                                'type'      => $uplift['type'],
                                'duration'  => $uplift['duration'],
                                'value'     => $uplift['value']
                            ];
                        } elseif (2 == $typeId) {
                            $choosePlate['uplift'] = [
                                'label'     => 'Offert: ' . $uplift['value'],
                                'name'      => 'Offert: ' . $uplift['value'],
                                'text1'     => isAke($uplift, 'text1', ''),
                                'text2'     => isAke($uplift, 'text2', ''),
                                'type_id'   => $typeId,
                                'id'        => $uplift['id'],
                                'type'      => $uplift['type'],
                                'duration'  => $uplift['duration'],
                                'value'     => $uplift['value']
                            ];
                        }

                        $choosePlate['name'] = $k;

                        $plats[] = $choosePlate;
                    }
                }
            }

            if (empty($plats)) {
                $coll = [];

                foreach ($choosePlates as $k => $choosePlate) {
                    $choosePlate['name'] = $k;
                    $coll[] = $choosePlate;
                }

                return $coll;
            } else {
                $coll = $ups = $tups = [];

                foreach ($plats as $plat) {
                    $uplift = isAke($plat, 'uplift', []);

                    if (!empty($uplift)) {
                        if (!in_array($uplift['id'], $tups)) {
                            $tups[] = $uplift['id'];
                            $ups[] = $uplift;
                        }
                    }
                }

                self::$datas['uplifts_' . Now::get('resto.reseller_id')] = $ups;
            }

            return $plats;
        }

        /**
         * [hasKey description]
         * @param  array   $tab     [description]
         * @param  [type]  $key     [description]
         * @param  [type]  $default [description]
         * @return boolean          [description]
         */
        private function hasKey(array $tab, $key, $default = null)
        {
            if (isset($tab[$key])) {
                if (!empty($tab[$key])) {
                    return $tab[$key];
                }
            }

            return is_callable($default) ? $default($tab) : $default;
        }

        /**
         * [checkCanServe description]
         * @param  [type] $reseller_id  [description]
         * @param  [type] $nb_customer  [description]
         * @param  [type] $schedules    [description]
         * @param  [type] $date         [description]
         * @param  [type] $hour         [description]
         * @param  [type] $optionsResto [description]
         * @param  [type] $type         [description]
         * @param  [type] $last_minute  [description]
         * @return [type]               [description]
         */
        private function checkCanServe($reseller_id, $nb_customer, $schedules, $date, $hour, $optionsResto, $type, $last_minute)
        {
            $start_last_minute_midi = strtotime('today 11:25');
            $start_last_minute_soir = strtotime('today 18:25');

            list($y, $m, $d)    = explode('-', $date);
            list($h, $i)        = explode(':', $hour);

            $start      = mktime($h, $i, 0, $m, $d, $y);
            $when       = lib('time')->createFromTimestamp((int) $start);
            $midnight   = (int) $when->startOfDay()->getTimestamp();

            $day = (string) $when->frenchDay();

            $schedulesDay = isAke($schedules, $day, []);

            $now = time();

            $fermeMidi = false;
            $fermeSoir = false;

            if (!empty($schedulesDay)) {
                if (!strlen($schedulesDay['am_start']) && !strlen($schedulesDay['pm_end'])) {
                    return [false, 1, $last_minute, $fermeMidi, $fermeSoir];
                }

                if (strlen($schedulesDay['am_start']) && !strlen($schedulesDay['am_end'])) {
                    $schedulesDay['am_end'] = '12_00';
                }

                if (strlen($schedulesDay['pm_end']) && !strlen($schedulesDay['pm_start'])) {
                    $schedulesDay['pm_start'] = '12_00';
                }

                if (!strlen($schedulesDay['am_start']) && !strlen($schedulesDay['am_end'])) {
                    $fermeMidi = true;
                }

                if (!strlen($schedulesDay['pm_start']) && !strlen($schedulesDay['pm_end'])) {
                    $fermeSoir = true;
                }

                if (strlen($schedulesDay['am_start'])) {
                    $amStart = lib('agenda')->transform((string) $schedulesDay['am_start'], (int) $midnight);
                }

                if (strlen($schedulesDay['am_end'])) {
                    $amEnd = lib('agenda')->transform((string) $schedulesDay['am_end'], (int) $midnight);
                }

                if (strlen($schedulesDay['pm_start'])) {
                    $pmStart = lib('agenda')->transform((string) $schedulesDay['pm_start'], (int) $midnight);
                }

                if (strlen($schedulesDay['pm_end'])) {
                    $pmEnd = lib('agenda')->transform((string) $schedulesDay['pm_end'], (int) $midnight);
                }

                $continue = false;

                if ($fermeMidi || $fermeSoir) {
                    if ($fermeMidi) {
                        $continue   = $start <= $pmEnd;
                        $service    = 2;
                    }

                    if ($fermeSoir) {
                        $continue   = $start <= $amEnd;
                        $service    = 1;
                    }
                } else {
                    if ($start >= $amStart && $start <= $amEnd) {
                        $continue   = true;
                        $service    = 1;

                        if (!$last_minute && date('Y-m-d') == $date) {
                            $last_minute = $start >= $start_last_minute_midi && $now >= $start_last_minute_midi;
                        }
                    } elseif ($start >= $pmStart && $start <= $pmEnd) {
                        $continue   = true;
                        $service    = 2;

                        if (!$last_minute && date('Y-m-d') == $date) {
                            $last_minute = $start >= $start_last_minute_soir && $now >= $start_last_minute_soir;
                        }
                    }
                }

                if ($continue) {
                    if ($type == 'sur_place' && !$last_minute) {
                        if ($nb_customer < 1) {
                            return [true, $service, $last_minute, $fermeMidi, $fermeSoir, 0];
                        }

                        if ($service == 1) {
                            $initialStock = isAke($optionsResto, 'nombre_places_stock_' . $day . '_midi', 0);
                        } elseif ($service == 2) {
                            $initialStock = isAke($optionsResto, 'nombre_places_stock_' . $day . '_midi', 0);
                        }

                        if ($initialStock > 0) {
                            $cachedStock = redis()->get('stock.' . $reseller_id . '.' . $service  . '.' . date('Y-m-d', $midnight));

                            if (!$cachedStock) {
                                $dbStock = Model::Stockrestaurant()->chain([
                                    'reseller_id'   => (int) $reseller_id,
                                    'day'           => date('Y-m-d', $midnight),
                                    'service'       => (int) $service
                                ])->first();

                                $cachedStock = $dbStock['stock'];

                                redis()->set(
                                    'stock.' . $reseller_id . '.' . $service  . '.' . date('Y-m-d', $midnight),
                                    $cachedStock
                                );
                            }

                            $stockUsed = 0;

                            $offersout = Model::Restoofferout()
                            ->where(['reseller_id', '=', (int) $reseller_id])
                            ->where(['service', '=', (int) $service])
                            ->where(['date', '=', date('Y-m-d', $midnight)])
                            ->cursor();

                            foreach ($offersout as $offerout) {
                                $stockUsed += $offerout['stock'];
                            }

                            $remainStock = $cachedStock - $stockUsed;

                            return [$remainStock >= $nb_customer, $service, $last_minute, $fermeMidi, $fermeSoir, $remainStock];
                        } else {
                            return [false, $service, $last_minute, $fermeMidi, $fermeSoir, 0];
                        }
                    } else {
                        return [true, $service, $last_minute, $fermeMidi, $fermeSoir, 0];
                    }
                }
            } else {
                if (fnmatch('*sans_reservation*', $type)) {
                    return [true, 1, $last_minute, $fermeMidi, $fermeSoir, 0];
                }
            }

            return [false, 1, $last_minute, $fermeMidi, $fermeSoir, 0];
        }

        /**
         * [populateSchedules description]
         * @param  [type]  $reseller_id [description]
         * @param  integer $days        [description]
         * @return [type]               [description]
         */
        function populateSchedules($reseller_id = null, $days = 30)
        {
            set_time_limit(0);

            $q = Model::Optionsrestaurant();

            if ($reseller_id) {
                $q->where(['reseller_id', '=', (int) $reseller_id]);
            }

            $restos = $q->cursor();

            $start = lib('time')->createFromTimestamp((int) time());

            $min = (int) $start->startOfDay()->getTimestamp();
            $max = $start->addDays($days)->getTimestamp();

            foreach ($restos as $resto) {
                $reseller_id = isAke($resto, 'reseller_id', false);

                if (false !== $reseller_id) {
                    $schedules = $this->getOpenSchedules((int) $reseller_id);

                    for ($i = $min; $i <= $max; $i += 24 * 3600) {
                        $when       = lib('time')->createFromTimestamp((int) $i);
                        $midnight   = (int) $when->startOfDay()->getTimestamp();

                        $day = (string) $when->frenchDay();

                        $schedulesDay = isAke($schedules, $day, []);

                        $fermeMidi = false;
                        $fermeSoir = false;

                        if (!empty($schedulesDay)) {
                            if (!strlen($schedulesDay['am_start']) && !strlen($schedulesDay['pm_end'])) {
                                continue;
                            }

                            if (strlen($schedulesDay['am_start']) && !strlen($schedulesDay['am_end'])) {
                                $schedulesDay['am_end'] = '12_00';
                            }

                            if (strlen($schedulesDay['pm_end']) && !strlen($schedulesDay['pm_start'])) {
                                $schedulesDay['pm_start'] = '12_00';
                            }

                            if (!strlen($schedulesDay['am_start']) && !strlen($schedulesDay['am_end'])) {
                                $fermeMidi = true;
                            }

                            if (!strlen($schedulesDay['pm_start']) && !strlen($schedulesDay['pm_end'])) {
                                $fermeSoir = true;
                            }

                            if (strlen($schedulesDay['am_start'])) {
                                $amStart = lib('agenda')->transform((string) $schedulesDay['am_start'], (int) $midnight);
                            }

                            if (strlen($schedulesDay['am_end'])) {
                                $amEnd = lib('agenda')->transform((string) $schedulesDay['am_end'], (int) $midnight);
                            }

                            if (strlen($schedulesDay['pm_start'])) {
                                $pmStart = lib('agenda')->transform((string) $schedulesDay['pm_start'], (int) $midnight);
                            }

                            if (strlen($schedulesDay['pm_end'])) {
                                $pmEnd = lib('agenda')->transform((string) $schedulesDay['pm_end'], (int) $midnight);
                            }

                            $initialStockMidi = isAke($resto, 'nombre_places_stock_' . $day . '_midi', 0);
                            $initialStockSoir = isAke($resto, 'nombre_places_stock_' . $day . '_soir', 0);

                            if ($initialStockMidi > 0 && !$fermeMidi) {
                                $dbStock = Model::Stockrestaurant()->firstOrCreate([
                                    'reseller_id'   => (int) $reseller_id,
                                    'day'           => date('Y-m-d', $midnight),
                                    'service'       => 1
                                ]);

                                $stockDb = $dbStock->stock;

                                if (!$stockDb) {
                                    $dbStock->stock = (int) $initialStockMidi;
                                    $dbStock->save();
                                }
                            }

                            if ($initialStockSoir > 0 && !$fermeSoir) {
                                $dbStock = Model::Stockrestaurant()->firstOrCreate([
                                    'reseller_id'   => (int) $reseller_id,
                                    'day'           => date('Y-m-d', $midnight),
                                    'service'       => 2
                                ]);

                                $stockDb = $dbStock->stock;

                                if (!$stockDb) {
                                    $dbStock->stock = (int) $initialStockSoir;
                                    $dbStock->save();
                                }
                            }
                        }
                    }
                }
            }

            Model::Cronrunning()->firstOrCreate(['task' => 'makeRestoStock'])->setRunning(0)->save();
        }

        public function extractGuides($data)
        {
            return $this->extractKey($data, 'guides');
        }

        /**
         * [extractPreferences description]
         * @param  array  $data [description]
         * @return [type]       [description]
         */
        public function extractPreferences(array $data)
        {
            return $this->extractKey($data, 'preferences_client');
        }

        /**
         * [extractActivites description]
         * @param  array  $data [description]
         * @return [type]       [description]
         */
        public function extractActivites(array $data)
        {
            return $this->extractKey($data, 'activites');
        }

        /**
         * [extractLabels description]
         * @param  [type] $data [description]
         * @return [type]       [description]
         */
        public function extractLabels($data)
        {
            return $this->extractKey($data, 'labels');
        }

        /**
         * [extractThematiques description]
         * @param  [type] $data [description]
         * @return [type]       [description]
         */
        public function extractThematiques($data)
        {
            return $this->extractKey($data, 'thematiques');
        }

        /**
         * [extractKey description]
         * @param  array  $data [description]
         * @param  [type] $key  [description]
         * @return [type]       [description]
         */
        public function extractKey(array $data, $key)
        {
            $collection = [];

            foreach ($data as $k => $v) {
                if (fnmatch($key . '_*', $k)) {
                    if ($v == 1) {
                        $collection[$k] = $v;
                    }
                }
            }

            return $collection;
        }

        /**
         * [getTypesNonAuto description]
         * @param  [int]    $segment_id [description]
         * @return [array]  [description]
         */
        public function getTypesNonAuto($segment_id)
        {
            $father     = repo('segment')->getFather($segment_id);
            $datasSeg   = repo('segment')->getData($segment_id);

            if (fnmatch('*aurant*', strtolower($father['name']))) {
                $context = 'resto';
            } elseif (fnmatch('*nack*', strtolower($father['name']))) {
                $context = 'snack';
            } elseif (fnmatch('*vin*', strtolower($father['name']))) {
                $context = 'vin';
            } else {
                $context = isAke($datasSeg, 'context', 'resto');
            }

            $isPetitesenvies = false;

            $collection = [];

            $segmenttype_id = Model::Segmenttype()->where(['name', '=', 'resto_nonauto'])->first(true)->id;

            $segments = Model::Segment()->where(['segmenttype_id', '=', (int) $segmenttype_id])->cursor();

            if ($context == 'snack') {
                $family             = repo('segment')->getFamily($segment_id);
                $seg                = isset($family[1]) ? $family[1] : [];
                $isPetitesenvies    = fnmatch('*nvies*', isAke($seg, 'name', ''));

                if ($isPetitesenvies) $context = 'envies';
            }

            foreach ($segments as $segment) {
                $contextSeg     = false;
                $datas          = repo('segment')->getData($segment['id']);
                $fatherSegment  = repo('segment')->getFather($segment['id']);

                if (fnmatch('*aurant*', strtolower($fatherSegment['name']))) {
                    $contextSeg = 'resto';
                } elseif (fnmatch('*nack*', strtolower($fatherSegment['name']))) {
                    $contextSeg = 'snack';
                } elseif (fnmatch('*vin*', strtolower($fatherSegment['name']))) {
                    $contextSeg = 'vin';
                } elseif (fnmatch('*nvies*', strtolower($fatherSegment['name']))) {
                    $contextSeg = 'envies';
                }

                if ($context != $contextSeg || !strlen($segment['segment_id'])) {
                    continue;
                }

                if (!empty($datas)) {
                    foreach ($datas as $k => $v) {
                        $segment[$k] = $v;
                    }
                }

                unset($segment['_id']);
                unset($segment['hash']);

                $collection[] = $segment;
            }

            $collection = lib('collection', [$collection])->sortBy('name');

            return array_values($collection->toArray());
        }

        /**
         * [assocPoi description]
         * @param  [type] $reseller_id [description]
         * @return [type]              [description]
         */
        public function assocPoi($reseller_id = null)
        {
            set_time_limit(0);

            $distanceMax = 0.5;
            $segments = Model::Segment()->where(['segmenttype_id', '=', 8])->cursor();

            $pois = [];

            foreach ($segments as $segment) {
                if (isset($segment['segment_id'])) {
                    if (strlen($segment['segment_id'])) {
                        $count = Model::Segment()->where(['segment_id', '=', (int) $segment['id']])->cursor()->count();

                        $hasChildren = $count > 0;

                        if (!$hasChildren) {
                            $data = repo('segment')->getData((int) $segment['id']);

                            $latitude       = isAke($data, 'latitude', false);
                            $longitude      = isAke($data, 'longitude', false);
                            $sellzone_id    = isAke($data, 'sellzone_id', false);

                            if (false !== $latitude && false !== $longitude && false !== $sellzone_id) {
                                $latitude = str_replace(',', '.', $latitude);
                                $longitude = str_replace(',', '.', $longitude);

                                $pois[] = ['segment_id' => (int) $segment['id'], 'lat' => (double) $latitude, 'lng' => (double) $longitude, 'sellzone_id' => (int)$sellzone_id];
                            }
                        }
                    }
                }
            }

            $q = Model::Optionsrestaurant();

            if ($reseller_id) {
                $q->where(['reseller_id', '=', (int) $reseller_id]);
            }

            $restos = $q->cursor();

            foreach ($restos as $resto) {
                $company = Model::company()->where(['reseller_id', '=', (int) $resto['reseller_id']])->first(true);

                if ($company) {
                    $loc = lib('utils')->remember('has.locations.companies.' . $resto['reseller_id'], function ($reseller_id) {
                        $company = Model::Company()->where(['reseller_id', '=', (int) $reseller_id])->first(true);
                            $coords = lib('geo')->getCoords($company->address . ' ' . $company->zip . ' ' . $company->city);

                            $loc = ['lng' => $coords['lng'], 'lat' => $coords['lat']];
                        return $loc;
                    }, Model::Company()->getAge(), [$resto['reseller_id']]);

                    foreach ($pois as $poi) {
                        if ($resto['sellzone_id'] == $poi['sellzone_id']) {
                            $distances = distanceKmMiles(
                                $loc['lng'],
                                $loc['lat'],
                                $poi['lng'],
                                $poi['lat']
                            );

                            $km = (double) $distances['km'];

                            $check = $km <= $distanceMax;

                            if (true === $check) {
                                Model::Restopoi()->firstOrCreate([
                                    'sellzone_id'   => (int) $resto['sellzone_id'],
                                    'reseller_id'   => (int) $resto['reseller_id'],
                                    'segment_id'    => (int) $poi['segment_id'],
                                    'distance'      => (double) $km
                                ]);
                            }
                        }
                    }
                }
            }

            Model::Cronrunning()->firstOrCreate(['task' => 'assocPoi'])->setRunning(0)->save();
        }

        private function getInfos($reseller_id)
        {
            $specialites_dispo = $types_auto_dispo = $types_non_auto_dispo = $restoPlats = $idPlats = $pricePlats = [];

            $min = min(
                Model::Mealgeo()->getAge(),
                Model::Mealnonauto()->getAge(),
                Model::Mealtype()->getAge(),
                Model::Catalog()->getAge()
            );

            $keyCache = 'lib.resto.getinfos.' . $reseller_id;
            $keyCacheAge = 'lib.resto.getinfos.age.' . $reseller_id;

            $cached     = redis()->get($keyCache);
            $cachedAge  = redis()->get($keyCacheAge);

            $takeCache = $cached && $cachedAge;

            if ($takeCache) {
                $takeCache = $cachedAge >= $min;
            }

            if ($takeCache) {
                return unserialize($cached);
            } else {
                $cursor = Model::Catalog()
                ->where(['reseller_id', '=', (int) $reseller_id])
                ->where(['is_challenge', '=', 1])
                ->cursor();

                foreach ($cursor as $plat) {
                    $restoPlats[]                       = (int) $plat['segment_id'];
                    $idPlats[]                          = (int) $plat['id'];
                    $pricePlats[$plat['segment_id']]    = (double) $plat['price'];

                    $mgeo = Model::Mealgeo()->where(['segment_id', '=', (int) $plat['segment_id']])->first(true);

                    if ($mgeo) {
                        if (!in_array($mgeo->resto_geo_id, $specialites_dispo)) {
                            $specialites_dispo[] = (int) $mgeo->resto_geo_id;
                        }
                    }

                    $mtypeauto = Model::Mealtype()->where(['segment_id', '=', (int) $plat['segment_id']])->first(true);

                    if ($mtypeauto) {
                        if (!in_array($mtypeauto->resto_type_id, $types_auto_dispo)) {
                            $types_auto_dispo[] = (int) $mtypeauto->resto_type_id;
                        }
                    }

                    $mtypeauto = Model::Mealnonauto()->where(['catalog_id', '=', (int) $plat['id']])->first(true);

                    if ($mtypeauto) {
                        if (!in_array($mtypeauto->resto_nonauto_id, $types_non_auto_dispo)) {
                            $types_non_auto_dispo[] = (int) $mtypeauto->resto_nonauto_id;
                        }
                    }
                }

                $results = [$specialites_dispo, $types_auto_dispo, $types_non_auto_dispo, $restoPlats, $idPlats, $pricePlat];

                redis()->set($keyCach, serialize($results));
                redis()->set($keyCacheAge, time());

                return $results;
            }
        }

        public function datas($reseller_id)
        {
            $infos = lib('myzelift')->fiche($reseller_id);

            $return = [];

            $take = ['preferences', 'extras', 'horaires'];

            foreach ($infos as $k => $v) {
                if (in_array($k, $take) || fnmatch("*paiement*", $k)) {
                    $return[$k] = $v;
                }
            }

            return $return;
        }

        function consolidate($reseller_id = null)
        {
            Save::clean();
            // $szs = Model::Sellzone()->cursor();

            // foreach ($szs as $sz) {
            //     $coll = [];

            //     $zips = Model::Coveredcity()->where(['sellzone_id', '=', (int) $sz['id']])->cursor();

            //     foreach ($zips as $zip) {
            //         $cs = Model::City()->where(['zip', '=', (string) $zip['zip']])->cursor();
            //         foreach ($cs as $c) {
            //             $n      = str_replace(' ', '_', Inflector::unaccent(Inflector::lower($zip['name'])));
            //             $n2     = str_replace(' ', '_', Inflector::unaccent(Inflector::lower($c['name'])));

            //             if ($n == $n2) {
            //                 $coll[] = [
            //                     'insee' => $c['insee'],
            //                     'zip'   => $zip['zip'],
            //                     'name'  => $c['name'],
            //                 ];
            //             }
            //         }
            //     }

            //     $coll = array_values(array_unique($coll));

            //     redis()->set('insees.' . $sz['id'], serialize($coll));
            // }

            $plats = Model::Mealgeo()->cursor();

            $geos = [];

            foreach ($plats as $plat) {
                if (!isset($geos[$plat['resto_geo_id']])) {
                    $geos[$plat['resto_geo_id']] = [];
                }

                $geos[$plat['resto_geo_id']][] = $plat['segment_id'];
            }

            foreach ($geos as $id => $tab) {
                redis()->set('geos.spes.' . $id, serialize($tab));
            }

            $ids = repo('segment')->getAllFamilyIds(1941);

            $names = [];

            $segs = Model::Segment()
            ->where(['id', 'IN', implode(',', $ids)])
            ->cursor();

            foreach ($segs as $seg) {
                $data = repo('segment')->getData((int) $seg['id']);
                $is_item = isAke($data, 'is_item', false);

                if (false !== $is_item) {
                    $names[] = $seg['name'];
                }
            }

            asort($names);

            redis()->set('sucres', serialize(array_values($names)));

            $suggestIds = [];

            $segments = Model::Segment()->reset()
            ->where(['segmenttype_id', '=', 9])
            ->cursor();

            $collection = [];

            foreach ($segments as $segment) {
                $data               = repo('segment')->getData((int) $segment['id']);
                $ordre              = isAke($data, 'ordre', 1);
                $segment['ordre']   = $ordre;
                $collection[]       = $segment;
            }

            $collection = lib('collection', [$collection]);

            $collection->sortBy('ordre');

            $segments = $collection->toArray();

            foreach ($segments as $segment) {
                $data = repo('segment')->getData((int) $segment['id']);

                $segmentsItem = isAke($data, 'segments', false);

                if (false !== $segmentsItem) {
                    $tab = explode(',', $segmentsItem);
                    $item = [];
                    $item['segments'] = [];
                    $item['plats'] = [];

                    foreach ($tab as $itId) {
                        $seg = Model::Segment()->find($itId);

                        if ($seg) {
                            if (!in_array($itId, $suggestIds)) {
                                $suggestIds[] = $itId;
                            }

                            $s = [];
                            $s['id']    = (int) $itId;
                            $s['name']  = $seg->name;

                            unset($s['created_at']);
                            unset($s['updated_at']);

                            $type = Model::segmenttype()->find($seg->segmenttype_id);

                            if ($type) {
                                $s['type'] = $type->name;
                            }

                            if (fnmatch('*non*', $type->name)) {
                                $plats = Model::Mealnonauto()->where(['resto_nonauto_id', '=', (int) $itId])->cursor();

                                foreach ($plats as $plat) {
                                    $cat = Model::Catalog()->find((int) $plat['catalog_id']);

                                    if ($cat) {
                                        $item['plats'][] = $cat->segment_id . '::' . $cat->reseller_id;
                                    }
                                }
                            }

                            if (fnmatch('*_type*', $type->name)) {
                                $plats = Model::Mealtype()->where(['resto_type_id', '=', (int) $itId])->cursor();

                                foreach ($plats as $plat) {
                                    $item['plats'][] = $plat['segment_id'];
                                }
                            }

                            if (fnmatch('*geo*', $type->name)) {
                                $plats = Model::Mealgeo()->where(['resto_geo_id', '=', (int) $itId])->cursor();

                                foreach ($plats as $plat) {
                                    $item['plats'][] = $plat['segment_id'];
                                }
                            }
                        }

                        $item['segments'][] = $s;
                    }

                    $row = Model::Suggestion()
                    ->firstOrCreate(['segment_id' => (int) $segment['id']])
                    ->setName($segment['name'])
                    ->setSegments($item['segments'])
                    ->setPlats($item['plats'])
                    ->save();
                }
            }

            $days = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
            $when = ['am_start', 'am_end', 'pm_start', 'pm_end'];

            $q = Model::Optionsrestaurant();

            if (!is_null($reseller_id)) {
                $q->where(['reseller_id', '=', (int) $reseller_id]);
            }

            $restos = $q->cursor();

            foreach ($restos as $resto) {
                $schedules = [];

                $schedules['sur_place']     = [];
                $schedules['a_emporter']    = [];
                $schedules['en_livraison']  = [];

                $row = Model::Restodata()->firstOrCreate([
                    'reseller_id' => (int) $resto['reseller_id'],
                    'sellzone_id' => (int) $resto['sellzone_id']
                ]);

                $reseller_id = $resto['reseller_id'];

                $loc = isAke($row->toArray(), 'loc', []);

                $lat = isAke($loc, 'lat', false);
                $lng = isAke($loc, 'lng', false);


                if (!$lat || !$lng) {
                    $company    = Model::Company()->refresh()->where(['reseller_id', '=', (int) $reseller_id])->first(true);
                    $coords     = lib('geo')->getCoords($company->address . ' ' . $company->zip . ' ' . $company->city);

                    $locationReseller = ['lng' => $coords['lng'], 'lat' => $coords['lat']];
                } else {
                    $locationReseller = ['lng' => floatval($lng), 'lat' => floatval($lat)];
                }

                $options = $resto;

                unset($options['_id']);
                unset($options['reseller_id']);
                unset($options['sellzone_id']);
                unset($options['created_at']);
                unset($options['updated_at']);

                $pattern = 'type_restauration_sur_place_horaires_services_##day##_##when##';

                foreach ($days as $day) {
                    if (!isset($collection[$day])) {
                        $schedules['sur_place'][$day] = [];
                    }

                    foreach ($when as $moment) {
                        $key = str_replace(['##day##', '##when##'], [$day, $moment], $pattern);
                        $schedules['sur_place'][$day][$moment] = str_replace(':', '_', $options[$key]);
                    }
                }

                $pattern = 'type_restauration_a_emporter_horaires_services_##day##_##when##';

                foreach ($days as $day) {
                    if (!isset($collection[$day])) {
                        $schedules['a_emporter'][$day] = [];
                    }

                    foreach ($when as $moment) {
                        $key = str_replace(['##day##', '##when##'], [$day, $moment], $pattern);
                        $schedules['a_emporter'][$day][$moment] = str_replace(':', '_', $options[$key]);
                    }
                }

                $pattern = 'type_restauration_en_livraison_horaires_services_##day##_##when##';

                foreach ($days as $day) {
                    if (!isset($collection[$day])) {
                        $schedules['en_livraison'][$day] = [];
                    }

                    foreach ($when as $moment) {
                        $key = str_replace(['##day##', '##when##'], [$day, $moment], $pattern);
                        $schedules['en_livraison'][$day][$moment] = str_replace(':', '_', isAke($options, $key, ''));
                    }
                }

                $catalog_names =
                $plats_names =
                $families_types_auto_dispo =
                $families_types_non_auto_dispo =
                $families_plats_dispo =
                $pois_dispo =
                $families_pois_dispo =
                $specialites_dispo =
                $families_specialites_dispo =
                $spe_plats =
                $auto_plats =
                $non_auto_plats =
                $types_auto_dispo =
                $types_non_auto_dispo =
                $allPlats =
                $restoPlats =
                $composed =
                $assocs =
                $families =
                $names =
                $fathers =
                $jours =
                $services =
                $contraintes =
                $orders =
                $carte =
                $descriptions =
                $accompagnements =
                $pricePlats = [];

                $catalagCategories = [
                    1 => [402,406],
                    2 => [519,500,518,521,525,522,696,1538,2100],
                    3 => [668,526,690,1530],
                    4 => [1528],
                    5 => [1952,1958,1548],
                    6 => [2167,2168],
                    7 => [1942],
                ];

                $tuplesPlats = [];

                $carte[1] = $carte[2] = $carte[3] = $carte[4] = $carte[5] = $carte[6] = $carte[7] = [];

                if ($row->carte) {
                    foreach ($row->carte as $t => $ps) {
                        $carte[$t] = $ps;

                        foreach ($ps as $platCarte) {
                            $tuplesPlats[] = $platCarte['id'];
                        }
                    }
                }

                $cursor = Model::Catalog()
                ->where(['reseller_id', '=', (int) $resto['reseller_id']])
                ->where(['is_challenge', '=', 1])
                ->cursor();

                $collection = [];

                foreach ($cursor as $plat) {
                    $segId = isAke($plat, 'segment_id', 0);

                    if ($segId < 1) {
                        continue;
                    }

                    $plats = isAke($plat, 'plats', []);

                    $accompagnements[$plat['id']] = isAke($plat, 'accompagnement', null);
                    $descriptions[$plat['id']] = isAke($plat, 'description', null);

                    if (!empty($plats)) {
                        $composed[$plat['segment_id']] = $plats;
                    }

                    $data                           = repo('segment')->getData((int) $plat['segment_id']);
                    $ordre                          = isAke($data, 'ordre', 1);
                    $plat['ordre']                  = $ordre;
                    $collection[]                   = $plat;
                    $fathers[$plat['segment_id']]   = repo('segment')->getFather($plat['segment_id'])['id'];

                    $family                         = repo('segment')->getFamily($plat['segment_id']);

                    foreach ($catalagCategories as $idCat => $segsCat) {
                        foreach ($family as $child) {
                            if (!isset($families[$plat['segment_id']])) {
                                $families[$plat['segment_id']] = [];
                            }

                            $families[$plat['segment_id']][$child['id']] = $child;

                            if (!in_array($plat['id'], $tuplesPlats)) {
                                if (in_array($child['id'], $segsCat)) {
                                    unset($plat['_id']);

                                    if ($plat['segment_id'] == 1548) {
                                        $carte[5][] = $plat;

                                        $p = Model::Catalog()->find((int) $plat['id']);

                                        $p->catalogcategory_id = 5;

                                        $p->save();
                                    } else {
                                        $carte[$idCat][] = $plat;

                                        $p = Model::Catalog()->find((int) $plat['id']);

                                        $p->catalogcategory_id = (int) $idCat;

                                        $p->save();
                                    }
                                }
                            }
                        }
                    }
                }

                $collection = lib('collection', [$collection]);

                $collection->sortBy('ordre');

                $cursor = array_values($collection->toArray());

                $contraintes['jours'] = [];
                $contraintes['services'] = [];

                foreach ($cursor as $plat) {
                    $s = Model::Segment()->find((int) $plat['segment_id']);

                    if (!$s) {
                        continue;
                    }

                    $jours[$plat['id']] = isAke($plat, 'jours', []);
                    $services[$plat['id']] = isAke($plat, 'services', []);

                    if (!isset($contraintes['jours'][$plat['segment_id']])) {
                        $contraintes['jours'][$plat['segment_id']] = [];
                    }

                    if (!isset($contraintes['services'][$plat['segment_id']])) {
                        $contraintes['services'][$plat['segment_id']] = [];
                    }

                    $contraintes['jours'][$plat['segment_id']][$plat['id']] = isAke($plat, 'jours', []);
                    $contraintes['services'][$plat['segment_id']][$plat['id']] = isAke($plat, 'services', []);

                    if (!isset($assocs[$plat['id']])) {
                        $assocs[$plat['id']]                = [];
                        $assocs[$plat['id']]['family']      = [];
                        $assocs[$plat['id']]['geo']         = [];
                        $assocs[$plat['id']]['auto']        = [];
                        $assocs[$plat['id']]['non_auto']    = [];
                    }

                    if (!isset($allPlats[$plat['segment_id']])) {
                        $allPlats[$plat['segment_id']] = [];
                    }

                    $restoPlats[$plat['segment_id']]    = (int) $plat['id'];
                    $pricePlats[$plat['id']]            = (double) $plat['price'];
                    $plat['data']                       = repo('segment')->getData((int) $plat['segment_id']);
                    $allPlats[$plat['segment_id']][]    = $plat;
                    $names[$plat['segment_id']]         = $plats_names[$plat['segment_id']]
                    = Model::Segment()->find((int) $plat['segment_id'])->name;

                    $orders[$plat['segment_id']]         = $plat['ordre'];

                    $catalog_names[$plat['id']] = $plat['name'];

                    $family = repo('segment')->getFamily((int) $plat['segment_id']);

                    $assocs[$plat['id']]['family'][] = $plat['segment_id'];

                    foreach ($family as $seg) {
                        if (!in_array($seg['id'], $families_plats_dispo)) {
                            $families_plats_dispo[] = $assocs[$plat['id']]['family'][] = $seg['id'];
                            $names[$seg['id']] = $seg['name'];
                        }
                    }

                    $mgeos = Model::Mealgeo()
                    ->where(['segment_id', '=', (int) $plat['segment_id']])
                    ->models();

                    if ($mgeos->count() > 0) {
                        foreach ($mgeos as $mgeo) {
                            $s = Model::Segment()->find((int) $mgeo->resto_geo_id);

                            if (!$s) continue;
                            // if (!in_array($mgeo->resto_geo_id, $specialites_dispo)) {
                                if (!isset($spe_plats[$mgeo->resto_geo_id])) {
                                    $spe_plats[$mgeo->resto_geo_id] = [];
                                }

                                $specialites_dispo[] = (int) $mgeo->resto_geo_id;
                                $names[$mgeo->resto_geo_id] = Model::Segment()->find((int) $mgeo->resto_geo_id)->name;
                                $family = repo('segment')->getFamily($mgeo->resto_geo_id);

                                $assocs[$plat['id']]['geo'][] = $mgeo->resto_geo_id;
                                $spe_plats[$mgeo->resto_geo_id][] = $plat['segment_id'];

                                foreach ($family as $seg) {
                                    if (!isset($spe_plats[$seg['id']])) {
                                        $spe_plats[$seg['id']] = [];
                                    }

                                    // $spe_plats[$seg['id']][] = $plat['segment_id'];
                                    // $spe_plats[$mgeo->resto_geo_id][] = $plat['segment_id'];

                                    if (!in_array($seg['id'], $families_specialites_dispo)) {
                                        $families_specialites_dispo[] = $assocs[$plat['id']]['geo'][] = $seg['id'];
                                        $names[$seg['id']] = $seg['name'];
                                    }
                                }
                            // }
                        }
                    }

                    $mtypeautos = Model::Mealtype()
                    ->where(['segment_id', '=', (int) $plat['segment_id']])
                    ->models();

                    if ($mtypeautos->count() > 0) {
                        foreach ($mtypeautos as $mtypeauto) {
                            // if (!in_array($mtypeauto->resto_type_id, $types_auto_dispo)) {
                                if (!isset($auto_plats[$mtypeauto->resto_type_id])) {
                                    $auto_plats[$mtypeauto->resto_type_id] = [];
                                }

                                $auto_plats[$mtypeauto->resto_type_id][] = $plat['segment_id'];

                                $types_auto_dispo[] = (int) $mtypeauto->resto_type_id;
                                $names[$mtypeauto->resto_type_id] = Model::Segment()->find((int) $mtypeauto->resto_type_id)->name;
                                $family = repo('segment')->getFamily($mtypeauto->resto_type_id);
                                $assocs[$plat['id']]['auto'][] = $mtypeauto->resto_type_id;

                                foreach ($family as $seg) {
                                    if (!isset($auto_plats[$seg['id']])) {
                                        $auto_plats[$seg['id']] = [];
                                    }

                                    $auto_plats[$seg['id']][] = $plat['segment_id'];
                                    $auto_plats[$mtypeauto->resto_type_id][] = $plat['segment_id'];

                                    if (!in_array($seg['id'], $families_types_auto_dispo)) {
                                        $families_types_auto_dispo[] = $assocs[$plat['id']]['auto'][] = $seg['id'];
                                        $names[$seg['id']] = $seg['name'];
                                    }
                                }
                            // }
                        }
                    }

                    $mtypeautos = Model::Mealnonauto()
                    ->where(['catalog_id', '=', (int) $plat['id']])
                    ->models();

                    if ($mtypeautos->count() > 0) {
                        foreach ($mtypeautos as $mtypeauto) {
                            // if (!in_array($mtypeauto->resto_nonauto_id, $types_non_auto_dispo)) {
                                if (!isset($non_auto_plats[$mtypeauto->resto_nonauto_id])) {
                                    $non_auto_plats[$mtypeauto->resto_nonauto_id] = [];
                                }

                                $non_auto_plats[$mtypeauto->resto_nonauto_id][] = $plat['segment_id'];

                                $types_non_auto_dispo[] = (int) $mtypeauto->resto_nonauto_id;

                                $names[$mtypeauto->resto_nonauto_id] = Model::Segment()->find((int) $mtypeauto->resto_nonauto_id)->name;

                                $family = repo('segment')->getFamily($mtypeauto->resto_nonauto_id);
                                $assocs[$plat['id']]['non_auto'][] = $mtypeauto->resto_nonauto_id;

                                foreach ($family as $seg) {
                                    if (!isset($non_auto_plats[$seg['id']])) {
                                        $non_auto_plats[$seg['id']] = [];
                                    }

                                    $non_auto_plats[$seg['id']][] = $plat['segment_id'];
                                    // $non_auto_plats[$mtypeauto->resto_nonauto_id][] = $plat['segment_id'];

                                    if (!in_array($seg['id'], $families_types_non_auto_dispo)) {
                                        $families_types_non_auto_dispo[] = $assocs[$plat['id']]['non_auto'][] = $seg['id'];
                                        $names[$seg['id']] = $seg['name'];
                                    }
                                }
                            // }
                        }
                    }

                    $assocs[$plat['id']]['family']   = array_values(array_unique($assocs[$plat['id']]['family']));
                    $assocs[$plat['id']]['geo']      = array_values(array_unique($assocs[$plat['id']]['geo']));
                    $assocs[$plat['id']]['auto']     = array_values(array_unique($assocs[$plat['id']]['auto']));
                    $assocs[$plat['id']]['non_auto'] = array_values(array_unique($assocs[$plat['id']]['non_auto']));
                }

                $pois = Model::Restopoi()
                ->where(['sellzone_id', '=', (int) $resto['sellzone_id']])
                ->where(['reseller_id', '=', (int) $resto['reseller_id']])
                ->cursor();

                $distances = [];

                foreach ($pois as $poi) {
                    if (!in_array($poi['segment_id'], $pois_dispo)) {
                        $poiSeg = Model::Segment()->find((int) $poi['segment_id']);

                        if ($poiSeg && (double) $poi['distance'] <= 0.5) {
                            $distances[$poi['segment_id']] = (double) $poi['distance'];
                            $pois_dispo[] = $poi['segment_id'];
                            $names[$poi['segment_id']] = $poiSeg->name;

                            $family = repo('segment')->getFamily((int) $poi['segment_id']);

                            foreach ($family as $seg) {
                                if (!in_array($seg['id'], $families_pois_dispo) && !in_array($seg['id'], $pois_dispo)) {
                                    $families_pois_dispo[]  = $seg['id'];
                                    $names[$seg['id']]      = $seg['name'];
                                }
                            }
                        }
                    }
                }

                $row->reseller      = Model::Reseller()->find((int) $resto['reseller_id'], false);

                $row->company       = Model::Company()
                ->where(['reseller_id', '=', (int) $resto['reseller_id']])
                ->first(false);

                $zc = Model::Zechallenge()->where(['reseller_id', '=', (int) $resto['reseller_id']])->cursor()->first(true);

                if ($zc) {
                    $status_zechallenge = $zc->status;
                } else {
                    $status_zechallenge = 'WAITING';

                    // Model::Zechallenge()->firstOrCreate([
                    //     'reseller_id'   => (int) $resto['reseller_id']
                    // ])->setStatus('WAITING')->save();
                }

                $segmentDatas = [];

                foreach ($names as $key => $n) {
                    $datasK = repo('segment')->getData((int) $key);
                    $segmentDatas[$key] = $datasK;
                }

                $orders = $grandfathers = [];

                foreach ($families as $s => $fathers) {
                    $f = 0;

                    foreach ($fathers as $father) {
                        if ($f == 2) {
                            $orders[$s] = isAke($father['data'], 'ordre', 1);
                            $grandfathers[$s] = $father;
                        }

                        $f++;
                    }
                }

                $row->composed              = $composed;
                $row->grandfathers          = $grandfathers;
                $row->orders                = $orders;
                $row->datas                 = $segmentDatas;
                $row->families              = $families;
                $row->names                 = $names;
                $row->all_plats             = $allPlats;
                $row->loc                   = $locationReseller;
                $row->options               = $options;
                $row->schedules             = $schedules;
                $row->status_zechallenge    = $status_zechallenge;

                $q = Model::Zenews()
                ->where(['status', '=', 'ACTIVE'])
                ->where(['context', '=', 'resto'])
                ->where(['reseller_id', '=', (int) $resto['reseller_id']])
                ->cursor()
                ->count();

                $hmz = Model::Myzelift()
                ->where(['reseller_id', '=', (int) $resto['reseller_id']])
                ->where(['status', '=', 'ACTIVE'])
                ->first(true);

                if (!$hmz) {
                    $hmz = false;
                } else {
                    $hmz = true;
                }

                $row->has_myzelift  = $hmz;
                $row->has_news  = $q > 0 ? true : false;
                $row->pois = [
                    'dispos'    => $pois_dispo,
                    'distances' => $distances,
                    'families'  => $families_pois_dispo
                ];

                $row->specialites   = ['dispos' => $specialites_dispo, 'families' => $families_specialites_dispo, 'plats' => $spe_plats];
                $row->auto          = ['dispos' => $types_auto_dispo, 'families' => $families_types_auto_dispo, 'plats' => $auto_plats];
                $row->non_auto      = ['dispos' => $types_non_auto_dispo, 'families' => $families_types_non_auto_dispo, 'plats' => $non_auto_plats];

                /* Rating */

                $rates = Model::Rating()
                ->where(['reseller_id', '=', (int) $resto['reseller_id']])
                ->cursor();

                $totalRate = 0;

                if ($rates->count() == 0) {
                    $row->rate = 0;
                } else {
                    foreach ($rates as $rateRow) {
                        $totalRate += (double) $rateRow['rate'];
                    }

                    $row->rate = (double) $totalRate / $rates->count();
                }

                $row->plats = [
                    'ordre'         => $orders,
                    'prices'        => $pricePlats,
                    'segments'      => array_keys($pricePlats),
                    'catalog'       => $restoPlats,
                    'families'      => $families_plats_dispo,
                    'names'         => $plats_names,
                    'catalog_names' => $catalog_names,
                    'fathers'       => $fathers
                ];

                $resas = [];
                $customers = [];

                $old = Model::Restoreservation()
                ->where(['reseller_id', '=', (int) $resto['reseller_id']])
                ->where(['updated_at', '>=', strtotime('-6 month')])
                ->cursor();

                foreach ($old as $resa) {
                    $resas[] = ['account_id' => $resa['account_id'], 'date' => $resa['updated_at'], 'filter' => $resa['filter']];

                    if (!isset($customers[$resa['account_id']])) {
                        $customers[$resa['account_id']] = 1;
                    } else {
                        $customers[$resa['account_id']]++;
                    }
                }

                $row->assocs    = $assocs;
                $row->resas     = $resas;
                $row->customers = $customers;
                $row->carte     = $carte;

                $nc = $c = [];

                $categories = Model::Catalogcategory()->cursor();

                foreach ($categories as $cat) {
                    $collection = lib('collection', [$carte[$cat['id']]])->sortBy('ordre')->toArray();
                    $nc[$cat['name']] = array_values($collection);
                }

                $row->plats_ranges = $nc;
                $row->themes = lib('myzelift')->getThemesAffil($resto['reseller_id']);

                $extras = Model::Extradata()->where(['reseller_id', '=', (int) $resto['reseller_id']])->cursor()->first();

                if (empty($extras)) {
                    $extras = ['access' => 'Prendre Tram 1 descendre à l\'arrêt Godrans.'];
                }

                $row->extras = $extras;

                $ups = [];

                $uplifts = Model::Uplift()
                ->where(['status', '=', 'ACTIVE'])
                ->where(['start', '<=', time()])
                ->where(['reseller_id', '=', (int) $resto['reseller_id']])
                ->cursor();

                foreach ($uplifts as $uplift) {
                    unset($uplift['_id']);
                    unset($uplift['created_at']);
                    unset($uplift['updated_at']);
                    // unset($uplift['reseller_id']);

                    $type = Model::Uplifttype()->find($uplift['uplifttype_id'], false);
                    unset($type['created_at']);
                    unset($type['updated_at']);
                    unset($type['id']);

                    $uplift['type'] = $type;

                    if (!isset($uplift['duration']) && isset($uplift['upliftduration_id'])) {
                        $duration = Model::Upliftduration()->find($uplift['upliftduration_id'], false);
                        unset($duration['created_at']);
                        unset($duration['updated_at']);
                        unset($duration['id']);

                        $uplift['duration'] = $duration;
                    }

                    $views = isset($uplift['views']) ? $uplift['views'] : 0;

                    if (100 >= $views) {
                        $ups[] = $uplift;
                    } else {
                        if ($uplift['end'] > time()) {
                            $ups[] = $uplift;
                        }
                    }
                }

                $row->uplifts           = $ups;
                $row->jours             = $jours;
                $row->services          = $services;
                $row->contraintes       = $contraintes;
                $row->accompagnements   = $accompagnements;
                $row->descriptions      = $descriptions;
                $row->contraintes       = $contraintes;

                $row->save();
            }
        }

        public function fiche($reseller_id, $account_id = null)
        {
            $return = [];

            if (is_null($account_id)) {
                $user       = session('user')->getUser();
                $account_id = (int) $user['id'];
            }

            $sid = $this->session_id();

            $fiche = Model::Restodata()->where(['reseller_id', '=', (int) $reseller_id])->first(true);

            $ir = Save::get('infos.data.resto.' . $account_id . '.' . $sid);
            $oi = Save::get('offer.in.' . $account_id . '.' . $sid);

            if (!$ir) {
                $ir = [];
            } else {
                $ir = unserialize($ir);
            }

            if ($fiche) {
                $return             = $fiche->toArray();
                $jours              = isAke($return, 'jours', []);
                $services           = isAke($return, 'services', []);
                $return['extras']   = isAke($return, 'extras', []);

                if (!$oi) {
                    $oi = serialize([]);
                }

                $datasPc = unserialize($oi);

                $restos     = isAke($datasPc, 'restaurants', []);
                $jour       = isAke($datasPc, 'jour', lib('time')->frenchDay());
                $service    = isAke($datasPc, 'service', 1);

                if (empty($restos)) {
                    return $return;
                }

                $nc = $c = [];

                $categories = Model::Catalogcategory()->cursor();

                foreach ($categories as $cat) {
                    $pls = $return['carte'][$cat['id']];
                    $cpls = [];

                    foreach ($pls as $segPlt) {
                        $contraintesJour    = isset($jours[$segPlt['id']]) ? $jours[$segPlt['id']] : [];
                        $contraintesService = isset($services[$segPlt['id']]) ? $services[$segPlt['id']] : [];

                        $add = true;

                        if (!empty($contraintesJour)) {
                            $add = in_array($jour, $contraintesJour);
                        }

                        if (!empty($contraintesService) && $add) {
                            $add = in_array($service, $contraintesService);
                        }

                        if ($add) {
                            $cpls[] = $segPlt;
                        }
                    }

                    $nc[] = [
                        'name'  => $cat['name'],
                        'plats' => $cpls
                    ];
                }

                $return['carte_ordonnee'] = $nc;

                foreach ($restos as $res) {
                    if ($res['id'] == $reseller_id) {
                        break;
                    }
                }

                redis()->set('infos.restos.' . $account_id . '.' . $reseller_id, serialize($res));

                $return['plats_choisis'] = isAke($res, 'plats_choisis', []);
                $ap = isAke($res, 'all_plats', []);

                if (!empty($return['plats_choisis'])) {
                    $keyCache = sha1($reseller_id . $this->session_id() . 'platschoisis');

                    $cp = redis()->get($keyCache);

                    if (!$cp) {
                        $pc1 = $return['plats_choisis'];

                        $pc2 = $tups = [];

                        foreach ($pc1 as $pc11) {
                            $plats = isAke($pc11, 'plats', []);

                            $c = [];

                            if (!empty($plats)) {
                                foreach ($plats as $p) {
                                    $n = $p['catalog_name'];

                                    if (in_array($p['id'], $tups)) {
                                        continue;
                                    }

                                    $tups[] = $p['id'];

                                    $p['name'] = $n;

                                    $i = isset($ap[$p['id']]) ? $ap[$p['id']] : [];

                                    if (!isset($p['description'])) {
                                        $p['description'] = isAke($i, 'description', '');
                                    } else {
                                        if (empty($p['description'])) {
                                            $p['description'] = isAke($i, 'description', '');
                                        }
                                    }

                                    if (!isset($p['accompagnement'])) {
                                        $p['accompagnement'] = isAke($i, 'accompagnement', '');
                                    } else {
                                        if (empty($p['accompagnement'])) {
                                            $p['accompagnement'] = isAke($i, 'accompagnement', '');
                                        }
                                    }

                                    unset($p['catalog_name']);

                                    $c[] = $p;
                                }

                                $pc11['plats'] = $c;
                            } else {
                                if (in_array($pc11['id'], $tups)) {
                                    continue;
                                }

                                $tups[] = $pc11['id'];

                                $pc11['plats']  = [];
                                $i                  = isset($ap[$pc11['id']]) ? $ap[$pc11['id']] : [];
                                $pt                 = $this->infoPlates($return, $pc11['id']);
                                $pt['name']         = $pc11['catalog_name'];
                                $pc11['plats'][]    = $pt;
                            }

                            $pc2[] = $pc11;
                        }

                        $return['plats_choisis'] = $pc2;
                    } else {
                        $return['plats_choisis'] = unserialize($cp);
                        // redis()->del($keyCache);
                    }
                }

                unset($return['names']);
                unset($return['assocs']);

                $return['infos'] = isset($ir[$reseller_id]) ? $ir[$reseller_id] : [];

                $ap = isAke($return['infos'], 'all_plats', []);
                $orders = isake($return, 'orders', []);

                $collection = [];

                foreach ($ap as $idAp => $ap) {
                    $ap             = current($ap);
                    $ordre          = isset($orders[$idAp]) ? $orders[$idAp] : 1;
                    $ap['ordre']    = $ordre;

                    $collection[] = $ap;
                }

                $collection = lib('collection', [$collection])->sortBy('ordre')->toArray();

                $return['infos']['all_plats'] = array_values($collection);

                if (!empty($account_id)) {
                    Model::Restoview()->firstOrCreate([
                        'date'          => date('d-m-Y'),
                        'account_id'    => (int) $account_id,
                        'reseller_id'   => (int) $reseller_id
                    ]);
                }

                unset($return['all_plats']);
            } else {
                die('resto introuvable');
            }

            unset($return['infos']);

            Save::setExpire('return.in.' . $account_id . '.' . $sid, $return, 120);

            $return['session_id'] = session_id();

            return $return;
        }

        public function makeOfferOutMyZelift($reseller_id, $filter, $account_id = null)
        {
            if (is_null($account_id)) {
                $user       = session('user')->getUser();
                $account_id = (int) $user['id'];
            }

            if (empty($account_id)) {
                return [
                    'success' => false,
                    'message' => 'account_id undefined',
                    'status' => 1
                ];
            }

            $resto = Model::Restodata()
            ->where(["reseller_id", "=", (int) $reseller_id])
            ->first();

            if (empty($resto)) {
                return [
                    'success'   => false,
                    'message'   => 'Reseller ' . $reseller_id . ' not found.',
                    'status'    => 4
                ];
            }

            $now = time();

            $account_id         = isAke($filter, 'account_id', 26);
            $nb_customer        = isAke($filter, 'nb_customer', 1);
            $distance           = isAke($filter, 'distance', 0);
            $is_now             = isAke($filter, 'now', 0);
            $mode_conso         = isAke($filter, 'mode_conso', 2);
            $budget             = isAke($filter, 'budget', 0);
            $poi                = isAke($filter, 'poi', false);
            $date               = isAke($filter, 'date', date('Y-m-d'));
            $hour               = isAke($filter, 'time', date('H') > 14 ? '19:30' : '12:30');

            list($h, $i)        = explode(':', $hour, 2);
            list($y, $m, $d)    = explode('-', $date, 3);

            $ts = mktime($h, $i, 0, $m, $d, $y);

            $minTime = $now - 1800;

            if ($ts < $minTime) {
                return [
                    'success'   => false,
                    'message'   => "The hour [" . $hour . "] is too early. Min hour is " . date('H:i', $minTime),
                    'status'    => 5
                ];
            }

            $mode_conso     = $mode_conso == 0 ? 2 : $mode_conso;
            $last_minute    = $is_now > 0;

            switch ($mode_conso) {
                case 1:
                    $type_conso = 'en_livraison';

                    break;
                case 2:
                    $type_conso = 'sur_place';

                    break;
                case 3:
                    $type_conso = 'a_emporter';

                    break;
                default:
                    $type_conso = 'sur_place';
            }

            $schedules = $resto['schedules'][$type_conso];

            list($canServe, $service, $last_minute, $fermeMidi, $fermeSoir) = $this->checkCanServe(
                (int) $reseller_id,
                (int) $nb_customer,
                $schedules,
                $date,
                $hour,
                $resto['options'],
                $type_conso,
                $last_minute
            );

            if (!empty($account_id)) {
                Model::Restoview()->firstOrCreate([
                    'date'          => date('d-m-Y'),
                    'account_id'    => (int) $account_id,
                    'reseller_id'   => (int) $reseller_id
                ]);
            }

            if (!$canServe) {
                if ($service == 1 && $fermeMidi) {
                    return [
                        'success'   => false,
                        'message'   => 'Reseller ' . $reseller_id . ' closed.',
                        'status'    => 7
                    ];
                } elseif ($service == 2 && $fermeSoir) {
                    return [
                        'success'   => false,
                        'message'   => 'Reseller ' . $reseller_id . ' closed.',
                        'status'    => 7
                    ];
                } else {
                    return [
                        'success'   => false,
                        'message'   => 'Reseller ' . $reseller_id . ' not available.',
                        'status'    => 6
                    ];
                }
            } else {
                list($y, $m, $d)    = explode('-', $date);
                list($h, $i)        = explode(':', $hour);

                $ts = mktime($h, $i, 0, $m, $d, $y);

                unset($filter['ACTION']);
                unset($filter['t']);

                $voucher = $this->makeVoucher();

                $offerout = Model::Restoofferout()->create([
                    'status'        => 'WAIT',
                    'filter'        => $filter,
                    'stock'         => (int) $nb_customer,
                    'hour'          => $hour,
                    'date'          => $date,
                    'timestamp'     => (int) $ts,
                    'voucher'       => $voucher,
                    'last_minute'   => $last_minute,
                    'service'       => (int) $service,
                    'account_id'    => (int) $account_id,
                    'reseller_id'   => (int) $reseller_id
                ])->save();

                return [
                    'success'           => true,
                    'status'            => 0,
                    'restoofferout_id'  => $offerout->id,
                    'last_minute'       => $last_minute,
                    'voucher'           => $voucher,
                    'filter'            => $filter
                ];
            }
        }

        public function makeOfferOut($reseller_id, $nb_customer = 0, $account_id = null)
        {
            if (is_null($account_id)) {
                $user       = session('user')->getUser();
                $account_id = (int) $user['id'];
            }

            if (empty($account_id)) {
                return [
                    'success' => false,
                    'message' => 'account_id undefined',
                    'status' => 1
                ];
            }

            $sid = $this->session_id();

            $offerin = Save::get('offer.in.' . $account_id . '.' . $sid);

            if (!$offerin) {
                return [
                    'success'   => false,
                    'message'   => 'no offer in found for account_id => ' . $account_id,
                    'status'    => 2
                ];
            }

            $offerin = unserialize($offerin);

            $filter = isAke($offerin, 'filter', false);

            $nb_customer = 0 > $nb_customer ? $nb_customer : isAke($filter, 'nb_customer', 1);

            if (false === $filter) {
                return [
                    'success'   => false,
                    'message'   => 'no offerin found for account_id => ' . $account_id,
                    'status'    => 3
                ];
            }

            $resto = Model::Restodata()
            ->where(["reseller_id", "=", (int) $reseller_id])
            ->first();

            if (empty($resto)) {
                return [
                    'success'   => false,
                    'message'   => 'Reseller ' . $reseller_id . ' not found.',
                    'status'    => 4
                ];
            }

            $now = time();

            $typesAuto          = isAke($filter, 'types_auto', []);
            $typesnonAuto       = isAke($filter, 'types_non_auto', []);
            $food               = isAke($filter, 'food', []);
            $specialities       = isAke($filter, 'specialities', []);
            $geo                = isAke($filter, 'geo', []);
            $account_id         = isAke($filter, 'account_id', 26);
            $sellzone_id        = isAke($filter, 'sellzone_id', 1);
            // $nb_customer        = isAke($filter, 'nb_customer', 1);
            $distance           = isAke($filter, 'distance', 0);
            $is_now             = isAke($filter, 'now', 0);
            $mode_conso         = isAke($filter, 'mode_conso', 2);
            $budget             = isAke($filter, 'budget', 0);
            $poi                = isAke($filter, 'poi', false);
            $date               = isAke($filter, 'date', date('Y-m-d'));
            $hour               = isAke($filter, 'time', date('H') > 14 ? '19:30' : '12:30');

            list($h, $i)        = explode(':', $hour, 2);
            list($y, $m, $d)    = explode('-', $date, 3);

            $ts = mktime($h, $i, 0, $m, $d, $y);

            $minTime = $now - 1800;

            if ($ts < $minTime) {
                return [
                    'success'   => false,
                    'message'   => "The hour [" . $hour . "] is too early. Min hour is " . date('H:i', $minTime),
                    'status'    => 5
                ];
            }

            $mode_conso     = $mode_conso == 0 ? 2 : $mode_conso;
            $last_minute    = $is_now > 0;

            switch ($mode_conso) {
                case 1:
                    $type_conso = 'en_livraison';

                    break;
                case 2:
                    $type_conso = 'sur_place';

                    break;
                case 3:
                    $type_conso = 'a_emporter';

                    break;
                default:
                    $type_conso = 'sur_place';
            }

            $schedules = $resto['schedules'][$type_conso];

            list($canServe, $service, $last_minute, $fermeMidi, $fermeSoir, $rStock) = $this->checkCanServe(
                (int) $reseller_id,
                (int) $nb_customer,
                $schedules,
                $date,
                $hour,
                $resto['options'],
                $type_conso,
                $last_minute
            );

            if (!empty($account_id)) {
                Model::Restoview()->firstOrCreate([
                    'date'          => date('d-m-Y'),
                    'account_id'    => (int) $account_id,
                    'reseller_id'   => (int) $reseller_id
                ]);
            }

            if (!$canServe) {
                if ($service == 1 && $fermeMidi) {
                    return [
                        'success'   => false,
                        'message'   => 'Reseller ' . $reseller_id . ' closed.',
                        'status'    => 7
                    ];
                } elseif ($service == 2 && $fermeSoir) {
                    return [
                        'success'   => false,
                        'message'   => 'Reseller ' . $reseller_id . ' closed.',
                        'status'    => 7
                    ];
                } else {
                    return [
                        'success'   => false,
                        'message'   => 'Reseller ' . $reseller_id . ' not available.',
                        'status'    => 6
                    ];
                }
            } else {
                list($y, $m, $d)    = explode('-', $date);
                list($h, $i)        = explode(':', $hour);

                $ts = mktime($h, $i, 0, $m, $d, $y);

                unset($filter['ACTION']);
                unset($filter['t']);

                $voucher = $this->makeVoucher();

                $data = redis()->get('infos.restos.' . $account_id . '.' . $reseller_id);

                if (!$data) {
                    $data = [];
                } else {
                    $data = unserialize($data);
                }

                unset($data['plats_ranges']);

                $offerout = Model::Restoofferout()->create([
                    'status'        => 'WAIT',
                    'filter'        => $filter,
                    'stock'         => (int) $nb_customer,
                    'hour'          => $hour,
                    'date'          => $date,
                    'voucher'       => $voucher,
                    'timestamp'     => (int) $ts,
                    'last_minute'   => $last_minute,
                    'service'       => (int) $service,
                    'data'          => $data,
                    'account_id'    => (int) $account_id,
                    'reseller_id'   => (int) $reseller_id
                ])->save();

                return [
                    'success'           => true,
                    'status'            => 0,
                    'restoofferout_id'  => $offerout->id,
                    'last_minute'       => $last_minute,
                    'voucher'           => $voucher,
                    'filter'            => $filter
                ];
            }
        }

        public function makeVoucher()
        {
            return 'R' . Inflector::upper(Inflector::random(9));
        }

        public function myzelift($reseller_id)
        {
            $myzelift = Model::Myzelift()
            ->where(['reseller_id', '=', (int) $reseller_id])
            // ->cursor()
            ->first();

            if (empty($myzelift)) return false;

            if (isset($myzelift['data'])) {
                return $myzelift['data'];
            }

            if (!isset($myzelift['general_intro_1'])) {
                // $myzelift = [
                //     'general_intro_1' => 'Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis intro 1',
                //     'general_intro_2' => 'Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis intro 2',
                //     'general_1' => 'Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis 1',
                //     'general_2' => 'Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis 2',
                //     'general_3' => 'Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis 3',
                //     'general_4' => 'Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis 4',
                //     'plus_1' => 'Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis 1',
                //     'plus_2' => 'Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis 1',
                //     'plus_3' => 'Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis 1',
                //     'coeur_1' => 'Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis 1',
                //     'coeur_2' => 'Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis 1',
                //     'coeur_3' => 'Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis Lorem ipsum aequitis 1',
                // ];
                //
                return false;
            }

            return $myzelift;
        }

        public function session_id()
        {
            return Now::get('session.id', session_id());
        }

        private function analyseSuggestions($choosePlates, $suggestions, $resto)
        {
            $tuples = $collection = $newPlates = [];
            $ap = isAke($resto, 'all_plats', []);

            foreach ($suggestions as $sugId) {
                $s = Model::Suggestion()->where(['segment_id', '=', $sugId])->first();
                $datas = repo('segment')->getData($sugId);
                $segments = isAke($datas, 'segments', '');

                if (fnmatch('*,*', $segments)) {
                    $segments = explode(',', $segments);
                    $item = [];
                    $item['id'] = isAke($s, 'id', '');
                    $item['name'] = isAke($s, 'name', '');
                    $item['plats'] = [];

                    foreach ($choosePlates as $cp) {
                        $plats = isAke($cp, 'plats', []);

                        foreach ($plats as $pl) {
                            $infos = isset($ap[$pl['id']]) ? current($ap[$pl['id']]) : [];
                            $attributes = $this->getAttributes(isAke($infos, 'data', []));

                            $addItem = false;

                            foreach ($attributes as $idAtt) {
                                if (in_array($idAtt, $segments)) {
                                    $addItem = true;
                                    break;
                                }
                            }

                            if ($addItem) {
                                $tuples[] = $cp['id'];
                                $item['plats'][] = $pl;
                            }
                        }
                    }

                    if (count($item['plats'])) {
                        $item['plats'] = array_values($item['plats']);
                        $item['nb'] = count($item['plats']);
                        $coll = lib('collection', [$item['plats']]);
                        $item['min_price'] = $coll->min('price');
                        $item['max_price'] = $coll->max('price');
                        $newPlates[] = $item;
                    }
                } else {
                    foreach ($choosePlates as $cp) {
                        if ($cp['id'] == $segments) {
                            $newPlates[] = $cp;
                            $tuples[] = $cp['id'];
                        }
                    }
                }
            }

            foreach ($choosePlates as $cp) {
                if (!in_array($cp['id'], $tuples)) {
                    $newPlates[] = $cp;
                }
            }

            foreach ($newPlates as $cp) {
                $plats = isAke($cp, 'plats', []);

                $cp['plats'] = array_values($plats);

                $collection[] = $cp;
            }

            return $collection;
        }

        private function getAttributes(array $tab)
        {
            $collection = [];

            foreach ($tab as $k => $v) {
                if (fnmatch('*_attribut_*', $k)) {
                    $collection[] = (int) $v;
                }
            }

            return $collection;
        }
    }
