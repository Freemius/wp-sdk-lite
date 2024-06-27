<?php
    /**
     * @package     Freemius
     * @copyright   Copyright (c) 2015, Freemius, Inc.
     * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @since       1.0.3
     */

    use FSLite\Core\Main;
    use FSLite\Core\VersionManager;

    if ( ! defined('ABSPATH'))
    {
        exit;
    }

    // Load sdk config file
    $sdk_config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);

    // Detect the main plugin file using debug_backtrace
    $backtrace   = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $plugin_file = $backtrace[0]['file'] ?? null;

    $autoload_relative_path = __DIR__ . '/autoload.php';
    $autoload_path          = realpath($autoload_relative_path);

    // Initialize the VersionManager
    require_once __DIR__ . '/src/FSLite/Core/VersionManager.php';
    try
    {
        VersionManager::init($plugin_file, $sdk_config, $autoload_path);
    }
    catch (Exception $e)
    {
        wp_die($e->getMessage());
    }

    if ( ! function_exists('fsl'))
    {
        /**
         * @throws Exception
         */
        function fsl($config): Main
        {
            return new Main($config);
        }
    }