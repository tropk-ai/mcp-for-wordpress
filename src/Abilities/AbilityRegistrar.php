<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities;

final class AbilityRegistrar {

	public const CATEGORY = 'tropk-core';
	public const NAMESPACE_PREFIX = 'tropk-core';

	/**
	 * Every ability shipped by the plugin under our own `tropk-core/*`
	 * namespace. One PHP class per ability, located in its vertical
	 * folder (Posts, Comments, Users, Menus, Media, Plugins, RankMath,
	 * Elementor, …). Add a new ability by dropping a new class file and
	 * appending its class-string here — or by hooking the
	 * `tropk_mcp_abilities` filter without modifying the plugin.
	 *
	 * @var array<int, class-string<Ability>>
	 */
	private array $abilities = [
		SiteInfoAbility::class,
		Seo\SeoGetHeadAbility::class,
		Seo\SeoAuditOnpageAbility::class,
		Acf\AcfFieldAbility::class,
		Acf\AcfFieldGroupAbility::class,
		Acf\AcfPostTypeAbility::class,
		Acf\AcfTaxonomyAbility::class,
		Ilj\IljGetKeywordsAbility::class,
		Ilj\IljSetKeywordsAbility::class,
		Ilj\IljInspectIndexAbility::class,
		Ilj\IljFindOrphansAbility::class,
		Perf\PerfPurgeCacheAbility::class,
		// Posts CRUD
		Posts\PostsListAbility::class,
		Posts\PostsGetAbility::class,
		Posts\PostsCreateAbility::class,
		Posts\PostsUpdateAbility::class,
		Posts\PostsDeleteAbility::class,
		Posts\PostsUpdateMetaAbility::class,
		// Comments
		Comments\CommentsListAbility::class,
		Comments\CommentsGetAbility::class,
		Comments\CommentsCreateAbility::class,
		Comments\CommentsUpdateStatusAbility::class,
		Comments\CommentsDeleteAbility::class,
		Comments\CommentsReplyAbility::class,
		// Users
		Users\UsersListAbility::class,
		Users\UsersGetAbility::class,
		Users\UsersCreateAbility::class,
		Users\UsersUpdateAbility::class,
		Users\UsersDeleteAbility::class,
		// Menus
		Menus\MenusListAbility::class,
		Menus\MenusGetItemsAbility::class,
		Menus\MenusCreateAbility::class,
		Menus\MenusAddItemAbility::class,
		Menus\MenusUpdateItemAbility::class,
		Menus\MenusDeleteItemAbility::class,
		Menus\MenusAssignLocationAbility::class,
		// Media
		Media\MediaListAbility::class,
		Media\MediaGetAbility::class,
		Media\MediaUploadFromUrlAbility::class,
		Media\MediaUploadBase64Ability::class,
		Media\MediaUpdateAbility::class,
		Media\MediaDeleteAbility::class,
		// Options
		Options\OptionsListAbility::class,
		Options\OptionsGetAbility::class,
		Options\OptionsUpdateAbility::class,
		// Meta
		Meta\MetaUpdatePostMetaAbility::class,
		Meta\MetaDeletePostMetaAbility::class,
		// System
		System\SystemDebugLogAbility::class,
		System\SystemGetTransientAbility::class,
		System\SystemToggleDebugAbility::class,
		// Widgets (classic)
		Widgets\WidgetsListAvailableAbility::class,
		Widgets\WidgetsListSidebarsAbility::class,
		Widgets\WidgetsGetSidebarAbility::class,
		// Plugins
		Plugins\PluginsListAbility::class,
		Plugins\PluginsActivateAbility::class,
		Plugins\PluginsDeactivateAbility::class,
		Plugins\PluginsDeleteAbility::class,
		Plugins\PluginsInstallDirectoryAbility::class,
		// Taxonomies
		Taxonomies\TaxonomiesAssociateAbility::class,
		// Elementor — one file per tool. Atomic widgets (V4) are detected
		// and treated as opaque: their JSON is preserved verbatim and they
		// are skipped by text replacements.
		Elementor\ElementorListPagesAbility::class,
		Elementor\ElementorGetPageOutlineAbility::class,
		Elementor\ElementorListWidgetsAbility::class,
		Elementor\ElementorGetWidgetAbility::class,
		Elementor\ElementorClonePageAbility::class,
		Elementor\ElementorReplaceTextAbility::class,
		Elementor\ElementorUpdateWidgetSettingAbility::class,
		Elementor\ElementorDeleteWidgetAbility::class,
		Elementor\ElementorFlushCssAbility::class,
		Elementor\ElementorGetGlobalColorsAbility::class,
		Elementor\ElementorGetGlobalFontsAbility::class,
		Elementor\ElementorGetActiveKitAbility::class,
		Elementor\ElementorListTemplatesAbility::class,
		Elementor\ElementorAddContainerAbility::class,
		Elementor\ElementorListConditionsAbility::class,
		Elementor\ElementorEnablePageAbility::class,
		Elementor\ElementorDisablePageAbility::class,
		Elementor\ElementorGetTemplateDataAbility::class,
		Elementor\ElementorFindByTypeAbility::class,
		Elementor\ElementorReplaceImageAbility::class,
		Elementor\ElementorRegeneratePageIdsAbility::class,
		Elementor\ElementorExportPageAbility::class,
		Elementor\ElementorImportPageAbility::class,
		// Rank Math SEO
		RankMath\RankMathGetMetaAbility::class,
		RankMath\RankMathUpdateMetaAbility::class,
		RankMath\RankMathBulkGetMetaAbility::class,
		RankMath\RankMathCreateRedirectionAbility::class,
		RankMath\RankMathDeleteRedirectionsAbility::class,
		RankMath\RankMathClear404LogsAbility::class,
		RankMath\RankMathDelete404LogsAbility::class,
		RankMath\RankMathListRedirectionsAbility::class,
		RankMath\RankMathList404LogsAbility::class,
		// Content (extra CRUD beyond posts/*)
		Content\ContentCreateCategoryAbility::class,
		Content\ContentCreateTagAbility::class,
		Content\ContentDeletePageAbility::class,
		Content\ContentGetNextPostAbility::class,
		Content\ContentBulkCreatePostsAbility::class,
		Content\ContentBulkDeletePostsAbility::class,
		Content\ContentBulkUpdatePostsAbility::class,
		// More Plugins + Comments
		Plugins\PluginsListUpdatesAbility::class,
		Plugins\PluginsSearchDirectoryAbility::class,
		Comments\CommentsApproveAbility::class,
		Comments\CommentsBulkApproveAbility::class,
		Comments\CommentsBulkDeleteAbility::class,
		// Pages (separate from Posts)
		Pages\PagesListAbility::class,
		Pages\PagesCreateAbility::class,
		Pages\PagesUpdateAbility::class,
		// Users roles
		Users\UsersAddRoleAbility::class,
		Users\UsersRemoveRoleAbility::class,
		// Meta read
		Meta\MetaGetPostMetaAbility::class,
		// Elementor audits + structural extras
		Elementor\ElementorAuditTextHierarchyAbility::class,
		Elementor\ElementorAuditColumnBalanceAbility::class,
		Elementor\ElementorAuditColumnDominanceAbility::class,
		Elementor\ElementorAuditColumnAlignmentRhythmAbility::class,
		Elementor\ElementorListContainersAbility::class,
		Elementor\ElementorCountAtomicWidgetsAbility::class,
		Elementor\ElementorListPagesWithStatsAbility::class,
		Elementor\ElementorFindContainingTextAbility::class,
		Elementor\ElementorGetPageSettingsAbility::class,
		Elementor\ElementorUpdatePageSettingsAbility::class,
		Elementor\ElementorApplyTextHierarchyAbility::class,
		// Media expansion
		Media\MediaBulkDeleteAbility::class,
		Media\MediaFindUnusedAbility::class,
		Media\MediaSetFeaturedImageAbility::class,
		// Elementor (page-rendering + Theme Builder)
		Elementor\ElementorGetPageHtmlAbility::class,
		Elementor\ElementorListThemeBuilderAbility::class,
		Elementor\ElementorSetTemplateConditionsAbility::class,
		Elementor\ElementorDuplicateWidgetAbility::class,
		Elementor\ElementorAddWidgetAbility::class,
		// Content extra
		Content\ContentGetBySlugAbility::class,
		Content\ContentSchedulePostAbility::class,
		// Taxonomies full set
		Taxonomies\TaxonomiesListAbility::class,
		Taxonomies\TaxonomiesListTermsAbility::class,
		Taxonomies\TaxonomiesAssignTermAbility::class,
		Taxonomies\TaxonomiesUpdateTermAbility::class,
		Taxonomies\TaxonomiesDeleteTermAbility::class,
		// Performance core
		Performance\PerfFlushObjectCacheAbility::class,
		Performance\PerfFlushRewriteRulesAbility::class,
		Performance\PerfPurgeAllTransientsAbility::class,
		Performance\PerfGetCacheProviderAbility::class,
		// Cron
		Cron\CronListAbility::class,
		Cron\CronGetSchedulesAbility::class,
		Cron\CronRunHookAbility::class,
		Cron\CronScheduleAbility::class,
		Cron\CronUnscheduleAbility::class,
		// Security
		Security\SecurityListAppPasswordsAbility::class,
		Security\SecurityCountFailedLoginsAbility::class,
		// Misc extras
		Elementor\ElementorListAtomicWidgetsAbility::class,
		Posts\PostsDuplicateAbility::class,
		// Database (read-only)
		Database\DbListTablesAbility::class,
		Database\DbDescribeTableAbility::class,
		Database\DbPreviewTableAbility::class,
		Database\DbExecuteSelectAbility::class,
		// Shortcodes
		Shortcodes\ShortcodesListAbility::class,
		Shortcodes\ShortcodesRenderAbility::class,
		Shortcodes\ShortcodesExistsAbility::class,
		// Gutenberg blocks
		Blocks\BlocksListTypesAbility::class,
		Blocks\BlocksParseAbility::class,
		Blocks\BlocksSerializeAbility::class,
		Blocks\BlocksGetPatternsAbility::class,
		// Theme
		Theme\ThemeGetActiveAbility::class,
		Theme\ThemeListInstalledAbility::class,
		Theme\ThemeSwitchAbility::class,
		Theme\ThemeGetCustomizerAbility::class,
		Theme\ThemeSetCustomizerAbility::class,
		// WooCommerce
		WooCommerce\WooStatusAbility::class,
		WooCommerce\WooListProductsAbility::class,
		WooCommerce\WooListOrdersAbility::class,
		// Bulk
		Bulk\BulkSetMetaAbility::class,
		// More Elementor audits + utilities
		Elementor\ElementorAuditTypographyAbility::class,
		Elementor\ElementorAuditSpacingAbility::class,
		Elementor\ElementorAuditLinkDensityAbility::class,
		Elementor\ElementorAuditImageAspectAbility::class,
		Elementor\ElementorAuditColorsAbility::class,
		Elementor\ElementorGetPageCssAbility::class,
		Elementor\ElementorGetWidgetSchemaAbility::class,
		Elementor\ElementorAuditPageAbility::class,
		Elementor\ElementorSwapWidgetTypeAbility::class,
		Elementor\ElementorSaveAsTemplateAbility::class,
		// Elementor introspection / link / responsive
		Elementor\ElementorListHeadingsAbility::class,
		Elementor\ElementorListImagesAbility::class,
		Elementor\ElementorListButtonsAbility::class,
		Elementor\ElementorSetWidgetLinkAbility::class,
		Elementor\ElementorCountSectionsAbility::class,
		Elementor\ElementorListLinksAbility::class,
		Elementor\ElementorCheckBrokenLinksAbility::class,
		Elementor\ElementorWordCountAbility::class,
		Elementor\ElementorListResponsiveSettingsAbility::class,
		Elementor\ElementorHasFormWidgetAbility::class,
		Elementor\ElementorAuditMobileResponsiveAbility::class,
		Elementor\ElementorListVideosAbility::class,
		Elementor\ElementorListIconsAbility::class,
		Elementor\ElementorListAnimationsAbility::class,
		Elementor\ElementorListCustomCssAbility::class,
		Elementor\ElementorListIdsAbility::class,
		// Pages tree
		Pages\PagesGetTreeAbility::class,
		// Users roles
		Users\UsersListRolesAbility::class,
		// Comments count
		Comments\CommentsCountAbility::class,
		// Elementor: 98 abilities ported from bjornfix-elem + msrbuilds-elem
		Elementor\ElementorAddCodeSnippetAbility::class,
		Elementor\ElementorAddCustomCssAbility::class,
		Elementor\ElementorAddCustomJsAbility::class,
		Elementor\ElementorAddStockImageAbility::class,
		Elementor\ElementorApplyTemplateAbility::class,
		Elementor\ElementorAuditColumnNecessityAbility::class,
		Elementor\ElementorAuditColumnPatternsAbility::class,
		Elementor\ElementorAuditCompositionRhythmAbility::class,
		Elementor\ElementorAuditEmphasisDriftAbility::class,
		Elementor\ElementorAuditGenericComponentRepetitionAbility::class,
		Elementor\ElementorAuditGenericLayoutPatternsAbility::class,
		Elementor\ElementorAuditLayoutMechanismFitAbility::class,
		Elementor\ElementorAuditNativeWidgetOpportunitiesAbility::class,
		Elementor\ElementorAuditSectionRivalryAbility::class,
		Elementor\ElementorAuditSeparatorDisciplineAbility::class,
		Elementor\ElementorAuditSurfaceOveruseAbility::class,
		Elementor\ElementorBatchUpdateAbility::class,
		Elementor\ElementorBuildPageAbility::class,
		Elementor\ElementorClearCacheAbility::class,
		Elementor\ElementorCloneDataAbility::class,
		Elementor\ElementorCopyLaneSettingsAbility::class,
		Elementor\ElementorCopyRowBalanceAbility::class,
		Elementor\ElementorCreateCustomCodeAbility::class,
		Elementor\ElementorCreatePageAbility::class,
		Elementor\ElementorCreatePopupAbility::class,
		Elementor\ElementorCreateTemplateAbility::class,
		Elementor\ElementorCreateThemeTemplateAbility::class,
		Elementor\ElementorDeleteCustomCodeAbility::class,
		Elementor\ElementorDeleteElementAbility::class,
		Elementor\ElementorDeleteFormSubmissionAbility::class,
		Elementor\ElementorDeletePageContentAbility::class,
		Elementor\ElementorDeleteTemplateAbility::class,
		Elementor\ElementorDuplicateElementAbility::class,
		Elementor\ElementorDuplicateTemplateAbility::class,
		Elementor\ElementorEmptyTrashAbility::class,
		Elementor\ElementorEnforceBoundaryCoherenceAbility::class,
		Elementor\ElementorEvaluateDesignAbility::class,
		Elementor\ElementorEvaluateRenderContextAbility::class,
		Elementor\ElementorExportTemplateAbility::class,
		Elementor\ElementorExtractDesignTokensAbility::class,
		Elementor\ElementorFindElementAbility::class,
		Elementor\ElementorFindElementsAbility::class,
		Elementor\ElementorFixVisibleGapRhythmAbility::class,
		Elementor\ElementorGetContainerSchemaAbility::class,
		Elementor\ElementorGetCustomCodeAbility::class,
		Elementor\ElementorGetDataAbility::class,
		Elementor\ElementorGetElementAbility::class,
		Elementor\ElementorGetElementSettingsAbility::class,
		Elementor\ElementorGetFormSubmissionAbility::class,
		Elementor\ElementorGetKitSettingsAbility::class,
		Elementor\ElementorGetMaintenanceModeAbility::class,
		Elementor\ElementorGetOfficialPatternGuidanceAbility::class,
		Elementor\ElementorGetOfficialWidgetCatalogAbility::class,
		Elementor\ElementorGetPageStructureAbility::class,
		Elementor\ElementorGetStyleGuideAbility::class,
		Elementor\ElementorGetTemplateAbility::class,
		Elementor\ElementorGetThemeBuilderConditionsAbility::class,
		Elementor\ElementorGetThemeContextAbility::class,
		Elementor\ElementorImageWidgetToBackgroundContainerAbility::class,
		Elementor\ElementorImportTemplateAbility::class,
		Elementor\ElementorListCodeSnippetsAbility::class,
		Elementor\ElementorListCustomCodeAbility::class,
		Elementor\ElementorListDynamicTagsAbility::class,
		Elementor\ElementorListExperimentsAbility::class,
		Elementor\ElementorListFormSubmissionsAbility::class,
		Elementor\ElementorListGlobalWidgetsAbility::class,
		Elementor\ElementorListKitsAbility::class,
		Elementor\ElementorMergeElementSettingsAbility::class,
		Elementor\ElementorMoveElementAbility::class,
		Elementor\ElementorNormalizeCampaignDetailPageAbility::class,
		Elementor\ElementorNormalizeResponsiveValuesAbility::class,
		Elementor\ElementorNormalizeSectionSpacingRhythmAbility::class,
		Elementor\ElementorPatchDataAbility::class,
		Elementor\ElementorReorderElementsAbility::class,
		Elementor\ElementorReplaceUrlsAbility::class,
		Elementor\ElementorResetNegativeMarginsSubtreeAbility::class,
		Elementor\ElementorRestoreTemplateAbility::class,
		Elementor\ElementorScoreDistinctivenessAbility::class,
		Elementor\ElementorSearchImagesAbility::class,
		Elementor\ElementorSetActiveKitAbility::class,
		Elementor\ElementorSetDynamicTagAbility::class,
		Elementor\ElementorSetPopupSettingsAbility::class,
		Elementor\ElementorSideloadImageAbility::class,
		Elementor\ElementorSuggestDesignFixesAbility::class,
		Elementor\ElementorSyncComponentVariantAbility::class,
		Elementor\ElementorUpdateContainerAbility::class,
		Elementor\ElementorUpdateCustomCodeAbility::class,
		Elementor\ElementorUpdateDataAbility::class,
		Elementor\ElementorUpdateElementAbility::class,
		Elementor\ElementorUpdateExperimentAbility::class,
		Elementor\ElementorUpdateGlobalColorsAbility::class,
		Elementor\ElementorUpdateGlobalTypographyAbility::class,
		Elementor\ElementorUpdateKitSettingsAbility::class,
		Elementor\ElementorUpdateMaintenanceModeAbility::class,
		Elementor\ElementorUpdateThemeBuilderConditionsAbility::class,
		Elementor\ElementorUpdateWidgetAbility::class,
		Elementor\ElementorUploadSvgIconAbility::class,
		Elementor\ElementorZeroContainerPaddingSubtreeAbility::class,
		// Elementor: per-widget creation shorthands — one ability per widget
		// type so callers can do "add a heading", "add an image", "add a
		// button" without manually specifying widgetType + settings via the
		// generic elementor-add-widget. Covers free, Pro and atomic (V4 e-
		// and legacy a-) widgets. Abilities are always declared; Pro widgets
		// will obviously render as empty placeholders if Elementor Pro is
		// not installed, but the tool itself is still callable so MCP
		// clients can advertise the full surface.
		Elementor\Widgets\ElementorAddAccordionAbility::class,
		Elementor\Widgets\ElementorAddAlertAbility::class,
		Elementor\Widgets\ElementorAddAudioAbility::class,
		Elementor\Widgets\ElementorAddBasicGalleryAbility::class,
		Elementor\Widgets\ElementorAddButtonAbility::class,
		Elementor\Widgets\ElementorAddCounterAbility::class,
		Elementor\Widgets\ElementorAddDividerAbility::class,
		Elementor\Widgets\ElementorAddGoogleMapsAbility::class,
		Elementor\Widgets\ElementorAddHeadingAbility::class,
		Elementor\Widgets\ElementorAddHtmlAbility::class,
		Elementor\Widgets\ElementorAddIconAbility::class,
		Elementor\Widgets\ElementorAddIconBoxAbility::class,
		Elementor\Widgets\ElementorAddIconListAbility::class,
		Elementor\Widgets\ElementorAddImageAbility::class,
		Elementor\Widgets\ElementorAddImageBoxAbility::class,
		Elementor\Widgets\ElementorAddImageCarouselAbility::class,
		Elementor\Widgets\ElementorAddImageGalleryAbility::class,
		Elementor\Widgets\ElementorAddMenuAnchorAbility::class,
		Elementor\Widgets\ElementorAddProgressAbility::class,
		Elementor\Widgets\ElementorAddRatingAbility::class,
		Elementor\Widgets\ElementorAddReadMoreAbility::class,
		Elementor\Widgets\ElementorAddShortcodeAbility::class,
		Elementor\Widgets\ElementorAddSocialIconsAbility::class,
		Elementor\Widgets\ElementorAddSoundCloudAbility::class,
		Elementor\Widgets\ElementorAddSpacerAbility::class,
		Elementor\Widgets\ElementorAddStarRatingAbility::class,
		Elementor\Widgets\ElementorAddTabsAbility::class,
		Elementor\Widgets\ElementorAddTestimonialAbility::class,
		Elementor\Widgets\ElementorAddTextEditorAbility::class,
		Elementor\Widgets\ElementorAddTextPathAbility::class,
		Elementor\Widgets\ElementorAddToggleAbility::class,
		Elementor\Widgets\ElementorAddVideoAbility::class,
		// Elementor Pro shorthands
		Elementor\Widgets\ElementorAddAnimatedHeadlineAbility::class,
		Elementor\Widgets\ElementorAddBlockquoteAbility::class,
		Elementor\Widgets\ElementorAddCallToActionAbility::class,
		Elementor\Widgets\ElementorAddCodeHighlightAbility::class,
		Elementor\Widgets\ElementorAddFlipBoxAbility::class,
		Elementor\Widgets\ElementorAddFormAbility::class,
		Elementor\Widgets\ElementorAddGalleryAbility::class,
		Elementor\Widgets\ElementorAddLoginAbility::class,
		Elementor\Widgets\ElementorAddLottieAbility::class,
		Elementor\Widgets\ElementorAddMediaCarouselAbility::class,
		Elementor\Widgets\ElementorAddNavMenuAbility::class,
		Elementor\Widgets\ElementorAddPaypalButtonAbility::class,
		Elementor\Widgets\ElementorAddPortfolioAbility::class,
		Elementor\Widgets\ElementorAddPostsAbility::class,
		Elementor\Widgets\ElementorAddPriceListAbility::class,
		Elementor\Widgets\ElementorAddPriceTableAbility::class,
		Elementor\Widgets\ElementorAddReviewsAbility::class,
		Elementor\Widgets\ElementorAddSearchFormAbility::class,
		Elementor\Widgets\ElementorAddShareButtonsAbility::class,
		Elementor\Widgets\ElementorAddSlidesAbility::class,
		Elementor\Widgets\ElementorAddTableOfContentsAbility::class,
		Elementor\Widgets\ElementorAddTestimonialCarouselAbility::class,
		Elementor\Widgets\ElementorAddVideoPlaylistAbility::class,
		Elementor\Widgets\ElementorAddWoocommerceProductAddToCartAbility::class,
		Elementor\Widgets\ElementorAddWoocommerceProductImagesAbility::class,
		Elementor\Widgets\ElementorAddWoocommerceProductMetaAbility::class,
		Elementor\Widgets\ElementorAddWoocommerceProductTitleAbility::class,
		Elementor\Widgets\ElementorAddWoocommerceProductsAbility::class,
		// Elementor atomic (V4) — e- prefix
		Elementor\Widgets\ElementorAddEButtonAbility::class,
		Elementor\Widgets\ElementorAddEDividerAbility::class,
		Elementor\Widgets\ElementorAddEHeadingAbility::class,
		Elementor\Widgets\ElementorAddEImageAbility::class,
		Elementor\Widgets\ElementorAddEParagraphAbility::class,
		Elementor\Widgets\ElementorAddESvgAbility::class,
		// Elementor atomic (legacy a- prefix)
		Elementor\Widgets\ElementorAddAButtonAbility::class,
		Elementor\Widgets\ElementorAddADividerAbility::class,
		Elementor\Widgets\ElementorAddAHeadingAbility::class,
		Elementor\Widgets\ElementorAddAImageAbility::class,
		Elementor\Widgets\ElementorAddAParagraphAbility::class,
		Elementor\Widgets\ElementorAddASvgAbility::class,
		// Divi 5 — one file per tool. Only Divi 5 (et_builder_version >= 5.0.0)
		// is supported; Divi 4 pages are rejected with a clear error.
		Divi\DiviListPagesAbility::class,
		Divi\DiviGetPageOutlineAbility::class,
		Divi\DiviGetPageStructureAbility::class,
		Divi\DiviListModulesAbility::class,
		Divi\DiviGetModuleAbility::class,
		Divi\DiviUpdateModuleSettingAbility::class,
		Divi\DiviDeleteModuleAbility::class,
		Divi\DiviClonePageAbility::class,
		Divi\DiviReplaceTextAbility::class,
		Divi\DiviFlushCssAbility::class,
	];

