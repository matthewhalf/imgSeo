/**
 * ImgSEO Edit Media Page
 * 
 * Script ottimizzato per la pagina di modifica dell'allegato
 * Gestisce le interazioni specifiche di questa pagina
 */
(function($) {
    'use strict';
    
    // Quando il documento è pronto
    $(document).ready(function() {
        // Verifica se siamo nella pagina di modifica dell'allegato
        if (!$('body').hasClass('imgseo-edit-media-page') && 
            !($('body').hasClass('post-type-attachment') && $('body').hasClass('post-php'))) {
            return;
        }
        
        // Debug
        var debug = typeof ImgSEO !== 'undefined' && ImgSEO.debug;
        
        function log(message, obj) {
            if (debug && console && console.log) {
                if (obj) {
                    console.log('[ImgSEO EditPage]', message, obj);
                } else {
                    console.log('[ImgSEO EditPage]', message);
                }
            }
        }
        
        log('Script pagina di modifica allegato inizializzato');
        
        // Assicurati che il container dei risultati sia visibile
        $('.alt-text-result').show();
        
        // Trova i campi principali della pagina
        var $altTextField = $('#attachment_alt');
        var $titleField = $('input[name="post_title"]');
        var $captionField = $('textarea[name="excerpt"]');
        var $descriptionField = $('textarea[name="content"]');
        
        // Migliora l'integrazione con l'editor WordPress
        enhanceEditorIntegration();
        
        /**
         * Migliora l'integrazione con l'editor WordPress
         * per assicurare che gli aggiornamenti dei campi funzionino correttamente
         */
        function enhanceEditorIntegration() {
            // Se l'editor TinyMCE è attivo, aggiungi handler per aggiornamenti editor-to-textarea
            if (typeof window.tinyMCE !== 'undefined') {
                try {
                    // Quando l'editor TinyMCE è pronto
                    $(document).on('tinymce-editor-setup', function(event, editor) {
                        editor.on('change', function() {
                            // Sincronizza le modifiche con il textarea
                            editor.save();
                        });
                    });
                    
                    log('Integrazione TinyMCE configurata');
                } catch (e) {
                    log('Errore durante l\'integrazione con TinyMCE:', e);
                }
            }
            
            // Migliora la gestione della visualizzazione dei risultati
            // Nota: Usiamo namespace personalizzato per l'evento per evitare conflitti
            $('.generate-alt-text-edit-btn').on('click.imgseoVisibility', function(e) {
                // Non propagare l'evento, così non verrà interpretato come un evento di generazione
                // e non creerà duplicazioni di chiamate API
                e.stopPropagation();
                
                // Non prevenire il comportamento predefinito, permettiamo all'altro handler di funzionare
                // però gestiamo solo la visibilità del container dei risultati
                var $button = $(this);
                var $metabox = $button.closest('.imgseo-metabox-content');
                var $result = $metabox.find('.alt-text-result');
                
                // Assicurati che il risultato sia visibile
                if ($result.length) {
                    $result.show();
                }
                
                log('Visibilità dei risultati aggiornata');
            });
        }
    });
    
})(jQuery);
