<?php
/**
 * @package Make Plus
 */

/**
 * Interface MAKEPLUS_Component_WPECommerce_ShortcodeInterface
 *
 * @since 1.7.0.
 */
interface MAKEPLUS_Component_WPECommerce_ShortcodeInterface extends MAKEPLUS_Util_ModulesInterface {
	public function shortcode_product_grid( $shortcode_atts );
}