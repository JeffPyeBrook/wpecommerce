<?php
/**
 * @package Make Plus
 */

/**
 * Class MAKEPLUS_Component_WPECommerce_Setup
 *
 * Integrate the WPECommerce plugin into Make's theme settings and Builder.
 *
 */
final class MAKEPLUS_Component_WPECommerce_Setup extends MAKEPLUS_Util_Modules implements MAKEPLUS_Util_HookInterface {
	/**
	 * An associative array of required modules.
	 *
	 * @since 1.7.0.
	 *
	 * @var array
	 */
	protected $dependencies = array(
		'mode'          => 'MAKEPLUS_Setup_ModeInterface',
		'compatibility' => 'MAKEPLUS_Compatibility_MethodsInterface',
		'wpec'          => 'WP_eCommerce',
	);

	/**
	 * The version of the WPECommerce plugin.
	 *
	 * @since 1.5.0.
	 *
	 * @var string|null    The version of the WPECommerce plugin.
	 */
	private $wpec_version = null;

	/**
	 * Indicator of whether the hook routine has been run.
	 *
	 * @since 1.7.0.
	 *
	 * @var bool
	 */
	private static $hooked = false;

	/**
	 * MAKEPLUS_Component_WPECommerce_Setup constructor.
	 *
	 * @since 1.7.0.
	 *
	 * @param MAKEPLUS_APIInterface|null $api
	 * @param array                      $modules
	 */
	public function __construct( MAKEPLUS_APIInterface $api = null, array $modules = array() ) {
		// Detect WPECommerce plugin version
		if ( defined( 'WPSC_VERSION' ) ) {
			$this->wpec_version = WPSC_VERSION;
		}

		// Load dependencies
		parent::__construct( $api, $modules );

		// Add the Make API if it's available
		if ( $this->mode()->has_make_api() ) {
			$this->add_module( 'theme', Make() );
		}
	}

	/**
	 * Hook into WordPress.
	 *
	 * @since 1.7.0.
	 *
	 * @return void
	 */
	public function hook() {
		if ( $this->is_hooked() ) {
			return;
		}

		add_filter( 'make_section_defaults', array( $this, 'section_defaults' ) );

		// Register section choices
		add_filter( 'make_section_choices', array( $this, 'section_choices' ), 10, 3 );

		// Passive mode. Only enable the shortcode.
		if ( 'active' !== $this->mode()->get_mode() ) {
			// Shortcode
			add_shortcode( 'ttfmp_wpecommerce_product_grid', array( $this, 'handle_shortcode' ) );
		}
		// Full functionality.
		else {
			// Shortcode
			add_shortcode( 'ttfmp_wpecommerce_product_grid', array( $this, 'handle_shortcode' ) );

			// Enqueue frontend
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ), 20 );

			// Define Shop view
			add_filter( 'makeplus_view_is_shop', array( $this, 'is_shop' ) );

			// Define Product view
			add_filter( 'makeplus_view_is_product', array( $this, 'is_product' ) );
			add_filter( 'makeplus_admin_view_is_product', array( $this, 'admin_is_product' ) );

			// E-Commerce support
			add_action( 'makeplus_components_loaded', array( $this, 'add_ecommerce_support' ) );

			// Layout: Shop description
			add_filter( 'makeplus_ecommerce_layoutshop_description', array( $this, 'layoutshop_description' ) );

			// Layout: Product description
			add_filter( 'makeplus_ecommerce_layoutproduct_description', array( $this, 'layoutproduct_description' ) );

			// Admin notice
			add_action( 'makeplus_notice_loaded', array( $this, 'admin_notice' ) );

