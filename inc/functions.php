<?php
/**
 * Contains several legacy and shorthand functions
 *
 * @since 3.0
 */

/**
 * @return mixed|void Are we currently viewing the home page
 */
function siteorigin_panels_is_home(){
	return SiteOrigin_Panels::is_home();
}

/**
 * Check if we're currently viewing a page builder page.
 *
 * @param bool $can_edit Also check if the user can edit this page
 * @return bool
 */
function siteorigin_panels_is_panel($can_edit = false){
	return SiteOrigin_Panels::is_panel( $can_edit );
}


function siteorigin_panels_get_home_page_data(){
	return SiteOrigin_Panels::single()->get_home_page_data();
}

/**
 * Render Page Builder content
 *
 * @param bool $post_id
 * @param bool $enqueue_css
 * @param bool $panels_data
 */
function siteorigin_panels_render( $post_id = false, $enqueue_css = true, $panels_data = false ){
	SiteOrigin_Panels_Renderer::single()->render( $post_id, $enqueue_css, $panels_data );
}

function siteorigin_panels_the_widget( $widget_info, $instance, $grid, $cell, $panel, $is_first, $is_last, $post_id = false, $style_wrapper = '' ) {
	SiteOrigin_Panels_Renderer::single()->the_widget( $widget_info, $instance, $grid, $cell, $panel, $is_first, $is_last, $post_id, $style_wrapper );
}
