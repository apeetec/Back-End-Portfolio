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
define( 'DB_NAME', 'back-end-portfolio' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

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
define( 'AUTH_KEY',         '_SiQMCK7Ow:(W:KVr=92b=lOTZRTr (^sE1%<*D[BGdC}qNcm|2GKSm-r_|j[ZL{' );
define( 'SECURE_AUTH_KEY',  'O&}Hcy&l{HUM|,5#xkD9<w.KSp~5n}*Ux@+`hVRl? 35f<xCi]6fP#&k?AxY#{:P' );
define( 'LOGGED_IN_KEY',    'TS0@h[us5nk|iqiP&J.fk`n3 R9%,?[uC/+kb^ekUgb~k$)D?-DW+aj^v@IQQkGH' );
define( 'NONCE_KEY',        'b$PEC;Cc89zXtnKWK OQ1ak]/=r=w@<+pPv[vd>uRC(?&mL09O)s=k2[Yg =qe9!' );
define( 'AUTH_SALT',        '7fVFp2{q cSC6w`M//2-ZRZtCA.@/y+qT:[1afI!8bgE[)nSl?ff]*1Ktp5P!06R' );
define( 'SECURE_AUTH_SALT', 'M{YTm}m+RX7p4.K`>zLf#/V)+]/N`r-uqX!:e^SnmR=t/LYfyMlDKO`r9867#B1)' );
define( 'LOGGED_IN_SALT',   '<&QUuxMh;Ep}YtfQ8z4G{4vxR{am%2f;CE-a9G<^vZL=]W,G(n2!b]nvP6:229@V' );
define( 'NONCE_SALT',       '7dChdWf90M4 :xx.p@v5;5`B9NrTDJVM7D79OD,@.=8{!|N8/^4K8<gELv:F&d5s' );

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
$table_prefix = 'wp_back_end_portfolio_';

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
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
