<?php
/**
 * Classe per la gestione della libreria media
 * 
 * @package ImgSEO
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe ImgSEO_Media_Library_Manager
 * Gestisce le funzionalità relative alla libreria media di WordPress
 */
class ImgSEO_Media_Library_Manager extends ImgSEO_Generator_Base {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add hook for alt text column in media library
        add_filter('manage_media_columns', array($this, 'add_alt_text_column'));
        add_action('manage_media_custom_column', array($this, 'render_alt_text_column'), 10, 2);
        add_filter('manage_upload_sortable_columns', array($this, 'make_alt_text_column_sortable'));
        
        // Add bulk actions in media library
        add_filter('bulk_actions-upload', array($this, 'register_bulk_actions'));
        add_filter('handle_bulk_actions-upload', array($this, 'handle_bulk_actions'), 10, 3);
    }
    
    /**
     * Aggiunge una colonna per il testo alternativo nella libreria media
     * 
     * @param array $columns Colonne esistenti
     * @return array Colonne aggiornate
     */
    public function add_alt_text_column($columns) {
        $columns['alt_text'] = __('Alt Text', IMGSEO_TEXT_DOMAIN);
        return $columns;
    }
    
    /**
     * Renderizza il contenuto della colonna del testo alternativo
     * 
     * @param string $column_name Nome della colonna
     * @param int $post_id ID del post
     */
    public function render_alt_text_column($column_name, $post_id) {
        if ($column_name == 'alt_text') {
            $alt_text = get_post_meta($post_id, '_wp_attachment_image_alt', true);
            echo esc_html($alt_text);
        }
    }
    
    /**
     * Rende sortabile la colonna del testo alternativo
     * 
     * @param array $columns Colonne sortabili
     * @return array Colonne sortabili aggiornate
     */
    public function make_alt_text_column_sortable($columns) {
        $columns['alt_text'] = 'alt_text';
        return $columns;
    }
    
    /**
     * Registra le azioni bulk nella libreria media
     * 
     * @param array $bulk_actions Azioni bulk esistenti
     * @return array Azioni bulk aggiornate
     */
    public function register_bulk_actions($bulk_actions) {
        $bulk_actions['imgseo_generate_alt'] = __('Generate Alt Texts', IMGSEO_TEXT_DOMAIN);
        $bulk_actions['imgseo_generate_alt_overwrite'] = __('Generate Alt Texts (Overwrite)', IMGSEO_TEXT_DOMAIN);
        return $bulk_actions;
    }
    
    /**
     * Gestisce le azioni bulk nella libreria media
     * 
     * @param string $redirect_to URL di reindirizzamento
     * @param string $doaction Azione da eseguire
     * @param array $post_ids ID dei post selezionati
     * @return string URL di reindirizzamento aggiornato
     */
    public function handle_bulk_actions($redirect_to, $doaction, $post_ids) {
        if ($doaction !== 'imgseo_generate_alt' && $doaction !== 'imgseo_generate_alt_overwrite') {
            return $redirect_to;
        }
        
        $overwrite = ($doaction === 'imgseo_generate_alt_overwrite');
        $processed = 0;
        
        foreach ($post_ids as $post_id) {
            if (!wp_attachment_is_image($post_id)) {
                continue;
            }
            
            $current_alt_text = get_post_meta($post_id, '_wp_attachment_image_alt', true);
            if (!$overwrite && !empty($current_alt_text)) {
                continue; // Salta se non deve sovrascrivere e ha già un testo alt
            }
            
            // Use smaller image size for bulk operations to optimize API calls
            $image_url = null;
            $image_sizes = array('large', 'medium_large', 'medium', 'thumbnail');
            
            // Try to get a thumbnail version first
            foreach ($image_sizes as $size) {
                $image_size = wp_get_attachment_image_src($post_id, $size);
                if ($image_size && is_array($image_size) && !empty($image_size[0])) {
                    $image_url = $image_size[0];
                    break;
                }
            }
            
            // Fallback to original if no thumbnails available
            if (!$image_url) {
                $image_url = wp_get_attachment_url($post_id);
            }
            
            if (!$image_url) {
                continue; // Skip if no valid URL found
            }
            
            $parent_post_id = wp_get_post_parent_id($post_id);
            $parent_post_title = $parent_post_id ? get_the_title($parent_post_id) : '';
            
            $alt_text = $this->generate_alt_text($image_url, $post_id, $parent_post_title);
            
            if (!is_wp_error($alt_text)) {
                update_post_meta($post_id, '_wp_attachment_image_alt', $alt_text);
                $processed++;
                
                // Aggiorna gli altri campi in base alle opzioni
                $update_title = get_option('imgseo_update_title', 0);
                $update_caption = get_option('imgseo_update_caption', 0);
                $update_description = get_option('imgseo_update_description', 0);
                
                $attachment_data = ['ID' => $post_id];
                
                if ($update_title) {
                    $attachment_data['post_title'] = $alt_text;
                }
                
                if ($update_caption) {
                    $attachment_data['post_excerpt'] = $alt_text;
                }
                
                if ($update_description) {
                    $attachment_data['post_content'] = $alt_text;
                }
                
                if (count($attachment_data) > 1) {
                    wp_update_post($attachment_data);
                }
            }
        }
        
        $redirect_to = add_query_arg('imgseo_processed', $processed, $redirect_to);
        return $redirect_to;
    }
}
