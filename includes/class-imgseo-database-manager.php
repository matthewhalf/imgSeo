<?php
/**
 * Classe per la gestione del database del sistema ImgSEO
 * Gestisce la creazione e aggiornamento delle tabelle per il nuovo sistema di scansione
 *
 * @package ImgSEO
 * @since 2.0.0
 */

// Impedisce l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe ImgSEO_Database_Manager
 * 
 * Gestisce la struttura del database per il sistema di scansione avanzato
 */
class ImgSEO_Database_Manager {
    
    /**
     * Versione corrente del database
     */
    const DB_VERSION = '2.0.0';
    
    /**
     * Istanza singleton della classe
     *
     * @var ImgSEO_Database_Manager|null
     */
    private static $instance = null;
    
    /**
     * Ottiene l'istanza singleton della classe
     *
     * @return ImgSEO_Database_Manager
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
        // Hook per l'attivazione del plugin
        register_activation_hook(IMGSEO_FILE, array($this, 'create_tables'));
        
        // Hook per verificare aggiornamenti database
        add_action('plugins_loaded', array($this, 'check_database_version'));
    }
    
    /**
     * Verifica se il database necessita di aggiornamenti
     */
    public function check_database_version() {
        $installed_version = get_option('imgseo_db_version', '1.0.0');
        
        if (version_compare($installed_version, self::DB_VERSION, '<')) {
            $this->create_tables();
            update_option('imgseo_db_version', self::DB_VERSION);
        }
    }
    
    /**
     * Crea tutte le tabelle necessarie per il sistema
     */
    public function create_tables() {
        global $wpdb;
        
        // Richiede il file per dbDelta
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabella principale per il registro delle immagini per contenuto
        $table_content_images = $wpdb->prefix . 'imgseo_content_images';
        $sql_content_images = "CREATE TABLE $table_content_images (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            content_type varchar(50) NOT NULL,
            content_id bigint(20) DEFAULT NULL,
            content_url varchar(500) DEFAULT NULL,
            image_url varchar(500) NOT NULL,
            image_context varchar(100) DEFAULT 'content',
            attachment_id bigint(20) DEFAULT NULL,
            has_alt_text tinyint(1) DEFAULT 0,
            alt_text text DEFAULT NULL,
            image_title varchar(255) DEFAULT NULL,
            source_location varchar(255) DEFAULT NULL,
            image_width int DEFAULT NULL,
            image_height int DEFAULT NULL,
            last_scanned datetime DEFAULT CURRENT_TIMESTAMP,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            KEY content_type_id (content_type, content_id),
            KEY content_url (content_url(255)),
            KEY attachment_id (attachment_id),
            KEY image_url (image_url(255)),
            KEY is_active (is_active),
            KEY last_scanned (last_scanned)
        ) $charset_collate;";
        
