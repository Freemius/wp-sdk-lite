<?php

    namespace FSLite\Core;

    /**
     * Class LicenseManager
     *
     * This class provides functionality to manage licenses for a product.
     * It includes methods to activate, deactivate, and sync licenses, as well as to retrieve the latest version information.
     * The class interacts with both public and authenticated APIs to perform these actions.
     *
     * Usage Example:
     * $licenseManager = new LicenseManager($productConfig);
     * $response = $licenseManager->activate($licenseParams);
     *
     * @package FSLite
     */

    use DateTime;
    use Exception;
    use FSLite\Api\AuthenticatedApi;
    use FSLite\Api\PublicApi;
    use FSLite\Data\License;
    use WP_Error;

    /**
     * Class LicenseManager
     *
     * The LicenseManager class provides functionality to manage licenses.
     */
    class LicenseManager
    {

        const OPTION_KEY = 'fs_lite_activations';

        const EXPIRATION_DAYS = 30;

        private $publicApi;

        private $authenticatedApi;

        private $productConfig;

        /**
         * @var License
         */
        public $params;

        /**
         * @throws Exception
         */
        public function __construct($productConfig)
        {
            $this->publicApi     = new PublicApi(Main::getSdkConfig()['endpoints']['api']);
            $this->productConfig = $productConfig;
            $this->initAuthenticatedApi();
        }

        /**
         * Activates the license key for the site.
         *
         * @throws Exception
         */
        public function activate(License $pParams)
        {
            // Set current site URL
            $pParams->setIfMissing('url', get_site_url());
            // Set current site UUID
            $pParams->setIfMissing('uid', VersionManager::getSiteUuid());
            // Set Plugin ID
            $pParams->setProductId($this->productConfig['id']);
            if ( ! empty($_POST['user_email']))
            {
                $user_email = sanitize_email($_POST['user_email']);
                $pParams->setUserEmail($user_email);
            }
            // Get saved install
            $install = $this->getInstall();
            // Install already saved and valid
            if (is_array($install) && $install['status'] === License::ACTIVE)
                return new WP_Error('license_already_activated');

            // Call remote API to activate license
            $response = $this->remoteLicenseActivation($pParams);

            $created_install = $this->publicApi->validateResponse($response);

            if (is_wp_error($created_install))
                return $created_install;

            if (isset($created_install['install_id']))
            {
                $saved_activations                                = get_option(self::OPTION_KEY, array());
                $created_install['activation_params']             = $pParams->toArray();
                $created_install['date']                          = (new DateTime())->format('Y-m-d H:i:s');
                $created_install['status']                        = License::ACTIVE;
                $saved_activations[$created_install['plugin_id']] = $created_install;
                update_option(self::OPTION_KEY, $saved_activations);

                $this->initAuthenticatedApi();
            }

            return $this->getInstall();
        }

        /**
         * @throws Exception
         */
        public function getInstall()
        {
            $saved_activations = get_option(self::OPTION_KEY, array());
            $install           = $saved_activations[$this->productConfig['id']] ?? false;
            if ( ! $install || ! isset($install['date']))
            {
                return false;
            }

            $install_saved_date = new DateTime($install['date']);
            $current_date       = new DateTime();
            $interval           = $current_date->diff($install_saved_date);

            if ($interval->days > self::EXPIRATION_DAYS)
            {
                return false;
            }

            return $install;
        }

        /**
         * Deactivates a license key.
         *
         * @return array|WP_Error True on successful deactivation, WP_Error object on failure.
         * @throws Exception
         */
        public function deactivate()
        {
            $params = new License();
            // Set current site URL
            $params->setUrl(get_site_url());
            // Set current site UUID
            $params->setUid(VersionManager::getSiteUuid());
            // Set Plugin ID
            $params->setProductId($this->productConfig['id']);

            $current_install = $this->getInstall();
            if ( ! is_array($current_install))
                return new WP_Error('Install not found');

            $params->setInstallId($current_install['install_id']);
            $params->setLicenseKey($current_install['activation_params']['license_key']);

            $response = $this->remoteLicenseDeactivation($params);

            $deleted_install = $this->publicApi->validateResponse($response);
            if (is_wp_error($deleted_install))
                return $deleted_install;

            if (isset($deleted_install['id']))
            {
                $saved_activations                                          = get_option(self::OPTION_KEY, array());
                $saved_activations[$deleted_install['plugin_id']]['status'] = $deleted_install['activated'] ? License::ACTIVE : License::INACTIVE;
                update_option(self::OPTION_KEY, $saved_activations);
            }

            return $this->getInstall();
        }

        /**
         * Activates a license key remotely.
         *
         * @param License $pParams
         *
         * @return array|WP_Error
         */
        private function remoteLicenseActivation(License $pParams)
        {
            if ( ! $pParams->isValidForActivation())
                return new WP_Error('license_activation_failed', $pParams->getErrors());

            $plugin_id = $pParams->getProductId();

            return $this->publicApi->post(
                "v1/plugins/$plugin_id/activate.json",
                $pParams->toArray()
            );
        }

        /**
         * Deactivates a license key remotely.
         *
         * @param License $pParams
         *
         * @return array|WP_Error  An associative array with 'success' and 'message' keys.
         */
        private function remoteLicenseDeactivation(License $pParams)
        {
            $api = new PublicApi(Main::getSdkConfig()['endpoints']['api']);

            if ( ! $pParams->isValidForDeactivation())
                return new WP_Error('license_deactivation_failed', $pParams->getErrors());

            $plugin_id = $pParams->getProductId();

            return $api->post(
                "v1/plugins/$plugin_id/deactivate.json",
                $pParams->toArray()
            );
        }

        /**
         * @throws Exception
         */
        private function initAuthenticatedApi()
        {
            $install = $this->getInstall();
            if (is_array($install))
            {
                // Looks like install scope misses all endpoints we need, so we use user scope
                $this->authenticatedApi = new AuthenticatedApi(
                    'user',
                    $install['user_id'],
                    $this->getPublicKey('user'),
                    $this->getSecretKey('user')
                );
            }
        }

        /**
         * Get the public key.
         *
         * @param string $pScope
         *
         * @return string|false
         * @throws Exception
         */
        public function getPublicKey(string $pScope = 'user')
        {
            $current_install = $this->getInstall();
            if (is_array($current_install))
            {
                // $scope can be 'user' or 'install'
                $key = $pScope . '_public_key';
                if (isset($current_install[$key]))
                {
                    return $current_install[$key];
                }
            }

            return false;
        }

        /**
         * Get the secret key.
         *
         * @param string $pScope
         *
         * @return string|false
         * @throws Exception
         */
        public function getSecretKey(string $pScope = 'user')
        {
            $current_install = $this->getInstall();
            if (is_array($current_install))
            {
                $key = $pScope . '_secret_key';
                if (isset($current_install[$key]))
                {
                    return $current_install[$key];
                }
            }

            return false;
        }

        /**
         * @throws Exception
         */
        public function syncInstall()
        {
            $current_install = $this->getInstall();
            if ( ! isset($this->authenticatedApi))
                $this->initAuthenticatedApi();

            if ( ! isset($this->authenticatedApi))
                return new WP_Error('authenticated_api_not_available');

            $endpoint = "/installs/{$current_install['install_id']}.json";

            $data = array(
                'install_id' => $current_install['install_id'],
            );

            $response = $this->authenticatedApi->get($endpoint, $data);

            $remote_install = $this->publicApi->validateResponse($response);
            if (is_wp_error($remote_install))
                return $remote_install;

            // Update local saved installation with remote installation data
            $saved_activations = get_option(self::OPTION_KEY, array());

            // COPY EXISTING KEYS FROM REMOTE TO LOCAL
            foreach ($saved_activations[$current_install['plugin_id']] as $key => $value)
            {
                if (array_key_exists($key, $remote_install))
                {
                    $saved_activations[$current_install['plugin_id']][$key] = $remote_install[$key];
                }
            }
            // END COPY EXISTING KEYS FROM REMOTE TO LOCAL

            /**
             * @todo: Find a better way to check if remote license is active
             */
            if ($remote_install['is_active'] && !is_null($remote_install['license_id']))
            {
                $saved_activations[$current_install['plugin_id']]['status'] = License::ACTIVE;
            } else {
                $saved_activations[$current_install['plugin_id']]['status'] = License::INACTIVE;
            }

            $saved_activations[$current_install['plugin_id']]['date'] = (new DateTime())->format('Y-m-d H:i:s');
            update_option(self::OPTION_KEY, $saved_activations);

            return $saved_activations[$current_install['plugin_id']];
        }

        /**
         * @throws Exception
         */
        public function getLatest()
        {
            if ( ! isset($this->authenticatedApi))
            {
                $this->initAuthenticatedApi();
            }
            if ( ! isset($this->authenticatedApi))
            {
                return new WP_Error('authenticated_api_not_available');
            }

			$install = $this->getInstall();

			$plugin_id = $install['plugin_id'];

            $endpoint = "/plugins/{$plugin_id}/updates/latest.json";

            $result = $this->authenticatedApi->get($endpoint);

            $latest_release = json_decode($result['body'], true);

            if (isset($latest_release['error']))
                return new WP_Error($latest_release['error']['code'], $latest_release['error']['message']);

            return $latest_release;
        }

        /**
         * @throws Exception
         */
        public function status()
        {
            $install = $this->getInstall();

            return $install['status'] ?? License::MISSING;
        }
    }
