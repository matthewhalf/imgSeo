<?php
/**
 * Class VisualComposer_Integration
 * Gestisce l'integrazione con Visual Composer / WPBakery Page Builder
 */
class VisualComposer_Integration implements Renamer_Integration_Interface {
    
    /**
     * Aggiorna i riferimenti alle immagini in Visual Composer
     * 
     * @param array $old_urls Vecchi URL
     * @param array $new_urls Nuovi URL
     * @param int $attachment_id ID dell'allegato
     * @return array|bool Risultato dell'aggiornamento
     */
    public function update_references($old_urls, $new_urls, $attachment_id) {
        // Verifica se Visual Composer è attivo
        if (!class_exists('WPBakeryVisualComposerAbstract') && !class_exists('WPBakeryShortCode')) {
            return array(
                'status' => false,
                'updated' => 0,
                'message' => __('Visual Composer is not active', IMGSEO_TEXT_DOMAIN)
            );
        }
        
        global $wpdb;
        
        $updated_count = 0;
        
        // 1. Aggiorna shortcode nei contenuti
        foreach ($old_urls as $index => $old_url) {
            // Cerca post che contengono l'URL dell'immagine
            $posts = $wpdb->get_results(
                "SELECT ID, post_content FROM {$wpdb->posts} 
                WHERE post_content LIKE '%" . $wpdb->esc_like($old_url) . "%'"
            );
            
            foreach ($posts as $post) {
                $post_content = $post->post_content;
                $new_content = str_replace($old_url, $new_urls[$index], $post_content);
                
                if ($new_content !== $post_content) {
                    $wpdb->update(
                        $wpdb->posts,
                        array('post_content' => $new_content),
                        array('ID' => $post->ID)
                    );
                    $updated_count++;
                }
            }
        }
        
        // 2. Aggiorna i metadati di VC
        $vc_meta_keys = array(
            '_wpb_shortcodes_custom_css',
            '_wpb_post_custom_css',
            'vc_post_settings',
            '_vc_post_settings'
        );
        
        foreach ($vc_meta_keys as $meta_key) {
            foreach ($old_urls as $index => $old_url) {
                $vc_data = $wpdb->get_results(
                    "SELECT post_id, meta_value FROM {$wpdb->postmeta} 
                    WHERE meta_key = '{$meta_key}' 
                    AND meta_value LIKE '%" . $wpdb->esc_like($old_url) . "%'"
                );
                
                foreach ($vc_data as $meta) {
                    $meta_value = $meta->meta_value;
                    $new_value = str_replace($old_url, $new_urls[$index], $meta_value);
                    
                    if ($new_value !== $meta_value) {
                        $wpdb->update(
                            $wpdb->postmeta,
                            array('meta_value' => $new_value),
                            array('post_id' => $meta->post_id, 'meta_key' => $meta_key)
                        );
                        $updated_count++;
                    }
                }
            }
        }
        
        // 3. Gestisce i metadati dell'editor con array serializzati
        $serialized_meta_keys = array(
            'vc_grid_id',
            '_vc_grid_data'
        );
        
        foreach ($serialized_meta_keys as $meta_key) {
            foreach ($old_urls as $index => $old_url) {
                $serialized_data = $wpdb->get_results(
                    "SELECT post_id, meta_value FROM {$wpdb->postmeta} 
                    WHERE meta_key = '{$meta_key}' 
                    AND meta_value LIKE '%" . $wpdb->esc_like($old_url) . "%'"
                );
                
                foreach ($serialized_data as $meta) {
                    $meta_value = $meta->meta_value;
                    
                    // Manipolazione sicura di dati serializzati
                    $data = maybe_unserialize($meta_value);
                    if (is_array($data) || is_object($data)) {
                        // Converti in JSON e sostituisci
                        $json = json_encode($data);
                        $new_json = str_replace($old_url, $new_urls[$index], $json);
                        
                        if ($new_json !== $json) {
                            $new_data = json_decode($new_json, true);
                            
                            if (is_array($new_data) || is_object($new_data)) {
                                $wpdb->update(
                                    $wpdb->postmeta,
                                    array('meta_value' => maybe_serialize($new_data)),
                                    array('post_id' => $meta->post_id, 'meta_key' => $meta_key)
                                );
                                $updated_count++;
                            }
                        }
                    }
                }
            }
        }
        
        return array(
            'status' => true,
            'updated' => $updated_count,
            'message' => sprintf(__('Updated %d Visual Composer references', IMGSEO_TEXT_DOMAIN), $updated_count)
        );
    }
}
