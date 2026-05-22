<?php
declare(strict_types=1);

namespace Tropk\Mcp\Acf;

/**
 * Thin client over the vendored angie-acf-mcp REST controllers
 * (`/wp-json/angie-acf-mcp/v1/{resource}`). Each ACF ability turns its
 * `action` argument into one of these HTTP verbs and returns the parsed
 * REST response.
 *
 * Using rest_do_request — not direct calls into the controller classes —
 * because rest_do_request runs the registered permission checks the same
 * way an HTTP client would. Our ability `permission_callback` only gates
 * on `manage_options`; ACF's own role/cap checks still get to fire here.
 */
final class AngieAcfBridge {

	private const NAMESPACE = '/angie-acf-mcp/v1';

	public function is_active(): bool {
		return function_exists( 'acf_get_field_groups' );
	}

	/**
	 * @param string                $method  GET | POST | PUT | DELETE
	 * @param string                $path    e.g. "fields", "field-groups/group_abc"
	 * @param array<string, mixed>  $body
	 * @return array<string, mixed>|mixed
	 */
	public function request( string $method, string $path, array $body = [] ) {
		if ( ! $this->is_active() ) {
			throw new \RuntimeException( 'Advanced Custom Fields is not active on this site.' );
		}

		$route   = self::NAMESPACE . '/' . ltrim( $path, '/' );
		$request = new \WP_REST_Request( strtoupper( $method ), $route );

		if ( ! empty( $body ) ) {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
			$request->set_body_params( $body );
			foreach ( $body as $k => $v ) {
				$request->set_param( $k, $v );
			}
		}

		$response = rest_do_request( $request );
		if ( $response instanceof \WP_REST_Response ) {
			$status = $response->get_status();
			$data   = $response->get_data();
			if ( $status >= 400 ) {
				$message = is_array( $data ) && isset( $data['message'] ) ? (string) $data['message'] : 'REST error ' . $status;
				throw new \RuntimeException( $message );
			}
			return $data;
		}
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( $response->get_error_message() );
		}
		return $response;
	}
}
