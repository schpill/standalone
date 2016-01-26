<?php
    namespace Thin;

    function memory_usage()
    {
        $mem_usage = memory_get_usage(true);

        if ($mem_usage < 1024) return $mem_usage . ' B';
        elseif ($mem_usage < 1048576) return round($mem_usage / 1024, 2) . ' KB';
        else return round($mem_usage / 1048576, 2) . ' MB';
    }

    function db($table, $db = SITE_NAME)
    {
        $model = Inflector::camelize($db . '_' . $table);

        return Raw::$model();
    }

    function model($table, $data = [], $db = SITE_NAME)
    {
        $model = Inflector::camelize($db . '_' . $table);

        return Raw::$model()->model($data);
    }

    function ldb($table, $db = SITE_NAME)
    {
        $model = Inflector::camelize($db . '_' . $table);

        return Way::$model();
    }

    function lmodel($table, $data = [], $db = SITE_NAME)
    {
        $model = Inflector::camelize($db . '_' . $table);

        return Way::$model()->model($data);
    }

    function load($lib, $args = [], $type = 'lib')
    {
        $type = strtolower($type);

        $dir = Config::get('app.' . $type . '.dir', Config::get('app.module.dir', APPLICATION_PATH) . DS . $type);

        if (!is_dir($dir)) {
            throw new Exception("The library $lib does not exist.");
        }

        $lib    = strtolower(Inflector::uncamelize($lib));
        $script = str_replace('_', DS, $lib) . '.php';

        if (fnmatch('*_*', $lib)) {
            $class  = 'Thin\\' . str_replace('_', '\\', $lib);
            $tab    = explode('\\', $class);
            $first  = $tab[1];
            $class  = str_replace('Thin\\' . $first, 'Thin\\' . ucfirst($first) . '_' . $type, $class);

            if (count($tab) > 2) {
                for ($i = 2; $i < count($tab); $i++) {
                    $seg    = trim($tab[$i]);
                    $class  = str_replace('\\' . $seg, '\\' . ucfirst($seg), $class);
                }
            }
        } else {
            $class = 'Thin\\' . ucfirst($lib) . '_' . $type;
        }

        $file = $dir . DS . $script;

        if (File::exists($file)) {
            require_once $file;

            return lib('app')->make($class, $args);
        }

        throw new Exception("The library $class does not exist.");
    }

    function myModel($table)
    {
        return rdb(SITE_NAME, $table);
    }

    function exception($type, $message)
    {
        $what = ucfirst(Inflector::camelize($type . '_exception'));
        $class = 'Thin\\' . $what;

        if (!class_exists($class)) {
            $code = 'namespace Thin; class ' . $what . ' extends \\Exception {}';
            eval($code);
        }

        throw new $class($message);
    }

    function factory($type, $native = null)
    {
        loader('dyn');

        $what   = ucfirst(Inflector::camelize($type . '_factory'));
        $class  = 'Thin\\' . $what;

        if (!class_exists($class)) {
            $code = 'namespace Thin; class ' . $what . ' extends DynLib {}';
            eval($code);
        }

        return new $class($native);
    }

    function uploadFile($field, $name)
    {
        $bucket = container()->bucket();

        if (Arrays::exists($field, $_FILES)) {
            $fileupload         = $_FILES[$field]['tmp_name'];
            $fileuploadName     = $_FILES[$field]['name'];

            if (strlen($fileuploadName)) {
                $tab = explode('.', $fileuploadName);
                $data = fgc($fileupload);

                if (!strlen($data)) {
                    return null;
                }

                return $bucket->uploadNews([
                    'data' => $data,
                    'name' => $name
                ]);
            }
        }

        return null;
    }

    function exb($balise, $postTmp)
    {
        list($d, $seg) = explode("<$balise>", $postTmp, 2);
        list($seg, $d) = explode("</$balise>", $seg, 2);

        $seg = str_replace(['<![CDATA[', ']]>'], '', $seg);

        return $seg;
    }

    function clipp()
    {
        return lib('clipp');
    }

    function isCached($k, callable $c, $maxAge = null, $args = [])
    {
        $cached = redis()->get($k);

        if ($maxAge) {
            $age = redis()->get($k . '.age');

            if ($age) {
                if (time() > $age) {
                    $cached = null;
                }
            } else {
                $cached = null;
            }
        }

        if (!$cached) {
            $data = call_user_func_array($c, $args);

            redis()->set($k, serialize($data));

            if ($maxAge) {
                if ($maxAge < 1444000000) {
                    $maxAge = ($maxAge * 60) + time();
                }

                redis()->set($k . '.age', $maxAge);
            }
        } else {
            $data = unserialize($cached);
        }

        return $data;
    }

    function coll($c = [])
    {
        return lib('collection', [$c]);
    }

    function s3()
    {
        $client = \Aws\S3\S3Client::factory([
            'credentials'   => [
                'key'       => Config::get('aws.access_key'),
                'secret'    => Config::get('aws.secret_key')
            ],
            'region'        => Config::get('aws.region', 'eu-west-1'),
            'version'       => Config::get('aws.version', 'latest'),
        ]);

        $s3Adapter  = new \League\Flysystem\AwsS3v3\AwsS3Adapter($client, Config::get('s3.bucket'));
        $cacheStore = new \League\Flysystem\Cached\Storage\Memory();
        $adapter    = new \League\Flysystem\Cached\CachedAdapter($s3Adapter, $cacheStore);

        return new \League\Flysystem\Filesystem($adapter);
    }

    function kh($ns = 'core.cache')
    {
        return new \Raw\Store($ns);
    }

    function isKh($k, callable $c, $maxAge = null, $args = [])
    {
        $cached = kh()->get($k);

        if ($maxAge) {
            $age = kh()->get($k . '.age');

            if ($age) {
                if (time() > $age) {
                    $cached = null;
                }
            } else {
                $cached = null;
            }
        }

        if (!$cached) {
            $data = call_user_func_array($c, $args);

            kh()->set($k, $data);

            if ($maxAge) {
                if ($maxAge < 1444000000) {
                    $maxAge = ($maxAge * 60) + time();
                }

                kh()->set($k . '.age', $maxAge);
            }
        } else {
            $data = $cached;
        }

        return $data;
    }

    function getCached($k, callable $c, $maxAge = null, $args = [])
    {
        $old = Config::get('dir.raw.store');

        Config::set('dir.raw.store', '/home/storage');

        $cached = kh()->get($k);

        if ($maxAge) {
            $age = kh()->get($k . '.age');

            if ($age) {
                if (time() > $age) {
                    $cached = null;
                }
            } else {
                $cached = null;
            }
        }

        if (!$cached) {
            $data = call_user_func_array($c, $args);

            kh()->set($k, $data);

            if ($maxAge) {
                if ($maxAge < 1444000000) {
                    $maxAge = ($maxAge * 60) + time();
                }

                kh()->set($k . '.age', $maxAge);
            }
        } else {
            $data = $cached;
        }

        Config::set('dir.raw.store', $old);

        return $data;
    }

    function xCache($k, callable $c, $maxAge = null, $args = [])
    {
        $dir = '/home/storage/xcache';

        if (!is_dir($dir)) {
            File::mkdir($dir);
        }

        $hash = sha1($k);

        $f = substr($hash, 0, 2);
        $s = substr($hash, 2, 2);

        $dir .= DS . $f;

        if (!is_dir($dir)) {
            File::mkdir($dir);
        }

        $dir .= DS . $s;

        if (!is_dir($dir)) {
            File::mkdir($dir);
        }

        $file = $dir . DS . $k;

        if (file_exists($file)) {
            if (is_null($maxAge)) {
                return unserialize(File::read($file));
            } else {
                if (filemtime($file) >= time()) {
                    return unserialize(File::read($file));
                } else {
                    File::delete($file);
                }
            }
        }

        $data = call_user_func_array($c, $args);

        File::put($file, serialize($data));

        if (!is_null($maxAge)) {
            if ($maxAge < 1444000000) {
                $maxAge = ($maxAge * 60) + time();
            }

            touch($file, $maxAge);
        }

        return $data;
    }

    function ageCache($k, callable $c, $maxAge = null, $args = [])
    {
        $dir = '/home/storage/aged';

        if (!is_dir($dir)) {
            File::mkdir($dir);
        }

        $hash = sha1($k);

        $f = substr($hash, 0, 2);
        $s = substr($hash, 2, 2);

        $dir .= DS . $f;

        if (!is_dir($dir)) {
            File::mkdir($dir);
        }

        $dir .= DS . $s;

        if (!is_dir($dir)) {
            File::mkdir($dir);
        }

        $file = $dir . DS . $k;

        if (file_exists($file)) {
            if (is_null($maxAge)) {
                return unserialize(File::read($file));
            } else {
                if (filemtime($file) >= $maxAge) {
                    return unserialize(File::read($file));
                } else {
                    File::delete($file);
                }
            }
        }

        $data = call_user_func_array($c, $args);

        File::put($file, serialize($data));

        if (!is_null($maxAge)) {
            if ($maxAge < 1000000000) {
                $maxAge = ($maxAge * 60) + time();
            }

            touch($file, $maxAge);
        }

        return $data;
    }

    function until($k, callable $c, $until, $args = [])
    {
        $old = Config::get('dir.raw.store');

        Config::set('dir.raw.store', '/home/storage');

        $cached = kh()->get($k);

        $age = kh()->get($k . '.age');

        if (is_callable($until)) {
            $until = $until();
        }

        if ($age) {
            if ($age < $until) {
                $cached = null;
            }
        }

        if (!$cached) {
            $data = call_user_func_array($c, $args);

            kh()->set($k, $data);
            kh()->set($k . '.age', time());
        } else {
            $data = $cached;
        }

        Config::set('dir.raw.store', $old);

        return $data;
    }

    function khView($key, $what = null)
    {
        $dir = '/home/storage/' . SITE_NAME . '/views';
        $file = $dir . DS . $key . '.cache';

        if (!is_null($what)) {
            File::delete($file);
            file_put_contents($file, $what);
            touch($file, Config::get('app.view.ttl', strtotime('+2 hour')));
        } else {
            if (file_exists($file)) {
                if (filemtime($file) > time()) {
                    return $file;
                } else {
                    File::delete($file);
                }
            }
        }

        return $what;
    }

    function dbm($collection)
    {
        return Model::Nodes()->getCollection($collection);
    }

    function set($k, $v)
    {
        return Now::set('helpers.bag.' . $k, $v);
    }

    function get($k, $d = null)
    {
        return Now::get('helpers.bag.' . $k, $d);
    }

    function has($k)
    {
        return Now::has('helpers.bag.' . $k);
    }

    function del($k)
    {
        return Now::del('helpers.bag.' . $k);
    }

    function sc()
    {
        return lib('shortcode');
    }

    function next(callable $c, $next = null, $args = [])
    {
        $res = call_user_func_array($c, $args);

        if (!is_null($next)) {
            if (is_callable($next)) {
                return call_user_func_array($next, [$res]);
            }
        }

        return $res;
    }

    function aged($key, $age)
    {
        $file = '/home/storage/ages/' . $key;

        if (is_file($file)) {
            if (filemtime($file) >= time()) {
                return false;
            } else {
                File::delete($file);
            }
        } else {
            @touch($file, $age);
        }

        return true;
    }

    function fmr($ns = 'core')
    {
        return lib('ephemere', [$ns]);
    }

    function dyn($obj = null)
    {
        return lib('dyn', [$obj]);
    }

    function schedule($t, $a = null)
    {
        return lib('due', [$t, $a]);
    }

    function reg($ns = 'core')
    {
        return Now::instance($ns);
    }

    function bag($k, $data = [])
    {
        return clipp()->bag($k, $data);
    }

    function env($k, $d = null)
    {
        if (file_exists(path('module') . DS . '.env')) {
            $ini = parse_ini_file(path('module') . DS . '.env');

            return isset($ini[$k]) ? $ini[$k] : $d;
        }

        return $d;
    }

    function middleware($k, $v = null, $d = null)
    {
        $k = "helpers.middlewares.$k";

        if ($v) {
            if (is_callable($v)) {
                Now::set($k, $v);
            } else {
                return false;
            }
        } else {
            $c = Now::get($k, $d);

            if (is_callable($c)) {
                return $c;
            } else {
                return false;
            }
        }

        return true;
    }

    function dispatch($k, $v = null, $d = null)
    {
        $k = "helpers.commands.$k";

        if ($v) {
            if (is_callable($v)) {
                Now::set($k, $v);
            } else {
                return false;
            }
        } else {
            $c = Now::get($k, $d);

            if (is_callable($c)) {
                return $c;
            } else {
                return false;
            }
        }

        return true;
    }

    function appli($k, $v = null, $d = null)
    {
        $k = 'helpers.applis.' . $k;

        if (is_null($v)) {
            return Now::get($k, $d);
        } else {
            return Now::set($k, $v);
        }
    }

    function path($k, $v = null, $d = null)
    {
        $k = 'helpers.paths.' . $k;

        if (is_null($v)) {
            return Now::get($k, $d);
        } else {
            return Now::set($k, $v);
        }
    }

    function log($name = '')
    {
        return lib('log', [$name . date('d_m_Y')]);
    }

    function loader($lib)
    {
        $class = 'Thin\\' . ucfirst(strtolower($lib)) . 'Lib';

        if (!class_exists($class)) {
            $file = VENDOR . DS . 'schpill/standalone/helpers' . DS . $lib . '.php';

            if (file_exists($file)) {
                require_once $file;

                return true;
            } else {
                $file = path('module') . DS . 'lib' . DS . $lib . '.php';

                if (file_exists($file)) {
                    require_once $file;

                    return true;
                }
            }

            return false;
        }

        return true;
    }

    function loaderCore($lib)
    {
        $class = 'Thin\\' . ucfirst(strtolower($lib)) . 'Core';

        if (!class_exists($class)) {
            $file = VENDOR . DS . 'schpill/standalone/core' . DS . $lib . '.php';

            if (file_exists($file)) {
                require_once $file;

                return true;
            } else {
                $file = path('module') . DS . 'core' . DS . $lib . '.php';

                if (file_exists($file)) {
                    require_once $file;

                    return true;
                }
            }

            return false;
        }

        return true;
    }

    function response($message = '', $code = 200, array $headers = [])
    {
        $statuses = array(
            100 => 'Continue',
            102 => 'Processing',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            207 => 'Multi-Status',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            306 => 'unused',
            307 => 'Temporary Redirect',
            400 => 'Bad Request',
            401 => 'Authorization Required',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Time-out',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Large',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            418 => 'I\'m a teapot',
            419 => 'unused',
            420 => 'unused',
            421 => 'Misdirected Request',
            422 => 'Unprocessable Entity',
            423 => 'Locked',
            424 => 'Failed Dependency',
            425 => 'No code',
            426 => 'Upgrade Required',
            500 => 'Internal Server Error',
            501 => 'Method Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Temporarily Unavailable',
            504 => 'Gateway Time-out',
            505 => 'HTTP Version Not Supported',
            506 => 'Variant Also Negotiates',
            507 => 'Insufficient Storage',
            508 => 'Loop Detected',
            509 => 'Bandwidth Limit Exceeded',
            510 => 'Not Extended',
            511 => 'Network Authentication Required'
        );

        $status = isset($statuses[$code]) ? $statuses[$code] : 'OK';

        thin('response')->setStatusCode($code, $status)->sendHeaders($headers)->setContent($message)->send();

        exit;
    }

    function at(callable $m, $ts = 'now', $args = [])
    {
        $time   = 'now' == $ts ? time() : $ts;
        $c      = lib('utils')->stringClosure($m);
        $hash   = sha1($c . serialize($args));

        $row = Model::Bus()->firstOrCreate(['hash' => $hash])->setArgs(serialize($args))->setMethod($c)->setStatus(2)->setTime($time)->save();

        if ('now' == $ts) {
            $file = Config::get('app.module.dir') . DS . 'background.php';

            if (file_exists($file)) {
                $cmd = 'php ' . $file;
                lib('utils')->backgroundTask($cmd);
            }
        }
    }

    function now(callable $m, $args = [])
    {
        return at($m, 'now', $args);
    }

    function async(callable $m, $args = [])
    {
        return at($m, 'now', $args);
    }

    function forever($ns = 'user')
    {
        if (php_sapi_name() == 'cli' || PHP_SAPI == 'cli') {
            return sha1(SITE_NAME . '::cli');
        }

        $ns         = SITE_NAME . '_' . $ns;
        $cookie     = isAke($_COOKIE, $ns, null);

        if (!$cookie) {
            $cookie = Utils::UUID();
        }

        setcookie($ns, $cookie, strtotime('+1 year'), '/', '.' . Config::get('module.domain'));

        return $cookie;
    }

    function limit($key, $max, $timeout, $interval, $cb)
    {
        $session    = session('throttle');
        $now        = time();

        $row = $session->get($key, []);

        $allowed = isAke($row, 'allowed', false);

        if ($allowed) {
            $allowedTime = $now + ($timeout * 60);
            $timeLeft    = $now - $allowed;
            $secondsLeft = $timeLeft * -1;

            if ($timeLeft < 0) {
                $cb($secondsLeft);
            } else {
                $row['pass']  = 1;
                $row['setAt'] = $now;

                if ($row['pass'] == $max) {
                    $row['allowed'] = $now + $timeout;
                }

                $session->set($key, $row);
            }
        } else {
            if (!isset($row['setAt'])) {
                $row['setAt'] = $now;
            } else {
                if ($now > ($row['setAt'] + $interval)) {
                    $row['setAt'] = $now;
                    $row['pass']  = 0;
                }
            }

            if (isset($row['pass'])) {
                $row['pass']++;
            } else {
                $row['pass'] = 1;
            }

            if ($row == $max) {
                $row['allowed'] = $now + $timeout;
            }

            $session->set($key, $row);
        }
    }

    function fluent()
    {
        static $fluents;

        $args = func_get_args();
        $name = array_shift($args);

        if (!isset($fluents)) {
            $fluents = [];
        }

        $i = isAke($fluents, $name, null);

        if (!$i) {
            $i = lib('misc', $args);

            $fluents[$name] = $i;
        }

        return $i;
    }

    function loadCore($lib, $args = null)
    {
        try {
            core($lib, $args);

            return true;
        } catch (Excexption $e) {
            return false;
        }
    }

    function core($lib, $args = null)
    {
        $lib    = strtolower(Inflector::uncamelize($lib));
        $script = str_replace('_', DS, $lib) . '.php';

        if (fnmatch('*_*', $lib)) {
            $class  = 'Thin\\' . str_replace('_', '\\', $lib);
            $tab    = explode('\\', $class);
            $first  = $tab[1];
            $class  = str_replace('Thin\\' . $first, 'Thin\\' . ucfirst($first) . 'Core', $class);

            if (count($tab) > 2) {
                for ($i = 2; $i < count($tab); $i++) {
                    $seg    = trim($tab[$i]);
                    $class  = str_replace('\\' . $seg, '\\' . ucfirst($seg), $class);
                }
            }
        } else {
            $class = 'Thin\\' . ucfirst($lib) . 'Core';
        }

        $file = VENDOR . DS . 'schpill/standalone/core' . DS . $script;

        if (!file_exists($file)) {
            $file = path('module') . DS . 'core' . DS . $script;
        }

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
                    $check = new \ReflectionMethod($class, 'instance');

                    if ($check->isStatic()) {
                        return call_user_func_array([$class, 'instance'], $args);
                    }
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

        throw new Exception("The core $class does not exist.");
    }

    function hook($file, $cb = null)
    {
        static $hooks;

        if (!isset($hooks)) {
            $hooks = [];
        }

        if (is_null($cb)) {
            return isAke($hooks, $file, null);
        }

        $hooks[$file] = $cb;
    }

    function isAke($tab, $key, $default = [])
    {
        if (true === is_object($tab)) {
            $methods = get_class_methods($tab);

            if (core('array')->in('toArray', $methods)) {
                $tab = $tab->toArray();
            } else {
                $tab = (array) $tab;
            }
        }

        return core('array')->is($tab) ?
            core('array')->isAssoc($tab) ?
                core('array')->exists($key, $tab) ?
                    $tab[$key] :
                $default :
            $default :
        $default;
    }

    function r($type = null)
    {
        $type = is_null($type) ? $_REQUEST : $type;

        return coll($type);
    }

    function lib($lib, $args = [])
    {
        try {
            return \lib($lib, $args);
        } catch (\Exception $e) {
            return \lib('app')->make($lib, $args);
        }
    }

    function loadModel($class, $data)
    {
        $typeModel  = str_replace(['Thin\\', 'Lib'], '', get_class($class));
        $db         = $class->db();
        $table      = $class->table();
        $dir        = path('module') . DS . 'models' . DS . $typeModel;

        $modelFile = $dir . DS . Inflector::lower($db) . DS . ucfirst(Inflector::lower($table)) . '.php';

        if (!is_dir(path('module') . DS . 'models')) {
            File::mkdir(path('module') . DS . 'models');
        }

        if (!is_dir($dir)) {
            File::mkdir($dir);
        }

        if (!is_dir($dir . DS . Inflector::lower($db))) {
            File::mkdir($dir . DS . Inflector::lower($db));
        }

        if (!File::exists($modelFile)) {
            $tpl = '<?php
    namespace Thin;

    loader("model");

    class ' . $typeModel . ucfirst(Inflector::lower($db)) . ucfirst(Inflector::lower($table)) . 'Model extends ModelLib {
        /* Make hooks of model */
        public function _hooks()
        {
            $obj = $this;
            // $this->_hooks[\'beforeCreate\'] = function () use ($obj) {};
            // $this->_hooks[\'beforeRead\'] = ;
            // $this->_hooks[\'beforeUpdate\'] = ;
            // $this->_hooks[\'beforeDelete\'] = ;
            // $this->_hooks[\'afterCreate\'] = ;
            // $this->_hooks[\'afterRead\'] = ;
            // $this->_hooks[\'afterUpdate\'] = ;
            // $this->_hooks[\'afterDelete\'] = ;
            // $this->_hooks[\'validate\'] = function () use ($data) {
            //     return true;
            // };
        }
    }';

            File::put($modelFile, $tpl);
        }

        $instanciate = '\\Thin\\' . $typeModel . ucfirst(Inflector::lower($db)) . ucfirst(Inflector::lower($table)) . 'Model';

        if (!class_exists($instanciate)) {
            require_once $modelFile;
        }

        return new $instanciate($class, $data);
    }

    function logg($message, $type = 'info')
    {
        static $i;

        if (!$i) {
            $i = core('log');
        }

        return call_user_func_array(
            [$i, $type],
            [$message]
        );
    }

    require_once __DIR__ . DS . 'traits.php';
