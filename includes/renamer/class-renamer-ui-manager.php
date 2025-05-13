<?php
/**
 * Class Renamer_UI_Manager
 * Manages the UI for the Image Renamer functionality
 */
class Renamer_UI_Manager {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Initialize the class and set its properties.
     */
    private function __construct() {
        // Aggiungi gli script e gli stili necessari
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Get the singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Il metodo add_admin_menu è stato rimosso e spostato in ImgSEO_Menu_Manager
    
    /**
     * Enqueue scripts and styles for the renamer UI
     */
    public function enqueue_scripts($hook) {
        // Carica script e stili solo nella pagina del renamer
        if ($hook !== 'imgseo_page_imgseo-renamer') {
            return;
        }
        
        // Registra lo stile principale
        wp_enqueue_style('wp-jquery-ui-dialog');
        wp_enqueue_script('jquery-ui-dialog');
        
        // Media uploader
        wp_enqueue_media();
    }
    
    /**
     * Render the renamer admin page
     */
    public function render_renamer_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Get active tab
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'rename';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="imgseo-tabs">
                <div class="nav-tab-wrapper">
                    <a href="?page=imgseo-renamer&tab=rename" class="nav-tab <?php echo $active_tab == 'rename' ? 'nav-tab-active' : ''; ?>"><?php _e('Rename Images', IMGSEO_TEXT_DOMAIN); ?></a>
                    <a href="?page=imgseo-renamer&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>"><?php _e('Logs', IMGSEO_TEXT_DOMAIN); ?></a>
                    <a href="?page=imgseo-renamer&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>"><?php _e('Renamer Settings', IMGSEO_TEXT_DOMAIN); ?></a>
                </div>
                
                <div id="tab-rename" class="tab-content <?php echo $active_tab == 'rename' ? 'active' : ''; ?>">
                    <?php $this->render_rename_tab(); ?>
                </div>
                
                <div id="tab-logs" class="tab-content <?php echo $active_tab == 'logs' ? 'active' : ''; ?>">
                    <?php $this->render_logs_tab(); ?>
                </div>
                
                <div id="tab-settings" class="tab-content <?php echo $active_tab == 'settings' ? 'active' : ''; ?>">
                    <form method="post" action="options.php">
                        <input type="hidden" name="imgseo_active_tab" value="settings">
                        <?php
                        settings_fields('imgseo_renamer_settings');
                        do_settings_sections('imgseo_renamer_settings');
                        submit_button();
                        ?>
                    </form>
                </div>
            </div>
        </div>
        
        <style>
            /* Stili comuni per tutte le tab */
            .tab-content {
                display: none;
                padding: 20px 0;
            }
            .tab-content.active {
                display: block;
            }
            
            /* Stili specifici per immagini */
            .imgseo-image-list {
                margin-top: 15px;
            }
            .imgseo-image-list .image-column {
                width: 110px;
            }
            .imgseo-image-list .filename-column {
                width: auto;
            }
            .imgseo-image-list .actions-column {
                width: 240px;
                text-align: right;
            }
            .imgseo-image-list .thumbnail {
                max-width: 80px;
                max-height: 80px;
                margin: 5px;
            }
            .imgseo-filename-input {
                width: 100%;
            }
            .generate-ai-button {
                margin-top: 5px !important;
            }
            .imgseo-image-row {
                background-color: #f9f9f9;
            }
            .imgseo-image-row:nth-child(odd) {
                background-color: #ffffff;
            }
            .imgseo-image-list tr.success-highlight {
                background-color: #edfaef !important;
                transition: background-color 0.5s ease;
            }
            
            /* Log status styles */
            .imgseo-log-status {
                display: inline-block;
                padding: 3px 6px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 500;
                text-transform: capitalize;
            }
            
            .imgseo-log-status.success {
                background-color: #edfaef;
                color: #0a7a2c;
            }
            
            .imgseo-log-status.error {
                background-color: #fef1f1;
                color: #c32727;
            }
            
            .imgseo-log-status.restore {
                background-color: #e8f4fd;
                color: #0073aa;
            }
            
            /* Restore button styling */
            .restore-button {
                margin-left: 8px !important;
                vertical-align: middle !important;
            }
            
            /* Stili per la tab di Batch Rename */
            .imgseo-batch-options {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 15px;
                margin-bottom: 20px;
                border-radius: 3px;
            }
            
            .imgseo-patterns-help {
                background: #f8f9fa;
                padding: 10px 15px;
                border-left: 4px solid #007cba;
                margin: 10px 0;
            }
            
            .imgseo-patterns-help ul {
                margin-left: 20px;
            }
            
            .imgseo-batch-preview {
                margin-top: 25px;
            }
            
            .imgseo-selected-images {
                display: flex;
                flex-wrap: wrap;
                margin: 15px 0;
            }
            
            .imgseo-selected-image {
                margin: 0 10px 10px 0;
                background: #fff;
                border: 1px solid #ddd;
                padding: 10px;
                border-radius: 3px;
                width: 150px;
                text-align: center;
                position: relative;
            }
            
            .imgseo-selected-image img {
                max-width: 100%;
                height: auto;
                max-height: 100px;
            }
            
            .imgseo-selected-image .filename {
                margin-top: 5px;
                font-size: 12px;
                word-break: break-word;
            }
            
            .imgseo-selected-image .new-filename {
                font-weight: bold;
                color: #007cba;
            }
            
            .imgseo-remove-image {
                position: absolute;
                top: 5px;
                right: 5px;
                background: #f1f1f1;
                border-radius: 50%;
                width: 20px;
                height: 20px;
                text-align: center;
                line-height: 18px;
                cursor: pointer;
                color: #999;
            }
            
            .imgseo-remove-image:hover {
                background: #e5e5e5;
                color: #666;
            }
            
            .imgseo-batch-actions {
                margin-top: 15px;
            }
            
            .imgseo-batch-results {
                margin-top: 25px;
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 15px;
                border-radius: 3px;
            }
            
            .imgseo-batch-result-item {
                margin-bottom: 10px;
                padding-bottom: 10px;
                border-bottom: 1px solid #f1f1f1;
            }
            
            .imgseo-batch-result-item:last-child {
                border-bottom: none;
            }
            
            .imgseo-batch-result-item.success {
                color: #0a7a2c;
            }
            
            .imgseo-batch-result-item.error {
                color: #c32727;
            }
            
            .imgseo-batch-result-item.skipped {
                color: #856404;
            }
        </style>
        <?php
    }
    
    /**
     * Render the rename tab content
     */
    private function render_rename_tab() {
        // Get current page number
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20; // Number of images per page
        
        // Query for all image attachments
        $args = array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );
        
