<?php
/**
 * Classe per l'inizializzazione del sistema ImgSEO 2.0
 * Gestisce il caricamento e l'inizializzazione di tutte le componenti del nuovo sistema
 *
 * @package ImgSEO
 * @since 2.0.0
 */

// Impedisce l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe ImgSEO_System_Initializer
 * 
 * Coordina l'inizializzazione del sistema di scansione universale
 */
class ImgSEO_System_Initializer {
    
    /**
     * Istanza singleton della classe
     *
     * @var ImgSEO_System_Initializer|null
     */
    private static $instance = null;
    
    /**
     * Flag per verificare se il sistema è stato inizializzato
     *
     * @var bool
     */
    private $initialized = false;
    
    /**
     * Versione del sistema
     *
     * @var string
     */
    private $system_version = '2.0.0';
    
    /**
     * Ottiene l'istanza singleton della classe
     *
     * @return ImgSEO_System_Initializer
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Costruttore privato per implementare il pattern singleton
     */
    private function __construct() {
        // Inizializza il sistema immediatamente se non è già inizializzato
        if (!$this->is_initialized()) {
            add_action('plugins_loaded', array($this, 'init_system'), 10);
        }
        
        add_action('init', array($this, 'schedule_background_tasks'), 20);
        
        // Registra gli hook AJAX immediatamente
        add_action('wp_ajax_imgseo_system_status', array($this, 'ajax_system_status'));
        add_action('wp_ajax_imgseo_force_scan', array($this, 'ajax_force_scan'));
        add_action('wp_ajax_imgseo_clear_cache', array($this, 'ajax_clear_cache'));
        
        // Forza l'inizializzazione se siamo in admin e il sistema non è inizializzato
        if (is_admin() && !$this->is_initialized()) {
            add_action('admin_init', array($this, 'ensure_system_initialized'), 5);
        }
    }
    
