<div class="wrap">
    <h1><?php _e('Bulk Generation', IMGSEO_TEXT_DOMAIN); ?></h1>
    <p class="description">
        <?php _e('This feature automatically processes alternative texts for all images on your site.', IMGSEO_TEXT_DOMAIN); ?>
    </p>
    
    <div class="imgseo-stats-container" style="display: flex; flex-wrap: wrap; gap: 20px; margin: 20px 0;">
        <?php
        // Get media library stats
        $total_images = wp_count_posts('attachment')->inherit;
        
        // Count images without alt text
        global $wpdb;
        $images_without_alt = $wpdb->get_var(
            "SELECT COUNT(*) FROM $wpdb->posts p
            LEFT JOIN $wpdb->postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
            WHERE p.post_type = 'attachment' 
            AND (p.post_mime_type LIKE 'image/%')
            AND (pm.meta_value IS NULL OR pm.meta_value = '')"
        );
        
        // Get available credits
        $api_key = get_option('imgseo_api_key', '');
        $api_verified = !empty($api_key) && get_option('imgseo_api_verified', false);
        $credits = get_option('imgseo_credits', 0);
        ?>
        
        <div class="stats-card" style="flex: 1; min-width: 200px; padding: 20px; background-color: #f0f6fc; border-left: 5px solid #2271b1; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; gap: 15px;">
                <span class="dashicons dashicons-images-alt2" style="font-size: 30px; color: #2271b1;"></span>
                <div>
                    <h3 style="margin: 0; font-size: 16px; color: #555;"><?php _e('Total Images', IMGSEO_TEXT_DOMAIN); ?></h3>
                    <div style="font-size: 24px; font-weight: bold; margin-top: 5px;"><?php echo esc_html($total_images); ?></div>
                </div>
            </div>
        </div>
        
        <div class="stats-card" style="flex: 1; min-width: 200px; padding: 20px; background-color: #fcf0f0; border-left: 5px solid #b72121; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; gap: 15px;">
                <span class="dashicons dashicons-warning" style="font-size: 30px; color: #b72121;"></span>
                <div>
                    <h3 style="margin: 0; font-size: 16px; color: #555;"><?php _e('Images without alt text', IMGSEO_TEXT_DOMAIN); ?></h3>
                    <div style="font-size: 24px; font-weight: bold; margin-top: 5px;"><?php echo esc_html($images_without_alt); ?></div>
                </div>
            </div>
        </div>
        
        <div class="stats-card" style="flex: 1; min-width: 200px; padding: 20px; background-color: #f0fcf0; border-left: 5px solid #1eb721; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; gap: 15px;">
                <span class="dashicons dashicons-tickets-alt" style="font-size: 30px; color: #1eb721;"></span>
                <div>
                    <h3 style="margin: 0; font-size: 16px; color: #555;"><?php _e('Available Credits', IMGSEO_TEXT_DOMAIN); ?></h3>
                    <div style="font-size: 24px; font-weight: bold; margin-top: 5px;">
                        <?php 
                        if ($api_verified && $credits > 0) {
                            echo esc_html($credits);
                        } else {
                            echo '<span style="color: #b72121;">0</span>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php
    // Check if the user has a valid API key
    $api_key = get_option('imgseo_api_key', '');
    $api_verified = !empty($api_key) && get_option('imgseo_api_verified', false);
    $credits = get_option('imgseo_credits', 0);
    $api_missing = empty($api_key) || !$api_verified; // Allows starting even with insufficient credits
    // We no longer block based on credits, the process will stop itself when necessary
    
    if (empty($api_key)) {
        echo '<div class="notice notice-error">';
        echo '<p><strong>' . __('API Key missing!', IMGSEO_TEXT_DOMAIN) . '</strong> ';
        echo __('To use bulk generation, you must first configure the ImgSEO API key. ', IMGSEO_TEXT_DOMAIN);
        echo '<a href="' . admin_url('admin.php?page=imgseo&tab=api') . '" class="button button-primary">' . __('Configure API Key', IMGSEO_TEXT_DOMAIN) . '</a>';
        echo '</p></div>';
    } elseif (!$api_verified) {
        echo '<div class="notice notice-error">';
        echo '<p><strong>' . __('API Key not verified!', IMGSEO_TEXT_DOMAIN) . '</strong> ';
        echo __('To use bulk generation, you need to verify the API key. ', IMGSEO_TEXT_DOMAIN);
        echo '<a href="' . admin_url('admin.php?page=imgseo&tab=api') . '" class="button button-primary">' . __('Verify API Key', IMGSEO_TEXT_DOMAIN) . '</a>';
        echo '</p></div>';
    } elseif ($credits <= 0) {
        echo '<div class="notice notice-error">';
        echo '<p><strong>' . __('Insufficient credits!', IMGSEO_TEXT_DOMAIN) . '</strong> ';
        echo __('You have no available credits. Proceed only if you intend to purchase credits during processing. ', IMGSEO_TEXT_DOMAIN);
        echo '<a href="https://ai.imgseo.net/dashboard" target="_blank" class="button button-primary">' . __('Purchase credits', IMGSEO_TEXT_DOMAIN) . '</a>';
        echo '</p></div>';
    } elseif ($credits < $images_without_alt && $credits > 0) {
        echo '<div class="notice notice-warning">';
        echo '<p><strong>' . __('Limited credits:', IMGSEO_TEXT_DOMAIN) . '</strong></p>';
        echo sprintf(
            __('You have %1$s available credits, but there are %2$s images without alt text. Processing will stop when credits are exhausted. ', IMGSEO_TEXT_DOMAIN),
            '<strong>' . $credits . '</strong>',
            '<strong>' . $images_without_alt . '</strong>'
        );
        echo '<a href="https://ai.imgseo.net/dashboard" target="_blank" class="button button-primary">' . __('Purchase more credits', IMGSEO_TEXT_DOMAIN) . '</a>';
        echo '</p></div>';
    }
    ?>
    
    <form id="bulk-generate-form" method="post">
        <div class="bulk-options">
            <div class="option-group">
                <label style="display: block; margin: 1rem 0;">
                    <input type="checkbox" name="overwrite" value="1">
                    <?php _e('Overwrite Existing Alt Texts', IMGSEO_TEXT_DOMAIN); ?>
                </label>
            </div>
            
            <div class="option-group">
                <h3><?php _e('Update Other Fields', IMGSEO_TEXT_DOMAIN); ?></h3>
                <p class="description"><?php _e('Select which other image fields to update with the generated alt text.', IMGSEO_TEXT_DOMAIN); ?></p>
                <div style="margin-left: 15px;">
                    <label style="display: block; margin: 0.5rem 0;">
                        <input type="checkbox" name="update_title" value="1" <?php checked(get_option('imgseo_update_title'), 1); ?>>
                        <?php _e('Update Title', IMGSEO_TEXT_DOMAIN); ?>
                    </label>
                    <label style="display: block; margin: 0.5rem 0;">
                        <input type="checkbox" name="update_caption" value="1" <?php checked(get_option('imgseo_update_caption'), 1); ?>>
                        <?php _e('Update Caption', IMGSEO_TEXT_DOMAIN); ?>
                    </label>
                    <label style="display: block; margin: 0.5rem 0;">
                        <input type="checkbox" name="update_description" value="1" <?php checked(get_option('imgseo_update_description'), 1); ?>>
                        <?php _e('Update Description', IMGSEO_TEXT_DOMAIN); ?>
                    </label>
                </div>
            </div>
            
            <div class="option-group">
                <h3><?php _e('Processing Speed', IMGSEO_TEXT_DOMAIN); ?></h3>
                <p class="description"><?php _e('Select the processing speed. Higher speeds process more images in parallel but may put more load on your server.', IMGSEO_TEXT_DOMAIN); ?></p>
                
                <div style="margin-left: 15px;">
                    <?php 
                    // Get current processing speed
                    $current_speed = get_option('imgseo_processing_speed', 'normal');
                    ?>
                    <select name="processing_speed" id="processing_speed" class="regular-text">
                        <option value="slow" <?php selected($current_speed, 'slow'); ?>><?php _e('Slow (2 images at once)', IMGSEO_TEXT_DOMAIN); ?></option>
                        <option value="normal" <?php selected($current_speed, 'normal'); ?>><?php _e('Normal (3 images at once)', IMGSEO_TEXT_DOMAIN); ?></option>
                        <option value="fast" <?php selected($current_speed, 'fast'); ?>><?php _e('Fast (4 images at once)', IMGSEO_TEXT_DOMAIN); ?></option>
                        <option value="ultra" <?php selected($current_speed, 'ultra'); ?>><?php _e('Ultra (5 images at once)', IMGSEO_TEXT_DOMAIN); ?></option>
                    </select>
                </div>
                <input type="hidden" name="processing_mode" value="async">
            </div>
        </div>
        
        <?php 
        // Show the button, but disable it if the API key is not configured
        $btn_attrs = array(
            'id' => 'imgseo-bulk-generate'
        );
        if ($api_missing) {
            $btn_attrs['disabled'] = 'disabled';
            $btn_attrs['title'] = __('Configure the API key first to enable this feature', IMGSEO_TEXT_DOMAIN);
        }
        submit_button(__('Start Generation', IMGSEO_TEXT_DOMAIN), 'primary', 'submit', false, $btn_attrs);
        ?>
    </form>
    
    <!-- Container for notification messages -->
    <div id="imgseo-notification-container" style="margin: 20px 0; display: none;"></div>

    <div id="progress-container" style="display:none;">
        <h2><?php _e('Processing Status:', IMGSEO_TEXT_DOMAIN); ?></h2>
        <div id="progress-bar" style="width: 100%; background-color: #9f9f9f;">
            <div id="progress-bar-fill" style="height: 20px; width: 0%; background-color: #4caf50;"></div>
        </div>
        <p id="progress-text"></p>
        <p class="description" id="progress-description"><?php _e('Please keep this page open until processing completes.', IMGSEO_TEXT_DOMAIN); ?></p>
        <div class="button-group">
            <button id="imgseo-cancel" class="button button-secondary"><?php _e('Hide Monitoring', IMGSEO_TEXT_DOMAIN); ?></button>
            <button id="imgseo-stop" class="button button-secondary" style="margin-left: 10px; color: #fff; background-color: #d63638; border-color: #d63638;"><?php _e('Stop Processing', IMGSEO_TEXT_DOMAIN); ?></button>
        </div>
    </div>
    
    
    <!-- Separator between the form/progress section and the jobs section -->
    <hr style="margin: 40px 0 30px; border-top: 1px solid #ddd; border-bottom: 0;">
    
    <div id="job-status-list" class="job-status-list" style="background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 4px; <?php echo $api_missing ? 'display:none;' : ''; ?>">
        <h2 style="margin-top: 0; border-bottom: 2px solid #ddd; padding-bottom: 10px; color: #23282d;">
            <span class="dashicons dashicons-list-view" style="vertical-align: middle; font-size: 24px; margin-right: 5px;"></span>
            <?php _e('Recent Jobs', IMGSEO_TEXT_DOMAIN); ?>
        </h2>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <p class="description"><?php _e('History of previously started bulk processes.', IMGSEO_TEXT_DOMAIN); ?></p>
            <button id="delete-all-jobs" class="button button-secondary"><?php _e('Delete all jobs', IMGSEO_TEXT_DOMAIN); ?></button>
        </div>
        <?php
        global $wpdb;
        $table_name = $wpdb->prefix . 'imgseo_jobs';
        
        // Verifica se la tabella esiste
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if ($table_exists) {
            $recent_jobs = $wpdb->get_results(
                "SELECT * FROM $table_name ORDER BY updated_at DESC LIMIT 10"
            );
            
            if ($recent_jobs) {
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr>';
                echo '<th>ID</th>';
                echo '<th>' . __('Status', IMGSEO_TEXT_DOMAIN) . '</th>';
                echo '<th>' . __('Progress', IMGSEO_TEXT_DOMAIN) . '</th>';
                echo '<th>' . __('Creation Date', IMGSEO_TEXT_DOMAIN) . '</th>';
                echo '<th>' . __('Last Update', IMGSEO_TEXT_DOMAIN) . '</th>';
                echo '<th>' . __('Actions', IMGSEO_TEXT_DOMAIN) . '</th>';
                echo '</tr></thead>';
                echo '<tbody>';
                
                foreach ($recent_jobs as $job) {
                    // Check for accurate image count for stopped/completed jobs with zero processed images
                    $processed_count = $job->processed_images;
                    
                    if (($job->status == 'stopped' || $job->status == 'completed') && $processed_count == 0) {
                        // Try to get count from logs
                        $log_table_name = $wpdb->prefix . 'imgseo_logs';
                        $log_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$log_table_name}'") === $log_table_name;
                        
                        if ($log_table_exists) {
                            $count = $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM {$log_table_name} WHERE job_id = %s",
                                $job->job_id
                            ));
                            
                            if ($count && intval($count) > 0) {
                                $processed_count = intval($count);
                                
                                // Update the database record with correct count
                                $wpdb->update(
                                    $table_name,
                                    ['processed_images' => $processed_count],
                                    ['id' => $job->id]
                                );
                                
                                // Also update the object for current display
                                $job->processed_images = $processed_count;
                            }
                        }
                    }
                    
                    $progress = ($job->total_images > 0) ?
                        round(($processed_count / $job->total_images) * 100) : 0;
                    
                    $status_label = '';
                    $status_class = '';
                    
                    switch ($job->status) {
                        case 'pending':
                            $status_label = __('Pending', IMGSEO_TEXT_DOMAIN);
                            $status_class = 'status-pending';
                            break;
                        case 'processing':
                            $status_label = __('Processing', IMGSEO_TEXT_DOMAIN);
                            $status_class = 'status-processing';
                            break;
                        case 'completed':
                            $status_label = __('Completed', IMGSEO_TEXT_DOMAIN);
                            $status_class = 'status-completed';
                            break;
                        case 'stopped':
                            $status_label = __('Stopped', IMGSEO_TEXT_DOMAIN);
                            $status_class = 'status-stopped';
                            break;
                        default:
                            $status_label = $job->status;
                            break;
                    }
                    
                    echo '<tr>';
                    echo '<td>' . esc_html($job->job_id) . '</td>';
                    echo '<td><span class="' . $status_class . '">' . esc_html($status_label) . '</span></td>';
                    echo '<td>' . esc_html($job->processed_images) . '/' . esc_html($job->total_images) . ' (' . $progress . '%)</td>';
                    echo '<td>' . esc_html($job->created_at) . '</td>';
                    echo '<td>' . esc_html($job->updated_at) . '</td>';
                    echo '<td>';
                    
                    // Mostra il pulsante di stop solo per i job pending o in elaborazione
                    if ($job->status === 'pending' || $job->status === 'processing') {
                        // Aggiungiamo un ID univoco per ogni pulsante stop e forziamo l'attributo data-job-id
                        $stop_btn_id = 'stop-job-' . esc_attr($job->job_id);
                        echo '<button type="button" id="' . $stop_btn_id . '" class="button button-small stop-job-button" data-job-id="' . esc_attr($job->job_id) . '" style="color: #fff; background-color: #d63638; border-color: #d63638; margin-right: 5px;" onclick="jQuery(this).data(\'job-id\', \'' . esc_attr($job->job_id) . '\');">' . __('Stop', IMGSEO_TEXT_DOMAIN) . '</button>';
                    }
                    
                    // Aggiungi sempre il pulsante Elimina
                    echo '<button type="button" class="button button-small delete-job-button" data-job-id="' . esc_attr($job->job_id) . '">' . __('Delete', IMGSEO_TEXT_DOMAIN) . '</button>';
                    echo '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table>';
            } else {
                echo '<p>' . __('No recent jobs found.', IMGSEO_TEXT_DOMAIN) . '</p>';
            }
        } else {
            echo '<p>' . __('The jobs table does not exist yet. Deactivate and reactivate the plugin to create it.', IMGSEO_TEXT_DOMAIN) . '</p>';
        }
        ?>
    </div>
