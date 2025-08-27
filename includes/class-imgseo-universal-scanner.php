<?php
/**
 * Classe per la scansione universale delle immagini in WordPress
 * Rileva tutte le immagini presenti in posts, pages, custom post types, widget, archivi, etc.
 *
 * @package ImgSEO
 * @since 2.0.0
 */

// Impedisce l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe ImgSEO_Universal_Scanner
 * 
 * Gestisce la scansione completa del sito per rilevare tutte le immagini
 */
class ImgSEO_Universal_Scanner {
    
    /**
     * Istanza singleton della classe
     *
     * @var ImgSEO_Universal_Scanner|null
     */
    private static $instance = null;
    
    /**
     * Registry delle immagini
     *
     * @var ImgSEO_Image_Registry
     */
    private $image_registry;
    
    /**
     * Database manager
     *
     * @var ImgSEO_Database_Manager
     */
    private $db_manager;
    
    /**
     * Page builder detector
     *
     * @var ImgSEO_Page_Builder_Detector
     */
    private $page_builder_detector;
    
    /**
     * Configurazioni di scansione
     *
     * @var array
     */
    private $scan_config;
    
    /**
     * Statistiche della scansione corrente
     *
     * @var array
     */
    private $scan_stats;
    
    /**
     * Ottiene l'istanza singleton della classe
     *
     * @return ImgSEO_Universal_Scanner
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
        $this->image_registry = ImgSEO_Image_Registry::get_instance();
        $this->db_manager = ImgSEO_Database_Manager::get_instance();
        $this->page_builder_detector = ImgSEO_Page_Builder_Detector::get_instance();
        
        $this->init_scan_config();
        $this->init_hooks();
    }
    
    /**
     * Inizializza la configurazione di scansione
     */
    private function init_scan_config() {
        $this->scan_config = array(
            'batch_size' => get_option('imgseo_scan_batch_size', 50),
            'timeout' => get_option('imgseo_scan_timeout', 300),
            'scan_external_images' => get_option('imgseo_scan_external_images', 1),
            'scan_background_images' => get_option('imgseo_scan_background_images', 1),
            'scan_page_builders' => get_option('imgseo_scan_page_builders', 1),
            'max_memory_usage' => '256M'
        );
    }
    
    /**
     * Inizializza gli hook di WordPress
     */
    private function init_hooks() {
        // Hook per scansione automatica quando viene salvato un post
        add_action('save_post', array($this, 'scan_single_post'), 10, 1);
        
        // Hook AJAX per scansione manuale
        add_action('wp_ajax_imgseo_scan_content', array($this, 'ajax_scan_content'));
        
        // Hook per scansione programmata
        add_action('imgseo_scheduled_scan', array($this, 'run_scheduled_scan'));
    }
    
    /**
     * Avvia una scansione completa del sito
     *
     * @param array $options Opzioni di scansione
     * @return array Risultato della scansione
     */
    public function scan_entire_site($options = array()) {
        $this->init_scan_stats();
        
        // Aumenta il limite di memoria e tempo
        $this->set_scan_limits();
        
        try {
            // Scansiona tutti i tipi di contenuto
            $this->scan_all_posts();
            $this->scan_all_pages();
            $this->scan_custom_post_types();
            $this->scan_widgets();
            $this->scan_theme_locations();
            
            // Marca la scansione come completata
            $this->mark_scan_completed();
            
            return array(
                'success' => true,
                'stats' => $this->scan_stats,
                'message' => 'Scansione completata con successo'
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'stats' => $this->scan_stats
            );
        }
    }
    
