<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'blessedh_ranng' );

/** Database username */
define( 'DB_USER', 'blessedh_ranng' );

/** Database password */
define( 'DB_PASSWORD', 'Metabole@1978' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'JjML4@,WkcBAVOIUq{Cy>a+t%)v<7F%9u_ow/9+68c(X6UUs[O9|k15$9 rWhDl;' );
define( 'SECURE_AUTH_KEY',  'x/IV??.0@]*d=dUv*d_gpD>mn/-NFDvcpHWsSd9W:M%Vx15^6){sZj!+@f_@/5d2' );
define( 'LOGGED_IN_KEY',    '!e2xiW5[Q&`0(;^~DGUl!|qb4S_}/?$V-1myl+!C0^OxJZoj}AWzclH?whh|0.{k' );
define( 'NONCE_KEY',        'urG/ZCQ$yU_H([TnkhO*q]N-AhDPxrW(]6FnoEQ#5^TeyHF;lEceQ!VNwD%sHzTJ' );
define( 'AUTH_SALT',        '-9SC=mQ!a/<drj>dP(m4I;tM8hsh;{]g7@Bb^Jim_L!!n:wziU)q$C^`v$onwlkd' );
define( 'SECURE_AUTH_SALT', 'Fn(RkTMttZ.#Y+bc(daW3qXE#V4qY+M`&:P`6^UfSjUkN`a>Nn%gHWCAXT{`~e!W' );
define( 'LOGGED_IN_SALT',   'r[t#d:L^{t60<g]JJ5NE:oqKGk_@s>6NOslz0LoPGv(mX/Ydl^^-isnL{R!Ok-|R' );
define( 'NONCE_SALT',       'jt89^[pNe;8Lh4/bQ#P!#D>n&1*mh]zA9z2VULFE0wjn-r+>3BS*lUQX|[:.g${n' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_ranng';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', true );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
