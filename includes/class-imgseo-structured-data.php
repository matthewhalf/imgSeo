<?php
/**
 * Classe per la gestione dei dati strutturati JSON-LD per le immagini
 * Versione 2.0 - Integrata con il nuovo sistema di scansione universale
 *
 * @package ImgSEO
 * @since 2.0.0
 */

// Impedisce l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe ImgSEO_Structured_Data
 *
 * Gestisce la generazione e l'inserimento di markup JSON-LD per le immagini
 * Ora utilizza il nuovo sistema di scansione universale per rilevare tutte le immagini
 */
class ImgSEO_Structured_Data {
    
    /**
     * Istanza singleton della classe
     *
     * @var ImgSEO_Structured_Data|null
     */
    private static $instance = null;
    
    /**
     * Cache per gli attachment ID per evitare query ripetute
     *
     * @var array
     */
    private $attachment_cache = array();
    
    /**
     * Cache per i metadati delle immagini
     *
     * @var array
     */
    private $metadata_cache = array();
    
    /**
     * Universal scanner instance
     *
     * @var ImgSEO_Universal_Scanner
     */
    private $universal_scanner;
    
    /**
     * Image registry instance
     *
     * @var ImgSEO_Image_Registry
     */
    private $image_registry;
    
    /**
     * Stats calculator instance
     *
     * @var ImgSEO_Accurate_Stats_Calculator
     */
    private $stats_calculator;
    
    /**
     * Costruttore privato per implementare il pattern singleton
     */
    private function __construct() {
        // Inizializza le dipendenze del nuovo sistema se disponibili
        if (class_exists('ImgSEO_Universal_Scanner')) {
            $this->universal_scanner = ImgSEO_Universal_Scanner::get_instance();
        }
        
        if (class_exists('ImgSEO_Image_Registry')) {
            $this->image_registry = ImgSEO_Image_Registry::get_instance();
        }
        
        if (class_exists('ImgSEO_Accurate_Stats_Calculator')) {
            $this->stats_calculator = ImgSEO_Accurate_Stats_Calculator::get_instance();
        }
        
        $this->init_hooks();
    }
    
    /**
     * Ottiene l'istanza singleton della classe
     *
     * @return ImgSEO_Structured_Data
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Inizializza gli hook di WordPress
     */
    private function init_hooks() {
        // Hook per inserire i dati strutturati nel footer
        add_action('wp_footer', array($this, 'add_structured_data_to_page'));
        
        // Hook per aggiungere JavaScript che analizza il DOM
        add_action('wp_footer', array($this, 'add_dom_analysis_script'), 999);
        
        // Hook AJAX per generare structured data
        add_action('wp_ajax_imgseo_generate_structured_data', array($this, 'ajax_generate_structured_data'));
        add_action('wp_ajax_nopriv_imgseo_generate_structured_data', array($this, 'ajax_generate_structured_data'));
    }
    
    /**
     * Genera il markup JSON-LD per un'immagine con cache ottimizzata
     *
     * @param int $attachment_id ID dell'allegato
     * @return array|null Dati strutturati in formato array o null se non valido
     */
    public function generate_image_schema($attachment_id) {
        // Verifica che l'ID sia valido
        if (!$attachment_id || !wp_attachment_is_image($attachment_id)) {
            return null;
        }
        
        // Cache per i metadati dell'immagine
        if (isset($this->metadata_cache[$attachment_id])) {
            $cached_data = $this->metadata_cache[$attachment_id];
            if ($cached_data === false) {
                return null; // Cache negativa
            }
            return $this->build_schema_from_cached_data($cached_data, $attachment_id);
        }
        
        // Ottieni i metadati dell'immagine in modo ottimizzato
        $image_data = $this->get_image_data_optimized($attachment_id);
        
        // Cache del risultato (anche se null per evitare query ripetute)
        $this->metadata_cache[$attachment_id] = $image_data;
        
        // Verifica che i dati essenziali siano disponibili
        if (!$image_data || !$image_data['url'] || !$image_data['title']) {
            return null;
        }
        
        
        return $this->build_schema_from_cached_data($image_data, $attachment_id);
    }
    
