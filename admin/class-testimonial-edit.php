<?php
/**
 * New/Edit testimonial admin screen.
 *
 * @package    Toot
 * @subpackage Admin
 * @author     Justin Tadlock <justintadlock@gmail.com>
 * @copyright  Copyright (c) 2017, Justin Tadlock
 * @link       http://themehybrid.com/plugins/toot
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

/**
 * Testimonial edit screen functionality.
 *
 * @since  1.0.0
 * @access public
 */
final class Toot_Testimonial_Edit {

	/**
	 * Sets up the needed actions.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	private function __construct() {

		add_action( 'load-post.php',     array( $this, 'load' ) );
		add_action( 'load-post-new.php', array( $this, 'load' ) );

		// Add the help tabs.
		add_action( 'toot_load_testimonial_edit', array( $this, 'add_help_tabs' ) );
	}

	/**
	 * Runs on the page load. Checks if we're viewing the testimonial post type and adds
	 * the appropriate actions/filters for the page.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function load() {

		$screen       = get_current_screen();
		$testimonial_type = toot_get_testimonial_post_type();

		// Bail if not on the testimonials screen.
		if ( empty( $screen->post_type ) || $testimonial_type !== $screen->post_type )
			return;

		// Custom action for loading the edit testimonial screen.
		do_action( 'toot_load_testimonial_edit' );

		// Enqueue scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );

		// Print custom styles.
		add_action( 'admin_head', array( $this, 'print_styles' ) );

		// Add custom option to the publish/submit meta box.
		add_action( 'post_submitbox_misc_actions', array( $this, 'submitbox_misc_actions' ) );

		// Save metadata on post save.
		add_action( 'save_post', array( $this, 'update' ) );

		// Filter the post author drop-down.
		add_filter( 'wp_dropdown_users_args', array( $this, 'dropdown_users_args' ), 10, 2 );
	}

	/**
	 * Load scripts and styles.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function enqueue() {

		wp_enqueue_script( 'toot-edit-testimonial' );
	}

	/**
	 * Print styles.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function print_styles() { ?>

		<style type="text/css">
			.misc-pub-testimonial-sticky .dashicons {
				color: rgb( 130, 135, 140 );
			}

			.misc-pub-testimonial-sticky label {
				display: block;
				margin:  8px 0 8px 2px;
			}
		</style>
	<?php }

	/**
	 * Callback on the `post_submitbox_misc_actions` hook (submit meta box). This handles
	 * the output of the sticky testimonial feature.
	 *
	 * @note   Prior to WP 4.4.0, the `$post` parameter was not passed.
	 * @since  1.0.0
	 * @access public
	 * @param  object  $post
	 * @return void
	 */
	public function submitbox_misc_actions( $post = '' ) {

		// Pre-4.4.0 compatibility.
		if ( ! $post ) {
			global $post;
		}

		// Get the post type object.
		$post_type_object = get_post_type_object( toot_get_testimonial_post_type() );

		// Is the testimonial sticky?
		$is_sticky = toot_is_testimonial_sticky( $post->ID );

		// Set the label based on whether the testimonial is sticky.
		$label = $is_sticky ? esc_html__( 'Sticky', 'toot' ) : esc_html__( 'Not Sticky', 'toot' ); ?>

		<div class="misc-pub-section curtime misc-pub-testimonial-sticky">

			<?php wp_nonce_field( "stick_testimonial_{$post->ID}", 'toot_sticky_nonce' ); ?>

			<i class="dashicons dashicons-sticky"></i>
			<?php printf( esc_html__( 'Sticky: %s', 'toot' ), "<strong class='toot-sticky-status'>{$label}</strong>" ); ?>

			<?php if ( current_user_can( $post_type_object->cap->publish_posts ) ) : ?>

				<a href="#toot-sticky-edit" class="toot-edit-sticky"><span aria-hidden="true"><?php esc_html_e( 'Edit', 'toot' ); ?></span> <span class="screen-reader-text"><?php esc_html_e( 'Edit sticky status', 'toot' ); ?></span></a>

				<div id="toot-sticky-edit" class="hide-if-js">
					<label>
						<input type="checkbox" name="toot_testimonial_sticky" id="toot-testimonial-sticky" <?php checked( $is_sticky ); ?> value="true" />
						<?php esc_html_e( 'Stick to the testimonials archive', 'toot' ); ?>
					</label>
					<a href="#toot-testimonial-sticky" class="toot-save-sticky hide-if-no-js button"><?php esc_html_e( 'OK', 'custom-content-portolio' ); ?></a>
					<a href="#toot-testimonial-sticky" class="toot-cancel-sticky hide-if-no-js button-cancel"><?php esc_html_e( 'Cancel', 'custom-content-portolio' ); ?></a>
				</div><!-- #toot-sticky-edit -->

			<?php endif; ?>

		</div><!-- .misc-pub-testimonial-sticky -->
	<?php }