	public function register(): void {
		// WP 6.9 core ships Abilities API with the wp_-prefixed action names
		// (wp_abilities_api_init). The wordpress/abilities-api Composer package
		// uses the unprefixed names (abilities_api_init). Hook both so we work
		// regardless of whether core or the package wins the class_exists race.
		add_action( 'abilities_api_categories_init',    [ $this, 'register_category' ] );
		add_action( 'abilities_api_init',               [ $this, 'register_abilities' ] );
		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_category' ] );
		add_action( 'wp_abilities_api_init',            [ $this, 'register_abilities' ] );

		// Belt-and-braces: WP 6.9 / Abilities API 0.3.0 rejects any ability
		// whose category slug is not already registered. The action ordering
		// above SHOULD ensure categories land first, but if a third-party
		// plugin happens to call wp_get_ability() earlier in the request the
		// categories_init action may have fired without our hook attached
		// yet. So we ALSO register categories synchronously at plugins_loaded
		// priority 99 (the same slot our VendorLoader uses), where the
		// Abilities API is guaranteed to be loaded and the registry is
		// guaranteed to be fresh.
		add_action(
			'plugins_loaded',
			function (): void {
				$this->register_category();
			},
			99
		);

		// Eagerly initialise the registry now, while our callbacks are guaranteed
		// to be hooked. If we let mcp-adapter trigger initialization later from
		// inside create_server, any earlier wp_get_ability call from another
		// plugin would have fired abilities_api_init before our hook existed and
		// left our abilities silently unregistered.
		add_action(
			'init',
			static function (): void {
				if ( class_exists( '\\WP_Abilities_Registry' ) ) {
					\WP_Abilities_Registry::get_instance();
				}
			},
			1
		);
	}

	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$classes = (array) apply_filters( 'tropk_mcp_abilities', $this->abilities );

