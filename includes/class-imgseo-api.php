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
        
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        
        if (empty($api_key)) {
            wp_send_json_error(['message' => __('ImgSEO Token not provided', IMGSEO_TEXT_DOMAIN)]);
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
            
            error_log('ImgSEO API: Credits response - ' . print_r($response_body, true));
            
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
