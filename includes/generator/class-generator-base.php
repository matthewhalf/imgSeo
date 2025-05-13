<?php

/**

 * Classe base per le funzionalità di generazione

 *

 * @package ImgSEO

 * @since 1.0.0

 */



// Exit if accessed directly

if (!defined('ABSPATH')) {

    exit;

}



/**

 * Classe ImgSEO_Generator_Base

 * Fornisce funzionalità di base comuni a tutte le classi di generazione

 */

class ImgSEO_Generator_Base {



    /**

     * Crea la tabella dei log se non esiste

     */

    protected function create_logs_table_if_not_exists() {

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

     * Tenta di avviare un'esecuzione immediata del cron

     *

     * @param bool $force_immediate Se true, usa un timeout più lungo per assicurare l'avvio

     * @return bool True se la richiesta è stata inviata

     */

    protected function try_trigger_cron($force_immediate = false) {

        // Crea una richiesta non bloccante al wp-cron.php

        $cron_url = site_url('wp-cron.php?doing_wp_cron=1&imgseo_force=1');



        // Se richiesto avvio immediato, usa un timeout più lungo per garantire l'esecuzione

        $timeout = $force_immediate ? 1.0 : 0.01;



        $args = [

            'blocking'  => false,

            'sslverify' => apply_filters('https_local_ssl_verify', false),

            'timeout'   => $timeout,

            'headers'   => [

                'Cache-Control' => 'no-cache',

            ]

        ];



        $response = wp_remote_get($cron_url, $args);



        // Log per debug

        error_log('ImgSEO: Avviato cron con timeout ' . $timeout . ' secondi');



        return !is_wp_error($response);

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

        error_log('ImgSEO DEBUG: generate_alt_text chiamato per URL: ' . $image_url . ', ID: ' . $attachment_id);



        // Ottieni le impostazioni

        $language_code = get_option('imgseo_language', 'english');

        $max_characters = intval(get_option('imgseo_max_characters', 125));

        $include_page_title = get_option('imgseo_include_page_title', 1);

        $include_image_name = get_option('imgseo_include_image_name', 1);



        error_log('ImgSEO DEBUG: Impostazioni - lingua: ' . $language_code . ', max_chars: ' . $max_characters);



        // Mappa dei codici di lingua ai nomi visualizzati (per il prompt)

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



        // Ottieni il nome della lingua per il prompt dalla mappatura o usa il codice come fallback

        $language_name = isset($languages[$language_code]) ? $languages[$language_code] : $language_code;



        // Log per debug

        error_log("ImgSEO Generator: Using language '{$language_name}' from setting '{$language_code}'");



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

            [$language_name, $max_characters, $page_title_info, $image_name_info],

            $prompt_template

        );



        // Pulisci il prompt (rimuovi spazi multipli che potrebbero essere creati)

        $prompt = preg_replace('/\s+/', ' ', $prompt);

        $prompt = trim($prompt);



        // Verifica se esiste un blocco globale di processo

        if (class_exists('ImgSEO_Process_Lock') && ImgSEO_Process_Lock::is_globally_locked()) {

            return new WP_Error('process_locked', __('Le operazioni ImgSEO sono temporaneamente bloccate. Riprova tra qualche istante.', IMGSEO_TEXT_DOMAIN));

        }



        // Opzioni da passare all'API

        $options = [

            'lang' => $language_code, // Passa il codice lingua, non il nome

            'optimize' => true,

            'attachment_id' => $attachment_id

        ];



        // Ottieni l'istanza API

        $api = ImgSEO_API::get_instance();



        // Verifica API key

        if (empty($api->get_api_key())) {

            return new WP_Error('missing_api_key', 'ImgSEO Token non configurato. Configura il token nelle impostazioni.');

        }



        // Verifica i crediti disponibili dal database locale (senza forzare refresh)

        // L'API aggiornerà automaticamente i crediti durante la chiamata di generazione

        $credits = get_option('imgseo_credits', 0);



        // Log per debug ma non blocchiamo se i crediti sono insufficienti

        // Potrebbe essere che i crediti nel DB non siano aggiornati

        if ($credits <= 0) {

            error_log('ImgSEO: ATTENZIONE - Crediti locali insufficienti, ma procediamo comunque');

        }



        // Use ImgSEO API to generate alt text

        $response = $api->generate_alt_text($image_url, $prompt, $options);



        if (is_wp_error($response)) {

            return $response;

        }



        // Solo il nuovo formato API

        if (!isset($response['alt_text'])) {

            return new WP_Error('invalid_response', 'Risposta API non valida: manca alt_text');

        }



        $alt_text = $response['alt_text'];



        // Pulisci il testo alternativo

        $alt_text = str_replace('"', '', $alt_text);

        $alt_text = trim($alt_text);



        return $alt_text;

    }

}
