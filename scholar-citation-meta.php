<?php
/**
 * Plugin Name:       Scholar Citation Meta
 * Description:       Generates Google Scholar / Zotero (Highwire Press) citation meta tags for a page about a single work, from a DOI (Crossref, DataCite, doi.org) or pasted BibTeX. Also serves the PDF from an alias under the post's URL and offers BibTeX/RIS downloads. Shortcodes: [scholar_pdf], [scholar_pdf lang="fr"], [scholar_title], [scholar_translations], [scholar_cite].
 * Version:           1.8.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Dillon Mahmoudi
 * Author URI:        https://dillonm.io/
 * Plugin URI:        https://github.com/dillonma/scholar-citation-meta
 * Update URI:        https://github.com/dillonma/scholar-citation-meta
 * Text Domain:       scholar-citation-meta
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Optional settings (put in wp-config.php, above "That's all, stop editing"):
 *   define( 'SCM_POST_TYPES', 'post' );   // comma-separated post types that get the meta box
 *   define( 'SCM_PDF_PREFIX', 'papers' ); // first path segment of paper URLs, e.g. /papers/<slug>/
 *   define( 'SCM_PDF_ALIAS', true );      // false = don't add the .htaccess alias rules
 *   define( 'SCM_PDF_DIR', 'articles' );  // a folder outside the Media Library that holds PDFs ('' = none)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SCM_POST_TYPES' ) ) {
	define( 'SCM_POST_TYPES', 'post' );
}
if ( ! defined( 'SCM_PDF_PREFIX' ) ) {
	define( 'SCM_PDF_PREFIX', 'papers' );
}
if ( ! defined( 'SCM_PDF_ALIAS' ) ) {
	define( 'SCM_PDF_ALIAS', true );
}
if ( ! defined( 'SCM_PDF_DIR' ) ) {
	define( 'SCM_PDF_DIR', 'articles' );
}

function scm_post_types() {
	return array_filter( array_map( 'trim', explode( ',', SCM_POST_TYPES ) ) );
}

/* -------------------------------------------------------------------------
 * Admin meta box
 * ---------------------------------------------------------------------- */

add_action( 'add_meta_boxes', function () {
	foreach ( scm_post_types() as $pt ) {
		add_meta_box( 'scm_box', 'Scholar citation metadata', 'scm_render_box', $pt, 'normal', 'default' );
	}
} );

function scm_render_box( $post ) {
	wp_nonce_field( 'scm_save', 'scm_nonce' );
	$doi   = get_post_meta( $post->ID, '_scm_doi', true );
	$pdf   = get_post_meta( $post->ID, '_scm_pdf', true );
	$bib   = get_post_meta( $post->ID, '_scm_bibtex', true );
	$error = get_post_meta( $post->ID, '_scm_error', true );
	?>
	<p>
		<label for="scm_doi"><strong>DOI</strong></label><br>
		<input type="text" id="scm_doi" name="scm_doi" value="<?php echo esc_attr( $doi ); ?>" style="width:100%" placeholder="10.1080/24694452.2024.2353172">
		<span class="description">Metadata is fetched from Crossref, then DataCite, then doi.org, when you save. A doi.org link works too.</span>
	</p>
	<p>
		<label for="scm_pdf"><strong>PDF URL</strong></label><br>
		<input type="url" id="scm_pdf" name="scm_pdf" value="<?php echo esc_attr( $pdf ); ?>" style="width:100%" placeholder="https://example.org/wp-content/uploads/2024/06/paper.pdf">
		<span class="description">Paste the PDF's URL (Media Library or the /<?php echo esc_html( SCM_PDF_DIR ); ?>/ folder). The tags automatically use an alias under this post's URL so Google Scholar accepts it.</span>
	</p>
	<p>
		<label for="scm_bibtex"><strong>BibTeX (optional)</strong></label><br>
		<textarea id="scm_bibtex" name="scm_bibtex" rows="6" style="width:100%;font-family:monospace"><?php echo esc_textarea( $bib ); ?></textarea>
		<span class="description">Use this for items without a DOI, or to override any Crossref field. BibTeX values win.</span>
	</p>
	<?php scm_render_translations( $post->ID ); ?>
	<p><label><input type="checkbox" name="scm_refresh" value="1"> Re-fetch from Crossref on save</label></p>
	<?php
	if ( $error ) {
		echo '<p style="color:#b32d2e"><strong>Crossref:</strong> ' . esc_html( $error ) . '</p>';
	}
	$pdf_link = $pdf ? scm_pdf_url( $post->ID ) : '';
	if ( $pdf_link ) {
		$is_alias = ( $pdf_link !== $pdf );
		echo '<div style="background:#f0f6fc;border-left:4px solid #2271b1;padding:8px 12px;margin:12px 0">';
		echo '<p style="margin:0 0 6px"><strong>Use this link for "Download PDF" on the page</strong></p>';
		echo '<input type="text" id="scm_pdf_link" readonly value="' . esc_attr( $pdf_link ) . '" style="width:100%;font-family:monospace" onclick="this.select()">';
		echo '<p style="margin:6px 0 0"><button type="button" class="button" onclick="var f=document.getElementById(\'scm_pdf_link\');f.select();navigator.clipboard.writeText(f.value);this.textContent=\'Copied\';">Copy link</button> ';
		echo '<a href="' . esc_url( $pdf_link ) . '" target="_blank" rel="noopener">Test it</a> · or insert <code>[scholar_pdf]</code> in the post to output this link automatically.</p>';
		if ( $is_alias ) {
			$status = scm_htaccess_status();
			if ( 'ok' === $status ) {
				echo '<p style="margin:6px 0 0;color:#008a20">Alias rule found in .htaccess: Apache serves the PDF directly.</p>';
			} else {
				echo '<p style="margin:6px 0 0;color:#996800">' . ( 'missing' === $status ? 'Alias rule not found in .htaccess' : 'Could not read .htaccess' ) . ', so WordPress serves the PDF itself (this works too). For the faster Apache rule, go to Settings → Permalinks and click Save; if WordPress says .htaccess isn\'t writable, copy the rules it shows into the file.</p>';
			}
		}
		if ( ! $is_alias ) {
			echo '<p style="margin:6px 0 0;color:#996800">This is your original URL, not an alias: the PDF isn\'t directly in /' . esc_html( SCM_PDF_DIR ) . '/ or /uploads/YYYY/MM/, or the post isn\'t at /' . esc_html( SCM_PDF_PREFIX ) . '/&lt;slug&gt;/. Google Scholar may not link it to this page.</p>';
		}
		echo '</div>';
	}

	$cr = get_post_meta( $post->ID, '_scm_crossref', true );
	if ( is_array( $cr ) && ! empty( $cr['source'] ) ) {
		$d       = scm_get_data( $post->ID );
		$missing = array();
		foreach ( array( 'container' => 'journal', 'volume' => 'volume', 'issue' => 'issue', 'firstpage' => 'pages' ) as $k => $label ) {
			if ( '' === (string) $d[ $k ] ) {
				$missing[] = $label;
			}
		}
		echo '<p class="description">Metadata from <strong>' . esc_html( $cr['source'] ) . '</strong>.';
		if ( $missing && 'journal-article' === $d['type'] ) {
			echo ' <span style="color:#996800">Missing: ' . esc_html( implode( ', ', $missing ) ) . '. Add them with a short BibTeX entry, e.g. <code>@article{x, pages={327--336}}</code>.</span>';
		}
		echo '</p>';
	}

	$tags = scm_build_tags( $post->ID );
	if ( $tags ) {
		echo '<p><strong>Tags output on this page</strong> (reload the editor after saving to refresh)</p>';
		echo '<textarea readonly rows="12" style="width:100%;font-family:monospace;font-size:12px">' . esc_textarea( $tags ) . '</textarea>';
		$link = add_query_arg( 'scm_cite', 'bibtex', get_permalink( $post ) );
		echo '<p class="description">Downloads: <a href="' . esc_url( $link ) . '">BibTeX</a> · <a href="' . esc_url( add_query_arg( 'scm_cite', 'ris', get_permalink( $post ) ) ) . '">RIS</a>. Add <code>[scholar_cite]</code> to the post to show these links to readers.</p>';
	} elseif ( $doi || $bib ) {
		echo '<p style="color:#b32d2e">No tags yet: Google Scholar needs at least a title, one author and a date.</p>';
	}
	?>
	<details style="margin-top:12px">
		<summary><strong>Shortcodes</strong></summary>
		<table class="widefat striped" style="margin-top:8px">
			<thead><tr><th style="width:42%">Shortcode</th><th>Result</th></tr></thead>
			<tbody>
				<tr><td><code>[scholar_pdf]</code></td><td>PDF icon + bold <strong>Download PDF</strong> link to the alias</td></tr>
				<tr><td><code>[scholar_pdf text="Read the PDF"]</code></td><td>Same link with your own wording</td></tr>
				<tr><td><code>[scholar_pdf icon="0"]</code></td><td>Link without the PDF icon</td></tr>
				<tr><td><code>[scholar_pdf class="button"]</code></td><td>Adds your own CSS class (default <code>scholar-pdf</code>)</td></tr>
				<tr><td><code>[scholar_pdf url="1"]</code></td><td>Just the bare URL, to use inside your own link or button</td></tr>
				<tr><td><code>[scholar_cite]</code></td><td>"Cite: BibTeX · RIS" download links</td></tr>
				<tr><th colspan="2">Translations</th></tr>
				<tr><td><code>[scholar_pdf lang="fr"]</code></td><td>Link to the French PDF, using that row's link text (e.g. <strong>en français</strong>)</td></tr>
				<tr><td><code>[scholar_pdf] · [scholar_pdf lang="fr" icon="0"]</code></td><td><strong>Download PDF</strong> · <strong>en français</strong></td></tr>
				<tr><td><code>[scholar_title lang="fr"]</code></td><td>The translated title (<code>[scholar_title]</code> = original title)</td></tr>
				<tr><td><code>[scholar_translations]</code></td><td>A list of every translation: title and PDF link (and page link, if set). Add <code>titles="0"</code> to show links only</td></tr>
				<tr><td><code>[scholar_pdf url="https://…" text="en français" lang="fr"]</code></td><td>A one-off link that isn't stored in the box</td></tr>
			</tbody>
		</table>
		<p class="description">Options can be combined, e.g. <code>[scholar_pdf text="PDF" icon="0"]</code>.</p>
	</details>
	<?php
}

