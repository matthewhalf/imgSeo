<?php
/**
 * Class Image_Renamer
 * Manages the renaming of WordPress media files while maintaining all references
 * 
 * This class now serves as a backward-compatibility layer, delegating actual implementation
 * to the modular components in the renamer/ directory.
 */
class Image_Renamer {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Controller instance
     */
    private $controller;
    
    /**
     * Initialize the class and set its properties.
     */
    private function __construct() {
        // Load the modular components
        require_once plugin_dir_path(__FILE__) . 'renamer/class-renamer-controller.php';
        
        // Get the controller instance
        $this->controller = Renamer_Controller::get_instance();
        
        // Register AJAX handlers (for backward compatibility)
        add_action('wp_ajax_imgseo_rename_image', array($this, 'ajax_rename_image'));
        add_action('wp_ajax_imgseo_get_rename_logs', array($this, 'ajax_get_rename_logs'));
        add_action('wp_ajax_imgseo_delete_rename_logs', array($this, 'ajax_delete_rename_logs'));
    }
    
    /**
     * Get the singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * AJAX handler for renaming an image - delegates to the controller
     */
    public function ajax_rename_image() {
        $this->controller->ajax_rename_image();
    }
    
    /**
     * AJAX handler for getting rename logs - delegates to the controller
     */
    public function ajax_get_rename_logs() {
        $this->controller->ajax_get_rename_logs();
    }
    
    /**
     * AJAX handler for deleting rename logs - delegates to the controller
     */
    public function ajax_delete_rename_logs() {
        $this->controller->ajax_delete_rename_logs();
    }
}
