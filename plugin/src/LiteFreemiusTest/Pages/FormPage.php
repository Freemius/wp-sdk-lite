<?php

    namespace LiteFreemiusTest\Pages;

    use Exception;
    use FSLite\Api\LicenseApi;
    use FSLite\Data\License;
    use Lite_Freemius_Test_Plugin;
    use LiteFreemiusTest\Template;

    class FormPage
    {

        const NONCE = 'form-page-action-nonce';

        /**
         * @var Lite_Freemius_Test_Plugin
         */
        protected $plugin;

        public function __construct(Lite_Freemius_Test_Plugin $plugin)
        {
            $this->plugin = $plugin;
        }

        /**
         * @throws Exception
         */
        public function render()
        {
            $messages = [];
            $action   = $_POST['action'] ?? 'n-d';
            if ($_SERVER['REQUEST_METHOD'] === 'POST')
            {
                if ( ! isset($_POST['_wpnonce']) || ! wp_verify_nonce($_POST['_wpnonce'], 'freemius_nonce_action'))
                {
                    die('Nonce verification failed.');
                }

                switch ($action)
                {
                    case 'activate_license_by_key':
                        // Check if install exists
                        $current_install = $this->plugin->freemius->license->getInstall();
                        if (is_array($current_install) && $current_install['status'] === License::ACTIVE)
                        {
                            _e('<h3 style="color: #990000">License is already installed</h3>');
                        }
                        else
                        {
                            $license_params = License::fromArray($_POST);
                            $activation_result = $this->plugin->freemius->license->activate($license_params);
                            echo '<pre>';
                            var_dump($activation_result);
                            echo '</pre>';
                        }
                        break;
                    case 'check_activation_status':
                        $current_status = $this->plugin->freemius->license->status();
                        if ($current_status === License::ACTIVE)
                        {
                            _e('<h3 style="color: #006633;">License is installed and valid</h3>');
                            echo '<pre>';
                            var_dump($current_status);
                            echo '</pre>';
                        }
                        else
                        {
                            _e(sprintf('<h3 style="color: #990000">License is %s</h3>', $current_status));
                        }
                        break;
                    case 'deactivate_license':
                        $deactivation_result = $this->plugin->freemius->license->deactivate();
                        echo '<pre>';
                        var_dump($deactivation_result);
                        echo '</pre>';
                        break;
                    case 'sync_install':
                        $sync_result = $this->plugin->freemius->license->syncInstall();
                        echo '<pre>';
                        var_dump($sync_result);
                        echo '</pre>';
                        break;
                    case 'get_latest':
                        $sync_result = $this->plugin->freemius->license->getLatest();
                        echo '<pre>';
                        var_dump($sync_result);
                        echo '</pre>';
                        break;
                    default:
                        _e('<h3 style="color: #990000">Method not implemented</h3>');
                        break;
                }
            }
            $nonce = wp_create_nonce(self::NONCE);

            Template::view(
                'full-form.html',
                [
                    'sdk_version' => $this->plugin->freemius->getSdkVersion(),
                    'plugin_id'   => $this->plugin->freemius->getProductId(),
                    'nonce'       => $nonce,
                    'messages'    => $messages,
                    'action'      => $action,
                ]
            );
        }
    }