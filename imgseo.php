<?php

/*

* Plugin Name: ImgSEO - AI Image Alt Text & SEO File Renamer

* Description: Boost your website's SEO and accessibility with our advanced AI alt text generator and Renamer. Perfect for WordPress users and web professionals alike.

* Author: pianoweb, jonathanwambua

* Author Email: info@pianoweb.eu

* Version: 1.1.8

*

* Author URI: https://pianoweb.eu

* Plugin URI: https://imgseo.net

License: GPLv2 or later

License URI: https://www.gnu.org/licenses/gpl-2.0.html

*/



defined('ABSPATH') or die('Access Denied!');



// Constants definitions

define('IMGSEO_FILE', __FILE__);

define('IMGSEO_DIRECTORY', dirname(__FILE__));

define('IMGSEO_TEXT_DOMAIN', 'imgseo');

define('IMGSEO_ACCESS_DENIED', 'Access Denied!');

define('IMGSEO_DIRECTORY_PATH', plugin_dir_path(IMGSEO_FILE));

define('IMGSEO_PLUGIN_URL', plugin_dir_url(IMGSEO_FILE));

define('IMGSEO_PLUGIN_NAME', 'imgseo');

define('IMGSEO_PLUGIN_VERSION', '1.0.5');

define('IMGSEO_CRON_HOOK', 'imgseo_cron_process');

define('IMGSEO_STATUS_CREATED', 201);

define('IMGSEO_STATUS_OK', 200);



// Main class for plugin initialization

class IMGSEO_Init {

    // Singleton instance

    protected static $instance = null;



    // Plugin components

    public $api;

    public $settings;

    public $generator;

    public $media_button;

    public $renamer;



    /**

     * Initializes the plugin and returns the singleton instance

     *

     * @return IMGSEO_Init

     */

    public static function init() {

        if (self::$instance === null) {

            self::$instance = new self();

        }

        return self::$instance;

    }



    /**

     * Constructor: loads files, initializes components, and registers hooks

     */

    private function __construct() {

        $this->load_files();

        $this->initialize_components();

        $this->register_hooks();

    }



    /**

     * Loads necessary class files

     */

    private function load_files() {

        // API File

        require_once IMGSEO_DIRECTORY_PATH . 'includes/class-imgseo-api.php';



        // Core files

        require_once IMGSEO_DIRECTORY_PATH . 'includes/class-imgseo-menu-manager.php';

        require_once IMGSEO_DIRECTORY_PATH . 'includes/class-imgseo-settings.php';

        require_once IMGSEO_DIRECTORY_PATH . 'includes/generator/class-generator-main.php';

        require_once IMGSEO_DIRECTORY_PATH . 'includes/class-media-modal-button.php';

        require_once IMGSEO_DIRECTORY_PATH . 'includes/class-image-renamer.php';



        // Load processing speed classes

        require_once IMGSEO_DIRECTORY_PATH . 'includes/class-imgseo-process-speed.php';

        require_once IMGSEO_DIRECTORY_PATH . 'includes/class-imgseo-speed-integrator.php';

    }



    /**

     * Initializes plugin components

     */

    private function initialize_components() {

        // Initialize main components

        $this->api = ImgSEO_API::get_instance();



        // Inizializzazione del gestore menu centralizzato (prima di altri componenti che potrebbero utilizzare menu)

        ImgSEO_Menu_Manager::get_instance();



        // Inizializza gli altri componenti utilizzando il pattern singleton dove disponibile

        $this->settings = ImgSEO_Settings::get_instance();

        $this->generator = ImgSEO_Generator_Main::get_instance();

        $this->media_button = Media_Modal_Button::instance();

        $this->renamer = Image_Renamer::get_instance();

    }



    /**

     * Registers main and custom hooks

     */

