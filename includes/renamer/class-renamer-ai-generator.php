<?php
/**
 * Class Renamer_AI_Generator
 * Gestisce la generazione di nomi file ottimizzati per SEO tramite AI
 */
class Renamer_AI_Generator {
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Inizializza il generatore
     */
    private function __construct() {
        // Registra l'AJAX handler
        add_action('wp_ajax_imgseo_generate_ai_filename', array($this, 'ajax_generate_ai_filename'));
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
     * AJAX handler per la generazione del nome file
     */
    public function ajax_generate_ai_filename() {
        // Verifica nonce
        check_ajax_referer('imgseo_renamer_nonce', 'security');
        
        // Verifica permessi
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', IMGSEO_TEXT_DOMAIN)));
        }
        
        // Ottieni ID allegato
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        if (!$attachment_id) {
            wp_send_json_error(array('message' => __('Invalid attachment ID', IMGSEO_TEXT_DOMAIN)));
        }
        
        // Genera il nome file
        $result = $this->generate_filename($attachment_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array('filename' => $result));
    }
    
    /**
     * Genera un nome file ottimizzato per SEO utilizzando l'AI
     * 
     * @param int $attachment_id ID dell'allegato
     * @return string|WP_Error Nome del file generato o errore
     */
    public function generate_filename($attachment_id) {
        // Verifica se esiste un blocco globale di processo
        if (class_exists('ImgSEO_Process_Lock') && ImgSEO_Process_Lock::is_globally_locked()) {
            return new WP_Error('process_locked', __('Le operazioni ImgSEO sono temporaneamente bloccate. Riprova tra qualche istante.', IMGSEO_TEXT_DOMAIN));
        }
        
        // Ottieni l'URL dell'immagine
        $image_url = wp_get_attachment_url($attachment_id);
        if (!$image_url) {
            return new WP_Error('invalid_attachment', __('Invalid attachment ID', IMGSEO_TEXT_DOMAIN));
        }
        
        // Ottieni il contesto dell'immagine
        $context = $this->get_image_context($attachment_id);
        
        // Genera il prompt per l'AI
        $prompt = $this->build_ai_prompt($context);
        
        // Verifica i crediti disponibili prima di chiamare l'API
        $api_instance = Imgseo_API::get_instance();
        $credits = get_option('imgseo_credits', 0);
        
        // Se i crediti sono insufficienti, avvisa l'utente
        if ($credits <= 0) {
            return new WP_Error('insufficient_credits', __('Crediti ImgSEO insufficienti. Acquista più crediti per continuare.', IMGSEO_TEXT_DOMAIN));
        }
        
        // Ottieni il codice lingua dalle impostazioni
        $lang_code = get_option('imgseo_language', 'english');
        
        // Imposta opzioni per le API nuove
        $options = array(
            'source' => 'ai_generator',
            'attachment_id' => $attachment_id,
            'lang' => $lang_code // Passa direttamente il codice lingua all'API
        );
        
        // Chiama l'API ImgSEO
        $response = $api_instance->generate_alt_text($image_url, $prompt, $options);
        
        // Process the response
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Solo la nuova struttura API
        if (!isset($response['alt_text'])) {
            return new WP_Error('invalid_response', __('Invalid API response', IMGSEO_TEXT_DOMAIN));
        }
        
        $alt_text = $response['alt_text'];
        
        // Restituisci il nome file generato 
        // Rimuovi eventuali punti o estensioni dalla risposta AI
        $filename = $alt_text;
        $filename = preg_replace('/\.\w+$/', '', $filename); // Rimuove eventuali estensioni
        
        return $filename;
    }
    
    /**
     * Ottiene il contesto dell'immagine per arricchire il prompt
     * 
     * @param int $attachment_id ID dell'allegato
     * @return array Contesto dell'immagine
     */
    private function get_image_context($attachment_id) {
        $context = array();
        
        // Ottieni i metadati dell'immagine
        $attachment = get_post($attachment_id);
        if ($attachment) {
            $context['title'] = $attachment->post_title;
            $context['alt_text'] = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
            $context['caption'] = $attachment->post_excerpt;
            $context['description'] = $attachment->post_content;
        }
        
        // Ottieni il post parent se esiste
        $post_parent = wp_get_post_parent_id($attachment_id);
        if ($post_parent) {
            $parent = get_post($post_parent);
            if ($parent) {
                $context['post_title'] = $parent->post_title;
                $context['post_content'] = wp_trim_words($parent->post_content, 50); // Limita la lunghezza
                
                // Ottieni categorie
                $categories = get_the_category($post_parent);
                if (!empty($categories)) {
                    $context['category'] = $categories[0]->name;
                }
            }
        }
        
        return $context;
    }
    
