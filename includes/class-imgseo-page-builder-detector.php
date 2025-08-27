<?php
/**
 * Classe per il rilevamento delle immagini nei page builder
 * Supporta Elementor, Divi, Beaver Builder, Visual Composer e Gutenberg
 *
 * @package ImgSEO
 * @since 2.0.0
 */

// Impedisce l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe ImgSEO_Page_Builder_Detector
 * 
 * Rileva immagini specifiche dei page builder più popolari
 */
class ImgSEO_Page_Builder_Detector {
    
    /**
     * Istanza singleton della classe
     *
     * @var ImgSEO_Page_Builder_Detector|null
     */
    private static $instance = null;
    
    /**
     * Cache per i dati dei page builder
     *
     * @var array
     */
    private $cache = array();
    
    /**
     * Ottiene l'istanza singleton della classe
     *
     * @return ImgSEO_Page_Builder_Detector
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
        // Inizializzazione se necessaria
    }
    
    /**
     * Rileva tutte le immagini nei page builder per un post
     *
     * @param int $post_id ID del post
     * @return array Array di dati immagine
     */
    public function detect_images_in_post($post_id) {
        $images = array();
        
        // Controlla cache
        if (isset($this->cache[$post_id])) {
            return $this->cache[$post_id];
        }
        
        // Rileva immagini Elementor
        if ($this->is_elementor_active()) {
            $elementor_images = $this->detect_elementor_images($post_id);
            $images = array_merge($images, $elementor_images);
        }
        
        // Rileva immagini Divi
        if ($this->is_divi_active()) {
            $divi_images = $this->detect_divi_images($post_id);
            $images = array_merge($images, $divi_images);
        }
        
        // Rileva immagini Beaver Builder
        if ($this->is_beaver_builder_active()) {
            $beaver_images = $this->detect_beaver_images($post_id);
            $images = array_merge($images, $beaver_images);
        }
        
        // Rileva immagini Visual Composer
        if ($this->is_visual_composer_active()) {
            $vc_images = $this->detect_visual_composer_images($post_id);
            $images = array_merge($images, $vc_images);
        }
        
        // Rileva immagini Gutenberg
        $gutenberg_images = $this->detect_gutenberg_images($post_id);
        $images = array_merge($images, $gutenberg_images);
        
        // Cache del risultato
        $this->cache[$post_id] = $images;
        
        return $images;
    }
    
    /**
     * Rileva immagini in Elementor
     *
     * @param int $post_id ID del post
     * @return array Array di dati immagine
     */
    public function detect_elementor_images($post_id) {
        $images = array();
        
        if (!$this->is_elementor_active()) {
            return $images;
        }
        
        // Ottieni i dati Elementor
        $elementor_data = get_post_meta($post_id, '_elementor_data', true);
        
        if (empty($elementor_data)) {
            return $images;
        }
        
        // Decodifica i dati JSON
        $data = json_decode($elementor_data, true);
        if (!is_array($data)) {
            return $images;
        }
        
        // Funzione ricorsiva per cercare immagini
        $find_images = function($elements) use (&$find_images, &$images) {
            if (!is_array($elements)) {
                return;
            }
            
            foreach ($elements as $element) {
                if (!is_array($element)) {
                    continue;
                }
                
                // Controlla le impostazioni dell'elemento
                if (isset($element['settings'])) {
                    $this->extract_elementor_images_from_settings($element['settings'], $images);
                }
                
                // Ricorsione per elementi figli
                if (isset($element['elements'])) {
                    $find_images($element['elements']);
                }
            }
        };
        
        $find_images($data);
        
        return $images;
    }
    
