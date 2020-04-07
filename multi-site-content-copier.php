<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://instance.studio
 * @since             1.0.0
 * @package           Multi_Site_Content_Copier
 *
 * @wordpress-plugin
 * Plugin Name:       Multi Site Content Copier
 * Plugin URI:        https://instance.studio
 * Description:       A Multi Site Content duplication plugin.
 * Version:           1.2.1
 * Author:            Joren Rothman
 * Author URI:        https://instance.studio
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       multi-site-content-copier
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

define('MSCC_PATH', plugin_dir_path(__FILE__));
define('MSCC_URL', plugin_dir_url(__FILE__));

// Load helpers file
include_once(MSCC_PATH . 'includes/msccHelpers.php');

// Load core file
include_once(MSCC_PATH . 'includes/msccCore.php');
