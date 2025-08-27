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

        imgseo_debug_log('generate_alt_text chiamato per URL: ' . $image_url . ', ID: ' . $attachment_id);



        // Ottieni le impostazioni

        $language_code = get_option('imgseo_language', 'english');

        $max_characters = intval(get_option('imgseo_max_characters', 125));

        $include_page_title = get_option('imgseo_include_page_title', 1);

        $include_image_name = get_option('imgseo_include_image_name', 1);



        imgseo_debug_log('Impostazioni - lingua: ' . $language_code . ', max_chars: ' . $max_characters);



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



        // Check if this is a WooCommerce product image
        $is_woocommerce_product = false;
        $parent_post_id = wp_get_post_parent_id($attachment_id);
        
        if ($parent_post_id) {
            $post_type = get_post_type($parent_post_id);
            if ($post_type === 'product') {
                $is_woocommerce_product = true;
                error_log("ImgSEO Generator: Detected WooCommerce product image for post ID: {$parent_post_id}");
            }
        }
        
        // Get the appropriate prompt template based on post type
        $enable_woocommerce_prompt = get_option('imgseo_enable_woocommerce_prompt', 0);
        
        if ($is_woocommerce_product && $enable_woocommerce_prompt) {
            // Use WooCommerce product prompt
            $prompt_template = get_option('imgseo_woocommerce_prompt', 'Generate alt text for this product image: {product_name} by {product_brand}\n\nContext: {product_short_description} - {product_categories} - {product_price} {on_sale} - {product_attributes}\n\nDescribe the visual elements shown (colors, style, angle, details) while naturally incorporating the product name. Keep it descriptive, SEO-friendly, under {max_characters} characters in {language}. Focus on what customers would want to know about this product image.');
            error_log("ImgSEO Generator: Using WooCommerce product prompt");
        } else {
            // Use standard prompt
            $prompt_template = get_option('imgseo_custom_prompt', 'Generate SEO-friendly alt text describing the main visual elements of this image. Output only the alt text in {language}, maximum {max_characters} characters. {page_title_info} {image_name_info} Naturally incorporate relevant keywords and aim to be as close as possible to the specified character limit.');
        }



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
        
        // Variabili WooCommerce specifiche
        $product_name = '';
        $product_brand = '';
        $product_short_description = '';
        $product_categories = '';
        $product_price = '';
        $on_sale = '';
        $product_attributes = '';
        
        // Se è un prodotto WooCommerce, estrai le informazioni del prodotto
        if ($is_woocommerce_product && $enable_woocommerce_prompt && $parent_post_id && function_exists('wc_get_product')) {
            $product = wc_get_product($parent_post_id);
            
            if ($product) {
                // Estrai le informazioni del prodotto
                $product_name = $product->get_name();
                $product_short_description = wp_strip_all_tags($product->get_short_description());
                $product_price = $product->get_price_html();
                $product_stock_status = $product->is_in_stock() ? 'available' : 'out of stock';
                
                // Categorie del prodotto
                $product_categories = implode(', ', wp_get_post_terms($parent_post_id, 'product_cat', ['fields' => 'names']));
                
                // Brand del prodotto (attributo)
                $product_brand = $product->get_attribute('brand') ?: $product->get_attribute('pa_brand');
                
                // Stato di vendita
                $on_sale = $product->is_on_sale() ? '(ON SALE)' : '';
                
                // Attributi del prodotto
                $product_attributes = $this->get_product_attributes_string($product);
                
                error_log("ImgSEO Generator: Extracted WooCommerce product info for product ID: {$parent_post_id}");
            }
        }

        // Sostituisci le variabili nel template
        $replacement_vars = [
            '{language}', '{max_characters}', '{page_title_info}', '{image_name_info}',
            '{product_name}', '{product_brand}', '{product_short_description}', '{product_categories}',
            '{product_price}', '{on_sale}', '{product_attributes}'
        ];
        
        $replacement_values = [
            $language_name, $max_characters, $page_title_info, $image_name_info,
            $product_name, $product_brand, $product_short_description, $product_categories,
            $product_price, $on_sale, $product_attributes
        ];
        
        $prompt = str_replace($replacement_vars, $replacement_values, $prompt_template);



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

    /**
     * Estrae gli attributi del prodotto WooCommerce come stringa
     *
     * @param WC_Product $product Il prodotto WooCommerce
     * @return string Gli attributi del prodotto come stringa
     */
    private function get_product_attributes_string($product) {
        if (!$product) {
            return '';
        }
        
        $attributes = [];
        $product_attributes = $product->get_attributes();
        
        foreach ($product_attributes as $attribute) {
            if ($attribute->get_variation()) {
                continue; // Salta gli attributi di variazione
            }
            
            $attribute_name = wc_attribute_label($attribute->get_name());
            $attribute_values = [];
            
            if ($attribute->is_taxonomy()) {
                $terms = wp_get_post_terms($product->get_id(), $attribute->get_name(), ['fields' => 'names']);
                $attribute_values = $terms;
            } else {
                $attribute_values = $attribute->get_options();
            }
            
            if (!empty($attribute_values)) {
                $attributes[] = $attribute_name . ': ' . implode(', ', $attribute_values);
            }
        }
        
        return implode(' | ', $attributes);
    }

}
