<?php
/**
 * Class Renamer_File_Processor
 * Handles the actual file renaming operations
 */
class Renamer_File_Processor {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Managers interni
     */
    private $logs_manager;
    private $pattern_manager;
    private $integration_manager;
    private $settings_manager;
    
    /**
     * Initialize the class and set its properties.
     */
    private function __construct() {
        // Dipendenza da logs manager per le operazioni di logging
        require_once plugin_dir_path(__FILE__) . 'class-renamer-logs-manager.php';
        $this->logs_manager = Renamer_Logs_Manager::get_instance();
        
        // Le altre dipendenze potrebbero non essere ancora disponibili qui
        add_action('init', array($this, 'late_initialize'), 20);
    }
    
    /**
     * Inizializzazione tardiva per componenti che potrebbero non essere ancora disponibili
     */
    public function late_initialize() {
        if (class_exists('Renamer_Pattern_Manager')) {
            $this->pattern_manager = Renamer_Pattern_Manager::get_instance();
        }
        
        if (class_exists('Renamer_Settings_Manager')) {
            $this->settings_manager = Renamer_Settings_Manager::get_instance();
        }
        
        // Non caricare l'integration manager immediatamente per risparmiare memoria
        // Verrà caricato solo quando effettivamente necessario
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
     * Rename an image file
     * 
     * @param int $attachment_id The attachment ID
     * @param string $new_filename The new filename (without extension)
     * @param array $options Opzioni aggiuntive per la rinomina
     * @return array|WP_Error Result of the rename operation
     */
    public function rename_image($attachment_id, $new_filename, $options = array()) {
        // Check if attachment exists
        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            return new WP_Error('invalid_attachment', __('Invalid attachment.', IMGSEO_TEXT_DOMAIN));
        }
        
        // Get attachment file path
        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) {
            return new WP_Error('file_not_found', __('Attachment file not found.', IMGSEO_TEXT_DOMAIN));
        }
        
        // Get file info
        $path_parts = pathinfo($file);
        $old_filename = basename($file);
        $old_filename_base = $path_parts['filename'];
        $extension = isset($path_parts['extension']) ? $path_parts['extension'] : '';
        $dir_path = $path_parts['dirname'];
        
        if (empty($extension)) {
            return new WP_Error('no_extension', __('Could not determine file extension.', IMGSEO_TEXT_DOMAIN));
        }
        
        // Applica pattern se pattern manager è disponibile e l'opzione è attivata
        if (!empty($options['use_patterns']) && !empty($options['pattern']) && $this->pattern_manager) {
            $context = array(
                'original_filename' => $old_filename_base,
                'attachment_id' => $attachment_id,
            );
            
            // Genera il nuovo nome file usando il pattern fornito
            $pattern_result = $this->pattern_manager->apply_patterns($options['pattern'], $attachment_id, $context);
            
            // Usa il risultato del pattern solo se non è vuoto
            if (!empty($pattern_result)) {
                $new_filename = $pattern_result;
            }
        }
        
