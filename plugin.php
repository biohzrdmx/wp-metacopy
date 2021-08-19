<?php

/**
 * Plugin Name: MetaCopy
 * Description: Copy metadata from one post to another
 * Author: biohzrdmx
 * Version: 1.0
 * Plugin URI: http://github.com/biohzrdmx/wp-copy-metadata
 * Author URI: http://github.com/biohzrdmx/
 */

	if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

	if( ! class_exists('MetaCopy') ) {

		/**
		 * MetaCopy class
		 */
		class MetaCopy {

			public static function init() {
				$folder = dirname( plugin_basename(__FILE__) );
				$ret = load_plugin_textdomain('metacopy', false, "{$folder}/lang");
			}

			public static function actionAdminMenu() {
				add_menu_page('MetaCopy', 'MetaCopy', 'manage_options', 'metacopy', 'MetaCopy::callbackAdminPage', 'dashicons-edit-page');
				add_submenu_page(null, 'MetaCopy', __('Copy metadata'), 'manage_options', 'metacopy-copy', 'MetaCopy::callbackCopyPage');
			}

			public static function actionEnqueueScripts($hook) {
				if (! in_array( $hook, ['toplevel_page_metacopy', 'admin_page_metacopy-copy'] ) ) {
					return;
				}
				wp_enqueue_style( 'metacopy_admin_css', plugins_url('metacopy.css', __FILE__) );
				wp_enqueue_script( 'metacopy_admin_js', plugins_url('metacopy.js', __FILE__), array('jquery') );
			}

			public static function actionAdminInit() {
				register_setting( 'metacopy', 'metacopy_options' );
				add_settings_section( 'metacopy_settings', __( 'Supported post types', 'metacopy' ), function() {
					?>
						<p><?php _e('The MetaCopy action will be shown for the following post types:', 'metacopy'); ?></p>
					<?php
				}, 'metacopy' );
				add_settings_field( "metacopy_field_post", __('Posts', 'metacopy'), 'MetaCopy::fieldToggle', 'metacopy', 'metacopy_settings', [ 'label_for' => "metacopy_field_post", 'class' => 'metacopy_row' ] );
				add_settings_field( "metacopy_field_page", __('Pages', 'metacopy'), 'MetaCopy::fieldToggle', 'metacopy', 'metacopy_settings', [ 'label_for' => "metacopy_field_page", 'class' => 'metacopy_row' ] );
				$args = array(
					'public'   => true,
					'_builtin' => false
				);
				$types = get_post_types($args, 'objects', 'AND');
				if ($types) {
					foreach ($types as $type) {
						add_settings_field( "metacopy_field_{$type->name}", __($type->label, 'metacopy'), 'MetaCopy::fieldToggle', 'metacopy', 'metacopy_settings', [ 'label_for' => "metacopy_field_{$type->name}", 'class' => 'metacopy_row' ] );
					}
				}
				if ( function_exists('get_field') ) {
					add_settings_section( 'metacopy_acf', __( 'Advanced Custom Fields integration', 'metacopy' ), function() {
						?>
							<p><?php _e('MetaCopy can leverage the ACF functions to copy full fields instead of raw metadata fields.', 'metacopy'); ?></p>
						<?php
					}, 'metacopy' );
					add_settings_field( "metacopy_enable_acf", __('Enable ACF integration', 'metacopy'), 'MetaCopy::fieldToggle', 'metacopy', 'metacopy_acf', [ 'label_for' => "metacopy_enable_acf", 'class' => 'metacopy_row' ] );
				}
			}

			public static function adminSettingsLink($links, $file) {
				$folder = dirname( plugin_basename(__FILE__) );
				$links = (array) $links;
				if ( $file === "{$folder}/plugin.php" && current_user_can( 'manage_options' ) ) {
					$url = admin_url('admin.php?page=metacopy');
					$link = sprintf( '<a href="%s">%s</a>', $url, __( 'Settings', 'metacopy' ) );
					array_unshift($links, $link);
				}
				return $links;
			}

			public static function fieldToggle($args) {
				$options = get_option( 'metacopy_options' );
				?>
					<input type="checkbox" class="js-toggle-switch" id="<?php echo esc_attr( $args['label_for'] ); ?>" <?php echo ( isset( $options[ $args['label_for'] ] ) ? 'checked="checked"' : '' ); ?> name="metacopy_options[<?php echo esc_attr( $args['label_for'] ); ?>]" value="1">
				<?php
			}

			public static function callbackAdminPage() {
				if ( ! current_user_can( 'manage_options' ) ) {
					return;
				}
				if ( isset( $_GET['settings-updated'] ) ) {
					add_settings_error( 'metacopy_messages', 'metacopy_message', __( 'Settings Saved', 'metacopy' ), 'updated' );
				}
				settings_errors( 'metacopy_messages' );
				?>
					<div class="wrap">
						<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
						<form action="options.php" method="post">
							<?php
								settings_fields( 'metacopy' );
								do_settings_sections( 'metacopy' );
								submit_button( __('Save Settings', 'metacopy') );
							?>
						</form>
					</div>
				<?php
			}

			public static function callbackCopyPage() {
				if ( ! current_user_can( 'manage_options' ) ) {
					return;
				}
				if ($_POST) {
					$source_id = self::getItem( $_POST, 'source_id' );
					$dest_id = self::getItem( $_POST, 'page_id', self::getItem($_POST, 'post_id') );
					$fields = self::getItem( $_POST, 'fields' );
					$mode = self::getItem( $_POST, 'mode' );
					$copy_content = self::getItem( $_POST, 'copy_content' );
					$result = __('Error', 'metacopy');
					$log = '';
					$error = 0;
					#
					$source_post = get_post($source_id);
					$dest_post = get_post($dest_id);
					if ( $source_post && $dest_post ) {
						if ( $source_post->post_type == $dest_post->post_type ) {
							if ($fields || $copy_content) {
								switch ($mode) {
									case 'fields':
										$log .= __('Starting, ACF integration enabled', 'metacopy') . PHP_EOL;
										$log .= sprintf( __('Using Advanced Custom Fields plugin version %s', 'metacopy'), acf()->version ) . PHP_EOL;
										if ($fields) {
											foreach ($fields as $field) {
												$log .= sprintf( __('Copying value from %s field...', 'metacopy'), $field ) . PHP_EOL;
												$value = get_field($field, $source_post->ID, false);
												update_field($field, $value, $dest_post->ID);
												$log .= sprintf( __(' > %s', 'metacopy'), $ret ? __('Success', 'metacopy') : __('Duplicated value or Error', 'metacopy') ) . PHP_EOL;
											}
										}
									break;
									case 'metas':
										$log .= __('Starting, ACF integration disabled', 'metacopy') . PHP_EOL;
										if ($fields) {
											foreach ($fields as $field) {
												$log .= sprintf( __('Copying value from %s field...', 'metacopy'), $field ) . PHP_EOL;
												$value = get_post_meta($source_post->ID, $field, true);
												$ret = update_post_meta($dest_post->ID, $field, $value);
												$log .= sprintf( __(' > %s', 'metacopy'), $ret ? __('Success', 'metacopy') : __('Duplicated value or Error', 'metacopy') ) . PHP_EOL;
											}
										}
									break;
								}
								if ($copy_content) {
									$log .= __('Copying content...', 'metacopy') . PHP_EOL;
									$ret = wp_update_post([
										'ID' => $dest_post->ID,
										'post_content' => $source_post->post_content
									]);
									$error += $ret ? 0 : 1;
									$log .= sprintf( __(' > %s', 'metacopy'), $ret > 0 ? __('Success', 'metacopy') : __('Error', 'metacopy') ) . PHP_EOL;
								}
								$log .= __('Finished');
								if (! $error ) {
									add_settings_error( 'metacopy_messages', 'metacopy_message', __( 'The copy process has ended successfully', 'metacopy' ), 'updated' );
									$result = __('Success', 'metacopy');
								} else {
									add_settings_error( 'metacopy_messages', 'metacopy_message', __( 'An error has ocurred, please check the log for more details', 'metacopy' ), 'error' );
								}
							} else {
								add_settings_error( 'metacopy_messages', 'metacopy_message', __( 'Nothing to copy!', 'metacopy' ), 'error' );
								$log .= __('Error: No fields selected for copying, aborting', 'metacopy') . PHP_EOL;
							}
						} else {
							add_settings_error( 'metacopy_messages', 'metacopy_message', __( 'Source and destination items must be of the same type', 'metacopy' ), 'error' );
							$log .= __('Error: Source and destination items are not of the same type, aborting', 'metacopy') . PHP_EOL;
						}
					} else {
						add_settings_error( 'metacopy_messages', 'metacopy_message', __( 'Invalid source or destination items', 'metacopy' ), 'error' );
						$log .= __('Error: Can not find source or destination items, aborting', 'metacopy') . PHP_EOL;
					}
					settings_errors( 'metacopy_messages' );
					?>
						<div class="wrap">
							<h1><?php _e( 'Copy metadata', 'metacopy' ); ?></h1>
							<p><?php printf( __( 'Source item is %s', 'metacopy' ), '<a class="preview-link" href="'.get_the_permalink($source_post->ID).'" target="_blank">'.$source_post->post_title.'</a>' ); ?><p>
							<p><?php printf( __( 'Destination item is %s', 'metacopy' ), '<a class="preview-link" href="'.get_the_permalink($dest_post->ID).'" target="_blank">'.$dest_post->post_title.'</a>' ); ?><p>
							<p><?php printf( __( 'Result %s', 'metacopy' ), "<strong>{$result}</strong>" ); ?></p>
							<table class="form-table" role="presentation">
								<tbody>
									<tr>
										<th>
											<label for="log"><?php echo __( 'Operation log', 'metacopy' ) ?></label>
										</th>
										<td>
											<textarea name="log" id="log" rows="5" cols="30"><?php echo $log; ?></textarea>
										</td>
									</tr>
								</tbody>
							</table>
						</div>
					<?php
				} else {
					?>
						<div class="wrap">
							<h1><?php _e( 'Copy metadata', 'metacopy' ); ?></h1>
							<form action="" method="post">
								<h2><?php _e( 'Available fields', 'metacopy' ); ?></h2>
								<?php
									$post_id = $_GET['post'];
									$source = get_post($post_id);
									$options = get_option( 'metacopy_options' );
									$enable_acf = function_exists('get_field_objects') && self::getItem($options, 'metacopy_enable_acf');
									?>
										<p><?php printf( __( 'Please select which fields will be copied from %s', 'metacopy' ), '<a class="preview-link" href="'.get_the_permalink($source->ID).'" target="_blank">'.$source->post_title.'</a>' ); ?></p>
										<input type="hidden" name="source_id" value="<?php echo esc_html($post_id) ?>">
									<?php
									$fields = null;
									if ($enable_acf) {
										$fields = get_field_objects($post_id, false);
										if ($fields) {
											?>
												<input type="hidden" name="mode" value="fields">
												<table class="form-table" role="presentation">
													<tbody>
											<?php
												foreach ($fields as $key => $value) {
													?>
														<tr class="metacopy_row">
															<th scope="row">
																<label for="metacopy_field_post"><?php echo esc_html( self::getItem($value, 'label', $key) ); ?></label>
															</th>
															<td>
																<input type="checkbox" class="js-toggle-switch" id="" name="fields[]" value="<?php echo esc_html($key); ?>">
															</td>
														</tr>
													<?php
												}
											?>
													</tbody>
												</table>
											<?php
										} else {
											?>
												<p><em><?php _e('There are no fields to copy'); ?></em></p>
											<?php
										}
									} else {
										$fields = get_post_custom($post_id);
										if ($fields) {
											?>
												<input type="hidden" name="mode" value="metas">
												<table class="form-table" role="presentation">
													<tbody>
											<?php
												foreach ($fields as $key => $value) {
													?>
														<tr class="metacopy_row">
															<th scope="row">
																<label for="metacopy_field_post"><?php echo esc_html($key); ?></label>
															</th>
															<td>
																<input type="checkbox" class="js-toggle-switch" id="" name="fields[]" value="<?php echo esc_html($key); ?>">
															</td>
														</tr>
													<?php
												}
											?>
													</tbody>
												</table>
											<?php
										} else {
											?>
												<p><em><?php _e('There are no fields to copy'); ?></em></p>
											<?php
										}
									}
									if ($fields) {
										?>
											<h2><?php _e( 'Destination entry', 'metacopy' ); ?></h2>
											<p><?php _e( 'Please select which entry will receive the copied fields:', 'metacopy' ); ?></p>
											<?php
												$post_type = get_post_type_object($source->post_type);
												if ($post_type->hierarchical) {
													wp_dropdown_pages([
														'sort_order'   => 'ASC',
														'exclude' => [ $source->ID ],
														'sort_column'  => 'post_title',
														'hierarchical' => false,
														'post_type' => $source->post_type
													]);
												} else {
													self::wp_dropdown_posts([
														'order'   => 'ASC',
														'orderby'  => 'post_title',
														'post__not_in' => [ $source->ID ],
														'post_type' => $source->post_type
													]);
												}
											?>
										<?php
									}
								?>
								<br><br>
								<h2><?php _e( 'Advanced options', 'metacopy' ); ?></h2>
								<p><?php _e( 'Extra options for a more fine-grained copy', 'metacopy' ); ?></p>
								<table class="form-table" role="presentation">
									<tbody>
										<tr class="metacopy_row">
											<th scope="row">
												<label for="metacopy_field_post"><?php _e('Copy content', 'metacopy'); ?></label>
											</th>
											<td>
												<input type="checkbox" class="js-toggle-switch" id="" name="copy_content" value="1">
											</td>
										</tr>
									</tbody>
								</table>
								<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Copy metadata', 'metacopy'); ?>"></p>
							</form>
						</div>
					<?php
				}
			}

			public static function actionRowItems($actions, $post) {
				$options = get_option( 'metacopy_options' );
				$key = "metacopy_field_{$post->post_type}";
				if ( self::getItem($options, $key) == 1 ) {
					$label = __('MetaCopy', 'metacopy');
					$url = admin_url("admin.php?page=metacopy-copy&amp;post={$post->ID}");
					$action = "<a href=\"{$url}\">{$label}</a>";
					$actions[''] = $action;
				}
				return $actions;
			}

			/**
			 * Get an item from an array/object, or a default value if it's not set
			 * @param  mixed $var      Array or object
			 * @param  mixed $key      Key or index, depending on the array/object
			 * @param  mixed $default  A default value to return if the item it's not in the array/object
			 * @return mixed           The requested item (if present) or the default value
			 */
			protected static function getItem($var, $key, $default = '') {
				return is_object($var) ?
					( isset( $var->$key ) ? $var->$key : $default ) :
					( isset( $var[$key] ) ? $var[$key] : $default );
			}

			// protected static function duplicate($post_id) {
			// 	$title   = get_the_title($post_id);
			// 	$oldpost = get_post($post_id);
			// 	$post    = array(
			// 		'post_title' => $title,
			// 		'post_status' => $oldpost->post_status,
			// 		'post_type' => $oldpost->post_type,
			// 		'post_content' => $oldpost->post_content,
			// 		'post_excerpt' => $oldpost->post_excerpt,
			// 		'post_parent' => $oldpost->post_parent,
			// 		'post_password' => $oldpost->post_password,
			// 		'comment_status' => $oldpost->comment_status,
			// 		'ping_status' => $oldpost->ping_status,
			// 		'post_author' => get_current_user_id()
			// 	);
			// 	$new_post_id = wp_insert_post($post);
			// 	// Copy post metadata
			// 	$data = get_post_custom($post_id);
			// 	foreach ( $data as $key => $values) {
			// 		foreach ($values as $value) {
			// 			add_post_meta( $new_post_id, $key, $value );
			// 		}
			// 	}

			// 	return $new_post_id;
			// }

			protected static function wp_dropdown_posts( $args = '' ) {
				$defaults = array(
					'selected'              => FALSE,
					'pagination'            => FALSE,
					'posts_per_page'        => -1,
					'post_status'           => 'publish',
					'cache_results'         => TRUE,
					'cache_post_meta_cache' => TRUE,
					'echo'                  => 1,
					'select_name'           => 'post_id',
					'id'                    => '',
					'class'                 => '',
					'show'                  => 'post_title',
					'show_callback'         => NULL,
					'show_option_all'       => NULL,
					'show_option_none'      => NULL,
					'option_none_value'     => '',
					'multi'                 => FALSE,
					'value_field'           => 'ID',
					'order'                 => 'ASC',
					'orderby'               => 'post_title',
				);
				$r = wp_parse_args( $args, $defaults );
				$posts  = get_posts( $r );
				$output = '';
				$show = $r['show'];
				if( ! empty($posts) ) {
					$name = esc_attr( $r['select_name'] );
					if( $r['multi'] && ! $r['id'] ) {
						$id = '';
					} else {
						$id = $r['id'] ? " id='" . esc_attr( $r['id'] ) . "'" : " id='$name'";
					}
					$output = "<select name='{$name}'{$id} class='" . esc_attr( $r['class'] ) . "'>\n";
					if( $r['show_option_all'] ) {
						$output .= "\t<option value='0'>{$r['show_option_all']}</option>\n";
					}
					if( $r['show_option_none'] ) {
						$_selected = selected( $r['show_option_none'], $r['selected'], FALSE );
						$output .= "\t<option value='" . esc_attr( $r['option_none_value'] ) . "'$_selected>{$r['show_option_none']}</option>\n";
					}
					foreach( (array) $posts as $post ) {
						$value   = ! isset($r['value_field']) || ! isset($post->{$r['value_field']}) ? $post->ID : $post->{$r['value_field']};
						$_selected = selected( $value, $r['selected'], FALSE );
						$display = ! empty($post->$show) ? $post->$show : sprintf( __( '#%d (no title)' ), $post->ID );
						if( $r['show_callback'] ) $display = call_user_func( $r['show_callback'], $display, $post->ID );
						$output .= "\t<option value='{$value}'{$_selected}>" . esc_html( $display ) . "</option>\n";
					}
					$output .= "</select>";
				}
				/**
				 * Filter the HTML output of a list of pages as a drop down.
				 *
				 * @since 1.0.0
				 *
				 * @param string $output HTML output for drop down list of posts.
				 * @param array  $r      The parsed arguments array.
				 * @param array  $posts  List of WP_Post objects returned by `get_posts()`
				 */
				$html = apply_filters( 'wp_dropdown_posts', $output, $r, $posts );
				if( $r['echo'] ) {
					echo $html;
				}
				return $html;
			}
		}

		add_action( 'init', 'MetaCopy::init' );
		add_action( 'admin_init', 'MetaCopy::actionAdminInit' );
		add_action( 'admin_menu', 'MetaCopy::actionAdminMenu' );
		add_filter( 'post_row_actions','MetaCopy::actionRowItems', 10, 2 );
		add_filter( 'page_row_actions','MetaCopy::actionRowItems', 10, 2 );
		add_action( 'admin_enqueue_scripts', 'MetaCopy::actionEnqueueScripts' );
		add_filter( 'plugin_action_links', 'MetaCopy::adminSettingsLink', 10, 5 );
	}
?>