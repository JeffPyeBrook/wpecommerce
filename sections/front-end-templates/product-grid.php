<?php
/**
 * @package Make Plus
 */

global $ttfmake_section_data, $ttfmake_sections;

if ( ! function_exists( 'ttfmake_get_section_default' ) ) {
    function ttfmake_get_section_default( $a, $b ) {
        return 1;
    }

    function ttfmake_sanitize_image_id( $id ) {
        return $id;
    }

	function ttfmake_sanitize_section_choice( $a, $b, $c ) {
        return $a;
    }
}

if ( ! function_exists( 'ttfmake_get_image_src') ) {
	function ttfmake_get_image_src( $image_id, $size ) {
		$src = '';

		if ( false === strpos( $image_id, 'x' ) ) {
			$image = wp_get_attachment_image_src( $image_id, $size );

			if ( false !== $image && isset( $image[0] ) ) {
				$src = $image;
			}
		} else {
			$image = ttfmake_get_placeholder_image( $image_id );

			if ( isset( $image['src'] ) ) {
				$wp_src = array(
					0 => $image['src'],
					1 => $image['width'],
					2 => $image['height'],
				);
				$src    = array_merge( $image, $wp_src );
			}
		}

		/**
		 * Filter the image source attributes.
		 *
		 * @since 1.2.3.
		 *
		 * @param string $src      The image source attributes.
		 * @param int    $image_id The ID for the image.
		 * @param bool   $size     The requested image size.
		 */
		return apply_filters( 'make_get_image_src', $src, $image_id, $size );
	}
}

$defaults = array(
	'title' => ttfmake_get_section_default( 'title', 'wpecommerce-product-grid' ),
	'background-image' => ttfmake_get_section_default( 'background-image', 'wpecommerce-product-grid' ),
	'darken' => ttfmake_get_section_default( 'darken', 'wpecommerce-product-grid' ),
	'background-style' => ttfmake_get_section_default( 'background-style', 'wpecommerce-product-grid' ),
	'background-color' => ttfmake_get_section_default( 'background-color', 'wpecommerce-product-grid' ),
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


$data = wp_parse_args( $ttfmake_section_data, $defaults );

// Sanitize all the data
$title = apply_filters( 'the_title', $data['title'] );
$background_image = ttfmake_sanitize_image_id( $data['background-image'] );
$darken = absint( $data['darken'] );
$background_style = ttfmake_sanitize_section_choice( $data['background-style'], 'background-style', 'wpecommerce-product-grid' );
$background_color = maybe_hash_hex_color( $data['background-color'] );
$columns = ttfmake_sanitize_section_choice( $data['columns'], 'columns', 'wpecommerce-product-grid' );
$type = ttfmake_sanitize_section_choice( $data['type'], 'type', 'wpecommerce-product-grid' );
$taxonomy = ttfmake_sanitize_section_choice( $data['taxonomy'], 'taxonomy', 'wpecommerce-product-grid' );
$sortby = ttfmake_sanitize_section_choice( $data['sortby'], 'sortby', 'wpecommerce-product-grid' );
$count = esc_attr( $data['count'] );
$thumb = absint( $data['thumb'] );
$rating = absint( $data['rating'] );
$price = absint( $data['price'] );
$addcart = absint( $data['addcart'] );

// Section ID
$section_id = 'builder-section-' . $ttfmake_section_data['id'];
if ( method_exists( 'TTFMAKE_Builder_Save', 'section_html_id' ) ) :
	$section_id = ttfmake_get_builder_save()->section_html_id( $ttfmake_section_data );
endif;

// Classes
$classes = 'builder-section ';
//$classes .= ttfmake_get_builder_save()->section_classes( $ttfmake_section_data, $ttfmake_sections );
if ( ! empty( $background_color ) || 0 !== $background_image ) {
	$classes .= ' has-background';
}
$full_width = isset( $ttfmake_section_data['full-width'] ) && 0 !== absint( $ttfmake_section_data['full-width'] );
if ( true === $full_width ) {
	$classes .= ' builder-section-full-width';
}

// Style
$style = '';
if ( ! empty( $background_color ) ) {
	$style .= 'background-color:' . $background_color . ';';
}
if ( 0 !== $background_image ) {
	$image_src = ttfmake_get_image_src( $background_image, 'full' );
	if ( isset( $image_src[0] ) ) {
		$style .= 'background-image: url(\'' . addcslashes( esc_url_raw( $image_src[0] ), '"' ) . '\');';
	}
}
if ( 'cover' === $background_style  ) {
	$style .= 'background-size: cover;';
}
?>

<section id="<?php echo esc_attr( $section_id ); ?>" class="<?php echo esc_attr( $classes ); ?>" style="<?php echo esc_attr( $style ); ?>">
	<?php if ( '' !== $data['title'] ) : ?>
	<h3 class="builder-wpecommerce-product-grid-section-title">
		<?php echo $title; ?>
	</h3>
	<?php endif; ?>
	<div class="builder-section-content">
		[ttfmp_wpecommerce_product_grid columns="<?php echo $columns; ?>" type="<?php echo $type; ?>" taxonomy="<?php echo $taxonomy; ?>" sortby="<?php echo $sortby; ?>" count="<?php echo $count; ?>" thumb="<?php echo $thumb; ?>" rating="<?php echo $rating; ?>" price="<?php echo $price; ?>" addcart="<?php echo $addcart; ?>"]
	</div>
	<?php if ( 0 !== $darken ) : ?>
	<div class="builder-section-overlay"></div>
	<?php endif; ?>
</section>