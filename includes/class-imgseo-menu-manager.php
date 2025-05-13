<?php
/**
 * Class ImgSEO_Menu_Manager
 * Gestisce centralmente tutti i menu e sottomenu del plugin ImgSEO
 */
class ImgSEO_Menu_Manager {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Initialize the class
     */
    private function __construct() {
        // Registra il gestore dei menu con priorità standard
        add_action('admin_menu', array($this, 'register_all_menus'), 10);
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
     * Registra tutti i menu e sottomenu del plugin in un unico posto
     */
    public function register_all_menus() {
        // Crea il menu principale
        add_menu_page(
            'ImgSEO',                             // Titolo della pagina
            'ImgSEO',                             // Testo del menu
            'manage_options',                     // Capability richiesta
            'imgseo',                             // Slug del menu
            array($this, 'render_settings_page'), // Callback per la pagina principale
            'dashicons-images-alt2',              // Icona
            81                                    // Posizione
        );
        
        // Aggiungi i sottomenu
        
        // Settings (primo sottomenu, che corrisponde al menu principale)
        add_submenu_page(
            'imgseo',                             // Slug del menu principale
            __('Settings', IMGSEO_TEXT_DOMAIN),   // Titolo pagina
            __('Settings', IMGSEO_TEXT_DOMAIN),   // Testo menu
            'manage_options',                     // Capability
            'imgseo',                             // Slug (stesso del principale per il primo sottomenu)
            array($this, 'render_settings_page')  // Callback
        );
        
        // Bulk Generation
        add_submenu_page(
            'imgseo',
            __('Bulk Generation', IMGSEO_TEXT_DOMAIN),
            __('Bulk Generation', IMGSEO_TEXT_DOMAIN),
            'manage_options',
            'imgseo-bulk',
            array($this, 'render_bulk_page')
        );
        
        // Image Renamer
        add_submenu_page(
            'imgseo',
            __('Image Renamer', IMGSEO_TEXT_DOMAIN),
            __('Image Renamer', IMGSEO_TEXT_DOMAIN),
            'manage_options',
            'imgseo-renamer',
            array($this, 'render_renamer_page')
        );
    }
    
    /**
     * Renderizza la pagina delle impostazioni (delega a ImgSEO_Settings)
     */
    public function render_settings_page() {
        // Ottieni l'istanza della classe ImgSEO_Settings
        $settings_manager = ImgSEO_Settings::get_instance();
        // Delega il rendering della pagina
        $settings_manager->render_settings_page();
    }
    
    /**
     * Renderizza la pagina bulk generation (delega a ImgSEO_Settings)
     */
    public function render_bulk_page() {
        // Ottieni l'istanza della classe ImgSEO_Settings
        $settings_manager = ImgSEO_Settings::get_instance();
        // Delega il rendering della pagina
        $settings_manager->render_bulk_page();
    }
    
    /**
     * Renderizza la pagina del renamer (delega a Renamer_UI_Manager)
     */
    public function render_renamer_page() {
        // Ottieni l'istanza della classe Renamer_UI_Manager
        $renamer_ui = Renamer_UI_Manager::get_instance();
        // Delega il rendering della pagina
        $renamer_ui->render_renamer_page();
    }
}
