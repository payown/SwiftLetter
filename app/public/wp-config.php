<?php
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
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

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
define( 'AUTH_KEY',          'bRdEe[+YKXG.uOxPCn~&}:osl[?:V(1Hmz]~Z4dD0G1w#M~Ryj*i,_5z|.t9*he]' );
define( 'SECURE_AUTH_KEY',   '8FVB?m4*U5yEmemQ}Io^a}tOT25mF%.Q%8;J[:m?U2cBy5<ynrR95w$|J<%WK%2r' );
define( 'LOGGED_IN_KEY',     '8|T~8-zf43VOf@Q2h%/]#)pvnV;Ys/Gm#h.4}IvPd5unm>$c<eLe-{cnyVMXDM*M' );
define( 'NONCE_KEY',         '&if4dz&18FFDaMjM{x)$b_7BwENITF+zNQBFijW%+Gi[xt[r=wF#iCp(?0$mmnUJ' );
define( 'AUTH_SALT',         'POF);u3Fj~Xy<ldXm/JGe4JWON~A1m,<p:YWHi?gQviQrZz^QWc22!Bu`*_hh.|X' );
define( 'SECURE_AUTH_SALT',  'kD2VV81qcu FQvbBRF/_;+@j,6ME{Nt3#?zh77}MC3 ];R1l4nfx_Rsnpa=,rp2W' );
define( 'LOGGED_IN_SALT',    '+N){{o}uoocq?LlZ/g!3=ijl0XMPlL|E,;~N/@&woPL7N*u-ioON8-n=v>QQ~c|7' );
define( 'NONCE_SALT',        '8tm!:|W<8+py*ic^VH}yq=@t2BNIrRXny[v(oAp`jbeV+{P{l%k) ,i&QF}aIdF~' );
define( 'WP_CACHE_KEY_SALT', 'kbQ[.bO1{/Gr!gi>*2[|-JCF`fl KdmN$uEPxw^==UNCck@073m5,+Qu@/h0xEU/' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



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
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'WP_ENVIRONMENT_TYPE', 'local' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
