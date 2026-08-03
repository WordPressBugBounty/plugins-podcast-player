<?php
/**
 * Fetch Feed Data from Feed XML file.
 *
 * @link       https://www.vedathemes.com
 * @since      1.0.0
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 */

namespace Podcast_Player\Helper\Feed;

use Podcast_Player\Helper\Store\FeedData;
use Podcast_Player\Helper\Store\ItemData;
use Podcast_Player\Helper\Functions\Getters as Get_Fn;
use Podcast_Player\Helper\Functions\Validation as Validation_Fn;

/**
 * Fetch Feed Data from Feed XML file.
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 * @author     vedathemes <contact@easypodcastpro.com>
 */
class Fetch_Feed {

	/**
	 * Holds podcast feed URL.
	 *
	 * @since  6.5.0
	 * @access public
	 * @var    string
	 */
	public $url = '';

	/**
	 * Holds feed raw data.
	 *
	 * @since  6.5.0
	 * @access private
	 * @var    string
	 */
	private $feed = '';

	/**
	 * Atom Feed Namespace.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    string
	 */
	private $atom;

	/**
	 * Itunes Feed Namespace.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    string
	 */
	private $itunes;

	/**
	 * Podcast Feed Namespace.
	 *
	 * @since  7.4.0
	 * @access private
	 * @var    string
	 */
	private $podcast;

	/**
	 * Holds instance of current podcast item.
	 *
	 * @since  6.4.3
	 * @access private
	 * @var    object
	 */
	private $item = '';

	/**
	 * Holds iTunes namespace for current podcast item
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    mixed
	 */
	private $item_tunes;

	/**
	 * Holds atom namespace for current podcast item
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    mixed
	 */
	private $item_atom;

	/**
	 * Holds podcast namespace for current episode.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    mixed
	 */
	private $item_podcast;

	/**
	 * Holds ID of current podcast item.
	 *
	 * @since  6.5.0
	 * @access private
	 * @var    string
	 */
	private $id;

	/**
	 * Holds podcast last_modified date.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $last_modified;

	/**
	 * Holds podcast eTag value for cache updation.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $etag;

	/**
	 * Holds fetch process response.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    mixed
	 */
	public $response = null;

	/**
	 * Holds fetch process response code.
	 *
	 * @since  7.4.0
	 * @access public
	 * @var    int|string
	 */
	public $response_code = '';

	/**
	 * Final URL reached after following HTTP redirects.
	 *
	 * @since  8.3.0
	 * @access private
	 * @var    string
	 */
	private $resolved_url = '';

	/**
	 * Permanent-redirect status (301/308) on the original feed URL, else 0.
	 *
	 * @since  8.3.0
	 * @access private
	 * @var    int
	 */
	private $redirect_status = 0;

	/**
	 * Create initial state of the object.
	 *
	 * @since 6.5.0
	 *
	 * @param string $url  Podcast feed URL.
	 * @param string $etag Etag.
	 * @param string $last_updated Last Updated.
	 */
	public function __construct( $url, $etag = '', $last_updated = '' ) {
		$this->url = $url;
		$this->get_fetched_object( $etag, $last_updated );
	}

	/**
	 * Get Podcast Feed Data.
	 *
	 * @since 6.5.0
	 *
	 * @param string $etag Etag.
	 * @param string $last_updated Last Updated.
	 */
	public function get_fetched_object( $etag, $last_updated ) {
		$headers = array(
			'Cache-Control' => 'no-cache, must-revalidate, max-age=0',
			'Pragma'        => 'no-cache',
		);
		if ( 'yes' === Get_Fn::get_plugin_option( 'check_cache_headers' ) ) {
			$headers = array_merge( $headers, array_filter( array(
				'If-None-Match'     => $etag,
				'If-Modified-Since' => $last_updated
			) ) );
		}

		$this->response = wp_safe_remote_request(
			$this->url,
			array(
				'headers' => $headers,
				'timeout' => 10
			)
		);

		$this->response_code = wp_remote_retrieve_response_code( $this->response );
		$this->capture_redirect_data();
		if ( 304 === $this->response_code ) {
			return;
		}

		$xml_string = wp_remote_retrieve_body( $this->response );
		$xml_data   = empty( $xml_string ) ? '' : $this->get_valid_xml(
			str_replace(
				'http://www.itunes.com/DTDs/Podcast-1.0.dtd',
				'http://www.itunes.com/dtds/podcast-1.0.dtd',
				$xml_string
			)
		);

		if ( empty( $xml_data ) || is_wp_error( $xml_data ) || empty( $xml_data->channel ) ) {
			$this->response = is_wp_error( $xml_data ) ? $xml_data : new \WP_Error(
				'no-feed-data',
				__( 'Feed Data Not Available', 'podcast-player' )
			);
			return;
		}

		$this->feed    = $xml_data->channel;
		$this->itunes  = $this->feed->children( 'http://www.itunes.com/dtds/podcast-1.0.dtd' );
		$this->atom    = $this->feed->children( 'http://www.w3.org/2005/Atom' );
		$this->podcast = $this->feed->children( 'https://podcastindex.org/namespace/1.0' );

		$this->etag          = wp_remote_retrieve_header( $this->response, 'etag' );
		$this->last_modified = wp_remote_retrieve_header( $this->response, 'last-modified' );
	}

