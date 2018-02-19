<?php
/*
Plugin Name: WP Post Relationships (WPPR)
Plugin URI: https://github.com/sethrubenstein/wp-post-relationships/
Description: A schema for creating post -> child post relationships. <strong>Requires Advanced Custom Fields</strong>
Version: 1.0
Author: Seth Rubenstein
Author URI: http://sethrubenstein.info
License: GPL3
*/

// don't call the file directly
if ( ! defined( 'ABSPATH' ) )
	return;

$wppr_plugin_file = __FILE__;

/* Find our plugin, wherever it may live! */
if ( isset( $plugin ) ) {
	$wppr_plugin_file = $plugin;
}
else if ( isset( $mu_plugin ) ) {
	$wppr_plugin_file = $mu_plugin;
}
else if ( isset( $network_plugin ) ) {
	$wppr_plugin_file = $network_plugin;
}

define( 'WPPR_FILE', $wppr_plugin_file );

/**
 * post_relationships class
 *
 * @class post_relationships	The class that holds the entire post_relationships plugin
 */
class post_relationships {

	/**
	 * @var $name	Variable for post_relationships used throughout the plugin
	 */
	protected $name = "WP Post Relationships";

	/**
	 * @var $nonce_key	A security key used internally by the plugin
	 */
	protected $nonce_key = '+Y|*Ec/-\s3';

	/**
	 * PHP 5.3 and lower compatibility
	 *
	 * @uses post_relationships::__construct()
	 *
	 */
	public function post_relationships() {
		$this->__construct();
	}

	/**
	 * Constructor for the post_relationships class
	 *
	 * Sets up all the appropriate hooks and actions
	 * within our plugin.
	 *
	 * @uses register_activation_hook()
	 * @uses register_deactivation_hook()
	 * @uses is_admin()
	 * @uses add_action()
	 *
	 */
	public function __construct() {
		register_activation_hook( WPPR_FILE, array( &$this, 'activate' ) );
		register_deactivation_hook( WPPR_FILE, array( &$this, 'deactivate' ) );
        add_action( 'acf/init', array($this, 'acf_define_fields') );
        add_action( 'admin_init', array( $this, 'admin_hooks' ) );
	}

