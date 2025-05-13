<?php
/**
 * Class Renamer_Controller
 * Main controller for the Image Renamer functionality
 */
class Renamer_Controller {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Component instances
     */
    private $settings_manager;
    private $ui_manager;
    private $file_processor;
    private $logs_manager;
    private $pattern_manager;
    private $batch_processor;
    private $integration_manager;
    private $ai_generator;
    
    /**
     * Initialize the class and set its properties.
     */
    private function __construct() {
        // Carica solo le dipendenze essenziali all'inizio
        require_once plugin_dir_path(__FILE__) . 'class-renamer-settings-manager.php';
        
        // Inizializza solo il settings manager immediatamente
        $this->settings_manager = Renamer_Settings_Manager::get_instance();
        
        // Registra gli handler AJAX
        $this->register_ajax_handlers();
        
        // Utilizza hook init per caricare ulteriori componenti quando necessario
        add_action('init', array($this, 'late_load_components'), 999);
    }
    
    /**
     * Carica i componenti in modo ritardato per risparmiare memoria
     */
    public function late_load_components() {
        // Carica solo quando necessario
        if (!is_admin() || (defined('DOING_AJAX') && DOING_AJAX && !$this->is_renamer_ajax())) {
            return;
        }
        
        // Carica i componenti base
        $this->load_core_components();
    }
    
    /**
     * Verifica se la richiesta AJAX corrente è relativa al renamer
     */
    private function is_renamer_ajax() {
        if (!isset($_REQUEST['action'])) {
            return false;
        }
        
        $renamer_actions = array(
            'imgseo_rename_image',
            'imgseo_restore_image',
            'imgseo_get_rename_logs',
            'imgseo_delete_rename_logs',
            'imgseo_preview_batch_rename',
            'imgseo_batch_rename',
            'imgseo_generate_ai_filename'
        );
        
        return in_array($_REQUEST['action'], $renamer_actions);
    }
    
    /**
     * Carica i componenti core richiesti
     */
    private function load_core_components() {
        // Carica logs manager se non già caricato
        if (!$this->logs_manager) {
            require_once plugin_dir_path(__FILE__) . 'class-renamer-logs-manager.php';
            $this->logs_manager = Renamer_Logs_Manager::get_instance();
        }
        
        // Carica file processor se non già caricato
        if (!$this->file_processor) {
            require_once plugin_dir_path(__FILE__) . 'class-renamer-file-processor.php';
            $this->file_processor = Renamer_File_Processor::get_instance();
        }
        
        // Carica AI generator se non già caricato
        if (!$this->ai_generator) {
            require_once plugin_dir_path(__FILE__) . 'class-renamer-ai-generator.php';
            $this->ai_generator = Renamer_AI_Generator::get_instance();
        }
        
        // Carica UI manager solo nell'admin
        if (is_admin() && !defined('DOING_AJAX') && !$this->ui_manager) {
            require_once plugin_dir_path(__FILE__) . 'class-renamer-ui-manager.php';
            $this->ui_manager = Renamer_UI_Manager::get_instance();
        }
    }
    
    /**
     * Carica i componenti avanzati solo quando necessario
     */
    private function load_extended_components() {
        // Carica pattern manager se non già caricato
        if (!$this->pattern_manager) {
            require_once plugin_dir_path(__FILE__) . 'class-renamer-pattern-manager.php';
            $this->pattern_manager = Renamer_Pattern_Manager::get_instance();
        }
        
        // Carica batch processor se non già caricato
        if (!$this->batch_processor) {
            require_once plugin_dir_path(__FILE__) . 'class-renamer-batch-processor.php';
            $this->batch_processor = Renamer_Batch_Processor::get_instance();
        }
    }
    