	/**
	 * Capture the final resolved URL and any permanent-redirect status.
	 *
	 * Reads the redirect history already produced by the single feed request,
	 * so no extra HTTP request is made and the existing fetch behaviour is
	 * unchanged. Only a 301/308 on the original URL is treated as permanent.
	 *
	 * @since 8.3.0
	 */
	private function capture_redirect_data() {
		$this->resolved_url    = $this->url;
		$this->redirect_status = 0;

		if ( is_wp_error( $this->response ) || ! is_array( $this->response ) ) {
			return;
		}

		$http = isset( $this->response['http_response'] ) ? $this->response['http_response'] : null;
		if ( ! $http || ! method_exists( $http, 'get_response_object' ) ) {
			return;
		}

		$object = $http->get_response_object();
		if ( ! is_object( $object ) ) {
			return;
		}

		if ( ! empty( $object->url ) && Validation_Fn::is_valid_url( (string) $object->url ) ) {
			$this->resolved_url = (string) $object->url;
		}

		if ( empty( $object->history ) || ! is_array( $object->history ) ) {
			return;
		}

		// history[0] is the response to the original URL; it must be permanent.
		$first = reset( $object->history );
		$code  = is_object( $first ) && isset( $first->status_code ) ? (int) $first->status_code : 0;
		if ( ! in_array( $code, array( 301, 308 ), true ) ) {
			return;
		}

		// Any temporary hop in the chain means the final URL is not a permanent
		// target, so do not treat the resolved URL as a migration destination.
		foreach ( $object->history as $hop ) {
			$hop_code = is_object( $hop ) && isset( $hop->status_code ) ? (int) $hop->status_code : 0;
			if ( in_array( $hop_code, array( 302, 303, 307 ), true ) ) {
				return;
			}
		}

		$this->redirect_status = $code;
	}

	/**
	 * Get the iTunes new-feed-url migration signal, if present and valid.
	 *
	 * @since 8.3.0
	 *
	 * @return string
	 */
	public function get_new_feed_url() {
		if ( ! isset( $this->itunes ) || ! isset( $this->itunes->{'new-feed-url'} ) ) {
			return '';
		}
		$new_feed_url = trim( (string) $this->itunes->{'new-feed-url'} );
		return Validation_Fn::is_valid_url( $new_feed_url ) ? $new_feed_url : '';
	}

	/**
	 * Get the final URL reached after any redirects.
	 *
	 * @since 8.3.0
	 *
	 * @return string
	 */
	public function get_resolved_url() {
		return $this->resolved_url ? $this->resolved_url : $this->url;
	}

	/**
	 * Get the HTTP response code from the feed request.
	 *
	 * @since 8.3.0
	 *
	 * @return int
	 */
	public function get_response_code() {
		return absint( $this->response_code );
	}

	/**
	 * Get permanent-redirect migration data for the original feed URL.
	 *
	 * Returns an empty array unless the original URL issued a 301/308 to a
	 * different valid URL. `same_host` marks a scheme/slash/host-case-only change
	 * (an alias fold) versus a genuine cross-host migration.
	 *
	 * @since 8.3.0
	 *
	 * @return array
	 */
	public function get_redirect_migration_data() {
		if ( ! $this->redirect_status || ! Validation_Fn::is_valid_url( $this->resolved_url ) ) {
			return array();
		}
		if ( $this->resolved_url === $this->url ) {
			return array();
		}
		return array(
			'from'      => $this->url,
			'to'        => $this->resolved_url,
			'status'    => $this->redirect_status,
			'same_host' => self::hosts_match( $this->url, $this->resolved_url ),
		);
	}

	/**
	 * Whether two URLs share the same host (case-insensitive), ignoring scheme.
	 *
	 * @since 8.3.0
	 *
	 * @param string $first  First URL.
	 * @param string $second Second URL.
	 * @return bool
	 */
	public static function hosts_match( $first, $second ) {
		$first_host  = strtolower( (string) wp_parse_url( $first, PHP_URL_HOST ) );
		$second_host = strtolower( (string) wp_parse_url( $second, PHP_URL_HOST ) );
		return '' !== $first_host && $first_host === $second_host;
	}

	/**
	 * Validate XML.
	 *
	 * Check if a given string is a valid XML.
	 *
	 * @since 6.5.0
	 *
	 * @param string $xmlstr Podcast feed XML.
	 */
	private function get_valid_xml( $xmlstr ) {
		libxml_use_internal_errors( true );
		$doc = simplexml_load_string( $xmlstr, 'SimpleXMLIterator' );

		if ( false === $doc ) {
			$response = new \WP_Error();
			$errors   = libxml_get_errors();

			foreach ( $errors as $error ) {
				$response->add( $this->get_xml_errorcode( $error ), trim( $error->message ) );
			}

			libxml_clear_errors();
			return $response;
		}
		return $doc;
	}

	/**
	 * Get properly formatted xml error code.
	 *
	 * @since 6.5.0
	 *
	 * @param object $error LibXMLError object.
	 */
	private function get_xml_errorcode( $error ) {
		$return = '';
		switch ( $error->level ) {
			case LIBXML_ERR_WARNING:
				$return .= "Warning $error->code";
				break;
			case LIBXML_ERR_ERROR:
				$return .= "Error $error->code";
				break;
			case LIBXML_ERR_FATAL:
				$return .= "Fatal Error $error->code";
				break;
		}
		return $return;
	}

	/**
	 * Prepare feed data object.
	 *
	 * @since 6.5.0
	 */
	public function get_feed_data() {
		if ( 304 === $this->response_code ) {
			return '304_use_cache';
		}

		if ( is_wp_error( $this->response ) ) {
			return $this->response;
		}

		$items = $this->get_feed_items();
		if ( empty( $items ) ) {
			return new \WP_Error(
				'no-items-error',
				esc_html__( 'No feed items available.', 'podcast-player' )
			);
		}
		list( $is_active, $release_cycle, $last_released, $cache_duration ) = $this->get_podcast_analysis( $items );

		$feed = new FeedData();
		$feed->set( 'title', $this->get_feed_title() );
		$feed->set( 'desc', $this->get_feed_description() );
		$feed->set( 'link', $this->get_feed_link() );
		$feed->set( 'image', $this->get_feed_cover() );
		$feed->set( 'furl', $this->get_feed_url() );
		$feed->set( 'fkey', md5( $this->get_feed_url() ) );
		$feed->set( 'copyright', $this->get_feed_copyright() );
		$feed->set( 'author', $this->get_feed_author() );
		$feed->set( 'podcats', $this->get_feed_category() );
		$feed->set( 'owner', $this->get_feed_owner() );
		$feed->set( 'items', $items );
		$feed->set( 'seasons', $this->get_feed_seasons( $items ) );
		$feed->set( 'categories', $this->get_items_categories( $items ) );
		$feed->set( 'total', $this->get_total_items( $items ) );
		$feed->set( 'funding', $this->get_feed_funding() );
		$feed->set( 'etag', $this->etag );
		$feed->set( 'lastbuild', $this->last_modified );
		$feed->set( 'is_active', $is_active );
		$feed->set( 'release_cycle', $release_cycle );
		$feed->set( 'last_released', $last_released );
		$feed->set( 'cache_duration', $cache_duration );
		
		return $feed;
	}