			// Add the section styles and scripts
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ) );

			// Add Product Grid section settings
			add_filter( 'make_section_defaults', array( $this, 'section_defaults' ) );
			add_filter( 'make_section_choices', array( $this, 'section_choices' ), 10, 3 );

			// Add section
			if ( is_admin() ) {
				add_action( 'after_setup_theme', array( $this, 'register_product_grid_section' ), 11 );
				add_filter( 'make_get_section_json', array ( $this, 'get_section_json' ), 10, 1 );
			}
		}

		// Hooking has occurred.
		self::$hooked = true;
	}

	/**
	 * Check if the hook routine has been run.
	 *
	 * @since 1.7.0.
	 *
	 * @return bool
	 */
	public function is_hooked() {
		return self::$hooked;
	}

	/**
	 * Create a shortcode instance and return the rendered output.
	 *
	 * @since 1.7.0.
	 *
	 * @param array $atts
	 *
	 * @return string
	 */
	public function handle_shortcode( $atts ) {
		$shortcode = new MAKEPLUS_Component_WPECommerce_Shortcode( null, array() );
		return $shortcode->shortcode_product_grid( $atts );
	}

	/**
	 * Enqueue styles and scripts
	 *
	 * @since 1.0.0.
	 *
	 * @hooked action wp_enqueue_scripts
	 *
	 * @return void
	 */
	public function enqueue_frontend() {
		if ( function_exists( 'ttfmake_is_builder_page' ) && ttfmake_is_builder_page() ) {
			$sections = ttfmake_get_section_data( get_the_ID() );
			if ( ! empty( $sections ) ) {
				// Parse the sections included on the page.
				$section_types = wp_list_pluck( $sections, 'section-type' );
				$matched_sections = array_keys( $section_types, 'productgrid' );

				// Only enqueue if there is at least one Products section.
				if ( ! empty( $matched_sections ) ) {
					// Styles
						wp_enqueue_style(
							'makeplus-wpecommerce-frontend',
							makeplus_get_plugin_directory_uri() . 'css/wpecommerce/frontend.css',
							array(),
							MAKEPLUS_VERSION
						);

						// If current theme is a child theme of Make, load the stylesheet
						// before the child theme stylesheet so styles can be customized.
						if ( $this->has_module( 'theme' ) && is_child_theme() ) {
							$this->theme()->scripts()->add_dependency( 'make-main', 'makeplus-wpecommerce-frontend', 'style' );
					}
				}
			}
		}
	}

	/**
	 * Define the conditions for the view to be "Shop".
	 *
	 * @since 1.7.0.
	 *
	 * @hooked filter makeplus_view_is_shop
	 *
	 * @param bool $is_shop
	 *
	 * @return bool
	 */
	public function is_shop( $is_shop ) {
		return false;
		if (
			is_shop()
			||
			is_product_category()
			||
			is_product_tag()
		) {
			$is_shop = true;
		}

		return $is_shop;
	}

	/**
	 * Define the conditions for the view to be "Product".
	 *
	 * @since 1.7.0.
	 *
	 * @hooked filter makeplus_view_is_product
	 *
	 * @param bool $is_product
	 *
	 * @return bool
	 */
	public function is_product( $is_product ) {
		$post = get_post();
		$parent_post_type = ( $post instanceof WP_Post ) ? get_post_type( $post->post_parent ) : '';

		if (
				( get_post_type( $post ) == 'wpsc-product' )
				||
				( is_attachment() && 'wpsc-product' === $parent_post_type )
		) {
			$is_product = true;
		}

		return $is_product;
	}

	/**
	 * Define the conditions for the admin view to be "Product".
	 *
	 * @since 1.7.0.
	 *
	 * @hooked filter makeplus_admin_view_is_product
	 *
	 * @param bool $is_product
	 *
	 * @return bool
	 */
	public function admin_is_product( $is_product ) {
		global $typenow;

		if ( isset( $typenow ) && 'wpsc-product' === $typenow ) {
			$is_product = true;
		}

		return $is_product;
	}

	/**
	 * Add support for various features in the Ecommerce component.
	 *
	 * @since 1.2.0.
	 *
	 * @hooked action makeplus_components_loaded
	 *
	 * @return void
	 */
	public function add_ecommerce_support() {
		// Layout: Shop
		add_theme_support( 'makeplus-ecommerce-layoutshop' );

		// Layout: Product
		add_theme_support( 'makeplus-ecommerce-layoutproduct' );

		// Shop Sidebar
		add_theme_support( 'makeplus-ecommerce-sidebar' );

		// Highlight color
//		if ( version_compare( $this->wc_version, '2.3', '<' ) ) {
//			add_theme_support( 'makeplus-ecommerce-colorhighlight' );
//		}
	}

	/**
	 * Add a description to the Layout: Shop section.
	 *
	 * @since 1.2.0.
	 *
	 * @hooked filter makeplus_ecommerce_layoutshop_description
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	public function layoutshop_description( $text ) {
		$description = esc_html__( 'For WPECommerce, this view consists of product archives and related category and tag archives.', 'make-plus' );

		if ( '' !== $text ) {
			$text .= '<br />';
		}

		return $text . $description;
	}

	/**
	 * Add a description to the Layout: Product section.
	 *
	 * @since 1.2.0.
	 *
	 * @hooked filter makeplus_ecommerce_layoutproduct_description
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	public function layoutproduct_description( $text ) {
		$description = esc_html__( 'For WPECommerce, this view consists of single products.', 'make-plus' );

		if ( '' !== $text ) {
			$text .= '<br />';
		}

		return $text . $description;
	}

	/**
	 * Add admin notice.
	 *
	 * @since 1.5.0.
	 *
	 * @hooked action makeplus_notice_loaded
	 *
	 * @param MAKEPLUS_Admin_NoticeInterface $notice
	 *
	 * @return bool
	 */
	public function admin_notice( MAKEPLUS_Admin_NoticeInterface $notice ) {
		return false;
	}

	/**
	 * Enqueue the JS and CSS for the admin.
	 *
	 * @since 1.0.0.
	 *
	 * @hooked action admin_enqueue_scripts
	 *
	 * @param string $hook_suffix    The suffix for the screen.
	 *
	 * @return void
	 */
	public function admin_enqueue( $hook_suffix ) {
		// Have to be careful with this test because this function was introduced in Make 1.2.0.
		$post_type_supports_builder = ( function_exists( 'ttfmake_post_type_supports_builder' ) ) ? ttfmake_post_type_supports_builder( get_post_type() ) : false;
		// Only load resources if they are needed on the current page
		if (
			in_array( $hook_suffix, array( 'post.php', 'post-new.php' ) )
			&&
			( $post_type_supports_builder || 'page' === get_post_type() )
		) {
			$dependencies[] = 'ttfmake-builder';

			// Add the section CSS
			wp_enqueue_style(
				'makeplus-wpecommerce-sections',
				makeplus_get_plugin_directory_uri() . 'css/wpecommerce/sections.css',
				array(),
				MAKEPLUS_VERSION
			);

			wp_enqueue_script(
				'makeplus-productgrid-model',
				makeplus_get_plugin_directory_uri() . 'js/wpecommerce/builder-model.js',
				$dependencies,
				MAKEPLUS_VERSION,
				true
			);

			wp_enqueue_script(
				'makeplus-productgrid-view',
				makeplus_get_plugin_directory_uri() . 'js/wpecommerce/builder-view.js',
				$dependencies,
				MAKEPLUS_VERSION,
				true
			);
		}
	}

	private function get_defaults() {
		return array(
			'title' => '',
			'background-image' => '',
			'darken' => '',
			'background-style' => 'tile',
			'background-color' => '',
			'columns' => 3,
			'type' => 'all',
			'taxonomy' => 'all',
			'sortby' => 'menu_order',
			'count' => 9,
			'thumb' => 1,
			'price' => 1,
			'addcart' => 1,
			'state' => 'open',
			'full-width' => 0,
		);
	}

	/**
	 * Add new section defaults.
	 *
	 * @since 1.0.0.
	 *
	 * @hooked filter make_section_defaults
	 *
	 * @param array $defaults    The default section defaults.
	 *
	 * @return array             The augmented section defaults.
	 */
	public function section_defaults( $defaults ) {
		$defaults['productgrid'] = $this->get_defaults();

		return $defaults;
	}

	/**
	 * Add new section choices.
	 *
	 * @since 1.0.0.
	 *
	 * @hooked filter make_section_choices
	 *
	 * @param array  $choices         The existing choices.
	 * @param string $key             The key for the section setting.
	 * @param string $section_type    The section type.
	 *
	 * @return array                  The choices for the particular section_type / key combo.
	 */
	public function section_choices( $choices, $key, $section_type ) {
		if ( count( $choices ) > 1 || ! in_array( $section_type, array( 'wpecommerce-product-grid' ) ) ) {
			return $choices;
		}

		$choice_id = "$section_type-$key";

		switch ( $choice_id ) {
			case 'wpecommerce-product-grid-background-style' :
				$choices = array(
					'tile'  => __( 'Tile', 'make-plus' ),
					'cover' => __( 'Cover', 'make-plus' ),
				);
				break;
			case 'wpecommerce-product-grid-columns' :
				$choices = array(
					1 => __( '1', 'make-plus' ),
					2 => __( '2', 'make-plus' ),
					3 => __( '3', 'make-plus' ),
					4 => __( '4', 'make-plus' ),
				);
				break;
			case 'wpecommerce-product-grid-type' :
				$choices = array(
					'all' => __( 'All products', 'make-plus' ),
					'featured' => __( 'Featured products', 'make-plus' ),
					'sale' => __( 'Sale products', 'make-plus' ),
				);
				break;
			case 'wpecommerce-product-grid-sortby' :
				$choices = array(
					'menu_order' => __( 'Default sorting', 'make-plus' ),
					'popularity' => __( 'Popularity', 'make-plus' ),
					'rating'     => __( 'Average rating', 'make-plus' ),
					'date'       => __( 'Newness', 'make-plus' ),
					'price'      => __( 'Price: low to high', 'make-plus' ),
					'price-desc' => __( 'Price: high to low', 'make-plus' )
				);
				if ( get_option( 'wpecommerce_enable_review_rating' ) === 'no' ) {
					unset( $choices['rating'] );
				}
				break;
			case 'wpecommerce-product-grid-taxonomy' :
				// Default
				$choices = array( 'all' => __( 'All product categories/tags', 'make-plus' ) );
				// Categories
				$product_category_terms = get_terms( 'wpsc_product_category' );
				if ( ! empty( $product_category_terms ) ) {
					$category_slugs = array_map( array( $this, 'prefix_cat' ), wp_list_pluck( $product_category_terms, 'slug' ) );
					$category_names = wp_list_pluck( $product_category_terms, 'name' );
					$category_list = array_combine( $category_slugs, $category_names );
					$choices = array_merge(
						$choices,
						array( 'ttfmp-disabled1' => '--- ' . __( 'Product categories', 'make-plus' ) . ' ---' ),
						$category_list
					);
				}
				// Tags
				$product_tag_terms = get_terms( 'product_tag' );
				if ( ! empty( $product_tag_terms ) ) {
					$tag_slugs = array_map( array( $this, 'prefix_tag' ), wp_list_pluck( $product_tag_terms, 'slug' ) );
					$tag_names = wp_list_pluck( $product_tag_terms, 'name' );
					$tag_list = array_combine( $tag_slugs, $tag_names );
					$choices = array_merge(
						$choices,
						array( 'ttfmp-disabled2' => '--- ' . __( 'Product tags', 'make-plus' ) . ' ---' ),
						$tag_list
					);
				}
				break;
		}

		return $choices;
	}

	/**
	 * Add a category prefix to a value.
	 *
	 * @since 1.0.0.
	 *
	 * @param string $value    The original value.
	 *
	 * @return string          The modified value.
	 */
	private function prefix_cat( $value ) {
		return 'cat_' . $value;
	}

	/**
	 * Add a tag prefix to a value.
	 *
	 * @since 1.0.0.
	 *
	 * @param string $value    The original value.
	 *
	 * @return string          The modified value.
	 */
	private function prefix_tag( $value ) {
		return 'tag_' . $value;
	}

	/**
	 * Register the Product Grid section.
	 *
	 * @since 1.0.0.
	 *
	 * @hooked action after_setup_theme
	 *
	 * @return void
	 */
	public function register_product_grid_section() {
		// Bail if we aren't in the admin
		if ( ! is_admin() ) {
			return;
		}

		ttfmake_add_section(
			'productgrid',
			__( 'Products', 'make-plus' ),
			makeplus_get_plugin_directory_uri() . 'css/wpecommerce/images/wpecommerce.png',
			__( 'Display your WPECommerce products in a grid layout.', 'make-plus' ),
			array( $this, 'save_product_grid' ),
			'sections/builder-templates/product-grid',
			'sections/front-end-templates/product-grid',
			820,
			makeplus_get_plugin_directory() . 'inc/component/wpecommerce',
			array(
				100 => array(
					'type'  => 'section_title',
					'name'  => 'title',
					'label' => __( 'Enter section title', 'make-plus' ),
					'class' => 'ttfmake-configuration-title ttfmake-section-header-title-input',
				),
				200 => array(
					'type'    => 'checkbox',
					'label'   => __( 'Full width', 'make-plus' ),
					'name'    => 'full-width',
					'default' => ttfmake_get_section_default( 'full-width', 'wpecommerce-product-grid' ),
				),
				300 => array(
					'type'  => 'image',
					'name'  => 'background-image',
					'label' => __( 'Background image', 'make-plus' ),
					'class' => 'ttfmake-configuration-media',
					'default' => ttfmake_get_section_default( 'background-image', 'wpecommerce-product-grid' ),
				),
				400 => array(
					'type'    => 'checkbox',
					'label'   => __( 'Darken background to improve readability', 'make-plus' ),
					'name'    => 'darken',
					'default' => ttfmake_get_section_default( 'darken', 'wpecommerce-product-grid' ),
				),
				500 => array(
					'type'    => 'select',
					'name'    => 'background-style',
					'label'   => __( 'Background style', 'make-plus' ),
					'default' => ttfmake_get_section_default( 'background-style', 'wpecommerce-product-grid' ),
					'options' => ttfmake_get_section_choices( 'background-style', 'wpecommerce-product-grid' ),
				),
				600 => array(
					'type'    => 'color',
					'label'   => __( 'Background color', 'make-plus' ),
					'name'    => 'background-color',
					'class'   => 'ttfmake-text-background-color ttfmake-configuration-color-picker',
					'default' => ttfmake_get_section_default( 'background-color', 'wpecommerce-product-grid' ),
				),
			)
		);
	}

	/**
	 * Filter the json representation of this section.
	 *
	 * @since 1.8.0.
	 *
	 * @hooked filter make_get_section_json
	 *
	 * @param array $defaults    The array of data for this section.
	 *
	 * @return array             The modified array to be jsonified.
	 */
	public function get_section_json( $data ) {
		if ( $data['section-type'] == 'productgrid' ) {
			$data = wp_parse_args( $data, $this->get_defaults() );
			$image = ttfmake_get_image_src( $data['background-image'], 'large' );

			if ( isset( $image[0] ) ) {
				$data['background-image-url'] = $image[0];
			}
		}

		return $data;
	}

	/**
	 * Save the data for the Product Grid section.
	 *
	 * @since 1.0.0.
	 *
	 * @param array $data    The data from the $_POST array for the section.
	 *
	 * @return array         The cleaned data.
	 */
	public function save_product_grid( $data ) {
		// Checkbox fields will not be set if they are unchecked.
		$checkboxes = array( 'thumb', 'rating', 'price', 'addcart' );
		if ( get_option( 'wpecommerce_enable_review_rating' ) === 'no' ) {
			unset( $checkboxes['rating'] );
		}
		foreach ( $checkboxes as $key ) {
			if ( ! isset( $data[$key] ) ) {
				$data[$key] = 0;
			}
		}

		// Data to sanitize and save
		$defaults = array(
			'title' => ttfmake_get_section_default( 'title', 'wpecommerce-product-grid' ),
			'background-image' => ttfmake_get_section_default( 'background-image', 'wpecommerce-product-grid' ),
			'darken' => ttfmake_get_section_default( 'darken', 'wpecommerce-product-grid' ),
			'background-style' => ttfmake_get_section_default( 'background-style', 'wpecommerce-product-grid' ),
			'background-color' => ttfmake_get_section_default( 'background-color', 'wpecommerce-product-grid' ),
			'full-width' => ttfmake_get_section_default( 'full-width', 'wpecommerce-product-grid' ),
			'columns' => ttfmake_get_section_default( 'columns', 'wpecommerce-product-grid' ),
			'type' => ttfmake_get_section_default( 'type', 'wpecommerce-product-grid' ),
			'taxonomy' => ttfmake_get_section_default( 'taxonomy', 'wpecommerce-product-grid' ),
			'sortby' => ttfmake_get_section_default( 'sortby', 'wpecommerce-product-grid' ),
			'count' => ttfmake_get_section_default( 'count', 'wpecommerce-product-grid' ),
			'thumb' => ttfmake_get_section_default( 'thumb', 'wpecommerce-product-grid' ),
			'rating' => ttfmake_get_section_default( 'rating', 'wpecommerce-product-grid' ),
			'price' => ttfmake_get_section_default( 'price', 'wpecommerce-product-grid' ),
			'addcart' => ttfmake_get_section_default( 'addcart', 'wpecommerce-product-grid' ),
		);
		$parsed_data = wp_parse_args( $data, $defaults );

		$clean_data = array();

		// Title
		$clean_data['title'] = $clean_data['label'] = apply_filters( 'title_save_pre', $parsed_data['title'] );

		// Background image
		$clean_data['background-image'] = ttfmake_sanitize_image_id( $parsed_data['background-image'] );

		// Darken
		$clean_data['darken'] = absint( $parsed_data['darken'] );

		// Background style
		$clean_data['background-style'] = ttfmake_sanitize_section_choice( $parsed_data['background-style'], 'background-style', 'wpecommerce-product-grid' );

		// Background color
		$clean_data['background-color'] = maybe_hash_hex_color( $parsed_data['background-color'] );

		// Full width
		$clean_data['full-width'] = absint( $parsed_data['full-width'] );

		// Columns
		$clean_data['columns'] = ttfmake_sanitize_section_choice( $parsed_data['columns'], 'columns', 'wpecommerce-product-grid' );

		// Type
		$clean_data['type'] = ttfmake_sanitize_section_choice( $parsed_data['type'], 'type', 'wpecommerce-product-grid' );

		// Taxonomy
		$clean_data['taxonomy'] = ttfmake_sanitize_section_choice( $parsed_data['taxonomy'], 'taxonomy', 'wpecommerce-product-grid' );

		// Sort
		$clean_data['sortby'] = ttfmake_sanitize_section_choice( $parsed_data['sortby'], 'sortby', 'wpecommerce-product-grid' );

		// Count
		$clean_count = (int) $parsed_data['count'];
		if ( $clean_count < 1 && -1 !== $clean_count ) {
			$clean_data['count'] = ttfmake_get_section_default( 'count', 'wpecommerce-product-grid' );
		} else {
			$clean_data['count'] = $clean_count;
		}

		// Product name
		$clean_data['thumb'] = absint( $parsed_data['thumb'] );

		// Product description
		$clean_data['rating'] = absint( $parsed_data['rating'] );

		// Price
		$clean_data['price'] = absint( $parsed_data['price'] );

		// Add To Cart
		$clean_data['addcart'] = absint( $parsed_data['addcart'] );

		return $clean_data;
	}
}
