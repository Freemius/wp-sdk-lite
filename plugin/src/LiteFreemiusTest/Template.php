<?php
    /**
     * Lite Template Engine
     *
     * This class provides a lightweight, flexible template engine designed to be integrated within a WordPress plugin.
     * It compiles templates into optimized PHP code, handling caching to improve performance and provides a clean separation
     * of logic and presentation layers suitable for MVC architectural patterns in WordPress development.
     *
     * Features include:
     * - Template inheritance and file inclusion.
     * - Block-level template overrides with support for parent data referencing.
     * - Automatic HTML escaping for output security.
     * - Simple syntax for echoing and escaping variables within templates.
     * - Cache management functionalities to enhance performance.
     *
     * Usage:
     * Include this file in your WordPress plugin, set the `$cache_enabled` to true for production environments to enable
     * caching functionality. Use the `view()` method to render templates with optional data passed as an associative array.
     *
     * Based on https://codeshack.io/lightweight-template-engine-php/
     *
     * @package LiteFreemiusTest
     * @license MIT License
     */

    namespace LiteFreemiusTest;

    class Template {

        static $blocks        = array();
        static $cache_path;  // Will be set in the constructor to use WordPress upload directory
        static $cache_enabled = false;

        public function __construct() {
            // Set the cache path to a directory inside WordPress uploads
            self::$cache_path = wp_upload_dir()['basedir'] . '/lite_freemius_cache/';
            if ( ! file_exists( self::$cache_path ) ) {
                wp_mkdir_p( self::$cache_path );
            }
        }

        static function view( $file, $data = array() ) {
            $cached_file = self::cache( $file );
            extract( $data, EXTR_SKIP );
            require $cached_file;
        }

        static function cache( $file ) {
            $file_path   = LITE_FREEMIUS_TEST__PLUGIN_DIR . 'templates/' . $file;
            $cached_file = self::$cache_path . str_replace( array( '/', '.html' ), array( '_', '' ), $file . '.php' );
            if ( ! self::$cache_enabled || ! file_exists( $cached_file ) || filemtime( $cached_file ) < filemtime( $file_path ) ) {
                $code = self::includeFiles( $file_path );
                $code = self::compileCode( $code );
                file_put_contents( $cached_file,
                    '<?php class_exists(\'' . __CLASS__ . '\') or exit; ?>' . PHP_EOL . $code );
            }

            return $cached_file;
        }

        static function clearCache() {
            foreach ( glob( self::$cache_path . '*' ) as $file ) {
                unlink( $file );
            }
        }

        static function compileCode( $code ) {
            $code = self::compileBlock( $code );
            $code = self::compileYield( $code );
            $code = self::compileEscapedEchos( $code );
            $code = self::compileEchos( $code );
            $code = self::compilePHP( $code );

            return $code;
        }

        static function includeFiles( $file_path ) {
            $code = file_get_contents( $file_path );
            preg_match_all( '/{% ?(extends|include) ?\'?(.*?)\'? ?%}/i', $code, $matches, PREG_SET_ORDER );
            foreach ( $matches as $value ) {
                $include_path = plugin_dir_path( __FILE__ ) . $value[2];
                $code         = str_replace( $value[0], self::includeFiles( $include_path ), $code );
            }
            $code = preg_replace( '/{% ?(extends|include) ?\'?(.*?)\'? ?%}/i', '', $code );

            return $code;
        }

        static function compilePHP( $code ) {
            return preg_replace( '~\{%\s*(.+?)\s*\%}~is', '<?php $1 ?>', $code );
        }

        static function compileEchos( $code ) {
            return preg_replace( '~\{{\s*(.+?)\s*\}}~is', '<?php echo $1 ?>', $code );
        }

        static function compileEscapedEchos( $code ) {
            return preg_replace( '~\{{{\s*(.+?)\s*\}}}~is',
                '<?php echo htmlentities($1, ENT_QUOTES, \'UTF-8\') ?>',
                $code );
        }

        static function compileBlock( $code ) {
            preg_match_all( '/{% ?block ?(.*?) ?%}(.*?){% ?endblock ?%}/is', $code, $matches, PREG_SET_ORDER );
            foreach ( $matches as $value ) {
                if ( ! array_key_exists( $value[1], self::$blocks ) ) {
                    self::$blocks[ $value[1] ] = '';
                }
                if ( strpos( $value[2], '@parent' ) === false ) {
                    self::$blocks[ $value[1] ] = $value[2];
                } else {
                    self::$blocks[ $value[1] ] = str_replace( '@parent', self::$blocks[ $value[1] ], $value[2] );
                }
                $code = str_replace( $value[0], '', $code );
            }

            return $code;
        }

        static function compileYield( $code ) {
            foreach ( self::$blocks as $block => $value ) {
                $code = preg_replace( '/{% ?yield ?' . $block . ' ?%}/', $value, $code );
            }
            $code = preg_replace( '/{% ?yield ?(.*?) ?%}/i', '', $code );

            return $code;
        }

    }