	/**
	 * Fetch items level data.
	 *
	 * @since  6.4.3
	 */
	public function get_feed_items() {
		$nitems = array();
		$items  = $this->feed->item;

		if ( Validation_Fn::is_iterable( $items ) ) {
			foreach ( $items as $item ) {
				$this->item         = $item;
				$this->item_tunes   = $item->children( 'http://www.itunes.com/dtds/podcast-1.0.dtd' );
				$this->item_atom    = $item->children( 'http://www.w3.org/2005/Atom' );
				$this->item_podcast = $item->children( 'https://podcastindex.org/namespace/1.0' );
				$data               = $this->get_item_data();
				if ( $data ) {
					$item_key            = $this->get_item_storage_key( $nitems, $data );
					$nitems[ $item_key ] = $data;
				}
			}
		}
		return $nitems;
	}

	/**
	 * Get item storage key.
	 *
	 * @since 8.3.0
	 *
	 * @param array    $items Existing feed items.
	 * @param ItemData $item  Current item.
	 * @return string
	 */
	private function get_item_storage_key( $items, $item ) {
		$key = $item->get( 'episode_uid', 'none' );
		$key = $key ? $key : $this->id;

		if ( ! isset( $items[ $key ] ) ) {
			return $key;
		}

		$source = $item->get( 'src', 'none' );
		$index  = 1;
		do {
			$candidate = Episode_Identity::disambiguate( $key, $source, $index );
			$index++;
		} while ( isset( $items[ $candidate ] ) );

		$item->set( 'episode_uid', $candidate );
		return $candidate;
	}

	/**
	 * Fetch single item data.
	 *
	 * @since  6.4.3
	 */
	public function get_item_data() {
		list( $media, $media_type, $media_sources ) = $this->get_item_media();
		if ( ! $media || ! $media_type ) {
			return false;
		}

		$this->id = $this->get_item_id( $media );
		$title    = $this->get_item_title();
		$date     = $this->get_item_date();
		$duration = $this->get_item_duration();
		$identity = Episode_Identity::generate(
			array(
				'rss_guid'  => $this->get_rss_guid(),
				'atom_id'   => $this->get_atom_id(),
				'title'     => $title,
				'date'      => $date,
				'duration'  => $duration,
				'media_url' => $media,
			)
		);

		$item = new ItemData();
		$item->set( 'title', $title );
		$item->set( 'description', $this->get_item_description() );
		$item->set( 'author', $this->get_item_author() );
		$item->set( 'date', $date );
		$item->set( 'link', $this->get_item_link( $media ) );
		$item->set( 'src', $media );
		$item->set( 'featured', $this->get_item_featured() );
		$item->set( 'mediatype', $media_type );
		$item->set( 'media_sources', $media_sources );
		$item->set( 'episode', $this->get_itunes_episode() );
		$item->set( 'season', $this->get_itunes_season() );
		$item->set( 'categories', $this->get_item_cats() );
		$item->set( 'episode_id', $this->get_episode_id( $media ) );
		$item->set( 'episode_uid', $identity['episode_uid'] );
		$item->set( 'identity_source', $identity['identity_source'] );
		$item->set( 'identity_reliability', $identity['identity_reliability'] );
		$item->set( 'duration', $duration );
		$item->set( 'episodetype', $this->get_item_episodetype() );
		$item->set( 'transcript', $this->get_episode_transcript() );
		$item->set( 'chapters', $this->get_episode_chapters() );
		return $item;
	}

	/**
	 * Get media src from the item.
	 *
	 * @since  6.4.3
	 */
	public function get_item_media() {
		$enclosure     = $this->get_media_enclosure();
		$primary       = false !== $enclosure ? $this->get_primary_media_source( $enclosure ) : array();
		$alternates    = $this->get_alternate_media_sources();
		$media_sources = array_filter( array_merge( $primary ? array( $primary ) : array(), $alternates ) );

		if ( empty( $primary ) && ! empty( $media_sources ) ) {
			$primary = reset( $media_sources );
		}

		$media      = isset( $primary['url'] ) ? $primary['url'] : '';
		$mime_type  = isset( $primary['type'] ) ? $primary['type'] : '';
		$media_type = $media ? Get_Fn::get_media_type( $media, $mime_type ) : '';

		return array( esc_url_raw( $media ), sanitize_text_field( $media_type ), $media_sources );
	}

