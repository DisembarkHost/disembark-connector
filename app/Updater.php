<?php
namespace DisembarkConnector;

class Updater {

    public $plugin_slug;
    public $version;
    public $cache_key;
    public $cache_allowed;

    public function __construct() {

        if ( defined( 'DISEMBARK_CONNECT_DEV_MODE' ) ) {
            add_filter('https_ssl_verify', '__return_false');
            add_filter('https_local_ssl_verify', '__return_false');
            add_filter('http_request_host_is_external', '__return_true');
        }

        $this->plugin_slug   = dirname ( plugin_basename( __DIR__ ) );
        $this->version       = '1.0.0';
        $this->cache_key     = 'disembark_connect_updater';
        $this->cache_allowed = false;

        add_filter( 'plugins_api', [ $this, 'info' ], 20, 3 );
        add_filter( 'site_transient_update_plugins', [ $this, 'update' ] );
        add_action( 'upgrader_process_complete', [ $this, 'purge' ], 10, 2 );

    }

    public function request(){

        if ( defined( 'DISEMBARK_CONNECT_DEV_MODE' ) ) {
            $manifest_file = dirname( plugin_dir_path( __FILE__ ) ) . "/manifest.json";
            $remote        = json_decode( file_get_contents( $manifest_file ) );
            $token         = Token::get();
            $remote->sections->description = "{$remote->sections->description}<br /><br /><strong>Your Disembark Connector Token</strong><br /><code>$token</code>";
            return $remote;
        }

        $remote = get_transient( $this->cache_key );

        if( false === $remote || ! $this->cache_allowed ) {

            $remote = wp_remote_get( 'https://raw.githubusercontent.com/DisembarkHost/disembark-connector/main/manifest.json', [
                    'timeout' => 30,
                    'headers' => [
                        'Accept' => 'application/json'
                    ]
                ]
            );

            if ( is_wp_error( $remote ) || 200 !== wp_remote_retrieve_response_code( $remote ) || empty( wp_remote_retrieve_body( $remote ) ) ) {
                return false;
            }

            $remote = json_decode( wp_remote_retrieve_body( $remote ) );
            $token  = Token::get();
            $remote->sections->description = "{$remote->sections->description}<br /><br /><strong>Your Disembark Connector Token</strong><br /><code>$token</code>";
            set_transient( $this->cache_key, $remote, DAY_IN_SECONDS );
            return $remote;

        }

        return $remote;

    }

    function info( $response, $action, $args ) {

        // do nothing if you're not getting plugin information right now
        if ( 'plugin_information' !== $action ) {
            return $response;
        }

        // do nothing if it is not our plugin
        if ( empty( $args->slug ) || $this->plugin_slug !== $args->slug ) {
            return $response;
        }

        // get updates
        $remote = $this->request();

        if ( ! $remote ) {
            return $response;
        }

        $response = new \stdClass();

        $response->name           = $remote->name;
        $response->slug           = $remote->slug;
        $response->version        = $remote->version;
        $response->tested         = $remote->tested;
        $response->requires       = $remote->requires;
        $response->author         = $remote->author;
        $response->author_profile = $remote->author_profile;
        $response->donate_link    = $remote->donate_link;
        $response->homepage       = $remote->homepage;
        $response->download_link  = $remote->download_url;
        $response->trunk          = $remote->download_url;
        $response->requires_php   = $remote->requires_php;
        $response->last_updated   = $remote->last_updated;

        $response->sections = [
            'description'  => $remote->sections->description,
            'installation' => $remote->sections->installation,
            'changelog'    => $remote->sections->changelog
        ];

        if ( ! empty( $remote->banners ) ) {
            $response->banners = [
                'low'  => $remote->banners->low,
                'high' => $remote->banners->high
            ];
        }

        return $response;

    }

    public function update( $transient ) {

        if ( empty($transient->checked ) ) {
            return $transient;
        }

        $remote          = $this->request();
        $response        = new \stdClass();
        $response->slug  = $this->plugin_slug;
        $response->plugin = "{$this->plugin_slug}/{$this->plugin_slug}.php";
        $response->tested = $remote->tested;

        if ( $remote && version_compare( $this->version, $remote->version, '<' ) && version_compare( $remote->requires, get_bloginfo( 'version' ), '<=' ) && version_compare( $remote->requires_php, PHP_VERSION, '<' ) ) {
            $response->new_version = $remote->version;
            $response->package     = $remote->download_url;
            $transient->response[ $response->plugin ] = $response;
        } else {
            $transient->no_update[ $response->plugin ] = $response;
        }

        return $transient;

    }

    public function purge( $upgrader, $options ) {

        if ( $this->cache_allowed && 'update' === $options['action'] && 'plugin' === $options[ 'type' ] ) {
            // just clean the cache when new plugin version is installed
            delete_transient( $this->cache_key );
        }

    }

}