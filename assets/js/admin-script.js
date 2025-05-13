/**
 * Administration script for the ImgSEO plugin
 */

// Global variables to track ongoing operations
var processingButtons = {};
var globalStopInProgress = false;
var jobDeletionInProgress = false;

/**
 * Function to handle button clicks preventing multiple clicks
 * @param {string} buttonSelector - The CSS selector for the button
 * @param {Function} handlerFunction - The function to execute on click
 */
function setupSafeButtonHandler(buttonSelector, handlerFunction) {
    jQuery(document).on('click', buttonSelector, function(e) {
        e.preventDefault();
        
        var $button = jQuery(this);
        var buttonId = $button.attr('id') || $button.data('button-id') || 'button-' + Math.random().toString(36).substring(2, 10);
        
        // If the button is already being processed, ignore the click
        if (processingButtons[buttonId]) {
            console.log("Button already processing, ignoring click:", buttonId);
            return;
        }
        
        // Mark the button as processing
        processingButtons[buttonId] = true;
        
        // Original button state
        var originalText = $button.text();
        var isDisabled = $button.prop('disabled');
        
        // Set processing state
        $button.prop('disabled', true);
        $button.text('Processing...');
        
        // Call the handler function with a callback to restore the state
        var callbackInvoked = false;
        
        var completeCallback = function() {
            if (callbackInvoked) return; // Prevent multiple calls
            callbackInvoked = true;
            
            // Safety timeout to ensure the button is reactivated
            // even if an error occurs in the handler function
            setTimeout(function() {
                // Restore the original button state
                $button.prop('disabled', isDisabled);
                $button.text(originalText);
                
                // Remove the button from the list of those being processed
                delete processingButtons[buttonId];
            }, 200);
        };
        
        try {
            // Pass the button, event and callback to the handler function
            handlerFunction.call(this, $button, e, completeCallback);
        } catch (error) {
            console.error("Error in click handling:", error);
            completeCallback();
        }
    });
}

