<?php
/**
 * Class ImgSEO_API
 * Manages all interactions with the ImgSEO API for alt text generation
 */

// FIX: Add a check to avoid class redeclarations
if (!class_exists('ImgSEO_API')) {

class ImgSEO_API {
    /**
     * ImgSEO API Endpoint
     */
    const API_ENDPOINT = 'https://api.imgseo.net';
    
    /**
     * ImgSEO Token
     *
     * @var string
     */
    private $api_key;
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Ottiene la chiave API corrente
     * 
     * @return string La chiave API
     */
    public function get_api_key() {
        return $this->api_key;
    }
    
    /**
     * Constructor
     *
     * @param string $api_key API Key (optional, otherwise taken from options)
     */
    private function __construct($api_key = null) {
        if ($api_key === null) {
            $this->api_key = get_option('imgseo_api_key', '');
        } else {
            $this->api_key = $api_key;
        }
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
     * Verifies if the API key is valid via AJAX
     */
    public function ajax_verify_api_key() {
        check_ajax_referer('imgseo_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', IMGSEO_TEXT_DOMAIN)]);
        }
        
        // Security: Rate limiting - max 5 attempts per 10 minutes per user
        $user_id = get_current_user_id();
        $rate_key = 'imgseo_api_verify_attempts_' . $user_id;
        $attempts = get_transient($rate_key) ?: 0;
        
        if ($attempts >= 5) {
            wp_send_json_error(['message' => __('Too many verification attempts. Please wait 10 minutes.', IMGSEO_TEXT_DOMAIN)]);
        }
        
        set_transient($rate_key, $attempts + 1, 600); // 10 minutes
        
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        
        if (empty($api_key)) {
            wp_send_json_error(['message' => __('ImgSEO Token not provided', IMGSEO_TEXT_DOMAIN)]);
        }
        
        // Security: Validate API key format (basic validation)
        if (strlen($api_key) < 10 || strlen($api_key) > 100) {
            wp_send_json_error(['message' => __('Invalid ImgSEO Token format', IMGSEO_TEXT_DOMAIN)]);
        }
        
        // Security: Check for malicious patterns
        if (preg_match('/[<>"\']/', $api_key)) {
            wp_send_json_error(['message' => __('Invalid ImgSEO Token format', IMGSEO_TEXT_DOMAIN)]);
        }
        
        // Verify the API key - create a new instance with the specified API key
        $api = new self($api_key);  // We use self to maintain access to the private constructor within the class
        $account_details = $api->verify_api_key();
        
        if ($account_details !== false) {
            // Save the API key and account details
            update_option('imgseo_api_key', $api_key);
            update_option('imgseo_api_verified', true);
            
            wp_send_json_success([
                'message' => __('ImgSEO Token verified successfully!', IMGSEO_TEXT_DOMAIN),
                'plan' => $account_details['plan'],
                'credits' => $account_details['available']
            ]);
        } else {
            update_option('imgseo_api_verified', false);
            wp_send_json_error(['message' => __('Invalid ImgSEO Token', IMGSEO_TEXT_DOMAIN)]);
        }
    }
    
    /**
     * Updates available credits via AJAX
     */
    public function ajax_refresh_credits() {
        check_ajax_referer('imgseo_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', IMGSEO_TEXT_DOMAIN)]);
        }
        
        // Dalla documentazione, c'è un endpoint dedicato per controllare i crediti
        // che non consuma crediti aggiuntivi
        $credits_check = wp_remote_get(
            self::API_ENDPOINT . '/credits',
            array(
                'headers' => array(
                    'Accept' => 'application/json',
                    'imgseo-token' => $this->api_key
                ),
                'timeout' => 15
            )
        );
        
        $credits = 0;
        $update_success = false;
        
        if (!is_wp_error($credits_check)) {
            $response_code = wp_remote_retrieve_response_code($credits_check);
            $response_body = json_decode(wp_remote_retrieve_body($credits_check), true);
            
            // Log safe response info without sensitive data
            error_log('ImgSEO API: Credits response received with code: ' . $response_code);
            
            if ($response_code === 200) {
                if (isset($response_body['credits_remaining'])) {
                    $credits = intval($response_body['credits_remaining']);
                    update_option('imgseo_credits', $credits);
                    update_option('imgseo_last_check', time());
                    $update_success = true;
                    
                    error_log('ImgSEO API: Credits updated successfully: ' . $credits);
                    
                    // Check if credits are sufficient
                    if ($credits <= 0) {
                        set_transient('imgseo_insufficient_credits', true, 3600);
                    } else {
                        delete_transient('imgseo_insufficient_credits');
                    }
                }
            }
        } else {
            error_log('ImgSEO API: Error checking credits - ' . $credits_check->get_error_message());
        }
        
        if ($update_success) {
            $last_check_time = human_time_diff(time(), time() + 1) . ' ' . __('ago', IMGSEO_TEXT_DOMAIN);
            
            wp_send_json_success([
                'credits' => $credits,
                'last_check' => $last_check_time,
                'message' => __('Credits updated successfully!', IMGSEO_TEXT_DOMAIN)
            ]);
        } else {
            // In caso di errore utilizziamo i crediti memorizzati
            $credits = get_option('imgseo_credits', 0);
            $last_check_time = human_time_diff(get_option('imgseo_last_check', time()), time()) . ' ' . __('ago', IMGSEO_TEXT_DOMAIN);
            
            wp_send_json_success([
                'credits' => $credits,
                'last_check' => $last_check_time,
                'message' => __('Using saved credits information.', IMGSEO_TEXT_DOMAIN)
            ]);
        }
    }
    
    /**
     * Handles API disconnection via AJAX
     */
    public function ajax_disconnect_api() {
        check_ajax_referer('imgseo_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', IMGSEO_TEXT_DOMAIN)]);
        }
        
        // Remove API options
        delete_option('imgseo_api_key');
        delete_option('imgseo_api_verified');
        delete_option('imgseo_credits');
        delete_option('imgseo_plan');
        delete_option('imgseo_last_check');
        
        wp_send_json_success(['message' => __('API disconnected successfully', IMGSEO_TEXT_DOMAIN)]);
    }
    
    /**
     * Verifies if the API key is valid
     *
     * @return bool|array False if invalid, or array with account details
     */
    public function verify_api_key() {
        $account_details = $this->get_account_details();
        return $account_details !== false ? $account_details : false;
    }
    
    /**
     * Gets account details, including available credits
     * Ottimizzato per ridurre le chiamate API ridondanti
     *
     * @return array|bool False in case of error, or array with account details
     */
    public function get_account_details() {
        if (empty($this->api_key)) {
            return false;
        }
        
        // Verifica se c'è una cache recente per evitare richieste multiple ravvicinate
        $cache_key = 'imgseo_account_details_' . md5($this->api_key);
        $cached_details = get_transient($cache_key);
        
        // Usa la cache se disponibile e fresca (5 minuti)
        if ($cached_details !== false) {
            return $cached_details;
        }
        
        // Ottimizzazione: facciamo solo UNA chiamata all'endpoint /credits
        // che può verificare sia la validità del token che recuperare i crediti
        $credits_response = wp_remote_get(
            self::API_ENDPOINT . '/credits',
            array(
                'headers' => array(
                    'Accept' => 'application/json',
                    'imgseo-token' => $this->api_key
                ),
                'timeout' => 15
            )
        );
        
        // Prepara i dettagli dell'account
        $account_details = array();
        $account_details['plan'] = "ImgSEO Plan"; // Valore di default
        $account_details['expires_at'] = 'never';
        
        // Verifica errori di connessione
        if (is_wp_error($credits_response)) {
            error_log('ImgSEO API: Connection error - ' . $credits_response->get_error_message());
            
            // Usa i valori memorizzati come fallback
            $account_details['available'] = get_option('imgseo_credits', 0);
            return $account_details;
        }
        
        $response_code = wp_remote_retrieve_response_code($credits_response);
        
        // Verifica di validità token
        if ($response_code === 401) {
            error_log('ImgSEO API: Invalid token - Code 401');
            return false;
        }
        
        // Verifica del successo della richiesta
        if ($response_code !== 200) {
            error_log('ImgSEO API: Unexpected response - Code ' . $response_code);
            
            // Usa i valori memorizzati come fallback
            $account_details['available'] = get_option('imgseo_credits', 0);
            return $account_details;
        }
        
        // Elabora la risposta
        $credits_body = json_decode(wp_remote_retrieve_body($credits_response), true);
        
        if (isset($credits_body['credits_remaining'])) {
            $account_details['available'] = intval($credits_body['credits_remaining']);
            error_log('ImgSEO API: Credits fetched from endpoint: ' . $account_details['available']);
        } else {
            // Se l'endpoint non restituisce i crediti, utilizza quelli memorizzati
            $account_details['available'] = get_option('imgseo_credits', 0);
            error_log('ImgSEO API: Credits not found in response, using stored: ' . $account_details['available']);
        }
        
        // Store credits in WordPress options
        update_option('imgseo_credits', $account_details['available']);
        update_option('imgseo_plan', $account_details['plan']);
        update_option('imgseo_last_check', time());
        
        // Check if credits are sufficient
        if ($account_details['available'] <= 0) {
            set_transient('imgseo_insufficient_credits', true, 3600);
        } else {
            delete_transient('imgseo_insufficient_credits');
        }
        
        // Salva la cache per 5 minuti per evitare troppe chiamate ripetute
        set_transient($cache_key, $account_details, 5 * MINUTE_IN_SECONDS);
        
        return $account_details;
    }
    
    /**
     * Generate alt text for an image using the ImgSEO API
     * Versione migliorata con protezione contro chiamate multiple
     *
     * @param string $image_url The image URL
     * @param string $prompt The prompt for text generation
     * @param array $options Additional options for the API request
     * @return array|WP_Error API response or WP_Error in case of error
     */
    public function generate_alt_text($image_url, $prompt, $options = array()) {
       $attachment_id = isset($options['attachment_id']) ? intval($options['attachment_id']) : 0;
       
       // Genera un ID univoco basato su URL e attachment ID
       $request_key = md5($image_url . '_' . $attachment_id);
       
       // Sistema di lock semplificato per evitare elaborazioni duplicate ma non troppo restrittivo
       if ($attachment_id > 0) {
           $lock_key = 'imgseo_processing_' . $attachment_id;
           
           // Verifica se esiste un lock recente (ultimi 15 secondi)
           $processing_lock = get_transient($lock_key);
           $lock_time = $processing_lock ? (int)$processing_lock : 0;
           $time_diff = time() - $lock_time;
           
           // Se il lock esiste ma è vecchio (più di 15 secondi), lo consideriamo scaduto
           // Questo impedisce lock "zombi" che non vengono rilasciati
           if ($processing_lock && $time_diff < 15) {
               $last_result = get_transient('imgseo_last_result_' . $attachment_id);
               
               // Se abbiamo un risultato recente, lo restituiamo invece di generarne uno nuovo
               if ($last_result) {
                   error_log('ImgSEO API: Returning cached result for attachment ID: ' . $attachment_id);
                   return $last_result;
               }
               
               // Non usiamo più un errore per non bloccare il processo
               error_log('ImgSEO API: Nota: richiesta in corso per ID: ' . $attachment_id . ' da ' . $time_diff . ' secondi');
           }
           
           // Imposta un lock con timestamp corrente (per calcolare il tempo passato)
           set_transient($lock_key, time(), 30);
           error_log('ImgSEO API: Processing lock set for attachment ID: ' . $attachment_id);
       }
       
       if (empty($this->api_key)) {
           if ($attachment_id > 0) {
               delete_transient('imgseo_processing_' . $attachment_id);
           }
           return new WP_Error('api_key_missing', 'ImgSEO Token not provided');
       }
       
       // Funzione per il cleanup dei transient in caso di errore
       $cleanup = function() use ($attachment_id) {
           if ($attachment_id > 0) {
               // Rilascia entrambi i lock (locale e globale)
               delete_transient('imgseo_processing_' . $attachment_id);
               delete_transient('imgseo_global_processing_' . $attachment_id);
               error_log('ImgSEO API: All locks released for attachment ID: ' . $attachment_id);
           }
       };
       
       // Controllo dei crediti: blocchiamo definitivamente se crediti insufficienti
       $credits_exhausted = get_transient('imgseo_insufficient_credits');
       $credits = get_option('imgseo_credits', 0);
       
       // Controllo più rigoroso: se siamo a zero o sotto, o se abbiamo già impostato il flag
       if ($credits_exhausted || $credits < 1) {
           // Aggiorniamo il flag se non è già impostato
           if (!$credits_exhausted) {
               set_transient('imgseo_insufficient_credits', true, 3600); // 1 ora
           }
           error_log('ImgSEO API: Operazione bloccata - crediti insufficienti: ' . $credits);
           $cleanup();
           return new WP_Error('insufficient_credits', 'Crediti ImgSEO insufficienti. Acquista altri crediti per continuare.');
       }
       
       // Log informativo sui crediti disponibili
       error_log('ImgSEO API: Crediti disponibili prima dell\'operazione: ' . $credits);
       
       try {
           // Check if we should always use the base64 method (bypass hotlinking protections and Cloudflare)
           $always_use_base64 = get_option('imgseo_always_use_base64', 0);
           
           // If always_use_base64 is enabled, skip the URL method and go straight to base64
           if ($always_use_base64) {
               error_log('ImgSEO API: Using base64 method directly as per settings (imgseo_always_use_base64 enabled)');
               return $this->generate_alt_text_with_base64($image_url, $prompt, $options);
           }
           
           // Check if the site is offline or online
           $is_offline = $this->is_site_offline();
           
           // Set request parameters according to new API format
           $body = array(
               'image_url' => $image_url,
               'prompt' => $prompt,
               'lang' => isset($options['lang']) ? $options['lang'] : 'it',
               'optimize' => isset($options['optimize']) ? (bool)$options['optimize'] : true,
               // Aggiungiamo un parametro univoco per evitare caching non voluto
               'request_id' => uniqid('req_')
           );
       
           // If the site is offline, use file content instead of URL
           if ($is_offline) {
               try {
                   // Get file path from WordPress upload system
                   $upload_dir = wp_upload_dir();
                   $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_url);
                   
                   // Check if file exists
                   if (!file_exists($file_path)) {
                       $cleanup();
                       return new WP_Error('file_not_found', 'Image file not found');
                   }
                   
                   // Per il momento, se il sito è offline, non possiamo processare 
                   // l'immagine direttamente poiché la nuova API non supporta
                   // l'invio diretto di file in base64
                   $cleanup();
                   return new WP_Error('offline_site', 'La nuova API richiede che le immagini siano accessibili tramite URL pubblici. Il sito appare essere offline.');
               } catch (Exception $e) {
                   $cleanup();
                   return new WP_Error('file_processing_error', "Error processing the file: " . $e->getMessage());
               }
           }
        
           error_log('ImgSEO API: Sending request to generate alt text for attachment ID: ' . $attachment_id);
           
           // Execute API request with new format
           $response = wp_remote_post(
               self::API_ENDPOINT . '/genera-alt-text',
               array(
                   'headers' => array(
                       'Content-Type' => 'application/json',
                       'Accept' => 'application/json',
                       'imgseo-token' => $this->api_key,
                       // Aggiungiamo un header univoco per evitare caching della CDN
                       'X-Request-ID' => uniqid()
                   ),
                   'body' => wp_json_encode($body),
                   'timeout' => 30
               )
           );
           
           // Check for connection errors
           if (is_wp_error($response)) {
               $error_message = $response->get_error_message();
               error_log('ImgSEO API: Connection error - ' . $error_message);
               $cleanup();
               return $response;
           }
           
           // Check response code
           $response_code = wp_remote_retrieve_response_code($response);
           if ($response_code !== 200 && $response_code !== 201) {
               // Ottieni il corpo della risposta per analizzarlo
               $response_body_text = wp_remote_retrieve_body($response);
               error_log('ImgSEO API: Risposta non-200 ricevuta. Codice: ' . $response_code . ', Corpo: ' . $response_body_text);
               $response_body_json = json_decode($response_body_text, true);
               
               // Verifica se è un errore che può beneficiare del fallback con base64
               $should_try_fallback = false;
               
               // Errore 403 (protezione hotlinking)
               if ($response_code === 403) {
                   error_log('ImgSEO API: Rilevata protezione hotlinking (403 Forbidden). Tentativo di fallback al metodo base64.');
                   $should_try_fallback = true;
               }
               // Errore 5xx (problemi lato server)
               elseif ($response_code >= 500) {
                   error_log('ImgSEO API: Rilevato errore server ' . $response_code . '. Tentativo di fallback al metodo base64.');
                   $should_try_fallback = true;
               }
               // Errore 400 che potrebbe contenere errori server (come 520 Cloudflare) nel corpo
               elseif ($response_code === 400 && $response_body_json) {
                   // Verifica per errore Cloudflare 520 o altri errori server nel corpo JSON
                   if (
                       (isset($response_body_json['status_code']) && ($response_body_json['status_code'] >= 500 || $response_body_json['status_code'] == 520)) ||
                       (isset($response_body_json['error']) && strpos(strtolower($response_body_json['error']), 'server error') !== false)
                   ) {
                       $status_code = isset($response_body_json['status_code']) ? $response_body_json['status_code'] : 'sconosciuto';
                       error_log('ImgSEO API: Rilevato errore server (codice ' . $status_code . ') all\'interno di risposta 400. Tentativo di fallback al metodo base64.');
                       $should_try_fallback = true;
                   }
               }
               // Altri errori server inclusi nel corpo della risposta
               elseif ($response_body_json && (
                   (isset($response_body_json['status_code']) && ($response_body_json['status_code'] >= 500 || $response_body_json['status_code'] == 520)) ||
                   (isset($response_body_json['error']) && strpos(strtolower($response_body_json['error']), 'server error') !== false)
               )) {
                   error_log('ImgSEO API: Rilevato errore server nel corpo della risposta (codice ' .
                       (isset($response_body_json['status_code']) ? $response_body_json['status_code'] : 'sconosciuto') .
                       '). Tentativo di fallback al metodo base64.');
                   $should_try_fallback = true;
               }
               
               // Se abbiamo identificato un caso in cui il fallback è appropriato, proviamo
               if ($should_try_fallback) {
                   // Tentativo di fallback con base64
                   $base64_result = $this->generate_alt_text_with_base64($image_url, $prompt, $options);
                   
                   // Se il fallback ha avuto successo, restituisci il risultato
                   if (!is_wp_error($base64_result)) {
                       $cleanup();
                       return $base64_result;
                   }
                   
                   // Se anche il fallback fallisce, log dell'errore specifico
                   error_log('ImgSEO API: Anche il fallback base64 è fallito: ' . $base64_result->get_error_message());
               }
               
               // Gestione degli errori originale per altri codici di errore
               $error_message = wp_remote_retrieve_body($response);
               error_log('ImgSEO API: Invalid response - Code ' . $response_code . ' - ' . $error_message);
               $cleanup();
               return new WP_Error('api_error', 'API Error (' . $response_code . '): ' . $error_message);
           }
           
           // Decode response
           $response_body = json_decode(wp_remote_retrieve_body($response), true);
           
           // Verifica che la risposta contenga l'alt text
           if (!isset($response_body['alt_text'])) {
               error_log('ImgSEO API: Missing alt_text in response');
               $cleanup();
               return new WP_Error('invalid_response', 'Risposta API non valida: manca alt_text');
           }
           
           // Estrai i crediti residui se disponibili
           $remaining_credits = isset($response_body['credits_remaining']) 
               ? intval($response_body['credits_remaining']) 
               : null;
           
           // Aggiorna i crediti se disponibili nella risposta
           if ($remaining_credits !== null) {
               update_option('imgseo_credits', $remaining_credits);
               
               // If credits are exhausted, set a warning
               if ($remaining_credits <= 0) {
                   set_transient('imgseo_insufficient_credits', true, 3600);
               } else {
                   delete_transient('imgseo_insufficient_credits');
               }
           } else {
               // Se non riceviamo i crediti, diminuiamo manualmente di 1
               $credits = get_option('imgseo_credits', 0);
               update_option('imgseo_credits', max(0, $credits - 1));
               
               if ($credits - 1 <= 0) {
                   set_transient('imgseo_insufficient_credits', true, 3600);
               }
           }
           
           // Cache il risultato per un breve periodo (30 secondi)
           // così possiamo restituirlo in caso di richieste duplicate
           if ($attachment_id > 0) {
               set_transient('imgseo_last_result_' . $attachment_id, $response_body, 30);
           }
           
           error_log('ImgSEO API: Alt text generated successfully for attachment ID: ' . $attachment_id);
           
           // Release the lock
           $cleanup();
           
           return $response_body;
       } catch (Exception $e) {
           error_log('ImgSEO API: Exception during alt text generation: ' . $e->getMessage());
           $cleanup(); // Assicurati che il lock venga rimosso anche in caso di eccezione
           throw $e; // Rilancia l'eccezione per gestione errori superiore
       }
    }
    
   /**
    * Genera alt text utilizzando il metodo base64 con thumbnail ottimizzate
    *
    * @param string $image_url URL dell'immagine originale
    * @param string $prompt Il prompt per la generazione
    * @param array $options Opzioni aggiuntive
    * @return array|WP_Error Risposta API o errore
    */
   private function generate_alt_text_with_base64($image_url, $prompt, $options = array()) {
       $attachment_id = isset($options['attachment_id']) ? intval($options['attachment_id']) : 0;
       
       try {
           // Se abbiamo un ID allegato valido, utilizziamo le thumbnail di WordPress
           if ($attachment_id > 0) {
               // Tenta di ottenere la migliore thumbnail disponibile
               list($thumbnail_url, $thumbnail_path) = $this->get_best_thumbnail($attachment_id);
               
               if ($thumbnail_path && file_exists($thumbnail_path)) {
                   error_log('ImgSEO API: Utilizzo thumbnail per base64: ' . basename($thumbnail_path));
                   $image_path = $thumbnail_path;
               } else {
                   // Fallback all'immagine originale
                   $image_path = $this->get_local_path_from_url($image_url);
                   error_log('ImgSEO API: Thumbnail non disponibile, utilizzo immagine originale per base64');
               }
           } else {
               // Senza ID allegato, utilizziamo l'URL originale
               $image_path = $this->get_local_path_from_url($image_url);
           }
           
           // Verifica che il file esista
           if (!file_exists($image_path)) {
               return new WP_Error('file_not_found', 'Unable to find local image file for base64 fallback');
           }
           
           // Verifica che il formato dell'immagine sia supportato
           $mime_type = mime_content_type($image_path);
           $supported_formats = array(
               'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'
           );
           
           if (!in_array($mime_type, $supported_formats)) {
               if ($mime_type === 'image/svg+xml') {
                   error_log('ImgSEO API: File SVG non supportato dall\'API: ' . $image_path);
                   return new WP_Error('unsupported_format', 'SVG files are not supported by the ImgSEO API. Consider converting them to PNG format.');
               } else {
                   error_log('ImgSEO API: Formato immagine non supportato: ' . $mime_type . ' - Path: ' . $image_path);
                   return new WP_Error('unsupported_format', 'Unsupported image format: ' . $mime_type);
               }
           }
           
           // Verifica la dimensione del file
           $file_size = filesize($image_path);
           $max_size_warning = 1 * 1024 * 1024; // 1MB come soglia di avviso
           
           if ($file_size > $max_size_warning) {
               error_log('ImgSEO API: ATTENZIONE - Immagine grande (' . round($file_size / 1024, 2) . ' KB) - Path: ' . basename($image_path));
           } else {
               error_log('ImgSEO API: Dimensione immagine accettabile: ' . round($file_size / 1024, 2) . ' KB - Path: ' . basename($image_path));
           }
           
           // Converti l'immagine in base64
           $base64_data = $this->get_image_as_base64($image_path);
           if (empty($base64_data)) {
               return new WP_Error('base64_conversion_failed', 'Error converting the image to base64');
           }
           
           // Prepara i dati per l'API con base64 invece dell'URL
           $body = array(
               'image_data' => $base64_data,
               'mime_type' => mime_content_type($image_path), // Aggiungiamo il tipo MIME separatamente
               'prompt' => $prompt,
               'lang' => isset($options['lang']) ? $options['lang'] : 'it',
               'optimize' => isset($options['optimize']) ? (bool)$options['optimize'] : true,
               'request_id' => uniqid('req_')
           );
           
           error_log('ImgSEO API: Invio immagine con metodo base64 per attachment ID: ' . $attachment_id);
           
           // Esegui la richiesta API con gli stessi endpoint ma dati diversi
           $response = wp_remote_post(
               self::API_ENDPOINT . '/genera-alt-text',  // Stesso endpoint, cambiano solo i dati
               array(
                   'headers' => array(
                       'Content-Type' => 'application/json',
                       'Accept' => 'application/json',
                       'imgseo-token' => $this->api_key,
                       'X-Request-ID' => uniqid()
                   ),
                   'body' => wp_json_encode($body),
                   'timeout' => 45  // Timeout più lungo per le richieste base64
               )
           );
           
           // Check for connection errors
           if (is_wp_error($response)) {
               $error_message = $response->get_error_message();
               error_log('ImgSEO API (base64): Connection error - ' . $error_message);
               return $response;
           }
           
           // Check response code
           $response_code = wp_remote_retrieve_response_code($response);
           if ($response_code !== 200 && $response_code !== 201) {
               $error_message = wp_remote_retrieve_body($response);
               error_log('ImgSEO API (base64): Invalid response - Code ' . $response_code . ' - ' . $error_message);
               
               // Log più dettagliato in base al tipo di errore
               $error_type = 'api_error';
               $error_message_user = 'API Error (' . $response_code . '): ' . $error_message;
               
               // Gestione specifica errore 413 (Payload Too Large)
               if ($response_code === 413) {
                   error_log('ImgSEO API (base64): Immagine troppo grande per API - Dimensione: ' . round(filesize($image_path) / 1024, 2) . ' KB - Path: ' . basename($image_path));
                   $error_type = 'image_too_large';
                   $error_message_user = 'Image too large for the API (max ~10MB). Try optimizing the image.';
               }
               // Log per errori di formato immagine
               else if ($response_json = json_decode($error_message, true)) {
                   if (isset($response_json['error']) &&
                       (strpos($response_json['error'], 'Invalid image data') !== false ||
                       strpos($response_json['details'], 'unsupported image format') !== false)) {
                       
                       error_log('ImgSEO API (base64): Formato immagine non supportato - MIME: ' . mime_content_type($image_path) .
                               ' - Dimensione: ' . round(filesize($image_path) / 1024, 2) . ' KB' .
                               ' - Path: ' . $image_path);
                   }
               }
               
               return new WP_Error($error_type, $error_message_user);
           }
           
           // Decode response
           $response_body = json_decode(wp_remote_retrieve_body($response), true);
           
           // Verifica che la risposta contenga l'alt text
           if (!isset($response_body['alt_text'])) {
               error_log('ImgSEO API (base64): Missing alt_text in response');
               return new WP_Error('invalid_response', 'Invalid API response: alt_text is missing');
           }
           
           // Estrai i crediti residui se disponibili
           $remaining_credits = isset($response_body['credits_remaining'])
               ? intval($response_body['credits_remaining'])
               : null;
           
           // Aggiorna i crediti se disponibili nella risposta
           if ($remaining_credits !== null) {
               update_option('imgseo_credits', $remaining_credits);
               
               // If credits are exhausted, set a warning
               if ($remaining_credits <= 0) {
                   set_transient('imgseo_insufficient_credits', true, 3600);
               } else {
                   delete_transient('imgseo_insufficient_credits');
               }
           } else {
               // Se non riceviamo i crediti, diminuiamo manualmente di 1
               $credits = get_option('imgseo_credits', 0);
               update_option('imgseo_credits', max(0, $credits - 1));
               
               if ($credits - 1 <= 0) {
                   set_transient('imgseo_insufficient_credits', true, 3600);
               }
           }
           
           error_log('ImgSEO API: Alt text generato con successo tramite base64 per attachment ID: ' . $attachment_id);
           
           return $response_body;
           
       } catch (Exception $e) {
           error_log('ImgSEO API: Eccezione durante il fallback base64: ' . $e->getMessage());
           return new WP_Error('base64_fallback_error', 'Error during base64 fallback: ' . $e->getMessage());
       }
   }
   
   /**
    * Trova la migliore thumbnail disponibile per un allegato
    *
    * @param int $attachment_id ID dell'allegato
    * @return array Array con [url_thumbnail, percorso_file_locale]
    */
   private function get_best_thumbnail($attachment_id) {
       // Dimensione massima accettabile per l'API (in byte)
       $max_acceptable_size = 10 * 1024 * 1024; // 10MB come limite
       
       // Array delle dimensioni thumbnail in ordine di preferenza
       $sizes = array('large', 'medium_large', 'medium', 'thumbnail');
       
       // Verifica se l'originale è sotto il limite
       $original_path = get_attached_file($attachment_id);
       $original_url = wp_get_attachment_url($attachment_id);
       
       if (file_exists($original_path) && filesize($original_path) <= $max_acceptable_size) {
           error_log('ImgSEO API: Usando immagine originale, dimensione accettabile: ' . round(filesize($original_path) / 1024, 2) . ' KB');
           return array($original_url, $original_path);
       }
       
       // Se l'originale è troppo grande, cerca una thumbnail adatta
       foreach ($sizes as $size) {
           // Ottieni l'URL della thumbnail
           $thumb = wp_get_attachment_image_src($attachment_id, $size);
           if ($thumb) {
               $thumb_url = $thumb[0];
               
               // Converti URL in percorso locale
               $upload_dir = wp_upload_dir();
               $thumb_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $thumb_url);
               
               // Verifica che il file esista e sia sotto il limite di dimensione
               if (file_exists($thumb_path)) {
                   $file_size = filesize($thumb_path);
                   if ($file_size <= $max_acceptable_size) {
                       error_log('ImgSEO API: Usando thumbnail ' . $size . ', dimensione: ' . round($file_size / 1024, 2) . ' KB');
                       return array($thumb_url, $thumb_path);
                   } else {
                       error_log('ImgSEO API: Thumbnail ' . $size . ' troppo grande (' . round($file_size / 1024, 2) . ' KB), provo la successiva');
                   }
               }
           }
       }
       
       // Se arriviamo qui, tutte le thumbnail sono troppo grandi o non disponibili
       // Restituisci la thumbnail più piccola disponibile come ultimo tentativo
       foreach (array_reverse($sizes) as $size) {
           $thumb = wp_get_attachment_image_src($attachment_id, $size);
           if ($thumb) {
               $thumb_url = $thumb[0];
               $upload_dir = wp_upload_dir();
               $thumb_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $thumb_url);
               
               if (file_exists($thumb_path)) {
                   error_log('ImgSEO API: Tutte le immagini sono oltre il limite di dimensione. Usando ' . $size . ' come ultima risorsa: ' .
                             round(filesize($thumb_path) / 1024, 2) . ' KB');
                   return array($thumb_url, $thumb_path);
               }
           }
       }
       
       // Se proprio non troviamo alternative, restituisci l'originale e lascia che l'API gestisca l'errore
       error_log('ImgSEO API: Nessuna thumbnail trovata, provo con l\'originale: ' . round(filesize($original_path) / 1024, 2) . ' KB');
       return array($original_url, $original_path);
   }
   
   /**
    * Converte un'immagine in stringa base64
    *
    * @param string $file_path Percorso del file immagine
    * @return string|false Stringa base64 o false in caso di errore
    */
   private function get_image_as_base64($file_path) {
       // Security: Validate path is within upload directory
       $upload_dir = wp_upload_dir();
       $upload_basedir = realpath($upload_dir['basedir']);
       $resolved_path = realpath($file_path);
       
       if ($resolved_path === false || strpos($resolved_path, $upload_basedir) !== 0) {
           error_log('ImgSEO Security: Blocked base64 conversion for unsafe path: ' . $file_path);
           return false;
       }
       
       // Verifica che il file esista
       if (!file_exists($file_path)) {
           error_log('ImgSEO API: File non trovato per conversione base64: ' . $file_path);
           return false;
       }
       
       // Ottieni tipo MIME
       $mime_type = mime_content_type($file_path);
       
       // Log della dimensione del file per debug
       $file_size = filesize($file_path);
       error_log('ImgSEO API: Dimensione file per base64: ' . round($file_size / 1024, 2) . ' KB');
       
       // Leggi e codifica file
       $image_data = file_get_contents($file_path);
       $base64 = base64_encode($image_data);
       
       // Restituisci solo la stringa base64 senza prefisso data URI
       // L'API si aspetta solo il contenuto base64 puro, non il formato data URI completo
       return $base64;
   }
   
   /**
    * Converte un URL immagine nel percorso file locale
    *
    * @param string $image_url URL dell'immagine
    * @return string Percorso locale del file
    */
   private function get_local_path_from_url($image_url) {
       // Gestione URL WordPress standard
       $upload_dir = wp_upload_dir();
       $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_url);
       
       // Security: Validate that the path is within upload directory
       $upload_basedir = realpath($upload_dir['basedir']);
       $resolved_path = realpath($file_path);
       
       if ($resolved_path === false || strpos($resolved_path, $upload_basedir) !== 0) {
           // Path is outside upload directory - security risk
           error_log('ImgSEO Security: Blocked path traversal attempt: ' . $image_url);
           
           // Fallback: try to get safe path via attachment ID
           $attachment_id = attachment_url_to_postid($image_url);
           if ($attachment_id) {
               $safe_path = get_attached_file($attachment_id);
               $safe_resolved = realpath($safe_path);
               if ($safe_resolved && strpos($safe_resolved, $upload_basedir) === 0) {
                   return $safe_path;
               }
           }
           return false; // Reject unsafe path
       }
       
       // Gestione URL con CDN o personalizzati
       if (!file_exists($file_path)) {
           // Tenta di risolvere l'URL tramite l'ID dell'allegato
           $attachment_id = attachment_url_to_postid($image_url);
           if ($attachment_id) {
               $safe_path = get_attached_file($attachment_id);
               $safe_resolved = realpath($safe_path);
               if ($safe_resolved && strpos($safe_resolved, $upload_basedir) === 0) {
                   return $safe_path;
               }
           }
       }
       
       return $file_path;
   }
   
   /**
     * Checks if the site is offline (not accessible from the internet)
     *
     * @return bool True if the site is offline, false otherwise
     */
    private function is_site_offline() {
       $site_url = get_site_url();
       
       // List of known local domains
       $local_domains = array(
           'localhost',
           '.local',
           '.test',
           '.dev',
           '127.0.0.1',
           '192.168.',
           '10.',
           '172.16.',
           '172.17.',
           '172.18.',
           '172.19.',
           '172.20.',
           '172.21.',
           '172.22.',
           '172.23.',
           '172.24.',
           '172.25.',
           '172.26.',
           '172.27.',
           '172.28.',
           '172.29.',
           '172.30.',
           '172.31.'
       );
       
       // Check if the site URL contains one of the local domains
       foreach ($local_domains as $domain) {
           if (strpos($site_url, $domain) !== false) {
               return true;
           }
       }
       
       return false;
   }
   
   /**
    * Gets available credits
    *
    * @param bool $refresh If true, forces an update from ImgSEO servers
    * @return int The number of available credits
    */
   public function get_available_credits($refresh = false) {
       // If an update is requested or the last check is older than an hour
       $last_check = get_option('imgseo_last_check', 0);
        if ($refresh || time() - $last_check > 3600) {
            $account_details = $this->get_account_details();
            if ($account_details !== false) {
                return $account_details['available'];
            }
        }
        
        return get_option('imgseo_credits', 0);
    }
}

} // Fine controllo class_exists
