<?php
/*
Plugin Name:	DO Post Ideas
Description:	A simple plugin to let editor add Post Ideas to the database and let Contributor assign Post Ideas to themselves.
Version:		1.0.0
Author:			Damien Oh
Author URI:		http://damienoh.com/
*/

if ( ! class_exists( 'DO_Posts_Ideas' ) ) {

	class DO_Posts_Ideas {

		public function __construct() {
			add_action( 'init', array( $this, 'register_post_ideas_post_type' ) );
			add_action( 'admin_menu', array( $this, 'register_post_ideas_menu' ) );
			add_action( 'add_meta_boxes_post-idea', array( $this, 'add_meta_boxes' ) );
			add_action( 'add_meta_boxes_post', array( $this, 'add_post_meta_boxes' ) );
			add_action( 'save_post', array( $this, 'save_meta' ) );

			require_once( 'includes/class-post-ideas-list.php' );
		}

		function register_post_ideas_post_type() {

			$args = array(
				'label' => 'Post Ideas',
				'description' => '',
				'public' => true,
				'publicly_queryable' => false,
				'show_ui' => true,
				'show_in_menu' => false,
				'hierarchical' => false,
				'rewrite' => false,
				'query_var' => false,
				'exclude_from_search' => true,
				'menu_position' => 8,
				'supports' => array( 'title', 'author', 'comments' ),
				'taxonomies' => array( 'category' ),
				'labels' => array(
					'name' => 'Post Ideas',
					'singular_name' => 'Post Idea',
					'menu_name' => 'Post Ideas',
					'add_new' => 'Add Post Idea',
					'add_new_item' => 'Add New Post Idea',
					'edit' => 'Edit',
					'edit_item' => 'Edit Post Idea',
					'new_item' => 'New Post Idea',
					'view' => 'View Post Idea',
					'view_item' => 'View Post Idea',
					'search_items' => 'Search Post Ideas',
					'not_found' => 'No Post Ideas Found',
					'not_found_in_trash' => 'No Post Ideas Found in Trash',
					'parent' => 'Parent Post Idea',
				),
			);
			register_post_type( 'post-idea', $args );
		}

		public function register_post_ideas_menu() {

			add_menu_page( 'Post Ideas', 'Post Ideas', 'edit_posts', 'post-ideas', array( $this, 'render_post_ideas' ), '', 6 );

			add_submenu_page( 'post-ideas', 'Post Ideas', 'Post Ideas (editor\'s mode)', 'publish_posts', 'edit.php?post_type=post-idea' );

			add_submenu_page( 'post-ideas', 'Add Post Ideas', 'Add Post Idea', 'publish_posts', 'post-new.php?post_type=post-idea' );

			add_submenu_page( 'post-ideas', 'Restore Assigned Post to Post Idea', 'Restore Post Idea', 'manage_options', 'restore-post-idea', array( $this, 'restore_post_idea' ) );
		}

		public function render_post_ideas() {
			if ( isset( $_GET['action'] ) ) {
				switch ( $_GET['action'] ) {
					case 'assign':
						$this->assign_post_idea();
						break;
					case 'bulk-assign':
						$this->assign_post_idea( $bulk_assign = true );
						break;
					default:
						if ( isset( $_GET['action2'] ) ) {
							$action2 = sanitize_text_field( wp_unslash( $_GET['action2'] ) );
							if ( 'bulk-assign' === $action2 ) {
								$this->assign_post_idea( $bulk_assign = true );
							}
						}
						break;
				}
			}
			?>
			<h3><?php _e( 'About Post Ideas', 'do_post_ideas' ); ?></h3>
			<?php $message = '<p>This is the place where you can find post topics to write about. Here is what you need to do:</p>';
				$message .= '<ol>';
				$message .= '<li>Go through the list and read the content brief, length of article and submission date carefully</li>';
				$message .= '<li>If there is any post idea that you are confident of writing and completing before the submission date, click the "<strong>Assign to me</strong>" button. This will create a working draft for you in the Posts section.</li>';
				$message .= '<li>While writing and editing the article, change the post status to "<strong>In Progress</strong>"</li>';
				$message .= '<li>Once you are done with your article, change the status to "<strong>Pending Review</strong>" and submit it for review.</li>';
				$message .= '</ol>';
			echo apply_filters( 'do_post_ideas_message', $message ); ?>

			<form method="get">
				<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
				<?php $list_table = new Post_Ideas_List;
				$list_table->prepare_items();
				$list_table->display(); ?>
			</form>
			<?php
		}

		public function add_meta_boxes() {
			add_meta_box(
				'post-idea-meta',
				'Editorial Metadata',
				array( $this, 'setup_meta_box' ),
				'post-idea',
				'normal',
				'high'
			);
		}

		public function add_post_meta_boxes() {
			add_meta_box(
				'editorial-meta',
				'Editorial Metadata',
				array( $this, 'setup_post_meta_box' ),
				'post',
				'side',
				'core'
			);
		}

		public function setup_meta_box( $post ) {
			$postidea_meta = get_post_meta( $post->ID, '_postidea_meta', true );
			if ( empty( $postidea_meta ) || ! is_array( $postidea_meta ) ) {
				$postidea_meta = array(
					'description' => '',
					'keywords' => '',
				);
			}
			?>
			<input type="hidden" name="postidea-nonce" id="postidea-nonce" value="<?php echo wp_create_nonce( 'postidea-meta-box' ); ?>"/>
			<table class="form-table">
				<tr>
					<th scope="row">
						<?php _e( 'Content Brief:', 'do_post_idea' ); ?>
					</th>
					<td>
						<textarea name="pi[description]"
						          placeholder="Enter the details on what the writer should cover for this post topic"
						          style="width:600px;height:200px;"><?php echo stripslashes( $postidea_meta['description'] ); ?></textarea>

					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php _e( 'SEO keywords:', 'do_post_idea' ); ?>
					</th>
					<td>
						<input type="text" name="pi[keywords]"
						       value="<?php echo stripslashes( $postidea_meta['keywords'] ); ?>"
						       placeholder="Enter the keyword to be used in the post" style="width:600px;"/>

					</td>
				</tr>
			</table>
			<?php
		}

		public function setup_post_meta_box( $post ) {
			$postidea_meta = get_post_meta( $post->ID, '_postidea_meta', true );
			if ( empty( $postidea_meta ) || ! is_array( $postidea_meta ) ) {
				$postidea_meta = array(
					'description' => '',
					'keywords' => '',
				);
			}
			?>
			<div style="margin-bottom:10px;">
				<label style="font-weight:bold;">Content Brief</label><br/>
				<span class="description">What the post need to cover.</span>
				<textarea name="pi[description]"
				          style="width:98%;"><?php echo stripslashes( $postidea_meta['description'] ); ?></textarea>
			</div>
			<div style="margin-bottom:10px;">
				<label style="font-weight:bold;">Keywords</label><br/>
				<span class="description">SEO keywords to use for the post.</span>
				<input type="text" name="pi[keywords]" value="<?php echo stripslashes( $postidea_meta['keywords'] ); ?>"
				       style="width:98%;"/>
			</div>
			<input type="hidden" name="postidea-nonce" id="postidea-nonce"
			       value="<?php echo wp_create_nonce( 'postidea-meta-box' ); ?>"/>
			<?php
		}

		public function save_meta( $post_id ) {

			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			if ( ! wp_verify_nonce( $_POST['postidea-nonce'], 'postidea-meta-box' ) ) {
				return;
			}

			// Check permissions
			if ( ! current_user_can( 'publish_post', $post_id ) ) {
				return;
			}

			$postidea_meta = $_POST['pi'];

			if ( ! empty( $postidea_meta ) && is_array( $postidea_meta ) ) {
				update_post_meta( $post_id, '_postidea_meta', $postidea_meta );
			}

			return;
		}

		public function assign_post_idea( $bulk_assign = false ) {
			$author_id = get_current_user_id();
			if ( $bulk_assign ) {
				$nonce = $_REQUEST['_wpnonce'];
				$post_ids = $_REQUEST['post_id'];
				if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'do-post-ideas-assignment' ) ) {
					echo '<div class="error"><p>You have no permission to perform this action</p></div>';
				} else {
					if ( is_array( $post_ids ) ) {
						if ( current_user_can( 'edit_posts' ) ) {
							foreach ( $post_ids as $post_id ) {
								$status = apply_filters( 'post_idea_assigned_status', 'draft' );
								wp_update_post( array( 'ID' => $post_id, 'post_type' => 'post', 'post_author' => $author_id, 'post_status' => $status ) );
							}
						}
					}
				}
			} else {
				$nonce = $_REQUEST['nonce'];
				$post_id = $_REQUEST['post_id'];
				if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'assign-post-idea' ) ) {
					echo '<div class="error"><p>You have no permission to perform this action</p></div>';
				} else {
					if ( ! empty( $post_id ) && current_user_can( 'edit_posts' ) ) {
						wp_update_post( array( 'ID' => $post_id, 'post_type' => 'post', 'post_author' => $author_id, 'post_status' => 'draft' ) );
					}
				}
			} ?>
			<div class="update-nag">The post ideas have been assigned to you as a working draft. You can go to the
				<a href="<?php echo admin_url( '/edit.php?post_status=draft&author=' . $author_id . '&post_type=post' ); ?>">Posts</a>
				section to retrieve it and start your writing.
				Please remember to change the post status to "<strong>In Progress</strong>" while you are editing your
				article.
			</div>
			<?php
		}

		public function restore_post_idea() {
			$message = '';
			$error = '';
			if ( isset( $_REQUEST['action'] ) && 'restore' === $_REQUEST['action'] ) {
				if ( wp_verify_nonce( $_POST['wpnonce'], 'restore_post_ideas' ) ) {
					global $wpdb;
					$post_id = (int) $_POST['post_id'];
					if ( ! empty( $post_id ) ) {
						$post = $wpdb->get_row( $wpdb->prepare( "SELECT post_status, post_type FROM $wpdb->posts WHERE id = %d", $post_id ) );
						if ( 'post' === $post->post_type && 'publish' !== $post->post_status ) {
							$result = $wpdb->update( $wpdb->posts, array(
								'post_status' => 'publish',
								'post_type' => 'post-idea',
							), array( 'ID' => $post_id ) );
							if ( $result ) {
								$message = 'Post Idea restored successfully.';
							} else {
								$error = 'Post idea not restored.';
							}
						}
					}
				}
			}
			?>
			<div class="wrap">
				<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
				<?php if ( ! empty( $message ) ) : ?>
					<div class="updated"><p><strong><?php echo $message; ?> </strong></p></div>
				<?php elseif ( ! empty( $error ) ) : ?>
					<div class="error"><p><strong><?php echo $error; ?> </strong></p></div>
				<?php endif; ?>
				<p>Use the form below to restore an assigned, yet abandoned post to Post Ideas so other writers can
					select it.</p>
				<form action="<?php echo admin_url( 'admin.php?page=restore-post-idea&action=restore' ); ?>"
				      method="post">
					<input type="hidden" name="wpnonce" value="<?php echo wp_create_nonce( 'restore_post_ideas' ); ?>">
					<table class="form-table">
						<tr>
							<th scope="row">
								Enter the id of the post to restore:
							</th>
							<td>
								<input type="number" class="regular-text" value="" name="post_id">
							</td>
						</tr>
					</table>
					<p class="submit">
						<input type="submit" value="Restore" class="button-primary">
					</p>
				</form>
			</div>
			<?php
		}
	}
}
new DO_Posts_Ideas;