<?php
/**
 * Classe per la generazione del testo alternativo
 * 
 * @package ImgSEO
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe ImgSEO_Alt_Text_Generator
 * Gestisce la generazione del testo alternativo per le immagini
 */
class ImgSEO_Alt_Text_Generator extends ImgSEO_Generator_Base {
    
    /**
     * Constructor - semplificato per evitare registrazioni multiple
     */
    public function __construct() {
        // Add handler for single alt text generation
        add_action('imgseo_single_generate', array($this, 'process_single_generate'), 10, 2);
        
        // Rimuoviamo la registrazione duplicata per evitare chiamate multiple
        // Il hook principale deve essere registrato solo una volta nel file principale
    }
    
    /**
     * Inizializza gli hook necessari - questo metodo deve essere chiamato solo una volta
     * dal file principale per evitare registrazioni multiple
     */
    public static function initialize_hooks() {
        // Registrazione centralizzata per evitare duplicati
        static $initialized = false;
        
        if ($initialized) {
            error_log('ImgSEO DEBUG: initialize_hooks già chiamato, ignoro duplicato');
            return;
        }
        
        $initialized = true;
        error_log('ImgSEO DEBUG: initialize_hooks eseguito - inizio registrazione');
        
        // Ottieni l'istanza singleton per la registrazione
        $instance = IMGSEO_Init::init()->generator->get_alt_text_generator();
        if (!$instance) {
            error_log('ImgSEO DEBUG: ERRORE CRITICO - impossibile ottenere istanza generator');
            return;
        }
        
        error_log('ImgSEO DEBUG: Istanza generator ottenuta: ' . (is_object($instance) ? get_class($instance) : 'non è un oggetto'));
        
        // Registra l'hook standard di WordPress per gli upload
        add_action('add_attachment', array($instance, 'auto_generate_alt_text'), 10);
        error_log('ImgSEO DEBUG: Hook add_attachment registrato per auto_generate_alt_text - priorità 10');
        
        // Registra anche un hook per upload API REST (e.g. Gutenberg)
        add_action('rest_insert_attachment', array($instance, 'auto_generate_alt_text'), 10);
        error_log('ImgSEO DEBUG: Hook rest_insert_attachment registrato per auto_generate_alt_text - priorità 10');
        
        // Registrazione debug sulla funzione di callback
        error_log('ImgSEO DEBUG: Funzione di callback: ' . (is_callable(array($instance, 'auto_generate_alt_text')) ? 'è callable' : 'NON è callable'));
    }
    