add_action( 'save_post', function ( $post_id ) {
	if ( ! isset( $_POST['scm_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scm_nonce'] ) ), 'scm_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$doi     = scm_clean_doi( wp_unslash( $_POST['scm_doi'] ?? '' ) );
	$pdf     = esc_url_raw( trim( wp_unslash( $_POST['scm_pdf'] ?? '' ) ) );
	$bib     = trim( sanitize_textarea_field( wp_unslash( $_POST['scm_bibtex'] ?? '' ) ) );
	$old_doi = get_post_meta( $post_id, '_scm_doi', true );

	update_post_meta( $post_id, '_scm_doi', $doi );
	update_post_meta( $post_id, '_scm_pdf', $pdf );
	update_post_meta( $post_id, '_scm_bibtex', wp_slash( $bib ) );
	scm_save_translations( $post_id, wp_unslash( $_POST['scm_tr'] ?? array() ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized per field

	if ( ! $doi ) {
		delete_post_meta( $post_id, '_scm_crossref' );
		delete_post_meta( $post_id, '_scm_error' );
		return;
	}
	$cached = get_post_meta( $post_id, '_scm_crossref', true );
	if ( $doi !== $old_doi || empty( $cached ) || ! empty( $_POST['scm_refresh'] ) ) {
		$res = scm_fetch_crossref( $doi );
		if ( is_wp_error( $res ) ) {
			update_post_meta( $post_id, '_scm_error', $res->get_error_message() );
		} else {
			update_post_meta( $post_id, '_scm_crossref', wp_slash( $res ) );
			delete_post_meta( $post_id, '_scm_error' );
			if ( ! empty( $res['doi'] ) && 0 !== strcasecmp( $res['doi'], $doi ) ) {
				update_post_meta( $post_id, '_scm_doi', $res['doi'] ); // store the clean DOI that actually resolved
			}
		}
	}
} );

/* -------------------------------------------------------------------------
 * Metadata sources
 * ---------------------------------------------------------------------- */

function scm_clean_doi( $doi ) {
	$doi = trim( rawurldecode( (string) $doi ) );
	$doi = preg_replace( '#^(https?://(dx\.)?doi\.org/|doi:\s*)#i', '', $doi );
	// Accept a publisher URL too, e.g. https://www.taylorfrancis.com/chapters/oa-edit/10.1201/…-9/slug
	if ( preg_match( '~(10\.\d{4,9}/[-._;()/:A-Za-z0-9]+)~', $doi, $m ) ) {
		$doi = rtrim( $m[1], '.,;:' );
	}
	return trim( $doi );
}

function scm_clean_text( $s ) {
	$s = wp_strip_all_tags( html_entity_decode( (string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	return trim( preg_replace( '/\s+/u', ' ', $s ) );
}

function scm_date_from_parts( $parts ) {
	$parts = array_values( array_filter( array_map( 'intval', (array) $parts ) ) );
	return $parts ? implode( '/', $parts ) : ''; // Scholar format: 2010/5/12, or just the year
}

function scm_split_pages( $pages ) {
	$p = preg_split( '/\s*(?:-+|–|—)\s*/u', trim( (string) $pages ) );
	return array( $p[0] ?? '', $p[1] ?? '' );
}

function scm_first( $v ) {
	return is_array( $v ) ? (string) ( $v[0] ?? '' ) : (string) $v;
}

/**
 * Some registrars (often DataCite/OJS journals) put text like "Vol. 21 No. 4 (2022): Title"
 * in the page field. Keep it only if it looks like page numbers; otherwise mine it for
 * volume/issue. Returns [firstpage, lastpage, volume, issue].
 */
function scm_clean_pages( $raw, $volume = '', $issue = '' ) {
	$raw = trim( (string) $raw );
	if ( preg_match( '/^[A-Za-z]{0,2}\d+[A-Za-z]?(\s*(?:-+|–|—)\s*[A-Za-z]{0,2}\d+[A-Za-z]?)?$/u', $raw ) ) {
		list( $f, $l ) = scm_split_pages( $raw );
		return array( $f, $l, $volume, $issue );
	}
	if ( '' === (string) $volume && preg_match( '/\bVol(?:ume)?\.?\s*(\d+)/i', $raw, $m ) ) {
		$volume = $m[1];
	}
	if ( '' === (string) $issue && preg_match( '/\b(?:No|Nr|Iss(?:ue)?)\.?\s*(\d+)/i', $raw, $m ) ) {
		$issue = $m[1];
	}
	return array( '', '', $volume, $issue );
}

function scm_user_agent() {
	return 'ScholarCitationMeta/1.8 (' . home_url() . '; mailto:' . get_option( 'admin_email' ) . ')';
}

/**
 * DataCite REST API lookup (used when Crossref has no record). Returns the record mapped to the
 * Crossref/CSL-style keys the normalizer already understands, or null.
 */
function scm_fetch_datacite( $path, $ua ) {
	$r = wp_remote_get( 'https://api.datacite.org/dois/' . $path, array(
		'timeout' => 10,
		'headers' => array( 'Accept' => 'application/vnd.api+json', 'User-Agent' => $ua ),
	) );
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		return null;
	}
	$j = json_decode( wp_remote_retrieve_body( $r ), true );
	$a = $j['data']['attributes'] ?? null;
	if ( ! is_array( $a ) ) {
		return null;
	}

	$title = '';
	$sub   = '';
	foreach ( (array) ( $a['titles'] ?? array() ) as $t ) {
		if ( empty( $t['title'] ) ) {
			continue;
		}
		$tt = $t['titleType'] ?? '';
		if ( '' === $tt && '' === $title ) {
			$title = $t['title'];
		} elseif ( 'Subtitle' === $tt && '' === $sub ) {
			$sub = $t['title'];
		}
	}
	if ( '' === $title ) {
		return null;
	}

	$authors = array();
	foreach ( (array) ( $a['creators'] ?? array() ) as $c ) {
		if ( ! empty( $c['familyName'] ) ) {
			$authors[] = array( 'family' => $c['familyName'], 'given' => $c['givenName'] ?? '' );
		} elseif ( ! empty( $c['name'] ) ) {
			$authors[] = array( 'literal' => $c['name'] );
		}
	}

	$date = '';
	foreach ( (array) ( $a['dates'] ?? array() ) as $d ) {
		if ( 'Issued' === ( $d['dateType'] ?? '' ) && ! empty( $d['date'] ) ) {
			$date = $d['date'];
			break;
		}
	}
	$parts = $date ? array_map( 'intval', explode( '-', substr( $date, 0, 10 ) ) ) : array( (int) ( $a['publicationYear'] ?? 0 ) );

	$cont  = (array) ( $a['container'] ?? array() );
	$page  = trim( (string) ( $cont['firstPage'] ?? '' ) );
	if ( '' !== $page && ! empty( $cont['lastPage'] ) ) {
		$page .= '-' . $cont['lastPage'];
	}
	$issn = array();
	if ( 'ISSN' === strtoupper( (string) ( $cont['identifierType'] ?? '' ) ) && ! empty( $cont['identifier'] ) ) {
		$issn[] = $cont['identifier'];
	}
	foreach ( (array) ( $a['relatedIdentifiers'] ?? array() ) as $ri ) {
		if ( 'ISSN' === strtoupper( (string) ( $ri['relatedIdentifierType'] ?? '' ) ) && 'IsPartOf' === ( $ri['relationType'] ?? '' ) ) {
			$issn[] = $ri['relatedIdentifier'];
		}
	}
	$publisher = $a['publisher'] ?? '';
	if ( is_array( $publisher ) ) {
		$publisher = $publisher['name'] ?? '';
	}
	$type = (string) ( $a['types']['citeproc'] ?? '' );
	if ( '' === $type ) {
		$map  = array( 'JournalArticle' => 'article-journal', 'BookChapter' => 'chapter', 'Book' => 'book', 'ConferencePaper' => 'paper-conference', 'Dissertation' => 'thesis', 'Report' => 'report', 'Preprint' => 'article', 'Dataset' => 'dataset', 'Software' => 'software' );
		$type = $map[ $a['types']['resourceTypeGeneral'] ?? '' ] ?? 'article';
	}
	$container_title = ( 'DataRepository' === ( $cont['type'] ?? '' ) ) ? '' : (string) ( $cont['title'] ?? '' );

	return array(
		'type'            => $type,
		'title'           => $title,
		'subtitle'        => $sub,
		'author'          => $authors,
		'issued'          => array( 'date-parts' => array( array_values( array_filter( $parts ) ) ) ),
		'container-title' => $container_title,
		'volume'          => (string) ( $cont['volume'] ?? '' ),
		'issue'           => (string) ( $cont['issue'] ?? '' ),
		'page'            => $page,
		'ISSN'            => array_values( array_unique( $issn ) ),
		'publisher'       => (string) $publisher,
		'DOI'             => (string) ( $a['doi'] ?? '' ),
	);
}

/**
 * Look up a DOI: Crossref first, then DataCite, then doi.org content negotiation (CSL-JSON),
 * which covers mEDRA, JaLC and the other registration agencies. Kept under the old name for compatibility.
 */
/** Look up a DOI; if it fails, drop trailing "/slug" segments (publisher URLs) and try again. */
function scm_fetch_crossref( $doi ) {
	$first = null;
	$cand  = $doi;
	while ( true ) {
		$res = scm_fetch_one( $cand );
		if ( ! is_wp_error( $res ) ) {
			return $res;
		}
		$first = $first ?: $res;
		if ( substr_count( $cand, '/' ) < 2 ) {
			return $first;
		}
		$cand = substr( $cand, 0, strrpos( $cand, '/' ) );
	}
}

function scm_fetch_one( $doi ) {
	$path = implode( '/', array_map( 'rawurlencode', explode( '/', $doi ) ) );
	$m    = null;
	$from = '';

	$resp = wp_remote_get( 'https://api.crossref.org/works/' . $path, array( 'timeout' => 15, 'headers' => array( 'User-Agent' => scm_user_agent() ) ) );
	if ( ! is_wp_error( $resp ) && 200 === (int) wp_remote_retrieve_response_code( $resp ) ) {
		$json = json_decode( wp_remote_retrieve_body( $resp ), true );
		$m    = is_array( $json['message'] ?? null ) ? $json['message'] : null;
		$from = 'Crossref';
	}
	if ( ! $m ) {
		$m    = scm_fetch_datacite( $path, scm_user_agent() );
		$from = $m ? 'DataCite' : '';
	}
	if ( ! $m ) {
		$resp = wp_remote_get( 'https://doi.org/' . $path, array(
			'timeout'     => 15,
			'redirection' => 5,
			'headers'     => array( 'Accept' => 'application/vnd.citationstyles.csl+json', 'User-Agent' => scm_user_agent() ),
		) );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		$json = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( 200 !== $code || ! is_array( $json ) || empty( $json['title'] ) ) {
			return new WP_Error( 'scm_http', sprintf( 'Crossref, DataCite and doi.org returned no metadata for DOI %s (HTTP %d). Paste a BibTeX entry instead.', $doi, $code ) );
		}
		$m    = $json;
		$from = 'doi.org';
	}

	$types = array(
		'article-journal' => 'journal-article', 'chapter' => 'book-chapter', 'paper-conference' => 'proceedings-article',
		'thesis' => 'dissertation', 'book-section' => 'book-chapter', 'monograph' => 'book', 'edited-book' => 'book',
	);
	$type = strtolower( (string) ( $m['type'] ?? 'journal-article' ) );
	$type = $types[ $type ] ?? $type;

	$title = scm_clean_text( scm_first( $m['title'] ?? '' ) );
	$sub   = scm_clean_text( scm_first( $m['subtitle'] ?? '' ) );
	if ( $sub && false === stripos( $title, $sub ) ) {
		$title .= ': ' . $sub;
	}

	$authors = array();
	foreach ( (array) ( $m['author'] ?? array() ) as $a ) {
		if ( ! empty( $a['family'] ) ) {
			$authors[] = trim( $a['family'] . ( empty( $a['given'] ) ? '' : ', ' . $a['given'] ) );
		} elseif ( ! empty( $a['literal'] ) || ! empty( $a['name'] ) ) {
			$authors[] = $a['literal'] ?? $a['name'];
		}
	}

	$parts = $m['issued']['date-parts'][0] ?? ( $m['published-print']['date-parts'][0] ?? ( $m['published-online']['date-parts'][0] ?? array() ) );
	list( $first, $last, $volume, $issue ) = scm_clean_pages( $m['page'] ?? '', (string) ( $m['volume'] ?? '' ), (string) ( $m['issue'] ?? '' ) );

	// Keep the DOI as you typed it (doi.org sometimes returns it upper-cased).
	$ret_doi = (string) ( $m['DOI'] ?? $doi );
	if ( 0 === strcasecmp( $ret_doi, $doi ) ) {
		$ret_doi = $doi;
	}

	return array(
		'type'      => $type,
		'title'     => $title,
		'authors'   => $authors,
		'date'      => scm_date_from_parts( $parts ),
		'container' => scm_clean_text( scm_first( $m['container-title'] ?? '' ) ),
		'volume'    => $volume,
		'issue'     => $issue,
		'firstpage' => $first ?: (string) ( $m['article-number'] ?? '' ),
		'lastpage'  => $last,
		'issn'      => array_values( array_unique( array_filter( (array) ( $m['ISSN'] ?? array() ) ) ) ),
		'isbn'      => array_values( array_unique( array_filter( (array) ( $m['ISBN'] ?? array() ) ) ) ),
		'publisher' => scm_clean_text( $m['publisher'] ?? '' ),
		'doi'       => $ret_doi,
		'source'    => $from,
	);
}

/** Strip common LaTeX markup from a BibTeX value. Use UTF-8 characters in BibTeX for best results. */
function scm_bib_clean( $s ) {
	$s = str_replace( array( '\\&', '\\%', '\\$', '\\_', '\\#', '~' ), array( '&', '%', '$', '_', '#', ' ' ), $s );
	$s = preg_replace( '/\\\\[\'"`^~=.]/', '', $s );       // accent commands: \'e -> e
	$s = preg_replace( '/\\\\[a-zA-Z]+\*?\s*/', '', $s );  // \textit, \emph, ...
	$s = str_replace( array( '{', '}' ), '', $s );
	return trim( preg_replace( '/\s+/u', ' ', $s ) );
}

function scm_parse_bibtex( $bib ) {
	if ( ! preg_match( '/@(\w+)\s*\{\s*[^,\s]*\s*,/', $bib, $m, PREG_OFFSET_CAPTURE ) ) {
		return array();
	}
	$entry = strtolower( $m[1][0] );
	$pos   = $m[0][1] + strlen( $m[0][0] );
	$len   = strlen( $bib );
	$f     = array();

	while ( $pos < $len && preg_match( '/\G\s*,?\s*([A-Za-z][\w\-]*)\s*=\s*/', $bib, $km, 0, $pos ) ) {
		$key  = strtolower( $km[1] );
		$pos += strlen( $km[0] );
		$ch   = $bib[ $pos ] ?? '';
		if ( '{' === $ch ) {
			$depth = 0;
			for ( $i = $pos; $i < $len; $i++ ) {
				if ( '{' === $bib[ $i ] ) {
					$depth++;
				} elseif ( '}' === $bib[ $i ] && 0 === --$depth ) {
					break;
				}
			}
			$val = substr( $bib, $pos + 1, $i - $pos - 1 );
			$pos = $i + 1;
		} elseif ( '"' === $ch ) {
			$depth = 0;
			for ( $i = $pos + 1; $i < $len; $i++ ) {
				$c = $bib[ $i ];
				if ( '{' === $c ) {
					$depth++;
				} elseif ( '}' === $c ) {
					$depth--;
				} elseif ( '"' === $c && 0 === $depth ) {
					break;
				}
			}
			$val = substr( $bib, $pos + 1, $i - $pos - 1 );
			$pos = $i + 1;
		} else {
			preg_match( '/\G[^,}\s]+/', $bib, $vm, 0, $pos );
			$val  = $vm[0] ?? '';
			$pos += strlen( $val );
		}
		$f[ $key ] = $val;
	}

	$types = array(
		'article'       => 'journal-article',
		'incollection'  => 'book-chapter',
		'inbook'        => 'book-chapter',
		'inproceedings' => 'proceedings-article',
		'conference'    => 'proceedings-article',
		'book'          => 'book',
		'techreport'    => 'report',
		'report'        => 'report',
		'phdthesis'     => 'dissertation',
		'mastersthesis' => 'dissertation',
		'thesis'        => 'dissertation',
	);

	$out = array( 'type' => $types[ $entry ] ?? 'other' );

	if ( isset( $f['title'] ) ) {
		$out['title'] = scm_bib_clean( $f['title'] );
	}
	if ( isset( $f['author'] ) ) {
		$out['authors'] = array_values( array_filter( array_map( 'scm_bib_clean', preg_split( '/\s+and\s+/i', $f['author'] ) ) ) );
	}

	// Date: biblatex "date = {2024-06-18}" or year/month/day.
	if ( ! empty( $f['date'] ) ) {
		$out['date'] = scm_date_from_parts( preg_split( '/[-\/]/', scm_bib_clean( $f['date'] ) ) );
	} elseif ( ! empty( $f['year'] ) ) {
		$months = array( 'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12 );
		$parts  = array( (int) scm_bib_clean( $f['year'] ) );
		if ( ! empty( $f['month'] ) ) {
			$mo = strtolower( substr( scm_bib_clean( $f['month'] ), 0, 3 ) );
			$mo = $months[ $mo ] ?? (int) $mo;
			if ( $mo ) {
				$parts[] = $mo;
				if ( ! empty( $f['day'] ) ) {
					$parts[] = (int) scm_bib_clean( $f['day'] );
				}
			}
		}
		$out['date'] = scm_date_from_parts( $parts );
	}

	foreach ( array( 'journal', 'journaltitle', 'booktitle' ) as $k ) {
		if ( ! empty( $f[ $k ] ) ) {
			$out['container'] = scm_bib_clean( $f[ $k ] );
			break;
		}
	}
	$simple = array( 'volume' => 'volume', 'number' => 'issue', 'issue' => 'issue', 'publisher' => 'publisher', 'institution' => 'institution', 'school' => 'institution' );
	foreach ( $simple as $from => $to ) {
		if ( ! empty( $f[ $from ] ) ) {
			$out[ $to ] = scm_bib_clean( $f[ $from ] );
		}
	}
	if ( ! empty( $f['pages'] ) ) {
		list( $out['firstpage'], $out['lastpage'] ) = scm_split_pages( scm_bib_clean( $f['pages'] ) );
	}
	if ( ! empty( $f['doi'] ) ) {
		$out['doi'] = scm_clean_doi( scm_bib_clean( $f['doi'] ) );
	}
	foreach ( array( 'issn', 'isbn' ) as $k ) {
		if ( ! empty( $f[ $k ] ) ) {
			$out[ $k ] = array_values( array_filter( array_map( 'trim', preg_split( '/[,;]/', scm_bib_clean( $f[ $k ] ) ) ) ) );
		}
	}
	return $out;
}

/** Merged metadata for a post: Crossref first, then BibTeX overrides. */
function scm_get_data( $post_id ) {
	$d   = get_post_meta( $post_id, '_scm_crossref', true );
	$d   = is_array( $d ) ? $d : array();
	$bib = get_post_meta( $post_id, '_scm_bibtex', true );
	if ( $bib ) {
		foreach ( scm_parse_bibtex( $bib ) as $k => $v ) {
			if ( '' !== $v && array() !== $v && ! ( 'type' === $k && 'other' === $v && ! empty( $d['type'] ) ) ) {
				$d[ $k ] = $v;
			}
		}
	}
	if ( ! $d ) {
		return array();
	}
	$d += array(
		'type' => 'journal-article', 'title' => '', 'authors' => array(), 'date' => '', 'container' => '',
		'volume' => '', 'issue' => '', 'firstpage' => '', 'lastpage' => '', 'issn' => array(), 'isbn' => array(),
		'publisher' => '', 'institution' => '', 'doi' => '',
	);
	$d['pdf_url'] = scm_pdf_url( $post_id );
	return apply_filters( 'scm_data', $d, $post_id );
}

/* -------------------------------------------------------------------------
 * PDF aliases (Google Scholar wants the PDF in the same "folder" as the abstract page):
 *   /papers/<slug>/YYYY/MM/file.pdf  ->  /wp-content/uploads/YYYY/MM/file.pdf  (Media Library)
 *   /papers/<slug>/file.pdf          ->  /articles/file.pdf                     (SCM_PDF_DIR)
 * ---------------------------------------------------------------------- */

/** Path of $url relative to $base_url (scheme-insensitive), or null if $url isn't under it. */
function scm_relative_to( $url, $base_url ) {
	$base = trailingslashit( $base_url );
	foreach ( array( set_url_scheme( $base, 'https' ), set_url_scheme( $base, 'http' ) ) as $b ) {
		if ( 0 === stripos( $url, $b ) ) {
			return substr( $url, strlen( $b ) );
		}
	}
	return null;
}

function scm_pdf_url( $post_id ) {
	return scm_alias_url( $post_id, (string) get_post_meta( $post_id, '_scm_pdf', true ) );
}

/** The Scholar-friendly alias for any PDF URL on this post (main PDF or a translation), or the URL unchanged. */
function scm_alias_url( $post_id, $pdf ) {
	$pdf = (string) $pdf;
	if ( ! $pdf || ! SCM_PDF_ALIAS ) {
		return $pdf;
	}
	$permalink = trailingslashit( get_permalink( $post_id ) );
	$path      = (string) wp_parse_url( $permalink, PHP_URL_PATH );
	if ( ! preg_match( '#^/' . preg_quote( SCM_PDF_PREFIX, '#' ) . '/[^/]+/$#', $path ) ) {
		return $pdf; // post isn't at /papers/<slug>/, so no alias
	}

	$rel = scm_relative_to( $pdf, wp_get_upload_dir()['baseurl'] );
	if ( null !== $rel && preg_match( '#^\d{4}/\d{2}/[^/]+\.pdf$#i', $rel ) ) {
		return $permalink . $rel;
	}

	if ( SCM_PDF_DIR ) {
		$rel = scm_relative_to( $pdf, home_url( '/' . trim( SCM_PDF_DIR, '/' ) ) );
		// Single file name only, and not something that looks like the YYYY/MM uploads pattern.
		if ( null !== $rel && preg_match( '#^[^/]+\.pdf$#i', $rel ) ) {
			return $permalink . $rel;
		}
	}
	return $pdf;
}

/** Adds the alias rules inside WordPress's own .htaccess block (written when permalinks are saved). */
function scm_mod_rewrite_rules( $rules ) {
	if ( ! SCM_PDF_ALIAS ) {
		return $rules;
	}
	$prefix  = preg_quote( SCM_PDF_PREFIX, '#' );
	$uploads = untrailingslashit( wp_make_link_relative( wp_get_upload_dir()['baseurl'] ) );
	$add     = '# Scholar Citation Meta: PDF aliases' . "\n";
	$add    .= 'RewriteRule ^' . $prefix . '/[^/]+/(\d{4}/\d{2}/[^/]+\.pdf)$ ' . $uploads . '/$1 [L,NC]' . "\n";
	if ( SCM_PDF_DIR ) {
		$dir  = untrailingslashit( wp_make_link_relative( home_url( '/' . trim( SCM_PDF_DIR, '/' ) ) ) );
		$add .= 'RewriteRule ^' . $prefix . '/[^/]+/([^/]+\.pdf)$ ' . $dir . '/$1 [L,NC]' . "\n";
	}
	$anchor = 'RewriteRule ^index\.php$ - [L]' . "\n";
	if ( false !== strpos( $rules, $anchor ) ) {
		return str_replace( $anchor, $anchor . $add, $rules );
	}
	return $rules;
}
add_filter( 'mod_rewrite_rules', 'scm_mod_rewrite_rules' );

/** Absolute filesystem path of the file an alias path points to, or '' if none / not found. */
function scm_alias_target_file( $request_path ) {
	$prefix = preg_quote( SCM_PDF_PREFIX, '#' );
	$path   = rawurldecode( $request_path );
	if ( preg_match( '#^/' . $prefix . '/[^/]+/(\d{4}/\d{2})/([^/]+\.pdf)$#i', $path, $m ) ) {
		$dir  = wp_get_upload_dir()['basedir'] . '/' . $m[1];
		$file = $m[2];
	} elseif ( SCM_PDF_DIR && preg_match( '#^/' . $prefix . '/[^/]+/([^/]+\.pdf)$#i', $path, $m ) ) {
		$root = ! empty( $_SERVER['DOCUMENT_ROOT'] ) ? $_SERVER['DOCUMENT_ROOT'] : ABSPATH;
		$dir  = untrailingslashit( $root ) . '/' . trim( SCM_PDF_DIR, '/' );
		$file = $m[1];
	} else {
		return '';
	}
	$real_dir  = realpath( $dir );
	$real_file = $real_dir ? realpath( $real_dir . '/' . basename( $file ) ) : false;
	// Only serve real .pdf files that sit inside the expected folder.
	if ( ! $real_file || 0 !== strpos( $real_file, $real_dir . DIRECTORY_SEPARATOR ) || ! is_file( $real_file ) ) {
		return '';
	}
	return $real_file;
}

/**
 * Fallback: if the .htaccess rule is missing (file not writable, host ignores it, etc.)
 * the request reaches WordPress, which would show a 404. Serve the PDF from PHP instead.
 */
add_action( 'init', function () {
	if ( ! SCM_PDF_ALIAS || empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}
	$path = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
	$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( '/' !== $home && 0 === strpos( $path, rtrim( $home, '/' ) ) ) {
		$path = substr( $path, strlen( rtrim( $home, '/' ) ) ); // WordPress in a subdirectory
	}
	if ( ! preg_match( '#\.pdf$#i', $path ) ) {
		return;
	}
	$file = scm_alias_target_file( $path );
	if ( ! $file ) {
		return;
	}
	$mtime = filemtime( $file );
	$etag  = '"' . md5( $file . $mtime . filesize( $file ) ) . '"';
	while ( ob_get_level() ) {
		ob_end_clean();
	}
	status_header( 200 );
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: inline; filename="' . basename( $file ) . '"' );
	header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $mtime ) . ' GMT' );
	header( 'ETag: ' . $etag );
	header( 'Cache-Control: public, max-age=86400' );
	header( 'X-Scholar-Citation-Meta: php-fallback' );
	$inm = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? trim( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) : '';
	if ( $inm === $etag ) {
		status_header( 304 );
		exit;
	}
	header( 'Content-Length: ' . filesize( $file ) );
	if ( 'HEAD' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	exit;
}, 0 );

/** 'ok' if the alias rules are in .htaccess, 'missing' if not, 'unknown' if the file can't be read. */
function scm_htaccess_status() {
	$root = ! empty( $_SERVER['DOCUMENT_ROOT'] ) ? $_SERVER['DOCUMENT_ROOT'] : ABSPATH;
	$file = untrailingslashit( $root ) . '/.htaccess';
	if ( ! is_readable( $file ) ) {
		$file = ABSPATH . '.htaccess';
	}
	if ( ! is_readable( $file ) ) {
		return 'unknown';
	}
	$c = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( false === strpos( $c, '# Scholar Citation Meta: PDF aliases' ) ) {
		return 'missing';
	}
	if ( SCM_PDF_DIR && false === strpos( $c, '/' . trim( SCM_PDF_DIR, '/' ) . '/$1' ) ) {
		return 'missing';
	}
	return 'ok';
}

register_activation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );
register_deactivation_hook( __FILE__, function () {
	remove_filter( 'mod_rewrite_rules', 'scm_mod_rewrite_rules' );
	flush_rewrite_rules();
} );

/* -------------------------------------------------------------------------
 * Front end: meta tags, BibTeX/RIS downloads, [scholar_cite] shortcode
 * ---------------------------------------------------------------------- */

function scm_build_tags( $post_id ) {
	$d = scm_get_data( $post_id );
	// Scholar ignores the tags unless title, at least one author, and a date are present.
	if ( ! $d || '' === $d['title'] || ! $d['authors'] || '' === $d['date'] ) {
		return '';
	}
	$t   = array();
	$add = function ( $name, $value ) use ( &$t ) {
		if ( '' !== (string) $value ) {
			$t[] = sprintf( '<meta name="%s" content="%s">', esc_attr( $name ), esc_attr( $value ) );
		}
	};

	$add( 'citation_title', $d['title'] );
	foreach ( $d['authors'] as $a ) {
		$add( 'citation_author', $a );
	}
	$add( 'citation_publication_date', $d['date'] );

	switch ( $d['type'] ) {
		case 'journal-article':
			$add( 'citation_journal_title', $d['container'] );
			break;
		case 'book-chapter':
			$add( 'citation_inbook_title', $d['container'] );
			$add( 'citation_book_title', $d['container'] );
			break;
		case 'proceedings-article':
			$add( 'citation_conference_title', $d['container'] );
			break;
		case 'report':
			$add( 'citation_technical_report_institution', $d['institution'] ?: $d['publisher'] );
			break;
		case 'dissertation':
			$add( 'citation_dissertation_institution', $d['institution'] ?: $d['publisher'] );
			break;
		default:
			$add( 'citation_journal_title', $d['container'] );
	}

	$add( 'citation_volume', $d['volume'] );
	$add( 'citation_issue', $d['issue'] );
	$add( 'citation_firstpage', $d['firstpage'] );
	$add( 'citation_lastpage', $d['lastpage'] );
	foreach ( (array) $d['issn'] as $v ) {
		$add( 'citation_issn', $v );
	}
	foreach ( (array) $d['isbn'] as $v ) {
		$add( 'citation_isbn', $v );
	}
	$add( 'citation_publisher', $d['publisher'] );
	$add( 'citation_doi', $d['doi'] );
	$add( 'citation_abstract_html_url', get_permalink( $post_id ) );
	$add( 'citation_pdf_url', $d['pdf_url'] );

	return implode( "\n", apply_filters( 'scm_tags', $t, $d, $post_id ) );
}

add_action( 'wp_head', function () {
	if ( ! is_singular( scm_post_types() ) ) {
		return;
	}
	$tags = scm_build_tags( get_queried_object_id() );
	if ( $tags ) {
		echo "\n<!-- Scholar Citation Meta -->\n" . $tags . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in scm_build_tags()
	}
}, 1 );

function scm_year( $d ) {
	return (string) strtok( (string) $d['date'], '/' );
}

function scm_cite_key( $d ) {
	$first = $d['authors'][0] ?? 'anon';
	$fam   = strtolower( preg_replace( '/[^A-Za-z]/', '', remove_accents( strtok( $first, ',' ) ) ) );
	preg_match( '/[A-Za-z]{4,}/', remove_accents( preg_replace( '/^(the|a|an)\s+/i', '', $d['title'] ) ), $w );
	return $fam . scm_year( $d ) . strtolower( $w[0] ?? '' );
}

function scm_to_bibtex( $d, $post_id ) {
	$types = array( 'journal-article' => 'article', 'book-chapter' => 'incollection', 'proceedings-article' => 'inproceedings', 'book' => 'book', 'report' => 'techreport', 'dissertation' => 'phdthesis' );
	$esc   = function ( $s ) {
		return str_replace( array( '&', '%', '#' ), array( '\\&', '\\%', '\\#' ), (string) $s );
	};
	$parts = explode( '/', $d['date'] );
	$f     = array(
		'title'     => '{' . $esc( $d['title'] ) . '}',
		'author'    => $esc( implode( ' and ', $d['authors'] ) ),
		'year'      => scm_year( $d ),
		'month'     => $parts[1] ?? '',
		'volume'    => $d['volume'],
		'number'    => $d['issue'],
		'pages'     => $d['firstpage'] . ( $d['lastpage'] ? '--' . $d['lastpage'] : '' ),
		'publisher' => $esc( $d['publisher'] ),
		'doi'       => $d['doi'],
		'issn'      => implode( ', ', (array) $d['issn'] ),
		'isbn'      => implode( ', ', (array) $d['isbn'] ),
		'url'       => $d['doi'] ? 'https://doi.org/' . $d['doi'] : get_permalink( $post_id ),
	);
	$container_key = in_array( $d['type'], array( 'book-chapter', 'proceedings-article' ), true ) ? 'booktitle' : 'journal';
	if ( $d['container'] ) {
		$f = array( $container_key => $esc( $d['container'] ) ) + $f;
	}
	if ( in_array( $d['type'], array( 'report', 'dissertation' ), true ) ) {
		$f[ 'report' === $d['type'] ? 'institution' : 'school' ] = $esc( $d['institution'] ?: $d['publisher'] );
	}
	$lines = array();
	foreach ( $f as $k => $v ) {
		if ( '' !== (string) $v ) {
			$lines[] = sprintf( '  %s = {%s}', $k, $v );
		}
	}
	return sprintf( "@%s{%s,\n%s\n}\n", $types[ $d['type'] ] ?? 'misc', scm_cite_key( $d ), implode( ",\n", $lines ) );
}

function scm_to_ris( $d, $post_id ) {
	$types = array( 'journal-article' => 'JOUR', 'book-chapter' => 'CHAP', 'proceedings-article' => 'CPAPER', 'book' => 'BOOK', 'report' => 'RPRT', 'dissertation' => 'THES' );
	$r     = array( 'TY  - ' . ( $types[ $d['type'] ] ?? 'GEN' ), 'TI  - ' . $d['title'] );
	foreach ( $d['authors'] as $a ) {
		$r[] = 'AU  - ' . $a;
	}
	$r[] = 'PY  - ' . scm_year( $d );
	$r[] = 'DA  - ' . $d['date'];
	$map = array( 'T2' => $d['container'], 'VL' => $d['volume'], 'IS' => $d['issue'], 'SP' => $d['firstpage'], 'EP' => $d['lastpage'], 'PB' => $d['publisher'] ?: $d['institution'], 'DO' => $d['doi'], 'UR' => get_permalink( $post_id ), 'L1' => $d['pdf_url'] );
	foreach ( $map as $k => $v ) {
		if ( '' !== (string) $v ) {
			$r[] = $k . '  - ' . $v;
		}
	}
	foreach ( array_merge( (array) $d['issn'], (array) $d['isbn'] ) as $v ) {
		$r[] = 'SN  - ' . $v;
	}
	$r[] = 'ER  - ';
	return implode( "\r\n", $r ) . "\r\n";
}

add_action( 'template_redirect', function () {
	if ( empty( $_GET['scm_cite'] ) || ! is_singular( scm_post_types() ) ) {
		return;
	}
	$fmt = sanitize_key( wp_unslash( $_GET['scm_cite'] ) );
	$id  = get_queried_object_id();
	$d   = scm_get_data( $id );
	if ( ! $d || '' === $d['title'] ) {
		return;
	}
	if ( 'bibtex' === $fmt ) {
		$body = scm_to_bibtex( $d, $id );
		$mime = 'application/x-bibtex';
		$ext  = 'bib';
	} elseif ( 'ris' === $fmt ) {
		$body = scm_to_ris( $d, $id );
		$mime = 'application/x-research-info-systems';
		$ext  = 'ris';
	} else {
		return;
	}
	nocache_headers();
	header( 'Content-Type: ' . $mime . '; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( get_post_field( 'post_name', $id ) ) . '.' . $ext . '"' );
	header( 'X-Robots-Tag: noindex' );
	echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- plain-text file download
	exit;
} );

/**
 * [scholar_pdf] outputs the PDF link (alias URL) for the current post.
 * Options: text="Download PDF" (alias: label) icon="1" class="..." url="1" (bare URL only)
 * Translations: lang="fr" uses the French row from the Translations table.
 * One-off link: url="https://…" (alias: link) with text/label and optional lang.
 */
add_shortcode( 'scholar_pdf', function ( $atts ) {
	$atts = (array) $atts;
	if ( isset( $atts['label'] ) && ! isset( $atts['text'] ) ) {
		$atts['text'] = $atts['label'];
	}
	if ( isset( $atts['link'] ) && ! isset( $atts['url'] ) ) {
		$atts['url'] = $atts['link'];
	}
	$a  = shortcode_atts( array( 'text' => '', 'icon' => '1', 'class' => 'scholar-pdf', 'url' => '0', 'lang' => '' ), $atts, 'scholar_pdf' );
	$id = get_the_ID();
	if ( ! $id ) {
		return '';
	}
	$lang  = scm_clean_lang( $a['lang'] );
	$title = '';
	$bare  = '1' === (string) $a['url'];
	$text  = (string) $a['text'];

	if ( preg_match( '#^https?://#i', (string) $a['url'] ) ) {          // one-off link
		$link = scm_alias_url( $id, esc_url_raw( $a['url'] ) );
	} elseif ( $lang ) {                                                // stored translation
		$tr = scm_get_translation( $id, $lang );
		if ( ! $tr ) {
			return '';
		}
		$link  = $tr['pdf'] ? scm_alias_url( $id, $tr['pdf'] ) : $tr['page'];
		$lang  = $tr['lang'];
		$title = $tr['title'];
		if ( ! $tr['pdf'] ) {
			$a['icon'] = '0'; // links to a translated page, not a PDF
		}
		$text = '' !== $text ? $text : ( $tr['text'] ?: ( $tr['pdf'] ? sprintf( 'PDF (%s)', scm_lang_name( $lang ) ) : scm_lang_name( $lang ) ) );
	} else {                                                            // main PDF
		$link = scm_pdf_url( $id );
	}
	if ( ! $link ) {
		return '';
	}
	if ( $bare ) {
		return esc_url( $link );
	}
	$text = '' !== $text ? $text : 'Download PDF';
	$icon = '1' === (string) $a['icon'] ? '<i class="fas fa-file-pdf" aria-hidden="true"></i> ' : '';
	$attr = '';
	if ( $lang ) {
		$attr .= sprintf( ' hreflang="%1$s" lang="%1$s"', esc_attr( $lang ) );
	}
	if ( $title ) {
		$attr .= sprintf( ' title="%s"', esc_attr( $title ) );
	}
	return sprintf( '<a class="%s" href="%s"%s>%s<strong>%s</strong></a>', esc_attr( $a['class'] ), esc_url( $link ), $attr, $icon, esc_html( $text ) );
} );

/** [scholar_title] = original title; [scholar_title lang="fr"] = translated title. */
add_shortcode( 'scholar_title', function ( $atts ) {
	$a  = shortcode_atts( array( 'lang' => '' ), $atts, 'scholar_title' );
	$id = get_the_ID();
	if ( ! $id ) {
		return '';
	}
	$lang = scm_clean_lang( $a['lang'] );
	if ( $lang ) {
		$tr = scm_get_translation( $id, $lang );
		return ( $tr && $tr['title'] ) ? sprintf( '<span class="scholar-title" lang="%s">%s</span>', esc_attr( $tr['lang'] ), esc_html( $tr['title'] ) ) : '';
	}
	$d     = scm_get_data( $id );
	$title = ( $d && $d['title'] ) ? $d['title'] : get_the_title( $id );
	return '<span class="scholar-title">' . esc_html( $title ) . '</span>';
} );

/** [scholar_translations] lists every translation. Options: titles="0" icon="1" class="…" */
add_shortcode( 'scholar_translations', function ( $atts ) {
	$a  = shortcode_atts( array( 'titles' => '1', 'icon' => '1', 'class' => 'scholar-translations' ), $atts, 'scholar_translations' );
	$id = get_the_ID();
	$rows = $id ? scm_get_translations( $id ) : array();
	if ( ! $rows ) {
		return '';
	}
	$icon  = '1' === (string) $a['icon'] ? '<i class="fas fa-file-pdf" aria-hidden="true"></i> ' : '';
	$items = array();
	foreach ( $rows as $tr ) {
		$l     = esc_attr( $tr['lang'] );
		$parts = array();
		if ( '1' === (string) $a['titles'] && $tr['title'] ) {
			$parts[] = sprintf( '<cite lang="%s">%s</cite>', $l, esc_html( $tr['title'] ) );
		}
		$links = array();
		if ( $tr['pdf'] ) {
			$links[] = sprintf( '<a href="%1$s" hreflang="%2$s" lang="%2$s">%3$s<strong>%4$s</strong></a>', esc_url( scm_alias_url( $id, $tr['pdf'] ) ), $l, $icon, esc_html( $tr['text'] ?: sprintf( 'PDF (%s)', scm_lang_name( $tr['lang'] ) ) ) );
		}
		if ( $tr['page'] ) {
			$links[] = sprintf( '<a href="%1$s" hreflang="%2$s" lang="%2$s">%3$s</a>', esc_url( $tr['page'] ), $l, esc_html( scm_lang_name( $tr['lang'] ) ) );
		}
		$parts[] = implode( ' · ', $links );
		$items[] = sprintf( '<li lang="%s">%s</li>', $l, implode( ' — ', array_filter( $parts ) ) );
	}
	return sprintf( '<ul class="%s">%s</ul>', esc_attr( $a['class'] ), implode( '', $items ) );
} );

add_shortcode( 'scholar_cite', function () {
	$id = get_the_ID();
	if ( ! $id || ! scm_get_data( $id ) ) {
		return '';
	}
	$p = get_permalink( $id );
	return sprintf(
		'<span class="scholar-cite">Cite: <a href="%s" rel="nofollow">BibTeX</a> · <a href="%s" rel="nofollow">RIS</a></span>',
		esc_url( add_query_arg( 'scm_cite', 'bibtex', $p ) ),
		esc_url( add_query_arg( 'scm_cite', 'ris', $p ) )
	);
} );

/* -------------------------------------------------------------------------
 * Translations: [lang, title, pdf, text, page] rows stored per post
 * ---------------------------------------------------------------------- */

/** BCP 47-ish language tag ("fr", "pt-BR", "zh-Hant"), or ''. */
function scm_clean_lang( $lang ) {
	$lang = trim( str_replace( '_', '-', (string) $lang ) );
	return preg_match( '/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*$/', $lang ) ? $lang : '';
}

/** Language name in that language ("fr" -> "français"), for default link text. */
function scm_lang_name( $lang ) {
	if ( function_exists( 'locale_get_display_language' ) ) {
		$n = locale_get_display_language( $lang, $lang );
		if ( $n && strtolower( $n ) !== strtolower( $lang ) ) {
			return $n;
		}
	}
	$names = array(
		'ar' => 'العربية', 'de' => 'Deutsch', 'en' => 'English', 'es' => 'español', 'fa' => 'فارسی', 'fr' => 'français',
		'he' => 'עברית', 'hi' => 'हिन्दी', 'it' => 'italiano', 'ja' => '日本語', 'ko' => '한국어', 'nl' => 'Nederlands',
		'pl' => 'polski', 'pt' => 'português', 'ru' => 'русский', 'sv' => 'svenska', 'tr' => 'Türkçe', 'uk' => 'українська',
		'zh' => '中文', 'cs' => 'čeština', 'el' => 'Ελληνικά', 'id' => 'Bahasa Indonesia', 'vi' => 'Tiếng Việt',
	);
	return $names[ strtolower( strtok( $lang, '-' ) ) ] ?? $lang;
}

function scm_get_translations( $post_id ) {
	$rows = get_post_meta( $post_id, '_scm_translations', true );
	return is_array( $rows ) ? array_values( $rows ) : array();
}

/** Row for a language: exact tag first ("pt-BR"), then same base language ("pt"). */
function scm_get_translation( $post_id, $lang ) {
	$rows = scm_get_translations( $post_id );
	foreach ( $rows as $r ) {
		if ( 0 === strcasecmp( $r['lang'], $lang ) ) {
			return $r;
		}
	}
	$base = strtolower( strtok( $lang, '-' ) );
	foreach ( $rows as $r ) {
		if ( strtolower( strtok( $r['lang'], '-' ) ) === $base ) {
			return $r;
		}
	}
	return null;
}

function scm_save_translations( $post_id, $input ) {
	$rows = array();
	foreach ( (array) $input as $r ) {
		if ( ! is_array( $r ) ) {
			continue;
		}
		$row = array(
			'lang'  => scm_clean_lang( $r['lang'] ?? '' ),
			'title' => sanitize_text_field( $r['title'] ?? '' ),
			'pdf'   => esc_url_raw( trim( (string) ( $r['pdf'] ?? '' ) ) ),
			'text'  => sanitize_text_field( $r['text'] ?? '' ),
			'page'  => esc_url_raw( trim( (string) ( $r['page'] ?? '' ) ) ),
		);
		if ( $row['lang'] && ( $row['pdf'] || $row['page'] || $row['title'] ) ) {
			$rows[] = $row;
		}
	}
	if ( $rows ) {
		update_post_meta( $post_id, '_scm_translations', wp_slash( $rows ) );
	} else {
		delete_post_meta( $post_id, '_scm_translations' );
	}
}

function scm_render_translations( $post_id ) {
	$rows   = scm_get_translations( $post_id );
	$rows[] = array(); // one blank row to add a new translation
	$cell   = 'style="width:100%"';
	?>
	<div class="scm-translations" style="margin:12px 0">
		<p style="margin-bottom:4px"><strong>Translations (optional)</strong></p>
		<table class="widefat striped" id="scm-tr-table">
			<thead><tr>
				<th style="width:9%">Language</th><th style="width:27%">Translated title</th><th style="width:27%">PDF URL</th>
				<th style="width:14%">Link text</th><th style="width:20%">Translated page (optional)</th><th style="width:3%"></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $rows as $i => $r ) : ?>
				<tr>
					<td><input type="text" name="scm_tr[<?php echo (int) $i; ?>][lang]" value="<?php echo esc_attr( $r['lang'] ?? '' ); ?>" placeholder="fr" <?php echo $cell; // phpcs:ignore ?>></td>
					<td><input type="text" name="scm_tr[<?php echo (int) $i; ?>][title]" value="<?php echo esc_attr( $r['title'] ?? '' ); ?>" placeholder="Le titre traduit" <?php echo $cell; // phpcs:ignore ?>></td>
					<td><input type="url" name="scm_tr[<?php echo (int) $i; ?>][pdf]" value="<?php echo esc_attr( $r['pdf'] ?? '' ); ?>" placeholder="https://…/paper_fr.pdf" <?php echo $cell; // phpcs:ignore ?>></td>
					<td><input type="text" name="scm_tr[<?php echo (int) $i; ?>][text]" value="<?php echo esc_attr( $r['text'] ?? '' ); ?>" placeholder="en français" <?php echo $cell; // phpcs:ignore ?>></td>
					<td><input type="url" name="scm_tr[<?php echo (int) $i; ?>][page]" value="<?php echo esc_attr( $r['page'] ?? '' ); ?>" placeholder="https://…" <?php echo $cell; // phpcs:ignore ?>></td>
					<td><button type="button" class="button-link scm-tr-del" aria-label="Remove row" onclick="this.closest('tr').querySelectorAll('input').forEach(function(f){f.value='';});this.closest('tr').style.opacity=.4;">✕</button></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p style="margin-top:6px">
			<button type="button" class="button" onclick="(function(){var b=document.querySelector('#scm-tr-table tbody'),r=b.rows[b.rows.length-1].cloneNode(true),n=b.rows.length;r.style.opacity=1;r.querySelectorAll('input').forEach(function(f){f.value='';f.name=f.name.replace(/\[\d+\]/,'['+n+']');});b.appendChild(r);})();">Add translation</button>
			<span class="description">Language is a code like <code>fr</code>, <code>es</code> or <code>pt-BR</code>. PDFs in /<?php echo esc_html( SCM_PDF_DIR ); ?>/ or the Media Library get an alias under this post's URL, like the main PDF. Use <code>[scholar_pdf lang="fr"]</code>, <code>[scholar_title lang="fr"]</code> or <code>[scholar_translations]</code> on the page.</span>
		</p>
		<?php
		foreach ( scm_get_translations( $post_id ) as $tr ) {
			if ( $tr['pdf'] ) {
				$alias = scm_alias_url( $post_id, $tr['pdf'] );
				printf( '<p style="margin:2px 0;font-size:12px"><code>%s</code> PDF link: <a href="%s" target="_blank" rel="noopener">%s</a>%s</p>', esc_html( $tr['lang'] ), esc_url( $alias ), esc_html( $alias ), $alias === $tr['pdf'] ? ' <span style="color:#996800">(no alias: not directly in /' . esc_html( SCM_PDF_DIR ) . '/ or /uploads/YYYY/MM/)</span>' : '' );
			}
		}
		?>
	</div>
	<?php
}

// Translated pages (not PDFs) are announced as language alternates in <head>.
add_action( 'wp_head', function () {
	if ( ! is_singular( scm_post_types() ) ) {
		return;
	}
	foreach ( scm_get_translations( get_queried_object_id() ) as $tr ) {
		if ( $tr['page'] ) {
			printf( '<link rel="alternate" hreflang="%s" href="%s">' . "\n", esc_attr( $tr['lang'] ), esc_url( $tr['page'] ) );
		}
	}
}, 2 );

/* -------------------------------------------------------------------------
 * Updates from GitHub Releases (uses the "Update URI" header, WordPress 5.8+)
 * ---------------------------------------------------------------------- */

const SCM_GITHUB_REPO = 'dillonma/scholar-citation-meta';

/** Latest GitHub release as ['version','package','url','notes','date'], cached for 6 hours. */
function scm_latest_release( $force = false ) {
	$key = 'scm_gh_release';
	$rel = $force ? false : get_site_transient( $key );
	if ( false !== $rel ) {
		return $rel ?: null;
	}
	$r = wp_remote_get( 'https://api.github.com/repos/' . SCM_GITHUB_REPO . '/releases/latest', array(
		'timeout' => 10,
		'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url() ),
	) );
	$rel = array();
	if ( ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r ) ) {
		$j = json_decode( wp_remote_retrieve_body( $r ), true );
		foreach ( (array) ( $j['assets'] ?? array() ) as $a ) {
			if ( preg_match( '/^scholar-citation-meta(-[\d.]+)?\.zip$/', $a['name'] ?? '' ) ) {
				$rel = array(
					'version' => ltrim( (string) $j['tag_name'], 'vV' ),
					'package' => $a['browser_download_url'],
					'url'     => $j['html_url'],
					'notes'   => (string) ( $j['body'] ?? '' ),
					'date'    => (string) ( $j['published_at'] ?? '' ),
				);
				break;
			}
		}
	}
	if ( ! $rel ) {
		// API unavailable or rate-limited (60 requests/hour per server IP): follow the
		// github.com/…/releases/latest redirect instead and build the asset URL from the tag.
		$h   = wp_remote_head( 'https://github.com/' . SCM_GITHUB_REPO . '/releases/latest', array( 'timeout' => 10, 'redirection' => 0 ) );
		$loc = is_wp_error( $h ) ? '' : (string) wp_remote_retrieve_header( $h, 'location' );
		if ( preg_match( '~/releases/tag/(v?([\d.]+))$~', $loc, $m ) ) {
			$rel = array(
				'version' => $m[2],
				'package' => 'https://github.com/' . SCM_GITHUB_REPO . '/releases/download/' . $m[1] . '/scholar-citation-meta-' . $m[2] . '.zip',
				'url'     => $loc,
				'notes'   => 'See ' . $loc,
				'date'    => '',
			);
		}
	}
	set_site_transient( $key, $rel, $rel ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	return $rel ?: null;
}

add_filter( 'update_plugins_github.com', function ( $update, $plugin_data, $plugin_file ) {
	if ( plugin_basename( __FILE__ ) !== $plugin_file ) {
		return $update;
	}
	$rel = scm_latest_release();
	if ( ! $rel ) {
		return $update;
	}
	return array(
		'slug'    => dirname( $plugin_file ),
		'version' => $rel['version'],
		'url'     => $rel['url'],
		'package' => $rel['package'],
	);
}, 10, 3 );

// "View details" popup on the Plugins/Updates screens.
add_filter( 'plugins_api', function ( $res, $action, $args ) {
	if ( 'plugin_information' !== $action || dirname( plugin_basename( __FILE__ ) ) !== ( $args->slug ?? '' ) ) {
		return $res;
	}
	$rel = scm_latest_release();
	if ( ! $rel ) {
		return $res;
	}
	$data = get_file_data( __FILE__, array( 'Name' => 'Plugin Name', 'Description' => 'Description', 'Author' => 'Author', 'Requires' => 'Requires at least', 'RequiresPHP' => 'Requires PHP' ) );
	return (object) array(
		'name'          => $data['Name'],
		'slug'          => $args->slug,
		'version'       => $rel['version'],
		'author'        => $data['Author'],
		'homepage'      => 'https://github.com/' . SCM_GITHUB_REPO,
		'requires'      => $data['Requires'],
		'requires_php'  => $data['RequiresPHP'],
		'last_updated'  => $rel['date'],
		'download_link' => $rel['package'],
		'sections'      => array(
			'description' => esc_html( $data['Description'] ),
			'changelog'   => wpautop( esc_html( $rel['notes'] ) ),
		),
	);
}, 10, 3 );

// Forget the cached release after this plugin is updated.
add_action( 'upgrader_process_complete', function () {
	delete_site_transient( 'scm_gh_release' );
} );
