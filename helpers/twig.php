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

    use Twig_Environment;
    use Twig_Extension_Debug;
    use Twig_Loader_Chain;
    use Twig_Loader_Filesystem;
    use Twig_Loader_Array;

    class TwigLib
    {
        /**
         * @var \Thin\Container
         */
        public $app;

        /**
         * @var \Thin\Config
         */
        public $config;

        /**
         * @var \Twig_Environment
         */
        public $environment;

        /**
         * Constructor
         *
         * @param \Thin\AppLib $app
         */
        public function __construct(AppLib $app)
        {
            $this->app = $app;
            $this->config = $app['config'];
        }

        public function init()
        {
            $loader = $this->getTwigFilesystemLoader();
            $this->environment = new Twig_Environment($loader, [
                'debug' => $this->config->get('twig.debug'),
                'cache' => $this->config->get('twig.cache')
            ]);

            if (!$this->config->isEmpty('twig.debug')) {
                $this->environment->addExtension(new Twig_Extension_Debug());
            }

            $this->environment->addExtension(new Twig\HerbieExtension($this->app));
            $this->addTwigPlugins();
        }

        /**
         * @param string $name
         * @param array $context
         * @return string
         */
        public function render($name, array $context = [])
        {
            $context = array_merge($context, [
                'route'     => $this->app->getRoute(),
                'baseUrl'   => $this->app['request']->getBasePath(),
                'theme'     => $this->app['config']->get('theme')
            ]);
            return $this->environment->render($name, $context);
        }

        /**
         * @param string $string
         * @return string
         */
        public function renderString($string)
        {
            // no rendering if empty
            if (empty($string)) {
                return $string;
            }

            // see Twig\Extensions\Twig_Extension_StringLoader
            $name = '__twig_string__';
            // get current loader
            $loader = $this->environment->getLoader();
            // set loader chain with new array loader

            $this->environment->setLoader(new Twig_Loader_Chain(array(
                new Twig_Loader_Array(array($name => $string)),
                $loader
            )));

            // render string
            $rendered = $this->environment->render($name);
            // reset current loader
            $this->environment->setLoader($loader);

            return $rendered;
        }


        /**
         * @return void
         */
        public function addTwigPlugins()
        {
            if ($this->config->isEmpty('twig.extend')) {
                return;
            }

            // Functions
            $dir = $this->config->get('twig.extend.functions');

            foreach ($this->readPhpFiles($dir) as $file) {
                $included = $this->includePhpFile($file);
                $this->environment->addFunction($included);
            }

            // Filters
            $dir = $this->config->get('twig.extend.filters');

            foreach ($this->readPhpFiles($dir) as $file) {
                $included = $this->includePhpFile($file);
                $this->environment->addFilter($included);
            }

            // Tests
            $dir = $this->config->get('twig.extend.tests');

            foreach ($this->readPhpFiles($dir) as $file) {
                $included = $this->includePhpFile($file);
                $this->environment->addTest($included);
            }
        }

        /**
         * @return Twig_Loader_Filesystem
         */
        private function getTwigFilesystemLoader()
        {
            $paths = [];

            if ($this->config->isEmpty('theme')) {
                $paths[] = $this->config->get('layouts.path');
            } elseif ($this->config->get('theme') == 'default') {
                $paths[] = $this->config->get('layouts.path') . '/default';
            } else {
                $paths[] = $this->config->get('layouts.path') . '/' . $this->config->get('theme');
                $paths[] = $this->config->get('layouts.path') . '/default';
            }

            $paths[] = __DIR__ . '/layouts'; // Fallback

            $loader = new Twig_Loader_Filesystem($paths);

            // namespaces
            $namespaces = [
                'plugin' => $this->config->get('plugins.path'),
                'page' => $this->config->get('pages.path'),
                'post' => $this->config->get('posts.path'),
                'site' => $this->config->get('site.path'),
                'widget' => __DIR__ . '/Twig/widgets'
            ];

            foreach ($namespaces as $namespace => $path) {
                if (is_readable($path)) {
                    $loader->addPath($path, $namespace);
                }
            }

            return $loader;
        }

        /**
         * @param string $file
         * @return string
         */
        private function includePhpFile($file)
        {
            $app = $this->app; // Global $app var used by plugins

            return include($file);
        }

        /**
         * @param string $dir
         * @return array
         */
        private function readPhpFiles($dir)
        {
            $dir = rtrim($dir, '/');

            if (empty($dir) || !is_readable($dir)) {
                return [];
            }

            $pattern = $dir . '/*.php';

            return glob($pattern);
        }
    }
