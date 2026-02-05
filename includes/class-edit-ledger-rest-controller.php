<?php
/**
 * REST API controller for Edit Ledger.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Edit_Ledger_REST_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'edit-ledger/v1';
		$this->rest_base = 'posts';
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// GET /posts/{post_id}/revisions - List revisions for a post.
		register_rest_route(
			$this->namespace,
			'/posts/(?P<post_id>[\d]+)/revisions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_post_revisions' ),
				'permission_callback' => array( $this, 'get_post_revisions_permissions_check' ),
				'args'                => array(
					'post_id'  => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'default'           => 50,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// GET /revisions/{revision_id}/diff - Get diff for a revision.
		register_rest_route(
			$this->namespace,
			'/revisions/(?P<revision_id>[\d]+)/diff',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_revision_diff' ),
				'permission_callback' => array( $this, 'get_revision_permissions_check' ),
				'args'                => array(
					'revision_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'compare_to'  => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'description'       => 'Revision ID to compare to. Defaults to previous revision.',
					),
				),
			)
		);

		// POST /revisions/{revision_id}/restore - Restore a revision.
		register_rest_route(
			$this->namespace,
			'/revisions/(?P<revision_id>[\d]+)/restore',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'restore_revision' ),
				'permission_callback' => array( $this, 'restore_revision_permissions_check' ),
				'args'                => array(
					'revision_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// GET /recent - Recent revisions across all posts (admin).
		register_rest_route(
			$this->namespace,
			'/recent',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_recent_revisions' ),
				'permission_callback' => array( $this, 'get_recent_permissions_check' ),
				'args'                => array(
					'per_page' => array(
						'default'           => 20,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'minimum'           => 1,
						'maximum'           => 100,
					),
					'page'     => array(
						'default'           => 1,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'minimum'           => 1,
					),
					'author'   => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'post_id'  => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'after'    => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'before'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Get revisions for a post.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_post_revisions( $request ) {
		$post_id  = $request->get_param( 'post_id' );
		$per_page = $request->get_param( 'per_page' );

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'not_found', __( 'Post not found.', 'edit-ledger' ), array( 'status' => 404 ) );
		}

		$revisions = wp_get_post_revisions(
			$post_id,
			array(
				'posts_per_page' => $per_page,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$data           = array();
		$revision_array = array_values( $revisions );

		foreach ( $revision_array as $index => $revision ) {
			// Get previous revision for comparison.
			$previous = isset( $revision_array[ $index + 1 ] ) ? $revision_array[ $index + 1 ] : $post;
			$data[]   = $this->format_revision( $revision, $previous );
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Get diff for a revision.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_revision_diff( $request ) {
		$revision_id = $request->get_param( 'revision_id' );
		$compare_to  = $request->get_param( 'compare_to' );

		$revision = get_post( $revision_id );
		if ( ! $revision || 'revision' !== $revision->post_type ) {
			return new WP_Error( 'not_found', __( 'Revision not found.', 'edit-ledger' ), array( 'status' => 404 ) );
		}

		$parent_id = $revision->post_parent;

		// Determine what to compare against.
		if ( $compare_to ) {
			$compare_post = get_post( $compare_to );
			if ( ! $compare_post ) {
				return new WP_Error( 'not_found', __( 'Comparison revision not found.', 'edit-ledger' ), array( 'status' => 404 ) );
			}
		} else {
			// Find the previous revision.
			$compare_post = $this->get_previous_revision( $revision );
			if ( ! $compare_post ) {
				// Fall back to current post state.
				$compare_post = get_post( $parent_id );
			}
		}

		// Generate diffs for each field (strip HTML for editor-friendly view).
		$diff_generator = new Edit_Ledger_Diff_Generator();

		// Clean content for readable diffs.
		$from_content = $this->strip_html_for_diff( $compare_post->post_content );
		$to_content   = $this->strip_html_for_diff( $revision->post_content );
		$from_excerpt = $this->strip_html_for_diff( $compare_post->post_excerpt );
		$to_excerpt   = $this->strip_html_for_diff( $revision->post_excerpt );

		// Extract media changes (images, videos, embeds).
		$media_changes = $this->get_media_changes( $compare_post->post_content, $revision->post_content );

		$fields = array(
			'title'   => array(
				'from'      => $compare_post->post_title,
				'to'        => $revision->post_title,
				'diff_html' => $diff_generator->generate( $compare_post->post_title, $revision->post_title ),
			),
			'content' => array(
				'from'      => $from_content,
				'to'        => $to_content,
				'diff_html' => $diff_generator->generate( $from_content, $to_content ),
			),
			'excerpt' => array(
				'from'      => $from_excerpt,
				'to'        => $to_excerpt,
				'diff_html' => $diff_generator->generate( $from_excerpt, $to_excerpt ),
			),
		);

		// Get metadata for comparison header.
		$revision_author = get_userdata( $revision->post_author );
		$compare_author  = get_userdata( $compare_post->post_author );

		$comparison = array(
			'from' => array(
				'id'            => $compare_post->ID,
				'date'          => $compare_post->post_modified,
				'date_relative' => human_time_diff( strtotime( $compare_post->post_modified_gmt ), time() ),
				'author'        => array(
					'name'   => $compare_author ? $compare_author->display_name : __( 'Unknown', 'edit-ledger' ),
					'avatar' => get_avatar_url( $compare_post->post_author, array( 'size' => 48 ) ),
				),
				'is_current'    => $compare_post->post_type !== 'revision',
			),
			'to'   => array(
				'id'            => $revision->ID,
				'date'          => $revision->post_modified,
				'date_relative' => human_time_diff( strtotime( $revision->post_modified_gmt ), time() ),
				'author'        => array(
					'name'   => $revision_author ? $revision_author->display_name : __( 'Unknown', 'edit-ledger' ),
					'avatar' => get_avatar_url( $revision->post_author, array( 'size' => 48 ) ),
				),
				'is_current'    => false,
			),
		);

		return rest_ensure_response(
			array(
				'revision_id'   => $revision_id,
				'compare_to'    => $compare_post->ID,
				'comparison'    => $comparison,
				'fields'        => $fields,
				'media_changes' => $media_changes,
			)
		);
	}

	/**
	 * Get recent revisions across all posts.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_recent_revisions( $request ) {
		global $wpdb;

		$per_page = $request->get_param( 'per_page' );
		$page     = $request->get_param( 'page' );
		$author   = $request->get_param( 'author' );
		$post_id  = $request->get_param( 'post_id' );
		$after    = $request->get_param( 'after' );
		$before   = $request->get_param( 'before' );

		$where  = array( "r.post_type = 'revision'" );
		$params = array();

		if ( $author ) {
			$where[]  = 'r.post_author = %d';
			$params[] = $author;
		}

		if ( $post_id ) {
			$where[]  = 'r.post_parent = %d';
			$params[] = $post_id;
		}

		if ( $after ) {
			$where[]  = 'r.post_modified >= %s';
			$params[] = $after;
		}

		if ( $before ) {
			$where[]  = 'r.post_modified <= %s';
			$params[] = $before;
		}

		$offset    = ( $page - 1 ) * $per_page;
		$where_sql = implode( ' AND ', $where );

		$params[] = $per_page;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare(
			"SELECT r.*, p.post_title as parent_title, p.post_type as parent_type
			 FROM {$wpdb->posts} r
			 INNER JOIN {$wpdb->posts} p ON r.post_parent = p.ID
			 WHERE {$where_sql}
			 ORDER BY r.post_modified DESC
			 LIMIT %d OFFSET %d",
			$params
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$revisions = $wpdb->get_results( $query );

		$data = array();
		foreach ( $revisions as $revision ) {
			$data[] = $this->format_revision_for_admin( $revision );
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Format a revision for API response.
	 *
	 * @param WP_Post $revision  The revision post.
	 * @param WP_Post $compare   The post to compare against.
	 * @return array
	 */
	private function format_revision( $revision, $compare ) {
		$author      = get_userdata( $revision->post_author );
		$is_autosave = wp_is_post_autosave( $revision->ID );

		// Determine what changed.
		$changes = array();
		if ( $revision->post_title !== $compare->post_title ) {
			$changes[] = 'title';
		}
		if ( $revision->post_content !== $compare->post_content ) {
			$changes[] = 'content';
		}
		if ( $revision->post_excerpt !== $compare->post_excerpt ) {
			$changes[] = 'excerpt';
		}

		return array(
			'id'            => $revision->ID,
			'parent_id'     => $revision->post_parent,
			'title'         => $revision->post_title,
			'date'          => $revision->post_modified,
			'date_gmt'      => $revision->post_modified_gmt,
			'date_relative' => human_time_diff( strtotime( $revision->post_modified_gmt ), time() ),
			'author'        => array(
				'id'     => $revision->post_author,
				'name'   => $author ? $author->display_name : __( 'Unknown', 'edit-ledger' ),
				'avatar' => get_avatar_url( $revision->post_author, array( 'size' => 48 ) ),
			),
			'type'          => $is_autosave ? 'autosave' : 'manual',
			'changes'       => $changes,
		);
	}

	/**
	 * Format a revision for admin API response.
	 *
	 * @param object $revision The revision object from database.
	 * @return array
	 */
	private function format_revision_for_admin( $revision ) {
		$author      = get_userdata( $revision->post_author );
		$is_autosave = strpos( $revision->post_name, 'autosave' ) !== false;

		return array(
			'id'            => $revision->ID,
			'parent_id'     => $revision->post_parent,
			'parent_title'  => $revision->parent_title,
			'parent_type'   => $revision->parent_type,
			'date'          => $revision->post_modified,
			'date_relative' => human_time_diff( strtotime( $revision->post_modified_gmt ), time() ),
			'author'        => array(
				'id'     => $revision->post_author,
				'name'   => $author ? $author->display_name : __( 'Unknown', 'edit-ledger' ),
				'avatar' => get_avatar_url( $revision->post_author, array( 'size' => 32 ) ),
			),
			'type'          => $is_autosave ? 'autosave' : 'manual',
			'edit_url'      => get_edit_post_link( $revision->post_parent, 'raw' ),
		);
	}

	/**
	 * Get the previous revision for comparison.
	 *
	 * @param WP_Post $revision The current revision.
	 * @return WP_Post|null
	 */
	private function get_previous_revision( $revision ) {
		$revisions = wp_get_post_revisions(
			$revision->post_parent,
			array(
				'posts_per_page' => 1,
				'date_query'     => array(
					array(
						'before' => $revision->post_modified_gmt,
						'column' => 'post_modified_gmt',
					),
				),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		return ! empty( $revisions ) ? array_shift( $revisions ) : null;
	}

	/**
	 * Strip HTML and clean content for editor-friendly diff display.
	 *
	 * @param string $content The content to clean.
	 * @return string Cleaned text content.
	 */
	private function strip_html_for_diff( $content ) {
		if ( empty( $content ) ) {
			return '';
		}

		// Convert media elements to placeholders BEFORE stripping HTML.
		$content = $this->convert_media_to_placeholders( $content );

		// Remove Gutenberg block comments.
		$content = preg_replace( '/<!--\s*\/?wp:[^>]*-->/s', '', $content );

		// Convert block-level elements to newlines.
		$content = preg_replace( '/<\/(p|div|h[1-6]|li|blockquote|pre)>/i', "\n", $content );
		$content = preg_replace( '/<(br|hr)\s*\/?>/i', "\n", $content );

		// Strip remaining HTML tags.
		$content = wp_strip_all_tags( $content );

		// Decode HTML entities.
		$content = html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );

		// Normalize whitespace.
		$content = preg_replace( '/[ \t]+/', ' ', $content );
		$content = preg_replace( '/\n{3,}/', "\n\n", $content );

		// Trim whitespace from each line.
		$lines   = explode( "\n", $content );
		$lines   = array_map( 'trim', $lines );
		$content = implode( "\n", $lines );

		return trim( $content );
	}

	/**
	 * Convert media elements to readable placeholders.
	 *
	 * @param string $content The HTML content.
	 * @return string Content with media replaced by placeholders.
	 */
	private function convert_media_to_placeholders( $content ) {
		// Images.
		$content = preg_replace_callback(
			'/<img[^>]*>/i',
			function ( $matches ) {
				$img = $matches[0];
				if ( preg_match( '/alt=["\']([^"\']*)["\']/', $img, $alt_match ) && ! empty( $alt_match[1] ) ) {
					return '[Image: ' . $alt_match[1] . ']';
				}
				if ( preg_match( '/src=["\']([^"\']*)["\']/', $img, $src_match ) ) {
					$filename = basename( parse_url( $src_match[1], PHP_URL_PATH ) );
					if ( ! empty( $filename ) ) {
						return '[Image: ' . $filename . ']';
					}
				}
				return '[Image]';
			},
			$content
		);

		// Videos.
		$content = preg_replace_callback(
			'/<video[^>]*>.*?<\/video>/is',
			function ( $matches ) {
				$video = $matches[0];
				if ( preg_match( '/src=["\']([^"\']*)["\']/', $video, $src_match ) ) {
					$filename = basename( parse_url( $src_match[1], PHP_URL_PATH ) );
					if ( ! empty( $filename ) ) {
						return '[Video: ' . $filename . ']';
					}
				}
				return '[Video]';
			},
			$content
		);

		// Audio.
		$content = preg_replace_callback(
			'/<audio[^>]*>.*?<\/audio>/is',
			function ( $matches ) {
				$audio = $matches[0];
				if ( preg_match( '/src=["\']([^"\']*)["\']/', $audio, $src_match ) ) {
					$filename = basename( parse_url( $src_match[1], PHP_URL_PATH ) );
					if ( ! empty( $filename ) ) {
						return '[Audio: ' . $filename . ']';
					}
				}
				return '[Audio]';
			},
			$content
		);

		// Iframes.
		$content = preg_replace_callback(
			'/<iframe[^>]*>.*?<\/iframe>/is',
			function ( $matches ) {
				$iframe = $matches[0];
				if ( preg_match( '/src=["\']([^"\']*)["\']/', $iframe, $src_match ) ) {
					$url = $src_match[1];
					if ( strpos( $url, 'youtube' ) !== false || strpos( $url, 'youtu.be' ) !== false ) {
						return '[YouTube Video]';
					}
					if ( strpos( $url, 'vimeo' ) !== false ) {
						return '[Vimeo Video]';
					}
					if ( strpos( $url, 'twitter' ) !== false || strpos( $url, 'x.com' ) !== false ) {
						return '[Twitter/X Embed]';
					}
					if ( strpos( $url, 'spotify' ) !== false ) {
						return '[Spotify Embed]';
					}
					return '[Embed: ' . parse_url( $url, PHP_URL_HOST ) . ']';
				}
				return '[Embed]';
			},
			$content
		);

		// WordPress embeds.
		$content = preg_replace_callback(
			'/<figure[^>]*class=["\'][^"\']*wp-block-embed[^"\']*["\'][^>]*>.*?<\/figure>/is',
			function ( $matches ) {
				$figure = $matches[0];
				if ( strpos( $figure, 'youtube' ) !== false ) {
					return '[YouTube Video]';
				}
				if ( strpos( $figure, 'vimeo' ) !== false ) {
					return '[Vimeo Video]';
				}
				if ( strpos( $figure, 'twitter' ) !== false || strpos( $figure, 'x.com' ) !== false ) {
					return '[Twitter/X Embed]';
				}
				if ( strpos( $figure, 'instagram' ) !== false ) {
					return '[Instagram Embed]';
				}
				return '[Embed]';
			},
			$content
		);

		// Gallery blocks.
		$content = preg_replace(
			'/<figure[^>]*class=["\'][^"\']*wp-block-gallery[^"\']*["\'][^>]*>.*?<\/figure>/is',
			'[Gallery]',
			$content
		);

		// File downloads.
		$content = preg_replace_callback(
			'/<a[^>]*class=["\'][^"\']*wp-block-file[^"\']*["\'][^>]*>.*?<\/a>/is',
			function ( $matches ) {
				$link = $matches[0];
				if ( preg_match( '/href=["\']([^"\']*)["\']/', $link, $href_match ) ) {
					$filename = basename( parse_url( $href_match[1], PHP_URL_PATH ) );
					if ( ! empty( $filename ) ) {
						return '[File: ' . $filename . ']';
					}
				}
				return '[File]';
			},
			$content
		);

		// Tables.
		$content = preg_replace(
			'/<table[^>]*>.*?<\/table>/is',
			'[Table]',
			$content
		);

		// Buttons.
		$content = preg_replace_callback(
			'/<div[^>]*class=["\'][^"\']*wp-block-button[^"\']*["\'][^>]*>.*?<\/div>/is',
			function ( $matches ) {
				if ( preg_match( '/<a[^>]*>([^<]*)<\/a>/', $matches[0], $text_match ) ) {
					return '[Button: ' . trim( $text_match[1] ) . ']';
				}
				return '[Button]';
			},
			$content
		);

		return $content;
	}

	/**
	 * Get media changes between two content versions.
	 *
	 * @param string $from_content The original content.
	 * @param string $to_content   The new content.
	 * @return array Media changes with 'added' and 'removed' arrays.
	 */
	private function get_media_changes( $from_content, $to_content ) {
		$from_media = $this->extract_media( $from_content );
		$to_media   = $this->extract_media( $to_content );

		$added = array();
		foreach ( $to_media as $media ) {
			$found = false;
			foreach ( $from_media as $old_media ) {
				if ( $media['src'] === $old_media['src'] ) {
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				$added[] = $media;
			}
		}

		$removed = array();
		foreach ( $from_media as $media ) {
			$found = false;
			foreach ( $to_media as $new_media ) {
				if ( $media['src'] === $new_media['src'] ) {
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				$removed[] = $media;
			}
		}

		return array(
			'added'   => $added,
			'removed' => $removed,
		);
	}

	/**
	 * Extract media elements from content.
	 *
	 * @param string $content The HTML content.
	 * @return array Array of media items.
	 */
	private function extract_media( $content ) {
		$media = array();

		// Images.
		if ( preg_match_all( '/<img[^>]*>/i', $content, $matches ) ) {
			foreach ( $matches[0] as $img ) {
				$src = '';
				$alt = '';
				if ( preg_match( '/src=["\']([^"\']*)["\']/', $img, $src_match ) ) {
					$src = $src_match[1];
				}
				if ( preg_match( '/alt=["\']([^"\']*)["\']/', $img, $alt_match ) ) {
					$alt = $alt_match[1];
				}
				if ( ! empty( $src ) ) {
					$media[] = array(
						'type' => 'image',
						'src'  => $src,
						'alt'  => $alt,
						'name' => ! empty( $alt ) ? $alt : basename( parse_url( $src, PHP_URL_PATH ) ),
					);
				}
			}
		}

		// Videos.
		if ( preg_match_all( '/<video[^>]*>.*?<\/video>/is', $content, $matches ) ) {
			foreach ( $matches[0] as $video ) {
				$src = '';
				if ( preg_match( '/src=["\']([^"\']*)["\']/', $video, $src_match ) ) {
					$src = $src_match[1];
				} elseif ( preg_match( '/<source[^>]*src=["\']([^"\']*)["\']/', $video, $src_match ) ) {
					$src = $src_match[1];
				}
				if ( ! empty( $src ) ) {
					$media[] = array(
						'type' => 'video',
						'src'  => $src,
						'alt'  => '',
						'name' => basename( parse_url( $src, PHP_URL_PATH ) ),
					);
				}
			}
		}

		// YouTube.
		if ( preg_match_all( '/(?:youtube\.com\/(?:embed\/|watch\?v=)|youtu\.be\/)([a-zA-Z0-9_-]+)/', $content, $matches ) ) {
			foreach ( $matches[1] as $video_id ) {
				$media[] = array(
					'type'      => 'youtube',
					'src'       => 'https://www.youtube.com/embed/' . $video_id,
					'thumbnail' => 'https://img.youtube.com/vi/' . $video_id . '/hqdefault.jpg',
					'alt'       => '',
					'name'      => 'YouTube Video',
				);
			}
		}

		// Vimeo.
		if ( preg_match_all( '/vimeo\.com\/(?:video\/)?(\d+)/', $content, $matches ) ) {
			foreach ( $matches[1] as $video_id ) {
				$media[] = array(
					'type' => 'vimeo',
					'src'  => 'https://vimeo.com/' . $video_id,
					'alt'  => '',
					'name' => 'Vimeo Video (' . $video_id . ')',
				);
			}
		}

		return $media;
	}

	/**
	 * Permission check for getting post revisions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function get_post_revisions_permissions_check( $request ) {
		$post_id = $request->get_param( 'post_id' );
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission to view revisions for this post.', 'edit-ledger' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Permission check for getting a revision diff.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function get_revision_permissions_check( $request ) {
		$revision_id = $request->get_param( 'revision_id' );
		$revision    = get_post( $revision_id );

		if ( ! $revision ) {
			return new WP_Error( 'not_found', __( 'Revision not found.', 'edit-ledger' ), array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'edit_post', $revision->post_parent ) ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission to view this revision.', 'edit-ledger' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Permission check for getting recent revisions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function get_recent_permissions_check( $request ) {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission to view all revisions.', 'edit-ledger' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Restore a revision to the parent post.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function restore_revision( $request ) {
		$revision_id = $request->get_param( 'revision_id' );
		$revision    = get_post( $revision_id );

		if ( ! $revision || 'revision' !== $revision->post_type ) {
			return new WP_Error( 'not_found', __( 'Revision not found.', 'edit-ledger' ), array( 'status' => 404 ) );
		}

		$parent_id = $revision->post_parent;
		$restored  = wp_restore_post_revision( $revision_id );

		if ( ! $restored ) {
			return new WP_Error( 'restore_failed', __( 'Failed to restore revision.', 'edit-ledger' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success'  => true,
				'message'  => __( 'Revision restored successfully.', 'edit-ledger' ),
				'post_id'  => $parent_id,
				'edit_url' => get_edit_post_link( $parent_id, 'raw' ),
			)
		);
	}

	/**
	 * Permission check for restoring a revision.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function restore_revision_permissions_check( $request ) {
		$revision_id = $request->get_param( 'revision_id' );
		$revision    = get_post( $revision_id );

		if ( ! $revision ) {
			return new WP_Error( 'not_found', __( 'Revision not found.', 'edit-ledger' ), array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'edit_post', $revision->post_parent ) ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission to restore this revision.', 'edit-ledger' ), array( 'status' => 403 ) );
		}

		return true;
	}
}
