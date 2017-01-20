<?php

namespace BootPress\Page;

class Session
{
    /** @var bool A static confirmation that the session has been started, and the flash vars managed. */
    public static $started;

    /**
     * Ensure a session has been started, and get the ``session_id()``.
     *
     * @return string
     */
    public function id()
    {
        return $this->started() ? session_id() : '';
    }

    /**
     * Set the **$value** of a $_SESSION[**$key**].
     *
     * @param string|array $key   $_SESSION key(s) in``array()`` or dot '**.**' notation.
     * @param mixed        $value Any value except ``null``.
     *
     * @example
     *
     * ```php
     * $page->session->set(array('key', 'custom), 'value');
     * $page->session->set('user.id', 100);
     * ```
     */
    public function set($key, $value)
    {
        if (!is_null($value) && $this->started()) {
            $merge = &$_SESSION;
            foreach ($this->explode($key) as $name) {
                $merge = &$merge[$name];
            }
            $merge = $value;
        }
    }

    /**
     * Merge **$values** into a $_SESSION[**$key**].  If it wasn't an array before, it will be now.
     *
     * @param string|array $key    $_SESSION key(s) in``array()`` or dot '**.**' notation.
     * @param mixed        $values To overwrite or add.
     *
     * @example
     *
     * ```php
     * $page->session->add('user', array('name' => 'Joe Bloggs'));
     * ```
     */
    public function add($key, array $values)
    {
        $get = (array) $this->get($key);
        $this->set($key, $get + $values);
    }

    /**
     * Retrieve the value of a $_SESSION[**$key**].
     * 
     * @param string|array $key     $_SESSION key(s) in ``array()`` or dot '**.**' notation.
     * @param mixed        $default The value you want to return if the value does not exist.
     * 
     * @return mixed Either **$default** if the value does not exist, or the value if it does.
     *
     * @example
     *
     * ```php
     * echo $page->session->get(array('key', 'custom)); // value
     * print_r($page->session->get('user')); // array('id' => 100, 'name' => 'Joe Bloggs')
     * ```
     */
    public function get($key, $default = null)
    {
        if ($this->resumable() && $this->started()) {
            $session = $_SESSION;
            foreach ($this->explode($key) as $name) {
                if (isset($session[$name])) {
                    $session = $session[$name];
                } else {
                    return $default;
                }
            }
        }

        return isset($session) ? $session : $default;
    }

    /**
     * Unset the $_SESSION **$key**(s).  Every param you pass will be removed.
     * 
     * @param string|array $key $_SESSION key(s) in ``array()`` or dot '**.**' notation.
     *
     * @example
     *
     * ```php
     * $page->session->remove('user');
     * ```
     */
    public function remove($key)
    {
        if ($this->resumable() && $this->started()) {
            $unset = &$_SESSION;
            foreach (func_get_args() as $key) {
                $names = $this->explode($key);
                while (count($names) > 1) {
                    $name = array_shift($names);
                    if (isset($_SESSION[$name]) && is_array($_SESSION[$name])) {
                        $_SESSION = &$_SESSION[$name];
                    }
                }
                unset($_SESSION[array_shift($names)]);
                $_SESSION = &$unset; // clean up after each pass
            }
        }
    }

    /**
     * Set a flash value that will only be available on the next page request.
     *
     * @param string|array $key   $_SESSION flash key(s) in``array()`` or dot '**.**' notation.
     * @param mixed        $value Any value except ``null``.
     *
     * @example
     *
     * ```php
     * $page->session->setFlash('message', 'Hello world!');
     * ```
     */
    public function setFlash($key, $value)
    {
        $key = $this->explode($key);
        array_unshift($key, __CLASS__, 'flash', 'next');
        $this->set($key, $value);
    }

    /**
     * Get a flash value that was set on the previous page request.
     *
     * @param string|array $key     $_SESSION flash key(s) in ``array()`` or dot '**.**' notation.
     * @param mixed        $default The value you want to return if the value does not exist.
     * 
     * @return mixed Either **$default** if the value does not exist, or the value if it does.
     *
     * @example
     *
     * ```php
     * $page->session->getFlash('message'); // Hello world!
     * ```
     */
    public function getFlash($key, $default = null)
    {
        $key = $this->explode($key);
        array_unshift($key, __CLASS__, 'flash', 'now');

        return $this->get($key, $default);
    }

    /**
     * Keep the flash values in the current request, and add them to the next page request.  This is not necessary for XML Http Requests, as we only forward the flash vars on HTML page requests.
     */
    public function keepFlash()
    {
        if ($now = $this->get(array(__CLASS__, 'flash', 'now'))) {
            $this->add(array(__CLASS__, 'flash', 'next'), $now);
        }
    }

    private function explode($key)
    {
        return (is_array($key)) ? $key : explode('.', $key);
    }

    /**
     * Determine if a session has been previously started.
     *
     * @return bool
     */
    private function resumable()
    {
        return (isset($_SESSION) || isset($_COOKIE[session_name()])) ? true : false;
    }

    /**
     * Start a new, or update an existing session.
     *
     * @return bool
     */
    private function started()
    {
        if (is_null(self::$started)) {
            self::$started = (session_status() === PHP_SESSION_ACTIVE) ? true : session_start();
            if (self::$started) {
                if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
                    if (isset($_SESSION[__CLASS__]['flash']['next'])) {
                        $_SESSION[__CLASS__]['flash']['now'] = $_SESSION[__CLASS__]['flash']['next'];
                        unset($_SESSION[__CLASS__]['flash']['next']);
                    } elseif (isset($_SESSION[__CLASS__]['flash'])) {
                        unset($_SESSION[__CLASS__]);
                    }
                }
            }
        }

        return self::$started;
    }
}