    /**
     * Estrae immagini dalle impostazioni di un elemento Elementor
     *
     * @param array $settings Impostazioni dell'elemento
     * @param array &$images Array di immagini (passato per riferimento)
     */
    private function extract_elementor_images_from_settings($settings, &$images) {
        if (!is_array($settings)) {
            return;
        }
        
        // Campi immagine comuni in Elementor
        $image_fields = array(
            'image', 'background_image', 'bg_image', 'icon_image',
            'gallery', 'carousel_image', 'slide_image', 'testimonial_image'
        );
        
        foreach ($settings as $key => $value) {
            // Controlla se è un campo immagine
            if (in_array($key, $image_fields) || strpos($key, 'image') !== false) {
                if (is_array($value) && isset($value['url'])) {
                    // Formato standard Elementor
                    $images[] = array(
                        'url' => $value['url'],
                        'alt' => isset($value['alt']) ? $value['alt'] : '',
                        'title' => isset($value['title']) ? $value['title'] : '',
                        'context' => 'page_builder',
                        'source' => 'elementor_' . $key
                    );
                } elseif (is_string($value) && $this->is_valid_image_url($value)) {
                    // URL diretto
                    $images[] = array(
                        'url' => $value,
                        'alt' => '',
                        'title' => '',
                        'context' => 'page_builder',
                        'source' => 'elementor_' . $key
                    );
                }
            }
            
            // Controlla gallery
            if ($key === 'gallery' && is_array($value)) {
                foreach ($value as $gallery_item) {
                    if (is_array($gallery_item) && isset($gallery_item['url'])) {
                        $images[] = array(
                            'url' => $gallery_item['url'],
                            'alt' => isset($gallery_item['alt']) ? $gallery_item['alt'] : '',
                            'title' => isset($gallery_item['title']) ? $gallery_item['title'] : '',
                            'context' => 'page_builder',
                            'source' => 'elementor_gallery'
                        );
                    }
                }
            }
            
            // Ricorsione per array annidati
            if (is_array($value)) {
                $this->extract_elementor_images_from_settings($value, $images);
            }
        }
    }
    
