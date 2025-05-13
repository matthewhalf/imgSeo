<?php
/**
 * Class Renamer_Integration_Manager
 * Gestisce l'integrazione con page builder e altri plugin
 */
class Renamer_Integration_Manager {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Integrations registrate
     */
    private $integrations = array();
    
    /**
     * Inizializza il manager
     */
    private function __construct() {
        // Crea directory per le integrazioni se non esiste
        $integration_dir = dirname(__FILE__) . '/integrations';
        if (!file_exists($integration_dir)) {
            wp_mkdir_p($integration_dir);
        }
        
        // Carica le integrazioni in base alle impostazioni
        $this->load_active_integrations();
        
        // Action hook per permettere il caricamento di integrazioni personalizzate
        do_action('imgseo_renamer_load_integrations', $this);
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
     * Carica le integrazioni attive
     */
    private function load_active_integrations() {
        // Controlla quali integrazioni sono abilitate nelle impostazioni
        
        // Elementor
        if ($this->is_integration_enabled('elementor')) {
            $this->load_integration('elementor', 'Elementor_Integration');
        }
        
        // Visual Composer
        if ($this->is_integration_enabled('visualcomposer')) {
            $this->load_integration('visualcomposer', 'VisualComposer_Integration');
        }
        
        // Divi
        if ($this->is_integration_enabled('divi')) {
            $this->load_integration('divi', 'Divi_Integration');
        }
        
        // Beaver Builder
        if ($this->is_integration_enabled('beaver')) {
            $this->load_integration('beaver', 'Beaver_Integration');
        }
    }
    
    /**
     * Verifica se un'integrazione è abilitata
     * 
     * @param string $integration Nome dell'integrazione
     * @return bool
     */
    private function is_integration_enabled($integration) {
        $option_name = 'imgseo_renamer_' . $integration . '_support';
        return (bool) get_option($option_name, 1); // Default: abilitata
    }
    
    /**
     * Carica un file di integrazione
     * 
     * @param string $slug Slug dell'integrazione
     * @param string $class_name Nome della classe
     * @return bool Successo o fallimento
     */
    private function load_integration($slug, $class_name) {
        $file_path = dirname(__FILE__) . '/integrations/class-' . $slug . '-integration.php';
        
        if (file_exists($file_path)) {
            require_once $file_path;
            
            if (class_exists($class_name)) {
                $this->integrations[$slug] = new $class_name();
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Registra manualmente un'integrazione
     * 
     * @param string $slug Slug dell'integrazione
     * @param object $integration Oggetto integrazione
     */
    public function register_integration($slug, $integration) {
        if (is_object($integration) && method_exists($integration, 'update_references')) {
            $this->integrations[$slug] = $integration;
            return true;
        }
        
        return false;
    }
    
    /**
     * Aggiorna i riferimenti alle immagini in tutti i plugin supportati
     * 
     * @param array $old_urls Vecchi URL
     * @param array $new_urls Nuovi URL
     * @param int $attachment_id ID dell'allegato
     * @return array Risultati dell'aggiornamento
     */
    public function update_all_references($old_urls, $new_urls, $attachment_id) {
        $results = array();
        
        foreach ($this->integrations as $key => $integration) {
            if (method_exists($integration, 'update_references')) {
                $result = $integration->update_references($old_urls, $new_urls, $attachment_id);
                $results[$key] = $result;
            }
        }
        
        return $results;
    }
    
    /**
     * Ottiene tutte le integrazioni attive
     * 
     * @return array Integrazioni attive
     */
    public function get_active_integrations() {
        return $this->integrations;
    }
}

/**
 * Interfaccia base per integrazioni
 */
interface Renamer_Integration_Interface {
    /**
     * Aggiorna i riferimenti alle immagini
     * 
     * @param array $old_urls Vecchi URL
     * @param array $new_urls Nuovi URL
     * @param int $attachment_id ID dell'allegato
     * @return array|bool Risultato dell'aggiornamento
     */
    public function update_references($old_urls, $new_urls, $attachment_id);
}