	/**
	 * Initializes the post_relationships() class
	 *
	 * Checks for an existing post_relationships() instance
	 * and if it doesn't find one, creates it.
	 *
	 * @uses post_relationships()
	 *
	 */
	public function &init() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new post_relationships();
		}

		return $instance;
	}

	/**
	 * Placeholder for activation function
	 *
	 * Nothing being called here yet.
	 */
	public function activate() {

	}

	/**
	 * Placeholder for deactivation function
	 *
	 * Nothing being called here yet.
	 */
	public function deactivate() {

	}

    /**
     * Includes all filters and actions that need to happen in admin
     * @return [type] [description]
     */
    public function admin_hooks() {
        add_action( 'pre_get_posts', array($this, 'query_hide_children') );
        add_filter( 'the_title', array($this, 'indicate_child_post') );
        add_action( 'admin_footer', array($this, 'child_post_metabox') );
        add_action( 'wp_ajax_ajax_remove_parent_relationship', array($this,'ajax_remove_parent_relationship') );

        add_filter( 'acf/fields/relationship/query', array($this, 'acf_query_hide_current_post'), 10, 3 );
        add_action( 'acf/save_post', array($this, 'acf_save_set_children'), 20 ); // The taxonomy fields get saved at priority 15
    }

    /**
     * [acf_define_fields description]
     * @return [type] [description]
     */
	public function acf_define_fields() {
        $fields = array(
            array(
                'key' => 'field_594024c63e38e',
                'label' => 'Parent Post',
                'name' => '',
                'type' => 'message',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'message' => '<div id="js-child-post-attach-here"></div>',
                'new_lines' => '',
                'esc_html' => 0,
            ),
            array(
                'key' => 'field_562696s96cc85',
                'label' => 'Child Posts',
                'name' => 'multi_section_report',
                'type' => 'relationship',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'post_type' => array(
                    0 => 'post',
                ),
                'taxonomy' => array(),
                'filters' => array(
                    0 => 'search',
                ),
                'elements' => '',
                'min' => '',
                'max' => '',
                'return_format' => 'id',
            )
        );

        $locations = array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'post',
                ),
            )
        );

        // You can add your own additional fields OR modify the one's we've created but, be aware that could casue problems - feel free to add fields however.
        $fields = apply_filters( 'wp_post_relationship_fields', $fields );
        // You can define where post relationship fields will appear. By default it's just "post" post type.
        $locations = apply_filters( 'wp_post_relationship_locations', $locations );

        acf_add_local_field_group(array(
            'key' => 'group_591ce1c12b09g',
            'title' => 'Post Relationships',
            'fields' => $fields,
            'location' => $locations,
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => 1,
            'description' => ''
        ));
    }

	/////// Helpers //////
	/**
     * * $post_relationships->is_post_a_child();
     * @param  [type]  $post_ID [description]
     * @return boolean          [description]
     */
	public function is_post_a_child($post_ID) {
		if  ( get_post($post_ID)->post_parent == 0 ) {
	        return false;
	    } else {
	        return true;
	    }
	}
    /**
     * $post_relationships->get_parent_post();
     * Pass through a post ID and if the post has a parent and is therefore a child it will return the parent's post object. Otherwise, if the post given is a lone post it will return the passed post.
     * @param  integer $post_ID - The post to check.
     * @return object           - return a WP_Post object of a child's parent post.
     */
	public function get_parent_post($post_ID) {
		$parent_post_ID = wp_get_post_parent_id( $post_ID );
	    $parent_post = get_post( $parent_post_ID );
	    return $parent_post;
	}

	/**
	 * Modify the main query and hide posts that are children.
	 * @param $query
	 * @return modified WP_Query with post_parent set to 0.
	 */
	public function query_hide_children($query) {
		if ( is_admin() || !$query->is_main_query() ) {
			return;
		}
		if ( is_category() || is_author() ) {
			$query->set( 'post_parent', 0 );
			return;
		}
	}

	/**
	 * Modify the post title if it's a child post in the admin view.
	 * @param title
	 */
	public function indicate_child_post($title) {
		$valid_post_types = array( 'post' );

		// Not interested if it's not the admin screen...
		if( !is_admin() || is_customize_preview() ) {
			return $title;
		}
		// Not interested if we're not looking at the edit.php screen...
		$screen = get_current_screen();
		if( !$screen || $screen->parent_base != 'edit' ) {
			return $title;
		}

        global $post;
		// We're white-listing valid post_types to apply this to. Pages already do this behavior which would screw things up...
		if( !in_array( $post->post_type, $valid_post_types ) ) {
			return $title;
		}

		// Add a dash before the title...
		if( $post->post_parent != 0 ) {
			$title = '&mdash; ' . $post->post_title;
		}

		return $title;
	}

	/**
	 * Displays information about the parent post on children posts.
	 */
	public function child_post_metabox() {
		// If editing an existing post
		if ( get_current_screen()->parent_base == 'edit' ) {
			global $post;
			if ( true == $this->is_post_a_child($post->ID) ) {
				echo '<div id="js-post-parent-info" style="display:none;">';
			    echo $this->get_parent_post($post->ID)->post_title;
			    echo '<p><a href="'.get_edit_post_link( $this->get_parent_post($post->ID)->ID ).'" class="button">Edit the parent post</a> <button id="remove-child" class="button">Remove this relationship</button></p>';
				echo '</div>';
				?>
				<style>
				#js-post-parent-info { display:block!important; }
				[data-key="field_562696s96cc85"] { display:none!important; }
				</style>
				<script>
				jQuery(document).ready(function(){
					jQuery('#js-post-parent-info').prependTo('#js-child-post-attach-here');
					jQuery("#remove-child").click(function(){
						jQuery.post("<?php echo admin_url( 'admin-ajax.php' );?>", {
							action:   "ajax_remove_parent_relationship",
							post_id:  <?php echo $post->ID;?>
						});
					});
				});
				</script>
				<?php
			} else {
				?>
				<style>[data-key="field_594024c63e38e"] {display:none;}</style>
				<?php
			}
		} else {
			?>
			<style>[data-key="field_594024c63e38e"] {display:none;}</style>
			<?php
		}
	}

	/**
	 * This little ajax function will unset any child post and remove the child relationship.
	 * Effectively it resets a child post back to a parent post.
	 * @param $post_IDs needs to be an array even if its just one post id.
	 */
	public function ajax_remove_parent_relationship(){
		if( isset( $_POST['post_id'] ) && is_numeric( $_POST['post_id'] ) ) {
			// Get the parent post id and update that post with the child post removed.
			$parent_post_ID = $this->get_parent_post($_POST['post_id'])->ID;
			$child_posts = get_field('multi_section_report', $parent_post_ID);
			$remove = array_search($_POST['post_id'], $child_posts);
			unset($child_posts[$remove]);
			update_field('multi_section_report', $child_posts, $parent_post_ID);
			// TODO: This isn't working correctly. FIXME
			// Now actually unset the post_parent.
			$updates = array(
				'ID' => $_POST['post_id'],
				'post_parent' => 0
			);
			// Update the post into the database
			wp_update_post( $updates );
		}
		die();
	}

    /**
     * [set_children description]
     * @param [type] $parent_post_id [description]
     * @param [type] $child_posts    [description]
     */
    public function set_children( $parent_post_id, $child_posts ) {
	    // bail early if no ACF data
	    if( !empty($parent_post_id) && !empty($child_posts) ) {
			// Get Parent Post Information
			$taxonomies = get_taxonomies();
            $parent_taxonomies = wp_get_post_terms( $parent_post_id, $taxonomies, $args );

            // For each $child_posts update the post_parent to $parent_post_id
	        foreach( $child_posts as $order => $post_id ) {
				$updates = array(
					'ID' => $post_id,
					'post_parent' => $parent_post_id
				);

                $updates = apply_filters( 'wp_post_relationship_set_children', $updates );
                do_action( 'wp_post_relationship_set_children', array( 'parent'=> $parent_post_id, 'id' => $post_id, 'taxonomies' => $parent_taxonomies ) );
                // Update the post into the database
				wp_update_post( $updates );
	        }

	    }
	}

    /**
     * [unset_children description]
     * @param array $child_posts [description]
     */
    public function unset_children( $child_posts = array() ) {
        if ( !empty($child_posts) ) {
            foreach ($child_posts as $post_id) {
                $updates = array(
                    'ID' => $post_id,
                    'post_parent' => 0
                );
                $updates = apply_filters( 'wp_post_relationship_unset_children', $updates );
                do_action( 'wp_post_relationship_unset_children', array( 'id' => $post_id ) );
                // Update the post into the database
                wp_update_post( $updates );
            }

        }
    }

	/**
	 * It'd be crazy to set the post you're currently on as a child of it self. So to prevent this from happening we wont show the current post in the post relationship field. This will apply to all relationship field types because why would you ever select yourself.
	 * NOTE This is a broader filter that effects all relationship fields.
	 */
	public function acf_query_hide_current_post( $args, $field, $post ) {
	    $args['post__not_in'] = array($post);
	    return $args;
	}

	/**
	 * This ACF hook onto the Multi Section Report field takes the associated posts and makes them children of the parent.
	 * @param $parent_post_id
	 */
	public function acf_save_set_children( $parent_post_id ) {
	    $current_data = $_POST['acf']['field_562696s96cc85']; // The data on the post NOW, the data that was in the field.

	    // bail early if no ACF data
	    if( !empty($current_data) ) {

			// The data on the post PREVIOUSLY, the data has already been saved to meta.
			$previous_data = get_field('multi_section_report');

	        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
	            return $parent_post_id;
	        }
	        if( defined('DOING_AJAX') && DOING_AJAX ) {
	            return $parent_post_id;
	        }

            // Set Children Posts
			$this->set_children($parent_post_id, $current_data);

            // If a post has been removed from the previous data to the now data then lets take that post id and add it to an array.
            // Unset Children Posts
			$data_diff = array_diff($previous_data, $current_data);
			if ( !empty($data_diff) ) {
				$this->unset_children($data_diff);
			}

	    }
	}

} // post_relationships

new post_relationships();
