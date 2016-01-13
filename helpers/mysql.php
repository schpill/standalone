<?php
    namespace Thin;
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


    class MysqlLib
    {
        public static function instance($model, $db = null, $options = [])
        {
            $db     = is_null($db) ? SITE_NAME : $db;
            $class  = __NAMESPACE__ . '\\Model\\' . ucfirst(Inflector::lower($model));

            $file   = APPLICATION_PATH . DS . 'models' . DS .
            'Mysql' . DS . ucfirst(Inflector::lower($db)) . DS . ucfirst(Inflector::lower($model)) . '.php';

            if (File::exists($file)) {
                require_once $file;

                return new $class;
            }

            if (!class_exists($class)) {
                $code = 'namespace ' . __NAMESPACE__ . '\\Model;' . "\n" .'
    class ' . ucfirst(Inflector::lower($model)) . ' extends \\Thin\\MyOrm
    {
        public $timestamps = false;
        protected $table = "' . Inflector::lower($model) . '";

        public static function boot()
        {
            static::unguard();
        }
    }';

                if (!is_dir(APPLICATION_PATH . DS . 'models' . DS . 'Mysql')) {
                    $dir = APPLICATION_PATH . DS . 'models' . DS . 'Mysql';
                    File::mkdir($dir);
                }

                if (!is_dir(APPLICATION_PATH . DS . 'models' . DS . 'Mysql' . DS . ucfirst(Inflector::lower($db)))) {
                    $dir = APPLICATION_PATH . DS . 'models' . DS . 'Mysql' . DS . ucfirst(Inflector::lower($db));
                    File::mkdir($dir);
                }

                File::put($file, '<?php' . "\n" . $code);

                require_once $file;

                return new $class;
            }
        }

        public function table($table, $db = null, $options = [])
        {
            return self::instance($table, $db, $options);
        }
    }
