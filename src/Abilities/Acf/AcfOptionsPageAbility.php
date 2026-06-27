<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Acf;

use Tropk\Mcp\Abilities\Ability;
use Tropk\Mcp\Abilities\AbilityRegistrar;
use Tropk\Mcp\Acf\AcfRuntime;

/**
 * Manage ACF Pro Options Pages. Options pages are admin pages whose
 * fields store SITE-WIDE values (read with target="options"). Two kinds:
 *
 * - Code-registered: `acf_add_options_page()` (runtime). Lost on restart
 *   if not re-registered each request; we surface list/add/remove of the
 *   currently-registered pages.
 * - UI-registered (Pro 6.2+): persisted as `acf-ui-options-page` posts in
 *   wp_posts and managed via the standard CRUD here.
 */
final class AcfOptionsPageAbility implements Ability {

	public function slug(): string {
		return 'acf-options-page';
	}

	public function definition(): array {
		return [
			'label'               => __( 'Manage ACF options pages (Pro)', 'mcp-for-wordpress' ),
			'description'         => __( 'Manage ACF Pro Options Pages — admin pages whose fields store site-wide values (target="options" or the page menu_slug). list-options-pages returns code-registered + UI-registered pages. add-options-page / add-options-sub-page register a page at runtime. create-ui-options-page persists a Pro 6.2+ UI options page in the database.', 'mcp-for-wordpress' ),
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => [
				'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => [ 'action' ],
				'properties'           => [
					'action'      => [
						'type' => 'string',
						'enum' => [
							'list-options-pages', 'add-options-page', 'add-options-sub-page',
							'set-options-page-menu',
							'get-ui-options-page', 'create-ui-options-page', 'update-ui-options-page', 'delete-ui-options-page',
						],
					],
					'page'        => [
						'type'                 => 'object',
						'description'          => 'Page args. Accepts: page_title, menu_title, menu_slug, capability (default edit_posts), parent_slug (sub-page), position, icon_url, redirect (bool), post_id (target id for field values, default "options"), autoload (bool, Pro), update_button, updated_message. Required for add-options-page and add-options-sub-page.',
						'additionalProperties' => true,
					],
					'menu_title'  => [
						'type'        => 'string',
						'description' => 'New title for set-options-page-menu (renames the parent "Options" menu).',
					],
					'page_id'     => [
						'type'        => 'string',
						'description' => 'ACF UI options page key like "ui_options_page_xyz" or numeric post ID. Required for the *-ui-options-page CRUD actions.',
					],
				],
			],
			'execute_callback'    => [ $this, 'execute' ],
			'permission_callback' => [ $this, 'authorize' ],
			'meta'                => [
				'annotations'  => [
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
				],
				'show_in_rest' => true,
			],
		];
	}

