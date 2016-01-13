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

    use Aws\S3\S3Client;
    use Symfony\Component\Finder\SplFileInfo as File;
    use Aws\S3\Exception\InvalidAccessKeyIdException;
    use Aws\Common\Exception\InstanceProfileCredentialsException;

    class S3Lib
    {
        private $client;
        private $name;

        public function __construct()
        {
            $this->bucket = Config::get('s3.bucket', SITE_NAME);

            $this->client = S3Client::factory([
                'credentials' => [
                    'key'    => Config::get('aws.access_key'),
                    'secret' => Config::get('aws.secret_key')
                ],
                'region' => Config::get('s3.region', 'eu-west-1'),
                'version' => 'latest',
            ]);
        }

        public function put($key, $content, $acl = null, $options = [])
        {
            $acl = is_null($acl) ? 'public' : $acl;

            return $this->client->upload($this->bucket, $key, $content, $acl);
        }

        public function delete($fileName)
        {
            try {
                $this->client->deleteObject(array(
                    'Bucket' => $this->name,
                    'Key'    => $fileName
                ));
            } catch(\Exception $e) {
                throw new InvalidAccessKeyIdException("The AWS Access Key Id you provided does not exist in our records.");
            }
        }
    }
