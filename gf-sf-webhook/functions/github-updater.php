<?php
/**
 * GitHub release updater.
 *
 * @package GFSFWebhook
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GFSF_GitHub_Updater {
	private const OWNER              = 'cchatterton';
	private const REPO               = 'gf-sf-webhook';
	private const SLUG               = 'gf-sf-webhook';
	private const ASSET_NAME         = 'gf-sf-webhook.zip';
	private const RELEASE_TRANSIENT  = 'gfsf_github_latest_release';
	private const ERROR_TRANSIENT    = 'gfsf_github_latest_release_error';
	private const BACKOFF_TRANSIENT  = 'gfsf_github_latest_release_backoff';
	private const CHECK_QUERY_KEY    = 'gfsf_check_updates';
	private const CHECK_RESULT_KEY   = 'gfsf_update_check_result';
	private const GITHUB_URL         = 'https://github.com/cchatterton/gf-sf-webhook';
	private const REQUIRES           = '6.0';
	private const REQUIRES_PHP       = '8.1';
	private const TEXT_DOMAIN        = 'gf-sf-webhook';

	private static $request_release = null;
	private static bool $forced_cache_cleared = false;
	private static bool $rate_limited = false;

	public static function init(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'add_update_data' ) );
		add_filter( 'site_transient_update_plugins', array( __CLASS__, 'add_update_data' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'add_plugin_information' ), 10, 3 );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'add_plugin_row_meta' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'handle_manual_check' ) );
		add_action( 'admin_notices', array( __CLASS__, 'show_manual_check_notice' ) );
		add_action( 'network_admin_notices', array( __CLASS__, 'show_manual_check_notice' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_release_cache_after_update' ), 10, 2 );
	}

	public static function add_update_data( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$plugin_file         = plugin_basename( GFSF_PLUGIN_FILE );
		$transient->response = isset( $transient->response ) && is_array( $transient->response ) ? $transient->response : array();
		$transient->no_update = isset( $transient->no_update ) && is_array( $transient->no_update ) ? $transient->no_update : array();
		$release             = self::get_latest_release();

		if ( ! self::is_valid_release( $release ) ) {
			return $transient;
		}

		$update = self::build_update_object( $release, $plugin_file );

		if ( $update ) {
			$transient->response[ $plugin_file ] = $update;
			unset( $transient->no_update[ $plugin_file ] );

			return $transient;
		}

		unset( $transient->response[ $plugin_file ] );
		unset( $transient->no_update[ $plugin_file ] );

		return $transient;
	}

	public static function add_plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! is_object( $args ) || self::SLUG !== ( $args->slug ?? '' ) ) {
			return $result;
		}

		$release      = self::get_latest_release();
		$valid        = self::is_valid_release( $release );
		$version      = $valid ? self::get_release_version( $release ) : GFSF_VERSION;
		$download_url = $valid ? self::get_release_asset_url( $release ) : '';
		$body         = $valid ? (string) ( $release['body'] ?? '' ) : '';

		return (object) array(
			'name'          => __( 'GF SF Webhook', self::TEXT_DOMAIN ),
			'slug'          => self::SLUG,
			'version'       => $version,
			'author'        => 'AlphaSys',
			'homepage'      => self::GITHUB_URL,
			'download_link' => $download_url,
			'requires'      => self::REQUIRES,
			'requires_php'  => self::REQUIRES_PHP,
			'sections'      => array(
				'description' => __( 'Sends mapped Gravity Forms submissions to a configured Salesforce webhook.', self::TEXT_DOMAIN ),
				'changelog'   => $body ?: __( 'Release notes are available on GitHub.', self::TEXT_DOMAIN ),
			),
		);
	}

	public static function add_plugin_row_meta( $links, $plugin_file ) {
		if ( plugin_basename( GFSF_PLUGIN_FILE ) !== $plugin_file ) {
			return $links;
		}

		$links[] = sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( self::GITHUB_URL ),
			esc_html__( 'GitHub', self::TEXT_DOMAIN )
		);

		if ( current_user_can( 'update_plugins' ) ) {
			$plugins_url = is_multisite() ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' );
			$check_url   = wp_nonce_url(
				add_query_arg( self::CHECK_QUERY_KEY, '1', $plugins_url ),
				self::CHECK_QUERY_KEY
			);
			$links[]     = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $check_url ),
				esc_html__( 'Check for updates', self::TEXT_DOMAIN )
			);
		}

		return $links;
	}

	public static function handle_manual_check(): void {
		if ( ! isset( $_GET[ self::CHECK_QUERY_KEY ] ) ) {
			return;
		}

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die(
				esc_html__( 'You are not allowed to check for plugin updates.', self::TEXT_DOMAIN ),
				esc_html__( 'Permission denied', self::TEXT_DOMAIN ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::CHECK_QUERY_KEY );
		self::clear_release_cache();
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();

		$update_transient = get_site_transient( 'update_plugins' );

		if ( ! is_object( $update_transient ) ) {
			$update_transient = new stdClass();
		}

		$update_transient = self::add_update_data( $update_transient );
		set_site_transient( 'update_plugins', $update_transient );

		$plugin_file = plugin_basename( GFSF_PLUGIN_FILE );
		$result      = isset( $update_transient->response[ $plugin_file ] ) ? 'available' : 'current';

		if ( false !== get_site_transient( self::ERROR_TRANSIENT ) && ! self::is_valid_release( self::$request_release ) ) {
			$result = 'failed';
		}

		$plugins_url = is_multisite() ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' );
		$redirect_url = add_query_arg( self::CHECK_RESULT_KEY, $result, $plugins_url );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	public static function show_manual_check_notice(): void {
		if ( ! current_user_can( 'update_plugins' ) || ! isset( $_GET[ self::CHECK_RESULT_KEY ] ) ) {
			return;
		}

		$result = sanitize_key( wp_unslash( $_GET[ self::CHECK_RESULT_KEY ] ) );
		$notices = array(
			'available' => array( 'success', __( 'A GF SF Webhook update is available. Use the native update action below to install it.', self::TEXT_DOMAIN ) ),
			'current'   => array( 'success', __( 'GF SF Webhook is up to date.', self::TEXT_DOMAIN ) ),
			'failed'    => array( 'warning', __( 'GF SF Webhook could not check GitHub for updates. Please try again later.', self::TEXT_DOMAIN ) ),
		);

		if ( ! isset( $notices[ $result ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $notices[ $result ][0] ),
			esc_html( $notices[ $result ][1] )
		);
	}

	public static function clear_release_cache_after_update( $upgrader, $options ): void {
		unset( $upgrader );

		if ( ! is_array( $options ) || 'update' !== ( $options['action'] ?? '' ) || 'plugin' !== ( $options['type'] ?? '' ) ) {
			return;
		}

		$updated_plugins = isset( $options['plugins'] ) && is_array( $options['plugins'] ) ? $options['plugins'] : array();

		if ( isset( $options['plugin'] ) ) {
			$updated_plugins[] = $options['plugin'];
		}

		if ( in_array( plugin_basename( GFSF_PLUGIN_FILE ), $updated_plugins, true ) ) {
			self::clear_release_cache();
		}
	}

	private static function build_update_object( $release, string $plugin_file ): ?object {
		$version      = self::get_release_version( $release );
		$download_url = self::get_release_asset_url( $release );

		if ( '' === $version || '' === $download_url || ! version_compare( $version, GFSF_VERSION, '>' ) ) {
			return null;
		}

		return (object) array(
			'id'           => self::GITHUB_URL,
			'slug'         => self::SLUG,
			'plugin'       => $plugin_file,
			'new_version'  => $version,
			'url'          => (string) ( $release['html_url'] ?? self::GITHUB_URL ),
			'package'      => $download_url,
			'requires'     => self::REQUIRES,
			'requires_php' => self::REQUIRES_PHP,
		);
	}

	private static function get_latest_release() {
		$forced = self::is_forced_update_check();

		if ( $forced && ! self::$forced_cache_cleared ) {
			self::clear_release_cache();
			self::$forced_cache_cleared = true;
		}

		if ( null !== self::$request_release ) {
			return self::$request_release;
		}

		$cached = get_site_transient( self::RELEASE_TRANSIENT );

		if ( is_array( $cached ) && self::is_usable_cached_release( $cached ) ) {
			self::$request_release = $cached;

			return $cached;
		}

		if ( ! $forced && false !== get_site_transient( self::BACKOFF_TRANSIENT ) ) {
			self::$request_release = false;

			return false;
		}

		$release = self::lookup_manifest_release();

		if ( ! $release && ! self::$rate_limited ) {
			$release = self::lookup_redirect_release();
		}

		if ( ! $release && ! self::$rate_limited ) {
			$release = self::lookup_api_release();
		}

		if ( ! self::is_valid_release( $release ) ) {
			set_site_transient( self::BACKOFF_TRANSIENT, 1, 10 * MINUTE_IN_SECONDS );
			delete_site_transient( self::RELEASE_TRANSIENT );
			self::$request_release = false;

			return false;
		}

		$version   = self::get_release_version( $release );
		$cache_ttl = version_compare( $version, GFSF_VERSION, '>' ) ? 6 * HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS;
		$release['gfsf_cached_for_version'] = GFSF_VERSION;
		$release['gfsf_cached_at']          = time();

		set_site_transient( self::RELEASE_TRANSIENT, $release, $cache_ttl );
		delete_site_transient( self::ERROR_TRANSIENT );
		delete_site_transient( self::BACKOFF_TRANSIENT );
		self::$request_release = $release;

		return $release;
	}

	private static function lookup_manifest_release() {
		$response = wp_remote_get(
			'https://raw.githubusercontent.com/' . self::OWNER . '/' . self::REPO . '/main/update.json',
			array(
				'timeout' => 10,
				'headers' => array( 'User-Agent' => 'GF-SF-Webhook/' . GFSF_VERSION ),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::record_release_error( 'wp_error', 0, $response->get_error_message() );

			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $response_code ) {
			self::mark_rate_limited( $response, $response_code );
			self::record_release_error( 'http_error', $response_code, wp_remote_retrieve_response_message( $response ) );

			return false;
		}

		$manifest = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $manifest ) || ! self::is_valid_version( $manifest['version'] ?? '' ) ) {
			self::record_release_error( 'json_error', 0, 'Invalid update manifest.' );

			return false;
		}

		return self::build_constructed_release(
			(string) $manifest['version'],
			sanitize_textarea_field( (string) ( $manifest['body'] ?? '' ) )
		);
	}

	private static function lookup_redirect_release() {
		$response = wp_remote_get(
			self::GITHUB_URL . '/releases/latest',
			array(
				'timeout'     => 10,
				'redirection' => 0,
				'headers'     => array( 'User-Agent' => 'GF-SF-Webhook/' . GFSF_VERSION ),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::record_release_error( 'wp_error', 0, $response->get_error_message() );

			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$location      = wp_remote_retrieve_header( $response, 'location' );

		if ( $response_code < 300 || $response_code >= 400 || ! is_string( $location ) ) {
			self::mark_rate_limited( $response, $response_code );
			self::record_release_error( 'http_error', $response_code, 'Latest release redirect unavailable.' );

			return false;
		}

		$path = (string) wp_parse_url( $location, PHP_URL_PATH );

		if ( ! preg_match( '#/releases/tag/([^/]+)$#', $path, $matches ) ) {
			self::record_release_error( 'http_error', $response_code, 'Latest release tag unavailable.' );

			return false;
		}

		$version = ltrim( rawurldecode( $matches[1] ), 'vV' );

		if ( ! self::is_valid_version( $version ) ) {
			self::record_release_error( 'json_error', 0, 'Invalid release version.' );

			return false;
		}

		return self::build_constructed_release( $version, '' );
	}

	private static function lookup_api_release() {
		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::OWNER . '/' . self::REPO . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'GF-SF-Webhook/' . GFSF_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::record_release_error( 'wp_error', 0, $response->get_error_message() );

			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $response_code ) {
			self::mark_rate_limited( $response, $response_code );
			self::record_release_error( 'http_error', $response_code, wp_remote_retrieve_response_message( $response ) );

			return false;
		}

		$release = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! self::is_valid_release( $release ) ) {
			self::record_release_error( 'json_error', 0, 'Invalid GitHub release response.' );

			return false;
		}

		return $release;
	}

	private static function build_constructed_release( string $version, string $body ): array {
		$tag = 'v' . $version;

		return array(
			'tag_name' => $tag,
			'body'     => $body,
			'html_url' => self::GITHUB_URL . '/releases/tag/' . rawurlencode( $tag ),
			'assets'   => array(
				array(
					'name'                 => self::ASSET_NAME,
					'browser_download_url' => self::GITHUB_URL . '/releases/download/' . rawurlencode( $tag ) . '/' . self::ASSET_NAME,
				),
			),
		);
	}

	private static function record_release_error( string $type, int $code, string $message ): void {
		set_site_transient(
			self::ERROR_TRANSIENT,
			array(
				'type'       => $type,
				'code'       => $code,
				'message'    => $message,
				'checked_at' => time(),
			),
			10 * MINUTE_IN_SECONDS
		);
	}

	private static function mark_rate_limited( $response, int $response_code ): void {
		$remaining = wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' );

		if ( 429 === $response_code || ( 403 === $response_code && '0' === (string) $remaining ) ) {
			self::$rate_limited = true;
		}
	}

	private static function get_release_version( $release ): string {
		if ( ! is_array( $release ) ) {
			return '';
		}

		$version = ltrim( (string) ( $release['tag_name'] ?? '' ), 'vV' );

		return self::is_valid_version( $version ) ? $version : '';
	}

	private static function is_valid_release( $release ): bool {
		return is_array( $release )
			&& '' !== self::get_release_version( $release )
			&& '' !== self::get_release_asset_url( $release );
	}

	private static function is_usable_cached_release( array $release ): bool {
		if ( GFSF_VERSION !== (string) ( $release['gfsf_cached_for_version'] ?? '' ) || ! self::is_valid_release( $release ) ) {
			return false;
		}

		$version = self::get_release_version( $release );

		if ( version_compare( $version, GFSF_VERSION, '>' ) ) {
			return true;
		}

		$cached_at = (int) ( $release['gfsf_cached_at'] ?? 0 );

		return $cached_at > 0 && ( time() - $cached_at ) < 5 * MINUTE_IN_SECONDS;
	}

	private static function get_release_asset_url( $release ): string {
		if ( ! is_array( $release ) || empty( $release['assets'] ) || ! is_array( $release['assets'] ) ) {
			return '';
		}

		foreach ( $release['assets'] as $asset ) {
			if ( is_array( $asset ) && self::ASSET_NAME === ( $asset['name'] ?? '' ) && ! empty( $asset['browser_download_url'] ) ) {
				return esc_url_raw( (string) $asset['browser_download_url'] );
			}
		}

		return '';
	}

	private static function is_valid_version( $version ): bool {
		return is_string( $version ) && 1 === preg_match( '/^[0-9]+(?:\.[0-9]+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/', $version );
	}

	private static function is_forced_update_check(): bool {
		if ( ! is_admin() || ! current_user_can( 'update_plugins' ) ) {
			return false;
		}

		if ( isset( $_GET[ self::CHECK_QUERY_KEY ] ) || isset( $_POST[ self::CHECK_QUERY_KEY ] ) ) {
			return true;
		}

		if ( isset( $_GET['force-check'] ) || isset( $_POST['force-check'] ) ) {
			return true;
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		return in_array( $action, array( 'update-selected', 'upgrade-plugin', 'do-plugin-upgrade' ), true );
	}

	private static function clear_release_cache(): void {
		delete_site_transient( self::RELEASE_TRANSIENT );
		delete_site_transient( self::ERROR_TRANSIENT );
		delete_site_transient( self::BACKOFF_TRANSIENT );
		self::$request_release = null;
	}
}

GFSF_GitHub_Updater::init();
