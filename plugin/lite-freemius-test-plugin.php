<?php

    /*
    Plugin Name: Lite Freemius SDK Test Plugin
    Description: A simple plugin to test Lite SDK integration
    Plugin URI: http://freemius.com
    Version: 1.0
    Author: Daniele Alessandra <daniele@freemius.com>
    License:      GPL-2.0-or-later
    License URI:  https://www.gnu.org/licenses/gpl-2.0.html
    Text Domain:  lite-fs-test
    Version:     1.0.0
    Requires PHP: 7.3
    Requires at least: 6.0
    Domain Path:  /language
    */

    // This is not required for SDK to work, but is used merely to show current SDK version on plugin page
    use LiteFreemiusTest\Pages\FormPage;

    define('LITE_FREEMIUS_TEST__PLUGIN_DIR', plugin_dir_path(__FILE__));

    class Lite_Freemius_Test_Plugin
    {

        const FREEMIUS_CONFIG = [
            'id'             => '7149',
            'slug'           => 'lite-freemius-test',
            'type'           => 'plugin',
            'public_key'     => 'pk_b00bda0cd7eab1fb30d4fe9364d13',
            'is_premium'     => false,
            'has_addons'     => false,
            'has_paid_plans' => false,
            'is_sandbox'     => false,
            'menu'           => array(
                'account' => false,
                'support' => false,
            ),
        ];

        private static $instance = null;

        public         $freemius;

        /**
         * @throws Freemius_Exception
         */
        private function __construct()
        {
            require_once dirname(__FILE__) . '/autoload.php';
            /**
             * Start init FSLite
             */
            require_once dirname(__FILE__) . '/fs-lite/start.php';
            $this->freemius = fsl(self::FREEMIUS_CONFIG);
            /**
             * Done init FSLite
             */
        }

        private function setHooks()
        {
            // Hook to add a menu item
            add_action('admin_menu', [$this, 'addMyMenu']);
        }

        public function init()
        {
            $this->setHooks();
        }

        public static function getInstance()
        {
            if (self::$instance === null)
            {
                self::$instance = new Lite_Freemius_Test_Plugin();
            }

            return self::$instance;
        }

        // Function to define new menu item
        public function addMyMenu()
        {
            $form = new FormPage($this);

            add_menu_page(
                'Lite Freemius Test Plugin',
                'Lite Freemius Test Plugin',
                'manage_options',
                'lite-freemius-test-main',
                [$form, 'render'],
                'dashicons-carrot',
                6
            );
        }
    }

    Lite_Freemius_Test_Plugin::getInstance()->init();
