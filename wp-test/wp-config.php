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
define( 'DB_NAME', 'webakery_test' );

/** Database username */
define( 'DB_USER', 'webakery' );

/** Database password */
define( 'DB_PASSWORD', 'webakery' );

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
define( 'AUTH_KEY',          'BBP*0)W-2mG!D)x6/m+xUTIX{=&3a?0`_vv2I}~TvuY@_Eskd+K>>%J{tqE>+}2*' );
define( 'SECURE_AUTH_KEY',   '9Qito nVeGC)YdK?MF%hiD@)kDLM%zKoC^m$]-G13#sMI<.I]39W*N?A K_M5Q9|' );
define( 'LOGGED_IN_KEY',     '6?T1!!_BN-!WU{eAcR,V&mCKwG3h;kZ-#!p2a5x$9cx0j*%!|bO:]HO3lI@,WZ)x' );
define( 'NONCE_KEY',         '&^Kf_*{8Q_(+)y4X|QoB=A]X67L_AE6TUBZXl{bmn?f|xz@?hMxj&x,,PqQ*,|^a' );
define( 'AUTH_SALT',         'kSE`lb4YaOmF*|[E,vOP3G$e7;dASUoHX_s</Wc@ip4D&XdMxq= [&7V_m(YqyGx' );
define( 'SECURE_AUTH_SALT',  'wSARH9j9-p@u{]6TldJ|TvCjthJTA-M0]{ZXmsK1[]$Gy@Pd4!{0;xHDK/]u) 5}' );
define( 'LOGGED_IN_SALT',    'KaLm%MoXDW>m6l<hC{q)?= 7n:<yk]|:Q|i26Yw6Gec`*Csv>.!u.NOf<V|g*WJ+' );
define( 'NONCE_SALT',        'R1[Kfc^AMI<tnk8/`6MddfPfXX1GYiq5.(blDZ!7XFo0`dUo,UOLxw3%mRxJPUYx' );
define( 'WP_CACHE_KEY_SALT', '>c1R;40WcmDC&jP}Jj5.qr%ni8Ps5;QMt&fz`*OP/TpySl(%E~L?*Af=()X9VnjJ' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */

define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISABLE_WP_CRON', true );


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

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