    /**
     * Carica il gestore di integrazioni solo quando necessario
     */
    private function load_integration_manager() {
        if (!$this->integration_manager) {
            require_once plugin_dir_path(__FILE__) . 'class-renamer-integration-manager.php';
            $this->integration_manager = Renamer_Integration_Manager::get_instance();
        }
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
     * Register all AJAX handlers for the renamer
     */
    private function register_ajax_handlers() {
        // Basic renaming handlers
        add_action('wp_ajax_imgseo_rename_image', array($this, 'ajax_rename_image'));
        add_action('wp_ajax_imgseo_restore_image', array($this, 'ajax_restore_image'));
        
        // Log handlers
        add_action('wp_ajax_imgseo_get_rename_logs', array($this, 'ajax_get_rename_logs'));
        add_action('wp_ajax_imgseo_delete_rename_logs', array($this, 'ajax_delete_rename_logs'));
        
        // Batch renaming handlers
        add_action('wp_ajax_imgseo_preview_batch_rename', array($this, 'ajax_preview_batch_rename'));
        add_action('wp_ajax_imgseo_batch_rename', array($this, 'ajax_batch_rename'));
        
        // AI filename generator handler - registrato dalla classe stessa
    }
    
    /**
     * Blocca temporaneamente le richieste API esterne durante le operazioni di gestione
     * per evitare consumo accidentale di crediti
     *
     * @param mixed $preempt
     * @param array $args
     * @param string $url
     * @return mixed
     */
    public function block_external_api_requests($preempt, $args, $url) {
        // Blocca solo le richieste all'API di ImgSEO
        if (strpos($url, 'api.imgseo.net') !== false) {
            error_log('ImgSEO API: Blocked unnecessary API request during renamer operation: ' . $url);
            return new WP_Error('api_blocked', 'API request blocked during renamer operation');
        }
        
        return $preempt;
    }
    
    /**
     * AJAX handler for renaming an image
     */
    public function ajax_rename_image() {
        check_ajax_referer('imgseo_renamer_nonce', 'security');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You do not have permission to rename images.', IMGSEO_TEXT_DOMAIN)));
        }
        
        // Temporaneamente disabilita controlli API per evitare consumo crediti
        // Salva lo stato attuale per ripristinarlo dopo
        $did_http_api_filter = false;
        if (!has_filter('pre_http_request', array($this, 'block_external_api_requests'))) {
            add_filter('pre_http_request', array($this, 'block_external_api_requests'), 10, 3);
            $did_http_api_filter = true;
        }
        
        try {
            $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
            $new_filename = isset($_POST['new_filename']) ? sanitize_file_name($_POST['new_filename']) : '';
            
            if (!$attachment_id || empty($new_filename)) {
                wp_send_json_error(array('message' => __('Invalid attachment ID or filename.', IMGSEO_TEXT_DOMAIN)));
            }
            
            // Verifica se esiste un blocco globale di processo
            if (class_exists('ImgSEO_Process_Lock') && ImgSEO_Process_Lock::is_globally_locked()) {
                wp_send_json_error(array('message' => __('Le operazioni ImgSEO sono temporaneamente bloccate. Riprova tra qualche istante.', IMGSEO_TEXT_DOMAIN)));
            }
            
            // Carica i componenti necessari
            $this->load_core_components();
            
            // Get additional options if provided
            $options = array();
            
            // Apply settings from the settings manager
            $options['remove_accents'] = $this->settings_manager->is_enabled('remove_accents', true);
            $options['lowercase'] = $this->settings_manager->is_enabled('lowercase', true);
            $options['handle_duplicates'] = $this->settings_manager->get_setting('handle_duplicates', 'increment');
            
            // Enable sanitization
            $options['sanitize'] = true;
            
            // Ottimizzazione per velocizzare la generazione AI - verifica se la richiesta proviene dal generatore AI
            $options['update_references'] = !(isset($_POST['source']) && $_POST['source'] === 'ai_generator');
            
            // Process the rename operation
            $result = $this->file_processor->rename_image($attachment_id, $new_filename, $options);
            
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }
            
            wp_send_json_success(array(
                'old_filename' => $result['old_filename'],
                'new_filename' => $result['new_filename'],
                'new_url' => $result['new_url']
            ));
        } finally {
            // Ripristina il comportamento normale delle API
            if ($did_http_api_filter) {
                remove_filter('pre_http_request', array($this, 'block_external_api_requests'), 10);
            }
        }
    }
    
    /**
     * AJAX handler for getting rename logs
     */
    public function ajax_get_rename_logs() {
        // Carica i componenti necessari
        $this->load_core_components();
        
        // Delegate to the logs manager
        $this->logs_manager->ajax_get_logs();
    }
    
    /**
     * AJAX handler for deleting rename logs
     */
    public function ajax_delete_rename_logs() {
        // Carica i componenti necessari
        $this->load_core_components();
        
        // Delegate to the logs manager
        $this->logs_manager->ajax_delete_logs();
    }
    
    /**
     * AJAX handler for restoring a renamed image to its original filename
     */
    public function ajax_restore_image() {
        check_ajax_referer('imgseo_renamer_nonce', 'security');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You do not have permission to restore images.', IMGSEO_TEXT_DOMAIN)));
        }
        
        // Temporaneamente disabilita controlli API per evitare consumo crediti
        $did_http_api_filter = false;
        if (!has_filter('pre_http_request', array($this, 'block_external_api_requests'))) {
            add_filter('pre_http_request', array($this, 'block_external_api_requests'), 10, 3);
            $did_http_api_filter = true;
        }
        
        try {
            $attachment_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
            $original_filename = isset($_POST['original_filename']) ? sanitize_text_field($_POST['original_filename']) : '';
            $current_filename = isset($_POST['current_filename']) ? sanitize_text_field($_POST['current_filename']) : '';
            
            if (!$attachment_id || empty($original_filename) || empty($current_filename)) {
                wp_send_json_error(array('message' => __('Invalid attachment ID or filenames.', IMGSEO_TEXT_DOMAIN)));
            }
            
            // Verifica se esiste un blocco globale di processo
            if (class_exists('ImgSEO_Process_Lock') && ImgSEO_Process_Lock::is_globally_locked()) {
                wp_send_json_error(array('message' => __('Le operazioni ImgSEO sono temporaneamente bloccate. Riprova tra qualche istante.', IMGSEO_TEXT_DOMAIN)));
            }
            
            // Carica i componenti necessari
            $this->load_core_components();
            
            // Process the restore operation
            $result = $this->file_processor->restore_image($attachment_id, $original_filename, $current_filename);
            
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }
            
            wp_send_json_success(array(
                'old_filename' => $result['old_filename'],
                'new_filename' => $result['new_filename'],
                'new_url' => $result['new_url'],
                'message' => __('Image successfully restored to its original filename.', IMGSEO_TEXT_DOMAIN)
            ));
        } finally {
            // Ripristina il comportamento normale delle API
            if ($did_http_api_filter) {
                remove_filter('pre_http_request', array($this, 'block_external_api_requests'), 10);
            }
        }
    }
    
    /**
     * AJAX handler for previewing batch rename operations
     */
    public function ajax_preview_batch_rename() {
        check_ajax_referer('imgseo_renamer_nonce', 'security');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You do not have permission to rename images.', IMGSEO_TEXT_DOMAIN)));
        }
        
        $attachment_ids = isset($_POST['attachment_ids']) ? array_map('intval', $_POST['attachment_ids']) : array();
        $options = isset($_POST['options']) ? $_POST['options'] : array();
        
        if (empty($attachment_ids)) {
            wp_send_json_error(array('message' => __('No images selected.', IMGSEO_TEXT_DOMAIN)));
        }
        
        // Carica i componenti necessari
        $this->load_core_components();
        $this->load_extended_components();
        
        // Make sure the pattern manager is ready
        if (!$this->pattern_manager) {
            wp_send_json_error(array('message' => __('Pattern manager not available.', IMGSEO_TEXT_DOMAIN)));
        }
        
        // Ensure options have default values if not set
        $options = wp_parse_args($options, array(
            'pattern' => $this->settings_manager->get_setting('pattern_template', '{post_title}-{numero}'),
            'lowercase' => $this->settings_manager->is_enabled('lowercase', true),
            'remove_accents' => $this->settings_manager->is_enabled('remove_accents', true),
            'handle_duplicates' => $this->settings_manager->get_setting('handle_duplicates', 'increment'),
            'sanitize' => true,
            'use_patterns' => true
        ));
        
        // Processa in batch di dimensioni limitate per ridurre l'utilizzo di memoria
        $batch_size = 20; // Processa un numero limitato di immagini alla volta
        $total_batches = ceil(count($attachment_ids) / $batch_size);
        $previews = array();
        
        for ($batch = 0; $batch < $total_batches; $batch++) {
            // Ottieni gli ID per questo batch
            $batch_ids = array_slice($attachment_ids, $batch * $batch_size, $batch_size);
            
            // Reset the sequence counter for consistent previews
            $this->pattern_manager->reset_sequence();
            
            // Processa questo batch
            foreach ($batch_ids as $attachment_id) {
                // Get attachment details
                $attachment = get_post($attachment_id);
                if (!$attachment || $attachment->post_type !== 'attachment' || !wp_attachment_is_image($attachment_id)) {
                    continue;
                }
                
                $file = get_attached_file($attachment_id);
                if (!$file || !file_exists($file)) {
                    continue;
                }
                
                // Get file info
                $path_parts = pathinfo($file);
                $original_filename = $path_parts['filename'];
                $extension = isset($path_parts['extension']) ? $path_parts['extension'] : '';
                
                // Create context for pattern replacement
                $context = array(
                    'original_filename' => $original_filename,
                    'attachment_id' => $attachment_id
                );
                
                // Generate new filename using pattern
                $new_filename = $this->pattern_manager->apply_patterns($options['pattern'], $attachment_id, $context);
                
                // Add to previews array
                $previews[] = array(
                    'id' => $attachment_id,
                    'current_filename' => $original_filename,
                    'new_filename' => $new_filename,
                    'extension' => $extension,
                    'thumbnail_url' => wp_get_attachment_image_src($attachment_id, 'thumbnail')[0],
                    'status' => ($original_filename === $new_filename) ? 'unchanged' : 'preview'
                );
                
                // Libera memoria
                $context = null;
                $path_parts = null;
            }
            
            // Libera memoria non necessaria dopo ogni batch
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
        
        wp_send_json_success(array(
            'previews' => $previews,
            'count' => count($previews)
        ));
    }
    
    /**
     * AJAX handler for batch rename operations
     */
    public function ajax_batch_rename() {
        check_ajax_referer('imgseo_renamer_nonce', 'security');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You do not have permission to rename images.', IMGSEO_TEXT_DOMAIN)));
        }
        
        $attachment_ids = isset($_POST['attachment_ids']) ? array_map('intval', $_POST['attachment_ids']) : array();
        $options = isset($_POST['options']) ? $_POST['options'] : array();
        
        if (empty($attachment_ids)) {
            wp_send_json_error(array('message' => __('No images selected.', IMGSEO_TEXT_DOMAIN)));
        }
        
        // Carica i componenti necessari
        $this->load_core_components();
        $this->load_extended_components();
        
        // Make sure the batch processor is ready
        if (!$this->batch_processor) {
            wp_send_json_error(array('message' => __('Batch processor not available.', IMGSEO_TEXT_DOMAIN)));
        }
        
        // Ensure options have default values if not set
        $options = wp_parse_args($options, array(
            'pattern' => $this->settings_manager->get_setting('pattern_template', '{post_title}-{numero}'),
            'lowercase' => $this->settings_manager->is_enabled('lowercase', true),
            'remove_accents' => $this->settings_manager->is_enabled('remove_accents', true),
            'handle_duplicates' => $this->settings_manager->get_setting('handle_duplicates', 'increment'),
            'sanitize' => true,
            'use_patterns' => true
        ));
        
        // Processa in batch di dimensioni limitate per ridurre l'utilizzo di memoria
        $batch_size = 10; // Numero ridotto per le operazioni di rinomina effettive
        $total_batches = ceil(count($attachment_ids) / $batch_size);
        $all_results = array(
            'success' => array(),
            'errors' => array(),
        );
        
        for ($batch = 0; $batch < $total_batches; $batch++) {
            // Ottieni gli ID per questo batch
            $batch_ids = array_slice($attachment_ids, $batch * $batch_size, $batch_size);
            
            // Prepara i dati per questo batch
            $batch_data = $this->batch_processor->prepare_batch_rename($batch_ids, $options);
            
            // Esegui la rinomina per questo batch
            $results = $this->batch_processor->execute_batch_rename($batch_data['items'], $options);
            
            // Aggiungi i risultati ai risultati totali
            $all_results['success'] += $results['success'];
            $all_results['errors'] += $results['errors'];
            
            // Libera memoria non necessaria dopo ogni batch
            $batch_data = null;
            $results = null;
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
        
        wp_send_json_success(array(
            'results' => $all_results,
            'message' => sprintf(
                __('Processati %d immagini: %d rinominate con successo, %d errori.', IMGSEO_TEXT_DOMAIN),
                count($attachment_ids),
                count($all_results['success']),
                count($all_results['errors'])
            )
        ));
    }
}
