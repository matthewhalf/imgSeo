<?php


/**


 * Handle processing speed settings and batch processing.


 *


 * @package ImgSEO


 */





if (!defined('ABSPATH')) {


    exit; // Exit if accessed directly.


}





class ImgSEO_Process_Speed {





    /**


     * Speed presets with their corresponding batch sizes


     */


    const SPEED_PRESETS = [


        'slow' => 2,


        'normal' => 3,


        'fast' => 4,


        'ultra' => 5


    ];





    /**


     * Delay between image processing in seconds


     */


    const IMAGE_DELAY = 0.5;





    /**


     * Initialize the process speed settings


     */


    public static function init() {


        // Register settings


        add_action('admin_init', [self::class, 'register_settings']);





        // Add speed settings to the settings page


        add_filter('imgseo_settings_fields', [self::class, 'add_speed_settings'], 20, 1);





        // Initialize the speed setting if it doesn't exist


        self::maybe_add_default_option();


    }





    /**


     * Add the default speed option if it doesn't exist


     */


    public static function maybe_add_default_option() {


        if (false === get_option('imgseo_processing_speed')) {


            add_option('imgseo_processing_speed', 'normal');


        }


    }





    /**


     * Register the settings


     */


    public static function register_settings() {


        register_setting('imgseo_settings', 'imgseo_processing_speed');


    }





    /**


     * Add speed settings to the ImgSEO settings


     */


    public static function add_speed_settings($fields) {


        $speed_field = [


            'id' => 'imgseo_processing_speed',


            'label' => __('Processing Speed', 'imgseo'),


            'description' => __('Select the processing speed for bulk operations. Higher speeds process more images in parallel.', 'imgseo'),


            'type' => 'select',


            'options' => [


                'slow' => __('Slow (2 images at once)', 'imgseo'),


                'normal' => __('Normal (3 images at once)', 'imgseo'),


                'fast' => __('Fast (4 images at once)', 'imgseo'),


                'ultra' => __('Ultra (5 images at once)', 'imgseo')


            ],


            'default' => 'normal'


        ];





        // Find the position to insert our field - after the batch size if it exists


        $batch_size_index = -1;


        foreach ($fields as $index => $field) {


            if (isset($field['id']) && $field['id'] === 'imgseo_batch_size') {


                $batch_size_index = $index;


                break;


            }


        }





        if ($batch_size_index >= 0) {


            // Insert after the batch size field


            array_splice($fields, $batch_size_index + 1, 0, [$speed_field]);


        } else {


            // Just append to the end if batch size field not found


            $fields[] = $speed_field;


        }





        return $fields;


    }





    /**


     * Get current batch size based on the selected speed


     */


    public static function get_batch_size() {


        $speed = get_option('imgseo_processing_speed', 'normal');


        return isset(self::SPEED_PRESETS[$speed]) ? self::SPEED_PRESETS[$speed] : self::SPEED_PRESETS['normal'];


    }





    /**


     * Get delay between image processing in milliseconds


     *


     * Calculate delay dynamically based on the number of images processed in parallel


     */


    public static function get_delay_ms() {


        try {


            $batch_size = self::get_batch_size();


            // Ensure we have a valid number to prevent division by zero


            if (!is_numeric($batch_size) || $batch_size <= 0) {


                $batch_size = 3; // Default fallback


            }


            // Calculate delay by dividing 3 seconds by the batch size


            $delay = 3.0 / (float)$batch_size;


            // Convert to milliseconds and ensure it's an integer


            return (int)($delay * 1000);


        } catch (Exception $e) {


            error_log('ImgSEO Error in get_delay_ms: ' . $e->getMessage());


            return 1000; // Default fallback of 1 second


        }


    }


}





// Initialize the class


ImgSEO_Process_Speed::init();