    /**
     * Generates alt text automatically when a new image is uploaded
     * Simple version without multiple attempts
     *
     * @param int $attachment_id Attachment ID
     */
    public function auto_generate_alt_text($attachment_id) {
        error_log('ImgSEO DEBUG: auto_generate_alt_text chiamato per ID: ' . $attachment_id);
        
        // Verifica che sia un'immagine
        if (!wp_attachment_is_image($attachment_id)) {
            error_log('ImgSEO DEBUG: ID ' . $attachment_id . ' non è un\'immagine, uscita');
            return;
        }
        
        // Verifica se la funzionalità è abilitata
        $auto_generate = get_option('imgseo_auto_generate', 0);
        error_log('ImgSEO DEBUG: auto_generate è: ' . ($auto_generate ? 'ATTIVO' : 'DISATTIVATO'));
        
        if (!$auto_generate) {
            error_log('ImgSEO DEBUG: Generazione automatica disattivata, ignorando');
            return;
        }
        
        // Verifica se esiste già un alt text
        $current_alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $overwrite = get_option('imgseo_overwrite', 0);
        error_log('ImgSEO DEBUG: Testo alternativo attuale: ' . ($current_alt_text ? '"'.$current_alt_text.'"' : 'NESSUNO') . ', overwrite: ' . $overwrite);
        
        if (!$overwrite && !empty($current_alt_text)) {
            error_log('ImgSEO DEBUG: Testo alternativo già presente e overwrite disattivato, ignoro');
            return;
        }
        
        // Verifica crediti disponibili
        $credits_exhausted = get_transient('imgseo_insufficient_credits');
        $credits = get_option('imgseo_credits', 0);
        error_log('ImgSEO DEBUG: Crediti disponibili: ' . $credits . ', esauriti: ' . ($credits_exhausted ? 'SÌ' : 'NO'));
        
        if ($credits_exhausted || $credits < 1) {
            error_log('ImgSEO DEBUG: Crediti insufficienti, generazione saltata');
            return;
        }
        
        // Previene multiple elaborazioni per la stessa immagine
        $processing_key = 'imgseo_processing_' . $attachment_id;
        if (get_transient($processing_key)) {
            error_log('ImgSEO DEBUG: Elaborazione già in corso per ID: ' . $attachment_id . ', ignoro');
            return;
        }
        
        // Imposta un lock di 60 secondi
        set_transient($processing_key, true, 60);
        
        try {
            // Nota: abbiamo rimosso il sleep(1) che poteva interrompere l'esecuzione
            
            // Ottieni l'URL dell'immagine
            $image_url = wp_get_attachment_url($attachment_id);
            if (!$image_url) {
                error_log('ImgSEO DEBUG: Impossibile ottenere URL per ID: ' . $attachment_id);
                return;
            }
            
            // Usa una versione ridotta se disponibile per migliorare le performance
            $sizes = array('large', 'medium_large', 'medium', 'thumbnail');
            foreach ($sizes as $size) {
                $image_data = wp_get_attachment_image_src($attachment_id, $size);
                if ($image_data && !empty($image_data[0])) {
                    $image_url = $image_data[0];
                    error_log('ImgSEO DEBUG: Usando versione ridotta ' . $size . ': ' . $image_url);
                    break;
                }
            }
            
            // Ottieni il titolo del post genitore se disponibile
            $parent_post_id = wp_get_post_parent_id($attachment_id);
            $parent_post_title = $parent_post_id ? get_the_title($parent_post_id) : '';
            
            // Genera il testo alternativo
            error_log('ImgSEO DEBUG: Avvio generazione per ID: ' . $attachment_id);
            $alt_text = $this->generate_alt_text($image_url, $attachment_id, $parent_post_title);
            
            if (is_wp_error($alt_text)) {
                error_log('ImgSEO DEBUG: Errore durante la generazione: ' . $alt_text->get_error_message());
                return;
            }
            
            // Aggiorna il testo alternativo
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
            error_log('ImgSEO DEBUG: Testo alternativo generato e salvato: "' . $alt_text . '"');
            
            // Aggiorna gli altri campi in base alle opzioni
            $update_title = get_option('imgseo_update_title', 0);
            $update_caption = get_option('imgseo_update_caption', 0);
            $update_description = get_option('imgseo_update_description', 0);
            
            if ($update_title || $update_caption || $update_description) {
                $attachment_data = array('ID' => $attachment_id);
                
                if ($update_title) {
                    $attachment_data['post_title'] = $alt_text;
                }
                
                if ($update_caption) {
                    $attachment_data['post_excerpt'] = $alt_text;
                }
                
                if ($update_description) {
                    $attachment_data['post_content'] = $alt_text;
                }
                
                // Sospendi hook temporaneamente per evitare ricorsione
                remove_action('attachment_updated', array(IMGSEO_Init::init(), 'handle_attachment_update'), 20);
                wp_update_post($attachment_data);
                add_action('attachment_updated', array(IMGSEO_Init::init(), 'handle_attachment_update'), 20);
                
                error_log('ImgSEO DEBUG: Altri campi aggiornati in base alle opzioni');
            }
        } catch (Exception $e) {
            error_log('ImgSEO DEBUG: Eccezione durante la generazione: ' . $e->getMessage());
        } finally {
            // Rimuovi il lock di elaborazione
            delete_transient($processing_key);
        }
    }
    
    /**
     * Handles AJAX request to generate alt text
     */
    public function handle_generate_alt_text() {
        check_ajax_referer('imgseo_nonce', 'security');
        
        // FIX: Handle both types of possible parameters
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) :
                       (isset($_POST['image_id']) ? intval($_POST['image_id']) : 0);
        
        if (!$attachment_id) {
            error_log('ImgSEO: Missing or invalid attachment ID in AJAX request');
            wp_send_json_error(['message' => 'Missing or invalid attachment ID']);
            return;
        }
        
        // Verify the attachment exists
        $attachment = get_post($attachment_id);
        if (!$attachment) {
            error_log('ImgSEO: Attachment not found with ID: ' . $attachment_id);
            wp_send_json_error(['message' => 'Attachment not found']);
            return;
        }
        
        // Verify it's an image
        if (!wp_attachment_is_image($attachment_id)) {
            error_log('ImgSEO: The attachment is not an image. ID: ' . $attachment_id);
            wp_send_json_error(['message' => 'The attachment is not an image']);
            return;
        }
        
