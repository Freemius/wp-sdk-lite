<?php

    namespace FSLite\Core;

    use Exception;
    use InvalidArgumentException;

    /**
     * Class VersionManager
     *
     * Manages the SDK version used by WordPress plugins and themes. This class ensures
     * that only the most recent and compatible version of the SDK is loaded when multiple
     * plugins or themes attempt to load different versions of the same SDK. This is essential
     * for preventing conflicts and ensuring stability across different components of a WordPress site.
     *
     * Methods:
     * - init(): Initializes the SDK version management and loads the Main class.
     * - isSdkAvailable(array $pSdk): Checks if a specific version of the SDK is available and valid.
     * - loadSdk(): Loads the specified version of the SDK.
     * - registerSdkVersion(array $pSdk): Registers and loads a new version of the SDK.
     *
     * Constants:
     * - SDK_OPTION_NAME: The option name used to store the SDK information.
     */
    if ( ! class_exists('FSLite\Core\VersionManager'))
    {
        class VersionManager
        {

            private static $loaded_version = '0';

            const SDK_OPTION_NAME = 'fsl_site_sdk';

            /**
             * Initializes the SDK version management and loads the Main class.
             *
             * @param string $pPluginFile   The main plugin file.
             * @param array  $pSdkConfig    The SDK configuration.
             * @param string $pAutoloadPath The path to the autoload file.
             *
             * @throws InvalidArgumentException If the plugin file is not detected.
             * @throws Exception If the autoload file is not found.
             */
            public static function init(string $pPluginFile, array $pSdkConfig, string $pAutoloadPath)
            {
                if (empty($pAutoloadPath))
                    throw new Exception('Autoload file not found.');

                $local_sdk = [
                    'plugin'  => substr($pPluginFile, strlen(WP_PLUGIN_DIR) + 1),
                    'version' => $pSdkConfig['version'],
                    'loader'  => $pAutoloadPath,
                    'time'    => time(),
                ];

                $site_sdk = get_option(self::SDK_OPTION_NAME);
                if ($site_sdk)
                {
                    self::verifyAndLoadVersion($site_sdk, $local_sdk);
                }
                else
                {
                    self::registerSdkVersion($local_sdk);
                    self::loadSdk();
                    Main::setSdkConfig($pSdkConfig);
                }
            }

            /**
             * Verifies and loads the appropriate SDK version.
             *
             * @param array $pSiteSdk  The SDK data stored in the site options.
             * @param array $pLocalSdk The local SDK data.
             */
            private static function verifyAndLoadVersion(array $pSiteSdk, array $pLocalSdk)
            {
                $register = false;

                if ($pSiteSdk['plugin'] === $pLocalSdk['plugin'] && $pSiteSdk['version'] !== $pLocalSdk['version'])
                {
                    $register = true;
                }
                else if ( ! self::isSdkAvailable($pSiteSdk))
                {
                    $register = true;
                }
                else if (version_compare($pSiteSdk['version'], $pLocalSdk['version'], '<'))
                {
                    $register = true;
                }

                if ($register)
                    self::registerSdkVersion($pLocalSdk);

                self::loadSdk();
            }

            /**
             * Checks if the given SDK version is available and valid.
             *
             * @param array $pSdk The SDK data to check.
             *
             * @return bool Returns true if the version is available and valid, otherwise false.
             */
            private static function isSdkAvailable(array $pSdk): bool
            {
                $active_plugins = get_option('active_plugins');

                /**
                 * @Swas - I think apart from checking if the plugin is active, you also need to check if `file_exists` for the loader file. See my refactoring.
                 *         The reason is, a plugin can suddenly decide to remove the SDK. In that case, we want to keep the site running.
                 */
                if (empty($active_plugins))
                    return false;

                if ( ! in_array($pSdk['plugin'], $active_plugins))
                    return false;

                if ( ! file_exists($pSdk['loader']))
                    return false;

                return true;
            }

            /**
             * Loads the SDK.
             */
            private static function loadSdk()
            {
                $sdk = get_option(self::SDK_OPTION_NAME);
                if ($sdk)
                {
                    self::$loaded_version = $sdk['version'];
                    include_once $sdk['loader'];
                }
            }

            /**
             * Registers a new version of the SDK.
             *
             * @param array $pSdk The SDK data to register.
             */
            private static function registerSdkVersion(array $pSdk)
            {
                update_option(self::SDK_OPTION_NAME, $pSdk);
            }

            /**
             * Returns the currently loaded SDK version.
             *
             * @return string The loaded SDK version.
             */
            public static function getLoadedVersion(): string
            {
                return self::$loaded_version;
            }

            /**
             * Gets the unique site ID.
             *
             * This function retrieves a stored UUID if it exists and matches the current site URL.
             * If no UUID is found or the URL has changed (e.g., site was cloned), a new UUID is generated and stored.
             *
             * @return string The unique site UUID.
             */
            public static function getSiteUuid(): string
            {
                // Option name to store the UUID and site URL
                $option_name = 'fsl_site_data';

                // Retrieve the saved option
                $site_data = get_option($option_name);

                // Get the current site URL
                $current_site_url = get_site_url();

                // Check if the UUID exists and the URL matches
                if ($site_data && isset($site_data['uuid']) && isset($site_data['url']) && $site_data['url'] === $current_site_url)
                {
                    // UUID exists and is valid for the current URL
                    return $site_data['uuid'];
                }

                // Generate a new UUID
                $random_data  = wp_generate_uuid4();
                $current_time = date('c');  // ISO 8601 format
                $new_uuid     = md5($current_site_url . $random_data . $current_time);

                // Save the new UUID and current site URL
                $new_site_data = [
                    'uuid' => $new_uuid,
                    'url'  => $current_site_url,
                ];
                update_option($option_name, $new_site_data);

                return $new_uuid;
            }
        }
    }
