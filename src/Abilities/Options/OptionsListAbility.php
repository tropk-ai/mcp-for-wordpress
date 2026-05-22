<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Options;

use Tropk\Mcp\Abilities\AbstractAbility;

final class OptionsListAbility extends AbstractAbility {
	public function slug(): string { return 'options-list'; }
	protected function meta(): array { return [
		'label' => __( 'List safe options', 'mcp-for-wordpress' ),
		'description' => __( 'Returns commonly-needed site options: siteurl, blogname, admin_email, timezone, language, etc. Whitelisted to avoid exposing secrets.', 'mcp-for-wordpress' ),
		'readonly' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'properties'           => [ 'keys' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ] ],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'options' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array {
		$safe = [
			'siteurl', 'home', 'blogname', 'blogdescription', 'users_can_register', 'admin_email',
			'start_of_week', 'timezone_string', 'date_format', 'time_format', 'links_updated_date_format',
			'comment_moderation', 'moderation_notify', 'comments_notify', 'permalink_structure',
			'default_category', 'default_post_format', 'WPLANG',
			'show_on_front', 'page_on_front', 'page_for_posts', 'posts_per_page',
			'thumbnail_size_w', 'thumbnail_size_h', 'medium_size_w', 'medium_size_h', 'large_size_w', 'large_size_h',
			'uploads_use_yearmonth_folders', 'template', 'stylesheet',
		];
		$keys = isset( $input['keys'] ) && is_array( $input['keys'] ) ? array_values( array_intersect( $input['keys'], $safe ) ) : $safe;
		$out  = [];
		foreach ( $keys as $k ) {
			$out[ $k ] = get_option( $k );
		}
		return [ 'options' => $out ];
	}
}
