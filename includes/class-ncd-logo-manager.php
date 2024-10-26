<?php
/**
 * Logo Manager Class
 *
 * Verwaltet das Upload, Speichern und Abrufen des Logos für E-Mail-Templates
 *
 * @package NewCustomerDiscount
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class NCD_Logo_Manager {
    /**
     * Option name für das Logo in der WordPress Datenbank
     *
     * @var string
     */
    private static $option_name = 'ncd_logo_base64';

    /**
     * Erlaubte Bildtypen
     *
     * @var array
     */
    private static $allowed_types = ['image/jpeg', 'image/png'];

    /**
     * Maximale Dateigröße in Bytes (2MB)
     *
     * @var int
     */
    private static $max_file_size = 2097152; // 2 * 1024 * 1024

    /**
     * Speichert einen Base64-String als Logo
     *
     * @param string $base64_string Der zu speichernde Base64-String
     * @return bool True bei Erfolg, False bei Fehler
     */
    public static function save_base64($base64_string) {
        try {
            if (!self::validate_base64($base64_string)) {
                throw new Exception(__('Ungültiger Base64-String.', 'newcustomer-discount'));
            }
            
            return update_option(self::$option_name, $base64_string);
        } catch (Exception $e) {
            self::log_error('Base64 save failed', [
                'error' => $e->getMessage(),
                'base64_length' => strlen($base64_string)
            ]);
            return false;
        }
    }

    /**
     * Speichert ein Logo via File-Upload
     *
     * @param array $file $_FILES Array des Uploads
     * @return bool True bei Erfolg, False bei Fehler
     */
    public static function save_logo($file) {
        try {
            if (!self::validate_upload($file)) {
                throw new Exception(__('Ungültige Datei.', 'newcustomer-discount'));
            }

            $base64 = self::convert_to_base64($file);
            if (!$base64) {
                throw new Exception(__('Konvertierung fehlgeschlagen.', 'newcustomer-discount'));
            }

            return self::save_base64($base64);
        } catch (Exception $e) {
            self::log_error('File upload failed', [
                'error' => $e->getMessage(),
                'file' => $file['name']
            ]);
            return false;
        }
    }

    /**
     * Ruft das gespeicherte Logo ab
     *
     * @return string Base64-String des Logos oder leerer String
     */
    public static function get_logo() {
        return get_option(self::$option_name, '');
    }

    /**
     * Löscht das gespeicherte Logo
     *
     * @return bool True bei Erfolg, False bei Fehler
     */
    public static function delete_logo() {
        return delete_option(self::$option_name);
    }

    /**
     * Validiert einen Base64-String
     *
     * @param string $string Zu validierender Base64-String
     * @return bool True wenn valid, False wenn invalid
     */
    private static function validate_base64($string) {
        if (!preg_match('/^data:image\/(jpeg|png);base64,/', $string)) {
            return false;
        }

        $base64_string = preg_replace('/^data:image\/(jpeg|png);base64,/', '', $string);
        $decoded = base64_decode($base64_string, true);

        if (!$decoded) {
            return false;
        }

        // Überprüfe Dateigröße nach Dekodierung
        if (strlen($decoded) > self::$max_file_size) {
            return false;
        }

        return true;
    }

    /**
     * Validiert einen File-Upload
     *
     * @param array $file $_FILES Array des Uploads
     * @return bool True wenn valid, False wenn invalid
     */
    private static function validate_upload($file) {
        // Grundlegende Überprüfungen
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return false;
        }

        // Typ-Überprüfung
        if (!in_array($file['type'], self::$allowed_types)) {
            return false;
        }

        // Größen-Überprüfung
        if ($file['size'] > self::$max_file_size) {
            return false;
        }

        // MIME-Type Validierung
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, self::$allowed_types)) {
            return false;
        }

        return true;
    }

    /**
     * Konvertiert eine Datei zu Base64
     *
     * @param array $file $_FILES Array des Uploads
     * @return string|false Base64-String oder False bei Fehler
     */
    private static function convert_to_base64($file) {
        try {
            if (!file_exists($file['tmp_name'])) {
                throw new Exception('Temporäre Datei nicht gefunden');
            }

            $data = file_get_contents($file['tmp_name']);
            if ($data === false) {
                throw new Exception('Datei konnte nicht gelesen werden');
            }

            return 'data:' . $file['type'] . ';base64,' . base64_encode($data);
        } catch (Exception $e) {
            self::log_error('Base64 conversion failed', [
                'error' => $e->getMessage(),
                'file' => $file['name']
            ]);
            return false;
        }
    }

    /**
     * Loggt Fehler für Debugging
     *
     * @param string $message Fehlermeldung
     * @param array $context Zusätzliche Kontext-Informationen
     * @return void
     */
    private static function log_error($message, $context = []) {
        if (WP_DEBUG) {
            error_log(sprintf(
                '[NewCustomerDiscount] Logo Manager Error: %s | Context: %s',
                $message,
                json_encode($context)
            ));
        }
    }

    /**
     * Gibt die erlaubten Dateitypen zurück
     *
     * @return array Array mit erlaubten MIME-Types
     */
    public static function get_allowed_types() {
        return self::$allowed_types;
    }

    /**
     * Gibt die maximale Dateigröße in Bytes zurück
     *
     * @return int Maximale Dateigröße in Bytes
     */
    public static function get_max_file_size() {
        return self::$max_file_size;
    }
}