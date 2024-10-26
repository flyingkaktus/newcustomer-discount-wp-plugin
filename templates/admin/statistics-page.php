<?php
/**
 * Admin Statistics Page Template
 *
 * @package NewCustomerDiscount
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap ncd-wrap">
    <h1><?php _e('Neukunden Rabatt Statistiken', 'newcustomer-discount'); ?></h1>

    <!-- Übersichts-Karten -->
    <div class="ncd-stats-grid">
        <!-- Kunden Statistiken -->
        <div class="ncd-stats-card">
            <h2><?php _e('Kunden', 'newcustomer-discount'); ?></h2>
            <div class="ncd-stats-numbers">
                <div class="ncd-stat-item">
                    <span class="ncd-stat-label"><?php _e('Gesamt', 'newcustomer-discount'); ?></span>
                    <span class="ncd-stat-value"><?php echo esc_html($stats['customers']['total']); ?></span>
                </div>
                <div class="ncd-stat-item">
                    <span class="ncd-stat-label"><?php _e('Neukunden', 'newcustomer-discount'); ?></span>
                    <span class="ncd-stat-value"><?php echo esc_html($stats['customers']['pending']); ?></span>
                </div>
                <div class="ncd-stat-item">
                    <span class="ncd-stat-label"><?php _e('Rabatt erhalten', 'newcustomer-discount'); ?></span>
                    <span class="ncd-stat-value"><?php echo esc_html($stats['customers']['sent']); ?></span>
                </div>
            </div>
        </div>

        <!-- Gutschein Statistiken -->
        <div class="ncd-stats-card">
            <h2><?php _e('Gutscheine', 'newcustomer-discount'); ?></h2>
            <div class="ncd-stats-numbers">
                <div class="ncd-stat-item">
                    <span class="ncd-stat-label"><?php _e('Erstellt', 'newcustomer-discount'); ?></span>
                    <span class="ncd-stat-value"><?php echo esc_html($stats['coupons']['total']); ?></span>
                </div>
                <div class="ncd-stat-item">
                    <span class="ncd-stat-label"><?php _e('Eingelöst', 'newcustomer-discount'); ?></span>
                    <span class="ncd-stat-value"><?php echo esc_html($stats['coupons']['used']); ?></span>
                </div>
                <div class="ncd-stat-item">
                    <span class="ncd-stat-label"><?php _e('Aktiv', 'newcustomer-discount'); ?></span>
                    <span class="ncd-stat-value"><?php echo esc_html($stats['coupons']['active']); ?></span>
                </div>
                <div class="ncd-stat-item">
                    <span class="ncd-stat-label"><?php _e('Abgelaufen', 'newcustomer-discount'); ?></span>
                    <span class="ncd-stat-value"><?php echo esc_html($stats['coupons']['expired']); ?></span>
                </div>
            </div>
        </div>

        <!-- E-Mail Statistiken -->
        <div class="ncd-stats-card">
            <h2><?php _e('E-Mails', 'newcustomer-discount'); ?></h2>
            <div class="ncd-stats-numbers">
                <div class="ncd-stat-item">
                    <span class="ncd-stat-label"><?php _e('Gesendet', 'newcustomer-discount'); ?></span>
                    <span class="ncd-stat-value"><?php echo esc_html($stats['emails']['total_sent']); ?></span>
                </div>
                <div class="ncd-stat-item">
                    <span class="ncd-stat-label"><?php _e('Erfolgsrate', 'newcustomer-discount'); ?></span>
                    <span class="ncd-stat-value"><?php echo number_format($stats['emails']['success_rate'], 1); ?>%</span>
                </div>
                <div class="ncd-stat-item">
                    <span class="ncd-stat-label"><?php _e('Letzter Versand', 'newcustomer-discount'); ?></span>
                    <span class="ncd-stat-value">
                        <?php echo $stats['emails']['last_sent'] ? 
                            date_i18n(get_option('date_format'), strtotime($stats['emails']['last_sent'])) : 
                            '-'; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Metriken -->
    <div class="ncd-performance-section">
        <h2><?php _e('Performance Metriken', 'newcustomer-discount'); ?></h2>
        <div class="ncd-stats-grid">
            <div class="ncd-stats-card">
                <h3><?php _e('Konversionsrate', 'newcustomer-discount'); ?></h3>
                <div class="ncd-big-number">
                    <?php 
                    $conversion_rate = ($stats['coupons']['used'] / max(1, $stats['coupons']['total'])) * 100;
                    echo number_format($conversion_rate, 1) . '%';
                    ?>
                </div>
                <p class="ncd-stats-description">
                    <?php _e('Prozentsatz der eingelösten Gutscheine', 'newcustomer-discount'); ?>
                </p>
            </div>

            <div class="ncd-stats-card">
                <h3><?php _e('Durchschn. Bestellwert', 'newcustomer-discount'); ?></h3>
                <div class="ncd-big-number">
                    <?php 
                    echo wc_price($stats['coupons']['total_amount'] / max(1, $stats['coupons']['used']));
                    ?>
                </div>
                <p class="ncd-stats-description">
                    <?php _e('Durchschnittlicher Wert der Bestellungen mit Gutschein', 'newcustomer-discount'); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Export Button -->
    <div class="ncd-export-section">
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="ncd_export_statistics">
            <?php wp_nonce_field('ncd_export_statistics', 'ncd_export_nonce'); ?>
            <button type="submit" class="button button-primary">
                <?php _e('Statistiken exportieren', 'newcustomer-discount'); ?>
            </button>
        </form>
    </div>

    <!-- Monatliche Trends -->
    <?php if (!empty($stats['emails']['monthly_stats'])): ?>
    <div class="ncd-trends-section">
        <h2><?php _e('Monatliche Trends', 'newcustomer-discount'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Monat', 'newcustomer-discount'); ?></th>
                    <th><?php _e('Versendete E-Mails', 'newcustomer-discount'); ?></th>
                    <th><?php _e('Erfolgreiche Zustellungen', 'newcustomer-discount'); ?></th>
                    <th><?php _e('Erfolgsrate', 'newcustomer-discount'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats['emails']['monthly_stats'] as $month => $month_stats): ?>
                <tr>
                    <td><?php echo date_i18n('F Y', strtotime($month)); ?></td>
                    <td><?php echo esc_html($month_stats['sent']); ?></td>
                    <td><?php echo esc_html($month_stats['success']); ?></td>
                    <td>
                        <?php 
                        $rate = ($month_stats['success'] / max(1, $month_stats['sent'])) * 100;
                        echo number_format($rate, 1) . '%';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>