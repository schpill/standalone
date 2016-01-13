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

    class GraphLib
    {
        private $id, $object;

        public function __construct($object)
        {
            $this->object   = $object;
            $this->key      = $this->makeKey($object);
        }

        public function link($object)
        {
            $key = $this->makeKey($object);

            redis()->sadd("g.{$this->key}.l", $key);
            redis()->sadd("g.{$key}.lb", $this->key);
        }

        public function unlink($object)
        {
            if ($this->isLinking($object)) {
                $key = $this->makeKey($object);

                redis()->srem("g.{$this->key}.l", $key);
                redis()->srem("g.{$key}.lb", $this->key);
            }
        }

        public function linker($object)
        {
            $key = $this->makeKey($object);

            redis()->sadd("g.{$key}.l", $this->key);
            redis()->sadd("g.{$this->key}.lb", $key);
        }

        public function unlinker($object)
        {
            if ($this->isLinkedBy($object)) {
                $key = $this->makeKey($object);

                redis()->srem("g.{$key}.l", $this->key);
                redis()->srem("g.{$this->key}.lb", $key);
            }
        }

        public function attach($action, $object)
        {
            $key = $this->makeKey($object);

            redis()->sadd("g.{$this->key}.$action", $key);
            redis()->sadd("g.{$key}.$action" . "b", $this->key);
        }

        public function detach($action, $object)
        {
            if ($this->isAttaching($action, $object)) {
                $key = $this->makeKey($object);

                redis()->srem("g.{$this->key}.$action", $key);
                redis()->srem("g.{$key}.$action" . "b", $this->key);
            }
        }

        public function isAttaching($action, $object)
        {
            $key = $this->makeKey($object);

            return redis()->sismember("g.{$this->key}.$action", $key);
        }

        public function associate($action, $object)
        {
            $key = $this->makeKey($object);

            redis()->sadd("g.{$key}.$action", $this->key);
            redis()->sadd("g.{$this->key}.$action" . "b", $key);
        }

        public function disociate($action, $object)
        {
            if ($this->isAssociatedBy($action, $object)) {
                $key = $this->makeKey($object);

                redis()->srem("g.{$key}.$action", $this->key);
                redis()->srem("g.{$this->key}.$action" . "b", $key);
            }
        }

        public function isAssociatedBy($action, $object)
        {
            $key = $this->makeKey($object);

            return redis()->sismember("g.{$this->key}.$action" . "b", $key);
        }

        /* TODO */
        public function __call($m, $a)
        {

        }

        /* Aliases */

        public function follow($object)
        {
            return $this->link($object);
        }

        public function member($object)
        {
            return $this->link($object);
        }

        public function unfollow($object)
        {
            return $this->unlink($object);
        }

        public function unmember($object)
        {
            return $this->unlink($object);
        }

        /* Aliases */

        public function following($object)
        {
            return $this->linker($object);
        }

        public function membership($object)
        {
            return $this->linker($object);
        }

        public function unfollowing($object)
        {
            return $this->unlinker($object);
        }

        public function unmembership($object)
        {
            return $this->unlinker($object);
        }

        public function links()
        {
            return redis()->smembers("g.{$this->key}.l");
        }

        public function linkers()
        {
            return redis()->smembers("g.{$this->key}.lb");
        }

        /* Aliases */

        public function followings()
        {
            return $this->links();
        }

        public function memberships()
        {
            return $this->links();
        }

        public function followers()
        {
            return $this->linkers();
        }

        public function members()
        {
            return $this->linkers();
        }

        public function isLinking($object)
        {
            $key = $this->makeKey($object);

            return redis()->sismember("g.{$this->key}.l", $key);
        }

        public function isLinkedBy($object)
        {
            $key = $this->makeKey($object);

            return redis()->sismember("g.{$this->key}.lb", $key);
        }

        /* Aliases */

        public function isFollowing($object)
        {
            return $this->isLinking($object);
        }

        public function isMembership($object)
        {
            return $this->isLinking($object);
        }

        public function isFollowed($object)
        {
            return $this->isLinkedBy($object);
        }

        public function isMember($object)
        {
            return $this->isLinkedBy($object);
        }

        public function isMutual($object)
        {
            return $this->isLinking($object) && $this->isLinkedBy($object);
        }

        public function linksCount()
        {
            return redis()->scard("g.{$this->key}.l");
        }

        public function linkersCount()
        {
            return redis()->scard("g.{$this->key}.lb");
        }

        /* Aliases */

        public function countFollowings()
        {
            return $this->linksCount();
        }

        public function countMemberships()
        {
            return $this->linksCount();
        }

        public function countFollowers()
        {
            return $this->linkersCount();
        }

        public function countMembers()
        {
            return $this->linkersCount();
        }

        public function commonLinks($objects = [])
        {
            $objects[] = $this->object;

            $keys = [];

            foreach ($objects as $object) {
                $key    = $this->makeKey($object);
                $keys[] = "g.{$key}.l";
            }

            return call_user_func_array(
                [redis(), 'sinter'],
                $keys
            );
        }

        public function commonLinkers($objects = [])
        {
            $objects[] = $this->object;

            $keys = [];

            foreach ($objects as $object) {
                $key    = $this->makeKey($object);
                $keys[] = "g.{$key}.lb";
            }

            return call_user_func_array(
                [redis(), 'sinter'],
                $keys
            );
        }

        /* Aliases */

        public function commonFollowings($objects = [])
        {
            return $this->commonLinks($objects);
        }


        public function commonMemberships($objects = [])
        {
            return $this->commonLinks($objects);
        }

        public function commonFollowers($objects = [])
        {
            return $this->commonLinkers($objects);
        }

        public function commonMembers($objects = [])
        {
            return $this->commonLinkers($objects);
        }

        private function makeKey($object)
        {
            return $object->db()->db . '.' . $object->db()->table . '.' . $object->id;
        }

        public function getKey()
        {
            return $this->key;
        }

        public function getObject()
        {
            return $this->object;
        }

        public function getDataObject($object)
        {
            list($db, $table, $id) = explode('.', $object, 3);
            $dataObject = rdb($db, $table)->find($id);

            if ($dataObject) {
                return $dataObject;
            }

            return false;
        }

        public function membersMemberships()
        {
            $collection = [];

            $members = $this->members();

            foreach ($members as $member) {
                $object = $this->getDataObject($member);

                if ($object) {
                    $i = new self($object);
                    $collection[] = $i->memberships();
                }
            }

            return $collection;
        }

        public function membersMembers()
        {
            $collection = [];

            $members = $this->members();

            foreach ($members as $member) {
                $object = $this->getDataObject($member);

                if ($object) {
                    $i = new self($object);
                    $collection[] = $i->members();
                }
            }

            return $collection;
        }

        public function membershipsMembers()
        {
            $collection = [];

            $members = $this->memberships();

            foreach ($members as $member) {
                $object = $this->getDataObject($member);

                if ($object) {
                    $i = new self($object);
                    $collection[] = $i->members();
                }
            }

            return $collection;
        }

        public function membershipsMemberships()
        {
            $collection = [];

            $members = $this->memberships();

            foreach ($members as $member) {
                $object = $this->getDataObject($member);

                if ($object) {
                    $i = new self($object);
                    $collection[] = $i->memberships();
                }
            }

            return $collection;
        }

        public function shortestPath($object)
        {
            $path           = [];
            $distance       = PHP_INT_MAX;
            $graphObject    = new self($object);

            if ($this->isMember($object) || $this->isMembership($object)) {
                $distance = 0;
            } else {
                $members = $this->members();
                list($path, $distance, $found) = $this->recursiveMembers($members, $graphObject, $path);

                if (!$found) {
                    $path       = [];
                    $distance   = false;
                }
            }

            if (empty($path)) {
                $distance = false;
            }

            if ($distance == PHP_INT_MAX) {
                $distance = false;
            }

            return ['path' => $path, 'distance' => $distance];
        }

        private function recursiveMembers($members, $instance, $path, $distance = 1, $found = false)
        {
            foreach ($members as $member) {
                $tmpObject = $this->getDataObject($member);

                if ($tmpObject) {
                    $path[] = $member;

                    if (!$instance->isMember($tmpObject) && !$instance->isMembership($tmpObject)) {
                        $graphObject = new self($tmpObject);
                        $objectMembers = $graphObject->members();
                        $distance++;

                        return $this->recursiveMembers($objectMembers, $graphObject, $path, $distance);
                    } else {
                        $found = true;
                    }
                }
            }

            return [$path, $distance, $found];
        }

        public function query(callable $query, callable $closure, $args = [])
        {
            $args[] = $closure;

            return call_user_func_array($query, $args);
        }
    }
