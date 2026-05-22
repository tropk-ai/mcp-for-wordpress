<?php
declare(strict_types=1);

namespace Tropk\Mcp\Seo;

/**
 * Server-side on-page SEO auditor. Fetches a URL via wp_remote_get,
 * parses the HTML with DOMDocument, and returns a structured snapshot:
 * meta block, headings hierarchy, image alt coverage, link breakdown,
 * JSON-LD presence, plus a small set of derived issues + score.
 *
 * The output is intentionally structured (not free-form text) so an
 * agent can act on individual issues without re-parsing.
 */
final class PageAuditor {

	/**
	 * @return array<string, mixed>
	 */
	public function audit( string $url, int $timeout = 15 ): array {
		if ( '' === $url || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			throw new \RuntimeException( 'A valid URL is required.' );
		}

		$response = wp_remote_get(
			$url,
			[
				'timeout'     => max( 1, min( 60, $timeout ) ),
				'redirection' => 5,
				'user-agent'  => 'TropkMCP/1.0',
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Fetch failed: ' . $response->get_error_message() );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = (string) wp_remote_retrieve_body( $response );
		$ctype  = (string) wp_remote_retrieve_header( $response, 'content-type' );

		if ( '' === $body ) {
			return [
				'url'         => $url,
				'status'      => $status,
				'content_type' => $ctype,
				'issues'      => [ [ 'severity' => 'error', 'message' => 'Empty response body.' ] ],
				'score'       => 0,
			];
		}

		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $body );
		libxml_clear_errors();

		$xpath = new \DOMXPath( $dom );

		$meta       = $this->extract_meta( $dom, $xpath );
		$headings   = $this->extract_headings( $xpath );
		$images     = $this->extract_images( $xpath );
		$links      = $this->extract_links( $xpath, $url );
		$jsonld     = $this->extract_jsonld( $xpath );
		$text_stats = $this->text_stats( $xpath );

		$issues = $this->derive_issues( $meta, $headings, $images, $links, $jsonld, $text_stats );
		$score  = $this->derive_score( $issues );

		return [
			'url'          => $url,
			'status'       => $status,
			'content_type' => $ctype,
			'meta'         => $meta,
			'headings'     => $headings,
			'images'       => $images,
			'links'        => $links,
			'jsonld'       => $jsonld,
			'text_stats'   => $text_stats,
			'issues'       => $issues,
			'score'        => $score,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function extract_meta( \DOMDocument $dom, \DOMXPath $xpath ): array {
		$title_node = $xpath->query( '//head/title' )->item( 0 );
		$title      = $title_node ? trim( (string) $title_node->textContent ) : '';

		$description = $this->meta_content( $xpath, 'description' );
		$robots      = $this->meta_content( $xpath, 'robots' );
		$canonical   = $this->link_href( $xpath, 'canonical' );

		$og      = [];
		$twitter = [];
		foreach ( $xpath->query( "//meta[starts-with(@property, 'og:')]" ) as $node ) {
			if ( $node instanceof \DOMElement ) {
				$og[ (string) $node->getAttribute( 'property' ) ] = (string) $node->getAttribute( 'content' );
			}
		}
		foreach ( $xpath->query( "//meta[starts-with(@name, 'twitter:')]" ) as $node ) {
			if ( $node instanceof \DOMElement ) {
				$twitter[ (string) $node->getAttribute( 'name' ) ] = (string) $node->getAttribute( 'content' );
			}
		}

		$hreflang = [];
		foreach ( $xpath->query( "//link[@rel='alternate' and @hreflang]" ) as $node ) {
			if ( $node instanceof \DOMElement ) {
				$hreflang[ (string) $node->getAttribute( 'hreflang' ) ] = (string) $node->getAttribute( 'href' );
			}
		}

		return [
			'title'             => $title,
			'title_length'      => strlen( $title ),
			'description'       => $description,
			'description_length' => strlen( $description ),
			'robots'            => $robots,
			'canonical'         => $canonical,
			'og'                => $og,
			'twitter'           => $twitter,
			'hreflang'          => $hreflang,
		];
	}

	private function meta_content( \DOMXPath $xpath, string $name ): string {
		$node = $xpath->query( sprintf( "//meta[translate(@name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')='%s']", $name ) )->item( 0 );
		if ( $node instanceof \DOMElement ) {
			return (string) $node->getAttribute( 'content' );
		}
		return '';
	}

	private function link_href( \DOMXPath $xpath, string $rel ): string {
		$node = $xpath->query( sprintf( "//link[@rel='%s']", $rel ) )->item( 0 );
		if ( $node instanceof \DOMElement ) {
			return (string) $node->getAttribute( 'href' );
		}
		return '';
	}

	/**
	 * @return array<string, mixed>
	 */
	private function extract_headings( \DOMXPath $xpath ): array {
		$counts = [];
		$first_h1 = '';
		for ( $level = 1; $level <= 6; $level++ ) {
			$nodes = $xpath->query( '//h' . $level );
			$counts[ 'h' . $level ] = $nodes ? $nodes->length : 0;
			if ( 1 === $level && $nodes && $nodes->length > 0 ) {
				$first_h1 = trim( (string) $nodes->item( 0 )->textContent );
			}
		}
		return [
			'counts'   => $counts,
			'first_h1' => $first_h1,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function extract_images( \DOMXPath $xpath ): array {
		$images   = $xpath->query( '//img' );
		$total    = $images ? $images->length : 0;
		$missing  = 0;
		$samples  = [];
		if ( $images ) {
			foreach ( $images as $img ) {
				if ( ! $img instanceof \DOMElement ) {
					continue;
				}
				if ( '' === trim( (string) $img->getAttribute( 'alt' ) ) ) {
					$missing++;
					if ( count( $samples ) < 10 ) {
						$samples[] = (string) $img->getAttribute( 'src' );
					}
				}
			}
		}
		return [
			'total'           => $total,
			'missing_alt'     => $missing,
			'missing_samples' => $samples,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function extract_links( \DOMXPath $xpath, string $url ): array {
		$host     = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$anchors  = $xpath->query( '//a[@href]' );
		$internal = 0;
		$external = 0;
		$nofollow = 0;
		$total    = 0;
		if ( $anchors ) {
			foreach ( $anchors as $a ) {
				if ( ! $a instanceof \DOMElement ) {
					continue;
				}
				$href = (string) $a->getAttribute( 'href' );
				if ( '' === $href || str_starts_with( $href, '#' ) || str_starts_with( $href, 'javascript:' ) || str_starts_with( $href, 'mailto:' ) ) {
					continue;
				}
				$total++;
				$link_host = strtolower( (string) wp_parse_url( $href, PHP_URL_HOST ) );
				if ( '' === $link_host || $link_host === $host ) {
					$internal++;
				} else {
					$external++;
				}
				$rel = strtolower( (string) $a->getAttribute( 'rel' ) );
				if ( str_contains( $rel, 'nofollow' ) ) {
					$nofollow++;
				}
			}
		}
		return [
			'total'    => $total,
			'internal' => $internal,
			'external' => $external,
			'nofollow' => $nofollow,
		];
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function extract_jsonld( \DOMXPath $xpath ): array {
		$nodes = $xpath->query( "//script[@type='application/ld+json']" );
		$out   = [];
		if ( ! $nodes ) {
			return $out;
		}
		foreach ( $nodes as $node ) {
			$raw     = trim( (string) $node->textContent );
			$decoded = json_decode( $raw, true );
			$out[]   = [
				'valid' => is_array( $decoded ),
				'type'  => is_array( $decoded ) ? (string) ( $decoded['@type'] ?? '' ) : '',
				'bytes' => strlen( $raw ),
			];
		}
		return $out;
	}

	/**
	 * @return array<string, int>
	 */
	private function text_stats( \DOMXPath $xpath ): array {
		$body = $xpath->query( '//body' )->item( 0 );
		if ( ! $body instanceof \DOMElement ) {
			return [ 'word_count' => 0, 'char_count' => 0 ];
		}
		$text = trim( (string) $body->textContent );
		$text = preg_replace( '/\s+/', ' ', $text ) ?? '';
		$words = '' === $text ? 0 : count( explode( ' ', $text ) );
		return [
			'word_count' => $words,
			'char_count' => strlen( $text ),
		];
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private function derive_issues( array $meta, array $headings, array $images, array $links, array $jsonld, array $stats ): array {
		$issues = [];

		if ( '' === $meta['title'] ) {
			$issues[] = [ 'severity' => 'error', 'message' => 'Missing <title>.' ];
		} elseif ( $meta['title_length'] < 30 ) {
			$issues[] = [ 'severity' => 'warning', 'message' => 'Title is shorter than 30 characters.' ];
		} elseif ( $meta['title_length'] > 65 ) {
			$issues[] = [ 'severity' => 'warning', 'message' => 'Title exceeds 65 characters; may be truncated in SERP.' ];
		}

		if ( '' === $meta['description'] ) {
			$issues[] = [ 'severity' => 'warning', 'message' => 'Missing meta description.' ];
		} elseif ( $meta['description_length'] < 70 ) {
			$issues[] = [ 'severity' => 'info', 'message' => 'Meta description is shorter than 70 characters.' ];
		} elseif ( $meta['description_length'] > 160 ) {
			$issues[] = [ 'severity' => 'info', 'message' => 'Meta description exceeds 160 characters.' ];
		}

		if ( '' === $meta['canonical'] ) {
			$issues[] = [ 'severity' => 'info', 'message' => 'No canonical link declared.' ];
		}

		if ( 0 === $headings['counts']['h1'] ) {
			$issues[] = [ 'severity' => 'error', 'message' => 'No H1 heading on page.' ];
		} elseif ( $headings['counts']['h1'] > 1 ) {
			$issues[] = [ 'severity' => 'warning', 'message' => sprintf( 'Multiple H1 headings (%d).', $headings['counts']['h1'] ) ];
		}

		if ( $images['total'] > 0 && $images['missing_alt'] > 0 ) {
			$pct = (int) round( ( $images['missing_alt'] / $images['total'] ) * 100 );
			$issues[] = [ 'severity' => $pct >= 25 ? 'warning' : 'info', 'message' => sprintf( '%d/%d images missing alt text (%d%%).', $images['missing_alt'], $images['total'], $pct ) ];
		}

		if ( 0 === $links['internal'] ) {
			$issues[] = [ 'severity' => 'warning', 'message' => 'No internal links found.' ];
		}

		if ( [] === $jsonld ) {
			$issues[] = [ 'severity' => 'info', 'message' => 'No JSON-LD structured data detected.' ];
		} else {
			foreach ( $jsonld as $idx => $entry ) {
				if ( empty( $entry['valid'] ) ) {
					$issues[] = [ 'severity' => 'warning', 'message' => sprintf( 'JSON-LD block #%d failed to parse.', $idx + 1 ) ];
				}
			}
		}

		if ( $stats['word_count'] < 300 ) {
			$issues[] = [ 'severity' => 'info', 'message' => sprintf( 'Body word count is low (%d).', $stats['word_count'] ) ];
		}

		if ( [] === $meta['og'] ) {
			$issues[] = [ 'severity' => 'info', 'message' => 'No Open Graph tags detected.' ];
		}

		return $issues;
	}

	private function derive_score( array $issues ): int {
		$score = 100;
		foreach ( $issues as $issue ) {
			switch ( $issue['severity'] ?? 'info' ) {
				case 'error':
					$score -= 15;
					break;
				case 'warning':
					$score -= 7;
					break;
				case 'info':
					$score -= 2;
					break;
			}
		}
		return max( 0, min( 100, $score ) );
	}
}
