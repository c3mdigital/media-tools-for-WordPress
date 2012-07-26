<?php
/**
 * Plugin Name: Convert to featured image
 * Plugin URI: http://wordpress.org
 * Description: Tools for working with the WordPress media library and converting images to attachments and featured images
 * Version: 1.0
 * Author: Chris Olbekson
 * Author URI: http://c3mdigital.com
 * License: GPL v2
 * 
 */

/*  Copyright 2012  Chris Olbekson  (email : plugins@c3mdigital.com )

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
 
$c3m_media_tools = new C3M_Media_Tools();

class C3M_Media_Tools {

	private static $media_tabs_key = 'media_tabs_key';

	function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_head', array( $this, 'home_tab_js' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'media_tools_js' ) );
		add_action( 'wp_ajax_convert-featured', array( $this, 'ajax_handler' ) );
	}

	/**
	 * @param string $hook reference to current admin page
	 */
	function media_tools_js( $hook ) {
		if( 'tools_page_media_tools' == $hook )
			wp_enqueue_script( 'media-tools-ajax', plugins_url( 'js/media.tools.ajax.js', __FILE__), array( 'jquery' ) );

	}

	function admin_menu() {
		add_submenu_page( 'tools.php', 'Media Tools', 'Media Tools', 'manage_options', self::$media_tabs_key, array( $this, 'admin_media_page') );
	}

	function media_tools_tabs() {
		$tabs = array(
			'home'      => 'Media Tools',
			'media'     => 'Media',
			'options'   => 'Media Options',
		);
		return $tabs;
	}

	function admin_media_page() {
		if( isset( $_GET['tab'] ) )
			$tab = $_GET['tab'];
		else
			$tab = 'home'; ?>

		<div class="wrap">
			<?php wp_nonce_field( $tab );
			$this->menu_tabs( $tab );

			switch ( $tab ) :
				case 'home' :
					$this->home_tab();
				break;
				case 'media' :
					$this->media_tab();
				break;
				case 'options' :
					$this->options();
			endswitch; ?>

		</div>

<?php	}

	/**
	 * @param string $current_tab
	 *
	 * @return string The tab header html
	 */
	function menu_tabs( $current_tab = 'home' ) {
		$output = '<div id="icon-options-general" class="icon32"><br></div>';
		$output .=  '<h2 class="nav-tab-wrapper">';
		$tabs = $this->media_tools_tabs();

		foreach( $tabs as $tab_key => $tab_caption ) :
			$active = $current_tab == $tab_key ? 'nav-tab-active' : '';

			$output .= '<a class="nav-tab '.$active. '" href=?page=' .self::$media_tabs_key. '&tab='.$tab_key. '>' .$tab_caption. '</a>';
		endforeach;
			$output .= '</h2>';
		return $output;

	}


	function home_tab_js() {
		?>
		<script type="text/javascript">
			//<![CDATA[
			jQuery(document).ready(function ($) {
			var form = $('#export-filters'),
			filters = form.find('.export-filters');
			filters.hide();
			form.find('input:radio').change(function () {
				filters.slideUp('fast');
					switch ($(this).val()) {
					case 'posts':
					$('#post-filters').slideDown();
					break;
					case 'pages':
					$('#page-filters').slideDown();
					break;
					}
			});
		});
		//]]>
	</script>

<?php }

	 function home_tab() {

		global $wpdb;
		$title = __( 'WordPress Media Tools' ); ?>

			<div class="page-description">
				<h2><?php echo esc_html( $title ); ?></h2>

				<p><?php _e( 'WordPress Media Tools are a set of tools to help you manage the media in your posts and pages.' ); ?></p>
				<p><?php _e( 'You can import external images into the media library, attach media to a post or page, and set images as the featured image.' ); ?></p>

			</div>

			<div class="set-featured">
			<h3><?php _e( 'Set Featured Images' ); ?></h3>
			<p><?php _e( 'This tool goes through your posts and sets the first image found as the featured image' ); ?></p>
			<h3 id="convert-title"><?php _e( 'Choose where to convert from' ); ?></h3>
			<form action="" method="get" id="export-filters">

				<p><label><input type="radio" name="content" value="all" checked="checked"/> <?php _e( 'All content' ); ?></label></p>
				<p class="description"><?php _e( 'This will convert the first image from  all of your posts, pages, custom posts.' ); ?></p>

				<p><label><input type="radio" name="content" value="posts"/> <?php _e( 'Posts' ); ?></label></p>
					<ul id="post-filters" class="export-filters">
						<li><label><?php _e( 'Categories:' ); ?></label><?php wp_dropdown_categories( array ( 'show_option_all' => __( 'All' ) ) ); ?></li>
						<li><label><?php _e( 'Authors:' ); ?></label><?php $authors = $wpdb->get_col( "SELECT DISTINCT post_author FROM {$wpdb->posts} WHERE post_type = 'post'" );
							wp_dropdown_users( array ( 'include' => $authors, 'name' => 'post_author', 'multi' => true, 'show_option_all' => __( 'All' ) ) );?>
						</li>
						<li><label><?php _e( 'Date range:' ); ?></label>
							<select name="post_start_date">
								<option value="0"><?php _e( 'Start Date' ); ?></option>
								<?php $this->convert_date_options(); ?>
							</select>
							<select name="post_end_date">
								<option value="0"><?php _e( 'End Date' ); ?></option>
								<?php $this->convert_date_options(); ?>
							</select>
						</li>
						<li><label><?php _e( 'Status:' ); ?></label>
							<select name="post_status">
								<option value="0"><?php _e( 'All' ); ?></option>
								<?php $post_stati = get_post_stati( array ( 'internal' => false ), 'objects' );
								foreach ( $post_stati as $status ) : ?>
								<option value="<?php echo esc_attr( $status->name ); ?>"><?php echo esc_html( $status->label ); ?></option>
								<?php endforeach; ?>
							</select>
						</li>
					</ul>

				<p><label><input type="radio" name="content" value="pages"/> <?php _e( 'Pages' ); ?></label></p>
					<ul id="page-filters" class="export-filters">
						<li><label><?php _e( 'Authors:' ); ?></label><?php
							$authors = $wpdb->get_col( "SELECT DISTINCT post_author FROM {$wpdb->posts} WHERE post_type = 'page'" );
							wp_dropdown_users( array ( 'include' => $authors, 'name' => 'page_author', 'multi' => true, 'show_option_all' => __( 'All' ) ) );?>
						</li>
						<li><label><?php _e( 'Date range:' ); ?></label>
							<select name="page_start_date">
								<option value="0"><?php _e( 'Start Date' ); ?></option>
								<?php $this->convert_date_options( 'page' ); ?>
							</select>
							<select name="page_end_date">
								<option value="0"><?php _e( 'End Date' ); ?></option>
								<?php $this->convert_date_options( 'page' ); ?>
							</select>
						</li>
						<li><label><?php _e( 'Status:' ); ?></label>
							<select name="page_status">
								<option value="0"><?php _e( 'All' ); ?></option>
								<?php foreach ( $post_stati as $status ) : ?>
								<option value="<?php echo esc_attr( $status->name ); ?>"><?php echo esc_html( $status->label ); ?></option>
								<?php endforeach; ?>
							</select>
						</li>
					</ul>

				<?php foreach ( get_post_types( array ( '_builtin' => false, 'can_export' => true ), 'objects' ) as $post_type ) : ?>
				<p><label><input type="radio" name="content" value="<?php echo esc_attr( $post_type->name ); ?>"/> <?php echo esc_html( $post_type->label ); ?></label></p>
				<?php endforeach; ?>

				<?php submit_button( __( 'Set Featured Images' ), 'secondary' ); ?>
			</form>
				<div id="ajax-spinner" style="display:none;"><img src="<?php echo admin_url('/images/wpspin_light.gif'); ?>" /></div>
				<div id="featured-ajax-response"></div>

			</div>


<?php	}

	function ajax_handler() {

		/** @var object $data The  serialized form object */

		$data = $_POST['args'];
		if ( ! isset( $data[0]['content'] ) || 'all' == $data[0]['content'] ) {
			$args['content'] = 'all';
		}
		else if ( 'posts' == $data[0]['content'] ) {
			$args['content'] = 'post';

			if ( $data[1]['cat'] )
				$args['category'] = (int)$data[1]['cat'];

			if ( $data[2]['post_author'] )
				$args['author'] = (int)$data[2]['post_author'];

			if ( $data[3]['post_start_date'] || $data[4]['post_end_date'] ) {
				$args['start_date'] = $data[3]['post_start_date'];
				$args['end_date'] = $data[4]['post_end_date'];
			}

			if ( $data[5]['post_status'] )
				$args['status'] = $data[5]['post_status'];
		}
		else if ( 'pages' == $data[0]['content'] ) {
			$args['content'] = 'page';

			if ( $data[6]['page_author'] )
				$args['author'] = (int)$data[6]['page_author'];

			if ( $data[7]['page_start_date'] || $data[8]['page_end_date'] ) {
				$args['start_date'] = $data[7]['page_start_date'];
				$args['end_date'] = $data[8]['page_end_date'];
			}

			if ( $data[9]['page_status'] )
				$args['status'] = $data[9]['page_status'];
		}
		else {
			$args['content'] = $data[0]['content'];
		}
		$response = false;
		$ids = $this->query( $args );

		/** @var $i @param array $ids The array of post ids returned from the query  */

		for ( $i = 0; $i < count( $ids ); $i ++ ) {
			$post = get_post( $ids[ $i ] );

			/** If the post already has an attached thumbnail continue with the loop  */

			if ( has_post_thumbnail( $post->ID ) )
				continue;

			/** @var $attachments array of attached images to the post */

			$attachments = $this->get_attach( $post->ID );

			if ( ! $attachments ) {
				$img = $this->extract_image( $post );
				if( empty( $img ) )
					continue;
				/** @var $file string or WP_Error of image attached to the post  */
				$file = media_sideload_image( $img, (int)$post->ID );

				if ( ! is_wp_error( $file ) ) {
					$atts = $this->get_attach( $post->ID );
					foreach ( $atts as $a ) {

						/** @var $img bool attaches image as the post thumbnail  */

						$img = set_post_thumbnail( $post->ID, $a['ID'] );
						if ( $img )
							$response .= $post->post_title . ' featured image set' . "\n";
					}
				}
			} else {

				foreach( $attachments as $a ) {
					$img = set_post_thumbnail( $post->ID, $a['ID'] );
					if ( $img )
						$response .=  $post->post_title. ' featured image set'."\n";
				}

			}

		}

		if ( empty( $response) )
			die( -1 );

		echo $response;
			die(1);
	}

	function media_tab() {

	}

	function options() {

	}

	function get_attach( $post_id ) {
		return get_children( array (
				'post_parent'    => $post_id,
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_per_page'  => 1
			), ARRAY_A
		);

	}

	/**
	 * Extracts the first image in the post content
	 *
	 * @param object $post the post object
	 *
	 * @return bool|array false if no images or img src
	 */
	function extract_image( $post ) {
		$html = $post->post_content;
		if ( stripos( $html, '<img' ) !== false ) {
			$regex = '#<\s*img [^\>]*src\s*=\s*(["\'])(.*?)\1#im';
			preg_match( $regex, $html, $matches );
			unset( $regex );
			unset( $html );
			if ( is_array( $matches ) && ! empty( $matches ) ) {
				return  $matches[2];

			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * Queries the posts based on the form field data
	 * @param array $args The ajax form array formatted for the query
	 *
	 * @return array $post_ids an array of post ids from the query result
	 */
	function query( $args = array() ) {
		global $wpdb, $post;

		$defaults = array( 'content' => 'all', 'author' => false, 'category' => false,
		'start_date' => false, 'end_date' => false, 'status' => false, );
		$args = wp_parse_args( $args, $defaults );

		if ( 'all' != $args['content'] && post_type_exists( $args['content'] ) ) {
			$ptype = get_post_type_object( $args['content'] );
			if ( ! $ptype->can_export )
				$args['content'] = 'post';

		$where = $wpdb->prepare( "{$wpdb->posts}.post_type = %s", $args['content'] );
		} else {
			$post_types = get_post_types();
			$post_types = array_diff( $post_types, array( 'attachment', 'revision', 'nav_menu_item' ) );
			$esses = array_fill( 0, count($post_types), '%s' );
			$where = $wpdb->prepare( "{$wpdb->posts}.post_type IN (" . implode( ',', $esses ) . ')', $post_types );
		}

		if ( $args['status'] && ( 'post' == $args['content'] || 'page' == $args['content'] ) )
			$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_status = %s", $args['status'] );
		else
			$where .= " AND {$wpdb->posts}.post_status != 'auto-draft'";

		$join = '';
		if ( $args['category'] && 'post' == $args['content'] ) {
			if ( $term = term_exists( $args['category'], 'category' ) ) {
				$join = "INNER JOIN {$wpdb->term_relationships} ON ({$wpdb->posts}.ID = {$wpdb->term_relationships}.object_id)";
				$where .= $wpdb->prepare( " AND {$wpdb->term_relationships}.term_taxonomy_id = %d", $term['term_taxonomy_id'] );
			}
		}

		if ( 'post' == $args['content'] || 'page' == $args['content'] ) {
			if ( $args['author'] )
				$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_author = %d", $args['author'] );

			if ( $args['start_date'] )
				$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_date >= %s", date( 'Y-m-d', strtotime($args['start_date']) ) );

			if ( $args['end_date'] )
				$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_date < %s", date( 'Y-m-d', strtotime('+1 month', strtotime($args['end_date'])) ) );
		}
		$post_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} $join WHERE $where" );

			return $post_ids;
	}

	/**
	 * Converts the data ranges to a string for the query
	 * @param string $post_type
	 */
	function convert_date_options( $post_type = 'post' ) {
		global $wpdb, $wp_locale;

		$months = $wpdb->get_results( $wpdb->prepare( "
		SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
		FROM $wpdb->posts
		WHERE post_type = %s AND post_status != 'auto-draft'
		ORDER BY post_date DESC
	", $post_type
			)
		);

		$month_count = count( $months );
		if ( ! $month_count || ( 1 == $month_count && 0 == $months[0]->month ) )
			return;

		foreach ( $months as $date ) {
			if ( 0 == $date->year )
				continue;

			$month = zeroise( $date->month, 2 );
			echo '<option value="' . $date->year . '-' . $month . '">' . $wp_locale->get_month( $month ) . ' ' . $date->year . '</option>';
		}
	}

}