=== Scholar Citation Meta ===
Contributors: dillonma
Tags: google scholar, citation, doi, zotero, highwire
Requires at least: 5.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.7.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Google Scholar and Zotero citation meta tags for paper posts, generated from a DOI (Crossref, DataCite, doi.org) or BibTeX, with Scholar-friendly PDF links.

== Description ==

Use this plugin for a page about a single work (a paper, chapter or book). For pages that list many works, use the companion plugin Scholar Citation Meta Scanner (https://github.com/dillonma/scholar-citation-meta-scanner); the two work together.

Examples:

* One paper: https://dillonm.io/papers/the-urban-tech-feedback-loop/
* One book chapter: https://dillonm.io/papers/land-for-ai/
* With the Scanner on the same page: https://dillonm.io/papers/doing-critical-gis/

Google Scholar and Zotero citation meta tags for paper posts, generated from a DOI (Crossref, DataCite, doi.org) or BibTeX, with Scholar-friendly PDF links.

Full documentation: https://github.com/dillonma/scholar-citation-meta

== Installation ==

1. Download the latest `scholar-citation-meta-<version>.zip` from https://github.com/dillonma/scholar-citation-meta/releases/latest
2. Plugins → Add New → Upload Plugin, choose the zip, and activate.
3. Settings → Permalinks → Save (writes the PDF alias rules).

Later versions update from GitHub Releases through the normal WordPress Updates screen.

== Changelog ==

= 1.7.1 =
* Update checks fall back to github.com when GitHub's API is rate-limited (common on shared hosting).

= 1.7.0 =
* Updates from GitHub Releases: new versions appear on the Plugins screen like any other plugin.

= 1.6.0 =
* The DOI field accepts publisher URLs (e.g. Taylor & Francis chapter links), drops title slugs after the DOI, and stores the DOI that actually resolved.

= 1.5.0 =
* Checks the DataCite API directly when Crossref has no record, before falling back to doi.org.

= 1.4.0 =
* doi.org fallback; cleanup of page fields that are not page numbers; missing-field hints.

= 1.3.0 =
* PHP fallback for PDF aliases; .htaccess status line; shortcode reference table.

= 1.2.0 =
* Alias link with copy button; [scholar_pdf] shortcode.

= 1.1.0 =
* Aliases for PDFs stored in /articles/.

= 1.0.0 =
* First release.
