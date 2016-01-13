<?php
    /**
     * Thin is a swift Framework for PHP 5.4+
     *
     * @package    Thin
     * @version    1.0
     * @author     Gerald Plusquellec
     * @license    BSD License
     * @copyright  1996 - 2016 Gerald Plusquellec
     * @link       http://github.com/schpill/thin
     */
    namespace Handler;

    class Redis
    {
        public function write($id, $data, $changed = true)
        {
            $expires = time() + (int) ini_get('session.gc_maxlifetime');

            redis()->hset('sessionsphp.' . $id, 'expires', $expires);
            redis()->hset('sessionsphp.' . $id, 'data', $data);

            return true;
        }

        public function read($id)
        {
            $expires = redis()->hget('sessionsphp.' . $id, 'expires');
            $data = redis()->hget('sessionsphp.' . $id, 'data');

            return [
                'expires' => $expires,
                'data' => $data
            ];
        }

        public function delete($id)
        {
            redis()->del('sessionsphp.' . $id);

            return true;
        }

        public function gc($max)
        {
            $keys = redis()->keys('sessionsphp.*');

            foreach ($keys as $key) {
                $expires = redis()->hget($key, 'expires');

                if ($expires < time()) {
                    redis()->del($key);
                }
            }
        }
    }
