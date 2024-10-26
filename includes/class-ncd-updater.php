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

    public function __construct($file)
    {
        $this->file = $file;
        add_action('admin_init', [$this, 'set_plugin_properties']);

        // GitHub-Einstellungen
        $this->github_username = 'flyingkaktus';
        $this->github_repo = 'newcustomer-discount-wp-plugin';

        add_filter('pre_set_site_transient_update_plugins', [$this, 'modify_transient'], 10, 1);
        add_filter('plugins_api', [$this, 'plugin_popup'], 10, 3);
        add_filter('upgrader_post_install', [$this, 'after_install'], 10, 3);
    }

    public function set_plugin_properties()
    {
        $this->plugin = get_plugin_data($this->file);
        $this->basename = plugin_basename($this->file);
        $this->active = is_plugin_active($this->basename);
    }

    private function get_repository_info()
    {
        if (is_null($this->github_response)) {
            $request_uri = sprintf(
                'https://api.github.com/repos/%s/%s/releases/latest',
                $this->github_username,
                $this->github_repo
            );

            $response = wp_remote_get($request_uri, [
                'headers' => [
                    'Accept' => 'application/vnd.github.v3+json'
                ]
            ]);

            // Prüfe auf Fehler
            if (is_wp_error($response)) {
                return false;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                return false;
            }

            $response_body = wp_remote_retrieve_body($response);
            if (empty($response_body)) {
                return false;
            }

            $response_data = json_decode($response_body, true);
            if (!is_array($response_data) || !isset($response_data['tag_name'])) {
                return false;
            }

            $this->github_response = $response_data;
        }

        return true;
    }

    public function modify_transient($transient)
    {
        if (property_exists($transient, 'checked')) {
            if ($checked = $transient->checked) {
                // Hole Repository-Informationen
                if (!$this->get_repository_info()) {
                    return $transient;
                }

                $current_version = isset($checked[$this->basename]) ? $checked[$this->basename] : '0';
                $remote_version = $this->github_response['tag_name'];

                // Entferne 'v' Präfix falls vorhanden
                $remote_version = str_replace('v', '', $remote_version);
                
                $out_of_date = version_compare(
                    $remote_version,
                    $current_version,
                    'gt'
                );

                if ($out_of_date) {
                    $new_files = isset($this->github_response['zipball_url']) 
                        ? $this->github_response['zipball_url'] 
                        : null;

                    if (!empty($new_files)) {
                        $slug = current(explode('/', $this->basename));

                        $plugin = [
                            'url' => $this->plugin["PluginURI"],
                            'slug' => $slug,
                            'package' => $new_files,
                            'new_version' => $remote_version
                        ];

                        $transient->response[$this->basename] = (object) $plugin;
                    }
                }
            }
        }

        return $transient;
    }

    public function plugin_popup($result, $action, $args)
    {
        if ($action !== 'plugin_information') {
            return false;
        }

        if (!empty($args->slug)) {
            if ($args->slug == current(explode('/', $this->basename))) {
                if (!$this->get_repository_info()) {
                    return false;
                }

                $plugin = [
                    'name' => $this->plugin["Name"],
                    'slug' => $this->basename,
                    'version' => str_replace('v', '', $this->github_response['tag_name']),
                    'author' => $this->plugin["AuthorName"],
                    'author_profile' => $this->plugin["AuthorURI"],
                    'last_updated' => $this->github_response['published_at'],
                    'homepage' => $this->plugin["PluginURI"],
                    'short_description' => $this->plugin["Description"],
                    'sections' => [
                        'Description' => $this->plugin["Description"],
                        'Updates' => $this->github_response['body'],
                    ],
                    'download_link' => $this->github_response['zipball_url']
                ];

                return (object) $plugin;
            }
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