	/**
	 * Get media src from the item.
	 *
	 * @since  6.4.3
	 */
	public function get_media_enclosure() {
		// Look for media in the media group.
		$media = $this->item->children( 'http://search.yahoo.com/mrss/' );
		$group = isset( $media->group ) ? $media->group : false;
		if ( Validation_Fn::is_iterable( $group ) ) {
			foreach ( $group as $g ) {
				$contents = isset( $g->children( 'http://search.yahoo.com/mrss/' )->content ) ? $g->children( 'http://search.yahoo.com/mrss/' )->content : false;
				if ( Validation_Fn::is_iterable( $contents ) ) {
					foreach ( $contents as $enclosure ) {
						if ( method_exists( $enclosure, 'attributes' ) ) {
							$type = (string) $enclosure->attributes()->type;
							$url  = (string) $enclosure->attributes()->url;
							if ( $this->is_supported_media_type( $type, $url ) ) {
								return $enclosure;
							}
						}
					}
				}
			}
		}

		// Look for media in direct media content.
		$contents = isset( $media->content ) ? $media->content : false;
		if ( Validation_Fn::is_iterable( $contents ) ) {
			foreach ( $contents as $enclosure ) {
				if ( method_exists( $enclosure, 'attributes' ) ) {
					$type = (string) $enclosure->attributes()->type;
					$url  = (string) $enclosure->attributes()->url;
					if ( $this->is_supported_media_type( $type, $url ) ) {
						return $enclosure;
					}
				}
			}
		}

		// Finally look for media in the enclosures.
		$enc = isset( $this->item->enclosure ) ? $this->item->enclosure : false;
		if ( ! Validation_Fn::is_iterable( $enc ) ) {
			return false;
		}
		foreach ( $enc as $enclosure ) {
			if ( method_exists( $enclosure, 'attributes' ) ) {
				$type = (string) $enclosure->attributes()->type;
				$url = (string) $enclosure->attributes()->url;
				if ( $this->is_supported_media_type( $type, $url ) ) {
					return $enclosure;
				}
			}
		}

		// Finally, look for media in all enclosures.
		foreach ( $enc as $enclosure ) {
			if ( method_exists( $enclosure, 'attributes' ) ) {
				$url = (string) $enclosure->attributes()->url;
				if ( $url && false !== Get_Fn::get_media_type( $url ) ) {
					return $enclosure;
				}
			}
		}
		return false;
	}

	/**
	 * Get structured source data for the primary enclosure.
	 *
	 * @since 8.2.0
	 *
	 * @param object $enclosure Enclosure XML node.
	 * @return array
	 */
	private function get_primary_media_source( $enclosure ) {
		if ( ! method_exists( $enclosure, 'attributes' ) ) {
			return array();
		}

		$attrs = $enclosure->attributes();
		$url   = isset( $attrs->url ) ? esc_url_raw( (string) $attrs->url ) : '';
		if ( ! $url ) {
			return array();
		}

		$type = isset( $attrs->type ) ? sanitize_text_field( (string) $attrs->type ) : '';

		return $this->prepare_media_source(
			array(
				'url'     => $url,
				'type'    => $type,
				'length'  => isset( $attrs->length ) ? (string) $attrs->length : 0,
				'rel'     => 'enclosure',
				'default' => true,
			)
		);
	}

	/**
	 * Get Podcasting 2.0 alternate enclosure sources.
	 *
	 * @since 8.2.0
	 *
	 * @return array
	 */
	private function get_alternate_media_sources() {
		$sources    = array();
		$alternates = isset( $this->item_podcast->alternateEnclosure ) ? $this->item_podcast->alternateEnclosure : false;
		if ( ! Validation_Fn::is_iterable( $alternates ) ) {
			return $sources;
		}

		foreach ( $alternates as $alternate ) {
			if ( ! method_exists( $alternate, 'attributes' ) ) {
				continue;
			}

			$attrs       = $alternate->attributes();
			$type        = isset( $attrs->type ) ? sanitize_text_field( (string) $attrs->type ) : '';
			$alt_sources = $alternate->children( 'https://podcastindex.org/namespace/1.0' )->source;

			if ( ! Validation_Fn::is_iterable( $alt_sources ) ) {
				$alt_sources = isset( $alternate->source ) ? $alternate->source : false;
			}

			if ( ! Validation_Fn::is_iterable( $alt_sources ) ) {
				continue;
			}

			foreach ( $alt_sources as $source ) {
				if ( ! method_exists( $source, 'attributes' ) ) {
					continue;
				}

				$source_attrs = $source->attributes();
				$url          = isset( $source_attrs->uri ) ? esc_url_raw( (string) $source_attrs->uri ) : '';
				if ( ! $url ) {
					continue;
				}

				$sources[] = $this->prepare_media_source(
					array(
						'url'     => $url,
						'type'    => $type,
						'length'  => isset( $attrs->length ) ? (string) $attrs->length : 0,
						'bitrate' => isset( $attrs->bitrate ) ? (string) $attrs->bitrate : 0,
						'height'  => isset( $attrs->height ) ? (string) $attrs->height : 0,
						'width'   => isset( $attrs->width ) ? (string) $attrs->width : 0,
						'lang'    => isset( $attrs->lang ) ? (string) $attrs->lang : '',
						'title'   => isset( $attrs->title ) ? (string) $attrs->title : '',
						'rel'     => isset( $attrs->rel ) ? (string) $attrs->rel : 'alternate',
						'default' => isset( $attrs->default ) && in_array( strtolower( (string) $attrs->default ), array( 'true', '1', 'yes' ), true ),
						'codecs'  => isset( $attrs->codecs ) ? (string) $attrs->codecs : '',
					)
				);
			}
		}

		return $sources;
	}

	/**
	 * Prepare a normalized media source array.
	 *
	 * @since 8.2.0
	 *
	 * @param array $source Raw source values.
	 * @return array
	 */
	private function prepare_media_source( $source ) {
		$url        = isset( $source['url'] ) ? esc_url_raw( $source['url'] ) : '';
		$type       = isset( $source['type'] ) ? sanitize_text_field( $source['type'] ) : '';
		$is_hls     = Get_Fn::is_hls_source( $url, $type );
		$media_type = Get_Fn::get_media_type( $url, $type );

		if ( ! $url || ! $media_type ) {
			return array();
		}

		$url_path = wp_parse_url( $url, PHP_URL_PATH );
		$url_path = is_string( $url_path ) ? $url_path : '';

		return array(
			'url'        => $url,
			'type'       => $type,
			'format'     => $is_hls ? 'hls' : sanitize_text_field( pathinfo( $url_path, PATHINFO_EXTENSION ) ),
			'media_type' => sanitize_text_field( $media_type ),
			'rel'        => isset( $source['rel'] ) ? sanitize_text_field( $source['rel'] ) : '',
			'title'      => isset( $source['title'] ) ? sanitize_text_field( $source['title'] ) : '',
			'lang'       => isset( $source['lang'] ) ? sanitize_text_field( $source['lang'] ) : '',
			'bitrate'    => isset( $source['bitrate'] ) ? absint( $source['bitrate'] ) : 0,
			'height'     => isset( $source['height'] ) ? absint( $source['height'] ) : 0,
			'width'      => isset( $source['width'] ) ? absint( $source['width'] ) : 0,
			'length'     => isset( $source['length'] ) ? absint( $source['length'] ) : 0,
			'default'    => ! empty( $source['default'] ) ? 1 : 0,
			'codecs'     => isset( $source['codecs'] ) ? sanitize_text_field( $source['codecs'] ) : '',
		);
	}

