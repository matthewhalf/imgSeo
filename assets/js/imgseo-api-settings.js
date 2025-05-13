/**
 * Script for managing ImgSEO API settings
 */
jQuery(document).ready(function($) {
    console.log("ImgSEO API settings script loaded");

    // Function to display status messages
    function showStatusMessage(container, type, message) {
        // Remove any existing messages
        container.empty();

        // Create icon based on message type
        var icon = '';
        switch (type) {
            case 'success':
                icon = 'dashicons-yes-alt';
                break;
            case 'error':
                icon = 'dashicons-dismiss';
                break;
            case 'warning':
                icon = 'dashicons-warning';
                break;
            case 'info':
                icon = 'dashicons-info';
                break;
            default:
                icon = 'dashicons-info';
        }

        // Create the message
        var html = '<div class="status-message ' + type + '">' +
                   '<span class="dashicons ' + icon + '"></span> ' +
                   message +
                   '</div>';

        // Add the message to the container
        container.html(html).show();
    }

    // Handle API Key visibility
    $('#toggle_api_key_visibility').on('click', function(e) {
        e.preventDefault();

        const $input = $('#imgseo_api_key');
        const $icon = $(this).find('.dashicons');

        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
        } else {
            $input.attr('type', 'password');
            $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
        }
    });

    // Handle API Key disconnect button
    $(document).on('click', '#disconnect_api_key', function(e) {
        e.preventDefault();

        var $button = $(this);

        if (confirm('Are you sure you want to disconnect your ImgSEO account? You will need to enter your ImgSEO Token again.')) {
            // Disable the button while disconnection is in progress
            $button.prop('disabled', true).html('<span class="spinner" style="visibility:visible; float:none; margin:0 5px 0 0;"></span> Disconnecting...');

            // Make AJAX call to disconnect on server side
            $.ajax({
                url: ImgSEOSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'imgseo_disconnect_api',
                    nonce: ImgSEOSettings.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Force a complete reload with anti-cache parameters
                        var cleanUrl = window.location.href.split('?')[0];
                        var reloadUrl = cleanUrl + '?page=imgseo&disconnected=1&_=' + new Date().getTime();
                        window.location.replace(reloadUrl);
                    } else {
                        alert('Error during disconnection: ' + (response.data ? response.data.message : 'Unknown error'));
                        $button.prop('disabled', false).html('<span class="dashicons dashicons-no-alt"></span> Disconnect');
                    }
                },
                error: function() {
                    alert('Connection error during disconnection');
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-no-alt"></span> Disconnect');
                }
            });
        }
    });

    // Handle API Key verification button
    $('#verify_api_key').on('click', function(e) {
        e.preventDefault();

        const $button = $(this);
        const $statusDiv = $('#api_key_status');
        const apiKey = $('#imgseo_api_key').val();

        if (!apiKey) {
            showStatusMessage($statusDiv, 'error', 'Enter an ImgSEO Token before proceeding');
            return;
        }

        // Disable the button and show loading message
        $button.prop('disabled', true).html('<span class="spinner" style="visibility:visible; float:none; margin:0 5px 0 0;"></span> ' + ImgSEOSettings.verify_message);
        showStatusMessage($statusDiv, 'info', ImgSEOSettings.verify_message);

        // Send AJAX request
        $.ajax({
            url: ImgSEOSettings.ajax_url,
            type: 'POST',
            data: {
                action: 'imgseo_verify_api_key',
                nonce: ImgSEOSettings.nonce,
                api_key: apiKey
            },
            success: function(response) {
                console.log("API verification response", response);

                if (response.success) {
                    // Update interface with success
                    showStatusMessage($statusDiv, 'success', 'ImgSEO Token verificato! Plan: <strong>' + response.data.plan + '</strong>');

                    // Update displayed credits
                    $('#imgseo_credits_display').text(response.data.credits);

                    // Update the last check date
                    $('#last_credits_check').html('<span class="dashicons dashicons-clock"></span> Last update: just now');

                    // Set the input as readonly
                    $('#imgseo_api_key').attr('readonly', true);

                    // Update buttons
                    $('.api-key-actions').html(
                        '<button type="button" id="disconnect_api_key" class="button button-secondary">' +
                        '<span class="dashicons dashicons-no-alt"></span> Disconnect' +
                        '</button> ' +
                        '<button type="button" id="verify_api_key" class="button button-secondary">' +
                        '<span class="dashicons dashicons-update"></span> Verify again' +
                        '</button>'
                    );

                    // Disable the "Save changes" button to avoid conflicts
                    var $submitButton = $('input[type="submit"]');
                    $submitButton.prop('disabled', true);

                    // Add a message explaining to the user what is happening
                    var $saveMessage = $('<div class="notice notice-info inline"><p><span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span> ImgSEO Token verified! The page will automatically refresh in a few seconds...</p></div>');
                    $saveMessage.insertAfter($submitButton);

                    // Reload the page to show all changes
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    // Show error message
                    showStatusMessage($statusDiv, 'error', 'Invalid ImgSEO Token. Make sure you copied it correctly or <a href="https://api.imgseo.net/register" target="_blank">register to get a new one</a>.');
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX error:", error, xhr.responseText);

                // Handle connection error
                showStatusMessage($statusDiv, 'error', 'Connection error to the server. Please try again in a moment.');
            },
            complete: function() {
                // Re-enable the button
                $button.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Verify ImgSEO Token');
            }
        });
    });

    // Handle credits refresh button
    $('#refresh_credits').on('click', function(e) {
        e.preventDefault();

        const $button = $(this);
        const $creditsDisplay = $('#imgseo_credits_display');
        const $lastCheckDisplay = $('#last_credits_check');

        // Disable the button and show loading message
        $button.prop('disabled', true).html('<span class="spinner" style="visibility:visible; float:none; margin:0 5px 0 0;"></span> ' + ImgSEOSettings.refresh_credits_message);

        // Send AJAX request
        $.ajax({
            url: ImgSEOSettings.ajax_url,
            type: 'POST',
            data: {
                action: 'imgseo_refresh_credits',
                nonce: ImgSEOSettings.nonce
            },
            success: function(response) {
                console.log("Credits refresh response", response);

                if (response.success) {
                    // Update displayed credits
                    var credits = response.data.credits || 0;
                    $creditsDisplay.text(credits);

                    // Add or remove the low-credits class based on the value
                    if (credits <= 10) {
                        $creditsDisplay.addClass('low-credits');
                    } else {
                        $creditsDisplay.removeClass('low-credits');
                    }
                    
                    // Log per debugging
                    console.log("Credits aggiornati:", credits);

                    // Update the last check date
                    $lastCheckDisplay.html('<span class="dashicons dashicons-clock"></span> Last update: ' + response.data.last_check);

                    // Show a confirmation toast
                    $('<div class="notice notice-success is-dismissible"><p>Credits updated successfully!</p></div>')
                        .insertAfter('.imgseo-credits-container')
                        .delay(3000)
                        .fadeOut(function() {
                            $(this).remove();
                        });

                    // Update status and messages based on remaining credits
                    $('.credits-warning, .credits-status').remove();

                    if (response.data.credits <= 0) {
                        // No credits remaining
                        $('.imgseo-credits-container').append(
                            '<div class="credits-warning error">' +
                            '<span class="dashicons dashicons-dismiss"></span> ' +
                            'You have no credits available! Purchase new credits to continue using the service.' +
                            '</div>'
                        );
                    } else if (response.data.credits <= 10) {
                        // Few credits remaining
                        $('.imgseo-credits-container').append(
                            '<div class="credits-warning warning">' +
                            '<span class="dashicons dashicons-warning"></span> ' +
                            'Your credits are running low. Consider purchasing additional credits.' +
                            '</div>'
                        );
                    } else {
                        // Sufficient credits
                        $('.imgseo-credits-container').append(
                            '<div class="credits-status success">' +
                            '<span class="dashicons dashicons-yes-alt"></span> ' +
                            'You have sufficient credits to generate alternative texts.' +
                            '</div>'
                        );
                    }
                } else {
                    // Show error
                    $('<div class="notice notice-error is-dismissible"><p>' + (response.data ? response.data.message : 'Error during credits update') + '</p></div>')
                        .insertAfter('.imgseo-credits-container')
                        .delay(3000)
                        .fadeOut(function() {
                            $(this).remove();
                        });
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX error:", error, xhr.responseText);

                // Handle connection error
                $('<div class="notice notice-error is-dismissible"><p>Connection error during credits update</p></div>')
                    .insertAfter('.imgseo-credits-container')
                    .delay(3000)
                    .fadeOut(function() {
                        $(this).remove();
                    });
            },
            complete: function() {
                // Re-enable the button
                $button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Refresh Credits');
            }
        });
    });

    // Verifica se è davvero necessario l'aggiornamento automatico
    // Lo facciamo solo se sono passate più di 4 ore dall'ultimo aggiornamento
    if ($('#api_key_status .status-message.success').length > 0) {
        var lastCheckText = $('#last_credits_check').text();
        var needsRefresh = true;
        
        // Verifica se contiene ore o giorni, indicando un aggiornamento vecchio
        if (lastCheckText.indexOf('hour') > -1 || lastCheckText.indexOf('day') > -1) {
            needsRefresh = true;
        } else if (lastCheckText.indexOf('minute') > -1) {
            // Estrai il numero di minuti
            var minutesMatch = lastCheckText.match(/(\d+)\s+minute/);
            if (minutesMatch && parseInt(minutesMatch[1]) < 60) {
                needsRefresh = false;
            }
        } else if (lastCheckText.indexOf('just now') > -1 || lastCheckText.indexOf('second') > -1) {
            needsRefresh = false;
        }
        
        if (needsRefresh) {
            console.log('ImgSEO: Aggiornamento crediti necessario, ultimo aggiornamento: ' + lastCheckText);
            setTimeout(function() {
                $('#refresh_credits').trigger('click');
            }, 1000);
        } else {
            console.log('ImgSEO: Aggiornamento crediti non necessario, ultimo aggiornamento: ' + lastCheckText);
        }
    }
});
