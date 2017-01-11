<?php

namespace BootPress\Page;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Pimple\Container;
use AltoRouter;

class Component
{
    /** 
     * A Dependency Injection object that you can access at ``$page->di``.  If you access it and this static property is null, we will set up a [Pimple\Container](http://pimple.sensiolabs.org/) object for you.
     *
     * @var object
     */
    public static $di;

    /**
     * @var object Either the [Symfony\Component\HttpFoundation\Request](http://symfony.com/doc/current/components/http_foundation.html) object you gave us at ``Page::html()``, or the one we made for you.
     */
    private $request;

    /**
     * @var array An array of directory names for reference purposes:
     *
     *   - '**base**' => The common dir among all those in this array - don't ever rely on this to be anything in particular.
     *   - '**page**' => The submitted ``$url['dir']`` when you first accessed the ``Page::html()``.
     *   - '**$name**' => The ``$name = $page->dirname(__CLASS__)`` directory.
     *   - '**...**' => Whatever other classes you have ``$page->dirname()``ed.
     */
    private $dir = array();

    /**
     * @var array Information about your url's that may come in handy:
     *
     *   - '**full**' => The complete url '**base**', '**path**', '**suffix**', and '**query**' as presently constituted.
     *   - '**base**' => The submitted ``$url['base']`` when you first accessed the ``Page::html()``, including a trailing slash '__/__'.
     *   - '**path**' => The url path that comes after the '**base**', and before the '**query**', with no leading or trailing slashes.  If this '**format**' is an html page, then it does not include the url '**suffix**'.
     *   - '**suffix**' => The submitted ``$url['suffix']`` when you first accessed the ``Page::html()``.
     *   - '**query**' => A string beginning with '**?**' if there are any url params, or blank if not.
     *   - '**preg**' => The url '**base**', ``preg_quote()``ed, and ready to go.
     *   - '**chars**' => The submitted ``$url['chars']`` when you first accessed the ``Page::html()``, ``preg_quote()``ed, but with the dot ('**.**'), slashes ('__/__'), question mark ('**?**'), and hash tag ('**#**') removed, so that we can include them as desired.
     *   - '**html**' => An array of ``$url['suffix']``'s that correspond with html pages.
     *   - '**format**' => Either '**html**' if the current url's suffix corresponds with an html page, or the current suffix without a leading dot eg. '**pdf**', '**jpg**', etc.
     *   - '**method**' => How the page is being called eg. '**GET**' or '**POST**'.
     *   - '**route**' => The current '**path**' with a leading slash ie. ``'/'.$page->url['path']``.
     *   - '**set**' => An ``array($name => $url, ...)`` of the ``$page->url('set', $name, $url)``'s you (and we) have set.
     */
    private $url = array();

    /**
     * @var array The property from which all others are set and retrieved.  The ones we use to ``$page->display()`` your page are:
     *
     *   - '**doctype**' => The '``<!doctype html>``' that goes at the top of your HTML page.
     *   - '**language**' => The ``<html lang="...">`` value.  The default is '**en**'.  If your page no speaka any english, then you can change it to ``$page->language = 'sp'``, or any other [two-letter language abbreviation](http://www.loc.gov/standards/iso639-2/langcodes.html).
     *   - '**charset**' => The ``<meta charset="...">`` value.  The default is '**utf-8**'.
     *   - '**title**' => Inserted into the ``<head>`` section within ``<title>`` tags (empty or not).  The default is empty.
     *   - '**description**' => The ``<meta name="description" content="...">`` value (if any).  The default is empty.
     *   - '**keywords**' => The ``<meta name="keywords" content="...">`` value (if any).  The default is empty.
     *   - '**robots**' => If you set this to ``false``, then we'll tell the search engines ``<meta name="robots" content="noindex, nofollow">``: "Don't add this page to your index" (noindex), and "Don't follow any links that may be here" (nofollow) either.  If you want one or the other, then just leave this property alone and you can spell it all out for them in ``$page->meta('name="robots" content="noindex"')``.
     *   - '**body**' => This used to be useful for Google Maps, and other ugly hacks before the advent of jQuery.  There are better ways to go about this, but it makes for a handy onload handler, or to insert css styles for the body.  Whatever you set here will go inside the ``<body>`` tag.
     */
    private $html = array();

    /** @var array Stores meta, ico, css, link, style, other, js, jquery, and script info. */
    private $data = array();

    /** @var array Managed in ``$this->filter()`` (public), and retrieved in ``$this->process()`` (private). */
    private $filters = array();

    /** @var bool ``$this->send()`` exit's a Symfony Response if this is ``false``. */
    private $testing = false;

    /** @var object The Singleton pattern. */
    private static $instance;

    /** @var object A BootPress\Page\Session instance. */
    private static $session;

    /**
     * Get a singleton instance of the Page class, so that your code can always be on the same "Page".  Passing parameters will only make a difference when calling it for the first time, unless you **$overthrow** it.
     *
     * @param array $url You can override any of the following default options:
     *
     * - '**dir**' => The base directory of your website.  I would recommend using a root folder that is not publically accessible.  The default is your public html folder.
     * - '**base**' => The root url.  If you specify this, then we will enforce it.  If it starts with *'https'* (secured), then your website will be inaccessible via *'http'* (insecure).  If you include a subdomain (eg. *'www'*) or not, it will be enforced.  This way you don't have duplicate content issues, and know exactly how your website will be accessed.  The default is whatever the current url is.
     * - '**suffix**' => What you want to come after all of your url (html) paths.  The options are:  '' (empty), '**\/**', '**.htm**', '**.html**', '**.shtml**', '**.phtml**', '**.php**', '**.asp**', '**.jsp**', '**.cgi**', '**.cfm**', and '**.pl**'.  The default is nothing.
     * - '**chars**' => This lets you specify which characters are permitted within your url paths.  You should restrict this to as few characters as possible.  The default is '**a-z0-9~%.:_-**'.
     * - '**testing**' => If you include and set this to anything, then any calls to ``$page->send()`` will not ``exit``.  This enables us to unit test responses without halting the script.
     *
     * @param object $request   A [Symfony\Component\HttpFoundation\Request](http://symfony.com/doc/current/components/http_foundation.html) object.
     * @param mixed  $overthrow If anything but ``false``, then the parameters you pass will overthrow the previous ones submitted.  This is mainly useful for unit testing.
     *
     * @return object A singleton Page instance.
     *
     * @example
     *
     * ```php
     * $page = \BootPress\Page\Component::html();
     * ```
     */
    public static function html(array $url = array(), Request $request = null, $overthrow = false)
    {
        if ($overthrow || null === static::$instance) {
            $page = static::isolated($url, $request);
            if ($page->url['format'] == 'html' && isset($url['base']) && is_string($url['base'])) {
                if (($path = $page->redirect()) || strcmp($page->url['full'], $page->request->getUri()) !== 0) {
                    $page->filter('response', function ($page, $response) {
                        $cookie = new Cookie('referer', $page->request->headers->get('referer'), time() + 60);
                        $response->headers->setCookie($cookie);
                    }, array('redirect', 301));
                    $page->eject($path ? $path : $page->url['full'], 301);
                } elseif ($referer = $page->request->cookies->get('referer')) {
                    $page->request->headers->set('referer', $referer);
                    $page->filter('response', function ($page, $response) {
                        $response->headers->clearCookie('referer');
                    });
                }
            }
            static::$instance = $page;
        }

        return static::$instance;
    }

