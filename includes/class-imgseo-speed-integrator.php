<?php
/**
 * Processing speed integrator for the ImgSEO plugin.
 * 
 * This class handles the integration of the processing speed settings 
 * into the existing ImgSEO plugin architecture.
 *
 * @package ImgSEO
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class ImgSEO_Speed_Integrator {
    
    /**
     * Initialize the speed integrator
     */
    public static function init() {
        // Load the process speed class if not already loaded
        if (!class_exists('ImgSEO_Process_Speed')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-imgseo-process-speed.php';
        }
        
        // Add the parameters to the JS script
        add_filter('imgseo_localize_script', [self::class, 'add_speed_params_to_js']);
        
        // Replace the default batch size with the speed-based one
        add_filter('imgseo_batch_size', [self::class, 'filter_batch_size']);
    }
    
    /**
     * Add processing speed parameters to JavaScript
     * 
     * @param array $params The existing script parameters
     * @return array The modified script parameters
     */
    public static function add_speed_params_to_js($params) {
        if (!is_array($params)) {
            $params = [];
        }
        
        try {
            // Add speed settings to the params array with error handling
            $batch_size = ImgSEO_Process_Speed::get_batch_size();
            $delay_ms = ImgSEO_Process_Speed::get_delay_ms();
            $speed = get_option('imgseo_processing_speed', 'normal');
            
            // Make sure we're returning clean values
            $params['processing_speed_batch_size'] = (int)$batch_size;
            $params['processing_image_delay_ms'] = (int)$delay_ms;
            $params['processing_speed'] = sanitize_text_field($speed);
            
            // Debug log for troubleshooting
            error_log('ImgSEO: Adding processing speed params - batch_size: ' . $batch_size . ', delay_ms: ' . $delay_ms . ', speed: ' . $speed);
        } catch (Exception $e) {
            // Log the error but don't break the script
            error_log('ImgSEO Error: ' . $e->getMessage());
            
            // Use default values as fallback
            $params['processing_speed_batch_size'] = 3;
            $params['processing_image_delay_ms'] = 1000;
            $params['processing_speed'] = 'normal';
        }
        
        return $params;
    }
    
    /**
     * Override the default batch size with our speed-based one
     * 
     * @param int $batch_size The original batch size
     * @return int The new batch size based on speed settings
     */
    public static function filter_batch_size($batch_size) {
        return ImgSEO_Process_Speed::get_batch_size();
    }
}

// Initialize the class
ImgSEO_Speed_Integrator::init();
