<?php

namespace Thin;

use Aws\Ses\SesClient;
use Swift_Mime_Message;
use Swift_Transport;
use Swift_Events_SendEvent;
use Swift_Events_EventListener;

class SesLib implements Swift_Transport
{
    /**
     * The Amazon SES instance.
     *
     * @var \Aws\Ses\SesClient
     */
    protected $ses;

    /**
     * The plug-ins registered with the transport.
     *
     * @var array
     */
    public $plugins = [];

    /**
     * {@inheritdoc}
     */
    public function isStarted()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        return true;
    }

    /**
     * Register a plug-in with the transport.
     *
     * @param  \Swift_Events_EventListener  $plugin
     * @return void
     */
    public function registerPlugin(Swift_Events_EventListener $plugin)
    {
        array_push($this->plugins, $plugin);

        return $this;
    }

    /**
     * Iterate through registered plugins and execute plugins' methods.
     *
     * @param  \Swift_Mime_Message  $message
     * @return void
     */
    protected function beforeSendPerformed(Swift_Mime_Message $message)
    {
        $event = new Swift_Events_SendEvent($this, $message);

        foreach ($this->plugins as $plugin) {
            if (method_exists($plugin, 'beforeSendPerformed')) {
                $plugin->beforeSendPerformed($event);
            }
        }
    }

    /**
     * Create a new SES transport instance.
     *
     * @param  \Aws\Ses\SesClient  $ses
     * @return void
     */
    public function __construct(SesClient $ses)
    {
        $this->ses = $ses;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Swift_Mime_Message $message, &$failedRecipients = null)
    {
        $this->beforeSendPerformed($message);

        return $this->ses->sendRawEmail([
            'Source' => key($message->getSender() ?: $message->getFrom()),
            'RawMessage' => [
                'Data' => $message->toString(),
            ],
        ]);
    }
}