    private function register_hooks() {

        // Load translation files

        add_action('init', array($this, 'load_textdomain'));



        // Debug log per registrazione hook

        error_log('ImgSEO DEBUG: Registrazione degli hook in corso - ' . current_time('mysql'));



        // Inizializza centralmente gli hook di generazione alt text

        // con un log più dettagliato per il debug

        error_log('ImgSEO DEBUG: Inizializzazione centralizzata hooks per generazione alt text');

        ImgSEO_Alt_Text_Generator::initialize_hooks();

        error_log('ImgSEO DEBUG: Hook centralizzato inizializzato con successo');



        // Hook for single image generation

        add_action('imgseo_single_generate', array($this->generator, 'process_single_generate'));



        // Hook for single generation via AJAX

        add_action('wp_ajax_generate_alt_text', array($this->generator, 'handle_generate_alt_text'));



        // Hooks for bulk processing

        add_action('wp_ajax_imgseo_start_bulk', array($this->generator, 'handle_start_bulk'));

        add_action('wp_ajax_imgseo_check_job_status', array($this->generator, 'handle_check_job_status'));

        add_action('wp_ajax_imgseo_stop_job', array($this->generator, 'handle_stop_job'));

        add_action('wp_ajax_imgseo_delete_job', array($this->generator, 'handle_delete_job'));

        add_action('wp_ajax_imgseo_delete_all_jobs', array($this->generator, 'handle_delete_all_jobs'));



        // Hook for cron processing

        add_action(IMGSEO_CRON_HOOK, array($this->generator, 'process_cron_batch'));



        // Hook to force cron execution

        add_action('wp_ajax_imgseo_force_cron', array($this->generator, 'force_cron_execution'));



        // Hooks for settings

        add_action('wp_ajax_imgseo_verify_api_key', array($this->api, 'ajax_verify_api_key'));

        add_action('wp_ajax_imgseo_refresh_credits', array($this->api, 'ajax_refresh_credits'));

        add_action('wp_ajax_imgseo_disconnect_api', array($this->api, 'ajax_disconnect_api'));

        add_action('wp_ajax_imgseo_update_settings', array($this->settings, 'ajax_update_settings'));



        // Renamer AJAX hooks

        add_action('wp_ajax_imgseo_rename_image', array($this->renamer, 'ajax_rename_image'));

        add_action('wp_ajax_imgseo_get_rename_logs', array($this->renamer, 'ajax_get_rename_logs'));

        add_action('wp_ajax_imgseo_delete_rename_logs', array($this->renamer, 'ajax_delete_rename_logs'));

        add_action('wp_ajax_imgseo_restore_image', array($this->renamer, 'ajax_restore_image'));

    }



    /**

     * Plugin activation function

     */

    public static function on_activation() {

        // Create database tables

        require_once IMGSEO_DIRECTORY_PATH . 'includes/class-imgseo-activator.php';

        ImgSEO_Activator::activate();

    }



    /**

     * Plugin deactivation function

     */

    public static function on_deactivation() {

        // Remove cron jobs

        wp_clear_scheduled_hook(IMGSEO_CRON_HOOK);

        wp_clear_scheduled_hook('imgseo_check_stuck_jobs');

        wp_clear_scheduled_hook('imgseo_cleanup_rename_logs');

    }



    /**

     * Loads the text domain for translations

     */

    public function load_textdomain() {

        load_plugin_textdomain(

            IMGSEO_TEXT_DOMAIN,

            false,

            dirname(plugin_basename(IMGSEO_FILE)) . '/languages/'

        );

    }



    /**

     * Registers new uploads to ensure automatic generation works

     * Improved and more reliable method

     *

     * @param array $metadata Attachment metadata

     * @param int $attachment_id Attachment ID

     * @return array Unmodified metadata

     */