        // Applica sanitizzazione opzionale al nuovo nome file
        if (!empty($options['sanitize'])) {
            $sanitize_options = array();
            
            // Imposta opzioni di sanitizzazione dalle opzioni o dalle impostazioni
            $sanitize_options['remove_accents'] = isset($options['remove_accents']) ? 
                (bool)$options['remove_accents'] : 
                ($this->settings_manager ? $this->settings_manager->is_enabled('remove_accents') : true);
                
            $sanitize_options['lowercase'] = isset($options['lowercase']) ? 
                (bool)$options['lowercase'] : 
                ($this->settings_manager ? $this->settings_manager->is_enabled('lowercase') : true);
            
            // Sanitizza usando il pattern manager o una sanitizzazione base
            if ($this->pattern_manager) {
                $new_filename = $this->pattern_manager->sanitize_filename($new_filename, $sanitize_options);
            } else {
                // Sanitizzazione base fallback
                if ($sanitize_options['remove_accents']) {
                    $new_filename = remove_accents($new_filename);
                }
                
                if ($sanitize_options['lowercase']) {
                    $new_filename = strtolower($new_filename);
                }
                
                // Sostituisci caratteri non alfanumerici con trattini
                $new_filename = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $new_filename);
                $new_filename = preg_replace('/-+/', '-', $new_filename);
                $new_filename = trim($new_filename, '-');
            }
        }
        
        // Gestisci i nomi file duplicati
        $handle_duplicates = isset($options['handle_duplicates']) ? 
            $options['handle_duplicates'] : 
            ($this->settings_manager ? $this->settings_manager->get_setting('handle_duplicates', 'increment') : 'increment');
        
        $new_filename_with_ext = $this->handle_duplicate_filename($new_filename, $extension, $dir_path, $handle_duplicates);
        
        // Se la gestione duplicati restituisce false, significa che non dobbiamo rinominare
        if ($new_filename_with_ext === false) {
            return new WP_Error('duplicate_filename', __('A file with this name already exists and duplicate handling is set to fail.', IMGSEO_TEXT_DOMAIN));
        }
        
        // Ora estraiamo solo il nuovo nome file senza estensione
        $new_filename = pathinfo($new_filename_with_ext, PATHINFO_FILENAME);
        
        // Check if new filename is same as old
        if ($old_filename === $new_filename_with_ext) {
            return new WP_Error('same_filename', __('The new filename is the same as the current one.', IMGSEO_TEXT_DOMAIN));
        }
        
        // Create the new file path
        $new_file = str_replace($old_filename, $new_filename_with_ext, $file);
        
        // Get the current metadata
        $old_metadata = wp_get_attachment_metadata($attachment_id);
        
        // Get current thumbnails before rename
        $old_thumbnails = array();
        if (!empty($old_metadata['sizes'])) {
            foreach ($old_metadata['sizes'] as $size_name => $size_info) {
                $old_thumbnail_file = $dir_path . '/' . $size_info['file'];
                if (file_exists($old_thumbnail_file)) {
                    $old_thumbnails[$size_name] = $old_thumbnail_file;
                }
            }
        }
        
        try {
            // Start a transaction if possible
            global $wpdb;
            $wpdb->query('START TRANSACTION');
            
            // Rename the main file
            if (!@rename($file, $new_file)) {
                $wpdb->query('ROLLBACK');
                $this->logs_manager->log_rename_operation($attachment_id, $old_filename, $new_filename_with_ext, 'error');
                return new WP_Error('rename_failed', __('Failed to rename the file.', IMGSEO_TEXT_DOMAIN));
            }
            
            // Update attachment metadata file path
            update_attached_file($attachment_id, $new_file);
            
            // Manually rename all existing thumbnails - first get a complete inventory of all thumbnails
            $renamed_thumbnails = array();
            $upload_dir = wp_upload_dir();
            $base_dir = $upload_dir['basedir'];
            $file_dir = dirname($file);
            
            // First, rename thumbnails that are in the metadata
            foreach ($old_thumbnails as $size_name => $old_thumbnail_path) {
                $thumb_path_parts = pathinfo($old_thumbnail_path);
                $thumb_old_filename = $thumb_path_parts['basename'];
                
                // Replace the base filename part in the thumbnail filename
                $thumb_new_filename = str_replace($old_filename_base, $new_filename, $thumb_old_filename);
                $thumb_new_path = $thumb_path_parts['dirname'] . '/' . $thumb_new_filename;
                
                // Rename the thumbnail file
                if (file_exists($old_thumbnail_path)) {
                    if (@rename($old_thumbnail_path, $thumb_new_path)) {
                        $renamed_thumbnails[$size_name] = $thumb_new_path;
                        error_log("Renamed thumbnail: $old_thumbnail_path to $thumb_new_path");
                    }
                }
            }
            
            // Then search for any other thumbnail sizes that might not be in the metadata
            // Common WordPress thumbnail pattern is: filename-WIDTHxHEIGHT.ext
            $dir_handle = opendir($file_dir);
            if ($dir_handle) {
                $pattern = '/^' . preg_quote($old_filename_base, '/') . '-\d+x\d+\.[a-zA-Z0-9]+$/';
                
                while (($file_in_dir = readdir($dir_handle)) !== false) {
                    // Skip . and .. directories
                    if ($file_in_dir == '.' || $file_in_dir == '..') {
                        continue;
                    }
                    
                    // Check if this is a thumbnail file for our image
                    if (preg_match($pattern, $file_in_dir)) {
                        $old_thumb_path = $file_dir . '/' . $file_in_dir;
                        
                        // Only continue if this thumbnail wasn't already renamed above
                        if (!in_array($old_thumb_path, $old_thumbnails)) {
                            // Create new thumbnail filename
                            $new_thumb_filename = str_replace($old_filename_base, $new_filename, $file_in_dir);
                            $new_thumb_path = $file_dir . '/' . $new_thumb_filename;
                            
                            // Rename the thumbnail
                            if (@rename($old_thumb_path, $new_thumb_path)) {
                                $renamed_thumbnails['extra_' . count($renamed_thumbnails)] = $new_thumb_path;
                                error_log("Renamed additional thumbnail: $old_thumb_path to $new_thumb_path");
                            }
                        }
                    }
                }
                
                closedir($dir_handle);
            }
            
            // Generate metadata for the attachment
            $metadata = wp_generate_attachment_metadata($attachment_id, $new_file);
            
            // Update the database to use the new metadata
            wp_update_attachment_metadata($attachment_id, $metadata);
            
            // Update post guid if needed
            $guid = get_the_guid($attachment_id);
            if (strpos($guid, $old_filename) !== false) {
                $new_guid = str_replace($old_filename, $new_filename_with_ext, $guid);
                $wpdb->update(
                    $wpdb->posts,
                    array('guid' => $new_guid),
                    array('ID' => $attachment_id)
                );
            }
            
            // Get old and new attachment URLs for reference updates
            $old_url = wp_get_attachment_url($attachment_id);
            $old_url_base = str_replace($new_filename_with_ext, $old_filename, $old_url);
            $new_url = $old_url; // wp_get_attachment_url should now return the updated URL
            
            // Prepara array di vecchi e nuovi URL per gli aggiornamenti
            $old_urls = array($old_url_base);
            $new_urls = array($new_url);
            
            // Raccogli URL di miniature per gli aggiornamenti
            if (!empty($old_metadata['sizes'])) {
                foreach ($old_metadata['sizes'] as $size => $size_info) {
                    $old_size_file = $size_info['file'];
                    $old_size_url = str_replace(basename($old_url_base), $old_size_file, $old_url_base);
                    $old_urls[] = $old_size_url;
                    
                    // Calcola l'URL nuovo corrispondente
                    $new_size_file = str_replace($old_filename_base, $new_filename, $old_size_file);
                    $new_size_url = str_replace(basename($new_url), $new_size_file, $new_url);
                    $new_urls[] = $new_size_url;
                }
            }
            
            // Determina se dobbiamo aggiornare i riferimenti (manteniamo compatibilità con vecchie versioni)
            $update_references = isset($options['update_references']) ? (bool)$options['update_references'] : true;
            
            if ($update_references) {
                // Update URLs in post content with an extended scope
                $this->update_image_references($old_filename_base, $new_filename, $old_url_base, dirname($new_url));
                
                // Aggiorna riferimenti nei page builder usando l'Integration Manager SOLO se necessario
                $integration_results = array();
                $enable_integrations = get_option('imgseo_renamer_enable_integrations', 1);
                
                if ($enable_integrations && !defined('IMGSEO_DISABLE_INTEGRATIONS')) {
                    // Carica l'integration manager solo se effettivamente necessario
                    if (!$this->integration_manager && class_exists('Renamer_Integration_Manager')) {
                        $this->integration_manager = Renamer_Integration_Manager::get_instance();
                    }
                    
                    if ($this->integration_manager) {
                        $integration_results = $this->integration_manager->update_all_references($old_urls, $new_urls, $attachment_id);
                    }
                }
                
                // Force refresh post caches (solo se stiamo aggiornando i riferimenti)
                clean_post_cache($attachment_id);
            }
            
            // Update _wp_attached_file again to ensure it's correctly set
            update_post_meta($attachment_id, '_wp_attached_file', str_replace(trailingslashit($upload_dir['basedir']), '', $new_file));
            
            // Log the successful operation
            $this->logs_manager->log_rename_operation($attachment_id, $old_filename, $new_filename_with_ext, 'success');
            
            // Commit the transaction
            $wpdb->query('COMMIT');
            
            return array(
                'old_filename' => $old_filename,
                'new_filename' => $new_filename_with_ext,
                'new_url' => $new_url,
                'thumbnails_renamed' => count($renamed_thumbnails),
                'integration_results' => $integration_results
            );
            
        } catch (Exception $e) {
            // Rollback the transaction
            $wpdb->query('ROLLBACK');
            
            // Log the error
            $this->logs_manager->log_rename_operation($attachment_id, $old_filename, $new_filename_with_ext, 'error');
            
            return new WP_Error('exception', $e->getMessage());
        }
    }
    
    /**
     * Gestisce la generazione di nomi file in caso di duplicati
     * 
     * @param string $filename Nome file base (senza estensione)
     * @param string $extension Estensione del file
     * @param string $dir_path Percorso della directory
     * @param string $mode Modalità di gestione ("increment", "timestamp", "fail")
     * @return string|false Nuovo nome file con estensione o false se fallisce
     */
    private function handle_duplicate_filename($filename, $extension, $dir_path, $mode = 'increment') {
        $filename_with_ext = $filename . '.' . $extension;
        $file_path = $dir_path . '/' . $filename_with_ext;
        
        // Se il file non esiste già, usa il nome originale
        if (!file_exists($file_path)) {
            return $filename_with_ext;
        }
        
        // Gestisci il caso in cui esiste già un file con questo nome
        switch ($mode) {
            case 'increment':
                // Aggiungi un numero incrementale (file-1.jpg, file-2.jpg, ecc.)
                $i = 1;
                while (file_exists($dir_path . '/' . $filename . '-' . $i . '.' . $extension)) {
                    $i++;
                }
                return $filename . '-' . $i . '.' . $extension;
                
            case 'timestamp':
                // Aggiungi un timestamp (file-1679419361.jpg)
                return $filename . '-' . time() . '.' . $extension;
                
            case 'fail':
                // Non rinominare se esiste già un file con lo stesso nome
                return false;
                
            default:
                return $filename_with_ext;
        }
    }
    
    /**
     * Restore a previously renamed image file to its original filename
     * 
     * @param int $attachment_id The attachment ID
     * @param string $original_filename The original filename to restore to
     * @param string $current_filename The current filename
     * @return array|WP_Error Result of the restore operation
     */
    public function restore_image($attachment_id, $original_filename, $current_filename) {
        // Check if attachment exists
        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            return new WP_Error('invalid_attachment', __('Invalid attachment.', IMGSEO_TEXT_DOMAIN));
        }
        
        // Get attachment file path
        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) {
            return new WP_Error('file_not_found', __('Attachment file not found.', IMGSEO_TEXT_DOMAIN));
        }
        
        // Extract original and current filename components
        $orig_path_parts = pathinfo($original_filename);
        $curr_path_parts = pathinfo($current_filename);
        
        $original_base = $orig_path_parts['filename'];
        $current_base = $curr_path_parts['filename'];
        $extension = isset($curr_path_parts['extension']) ? $curr_path_parts['extension'] : '';
        
        if (empty($extension)) {
            return new WP_Error('no_extension', __('Could not determine file extension.', IMGSEO_TEXT_DOMAIN));
        }
        
        // Get the directory from the current file path
        $dir_path = pathinfo($file, PATHINFO_DIRNAME);
        
        // Set the paths for renaming
        $current_path = $file;
        $original_path = $dir_path . '/' . $original_filename;
        
        // Check if the original filename already exists (to prevent conflicts)
        if (file_exists($original_path)) {
            return new WP_Error('file_exists', __('Cannot restore: a file with the original filename already exists.', IMGSEO_TEXT_DOMAIN));
        }
        
        // Get the current metadata
        $old_metadata = wp_get_attachment_metadata($attachment_id);
        
        // Get current thumbnails before rename
        $current_thumbnails = array();
        if (!empty($old_metadata['sizes'])) {
            foreach ($old_metadata['sizes'] as $size_name => $size_info) {
                $current_thumbnail_file = $dir_path . '/' . $size_info['file'];
                if (file_exists($current_thumbnail_file)) {
                    $current_thumbnails[$size_name] = $current_thumbnail_file;
                }
            }
        }
        
        try {
            // Start a transaction if possible
            global $wpdb;
            $wpdb->query('START TRANSACTION');
            
            // Rename the main file
            if (!@rename($current_path, $original_path)) {
                $wpdb->query('ROLLBACK');
                $this->logs_manager->log_rename_operation($attachment_id, $current_filename, $original_filename, 'error');
                return new WP_Error('rename_failed', __('Failed to restore the file.', IMGSEO_TEXT_DOMAIN));
            }
            
            // Update attachment metadata file path
            update_attached_file($attachment_id, $original_path);
            
            // Manually rename all existing thumbnails - first get a complete inventory of all thumbnails
            $restored_thumbnails = array();
            $upload_dir = wp_upload_dir();
            $base_dir = $upload_dir['basedir'];
            $file_dir = dirname($file);
            
            // First, rename thumbnails that are in the metadata
            foreach ($current_thumbnails as $size_name => $current_thumb_path) {
                $thumb_path_parts = pathinfo($current_thumb_path);
                $thumb_current_filename = $thumb_path_parts['basename'];
                
                // Replace the base filename part in the thumbnail filename
                $thumb_original_filename = str_replace($current_base, $original_base, $thumb_current_filename);
                $thumb_original_path = $thumb_path_parts['dirname'] . '/' . $thumb_original_filename;
                
                // Rename the thumbnail file
                if (file_exists($current_thumb_path)) {
                    if (@rename($current_thumb_path, $thumb_original_path)) {
                        $restored_thumbnails[$size_name] = $thumb_original_path;
                        error_log("Restored thumbnail: $current_thumb_path to $thumb_original_path");
                    }
                }
            }
            
            // Then search for any other thumbnail sizes that might not be in the metadata
            // Common WordPress thumbnail pattern is: filename-WIDTHxHEIGHT.ext
            $dir_handle = opendir($file_dir);
            if ($dir_handle) {
                $pattern = '/^' . preg_quote($current_base, '/') . '-\d+x\d+\.[a-zA-Z0-9]+$/';
                
                while (($file_in_dir = readdir($dir_handle)) !== false) {
                    // Skip . and .. directories
                    if ($file_in_dir == '.' || $file_in_dir == '..') {
                        continue;
                    }
                    
                    // Check if this is a thumbnail file for our image
                    if (preg_match($pattern, $file_in_dir)) {
                        $current_thumb_path = $file_dir . '/' . $file_in_dir;
                        
                        // Only continue if this thumbnail wasn't already renamed above
                        if (!in_array($current_thumb_path, $current_thumbnails)) {
                            // Create new thumbnail filename
                            $thumb_original_filename = str_replace($current_base, $original_base, $file_in_dir);
                            $thumb_original_path = $file_dir . '/' . $thumb_original_filename;
                            
                            // Rename the thumbnail
                            if (@rename($current_thumb_path, $thumb_original_path)) {
                                $restored_thumbnails['extra_' . count($restored_thumbnails)] = $thumb_original_path;
                                error_log("Restored additional thumbnail: $current_thumb_path to $thumb_original_path");
                            }
                        }
                    }
                }
                
                closedir($dir_handle);
            }
            
            // Generate metadata for the attachment
            $metadata = wp_generate_attachment_metadata($attachment_id, $original_path);
            
            // Update the database to use the new metadata
            wp_update_attachment_metadata($attachment_id, $metadata);
            
            // Update post guid if needed
            $guid = get_the_guid($attachment_id);
            if (strpos($guid, $current_filename) !== false) {
                $new_guid = str_replace($current_filename, $original_filename, $guid);
                $wpdb->update(
                    $wpdb->posts,
                    array('guid' => $new_guid),
                    array('ID' => $attachment_id)
                );
            }
            
            // Get old and new attachment URLs for reference updates
            $old_url = wp_get_attachment_url($attachment_id);
            $old_url_base = str_replace($original_filename, $current_filename, $old_url);
            $new_url = $old_url; // wp_get_attachment_url should now return the updated URL
            
            // Prepara array di vecchi e nuovi URL per gli aggiornamenti
            $old_urls = array($old_url_base);
            $new_urls = array($new_url);
            
            // Raccogli URL di miniature per gli aggiornamenti
            if (!empty($old_metadata['sizes'])) {
                foreach ($old_metadata['sizes'] as $size => $size_info) {
                    $current_size_file = $size_info['file'];
                    $current_size_url = str_replace(basename($old_url_base), $current_size_file, $old_url_base);
                    $old_urls[] = $current_size_url;
                    
                    // Calcola l'URL originale corrispondente
                    $original_size_file = str_replace($current_base, $original_base, $current_size_file);
                    $original_size_url = str_replace(basename($new_url), $original_size_file, $new_url);
                    $new_urls[] = $original_size_url;
                }
            }
            
            // Update URLs in post content
            $this->update_image_references($current_base, $original_base, dirname($old_url_base), dirname($new_url));
            
            // Aggiorna riferimenti nei page builder usando l'Integration Manager SOLO se necessario
            $integration_results = array();
            $enable_integrations = get_option('imgseo_renamer_enable_integrations', 1);
            
            if ($enable_integrations && !defined('IMGSEO_DISABLE_INTEGRATIONS')) {
                // Carica l'integration manager solo se effettivamente necessario
                if (!$this->integration_manager && class_exists('Renamer_Integration_Manager')) {
                    $this->integration_manager = Renamer_Integration_Manager::get_instance();
                }
                
                if ($this->integration_manager) {
                    $integration_results = $this->integration_manager->update_all_references($old_urls, $new_urls, $attachment_id);
                }
            }
            
            // Add a short delay to let WordPress process the file changes
            usleep(300000); // 0.3 secondi invece di 1 secondo
            
            // Force refresh post caches
            $this->force_refresh_content_caches();
            
            // Update _wp_attached_file again to ensure it's correctly set
            update_post_meta($attachment_id, '_wp_attached_file', str_replace(trailingslashit($upload_dir['basedir']), '', $original_path));
            
            // Log the successful operation
            $this->logs_manager->log_rename_operation($attachment_id, $current_filename, $original_filename, 'restore');
            
            // Commit the transaction
            $wpdb->query('COMMIT');
            
            return array(
                'old_filename' => $current_filename,
                'new_filename' => $original_filename,
                'new_url' => $new_url,
                'thumbnails_restored' => count($restored_thumbnails),
                'integration_results' => $integration_results
            );
            
        } catch (Exception $e) {
            // Rollback the transaction
            $wpdb->query('ROLLBACK');
            
            // Log the error
            $this->logs_manager->log_rename_operation($attachment_id, $current_filename, $original_filename, 'error');
            
            return new WP_Error('exception', $e->getMessage());
        }
    }

    /**
     * Update image references in post content and metadata
     * 
     * @param string $old_base Old filename base (without extension)
     * @param string $new_base New filename base (without extension)
     * @param string $old_url_base Old URL base (directory part)
     * @param string $new_url_base New URL base (directory part)
     */
    private function update_image_references($old_base, $new_base, $old_url_base, $new_url_base) {
        global $wpdb;
        
        // Debug info
        error_log("Updating image references: old_base={$old_base}, new_base={$new_base}, old_url_base={$old_url_base}, new_url_base={$new_url_base}");
        
        // Make absolutely sure we have trailing slashes for path components
        $old_url_base = rtrim($old_url_base, '/');
        $new_url_base = rtrim($new_url_base, '/');
        
        // First, get ALL posts that could possibly contain images
        // This is more aggressive than before, but ensures we catch everything
        $posts = $wpdb->get_results("SELECT ID, post_content FROM {$wpdb->posts} WHERE post_content LIKE '%<img%' OR post_content LIKE '%wp-image-%'");
        
        // Direct replacement of URLs with old base
        $old_url_pattern = $old_url_base . '/' . $old_base;
        $new_url_pattern = $new_url_base . '/' . $new_base;
                
        foreach ($posts as $post) {
            $updated_content = $post->post_content;
            $content_changed = false;
            
            // 1. Direct replacement for the main file
            $old_main_file = $old_url_base . '/' . $old_base . '.';
            $new_main_file = $new_url_base . '/' . $new_base . '.';
            if (strpos($updated_content, $old_main_file) !== false) {
                $updated_content = str_replace($old_main_file, $new_main_file, $updated_content);
                $content_changed = true;
                error_log("Found and replaced main file URL in post {$post->ID}");
            }
            
            // 2. Replace thumbnail URLs (format: base-300x200.ext)
            $thumb_pattern = '/' . preg_quote($old_url_base . '/' . $old_base, '/') . '-(\d+)x(\d+)\.([a-zA-Z0-9]+)/';
            if (preg_match_all($thumb_pattern, $updated_content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $old_thumb_url = $match[0];
                    $width = $match[1];
                    $height = $match[2];
                    $ext = $match[3];
                    $new_thumb_url = $new_url_base . '/' . $new_base . '-' . $width . 'x' . $height . '.' . $ext;
                    
                    $updated_content = str_replace($old_thumb_url, $new_thumb_url, $updated_content);
                    $content_changed = true;
                    error_log("Found and replaced thumbnail URL {$old_thumb_url} in post {$post->ID}");
                }
            }
            
            // 3. Replace srcset attributes which may contain multiple thumbnail URLs
            $srcset_pattern = '/srcset="([^"]*)' . preg_quote($old_url_base . '/' . $old_base, '/') . '([^"]*)"/';
            if (preg_match_all($srcset_pattern, $updated_content, $srcset_matches, PREG_SET_ORDER)) {
                foreach ($srcset_matches as $srcset_match) {
                    $old_srcset = $srcset_match[0];
                    $srcset_content = $srcset_match[1] . $old_url_base . '/' . $old_base . $srcset_match[2];
                    $new_srcset_content = str_replace(
                        $old_url_base . '/' . $old_base, 
                        $new_url_base . '/' . $new_base,
                        $srcset_content
                    );
                    $new_srcset = 'srcset="' . $new_srcset_content . '"';
                    
                    $updated_content = str_replace($old_srcset, $new_srcset, $updated_content);
                    $content_changed = true;
                    error_log("Found and replaced srcset attribute in post {$post->ID}");
                }
            }
            
            // 4. Replace data-* attributes which may contain image URLs
            $data_pattern = '/data-(?:large|medium|thumb|small|orig|src|image)="([^"]*)' . 
                            preg_quote($old_url_base . '/' . $old_base, '/') . '([^"]*)"/i';
            if (preg_match_all($data_pattern, $updated_content, $data_matches, PREG_SET_ORDER)) {
                foreach ($data_matches as $data_match) {
                    $old_data = $data_match[0];
                    $data_content = $data_match[1] . $old_url_base . '/' . $old_base . $data_match[2];
                    $new_data_content = str_replace(
                        $old_url_base . '/' . $old_base, 
                        $new_url_base . '/' . $new_base,
                        $data_content
                    );
                    $new_data = str_replace($data_content, $new_data_content, $old_data);
                    
                    $updated_content = str_replace($old_data, $new_data, $updated_content);
                    $content_changed = true;
                    error_log("Found and replaced data attribute in post {$post->ID}");
                }
            }
            
            // Update the post if content has changed
            if ($content_changed) {
                $wpdb->update(
                    $wpdb->posts,
                    array('post_content' => $updated_content),
                    array('ID' => $post->ID)
                );
                error_log("Updated post content for post {$post->ID}");
                
                // Clear any post cache
                clean_post_cache($post->ID);
            }
        }
        
        // Update postmeta (for galleries, featured images, etc.)
        $meta_items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta} 
                WHERE meta_value LIKE %s OR meta_value LIKE %s",
                '%' . $wpdb->esc_like($old_url_base) . '%',
                '%' . $wpdb->esc_like($old_base) . '%'
            )
        );
        
        foreach ($meta_items as $meta) {
            $updated_value = $meta->meta_value;
            $value_changed = false;
            
            // Handle serialized data
            if (is_serialized($meta->meta_value)) {
                // Unserialize, modify, and reserialize
                $unserialized = @unserialize($meta->meta_value);
                if ($unserialized !== false) {
                    // Convert to JSON and back to handle nested structures
                    $json = json_encode($unserialized);
                    if ($json !== false) {
                        // Replace in JSON string
                        $old_patterns = array(
                            $old_url_base . '/' . $old_base . '.',
                            '"' . $old_url_base . '/' . $old_base . '-',
                            '{' . $old_url_base . '/' . $old_base . '-',
                            '[' . $old_url_base . '/' . $old_base . '-',
                            ' ' . $old_url_base . '/' . $old_base . '-'
                        );
                        
                        $new_patterns = array(
                            $new_url_base . '/' . $new_base . '.',
                            '"' . $new_url_base . '/' . $new_base . '-',
                            '{' . $new_url_base . '/' . $new_base . '-',
                            '[' . $new_url_base . '/' . $new_base . '-',
                            ' ' . $new_url_base . '/' . $new_base . '-'
                        );
                        
                        $json_new = str_replace($old_patterns, $new_patterns, $json);
                        
                        if ($json_new !== $json) {
                            $value_changed = true;
                            // Convert back to PHP array
                            $modified = json_decode($json_new, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                // Reserialize
                                $updated_value = serialize($modified);
                                error_log("Updated serialized meta data for post {$meta->post_id}, meta key {$meta->meta_key}");
                            }
                        }
                    }
                }
            } 
            // Handle JSON data
            else if (($meta->meta_value[0] === '{' && substr($meta->meta_value, -1) === '}') || 
                    ($meta->meta_value[0] === '[' && substr($meta->meta_value, -1) === ']')) {
                
                // Replace in JSON string
                $json = $meta->meta_value;
                $old_patterns = array(
                    $old_url_base . '/' . $old_base . '.',
                    '"' . $old_url_base . '/' . $old_base . '-',
                    ' ' . $old_url_base . '/' . $old_base . '-'
                );
                
                $new_patterns = array(
                    $new_url_base . '/' . $new_base . '.',
                    '"' . $new_url_base . '/' . $new_base . '-',
                    ' ' . $new_url_base . '/' . $new_base . '-'
                );
                
                $json_new = str_replace($old_patterns, $new_patterns, $json);
                
                if ($json_new !== $json) {
                    $updated_value = $json_new;
                    $value_changed = true;
                    error_log("Updated JSON meta data for post {$meta->post_id}, meta key {$meta->meta_key}");
                }
            }
            // Handle regular string metadata
            else {
                // Direct replacement for full URLs
                if (strpos($meta->meta_value, $old_url_base . '/' . $old_base) !== false) {
                    $updated_value = str_replace(
                        $old_url_base . '/' . $old_base,
                        $new_url_base . '/' . $new_base,
                        $meta->meta_value
                    );
                    $value_changed = true;
                    error_log("Updated plain text meta data for post {$meta->post_id}, meta key {$meta->meta_key}");
                }
            }
            
            // Update the meta if it has changed
            if ($value_changed && $updated_value !== $meta->meta_value) {
                $wpdb->update(
                    $wpdb->postmeta,
                    array('meta_value' => $updated_value),
                    array('meta_id' => $meta->meta_id)
                );
            }
        }
        
        // Clear any caches that might be holding old URLs
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
    
    /**
     * Force refresh all content caches
     * This addresses persistent reference issues after renaming
     */
    private function force_refresh_content_caches() {
        global $wpdb;
        
        // Refresh post caches
        $posts_with_images = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE '%<img%' OR post_content LIKE '%wp-image-%' LIMIT 100");
        
        if (!empty($posts_with_images)) {
            foreach ($posts_with_images as $post_id) {
                clean_post_cache($post_id);
            }
        }
        
        // Flush object cache per specifiche chiavi, non tutto
        if (function_exists('wp_cache_delete_many')) {
            $keys = array('posts', 'post_meta');
            wp_cache_delete_many($keys);
        }
    }
}
