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

    if (!defined('DUMP_CAPTURE')) {
        define('DUMP_CAPTURE','ce73a8aec183db86dae59bef1f7c273a61720b34');
    }

    class DumpLib
    {
        /**
         * Return DumpLib version
         *
         * @return string
         * @access public
         * @static
         */
        public static function version()
        {
            return '0.7';
        }


        /**
         * Prints a debug backtrace
         *
         * @access public
         * @static
         */
        public static function backtrace($max = 15, $capture= false)
        {
            // disabled ?
            if (!self::_debug()) {
                return false;
            }

            $dump = array();
            $filenames = array();

            foreach (debug_backtrace() as $line) {
                // Keep the 'max' in check..
                if ($max-- < 1) {
                    break;
                }
                // Get a descriptive 'name' for the function..
                $name = sprintf("%s(%s)",
                    self::trimString($line['function'], 40),
                    !empty($line['args']) ? count($line['args']) : ""
                );

                if (!empty($line['type'])) {
                    $name = sprintf("%s&nbsp;%s&nbsp;%s",
                        self::trimString($line['class'], 40),
                        $line['type'],
                        $name
                    );
                }

                $thisdump = array();
                $thisdump['file'] = $line['file'];

                if (!empty($line['file'])) {
                    $filenames[] = $line['file'];
                }

                if (!empty($line['line'])) {
                    $thisdump['line'] = $line['line'];
                }

                if (!empty($line['args'])) {
                    $thisdump['args'] = $line['args'];
                }

                $dump[$name] = $thisdump;
            }

            // get the common prefix for all the used filenames..
            $commonprefix = self::commonPrefix($filenames);

            // iterate over the results, cleaning up the filenames..
            foreach($dump as $key => $line) {
                $dump[$key]['file'] = str_replace($commonprefix, "…" . DS, $dump[$key]['file']);
            }

            // render it
            if (!$capture) {
                self::dump($dump);
            } else {
                return self::dump($dump, STORAGE_PATH);
            }
        }

        /**
         * Prints a list of all currently declared classes.
         *
         * @access public
         * @static
         */
        public static function classes()
        {
            // disabled ?
            if (!self::_debug()) {
                return false;
            }

            print "<div class=\"DumpLib-title\">\n";
            print "This is a list of all currently declared classes.";
            print "</div>\n";

            return self::dump(get_declared_classes());
        }

        /**
         * Prints a list of all currently declared interfaces (PHP5 only).
         *
         * @access public
         * @static
         */
        public static function interfaces()
        {
            // disabled
            if (!self::_debug()) {
                return false;
            }

            // render it
            print "<div class=\"DumpLib-title\">This is a list of all currently declared interfaces.</div>";

            return self::dump(get_declared_interfaces());
        }

        /**
         * Prints a list of all currently included (or required) files.
         *
         * @access public
         * @static
         */
        public static function includes()
        {
            // disabled
            if (!self::_debug()) {
                return false;
            }

            // render it
            print "<div class=\"DumpLib-title\">This is a list of all currently included (or required) files.</div>";

            return self::dump(get_included_files());
        }

        /**
         * Prints a list of all currently declared functions.
         *
         * @access public
         * @static
         */
        public static function functions()
        {
            // disabled
            if (!self::_debug()) {
                return false;
            }

            // render it
            print "<div class=\"DumpLib-title\">This is a list of all currently declared functions.</div>";

            return self::dump(get_defined_functions());
        }

        /**
         * Prints a list of all currently declared constants.
         *
         * @access public
         * @static
         */
        public static function defines()
        {
            // disabled
            if (!self::_debug()) {
                return false;
            }

            // render it
            print "<div class=\"DumpLib-title\">This is a list of all currently declared constants (defines).</div>";

            return self::dump(get_defined_constants());
        }

        /**
         * Prints a list of all currently loaded PHP extensions.
         *
         * @access public
         * @static
         */
        public static function extensions()
        {
            // disabled
            if (!self::_debug()) {
                return false;
            }

            // render it
            print "<div class=\"DumpLib-title\">This is a list of all currently loaded PHP extensions.</div>";

            return self::dump(get_loaded_extensions());
        }

        /**
         * Prints a list of all HTTP request headers.
         *
         * @access public
         * @static
         */
        public static function headers()
        {
            // disabled
            if (!self::_debug()) {
                return false;
            }

            // render it
            print "<div class=\"DumpLib-title\">This is a list of all HTTP request headers.</div>";

            return self::dump(getAllHeaders());
        }

        /**
         * Prints a list of the configuration settings read from <i>php.ini</i>
         *
         * @access public
         * @static
         */
        public static function phpini()
        {
            // disabled
            if (!self::_debug()) {
                return false;
            }

            if (!is_readable(get_cfg_var('cfg_file_path'))) {
                return false;
            }

            // render it
            print "<div class=\"DumpLib-title\">";
            print "This is a list of the configuration settings read from <code><b>" . get_cfg_var('cfg_file_path') . "</b></code>.";
            print "</div>";

            return self::dump(parse_ini_file(get_cfg_var('cfg_file_path'), true));
        }

        /**
         * Prints a list of all your configuration settings.
         *
         * @access public
         * @static
         */
        public static function conf()
        {
            // disabled
            if (!self::_debug()) {
                return false;
            }

            // render it
            print "<div class=\"DumpLib-title\">This is a list of all your configuration settings.</div>";

            return self::dump(ini_get_all());
        }

        /**
         * Prints a list of the specified directories under your <i>include_path</i> option.
         *
         * @access public
         * @static
         */
        public static function path()
        {
            // disabled
            if (!self::_debug()) {
                return false;
            }

            // render it
            print "<div class=\"DumpLib-title\"> This is a list of the specified directories under your <code><b>include_path</b></code> option.</div>";

            return self::dump(explode(PATH_SEPARATOR, ini_get('include_path')));
        }

        /**
         * Prints a list of all the values from the <i>$_REQUEST</i> array.
         *
         * @access public
         * @static
         */
        public static function request()
        {
            // disabled
            if (!self::_debug()) {
                return false;
            }

            // render it
            print "<div class=\"DumpLib-title\">This is a list of all the values from the <code><b>\$_REQUEST</b></code> array.</div>";

            return self::dump($_REQUEST);
        }

        /**
         * Prints a list of all the values from the <i>$_GET</i> array.
         *
         * @access public
         * @static
         */
        public static function get()
        {
            // disabled
            if (!self::_debug()) {
                return false;
            }

            // render it
            print "<div class=\"DumpLib-title\">This is a list of all the values from the <code><b>\$_GET</b></code> array.</div>";

            return self::dump($_GET);
        }

        /**
         * Prints a list of all the values from the <i>$_POST</i> array.
         *
         * @access public
         * @static
         */
        public static function post()
        {
            // disabled
            if (!self::_debug()) {
                return false;
            }

            // render it
            print "<div class=\"DumpLib-title\">This is a list of all the values from the <code><b>\$_POST</b></code> array.</div>";

            return self::dump($_POST);
        }

        /**
         * Prints a list of all the values from the <i>$_SERVER</i> array.
         *
         * @access public
         * @static
         */
        public static function server()
        {
            // disabled
            if (!self::_debug()) {
                return false;
            }

            // render it
            print "<div class=\"DumpLib-title\">This is a list of all the values from the <code><b>\$_SERVER</b></code> array.</div>";

            return self::dump($_SERVER);
        }

        /**
         * Prints a list of all the values from the <i>$_COOKIE</i> array.
         *
         * @access public
         * @static
         */
        public static function cookie()
        {
            // disabled
            if (!self::_debug()) {
                return false;
            }

            // render it
            print "<div class=\"DumpLib-title\">This is a list of all the values from the <code><b>\$_COOKIE</b></code> array.</div>";

            return self::dump($_COOKIE);
        }

        /**
         * Prints a list of all the values from the <i>$_ENV</i> array.
         *
         * @access public
         * @static
         */
        public static function env()
        {
            // disabled
            if (!self::_debug()) {
                return false;
            }

            // render it
            print "<div class=\"DumpLib-title\">This is a list of all the values from the <code><b>\$_ENV</b></code> array.</div>";

            return self::dump($_ENV);
        }

        /**
         * Prints a list of all the values from the <i>$_SESSION</i> array.
         *
         * @access public
         * @static
         */
        public static function session()
        {
            // disabled
            if (!self::_debug()) {
                return false;
            }

            // render it
            print "<div class=\"DumpLib-title\">This is a list of all the values from the <code><b>\$_SESSION</b></code> array.</div>";

            return self::dump($_SESSION);
        }

        /**
         * Prints a list of all the values from an INI file.
         *
         * @param string $ini_file
         *
         * @access public
         * @static
         */
        public static function ini($ini_file)
        {
            // disabled
            if (!self::_debug()) {
                return false;
            }

            // read it
            if (!$_ = @parse_ini_file($ini_file, 1)) {
                return false;
            }

            // render it
            if (realpath($ini_file)) {
                $ini_file = realpath($ini_file);
            }

            print "<div class=\"DumpLib-title\">";
            print "This is a list of all the values from the <code><b>" . $ini_file . "</b></code> INI file.</div>";

            return self::dump($_);
        }


        /**
         * Dump information about a variable
         *
         * @param mixed $data,...
         * @access public
         * @static
         */
        public static function dump($data, $capture = false)
        {
            // If we're capturing call dump() with just data and capture the output
            if ($capture === DUMP_CAPTURE) {
                ob_start();

                self::dump($data);

                $str = ob_get_clean();

                return $str;
            }

            $clearObjectRecursionProtection   = false;

            if (self::$objectRecursionProtection === null) {
                self::$objectRecursionProtection = array();
                $clearObjectRecursionProtection  = true;
            }

            // disabled
            if (!self::_debug()) {
                return false;
            }

            // more arguments
            if (func_num_args() > 1) {
                $_ = func_get_args();
                foreach ($_ as $d) {
                    self::dump($d);
                }

                return;
            }

            // find caller
            $_ = debug_backtrace();

            while ($d = array_pop($_)) {
                $callback = self::$lineNumberTestCallback;
                $function = strToLower($d['function']);

                if (in_array($function, array("DumpLib","k","kd")) || (strToLower(@$d['class']) == 'DumpLib') || (is_callable($callback) && $callback($d))) {
                    break;
                }
            }

            $showVersion  = self::_config('display', 'show_version', TRUE);
            $showCallInfo = self::_config('display', 'show_call_info', TRUE);
            $DumpLibUrl     = 'https://github.com/oodle/DumpLib';

            //////////////////////
            // Start HTML header//
            //////////////////////
            print "<div class=\"DumpLib-root\">\n";
            print "\t<ul class=\"DumpLib-node DumpLib-first\">\n";

            // The actual item itself
            print self::_dump($data);

            if ($showVersion || $showCallInfo) {
                print "\t\t<li class=\"DumpLib-footnote\" onDblClick=\"toggle_expand_all();\">\n";

                if ($showCallInfo && isset($d['file']) && $d['file']) {
                    print "<span class=\"DumpLib-call\" style=\"white-space:nowrap;\">";
                    print "Called from <strong><code>" . $d['file'] . "</code></strong>, ";
                    print "line <strong><code>" . $d['line'] . "</code></strong></span>";
                }

                if ($showVersion) {
                    $version = self::version();
                    print "<span class=\"DumpLib-version\" style=\"white-space:nowrap;\">\n";
                    print "<strong class=\"DumpLib-version-number\">DumpLib version $version</strong> | <a href=\"$DumpLibUrl\" target=\"_blank\">$DumpLibUrl</a>\n";
                    print "</span>\n";
                }

                print "</li>";
            }

            print "</ul></div>\n";
            print "<!-- DumpLib - HTML -->\n\n";

            // Output the CSS and JavaScript AFTER the HTML
            self::_css();
            ////////////////////
            // End HTML header//
            ////////////////////

            // flee the hive
            $_recursion_marker = self::_marker();

            if ($hive =& self::_hive($dummy)) {
                foreach ($hive as $i => $bee) {
                    if (is_object($bee)) {
                        if (($hash = spl_object_hash($bee)) && isset(self::$objectRecursionProtection[$hash])) {
                            unset(self::$objectRecursionProtection[$hash]);
                        }
                    } elseif (isset($hive[$i]->$_recursion_marker)) {
                        unset($hive[$i][$_recursion_marker]);
                    }
                }
            }

            if ($clearObjectRecursionProtection) {
                self::$objectRecursionProtection = NULL;
            }

            // End of dump()
        }


        /**
         * Configuration array.
         */
        private static $_config = array();

        /**
         * Returns values from DumpLib's configuration
         *
         * @param string $group
         * @param string $name
         * @param mixed $fallback
         * @return mixed
         *
         * @access private
         * @static
         */
        private static function _config($group, $name, $fallback=null)
        {
            $DumpLib_ini = STORAGE_PATH . 'DumpLib.ini';

            // The config isn't loaded yet
            if (empty(self::$_config) && is_readable($DumpLib_ini)) {
                self::$_config = (array) parse_ini_file($DumpLib_ini, true);
            }

            // exists
            if (isset(self::$_config[$group][$name])) {
                return self::$_config[$group][$name];
            } else {
                return $fallback;
            }
        }

        public static function setConfig($config)
        {
            self::$_config = $config;
        }

        public static function setLineNumberTestCallback($callback)
        {
            self::$lineNumberTestCallback = $callback;
        }

        private static $lineNumberTestCallback = null;


        /**
         * Cascade configuration array
         *
         * By default, all nodes are collapsed.
         */
        private static $_cascade = null;

        /**
         * Set a cascade configuration array.
         *
         * Each value in the array is the maximum number of entries that node can
         * have before it is being collapsed. The last value is repeated for all
         * further levels.
         *
         * Example:
         * array(10,5,0) - Nodes from the first level are expanded if they have less
         *                 than or equal to 10 child nodes. Nodes from the second level are ex-
         *                 panded if they have less or equal to 5 nodes and all lower levels
         *                 are collapsed.
         *
         * Note:
         *   To reset, simply call this function with no arguments.
         *
         * @param array $cascade Cascading information
         * @access public
         * @static
         */
        public static function cascade(array $cascade = null)
        {
            self::$_cascade = $cascade;
        }

        /**
         * This allows you to uncollapse items programattically. Example:
         *
         * \self::$expand_all = 1;
         * \self::dump($my_array);
         */
        public static $expand_all = 0;

        /**
         * Determines if a given node will be collapsed or not.
         */
        private static function _isCollapsed($level, $childCount)
        {
            if (self::$expand_all) {
                return false;
            }

            $cascade = self::$_cascade;

            if ($cascade == null) {
                $cascade = self::_config('display', 'cascade', array());
            }

            if (isset($cascade[$level])) {
                return $childCount >= $cascade[$level];
            } else {
                return true;
            }
        }


        /**
         * Calculate the relative path of a given absolute URL
         *
         * @return string
         * @access public
         * @static
         */
        public static function calculate_relative_path($file, $return_dir = 0)
        {
            // We find the document root of the webserver
            $doc_root = $_SERVER['DOCUMENT_ROOT'];

            // Remove the document root, from the FULL absolute path of the
            // file we're looking for
            $ret = "/" . str_replace($doc_root, "", $file, $ok);

            if (!$ok) {
                return false;
            }

            // If they want the path to the dir, only return the dir part
            if ($return_dir) {
                $ret = dirname($ret) . "/";
            }

            $ret = preg_replace("|//|","/",$ret);

            return $ret;
        }

        /**
         * Print the skin (CSS)
         *
         * @return boolean
         * @access private
         * @static
         */
        private static function _css()
        {
            static $_css = false;

            // already set ?
            if ($_css) {
                return true;
            }

            $css = '';

            // custom selected skin
            $css_file = STORAGE_PATH . "assets/skin.min.css";

            if (is_readable($css_file)) {
                $css = join(file($css_file));
            } else {
                $css = "/* Missing CSS file DumpLib.min.css */\n";
            }


            // print
            if ($_css = $css != '') {

                // the CSS
                print "<!-- DumpLib - CSS begin -->\n";
                print "<style type=\"text/css\">\n";
                print trim($css) . "\n";
                print "</style>\n";
                print "<!-- DumpLib - CSS end->\n";

                // the JS
                print "<!-- DumpLib - JavaScript start -->\n<script type=\"text/javascript\">\n";

                $js_file = STORAGE_PATH . "assets/DumpLib.min.js";

                if (is_readable($js_file)) {
                    $js_text = join(file($js_file));
                } else {
                    $js_text = "// Missing JS file DumpLib.min.js\n";
                }

                print "$js_text</script>\n";
                print "<!-- DumpLib - JavaScript end -->\n";
            }

            return $_css;
        }


        /**
         * Enable DumpLib
         *
         * @return boolean
         * @access public
         * @static
         */
        public static function enable()
        {
            return true === self::_debug(true);
        }

        /**
         * Disable DumpLib
         *
         * @return boolean
         * @access public
         * @static
         */
        public static function disable()
        {
            return false === self::_debug(false);
        }

        /**
         * Get\Set DumpLib state: whether it is enabled or disabled
         *
         * @param boolean $state
         * @return boolean
         * @access private
         * @static
         */
        private static function _debug($state = null)
        {
            static $_ = true;

            // set
            if (isset($state)) {
                $_ = (boolean) $state;
            }

            // get
            return $_;
        }

        private static function sanitize_name($name)
        {
            // Check if the key has whitespace in it, if so show it and add an icon explanation
            $has_white_space = preg_match("/\s/",$name);

            if ($has_white_space) {
                // Convert the white space to unicode underbars to visualize it
                $name  = preg_replace("/\s/","&#9251;",$name);
            }

            return $name;
        }


        /**
         * Dump information about a variable
         *
         * @param mixed $data
         * @param string $name
         * @access private
         * @static
         */
        private static function _dump(&$data, $name = '…')
        {
            // Highlight elements that have a space in their name.
            // Spaces are hard to see in the HTML and are hard to troubleshoot
            $name = self::sanitize_name($name);

            // object
            if (is_object($data)) {
                return self::_object($data, $name);
            }

            // array
            if (is_array($data)) {
                return self::_array($data, $name);
            }

            // resource
            if (is_resource($data)) {
                return self::_resource($data, $name);
            }

            // scalar
            if (is_string($data)) {
                return self::_string($data, $name);
            }

            // float
            if (is_float($data)) {
                return self::_float($data, $name);
            }

            // integer
            if (is_integer($data)) {
                return self::_integer($data, $name);
            }

            // boolean
            if (is_bool($data)) {
                return self::_boolean($data, $name);
            }

            // null
            if (is_null($data)) {
                return self::_null($name);
            }
        }


        /**
         * Render a dump for a NULL value
         *
         * @param string $name
         * @return string
         * @access private
         * @static
         */
        private static function _null($name)
        {

            $html = '<li class="DumpLib-child">
                <div class="DumpLib-element" onMouseOver="DumpLib.over(this);" onMouseOut="DumpLib.out(this);">
                <a class="DumpLib-name">%s</a> %s <strong class="DumpLib-type DumpLib-null">NULL</strong>
                </div></li>';

            $html = sprintf($html, $name, self::get_separator() );

            echo $html;

        }


        /**
         * Return the marked used to stain arrays
         * and objects in order to detect recursions
         *
         * @return string
         * @access private
         * @static
         */
        private static function _marker()
        {
            static $_recursion_marker;

            if (!isset($_recursion_marker)) {
                $_recursion_marker = uniqid('DumpLib');
            }

            return $_recursion_marker;
        }


        /**
         * Adds a variable to the hive of arrays and objects which
         * are tracked for whether they have recursive entries
         *
         * @param mixed &$bee either array or object, not a scalar value
         * @return array all the bees
         *
         * @access private
         * @static
         */
        private static $objectRecursionProtection = NULL;

        private static function &_hive(&$bee)
        {
            static $_ = array();

            // new bee
            if (!is_null($bee)) {

                // stain it
                $_recursion_marker = self::_marker();
                if (is_object($bee)) {
                    $hash = spl_object_hash($bee);
                    if ($hash && isset(self::$objectRecursionProtection[$hash])) {
                        self::$objectRecursionProtection[$hash]++;
                    } elseif ($hash) {
                        self::$objectRecursionProtection[$hash] = 1;
                    }
                } else {
                    if (isset($bee[$_recursion_marker])) {
                        $bee[$_recursion_marker]++;
                    } else {
                        $bee[$_recursion_marker] = 1;
                    }
                }

                $_[0][] =& $bee;
            }

            // return all bees
            return $_[0];
        }


        /**
         * Level of recursion.
         */
        private static $_level = 0;

        /**
         * Render a dump for the properties of an array or objeect
         *
         * @param mixed &$data
         * @access private
         * @static
         */
        private static function _vars(&$data)
        {
            $_is_object = is_object($data);

            // test for references in order to
            // prevent endless recursion loops
            $_recursion_marker = self::_marker();

            if ($_is_object) {
                if (($hash = spl_object_hash($data)) && isset(self::$objectRecursionProtection[$hash])) {
                    $_r = self::$objectRecursionProtection[$hash];
                } else {
                    $_r = NULL;
                }
            } else {
                $_r = isset($data[$_recursion_marker]) ? $data[$_recursion_marker] : null;
            }

            // recursion detected
            if ($_r > 0) {
                return self::_recursion();
            }

            // stain it
            self::_hive($data);

            // render it
            $collapsed = self::_isCollapsed(self::$_level, count($data)-1);
            if ($collapsed) {
                $collapse_style = 'style="display: none;"';
            } else {
                $collapse_style = '';
            }

            print "<div class=\"DumpLib-nest\" $collapse_style>";
            print "<ul class=\"DumpLib-node\">";

            // we're descending one level deeper
            self::$_level++;

            // Object?? - use Reflection
            if ($_is_object) {
                $reflection = new ReflectionObject($data);
                $properties = $reflection->getProperties();

                foreach ($properties as $property) {
                    $prefix = null;
                    $setAccessible = false;

                    if ($property->isPrivate()) {
                        $setAccessible = true;
                        $prefix = 'private';
                    } elseif ($property->isProtected()) {
                        $setAccessible = true;
                        $prefix = 'protected';
                    } elseif ($property->isPublic()) {
                        $prefix = 'public';
                    }

                    $name = $property->getName();

                    if ($setAccessible) {
                        $property->setAccessible(true);
                    }

                    $value = $property->getValue($data);

                    self::_dump($value, "<span>$prefix</span>&nbsp;$name");

                    if ($setAccessible) {
                        $property->setAccessible(false);
                    }
                }
            } else {
                // keys
                $keys = array_keys($data);

                // iterate
                foreach ($keys as $k) {
                    // skip marker
                    if ($k === $_recursion_marker) {
                        continue;
                    }

                    // get real value
                    $v =& $data[$k];

                    self::_dump($v,$k);
                }
            }

            print "</ul>\n</div>";

            self::$_level--;
        }


        /**
         * Render a block that detected recursion
         *
         * @access private
         * @static
         */
        private static function _recursion()
        {

            $html = '<div class="DumpLib-nest" style="display:none;">
                <ul class="DumpLib-node">
                    <li class="DumpLib-child">
                        <div class="DumpLib-element" onMouseOver="DumpLib.over(this);" onMouseOut="DumpLib.out(this);">
                            <a class="DumpLib-name">&#8734;</a>
                            (<em class="DumpLib-type">Recursion</em>)
                        </div>

                    </li>
                </ul>';

            echo $html;
        }

        private static function is_assoc($var)
        {
            return is_array($var) && array_diff_key($var, array_keys(array_keys($var)));
        }


        /**
         * Render a dump for an array
         *
         * @param mixed $data
         * @param string $name
         * @access private
         * @static
         */
        private static function _array($data, $name)
        {
            $config_sort = self::_config('display','sort_arrays',true);

            // If the sort is enabled in the config (default = yes) and the array is assoc (non-numeric)
            if (sizeof($data) > 1 && $config_sort && self::is_assoc($data)) {
                // Copy the array to a temp variable and sort it
                $new = $data;
                ksort($new);

                // If the sorted array is the same as the old don't sort it
                if ($new === $data) {
                    $sort = 0;
                } else {
                    $data = $new;
                    $sort = 1;
                }
            } else {
                $sort = 0;
            }

            $childCount = count($data);
            $collapsed = self::_isCollapsed(self::$_level, count($data));

            // Setup the CSS classes depending on how many children there are
            if ($childCount > 0 && $collapsed) {
                $elementClasses = ' DumpLib-expand';
            } elseif ($childCount > 0) {
                $elementClasses = ' DumpLib-expand DumpLib-opened';
            } else {
                $elementClasses = '';
            }

            print "<li class=\"DumpLib-child\">";
            print "<div class=\"DumpLib-element $elementClasses\"";

            // If there is more than one, make a dropdown
            if (count($data) > 0) {
                print "onClick=\"DumpLib.toggle(this);\"";
            }

            print "onMouseOver=\"DumpLib.over(this);\" onMouseOut=\"DumpLib.out(this);\">";
            print "<a class=\"DumpLib-name\">$name</a> <em class=\"DumpLib-type\">arr(<strong class=\"DumpLib-array-length\">";
            print count($data) . "</strong>)</em>";

            if (count($data)>0) {
                print " &hellip;";
            }

            if ($sort) {
                $title = "Array has been sorted prior to display. This is configurable in DumpLib.ini.";
                print " - <span title=\"$title\"><strong class=\"DumpLib-sorted\">Sorted</strong></span>";
            }

            // callback
            if (is_callable($data)) {
                $_ = array_values($data);
                print "<span class=\"DumpLib-callback\"> |";
                print " (<em class=\"DumpLib-type\">Callback</em>) <strong class=\"DumpLib-string\">";

                if (!is_object($_[0])) {
                    echo htmlSpecialChars($_[0]);
                } else {
                    echo htmlSpecialChars(get_class($_[0])) . "::";
                }

                echo htmlSpecialChars($_[1]) . "()</strong></span>";
            }

            print "</div>";

            if (count($data)) {
                self::_vars($data);
            }

            print "</li>";
        }


        /**
         * Render a dump for an object
         *
         * @param mixed $data
         * @param string $name
         * @access private
         * @static
         */
        private static function _object(&$data, $name)
        {
            $reflection = new ReflectionObject($data);

            $childCount = count($reflection->getProperties());

            $collapsed = self::_isCollapsed(self::$_level, $childCount);

            $class = get_class($data);
            $dont_traverse = self::_config('dont_traverse', 'objects', array());

            // Traverse deeper if we're on level 0, or otherwise if the current class
            // is not in the dont_traverse config setting
            $traverse_this = ((self::$_level == 0) || !in_array($class, $dont_traverse));

            // Setup the CSS classes depending on how many children there are
            if ($childCount > 0 && $collapsed ) {
                $elementClasses = ' DumpLib-expand';
            } elseif ($childCount > 0 && $traverse_this ) {
                $elementClasses = ' DumpLib-expand DumpLib-opened';
            } else {
                $elementClasses = '';
            }

            $output = sprintf('<li class="DumpLib-child"> <div class="DumpLib-element%s"', $elementClasses);
            if (count($data) > 0) {
                $output .= 'onClick="DumpLib.toggle(this);"';
            }
            $output .= 'onMouseOver="DumpLib.over(this);" onMouseOut="DumpLib.out(this);">';

            $addendum = '';
            if ($childCount == 0) {
                $addendum = ' (empty)';
            } else if (!$traverse_this) {
                $addendum = ' (skipped)';
            }

            $output .= sprintf('<a class="DumpLib-name"></a> <em class="DumpLib-type">obj</em> ', $name);
            $output .= sprintf('%s <strong class="DumpLib-class">%s</strong>%s</div>',
                self::get_separator(),
                $class,
                $addendum
            );

            echo $output;

            if ($childCount && $traverse_this) {
                self::_vars($data);
            }

            echo '</li>';
        }


        /**
         * Render a dump for a resource
         *
         * @param mixed $data
         * @param string $name
         * @access private
         * @static
         */
        private static function _resource($data, $name)
        {

            $html = '<li class="DumpLib-child">
                <div class="DumpLib-element" onMouseOver="DumpLib.over(this);" onMouseOut="DumpLib.out(this);">
                <a class="DumpLib-name">%s</a> <em class="DumpLib-type">res</em>
                %s<strong class="DumpLib-resource">%s</strong>
                </div></li>';

            $html = sprintf($html, $name, self::get_separator(), get_resource_type($data));

            echo $html;

        }


        /**
         * Render a dump for a boolean value
         *
         * @param mixed $data
         * @param string $name
         * @access private
         * @static
         */
        private static function _boolean($data, $name)
        {
            if ($data == false) {
                $value = "FALSE";
            } elseif ($data == true) {
                $value = "TRUE";
            }

            $html = '<li class="DumpLib-child">
                <div class="DumpLib-element" onMouseOver="DumpLib.over(this);" onMouseOut="DumpLib.out(this);">
                <a class="DumpLib-name">%s</a> <em class="DumpLib-type">bool</em>
                %s<strong class="DumpLib-boolean">%s</strong>
                </div></li>';

            $html = sprintf($html, $name, self::get_separator(), $value);

            echo $html;

        }


        /**
         * Render a dump for a integer value
         *
         * @param mixed $data
         * @param string $name
         * @access private
         * @static
         */
        private static function _integer($data, $name)
        {
            print "<li class=\"DumpLib-child\">";
            print "<div class=\"DumpLib-element\" onMouseOver=\"DumpLib.over(this);\" onMouseOut=\"DumpLib.out(this);\">";
            print "<a class=\"DumpLib-name\">$name</a> <em class=\"DumpLib-type\">int</em> ";
            print self::get_separator() . " <strong class=\"DumpLib-integer\">$data</strong>";

            $ut = self::is_datetime($name, $data);

            if ($ut) {
                print " ~ <strong class=\"DumpLib-datetime\">$ut</strong>";
            }

            print "</div></li>";
        }


        /**
         * Render a dump for a float value
         *
         * @param mixed $data
         * @param string $name
         * @access private
         * @static
         */
        private static function _float($data, $name)
        {
            print "<li class=\"DumpLib-child\">";
            print "<div class=\"DumpLib-element\" onMouseOver=\"DumpLib.over(this);\" onMouseOut=\"DumpLib.out(this);\">";
            print "<a class=\"DumpLib-name\">$name</a> <em class=\"DumpLib-type\">float</em> ";
            print self::get_separator() . " <strong class=\"DumpLib-float\">$data</strong>";

            $ut = self::is_datetime($name,$data);

            if ($ut) {
                print " ~ <strong class=\"DumpLib-datetime\">$ut</strong>";
            }

            print "</div></li>";
        }

        public static function get_icon($name,$title)
        {
            $path = dirname(__FILE__) . "/icons/$name.png";
            $rel  = self::calculate_relative_path($path);

            $ret = "<img style=\"padding: 0 2px 0 2px\" src=\"$rel\" title=\"$title\" alt=\"name\" />";

            return $ret;
        }

        /**
         * Get the separator to use for separating 'key' / 'value' pairs. Defaults to ' => '
         *
         * @return string
         */
        public static function get_separator()
        {
            $separator = self::_config('display', 'separator', " =&gt; ");

            return $separator;
        }

        private static function is_datetime($name,$value)
        {
            // If the name contains date or time, and the value looks like a unixtime
            if (preg_match("/date|time/i",$name) && ($value > 10000000 && $value < 4000000000)) {
                $ret = date("r",$value);

                return $ret;
            }

            return false;
        }


        /**
         * Render a dump for a string value
         *
         * @param mixed $data
         * @param string $name
         * @access private
         * @static
         */
        private static function _string($data, $name)
        {
            $collapsed = self::_isCollapsed(self::$_level, 1);

            if ($collapsed) {
                $collapse_style = 'style="display: none;"';
            } else {
                $collapse_style = '';
            }

            // extra
            $_extra = false;
            $_ = $data;

            // Get the truncate length from the config, or default to 100
            $truncate_length = self::_config('display', 'truncate_length', 100);

            if (strLen($data) > $truncate_length ) {
                $_ = substr($data, 0, $truncate_length - 1);
                $_extra = true;
            }

            $expand_class = '';

            if ($_extra) {
                $expand_class = 'DumpLib-expand';
            }

            print "<li class=\"DumpLib-child\">";
            print "<div class=\"DumpLib-element $expand_class\" ";

            if ($_extra) {
                print " onClick=\"DumpLib.toggle(this);\" ";
            }

            print "onMouseOver=\"DumpLib.over(this);\" onMouseOut=\"DumpLib.out(this);\">\n";

            print "<a class=\"DumpLib-name\">$name</a> ";
            print "<em class=\"DumpLib-type\">str(<strong class=\"DumpLib-string-length\">" . strlen($data) . "</strong>)</em> ";
            print self::get_separator() . " <strong class=\"DumpLib-string\">" . htmlspecialchars($_);

            // This has to go AFTER the htmlspecialchars
            if ($_extra) {
                print "&hellip;";
            }

            print "</strong>";

            $ut = self::is_datetime($name, $data);

            if ($ut) {
                print " ~ <strong class=\"DumpLib-datetime\">$ut</strong>";
            }

            // callback
            if (is_callable($data)) {
                print "<span class=\"DumpLib-callback\"> | ";
                print "(<em class=\"DumpLib-type\">Callback</em>) <strong class=\"DumpLib-string\">" . htmlSpecialChars($_) . "()</strong></span>";
            }

            print "</div>";

            if ($_extra) {
                print "<div class=\"DumpLib-nest\" $collapse_style>";
                print "<ul class=\"DumpLib-node\">";
                print "<li class=\"DumpLib-child\"> <div class=\"DumpLib-preview\">" . htmlSpecialChars($data) . "</div></li>";
                print "</ul></div>";
            }

            print "</li>";
        }

        /**
         * Trims the given string to a particular length. Does plain trimming.
         *
         * @param string $str Input string
         * @param int $length Desired length
         * @return string
         */
        private static function trimString($str, $length = 40)
        {
            if (function_exists('mb_strwidth')) {
                $strlen =  mb_strwidth($str, 'UTF-8');
            } else {
                $strlen =  strlen($str);
            }

            if ($strlen > $length) {
                $str = mb_substr($str, 0, $length, "UTF-8");
                $str .= '…';
            }

            return $str;
        }

        /**
         * Return length of longest common prefix in an array of strings.
         *
         * @see http://stackoverflow.com/questions/1336207/finding-common-prefix-of-array-of-strings
         * @param array $array
         * @return string
         */
        private static function commonPrefix($array) {
            if(count($array) < 2) {
                return strlen($array[0]); // 1 element: trivial case
            }

            $len = max(array_map('strlen', $array)); // initial upper limit: max length of all strings.
            $prevval = reset($array);

            while(($newval = next($array)) !== FALSE) {
                for($j = 0 ; $j < $len ; $j += 1) {
                    if($newval[$j] != $prevval[$j]) {
                        $len = $j;
                    }
                }

                $prevval = $newval;
            }

            return substr($array[0], 0, $len);
        }

    }