</div>

<!-- Modal dialog for insufficient credits confirmation -->
<div id="credits-confirmation-modal" class="imgseo-modal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
    <div class="imgseo-modal-content" style="background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 4px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
        <h3 style="margin-top: 0; color: #d63638;">
            <span class="dashicons dashicons-warning" style="color: #d63638;"></span> 
            <?php _e('Insufficient credits for all images', IMGSEO_TEXT_DOMAIN); ?>
        </h3>
        <p id="credits-confirmation-message"></p>
        <div style="text-align: right; margin-top: 20px;">
            <button id="credits-cancel-button" class="button button-secondary"><?php _e('Cancel', IMGSEO_TEXT_DOMAIN); ?></button>
            <button id="credits-confirm-button" class="button button-primary" style="margin-left: 10px;"><?php _e('Continue anyway', IMGSEO_TEXT_DOMAIN); ?></button>
        </div>
    </div>
</div>

<!-- Modal dialog for job interruption confirmation -->
<div id="stop-job-confirmation-modal" class="imgseo-modal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
    <div class="imgseo-modal-content" style="background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 4px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
        <h3 style="margin-top: 0; color: #d63638;">
            <span class="dashicons dashicons-warning" style="color: #d63638;"></span> 
            <?php _e('Stop processing', IMGSEO_TEXT_DOMAIN); ?>
        </h3>
        <p id="stop-job-confirmation-message"></p>
        <div style="text-align: right; margin-top: 20px;">
            <button id="stop-job-cancel-button" class="button button-secondary"><?php _e('Cancel', IMGSEO_TEXT_DOMAIN); ?></button>
            <button id="stop-job-confirm-button" class="button button-primary" style="margin-left: 10px;"><?php _e('Stop', IMGSEO_TEXT_DOMAIN); ?></button>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Function to show notifications instead of alerts
    function showNotification(message, type) {
        var $container = $('#imgseo-notification-container');
        var noticeClass = 'notice-info';
        
        if (type === 'error') {
            noticeClass = 'notice-error';
        } else if (type === 'warning') {
            noticeClass = 'notice-warning';
        } else if (type === 'success') {
            noticeClass = 'notice-success';
        }
        
        var $notice = $('<div class="notice ' + noticeClass + '" style="padding: 10px; margin: 10px 0;"><p>' + message + '</p></div>');
        
        // Add close button
        var $dismissBtn = $('<button type="button" class="notice-dismiss"></button>');
        $dismissBtn.on('click', function() {
            $notice.fadeOut(300, function() { $(this).remove(); });
        });
        
        $notice.append($dismissBtn);
        $container.empty().append($notice).show();
        
        // Scroll the page to the notification
        $('html, body').animate({
            scrollTop: $container.offset().top - 50
        }, 500);
    }
    // Global variables to store form parameters
    var formData = {};
    
    // Function to start image processing
    function startProcessing(isConfirmed) {
        // Prepare UI
        $('#progress-bar-fill').css('width', '0%');
        $('#progress-text').text("<?php _e('Starting processing...', IMGSEO_TEXT_DOMAIN); ?>");
        $('#progress-container').show();
        
        // Show the cron status container
        $('#cron-status-container').show();
        
        // Set the descriptive text based on the processing mode
        $('#progress-description').text("<?php _e('Processing takes place in real-time. Keep this page open until completion.', IMGSEO_TEXT_DOMAIN); ?>");
        
        // Disable the submit button
        $('#imgseo-bulk-generate').prop('disabled', true).text("<?php _e('Processing...', IMGSEO_TEXT_DOMAIN); ?>");
        
        // If it's a confirmation of insufficient credits, add a notification message
        if (isConfirmed) {
            showNotification('<?php _e('Processing will continue until available credits are exhausted.', IMGSEO_TEXT_DOMAIN); ?>', 'warning');
        }
        
        // Start the process
        $.ajax({
            url: ImgSEO.ajax_url,
            type: 'POST',
            data: {
                action: 'imgseo_start_bulk',
                overwrite: formData.overwrite,
                processing_mode: formData.processingMode,
                update_title: formData.updateTitle,
                update_description: formData.updateDescription,
                update_caption: formData.updateCaption,
                security: ImgSEO.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    var jobId = response.data.job_id;
                    var imageIds = response.data.image_ids;
                    $('#progress-text').text(response.data.message);
                    
                    // Update the stop button with the job ID
                    $('#imgseo-stop').data('job-id', jobId);
                    
                    // Asynchronous process: process directly
                    processAsyncBatch(jobId, imageIds);
                } else {
                    var errorMessage = response.data ? response.data.message : '<?php _e('Error starting the process', IMGSEO_TEXT_DOMAIN); ?>';
                    
                    // Usa il sistema di notifiche invece di alert
                    showNotification(errorMessage, 'error');
                    
                    // Se c'è un URL di reindirizzamento, reindirizza l'utente alla pagina di configurazione
                    if (response.data && response.data.redirect_url) {
                        setTimeout(function() {
                            window.location.href = response.data.redirect_url;
                        }, 1000);
                        return;
                    }
                    
                    $('#imgseo-bulk-generate').prop('disabled', false).text("<?php _e('Start Generation', IMGSEO_TEXT_DOMAIN); ?>");
                    $('#progress-container').hide();
                }
            },
            error: function() {
                showNotification('<?php _e('Error starting the process. Please try again later.', IMGSEO_TEXT_DOMAIN); ?>', 'error');
                $('#imgseo-bulk-generate').prop('disabled', false).text("<?php _e('Start Generation', IMGSEO_TEXT_DOMAIN); ?>");
                $('#progress-container').hide();
            }
        });
    }
    
    // Gestione modale di conferma per crediti insufficienti
    $('#credits-cancel-button').on('click', function() {
        // Nascondi il modale
        $('#credits-confirmation-modal').hide();
        
        // Riabilita il pulsante di submit
        $('#imgseo-bulk-generate').prop('disabled', false).text("<?php _e('Start Generation', IMGSEO_TEXT_DOMAIN); ?>");
    });
    
    $('#credits-confirm-button').on('click', function() {
        // Nascondi il modale
        $('#credits-confirmation-modal').hide();
        
        // Avvia l'elaborazione con la conferma
        startProcessing(true);
    });
    
    // Gestione form di avvio generazione bulk
    $('#bulk-generate-form').on('submit', function(e) {
        e.preventDefault();
        
        // Ottieni i dati del form
        formData = {
            overwrite: $('input[name="overwrite"]').is(':checked') ? 1 : 0,
            processingMode: $('input[name="processing_mode"]:checked').val() || 'background',
            updateTitle: $('input[name="update_title"]').is(':checked') ? 1 : 0,
            updateCaption: $('input[name="update_caption"]').is(':checked') ? 1 : 0,
            updateDescription: $('input[name="update_description"]').is(':checked') ? 1 : 0
        };
        
        // Controlla i crediti disponibili e le immagini da elaborare
        var availableCredits = <?php echo esc_js($credits); ?>;
        var imagesWithoutAlt = <?php echo esc_js($images_without_alt); ?>;
        
        // Se non ci sono immagini da elaborare, mostra un messaggio e interrompi
        if (imagesWithoutAlt <= 0) {
            showNotification('<?php _e('No images to process. All images already have alt text.', IMGSEO_TEXT_DOMAIN); ?>', 'warning');
            return;
        }
        
        // Se i crediti sono insufficienti ma maggiori di zero, mostra la finestra di dialogo di conferma
        if (availableCredits > 0 && availableCredits < imagesWithoutAlt) {
            // Aggiorna il messaggio di conferma
            var message = '<?php _e('You have <strong>{credits}</strong> available credits, but there are <strong>{images}</strong> images without alt text. Only the first {credits} images will be processed and then processing will stop. Do you want to continue?', IMGSEO_TEXT_DOMAIN); ?>';
            message = message.replace('{credits}', availableCredits).replace('{images}', imagesWithoutAlt).replace('{credits}', availableCredits);
            $('#credits-confirmation-message').html(message);
            
            // Mostra la finestra di dialogo
            $('#credits-confirmation-modal').show();
        } else if (availableCredits <= 0) {
            // Se i crediti sono zero, mostra la finestra di dialogo di conferma
            var message = '<?php _e('You have no available credits. Processing cannot start until you purchase credits. Do you want to continue anyway?', IMGSEO_TEXT_DOMAIN); ?>';
            $('#credits-confirmation-message').html(message);
            
            // Mostra la finestra di dialogo
            $('#credits-confirmation-modal').show();
        } else {
            // Se i crediti sono sufficienti, avvia l'elaborazione direttamente
            startProcessing(false);
        }
    });
    
    // Pulsante per forzare l'esecuzione del cron
    $('#force-cron-button').on('click', function() {
        var $button = $(this);
        var $spinner = $('#cron-spinner');
        
        $button.prop('disabled', true);
        $spinner.css('visibility', 'visible');
        
        $.ajax({
            url: ImgSEO.ajax_url,
            type: 'POST',
            data: {
                action: 'imgseo_force_cron',
                security: ImgSEO.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#cron-status-text').text('<?php _e('Status: Processing started manually', IMGSEO_TEXT_DOMAIN); ?>');
                    $('#last-cron-run').text('<?php _e('Last update:', IMGSEO_TEXT_DOMAIN); ?> ' + response.data.last_run + ' (' + response.data.time_ago + ')');
                    
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                } else {
                    $('#cron-status-text').html('<span style="color: red;"><?php _e('Status: Error starting the process', IMGSEO_TEXT_DOMAIN); ?></span>');
                    showNotification('<?php _e('An error occurred while starting the process.', IMGSEO_TEXT_DOMAIN); ?>', 'error');
                }
            },
            error: function() {
                $('#cron-status-text').html('<span style="color: red;"><?php _e('Status: Connection error', IMGSEO_TEXT_DOMAIN); ?></span>');
                showNotification('<?php _e('A connection error occurred.', IMGSEO_TEXT_DOMAIN); ?>', 'error');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.css('visibility', 'hidden');
            }
        });
    });
    
    // Pulsante per interrompere l'elaborazione con gestione sicura per prevenire doppi clic
    var isStopInProgress = false;
    
    // Dialog modale per l'interruzione del job
    var $stopJobButton;
    var stopJobId;
    
    function showStopJobModal(jobId, $button) {
        stopJobId = jobId;
        $stopJobButton = $button;
        
        var message = '<?php _e('Are you sure you want to stop this job? This action cannot be undone.', IMGSEO_TEXT_DOMAIN); ?>';
        $('#stop-job-confirmation-message').html(message);
        $('#stop-job-confirmation-modal').show();
    }
    
    $('#stop-job-cancel-button').on('click', function() {
        $('#stop-job-confirmation-modal').hide();
    });
    
    $('#stop-job-confirm-button').on('click', function() {
        $('#stop-job-confirmation-modal').hide();
        
        isStopInProgress = true;
        
        // Disabilita tutti i pulsanti di stop
        $('#imgseo-stop, .stop-job-button').prop('disabled', true);
        $stopJobButton.text('<?php _e('Stopping...', IMGSEO_TEXT_DOMAIN); ?>');

        // Aggiorna il testo di stato
        $('#progress-text').text("<?php _e('Stopping in progress, please wait...', IMGSEO_TEXT_DOMAIN); ?>");
        
        $.ajax({
            url: ImgSEO.ajax_url,
            type: 'POST',
            data: {
                action: 'imgseo_stop_job',
                job_id: stopJobId,
                security: ImgSEO.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Aggiorna il testo dello stato
                    var processedCount = response.data.processed_images || 0;
                    $('#progress-text').text('<?php _e('Processing stopped:', IMGSEO_TEXT_DOMAIN); ?> ' + processedCount + ' <?php _e('images processed', IMGSEO_TEXT_DOMAIN); ?>');
                    
                    // Ricarica la pagina dopo un breve ritardo
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    isStopInProgress = false;
                    $('#imgseo-stop, .stop-job-button').prop('disabled', false);
                    $stopJobButton.text('<?php _e('Stop Processing', IMGSEO_TEXT_DOMAIN); ?>');
                    showNotification(response.data ? response.data.message : '<?php _e('Error stopping the job', IMGSEO_TEXT_DOMAIN); ?>', 'error');
                }
            },
            error: function() {
                isStopInProgress = false;
                $('#imgseo-stop, .stop-job-button').prop('disabled', false);
                $stopJobButton.text('<?php _e('Stop Processing', IMGSEO_TEXT_DOMAIN); ?>');
                showNotification('<?php _e('Connection error while stopping the job', IMGSEO_TEXT_DOMAIN); ?>', 'error');
            }
        });
    });
    
    $('#imgseo-stop, .stop-job-button').on('click', function() {
        // Previeni clic multipli
        if (isStopInProgress) {
            return;
        }
        
        var $button = $(this);
        var jobId = $button.data('job-id');
        
        if (!jobId) {
            return;
        }
        
        showStopJobModal(jobId, $button);
    });
    
    // Pulsante per eliminare un job
    $('.delete-job-button').on('click', function() {
        var jobId = $(this).data('job-id');
        
        if (!jobId) {
            return;
        }
        
        if (confirm('<?php _e('Are you sure you want to delete this job?', IMGSEO_TEXT_DOMAIN); ?>')) {
            var $button = $(this);
            $button.prop('disabled', true).text('<?php _e('Deleting...', IMGSEO_TEXT_DOMAIN); ?>');
            
            $.ajax({
                url: ImgSEO.ajax_url,
                type: 'POST',
                data: {
                    action: 'imgseo_delete_job',
                    job_id: jobId,
                    security: ImgSEO.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        $button.prop('disabled', false).text('<?php _e('Delete', IMGSEO_TEXT_DOMAIN); ?>');
                        showNotification(response.data ? response.data.message : '<?php _e('Error deleting the job', IMGSEO_TEXT_DOMAIN); ?>', 'error');
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text('<?php _e('Delete', IMGSEO_TEXT_DOMAIN); ?>');
                    showNotification('<?php _e('Connection error while deleting the job', IMGSEO_TEXT_DOMAIN); ?>', 'error');
                }
            });
        }
    });
    
    // Pulsante per eliminare tutti i job
    $('#delete-all-jobs').on('click', function() {
        if (confirm('<?php _e('Are you sure you want to delete all jobs?', IMGSEO_TEXT_DOMAIN); ?>')) {
            var $button = $(this);
            $button.prop('disabled', true).text('<?php _e('Deleting in progress...', IMGSEO_TEXT_DOMAIN); ?>');
            
            $.ajax({
                url: ImgSEO.ajax_url,
                type: 'POST',
                data: {
                    action: 'imgseo_delete_all_jobs',
                    security: ImgSEO.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        $button.prop('disabled', false).text('<?php _e('Delete all jobs', IMGSEO_TEXT_DOMAIN); ?>');
                        showNotification('<?php _e('Error deleting jobs', IMGSEO_TEXT_DOMAIN); ?>', 'error');
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text('<?php _e('Delete all jobs', IMGSEO_TEXT_DOMAIN); ?>');
                    showNotification('<?php _e('Connection error while deleting jobs', IMGSEO_TEXT_DOMAIN); ?>', 'error');
                }
            });
        }
    });
    
    // Funzione per monitorare lo stato del job con polling frequente
    function monitorJobStatusWithPolling(jobId) {
        var $progressBar = $('#progress-bar-fill');
        var $progressText = $('#progress-text');
        var $cronStatusText = $('#cron-status-text');
        var lastLogId = 0;
        var pollingInterval;
        
        // Contenitore per i log se non esiste
        if ($('#processing-logs-container').length === 0) {
            $('#progress-container').after('<div id="processing-logs-container" class="log-container"><h3>Log di elaborazione in tempo reale</h3><div id="processing-logs" class="log-entries"></div></div>');
        }
        var $logsContainer = $('#processing-logs');
        
        function checkJobStatus() {
            $.ajax({
                url: ImgSEO.ajax_url,
                type: 'POST',
                data: {
                    action: 'imgseo_check_job_status',
                    job_id: jobId,
                    last_log_id: lastLogId,
                    security: ImgSEO.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Aggiorna la barra di progresso
                        $progressBar.css('width', response.data.progress + '%');
                        $progressText.text(response.data.message);
                        
                        // Aggiorna lo stato del cron
                        var status = response.data.status;
                        switch(status) {
                            case 'pending':
                                $cronStatusText.text('<?php _e('Status: Waiting', IMGSEO_TEXT_DOMAIN); ?>');
                                break;
                            case 'processing':
                                $cronStatusText.text('<?php _e('Status: Processing', IMGSEO_TEXT_DOMAIN); ?>');
                                break;
                            case 'completed':
                                $cronStatusText.text('<?php _e('Status: Completed', IMGSEO_TEXT_DOMAIN); ?>');
                                break;
                            case 'stopped':
                                $cronStatusText.text('<?php _e('Status: Stopped', IMGSEO_TEXT_DOMAIN); ?>');
                                break;
                            default:
                                $cronStatusText.text('<?php _e('Status:', IMGSEO_TEXT_DOMAIN); ?> ' + status);
                        }
                        
                        // Aggiorna i log se presenti
                        if (response.data.logs && response.data.logs.length > 0) {
                            response.data.logs.forEach(function(log) {
                                var statusClass = log.status === 'success' ? 'log-success' : 'log-error';
                                var logEntry = 
                                    '<div class="log-entry ' + statusClass + '">' +
                                    '<span class="log-time">' + formatTime(log.time) + '</span>' +
                                    '<span class="log-filename">' + log.filename + '</span>' +
                                    '<span class="log-text" title="' + log.alt_text.replace(/"/g, '&quot;') + '">' + 
                                    truncateText(log.alt_text, 60) + '</span>' +
                                    '</div>';
                                $logsContainer.append(logEntry);
                            });
                            
                            // Scrolla in fondo
                            $logsContainer.scrollTop($logsContainer[0].scrollHeight);
                            
                            // Aggiorna l'ultimo ID di log
                            if (response.data.logs.length > 0) {
                                var lastLog = response.data.logs[response.data.logs.length - 1];
                                lastLogId = lastLog.id;
                            }
                        }
                        
                        // Controlla se il job è completo
                        if (status === 'completed' || status === 'stopped') {
                            clearInterval(pollingInterval);
                            
                            // Mostra una notifica
                            var notificationType = status === 'completed' ? 'success' : 'warning';
                            var notificationMessage = status === 'completed' ? 
                                '<?php _e('Processing completed successfully!', IMGSEO_TEXT_DOMAIN); ?>' :
                                '<?php _e('Processing stopped by user.', IMGSEO_TEXT_DOMAIN); ?>';
                            
                            setTimeout(function() {
                                showNotification(notificationMessage, notificationType);
                            }, 1000);
                            
                            if (status === 'completed') {
                                $progressBar.css('width', '100%');
                            }
                        }
                    }
                },
                error: function() {
                    // Gestisci errori in modo silenzioso
                    console.log('Connection error while checking job status');
                }
            });
        }
        
        // Avvia il polling
        checkJobStatus(); // Prima chiamata immediata
        pollingInterval = setInterval(checkJobStatus, 5000); // Poi ogni 5 secondi
    }
    
    // Funzione per elaborare il batch asincrono
    function processAsyncBatch(jobId, imageIds) {
        var $progressBar = $('#progress-bar-fill');
        var $progressText = $('#progress-text');
        var $logsContainer = $('#processing-logs');
        var totalImages = imageIds.length;
        var currentIndex = 0;
        
        // Contenitore per i log se non esiste
        if ($('#processing-logs-container').length === 0) {
            $('#progress-container').after('<div id="processing-logs-container" class="log-container"><h3>Log di elaborazione in tempo reale</h3><div id="processing-logs" class="log-entries"></div></div>');
            $logsContainer = $('#processing-logs');
        }
        
        function processNextImage() {
            if (currentIndex >= imageIds.length) {
                // Tutti elaborati
                $progressText.text('<?php _e('Processing completed!', IMGSEO_TEXT_DOMAIN); ?>');
                showNotification('<?php _e('Processing completed successfully!', IMGSEO_TEXT_DOMAIN); ?>', 'success');
                $('#imgseo-bulk-generate').prop('disabled', false).text('<?php _e('Start Generation', IMGSEO_TEXT_DOMAIN); ?>');
                return;
            }
            
            var imageId = imageIds[currentIndex];
            $progressText.text('<?php _e('Processing image', IMGSEO_TEXT_DOMAIN); ?> ' + (currentIndex + 1) + '/' + totalImages);
            $progressBar.css('width', ((currentIndex + 1) / totalImages * 100) + '%');
            
            $.ajax({
                url: ImgSEO.ajax_url,
                type: 'POST',
                data: {
                    action: 'imgseo_process_single_image',
                    image_id: imageId,
                    job_id: jobId,
                    security: ImgSEO.nonce
                },
                success: function(response) {
                    currentIndex++;
                    
                    if (response.success && response.data) {
                        var statusClass = response.data.status === 'success' ? 'log-success' : 'log-error';
                        var logEntry = 
                            '<div class="log-entry ' + statusClass + '">' +
                            '<span class="log-time">' + formatTime(new Date().getTime() / 1000) + '</span>' +
                            '<span class="log-filename">' + response.data.filename + '</span>' +
                            '<span class="log-text" title="' + response.data.alt_text.replace(/"/g, '&quot;') + '">' + 
                            truncateText(response.data.alt_text, 60) + '</span>' +
                            '</div>';
                        $logsContainer.append(logEntry);
                        $logsContainer.scrollTop($logsContainer[0].scrollHeight);
                    }
                    
                    // Controlla se il job è stato interrotto
                    $.ajax({
                        url: ImgSEO.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'imgseo_check_job_status',
                            job_id: jobId,
                            last_log_id: 0,
                            security: ImgSEO.nonce
                        },
                        success: function(statusResponse) {
                            if (statusResponse.success && statusResponse.data) {
                                var status = statusResponse.data.status;
                                if (status === 'stopped') {
                                    $progressText.text('<?php _e('Processing stopped by user.', IMGSEO_TEXT_DOMAIN); ?>');
                                    showNotification('<?php _e('Processing stopped by user.', IMGSEO_TEXT_DOMAIN); ?>', 'warning');
                                    $('#imgseo-bulk-generate').prop('disabled', false).text('<?php _e('Start Generation', IMGSEO_TEXT_DOMAIN); ?>');
                                    return;
                                } else {
                                    // Continua con l'immagine successiva
                                    setTimeout(processNextImage, 100);
                                }
                            }
                        },
                        error: function() {
                            // Continua comunque al prossimo
                            setTimeout(processNextImage, 100);
                        }
                    });
                },
                error: function() {
                    currentIndex++;
                    var logEntry = 
                        '<div class="log-entry log-error">' +
                        '<span class="log-time">' + formatTime(new Date().getTime() / 1000) + '</span>' +
                        '<span class="log-filename">ID: ' + imageId + '</span>' +
                        '<span class="log-text"><?php _e('Connection error', IMGSEO_TEXT_DOMAIN); ?></span>' +
                        '</div>';
                    $logsContainer.append(logEntry);
                    $logsContainer.scrollTop($logsContainer[0].scrollHeight);
                    
                    // Continua comunque
                    setTimeout(processNextImage, 100);
                }
            });
        }
        
        // Avvia l'elaborazione
        processNextImage();
    }
    
    // Funzione per formattare il tempo
    function formatTime(timestamp) {
        var date = new Date(timestamp * 1000);
        var hours = date.getHours().toString().padStart(2, '0');
        var minutes = date.getMinutes().toString().padStart(2, '0');
        var seconds = date.getSeconds().toString().padStart(2, '0');
        return hours + ':' + minutes + ':' + seconds;
    }
    
    // Funzione per troncare il testo
    function truncateText(text, maxLength) {
        if (!text) return '';
        if (text.length <= maxLength) return text;
        return text.substr(0, maxLength) + '...';
    }
    
    // Pulsante per nascondere il monitoraggio
    $('#imgseo-cancel').on('click', function() {
        $('#progress-container').hide();
        $('#cron-status-container').hide();
        $('#processing-logs-container').hide();
    });
