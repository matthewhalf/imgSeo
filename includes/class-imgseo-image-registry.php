<?php
/**
 * Classe per la gestione del registro delle immagini
 * Gestisce l'archiviazione, recupero e indicizzazione delle immagini trovate nel sito
 *
 * @package ImgSEO
 * @since 2.0.0
 */

// Impedisce l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe ImgSEO_Image_Registry
 * 
 * Gestisce il registro centralizzato di tutte le immagini trovate nel sito
 */
class ImgSEO_Image_Registry {
    
    /**
     * Istanza singleton della classe
     *
     * @var ImgSEO_Image_Registry|null
     */
    private static $instance = null;
    
    /**
     * Cache locale per evitare query ripetute
     *
     * @var array
     */
    private $cache = array();
    
    /**
     * Database manager instance
     *
     * @var ImgSEO_Database_Manager
     */
    private $db_manager;
    
    /**
     * Ottiene l'istanza singleton della classe
     *
     * @return ImgSEO_Image_Registry
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
        $this->db_manager = ImgSEO_Database_Manager::get_instance();
    }
    
    /**
     * Registra un'immagine nel sistema
     *
     * @param string $content_type Tipo di contenuto (post, page, widget, etc.)
     * @param int|null $content_id ID del contenuto (se applicabile)
     * @param string|null $content_url URL del contenuto (per archivi, etc.)
     * @param array $image_data Dati dell'immagine
     * @return int|false ID del record inserito o false in caso di errore
     */
    public function register_image($content_type, $content_id, $content_url, $image_data) {
        global $wpdb;
        
        // Valida i dati di input
        if (!$this->validate_image_data($image_data)) {
            return false;
        }
        
        $table_name = $this->db_manager->get_table_name('content_images');
        
        // Prepara i dati per l'inserimento
        $data = array(
            'content_type' => sanitize_text_field($content_type),
            'content_id' => $content_id ? intval($content_id) : null,
            'content_url' => $content_url ? esc_url_raw($content_url) : null,
            'image_url' => esc_url_raw($image_data['url']),
            'image_context' => sanitize_text_field($image_data['context'] ?? 'content'),
            'attachment_id' => $this->resolve_attachment_id($image_data['url']),
            'has_alt_text' => !empty($image_data['alt']) ? 1 : 0,
            'alt_text' => sanitize_textarea_field($image_data['alt'] ?? ''),
            'image_title' => sanitize_text_field($image_data['title'] ?? ''),
            'source_location' => sanitize_text_field($image_data['source'] ?? ''),
            'image_width' => isset($image_data['width']) ? intval($image_data['width']) : null,
            'image_height' => isset($image_data['height']) ? intval($image_data['height']) : null,
            'last_scanned' => current_time('mysql'),
            'is_active' => 1
        );
        
        // Verifica se l'immagine esiste già per questo contenuto
        $existing_id = $this->get_existing_image_id($content_type, $content_id, $content_url, $image_data['url']);
        
        if ($existing_id) {
            // Aggiorna il record esistente
            $result = $wpdb->update(
                $table_name,
                $data,
                array('id' => $existing_id),
                array('%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%d'),
                array('%d')
            );
            
            return $result !== false ? $existing_id : false;
        } else {
            // Inserisci nuovo record
            $result = $wpdb->insert(
                $table_name,
                $data,
                array('%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%d')
            );
            
            if ($result !== false) {
                $image_id = $wpdb->insert_id;
                
                // Aggiorna l'indice URL se necessario
                $this->update_url_index($image_data['url'], $data['attachment_id']);
                
                return $image_id;
            }
        }
        
        return false;
    }
    
