<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Options;

use Tropk\Mcp\Abilities\AbstractAbility;

final class OptionsUpdateAbility extends AbstractAbility {

	private const WRITABLE = [
		'blogname', 'blogdescription', 'admin_email', 'timezone_string', 'date_format', 'time_format',
		'start_of_week', 'comment_moderation', 'moderation_notify', 'comments_notify',
		'show_on_front', 'page_on_front', 'page_for_posts', 'posts_per_page', 'default_category',
	];

	public function slug(): string { return 'options-update'; }
	protected function meta(): array { return [
		'label' => __( 'Update a safe option', 'mcp-for-wordpress' ),
		'description' => __( 'Updates one of the safe site options (general / reading / discussion settings). Other keys are refused.', 'mcp-for-wordpress' ),
		'destructive' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'key', 'value' ],
		'properties'           => [
			'key'   => [ 'type' => 'string', 'enum' => self::WRITABLE ],
			'value' => [],
			'dry_run' => [ 'type' => 'boolean', 'default' => false ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'updated' => [ 'type' => 'boolean' ], 'dry_run' => [ 'type' => 'boolean' ], 'key' => [ 'type' => 'string' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$key = (string) $input['key'];
		if ( ! in_array( $key, self::WRITABLE, true ) ) {
			throw new \RuntimeException( 'Key not on the writable allowlist.' );
		}
		if ( ! empty( $input['dry_run'] ) ) {
			return [ 'updated' => false, 'dry_run' => true, 'key' => $key ];
		}
		update_option( $key, $input['value'] );
		return [ 'updated' => true, 'dry_run' => false, 'key' => $key ];
	}
}