	/**
	 * Check whether a type or URL is a supported media source.
	 *
	 * @since 8.2.0
	 *
	 * @param string $type MIME type.
	 * @param string $url  Media URL.
	 * @return bool
	 */
	private function is_supported_media_type( $type, $url ) {
		return false !== strpos( $type, 'audio' )
			|| false !== strpos( $type, 'video' )
			|| Get_Fn::is_hls_source( $url, $type );
	}

	/**
	 * Generate current item's unique ID.
	 *
	 * @since  6.4.3
	 *
	 * @param string $media Media for current item.
	 */
	public function get_item_id( $media ) {
		return md5( $media );
	}

	/**
	 * Get item title.
	 *
	 * @since 1.0.0
	 */
	private function get_item_title() {
		return trim( (string) $this->item->title );
	}

	/**
	 * Get item description.
	 *
	 * @since 1.0.0
	 */
	private function get_item_description() {
		$content = isset( $this->item_atom->content ) && trim( (string) $this->item_atom->content ) ? trim( (string) $this->item_atom->content ) : '';

		if ( ! $content ) {
			$namespace = $this->item->children( 'http://purl.org/rss/1.0/modules/content/' );
			$content   = isset( $namespace->encoded ) && trim( (string) $namespace->encoded ) ? trim( (string) $namespace->encoded ) : '';
		}

		if ( ! $content ) {
			$content = $this->item->description && trim( (string) $this->item->description ) ? trim( (string) $this->item->description ) : '';
		}

		if ( ! $content ) {
			$content = isset( $this->item_atom->summary ) && trim( (string) $this->item_atom->summary ) ? trim( (string) $this->item_atom->summary ) : '';
		}

		if ( ! $content ) {
			$content = isset( $this->item_tunes->summary ) && trim( (string) $this->item_tunes->summary ) ? trim( (string) $this->item_tunes->summary ) : '';
		}

		if ( ! $content ) {
			$content = isset( $this->item_tunes->subtitle ) && trim( (string) $this->item_tunes->subtitle ) ? trim( (string) $this->item_tunes->subtitle ) : '';
		}

		if ( $content ) {
			if ( 'yes' === Get_Fn::get_plugin_option( 'rel_external' ) ) {
				$link_mod = Add_External_Link_Attr::get_instance();
				$content  = $link_mod->init( $content );
			}
			return apply_filters( 'podcast_player_feed_episode_content', $content );
		} else {
			return '';
		}
	}

	/**
	 * Get Item Author.
	 *
	 * @since 1.0.0
	 */
	private function get_item_author() {
		$authors = $this->get_item_authors();
		if ( ! empty( $authors ) ) {
			return $authors[0];
		}
		return '';
	}

	/**
	 * Get item Author.
	 *
	 * @since 1.0.0
	 */
	private function get_item_authors() {
		$authors = array();
		$auths   = isset( $this->item_tunes->author ) ? $this->item_tunes->author : false;
		if ( Validation_Fn::is_iterable( $auths ) ) {
			foreach ( $auths as $author ) {
				$authors[] = trim( (string) $author );
			}
		}

		$auths = isset( $this->item_atom->author ) ? $this->item_atom->author : false;
		if ( Validation_Fn::is_iterable( $auths ) ) {
			foreach ( $auths as $author ) {
				$authors[] = trim( (string) $author->name );
			}
		}

		$authors = array_unique( array_filter( $authors ) );
		if ( empty( $authors ) ) {
			$feed_authors = $this->get_feed_authors();
			foreach ( $feed_authors as $author ) {
				$authors[] = trim( (string) $author );
			}
		}
		return $authors;
	}

	/**
	 * Get item publish date.
	 *
	 * @since 1.0.0
	 */
	private function get_item_date() {
		$date = $this->item->pubDate ? $this->item->pubDate : '';
		if ( ! $date && isset( $this->item_atom->published ) ) {
			$date = $this->item_atom->published;
		}

		if ( ! $date && $this->item_atom->updated ) {
			$date = $this->item_atom->updated;
		}

		return $date ? (string) $date : '';
	}

	/**
	 * Get item link.
	 *
	 * @since 1.0.0
	 *
	 * @param string $media Episode media url.
	 */
	private function get_item_link( $media ) {
		$link = isset( $this->item_atom->link ) ? $this->item_atom->link : false;
		if ( $link ) {
			if ( method_exists( $link, 'attributes' ) ) {
				$link = trim( (string) $link->attributes()->href );
			} else {
				$link = false;
			}
		}

		if ( ! $link ) {
			$link = $this->item->link && (string) $this->item->link ? trim( (string) $this->item->link ) : '';
		}

		if ( ! $link ) {
			$guid = $this->item->guid ? $this->item->guid : false;
			if ( $guid ) {
				$is_perma = '';
				if ( method_exists( $guid, 'attributes' ) ) {
					$is_perma = (string) $guid->attributes()->isPermaLink;
				}

				if ( 'false' !== $is_perma ) {
					$u = trim( (string) $guid );
					if ( Validation_Fn::is_valid_url( $u ) ) {
						$link = $u;
					}
				}
			}
		}

		if ( ! $link ) {
			$link = $media;
		}

		return $link;
	}

