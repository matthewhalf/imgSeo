<?php
/**
 * Batch processing class
 *
 * @package ImgSEO
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ImgSEO_Batch_Processor
 * Manages batch processing of images
 */
class ImgSEO_Batch_Processor extends ImgSEO_Generator_Base {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Aggiungiamo un comando di emergenza per fermare tutti i processi
        add_action('admin_init', array($this, 'check_emergency_stop'));
    }
    
    /**
     * Controlla se è stata richiesta una fermata di emergenza di tutti i processi
     */
    public function check_emergency_stop() {
        // Se non è una richiesta di emergenza, esci
        if (!isset($_GET['imgseo_emergency_stop']) || !current_user_can('manage_options')) {
            return;
        }
        
        // Verifica nonce per sicurezza
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'imgseo_emergency_stop')) {
            return;
        }
        
        // Includi la classe process lock
        require_once IMGSEO_DIRECTORY_PATH . 'includes/renamer/class-imgseo-process-lock.php';
        
        // Imposta il blocco globale
        ImgSEO_Process_Lock::set_global_lock();
        
        // Segna tutti i job come fermati
        global $wpdb;
        $table_name = $wpdb->prefix . 'imgseo_jobs';
        
        // Aggiorna tutti i job pendenti o in elaborazione a 'stopped'
        $wpdb->query(
            "UPDATE $table_name 
            SET status = 'stopped', updated_at = '" . current_time('mysql') . "' 
            WHERE status IN ('pending', 'processing')"
        );
        
        // Cancella tutti i cron job pianificati
        wp_clear_scheduled_hook(IMGSEO_CRON_HOOK);
        
        // Reindirizza alla pagina di bulk generazione con un messaggio
        wp_safe_redirect(add_query_arg(
            array('page' => 'imgseo-bulk', 'emergency_stopped' => '1'),
            admin_url('admin.php')
        ));
        exit;
    }
    
    /**
     * Handles the AJAX request to start bulk generation
     */
    public function handle_start_bulk() {
        check_ajax_referer('imgseo_nonce', 'security');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        // Check if the API key is present and valid
        $api_key = get_option('imgseo_api_key', '');
        $api_verified = !empty($api_key) && get_option('imgseo_api_verified', false);
        
        if (empty($api_key)) {
            wp_send_json_error([
                'message' => __('API Key missing. To use bulk generation, you must first configure an API Key.', IMGSEO_TEXT_DOMAIN),
                'redirect_url' => admin_url('admin.php?page=imgseo&tab=api')
            ]);
            return;
        }
        
        if (!$api_verified) {
            wp_send_json_error([
                'message' => __('API Key not verified. To use bulk generation, you need to verify the API key first.', IMGSEO_TEXT_DOMAIN),
                'redirect_url' => admin_url('admin.php?page=imgseo&tab=api')
            ]);
            return;
        }
        
        $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] == 1;
        $processing_mode = 'async'; // Modalità fast impostata di default
        
        // Get update settings for this job
        $update_title = isset($_POST['update_title']) && $_POST['update_title'] == 1;
        $update_caption = isset($_POST['update_caption']) && $_POST['update_caption'] == 1;
        $update_description = isset($_POST['update_description']) && $_POST['update_description'] == 1;
        
        // Get processing speed settings
        $processing_speed = isset($_POST['processing_speed']) ? sanitize_text_field($_POST['processing_speed']) : 'normal';
        
        // Validate processing speed value
        $valid_speeds = ['slow', 'normal', 'fast', 'ultra'];
        if (!in_array($processing_speed, $valid_speeds)) {
            $processing_speed = 'normal'; // Default to normal if invalid value provided
        }
        
        // Temporarily update options for this process
        update_option('imgseo_update_title', $update_title ? 1 : 0);
        update_option('imgseo_update_caption', $update_caption ? 1 : 0);
        update_option('imgseo_update_description', $update_description ? 1 : 0);
        update_option('imgseo_processing_speed', $processing_speed);
        
        // Get all images
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => -1,
            'post_status' => 'inherit'
        );
        
        $query = new WP_Query($args);
        $images = $query->posts;
        
        $total_images = count($images);
        
        if ($total_images === 0) {
            wp_send_json_error(['message' => 'No images found.']);
            return;
        }
        
        // Create a unique ID for this job
        $job_id = 'job_' . uniqid();
        
        // Prepare image data
        $images_data = [];
        foreach ($images as $image) {
            // Check that it's a valid image
            if (!wp_attachment_is_image($image->ID)) {
                continue;
            }
            
            $current_alt_text = get_post_meta($image->ID, '_wp_attachment_image_alt', true);
            if (!$overwrite && !empty($current_alt_text)) {
                continue; // Salta se non deve sovrascrivere e il testo alt esiste già
            }
            
            $images_data[] = $image->ID;
        }
        
        $images_to_process = count($images_data);
        
        if ($images_to_process === 0) {
            wp_send_json_error(['message' => 'Nessuna immagine da elaborare. Tutte le immagini hanno già un testo alternativo.']);
            return;
        }
        
        // Verifica con più precisione i crediti disponibili
        // Prima controlla il transient
        $credits_exhausted = get_transient('imgseo_insufficient_credits');
        if ($credits_exhausted) {
            wp_send_json_error(['message' => 'Insufficient ImgSEO credits. Please purchase more credits to continue.']);
            return;
        }
        
        // Poi controlla il valore numerico
        $credits = get_option('imgseo_credits', 0);
        if ($credits < 1) { // Controllo più rigoroso: crediti devono essere almeno 1
            // Imposta anche il transient per sicurezza
            set_transient('imgseo_insufficient_credits', true, 3600); // 1 ora
            wp_send_json_error(['message' => 'Insufficient ImgSEO credits. You currently have ' . $credits . ' credits. Please purchase more credits to continue.']);
            return;
        }
        
        // Se i crediti sono insufficienti per completare il job, avvisiamo l'utente ma procediamo comunque
        $warning_message = '';
        if ($credits < $images_to_process) {
            $warning_message = 'Note: You have ' . $credits . ' credits but need ' . $images_to_process . ' credits to process all images. The job will proceed and stop automatically when credits run out.';
        }
        
            // Check if the jobs table exists
            global $wpdb;
            $table_name = $wpdb->prefix . 'imgseo_jobs';
            
            // Verifica se la tabella esiste
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            )) === $table_name;
            
            if (!$table_exists) {
                // Se la tabella non esiste, crea la tabella usando query diretta
                // invece di dbDelta per maggiore compatibilità
                try {
                    // Attiva il logging degli errori per catturare eventuali problemi
                    $wpdb->show_errors();
                    
                    // Crea una versione semplificata della tabella per massima compatibilità
                    $charset_collate = $wpdb->get_charset_collate();
                    
                    // Prima prova a creare solo una tabella di base molto semplice
                    $create_query = "CREATE TABLE IF NOT EXISTS $table_name (
                        id INT NOT NULL AUTO_INCREMENT,
                        job_id VARCHAR(50) NOT NULL,
                        total_images INT NOT NULL,
                        processed_images INT NOT NULL DEFAULT 0,
                        images_data LONGTEXT NOT NULL,
                        overwrite TINYINT(1) NOT NULL DEFAULT 0,
                        status VARCHAR(20) NOT NULL DEFAULT 'pending',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (id)
                    ) $charset_collate;";
                    
                    // Esegui la query per creare la tabella
                    $result = $wpdb->query($create_query);
                    
                    // Verifica se la query ha generato un errore
                    if ($result === false) {
                        $error_message = 'Errore nella creazione della tabella. ' . 
                            'Tabella: ' . $table_name . '. ' . 
                            'Errore SQL: ' . $wpdb->last_error;
                        
                        error_log($error_message);  // Logga l'errore per il debugging
                        
                        // Fallback: prova con una versione ancora più semplice, senza caratteristiche avanzate
                        $simple_query = "CREATE TABLE IF NOT EXISTS $table_name (
                            id INT NOT NULL AUTO_INCREMENT,
                            job_id VARCHAR(50) NOT NULL,
                            total_images INT NOT NULL,
                            processed_images INT NOT NULL DEFAULT 0,
                            images_data TEXT NOT NULL,
                            status VARCHAR(20) NOT NULL,
                            PRIMARY KEY (id)
                        );";
                        
                        $result = $wpdb->query($simple_query);
                        
                        if ($result === false) {
                            // Non possiamo creare la tabella, segnala l'errore dettagliato
                            wp_send_json_error([
                                'message' => 'Errore critico nella creazione della tabella nel database. ' . 
                                            'Tabella: ' . $table_name . '. ' . 
                                            'Errore SQL: ' . $wpdb->last_error . '. ' . 
                                            'Query: ' . $simple_query
                            ]);
                            return;
                        }
                    }
                    
                    // Ora aggiungi l'indice in una query separata
                    $index_query = "ALTER TABLE $table_name ADD INDEX (job_id);";
                    $wpdb->query($index_query);  // Ignora eventuali errori qui
                    
                    // Controlla che la tabella esista ora
                    $table_exists_after_creation = $wpdb->get_var($wpdb->prepare(
                        "SHOW TABLES LIKE %s",
                        $table_name
                    )) === $table_name;
                    
                    if (!$table_exists_after_creation) {
                        wp_send_json_error([
                            'message' => 'La tabella non risulta creata nonostante il tentativo sia riuscito. ' . 
                                        'Tabella: ' . $table_name . '. ' . 
                                        'Verifica i permessi del database e le impostazioni.'
                        ]);
                        return;
                    }
                } catch (Exception $e) {
                    wp_send_json_error([
                        'message' => 'Eccezione durante la creazione della tabella: ' . $e->getMessage()
                    ]);
                    return;
                }
            }
        
        // Salva il job nel database
        $result = $wpdb->insert(
            $table_name, 
            [
                'job_id' => $job_id,
                'total_images' => $images_to_process,
                'processed_images' => 0,
                'images_data' => json_encode($images_data),
                'overwrite' => $overwrite ? 1 : 0,
                'status' => 'processing' // Sempre processing con modalità fast
            ]
        );
        
        if ($result === false) {
            wp_send_json_error(['message' => 'Error saving job to database: ' . $wpdb->last_error]);
            return;
        }
        
        // Verifica anche se la tabella dei log esiste
        $log_table_name = $wpdb->prefix . 'imgseo_logs';
        $log_table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $log_table_name
        )) === $log_table_name;
        
        if (!$log_table_exists) {
            // Se la tabella dei log non esiste, creala con lo stesso approccio sicuro
            try {
                // Attiva il logging degli errori per catturare eventuali problemi
                $wpdb->show_errors();
                
                // Crea una versione semplificata della tabella per massima compatibilità
                $charset_collate = $wpdb->get_charset_collate();
                
                // Prima prova a creare una tabella di base molto semplice
                $create_query = "CREATE TABLE IF NOT EXISTS $log_table_name (
                    id INT NOT NULL AUTO_INCREMENT,
                    job_id VARCHAR(50) NOT NULL,
                    image_id BIGINT(20) NOT NULL,
                    filename TEXT NOT NULL,
                    alt_text TEXT,
                    status VARCHAR(20) NOT NULL DEFAULT 'success',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id)
                ) $charset_collate;";
                
                // Esegui la query per creare la tabella
                $result = $wpdb->query($create_query);
                
                // Verifica se la query ha generato un errore
                if ($result === false) {
                    $error_message = 'Errore nella creazione della tabella dei log. ' . 
                        'Tabella: ' . $log_table_name . '. ' . 
                        'Errore SQL: ' . $wpdb->last_error;
                    
                    error_log($error_message);  // Logga l'errore per il debugging
                    
                    // Fallback: prova con una versione ancora più semplice
                    $simple_query = "CREATE TABLE IF NOT EXISTS $log_table_name (
                        id INT NOT NULL AUTO_INCREMENT,
                        job_id VARCHAR(50) NOT NULL,
                        image_id BIGINT(20) NOT NULL,
                        filename TEXT NOT NULL,
                        alt_text TEXT,
                        status VARCHAR(20) NOT NULL,
                        PRIMARY KEY (id)
                    );";
                    
                    $result = $wpdb->query($simple_query);
                    
                    if ($result === false) {
                        // Non possiamo creare la tabella, segnala l'errore dettagliato
                        wp_send_json_error([
                            'message' => 'Errore critico nella creazione della tabella dei log. ' . 
                                        'Tabella: ' . $log_table_name . '. ' . 
                                        'Errore SQL: ' . $wpdb->last_error . '. ' . 
                                        'Query: ' . $simple_query
                        ]);
                        return;
                    }
                }
                
                // Ora aggiungi gli indici in query separate
                $wpdb->query("ALTER TABLE $log_table_name ADD INDEX (job_id);");
                $wpdb->query("ALTER TABLE $log_table_name ADD INDEX (image_id);");
                
                // Controlla che la tabella esista ora
                $log_table_exists_after_creation = $wpdb->get_var($wpdb->prepare(
                    "SHOW TABLES LIKE %s",
                    $log_table_name
                )) === $log_table_name;
                
                if (!$log_table_exists_after_creation) {
                    wp_send_json_error([
                        'message' => 'La tabella dei log non risulta creata nonostante il tentativo sia riuscito. ' . 
                                    'Tabella: ' . $log_table_name . '. ' . 
                                    'Verifica i permessi del database e le impostazioni.'
                    ]);
                    return;
                }
            } catch (Exception $e) {
                wp_send_json_error([
                    'message' => 'Eccezione durante la creazione della tabella dei log: ' . $e->getMessage()
                ]);
                return;
            }
        }

        // La modalità background è stata rimossa, ora c'è solo la modalità fast (async)
        
        // Includi avviso sui crediti nella risposta come parte del messaggio 
        // senza utilizzare flag che potrebbero generare popup
        $message = "Processing started for $images_to_process images";
        if (!empty($warning_message)) {
            $message .= ". " . $warning_message;
        }
        
        wp_send_json_success([
            'job_id' => $job_id,
            'total_images' => $images_to_process,
            'image_ids' => $images_data,
            'processing_mode' => $processing_mode,
            'message' => $message
        ]);
    }
    
    /**
     * Gestisce la richiesta AJAX per controllare lo stato del job
     */
    public function handle_check_job_status() {
        check_ajax_referer('imgseo_nonce', 'security');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
        
        if (empty($job_id)) {
            wp_send_json_error(['message' => 'ID job mancante']);
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'imgseo_jobs';
        $log_table_name = $wpdb->prefix . 'imgseo_logs';
        
        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE job_id = %s",
            $job_id
        ));
        
        if (!$job) {
            wp_send_json_error(['message' => 'Job non trovato']);
        }
        
        // Calcolo della percentuale di avanzamento
        // Assicuriamoci che i job completati mostrino sempre 100%
        $progress = 0;
        if ($job->total_images > 0) {
            if ($job->status === 'completed') {
                $progress = 100;
            } else {
                $progress = round(($job->processed_images / $job->total_images) * 100);
            }
        }
        
        // Ottieni gli ultimi log per questo job
        $last_log_id = isset($_POST['last_log_id']) ? intval($_POST['last_log_id']) : 0;
        $logs = [];
        
        // Verifica se la tabella dei log esiste
        $log_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $log_table_name)) === $log_table_name;
        
        if ($log_table_exists) {
            $logs = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $log_table_name WHERE job_id = %s AND id > %d ORDER BY id ASC LIMIT 50",
                $job_id,
                $last_log_id
            ));
        }
        
        // Formatta i log per il client
        $formatted_logs = [];
        $max_log_id = $last_log_id;
        
        foreach ($logs as $log) {
            $formatted_logs[] = [
                'id' => $log->id,
                'image_id' => $log->image_id,
                'filename' => $log->filename,
                'alt_text' => $log->alt_text,
                'status' => $log->status,
                'time' => $log->created_at
            ];
            
            $max_log_id = max($max_log_id, $log->id);
        }
        
        wp_send_json_success([
            'job_id' => $job->job_id,
            'status' => $job->status,
            'total_images' => $job->total_images,
            'processed_images' => $job->processed_images,
            'progress' => $progress,
            'message' => "Processing: $job->processed_images of $job->total_images completed",
            'is_completed' => ($job->status === 'completed' || $job->status === 'stopped'),
            'logs' => $formatted_logs,
            'max_log_id' => $max_log_id,
            'last_updated' => $job->updated_at
        ]);
    }
    
    /**
     * Gestisce la richiesta AJAX per interrompere un job
     * Supporta anche il completamento di job asincroni (fast processing)
     */
    public function handle_stop_job() {
        check_ajax_referer('imgseo_nonce', 'security');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Non autorizzato']);
        }
        
        $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
        
        if (empty($job_id)) {
            wp_send_json_error(['message' => 'ID job mancante']);
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'imgseo_jobs';
        
        // Verifica se il job esiste
        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE job_id = %s",
            $job_id
        ));
        
        if (!$job) {
            wp_send_json_error(['message' => 'Job non trovato']);
        }
        
        // Verifica se il job è già completato o interrotto
        if ($job->status === 'completed' || $job->status === 'stopped') {
            // Anche se già interrotto, ottieni comunque il conteggio corretto dai log
            $processed_count = $job->processed_images;
            
            // Ottieni il conteggio dai log in ogni caso
            $log_table_name = $wpdb->prefix . 'imgseo_logs';
            $log_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $log_table_name)) === $log_table_name;
            
            if ($log_table_exists) {
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $log_table_name WHERE job_id = %s",
                    $job_id
                ));
                
                if ($count) {
                    $processed_count = max(intval($count), $processed_count);
                    
                    // Aggiorna il conteggio nel database anche se è già interrotto
                    if ($processed_count > $job->processed_images) {
                        $wpdb->update(
                            $table_name,
                            ['processed_images' => $processed_count],
                            ['job_id' => $job_id]
                        );
                    }
                }
            }
            
            wp_send_json_success([
                'message' => 'Il job è già stato interrotto o completato',
                'job_id' => $job_id,
                'status' => $job->status,
                'processed_images' => $processed_count
            ]);
            return;
        }
        
        // Determina se è una interruzione o un completamento normale
        // Il flag completion_status è usato dal processo asincrono per indicare completamento invece di interruzione
        $completion_status = isset($_POST['completion_status']) && $_POST['completion_status'] === 'completed' ? 'completed' : 'stopped';
        
        // Ottieni il conteggio delle immagini processate
        // Prima prova a prendere il valore passato esplicitamente dalla richiesta
        $processed_count = isset($_POST['processed_count']) ? intval($_POST['processed_count']) : $job->processed_images;
        
        // SEMPRE verifica i log per ottenere il conteggio più accurato
        // indipendentemente dallo stato del job o dal conteggio precedente
        $log_table_name = $wpdb->prefix . 'imgseo_logs';
        $log_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $log_table_name)) === $log_table_name;
        
        if ($log_table_exists) {
            // Usa una query SQL più affidabile per contare correttamente
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT image_id) FROM $log_table_name WHERE job_id = %s",
                $job_id
            ));
            
            if ($count && intval($count) > 0) {
                // Prendi sempre il valore più alto tra il conteggio dai log
                // e quello eventualmente passato dal frontend
                $processed_count = max(intval($count), $processed_count);
                
                // Log per debug
                error_log("Job ID: $job_id - Count from logs: $count, Final count: $processed_count");
            }
        }
        
        // Se è un job di terminazione normale, setta una transient per comunicare al cron job di fermarsi immediatamente
        if ($completion_status === 'stopped') {
            set_transient('imgseo_stop_job_' . $job_id, 'yes', 60 * 5); // 5 minuti di durata
        }
        
        // Aggiorna lo stato del job e il conteggio delle immagini
        $result = $wpdb->update(
            $table_name,
            [
                'status' => $completion_status,
                'processed_images' => $processed_count,
                'updated_at' => current_time('mysql')
            ],
            ['job_id' => $job_id]
        );
        
        // Forza una nuova query per verificare che i dati siano stati effettivamente salvati
        $updated_job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE job_id = %s",
            $job_id
        ));
        
        // Log per debug
        error_log("Job ID: $job_id - Status after update: {$updated_job->status}, Processed: {$updated_job->processed_images}");
        
        if ($result === false) {
            wp_send_json_error(['message' => 'Error updating the job status']);
        }
        
        // Assicurati che il conteggio finale sia quello realmente salvato nel database
        $final_count = $updated_job ? $updated_job->processed_images : $processed_count;
        
        wp_send_json_success([
            'message' => $completion_status === 'completed' ? 'Job completato con successo' : 'Job interrotto con successo',
            'job_id' => $job_id,
            'processed_images' => $final_count,
            'total_images' => $job->total_images,
            'progress' => $job->total_images > 0 ? round(($final_count / $job->total_images) * 100) : 0,
            'status' => $completion_status
        ]);
    }
    
    /**
     * Gestisce la richiesta AJAX per eliminare un job
     */
    public function handle_delete_job() {
        check_ajax_referer('imgseo_nonce', 'security');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Non autorizzato']);
        }
        
        $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
        
        if (empty($job_id)) {
            wp_send_json_error(['message' => 'ID job mancante']);
        }
        
        // Temporaneamente disabilita controlli API per evitare consumo crediti
        // Salva lo stato attuale per ripristinarlo dopo
        $did_http_api_filter = false;
        if (!has_filter('pre_http_request', array($this, 'block_external_api_requests'))) {
            add_filter('pre_http_request', array($this, 'block_external_api_requests'), 10, 3);
            $did_http_api_filter = true;
        }
        
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'imgseo_jobs';
            $log_table_name = $wpdb->prefix . 'imgseo_logs';
            
            // Blocca immediatamente questo job per prevenire elaborazioni addizionali
            require_once IMGSEO_DIRECTORY_PATH . 'includes/renamer/class-imgseo-process-lock.php';
            ImgSEO_Process_Lock::set_job_lock($job_id);
            
            // Verifica se il job esiste
            $job = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE job_id = %s",
                $job_id
            ));
            
            if (!$job) {
                // Se il job non esiste, potrebbe essere già stato eliminato
                wp_send_json_success([
                    'message' => 'Job già eliminato',
                    'job_id' => $job_id
                ]);
                return;
            }
            
            // Prima interrompi il job se è in esecuzione
            if ($job->status === 'pending' || $job->status === 'processing') {
                $wpdb->update(
                    $table_name,
                    ['status' => 'stopped', 'updated_at' => current_time('mysql')],
                    ['job_id' => $job_id]
                );
            }
            
            // Delete logs associated with the job first
            $log_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $log_table_name)) === $log_table_name;
            if ($log_table_exists) {
                $wpdb->delete(
                    $log_table_name,
                    ['job_id' => $job_id]
                );
            }
            
            // Poi elimina il job
            $result = $wpdb->delete(
                $table_name,
                ['job_id' => $job_id]
            );
            
            if ($result === false) {
                wp_send_json_error(['message' => 'Error deleting job']);
            }
            
            wp_send_json_success([
                'message' => 'Job eliminato con successo',
                'job_id' => $job_id
            ]);
        } finally {
            // Ripristina il comportamento normale delle API
            if ($did_http_api_filter) {
                remove_filter('pre_http_request', array($this, 'block_external_api_requests'), 10);
            }
        }
    }
    
    /**
     * Blocca le richieste API esterne durante alcune operazioni
     */
    public function block_external_api_requests($preempt, $args, $url) {
        return new WP_Error('api_disabled', 'Le API esterne sono temporaneamente disabilitate durante le operazioni amministrative.');
    }
    
    /**
     * Gestisce la richiesta AJAX per eliminare tutti i job
     */
    public function handle_delete_all_jobs() {
        check_ajax_referer('imgseo_nonce', 'security');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Non autorizzato']);
        }
        
        // Temporaneamente disabilita controlli API per evitare consumo crediti
        // Salva lo stato attuale per ripristinarlo dopo
        $did_http_api_filter = false;
        if (!has_filter('pre_http_request', array($this, 'block_external_api_requests'))) {
            add_filter('pre_http_request', array($this, 'block_external_api_requests'), 10, 3);
            $did_http_api_filter = true;
        }
        
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'imgseo_jobs';
            $log_table_name = $wpdb->prefix . 'imgseo_logs';
            
            // Prima interrompi tutti i job in esecuzione
            $wpdb->query(
                "UPDATE $table_name 
                SET status = 'stopped', updated_at = '" . current_time('mysql') . "' 
                WHERE status IN ('pending', 'processing')"
            );
            
            // Delete all logs
            $log_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $log_table_name)) === $log_table_name;
            if ($log_table_exists) {
                $wpdb->query("TRUNCATE TABLE $log_table_name");
            }
            
            // Delete all jobs
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
            if ($table_exists) {
                $wpdb->query("TRUNCATE TABLE $table_name");
            }
            
            // Rimuovi qualsiasi evento cron
            wp_clear_scheduled_hook(IMGSEO_CRON_HOOK);
            
            wp_send_json_success([
                'message' => 'Tutti i job sono stati eliminati con successo'
            ]);
        } finally {
            // Ripristina il comportamento normale delle API
            if ($did_http_api_filter) {
                remove_filter('pre_http_request', array($this, 'block_external_api_requests'), 10);
            }
        }
    }
}
