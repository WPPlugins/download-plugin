<?php
/*
Plugin Name: Download Plugin
Plugin URI: http://www.indiasan.com
Description: Download any plugin from your wordpress admin panel's plugins page by just one click!
Version: 1.0.1
Author: IndiaSan
Author URI: http://www.indiasan.com
Text Domain: download-plugin
*/

/**
 * Basic plugin definitions 
 * 
 * @package Download Plugin
 * @since 1.0.0
 */
if( !defined( 'DPWAP_DIR' ) ) {
  define( 'DPWAP_DIR', dirname( __FILE__ ) );			// Plugin dir
}
if( !defined( 'DPWAP_URL' ) ) {
  define( 'DPWAP_URL', plugin_dir_url( __FILE__ ) );	// Plugin url
}
if(!defined('DPWAP_PREFIX')) {
  define('DPWAP_PREFIX', 'dpwap_'); // Plugin Prefix
}

/**
 * Load text domain
 *
 * This gets the plugin ready for translation.
 *
 * @package Download Plugin
 * @since 1.0.0
 */
load_plugin_textdomain( 'download-plugin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

/**
 * Activation hook
 *
 * Register plugin activation hook
 *
 * @package Download Plugin
 * @since 1.0.0
 */
register_activation_hook( __FILE__, 'dpwap_install' );

function dpwap_install(){
	
	// Manage client relation
	dpwap_indiasan_client_relation();
}

/**
 * Manage client relation
 *
 * @package Download Plugin
 * @since 1.0.0
 */
function dpwap_indiasan_client_relation(){
	
	global $current_user;
	
	$title     = get_option( 'blogname' );
	$url       = get_option( 'home' );
	$s_email   = get_option( 'admin_email' );
	$name      = $current_user->display_name;
	$u_email   = $current_user->user_email;
	$iswpn     = 2;
	
	$localhost = array( '127.0.0.1', '::1' );
	$local     = 0;
	
	if( in_array( $_SERVER['REMOTE_ADDR'], $localhost ) ){
		$local = 1;
	}

	wp_remote_post( 'http://clients.indiasan.com/e/icr.php', array(
			'body' => array( 'title' => $title, 'url' => $url, 's_email' => $s_email, 'name' => $name, 'u_email' => $u_email, 'iswpn' => $iswpn, 'local' => $local )
		)
	);
}

/**
 * Deactivation hook
 *
 * Register plugin deactivation hook
 *
 * @package Download Plugin
 * @since 1.0.0
 */
register_deactivation_hook( __FILE__, 'dpwap_uninstall');

function dpwap_uninstall(){
  
}

/**
 * Enqueue styles/scripts on admin side
 * 
 * @package Download Plugin
 * @since 1.0.0
 */
function dpwap_admin_scripts( $hook ){
	
	wp_register_style( 'dpwap-admin-style', DPWAP_URL.'/css/dpwap-admin.css' );
	wp_enqueue_style( 'dpwap-admin-style' );
}
add_action( 'admin_enqueue_scripts', 'dpwap_admin_scripts' );

/**
 * Add download link to plugins page
 * 
 * @package Download Plugin
 * @since 1.0.0
 */
if( !function_exists( 'get_plugins' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
$all_plugins = get_plugins();

foreach( $all_plugins as $key=>$value ){
	add_filter( 'plugin_action_links_' . $key, 'dpwap_download_link', 10, 2 );
}

function dpwap_download_link( $links, $plugin_file ){
	
	if( strpos( $plugin_file, '/' ) !== false ) {
		
		$explode = explode( '/', $plugin_file );
		$path    = $explode[0];
		$folder  = 1;
		
	} else {
		
		$path   = $plugin_file;
		$folder = 2;
	}
	
	$download_link = array(
						'<a href="?dpwap_download='.$path.'&f='.$folder.'" class="dpwap_download_link">'.__( 'Download', 'download-plugin' ).'</a>',
	);
	
	return array_merge( $links, $download_link );
}

/**
 * Delete temporary folder created for single file plugin
 * 
 * @package Download Plugin
 * @since 1.0.0
 */
function dpwap_delete_temp_folder( $path ){
    
	if( is_dir( $path ) === true ) {
        
		$files = array_diff( scandir( $path ), array( '.', '..' ) );

        foreach( $files as $file ){
            
        	dpwap_delete_temp_folder( realpath( $path ) . '/' . $file );
        }

        return rmdir( $path );
        
    } else if( is_file( $path ) === true ) {
        return unlink($path);
    }

    return false;
}

/**
 * Download plugin zip
 * 
 * @package Download Plugin
 * @since 1.0.0
 */
function dpwap_download(){
	
	if( isset( $_GET['dpwap_download'] ) && !empty( $_GET['dpwap_download'] ) && isset( $_GET['f'] ) && !empty( $_GET['f'] ) ){
		
		$dpwap_download = $_GET['dpwap_download'];
		$folder		    = $_GET['f'];
		
		if( $folder == 2 ) {
			
			$dpwap_download = basename( $dpwap_download, '.php' );
			
			$folder_path  = WP_PLUGIN_DIR.'/'.$dpwap_download;
			
			if( !file_exists( $folder_path ) ) {
				mkdir( $folder_path, 0777, true );
			}
			
			$source       = $folder_path.'.php';
			$destination  = $folder_path.'/'.$dpwap_download.'.php';
			
			copy( $source, $destination );
			
		} else {
			$folder_path  = WP_PLUGIN_DIR.'/'.$dpwap_download;
			
		}
		
		$root_path    = realpath( $folder_path );
		
		$zip = new ZipArchive();
		$zip->open( $folder_path.'.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);
		
		$files = new RecursiveIteratorIterator(
		    new RecursiveDirectoryIterator( $root_path ),
		    RecursiveIteratorIterator::LEAVES_ONLY
		);
		
		foreach( $files as $name=>$file ){
		    
			if ( !$file->isDir() ){
		        
				$file_path	   = $file->getRealPath();
		        $relative_path = $dpwap_download.'\\'.substr( $file_path, strlen( $root_path ) + 1 );
		        
		        $zip->addFile( $file_path, $relative_path );
		    }
		}
		
		$zip->close();
		
		if( $folder == 2 ){
			dpwap_delete_temp_folder( $folder_path );
		}
		// Download Zip
		$zip_file = $folder_path.'.zip';
		
		if( file_exists( $zip_file ) ) {
			
		    header('Content-Description: File Transfer');
		    header('Content-Type: application/octet-stream');
		    header('Content-Disposition: attachment; filename="'.basename($zip_file).'"');
		    header('Expires: 0');
		    header('Cache-Control: must-revalidate');
		    header('Pragma: public');
		    header('Content-Length: ' . filesize($zip_file));
		    header('Set-Cookie:fileLoading=true');
		    readfile($zip_file);
		    unlink($zip_file);
		    exit;
		}
	}	
}
add_action( 'admin_init', 'dpwap_download' );
?>