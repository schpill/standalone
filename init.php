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

    umask(0000);

    defined('MB_STRING')    || define('MB_STRING', (int) function_exists('mb_get_info'));
    defined('DS')           || define('DS', DIRECTORY_SEPARATOR);
    defined('PS')           || define('PS', PATH_SEPARATOR);

    require_once __DIR__ . '/../../schpill/thin/src/Helper.php';
    require_once __DIR__ . '/../../autoload.php';
    require_once __DIR__ . '/../../schpill/thin/src/Loader.php';
    require_once __DIR__ . DS . 'context.php';
