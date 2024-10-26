<?php
/**
 * Plugin Updater Class
 *
 * @package NewCustomerDiscount
 */

class NCD_Updater
{
    private $file;
    private $plugin;
    private $basename;
    private $active;
    private $github_username;
    private $github_repo;
    private $github_response;
    private $authorize_token; // Optional für private Repos

    public function __construct($file)
    {
        // Include plugin.php to get access to is_plugin_active()
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $this->file = $file;
        $this->basename = plugin_basename($file);
        $this->active = is_plugin_active($this->basename);

        // GitHub-Einstellungen
        $this->github_username = 'flyingkaktus';
        $this->github_repo = 'newcustomer-discount-wp-plugin';

        // Plugin Daten direkt setzen
        $this->plugin = get_plugin_data($this->file);

        // Hooks hinzufügen
        add_filter('pre_set_site_transient_update_plugins', [$this, 'modify_transient'], 10, 1);
        add_filter('plugins_api', [$this, 'plugin_popup'], 10, 3);
        add_filter('upgrader_post_install', [$this, 'after_install'], 10, 3);

        // Cache regelmäßig leeren
        add_action('admin_init', [$this, 'clear_plugin_cache']);
    }

    public function clear_plugin_cache()
    {
        delete_site_transient('update_plugins');
    }

    private function get_repository_info()
    {
        if (!empty($this->github_response)) {
            return;
        }

        $request_uri = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->github_username,
            $this->github_repo
        );

        $args = [
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
            ]
        ];

        // Füge Authorization hinzu falls Token gesetzt
        if (!empty($this->authorize_token)) {
            $args['headers']['Authorization'] = "token {$this->authorize_token}";
        }

        $response = wp_remote_get($request_uri, $args);

        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response));

        if (empty($data)) {
            return false;
        }

        // Speichere Response
        $this->github_response = $data;

        return true;
    }

    public function modify_transient($transient)
    {
        if (!is_object($transient)) {
            $transient = new stdClass;
        }

        if (empty($transient->checked)) {
            return $transient;
        }

        // Hole aktuelle Version
        $current_version = $this->plugin['Version'];

        // Hole GitHub Version
        if ($this->get_repository_info()) {
            $remote_version = str_replace('v', '', $this->github_response->tag_name);

            // Debug
            error_log("Current version: " . $current_version);
            error_log("Remote version: " . $remote_version);

            if (version_compare($remote_version, $current_version, '>')) {
                $obj = new stdClass();
                $obj->slug = dirname($this->basename);
                $obj->new_version = $remote_version;
                $obj->url = $this->plugin["PluginURI"];
                $obj->package = $this->github_response->zipball_url;

                $transient->response[$this->basename] = $obj;
            }
        }

        return $transient;
    }

    public function plugin_popup($result, $action, $args)
    {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== dirname($this->basename)) {
            return $result;
        }

        if ($this->get_repository_info()) {
            $plugin = new stdClass();
            $plugin->name = $this->plugin["Name"];
            $plugin->slug = dirname($this->basename);
            $plugin->version = str_replace('v', '', $this->github_response->tag_name);
            $plugin->author = $this->plugin["AuthorName"];
            $plugin->homepage = $this->plugin["PluginURI"];
            $plugin->requires = $this->plugin["RequiresWP"];
            $plugin->tested = $this->plugin["RequiresWP"];
            $plugin->downloaded = 0;
            $plugin->last_updated = $this->github_response->published_at;
            $plugin->sections = [
                'description' => $this->plugin["Description"],
                'changelog' => $this->github_response->body
            ];
            $plugin->download_link = $this->github_response->zipball_url;

            return $plugin;
        }

        return $result;
    }

    public function after_install($response, $hook_extra, $result)
    {
        global $wp_filesystem;

        $install_directory = plugin_dir_path($this->file);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;

        if ($this->active) {
            activate_plugin($this->basename);
        }

        return $result;
    }
}