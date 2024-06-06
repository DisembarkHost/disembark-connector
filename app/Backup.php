<?php

namespace DisembarkConnector;

class Backup {

    private $backup_path      = "";
    private $backup_url       = "";
    private $token            = "";
    private $zip              = "";
    private $rows_per_segment = 100;

    public function __construct( $token = "" ) {
        $bytes             = random_bytes( 20 );
        $this->token       = empty( $token ) ? substr( bin2hex( $bytes ), 0, -28) : $token;
        $this->zip         = new \ZipArchive;
        $this->backup_path = wp_upload_dir()["basedir"] . "/disembark/{$this->token}";
        $this->backup_url  = wp_upload_dir()["baseurl"] . "/disembark/{$this->token}";
        if ( ! file_exists( $this->backup_path )) {
            mkdir( $this->backup_path, 0777, true );
        }
    }

    function database( $table ) {
        global $wpdb;

        $table_structure  = $wpdb->get_results( "DESCRIBE $table" );
        $backup_file_name = "{$this->backup_path}/{$table}.sql";
        $backup_file      = fopen( $backup_file_name, "w") or die("Unable to open file!");
        if ( ! $table_structure ) {
            echo "Error getting table details: $table";
            return false;
        }
       
        // Add SQL statement to drop existing table
        fwrite( $backup_file, "--\n" );
        fwrite( $backup_file, '-- ' . sprintf( __( 'Delete any existing table %s', 'disembark-connector' ), $this->backquote( $table ) ) . "\n" );
        fwrite( $backup_file, "--\n" );
        fwrite( $backup_file, "\n" );
        fwrite( $backup_file, 'DROP TABLE IF EXISTS ' . $this->backquote( $table ) . ";\n" );
        fwrite( $backup_file, "\n" );

        // Table structure
        // Comment in SQL-file
        fwrite( $backup_file, "--\n" );
        fwrite( $backup_file, '-- ' . sprintf( __( 'Table structure of table %s', 'disembark-connector' ), $this->backquote( $table ) ) . "\n" );
        fwrite( $backup_file, "--\n" );
        fwrite( $backup_file, "\n" );

        $create_table = $wpdb->get_results( "SHOW CREATE TABLE $table", ARRAY_N );
        if ( false === $create_table ) {
            $err_msg = sprintf( __( 'Error with SHOW CREATE TABLE for %s.', 'disembark-connector' ), $table );
            fwrite( $backup_file, "--\n-- $err_msg\n--\n" );
        }
        if ( count( $create_table[0] ) == 1 ) {
            fwrite( $backup_file, $create_table[0][0] );
        } else {
            fwrite( $backup_file, $create_table[0][1] . ' ;' );
        }
        
        if ( false === $table_structure ) {
            $err_msg = sprintf( __( 'Error getting table structure of %s', 'disembark-connector' ), $table );
            fwrite( $backup_file, "--\n-- $err_msg\n--\n" );
        }

        // Comment in SQL-file
        fwrite( $backup_file, "--\n" );
        fwrite( $backup_file, '-- ' . sprintf( __( 'Data contents of table %s', 'disembark-connector' ), $this->backquote( $table ) ) . "\n" );
        fwrite( $backup_file, "--\n" );
    
        $defs = [];
        $ints = [];
        foreach ( $table_structure as $struct ) {
            if ( ( 0 === strpos( $struct->Type, 'tinyint' ) ) ||
                ( 0 === strpos( strtolower( $struct->Type ), 'smallint' ) ) ||
                ( 0 === strpos( strtolower( $struct->Type ), 'mediumint' ) ) ||
                ( 0 === strpos( strtolower( $struct->Type ), 'int' ) ) ||
                ( 0 === strpos( strtolower( $struct->Type ), 'bigint' ) ) ) {
                    $defs[ strtolower( $struct->Field ) ] = ( null === $struct->Default ) ? 'NULL' : $struct->Default;
                    $ints[ strtolower( $struct->Field ) ] = '1';
            }
        }

        $row_start = 0;
        $row_inc   = $this->rows_per_segment;

        do {

            if ( ! ini_get( 'safe_mode' ) ) {
                @set_time_limit( 15 * 60 );
            }
            $table_data = $wpdb->get_results( "SELECT * FROM $table LIMIT {$row_start}, {$row_inc}", ARRAY_A );

            $entries = 'INSERT INTO ' . $this->backquote( $table ) . ' VALUES (';
            //    \x08\\x09, not required
            $search  = [ "\x00", "\x0a", "\x0d", "\x1a" ];
            $replace = [ '\0', '\n', '\r', '\Z' ];

            if ( $table_data ) {
                foreach ( $table_data as $row ) {
                    $values = [];
                    foreach ( $row as $key => $value ) {
                        if ( ! empty( $ints[ strtolower( $key ) ] ) ) {
                            // make sure there are no blank spots in the insert syntax,
                            // yet try to avoid quotation marks around integers
                            $value    = ( null === $value || '' === $value ) ? $defs[ strtolower( $key ) ] : $value;
                            $values[] = ( '' === $value ) ? "''" : $value;
                        } else {
                            $values[] = "'" . str_replace( $search, $replace, $this->sql_addslashes( $value ) ) . "'";
                        }
                    }
                    fwrite( $backup_file, " \n" . $entries . implode( ', ', $values ) . ');' );
                }
                $row_start += $row_inc;
            }
        } while ( ( count( $table_data ) > 0 ) );

        // Create footer/closing comment in SQL-file
        fwrite( $backup_file, "\n" );
        fwrite( $backup_file, "--\n" );
        fwrite( $backup_file, '-- ' . sprintf( __( 'End of data contents of table %s', 'disembark-connector' ), $this->backquote( $table ) ) . "\n" );
        fwrite( $backup_file, "-- --------------------------------------------------------\n" );
        fwrite( $backup_file, "\n" );
        fclose( $backup_file );

        return  "{$this->backup_url}\{$table}.sql";
    }

