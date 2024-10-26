<?php
/**
 * Admin Customers Page Template
 *
 * @package NewCustomerDiscount
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Aktueller Filter-Status
$days = isset($_GET['days_filter']) ? (int)$_GET['days_filter'] : 30;
$only_new = isset($_GET['only_new']);

// Hole Statistiken für Info-Boxen
$stats = $this->customer_tracker->get_statistics();
?>

<div class="wrap ncd-wrap">
    <h1><?php _e('Neukunden Übersicht', 'newcustomer-discount'); ?></h1>

    <!-- Info Boxes -->
    <div class="ncd-info-boxes">
        <div class="ncd-info-box">
            <h3><?php _e('Gesamt', 'newcustomer-discount'); ?></h3>
            <p class="ncd-big-number"><?php echo esc_html($stats['total']); ?></p>
        </div>
        <div class="ncd-info-box">
            <h3><?php _e('Ausstehend', 'newcustomer-discount'); ?></h3>
            <p class="ncd-big-number"><?php echo esc_html($stats['pending']); ?></p>
        </div>
        <div class="ncd-info-box">
            <h3><?php _e('Rabatt gesendet', 'newcustomer-discount'); ?></h3>
            <p class="ncd-big-number"><?php echo esc_html($stats['sent']); ?></p>
        </div>
    </div>

    <!-- Test Email Card -->
    <div class="ncd-card">
        <h2><?php _e('Test E-Mail senden', 'newcustomer-discount'); ?></h2>
        <p><?php _e('Hier können Sie eine Test-E-Mail mit Gutscheincode an eine beliebige Adresse senden.', 'newcustomer-discount'); ?></p>
        
        <div class="ncd-test-email-form">
            <input type="email" 
                   name="test_email" 
                   placeholder="test@example.com" 
                   class="regular-text"
                   value="<?php echo isset($_POST['test_email']) ? esc_attr($_POST['test_email']) : ''; ?>">
            <button type="button" class="button button-secondary ncd-send-test">
                <?php _e('Test E-Mail senden', 'newcustomer-discount'); ?>
            </button>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="ncd-card">
        <form method="get" class="ncd-filter-form">
            <input type="hidden" name="page" value="new-customers">
            
            <select name="days_filter" class="ncd-select">
                <option value="7" <?php selected($days, 7); ?>>
                    <?php _e('Letzte 7 Tage', 'newcustomer-discount'); ?>
                </option>
                <option value="30" <?php selected($days, 30); ?>>
                    <?php _e('Letzte 30 Tage', 'newcustomer-discount'); ?>
                </option>
                <option value="90" <?php selected($days, 90); ?>>
                    <?php _e('Letzte 90 Tage', 'newcustomer-discount'); ?>
                </option>
                <option value="365" <?php selected($days, 365); ?>>
                    <?php _e('Letztes Jahr', 'newcustomer-discount'); ?>
                </option>
            </select>

            <label class="ncd-checkbox-label">
                <input type="checkbox" 
                       name="only_new" 
                       value="1" 
                       <?php checked($only_new); ?>>
                <?php _e('Nur Neukunden', 'newcustomer-discount'); ?>
            </label>

            <button type="submit" class="button button-secondary">
                <?php _e('Filter anwenden', 'newcustomer-discount'); ?>
            </button>
        </form>
    </div>

    <!-- Info Notice -->
    <div class="notice notice-info">
        <p>
            <?php printf(
                __('Als Neukunden werden alle Kunden gezählt, die vor dem %s noch keine Bestellung aufgegeben haben.', 'newcustomer-discount'),
                date_i18n(get_option('date_format'), strtotime(NEWCUSTOMER_CUTOFF_DATE))
            ); ?>
        </p>
    </div>

    <!-- Customers Table -->
    <div class="ncd-card">
        <table class="wp-list-table widefat fixed striped ncd-customers-table">
            <thead>
                <tr>
                    <th scope="col" class="ncd-col-email">
                        <?php _e('E-Mail', 'newcustomer-discount'); ?>
                    </th>
                    <th scope="col" class="ncd-col-name">
                        <?php _e('Name', 'newcustomer-discount'); ?>
                    </th>
                    <th scope="col" class="ncd-col-date">
                        <?php _e('Bestelldatum', 'newcustomer-discount'); ?>
                    </th>
                    <th scope="col" class="ncd-col-status">
                        <?php _e('Status', 'newcustomer-discount'); ?>
                    </th>
                    <th scope="col" class="ncd-col-code">
                        <?php _e('Rabattcode', 'newcustomer-discount'); ?>
                    </th>
                    <th scope="col" class="ncd-col-sent">
                        <?php _e('Gesendet am', 'newcustomer-discount'); ?>
                    </th>
                    <th scope="col" class="ncd-col-actions">
                        <?php _e('Aktionen', 'newcustomer-discount'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                <tr>
                    <td colspan="7" class="ncd-no-items">
                        <?php _e('Keine Kunden gefunden.', 'newcustomer-discount'); ?>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($customers as $customer): 
                        $is_new = $this->customer_tracker->is_new_customer($customer['customer_email']);
                        $has_coupon = !empty($customer['coupon_code']);
                    ?>
                    <tr>
                        <td class="ncd-col-email">
                            <?php echo esc_html($customer['customer_email']); ?>
                        </td>
                        <td class="ncd-col-name">
                            <?php echo esc_html($customer['customer_first_name'] . ' ' . $customer['customer_last_name']); ?>
                        </td>
                        <td class="ncd-col-date">
                            <?php echo date_i18n(
                                get_option('date_format') . ' ' . get_option('time_format'), 
                                strtotime($customer['created_at'])
                            ); ?>
                        </td>
                        <td class="ncd-col-status">
                            <?php if ($is_new): ?>
                                <span class="ncd-status ncd-status-new">
                                    <?php _e('Neukunde', 'newcustomer-discount'); ?>
                                </span>
                            <?php else: ?>
                                <span class="ncd-status ncd-status-existing">
                                    <?php _e('Bestandskunde', 'newcustomer-discount'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="ncd-col-code">
                            <?php echo $has_coupon ? esc_html($customer['coupon_code']) : '-'; ?>
                        </td>
                        <td class="ncd-col-sent">
                            <?php echo $customer['discount_email_sent'] 
                                ? date_i18n(
                                    get_option('date_format') . ' ' . get_option('time_format'), 
                                    strtotime($customer['discount_email_sent'])
                                  ) 
                                : '-'; ?>
                        </td>
                        <td class="ncd-col-actions">
                            <?php if ($is_new && !$has_coupon): ?>
                                <button type="button" 
                                        class="button button-primary ncd-send-discount"
                                        data-email="<?php echo esc_attr($customer['customer_email']); ?>"
                                        data-first-name="<?php echo esc_attr($customer['customer_first_name']); ?>"
                                        data-last-name="<?php echo esc_attr($customer['customer_last_name']); ?>">
                                    <?php _e('Rabattcode senden', 'newcustomer-discount'); ?>
                                </button>
                            <?php elseif ($has_coupon): ?>
                                <span class="ncd-sent-info" 
                                      title="<?php esc_attr_e('Rabattcode wurde bereits gesendet', 'newcustomer-discount'); ?>">
                                    ✓
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
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

    // Send Discount Handler
    $('.ncd-send-discount').on('click', function() {
        const $button = $(this);
        const email = $button.data('email');
        const firstName = $button.data('first-name');
        const lastName = $button.data('last-name');

        if (!confirm(ncdAdmin.messages.confirm_send)) {
            return;
        }

        $button.prop('disabled', true).addClass('updating-message');

        $.post(ajaxurl, {
            action: 'ncd_send_discount',
            nonce: ncdAdmin.nonce,
            email: email,
            first_name: firstName,
            last_name: lastName
        })
        .done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || ncdAdmin.messages.error);
                $button.prop('disabled', false).removeClass('updating-message');
            }
        })
        .fail(function() {
            alert(ncdAdmin.messages.error);
            $button.prop('disabled', false).removeClass('updating-message');
        });
    });
});
</script>