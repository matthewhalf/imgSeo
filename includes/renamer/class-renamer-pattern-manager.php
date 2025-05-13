<?php
/**
 * Class Renamer_Pattern_Manager
 * Gestisce i pattern di rinomina e la loro applicazione ai nomi dei file
 */
class Renamer_Pattern_Manager {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Pattern disponibili
     */
    private $available_patterns = array();
    
    /**
     * Counter sequenziale per pattern {numero}
     */
    private $sequence_counter = 1;
    
    /**
     * Inizializza il manager
     */
    private function __construct() {
        // Registra i pattern di base
        $this->register_default_patterns();
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
     * Registra i pattern predefiniti
     */
    private function register_default_patterns() {
        $this->register_pattern('{post_title}', array($this, 'process_post_title'));
        $this->register_pattern('{category}', array($this, 'process_category'));
        $this->register_pattern('{numero}', array($this, 'process_sequential_number'));
        $this->register_pattern('{originale}', array($this, 'process_original_filename'));
        $this->register_pattern('{data}', array($this, 'process_date'));
        $this->register_pattern('{alt}', array($this, 'process_alt_text'));
    }
    
    /**
     * Registra un nuovo pattern
     * 
     * @param string $pattern Il pattern da registrare (es. {post_title})
     * @param callable $callback La funzione che elabora il pattern
     */
    public function register_pattern($pattern, $callback) {
        if (is_callable($callback)) {
            $this->available_patterns[$pattern] = $callback;
        }
    }
    
    /**
     * Applica tutti i pattern a un nome file
     * 
     * @param string $template Template con pattern da elaborare
     * @param int $attachment_id ID dell'allegato
     * @param array $context Dati contestuali per l'elaborazione
     * @return string Nome file con pattern sostituiti
     */
    public function apply_patterns($template, $attachment_id, $context = array()) {
        if (empty($template)) {
            return '';
        }
        
        // Prepara il context con dati essenziali se non presenti
        if (!isset($context['original_filename'])) {
            $file = get_attached_file($attachment_id);
            if ($file) {
                $context['original_filename'] = pathinfo($file, PATHINFO_FILENAME);
            }
        }
        
        // Applica ciascun pattern registrato
        foreach ($this->available_patterns as $pattern => $callback) {
            if (strpos($template, $pattern) !== false) {
                $replacement = call_user_func($callback, $attachment_id, $context);
                $template = str_replace($pattern, $replacement, $template);
            }
        }
        
        // Sanitizza il risultato finale
        $template = $this->sanitize_filename($template, array(
            'remove_accents' => true,
            'lowercase' => true
        ));
        
        return $template;
    }
    
    /**
     * Restituisce il titolo del post associato
     */
    public function process_post_title($attachment_id, $context) {
        // Cerca prima il post parent
        $post_parent = wp_get_post_parent_id($attachment_id);
        
        if ($post_parent) {
            $parent = get_post($post_parent);
            if ($parent && !empty($parent->post_title)) {
                return $this->sanitize_filename($parent->post_title);
            }
        }
        
        // Se non c'è un post parent, usa il titolo dell'allegato
        $attachment = get_post($attachment_id);
        if ($attachment && !empty($attachment->post_title)) {
            return $this->sanitize_filename($attachment->post_title);
        }
        
        // Fallback al nome originale
        return isset($context['original_filename']) ? $context['original_filename'] : '';
    }
    
    /**
     * Restituisce la categoria del post associato
     */
    public function process_category($attachment_id, $context) {
        // Cerca il post parent
        $post_parent = wp_get_post_parent_id($attachment_id);
        
        if ($post_parent) {
            // Ottieni le categorie
            $categories = get_the_category($post_parent);
            
            if (!empty($categories)) {
                // Usa la prima categoria trovata
                return $this->sanitize_filename($categories[0]->name);
            }
        }
        
        // Fallback - se non ci sono categorie, restituisci stringa vuota
        return '';
    }
    
    /**
     * Restituisce un numero sequenziale
     */
    public function process_sequential_number($attachment_id, $context) {
        // Se c'è un numero già definito nel context, usalo
        if (isset($context['sequence_number'])) {
            return $context['sequence_number'];
        }
        
        // Altrimenti usa il numero sequenziale interno
        $number = $this->sequence_counter++;
        return sprintf('%03d', $number); // Formatta con zeri iniziali (001, 002, ecc.)
    }
    
    /**
     * Restituisce il nome file originale
     */
    public function process_original_filename($attachment_id, $context) {
        if (isset($context['original_filename'])) {
            return $context['original_filename'];
        }
        
        $file = get_attached_file($attachment_id);
        if ($file) {
            return pathinfo($file, PATHINFO_FILENAME);
        }
        
        return '';
    }
    
    /**
     * Restituisce la data corrente o del post
     */
    public function process_date($attachment_id, $context) {
        // Ottieni la data dell'allegato
        $attachment = get_post($attachment_id);
        if ($attachment) {
            $post_date = get_the_date('Y-m-d', $attachment_id);
            return str_replace('-', '', $post_date); // Formato YYYYMMDD
        }
        
        // Fallback alla data corrente
        return date('Ymd');
    }
    
    /**
     * Restituisce il testo alternativo dell'immagine
     */
    public function process_alt_text($attachment_id, $context) {
        $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        
        if (!empty($alt_text)) {
            return $this->sanitize_filename($alt_text);
        }
        
        return '';
    }
    
    /**
     * Reimposta il contatore sequenziale
     */
    public function reset_sequence() {
        $this->sequence_counter = 1;
    }
    
    /**
     * Imposta un valore specifico per il contatore sequenziale
     */
    public function set_sequence_start($value) {
        $this->sequence_counter = max(1, intval($value));
    }
    
    /**
     * Sanitizza un nome file
     * 
     * @param string $filename Nome file da sanitizzare
     * @param array $options Opzioni di sanitizzazione
     * @return string Nome file sanitizzato
     */
    public function sanitize_filename($filename, $options = array()) {
        // Rimuovi spazi extra e caratteri non necessari
        $filename = trim($filename);
        
        // Rimozione accenti
        if (!empty($options['remove_accents'])) {
            $filename = remove_accents($filename);
        }
        
        // Conversione in minuscolo
        if (!empty($options['lowercase'])) {
            $filename = strtolower($filename);
        }
        
        // Sostituzione spazi e caratteri speciali
        $filename = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $filename);
        $filename = preg_replace('/-+/', '-', $filename); // Rimuovi trattini multipli
        $filename = trim($filename, '-');
        
        // Assicurati che il nome file non sia vuoto
        if (empty($filename)) {
            $filename = 'image';
        }
        
        return $filename;
    }
}