	/**
	 * Get item featured image.
	 *
	 * @since 1.0.0
	 */
	private function get_item_featured() {
		$image = isset( $this->item_tunes->image ) ? $this->item_tunes->image : false;
		if ( $image ) {
			$image = (string) $image->attributes()->href;
		}
		if ( ! $image ) {
			$enc = isset( $this->item->enclosure ) ? $this->item->enclosure : false;
			if ( $enc && ( is_array( $enc ) || ( is_object( $enc ) && method_exists( $enc, 'rewind' ) ) ) ) {
				foreach ( $enc as $enclosure ) {
					$type = (string) $enclosure->type;
					if ( false !== strpos( $type, 'image' ) ) {
						$image = (string) $enclosure->url;
						break;
					}
				}
			}
		}

		if ( ! $image ) {
			$media    = $this->item->children( 'http://search.yahoo.com/mrss/' );
			$contents = isset( $media->content ) ? $media->content : false;
			if ( $contents ) {
				if ( Validation_Fn::is_iterable( $contents ) ) {
					foreach ( $contents as $enclosure ) {
						if ( method_exists( $enclosure, 'attributes' ) ) {
							$type = (string) $enclosure->attributes()->medium;
							if ( false !== strpos( $type, 'image' ) ) {
								$image = (string) $enclosure->attributes()->url;
								break;
							}
						}
					}
				} elseif ( method_exists( $contents, 'attributes' ) ) {
					$type = (string) $contents->attributes()->medium;
					if ( false !== strpos( $type, 'image' ) ) {
						$image = (string) $contents->attributes()->url;
					}
				}
			}
		}

		if ( $image && Validation_Fn::is_valid_image_url( $image ) ) {
			return $image;
		} else {
			return '';
		}
	}

	/**
	 * Get item iTunes episode number.
	 *
	 * @since 1.0.0
	 */
	private function get_itunes_episode() {
		$episode = isset( $this->item_tunes->episode ) ? (string) $this->item_tunes->episode : false;
		$season  = $this->get_itunes_season();
		if ( $episode ) {
			$episode = $season ? $season . '-' . $episode : $episode;
			return $episode;
		}
		return '';
	}

	/**
	 * Get item iTunes episode season.
	 *
	 * @since 1.0.0
	 */
	private function get_itunes_season() {
		return isset( $this->item_tunes->season ) && $this->item_tunes->season ? (string) $this->item_tunes->season : '';
	}

	/**
	 * Get item iTunes episode categories.
	 *
	 * @since 1.0.0
	 */
	private function get_item_cats() {
		$categories = array();
		$cats       = isset( $this->item->category ) ? $this->item->category : false;
		if ( Validation_Fn::is_iterable( $cats ) ) {
			foreach ( $cats as $category ) {
				$term = (string) $category;
				$term = sanitize_text_field( $term );
				$key  = strtolower( str_replace( ' ', '', $term ) );
				if ( $key ) {
					$categories[ $key ] = $term;
				}
			}
		}

		$cats = isset( $this->item_atom->category ) ? $this->item_atom->category : false;
		if ( ! Validation_Fn::is_iterable( $cats ) ) {
			return $categories;
		}
		foreach ( $cats as $category ) {
			$term = (string) $category->term;
			$term = sanitize_text_field( $term );
			$key  = strtolower( str_replace( ' ', '', $term ) );
			if ( $key ) {
				$categories[ $key ] = $term;
			}
		}

		return $categories;
	}

	/**
	 * Get Episode ID.
	 *
	 * @since  5.7.0
	 *
	 * @param string $media Media Src.
	 */
	public function get_episode_id( $media ) {
		$id = isset( $this->item_atom->id ) && $this->item_atom->id ? $this->item_atom->id : $this->item->guid;
		if ( ! $id ) {
			$id = md5( $this->get_item_title() );
		}
		return (string) $id;
	}

	/**
	 * Get raw RSS GUID, if present.
	 *
	 * @since 8.3.0
	 */
	private function get_rss_guid() {
		return $this->item->guid ? trim( (string) $this->item->guid ) : '';
	}

	/**
	 * Get raw Atom ID, if present.
	 *
	 * @since 8.3.0
	 */
	private function get_atom_id() {
		return isset( $this->item_atom->id ) && $this->item_atom->id ? trim( (string) $this->item_atom->id ) : '';
	}

	/**
	 * Get Episode duration.
	 *
	 * @since  5.7.0
	 */
	public function get_item_duration() {
		$d = isset( $this->item_tunes->duration ) ? (string) $this->item_tunes->duration : false;
		return ( ! $d || empty( $d ) ) ? false : $d;
	}

	/**
	 * Get Episode Type. Full or Trailer.
	 *
	 * @since  7.3.0
	 */
	public function get_item_episodetype() {
		return isset( $this->item_tunes->episodeType ) ? (string) $this->item_tunes->episodeType : 'full';
	}

	/**
	 * Get Episode Transcript.
	 *
	 * @since 7.4.0
	 */
	public function get_episode_transcript() {
		$trans_arr    = array();
		$transcripts  = $this->item_podcast && isset( $this->item_podcast->transcript ) ? $this->item_podcast->transcript : false;
		if ( Validation_Fn::is_iterable( $transcripts ) ) {
			foreach ( $transcripts as $transcript ) {
				if ( ! method_exists( $transcript, 'attributes' ) ) {
					continue;
				}
				$url  = (string) $transcript->attributes()->url;
				$type = (string) $transcript->attributes()->type;
				$lang = (string) $transcript->attributes()->lang;
				$rel  = (string) $transcript->attributes()->rel;

				if ( ! $url || ! $type ) {
					continue;
				}

				$arr = array( 'url' => $url, 'type' => $type, 'lang' => $lang, 'rel' => $rel );
				$trans_arr[] = $arr;
			}
		}

		return $trans_arr;
	}

