<?php
/**
 * Class Beaver_Integration
 * Gestisce l'integrazione con Beaver Builder
 */
class Beaver_Integration implements Renamer_Integration_Interface {
    
    /**
     * Aggiorna i riferimenti alle immagini in Beaver Builder
     * 
     * @param array $old_urls Vecchi URL
     * @param array $new_urls Nuovi URL
     * @param int $attachment_id ID dell'allegato
     * @return array|bool Risultato dell'aggiornamento
     */
    public function update_references($old_urls, $new_urls, $attachment_id) {
        // Verifica se Beaver Builder è attivo
        if (!class_exists('FLBuilder')) {
            return array(
                'status' => false,
                'updated' => 0,
                'message' => __('Beaver Builder is not active', IMGSEO_TEXT_DOMAIN)
            );
        }
        
        global $wpdb;
        
        $updated_count = 0;
        $updated_templates = 0;
        
        // 1. Aggiorna i metadati di Beaver Builder
        $beaver_meta_keys = array(
            '_fl_builder_data',
            '_fl_builder_data_settings',
            '_fl_builder_draft',
            '_fl_builder_draft_settings',
            '_fl_builder_history',
            '_fl_builder_enabled'
        );
        
        foreach ($beaver_meta_keys as $meta_key) {
            foreach ($old_urls as $index => $old_url) {
                $beaver_data = $wpdb->get_results(
                    "SELECT post_id, meta_value FROM {$wpdb->postmeta} 
                    WHERE meta_key = '{$meta_key}' 
                    AND meta_value LIKE '%" . $wpdb->esc_like($old_url) . "%'"
                );
                
                foreach ($beaver_data as $meta) {
                    $meta_value = $meta->meta_value;
                    
                    // I dati di Beaver Builder sono spesso serializzati
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
                    } else {
                        // Per i meta che potrebbero essere stringhe semplici
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
        }
        
        // 2. Aggiorna i template di Beaver Builder
        $template_post_types = array('fl-builder-template', 'fl-theme-layout');
        
        foreach ($template_post_types as $post_type) {
            if (post_type_exists($post_type)) {
                foreach ($old_urls as $index => $old_url) {
                    $templates = $wpdb->get_results(
                        "SELECT p.ID, pm.meta_value 
                        FROM {$wpdb->posts} p
                        JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                        WHERE p.post_type = '{$post_type}'
                        AND pm.meta_key = '_fl_builder_data'
                        AND pm.meta_value LIKE '%" . $wpdb->esc_like($old_url) . "%'"
                    );
                    
                    foreach ($templates as $template) {
                        $template_data = maybe_unserialize($template->meta_value);
                        if (is_array($template_data) || is_object($template_data)) {
                            // Converti in JSON e sostituisci
                            $json = json_encode($template_data);
                            $new_json = str_replace($old_url, $new_urls[$index], $json);
                            
                            if ($new_json !== $json) {
                                $new_data = json_decode($new_json, true);
                                
                                if (is_array($new_data) || is_object($new_data)) {
                                    $wpdb->update(
                                        $wpdb->postmeta,
                                        array('meta_value' => maybe_serialize($new_data)),
                                        array('post_id' => $template->ID, 'meta_key' => '_fl_builder_data')
                                    );
                                    $updated_templates++;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // 3. Aggiorna i CSS generati da Beaver Builder
        $css_meta_keys = array(
            '_fl_builder_css',
            '_fl_builder_css_assets',
            '_fl_builder_rendered_css'
        );
        
        foreach ($css_meta_keys as $meta_key) {
            $wpdb->query(
                "DELETE FROM {$wpdb->postmeta} 
                WHERE meta_key = '{$meta_key}'"
            );
        }
        
        // 4. Pulisci la cache di Beaver Builder
        if (class_exists('FLBuilderModel') && method_exists('FLBuilderModel', 'delete_asset_cache_for_all_posts')) {
            FLBuilderModel::delete_asset_cache_for_all_posts();
        }
        
        return array(
            'status' => true,
            'updated' => $updated_count + $updated_templates,
            'message' => sprintf(
                __('Updated %d references in posts/pages and %d Beaver Builder templates', IMGSEO_TEXT_DOMAIN),
                $updated_count, 
                $updated_templates
            )
        );
    }
}
