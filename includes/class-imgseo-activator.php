<?php

/**

 * Classe ImgSEO_Activator

 * Gestisce l'attivazione del plugin e la creazione delle tabelle di database

 */

class ImgSEO_Activator {



    /**

     * Esegui le operazioni di attivazione del plugin

     */

    public static function activate() {

        // Crea tabella per tenere traccia dei lavori di elaborazione

        self::create_jobs_table();



        // Crea tabella per i log di elaborazione

        self::create_logs_table();



        // Configura i cron job

        self::setup_cron_jobs();



        // Imposta le opzioni predefinite

        self::set_default_options();

    }



    /**

     * Crea la tabella dei job

     */

    private static function create_jobs_table() {

        global $wpdb;

        $table_name = $wpdb->prefix . 'imgseo_jobs';



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



    /**

     * Crea la tabella dei log

     */

    private static function create_logs_table() {

        global $wpdb;

        $log_table_name = $wpdb->prefix . 'imgseo_logs';



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



        // Create table for image rename logs

        self::create_rename_logs_table();

    }



    /**

     * Create the table for image renamer logs

     */

    private static function create_rename_logs_table() {

        global $wpdb;

        $log_table = $wpdb->prefix . 'imgseo_rename_logs';



        $charset_collate = $wpdb->get_charset_collate();



        $sql = "CREATE TABLE $log_table (

            id bigint(20) NOT NULL AUTO_INCREMENT,

            image_id bigint(20) NOT NULL,

            old_filename varchar(255) NOT NULL,

            new_filename varchar(255) NOT NULL,

            status varchar(20) NOT NULL DEFAULT 'success',

            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,

            PRIMARY KEY  (id),

            KEY image_id (image_id),

            KEY created_at (created_at)

        ) $charset_collate;";



        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($sql);

    }



    /**

     * Configura i cron job

     */

    private static function setup_cron_jobs() {

        // Aggiungi l'intervallo personalizzato per il cron

        add_filter('cron_schedules', array('ImgSEO_Activator', 'add_cron_schedules'));



        // Rimuovi qualsiasi evento cron esistente per evitare duplicazioni

        wp_clear_scheduled_hook(IMGSEO_CRON_HOOK);

        wp_clear_scheduled_hook('imgseo_check_stuck_jobs');



        // Pianifica il controllo dei job bloccati ogni giorno

        wp_schedule_event(time(), 'daily', 'imgseo_check_stuck_jobs');

    }



    /**

     * Aggiunge intervalli personalizzati per i cron job

     */

    public static function add_cron_schedules($schedules) {

        $schedules['every_minute'] = array(

            'interval' => 60,

            'display'  => __('Ogni Minuto', IMGSEO_TEXT_DOMAIN)

        );

        $schedules['every_30_seconds'] = array(

            'interval' => 30,

            'display'  => __('Ogni 30 Secondi', IMGSEO_TEXT_DOMAIN)

        );

        $schedules['every_2_minutes'] = array(

            'interval' => 120,

            'display'  => __('Ogni 2 Minuti', IMGSEO_TEXT_DOMAIN)

        );

        $schedules['every_5_minutes'] = array(

            'interval' => 300,

            'display'  => __('Ogni 5 Minuti', IMGSEO_TEXT_DOMAIN)

        );

        return $schedules;

    }



    /**

     * Imposta le opzioni predefinite

     */

    private static function set_default_options() {

        // Impostazioni generali

        add_option('imgseo_language', 'english');

        add_option('imgseo_max_characters', 125);

        add_option('imgseo_include_page_title', 1);

        add_option('imgseo_include_image_name', 1);

        add_option('imgseo_overwrite', 0);



        // Impostazioni automatiche

        add_option('imgseo_auto_generate', 0);

        add_option('imgseo_batch_size', 5);

        add_option('imgseo_cron_interval', 'every_2_minutes');



        // Impostazioni di aggiornamento

        add_option('imgseo_update_title', 0);

        add_option('imgseo_update_caption', 0);

        add_option('imgseo_update_description', 0);



        // Impostazioni API

        add_option('imgseo_api_verified', 0);

        add_option('imgseo_last_cron_run', 0);

    }

}
