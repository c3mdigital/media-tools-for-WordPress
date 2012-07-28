<?php
/**
 * Plugin Name: Media Tools
 * Plugin URI: https://github.com/c3mdigital/media-tools-for-WordPress
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

	var $media_settings;
	var $media_tools;
	private $media_tabs_key = 'media_tabs';
	private static $thumbnail_support;
	private $media_tools_key = 'media_tools';
	private $media_settings_key = 'media_options';
	private $media_settings_tabs = array();



	function __construct() {
		self:: $thumbnail_support = current_theme_supports( 'post-thumbnails' ) ? true : add_theme_support( 'post-thumbnails' );
		$this->add_image_sizes();
		$this->actions();
	}

	function actions() {
		add_action( 'admin_menu', array ( $this, 'admin_menu' ) );
		add_action( 'init', array( $this, 'load_settings' ) );
		add_action( 'admin_head', array ( $this, 'home_tab_js' ) );
		add_action( 'admin_enqueue_scripts', array ( $this, 'media_tools_js' ) );
		add_action( 'wp_ajax_convert-featured', array ( $this, 'ajax_handler' ) );
		add_action( 'admin_init', array( $this, 'register_media_options' ) );
		add_action( 'admin_init', array ( $this, 'register_media_tools' ) );
	}

	function load_settings() {
		$this->media_tools = (array)get_option( $this->media_tools_key );
		$this->media_settings = (array) get_option( $this->media_settings_key );
		$this->media_settings = array_merge( array( 'img_handle' => '', 'img_width' => '', 'img_height' => '', 'img_crop' => '' ), $this->media_settings );
		$this->media_tools = array_merge( array ( ), $this->media_tools );
	}

	 function register_media_tools() {
		 $this->media_settings_tabs[$this->media_tools_key] = 'Media Tools';
		 register_setting( $this->media_tools_key, $this->media_tools_key );
	 }

	function register_media_options() {
		$this->media_settings_tabs[$this->media_settings_key] = 'Media Options';
		register_setting( $this->media_settings_key, $this->media_settings_key );
		add_settings_section( 'media_options_general', 'Add additional image size', array( $this, 'section_description' ), $this->media_settings_key );
		add_settings_field( 'img_handle', 'Image  Handle', array( $this, 'field_media_options' ), $this->media_settings_key, 'media_options_general' );
		add_settings_field( 'img_width', 'Image Width', array ( $this, 'field_width_options' ), $this->media_settings_key, 'media_options_general' );
		add_settings_field( 'img_height', 'Image Height', array ( $this, 'field_height_options' ), $this->media_settings_key, 'media_options_general' );
		add_settings_field( 'img_crop', 'Image Crop Factor', array ( $this, 'field_crop_options' ), $this->media_settings_key, 'media_options_general' );
	}

	function section_description() {
		echo 'WordPress custom featured image sizes require a handle, height, width, and crop factor';
	}
	/** @todo Validation function for text input fields */
	function field_media_options() { ?>
	<input type="text" name="<?php echo $this->media_settings_key; ?>[img_handle]" value=""/><br>
	<?php }

	 function field_width_options() { ?>
	 <input type="text" name="<?php echo $this->media_settings_key; ?>[img_width]" value=""/>
	 <?php }

	 function field_height_options() {  ?>
	 <input type="text" name="<?php echo $this->media_settings_key; ?>[img_height]" value=""/>
	 <?php }

	 function field_crop_options() { ?>
	 <select name="<?php echo $this->media_settings_key; ?>[img_crop]" >
	    <option value="1"><?php _e( 'Hard Crop' ); ?></option>
	    <option value="0"><?php _e( 'Soft Crop' ); ?></option>
	 </select>
	 <?php }

	function options_tab( $tab ) {
		echo '<form method = "post" action = "options.php" >';
		wp_nonce_field( 'options.php' );
			echo '<div style="float:left;width:330px;">';
			$this->show_thumb_sizes();
			echo '</div>';
		echo '<div style="float:left;margin-top:50px;">';
		settings_fields( $tab );
		do_settings_sections( $tab );
		submit_button( 'Add Image Size');
		echo '</div>';
		echo '</form>';
		echo '<div class="clear"></div>';
		if ( class_exists( 'RegenerateThumbnails') ) {
			$regen = new RegenerateThumbnails;
			$regen->regenerate_interface();
		} else {
			_e( '<h3>Regenerate Thumbnails</h3>' );
			printf( __('<p>Install Regenerate Thumbnails to crop all images that you have uploaded to your blog. This is useful if you\'ve changed any of the thumbnail dimensions above or on the <a href="%s">media settings page</a></p>' ), admin_url( 'options-media.php') );


			$url = current_user_can( 'install_plugins' ) ? wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=regenerate-thumbnails' ), 'install-plugin_regenerate-thumbnails' ) : 'http://wordpress.org/extend/plugins/regenerate-thumbnails/';
			_e( '<a href="'.esc_url( $url ).'"> class="button-secondary>Install Regenerate Thumbnails</a>' );
		}

	 }

	function add_image_sizes() {
			$ops = (array) get_option( $this->media_settings_key );
		if ( ! empty( $ops ) && isset( $ops['img_crop'] ) && isset( $ops['img_handle'] ) && isset( $ops['img_width'] ) && isset( $ops['img_height'] ) ) {
			$crop = $ops['img_crop'] == '1' ? true : false;
			add_image_size( $ops['img_handle'] , (int) $ops['img_width'], (int) $ops['img_height'], $crop );
		}
	}

	function show_thumb_sizes() {
		global $_wp_additional_image_sizes; ?>
		<h2><?php _e( 'Current Registered Image Sizes' ); ?></h2>
			<ul>
			<?php foreach( $_wp_additional_image_sizes as $size => $props ) {
				$crop = true == $props['crop'] ? 'Hard Crop' : 'Soft Crop';
				_e( '<li><h3>'     .$size.'</h3>' );
				_e( 'Width: '  .$props['width'].'<br>' );
				_e( 'Height: ' .$props['height'].'<br>' );
				_e( 'Crop: '   .$crop.'<br>' );
				_e( '</li>' );
		}
		echo '</ul>';

	}

	/**
	 * @param string $hook reference to current admin page
	 */
	function media_tools_js( $hook ) {
		if( 'tools_page_media_tabs' == $hook )
			wp_enqueue_script( 'media-tools-ajax', plugins_url( 'js/media.tools.ajax.js', __FILE__), array( 'jquery' ) );

	}

	function admin_menu() {
		add_submenu_page( 'tools.php', 'Media Tools', 'Media Tools', 'manage_options', $this->media_tabs_key, array( $this, 'admin_media_page') );
	}

	function admin_media_page() {
		$tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->media_tools_key; ?>
		<div class="wrap">
			<?php  $this->menu_tabs( $tab );

			switch ( $tab ) :
				case $this->media_tools_key :
					$this->home_tab();
				break;
				case $this->media_settings_key :
					$this->options_tab( $tab );
				break;
			endswitch; ?>
		</div>

<?php	}


	function menu_tabs() {
		$current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->media_tools_key;
		echo '<div id="icon-options-general" class="icon32"><br></div>';
		echo  '<h2 class="nav-tab-wrapper">';

		foreach( $this->media_settings_tabs  as $tab_key => $tab_caption ) :
			$active = $current_tab == $tab_key ? 'nav-tab-active' : '';

			echo  '<a class="nav-tab '.$active. '" href=?page=' .$this->media_tabs_key. '&tab='.$tab_key. '>' .$tab_caption. '</a>';
		endforeach;
			echo  '</h2>';

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
			}); });
			//]]>
		</script>

