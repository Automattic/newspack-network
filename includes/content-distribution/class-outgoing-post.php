<?php
/**
 * Newspack Network Content Distribution: Outgoing Post.
 *
 * @package Newspack
 */

namespace Newspack_Network\Content_Distribution;

use Newspack_Network\Content_Distribution as Content_Distribution_Class;
use Newspack_Network\User_Update_Watcher;
use Newspack_Network\Utils\Network;
use WP_Error;
use WP_Post;

/**
 * Outgoing Post Class.
 */
class Outgoing_Post {
	/**
	 * The post meta key for the sites the post is distributed to.
	 */
	const DISTRIBUTED_POST_META = 'newspack_network_distributed_sites';

	/**
	 * The post object.
	 *
	 * @var WP_Post
	 */
	protected $post = null;

	/**
	 * Constructor.
	 *
	 * @param WP_Post|int $post The post object or post ID.
	 *
	 * @throws \InvalidArgumentException If the post is invalid.
	 */
	public function __construct( $post ) {
		$post = get_post( $post );
		if ( ! $post instanceof WP_Post || empty( $post->ID ) ) {
			throw new \InvalidArgumentException( esc_html( __( 'Invalid post.', 'newspack-network' ) ) );
		}

		if ( ! in_array( $post->post_type, Content_Distribution_Class::get_distributed_post_types() ) ) {
			/* translators: unsupported post type for content distribution */
			throw new \InvalidArgumentException( esc_html( sprintf( __( 'Post type %s is not supported as a distributed outgoing post.', 'newspack-network' ), $post->post_type ) ) );
		}

		$this->post = $post;
	}

	/**
	 * Gets the user data of a WP user to be distributed along with the post.
	 *
	 * @param int|WP_Post $user The user ID or object.
	 *
	 * @return WP_Error|array
	 */
	public static function get_outgoing_wp_user_author( $user ) {
		if ( ! is_a( $user, 'WP_User' ) ) {
			$user = get_user_by( 'ID', $user );
		}

		if ( ! $user ) {
			return new WP_Error( 'Error getting WP User details for distribution. Invalid User' );
		}

		$author = [
			'type' => 'wp_user',
			'ID'   => $user->ID,
		];

		foreach ( User_Update_Watcher::$user_props as $prop ) {
			if ( ! empty( $user->$prop ) ) {
				$author[ $prop ] = $user->$prop;
			}
		}

		// CoAuthors' guest authors have a 'website' property.
		if ( ! empty( $user->website ) ) {
			$author['website'] = $user->website;
		}

		foreach ( User_Update_Watcher::$watched_meta as $meta_key ) {
			$value = get_user_meta( $user->ID, $meta_key, true );
			if ( ! empty( $value ) ) {
				$author[ $meta_key ] = $value;
			}
		}

		return $author;
	}

	/**
	 * Get the post object.
	 *
	 * @return WP_Post The post object.
	 */
	public function get_post() {
		return $this->post;
	}

	/**
	 * Get network post ID.
	 *
	 * @return string The network post ID.
	 */
	public function get_network_post_id() {
		$site_hash = get_option( 'newspack_network_content_distribution_hash' );
		if ( ! $site_hash ) {
			$site_hash = md5( get_bloginfo( 'url' ) );
			update_option( 'newspack_network_content_distribution_hash', $site_hash );
		}
		return md5( $site_hash . $this->post->ID );
	}