    function plugins() {
        if ( $this->zip->open ( "{$this->backup_path}/plugins-{$this->token}.zip", \ZipArchive::CREATE ) === TRUE) {
            $directory  = WP_PLUGIN_DIR;
            $files      = ( new Run )->files( $directory );
            foreach( $files as $file ) {
                $this->zip->addFile( "{$directory}/{$file->name}", $file->name );
            }
            $this->zip->close();
        }
        return "{$this->backup_url}plugins-{$this->token}.zip";
    }

    function themes() { 
        if ( $this->zip->open ( "{$this->backup_path}/themes-{$this->token}.zip", \ZipArchive::CREATE ) === TRUE) {
            $directory  = WP_CONTENT_DIR . "/themes";
            $files      = ( new Run )->files( $directory );
            foreach( $files as $file ) {
                $this->zip->addFile( "{$directory}/{$file->name}", $file->name );
            }
            $this->zip->close();
        }
        return "{$this->backup_url}themes-{$this->token}.zip";
    }

    function wordpress() { 
        if ( $this->zip->open ( "{$this->backup_path}/wordpress-{$this->token}.zip", \ZipArchive::CREATE ) === TRUE ) {
            $directory  = get_home_path() . "wp-admin";
            $files      = ( new Run )->files( $directory );
            foreach( $files as $file ) {
                $this->zip->addFile( "{$directory}/{$file->name}", "wp-admin/". $file->name );
            }
            $directory  = get_home_path() . "wp-includes";
            $files      = ( new Run )->files( $directory );
            foreach( $files as $file ) {
                $this->zip->addFile( "{$directory}/{$file->name}", "wp-includes/". $file->name );
            }
            $directory  = get_home_path();
            $files      = [ "index.php", "license.txt", "readme.html", "wp-activate.php", "wp-app.php", "wp-blog-header.php", "wp-comments-post.php", "wp-config-sample.php", "wp-cron.php", "wp-links-opml.php", "wp-load.php", "wp-login.php", "wp-mail.php", "wp-pass.php", "wp-register.php", "wp-settings.php", "wp-signup.php", "wp-trackback.php", "xmlrpc.php" ];
            foreach( $files as $file ) {
                if ( file_exists( "{$directory}/{$file}" ) ) {
                    $this->zip->addFile( "{$directory}/{$file}", $file );
                }
            }
            $this->zip->close();
        }
        return "{$this->backup_url}wordpress-{$this->token}.zip";
    }

