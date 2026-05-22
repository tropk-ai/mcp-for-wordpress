<?php
/**
 * Cron abilities for the Abilities API.
 *
 * Registers 5 abilities under the `cron/*` namespace covering listing,
 * scheduling, unscheduling, manually running and inspecting available
 * recurrences.
 *
 * @package Tropk\Mcp\Extras
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_abilities_api_init',
	static function (): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'tropk-cron/list',
			[
				'label'               => 'Cron: list scheduled events',
     'category'            => 'tropk-core',
				'description'         => 'List all scheduled cron events with hook, next_run, schedule, args.',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					$crons = _get_cron_array();
					$out   = [];
					foreach ( $crons as $timestamp => $hooks ) {
						foreach ( $hooks as $hook => $events ) {
							foreach ( $events as $key => $event ) {
								$out[] = [
									'hook'      => $hook,
									'next_run'  => (int) $timestamp,
									'next_iso'  => gmdate( 'c', (int) $timestamp ),
									'schedule'  => $event['schedule'] ?? null,
									'interval'  => $event['interval'] ?? null,
									'args'      => $event['args'] ?? [],
									'signature' => $key,
								];
							}
						}
					}
					return [ 'events' => $out, 'count' => count( $out ) ];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-cron/get-schedules',
			[
				'label'               => 'Cron: list registered schedules',
     'category'            => 'tropk-core',
				'description'         => 'List registered cron recurrences (hourly, twicedaily, daily, weekly, plus custom).',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					return [ 'schedules' => wp_get_schedules() ];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-cron/schedule',
			[
				'label'               => 'Cron: schedule event',
     'category'            => 'tropk-core',
				'description'         => 'Schedule a one-off or recurring cron event.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'hook' ],
					'properties' => [
						'hook'       => [ 'type' => 'string' ],
						'recurrence' => [ 'type' => 'string', 'description' => 'Schedule slug (hourly, daily, ...). Omit for single event.' ],
						'timestamp'  => [ 'type' => 'integer', 'description' => 'UNIX timestamp for first run. Default now+60s.' ],
						'args'       => [ 'type' => 'array' ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$hook       = (string) $input['hook'];
					$timestamp  = (int) ( $input['timestamp'] ?? time() + 60 );
					$args       = (array) ( $input['args'] ?? [] );
					$recurrence = (string) ( $input['recurrence'] ?? '' );
					if ( '' === $recurrence ) {
						$ok = wp_schedule_single_event( $timestamp, $hook, $args );
					} else {
						$ok = wp_schedule_event( $timestamp, $recurrence, $hook, $args );
					}
					if ( false === $ok || is_wp_error( $ok ) ) {
						$msg = is_wp_error( $ok ) ? $ok->get_error_message() : 'Scheduling failed.';
						throw new \RuntimeException( $msg );
					}
					return [ 'scheduled' => true, 'hook' => $hook, 'timestamp' => $timestamp, 'recurrence' => $recurrence ?: null ];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => false ] ],
			]
		);

		wp_register_ability(
			'tropk-cron/unschedule',
			[
				'label'               => 'Cron: unschedule event',
     'category'            => 'tropk-core',
				'description'         => 'Unschedule a specific event (by hook + args) or clear all events for a hook.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'hook' ],
					'properties' => [
						'hook' => [ 'type' => 'string' ],
						'args' => [ 'type' => 'array' ],
						'all'  => [ 'type' => 'boolean', 'default' => false ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$hook = (string) $input['hook'];
					if ( ! empty( $input['all'] ) ) {
						$count = wp_clear_scheduled_hook( $hook );
						return [ 'cleared' => true, 'count' => (int) $count ];
					}
					$args = (array) ( $input['args'] ?? [] );
					$ts   = wp_next_scheduled( $hook, $args );
					if ( ! $ts ) {
						return [ 'cleared' => false, 'reason' => 'No matching event scheduled.' ];
					}
					$ok = wp_unschedule_event( $ts, $hook, $args );
					return [ 'cleared' => (bool) $ok, 'timestamp' => $ts ];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'destructive' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-cron/run',
			[
				'label'               => 'Cron: trigger event now',
     'category'            => 'tropk-core',
				'description'         => 'Run a cron hook immediately (in the current process).',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'hook' ],
					'properties' => [
						'hook' => [ 'type' => 'string' ],
						'args' => [ 'type' => 'array' ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$hook = (string) $input['hook'];
					$args = (array) ( $input['args'] ?? [] );
					do_action_ref_array( $hook, $args );
					return [ 'ran' => true, 'hook' => $hook ];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => false ] ],
			]
		);
	},
	20
);
