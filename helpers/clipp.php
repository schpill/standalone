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

    use Aws\Ses\SesClient;
    use Thin\Mail\Message;
    use Thin\Mail\Mandrill;
    use Swift_Message as SM;

    class ClippLib
    {
        private static $gravatar;
        private static $is_gravatar_loaded;

        public function identify()
        {
            $user = session('log')->getUser();

            if (!$user) {
                $session = session('log');
                $forever = forever();

                $user = Model::Account()->where(['forever', '=', $forever])->first();

                if (!$user) {
                    $user = Model::Visitor()->firstOrCreate(['forever' => $forever])->toArray();
                    $user['accounted']  = false;
                    $user['visitor']    = true;
                } else {
                    $user['accounted']  = true;
                    $user['visitor']    = false;
                }

                $user['ip'] = $this->ip();

                $session->setUser($user);
            }

            return $this;
        }

        public function getIdentifier()
        {
            $u = session('front')->getUser();

            $isLogged = !is_null($u);

            return $isLogged ? $u['forever'] : forever();
        }

        public function bag($k, $data = [])
        {
            $key = sha1($this->getIdentifier() . $k);
            $file = '/home/php/storage/bag/' . $key;

            $data = array_merge($data, ['id' => $key]);

            if (file_exists($file)) {
                $data = array_merge($data, unserialize(File::read($file)));
            }

            return dyn(lib('model', [$data]))->extend('save', function ($app) use ($file, $k) {
                $row = $app->getNative()->toArray();
                File::delete($file);
                File::put($file, serialize($row));

                return bag($k);
            })->extend('delete', function ($app) use ($file) {
                return File::delete($file);
            });
        }

        public function log($action, $data = [])
        {
            $user = session('log')->getUser();

            $u = session('front')->getUser();

            $isLogged = !is_null($u);

            if (!$user) {
                return $this->identify()->log($action, $data);
            } else {
                if ($isLogged) {
                    $user['id'] = $u['id'];
                }

                $user['browser']['agent'] = isAke($_SERVER, 'HTTP_USER_AGENT', null);
                $user['browser']['referer'] = isAke($_SERVER, 'HTTP_REFERER', null);

                $row = [
                    'date'          => new \MongoDate(),
                    'action'        => $action,
                    'id_user'       => $user['id'],
                    'cookie'        => forever(),
                    'browser'       => isAke($user, 'browser', []),
                    'location'      => isAke($user, 'location', []),
                    'ip'            => array_get($user, 'ip.ip'),
                    'isp'           => array_get($user, 'ip.isp'),
                    'city'          => array_get($user, 'ip.city'),
                    'country'       => array_get($user, 'ip.country'),
                    'country_code'  => array_get($user, 'ip.country_code'),
                    'region'        => array_get($user, 'ip.region_name'),
                    'zip'           => array_get($user, 'ip.zip'),
                    'timezone'      => array_get($user, 'ip.timezone'),
                    'connected'     => $isLogged,
                    'session'       => session_id(),
                    'anonymous'     => !$user['accounted']
                ];

                foreach ($data as $k => $v) {
                    $row[$k] = $v;
                }

                $this->em('logs')->insert($row);
            }
        }

        public function logs()
        {
            $rows = $this->em('logs')->find()->sort(['date' => -1]);

            $csv = [];

            foreach ($rows as $row) {
                unset($row['_id']);
                $date = $row['date']->sec;
                unset($row['date']);
                $browser = isAke(isAke($row, 'browser', []), 'agent', '');
                $screen = isAke(isAke($row, 'browser', []), 'screen', '');
                $referer = isAke(isAke($row, 'browser', []), 'referer', '/');
                $language = isAke(isAke($row, 'browser', []), 'language', 'en');
                $latitude = isAke(isAke($row, 'location', []), 'lat', 0);
                $longitude = isAke(isAke($row, 'location', []), 'lng', 0);

                $row['browser'] = $browser;
                $row['screen'] = $screen;
                $row['referer'] = $referer;
                $row['language'] = $language;
                $row['latitude'] = floatval($latitude);
                $row['longitude'] = floatval($longitude);

                unset($row['location']);
                $row['connected']   = $row['connected'] ? 1 : 0;
                $row['anonymous']   = $row['anonymous'] ? 1 : 0;
                $row['session']     = isAke($row, 'session', '');
                $row['id']          = isAke($row, 'id', '');
                $row['action']      = isAke($row, 'action', '');
                $row['cookie']      = isAke($row, 'cookie', '');
                $row['user']        = 1 == $row['anonymous'] ? isAke($row, 'id_user', '') : '';
                unset($row['id_user']);

                if (empty($csv)) {
                    $csv[] = implode(
                        '|',
                        [
                            'date',
                            'action',
                            'cookie',
                            'browser',
                            'ip',
                            'isp',
                            'city',
                            'zip',
                            'country',
                            'country_code',
                            'region',
                            'timezone',
                            'connected',
                            'anonymous',
                            'session',
                            'id',
                            'page',
                            'screen',
                            'referer',
                            'language',
                            'latitude',
                            'longitude',
                            'user'
                        ]
                    );
                }

                $csv[] = implode(
                    '|',
                    [
                        $date,
                        $row['action'],
                        $row['cookie'],
                        $row['browser'],
                        $row['ip'],
                        $row['isp'],
                        $row['city'],
                        $row['zip'],
                        $row['country'],
                        $row['country_code'],
                        $row['region'],
                        $row['timezone'],
                        $row['connected'],
                        $row['anonymous'],
                        $row['session'],
                        $row['id'],
                        $row['page'],
                        $row['screen'],
                        $row['referer'],
                        $row['language'],
                        $row['latitude'],
                        $row['longitude'],
                        $row['user']
                    ]
                );
            }

            die(implode("\n", $csv));
        }

        public function clippedCount($object, $external = false)
        {
            $id = $external ? (int) $object['id'] : (string) $object['_id'];

            return Model::Clipp()->where(['article_id', '=', $id])->count();
        }

        public function makeObject($data)
        {
            $row = ['table' => 'nodes'];
            $row['id_object'] = (string) $data['_id'];

            return Model::Native()->create($row)->save();
        }

        public function user()
        {
            $user = session('log')->getUser();

            if (!$user) {
                return $this->identify()->user();
            }

            return $user;
        }

        public function userModel()
        {
            $user = session('log')->getUser();

            if (!$user) {
                return $this->identify()->user();
            }

            if ($user['accounted']) {
                return Model::Account()->find($user['id']);
            } else {
                return Model::Visitor()->find($user['id']);
            }
        }

        public function ip()
        {
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } elseif (!empty($_SERVER['X_FORWARDED_FOR'])) {
                $ip = $_SERVER['X_FORWARDED_FOR'];
            } else {
                $ip = $_SERVER['REMOTE_ADDR'];
            }

            $url = "http://ip-api.com/json/$ip";

            $json = lib('geo')->dwnCache($url);
            $json = str_replace(
                array(
                    'query',
                    'countryCode',
                    'regionName'
                ),
                array(
                    'ip',
                    'country_code',
                    'region_name'
                ),
                $json
            );

            $data = json_decode($json, true);

            $data['ip'] = $ip;
            $data['language'] = $this->preferedLanguage();

            return $data;
        }

        public function preferedLanguage()
        {
            return \Locale::acceptFromHttp(isAke($_SERVER, "HTTP_ACCEPT_LANGUAGE", null));
        }

        public function email(array $conf)
        {
            $to = isAke($conf, 'to', false);

            if (false !== $to) {
                $toName     = isAke($conf, 'to_name', $to);
                $from       = isAke($conf, 'from', 'contact@clippCity.com');
                $fromName   = isAke($conf, 'from_name', 'clippCity');
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

        public function mail()
        {
            $client = SesClient::factory(array(
                'key'       => 'AKIAJ3WC7JCITHUWBVHQ',
                'secret'    => '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',
                'version'   => '2010-12-01',
                'region'    => 'eu-west-1'
            ));

            $status = $client->sendEmail(array(
                // Source is required
                'Source' => 'contacts@clippcity.com',
                // Destination is required
                'Destination' => array(
                    'ToAddresses' => array('gerald.plusquellec@clippcity.com')
                ),
                // Message is required
                'Message' => array(
                    // Subject is required
                    'Subject' => array(
                        // Data is required
                        'Data' => 'SES Testing 2',
                        'Charset' => 'UTF-8',
                    ),
                    // Body is required
                    'Body' => array(
                        'Text' => array(
                            // Data is required
                            'Data' => 'My plain text email',
                            'Charset' => 'UTF-8',
                        ),
                        'Html' => array(
                            // Data is required
                            'Data' => '<b>My HTML Email</b>',
                            'Charset' => 'UTF-8',
                        ),
                    ),
                ),
                'ReplyToAddresses' => array('technic@clippcity.com'),
                'ReturnPath' => 'technic@clippcity.com'
            ));

            return true;
        }

        public function native($id)
        {
            $id = is_object($id) ? $id->{'$id'} : $id;

            try {
                $object = Model::Nodes()
                ->getCollection('nodes')
                ->findOne(
                    array(
                        "_id" => new \MongoId($id)
                    )
                );

                return $object;
            } catch (\Exception $e) {
                return null;
            }
        }

        public function getImage($id, $return = false)
        {
            if (is_array($id)) {
                $id = (string) $id['_id'];
            }

            $c = [];
            $native = $this->native($id);

            if (isset($native['objects'])) {
                foreach ($native['objects'] as $obj) {
                    $obj = $this->native($obj);

                    if ($obj['type'] == 'picture') {
                        $c[] = $obj['_id'];

                        if ($return) {
                            return $obj;
                        }
                    }
                }

                return $c;
            }

            return null;
        }

        public function getContent($id)
        {
            $c = [];
            $native = $this->native($id);

            if (isset($native['objects'])) {
                foreach ($native['objects'] as $obj) {
                    $obj = $this->native($obj);

                    if ($obj['type'] == 'article') {
                        $c[] = $obj;
                    }
                }

                return $c;
            }

            return [];
        }

        public function getPlace($id, $return = false)
        {
            $c = [];
            $native = $this->native($id);

            if (isset($native['objects'])) {
                foreach ($native['objects'] as $obj) {
                    $obj = $this->native($obj);

                    if ($obj['type'] == 'place') {
                        $c[] = $obj['_id'];

                        if ($return) {
                            return $obj;
                        }
                    }
                }

                return $c;
            }

            return null;
        }

        public function getJsonLd($id)
        {
            $json = [];

            if (is_object($id)) {
                $id = (string) $id;
            }

            $links = Model::GraphLink()
            ->where(['article_id', '=', $id])
            ->where(['article_id', '=', new \MongoId($id)], 'OR')
            ->models();

            foreach ($links as $link) {
                $type = $link->type;

                if ($type == "people" || $type == "place") {
                    $data = $link->data();

                    if ($type == 'people') {
                        $bd = isAke($data, 'birthdate', '00/00/0000');
                        list($d, $m, $y) = explode('/', $bd, 3);
                        $md = "$y-$m-$d";
                        $j = '<script type="application/ld+json">
                        {
                            "@context": "http://schema.org",
                            "@type": "Person",
                            "@id": "https://fr.wikipedia.org/wiki/index.html?curid=' . $data['id_wp'] . '",
                            "name": ' . json_encode(isAke($data, 'completename', isAke($data, 'name', ''))) . ',
                            "jobTitle": ' . json_encode(isAke($data, 'profession', '')) . ',
                            "born": "' . $md . '"
                        }
                        </script>';

                        $json[] = $j;
                    } elseif ($type == 'place') {
                        $j = '<script type="application/ld+json">
                        {
                            "@context": {
                                "name": "http://schema.org/name",
                                "address": "http://schema.org/address",
                                "category": "http://schema.org/category",
                                "geo": "http://schema.org/geo",
                                "latitude": {
                                    "@id": "http://schema.org/latitude",
                                    "@type": "xsd:float"
                                },
                                "longitude": {
                                    "@id": "http://schema.org/longitude",
                                    "@type": "xsd:float"
                                },
                                "xsd": "http://www.w3.org/2001/XMLSchema#"
                            },
                            "@type": "Place",
                            "@id": "https://fr.wikipedia.org/wiki/index.html?curid=' . $data['id_wp'] . '",
                            "category": ' . json_encode(isAke($data, 'cat', '')) . ',
                            "name": ' . json_encode(isAke($data, 'name', '')) . ',
                            "geo": {
                                "latitude": "' . json_encode(isAke($data, 'latitude', 0)) . '",
                                "longitude": "' . json_encode(isAke($data, 'longitude', 0)) . '"
                            }
                        }
                        </script>';

                        $json[] = $j;
                    }
                }
            }

            return implode("\n", $json);
        }

        public function getGraphs($id)
        {
            if (is_object($id)) {
                $id = (string) $id;
            }

            $content = $this->native($id)['content'];

            $content = str_replace('<span data-graph-place="true">', '', $content);
            $content = str_replace('<span data-graph-object="true">', '', $content);
            $content = str_replace('<span data-graph-people="true">', '', $content);
            $content = str_replace('</span></span>', '</span>', $content);

            if (strstr($content, '</span></span>')) {
                $content = str_replace('</span></span>', '</span>', $content);

                $tab = explode('="true"><span', $content);

                foreach ($tab as $row) {
                    $subtab = explode('<span data-', $row);
                    array_pop($subtab);
                    $content = str_replace($row, implode('<span data-', $subtab), $content);
                }
            }

            $content = str_replace('<span="true">', '', $content);
            $content = str_replace('">="true">', '">', $content);


            $content = str_replace('"></span>', '">', $content);
            $content = str_replace(' ="true"><span', '<span', $content);
            $content = str_replace('="true"></span>', '="true">', $content);
            $content = str_replace('&nbsp;</span>', '</span>&nbsp;', $content);
            $content = str_replace(' </span>', '</span> ', $content);
            $content = str_replace('&nbsp;', ' ', $content);

            if (strstr($content, '<span="true"')) {
                $tab = explode('<span="true"', $content);

                array_shift($tab);

                foreach ($tab as $row) {
                    $color = Utils::cut('style="color: ', ';', $row);

                    if ($color == 'orange') {
                        $type = "place";
                    } elseif ($color == 'green') {
                        $type = "people";
                    } elseif ($color == 'navy') {
                        $type = "object";
                    }

                    $content = str_replace(
                        '<span="true" style="color: ' . $color,
                        '<span data-graph-' . $type . '="true" style="color: ' . $color,
                        $content
                    );
                }
            }

            $links = Model::GraphLink()
            ->where(['article_id', '=', $id])
            ->where(['article_id', '=', new \MongoId($id)], 'OR')
            ->models();

            foreach ($links as $link) {
                $tab    = $link->toArray();
                $type   = $tab['type'];
                $search = $tab['search'];

                switch ($type) {
                    case 'people':
                        $color = 'green';
                        break;
                    case 'place':
                        $color = 'orange';
                        break;
                    default:
                        $color = 'navy';
                        break;
                }

                $type = !in_array($type, ['people', 'place', 'object']) ? 'object' : $type;

                $chunks = explode("data-graph-$type", $content);
                array_shift($chunks);

                foreach ($chunks as $chunk) {
                    $segment    = Utils::cut('>', '</', $chunk);

                    if (!strlen($segment)) continue;
                    $data       = $link->data();
                    $pl         = Inflector::urlize(isAke($data, 'completename', isAke($data, 'name', '')));
                    $data_id    = $data['id'];

                    if (Inflector::urlize($segment) == Inflector::urlize($search)) {
                        $what = "<span data-graph-$type=\"true\" style=\"color: $color; font-weight: bold; text-decoration: underline;\">$segment</span>";
                        $by = "<a class=\"graph_$type\" href=\"/graph/{$link->id}/$pl\">$segment</a>";

                        $content = str_replace($what, $by, $content);

                        $what = "<span data-graph-$type=\"true\" style=\"color: $color; text-decoration: underline; font-weight: bold;\">$segment</span>";
                        $content = str_replace($what, $by, $content);

                        $what = "<span data-graph-$type=\"true\" style=\"text-decoration:underline; color:$color; font-weight: bold;\">$segment</span>";
                        $content = str_replace($what, $by, $content);


                        $what = "<em data-graph-$type=\"true\" style=\"color: $color; font-weight: bold; text-decoration: underline;\">$segment</em>";
                        // $by = "<a class=\"graph_$type\" href=\"/graph/{$link->id}/$pl\">$segment</a>";

                        $content = str_replace($what, $by, $content);

                        $what = "<em data-graph-$type=\"true\" style=\"color: $color; text-decoration: underline; font-weight: bold;\">$segment</em>";
                        $content = str_replace($what, $by, $content);

                        $what = "<em data-graph-$type=\"true\" style=\"text-decoration:underline; color:$color; font-weight: bold;\">$segment</em>";
                        $content = str_replace($what, $by, $content);
                    } else {
                    }
                }
            }

            // while (strstr($content, '<span data-graph-')) {
            //     $cleans = explode('<span data-graph-', $content);
            //     array_shift($cleans);

            //     foreach ($cleans as $tmp) {
            //         $seg = Utils::cut('>', '</span>', $tmp);
            //         list($f, $dummy) = explode('>', $tmp, 2);
            //         $what = '<span data-graph-' . $f . '>' . $seg . '</span>';
            //         $content = str_replace($what, $seg, $content);
            //     }
            // }

            // while (strstr($content, '<em data-graph-')) {
            //     $cleans = explode('<em data-graph-', $content);
            //     array_shift($cleans);

            //     foreach ($cleans as $tmp) {
            //         $seg = Utils::cut('>', '</em>', $tmp);
            //         list($f, $dummy) = explode('>', $tmp, 2);
            //         $what = '<em data-graph-' . $f . '>' . $seg . '</em>';
            //         $content = str_replace($what, $seg, $content);
            //     }
            // }

            $content = str_replace('style="color: navy; font-weight: bold; text-decoration: underline;"', '', $content);
            $content = str_replace('style="color: orange; font-weight: bold; text-decoration: underline;"', '', $content);
            $content = str_replace('style="color: green; font-weight: bold; text-decoration: underline;"', '', $content);

            return $content;
        }

        public function graphs($id)
        {
            if (is_object($id)) {
                $id = (string) $id;
            }

            return Model::GraphLink()
            ->where(['article_id', '=', $id])
            ->where(['article_id', '=', new \MongoId($id)], 'OR')
            ->models();
        }

        public function reco($id, $max = 10)
        {
            if (is_object($id)) {
                $id = (string) $id;
            }

            $location = $this->native($id)['location'];

            $lng = current($location['coordinates']);
            $lat = end($location['coordinates']);

            $db = $this->em('nodes');
            // $db->ensureIndex(['location.coordinates' => '2d']);

            $lang = $this->lng();

            return $db->find([
                '_id' => ['$ne' => $this->native($id)['_id']],
                'status' => 2,
                'type' => 'article',
                'lang' => $lang,
                'metas.applis.clippCity' => ['$exists' => true],
                'metas.picture' => ['$exists' => true, '$not' => ['$size' => 0]],
                'location.coordinates' => ['$near' => [floatval($lng), floatval($lat)]]
            ])->limit($max);
        }

        public function pois($id, $max = 50)
        {
            $db = $this->em('nodes');

            $id = (string) $id;

            $ll = $this->getLatLng($this->native($id));

            return xCache('pois.map.' . $id, function () use ($db, $ll) {
                $rows = $db->find([
                    'type' => 'poi',
                    'subtype' => ['$exists' => false],
                ]);

                $collection = [];

                foreach ($rows as $row) {
                    $distances = distanceKmMiles(
                        floatval($ll['lng']),
                        floatval($ll['lat']),
                        floatval($row['location']['coordinates'][0]),
                        floatval($row['location']['coordinates'][1])
                    );

                    $row['distance'] = $distances['km'] * 1000;

                    if ($row['distance'] <= 500 && !empty($row['medias'])) {
                        $collection[] = $row;
                    }
                }

                return array_values(coll($collection)->sortBy('distance')->toArray());
            });

            $pois = Model::PoiAssoc()->where(['id_node', '=', $id])->cursor();

            $collection = [];

            $ll = $this->getLatLng($this->native($id));

            foreach ($pois as $poi) {
                $poi = $this->native($poi['id_poi']);
                $location = $poi['location'];

                $lng = current($location['coordinates']);
                $lat = end($location['coordinates']);

                $distances = distanceKmMiles(
                    floatval($ll['lng']),
                    floatval($ll['lat']),
                    floatval($lng),
                    floatval($lat)
                );

                $poi['distance'] = $distances['km'] * 1000;

                if ($poi['distance'] <= 500 && !empty($poi['medias'])) {
                    // $imgs = lib('geo')->panoramic($poi['title']);

                    // $poi['imgs'] = $imgs;

                    $collection[] = $poi;
                }
            }

            return array_values(coll($collection)->sortBy('distance')->toArray());

            $location = $this->native($id)['location'];

            $lng = current($location['coordinates']);
            $lat = end($location['coordinates']);

            $db = $this->em('geo.place');
            $db->ensureIndex(['coordinates' => '2d']);

            return $db->find([
                'id_4s' => ['$exists' => true],
                'image' => ['$exists' => true],
                'coordinates' => ['$near' => [floatval($lng), floatval($lat)]]
            ])->limit($max);
        }

        public function em($collection)
        {
            return dbm($collection);
        }

        public function lng()
        {
            $lng = 'en_us';

            if (fnmatch('fr*', lng())) {
                $lng = 'fr_fr';
            } elseif (fnmatch('en*', lng())) {
                $lng = 'en_us';
            }

            if ($lng == 'fr_fr') {
                setlocale(LC_TIME, "fra");
            } else {
                setlocale(LC_TIME, "English");
            }

            return $lng;
        }

        public function lng2()
        {
            dd(lng());
        }

        public function gravatar($email, $s = 80, $d = 'mm', $r = 'g', $img = false, $atts = [])
        {
            $url = 'http://www.gravatar.com/avatar/';
            $url .= md5(strtolower(trim($email)));
            $url .= "?s=$s&d=$d&r=$r";

            if ($img) {
                $url = '<img src="' . $url . '"';

                foreach ($atts as $key => $val) {
                    $url .= ' ' . $key . '="' . $val . '"';
                }

                $url .= ' />';
            }

            return $url;
        }

        public function transports($article, $context = null)
        {
            $ll = $this->getLatLng($article);

            if ($context) {
                $context->ll = $ll;
            }

            return lib('geo')->getTransports(floatval($ll['lat']), floatval($ll['lng']));
        }

        public function display($html)
        {
            return str_replace([' !', ' :', ': '], ['&nbsp;!', '&nbsp;:', ':&nbsp;'], $html);
        }

        public function homeSlide($id)
        {
            $file = Config::get('app.module.dir') . '/assets/picture/clippcity/' . $id . '.png';
            $target = Config::get('app.module.dir') . '/assets/picture/home/' . $id . '.png';
            $src = '/assets/picture/home/' . $id . '.png';

            if (!is_file($target)) {
                $cmd = 'convert -resize 1200x480! "' . $file . '" "' . $target . '"';
                shell_exec($cmd);
            } else {
                if (filemtime($file) > filemtime($target)) {
                    $cmd = 'convert -resize 1200x480! "' . $file . '" "' . $target . '"';
                    shell_exec($cmd);
                }
            }

            return $src;
        }

        public function getLatLng($art)
        {
            $article = null;

            if (is_array($art)) {
                $article = $art;
            } elseif (is_object($art) || is_string($art)) {
                $article = $this->native($art);
            }

            if (!is_array($article) || is_null($article)) {
                return ['lat' => 0, 'lng' => 0];
            }

            if ((string) $article['_id'] == '565d6ece1a2dc2d6422445b2') {
                return ['lat' => 16.8649298, 'lng' => 11.9537596];
            }

            $lat = $lng = 0;

            $place = $this->getPlace($article['_id'], true);

            if ($place) {
                $coordinates    = isAke(isAke($place, 'location', []), 'coordinates', []);

                if (count($coordinates) == 2) {
                    $lat = $place['location']['coordinates'][1];
                    $lng = $place['location']['coordinates'][0];
                }
            }

            if ($lat == 0 || $lng == 0) {
                $lat = $article['location']['coordinates'][1];
                $lng = $article['location']['coordinates'][0];
            }

            if ($lat == 0 || $lng == 0) {
                if (isset($article['place'])) {
                    $lat = $article['place']['lat'];
                    $lng = $article['place']['lng'];
                }
            }

            if ($lat == 0 || $lng == 0) {
                if (isAke($article, 'latitude', 0) <> 0) {
                    $lat = isAke($article, 'latitude', 0);
                    $lng = isAke($article, 'longitude', 0);
                } else {
                    if (isAke(isAke($article, 'metas', []), 'latitude', 0) <> 0) {
                        $lat = isAke($article['metas'], 'latitude', 0);
                        $lng = isAke($article['metas'], 'longitude', 0);
                    } else {
                        $place          = $this->getPlace($article['_id'], true);
                        $coordinates    = isAke(isAke($place, 'location', []), 'coordinates', []);

                        if (count($coordinates) == 2) {
                            $lat = $place['location']['coordinates'][1];
                            $lng = $place['location']['coordinates'][0];
                        } else {
                            return ['lat' => 0, 'lng' => 0];
                        }
                    }
                }
            }

            return ['lat' => floatval($lat), 'lng' => floatval($lng)];
        }

        public function sc()
        {
            return lib('shortcode');
        }

        public function assetTimed($file, $echo = true)
        {
            $asset = lib('shortcode')->lastAsset($file);

            if ($echo) {
                echo $asset;
            } else {
                return $asset;
            }
        }

        public function getVar($k, $d = null)
        {
            return Now::get($k, $d);
        }

        public function setVar($k, $v = null)
        {
            return Now::set($k, $v);
        }

        public function conf($k, $d = null)
        {
            return Config::get($k, $d);
        }

        public function socialBg()
        {
            $user = session("front")->getUser();

            if ($user) {
                $login = isAke($user, 'login', null);

                if ($login) {
                    $first = $login[0];

                    if (is_numeric($first)) {
                        return (int) $first;
                    } else {
                        $first = strtolower($first);

                        for ($l = 'a', $i = 10; $l <= 'z', strlen($l) < 2; $l++, $i++) {
                            if ($first == $l) {
                                return $i;
                            }
                        }
                    }
                }
            }

            return null;
        }

        public function getPoisArticle($art)
        {
            $article = null;

            if (is_array($art)) {
                $article = $art;
            } elseif (is_object($art) || is_string($art)) {
                $article = $this->native($art);
            }

            if (is_array($article)) {
                $key    = 'getPoisArticles.' . (string) $article['_id'];

                // getCached($key, function () use ($article) {
                    $ll = lib('clipp')->getLatLng($article);
                    $ida = (string) $article['_id'];

                    $lat = isAke($ll, 'lat', 0);
                    $lng = isAke($ll, 'lng', 0);

                    if ($lat != 0 && $lng != 0) {
                        $pois   = lib('geo')->poisTouristic(floatval($lat), floatval($lng));

                        $key    = 'getPoisArticle.' . sha1((string) $article['_id'] . floatval($lat) . floatval($lng));

                        $db = lib('clipp')->em('nodes');

                        foreach ($pois as $poi) {
                            $ds = isAke($poi, 'datasheets', []);

                            if (!empty($ds)) {
                                $row = current($ds);

                                $count = $db->find([
                                    'permalink' => '/poi/' . Inflector::urlize($row['name'])
                                ])->count();

                                if ($count == 0) {
                                    $obj = [
                                        '__v'           => 0,
                                        'history'       => [],
                                        'key'           => isAke($poi, 'poi_id', null),
                                        'type'          => 'poi',
                                        'status'        => 2,
                                        'lang'          => 'fr_fr',
                                        'title'         => $row['name'],
                                        'description'   => $row['description'],
                                        'medias'        => $row['medias'],
                                        'permalink'     => '/poi/' . Inflector::urlize($row['name']),
                                        'search'        => Inflector::urlize($row['description'], ' '),
                                        'updated_at'    => new \MongoDate(),
                                        'created_at'    => new \MongoDate()
                                    ];

                                    $location                       = new \stdclass;
                                    $location->address              = new \stdclass;
                                    $location->address->area        = isAke($row, 'area', null);
                                    $location->address->country     = isAke($row, 'country', null);
                                    $location->address->city        = isAke($row, 'city', null);
                                    $location->address->ref         = isAke($row, 'ref_lieu', null);
                                    $location->address->street      = isAke($row, 'address', null);
                                    $location->address->zip         = isAke($row, 'postcode', null);
                                    $location->formatted_address    = isAke($row, 'formated_address_line', null);
                                    $location->formatted_city       = isAke($row, 'formated_city_line', null);
                                    $location->type                 = 'Point';
                                    $location->coordinates          = [floatval(isAke($row, 'longitude', 0)), floatval(isAke($row, 'latitude', 0))];

                                    $obj['location'] = $location;

                                    $db->insert($obj, ['fsync' => true]);

                                    $idp = (string) $obj['_id'];

                                    // Model::PoiAssoc()->create([
                                    //     'id_node' => $ida,
                                    //     'id_poi' => $idp
                                    // ])->save();
                                }
                                // else {
                                //     if ($count == 1) {
                                //         $row = $db->findOne([
                                //             'permalink' => '/poi/' . Inflector::urlize($row['name'])
                                //         ]);

                                //         $idp = (string) $row['_id'];

                                //         Model::PoiAssoc()->create([
                                //             'id_node' => $ida,
                                //             'id_poi' => $idp
                                //         ])->save();
                                //     }
                                // }
                            }
                        }
                    }

                    return true;
                // });
            }
        }

        public function clippExterne($id, $url)
        {
            $row = Model::Url()->where(['url', '=', $url])->first();

            if ($row) {
                return $row['key'];
            }

            $key = 'CC' . strtoupper(Inflector::random(9));

            Model::Url()->create([
                'url' => $url,
                'key' => $key
            ])->save();

            return $key;
        }

        public function makeMetas($art, $subtype = false)
        {
            $html = '';
            $article = null;

            $subtypeColors = [
                'insolite'                  => '#ff1b46',
                'people'                    => '#e766a0',
                'cinema'                    => '#f0941d',
                'history'                   => '#ffd800',
                'hotels-bed-and-breakfast'  => '#49a034',
                'bars-restaurants'          => '#009fde',
                'faits-divers'              => '#c5cae9',
                'art-culture'               => '#9721a9',
            ];

            if (is_array($art)) {
                $article = $art;
            } elseif (is_object($art) || is_string($art)) {
                $article = $this->native($art);
            }

            if ($article['type'] == 'external') {
                $isExternal = true;
                $pic = $article['image'];
                $article['subtype'] = str_replace('www.', '', parse_url($article['url'], PHP_URL_HOST));
                $article['_id'] = $article['id'];
            } else {
                $isExternal = false;
                $pic = URLSITE . 'assets/picture/clippcity/' . $article['_id'] . '.png';
            }

            if ($article['type'] == 'poi') {
                $article['permalink'] = str_replace('/poi/', '/gpoi/', $article['permalink']);
                $pic = $article['medias']['img_in'];
            } elseif ($article['type'] == 'external') {
                $article['permalink'] = '/u/' . $this->clippExterne($article['id'], $article['url']);
            }

            if (is_array($article)) {
                $html .= '<div style="margin-bottom: 10px; margin-top: 10px;">';

                if ($subtype) {
                    if ($article['type'] == 'external') {
                        $html .= '';
                    } else {
                        if (fnmatch('hotel*', $article['subtype'])) {
                            $d = 'hotels';
                        } else {
                            $d = $article['subtype'];
                        }

                        $color = isAke($subtypeColors, $article['subtype'], '#000');

                        $html .= '<a href="/category/' . $article['subtype'] . '">
                                <span style="color:' . $color . '">#' . $d . '</span>
                            </a>&nbsp;&nbsp;&nbsp;';
                    }
                }

                $html .= '<span onclick="clipp.myClipp.clipp(\'' . $article['_id'] . '\');" class="blueClipp">
                    <i class="fa fa-paperclip fa-rotate-180"></i>
                    ' . $this->clippedCount($article, $isExternal) . '
                </span>&nbsp;&nbsp;';
                $html .= '<a data-track-click="article.share.facebook.'.$article['_id'].'" target="_share" href="https://www.facebook.com/dialog/feed?app_id='.$this->conf('facebook.id').'&redirect_uri='.urlencode(substr(URLSITE, 0, -1) . $article['permalink']).'&picture='.urlencode($pic).'&name='. urlencode($article['title']).'&description='. urlencode($article['teaser']).'&display=popup&show_error=yes">
                        <i data-track-click="article.share.facebook.'. $article['_id'].'" class="fa fa-facebook"></i>
                    </a> | ';
                $html .= '<a data-track-click="article.share.twitter.'. $article['_id'].'" target="_share" href="https://twitter.com/intent/tweet?text='. urlencode($article['title']).'&url='. urlencode(substr(URLSITE, 0, -1) . $article['permalink']).'&via=clippCity">
                        <i data-track-click="article.share.twitter.'. $article['_id'].'" class="fa fa-twitter"></i>
                    </a> | ';
                $html .= '<a data-track-click="article.share.gplus.'. $article['_id'].'" target="_share" href="http://plus.google.com/share?url='. substr(URLSITE, 0, -1) . $article['permalink'].'">
                        <i data-track-click="article.share.gplus.'. $article['_id'].'" class="fa fa-google-plus"></i>
                    </a> | ';
                $html .= '<a data-track-click="article.share.pinterest.'. $article['_id'].'" target="_share" href="http://pinterest.com/pin/create/button/?url='. substr(URLSITE, 0, -1) . $article['permalink'].'&media='. urlencode($pic) .'">
                            <i data-track-click="article.share.pinterest.'. $article['_id'].'" class="fa fa-pinterest"></i>
                        </a> | ';
                $html .= '<a data-track-click="article.share.tumblr.'. $article['_id'].'" target="_share" href="https://www.tumblr.com/share/photo?clickthru='. urlencode(substr(URLSITE, 0, -1) . $article['permalink']).'&caption='. urlencode($article['teaser']).'&source='. urlencode($pic).'">
                        <i data-track-click="article.share.tumblr.'. $article['_id'].'" class="fa fa-tumblr"></i>
                    </a>';

                return $html . '</div>';
            }

            return $html;
        }

        public function metro($transports)
        {
            foreach ($transports as $t) {
                if ($t['subtype'] == 'subway' || $t['type'] == 'subway') {
                    return $t;
                }
            }

            return [];
        }

        public function plural($count, $singular, $plural, $none = null)
        {
            if (is_null($none)) {
                $none = $singular;
            }

            if ($count == 0) {
                __($none, 'general');
            } elseif ($count == 1) {
                __($singular, 'general');
            } else {
                __($plural, 'general');
            }
        }

        public function getHexa($article)
        {
            $location = isAke($article, 'location', []);

            $data = lib('geo')->getCoordsMap($location['formatted_address']);

            $id_hex = isAke($data, 'id_hex', null);

            if (!fnmatch('0x*', $id_hex)) {
                dd($data, $location['formatted_address']);
            }

            return $id_hex;
        }

        public function nativePoi($cid)
        {
            return $this->em('nodes')->findOne([
                'type' => 'poi',
                'key' => $cid
            ]);
        }

        public function searchGPois($type, $ll)
        {
            $rows = $this->em('nodes')->find([
                'type' => 'poi',
                'provider' => 'g',
                'subtype' => $type
            ]);

            $collection = [];

            $start = true;

            foreach ($rows as $row) {
                $distances = distanceKmMiles(
                    floatval($ll['lng']),
                    floatval($ll['lat']),
                    floatval($row['location']['coordinates'][0]),
                    floatval($row['location']['coordinates'][1])
                );

                $row['distance'] = $distances['km'] * 1000;

                if ($row['distance'] <= 1000 || (empty($collection) && !$start)) {
                    $collection[] = $row;
                    $start = true;
                }
            }

            return array_values(coll($collection)->sortBy('distance')->toArray());
        }

        public function cache($k, callable $c, $maxAge = null, $args = [])
        {
            return xCache($k, $c, $maxAge, $args);
        }

        public function distantAsset($src)
        {
            $ext    = strtolower(Arrays::last(explode('.', $src)));
            $file   = Config::get('app.module.assets') . DS . 'cache' . DS . sha1($src) . '.' . $ext;
            $render = '/assets/cache/' . sha1($src) . '.' . $ext;

            if (!file_exists($file)) {
                $ctn = file_get_contents($src);
                File::put($file, $ctn);
            }

            return $render;
        }

        public function thumb($article, $width = 0, $height = 0, $quality = 60)
        {
            if (is_array($article)) {
                $id = (string) $article['id'];
            } else {
                $id = (string) $article;
            }

            $img = '/home/php/dev/modules/clippcity/assets/picture/clippcity/' . $id . '.png';

            if (!file_exists($img)) return '';

            $key = sha1(filemtime($img) . $img . $width . $quality);

            $o = substr($key, 0, 2);
            $t = substr($key, 2, 2);
            $d = substr($key, 4, 2);

            $keyDir = Config::get('app.module.assets') . DS . 'cache' . DS . $o;

            if (!is_dir($keyDir)) {
                File::mkdir($keyDir);
            }

            $keyDir .= DS . $t;

            if (!is_dir($keyDir)) {
                File::mkdir($keyDir);
            }

            $keyDir .= DS . $d;

            if (!is_dir($keyDir)) {
                File::mkdir($keyDir);
            }

            $to = $keyDir . DS . $key . '-' . $width . '-' . $quality . '.jpg';

            list($dummy, $dir) = explode('/assets/', $to, 2);

            $render = '/assets/' . $dir;

            if (!file_exists($to)) {
                $dimensions = getimagesize($img);
                $ratio      = $dimensions[0] / $dimensions[1];

                if ($width == 0 && $height == 0) {
                    $width  = $dimensions[0];
                    $height = $dimensions[1];
                } elseif ($height == 0) {
                    $height = round($width / $ratio);
                } elseif ($width == 0) {
                    $width = round($height * $ratio);
                }

                if ($dimensions[0] > ($width / $height) * $dimensions[1]) {
                    $dimY   = $height;
                    $dimX   = round($height * $dimensions[0] / $dimensions[1]);
                    $decalX = ($dimX - $width) / 2;
                    $decalY = 0;
                }

                if ($dimensions[0] < ($width / $height) * $dimensions[1]) {
                    $dimX   = $width;
                    $dimY   = round($width * $dimensions[1] / $dimensions[0]);
                    $decalY = ($dimY - $height) / 2;
                    $decalX = 0;
                }

                if ($dimensions[0] == ($width / $height) * $dimensions[1]) {
                    $dimX   = $width;
                    $dimY   = $height;
                    $decalX = 0;
                    $decalY = 0;
                }

                $cmd = '/usr/bin/convert -quality ' . $quality . ' -resize ' . $dimX . 'x' . $dimY . ' "' . $img . '" "' . $to . '"';
                shell_exec($cmd);
            }

            return $render;
        }

        public function getGravatar($email, $size)
        {
            if (!self::$is_gravatar_loaded) {
                self::$is_gravatar_loaded = true;
                self::$gravatar = lib('gravatar');
                self::$gravatar->setDefaultImage(URLSITE . 'assets/img/nobody.png');
            }

            self::$gravatar->setAvatarSize($size);
            self::$gravatar->enableSecureImages();

            return self::$gravatar->buildGravatarURL($email);
        }
    }