    /**
     * Ottiene tutte le immagini per un contenuto specifico
     *
     * @param string $content_type Tipo di contenuto
     * @param int|null $content_id ID del contenuto
     * @param string|null $content_url URL del contenuto
     * @param bool $active_only Se restituire solo immagini attive
     * @return array Array di immagini
     */
    public function get_images_for_content($content_type, $content_id = null, $content_url = null, $active_only = true) {
        global $wpdb;
        
        // Controlla cache locale
        $cache_key = md5($content_type . '_' . $content_id . '_' . $content_url . '_' . ($active_only ? '1' : '0'));
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        $table_name = $this->db_manager->get_table_name('content_images');
        
        $where_conditions = array('content_type = %s');
        $where_values = array($content_type);
        
        if ($content_id !== null) {
            $where_conditions[] = 'content_id = %d';
            $where_values[] = $content_id;
        }
        
        if ($content_url !== null) {
            $where_conditions[] = 'content_url = %s';
            $where_values[] = $content_url;
        }
        
        if ($active_only) {
            $where_conditions[] = 'is_active = 1';
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY last_scanned DESC";
        $prepared_query = $wpdb->prepare($query, $where_values);
        
        $results = $wpdb->get_results($prepared_query, ARRAY_A);
        
        // Cache del risultato
        $this->cache[$cache_key] = $results;
        
        return $results;
    }
    
    /**
     * Risolve l'attachment ID da un URL immagine
     *
     * @param string $image_url URL dell'immagine
     * @return int|null Attachment ID o null se non trovato
     */
    public function resolve_attachment_id($image_url) {
        global $wpdb;
        
        if (empty($image_url)) {
            return null;
        }
        
        // Controlla prima nell'indice URL
        $url_hash = md5($image_url);
        $table_url_index = $this->db_manager->get_table_name('url_index');
        
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT attachment_id FROM $table_url_index WHERE url_hash = %s",
            $url_hash
        ));
        
        if ($attachment_id) {
            // Aggiorna il contatore di verifica
            $wpdb->update(
                $table_url_index,
                array(
                    'last_verified' => current_time('mysql'),
                    'verification_count' => $wpdb->get_var($wpdb->prepare(
                        "SELECT verification_count FROM $table_url_index WHERE url_hash = %s",
                        $url_hash
                    )) + 1
                ),
                array('url_hash' => $url_hash)
            );
            
            return intval($attachment_id);
        }
        
        // Se non trovato nell'indice, prova con WordPress
        $attachment_id = attachment_url_to_postid($image_url);
        
        if (!$attachment_id) {
            // Prova con query diretta per immagini importate
            $attachment_id = $this->find_attachment_by_url($image_url);
        }
        
        // Aggiorna l'indice con il risultato
        $this->update_url_index($image_url, $attachment_id);
        