    public function log_new_attachment($metadata, $attachment_id) {

        error_log('ImgSEO DEBUG: log_new_attachment chiamato per ID: ' . $attachment_id . ' al ' . current_time('mysql'));



        // Debug della struttura dei metadati

        error_log('ImgSEO DEBUG: Metadati ricevuti: ' . (is_array($metadata) ? json_encode($metadata) : 'non è un array'));



        // Check if the attachment is an image

        $is_image = wp_attachment_is_image($attachment_id);

        $mime_type = get_post_mime_type($attachment_id);

        $is_image_mime = strpos($mime_type, 'image/') === 0;



        error_log('ImgSEO DEBUG: Tipo MIME: ' . $mime_type . ', wp_attachment_is_image(): ' . ($is_image ? 'true' : 'false'));



        if (!$is_image && !$is_image_mime) {

            error_log('ImgSEO DEBUG: ID ' . $attachment_id . ' non è un\'immagine, uscita da log_new_attachment');

            return $metadata;

        }



        error_log('ImgSEO DEBUG: Nuova immagine caricata, ID: ' . $attachment_id);



        // Check if automatic generation is enabled

        $auto_generate = get_option('imgseo_auto_generate', 0);

        error_log('ImgSEO DEBUG: Opzione auto_generate: ' . $auto_generate);



        if ($auto_generate) {

            // Check if the image already has alt text

            $current_alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);

            error_log('ImgSEO DEBUG: Testo alternativo attuale: ' . ($current_alt_text ? '"'.$current_alt_text.'"' : 'NESSUNO'));



            // Check the overwrite option

            $overwrite = get_option('imgseo_overwrite', 0);

            error_log('ImgSEO DEBUG: Opzione overwrite: ' . $overwrite);



            if (empty($current_alt_text) || $overwrite) {

                // Execute generation immediately

                error_log('ImgSEO DEBUG: Avvio immediato della generazione del testo alternativo per ID: ' . $attachment_id);

                update_post_meta($attachment_id, '_imgseo_pending_generation', time());



                // Verifica dei crediti

                $credits_exhausted = get_transient('imgseo_insufficient_credits');

                $credits = get_option('imgseo_credits', 0);

                error_log('ImgSEO DEBUG: Crediti disponibili: ' . $credits . ', esauriti: ' . ($credits_exhausted ? 'SI' : 'NO'));



                // Solo se ci sono crediti, procedi

                if (!$credits_exhausted && $credits > 0) {

                    error_log('ImgSEO DEBUG: Chiamata a process_single_generate per ID: ' . $attachment_id);

                    $this->generator->process_single_generate($attachment_id, 1);



                    // Schedule a fallback in case of issues

                    error_log('ImgSEO DEBUG: Pianificazione fallback tra 30 secondi');

                    wp_schedule_single_event(time() + 30, 'imgseo_single_generate', array($attachment_id, 2));

                } else {

                    error_log('ImgSEO DEBUG: Generazione automatica bloccata - crediti insufficienti');

                }

            } else {

                error_log('ImgSEO DEBUG: L\'immagine ha già un testo alternativo e overwrite è disattivato, salto generazione');

            }

        } else {

            error_log('ImgSEO DEBUG: Generazione automatica disattivata nelle impostazioni');

        }



