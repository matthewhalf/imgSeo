// handle tabs
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('#imgseo-settings .nav-tab');
    const tabContents = document.querySelectorAll('#imgseo-settings .imgseo-tab');

    tabs.forEach(tab => {
        tab.addEventListener('click', function(event) {
            // event.preventDefault(); 

            // Remove active class from all tabs and hide all tab contents
            tabs.forEach(t => {
                t.classList.remove('active');
            });
            tabContents.forEach(content => {
                content.classList.remove('active');
            });

            // Add active class to the clicked tab and show the corresponding content
            this.classList.add('active');
            const targetId = this.getAttribute('data-target');
            document.getElementById(targetId).classList.add('active');
        });
    });
});

// main js
jQuery(document).ready(function ($) { 
    // syncing app key
    synchAppKey()

    // when media modal gets opened
    jQuery(document).on('click', 'ul.attachments li.attachment', function () {
        let modal = jQuery(this)
        const altTextEl = jQuery('[data-setting="alt"] textarea')

        // return if there's no id
        if (!modal.attr('data-id')) return
        var attachmentId = parseInt(modal.attr('data-id'), 10)
        if (!attachmentId) return;
        generateAltTextButton(attachmentId, altTextEl, 'modal')
    })

    // alt text button
    const altTextEl = jQuery('#attachment_alt')
    if (altTextEl.length) {
        var attachmentId = getQueryParam('post');
        if (!attachmentId) return false
        attachmentId = parseInt(attachmentId, 10);

        generateAltTextButton(attachmentId, altTextEl, 'post')
    }

    jQuery('[data-bulk-generate-all-images]').on('click', function (event) {
        let isChecked = jQuery(this).is(':checked')
        let currentUrl = window.location.href

        if (isChecked) {
            if (currentUrl.indexOf('?') > -1) {
                currentUrl += '&mode=all'
            } else {
                currentUrl += '?mode=all'
            }
        } else {
            let urlObj = new URL(currentUrl)
            urlObj.searchParams.delete('mode')
            currentUrl = urlObj.toString()
        }

        window.location.href = currentUrl
    })

    // bulk generate
    jQuery('[data-start-bulk-generate]').on('click', function (event) {
        event.preventDefault();

        const $button = jQuery(this);
        if ($button.hasClass('disabled')) return;

        $button.find('p').hide();
        const loader = document.createElement('span');
        loader.classList.add('imgseo-loader');
        loader.style.marginLeft = '10px';
        loader.style.display = 'inline-block';
        $button.append(loader);

        // Disable the button
        $button.addClass('disabled');

        // Bulk generate all is selected
        window.imgseo.all_images = jQuery('[data-bulk-generate-all-images]').is(':checked');
        window.imgseo.bulk_generate_progress_wrapper = jQuery('[data-bulk-generate-progress-bar-wrapper]');
        window.imgseo.bulk_generate_progress_bar = jQuery('[data-bulk-generate-progress-bar]');
        window.imgseo.progressTotal = 0;
        window.imgseo.progressMaximum = window.imgseo.bulk_generate_progress_bar.data('max-images');
        window.imgseo.last_post_id = 0;

        window.imgseo.bulk_generate_progress_wrapper.removeClass('d-none');

        bulkGenerateAJAX(5, function () {
            loader.remove(); // Remove the loader when done
            $button.removeClass('disabled').text('Start Now');
        });
    });

    // file renamer
    jQuery('[data-post-renamer]').on('click', async function (event) {
        event.preventDefault();

        var button = jQuery(this);
        const postId = button.data('id');

        let generatebuttons = jQuery('.generate-filename')
        generatebuttons.addClass('generating').prop('disabled', true);
        
        let fileRenameNotice = document.createElement('p');
        button.after(fileRenameNotice);

        fileRenameNotice.innerText = 'Renaming file.';

        if (!postId) {
            fileRenameNotice.innerText = 'This is not a valid post.';
            return;
        }

        try {
            const response = await fileRename(postId);

            if (response.status) {
                fileRenameNotice.innerText = 'Renaming successful!';
                window.location.reload();
            } else {
                fileRenameNotice.innerText = 'Unable to rename file.';
            }
        } catch (error) {
            fileRenameNotice.innerText = 'An error occurred since the image could not be converted.';
            console.error(error);
        }

        generatebuttons.removeClass('generating').prop('disabled', false);
    })

    // file renamer save
    jQuery('[data-save-renamer]').on('click', async function (event) {
        event.preventDefault();
        var button = jQuery(this);
        const postId = button.data('id');
        const newFileName = jQuery(`#newFileName-${postId}`).val();
        button.addClass('saving').prop('disabled', true);
        const updateNotice = this.nextElementSibling;

        let fileRenameNotice = document.createElement('p');
        button.after(fileRenameNotice);

        fileRenameNotice.innerText = 'Renaming file.';

        if (!postId) {
            updateNotice.innerText = __('This is not a valid post.', 'imgseo-net');
            return;
        }
        jQuery.ajax({
            type: 'post',
            dataType: 'json',
            data: {
                action: 'imgseo_image_name_save',
                security: imgseo.security_single_generate,
                attachment_id: postId,
                new_file_name: newFileName,
            },
            url: imgseo.ajax_url,
            success: function (response) {
                fileRenameNotice.innerText = 'Renaming successful!';
                window.location.reload();
            },
            error: function (response) {
                button.remove('saving').prop('disabled', false);
                fileRenameNotice.innerText = 'Unable to rename file.';
                return (new Error('AJAX request failed'));
            }
        });    
    });

    // reset app key
    jQuery('#imgseo-reset-key').on('click', async function (event) {
        event.preventDefault()
        clearAppKey()
    })
})

