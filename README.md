# Enable Sass and Scss in WordPress

This plugin allows you to write and edit .sass and .scss files directly and
have WordPress do the job of compiling and caching the resulting CSS. It
eliminates the extra step of having to compile the files into CSS yourself
before deploying them.

## Installation:

If you are using git to clone the repository do the following:

    git clone git://github.com/sanchothefat/wp-sass.git wp-sass
    git submodule update --init --recursive

If you are downloading the zip or tar don't forget to download the phpsass
dependency too https://github.com/richthegeek/phpsass and copy it into the `phpsass`
folder.

## Usage:

You can either install the script as a standard plugin or use it as an include within a theme or plugin.

For use with themes add the following lines to your functions.php:

```php
<?php

// Include the class (unless you are using the script as a plugin)
require_once( 'wp-sass/wp-sass.php' );

// enqueue a .less style sheet
if ( ! is_admin() )
    wp_enqueue_style( 'style', get_stylesheet_directory_uri() . '/style.scss' );
else
	wp_enqueue_style( 'admin', get_stylesheet_directory_uri() . '/admin.sass.php' );

// you can also use .less files as mce editor style sheets
add_editor_style( 'editor-style.sass' );

?>
```

Any registered styles with the .sass or .scss suffix will be compiled and the file URL
rewritten.

### Using PHP in .sass and .scss files

You can also create `.sass.php` or `.scss.php` files that will be preprocessed in the WordPress context.
What this means is that you can use WordPress functions within the stylesheets themselves eg:

```sass
$red: <?php echo get_option( 'redcolour', '#c00' ); ?>;

body {
  background: $red;
}
```

## Further Reading

[Read the Sass and Scss documentation here](http://sass-lang.com/)


## License

The software is licensed under the MIT license.