        $images_query = new WP_Query($args);
        $total_images = $images_query->found_posts;
        $total_pages = ceil($total_images / $per_page);
        ?>
        <div class="imgseo-renamer-container">
            <div class="imgseo-renamer-intro">
                <h2><?php _e('Image Renamer Tool', IMGSEO_TEXT_DOMAIN); ?></h2>
                <p><?php _e('Safely rename your WordPress media files while maintaining all references to prevent broken links and 404 errors.', IMGSEO_TEXT_DOMAIN); ?></p>
            </div>
            
            <div id="imgseo-renamer-result" class="imgseo-renamer-result hidden">
                <div id="imgseo-renamer-success" class="notice notice-success hidden">
                    <p>
                        <span class="dashicons dashicons-yes-alt"></span> 
                        <?php _e('Image successfully renamed!', IMGSEO_TEXT_DOMAIN); ?>
                    </p>
                    <p id="imgseo-renamer-success-details"></p>
                </div>
                
                <div id="imgseo-renamer-error" class="notice notice-error hidden">
                    <p>
                        <span class="dashicons dashicons-warning"></span>
                        <?php _e('Error renaming image:', IMGSEO_TEXT_DOMAIN); ?> 
                        <span id="imgseo-renamer-error-message"></span>
                    </p>
                </div>
            </div>
            
            <?php if ($images_query->have_posts()) : ?>
                <div id="imgseo-image-list-container">
                    <h3><?php _e('Media Library Images', IMGSEO_TEXT_DOMAIN); ?></h3>
                    <p class="description"><?php _e('Edit filenames without the extension. Special characters will be converted to hyphens.', IMGSEO_TEXT_DOMAIN); ?></p>
                    
                    <div class="tablenav top">
                        <div class="tablenav-pages">
                            <span class="displaying-num">
                                <?php printf(_n('%s item', '%s items', $total_images, IMGSEO_TEXT_DOMAIN), number_format_i18n($total_images)); ?>
                            </span>
                            <?php if ($total_pages > 1) : ?>
                                <span class="pagination-links">
                                    <?php 
                                    // First page link
                                    if ($paged > 1) {
                                        printf('<a class="first-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">«</span></a>',
                                            esc_url(add_query_arg(array('paged' => 1, 'tab' => 'rename'), admin_url('admin.php?page=imgseo-renamer'))),
                                            __('First page', IMGSEO_TEXT_DOMAIN)
                                        );
                                    } else {
                                        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>';
                                    }
                                    
                                    // Previous page link
                                    if ($paged > 1) {
                                        printf('<a class="prev-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">‹</span></a>',
                                            esc_url(add_query_arg(array('paged' => max(1, $paged - 1), 'tab' => 'rename'), admin_url('admin.php?page=imgseo-renamer'))),
                                            __('Previous page', IMGSEO_TEXT_DOMAIN)
                                        );
                                    } else {
                                        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>';
                                    }
                                    
                                    // Current page number
                                    printf('<span class="paging-input">%s <span class="tablenav-paging-text">%s <span class="total-pages">%s</span></span></span>',
                                        $paged,
                                        __('of', IMGSEO_TEXT_DOMAIN),
                                        number_format_i18n($total_pages)
                                    );
                                    
                                    // Next page link
                                    if ($paged < $total_pages) {
                                        printf('<a class="next-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">›</span></a>',
                                            esc_url(add_query_arg(array('paged' => min($total_pages, $paged + 1), 'tab' => 'rename'), admin_url('admin.php?page=imgseo-renamer'))),
                                            __('Next page', IMGSEO_TEXT_DOMAIN)
                                        );
                                    } else {
                                        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>';
                                    }
                                    
                                    // Last page link
                                    if ($paged < $total_pages) {
                                        printf('<a class="last-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">»</span></a>',
                                            esc_url(add_query_arg(array('paged' => $total_pages, 'tab' => 'rename'), admin_url('admin.php?page=imgseo-renamer'))),
                                            __('Last page', IMGSEO_TEXT_DOMAIN)
                                        );
                                    } else {
                                        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>';
                                    }
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <table class="widefat striped imgseo-image-list">
                        <thead>
                            <tr>
                                <th class="image-column"><?php _e('Image', IMGSEO_TEXT_DOMAIN); ?></th>
                                <th class="filename-column"><?php _e('Filename', IMGSEO_TEXT_DOMAIN); ?></th>
                                <th class="actions-column"><?php _e('Actions', IMGSEO_TEXT_DOMAIN); ?></th>
                            </tr>
                        </thead>
                        <tbody id="imgseo-image-list-body">
                            <?php
                            while ($images_query->have_posts()) {
                                $images_query->the_post();
                                $attachment_id = get_the_ID();
                                $attachment_url = wp_get_attachment_url($attachment_id);
                                $attachment_thumbnail = wp_get_attachment_image_src($attachment_id, 'thumbnail')[0];
                                $filename = basename($attachment_url);
                                $extension = pathinfo($filename, PATHINFO_EXTENSION);
                                $filename_without_ext = pathinfo($filename, PATHINFO_FILENAME);
                                ?>
                                <tr id="imgseo-image-row-<?php echo esc_attr($attachment_id); ?>" class="imgseo-image-row">
                    <td class="image-column">
                        <img class="thumbnail" src="<?php echo esc_url($attachment_thumbnail); ?>" alt="<?php echo esc_attr($filename); ?>">
                        <div class="file-info-container">
                            <div class="filename-display" id="filename-display-<?php echo esc_attr($attachment_id); ?>">
                                <?php echo esc_html($filename); ?>
                            </div>
                            <div class="original-path" id="original-path-<?php echo esc_attr($attachment_id); ?>">
                                <?php 
                                $upload_dir = wp_upload_dir();
                                $file_path = str_replace($upload_dir['baseurl'], '', $attachment_url);
                                $path_parts = pathinfo($file_path);
                                $dir_name = $path_parts['dirname'];
                                echo esc_html(trim($dir_name, '/')); 
                                ?>
                            </div>
                        </div>
                    </td>
                                    <td class="filename-column">
                                        <input type="text" class="imgseo-filename-input" id="imgseo-filename-<?php echo esc_attr($attachment_id); ?>" value="<?php echo esc_attr($filename_without_ext); ?>">
                                        <br>
                                        <button type="button" class="button generate-ai-button" data-id="<?php echo esc_attr($attachment_id); ?>">
                                            <span class="dashicons dashicons-update"></span>
                                            <?php _e('Generate Filename (AI)', IMGSEO_TEXT_DOMAIN); ?>
                                        </button>
                                        <span class="ai-result-message" id="ai-result-<?php echo esc_attr($attachment_id); ?>"></span>
                                    </td>
                                    <td class="actions-column">
                                        <button type="button" class="button button-primary save-filename-button" data-id="<?php echo esc_attr($attachment_id); ?>" data-extension="<?php echo esc_attr('.' . $extension); ?>">
                                            <?php _e('Save Custom Filename', IMGSEO_TEXT_DOMAIN); ?>
                                        </button>
                                        <span class="spinner" style="float: none; margin: 0 0 0 5px;"></span>
                                    </td>
                                </tr>
                                <?php
                            }
                            wp_reset_postdata();
                            ?>
                        </tbody>
                    </table>
                    