        // Use smaller image size to optimize API call
        $image_url = null;
        $image_sizes = array('large', 'medium_large', 'medium', 'thumbnail');
        
        // Try to get a thumbnail version first
        foreach ($image_sizes as $size) {
            $image_size = wp_get_attachment_image_src($attachment_id, $size);
            if ($image_size && is_array($image_size) && !empty($image_size[0])) {
                $image_url = $image_size[0];
                error_log('ImgSEO AJAX: Usando URL ottimizzato (' . $size . '): ' . $image_url);
                break;
            }
        }
        
        // Fallback to original if no thumbnails available
        if (!$image_url) {
            $image_url = wp_get_attachment_url($attachment_id);
            error_log('ImgSEO AJAX: Usando URL originale: ' . $image_url);
        }
        
        if (!$image_url) {
            error_log('ImgSEO: URL immagine non trovato per ID: ' . $attachment_id);
            wp_send_json_error(['message' => 'URL immagine non trovato']);
            return;
        }
        
        // Verifica cache prima di procedere con la generazione
        $cached_result = get_transient('imgseo_alt_text_' . $attachment_id);
        if ($cached_result && !empty($cached_result) && !isset($_POST['force_refresh'])) {
            error_log('ImgSEO: Returning cached alt text for ID: ' . $attachment_id);
            
            // Crea un array per la risposta
            $response_data = [
                'alt_text' => $cached_result,
                'image_url' => $image_url,
                'filename' => basename($image_url),
                'cached' => true
            ];
            
            wp_send_json_success($response_data);
            return;
        }
        
        // Controllo dei crediti migliorato - verifica sia transient che valore numerico
        $credits_exhausted = get_transient('imgseo_insufficient_credits');
        $credits = get_option('imgseo_credits', 0);
        
        // Blocco rigoroso se crediti insufficienti (transient impostato o crediti < 1)
        if ($credits_exhausted || $credits < 1) {
            error_log('ImgSEO: Fast processing bloccato - crediti insufficienti: ' . $credits);
            
            // Imposta il transient se non è già impostato
            if (!$credits_exhausted) {
                set_transient('imgseo_insufficient_credits', true, 3600); // 1 ora
            }
            
            wp_send_json_error([
                'message' => 'Crediti ImgSEO insufficienti. Hai ' . $credits . ' crediti. Acquista altri crediti per continuare.',
                'filename' => basename($image_url),
                'error_type' => 'insufficient_credits'
            ]);
            return;
        }
        
        // Ottieni il titolo della pagina genitore se disponibile
        $parent_post_id = get_post_field('post_parent', $attachment_id);
        $parent_post_title = $parent_post_id ? get_the_title($parent_post_id) : '';
        
        // Ottieni il nome del file per i log
        $filename = basename($image_url);
        
        // Ottieni job_id se presente
        $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
        
        // Aggiungi un parametro univoco all'URL dell'immagine per evitare cache CDN
        $unique_image_url = add_query_arg('t', time(), $image_url);
        
        $alt_text = $this->generate_alt_text($unique_image_url, $attachment_id, $parent_post_title);
        
        if (is_wp_error($alt_text)) {
            error_log('ImgSEO: Error generating alt text: ' . $alt_text->get_error_message());
            wp_send_json_error([
                'message' => $alt_text->get_error_message(),
                'filename' => $filename
            ]);
            return;
        }
        
        // Crea un array per la risposta
        $response_data = [
            'alt_text' => $alt_text,
            'image_url' => $image_url,
            'page_title' => $parent_post_title,
            'filename' => $filename
        ];
        
        // Aggiorna il testo alternativo
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
        
        // Ottieni le opzioni di aggiornamento
        $update_title = isset($_POST['update_title']) ? (bool)$_POST['update_title'] : get_option('imgseo_update_title', 0);
        $update_caption = isset($_POST['update_caption']) ? (bool)$_POST['update_caption'] : get_option('imgseo_update_caption', 0);
        $update_description = isset($_POST['update_description']) ? (bool)$_POST['update_description'] : get_option('imgseo_update_description', 0);
        
        // Prepara l'array di aggiornamento
        $attachment_data = [
            'ID' => $attachment_id
        ];
        
        // Aggiorna in base alle opzioni
        if ($update_title) {
            $attachment_data['post_title'] = $alt_text;
            $response_data['title'] = $alt_text;
        }
        
        if ($update_caption) {
            $attachment_data['post_excerpt'] = $alt_text;
            $response_data['caption'] = $alt_text;
        }
        
