# use BootPress\Page\Component;

[![Latest Version on Packagist](https://img.shields.io/packagist/v/bootpress/page.svg?style=flat-square)](https://packagist.org/packages/bootpress/page)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/Kylob/Page/master.svg?style=flat-square)](https://travis-ci.org/Kylob/Page)
[![Code Climate](https://img.shields.io/codeclimate/github/Kylob/Page.svg?style=flat-square)](https://codeclimate.com/github/Kylob/Page)
[![Test Coverage](https://img.shields.io/codeclimate/coverage/github/Kylob/Page.svg?style=flat-square)](https://codeclimate.com/github/Kylob/Page/coverage)

A framework agnostic HTML framework that allows you to manipulate every part of an HTML Page at any time.

## Installation

Add the following to your ``composer.json`` file.

``` bash
{
    "require": {
        "bootpress/page": "^1.0"
    }
}
```

## Example Usage

``` php
$page = Page::html();
```

The Page class implements the Singleton design pattern so that you can call it from anywhere, and still be on the same "Page".  You don't have to, but if you would like to enforce a desired url scheme (recommended), then do this:

``` php
$page = Page::html(array(
    'dir' => '../', // a root folder where you can keep everything safe and sound
    'base' => 'https://example.com/', // this will be enforced now
    'suffix' => '.html', // goes at the end of your urls
)); // You can only do this once
```

Symfony is our undisputed friend here.  This class relies on their HttpFoundation component.  You can pass us an instance if you already have one going, or we will create one for you.  Check this out:

```php
$page->response->request->query->get('key'); // or ...
$page->post('key'); // ie. a $_POST['key'] with benefits
```

The ``$page->response`` above is the Symfony Response object that you can access directly.  You can also call (or set) ``$page->session``, and if you didn't have one before, you do now.

Now that setting up is out of the way ...

``` php
// The .ico and .css files will go to your <head>
// The .js file will be at the bottom of your <body>
$page->link(array(
    $page->url('images/favicon.ico'),
    $page->url('css/stylesheet.css'),
    $page->url('js/functions.js'),
));

// To put a <link> before all the others you have set
$page->link('https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css', 'prepend');

// Meta tags are placed at the top of the <head>
$page->meta('name="author" content="name"'); // or ...
$page->meta(array('name'=>'author', 'content'=>'name')); // or ...
$page->link('<meta name="author" content="name">'); // You can spell all these tags out with the link method

// <style> tags are placed right after the <link>'s
$page->style('body { background-color:red; color:black; }'); // or ...
$page->style(array('body { background-color:red; color:black; }')); // or ...
$page->style(array('body' => 'background-color:red; color:black;')); // or ...
$page->style(array('body' => array('background-color:red;', 'color:black;'))); // or ...
$page->link('<style>body { background-color:red; color:black; }</style>');

// <script> tags come immediately after the .js files
$page->script('alert("Hello World");'); // or
$page->link('<script>alert("Hello World");</script>');

// All of these will go into one $(document).ready(function(){...}); at the bottom of your page
$page->jquery('$("button.continue").html("Next Step...");');
```

Of course, none of that does any good if you don't give us the final HTML to work with:

``` php
echo $page->display('<p>Content</p>');
```

That will return you a nice:

``` html
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title></title>
    <meta name="author" content="name">
    <link rel="shortcut icon" href="https://example.com/images/favicon.ico">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://example.com/css/stylesheet.css">
    <style>body { background-color:red; color:black; }</style>
</head>
<body>
    <p>Content</p>
    <script src="https://example.com/js/functions.js"></script>
    <script>alert("Hello World");</script>
    $(document).ready(function(){
        $("button.continue").html("Next Step...");
    });
</body>
</html>
```

To change (or access) the default values that have been set (by anyone), you can:

``` php
$page->title = 'Page Title';
$page->description = 'Page description.';
$page->charset = 'UTF-8';
$page->language = 'en-us';

echo '<h1>'.$page->title.'</h1>'; // <h1>Page Title</h1>
```

Above, you just gave us your ``<p>Content</p>``, and we created the HTML Page around it.  You can also give us the entire page, and we'll still put things where they belong:

``` php
$page->display(<<<'EOT'

	<  !doctype html>
<html   >
<HEad>< title>Broken</tit
<META content=" name " name="author">
	</ head>  <body style="color:#333;">
	
	I'm in the body!</body>
< /html>

EOT;
);
```

That will give you:

``` php
<  !doctype html>
<html   >
<HEad>
    <meta charset="UTF-8">
    <meta name="description" content="Page description">
< title>Broken</tit
<META content=" name " name="author">
    <link rel="shortcut icon" href="https://example.com/images/favicon.ico">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://example.com/css/stylesheet.css">
    <style>body { background-color:red; color:black; }</style>
</head>
<body style="color:#333;">

	
	I'm in the body!
    <script src="https://example.com/js/functions.js"></script>
    <script>alert("Hello World");</script>
    $(document).ready(function(){
        $("button.continue").html("Next Step...");
    });
</body>
</html>
```

It's a bit screwed up still, but that's how you wanted it.  We took nothing out, and only added the parts that were missing with the information you gave us.  This class can do as little, or as much as you like.  It's all the same to us.  Even if you use none of the above, it's still nice to have a central location where an application can create, and work with urls established according to your specs.

``` php
$page->enforce('seo-path'); // If the current url was not https://example.com/seo-path.html, it is now.

echo $page->url['path']; // seo-path

if ($page->get('form') == 'submitted') { // https://example.com/seo-path.html?form=submitted
    $eject = $page->url('delete', '', 'form'); // https://example.com/seo-path.html
    $page->eject($page->url('add', $eject, 'payment', 'received')); // go now to https://example.com/seo-path.html?payment=received
} elseif ($page->get('payment') == 'received') {
    mail($address, 'Thanks for your money!');
}
```

That should be a good enough sampling for a README file.  We didn't even get to directories, filters, and responses, but it's all there in the source code, fully documented.

Enjoy!


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
