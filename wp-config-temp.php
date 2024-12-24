<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'cloud_wp10');

/** MySQL database username */
define('DB_USER', 'cloud_wp10');

/** MySQL database password */
define('DB_PASSWORD', 'W(du#8Ixu0m(BPH~4a@79#[2');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'UqypYcJT26weFiTzT3iypvEbqmcJ49j4mOy9wXh2o32KkaRVClxCTDYYlVeUcmgv');
define('SECURE_AUTH_KEY',  'S4Els7Gz7WdByhWxkn1DeItylOSEDzgjAUQENd6ZP7ySGos10SgfHGO3iumQJ0mk');
define('LOGGED_IN_KEY',    'fxG1oW1fGWH5uJhtVWivPqC481uOBOMQKtfg3a3bfXZh93JiLSFHFtFjI4xaCenn');
define('NONCE_KEY',        'VFSPuERokPlwNp9juvzE082OCMdHf4VfJK1twNsFPF9fNYCDfRbRIjnm90ZEmp8R');
define('AUTH_SALT',        'l9y4quwbcz3Oo7aVKxjcC1BdJxcnbkOYy860LUW0PEfOB2cAtZnNSzjYkSH8riAs');
define('SECURE_AUTH_SALT', 'KR3JPNvhdzLMIy7hSCrJYSpChIWZHe1YvTJv6v91YZ0vHgd6bDcu0Z4eNkJ3fJCv');
define('LOGGED_IN_SALT',   'TTCXQ8iFumXre6mNVNS7XPGMIUUB4VSWlnaEBQcVZXIguKOV8QAkOBZ7TgAObx83');
define('NONCE_SALT',       'TDWuyiJx4OiSkZ1pTmHSD8WvTzHO0yPnoxWJ1QV24jgJKxd84JQz5V1tSKzeQinO');

/**
 * Other customizations.
 */
define('FS_METHOD','direct');define('FS_CHMOD_DIR',0755);define('FS_CHMOD_FILE',0644);
define('WP_TEMP_DIR',dirname(__FILE__).'/wp-content/uploads');

/**
 * Turn off automatic updates since these are managed upstream.
 */
define('AUTOMATIC_UPDATER_DISABLED', true);


/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

define( 'WP_AUTO_UPDATE_CORE', true );
/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
//require_once(ABSPATH . 'wp-settings.php');
