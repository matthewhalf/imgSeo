<?php

/**

 * Class ImgSEO_Settings

 * Manages all plugin settings and admin pages

 */

class ImgSEO_Settings {



/**

 * Singleton instance

 */

private static $instance = null;



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

 * Constructor

 */

private function __construct() {

    // Register settings

    add_action('admin_init', array($this, 'register_settings'));

    add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));



    add_action('wp_footer', array($this, 'add_footer_badge'));



    $this->register_shortcodes();



    // Add redirect filter to active tab

    add_filter('wp_redirect', array($this, 'redirect_to_active_tab'), 10, 2);

}



    // Il metodo add_admin_menu è stato rimosso e spostato in ImgSEO_Menu_Manager



    /**

     * Registers all plugin settings

     */

    public function register_settings() {

        // API Section

        register_setting('imgseo_api_settings', 'imgseo_api_key');

        register_setting('imgseo_api_settings', 'imgseo_api_verified');



        // Custom Prompt Section
        register_setting('imgseo_prompt_settings', 'imgseo_custom_prompt', array(
            'default' => 'Carefully analyze the image and generate an SEO-friendly alt text that accurately describes the main visual elements. {page_title_info} {image_name_info} Include relevant keywords extracted from the page title and file name when available. Describe the image concisely yet comprehensively in {language}, using exactly {max_characters} characters or getting as close as possible to this limit. The description should be natural, informative, and optimized for search engines. Provide only the alt text without quotation marks, containing apostrophes, or other textual decorations.'
        ));
        
        // WooCommerce Product Prompt Section
        register_setting('imgseo_prompt_settings', 'imgseo_woocommerce_prompt', array(
            'default' => 'Generate alt text for this product image: {product_name} by {product_brand}\n\nContext: {product_short_description} - {product_categories} - {product_price} {on_sale} - {product_attributes}\n\nDescribe the visual elements shown (colors, style, angle, details) while naturally incorporating the product name. Keep it descriptive, SEO-friendly, under {max_characters} characters in {language}. Focus on what customers would want to know about this product image.'
        ));
        
        // Enable WooCommerce Specific Prompt
        register_setting('imgseo_prompt_settings', 'imgseo_enable_woocommerce_prompt', array(
            'default' => 0,
            'sanitize_callback' => 'absint'
        ));



        // General Section

        register_setting('imgseo_general_settings', 'imgseo_language', array(

            'default' => 'english'

        ));

        register_setting('imgseo_general_settings', 'imgseo_max_characters', array(

            'default' => 125,

            'sanitize_callback' => 'absint'

        ));

        register_setting('imgseo_general_settings', 'imgseo_include_page_title', array(

            'default' => 1,

            'sanitize_callback' => 'absint'

        ));

        register_setting('imgseo_general_settings', 'imgseo_include_image_name', array(

            'default' => 1,

            'sanitize_callback' => 'absint'

        ));

        register_setting('imgseo_general_settings', 'imgseo_overwrite', array(

            'default' => 0,

            'sanitize_callback' => 'absint'

        ));



        // Automatic Generation Option (moved to general settings)

        register_setting('imgseo_general_settings', 'imgseo_auto_generate', array(

            'default' => 0,

            'sanitize_callback' => 'absint'

        ));

        // Always use base64 option to bypass hotlinking protections and Cloudflare restrictions
        register_setting('imgseo_general_settings', 'imgseo_always_use_base64', array(
            'default' => 0,
            'sanitize_callback' => 'absint'
        ));

        // Footer badge settings

        register_setting('imgseo_general_settings', 'imgseo_footer_badge', array(

            'default' => 0,

            'sanitize_callback' => 'absint'

        ));



        register_setting('imgseo_general_settings', 'imgseo_support_link', array(

            'default' => 0,

            'sanitize_callback' => 'absint'

        ));



        // Field Updates Section

        register_setting('imgseo_update_settings', 'imgseo_update_title', array(

            'default' => 0,

            'sanitize_callback' => 'absint'

        ));

        register_setting('imgseo_update_settings', 'imgseo_update_caption', array(

            'default' => 0,

            'sanitize_callback' => 'absint'

        ));

        register_setting('imgseo_update_settings', 'imgseo_update_description', array(

            'default' => 0,

            'sanitize_callback' => 'absint'

        ));



        // Add sections

        add_settings_section(

            'imgseo_api_section',

            __('API Settings', IMGSEO_TEXT_DOMAIN),

            array($this, 'render_api_section'),

            'imgseo_api_settings'

        );



        // Custom Prompt Section
        add_settings_section(
            'imgseo_prompt_section',
            __('Custom Prompt', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_prompt_section'),
            'imgseo_prompt_settings'
        );

        // Custom Prompt Field
        add_settings_field(
            'imgseo_custom_prompt',
            __('Custom Prompt', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_custom_prompt_field'),
            'imgseo_prompt_settings',
            'imgseo_prompt_section'
        );
        
        // WooCommerce Prompt Section
        add_settings_section(
            'imgseo_woocommerce_prompt_section',
            __('WooCommerce Product Prompt', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_woocommerce_prompt_section'),
            'imgseo_prompt_settings'
        );
        
        // Enable WooCommerce Prompt Field
        add_settings_field(
            'imgseo_enable_woocommerce_prompt',
            __('Enable WooCommerce Prompt', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_enable_woocommerce_prompt_field'),
            'imgseo_prompt_settings',
            'imgseo_woocommerce_prompt_section'
        );
        
        // WooCommerce Prompt Field
        add_settings_field(
            'imgseo_woocommerce_prompt',
            __('WooCommerce Product Prompt', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_woocommerce_prompt_field'),
            'imgseo_prompt_settings',
            'imgseo_woocommerce_prompt_section'
        );



        add_settings_section(

            'imgseo_general_section',

            __('General Settings', IMGSEO_TEXT_DOMAIN),

            array($this, 'render_general_section'),

            'imgseo_general_settings'

        );





        add_settings_section(

            'imgseo_update_section',

            __('Field Updates', IMGSEO_TEXT_DOMAIN),

            array($this, 'render_update_section'),

            'imgseo_update_settings'

        );



        // Add fields

        // API Section

        add_settings_field(

            'imgseo_api_key',

            __('ImgSEO Token', IMGSEO_TEXT_DOMAIN),

            array($this, 'render_api_key_field'),

            'imgseo_api_settings',

            'imgseo_api_section'

        );



        add_settings_field(

            'imgseo_credits',

            __('Available Credits', IMGSEO_TEXT_DOMAIN),

            array($this, 'render_credits_field'),

            'imgseo_api_settings',

            'imgseo_api_section'

        );



        // General Section

        add_settings_field(

            'imgseo_language',

            __('Language', IMGSEO_TEXT_DOMAIN),

            array($this, 'render_language_field'),

            'imgseo_general_settings',

            'imgseo_general_section'

        );



        add_settings_field(

            'imgseo_max_characters',

            __('Maximum Characters', IMGSEO_TEXT_DOMAIN),

            array($this, 'render_max_characters_field'),

            'imgseo_general_settings',

            'imgseo_general_section'

        );



        add_settings_field(

            'imgseo_include_page_title',

            __('Include Page Title', IMGSEO_TEXT_DOMAIN),

            array($this, 'render_include_page_title_field'),

            'imgseo_general_settings',

            'imgseo_general_section'

        );



        add_settings_field(

            'imgseo_include_image_name',

            __('Include Image Filename', IMGSEO_TEXT_DOMAIN),

            array($this, 'render_include_image_name_field'),

            'imgseo_general_settings',

            'imgseo_general_section'

        );



        add_settings_field(

            'imgseo_overwrite',

            __('Overwrite Existing Alt Texts', IMGSEO_TEXT_DOMAIN),

            array($this, 'render_overwrite_field'),

            'imgseo_general_settings',

            'imgseo_general_section'

        );



        // Add automatic generation field to general settings

        add_settings_field(

            'imgseo_auto_generate',

            __('Automatic Generation', IMGSEO_TEXT_DOMAIN),

            array($this, 'render_auto_generate_field'),

            'imgseo_general_settings',

            'imgseo_general_section'

        );



          // Add footer badge field to general settings

          add_settings_field(

            'imgseo_footer_badge',

            __('Footer badge', IMGSEO_TEXT_DOMAIN),

            array($this, 'render_footer_badge_field'),

            'imgseo_general_settings',

            'imgseo_general_section'

        );


        // Add always use base64 field to general settings
        add_settings_field(
            'imgseo_always_use_base64',
            __('Force base64 image transfer', IMGSEO_TEXT_DOMAIN),
            array($this, 'render_always_use_base64_field'),
            'imgseo_general_settings',
            'imgseo_general_section'
        );





        // Update Fields Section

        add_settings_field(

            'imgseo_update_fields',

            __('Update Other Fields', IMGSEO_TEXT_DOMAIN),

            array($this, 'render_update_fields'),

            'imgseo_update_settings',

            'imgseo_update_section'

        );


    }



    /**

     * Function to redirect to the active tab after saving

     */

    public function redirect_to_active_tab($location, $status) {

        // Check if we're saving the plugin settings

        if (

            strpos($location, 'options.php') !== false &&

            isset($_POST['imgseo_active_tab']) &&

            isset($_POST['option_page']) &&

            strpos($_POST['option_page'], 'imgseo_') === 0

        ) {

            $active_tab = sanitize_text_field($_POST['imgseo_active_tab']);

            // Build the redirect URL with the active tab

            $redirect_url = add_query_arg(

                array(

                    'page' => 'imgseo',

                    'tab' => $active_tab,

                    'settings-updated' => 'true'

                ),

                admin_url('admin.php')

            );



            return $redirect_url;

        }



        return $location;

    }



    /**

     * Aggiorna le impostazioni tramite AJAX

     */

    public function ajax_update_settings() {

        check_ajax_referer('imgseo_settings_nonce', 'security');



        if (!current_user_can('manage_options')) {

            wp_send_json_error(['message' => 'Non autorizzato']);

        }



        $update_title = isset($_POST['update_title']) ? (bool)$_POST['update_title'] : false;

        $update_caption = isset($_POST['update_caption']) ? (bool)$_POST['update_caption'] : false;

        $update_description = isset($_POST['update_description']) ? (bool)$_POST['update_description'] : false;



        update_option('imgseo_update_title', $update_title ? 1 : 0);

        update_option('imgseo_update_caption', $update_caption ? 1 : 0);

        update_option('imgseo_update_description', $update_description ? 1 : 0);



        wp_send_json_success(['message' => 'Impostazioni aggiornate']);

    }



    /**

     * Carica gli script e gli stili necessari per le pagine di amministrazione

     */

    public function enqueue_admin_assets($hook) {

        // Carica gli asset solo nelle pagine del plugin

        if (strpos($hook, 'imgseo') === false) {

            return;

        }



        // CSS per le pagine admin

        wp_enqueue_style(

            'imgseo-admin-css',

            IMGSEO_PLUGIN_URL . 'assets/css/admin-style.css',

            array(),

            IMGSEO_PLUGIN_VERSION

        );



        // Script per l'API

        wp_enqueue_script(

            'imgseo-api-settings-js',

            IMGSEO_PLUGIN_URL . 'assets/js/imgseo-api-settings.js',

            array('jquery'),

            IMGSEO_PLUGIN_VERSION,

            true

        );



        // Localizza lo script con i parametri

        wp_localize_script('imgseo-api-settings-js', 'ImgSEOSettings', array(

            'ajax_url' => admin_url('admin-ajax.php'),

            'nonce' => wp_create_nonce('imgseo_settings_nonce'),

            'verify_message' => __('Verification in progress...', IMGSEO_TEXT_DOMAIN),

            'success_message' => __('Valid ImgSEO Token!', IMGSEO_TEXT_DOMAIN),

            'error_message' => __('Invalid ImgSEO Token!', IMGSEO_TEXT_DOMAIN),

            'refresh_credits_message' => __('Updating credits...', IMGSEO_TEXT_DOMAIN)

        ));



        // Script per l'admin

        wp_enqueue_script(

            'imgseo-admin-js',

            IMGSEO_PLUGIN_URL . 'assets/js/admin-script.js',

            array('jquery'),

            IMGSEO_PLUGIN_VERSION,

            true

        );



        try {

            // Prepare base data with types explicitly cast to ensure valid JS

            $localize_data = array(

                'ajax_url' => admin_url('admin-ajax.php'),

                'nonce' => wp_create_nonce('imgseo_nonce'),

                'debug' => (bool)WP_DEBUG

            );



            // Apply the filter to allow other components to add data, with error handling

            if (has_filter('imgseo_localize_script')) {

                $filtered_data = apply_filters('imgseo_localize_script', $localize_data);

                // Verify the filter returned an array

                if (is_array($filtered_data)) {

                    $localize_data = $filtered_data;

                } else {

                    error_log('ImgSEO Error: imgseo_localize_script filter did not return an array');

                }

            }



            // Log the data for debugging

            if (WP_DEBUG) {

                error_log('ImgSEO: Localizing script with data: ' . json_encode($localize_data));

            }



            // Localize the script

            wp_localize_script('imgseo-admin-js', 'ImgSEO', $localize_data);

        } catch (Exception $e) {

            // Log any errors but don't break the script

            error_log('ImgSEO Error in script localization: ' . $e->getMessage());



            // Provide minimal working data in case of error

            wp_localize_script('imgseo-admin-js', 'ImgSEO', array(

                'ajax_url' => admin_url('admin-ajax.php'),

                'nonce' => wp_create_nonce('imgseo_nonce')

            ));

        }

    }



    /**

     * Rendering della pagina delle impostazioni

     */

    public function render_settings_page() {

        if (!current_user_can('manage_options')) {

            return;

        }



        // Determina la tab attiva

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'api';

        ?>

        <div class="wrap">

            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>



            <div class="imgseo-tabs">

                <div class="nav-tab-wrapper">

                    <a href="?page=imgseo&tab=api" class="nav-tab <?php echo $active_tab == 'api' ? 'nav-tab-active' : ''; ?>"><?php _e('API Settings', IMGSEO_TEXT_DOMAIN); ?></a>

                    <a href="?page=imgseo&tab=prompt" class="nav-tab <?php echo $active_tab == 'prompt' ? 'nav-tab-active' : ''; ?>"><?php _e('Custom Prompt', IMGSEO_TEXT_DOMAIN); ?></a>

                    <a href="?page=imgseo&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>"><?php _e('General Settings', IMGSEO_TEXT_DOMAIN); ?></a>

                    <a href="?page=imgseo&tab=update" class="nav-tab <?php echo $active_tab == 'update' ? 'nav-tab-active' : ''; ?>"><?php _e('Field Updates', IMGSEO_TEXT_DOMAIN); ?></a>

                </div>





                <div id="tab-api" class="tab-content <?php echo $active_tab == 'api' ? 'active' : ''; ?>">

                    <div class="imgseo-api-wrapper">

                        <?php

                        settings_fields('imgseo_api_settings');

                        do_settings_sections('imgseo_api_settings');

                        ?>

                        <!-- Il pulsante "Salva le modifiche" è stato rimosso perché non necessario in questa tab -->

                        <!-- La API Key viene salvata automaticamente durante la verifica -->

                    </div>

                </div>



                <div id="tab-prompt" class="tab-content <?php echo $active_tab == 'prompt' ? 'active' : ''; ?>">

                    <form method="post" action="options.php">

                        <input type="hidden" name="imgseo_active_tab" value="prompt">

                        <?php

                        settings_fields('imgseo_prompt_settings');

                        do_settings_sections('imgseo_prompt_settings');

                        submit_button();

                        ?>

                    </form>

                </div>



                <div id="tab-general" class="tab-content <?php echo $active_tab == 'general' ? 'active' : ''; ?>">

                    <form method="post" action="options.php">

                        <input type="hidden" name="imgseo_active_tab" value="general">

                        <?php

                        settings_fields('imgseo_general_settings');

                        do_settings_sections('imgseo_general_settings');

                        submit_button();

                        ?>

                    </form>

                </div>





                <div id="tab-update" class="tab-content <?php echo $active_tab == 'update' ? 'active' : ''; ?>">

                    <form method="post" action="options.php">

                        <input type="hidden" name="imgseo_active_tab" value="update">

                        <?php

                        settings_fields('imgseo_update_settings');

                        do_settings_sections('imgseo_update_settings');

                        submit_button();

                        ?>

                    </form>

                </div>



                <div id="tab-renamer" class="tab-content <?php echo $active_tab == 'renamer' ? 'active' : ''; ?>">

                    <form method="post" action="options.php">

                        <input type="hidden" name="imgseo_active_tab" value="renamer">

                        <?php

                        settings_fields('imgseo_renamer_settings');

                        do_settings_sections('imgseo_renamer_settings');

                        submit_button();

                        ?>

                    </form>

                </div>

            </div>

        </div>

        <?php

    }



    /**

     * Rendering della pagina di generazione in bulk

     */

    public function render_bulk_page() {

        if (!current_user_can('manage_options')) {

            return;

        }



        include IMGSEO_DIRECTORY_PATH . 'templates/bulk-page.php';

    }



    /**

     * Render API section

     */

    public function render_api_section() {

        echo '<p>' . __('Configure your ImgSEO Token to access the ImgSEO service.', IMGSEO_TEXT_DOMAIN) . '</p>';

    }



    /**

     * Render general section

     */

    public function render_general_section() {

        echo '<p>' . __('Configure general settings for alt text generation.', IMGSEO_TEXT_DOMAIN) . '</p>';

    }



    /**

     * Render update fields section

     */

    public function render_update_section() {

        echo '<p>' . __('Configure which other fields to update besides alt text.', IMGSEO_TEXT_DOMAIN) . '</p>';

    }



    /**

     * Render custom prompt section

     */

    public function render_prompt_section() {

        echo '<p>' . __('Customize the prompt used to generate alt text with AI. You can use the following dynamic variables:', IMGSEO_TEXT_DOMAIN) . '</p>';



        echo '<ul class="imgseo-variables-list" style="background: #f9f9f9; padding: 15px 25px; border-left: 4px solid #0073aa; margin-bottom: 20px;">';

        echo '<li><code>{language}</code> - ' . __('Will be replaced with the selected language', IMGSEO_TEXT_DOMAIN) . '</li>';

        echo '<li><code>{max_characters}</code> - ' . __('Will be replaced with the maximum number of characters', IMGSEO_TEXT_DOMAIN) . '</li>';

        echo '<li><code>{page_title_info}</code> - ' . __('Will be replaced with "the page title is: [title]" if the option is active', IMGSEO_TEXT_DOMAIN) . '</li>';

        echo '<li><code>{image_name_info}</code> - ' . __('Will be replaced with "the image name is: [filename]" if the option is active', IMGSEO_TEXT_DOMAIN) . '</li>';

        echo '</ul>';

    }



    /**
     * Render custom prompt field
     */
    public function render_custom_prompt_field() {
        $default_prompt = 'Carefully analyze the image and generate an SEO-friendly alt text that accurately describes the main visual elements. {page_title_info} {image_name_info} Include relevant keywords extracted from the page title and file name when available. Describe the image concisely yet comprehensively in {language}, using exactly {max_characters} characters or getting as close as possible to this limit. The description should be natural, informative, and optimized for search engines. Provide only the alt text without quotation marks, containing apostrophes, or other textual decorations.';
        $custom_prompt = get_option('imgseo_custom_prompt', $default_prompt);
        ?>
        <textarea name="imgseo_custom_prompt" id="imgseo_custom_prompt" rows="6" cols="80" class="large-text code"><?php echo esc_textarea($custom_prompt); ?></textarea>

        <p class="description">
            <?php _e('Customize the prompt sent to the AI to generate alt text. Use the dynamic variables listed above to make the prompt more effective.', IMGSEO_TEXT_DOMAIN); ?>
        </p>

        <button type="button" id="reset_prompt" class="button button-secondary" style="margin-top: 10px;">
            <span class="dashicons dashicons-image-rotate" style="margin-top: 3px;"></span>
            <?php _e('Reset Default Prompt', IMGSEO_TEXT_DOMAIN); ?>
        </button>

        <script>
            jQuery(document).ready(function($) {
                $('#reset_prompt').on('click', function() {
                    if (confirm('<?php _e('Are you sure you want to reset to the default prompt? This operation will overwrite the current prompt.', IMGSEO_TEXT_DOMAIN); ?>')) {
                        $('#imgseo_custom_prompt').val('Carefully analyze the image and generate an SEO-friendly alt text that accurately describes the main visual elements. {page_title_info} {image_name_info} Include relevant keywords extracted from the page title and file name when available. Describe the image concisely yet comprehensively in {language}, using exactly {max_characters} characters or getting as close as possible to this limit. The description should be natural, informative, and optimized for search engines. Provide only the alt text without quotation marks, containing apostrophes, or other textual decorations.');
                    }
                });
            });
        </script>
        <?php
    }
    
    /**
      * Render WooCommerce prompt section
      */
     public function render_woocommerce_prompt_section() {
         ?>
         <p>
             <?php _e('Configure a specific prompt for WooCommerce product images. When enabled, this prompt will be used for all images attached to WooCommerce products.', IMGSEO_TEXT_DOMAIN); ?>
         </p>
         <p>
             <?php _e('You can use the following dynamic variables in your prompt:', IMGSEO_TEXT_DOMAIN); ?>
         </p>
         <ul class="imgseo-dynamic-variables">
             <li><code>{language}</code> - <?php _e('Will be replaced with the selected language', IMGSEO_TEXT_DOMAIN); ?></li>
             <li><code>{max_characters}</code> - <?php _e('Will be replaced with the maximum characters setting', IMGSEO_TEXT_DOMAIN); ?></li>
             <li><code>{product_name}</code> - <?php _e('Will be replaced with the product name', IMGSEO_TEXT_DOMAIN); ?></li>
             <li><code>{product_brand}</code> - <?php _e('Will be replaced with the product brand', IMGSEO_TEXT_DOMAIN); ?></li>
             <li><code>{product_short_description}</code> - <?php _e('Will be replaced with the product short description', IMGSEO_TEXT_DOMAIN); ?></li>
             <li><code>{product_categories}</code> - <?php _e('Will be replaced with the product categories', IMGSEO_TEXT_DOMAIN); ?></li>
             <li><code>{product_price}</code> - <?php _e('Will be replaced with the product price', IMGSEO_TEXT_DOMAIN); ?></li>
             <li><code>{on_sale}</code> - <?php _e('Will be replaced with sale status', IMGSEO_TEXT_DOMAIN); ?></li>
             <li><code>{product_attributes}</code> - <?php _e('Will be replaced with the product attributes', IMGSEO_TEXT_DOMAIN); ?></li>
         </ul>
         <?php
     }
    
    /**
     * Render enable WooCommerce prompt field
     */
    public function render_enable_woocommerce_prompt_field() {
        $enable_woocommerce_prompt = get_option('imgseo_enable_woocommerce_prompt', 0);
        ?>
        <label>
            <input type="checkbox" name="imgseo_enable_woocommerce_prompt" value="1" <?php checked(1, $enable_woocommerce_prompt); ?> />
            <?php _e('Use a specific prompt for WooCommerce product images', IMGSEO_TEXT_DOMAIN); ?>
        </label>
        <p class="description">
            <?php _e('When enabled, images attached to WooCommerce products will use the product-specific prompt below.', IMGSEO_TEXT_DOMAIN); ?>
        </p>
        <?php
    }
    
    /**
      * Render WooCommerce prompt field
      */
     public function render_woocommerce_prompt_field() {
         $default_prompt = 'Generate alt text for this product image: {product_name} by {product_brand}\n\nContext: {product_short_description} - {product_categories} - {product_price} {on_sale} - {product_attributes}\n\nDescribe the visual elements shown (colors, style, angle, details) while naturally incorporating the product name. Keep it descriptive, SEO-friendly, under {max_characters} characters in {language}. Focus on what customers would want to know about this product image.';
         $woocommerce_prompt = get_option('imgseo_woocommerce_prompt', $default_prompt);
         ?>
         <textarea name="imgseo_woocommerce_prompt" id="imgseo_woocommerce_prompt" rows="6" cols="80" class="large-text code"><?php echo esc_textarea($woocommerce_prompt); ?></textarea>

         <p class="description">
             <?php _e('Customize the prompt sent to the AI to generate alt text for WooCommerce product images. Use the dynamic variables listed above to make the prompt more effective.', IMGSEO_TEXT_DOMAIN); ?>
         </p>

         <button type="button" id="reset_woocommerce_prompt" class="button button-secondary" style="margin-top: 10px;">
             <span class="dashicons dashicons-image-rotate" style="margin-top: 3px;"></span>
             <?php _e('Reset Default Product Prompt', IMGSEO_TEXT_DOMAIN); ?>
         </button>

         <script>
             jQuery(document).ready(function($) {
                 $('#reset_woocommerce_prompt').on('click', function() {
                     if (confirm('<?php _e('Are you sure you want to reset to the default WooCommerce product prompt? This operation will overwrite the current prompt.', IMGSEO_TEXT_DOMAIN); ?>')) {
                         $('#imgseo_woocommerce_prompt').val('Generate alt text for this product image: {product_name} by {product_brand}\\n\\nContext: {product_short_description} - {product_categories} - {product_price} {on_sale} - {product_attributes}\\n\\nDescribe the visual elements shown (colors, style, angle, details) while naturally incorporating the product name. Keep it descriptive, SEO-friendly, under {max_characters} characters in {language}. Focus on what customers would want to know about this product image.');
                     }
                 });
             });
         </script>
         <?php
     }



    /**

     * Rendering del campo API Key

     */

    public function render_api_key_field() {

        $api_key = get_option('imgseo_api_key', '');

        $api_verified = !empty($api_key) && get_option('imgseo_api_verified', false);

        ?>

        <div class="imgseo-api-key-container">

            <div class="api-key-input-group">

                <input type="password"

                       name="imgseo_api_key"

                       id="imgseo_api_key"

                       value="<?php echo esc_attr($api_key); ?>"

                       class="regular-text"

                       placeholder="<?php _e('Enter your ImgSEO Token', IMGSEO_TEXT_DOMAIN); ?>"

                       <?php echo $api_verified ? 'readonly' : ''; ?>>



                <button type="button" id="toggle_api_key_visibility" class="button button-secondary">

                    <span class="dashicons dashicons-visibility"></span>

                </button>

            </div>



            <!-- Spazio tra il campo di input e i pulsanti d'azione -->

            <div style="margin-top: 10px;">

                <div class="api-key-actions">

                    <?php if (!$api_verified): ?>

                        <button type="button" id="verify_api_key" class="button button-primary">

                            <span class="dashicons dashicons-yes"></span> <?php _e('Verify ImgSEO Token', IMGSEO_TEXT_DOMAIN); ?>

                        </button>

                    <?php else: ?>

                        <button type="button" id="disconnect_api_key" class="button button-secondary">

                            <span class="dashicons dashicons-no-alt"></span> <?php _e('Disconnect', IMGSEO_TEXT_DOMAIN); ?>

                        </button>



                        <button type="button" id="verify_api_key" class="button button-secondary">

                            <span class="dashicons dashicons-update"></span> <?php _e('Verify again', IMGSEO_TEXT_DOMAIN); ?>

                        </button>

                    <?php endif; ?>

                </div>

            </div>



            <div id="api_key_status" class="api-key-status">

                <?php if ($api_verified): ?>

                    <div class="status-message success">

                        <span class="dashicons dashicons-yes-alt"></span>

                        <?php _e('ImgSEO Token Verified!', IMGSEO_TEXT_DOMAIN); ?>

                        <?php

                        $plan = get_option('imgseo_plan', '');

                        if (!empty($plan)):

                            echo ' Plan:  <strong>' . esc_html($plan) . '</strong>';

                        endif;

                        ?>

                    </div>

                <?php else: ?>

                    <div class="status-message info">

                        <span class="dashicons dashicons-info"></span>

                        <?php _e('Enter your ImgSEO Token and click on "Verify ImgSEO Token".', IMGSEO_TEXT_DOMAIN); ?>

                    </div>



                    <p class="register-link">
                        <?php _e('You don\'t have an ImgSEO Token?', IMGSEO_TEXT_DOMAIN); ?>

                        <a href="https://dashboard.imgseo.net/login" target="_blank" class="button button-link">
                            <span class="dashicons dashicons-external"></span>
                            <?php _e('Register on ImgSEO. You\'ll receive 30 free credits and 10 free credits daily (if your balance falls below 10).', IMGSEO_TEXT_DOMAIN); ?>
                        </a>
                    </p>

                <?php endif; ?>

            </div>

        </div>

        <?php

    }



    /**

     * Rendering del campo crediti

     */

    public function render_credits_field() {

        $api_key = get_option('imgseo_api_key', '');

        $api_verified = !empty($api_key) && get_option('imgseo_api_verified', false);

        $credits = get_option('imgseo_credits', 0);

        $last_check = get_option('imgseo_last_check', 0);



        if (!$api_verified) {

            echo '<p>' . __('Verify your ImgSEO Token to view available credits.', IMGSEO_TEXT_DOMAIN) . '</p>';

            return;

        }



        $last_check_time = '';

        if ($last_check > 0) {

            $last_check_time = human_time_diff($last_check, time()) . ' ' . __('ago', IMGSEO_TEXT_DOMAIN);

        }

        ?>

        <div class="imgseo-credits-container">

            <div id="imgseo_credits_display" class="credits-count <?php echo $credits <= 10 ? 'low-credits' : ''; ?>">

                <?php echo esc_html($credits); ?>

            </div>

            <div class="credits-label"><?php _e('available credits', IMGSEO_TEXT_DOMAIN); ?></div>



            <div class="credits-actions">

                <button type="button" id="refresh_credits" class="button button-primary">

                    <span class="dashicons dashicons-update"></span> <?php _e('Refresh Credits', IMGSEO_TEXT_DOMAIN); ?>

                </button>



                <a href="https://api.imgseo.net/purchase" target="_blank" class="button button-secondary">

                    <span class="dashicons dashicons-cart"></span> <?php _e('Purchase credits', IMGSEO_TEXT_DOMAIN); ?>

                </a>

            </div>



            <?php if (!empty($last_check_time)): ?>

                <p class="description" id="last_credits_check">

                    <span class="dashicons dashicons-clock"></span> <?php echo __('Last update:', IMGSEO_TEXT_DOMAIN) . ' ' . $last_check_time; ?>

                </p>

            <?php endif; ?>



            <?php if ($credits <= 10 && $credits > 0): ?>

                <div class="credits-warning warning">

                    <span class="dashicons dashicons-warning"></span>

                    <?php _e('Your credits are running low. Consider purchasing additional credits.', IMGSEO_TEXT_DOMAIN); ?>

                </div>

            <?php elseif ($credits <= 0): ?>

                <div class="credits-warning error">

                    <span class="dashicons dashicons-dismiss"></span>

                    <?php _e('You have no available credits! Purchase new credits to continue using the service.', IMGSEO_TEXT_DOMAIN); ?>

                </div>

            <?php else: ?>

                <div class="credits-status success">

                    <span class="dashicons dashicons-yes-alt"></span>

                    <?php _e('You have sufficient credits to generate alternative texts.', IMGSEO_TEXT_DOMAIN); ?>

                </div>

            <?php endif; ?>

        </div>

        <?php

    }



    /**

     * Rendering del campo lingua

     */

    public function render_language_field() {

        $languages = [

            'english' => 'English',

            'italiano' => 'Italiano',

            'japanese' => '日本語',

            'korean' => '한국어',

            'arabic' => 'العربية',

            'bahasa_indonesia' => 'Bahasa Indonesia',

            'bengali' => 'বাংলা',

            'bulgarian' => 'Български',

            'chinese_simplified' => '中文 (简体)',

            'chinese_traditional' => '中文 (繁體)',

            'croatian' => 'Hrvatski',

            'czech' => 'Čeština',

            'danish' => 'Dansk',

            'dutch' => 'Nederlands',

            'estonian' => 'Eesti',

            'farsi' => 'فارسی',

            'finnish' => 'Suomi',

            'french' => 'Français',

            'german' => 'Deutsch',

            'gujarati' => 'ગુજરાતી',

            'greek' => 'Ελληνικά',

            'hebrew' => 'עברית',

            'hindi' => 'हिन्दी',

            'hungarian' => 'Magyar',

            'kannada' => 'ಕನ್ನಡ',

            'latvian' => 'Latviešu',

            'lithuanian' => 'Lietuvių',

            'malayalam' => 'മലയാളം',

            'marathi' => 'मराठी',

            'norwegian' => 'Norsk',

            'polish' => 'Polski',

            'portuguese' => 'Português',

            'romanian' => 'Română',

            'russian' => 'Русский',

            'serbian' => 'Српски',

            'slovak' => 'Slovenčina',

            'slovenian' => 'Slovenščina',

            'spanish' => 'Español',

            'swahili' => 'Kiswahili',

            'swedish' => 'Svenska',

            'tamil' => 'தமிழ்',

            'telugu' => 'తెలుగు',

            'thai' => 'ไทย',

            'turkish' => 'Türkçe',

            'ukrainian' => 'Українська',

            'urdu' => 'اردو',

            'vietnamese' => 'Tiếng Việt'

        ];



        $selected = get_option('imgseo_language', 'english');

        ?>

        <select name="imgseo_language">

            <?php foreach ($languages as $value => $label): ?>

                <option value="<?php echo esc_attr($value); ?>" <?php selected($selected, $value); ?>>

                    <?php echo esc_html($label); ?>

                </option>

            <?php endforeach; ?>

        </select>

        <p class="description"><?php _e('Select the language in which alternative texts will be generated.', IMGSEO_TEXT_DOMAIN); ?></p>

        <?php

    }



    /**

     * Rendering del campo caratteri massimi

     */

    public function render_max_characters_field() {

        $max_characters = get_option('imgseo_max_characters', 125);

        ?>

        <input type="number" name="imgseo_max_characters" value="<?php echo esc_attr($max_characters); ?>" min="50" max="300" />

        <p class="description"><?php _e('Maximum length of the generated alternative text. Recommended value: 125 characters.', IMGSEO_TEXT_DOMAIN); ?></p>

        <?php

    }



    /**

     * Rendering del campo includi titolo pagina

     */

    public function render_include_page_title_field() {

        $include_page_title = get_option('imgseo_include_page_title', 1);

        ?>

        <input type="checkbox" name="imgseo_include_page_title" id="imgseo_include_page_title" value="1" <?php checked($include_page_title, 1); ?> />

        <label for="imgseo_include_page_title"><?php _e('Include page title in prompt', IMGSEO_TEXT_DOMAIN); ?></label>

        <p class="description"><?php _e('If selected, the title of the page containing the image will be included in the prompt to generate a more contextualized alternative text.', IMGSEO_TEXT_DOMAIN); ?></p>

        <?php

    }



    /**

     * Rendering del campo includi nome immagine

     */

    public function render_include_image_name_field() {

        $include_image_name = get_option('imgseo_include_image_name', 1);

        ?>

        <input type="checkbox" name="imgseo_include_image_name" id="imgseo_include_image_name" value="1" <?php checked($include_image_name, 1); ?> />

        <label for="imgseo_include_image_name"><?php _e('Include image filename in prompt', IMGSEO_TEXT_DOMAIN); ?></label>

        <p class="description"><?php _e('If selected, the image filename will be included in the prompt, useful if filenames contain relevant information.', IMGSEO_TEXT_DOMAIN); ?></p>

        <?php

    }



    /**

     * Rendering del campo sovrascrivi

     */

    public function render_overwrite_field() {

        $overwrite = get_option('imgseo_overwrite', 0);

        ?>

        <input type="checkbox" name="imgseo_overwrite" id="imgseo_overwrite" value="1" <?php checked($overwrite, 1); ?> />

        <label for="imgseo_overwrite"><?php _e('Overwrite existing alt texts', IMGSEO_TEXT_DOMAIN); ?></label>

        <p class="description"><?php _e('If selected, the plugin will overwrite existing alt texts during batch processing or automatic generation.', IMGSEO_TEXT_DOMAIN); ?></p>

        <?php

    }



    /**

     * Rendering del campo generazione automatica

     */

    public function render_auto_generate_field() {

        $auto_generate = get_option('imgseo_auto_generate', 0);

        ?>

        <input type="checkbox" name="imgseo_auto_generate" id="imgseo_auto_generate" value="1" <?php checked($auto_generate, 1); ?> />

       


        <label for="imgseo_auto_generate"><?php _e('Automatically generate alt text when uploading images', IMGSEO_TEXT_DOMAIN); ?></label>

        <p class="description"><?php _e('When enabled, alt text will be automatically generated for each newly uploaded image.', IMGSEO_TEXT_DOMAIN); ?></p>

        <?php

    }



    /**
     * Rendering del campo forza utilizzo base64
     */
    public function render_always_use_base64_field() {
        $always_use_base64 = get_option('imgseo_always_use_base64', 0);
        ?>
        <input type="checkbox" name="imgseo_always_use_base64" id="imgseo_always_use_base64" value="1" <?php checked($always_use_base64, 1); ?> />
        <label for="imgseo_always_use_base64"><?php _e('Force base64 image transfer', IMGSEO_TEXT_DOMAIN); ?></label>
        <p class="description"><?php _e('When enabled, images will always be sent to the service in base64 format instead of as URLs. Useful for sites with anti-hotlinking protection or with Cloudflare active.', IMGSEO_TEXT_DOMAIN); ?></p>
        <?php
    }


       /**

     * Rendering del campo aggiungi badge nel footer

     */

public function render_footer_badge_field() {

    


    $footer_badge = get_option('imgseo_footer_badge', 0);
    $support_link = get_option('imgseo_support_link', 0);
    ?>

    <input type="checkbox" name="imgseo_footer_badge" id="imgseo_footer_badge" value="1" <?php checked($footer_badge, 1); ?> />
    <label for="imgseo_footer_badge"><?php _e('Display Accessibility Compliance Badge', IMGSEO_TEXT_DOMAIN); ?></label>

    <p class="description">
        <?php _e('The badge shows your site\'s compliance with accessibility standards for images. When less than 95% of your images have proper alt text, the badge will appear without a checkmark. Once you reach or exceed 95% alt text coverage, the badge will display with a green checkmark, demonstrating your commitment to accessibility. You can also use the shortcode [imgseo_badge] to place the badge anywhere on your site.', IMGSEO_TEXT_DOMAIN); ?>
    </p>

    <br>

    <input type="checkbox" name="imgseo_support_link" id="imgseo_support_link" value="1" <?php checked($support_link, 1); ?> />
    <label for="imgseo_support_link"><?php _e('Remove ImgSEO reference link', IMGSEO_TEXT_DOMAIN); ?></label>

    <p class="description">
        <?php _e('When checked, this option will remove the link to ImgSEO\'s accessibility guidelines from the badge. By keeping this unchecked, you help support ImgSEO\'s mission of promoting web accessibility standards while providing visitors with access to valuable resources about image accessibility compliance.', IMGSEO_TEXT_DOMAIN); ?>
    </p>

    <?php
}




    public function render_badge_svg() {
    $support_link = get_option('imgseo_support_link', 0);

    // Ottieni tutte le immagini
    $args = array(
        'post_type'      => 'attachment',
        'post_mime_type' => 'image',
        'posts_per_page' => -1,
        'post_status'    => 'inherit'
    );

    $query  = new WP_Query($args);
    $images = $query->posts;
    $total_images = count($images);

    // Se non ci sono immagini, non mostrare nulla
    if ($total_images === 0) {
        return '';
    }

    // Conta immagini con alt text
    $with_alt = 0;
    foreach ($images as $image) {
        $alt_text = get_post_meta($image->ID, '_wp_attachment_image_alt', true);
        if (!empty(trim($alt_text))) {
            $with_alt++;
        }
    }

    // Calcola percentuale
    $percent_with_alt = ($with_alt / $total_images) * 100;

    // Mostra SVG solo se almeno il 90% delle immagini ha alt
    $show_svg = $percent_with_alt >= 90;

    ob_start();
    ?>
    <div style="margin: 20px auto; position:relative; width:fit-content;">
        <?php if ($support_link != 1): // se il checkbox NON è selezionato, mostra il link ?>
            <a href="https://imgseo.net/web-image-accessibility/" target="_blank" rel="noopener">
        <?php endif; ?>

        <img src="<?php echo plugin_dir_url(dirname(__FILE__)) . 'assets/img/w3c-badge-2-1.png'; ?>"
             alt="alt text compliant" width="150px">

        <?php if ($show_svg): ?>
            <svg style="position:absolute; right:-11%; bottom:-21%"
                 width="30px" height="30px" viewBox="0 0 24 24" fill="none"
                 xmlns="http://www.w3.org/2000/svg">
                <path d="M4 12.6111L8.92308 17.5L20 6.5"
                      stroke="#0A4906" stroke-width="4"
                      stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        <?php endif; ?>

        <?php if ($support_link != 1): ?>
            </a>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}






    public function add_footer_badge() {

        $footer_badge = get_option('imgseo_footer_badge', 0);



        if ($footer_badge == 1) {

            echo $this->render_badge_svg();

        }

    }



    public function register_shortcodes() {

        add_shortcode('imgseo_badge', [$this, 'render_badge_svg']);

    }





    /**

     * Rendering del campo dimensione batch

     */



    /**

     * Rendering del campo aggiorna altri campi

     */

    public function render_update_fields() {

        $update_title = get_option('imgseo_update_title', 0);

        $update_caption = get_option('imgseo_update_caption', 0);

        $update_description = get_option('imgseo_update_description', 0);

        ?>

        <fieldset>

            <p>

                <input type="checkbox" name="imgseo_update_title" id="imgseo_update_title" value="1" <?php checked($update_title, 1); ?> />

                <label for="imgseo_update_title"><?php _e('Update image title', IMGSEO_TEXT_DOMAIN); ?></label>

            </p>



            <p>

                <input type="checkbox" name="imgseo_update_caption" id="imgseo_update_caption" value="1" <?php checked($update_caption, 1); ?> />

                <label for="imgseo_update_caption"><?php _e('Update image caption', IMGSEO_TEXT_DOMAIN); ?></label>

            </p>



            <p>

                <input type="checkbox" name="imgseo_update_description" id="imgseo_update_description" value="1" <?php checked($update_description, 1); ?> />

                <label for="imgseo_update_description"><?php _e('Update image description', IMGSEO_TEXT_DOMAIN); ?></label>

            </p>



            <p class="description"><?php _e('Select which other image fields to update with the generated alt text. These options apply to both single and batch generation.', IMGSEO_TEXT_DOMAIN); ?></p>

        </fieldset>

        <?php

    }

    /**
     * Rendering del campo per abilitare i dati strutturati
     */

}
