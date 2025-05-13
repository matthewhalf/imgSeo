/**
 * ImgSEO Alt Text Generator
 * 
 * Gestisce la generazione del testo alternativo attraverso AJAX
 * e l'aggiornamento dei campi nell'interfaccia utente
 */
(function($) {
    'use strict';
    
    // Configurazione
    var config = {
        debug: typeof ImgSEO !== 'undefined' && ImgSEO.debug ? true : false,
        ajaxUrl: typeof ImgSEO !== 'undefined' && ImgSEO.ajax_url ? ImgSEO.ajax_url : ajaxurl,
        nonce: typeof ImgSEO !== 'undefined' && ImgSEO.nonce ? ImgSEO.nonce : '',
        updateTitle: typeof ImgSEO !== 'undefined' && typeof ImgSEO.update_title !== 'undefined' ? ImgSEO.update_title : false,
        updateCaption: typeof ImgSEO !== 'undefined' && typeof ImgSEO.update_caption !== 'undefined' ? ImgSEO.update_caption : false,
        updateDescription: typeof ImgSEO !== 'undefined' && typeof ImgSEO.update_description !== 'undefined' ? ImgSEO.update_description : false,
        // Sostituiamo il flag booleano con una mappa per tracciare gli ID in elaborazione
        processingIds: {},
        // Tempo minimo (in ms) tra chiamate successive per lo stesso ID
        processingTimeout: 3000,
        texts: typeof ImgSEO !== 'undefined' && ImgSEO.texts ? ImgSEO.texts : {
            generate_button: 'Genera Testo Alternativo',
            generating: 'Generazione in corso...',
            success: 'Testo alternativo generato con successo!',
            error: 'Errore durante la generazione:',
            connection_error: 'Errore di connessione:'
        }
    };
    
    // Inizializzazione
    $(document).ready(function() {
        // Delega per il click sui bottoni di generazione
        $(document).on('click', '.generate-alt-text-modal-btn, .generate-alt-text-edit-btn', handleGenerateButtonClick);
        
        log('ImgSEO Alt Text Generator inizializzato');
    });
    
    /**
     * Gestisce il click sul bottone di generazione
     * Versione migliorata con protezione contro click multipli
     */
    function handleGenerateButtonClick(e) {
        // Ferma la propagazione dell'evento per evitare attivazioni multiple
        e.preventDefault();
        e.stopPropagation();
        
        var $button = $(this);
        var attachmentId = parseInt($button.data('id'), 10);
        
        // Se l'ID non è valido, termina l'esecuzione
        if (!attachmentId) {
            log('ID allegato mancante');
            alert(config.texts.no_id_found || 'ID immagine non trovato');
            return;
        }
        
        // Verifica se l'immagine è già in elaborazione
        if (isProcessing(attachmentId)) {
            log('ID ' + attachmentId + ' già in elaborazione, richiesta ignorata');
            return;
        }
        
        // Genera il testo alternativo
        generateAltText(attachmentId, $button);
    }
    
    /**
     * Verifica se un'immagine è già in fase di elaborazione
     * @param {number} attachmentId ID dell'allegato
     * @return {boolean} true se l'immagine è in elaborazione
     */
    function isProcessing(attachmentId) {
        var now = Date.now();
        
        // Se l'ID è già in elaborazione, controlla il timeout
        if (config.processingIds[attachmentId]) {
            // Se l'ultima elaborazione è più vecchia del timeout, possiamo procedere
            if (now - config.processingIds[attachmentId] > config.processingTimeout) {
                log('Timeout di elaborazione scaduto per ID: ' + attachmentId + ', procedo con nuova richiesta');
                // Aggiorna il timestamp per il nuovo processo
                config.processingIds[attachmentId] = now;
                return false;
            }
            
            return true;
        }
        
        // Segna l'ID come in elaborazione
        config.processingIds[attachmentId] = now;
        return false;
    }
    
    /**
     * Genera il testo alternativo tramite AJAX
     * Versione migliorata con protezione contro chiamate multiple
     */
    function generateAltText(attachmentId, $button) {
        log('Generazione Alt Text per ID:', attachmentId);
        
        // Trova o crea il container per i risultati
        var $result = $('#alt-text-result-' + attachmentId);
        if (!$result.length) {
            $result = $('<div id="alt-text-result-' + attachmentId + '" class="alt-text-result"></div>');
            $button.after($result);
        }
        
        // Aggiorna lo stato dell'interfaccia
        $button.prop('disabled', true).text(config.texts.generating);
        $result.html('<span style="color:#666;">' + config.texts.generating + '</span>').show();
        
        // Creiamo un ID univoco per questa richiesta AJAX per debugging
        var requestUUID = 'req_' + Math.random().toString(36).substr(2, 9);
        log('Inizio richiesta AJAX [' + requestUUID + '] per ID:', attachmentId);
        
        // Chiamata AJAX
        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'generate_alt_text',
                attachment_id: attachmentId,
                update_title: config.updateTitle ? 1 : 0,
                update_caption: config.updateCaption ? 1 : 0,
                update_description: config.updateDescription ? 1 : 0,
                security: config.nonce,
                // Aggiungiamo un timestamp per evitare cache
                _: Date.now()
            },
            success: function(response) {
                log('Risposta AJAX [' + requestUUID + ']:', response);
                
                if (response.success && response.data) {
                    // Aggiorna i campi nell'interfaccia
                    updateAllFieldsInUI(response.data);
                    
                    // Mostra il messaggio di successo
                    $result.html('<span style="color:green;">' + config.texts.success + '</span>');
                    setTimeout(function() { $result.fadeOut(); }, 3000);
                } else {
                    // Gestisci l'errore
                    var errorMsg = response.data && response.data.message ? 
                        response.data.message : config.texts.error;
                    $result.html('<span style="color:red;">' + errorMsg + '</span>');
                }
            },
            error: function(xhr, status, error) {
                log('Errore AJAX [' + requestUUID + ']:', error);
                $result.html('<span style="color:red;">' + config.texts.connection_error + ' ' + error + '</span>');
            },
            complete: function() {
                log('Completamento AJAX [' + requestUUID + '] per ID:', attachmentId);
                
                // Ripristina lo stato del bottone
                $button.prop('disabled', false).text(config.texts.generate_button);
                
                // Pianifica la rimozione dell'ID dalla lista di quelli in elaborazione
                // dopo un certo ritardo per evitare richieste multiple in rapida successione
                setTimeout(function() {
                    if (config.processingIds[attachmentId]) {
                        log('Fine periodo di protezione per ID: ' + attachmentId);
                        delete config.processingIds[attachmentId];
                    }
                }, config.processingTimeout);
            }
        });
    }
    
    /**
     * Aggiorna tutti i campi nell'interfaccia utente
     */
    function updateAllFieldsInUI(data) {
        // Determina il contesto (Media Modal o Pagina di modifica)
        var isEditPage = typeof ImgSEO !== 'undefined' && ImgSEO.is_attachment_edit;
        
        // Aggiorna il testo alternativo
        if (data.alt_text) {
            updateTextField('alt', data.alt_text);
        }
        
        // Aggiorna il titolo se richiesto
        if (data.title && config.updateTitle) {
            updateTextField('title', data.title);
        }
        
        // Aggiorna la didascalia se richiesta
        if (data.caption && config.updateCaption) {
            updateTextField('caption', data.caption);
        }
        
        // Aggiorna la descrizione se richiesta
        if (data.description && config.updateDescription) {
            updateTextField('description', data.description);
        }
        
        // Forza l'aggiornamento dell'interfaccia nei media modal
        if (wp.media && wp.media.frame && typeof wp.media.frame.trigger === 'function') {
            wp.media.frame.trigger('selection:action:done');
        }
    }
    
    /**
     * Aggiorna un campo di testo specifico nell'interfaccia
     */
    function updateTextField(fieldName, value) {
        try {
            log('Aggiornamento campo ' + fieldName + ':', value);
            
            // Prova prima ad aggiornare tramite Backbone se disponibile
            var backboneUpdated = updateViaBackbone(fieldName, value);
            
            // Se l'aggiornamento Backbone fallisce, usa l'approccio DOM
            if (!backboneUpdated) {
                updateViaDom(fieldName, value);
            }
        } catch (e) {
            log('Errore nell\'aggiornamento del campo ' + fieldName + ':', e);
        }
    }
    
    /**
     * Tenta di aggiornare un campo tramite il modello Backbone
     */
    function updateViaBackbone(fieldName, value) {
        if (!wp.media || !wp.media.frame || !wp.media.frame.state) {
            return false;
        }
        
        try {
            var state = wp.media.frame.state();
            var selection = state.get('selection');
            var attachment = selection.first();
            
            if (attachment && attachment.set) {
                attachment.set(fieldName, value);
                log('Campo ' + fieldName + ' aggiornato via Backbone');
                return true;
            }
        } catch (e) {
            log('Errore Backbone:', e);
        }
        
        return false;
    }
    
    /**
     * Aggiorna un campo direttamente nel DOM
     */
    function updateViaDom(fieldName, value) {
        var selectors = getSelectorsForField(fieldName);
        
        if (!selectors.length) {
            log('Nessun selettore trovato per il campo:', fieldName);
            return false;
        }
        
        var updated = false;
        
        // Prova tutti i selettori possibili per il campo
        for (var i = 0; i < selectors.length; i++) {
            var $fields = $(selectors[i]);
            
            if ($fields.length) {
                $fields.val(value).trigger('change');
                log('Campo ' + fieldName + ' aggiornato via DOM con selettore:', selectors[i]);
                updated = true;
                
                // Se è un campo con TinyMCE (per la descrizione), aggiorna anche l'editor
                if (fieldName === 'description' && typeof window.tinyMCE !== 'undefined') {
                    $fields.each(function() {
                        var editorId = $(this).attr('id');
                        if (editorId && tinyMCE.get(editorId)) {
                            tinyMCE.get(editorId).setContent(value);
                        }
                    });
                }
            }
        }
        
        return updated;
    }
    
    /**
     * Ottiene i selettori possibili per un campo specifico
     */
    function getSelectorsForField(fieldName) {
        var selectors = [];
        
        switch (fieldName) {
            case 'alt':
                selectors = [
                    '.attachment-details [data-setting="alt"] input, .attachment-details [data-setting="alt"] textarea',
                    '.media-sidebar [data-setting="alt"] input, .media-sidebar [data-setting="alt"] textarea',
                    '#attachment-details-alt-text, #attachment-details-two-column-alt-text',
                    '#alt, [name="alt"], #attachment_alt'
                ];
                break;
            case 'title':
                selectors = [
                    '.attachment-details [data-setting="title"] input',
                    '.media-sidebar [data-setting="title"] input',
                    '#attachment-details-two-column-title',
                    '#title, [name="post_title"], [name="title"]'
                ];
                break;
            case 'caption':
                selectors = [
                    '.attachment-details [data-setting="caption"] textarea',
                    '.media-sidebar [data-setting="caption"] textarea',
                    '#attachment-details-two-column-caption',
                    '#caption, #excerpt, [name="excerpt"], [name="post_excerpt"], [name="caption"]'
                ];
                break;
            case 'description':
                selectors = [
                    '.attachment-details [data-setting="description"] textarea',
                    '.media-sidebar [data-setting="description"] textarea',
                    '#attachment-details-two-column-description',
                    '#content, #description, [name="content"], [name="post_content"], [name="description"]'
                ];
                break;
        }
        
        return selectors;
    }
    
    /**
     * Funzione di log per debug
     */
    function log(message, obj) {
        if (config.debug && console && console.log) {
            if (obj) {
                console.log('[ImgSEO]', message, obj);
            } else {
                console.log('[ImgSEO]', message);
            }
        }
    }
    
})(jQuery);
