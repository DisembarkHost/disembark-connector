<?php

namespace DisembarkConnector;

class Command {
    
    /**
     * Retrieve or generate a token for Disembark.
     *
     * ## OPTIONS
     *
     * [--generate]
     * : If provided, generates a new token before retrieving it.
     *
     * ## EXAMPLES
     *
     *     wp disembark token
     *     wp disembark token --generate
     *
     * @when after_wp_load
     */
    public function token( $args, $assoc_args ) {
        if ( isset( $assoc_args['generate'] ) ) {
            $token = wp_generate_password( 42, false );
            update_option( "disembark_token", $token );
            \WP_CLI::success( "New token generated and saved." );
        }
        $token = Token::get();
        \WP_CLI::log( $token );
    }

    /**
     * Retrieve Disembark CLI connection command.
     *
     * ## EXAMPLES
     *
     *     wp disembark backup-url
     *
     * @when after_wp_load
     */
    public function cli_info( $args, $assoc_args ) {
        $token    = Token::get();
        $home_url = home_url();
        \WP_CLI::log( "disembark connect $home_url $token" );
    }

    /**
     * Retrieve Disembark backup URL.
     *
     * ## EXAMPLES
     *
     *     wp disembark backup-url
     *
     * @when after_wp_load
     */
    public function backup_url( $args, $assoc_args ) {
        $token    = Token::get();
        $home_url = home_url();
        \WP_CLI::log( "https://disembark.host/?disembark_site_url=$home_url&disembark_token=$token" );
    }

}