	/**
	 * Save testimonial details settings on post save.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  int     $post_id
	 * @return void
	 */
	public function update( $post_id ) {

		// Verify the nonce.
		if ( ! toot_verify_nonce_post( "stick_testimonial_{$post_id}", 'toot_sticky_nonce' ) )
			return;

		// Is the sticky checkbox checked?
		$should_stick = ! empty( $_POST['toot_testimonial_sticky'] );

		// If checked, add the testimonial if it is not sticky.
		if ( $should_stick && ! toot_is_testimonial_sticky( $post_id ) )
			toot_add_sticky_testimonial( $post_id );

		// If not checked, remove the testimonial if it is sticky.
		elseif ( ! $should_stick && toot_is_testimonial_sticky( $post_id ) )
			toot_remove_sticky_testimonial( $post_id );
	}

	/**
	 * Filter on the post author drop-down (used in the "Author" meta box) to only show users
	 * of roles that have the correct capability for editing testimonials.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  array   $args
	 * @param  array   $r
	 * @global object  $wp_roles
	 * @global object  $post
	 * @return array
	 */
	function dropdown_users_args( $args, $r ) {
		global $wp_roles, $post;

		// Check that this is the correct drop-down.
		if ( 'post_author_override' === $r['name'] && toot_get_testimonial_post_type() === $post->post_type ) {

			$roles = array();

			// Loop through the available roles.
			foreach ( $wp_roles->roles as $name => $role ) {

				// Get the edit posts cap.
				$cap = get_post_type_object( toot_get_testimonial_post_type() )->cap->edit_posts;

				// If the role is granted the edit posts cap, add it.
				if ( isset( $role['capabilities'][ $cap ] ) && true === $role['capabilities'][ $cap ] )
					$roles[] = $name;
			}

			// If we have roles, change the args to only get users of those roles.
			if ( $roles ) {
				$args['who']      = '';
				$args['role__in'] = $roles;
			}
		}

		return $args;
	}

	/**
	 * Adds custom help tabs.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function add_help_tabs() {

		$screen = get_current_screen();

		// Title and editor help tab.
		$screen->add_help_tab(
			array(
				'id'       => 'title_editor',
				'title'    => esc_html__( 'Title and Editor', 'toot' ),
				'callback' => array( $this, 'help_tab_title_editor' )
			)
		);

		// Testimonial details help tab.
		$screen->add_help_tab(
			array(
				'id'       => 'testimonial_details',
				'title'    => esc_html__( 'Testimonial Details', 'toot' ),
				'callback' => array( $this, 'help_tab_testimonial_details' )
			)
		);

		// Set the help sidebar.
		$screen->set_help_sidebar( toot_get_help_sidebar_text() );
	}

	/**
	 * Displays the title and editor help tab.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function help_tab_title_editor() { ?>

		<ul>
			<li><?php _e( "<strong>Title:</strong> Enter a title for your testimonial. After you enter a title, you'll see the permalink below, which you can edit.", 'toot' ); ?></li>
			<li><?php _e( '<strong>Editor:</strong> The editor allows you to add or edit content for your testimonial. You can insert text, media, or shortcodes.', 'toot' ); ?></li>
		</ul>
	<?php }

	/**
	 * Displays the testimonial details help tab.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function help_tab_testimonial_details() { ?>

		<p>
			<?php esc_html_e( 'The Testimonial Details meta box allows you to customize the details of your testimonial. All fields are optional.', 'toot' ); ?>
		</p>

		<ul>
			<li><?php _e( '<strong>URL:</strong> The URL to the Web site or page associated with the testimonial, such as a client Web site.', 'toot' ); ?></li>
			<li><?php _e( '<strong>Email:</strong> Todo - description', 'toot' ); ?></li>
		</ul>
	<?php }

	/**
	 * Returns the instance.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return object
	 */
	public static function get_instance() {

		static $instance = null;

		if ( is_null( $instance ) )
			$instance = new self;

		return $instance;
	}
}

Toot_Testimonial_Edit::get_instance();
