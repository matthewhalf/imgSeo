<?php
/**
 * Class Media_Modal_Button
 * Manages buttons for alt text generation in various WordPress media interfaces
 * Optimized implementation according to best practices
 */
class Media_Modal_Button {

    /**
     * Singleton instance of the class
     * @var Media_Modal_Button
     */
    private static $instance = null;
    
    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        // Immediate initialization of hooks
        $this->init_hooks();
    }

    /**
     * Gets the instance of the class (singleton)
     * @return Media_Modal_Button
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initializes all necessary hooks
     */
    public function init_hooks() {
        // Hook for Media Modal - priority 1 to add the button before other filters
        add_filter('attachment_fields_to_edit', array($this, 'add_button_to_attachment_form'), 1, 2);
        
        // Metabox in the attachment edit page
        add_action('add_meta_boxes_attachment', array($this, 'add_alt_generation_metabox'));
        
        // Loading scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_media', array($this, 'enqueue_media_scripts'));
        
        // Add class to body for media edit page
        add_filter('admin_body_class', array($this, 'add_body_class'));
    }
    
    /**
     * Loads scripts and styles for admin pages
     * 
     * @param string $hook Page hook
     */
    public function enqueue_admin_scripts($hook) {
        // Check if we are on the attachment edit page
        $is_edit_attachment_page = ($hook === 'post.php' && 
                                 isset($_GET['post']) && 
                                 get_post_type($_GET['post']) === 'attachment');
        
        if ($is_edit_attachment_page) {
            $this->enqueue_common_resources();
        }
    }
    
    /**
     * Loads scripts for the Media Modal
     */
    public function enqueue_media_scripts() {
        $this->enqueue_common_resources();
        
        // Specific script for the Media Modal
        wp_enqueue_script(
            'imgseo-media-modal-button', 
            IMGSEO_PLUGIN_URL . 'assets/js/media-modal-button.js', 
            array('jquery'), 
            IMGSEO_PLUGIN_VERSION,
            true
        );
    }
    
    /**
     * Loads common resources (scripts and styles)
     */
    private function enqueue_common_resources() {
        // Main script for alt text generation
        wp_enqueue_script(
            'imgseo-alt-text-generator', 
            IMGSEO_PLUGIN_URL . 'assets/js/imgseo-alt-text-generator.js', 
            array('jquery'), 
            IMGSEO_PLUGIN_VERSION, 
            true
        );
        
        // CSS styles
        wp_enqueue_style(
            'imgseo-media-modal-css',
            IMGSEO_PLUGIN_URL . 'assets/css/media-modal-custom.css',
            array(),
            IMGSEO_PLUGIN_VERSION
        );
        
        // Prepare base localized data
        $localized_data = $this->get_localized_script_data();
        
        // Apply filter to allow other components to add data (like processing speed)
        if (has_filter('imgseo_localize_script')) {
            $localized_data = apply_filters('imgseo_localize_script', $localized_data);
        }
        
        // Localize the script with potentially modified data
        wp_localize_script('imgseo-alt-text-generator', 'ImgSEO', $localized_data);
    }
    
    /**
     * Adds a class to the body to identify the media edit page
     * @param string $classes Classi esistenti
     * @return string Classi aggiornate
     */
    public function add_body_class($classes) {
        global $pagenow;
        
        // Check if we are on the attachment edit page
        if ($pagenow === 'post.php' && isset($_GET['post']) && get_post_type($_GET['post']) === 'attachment') {
            return $classes . ' imgseo-edit-media-page';
        }
        
        return $classes;
    }
    
    /**
     * Returns localized data to pass to JavaScript scripts
     * 
     * @return array Dati per wp_localize_script
     */
    private function get_localized_script_data() {
        // Translated texts
        $texts = array(
            'generate_button' => __('Generate Alt Text', IMGSEO_TEXT_DOMAIN),
            'generating' => __('Generating...', IMGSEO_TEXT_DOMAIN),
            'success' => __('Alternative text successfully generated!', IMGSEO_TEXT_DOMAIN),
            'error' => __('Error during generation:', IMGSEO_TEXT_DOMAIN),
            'connection_error' => __('Connection error:', IMGSEO_TEXT_DOMAIN),
            'no_field_found' => __('Alt text field not found', IMGSEO_TEXT_DOMAIN),
            'no_id_found' => __('Image ID not found', IMGSEO_TEXT_DOMAIN)
        );
        
        // Complete data
        return array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('imgseo_nonce'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'update_title' => (bool)get_option('imgseo_update_title', 0),
            'update_caption' => (bool)get_option('imgseo_update_caption', 0),
            'update_description' => (bool)get_option('imgseo_update_description', 0),
            'texts' => $texts,
            'version' => IMGSEO_PLUGIN_VERSION,
            'plugin_url' => IMGSEO_PLUGIN_URL,
            'is_admin' => is_admin(),
            'is_attachment_edit' => isset($_GET['post']) && isset($_GET['action']) && 
                                  $_GET['action'] === 'edit' && 
                                  get_post_type($_GET['post']) === 'attachment'
        );
    }

    /**
     * Adds the button to the attachment edit form
     * Implements the standard WordPress approach through filters
     * 
     * @param array $form_fields Campi del form
     * @param WP_Post $post Oggetto post
     * @return array Campi del form modificati
     */
    public function add_button_to_attachment_form($form_fields, $post) {
        // Only for images
        if (strpos($post->post_mime_type, 'image/') !== 0) {
            return $form_fields;
        }
        
        // Create the button with a unique ID
        $button_html = sprintf(
            '<button type="button" id="generate-alt-text-%1$d" class="button button-primary generate-alt-text-modal-btn" data-id="%1$d">%2$s</button>',
            $post->ID,
            __('Generate Alt Text', IMGSEO_TEXT_DOMAIN)
        );
        
        // Add container for results
        $button_html .= sprintf(
            '<div id="alt-text-result-%d" class="alt-text-result"></div>',
            $post->ID
        );
        
        // Insert everything into a container
        $button_html = '<div class="imgseo-button-container">' . $button_html . '</div>';
        
        // Information about active update options
        $update_options = array();
        if (get_option('imgseo_update_title', 0)) {
            $update_options[] = __('Title', IMGSEO_TEXT_DOMAIN);
        }
        if (get_option('imgseo_update_caption', 0)) {
            $update_options[] = __('Caption', IMGSEO_TEXT_DOMAIN);
        }
        if (get_option('imgseo_update_description', 0)) {
            $update_options[] = __('Description', IMGSEO_TEXT_DOMAIN);
        }
        
        // Help descriptions
        $helps = [__('Generate an SEO-optimized alt text using artificial intelligence.', IMGSEO_TEXT_DOMAIN)];
        
        if (!empty($update_options)) {
            $helps[] = sprintf(
                __('Also updates: %s (configure in plugin settings)', IMGSEO_TEXT_DOMAIN),
                implode(', ', $update_options)
            );
        }
        
        // Add the custom button right after the alt field
        $form_fields['generate_alt_text_button'] = array(
            'input' => 'html',
            'html'  => $button_html,
            'helps' => $helps
        );
        
        return $form_fields;
    }
    
    /**
     * Adds a metabox for alt text generation in the attachment edit page
     */
    public function add_alt_generation_metabox() {
        // Get the current post type
        $post_id = get_the_ID();
        $post_mime_type = get_post_mime_type($post_id);
        
        // Add the metabox only if the attachment is an image
        if (strpos($post_mime_type, 'image/') === 0) {
            add_meta_box(
                'imgseo_alt_generation_metabox',
                __('ImgSEO - Alt Text Generation', IMGSEO_TEXT_DOMAIN),
                array($this, 'render_alt_generation_metabox'),
                'attachment',
                'side',
                'high'
            );
        }
    }
    
    /**
     * Renders the content of the metabox for alt text generation
     * 
     * @param WP_Post $post Oggetto post corrente
     */
    public function render_alt_generation_metabox($post) {
        $attachment_id = $post->ID;
        
        // Active options
        $update_options = array();
        if (get_option('imgseo_update_title', 0)) {
            $update_options[] = __('Title', IMGSEO_TEXT_DOMAIN);
        }
        if (get_option('imgseo_update_caption', 0)) {
            $update_options[] = __('Caption', IMGSEO_TEXT_DOMAIN);
        }
        if (get_option('imgseo_update_description', 0)) {
            $update_options[] = __('Description', IMGSEO_TEXT_DOMAIN);
        }
        
        // Verify nonce for security
        wp_nonce_field('imgseo_alt_generation', 'imgseo_alt_generation_nonce');
        
        // HTML output
        ?>
        <div class="imgseo-metabox-content">
            <p><?php _e('Generate an SEO-optimized alt text using artificial intelligence.', IMGSEO_TEXT_DOMAIN); ?></p>
            
            <?php if (!empty($update_options)): ?>
            <p class="description">
                <?php printf(
                    __('Also updates: %s', IMGSEO_TEXT_DOMAIN),
                    implode(', ', $update_options)
                ); ?>
            </p>
            <?php endif; ?>
            
            <div class="imgseo-button-container">
                <button type="button" id="generate-alt-text-edit" 
                        class="button button-primary generate-alt-text-edit-btn" 
                        data-id="<?php echo esc_attr($attachment_id); ?>">
                    <?php _e('Generate Alt Text', IMGSEO_TEXT_DOMAIN); ?>
                </button>
                <div id="alt-text-result-<?php echo esc_attr($attachment_id); ?>" class="alt-text-result"></div>
            </div>
        </div>
        <?php
    }
}
