<?php
/**
 * Classe principale per il generatore
 * 
 * @package ImgSEO
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe ImgSEO_Generator_Main
 * Classe principale che inizializza e coordina tutte le funzionalità di generazione
 */
class ImgSEO_Generator_Main {
    
    /**
     * Istanza del generatore di testo alternativo
     *
     * @var ImgSEO_Alt_Text_Generator
     */
    private $alt_text_generator;
    
    /**
     * Istanza del processore batch
     *
     * @var ImgSEO_Batch_Processor
     */
    private $batch_processor;
    
    /**
     * Istanza del gestore della libreria media
     *
     * @var ImgSEO_Media_Library_Manager
     */
    private $media_library_manager;
    
    /**
     * Singleton instance
     * 
     * @var ImgSEO_Generator_Main
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     * 
     * @return ImgSEO_Generator_Main
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Definizione delle costanti PLUGIN_DIR se non già definita
        if (!defined('IMGSEO_PLUGIN_DIR')) {
            define('IMGSEO_PLUGIN_DIR', IMGSEO_DIRECTORY_PATH);
        }
        
        // Caricamento delle classi necessarie
        $this->load_dependencies();
        
        // Inizializzazione delle classi
        $this->alt_text_generator = new ImgSEO_Alt_Text_Generator();
        $this->batch_processor = new ImgSEO_Batch_Processor();
        $this->media_library_manager = new ImgSEO_Media_Library_Manager();
        
        // Registrazione degli hook AJAX
        $this->register_ajax_hooks();
        
        // Non registriamo più qui il hook per la generazione automatica
        // è gestito centralmente dal metodo ImgSEO_Alt_Text_Generator::initialize_hooks()
        
        // Registrazione del hook per l'elaborazione batch tramite cron
        add_action(IMGSEO_CRON_HOOK, array($this->batch_processor, 'process_cron_batch'));
    }
    
    /**
     * Carica le dipendenze necessarie
     */
    private function load_dependencies() {
        // Base class
        require_once IMGSEO_PLUGIN_DIR . 'includes/generator/class-generator-base.php';
        
        // Specialized classes
        require_once IMGSEO_PLUGIN_DIR . 'includes/generator/class-alt-text-generator.php';
        require_once IMGSEO_PLUGIN_DIR . 'includes/generator/class-batch-processor.php';
        require_once IMGSEO_PLUGIN_DIR . 'includes/generator/class-media-library-manager.php';
    }
    
    /**
     * Registra gli hook AJAX
     */
    private function register_ajax_hooks() {
        // Alt text generation
        add_action('wp_ajax_imgseo_generate_alt_text', array($this->alt_text_generator, 'handle_generate_alt_text'));
        add_action('wp_ajax_generate_alt_text', array($this->alt_text_generator, 'handle_generate_alt_text'));
        
        // Batch processing
        add_action('wp_ajax_imgseo_start_bulk', array($this->batch_processor, 'handle_start_bulk'));
        add_action('wp_ajax_imgseo_check_job_status', array($this->batch_processor, 'handle_check_job_status'));
        add_action('wp_ajax_imgseo_stop_job', array($this->batch_processor, 'handle_stop_job'));
        add_action('wp_ajax_imgseo_delete_job', array($this->batch_processor, 'handle_delete_job'));
        add_action('wp_ajax_imgseo_delete_all_jobs', array($this->batch_processor, 'handle_delete_all_jobs'));
        add_action('wp_ajax_imgseo_force_cron', array($this->batch_processor, 'force_cron_execution'));
    }
    
    /**
     * Proxy method per auto_generate_alt_text
     * 
     * @param int $attachment_id ID dell'allegato
     */
    public function auto_generate_alt_text($attachment_id) {
        $this->alt_text_generator->auto_generate_alt_text($attachment_id);
    }
    
    /**
     * Proxy method per process_single_generate
     * 
     * @param int $attachment_id ID dell'allegato
     * @param int $attempt_number Numero del tentativo
     */
    public function process_single_generate($attachment_id, $attempt_number = 1) {
        $this->alt_text_generator->process_single_generate($attachment_id, $attempt_number);
    }
    
    /**
     * Proxy method per handle_generate_alt_text
     */
    public function handle_generate_alt_text() {
        $this->alt_text_generator->handle_generate_alt_text();
    }
    
    /**
     * Proxy method per handle_start_bulk
     */
    public function handle_start_bulk() {
        $this->batch_processor->handle_start_bulk();
    }
    
    /**
     * Proxy method per handle_check_job_status
     */
    public function handle_check_job_status() {
        $this->batch_processor->handle_check_job_status();
    }
    
    /**
     * Proxy method per handle_stop_job
     */
    public function handle_stop_job() {
        $this->batch_processor->handle_stop_job();
    }
    
    /**
     * Proxy method per handle_delete_job
     */
    public function handle_delete_job() {
        $this->batch_processor->handle_delete_job();
    }
    
    /**
     * Proxy method per handle_delete_all_jobs
     */
    public function handle_delete_all_jobs() {
        $this->batch_processor->handle_delete_all_jobs();
    }
    
    /**
     * Proxy method per process_cron_batch
     */
    public function process_cron_batch() {
        $this->batch_processor->process_cron_batch();
    }
    
    /**
     * Proxy method per force_cron_execution
     */
    public function force_cron_execution() {
        $this->batch_processor->force_cron_execution();
    }
    
    /**
     * Ottiene l'istanza del generatore di testo alternativo
     * 
     * @return ImgSEO_Alt_Text_Generator
     */
    public function get_alt_text_generator() {
        imgseo_debug_log('get_alt_text_generator chiamato, restituendo istanza: ' . (is_object($this->alt_text_generator) ? get_class($this->alt_text_generator) : 'non è un oggetto'));
        return $this->alt_text_generator;
    }
}
