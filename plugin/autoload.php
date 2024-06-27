<?php
    spl_autoload_register( function ( $class ) {
        // Namespace prefix
        $prefix = 'LiteFreemiusTest\\';

        // Base Directory for namespace files
        $base_dir = __DIR__ . '/src/LiteFreemiusTest/';

        // Verify if class name has registered prefix
        $len = strlen( $prefix );
        if ( strncmp( $prefix, $class, $len ) !== 0 ) {
            // If not, pass to the following autoloader
            return;
        }

        // Get class relative name
        $relative_class = substr( $class, $len );

        // Replaces the namespace with the base directory, replaces namespace separators
        // with directory separators in the relative class name, appends with .php
        $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

        // If file exists, require it
        if ( file_exists( $file ) ) {
            require $file;
        }
    } );