        // Tabella per l'indice veloce URL → Attachment ID
        $table_url_index = $wpdb->prefix . 'imgseo_url_index';
        $sql_url_index = "CREATE TABLE $table_url_index (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            image_url varchar(500) NOT NULL,
            attachment_id bigint(20) DEFAULT NULL,
            url_hash varchar(32) NOT NULL,
            last_verified datetime DEFAULT CURRENT_TIMESTAMP,
            verification_count int DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY url_hash (url_hash),
            KEY attachment_id (attachment_id),
            KEY last_verified (last_verified)
        ) $charset_collate;";
        
        // Tabella per lo stato della scansione
        $table_scan_status = $wpdb->prefix . 'imgseo_scan_status';
        $sql_scan_status = "CREATE TABLE $table_scan_status (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            content_type varchar(50) NOT NULL,
            content_id bigint(20) DEFAULT NULL,
            content_url varchar(500) DEFAULT NULL,
            last_scanned datetime DEFAULT CURRENT_TIMESTAMP,
            scan_duration float DEFAULT NULL,
            images_found int DEFAULT 0,
            scan_status enum('pending', 'scanning', 'completed', 'error') DEFAULT 'pending',
            error_message text DEFAULT NULL,
            scan_method varchar(100) DEFAULT 'auto',
            PRIMARY KEY (id),
            UNIQUE KEY content_identifier (content_type, content_id, content_url(255)),
            KEY scan_status (scan_status),
            KEY last_scanned (last_scanned)
        ) $charset_collate;";
        
        // Tabella per le statistiche aggregate (cache)
        $table_stats_cache = $wpdb->prefix . 'imgseo_stats_cache';
        $sql_stats_cache = "CREATE TABLE $table_stats_cache (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            stat_key varchar(100) NOT NULL,
            stat_value longtext NOT NULL,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY stat_key (stat_key),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        // Esegui la creazione delle tabelle
        dbDelta($sql_content_images);
        dbDelta($sql_url_index);
        dbDelta($sql_scan_status);
        dbDelta($sql_stats_cache);
        
        // Crea indici aggiuntivi per performance
        $this->create_additional_indexes();
        
        // Inizializza dati di base
        $this->initialize_default_data();
    }
    
    /**
     * Crea indici aggiuntivi per migliorare le performance
     */
    private function create_additional_indexes() {
        global $wpdb;
        
        $table_content_images = $wpdb->prefix . 'imgseo_content_images';
        
        // Indice composito per query frequenti
        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_content_active ON $table_content_images (content_type, is_active, last_scanned)");
        $wpdb->query("CREATE INDEX IF NOT EXISTS idx_attachment_alt ON $table_content_images (attachment_id, has_alt_text)");
    }
    
    /**
     * Inizializza dati di default
     */
    private function initialize_default_data() {
        // Imposta opzioni di default per il nuovo sistema
        add_option('imgseo_universal_scanner_enabled', 1);
        add_option('imgseo_scan_batch_size', 50);
        add_option('imgseo_scan_timeout', 300); // 5 minuti
        add_option('imgseo_cache_expiry_hours', 24);
        add_option('imgseo_auto_scan_frequency', 'daily');
        add_option('imgseo_scan_external_images', 1);
        add_option('imgseo_scan_background_images', 1);
        add_option('imgseo_scan_page_builders', 1);
    }
    
    /**
     * Ottiene il nome della tabella con prefisso
     *
     * @param string $table_name Nome della tabella senza prefisso
     * @return string Nome completo della tabella
     */
    public function get_table_name($table_name) {
        global $wpdb;
        return $wpdb->prefix . 'imgseo_' . $table_name;
    }
    
    /**
     * Verifica se le tabelle esistono
     *
     * @return bool
     */
    public function tables_exist() {
        global $wpdb;
        
        $tables = array(
            'imgseo_content_images',
            'imgseo_url_index',
            'imgseo_scan_status',
            'imgseo_stats_cache'
        );
        
        foreach ($tables as $table) {
            $table_name = $this->get_table_name(str_replace('imgseo_', '', $table));
            $result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
            if ($result !== $table_name) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Pulisce i dati obsoleti
     */
    public function cleanup_old_data() {
        global $wpdb;
        
        $table_content_images = $this->get_table_name('content_images');
        $table_url_index = $this->get_table_name('url_index');
        $table_stats_cache = $this->get_table_name('stats_cache');
        
        // Rimuovi immagini non attive più vecchie di 30 giorni
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_content_images 
             WHERE is_active = 0 
             AND last_scanned < DATE_SUB(NOW(), INTERVAL %d DAY)",
            30
        ));
        
        // Rimuovi URL index non verificati da più di 7 giorni
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_url_index 
             WHERE last_verified < DATE_SUB(NOW(), INTERVAL %d DAY)",
            7
        ));
        
        // Rimuovi cache statistiche scadute
        $wpdb->query("DELETE FROM $table_stats_cache WHERE expires_at IS NOT NULL AND expires_at < NOW()");
    }
    
    /**
     * Ottiene statistiche sulle tabelle
     *
     * @return array
     */
    public function get_database_stats() {
        global $wpdb;
        
        $stats = array();
        
        $tables = array(
            'content_images' => 'Immagini per Contenuto',
            'url_index' => 'Indice URL',
            'scan_status' => 'Stato Scansioni',
            'stats_cache' => 'Cache Statistiche'
        );
        
        foreach ($tables as $table => $label) {
            $table_name = $this->get_table_name($table);
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $stats[$table] = array(
                'label' => $label,
                'count' => intval($count)
            );
        }
        
        return $stats;
    }
    
    /**
     * Esegue la migrazione dei dati dal vecchio sistema
     */
    public function migrate_legacy_data() {
        // Questa funzione sarà implementata per migrare i dati esistenti
        // dal vecchio sistema al nuovo formato
        
        // Per ora, registra che la migrazione è stata eseguita
        update_option('imgseo_legacy_migration_completed', time());
    }
    
    /**
     * Rimuove tutte le tabelle del plugin (per disinstallazione)
     */
    public function drop_tables() {
        global $wpdb;
        
        $tables = array(
            'imgseo_content_images',
            'imgseo_url_index',
            'imgseo_scan_status',
            'imgseo_stats_cache'
        );
        
        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $wpdb->query("DROP TABLE IF EXISTS $table_name");
        }
        
        // Rimuovi le opzioni
        delete_option('imgseo_db_version');
        delete_option('imgseo_universal_scanner_enabled');
        delete_option('imgseo_scan_batch_size');
        delete_option('imgseo_scan_timeout');
        delete_option('imgseo_cache_expiry_hours');
        delete_option('imgseo_auto_scan_frequency');
        delete_option('imgseo_scan_external_images');
        delete_option('imgseo_scan_background_images');
        delete_option('imgseo_scan_page_builders');
        delete_option('imgseo_legacy_migration_completed');
    }
}