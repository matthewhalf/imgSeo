<?php
/**
 * Class Elementor_Integration
 * Gestisce l'integrazione con Elementor
 */
class Elementor_Integration implements Renamer_Integration_Interface {
    
    /**
     * Aggiorna i riferimenti alle immagini in Elementor
     * 
     * @param array $old_urls Vecchi URL
     * @param array $new_urls Nuovi URL
     * @param int $attachment_id ID dell'allegato
     * @return array|bool Risultato dell'aggiornamento
     */
    public function update_references($old_urls, $new_urls, $attachment_id) {
        // Verifica se Elementor è attivo
        if (!did_action('elementor/loaded')) {
            return array(
                'status' => false,
                'updated' => 0,
                'message' => __('Elementor is not active', IMGSEO_TEXT_DOMAIN)
            );
        }
        
        global $wpdb;
        
        // Ottieni tutti i post che potrebbero contenere dati Elementor
        $elementor_posts = $wpdb->get_results(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} 
            WHERE meta_key = '_elementor_data' 
            AND meta_value LIKE '%" . $wpdb->esc_like('"url":"' . $old_urls[0]) . "%'"
        );
        
        $updated_count = 0;
        
        foreach ($elementor_posts as $e_post) {
            $post_id = $e_post->post_id;
            $data = $e_post->meta_value;
            
            $modified = false;
            
            // Sostituisci tutti gli URL vecchi con i nuovi
            foreach ($old_urls as $index => $old_url) {
                // Gestisci sia url che placeholder interni a Elementor
                $old_patterns = array(
                    '"url":"' . $old_url . '"',
                    '"url":"' . str_replace('/', '\/', $old_url) . '"'
                );
                
                $new_patterns = array(
                    '"url":"' . $new_urls[$index] . '"',
                    '"url":"' . str_replace('/', '\/', $new_urls[$index]) . '"'
                );
                
                $new_data = str_replace($old_patterns, $new_patterns, $data);
                
                if ($new_data !== $data) {
                    $data = $new_data;
                    $modified = true;
                }
            }
            
            // Aggiorna il post meta se sono state fatte modifiche
            if ($modified) {
                // Aggiorna i meta data
                $wpdb->update(
                    $wpdb->postmeta, 
                    array('meta_value' => $data),
                    array('post_id' => $post_id, 'meta_key' => '_elementor_data')
                );
                
                // Forza la rigenerazione dei CSS
                delete_post_meta($post_id, '_elementor_css');
                
                $updated_count++;
            }
        }
        
        return array(
            'status' => true,
            'updated' => $updated_count,
            'message' => sprintf(__('Updated %d Elementor posts', IMGSEO_TEXT_DOMAIN), $updated_count)
        );
    }
}