	public function authorize( array $input = [] ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function execute( array $input ): array {
		AcfRuntime::require_pro_options_pages();
		$action = (string) ( $input['action'] ?? '' );

		switch ( $action ) {
			case 'list-options-pages':
				$pages = (array) acf_get_options_pages();
				$ui    = function_exists( 'acf_get_acf_ui_options_pages' ) ? (array) acf_get_acf_ui_options_pages() : [];
				return [ 'pages' => array_values( $pages ), 'ui_pages' => $ui, 'count' => count( $pages ) + count( $ui ) ];

			case 'add-options-page':
				$page = (array) ( $input['page'] ?? [] );
				if ( empty( $page ) ) {
					throw new \RuntimeException( 'page is required for add-options-page.' );
				}
				$saved = acf_add_options_page( $page );
				return [ 'added' => true, 'page' => is_array( $saved ) ? $saved : $page ];

			case 'add-options-sub-page':
				$page = (array) ( $input['page'] ?? [] );
				if ( empty( $page ) ) {
					throw new \RuntimeException( 'page is required for add-options-sub-page.' );
				}
				if ( ! function_exists( 'acf_add_options_sub_page' ) ) {
					throw new \RuntimeException( 'acf_add_options_sub_page is unavailable on this ACF version.' );
				}
				$saved = acf_add_options_sub_page( $page );
				return [ 'added' => true, 'page' => is_array( $saved ) ? $saved : $page ];

			case 'set-options-page-menu':
				if ( ! function_exists( 'acf_set_options_page_menu' ) ) {
					throw new \RuntimeException( 'acf_set_options_page_menu is unavailable on this ACF version.' );
				}
				$title = (string) ( $input['menu_title'] ?? 'Options' );
				acf_set_options_page_menu( $title );
				return [ 'updated' => true, 'menu_title' => $title ];

			case 'get-ui-options-page':
				$id  = $this->require_id( $input, 'page_id', $action );
				$pos = $this->ui_options_page_helpers();
				if ( ! function_exists( $pos['get'] ) ) {
					throw new \RuntimeException( 'UI options pages require ACF Pro 6.2+.' );
				}
				$page = $pos['get']( $id );
				if ( ! is_array( $page ) ) {
					throw new \RuntimeException( sprintf( 'ACF UI options page "%s" not found.', $id ) );
				}
				return [ 'page' => $page ];

			case 'create-ui-options-page':
				$page = (array) ( $input['page'] ?? [] );
				if ( empty( $page ) ) {
					throw new \RuntimeException( 'page is required for create-ui-options-page.' );
				}
				$pos = $this->ui_options_page_helpers();
				if ( ! function_exists( $pos['update'] ) ) {
					throw new \RuntimeException( 'UI options pages require ACF Pro 6.2+.' );
				}
				$saved = $pos['update']( $page );
				if ( ! is_array( $saved ) ) {
					throw new \RuntimeException( 'ACF rejected the UI options page payload.' );
				}
				return [ 'created' => true, 'page' => $saved ];

			case 'update-ui-options-page':
				$id  = $this->require_id( $input, 'page_id', $action );
				$pos = $this->ui_options_page_helpers();
				if ( ! function_exists( $pos['get'] ) || ! function_exists( $pos['update'] ) ) {
					throw new \RuntimeException( 'UI options pages require ACF Pro 6.2+.' );
				}
				$current = $pos['get']( $id );
				if ( ! is_array( $current ) ) {
					throw new \RuntimeException( sprintf( 'ACF UI options page "%s" not found.', $id ) );
				}
				$patch        = (array) ( $input['page'] ?? [] );
				$merged       = array_merge( $current, $patch );
				$merged['key'] = $current['key'] ?? ( $merged['key'] ?? '' );
				$merged['ID']  = $current['ID']  ?? ( $merged['ID']  ?? 0 );
				$saved        = $pos['update']( $merged );
				return [ 'updated' => true, 'page' => is_array( $saved ) ? $saved : null ];

			case 'delete-ui-options-page':
				$id  = $this->require_id( $input, 'page_id', $action );
				$pos = $this->ui_options_page_helpers();
				if ( ! function_exists( $pos['delete'] ) ) {
					throw new \RuntimeException( 'UI options pages require ACF Pro 6.2+.' );
				}
				$ok = (bool) $pos['delete']( $id );
				return [ 'deleted' => $ok ];
		}

		throw new \RuntimeException( sprintf( 'Unknown action "%s".', $action ) );
	}

	/**
	 * @param array<string, mixed> $input
	 */
	private function require_id( array $input, string $key, string $action ): string {
		$id = (string) ( $input[ $key ] ?? '' );
		if ( '' === $id ) {
			throw new \RuntimeException( sprintf( '%s is required for %s.', $key, $action ) );
		}
		return $id;
	}

	/** @return array{get:string,update:string,delete:string} */
	private function ui_options_page_helpers(): array {
		return [
			'get'    => 'acf_get_ui_options_page',
			'update' => 'acf_update_ui_options_page',
			'delete' => 'acf_delete_ui_options_page',
		];
	}
}
