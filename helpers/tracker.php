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

    class TrackerLib
    {

        private static $SDK_ID = "php";
        private static $default_config = array(
            "domain" => "",
            "cookie_name" => "TrackerLib",
            "cookie_domain" => "",
            "cookie_path" => "/",
            "ping" => true,
            "ping_interval" => 12000,
            "idle_timeout" => 300000,
            "download_tracking" => true,
            "outgoing_tracking" => true,
            "download_pause" => 200,
            "outgoing_pause" => 400,
            "ignore_query_url" => true,
            "hide_campaign" => false,
            "ip_address" => "",
            "cookie_value" => "",
            "app" => ""
        );

        /**
        * Custom configuration stack.
        * If the user has set up custom configuration, store it in this array. It will be sent when the tracker is ready.
        * @var array
        */
        private $custom_config;

        /**
        * Current configuration
        * Default configuration array, updated by Manual configurations.
        * @var array
        */
        public $current_config;

        /**
        * User array.
        * If the user has been identified, store his information in this array
        * KEYS:
        * email (string) – Which displays the visitor’s email address and it will be used as a unique identifier instead of cookies.
        * name (string) – Which displays the visitor’s full name
        * company (string) – Which displays the company name or account of your customer
        * avatar (string) – Which is a URL link to a visitor avatar
        * other (string) - You can define any attribute you like and have that detail passed from within the visitor live stream data when viewing TrackerLib
        * @var array
        */
        private $user;

        /**
        * Has the latest information on the user been sent to TrackerLib?
        * @var boolean
        */
        private $user_up_to_date;

        /**
        * Events array stack
        * Each item of the stack is either:
        * - an empty array (if pv event)
        * - an array(2) (if custom event)
        * O (string) - the name of the event
        * 1 (array) - properties associated with that action
        * @var array
        */
        private $events;

        /**
        * Is JavaScript Tracker Ready?
        * @var boolean
        */
        private $tracker_ready;

        /**
         * TrackerLib Analytics
         * @param none
         * @return none
         * @constructor
         */
        function __construct($config_params = null)
        {
            //Tracker is not ready yet
            $this->tracker_ready = false;

            //Current configuration is Default
            $this->current_config = TrackerLib::$default_config;

            //Set the default IP
            $this->current_config["ip_address"] = $this->getIp();

            //Set the domain name and the cookie_domain
            $this->current_config["domain"] = $_SERVER["HTTP_HOST"];
            $this->current_config["cookie_domain"] = $_SERVER["HTTP_HOST"];

            //configure app ID
            $this->current_config["app"] = TrackerLib::$SDK_ID;
            $this->custom_config = array("app" => TrackerLib::$SDK_ID);

            //If configuration array was passed, configure TrackerLib
            if (isset($config_params)) {
                $this->config($config_params);
            }

            //Get cookie or generate a random one
            $this->current_config["cookie_value"] = isset($_COOKIE[$this->current_config["cookie_name"]])
            ? $_COOKIE[$this->current_config["cookie_name"]]
            : TrackerLib::RandomString();

            //We don't have any info on the user yet, so he is up to date by default.
            $this->user_up_to_date = true;
        }

        /**
         * Random Cookie generator in case the user doesn't have a cookie yet. Better to use a hash of the email.
         * @param none
         * @return string
         */
        private static function RandomString()
        {
            $characters = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
            $randstring = "";

            for ($i = 0; $i < 12; $i++) {
                $randstring .= $characters[rand(0, strlen($characters)-1)];
            }

            return $randstring;
        }



        /**
         * Prepares the http request and sends it.
         * @param boolean Is this a tracking event or are we just identifying a user?
         * @param (optional) array
         * @return none
         */
        private function httpRequest($is_tracking, $event = null)
        {
            $base_url = "http://cloud.clippcity.com/track/";

            //Config params
            $config_params = "?host=" . urlencode($this->current_config["domain"]);
            $config_params .= "&cookie=" . urlencode($this->current_config["cookie_value"]);
            $config_params .= "&ip=" . urlencode($this->current_config["ip_address"]);
            $config_params .= "&timeout=" . urlencode($this->current_config["idle_timeout"]);

            //User params
            $user_params = "";

            if ( isset($this->user) ) {
                foreach($this->user as $option => $value) {
                    if (! (empty($option) || empty($value))) {
                        $user_params .= "&cv_" . urlencode($option) . "=" . urlencode($value);
                    }
                }
            }

            //Just identifying
            if (!$is_tracking ) {
                $url = $base_url . "identify/" . $config_params . $user_params . "&ce_app=" . $this->current_config["app"];
            } else {
                //Event params
                $event_params = "";
                if ( $event != null ) {
                    $event_params .= "&ce_name=" . urlencode($event[0]);
                    foreach($event[1] as $option => $value) {
                        if (!(empty($option) || empty($value))) {
                            $event_params .= "&ce_" . urlencode($option) . "=" . urlencode($value);
                        }
                    }
                } else {
                    $event_params .= "&ce_name=pv&ce_url=" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                }

                $url = $base_url . "ce/" . $config_params . $user_params . $event_params . "&ce_app=" . $this->current_config["app"];
            }

            //Send the request
            if (function_exists('curl_version')) {
                $this->getData($url);
            } else {
                $opts = array(
                    'http'=>array(
                        'method'=>"GET",
                        'header'=>"User-Agent: ".$_SERVER['HTTP_USER_AGENT']
                    )
                );

                $context = stream_context_create($opts);

                file_get_contents($url, false, $context);
            }
        }

        /**
        * Configures TrackerLib
        * @param array
        * @return TrackerLib object
        */
        public function config($args)
        {
            if (! isset($this->custom_config)) {
                $this->custom_config = array();
            }

            foreach( $args as $option => $value) {
                if ( array_key_exists($option, TrackerLib::$default_config) ) {
                    if ( gettype($value) == gettype( rackerLib::$default_config[$option])) {
                        if ($option != "ip_address" && $option != "cookie_value") {
                            $this->custom_config[$option] = $value;
                        }

                        $this->current_config[$option] = $value;
                        //If the user is customizing the name of the cookie, check again if the user already has one.

                        if ($option == "cookie_name") {
                            $this->current_config["cookie_value"] = isset($_COOKIE[$current_config["cookie_name"]])
                            ? $_COOKIE[$current_config["cookie_name"]]
                            : $this->current_config["cookie_value"];
                        }
                    } else {
                        trigger_error("Wrong value type in configuration array for parameter " . $option . ". Recieved " . gettype($value) . ", expected " . gettype(TrackerLib::$default_config[$option]) . ".");
                    }
                } else {
                    trigger_error("Unexpected parameter in configuration array: " . $option . ".");
                }
            }

            return $this;
        }

        /**
        * Identifies User
        * @param array
        * @return TrackerLib object
        */
        public function identify($identified_user, $override = false)
        {
            if (isset($identified_user["email"]) && ! empty($identified_user["email"])) {
                $this->user = $identified_user;
                $this->user_up_to_date = false;

                if ($override || !isset($_COOKIE[$this->current_config["cookie_name"]])) {
                    $this->current_config["cookie_value"] = crc32($identified_user["email"]);
                }
            }

            return $this;
        }

        /**
        * Tracks Custom Event. If no parameters are specified, will simply track pageview.
        * @param string
        * @param array
        * @param (optional) boolean
        * @return TrackerLib object
        */
        public function track($event = null, $args = array(), $back_end_processing = false)
        {
            if ($back_end_processing) {
                $http_event = null;

                if ($event != null) {
                    $http_event = array($event, $args);
                }

                $this->httpRequest(true, $http_event);

                return $this;
            }

            if ($event == null) {
                if ($this->tracker_ready) {
                } else {
                    if (! isset($this->events) ) {
                        $this->events = array();
                    }

                    array_push($this->events, array());
                }

                return $this;
            }

            if (!isset($this->events)) {
                $this->events = array();
            }

            array_push($this->events, array($event, $args));

            if ( $this->tracker_ready ) {
            }

            return $this;
        }

        /**
        * Pushes unprocessed actions
        * @param none
        * @param (optional) boolean
        * @return none
        */
        public function push($back_end_processing = false)
        {
            if ($back_end_processing) {
                $this->httpRequest(false);
                $this->user_up_to_date = true;
            } elseif($this->tracker_ready) {

            }
        }

        /**
        * Sets the cookie from the back-end. Call this function before any headers are sent (HTTP restrictions).
        * @param none
        * @return none
        */
        public function setCookie()
        {
            setcookie(
                $this->current_config["cookie_name"],
                $this->current_config["cookie_value"],
                time()+(60*60*24*365*2),
                $this->current_config["cookie_path"],
                $this->current_config["cookie_domain"]
            );
        }

        /**
        * Retrieves the user's IP address
        * @param none
        * @return String
        */
        private function getIp()
        {
            if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
                $ips = explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"]);

                return trim($ips[0]);
            } else {
                return $_SERVER["REMOTE_ADDR"];
            }
        }

        /**
        * Gets the data from a URL using CURL
        * @param String
        * @return String
        */
        private function getData($url)
        {
            $ch = curl_init();
            $timeout = 5;
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
            $data = curl_exec($ch);
            curl_close($ch);

            return $data;
        }
    }
