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

    class GeoLib
    {
        public function tree()
        {
            $tree = [];
            $tree['countries'] = [];

            $countries = Model::Country()->full()->orderByName()->exec();

            $i = 0;

            foreach ($countries as $country) {
                $tree['countries'][$i] = ['id' => $country['id'], 'code' => $country['code'], 'name' => $country['name']];
                $tree['countries'][$i]['regions'] = [];

                $regions = Model::Region()->full()->orderByName()->exec();

                foreach ($regions as $region) {
                    $dpts = [];

                    $departments = Model::Department()->where(['region', '=', $region['code']])->orderByCode()->exec();

                    if (!empty($departments)) {
                        foreach ($departments as $dpt) {
                            $dpts[] = [
                                'id'    => $dpt['id'],
                                'code'  => $dpt['code'],
                                'name'  => $dpt['name']
                            ];
                        }
                    }

                    $tree['countries'][$i]['regions'][] = [
                        'id'            => $region['id'],
                        'code'          => $region['code'],
                        'name'          => $region['name'],
                        'departments'   => $dpts
                    ];
                }

                $i++;
            }

            return $tree;
        }

        public function search($q)
        {
            $html = $this->dwn("https://www.google.fr/search?num=100&newwindow=1&client=ubuntu&hs=Eb1&channel=fs&q=" . urlencode($q) . "&oq=" . urlencode($q));

            dd($html);
        }

        public function imghd($q)
        {
            // return getCached('highs.imghd.' . sha1($q), function () use ($q) {
                $html = $this->dwnCache('https://www.google.fr/search?as_st=y&tbm=isch&q='.urlencode($q).'&as_epq=&as_oq=&as_eq=&cr=&as_sitesearch=&safe=images&tbs=isz:lt,islt:4mp');

                $collection = [];

                $tab = explode('/imgres?imgurl', $html);
                array_shift($tab);

                foreach ($tab as $row) {
                    $collection[] = Utils::cut('=', '&', trim($row));
                }

                return $collection;
            // });
        }

        public function imgs($q)
        {
            return getCached('st.imgs.' . sha1($q), function () use ($q) {
                $html = lib('geo')->dwn("https://www.google.fr/search?q=" . urlencode($q) . "&newwindow=1&tbm=isch&source=lnt&safe=images&tbs=isz:lt,islt:svga,itp:photo,ic:color,iar:w");

                $collection = [];

                $tab = explode('imgres?imgurl', $html);
                array_shift($tab);

                foreach ($tab as $row) {
                    $collection[] = Utils::cut('=', '&', trim($row));
                }

                return $collection;
            });
        }

        public function panoramic($q)
        {
            return getCached('s.panoramas.' . sha1($q), function () use ($q) {
                $html = lib('geo')->dwn("https://www.google.com/search?as_st=y&tbm=isch&as_q=" . urlencode($q) . "&as_epq=&as_oq=&as_eq=&cr=&as_sitesearch=&safe=images&tbs=isz:lt,islt:svga,itp:photo,ic:color,iar:w,ift:jpg");

                $collection = [];

                $tab = explode('imgres?imgurl', $html);
                array_shift($tab);

                foreach ($tab as $row) {
                    $img = Utils::cut('=', '&', trim($row));

                    if (@getimagesize($img)) {
                        return [$img];
                    }
                }

                return $collection;
            });
        }

        public function getAdressLatLng($lat, $lng, $cursor = 1)
        {
            $url = "http://vmrest.viamichelin.com/apir/1/rgeocode.json2?center=$lng:$lat&showHT=true&obfuscation=false&ie=UTF-8&charset=UTF-8&callback=JSE.HTTP.asyncRequests[5].HTTPResponseLoaded&authKey=JSBS20110216111214120400892678&lg=fra";

            return getCached('vmrest.' . sha1($lat, $lng), function () use ($url, $lat, $lng) {
                $html = $this->dwn($url);

                $seg = Utils::cut('HTTPResponseLoaded({', '}}]})', $html);

                $json = '{' . $seg . '}}]}';

                $tab = json_decode($json, true);

                $locations = isAke($tab, 'locationList', []);

                if (!empty($locations)) {
                    $location = current($locations);
                    $location = isAke($location, 'location', []);

                    if (!empty($location)) {
                        $row                    = [];
                        $formattedAddressLine   = isAke($location, 'formattedAddressLine', null);
                        $formattedCityLine      = isAke($location, 'formattedCityLine', null);
                        $streetLabel            = isAke($location, 'streetLabel', null);
                        $city                   = isAke($location, 'city', null);
                        $postalCode             = isAke($location, 'postalCode', null);
                        $area                   = isAke($location, 'area', null);
                        $countryISO             = isAke($location, 'countryISO', null);
                        $countryLabel           = isAke($location, 'countryLabel', null);

                        if ($formattedAddressLine && $formattedCityLine) {
                            $row['address'] = $formattedAddressLine . ', ' . $formattedCityLine . ', ' . $countryLabel;
                        }

                        if (!$formattedAddressLine && $formattedCityLine) {
                            $row['address'] = $formattedCityLine . ', ' . $countryLabel;
                        }

                        $row['street']  = $streetLabel;
                        $row['city']    = $city;
                        $row['zip']     = $postalCode;
                        $row['area']    = $area;
                        $row['country'] = $countryLabel;
                        $row['lat']     = $lat;
                        $row['lng']     = $lng;

                        return $row;
                    }
                }

                return null;
            });
        }

        public function poisTouristic($lat, $lng)
        {
            /*http://vmrest.viamichelin.com/apir/1/searchLocationAround.json2?center=-99.1323531:19.4306174&maxDistance=20000&maxResult=11&lang=fra&showHT=true&filterHTLang=true&obfuscation=false&ie=UTF-8&charset=UTF-8&callback=JSE.HTTP.asyncRequests[4].HTTPResponseLoaded&authKey=JSBS20110216111214120400892678&lg=fra*/

            /*http://vmrest.viamichelin.com/apir/2/FindPOI.json2/TOURISM/eng?source=AGG&field=name;latitude;longitude;formated_address_line;formated_city_line;description;medias;address;ref_lieu;michelin_stars;phone;email;web;opening_times_label&center=-99.1323531:19.4306174&nb=10&sidx=0&facet.field=michelin_stars&facet.mincount=0&obfuscation=false&ie=UTF-8&charset=UTF-8&callback=JSE.HTTP.asyncRequests[2].HTTPResponseLoaded&authKey=JSBS20110216111214120400892678&lg=fra*/
            // $bb = $this->getBoundingBox($lat, $lng, 4);
            // $bb = $this->tileToBoundingBox($lng, $lat);

            // $url = "http://vmrest.viamichelin.com/apir/1/PoiCrit.json/{$bb[0]}:{$bb[1]}:{$lng}:{$lat}?zoomLevel=12&param=TOUMPMVM||||source:AGG&obfuscation=false&ie=UTF-8&charset=UTF-8&callback=JSE.HTTP.asyncRequests[10].HTTPResponseLoaded&authKey=JSBS20110216111214120400892678&lg=fra";
            // $html = $this->dwn($url);

            return getCached('pois.mic.' . sha1(serialize(func_get_args())) , function () use ($lat, $lng) {

                $html = lib('geo')->dwn("http://vmrest.viamichelin.com/apir/2/FindPOI.json2/TOURISM/fra?source=AGG&field=name;latitude;longitude;formated_address_line;formated_city_line;description;medias;address;ref_lieu;michelin_stars;phone;email;web;opening_times_label&center={$lng}:{$lat}&nb=100&sidx=0&facet.field=michelin_stars&facet.mincount=0&obfuscation=false&ie=UTF-8&charset=UTF-8&callback=JSE.HTTP.asyncRequests[2].HTTPResponseLoaded&authKey=JSBS20110216111214120400892678&lg=fra");

                $res = Utils::cut('HTTPResponseLoaded(', '})', $html) . '}';

                $tab = json_decode($res, 1);

                return isAke($tab, 'poiList', []);
            });
        }

        public function numTiles($zoom = 12)
        {
            return pow(2, $zoom);
        }

        public function tileToBoundingBox($x, $y, $zoom = 11)
        {
            $xtile = floor((($x + 180) / 360) * pow(2, $zoom));
            $ytile = floor((1 - log(tan(deg2rad($y)) + 1 / cos(deg2rad($y))) / pi()) / 2 * pow(2, $zoom));

            $n = pow(2, $zoom);
            $lon_deg = $xtile / $n * 360.0 - 180.0;
            $lat_deg = rad2deg(atan(sinh(pi() * (1 - 2 * $ytile / $n))));

            return [$lon_deg, $lat_deg];
        }

        public function tileToLon($x, $zoom = 12)
        {
            return $x / $this->numTiles($zoom) * 360.0 - 180.0;
        }

        public function tileToLat($y, $zoom = 12)
        {
            $n = pi() * (1 - 2 * $y / $this->numTiles($zoom));

            return rad2deg(atan(sinh($n)));
        }

        public function features($lat, $lng)
        {
            $res = [];

            $a = func_get_args();

            $flat = $lat;
            $flng = $lng;

            $xy = $this->llToTile($lat, $lng);

            $x = current($xy);
            $y = end($xy);

            $json = $this->dwnCache("https://www.google.com/maps/vt?pb=!1m4!1m3!1i18!2i$x!3i$y!2m3!1e0!2sm!3i330397977!2m28!1e2!2sspotlight!4m2!1sgid!tG9yOZZcAaKLR2aIp2yfTQ!5i1!8m21!1m11!2m7!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2svannes!4m2!3d48.8430891!4d2.3158186!5e0!6b1!11e11!13m1!14b1!2m6!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2stour+eiffel!4m2!3d43.2271796!4d6.143419799999999!5e0!13m1!14b1!3m14!2sfr!3sFR!5e289!12m4!1e52!2m2!1sentity_class!2s0!12m1!1e47!12m3!1e37!2m1!1ssmartmaps!4e3!12m1!5b1");

            $tab = json_decode($json, true);

            $row = $tab[1];

            $features = isAke($row, 'features', []);

            $ids = array_values(coll($features)->fetch('id')->toArray());

            $bb = $this->getBoundingBox($flat, $flng, .5);

            $lat = array_shift($bb);
            $lng = array_shift($bb);

            $xy = $this->llToTile($lat, $lng);

            $x = current($xy);
            $y = end($xy);

            $json = $this->dwnCache("https://www.google.com/maps/vt?pb=!1m4!1m3!1i18!2i$x!3i$y!2m3!1e0!2sm!3i330397977!2m28!1e2!2sspotlight!4m2!1sgid!tG9yOZZcAaKLR2aIp2yfTQ!5i1!8m21!1m11!2m7!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2svannes!4m2!3d48.8430891!4d2.3158186!5e0!6b1!11e11!13m1!14b1!2m6!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2stour+eiffel!4m2!3d43.2271796!4d6.143419799999999!5e0!13m1!14b1!3m14!2sfr!3sFR!5e289!12m4!1e52!2m2!1sentity_class!2s0!12m1!1e47!12m3!1e37!2m1!1ssmartmaps!4e3!12m1!5b1");

            $tab = json_decode($json, true);

            $row = $tab[1];

            $features = isAke($row, 'features', []);

            $ids = array_merge($ids, array_values(coll($features)->fetch('id')->toArray()));

            $lat = array_shift($bb);
            $lng = array_shift($bb);

            $xy = $this->llToTile($lat, $lng);

            $x = current($xy);
            $y = end($xy);

            $json = $this->dwnCache("https://www.google.com/maps/vt?pb=!1m4!1m3!1i18!2i$x!3i$y!2m3!1e0!2sm!3i330397977!2m28!1e2!2sspotlight!4m2!1sgid!tG9yOZZcAaKLR2aIp2yfTQ!5i1!8m21!1m11!2m7!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2svannes!4m2!3d48.8430891!4d2.3158186!5e0!6b1!11e11!13m1!14b1!2m6!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2stour+eiffel!4m2!3d43.2271796!4d6.143419799999999!5e0!13m1!14b1!3m14!2sfr!3sFR!5e289!12m4!1e52!2m2!1sentity_class!2s0!12m1!1e47!12m3!1e37!2m1!1ssmartmaps!4e3!12m1!5b1");

            $tab = json_decode($json, true);

            $row = $tab[1];

            $features = isAke($row, 'features', []);

            $ids = array_merge($ids, array_values(coll($features)->fetch('id')->toArray()));

            $bb = $this->getBoundingBox($flat, $flng, 1);

            $lat = array_shift($bb);
            $lng = array_shift($bb);

            $xy = $this->llToTile($lat, $lng);

            $x = current($xy);
            $y = end($xy);

            $json = $this->dwnCache("https://www.google.com/maps/vt?pb=!1m4!1m3!1i18!2i$x!3i$y!2m3!1e0!2sm!3i330397977!2m28!1e2!2sspotlight!4m2!1sgid!tG9yOZZcAaKLR2aIp2yfTQ!5i1!8m21!1m11!2m7!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2svannes!4m2!3d48.8430891!4d2.3158186!5e0!6b1!11e11!13m1!14b1!2m6!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2stour+eiffel!4m2!3d43.2271796!4d6.143419799999999!5e0!13m1!14b1!3m14!2sfr!3sFR!5e289!12m4!1e52!2m2!1sentity_class!2s0!12m1!1e47!12m3!1e37!2m1!1ssmartmaps!4e3!12m1!5b1");

            $tab = json_decode($json, true);

            $row = $tab[1];

            $features = isAke($row, 'features', []);

            $ids = array_merge($ids, array_values(coll($features)->fetch('id')->toArray()));

            $lat = array_shift($bb);
            $lng = array_shift($bb);

            $xy = $this->llToTile($lat, $lng);

            $x = current($xy);
            $y = end($xy);

            $json = $this->dwnCache("https://www.google.com/maps/vt?pb=!1m4!1m3!1i18!2i$x!3i$y!2m3!1e0!2sm!3i330397977!2m28!1e2!2sspotlight!4m2!1sgid!tG9yOZZcAaKLR2aIp2yfTQ!5i1!8m21!1m11!2m7!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2svannes!4m2!3d48.8430891!4d2.3158186!5e0!6b1!11e11!13m1!14b1!2m6!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2stour+eiffel!4m2!3d43.2271796!4d6.143419799999999!5e0!13m1!14b1!3m14!2sfr!3sFR!5e289!12m4!1e52!2m2!1sentity_class!2s0!12m1!1e47!12m3!1e37!2m1!1ssmartmaps!4e3!12m1!5b1");

            $tab = json_decode($json, true);

            $row = $tab[1];

            $features = isAke($row, 'features', []);

            $ids = array_merge($ids, array_values(coll($features)->fetch('id')->toArray()));

            $bb = $this->getBoundingBox($flat, $flng, 1.5);

            $lat = array_shift($bb);
            $lng = array_shift($bb);

            $xy = $this->llToTile($lat, $lng);

            $x = current($xy);
            $y = end($xy);

            $json = $this->dwnCache("https://www.google.com/maps/vt?pb=!1m4!1m3!1i18!2i$x!3i$y!2m3!1e0!2sm!3i330397977!2m28!1e2!2sspotlight!4m2!1sgid!tG9yOZZcAaKLR2aIp2yfTQ!5i1!8m21!1m11!2m7!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2svannes!4m2!3d48.8430891!4d2.3158186!5e0!6b1!11e11!13m1!14b1!2m6!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2stour+eiffel!4m2!3d43.2271796!4d6.143419799999999!5e0!13m1!14b1!3m14!2sfr!3sFR!5e289!12m4!1e52!2m2!1sentity_class!2s0!12m1!1e47!12m3!1e37!2m1!1ssmartmaps!4e3!12m1!5b1");

            $tab = json_decode($json, true);

            $row = $tab[1];

            $features = isAke($row, 'features', []);

            $ids = array_merge($ids, array_values(coll($features)->fetch('id')->toArray()));

            $lat = array_shift($bb);
            $lng = array_shift($bb);

            $xy = $this->llToTile($lat, $lng);

            $x = current($xy);
            $y = end($xy);

            $json = $this->dwnCache("https://www.google.com/maps/vt?pb=!1m4!1m3!1i18!2i$x!3i$y!2m3!1e0!2sm!3i330397977!2m28!1e2!2sspotlight!4m2!1sgid!tG9yOZZcAaKLR2aIp2yfTQ!5i1!8m21!1m11!2m7!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2svannes!4m2!3d48.8430891!4d2.3158186!5e0!6b1!11e11!13m1!14b1!2m6!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2stour+eiffel!4m2!3d43.2271796!4d6.143419799999999!5e0!13m1!14b1!3m14!2sfr!3sFR!5e289!12m4!1e52!2m2!1sentity_class!2s0!12m1!1e47!12m3!1e37!2m1!1ssmartmaps!4e3!12m1!5b1");

            $tab = json_decode($json, true);

            $row = $tab[1];

            $features = isAke($row, 'features', []);

            $ids = array_merge($ids, array_values(coll($features)->fetch('id')->toArray()));

            $bb = $this->getBoundingBox($flat, $flng, 2);

            $lat = array_shift($bb);
            $lng = array_shift($bb);

            $xy = $this->llToTile($lat, $lng);

            $x = current($xy);
            $y = end($xy);

            $json = $this->dwnCache("https://www.google.com/maps/vt?pb=!1m4!1m3!1i18!2i$x!3i$y!2m3!1e0!2sm!3i330397977!2m28!1e2!2sspotlight!4m2!1sgid!tG9yOZZcAaKLR2aIp2yfTQ!5i1!8m21!1m11!2m7!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2svannes!4m2!3d48.8430891!4d2.3158186!5e0!6b1!11e11!13m1!14b1!2m6!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2stour+eiffel!4m2!3d43.2271796!4d6.143419799999999!5e0!13m1!14b1!3m14!2sfr!3sFR!5e289!12m4!1e52!2m2!1sentity_class!2s0!12m1!1e47!12m3!1e37!2m1!1ssmartmaps!4e3!12m1!5b1");

            $tab = json_decode($json, true);

            $row = $tab[1];

            $features = isAke($row, 'features', []);

            $ids = array_merge($ids, array_values(coll($features)->fetch('id')->toArray()));

            $lat = array_shift($bb);
            $lng = array_shift($bb);

            $xy = $this->llToTile($lat, $lng);

            $x = current($xy);
            $y = end($xy);

            $json = $this->dwnCache("https://www.google.com/maps/vt?pb=!1m4!1m3!1i18!2i$x!3i$y!2m3!1e0!2sm!3i330397977!2m28!1e2!2sspotlight!4m2!1sgid!tG9yOZZcAaKLR2aIp2yfTQ!5i1!8m21!1m11!2m7!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2svannes!4m2!3d48.8430891!4d2.3158186!5e0!6b1!11e11!13m1!14b1!2m6!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2stour+eiffel!4m2!3d43.2271796!4d6.143419799999999!5e0!13m1!14b1!3m14!2sfr!3sFR!5e289!12m4!1e52!2m2!1sentity_class!2s0!12m1!1e47!12m3!1e37!2m1!1ssmartmaps!4e3!12m1!5b1");

            $tab = json_decode($json, true);

            $row = $tab[1];

            $features = isAke($row, 'features', []);

            $ids = array_merge($ids, array_values(coll($features)->fetch('id')->toArray()));

            $bb = $this->getBoundingBox($flat, $flng, 2.5);

            $lat = array_shift($bb);
            $lng = array_shift($bb);

            $xy = $this->llToTile($lat, $lng);

            $x = current($xy);
            $y = end($xy);

            $json = $this->dwnCache("https://www.google.com/maps/vt?pb=!1m4!1m3!1i18!2i$x!3i$y!2m3!1e0!2sm!3i330397977!2m28!1e2!2sspotlight!4m2!1sgid!tG9yOZZcAaKLR2aIp2yfTQ!5i1!8m21!1m11!2m7!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2svannes!4m2!3d48.8430891!4d2.3158186!5e0!6b1!11e11!13m1!14b1!2m6!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2stour+eiffel!4m2!3d43.2271796!4d6.143419799999999!5e0!13m1!14b1!3m14!2sfr!3sFR!5e289!12m4!1e52!2m2!1sentity_class!2s0!12m1!1e47!12m3!1e37!2m1!1ssmartmaps!4e3!12m1!5b1");

            $tab = json_decode($json, true);

            $row = $tab[1];

            $features = isAke($row, 'features', []);

            $ids = array_merge($ids, array_values(coll($features)->fetch('id')->toArray()));

            $lat = array_shift($bb);
            $lng = array_shift($bb);

            $xy = $this->llToTile($lat, $lng);

            $x = current($xy);
            $y = end($xy);

            $json = $this->dwnCache("https://www.google.com/maps/vt?pb=!1m4!1m3!1i18!2i$x!3i$y!2m3!1e0!2sm!3i330397977!2m28!1e2!2sspotlight!4m2!1sgid!tG9yOZZcAaKLR2aIp2yfTQ!5i1!8m21!1m11!2m7!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2svannes!4m2!3d48.8430891!4d2.3158186!5e0!6b1!11e11!13m1!14b1!2m6!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2stour+eiffel!4m2!3d43.2271796!4d6.143419799999999!5e0!13m1!14b1!3m14!2sfr!3sFR!5e289!12m4!1e52!2m2!1sentity_class!2s0!12m1!1e47!12m3!1e37!2m1!1ssmartmaps!4e3!12m1!5b1");

            $tab = json_decode($json, true);

            $row = $tab[1];

            $features = isAke($row, 'features', []);

            $ids = array_merge($ids, array_values(coll($features)->fetch('id')->toArray()));

            $bb = $this->getBoundingBox($flat, $flng, 3);

            $lat = array_shift($bb);
            $lng = array_shift($bb);

            $xy = $this->llToTile($lat, $lng);

            $x = current($xy);
            $y = end($xy);

            $json = $this->dwnCache("https://www.google.com/maps/vt?pb=!1m4!1m3!1i18!2i$x!3i$y!2m3!1e0!2sm!3i330397977!2m28!1e2!2sspotlight!4m2!1sgid!tG9yOZZcAaKLR2aIp2yfTQ!5i1!8m21!1m11!2m7!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2svannes!4m2!3d48.8430891!4d2.3158186!5e0!6b1!11e11!13m1!14b1!2m6!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2stour+eiffel!4m2!3d43.2271796!4d6.143419799999999!5e0!13m1!14b1!3m14!2sfr!3sFR!5e289!12m4!1e52!2m2!1sentity_class!2s0!12m1!1e47!12m3!1e37!2m1!1ssmartmaps!4e3!12m1!5b1");

            $tab = json_decode($json, true);

            $row = $tab[1];

            $features = isAke($row, 'features', []);

            $ids = array_merge($ids, array_values(coll($features)->fetch('id')->toArray()));

            $lat = array_shift($bb);
            $lng = array_shift($bb);

            $xy = $this->llToTile($lat, $lng);

            $x = current($xy);
            $y = end($xy);

            $json = $this->dwnCache("https://www.google.com/maps/vt?pb=!1m4!1m3!1i18!2i$x!3i$y!2m3!1e0!2sm!3i330397977!2m28!1e2!2sspotlight!4m2!1sgid!tG9yOZZcAaKLR2aIp2yfTQ!5i1!8m21!1m11!2m7!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2svannes!4m2!3d48.8430891!4d2.3158186!5e0!6b1!11e11!13m1!14b1!2m6!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2stour+eiffel!4m2!3d43.2271796!4d6.143419799999999!5e0!13m1!14b1!3m14!2sfr!3sFR!5e289!12m4!1e52!2m2!1sentity_class!2s0!12m1!1e47!12m3!1e37!2m1!1ssmartmaps!4e3!12m1!5b1");

            $tab = json_decode($json, true);

            $row = $tab[1];

            $features = isAke($row, 'features', []);

            $ids = array_merge($ids, array_values(coll($features)->fetch('id')->toArray()));

            $bb = $this->getBoundingBox($flat, $flng, 3.5);

            $lat = array_shift($bb);
            $lng = array_shift($bb);

            $xy = $this->llToTile($lat, $lng);

            $x = current($xy);
            $y = end($xy);

            $json = $this->dwnCache("https://www.google.com/maps/vt?pb=!1m4!1m3!1i18!2i$x!3i$y!2m3!1e0!2sm!3i330397977!2m28!1e2!2sspotlight!4m2!1sgid!tG9yOZZcAaKLR2aIp2yfTQ!5i1!8m21!1m11!2m7!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2svannes!4m2!3d48.8430891!4d2.3158186!5e0!6b1!11e11!13m1!14b1!2m6!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2stour+eiffel!4m2!3d43.2271796!4d6.143419799999999!5e0!13m1!14b1!3m14!2sfr!3sFR!5e289!12m4!1e52!2m2!1sentity_class!2s0!12m1!1e47!12m3!1e37!2m1!1ssmartmaps!4e3!12m1!5b1");

            $tab = json_decode($json, true);

            $row = $tab[1];

            $features = isAke($row, 'features', []);

            $ids = array_merge($ids, array_values(coll($features)->fetch('id')->toArray()));

            $lat = array_shift($bb);
            $lng = array_shift($bb);

            $xy = $this->llToTile($lat, $lng);

            $x = current($xy);
            $y = end($xy);

            $json = $this->dwnCache("https://www.google.com/maps/vt?pb=!1m4!1m3!1i18!2i$x!3i$y!2m3!1e0!2sm!3i330397977!2m28!1e2!2sspotlight!4m2!1sgid!tG9yOZZcAaKLR2aIp2yfTQ!5i1!8m21!1m11!2m7!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2svannes!4m2!3d48.8430891!4d2.3158186!5e0!6b1!11e11!13m1!14b1!2m6!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2stour+eiffel!4m2!3d43.2271796!4d6.143419799999999!5e0!13m1!14b1!3m14!2sfr!3sFR!5e289!12m4!1e52!2m2!1sentity_class!2s0!12m1!1e47!12m3!1e37!2m1!1ssmartmaps!4e3!12m1!5b1");

            $tab = json_decode($json, true);

            $row = $tab[1];

            $features = isAke($row, 'features', []);

            $ids = array_merge($ids, array_values(coll($features)->fetch('id')->toArray()));

            $bb = $this->getBoundingBox($flat, $flng, 4);

            $lat = array_shift($bb);
            $lng = array_shift($bb);

            $xy = $this->llToTile($lat, $lng);

            $x = current($xy);
            $y = end($xy);

            $json = $this->dwnCache("https://www.google.com/maps/vt?pb=!1m4!1m3!1i18!2i$x!3i$y!2m3!1e0!2sm!3i330397977!2m28!1e2!2sspotlight!4m2!1sgid!tG9yOZZcAaKLR2aIp2yfTQ!5i1!8m21!1m11!2m7!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2svannes!4m2!3d48.8430891!4d2.3158186!5e0!6b1!11e11!13m1!14b1!2m6!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2stour+eiffel!4m2!3d43.2271796!4d6.143419799999999!5e0!13m1!14b1!3m14!2sfr!3sFR!5e289!12m4!1e52!2m2!1sentity_class!2s0!12m1!1e47!12m3!1e37!2m1!1ssmartmaps!4e3!12m1!5b1");

            $tab = json_decode($json, true);

            $row = $tab[1];

            $features = isAke($row, 'features', []);

            $ids = array_merge($ids, array_values(coll($features)->fetch('id')->toArray()));

            $lat = array_shift($bb);
            $lng = array_shift($bb);

            $xy = $this->llToTile($lat, $lng);

            $x = current($xy);
            $y = end($xy);

            $json = $this->dwnCache("https://www.google.com/maps/vt?pb=!1m4!1m3!1i18!2i$x!3i$y!2m3!1e0!2sm!3i330397977!2m28!1e2!2sspotlight!4m2!1sgid!tG9yOZZcAaKLR2aIp2yfTQ!5i1!8m21!1m11!2m7!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2svannes!4m2!3d48.8430891!4d2.3158186!5e0!6b1!11e11!13m1!14b1!2m6!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2stour+eiffel!4m2!3d43.2271796!4d6.143419799999999!5e0!13m1!14b1!3m14!2sfr!3sFR!5e289!12m4!1e52!2m2!1sentity_class!2s0!12m1!1e47!12m3!1e37!2m1!1ssmartmaps!4e3!12m1!5b1");

            $tab = json_decode($json, true);

            $row = $tab[1];

            $features = isAke($row, 'features', []);

            $ids = array_merge($ids, array_values(coll($features)->fetch('id')->toArray()));

            $bb = $this->getBoundingBox($flat, $flng, 4.5);

            $lat = array_shift($bb);
            $lng = array_shift($bb);

            $xy = $this->llToTile($lat, $lng);

            $x = current($xy);
            $y = end($xy);

            $json = $this->dwnCache("https://www.google.com/maps/vt?pb=!1m4!1m3!1i18!2i$x!3i$y!2m3!1e0!2sm!3i330397977!2m28!1e2!2sspotlight!4m2!1sgid!tG9yOZZcAaKLR2aIp2yfTQ!5i1!8m21!1m11!2m7!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2svannes!4m2!3d48.8430891!4d2.3158186!5e0!6b1!11e11!13m1!14b1!2m6!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2stour+eiffel!4m2!3d43.2271796!4d6.143419799999999!5e0!13m1!14b1!3m14!2sfr!3sFR!5e289!12m4!1e52!2m2!1sentity_class!2s0!12m1!1e47!12m3!1e37!2m1!1ssmartmaps!4e3!12m1!5b1");

            $tab = json_decode($json, true);

            $row = $tab[1];

            $features = isAke($row, 'features', []);

            $ids = array_merge($ids, array_values(coll($features)->fetch('id')->toArray()));

            $lat = array_shift($bb);
            $lng = array_shift($bb);

            $xy = $this->llToTile($lat, $lng);

            $x = current($xy);
            $y = end($xy);

            $json = $this->dwnCache("https://www.google.com/maps/vt?pb=!1m4!1m3!1i18!2i$x!3i$y!2m3!1e0!2sm!3i330397977!2m28!1e2!2sspotlight!4m2!1sgid!tG9yOZZcAaKLR2aIp2yfTQ!5i1!8m21!1m11!2m7!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2svannes!4m2!3d48.8430891!4d2.3158186!5e0!6b1!11e11!13m1!14b1!2m6!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2stour+eiffel!4m2!3d43.2271796!4d6.143419799999999!5e0!13m1!14b1!3m14!2sfr!3sFR!5e289!12m4!1e52!2m2!1sentity_class!2s0!12m1!1e47!12m3!1e37!2m1!1ssmartmaps!4e3!12m1!5b1");

            $tab = json_decode($json, true);

            $row = $tab[1];

            $features = isAke($row, 'features', []);

            $ids = array_merge($ids, array_values(coll($features)->fetch('id')->toArray()));

            $bb = $this->getBoundingBox($flat, $flng, 5);

            $lat = array_shift($bb);
            $lng = array_shift($bb);

            $xy = $this->llToTile($lat, $lng);

            $x = current($xy);
            $y = end($xy);

            $json = $this->dwnCache("https://www.google.com/maps/vt?pb=!1m4!1m3!1i18!2i$x!3i$y!2m3!1e0!2sm!3i330397977!2m28!1e2!2sspotlight!4m2!1sgid!tG9yOZZcAaKLR2aIp2yfTQ!5i1!8m21!1m11!2m7!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2svannes!4m2!3d48.8430891!4d2.3158186!5e0!6b1!11e11!13m1!14b1!2m6!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2stour+eiffel!4m2!3d43.2271796!4d6.143419799999999!5e0!13m1!14b1!3m14!2sfr!3sFR!5e289!12m4!1e52!2m2!1sentity_class!2s0!12m1!1e47!12m3!1e37!2m1!1ssmartmaps!4e3!12m1!5b1");

            $tab = json_decode($json, true);

            $row = $tab[1];

            $features = isAke($row, 'features', []);

            $ids = array_merge($ids, array_values(coll($features)->fetch('id')->toArray()));

            $lat = array_shift($bb);
            $lng = array_shift($bb);

            $xy = $this->llToTile($lat, $lng);

            $x = current($xy);
            $y = end($xy);

            $json = $this->dwnCache("https://www.google.com/maps/vt?pb=!1m4!1m3!1i18!2i$x!3i$y!2m3!1e0!2sm!3i330397977!2m28!1e2!2sspotlight!4m2!1sgid!tG9yOZZcAaKLR2aIp2yfTQ!5i1!8m21!1m11!2m7!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2svannes!4m2!3d48.8430891!4d2.3158186!5e0!6b1!11e11!13m1!14b1!2m6!1s0x12c93c0de74ed1ff:0x56aa7b92d8665036!2stour+eiffel!4m2!3d43.2271796!4d6.143419799999999!5e0!13m1!14b1!3m14!2sfr!3sFR!5e289!12m4!1e52!2m2!1sentity_class!2s0!12m1!1e47!12m3!1e37!2m1!1ssmartmaps!4e3!12m1!5b1");

            $tab = json_decode($json, true);

            $row = $tab[1];

            $features = isAke($row, 'features', []);

            $ids = array_merge($ids, array_values(coll($features)->fetch('id')->toArray()));

            foreach ($ids as $id) {
                /*https://maps.google.fr/maps/api/js/ApplicationService.GetEntityDetails?pb=!1m2!1m1!1s0x808f7e0637de465b:0x405d498b744e53be!2m2!1sfr!2sUS!6e1*/
                $data = $this->dwnCache("https://maps.google.fr/maps/api/js/ApplicationService.GetEntityDetails?pb=!1m2!1m1!4s$id!2m2!1sfr!2sFR!6e1");

                $data = str_replace("\n", "", $data);
                $data = str_replace("\r", "", $data);
                $data = str_replace("\t", "", $data);
                $data = str_replace(")]}'", "", $data);

                $data = json_decode($data, true);

                $row = isset($data[1]) ? $data[1] : [];

                if (!empty($row)) {
                    $latRow     = $row[0][2][0];
                    $lngRow     = $row[0][2][1];
                    $distances  = distanceKmMiles($flng, $flat, $lngRow, $latRow);
                    $name       = $row[1];
                    $ws         = $row[11][0];
                    $tel        = $row[7];
                    $address    = $row[13];
                    $rate       = floatval($row[3]);
                    $avis       = (int) str_replace(' avis', '', $row[4]);
                    $type       = $row[12];
                    $hexa       = $row[0][0];
                    $label      = $row[0][1];
                    $schedule   = $row[9][1];
                    $link       = $row[5];

                    $obj = [
                        'distance'  => $distances['km'] * 1000,
                        'hexa'      => $hexa,
                        'cid'       => $id,
                        'type'      => $type,
                        'label'     => $label,
                        // 'abstract'  => $abstract,
                        'name'      => $name,
                        'lat'       => $latRow,
                        'lng'       => $lngRow,
                        'website'   => $ws,
                        'phone'     => $tel,
                        'address'   => $address,
                        'rate'      => $rate,
                        'avis'      => $avis,
                        'schedule'  => $schedule,
                        'img_in'    => 'http:' . $row[16][0][2][0],
                        'img_out'   => 'http:' . $row[16][1][2][0]
                    ];

                    if ($obj['img_in'] == 'http:') {
                        continue;
                    } else {
                        $obj['img_in'] .= '&w=600&h=400';
                    }

                    if ($obj['img_out'] == 'http:') {
                        $obj['img_out'] = null;
                    } else {
                        $obj['img_out'] .= '&w=600&h=400';
                    }

                    $res[] = $obj;
                }
            }

            $sort = array_values(coll($res)->sortBy('distance')->toArray());

            return $sort;
        }

        public function llToTile($lat, $lng, $zoom = 18)
        {
            $x = floor((($lng + 180) / 360) * pow(2, $zoom));
            $y = floor((1 - log(tan(deg2rad($lat)) + 1 / cos(deg2rad($lat))) / pi()) / 2 * pow(2, $zoom));

            return [$x, $y];
        }

        public function dwn($url)
        {
            $userAgent  = "Mozilla/5.0 (Linux; U; Android 4.2.1; fr-fr; LG-L160L Build/IML74K) AppleWebkit/534.30 (KHTML, like Gecko) Version/4.0 Mobile Safari/534.30";

            $ip         = rand(200, 225) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);
            $ch         = curl_init();

            $headers    = array();

            curl_setopt($ch, CURLOPT_URL,       $url);
            curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept-Language: fr,fr-fr;q=0.8,en-us;q=0.5,en;q=0.3']);

            $headers[] = "REMOTE_ADDR: $ip";
            $headers[] = "HTTP_X_FORWARDED_FOR: $ip";

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            $result     = curl_exec($ch);
            curl_close ($ch);

            return $result;
        }

        public function raw($address, $country = 250, $cache = true)
        {
            if (is_array($address)) {
                $address = current($address);
            }

            $urlLocalisation = "http://search.mappy.net/search/1.0/find?q=" . urlencode($address) . "&favorite_country=$country&language=FRE&loc_format=geojson";

            if ($cache) {
                $key    = 'coords.' . Inflector::urlize($address, '_');
                $json   = Save::get($key);

                if (!$json) {
                    $json = dwn($urlLocalisation);
                    Save::set($key, $json);
                }
            } else {
                $json = dwn($urlLocalisation);
            }

            return json_decode($json, true);
        }

        public function getCoords($address, $country = 250, $cache = true)
        {
            $infos = $address_components = [];

            $lng = $lat = $lng1 = $lng2 = $lat1 = $lat2 = null;

            $urlLocalisation = "http://search.mappy.net/search/1.0/find?q=" . urlencode($address) . "&favorite_country=$country&language=FRE&loc_format=geojson";

            if ($cache) {
                $key    = 'coords.' . Inflector::urlize($address, '_');
                $json   = Save::get($key);

                if (!$json) {
                    $json = dwn($urlLocalisation);
                    Save::set($key, $json);
                }
            } else {
                $json = dwn($urlLocalisation);
            }

            $tab = json_decode($json, true);

            if (isset($tab['addresses'])) {
                if (isset($tab['addresses']['features'])) {
                    if (count($tab['addresses']['features'])) {
                        $infos = current($tab['addresses']['features']);

                        if (isset($infos['properties'])) {

                            if (isset($infos['properties']['address_components'])) {
                                $address_components = $infos['properties']['address_components'];

                                if (isset($address_components['postcode'])) {
                                    $address_components['zip'] = $address_components['postcode'];
                                }

                                if (isset($address_components['town'])) {
                                    $address_components['city'] = $address_components['town'];
                                }

                                if (isset($address_components['admin_1'])) {
                                    $address_components['region'] = $address_components['admin_1'];
                                }

                                if (isset($address_components['region'])) {
                                    if (isset($address_components['region']['label'])) {
                                        $address_components['region_name'] = $address_components['region']['label'];
                                    }

                                    if (isset($address_components['region']['code'])) {
                                        $address_components['region_id'] = $address_components['region']['code'];
                                    }
                                }

                                unset($address_components['region']);

                                if (isset($address_components['way'])) {
                                    $address_components['address'] = $address_components['way'];
                                }

                                if (isset($address_components['way_number'])) {
                                    $address_components['address_number'] = $address_components['way_number'];
                                }

                                if (isset($address_components['country'])) {
                                    if (isset($address_components['country']['code'])) {
                                        $address_components['country_id'] = $address_components['country']['code'];
                                    }

                                    if (isset($address_components['country']['label'])) {
                                        $address_components['country_name'] = $address_components['country']['label'];
                                    }
                                }

                                unset($address_components['country']);

                                if (isset($address_components['city']) && isset($address_components['zip'])) {
                                    if (!is_array($address_components['zip'])) {
                                        $dpt = (int) substr($address_components['zip'], 0, 2);

                                        $cityModel = Model::City()
                                        ->where(['name', '=i', (string) $address_components['city']['label']])
                                        ->where(['zip', '=', (string) $address_components['zip']])
                                        ->first(true);

                                        if ($cityModel) {
                                            $address_components['city_name'] = $address_components['city']['label'];
                                            $address_components['city_id'] = $cityModel->id;
                                            unset($address_components['city']);

                                            $dptModel = $cityModel->department(true);

                                            if ($dptModel) {
                                                $address_components['department_code']  = $dpt;
                                                $address_components['department_id']    = $dptModel->id;
                                                $address_components['department_name']  = $dptModel->name;
                                            } else {
                                                $address_components['department'] = $dpt;
                                            }
                                        } else {
                                            $dptModel = Model::Department()->where(['code', '=', (string) $dpt])->first(true);

                                            if ($dptModel) {
                                                $address_components['department_code']  = $dpt;
                                                $address_components['department_id']    = $dptModel->id;
                                                $address_components['department_name']  = $dptModel->name;
                                            } else {
                                                $address_components['department'] = $dpt;
                                            }
                                        }
                                    }
                                }

                                unset($address_components['postcode']);
                                unset($address_components['admin_1']);
                                unset($address_components['town']);
                                unset($address_components['way']);
                                unset($address_components['way_number']);
                            }

                            if (isset($infos['properties']['formatted_address'])) {
                                if (isset($infos['properties']['formatted_address']['label'])) {
                                    $address_components['address_label'] = $infos['properties']['formatted_address']['label'];
                                }
                            }

                            if (isset($infos['properties']['viewport'])) {
                                $viewport = $infos['properties']['viewport'];
                                list($lng1, $lat1, $lng2, $lat2) = $viewport;

                                $lng1 = (float) $lng1;
                                $lat1 = (float) $lat1;
                                $lng2 = (float) $lng2;
                                $lat2 = (float) $lat2;
                            }
                        }

                        if (isset($infos['geometry'])) {
                            if (isset($infos['geometry']['geometries'])) {
                                if (count($infos['geometry']['geometries'])) {
                                    $coords = current($infos['geometry']['geometries']);
                                    $coords = isAke($coords, 'coordinates', []);
                                    list($lng, $lat) = $coords;

                                    $lng = (float) $lng;
                                    $lat = (float) $lat;
                                }
                            }
                        }
                    }
                }
            }

            $lat = str_replace(',', '.', $lat);
            $lng = str_replace(',', '.', $lng);
            $lat1 = str_replace(',', '.', $lat1);
            $lng1 = str_replace(',', '.', $lng1);
            $lng2 = str_replace(',', '.', $lng2);
            $lat1 = str_replace(',', '.', $lat1);
            $lat2 = str_replace(',', '.', $lat2);

            $return = array_merge(
                ['lat' => $lat, 'lng' => $lng, 'lat1' => $lat1, 'lng1' => $lng1, 'lat2' => $lat2, 'lng2' => $lng2],
                $address_components
            );

            ksort($return);

            return $return;
        }

        public function getAddressByLatLng($lat, $lng, $city = false)
        {
            $url = "http://www.google.com/maps?daddr=$lat,$lng";

            $html = isKh('getAddressByLatLng.' . sha1(serialize(func_get_args())), function () use ($url) {
                return fgc($url);
            });

            $tab = Utils::cut('cacheResponse(', ');', $html);
            eval('$tab = ' . $tab . ';');

            if (isset($tab[10])) {
                if (isset($tab[10][2])) {
                    if (isset($tab[10][2][1])) {
                        if (isset($tab[10][2][1][0])) {
                            if (isset($tab[10][2][1][0][1])) {
                                if (isset($tab[10][2][1][0][1][0])) {
                                    if (isset($tab[10][2][1][0][1][0][1])) {
                                        return $tab[10][2][1][0][1][0][0] . ', ' . $tab[10][2][1][0][1][0][1];
                                    }
                                }
                            }
                        }
                    }
                }
            }

            return $this->getAddressByCoords($lat, $lng, $city);
        }

        public function getAddressByCoords($lat, $lng, $city = false)
        {
            $infos = [];

            $lat = str_replace(',', '.', $lat);
            $lng = str_replace(',', '.', $lng);

            $cache = \Dbredis\Caching::instance('geo');
            $urlLocalisation = "http://uws2.mappy.net/data/map/1.0/loc/get.aspx?opt.format=json&opt.interactive=1&opt.language=fre&opt.xmlOutput=3v0&opt.favoriteCountry=250&x=$lng&y=$lat";

            $key = 'lat.long.address.' . $lat . '.' . $lng;

            $json = redis()->get($key);

            if (!$json) {
                $json = dwn($urlLocalisation);
                redis()->set($key, $json);
            }

            $tab = json_decode($json, true);

            $data           = isAke(isAke($tab, 'kml', []), 'Document', []);
            $placemark      = isAke($data, 'Placemark', []);

            if (!empty($placemark)) {
                $AddressDetails = isAke($placemark, 'AddressDetails', []);
                $Country = isAke($AddressDetails, 'Country', []);
                $AdministrativeArea = isAke($Country, 'AdministrativeArea', []);
                $AdministrativeAreaName = isAke($AdministrativeArea, 'AdministrativeAreaName', '');
                $Locality = isAke($AdministrativeArea, 'Locality', []);
                $LocalityName = isAke($Locality, 'LocalityName', '');
                $Thoroughfare = isAke($Locality, 'Thoroughfare', []);
                $postalCode = isAke($Thoroughfare, 'PostalCode', []);
                $street = isAke($Thoroughfare, 'ThoroughfareName', '');
                $PostalCodeNumber = isAke($postalCode, 'PostalCodeNumber', '');

                $ExtendedData = isAke($placemark, 'ExtendedData', []);

                $address = isAke($placemark, 'name');

                if (!empty($ExtendedData)) {
                    $mappy_address_lines = isAke($ExtendedData, 'mappy:address_lines', []);

                    if (!empty($mappy_address_lines)) {
                        $mappy_line = isAke($mappy_address_lines, 'mappy:line', []);

                        if (!empty($mappy_line)) {
                            $address = current($mappy_line);
                        }
                    }
                }

                $department = '';

                if (strlen($PostalCodeNumber)) {
                    $department = substr($PostalCodeNumber, 0, 2);
                }

                return !$city ? isAke($placemark, 'name') : [
                    'address' => $address,
                    'street' => $street,
                    'city' => $LocalityName,
                    'zip' => $PostalCodeNumber,
                    'department' => $department,
                    'region' => $AdministrativeAreaName,
                    'country' => isAke($Country, 'CountryName', 'France')
                ];
            } else {
                return false;
            }
        }

        public function itineraire($from, $to, $type = 'auto')
        {
            /* type = auto, velo, tc, pieton */
            /*http://routemm.mappy.net/route_vehicle/1.5/roadbook?rb.veh=midcar&rt.cost=time&rt.notoll=0&rb.infotraffic=0&rb.gascost=1.298&rb.gas=petrol&rt.nbroutes=3&opt.compensation=0&routeidx=0&from=2.31572205205336,48.8429991844234&to=2.34113168776833,48.8681323158042&clientId=mappy&wt=json&mid=384081603&tagid=SPD_RESPONSE_ITINERARY*/
            /*http://routemm.mappy.net/route/1.5/roadbook?date=20151106&time=1749&sens=1&criteria=1&transport_mode=pub_tp&rb.veh=metro-rer-bus-boat-tram-train&from=2.31572205205336,48.8429991844234&to=2.34113168776833,48.8681323158042&clientId=mappy&wt=json&mid=384081603&tagid=SPD_RESPONSE_ITINERARY*/
            /*http://routemm.mappy.net/route/1.5/roadbook?date=20151106&time=1805&sens=1&criteria=1&transport_mode=pub_tp&rb.veh=bus-tram&from=2.31572205205336,48.8429991844234&to=2.27196429200037,48.5153235054347&clientId=mappy&wt=json&mid=384081603&tagid=SPD_RESPONSE_ITINERARY*/
            $key = 'iti.' . sha1(serialize(func_get_args()));

            $from = $this->getCoords($from);
            $to = $this->getCoords($to);

            $d = date('Y') . date('m') . date('d');
            $h = date('H') . date('i');

            $url = "http://routemm.mappy.net/route/1.5/roadbook?date=$d&time=$h&sens=1&criteria=1&transport_mode=pub_tp&rb.veh=bus-tram&from={$from['lng']},{$from['lat']}&to={$to['lng']},{$to['lat']}&clientId=mappy&wt=json&tagid=SPD_RESPONSE_ITINERARY";

            $json = fgc($url);

            $tab = json_decode($json, true);

            return $tab;
        }

        public function getAddrByCoords($lat, $lng)
        {
            /* "http://maps.googleapis.com/maps/api/directions/json?origin=$lat,$lng&destination=$lat,$lng&mode=driving&sensor=false" */
            dd(fgc("http://maps.googleapis.com/maps/api/directions/json?language=fr&origin=$lat,$lng&destination=$lat,$lng&mode=driving&sensor=false"));
            $json = $this->dwnCache("http://cbk0.google.com/cbk?output=json&ll=$lat,$lng&cb_client=earth");
            // $json = fgc("http://geo0.ggpht.com/cbk?output=json&ll=$lat,$lng&cb_client=earth");
            $tab = json_decode($json, true);

            $row = isAke($tab, 'Location', []);
            $Projection = isAke($tab, 'Projection', []);

            $links = isAke($tab, 'Links', []);

            $panoId         = isAke($row, 'panoId', null);
            $row['panos']   = [isAke($row, 'panoId', null)];

            $row['img'] = "https://geo3.ggpht.com/cbk?panoid=$panoId&output=thumbnail&cb_client=search.LOCAL_UNIVERSAL.gps&thumb=2&w=600&h=480&yaw=".$Projection['pano_yaw_deg']."&pitch=" . $row['best_view_direction_deg'];

            $row['panos'] = array_merge($row['panos'], array_values(coll($links)->fetch('panoId')->toArray()));

            unset($row['panoId']);

            return $row;
        }

        public function itiCoords($from, $to, $type = 'auto')
        {
            /* type = auto, velo, tc, pieton */
            /*http://routemm.mappy.net/route_vehicle/1.5/roadbook?rb.veh=midcar&rt.cost=time&rt.notoll=0&rb.infotraffic=0&rb.gascost=1.298&rb.gas=petrol&rt.nbroutes=3&opt.compensation=0&routeidx=0&from=2.31572205205336,48.8429991844234&to=2.34113168776833,48.8681323158042&clientId=mappy&wt=json&mid=384081603&tagid=SPD_RESPONSE_ITINERARY*/
            /*http://routemm.mappy.net/route/1.5/roadbook?date=20151106&time=1749&sens=1&criteria=1&transport_mode=pub_tp&rb.veh=metro-rer-bus-boat-tram-train&from=2.31572205205336,48.8429991844234&to=2.34113168776833,48.8681323158042&clientId=mappy&wt=json&mid=384081603&tagid=SPD_RESPONSE_ITINERARY*/
            /*http://routemm.mappy.net/route/1.5/roadbook?date=20151106&time=1805&sens=1&criteria=1&transport_mode=pub_tp&rb.veh=bus-tram&from=2.31572205205336,48.8429991844234&to=2.27196429200037,48.5153235054347&clientId=mappy&wt=json&mid=384081603&tagid=SPD_RESPONSE_ITINERARY*/
            $key = 'iti.' . sha1(serialize(func_get_args()));

            $d = date('Y') . date('m') . date('d');
            $h = date('H') . date('i');

            $url = "http://routemm.mappy.net/route/1.5/roadbook?date=$d&time=$h&sens=1&criteria=1&transport_mode=pub_tp&rb.veh=bus-tram&from={$from['lng']},{$from['lat']}&to={$to['lng']},{$to['lat']}&clientId=mappy&wt=json&tagid=SPD_RESPONSE_ITINERARY";

            $json = fgc($url);

            $tab = json_decode($json, true);

            return $tab;
        }

        public function getCoveredCitiesBySellzone($sellzone_id)
        {
            if (!is_integer($sellzone_id)) {
                throw new Exception("sellzone_id must be an integer id.");
            }

            $collection = [];

            $rows = Model::Coveredcity()->where(['sellzone_id', '=', $sellzone_id])->get();

            foreach ($rows as $row) {
                $collection[] = ['name' => $row['name'], 'zip' => $row['zip']];
            }

            return $collecction;
        }

        public function getBoundingBox($lat, $lng, $distance = 2, $km = true)
        {
            $lat = floatval($lat);
            $lng = floatval($lng);

            // $geotools       = new Geos();
            // $coordToGeohash = new \League\Geotools\Coordinate\Coordinate("$lat, $lng");
            // $encoded = $geotools->geohash()->encode($coordToGeohash);
            // $boundingBox = $encoded->getBoundingBox();
            // $southWest   = $boundingBox[0];
            // $northEast   = $boundingBox[1];

            // return [
            //     $southWest->getLatitude(), $southWest->getLongitude(),
            //     $northEast->getLatitude(), $northEast->getLongitude()
            // ];

            $radius = $km ? 6372.797 : 3963.1; // of earth in km or miles

            // bearings - FIX
            $due_north  = deg2rad(0);
            $due_south  = deg2rad(180);
            $due_east   = deg2rad(90);
            $due_west   = deg2rad(270);

            // convert latitude and longitude into radians
            $lat_r = deg2rad($lat);
            $lon_r = deg2rad($lng);

            $northmost  = asin(sin($lat_r) * cos($distance / $radius) + cos($lat_r) * sin ($distance / $radius) * cos($due_north));
            $southmost  = asin(sin($lat_r) * cos($distance / $radius) + cos($lat_r) * sin ($distance / $radius) * cos($due_south));

            $eastmost = $lon_r + atan2(sin($due_east) * sin($distance / $radius) * cos($lat_r), cos($distance / $radius) - sin($lat_r) * sin($lat_r));
            $westmost = $lon_r + atan2(sin($due_west) * sin($distance / $radius) * cos($lat_r), cos($distance / $radius) - sin($lat_r) * sin($lat_r));

            $northmost  = rad2deg($northmost);
            $southmost  = rad2deg($southmost);
            $eastmost   = rad2deg($eastmost);
            $westmost   = rad2deg($westmost);

            // sort the lat and long so that we can use them for a between query
            if ($northmost > $southmost) {
                $lat1 = $southmost;
                $lat2 = $northmost;
            } else {
                $lat1 = $northmost;
                $lat2 = $southmost;
            }

            if ($eastmost > $westmost) {
                $lon1 = $westmost;
                $lon2 = $eastmost;
            } else {
                $lon1 = $eastmost;
                $lon2 = $westmost;
            }

            return [$lat1, $lon1, $lat2, $lon2];
        }

        public function addZone($address)
        {
            $urlLocalisation = "http://search.mappy.net/search/1.0/find?q=" . urlencode($address) . "&favorite_country=250&language=FRE&loc_format=geojson";

            $keyJsonLocal = 'pois.' . sha1($address);

            $json = redis()->get($keyJsonLocal);

            if (!$json) {
                $json = dwn($urlLocalisation);
                redis()->set($keyJsonLocal, $json);
            }

            $tab = json_decode($json, true);

            if (isset($tab['addresses'])) {
                if (isset($tab['addresses']['features'])) {
                    if (!empty($tab['addresses']['features'])) {
                        $coords = current($tab['addresses']['features']);
                        $bbox = isAke($coords, 'bbox', false);

                        if (false !== $bbox && count($bbox) == 4) {
                            $lng1 = str_replace(',', '.', $bbox[0]);
                            $lat1 = str_replace(',', '.', $bbox[1]);
                            $lng2 = str_replace(',', '.', $bbox[2]);
                            $lat2 = str_replace(',', '.', $bbox[3]);

                            return $zone = rdb('geo', 'zone')->firstOrCreate([
                                'address' => Inflector::lower($address),
                                'lat1' => $lat1,
                                'lng1' => $lng1,
                                'lat2' => $lat2,
                                'lng2' => $lng2
                            ]);
                        }
                    }
                }
            }
        }

        public function isCoveredCity($zip)
        {
            $zip = (string) $zip;

            $exists = Model::Coveredcity()->where(['zip', '=', $zip])->first(true);

            return $exists ? $exists->id : false;
        }

        public function getCoordsInternal($address)
        {
            $key    = 'internalCoords.' . sha1($address);
            $cached = Save::get($key);
            $coords = ['lat' => 0, 'lng' => 0];

            if (!$cached) {
                $json   = dwn("http://maps.googleapis.com/maps/api/directions/json?origin=" . urlencode($address) . "&destination=place%20darcy%20dijon");
                Save::set($key, $json);
            } else {
                $json = $cached;
            }

            $tab = json_decode($json, true);

            $seg = isAke($tab, 'routes', []);

            if (!empty($seg)) {
                $seg = current($seg);
                $legs = isAke($seg, 'legs', []);

                if (!empty($legs)) {
                    $legs = current($legs);

                    $start_location = isAke($legs, 'start_location', []);

                    $coords = ['lat' => floatval(isAke($start_location, 'lat', 0)), 'lng' => isAke($start_location, 'lng', 0)];
                }
            }

            return $coords;
        }

        public function makePlace($data)
        {
            $db = Model::GeoPlace();

            $odm = $db->getOdm();

            $collection = $odm->selectCollection($db->collection);
            $collection->ensureIndex(['coordinates' => '2d']);

            $lat = isAke($data, 'lat', 0);
            $lng = isAke($data, 'lng', 0);

            $data['coordinates'] = ['lng' => (float) $lng, 'lat' => (float) $lat];

            return $db->create($data)->save();
        }

        public function checkSyntax($code)
        {
            if(!defined("CR"))  define("CR","\r");
            if(!defined("LF")) define("LF","\n");
            if(!defined("CRLF")) define("CRLF","\r\n");

            $braces = 0;
            $inString = 0;

            foreach (token_get_all('<?php ' . $code) as $token) {
                if (is_array($token)) {
                    switch ($token[0]) {
                        case T_CURLY_OPEN:
                        case T_DOLLAR_OPEN_CURLY_BRACES:
                        case T_START_HEREDOC: ++$inString; break;
                        case T_END_HEREDOC:   --$inString; break;
                    }
                } else if ($inString & 1) {
                    switch ($token) {
                        case '`': case '\'':
                        case '"': --$inString; break;
                    }
                } else {
                    switch ($token) {
                        case '`': case '\'':
                        case '"': ++$inString; break;
                        case '{': ++$braces; break;
                        case '}':
                            if ($inString) {
                                --$inString;
                            } else {
                                --$braces;
                                if ($braces < 0) break 2;
                            }
                            break;
                    }
                }
            }

            $inString = @ini_set('log_errors', false);
            $token = @ini_set('display_errors', true);

            ob_start();

            $braces || $code = "if(0){{$code}\n}";

            if (eval($code) === false) {
                if ($braces) {
                    $braces = PHP_INT_MAX;
                } else {
                    false !== strpos($code,CR) && $code = strtr(str_replace(CRLF,LF,$code),CR,LF);
                    $braces = substr_count($code,LF);
                }

                $code = ob_get_clean();
                $code = strip_tags($code);

                if (preg_match("'syntax error, (.+) in .+ on line (\d+)$'s", $code, $code)) {
                    $code[2] = (int) $code[2];
                    $code = $code[2] <= $braces
                        ? array($code[1], $code[2])
                        : array('unexpected $end' . substr($code[1], 14), $braces);
                } else $code = array('syntax error', 0);
            } else {
                ob_end_clean();
                $code = false;
            }

            @ini_set('display_errors', $token);
            @ini_set('log_errors', $inString);

            return $code;
        }

        public function getCoordsMap($address, $poiCheck = true)
        {
            $lat = $lng = 0;

            $id_hex = $zip = $city = $number = $street = $addr = null;

            $key = 'g.maps.' . sha1($address);

            $url    = 'https://www.google.fr/search?tbm=map&fp=1&authuser=0&hl=fr&q=' . urlencode($address);

            $json   = $this->dwnCache($url);

            $json = str_replace(["\t", "\n", "\r"], '', $json);

            list($dummy, $segTab) = explode(")]}'", $json, 2);

            $code = '$tab = ' . $segTab . ';';

            $place_id = 'Ch' . Utils::cut('null,"Ch', '"', $json);

            if (strstr($json, 'ludocid') && $poiCheck) {
                $id_poi = (string) Utils::cut('ludocid%3D', '%', $json);

                if (!strlen($id_poi)) {
                    $id_poi = (string) Utils::cut('ludocid\u003d', '#', $json);
                }

                $d = $this->dwnCache("https://www.google.com/maps/api/js/ApplicationService.GetEntityDetails?pb=!1m2!1m1!4s$id_poi!2m2!1sfr!2sUS!6e1!13m1!4b1");

                $d = str_replace(["\t", "\n", "\r"], '', $d);

                list($dummy, $segTab) = explode(")]}'", $d, 2);

                eval('$dtab = ' . $segTab . ';');

                if (isset($dtab[1])) {
                    $i = $dtab[1];

                    $id_hex = $i[0][0];
                    $lat = $i[0][2][0];
                    $lng = $i[0][2][1];
                    $addr = $i[13];
                    $type = $i[12];

                    if (fnmatch('*, *, *', $addr)) {
                        list($streetAddress, $zipCity, $country) = explode(', ', $addr, 3);
                    } elseif (fnmatch('*, *', $addr)) {
                        $zipCity = null;
                        list($streetAddress, $country) = explode(', ', $addr, 2);
                    } else {
                        return ['lat' => 0, 'lng' => 0];
                    }

                    if (fnmatch('* * *', $streetAddress)) {
                        list($number, $street) = explode(' ', $streetAddress, 2);

                        if (!is_numeric($number)) {
                            $street = $streetAddress;
                            $number = null;
                        }
                    }

                    if (fnmatch('* *', $zipCity)) {
                        list($zip, $city) = explode(' ', $zipCity, 2);

                        if (!is_numeric($zip)) {
                            $city = $streetAddress;
                            $zip = null;
                        }
                    }

                    $tel    = isset($i[7]) ? $i[7] : null;
                    $name   = isset($i[1]) ? $i[1] : null;

                    $ws = null;

                    if (isset($i[11])) {
                        if (isset($i[11][0])) {
                            $ws = $i[11][0];
                        }
                    }

                    if (fnmatch("*q=*", $ws) && fnmatch("*u0026*", $ws)) {
                        $ws = Utils::cut('q=', '\u0026', $ws);
                    }

                    $box = $this->getBoundingBox($lat, $lng);

                    $this->makePlace([
                        'lat'                   => (double) $lat,
                        'lng'                   => (double) $lng,
                        'box'                   => $box,
                        'name'                  => (string) $name,
                        'type'                  => (string) $type,
                        'normalized_address'    => (string) $addr,
                        'country'               => (string) $country,
                        'street'                => (string) $street,
                        'number'                => (string) $number,
                        'city'                  => (string) $city,
                        'id_place'              => $place_id,
                        'id_poi'                => $id_poi,
                        'id_hex'                => $id_hex,
                        'tel'                   => $tel,
                        'site'                  => $ws,
                        'zip'                   => (string) $zip
                    ]);

                    $this->relatedNeighborhoods($box[0], $box[1], $box[2], $box[3]);

                    return [
                        'lat'                   => (double) $lat,
                        'lng'                   => (double) $lng,
                        'box'                   => $box,
                        'name'                  => (string) $name,
                        'type'                  => (string) $type,
                        'normalized_address'    => (string) $addr,
                        'country'               => (string) $country,
                        'street'                => (string) $street,
                        'number'                => (string) $number,
                        'city'                  => (string) $city,
                        'id_place'              => $place_id,
                        'id_poi'                => $id_poi,
                        'id_hex'                => $id_hex,
                        'tel'                   => $tel,
                        'site'                  => $ws,
                        'zip'                   => (string) $zip
                    ];
                }
            }

            if (strstr($json, 'https://www.google.com/local/add/choice?latlng\u003d') && $poiCheck) {
                $id_poi = (string) Utils::cut('https://www.google.com/local/add/choice?latlng\u003d', '\u0026', $json);

                $d = dwn("https://www.google.com/maps/api/js/ApplicationService.GetEntityDetails?pb=!1m2!1m1!4s$id_poi!2m2!1sfr!2sUS!6e1!13m1!4b1");

                $d = $this->dwnCache("https://www.google.com/maps/api/js/ApplicationService.GetEntityDetails?pb=!1m2!1m1!4s$id_poi!2m2!1sfr!2sUS!6e1!13m1!4b1");

                $d = str_replace(["\t", "\n", "\r"], '', $d);

                list($dummy, $segTab) = explode(")]}'", $d, 2);

                try {
                    eval('$dtab = ' . $segTab . ';');
                } catch (\Exception $e) {

                }

                $i = $dtab[1];

                $lat = $i[0][2][0];
                $lng = $i[0][2][1];
                $addr = $i[13];
                $type = $i[12];

                $streetAddress = '';

                if (fnmatch('*, *, *', $addr)) {
                    list($streetAddress, $zipCity, $country) = explode(', ', $addr, 3);
                } else {
                    if (fnmatch('*, *', $addr)) {
                        list($zipCity, $country) = explode(', ', $addr, 3);
                    }
                }

                if (fnmatch('* * *', $streetAddress)) {
                    list($number, $street) = explode(' ', $streetAddress, 2);

                    if (!is_numeric($number)) {
                        $street = $streetAddress;
                        $number = null;
                    }
                }

                if (fnmatch('* *', $zipCity)) {
                    list($zip, $city) = explode(' ', $zipCity, 2);

                    if (!is_numeric($zip)) {
                        $city = $streetAddress;
                        $zip = null;
                    }
                }

                $tel    = isset($i[7]) ? $i[7] : null;
                $name   = isset($i[1]) ? $i[1] : null;

                $ws = null;

                if (isset($i[11])) {
                    if (isset($i[11][0])) {
                        $ws = $i[11][0];
                    }
                }

                if (fnmatch("*q=*", $ws) && fnmatch("*u0026*", $ws)) {
                    $ws = Utils::cut('q=', '\u0026', $ws);
                }

                $box = $this->getBoundingBox($lat, $lng);

                $this->makePlace([
                    'lat'                   => (double) $lat,
                    'lng'                   => (double) $lng,
                    'box'                   => $box,
                    'name'                  => (string) $name,
                    'type'                  => (string) $type,
                    'normalized_address'    => (string) $addr,
                    'country'               => (string) $country,
                    'street'                => (string) $street,
                    'number'                => (string) $number,
                    'city'                  => (string) $city,
                    'id_place'              => $place_id,
                    'id_poi'                => $id_poi,
                    'id_hex'                => $i[0][0],
                    'tel'                   => $tel,
                    'site'                  => $ws,
                    'zip'                   => (string) $zip
                ]);

                $this->relatedNeighborhoods($box[0], $box[1], $box[2], $box[3]);

                return [
                    'lat'                   => (double) $lat,
                    'lng'                   => (double) $lng,
                    'box'                   => $box,
                    'name'                  => (string) $name,
                    'type'                  => (string) $type,
                    'normalized_address'    => (string) $addr,
                    'country'               => (string) $country,
                    'street'                => (string) $street,
                    'number'                => (string) $number,
                    'city'                  => (string) $city,
                    'id_place'              => $place_id,
                    'id_poi'                => $id_poi,
                    'id_hex'                => $i[0][0],
                    'tel'                   => $tel,
                    'site'                  => $ws,
                    'zip'                   => (string) $zip
                ];
            }

            $seg = Utils::cut('/@', '/data', $json);

            $tab = explode(',', $seg);

            $lat = floatval($tab[0]);
            $lng = floatval($tab[1]);

            $addr = urldecode(Utils::cut('preview/place/', '/', $json));

            $id_hex = '0x' . Utils::cut('],"0x', '"', $code);

            if (fnmatch('*, *', $addr)) {
                list($streetAddress, $cpCity) = explode(', ', $addr, 2);

                if (fnmatch('* *', $cpCity)) {
                    list($zip, $city) = explode(' ', $cpCity, 2);

                    if (!is_numeric($zip)) {
                        $city = $streetAddress;
                        $zip = null;
                    }
                }

                if (fnmatch('* * *', $streetAddress)) {
                    list($number, $street) = explode(' ', $streetAddress, 2);

                    if (!is_numeric($number)) {
                        $street = $streetAddress;
                        $number = null;
                    }
                }
            }

            $box = $this->getBoundingBox($lat, $lng);

            if ($poiCheck) {
                $this->relatedNeighborhoods($box[0], $box[1], $box[2], $box[3]);
            }

            return [
                'lat'                   => (double) $lat,
                'lng'                   => (double) $lng,
                'id_place'              => $place_id,
                'id_hex'                => $id_hex,
                'box'                   => $box,
                'normalized_address'    => (string) $addr,
                'street'                => (string) $street,
                'number'                => (string) $number,
                'city'                  => (string) $city,
                'zip'                   => (string) $zip
            ];
        }

        public function subway($lat, $lng)
        {
            $url = "https://api.foursquare.com/v2/search/recommendations?locale=fr&explicit-lang=true&v=20151207&m=foursquare&query=Station+de+m%C3%A9tro&limit=100&mode=typed&ll=$lat%2C$lng&acrid=56670ae3498e4c7c0640d1bd&wsid=D30NQNE1P2RWISXN4QIP4Y3T5VOOGU&oauth_token=QEJ4AQPTMMNB413HGNZ5YDMJSHTOHZHMLZCAQCCLXIX41OMP";
            $json = $this->dwnCache($url);

            $tab = json_decode($json, true);

            $response   = isAke($tab, 'response', []);
            $group      = isAke($response, 'group', []);
            $results    = isAke($group, 'results', []);

            $collection = [];

            foreach ($results as $result) {
                $venue      = isAke($result, 'venue', []);
                $categories = isAke($venue, 'categories', []);

                if (!empty($categories)) {
                    $category = current($categories);

                    $sn = isAke($category, 'shortName', null);

                    if ($sn == 'Métro') {
                        $loc = isAke($venue, 'location', []);

                        $obj = [
                            'id_venue'  => isAke($venue, 'id', null),
                            'name'      => isAke($venue, 'name', null),
                            'f_address' => implode(', ', isAke($loc, 'formattedAddress', [])),
                            'address'   => isAke($loc, 'address', null),
                            'city'      => isAke($loc, 'city', null),
                            'zip'       => isAke($loc, 'postalCode', null),
                            'district'  => isAke($loc, 'neighborhood', null),
                            'region'    => isAke($loc, 'state', null),
                            'country'   => isAke($loc, 'country', null),
                            'distance'  => isAke($loc, 'distance', 0),
                            'lat'       => floatval(isAke($loc, 'lat', 0)),
                            'lng'       => floatval(isAke($loc, 'lng', 0))
                        ];

                        $collection[] = $obj;
                    }
                }
            }

            return $collection;
        }

        public function bus($lat, $lng)
        {
            $url = "https://api.foursquare.com/v2/search/recommendations?locale=fr&explicit-lang=true&v=20151207&m=foursquare&query=Arr%C3%AAt+de+bus&limit=100&mode=typed&ll=$lat%2C$lng&acrid=56670ae3498e4c7c0640d1bd&wsid=D30NQNE1P2RWISXN4QIP4Y3T5VOOGU&oauth_token=QEJ4AQPTMMNB413HGNZ5YDMJSHTOHZHMLZCAQCCLXIX41OMP";
            $json = $this->dwnCache($url);

            $tab = json_decode($json, true);

            $response   = isAke($tab, 'response', []);
            $group      = isAke($response, 'group', []);
            $results    = isAke($group, 'results', []);

            $collection = [];

            foreach ($results as $result) {
                $venue      = isAke($result, 'venue', []);
                $categories = isAke($venue, 'categories', []);

                if (!empty($categories)) {
                    $category = current($categories);

                    $sn = isAke($category, 'shortName', null);

                    if ($sn == 'Arrêt de bus') {
                        $loc = isAke($venue, 'location', []);

                        $obj = [
                            'id_venue'  => isAke($venue, 'id', null),
                            'name'      => isAke($venue, 'name', null),
                            'f_address' => implode(', ', isAke($loc, 'formattedAddress', [])),
                            'address'   => isAke($loc, 'address', null),
                            'city'      => isAke($loc, 'city', null),
                            'zip'       => isAke($loc, 'postalCode', null),
                            'district'  => isAke($loc, 'neighborhood', null),
                            'region'    => isAke($loc, 'state', null),
                            'country'   => isAke($loc, 'country', null),
                            'distance'  => isAke($loc, 'distance', 0),
                            'lat'       => floatval(isAke($loc, 'lat', 0)),
                            'lng'       => floatval(isAke($loc, 'lng', 0))
                        ];

                        $collection[] = $obj;
                    }
                }
            }

            return $collection;
        }

        public function cycle($lat, $lng)
        {
            $url = "https://api.foursquare.com/v2/search/recommendations?locale=fr&explicit-lang=true&v=20151207&m=foursquare&query=V%C3%A9los+en+libre+service&limit=100&mode=typed&ll=$lat%2C$lng&acrid=56670ae3498e4c7c0640d1bd&wsid=D30NQNE1P2RWISXN4QIP4Y3T5VOOGU&oauth_token=QEJ4AQPTMMNB413HGNZ5YDMJSHTOHZHMLZCAQCCLXIX41OMP";
            $json = $this->dwnCache($url);

            $tab = json_decode($json, true);

            $response   = isAke($tab, 'response', []);
            $group      = isAke($response, 'group', []);
            $results    = isAke($group, 'results', []);

            $collection = [];

            foreach ($results as $result) {
                $venue      = isAke($result, 'venue', []);
                $categories = isAke($venue, 'categories', []);

                if (!empty($categories)) {
                    $category = current($categories);

                    $sn = isAke($category, 'shortName', null);

                    if ($sn == 'Vélo') {
                        $loc = isAke($venue, 'location', []);

                        $obj = [
                            'id_venue'  => isAke($venue, 'id', null),
                            'name'      => isAke($venue, 'name', null),
                            'f_address' => implode(', ', isAke($loc, 'formattedAddress', [])),
                            'address'   => isAke($loc, 'address', null),
                            'city'      => isAke($loc, 'city', null),
                            'zip'       => isAke($loc, 'postalCode', null),
                            'district'  => isAke($loc, 'neighborhood', null),
                            'region'    => isAke($loc, 'state', null),
                            'country'   => isAke($loc, 'country', null),
                            'distance'  => isAke($loc, 'distance', 0),
                            'lat'       => floatval(isAke($loc, 'lat', 0)),
                            'lng'       => floatval(isAke($loc, 'lng', 0))
                        ];

                        $collection[] = $obj;
                    }
                }
            }

            return $collection;
        }

        public function gare($lat, $lng)
        {
            $url = "https://api.foursquare.com/v2/search/recommendations?locale=fr&explicit-lang=true&v=20151207&m=foursquare&query=Gare&limit=100&mode=typed&ll=$lat%2C$lng&acrid=56670ae3498e4c7c0640d1bd&wsid=D30NQNE1P2RWISXN4QIP4Y3T5VOOGU&oauth_token=QEJ4AQPTMMNB413HGNZ5YDMJSHTOHZHMLZCAQCCLXIX41OMP";
            $json = $this->dwnCache($url);

            $tab = json_decode($json, true);

            $response   = isAke($tab, 'response', []);
            $group      = isAke($response, 'group', []);
            $results    = isAke($group, 'results', []);

            $collection = [];

            foreach ($results as $result) {
                $venue      = isAke($result, 'venue', []);
                $categories = isAke($venue, 'categories', []);

                if (!empty($categories)) {
                    $category = current($categories);

                    $sn = isAke($category, 'shortName', null);

                    if ($sn == 'Gare') {
                        $loc = isAke($venue, 'location', []);

                        $obj = [
                            'id_venue'  => isAke($venue, 'id', null),
                            'name'      => isAke($venue, 'name', null),
                            'f_address' => implode(', ', isAke($loc, 'formattedAddress', [])),
                            'address'   => isAke($loc, 'address', null),
                            'city'      => isAke($loc, 'city', null),
                            'zip'       => isAke($loc, 'postalCode', null),
                            'district'  => isAke($loc, 'neighborhood', null),
                            'region'    => isAke($loc, 'state', null),
                            'country'   => isAke($loc, 'country', null),
                            'distance'  => isAke($loc, 'distance', 0),
                            'lat'       => floatval(isAke($loc, 'lat', 0)),
                            'lng'       => floatval(isAke($loc, 'lng', 0))
                        ];

                        $collection[] = $obj;
                    }
                }
            }

            return $collection;
        }

        public function getMap($lat, $lng, $zoom = 14, $size = '1024x768')
        {
            $lat = str_replace(',', '.', $lat);
            $lng = str_replace(',', '.', $lng);

            header('Content-type: image/png;');
            // header('Content-Disposition: attachment; filename=coords.png');

            $png = dwn("https://maps.google.com/maps/api/staticmap?sensor=false&center=$lat%2C$lng&zoom=$zoom&size=$size&markers=color:blue%7Clabel:A%7C$lat,$lng");

            die($png);
        }

        public function getCityFromTheFirstLetter($letter = 'A')
        {
            $letter = strtoupper($letter);

            $json = redis()->get('getCityFromTheFirstLetter.' . $letter);

            if (!$json) {
                $json = dwn('http://shopping.mappy.com/import/city/rand/' . $letter . '.json');
                redis()->set('getCityFromTheFirstLetter.' . $letter, $json);
            }

            return json_decode($json, true);
        }

        public function searchStreetBySellzone($sellzone_id, $q, $max = 299)
        {
            $collection = [];

            $sz = Model::Sellzone()->refresh()->find((int) $sellzone_id);

            if ($sz) {
                $url = 'http://search.mappy.net/search/1.0/find?extend_bbox=0&bbox=' . $sz->bbox . '&q=' . urlencode($q . ' ' . $sz->department) . '&favorite_country=250&language=FRE&loc_format=geojson&max_results=' . $max;

                $key = 'searchStreetBySellzones.' . $sellzone_id . '.' . sha1($q);

                $json = redis()->get($key);

                if (!$json) {
                    $json = dwn($url);
                    redis()->set($key, $json);
                }

                $tab = json_decode($json, true);

                if (isset($tab['addresses'])) {
                    if (isset($tab['addresses']['features'])) {
                        if (count($tab['addresses']['features'])) {
                            foreach ($tab['addresses']['features'] as $infos) {
                                if (isset($infos['properties'])) {
                                    if (isset($infos['properties']['address_components'])) {
                                        $address_components = $infos['properties']['address_components'];

                                        if (isset($address_components['postcode'])) {
                                            $address_components['zip'] = $address_components['postcode'];
                                        }

                                        if (isset($address_components['town'])) {
                                            $address_components['city'] = $address_components['town'];
                                        }

                                        if (isset($address_components['admin_1'])) {
                                            $address_components['region'] = $address_components['admin_1'];
                                        }

                                        if (isset($address_components['region'])) {
                                            if (isset($address_components['region']['label'])) {
                                                $address_components['region_name'] = $address_components['region']['label'];
                                            }

                                            if (isset($address_components['region']['code'])) {
                                                $address_components['region_id'] = $address_components['region']['code'];
                                            }
                                        }

                                        unset($address_components['region']);

                                        if (isset($address_components['way'])) {
                                            $address_components['address'] = $address_components['way'];
                                        }

                                        if (isset($address_components['way_number'])) {
                                            $address_components['address_number'] = $address_components['way_number'];
                                        }

                                        if (isset($address_components['country'])) {
                                            if (isset($address_components['country']['code'])) {
                                                $address_components['country_id'] = $address_components['country']['code'];
                                            }

                                            if (isset($address_components['country']['label'])) {
                                                $address_components['country_name'] = $address_components['country']['label'];
                                            }
                                        }

                                        unset($address_components['country']);

                                        if (isset($address_components['city']) && isset($address_components['zip'])) {
                                            if (!is_array($address_components['zip'])) {
                                                $dpt = (int) substr($address_components['zip'], 0, 2);

                                                $cityModel = Model::City()
                                                ->where(['name', '=i', (string) $address_components['city']['label']])
                                                ->where(['zip', '=', (string) $address_components['zip']])
                                                ->first(true);

                                                if ($cityModel) {
                                                    $address_components['city_name'] = $address_components['city']['label'];
                                                    $address_components['city_id'] = $cityModel->id;
                                                    unset($address_components['city']);

                                                    $dptModel = $cityModel->department(true);

                                                    if ($dptModel) {
                                                        $address_components['department_code']  = $dpt;
                                                        $address_components['department_id']    = $dptModel->id;
                                                        $address_components['department_name']  = $dptModel->name;
                                                    } else {
                                                        $address_components['department'] = $dpt;
                                                    }
                                                } else {
                                                    $dptModel = Model::Department()->where(['code', '=', (string) $dpt])->first(true);

                                                    if ($dptModel) {
                                                        $address_components['department_code']  = $dpt;
                                                        $address_components['department_id']    = $dptModel->id;
                                                        $address_components['department_name']  = $dptModel->name;
                                                    } else {
                                                        $address_components['department'] = $dpt;
                                                    }
                                                }
                                            }
                                        }

                                        unset($address_components['postcode']);
                                        unset($address_components['admin_1']);
                                        unset($address_components['town']);
                                        unset($address_components['way']);
                                        unset($address_components['way_number']);
                                    }

                                    if (isset($infos['properties']['formatted_address'])) {
                                        if (isset($infos['properties']['formatted_address']['label'])) {
                                            $address_components['address_label'] = $infos['properties']['formatted_address']['label'];
                                        }
                                    }

                                    if (isset($infos['properties']['viewport'])) {
                                        $viewport = $infos['properties']['viewport'];
                                        list($lng1, $lat1, $lng2, $lat2) = $viewport;

                                        $lng1 = (float) $lng1;
                                        $lat1 = (float) $lat1;
                                        $lng2 = (float) $lng2;
                                        $lat2 = (float) $lat2;
                                    }
                                }

                                if (isset($infos['geometry'])) {
                                    if (isset($infos['geometry']['geometries'])) {
                                        if (count($infos['geometry']['geometries'])) {
                                            $coords = current($infos['geometry']['geometries']);
                                            $coords = isAke($coords, 'coordinates', []);
                                            list($lng, $lat) = $coords;

                                            $lng = (float) $lng;
                                            $lat = (float) $lat;
                                        }
                                    }
                                }

                                $lat = str_replace(',', '.', $lat);
                                $lng = str_replace(',', '.', $lng);
                                $lat1 = str_replace(',', '.', $lat1);
                                $lng1 = str_replace(',', '.', $lng1);
                                $lng2 = str_replace(',', '.', $lng2);
                                $lat1 = str_replace(',', '.', $lat1);
                                $lat2 = str_replace(',', '.', $lat2);

                                $return = array_merge(
                                    ['lat' => $lat, 'lng' => $lng, 'lat1' => $lat1, 'lng1' => $lng1, 'lat2' => $lat2, 'lng2' => $lng2],
                                    $address_components
                                );

                                ksort($return);

                                $collection[] = $return;
                            }
                        }
                    }
                }
            }

            return $collection;
        }

        public function importKml($sellzone_id)
        {
            set_time_limit(0);

            $file = APPLICATION_PATH . DS . 'geoip' . DS . $sellzone_id . '.kml';

            if (File::exists($file)) {
                $db     = Model::PoiZone();
                $xml    = File::read($file);
                list($dummy, $xml) = explode('<Folder>', $xml, 2);
                $xml = $xml;

                $folders = explode('<Folder>', $xml);
                array_shift($folders);

                foreach ($folders as $folder) {
                    $type = Utils::cut('<name>', '</name>', $folder);
                    $placemarks = explode('<Placemark>', $folder);

                    array_shift($placemarks);

                    foreach ($placemarks as $placemark) {
                        $name       = Utils::cut('<name>', '</name>', $placemark);
                        $lng        = (double) Utils::cut('<longitude>', '</longitude>', $placemark);
                        $lat        = (double) Utils::cut('<latitude>', '</latitude>', $placemark);
                        $altitude   = (double) Utils::cut('<altitude>', '</altitude>', $placemark);

                        $row = $db->create([
                            'sellzone_id' => (int) $sellzone_id,
                            'type' => $type,
                            'name' => $name,
                            'lng' => $lng,
                            'lat' => $lat,
                            'alt' => $altitude
                        ])->save();
                    }
                }
            }

            dd($row->id, 'terminé');
        }

        public function importQuartiersKml($sellzone_id)
        {
            set_time_limit(0);

            $file = APPLICATION_PATH . DS . 'geoip' . DS . 'quartiers_' . $sellzone_id . '.kml';

            if (File::exists($file)) {
                $db     = Model::PoiZone();
                $xml    = File::read($file);
                list($dummy, $xml) = explode('<Folder>', $xml, 2);
                $xml = $xml;

                $folders = explode('<Folder>', $xml);
                array_shift($folders);

                foreach ($folders as $folder) {
                    $type = Utils::cut('<name>', '</name>', $folder);
                    $placemarks = explode('<Placemark>', $folder);

                    array_shift($placemarks);

                    foreach ($placemarks as $placemark) {
                        $name       = Utils::cut('<name>', '</name>', $placemark);
                        $lng        = (double) Utils::cut('<longitude>', '</longitude>', $placemark);
                        $lat        = (double) Utils::cut('<latitude>', '</latitude>', $placemark);
                        $altitude   = (double) Utils::cut('<altitude>', '</altitude>', $placemark);

                        $row = $db->create([
                            'sellzone_id' => (int) $sellzone_id,
                            'type' => 'quartier',
                            'name' => $name,
                            'lng' => $lng,
                            'lat' => $lat,
                            'alt' => $altitude
                        ])->save();
                    }
                }
            }

            dd($row->id, 'terminé');
        }

        public function searchPoi($sellzone_id, $q, $limit = 10, $offset = 0)
        {
            $collection = [];

            $pois = Model::PoiZone()->where(['sellzone_id', '=', (int) $sellzone_id])->get();

            foreach ($pois as $poi) {
                $poi['name'] = str_replace('&apos;', "'", $poi['name']);

                if (strlen($q)) {
                    $comp   = Inflector::lower(Inflector::unaccent($q));
                    $value  = Inflector::lower(Inflector::unaccent($poi['name']));

                    $checkName = fnmatch("*$comp*", $value);

                    $value  = Inflector::lower(Inflector::unaccent($poi['type']));

                    $checkType = fnmatch("*$comp*", $value);

                    if ($checkName || $checkType) {
                        $collection[] = $poi;
                    }
                } else {
                    $collection[] = $poi;
                }
            }

            $collection = $this->orderSearch($collection, $q);

            return array_slice($collection, $offset, $limit);
        }

        private function orderSearch($collection, $pattern)
        {
            if (empty($collection)) {
                return $collection;
            }

            $newCollection = [];
            $lengths = [];

            foreach ($collection as $item) {
                if (!isset($lengths[strlen($item['name'])])) {
                    $lengths[strlen($item['name'])] = [];
                }

                $lengths[strlen($item['name'])][] = $item;
            }

            asort($lengths);

            foreach ($lengths as $length => $subColl) {
                foreach ($subColl as $k => $segment) {
                    $comp   = Inflector::lower(Inflector::unaccent($pattern));
                    $value  = Inflector::lower(Inflector::unaccent($segment['name']));
                    $valueType  = Inflector::lower(Inflector::unaccent($segment['type']));

                    $check = fnmatch("$comp*", $value) || fnmatch("$comp*", $valueType);

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

        public function addBanoRedis($dpt, $sz = 1)
        {
            set_time_limit(0);

            $i = 0;

            $file = APPLICATION_PATH . DS . 'geoip' . DS . 'bano' . $dpt . '.csv';

            $db = lib('redys', ['bano']);

            if (File::exists($file)) {
                $csv = file($file);

                $tuples = [];

                foreach ($csv as $row) {
                    list($idOsm, $streetNumber, $street, $zip, $city, $source, $lat, $lng) = explode(',', $row, 8);

                    $dpt = (int) substr($zip, 0, 2);

                    $s = Inflector::urlize($street, '_');

                    $key = "$sz.$dpt.$zip.$streetNumber.$s";

                    $db->set($key, serialize([
                        'street' => $street,
                        'city' => $city,
                        'lat' => (double) $lat,
                        'lng' => (double) $lng
                    ]));

                    $i++;
                }
            }
        }

        public function addBano($dpt)
        {
            set_time_limit(0);

            $file = APPLICATION_PATH . DS . 'geoip' . DS . 'bano' . $dpt . '.csv';

            $db = Model::GeoBano();

            if (File::exists($file)) {
                $csv = file($file);

                $tuples = [];

                foreach ($csv as $row) {
                    list($idOsm, $streetNumber, $street, $zip, $city, $source, $lat, $lng) = explode(',', $row, 8);

                    $keyTuple = sha1($street . $zip . $city);

                    if (!in_array($keyTuple, $tuples)) {
                        $add = $db->create([
                            'street' => $street,
                            'zip' => $zip,
                            'city' => $city,
                            'lat' => (double) $lat,
                            'lng' => (double) $lng
                        ])->save();

                        $tuples[] = $keyTuple;
                    }
                }

                dd($add->toArray());
            }
        }

        public function searchInsee($sellzone_id, $q, $limit = 30, $offset = 0, $lat = 0, $lng = 0)
        {
            $number = true;
            $first = 1;

            if (fnmatch('* *', $q)) {
                $tab = explode(' ', $q);
                $first = Arrays::first($tab);

                if (is_numeric($first)) {
                    array_shift($tab);

                    $q = implode(' ', $tab);
                }
            }

            $insees = redis()->get('insee.data.' . $sellzone_id);

            if (!$insees) {
                $zips = Model::Coveredcity()->where(['sellzone_id', '=', (int) $sellzone_id])->cursor();

                foreach ($zips as $zip) {
                    $cs = Model::City()->where(['zip', '=', (string) $zip['zip']])->cursor();
                    foreach ($cs as $c) {
                        $n      = str_replace(' ', '_', Inflector::unaccent(Inflector::lower($zip['name'])));
                        $n2     = str_replace(' ', '_', Inflector::unaccent(Inflector::lower($c['name'])));

                        if ($n == $n2) {
                            $coll[] = [
                                'insee' => $c['insee'],
                                'zip'   => $zip['zip'],
                                'name'  => $c['name'],
                            ];
                        }
                    }
                }

                // $coll = array_values(array_unique($coll));

                redis()->set('insee.data.' . $sellzone_id, serialize($coll));

                $insees = $coll;
            } else {
                $insees = unserialize($insees);
            }

            $res = [];

            foreach ($insees as $insee) {
                $i = $insee['insee'];
                $data = json_decode(dwn("http://www.ariase.com/scripts/eligibilite/rechercheVoie.php?insee=$i&term=" . urlencode($q)), true);

                foreach ($data as $row) {
                    $res[] = [
                        'street' => isAke($row, 'label', ''),
                        'zip' => isAke($insee, 'zip', ''),
                        'city' => isAke($insee, 'name', ''),
                    ];
                }
            }

            dd($res);
        }

        public function searchBanoRedis($sellzone_id, $q, $limit = 30, $offset = 0, $lat = 0, $lng = 0)
        {
            $number = true;
            $first = 1;

            if (fnmatch('* *', $q)) {
                $tab = explode(' ', $q);
                $first = Arrays::first($tab);

                if (is_numeric($first)) {
                    array_shift($tab);

                    $q = implode(' ', $tab);
                }
            }

            $keyCache = 'cacheBanoRedis.' . sha1(serialize(func_get_args()));

            $collection = redis()->get($keyCache);

            $cachedZip = redis()->get('zips.covered.' . $sellzone_id);

            if (!$cachedZip) {
                $coll = [];

                $zips = Model::Coveredcity()->where(['sellzone_id', '=', (int) $sellzone_id])->cursor();

                foreach ($zips as $zip) {
                    $coll[] = $zip['zip'];
                }

                redis()->set('zips.covered.' . $sellzone_id, serialize($coll));

                $cachedZip = $coll;
            } else {
                $cachedZip = unserialize($cachedZip);
            }

            $sz = Model::Sellzone()->find((int) $sellzone_id);

            $lat = 0 < $lat ? $lat : (double) $sz->latitude;
            $lng = 0 < $lng ? $lng : (double) $sz->longitude;

            $calcDistance = 0 < $lat && 0 < $lng;

            $db = lib('redys', ['bano']);

            $qr = Inflector::urlize($q, '*');

            $n = $first;

            if (!$number) {
                $n = 1;
            }

            if (!$collection) {
                $collection = $tuples = [];

                $pois = $db->keys("$sellzone_id.*.*.*.*$qr*");

                foreach ($pois as $poi) {
                    list($d, $sz, $dpt, $zip, $sn, $s) = explode('.', $poi, 6);

                    $k = "$sz.$dpt.$zip.$sn.$s";

                    if (in_array($zip, $cachedZip)) {
                        $row = unserialize($db->get($k));

                        $address = $row['street'];

                        $distances = distanceKmMiles(
                            floatval($row['lng']),
                            floatval($row['lat']),
                            floatval($lng),
                            floatval($lat)
                        );

                        if ((double) $distances['km'] > 5 || !isset($row['zip'])) {
                            continue;
                        }

                        $old = $row;

                        if (isset($number)) {
                            $row = $this->getCoords($first . ' ' . $row['street'] . ' ' . $row['zip'] . ' ' . $row['city']);
                        } else {
                            $row = $this->getCoords($row['street'] . ' ' . $row['zip'] . ' ' . $row['city']);
                        }

                        if (isset($row['zip'])) {
                            if (!in_array($row['zip'], $cachedZip)) {
                                continue;
                            }
                        } else {
                            continue;
                        }

                        if (isset($row['city'])) {
                            if (is_array($row['city'])) {
                                $row['city_id'] = $row['city']['code'];
                                $row['city'] = $old['city'];
                            }
                        }

                        $row['address'] = $address;

                        if ($calcDistance) {
                            if (isset($row['lng']) && isset($row['lat']) && isset($lat) && isset($lng)) {
                                $distances = distanceKmMiles(
                                    floatval($row['lng']),
                                    floatval($row['lat']),
                                    floatval($lng),
                                    floatval($lat)
                                );

                                $km = floatval($distances['km']);

                                $row['distance'] = $km;
                            } else {
                                $row['distance'] = 0;
                            }
                        }

                        foreach ($row as $k => $v) {
                            if (fnmatch('*_id', $k)) {
                                $row[$k] = (int) $v;
                            } else if (is_numeric($v) && (fnmatch('*.*', $v) || fnmatch('*.*', $v))) {
                                $row[$k] = (double) $v;
                            }
                        }

                        if (isset($row['address_label'])) {
                            $row['name'] = $row['address_label'];

                            unset($row['address_label']);
                        }

                        $checkTuple = sha1(floatval($row['lng']) . floatval($row['lat']));

                        if (fnmatch($sz->department . '*', $row['zip']) && !in_array($checkTuple, $tuples)) {
                            $collection[] = $row;
                            $tuples[] = $checkTuple;
                        }
                    }
                }

                redis()->set($keyCache, serialize($collection));
            } else {
                $collection = unserialize($collection);
            }

            if (isset($calcDistance) && !empty($collection)) {
                if ($calcDistance) {
                    $collection = $this->sortBy($collection, 'distance');
                }
            }

            return array_slice($collection, $offset, $limit);
        }

        public function suggestAddressBySellzone($sellzone_id, $q, $limit = 30, $offset = 0, $lat = 0, $lng = 0)
        {
            $collection = [];

            $sz = Model::Sellzone()->refresh()->find((int) $sellzone_id);

            $lat = 0 < $lat ? $lat : (double) $sz->latitude;
            $lng = 0 < $lng ? $lng : (double) $sz->longitude;

            $calcDistance = 0 < $lat && 0 < $lng;

            $cachedZip = redis()->get('zips.covered.' . $sellzone_id);

            if (!$cachedZip) {
                $coll = [];

                $zips = Model::Coveredcity()->where(['sellzone_id', '=', (int) $sellzone_id])->cursor();

                foreach ($zips as $zip) {
                    $coll[] = $zip['zip'];
                }

                redis()->set('zips.covered.' . $sellzone_id, serialize($coll));

                $cachedZip = $coll;
            } else {
                $cachedZip = unserialize($cachedZip);
            }

            $city = redis()->get('data.city.' . sha1($lat . $lng));

            if (!$city) {
                $city = $this->getAddressByCoords($lat, $lng, true);

                redis()->set('data.city.' . sha1($lat . $lng), $city);
            }

            if ($sz) {
                $url = 'http://suggest.mappy.net/suggest/1.0/suggest?bbox=' . $sz->bbox . '&f=all&q=' . urlencode($q . ' ' . $city);

                $key = 'suggestAddressBySellzone.' . sha1($url);

                $json = redis()->get($key);

                if (!$json) {
                    $json = dwn($url);
                    redis()->set($key, $json);
                }

                $tab = json_decode($json, true);

                $tab = current($tab);

                foreach ($tab as $row) {
                    $type       = isAke($row, 'type', false);
                    $address    = isAke($row, 'address', '');
                    $name       = isAke($row, 'name', '');

                    if ($type == 'address') {
                        $address    = str_replace(['<em>', '</em>'], '', $address);
                        $tabAddress = explode(', ', $address);

                        $last           = end($tabAddress);
                        $tabAddress2    = explode(' ', $last);
                        $zip            = current($tabAddress2);

                        if (in_array($zip, $cachedZip)) {
                            $coords = $this->getCoords($address);

                            if ($calcDistance) {
                                if (isset($coords['lng']) && isset($coords['lat']) && isset($lat) && isset($lng)) {
                                    $distances = distanceKmMiles(
                                        floatval($coords['lng']),
                                        floatval($coords['lat']),
                                        floatval($lng),
                                        floatval($lat)
                                    );

                                    $km = floatval($distances['km']);

                                    $coords['distance'] = $km;
                                } else {
                                    $coords['distance'] = 0;
                                }
                            }

                            if (isset($coords['city'])) {
                                if (is_array($coords['city'])) {
                                    $coords['city_id'] = $coords['city']['code'];
                                    $coords['city'] = $coords['city'];
                                }
                            }

                            foreach ($coords as $k => $v) {
                                if (fnmatch('*_id', $k)) {
                                    $coords[$k] = (int) $v;
                                } else if (is_numeric($v) && (fnmatch('*.*', $v) || fnmatch('*.*', $v))) {
                                    $coords[$k] = (double) $v;
                                }
                            }

                            if (isset($coords['address_label'])) {
                                $coords['name'] = $coords['address_label'];

                                unset($coords['address_label']);
                            }

                            $collection[] = $coords;
                        }
                    // } elseif ($type == 'poi') {
                    //     $address    = str_replace(['<em>', '</em>'], '', $address);
                    //     $name       = str_replace(['<em>', '</em>'], '', $name);
                    //     $tabAddress = explode(', ', $address);

                    //     $last           = end($tabAddress);
                    //     $tabAddress2    = explode(' ', $last);
                    //     $zip            = current($tabAddress2);

                    //     if (in_array($zip, $cachedZip)) {
                    //         $coords = $this->getCoords($address);

                    //         $coords['address_label'] = $name . ' ' . $coords['address'];

                    //         if ($calcDistance) {
                    //             if (isset($coords['lng']) && isset($coords['lat']) && isset($lat) && isset($lng)) {
                    //                 $distances = distanceKmMiles(
                    //                     floatval($coords['lng']),
                    //                     floatval($coords['lat']),
                    //                     floatval($lng),
                    //                     floatval($lat)
                    //                 );

                    //                 $km = floatval($distances['km']);

                    //                 $coords['distance'] = $km;
                    //             } else {
                    //                 $coords['distance'] = 0;
                    //             }
                    //         }

                    //         if (isset($coords['city'])) {
                    //             if (is_array($coords['city'])) {
                    //                 $coords['city_id']  = $coords['city']['code'];
                    //                 $coords['city']     = $coords['city'];
                    //             }
                    //         }

                    //         foreach ($coords as $k => $v) {
                    //             if (fnmatch('*_id', $k)) {
                    //                 $coords[$k] = (int) $v;
                    //             } else if (is_numeric($v) && (fnmatch('*.*', $v) || fnmatch('*.*', $v))) {
                    //                 $coords[$k] = (double) $v;
                    //             }
                    //         }

                    //         if (isset($coords['address_label'])) {
                    //             $coords['name'] = $coords['address_label'];

                    //             unset($coords['address_label']);
                    //         }

                    //         $collection[] = $coords;
                    //     }
                    } else {
                        continue;
                    }
                }
            }

            if (empty($collection) && !fnmatch('rue *', $q)) {
                return $this->suggestAddressBySellzone($sellzone_id, 'rue ' . $q, $limit, $offset, $lat, $lng);
            } else {
                return array_slice($collection, $offset, $limit);
            }
        }

        public function searchBano($sellzone_id, $q, $limit = 30, $offset = 0, $lat = 0, $lng = 0)
        {
            return call_user_func_array([$this, 'suggestAddressBySellzone'], func_get_args());

            if (fnmatch('* *', $q)) {
                $tab = explode(' ', $q);
                $first = Arrays::first($tab);

                if (is_numeric($first)) {
                    $number = true;
                    array_shift($tab);

                    $q = implode(' ', $tab);
                }
            }

            $keyCache = 'l.cachersBanos.' . sha1(serialize(func_get_args()));

            $collection = redis()->get($keyCache);

            $cachedZip = redis()->get('zips.covered.' . $sellzone_id);

            if (!$cachedZip) {
                $coll = [];

                $zips = Model::Coveredcity()->where(['sellzone_id', '=', (int) $sellzone_id])->cursor();

                foreach ($zips as $zip) {
                    $coll[] = $zip['zip'];
                }

                redis()->set('zips.covered.' . $sellzone_id, serialize($coll));

                $cachedZip = $coll;
            } else {
                $cachedZip = unserialize($cachedZip);
            }

            $sz = Model::Sellzone()->refresh()->find((int) $sellzone_id);

            $lat = 0 < $lat ? $lat : (double) $sz->latitude;
            $lng = 0 < $lng ? $lng : (double) $sz->longitude;

            $calcDistance = 0 < $lat && 0 < $lng;

            if (!$collection) {
                $collection = [];

                if ($sz) {
                    $q   = str_replace(' ', '%', Inflector::lower(Inflector::unaccent($q)));

                    $cursor = Model::GeoBano()
                    ->where(['sellzone_id', '=', $sz->id])
                    ->where(['street', 'LIKE', "%$q%"])
                    ->cursor();

                    $c = 0;

                    while ($row = $cursor->fetch()) {
                        unset($row['id']);
                        unset($row['sellzone_id']);
                        unset($row['created_at']);
                        unset($row['updated_at']);

                        $address = $row['street'];

                        ksort($row);

                        $distances = distanceKmMiles(
                            floatval($row['lng']),
                            floatval($row['lat']),
                            floatval($lng),
                            floatval($lat)
                        );

                        if ((double) $distances['km'] > 5) {
                            continue;
                        }

                        $old = $row;

                        if (isset($number)) {
                            $row = $this->getCoords($first . ' ' . $row['street'] . ' ' . $row['zip'] . ' ' . $row['city']);
                        } else {
                            $row = $this->getCoords($row['street'] . ' ' . $row['zip'] . ' ' . $row['city']);
                        }

                        if (isset($row['zip'])) {
                            if (!in_array($row['zip'], $cachedZip)) {
                                continue;
                            }
                        } else {
                            continue;
                        }

                        if (isset($row['city'])) {
                            if (is_array($row['city'])) {
                                $row['city_id'] = $row['city']['code'];
                                $row['city'] = $old['city'];
                            }
                        }

                        $row['address'] = $address;

                        if ($calcDistance) {
                            if (isset($row['lng']) && isset($row['lat']) && isset($lat) && isset($lng)) {
                                $distances = distanceKmMiles(
                                    floatval($row['lng']),
                                    floatval($row['lat']),
                                    floatval($lng),
                                    floatval($lat)
                                );

                                $km = floatval($distances['km']);

                                $row['distance'] = $km;
                            } else {
                                $row['distance'] = 0;
                            }
                        }

                        foreach ($row as $k => $v) {
                            if (fnmatch('*_id', $k)) {
                                $row[$k] = (int) $v;
                            } else if (is_numeric($v) && (fnmatch('*.*', $v) || fnmatch('*.*', $v))) {
                                $row[$k] = (double) $v;
                            }
                        }

                        if (isset($row['address_label'])) {
                            $row['name'] = $row['address_label'];

                            unset($row['address_label']);
                        }

                        if (fnmatch($sz->department . '*', $row['zip'])) {
                            // if (count($collection) >= $limit) {
                            //     return $this->sortBy($collection, 'distance');
                            // }

                            $collection[] = $row;

                            $c++;
                        }
                    }
                }

                redis()->set($keyCache, serialize($collection));
            } else {
                $collection = unserialize($collection);
            }

            if (empty($collection)) {
                $sugColl = [];

                $suggest = $this->suggestAddressBySellzone($sellzone_id, $q);

                foreach ($suggest as $sug) {
                    if (isset($sug['lng']) && isset($sug['lat']) && isset($lat) && isset($lng)) {
                        $distances = distanceKmMiles(
                            floatval($sug['lng']),
                            floatval($sug['lat']),
                            floatval($lng),
                            floatval($lat)
                        );

                        $km = floatval($distances['km']);

                        $sug['distance'] = $km;
                        $sugColl[] = $sug;
                    }
                }

                return $this->sortBy($sugColl, 'distance');
            }

            if (isset($calcDistance)) {
                if ($calcDistance) {
                    $collection = $this->sortBy($collection, 'distance');
                }
            }

            return array_slice($collection, $offset, $limit);
        }

        private function sortBy($collection, $field, $orderDirection = 'ASC')
        {
            $sortFunc = function($key, $direction) {
                return function ($a, $b) use ($key, $direction) {
                    if (!isset($a[$key]) || !isset($b[$key])) {
                        return false;
                    }

                    if ('ASC' == $direction) {
                        return $a[$key] > $b[$key];
                    } else {
                        return $a[$key] < $b[$key];
                    }
                };
            };

            usort($collection, $sortFunc($field, $orderDirection));

            return $collection;
        }

        public function pois($lat, $lng)
        {
            $collection = [];
            // $bbox = $this->getBoundingBox($lat, $lng);
            $bbox = isKh("bboxxes.$lat.$lng", function () use ($lat, $lng) {
                $json = fgc("https://api.foursquare.com/v2/private/webbounds?locale=fr&explicit-lang=true&v=20151103&ll=$lat%2C$lng&wsid=1V3PHKJ4FGVAELBCWHO2Z12PQCOUNJ&oauth_token=QEJ4AQPTMMNB413HGNZ5YDMJSHTOHZHMLZCAQCCLXIX41OMP");

                $tab = json_decode($json, true);

                $response = isAke($tab, 'response', []);

                if (empty($response)) {
                    return lib('geo')->getBoundingBox($lat, $lng);
                } else {
                    $suggestedBoundingBox = isAke($response, 'suggestedBoundingBox', []);

                    if (empty($suggestedBoundingBox)) {
                        return lib('geo')->getBoundingBox($lat, $lng);
                    } else {
                        return [
                            $suggestedBoundingBox['sw']['lat'],
                            $suggestedBoundingBox['sw']['lng'],
                            $suggestedBoundingBox['ne']['lat'],
                            $suggestedBoundingBox['ne']['lng']
                        ];
                    }
                }
            });

            $url = "https://api.foursquare.com/v2/search/recommendations?locale=fr&explicit-lang=true&v=20151103&m=foursquare&limit=100&intent=bestnearby&ll=" . $lat . "%2C" . $lng . "&wsid=1V3PHKJ4FGVAELBCWHO2Z12PQCOUNJ&oauth_token=QEJ4AQPTMMNB413HGNZ5YDMJSHTOHZHMLZCAQCCLXIX41OMP";

            $data = isKh('4sqlds.' . sha1($lat . $lng), function () use ($url) {
                return fgc($url);
            });

            $tab = json_decode($data, true);
            $response = $tab['response'];

            if (isset($response['group'])) {
                if (isset($response['group']['results'])) {
                    foreach ($response['group']['results'] as $poi) {
                        $id_category = $category = $pic = null;

                        $loc = isAke($poi['venue'], 'location', []);

                        if (isset($poi['photo'])) {
                            $pic = [
                                'url' => $poi['photo']['prefix'] . $poi['photo']['suffix'],
                                'width' => $poi['photo']['width'],
                                'height' => $poi['photo']['height'],
                                'date' => $poi['photo']['createdAt'],
                                'id_4s' => $poi['photo']['id'],
                            ];
                        }


                        $poi = isAke($poi, 'venue', []);

                        if (isset($poi['categories'])) {
                            if (is_array($poi['categories']) && !empty($poi['categories'])) {
                                $cat = current($poi['categories']);
                                $id_category = $cat['id'];
                                $category = $cat['name'];
                            }
                        }

                        $row = [
                            'id_4s'             => isAke($poi, 'id', null),
                            'name'              => isAke($poi, 'name', null),
                            'id_maponics'       => isAke($loc, 'contextGeoId', null),
                            'lat'               => isAke($loc, 'lat', null),
                            'lng'               => isAke($loc, 'lng', null),
                            'address'           => isAke($loc, 'address', null),
                            'city'              => isAke($loc, 'city', null),
                            'zip'               => isAke($loc, 'postalCode', null),
                            'neighborhood'      => isAke($loc, 'neighborhood', null),
                            'country'           => isAke($loc, 'country', null),
                            'canonical_path'    => isAke($poi, 'canonicalPath', null),
                            'rating'            => isAke($poi, 'rating', 0),
                            'stats'             => isAke($poi, 'stats', []),
                            'id_category'       => $id_category,
                            'category'          => $category,
                            'picture'           => $pic,
                        ];

                        $collection[] = $row;

                        $this->makePlace($row);
                    }
                }
            }

            $this->relatedNeighborhoods($bbox[0], $bbox[1], $bbox[2], $bbox[3]);

            return $collection;
        }

        public function fsSearch($q, $lat, $lng)
        {
            $collection = [];
            $url = "https://api.foursquare.com/v2/search/recommendations?locale=fr&explicit-lang=true&v=20151103&m=foursquare&query=" . urlencode($q) . "&limit=100&mode=mapRequery&ll=" . $lat . "%2C" . $lng . "&wsid=1V3PHKJ4FGVAELBCWHO2Z12PQCOUNJ&oauth_token=QEJ4AQPTMMNB413HGNZ5YDMJSHTOHZHMLZCAQCCLXIX41OMP";

            $data = isKh('4sqlds.' . sha1($q . $lat . $lng), function () use ($url) {
                return fgc($url);
            });

            $tab = json_decode($data, true);
            $response = $tab['response'];

            if (isset($response['group'])) {
                if (isset($response['group']['results'])) {
                    foreach ($response['group']['results'] as $poi) {
                        $id_category = $category = $pic = null;

                        $loc = isAke($poi['venue'], 'location', []);

                        if (isset($poi['photo'])) {
                            $pic = [
                                'url' => $poi['photo']['prefix'] . $poi['photo']['suffix'],
                                'width' => $poi['photo']['width'],
                                'height' => $poi['photo']['height'],
                                'date' => $poi['photo']['createdAt'],
                                'id_4s' => $poi['photo']['id'],
                            ];
                        }


                        $poi = isAke($poi, 'venue', []);

                        if (isset($poi['categories'])) {
                            if (is_array($poi['categories']) && !empty($poi['categories'])) {
                                $cat = current($poi['categories']);
                                $id_category = $cat['id'];
                                $category = $cat['name'];
                            }
                        }

                        $row = [
                            'id_4s'             => isAke($poi, 'id', null),
                            'name'              => isAke($poi, 'name', null),
                            'id_maponics'       => isAke($loc, 'contextGeoId', null),
                            'lat'               => isAke($loc, 'lat', null),
                            'lng'               => isAke($loc, 'lng', null),
                            'address'           => isAke($loc, 'address', null),
                            'city'              => isAke($loc, 'city', null),
                            'zip'               => isAke($loc, 'postalCode', null),
                            'neighborhood'      => isAke($loc, 'neighborhood', null),
                            'country'           => isAke($loc, 'country', null),
                            'canonical_path'    => isAke($poi, 'canonicalPath', null),
                            'rating'            => isAke($poi, 'rating', 0),
                            'stats'             => isAke($poi, 'stats', []),
                            'id_category'       => $id_category,
                            'category'          => $category,
                            'picture'           => $pic,
                        ];

                        $collection[] = $row;

                        $this->makePlace($row);
                    }
                }
            }

            return $collection;
        }

        public function foursquare($path)
        {
            $collection = [];

            $html = getCached('4js.' . sha1($path), function () use ($path) {
                try {
                    $ctn =  file_get_contents('https://fr.foursquare.com' . $path);

                    return $ctn;
                } catch (\Exception $e) {
                    return '';
                }
            });

            $row = [];

            $row['image']       = Utils::cut('property="og:description" /><meta content="', '"', $html);
            $row['tel']         = Utils::cut('<span class="tel" itemprop="telephone">', '<', $html);
            $row['description'] = html_entity_decode(Utils::cut('property="og:title" /><meta content="', '"', $html));
            $row['prox'] = [];

            if (strstr($html, ',{"venue":{"id":"')) {
                $tab = explode(',{"venue":', $html);
                array_shift($tab);

                foreach ($tab as $r) {
                    $id_category = null;
                    $category = null;

                    if (strstr($r, '],"title"')) {
                        list($r, $dummy) = explode('],"title"', $r, 2);
                    }

                    $venue = json_decode('{"venue":' . $r, 1);

                    $poi = $venue['venue'];

                    $loc = isAke($poi, 'location', []);

                    if (isset($poi['categories'])) {
                        if (is_array($poi['categories']) && !empty($poi['categories'])) {
                            $cat = current($poi['categories']);
                            $id_category = $cat['id'];
                            $category = $cat['name'];
                        }
                    }

                    $add = [
                        'id_4s'             => isAke($poi, 'id', null),
                        'name'              => isAke($poi, 'name', null),
                        'id_maponics'       => isAke($loc, 'contextGeoId', null),
                        'lat'               => isAke($loc, 'lat', null),
                        'lng'               => isAke($loc, 'lng', null),
                        'address'           => isAke($loc, 'address', null),
                        'city'              => isAke($loc, 'city', null),
                        'zip'               => isAke($loc, 'postalCode', null),
                        'neighborhood'      => isAke($loc, 'neighborhood', null),
                        'country'           => isAke($loc, 'country', null),
                        'canonical_path'    => isAke($poi, 'canonicalPath', null),
                        'rating'            => isAke($poi, 'rating', 0),
                        'stats'             => isAke($poi, 'stats', []),
                        'id_category'       => $id_category,
                        'category'          => $category,
                    ];

                    $row['prox'][] = $add;
                }
            }

            return $row;
        }

        public function relatedNeighborhoods($lat1, $lng1, $lat2, $lng2)
        {
            $collection = [];

            $url = "https://api.foursquare.com/v2/search/recommendations?locale=fr&explicit-lang=true&v=20151103&m=foursquare&limit=30&mode=mapRequery&intent=bestnearby&sw=".$lat1."%2C".$lng1."&ne=".$lat2."%2C".$lng2."&wsid=1V3PHKJ4FGVAELBCWHO2Z12PQCOUNJ&oauth_token=QEJ4AQPTMMNB413HGNZ5YDMJSHTOHZHMLZCAQCCLXIX41OMP";

            $data = isKh('4sq.' . sha1($lat1 . $lng1 . $lat2 . $lng2), function () use ($url) {
                return fgc($url);
            });

            $tab = json_decode($data, true);

            if (isset($tab['response'])) {
                $response = $tab['response'];

                if (isset($response['context'])) {
                    if (isset($response['context']['relatedNeighborhoods'])) {
                        foreach ($response['context']['relatedNeighborhoods'] as $quartier) {
                            $feature = isAke($quartier, 'feature', []);

                            if (!empty($feature)) {
                                $name           = isAke($feature, 'name', null);
                                $geometry       = isAke($feature, 'geometry', null);
                                $display        = isAke($feature, 'displayName', null);
                                $id_maponics    = str_replace('maponics:', '', isAke($feature, 'id', null));

                                $collection[] = [
                                    'id_maponics'   => $id_maponics,
                                    'name'          => $name,
                                    'display'       => $display,
                                    'geometry'      => $geometry
                                ];

                                Model::GeoNeighborhood()->create([
                                    'id_maponics'   => $id_maponics,
                                    'name'          => $name,
                                    'display'       => $display,
                                    'geometry'      => $geometry
                                ])->save();
                            }
                        }
                    }
                }
            }

            return $collection;
        }

        public function pois2($name, $city = 'Paris')
        {
            $collection = [];

            $infos = $this->getCoords($city);
            extract($infos);
            $url = 'http://suggest.mappy.net/suggest/1.0/suggest?bbox='.$lat1.'%2C'.$lng1.'%2C'.$lat2.'%2C'.$lng2.'&f=all&q=' . urlencode($name);

            $key = 'poi.' . $name . '.' . $city;

            $json = $cached = redis()->get($key);

            if (!$cached) {
                $json = dwn($url);
                redis()->set($key, $json);
            }

            $tab = json_decode($json, true);

            foreach ($tab['suggests'] as $row) {
                if ($row['type'] == 'poi') {
                    $row['location'] = $this->getCoords(str_replace(['<em>', '</em>'], '', $row['address']));

                    $collection[] = $row;
                }
            }

            return $collection;
        }

        public function poisByAddress($address)
        {
            $collection = [];
            $infos = $this->getCoords('paris');
            extract($infos);
            $url = "http://search.mappy.net/search/1.0/find?extend_bbox=1&bbox=$lat1,$lng1,$lat2,$lng2&q=".urlencode($address)."&favorite_country=250&language=FRE&loc_format=geojson&mid=384081603&tagid=SPD_RESPONSE_SEARCH&max_results=100";

            $key = 'poi.' . sha1($address);

            $json = $cached = redis()->get($key);

            if (!$cached) {
                $json = dwn($url);
                redis()->set($key, $json);
            }

            $tab = json_decode($json, true);

            if (isset($tab['pois'])) {
                $collection = $tab['pois'];
            }

            return $collection;
        }

        public function geonames($lat, $lng)
        {
            $key = 'geonames.' . sha1($lat . '.' . $lng);

            $json = redis()->get($key);

            if (!$json) {
                $json = dwn('http://scatter-otl.rhcloud.com/location?lat=' . $lat . '&long=' . $lng);
                redis()->set($key, $json);
            }

            return json_decode($json, true);
        }

        public function getImgPanoId($panoId)
        {
            return "https://geo3.ggpht.com/cbk?panoid=$panoId&output=thumbnail&cb_client=search.LOCAL_UNIVERSAL.gps&thumb=2&w=600&h=480&yaw=221.75778&pitch=0";
        }

        public function cid($cid)
        {
            $html = fgc('http://maps.google.fr/?cid=' . $cid);

            $seg    = Utils::cut('cacheResponse(', ');', $html);

            $ws = $tel = $advice = $name = $formatted_address = $id_hex = $panoId = $rate = $lng = $lat = $address = $zipCity = null;

            eval('$tab = ' . $seg . ';');

            if (strstr($seg, ".ggpht.com/cbk")) {
                $panoSeg    = Utils::cut('ggpht.com/cbk', 'u0026', $seg);
                $panoId     = Utils::cut('panoid=', '\\', $panoSeg);
            }

            if (isset($tab[8])) {
                if (!empty($tab[8])) {
                    // dd($tab);
                    $id_hex = $tab[8][0][0];
                    $formatted_address = $tab[8][13];
                    $lat = $tab[8][0][2][0];
                    $lng = $tab[8][0][2][1];
                    $rate = $tab[8][3];
                    $name = $tab[8][1];
                    $address = $tab[8][2][0] . ', ' . $tab[8][2][1];
                    $zipCity = isset($tab[8][2][2]) ? $tab[8][2][2] : $tab[8][2][1];
                    $tel = str_replace([' '], '', $tab[8][7]);
                    $advice = str_replace(['avis', ' '], '', $tab[8][4]);
                    $ws = 'http://' . str_replace([' '], '', $tab[8][11][1]);

                    if ($ws == 'http://') {
                        $ws = null;
                    }

                    return [
                        'coords' => $tab[0][0][0],
                        'pitch' => $tab[0][3],
                        'key' => $tab[8][27],
                        'cid' => $cid,
                        'id_hex' => $id_hex,
                        'id_pano' => $panoId,
                        'name' => $name,
                        'lat' => $lat,
                        'lng' => $lng,
                        'formatted_address' => $formatted_address,
                        'zipCity' => $zipCity,
                        'tel' => $tel,
                        'website' => $ws,
                        'advice' => $advice,
                        'rate' => $rate,
                        'type' => $tab[8][12],
                        'schedule' => $this->schedule($tab[8][9][1])
                    ];
                }
            }

            return null;
        }

        private function schedule($rows)
        {
            if (!is_array($rows)) {
                return [];
            }

            $schedule = [];

            foreach($rows as $row) {
                $day = $row[0];
                $schedules = $row[1];

                switch ($day) {
                    case 'lundi':
                        $order = 1;
                        break;
                    case 'mardi':
                        $order = 2;
                        break;
                    case 'mercredi':
                        $order = 3;
                        break;
                    case 'jeudi':
                        $order = 4;
                        break;
                    case 'vendredi':
                        $order = 5;
                        break;
                    case 'samedi':
                        $order = 6;
                        break;
                    case 'dimanche':
                        $order = 7;
                        break;
                }

                $schedule[] = ['day' => $day, 'schedules' => $schedules, 'order' => $order];
            }

            $schedule = array_values(coll($schedule)->sortBy("order")->toArray());

            $return = [];

            foreach ($schedule as $d) {
                $return[$d['day']] = $d['schedules'];
            }

            return $return;
        }

        public function ll2($lat, $lng)
        {
            $url = 'https://www.google.com/maps/dir//' . $lat . ',' . $lng . '?dg=dbrw&newdg=1';

            $html = $this->dwnCache($url);
            $url = urldecode(Utils::cut('<A HREF="', '"', $html));
            $html = $this->dwnCache($url);
            $url = urldecode(Utils::cut('<A HREF="', '"', $html));
            $html = $this->dwnCache($url);

            $seg    = Utils::cut('cacheResponse(', ');', $html);

            eval('$tab = ' . $seg . ';');

            $row = isset($tab[10]) ? $tab[10] : [];

            if (isset($row[2])) {
                if (isset($row[2][1])) {
                    if (isset($row[2][1][0])) {
                        if (isset($row[2][1][0][9])) {
                            $info = $row[2][1][0][9];

                            if (isset($info[1])) {
                                return ['id' => $info[0], 'address' => $info[1]];
                            }
                        }
                    }
                }
            }

            return null;
        }

        public function ll($lat, $lng)
        {
            /*https://www.google.com/maps/preview/reveal?authuser=0&hl=fr&pb=!2m12!1m3!1d2891.9216077701594!2d2.344914927810538!3d48.85266991714784!2m3!1f0!2f0!3f0!3m2!1i1440!2i423!4f13.1!3m2!2d2.3166071!3d48.857806829727*/

            $data = $this->dwnCache("https://www.google.com/maps/preview/reveal?authuser=0&hl=fr&pb=!2m12!1m3!1d2891.9216077701594!2d2.344914927810538!3d48.85266991714784!2m3!1f0!2f0!3f0!3m2!1i1440!2i423!4f13.1!3m2!2d$lng!3d" . $lat);

            $data = str_replace("\n", "", $data);
            $data = str_replace("\r", "", $data);
            $data = str_replace("\t", "", $data);
            $data = str_replace(")]}'", "", $data);

            $tab = json_decode($data, true);
            // dd($tab);

            $address    = $tab[2][18];
            $place_id   = $tab[2][78];
            $hexa       = $tab[1][1];
            $link       = $tab[2][66][0];
            $type       = str_replace('SearchResult.', '', $tab[2][88][1]);

            if (fnmatch('*ludocid=*', $link)) {
                $gid = Utils::cut('ludocid=', '#', $link);
            } else {
                $gid = null;
            }

            $description = $tab[2][44][2][0][0];

            return ['id' => $hexa, "lat" => $tab[1][2][2], "lng" => $tab[1][2][3], 'address' => $address, 'place_id' => $place_id, 'gid' => $gid, 'description' => $description, 'type' => $type];
        }

        public function iti($from, $to)
        {
            $url    = 'https://www.google.fr/maps/dir/' . urlencode($from) . '/' . urlencode($to);

            $html   = $this->dwnCache($url);

            $seg    = Utils::cut('cacheResponse(', ');', $html);

            eval('$tab = ' . $seg . ';');

            $infos = ['start' => [], 'end' => []];

            $start  = current(current($tab[10][2]));
            $end    = current(end($tab[10][2]));

            $infos['start']['latitude']             = floatval($start[0][2][2]);
            $infos['start']['longitude']            = floatval($start[0][2][3]);
            $infos['start']['formatted_address']    = $start[0][0];
            $infos['start']['id_coords']            = $start[0][1];
            $infos['start']['address']              = $start[1][0][0];
            $infos['start']['city']                 = $start[1][0][1];
            $infos['start']['id_gmap']              = $start[8][11];

            $infos['end']['latitude']               = floatval($end[0][2][2]);
            $infos['end']['longitude']              = floatval($end[0][2][3]);
            $infos['end']['formatted_address']      = $end[0][0];
            $infos['end']['id_coords']              = $end[0][1];
            $infos['end']['address']                = $end[1][0][0];
            $infos['end']['city']                   = $end[1][0][1];
            $infos['end']['id_gmap']                = $end[8][11];


            $infos['distance']                      = $tab[10][0][0][0][1][0];
            $infos['time']                          = $tab[10][0][0][0][2][0];

            return $infos;
        }

        public function getTransports($lat, $lng)
        {
            $bbox = $this->getBoundingBox($lat, $lng);

            $url = "https://navitia.opendatasoft.com/api/records/1.0/download/?dataset=global_stops&format=json&geofilter.bbox={$bbox[0]},{$bbox[1]},{$bbox[2]},{$bbox[3]}&rows=1000&timezone=Europe%2FParis";

            return xCache('get.transports.' . sha1($url), function () use ($url, $lat, $lng) {
                $json = lib('geo')->dwnCache($url);
                $tab = json_decode($json, true);

                $collection = [];

                foreach ($tab as $row) {
                    $obj        = [];
                    $stop_id    = isAke(isAke($row, 'fields', []), 'stop_id', '');
                    $name       = isAke(isAke($row, 'fields', []), 'stop_name', '');
                    $geo        = isAke(isAke($row, 'fields', []), 'geo', [0,0]);

                    $distances = distanceKmMiles($lng, $lat, $geo[1], $geo[0]);

                    $type = 'bus';

                    for ($i = 0; $i < strlen($name); $i++) {
                        $char = $name[$i];
                        $charC = Inflector::urlize($char, '');

                        if (strlen($charC)) {
                            if (!is_numeric($charC)) {
                                if (ctype_lower($char)) {
                                    $type = 'subway';
                                }
                            }
                        }
                    }

                    $has = false;

                    if (fnmatch("StopArea:OIF*", $stop_id)) {
                        list($dummy, $ratp_stop_id) = explode('StopArea:OIF', $stop_id, 2);

                        $native = clipp()->em('transports')->findOne(['stop_id' => strval($ratp_stop_id)]);

                        $has = true;

                        if ($native) {
                            unset($native['_id']);
                            unset($native['created_at']);
                            unset($native['updated_at']);
                            unset($native['status']);
                            unset($native['__v']);

                            $native['geo'] = $native['location']['coordinates'];

                            $native['km'] = $distances['km'];
                            $native['distances'] = $distances;

                            $collection[] = $native;
                        }
                    }

                    if (!$has) {
                        $obj['title'] = $name;
                        $obj['geo'] = $geo;
                        $obj['km'] = $distances['km'];
                        $obj['distances'] = $distances;
                        $obj['type'] = $type;
                        $obj['stop_id'] = $stop_id;

                        $collection[] = $obj;
                    }
                }

                $check = coll($collection)->min('km');

                $collection = coll($collection)->sortBy('km')->toArray();
                $collection = array_values($collection);

                if (0.5 > $check) {
                    $return = [];

                    foreach ($collection as $row) {
                        if (0.5 >= $row['km']) {
                            $return[] = $row;
                        } else {
                            return $return;
                        }
                    }
                } else {
                    return $collection;
                }
            });
        }

        public function mic()
        {
            /*http://ccu.viamichelin.com/recoa/r?ks=musee_du_louv&nks=1&fe=3&cb=FRA&lf=fra&nb=20&charset=UTF-8&version=&lang=fra*/

            /*http://vmrest.viamichelin.com/apir/1/geocode1f.json2?query=Rue%20Dulac,%2075015%20Paris%2015,%20France&favc=FRA&showHT=true&lg=fra&obfuscation=false&ie=UTF-8&charset=UTF-8&authKey=JSBS20110216111214120400892678*/

            /*http://vmrest.viamichelin.com/apir/1/weather.json2?center=2.334622:48.861194&todayForecast=true&fullForecast=true&nbDays=1&atLeastOne=true&obfuscation=false&ie=UTF-8&charset=UTF-8&authKey=JSBS20110216111214120400892678&lg=fra*/

            /*http://vmrest.viamichelin.com/apir/1/rgeocode.json2?center=2.334622:48.861194&showHT=true&obfuscation=false&ie=UTF-8&charset=UTF-8&authKey=JSBS20110216111214120400892678&lg=fra*/

            /**/
        }

        public function parkings($lat, $lng)
        {
            $bbox = $this->getBoundingBox($lat, $lng);
            $url = "http://vmrest.viamichelin.com/apir/1/PoiCrit.json/{$bbox[1]}:{$bbox[0]}:{$bbox[3]}:{$bbox[2]}?zoomLevel=10&param=PARKVM&obfuscation=false&ie=UTF-8&authKey=JSBS20110216111214120400892678&lg=fra&callback=JSE.HTTP.asyncRequests[39].HTTPResponseLoaded";

            $json = getCached('geo.parking.' . sha1(serialize(func_get_args())), function () use ($url) {
                return dwn($url);
            });

            dd($json);
        }

        public function weather($lat, $lng)
        {
            $url = "http://vmrest.viamichelin.com/apir/1/weather.json2?center=$lng:$lat&todayForecast=true&fullForecast=true&nbDays=1&atLeastOne=true&obfuscation=false&ie=UTF-8&charset=UTF-8&authKey=JSBS20110216111214120400892678&lg=fra";

            // $json = $this->dwn($url);

            $json = getCached('geoweather.' . sha1(serialize(func_get_args())), function () use ($url) {
                return dwn($url);
            }, strtotime('+1 hour'));

            $tab = json_decode($json, true);

            if (isset($tab['weatherStationList'])) {
                if (is_array($tab['weatherStationList'])) {
                    return $tab['weatherStationList'][0]['observation'];
                }
            }
        }

        public function apiPois($lat, $lng, $type = 'restaurant', $radius = 5000)
        {
            $json = $this->dwnCache("https://maps.googleapis.com/maps/api/place/nearbysearch/json?language=fr&location=$lat,$lng&radius=$radius&types=$type&key=AIzaSyBIfV0EMXrTDjrvD92QX5bBiyFmBbT-W8E");

            $tab = json_decode($json, true);

            $results = isAke($tab, 'results', []);

            $collection = [];

            foreach ($results as $row) {
                $latRow = floatval(array_get($row, 'geometry.location.lat', 0));
                $lngRow = floatval(array_get($row, 'geometry.location.lng', 0));

                $distances  = distanceKmMiles($lng, $lat, $lngRow, $latRow);

                $addr = $this->ll($latRow, $lngRow);

                $id = isAke($row, 'place_id', null);

                if ($id) {
                    $jdata = $this->dwnCache("https://www.waze.com/maps/api/place/details/json?placeid=$id&key=AIzaSyBIfV0EMXrTDjrvD92QX5bBiyFmBbT-W8E");

                    $dtab   = json_decode($jdata, true);
                    $dtab   = isAke($dtab, 'result', []);

                    $ac     = isAke($dtab, 'address_components', []);
                    $url    = isAke($dtab, 'url', null);

                    $street = $city = $zip = $country = $cid = null;

                    if (!empty($ac)) {
                        $street = $ac[0]['short_name'];
                        $city = $ac[1]['short_name'];
                        $zip = $ac[3]['short_name'];
                        $country = $ac[2]['long_name'];
                    }

                    if (fnmatch('*cid=*', $url)) {
                        list($dum, $cid) = explode('cid=', $url, 2);
                    }

                    $ref        = isAke($row, 'reference', null);
                    $address    = isAke($row, 'vicinity', null);
                    $name       = isAke($row, 'name', null);
                    $rate       = isAke($row, 'rating', null);
                    $types      = isAke($row, 'types', []);
                    $photos     = isAke($row, 'photos', []);

                    if (!empty($types)) {
                        $type = current($types);
                    } else {
                        $type = null;
                    }

                    if (!empty($photos)) {
                        $photo = current($photos);
                        unset($photo['html_attributions']);
                        $photo['id'] = $photo['photo_reference'];
                        unset($photo['photo_reference']);
                    } else {
                        $photo = null;
                    }

                    $obj = [
                        'distance'  => $distances['km'] * 1000,
                        'type'      => $type,
                        'ref'       => $ref,
                        'cid'       => $cid,
                        'id'        => $id,
                        'name'      => $name,
                        'rate'      => $rate,
                        // 'street'    => $street,
                        // 'city'      => $city,
                        // 'zip'       => $zip,
                        // 'country'   => $country,
                        'website'   => isAke($dtab, 'website', null),
                        'address'   => $addr,
                        'phone'     => isAke($dtab, 'formatted_phone_number', null),
                        'avis'      => isAke($dtab, 'user_ratings_total', 0),
                        'photo'     => $photo
                    ];

                    $collection[] = $obj;
                }
            }

            $collection = coll($collection)->sortBy('distance')->toArray();
            $collection = array_values($collection);

            return $collection;
        }

        public function gpois($lat, $lng, $type = 'restaurant')
        {
            $url = "https://www.google.fr/search?tbm=map&fp=1&authuser=0&hl=fr&pb=!4m12!1m3!1d4073.5434512203738!2d$lng!3d$lat!2m3!1f0!2f0!3f0!3m2!1i1360!2i298!4f13.1!7i10!10b1!12m6!2m3!5m1!2b0!20e3!10b1!16b1!19m3!2m2!1i392!2i106!20m40!2m2!1i203!2i200!3m1!2i4!6m6!1m2!1i86!2i86!1m2!1i408!2i256!7m26!1m3!1e1!2b0!3e3!1m3!1e2!2b1!3e2!1m3!1e2!2b0!3e3!1m3!1e3!2b0!3e3!1m3!1e4!2b0!3e3!1m3!1e3!2b1!3e2!2b1!4b0!9b0!7e81!24m1!2b1!26m3!2m2!1i80!2i92!30m28!1m6!1m2!1i0!2i0!2m2!1i458!2i298!1m6!1m2!1i1310!2i0!2m2!1i1360!2i298!1m6!1m2!1i0!2i0!2m2!1i1360!2i20!1m6!1m2!1i0!2i278!2m2!1i1360!2i298!37m1!1e81&q=" . urlencode($type);

            $data = xCache('gpois.' . sha1(serialize(func_get_args()) . $type), function () use ($url) {
                return lib('geo')->dwn($url);
            }, strtotime('+6 month'));

            $data = str_replace("\n", "", $data);
            $data = str_replace("\r", "", $data);
            $data = str_replace("\t", "", $data);
            $data = str_replace(")]}'", "", $data);

            $data = json_decode($data, true);

            $tab = $data[0][1];

            array_shift($tab);

            $collection = [];

            foreach ($tab as $row) {
                $latRow     = $row[14][9][2];
                $lngRow     = $row[14][9][3];
                $distances  = distanceKmMiles($lng, $lat, $lngRow, $latRow);
                $name       = $row[14][11];
                $ws         = $row[14][7][0];
                $tel        = $row[14][3][0];
                $address    = $row[14][2][0] . ', ' . $row[14][2][1];
                $price      = $row[14][4][2];
                $rate       = floatval($row[14][4][7]);
                $avis       = (int) $row[14][4][8];
                $type       = $row[14][13][0];
                $hexa       = $row[14][10];
                $label      = $row[14][18];
                $link       = $row[14][4][3][0];

                if (strstr($link, 'ludocid')) {
                    $cid        = 'g' . Utils::cut('ludocid=', '#', $link);
                } else {
                    $cid        = 'g' . Utils::cut('.com/', '/', $row[6][1]);
                }

                $horaires   = $row[14][34][1];

                $abstract   = $row[14][32][0][1] . '. ' . $row[14][32][1][1];

                $obj = [
                    'distance'  => $distances['km'] * 1000,
                    'hexa'      => $hexa,
                    'cid'       => $cid,
                    'type'      => $type,
                    'label'     => $label,
                    'abstract'  => $abstract,
                    'name'      => $name,
                    'lat'       => $latRow,
                    'lng'       => $lngRow,
                    'website'   => $ws,
                    'phone'     => $tel,
                    'address'   => $address,
                    'rate'      => $rate,
                    'avis'      => $avis,
                    'price'     => $price,
                    'schedule'  => $this->schedule($horaires),
                    'img_in'    => 'http:' . $row[14][37][0][1][6][0],
                    'img_out'   => 'http:' . $row[14][37][0][2][6][0],
                ];

                if ($obj['img_in'] == 'http:') {
                    continue;
                } else {
                    $obj['img_in'] .= '&w=600&h=400';
                }

                if ($obj['img_out'] == 'http:') {
                    $obj['img_out'] = null;
                } else {
                    $obj['img_out'] .= '&w=600&h=400';
                }

                if ($obj['cid'] == 'g') {
                    $obj['cid'] = null;
                    Model::FeatureMap()->firstOrCreate(['fid' => 'g' . $hexa]);
                } else {
                    Model::FeatureMap()->firstOrCreate(['fid' => $cid]);
                }

                $collection[] = $obj;
            }

            $collection = coll($collection)->sortBy('distance')->toArray();
            $collection = array_values($collection);

            return $collection;
        }

        public function restoMic($lat, $lng, $start = 0, $pois = [])
        {
            $url = "http://vmrest.viamichelin.com/apir/2/FindPOI.json2/RESTAURANT/fra?source=RESGR&field=name;latitude;longitude;formated_address_line;formated_city_line;description;medias;address;ref_lieu;michelin_stars;price_min_gm21;price_max_gm21;currency;bib_gourmand;michelin_guide_selection;cooking_lib;phone;email;web;dts_id;facilities;meal_price;nota_bene;good_value_menu;interesting_wine_list;rating&center={$lng}:{$lat}&nb=100&sidx={$start}&filter=provider%20eq%20RESGR&facet.field=michelin_stars;bib_gourmand;good_value_menu&facet.mincount=0&obfuscation=false&ie=UTF-8&charset=UTF-8&callback=JSE.HTTP.asyncRequests[2].HTTPResponseLoaded&authKey=JSBS20110216111214120400892678&lg=fra";

            $html = $this->dwn($url);

            $seg = Utils::cut('HTTPResponseLoaded({', '}]})', $html);

            $json = '{' . $seg . '}]}';

            $tab = json_decode($json, true);

            if (!is_array($tab)) {
                return $this->restoMic($lat, $lng);
            }

            if (!isset($tab['poiList'])) {
                return $this->restoMic($lat, $lng);
            }

            if (!is_array($tab['poiList'])) {
                return $this->restoMic($lat, $lng);
            }

            $num = isAke($tab['searchInfos'], 'numFound', 100);

            if ($num > 100 + $start) {
                return $this->restoMic($lat, $lng, $start + 100, array_merge($pois, $tab['poiList']));
            }

            return array_merge($pois, $tab['poiList']);
        }

        public function hotelMic($lat, $lng, $start = 0, $pois = [])
        {
            $url = "http://vmrest.viamichelin.com/apir/2/FindPOI.json2/HOTEL/fra?source=ASSO_BOOKING;ASSO_BOOKING_BB;ASSO_BOOKING_RESIDENCE;ASSO_BOOKING_CAMPING;CHAIN_ACCOR_HOTELS&field=name;latitude;longitude;formated_address_line;formated_city_line;description;medias;address;ref_lieu;hotel_stars;price_min_gm21;currency;avg_rating;nb_reviews;web;number_of_rooms;activities;bathroom;food_amenities;facilities_general;view;services;room_amenities;technologies;provider;michelin_guide_selection_hotel;rooms&center={$lng}:{$lat}&nb=100&sidx={$start}&filter=(provider%20eq%20ASSO_BOOKING%20or%20provider%20eq%20ASSO_BOOKING_BB%20or%20provider%20eq%20ASSO_BOOKING_RESIDENCE%20or%20provider%20eq%20ASSO_BOOKING_CAMPING%20or%20provider%20eq%20CHAIN_ACCOR_HOTELS)%20AND%20price_min_gm21%20gt%200%20AND%20reservable%20eq%201&facet.field=hotel_stars;accommodation_type&facet.mincount=0&facet.filter=NET:services%20eq%20NET;PARK:facilities_general%20eq%20PARK;SHUTTLE-AIR:services%20eq%20SHUTTLE-AIR;SPORT-FITNESS:activities%20eq%20SPORT-FITNESS;NOSMOK:room_amenities%20eq%20NOSMOK;POOL:activities%20eq%20POOL;SPA:bathroom%20eq%20SPA;FAMLYROOM:facilities_general%20eq%20FAMLYROOM;PET:facilities_general%20eq%20PET;DISABLEDHELP:facilities_general%20eq%20DISABLEDHELP;REST:facilities_general%20eq%20REST;[0,49[:price_min_gm21%20lt%2049;[50,99[:price_min_gm21%20ge%2050%20AND%20price_min_gm21%20lt%2099;[100,149[:price_min_gm21%20ge%20100%20AND%20price_min_gm21%20lt%20149;[150,199[:price_min_gm21%20ge%20150%20AND%20price_min_gm21%20lt%20199;[200,Infinity[:price_min_gm21%20ge%20200;1:michelin_guide_selection_hotel%20eq%201&obfuscation=false&ie=UTF-8&charset=UTF-8&callback=JSE.HTTP.asyncRequests[8].HTTPResponseLoaded&authKey=JSBS20110216111214120400892678&lg=fra";

            $html = $this->dwn($url);

            $seg = Utils::cut('HTTPResponseLoaded({', '}]})', $html);

            $json = '{' . $seg . '}]}';

            $tab = json_decode($json, true);

            if (!is_array($tab)) {
                return $this->hotelMic($lat, $lng);
            }

            if (!isset($tab['poiList'])) {
                return $this->hotelMic($lat, $lng);
            }

            if (!is_array($tab['poiList'])) {
                return $this->hotelMic($lat, $lng);
            }

            $num = isAke($tab['searchInfos'], 'numFound', 100);

            if ($num > 100 + $start) {
                return $this->hotelMic($lat, $lng, $start + 100, array_merge($pois, $tab['poiList']));
            }

            return array_merge($pois, $tab['poiList']);
        }

        public function dwnCache($url, $max = null)
        {
            return xCache('url.' . sha1($url), function () use ($url) {
                return lib('geo')->dwn($url);
            }, $max);
        }

        public function ta($lat, $lng)
        {
            /*http://www.tripadvisor.fr/GMapsLocationController?Action=update&from=Restaurants&g=60763&geo=60763&mapProviderFeature=ta-maps-gmaps3&validDates=false&mc=40.712912707912366,-74.0115048244395&mz=14&mw=1320&mh=420&pinSel=v2&origLocId=60763&sponsors=&finalRequest=false&includeMeta=false&trackPageView=false

            http://www.tripadvisor.fr/GMapsLocationController?Action=info&from=Restaurants&g=7207209&parent=&originalloc=60763&mapProviderFeature=ta-maps-gmaps3&infoType=miniHover
            */

            $json = $this->dwnCache("http://www.tripadvisor.fr/GMapsLocationController?Action=update&from=Restaurants&g=60763&geo=60763&mapProviderFeature=ta-maps-gmaps3&validDates=false&mc={$lat},{$lng}&mz=17&mw=1320&mh=420&pinSel=v2&origLocId=60763&sponsors=&finalRequest=false&includeMeta=false&trackPageView=false");

            $tab = json_decode($json, true);

            $hotels = isAke($tab, 'hotels', []);
            $restaurants = isAke($tab, 'restaurants', []);
            $attractions = isAke($tab, 'attractions', []);

            $json = $this->dwnCache("http://www.tripadvisor.fr/GMapsLocationController?Action=update&from=Restaurants&g=60763&geo=60763&mapProviderFeature=ta-maps-gmaps3&validDates=false&mc={$lat},{$lng}&mz=10&mw=1320&mh=420&pinSel=v2&origLocId=60763&sponsors=&finalRequest=false&includeMeta=false&trackPageView=false");

            $tab = json_decode($json, true);

            $hotels = array_merge($hotels, isAke($tab, 'hotels', []));
            $restaurants = array_merge($restaurants, isAke($tab, 'restaurants', []));
            $attractions = array_merge($attractions, isAke($tab, 'attractions', []));

            $collection = [
                'hotels' => $hotels,
                'restaurants' => $restaurants,
                'attractions' => $attractions
            ];

            return $collection;
        }

        public function ghexa2($hexa)
        {
            /*
                https://maps.google.fr/maps/api/js/ApplicationService.GetEntityDetails?pb=!1m2!1m1!4s13684705610450531450!2m2!1sfr!2sUS!6e1

                https://maps.google.fr/maps/api/js/ApplicationService.GetEntityDetails?pb=!1m2!1m1!1s0x808f7e0637de465b:0x405d498b744e53be!2m2!1sfr!2sUS!6e1

                https://maps.google.fr/maps/api/js/ApplicationService.GetEntityDetails?pb=!1m2!1m1!1s0x47e6722d5e69ac0f:0x81c5484276a4fcc0!2m2!1sfr!2sUS!6e1

                https://maps.googleapis.com/maps/api/js/jsonp/ApplicationService.GetEntityDetails?pb=!1m2!1m1!1s0x47e671cced0293a1:0xaabccf9f46087765!2m2!1sfr!2sUS!6e1

                https://maps.google.fr/maps/api/js/GeocodeService.Search?5m2&1d48.842849028882064&2d2.3127072375427815&7sUS&9sfr

                http://maps.googleapis.com/maps/api/js/GeocodeService.Search?5m2&1d48.84343508282214&2d2.317084602655086&7sUS&9sfr&callback=_xdc_._kyvpyb&token=88242

                https://maps.googleapis.com/maps/api/js/PlaceService.GetPlaceDetails?2sfr&8sEiIyMCBSdWUgZGUgbGEgQm91cnNlLCBQYXJpcywgRnJhbmNl&10e3&key=AIzaSyDIJ9XX2ZvRKCJcFRrl-lRanEtFUow4piM&callback=_xdc_._wkmuwl&token=109795
            */
        }

        public function ghexa($hexa)
        {
            $data = $this->dwnCache("https://www.google.fr/maps/preview/entity?authuser=0&hl=fr&pb=!1m18!1s$hexa!3m12!1m3!1d16294.179092557824!2d2.3078046803197836!3d48.86649036092792!2m3!1f0!2f0!3f0!3m2!1i1360!2i298!4f13.1!4m2!3d48.87284474327185!4d2.3193597793579213!5e4!6shotel!12m3!2m2!1i392!2i106!13m40!2m2!1i203!2i100!3m1!2i4!6m6!1m2!1i86!2i86!1m2!1i408!2i256!7m26!1m3!1e1!2b0!3e3!1m3!1e2!2b1!3e2!1m3!1e2!2b0!3e3!1m3!1e3!2b0!3e3!1m3!1e4!2b0!3e3!1m3!1e3!2b1!3e2!2b1!4b0!9b0!14m4!1swApXVtLhKMiaU9OMuNAP!3b1!7e81!15i10555!15m1!2b1!22m1!1e81&pf=p");

            $data = str_replace("\n", "", $data);
            $data = str_replace("\r", "", $data);
            $data = str_replace("\t", "", $data);
            $data = str_replace(")]}'", "", $data);

            $data = json_decode($data, true);

            $row = $data[0][1][0];

            $latRow     = $row[14][9][2];
            $lngRow     = $row[14][9][3];
            $name       = $row[14][11];
            $ws         = $row[14][7][0];
            $tel        = $row[14][3][0];
            $address    = $row[14][2][0] . ', ' . $row[14][2][1];
            $price      = $row[14][4][2];
            $rate       = floatval($row[14][4][7]);
            $avis       = (int) $row[14][4][8];
            $type       = $row[14][13][0];
            $hexa       = $row[14][10];
            $label      = $row[14][18];
            $link       = $row[14][4][3][0];

            if (strstr($link, 'ludocid')) {
                $cid        = 'g' . Utils::cut('ludocid=', '#', $link);
            } else {
                $cid        = 'g' . Utils::cut('.com/', '/', $row[6][1]);
            }

            $horaires   = $row[14][34][1];

            $abstract   = $row[14][32][0][1] . '. ' . $row[14][32][1][1];

            $obj = [
                'coords'    => $data[3][0][0],
                'hexa'      => $hexa,
                'cid'       => $cid,
                'type'      => $type,
                'label'     => $label,
                'abstract'  => $abstract,
                'name'      => $name,
                'lat'       => $latRow,
                'lng'       => $lngRow,
                'website'   => $ws,
                'phone'     => $tel,
                'address'   => $address,
                'rate'      => $rate,
                'avis'      => $avis,
                'price'     => $price,
                'schedule'  => $this->schedule($horaires),
                'img_in'    => 'http:' . $row[14][37][0][1][6][0],
                'img_out'   => 'http:' . $row[14][37][0][2][6][0],
            ];

            return $obj;
        }

        public function pp($lat1, $lng1, $lat2, $lng2)
        {
            $json = $this->dwnCache("https://route.cit.api.here.com/routing/7.2/calculateroute.json?language=fr-fr&waypoint0={$lat1}%2C{$lng1}&waypoint1={$lat2}%2C{$lng2}&mode=fastest%3Bpedestrian&app_id=DemoAppId01082013GAL&app_code=AJKnXv84fjrb0KIHawS0Tg");

            $tab = json_decode($json, true);

            $subtype = isAke($tab, 'subtype', null);

            if ($subtype == 'NoRouteFound') {
                return null;
            }

            $response = isAke($tab, 'response', []);
            $route = isAke($response, 'route', []);

            if (empty($route)) {
                return null;
            }

            $route = current($route);

            $leg = isAke($route, 'leg', []);

            if (empty($leg)) {
                return null;
            }

            $leg = current($leg);

            $steps = isAke($leg, 'maneuver', []);
            $summary = isAke($route, 'summary', []);

            unset($summary['flags']);
            unset($summary['_type']);

            return ['steps' => $steps, 'summary' => $summary];
        }

        public function giti($hex1, $hex2, $addr1, $addr2)
        {
            $url = "https://www.google.fr/maps/preview/directions?authuser=0&hl=fr&pb=!1m5!1s".urlencode($addr1)."!2s$hex1!3m2!3d48.8681105!4d2.3412484!1m5!1s".urlencode($addr2)."!2s$hex27!3m2!3d48.8424433!4d2.3707872!3m12!1m3!1d24009.883317948053!2d2.3199650143376322!3d48.8538926236275!2m3!1f0!2f0!3f0!3m2!1i1440!2i439!4f13.1!6m6!2m3!5m1!2b0!20e3!10b1!16b1!8m0!15m4!1siHxcVq_OBoWRPfzVscgN!4m1!2i4975!7e81!20m28!1m6!1m2!1i0!2i0!2m2!1i458!2i439!1m6!1m2!1i1390!2i0!2m2!1i1440!2i439!1m6!1m2!1i0!2i0!2m2!1i1440!2i20!1m6!1m2!1i0!2i419!2m2!1i1440!2i439";

            $data = $this->dwnCache($url);

            $data = str_replace("\n", "", $data);
            $data = str_replace("\r", "", $data);
            $data = str_replace("\t", "", $data);
            $data = str_replace(")]}'", "", $data);

            $data = json_decode($data, true);

            // vd($data);
        }

        public function nokia($lat, $lng, $cat = 'transport')
        {
            $url = "http://demo.places.nlp.nokia.com/places/v1/discover/explore?cat=$cat&at={$lat},{$lng}&app_id=DemoAppId01082013GAL&app_code=AJKnXv84fjrb0KIHawS0Tg&accept=application/json";

            $json = $this->dwnCache($url);

            $tab = json_decode($json, true);

            if (isset($tab['results'])) {
                if (isset($tab['results']['items'])) {
                    if (is_array($tab['results']['items'])) {
                        if (!empty($tab['results']['items'])) {
                            return coll($tab['results']['items'])->sortBy('distance')->toArray();
                        }
                    }
                }
            }

            return [];
        }

        public function filterSuggest($q)
        {
            $url = "http://ccu.viamichelin.com/recoa/r?&nks=1&fe=3&cb=COM&lf=fra&nb=50&charset=UTF-8&callBack=addr&version=&lang=fra&ks=" . urlencode($q);
            // $url = "http://open.mapquestapi.com/nominatim/v1/search.php?g=1&key=Kmjtd|luub290anq%2C8w%3Do5-lzznh&format=json&q=" . urlencode($q);

            $json = $this->dwnCache($url);

            $json = str_replace(['addr(', '"})', "\r", "\n", "\t"], ['', '"}', '', '', ''], $json);
            $tab = json_decode($json, true);

            dd($json);
        }

        public function pj($q)
        {
            $cmd = "curl 'http://dsk3ufaxut-dsn.algolia.net/1/indexes/PROD_OuPub/query?x-algolia-api-key=30a9c866c7245bafc39b9d3612ca1a95&x-algolia-application-id=DSK3UFAXUT&x-algolia-agent=Algolia%20for%20vanilla%20JavaScript%203.9.3' -H 'Host: dsk3ufaxut-dsn.algolia.net' -H 'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:42.0) Gecko/20100101 Firefox/42.0' -H 'Accept: application/json' -H 'Accept-Language: fr,fr-FR;q=0.8,en-US;q=0.5,en;q=0.3' -H 'Accept-Encoding: gzip, deflate' -H 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8' -H 'Referer: http://www.pagesjaunes.fr/' -H 'Origin: http://www.pagesjaunes.fr' -H 'Connection: keep-alive' -H 'Pragma: no-cache' -H 'Cache-Control: no-cache' --data '{\"params\":\"query=".urlencode($q)."&getRankingInfo=1&hitsPerPage=5&allowTyposOnNumericTokens=0&useQueryEqualsOneAttributeInRanking=0&facets=&facetFilters=\"}'";
        }

        public function gPics($id = '110447521304598288426')
        {
            $html = $this->dwn("https://www.google.com/maps/contrib/$id/photos?nogmmr=1");die('<textarea>' . $html . '</textarea>');
            $url = urldecode(Utils::cut('<A HREF="', '"', $html));
            $html = $this->dwn($url);
            $url = urldecode(Utils::cut('<A HREF="', '"', $html));
            $html = $this->dwn($url);
            die('<textarea>' . $html . '</textarea>');
        }

        public function gPhoto($lat, $lng)
        {
            $data = $this->dwnCache("https://www.google.fr/maps/preview/photo?authuser=0&hl=fr&pb=!1e3!5m36!2m2!1i203!2i100!3m1!2i4!7m26!1m3!1e1!2b0!3e3!1m3!1e2!2b1!3e2!1m3!1e2!2b0!3e3!1m3!1e3!2b0!3e3!1m3!1e4!2b0!3e3!1m3!1e3!2b1!3e2!2b1!4b1!8m2!1m1!1e2!9b0!6m3!1sRCp5VoOROMXbUeu8hvAH!7e81!15i11167!9m3!1d0!2d$lng!3d$lat!10d25");

            $data = str_replace("\n", "", $data);
            $data = str_replace("\r", "", $data);
            $data = str_replace("\t", "", $data);
            $data = str_replace(")]}'", "", $data);

            $data = json_decode($data, true);

            $obj = [
                'id_pano' => $data[0][0][0],
                'url' => $data[0][0][6][0],
                'address' => $data[0][0][6][1],
            ];

            return $obj;
        }

        public function go($lat1, $lng1, $lat2, $lng2, $at = 0)
        {
            $data = $this->dwnCache("https://www.waze.com/row-RoutingManager/routingRequest?from=x%3A$lng1+y%3A$lat1&to=x%3A$lng2+y%3A$lat2&at=$at&returnJSON=true&returnGeometries=false&returnInstructions=true&timeout=60000&nPaths=3&clientVersion=4.0.0&options=AVOID_TRAILS%3At%2CALLOW_UTURNS%3At");

            $data = json_decode($data, true);

            dd($data);
        }
    }
// curl 'https://roadtrippers.com/api/proxy/reverse_geocode' -H 'Accept: application/json, text/javascript, */*; q=0.01' -H 'Accept-Encoding: gzip, deflate' -H 'Accept-Language: fr,fr-FR;q=0.8,en-US;q=0.5,en;q=0.3' -H 'Cache-Control: no-cache' -H 'Connection: keep-alive' -H 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8' -H 'Cookie: acquisition_date=2015-12-03+11%3A57%3A49+%2B0000; _roadtrippers_production_session=BAh7B0kiD3Nlc3Npb25faWQGOgZFVEkiJWQ1MmE4NmQyZDdjYWE3ZTNlYWVlZTQxMGU5YjYyMWE5BjsAVEkiEF9jc3JmX3Rva2VuBjsARkkiMUJ2RUp4a2crZXp0QkhSRDdIR0J0WTRQdVQ0NEF5VFEvNFMxN0F4b21yL1k9BjsARg%3D%3D--b62e1e14839552a3242c5c232615e9bdafcfbf4e; mp_1a019bbcdfcca9c1e1456161619492fb_mixpanel=%7B%22distinct_id%22%3A%20%2215167b4a6fd6b4-0a7e36f7286216-77276750-13c680-15167b4a6fea6d%22%2C%22%24search_engine%22%3A%20%22google%22%2C%22%24initial_referrer%22%3A%20%22https%3A%2F%2Fwww.google.fr%22%2C%22%24initial_referring_domain%22%3A%20%22www.google.fr%22%2C%22rt_distinct_id%22%3A%20%2215167b4a6fd6b4-0a7e36f7286216-77276750-13c680-15167b4a6fea6d%22%7D; _ga=GA1.2.837615954.1449143871; referrer=https://www.google.fr; _gat=1; s_sess=%20s_cc%3Dtrue%3B%20s_sq%3D%3B; s_pers=%20s_fid%3D4693FEC3F5D66C54-3A0F4242C44E97D7%7C1512302355228%3B%20s_getnr%3D1449143955307-New%7C1512215955307%3B%20s_nrgvo%3DNew%7C1512215955341%3B; _cb_ls=1; _chartbeat2=CtSoWEDy59ZcDe9MWa.1449143872211.1449143872211.1' -H 'Host: roadtrippers.com' -H 'Pragma: no-cache' -H 'Referer: https://roadtrippers.com/map?lat=40.80972&lng=-96.67528&z=5&a2=p!18*1' -H 'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:42.0) Gecko/20100101 Firefox/42.0' -H 'X-CSRF-Token: BvEJxkg+eztBHRD7HGBtY4PuT44AyTQ/4S17Axomr/Y=' -H 'X-Requested-With: XMLHttpRequest' --data 'location%5Blongitude%5D=2.3488&location%5Blatitude%5D=48.85341'

    /*https://roadtrippers.com/api/v2/pois/discover?segments[]=bed-breakfasts-inns&segments[]=boutique-hotels&segments[]=holiday-inn&segments[]=hostels&segments[]=hotels&segments[]=resorts&segments[]=motels&segments[]=unique-stays&sw_corner=1.8560028076171875%2C48.762978520066326&ne_corner=2.83721923828125%2C48.95452280895973&offset=0&page_size=500*/
/*
https://roadtrippers.com/api/v2/pois/discover?segments[]=bars-drinks&segments[]=american-food&segments[]=fast-food&segments[]=coffee-tea&segments[]=delis-bakeries&segments[]=diners-breakfast-spots&segments[]=sweet-tooth&segments[]=restaurants&segments[]=asian-food&segments[]=latin-american-food&segments[]=european-food&segments[]=african-food&segments[]=middle-eastern-food&segments[]=australian-food&segments[]=vegetarian-health-food&segments[]=wineries-breweries-distilleries&sw_corner=1.8560028076171875%2C48.762978520066326&ne_corner=2.83721923828125%2C48.95452280895973&offset=0&page_size=500

https://roadtrippers.com/api/v2/pois/discover?segments%5B%5D=amusement-parks&segments%5B%5D=childrens-attractions&segments%5B%5D=fall-attractions&segments%5B%5D=museums&segments%5B%5D=offbeat-attractions&segments%5B%5D=top-attractions&segments%5B%5D=cultural-theme-tours&segments%5B%5D=sightseeing-tours&segments%5B%5D=cruises-sailing-water-activities&segments%5B%5D=winter-attractions&segments%5B%5D=zoos-aquariums&sw_corner=1.8560028076171875%2C48.762978520066326&ne_corner=2.83721923828125%2C48.95452280895973&offset=25&page_size=200&is_chain=&allows_pets=

https://roadtrippers.com/api/v2/pois/discover?segments%5B%5D=antiques&segments%5B%5D=books-music&segments%5B%5D=crafts-handmade&segments%5B%5D=general-goods&segments%5B%5D=specialty-shops&segments%5B%5D=clothing&segments%5B%5D=outfitters&segments%5B%5D=kids&segments%5B%5D=gifts-souvenirs&segments%5B%5D=groceries&segments%5B%5D=malls-shopping-areas&segments%5B%5D=quirky-shops&segments%5B%5D=flea-markets-pawn-shops&sw_corner=1.8560028076171875%2C48.762978520066326&ne_corner=2.83721923828125%2C48.95452280895973&offset=25&page_size=200&is_chain=&allows_pets=
*/

/*http://mapserver.superpages.com/mapbasedsearch/spSearchProxyLight?app=sp&sw=1&i=8&a=hotel&c=48.882725&d=2.326351&r=100.000000&ppc=1&pi=0&FP=map&SRC=MapBased

http://www.ukphonebook.com/ajax/proxy?callback=jQuery18207940775828896017_1449312033095&method=geo_code&output=json&text=grill&hint=y&uen=&non_uk=y&_=1449312064883


http://www.booking.com/markers_on_map_immutable?aid=304142;label=gen173nr-15CAEoggJCAlhYSDNiBW5vcmVmaE2IAQGYAQ24AQTIAQTYAQPoAQE;dcid=1;aid=304142;dest_id=704492;dest_type=hotel;sr_id=;ref=hotel;limit=100;stype=1;lang=fr;get_hotel_details=1;cs=0;u=0;dbc=1;hr=3;b_group={%22num_rooms%22%3A1};BBOX=6.30989134311676,48.40983122710533,10.01503050327301,48.8220089197459

http://www.booking.com/hotels_onmap_detail?label=gen173nr-15CAEoggJCAlhYSDNiBW5vcmVmaE2IAQGYAQ24AQTIAQTYAQPoAQE;dcid=4;lang=fr;detail=0;currency=EUR;av=1;c=1;stype=1;cc1=;aid=304142;img_size=90;localize_format=1%20;rr=1%20;rp=1;fav_hot_mins=1;g=0;b_group={%22num_rooms%22%3A1};hotel_id=422847
*/
