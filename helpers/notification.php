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

    class NotificationLib
    {
        public function send($ids, $message)
        {
            $url = 'https://android.googleapis.com/gcm/send';

            $fields = [
                'registration_ids' => $ids,
                'data' => $message,
            ];

            $headers = [
                'Authorization: key=' . Config::get('gcm.key.android'),
                'Content-Type: application/json'
            ];
            // Open connection
            $ch = curl_init();

            // Set the url, number of POST vars, POST data
            curl_setopt($ch, CURLOPT_URL, $url);

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // Disabling SSL Certificate support temporarly
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

            // Execute post
            $result = curl_exec($ch);

            if ($result === false) {
                return false;
            }

            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            vd($result);
            vd(Config::get('gcm.key.android'));

            return $status;
        }

        public function pushToSession($account_id, array $data = [])
        {
            $session = Model::Sessionmobile()->firstOrCreate(['account_id' => (int) $account_id]);

            if ($session) {
                $session    = $session->toArray();

                $regid      = isAke($session, 'regid', false);
                $socket_id  = isAke($session, 'socket_id', false);

                // if ($regid) {
                //     $this->send([$regid], $data);
                // }

                if ($socket_id) {
                    $io = lib('io');
                    $data['id'] = $socket_id;
                    $message = $io->emit('bump', $data);
                }

                return Timer::get();
            }
        }
    }
