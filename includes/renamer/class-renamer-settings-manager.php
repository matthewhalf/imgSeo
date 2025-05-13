<?php
/**
 * Class Renamer_Settings_Manager
 * Manages settings for the Image Renamer functionality
 */
class Renamer_Settings_Manager {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Log retention days
     */
    private $log_retention_days = 30;
    
    /**
     * Initialize the class and set its properties.
     */
    private function __construct() {
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
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
     * Register settings for the renamer
     */
    public function register_settings() {
        // ========== IMPOSTAZIONI ESISTENTI ==========
        
        // Register log retention setting
        register_setting('imgseo_renamer_settings', 'imgseo_log_retention_days', array(
            'default' => 30,
            'sanitize_callback' => 'absint'
        ));
        
        // ========== IMPOSTAZIONI AI ==========
        
        // Impostazioni per generazione AI
        register_setting('imgseo_renamer_settings', 'imgseo_renamer_ai_max_words', array(
            'default' => 4,
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('imgseo_renamer_settings', 'imgseo_renamer_ai_include_post_title', array(
            'default' => 1,
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('imgseo_renamer_settings', 'imgseo_renamer_ai_include_category', array(
            'default' => 1,
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('imgseo_renamer_settings', 'imgseo_renamer_ai_include_alt_text', array(
            'default' => 1,
            'sanitize_callback' => 'absint'
        ));
        
        // ========== NUOVE IMPOSTAZIONI ==========
        
        // Opzioni di sanitizzazione
        register_setting('imgseo_renamer_settings', 'imgseo_renamer_remove_accents', array(
            'default' => 1,
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('imgseo_renamer_settings', 'imgseo_renamer_lowercase', array(
            'default' => 1,
            'sanitize_callback' => 'absint'
        ));
        
        // Opzioni per duplicati
        register_setting('imgseo_renamer_settings', 'imgseo_renamer_handle_duplicates', array(
            'default' => 'increment',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        // Supporto page builder
        register_setting('imgseo_renamer_settings', 'imgseo_renamer_elementor_support', array(
            'default' => 1,
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('imgseo_renamer_settings', 'imgseo_renamer_visualcomposer_support', array(
            'default' => 1,
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('imgseo_renamer_settings', 'imgseo_renamer_divi_support', array(
            'default' => 1,
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('imgseo_renamer_settings', 'imgseo_renamer_beaver_support', array(
            'default' => 1,
            'sanitize_callback' => 'absint'
        ));
        
        // ========== SEZIONI IMPOSTAZIONI ==========
        
        // Sezione generale
        add_settings_section(
            'imgseo_renamer_general_section',
            __('General Settings', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_general_section'),
            'imgseo_renamer_settings'
        );
        
        // Sanitization section
        add_settings_section(
            'imgseo_renamer_sanitization_section',
            __('Sanitization Options', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_sanitization_section'),
            'imgseo_renamer_settings'
        );
        
        // AI Section
        add_settings_section(
            'imgseo_renamer_ai_section',
            __('AI Filename Generator', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_ai_section'),
            'imgseo_renamer_settings'
        );
        
        // Integration section
        add_settings_section(
            'imgseo_renamer_integration_section',
            __('Page Builder Integration', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_integration_section'),
            'imgseo_renamer_settings'
        );
        
        // ========== CAMPI PER SEZIONE GENERALE ==========
        
        // Campo di log retention
        add_settings_field(
            'imgseo_log_retention_days',
            __('Log Retention (Days)', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_log_retention_field'),
            'imgseo_renamer_settings',
            'imgseo_renamer_general_section'
        );
        
        // ========== CAMPI PER SEZIONE AI ==========
        
        // Campo per numero massimo di parole
        add_settings_field(
            'imgseo_renamer_ai_max_words',
            __('Max Words in AI Generated Filename', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_number_field'),
            'imgseo_renamer_settings',
            'imgseo_renamer_ai_section',
            array(
                'name' => 'imgseo_renamer_ai_max_words',
                'min' => 2,
                'max' => 10,
                'description' => __('Number of words to use in AI generated filenames', IMGSEO_TEXT_DOMAIN)
            )
        );
        
        // Campi per le opzioni di contesto
        add_settings_field(
            'imgseo_renamer_ai_include_post_title',
            __('Include Post Title in Context', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_checkbox_field'),
            'imgseo_renamer_settings',
            'imgseo_renamer_ai_section',
            array(
                'name' => 'imgseo_renamer_ai_include_post_title',
                'description' => __('Include post title in AI prompt for better context', IMGSEO_TEXT_DOMAIN)
            )
        );
        
        add_settings_field(
            'imgseo_renamer_ai_include_category',
            __('Include Category in Context', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_checkbox_field'),
            'imgseo_renamer_settings',
            'imgseo_renamer_ai_section',
            array(
                'name' => 'imgseo_renamer_ai_include_category',
                'description' => __('Include post category in AI prompt for better context', IMGSEO_TEXT_DOMAIN)
            )
        );
        
        add_settings_field(
            'imgseo_renamer_ai_include_alt_text',
            __('Include Alt Text in Context', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_checkbox_field'),
            'imgseo_renamer_settings',
            'imgseo_renamer_ai_section',
            array(
                'name' => 'imgseo_renamer_ai_include_alt_text',
                'description' => __('Include image alt text in AI prompt for better context', IMGSEO_TEXT_DOMAIN)
            )
        );
        
        // ========== CAMPI PER SEZIONE SANITIZZAZIONE ==========
        
        // Campo per rimuovere accenti
        add_settings_field(
            'imgseo_renamer_remove_accents',
            __('Remove Accents', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_checkbox_field'),
            'imgseo_renamer_settings',
            'imgseo_renamer_sanitization_section',
            array(
                'name' => 'imgseo_renamer_remove_accents',
                'description' => __('Remove accents from filenames', IMGSEO_TEXT_DOMAIN)
            )
        );
        
        // Campo per conversione in minuscolo
        add_settings_field(
            'imgseo_renamer_lowercase',
            __('Convert to Lowercase', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_checkbox_field'),
            'imgseo_renamer_settings',
            'imgseo_renamer_sanitization_section',
            array(
                'name' => 'imgseo_renamer_lowercase',
                'description' => __('Convert filenames to lowercase', IMGSEO_TEXT_DOMAIN)
            )
        );
        
        // Campo per gestione duplicati
        add_settings_field(
            'imgseo_renamer_handle_duplicates',
            __('Duplicate Handling', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_select_field'),
            'imgseo_renamer_settings',
            'imgseo_renamer_sanitization_section',
            array(
                'name' => 'imgseo_renamer_handle_duplicates',
                'description' => __('How to handle duplicate filenames', IMGSEO_TEXT_DOMAIN),
                'options' => array(
                    'increment' => __('Add sequential number (file-1.jpg, file-2.jpg)', IMGSEO_TEXT_DOMAIN),
                    'timestamp' => __('Add timestamp (file-1679419361.jpg)', IMGSEO_TEXT_DOMAIN),
                    'fail' => __('Do not rename if already exists', IMGSEO_TEXT_DOMAIN),
                )
            )
        );
        
        // ========== CAMPI PER SEZIONE INTEGRAZIONE ==========
        
        // Campo per supporto Elementor
        add_settings_field(
            'imgseo_renamer_elementor_support',
            __('Elementor Support', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_checkbox_field'),
            'imgseo_renamer_settings',
            'imgseo_renamer_integration_section',
            array(
                'name' => 'imgseo_renamer_elementor_support',
                'description' => __('Update image references in Elementor', IMGSEO_TEXT_DOMAIN)
            )
        );
        
        // Campo per supporto Visual Composer
        add_settings_field(
            'imgseo_renamer_visualcomposer_support',
            __('Visual Composer / WPBakery Support', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_checkbox_field'),
            'imgseo_renamer_settings',
            'imgseo_renamer_integration_section',
            array(
                'name' => 'imgseo_renamer_visualcomposer_support',
                'description' => __('Update image references in Visual Composer / WPBakery', IMGSEO_TEXT_DOMAIN)
            )
        );
        
        // Campo per supporto Divi
        add_settings_field(
            'imgseo_renamer_divi_support',
            __('Divi Support', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_checkbox_field'),
            'imgseo_renamer_settings',
            'imgseo_renamer_integration_section',
            array(
                'name' => 'imgseo_renamer_divi_support',
                'description' => __('Update image references in Divi', IMGSEO_TEXT_DOMAIN)
            )
        );
        
        // Campo per supporto Beaver Builder
        add_settings_field(
            'imgseo_renamer_beaver_support',
            __('Beaver Builder Support', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_checkbox_field'),
            'imgseo_renamer_settings',
            'imgseo_renamer_integration_section',
            array(
                'name' => 'imgseo_renamer_beaver_support',
                'description' => __('Update image references in Beaver Builder', IMGSEO_TEXT_DOMAIN)
            )
        );
    }
    
    /**
     * Render the general settings section
     */
    public function render_general_section() {
        echo '<p>' . __('General settings for the image renaming functionality.', IMGSEO_TEXT_DOMAIN) . '</p>';
    }
    
    /**
     * Render the sanitization settings section
     */
    public function render_sanitization_section() {
        echo '<p>' . __('Configure how to sanitize file names during renaming.', IMGSEO_TEXT_DOMAIN) . '</p>';
    }
    
    /**
     * Render the AI settings section
     */
    public function render_ai_section() {
        echo '<p>' . __('Configure how AI generates filenames for your images.', IMGSEO_TEXT_DOMAIN) . '</p>';
    }
    
    /**
     * Render the integration settings section
     */
    public function render_integration_section() {
        echo '<p>' . __('Configure integration with page builders and other plugins.', IMGSEO_TEXT_DOMAIN) . '</p>';
    }
    
    /**
     * Render the log retention field
     */
    public function render_log_retention_field() {
        $log_retention_days = get_option('imgseo_log_retention_days', 30);
        ?>
        <input type="number" name="imgseo_log_retention_days" value="<?php echo esc_attr($log_retention_days); ?>" min="1" max="365" />
        <p class="description"><?php _e('Number of days to keep rename operation logs.', IMGSEO_TEXT_DOMAIN); ?></p>
        <?php
    }
    
    /**
     * Render a checkbox field
     */
    public function render_checkbox_field($args) {
        $name = $args['name'];
        $description = isset($args['description']) ? $args['description'] : '';
        $value = get_option($name, 1);
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr($name); ?>" value="1" <?php checked(1, $value); ?> />
            <?php echo esc_html($description); ?>
        </label>
        <?php
    }
    
    /**
     * Render a number field
     */
    public function render_number_field($args) {
        $name = $args['name'];
        $description = isset($args['description']) ? $args['description'] : '';
        $min = isset($args['min']) ? $args['min'] : 1;
        $max = isset($args['max']) ? $args['max'] : 100;
        $value = get_option($name, $min);
        ?>
        <input type="number" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" min="<?php echo esc_attr($min); ?>" max="<?php echo esc_attr($max); ?>" />
        <p class="description"><?php echo esc_html($description); ?></p>
        <?php
    }
    
    /**
     * Render a text field
     */
    public function render_text_field($args) {
        $name = $args['name'];
        $description = isset($args['description']) ? $args['description'] : '';
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';
        $value = get_option($name, $placeholder);
        ?>
        <input type="text" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="<?php echo esc_attr($placeholder); ?>" />
        <p class="description"><?php echo esc_html($description); ?></p>
        <?php
    }
    
    /**
     * Render a select field
     */
    public function render_select_field($args) {
        $name = $args['name'];
        $description = isset($args['description']) ? $args['description'] : '';
        $options = isset($args['options']) ? $args['options'] : array();
        $value = get_option($name, '');
        ?>
        <select name="<?php echo esc_attr($name); ?>">
            <?php foreach ($options as $option_value => $option_label) : ?>
                <option value="<?php echo esc_attr($option_value); ?>" <?php selected($value, $option_value); ?>><?php echo esc_html($option_label); ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php echo esc_html($description); ?></p>
        <?php
    }
    
    /**
     * Get log retention days
     */
    public function get_log_retention_days() {
        return get_option('imgseo_log_retention_days', 30);
    }
    
    /**
     * Get a specific renamer setting
     * 
     * @param string $key Setting key (without prefix)
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    public function get_setting($key, $default = '') {
        $option_name = 'imgseo_renamer_' . $key;
        return get_option($option_name, $default);
    }
    
    /**
     * Verifica se un'impostazione di checkbox è abilitata
     * 
     * @param string $key Setting key (without prefix)
     * @param bool $default Default value
     * @return bool Setting enabled
     */
    public function is_enabled($key, $default = true) {
        return (bool) $this->get_setting($key, $default ? 1 : 0);
    }
}
