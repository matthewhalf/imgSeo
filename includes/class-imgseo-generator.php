<?php

/**

 * Class ImgSEO_Generator

 * Manages alt text generation and batch processing functionality

 */

class ImgSEO_Generator {



    /**

     * Constructor

     */

    public function __construct() {

        // Add hook for alt text column in media library

        add_filter('manage_media_columns', array($this, 'add_alt_text_column'));

        add_action('manage_media_custom_column', array($this, 'render_alt_text_column'), 10, 2);

        add_filter('manage_upload_sortable_columns', array($this, 'make_alt_text_column_sortable'));



        // Add bulk actions in media library

        add_filter('bulk_actions-upload', array($this, 'register_bulk_actions'));

        add_filter('handle_bulk_actions-upload', array($this, 'handle_bulk_actions'), 10, 3);



        // Hook for checking stuck jobs

        add_action('imgseo_check_stuck_jobs', array($this, 'check_stuck_jobs'));



        // Add handler for single alt text generation

        add_action('imgseo_single_generate', array($this, 'process_single_generate'));

    }



    /**

     * Automatically generates alt text when a new image is uploaded

     * Improved version with status verification and more robust handling

     *

     * @param int $attachment_id Attachment ID

     */

    public function auto_generate_alt_text($attachment_id) {

        error_log('ImgSEO: auto_generate_alt_text called for ID: ' . $attachment_id);



        // Check if the feature is enabled

        $auto_generate = get_option('imgseo_auto_generate', 0);

        error_log('ImgSEO: auto_generate is: ' . ($auto_generate ? 'ENABLED' : 'DISABLED'));



        if (!$auto_generate) {

            error_log('ImgSEO: Automatic generation disabled, ignoring');

            return;

        }



        // Verify that it's an image

        // In some cases wp_attachment_is_image can fail if metadata is not yet generated

        // so we do an additional check on post_mime_type

        $is_image = wp_attachment_is_image($attachment_id);

        $mime_type = get_post_mime_type($attachment_id);

        $is_image_mime = strpos($mime_type, 'image/') === 0;



        if (!$is_image && !$is_image_mime) {

            error_log('ImgSEO: ID ' . $attachment_id . ' is not an image (multiple check)');

            return;

        }



        // Check if the attachment is already pending generation

        $pending = get_post_meta($attachment_id, '_imgseo_pending_generation', true);

        if ($pending) {

            $pending_time = intval($pending);

            $current_time = time();

            // If it's been pending for less than 3 minutes, don't reschedule

            if ($current_time - $pending_time < 180) {

                error_log('ImgSEO: ID ' . $attachment_id . ' already pending generation for ' . human_time_diff($pending_time, $current_time));

                return;

            }

            // Otherwise, consider that the previous attempt failed and continue

            error_log('ImgSEO: Previous attempt failed or expired, proceeding with new attempt');

        }



        // Check if it already has alt text

        $current_alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);

        $overwrite = get_option('imgseo_overwrite', 0);

        error_log('ImgSEO: Existing alt text: ' . ($current_alt_text ? '"'.$current_alt_text.'"' : 'NONE') . ', overwrite: ' . ($overwrite ? 'YES' : 'NO'));



        if (!$overwrite && !empty($current_alt_text)) {

            error_log('ImgSEO: Alt text already present and overwrite disabled, ignoring');

            return; // If it shouldn't overwrite and there's already alt text, skip

        }



        // Check ImgSEO credits

        $credits = get_option('imgseo_credits', 0);

        error_log('ImgSEO: Available credits: ' . $credits);



        if ($credits <= 0) {

            error_log('ImgSEO: Insufficient credits for automatic generation');

            return;

        }



        // Check if the image is accessible via URL

        $image_url = wp_get_attachment_url($attachment_id);

        if (!$image_url) {

            error_log('ImgSEO: Unable to get URL for ID: ' . $attachment_id . ', rescheduling for later');

            // Schedule a new attempt in 30 seconds

            wp_schedule_single_event(time() + 30, 'add_attachment', array($attachment_id));

            return;

        }



        // Add a temporary identifier to the post to indicate it's pending generation

        update_post_meta($attachment_id, '_imgseo_pending_generation', time());



        // Execute the first attempt immediately

        error_log('ImgSEO: Executing first attempt immediately');

        $this->process_single_generate($attachment_id, 1);



        // Schedule fallback attempts in case the first one doesn't succeed

        // Second attempt after 30 seconds

        error_log('ImgSEO: Scheduling second attempt after 30 seconds');

        wp_schedule_single_event(time() + 30, 'imgseo_single_generate', array($attachment_id, 2));



        // Third attempt after 1 minute

        error_log('ImgSEO: Scheduling third attempt after 60 seconds');

        wp_schedule_single_event(time() + 60, 'imgseo_single_generate', array($attachment_id, 3));



