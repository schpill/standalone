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

    use Aws\Ses\SesClient;
    use Thin\Mail\Message;
    use Thin\Mail\Mandrill;
    use Swift_SmtpTransport;
    use Swift_Message as SM;
    use Swift_Mailer;

    class MailerLib
    {
        public $driver;

        public function __construct($driver = null)
        {
            $this->driver = is_null($driver) ? 'ses' : $driver;
        }

        public function send()
        {
            $driver = $this->driver;

            return call_user_func_array([$this, $driver], func_get_args());
        }

        public function async()
        {
            $tab = (array) $this;

            async(function ($args) {
                $instance = lib('mailer', [$args['driver']]);

                unset($args['driver']);

                foreach ($args as $k => $v) {
                    $instance->$k($v);
                }

                return $instance->send();
            }, [$tab]);
        }

        public function at($ts = 'now')
        {
            $tab = (array) $this;

            at(function ($args) {
                $instance = lib('mailer', [$args['driver']]);

                unset($args['driver']);

                foreach ($args as $k => $v) {
                    $instance->$k($v);
                }

                return $instance->send();
            }, $ts, [$tab]);
        }

        public function mandrill()
        {
            $to = isAke($this, 'to', false);

            if ($to) {
                $toName     = isAke($this, 'to_name', $to);
                $from       = isAke($this, 'from', 'contact@clippCity.com');
                $fromName   = isAke($this, 'from_name', 'clippCity');
                $subject    = isAke($this, 'subject', 'Message');
                $priority   = isAke($this, 'priority', 3);
                $files      = isAke($this, 'files', []);
                $embeds     = isAke($this, 'embeds', []);

                $text       = isAke($this, 'text', false);
                $html       = isAke($this, 'html', false);

                if (false === $text && false === $html) {
                    throw new Exception("You need to provide a valid text or html message to send this email.");
                } else {
                    $message = new Message(new SM);

                    $message->from($from, $fromName)
                    ->to($to, $toName)
                    ->subject($subject)
                    ->priority($priority);

                    if (!empty($files)) {
                        foreach ($files as $file) {
                            if (File::exists($file)) {
                                $message->attach($file);
                            }
                        }
                    }

                    if (!empty($embeds)) {
                        foreach ($embeds as $embed) {
                            if (File::exists($embed)) {
                                $message->embed($embed);
                            }
                        }
                    }

                    $addText = false;

                    if (false !== $html) {
                        $message->setBody($html, 'text/html');

                        if (false !== $text) {
                            $message->addPart($text, 'text/plain');
                            $addText = true;
                        }
                    }

                    if (false !== $text && false === $addText) {
                        $message->setBody($text, 'text/plain');
                    }

                    return with(new Mandrill(Config::get('mailer.password')))->send($message->getSwiftMessage());
                }
            } else {
                throw new Exception("The field 'to' is needed to send this email.");
            }
        }

        public function ses()
        {
            $to = isAke($this, 'to', false);

            if ($to) {
                $toName     = isAke($this, 'to_name', $to);
                $from       = isAke($this, 'from', 'contact@clippCity.com');
                $fromName   = isAke($this, 'from_name', 'clippCity');
                $subject    = isAke($this, 'subject', 'Message');
                $priority   = isAke($this, 'priority', 3);
                $files      = isAke($this, 'files', []);
                $embeds     = isAke($this, 'embeds', []);

                $text       = isAke($this, 'text', false);
                $html       = isAke($this, 'html', false);

                if (false === $text && false === $html) {
                    return false;
                } else {
                    $transport = Swift_SmtpTransport::newInstance(
                        Config::get('ses.host'),
                        587,
                        'tls'
                    )->setUsername(Config::get('ses.user'))->setPassword(Config::get('ses.password'));

                    $this->swift = $message = SM::newInstance($subject)
                    ->setFrom([$from => $fromName])
                    ->setTo([$to => $toName])
                    ->setPriority($priority);

                    if (!empty($files)) {
                        foreach ($files as $file) {
                            if (File::exists($file)) {
                                $attachment = \Swift_Attachment::fromPath($file);
                                $message->attach($attachment);
                            }
                        }
                    }

                    if (!empty($embeds)) {
                        foreach ($embeds as $embed) {
                            if (File::exists($embed)) {
                                $attachment = \Swift_Image::fromPath($file);
                                $message->embed($attachment);
                            }
                        }
                    }

                    $addText = false;

                    if (false !== $html) {
                        $message->setBody($html, 'text/html');

                        if (false !== $text) {
                            $message->addPart($text, 'text/plain');
                            $addText = true;
                        }
                    }

                    if (false !== $text && false === $addText) {
                        $message->setBody($text, 'text/plain');
                    }

                    $mailer = Swift_Mailer::newInstance($transport);

                    return $mailer->send($message);
                }
            }

            return false;
        }

        protected function addAddresses($address, $name, $type)
        {
            if (is_array($address)) {
                $this->swift->{"set{$type}"}($address, $name);
            } else {
                $this->swift->{"add{$type}"}($address, $name);
            }

            return $this;
        }

        protected function prepAttachment($attachment, $options = [])
        {
            if (isset($options['mime'])) {
                $attachment->setContentType($options['mime']);
            }

            if (isset($options['as'])) {
                $attachment->setFilename($options['as']);
            }

            $this->swift->attach($attachment);

            return $this;
        }

        public function sesapi()
        {
            $to = isAke($this, 'to', false);

            if ($to) {
                $from       = isAke($this, 'from', 'contacts@clippcity.com');
                $reply      = isAke($this, 'reply', $from);
                $return     = isAke($this, 'return', $reply);
                $fromName   = isAke($this, 'from_name', 'clippCity');
                $subject    = isAke($this, 'subject', 'Message');
                $priority   = isAke($this, 'priority', 3);
                $files      = isAke($this, 'files', []);
                $embeds     = isAke($this, 'embeds', []);

                $text       = isAke($this, 'text', false);
                $html       = isAke($this, 'html', false);

                $client = SesClient::factory(array(
                    'key'       => Config::get('aws.access_key'),
                    'secret'    => Config::get('aws.secret_key'),
                    'version'   => Config::get('aws.ses_version', '2010-12-01'),
                    'region'    => Config::get('aws.host')
                ));

                $body = [];

                if ($text) {
                    $body['Text'] = [
                        'Data' => $text,
                        'Charset' => 'UTF-8',
                    ];
                }

                if ($html) {
                    $body['Html'] = [
                        'Data' => $html,
                        'Charset' => 'UTF-8',
                    ];
                }

                $status = $client->sendEmail([
                    // Source is required
                    'Source' => $from,
                    // Destination is required
                    'Destination' => [
                        'ToAddresses' => [$to]
                    ],
                    // Message is required
                    'Message' => [
                        // Subject is required
                        'Subject' => [
                            // Data is required
                            'Data' => $subject,
                            'Charset' => 'UTF-8',
                        ],
                        // Body is required
                        'Body' => $body,
                    ],
                    'ReplyToAddresses' => [$reply],
                    'ReturnPath' => $return
                ]);

                return true;
            }

            return false;
        }

        public function log()
        {
            $to = isAke($this, 'to', false);

            if ($to) {
                $toName     = isAke($this, 'to_name', $to);
                $from       = isAke($this, 'from', 'contacts@clippcity.com');
                $fromName   = isAke($this, 'from_name', 'clippCity');
                $subject    = isAke($this, 'subject', 'Message');
                $priority   = isAke($this, 'priority', 3);
                $files      = isAke($this, 'files', []);
                $embeds     = isAke($this, 'embeds', []);

                $text       = isAke($this, 'text', false);
                $html       = isAke($this, 'html', false);

                if (false === $text && false === $html) {
                    throw new Exception("You need to provide a valid text or html message to send this email.");
                } else {
                    $message = new Message(new SM);

                    $message->from($from, $fromName)
                    ->to($to, $toName)
                    ->subject($subject)
                    ->priority($priority);

                    if (!empty($files)) {
                        foreach ($files as $file) {
                            if (File::exists($file)) {
                                $message->attach($file);
                            }
                        }
                    }

                    if (!empty($embeds)) {
                        foreach ($embeds as $embed) {
                            if (File::exists($embed)) {
                                $message->embed($embed);
                            }
                        }
                    }

                    $addText = false;

                    if (false !== $html) {
                        $message->setBody($html, 'text/html');

                        if (false !== $text) {
                            $message->addPart($text, 'text/plain');
                            $addText = true;
                        }
                    }

                    if (false !== $text && false === $addText) {
                        $message->setBody($text, 'text/plain');
                    }

                    return log()->debug($this->getMimeEntityString($message));
                }
            } else {
                throw new Exception("The field 'to' is needed to send this email.");
            }
        }

        protected function getMimeEntityString($entity)
        {
            $string = PHP_EOL . (string) $entity->getHeaders() . PHP_EOL . $entity->getBody();

            foreach ($entity->getChildren() as $children) {
                $string .= PHP_EOL . PHP_EOL . $this->getMimeEntityString($children);
            }

            return $string;
        }

        public function __call($m, $a)
        {
            $m = Inflector::uncamelize($m);

            $this->$m = current($a);

            return $this;
        }
    }