    /**
     * Scansiona tutti i post
     */
    private function scan_all_posts() {
        $this->scan_content_type('post', array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $this->scan_config['batch_size'],
            'fields' => 'ids'
        ));
    }
    
    /**
     * Scansiona tutte le pagine
     */
    private function scan_all_pages() {
        $this->scan_content_type('page', array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => $this->scan_config['batch_size'],
            'fields' => 'ids'
        ));
    }
    
    /**
     * Scansiona tutti i custom post types
     */
    private function scan_custom_post_types() {
        $custom_post_types = get_post_types(array(
            'public' => true,
            '_builtin' => false
        ));
        
        foreach ($custom_post_types as $post_type) {
            $this->scan_content_type($post_type, array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => $this->scan_config['batch_size'],
                'fields' => 'ids'
            ));
        }
    }
    
    /**
     * Scansiona un tipo di contenuto specifico
     *
     * @param string $content_type Tipo di contenuto
     * @param array $query_args Argomenti per la query
     */
    private function scan_content_type($content_type, $query_args) {
        $paged = 1;
        
        do {
            $query_args['paged'] = $paged;
            $query = new WP_Query($query_args);
            
            if ($query->have_posts()) {
                foreach ($query->posts as $post_id) {
                    $this->scan_single_post($post_id);
                    
                    // Controlla memoria e tempo
                    if ($this->should_pause_scan()) {
                        break 2;
                    }
                }
            }
            
            $paged++;
            
            // Libera memoria
            wp_reset_postdata();
            
        } while ($query->have_posts() && $paged <= $query->max_num_pages);
    }
    
    /**
     * Scansiona la homepage
     *
     * @return array Risultato della scansione
     */
    public function scan_homepage() {
        $this->init_scan_stats();
        
        try {
            // Ottieni la homepage
            $homepage_url = home_url('/');
            
            // Scansiona la homepage come contenuto speciale
            $images = $this->scan_homepage_content();
            
            // Registra le immagini trovate
            foreach ($images as $image_data) {
                $this->image_registry->register_image(
                    'homepage',
                    null,
                    $homepage_url,
                    $image_data['url'],
                    $image_data['context'],
                    $image_data['attachment_id'],
                    $image_data['alt_text'],
                    $image_data['title']
                );
                
                $this->scan_stats['images_found']++;
                $this->scan_stats['images_registered']++;
            }
            
            return array(
                'success' => true,
                'images_found' => count($images),
                'images_registered' => count($images),
                'scan_duration' => microtime(true) - $this->scan_stats['start_time']
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'images_found' => 0,
                'images_registered' => 0
            );
        }
    }
    
    /**
     * Scansiona un singolo post
     *
     * @param int $post_id ID del post
     * @return array Risultato della scansione
     */
    public function scan_single_post($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return array('success' => false, 'error' => 'Post non trovato');
        }
        
        $this->update_scan_status($post->post_type, $post_id, null, 'scanning');
        
        $start_time = microtime(true);
        $images_found = array();
        
        try {
            // 1. Scansiona il contenuto del post
            $content_images = $this->extract_images_from_content($post->post_content);
            $images_found = array_merge($images_found, $content_images);
            
            // 2. Scansiona l'immagine in evidenza
            $featured_image = $this->get_featured_image($post_id);
            if ($featured_image) {
                $images_found[] = $featured_image;
            }
            
            // 3. Scansiona i custom fields
            $custom_field_images = $this->extract_images_from_custom_fields($post_id);
            $images_found = array_merge($images_found, $custom_field_images);
            
            // 4. Scansiona page builder content se abilitato
            if ($this->scan_config['scan_page_builders']) {
                $builder_images = $this->page_builder_detector->detect_images_in_post($post_id);
                $images_found = array_merge($images_found, $builder_images);
            }
            
            // 5. Registra tutte le immagini trovate
            $registered_count = 0;
            $active_urls = array();
            
            foreach ($images_found as $image_data) {
                $result = $this->image_registry->register_image(
                    $post->post_type,
                    $post_id,
                    null,
                    $image_data
                );
                
                if ($result) {
                    $registered_count++;
                    $active_urls[] = $image_data['url'];
                }
            }
            
            // 6. Marca come non attive le immagini che non sono più presenti
            $this->image_registry->mark_inactive_images(
                $post->post_type,
                $post_id,
                null,
                $active_urls
            );
            
            $scan_duration = microtime(true) - $start_time;
            
            // Aggiorna lo stato della scansione
            $this->update_scan_status(
                $post->post_type,
                $post_id,
                null,
                'completed',
                $scan_duration,
                count($images_found)
            );
            
            // Aggiorna statistiche
            $this->scan_stats['posts_scanned']++;
            $this->scan_stats['images_found'] += count($images_found);
            $this->scan_stats['images_registered'] += $registered_count;
            
            return array(
                'success' => true,
                'images_found' => count($images_found),
                'images_registered' => $registered_count,
                'scan_duration' => $scan_duration
            );
            
        } catch (Exception $e) {
            $this->update_scan_status(
                $post->post_type,
                $post_id,
                null,
                'error',
                null,
                0,
                $e->getMessage()
            );
            
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Estrae immagini dal contenuto HTML
     *
     * @param string $content Contenuto HTML
     * @return array Array di dati immagine
     */
    private function extract_images_from_content($content) {
        $images = array();
        
        if (empty($content)) {
            return $images;
        }
        
        // 1. Pattern per immagini con classe wp-image-ID
        $pattern_wp_image = '/<img[^>]+wp-image-([0-9]+)[^>]*>/i';
        if (preg_match_all($pattern_wp_image, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $image_data = $this->parse_img_tag($match[0]);
                if ($image_data) {
                    $image_data['context'] = 'content';
                    $image_data['source'] = 'post_content';
                    $images[] = $image_data;
                }
            }
        }
        
        // 2. Pattern per tutte le altre immagini
        $pattern_all_images = '/<img[^>]+>/i';
        if (preg_match_all($pattern_all_images, $content, $matches)) {
            foreach ($matches[0] as $img_tag) {
                // Salta se già processata con wp-image
                if (strpos($img_tag, 'wp-image-') !== false) {
                    continue;
                }
                
                $image_data = $this->parse_img_tag($img_tag);
                if ($image_data) {
                    $image_data['context'] = 'content';
                    $image_data['source'] = 'post_content';
                    $images[] = $image_data;
                }
            }
        }
        
        // 3. Scansiona background images se abilitato
        if ($this->scan_config['scan_background_images']) {
            $bg_images = $this->extract_background_images($content);
            $images = array_merge($images, $bg_images);
        }
        
        return $images;
    }
    
    /**
     * Estrae immagini di sfondo dal CSS inline
     *
     * @param string $content Contenuto HTML
     * @return array Array di dati immagine
     */
    private function extract_background_images($content) {
        $images = array();
        
        // Pattern per background-image nel CSS inline
        $pattern = '/background-image\s*:\s*url\s*\(\s*["\']?([^"\')\s]+)["\']?\s*\)/i';
        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[1] as $url) {
                if ($this->is_valid_image_url($url)) {
                    $images[] = array(
                        'url' => $url,
                        'alt' => '',
                        'title' => '',
                        'context' => 'background',
                        'source' => 'css_inline'
                    );
                }
            }
        }
        
        return $images;
    }
    
    /**
     * Ottiene l'immagine in evidenza
     *
     * @param int $post_id ID del post
     * @return array|null Dati dell'immagine in evidenza
     */
    private function get_featured_image($post_id) {
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if (!$thumbnail_id) {
            return null;
        }
        
        $image_url = wp_get_attachment_url($thumbnail_id);
        if (!$image_url) {
            return null;
        }
        
        $image_meta = wp_get_attachment_metadata($thumbnail_id);
        $alt_text = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
        $image_post = get_post($thumbnail_id);
        
        return array(
            'url' => $image_url,
            'alt' => $alt_text,
            'title' => $image_post ? $image_post->post_title : '',
            'width' => isset($image_meta['width']) ? $image_meta['width'] : null,
            'height' => isset($image_meta['height']) ? $image_meta['height'] : null,
            'context' => 'featured',
            'source' => 'featured_image'
        );
    }
    
    /**
     * Estrae immagini dai custom fields
     *
     * @param int $post_id ID del post
     * @return array Array di dati immagine
     */
    private function extract_images_from_custom_fields($post_id) {
        $images = array();
        
        // Ottieni tutti i meta fields del post
        $meta_fields = get_post_meta($post_id);
        
        foreach ($meta_fields as $meta_key => $meta_values) {
            // Salta meta fields interni di WordPress
            if (strpos($meta_key, '_') === 0 && !in_array($meta_key, array('_thumbnail_id'))) {
                continue;
            }
            
            foreach ($meta_values as $meta_value) {
                // Controlla se il valore è un URL di immagine
                if (is_string($meta_value) && $this->is_valid_image_url($meta_value)) {
                    $images[] = array(
                        'url' => $meta_value,
                        'alt' => '',
                        'title' => '',
                        'context' => 'custom_field',
                        'source' => 'meta_' . $meta_key
                    );
                }
                
                // Controlla se il valore è un attachment ID
                if (is_numeric($meta_value) && wp_attachment_is_image($meta_value)) {
                    $image_url = wp_get_attachment_url($meta_value);
                    if ($image_url) {
                        $alt_text = get_post_meta($meta_value, '_wp_attachment_image_alt', true);
                        $image_post = get_post($meta_value);
                        
                        $images[] = array(
                            'url' => $image_url,
                            'alt' => $alt_text,
                            'title' => $image_post ? $image_post->post_title : '',
                            'context' => 'custom_field',
                            'source' => 'meta_' . $meta_key
                        );
                    }
                }
                
                // Controlla se il valore è JSON (per ACF e altri)
                if (is_string($meta_value) && $this->is_json($meta_value)) {
                    $json_images = $this->extract_images_from_json($meta_value, 'meta_' . $meta_key);
                    $images = array_merge($images, $json_images);
                }
            }
        }
        
        return $images;
    }
    
    /**
     * Estrae immagini da dati JSON
     *
     * @param string $json_string Stringa JSON
     * @param string $source Sorgente dei dati
     * @return array Array di dati immagine
     */
    private function extract_images_from_json($json_string, $source) {
        $images = array();
        $data = json_decode($json_string, true);
        
        if (!is_array($data)) {
            return $images;
        }
        
        // Funzione ricorsiva per cercare immagini nei dati JSON
        $find_images = function($data, $path = '') use (&$find_images, &$images, $source) {
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    $current_path = $path ? $path . '.' . $key : $key;
                    
                    if (is_string($value) && $this->is_valid_image_url($value)) {
                        $images[] = array(
                            'url' => $value,
                            'alt' => '',
                            'title' => '',
                            'context' => 'custom_field',
                            'source' => $source . '.' . $current_path
                        );
                    } elseif (is_array($value) || is_object($value)) {
                        $find_images($value, $current_path);
                    }
                }
            }
        };
        
        $find_images($data);
        
        return $images;
    }
    
    /**
     * Scansiona i widget
     */
    private function scan_widgets() {
        $widget_instances = array();
        
        // Ottieni tutte le sidebar registrate
        global $wp_registered_sidebars;
        
        foreach ($wp_registered_sidebars as $sidebar_id => $sidebar) {
            $widgets = wp_get_sidebars_widgets();
            
            if (isset($widgets[$sidebar_id])) {
                foreach ($widgets[$sidebar_id] as $widget_id) {
                    $this->scan_single_widget($widget_id, $sidebar_id);
                }
            }
        }
    }
    
    /**
     * Scansiona un singolo widget
     *
     * @param string $widget_id ID del widget
     * @param string $sidebar_id ID della sidebar
     */
    private function scan_single_widget($widget_id, $sidebar_id) {
        // Ottieni i dati del widget
        $widget_data = $this->get_widget_data($widget_id);
        
        if (!$widget_data) {
            return;
        }
        
        $images_found = array();
        
        // Cerca immagini nei dati del widget
        foreach ($widget_data as $key => $value) {
            if (is_string($value)) {
                // Controlla se è un URL di immagine
                if ($this->is_valid_image_url($value)) {
                    $images_found[] = array(
                        'url' => $value,
                        'alt' => '',
                        'title' => '',
                        'context' => 'widget',
                        'source' => 'widget_' . $widget_id . '_' . $key
                    );
                }
                
                // Cerca immagini nel contenuto HTML
                $content_images = $this->extract_images_from_content($value);
                foreach ($content_images as $image) {
                    $image['context'] = 'widget';
                    $image['source'] = 'widget_' . $widget_id . '_content';
                    $images_found[] = $image;
                }
            }
        }
        
        // Registra le immagini trovate
        $active_urls = array();
        foreach ($images_found as $image_data) {
            $result = $this->image_registry->register_image(
                'widget',
                null,
                $sidebar_id . '/' . $widget_id,
                $image_data
            );
            
            if ($result) {
                $active_urls[] = $image_data['url'];
            }
        }
        
        // Marca come non attive le immagini che non sono più presenti
        $this->image_registry->mark_inactive_images(
            'widget',
            null,
            $sidebar_id . '/' . $widget_id,
            $active_urls
        );
        
        $this->scan_stats['widgets_scanned']++;
        $this->scan_stats['images_found'] += count($images_found);
    }
    
    /**
     * Ottiene i dati di un widget
     *
     * @param string $widget_id ID del widget
     * @return array|null Dati del widget
     */
    private function get_widget_data($widget_id) {
        // Estrai il tipo di widget e il numero dall'ID
        if (preg_match('/^(.+)-(\d+)$/', $widget_id, $matches)) {
            $widget_type = $matches[1];
            $widget_number = intval($matches[2]);
            
            $widget_options = get_option('widget_' . $widget_type, array());
            
            return isset($widget_options[$widget_number]) ? $widget_options[$widget_number] : null;
        }
        
        return null;
    }
    
    /**
     * Scansiona le posizioni del tema (header, footer, etc.)
     */
    private function scan_theme_locations() {
        // Questa funzione può essere estesa per scansionare
        // immagini specifiche del tema come logo, favicon, etc.
        
        // Logo del sito
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_url($custom_logo_id);
            if ($logo_url) {
                $this->image_registry->register_image(
                    'theme',
                    null,
                    'site_logo',
                    array(
                        'url' => $logo_url,
                        'alt' => get_bloginfo('name'),
                        'title' => get_bloginfo('name'),
                        'context' => 'logo',
                        'source' => 'custom_logo'
                    )
                );
            }
        }
        
        // Favicon
        $site_icon_id = get_option('site_icon');
        if ($site_icon_id) {
            $icon_url = wp_get_attachment_url($site_icon_id);
            if ($icon_url) {
                $this->image_registry->register_image(
                    'theme',
                    null,
                    'site_icon',
                    array(
                        'url' => $icon_url,
                        'alt' => get_bloginfo('name'),
                        'title' => get_bloginfo('name'),
                        'context' => 'icon',
                        'source' => 'site_icon'
                    )
                );
            }
        }
    }
    
    /**
     * Parsa un tag img e estrae i dati
     *
     * @param string $img_tag Tag img HTML
     * @return array|null Dati dell'immagine
     */
    private function parse_img_tag($img_tag) {
        $doc = new DOMDocument();
        @$doc->loadHTML('<html><body>' . $img_tag . '</body></html>');
        $img = $doc->getElementsByTagName('img')->item(0);
        
        if (!$img) {
            return null;
        }
        
        $src = $img->getAttribute('src');
        if (!$this->is_valid_image_url($src)) {
            return null;
        }
        
        return array(
            'url' => $src,
            'alt' => $img->getAttribute('alt'),
            'title' => $img->getAttribute('title'),
            'width' => $img->getAttribute('width') ? intval($img->getAttribute('width')) : null,
            'height' => $img->getAttribute('height') ? intval($img->getAttribute('height')) : null
        );
    }
    
    /**
     * Verifica se un URL è un'immagine valida
     *
     * @param string $url URL da verificare
     * @return bool True se è un'immagine valida
     */
    private function is_valid_image_url($url) {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        $image_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp');
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        
        return in_array($extension, $image_extensions);
    }
    
    /**
     * Verifica se una stringa è JSON valido
     *
     * @param string $string Stringa da verificare
     * @return bool True se è JSON valido
     */
    private function is_json($string) {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    /**
     * Inizializza le statistiche di scansione
     */
    private function init_scan_stats() {
        $this->scan_stats = array(
            'start_time' => time(),
            'posts_scanned' => 0,
            'widgets_scanned' => 0,
            'images_found' => 0,
            'images_registered' => 0,
            'errors' => 0
        );
    }
    
    /**
     * Imposta i limiti per la scansione
     */
    private function set_scan_limits() {
        // Aumenta il limite di memoria
        if (function_exists('ini_set')) {
            ini_set('memory_limit', $this->scan_config['max_memory_usage']);
        }
        
        // Aumenta il limite di tempo
        set_time_limit($this->scan_config['timeout']);
    }
    
    /**
     * Verifica se la scansione dovrebbe essere messa in pausa
     *
     * @return bool True se dovrebbe essere messa in pausa
     */
    private function should_pause_scan() {
        // Controlla memoria
        $memory_usage = memory_get_usage(true);
        $memory_limit = $this->parse_size(ini_get('memory_limit'));
        
        if ($memory_usage > ($memory_limit * 0.9)) {
            return true;
        }
        
        // Controlla tempo
        $elapsed_time = time() - $this->scan_stats['start_time'];
        if ($elapsed_time > ($this->scan_config['timeout'] * 0.9)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Converte una stringa di dimensione in bytes
     *
     * @param string $size Stringa di dimensione (es. "256M")
     * @return int Dimensione in bytes
     */
    private function parse_size($size) {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);
        
        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }
        
        return round($size);
    }
    
    /**
     * Aggiorna lo stato della scansione
     *
     * @param string $content_type Tipo di contenuto
     * @param int|null $content_id ID del contenuto
     * @param string|null $content_url URL del contenuto
     * @param string $status Stato della scansione
     * @param float|null $duration Durata della scansione
     * @param int $images_found Numero di immagini trovate
     * @param string|null $error_message Messaggio di errore
     */
    private function update_scan_status($content_type, $content_id, $content_url, $status, $duration = null, $images_found = 0, $error_message = null) {
        global $wpdb;
        
        $table_name = $this->db_manager->get_table_name('scan_status');
        
        $data = array(
            'content_type' => $content_type,
            'content_id' => $content_id,
            'content_url' => $content_url,
            'last_scanned' => current_time('mysql'),
            'scan_duration' => $duration,
            'images_found' => $images_found,
            'scan_status' => $status,
            'error_message' => $error_message,
            'scan_method' => 'universal_scanner'
        );
        
        // Verifica se esiste già un record
        $where_conditions = array('content_type = %s');
        $where_values = array($content_type);
        
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
        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE $where_clause LIMIT 1",
            $where_values
        ));
        
        if ($existing_id) {
            $wpdb->update($table_name, $data, array('id' => $existing_id));
        } else {
            $wpdb->insert($table_name, $data);
        }
    }
    
    /**
     * Marca la scansione come completata
     */
    private function mark_scan_completed() {
        update_option('imgseo_last_full_scan', time());
        update_option('imgseo_last_scan_stats', $this->scan_stats);
    }
    
    /**
     * Handler AJAX per scansione manuale
     */
    public function ajax_scan_content() {
        // Verifica nonce per sicurezza
        if (!wp_verify_nonce($_POST['nonce'], 'imgseo_scan_content')) {
            wp_die('Security check failed');
        }
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $content_type = sanitize_text_field($_POST['content_type'] ?? 'all');
        $content_id = isset($_POST['content_id']) ? intval($_POST['content_id']) : null;
        
        if ($content_type === 'all') {
            $result = $this->scan_entire_site();
        } elseif ($content_id) {
            $result = $this->scan_single_post($content_id);
        } else {
            $result = array('success' => false, 'error' => 'Parametri non validi');
        }
        
        wp_send_json($result);
    }
    

    
    /**
     * Esegue una scansione programmata
     */
    public function run_scheduled_scan() {
        $this->scan_entire_site();
    }
    
    /**
     * Ottiene le statistiche dell'ultima scansione
     *
     * @return array Statistiche della scansione
     */
    public function get_scan_stats() {
        return get_option('imgseo_last_scan_stats', array());
    }
    
    /**
     * Ottiene il timestamp dell'ultima scansione completa
     *
     * @return int Timestamp dell'ultima scansione
     */
    public function get_last_scan_time() {
        return get_option('imgseo_last_full_scan', 0);
    }
    /**
     * Estrae immagini da un post specifico
     *
     * @param int $post_id ID del post
     * @return array Array di dati immagine
     */
    private function extract_images_from_post($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return array();
        }
        
        $images = array();
        
        // Estrai immagini dal contenuto
        $content_images = $this->extract_images_from_content($post->post_content);
        $images = array_merge($images, $content_images);
        
        // Aggiungi immagine in evidenza
        $featured_image_id = get_post_thumbnail_id($post_id);
        if ($featured_image_id) {
            $featured_url = wp_get_attachment_url($featured_image_id);
            if ($featured_url) {
                $images[] = array(
                    'url' => $featured_url,
                    'attachment_id' => $featured_image_id,
                    'alt_text' => get_post_meta($featured_image_id, '_wp_attachment_image_alt', true),
                    'title' => get_the_title($featured_image_id),
                    'context' => 'featured_image'
                );
            }
        }
        
        // Estrai immagini dai custom fields
        $custom_field_images = $this->extract_images_from_custom_fields($post_id);
        $images = array_merge($images, $custom_field_images);
        
        return $images;
    }
    
    /**
     * Estrae immagini da un widget
     *
     * @param string $widget_id ID del widget
     * @return array Array di dati immagine
     */
    private function extract_images_from_widget($widget_id) {
        $images = array();
        
        // Ottieni le opzioni del widget
        $widget_options = get_option('widget_' . $widget_id, array());
        
        if (empty($widget_options)) {
            return $images;
        }
        
        // Cerca immagini nelle opzioni del widget
        foreach ($widget_options as $instance) {
            if (!is_array($instance)) {
                continue;
            }
            
            // Cerca URL di immagini
            foreach ($instance as $key => $value) {
                if (is_string($value)) {
                    // Cerca URL di immagini nel valore
                    $widget_images = $this->extract_images_from_content($value);
                    foreach ($widget_images as $image) {
                        $image['context'] = 'widget_' . $widget_id;
                        $images[] = $image;
                    }
                }
            }
        }
        
        return $images;
    }
    
    /**
     * Scansiona il contenuto della homepage
     *
     * @return array Array di dati immagine
     */
    private function scan_homepage_content() {
        global $wp_query;
        $images = array();
        
        // Salva lo stato corrente
        $original_query = $wp_query;
        $original_post = $GLOBALS['post'] ?? null;
        
        try {
            // Se la homepage mostra gli ultimi post
            if (is_home() || (is_front_page() && get_option('show_on_front') == 'posts')) {
                // Ottieni i post della homepage
                $homepage_query = new WP_Query(array(
                    'post_type' => 'post',
                    'posts_per_page' => get_option('posts_per_page', 10),
                    'post_status' => 'publish'
                ));
                
                if ($homepage_query->have_posts()) {
                    while ($homepage_query->have_posts()) {
                        $homepage_query->the_post();
                        $post_images = $this->extract_images_from_post(get_the_ID());
                        $images = array_merge($images, $post_images);
                    }
                }
                
                wp_reset_postdata();
            }
            // Se la homepage è una pagina statica
            elseif (is_front_page() && get_option('show_on_front') == 'page') {
                $page_id = get_option('page_on_front');
                if ($page_id) {
                    $images = $this->extract_images_from_post($page_id);
                }
            }
            
            // Aggiungi immagini dai widget della homepage
            $widget_images = $this->scan_widgets_for_homepage();
            $images = array_merge($images, $widget_images);
            
        } finally {
            // Ripristina lo stato originale
            $wp_query = $original_query;
            if ($original_post) {
                $GLOBALS['post'] = $original_post;
            }
        }
        
        return array_unique($images, SORT_REGULAR);
    }
    
    /**
     * Scansiona i widget per la homepage
     *
     * @return array Array di dati immagine
     */
    private function scan_widgets_for_homepage() {
        $images = array();
        
        // Ottieni le sidebar attive
        $sidebars = wp_get_sidebars_widgets();
        
        foreach ($sidebars as $sidebar_id => $widget_ids) {
            if (empty($widget_ids) || !is_array($widget_ids)) {
                continue;
            }
            
            foreach ($widget_ids as $widget_id) {
                $widget_images = $this->extract_images_from_widget($widget_id);
                $images = array_merge($images, $widget_images);
            }
        }
        
        return $images;
    }
}