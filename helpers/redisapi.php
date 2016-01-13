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

    use Dbredis\Db;

    class RedisapiLib
    {
        private $method, $token, $db;

        public function __construct()
        {
            if (isset($_SERVER['REQUEST_URI']) && isset($_SERVER['REQUEST_METHOD'])) {
                if (!fnmatch('/apidb/*', $_SERVER['REQUEST_URI'])) {
                    return;
                }

                header("Access-Control-Allow-Origin: *");

                $this->method = isAke($_SERVER, 'REQUEST_METHOD', 'GET');
                $uri = str_replace('/apidb/', '', isAke($_SERVER, 'REQUEST_URI', '/apidb/'));
                $this->dispatch($uri);
            }
        }

        private function isGet()
        {
            return $this->method == 'GET';
        }

        private function isPost()
        {
            $_REQUEST = array_merge($_REQUEST, json_decode(file_get_contents('php://input'), true));

            return $this->method == 'POST';
        }

        private function isPut()
        {
            $_REQUEST = array_merge($_REQUEST, json_decode(file_get_contents('php://input'), true));

            return $this->method == 'PUT';
        }

        private function isDelete()
        {
            $_REQUEST = array_merge($_REQUEST, json_decode(file_get_contents('php://input'), true));

            return $this->method == 'DELETE';
        }

        private function isHead()
        {
            return $this->method == 'HEAD';
        }

        private function dispatch($uri)
        {
            if (fnmatch('authorize/*/*', $uri) && !fnmatch('authorize/*/*/*', $uri) && $this->isGet()) {
                list($publicKey, $privateKey) = explode('/', str_replace('authorize/', '', $uri), 2);

                return $this->authorize($publicKey, $privateKey);
            } else {
                if (fnmatch('*/*/*/*', $uri) && !fnmatch('*/*/*/*/*', $uri)) {
                    list($token, $collection, $method, $args) = explode('/', $uri, 4);
                }

                if (fnmatch('*/*/*', $uri)) {
                    list($token, $collection, $method) = explode('/', $uri, 34);
                }

                if (strlen($token) == 40) {
                    $this->token = $token;
                    $check = $this->checkToken();

                    if (fnmatch('*.*', $collection)) {
                        list($db, $table) = explode('.', $collection, 2);
                        $this->db = Db::instance($db, $table);
                    } else {
                        $this->db = Db::instance(SITE_NAME, $collection);
                    }

                    if (strstr($method, '?')) {
                        list($method, $query) = explode('?', $method, 2);
                        $query = urldecode($query);

                        parse_str($query, $query);

                        foreach ($query as $k => $v) {
                            $_REQUEST[$k] = $v;
                        }
                    }

                    $methods = get_class_methods($this);

                    if (in_array($method, $methods)) {
                        return isset($args) ? $this->$method([$args]) : $this->$method();
                    }
                }
            }

            Api::forbidden();
        }

        private function create()
        {
            if ($this->isPost()) {
                $data = isAke($_REQUEST, 'data', []);

                if (!empty($data)) {
                    if (Arrays::isAssoc($data)) {
                        unset($data['id']);
                        unset($data['created_at']);
                        unset($data['updated_at']);
                        unset($data['deleted_at']);

                        $row = $this->db->create($data)->save();

                        Api::render([
                            'status'            => 200,
                            'execution_time'    => Timer::get(),
                            'token'             => $this->token,
                            'data'              => $row->toArray()
                        ]);
                    }
                }
            }

            Api::forbidden();
        }

        private function edit()
        {
            if ($this->isPut()) {
                $data = isAke($_REQUEST, 'data', []);

                if (!empty($data)) {
                    if (Arrays::isAssoc($data)) {
                        $id = isAke($data, 'id', false);

                        if ($id) {
                            $row = $this->db->find($id);

                            if ($row) {
                                $row->fillAndSave($data);

                                Api::render([
                                    'status'            => 200,
                                    'execution_time'    => Timer::get(),
                                    'token'             => $this->token,
                                    'data'              => $row->toArray()
                                ]);
                            }
                        }
                    }
                }
            }

            Api::forbidden();
        }

        private function delete()
        {
            if ($this->isDelete()) {
                $data = isAke($_REQUEST, 'data', []);

                if (!empty($data)) {
                    if (Arrays::isAssoc($data)) {
                        $id = isAke($data, 'id', false);

                        if ($id) {
                            $row = $this->db->find($id);

                            if ($row) {
                                $row->delete();

                                Api::render([
                                    'status'            => 200,
                                    'execution_time'    => Timer::get(),
                                    'token'             => $this->token,
                                    'message'           => 'Row id [' . $id . '] deleted'
                                ]);
                            }
                        } else {
                            $where = isAke($data, 'where', false);

                            if ($where) {
                                $where = eval('return ' . $where . ';');
                                $cursor = $this->db->multiQuery($where)->cursor();

                                $count = $cursor->count();

                                $cursor->delete();

                                Api::render([
                                    'status'            => 200,
                                    'execution_time'    => Timer::get(),
                                    'token'             => $this->token,
                                    'message'           => $count . ' rows deleted'
                                ]);
                            }
                        }
                    }
                }
            }

            Api::forbidden();
        }

        private function query()
        {
            if ($this->isGet()) {
                $where  = isAke($_REQUEST, 'where', false);
                $order  = isAke($_REQUEST, 'order', false);
                $select = isAke($_REQUEST, 'select', false);
                $limit  = isAke($_REQUEST, 'limit', false);

                $query = $this->db;

                if ($where) {
                    $where = eval('return ' . $where . ';');
                    $query = $this->db->multiQuery($where);
                }

                if ($order) {
                    $order = eval('return ' . $order . ';');

                    if (count($order) == 1) {
                        $query = $query->order(current($order));
                    } elseif (count($order) == 2) {
                        $query = $query->order(current($order), strtoupper(end($order)));
                    }
                }

                if ($select) {
                    $select = eval('return ' . $select . ';');
                    $select = implode(',', array_values($select));
                    $query = $query->select($select);
                }

                if ($limit) {
                    $limit = eval('return ' . $limit . ';');

                    if (count($limit) == 1) {
                        $query = $query->limit(current($limit));
                    } elseif (count($limit) == 2) {
                        $query = $query->limit(current($limit), end($limit));
                    }
                }

                $rows = $query->cursor();

                $collection = [];

                foreach ($rows as $row) {
                    $collection[] = $row;
                }

                Api::render([
                    'status'            => 200,
                    'execution_time'    => Timer::get(),
                    'token'             => $this->token,
                    'data'              => $collection
                ]);
            }

            Api::forbidden();
        }

        private function find($args)
        {
            if ($this->isGet()) {
                if (!empty($args)) {
                    $id = current($args);

                    if (!fnmatch('*,*', $id)) {
                        $row = $this->db->find($id);

                        if ($row) {
                            Api::render([
                                'status'            => 200,
                                'execution_time'    => Timer::get(),
                                'token'             => $this->token,
                                'data'              => $row->toArray()
                            ]);
                        } else {
                            Api::NotFound();
                        }
                    } else {
                        $ids = str_replace(', ', ',', $id);

                        $rows = $this->db->where(['id', 'IN', $ids])->cursor();

                        $collection = [];

                        foreach ($rows as $row) {
                            $collection[] = $row;
                        }

                        Api::render([
                            'status'            => 200,
                            'execution_time'    => Timer::get(),
                            'token'             => $this->token,
                            'data'              => $collection
                        ]);
                    }
                }
            }

            Api::forbidden();
        }

        private function all()
        {
            if ($this->isGet()) {
                $rows = $this->db->cursor();
                $collection = [];

                foreach ($rows as $row) {
                    $collection[] = $row;
                }

                Api::render([
                    'status'            => 200,
                    'execution_time'    => Timer::get(),
                    'token'             => $this->token,
                    'data'              => $collection
                ]);
            }

            Api::forbidden();
        }

        private function authorize($publicKey, $privateKey)
        {
            $exists = Model::ApiUser()
            ->where(['public_key', '=', (string) $publicKey])
            ->where(['private_key', '=', (string) $privateKey])
            ->first(true);

            if ($exists) {
                $token = Utils::token();
                $row = Model::ApiAuth()->firstOrCreate(['user_id' => (int) $exists->id]);

                $row->setToken($token)->setExpiration(time() + 3600)->save();

                Api::render([
                    'status'            => 200,
                    'execution_time'    => Timer::get(),
                    'token'             => $token
                ]);
            }

            Api::forbidden();
        }

        private function checkToken()
        {
            $row = Model::ApiAuth()->where(['token', '=', $this->token])->first(true);

            if ($row) {
                $row->setExpiration(time() + 3600)->save();

                return true;
            }

            Api::forbidden();
        }
    }