    /**
     * Costruisce il prompt per l'AI in base al contesto e alle impostazioni
     * 
     * @param array $context Contesto dell'immagine
     * @return string Prompt per l'AI
     */
    private function build_ai_prompt($context) {
        // Ottieni la lingua dalle impostazioni generali del plugin
        $lang_code = get_option('imgseo_language', 'english');
        
        // Mappa dei codici di lingua ai nomi visualizzati
        $languages = [
            'english' => 'English',
            'italiano' => 'Italian',
            'japanese' => 'Japanese',
            'korean' => 'Korean',
            'arabic' => 'Arabic',
            'bahasa_indonesia' => 'Indonesian',
            'bengali' => 'Bengali',
            'bulgarian' => 'Bulgarian',
            'chinese_simplified' => 'Chinese (Simplified)',
            'chinese_traditional' => 'Chinese (Traditional)',
            'croatian' => 'Croatian',
            'czech' => 'Czech',
            'danish' => 'Danish',
            'dutch' => 'Dutch',
            'estonian' => 'Estonian',
            'farsi' => 'Persian',
            'finnish' => 'Finnish',
            'french' => 'French',
            'german' => 'German',
            'gujarati' => 'Gujarati',
            'greek' => 'Greek',
            'hebrew' => 'Hebrew',
            'hindi' => 'Hindi',
            'hungarian' => 'Hungarian',
            'kannada' => 'Kannada',
            'latvian' => 'Latvian',
            'lithuanian' => 'Lithuanian',
            'malayalam' => 'Malayalam',
            'marathi' => 'Marathi',
            'norwegian' => 'Norwegian',
            'polish' => 'Polish',
            'portuguese' => 'Portuguese',
            'romanian' => 'Romanian',
            'russian' => 'Russian',
            'serbian' => 'Serbian',
            'slovak' => 'Slovak',
            'slovenian' => 'Slovenian',
            'spanish' => 'Spanish',
            'swahili' => 'Swahili',
            'swedish' => 'Swedish',
            'tamil' => 'Tamil',
            'telugu' => 'Telugu',
            'thai' => 'Thai',
            'turkish' => 'Turkish',
            'ukrainian' => 'Ukrainian',
            'urdu' => 'Urdu',
            'vietnamese' => 'Vietnamese'
        ];
        
        // Ottieni il nome della lingua dalla mappatura o usa English come fallback
        $lang = isset($languages[$lang_code]) ? $languages[$lang_code] : 'English';
        
        // Log per debug
        error_log("ImgSEO Renamer: Using language '{$lang}' from setting '{$lang_code}'");
        
        // Ottieni le impostazioni AI
        $max_words = (int) get_option('imgseo_renamer_ai_max_words', 4);
        $include_post_title = (bool) get_option('imgseo_renamer_ai_include_post_title', 1);
        $include_category = (bool) get_option('imgseo_renamer_ai_include_category', 1);
        $include_alt_text = (bool) get_option('imgseo_renamer_ai_include_alt_text', 1);
        
        // Imposta il prompt base
        $prompt = "Rename the image in the format xxx-xxx-xxx-xxx-xxx to be SEO Optimized. Generate the filename in {$lang} language.";
        
        // Aggiungi il numero massimo di parole dalle impostazioni
        $prompt .= " Use exactly {$max_words} words in the new name.";
        
        // Includi informazioni contestuali se disponibili e abilitate
        if ($include_post_title && !empty($context['post_title'])) {
            $prompt .= " The image is related to this article title: \"{$context['post_title']}\".";
        }
        
        if ($include_category && !empty($context['category'])) {
            $prompt .= " The article category is: \"{$context['category']}\".";
        }
        
        if ($include_alt_text && !empty($context['alt_text'])) {
            $prompt .= " Current alt text of the image is: \"{$context['alt_text']}\".";
        }
        
        // Se c'è una didascalia, usala come contesto aggiuntivo
        if (!empty($context['caption'])) {
            $prompt .= " Image caption: \"{$context['caption']}\".";
        }
        
        // Aggiungi istruzioni specifiche sul formato richiesto
        $prompt .= " Return only the file name without the extension, without any additional comments, and avoid special characters. Use hyphens to separate words.";
        
        return $prompt;
    }
}
