<?php

if (!defined('ABSPATH')) {
    exit;
}

class WSF_Autoloader {
    
    public static function init() {
        spl_autoload_register([__CLASS__, 'autoload']);
    }
    
    public static function autoload($class) {
        if (strpos($class, 'WSF_') !== 0) {
            return;
        }
        
        $class_file = strtolower(str_replace('_', '-', $class));
        $file = WSF_PATH . 'includes/class-' . $class_file . '.php';
        
        if (file_exists($file)) {
            require_once $file;
        }
    }
}