    function zip_files( $file_manifest = "" ) {
        if ( empty( $file_manifest ) ) {
            return;
        }
        $file_name = str_replace( ".json", "", basename( $file_manifest ) );
        $files     = json_decode( file_get_contents( $file_manifest ) );
        $zip_name  = "{$this->backup_path}/{$file_name}.zip";
        $directory = get_home_path();
        if ( $this->zip->open ( $zip_name, \ZipArchive::CREATE ) === TRUE) {
            foreach( $files as $file ) {
                $this->zip->addFile( "{$directory}/{$file->name}", $file->name );
            }
            $this->zip->close();
        }
        return "{$this->backup_url}/{$file_name}.zip";
    }

    function zip_database() {
        $database_files = glob( "{$this->backup_path}/*.sql" );
        $zip_name       = "{$this->backup_path}/database.zip";
        if ( $this->zip->open ( $zip_name, \ZipArchive::CREATE ) === TRUE) {
            foreach( $database_files as $file ) {
                $this->zip->addFile( $file, basename( $file ) );
            }
            $this->zip->close();
        }
        // Delete all SQL files
        foreach( $database_files as $file ) {
            unlink( $file );
        }
        return "{$this->backup_url}/database.zip";
    }

    function list_downloads() {
        $zip_files     = glob( "{$this->backup_path}/*.zip" );
        $zip_downloads = [];
        foreach ( $zip_files as $file ) {
            $zip_downloads[] = str_replace( $this->backup_path, $this->backup_url, $file );
        }
        return $zip_downloads;
    }

    /**
	 * Add backquotes to tables and db-names in
	 * SQL queries. Taken from phpMyAdmin.
	 */
	function backquote( $a_name ) {
		if ( ! empty( $a_name ) && $a_name != '*' ) {
			if ( is_array( $a_name ) ) {
				$result = [];
				reset( $a_name );
				while ( list($key, $val) = each( $a_name ) ) {
					$result[ $key ] = '`' . $val . '`';
				}
				return $result;
			} else {
				return '`' . $a_name . '`';
			}
		} else {
			return $a_name;
		}
	}

    /**
	 * Better addslashes for SQL queries.
	 * Taken from phpMyAdmin.
	 */
	function sql_addslashes( $a_string = '', $is_like = false ) {
		if ( $is_like ) {
			$a_string = str_replace( '\\', '\\\\\\\\', $a_string );
		} else {
			$a_string = str_replace( '\\', '\\\\', $a_string );
		}

		return str_replace( '\'', '\\\'', $a_string );
	}

    function everything_else() { 

    }

    function generate_manifest( $files ) {
        $storage_limit    = 104857600;
        $manifest_storage = 0;
        $manifest_count   = 1;
        $file_count       = 0;
        $file_current     = 0;
        $total_files      = count( $files );
        $manifest         = [];
        $response         = [];

        do {
            foreach ( $files as $key => $file ) {
                $manifest[] = $file;
                $manifest_storage += $file->size;
                $file_count++;
                if (  $manifest_storage + $file->size > $storage_limit ) {
                    $files = array_slice($files, $key + 1);
                    break;
                }
            }
            $response[] = (object) [ 
                "name"  => "{$this->backup_path}/files-{$manifest_count}.json",
                "size"  => $manifest_storage,
                "count" => $file_count
            ];
            file_put_contents( "{$this->backup_path}/files-{$manifest_count}.json", json_encode( $manifest, JSON_PRETTY_PRINT ) );
            $manifest_storage = 0;
            $manifest         = [];
            $manifest_count++;
        } while ( $file_count < $total_files );
        file_put_contents( "{$this->backup_path}/manifest.json", json_encode( $response, JSON_PRETTY_PRINT ) );
    }

    function list_manifest() {
        return json_decode( file_get_contents( "{$this->backup_path}/manifest.json" ) );
    }

}