    /**
     * Ottieni i dati dell'immagine in modo ottimizzato
     *
     * @param int $attachment_id
     * @return array|false
     */
    private function get_image_data_optimized($attachment_id) {
        // Ottieni tutti i dati necessari con il minor numero di query possibili
        $post = get_post($attachment_id);
        if (!$post || $post->post_type !== 'attachment') {
            return false;
        }
        
        $image_url = wp_get_attachment_url($attachment_id);
        if (!$image_url) {
            return false;
        }
        
        // Ottieni metadati in batch
        $image_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $image_metadata = wp_get_attachment_metadata($attachment_id);
        
        return array(
            'url' => $image_url,
            'title' => $post->post_title,
            'alt' => $image_alt,
            'metadata' => $image_metadata,
            'upload_date' => $post->post_date,
            'author_id' => $post->post_author
        );
    }
    
    /**
     * Costruisci lo schema dai dati cached
     *
     * @param array $image_data
     * @param int $attachment_id
     * @return array
     */
    private function build_schema_from_cached_data($image_data, $attachment_id) {
        // Crea lo schema di base
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'ImageObject',
            'contentUrl' => $image_data['url'],
            'name' => $image_data['title']
        );
        
        // Aggiungi la descrizione se disponibile
        if (!empty($image_data['alt'])) {
            $schema['description'] = $image_data['alt'];
        }
        
        // Aggiungi dimensioni se disponibili
        if (!empty($image_data['metadata']['width'])) {
            $schema['width'] = intval($image_data['metadata']['width']);
        }
        
        if (!empty($image_data['metadata']['height'])) {
            $schema['height'] = intval($image_data['metadata']['height']);
        }
        
        // Aggiungi data di caricamento
        if (!empty($image_data['upload_date'])) {
            $schema['uploadDate'] = date('c', strtotime($image_data['upload_date']));
        }
        
        // Ottieni URL della miniatura se abilitato e disponibile
        if ($this->should_include_thumbnails()) {
            $thumbnail = wp_get_attachment_image_src($attachment_id, 'thumbnail');
            if ($thumbnail && !empty($thumbnail[0])) {
                $schema['thumbnailUrl'] = $thumbnail[0];
            }
        }
        
        // Aggiungi informazioni sull'autore se abilitato e disponibili
        if ($this->should_include_author() && !empty($image_data['author_id'])) {
            $author_name = get_the_author_meta('display_name', $image_data['author_id']);
            if (!empty($author_name)) {
                $schema['author'] = array(
                    '@type' => 'Person',
                    'name' => $author_name
                );
            }
        }
        
        // Applica filtro per permettere personalizzazioni
        $schema = apply_filters('imgseo_structured_data_schema', $schema, $attachment_id);
        
