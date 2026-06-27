<?php
declare(strict_types=1);

namespace Tropk\Mcp\Elementor;

/**
 * Runtime detection + accessors for Elementor V4's design-system stores
 * (Global Classes and Variables).
 *
 * Both stores moved through several internal layouts during Elementor 4.x
 * development (kit meta blob → CPT per class → Variables_Repository →
 * Storage\Repository). We never poke their post-meta keys directly: we
 * always go through the official Repository classes when they are loaded,
 * which guarantees ordering, label maps, watermark and cache invalidation
 * stay consistent with the editor.
 *
 * This class is the single point of detection so the abilities themselves
 * stay tiny and the test suite has one façade to mock.
 */
final class ElementorRuntime {

	private const PLUGIN_CLASS         = '\\Elementor\\Plugin';
	private const KIT_CLASS            = '\\Elementor\\Core\\Kits\\Documents\\Kit';
	private const GLOBAL_CLASSES_CLASS = '\\Elementor\\Modules\\GlobalClasses\\Global_Classes_Repository';
	private const VARIABLES_CLASS      = '\\Elementor\\Modules\\Variables\\Storage\\Repository';

	/** Is Elementor loaded at all? */
	public static function is_loaded(): bool {
		return class_exists( self::PLUGIN_CLASS );
	}

	/**
	 * Active Kit document. The Variables repository requires it and the
	 * Global Classes repository uses it for the order/labels store on the
	 * frontend channel.
	 */
	public static function active_kit(): ?object {
		if ( ! self::is_loaded() ) {
			return null;
		}
		$plugin = \Elementor\Plugin::$instance ?? null;
		if ( ! $plugin || empty( $plugin->kits_manager ) ) {
			return null;
		}
		$kit = $plugin->kits_manager->get_active_kit();
		return is_object( $kit ) ? $kit : null;
	}

	/** Active Kit post id, or 0 if Elementor isn't loaded or no Kit exists. */
	public static function active_kit_id(): int {
		return (int) get_option( 'elementor_active_kit', 0 );
	}

	/**
	 * Build a Global_Classes_Repository instance, or null when the class
	 * is not loaded (pre-V4 Elementor, or the module disabled).
	 */
	public static function global_classes(): ?object {
		if ( ! class_exists( self::GLOBAL_CLASSES_CLASS ) ) {
			return null;
		}
		$class = self::GLOBAL_CLASSES_CLASS;
		return new $class();
	}

	/**
	 * Build a Variables Storage\Repository bound to the active Kit. Both
	 * the class AND a Kit must be available — the constructor signature
	 * declares Kit non-nullable.
	 */
	public static function variables(): ?object {
		if ( ! class_exists( self::VARIABLES_CLASS ) ) {
			return null;
		}
		$kit = self::active_kit();
		if ( ! $kit instanceof \Elementor\Core\Kits\Documents\Kit ) {
			return null;
		}
		$class = self::VARIABLES_CLASS;
		return new $class( $kit );
	}

	/** Throw a uniform error when a V4 store is missing. */
	public static function require_global_classes(): object {
		$repo = self::global_classes();
		if ( null === $repo ) {
			throw new \RuntimeException( 'Elementor V4 Global Classes are not available on this site. Update Elementor to 4.x or enable the Atomic Editor experiment.' );
		}
		return $repo;
	}

	/** Throw a uniform error when a V4 store is missing. */
	public static function require_variables(): object {
		$repo = self::variables();
		if ( null === $repo ) {
			throw new \RuntimeException( 'Elementor V4 Variables are not available on this site. Update Elementor to 4.x or enable the Atomic Editor experiment.' );
		}
		return $repo;
	}
}