        return $attachment_id ? intval($attachment_id) : null;
    }
    
    /**
     * Trova attachment tramite query diretta
     *
     * @param string $image_url URL dell'immagine
     * @return int|false Attachment ID o false se non trovato
     */
    private function find_attachment_by_url($image_url) {
        global $wpdb;
        
        $filename = basename($image_url);
        $filename_no_ext = pathinfo($filename, PATHINFO_FILENAME);
        
        // Query ottimizzata per trovare l'attachment
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_wp_attached_file'
             AND meta_value LIKE %s
             LIMIT 1",
            '%' . $wpdb->esc_like($filename) . '%'
        ));
        
        if (!$attachment_id) {
            $attachment_id = $wpdb->get_var($wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p
                 WHERE p.post_type = 'attachment'
                 AND p.post_mime_type LIKE 'image/%'
                 AND p.post_title LIKE %s
                 LIMIT 1",
                '%' . $wpdb->esc_like($filename_no_ext) . '%'
            ));
        }
        
        return $attachment_id ? intval($attachment_id) : false;
    }
    
    /**
     * Aggiorna l'indice URL
     *
     * @param string $image_url URL dell'immagine
     * @param int|null $attachment_id Attachment ID
     */
    private function update_url_index($image_url, $attachment_id) {
        global $wpdb;
        
        $table_url_index = $this->db_manager->get_table_name('url_index');
        $url_hash = md5($image_url);
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_url_index WHERE url_hash = %s",
            $url_hash
        ));
        
        $data = array(
            'image_url' => $image_url,
            'attachment_id' => $attachment_id,
            'url_hash' => $url_hash,
            'last_verified' => current_time('mysql')
        );
        
        if ($existing) {
            $wpdb->update(
                $table_url_index,
                $data,
                array('url_hash' => $url_hash)
            );
        } else {
            $data['verification_count'] = 1;
            $wpdb->insert($table_url_index, $data);
        }
    }
    
    /**
     * Verifica se esiste già un record per questa immagine
     *
     * @param string $content_type Tipo di contenuto
     * @param int|null $content_id ID del contenuto
     * @param string|null $content_url URL del contenuto
     * @param string $image_url URL dell'immagine
     * @return int|false ID del record esistente o false
     */
    private function get_existing_image_id($content_type, $content_id, $content_url, $image_url) {
        global $wpdb;
        
        $table_name = $this->db_manager->get_table_name('content_images');
        
        $where_conditions = array(
            'content_type = %s',
            'image_url = %s'
        );
        $where_values = array($content_type, $image_url);
        
        if ($content_id !== null) {
            $where_conditions[] = 'content_id = %d';
            $where_values[] = $content_id;
        } else {
            $where_conditions[] = 'content_id IS NULL';
        }
        
        if ($content_url !== null) {
            $where_conditions[] = 'content_url = %s';
            $where_values[] = $content_url;
        } else {
            $where_conditions[] = 'content_url IS NULL';
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        $query = "SELECT id FROM $table_name WHERE $where_clause LIMIT 1";
        
        return $wpdb->get_var($wpdb->prepare($query, $where_values));
    }
    
    /**
     * Valida i dati dell'immagine
     *
     * @param array $image_data Dati dell'immagine
     * @return bool True se validi, false altrimenti
     */
    private function validate_image_data($image_data) {
        if (!is_array($image_data)) {
            return false;
        }
        
        if (empty($image_data['url']) || !filter_var($image_data['url'], FILTER_VALIDATE_URL)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Marca le immagini come non attive per un contenuto
     *
     * @param string $content_type Tipo di contenuto
     * @param int|null $content_id ID del contenuto
     * @param string|null $content_url URL del contenuto
     * @param array $active_image_urls Array di URL delle immagini ancora attive
     * @return int Numero di immagini marcate come non attive
     */
    public function mark_inactive_images($content_type, $content_id, $content_url, $active_image_urls = array()) {
        global $wpdb;
        
        $table_name = $this->db_manager->get_table_name('content_images');
        
        $where_conditions = array('content_type = %s');
        $where_values = array($content_type);
        
        if ($content_id !== null) {
            $where_conditions[] = 'content_id = %d';
            $where_values[] = $content_id;
        }
        
        if ($content_url !== null) {
            $where_conditions[] = 'content_url = %s';
            $where_values[] = $content_url;
        }
        
        if (!empty($active_image_urls)) {
            $placeholders = implode(',', array_fill(0, count($active_image_urls), '%s'));
            $where_conditions[] = "image_url NOT IN ($placeholders)";
            $where_values = array_merge($where_values, $active_image_urls);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "UPDATE $table_name SET is_active = 0, last_scanned = %s WHERE $where_clause";
        $where_values[] = current_time('mysql');
        
        return $wpdb->query($wpdb->prepare($query, $where_values));
    }
    
    /**
     * Ottiene statistiche del registro
     *
     * @return array Statistiche del registro
     */
    public function get_registry_stats() {
        global $wpdb;
        
        $table_name = $this->db_manager->get_table_name('content_images');
        
        $stats = array();
        
        // Totale immagini attive
        $stats['total_active_images'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name WHERE is_active = 1"
        ));
        
        // Immagini con alt text
        $stats['images_with_alt'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name WHERE is_active = 1 AND has_alt_text = 1"
        ));
        
        // Immagini per tipo di contenuto
        $content_types = $wpdb->get_results(
            "SELECT content_type, COUNT(*) as count 
             FROM $table_name 
             WHERE is_active = 1 
             GROUP BY content_type",
            ARRAY_A
        );
        
        $stats['by_content_type'] = array();
        foreach ($content_types as $type) {
            $stats['by_content_type'][$type['content_type']] = intval($type['count']);
        }
        
        // Immagini WordPress vs esterne
        $stats['wp_library_images'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name WHERE is_active = 1 AND attachment_id IS NOT NULL"
        ));
        
        $stats['external_images'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name WHERE is_active = 1 AND attachment_id IS NULL"
        ));
        
        // Coverage percentuale
        $stats['coverage_percentage'] = $stats['total_active_images'] > 0 
            ? round(($stats['images_with_alt'] / $stats['total_active_images']) * 100, 2) 
            : 0;
        
        return $stats;
    }
    
    /**
     * Pulisce la cache locale
     */
    public function clear_cache() {
        $this->cache = array();
    }
    
    /**
     * Rimuove record obsoleti
     *
     * @param int $days Giorni di retention per record non attivi
     * @return int Numero di record rimossi
     */
    public function cleanup_old_records($days = 30) {
        global $wpdb;
        
        $table_name = $this->db_manager->get_table_name('content_images');
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name 
             WHERE is_active = 0 
             AND last_scanned < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
}