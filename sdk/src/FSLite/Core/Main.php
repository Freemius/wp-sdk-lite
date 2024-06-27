<?php

    namespace FSLite\Core;

    use Exception;

    /**
     * Class Main
     *
     * This class represents the main functionality of the application.
     * It provides methods to manage licenses, retrieve SDK configuration, and interact with both public and authenticated APIs.
     *
     * Usage Example:
     *     fsl()->license->activate($licenseParams);
     *     fsl()->license->deactivate($licenseParams);
     *     fsl()->license->getInstall(); // Retrieve information about install from WordPress database
     *     fsl()->license->status(); // could be License::ACTIVE, License::INACTIVE, License::EXPIRED etc.
     *
     * @package FSLite
     */
    class Main
    {

        const SDK_CONFIG_OPTION = 'fsl_sdk_config';

        private static $sdkConfig;

        /**
         * @var LicenseManager
         */
        public  $license;

        private $productConfig;

        /**
         * Main constructor.
         *
         * @param array $pProductConfig
         *
         * @throws Exception
         */
        public function __construct(array $pProductConfig)
        {
            $this->productConfig = $pProductConfig;
            $this->license       = new LicenseManager($this->productConfig);
        }

        /**
         * Get the SDK version from global configuration.
         *
         * @return string
         */
        public static function getSdkVersion(): string
        {
            return self::getSdkConfig()['version'];
        }

        /**
         * Get the global configuration.
         *
         * @return array
         */
        public static function getSdkConfig(): array
        {
            if ( ! isset(self::$sdkConfig))
            {
                self::$sdkConfig = self::loadSdkConfig();
            }

            return self::$sdkConfig;
        }

        /**
         * Set the global configuration.
         *
         * @param array $pConfig
         */
        public static function setSdkConfig(array $pConfig)
        {
            self::$sdkConfig = $pConfig;
            self::saveSdkConfig();
        }

        /**
         * Save the configuration to DB
         *
         * @return void
         */
        private static function saveSdkConfig()
        {
            update_option(self::SDK_CONFIG_OPTION, self::$sdkConfig);
        }

        /**
         * Get the configuration
         *
         * @return array
         */
        public static function loadSdkConfig(): array
        {
            return get_option(self::SDK_CONFIG_OPTION, []);
        }

        /**
         * Get the product ID from the configuration.
         *
         * @return string
         */
        public function getProductId(): string
        {
            return $this->productConfig['id'];
        }
    }