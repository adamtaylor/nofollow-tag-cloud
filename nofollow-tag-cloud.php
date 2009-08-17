<?php

/**
 * Display tag cloud widget.
 *
 * @since 2.3.0
 *
 * @param array $args Widget arguments.
 */
function no_follow_widget_tag_cloud($args) {
	extract($args);
	$options = get_option('widget_tag_cloud');
	$title = empty($options['title']) ? __('Tags') : apply_filters('widget_title', $options['title']);

	echo $before_widget;
	echo $before_title . $title . $after_title;
	no_follow_tag_cloud();
	echo $after_widget;
}

/**
 * Manage WordPress Tag Cloud widget options.
 *
 * Displays management form for changing the tag cloud widget title.
 *
 * @since 2.3.0
 */
function no_follow_widget_tag_cloud_control() {
	$options = $newoptions = get_option('widget_tag_cloud');

	if ( isset($_POST['tag-cloud-submit']) ) {
		$newoptions['title'] = strip_tags(stripslashes($_POST['tag-cloud-title']));
	}

	if ( $options != $newoptions ) {
		$options = $newoptions;
		update_option('widget_tag_cloud', $options);
	}

	$title = attribute_escape( $options['title'] );

/**
 * Display tag cloud.
 *
 * The text size is set by the 'smallest' and 'largest' arguments, which will
 * use the 'unit' argument value for the CSS text size unit. The 'format'
 * argument can be 'flat' (default), 'list', or 'array'. The flat value for the
 * 'format' argument will separate tags with spaces. The list value for the
 * 'format' argument will format the tags in a UL HTML list. The array value for
 * the 'format' argument will return in PHP array type format.
 *
 * The 'orderby' argument will accept 'name' or 'count' and defaults to 'name'.
 * The 'order' is the direction to sort, defaults to 'ASC' and can be 'DESC'.
 *
 * The 'number' argument is how many tags to return. By default, the limit will
 * be to return the top 45 tags in the tag cloud list.
 *
* The 'topic_count_text_callback' argument is a function, which, given the count
 * of the posts  with that tag, returns a text for the tooltip of the tag link.
 * @see default_topic_count_text
 *
 * The 'exclude' and 'include' arguments are used for the {@link get_tags()}
 * function. Only one should be used, because only one will be used and the
 * other ignored, if they are both set.
 *
 * @since 2.3.0
 *
 * @param array|string $args Optional. Override default arguments.
 * @return array Generated tag cloud, only if no failures and 'array' is set for the 'format' argument.
 */
function no_follow_tag_cloud( $args = '' ) {
	$defaults = array(
		'smallest' => 8, 'largest' => 22, 'unit' => 'pt', 'number' => 45,
		'format' => 'flat', 'orderby' => 'name', 'order' => 'ASC',
		'exclude' => '', 'include' => '', 'link' => 'view'
	);
	$args = wp_parse_args( $args, $defaults );

	$tags = get_tags( array_merge( $args, array( 'orderby' => 'count', 'order' => 'DESC' ) ) ); // Always query top tags

	if ( empty( $tags ) )
		return;

	foreach ( $tags as $key => $tag ) {
		if ( 'edit' == $args['link'] )
			$link = get_edit_tag_link( $tag->term_id );
		else
			$link = get_tag_link( $tag->term_id );
		if ( is_wp_error( $link ) )
			return false;

		$tags[ $key ]->link = $link;
		$tags[ $key ]->id = $tag->term_id;
	}

	$return = no_follow_generate_tag_cloud( $tags, $args ); // Here's where those top tags get sorted according to $args

	$return = apply_filters( 'wp_tag_cloud', $return, $args );

	if ( 'array' == $args['format'] )
		return $return;

	echo $return;
}

/**
 * Generates a tag cloud (heatmap) from provided data.
 *
 * The text size is set by the 'smallest' and 'largest' arguments, which will
 * use the 'unit' argument value for the CSS text size unit. The 'format'
 * argument can be 'flat' (default), 'list', or 'array'. The flat value for the
 * 'format' argument will separate tags with spaces. The list value for the
 * 'format' argument will format the tags in a UL HTML list. The array value for
 * the 'format' argument will return in PHP array type format.
 *
 * The 'orderby' argument will accept 'name' or 'count' and defaults to 'name'.
 * The 'order' is the direction to sort, defaults to 'ASC' and can be 'DESC' or
 * 'RAND'.
 *
 * The 'number' argument is how many tags to return. By default, the limit will
 * be to return the entire tag cloud list.
 *
 * The 'topic_count_text_callback' argument is a function, which given the count
 * of the posts  with that tag returns a text for the tooltip of the tag link.
 * @see default_topic_count_text
 *
 *
 * @todo Complete functionality.
 * @since 2.3.0
 *
 * @param array $tags List of tags.
 * @param string|array $args Optional, override default arguments.
 * @return string
 */
function no_follow_generate_tag_cloud( $tags, $args = '' ) {
	global $wp_rewrite;
	$defaults = array(
		'smallest' => 8, 'largest' => 22, 'unit' => 'pt', 'number' => 0,
		'format' => 'flat', 'orderby' => 'name', 'order' => 'ASC',
		'topic_count_text_callback' => 'default_topic_count_text',
	);

	if ( !isset( $args['topic_count_text_callback'] ) && isset( $args['single_text'] ) && isset( $args['multiple_text'] ) ) {
		$body = 'return sprintf (
			__ngettext('.var_export($args['single_text'], true).', '.var_export($args['multiple_text'], true).', $count),
			number_format_i18n( $count ));';
		$args['topic_count_text_callback'] = create_function('$count', $body);
	}

	$args = wp_parse_args( $args, $defaults );

	extract( $args );

	if ( empty( $tags ) )
		return;

	// SQL cannot save you; this is a second (potentially different) sort on a subset of data.
	if ( 'name' == $orderby )
		uasort( $tags, create_function('$a, $b', 'return strnatcasecmp($a->name, $b->name);') );
	else
		uasort( $tags, create_function('$a, $b', 'return ($a->count < $b->count);') );

	if ( 'DESC' == $order )
		$tags = array_reverse( $tags, true );
	elseif ( 'RAND' == $order ) {
		$keys = array_rand( $tags, count( $tags ) );
		foreach ( $keys as $key )
			$temp[$key] = $tags[$key];
		$tags = $temp;
		unset( $temp );
	}

	if ( $number > 0 )
		$tags = array_slice($tags, 0, $number);

	$counts = array();
	foreach ( (array) $tags as $key => $tag )
		$counts[ $key ] = $tag->count;

	$min_count = min( $counts );
	$spread = max( $counts ) - $min_count;
	if ( $spread <= 0 )
		$spread = 1;
	$font_spread = $largest - $smallest;
	if ( $font_spread < 0 )
		$font_spread = 1;
	$font_step = $font_spread / $spread;

	$a = array();

	$rel = ( is_object( $wp_rewrite ) && $wp_rewrite->using_permalinks() ) ? ' rel="tag"' : '';

	foreach ( $tags as $key => $tag ) {
		$count = $counts[ $key ];
		$tag_link = '#' != $tag->link ? clean_url( $tag->link ) : '#';
		$tag_id = isset($tags[ $key ]->id) ? $tags[ $key ]->id : $key;
		$tag_name = $tags[ $key ]->name;
		$a[] = "<a href='$tag_link' rel='nofollow' class='tag-link-$tag_id' title='" . attribute_escape( $topic_count_text_callback( $count ) ) . "'$rel style='font-size: " .
			( $smallest + ( ( $count - $min_count ) * $font_step ) )
			. "$unit;'>$tag_name</a>";
	}

	switch ( $format ) :
	case 'array' :
		$return =& $a;
		break;
	case 'list' :
		$return = "<ul class='wp-tag-cloud'>\n\t<li>";
		$return .= join( "</li>\n\t<li>", $a );
		$return .= "</li>\n</ul>\n";
		break;
	default :
		$return = join( "\n", $a );
		break;
	endswitch;

	return apply_filters( 'wp_generate_tag_cloud', $return, $tags, $args );
}

?>