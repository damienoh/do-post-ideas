<?php

/**
 * Post Ideas List Table Helper Class
 *
 *
 * @since      1.0.0
 * @package    Post Ideas
 * @subpackage Plugin_Name/includes/
 * @author     Damien Oh
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Post_Ideas_List extends WP_List_Table {
	public $bulk_action_nonce_field = 'do-post-ideas-assignment';

	public $args;

	public $columns = array();

	// the number of items for each table page
	public $post_per_page = 20;

	/** Class constructor */
	public function __construct() {

		parent::__construct( array(
			'singular' => __( 'post-idea', 'post_ideas' ), //singular name of the listed records
			'plural' => __( 'post-ideas', 'post_ideas' ), //plural name of the listed records
			'ajax' => true, //should this table support ajax?
			)
		);
	}


	/** ************************************************************************
	 * Recommended. This method is called when the parent class can't find a method
	 * specifically build for a given column. Generally, it's recommended to include
	 * one method for each column you want to render, keeping your package class
	 * neat and organized. For example, if the class needs to process a column
	 * named 'title', it would first see if a method named $this->column_title()
	 * exists - if it does, that method will be used. If it doesn't, this one will
	 * be used. Generally, you should try to use custom column methods as much as
	 * possible.
	 *
	 * Since we have defined a column_title() method later on, this method doesn't
	 * need to concern itself with any column with a name of 'title'. Instead, it
	 * needs to handle everything else.
	 *
	 * For more detailed insight into how columns are handled, take a look at
	 * WP_List_Table::single_row_columns()
	 *
	 * @param array $item        A singular item (one full row's worth of data)
	 * @param array $column_name The name/slug of the column to be processed
	 *
	 * @return string Text or HTML to be placed inside the column <td>
	 **************************************************************************/
	function column_default( $item, $column_name ) {
		return $item[ $column_name ];
	}

	function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			/*$1%s*/
			'post_id',
			/*$2%s*/
			$item['id']                //The value of the checkbox should be the record's id
		);
	}

	function extra_tablenav( $which ) {
		if ( 'top' === $which ) {
			?>
			<div class="alignleft actions">
				<?php
				if ( isset( $_GET['cat']) ) {
					$selected = $_GET['cat'];
				} else {
					$selected = 0;
				}
				wp_dropdown_categories( array(
					'show_option_all' => 'All Categories',
					'selected' => $selected,
					)
				);
				?>
			</div>
			<input name="filter_action" id="post-query-submit" class="button" value="Filter" type="submit">
			<?php
		}

	}

	function get_columns() {
		$columns = array(
			'cb'         => '<input type="checkbox" />', //Render a checkbox instead of text
			'post_title' => 'Post Title',
			'categories' => 'Category',
			'content_brief' => 'Content Brief',
			'keywords' => 'Keywords',
			'action' => 'Action',
		);
		return $columns;
	}

	function get_bulk_actions() {
		$actions = array(
			'bulk-assign' => 'Assign to me',
		);
		return $actions;
	}

	function get_data() {

		if ( isset( $_GET['filter_action'] ) && 'Filter' === $_GET['filter_action'] ) {
			if ( isset( $_GET['cat'] ) ) {
				$posts = get_posts( array( 'post_type' => 'post-idea', 'cat' => $_GET['cat'], 'numberposts' => - 1 ) );
			}
		} else {
			$posts = get_posts( array( 'post_type' => 'post-idea', 'numberposts' => - 1 ) );
		}

		$data = array();

		foreach ( $posts as $post ) {
			$item = array(
				'content_brief' => '',
				'keywords' => '',
			);
			$item['id']         = $post->ID;
			$item['post_title'] = '<strong>' . $post->post_title . '</strong>';
			$categories         = get_the_category( $post->ID );
			$cat                = array();
			foreach ( $categories as $category ) {
				$cat[] = $category->name;
			}
			$item['categories'] = implode( ',', $cat );
			$postidea_meta = get_post_meta( $post->ID, '_postidea_meta', true );
			if ( ! empty( $postidea_meta ) && is_array( $postidea_meta ) ) {
				if ( ! empty( $postidea_meta['description'] ) ) {
					$item['content_brief'] = stripslashes( $postidea_meta['description'] );
				}
				if ( ! empty( $postidea_meta['keywords'] ) ) {
					$item['keywords'] = stripslashes( $postidea_meta['keywords'] );
				}
			}
			$item['action'] = sprintf( '<a href="?page=%s&action=assign&nonce=%s&post_id=%d" class="button-primary">Assign to me</a>', $_REQUEST['page'], wp_create_nonce( 'assign-post-idea' ), $post->ID );
			array_push( $data, $item );
		}

		return $data;
	}

	/** ************************************************************************
	 * REQUIRED! This is where you prepare your data for display. This method will
	 * usually be used to query the database, sort and filter the data, and generally
	 * get it ready to be displayed. At a minimum, we should set $this->items and
	 * $this->set_pagination_args(), although the following properties and methods
	 * are frequently interacted with here...
	 *
	 * @global WPDB $wpdb
	 * @uses $this->_column_headers
	 * @uses $this->items
	 * @uses $this->get_columns()
	 * @uses $this->get_sortable_columns()
	 * @uses $this->get_pagenum()
	 * @uses $this->set_pagination_args()
	 **************************************************************************/
	function prepare_items() {
		/**
		 * First, lets decide how many records per page to show
		 */
		$per_page = $this->post_per_page;


		/**
		 * REQUIRED. Now we need to define our column headers. This includes a complete
		 * array of columns to be displayed (slugs & titles), a list of columns
		 * to keep hidden, and a list of columns that are sortable. Each of these
		 * can be defined in another method (as we've done here) before being
		 * used to build the value for our _column_headers property.
		 */
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();


		/**
		 * REQUIRED. Finally, we build an array to be used by the class for column
		 * headers. The $this->_column_headers property takes an array which contains
		 * 3 other arrays. One for all columns, one for hidden columns, and one
		 * for sortable columns.
		 */
		$this->_column_headers = array( $columns, $hidden, $sortable );


		/**
		 * Optional. You can handle your bulk actions however you see fit. In this
		 * case, we'll handle them within our package just to keep things clean.
		 */
		//$this->process_bulk_action();


		/**
		 * Instead of querying a database, we're going to fetch the example data
		 * property we created for use in this plugin. This makes this example
		 * package slightly different than one you might build on your own. In
		 * this example, we'll be using array manipulation to sort and paginate
		 * our data. In a real-world implementation, you will probably want to
		 * use sort and pagination data to build a custom query instead, as you'll
		 * be able to use your precisely-queried data immediately.
		 */

		$data = $this->get_data();


		/**
		 * This checks for sorting input and sorts the data in our array accordingly.
		 *
		 * In a real-world situation involving a database, you would probably want
		 * to handle sorting by passing the 'orderby' and 'order' values directly
		 * to a custom query. The returned data will be pre-sorted, and this array
		 * sorting technique would be unnecessary.
		 */
		if ( !function_exists( 'usort_reorder' ) ) {
			function usort_reorder( $a, $b ) {
				$orderby = ( !empty( $_REQUEST['orderby'] ) ) ? $_REQUEST['orderby'] : 'title'; //If no sort, default to title
				$order   = ( !empty( $_REQUEST['order'] ) ) ? $_REQUEST['order'] : 'asc'; //If no order, default to asc
				$result  = strcmp( $a[$orderby], $b[$orderby] ); //Determine sort order
				return ( $order === 'asc' ) ? $result : - $result; //Send final sort direction to usort
			}
		}
		//usort($data, 'usort_reorder');


		/***********************************************************************
		 * ---------------------------------------------------------------------
		 * vvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvv
		 *
		 * In a real-world situation, this is where you would place your query.
		 *
		 * ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
		 * ---------------------------------------------------------------------
		 **********************************************************************/


		/**
		 * REQUIRED for pagination. Let's figure out what page the user is currently
		 * looking at. We'll need this later, so you should always include it in
		 * your own package classes.
		 */
		$current_page = $this->get_pagenum();

		/**
		 * REQUIRED for pagination. Let's check how many items are in our data array.
		 * In real-world use, this would be the total number of items in your database,
		 * without filtering. We'll need this later, so you should always include it
		 * in your own package classes.
		 */
		$total_items = count( $data );


		/**
		 * The WP_List_Table class does not handle pagination for us, so we need
		 * to ensure that the data is trimmed to only the current page. We can use
		 * array_slice() to
		 */
		$data = array_slice( $data, ( ( $current_page - 1 ) * $per_page ), $per_page );


		/**
		 * REQUIRED. Now we can add our *sorted* data to the items property, where
		 * it can be used by the rest of the class.
		 */
		$this->items = $data;


		/**
		 * REQUIRED. We also have to register our pagination options & calculations.
		 */
		$this->set_pagination_args( array(
			'total_items' => $total_items,                  //WE have to calculate the total number of items
			'per_page'    => $per_page,                     //WE have to determine how many items to show on a page
			'total_pages' => ceil( $total_items / $per_page )   //WE have to calculate the total number of pages
		) );
	}
}