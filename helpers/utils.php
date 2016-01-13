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

    use Illuminate\View\Compilers\BladeCompiler;
    use Illuminate\Filesystem\Filesystem;
    use Illuminate\Encryption\Encrypter;

    class UtilsLib
    {
        public function getUniverses()
        {
            return rdb(SITE_NAME, 'segmenttype')->exec();
        }

        public function async($cmd)
        {
            return $this->backgroundTask($cmd);
        }

        public function backgroundTask($cmd)
        {
            if (substr(php_uname(), 0, 7) == "Windows"){
                pclose(popen("start /B " . $cmd, "r"));
            } else {
                exec($cmd . " > /dev/null &");
            }
        }

        public function ean($ean)
        {
            $cache = lib('redys', ['ean'])->get("code.ean.$ean");

            if (!strlen($cache)) {
                $html = dwn("http://saela.eu/ean/" . $ean);

                $what = Utils::cut($ean . '</strong><br />', '<br /><br />', $html);

                if (!strlen($what)) {
                    lib('redys', ['ean'])->set("code.ean.$ean", serialize([]));
                }

                $what           = explode('<br />', $what);

                $w              = [];

                $w['product']   = $what[0];
                $w['brand']     = $what[1];
                $w['country']   = $what[2];

                lib('redys', ['ean'])->set("code.ean.$ean", serialize($w));

                $cache = $w;
            } else {
                $cache = unserialize($cache);
            }

            return $cache;
        }

        public function pdf($html, $name = null, $orientation = null, $disposition = null)
        {
            return pdf($html, $name, $orientation, $disposition);
        }

        public function age()
        {
            return Model::CoreAge();
        }

        public function cityzone($city_id, $reseller, $zonereseller_id, $distance)
        {
            $city = Model::City()->find($city_id);

            if ($city) {
                $tab = $city->assoc();
                $tab['distance'] = ['km' => 0, 'miles' => 0];
                $near = [$tab];
                $near = array_merge($near, getNearCitiesFromZip($city->zip, $distance));

                foreach ($near as $c) {
                    $cz = Model::Cityzone()->create([
                        'zonereseller_id'   => (int) $zonereseller_id,
                        'reseller_id'       => (int) $reseller,
                        'zip'               => (string) $c['zip'],
                        'city_id'           => (int) $c['id'],
                        'distance'          => (float) $c['distance']['km']
                    ])->save();
                }
            }
        }

        public function uuid()
        {
            return Utils::UUID();
        }

        public function callAWebPage($url, $delay = 1000, $force = true, $ua = null)
        {
            if (fnmatch('https://*', $url)) {
                list($dummy, $url) = explode('https://', $url, 2);
            } elseif (fnmatch('http://*', $url)) {
                list($dummy, $url) = explode('http://', $url, 2);
            }

            $options = [];

            $options['url']         = urlencode($url);
            $options['full_page']   = 'true';
            $options['force']       = $force ? 'true' : 'false';
            $options['delay']       = $delay;

            if ($ua) {
                $options['user_agent']  = $ua;
            }

            foreach ($options as $key => $value) {
                $parts[] = "$key=$value";
            }

            $queryString = implode("&", $parts);

            $key    = 'ca482d7e-9417-4569-90fe-80f7c5e1c781';
            $secret = 'd18ff559-8fc2-447f-8e8d-1b1157f9b1c2';
            $token  = hash_hmac("sha1", $queryString, $secret);
            $src    = "https://api.urlbox.io/v1/$key/$token/png?$queryString";

            header('Pragma: public');
            header('Cache-Control: max-age=86400');
            header('Expires: '. gmdate('D, d M Y H:i:s \G\M\T', time() + 86400));
            header('Content-Type: image/png');

            die(dwn($src));
        }

        public function urlToPng($url)
        {
            if (fnmatch('https://*', $url)) {
                list($dummy, $url) = explode('https://', $url, 2);
            } elseif (fnmatch('http://*', $url)) {
                list($dummy, $url) = explode('http://', $url, 2);
            }

            $options = [];

            $options['url']         = urlencode($url);
            $options['full_page']   = 'true';
            // $options['force']       = 'true';
            // $options['delay']       = 25000;
            // $options['user_agent']  = '';

            foreach ($options as $key => $value) {
                $parts[] = "$key=$value";
            }

            $queryString = implode("&", $parts);

            $key    = 'ca482d7e-9417-4569-90fe-80f7c5e1c781';
            $secret = 'd18ff559-8fc2-447f-8e8d-1b1157f9b1c2';
            $token  = hash_hmac("sha1", $queryString, $secret);
            $src    = "https://api.urlbox.io/v1/$key/$token/png?$queryString";

            header('Pragma: public');
            header('Cache-Control: max-age=86400');
            header('Expires: '. gmdate('D, d M Y H:i:s \G\M\T', time() + 86400));
            header('Content-Type: image/png');

            die(dwn($src));
        }

        function htmlToPng($html, $name = null, $orientation = null, $count = 0)
        {
            $name           = is_null($name)        ? 'doc.pdf'     : Inflector::urlize($name) . '.pdf';
            $orientation    = is_null($orientation) ? 'portrait'    : $orientation;

            $file           = TMP_PUBLIC_PATH . DS . sha1(serialize(func_get_args())) . '.html';

            files()->put($file, $html);

            $pdf = str_replace('.html', '.pdf', $file);

            // $keep = lib('keep')->instance();

            $url = URLSITE . 'tmp/' . Arrays::last(explode('/', $file));

            $this->urlToPng($url);
        }

        public function getSegment($idSegment)
        {
            $segment = Model::Segment()->find((int) $segmentId);

            if ($segment) {
                $datas = repo('segment')->getData($segmentId);
            }
        }

        public function since($name, $closure, $max, $args = [])
        {
            $row    = Model::Since()->firstOrCreate(['name' => $name]);
            $when   = $row->when;
            $value  = $row->value;

            if ($when && $value) {
                if ($when > $max) {
                    $data = call_user_func_array($closure, $args);

                    $row->setWhen(time())->setValue(serialize($data))->save();
                } else {
                    return unserialize($value);
                }
            }

            $data = call_user_func_array($closure, $args);
            $row->setWhen(time())->setValue(serialize($data))->save();

            return $data;
        }

        public function until($name, $closure, $max, $args = [])
        {
            $row    = Light::Until()->firstOrCreate(['name' => $name]);
            $when   = $row->when;
            $value  = $row->value;

            if ($when && $value) {
                if ($when < $max) {
                    $data = call_user_func_array($closure, $args);

                    $row->setWhen(time())->setValue(serialize($data))->save();
                } else {
                    return unserialize($value);
                }
            }

            $data = call_user_func_array($closure, $args);

            $row->setWhen(time())->setValue(serialize($data))->save();

            return $data;
        }

        public function remember($name, $closure, $max, $args = [])
        {
            $when   = lib('redys', ['remember'])->get('remember.' . sha1($name) . '.when');
            $value  = lib('redys', ['remember'])->get('remember.' . sha1($name) . '.value');

            if ($when && $value) {
                $when = (int) $when;

                if ($when < $max) {
                    $data = call_user_func_array($closure, $args);

                    lib('redys', ['remember'])->set('remember.' . sha1($name) . '.when', time());
                    lib('redys', ['remember'])->set('remember.' . sha1($name) . '.value', serialize($data));
                } else {
                    return unserialize($value);
                }
            }

            $data = call_user_func_array($closure, $args);

            lib('redys', ['remember'])->set('remember.' . sha1($name) . '.when', time());
            lib('redys', ['remember'])->set('remember.' . sha1($name) . '.value', serialize($data));

            return $data;
        }

        public function rememberAge($name, $closure, $max, $args = [])
        {
            $when   = lib('redys', ['remember'])->get('rememberAge.' . sha1($name) . '.when');
            $value  = lib('redys', ['remember'])->get('rememberAge.' . sha1($name) . '.value');

            if ($when && $value) {
                $age = time() - (int) $when;

                if ($age > $max) {
                    $data = call_user_func_array($closure, $args);

                    lib('redys', ['remember'])->set('rememberAge.' . sha1($name) . '.when', time());
                    lib('redys', ['remember'])->set('rememberAge.' . sha1($name) . '.value', serialize($data));
                } else {
                    return unserialize($value);
                }
            }

            $data = call_user_func_array($closure, $args);

            lib('redys', ['remember'])->set('rememberAge.' . sha1($name) . '.when', time());
            lib('redys', ['remember'])->set('rememberAge.' . sha1($name) . '.value', serialize($data));

            return $data;
        }

        public function untilAge($name, $closure, $max, $args = [])
        {
            $row    = Light::Until()->firstOrCreate(['name' => $name]);
            $when   = $row->when;
            $value  = $row->value;

            if ($when && $value) {
                $age = time() - (int) $when;

                if ($age > $max) {
                    $data = call_user_func_array($closure, $args);

                    $row->setWhen(time())->setValue(serialize($data))->save();
                } else {
                    return unserialize($value);
                }
            }

            $data = call_user_func_array($closure, $args);

            $row->setWhen(time())->setValue(serialize($data))->save();

            return $data;
        }

        public function sinceAge($name, $closure, $max, $args = [])
        {
            $row    = Model::Since()->firstOrCreate(['name' => $name]);
            $when   = $row->when;
            $value  = $row->value;

            if ($when && $value) {
                $age = time() - (int) $when;

                if ($age > $max) {
                    $data = call_user_func_array($closure, $args);

                    $row->setWhen(time())->setValue(serialize($data))->save();
                } else {
                    return unserialize($value);
                }
            }

            $data = call_user_func_array($closure, $args);

            $row->setWhen(time())->setValue(serialize($data))->save();

            return $data;
        }

        public function disqus($account = null, $echo = false)
        {
            $account = is_null($account) ? Config::get('disqus.account') : $account;
            $code = '<div id="disqus_thread"></div>
<script type="text/javascript">
    var disqus_shortname = "' . $account . '";
    (function() {
        var dsq = document.createElement("script");
        dsq.type = "text/javascript";
        dsq.async = true;
        dsq.src = "//" + disqus_shortname + ".disqus.com/embed.js";
        (document.getElementsByTagName("head")[0] || document.getElementsByTagName("body")[0]).appendChild(dsq);
    })();
</script>';
            if ($echo) {
                echo $code;
            } else {
                return $code;
            }
        }

        public function translate($sentence, $source = 'fr', $target = 'en')
        {
            if ($source == $target) {
                return $sentence;
            }

            $key = 'trad.' . sha1(serialize(func_get_args()));

            $cached = lib('cache')->get($key);

            if (!$cached) {
                $cached = $sentence;

                $source = Inflector::lower($source);
                $target = Inflector::lower($target);

                $url = "http://api.mymemory.translated.net/get?q=" . urlencode($sentence) . "&langpair=" . urlencode($source) . "|" . urlencode($target);

                $res = dwn($url);
                $tab = json_decode($res, true);

                $data = isAke($tab, 'responseData', false);

                if (false !== $data) {
                    $translatedText = isAke($data, 'translatedText', false);

                    if (false !== $translatedText) {
                        $cached = $translatedText;
                    }
                }

                lib('cache')->set($key, $cached);
            }

            return $cached;
        }

        public function gTranslate($sentence, $to = 'en', $from = 'fr')
        {
            $word = urlencode($word);

            $url  = 'https://translate.google.com/translate_a/single?client=t&sl=' . $from . '&tl=' . $to . '&hl=' . $to . '-419&dt=bd&dt=ex&dt=ld&dt=md&dt=qca&dt=rw&dt=rm&dt=ss&dt=t&dt=at&ie=UTF-8&oe=UTF-8&otf=1&ssel=0&tsel=0&tk=519235|682612&q=' . $word;

            $tr = $this->curl($url);

            $tr = explode('"', $tr);

            return $tr[1];
        }

        private function curl($url, $params = [], $hasCookie = false)
        {
            if (!$hasCookie) {
                $ckfile = tempnam ("/tmp", "CURLCOOKIE");
                $ch = curl_init($url);
                curl_setopt ($ch, CURLOPT_COOKIEJAR, $ckfile);
                curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
                $output = curl_exec($ch);
                curl_close($ch);
            }

            $str = '';
            $strArr = [];

            foreach ($params as $key => $value) {
                $strArr[] = urlencode($key) . "=" . urlencode($value);
            }

            if (!empty($strArr)) {
                $str = '?' . implode('&', $strArr);
            }

            $finalUrl = $url . $str;
            $ch = curl_init($finalUrl);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $ckfile);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $output = curl_exec($ch);

            curl_close($ch);

            return $output;
        }

        public function blader($str, $data = [])
        {
            $blade          = new BladeCompiler((new Filesystem), 'thin');
            $parsedString   = $blade->compileString($str);

            ob_start() && extract($data, EXTR_SKIP);

            try {
                eval('?>' . $parsedString);
            } catch (\Exception $e) {
                ob_end_clean();
                throw $e;
            }

            $str = ob_get_contents();

            ob_end_clean();

            return $str;
        }

        public function mic($zip = 21000, $city = 'dijon')
        {
            $db = rdb('michelin', 'resto');
            $page = 1;
            $url = "http://restaurant.michelin.fr/ajaxSearchRestaurant/france/$zip-$city/page-##page##";

            $urlTo = str_replace('##page##', $page, $url);

            $etabs = redis()->get("resto.mic.$zip.$city");

            if (!$etabs) {
                $restos = [];
                $json = dwn($urlTo);

                $etabs = json_decode($json, true);

                $asyncPoiList = isAke($etabs, 'asyncPoiList', []);
                $stats = isAke($asyncPoiList, 'stats', []);
                $nbresults = (int) (isAke($stats, 'tag_vm_nbresults_total', 0));
                $Oj = isAke($asyncPoiList, 'Oj', []);

                $restos = array_merge($restos, $Oj);

                $nbPages = ceil($nbresults / 18);

                for ($i = 2; $i <= $nbPages; $i++) {
                    $urlTo = str_replace('##page##', $i, $url);
                    $json = dwn($urlTo);

                    $etabs = json_decode($json, true);

                    $asyncPoiList = isAke($etabs, 'asyncPoiList', []);
                    $Oj = isAke($asyncPoiList, 'Oj', []);

                    $restos = array_merge($restos, $Oj);
                }

                redis()->set("resto.mic.$zip.$city", serialize($restos));
            } else {
                $etabs = unserialize($etabs);

                foreach ($etabs as $etab) {
                    $id = isAke($etab, 'id', false);

                    if (false !== $id) {
                        $infoUrl = "http://vmrest.viamichelin.com/apir/2/FindPOIByCriteria.json/RESTAURANT/fra?&filter=poi_id%20in%20%5B$id%5D&obfuscation=true&ie=UTF-8&charset=UTF-8&callback=JSE.cr.pv[0].cv&authKey=JSBS20111110142911566673070414&lg=fra&nocache=1433580926785";

                        $data = redis()->get("restos.mic.$id");

                        if (!$data) {
                            $html = dwn($infoUrl);

                            $json = Utils::cut('cv(', '}]}]})', $html) . '}]}]}';

                            $data = json_decode($json, true);

                            $resto = isAke($data, 'Oj', []);

                            if (!empty($resto)) {
                                $resto = current($resto);
                                $datasheets = isAke($resto, 'datasheets', []);

                                if (!empty($datasheets)) {
                                    $numFound = false;

                                    foreach ($datasheets as $ds) {
                                        $ds['poi_id'] = $id;

                                        $dts_id = isAke($ds, 'dts_id', 'a');

                                        if (is_numeric($dts_id)) {
                                            $numFound = true;
                                        }

                                        $resto = $ds;
                                    }

                                    redis()->set("restos.mic.$id", serialize($resto));
                                }
                            }
                        } else {
                            $data = unserialize($data);

                            $data['phone'] = (string) $data['phone'];
                            $data['local_phone'] = (string) $data['local_phone'];

                            $row = $db->firstOrCreate(['poi_id' => $data['poi_id']]);

                            foreach ($data as $k => $v) {
                                if (fnmatch('*phone*', $k)) {
                                    $v = str_replace('+', '', $v);
                                }

                                $row->$k = $v;
                            }

                            $row->save();
                        }
                    }
                }

                dd($row);
            }
        }

        public function recursive($arg, $func, $stack = null)
        {
            if ($stack) {
                foreach ($stack as $node) {
                    if ($arg === $node) {
                        return $arg;
                    }
                }
            } else {
                $stack = [];
            }

            switch (gettype($arg)) {
                case 'object':
                    if (method_exists('ReflectionClass','iscloneable')) {
                        $ref = new \ReflectionClass($arg);

                        if ($ref->iscloneable()) {
                            $arg = clone($arg);

                            $cast = is_a($arg, 'IteratorAggregate')
                            ? iterator_to_array($arg)
                            : get_object_vars($arg);

                            foreach ($cast as $key => $val) {
                                $arg->$key = $this->recursive(
                                    $val,
                                    $func,
                                    array_merge(
                                        $stack,
                                        [$arg]
                                    )
                                );
                            }
                        }
                    }

                    return $arg;
                case 'array':
                    $copy = [];

                    foreach ($arg as $key => $val) {
                        $copy[$key] = $this->recursive(
                            $val,
                            $func,
                            array_merge(
                                $stack,
                                [$arg]
                            )
                        );
                    }

                    return $copy;
                default:
                    throw new Exception("This method needs first argument to be an array or an object.");
            }

            return call_user_func_array(
                $func,
                $arg
            );
        }

        public function debug($data)
        {
            $debug = [];

            foreach ($data as $key => $value) {
                if (is_scalar($value)) {
                    $debug[$key] = $value;
                } elseif (is_array($value)) {
                    $debug[$key] = $this->debug($value);
                }
            }

            return $debug;
        }

        public function sanitize($string)
        {
            $minLength = 2;
            $maxLength = 15;

            $classExclude = '\x{0}-\x{2c}\x{2e}-\x{2f}\x{3a}-\x{40}\x{5b}-\x{60}\x{7b}-\x{bf}\x{d7}\x{f7}\x{2b0}-'.
            '\x{385}\x{387}\x{3f6}\x{482}-\x{489}\x{559}-\x{55f}\x{589}-\x{5c7}\x{5f3}-'.
            '\x{61f}\x{640}\x{64b}-\x{65e}\x{66a}-\x{66d}\x{670}\x{6d4}\x{6d6}-\x{6ed}'.
            '\x{6fd}\x{6fe}\x{700}-\x{70f}\x{711}\x{730}-\x{74a}\x{7a6}-\x{7b0}\x{901}-'.
            '\x{903}\x{93c}\x{93e}-\x{94d}\x{951}-\x{954}\x{962}-\x{965}\x{970}\x{981}-'.
            '\x{983}\x{9bc}\x{9be}-\x{9cd}\x{9d7}\x{9e2}\x{9e3}\x{9f2}-\x{a03}\x{a3c}-'.
            '\x{a4d}\x{a70}\x{a71}\x{a81}-\x{a83}\x{abc}\x{abe}-\x{acd}\x{ae2}\x{ae3}'.
            '\x{af1}-\x{b03}\x{b3c}\x{b3e}-\x{b57}\x{b70}\x{b82}\x{bbe}-\x{bd7}\x{bf0}-'.
            '\x{c03}\x{c3e}-\x{c56}\x{c82}\x{c83}\x{cbc}\x{cbe}-\x{cd6}\x{d02}\x{d03}'.
            '\x{d3e}-\x{d57}\x{d82}\x{d83}\x{dca}-\x{df4}\x{e31}\x{e34}-\x{e3f}\x{e46}-'.
            '\x{e4f}\x{e5a}\x{e5b}\x{eb1}\x{eb4}-\x{ebc}\x{ec6}-\x{ecd}\x{f01}-\x{f1f}'.
            '\x{f2a}-\x{f3f}\x{f71}-\x{f87}\x{f90}-\x{fd1}\x{102c}-\x{1039}\x{104a}-'.
            '\x{104f}\x{1056}-\x{1059}\x{10fb}\x{10fc}\x{135f}-\x{137c}\x{1390}-\x{1399}'.
            '\x{166d}\x{166e}\x{1680}\x{169b}\x{169c}\x{16eb}-\x{16f0}\x{1712}-\x{1714}'.
            '\x{1732}-\x{1736}\x{1752}\x{1753}\x{1772}\x{1773}\x{17b4}-\x{17db}\x{17dd}'.
            '\x{17f0}-\x{180e}\x{1843}\x{18a9}\x{1920}-\x{1945}\x{19b0}-\x{19c0}\x{19c8}'.
            '\x{19c9}\x{19de}-\x{19ff}\x{1a17}-\x{1a1f}\x{1d2c}-\x{1d61}\x{1d78}\x{1d9b}-'.
            '\x{1dc3}\x{1fbd}\x{1fbf}-\x{1fc1}\x{1fcd}-\x{1fcf}\x{1fdd}-\x{1fdf}\x{1fed}-'.
            '\x{1fef}\x{1ffd}-\x{2070}\x{2074}-\x{207e}\x{2080}-\x{2101}\x{2103}-\x{2106}'.
            '\x{2108}\x{2109}\x{2114}\x{2116}-\x{2118}\x{211e}-\x{2123}\x{2125}\x{2127}'.
            '\x{2129}\x{212e}\x{2132}\x{213a}\x{213b}\x{2140}-\x{2144}\x{214a}-\x{2b13}'.
            '\x{2ce5}-\x{2cff}\x{2d6f}\x{2e00}-\x{3005}\x{3007}-\x{303b}\x{303d}-\x{303f}'.
            '\x{3099}-\x{309e}\x{30a0}\x{30fb}\x{30fd}\x{30fe}\x{3190}-\x{319f}\x{31c0}-'.
            '\x{31cf}\x{3200}-\x{33ff}\x{4dc0}-\x{4dff}\x{a015}\x{a490}-\x{a716}\x{a802}'.
            '\x{e000}-\x{f8ff}\x{fb29}\x{fd3e}-\x{fd3f}\x{fdfc}-\x{fdfd}'.
            '\x{fd3f}\x{fdfc}-\x{fe6b}\x{feff}-\x{ff0f}\x{ff1a}-\x{ff20}\x{ff3b}-\x{ff40}'.
            '\x{ff5b}-\x{ff65}\x{ff70}\x{ff9e}\x{ff9f}\x{ffe0}-\x{fffd}';

            $classNumbers = '\x{30}-\x{39}\x{b2}\x{b3}\x{b9}\x{bc}-\x{be}\x{660}-\x{669}\x{6f0}-\x{6f9}'.
            '\x{966}-\x{96f}\x{9e6}-\x{9ef}\x{9f4}-\x{9f9}\x{a66}-\x{a6f}\x{ae6}-\x{aef}'.
            '\x{b66}-\x{b6f}\x{be7}-\x{bf2}\x{c66}-\x{c6f}\x{ce6}-\x{cef}\x{d66}-\x{d6f}'.
            '\x{e50}-\x{e59}\x{ed0}-\x{ed9}\x{f20}-\x{f33}\x{1040}-\x{1049}\x{1369}-'.
            '\x{137c}\x{16ee}-\x{16f0}\x{17e0}-\x{17e9}\x{17f0}-\x{17f9}\x{1810}-\x{1819}'.
            '\x{1946}-\x{194f}\x{2070}\x{2074}-\x{2079}\x{2080}-\x{2089}\x{2153}-\x{2183}'.
            '\x{2460}-\x{249b}\x{24ea}-\x{24ff}\x{2776}-\x{2793}\x{3007}\x{3021}-\x{3029}'.
            '\x{3038}-\x{303a}\x{3192}-\x{3195}\x{3220}-\x{3229}\x{3251}-\x{325f}\x{3280}-'.
            '\x{3289}\x{32b1}-\x{32bf}\x{ff10}-\x{ff19}';

            $classPunctuation = '\x{21}-\x{23}\x{25}-\x{2a}\x{2c}-\x{2f}\x{3a}\x{3b}\x{3f}\x{40}\x{5b}-\x{5d}'.
            '\x{5f}\x{7b}\x{7d}\x{a1}\x{ab}\x{b7}\x{bb}\x{bf}\x{37e}\x{387}\x{55a}-\x{55f}'.
            '\x{589}\x{58a}\x{5be}\x{5c0}\x{5c3}\x{5f3}\x{5f4}\x{60c}\x{60d}\x{61b}\x{61f}'.
            '\x{66a}-\x{66d}\x{6d4}\x{700}-\x{70d}\x{964}\x{965}\x{970}\x{df4}\x{e4f}'.
            '\x{e5a}\x{e5b}\x{f04}-\x{f12}\x{f3a}-\x{f3d}\x{f85}\x{104a}-\x{104f}\x{10fb}'.
            '\x{1361}-\x{1368}\x{166d}\x{166e}\x{169b}\x{169c}\x{16eb}-\x{16ed}\x{1735}'.
            '\x{1736}\x{17d4}-\x{17d6}\x{17d8}-\x{17da}\x{1800}-\x{180a}\x{1944}\x{1945}'.
            '\x{2010}-\x{2027}\x{2030}-\x{2043}\x{2045}-\x{2051}\x{2053}\x{2054}\x{2057}'.
            '\x{207d}\x{207e}\x{208d}\x{208e}\x{2329}\x{232a}\x{23b4}-\x{23b6}\x{2768}-'.
            '\x{2775}\x{27e6}-\x{27eb}\x{2983}-\x{2998}\x{29d8}-\x{29db}\x{29fc}\x{29fd}'.
            '\x{3001}-\x{3003}\x{3008}-\x{3011}\x{3014}-\x{301f}\x{3030}\x{303d}\x{30a0}'.
            '\x{30fb}\x{fd3e}\x{fd3f}\x{fe30}-\x{fe52}\x{fe54}-\x{fe61}\x{fe63}\x{fe68}'.
            '\x{fe6a}\x{fe6b}\x{ff01}-\x{ff03}\x{ff05}-\x{ff0a}\x{ff0c}-\x{ff0f}\x{ff1a}'.
            '\x{ff1b}\x{ff1f}\x{ff20}\x{ff3b}-\x{ff3d}\x{ff3f}\x{ff5b}\x{ff5d}\x{ff5f}-'.
            '\x{ff65}';

            $classCJK = '\x{3041}-\x{30ff}\x{31f0}-\x{31ff}\x{3400}-\x{4db5}\x{4e00}-\x{9fbb}\x{f900}-\x{fad9}';

            $string = trim($string);

            if (empty($string)) {
                return '';
            }

            $string = Inflector::lower(strip_tags($string));
            $string = html_entity_decode($string, ENT_NOQUOTES, 'utf-8');

            $string = preg_replace(
                '/([' . $classNumbers . ']+)[' . $classPunctuation . ']+(?=[' . $classNumbers . '])/u',
                '\1',
                $string
            );

            $string = preg_replace('/[' . $classExclude . ']+/u', ' ', $string);
            $string = preg_replace('/[._-]+/', ' ', $string);

            $string = preg_replace('/(?<=\s)[^\s]{1,' . $minLength . '}(?=\s)/Su', ' ', $string);
            $string = preg_replace('/^[^\s]{1,' . $minLength . '}(?=\s)/Su', '', $string);
            $string = preg_replace('/(?<=\s)[^\s]{1,' . $minLength . '}$/Su', '', $string);
            $string = preg_replace('/^[^\s]{1,' . $minLength . '}$/Su', '', $string);

            $string = trim(preg_replace('/\s+/', ' ', $string));

            return Inflector::unaccent($string);
        }

        public function serializeClosure(callable $closure)
        {
            $ref = new \ReflectionFunction($closure);

            $file   = $ref->getFileName();
            $start  = $ref->getStartLine();
            $end    = $ref->getEndline();

            $content = file($file);

            $code = [];

            for ($i = $start - 1; $i < $end; $i++) {
                $code[] = trim($content[$i]);
            }

            $code = implode('', $code) . 'end';

            $function = 'function (' . Utils::cut('function (', '});end', $code) . '}';

            return $function;
        }

        public function diff($from, $to)
        {
            $from       = mb_convert_encoding($from, 'HTML-ENTITIES', 'UTF-8');
            $to         = mb_convert_encoding($to, 'HTML-ENTITIES', 'UTF-8');
            $diffValues = array();
            $diffMask   = array();

            $dm = array();
            $n1 = count($from);
            $n2 = count($to);

            for ($j = -1; $j < $n2; $j++) $dm[-1][$j] = 0;
            for ($i = -1; $i < $n1; $i++) $dm[$i][-1] = 0;
            for ($i = 0; $i < $n1; $i++) {
                for ($j = 0; $j < $n2; $j++) {
                    if ($from[$i] == $to[$j]) {
                        $ad = $dm[$i - 1][$j - 1];
                        $dm[$i][$j] = $ad + 1;
                    } else {
                        $a1 = $dm[$i - 1][$j];
                        $a2 = $dm[$i][$j - 1];
                        $dm[$i][$j] = max($a1, $a2);
                    }
                }
            }

            $i = $n1 - 1;
            $j = $n2 - 1;

            while (($i > -1) || ($j > -1)) {
                if ($j > -1) {
                    if ($dm[$i][$j - 1] == $dm[$i][$j]) {
                        $diffValues[] = $to[$j];
                        $diffMask[] = 1;
                        $j--;
                        continue;
                    }
                }

                if ($i > -1) {
                    if ($dm[$i - 1][$j] == $dm[$i][$j]) {
                        $diffValues[] = $from[$i];
                        $diffMask[] = -1;
                        $i--;
                        continue;
                    }
                } {
                    $diffValues[] = $from[$i];
                    $diffMask[] = 0;
                    $i--;
                    $j--;
                }
            }

            $diffValues = array_reverse($diffValues);
            $diffMask = array_reverse($diffMask);

            return array('values' => $diffValues, 'mask' => $diffMask);
        }

        public function stringClosure(callable $closure)
        {
            $reflection = new \ReflectionFunction($closure);
            $file = new \SplFileObject($reflection->getFileName());

            $file->seek($reflection->getStartLine() - 1);

            $code = '';

            while ($file->key() < $reflection->getEndLine()) {
                $code .= $file->current();
                $file->next();
            }

            $begin  = strpos($code, 'function');
            $end    = strrpos($code, '}');
            $code   = substr($code, $begin, $end - $begin + 1);

            $code   = str_replace(["\r", "\n", "\t"], '', $code);

            return $code;
        }
    }