    /**
     * Rileva immagini in Divi
     *
     * @param int $post_id ID del post
     * @return array Array di dati immagine
     */
    public function detect_divi_images($post_id) {
        $images = array();
        
        if (!$this->is_divi_active()) {
            return $images;
        }
        
        // Ottieni il contenuto del post (Divi salva tutto nel post_content)
        $post = get_post($post_id);
        if (!$post) {
            return $images;
        }
        
        $content = $post->post_content;
        
        // Pattern per moduli Divi con immagini
        $patterns = array(
            // Modulo immagine
            '/\[et_pb_image[^\]]*src="([^"]+)"[^\]]*\]/i',
            // Modulo gallery
            '/\[et_pb_gallery[^\]]*gallery_ids="([^"]+)"[^\]]*\]/i',
            // Background image
            '/background_image="([^"]+)"/i',
            // Slider
            '/\[et_pb_slide[^\]]*image="([^"]+)"[^\]]*\]/i'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $match) {
                    if (strpos($pattern, 'gallery_ids') !== false) {
                        // Gestisci gallery IDs
                        $ids = explode(',', $match);
                        foreach ($ids as $id) {
                            $id = trim($id);
                            if (is_numeric($id) && wp_attachment_is_image($id)) {
                                $image_url = wp_get_attachment_url($id);
                                if ($image_url) {
                                    $alt_text = get_post_meta($id, '_wp_attachment_image_alt', true);
                                    $image_post = get_post($id);
                                    
                                    $images[] = array(
                                        'url' => $image_url,
                                        'alt' => $alt_text,
                                        'title' => $image_post ? $image_post->post_title : '',
                                        'context' => 'page_builder',
                                        'source' => 'divi_gallery'
                                    );
                                }
                            }
                        }
                    } elseif ($this->is_valid_image_url($match)) {
                        $images[] = array(
                            'url' => $match,
                            'alt' => '',
                            'title' => '',
                            'context' => 'page_builder',
                            'source' => 'divi_module'
                        );
                    }
                }
            }
        }
        
        return $images;
    }
    
    /**
     * Rileva immagini in Beaver Builder
     *
     * @param int $post_id ID del post
     * @return array Array di dati immagine
     */
    public function detect_beaver_images($post_id) {
        $images = array();
        
        if (!$this->is_beaver_builder_active()) {
            return $images;
        }
        
        // Ottieni i dati Beaver Builder
        $fl_builder_data = get_post_meta($post_id, '_fl_builder_data', true);
        
        if (empty($fl_builder_data) || !is_array($fl_builder_data)) {
            return $images;
        }
        
        foreach ($fl_builder_data as $node) {
            if (!is_object($node) || !isset($node->settings)) {
                continue;
            }
            
            $settings = $node->settings;
            
            // Campi immagine comuni in Beaver Builder
            $image_fields = array(
                'photo', 'bg_image', 'image', 'gallery', 'photos'
            );
            
            foreach ($image_fields as $field) {
                if (isset($settings->$field)) {
                    $value = $settings->$field;
                    
                    if (is_string($value) && $this->is_valid_image_url($value)) {
                        $images[] = array(
                            'url' => $value,
                            'alt' => '',
                            'title' => '',
                            'context' => 'page_builder',
                            'source' => 'beaver_' . $field
                        );
                    } elseif (is_array($value)) {
                        foreach ($value as $item) {
                            if (is_string($item) && $this->is_valid_image_url($item)) {
                                $images[] = array(
                                    'url' => $item,
                                    'alt' => '',
                                    'title' => '',
                                    'context' => 'page_builder',
                                    'source' => 'beaver_' . $field
                                );
                            }
                        }
                    }
                }
            }
        }
        
        return $images;
    }
    
    /**
     * Rileva immagini in Visual Composer
     *
     * @param int $post_id ID del post
     * @return array Array di dati immagine
     */
    public function detect_visual_composer_images($post_id) {
        $images = array();
        
        if (!$this->is_visual_composer_active()) {
            return $images;
        }
        
        // Ottieni il contenuto del post
        $post = get_post($post_id);
        if (!$post) {
            return $images;
        }
        
        $content = $post->post_content;
        
        // Pattern per shortcode Visual Composer con immagini
        $patterns = array(
            // Single Image
            '/\[vc_single_image[^\]]*image="([^"]+)"[^\]]*\]/i',
            // Gallery
            '/\[vc_gallery[^\]]*images="([^"]+)"[^\]]*\]/i',
            // Background image
            '/css="[^"]*background-image:\s*url\(([^)]+)\)[^"]*"/i'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $match) {
                    if (strpos($pattern, 'images=') !== false) {
                        // Gestisci gallery IDs
                        $ids = explode(',', $match);
                        foreach ($ids as $id) {
                            $id = trim($id);
                            if (is_numeric($id) && wp_attachment_is_image($id)) {
                                $image_url = wp_get_attachment_url($id);
                                if ($image_url) {
                                    $alt_text = get_post_meta($id, '_wp_attachment_image_alt', true);
                                    $image_post = get_post($id);
                                    
                                    $images[] = array(
                                        'url' => $image_url,
                                        'alt' => $alt_text,
                                        'title' => $image_post ? $image_post->post_title : '',
                                        'context' => 'page_builder',
                                        'source' => 'vc_gallery'
                                    );
                                }
                            }
                        }
                    } elseif (is_numeric($match) && wp_attachment_is_image($match)) {
                        // ID attachment
                        $image_url = wp_get_attachment_url($match);
                        if ($image_url) {
                            $alt_text = get_post_meta($match, '_wp_attachment_image_alt', true);
                            $image_post = get_post($match);
                            
                            $images[] = array(
                                'url' => $image_url,
                                'alt' => $alt_text,
                                'title' => $image_post ? $image_post->post_title : '',
                                'context' => 'page_builder',
                                'source' => 'vc_image'
                            );
                        }
                    } elseif ($this->is_valid_image_url($match)) {
                        $images[] = array(
                            'url' => $match,
                            'alt' => '',
                            'title' => '',
                            'context' => 'page_builder',
                            'source' => 'vc_background'
                        );
                    }
                }
            }
        }
        
        return $images;
    }
    
    /**
     * Rileva immagini nei blocchi Gutenberg
     *
     * @param int $post_id ID del post
     * @return array Array di dati immagine
     */
    public function detect_gutenberg_images($post_id) {
        $images = array();
        
        $post = get_post($post_id);
        if (!$post) {
            return $images;
        }
        
        // Controlla se il post usa Gutenberg
        if (!has_blocks($post->post_content)) {
            return $images;
        }
        
        // Parsa i blocchi
        $blocks = parse_blocks($post->post_content);
        
        foreach ($blocks as $block) {
            $block_images = $this->extract_images_from_gutenberg_block($block);
            $images = array_merge($images, $block_images);
        }
        
        return $images;
    }
    
    /**
     * Estrae immagini da un blocco Gutenberg
     *
     * @param array $block Dati del blocco
     * @return array Array di dati immagine
     */
    private function extract_images_from_gutenberg_block($block) {
        $images = array();
        
        if (!is_array($block) || empty($block['blockName'])) {
            return $images;
        }
        
        $block_name = $block['blockName'];
        $attributes = isset($block['attrs']) ? $block['attrs'] : array();
        
        // Blocchi immagine standard
        if ($block_name === 'core/image') {
            if (isset($attributes['id']) && wp_attachment_is_image($attributes['id'])) {
                $image_url = wp_get_attachment_url($attributes['id']);
                if ($image_url) {
                    $alt_text = get_post_meta($attributes['id'], '_wp_attachment_image_alt', true);
                    $image_post = get_post($attributes['id']);
                    
                    $images[] = array(
                        'url' => $image_url,
                        'alt' => $alt_text,
                        'title' => $image_post ? $image_post->post_title : '',
                        'context' => 'page_builder',
                        'source' => 'gutenberg_image'
                    );
                }
            } elseif (isset($attributes['url']) && $this->is_valid_image_url($attributes['url'])) {
                $images[] = array(
                    'url' => $attributes['url'],
                    'alt' => isset($attributes['alt']) ? $attributes['alt'] : '',
                    'title' => isset($attributes['caption']) ? $attributes['caption'] : '',
                    'context' => 'page_builder',
                    'source' => 'gutenberg_image'
                );
            }
        }
        
        // Blocco gallery
        elseif ($block_name === 'core/gallery') {
            if (isset($attributes['ids']) && is_array($attributes['ids'])) {
                foreach ($attributes['ids'] as $id) {
                    if (wp_attachment_is_image($id)) {
                        $image_url = wp_get_attachment_url($id);
                        if ($image_url) {
                            $alt_text = get_post_meta($id, '_wp_attachment_image_alt', true);
                            $image_post = get_post($id);
                            
                            $images[] = array(
                                'url' => $image_url,
                                'alt' => $alt_text,
                                'title' => $image_post ? $image_post->post_title : '',
                                'context' => 'page_builder',
                                'source' => 'gutenberg_gallery'
                            );
                        }
                    }
                }
            }
        }
        
        // Blocco cover con background image
        elseif ($block_name === 'core/cover') {
            if (isset($attributes['id']) && wp_attachment_is_image($attributes['id'])) {
                $image_url = wp_get_attachment_url($attributes['id']);
                if ($image_url) {
                    $images[] = array(
                        'url' => $image_url,
                        'alt' => '',
                        'title' => '',
                        'context' => 'page_builder',
                        'source' => 'gutenberg_cover'
                    );
                }
            } elseif (isset($attributes['url']) && $this->is_valid_image_url($attributes['url'])) {
                $images[] = array(
                    'url' => $attributes['url'],
                    'alt' => '',
                    'title' => '',
                    'context' => 'page_builder',
                    'source' => 'gutenberg_cover'
                );
            }
        }
        
        // Blocco media e testo
        elseif ($block_name === 'core/media-text') {
            if (isset($attributes['mediaId']) && wp_attachment_is_image($attributes['mediaId'])) {
                $image_url = wp_get_attachment_url($attributes['mediaId']);
                if ($image_url) {
                    $alt_text = get_post_meta($attributes['mediaId'], '_wp_attachment_image_alt', true);
                    $image_post = get_post($attributes['mediaId']);
                    
                    $images[] = array(
                        'url' => $image_url,
                        'alt' => $alt_text,
                        'title' => $image_post ? $image_post->post_title : '',
                        'context' => 'page_builder',
                        'source' => 'gutenberg_media_text'
                    );
                }
            }
        }
        
        // Ricorsione per blocchi annidati
        if (isset($block['innerBlocks']) && is_array($block['innerBlocks'])) {
            foreach ($block['innerBlocks'] as $inner_block) {
                $inner_images = $this->extract_images_from_gutenberg_block($inner_block);
                $images = array_merge($images, $inner_images);
            }
        }
        
        return $images;
    }
    
    /**
     * Verifica se Elementor è attivo
     *
     * @return bool
     */
    private function is_elementor_active() {
        return did_action('elementor/loaded');
    }
    
    /**
     * Verifica se Divi è attivo
     *
     * @return bool
     */
    private function is_divi_active() {
        return function_exists('et_setup_theme') || defined('ET_BUILDER_VERSION');
    }
    
    /**
     * Verifica se Beaver Builder è attivo
     *
     * @return bool
     */
    private function is_beaver_builder_active() {
        return class_exists('FLBuilder');
    }
    
    /**
     * Verifica se Visual Composer è attivo
     *
     * @return bool
     */
    private function is_visual_composer_active() {
        return defined('WPB_VC_VERSION') || function_exists('vc_map');
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
     * Ottiene informazioni sui page builder attivi
     *
     * @return array Informazioni sui page builder
     */
    public function get_active_page_builders() {
        $builders = array();
        
        if ($this->is_elementor_active()) {
            $builders['elementor'] = array(
                'name' => 'Elementor',
                'version' => defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : 'Unknown'
            );
        }
        
        if ($this->is_divi_active()) {
            $builders['divi'] = array(
                'name' => 'Divi Builder',
                'version' => defined('ET_BUILDER_VERSION') ? ET_BUILDER_VERSION : 'Unknown'
            );
        }
        
        if ($this->is_beaver_builder_active()) {
            $builders['beaver'] = array(
                'name' => 'Beaver Builder',
                'version' => defined('FL_BUILDER_VERSION') ? FL_BUILDER_VERSION : 'Unknown'
            );
        }
        
        if ($this->is_visual_composer_active()) {
            $builders['visual_composer'] = array(
                'name' => 'Visual Composer',
                'version' => defined('WPB_VC_VERSION') ? WPB_VC_VERSION : 'Unknown'
            );
        }
        
        $builders['gutenberg'] = array(
            'name' => 'Gutenberg',
            'version' => get_bloginfo('version')
        );
        
        return $builders;
    }
    
    /**
     * Pulisce la cache
     */
    public function clear_cache() {
        $this->cache = array();
    }
    
    /**
     * Ottiene statistiche sui page builder
     *
     * @return array Statistiche
     */
    public function get_page_builder_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Conta post con Elementor
        if ($this->is_elementor_active()) {
            $stats['elementor_posts'] = intval($wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_edit_mode' AND meta_value = 'builder'"
            ));
        }
        
        // Conta post con Divi
        if ($this->is_divi_active()) {
            $stats['divi_posts'] = intval($wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_et_pb_use_builder' AND meta_value = 'on'"
            ));
        }
        
        // Conta post con Beaver Builder
        if ($this->is_beaver_builder_active()) {
            $stats['beaver_posts'] = intval($wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_fl_builder_enabled' AND meta_value = '1'"
            ));
        }
        
        // Conta post con blocchi Gutenberg
        $stats['gutenberg_posts'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE '%<!-- wp:%' AND post_status = 'publish'"
        ));
        
        return $stats;
    }
}