        return $metadata;

    }



    /**

     * Safe handler for attachment updates to avoid recursion

     *

     * @param int $attachment_id Attachment ID

     */

    public function handle_attachment_update($attachment_id) {

        error_log('ImgSEO DEBUG: handle_attachment_update chiamato per ID: ' . $attachment_id . ' al ' . current_time('mysql'));



        static $is_processing = false;



        // Protection against recursion

        if ($is_processing) {

            error_log('ImgSEO DEBUG: Ricorsione rilevata in handle_attachment_update, ignorata');

            return;

        }



        $is_processing = true;



        // Verify it's an image

        $is_image = wp_attachment_is_image($attachment_id);

        $mime_type = get_post_mime_type($attachment_id);

        $is_image_mime = strpos($mime_type, 'image/') === 0;



        error_log('ImgSEO DEBUG: Tipo MIME: ' . $mime_type . ', wp_attachment_is_image(): ' . ($is_image ? 'true' : 'false'));



        if (!$is_image && !$is_image_mime) {

            error_log('ImgSEO DEBUG: ID ' . $attachment_id . ' non è un\'immagine, uscita da handle_attachment_update');

            $is_processing = false;

            return;

        }



        // Check if automatic generation is enabled

        $auto_generate = get_option('imgseo_auto_generate', 0);

        error_log('ImgSEO DEBUG: Opzione auto_generate: ' . $auto_generate);



        if (!$auto_generate) {

            error_log('ImgSEO DEBUG: Generazione automatica disattivata nelle impostazioni');

            $is_processing = false;

            return;

        }



        // Check if it's already being processed

        $processing_lock = get_transient('imgseo_processing_' . $attachment_id);

        if ($processing_lock) {

            error_log('ImgSEO DEBUG: Elaborazione già in corso per ID: ' . $attachment_id);

            $is_processing = false;

            return;

        }



        // Set a temporary lock (10 seconds)

        set_transient('imgseo_processing_' . $attachment_id, true, 10);

        error_log('ImgSEO DEBUG: Lock temporaneo impostato per ID: ' . $attachment_id);



        // Check if the image already has alt text

        $current_alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);

        $overwrite = get_option('imgseo_overwrite', 0);

        error_log('ImgSEO DEBUG: Testo alternativo attuale: ' . ($current_alt_text ? '"'.$current_alt_text.'"' : 'NESSUNO') . ', overwrite: ' . $overwrite);



        if (!$overwrite && !empty($current_alt_text)) {

            error_log('ImgSEO DEBUG: L\'immagine ha già un testo alternativo e overwrite è disattivato, salto generazione');

            $is_processing = false;

            delete_transient('imgseo_processing_' . $attachment_id);

            return;

        }



        error_log('ImgSEO DEBUG: Verifica crediti per ID: ' . $attachment_id);



        // Verifica dei crediti

        $credits_exhausted = get_transient('imgseo_insufficient_credits');

        $credits = get_option('imgseo_credits', 0);

        error_log('ImgSEO DEBUG: Crediti disponibili: ' . $credits . ', esauriti: ' . ($credits_exhausted ? 'SI' : 'NO'));



        // Solo se ci sono crediti, procedi

        if (!$credits_exhausted && $credits > 0) {

            error_log('ImgSEO DEBUG: Avvio generazione per ID: ' . $attachment_id);

            // Execute generation immediately (first attempt)

            update_post_meta($attachment_id, '_imgseo_pending_generation', time());



            error_log('ImgSEO DEBUG: Chiamata a process_single_generate per ID: ' . $attachment_id);

            $this->generator->process_single_generate($attachment_id, 1);



            // Schedule a fallback attempt as backup

            error_log('ImgSEO DEBUG: Pianificazione fallback tra 30 secondi');

            if (!wp_next_scheduled('imgseo_single_generate', array($attachment_id, 2))) {

                wp_schedule_single_event(time() + 30, 'imgseo_single_generate', array($attachment_id, 2));

            }

        } else {

            error_log('ImgSEO DEBUG: Generazione automatica bloccata - crediti insufficienti');

        }



        // Reset state

        $is_processing = false;

        error_log('ImgSEO DEBUG: Reset stato processing per ID: ' . $attachment_id);



        // Remove the lock after processing

        delete_transient('imgseo_processing_' . $attachment_id);

        error_log('ImgSEO DEBUG: Lock temporaneo rimosso per ID: ' . $attachment_id);

    }



    /**

     * Handler for attachments uploaded via REST API

     *

     * @param WP_Post $attachment Attachment object

     */

    public function handle_rest_attachment($attachment) {

        if (!is_object($attachment) || !isset($attachment->ID)) {

            return;

        }



        $attachment_id = $attachment->ID;



        // Use the safe wrapper to avoid recursion

        $this->handle_attachment_update($attachment_id);

    }



    /**

     * Checks meta updates to trigger alt text generation

     * when needed.

     *

     * @param int $meta_id Metadata ID

     * @param int $post_id Post ID

     * @param string $meta_key Meta key

     * @param mixed $meta_value Meta value

     */

    public function check_image_alt_on_meta_update($meta_id, $post_id, $meta_key, $meta_value) {

        error_log('ImgSEO DEBUG: check_image_alt_on_meta_update chiamato per ID: ' . $post_id . ', meta_key: ' . $meta_key . ' al ' . current_time('mysql'));



        // Ignore _wp_attachment_image_alt meta updates to avoid recursion

        if ($meta_key === '_wp_attachment_image_alt' || $meta_key === '_imgseo_pending_generation') {

            error_log('ImgSEO DEBUG: meta_key ' . $meta_key . ' ignorata per evitare ricorsione');

            return;

        }



        // Check if it's an image and automatic generation is enabled

        $is_image = wp_attachment_is_image($post_id);

        $auto_generate = get_option('imgseo_auto_generate', 0);

        error_log('ImgSEO DEBUG: Verifica tipo: è immagine? ' . ($is_image ? 'SI' : 'NO') . ', auto_generate: ' . $auto_generate);



        if (!$is_image || !$auto_generate) {

            error_log('ImgSEO DEBUG: Uscita - non è immagine o generazione automatica disattivata');

            return;

        }



        // Check if the updated metadata is relevant for alt text

        $relevant_meta_keys = array('_wp_attachment_metadata', '_wp_attached_file');

        $is_relevant = in_array($meta_key, $relevant_meta_keys);

        error_log('ImgSEO DEBUG: meta_key ' . $meta_key . ' rilevante? ' . ($is_relevant ? 'SI' : 'NO'));



        if (!$is_relevant) {

            error_log('ImgSEO DEBUG: Uscita - meta_key non rilevante');

            return;

        }



        // Check if it's already being processed

        $processing_lock = get_transient('imgseo_processing_' . $post_id);

        if ($processing_lock) {

            error_log('ImgSEO DEBUG: Meta update - elaborazione già in corso per ID: ' . $post_id);

            return;

        }



        // Set a temporary lock (10 seconds)

        set_transient('imgseo_processing_' . $post_id, true, 10);

        error_log('ImgSEO DEBUG: Lock temporaneo impostato per ID: ' . $post_id);



        // Check if the image already has alt text

        $current_alt_text = get_post_meta($post_id, '_wp_attachment_image_alt', true);

        $overwrite = get_option('imgseo_overwrite', 0);

        error_log('ImgSEO DEBUG: Testo alternativo attuale: ' . ($current_alt_text ? '"'.$current_alt_text.'"' : 'NESSUNO') . ', overwrite: ' . $overwrite);



        // If it shouldn't overwrite and already has alt text, skip

        if (!$overwrite && !empty($current_alt_text)) {

            error_log('ImgSEO DEBUG: Uscita - testo alternativo già presente e overwrite disattivato');

            delete_transient('imgseo_processing_' . $post_id);

            return;

        }



        // Avoid double updates

        $pending_generation = get_post_meta($post_id, '_imgseo_pending_generation', true);

        if ($pending_generation) {

            $pending_time = intval($pending_generation);

            $current_time = time();

            $time_diff = $current_time - $pending_time;

            error_log('ImgSEO DEBUG: Generazione già in attesa da ' . $time_diff . ' secondi');



            if ($time_diff < 180) { // 3 minutes

                error_log('ImgSEO DEBUG: Generazione già pianificata, uscita');

                delete_transient('imgseo_processing_' . $post_id);

                return;

            }

        }



        error_log('ImgSEO DEBUG: Metadati immagine aggiornati, avvio generazione testo alt per ID: ' . $post_id);



        // Verifica dei crediti

        $credits_exhausted = get_transient('imgseo_insufficient_credits');

        $credits = get_option('imgseo_credits', 0);

        error_log('ImgSEO DEBUG: Crediti disponibili: ' . $credits . ', esauriti: ' . ($credits_exhausted ? 'SI' : 'NO'));



        // Solo se ci sono crediti, procedi

        if (!$credits_exhausted && $credits > 0) {

            // Execute generation immediately

            update_post_meta($post_id, '_imgseo_pending_generation', time());

            error_log('ImgSEO DEBUG: Chiamata a process_single_generate per ID: ' . $post_id);

            $this->generator->process_single_generate($post_id, 1);



            // Schedule a fallback attempt as backup

            error_log('ImgSEO DEBUG: Pianificazione fallback tra 30 secondi');

            if (!wp_next_scheduled('imgseo_single_generate', array($post_id, 2))) {

                wp_schedule_single_event(time() + 30, 'imgseo_single_generate', array($post_id, 2));

            }

        } else {

            error_log('ImgSEO DEBUG: Generazione automatica bloccata - crediti insufficienti');

        }



        // Remove the lock after processing

        delete_transient('imgseo_processing_' . $post_id);

        error_log('ImgSEO DEBUG: Lock temporaneo rimosso per ID: ' . $post_id);

    }

}



// Register activation and deactivation hooks

register_activation_hook(IMGSEO_FILE, array('IMGSEO_Init', 'on_activation'));

register_deactivation_hook(IMGSEO_FILE, array('IMGSEO_Init', 'on_deactivation'));



// Initialize the plugin when WordPress is ready

add_action('plugins_loaded', array('IMGSEO_Init', 'init'));
