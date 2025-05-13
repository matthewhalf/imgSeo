/**
 * ImgSEO Media Modal Button
 * 
 * Script ottimizzato per aggiungere e posizionare correttamente il bottone di generazione
 * del testo alternativo nel Media Modal di WordPress.
 */
(function($) {
    'use strict';
    
    // Quando il documento è pronto
    $(document).ready(function() {
        // Configurazione
        var config = {
            debug: typeof ImgSEO !== 'undefined' && ImgSEO.debug ? true : false,
            texts: (typeof ImgSEO !== 'undefined' && ImgSEO.texts) ? ImgSEO.texts : {
                generate_button: 'Genera Testo Alternativo',
                generating: 'Generazione in corso...',
                success: 'Testo alternativo generato con successo!',
                error: 'Errore durante la generazione:'
            }
        };
        
        // Flag per tenere traccia dell'aggiunta del bottone
        var buttonAdded = false;
        
        // Inizializza il monitoraggio degli eventi nel media modal
        initMediaModalEvents();
        
        // Funzione di log per debug
        function log(message, obj) {
            if (config.debug && console && console.log) {
                if (obj) {
                    console.log('[ImgSEO MediaModal]', message, obj);
                } else {
                    console.log('[ImgSEO MediaModal]', message);
                }
            }
        }
        
        /**
         * Inizializza gli eventi per il Media Modal
         */
        function initMediaModalEvents() {
            if (!wp || !wp.media) {
                log('WordPress Media API non trovata');
                return;
            }
            
            // Eventi chiave del Media Modal per posizionare il bottone
            var events = [
                'open',                  // Quando il modal si apre
                'select',                // Quando un elemento viene selezionato
                'selection:single'       // Quando cambia la selezione singola
            ];
            
            // Aggiungi handler per gli eventi chiave
            events.forEach(function(event) {
                if (wp.media.frame) {
                    wp.media.frame.on(event, function() {
                        buttonAdded = false; // Reset flag quando cambia il contesto
                        _.defer(positionButtonInModal); // Usa defer per dare tempo al DOM di aggiornarsi
                    });
                }
            });
            
            // Monitoraggio navigazione tra immagini con prev/next
            $(document).on('click', '.attachment-info .previous-attachment, .attachment-info .next-attachment', function() {
                buttonAdded = false; // Reset flag quando si naviga
                setTimeout(positionButtonInModal, 100);
            });
            
            // Nota: Abbiamo rimosso l'event listener delegato che potrebbe causare
            // doppia esecuzione. Il click è già gestito in imgseo-alt-text-generator.js
        }
        
        /**
         * Posiziona il bottone correttamente nel Media Modal
         * Versione migliorata per evitare duplicazioni
         */
        function positionButtonInModal() {
            // Verifica più robusta per il controllo di duplicazione
            // Prima verifichiamo se un bottone è già stato aggiunto per l'allegato corrente
            var currentId = getCurrentAttachmentId();
            
            // Se abbiamo già un ID, verifichiamo se esiste un bottone per questo ID
            if (currentId && $('#generate-alt-text-modal-' + currentId).length) {
                log('Bottone già presente per ID: ' + currentId);
                buttonAdded = true;
                return;
            }
            
            // Controllo generale, se il flag è ancora true nonostante il reset
            if (buttonAdded) {
                return;
            }
            
            // Trova il campo del testo alternativo
            var $altTextField = $('.attachment-details [data-setting="alt"], .media-sidebar [data-setting="alt"]').first();
            
            // Verifica se il campo è presente
            if ($altTextField.length) {
                log('Campo alt text trovato');
                
                // Controlla se il bottone è già stato aggiunto per questo campo
                var $existingButton = $altTextField.next('.imgseo-button-container');
                if ($existingButton.length) {
                    log('Bottone già presente, aggiornamento non necessario');
                    buttonAdded = true;
                    return;
                }
                
                // Ottieni l'ID dell'allegato con metodo migliorato
                var attachmentId = getAttachmentIdFromAltField($altTextField) || 
                                  getCurrentAttachmentId();
                
                if (attachmentId) {
                // Crea il bottone con attributo data-instance-id univoco per evitare duplicazioni
                var instanceId = 'inst_' + Math.random().toString(36).substr(2, 9);
                var $button = $('<div class="imgseo-button-container">' +
                    '<button type="button" id="generate-alt-text-modal-' + attachmentId + '" ' +
                    'class="button button-primary generate-alt-text-modal-btn" ' +
                    'data-id="' + attachmentId + '" data-instance-id="' + instanceId + '">' + 
                    config.texts.generate_button + '</button>' +
                    '<div id="alt-text-result-' + attachmentId + '" class="alt-text-result"></div>' +
                    '</div>');
                    
                    // Aggiungi il bottone dopo il campo del testo alternativo
                    $altTextField.after($button);
                    log('Bottone posizionato correttamente con ID:', attachmentId);
                    
                    // Imposta il flag per evitare duplicati
                    buttonAdded = true;
                } else {
                    // Anche se non troviamo l'ID per qualche motivo, non loghiamo l'errore
                    // per evitare messaggi confusi nei log quando poi il bottone funziona
                    
                    // Possiamo comunque creare un bottone con un ID temporaneo che verrà aggiornato al click
                    // Aggiungiamo un attributo data-instance-id univoco per evitare duplicazioni
                    var instanceId = 'inst_' + Math.random().toString(36).substr(2, 9);
                    var $genericButton = $('<div class="imgseo-button-container">' +
                        '<button type="button" class="button button-primary generate-alt-text-modal-btn" data-instance-id="' + instanceId + '">' + 
                        config.texts.generate_button + '</button>' +
                        '<div class="alt-text-result"></div>' +
                        '</div>');
                    
                    $altTextField.after($genericButton);
                    buttonAdded = true;
                }
            }
        }
        
        /**
         * Estrae l'ID dell'allegato dal genitore del campo alt text
         * Metodo alternativo che spesso funziona quando gli altri falliscono
         */
        function getAttachmentIdFromAltField($altField) {
            try {
                // Cerca nei dati dell'elemento parent
                var $parent = $altField.closest('[data-id]');
                if ($parent.length && $parent.data('id')) {
                    return parseInt($parent.data('id'), 10);
                }
                
                // Cerca nei dati dell'input
                var inputName = $altField.find('input').attr('name');
                if (inputName && inputName.match(/\[(\d+)\]/)) {
                    return parseInt(inputName.match(/\[(\d+)\]/)[1], 10);
                }
                
                return null;
            } catch (e) {
                log('Errore nell\'estrazione dell\'ID dal campo alt:', e);
                return null;
            }
        }
        
        /**
         * Ottiene l'ID dell'allegato corrente dalla UI
         */
        function getCurrentAttachmentId() {
            // Metodo 1: dal modello Backbone
            try {
                if (wp.media && wp.media.frame && wp.media.frame.state) {
                    var state = wp.media.frame.state();
                    if (state && state.get) {
                        var selection = state.get('selection');
                        if (selection && selection.first) {
                            var attachment = selection.first();
                            if (attachment && attachment.id) {
                                return parseInt(attachment.id, 10);
                            }
                        }
                    }
                }
            } catch (e) {
                log('Errore nel recupero ID da Backbone:', e);
            }
            
            // Metodo 2: dalla selezione corrente
            try {
                if (wp.media && wp.media.frame && wp.media.frame.content) {
                    var selection = wp.media.frame.content.get().collection.selection;
                    if (selection && selection.models && selection.models.length) {
                        return parseInt(selection.models[0].id, 10);
                    }
                }
            } catch (e) {
                log('Errore nel recupero ID da selection:', e);
            }
            
            // Metodo 3: da elementi del DOM (più affidabile in certi casi)
            var selectors = [
                '.attachment.selected',                           // Elemento selezionato
                '.attachment-details',                            // Pannello dettagli
                '.media-modal .attachment.selection-preview',     // Anteprima selezione
                'input[name="id"], input[name="attachmentId"]'    // Campi nascosti
            ];
            
            for (var i = 0; i < selectors.length; i++) {
                var $elem = $(selectors[i]).first();
                if ($elem.length) {
                    var id = $elem.data('id') || $elem.val();
                    if (id) {
                        return parseInt(id, 10);
                    }
                }
            }
            
            return null;
        }
        
        // Avvia il posizionamento iniziale del bottone dopo un breve ritardo
        setTimeout(positionButtonInModal, 300);
    });
})(jQuery);