	/**
	 * Validate URLs for distribution.
	 *
	 * @param string[] $urls Array of site URLs to distribute the post to.
	 *
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public static function validate_distribution( $urls ) {
		if ( empty( $urls ) ) {
			return new WP_Error( 'no_site_urls', __( 'No site URLs provided.', 'newspack-network' ) );
		}

		if ( in_array( get_bloginfo( 'url' ), $urls, true ) ) {
			return new WP_Error( 'no_own_site', __( 'Cannot distribute to own site.', 'newspack-network' ) );
		}

		$urls_not_in_network = Network::get_non_networked_urls_from_list( $urls );
		if ( ! empty( $urls_not_in_network ) ) {
			return new WP_Error(
				'non_networked_urls',
				sprintf(
					/* translators: %s: list of non-networked URLs */
					__( 'Non-networked URLs were passed to config: %s', 'newspack-network' ),
					implode( ', ', $urls_not_in_network )
				)
			);
		}

		return true;
	}

	/**
	 * Set the distribution configuration for a given post.
	 *
	 * @param int[] $site_urls Array of site URLs to distribute the post to.
	 *
	 * @return array|WP_Error Config array on success, WP_Error on failure.
	 */
	public function set_distribution( $site_urls ) {
		$error = self::validate_distribution( $site_urls );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$distribution = get_post_meta( $this->post->ID, self::DISTRIBUTED_POST_META, true );
		if ( ! is_array( $distribution ) ) {
			$distribution = [];
		}

		// If there are urls not already in the config, add them. Note that we don't support
		// removing urls from the config.
		$distribution = array_unique( array_merge( $distribution, $site_urls ) );

		$updated = update_post_meta( $this->post->ID, self::DISTRIBUTED_POST_META, $distribution );

		if ( ! $updated ) {
			return new WP_Error( 'update_failed', __( 'Failed to update post distribution.', 'newspack-network' ) );
		}

		return $distribution;
	}

	/**
	 * Whether the post is distributed. Optionally provide a $site_url to check if
	 * the post is distributed to that site.
	 *
	 * @param string|null $site_url Optional site URL.
	 *
	 * @return bool
	 */
	public function is_distributed( $site_url = null ) {
		$distributed_post_types = Content_Distribution_Class::get_distributed_post_types();
		if ( ! in_array( $this->post->post_type, $distributed_post_types, true ) ) {
			return false;
		}

		$distribution = $this->get_distribution();
		if ( empty( $distribution ) ) {
			return false;
		}

		if ( ! empty( $site_url ) ) {
			return in_array( $site_url, $distribution, true );
		}

		return true;
	}

	/**
	 * Get the sites the post is distributed to.
	 *
	 * @return array The distribution configuration.
	 */
	public function get_distribution() {
		$config = get_post_meta( $this->post->ID, self::DISTRIBUTED_POST_META, true );
		if ( ! is_array( $config ) ) {
			$config = [];
		}
		return $config;
	}

	/**
	 * Get the payload hash.
	 *
	 * @param array|null $payload Optional payload to hash.
	 *
	 * @return string The payload hash.
	 */
	public function get_payload_hash( $payload = null ) {
		if ( empty( $payload ) ) {
			$payload = $this->get_payload();
		}
		unset( $payload['status_on_create'] );
		unset( $payload['post_data']['date_gmt'] );
		unset( $payload['post_data']['modified_gmt'] );
		return md5( wp_json_encode( $payload ) );
	}

	/**
	 * Get the post payload for distribution.
	 *
	 * @param string $status_on_create The post status when creating the post.
	 *
	 * @return array|WP_Error The post payload or WP_Error if the post is invalid.
	 */
	public function get_payload( $status_on_create = 'draft' ) {
		$post_author = self::get_outgoing_wp_user_author( $this->post->post_author );

		$payload = [
			'site_url'         => get_bloginfo( 'url' ),
			'post_id'          => $this->post->ID,
			'post_url'         => get_permalink( $this->post->ID ),
			'network_post_id'  => $this->get_network_post_id(),
			'sites'            => $this->get_distribution(),
			'status_on_create' => $status_on_create,
			'post_data'        => [
				'title'          => html_entity_decode( get_the_title( $this->post->ID ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
				'author'         => is_wp_error( $post_author ) ? [] : $post_author,
				'post_status'    => $this->post->post_status,
				'date_gmt'       => $this->post->post_date_gmt,
				'modified_gmt'   => $this->post->post_modified_gmt,
				'slug'           => $this->post->post_name,
				'post_type'      => $this->post->post_type,
				'raw_content'    => $this->post->post_content,
				'content'        => $this->get_processed_post_content(),
				'excerpt'        => $this->post->post_excerpt,
				'comment_status' => $this->post->comment_status,
				'ping_status'    => $this->post->ping_status,
				'taxonomy'       => $this->get_post_taxonomy_terms(),
				'thumbnail_url'  => get_the_post_thumbnail_url( $this->post->ID, 'full' ),
				'post_meta'      => $this->get_post_meta(),
			],
		];

		/**
		 * Filter the payload's post_data to let others add to it.
		 *
		 * @param array   $post_data The post_data to filter
		 * @param WP_Post $post      The post object for the outgoing post.
		 */
		$payload['post_data'] = apply_filters( 'newspack_network_outgoing_payload_post_data', $payload['post_data'], $this->post );

		return $payload;
	}

	/**
	 * Get a partial payload for distribution.
	 *
	 * @param string[] $post_data_keys Keys in the post_data array to include in
	 *                                 the partial payload.
	 *
	 * @return array|WP_Error The partial payload or WP_Error if any of the keys were not found.
	 */
	public function get_partial_payload( $post_data_keys ) {
		if ( is_string( $post_data_keys ) ) {
			$post_data_keys = [ $post_data_keys ];
		}

		$payload = $this->get_payload();
		foreach ( $post_data_keys as $post_data_key ) {
			if ( ! isset( $payload['post_data'][ $post_data_key ] ) ) {
				return new WP_Error( 'key_not_found', __( 'Key not found in payload.', 'newspack-network' ) );
			}
		}

		// Mark the payload as partial.
		$payload['partial'] = true;

		$post_data = [];
		foreach ( $post_data_keys as $post_data_key ) {
			$post_data[ $post_data_key ] = $payload['post_data'][ $post_data_key ];
		}

		// Always add the date and modified date to the partial payload.
		$post_data['date_gmt']     = $payload['post_data']['date_gmt'];
		$post_data['modified_gmt'] = $payload['post_data']['modified_gmt'];

		$payload['post_data'] = $post_data;

		return $payload;
	}

	/**
	 * Get the processed post content for distribution.
	 *
	 * @return string The post content.
	 */
	protected function get_processed_post_content() {
		global $wp_embed;
		/**
		 * Remove autoembed filter so that actual URL will be pushed and not the generated markup.
		 */
		remove_filter( 'the_content', [ $wp_embed, 'autoembed' ], 8 );
		// Filter documented in WordPress core.
		$post_content = apply_filters( 'the_content', $this->post->post_content );
		add_filter( 'the_content', [ $wp_embed, 'autoembed' ], 8 );
		return $post_content;
	}

	/**
	 * Get post taxonomy terms for distribution.
	 *
	 * @return array The taxonomy term data.
	 */
	protected function get_post_taxonomy_terms() {
		$ignored_taxonomies = Content_Distribution_Class::get_ignored_taxonomies();
		$taxonomies         = get_object_taxonomies( $this->post->post_type, 'objects' );
		$data                = [];
		foreach ( $taxonomies as $taxonomy ) {
			if ( in_array( $taxonomy->name, $ignored_taxonomies, true ) ) {
				continue;
			}
			if ( ! $taxonomy->public ) {
				continue;
			}
			$terms = get_the_terms( $this->post->ID, $taxonomy->name );
			if ( ! $terms ) {
				continue;
			}
			$data[ $taxonomy->name ] = array_map(
				function( $term ) {
					return [
						'name' => $term->name,
						'slug' => $term->slug,
					];
				},
				$terms
			);
		}
		return $data;
	}

	/**
	 * Get post meta for distribution.
	 *
	 * @return array The post meta data.
	 */
	protected function get_post_meta() {
		$ignored_keys = Content_Distribution_Class::get_ignored_post_meta_keys();

		$meta = get_post_meta( $this->post->ID );

		if ( empty( $meta ) ) {
			return [];
		}

		$meta = array_filter(
			$meta,
			function( $value, $key ) use ( $ignored_keys ) {
				// Filter out ignored keys.
				return ! in_array( $key, $ignored_keys, true );
			},
			ARRAY_FILTER_USE_BOTH
		);

		// Unserialize meta values and reformat the array.
		$meta = array_reduce(
			array_keys( $meta ),
			function( $carry, $key ) use ( $meta ) {
				$carry[ $key ] = array_map(
					function( $v ) {
						return maybe_unserialize( $v );
					},
					$meta[ $key ]
				);
				return $carry;
			},
			[]
		);

		/**
		 * Filters the post meta data for distribution.
		 *
		 * @param array   $meta The post meta data.
		 * @param WP_Post $post The post object.
		 */
		return apply_filters( 'newspack_network_distributed_post_meta', $meta, $this->post );
	}
}
