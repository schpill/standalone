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

    class IpLib
    {
        public function ipv4InRange($ip, $range)
        {
            if (strpos($range, '/') !== false) {
                // $range is in IP/NETMASK format
                list($range, $netmask) = explode('/', $range, 2);

                if (strpos($netmask, '.') !== false) {
                    // $netmask is a 255.255.0.0 format
                    $netmask        = str_replace('*', '0', $netmask);
                    $netmask_dec    = ip2long($netmask);

                    return ((ip2long($ip) & $netmask_dec) == (ip2long($range) & $netmask_dec));
                } else {
                    // $netmask is a CIDR size block
                    // fix the range argument
                    $x = explode('.', $range);

                    while(count($x) < 4) $x[] = '0';

                    list($a,$b,$c,$d) = $x;
                    $range      = sprintf("%u.%u.%u.%u", empty($a) ? '0' : $a, empty($b) ? '0': $b, empty($c) ? '0': $c, empty($d) ? '0' : $d);
                    $range_dec  = ip2long($range);
                    $ip_dec     = ip2long($ip);

                    # Strategy 1 - Create the netmask with 'netmask' 1s and then fill it to 32 with 0s
                    #$netmask_dec = bindec(str_pad('', $netmask, '1') . str_pad('', 32-$netmask, '0'));

                    # Strategy 2 - Use math to create it
                    $wildcard_dec = pow(2, (32-$netmask)) - 1;
                    $netmask_dec = ~ $wildcard_dec;

                    return (($ip_dec & $netmask_dec) == ($range_dec & $netmask_dec));
                }
            } else {
                // range might be 255.255.*.* or 1.2.3.0-1.2.3.255
                if (strpos($range, '*') !==false) { // a.b.*.* format
                    // Just convert to A-B format by setting * to 0 for A and 255 for B
                    $lower = str_replace('*', '0', $range);
                    $upper = str_replace('*', '255', $range);
                    $range = "$lower-$upper";
                }

                if (strpos($range, '-')!==false) { // A-B format
                    list($lower, $upper) = explode('-', $range, 2);
                    $lower_dec = (float)sprintf("%u",ip2long($lower));
                    $upper_dec = (float)sprintf("%u",ip2long($upper));
                    $ip_dec = (float)sprintf("%u",ip2long($ip));

                    return ( ($ip_dec>=$lower_dec) && ($ip_dec<=$upper_dec) );
                }

                return false;
            }
        }

        public function ip2long6($ip)
        {
            if (substr_count($ip, '::')) {
                $ip = str_replace('::', str_repeat(':0000', 8 - substr_count($ip, ':')) . ':', $ip);
            }

            $ip = explode(':', $ip);
            $r_ip = '';

            foreach ($ip as $v) {
                $r_ip .= str_pad(base_convert($v, 16, 2), 16, 0, STR_PAD_LEFT);
            }

            return base_convert($r_ip, 2, 10);
        }

        public function getIpv6Full($ip)
        {
            $pieces = explode ("/", $ip, 2);
            $left_piece = $pieces[0];
            $right_piece = null;

            if (count($pieces) > 1) $right_piece = $pieces[1];

            // Extract out the main IP pieces
            $ip_pieces = explode("::", $left_piece, 2);
            $main_ip_piece = $ip_pieces[0];
            $last_ip_piece = null;

            if (count($ip_pieces) > 1) $last_ip_piece = $ip_pieces[1];

            // Pad out the shorthand entries.
            $main_ip_pieces = explode(":", $main_ip_piece);

            foreach($main_ip_pieces as $key=>$val) {
                $main_ip_pieces[$key] = str_pad($main_ip_pieces[$key], 4, "0", STR_PAD_LEFT);
            }

            // Check to see if the last IP block (part after ::) is set
            $last_piece = "";
            $size = count($main_ip_pieces);

            if (trim($last_ip_piece) != "") {
                $last_piece = str_pad($last_ip_piece, 4, "0", STR_PAD_LEFT);

                // Build the full form of the IPV6 address considering the last IP block set
                for ($i = $size; $i < 7; $i++) {
                    $main_ip_pieces[$i] = "0000";
                }

                $main_ip_pieces[7] = $last_piece;
            } else {
                // Build the full form of the IPV6 address
                for ($i = $size; $i < 8; $i++) {
                    $main_ip_pieces[$i] = "0000";
                }
            }

            // Rebuild the final long form IPV6 address
            $final_ip = implode(":", $main_ip_pieces);

            return ip2long6($final_ip);
        }

        public function ipv6InRange($ip, $range_ip)
        {
            $pieces = explode ("/", $range_ip, 2);
            $left_piece = $pieces[0];
            $right_piece = $pieces[1];

            // Extract out the main IP pieces
            $ip_pieces = explode("::", $left_piece, 2);
            $main_ip_piece = $ip_pieces[0];
            $last_ip_piece = $ip_pieces[1];

            // Pad out the shorthand entries.
            $main_ip_pieces = explode(":", $main_ip_piece);

            foreach($main_ip_pieces as $key=>$val) {
                $main_ip_pieces[$key] = str_pad($main_ip_pieces[$key], 4, "0", STR_PAD_LEFT);
            }

            // Create the first and last pieces that will denote the IPV6 range.
            $first = $main_ip_pieces;
            $last = $main_ip_pieces;

            // Check to see if the last IP block (part after ::) is set
            $last_piece = "";
            $size = count($main_ip_pieces);
            if (trim($last_ip_piece) != "") {
                $last_piece = str_pad($last_ip_piece, 4, "0", STR_PAD_LEFT);

                // Build the full form of the IPV6 address considering the last IP block set
                for ($i = $size; $i < 7; $i++) {
                    $first[$i] = "0000";
                    $last[$i] = "ffff";
                }
                $main_ip_pieces[7] = $last_piece;
            }
            else {
                // Build the full form of the IPV6 address
                for ($i = $size; $i < 8; $i++) {
                    $first[$i] = "0000";
                    $last[$i] = "ffff";
                }
            }

            // Rebuild the final long form IPV6 address
            $first  = ip2long6(implode(":", $first));
            $last   = ip2long6(implode(":", $last));
            $in_range = ($ip >= $first && $ip <= $last);

            return $in_range;
        }

        private function decbin32($dec)
        {
            return str_pad(
                decbin($dec),
                32,
                '0',
                STR_PAD_LEFT
            );
        }

        public function get()
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
            return \Locale::acceptFromHttp(
                isAke(
                    $_SERVER,
                    "HTTP_ACCEPT_LANGUAGE",
                    null
                )
            );
        }

        public function get()
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
    }