<?php }

	 function home_tab() {

		global $wpdb;
		$title = __( 'WordPress Media Tools' ); ?>

			<div class="page-description">
				<h2><?php echo esc_html( $title ); ?></h2>

				<p><?php _e( 'WordPress Media Tools are a set of tools to help you manage the media in your posts and pages.<br>' ); ?>
				<?php _e( 'You can import external images into the media library, attach media to a post or page, and set images as the featured image.' ); ?></p>

			</div>
			<div>
			<div class="set-featured">
				<h3><?php _e( 'Set Featured Images' ); ?></h3>
				<p><?php  _e( 'This tool goes through your posts and sets the first image found as the featured image' ); ?>
				<?php  _e( 'If the post already has a featured image set it will be skipped.<br>' ); ?>
				<?php  _e( 'If the first image is from an external source or not attached to the post it will be added to the media library and attached to the post' ); ?></p>
			</div>
			<div class="convert-media">
				<h3><?php _e( 'Import External Images' ); ?></h3>
				<p><?php  _e( 'This tool goes through your chosen posts or pages and imports external images into the media library' ); ?></p>
				<p><?php  _e( 'The src attribute of any found images are checked against your set uploads dir and will not insert if they match' ); ?></p>
				<p><?php  _e( 'This also changes the img src attribute to reference the new location in your uploads folder' ); ?>
				<?php     _e( 'You can also choose to make the first image the featured image' ); ?></p>
			</div>

			<h2 id="convert-title"><?php _e( 'Choose tool to run' ); ?></h2>
			<form action="" method="get" id="export-filters">
				<p>
					<select id="choose-tool" name="choose-tool">
						<option value="set-featured"><?php _e( 'Set Featured Images' ); ?></option>
						<option value="import-media"><?php _e( 'Import External Images' ); ?></option>
						<option value="convert-import"><?php _e( 'Import External and Set Featured Image' ) ;?></option>
					</select></p>
				<h3 id="convert-title"><?php _e( 'Choose content to run the tool on' ); ?></h3>
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

				<?php foreach ( get_post_types( array ( '_builtin' => false, 'can_export' => true, 'show_ui' => true ), 'objects' ) as $post_type ) : ?>
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

		if ( ! isset( $data[1]['content'] ) || 'all' == $data[1]['content'] ) {
			$args['content'] = 'all';
		}
		else if ( 'posts' == $data[1]['content'] ) {
			$args['content'] = 'post';

			if ( $data[2]['cat'] )
				$args['category'] = (int)$data[2]['cat'];

			if ( $data[3]['post_author'] )
				$args['author'] = (int)$data[3]['post_author'];

			if ( $data[4]['post_start_date'] || $data[5]['post_end_date'] ) {
				$args['start_date'] = $data[4]['post_start_date'];
				$args['end_date'] = $data[5]['post_end_date'];
			}

			if ( $data[6]['post_status'] )
				$args['status'] = $data[6]['post_status'];
		}
		else if ( 'pages' == $data[1]['content'] ) {
			$args['content'] = 'page';

			if ( $data[7]['page_author'] )
				$args['author'] = (int)$data[7]['page_author'];

			if ( $data[8]['page_start_date'] || $data[9]['page_end_date'] ) {
				$args['start_date'] = $data[8]['page_start_date'];
				$args['end_date'] = $data[9]['page_end_date'];
			}

			if ( $data[10]['page_status'] )
				$args['status'] = $data[10]['page_status'];
		}
		else {
			$args['content'] = $data[1]['content'];
		}
		$response = array();
		$ids = $this->query( $args );

		/** @var $i @param array $ids The array of post ids returned from the query  */
		for ( $i = 0; $i < count( $ids ); $i ++ ) {
			$post = get_post( $ids[ $i ] );

			if ( in_array( 'import-media', $data[0] ) ) {
				 $this->extract_multi( $post );
					continue;
			}

			if ( in_array( 'convert-import', $data[0] ) )
				 $this->extract_multi( $post );

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

		echo $response;
			wp_die(1);
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
	 * @return bool|string false if no images or img src
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
	 * @param object $post The post object
	 *
	 * @return array|bool Post id and images converted on success false if no images found in source
	 */
	function extract_multi( $post ) {
		global $wpdb;
		$html = $post->post_content;
		$upload_path = wp_upload_dir();

		if ( stripos( $html, '<img' ) !== false ) {
			echo '<h3>Results for <a href="' . esc_url( admin_url( 'post.php?post=' . $post->ID . '&action=edit' ) ) . '">Post ID: ' . $post->ID. '</a></h3>';
			$regex = '#<\s*img [^\>]*src\s*=\s*(["\'])(.*?)\1#im';
			 preg_match_all( $regex, $html, $matches );

			if ( is_array( $matches ) && ! empty( $matches ) ) {
				$new = array();
				$old = array();
				foreach( $matches[2] as $img ) {
					/** Compare image source against upload directory to prevent adding same attachment multiple times  */
					if ( false != strpbrk( $img, $upload_path['path'] ) )
						continue;
					$tmp = download_url( $img );

					preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $img, $matches);
					$file_array['name'] = basename($matches[0]);
					$file_array['tmp_name'] = $tmp;
					// If error storing temporarily, unlink
	                if ( is_wp_error( $tmp ) ) {
	                        @unlink($file_array['tmp_name']);
	                        $file_array['tmp_name'] = '';
		                    continue;
	                }

					$id = media_handle_sideload( $file_array, $post->ID );

					if ( ! is_wp_error( $id ) ) {
  						$url  = wp_get_attachment_url( $id );
						$thumb = wp_get_attachment_thumb_url( $id );
						array_push( $new, $url );
						array_push( $old, $img ); ?>
						<p>
						<a href="<?php echo wp_nonce_url( get_edit_post_link( $id, true ) ); ?>" title="edit-image"><img src="<?php echo esc_url( $thumb ); ?>" style="max-width:100px;" /></a>
						</p>

					<?php
					}
				}
				if( !empty( $new ) ) {
				$content = str_ireplace( $old, $new, $html );
				$post_args = array( 'ID' => $post->ID, 'post_content' => $content, );
				if ( !empty( $content ) )
					$post_id = wp_update_post( $post_args );
					if ( isset( $post_id ) )
						echo 'Post Content updated for Post: '.$post_id.'<br>';
				} echo 'No external images found for '.$post->ID;

			} else {
				return false;
			}
		} else {
			return false;

		}

		return 'Process Complete';
	}

	/**
	 * Queries the posts based on the form field data
	 * The database MySql queries were <del>inspired</del> jacked from the WordPress Export tool
	 *
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