function synchAppKey()
{
    jQuery('#imgseo-sync-key').on('click', function () {
        jQuery.ajax({
            url: imgseo.ajax_url,
            type: 'POST',
            data: {
                action: 'sync_app_key',
                nonce: imgseo.nonce,
                app_key: jQuery('#imgseo-api-key').val(), //fix me, pick real app key
            },
            success: function (res) {
                console.log('the response is, ', res)
                if (res.status !== false) {
                    toastrSuccessNotification(
                        "Success",
                        "Your api key has been saved succesfully!"
                    )
                    window.location.reload()
                } else {
                    toastrErrorNotification(
                        "Error",
                        "That did not seem correct, please check and try again!"
                    )
                }
            },
            error: function (err) {
                console.log('an error has occured', err)
            }
        })
    })
}

function clearAppKey()
{
    jQuery.ajax({
        url: imgseo.ajax_url,
        type: 'POST',
        data: {
            action: 'reset_app_key',
            nonce: imgseo.nonce
        },
        success: function (res) {
            toastrSuccessNotification(
                "Success",
                "Your api key has been reset succesfully!"
            )
            window.location.reload()
        },
        error: function (err) {
            console.log('an error has occured', err)
        }
    })
}

function generateAltTextButton(attachmentId, altTextEl, type)
{
    // determine the position to insert at
    const insertPosition = type === 'modal' ? jQuery('[data-setting="alt"]') : altTextEl

    if (insertPosition.length) {
        if (jQuery('#imgseo-generate-alt-button').length === 0) {
            insertPosition.after('<div id="imgseo-button-wrapper"><button id="imgseo-generate-alt-button" class="imgseo-button"><p>Update Alt Text</p></button></div>')
        }
    }

    var updateAltButton = jQuery('#imgseo-generate-alt-button')

    var altTextUpdateNotice = document.createElement('span')
    altTextUpdateNotice.classList.add('imgseo-alttext-update-notice')
    altTextUpdateNotice.style.color = 'green'
    updateAltButton.after(altTextUpdateNotice)

    // if modal, add some inline css
    if (type === 'modal') {
        updateAltButton.css({
            'margin-left': '35%'
        })
        altTextUpdateNotice.style.margin = '0 0 0 35%'
    }

    var loader = document.createElement('span')
    loader.classList.add('imgseo-loader')
    loader.style.display = 'none'
    updateAltButton.append(loader);

    const updateAltButtonText = jQuery('#imgseo-generate-alt-button p')

    updateAltButton.on('click', async function (event) {
        event.preventDefault()

        loader.style.display = 'block';
        updateAltButtonText.text('')
        updateAltButton.addClass('disabled')

        // bail early if api key is missing
        if (!imgseo.has_app_key) {
            window.location.href = imgseo.settings_url + "&missing_api_key=1"
        }

        try {
            const response = await singleGenerateAltText(attachmentId)
            if (response.status) {
                altTextEl.text(response.alt_text)
                altTextUpdateNotice.style.color = 'green'
                altTextUpdateNotice.innerText = 'Alt text updated!'
                window.location.reload()
            } else {
                altTextUpdateNotice.style.color = 'red'
                altTextUpdateNotice.innerText = 'Failed to generate alt text!'
            }
        } catch (error) {
            altTextUpdateNotice.style.color = 'red'
            altTextUpdateNotice.innerText = 'Failed to generate alt text!'
            console.log(error)
        } finally {
            loader.style.display = 'none'
            updateAltButtonText.text('Update Alt Text')
            updateAltButton.removeClass('disabled')

            // clear the notice
            setTimeout(() => {
                altTextUpdateNotice.innerText = ''
            }, 3000)
        }
    })
}