	/**
	 * Get Episode Chapters.
	 *
	 * @since 7.4.0
	 */
	public function get_episode_chapters() {
		$chap_arr = array();
		$chapters = $this->item_podcast && isset( $this->item_podcast->chapters ) ? $this->item_podcast->chapters : false;
		if ( $chapters && Validation_Fn::is_iterable( $chapters ) ) {
			foreach ( $chapters as $chapter ) {
				if ( ! method_exists( $chapter, 'attributes' ) ) {
					continue;
				}
				$url  = (string) $chapter->attributes()->url;
				$type = (string) $chapter->attributes()->type;

				if ( ! $url || ! $type ) {
					continue;
				}
				$chap_arr[ $url ] = $type;
			}
		}

		return $chap_arr;
	}

	/**
	 * Get podcast title.
	 *
	 * @since 6.5.0
	 */
	private function get_feed_title() {
		return (string) $this->feed->title;
	}

	/**
	 * Get podcast description.
	 *
	 * @since 6.5.0
	 */
	private function get_feed_description() {
		$desc = (string) $this->feed->description;
		if ( ! $desc && isset( $this->itunes->summary ) ) {
			$desc = (string) $this->itunes->summary;
		}
		return $desc;
	}

	/**
	 * Get podcast website link.
	 *
	 * @since 6.5.0
	 */
	private function get_feed_link() {
		$links = isset( $this->feed->link ) ? $this->feed->link : false;
		if ( $links ) {
			if ( Validation_Fn::is_iterable( $links ) ) {
				foreach ( $links as $link ) {
					if ( (string) $link ) {
						return trim( (string) $link );
					}
				}
			} else {
				return trim( (string) $links );
			}
		}
		return '';
	}

	/**
	 * Get podcast feed URL from the feed atom link.
	 *
	 * @since 7.4.0
	 */
	private function get_feed_url() {
		return $this->url;

		// We should be using internal atom link as main podcast link. However, it is very complex to setup it now.
		// Let's handle it at a later stage.
		// $link = isset( $this->atom->link ) ? $this->atom->link : false;
		// if ( $link && (string) $link->attributes()->href ) {
		// 	return trim( (string) $link->attributes()->href );
		// }
	}

	/**
	 * Get podcast cover.
	 *
	 * @since 6.5.0
	 */
	private function get_feed_cover() {
		$cover = $this->itunes && isset( $this->itunes->image ) ? (string) $this->itunes->image->attributes()->href : '';
		if ( ! $cover ) {
			$cover = isset( $this->feed->image->url ) ? (string) $this->feed->image->url : '';
		}
		return trim( $cover );
	}

	/**
	 * Get podcast copyright info.
	 *
	 * @since 1.0.0
	 */
	private function get_feed_copyright() {
		return trim( (string) $this->feed->copyright );
	}

	/**
	 * Get podcast Author.
	 *
	 * @since 1.0.0
	 */
	private function get_feed_author() {
		$authors = $this->get_feed_authors();
		if ( ! empty( $authors ) ) {
			return $authors[0];
		}
		return '';
	}

	/**
	 * Get podcast Authors.
	 *
	 * @since 1.0.0
	 */
	private function get_feed_authors() {
		$authors = array();
		$auths   = isset( $this->itunes->author ) ? $this->itunes->author : false;
		if ( $auths ) {
			if ( Validation_Fn::is_iterable( $auths ) ) {
				foreach ( $auths as $author ) {
					$authors[] = (string) $author;
				}
			} else {
				$authors[] = (string) $auths;
			}
		}

		$auths = isset( $this->atom->author ) ? $this->atom->author : false;
		if ( $auths ) {
			if ( Validation_Fn::is_iterable( $auths ) ) {
				foreach ( $auths as $author ) {
					$authors[] = (string) $author;
				}
			} else {
				$authors[] = (string) $auths;
			}
		}

		return $authors;
	}

	/**
	 * Get podcast Categories.
	 *
	 * @since 1.0.0
	 */
	private function get_feed_category() {
		$categories = array();

		$podcats = isset( $this->itunes->category ) ? $this->itunes->category : false;
		// Check items are available and are iterable.
		if ( ! Validation_Fn::is_iterable( $podcats ) ) {
			return array();
		}
		foreach ( $podcats as $podcat ) {
			$label = (string) $podcat->attributes()->text;
			if ( ! $label ) {
				continue;
			}
			$label = sanitize_text_field( $label );
			$key   = strtolower( str_replace( ' ', '', $label ) );
			if ( ! isset( $categories[ $key ] ) ) {
				$categories[ $key ] = array(
					'label'   => $label,
					'subcats' => array(),
				);
			}
			$subcats = $podcat->category;
			if ( ! Validation_Fn::is_iterable( $subcats ) ) {
				continue;
			}
			$sub = $categories[ $key ]['subcats'];
			foreach ( $subcats as $subcat ) {
				$sub[] = (string) $subcat->attributes()->text;
			}
			$sub                           = array_unique( array_filter( $sub ) );
			$categories[ $key ]['subcats'] = $sub;
		}
		return $categories;
	}

	/**
	 * Get podcast Owner.
	 *
	 * @since 1.0.0
	 */
	private function get_feed_owner() {
		$owner = $this->itunes->owner;
		$name  = '';
		$email = '';
		if ( $owner ) {
			$name_child = $owner->children( 'http://www.itunes.com/dtds/podcast-1.0.dtd' )->name;
			if ( $name_child ) {
				$name = sanitize_text_field( (string) $name_child );
			}
			$email_child = $owner->children( 'http://www.itunes.com/dtds/podcast-1.0.dtd' )->email;
			if ( $email_child ) {
				$email = sanitize_email( (string) $email_child );
			}
		}
		return array(
			'name'  => $name,
			'email' => $email,
		);
	}

