<?php
$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	// Check condition show field search by author for post.
	$show_search_author_field = 0;
if ( $screen instanceof WP_Screen ) {
	$course_item_types                = learn_press_get_course_item_types();
	$screens_show_search_author_field = [
		'edit-' . LP_COURSE_CPT,
		'edit-' . LP_QUESTION_CPT,
		'edit-' . LP_ORDER_CPT,
	];

	foreach ( $course_item_types as $type ) {
		$screens_show_search_author_field[] = 'edit-' . $type;
	}

	$show_search_author_field = in_array( $screen->id, $screens_show_search_author_field ) ? 1 : 0;

	if ( $show_search_author_field ) {
		global $typenow;
		$post_type        = $typenow;
		$post_type_object = get_post_type_object( $post_type );

		ob_start();
		submit_button( $post_type_object->labels->search_items, '', '', false, array( 'id' => 'search-submit' ) );
		$btn_submit = ob_get_clean();

		ob_start();
		$input_id = 'post-search-input';

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
		}
		if ( ! empty( $_REQUEST['order'] ) ) {
			echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
		}
		if ( ! empty( $_REQUEST['post_mime_type'] ) ) {
			echo '<input type="hidden" name="post_mime_type" value="' . esc_attr( $_REQUEST['post_mime_type'] ) . '" />';
		}
		if ( ! empty( $_REQUEST['detached'] ) ) {
			echo '<input type="hidden" name="detached" value="' . esc_attr( $_REQUEST['detached'] ) . '" />';
		}

		printf(
			'<p class="search-box">
						<label class="screen-reader-text" for="%s">%s:</label>
						<input type="search" id="%s" name="s" value="%s" />
							%s
					</p>',
			$post_type_object->labels->search_items,
			esc_attr( $input_id ),
			$input_id,
			isset( $_REQUEST['s'] ) ? esc_attr( wp_unslash( $_REQUEST['s'] ) ) : '',
			$btn_submit
		);
		$show_search_author_field = ob_get_clean();
	}
}

return $show_search_author_field;
