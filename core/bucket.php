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

    namespace Thin;

    class BucketCore
    {
        private $args;
        private $dir;
        private $bucket;

        public function __construct($bucket = null, $args = [])
        {
            $this->bucket   = !$bucket ? SITE_NAME : Inflector::urlize($bucket, '');
            $this->args     = $args;

            if ($this->bucket == 'download') {
                throw new Exception("The bucket name 'download' is not possible. Please chosse another one => ex: 'downloads'.");
            }

            $dir = session_save_path() . DS . 'bucket';

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $this->dir = $dir . DS . $this->bucket;

            if (!is_dir($this->dir)) {
                File::mkdir($this->dir);
            }

            $this->dir .= '/';

            $this->check();
        }

        public function __get($key)
        {
            if (isset($this->$key)) {
                return $this->$key;
            }

            return isAke($this->args, $key, null);
        }

        public function __isset($key)
        {
            if (isset($this->$key)) {
                return true;
            }

            $val = isAke($this->args, $key, null);

            return strlen($val) > 0 ? true : false;
        }

        public function __set($key, $value)
        {
            $this->args[$key] = $value;

            return $this;
        }

        public function check()
        {
            $expires = glob($this->dir . "expire::*");

            if (!empty($expires)) {
                foreach ($expires as $expire) {
                    $tab = explode("::", $expire);
                    $time = end($tab);

                    if ($time > 0 && $time < time()) {
                        File::delete($expire);
                    }
                }
            }
        }

        public function upload($args = [])
        {
            $data = isAke($args, 'data', null);

            if (!$data) {
                $this->forbidden('no data');
            }

            $name = isAke($args, 'name', null);

            if (!$name) {
                $this->forbidden('no name');
            }

            $name = Utils::UUID() . '-' . $name;

            if (!is_dir($this->dir . 'upload')) {
                File::mkdir($this->dir . 'upload');
            }

            $fileData = $this->dir . 'upload/' . $name;

            $url = core('request')->setUrl('/bucket/' . $this->bucket . '/' . $name);

            $this->putFile($fileData, $data);

            $this->success($url);
        }

        public function file($field)
        {
            if (Arrays::exists($field, $_FILES)) {
                $fileupload         = $_FILES[$field]['tmp_name'];
                $fileuploadName     = $_FILES[$field]['name'];

                if (strlen($fileuploadName)) {
                    $data = File::read($fileupload);

                    if (!strlen($data)) {
                        return null;
                    }

                    return $this->upload([
                        'data' => $data,
                        'name' => $fileuploadName
                    ]);
                }
            }

            return null;
        }

        public function set()
        {
            $key = isAke($this->args, 'key', null);

            if (!strlen($key)) {
                $this->forbidden('no key');
            }

            $value = isAke($this->args, 'value', null);

            if (!strlen($value)) {
                $this->forbidden('no value');
            }

            $expire = isAke($this->args, 'expire', null);

            if (!strlen($expire)) {
                $this->forbidden('no expire');
            }

            $fileData       = $this->dir . $key;
            $fileExpire     = $this->dir . "expire::$key::$expire";

            if ($this->existsFile($fileExpire)) {
                unlink($fileExpire);
            } else {
                $expires = glob($this->dir . "expire::$key::*");

                if (!empty($expires)) {
                    foreach ($expires as $expire) {
                        File::delete($expire);
                    }
                }
            }

            if (file_exists($fileData)) {
                File::delete($fileData);
            }

            $this->putFile($fileData, $value);
            $this->putFile($fileExpire, "1");

            $this->success(true);
        }

        public function del()
        {
            $key = isAke($this->args, 'key', null);

            if (!strlen($key)) {
                $this->forbidden('no key');
            }

            $data = false;

            if ($this->existsFile($this->dir . $key)) {
                unlink($this->dir . $key);
                $data = true;
            }

            $this->success($data);
        }

        public function get()
        {
            $key = isAke($this->args, 'key', null);

            if (!strlen($key)) {
                $this->forbidden('no key');
            }

            $data = null;
            $exists = $this->existsFile($this->dir . $key);

            if (true === $exists) {
                $data = $this->readFile($this->dir . $key);
            }

            $this->success($data);
        }

        public function all()
        {
            $pattern = isAke($this->args, 'pattern', null);

            if (!strlen($pattern)) {
                $this->forbidden('no pattern');
            }

            $keys       = glob($this->dir . $pattern);
            $collection = array();

            if (count($keys)) {
                foreach ($keys as $key) {
                    $data               = $this->readFile($key);
                    $key                = str_replace($this->dir, '', $key);
                    $collection[$key]   = $data;
                }
            }

            $this->success($collection);
        }

        public function keys()
        {
            $pattern = isAke($this->args, 'pattern', null);

            if (!strlen($pattern)) {
                $this->forbidden('no pattern');
            }

            $keys       = glob($this->dir . $pattern);
            $collection = array();

            if (!empty($keys)) {
                foreach ($keys as $key) {
                    $key = str_replace($this->dir, '', $key);
                    array_push($collection, $key);
                }
            }

            $this->success($collection);
        }


        public function createFile($file, $content = null)
        {
            $this->deleteFile($file);

            if (null !== $content) {
                file_put_contents($file, $content, LOCK_EX);
            }

            umask(0000);

            chmod($file, 0777);

            return $create;
        }

        public function appendFile($file, $data)
        {
            $append = file_put_contents($file, $data, LOCK_EX | FILE_APPEND);

            umask(0000);

            chmod($file, 0777);

            return $append;
        }

        private function existsFile($file)
        {
            return file_exists($file);
        }

        private function getFile($file, $default = null)
        {
            return $this->existsFile($file) ? File::read($file) : $default;
        }

        private function putFile($file, $data, $chmod = 0777)
        {
            umask(0000);

            File::delete($file);

            $put = file_put_contents($file, $data, LOCK_EX);

            chmod($file, 0777);

            return $put;
        }

        private function deleteFile($file)
        {
            if (true === $this->existsFile($file)) {
                $fp = fopen($file, "w");

                if (flock($fp, LOCK_EX)) {
                    $status = File::delete($file);
                    fclose($fp);

                    return $status;
                } else {
                    throw new Exception("The file '$file' can not be removed.");
                }
            }

            return false;
        }

        private function readFile($file, $default = false, $mode = 'rb')
        {
            if (true === $this->existsFile($file)) {
                $fp     = fopen($file, $mode);
                $data   = fread($fp, filesize($file));

                fclose($fp);

                return $data;
            }

            return $default;
        }

        public function download($filename)
        {
            return $this->load($filename, false);
        }

        public function load($filename, $inline = true)
        {
            $filepath = $this->dir . 'upload/' . $filename;

            header('Cache-Control: public');
            header('Content-type: ' . $this->mime($filename));
            header('Content-Transfer-Encoding: Binary');
            header('Content-Length:' . filesize($filepath));

            if ($inline) {
                header('Content-Disposition: inline; filename=' . $filename);
            } else {
                header('Content-Disposition: attachment; filename=' . $filename);
            }

            readfile($filepath);

            exit;
        }

        public function remove($filename)
        {
            return File::delete($this->dir . 'upload/' . $filename);
        }

        private function mime($filename)
        {
            $mime_types = array(
                'txt'   => 'text/plain',
                'htm'   => 'text/html',
                'html'  => 'text/html',
                'php'   => 'text/html',
                'css'   => 'text/css',
                'js'    => 'application/javascript',
                'json'  => 'application/json',
                'xml'   => 'application/xml',
                'swf'   => 'application/x-shockwave-flash',
                'flv'   => 'video/x-flv',

                // images
                'png'   => 'image/png',
                'jpe'   => 'image/jpeg',
                'jpeg'  => 'image/jpeg',
                'jpg'   => 'image/jpeg',
                'gif'   => 'image/gif',
                'bmp'   => 'image/bmp',
                'ico'   => 'image/vnd.microsoft.icon',
                'tiff'  => 'image/tiff',
                'tif'   => 'image/tiff',
                'svg'   => 'image/svg+xml',
                'svgz'  => 'image/svg+xml',

                // archives
                'zip' => 'application/zip',
                'rar' => 'application/x-rar-compressed',
                'exe' => 'application/x-msdownload',
                'msi' => 'application/x-msdownload',
                'cab' => 'application/vnd.ms-cab-compressed',

                // audio/video
                'mp3' => 'audio/mpeg',
                'qt'  => 'video/quicktime',
                'mov' => 'video/quicktime',

                // adobe
                'pdf' => 'application/pdf',
                'psd' => 'image/vnd.adobe.photoshop',
                'ai'  => 'application/postscript',
                'eps' => 'application/postscript',
                'ps'  => 'application/postscript',

                // ms office
                'doc' => 'application/msword',
                'rtf' => 'application/rtf',
                'xls' => 'application/vnd.ms-excel',
                'ppt' => 'application/vnd.ms-powerpoint',

                // open office
                'odt' => 'application/vnd.oasis.opendocument.text',
                'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            );

            $tab = explode('.', $filename);

            $ext = strtolower(array_pop($tab));

            if (array_key_exists($ext, $mime_types)) {
                return $mime_types[$ext];
            } elseif (function_exists('finfo_open')) {
                $finfo      = finfo_open(FILEINFO_MIME);
                $mimetype   = finfo_file($finfo, $filename);
                finfo_close($finfo);

                return $mimetype;
            } else {
                return 'application/octet-stream';
            }
        }

        private function render($args = array())
        {
            header('content-type: application/json; charset=utf-8');

            die(json_encode($args));
        }

        private function forbidden($reason = 'NA')
        {
            $infos = array(
                'status'    => 403,
                'message'   => "Forbidden $reason"
            );

            $this->render($infos);
        }

        private function success($message)
        {
            $infos = array(
                'status'    => 200,
                'message'   => $message
            );

            $this->render($infos);
        }
    }