function setupStopJobHandlers() {
    // Remove any previous handlers to avoid duplication
    jQuery(document).off('click', '.stop-job-button, #imgseo-stop');
    
    // New unified handler for all stop buttons
    jQuery(document).on('click', '.stop-job-button, #imgseo-stop', function(e) {
        e.preventDefault();
        
        // Check if there is already an interruption in progress at the global level
        if (globalStopInProgress) {
            console.log("Interruption already in progress at global level, no action required");
            return;
        }
        
        var $button = jQuery(this);
        
        // First read the direct HTML attribute, then the data-attribute
        var jobId = $button.attr('data-job-id') || $button.data('job-id');
        console.log("Interruzione job con ID:", jobId, "Button:", $button.attr('id'));
        
        if (!jobId) {
            // A further attempt to retrieve the ID from the context
            if ($button.attr('id') && $button.attr('id').indexOf('stop-job-') === 0) {
                jobId = $button.attr('id').replace('stop-job-', '');
                console.log("Retrieved job ID from button ID:", jobId);
            } else {
                console.error("No job ID found on stop button");
                alert("Error: Unable to find the job ID to stop");
                return;
            }
        }
        
        // ONLY ONE confirm, with a clear message
        if (!confirm('Are you sure you want to stop this job? This operation cannot be undone.')) {
            return;
        }
        
        // Immediately set the global flag to prevent other calls
        globalStopInProgress = true;
        
        // Visually disable all stop buttons
        jQuery('.stop-job-button, #imgseo-stop').prop('disabled', true);
        $button.text('Stopping...');
        
        // Update UI to indicate interruption
        var $progressText = jQuery('#progress-text');
        if ($progressText.length) {
            $progressText.text("Stopping in progress, please wait...");
        }
        
        // Fix: More robust handling of AJAX URL and nonce
        var ajaxUrl = (typeof ImgSEO !== 'undefined' && ImgSEO.ajax_url) ? 
            ImgSEO.ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
            
        var securityNonce = (typeof ImgSEO !== 'undefined' && ImgSEO.nonce) ? 
            ImgSEO.nonce : '';
        
        // Set an attempt counter
        var stopAttempts = 0;
        var maxStopAttempts = 3;
        
        // Function to make the stop attempt
        function attemptStopJob() {
            stopAttempts++;
            console.log("Stop attempt #" + stopAttempts + " for job: " + jobId);
            
            // AJAX call
            // Trova il conteggio attuale delle immagini elaborate
            var currentProcessedCount = 0;
            var $progressText = jQuery('#progress-text');
            
            if ($progressText.length) {
                // Estrai il conteggio attuale dal testo di avanzamento se disponibile
                var progressText = $progressText.text();
                var match = progressText.match(/(\d+) of/);
                if (match && match[1]) {
                    currentProcessedCount = parseInt(match[1], 10);
                }
            }
            
            jQuery.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'imgseo_stop_job',
                    job_id: jobId,
                    processed_count: currentProcessedCount, // Passare il conteggio corrente
                    security: securityNonce
                },
                success: function(response) {
                    console.log("Stop job response:", response);
                    
                    // Even if there's an error in the response, we ignore the error message
                    // if the error indicates that the job is already stopped/completed
                    var isAlreadyStoppedError = response.data &&
                        response.data.message &&
                        response.data.message.indexOf('already completed or stopped') !== -1;
                    
                    if (response.success || isAlreadyStoppedError) {
                        if ($progressText.length) {
                            // Mostra il conteggio delle immagini elaborate nella risposta
                            var processedImages = response.data && response.data.processed_images ? response.data.processed_images : currentProcessedCount;
                            $progressText.text('Job stopped: ' + processedImages + ' images processed. Reloading page...');
                        }
                        
                        // Job fermato con successo, ricarica la pagina
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        // Se non è riuscito e non abbiamo raggiunto il numero massimo di tentativi, riprova
                        if (stopAttempts < maxStopAttempts) {
                            setTimeout(attemptStopJob, 1000); // Riprova dopo 1 secondo
                        } else {
                            // Solo in caso di errori gravi dopo tutti i tentativi
                            if (!isAlreadyStoppedError) {
                                alert('Could not stop the job after multiple attempts. The page will be reloaded.');
                            }
                            // Ricarica comunque la pagina dopo i tentativi
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX error stop job:", error);
                    
                    // In caso di errore di rete, riprova se non abbiamo raggiunto il limite
                    if (stopAttempts < maxStopAttempts) {
                        setTimeout(attemptStopJob, 1000); // Riprova dopo 1 secondo
                    } else {
                        if ($progressText.length) {
                            $progressText.text('Connection error. Reloading page...');
                        }
                        
                        // Ricarica la pagina dopo aver esaurito i tentativi
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    }
                }
            });
        }
        
        // Avvia il primo tentativo
        attemptStopJob();
        
        // In any case, we reduce the number of interactions required
        // from the user by automatically reloading the page after a maximum timeout
        setTimeout(function() {
            if (globalStopInProgress) {
                location.reload();
            }
        }, 10000); // Maximum timeout of 10 seconds
    });
}