        // Fourth (final) attempt after 2 minutes

        error_log('ImgSEO: Scheduling final attempt after 120 seconds');

        wp_schedule_single_event(time() + 120, 'imgseo_single_generate', array($attachment_id, 4));

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



        // Ottieni il titolo della pagina genitore se disponibile

        $parent_post_id = get_post_field('post_parent', $attachment_id);

        $parent_post_title = $parent_post_id ? get_the_title($parent_post_id) : '';



        // Ottieni il nome del file per i log

        $filename = basename($image_url);



        // Ottieni job_id se presente

        $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';



        $alt_text = $this->generate_alt_text($image_url, $attachment_id, $parent_post_title);



        if (is_wp_error($alt_text)) {

            error_log('ImgSEO: Errore nella generazione del testo alt: ' . $alt_text->get_error_message());

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

                error_log('ImgSEO: Errore aggiornamento: ' . $result->get_error_message());

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

     * Gestisce la richiesta AJAX per avviare la generazione in bulk

     */

    public function handle_start_bulk() {

        check_ajax_referer('imgseo_nonce', 'security');



        if (!current_user_can('manage_options')) {

            wp_send_json_error(['message' => 'Unauthorized']);

        }



        // Check if API key is present and valid

        $api_key = get_option('imgseo_api_key', '');

        $api_verified = !empty($api_key) && get_option('imgseo_api_verified', false);



        if (empty($api_key)) {

            wp_send_json_error([

                'message' => __('Missing API key. To use bulk generation, you must first configure an API key.', IMGSEO_TEXT_DOMAIN),

                'redirect_url' => admin_url('admin.php?page=imgseo&tab=api')

            ]);

            return;

        }



        if (!$api_verified) {

            wp_send_json_error([

                'message' => __('Unverified API key. To use bulk generation, you must first verify the API key.', IMGSEO_TEXT_DOMAIN),

                'redirect_url' => admin_url('admin.php?page=imgseo&tab=api')

            ]);

            return;

        }



        $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] == 1;

        $processing_mode = isset($_POST['processing_mode']) ? sanitize_text_field($_POST['processing_mode']) : 'background';



        // Get update settings for this job

        $update_title = isset($_POST['update_title']) && $_POST['update_title'] == 1;

        $update_caption = isset($_POST['update_caption']) && $_POST['update_caption'] == 1;

        $update_description = isset($_POST['update_description']) && $_POST['update_description'] == 1;



        // Update options temporarily for this process

        update_option('imgseo_update_title', $update_title ? 1 : 0);

        update_option('imgseo_update_caption', $update_caption ? 1 : 0);

        update_option('imgseo_update_description', $update_description ? 1 : 0);



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

            // Verify it's a valid image

            if (!wp_attachment_is_image($image->ID)) {

                continue;

            }



            $current_alt_text = get_post_meta($image->ID, '_wp_attachment_image_alt', true);

            if (!$overwrite && !empty($current_alt_text)) {

                continue; // Skip if not overwriting and alt text already exists

            }



            $images_data[] = $image->ID;

        }



        $images_to_process = count($images_data);



        if ($images_to_process === 0) {

            wp_send_json_error(['message' => 'No images to process. All images already have alt text.']);

            return;

        }



        // Check only if there are available credits

        $credits = get_option('imgseo_credits', 0);

        if ($credits <= 0) {

            wp_send_json_error(['message' => 'Insufficient ImgSEO credits. Please purchase more credits to continue.']);

            return;

        }

        // We don't block the process if credits are insufficient for all images



        // FIX: Check if the jobs table exists

        global $wpdb;

        $table_name = $wpdb->prefix . 'imgseo_jobs';



        // Check if the table exists

        $table_exists = $wpdb->get_var($wpdb->prepare(

            "SHOW TABLES LIKE %s",

            $table_name

        )) === $table_name;



