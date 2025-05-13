<?php
/**
 * Class Divi_Integration
 * Gestisce l'integrazione con il tema Divi e Divi Builder
 */
class Divi_Integration implements Renamer_Integration_Interface {
    
    /**
     * Aggiorna i riferimenti alle immagini in Divi
     * 
     * @param array $old_urls Vecchi URL
     * @param array $new_urls Nuovi URL
     * @param int $attachment_id ID dell'allegato
     * @return array|bool Risultato dell'aggiornamento
     */
    public function update_references($old_urls, $new_urls, $attachment_id) {
        // Verifica se Divi è attivo
        if (!function_exists('et_pb_is_pagebuilder_used') && !function_exists('et_core_is_builder_used_on_current_request')) {
            return array(
                'status' => false,
                'updated' => 0,
                'message' => __('Divi is not active', IMGSEO_TEXT_DOMAIN)
            );
        }
        
        global $wpdb;
        
        $updated_count = 0;
        $updated_layouts = 0;
        
        // 1. Aggiorna i contenuti dei post con Divi Builder
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
        
        // 2. Aggiorna i metadati di Divi
        $divi_meta_keys = array(
            '_et_pb_use_builder',
            '_et_pb_old_content',
            '_et_builder_version',
            '_et_pb_ab_stats_data',
            '_et_pb_ab_bounce_rate_limit',
            '_et_pb_ab_bounce_rate',
            '_et_pb_ab_current_shortcode',
            '_et_pb_built_for_post_type',
            '_et_pb_custom_css',
            '_et_pb_page_layout',
            '_et_pb_side_nav',
            '_et_pb_post_hide_nav',
            '_et_pb_show_title',
            '_et_pb_static_css_file'
        );
        
        foreach ($divi_meta_keys as $meta_key) {
            foreach ($old_urls as $index => $old_url) {
                $divi_data = $wpdb->get_results(
                    "SELECT post_id, meta_value FROM {$wpdb->postmeta} 
                    WHERE meta_key = '{$meta_key}' 
                    AND meta_value LIKE '%" . $wpdb->esc_like($old_url) . "%'"
                );
                
                foreach ($divi_data as $meta) {
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
        
        // 3. Gestione dei layout di Divi
        if (post_type_exists('et_pb_layout')) {
            foreach ($old_urls as $index => $old_url) {
                $layouts = $wpdb->get_results(
                    "SELECT ID, post_content FROM {$wpdb->posts} 
                    WHERE post_type = 'et_pb_layout'
                    AND post_content LIKE '%" . $wpdb->esc_like($old_url) . "%'"
                );
                
                foreach ($layouts as $layout) {
                    $layout_content = $layout->post_content;
                    $new_layout_content = str_replace($old_url, $new_urls[$index], $layout_content);
                    
                    if ($new_layout_content !== $layout_content) {
                        $wpdb->update(
                            $wpdb->posts,
                            array('post_content' => $new_layout_content),
                            array('ID' => $layout->ID)
                        );
                        $updated_layouts++;
                    }
                }
            }
        }
        
        // 4. Gestione dei dati serializzati di Divi
        $serialized_keys = array(
            '_et_pb_homepage_builder',
            '_et_pb_builder_settings',
            '_et_pb_module_settings'
        );
        
        foreach ($serialized_keys as $meta_key) {
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
        
        // 5. Pulisci la cache di Divi
        if (function_exists('et_core_clear_cache')) {
            et_core_clear_cache();
        }
        
        return array(
            'status' => true,
            'updated' => $updated_count + $updated_layouts,
            'message' => sprintf(
                __('Updated %d references in posts/pages and %d Divi layouts', IMGSEO_TEXT_DOMAIN),
                $updated_count, 
                $updated_layouts
            )
        );
    }
}