    /**
     * Inizializza il sistema completo
     */
    public function init_system() {
        if ($this->initialized) {
            return;
        }
        
        try {
            // 1. Carica le dipendenze
            $this->load_dependencies();
            
            // 2. Inizializza il database
            $this->init_database();
            
            // 3. Inizializza le componenti principali
            $this->init_core_components();
            
            // 4. Registra gli hook
            $this->register_hooks();
            
            // 5. Esegue la migrazione se necessaria
            $this->handle_migration();
            
            // 6. Programma le attività di background
            $this->schedule_background_tasks();
            
            $this->initialized = true;
            
            // Log dell'inizializzazione
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ImgSEO System 2.0 initialized successfully');
            }
            
        } catch (Exception $e) {
            // Log dell'errore
            error_log('ImgSEO System initialization failed: ' . $e->getMessage());
            
            // Fallback al sistema precedente
            $this->fallback_to_legacy_system();
        }
    }
    
    /**
     * Assicura che il sistema sia inizializzato (per admin)
     */
    public function ensure_system_initialized() {
        if (!$this->is_initialized()) {
            $this->init_system();
        }
    }
    
    /**
     * Carica tutte le dipendenze necessarie
     */
    private function load_dependencies() {
        $dependencies = array(
            'class-imgseo-database-manager.php',
            'class-imgseo-image-registry.php',
            'class-imgseo-page-builder-detector.php',
            'class-imgseo-universal-scanner.php',
            'class-imgseo-accurate-stats-calculator.php'
        );
        
        foreach ($dependencies as $file) {
            $file_path = plugin_dir_path(__FILE__) . $file;
            
            if (!file_exists($file_path)) {
                throw new Exception("Required file not found: $file");
            }
            
            require_once $file_path;
        }
    }
    
    /**
     * Inizializza il database
     */
    private function init_database() {
        $db_manager = ImgSEO_Database_Manager::get_instance();
        
        // Verifica se le tabelle esistono
        if (!$db_manager->tables_exist()) {
            $db_manager->create_tables();
        }
        
        // Verifica la versione del database
        $db_manager->check_database_version();
    }
    
    /**
     * Inizializza le componenti principali
     */
    private function init_core_components() {
        // Inizializza tutte le componenti singleton
        ImgSEO_Image_Registry::get_instance();
        ImgSEO_Page_Builder_Detector::get_instance();
        ImgSEO_Universal_Scanner::get_instance();
        ImgSEO_Accurate_Stats_Calculator::get_instance();
        
        // Aggiorna la classe principale dei dati strutturati
        ImgSEO_Structured_Data::get_instance();
    }
    
    /**
     * Registra gli hook di WordPress
     */
    private function register_hooks() {
        // Hook per la pulizia periodica
        add_action('imgseo_cleanup_old_data', array($this, 'cleanup_old_data'));
        
        // Hook per la scansione programmata
        add_action('imgseo_scheduled_scan', array($this, 'run_scheduled_scan'));
        
        // Hook per l'aggiornamento delle statistiche
        add_action('imgseo_update_stats_cache', array($this, 'update_stats_cache'));
        
        // Hook per la disattivazione del plugin
        register_deactivation_hook(IMGSEO_FILE, array($this, 'on_plugin_deactivation'));
    }
    
    /**
     * Gestisce la migrazione dal sistema precedente
     */
    private function handle_migration() {
        $migration_completed = get_option('imgseo_legacy_migration_completed', false);
        
        if (!$migration_completed) {
            $this->migrate_legacy_data();
            update_option('imgseo_legacy_migration_completed', time());
        }
    }
    
    /**
     * Migra i dati dal sistema precedente
     */
    private function migrate_legacy_data() {
        // Per ora, semplicemente segna come completata
        // In futuro, qui potremmo migrare cache esistente o impostazioni
        
        // Esegui una scansione iniziale per popolare il nuovo sistema
        if (get_option('imgseo_initial_scan_completed', false) === false) {
            wp_schedule_single_event(time() + 60, 'imgseo_initial_full_scan');
            update_option('imgseo_initial_scan_completed', true);
        }
    }
    
    /**
     * Programma le attività di background
     */
    public function schedule_background_tasks() {
        // Pulizia dati obsoleti (settimanale)
        if (!wp_next_scheduled('imgseo_cleanup_old_data')) {
            wp_schedule_event(time(), 'weekly', 'imgseo_cleanup_old_data');
        }
        
        // Scansione programmata (frequenza configurabile)
        $scan_frequency = get_option('imgseo_auto_scan_frequency', 'daily');
        if (!wp_next_scheduled('imgseo_scheduled_scan')) {
            wp_schedule_event(time(), $scan_frequency, 'imgseo_scheduled_scan');
        }
        
        // Aggiornamento cache statistiche (ogni 6 ore)
        if (!wp_next_scheduled('imgseo_update_stats_cache')) {
            wp_schedule_event(time(), 'twicedaily', 'imgseo_update_stats_cache');
        }
    }
    
    /**
     * Esegue la pulizia dei dati obsoleti
     */
    public function cleanup_old_data() {
        $db_manager = ImgSEO_Database_Manager::get_instance();
        $image_registry = ImgSEO_Image_Registry::get_instance();
        
        // Pulisci dati obsoleti
        $db_manager->cleanup_old_data();
        $image_registry->cleanup_old_records();
        
        // Log dell'operazione
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ImgSEO: Cleanup completed');
        }
    }
    
    /**
     * Esegue una scansione programmata
     */
    public function run_scheduled_scan() {
        $scanner = ImgSEO_Universal_Scanner::get_instance();
        
        // Esegui scansione completa
        $result = $scanner->scan_entire_site();
        
        // Log del risultato
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $status = $result['success'] ? 'completed' : 'failed';
            error_log("ImgSEO: Scheduled scan $status");
        }
    }
    
    /**
     * Aggiorna la cache delle statistiche
     */
    public function update_stats_cache() {
        $stats_calculator = ImgSEO_Accurate_Stats_Calculator::get_instance();
        
        // Forza il ricalcolo delle statistiche
        $stats_calculator->calculate_site_stats(true);
        
        // Log dell'operazione
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ImgSEO: Stats cache updated');
        }
    }
    
    /**
     * Handler AJAX per lo stato del sistema
     */
    public function ajax_system_status() {
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $db_manager = ImgSEO_Database_Manager::get_instance();
        $stats_calculator = ImgSEO_Accurate_Stats_Calculator::get_instance();
        $scanner = ImgSEO_Universal_Scanner::get_instance();
        
        $status = array(
            'system_version' => $this->system_version,
            'database_status' => $db_manager->tables_exist(),
            'database_stats' => $db_manager->get_database_stats(),
            'last_scan' => $scanner->get_last_scan_time(),
            'last_scan_formatted' => $scanner->get_last_scan_time() ? 
                date('Y-m-d H:i:s', $scanner->get_last_scan_time()) : 'Mai',
            'quick_stats' => $stats_calculator->get_quick_stats(),
            'memory_usage' => memory_get_usage(true),
            'memory_limit' => ini_get('memory_limit')
        );
        
        wp_send_json_success($status);
    }
    
    /**
     * Handler AJAX per forzare una scansione
     */
    public function ajax_force_scan() {
        try {
            // Verifica nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'imgseo_force_scan')) {
                wp_send_json_error('Security check failed');
                return;
            }
            
            // Verifica permessi
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }
            
            // Debug: Verifica che le classi esistano
            if (!class_exists('ImgSEO_Universal_Scanner')) {
                wp_send_json_error('ImgSEO_Universal_Scanner class not found');
                return;
            }
            
            // Verifica che il sistema sia inizializzato
            if (!$this->is_initialized()) {
                // Prova a inizializzare il sistema
                $this->init_system();
                
                if (!$this->is_initialized()) {
                    wp_send_json_error('System not initialized and initialization failed');
                    return;
                }
            }
            
            $scanner = ImgSEO_Universal_Scanner::get_instance();
            
            // Debug: Verifica che lo scanner sia valido
            if (!$scanner) {
                wp_send_json_error('Failed to get scanner instance');
                return;
            }
            
            // Esegui scansione
            $result = $scanner->scan_entire_site();
            
            // Debug: Log del risultato
            error_log('ImgSEO Force Scan Result: ' . print_r($result, true));
            
            if ($result && isset($result['success']) && $result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result ?: 'Scan failed - no result returned');
            }
            
        } catch (Exception $e) {
            error_log('ImgSEO Force Scan Exception: ' . $e->getMessage());
            wp_send_json_error('Error: ' . $e->getMessage());
        } catch (Error $e) {
            error_log('ImgSEO Force Scan Fatal Error: ' . $e->getMessage());
            wp_send_json_error('Fatal Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Handler AJAX per pulire la cache
     */
    public function ajax_clear_cache() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'], 'imgseo_clear_cache')) {
            wp_die('Security check failed');
        }
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $stats_calculator = ImgSEO_Accurate_Stats_Calculator::get_instance();
        $image_registry = ImgSEO_Image_Registry::get_instance();
        
        // Pulisci cache
        $stats_calculator->clear_stats_cache();
        $image_registry->clear_cache();
        
        wp_send_json_success('Cache cleared successfully');
    }
    
    /**
     * Fallback al sistema precedente in caso di errori
     */
    private function fallback_to_legacy_system() {
        // Disabilita il nuovo sistema
        update_option('imgseo_universal_scanner_enabled', 0);
        
        // Log del fallback
        error_log('ImgSEO: Falling back to legacy system due to initialization error');
    }
    
    /**
     * Gestisce la disattivazione del plugin
     */
    public function on_plugin_deactivation() {
        // Rimuovi i cron job programmati
        wp_clear_scheduled_hook('imgseo_cleanup_old_data');
        wp_clear_scheduled_hook('imgseo_scheduled_scan');
        wp_clear_scheduled_hook('imgseo_update_stats_cache');
        wp_clear_scheduled_hook('imgseo_initial_full_scan');
    }
    
    /**
     * Verifica se il sistema è inizializzato
     *
     * @return bool
     */
    public function is_initialized() {
        return $this->initialized;
    }
    
    /**
     * Ottiene la versione del sistema
     *
     * @return string
     */
    public function get_system_version() {
        return $this->system_version;
    }
    
    /**
     * Verifica se il nuovo sistema è abilitato
     *
     * @return bool
     */
    public function is_new_system_enabled() {
        return (bool) get_option('imgseo_universal_scanner_enabled', 1);
    }
    
    /**
     * Ottiene informazioni di debug del sistema
     *
     * @return array
     */
    public function get_debug_info() {
        $db_manager = ImgSEO_Database_Manager::get_instance();
        
        return array(
            'system_version' => $this->system_version,
            'initialized' => $this->initialized,
            'new_system_enabled' => $this->is_new_system_enabled(),
            'database_version' => get_option('imgseo_db_version'),
            'tables_exist' => $db_manager->tables_exist(),
            'migration_completed' => get_option('imgseo_legacy_migration_completed'),
            'initial_scan_completed' => get_option('imgseo_initial_scan_completed'),
            'last_full_scan' => get_option('imgseo_last_full_scan'),
            'scheduled_tasks' => array(
                'cleanup' => wp_next_scheduled('imgseo_cleanup_old_data'),
                'scan' => wp_next_scheduled('imgseo_scheduled_scan'),
                'stats_update' => wp_next_scheduled('imgseo_update_stats_cache')
            )
        );
    }
}

// Inizializza il sistema
ImgSEO_System_Initializer::get_instance();