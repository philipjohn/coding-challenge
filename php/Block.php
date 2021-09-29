<?php
/**
 * Block class.
 *
 * @package SiteCounts
 */

namespace XWP\SiteCounts;

use WP_Block;
use WP_Query;

/**
 * The Site Counts dynamic block.
 *
 * Registers and renders the dynamic block.
 */
class Block {

	/**
	 * The Plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Instantiates the class.
	 *
	 * @param Plugin $plugin The plugin object.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Adds the action to register the block.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', [ $this, 'register_block' ] );
	}

	/**
	 * Registers the block.
	 */
	public function register_block() {
		register_block_type_from_metadata(
			$this->plugin->dir(),
			[
				'render_callback' => [ $this, 'render_callback' ],
			]
		);
	}

	/**
	 * Renders the block.
	 *
	 * @param array    $attributes The attributes for the block.
	 * @param string   $content    The block content, if any.
	 * @param WP_Block $block      The instance of this block.
	 * @return string The markup of the block.
	 */
	public function render_callback( $attributes, $content, $block ) {
		$post_types = get_post_types( [ 'public' => true ] );
		$class_name = $attributes['className'];
		ob_start();

		?>
			<div class="<?php echo esc_attr( $class_name ); ?>">
			<h2><?php esc_html_e( 'Post Counts', 'site-counts' ); ?></h2>
			<ul>
			<?php
			foreach ( $post_types as $post_type_slug ) :
				$post_type_object = get_post_type_object( $post_type_slug );
				$post_count       = count( wp_count_posts( $post_type_slug ) );

				?>
				<li>
				<?php
				echo sprintf(
					/* translators: first replacement is number of posts, second replacement is the post type name (e.g. "post" ) */
					__( 'There are %1$d %2$s.', 'site-counts' ),
					intval( $post_count ),
					esc_html( $post_type_object->labels->name )
				);
				?>
				</li>
				<?php
				endforeach;
			?>
			</ul>
			<p>
				<?php
				echo sprintf(
					/* translators: replacement will be a numeric post ID number */
					__( 'The current post ID is %d.', 'site-counts' ),
					intval( $_GET['post_id'] )
				);
				?>
			</p>

			<?php
			$query = new WP_Query(
				[
					'post_type'      => [ 'post', 'page' ],
					'post_status'    => 'any',
					'posts_per_page' => 15, // We only need 5, but we need to exclude some - see the loop below.
					'date_query'     => [
						[
							'hour'    => 9,
							'compare' => '>=',
						],
						[
							'hour'    => 17,
							'compare' => '<=',
						],
					],
					'tax_query'      => [
						'relation' => 'AND',
						[
							'taxonomy' => 'post_tag',
							'field'    => 'slug',
							'terms'    => [ 'foo' ],
						],
						[
							'taxonomy'         => 'category',
							'field'            => 'name',
							'terms'            => [ 'baz' ],
							'include_children' => false,
						],
					],
				]
			);

			// Record the ID of the post we're on, so we can exclude it from the output.
			$host_post_id = get_the_ID();

			// Holding array for our post titles.
			$post_titles = [];

			if ( $query->have_posts() ) :
				while ( $query->have_posts() ) :

					$query->the_post();

					// Skip this post if it's the same as the current post.
					if ( get_the_ID() === $host_post_id ) {
						continue;
					}

					// Get all the post meta, so we can search it for any value of "Accepted", and skip any post with a
					// matching meta value.
					$post_meta = get_post_meta( get_the_ID() );
					foreach ( $post_meta as $key => $value ) {
						if (
							( is_array( $value ) && array_search( 'Accepted', $value, true ) ) ||
							'Accepted' === $value
						) {
							continue 2; // Skip this post in the loop.
						}
					}

					// Store the post title for later.
					$post_titles[] = get_the_title();

				endwhile;
			endif;

			// Print out the 5 post titles we actually need.
			?>
				<h2><?php _e( 'Any 5 posts with the tag of foo and the category of baz', 'site-counts' ); ?></h2>
				<ul>
					<?php
					foreach ( array_slice( $post_titles, 0, 5 ) as $title ) {
						?>
						<li><?php esc_html( $title ); ?></li>
						<?php
					}
					?>
				</ul>
		</div>
		<?php

		return ob_get_clean();
	}
}
