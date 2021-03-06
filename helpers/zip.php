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
     * Usage:
     *
     *   ZipLib::make('file.zip', '/some/path/*.php');
     *
     *   ZipLib::make('file.zip', function($zip) {
     *
     *       // Add all PHP files and directories
     *       $zip->add('/some/path/*.php');
     *
     *       // Do not include subdirectories, one level only
     *       $zip->add('/non/recursive/*', ['recursive' => false]);
     *
     *       // Add multiple paths
     *       $zip->add([
     *           '/collection/of/paths/*',
     *           '/a/single/file.php'
     *       ]);
     *
     *       // Add all INI files to a zip folder "config"
     *       $zip->folder('/config', '/path/to/config/*.ini');
     *
     *       // Add multiple paths to a zip folder "images"
     *       $zip->folder('/images', function($zip) {
     *           $zip->add('/my/gifs/*.gif', );
     *           $zip->add('/photo/reel/*.{png,jpg}', );
     *       });
     *
     *       // Remove these files/folders from the zip
     *       $zip->remove([
     *           '.htaccess',
     *           'config.php',
     *           'some/folder'
     *       ]);
     *
     *   });
     *
     *   ZipLib::extract('file.zip', '/destination/path');
     *
     */

    use ZipArchive;

    class ZipLib extends ZipArchive
    {

        /**
         * @var string Folder prefix
         */
        protected $folderPrefix = '';

        /**
         * Extract an existing zip file.
         * @param  string $source Path for the existing zip
         * @param  string $destination Path to extract the zip files
         * @param  array  $options
         * @return bool
         */
        public static function extract($source, $destination, $options = [])
        {
            extract(array_merge([
                'mask' => 0777
            ], $options));

            if (!file_exists($destination))
                mkdir($destination, $mask, true);

            $zip = new ZipArchive;

            if ($zip->open($source) === true) {
                $zip->extractTo($destination);
                $zip->close();
                return true;
            }

            return false;
        }

        /**
         * Creates a new empty zip file.
         * @param  string $destination Path for the new zip
         * @param  mixed  $source
         * @return self
         */
        public static function make($destination, $source)
        {
            $zip = new self;
            $zip->open($destination, ZipArchive::OVERWRITE);

            if (is_string($source)) $zip->add($source);

            elseif (is_callable($source)) $source($zip);

            elseif (is_array($source)) {
                foreach ($source as $_source) {
                    $zip->add($_source);
                }
            }

            $zip->close();

            return $zip;
        }

        /**
         * Includes a source to the Zip
         * @param mixed $source
         * @param array $options
         */
        public function add($source, $options = [])
        {
            // A directory has been supplied, convert it to a useful glob
            if (is_dir($source)) $source = implode('/', [dirname($source), basename($source), '*']);

            extract(array_merge([
                'recursive' => true,
                'basedir' => dirname($source),
                'baseglob' => basename($source)
            ], $options));

            if (is_file($source)) {
                $files = [$source];
                $recursive = false;
            } else {
                $files = glob($source, GLOB_BRACE);
                $folders = glob(dirname($source) . '/*', GLOB_ONLYDIR);
            }

            foreach ($files as $file) {
                if (!is_file($file)) continue;

                $localpath = $this->removePathPrefix($basedir.'/', dirname($file).'/');
                $localfile = $this->folderPrefix . $localpath . basename($file);
                $this->addFile($file, $localfile);
            }

            if (!$recursive) return $this;

            foreach ($folders as $folder) {
                if (!is_dir($folder)) continue;

                $localpath = $this->folderPrefix . $this->removePathPrefix($basedir.'/', $folder.'/');
                $this->addEmptyDir($localpath);
                $this->add($folder.'/'.$baseglob, array_merge($options, ['basedir' => $basedir]));
            }

            return $this;
        }

        /**
         * Creates a new folder inside the Zip and adds source files (optional)
         * @param  string $name Folder name
         * @param  mixed  $source
         * @return self
         */
        public function folder($name, $source = null)
        {
            $prefix = $this->folderPrefix;
            $this->addEmptyDir($prefix . $name);
            if ($source === null) return $this;

            $this->folderPrefix = $prefix . $name . '/';

            if (is_string($source)) $this->add($source);

            elseif (is_callable($source)) $source($this);

            elseif (is_array($source)) {
                foreach ($source as $_source) {
                    $this->add($_source);
                }
            }

            $this->folderPrefix = $prefix;

            return $this;
        }

        /**
         * Removes a file or folder from the zip collection.
         * Does not support wildcards.
         * @param  string $source
         * @return self
         */
        public function remove($source)
        {
            if (is_array($source)) {
                foreach ($source as $_source) $this->remove($_source);
            }

            if (!is_string($source)) return $this;

            if (substr($source, 0, 1) == '/') $source = substr($source, 1);

            for ($i = 0; $i < $this->numFiles; $i++) {
                $stats = $this->statIndex($i);
                if (substr($stats['name'], 0, strlen($source)) == $source) $this->deleteIndex($i);
            }

            return $this;
        }

        /**
         * Removes a prefix from a path.
         * @param  string $prefix /var/sites/
         * @param  string $path /var/sites/moo/cow/
         * @return string moo/cow/
         */
        protected function removePathPrefix($prefix, $path)
        {
            return (strpos($path, $prefix) === 0)
            ? substr($path, strlen($prefix))
            : $path;
        }
    }