                    <?php if ($total_pages > 1) : ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <span class="displaying-num">
                                <?php printf(_n('%s item', '%s items', $total_images, IMGSEO_TEXT_DOMAIN), number_format_i18n($total_images)); ?>
                            </span>
                            <span class="pagination-links">
                                <?php 
                                // First page link
                                if ($paged > 1) {
                                    printf('<a class="first-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">«</span></a>',
                                        esc_url(add_query_arg(array('paged' => 1, 'tab' => 'rename'), admin_url('admin.php?page=imgseo-renamer'))),
                                        __('First page', IMGSEO_TEXT_DOMAIN)
                                    );
                                } else {
                                    echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>';
                                }
                                
                                // Previous page link
                                if ($paged > 1) {
                                    printf('<a class="prev-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">‹</span></a>',
                                        esc_url(add_query_arg(array('paged' => max(1, $paged - 1), 'tab' => 'rename'), admin_url('admin.php?page=imgseo-renamer'))),
                                        __('Previous page', IMGSEO_TEXT_DOMAIN)
                                    );
                                } else {
                                    echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>';
                                }
                                
                                // Current page number
                                printf('<span class="paging-input">%s <span class="tablenav-paging-text">%s <span class="total-pages">%s</span></span></span>',
                                    $paged,
                                    __('of', IMGSEO_TEXT_DOMAIN),
                                    number_format_i18n($total_pages)
                                );
                                
                                // Next page link
                                if ($paged < $total_pages) {
                                    printf('<a class="next-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">›</span></a>',
                                        esc_url(add_query_arg(array('paged' => min($total_pages, $paged + 1), 'tab' => 'rename'), admin_url('admin.php?page=imgseo-renamer'))),
                                        __('Next page', IMGSEO_TEXT_DOMAIN)
                                    );
                                } else {
                                    echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>';
                                }
                                
                                // Last page link
                                if ($paged < $total_pages) {
                                    printf('<a class="last-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">»</span></a>',
                                        esc_url(add_query_arg(array('paged' => $total_pages, 'tab' => 'rename'), admin_url('admin.php?page=imgseo-renamer'))),
                                        __('Last page', IMGSEO_TEXT_DOMAIN)
                                    );
                                } else {
                                    echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <div class="notice notice-info">
                    <p><?php _e('No images found in your media library.', IMGSEO_TEXT_DOMAIN); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
            jQuery(document).ready(function($) {
                // Gestione click sul pulsante Generate Filename (AI)
                $('.generate-ai-button').on('click', function() {
                    var $button = $(this);
                    var attachmentId = $button.data('id');
                    var $row = $button.closest('tr');
                    var $spinner = $row.find('.spinner');
                    var $input = $row.find('.imgseo-filename-input');
                    var $resultMessage = $('#ai-result-' + attachmentId);
                    
                    // Disabilita il pulsante e mostra lo spinner
                    $button.prop('disabled', true);
                    $spinner.addClass('is-active');
                    $resultMessage.html('').removeClass('error success');
                    
                    // Chiamata AJAX
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'imgseo_generate_ai_filename',
                            attachment_id: attachmentId,
                            source: 'ai_generator', // Aggiungi il parametro source per ottimizzare la velocità
                            security: '<?php echo wp_create_nonce('imgseo_renamer_nonce'); ?>'
                        },
                        success: function(response) {
                            $spinner.removeClass('is-active');
                            $button.prop('disabled', false);
                            
                            if (response.success) {
                                // Inserisci il nome file generato nell'input
                                $input.val(response.data.filename);
                                // Evidenzia l'input per attirare l'attenzione
                                $input.effect('highlight', {}, 1000);
                                $resultMessage.html('✓ ' + '<?php _e('Name generated successfully', IMGSEO_TEXT_DOMAIN); ?>').addClass('success');
                            } else {
                                // Mostra errore
                                $resultMessage.html('⚠ ' + (response.data.message || '<?php _e('Error generating filename', IMGSEO_TEXT_DOMAIN); ?>')).addClass('error');
                            }
                        },
                        error: function() {
                            $spinner.removeClass('is-active');
                            $button.prop('disabled', false);
                            $resultMessage.html('⚠ ' + '<?php _e('Server error', IMGSEO_TEXT_DOMAIN); ?>').addClass('error');
                        }
                    });
                });
                