    /**
     * Get an isolated instance of the Page class, so that you can use it for whatever.
     *
     * @param array  $url     The '**base**' url is not enforced here.
     * @param object $request
     *
     * @return object
     */
    public static function isolated(array $url = array(), Request $request = null)
    {
        extract(array_merge(array(
            'dir' => null,
            'base' => null,
            'suffix' => null,
            'chars' => 'a-z0-9~%.:_-',
        ), $url), EXTR_SKIP);
        $page = new static();
        if (isset($testing)) {
            $page->testing = $testing;
        }
        $page->request = (is_null($request)) ? Request::createFromGlobals() : $request;
        if (false === $folder = realpath($dir)) {
            $folders = array();
            $current = realpath('');
            $dir = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $dir);
            if (strstr($current, DIRECTORY_SEPARATOR, true) !== strstr($dir, DIRECTORY_SEPARATOR, true)) {
                $dir = $current.DIRECTORY_SEPARATOR.$dir;
            }
            foreach (array_filter(explode(DIRECTORY_SEPARATOR, $dir), 'strlen') as $folder) {
                if ($folder == '..') {
                    array_pop($folders);
                } elseif ($folder != '.') {
                    $folders[] = $folder;
                }
            }
            $folder = implode(DIRECTORY_SEPARATOR, $folders);
        }
        $page->dir('set', 'base', $folder);
        $page->dir('set', 'page', $folder);
        $page->url['full'] = '';
        $page->url['base'] = (!empty($base)) ? trim($base, '/').'/' : $page->request->getUriForPath('/');
        if (parse_url($page->url['base'], PHP_URL_SCHEME) === null) {
            $page->url['base'] = 'http://'.$page->url['base'];
        }
        $page->url['path'] = trim($page->request->getPathInfo(), '/'); // excludes leading and trailing slashes
        if ($page->url['suffix'] = pathinfo($page->url['path'], PATHINFO_EXTENSION)) {
            $page->url['suffix'] = '.'.$page->url['suffix']; // includes leading dot
            $page->url['path'] = substr($page->url['path'], 0, -strlen($page->url['suffix'])); // remove suffix from path
        }
        $page->url['query'] = (null !== $qs = $page->request->getQueryString()) ? '?'.$qs : '';
        $page->url['preg'] = preg_quote($page->url['base'], '/');
        $page->url['chars'] = 'a-z0-9'.preg_quote(str_replace(array('a-z', '0-9', '.', '/', '\\', '?', '#'), '', $chars), '/');
        $page->url['html'] = array('', '/', '.htm', '.html', '.shtml', '.phtml', '.php', '.asp', '.jsp', '.cgi', '.cfm', '.pl');
        if (empty($page->url['suffix']) || in_array($page->url['suffix'], $page->url['html'])) {
            $page->url['format'] = 'html';
        } else {
            $page->url['format'] = substr($page->url['suffix'], 1);
            $page->url['path'] .= $page->url['suffix']; // put it back on since it is relevant now
        }
        $page->url['method'] = $page->request->getMethod(); // eg. GET|POST
        $page->url['route'] = '/'.$page->url['path']; // includes leading slash and unfiltered path (below)
        $page->url('set', 'base', $page->url['base']);
        $page->url('set', 'dir', $page->url['base'].'page/');
        $page->url['path'] = preg_replace('/[^'.$page->url['chars'].'.\/]/i', '', $page->url['path']);
        $page->url['suffix'] = (!empty($suffix) && in_array($suffix, $page->url['html'])) ? $suffix : '';
        $page->url['full'] = $page->formatLocalPath($page->url['base'].$page->url['path'].$page->url['query']);
        $page->set(array(), 'reset');

