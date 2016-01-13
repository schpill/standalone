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

    class AliasLib
    {
        public function ns($to, $target, $namespace = 'Thin')
        {
            $class  = '\\' . $target;
            $target = $to;
            $to     = $namespace . '\\' . $to;

            if (class_exists($class) && !class_exists($to)) {
                eval('namespace ' . $namespace . '; class ' . $target . ' extends ' . $class . ' {
                    private $__events = [];
                    private $__datas = [];

                    public function __call($m, $a)
                    {
                        $event = isAke($this->__events, $m, false);

                        if (false !== $event) {
                            return call_user_func_array($event, $a);
                        }
                    }

                    public function __isset($k)
                    {
                        $token = time();

                        return isAke($this->__datas, $k, $token) != $token;
                    }

                    public function __unset($k)
                    {
                        unset($this->__datas[$k]);
                    }

                    public function __set($k, $v)
                    {
                        $this->__datas[$n] = $e;

                        return $this;
                    }

                    public function __get($k)
                    {
                        return isAke($this->__datas, $k, null);
                    }

                    public function __event($n, $e)
                    {
                        if (!is_callable($e)) {
                            return $this->__data($n, $e);
                        }

                        $this->__events[$n] = $e;

                        return $this;
                    }

                    public function __data($n, $e)
                    {
                        if (is_callable($e)) {
                            return $this->__event($n, $e);
                        }

                        $this->__datas[$n] = $e;

                        return $this;
                    }
                }');
            } else {
                if (!class_exists($class)) {
                    throw new Exception("The class '$class' does not exist.");
                } elseif (class_exists($to)) {
                    throw new Exception("The class '$to' ever exists and cannot be aliased.");
                } else {
                    throw new Exception("A problem occured.");
                }
            }
        }

        public function none($to, $target)
        {
            if (class_exists($target) && !class_exists($to)) {
                eval('class ' . $to . ' extends ' . $target . ' {
                    private $__events = [];
                    private $__datas = [];

                    public function __call($m, $a)
                    {
                        $event = isAke($this->__events, $m, false);

                        if (false !== $event) {
                            return call_user_func_array($event, $a);
                        }
                    }

                    public function __isset($k)
                    {
                        $token = time();

                        return isAke($this->__datas, $k, $token) != $token;
                    }

                    public function __unset($k)
                    {
                        unset($this->__datas[$k]);
                    }

                    public function __set($k, $v)
                    {
                        $this->__datas[$n] = $e;

                        return $this;
                    }

                    public function __get($k)
                    {
                        return isAke($this->__datas, $k, null);
                    }

                    public function __event($n, $e)
                    {
                        if (!is_callable($e)) {
                            return $this->__data($n, $e);
                        }

                        $this->__events[$n] = $e;

                        return $this;
                    }

                    public function __data($n, $e)
                    {
                        if (is_callable($e)) {
                            return $this->__event($n, $e);
                        }

                        $this->__datas[$n] = $e;

                        return $this;
                    }
                }');
            } else {
                if (!class_exists($target)) {
                    throw new Exception("The class '$target' does not exist.");
                } elseif (class_exists($to)) {
                    throw new Exception("The class '$to' ever exists and cannot be aliased.");
                } else {
                    throw new Exception("A problem occured.");
                }
            }
        }
    }