function setupDeleteJobHandlers() {
    // First remove any previous handlers to avoid duplication
    jQuery(document).off('click', '.delete-job-button');
    
    // Add a single handler
    jQuery(document).on('click', '.delete-job-button', function(e) {
        e.preventDefault();
        
        // If there is already a deletion in progress, ignore this click
        if (jobDeletionInProgress) {
            console.log("Deletion already in progress, ignoring additional clicks");
            return;
        }
        
        var $button = jQuery(this);
        var jobId = $button.data('job-id');
        
        if (!jobId) {
            console.error("No job ID found on delete-job-button");
            return;
        }
        
        // ONLY ONE confirm with a clear message
        if (!confirm('Are you sure you want to delete this job?')) {
            return;
        }
        
        // Set the flag to prevent multiple clicks
        jobDeletionInProgress = true;
        
        // Disable all delete buttons
        jQuery('.delete-job-button').prop('disabled', true);
        
        // Fix: More robust handling of AJAX URL and nonce
        var ajaxUrl = (typeof ImgSEO !== 'undefined' && ImgSEO.ajax_url) ?
            ImgSEO.ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
            
        var securityNonce = (typeof ImgSEO !== 'undefined' && ImgSEO.nonce) ?
            ImgSEO.nonce : '';
        
        // AJAX call to delete the job
        jQuery.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'imgseo_delete_job',
                job_id: jobId,
                security: securityNonce
            },
            success: function(response) {
                console.log("Delete job response:", response);
                
                if (response.success) {
                    // No alert, instead we do an automatic reload
                    console.log("Job deleted successfully, reloading page...");
                    location.reload();
                } else {
                    // Only in case of error we show an alert
                    alert('Error while deleting the job: ' +
                         (response.data && response.data.message ? response.data.message : 'Unknown error'));
                    resetDeleteState();
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX error delete job:", error);
                alert('Connection error while deleting the job');
                resetDeleteState();
            }
        });
        
        function resetDeleteState() {
            jobDeletionInProgress = false;
            jQuery('.delete-job-button').prop('disabled', false);
        }
    });
    
    // Same approach for the "Delete all jobs" button
    jQuery(document).off('click', '#delete-all-jobs').on('click', '#delete-all-jobs', function(e) {
        e.preventDefault();
        
        if (jobDeletionInProgress) {
            console.log("Deletion already in progress, ignoring additional clicks");
            return;
        }
        
        if (!confirm('Are you sure you want to delete all jobs?')) {
            return;
        }
        
        jobDeletionInProgress = true;
        jQuery('#delete-all-jobs, .delete-job-button').prop('disabled', true);
        
        var ajaxUrl = (typeof ImgSEO !== 'undefined' && ImgSEO.ajax_url) ? 
            ImgSEO.ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
            
        var securityNonce = (typeof ImgSEO !== 'undefined' && ImgSEO.nonce) ? 
            ImgSEO.nonce : '';
        
        jQuery.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'imgseo_delete_all_jobs',
                security: securityNonce
            },
            success: function(response) {
                if (response.success) {
                    console.log("All jobs deleted successfully, reloading page...");
                    location.reload();
                } else {
                    alert('Error while deleting jobs');
                    resetDeleteState();
                }
            },
            error: function() {
                alert('Connection error while deleting jobs');
                resetDeleteState();
            }
        });
        
        function resetDeleteState() {
            jobDeletionInProgress = false;
            jQuery('#delete-all-jobs, .delete-job-button').prop('disabled', false);
        }
    });
}

