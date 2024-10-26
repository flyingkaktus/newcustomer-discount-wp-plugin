<?php
/**
 * Discount Email Template
 *
 * @package NewCustomerDiscount
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Sicherstellen dass die benötigten Variablen vorhanden sind
$logo = isset($logo) ? $logo : '';
$coupon_code = isset($coupon_code) ? $coupon_code : '';
$first_name = isset($first_name) ? $first_name : '';
$shop_name = get_bloginfo('name');
$current_year = date('Y');
?>
<!DOCTYPE html>
<html lang="<?php echo get_locale(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($shop_name); ?> - <?php _e('Neukundenrabatt', 'newcustomer-discount'); ?></title>
    <style>
        /* Email Styles */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #0c2461;
            background-color: #f5f6fa;
            margin: 0;
            padding: 0;
        }

        .email-wrapper {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(12, 36, 97, 0.1);
        }

        .email-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            max-width: 200px;
            height: auto;
            margin-bottom: 20px;
        }

        .email-title {
            color: #1e5128;
            font-size: 24px;
            font-weight: bold;
            margin: 0 0 20px;
            text-align: center;
            text-shadow: 0 1px 2px rgba(30, 81, 40, 0.2);
        }

        .email-content {
            background: #ffffff;
            border: 2px solid #1e5128;
            border-radius: 8px;
            padding: 25px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(30, 81, 40, 0.1);
        }

        .coupon-code {
            background: #f1f2f6;
            color: #0c2461;
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            letter-spacing: 2px;
        }

        .email-footer {
            text-align: center;
            font-size: 12px;
            color: #666666;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .highlight {
            color: #1e5128;
            font-weight: bold;
        }

        .button {
            display: inline-block;
            background-color: #1e5128;
            color: #ffffff;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 4px;
            margin: 15px 0;
        }

        /* Responsive Design */
        @media only screen and (max-width: 600px) {
            .email-wrapper {
                padding: 20px;
            }

            .email-title {
                font-size: 20px;
            }

            .coupon-code {
                font-size: 20px;
                padding: 10px;
            }
        }

        /* Dark Mode Support */
        @media (prefers-color-scheme: dark) {
            body {
                background-color: #1a1a1a;
                color: #ffffff;
            }

            .email-wrapper {
                background-color: #2d2d2d;
            }

            .email-content {
                background-color: #2d2d2d;
                border-color: #1e5128;
            }

            .coupon-code {
                background-color: #1a1a1a;
                color: #ffffff;
            }

            .email-footer {
                color: #999999;
                border-top-color: #404040;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <!-- Header -->
        <div class="email-header">
            <?php if ($logo): ?>
                <img src="<?php echo esc_url($logo); ?>" 
                     alt="<?php echo esc_attr($shop_name); ?>" 
                     class="logo">
            <?php endif; ?>
            <h1 class="email-title">
                <?php _e('Dein persönlicher Neukundenrabatt', 'newcustomer-discount'); ?>
            </h1>
        </div>

        <!-- Content -->
        <div class="email-content">
            <p>
                <?php printf(
                    __('Hallo %s,', 'newcustomer-discount'),
                    esc_html($first_name)
                ); ?>
            </p>

            <p>
                <?php _e('vielen Dank für deine Bestellung! Als Dankeschön senden wir dir deinen persönlichen Neukundenrabatt in Höhe von 20%.', 'newcustomer-discount'); ?>
            </p>

            <div class="coupon-code">
                <?php echo esc_html($coupon_code); ?>
            </div>

            <p>
                <?php _e('Zusätzlich zum besten Kratom haben wir im Sale aktuell 15%ige CBD Blüten der Sorte Tropic Kush mit einem einzigartigen Aroma. Das solltest du dir nicht entgehen lassen. Nur solange der Vorrat reicht.', 'newcustomer-discount'); ?>
            </p>

            <p>
                <?php _e('Mit visionären Grüßen', 'newcustomer-discount'); ?><br>
                <?php printf(
                    __('dein %s Team', 'newcustomer-discount'),
                    esc_html($shop_name)
                ); ?>
            </p>

            <a href="<?php echo esc_url(home_url()); ?>" class="button">
                <?php _e('Jetzt einkaufen', 'newcustomer-discount'); ?>
            </a>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p>
                <?php _e('Diese E-Mail wurde automatisch generiert. Bitte antworte nicht darauf.', 'newcustomer-discount'); ?>
            </p>
            <p>
                <?php printf(
                    __('© %1$s %2$s. Alle Rechte vorbehalten.', 'newcustomer-discount'),
                    esc_html($current_year),
                    esc_html($shop_name)
                ); ?>
            </p>
            <p>
                <?php _e('Um dich von unserem Newsletter abzumelden, antworte bitte auf diese E-Mail mit "Abmelden" im Betreff.', 'newcustomer-discount'); ?>
            </p>
        </div>
    </div>
</body>
</html>