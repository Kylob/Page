<?php

namespace BootPress\Page;

use BootPress\Page\Component as Page;

class Session
{
    /** @var bool A static confirmation that the session has been started, and the flash vars managed. */
    public static $started;

    /**
     * Set the **$value** of a $_SESSION[$key].  Every arg (or array value) will work it's way up the stack, so that you can establish any key within the array.
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
     * Merge **$values** into a $_SESSION[$key].  If it wasn't an array before, it will be now.
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
     * Retrieve the value of a $_SESSION[$key].  Every arg (or array value) will work it's way up the stack, so that you can access any key within the array.
     * 
     * @param string|array $key $_SESSION key(s) in ``array()`` or dot '**.**' notation.
     * 
     * @return mixed Either ``null`` if the value does not exist, or the value if it does.
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

    public function setFlash($key, $value)
    {
        $key = $this->explode($key);
        array_unshift($key, __CLASS__, 'flash', 'next');
        $this->set($key, $value);
    }

    public function getFlash($key, $default = null)
    {
        $key = $this->explode($key);
        array_unshift($key, __CLASS__, 'flash', 'now');

        return $this->get($key, $default);
    }

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
        return (self::$started || Page::html()->request->cookies->get(session_name())) ? true : false;
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
                $page = Page::html();
                if ($page->url['format'] == 'html' && !$page->request->isXmlHttpRequest()) {
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
