<?php
    /*
     * This file is included only once and serves as the main entry point for the SDK.
     * Here we define constants that will be used across all plugins that integrate this SDK.
     */

    // Define the main path of the SDK
    const FSL_PATH = __DIR__;

    spl_autoload_register(function ($class) {
        // Namespace prefix
        $prefix = 'FSLite\\';

        // Base Directory for namespace files
        $base_dir = __DIR__ . '/src/FSLite/';

        // Verify if class name has registered prefix
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0)
        {
            // If not, pass to the following autoloader
            return;
        }

        // Get class relative name
        $relative_class = substr($class, $len);

        // Replaces the namespace with the base directory, replaces namespace separators
        // with directory separators in the relative class name, appends with .php
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        // If file exists, require it
        if (file_exists($file))
        {
            require $file;
        }
    });