		foreach ( $classes as $class ) {
			if ( ! is_string( $class ) || ! class_exists( $class ) ) {
				continue;
			}
			$ability = new $class();
			if ( ! $ability instanceof Ability ) {
				continue;
			}
			$name = self::NAMESPACE_PREFIX . '/' . $ability->slug();
			// is_registered guard so the second action firing (we hook both
			// core-name and package-name) doesn't trigger duplicate-register
			// notices.
			if ( function_exists( 'wp_get_ability' ) && null !== wp_get_ability( $name ) ) {
				continue;
			}
			wp_register_ability( $name, $ability->definition() );
		}
	}

	public function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		// WP 6.9 / Abilities API 0.3.0 rejects ability registrations whose
		// `category` slug is not already registered. Pre-register every
		// category used by this plugin (our own abilities + the Extras
		// procedural files) so that downstream wp_register_ability calls
		// succeed regardless of order.
		$categories = [
			self::CATEGORY => [
				'label'       => __( 'MCP for WP', 'mcp-for-wordpress' ),
				'description' => __( 'Tools exposed by the MCP for WP plugin.', 'mcp-for-wordpress' ),
			],
		];

		foreach ( $categories as $slug => $args ) {
			if ( function_exists( 'wp_get_ability_category' ) && null !== wp_get_ability_category( $slug ) ) {
				continue;
			}
			wp_register_ability_category( $slug, $args );
		}
	}

	/**
	 * @return array<int, string>
	 */
	public static function registered_ability_names(): array {
		$instance = new self();
		$names    = [];
		foreach ( $instance->abilities as $class ) {
			if ( ! class_exists( $class ) ) {
				continue;
			}
			$ability = new $class();
			if ( $ability instanceof Ability ) {
				$names[] = self::NAMESPACE_PREFIX . '/' . $ability->slug();
			}
		}
		return $names;
	}
}