                // Bind save button click for all images
                $('.save-filename-button').on('click', function() {
                    var attachmentId = $(this).data('id');
                    var extension = $(this).data('extension');
                    var newFilename = $('#imgseo-filename-' + attachmentId).val().trim();
                    
                    if (!newFilename) {
                        alert('<?php _e('Please enter a filename.', IMGSEO_TEXT_DOMAIN); ?>');
                        return;
                    }
                    
                    // Show spinner
                    var row = $('#imgseo-image-row-' + attachmentId);
                    var spinner = row.find('.spinner');
                    var saveButton = row.find('.save-filename-button');
                    
                    spinner.addClass('is-active');
                    saveButton.prop('disabled', true);
                    
                    // Hide previous results
                    $('#imgseo-renamer-result').addClass('hidden');
                    $('#imgseo-renamer-success').addClass('hidden');
                    $('#imgseo-renamer-error').addClass('hidden');
                    
                    // Send AJAX request
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'imgseo_rename_image',
                            attachment_id: attachmentId,
                            new_filename: newFilename,
                            security: '<?php echo wp_create_nonce('imgseo_renamer_nonce'); ?>'
                        },
                        success: function(response) {
                            spinner.removeClass('is-active');
                            saveButton.prop('disabled', false);
                            
                            if (response.success) {
                                // Aggiorna l'immagine con il nuovo URL aggiungendo un parametro per forzare il reload
                                var newUrl = response.data.new_url + '?v=' + (new Date().getTime());
                                row.find('.thumbnail').attr('src', newUrl);
                                
                                // Aggiorna anche il nome file visualizzato nell'input
                                var filenameWithoutExt = response.data.new_filename.split('.').slice(0, -1).join('.');
                                var $input = $('#imgseo-filename-' + attachmentId);
                                $input.val(filenameWithoutExt);
                                $input.effect('highlight', {color: '#c7f3c8'}, 2000);
                                
                                // Aggiorna l'attributo alt dell'immagine
                                row.find('.thumbnail').attr('alt', response.data.new_filename);
                                
                                // Aggiorna il testo del nome file sotto l'immagine
                                var $filenameDisplay = $('#filename-display-' + attachmentId);
                                $filenameDisplay.text(response.data.new_filename);
                                $filenameDisplay.css('background-color', '#c7f3c8');
                                setTimeout(function() {
                                    $filenameDisplay.css('background-color', '');
                                }, 2000);
                                
                                // Il percorso rimane lo stesso, aggiorniamo solo l'aspetto
                                var $originalPath = $('#original-path-' + attachmentId);
                                if ($originalPath.length) {
                                    $originalPath.css('background-color', '#c7f3c8');
                                    setTimeout(function() {
                                        $originalPath.css('background-color', '');
                                    }, 2000);
                                }
                                
                                // Highlight the row briefly to indicate success
                                row.addClass('success-highlight');
                                setTimeout(function() {
                                    row.removeClass('success-highlight');
                                }, 2000);
                                
                                // Show success message
                                $('#imgseo-renamer-success-details').html('<?php _e('Renamed:', IMGSEO_TEXT_DOMAIN); ?> <strong>' + response.data.old_filename + '</strong> → <strong>' + response.data.new_filename + '</strong>');
                                $('#imgseo-renamer-success').removeClass('hidden');
                                $('#imgseo-renamer-result').removeClass('hidden');
                                
                                // Scroll to success message
                                $('html, body').animate({
                                    scrollTop: $('#imgseo-renamer-result').offset().top - 50
                                }, 500);
                            } else {
                                // Show error message
                                $('#imgseo-renamer-error-message').text(response.data.message);
                                $('#imgseo-renamer-error').removeClass('hidden');
                                $('#imgseo-renamer-result').removeClass('hidden');
                                
                                // Scroll to error message
                                $('html, body').animate({
                                    scrollTop: $('#imgseo-renamer-result').offset().top - 50
                                }, 500);
                            }
                        },
                        error: function(xhr, status, error) {
                            spinner.removeClass('is-active');
                            saveButton.prop('disabled', false);
                            
                            $('#imgseo-renamer-error-message').text('<?php _e('Server error. Please try again.', IMGSEO_TEXT_DOMAIN); ?>');
                            $('#imgseo-renamer-error').removeClass('hidden');
                            $('#imgseo-renamer-result').removeClass('hidden');
                            
                            // Scroll to error message
                            $('html, body').animate({
                                scrollTop: $('#imgseo-renamer-result').offset().top - 50
                            }, 500);
                        }
                    });
                });
            });
        </script>
        
        <style>
            .ai-result-message {
                display: inline-block;
                margin-left: 10px;
                font-size: 12px;
                padding: 3px 6px;
            }
            .ai-result-message.success {
                color: green;
            }
            .ai-result-message.error {
                color: red;
            }
            .file-info-container {
                margin-top: 5px;
                width: 100%;
            }
            .filename-display {
                font-size: 11px;
                color: #666;
                text-align: center;
                word-break: break-all;
                font-weight: bold;
            }
            .original-path {
                font-size: 10px;
                color: #888;
                margin-top: 3px;
                text-align: center;
                word-break: break-all;
                border-top: 1px dotted #ddd;
                padding-top: 3px;
            }
        </style>
        <?php
    }
    
    /**
     * Render the batch rename tab content
     */
    private function render_batch_tab() {
        // Ottieni le impostazioni salvate
        $settings_manager = Renamer_Settings_Manager::get_instance();
        $pattern_template = $settings_manager->get_setting('pattern_template', '{post_title}-{numero}');
        $remove_accents = $settings_manager->is_enabled('remove_accents', true);
        $lowercase = $settings_manager->is_enabled('lowercase', true);
        $handle_duplicates = $settings_manager->get_setting('handle_duplicates', 'increment');
        ?>
        <div class="imgseo-batch-renamer">
            <div class="imgseo-batch-intro">
                <h2><?php _e('Batch Image Renamer', IMGSEO_TEXT_DOMAIN); ?></h2>
                <p><?php _e('Rename multiple images at once using patterns and rules. Select images from the media library and apply a common naming pattern to all of them.', IMGSEO_TEXT_DOMAIN); ?></p>
            </div>
            
            <div id="imgseo-batch-result" class="imgseo-batch-result hidden">
                <div id="imgseo-batch-success" class="notice notice-success hidden">
                    <p>
                        <span class="dashicons dashicons-yes-alt"></span> 
                        <span id="imgseo-batch-success-message"></span>
                    </p>
                </div>
                
                <div id="imgseo-batch-error" class="notice notice-error hidden">
                    <p>
                        <span class="dashicons dashicons-warning"></span>
                        <span id="imgseo-batch-error-message"></span>
                    </p>
                </div>
            </div>
            
            <div class="imgseo-batch-options">
                <h3><?php _e('Rename Options', IMGSEO_TEXT_DOMAIN); ?></h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Pattern', IMGSEO_TEXT_DOMAIN); ?></th>
                        <td>
                            <input type="text" id="imgseo-batch-pattern" class="regular-text" value="<?php echo esc_attr($pattern_template); ?>" />
                            <p class="description"><?php _e('Pattern to use for renaming files.', IMGSEO_TEXT_DOMAIN); ?></p>
                            
                            <div class="imgseo-patterns-help">
                                <h4><?php _e('Available Patterns:', IMGSEO_TEXT_DOMAIN); ?></h4>
                                <ul>
                                    <li><code>{post_title}</code> - <?php _e('Title of associated post/page', IMGSEO_TEXT_DOMAIN); ?></li>
                                    <li><code>{category}</code> - <?php _e('Main category of associated post/page', IMGSEO_TEXT_DOMAIN); ?></li>
                                    <li><code>{numero}</code> - <?php _e('Sequential number (001, 002, etc.)', IMGSEO_TEXT_DOMAIN); ?></li>
                                    <li><code>{originale}</code> - <?php _e('Original filename', IMGSEO_TEXT_DOMAIN); ?></li>
                                    <li><code>{data}</code> - <?php _e('Date in YYYYMMDD format', IMGSEO_TEXT_DOMAIN); ?></li>
                                    <li><code>{alt}</code> - <?php _e('Alt text of the image', IMGSEO_TEXT_DOMAIN); ?></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Sanitization', IMGSEO_TEXT_DOMAIN); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" id="imgseo-batch-lowercase" <?php checked($lowercase); ?> />
                                <?php _e('Convert to lowercase', IMGSEO_TEXT_DOMAIN); ?>
                            </label>
                            <br />
                            <label>
                                <input type="checkbox" id="imgseo-batch-remove-accents" <?php checked($remove_accents); ?> />
                                <?php _e('Remove accents', IMGSEO_TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Handle Duplicates', IMGSEO_TEXT_DOMAIN); ?></th>
                        <td>
                            <select id="imgseo-batch-handle-duplicates">
                                <option value="increment" <?php selected($handle_duplicates, 'increment'); ?>><?php _e('Add sequential number (file-1.jpg)', IMGSEO_TEXT_DOMAIN); ?></option>
                                <option value="timestamp" <?php selected($handle_duplicates, 'timestamp'); ?>><?php _e('Add timestamp (file-1679419361.jpg)', IMGSEO_TEXT_DOMAIN); ?></option>
                                <option value="fail" <?php selected($handle_duplicates, 'fail'); ?>><?php _e('Skip if duplicate exists', IMGSEO_TEXT_DOMAIN); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <div class="imgseo-batch-action">
                    <button type="button" id="imgseo-select-images" class="button button-primary">
                        <span class="dashicons dashicons-admin-media" style="margin-top: 3px;"></span>
                        <?php _e('Select Images', IMGSEO_TEXT_DOMAIN); ?>
                    </button>
                </div>
            </div>
            
            <div id="imgseo-batch-preview" class="imgseo-batch-preview hidden">
                <h3><?php _e('Selected Images', IMGSEO_TEXT_DOMAIN); ?> (<span id="imgseo-selected-count">0</span>)</h3>
                
                <button type="button" id="imgseo-preview-rename" class="button button-secondary">
                    <span class="dashicons dashicons-visibility" style="margin-top: 3px;"></span>
                    <?php _e('Preview New Filenames', IMGSEO_TEXT_DOMAIN); ?>
                </button>
                
                <div id="imgseo-selected-images" class="imgseo-selected-images"></div>
                
                <div class="imgseo-batch-actions">
                    <button type="button" id="imgseo-start-batch" class="button button-primary">
                        <span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
                        <?php _e('Rename Selected Images', IMGSEO_TEXT_DOMAIN); ?>
                    </button>
                    <span class="spinner" style="float: none; margin: 0 0 0 5px;"></span>
                </div>
            </div>
            
            <div id="imgseo-batch-results" class="imgseo-batch-results hidden">
                <h3><?php _e('Results', IMGSEO_TEXT_DOMAIN); ?></h3>
                <div id="imgseo-results-content"></div>
            </div>
        </div>
        
        <script>
            jQuery(document).ready(function($) {
                var selectedImages = [];
                
                // Apri il media uploader quando si clicca sul pulsante "Seleziona Immagini"
                $('#imgseo-select-images').on('click', function(e) {
                    e.preventDefault();
                    
                    var mediaUploader = wp.media({
                        title: '<?php _e('Select Images to Rename', IMGSEO_TEXT_DOMAIN); ?>',
                        button: {
                            text: '<?php _e('Select Images', IMGSEO_TEXT_DOMAIN); ?>'
                        },
                        multiple: true,
                        library: {
                            type: 'image'
                        }
                    });
                    
                    mediaUploader.on('select', function() {
                        var selection = mediaUploader.state().get('selection');
                        
                        // Reset selection
                        selectedImages = [];
                        
                        selection.each(function(attachment) {
                            // Get attachment details
                            var id = attachment.get('id');
                            var url = attachment.get('url');
                            var filename = url.split('/').pop();
                            var filenameWithoutExt = filename.split('.').slice(0, -1).join('.');
                            var extension = filename.split('.').pop();
                            var thumbnailUrl = attachment.get('sizes') && attachment.get('sizes').thumbnail ? 
                                               attachment.get('sizes').thumbnail.url : url;
                            
                            // Add to selected images array
                            selectedImages.push({
                                id: id,
                                url: url,
                                thumbnail: thumbnailUrl,
                                filename: filename,
                                filenameWithoutExt: filenameWithoutExt,
                                extension: extension,
                                title: attachment.get('title') || '',
                                alt: attachment.get('alt') || '',
                                newFilename: ''
                            });
                        });
                        
                        // Show selected images
                        renderSelectedImages();
                        
                        // Show preview section
                        $('#imgseo-batch-preview').removeClass('hidden');
                        $('#imgseo-selected-count').text(selectedImages.length);
                    });
                    
                    mediaUploader.open();
                });
                
                // Funzione per visualizzare le immagini selezionate
                function renderSelectedImages() {
                    var html = '';
                    
                    if (selectedImages.length === 0) {
                        $('#imgseo-selected-images').html('<p><?php _e('No images selected.', IMGSEO_TEXT_DOMAIN); ?></p>');
                        return;
                    }
                    
                    $.each(selectedImages, function(index, image) {
                        html += '<div class="imgseo-selected-image" data-id="' + image.id + '">';
                        html += '<div class="imgseo-remove-image" data-index="' + index + '">×</div>';
                        html += '<img src="' + image.thumbnail + '" alt="' + image.filename + '" />';
                        html += '<div class="filename">' + image.filename + '</div>';
                        
                        if (image.newFilename) {
                            html += '<div class="new-filename">' + image.newFilename + '.' + image.extension + '</div>';
                        }
                        
                        html += '</div>';
                    });
                    
                    $('#imgseo-selected-images').html(html);
                    
                    // Bind remove action
                    $('.imgseo-remove-image').on('click', function() {
                        var index = $(this).data('index');
                        selectedImages.splice(index, 1);
                        renderSelectedImages();
                        $('#imgseo-selected-count').text(selectedImages.length);
                        
                        if (selectedImages.length === 0) {
                            $('#imgseo-batch-preview').addClass('hidden');
                        }
                    });
                }
                
                // Anteprima dei nuovi nomi file
                $('#imgseo-preview-rename').on('click', function() {
                    var pattern = $('#imgseo-batch-pattern').val();
                    var lowercase = $('#imgseo-batch-lowercase').is(':checked');
                    var removeAccents = $('#imgseo-batch-remove-accents').is(':checked');
                    var handleDuplicates = $('#imgseo-batch-handle-duplicates').val();
                    
                    // Hide previous results
                    $('#imgseo-batch-result').addClass('hidden');
                    $('#imgseo-batch-success').addClass('hidden');
                    $('#imgseo-batch-error').addClass('hidden');
                    $('#imgseo-results-content').empty();
                    $('#imgseo-batch-results').addClass('hidden');
                    
                    if (selectedImages.length === 0) {
                        $('#imgseo-batch-error-message').text('<?php _e('No images selected.', IMGSEO_TEXT_DOMAIN); ?>');
                        $('#imgseo-batch-error').removeClass('hidden');
                        $('#imgseo-batch-result').removeClass('hidden');
                        return;
                    }
                    
                    var attachmentIds = selectedImages.map(function(image) {
                        return image.id;
                    });
                    
                    // Show loading
                    var $spinner = $('.imgseo-batch-actions .spinner');
                    var $previewButton = $('#imgseo-preview-rename');
                    
                    $spinner.addClass('is-active');
                    $previewButton.prop('disabled', true);
                    
                    // Send AJAX request
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'imgseo_preview_batch_rename',
                            attachment_ids: attachmentIds,
                            options: {
                                pattern: pattern,
                                lowercase: lowercase,
                                remove_accents: removeAccents,
                                handle_duplicates: handleDuplicates,
                                sanitize: true,
                                use_patterns: true
                            },
                            security: '<?php echo wp_create_nonce('imgseo_renamer_nonce'); ?>'
                        },
                        success: function(response) {
                            $spinner.removeClass('is-active');
                            $previewButton.prop('disabled', false);
                            
                            if (response.success) {
                                var previews = response.data.previews;
                                
                                // Update selected images with previewed filenames
                                $.each(previews, function(index, preview) {
                                    var imageIndex = selectedImages.findIndex(function(image) {
                                        return image.id === preview.id;
                                    });
                                    
                                    if (imageIndex !== -1) {
                                        selectedImages[imageIndex].newFilename = preview.new_filename;
                                    }
                                });
                                
                                // Re-render selected images
                                renderSelectedImages();
                                
                                // Show success message
                                $('#imgseo-batch-success-message').text('<?php _e('Preview generated. Review the new filenames before proceeding.', IMGSEO_TEXT_DOMAIN); ?>');
                                $('#imgseo-batch-success').removeClass('hidden');
                                $('#imgseo-batch-result').removeClass('hidden');
                            } else {
                                // Show error message
                                $('#imgseo-batch-error-message').text(response.data.message);
                                $('#imgseo-batch-error').removeClass('hidden');
                                $('#imgseo-batch-result').removeClass('hidden');
                            }
                        },
                        error: function(xhr, status, error) {
                            $spinner.removeClass('is-active');
                            $previewButton.prop('disabled', false);
                            
                            $('#imgseo-batch-error-message').text('<?php _e('Server error. Please try again.', IMGSEO_TEXT_DOMAIN); ?>');
                            $('#imgseo-batch-error').removeClass('hidden');
                            $('#imgseo-batch-result').removeClass('hidden');
                        }
                    });
                });
                
                // Esecuzione della rinomina in blocco
                $('#imgseo-start-batch').on('click', function() {
                    var pattern = $('#imgseo-batch-pattern').val();
                    var lowercase = $('#imgseo-batch-lowercase').is(':checked');
                    var removeAccents = $('#imgseo-batch-remove-accents').is(':checked');
                    var handleDuplicates = $('#imgseo-batch-handle-duplicates').val();
                    
                    // Hide previous results
                    $('#imgseo-batch-result').addClass('hidden');
                    $('#imgseo-batch-success').addClass('hidden');
                    $('#imgseo-batch-error').addClass('hidden');
                    
                    if (selectedImages.length === 0) {
                        $('#imgseo-batch-error-message').text('<?php _e('No images selected.', IMGSEO_TEXT_DOMAIN); ?>');
                        $('#imgseo-batch-error').removeClass('hidden');
                        $('#imgseo-batch-result').removeClass('hidden');
                        return;
                    }
                    
                    if (!confirm('<?php _e('Are you sure you want to rename all selected images? This action cannot be undone.', IMGSEO_TEXT_DOMAIN); ?>')) {
                        return;
                    }
                    
                    var attachmentIds = selectedImages.map(function(image) {
                        return image.id;
                    });
                    
                    // Show loading
                    var $spinner = $('.imgseo-batch-actions .spinner');
                    var $batchButton = $('#imgseo-start-batch');
                    
                    $spinner.addClass('is-active');
                    $batchButton.prop('disabled', true);
                    
                    // Send AJAX request
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'imgseo_batch_rename',
                            attachment_ids: attachmentIds,
                            options: {
                                pattern: pattern,
                                lowercase: lowercase,
                                remove_accents: removeAccents,
                                handle_duplicates: handleDuplicates,
                                sanitize: true,
                                use_patterns: true
                            },
                            security: '<?php echo wp_create_nonce('imgseo_renamer_nonce'); ?>'
                        },
                        success: function(response) {
                            $spinner.removeClass('is-active');
                            $batchButton.prop('disabled', false);
                            
                            if (response.success) {
                                var results = response.data.results;
                                var totalSuccess = Object.keys(results.success).length;
                                var totalErrors = Object.keys(results.errors).length;
                                var totalSkipped = Object.keys(results.skipped || {}).length;
                                
                                // Show success message
                                $('#imgseo-batch-success-message').text(response.data.message);
                                $('#imgseo-batch-success').removeClass('hidden');
                                $('#imgseo-batch-result').removeClass('hidden');
                                
                                // Render results
                                var resultsHtml = '<div class="imgseo-batch-results-summary">';
                                resultsHtml += '<p><strong><?php _e('Total processed:', IMGSEO_TEXT_DOMAIN); ?></strong> ' + selectedImages.length + '</p>';
                                resultsHtml += '<p><strong><?php _e('Successfully renamed:', IMGSEO_TEXT_DOMAIN); ?></strong> ' + totalSuccess + '</p>';
                                
                                if (totalSkipped > 0) {
                                    resultsHtml += '<p><strong><?php _e('Skipped:', IMGSEO_TEXT_DOMAIN); ?></strong> ' + totalSkipped + '</p>';
                                }
                                
                                if (totalErrors > 0) {
                                    resultsHtml += '<p><strong><?php _e('Errors:', IMGSEO_TEXT_DOMAIN); ?></strong> ' + totalErrors + '</p>';
                                }
                                
                                resultsHtml += '</div>';
                                
                                // Success items
                                if (totalSuccess > 0) {
                                    resultsHtml += '<h4><?php _e('Successfully Renamed:', IMGSEO_TEXT_DOMAIN); ?></h4>';
                                    
                                    $.each(results.success, function(id, item) {
                                        resultsHtml += '<div class="imgseo-batch-result-item success">';
                                        resultsHtml += '<strong>' + item.old_filename + '</strong> → <strong>' + item.new_filename + '</strong>';
                                        resultsHtml += '</div>';
                                    });
                                }
                                
                                // Skipped items
                                if (totalSkipped > 0) {
                                    resultsHtml += '<h4><?php _e('Skipped:', IMGSEO_TEXT_DOMAIN); ?></h4>';
                                    
                                    $.each(results.skipped, function(id, item) {
                                        resultsHtml += '<div class="imgseo-batch-result-item skipped">';
                                        resultsHtml += '<strong>' + item.old_filename + '</strong> - ' + item.message;
                                        resultsHtml += '</div>';
                                    });
                                }
                                
                                // Error items
                                if (totalErrors > 0) {
                                    resultsHtml += '<h4><?php _e('Errors:', IMGSEO_TEXT_DOMAIN); ?></h4>';
                                    
                                    $.each(results.errors, function(id, item) {
                                        resultsHtml += '<div class="imgseo-batch-result-item error">';
                                        resultsHtml += '<strong>' + (item.old_filename || 'ID: ' + id) + '</strong> - ' + item.message;
                                        resultsHtml += '</div>';
                                    });
                                }
                                
                                $('#imgseo-results-content').html(resultsHtml);
                                $('#imgseo-batch-results').removeClass('hidden');
                                
                                // Scroll to results
                                $('html, body').animate({
                                    scrollTop: $('#imgseo-batch-results').offset().top - 50
                                }, 500);
                            } else {
                                // Show error message
                                $('#imgseo-batch-error-message').text(response.data.message);
                                $('#imgseo-batch-error').removeClass('hidden');
                                $('#imgseo-batch-result').removeClass('hidden');
                            }
                        },
                        error: function(xhr, status, error) {
                            $spinner.removeClass('is-active');
                            $batchButton.prop('disabled', false);
                            
                            $('#imgseo-batch-error-message').text('<?php _e('Server error. Please try again.', IMGSEO_TEXT_DOMAIN); ?>');
                            $('#imgseo-batch-error').removeClass('hidden');
                            $('#imgseo-batch-result').removeClass('hidden');
                        }
                    });
                });
            });
        </script>
        <?php
    }
    
    /**
     * Render the logs tab content
     */
    private function render_logs_tab() {
        // Get log retention days from settings manager
        $settings_manager = Renamer_Settings_Manager::get_instance();
        $log_retention_days = $settings_manager->get_log_retention_days();
        ?>
        <div class="imgseo-renamer-logs">
            <div class="imgseo-renamer-logs-header">
                <h2><?php _e('Rename Operation Logs', IMGSEO_TEXT_DOMAIN); ?></h2>
                <p><?php echo sprintf(__('Showing rename operations from the last %d days.', IMGSEO_TEXT_DOMAIN), $log_retention_days); ?></p>
                
                <div class="imgseo-renamer-logs-actions">
                    <button type="button" id="imgseo-refresh-logs" class="button button-secondary">
                        <span class="dashicons dashicons-update"></span> 
                        <?php _e('Refresh Logs', IMGSEO_TEXT_DOMAIN); ?>
                    </button>
                    <button type="button" id="imgseo-delete-logs" class="button button-secondary">
                        <span class="dashicons dashicons-trash"></span> 
                        <?php _e('Delete All Logs', IMGSEO_TEXT_DOMAIN); ?>
                    </button>
                </div>
            </div>
            
            <div id="imgseo-logs-table-container">
                <table class="wp-list-table widefat fixed striped imgseo-renamer-logs-table">
                    <thead>
                        <tr>
                            <th scope="col"><?php _e('Date', IMGSEO_TEXT_DOMAIN); ?></th>
                            <th scope="col"><?php _e('Image ID', IMGSEO_TEXT_DOMAIN); ?></th>
                            <th scope="col"><?php _e('Original Filename', IMGSEO_TEXT_DOMAIN); ?></th>
                            <th scope="col"><?php _e('New Filename', IMGSEO_TEXT_DOMAIN); ?></th>
                            <th scope="col"><?php _e('Status', IMGSEO_TEXT_DOMAIN); ?></th>
                        </tr>
                    </thead>
                    <tbody id="imgseo-logs-table-body">
                        <tr>
                            <td colspan="5" class="imgseo-logs-loading">
                                <?php _e('Loading logs...', IMGSEO_TEXT_DOMAIN); ?>
                                <span class="spinner is-active"></span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div id="imgseo-logs-pagination" class="imgseo-renamer-logs-pagination"></div>
            
            <div id="imgseo-logs-empty" class="imgseo-renamer-logs-empty hidden">
                <p><?php _e('No rename operations found in the logs.', IMGSEO_TEXT_DOMAIN); ?></p>
            </div>
        </div>
        
        <script>
            jQuery(document).ready(function($) {
                var currentPage = 1;
                
                // Load logs on page load
                loadRenameLogs(currentPage);
                
                // Refresh logs
                $('#imgseo-refresh-logs').on('click', function() {
                    loadRenameLogs(1);
                });
                
                // Delete logs
                $('#imgseo-delete-logs').on('click', function() {
                    if (confirm('<?php _e('Are you sure you want to delete all rename logs? This action cannot be undone.', IMGSEO_TEXT_DOMAIN); ?>')) {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'imgseo_delete_rename_logs',
                                security: '<?php echo wp_create_nonce('imgseo_renamer_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    loadRenameLogs(1);
                                } else {
                                    alert(response.data.message);
                                }
                            },
                            error: function() {
                                alert('<?php _e('Server error. Please try again.', IMGSEO_TEXT_DOMAIN); ?>');
                            }
                        });
                    }
                });
                
                // Function to load logs via AJAX
                function loadRenameLogs(page) {
                    currentPage = page;
                    
                    // Show loading
                    $('#imgseo-logs-table-body').html('<tr><td colspan="5" class="imgseo-logs-loading"><?php _e('Loading logs...', IMGSEO_TEXT_DOMAIN); ?> <span class="spinner is-active"></span></td></tr>');
                    $('#imgseo-logs-pagination').empty();
                    $('#imgseo-logs-empty').addClass('hidden');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'GET',
                        data: {
                            action: 'imgseo_get_rename_logs',
                            page: page,
                            security: '<?php echo wp_create_nonce('imgseo_renamer_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                if (response.data.logs.length === 0) {
                                    $('#imgseo-logs-table-body').empty();
                                    $('#imgseo-logs-empty').removeClass('hidden');
                                } else {
                                    var logsHtml = '';
                                    
                                    $.each(response.data.logs, function(index, log) {
                                        var rowId = 'log-row-' + log.id;
                                        logsHtml += '<tr id="' + rowId + '">';
                                        logsHtml += '<td>' + log.created_at + '</td>';
                                        logsHtml += '<td>' + log.image_id + '</td>';
                                        logsHtml += '<td>' + log.old_filename + '</td>';
                                        logsHtml += '<td>' + log.new_filename + '</td>';
                                        
                                        // Status with restore button for success entries
                                        var statusClass = log.status === 'success' ? 'success' : 
                                                        log.status === 'restore' ? 'restore' : 'error';
                                        
                                        logsHtml += '<td>';
                                        logsHtml += '<span class="imgseo-log-status ' + statusClass + '">' + log.status + '</span>';
                                        
                                        // Add restore button for successful rename operations
                                        // But not for entries that are already restored
                                        if (log.status === 'success') {
                                            logsHtml += ' <button type="button" id="restore-' + log.image_id + '" ' +
                                                        'class="button button-small restore-button" ' +
                                                        'data-image-id="' + log.image_id + '" ' +
                                                        'data-original="' + log.old_filename + '" ' +
                                                        'data-current="' + log.new_filename + '">' +
                                                        '<span class="dashicons dashicons-undo" style="font-size: 14px; vertical-align: text-bottom;"></span> ' +
                                                        '<?php _e('Restore', IMGSEO_TEXT_DOMAIN); ?>' +
                                                        '</button>';
                                            logsHtml += '<span class="spinner" style="float: none; margin: 0 0 0 5px;"></span>';
                                        }
                                        
                                        logsHtml += '</td>';
                                        logsHtml += '</tr>';
                                    });
                                    
                                    $('#imgseo-logs-table-body').html(logsHtml);
                                    
                                    // Bind restore buttons
                                    $('.restore-button').on('click', function() {
                                        var imageId = $(this).data('image-id');
                                        var originalFilename = $(this).data('original');
                                        var currentFilename = $(this).data('current');
                                        
                                        handleRestore(imageId, originalFilename, currentFilename);
                                    });
                    
                                    // Generate pagination
                                    if (response.data.total_pages > 1) {
                                        var paginationHtml = '<div class="tablenav-pages">';
                                        paginationHtml += '<span class="displaying-num">' + response.data.total_items + ' <?php _e('items', IMGSEO_TEXT_DOMAIN); ?></span>';
                                                        
                                                        if (response.data.total_pages > 1) {
                                                            paginationHtml += '<span class="pagination-links">';
                                                            
                                                            // First page
                                                            if (page > 1) {
                                                                paginationHtml += '<a class="first-page button" href="#" data-page="1"><span class="screen-reader-text"><?php _e('First page', IMGSEO_TEXT_DOMAIN); ?></span><span aria-hidden="true">«</span></a>';
                                                            } else {
                                                                paginationHtml += '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>';
                                                            }
                                                            
                                                            // Previous page
                                                            if (page > 1) {
                                                                paginationHtml += '<a class="prev-page button" href="#" data-page="' + (page - 1) + '"><span class="screen-reader-text"><?php _e('Previous page', IMGSEO_TEXT_DOMAIN); ?></span><span aria-hidden="true">‹</span></a>';
                                                            } else {
                                                                paginationHtml += '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>';
                                                            }
                                                            
                                                            // Current page
                                                            paginationHtml += '<span class="paging-input">';
                                                            paginationHtml += '<span class="tablenav-paging-text">' + page + ' <?php _e('of', IMGSEO_TEXT_DOMAIN); ?> <span class="total-pages">' + response.data.total_pages + '</span></span>';
                                                            paginationHtml += '</span>';
                                                            
                                                            // Next page
                                                            if (page < response.data.total_pages) {
                                                                paginationHtml += '<a class="next-page button" href="#" data-page="' + (page + 1) + '"><span class="screen-reader-text"><?php _e('Next page', IMGSEO_TEXT_DOMAIN); ?></span><span aria-hidden="true">›</span></a>';
                                                            } else {
                                                                paginationHtml += '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>';
                                                            }
                                                            
                                                            // Last page
                                                            if (page < response.data.total_pages) {
                                                                paginationHtml += '<a class="last-page button" href="#" data-page="' + response.data.total_pages + '"><span class="screen-reader-text"><?php _e('Last page', IMGSEO_TEXT_DOMAIN); ?></span><span aria-hidden="true">»</span></a>';
                                                            } else {
                                                                paginationHtml += '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>';
                                                            }
                                                            
                                                            paginationHtml += '</span>';
                                                        }
                                                        
                                                        paginationHtml += '</div>';
                                                        
                                                        $('#imgseo-logs-pagination').html(paginationHtml);
                                                    }
                                                }
                                            } else {
                                                $('#imgseo-logs-table-body').html('<tr><td colspan="5" class="imgseo-logs-error">' + response.data.message + '</td></tr>');
                                            }
                                        },
                                        error: function() {
                                            $('#imgseo-logs-table-body').html('<tr><td colspan="5" class="imgseo-logs-error"><?php _e('Error loading logs. Please try again.', IMGSEO_TEXT_DOMAIN); ?></td></tr>');
                                        }
                                    });
                                }
                                
                                // Function to handle restore operation
                                function handleRestore(imageId, originalFilename, currentFilename) {
                                    if (!confirm('<?php _e('Are you sure you want to restore this image to its original filename?', IMGSEO_TEXT_DOMAIN); ?>')) {
                                        return;
                                    }
                                    
                                    // Show loading spinner
                                    var $restoreBtn = $('#restore-' + imageId);
                                    var $spinner = $restoreBtn.next('.spinner');
                                    $restoreBtn.prop('disabled', true);
                                    $spinner.addClass('is-active');
                                    
                                    // Send AJAX request
                                    $.ajax({
                                        url: ajaxurl,
                                        type: 'POST',
                                        data: {
                                            action: 'imgseo_restore_image',
                                            image_id: imageId,
                                            original_filename: originalFilename,
                                            current_filename: currentFilename,
                                            security: '<?php echo wp_create_nonce('imgseo_renamer_nonce'); ?>'
                                        },
                                        success: function(response) {
                                            $spinner.removeClass('is-active');
                                            
                                            if (response.success) {
                                                alert('<?php _e('Image successfully restored to its original filename.', IMGSEO_TEXT_DOMAIN); ?>');
                                                // Reload logs to show the new restore operation
                                                loadRenameLogs(currentPage);
                                            } else {
                                                $restoreBtn.prop('disabled', false);
                                                alert(response.data.message || '<?php _e('Error restoring image.', IMGSEO_TEXT_DOMAIN); ?>');
                                            }
                                        },
                                        error: function() {
                                            $spinner.removeClass('is-active');
                                            $restoreBtn.prop('disabled', false);
                                            alert('<?php _e('Server error. Please try again.', IMGSEO_TEXT_DOMAIN); ?>');
                                        }
                                    });
                                }
                                
                                // Pagination clicks
                                $(document).on('click', '#imgseo-logs-pagination a.button', function(e) {
                                    e.preventDefault();
                                    var page = $(this).data('page');
                                    loadRenameLogs(page);
                                });
                            });
                        </script>
                        <?php
                    }
                }
