<?php

if (!defined('ABSPATH')) {
    exit;
}

class WSF_Widget_Manager {
    
    private static $instance = null;
    
    private function __construct() {
        add_action('widgets_init', [$this, 'register_widgets']);
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function register_widgets() {
        register_widget('WSF_Filter_Widget');
        register_widget('WSF_Active_Filters_Widget');
        register_widget('WSF_Universal_Filters_Widget');
        register_widget('WSF_Badges_Widget');
    }
}