function singleGenerateAltText(attachmentId)
{
    // bail eaarly if no attachment id
    if (!attachmentId) {
        return Promise.reject(new Error('Attachment is missing'))
    }

    return new Promise((resolve, reject) => {
        jQuery.ajax({
            type: 'post',
            dataType: 'json',
            data: {
                action: 'single_alttext_generate',
                security: imgseo.security_single_alttext_generate,
                attachment_id: attachmentId
            },
            url: imgseo.ajax_url,
            success: function (response) {
                resolve(response)
            },
            error: function (error) {
                console.log('an error occured, ', error)
                reject(new Error('Request failed'))
            }
        })
    })
}

function bulkGenerateAJAX(batchSize = 5, callback) { 
    let processedCount = 0;
    let totalCount = window.imgseo.progressTotal;
    let maxImages = window.imgseo.progressMaximum;

    function showNotification(message, type = 'success') {
        let color = type === 'error' ? 'red' : 'green';
        let notification = window.imgseo.bulk_generate_progress_wrapper.next('.bulk-generate-notification');

        if (notification.length === 0) {
            notification = jQuery('<p class="bulk-generate-notification"></p>');
            window.imgseo.bulk_generate_progress_wrapper.after(notification);
        }

        notification.css('color', color).text(message);
    }

    function processBatch() {
        jQuery.ajax({
            type: 'post',
            dataType: 'json',
            data: {
                action: 'bulk_alttext_generate',
                security: imgseo.security_bulk_alttext_generate,
                all_images: window.imgseo.all_images,
                last_post_id: window.imgseo.last_post_id,
                batch_size: batchSize
            },
            url: imgseo.ajax_url,
            success: function (response) {
                processedCount += response.processed_count;
                window.imgseo.progressTotal = processedCount;

                const percentageProgress = (processedCount * 100) / maxImages;
                window.imgseo.bulk_generate_progress_bar.css('width', percentageProgress + '%');
                console.log(`Processed ${processedCount} of ${maxImages} images (${percentageProgress.toFixed(2)}%)`);

                showNotification(`Processed ${processedCount} of ${maxImages} images (${percentageProgress.toFixed(2)}%)`);

                if (response.loop_again) {
                    window.imgseo.last_post_id = response.last_post_id;
                    processBatch();
                } else {
                    window.imgseo.bulk_generate_progress_bar.css('width', '100%');
                    window.imgseo.bulk_generate_progress_wrapper.addClass('d-none');
                    showNotification('Bulk generation completed successfully.');
                    console.log('Bulk generation completed.');
                    if (typeof callback === 'function') callback(); // Call the callback when complete
                }
            },
            error: function (error) {
                console.error(error);
                showNotification('An error occurred. Retrying...', 'error');
                processBatch(); // Retry or continue with the next batch
            }
        });
    }

    processBatch();
}

function toastrSuccessNotification(title, message)
{
    toastr.options = {
        "closeButton": true,
        "debug": false,
        "newestOnTop": true,
        "progressBar": false,
        "positionClass": "toast-top-right",
        "preventDuplicates": true,
        "onclick": null,
        "showDuration": "700",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
    }

    return toastr["success"](message, title);
}

function toastrErrorNotification(title, message)
{
    toastr.options = {
        "closeButton": true,
        "debug": false,
        "newestOnTop": true,
        "progressBar": false,
        "positionClass": "toast-top-right",
        "preventDuplicates": true,
        "onclick": null,
        "showDuration": "700",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
    }

    return toastr["error"](message, title);
}

function getQueryParam(name) {
    name = name.replace(/[[]/, '\\[').replace(/[\]]/, '\\]');
    let regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    let paramSearch = regex.exec(window.location.search);

    return paramSearch === null ? '' : decodeURIComponent(paramSearch[1].replace(/\+/g, ' '));
}

function fileRename(attachmentId)
{
    if (!attachmentId) {
        return Promise.reject(new Error('Attachment is missing'))
    }

    return new Promise((resolve, reject) => {
        jQuery.ajax({
            type: 'post',
            dataType: 'json',
            data: {
                action: 'file_rename',
                security: imgseo.security_single_alttext_generate,
                attachment_id: attachmentId
            },
            url: imgseo.ajax_url,
            success: function (response) {
                resolve(response)
            },
            error: function (error) {
                console.log('an error occured, ', error)
                reject(new Error('Request failed'))
            }
        })
    })
}