        return $page;
    }

    /**
     * Set Page properties that can be accessed directly via ``$page->$name``.  This is a convenience method as you can also set them directly eg. ``$page->name = 'value'``.  All of these values are stored (and can be accessed) at the ``$page->html`` array.
     *
     * @param string|array $name  The property you would like to set.  You can do this one at a time, or make this an array to set multiple values at once.
     * @param mixed        $value Used if the **$name** is a string.
     *
     * @example
     *
     * ```php
     * $page->set(array(
     *     'title' => 'Sample Page',
     *     'description' => 'Snippet of information',
     *     'keywords' => 'Comma, Spearated, Tags',
     * ));
     * ```
     */
    public function set($name, $value = '')
    {
        $html = (is_array($name)) ? $name : array($name => $value);
        if (is_array($name) && $value == 'reset') {
            $this->html = array(
                'doctype' => '<!doctype html>',
                'language' => 'en',
                'charset' => 'utf-8',
                'title' => '',
                'description' => '',
                'keywords' => '',
                'robots' => true,
                'body' => '',
            );
        }
        foreach ($html as $name => $value) {
            $this->html[strtolower($name)] = $value;
        }
    }

    /**
     * Enables us to use multi-dimensional arrays with HTML Page properties.
     * 
     * @param string $name
     * 
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->html[$name]);
    }

    /**
     * Enables us to set HTML Page properties directly.
     * 
     * @param string $name
     * @param mixed  $value
     */
    public function __set($name, $value)
    {
        $name = strtolower($name);
        if (is_null($value)) {
            unset($this->html[$name]);
        } else {
            $this->html[$name] = $value;
        }
    }

    /**
     * A magic getter for our private properties.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function &__get($name)
    {
        // This method must return a reference and not use ternary operators for __set()ing multi-dimensional arrays
        // http://stackoverflow.com/questions/4310473/using-set-with-arrays-solved-but-why
        // http://stackoverflow.com/questions/5966918/return-null-by-reference-via-get
        switch ($name) {
            case 'di':
                if (is_null(static::$di)) {
                    static::$di = new Container;
                }

                return static::$di;
                break;
            case 'session':
                if (is_null(static::$session)) {
                    static::$session = new Session;
                }

                return static::$session;
                break;
            case 'request':
            case 'dir':
            case 'url':
            case 'html':
                return $this->$name;
                break;
            default:
                return $this->html[strtolower($name)];
                break;
        }
    }

    /**
     * Send your visitor packing to another **$url** eg. after a form has been submitted.
     *
     * @param string $url                Either the full url, or just the path.
     * @param int    $http_response_code The status code.
     *
     * @example
     *
     * ```php
     * $page->eject('users');
     * ```
     */
    public function eject($url = '', $http_response_code = 302)
    {
        $url = (!empty($url)) ? $this->formatLocalPath($url) : $this->url['base'];

        return $this->send(RedirectResponse::create(htmlspecialchars_decode($url), $http_response_code));
    }

    /**
     * Ensure that the **$url** path you want to enforce matches the current path.
     *
     * @param string $url      Either the full url, or just the path.
     * @param int    $redirect The status code.
     *
     * @example
     *
     * ```php
     * echo $page->url['path']; // 'details/former-title-1'
     * $page->enforce('details/current-title-1');
     * echo $page->url['path']; // 'details/current-title-1'
     * ```
     */
    public function enforce($url, $redirect = 301)
    {
        list($url, $path, $suffix, $query) = $this->formatLocalPath($url, 'array');
        $compare = $this->url['path'];
        if (!empty($path)) {
            $compare .= $this->url['suffix']; // to redirect 'index' to ''
        }
        if (!in_array($suffix, $this->url['html'])) { // images, css files, etc.
            if ($path.$suffix != $this->url['path']) {
                return $this->eject($this->url['base'].$path.$suffix, $redirect);
            }
        } elseif ($path.$suffix != $compare) {
            if (strpos($url, $this->url['base']) === 0) {
                return $this->eject($this->url['base'].$path.$suffix.$this->url['query'], $redirect);
            }
        }
    }

    /**
     * Determine the directory your **$class** resides in, so that you can refer to it in ``$page->dir()`` and ``$page->url()``.
     *
     * @param string $class The ``__CLASS__`` you want to reference.
     *
     * @return string A slightly modified **$class** name.
     *
     * @example
     *
     * ```php
     * $name = $page->dirname(__CLASS__);
     * echo $page->dir($name); // The directory your __CLASS__ resides in
     * ```
     */
    public function dirname($class)
    {
        $class = trim(str_replace('/', '\\', $class), '\\');
        $name = str_replace('\\', '-', strtolower($class));
        if (!isset($this->dir[$name]) && class_exists($class)) {
            $ref = new \ReflectionClass($class);
            $this->dir('set', $name, dirname($ref->getFileName()));
            unset($ref);
        }

        return (isset($this->dir[$name])) ? $name : null;
    }

    /**
     * Get the absolute path to a directory, including the trailing slash '__/__'.
     *
     * @param string $folder The folder path(s) after ``$page->dir['page']``.  Every arg you include in the method will be another folder path.  If you want the directory to be relative to ``$name = $page->dirname(__CLASS__)``, then set the first parameter to ``$name``, and the subsequent arguments (folders) relative to it.  Any empty args will be ignored.
     *
     * @return string
     *
     * @example
     *
     * ```php
     * $page->dir(); // returns $page->dir['page'] - the one where your website resides
     * $page->dir('folder', 'path'); // $page->dir['page'].'folder/path/'
     * $page->dir('folder', '', 'path'); // $page->dir['page'].'folder/path/'
     * $page->dir('folder/path'); // $page->dir['page'].'folder/path/'
     * $page->dir('/folder//path///'); // $page->dir['page'].'folder/path/'
     * $page->dir($page->dir['page'].'folder/path'); // $page->dir['page'].'folder/path/'
     * $page->dir($page->dir['page'], '/folder/path/'); // $page->dir['page'].'folder/path/'
     * $page->dir('page', '/folder', '/path/'); // $page->dir['page'].'folder/path/'
     * $page->dir('base', 'folder/path'); // $page->dir['page'].'folder/path/' - 'base' is an alias for 'page'
     *
     * $name = $page->dirname(__CLASS__); // $page->dir[$name] is now the directory where the __CLASS__ resides
     * $page->dir($name, 'folder', 'path'); // $page->dir[$name].'folder/path/'
     * ```
     */
    public function dir($folder = null)
    {
        $folders = func_get_args();
        if ($folder == 'set') {
            list($folder, $name, $dir) = $folders;
            $this->dir[$name] = rtrim(str_replace('\\', '/', $dir), '/').'/';
            if (strpos($this->dir[$name], $this->dir['base']) !== 0) {
                $this->dir['base'] = $this->commonDir(array($this->dir[$name], $this->dir['base']));
            }

            return $this->dir[$name]; // all nicely formatted
        }
        $dir = $this->dir['page'];
        if ($folder == 'base') {
            array_shift($folders);
        } elseif (isset($this->dir[$folder])) {
            $dir = $this->dir[array_shift($folders)];
        } elseif (strpos($folder, $this->dir['base']) === 0) {
            $dir = rtrim(array_shift($folders), '/').'/';
        }
        if (empty($folders)) {
            return $dir;
        }
        $folders = array_filter(array_map(function ($path) {
            return trim($path, '/');
        }, $folders));

        return $dir.implode('/', $folders).'/';
    }

    /**
     * Get the absolute path to a file.  This method works exactly the same as ``$page->dir(...)``, but doesn't include the trailing slash as it should be pointing to a file.
     *
     * @param string $name Of the folder(s) and file.  Can span multiple arguments.
     *
     * @return string
     *
     * @example
     *
     * ```php
     * $page->file('image.jpg'); // $page->dir['page'].'image.jpg'
     * $page->file('files', 'suffixless'); // $page->dir['page'].'files/suffixless'
     *
     * $name = $page->dirname(__CLASS__); // $page->dir[$name] is now the directory where the __CLASS__ resides
     * $page->file($name, 'folder/file.php'); // $page->dir[$name].'folder/file.php'
     * ```
     */
    public function file($name)
    {
        return rtrim(call_user_func_array(array($this, 'dir'), func_get_args()), '/');
    }

    /**
     * Get a url path (with trailing slash '__/__') that you can add to and work with, as opposed to ``$page->url()`` that always returns the ``$page->url['suffix']`` with it.
     *
     * @param string $url Every argument given becomes part of the path, the same as ``$page->dir()`` only with a url.  The first argument can include the ``$page->url['base']``, be a ``$page->dirname()``, a reference that you ``$page->url('set', ...)``ed, or just be relative to the ``$page->url['base']``.
     *
     * @return string
     *
     * @example
     *
     * ```php
     * $page->path('folder'); // $page->url['base'].'folder/'
     * $page->path('base', 'folder'); // $page->url['base'].'folder/'
     * $page->path('page', 'folder'); // $page->url['base'].'page/folder/'
     * $page->path($page->url['base'], 'folder'); // $page->url['base'].'folder/'
     * ```
     */
    public function path($url = null)
    {
        $paths = func_get_args();
        $base_url = $this->url['base'];
        if ($url == 'base') {
            array_shift($paths);
        } elseif (isset($this->url['set'][$url])) {
            $base_url = $this->url['set'][array_shift($paths)];
        } elseif (strpos($url, $this->url['base']) === 0) {
            $base_url = rtrim(array_shift($paths), '/').'/';
        }
        if (empty($paths)) {
            return $base_url;
        }
        $paths = array_filter(array_map(function ($path) {
            return trim($path, '/');
        }, $paths));

        return $base_url.implode('/', $paths).'/';
    }

    /**
     * Create a url, and/or manipulate it's query string and fragment.
     *
     * @param string $action What you want this method to do.  The options are:
     *
     *   - '' (blank) - To get the ``$page->url['full']``.
     *   - '**params**' - To get an associative array of the **$url** query string.
     *   - '**delete**' - To remove a param (or more) from the **$url** query string.
     *   - '**add**' - To add a param (or more) to the **$url** query string.
     *   - '**set**' - To ``$page->url['set'][$url] = $key`` that can be referred to here, and in ``$page->path($url)``.
     *   - '**...**' - Anything else you do will create a url string in the same manner as ``$page->path()``, only with the ``$page->url['suffix']`` included.
     *
     * @param string       $url   If left empty then the ``$page->url['full']`` will be used, otherwise we will ``htmlspecialchars_decode()`` whatever you do give us.  If ``$action == 'set'`` then this is the shortcut name used to reference the url **$key**.
     * @param string|array $key   Either the url you are '**set**'ing for future reference, or the paramter(s) you want to '**add**' or '**delete**' from the **$url**.
     * @param string       $value If ``$action == 'add' && !is_array($key)``, then this is the **$key**'s value.   Otherwise this argument means nothing.
     *
     * @return string|array The ``htmlspecialchars($url)`` string with the ``$page->url['suffix']`` included for local html pages:
     *
     * - If ``$action == 'delete'`` and:
     *   - If ``$key == '#'`` the fragment will be removed.
     *   - If ``$key == '?'`` the entire query string will be removed.
     *   - If ``is_string($key)`` it will be removed from the query string.
     *   - If ``is_array($key)`` then every value will be removed from the query string.
     * - If ``$action == 'add'`` and:
     *   - If ``is_string($key)`` it's **$value** will be added to the query string.
     *   - If ``is_array($key)`` then every key and value will be added to the query string.
     *
     * Except for:
     *
     * - If ``$action == 'params'`` then the **$url** query string is returned as an associative array.
     *
     * @example
     *
     * ```php
     * // Get the current url
     * $page->url(); // $page->url['full']
     *
     * // 'base' is a reference to the ``$page->url['base']`` url
     * $page->url('base', 'path'); // http://example.com/path.html
     *
     * // 'page' is a reference to the ``$page->dir['page']`` directory
     * $page->url('page', 'path'); // http://example.com/page/path.html
     *
     * // You can include the base url
     * $page->url($page->url['base'], 'path'); // http://example.com/path.html
     *
     * // Or just skip it entirely
     * $page->url('path'); // http://example.com/path.html
     *
     * // The $page->url['suffix'] is enforced for local urls
     * $page->url('path.php'); // http://example.com/path.html
     *
     * // The suffix stays for non-html pages
     * $page->url('page', 'styles.css'); // http://example.com/page/styles.css
     *
     * // Any suffix goes for external urls
     * $page->url('http://another.com', 'path.php'); // http://another.com/path.php
     *
     * // The top level index page is removed
     * $page->url('index.html'); // http://example.com/
     *
     * // For other levels it is not
     * $page->url('page', 'index.php'); // http://example.com/page/index.html
     *
     * // Set a url shortcut
     * $page->url('set', 'folder', 'http://example.com/path/to/folder');
     * $page->url('folder'); // http://example.com/path/to/folder.html
     * $page->url('folder', '//hierarchy.php/'); // http://example.com/path/to/folder/hierarchy.html
     *
     * // Get the query params
     * $page->url('params', 'http://example.com/?foo=bar'); // array('foo' => 'bar')
     *
     * // Add to the query params
     * $url = $page->url('add', 'http://example.com', array('key' => 'value', 'test' => 'string')); // http://example.com/?key=value&amp;test=string
     * $page->url('add', $url, 'one', 'more'); // http://example.com/?key=value&amp;test=string&amp;one=more
     *
     * // Delete from the query params
     * $page->url('delete', $url, 'key'); // http://example.com/?test=string
     * $page->url('delete', $url, '?'); // http://example.com/
     *
     * // Manipulate fragments
     * $fragment = $page->url('add', 'http://example.com', '#', 'fragment'); // http://example.com/#fragment
     * $page->url('delete', $fragment, '#'); // http://example.com/
     * ```
     */
    public function url($action = '', $url = '', $key = '', $value = null)
    {
        if (empty($action)) {
            return htmlspecialchars($this->url['full']);
        } elseif ($action == 'set') {
            return $this->url['set'][$url] = $key;
        } elseif (!in_array($action, array('params', 'add', 'delete'))) {
            $base_url = (is_array($action) && isset($action['url'])) ? $action['url'] : implode('/', (array) $action);
            if (isset($this->url['set'][$base_url])) {
                $base_url = $this->url['set'][$base_url];
            } elseif (isset($this->dir[$base_url])) {
                $base_url = $this->url['base'].$base_url.'/';
            }
            // get an array of all url $segments after the $base_url
            $segments = array_filter(array_slice(func_get_args(), 1));
            if (($num = count($segments)) > 0) {
                // trim each $segments slashes
                $segments = array_map('trim', $segments, array_fill(0, $num, '/'));
            }
            array_unshift($segments, rtrim($base_url, '/\\'));

            return htmlspecialchars($this->formatLocalPath(htmlspecialchars_decode(implode('/', $segments))));
        }
        $url = (!empty($url)) ? htmlspecialchars_decode($url) : $this->url['full'];
        $base = preg_replace('/[\?#].*$/', '', $url); // just the url and path
        $url = parse_url($url);
        if (!isset($url['query'])) {
            $params = array();
        } else {
            parse_str($url['query'], $params);
        }
        $fragment = (!empty($url['fragment'])) ? '#'.$url['fragment'] : '';
        switch ($action) {
            case 'params':
                return $params;
                break;
            case 'add':
                if ($key == '#') {
                    $fragment = '#'.urlencode($value);
                } else {
                    $params = array_merge($params, (is_array($key) ? $key : array($key => $value)));
                }
                break;
            case 'delete':
                if ($key == '?') {
                    $params = array();
                } elseif ($key == '#') {
                    $fragment = '';
                } else {
                    foreach ((array) $key as $value) {
                        unset($params[$value]);
                    }
                }
                break;
        }
        $query = (!empty($params)) ? '?'.http_build_query($params) : '';

        return htmlspecialchars($this->formatLocalPath($base.$query.$fragment));
    }

    /**
     * A shortcut for ``$page->request->query->get($key, $default)``.
     *
     * @param string $key     The ``$_GET[$key]``.
     * @param mixed  $default The default value to return if the ``$_GET[$key]`` doesn't exits.
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return $this->request->query->get($key, $default);
    }

    /**
     * A shortcut for ``$page->request->request->get($key, $default)``.
     *
     * @param string $key     The ``$_POST[$key]``.
     * @param mixed  $default The default value to return if the ``$_POST[$key]`` doesn't exits.
     *
     * @return mixed
     */
    public function post($key, $default = null)
    {
        return $this->request->request->get($key, $default);
    }

    /**
     * Map the current **$route** to the paths you provide using the [AltoRouter](http://altorouter.com/).
     *
     * @param array $map An ``array($route => $target, ...)`` or just ``array($route, ...)``, or any combination thereof.  The **$target** could be a php file, a method name, or whatever you want that will help you to determine what comes next.  A **$route** is what you are expecting the url path to look like, and mapping them to variables that you can actually work with:
     *
     * - '**folder**' will match *'folder'*.
     * - '**users/[register|sign_in|forgot_password:action]**' will match *'users/sign_in'* with ``$params['action'] = 'sign_in'``.
     * - '**users/[i:id]**' will match *'users/12'* with ``$params['id'] = 12``.
     *
     * Notice that the '**i**' in '**[i:id]**' will match an integer and assign the paramter '**id**' to the value of '**i**'.  You can set or override these shortcuts in the **$types** below.  The defaults are:
     *
     * - '__*__' - Match all request URIs.
     * - '__[i]__' - Match an integer.
     * - '__[i:id]__' - Match an integer as '**id**'.
     * - '__[a:action]__' - Match alphanumeric characters as '**action**'.
     * - '__[h:key]__' - Match hexadecimal characters as '**key**'.
     * - '__[:action]__' - Match anything up to the next backslash '__/__', or end of the URI as '**action**'.
     * - '__[create|edit:action]__' - Match either *'create'* or *'edit'* as '**action**'.
     * - '__[*]__' - Catch all (lazy).
     * - '__[*:trailing]__' - Catch all as '**trailing**' (lazy).
     * - '__[**:trailing]__' - Catch all (possessive - will match the rest of the URI).
     * - '__.[:format]?__' - Match an optional parameter as '**format**'.
     *   - When you put a question mark '__?__' after the block (making it optional), a backslash '__/__' or dot '__.__' before the block is also optional.
     *
     * A few more examples for the road:
     *
     * - '__posts/[*:title]-[i:id]__' - Matches *'posts/this-is-a-title-123'*.
     * - '__posts/[create|edit:action]?/[i:id]?__' - Matches *'posts'*, *'posts/123'*, *'posts/create'*, and *'posts/edit/123'*.
     * - '__output.[xml|json:format]?__' - Matches *'output'*, *'output.xml'*, and *'output.json'*.
     * - '__@\.(json|csv)$__' - Matches all requests that end with *'.json'* or *'.csv'*.
     * - '__!@^admin/__' - Matches all requests that **do not** start with *'admin/'*.
     * - '__[:controller]?/[:action]?__' - Matches the typical controller/action format.
     * - '__[:controller]?/[:method]?/[**:uri]?__' - There's nothing that this won't cover.
     *
     * @param mixed $route If you don't want to use the ``$page->url['route']``, then set this value to the path you want to match against.
     * @param array $types If you want to add to (or override) the shortcut regex's, then you can add them here.  The defaults are:
     *
     * ```php
     * $types = array(
     *     'i'  => '[0-9]++', // integer
     *     'a'  => '[0-9A-Za-z]++', // alphanumeric
     *     'h'  => '[0-9A-Fa-f]++', // hexadecimal
     *     '*'  => '.+?', // anything (lazy)
     *     '**' => '.++', // anything (possessive)
     *     ''   => '[^/\.]++', // not a slash (/) or period (.)
     * );
     * ```
     *
     * @return mixed Either ``false`` if nothing matches, or an array of information with the following keys:
     *
     * - '**target**' => The **$map** route we successfully matched.  If the route is a key, then this is it's value.  Otherwise it is the route itself.
     * - '**params**' => All of the params we matched to the successful route.
     * - '**method**' => Either '**POST**' or '**GET**'.
     *
     * @example
     *
     * ```php
     * if ($route = $page->routes(array(
     *     '' => 'index.php',
     *     'listings' => 'listings.php',
     *     'details/[*:title]-[i:id]' => 'details.php',
     * ))) {
     *     include $route['target'];
     * } else {
     *     $page->send(404);
     * }
     * ```
     */
    public function routes(array $map, $route = null, array $types = array())
    {
        $path = (is_null($route)) ? $this->url['route'] : $route;
        $routes = array();
        foreach ($map as $route => $target) {
            if (is_numeric($route)) {
                $route = $target;
            }
            $routes[] = array($this->url['method'], ltrim($route, '/'), $target);
        }
        $router = new AltoRouter($routes, '', $types);
        if ($match = $router->match(ltrim($path, '/'), $this->url['method'])) {
            unset($match['name']);
        }

        return $match;
    }

    /**
     * Generate an HTML tag programatically.
     *
     * @param string $name       The tag's name eg. *'div'*.
     * @param array  $attributes An ``array($key => $value, ...)`` of attributes.
     *
     *   - If the **$key** is numeric (ie. not set) then the attribute is it's **$value** (eg. *'multiple'* or *'selected'*), and we'll delete any **$key** of the same name (eg. *multiple="multiple"* or *selected="selected"*).
     *   - If **$value** is an array (a good idea for classes) then we remove any duplicate or empty values, and implode them with a space in beween.
     *   - If the **$value** is an empty string and not ``null``, we ignore the attribute entirely.
     *
     * @param string $content    All args supplied after the **$attributes** are stripped of any empty values, and ``implode(' ', ...)``ed.
     *
     * @return string An opening HTML **$tag** with it's **$attributes**.  If **$content** is supplied then we add that, and a closing html tag too.
     *
     * @example
     *
     * ```php
     * echo $page->tag('meta', array('name'=>'description', 'content'=>'')); // <meta name="description">
     *
     * echo $page->tag('p', array('class'=>'lead'), 'Body', 'copy'); // <p class="lead">Body copy</p>
     * ```
     */
    public function tag($name, array $attributes, $content = null)
    {
        $args = func_get_args();
        $tag = array_shift($args);
        $attributes = array_shift($args);
        foreach ($attributes as $key => $value) {
            if (is_array($value)) {
                $value = implode(' ', array_unique(array_filter($value)));
            }
            if ($value === '') {
                unset($attributes[$key]);
            } elseif (!is_numeric($key)) {
                $attributes[$key] = $key.'="'.$value.'"';
            } elseif (isset($attributes[$value])) {
                unset($attributes[$key]);
            }
        }
        $attributes = (!empty($attributes)) ? ' '.implode(' ', $attributes) : '';
        $html = '<'.$tag.$attributes.'>';
        if (!empty($args)) {
            $html .= implode(' ', array_filter($args));
            $html .= '</'.strstr($tag.' ', ' ', true).'>';
        }

        return $html;
    }

    /**
     * Place a ``<meta>`` tag in your ``<head>``.
     *
     * @param mixed $args Either an array of attributes, or a string that gets inserted as is.
     *
     * @example
     *
     * ```php
     * $page->meta('name="author" content="name"'); // or ...
     *
     * $page->meta(array('name'=>'author', 'content'=>'name'));
     * ```
     */
    public function meta($args)
    {
        if (is_string($args)) {
            $this->data('meta', "<meta {$args}>", false);
        } else {
            $this->data('meta', $this->tag('meta', $args), false);
        }
    }

    /**
     * Add ``<link>``(s) to your ``<head>``.
     *
     * @param string|array $link    A single string, or an array of '**.ico**', '**.css**', and '**.js**' files.  You can also include ``<meta>``, ``<link>``, ``<style>``, and ``<script>`` tags here, and they will be placed appropriately.
     * @param mixed        $prepend If anything but ``false`` (I like to use '**prepend**'), then everything you just included will be prepended to the stack, as opposed to being inserted after all of the other links that have gone before.
     *
     * @example
     *
     * ```php
     * $page->link(array(
     *     $page->url('images/favicon.ico'),
     *     $page->url('css/stylesheet.css'),
     *     $page->url('js/functions.js'),
     * ));
     * ```
     */
    public function link($link, $prepend = false)
    {
        $link = (array) $link;
        if ($prepend !== false) {
            $link = array_reverse($link); // so they are added in the correct order
        }
        foreach ($link as $file) {
            $frag = (strpos($file, '<') === false) ? strstr($file, '#') : '';
            if (!empty($frag)) {
                $file = substr($file, 0, -strlen($frag));
            }
            if (preg_match('/\.(ico|css|js)$/i', $file)) {
                $split = strrpos($file, '.');
                $ext = substr($file, $split + 1);
                $name = substr($file, 0, $split);
                switch ($ext) {
                    case 'ico':
                        $this->data['ico'] = $file.$frag;
                        break;
                    case 'css':
                        $this->data('css', $file.$frag, $prepend);
                        break;
                    case 'js':
                        $this->data('js', $file.$frag, $prepend);
                        break;
                }
            } elseif (preg_match('/^\s*<\s*(?P<tag>meta|link|style|script)/i', $file, $match)) {
                $this->data($match['tag'], $file, $prepend);
            } else {
                $this->data('other', $file, $prepend);
            }
        }
    }

    /**
     * Enclose **$css** within ``<style>`` tags and place it in the ``<head>`` of your page.
     *
     * @param string|array $css
     *
     * @example
     *
     * ```php
     * $page->style('body { background-color:red; color:black; }');
     * $page->style(array('body { background-color:red; color:black; }'));
     * $page->style(array('body' => 'background-color:red; color:black;'));
     * $page->style(array('body' => array('background-color:red;', 'color:black;')));
     * ```
     */
    public function style($css)
    {
        if (is_array($css)) {
            foreach ($css as $tag => $rules) {
                if (is_array($rules)) {
                    $css[$tag] = $tag.' { '.implode(' ', $rules).' }';
                } elseif (!is_numeric($tag)) {
                    $css[$tag] = $tag.' { '.$rules.' }';
                }
            }
            $css = implode("\n", $css);
        }
        $this->link('<style>'.(strpos($css, "\n") ? "\n".$this->indent($css)."\n\t" : trim($css)).'</style>');
    }

    /**
     * Enclose **$javascript** within ``<script>`` tags and place it at the bottom of your page.
     *
     * @param string|array $javascript
     *
     * @example
     *
     * ```php
     * $page->script('alert("Hello World");');
     * ```
     */
    public function script($javascript)
    {
        if (is_array($javascript)) {
            $javascript = implode("\n", $javascript);
        }
        $this->link('<script>'.(strpos($javascript, "\n") ? "\n".$this->indent($javascript)."\n\t" : trim($javascript)).'</script>');
    }

    /**
     * Places all of your jQuery **$code** into one ``$(document).ready(function(){...})`` at the end of your page.
     *
     * @param string|array $code
     *
     * @example
     *
     * ```php
     * $page->jquery('$("button.continue").html("Next Step...");');
     * ```
     */
    public function jquery($code)
    {
        $this->data['jquery'][] = (is_array($code)) ? implode("\n", $code) : $code;
    }

    /**
     * We use this in the Form component to avoid input name collisions.  We use it in the Bootstrap component for accordions, carousels, and the like.  The problem with just incrementing a number and adding it onto something else is that css and jQuery don't like numbered id's.  So we use roman numerals instead, and that solves the problem for us.
     *
     * @param string $prefix What you would like to come before the roman numeral.  This is not really needed, but when you are looking at your source code, it helps to know what you are looking at.
     *
     * @return string A unique id.
     *
     * @example
     *
     * ```php
     * // Assuming this method has not been called before:
     * echo $page->id('name'); // nameI
     * echo $page->id('unique'); // uniqueII
     * echo $page->id('unique'); // uniqueIII
     * ```
     */
    public function id($prefix = '')
    {
        static $id = 0;
        ++$id;
        $result = '';
        $lookup = array('M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1);
        $number = $id;
        if ($number < 100) {
            $lookup = array_slice($lookup, 4);
        }
        foreach ($lookup as $roman => $value) {
            $matches = intval($number / $value);
            $result .= str_repeat($roman, $matches);
            $number = $number % $value;
        }

        return $prefix.$result;
    }

    /**
     * Map a **$path** to a folder and **$file** in **$dir** so that you can ``$page->load()`` it.  This will essentially make your **$file** a controller, if you subscribe to the MVC pattern.
     *
     * @param string $dir  The base directory whose folders you want to map to a **$path**.
     * @param string $path The ``$page->url['path']``, or whatever else you want to use.
     * @param string $file The filename that must be in the folder to make a match.
     *
     * @return array|null If we have a match then we will return an array with the following info:
     *
     * - '**file**' => The file path for which we made a match.
     * - '**dir**' => The dir in which the file resides (with trailing slash).
     * - '**assets**' => The url path (with trailing slash) that corresponds to the dir for including images and other files.
     * - '**url**' => The url path for linking to other pages that are relative to this dir.
     * - '**folder**' => The portion of your **$path** that got us to your **$file**.
     * - '**route**' => The remaining portion of your **$path** that your **$file** will have to figure out what to do with next.
     *
     * @example
     *
     * ```php
     * // Assuming ``$dir = $page->dir('folders')``, and you have a $dir.'users/index.php' file:
     * if ($params = $page->folder($dir, 'users/sign_in')) {
     *     $html = $page->load($params['file'], $params);
     *     // $params = array(
     *     //     'file' => $dir.'users/index.php',
     *     //     'dir' => $dir.'users/',
     *     //     'assets' => $page->url['base'].'page/folders/users/',
     *     //     'url' => $page->url['base'].'folders/users/',
     *     //     'folder' => 'users/',
     *     //     'route' => '/sign_in',
     *     // );
     * }
     * ```
     */
    public function folder($dir, $path, $file = 'index.php')
    {
        $dir = $this->dir($dir);
        if (strpos($dir, $this->dir['page']) === 0 && is_dir($dir)) {
            $folder = substr($dir, strlen($this->dir['page']));
            $paths = array();
            $path = preg_replace('/[^'.$this->url['chars'].'\.\/]/', '', strtolower($path));
            foreach (explode('/', $path) as $dir) {
                if (false !== $extension = strstr($dir, '.')) {
                    $dir = substr($dir, 0, -strlen($extension)); // remove file extension
                }
                if (!empty($dir)) {
                    $paths[] = $dir; // remove empty $paths
                }
            }
            $paths = array_diff($paths, array('index')); // remove any reference to 'index'
            $path = '/'.implode('/', $paths); // includes leading slash and corresponds with $paths
            if ($extension) {
                $path .= $extension;
            }
            while (!empty($paths)) {
                $route = implode('/', $paths).'/'; // includes trailing slash
                if (is_file($this->file($folder, $route, $file))) {
                    return array(
                        'file' => $this->file($folder, $route, $file),
                        'dir' => $this->dir($folder, $route),
                        'assets' => $this->url['base'].'page/'.$folder.$route,
                        'url' => $this->url['base'].$folder.$route,
                        'folder' => substr($path, 1, strlen($route)), // remove leading slash
                        'route' => substr($path, strlen($route)), // remove trailing slash
                    );
                }
                array_pop($paths);
            }
            if (is_file($this->file($folder, $file))) {
                return array(
                    'file' => $this->file($folder, $file),
                    'dir' => $this->dir($folder),
                    'assets' => $this->url['base'].'page/'.$folder,
                    'url' => $this->url['base'].$folder,
                    'folder' => '',
                    'route' => $path,
                );
            }
        }

        return;
    }

    /**
     * Passes **$params** to a **$file**, and returns the output.
     *
     * @param string $file   The file you want to ``include``.
     * @param array  $params Variables you would like your file to receive.
     *
     * @return mixed Whatever you ``$export``ed (could be anything), or a string of all that you ``echo``ed.
     *
     * @example
     *
     * ```php
     * $file = $page->file('folders/users/index.php');
     *
     * // Assuming $file has the following code:
     *
     * <?php
     * extract($params);
     * $export = $action.' Users';
     *
     * // Loading it like this would return 'Sign In Users'
     *
     * echo $page->load($file, array('action'=>'Sign In'));
     * ```
     */
    public function load($file, array $params = array())
    {
        if (!is_file($file)) {
            return;
        }
        foreach ($params as $key => $value) {
            if (is_numeric($key) && is_string($value)) {
                $params[$value] = true; // makes it possible to extract(), and easier to check if isset()
                unset($params[$key]);
            }
        }
        $export = '';
        ob_start();
        include $file;
        $html = ob_get_clean();

        return ($export !== '') ? $export : $html;
    }

    /**
     * Enables you to modify just about anything throughout the creation process of your page.
     *
     * @param string $section Must be one of:
     *
     *   - '**metadata**' - The ``<title>`` and ``<meta>`` data that we include right after the ``<head>`` tag.
     *   - '**css**' - An array of stylesheet link urls.
     *   - '**styles**' - The ``<link>``'s and ``<style>``'s that we include just before the ``</head>`` tag.
     *   - '**html**' - The ``$page->display($content)`` that comes right after the ``<body>`` tag, and just before the javascript we include.
     *   - '**javascript**' - An array of javascript urls.
     *   - '**scripts**' - The ``<script>``'s and jQuery code that we include just before the ``</body>`` tag.
     *   - '**head**' - Everything between the ``<head>`` ... ``</head>`` tags.
     *   - '**body**' - Everything between the ``<body>`` ... ``</body>`` tags.
     *   - '**page**' - The entire page from top to bottom.
     *   - '**response**' - The final Symfony Response object if you ``$page->send()`` it.
     *
     * @param callable $function If filtering the '**response**' then we'll pass the ``$page`` (this class instance), ``$response`` (what you are filtering), and ``$type`` ('html', 'json', 'redirect', or ``$page->url['format']``) of content that you are dealing with.
     *
     * @param array    $params
     *                           - If ``$section == 'response'``
     *                             - These are the page *type* and response *code* conditions that the response must meet in order to be processed.
     *                           - Otherwise:
     *                             - **$params** is an array of arguments that are passed to your **$function**.
     *                             - '**this**' must be listed as one of the **$params**.  It is the **$section** as currently constituted, and for which your filter would like to operate on.  If you don't return anything, then that section will magically disappear.
     * @param int      $order    The level of importance (or priority) that this filter should receive.  The default is 10.  All filters are called in the order specified here.
     *
     * @example
     *
     * ```php
     * $page->filter('response', function ($page, $response, $type) {
     *     return $response->setContent($type);
     * }, array('html', 200));
     *
     * $page->filter('response', function ($page, $response) {
     *     return $response->setContent('json');
     * }, array('json'));
     *
     * $page->filter('response', function ($page, $response) {
     *     return $response->setContent(404);
     * }, array(404));
     *
     * $page->filter('body', function ($prepend, $html, $append) {
     *     return implode(' ', array($prepend, $html, $append));
     * }, array('facebook_like_button', 'this', 'tracking_code');
     * ```
     *
     * @throws \LogicException If something was not set up right.
     */
    public function filter($section, callable $function, array $params = array('this'), $order = 10)
    {
        if ($section == 'response') {
            foreach ($params as $key => $value) {
                if (empty($value) || $value == 'this') {
                    unset($params[$key]);
                }
            }
            $key = false;
        } elseif (!in_array($section, array('metadata', 'css', 'styles', 'html', 'javascript', 'scripts', 'head', 'body', 'page'))) {
            $error = "'{$section}' cannot be filtered";
        } else {
            if (false === $key = array_search('this', $params)) {
                $error = "'this' must be listed in the \$params so that we can give you something to filter";
            }
        }
        if (isset($error)) {
            throw new \LogicException($error);
        }
        $this->filters[$section][] = array('function' => $function, 'params' => $params, 'order' => $order, 'key' => $key);
    }

    /**
     * Piece together the HTML Page from top to bottom.
     *
     * @param string $content
     *
     * @return string
     */
    public function display($content)
    {
        $html = array();
        if (preg_match('/<\s*!doctype\s.*>/i', $content, $match)) {
            $content = mb_substr(mb_strstr($content, $match[0]), mb_strlen($match[0]));
            $html[] = $match[0];
        } else {
            $html[] = $this->html['doctype'];
        }
        $pattern = '/(<\s*(%s)[^>]*>)([\s\S]*)<\s*\/\s*\2\s*>/i';
        if (preg_match(sprintf($pattern, 'html'), $content, $match)) {
            $html[] = $match[1];
            $content = $match[3];
        } else {
            $html[] = '<html lang="'.$this->html['language'].'">';
        }
        if (preg_match(sprintf($pattern.'U', 'head'), $content, $match)) {
            $content = mb_substr(mb_strstr($content, $match[0]), mb_strlen($match[0]));
            $html[] = $match[1];
            $head = array(
                $this->process('metadata', "\t".implode("\n\t", $this->metadata($match[3]))),
                $match[3],
                $this->process('styles', "\t".implode("\n\t", $this->styles())),
            );
        } else {
            $html[] = '<head>';
            $head = array(
                $this->process('metadata', "\t".implode("\n\t", $this->metadata())),
                $this->process('styles', "\t".implode("\n\t", $this->styles())),
            );
        }
        $html[] = $this->process('head', implode("\n", $head));
        $html[] = '</head>';
        if (preg_match(sprintf($pattern, 'body'), $content, $match)) {
            $html[] = $match[1];
            $content = $match[3];
        } else {
            $html[] = (!empty($this->html['body'])) ? '<body '.$this->html['body'].'>' : '<body>';
        }
        $html[] = $this->process('body', implode("\n", array(
            $this->process('html', $content),
            $this->process('scripts', "\t".implode("\n\t", $this->scripts())),
        )));
        $html[] = '</body>';
        $html[] = '</html>';

        return $this->process('page', implode("\n", $html));
    }

    /**
     * Sends a [Symfony Response](http://symfony.com/doc/current/components/http_foundation.html#response) object, and allows you to further process and ``$page->filter()`` it.
     *
     * @param object|string|int $response Either a Symfony Response object, the content of your response, or just a quick status code eg. ``$page->send(404)``.
     * @param int               $status   The status code if your ``$response`` is a content string.
     * @param array             $headers  A headers array if your ``response`` is a content string.
     *
     * @return object If you set ``Page::html(array('testing'=>true))`` then we will return the Symfony Response object so that it doesn't halt your script, otherwise it will send the Response and exit the page.
     *
     * @example
     *
     * ```php
     * if ($html = $page->load($page->file('index.php'))) {
     *     $page->send($page->display($html));
     * } else {
     *     $page->send(404);
     * }
     * ```
     */
    public function send($response = '', $status = 200, array $headers = array())
    {
        if (!$response instanceof Response) {
            if (func_num_args() == 1 && is_numeric($response)) {
                $status = (int) $response;
                $response = '';
            }
            $response = new Response($response, $status, $headers);
        }
        $status = $response->getStatusCode();
        if ($response instanceof RedirectResponse) {
            $type = 'redirect';
        } elseif ($response instanceof JsonResponse) {
            $type = 'json';
        } elseif (null === $type = $response->headers->get('Content-Type')) {
            $type = ($status == 304) ? $this->url['format'] : 'html';
        } elseif (stripos($type, 'html') !== false) {
            $type = 'html';
        }
        $this->process('response', $response, $status, $type);
        $response = $response->prepare($this->request)->send();

        return ($this->testing === false) ? exit : $response;
    }

    /**
     * Creates and sends a [Symfony JsonResponse](http://symfony.com/doc/current/components/http_foundation.html#creating-a-json-response) object.
     *
     * @param mixed $data   The response data.
     * @param int   $status The response status code.
     */
    public function sendJson($data = '', $status = 200)
    {
        return $this->send(JsonResponse::create($data, $status));
    }

    /**
     * An internal method we use that comes in handy elsewhere as well.
     * 
     * @param array $files An array of files that share a common directory somewhere.
     * 
     * @return string The common directory (with trailing slash) shared amongst your $files.
     */
    public function commonDir(array $files)
    {
        $files = array_values($files);
        $cut = 0;
        $count = count($files);
        $shortest = min(array_map('mb_strlen', $files));
        while ($cut < $shortest) {
            $char = $files[0][$cut];
            for ($i = 1; $i < $count; ++$i) {
                if ($files[$i][$cut] !== $char) {
                    break 2;
                }
            }
            ++$cut;
        }
        $dir = mb_substr($files[0], 0, $cut);
        if (false !== $slash = mb_strrpos($dir, '/')) {
            $dir = mb_substr($dir, 0, $slash + 1);
        } elseif (false !== $slash = mb_strrpos($dir, '\\')) {
            $dir = mb_substr($dir, 0, $slash + 1);
        } else {
            $dir = ''; // no common folder
        }

        return $dir; // with trailing slash (if any)
    }

    protected function process($section, $param, $code = 0, $type = '')
    {
        // Used in $this->send(), $this->display(), $this->styles(), and $this->scripts()
        if (!isset($this->filters[$section])) {
            return $param;
        }
        usort($this->filters[$section], function ($a, $b) {
            return $a['order'] - $b['order'];
        });
        foreach ($this->filters[$section] as $key => $filter) {
            if ($section == 'response') {
                foreach ($filter['params'] as $response) {
                    if (is_numeric($response)) {
                        if ($response != $code) {
                            continue 2;
                        }
                    } elseif (stripos($type, $response) === false) {
                        continue 2;
                    }
                }
                call_user_func($filter['function'], $this, $param, $type); // $page, $response, $type
            } else {
                $filter['params'][$filter['key']] = $param; // replaces 'this' with our content
                $param = call_user_func_array($filter['function'], $filter['params']);
            }
            unset($this->filters[$section][$key]);
        }

        return $param;
    }

    protected function data($type, $value, $prepend)
    {
        // Used in $this->meta() and $this->link()
        if ($prepend !== false) {
            if (!isset($this->data[$type])) {
                $this->data[$type] = array();
            }
            array_unshift($this->data[$type], $value);
        } else {
            $this->data[$type][] = $value;
        }
    }

    protected function metadata($head = null)
    {
        // Used in $this->display()
        $metadata = array();
        if (empty($head) || !preg_match('/<\s*meta\s(?=[^>]*?\b(charset)\s*=)/i', $head)) {
            $metadata[] = '<meta charset="'.$this->html['charset'].'">';
        }
        if (empty($head) || !preg_match('/(<\s*title\s*>)/i', $head)) {
            $metadata[] = '<title>'.trim($this->html['title']).'</title>';
        }
        $meta_names = array();
        if ($head) {
            // http://php.net/manual/en/function.get-meta-tags.php#117766
            $pattern = <<<'EOT'
                <\s*meta\s(?=[^>]*?
                \b(?:name)\s*=\s*
                    (?|"\s*([^"]*?)\s*"|'\s*([^']*?)\s*'|
                    ([^"'>]*?)(?=\s*\/?\s*>|\s\w+\s*=))
                )
EOT;
            if (preg_match_all("/{$pattern}/ix", $head, $match)) {
                $meta_names = array_map('strtolower', $match[1]);
            }
        }
        if (!empty($this->html['description']) && !in_array('description', $meta_names)) {
            $metadata[] = '<meta name="description" content="'.trim($this->html['description']).'">';
        }
        if (!empty($this->html['keywords']) && !in_array('keywords', $meta_names)) {
            $metadata[] = '<meta name="keywords" content="'.trim($this->html['keywords']).'">';
        }
        if ($this->robots !== true && !in_array('robots', $meta_names)) {
            $metadata[] = ($this->html['robots']) ? '<meta name="robots" content="'.$this->html['robots'].'">' : '<meta name="robots" content="noindex, nofollow">'; // ie. false or null
        }
        if (isset($this->data['meta'])) {
            $metadata = array_merge($metadata, $this->data['meta']);
        }

        return $metadata;
    }

    protected function styles()
    {
        // Used in $this->display()
        $styles = array();
        if (isset($this->data['ico'])) {
            $styles[] = '<link rel="shortcut icon" href="'.$this->data['ico'].'">';
        }
        $css = (isset($this->data['css'])) ? $this->data['css'] : array();
        $css = $this->process('css', array_unique($css));
        foreach ($css as $url) {
            $styles[] = '<link rel="stylesheet" href="'.$url.'">';
        }
        foreach (array('link', 'style', 'other') as $tag) {
            if (isset($this->data[$tag])) {
                $styles = array_merge($styles, $this->data[$tag]);
            }
        }

        return $styles;
    }

    protected function scripts()
    {
        // Used in $this->display()
        if (isset($this->data['jquery'])) {
            $jquery = array_filter(array_unique($this->data['jquery']));
            foreach ($jquery as $key => $value) {
                $jquery[$key] = $this->indent($value);
            }
            $this->script('$(document).ready(function(){'."\n".implode("\n", $jquery)."\n".'});');
        }
        $scripts = array();
        $javascript = (isset($this->data['js'])) ? $this->data['js'] : array();
        $javascript = $this->process('javascript', array_unique($javascript));
        foreach ($javascript as $url) {
            $scripts[] = '<script src="'.$url.'"></script>';
        }
        if (isset($this->data['script'])) {
            foreach (array_unique($this->data['script']) as $script) {
                $scripts[] = $script;
            }
        }

        return $scripts;
    }

    protected function indent($string, $tab = "\t")
    {
        // Used in $this->style() and $this->script()
        $array = preg_split("/\r\n|\n|\r/", trim($string));
        $first = $tab.trim(array_shift($array));
        if (empty($array)) {
            return $first; // ie. no indentation at all
        }
        $spaces = array();
        foreach ($array as $value) {
            $spaces[] = strspn($value, " \t");
        }
        $spaces = min($spaces);
        foreach ($array as $key => $value) {
            $array[$key] = $tab.substr($value, $spaces);
        }
        array_unshift($array, $first);

        return implode("\n", $array);
    }

    protected function formatLocalPath($url, $array = false)
    {
        if (!preg_match('/^((?!((f|ht)tps?:)?\/\/)|'.$this->url['preg'].'?)(['.$this->url['chars'].'\/]+)?(\.[a-z0-9]*)?(.*)$/i', $url, $matches)) {
            return ($array) ? array($url, '', '', '') : $url;
        }
        list($full, $url, $not, $applicable, $path, $suffix, $query) = $matches;
        $url = $this->url['base'];
        $path = trim($path, '/');
        if ($path == 'index') {
            $path = '';
        }
        if (in_array($suffix, $this->url['html'])) {
            $suffix = (!empty($path)) ? $this->url['suffix'] : '';
        }

        return ($array) ? array($url, $path, $suffix, $query) : $url.$path.$suffix.$query;
    }

    /**
     * Determine if the current page requires a 301 redirect.
     *
     * Routes are derived from a **301.txt** file at your ``$page->url['base']``.  Every path resides on it's own line, with the new path enclosed in '**[]**' brackets, and the former paths(s) you want to redirect coming after it.  For example:
     *
     * ```txt
     * [new]
     * former/[**:folder]?
     * path
     *
     * [next]
     * path
     * old
     * ```
     *
     * In this example, *'old'* will be redirected to *'next'*, and *'path'* will redirect to *'new'*, where it was first defined.  *'former'* will redirect to *'new'*, and *'former/dir/path'* will be redirected to *'new?folder=dir/path'*.
     *
     * @return false|string A path to ``$page->eject()`` your user to.
     */
    protected function redirect()
    {
        $redirect = false;
        $file = $this->file('301.txt');
        if (is_file($file)) {
            $map = array();
            foreach (array_filter(array_map('trim', file($file))) as $url) {
                if ($url[0] == '[' && substr($url, -1) == ']') {
                    $new = substr($url, 1, -1);
                } elseif (isset($new) && !isset($map[$url])) {
                    $map[$url] = $new;
                }
            }
            $endless = array();
            $path = $this->url['path'];
            parse_str(ltrim($this->url['query'], '?'), $params);
            while ($route = $this->routes($map, $path)) { // get all redirects at once
                $path = $route['target'];
                $params += $route['params'];
                if (in_array($path, $endless) || count($endless) > 5) {
                    return false;
                } else {
                    $endless[] = $path;
                    $redirect = rtrim($path.'?'.http_build_query($params), '?');
                }
            }
        }

        return $redirect;
    }

    protected function __construct()
    {
    }
}