        if (!$table_exists) {

            // If the table doesn't exist, create it

            $charset_collate = $wpdb->get_charset_collate();



            $sql = "CREATE TABLE $table_name (

                id mediumint(9) NOT NULL AUTO_INCREMENT,

                job_id varchar(50) NOT NULL,

                total_images int NOT NULL,

                processed_images int NOT NULL DEFAULT 0,

                images_data longtext NOT NULL,

                overwrite tinyint(1) NOT NULL DEFAULT 0,

                status varchar(20) NOT NULL DEFAULT 'pending',

                created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,

                updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,

                PRIMARY KEY  (id),

                KEY job_id (job_id)

            ) $charset_collate;";



            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            dbDelta($sql);

        }



        // Save the job to the database

        $result = $wpdb->insert(

            $table_name,

            [

                'job_id' => $job_id,

                'total_images' => $images_to_process,

                'processed_images' => 0,

                'images_data' => json_encode($images_data),

                'overwrite' => $overwrite ? 1 : 0,

                'status' => $processing_mode === 'async' ? 'processing' : 'pending'

            ]

        );



        if ($result === false) {

            wp_send_json_error(['message' => 'Error saving job to database: ' . $wpdb->last_error]);

            return;

        }



        // If in background mode, execute the cron job immediately

        if ($processing_mode === 'background') {

            // Remove any existing cron and schedule an immediate execution

            wp_clear_scheduled_hook(IMGSEO_CRON_HOOK);

            wp_schedule_single_event(time(), IMGSEO_CRON_HOOK);



            // Force cron execution if possible

            $this->try_trigger_cron();

        }



        wp_send_json_success([

            'job_id' => $job_id,

            'total_images' => $images_to_process,

            'image_ids' => $images_data,

            'processing_mode' => $processing_mode,

            'message' => "Processing started for $images_to_process images"

        ]);

    }



    /**

     * Attempts to start an immediate execution of the cron

     */

    private function try_trigger_cron() {

        // Create a non-blocking request to wp-cron.php

        $cron_url = site_url('wp-cron.php?doing_wp_cron=1&imgseo_force=1');

        $args = [

            'blocking'  => false,

            'sslverify' => apply_filters('https_local_ssl_verify', false),

            'timeout'   => 0.01,

            'headers'   => [

                'Cache-Control' => 'no-cache',

            ]

        ];



        wp_remote_get($cron_url, $args);

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



        $progress = ($job->total_images > 0) ?

            round(($job->processed_images / $job->total_images) * 100) : 0;



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

            'message' => "Elaborazione: $job->processed_images di $job->total_images completate",

            'is_completed' => ($job->status === 'completed' || $job->status === 'stopped'),

            'logs' => $formatted_logs,

            'max_log_id' => $max_log_id,

            'last_updated' => $job->updated_at

        ]);

    }



    /**

     * Gestisce la richiesta AJAX per interrompere un job

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

            wp_send_json_success([

                'message' => 'Il job è già stato interrotto o completato',

                'job_id' => $job_id,

                'status' => $job->status

            ]);

            return;

        }



        // Se è un job asincrono, conteggia quanto realmente processato finora

        $processed_count = $job->processed_images;



        if ($job->status === 'processing' && $job->processed_images === 0) {

            // Controlla se ci sono log per questo job

            $log_table_name = $wpdb->prefix . 'imgseo_logs';

            $log_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $log_table_name)) === $log_table_name;



            if ($log_table_exists) {

                $count = $wpdb->get_var($wpdb->prepare(

                    "SELECT COUNT(*) FROM $log_table_name WHERE job_id = %s",

                    $job_id

                ));



                if ($count) {

                    $processed_count = intval($count);

                }

            }

        }



        // Setta una transient per comunicare al cron job di fermarsi immediatamente

        // Questo fornisce un canale di comunicazione immediato che non dipende solo dal DB

        set_transient('imgseo_stop_job_' . $job_id, 'yes', 60 * 5); // 5 minuti di durata



        // Aggiorna lo stato del job a "stopped"

        $result = $wpdb->update(

            $table_name,

            [

                'status' => 'stopped',

                'processed_images' => $processed_count,

                'updated_at' => current_time('mysql')

            ],

            ['job_id' => $job_id]

        );



        if ($result === false) {

            wp_send_json_error(['message' => 'Errore durante l\'interruzione del job']);

        }



        // Cancelliamo qualsiasi pianificazione CRON esistente per essere sicuri

        wp_clear_scheduled_hook(IMGSEO_CRON_HOOK);



        wp_send_json_success([

            'message' => 'Job interrotto con successo',

            'job_id' => $job_id,

            'processed_images' => $processed_count

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



        global $wpdb;

        $table_name = $wpdb->prefix . 'imgseo_jobs';

        $log_table_name = $wpdb->prefix . 'imgseo_logs';



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



        // Elimina prima i log associati al job

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

            wp_send_json_error(['message' => 'Errore durante l\'eliminazione del job']);

        }



        wp_send_json_success([

            'message' => 'Job eliminato con successo',

            'job_id' => $job_id

        ]);

    }



    /**

     * Gestisce la richiesta AJAX per eliminare tutti i job

     */

    public function handle_delete_all_jobs() {

        check_ajax_referer('imgseo_nonce', 'security');



        if (!current_user_can('manage_options')) {

            wp_send_json_error(['message' => 'Non autorizzato']);

        }



        global $wpdb;

        $table_name = $wpdb->prefix . 'imgseo_jobs';

        $log_table_name = $wpdb->prefix . 'imgseo_logs';



        // Elimina tutti i log

        $log_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $log_table_name)) === $log_table_name;

        if ($log_table_exists) {

            $wpdb->query("TRUNCATE TABLE $log_table_name");

        }



        // Elimina tutti i job

        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;

        if ($table_exists) {

            $wpdb->query("TRUNCATE TABLE $table_name");

        }



        // Rimuovi qualsiasi evento cron

        wp_clear_scheduled_hook(IMGSEO_CRON_HOOK);



        wp_send_json_success([

            'message' => 'Tutti i job sono stati eliminati con successo'

        ]);

    }



    /**

     * Forza l'esecuzione del cron job

     */

    public function force_cron_execution() {

        check_ajax_referer('imgseo_nonce', 'security');



        if (!current_user_can('manage_options')) {

            wp_send_json_error(['message' => 'Non autorizzato']);

        }



        // Pulisci i cron esistenti e pianifica uno nuovo

        wp_clear_scheduled_hook(IMGSEO_CRON_HOOK);

        wp_schedule_single_event(time(), IMGSEO_CRON_HOOK);



        // Tenta di avviare immediatamente il cron

        $this->try_trigger_cron();



        // Verifica se è stato eseguito recentemente

        $last_run = get_option('imgseo_last_cron_run', 0);

        if ($last_run === 0) {

            $last_run = time();

            update_option('imgseo_last_cron_run', $last_run);

        }



        wp_send_json_success([

            'message' => 'Cron eseguito manualmente',

            'last_run' => date('Y-m-d H:i:s', $last_run),

            'time_ago' => human_time_diff($last_run, time()) . ' fa'

        ]);

    }



    // Metodo rimosso perché duplicato



    /**

     * Verifica e ripara job bloccati

     */

    public function check_stuck_jobs() {

        global $wpdb;

        $table_name = $wpdb->prefix . 'imgseo_jobs';



        // Trova i job bloccati (in stato pending o processing per più di 5 minuti)

        $five_minutes_ago = date('Y-m-d H:i:s', time() - 300);



        $stuck_jobs = $wpdb->get_results(

            $wpdb->prepare(

                "SELECT * FROM $table_name

                WHERE (status = 'pending' OR status = 'processing')

                AND updated_at < %s

                AND status != 'stopped'",

                $five_minutes_ago

            )

        );



        if (empty($stuck_jobs)) {

            return; // Nessun job bloccato

        }



        foreach ($stuck_jobs as $job) {

            // Forza l'esecuzione del cron

            do_action(IMGSEO_CRON_HOOK);



            // Attendi un momento per permettere al cron di processare

            sleep(2);



            // Verifica se il job è ancora bloccato

            $job_status = $wpdb->get_var(

                $wpdb->prepare(

                    "SELECT status FROM $table_name WHERE id = %d",

                    $job->id

                )

            );



            // Se il job è ancora in stato pending o processing, imposta un messaggio

            if ($job_status === 'pending' || $job_status === 'processing') {

                $wpdb->update(

                    $table_name,

                    [

                        'status' => 'processing',

                        'updated_at' => current_time('mysql')

                    ],

                    ['id' => $job->id]

                );

            }

        }

    }



    /**

     * Crea la tabella dei log se non esiste

     */

    private function create_logs_table_if_not_exists() {

        global $wpdb;

        $log_table_name = $wpdb->prefix . 'imgseo_logs';



        // Verifica se la tabella esiste già

        $table_exists = $wpdb->get_var($wpdb->prepare(

            "SHOW TABLES LIKE %s",

            $log_table_name

        )) === $log_table_name;



        if (!$table_exists) {

            $charset_collate = $wpdb->get_charset_collate();



            $sql = "CREATE TABLE $log_table_name (

                id mediumint(9) NOT NULL AUTO_INCREMENT,

                job_id varchar(50) NOT NULL,

                image_id int(11) NOT NULL,

                filename varchar(255) NOT NULL,

                alt_text text NOT NULL,

                status varchar(20) NOT NULL DEFAULT 'success',

                created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,

                PRIMARY KEY  (id),

                KEY job_id (job_id)

            ) $charset_collate;";



            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            dbDelta($sql);

        }

    }



    /**

     * Genera il testo alternativo per un'immagine

     *

     * @param string $image_url URL dell'immagine

     * @param int $attachment_id ID dell'allegato

     * @param string $parent_post_title Titolo del post genitore (opzionale)

     * @return string|WP_Error Testo alternativo generato o oggetto WP_Error

     */

    public function generate_alt_text($image_url, $attachment_id, $parent_post_title = '') {

        // Ottieni le impostazioni

        $language = get_option('imgseo_language', 'english');

        $max_characters = intval(get_option('imgseo_max_characters', 125));

        $include_page_title = get_option('imgseo_include_page_title', 1);

        $include_image_name = get_option('imgseo_include_image_name', 1);



        // Ottieni il prompt personalizzato

        $prompt_template = get_option('imgseo_custom_prompt', 'Generate SEO-friendly alt text describing the main visual elements of this image. Output only the alt text in {language}, maximum {max_characters} characters. {page_title_info} {image_name_info} Naturally incorporate relevant keywords and aim to be as close as possible to the specified character limit.');



        // Preparazione delle variabili per la sostituzione

        $page_title_info = '';

        if ($include_page_title && !empty($parent_post_title)) {

            $page_title_info = "the page title is: $parent_post_title";

        }



        $image_name_info = '';

        if ($include_image_name) {

            $image_name = basename($image_url);

            if ($image_name) {

                $image_name_info = "the image name is: $image_name";

            }

        }



        // Sostituisci le variabili nel template

        $prompt = str_replace(

            ['{language}', '{max_characters}', '{page_title_info}', '{image_name_info}'],

            [$language, $max_characters, $page_title_info, $image_name_info],

            $prompt_template

        );



        // Pulisci il prompt (rimuovi spazi multipli che potrebbero essere creati)

        $prompt = preg_replace('/\s+/', ' ', $prompt);

        $prompt = trim($prompt);



        // Opzioni da passare all'API

        $options = [

            'lang' => $language,

            'overwrite' => true,

            'attachment_id' => $attachment_id // Aggiungi l'ID dell'attachment alle opzioni

        ];



        // Verifica crediti disponibili

        $credits = get_option('imgseo_credits', 0);

        if ($credits <= 0) {

            return new WP_Error('insufficient_credits', 'Insufficient ImgSEO credits. Please purchase more credits to continue.');

        }



        // Usa l'API ImgSEO per generare il testo alternativo

        $api = ImgSEO_API::get_instance();

        $response = $api->generate_alt_text($image_url, $prompt, $options);



        if (is_wp_error($response)) {

            return $response;

        }



        // Extract alt text from the response

        if (isset($response['data']) && isset($response['data']['altText'])) {

            $alt_text = $response['data']['altText'];



            // Clean up the alt text

            $alt_text = str_replace('"', '', $alt_text);

            $alt_text = trim($alt_text);



            return $alt_text;

        }



        return new WP_Error('invalid_response', 'Invalid API response');

    }



    /**

     * Elabora un batch di immagini in background tramite cron

     *

     * Questa funzione è stata modificata per aggiungere un ritardo tra i batch

     * per evitare un consumo eccessivo di crediti API.

     */

    public function process_cron_batch() {

        // Aggiorna il timestamp dell'ultima esecuzione

        update_option('imgseo_last_cron_run', time());



        global $wpdb;

        $table_name = $wpdb->prefix . 'imgseo_jobs';

        $log_table_name = $wpdb->prefix . 'imgseo_logs';



        // Crea la tabella dei log se non esiste

        $this->create_logs_table_if_not_exists();



        // Trova solo UN job pendente alla volta

        $job = $wpdb->get_row(

            "SELECT * FROM $table_name

            WHERE (status = 'pending' OR status = 'processing')

            AND status != 'stopped'

            ORDER BY created_at ASC LIMIT 1"

        );



        if (!$job) {

            // Se non ci sono più job da elaborare, NON ripianificare eventi cron

            return;

        }



        // Aggiorna lo stato del job a 'processing'

        $wpdb->update(

            $table_name,

            ['status' => 'processing', 'updated_at' => current_time('mysql')],

            ['id' => $job->id]

        );



        // Ottieni i dati delle immagini

        $images_data = json_decode($job->images_data, true);

        if (empty($images_data)) {

            // Segna il job come completato se non ci sono immagini

            $wpdb->update(

                $table_name,

                ['status' => 'completed', 'updated_at' => current_time('mysql')],

                ['id' => $job->id]

            );



            // Pianifica una nuova esecuzione per controllare se ci sono altri job

            // Aggiungiamo un ritardo di 60 secondi per evitare un consumo eccessivo di crediti

            wp_schedule_single_event(time() + 60, IMGSEO_CRON_HOOK);



            return;

        }



        $overwrite = (bool) $job->overwrite;



        // Prepara le informazioni sul batch corrente

        $start_index = $job->processed_images;



        // Ottieni la dimensione del batch dalle impostazioni

        $batch_size = intval(get_option('imgseo_batch_size', 5));

        // Limita la dimensione del batch

        $batch_size = max(1, min($batch_size, 10));



        // Verifica i crediti disponibili e limita il batch se necessario

        $credits = get_option('imgseo_credits', 0);

        if ($credits <= 0) {

            // Se non ci sono crediti, aggiorna lo stato del job a "stopped" e esci

            $wpdb->update(

                $table_name,

                [

                    'status' => 'stopped',

                    'updated_at' => current_time('mysql')

                ],

                ['id' => $job->id]

            );

            error_log('ImgSEO: Insufficient credits, job stopped');

            return;

        }



        // Limita il batch in base ai crediti disponibili

        $batch_size = min($batch_size, $credits);



        $end_index = min($start_index + $batch_size, $job->total_images);



        // Verifica se il job è stato interrotto prima di elaborare le immagini

        $job_status = $wpdb->get_var($wpdb->prepare(

            "SELECT status FROM $table_name WHERE id = %d",

            $job->id

        ));



        if ($job_status === 'stopped') {

            return; // Salta questo job se è stato interrotto

        }



        // Elabora il batch di immagini

        $processed_now = 0;



        for ($i = $start_index; $i < $end_index; $i++) {

            if (!isset($images_data[$i])) {

                continue; // Salta indici non validi

            }



            $image_id = $images_data[$i];



            $current_alt_text = get_post_meta($image_id, '_wp_attachment_image_alt', true);



            if (!$overwrite && !empty($current_alt_text)) {

                // Conta come elaborata ma salta

                $processed_now++;

                continue;

            }



            $image_url = wp_get_attachment_url($image_id);



            if (!$image_url) {

                // Conta come elaborata ma salta

                $processed_now++;

                continue;

            }



            // Ottieni il titolo della pagina genitore se esiste

            $parent_post_id = wp_get_post_parent_id($image_id);

            $parent_post_title = $parent_post_id ? get_the_title($parent_post_id) : '';

            $filename = basename($image_url);



            // Verifica se il job è stato interrotto prima di elaborare questa immagine

            // Controllo sia lo stato nel DB che la transient di interruzione immediata

            $job_status = $wpdb->get_var($wpdb->prepare(

                "SELECT status FROM $table_name WHERE id = %d",

                $job->id

            ));



            $stop_transient = get_transient('imgseo_stop_job_' . $job->job_id);



            if ($job_status === 'stopped' || $stop_transient === 'yes') {

                // Se il job è stato interrotto, aggiorniamo lo stato e usciamo

                $wpdb->update(

                    $table_name,

                    ['status' => 'stopped', 'updated_at' => current_time('mysql')],

                    ['id' => $job->id]

                );



                break; // Interrompi l'elaborazione se il job è stato fermato

            }



            // Genera il testo alternativo

            $alt_text = $this->generate_alt_text($image_url, $image_id, $parent_post_title);

            $processed_now++;



            $log_status = 'success';



            if (is_wp_error($alt_text)) {

                $error_message = $alt_text->get_error_message();

                $log_status = 'error';

                $alt_text = $error_message;

            } else {

                // Aggiorna il testo alternativo

                update_post_meta($image_id, '_wp_attachment_image_alt', $alt_text);



                // Aggiorna gli altri campi in base alle opzioni

                $update_title = get_option('imgseo_update_title', 0);

                $update_caption = get_option('imgseo_update_caption', 0);

                $update_description = get_option('imgseo_update_description', 0);



                $attachment_data = ['ID' => $image_id];



                if ($update_title) {

                    $attachment_data['post_title'] = $alt_text;

                }



                if ($update_caption) {

                    $attachment_data['post_excerpt'] = $alt_text;

                }



                if ($update_description) {

                    $attachment_data['post_content'] = $alt_text;

                }



                if (count($attachment_data) > 1) {

                    wp_update_post($attachment_data);

                }

            }



            // Registra nel log

            $wpdb->insert(

                $log_table_name,

                [

                    'job_id' => $job->job_id,

                    'image_id' => $image_id,

                    'filename' => $filename,

                    'alt_text' => $alt_text,

                    'status' => $log_status,

                    'created_at' => current_time('mysql')

                ]

            );

        }



        // Aggiorna il job con il numero aggiornato di immagini elaborate

        $new_processed = $job->processed_images + $processed_now;

        $status = ($new_processed >= $job->total_images) ? 'completed' : 'processing';



        $wpdb->update(

            $table_name,

            [

                'processed_images' => $new_processed,

                'status' => $status,

                'updated_at' => current_time('mysql')

            ],

            ['id' => $job->id]

        );



        // Pianifica una nuova esecuzione con un ritardo di 60 secondi

        // Solo se ci sono ancora immagini da elaborare

        if ($status !== 'completed') {

            wp_schedule_single_event(time() + 60, IMGSEO_CRON_HOOK);

        }

    }



    /**

     * Aggiunge una colonna per il testo alternativo nella libreria media

     *

     * @param array $columns Colonne esistenti

     * @return array Colonne aggiornate

     */

    public function add_alt_text_column($columns) {

        $columns['alt_text'] = __('Testo Alt', IMGSEO_TEXT_DOMAIN);

        return $columns;

    }



    /**

     * Renderizza il contenuto della colonna del testo alternativo

     *

     * @param string $column_name Nome della colonna

     * @param int $post_id ID del post

     */

    public function render_alt_text_column($column_name, $post_id) {

        if ($column_name == 'alt_text') {

            $alt_text = get_post_meta($post_id, '_wp_attachment_image_alt', true);

            echo esc_html($alt_text);

        }

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

            error_log('ImgSEO: [PROTEZIONE] Elaborazione già in corso per ID: ' . $attachment_id . ', saltata');

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

            // Controllo per evitare tentativi eccessivi

            if ($attempt_number > 4) {

                error_log('ImgSEO: Too many attempts for ID: ' . $attachment_id . ', abandoning');

                delete_post_meta($attachment_id, '_imgseo_pending_generation');

                return;

            }



            // Verifica se un altro tentativo ha già avuto successo

            $pending_generation = get_post_meta($attachment_id, '_imgseo_pending_generation', true);

            $current_alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);



            // Se il flag non è più presente o abbiamo già un testo alternativo e non dobbiamo sovrascrivere, il processo è completato

            $overwrite = get_option('imgseo_overwrite', 0);

            if (empty($pending_generation) && !empty($current_alt_text) && !$overwrite) {

                error_log('ImgSEO: Alt text already present for ID: ' . $attachment_id . ', skipping this attempt');

                return;

            }



            // Verifica che l'allegato esista

            $attachment = get_post($attachment_id);

            if (!$attachment) {

                error_log('ImgSEO: Attachment not found with ID: ' . $attachment_id);

                delete_post_meta($attachment_id, '_imgseo_pending_generation');

                return;

            }



            // Verifica che sia un'immagine (controllo multiplo)

            $is_image = wp_attachment_is_image($attachment_id);

            $mime_type = get_post_mime_type($attachment_id);

            $is_image_mime = strpos($mime_type, 'image/') === 0;



            if (!$is_image && !$is_image_mime) {

                error_log('ImgSEO: ID ' . $attachment_id . ' is not an image (multiple check)');

                delete_post_meta($attachment_id, '_imgseo_pending_generation');

                return;

            }



            // Verifica crediti ImgSEO

            $credits = get_option('imgseo_credits', 0);

            if ($credits <= 0) {

                error_log('ImgSEO: Insufficient credits for automatic generation');

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

                        error_log('ImgSEO: Optimized URL (' . $size . ') found and verified: ' . $valid_url);

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

                        error_log('ImgSEO: Original URL used as fallback: ' . $valid_url);

                    }

                }

            }



            if (!$valid_url) {

                error_log('ImgSEO: No accessible image URL for ID: ' . $attachment_id);

                // Ripianifica solo se siamo sotto il limite di tentativi

                if ($attempt_number < 4) {

                    error_log('ImgSEO: Attempt #' . $attempt_number . ' failed, rescheduling for later');

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



            // Get attachment title for additional context

            $attachment_title = get_the_title($attachment_id);



            // Generate alt text

            error_log('ImgSEO: Generating alt text for ID ' . $attachment_id . ' with URL: ' . $valid_url);

            $alt_text = $this->generate_alt_text($valid_url, $attachment_id, $parent_post_title);



            if (is_wp_error($alt_text)) {

                $error_message = $alt_text->get_error_message();

                error_log('ImgSEO: Error generating alt text for ID ' . $attachment_id . ': ' . $error_message);



                // Reschedule only if it's a temporary error and we're under the attempt limit

                if (($attempt_number < 4) &&

                    (strpos($error_message, 'process the image') !== false ||

                     strpos($error_message, 'timeout') !== false ||

                     strpos($error_message, 'temporary') !== false)) {



                    // Incremento esponenziale del tempo di attesa tra tentativi

                    $wait_time = 30 * pow(2, $attempt_number - 1);

                    error_log('ImgSEO: Rescheduling generation in ' . $wait_time . ' seconds (attempt #' . ($attempt_number + 1) . ')');

                    if (!wp_next_scheduled('imgseo_single_generate', array($attachment_id, $attempt_number + 1))) {

                        wp_schedule_single_event(time() + $wait_time, 'imgseo_single_generate', array($attachment_id, $attempt_number + 1));

                    }

                } else {

                    // After too many attempts or permanent error, remove the pending flag

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



                // Add a small delay between operations

                usleep(100000); // 0.1 seconds



                // Phase 2: Add the new value

                add_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);



                // Phase 3: Force update with update_post_meta

                update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);



                // Verify that the update was successful

                $updated_alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);

                if ($updated_alt_text !== $alt_text) {

                    error_log('ImgSEO: Unable to update meta, forced attempt');

                    // Last forced attempt

                    update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text, '');

                }

            } finally {

                // RESTORE HOOKS even in case of errors

                if ($suspended_meta_hook) {

                    add_action('updated_post_meta', array(IMGSEO_Init::init(), 'check_image_alt_on_meta_update'), 15, 4);

                }

            }



            // Rimuovi il flag che indica che l'immagine è in attesa di generazione

            delete_post_meta($attachment_id, '_imgseo_pending_generation');



            error_log('ImgSEO: Testo alt aggiornato con successo per ID ' . $attachment_id . ': "' . $alt_text . '"');



            // Aggiungi un breve lock di 5 secondi per evitare ulteriori aggiornamenti immediati

            set_transient('imgseo_alt_updated_' . $attachment_id, true, 5);



            // Update other fields based on options

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

                // ALSO SUSPEND HOOKS TEMPORARILY HERE

                $suspended_hook = remove_action('attachment_updated', array(IMGSEO_Init::init(), 'handle_attachment_update'), 20);



                try {

                    $result = wp_update_post($attachment_data);



                    if (is_wp_error($result)) {

                        error_log('ImgSEO: Error updating additional metadata: ' . $result->get_error_message());

                    } else {

                        error_log('ImgSEO: Additional metadata successfully updated for ID: ' . $attachment_id);

                    }

                } finally {

                    // RESTORE HOOKS EVEN IN CASE OF ERRORS

                    if ($suspended_hook) {

                        add_action('attachment_updated', array(IMGSEO_Init::init(), 'handle_attachment_update'), 20);

                    }

                }

            }

        } catch (Exception $e) {

            error_log('ImgSEO: Exception during alt text generation: ' . $e->getMessage());

        } finally {

            // FINAL CLEANUP - always executed



            // Mark this ID as no longer being processed

            unset($processing_ids[$attachment_id]);



            // Remove the lock

            delete_transient('imgseo_processing_' . $attachment_id);

        }

    }



    /**

     * Rende sortabile la colonna del testo alternativo

     *

     * @param array $columns Colonne sortabili

     * @return array Colonne sortabili aggiornate

     */

    public function make_alt_text_column_sortable($columns) {

        $columns['alt_text'] = 'alt_text';

        return $columns;

    }



    /**

     * Registers bulk actions in the media library

     *

     * @param array $bulk_actions Existing bulk actions

     * @return array Updated bulk actions

     */

    public function register_bulk_actions($bulk_actions) {

        $bulk_actions['imgseo_generate_alt'] = __('Generate Alt Texts', IMGSEO_TEXT_DOMAIN);

        $bulk_actions['imgseo_generate_alt_overwrite'] = __('Genera Testi Alternativi (Sovrascrivi)', IMGSEO_TEXT_DOMAIN);

        return $bulk_actions;

    }



    /**

     * Gestisce le azioni bulk nella libreria media

     *

     * @param string $redirect_to URL di reindirizzamento

     * @param string $doaction Azione da eseguire

     * @param array $post_ids IDs of selected posts

     * @return string Updated redirect URL

     */

    public function handle_bulk_actions($redirect_to, $doaction, $post_ids) {

        if ($doaction !== 'imgseo_generate_alt' && $doaction !== 'imgseo_generate_alt_overwrite') {

            return $redirect_to;

        }



        $overwrite = ($doaction === 'imgseo_generate_alt_overwrite');

        $processed = 0;



        foreach ($post_ids as $post_id) {

            if (!wp_attachment_is_image($post_id)) {

                continue;

            }



            $current_alt_text = get_post_meta($post_id, '_wp_attachment_image_alt', true);

            if (!$overwrite && !empty($current_alt_text)) {

                continue; // Salta se non deve sovrascrivere e ha già un testo alt

            }



            // Use smaller image size for bulk operations to optimize API calls

            $image_url = null;

            $image_sizes = array('large', 'medium_large', 'medium', 'thumbnail');



            // Try to get a thumbnail version first

            foreach ($image_sizes as $size) {

                $image_size = wp_get_attachment_image_src($post_id, $size);

                if ($image_size && is_array($image_size) && !empty($image_size[0])) {

                    $image_url = $image_size[0];

                    break;

                }

            }



            // Fallback to original if no thumbnails available

            if (!$image_url) {

                $image_url = wp_get_attachment_url($post_id);

            }



            if (!$image_url) {

                continue; // Skip if no valid URL found

            }



            $parent_post_id = wp_get_post_parent_id($post_id);

            $parent_post_title = $parent_post_id ? get_the_title($parent_post_id) : '';



            $alt_text = $this->generate_alt_text($image_url, $post_id, $parent_post_title);



            if (!is_wp_error($alt_text)) {

                update_post_meta($post_id, '_wp_attachment_image_alt', $alt_text);

                $processed++;



                // Aggiorna gli altri campi in base alle opzioni

                $update_title = get_option('imgseo_update_title', 0);

                $update_caption = get_option('imgseo_update_caption', 0);

                $update_description = get_option('imgseo_update_description', 0);



                $attachment_data = ['ID' => $post_id];



                if ($update_title) {

                    $attachment_data['post_title'] = $alt_text;

                }



                if ($update_caption) {

                    $attachment_data['post_excerpt'] = $alt_text;

                }



                if ($update_description) {

                    $attachment_data['post_content'] = $alt_text;

                }



                if (count($attachment_data) > 1) {

                    wp_update_post($attachment_data);

                }

            }

        }



        $redirect_to = add_query_arg('imgseo_processed', $processed, $redirect_to);

        return $redirect_to;

    }

}