	/**
	 * Get cumulative array of all seasons.
	 *
	 * @since 1.0.0
	 *
	 * @param array $items Array of podcast feed items object.
	 */
	private function get_feed_seasons( $items ) {
		$seasons = array();
		foreach ( $items as $item ) {
			if ( $item instanceof ItemData ) {
				$seasons[] = $item->get( 'season' );
			}
		}
		return array_values( array_filter( array_unique( $seasons ) ) );
	}

	/**
	 * Get cumulative array of all items categories.
	 *
	 * @since 1.0.0
	 *
	 * @param array $items Array of podcast feed items object.
	 */
	private function get_items_categories( $items ) {
		$cats = array();
		foreach ( $items as $item ) {
			if ( $item instanceof ItemData ) {
				$cats = array_merge( $cats, $item->get( 'categories' ) );
			}
		}
		return array_filter( array_unique( $cats ) );
	}

	/**
	 * Get total items in the podcast feed.
	 *
	 * @since 1.0.0
	 *
	 * @param array $items Array of podcast feed items object.
	 */
	private function get_total_items( $items ) {
		return count( $items );
	}

	/**
	 * Get Podcast Funding URL.
	 *
	 * Links to financially support a show
	 *
	 * @since 7.4.0
	 */
	private function get_feed_funding() {
		$funding_arr = array();
		$fundings = $this->podcast && isset( $this->podcast->funding ) ? $this->podcast->funding : false;
		if ( $fundings && Validation_Fn::is_iterable( $fundings ) ) {
			foreach ( $fundings as $funding ) {
				if ( ! method_exists( $funding, 'attributes' ) ) {
					continue;
				}
				$url  = (string) $funding->attributes()->url;
				$text = trim( (string) $funding );

				if ( ! $url ) {
					continue;
				}
				$funding_arr[ $url ] = $text;
			}
		}

		return $funding_arr;
	}

	/**
	 * Analyse podcast release frequency and activeness.
	 *
	 * @since 7.4.0
	 *
	 * @param array $episodes Array of podcast feed episodes object.
	 */
	private function get_podcast_analysis( $episodes ) {
		if ( empty( $episodes ) ) {
			return array( false, 0, 0, 7 * DAY_IN_SECONDS );
		}

		$episodes = array_filter(
			$episodes,
			function ( $episode ) {
				return $episode instanceof ItemData;
			}
		);
		if ( empty( $episodes ) ) {
			return array( false, 0, 0, 7 * DAY_IN_SECONDS );
		}

		// Step 1: Sort episodes according to their release date.
		usort( $episodes, function( $a, $b ) {
			return $a->get( 'date' )['date'] < $b->get( 'date' )['date'] ? -1 : 1;
		});

		// Step 2: Cut off episodes for past 6 month
		$six_months_ago = strtotime( '-6 months' );
		$recent_episodes = array_filter( $episodes, function( $episode ) use ( $six_months_ago ) {
			return $episode->get( 'date' )['date'] >= $six_months_ago;
		});
		$recent_episodes = array_values( $recent_episodes );

		// Step 3: Create an array of differences between two consecutive episodes release dates
		$differences = [];
		for ( $i = 1; $i < count( $recent_episodes ); $i++ ) {
			$diff = $recent_episodes[$i]->get( 'date' )['date'] - $recent_episodes[$i - 1]->get( 'date' )['date'];
			$differences[] = $diff / (60 * 60 * 24); // Convert seconds to days
		}

		// Step 4: Get average time between two consecutive episodes
		if ( count( $differences ) > 0 ) {
			$average_diff = array_sum( $differences ) / count( $differences );
		} else {
			$average_diff = 0;
		}

		// Step 4: Remove all outlier values (2 * Std Deviation) from the array in point 2 (gap duration)
		if ( count( $differences ) > 1 ) {
			$std_dev = sqrt( array_sum( array_map( function( $x ) use ( $average_diff ) {
				return pow( $x - $average_diff, 2 );
			}, $differences ) ) / count( $differences ) );
		
			$outlier_threshold = 2 * $std_dev;
			$filtered_differences = array_filter( $differences, function( $diff ) use ( $average_diff, $outlier_threshold ) {
				return abs( $diff - $average_diff ) <= $outlier_threshold;
			});
		
			// Step 5: Calculate average time between two consecutive episodes
			if ( count( $filtered_differences)  > 0 ) {
				$filtered_average_diff = array_sum( $filtered_differences ) / count( $filtered_differences );
			} else {
				$filtered_average_diff = 0;
			}
		} else {
			$filtered_average_diff = $average_diff;
		}

		// Step 6: Calculate when the last episode was released
		$last_episode = end( $episodes );
		$last_episode_release_date = $last_episode ? $last_episode->get( 'date' )['date'] : 0;

		// Step 7: Calculate how mamy days ago last episode was released
		$days_since_last_episode = $last_episode_release_date ? ( time() - $last_episode_release_date ) / (60 * 60 * 24) : 0;

		// Step 8: Check if podcast is active ( Release an episode in last 3 months )
		$is_active = $days_since_last_episode <= 90;

		// Step 9: Calculate cache duration
		if ( ! $is_active ) {
			// If the podcast is not active, check for updates once a week
			$cache_duration = 7 * DAY_IN_SECONDS;
		} else {
			$cache_duration = DAY_IN_SECONDS;
			if ( $days_since_last_episode >= $filtered_average_diff - 7 ) {
				$cache_duration = 6 * HOUR_IN_SECONDS;
			}
	
			if ( $days_since_last_episode >= $filtered_average_diff - 2 ) {
				$cache_duration = 1 * HOUR_IN_SECONDS;
			}
	
			if ( $days_since_last_episode >= $filtered_average_diff - 1 ) {
				$cache_duration = 30 * MINUTE_IN_SECONDS;
			}
		}

		return array( $is_active, $filtered_average_diff, $last_episode_release_date, $cache_duration );
	}
}
