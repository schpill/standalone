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
    namespace Handler;

    class Session implements \SessionHandlerInterface
    {
        /** @var string store. */
        private $store;

        /** @var string Session save path. */
        private $savePath;

        /** @var string Session name. */
        private $sessionName;

        /** @var string The last known session ID */
        private $openSessionId = '';

        /** @var string Stores serialized data for tracking changes. */
        private $dataRead = '';

        /** @var bool Keeps track of whether the session has been written. */
        private $sessionWritten = false;

        public function __construct($store)
        {
            $this->store = $store;
        }

        /**
         * Register the store session handler.
         *
         * @return bool Whether or not the handler was registered.
         * @codeCoverageIgnore
         */
        public function register()
        {
            return session_set_save_handler($this, true);
        }

        /**
         * Open a session for writing. Triggered by session_start().
         *
         * @param string $savePath    Session save path.
         * @param string $sessionName Session name.
         *
         * @return bool Whether or not the operation succeeded.
         */
        public function open($savePath, $sessionName)
        {
            $this->savePath = $savePath;
            $this->sessionName = $sessionName;

            return true;
        }

        /**
         * Close a session from writing.
         *
         * @return bool Success
         */
        public function close()
        {
            $id = session_id();

            if ($this->openSessionId !== $id || !$this->sessionWritten) {
                $result = $this->store->write($this->formatId($id), '', false);
                $this->sessionWritten = (bool) $result;
            }

            return $this->sessionWritten;
        }

        /**
         * Read a session stored in store.
         *
         * @param string $id Session ID.
         *
         * @return string Session data.
         */
        public function read($id)
        {
            $this->openSessionId = $id;

            $this->dataRead = '';

            $item = $this->store->read($this->formatId($id));

            if (isset($item['expires']) && isset($item['data'])) {
                $this->dataRead = $item['data'];

                if ($item['expires'] <= time()) {
                    $this->dataRead = '';
                    $this->destroy($id);
                }
            }

            return $this->dataRead;
        }

        /**
         * Write a session to store.
         *
         * @param string $id   Session ID.
         * @param string $data Serialized session data to write.
         *
         * @return bool Whether or not the operation succeeded.
         */
        public function write($id, $data)
        {
            $changed = $id !== $this->openSessionId || $data !== $this->dataRead;
            $this->openSessionId = $id;

            $this->sessionWritten = $this->store->write($this->formatId($id), $data, $changed);

            return $this->sessionWritten;
        }

        /**
         * Delete a session stored in store.
         *
         * @param string $id Session ID.
         *
         * @return bool Whether or not the operation succeeded.
         */
        public function destroy($id)
        {
            $this->openSessionId = $id;

            $this->sessionWritten = $this->store->delete($this->formatId($id));

            return $this->sessionWritten;
        }

        /**
         * Satisfies the session handler interface, but does nothing. To do garbage
         * collection, you must manually call the garbageCollect() method.
         *
         * @param int $maxLifetime Ignored.
         *
         * @return bool Whether or not the operation succeeded.
         * @codeCoverageIgnore
         */
        public function gc($maxLifetime)
        {
            $this->store->gc($maxLifetime);

            return true;
        }

        /**
         * Prepend the session ID with the session name.
         *
         * @param string $id The session ID.
         *
         * @return string Prepared session ID.
         */
        private function formatId($id)
        {
            return sha1($this->sessionName . $id);
        }
    }
