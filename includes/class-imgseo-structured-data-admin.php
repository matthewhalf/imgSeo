<?php
/**
 * Class ImgSEO_Structured_Data_Admin
 * Manages the admin interface for structured data settings
 *
 * @package ImgSEO
 * @since 1.2.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ImgSEO_Structured_Data_Admin {
    
    /**
     * Singleton instance
     *
     * @var ImgSEO_Structured_Data_Admin|null
     */
    private static $instance = null;
    
    /**
     * Get the singleton instance
     *
     * @return ImgSEO_Structured_Data_Admin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Register structured data settings
     */
    public function register_settings() {
        // Register settings group
        register_setting('imgseo_structured_data_settings', 'imgseo_enable_structured_data', array(
            'default' => 1,
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('imgseo_structured_data_settings', 'imgseo_structured_data_include_thumbnails', array(
            'default' => 1,
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('imgseo_structured_data_settings', 'imgseo_structured_data_include_author', array(
            'default' => 1,
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('imgseo_structured_data_settings', 'imgseo_structured_data_max_images', array(
            'default' => 10,
            'sanitize_callback' => 'absint'
        ));
        
        // Add settings section
        add_settings_section(
            'imgseo_structured_data_section',
            __('JSON-LD Structured Data Settings', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_section_description'),
            'imgseo_structured_data_settings'
        );
        
        // Add settings fields
        add_settings_field(
            'imgseo_enable_structured_data',
            __('Enable JSON-LD Structured Data', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_enable_field'),
            'imgseo_structured_data_settings',
            'imgseo_structured_data_section'
        );
        
        add_settings_field(
            'imgseo_structured_data_include_thumbnails',
            __('Include Thumbnail URLs', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_thumbnails_field'),
            'imgseo_structured_data_settings',
            'imgseo_structured_data_section'
        );
        
        add_settings_field(
            'imgseo_structured_data_include_author',
            __('Include Author Information', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_author_field'),
            'imgseo_structured_data_settings',
            'imgseo_structured_data_section'
        );
        
        add_settings_field(
            'imgseo_structured_data_max_images',
            __('Maximum Images per Page', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_max_images_field'),
            'imgseo_structured_data_settings',
            'imgseo_structured_data_section'
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin page
        if ('imgseo_page_imgseo-structured-data' !== $hook) {
            return;
        }
        
        // Enqueue existing admin styles
        wp_enqueue_style(
            'imgseo-admin-style',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin-style.css',
            array(),
            '1.0.0'
        );
    }
    
    /**
     * Render the admin page
     */
    public function render_admin_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', IMGSEO_TEXT_DOMAIN));
        }
        
        // Get structured data statistics
        $structured_data = ImgSEO_Structured_Data::get_instance();
        $stats = $structured_data->get_structured_data_stats();
        
        // Include the template
        include plugin_dir_path(dirname(__FILE__)) . 'templates/structured-data-admin-page.php';
    }
    
    /**
     * Render section description
     */
    public function render_section_description() {
        echo '<p>' . esc_html__('Configure how JSON-LD structured data is generated for your images. This helps search engines better understand your image content.', IMGSEO_TEXT_DOMAIN) . '</p>';
    }
    
    /**
     * Render enable structured data field
     */
    public function render_enable_field() {
        $value = get_option('imgseo_enable_structured_data', 1);
        ?>
        <label>
            <input type="checkbox" name="imgseo_enable_structured_data" value="1" <?php checked($value, 1); ?> />
            <?php esc_html_e('Enable JSON-LD structured data for images', IMGSEO_TEXT_DOMAIN); ?>
        </label>
        <p class="description">
            <?php esc_html_e('Automatically inserts JSON-LD markup for images on pages. This helps search engines better understand your images.', IMGSEO_TEXT_DOMAIN); ?>
        </p>
        <?php
    }
    
    /**
     * Render include thumbnails field
     */
    public function render_thumbnails_field() {
        $value = get_option('imgseo_structured_data_include_thumbnails', 1);
        ?>
        <label>
            <input type="checkbox" name="imgseo_structured_data_include_thumbnails" value="1" <?php checked($value, 1); ?> />
            <?php esc_html_e('Include thumbnail URLs in structured data', IMGSEO_TEXT_DOMAIN); ?>
        </label>
        <p class="description">
            <?php esc_html_e('Includes thumbnail URLs in the JSON-LD structured data for better image representation.', IMGSEO_TEXT_DOMAIN); ?>
        </p>
        <?php
    }
    
    /**
     * Render include author field
     */
    public function render_author_field() {
        $value = get_option('imgseo_structured_data_include_author', 1);
        ?>
        <label>
            <input type="checkbox" name="imgseo_structured_data_include_author" value="1" <?php checked($value, 1); ?> />
            <?php esc_html_e('Include author information in structured data', IMGSEO_TEXT_DOMAIN); ?>
        </label>
        <p class="description">
            <?php esc_html_e('Includes the image author information in the JSON-LD structured data.', IMGSEO_TEXT_DOMAIN); ?>
        </p>
        <?php
    }
    
    /**
     * Get structured data settings
     *
     * @return array
     */
    public function get_settings() {
        return array(
            'enable_structured_data' => get_option('imgseo_enable_structured_data', 1),
            'include_thumbnails' => get_option('imgseo_structured_data_include_thumbnails', 1),
            'include_author' => get_option('imgseo_structured_data_include_author', 1),
            'max_images' => get_option('imgseo_structured_data_max_images', 10)
        );
    }
    
    /**
     * Check if structured data is enabled
     *
     * @return bool
     */
    public function is_structured_data_enabled() {
        return (bool) get_option('imgseo_enable_structured_data', 1);
    }
    
    /**
     * Check if thumbnails should be included
     *
     * @return bool
     */
    public function should_include_thumbnails() {
        return (bool) get_option('imgseo_structured_data_include_thumbnails', 1);
    }
    
    /**
     * Check if author information should be included
     *
     * @return bool
     */
    public function should_include_author() {
        return (bool) get_option('imgseo_structured_data_include_author', 1);
    }
    
    /**
     * Render max images field
     */
    public function render_max_images_field() {
        $value = get_option('imgseo_structured_data_max_images', 10);
        ?>
        <input type="number" name="imgseo_structured_data_max_images" id="imgseo_structured_data_max_images"
               value="<?php echo esc_attr($value); ?>" min="1" max="50" step="1" />
        <p class="description">
            <?php esc_html_e('Maximum number of images to process per page for structured data (1-50). Lower values improve performance.', IMGSEO_TEXT_DOMAIN); ?>
        </p>
        <?php
    }
    
    /**
     * Get maximum images per page setting
     *
     * @return int
     */
    public function get_max_images_per_page() {
        return max(1, min(50, intval(get_option('imgseo_structured_data_max_images', 10))));
    }
}