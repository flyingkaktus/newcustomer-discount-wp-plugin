<?php
/**
 * Admin Settings Page Template
 *
 * @package NewCustomerDiscount
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_logo = NCD_Logo_Manager::get_logo();
$max_file_size = size_format(NCD_Logo_Manager::get_max_file_size());
$allowed_types = implode(', ', array_map(function($type) {
    return strtoupper(str_replace('image/', '', $type));
}, NCD_Logo_Manager::get_allowed_types()));
?>

<div class="wrap ncd-wrap">
    <h1><?php _e('Neukunden Rabatt Einstellungen', 'newcustomer-discount'); ?></h1>

    <div class="ncd-tabs">
        <nav class="nav-tab-wrapper">
            <a href="#logo-settings" class="nav-tab nav-tab-active">
                <?php _e('Logo', 'newcustomer-discount'); ?>
            </a>
            <a href="#email-settings" class="nav-tab">
                <?php _e('E-Mail', 'newcustomer-discount'); ?>
            </a>
            <a href="#coupon-settings" class="nav-tab">
                <?php _e('Gutscheine', 'newcustomer-discount'); ?>
            </a>
        </nav>

        <!-- Logo Settings -->
        <div id="logo-settings" class="ncd-tab-content active">
            <div class="ncd-card">
                <h2><?php _e('Logo-Einstellungen', 'newcustomer-discount'); ?></h2>
                
                <form method="post" enctype="multipart/form-data" class="ncd-logo-form">
                    <?php wp_nonce_field('ncd_settings', 'ncd_settings_nonce'); ?>

                    <table class="form-table ncd-form-table">
                        <tr>
                            <th scope="row">
                                <label for="logo_file">
                                    <?php _e('Logo hochladen', 'newcustomer-discount'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="file" 
                                       name="logo_file" 
                                       id="ncd-logo-file" 
                                       accept="image/png,image/jpeg" 
                                       class="ncd-file-input">
                                <p class="description">
                                    <?php printf(
                                        __('Erlaubte Dateitypen: %s. Maximale Größe: %s', 'newcustomer-discount'),
                                        $allowed_types,
                                        $max_file_size
                                    ); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="logo_base64">
                                    <?php _e('ODER Base64-String', 'newcustomer-discount'); ?>
                                </label>
                            </th>
                            <td>
                                <textarea name="logo_base64" 
                                          id="logo_base64" 
                                          rows="5" 
                                          class="large-text code ncd-textarea"
                                          placeholder="data:image/png;base64,..."><?php echo esc_textarea($current_logo); ?></textarea>
                                <p class="description">
                                    <?php _e('Fügen Sie hier Ihren Base64-codierten Bildstring ein (beginnt mit \'data:image/...\')', 'newcustomer-discount'); ?>
                                </p>
                            </td>
                        </tr>

                        <?php if ($current_logo): ?>
                        <tr>
                            <th scope="row">
                                <?php _e('Aktuelles Logo', 'newcustomer-discount'); ?>
                            </th>
                            <td>
                                <div class="ncd-logo-preview-wrapper">
                                    <img src="<?php echo esc_attr($current_logo); ?>" 
                                         alt="<?php _e('Aktuelles Logo', 'newcustomer-discount'); ?>"
                                         class="ncd-logo-preview">
                                </div>
                                <div class="ncd-logo-actions">
                                    <button type="submit" 
                                            name="delete_logo" 
                                            class="button button-secondary ncd-delete-logo">
                                        <?php _e('Logo löschen', 'newcustomer-discount'); ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>

                    <p class="submit">
                        <button type="submit" 
                                name="update_logo" 
                                class="button button-primary">
                            <?php _e('Logo speichern', 'newcustomer-discount'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>

        <!-- Email Settings -->
        <div id="email-settings" class="ncd-tab-content">
            <div class="ncd-card">
                <h2><?php _e('E-Mail-Einstellungen', 'newcustomer-discount'); ?></h2>
                
                <form method="post" class="ncd-email-settings-form">
                    <?php wp_nonce_field('ncd_settings', 'ncd_settings_nonce'); ?>

                    <table class="form-table ncd-form-table">
                        <tr>
                            <th scope="row">
                                <label for="email_subject">
                                    <?php _e('E-Mail-Betreff', 'newcustomer-discount'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text" 
                                       name="email_subject" 
                                       id="email_subject" 
                                       value="<?php echo esc_attr(get_option('ncd_email_subject', __('Dein persönlicher Neukundenrabatt', 'newcustomer-discount'))); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <?php _e('Test-E-Mail', 'newcustomer-discount'); ?>
                            </th>
                            <td>
                                <div class="ncd-test-email-form">
                                    <input type="email" 
                                           name="test_email" 
                                           placeholder="test@example.com" 
                                           class="regular-text">
                                    <button type="button" 
                                            class="button button-secondary ncd-send-test">
                                        <?php _e('Test-E-Mail senden', 'newcustomer-discount'); ?>
                                    </button>
                                </div>
                                <p class="description">
                                    <?php _e('Senden Sie eine Test-E-Mail, um die Einstellungen zu überprüfen.', 'newcustomer-discount'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" 
                                name="save_email_settings" 
                                class="button button-primary">
                            <?php _e('Einstellungen speichern', 'newcustomer-discount'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>

        <!-- Coupon Settings -->
        <div id="coupon-settings" class="ncd-tab-content">
            <div class="ncd-card">
                <h2><?php _e('Gutschein-Einstellungen', 'newcustomer-discount'); ?></h2>
                
                <form method="post" class="ncd-coupon-settings-form">
                    <?php wp_nonce_field('ncd_settings', 'ncd_settings_nonce'); ?>

                    <table class="form-table ncd-form-table">
                        <tr>
                            <th scope="row">
                                <label for="discount_amount">
                                    <?php _e('Rabatt-Höhe (%)', 'newcustomer-discount'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number" 
                                       name="discount_amount" 
                                       id="discount_amount" 
                                       value="<?php echo esc_attr(get_option('ncd_discount_amount', '20')); ?>" 
                                       min="1" 
                                       max="100" 
                                       step="1" 
                                       class="small-text">
                                <p class="description">
                                    <?php _e('Prozentuale Höhe des Neukundenrabatts.', 'newcustomer-discount'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="expiry_days">
                                    <?php _e('Gültigkeitsdauer (Tage)', 'newcustomer-discount'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number" 
                                       name="expiry_days" 
                                       id="expiry_days" 
                                       value="<?php echo esc_attr(get_option('ncd_expiry_days', '30')); ?>" 
                                       min="1" 
                                       max="365" 
                                       step="1" 
                                       class="small-text">
                                <p class="description">
                                    <?php _e('Anzahl der Tage, die der Gutschein gültig ist.', 'newcustomer-discount'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" 
                                name="save_coupon_settings" 
                                class="button button-primary">
                            <?php _e('Einstellungen speichern', 'newcustomer-discount'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Tab Navigation
    $('.ncd-tabs .nav-tab').on('click', function(e) {
        e.preventDefault();
        
        // Update active tab
        $('.ncd-tabs .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show corresponding content
        const target = $(this).attr('href');
        $('.ncd-tab-content').removeClass('active');
        $(target).addClass('active');
    });

    // File Input Preview
    $('#ncd-logo-file').on('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('.ncd-logo-preview').attr('src', e.target.result);
            };
            reader.readAsDataURL(file);
        }
    });

    // Test Email Handler
    $('.ncd-send-test').on('click', function() {
        const $button = $(this);
        const $input = $button.prev('input[type="email"]');
        const email = $input.val();

        if (!email) {
            alert(ncdAdmin.messages.email_required);
            return;
        }

        if (!confirm(ncdAdmin.messages.confirm_test)) {
            return;
        }

        $button.prop('disabled', true).addClass('updating-message');

        $.post(ajaxurl, {
            action: 'ncd_send_test_email',
            nonce: ncdAdmin.nonce,
            email: email
        })
        .done(function(response) {
            if (response.success) {
                alert(response.data.message);
            } else {
                alert(response.data.message || ncdAdmin.messages.error);
            }
        })
        .fail(function() {
            alert(ncdAdmin.messages.error);
        })
        .always(function() {
            $button.prop('disabled', false).removeClass('updating-message');
        });
    });
});
</script>