        if ($update_description) {
            $attachment_data['post_content'] = $alt_text;
            $response_data['description'] = $alt_text;
        }
        
        // Se c'è almeno un campo da aggiornare, aggiorna l'allegato
        if (count($attachment_data) > 1) {
            $result = wp_update_post($attachment_data);
            
            if (is_wp_error($result)) {
                error_log('ImgSEO: Update error: ' . $result->get_error_message());
            }
        }
        
        // Se abbiamo un job_id, aggiungi un log dell'operazione
        if (!empty($job_id)) {
            global $wpdb;
            $log_table_name = $wpdb->prefix . 'imgseo_logs';
            
            // Crea la tabella dei log se non esiste
            $this->create_logs_table_if_not_exists();
            
            // Aggiungi il log
            $wpdb->insert(
                $log_table_name,
                [
                    'job_id' => $job_id,
                    'image_id' => $attachment_id,
                    'filename' => $filename,
                    'alt_text' => $alt_text,
                    'status' => 'success',
                    'created_at' => current_time('mysql')
                ]
            );
        }
        
        wp_send_json_success($response_data);
    }
    
    /**
     * Processa la generazione del testo alternativo per una singola immagine
     * Versione migliorata con protezione anti-ricorsione
     * Chiamato dal cron job pianificato da auto_generate_alt_text
     *
     * @param int $attachment_id ID dell'allegato
     * @param int $attempt_number Numero del tentativo (opzionale)
     */
    public function process_single_generate($attachment_id, $attempt_number = 1) {
        // ========== PROTEZIONE CONTRO RICORSIONE MIGLIORATA ===========
        // Verifica se c'è un lock attivo per evitare elaborazioni multiple simultanee
        $processing_lock = get_transient('imgseo_processing_' . $attachment_id);
        if ($processing_lock) {
            error_log('ImgSEO: [PROTECTION] Processing already in progress for ID: ' . $attachment_id . ', skipped');
            return;
        }
        
        // Imposta un lock temporaneo (30 secondi max per completare la generazione)
        // Usa la funzione di WordPress con terzo parametro = true per garantire che solo
        // un processo possa impostare il transient (protezione contro race condition)
        $lock_set = set_transient('imgseo_processing_' . $attachment_id, true, 30);
        
        // Se non siamo riusciti a impostare il lock, un altro processo potrebbe averlo fatto
        if (!$lock_set) {
            error_log('ImgSEO: [PROTEZIONE] Impossibile impostare lock per ID: ' . $attachment_id . ', potrebbe essere in corso altrove');
            return;
        }
        
        // Flag statico per evitare ricorsione all'interno dello stesso processo PHP
        static $processing_ids = array();
        if (isset($processing_ids[$attachment_id])) {
            error_log('ImgSEO: [PROTEZIONE] Ricorsione rilevata per ID: ' . $attachment_id . ', saltata');
            delete_transient('imgseo_processing_' . $attachment_id);
            return;
        }
        
        // Segna l'ID come in elaborazione
        $processing_ids[$attachment_id] = true;
        // ========== FINE PROTEZIONE RICORSIONE ===========
        
        error_log('ImgSEO: process_single_generate avviato per ID: ' . $attachment_id . ' (tentativo #' . $attempt_number . ')');
        
        try {
            // Controllo per evitare tentativi eccessivi - limitato a 2 tentativi come richiesto
            if ($attempt_number > 2) {
                error_log('ImgSEO: Massimo numero di tentativi raggiunto (2) per ID: ' . $attachment_id . ', abbandono');
                delete_post_meta($attachment_id, '_imgseo_pending_generation');
                return;
            }
            
            // Verifica se un altro tentativo ha già avuto successo
            $pending_generation = get_post_meta($attachment_id, '_imgseo_pending_generation', true);
            $current_alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
            
            // Se il flag non è più presente o abbiamo già un testo alternativo e non dobbiamo sovrascrivere, il processo è completato
            $overwrite = get_option('imgseo_overwrite', 0);
            if (empty($pending_generation) && !empty($current_alt_text) && !$overwrite) {
                error_log('ImgSEO: Testo alternativo già presente per ID: ' . $attachment_id . ', salta questo tentativo');
                return;
            }
            
            // Verifica che l'allegato esista
            $attachment = get_post($attachment_id);
            if (!$attachment) {
                error_log('ImgSEO: Allegato non trovato con ID: ' . $attachment_id);
                delete_post_meta($attachment_id, '_imgseo_pending_generation');
                return;
            }
            
            // Verifica che sia un'immagine (controllo multiplo)
            $is_image = wp_attachment_is_image($attachment_id);
            $mime_type = get_post_mime_type($attachment_id);
            $is_image_mime = strpos($mime_type, 'image/') === 0;
            
            if (!$is_image && !$is_image_mime) {
                error_log('ImgSEO: ID ' . $attachment_id . ' non è un\'immagine (controllo multiplo)');
                delete_post_meta($attachment_id, '_imgseo_pending_generation');
                return;
            }
            
            // Verifica crediti ImgSEO - controllo migliorato come negli altri punti
            $credits_exhausted = get_transient('imgseo_insufficient_credits');
            $credits = get_option('imgseo_credits', 0);
            
            // Controllo più rigoroso: crediti < 1 o transient impostato
            if ($credits_exhausted || $credits < 1) {
                error_log('ImgSEO: Crediti insufficienti per la generazione automatica: ' . $credits);
                
                // Imposta il transient se non è già impostato
                if (!$credits_exhausted) {
                    set_transient('imgseo_insufficient_credits', true, 3600); // 1 ora
                }
                
                delete_post_meta($attachment_id, '_imgseo_pending_generation');
                return;
            }
            
            // Optimized: prioritize using thumbnails for API processing
            // This reduces bandwidth usage and processing time
            
            // Define image sizes to try in priority order
            $image_sizes = array('large', 'medium_large', 'medium', 'thumbnail');
            
            $valid_url = null;
            
            // First try WordPress generated thumbnails in order of preference
            foreach ($image_sizes as $size) {
                $image_size = wp_get_attachment_image_src($attachment_id, $size);
                if ($image_size && is_array($image_size) && !empty($image_size[0])) {
                    $test_response = wp_remote_head($image_size[0], array('timeout' => 5, 'sslverify' => false));
                    
                    if (!is_wp_error($test_response) && wp_remote_retrieve_response_code($test_response) === 200) {
                        $valid_url = $image_size[0];
                        error_log('ImgSEO: URL ottimizzato (' . $size . ') trovato e verificato: ' . $valid_url);
                        break;
                    }
                }
            }
            
            // Fallback to original if no thumbnails available
            if (!$valid_url) {
                $image_original = wp_get_attachment_url($attachment_id);
                if ($image_original) {
                    $test_response = wp_remote_head($image_original, array('timeout' => 5, 'sslverify' => false));
                    
                    if (!is_wp_error($test_response) && wp_remote_retrieve_response_code($test_response) === 200) {
                        $valid_url = $image_original;
                        error_log('ImgSEO: URL originale utilizzato come fallback: ' . $valid_url);
                    }
                }
            }
            
            if (!$valid_url) {
                error_log('ImgSEO: Nessun URL immagine accessibile per ID: ' . $attachment_id);
                // Ripianifica solo se siamo sotto il limite di tentativi
                if ($attempt_number < 4) {
                    error_log('ImgSEO: Tentativo #' . $attempt_number . ' fallito, riprogrammo per più tardi');
                    if (!wp_next_scheduled('imgseo_single_generate', array($attachment_id, $attempt_number + 1))) {
                        wp_schedule_single_event(time() + 30, 'imgseo_single_generate', array($attachment_id, $attempt_number + 1));
                    }
                } else {
                    delete_post_meta($attachment_id, '_imgseo_pending_generation');
                }
                return;
            }
            
            // Ottieni il titolo della pagina genitore se disponibile
            $parent_post_id = get_post_field('post_parent', $attachment_id);
            $parent_post_title = $parent_post_id ? get_the_title($parent_post_id) : '';
            
            // Ottieni anche il titolo dell'allegato per contesto aggiuntivo
            $attachment_title = get_the_title($attachment_id);
            
            // Genera il testo alternativo
            error_log('ImgSEO: Generazione testo alt per ID ' . $attachment_id . ' con URL: ' . $valid_url);
            $alt_text = $this->generate_alt_text($valid_url, $attachment_id, $parent_post_title);
            
            if (is_wp_error($alt_text)) {
                $error_message = $alt_text->get_error_message();
                error_log('ImgSEO: Error generating alt text for ID ' . $attachment_id . ': ' . $error_message);
                
                // Ripianifica solo se è un errore temporaneo e siamo sotto il limite di tentativi
                if (($attempt_number < 4) && 
                    (strpos($error_message, 'elaborare l\'immagine') !== false || 
                     strpos($error_message, 'timeout') !== false || 
                     strpos($error_message, 'temporaneo') !== false)) {
                    
                    // Incremento esponenziale del tempo di attesa tra tentativi
                    $wait_time = 30 * pow(2, $attempt_number - 1);
                    error_log('ImgSEO: Riprogrammo generazione tra ' . $wait_time . ' secondi (tentativo #' . ($attempt_number + 1) . ')');
                    if (!wp_next_scheduled('imgseo_single_generate', array($attachment_id, $attempt_number + 1))) {
                        wp_schedule_single_event(time() + $wait_time, 'imgseo_single_generate', array($attachment_id, $attempt_number + 1));
                    }
                } else {
                    // Dopo troppi tentativi o errore permanente, rimuovi il flag di pending
                    delete_post_meta($attachment_id, '_imgseo_pending_generation');
                }
                return;
            }
            
            // SOSPENDI I HOOK temporaneamente per evitare ricorsione
            $suspended_meta_hook = remove_action('updated_post_meta', array(IMGSEO_Init::init(), 'check_image_alt_on_meta_update'), 15);
            
            // Aggiorna il testo alternativo con strategia a doppio passaggio
            try {
                // Fase 1: Rimuovi eventuale valore esistente
                delete_post_meta($attachment_id, '_wp_attachment_image_alt');
                
                // Aggiungi un piccolo ritardo tra operazioni
                usleep(100000); // 0.1 secondi
                
                // Fase 2: Aggiungi il nuovo valore
                add_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
                
                // Fase 3: Forza l'aggiornamento con update_post_meta
                update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
                
                // Verifica che l'aggiornamento sia avvenuto con successo
                $updated_alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                if ($updated_alt_text !== $alt_text) {
                    error_log('ImgSEO: Impossibile aggiornare meta, tentativo forzato');
                    // Ultimo tentativo forzato
                    update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text, '');
                }
            } finally {
                // RIPRISTINA I HOOK anche in caso di errori
                if ($suspended_meta_hook) {
                    add_action('updated_post_meta', array(IMGSEO_Init::init(), 'check_image_alt_on_meta_update'), 15, 4);
                }
            }
            
            // Rimuovi il flag che indica che l'immagine è in attesa di generazione
            delete_post_meta($attachment_id, '_imgseo_pending_generation');
            
            error_log('ImgSEO: Testo alt aggiornato con successo per ID ' . $attachment_id . ': "' . $alt_text . '"');
            
            // Aggiungi un breve lock di 5 secondi per evitare ulteriori aggiornamenti immediati
            set_transient('imgseo_alt_updated_' . $attachment_id, true, 5);
            
            // Aggiorna gli altri campi in base alle opzioni
            $update_title = get_option('imgseo_update_title', 0);
            $update_caption = get_option('imgseo_update_caption', 0);
            $update_description = get_option('imgseo_update_description', 0);
            
            $attachment_data = ['ID' => $attachment_id];
            $updates_made = false;
            
            if ($update_title) {
                $attachment_data['post_title'] = $alt_text;
                $updates_made = true;
            }
            
            if ($update_caption) {
                $attachment_data['post_excerpt'] = $alt_text;
                $updates_made = true;
            }
            
            if ($update_description) {
                $attachment_data['post_content'] = $alt_text;
                $updates_made = true;
            }
            
            if ($updates_made) {
                // SOSPENDI ANCHE QUI GLI HOOK TEMPORANEAMENTE
                $suspended_hook = remove_action('attachment_updated', array(IMGSEO_Init::init(), 'handle_attachment_update'), 20);
                
                try {
                    $result = wp_update_post($attachment_data);
                    
                    if (is_wp_error($result)) {
                        error_log('ImgSEO: Error updating additional metadata: ' . $result->get_error_message());
                    } else {
                        error_log('ImgSEO: Metadati aggiuntivi aggiornati con successo per ID: ' . $attachment_id);
                    }
                } finally {
                    // RIPRISTINA GLI HOOK ANCHE IN CASO DI ERRORI
                    if ($suspended_hook) {
                        add_action('attachment_updated', array(IMGSEO_Init::init(), 'handle_attachment_update'), 20);
                    }
                }
            }
        } catch (Exception $e) {
            error_log('ImgSEO: Eccezione durante la generazione del testo alt: ' . $e->getMessage());
        } finally {
            // PULIZIA FINALE - sempre eseguita
            
            // Marca questo ID come non più in elaborazione
            unset($processing_ids[$attachment_id]);
            
            // Rimuovi il lock
            delete_transient('imgseo_processing_' . $attachment_id);
        }
    }
}