        return $schema;
    }
    
    /**
     * Inserisce il markup JSON-LD nel footer della pagina
     * Genera JSON-LD per tutte le immagini trovate nella pagina
     */
    public function add_structured_data_to_page() {
        // Verifica se la funzionalità è abilitata
        if (!$this->is_structured_data_enabled()) {
            return;
        }
        
        // Verifica se siamo in una pagina che dovrebbe avere structured data
        if (!$this->should_add_structured_data()) {
            return;
        }
        
        // Ottieni tutte le immagini della pagina corrente
        $images = $this->get_images_in_current_page();
        
        if (empty($images)) {
            return;
        }
        
        // Genera JSON-LD per ogni immagine
        $json_ld_schemas = array();
        
        foreach ($images as $image) {
            if (is_array($image) && !empty($image['attachment_id'])) {
                // Nuovo formato con dati completi
                $schema = $this->generate_image_schema_from_data($image);
            } else {
                // Formato legacy (solo attachment ID)
                $attachment_id = is_array($image) ? $image['attachment_id'] : $image;
                $schema = $this->generate_image_schema($attachment_id);
            }
            
            if ($schema) {
                $json_ld_schemas[] = $schema;
            }
        }
        
        if (!empty($json_ld_schemas)) {
            echo '<script type="application/ld+json">' . "\n";
            echo wp_json_encode($json_ld_schemas, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            echo "\n" . '</script>' . "\n";
        }
    }
    
    /**
     * Genera schema JSON-LD da dati immagine completi
     *
     * @param array $image_data Dati completi dell'immagine
     * @return array|null Schema JSON-LD o null se non valido
     */
    private function generate_image_schema_from_data($image_data) {
        if (empty($image_data['image_url'])) {
            return null;
        }
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'ImageObject',
            'url' => $image_data['image_url'],
            'contentUrl' => $image_data['image_url']
        );
        
        // Aggiungi alt text se disponibile
        if (!empty($image_data['alt_text'])) {
            $schema['name'] = $image_data['alt_text'];
            $schema['alternateName'] = $image_data['alt_text'];
        }
        
        // Aggiungi titolo se disponibile
        if (!empty($image_data['image_title'])) {
            $schema['headline'] = $image_data['image_title'];
            if (empty($schema['name'])) {
                $schema['name'] = $image_data['image_title'];
            }
        }
        
        // Aggiungi contesto se disponibile
        if (!empty($image_data['image_context'])) {
            $schema['description'] = 'Image from ' . $image_data['image_context'];
        }
        
        // Se è un'immagine WordPress, aggiungi dati aggiuntivi
        if (!empty($image_data['attachment_id'])) {
            $attachment_id = $image_data['attachment_id'];
            
            // Ottieni metadati
            $metadata = wp_get_attachment_metadata($attachment_id);
            if ($metadata) {
                if (!empty($metadata['width'])) {
                    $schema['width'] = intval($metadata['width']);
                }
                if (!empty($metadata['height'])) {
                    $schema['height'] = intval($metadata['height']);
                }
            }
            
            // Ottieni data di caricamento
            $post = get_post($attachment_id);
            if ($post) {
                $schema['uploadDate'] = date('c', strtotime($post->post_date));
            }
            
            // Ottieni descrizione
            $description = get_post_field('post_content', $attachment_id);
            if (!empty($description)) {
                $schema['description'] = $description;
            }
        }
        
        return $schema;
    }
    
    /**
     * Check if structured data should be added to current page
     *
     * @return bool
     */
    private function should_add_structured_data() {
        // Aggiungi structured data a:
        // - Pagine singole (post, page, custom post types)
        // - Homepage
        // - Archivi (category, tag, custom taxonomy, author, date)
        // - Pagine di ricerca
        // - Feed RSS (opzionale)
        
        if (is_singular()) {
            return true; // Tutti i post types singoli
        }
        
        if (is_home() || is_front_page()) {
            return true; // Homepage
        }
        
        if (is_archive()) {
            return true; // Tutti gli archivi
        }
        
        if (is_search()) {
            return true; // Pagine di ricerca
        }
        
        // Escludi pagine admin, login, 404, etc.
        if (is_admin() || is_404() || is_attachment()) {
            return false;
        }
        
        return true; // Default: aggiungi structured data
    }
    
    /**
     * Ottiene gli ID degli allegati delle immagini nella pagina corrente
     * Ora utilizza il nuovo sistema di scansione universale
     *
     * @return array Array di ID degli allegati
     */
    private function get_images_in_current_page() {
        global $post, $wp_query;
        
        $attachment_ids = array();
        
        // Determina il tipo di pagina e ottieni le immagini dal registry
        if (is_singular()) {
            // Pagina singola (post, page, custom post type)
            $images = $this->image_registry->get_images_for_content(
                get_post_type($post->ID),
                $post->ID,
                null,
                true // solo immagini attive
            );
            
            // Se non ci sono immagini nel registry, esegui una scansione rapida
            if (empty($images)) {
                $this->universal_scanner->scan_single_post($post->ID);
                $images = $this->image_registry->get_images_for_content(
                    get_post_type($post->ID),
                    $post->ID,
                    null,
                    true
                );
            }
            
        } elseif (is_home() || is_front_page()) {
            // Homepage
            $images = $this->image_registry->get_images_for_content(
                'homepage',
                null,
                home_url(),
                true
            );
            
            // Se non ci sono immagini per la homepage, esegui una scansione rapida
            if (empty($images) && $this->universal_scanner) {
                $this->universal_scanner->scan_homepage();
                $images = $this->image_registry->get_images_for_content(
                    'homepage',
                    null,
                    home_url(),
                    true
                );
            }
            
            // Aggiungi immagini dei post in homepage
            if ($wp_query && $wp_query->have_posts()) {
                foreach ($wp_query->posts as $p) {
                    $post_images = $this->image_registry->get_images_for_content(
                        $p->post_type,
                        $p->ID,
                        null,
                        true
                    );
                    
                    // Se non ci sono immagini per questo post, scansiona
                    if (empty($post_images) && $this->universal_scanner) {
                        $this->universal_scanner->scan_single_post($p->ID);
                        $post_images = $this->image_registry->get_images_for_content(
                            $p->post_type,
                            $p->ID,
                            null,
                            true
                        );
                    }
                    
                    $images = array_merge($images, $post_images);
                }
            }
            
        } elseif (is_archive()) {
            // Pagina archivio
            $archive_url = get_pagenum_link();
            $images = $this->image_registry->get_images_for_content(
                'archive',
                null,
                $archive_url,
                true
            );
            
            // Aggiungi immagini dei post nell'archivio
            if ($wp_query && $wp_query->have_posts()) {
                foreach ($wp_query->posts as $p) {
                    $post_images = $this->image_registry->get_images_for_content(
                        $p->post_type,
                        $p->ID,
                        null,
                        true
                    );
                    $images = array_merge($images, $post_images);
                }
            }
            
        } else {
            // Fallback al metodo precedente per compatibilità
            return $this->get_images_in_current_page_legacy();
        }
        
        // Estrai gli attachment ID dalle immagini trovate
        foreach ($images as $image) {
            if (!empty($image['attachment_id'])) {
                $attachment_ids[] = intval($image['attachment_id']);
            }
        }
        
        // Rimuovi duplicati
        return array_unique(array_filter($attachment_ids));
    }
    
    /**
     * Metodo legacy per compatibilità con il sistema precedente
     *
     * @return array Array di ID degli allegati
     */
    private function get_images_in_current_page_legacy() {
        $attachment_ids = array();
        
        // Usa output buffering per catturare l'HTML renderizzato della pagina
        $rendered_content = $this->get_rendered_page_content();
        
        if (!empty($rendered_content)) {
            // Estrai le immagini dall'HTML renderizzato
            $attachment_ids = $this->extract_images_from_content($rendered_content);
        }
        
        // Se non troviamo immagini nell'HTML renderizzato, proviamo con il contenuto del database
        if (empty($attachment_ids)) {
            $db_content = $this->get_database_content();
            if (!empty($db_content)) {
                $attachment_ids = $this->extract_images_from_content($db_content);
            }
        }
        
        // Aggiungi sempre le immagini in evidenza per pagine singole e custom post
        if (is_singular()) {
            $featured_image_id = get_post_thumbnail_id();
            if ($featured_image_id && !in_array($featured_image_id, $attachment_ids)) {
                $attachment_ids[] = $featured_image_id;
            }
        }
        
        // Per homepage e archivi, aggiungi le immagini in evidenza dei post visibili
        if (is_home() || is_front_page() || is_archive() || is_search()) {
            $featured_ids = $this->get_featured_images_from_query();
            $attachment_ids = array_merge($attachment_ids, $featured_ids);
        }
        
        // Rimuovi duplicati e restituisci
        return array_unique(array_filter($attachment_ids));
    }
    
    /**
     * Get rendered page content by capturing actual HTML output
     *
     * @return string
     */
    private function get_rendered_page_content() {
        // Questo metodo cerca di catturare l'HTML effettivamente renderizzato
        // Tuttavia, nel contesto di wp_footer, il contenuto è già stato renderizzato
        // Quindi dobbiamo usare un approccio diverso
        
        global $wp_query, $post;
        $content = '';
        
        // Per pagine singole (post, page, custom post types)
        if (is_singular()) {
            if ($post) {
                // Ottieni il contenuto processato con tutti i filtri
                $content = apply_filters('the_content', $post->post_content);
                
                // Aggiungi anche eventuali shortcode e widget
                $content = do_shortcode($content);
            }
        }
        // Per homepage, archivi, etc.
        else if ($wp_query && $wp_query->have_posts()) {
            $original_post = $GLOBALS['post'] ?? null;
            
            // Salva lo stato corrente della query
            $wp_query->rewind_posts();
            
            while ($wp_query->have_posts()) {
                $wp_query->the_post();
                $post_content = apply_filters('the_content', get_the_content());
                $post_content = do_shortcode($post_content);
                $content .= $post_content;
            }
            
            // Ripristina lo stato originale
            if ($original_post) {
                $GLOBALS['post'] = $original_post;
            }
            wp_reset_postdata();
        }
        
        return $content;
    }
    
    /**
     * Get content from database as fallback
     *
     * @return string
     */
    private function get_database_content() {
        global $wp_query, $post;
        $content = '';
        
        // Per pagine singole
        if (is_singular() && $post) {
            $content = $post->post_content;
        }
        // Per homepage e archivi
        else if ($wp_query && $wp_query->have_posts()) {
            foreach ($wp_query->posts as $p) {
                if (isset($p->post_content)) {
                    $content .= $p->post_content;
                }
            }
        }
        
        return $content;
    }
    
    /**
     * Get featured images from current query
     *
     * @return array
     */
    private function get_featured_images_from_query() {
        global $wp_query;
        $featured_ids = array();
        
        if (!$wp_query || !$wp_query->have_posts()) {
            return $featured_ids;
        }
        
        foreach ($wp_query->posts as $p) {
            if (has_post_thumbnail($p->ID)) {
                $thumbnail_id = get_post_thumbnail_id($p->ID);
                if ($thumbnail_id) {
                    $featured_ids[] = $thumbnail_id;
                }
            }
        }
        
        return $featured_ids;
    }
    
    /**
     * Fallback method to get page content from DOM
     *
     * @return string
     */
    private function get_page_content_from_dom() {
        // Questo è un fallback - in realtà dovremmo avere il contenuto dai metodi precedenti
        // Ma se per qualche motivo non funziona, proviamo a ottenere il contenuto globalmente
        global $post, $posts;
        
        $content = '';
        
        if ($post) {
            $content .= $post->post_content;
        }
        
        if (is_array($posts)) {
            foreach ($posts as $p) {
                if (isset($p->post_content)) {
                    $content .= $p->post_content;
                }
            }
        }
        
        return $content;
    }
    
    /**
     * Extract image attachment IDs from content with performance optimization
     *
     * @param string $content
     * @return array
     */
    private function extract_images_from_content($content) {
        $attachment_ids = array();
        
        if (empty($content)) {
            return $attachment_ids;
        }
        
        // Cache key per questo contenuto
        $content_hash = md5($content);
        if (isset($this->attachment_cache[$content_hash])) {
            return $this->attachment_cache[$content_hash];
        }
        
        // 1. Pattern ottimizzato per trovare immagini con classe wp-image-ID (più veloce)
        $pattern_wp_image = '/<img[^>]+wp-image-([0-9]+)[^>]*>/i';
        if (preg_match_all($pattern_wp_image, $content, $matches)) {
            foreach ($matches[1] as $attachment_id) {
                $attachment_ids[] = intval($attachment_id);
            }
        }
        
        // 2. Pattern ottimizzato per immagini nella media library
        $upload_dir = wp_upload_dir();
        $upload_url = $upload_dir['baseurl'];
        $pattern_uploads = '/<img[^>]+src=["\'](' . preg_quote($upload_url, '/') . '[^"\']*)["\'][^>]*>/i';
        
        if (preg_match_all($pattern_uploads, $content, $matches)) {
            // Batch processing per migliorare le performance
            $urls_to_process = array_slice($matches[1], 0, 20); // Limita a 20 per performance
            
            foreach ($urls_to_process as $image_url) {
                $attachment_id = $this->get_attachment_id_optimized($image_url);
                if ($attachment_id && !in_array($attachment_id, $attachment_ids)) {
                    $attachment_ids[] = $attachment_id;
                }
            }
        }
        
        // 3. Pattern per immagini esterne che potrebbero essere state importate
        if (count($attachment_ids) < 10) { // Solo se non abbiamo già molte immagini
            $pattern_external = '/<img[^>]+src=["\']([^"\']+\.(jpg|jpeg|png|gif|webp))["\'][^>]*>/i';
            if (preg_match_all($pattern_external, $content, $matches)) {
                $external_urls = array_slice($matches[1], 0, 10); // Limita per performance
                
                foreach ($external_urls as $image_url) {
                    $attachment_id = $this->get_attachment_id_optimized($image_url);
                    if ($attachment_id && !in_array($attachment_id, $attachment_ids)) {
                        $attachment_ids[] = $attachment_id;
                    }
                }
            }
        }
        
        // 4. Fallback ottimizzato: solo se necessario e con limite
        if (empty($attachment_ids)) {
            $attachment_ids = $this->get_fallback_images();
        }
        
        // Cache del risultato per 5 minuti
        $this->attachment_cache[$content_hash] = $attachment_ids;
        
        return $attachment_ids;
    }
    
    /**
     * Ottimizzazione per ottenere attachment ID con cache
     *
     * @param string $image_url
     * @return int|false
     */
    private function get_attachment_id_optimized($image_url) {
        // Cache per URL già processati
        if (isset($this->attachment_cache[$image_url])) {
            return $this->attachment_cache[$image_url];
        }
        
        // Prova prima con attachment_url_to_postid (più veloce per immagini WP)
        $attachment_id = attachment_url_to_postid($image_url);
        
        // Se non trova, prova con query diretta per immagini importate
        if (!$attachment_id) {
            $attachment_id = $this->find_attachment_by_url($image_url);
        }
        
        // Cache del risultato
        $this->attachment_cache[$image_url] = $attachment_id;
        
        return $attachment_id;
    }
    
    /**
     * Trova attachment tramite query diretta (per immagini importate)
     *
     * @param string $image_url
     * @return int|false
     */
    private function find_attachment_by_url($image_url) {
        global $wpdb;
        
        // Estrai il nome del file dall'URL
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
        
        // Se non trova per nome file completo, prova con nome senza estensione
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
     * Ottieni immagini di fallback in modo ottimizzato
     *
     * @return array
     */
    private function get_fallback_images() {
        // Cache per le immagini di fallback
        static $fallback_cache = null;
        static $cache_time = 0;
        
        // Cache per 10 minuti
        if ($fallback_cache !== null && (time() - $cache_time) < 600) {
            return $fallback_cache;
        }
        
        $recent_images = get_posts(array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => 3, // Ridotto per performance
            'post_status' => 'inherit',
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids' // Solo gli ID per performance
        ));
        
        $fallback_cache = $recent_images;
        $cache_time = time();
        
        return $recent_images;
    }
    
    /**
     * Verifica se i dati strutturati sono abilitati nelle impostazioni
     *
     * @return bool
     */
    private function is_structured_data_enabled() {
        return (bool) get_option('imgseo_enable_structured_data', 1);
    }
    
    /**
     * Check if thumbnails should be included in structured data
     *
     * @return bool
     */
    private function should_include_thumbnails() {
        return (bool) get_option('imgseo_structured_data_include_thumbnails', 1);
    }
    
    /**
     * Check if author information should be included in structured data
     *
     * @return bool
     */
    private function should_include_author() {
        return (bool) get_option('imgseo_structured_data_include_author', 1);
    }
    
    
    /**
     * Ottiene le statistiche sui dati strutturati generati
     * Ora utilizza il nuovo sistema di calcolo accurato
     *
     * @return array
     */
    public function get_structured_data_stats() {
        // Usa il nuovo sistema di statistiche accurate se disponibile
        if ($this->stats_calculator) {
            return $this->stats_calculator->get_quick_stats();
        }
        
        // Fallback al sistema precedente
        return $this->get_legacy_stats();
    }
    
    /**
     * Ottiene statistiche complete e dettagliate
     *
     * @param bool $force_refresh Forza il ricalcolo
     * @return array Statistiche complete
     */
    public function get_detailed_stats($force_refresh = false) {
        if ($this->stats_calculator) {
            return $this->stats_calculator->calculate_site_stats($force_refresh);
        }
        
        // Fallback al sistema precedente
        return $this->get_legacy_stats();
    }
    
    /**
     * Ottiene statistiche per un tipo di contenuto specifico
     *
     * @param string $content_type Tipo di contenuto
     * @return array Statistiche per il tipo di contenuto
     */
    public function get_content_type_stats($content_type) {
        if ($this->stats_calculator) {
            return $this->stats_calculator->get_content_type_stats($content_type);
        }
        
        return array();
    }
    
    /**
     * Ottiene le immagini problematiche
     *
     * @param int $limit Numero massimo di risultati
     * @return array Immagini problematiche
     */
    public function get_problematic_images($limit = 20) {
        if ($this->stats_calculator) {
            return $this->stats_calculator->get_problematic_images($limit);
        }
        
        return array();
    }
    
    /**
     * Metodo legacy per le statistiche (compatibilità con nuovo formato JSON-LD)
     *
     * @return array
     */
    private function get_legacy_stats() {
        global $wpdb;
        
        // Conta le immagini con testo alternativo
        $images_with_alt = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_wp_attachment_image_alt'
             AND pm.meta_value != ''
             AND p.post_type = 'attachment'
             AND p.post_mime_type LIKE 'image/%'"
        );
        
        // Conta tutte le immagini
        $total_images = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'attachment'
             AND post_mime_type LIKE 'image/%'"
        );
        
        $images_without_alt = $total_images - $images_with_alt;
        
        return array(
            'total_images_scanned' => intval($total_images),
            'jsonld_complete' => intval($images_with_alt),
            'jsonld_partial' => intval($images_without_alt),
            'jsonld_total' => intval($total_images),
            'jsonld_quality_percentage' => $total_images > 0 ? round(($images_with_alt / $total_images) * 100, 2) : 0,
            'wp_library_images' => intval($total_images),
            'external_images' => 0
        );
    }
    
    /**
     * Valida uno schema JSON-LD
     *
     * @param array $schema
     * @return bool
     */
    public function validate_schema($schema) {
        // Verifica i campi obbligatori
        $required_fields = array('@context', '@type', 'contentUrl', 'name');
        
        foreach ($required_fields as $field) {
            if (!isset($schema[$field]) || empty($schema[$field])) {
                return false;
            }
        }
        
        // Verifica che il tipo sia corretto
        if ($schema['@type'] !== 'ImageObject') {
            return false;
        }
        
        // Verifica che il context sia corretto
        if ($schema['@context'] !== 'https://schema.org') {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get maximum images per page setting
     *
     * @return int
     */
    private function get_max_images_per_page() {
        return max(1, min(50, intval(get_option('imgseo_structured_data_max_images', 10))));
    }
    
    /**
     * Aggiunge JavaScript per analizzare il DOM e generare structured data
     */
    public function add_dom_analysis_script() {
        // Verifica se la funzionalità è abilitata
        if (!$this->is_structured_data_enabled() || !$this->should_add_structured_data()) {
            return;
        }
        
        // Ottieni le impostazioni per JavaScript
        $settings = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('imgseo_structured_data'),
            'max_images' => $this->get_max_images_per_page(),
            'include_thumbnails' => $this->should_include_thumbnails(),
            'include_author' => $this->should_include_author(),
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        );
        
        ?>
        <script type="text/javascript">
        (function() {
            // Configurazione
            var imgSeoConfig = <?php echo wp_json_encode($settings); ?>;
            
            // Funzione per analizzare il DOM e trovare tutte le immagini visibili
            function findVisibleImages() {
                var images = [];
                var imgElements = document.querySelectorAll('img');
                
                if (imgSeoConfig.debug) {
                    console.log('ImgSEO: Found ' + imgElements.length + ' img elements');
                }
                
                imgElements.forEach(function(img) {
                    // Verifica se l'immagine è visibile
                    if (img.offsetWidth > 0 && img.offsetHeight > 0 &&
                        getComputedStyle(img).display !== 'none' &&
                        getComputedStyle(img).visibility !== 'hidden') {
                        
                        var imageData = {
                            src: img.src,
                            alt: img.alt || '',
                            title: img.title || '',
                            width: img.naturalWidth || img.width,
                            height: img.naturalHeight || img.height,
                            className: img.className || '',
                            id: img.id || ''
                        };
                        
                        // Cerca attachment ID dalla classe wp-image-*
                        var wpImageMatch = img.className.match(/wp-image-(\d+)/);
                        if (wpImageMatch) {
                            imageData.attachment_id = parseInt(wpImageMatch[1]);
                        }
                        
                        images.push(imageData);
                    }
                });
                
                // Limita il numero di immagini
                if (images.length > imgSeoConfig.max_images) {
                    images = images.slice(0, imgSeoConfig.max_images);
                }
                
                if (imgSeoConfig.debug) {
                    console.log('ImgSEO: Found ' + images.length + ' visible images:', images);
                }
                
                return images;
            }
            
            // Funzione per inviare i dati al server e generare structured data
            function generateStructuredData(images) {
                if (images.length === 0) {
                    if (imgSeoConfig.debug) {
                        console.log('ImgSEO: No images to process');
                    }
                    return;
                }
                
                // Invia richiesta AJAX
                var xhr = new XMLHttpRequest();
                xhr.open('POST', imgSeoConfig.ajax_url, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success && response.data.structured_data) {
                                // Inserisci il JSON-LD nella pagina
                                var script = document.createElement('script');
                                script.type = 'application/ld+json';
                                script.textContent = JSON.stringify(response.data.structured_data);
                                document.head.appendChild(script);
                                
                                if (imgSeoConfig.debug) {
                                    console.log('ImgSEO: Structured data added:', response.data.structured_data);
                                }
                            }
                        } catch (e) {
                            if (imgSeoConfig.debug) {
                                console.error('ImgSEO: Error parsing response:', e);
                            }
                        }
                    }
                };
                
                var data = 'action=imgseo_generate_structured_data' +
                          '&nonce=' + encodeURIComponent(imgSeoConfig.nonce) +
                          '&images=' + encodeURIComponent(JSON.stringify(images));
                
                xhr.send(data);
            }
            
            // Esegui l'analisi quando il DOM è completamente caricato
            function init() {
                var images = findVisibleImages();
                generateStructuredData(images);
            }
            
            // Avvia l'analisi
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                // DOM già caricato
                setTimeout(init, 100); // Piccolo delay per assicurarsi che tutto sia renderizzato
            }
        })();
        </script>
        <?php
    }
    
    /**
     * Gestore AJAX per generare structured data dalle immagini del DOM
     * Ora integrato con il nuovo sistema di scansione
     */
    public function ajax_generate_structured_data() {
        // Verifica nonce per sicurezza
        if (!wp_verify_nonce($_POST['nonce'], 'imgseo_structured_data')) {
            wp_die('Security check failed');
        }
        
        // Verifica se la funzionalità è abilitata
        if (!$this->is_structured_data_enabled()) {
            wp_send_json_error('Structured data is disabled');
            return;
        }
        
        // Ottieni i dati delle immagini da JavaScript
        $images_data = json_decode(stripslashes($_POST['images']), true);
        
        if (empty($images_data) || !is_array($images_data)) {
            wp_send_json_error('No valid image data received');
            return;
        }
        
        $schemas = array();
        $processed_ids = array();
        $processed_urls = array();
        
        foreach ($images_data as $image_data) {
            $attachment_id = null;
            
            // 1. Prova con attachment_id se presente (da classe wp-image-*)
            if (!empty($image_data['attachment_id'])) {
                $attachment_id = intval($image_data['attachment_id']);
            }
            
            // 2. Se non ha attachment_id, prova a trovarlo dall'URL usando il registry
            if (!$attachment_id && !empty($image_data['src'])) {
                if ($this->image_registry) {
                    $attachment_id = $this->image_registry->resolve_attachment_id($image_data['src']);
                } else {
                    // Fallback al metodo precedente
                    $attachment_id = $this->get_attachment_id_from_url($image_data['src']);
                }
            }
            
            // 3. Evita duplicati per URL
            if (in_array($image_data['src'], $processed_urls)) {
                continue;
            }
            $processed_urls[] = $image_data['src'];
            
            // 4. Se trovato attachment_id, genera schema WordPress
            if ($attachment_id) {
                // Evita duplicati per attachment ID
                if (in_array($attachment_id, $processed_ids)) {
                    continue;
                }
                
                $schema = $this->generate_image_schema($attachment_id);
                if ($schema) {
                    $schemas[] = $schema;
                    $processed_ids[] = $attachment_id;
                }
            } else {
                // 5. Crea schema generico per immagini esterne
                $schema = $this->create_generic_image_schema($image_data);
                if ($schema) {
                    $schemas[] = $schema;
                }
            }
        }
        
        if (!empty($schemas)) {
            // Se c'è solo uno schema, restituiscilo direttamente
            // Se ce ne sono più di uno, restituiscili come array
            $structured_data = count($schemas) === 1 ? $schemas[0] : $schemas;
            
            wp_send_json_success(array(
                'structured_data' => $structured_data,
                'count' => count($schemas),
                'processed_images' => count($processed_urls),
                'wp_library_images' => count($processed_ids),
                'external_images' => count($schemas) - count($processed_ids)
            ));
        } else {
            wp_send_json_error('No valid schemas generated');
        }
    }
    
    /**
     * Ottieni attachment ID da URL immagine
     *
     * @param string $image_url
     * @return int|false
     */
    private function get_attachment_id_from_url($image_url) {
        // Usa la cache se disponibile
        if (isset($this->attachment_cache[$image_url])) {
            return $this->attachment_cache[$image_url];
        }
        
        // Prova con attachment_url_to_postid
        $attachment_id = attachment_url_to_postid($image_url);
        
        // Se non trova, prova con query diretta
        if (!$attachment_id) {
            $attachment_id = $this->find_attachment_by_url($image_url);
        }
        
        // Cache del risultato
        $this->attachment_cache[$image_url] = $attachment_id;
        
        return $attachment_id;
    }
    
    /**
     * Crea schema generico per immagini non presenti nella media library
     *
     * @param array $image_data
     * @return array|null
     */
    private function create_generic_image_schema($image_data) {
        if (empty($image_data['src'])) {
            return null;
        }
        
        // Schema di base per immagine generica
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'ImageObject',
            'contentUrl' => $image_data['src']
        );
        
        // Aggiungi nome se disponibile
        if (!empty($image_data['alt'])) {
            $schema['name'] = $image_data['alt'];
        } elseif (!empty($image_data['title'])) {
            $schema['name'] = $image_data['title'];
        } else {
            // Usa il nome del file come fallback
            $schema['name'] = basename($image_data['src']);
        }
        
        // Aggiungi descrizione se disponibile
        if (!empty($image_data['alt']) && $image_data['alt'] !== $schema['name']) {
            $schema['description'] = $image_data['alt'];
        }
        
        // Aggiungi dimensioni se disponibili
        if (!empty($image_data['width']) && $image_data['width'] > 0) {
            $schema['width'] = intval($image_data['width']);
        }
        
        if (!empty($image_data['height']) && $image_data['height'] > 0) {
            $schema['height'] = intval($image_data['height']);
        }
        
        // Applica filtro per permettere personalizzazioni
        $schema = apply_filters('imgseo_generic_structured_data_schema', $schema, $image_data);
        
        return $schema;
    }
}