jQuery(document).ready(function($) {
    console.log("ImgSEO: admin-script.js loaded (correct version)");
    
    // Fix: robust handling of AJAX URL and nonce
    var ajaxUrl = (typeof ImgSEO !== 'undefined' && ImgSEO.ajax_url) ?
        ImgSEO.ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
        
    var securityNonce = (typeof ImgSEO !== 'undefined' && ImgSEO.nonce) ?
        ImgSEO.nonce : '';
    
    // Debug - check for the presence of buttons
    console.log("Buttons #generate-alt-text:", $('#generate-alt-text').length);
    console.log("Buttons .generate-alt-text-btn:", $('.generate-alt-text-btn').length);
    console.log("Button #imgseo-bulk-generate:", $('#imgseo-bulk-generate').length);
    console.log("Buttons .stop-job-button:", $('.stop-job-button').length);
    console.log("Buttons .delete-job-button:", $('.delete-job-button').length);
    
    // Initialize specific handlers for buttons
    setupStopJobHandlers();
    setupDeleteJobHandlers();
    
    // Setup per il pulsante generate-alt-text
    setupSafeButtonHandler('#generate-alt-text', function($button, e, completeCallback) {
        var attachmentId = $button.data('attachment-id');
        var $result = $('#alt-text-result');
        
        // Verify ID
        if (!attachmentId) {
            console.error("Missing attachment ID");
            $result.addClass('error').text('Missing attachment ID').show();
            completeCallback(); // Important: always call the callback!
            return;
        }
        
        $result.removeClass('error success').empty();
        
        // AJAX call
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'generate_alt_text',
                attachment_id: attachmentId,
                security: securityNonce
            },
            success: function(response) {
                console.log("Response received:", response);
                
                if (response.success && response.data) {
                    // Update alt text field
                    var altText = response.data.alt_text;
                    var fields = ['#alt', '#attachment-details-two-column-alt-text', '#attachment_alt'];
                    
                    fields.forEach(function(selector) {
                        var $field = $(selector);
                        if ($field.length) {
                            $field.val(altText).trigger('change');
                        }
                    });
                    
                    // Also update other fields if present in the response
                    if (response.data.title) {
                        $('#title').val(response.data.title).trigger('change');
                    }
                    
                    if (response.data.caption) {
                        $('#excerpt').val(response.data.caption).trigger('change');
                    }
                    
                    if (response.data.description) {
                        $('#content').val(response.data.description).trigger('change');
                    }
                    
                    $result.addClass('success').text('Alt text updated successfully!').show();
                } else {
                    // Handle error
                    var errorMessage = response.data && response.data.message
                        ? response.data.message
                        : 'Error generating alt text';
                    
                    $result.addClass('error').text(errorMessage).show();
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX error:", textStatus, errorThrown);
                $result.addClass('error').text('Error during generation: ' + errorThrown).show();
            },
            complete: function() {
                completeCallback();
            }
        });
    });
    
    // Setup per il pulsante bulk generate
    setupSafeButtonHandler('#imgseo-bulk-generate', function($button, e, completeCallback) {
        var overwrite = $('#bulk-generate-form input[name="overwrite"]').is(':checked') ? 1 : 0;
        var processingMode = 'async';
        
        // Get update options
        var updateTitle = $('#bulk-generate-form input[name="update_title"]').is(':checked') ? 1 : 0;
        var updateDescription = $('#bulk-generate-form input[name="update_description"]').is(':checked') ? 1 : 0;
        var updateCaption = $('#bulk-generate-form input[name="update_caption"]').is(':checked') ? 1 : 0;
        
        var $progressBarFill = $('#progress-bar-fill');
        var $progressText = $('#progress-text');
        var $progressContainer = $('#progress-container');
        var $progressDescription = $('#progress-description');
        
        // Reset UI
        $progressBarFill.css('width', '0%');
        $progressText.text("Starting processing...");
        $progressContainer.show();
        
        // Set the descriptive text
        $progressDescription.text("Processing occurs in real time. Keep this page open until completion.");
        
        console.log("Sending bulk generation request with parameters:", {
            overwrite: overwrite,
            updateTitle: updateTitle,
            updateDescription: updateDescription,
            updateCaption: updateCaption
        });
        
        // Start the process
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'imgseo_start_bulk',
                overwrite: overwrite,
                processing_mode: 'async',
                update_title: updateTitle,
                update_description: updateDescription,
                update_caption: updateCaption,
                processing_speed: $('#processing_speed').val(), // Add the processing speed from the dropdown
                security: securityNonce
            },
            success: function(response) {
                console.log("Start bulk response:", response);
                
                if (response.success && response.data) {
                    var jobId = response.data.job_id;
                    var imageIds = response.data.image_ids;
                    $progressText.text(response.data.message);
                    
                    // Update the stop button with the job ID
                    $('#imgseo-stop').data('job-id', jobId);
                    // Asynchronous process (fast): process directly
                    processAsyncBatch(jobId, imageIds);
                    // We don't call completeCallback() here because
                    // we want the button to remain disabled during processing
                } else {
                    handleBulkError(response.data ? response.data.message : 'Error starting the process');
                    completeCallback(); // Riabilita il pulsante in caso di errore
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX error start bulk:", error);
                handleBulkError('Error starting the process: ' + (error || 'An unexpected error occurred'));
                completeCallback(); // Riabilita il pulsante in caso di errore
            }
        });
    });
    // Function to process batch asynchronously
    // Funzione per elaborare batch in modo asincrono
    function processAsyncBatch(jobId, imageIds) {
        // Salva il conteggio totale come attributo dati sul testo di avanzamento per riferimento quando si interrompe
        $('#progress-text').data('total-images', imageIds.length);
        console.log("Starting processAsyncBatch with job ID:", jobId);
        
        // Utilizza l'impostazione di velocità se disponibile o usa Normal (3) come predefinita
        var maxConcurrentRequests = typeof ImgSEO !== 'undefined' && ImgSEO.processing_speed_batch_size 
            ? parseInt(ImgSEO.processing_speed_batch_size) 
            : 3; // Default to Normal speed if setting not available
        
        // Get the dynamic delay based on the processing speed setting - defined here so it's available to all functions
        var processingDelay = typeof ImgSEO !== 'undefined' && ImgSEO.processing_image_delay_ms 
            ? parseInt(ImgSEO.processing_image_delay_ms) 
            : 1000; // Default to 1000ms if setting not available
        
        console.log("Processing with concurrency level:", maxConcurrentRequests, "and delay:", processingDelay, "ms");
        var activeRequests = 0;
        var completedImages = 0;
        var queue = [...imageIds];
        var $progressBarFill = $('#progress-bar-fill');
        var $progressText = $('#progress-text');
        var $logsContainer = $('#processing-logs');
        var isJobStopped = false;
        
        // Aggiungi container per i log se non esiste
        if ($('#processing-logs-container').length === 0) {
            $('#progress-container').after('<div id="processing-logs-container" class="log-container"><h3>Log di elaborazione in tempo reale</h3><div id="processing-logs" class="log-entries"></div></div>');
            $logsContainer = $('#processing-logs');
        } else {
            $logsContainer.empty();
        }
        
        // Funzione per elaborare un'immagine
        function processImage(imageId) {
            console.log("Scheduling image ID:", imageId);
            
            // Log the actual delay being used
            console.log("Using delay of " + processingDelay + "ms for processing speed " + 
                      (typeof ImgSEO !== 'undefined' ? ImgSEO.processing_speed : 'unknown'));
            
            // Importante: incrementiamo activeRequests solo quando effettivamente avviamo la richiesta,
            // non prima - questo permette di avviare più richieste in coda
            setTimeout(function() {
                activeRequests++;
                console.log("Now processing image ID:", imageId, "Active requests:", activeRequests);
                $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'generate_alt_text',
                    image_id: imageId,
                    job_id: jobId,
                    security: securityNonce
                },
                success: function(response) {
                    console.log("Process image response:", response);
                    activeRequests--;
                    completedImages++;
                    
                    if (response.success) {
                        // Aggiorna UI con risultato
                        var statusClass = 'log-success';
                        var altText = response.data.alt_text;
                        var filename = response.data.filename || 'Image ' + imageId;
                        
                        var truncatedAltText = altText.length > 60 ? 
                            altText.substring(0, 57) + '...' : altText;
                        
                        var logEntry = '<div class="log-entry ' + statusClass + '">' +
                                      '<span class="log-time">' + getCurrentTime() + '</span>' +
                                      '<span class="log-filename">' + filename + '</span>' +
                                      '<span class="log-text" title="' + altText.replace(/"/g, '&quot;') + '">' + 
                                      truncatedAltText + '</span>' +
                                      '</div>';
                        
                        $logsContainer.append(logEntry);
                        $logsContainer.scrollTop($logsContainer[0].scrollHeight);
                    } else {
                        // Controlla se l'errore è dovuto a crediti insufficienti
                        var errorMessage = response.data ? response.data.message : 'Unknown error';
                        var isInsufficientCredits = 
                            errorMessage.indexOf('Insufficient') !== -1 || 
                            errorMessage.indexOf('insufficient') !== -1 ||
                            errorMessage.indexOf('crediti insufficienti') !== -1 ||
                            (response.data && response.data.error_type === 'insufficient_credits');
                        
                        // Aggiorna UI con errore
                        var logEntry = '<div class="log-entry log-error">' +
                                      '<span class="log-time">' + getCurrentTime() + '</span>' +
                                      '<span class="log-filename">' + (response.data ? response.data.filename || 'Image ' + imageId : 'Error') + '</span>' +
                                      '<span class="log-text">' + errorMessage + '</span>' +
                                      '</div>';
                        
                        $logsContainer.append(logEntry);
                        $logsContainer.scrollTop($logsContainer[0].scrollHeight);
                        
                        // Se l'errore è dovuto a crediti insufficienti, interrompi tutto il processo
                        if (isInsufficientCredits) {
                            console.log("Insufficient credits detected, stopping all processing");
                            isJobStopped = true;  // Ferma l'elaborazione di ulteriori immagini
                            queue = [];  // Svuota la coda
                            
                            // Aggiorna la UI per indicare l'interruzione
                            $progressText.text('Processing stopped: Insufficient credits. ' + 
                                              completedImages + ' of ' + imageIds.length + ' images processed.');
                            
                            // Notifica job completato (interrotto) nel database
                            $.ajax({
                                url: ajaxUrl,
                                type: 'POST',
                                data: {
                                    action: 'imgseo_stop_job',
                                    job_id: jobId,
                                    security: securityNonce
                                },
                                success: function() {
                                    // Riabilita il pulsante dopo un po'
                                    setTimeout(function() {
                                        $('#imgseo-bulk-generate').prop('disabled', false);
                                    }, 2000);
                                }
                            });
                            
                            return;  // Esci dalla funzione senza elaborare altre immagini
                        }
                    }
                    
                    // Aggiorna progresso
                    var progress = Math.round((completedImages / imageIds.length) * 100);
                    $progressBarFill.css('width', progress + '%');
                    
                    // Mantieni sempre aggiornata la variabile completedImages nel DOM
                    // così può essere recuperata accuratamente quando si interrompe il job
                    $('#progress-text').data('completed-images', completedImages);
                    
                    $progressText.text('Processing: ' + completedImages + ' of ' + imageIds.length +
                                      ' (' + progress + '%)');
                    
                    // Processa altre immagini
                    if (!isJobStopped) {
                        processMoreImages();
                    }
                    
                    // Mantieni sempre aggiornata la variabile completedImages nel DOM
                    // così può essere recuperata accuratamente quando si interrompe il job
                    $('#progress-text').data('completed-images', completedImages);
                    
                    // Se tutto è completato
                    if (completedImages >= imageIds.length) {
                        $('#imgseo-bulk-generate').prop('disabled', false);
                        $progressText.text('Processing completed: ' + completedImages + ' images processed');
                        
                        // Usa imgseo_stop_job per aggiornare correttamente lo stato e il conteggio
                        // Questo metodo aggiorna sia lo stato che il conteggio delle immagini elaborate
                        $.ajax({
                            url: ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'imgseo_stop_job',
                                job_id: jobId,
                                processed_count: completedImages, // Passa il conteggio reale
                                completion_status: 'completed',   // Flag speciale per indicare completamento
                                security: securityNonce
                            },
                            success: function(response) {
                                console.log("Job marked as completed:", response);
                                setTimeout(function() {
                                    location.reload();
                                }, 3000);
                            },
                            error: function(xhr, status, error) {
                                console.error("Error in final update:", error);
                                setTimeout(function() {
                                    location.reload();
                                }, 3000);
                            }
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error process image:", error);
                    activeRequests--;
                    completedImages++;
                    
                    // Controlla se l'errore è un errore API 500 con messaggio di crediti insufficienti
                    var errorMessage = error || 'Unknown error';
                    var responseText = '';
                    
                    try {
                        if (xhr && xhr.responseText) {
                            responseText = xhr.responseText;
                            var responseJson = JSON.parse(responseText);
                            if (responseJson && responseJson.message) {
                                errorMessage = responseJson.message;
                            }
                        }
                    } catch (e) {
                        console.error("Error parsing response:", e);
                    }
                    
                    // Controlla per crediti insufficienti nel messaggio di errore o nel responseText
                    var isInsufficientCredits = 
                        errorMessage.indexOf('Insufficient') !== -1 || 
                        errorMessage.indexOf('insufficient') !== -1 ||
                        errorMessage.indexOf('crediti insufficienti') !== -1 ||
                        responseText.indexOf('Crediti insufficienti') !== -1 ||
                        responseText.indexOf('"reason":"Crediti insufficienti"') !== -1;
                    
                    // Aggiorna UI con errore
                    var logEntry = '<div class="log-entry log-error">' +
                                  '<span class="log-time">' + getCurrentTime() + '</span>' +
                                  '<span class="log-filename">Error</span>' +
                                  '<span class="log-text">Failed to process image: ' + errorMessage + '</span>' +
                                  '</div>';
                    
                    $logsContainer.append(logEntry);
                    $logsContainer.scrollTop($logsContainer[0].scrollHeight);
                    
                    // Se l'errore è dovuto a crediti insufficienti, interrompi tutto il processo
                    if (isInsufficientCredits) {
                        console.log("Insufficient credits detected in error response, stopping all processing");
                        isJobStopped = true;  // Ferma l'elaborazione di ulteriori immagini
                        queue = [];  // Svuota la coda
                        
                        // Aggiorna la UI per indicare l'interruzione
                        $progressText.text('Processing stopped: Insufficient credits. ' + 
                                          completedImages + ' of ' + imageIds.length + ' images processed.');
                        
                        // Notifica job completato (interrotto) nel database
                        $.ajax({
                            url: ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'imgseo_stop_job',
                                job_id: jobId,
                                security: securityNonce
                            },
                            success: function() {
                                // Riabilita il pulsante dopo un po'
                                setTimeout(function() {
                                    $('#imgseo-bulk-generate').prop('disabled', false);
                                }, 2000);
                            }
                        });
                        
                        return;  // Esci dalla funzione senza elaborare altre immagini
                    }
                    
                    // Continua con altre immagini solo se non ci sono problemi di crediti
                    if (!isJobStopped) {
                        processMoreImages();
                    }
                    
                    // Verifica se abbiamo completato tutte le immagini
                    if (completedImages >= imageIds.length) {
                        // Aggiorna il DB con il conteggio corretto delle immagini elaborate
                        $.ajax({
                            url: ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'imgseo_stop_job',
                                job_id: jobId,
                                processed_count: completedImages,
                                completion_status: 'completed',
                                security: securityNonce
                            },
                            success: function() {
                                $('#imgseo-bulk-generate').prop('disabled', false);
                                $progressText.text('Processing completed: ' + completedImages + ' of ' + imageIds.length + ' images processed');
                                
                                // Ricarica la pagina per vedere i risultati aggiornati
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            }
                        });
                    }
                }
                });
            }, processingDelay); // Apply the delay before starting the AJAX request
        }
        
        // Variabile per tracciare se è in corso lo scheduling di immagini
        var isSchedulingInProgress = false;
        
        // Processa le immagini progressivamente, con un piccolo ritardo tra l'avvio di ciascuna
        function processMoreImages() {
            // Evita chiamate multiple sovrapposte
            if (isSchedulingInProgress) {
                return;
            }
            
            // Se non ci sono altre immagini da elaborare o è stato interrotto, esci
            if (queue.length === 0 || isJobStopped) {
                return;
            }
            
            // Se abbiamo raggiunto il limite massimo di richieste attive, attendiamo
            if (activeRequests >= maxConcurrentRequests) {
                return;
            }
            
            // Impostiamo il flag per evitare chiamate multiple
            isSchedulingInProgress = true;
            
            // Calcola il ritardo tra le richieste
            var requestStagingDelay = processingDelay / maxConcurrentRequests;
            console.log("Staging delay between requests:", requestStagingDelay, "ms");
            
            // Avvia la prima immagine immediatamente
            var imageId = queue.shift();
            console.log("Starting first image in batch:", imageId);
            processImage(imageId);
            
            // Pianifica l'elaborazione progressiva delle immagini rimanenti con piccoli ritardi
            function scheduleNextImage() {
                // Se non ci sono altre immagini da elaborare o è stato interrotto, esci
                if (queue.length === 0 || isJobStopped || activeRequests >= maxConcurrentRequests) {
                    isSchedulingInProgress = false;
                    return;
                }
                
                // Preleva un'immagine dalla coda e la elabora
                var nextImageId = queue.shift();
                console.log("Scheduling next image:", nextImageId, "with delay:", requestStagingDelay);
                
                // Aggiungi un piccolo ritardo tra l'avvio di ogni immagine per un invio progressivo
                setTimeout(function() {
                    processImage(nextImageId);
                    
                    // Pianifica la prossima immagine se ci sono ancora immagini nella coda
                    // e non abbiamo raggiunto il limite massimo di richieste attive
                    if (queue.length > 0 && activeRequests < maxConcurrentRequests && !isJobStopped) {
                        scheduleNextImage();
                    } else {
                        isSchedulingInProgress = false;
                    }
                }, requestStagingDelay);
            }
            
            // Avvia la pianificazione se ci sono ancora immagini nella coda
            // e non abbiamo raggiunto il limite massimo di richieste attive
            if (queue.length > 0 && activeRequests < maxConcurrentRequests && !isJobStopped) {
                scheduleNextImage();
            } else {
                isSchedulingInProgress = false;
            }
        }
        
        // Funzione per ottenere l'ora corrente formattata
        function getCurrentTime() {
            var now = new Date();
            return now.getHours().toString().padStart(2, '0') + ':' + 
                   now.getMinutes().toString().padStart(2, '0') + ':' + 
                   now.getSeconds().toString().padStart(2, '0');
        }
        
        // Avvia l'elaborazione
        processMoreImages();
    }
    
    
    // Funzione per errori bulk
    function handleBulkError(errorMessage) {
        $('#progress-container').hide();
        $('#processing-logs-container').hide();
        $('#imgseo-bulk-generate').prop('disabled', false);
        
        alert(errorMessage);
        console.error('ImgSEO: ' + errorMessage);
    }
    
    // Formatta il tempo
    function formatTime(datetime) {
        if (!datetime) return '';
        
        try {
            var date = new Date(datetime.replace(' ', 'T'));
            var hours = date.getHours().toString().padStart(2, '0');
            var minutes = date.getMinutes().toString().padStart(2, '0');
            var seconds = date.getSeconds().toString().padStart(2, '0');
            return hours + ':' + minutes + ':' + seconds;
        } catch (e) {
            console.error("Errore nella formattazione del tempo:", e);
            return '';
        }
    }
    
    // Tronca il testo se più lungo di maxLength
    function truncateText(text, maxLength) {
        if (!text) return '';
        return text.length > maxLength ? text.substring(0, maxLength - 3) + '...' : text;
    }
    
    // Aggiungi un gestore per il pulsante "Forza elaborazione"
    $(document).on('click', '#force-cron-button', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $spinner = $('#cron-spinner');
        
        // Disabilita il pulsante e mostra lo spinner
        $button.prop('disabled', true);
        $spinner.css('visibility', 'visible');
        
        // Chiamata AJAX per forzare l'esecuzione del cron
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'imgseo_force_cron',
                security: securityNonce
            },
            success: function(response) {
                if (response.success) {
                    $('#cron-status-text').text('Status: Processing started manually');
                    $('#last-cron-run').text('Last update: ' + response.data.last_run + ' (' + response.data.time_ago + ')');
                    
                    // Show success message
                    alert('Processing started successfully! The process should begin shortly.');
                    
                    // Force a job status check after 3 seconds
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                } else {
                    $('#cron-status-text').html('<span style="color: red;">Status: Error starting the process</span>');
                    alert('An error occurred while starting the process.');
                }
            },
            error: function() {
                $('#cron-status-text').html('<span style="color: red;">Status: Connection error</span>');
                alert('A connection error occurred.');
            },
            complete: function() {
                // Riabilita il pulsante e nascondi lo spinner
                $button.prop('disabled', false);
                $spinner.css('visibility', 'hidden');
            }
        });
    });
    
    // Pulsante nascondi monitoraggio
    $(document).on('click', '#imgseo-cancel', function() {
        $('#progress-container').slideUp();
    });
});
