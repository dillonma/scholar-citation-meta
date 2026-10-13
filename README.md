# Scholar Citation Meta

Google Scholar and Zotero citation meta tags for paper posts, generated from a DOI (Crossref, DataCite, doi.org) or BibTeX, with Scholar-friendly PDF links.

[![Latest release](https://img.shields.io/github/v/release/dillonma/scholar-citation-meta)](https://github.com/dillonma/scholar-citation-meta/releases/latest) ![WordPress 5.9+](https://img.shields.io/badge/WordPress-5.9%2B-blue) ![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4) ![License GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-green)

## What it does
For each paper post, enter a DOI (and optionally a PDF URL or BibTeX). It then:

- Fetches title, authors, date, journal, volume, issue, pages, ISSN, publisher and DOI when you save the post. It tries Crossref first, then DataCite's API, then `doi.org` for any other registration agency. The box shows which one supplied the data and flags missing fields.
- Outputs Highwire `citation_*` meta tags in the page `<head>`. Google Scholar and the Zotero Connector both read these.
- Gives each PDF an alias under the post's URL and uses the alias in `citation_pdf_url`, because Google Scholar wants the PDF in the same folder as the abstract page:
  - `/articles/file.pdf` → `/papers/<slug>/file.pdf`
  - `/wp-content/uploads/YYYY/MM/file.pdf` → `/papers/<slug>/YYYY/MM/file.pdf`
  The alias rules are added to WordPress's own `.htaccess` block. PDFs in subfolders of `/articles/` are used as-is, without an alias.
- Serves BibTeX and RIS downloads at `?scm_cite=bibtex` / `?scm_cite=ris`. Put `[scholar_cite]` in a post to show the links to readers.

## Install
1. Download `scholar-citation-meta-<version>.zip` from the [latest release](https://github.com/dillonma/scholar-citation-meta/releases/latest). Download the release asset, not GitHub's "Source code" archive.
2. In WordPress, go to Plugins → Add New → Upload Plugin, choose the zip, and activate. If an older copy is installed, choose **Replace current with uploaded**.
3. Go to Settings → Permalinks and click **Save**. This writes the PDF alias rules to `.htaccess`.
4. Edit a paper post. In the **Scholar citation metadata** box, enter the DOI and the PDF's URL, then click Update.
5. Reload the editor. The box shows the PDF link to use on the page (with a Copy button) and the exact tags that will be output.

## Items without a DOI
Paste a BibTeX entry instead. Any field in the BibTeX overrides the Crossref value, which is useful for fixing a bad Crossref record. Supported entry types: article, incollection/inbook, inproceedings, book, techreport, thesis.

## Settings (optional, in wp-config.php)
    define( 'SCM_POST_TYPES', 'post' );   // comma-separated post types
    define( 'SCM_PDF_PREFIX', 'papers' ); // first URL segment of paper posts
    define( 'SCM_PDF_ALIAS', true );      // false = no alias rules
    define( 'SCM_PDF_DIR', 'articles' );  // non-Media-Library PDF folder ('' = none)

## Shortcodes
| Shortcode | Result |
|---|---|
| `[scholar_pdf]` | PDF icon + bold **Download PDF** link to the alias |
| `[scholar_pdf text="Read the PDF"]` | Same link with your own wording |
| `[scholar_pdf icon="0"]` | Link without the PDF icon |
| `[scholar_pdf class="button"]` | Adds your own CSS class (default `scholar-pdf`) |
| `[scholar_pdf url="1"]` | Just the bare URL |
| `[scholar_cite]` | "Cite: BibTeX · RIS" download links |

## If the alias URL doesn't load
The plugin box shows whether the alias rule is in `.htaccess`.
- **Rule found:** Apache serves the PDF directly.
- **Rule missing:** WordPress serves the PDF itself (v1.3+), so the alias still works. To use the faster Apache rule, go to Settings → Permalinks and click Save. If WordPress says `.htaccess` isn't writable, copy the rules it shows into the file.
- **Still a 404:** check that the file name matches exactly. Linux servers are case-sensitive, so `.PDF` is not the same as `.pdf`.

## DOIs Crossref can't find (DataCite, e.g. ACME)
Some journals register their DOIs with DataCite, not Crossref. When Crossref has no record, the plugin asks DataCite's API directly, but DataCite records are often thin. ACME's DOIs, for example, have no page numbers and put "Vol. 21 No. 4 (2022): ..." in the page field. The plugin pulls the volume and issue out of that text. To fill in anything still missing, add a short BibTeX entry with just those fields, e.g.:

    @article{x, pages = {327--336}, issn = {1492-9732}}

Use the same entry type as the item (@article for journal articles). Any fields you leave out still come from the DOI lookup.

To check which agency registered a DOI, open `https://doi.org/ra/<DOI>`.

## Updates
Version 1.7.0 and later update from this repository's Releases. When a new release is published, WordPress shows it on the Plugins and Updates screens, and you update with one click (or let auto-updates handle it). WordPress checks GitHub at most every 6 hours.

Versions before 1.7.0 don't have the updater, so upload 1.7.0 once by hand.

## Releasing a new version (maintainers)
1. Bump the version in the plugin header (`Version:`) and in `readme.txt` (`Stable tag:`), and add a changelog entry to `readme.txt`.
2. Commit and push, then create a release whose tag is `v<version>`, e.g. `gh release create v1.2.3 --generate-notes`.
3. The **Release** workflow checks that the tag matches both version numbers, builds `scholar-citation-meta-<version>.zip`, and attaches it to the release. Sites running the plugin will then see the update.

## Changelog
- **1.7.1**: Update checks fall back to github.com when GitHub's API is rate-limited (common on shared hosting).
- **1.7.0**: Updates from GitHub Releases: new versions appear on the Plugins screen like any other plugin.
- **1.6.0**: The DOI field accepts publisher URLs (e.g. Taylor & Francis chapter links), drops title slugs after the DOI, and stores the DOI that actually resolved.
- **1.5.0**: Checks the DataCite API directly when Crossref has no record, before falling back to doi.org.
- **1.4.0**: doi.org fallback; cleanup of page fields that are not page numbers; missing-field hints.
- **1.3.0**: PHP fallback for PDF aliases; .htaccess status line; shortcode reference table.
- **1.2.0**: Alias link with copy button; [scholar_pdf] shortcode.
- **1.1.0**: Aliases for PDFs stored in /articles/.
- **1.0.0**: First release.

## License
GPL-2.0-or-later. See [LICENSE](LICENSE).
