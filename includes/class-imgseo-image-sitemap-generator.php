<?php
/**
 * Class ImgSEO_Image_Sitemap_Generator
 * Gestisce la generazione della sitemap per le immagini.
 *
 * @package ImgSEO
 * @since   1.2.0 // Supponendo una nuova versione
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ImgSEO_Image_Sitemap_Generator {

	/**
	 * Singleton instance
	 *
	 * @var ImgSEO_Image_Sitemap_Generator|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance
	 *
	 * @return ImgSEO_Image_Sitemap_Generator
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 * Registra gli hook specifici per la sitemap.
	 */
	private function __construct() {
		// Hook per intercettare la richiesta della sitemap
		// Questo verrà gestito tramite la rewrite rule e un query_var
		add_action( 'parse_request', array( $this, 'handle_sitemap_request_check' ) );
	}

	/**
	 * Azioni da eseguire all'attivazione del plugin (principalmente flush rewrite).
	 * Questo metodo sarà chiamato da imgseo.php
	 */
	public function activate() {
		$this->register_sitemap_rewrite_rule();
		update_option( 'imgseo_sitemap_enabled', true ); // Abilita sitemap di default
		flush_rewrite_rules();
	}

	/**
	 * Azioni da eseguire alla disattivazione del plugin.
	 * Questo metodo sarà chiamato da imgseo.php
	 */
	public function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Registra la rewrite rule per la sitemap delle immagini.
	 * Questo metodo sarà chiamato dall'hook 'init' in imgseo.php.
	 */
	public function register_sitemap_rewrite_rule() {
		// Aggiungiamo una query var per identificare la nostra richiesta
		add_filter( 'query_vars', function( $vars ) {
			$vars[] = 'imgseo_image_sitemap';
			return $vars;
		} );

		// Aggiungiamo la regola di riscrittura
		add_rewrite_rule(
			'^imgseo-sitemap\.xml$',
			'index.php?imgseo_image_sitemap=1',
			'top'
		);

		// Aggiungiamo un hook alternativo per servire la sitemap via template_redirect
		// Questo può funzionare anche se le rewrite rules non funzionano correttamente
		add_action( 'template_redirect', array( $this, 'maybe_serve_sitemap' ) );
	}

	/**
	 * Metodo alternativo per servire la sitemap tramite template_redirect
	 * Questo può intercettare la richiesta anche se le rewrite rules non funzionano
	 *
	 * @since 1.2.0
	 */
	public function maybe_serve_sitemap() {
		$current_url = $_SERVER['REQUEST_URI'];
		
		// Se l'URL termina con 'imgseo-sitemap.xml', servi la sitemap
		if ( preg_match( '/imgseo-sitemap\.xml$/i', $current_url ) ) {
			$sitemap_enabled = (bool) get_option( 'imgseo_sitemap_enabled', true );
			if ( ! $sitemap_enabled ) {
				return; // La sitemap è disabilitata, lascia che WordPress gestisca il 404
			}
			
			$this->handle_sitemap_request();
			exit;
		}
	}

	/**
	 * Controlla se la richiesta corrente è per la sitemap delle immagini
	 * e chiama il metodo per gestirla.
	 *
	 * @param WP $wp WordPress request object.
	 */
	public function handle_sitemap_request_check( $wp ) {
		if ( isset( $wp->query_vars['imgseo_image_sitemap'] ) && $wp->query_vars['imgseo_image_sitemap'] == '1' ) {
			$sitemap_enabled = (bool) get_option( 'imgseo_sitemap_enabled', true ); // Default a true
			if ( ! $sitemap_enabled ) {
				// Se la sitemap non è abilitata nelle opzioni, non generarla.
				// WordPress dovrebbe restituire un 404 standard se la rewrite rule esiste ancora
				// ma noi non serviamo contenuti.
				// Potremmo anche inviare un header 403 o un commento XML.
				// Per ora, semplicemente non facciamo nulla, lasciando che WP gestisca.
				// status_header( 403 ); // Esempio: Forbidden
				// echo '<!-- ImgSEO Image Sitemap is disabled in plugin settings. -->';
				// exit;
				return;
			}
			$this->handle_sitemap_request();
			exit; // Termina l'esecuzione di WordPress dopo aver servito la sitemap
		}
	}

	/**
	 * Gestisce la richiesta della sitemap: orchestra la generazione e l'output.
	 */
	private function handle_sitemap_request() {
		try {
			// Log di debug per tracciare l'esecuzione
			error_log('ImgSEO Image Sitemap: handle_sitemap_request chiamato');
			
			// Per debug, aggiungiamo un parametro che permette di generare una sitemap minima di test
			if (isset($_GET['test']) && $_GET['test'] === '1') {
				error_log('ImgSEO Image Sitemap: Generando sitemap di test');
				// Genera una sitemap minima per test
				$test_xml = $this->generate_test_sitemap_xml();
				header( 'Content-Type: application/xml; charset=UTF-8' );
				echo $test_xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML output
				return;
			}
			
			// Prima controlla se esiste un file sitemap statico
			$sitemap_filepath = $this->get_sitemap_filepath();
			if (file_exists($sitemap_filepath)) {
				error_log('ImgSEO Image Sitemap: Servendo file statico da: ' . $sitemap_filepath);
				
				// Se il file esiste ed è accessibile, lo serviamo direttamente
				header( 'Content-Type: application/xml; charset=UTF-8' );
				readfile($sitemap_filepath);
				return;
			}
			
			// Se non esiste il file statico, procedi con la generazione dinamica
			error_log('ImgSEO Image Sitemap: Nessun file statico trovato, generando dinamicamente');
			error_log('ImgSEO Image Sitemap: Iniziando raccolta dati immagini');
			$image_data = $this->collect_image_data();
			error_log('ImgSEO Image Sitemap: Dati raccolti, elementi: ' . count($image_data));
			
			$sitemap_xml = $this->generate_sitemap_xml( $image_data );
			error_log('ImgSEO Image Sitemap: XML generato, lunghezza: ' . strlen($sitemap_xml));

			header( 'Content-Type: application/xml; charset=UTF-8' );
			echo $sitemap_xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML output
		} catch (Exception $e) {
			error_log('ImgSEO Image Sitemap ERROR: ' . $e->getMessage());
			
			// In caso di errore, genera una sitemap di errore
			header( 'Content-Type: application/xml; charset=UTF-8' );
			echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
			echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . PHP_EOL;
			echo '<!-- ImgSEO Image Sitemap Error: ' . esc_html($e->getMessage()) . ' -->' . PHP_EOL;
			echo '</urlset>';
		}
	}

	/**
	 * Raccoglie i dati delle immagini e delle pagine per la sitemap.
	 *
	 * @return array Dati raccolti.
	 */
	private function collect_image_data() {
		$sitemap_entries = array();
		$processed_pages = array(); // Per evitare duplicati di pagine se più immagini sono sulla stessa pagina
		$processed_images = array(); // Per evitare duplicati di immagini tra quelle collegate e quelle orfane

		// ========== PARTE 1: Raccoglie immagini collegate a post/pagine ==========
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		if ( empty( $post_types ) ) {
			return $sitemap_entries;
		}

		// Rimuoviamo 'attachment' dai post types da scansionare direttamente per le pagine
		if ( isset( $post_types['attachment'] ) ) {
			unset( $post_types['attachment'] );
		}
		
		$args = array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => -1, // Processa tutti i post
			'fields'         => 'ids', // Ottieni solo gli ID per efficienza
		);

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post_id ) {
				$page_url = get_permalink( $post_id );
				$page_images = array();

				// Recupera immagini allegate dalla Libreria Media
				$attachments_args = array(
					'post_type'      => 'attachment',
					'post_mime_type' => 'image',
					'posts_per_page' => -1,
					'post_parent'    => $post_id, // Immagini direttamente allegate a questo post
					'post_status'    => 'inherit',
				);
				$attachments = get_posts( $attachments_args );

				if ( ! empty( $attachments ) ) {
					foreach ( $attachments as $attachment ) {
						$image_url = wp_get_attachment_url( $attachment->ID );
						if ( ! $image_url ) {
							continue;
						}

						// Segna questa immagine come già processata
						$processed_images[$attachment->ID] = true;

						$image_entry = array(
							'loc' => $image_url,
						);

						// Titolo dell'immagine dalla Libreria Media
						if ( ! empty( $attachment->post_title ) ) {
							$image_entry['title'] = $attachment->post_title;
						}

						// Didascalia dell'immagine dalla Libreria Media
						if ( ! empty( $attachment->post_excerpt ) ) {
							$image_entry['caption'] = $attachment->post_excerpt;
						}
						$page_images[] = $image_entry;
					}
				}
				
				// TODO: Fase 2 - Scansione del contenuto per immagini non direttamente allegate
				// (es. immagini inserite tramite URL, o da page builder se non usano allegati)

				if ( ! empty( $page_images ) ) {
					// Gestione WPML (base)
					if ( class_exists( 'SitePress' ) ) { // Verifica se WPML è attivo
						global $sitepress;
						$trid = $sitepress->get_element_trid( $post_id, 'post_' . get_post_type( $post_id ) );
						if ( ! $trid ) { // Se non c'è trid, trattalo come non tradotto per questa logica
							if ( ! isset( $processed_pages[ $page_url ] ) ) {
								$sitemap_entries[] = array(
									'page_loc'   => $page_url,
									'images'     => $page_images,
								);
								$processed_pages[ $page_url ] = true;
							}
							continue; // Passa al prossimo post_id
						}
						$translations = $sitepress->get_element_translations( $trid, 'post_' . get_post_type( $post_id ) );

						foreach ( $translations as $translation ) {
							// Processa solo se la traduzione è pubblicata e attiva
							if ( $translation->element_id && get_post_status( $translation->element_id ) === 'publish' ) {
								$translated_page_url = get_permalink( $translation->element_id );
								// Per MVP, usiamo le stesse immagini dell'originale.
								// Una gestione più avanzata richiederebbe di trovare le immagini tradotte se WPML Media è usato.
								// Assicuriamoci di non aggiungere la stessa pagina più volte se WPML restituisce più volte la stessa lingua/url
								if ( ! isset( $processed_pages[ $translated_page_url ] ) ) {
									$sitemap_entries[] = array(
										'page_loc'   => $translated_page_url,
										'images'     => $page_images,
									);
									$processed_pages[ $translated_page_url ] = true;
								}
							}
						}
					} else {
						// Sito non multilingua o WPML non attivo
						if ( ! isset( $processed_pages[ $page_url ] ) ) {
							$sitemap_entries[] = array(
								'page_loc'   => $page_url,
								'images'     => $page_images,
							);
							$processed_pages[ $page_url ] = true;
						}
					}
				}
			}
		}
		wp_reset_postdata();
		
		// ========== PARTE 2: Raccoglie immagini orfane dalla libreria media ==========
		// Recupera tutte le immagini non collegate a nessun post o pagina
		$orphan_args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'post_parent'    => 0, // Solo immagini senza parent
			'fields'         => 'all',
		);
		$orphan_attachments = get_posts( $orphan_args );
		
		if ( ! empty( $orphan_attachments ) ) {
			$orphan_images = array();
			
			foreach ( $orphan_attachments as $attachment ) {
				// Salta le immagini già incluse nella prima parte
				if ( isset($processed_images[$attachment->ID]) ) {
					continue;
				}
				
				$image_url = wp_get_attachment_url( $attachment->ID );
				if ( ! $image_url ) {
					continue;
				}
				
				$image_entry = array(
					'loc' => $image_url,
				);
				
				// Titolo dell'immagine
				if ( ! empty( $attachment->post_title ) ) {
					$image_entry['title'] = $attachment->post_title;
				}
				
				// Didascalia dell'immagine
				if ( ! empty( $attachment->post_excerpt ) ) {
					$image_entry['caption'] = $attachment->post_excerpt;
				}
				
				$orphan_images[] = $image_entry;
			}
			
			// Aggiungi le immagini orfane alla sitemap se ce ne sono
			if ( ! empty( $orphan_images ) ) {
				// Per le immagini orfane, usiamo l'URL della pagina dell'attachment o la home
				$site_url = home_url();
				
				$sitemap_entries[] = array(
					'page_loc'   => $site_url, // Colleghiamo alla home o si potrebbe usare l'URL dell'archivio media
					'images'     => $orphan_images,
				);
			}
		}
		
		return $sitemap_entries;
	}

	/**
	 * Genera l'XML della sitemap dai dati raccolti.
	 *
	 * @param array $sitemap_data Dati per la sitemap.
	 * @return string XML della sitemap.
	 */
	private function generate_sitemap_xml( $sitemap_data ) {
		$xml_writer = new XMLWriter();
		$xml_writer->openMemory();
		$xml_writer->startDocument( '1.0', 'UTF-8' );
		$xml_writer->setIndent( true );
		$xml_writer->setIndentString( '  ' );

		$xml_writer->startElement( 'urlset' );
		$xml_writer->writeAttribute( 'xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9' );
		$xml_writer->writeAttribute( 'xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1' );

		if ( ! empty( $sitemap_data ) ) {
			foreach ( $sitemap_data as $entry ) {
				$xml_writer->startElement( 'url' );
				$xml_writer->writeElement( 'loc', htmlspecialchars( $entry['page_loc'] ) );

				if ( ! empty( $entry['images'] ) ) {
					foreach ( $entry['images'] as $image_info ) {
						$xml_writer->startElement( 'image:image' );
						$xml_writer->writeElement( 'image:loc', htmlspecialchars( $image_info['loc'] ) );

						if ( isset( $image_info['title'] ) ) {
							$xml_writer->writeElement( 'image:title', htmlspecialchars( $image_info['title'] ) );
						}
						if ( isset( $image_info['caption'] ) ) {
							$xml_writer->writeElement( 'image:caption', htmlspecialchars( $image_info['caption'] ) );
						}
						$xml_writer->endElement(); // image:image
					}
				}
				$xml_writer->endElement(); // url
			}
		}
		$xml_writer->endElement(); // urlset
		$xml_writer->endDocument();
		return $xml_writer->outputMemory();
	}
	
	/**
	 * Restituisce il percorso al file della sitemap statica
	 *
	 * @return string Percorso completo al file della sitemap
	 */
	public function get_sitemap_filepath() {
		// Salva nella root del sito invece che in uploads
		return ABSPATH . 'imgseo-sitemap.xml';
	}
	
	/**
	 * Restituisce l'URL del file della sitemap statica
	 *
	 * @return string URL completa al file della sitemap
	 */
	public function get_sitemap_url() {
		// URL diretta alla root del sito
		return home_url('/imgseo-sitemap.xml');
	}
	
	/**
	 * Genera la sitemap e la salva come file statico
	 *
	 * @return array Risultato dell'operazione con status e messaggio
	 */
	public function generate_sitemap_file() {
		try {
			error_log('ImgSEO Image Sitemap: Avvio generazione file sitemap statico');
			
			// Raccoglie i dati per la sitemap
			$image_data = $this->collect_image_data();
			if (empty($image_data)) {
				error_log('ImgSEO Image Sitemap: Nessuna immagine trovata');
				return array(
					'status' => 'warning',
					'message' => __('No images found to include in the sitemap.', 'imgseo')
				);
			}
			
			// Genera il contenuto XML
			$sitemap_xml = $this->generate_sitemap_xml($image_data);
			
			// Ottiene il percorso in cui salvare il file
			$sitemap_filepath = $this->get_sitemap_filepath();
			$sitemap_dir = dirname($sitemap_filepath);
			
			// Verifica che la directory sia scrivibile
			if (!is_writable($sitemap_dir)) {
				error_log('ImgSEO Image Sitemap ERROR: Root directory is not writable');
				return array(
					'status' => 'error',
					'message' => __('Could not write sitemap file. The WordPress root directory is not writable. Please check your server permissions.', 'imgseo')
				);
			}
			
			// Security: Validate the filepath before writing
			$safe_path = realpath(dirname($sitemap_filepath));
			$expected_path = realpath(ABSPATH);
			
			if ($safe_path !== $expected_path) {
				error_log('ImgSEO Security: Invalid sitemap filepath: ' . $sitemap_filepath);
				return array(
					'status' => 'error',
					'message' => __('Security error: Invalid sitemap path.', 'imgseo')
				);
			}
			
			// Salva il contenuto nel file
			$result = file_put_contents($sitemap_filepath, $sitemap_xml);
			
			if ($result !== false) {
				// Successo, aggiorna il timestamp dell'ultima generazione
				update_option('imgseo_sitemap_last_generated', current_time('timestamp'));
				error_log('ImgSEO Image Sitemap: File sitemap generato con successo: ' . $sitemap_filepath);
				
				// Conteggio effettivo del numero totale di immagini
				$total_images = 0;
				foreach ($image_data as $entry) {
					$total_images += count($entry['images']);
				}
				
				return array(
					'status' => 'success',
					'message' => sprintf(
						__('Image sitemap successfully generated with %d images. Available at: %s', 'imgseo'),
						$total_images,
						$this->get_sitemap_url()
					)
				);
			} else {
				error_log('ImgSEO Image Sitemap ERROR: Impossibile scrivere il file sitemap');
				return array(
					'status' => 'error',
					'message' => __('Could not write sitemap file. Please check folder permissions.', 'imgseo')
				);
			}
			
		} catch (Exception $e) {
			error_log('ImgSEO Image Sitemap ERROR: ' . $e->getMessage());
			return array(
				'status' => 'error',
				'message' => $e->getMessage()
			);
		}
	}
	
	/**
	 * Genera una sitemap XML minima per test
	 *
	 * @return string XML minimo per test
	 */
	private function generate_test_sitemap_xml() {
		$site_url = home_url();
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . PHP_EOL;
		$xml .= '  <url>' . PHP_EOL;
		$xml .= '    <loc>' . esc_url($site_url) . '</loc>' . PHP_EOL;
		$xml .= '    <image:image>' . PHP_EOL;
		$xml .= '      <image:loc>' . esc_url(admin_url('images/wordpress-logo.png')) . '</image:loc>' . PHP_EOL;
		$xml .= '      <image:title>WordPress Logo</image:title>' . PHP_EOL;
		$xml .= '    </image:image>' . PHP_EOL;
		$xml .= '  </url>' . PHP_EOL;
		$xml .= '</urlset>';
		
		return $xml;
	}

	/**
	 * Renderizza la pagina di amministrazione per la sitemap.
	 * Questo metodo sarà chiamato da ImgSEO_Menu_Manager.
	 */
	public function render_admin_page() {
		// Gestione delle richieste ACTIVATE
		$generate_result = null;
		if ( isset( $_POST['imgseo_activate_sitemap_nonce'] ) &&
			wp_verify_nonce( sanitize_key( $_POST['imgseo_activate_sitemap_nonce'] ), 'imgseo_activate_sitemap_action' ) ) {
			
			if ( isset( $_POST['imgseo_activate_sitemap'] ) ) {
				// L'utente ha richiesto di attivare la sitemap
				$generate_result = $this->generate_sitemap_file();
				
				// Flush rewrite rules dopo l'attivazione
				flush_rewrite_rules();
				
				// Rimuovi il flag di aggiornamento necessario
				update_option('imgseo_sitemap_needs_update', false);
				
				// Mostriamo il messaggio appropriato in base al risultato
				$notice_class = ($generate_result['status'] === 'success') ? 'notice-success' :
							   (($generate_result['status'] === 'warning') ? 'notice-warning' : 'notice-error');
				
				echo '<div class="notice ' . esc_attr($notice_class) . ' is-dismissible"><p>' . esc_html($generate_result['message']) . '</p></div>';
			}
		}
		
		// Gestione delle richieste REFRESH
		if ( isset( $_POST['imgseo_refresh_sitemap_nonce'] ) &&
			wp_verify_nonce( sanitize_key( $_POST['imgseo_refresh_sitemap_nonce'] ), 'imgseo_refresh_sitemap_action' ) ) {
			
			if ( isset( $_POST['imgseo_refresh_sitemap'] ) ) {
				// L'utente ha richiesto di aggiornare la sitemap
				$generate_result = $this->generate_sitemap_file();
				
				// Flush rewrite rules dopo il refresh
				flush_rewrite_rules();
				
				// Rimuovi il flag di aggiornamento necessario
				update_option('imgseo_sitemap_needs_update', false);
				
				// Mostriamo il messaggio appropriato in base al risultato
				$notice_class = ($generate_result['status'] === 'success') ? 'notice-success' :
							   (($generate_result['status'] === 'warning') ? 'notice-warning' : 'notice-error');
				
				echo '<div class="notice ' . esc_attr($notice_class) . ' is-dismissible"><p>' . esc_html($generate_result['message']) . '</p></div>';
			}
		}
		
		// Gestione delle richieste di generazione legacy (per compatibilità)
		if ( isset( $_POST['imgseo_generate_sitemap_nonce'] ) &&
			wp_verify_nonce( sanitize_key( $_POST['imgseo_generate_sitemap_nonce'] ), 'imgseo_generate_sitemap_action' ) ) {
			
			if ( isset( $_POST['imgseo_generate_sitemap'] ) ) {
				// L'utente ha richiesto di generare/aggiornare la sitemap (legacy)
				$generate_result = $this->generate_sitemap_file();
				
				// Rimuovi il flag di aggiornamento necessario
				update_option('imgseo_sitemap_needs_update', false);
				
				// Mostriamo il messaggio appropriato in base al risultato
				$notice_class = ($generate_result['status'] === 'success') ? 'notice-success' :
							   (($generate_result['status'] === 'warning') ? 'notice-warning' : 'notice-error');
				
				echo '<div class="notice ' . esc_attr($notice_class) . ' is-dismissible"><p>' . esc_html($generate_result['message']) . '</p></div>';
			}
		}
		
		// Aggiungiamo informazioni di debug per aiutare a diagnosticare problemi con la sitemap
		global $wp_rewrite;
		$rewrite_rules = $wp_rewrite->wp_rewrite_rules();
		$has_sitemap_rule = false;
		
		if ( is_array( $rewrite_rules ) ) {
			foreach ( $rewrite_rules as $rule => $rewrite ) {
				if ( strpos( $rule, 'imgseo-sitemap\.xml' ) !== false ) {
					$has_sitemap_rule = true;
					break;
				}
			}
		}
		
		// Verifica se la sitemap esiste come file statico
		$sitemap_filepath = $this->get_sitemap_filepath();
		$sitemap_exists = file_exists($sitemap_filepath);
		$last_generated = get_option('imgseo_sitemap_last_generated', 0);
		
		// Il percorso al file template
		$template_path = IMGSEO_DIRECTORY_PATH . 'templates/image-sitemap-admin-page.php';

		if ( file_exists( $template_path ) ) {
			// Passiamo le informazioni al template
			$template_data = array(
				// Info sulla sitemap
				'sitemap_exists' => $sitemap_exists,
				'sitemap_url' => $this->get_sitemap_url(),
				'last_generated' => $last_generated,
				
				// Debug info
				'debug_info' => array(
					'has_sitemap_rule' => $has_sitemap_rule,
					'permalink_structure' => get_option( 'permalink_structure' ),
					'sitemap_filepath' => $sitemap_filepath,
				),
			);
			
			// Rendiamo disponibili le informazioni nel template
			set_query_var( 'imgseo_sitemap_data', $template_data );
			
			include $template_path;
		} else {
			// Fallback se il template non esiste
			echo '<div class="wrap"><h2>' . esc_html__( 'Image Sitemap Settings', 'imgseo' ) . '</h2><p>' . esc_html__( 'Error: Admin page template not found.', 'imgseo' ) . '</p></div>';
		}
	}

	/**
	 * Invalida la cache della sitemap (se implementata).
	 * Chiamato dagli hook di aggiornamento contenuti.
	 */
	public function invalidate_sitemap_cache() {
		// Marca la sitemap come bisognosa di aggiornamento
		update_option('imgseo_sitemap_needs_update', true);
		error_log('ImgSEO Image Sitemap: Sitemap marked as needing update.');
	}
	
	/**
	 * Programma il cron job per l'aggiornamento automatico della sitemap
	 *
	 * @param string $interval Intervallo di aggiornamento (hourly, daily, weekly)
	 */
	public function schedule_auto_refresh($interval = 'daily') {
		// Rimuovi eventuali cron job esistenti
		wp_clear_scheduled_hook('imgseo_auto_refresh_sitemap');
		
		// Programma il nuovo cron job
		wp_schedule_event(time(), $interval, 'imgseo_auto_refresh_sitemap');
		
		error_log('ImgSEO Image Sitemap: Auto-refresh scheduled for ' . $interval);
	}
	
	/**
	 * Rimuove il cron job per l'aggiornamento automatico della sitemap
	 */
	public function unschedule_auto_refresh() {
		wp_clear_scheduled_hook('imgseo_auto_refresh_sitemap');
		error_log('ImgSEO Image Sitemap: Auto-refresh unscheduled.');
	}
	
	/**
	 * Callback per il cron job di aggiornamento automatico
	 */
	public function auto_refresh_sitemap() {
		// Verifica se la sitemap è abilitata
		if (!get_option('imgseo_sitemap_enabled', false)) {
			return;
		}
		
		// Genera la sitemap automaticamente
		$result = $this->generate_sitemap_file();
		
		// Rimuovi il flag di aggiornamento necessario
		update_option('imgseo_sitemap_needs_update', false);
		
		error_log('ImgSEO Image Sitemap: Auto-refresh completed with status: ' . $result['status']);
	}
}