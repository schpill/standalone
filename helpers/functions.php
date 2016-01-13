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

    use Thin\Container;
    use Thin\Arrays;
    use Thin\Inflector;
    use Thin\Utils;
    use Thin\Database\Collection;
    use Thin\Bucket;
    use Thin\Session;
    use Thin\Option;
    use Thin\Phonetic as Phonetik;
    use Thin\Instance;
    use Thin\Config;
    use Thin\Exception;
    use Thin\Sessionstore;
    use Thin\File;
    use Thin\Model;
    use Thin\Em;
    use Thin\Fly;
    use Thin\Mail\Message;
    use Thin\Mail\Mandrill;
    use Illuminate\Database\Capsule\Manager as DB;
    use Dbjson\Dbjson as DBJ;
    use Dbredis\Caching;
    use Elasticsearch\Client as ESC;
    use Swift_Message as SM;
    use Zelift\Request as ZRequest;
    use MongoClient as MC;
    use MongoCollection as MColl;
    use MongoRegex as MRgx;
    use Phalcon\Cache\Frontend\Data as DataFrontend;
    use Phalcon\Cache\Backend\Apc as ApcCache;
    use Phalcon\DI\FactoryDefault as DI;
    use Monolog\Logger;
    use Monolog\Handler\StreamHandler as SH;
    use Monolog\Handler\RedisHandler as RH;
    use Monolog\Formatter\LineFormatter as LF;


    if (!function_exists('setResource')) {
        function getResources($r = null)
        {
            static $resources;

            if (is_null($r)) {
                if (!isset($resources)) {
                    $resources = [];
                }
            } else {
                $resources = $r;
            }

            return $resources;
        }

        function setResource($key, $value)
        {
            $resource =  isAke(getResources(), $key, curl_init());

            curl_setopt(
                $resource,
                CURLOPT_PRIVATE,
                serialize($value)
            );

            $resources[$key] = $resource;

            getResources($resources);

            return $resource;
        }

        function getResource($key)
        {
            $resource = isAke(getResources(), $key, curl_init());

            return unserialize(
                curl_getinfo(
                    $resource,
                    CURLINFO_PRIVATE
                )
            );
        }
    }

    if (!function_exists('isCallable')) {
        function isCallable($value)
        {
            return !is_string($value) && is_callable($value);
        }
    }

    if (!function_exists('dic')) {
        function dic()
        {
            return lib('di')->instance();
        }
    }

    if (!function_exists('glue')) {
        function glue($ns = null)
        {
            $ns = is_null($ns) ? 'core' : $ns;

            return lib('glue', [$ns]);
        }
    }

    if (!function_exists('construct')) {
        function construct($class, $args = [])
        {
            $reflector = new \ReflectionClass($class);

            return $reflector->newInstanceArgs($args);
        }
    }

    if (!function_exists('dbCache')) {
        function dbCache($ns = null)
        {
            $ns = is_null($ns) ? 'db.cache' : 'db.' . $ns;

            return Caching::instance($ns);
        }
    }

    if (!function_exists('setYearCost')) {
        function setYearCost($object, $price)
        {
            return opt($object)->set('YEAR_COST', $price);
        }

        function getYearCost($object)
        {
            return opt($object)->get('YEAR_COST');
        }
    }

    if (!function_exists('setUnitCost')) {
        function setUnitCost($object, $price)
        {
            return opt($object)->set('UNIT_COST', $price);
        }

        function getUnitCost($object)
        {
            return opt($object)->get('UNIT_COST');
        }
    }

    if (!function_exists('getOptionMarketing')) {
        function getOptionMarketing($code, $value = null)
        {
            return bigDb('optionmarketing')
            ->firstOrCreate([
                'code' => Inflector::upper($code),
                'value' => Inflector::upper($value)
            ]);
        }
    }

    if (!function_exists('lib')) {
        function loadLib($lib, $args = null)
        {
            try {
                lib($lib, $args);

                return true;
            } catch (Excexption $e) {
                return false;
            }
        }

        function lib($lib, $args = null)
        {
            $lib    = strtolower(Inflector::uncamelize($lib));
            $script = str_replace('_', DS, $lib) . '.php';

            if (fnmatch('*_*', $lib)) {
                $class  = 'Thin\\' . str_replace('_', '\\', $lib);
                $tab    = explode('\\', $class);
                $first  = $tab[1];
                $class  = str_replace('Thin\\' . $first, 'Thin\\' . ucfirst($first) . 'Lib', $class);

                if (count($tab) > 2) {
                    for ($i = 2; $i < count($tab); $i++) {
                        $seg    = trim($tab[$i]);
                        $class  = str_replace('\\' . $seg, '\\' . ucfirst($seg), $class);
                    }
                }
            } else {
                $class = 'Thin\\' . ucfirst($lib) . 'Lib';
            }

            $file = VENDORS_PATH . DS . 'schpill/components/helpers' . DS . $script;

            if (file_exists($file)) {
                require_once $file;

                if (empty($args)) {
                    return new $class;
                } else {
                    if (!is_array($args)) {
                        if (is_string($args)) {
                            if (fnmatch('*,*', $args)) {
                                $args = explode(',', str_replace(', ', ',', $args));
                            } else {
                                $args = [$args];
                            }
                        } else {
                            $args = [$args];
                        }
                    }

                    $methods = get_class_methods($class);

                    if (in_array('instance', $methods)) {
                        return call_user_func_array([$class, 'instance'], $args);
                    } else {
                        return construct($class, $args);
                    }
                }
            }

            if (class_exists('Thin\\' . $lib)) {
                $c = 'Thin\\' . $lib;

                return new $c;
            }

            if (class_exists($lib)) {
                return new $lib;
            }

            throw new Exception("The library $class does not exist.");
        }

        function raw($db, $table)
        {
            if ($db != SITE_NAME) {
                $fn = Inflector::camelize($db . '_' . $table);
            } else {
                $fn = ucfirst(strtolower($table));
            }

            $code = 'namespace Thin; function ' . $fn . '() {return \Thin\Raw::' . $fn . '();}';

            if (!function_exists('\\Thin\\' . $fn)) {
                eval($code);
            } else {
                throw new Exception('The function ' . $fn . ' ever exists.');
            }
        }

        function flib($lib, $function, array $args = [])
        {
            return call_user_func_array([lib($lib), $function], $args);
        }
    }

    if (!function_exists('provider')) {
        function appli()
        {
            static $i;

            if (!isset($i)) {
                $i =  lib('app');
                $i = $i->setInstance($i);
            }

            return $i;
        }

        function loadProvider($lib, $args = null)
        {
            try {
                provider($lib, $args);

                return true;
            } catch (Excexption $e) {
                return false;
            }
        }

        function provider($lib, $args = null)
        {
            $lib    = strtolower(Inflector::uncamelize($lib));
            $script = str_replace('_', DS, $lib) . '.php';

            if (fnmatch('*_*', $lib)) {
                $class  = 'Thin\\' . str_replace('_', '\\', $lib);
                $tab    = explode('\\', $class);
                $first  = $tab[1];
                $class  = str_replace('Thin\\' . $first, 'Thin\\' . ucfirst($first) . 'Provider', $class);

                if (count($tab) > 2) {
                    for ($i = 2; $i < count($tab); $i++) {
                        $seg    = trim($tab[$i]);
                        $class  = str_replace('\\' . $seg, '\\' . ucfirst($seg), $class);
                    }
                }
            } else {
                $class = 'Thin\\' . ucfirst($lib) . 'Provider';
            }

            $file = APPLICATION_PATH . DS . 'providers' . DS . $script;

            if (File::exists($file)) {
                require_once $file;

                $methods = get_class_methods($class);

                $a = [lib('app')->getInstance()];

                if (in_array('boot', $methods)) {
                    call_user_func_array([$class, 'boot'], $a);
                }

                if (empty($args)) {
                    if (in_array('register', $methods)) {
                        call_user_func_array([$class, 'register'], $a);
                    }
                } else {
                    if (!is_array($args)) {
                        if (is_string($args)) {
                            if (fnmatch('*,*', $args)) {
                                $args = explode(',', str_replace(', ', ',', $args));
                            } else {
                                $args = [$args];
                            }
                        } else {
                            $args = [$args];
                        }
                    }

                    $a = array_merge($a, $args);

                    if (in_array('register', $methods)) {
                        call_user_func_array([$class, 'register'], $a);
                    }
                }

                return true;
            }

            throw new Exception("The provider $class does not exist.");
        }
    }

    if (!function_exists('searchGeo')) {
        function searchGeo($what, $limit = 1000)
        {
            $what = (string) $what;

            $collection = $cities = $department = $regions = [];

            if (is_numeric($what)) {
                $cities = Model::City()->where(['zip', 'LIKE', $what . '%'])->exec();

                if (strlen($what) < 3) {
                    $departments = Model::Department()->where(['code', 'LIKE', $what . '%'])->exec();
                }
            } else {
                $cities         = Model::City()->where(['name', 'LIKE', $what . '%'])->exec();
                $departments    = Model::Department()->where(['name', 'LIKE', $what . '%'])->exec();
                $regions        = Model::Region()->where(['name', 'LIKE', $what . '%'])->exec();
            }

            if (!empty($cities)) {
                foreach ($cities as $city) {
                    if (count($collection) >= $limit) {
                        return $collection;
                    }

                    $item = [
                        'type'      => 'city',
                        'city_id'   => $city['id'],
                        'name'      => ucwords($city['name']),
                        'zip'       => $city['zip']
                    ];

                    array_push($collection, $item);
                }
            }

            if (!empty($departments)) {
                foreach ($departments as $department) {
                    if (count($collection) >= $limit) {
                        return $collection;
                    }

                    $item = [
                        'type'          => 'department',
                        'department_id' => $department['id'],
                        'name'          => ucwords($department['name']),
                        'code'          => $department['code']
                    ];

                    array_push($collection, $item);
                }
            }

            if (!empty($regions)) {
                foreach ($regions as $region) {
                    if (count($collection) >= $limit) {
                        return $collection;
                    }

                    $item = [
                        'type'      => 'region',
                        'region_id' => $region['id'],
                        'name'      => ucwords($region['name'])
                    ];

                    array_push($collection, $item);
                }
            }

            return $collection;
        }
    }

    if (!function_exists('searchCities')) {
        function searchCities($what, $limit = 1000)
        {
            $what = (string) $what;

            $collection = $cities = [];

            if (is_numeric($what)) {
                $cities = Model::City()->where(['zip', 'LIKE', $what . '%'])->order('population', 'DESC')->exec();
            } else {
                $cities = Model::City()->where(['name', 'LIKE', $what . '%'])->order('population', 'DESC')->exec();
            }

            if (!empty($cities)) {
                foreach ($cities as $city) {
                    if (count($collection) >= $limit) {
                        return $collection;
                    }

                    $item = [
                        'id' => $city['id'],
                        'name' => ucwords($city['name']),
                        'zip' => $city['zip']
                    ];

                    array_push($collection, $item);
                }
            }

            return $collection;
        }
    }

    if (!function_exists('makeOfferIn')) {
        function makeOfferIn($basket, $universe, $peopleId, $companyId, $companyaddress_id, $delivery = [], $geo = [])
        {
            $collection = $collectionPush = [];
            $company    = Model::Company()->find($companyId);
            $people     = Model::People()->find($peopleId);

            if ($company && $people) {
                $companyStatus  = isAke($company->assoc(), 'status_id', getStatus('UNCHECKED'));
                $offers         = [];

                if (!empty($basket)) {
                    $i = 0;
                    foreach ($basket as $article) {
                        $dir = realpath(APPLICATION_PATH . DS . '..' . DS . 'public' . DS . 'files' . DS . $universe . DS .'users' . DS . $peopleId . DS . 'tmp_basket' . DS . $i);
                        if ($dir) {
                            $files = glob($dir . DS . '*');

                            if (!empty($files)) {
                                foreach ($files as $file) {
                                    $article['attach'][] = $file;
                                }
                            }
                        }

                        $market = 0;
                        $item_id = isake($article, 'item_id', 0);

                        if (0 >= $item_id) {
                            $market         = isAke($article, 'market', 0);
                        } else {
                            $family         = repo('segment')->getFamilyfromItem($item_id);

                            if (!empty($family)) {
                                $market = current($family);

                                $market = isAke($market, 'id', 0);
                            }
                        }

                        if (0 < $market) {
                            unset($article['family']);
                            $offers[$market][] = $article;
                        }

                        $i++;
                    }
                }

                if (!empty($offers)) {
                    foreach ($offers as $idMarket => $articles) {
                        $statusOffer = $companyStatus == getStatus('UNCHECKED') ? getStatus('WAIT') : getStatus('OK');

                        $offer = bigDb('offerin')->create([
                            'global'            => true,
                            'companyaddress_id' => $companyaddress_id,
                            'delivery_date'     => isAke($delivery, 'date', null),
                            'delivery_type'     => isAke($delivery, 'type', null),
                            'delivery_moment'   => isAke($delivery, 'moment', null),
                            'expiration'        => strtotime('+1 month'),
                            'universe'          => $universe,
                            'market'            => (int) $idMarket,
                            'status_id'         => (int) $statusOffer,
                            'date'              => time(),
                            'zip'               => $company->zip,
                            'people_id'         => (int) $peopleId,
                            'company_id'        => $companyId
                        ])->save();

                        foreach ($geo as $g) {
                            bigDb('offeringeo')->create([
                                'offerin_id'                => $offer->id,
                                'type'                      => isAke($g, 'type'),
                                isAke($g, 'type') . '_id'   => isAke($g, 'id'),
                                'range'                     => isAke($g, 'range')
                            ])->save();
                        }

                        foreach ($articles as $art) {
                            $options_comp_name  = isAke($art, 'options_comp_name');
                            $options_comp_val   = isAke($art, 'options_comp_val');
                            $item_id            = isAke($article, 'item_id', 0);

                            if (0 == $item_id) {
                                $statusOffer    = (int) getStatus('ADV');
                                $offer          = $offer->setStatusId($statusOffer)->save();
                            }

                            if (!empty($options_comp_name) && !empty($options_comp_val)) {
                                /* On cherche les doublons d'option le cas échéant */
                                list($options_comp_name, $options_comp_val) = cleanOptCompOfferIn(
                                    $options_comp_name,
                                    $options_comp_val
                                );

                                $art['options_comp_name']   = $options_comp_name;
                                $art['options_comp_val']    = $options_comp_val;
                            }

                            $attach = isAke($art, 'attach', []);
                            unset($art['attach']);

                            $art['offerin_id'] = $offer->id;
                            $a = bigDb('articlein')->create($art)->save();

                            if (!empty($attach)) {
                                $dirOffer = APPLICATION_PATH . DS . '..' . DS . 'public' . DS . 'files' . DS . $universe . DS .'offer_in';

                                if (!is_dir($dirOffer)) {
                                    File::mkdir($dirOffer);
                                }

                                $dirOffer .= DS . $offer->id;

                                if (!is_dir($dirOffer)) {
                                    File::mkdir($dirOffer);
                                }

                                $dirOffer .= DS . $a->id;

                                if (!is_dir($dirOffer)) {
                                    File::mkdir($dirOffer);
                                }

                                foreach ($attach as $f) {
                                    $tab        = explode(DS, $f);
                                    $nameAttach = Arrays::last($tab);
                                    $newFile    = $dirOffer . DS . $nameAttach;

                                    File::copy($f, $newFile);
                                    File::delete($f);
                                }
                            }
                        }

                        array_push($collection, $offer->assoc());

                        if ($statusOffer == getStatus('OK')) {
                            array_push($collectionPush, $offer->assoc());
                        }
                    }
                }
            }

            if (!empty($collectionPush)) {
                $resellers = lib('bourse')->getResellersByOffer($collectionPush);
                $employees = lib('bourse')->getEmployeesToNotif($resellers, $collectionPush);

                lib('bourse')->push($employees);
            }

            return $collection;
        }
    }

    function cleanOptCompOfferIn($keys, $values)
    {
        if (count($keys) == count($values)) {
            $tuples = [];

            foreach ($keys as $i => $key) {
                $value = $values[$i];
                $check = sha1($key . $value);

                if (!Arrays::in($check, $tuples)) {
                    $tuples[] = $check;
                } else {
                    unset($keys[$i]);
                    unset($values[$i]);
                }
            }
        }

        return [$keys, $values];
    }

    if (!function_exists('searchSegmentsByPhonetic')) {
        function searchSegmentsByPhonetic($universe, $word, $limit = 0, $tuples = [])
        {
            $collection = [];

            $keyCacheData   = 'phonetics.data.' . sha1(serialize(func_get_args()));
            $keyCacheAge    = 'phonetics.age.' . sha1(serialize(func_get_args()));

            $segType = Model::Segmenttype()->where(['name', '=', $universe])->first(true);

            if ($segType) {
                $segments       = Model::Segment()->where(['segmenttype_id', '=', $segType->id])->exec();
                $age            = Model::Segment()->getAge();

                $ageCache = Model::Segment()->cache()->get($keyCacheAge);

                if (strlen($ageCache)) {
                    if ($ageCache > $age) {
                        $data = Model::Segment()->cache()->get($keyCacheData);

                        if (strlen($data)) {
                            return unserialize($data);
                        }
                    }
                }

                foreach ($segments as $segment) {
                    $data                   = repo('segment')->getData($segment['id']);
                    $option_fondamentale    = isAke($data, 'option_fondamentale', false);
                    $option_suggeree        = isAke($data, 'option_suggeree', false);
                    $icon                   = isAke($data, 'icon', null);
                    $img                    = isAke($data, 'img', null);
                    $description            = isAke($data, 'description', null);
                    $is_item                = isAke($data, 'is_item', false);

                    if (is_string($is_item)) {
                        $is_item = 'oui' == strtolower($is_item) ? true : false;
                    }

                    if (!$option_fondamentale && !$option_suggeree) {
                        $score = Phonetik::proximity($segment['name'], $word);
                        $score = $score['score'];

                        if (60 <= $score) {
                            unset($segment['hash']);
                            $segment['is_item']     = $is_item;
                            $segment['icon']        = $icon;
                            $segment['img']         = $img;
                            $segment['description'] = $description;
                            $segment['score']       = $score;

                            if (true === $is_item) {
                                $father = Model::Segment()->find($segment['segment_id']);

                                if ($father) {
                                    $father = Model::Segment()->find($father->segment_id);

                                    if ($father) {
                                        $segment['cat'] = $father->name;
                                    }
                                }
                            }

                            if (!Arrays::in($segment['id'], $tuples)) {
                                array_push($collection, $segment);
                                array_push($tuples, $segment['id']);
                            }

                            if ($limit > 0) {
                                if (count($collection) == $limit) {
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            if (fnmatch('* *', $word)) {
                $words = explode(' ', $word);

                foreach ($words as $wd) {
                    if (strlen($wd) > 1) {
                        $collection = array_merge($collection, searchSegmentsByPhonetic($universe, $wd, 0, $tuples));
                    }
                }
            }

            if (!empty($collection)) {
                $collection = arrayOrderBy($collection, 'score', SORT_DESC);
            }

            Model::Segment()->cache()->set($keyCacheAge, time());
            Model::Segment()->cache()->set($keyCacheData, serialize($collection));

            return $collection;
        }

        function arrayOrderBy()
        {
            $args = func_get_args();
            $data = array_shift($args);

            foreach ($args as $n => $field) {
                if (is_string($field)) {
                    $tmp = [];

                    foreach ($data as $key => $row) {
                        $tmp[$key] = $row[$field];
                    }

                    $args[$n] = $tmp;
                }
            }

            $args[] = &$data;

            call_user_func_array('array_multisort', $args);

            return array_pop($args);
        }
    }

    if (!function_exists('fly')) {
        function fly($name = null)
        {
            $name  = is_null($name) ? 'app' : $name;

            return Fly::instance($name);
        }
    }

    if (!function_exists('updateByid')) {
        function updateById($table, $id, $data = [], $db = null)
        {
            $db  = is_null($db) ? SITE_NAME : $db;

            $obj = bigDb($table, $db)->findOrFail($id);

            return $obj->hydrate($data)->save();
        }
    }

    if (!function_exists('phonetic')) {
        function phonetic()
        {
            if (!class_exists('Phonetic')) {
                require_once LIBRARIES_PATH . DS . 'Phonetic/Phonetic.php';
            }

            return Phonetic::app()->run()->BMSoundex;
        }
    }

    if (!function_exists('convert')) {
        function convert($from, $to, $options = '')
        {
            $cmd = "convert $from $options $to";
            exec($cmd, $return);

            return true;
        }
    }

    if (!function_exists('ocr')) {
        function ocr($img, $lng = 'fre')
        {
            $t = new \TesseractOCR($img);
            $t->setTempDir(CACHE_PATH);
            // $t->setLanguage($lng);

            return $t->recognize();
        }
    }

    if (!function_exists('geoIP')) {
        function geoIP($ip, $object = false)
        {
            $reader     = new \GeoIp2\Database\Reader(APPLICATION_PATH . DS . 'geoip' . DS . 'GeoLite2-City.mmdb');

            $adapter    = new \Geocoder\Adapter\GeoIP2Adapter($reader);
            $geocoder   = new \Geocoder\Provider\GeoIP2($adapter);

            $address    = $geocoder->geocode($ip);

            $obj        = Arrays::first($address);

            $cont       = [];

            $country    = $obj->getCountry();

            $cont['country']['name'] = $country->getName();
            $cont['country']['code'] = $country->getCode();

            $region = $obj->getRegion();

            $cont['region']['name'] = $region->getName();
            $cont['region']['code'] = $region->getCode();

            $coordinates = $obj->getCoordinates();

            $cont['coordinates']['latitude']    = $coordinates->getLatitude();
            $cont['coordinates']['longitude']   = $coordinates->getLongitude();
            $cont['streetNumber']               = $obj->getStreetNumber();
            $cont['streetName']                 = $obj->getStreetName();
            $cont['zip']                        = $obj->getPostalCode();
            $cont['city']                       = $obj->getLocality();

            if ($object) {
                return with(new Container)->populate($cont);
            }

            return $cont;
        }
    }

    if (!function_exists('getStatus')) {
        function getStatus($code, $id = true)
        {
            $status = Model::Status()->firstOrCreate(['code' => Inflector::upper($code)]);

            return $id ? (int) $status->id : $status;
        }

        function getGenre($code, $id = true)
        {
            $genre = Model::Genre()->firstOrCreate(['code' => Inflector::upper($code)]);

            return $id ? (int) $genre->id : $genre;
        }

        function getResponsibility($code, $id = true)
        {
            $responsibility = Model::Responsibility()->firstOrCreate(['code' => Inflector::upper($code)]);

            return $id ? (int) $responsibility->id : $responsibility;
        }

        function getHabilitation($code, $id = true)
        {
            $habilitation = Model::Habilitation()->firstOrCreate(['code' => Inflector::upper($code)]);

            return $id ? (int) $habilitation->id : $habilitation;
        }

        function getZone($code, $id = true)
        {
            $zone = Model::Zone()->firstOrCreate(['code' => Inflector::upper($code)]);

            return $id ? $zone->id : $zone;
        }

        function getSubscription($code, $id = true)
        {
            $subscription = Model::Subscription()->firstOrCreate(['code' => Inflector::upper($code)]);

            return $id ? $subscription->id : $subscription;
        }

        function getSetting($code, $id = true)
        {
            $setting = Model::Setting()->firstOrCreate(['code' => Inflector::upper($code)]);

            return $id ? $setting->id : $setting;
        }
    }

    if (!function_exists('UserHasPersoCompany')) {
        function UserHasPersoCompany($user)
        {
            if (is_object($user)) {
                $user = $user->id;
            }

            if (!is_numeric($user)) {
                throw new Exception('User must be an instance of people or be an id');
            }

            $owners = bigDb('option')
            ->where(['object_database', '=', SITE_NAME])
            ->where(['object_table', '=', 'company'])
            ->where(['name', '=', 'owner'])
            ->where(['value', '=', $user])->exec();

            foreach ($owners as $owner) {
                $tab        = getCompany($owner['object_id'])->assoc();
                $is_pro     = isAke($tab, 'is_pro', true);
                $is_group   = isAke($tab, 'is_group', false);
                $name       = isAke($tab, 'name', null);

                if (true !== $is_pro && true !== $is_pro) {
                    return !strlen($name) ? 0 : $owner['object_id'];
                }
            }

            return 0;
        }
    }

    if (!function_exists('getUserCompanies')) {
        function deleteUserFromCompany($user, $company)
        {
            if (is_object($user)) {
                $user = $user->id;
            }

            if (!is_numeric($user)) {
                throw new Exception('User must be an instance of people or be an id');
            }

            if (is_object($company)) {
                $company = $company->id;
            }

            if (!is_numeric($company)) {
                throw new Exception('company must be an instance of people or be an id');
            }

            $companies = bigDb('option')
            ->where(['object_database', '=', SITE_NAME])
            ->where(['object_table', '=', 'company'])
            ->where(['object_id', '=', $company])
            ->where(['value', '=', $user])->exec(true);

            foreach ($companies as $company) {
                $company->delete();
            }

            return true;
        }

        function peopleBelongsToCompany($people, $company)
        {
            if (is_object($people)) {
                $people = $people->id;
            }

            if (!is_numeric($people)) {
                throw new Exception('People must be an instance of people or be an id');
            }

            if (is_object($company)) {
                $company = $company->id;
            }

            if (!is_numeric($company)) {
                throw new Exception('Company must be an instance of people or be an id');
            }

            $count = bigDb('option')
            ->where(['object_database', '=', SITE_NAME])
            ->where(['object_table', '=', 'company'])
            ->where(['object_id', '=', $company])
            ->where(['value', '=', $people])->count();

            return $count > 0 ? true : false;
        }

        function getUserCompanies($user)
        {
            if (is_object($user)) {
                $user = $user->id;
            }

            if (!is_numeric($user)) {
                throw new Exception('User must be an instance of people or be an id');
            }

            $owners = bigDb('option')
            ->where(['object_database', '=', SITE_NAME])
            ->where(['object_table', '=', 'company'])
            ->where(['name', '=', 'owner'])
            ->where(['value', '=', $user])->exec();

            $members = bigDb('option')
            ->where(['object_database', '=', SITE_NAME])
            ->where(['object_table', '=', 'company'])
            ->where(['name', '=', 'member'])
            ->where(['value', '=', $user])->exec();

            $collection = [];

            foreach ($owners as $owner) {
                $tab = getCompany($owner['object_id'])->assoc();
                $tab['type'] = 'owner';

                array_push($collection, $tab);
            }

            foreach ($members as $member) {
                $tab = getCompany($member['object_id'])->assoc();
                $tab['type'] = 'member';

                array_push($collection, $tab);
            }

            return $collection;
        }

        function addUserToCompany($user, $company, $type = 'owner')
        {
            if (is_object($user)) {
                $user = $user->id;
            }

            if (!is_numeric($user)) {
                throw new Exception('User must be an instance of people or be an id');
            }

            if (is_object($company)) {
                $company = $company->id;
            }

            if (!is_numeric($company)) {
                throw new Exception('company must be an instance of people or be an id');
            }

            return $type == 'owner' ? opt($company)->set($type, $user) : opt($company)->sets($type, $user);
        }

        function getUserSubscription($user)
        {
            if (is_object($user)) {
                $user = $user->id;
            }

            if (!is_numeric($user)) {
                throw new Exception('User must be an instance of people or be an id');
            }

            $companyId      = 0;
            $subscription   = 0;

            $companies = getUserCompanies($user);

            foreach ($companies as $company) {
                $subTmp = opt(bigDb('company')->find($company['id']))->get('subscription');

                if (strlen($subTmp)) {
                    $subTmp = (int) $subTmp;

                    if ($subTmp > $subscription) {
                        $subscription = $subTmp;
                        $companyId = $company['id'];
                    }
                }
            }

            return [$companyId => $subscription];
        }
    }

    if (!function_exists('pdf')) {
        function pdf($html, $name = null, $orientation = null, $disposition = null)
        {
            $name           = is_null($name)        ? 'doc.pdf'     : Inflector::urlize($name) . '.pdf';
            $orientation    = is_null($orientation) ? 'portrait'    : $orientation;
            $disposition    = is_null($disposition) ? 'inline'      : $disposition;

            $file           = TMP_PUBLIC_PATH . DS . sha1(serialize(func_get_args())) . '.html';

            files()->put($file, $html);

            $pdf = str_replace('.html', '.pdf', $file);

            $url = 'http://zelift.com/tmp/' . Arrays::last(explode('/', $file));

            // if ('portrait' == $orientation) {
            //     $cmd = "xvfb-run -a -s \"-screen 0 640x480x16\" wkhtmltopdf --dpi 600 --page-size A4 $url $pdf";
            // } else {
            //     $cmd = "xvfb-run -a -s \"-screen 0 640x480x16\" wkhtmltopdf --dpi 600 --page-size A4 -O Landscape $url $pdf";
            // }

            // $output = shell_exec($cmd);

            // $cnt = fgc($pdf);

            $cnt = dwn('http://apphuge.uk/pdf.php?url=' . urlencode($url) . '&orientation=' . $orientation);

            header('Content-Type: application/pdf');
            header('Content-Length: ' . strlen($cnt));
            header('Content-Disposition: ' . $disposition . '; filename="' . $name . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            ini_set('zlib.output_compression', '0');

            files()->delete($file);
            // files()->delete($pdf);

            die($cnt);
        }

        function pdfFile($html, $name = null, $orientation = null, $count = 0)
        {
            $name           = is_null($name)        ? 'doc.pdf'     : Inflector::urlize($name) . '.pdf';
            $orientation    = is_null($orientation) ? 'portrait'    : $orientation;

            $file           = TMP_PUBLIC_PATH . DS . sha1(serialize(func_get_args())) . '.html';

            files()->put($file, $html);

            $pdf = str_replace('.html', '.pdf', $file);

            // $keep = lib('keep')->instance();

            $url = 'http://www.zelift.com/tmp/' . Arrays::last(explode('/', $file));

            $cnt = dwn('http://apphuge.uk/pdf.php?url=' . urlencode($url) . '&orientation=' . $orientation);

            // if (!strlen($cnt) && $count < 5) {
            //     return pdfFile($html, $name, $orientation, $count++);
            // }

            // if (!strlen($cnt) && $count >= 5) {
            //     exception('pdf', 'Le pdf est erroné et ne peut être créé.');
            // }

            files()->delete($file);

            return $cnt;
        }
    }

    if (!function_exists('rlog')) {
        function rlog($name = null)
        {
            static $logs = [];

            $name = is_null($name) ? SITE_NAME . '.log' : SITE_NAME . '.' . $name . '.log';

            $log = isAke($logs, $name, false);

            if (false === $log) {
                $log = new Logger('name');
                $handler = new RH(redis(), $name);
                $handler->setFormatter(new LF("%message%"));
                $log->pushHandler($handler);
            }

            return $log;
        }
    }

    if (!function_exists('setCacheAge')) {
        function setCacheAge($key, $value)
        {
            $key .= '.aged.' . time();

            return redis()->set($key, $value);
        }

        function getCacheAge($key, $minAge)
        {
            $keys = redis()->keys($key . '.aged.*');

            foreach ($keys as $keyCache) {
                $age = Arrays::last(explode('.', $keyCache));

                if ($age > $minAge) {
                    return redis()->get($key);
                } else {
                    redis()->del($key);
                }
            }

            return null;
        }
    }

    if (!function_exists('linkTo')) {
        function linkTo($from, $to)
        {
            if (!is_object($from)) {
                throw new Exception("The 'from' argument is not a model.");
            }

            if ($from instanceof Container) {
                $motorFrom = 'dbjson';
            } else {
                $motorFrom = 'dbredis';
            }

            if (!is_object($to)) {
                throw new Exception("The 'to' argument is not a model.");
            }

            if ($to instanceof Container) {
                $motorTo = 'dbjson';
            } else {
                $motorTo = 'dbredis';
            }

            return bigDb('link')->firstOrCreate([
                'motor_from'    => $motorFrom,
                'motor_to'      => $motorTo,
                'database_from' => $from->db()->db,
                'database_to'   => $to->db()->db,
                'table_from'    => $from->db()->table,
                'table_to'      => $to->db()->table,
                'id_from'       => $from->id,
                'id_to'         => $to->id
            ]);
        }

        function unlinkTo($from, $to)
        {
            if (!is_object($from)) {
                throw new Exception("The 'from' argument is not a model.");
            }

            if ($from instanceof Container) {
                $motorFrom = 'dbjson';
            } else {
                $motorFrom = 'dbredis';
            }

            if (!is_object($to)) {
                throw new Exception("The 'to' argument is not a model.");
            }

            if ($to instanceof Container) {
                $motorTo = 'dbjson';
            } else {
                $motorTo = 'dbredis';
            }

            $row = bigDb('link')
            ->where(['motor_from', '=', $motorFrom])
            ->where(['motor_to', '=', $motorTo])
            ->where(['database_from', '=', $from->db()->db])
            ->where(['database_to', '=', $to->db()->db])
            ->where(['table_from', '=', $from->db()->table])
            ->where(['table_to', '=', $to->db()->table])
            ->where(['id_from', '=', $from->id])
            ->where(['id_to', '=', $to->id])
            ->first(true);

            if ($row) {
                $row->delete();

                return true;
            }

            return false;
        }

        function hasLinkTo($from, $to)
        {
            if (!is_object($from)) {
                throw new Exception("The 'from' argument is not a model.");
            }

            if ($from instanceof Container) {
                $motorFrom = 'dbjson';
            } else {
                $motorFrom = 'dbredis';
            }

            if (!is_object($to)) {
                throw new Exception("The 'to' argument is not a model.");
            }

            if ($to instanceof Container) {
                $motorTo = 'dbjson';
            } else {
                $motorTo = 'dbredis';
            }

            $row = bigDb('link')
            ->where(['motor_from', '=', $motorFrom])
            ->where(['motor_to', '=', $motorTo])
            ->where(['database_from', '=', $from->db()->db])
            ->where(['database_to', '=', $to->db()->db])
            ->where(['table_from', '=', $from->db()->table])
            ->where(['table_to', '=', $to->db()->table])
            ->where(['id_from', '=', $from->id])
            ->where(['id_to', '=', $to->id])
            ->first(true);

            if ($row) {
                return true;
            }

            return false;
        }

        function linkSegmentTo($segmentId, $to)
        {
            opt($to)->sets('segment', $segmentId);
        }

        function authUplift($to, $upliftName)
        {
            opt($to)->sets('uplift', $upliftName);
        }

        function unauthUplift($to, $upliftName)
        {
            opt($to)->delByValue('uplift', $upliftName);
        }

        function hasUplift($to, $upliftName)
        {
            return opt($to)->has('uplift', $upliftName);
        }

        function unlinkSegmentTo($segmentId, $to)
        {
            if (!is_object($to)) {
                throw new Exception("The 'to' argument is not a model.");
            }

            if ($to instanceof Container) {
                $motor = 'dbjson';
            } else {
                $motor = 'dbredis';
            }

            $option = bigDb('option')
            ->where(['object_motor', '=', $motor])
            ->where(['object_database', '=', $to->db()->db])
            ->where(['object_table', '=', $to->db()->table])
            ->where(['object_id', '=', $to->id])
            ->where(['name', '=', 'segment'])
            ->where(['value', '=', $segmentId])
            ->first(true);

            if ($option) {
                return $option->delete();
            }

            return false;
        }

        function hasLinkToSegment($to, $segmentId)
        {
            if (!is_object($to)) {
                throw new Exception("The 'to' argument is not a model.");
            }

            if ($to instanceof Container) {
                $motor = 'dbjson';
            } else {
                $motor = 'dbredis';
            }

            $option = bigDb('option')
            ->where(['object_motor', '=', $motor])
            ->where(['object_database', '=', $to->db()->db])
            ->where(['object_table', '=', $to->db()->table])
            ->where(['object_id', '=', $to->id])
            ->where(['name', '=', 'segment'])
            ->where(['value', '=', $segmentId])
            ->first(true);

            if ($option) {
                return true;
            }

            return false;
        }

        function getRowsFromOptionValue($table, $name, $value, $motor = null, $db = null, $object = false)
        {
            $collection = [];

            $db     = is_null($db)      ? SITE_NAME : $db;
            $motor  = is_null($motor)   ? 'dbredis' : $motor;

            $rows = bigDb('option')
            ->where(['object_motor', '=', $motor])
            ->where(['object_database', '=', $db])
            ->where(['object_table', '=', $table])
            ->where(['name', '=', $name])
            ->where(['value', '=', $value])
            ->exec();

            foreach ($rows as $row) {
                if ($motor == 'dbredis') {
                    $row = bigDb($table, $db)->find($row['object_id'], $object);
                } elseif ($motor == 'dbjson') {
                    $row = jdb($db, $table)->find($row['object_id'], $object);
                }

                array_push($collection, $row);
            }

            return $collection;
        }

        function getResellersByOffer($offer)
        {
            $collection = [];

            $options = opt($offer)->all();

            $resellers = bigDb('option')->where(['object_table', '=', 'reseller'])->where(['name', '=', 'universe'])->where(['value', '=', $offer->universe])->exec();

            foreach ($resellers as $resellerOpt) {
                $reseller = reseller($resellerOpt['object_id']);

                $members = opt($reseller)->gets('member');

                $reseller = $reseller->assoc();

                $reseller['members'] = [];

                foreach ($members as $memberId) {
                    $member = Model::People()->find($memberId, false);
                    array_push($reseller['members'], $member);
                }

                array_push($collection, $reseller);
            }

            return $collection;
        }
    }

    if (!function_exists('files')) {
        function files()
        {
            return new File;
        }
    }

    if (!function_exists('linkify')) {
        function linkify($model, $cache = true)
        {
            return new Option('link', $model, $cache);
        }
    }

    if (!function_exists('opt')) {
        function opt($model, $cache = true)
        {
            return new Option('option', $model, $cache);
        }
    }

    if (!function_exists('setting')) {
        function setting($model, $cache = true)
        {
            return new Option('setting', $model, $cache);
        }
    }

    if (!function_exists('getCoords')) {
        function getCoords($address, $country = 250)
        {
            return lib("geo")->getCoordsMap($address);
            $components         = [];
            $components['lat']  = $components['lng'] = 0;
            $keyJsonLocal       = 'pois.' . sha1($address);
            $urlLocalisation    = "http://search.mappy.net/search/1.0/find?q=" . urlencode($address) . "&favorite_country=$country&language=FRE&loc_format=geojson";

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
                        $bbox = isAke($coords, 'bbox', []);

                        $components['bbox'] = $bbox;

                        if (isset($coords['geometry'])) {
                            if (isset($coords['geometry']['geometries'])) {
                                if (!empty($coords['geometry']['geometries'])) {
                                    $geometries = current($coords['geometry']['geometries']);
                                    if (isset($geometries['coordinates'])) {
                                        list($lng, $lat) = $geometries['coordinates'];
                                        $components['lng'] = (float) $lng;
                                        $components['lat'] = (float) $lat;
                                    }
                                }
                            }
                        }

                        if (isset($coords['properties']['address_components'])) {
                            if (!empty($coords['properties']['address_components'])) {
                                foreach ($coords['properties']['address_components'] as $k => $v) {
                                    $components[$k] = $v;
                                }
                            }
                        }

                        if (isset($components['way'])) {
                            $components['street'] = $components['way'];
                            unset($components['way']);
                        }

                        if (isset($components['way_number'])) {
                            $components['street_number'] = $components['way_number'];
                            unset($components['way_number']);
                        }

                        if (isset($components['town'])) {
                            $components['city'] = $components['town'];
                            unset($components['town']);
                        }

                        if (isset($components['admin_1'])) {
                            $components['region'] = $components['admin_1'];
                            unset($components['admin_1']);
                        }

                        if (isset($components['bbox'])) {
                            $components['limits'] = [];
                            $components['limits']['lng1'] = $components['bbox'][0];
                            $components['limits']['lat1'] = $components['bbox'][1];
                            $components['limits']['lng2'] = $components['bbox'][2];
                            $components['limits']['lat2'] = $components['bbox'][3];
                            unset($components['bbox']);
                        }

                        if (isset($components['postcode'])) {
                            $components['zip'] = $components['postcode'];
                            unset($components['postcode']);
                        }

                        ksort($components);
                    }
                }
            }

            return $components;
        }
    }

    if (!function_exists('setLocationAsync')) {
        function setLocationAsync($object, $address)
        {
            $address = str_replace(["\n", "\r", ', ', ','], ' ', $address);
            $address = str_replace(['  '], ' ', $address);

            $cb = function ($id, $db, $table, $address) {
                $object = rdb($db, $table)->findOrFail($id);
                $coords = lib('geo')->getCoords($address, 250);
                setLocation($object, $coords['lng'], $coords['lat']);
            };

            lib('later')->set('setLocationAsync.' . Utils::token(), $cb, [$object->id, $object->db()->db, $object->db()->table, $address]);
            lib('later')->background();
        }
    }

    if (!function_exists('setLocation')) {
        function setLocation($model, $lng, $lat)
        {
            if (!is_object($model)) {
                throw new Exception("The first argument is not a model.");
            }

            if ($model instanceof Container) {
                $motor = 'dbjson';
            } else {
                $motor = 'dbredis';
            }

            $db = bigDb('location');

            $db->firstOrCreate([
                'object_id'         => $model->id,
                'object_motor'      => $motor,
                'object_database'   => $model->db()->db,
                'object_table'      => $model->db()->table,
                'value'             => ['lng' => (float) $lng, 'lat' => (float) $lat]
            ]);
        }

        function getLocation($model)
        {
            if (!is_object($model)) {
                throw new Exception("The first argument is not a model.");
            }

            if ($model instanceof Container) {
                $motor = 'dbjson';
            } else {
                $motor = 'dbredis';
            }

            $db = bigDb('location');

            $object = $db
            ->where(['object_id', '=', $model->id])
            ->where(['object_motor', '=', $motor])
            ->where(['object_database', '=', $model->db()->db])
            ->where(['object_table', '=', $model->db()->table])
            ->first(true);

            return $object ? $object->value : [];
        }

        function getNearCitiesFromZip($zip, $distance = 50, $maxCities = 1000)
        {
            $zip = (string) $zip;
            $collection = [];

            $city = Model::City()->where(['zip', '=', $zip])->first(true);

            if ($city) {
                $collection = getDatasFromMaxDistance(Model::city(), $city, $distance, $maxCities);
            }

            return $collection;
        }

        function getNearCitiesFromName($name, $distance = 50, $maxCities = 1000, $like = false)
        {
            $name = (string) $name;
            $collection = [];

            $op = !$like ? '=' : 'LIKE';

            $name = !$like ? $name : $name . '%';

            $city = Model::City()->where(['name', $op, $name])->first(true);

            if ($city) {
                $collection = getDatasFromMaxDistance(Model::City(), $city, $distance, $maxCities);
            }

            return $collection;
        }

        function getDatasFromMaxDistance($model, $ref, $maxDistance = 1, $max = 1000)
        {
            if (!is_object($model)) {
                throw new Exception("The first argument is not a model.");
            }

            if (!is_object($ref)) {
                throw new Exception("The second argument is not a model.");
            }

            $lngLat = getLocation($ref);

            if ($model instanceof Dbjson\Dbjson) {
                $motor = 'dbjson';
            } else {
                $motor = 'dbredis';
            }

            $add = [];

            $db = bigDb('location');

            $odm = $db->getOdm();

            $collection = $odm->selectCollection($db->collection);
            $collection->ensureIndex(['value' => '2d', 'object_motor' => 1, 'object_database' => 1, 'object_table' => 1]);

            $filter = [
                'value' => [
                    '$near' => [floatval($lngLat['lng']), floatval($lngLat['lat'])]
                ],
                'object_motor'     => $motor,
                'object_database'  => $model->db,
                'object_table'     => $model->table
            ];

            $results = $collection->find($filter)->limit($max);

            foreach ($results as $row) {
                $rowMotor = $row['object_motor'];

                if ('dbjson' == $rowMotor) {
                    $rowDb = jdb($row['object_database'], $row['object_table'])->find($row['object_id'], false);
                } elseif ('dbredis' == $rowMotor) {
                    $rowDb = rdb($row['object_database'], $row['object_table'])->find($row['object_id'], false);
                }

                $distances = distanceKmMiles($lngLat['lng'], $lngLat['lat'], $row['value']['lng'], $row['value']['lat']);

                if (floatval($distances['km']) <= $maxDistance) {
                    $rowDb['distance'] = [
                        'km'    => (float) $distances['km'],
                        'miles' => (float) $distances['miles'],
                    ];

                    if ($distances['km'] > 0) {
                        array_push($add, $rowDb);
                    }
                } else {
                    break;
                }
            }

            return $add;
        }

        function distanceKmMiles($lng1, $lat1, $lng2, $lat2)
        {
            $lng1 = floatval(str_replace(',', '.', $lng1));
            $lat1 = floatval(str_replace(',', '.', $lat1));
            $lng2 = floatval(str_replace(',', '.', $lng2));
            $lat2 = floatval(str_replace(',', '.', $lat2));

            $pi80 = M_PI / 180;
            $lat1 *= $pi80;
            $lng1 *= $pi80;
            $lat2 *= $pi80;
            $lng2 *= $pi80;

            $dlat           = $lat2 - $lat1;
            $dlng           = $lng2 - $lng1;
            $a              = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlng / 2) * sin($dlng / 2);
            $c              = 2 * atan2(sqrt($a), sqrt(1 - $a));

            /* km */
            $earthRadius    = 6372.797;
            $km             = $earthRadius * $c;
            $km             = round($km, 2);

            /* miles */
            $earthRadius    = 3963.1;
            $miles          = $earthRadius * $c;
            $miles          = round($miles, 2);

            return ['km' => $km, 'miles' => $miles];
        }
    }

    if (!function_exists('uplift')) {
        function uplift($name)
        {
            return bigDb('uplift')->where(['name', '=', $name])->first(true);
        }

        function setUplift($name, $data = [])
        {
            $uplift = uplift(Inflector::lower($name));

            if (!$uplift) {
                $uplift = bigDb('uplift')->create(['universe' => Inflector::lower($universe), 'name' => Inflector::lower($name)])->save();
            }

            foreach ($data as $value) {
                $priority = (int) bigDb('upliftvalue')->where(['uplift_id' => $uplift->id])->count();

                $priority += 1;

                bigDb('upliftvalue')
                ->firstOrCreate(['uplift_id' => $uplift->id, 'value' => $value, 'priority' => $priority]);
            }
        }

        function getUplift($name, $orderBy = 'value', $direction = 'ASC')
        {
            $collection = [];
            $uplift     = uplift(Inflector::lower($name));

            if ($uplift) {
                $values = bigDb('upliftvalue')->where(['uplift_id' => $uplift->id])->order($orderBy, $direction)->exec();

                foreach ($values as $value) {
                    array_push($collection, $value['value']);
                }
            }

            return $collection;
        }

        function linkUpliftTo($uplift, $toLink)
        {
            linkTo($toLink, $uplift);
        }

        function unlinkUpliftTo($uplift, $toLink)
        {
            unlinkTo($toLink, $uplift);
        }

        function linkUpliftToUser($uplift, $user)
        {
            linkTo($user, $uplift);
        }

        function linkUpliftToCompany($uplift, $company)
        {
            linkTo($company, $uplift);
        }

        function linkUpliftToGroup($uplift, $group)
        {
            linkTo($group, $uplift);
        }

        function unlinkUpliftToUser($uplift, $user)
        {
            unlinkTo($user, $uplift);
        }

        function unlinkUpliftToCompany($uplift, $company)
        {
            unlinkTo($company, $uplift);
        }

        function unlinkUpliftToGroup($uplift, $group)
        {
            unlinkTo($group, $uplift);
        }

        function userHasUplift($user, $uplift)
        {
            return hasLinkTo($user, $uplift);
        }

        function companyHasUplift($company, $uplift)
        {
            return hasLinkTo($company, $uplift);
        }

        function groupHasUplift($group, $uplift)
        {
            return hasLinkTo($group, $uplift);
        }

        function setPriorityUplift($name, $value, $priority = 1)
        {
            $uplift = uplift(Inflector::lower($name));

            if ($uplift) {
                $val = bigDb('upliftvalue')->where(['uplift_id' => $uplift->id])->where(['value', '=', $value])->first(true);

                if ($val) {
                    $val->setPriority($priority)->save();

                    return true;
                }
            }

            return false;
        }
    }

    /* credit user */

    if (!function_exists('getCreditByUser')) {
        function getCreditByUser($user)
        {
            if (!is_object($user)) {
                if (is_numeric($user)) {
                    $user = Model::People()->findOrFail($user);
                }
            }

            return opt($user)->get('credit', 0);
        }
    }

    if (!function_exists('setCreditByUser')) {
        function setCreditByUser($user, $amount)
        {
            if (!is_object($user)) {
                if (is_numeric($user)) {
                    $user = Model::People()->findOrFail($user);
                }
            }

            return opt($user)->set('credit', $amount);
        }
    }

    if (!function_exists('incrCreditByUser')) {
        function incrCreditByUser($user, $amount = 1)
        {
            if (!is_object($user)) {
                if (is_numeric($user)) {
                    $user = Model::People()->findOrFail($user);
                }
            }

            $credit = (int) opt($user)->get('credit', 0);

            $credit += $amount;

            return opt($user)->set('credit', $credit);
        }
    }

    if (!function_exists('decrCreditByUser')) {
        function decrCreditByUser($user, $amount = 1)
        {
            if (!is_object($user)) {
                if (is_numeric($user)) {
                    $user = Model::People()->findOrFail($user);
                }
            }

            $credit = (int) opt($user)->get('credit', 0);

            $credit -= $amount;

            $credit = 0 > $credit ? 0 : $credit;

            return opt($user)->set('credit', $credit);
        }
    }

    /* credit company */

    if (!function_exists('getCreditByCompany')) {
        function getCreditByCompany($company)
        {
            if (!is_object($company)) {
                if (is_numeric($company)) {
                    $company = bigDb('company')->findOrFail($company);
                }
            }

            return opt($company)->get('credit', 0);
        }
    }

    if (!function_exists('setCreditByCompany')) {
        function setCreditByCompany($company, $amount)
        {
            if (!is_object($company)) {
                if (is_numeric($company)) {
                    $company = bigDb('company')->findOrFail($company);
                }
            }

            return opt($company)->set('credit', $amount);
        }
    }

    if (!function_exists('incrCreditByCompany')) {
        function incrCreditByCompany($company, $amount = 1)
        {
            if (!is_object($company)) {
                if (is_numeric($company)) {
                    $company = bigDb('company')->findOrFail($company);
                }
            }

            $credit = (int) opt($company)->get('credit', 0);

            $credit += $amount;

            return opt($company)->set('credit', $credit);
        }
    }

    if (!function_exists('decrCreditByCompany')) {
        function decrCreditByCompany($company, $amount = 1)
        {
            if (!is_object($company)) {
                if (is_numeric($company)) {
                    $company = bigDb('company')->findOrFail($company);
                }
            }

            $credit = (int) opt($company)->get('credit', 0);

            $credit -= $amount;

            $credit = 0 > $credit ? 0 : $credit;

            return opt($company)->set('credit', $credit);
        }
    }

    /* credit group */

    if (!function_exists('getCreditByGroup')) {
        function getCreditByGroup($group)
        {
            if (!is_object($group)) {
                if (is_numeric($group)) {
                    $group = bigDb('group')->findOrFail($group);
                }
            }

            return opt($group)->get('credit', 0);
        }
    }

    if (!function_exists('setCreditByGroup')) {
        function setCreditByGroup($group, $amount)
        {
            if (!is_object($group)) {
                if (is_numeric($group)) {
                    $group = bigDb('group')->findOrFail($group);
                }
            }

            return opt($group)->set('credit', $amount);
        }
    }

    if (!function_exists('incrCreditByGroup')) {
        function incrCreditByGroup($group, $amount = 1)
        {
            if (!is_object($group)) {
                if (is_numeric($group)) {
                    $group = bigDb('group')->findOrFail($group);
                }
            }

            $credit = (int) opt($group)->get('credit', 0);

            $credit += $amount;

            return opt($group)->set('credit', $credit);
        }
    }

    if (!function_exists('decrCreditByGroup')) {
        function decrCreditByGroup($group, $amount = 1)
        {
            if (!is_object($group)) {
                if (is_numeric($group)) {
                    $group = bigDb('group')->findOrFail($group);
                }
            }

            $credit = (int) opt($group)->get('credit', 0);

            $credit -= $amount;

            $credit = 0 > $credit ? 0 : $credit;

            return opt($group)->set('credit', $credit);
        }
    }

    if (!function_exists('company')) {
        function company($what = null, $object = true)
        {
            /* Polymorphism */
            if (is_null($what)) {
                return bigDb('company');
            }

            if (is_numeric($what)) {
                return bigDb('company')->find($what, $object);
            } elseif (Arrays::is($what)) {
                $row = bigDb('company')->create($what)->save();

                return $object ? $row : $row->assoc();
            }
        }
    }

    if (!function_exists('people')) {
        function people($what = null, $object = true)
        {
            /* Polymorphism */
            if (is_null($what)) {
                return bigDb('people');
            }

            if (is_numeric($what)) {
                return bigDb('people')->find($what, $object);
            } elseif (Arrays::is($what)) {
                $row = bigDb('people')->create($what)->save();

                return $object ? $row : $row->assoc();
            }
        }
    }

    if (!function_exists('getCompany')) {
        function getCompany($id)
        {
            return bigDb('company')->find($id);
        }
    }

    if (!function_exists('setCompany')) {
        function setCompany($data = [])
        {
            return bigDb('company')->firstOrCreate($data);
        }
    }

    if (!function_exists('updateCompany')) {
        function updateCompany($id, $data = [])
        {
            $company = bigDb('company')->findOrFail($id);

            return $company->hydrate($data)->save();
        }
    }

    if (!function_exists('updateUser')) {
        function updateUser($id, $data = [])
        {
            $people = bigDb('people')->findOrFail($id);

            return $people->hydrate($data)->save();
        }
    }

    if (!function_exists('group')) {
        function group($what = null, $object = true)
        {
            /* Polymorphism */
            if (is_null($what)) {
                return bigDb('group');
            }

            if (is_numeric($what)) {
                return bigDb('group')->find($what, $object);
            } elseif (Arrays::is($what)) {
                $row = bigDb('group')->create($what)->save();

                return $object ? $row : $row->assoc();
            }
        }
    }

    if (!function_exists('getGroup')) {
        function getGroup($id)
        {
            return bigDb('group')->find($id);
        }
    }

    if (!function_exists('setGroup')) {
        function setGroup($data = [])
        {
            return bigDb('group')->create($data)->save();
        }
    }

    if (!function_exists('reseller')) {
        function reseller($what = null, $object = true)
        {
            /* Polymorphism */
            if (is_null($what)) {
                return bigDb('reseller');
            }

            if (is_numeric($what)) {
                return bigDb('reseller')->find($what, $object);
            } elseif (Arrays::is($what)) {
                $row = bigDb('reseller')->create($what)->save();

                return $object ? $row : $row->assoc();
            }
        }
    }

    if (!function_exists('getReseller')) {
        function getReseller($id)
        {
            return bigDb('reseller')->find($id);
        }
    }

    if (!function_exists('setReseller')) {
        function setReseller($data = [])
        {
            return bigDb('reseller')->firstOrCreate($data);
        }
    }

    if (!function_exists('setResellerEmployee')) {
        function setResellerEmployee($data = [])
        {
            $check = bigDb('reselleremployee')->where(['email_pro', '=', $data['email_pro']])->count();

            if ($check > 0) {
                return null;
            }

            return bigDb('reselleremployee')->firstOrCreate($data);
        }
    }

    if (!function_exists('getResellerEmployees')) {
        function getResellerEmployees($reseller, $object = false)
        {
            if (is_object($reseller)) {
                $reseller = $reseller->id;
            }

            if (!is_numeric($reseller)) {
                throw new Exception('Reseller must be an instance of reseller or be an id');
            }

            return bigDb('reselleremployee')
            ->where(['reseller_id', '=', $reseller])
            ->order('lastname')
            ->order('firstname')
            ->exec($object);
        }

        function addSettingToReseller($setting, $reseller, $value = true)
        {
            return addSetting($setting, $reseller, 'reseller', $value);
        }

        function removeSettingToReseller($setting, $reseller)
        {
            return removeSetting($setting, $reseller, 'reseller');
        }

        function settingBelongsToReseller($setting, $reseller)
        {
            return settingBelongsTo($setting, $reseller, 'reseller');
        }

        function getSettingValueFromReseller($setting, $reseller, $default = null)
        {
            $val = getSettingValue($setting, $reseller, 'reseller');

            return !empty($val) ? $val : $default;
        }

        function getAllSettingsFromReseller($reseller)
        {
            return getAllSettings($reseller, 'reseller');
        }

        function addSettingToEmployee($setting, $reselleremployee, $value = true)
        {
            return addSetting($setting, $reselleremployee, 'reselleremployee', $value);
        }

        function removeSettingToEmployee($setting, $reselleremployee)
        {
            return removeSetting($setting, $reselleremployee, 'reselleremployee');
        }

        function settingBelongsToEmployee($setting, $reselleremployee)
        {
            return settingBelongsTo($setting, $reselleremployee, 'reselleremployee');
        }

        function getSettingValueFromEmployee($setting, $reselleremployee, $default = null)
        {
            $val = getSettingValue($setting, $reselleremployee, 'reselleremployee');

            return !empty($val) ? $val : $default;
        }

        function getAllSettingsFromEmployee($reselleremployee)
        {
            return getAllSettings($reselleremployee, 'reselleremployee');
        }

        function addSetting($setting, $object, $type, $value = true)
        {
            if (is_object($setting)) {
                $setting = $setting->id;
            }

            if (!is_numeric($setting)) {
                throw new Exception('Setting must be an instance of setting or be an id');
            }

            if (is_object($object)) {
                $object = $object->id;
            }

            if (!is_numeric($object)) {
                throw new Exception(ucfirst($type) . ' must be an instance of ' . $type . ' or be an id');
            }

            return bigDb('setting')
            ->firstOrCreate([
                'type'          => $type,
                'type_id'       => $object,
                'setting_id'    => $setting
            ])->setValue($value)->save();
        }

        function removeSetting($setting, $object, $type)
        {
            if (is_object($setting)) {
                $setting = $setting->id;
            }

            if (!is_numeric($setting)) {
                throw new Exception('Setting must be an instance of setting or be an id');
            }

            if (is_object($object)) {
                $object = $object->id;
            }

            if (!is_numeric($object)) {
                throw new Exception(ucfirst($type) . ' must be an instance of ' . $type . ' or be an id');
            }

            $relation = bigDb('setting')
            ->where(['setting_id', '=', $setting])
            ->where(['type', '=', $type])
            ->where(['type_id', '=', $object])
            ->first(true);

            if ($relation) {
                return $relation->delete();
            }

            return false;
        }

        function settingBelongsTo($setting, $object, $type)
        {
            if (is_object($setting)) {
                $setting = $setting->id;
            }

            if (!is_numeric($setting)) {
                throw new Exception('Setting must be an instance of setting or be an id');
            }

            if (is_object($object)) {
                $object = $object->id;
            }

            if (!is_numeric($object)) {
                throw new Exception(ucfirst($type) . ' must be an instance of ' . $type . ' or be an id');
            }

            $relation = bigDb('setting')
            ->where(['setting_id', '=', $setting])
            ->where(['type', '=', $type])
            ->where(['type_id', '=', $object])
            ->first(true);

            return $relation ? true : false;
        }

        function getSettingValue($setting, $object, $type)
        {
            if (is_object($setting)) {
                $setting = $setting->id;
            }

            if (!is_numeric($setting)) {
                throw new Exception('Setting must be an instance of setting or be an id');
            }

            if (is_object($object)) {
                $object = $object->id;
            }

            if (!is_numeric($object)) {
                throw new Exception(ucfirst($type) . ' must be an instance of ' . $type . ' or be an id');
            }

            $relation = bigDb('setting')
            ->where(['setting_id', '=', $setting])
            ->where(['type', '=', $type])
            ->where(['type_id', '=', $object])
            ->first(true);

            return $relation ? $relation->value : null;
        }

        function getAllSettings($object, $type)
        {
            if (is_object($setting)) {
                $setting = $setting->id;
            }

            if (!is_numeric($setting)) {
                throw new Exception('Setting must be an instance of setting or be an id');
            }

            if (is_object($object)) {
                $object = $object->id;
            }

            if (!is_numeric($object)) {
                throw new Exception(ucfirst($type) . ' must be an instance of ' . $type . ' or be an id');
            }

            $relations = bigDb('setting')
            ->where(['type', '=', $type])
            ->where(['type_id', '=', $object])
            ->exec(true);

            $collection = [];

            if (!empty($relations)) {
                foreach ($relations as $relation) {
                    $setting = $relation->setting(true);

                    if ($setting) {
                        array_push($collection, ['name' => $setting->code, 'value' => $relation->value]);
                    }
                }
            }

            return $collection;
        }

        function addSubscriptionToReseller($subscription, $reseller)
        {
            if (is_object($reseller)) {
                $reseller = $reseller->id;
            }

            if (!is_numeric($reseller)) {
                throw new Exception('Reseller must be an instance of reseller or be an id');
            }

            if (is_object($subscription)) {
                $subscription = $subscription->id;
            }

            if (!is_numeric($subscription)) {
                throw new Exception('Subscription must be an instance of subscription or be an id');
            }

            return bigDb('subscription')
            ->firstOrCreate([
                'type'      => 'reseller',
                'type_id'   => $reseller
            ])->setSubscriptionId($subscription)->save();
        }

        function removeSubscriptionToReseller($subscription, $reseller)
        {
            if (is_object($reseller)) {
                $reseller = $reseller->id;
            }

            if (!is_numeric($reseller)) {
                throw new Exception('Reseller must be an instance of reseller or be an id');
            }

            if (is_object($subscription)) {
                $subscription = $subscription->id;
            }

            if (!is_numeric($subscription)) {
                throw new Exception('Subscription must be an instance of subscription or be an id');
            }

            $relation = bigDb('subscription')
            ->where(['subscription_id', '=', $subscription])
            ->where(['type', '=', 'reseller'])
            ->where(['type_id', '=', $reseller])
            ->first(true);

            if ($relation) {
                return $relation->delete();
            }

            return false;
        }

        function subscriptionBelongsToReseller($subscription, $reseller)
        {
            if (is_object($reseller)) {
                $reseller = $reseller->id;
            }

            if (!is_numeric($reseller)) {
                throw new Exception('Reseller must be an instance of reseller or be an id');
            }

            if (is_object($subscription)) {
                $subscription = $subscription->id;
            }

            if (!is_numeric($subscription)) {
                throw new Exception('Subscription must be an instance of subscription or be an id');
            }

            $relation = bigDb('subscription')
            ->where(['subscription_id', '=', $subscription])
            ->where(['type', '=', 'reseller'])
            ->where(['type_id', '=', $reseller])
            ->first(true);

            return $relation ? true : false;
        }

        function getSubscriptionByReseller($reseller, $object = false)
        {
            if (is_object($reseller)) {
                $reseller = $reseller->id;
            }

            if (!is_numeric($reseller)) {
                throw new Exception('Reseller must be an instance of reseller or be an id');
            }

            $relation = bigDb('optionmarketingreseller')
            ->where(['type', '=', 'reseller'])
            ->where(['type_id', '=', $reseller])
            ->first(true);

            if ($relation) {
                $subscription = $relation->subscription(true);

                if ($subscription) {
                    return !$object ? $subscription->assoc() : $subscription;
                }
            }

            return false;
        }

        function addOptionMarketingToReseller($optionmarketing, $reseller, $status_id = null)
        {
            $status_id = is_null($status_id) ? getStatus('ABO') : $status_id;

            if (is_object($reseller)) {
                $reseller = $reseller->id;
            }

            if (!is_numeric($reseller)) {
                throw new Exception('Reseller must be an instance of reseller or be an id');
            }

            if (is_object($optionmarketing)) {
                $optionmarketing = $optionmarketing->id;
            }

            if (!is_numeric($optionmarketing)) {
                throw new Exception('Optionmarketing must be an instance of optionmarketing or be an id');
            }

            return bigDb('optionmarketingreseller')
            ->firstOrCreate([
                'optionmarketing_id'    => $optionmarketing,
                'reseller_id'           => $reseller
            ])->setStatusId($status_id)->save();
        }

        function removeOptionMarketingToReseller($optionmarketing, $reseller)
        {
            if (is_object($reseller)) {
                $reseller = $reseller->id;
            }

            if (!is_numeric($reseller)) {
                throw new Exception('Reseller must be an instance of reseller or be an id');
            }

            if (is_object($optionmarketing)) {
                $optionmarketing = $optionmarketing->id;
            }

            if (!is_numeric($optionmarketing)) {
                throw new Exception('Optionmarketing must be an instance of optionmarketing or be an id');
            }

            $relation = bigDb('optionmarketingreseller')
            ->where(['optionmarketing_id', '=', $segment])
            ->where(['reseller_id', '=', $reseller])
            ->first(true);

            if ($relation) {
                return $relation->delete();
            }

            return false;
        }

        function optionMarketingBelongsToReseller($optionmarketing, $reseller)
        {
            if (is_object($reseller)) {
                $reseller = $reseller->id;
            }

            if (!is_numeric($reseller)) {
                throw new Exception('Reseller must be an instance of reseller or be an id');
            }

            if (is_object($optionmarketing)) {
                $optionmarketing = $optionmarketing->id;
            }

            if (!is_numeric($optionmarketing)) {
                throw new Exception('Optionmarketing must be an instance of optionmarketing or be an id');
            }

            $relation = bigDb('optionmarketingreseller')
            ->where(['optionmarketing_id', '=', $segment])
            ->where(['reseller_id', '=', $reseller])
            ->first(true);

            return $relation ? true : false;
        }

        function getoptionsMarketingByReseller($reseller)
        {
            $collection = [];

            if (is_object($reseller)) {
                $reseller = $reseller->id;
            }

            if (!is_numeric($reseller)) {
                throw new Exception('Reseller must be an instance of reseller or be an id');
            }

            $relations = bigDb('optionmarketingreseller')->where(['reseller_id', '=', $reseller])->exec();

            if (!empty($relations)) {
                foreach ($relations as $relation) {
                    $optionmarketing = isake($relation, 'optionmarketing_id', 0);

                    if ($optionmarketing > 0) {
                        array_push($collection, bigDb('optionmarketing')->find($optionmarketing)->assoc());
                    }
                }
            }

            return $collection;
        }

        function addZoneToReseller($reseller, $infos = [], $zone = null)
        {
            if (is_object($reseller)) {
                $reseller = $reseller->id;
            }

            if (!is_numeric($reseller)) {
                throw new Exception('Reseller must be an instance of reseller or be an id');
            }

            $zone = empty($zone) ? getZone('CUSTOM') : $zone;

            if (is_object($zone)) {
                $zone = $zone->id;
            }

            if (!is_numeric($zone)) {
                throw new Exception('Zone must be an instance of zone or be an id');
            }

            if (empty($infos)) {
                return bigDb('zonereseller')->firstOrCreate([
                    'zone_id'       => $zone,
                    'reseller_id'   => $reseller
                ]);
            } else {
                $type = isAke($infos, 'type', false);

                if (false === $type) {
                    throw new Exception("You must provide a type for custum zone.");
                }

                $type = Inflector::lower($type);

                $typeId = isAke($infos, $type . '_id', false);

                if (false === $typeId) {
                    throw new Exception("You must provide a " . $type . "_id for custum zone.");
                }

                $distance = isAke($infos, 'distance', 0);

                if (!is_numeric($distance)) {
                    $distance = 0;
                }

                $distance = (int) $distance;

                $distance = $distance > 1000 ? 1000 : $distance;

                $customzonereseller = bigDb('customzonereseller')->create([
                    'type'      => $type,
                    'distance'  => $distance,
                    'type_id'   => $typeId
                ])->save();

                $zoneR = bigDb('zonereseller')->create([
                    'customzonereseller_id' => $customzonereseller->id,
                    'zone_id'               => $zone,
                    'reseller_id'           => $reseller
                ])->save();

                if ($type == 'city') {
                    lib('queue')->pushlib('utils', 'cityzone', [$typeId, $reseller, $zoneR->id, $distance]);
                    lib('queue')->background();
                    // $city = Model::City()->find($typeId);

                    // if ($city) {
                    //     $tab = $city->assoc();
                    //     $tab['distance'] = ['km' => 0, 'miles' => 0];
                    //     $near = [$tab];
                    //     $near = array_merge($near, getNearCitiesFromZip($city->zip, $distance));

                    //     foreach ($near as $c) {
                    //         $cz = Model::Cityzone()->firstOrCreate([
                    //             'zone_id'       => (int) $zoneR->id,
                    //             'reseller_id'   => (int) $reseller,
                    //             'zip'           => (string) $c['zip'],
                    //             'city_id'       => (int) $c['id'],
                    //             'distance'      => (float) $c['distance']['km']
                    //         ]);
                    //     }
                    // }
                }

                return $zone;
            }
        }

        function removeZoneToReseller($zone, $reseller, $infos = [])
        {
            if (is_object($reseller)) {
                $reseller = $reseller->id;
            }

            if (!is_numeric($reseller)) {
                throw new Exception('Reseller must be an instance of reseller or be an id');
            }

            if (is_object($zone)) {
                $zone = $zone->id;
            }

            if (!is_numeric($zone)) {
                throw new Exception('Zone must be an instance of zone or be an id');
            }

            if (empty($infos)) {
                $relation = bigDb('zonereseller')
                ->where(['zone_id', '=', $zone])
                ->where(['reseller_id', '=', $reseller])
                ->first(true);

                if ($relation) {
                    return $relation->delete();
                }
            } else {
                $relations = bigDb('zonereseller')
                ->where(['zone_id', '=', $zone])
                ->where(['reseller_id', '=', $reseller])
                ->exec(true);

                if (!empty($relations)) {
                    foreach ($relations as $relation) {
                        $r = $relation->assoc();
                        $customzonereseller_id = isAke($r, 'customzonereseller_id', false);

                        if (false !== $customzonereseller_id) {
                            $cz = bigDb('customzonereseller')->find($customzonereseller_id);

                            $type = isAke($infos, 'type', false);

                            if (false === $type) {
                                throw new Exception("You must provide a type for custum zone.");
                            }

                            $type = Inflector::lower($type);

                            $typeId = isAke($infos, $type . '_id', false);

                            if (false === $typeId) {
                                throw new Exception("You must provide a " . $type . "_id for custum zone.");
                            }

                            $distance = isAke($info, 'distance', 0);

                            if ($distance == $cz->distance && $type == $cz->type && $typeId == $cz->type_id) {
                                return $relation->delete();
                            }
                        }
                    }
                }
            }

            return false;
        }

        function zoneBelongsToReseller($zone, $reseller, $infos = [])
        {
            if (is_object($reseller)) {
                $reseller = $reseller->id;
            }

            if (!is_numeric($reseller)) {
                throw new Exception('Reseller must be an instance of reseller or be an id');
            }

            if (is_object($zone)) {
                $zone = $zone->id;
            }

            if (!is_numeric($zone)) {
                throw new Exception('Zone must be an instance of zone or be an id');
            }

            if (empty($infos)) {
                $relation = bigDb('zonereseller')
                ->where(['zone_id', '=', $zone])
                ->where(['reseller_id', '=', $reseller])
                ->first(true);

                if ($relation) {
                    return true;
                }
            } else {
                $relations = bigDb('zonereseller')
                ->where(['zone_id', '=', $zone])
                ->where(['reseller_id', '=', $reseller])
                ->exec(true);

                if (!empty($relations)) {
                    foreach ($relations as $relation) {
                        $r = $relation->assoc();
                        $customzonereseller_id = isAke($r, 'customzonereseller_id', false);

                        if (false !== $customzonereseller_id) {
                            $cz = bigDb('customzonereseller')->find($customzonereseller_id);

                            $type = isAke($infos, 'type', false);

                            if (false === $type) {
                                throw new Exception("You must provide a type for custum zone.");
                            }

                            $type = Inflector::lower($type);

                            $typeId = isAke($infos, $type . '_id', false);

                            if (false === $typeId) {
                                throw new Exception("You must provide a " . $type . "_id for custum zone.");
                            }

                            $distance = isAke($info, 'distance', 0);

                            if ($distance == $cz->distance && $type == $cz->type && $typeId == $cz->type_id) {
                                return true;
                            }
                        }
                    }
                }
            }

            return false;
        }

        function getZones()
        {
            return [
                ['id' => getZone('INTERNATIONAL'), 'name' => 'International'],
                ['id' => getZone('NATIONAL'), 'name' => 'National'],
            ];
        }

        function getZonesByReseller($reseller)
        {
            if (is_object($reseller)) {
                $reseller = $reseller->id;
            }

            if (!is_numeric($reseller)) {
                throw new Exception('Reseller must be an instance of reseller or be an id');
            }

            $collection = [];

            $relations = bigDb('zonereseller')
            ->where(['reseller_id', '=', $reseller])
            ->exec();

            if (!empty($relations)) {
                foreach ($relations as $relation) {
                    $item = [];
                    $customzonereseller_id = isAke($relation, 'customzonereseller_id', false);

                    if (false !== $customzonereseller_id) {
                        $cz = bigDb('customzonereseller')->find($customzonereseller_id);

                        if ($cz) {
                            $cz     = $cz->assoc();
                            $type   = isAke($cz, 'type', false);

                            if (false !== $type) {
                                $item['code']                   = 'CUSTOM';
                                $item['infos']                  = [];
                                $item['infos']['type']          = $type;
                                $item['infos'][$type . '_id']   = isAke($cz, 'type_id', false);
                                $item['infos']['distance']      = isAke($cz, 'distance', false);

                                array_push($collection, $item);
                            }
                        }
                    } else {
                        $zone_id = isAke($relation, 'zone_id', false);

                        if (false !== $zone_id) {
                            $zone = bigDb('zone')->find($zone_id);

                            if ($zone) {
                                $item['code'] = $zone->code;
                                array_push($collection, $item);
                            }
                        }
                    }
                }
            }

            return $collection;
        }

        function addSegmentToReseller($segment, $reseller)
        {
            if (is_object($reseller)) {
                $reseller = $reseller->id;
            }

            if (!is_numeric($reseller)) {
                throw new Exception('Reseller must be an instance of reseller or be an id');
            }

            if (is_object($segment)) {
                $segment = $segment->id;
            }

            if (!is_numeric($segment)) {
                throw new Exception('Segment must be an instance of segment or be an id');
            }

            $market = current(repo('segment')->getFamilyfromItem($segment));
            $market = isAke($market, 'id', $segment);

            return bigDb('segmentreseller')->firstOrCreate(['segment_id' => $segment, 'reseller_id' => $reseller, 'market' => $market]);
        }

        function removeSegmentToReseller($segment, $reseller)
        {
            if (is_object($reseller)) {
                $reseller = $reseller->id;
            }

            if (!is_numeric($reseller)) {
                throw new Exception('Reseller must be an instance of reseller or be an id');
            }

            if (is_object($segment)) {
                $segment = $segment->id;
            }

            if (!is_numeric($segment)) {
                throw new Exception('Segment must be an instance of segment or be an id');
            }

            $relation = bigDb('segmentreseller')
            ->where(['segment_id', '=', $segment])
            ->where(['reseller_id', '=', $reseller])
            ->first(true);

            if ($relation) {
                return $relation->delete();
            }

            return false;
        }

        function segmentBelongsToReseller($segment, $reseller)
        {
            if (is_object($reseller)) {
                $reseller = $reseller->id;
            }

            if (!is_numeric($reseller)) {
                throw new Exception('Reseller must be an instance of reseller or be an id');
            }

            if (is_object($segment)) {
                $segment = $segment->id;
            }

            if (!is_numeric($segment)) {
                throw new Exception('Segment must be an instance of segment or be an id');
            }

            $relation = bigDb('segmentreseller')
            ->where(['segment_id', '=', $segment])
            ->where(['reseller_id', '=', $reseller])
            ->first(true);

            return $relation ? true : false;
        }

        function getSegmentsByReseller($reseller)
        {
            $collection = [];

            if (is_object($reseller)) {
                $reseller = $reseller->id;
            }

            if (!is_numeric($reseller)) {
                throw new Exception('Reseller must be an instance of reseller or be an id');
            }

            $relations = bigDb('segmentreseller')->where(['reseller_id', '=', $reseller])->exec();

            if (!empty($relations)) {
                foreach ($relations as $relation) {
                    $segment = isake($relation, 'segment_id', 0);

                    if ($segment > 0) {
                        array_push($collection, $segment);
                    }
                }
            }

            return $collection;
        }

        function addSegmentToEmployee($segment, $reselleremployee, $responsibility, $reseller)
        {
            if (is_object($reselleremployee)) {
                $reselleremployee = $reselleremployee->id;
            }

            if (!is_numeric($reselleremployee)) {
                throw new Exception('Reselleremployee must be an instance of reselleremployee or be an id');
            }

            if (is_object($reseller)) {
                $reseller = $reseller->id;
            }

            if (!is_numeric($reseller)) {
                throw new Exception('Reseller must be an instance of reseller or be an id');
            }

            if (is_object($segment)) {
                $segment = $segment->id;
            }

            if (!is_numeric($segment)) {
                throw new Exception('Segment must be an instance of segment or be an id');
            }

            if (is_object($responsibility)) {
                $responsibility = $responsibility->id;
            }

            if (!is_numeric($responsibility)) {
                throw new Exception('Responsibility must be an instance of responsibility or be an id');
            }

            $re = bigDb('reselleremployee')->find($reselleremployee);

            if ($re) {
                $check = (int) $re->reseller_id == (int) $reseller;

                if (true === $check) {
                    bigDb('segmentreselleremployee')->firstOrCreate([
                        'segment_id' => $segment,
                        'reselleremployee_id' => $reselleremployee
                    ])->setResponsibilityId($responsibility)->save();

                    return true;
                }
            }

            return false;
        }

        function employeeBelongsToReseller($reselleremployee, $reseller)
        {
            if (is_object($reselleremployee)) {
                $reselleremployee = $reselleremployee->id;
            }

            if (!is_numeric($reselleremployee)) {
                throw new Exception('Reselleremployee must be an instance of reselleremployee or be an id');
            }

            if (is_object($reseller)) {
                $reseller = $reseller->id;
            }

            if (!is_numeric($reseller)) {
                throw new Exception('Reseller must be an instance of reseller or be an id');
            }

            $re = bigDb('reselleremployee')->find($reselleremployee);

            if ($re) {
                return (int) $re->reseller_id == (int) $reseller;
            }

            return false;
        }

        function removeSegmentToEmployee($segment, $reselleremployee)
        {
            if (is_object($reselleremployee)) {
                $reselleremployee = $reselleremployee->id;
            }

            if (!is_numeric($reselleremployee)) {
                throw new Exception('Reselleremployee must be an instance of reselleremployee or be an id');
            }

            if (is_object($segment)) {
                $segment = $segment->id;
            }

            if (!is_numeric($segment)) {
                throw new Exception('Segment must be an instance of segment or be an id');
            }

            $relation = bigDb('segmentreselleremployee')
            ->where(['segment_id', '=', $segment])
            ->where(['reselleremployee_id', '=', $reselleremployee])
            ->first(true);

            if ($relation) {
                return $relation->delete();
            }

            return false;
        }

        function segmentBelongsToEmployee($segment, $reselleremployee, $reseller)
        {
            if (is_object($reselleremployee)) {
                $reselleremployee = $reselleremployee->id;
            }

            if (!is_numeric($reselleremployee)) {
                throw new Exception('Reselleremployee must be an instance of reselleremployee or be an id');
            }

            if (is_object($reseller)) {
                $reseller = $reseller->id;
            }

            if (!is_numeric($reseller)) {
                throw new Exception('reseller must be an instance of reseller or be an id');
            }

            if (is_object($segment)) {
                $segment = $segment->id;
            }

            if (!is_numeric($segment)) {
                throw new Exception('Segment must be an instance of segment or be an id');
            }

            $check = employeeBelongsToReseller($reseller, $reselleremployee);

            if (!$check) {
                return false;
            }

            $relation = bigDb('segmentreselleremployee')
            ->where(['segment_id', '=', $segment])
            ->where(['reselleremployee_id', '=', $reselleremployee])
            ->first(true);

            return $relation ? true : false;
        }

        function getResponsibilityByEmployeeAndBySegment($reselleremployee, $segment, $reseller)
        {
            if (is_object($reselleremployee)) {
                $reselleremployee = $reselleremployee->id;
            }

            if (!is_numeric($reselleremployee)) {
                throw new Exception('Reselleremployee must be an instance of reselleremployee or be an id');
            }

            if (is_object($reseller)) {
                $reseller = $reseller->id;
            }

            if (!is_numeric($reseller)) {
                throw new Exception('reseller must be an instance of reseller or be an id');
            }

            if (is_object($segment)) {
                $segment = $segment->id;
            }

            if (!is_numeric($segment)) {
                throw new Exception('Segment must be an instance of segment or be an id');
            }

            $check = employeeBelongsToReseller($reseller, $reselleremployee);

            if (!$check) {
                return false;
            }

            $relation = bigDb('segmentreselleremployee')
            ->where(['segment_id', '=', $segment])
            ->where(['reselleremployee_id', '=', $reselleremployee])
            ->first(true);

            if ($relation) {
                $relation = $relation->assoc();

                $responsibility_id = isAke($relation, 'responsibility_id', 0);

                if ($responsibility_id > 0) {
                    $responsibility = bigDb('responsibility')->find($responsibility_id);

                    if ($responsibility) {
                        return ucfirst(Inflector::lower($responsibility->code));
                    }
                }
            }

            return false;
        }

        function getEmployeesBySegment($segment, $reseller)
        {
            if (is_object($segment)) {
                $segment = $segment->id;
            }

            if (!is_numeric($segment)) {
                throw new Exception('Segment must be an instance of segment or be an id');
            }

            if (is_object($reseller)) {
                $reseller = $reseller->id;
            }

            if (!is_numeric($reseller)) {
                throw new Exception('Reseller must be an instance of reseller or be an id');
            }

            $relations = bigDb('segmentreselleremployee')
            ->where(['segment_id', '=', $segment])
            ->exec();

            $collection = [];

            if (!empty($relations)) {
                foreach ($relations as $relation) {
                    $reselleremployee_id = isAke($relation, 'reselleremployee_id', 0);
                    $responsibility_id = isAke($relation, 'responsibility_id', 0);

                    if ($reselleremployee_id > 0 && $responsibility_id > 0) {
                        $employee = bigDb('reselleremployee')->find($reselleremployee_id);
                        $responsibility = bigDb('responsibility')->find($responsibility_id);

                        if ($employee && $responsibility) {
                            $employee = $employee->assoc();

                            $reseller_id = isAke($employee, 'reseller_id', 0);
                            $employee['responsibility'] = ucfirst(Inflector::lower($responsibility->code));

                            if ($reseller_id == $reseller) {
                                array_push($collection, $employee);
                            }
                        }
                    }
                }
            }

            return $collection;
        }

        function getSegmentsByEmployee($reselleremployee)
        {
            if (is_object($reselleremployee)) {
                $reselleremployee = $reselleremployee->id;
            }

            if (!is_numeric($reselleremployee)) {
                throw new Exception('Reselleremployee must be an instance of reselleremployee or be an id');
            }

            $relations = bigDb('segmentreselleremployee')
            ->where(['reselleremployee_id', '=', $reselleremployee])
            ->exec();

            $collection = [];

            if (!empty($relations)) {
                foreach ($relations as $relation) {
                    $responsibility_id  = isAke($relation, 'responsibility_id', 0);
                    $segment_id         = isAke($relation, 'segment_id', 0);

                    if ($segment_id > 0 && $responsibility_id > 0) {
                        $responsibility = bigDb('responsibility')->find($responsibility_id);

                        array_push(
                            $collection,
                            [
                                'segment_id' => $segment_id,
                                'responsibility' => ucfirst(Inflector::lower($responsibility->code))
                            ]
                        );
                    }
                }
            }

            return $collection;
        }

        function addManagerToEmployee($manager, $reselleremployee)
        {
            if (is_object($reselleremployee)) {
                $reselleremployee = $reselleremployee->id;
            }

            if (!is_numeric($reselleremployee)) {
                throw new Exception('Reselleremployee must be an instance of reselleremployee or be an id');
            }

            if (is_object($manager)) {
                $manager = $manager->id;
            }

            if (!is_numeric($manager)) {
                throw new Exception('Manager must be an instance of reselleremployee or be an id');
            }

            $relations = bigDb('segmentreselleremployee')
            ->where(['reselleremployee_id', '=', $segment])
            ->exec();

            if (!empty($relations)) {
                foreach ($relations as $relation) {
                    bigDb('segmentreselleremployee')->firstOrCreate([
                        'segment_id' => $relation->segment_id,
                        'reselleremployee_id' => $manager
                    ])->setResponsibilityId(getResponsibility('manager'))->save();
                }
            }
        }

        function addHabilitationToEmployee($habilitation, $reselleremployee, $reseller, $value = true)
        {
            if (is_object($reselleremployee)) {
                $reselleremployee = $reselleremployee->id;
            }

            if (!is_numeric($reselleremployee)) {
                throw new Exception('Reselleremployee must be an instance of reselleremployee or be an id');
            }

            if (is_object($reseller)) {
                $reseller = $reseller->id;
            }

            if (!is_numeric($reseller)) {
                throw new Exception('Reseller must be an instance of reseller or be an id');
            }

            if (is_object($habilitation)) {
                $habilitation = $habilitation->id;
            }

            if (!is_numeric($habilitation)) {
                throw new Exception('Habilitation must be an instance of habilitation or be an id');
            }

            $re = bigDb('reselleremployee')->find($reselleremployee);

            if ($re) {
                $check = (int) $re->reseller_id == (int) $reseller;

                if (true === $check) {
                    bigDb('habilitationreselleremployee')->firstOrCreate([
                        'habilitation_id' => $habilitation,
                        'reselleremployee_id' => $reselleremployee,
                        'value' => $value
                    ]);

                    return true;
                }
            }

            return false;
        }

        function removeHabilitationToEmployee($habilitation, $reselleremployee)
        {
            if (is_object($reselleremployee)) {
                $reselleremployee = $reselleremployee->id;
            }

            if (!is_numeric($reselleremployee)) {
                throw new Exception('Reselleremployee must be an instance of reselleremployee or be an id');
            }

            if (is_object($habilitation)) {
                $habilitation = $habilitation->id;
            }

            if (!is_numeric($habilitation)) {
                throw new Exception('Habilitation must be an instance of habilitation or be an id');
            }

            $relation = bigDb('habilitationreselleremployee')
            ->where(['habilitation_id', '=', $habilitation])
            ->where(['reselleremployee_id', '=', $reselleremployee])
            ->first(true);

            if ($relation) {
                return $relation->delete();
            }

            return false;
        }

        function habilitationBelongsToEmployee($habilitation, $reselleremployee, $reseller)
        {
            if (is_object($reselleremployee)) {
                $reselleremployee = $reselleremployee->id;
            }

            if (!is_numeric($reselleremployee)) {
                throw new Exception('Reselleremployee must be an instance of reselleremployee or be an id');
            }

            if (is_object($habilitation)) {
                $habilitation = $habilitation->id;
            }

            if (!is_numeric($habilitation)) {
                throw new Exception('Habilitation must be an instance of habilitation or be an id');
            }

            if (is_object($reseller)) {
                $reseller = $reseller->id;
            }

            if (!is_numeric($reseller)) {
                throw new Exception('Reseller must be an instance of reseller or be an id');
            }

            $relation = bigDb('habilitationreselleremployee')
            ->where(['habilitation_id', '=', $habilitation])
            ->where(['reselleremployee_id', '=', $reselleremployee])
            ->first(true);

            return $relation ? $relation->value : true;
        }

        function getHabilitationsByEmployee($reselleremployee, $reseller)
        {
            if (is_object($reselleremployee)) {
                $reselleremployee = $reselleremployee->id;
            }

            if (!is_numeric($reselleremployee)) {
                throw new Exception('Reselleremployee must be an instance of reselleremployee or be an id');
            }

            if (is_object($reseller)) {
                $reseller = $reseller->id;
            }

            if (!is_numeric($reseller)) {
                throw new Exception('Reseller must be an instance of reseller or be an id');
            }

            $relations = bigDb('habilitationreselleremployee')
            ->where(['reselleremployee_id', '=', $reselleremployee])
            ->exec();

            $collection = [];

            if (!empty($relations)) {
                foreach ($relations as $relation) {
                    $habilitation_id  = isAke($relation, 'habilitation_id', 0);

                    if ($habilitation_id > 0) {
                        $habilitation = bigDb('habilitation')->find($habilitation_id);

                        array_push(
                            $collection,
                            [
                                'habilitation_id' => $habilitation_id,
                                'habilitation' => $habilitation->code
                            ]
                        );
                    }
                }
            }

            return $collection;
        }
    }

    if (!function_exists('addUserToReseller')) {
        function addUserToReseller($user, $reseller)
        {
            if (is_object($user)) {
                $user = $user->id;
            }

            if (!is_numeric($user)) {
                throw new Exception('User must be an instance of people or be an id');
            }

            if (!is_object($reseller)) {
                if (is_numeric($reseller)) {
                    $reseller = bigDb('reseller')->findOrFail($reseller);
                } else {
                    throw new Exception('Reseller must be an instance of reseller or be an id');
                }
            }

            return opt($reseller)->setMultiple('member', $user);
        }
    }

    if (!function_exists('message')) {
        function message($what = null)
        {
            /* Polymorphism */
            if (is_null($what)) {
                return bigDb('message');
            }

            if (is_numeric($what)) {
                return bigDb('message')->find($id);
            } elseif (Arrays::is($what)) {
                return bigDb('message')->create($what)->save();
            }
        }
    }

    if (!function_exists('getMessage')) {
        function getMessage($id)
        {
            return bigDb('message')->find($id);
        }

        function getSomethingFromObject($table, $object)
        {
            if (!is_object($object)) {
                throw new Exception("The 'object' argument is not a model.");
            }

            if ($object instanceof Container) {
                $motor = 'dbjson';
            } else {
                $motor = 'dbredis';
            }

            $collection = [];

            $datas = bigDb('link')
            ->where(['motor_from', '=', $motor])
            ->where(['motor_to', '=', 'dbredis'])
            ->where(['database_from', '=', $object->db()->db])
            ->where(['database_to', '=', SITE_NAME])
            ->where(['table_from', '=', $object->db()->table])
            ->where(['table_to', '=', $table])
            ->where(['id_from', '=', $object->id])
            ->exec();

            foreach ($datas as $data) {
                $row = bigDb($table)->findOrFail($data['id_to']);
                array_push($collection, $row);
            }

            return $collection;
        }

        function getMessagesFromOffer($offer)
        {
            return getSomethingFromObject('message', $offer);
        }

        function getUpliftsFromUser($user)
        {
            return getSomethingFromObject('uplift', $user);
        }
    }

    if (!function_exists('getUser')) {
        function getUser($id)
        {
            return Thin\Model::People()->find($id);
        }
    }

    if (!function_exists('sendMessage')) {
        function sendMessage($data = [], $toLink = null)
        {
            /*
                $data = [
                    'from' => $user->id,
                    'to' => [users ids (un ou plusieurs)],
                    'subject' => "sujet du message",
                    'body' => "corps du message",
                    'priority' => "integer 1[basse], 2[normale] ou 3[haute]",
                    'join' => [pièces jointes sous forme d'url],
                ];

             */

            $priority = isAke($data, 'priority', 2);
            $data['priority'] = $priority;

            $message = bigDb('message')->create($data)->save();

            if (!is_null($toLink)) {
                if (is_object($toLink)) {
                    linkTo($toLink, $message);
                }
            }

            return true;
        }
    }

    if (!function_exists('entity')) {
        function entity($name, $data = [], $save = true, $db = null)
        {
            $db = is_null($db) ? SITE_NAME : $db;

            $obj = bigDb($name, $db)->create($data);

            if (true === $save) {
                return $obj->save();
            }

            return $obj;
        }
    }

    if (!function_exists('setOffer')) {
        function setOffer($type, $data = [])
        {
            $auth = ['in', 'out'];

            if (!arrays::in($type, $auth)) {
                throw new Exception('An offer must be "in" or "out".');
            }

            $offer = bigDb('offer' . $type)->create($data)->save();
            opt($offer)->set('status', 1);

            return $offer;
        }

        function getOffer($type, $id)
        {
            $auth = ['in', 'out'];

            if (!arrays::in($type, $auth)) {
                throw new Exception('An offer must be "in" or "out".');
            }

            return bigDb('offer' . $type)->find($id);
        }

        function getOffers($type, $user)
        {
            $collection = [];

            if (is_object($user)) {
                $user = $user->id;
            }

            if (!is_numeric($user)) {
                throw new Exception('People must be an instance of people or be an id');
            }

            $auth = ['in', 'out'];

            if (!arrays::in($type, $auth)) {
                throw new Exception('An offer must be "in" or "out".');
            }

            $offers = bigDb('offer' . $type)->where(['people_id', '=', $user])->exec(true);

            foreach ($offers as $offer) {
                $status = opt($offer)->get('status');

                if (1 == $status) {
                    array_push($collection, $offer->assoc());
                }
            }

            return $collection;
        }

        function setDraft($type, $data = [])
        {
            $auth = ['in', 'out'];

            if (!arrays::in($type, $auth)) {
                throw new Exception('A draft must be "in" or "out".');
            }

            $draft = bigDb('draft' . $type)->create($data)->save();
            opt($draft)->set('status', 1);

            return $draft;
        }

        function getDraft($type, $id)
        {
            $auth = ['in', 'out'];

            if (!arrays::in($type, $auth)) {
                throw new Exception('A draft must be "in" or "out".');
            }

            return bigDb('draft' . $type)->find($id);
        }

        function draft($type)
        {
            $auth = ['in', 'out'];

            if (!arrays::in($type, $auth)) {
                throw new Exception('A draft must be "in" or "out".');
            }

            return bigDb('draft' . $type);
        }

        function offer($type)
        {
            $auth = ['in', 'out'];

            if (!arrays::in($type, $auth)) {
                throw new Exception('An offer must be "in" or "out".');
            }

            return bigDb('offer' . $type);
        }

        function getDrafts($type, $user)
        {
            $collection = [];

            if (is_object($user)) {
                $user = $user->id;
            }

            if (!is_numeric($user)) {
                throw new Exception('People must be an instance of people or be an id');
            }

            $auth = ['in', 'out'];

            if (!arrays::in($type, $auth)) {
                throw new Exception('An offer must be "in" or "out".');
            }

            $drafts = bigDb('draft' . $type)->where(['people_id', '=', $user])->exec(true);

            foreach ($drafts as $draft) {
                $status = opt($draft)->get('status', 1);

                if (1 == $status) {
                    array_push($collection, $draft->assoc());
                }
            }

            return $collection;
        }

        function setOfferArticle($offer, $article = [])
        {
            if (is_object($offer)) {
                $offer = $offer->id;
            }

            if (!is_numeric($offer)) {
                throw new Exception('Offer must be an instance of offer or be an id');
            }

            $offer = (int) $offer;

            $article['offer_id'] = $offer;

            return bigDb('article')->create($article)->save();
        }

        function getOfferInArticles(array $offer, $object = false)
        {
            $collection = [];

            if (!empty($offer)) {
                foreach ($offer as $of) {
                    array_push($collection, bigDb('articlein')->where(['offerin_id', '=', $of['id']])->exec($object));
                }
            }

            return $collection;
        }

        function getOfferOutArticles(array $offer, $object = false)
        {
            $collection = [];

            if (!empty($offer)) {
                foreach ($offer as $of) {
                    array_push($collection, bigDb('articleout')->where(['offerin_out', '=', $of['id']])->exec($object));
                }
            }

            return $collection;
        }

        function getOfferArticlesQuery($offer)
        {
            if (is_object($offer)) {
                $offer = $offer->id;
            }

            if (!is_numeric($offer)) {
                throw new Exception('Offer must be an instance of offer or be an id');
            }

            $offer = (int) $offer;

            return bigDb('article')->where(['offer_id', '=', $offer]);
        }

        function setDraftArticle($draft, $article = [])
        {
            if (is_object($draft)) {
                $draft = $draft->id;
            }

            if (!is_numeric($draft)) {
                throw new Exception('Draft must be an instance of draft or be an id');
            }

            $draft = (int) $draft;

            $article['draft_id'] = $draft;

            return bigDb('article')->create($article)->save();
        }

        function getDraftArticles($draft, $object = false)
        {
            if (is_object($draft)) {
                $draft = $draft->id;
            }

            if (!is_numeric($draft)) {
                throw new Exception('Draft must be an instance of draft or be an id');
            }

            $draft = (int) $draft;

            return bigDb('article')->where(['draft_id', '=', $draft])->exec($object);
        }
    }

    if (!function_exists('setOptionObject')) {
        function setOptionObject($row, $name, $value, $flush = true)
        {
            if ($row instanceof Container) {
                $databaseRow = $row->db();

                if ($databaseRow instanceof DBJ) {
                    $option = jmodel('optionobject')->create([
                        'object_database'   => $databaseRow->db,
                        'object_table'      => $databaseRow->table,
                        'object_id'         => $row->id,
                        'name'              => $name,
                        'value'             => $value
                    ])->save();

                    return $option;
                }
            }

            return false;
        }

        function getOptionObject($row, $name, $default = null)
        {
            if ($row instanceof Container) {
                $databaseRow = $row->db();

                if ($databaseRow instanceof DBJ) {
                    $dbDb       = $databaseRow->db;
                    $dbTable    = $databaseRow->table;
                    $dbId       = $row->id;

                    $option = jmodel('optionobject')
                    ->where(['object_database', '=', $dbDb])
                    ->where(['object_table', '=', $dbTable])
                    ->where(['object_id', '=', $dbId])
                    ->where(['name', '=', $name])
                    ->first(true);

                    return !$option ? $default : $option->value;
                }
            }

            return $default;
        }
    }

    if (!function_exists('ph')) {
        function ph($service = null)
        {
            static $i;

            if (null === $i) {
                $i = new DI;
            }

            if (null !== $service) {
                return $i->get($service);
            }

            return $i;
        }
    }

    if (!function_exists('apcCache')) {
        function apcCache($ttl = 0)
        {
            $cache = new DataFrontend(
                [
                    "lifetime" => $ttl
                ]
            );

            return new ApcCache(
                $cache,
                [
                    "prefix" => 'apccache',
                ]
            );
        }
    }

    if (!function_exists('odm')) {
        function odm($database, $table)
        {
            if (MONGO_ACTIVE) {
                $has = Instance::has(
                    'mongoClient',
                    sha1(
                        serialize(
                            SITE_NAME . $database . $table
                        )
                    )
                );

                if (true === $has) {
                    $client = Instance::get(
                        'mongoClient',
                        sha1(
                            serialize(
                                SITE_NAME . $database . $table
                            )
                        )
                    );
                } else {
                    $client = Instance::make(
                        'mongoClient',
                        sha1(
                            serialize(
                                SITE_NAME . $database . $table
                            )
                        ),
                        new MC()
                    );
                }

                return $client->selectCollection($database, $table);
            }
        }
    }

    if (!function_exists('likeMongo')) {
        function likeMongo($string)
        {
            return new MRgx("/^" . $string . "/imxsu");
        }
    }

    if (!function_exists('flash')) {
        function flash($key, $value)
        {
            return Sessionstore::instance('session', 'flash')->set($key, $value);
        }

        function getFlash($key, $default = null)
        {
            return Sessionstore::instance('session', 'flash')->get($key, $default);
        }

        function getPrevFlash($key, $default = null)
        {
            return Sessionstore::instance('session', 'flashprev')->get($key, $default);
        }

        function hasFlash($key)
        {
            return Sessionstore::instance('session', 'flash')->has($key);
        }

        function flushFlash()
        {
            return Sessionstore::instance('session', 'flash')->duplicate(
                Sessionstore::instance('session', 'flashprev')
            )->flush();
        }
    }

    if (!function_exists('es')) {
        function es()
        {
            $has = Instance::has('elasticSearch', SITE_NAME . '_es');

            if (true === $has) {
                return Instance::get('elasticSearch', SITE_NAME . '_es');
            } else {
                return Instance::make(
                    'elasticSearch', SITE_NAME . '_es', new ESC([
                        'hosts' => Config::get('elasticsearch.hosts', ['127.0.0.1:9200'])
                    ])
                );
            }
        }
    }

    if (!function_exists('fwk')) {
        function fwk($universe, $action)
        {
            $subActions = explode('-', $action);

            $follow = '';

            if (count($subActions)) {
                foreach ($subActions as $subAction) {
                    $follow .= ucfirst(Inflector::lower($subAction)) . '\\';
                }

                $follow = substr($follow, 0, -1);

                $class = '\\ThinService\\Workflows\\' . ucfirst(Inflector::lower($universe)) . '\\' . $follow;

                return with(new $class);
            }
        }
    }

    if (!function_exists('bucket')) {
        function bucket($name = null)
        {
            static $i;

            $name = is_null($name) ? SITE_NAME : $name;

            if (null === $i) {
                $i = new Bucket($name);
            }

            return $i;
        }
    }

    if (!function_exists('appLog')) {
        function appLog($message, $type = 'info', $severity = 3)
        {
            return Em::SystemLog()
            ->create()
            ->setLogTime(date("Y-m-d H:i:s"))
            ->setType($type)
            ->setMessage($message)
            ->setSeverity($severity)
            ->save();
        }
    }

    if (!function_exists('dbLog')) {
        function dbLog($message, $severity = 3)
        {
            return appLog($message, 'db', $severity);
        }

        function staticLog($type, $message, $severity = 3)
        {
            return appLog($message, $type, $severity);
        }
    }

    function createUserWithOptions($user, $options = [])
    {
        $people = Model::People()->firstOrCreate($user);

        if (!empty($options)) {
            foreach ($people as $key => $option) {
                opt($people)->set($key, $option);
            }
        }

        return opt($people)->all();
    }

    function codeOption($code)
    {
        return Em::Typeoption()->firstOrCreate(['code' => $code, 'name' => $code]);
    }

    function getOption($table, $id, $option, $default = null, $database = null)
    {
        $database = empty($database) ? SITE_NAME : $database;

        $option = codeOption($option);

        if ($option) {
            $rowOption = Em::Generaloption()
            ->typeoption_id($option->id)
            ->object_id($id)
            ->object_table($table)
            ->object_database($database)
            ->first(true);

            if ($rowOption) {
                return $rowOption->value;
            }
        }

        return $default;
    }

    function getOptions($table, $id, $object = false, $database = null)
    {
        $database = empty($database) ? SITE_NAME : $database;

        $values = Em::Generaloption()
        ->object_id($id)
        ->object_table($table)
        ->object_database($database)
        ->exec();

        $options = [];

        if (!empty($values)) {
            foreach ($values as $value) {
                $option = Em::Typeoption()->find($value['typeoption_id'])->code;
                $options[$option] = $value['value'];
            }
        }

        return $options;
    }

    function setOption($table, $id, $option, $value, $actif = true, $database = null, $tuple = true)
    {
        $database = empty($database) ? SITE_NAME : $database;

        $option = codeOption($option);

        if ($actif) {
            $status = Em::Optionstatus()->firstOrCreate(['name' => 'Actif', 'code' => 'ACTIVE']);
        } else {
            $status = Em::Optionstatus()->firstOrCreate(['name' => 'Inactif', 'code' => 'INACTIVE']);
        }

        if (true === $tuple) {
            return Em::Generaloption()
            ->firstOrCreate([
                'optionstatus_id'   => $status->id,
                'typeoption_id'     => $option->id,
                'object_id'         => $id,
                'object_database'   => $database,
                'object_table'      => $table,
                'value'             => $value
            ]);
        } else {
            $gOption = Em::Generaloption()
            ->firstOrCreate([
                'optionstatus_id'   => $status->id,
                'typeoption_id'     => $option->id,
                'object_id'         => $id,
                'object_database'   => $database,
                'object_table'      => $table
            ]);

            return $gOption->setValue($value)->save();
        }
    }

    if (!function_exists('getUserOption')) {
        function getUserOption($id, $option, $default = null)
        {
            return getOption('people', $id, $option, $default);
        }
    }

    if (!function_exists('setUserOption')) {
        function setUserOption($id, $option, $value, $tuple = false)
        {
            return setOption('people', $id, $option, $value, true, SITE_NAME, $tuple);
        }
    }

    if (!function_exists('getCompanyOption')) {
        function getCompanyOption($id, $option, $default = null)
        {
            getOption('company', $id, $option, $default);
        }
    }

    if (!function_exists('getInOption')) {
        function getInOption($id, $option, $default = null)
        {
            getOption('workflowbuyoffer', $id, $option, $default);
        }
    }

    if (!function_exists('getOutOption')) {
        function getOutOption($id, $option, $default = null)
        {
            getOption('workflowselloffer', $id, $option, $default);
        }
    }

    if (!function_exists('getZeliftOption')) {
        function getZeliftOption($id, $option, $default = null)
        {
            getOption('zeliftposition', $id, $option, $default);
        }
    }

    if (!function_exists('getAllUserOptions')) {
        function getAllUserOptions($id, $object = false)
        {
            return getOptions('people', $id, $object);
        }
    }

    if (!function_exists('getAllCompanyOptions')) {
        function getAllCompanyOptions($id, $object = false)
        {
            return getOptions('company', $id, $object);
        }
    }

    if (!function_exists('getAllInOptions')) {
        function getAllInOptions($id, $object = false)
        {
            return getOptions('workflowbuyoffer', $id, $object);
        }
    }

    if (!function_exists('getAllOutOptions')) {
        function getAllOutOptions($id, $object = false)
        {
            return getOptions('workflowselloffer', $id, $object);
        }
    }

    if (!function_exists('getAllZeliftOptions')) {
        function getAllZeliftOptions($id, $object = false)
        {
            return getOptions('zeliftposition', $id, $object);
        }
    }

    if (!function_exists('orm')) {
        function orm($db = null, $table = null)
        {
            $db     = is_null($db) ? 'system' : $db;
            $table  = is_null($table) ? 'system' : $table;

            return jdb($db, $table);
        }
    }

    if (!function_exists('sql')) {
        function sql()
        {
            static $orm;

            if (null === $orm) {
                $orm = new DB;

                $orm->addConnection([
                    'driver'    => Config::get('database.adapter', 'mysql'),
                    'host'      => Config::get('database.host', 'localhost'),
                    'database'  => Config::get('database.dbname', SITE_NAME),
                    'username'  => Config::get('database.username', 'root'),
                    'password'  => Config::get('database.password', ''),
                    'charset'   => Config::get('database.charset', 'utf8'),
                    'collation' => Config::get('database.collation', 'utf8_unicode_ci'),
                    'prefix'    => Config::get('database.prefix', '')
                ]);

                $orm->setAsGlobal();

                $orm->bootEloquent();
            }

            return $orm;
        }
    }

    if (!function_exists('ts')) {
        function ts($date)
        {
            if (is_numeric($date)) {
                return $date;
            } elseif (preg_match('/^[0-9]{2}\/[0-9]{2}\/[0-9]{4}$/', $date)) {
                list($d, $m, $y) = explode('/', $date, 3);

                return mktime(0, 0, 0, $m, $d, $y);
            } elseif (preg_match('/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}$/', $date)) {
                list($y, $m, $d) = explode('-', $date, 3);

                return mktime(0, 0, 0, $m, $d, $y);
            } elseif (preg_match('/^[0-9]{2}\/[0-9]{2}\/[0-9]{4} [0-9]{2}\:[0-9]{2}$/', $date)) {
                $date = preg_replace('/[^0-9\/]/', '/', $date);
                list($d, $m, $y, $h, $i) = explode('/', $date, 5);

                return mktime($h, $i, 0, $m, $d, $y);
            } elseif (preg_match('/^[0-9]{2}\/[0-9]{2}\/[0-9]{2} [0-9]{2}\:[0-9]{2}\:[0-9]{2},[0-9]+$/', $date)) {
                $date = Arrays::first(explode(',', $date));
                $date = preg_replace('/[^0-9\/]/', '/', $date);
                list($d, $m, $y, $h, $i) = explode('/', $date, 5);

                return mktime($h, $i, 0, $m, $d, $y);
            } else {
                list($dateYMD, $hourHIS)    = explode(' ', $date, 2);
                list($year, $month, $day)   = explode('-', $dateYMD, 3);
                list($hour, $minute, $sec)  = explode(':', $hourHIS, 3);

                return mktime($hour, $minute, $sec, $month, $day, $year);
            }
        }
    }

    function zlmail(array $conf)
    {
        $to = isAke($conf, 'to', false);

        if (false !== $to) {
            $toName     = isAke($conf, 'to_name', $to);
            $from       = isAke($conf, 'from', 'contact@zelift.com');
            $fromName   = isAke($conf, 'from_name', 'ZeLift');
            $subject    = isAke($conf, 'subject', 'Message');
            $priority   = isAke($conf, 'priority', 3);
            $files      = isAke($conf, 'files', []);
            $embeds     = isAke($conf, 'embeds', []);

            $text       = isAke($conf, 'text', false);
            $html       = isAke($conf, 'html', false);

            if (false === $text && false === $html) {
                throw new Exception("You need to provide a valid text or html message to send this email.");
            } else {
                $message = new Message(new SM);

                $message->from($from, $fromName)
                ->to($to, $toName)
                ->subject($subject)
                ->priority($priority);

                if (!empty($files)) {
                    foreach ($files as $file) {
                        if (File::exists($file)) {
                            $message->attach($file);
                        }
                    }
                }

                if (!empty($embeds)) {
                    foreach ($embeds as $embed) {
                        if (File::exists($embed)) {
                            $message->embed($embed);
                        }
                    }
                }

                $addText = false;

                if (false !== $html) {
                    $message->setBody($html, 'text/html');

                    if (false !== $text) {
                        $message->addPart($text, 'text/plain');
                        $addText = true;
                    }
                }

                if (false !== $text && false === $addText) {
                    $message->setBody($text, 'text/plain');
                }

                return with(new Mandrill(Config::get('mailer.password')))->send($message->getSwiftMessage());
            }
        } else {
            throw new Exception("The field 'to' is needed to send this email.");
        }
    }

    function jdbView(DBJ $db, $nameView)
    {
        $database   = $db->db;
        $table      = $db->table;

        $file = APPLICATION_PATH . DS . 'models' . DS . 'Views' . DS . $database . DS . ucfirst(strtolower($table)) . DS . strtolower($nameView) . '.php';

        if (File::exists($file)) {
            $config = include($file);

            return $db->view($config);
        } else {
            throw new Exception($nameView . ' does not exist.');
        }
    }

    function me($ns = 'core')
    {
        return lib('me', [$ns]);
    }
