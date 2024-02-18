<?php
define( 'WP_CACHE', true );
 // Added by WP Rocket
//Begin Really Simple SSL session cookie settings
@ini_set('session.cookie_httponly', true);
@ini_set('session.cookie_secure', true);
@ini_set('session.use_only_cookies', true);
//END Really Simple SSL
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */
// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'u638088046_thestyledare' );
/** Database username */
define( 'DB_USER', 'u638088046_thestyledare' );
/** Database password */
define( 'DB_PASSWORD', '2681999p@P' );
/** Database hostname */
define( 'DB_HOST', '127.0.0.1' );
/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );
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
define( 'AUTH_KEY',          'GBeHZX%Fg,%J? *#dkKXS7W;op.|C4VXf@(Z?;HkoRkuMDx0y~W:fM{KG4py+b2m' );
define( 'SECURE_AUTH_KEY',   'bJcj6ahf/*jzz+p$jbFpC#;H<u8Xs}d WLM5QhhwCy)[>wA3#1jM jp8}E.`>Cdy' );
define( 'LOGGED_IN_KEY',     'nofR$p|-x.+@#E8FG@4(a%W^G`{PJBZgt2qQld`CC_kqkp5}0{/#~}{;Z8i>j2R/' );
define( 'NONCE_KEY',         'vZ8pe2-M!l3 737PFvYjj-^[a%} >^r XMhZX3n:v^pW(JuE0XvzHPH%dT3^Xda+' );
define( 'AUTH_SALT',         '{bl1QVs=SP4?E++e29Ilwr&=;dKCv|3?rBO=FV}D#D]V0mh)gotiS&-j,d#g=!y`' );
define( 'SECURE_AUTH_SALT',  'w_PKI[r1VZJaf2LS<=v6vhW6eF|plOG$47v4n;,AzA^`9#:pincBH&obr]<?e4q;' );
define( 'LOGGED_IN_SALT',    'axj[whMp3QpQ6 seS#,fHU6Acm}5`0BovY:aNdCZDa6=!yvWq4IrYs[`d6u#~2np' );
define( 'NONCE_SALT',        'Wi>+PS}Z4sQ45oQsBMFQvrj*tH4s+GpPW]F@Xy@JB.JjtyGSqfNAGh^B:$3-lg&c' );
define( 'WP_CACHE_KEY_SALT', '}0eq%p9Bn[Ijn0zFq{dV5C~V$9l-0AAv#a5@{H^1!@QgH!sF&=FJ-edBYC%lZ+Fc' );
/**#@-*/
/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';
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
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );
/* Add any custom values between this line and the "stop editing" line. */
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
define( 'FS_METHOD', 'direct' );
/* That's all, stop editing! Happy publishing. */
/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
