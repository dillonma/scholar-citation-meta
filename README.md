# Scholar Citation Meta

Google Scholar and Zotero citation tags for a page about a single paper, chapter or book, generated from a DOI (Crossref, DataCite, doi.org) or BibTeX, with Scholar-friendly PDF links.

[![Latest release](https://img.shields.io/github/v/release/dillonma/scholar-citation-meta)](https://github.com/dillonma/scholar-citation-meta/releases/latest) ![WordPress 5.9+](https://img.shields.io/badge/WordPress-5.9%2B-blue) ![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4) ![License GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-green)

## What it does
**Scholar Citation Meta is for a page about a single work.** Give a post a DOI, and the plugin adds the citation tags Google Scholar and Zotero need to recognize the page as that paper, chapter or book. It also gives the PDF a link Google Scholar will accept.

For pages that **list many works**, such as a publications page, use the companion plugin **[Scholar Citation Meta Scanner](https://github.com/dillonma/scholar-citation-meta-scanner)**. It works on its own or together with this one.

| | [Scholar Citation Meta](https://github.com/dillonma/scholar-citation-meta) | [Scholar Citation Meta Scanner](https://github.com/dillonma/scholar-citation-meta-scanner) |
|---|---|---|
| **Use it for** | A page about **one** work: a paper, chapter or book | A page that **lists many** works: a publications list, syllabus, reading list or bibliography |
| **What it adds** | Highwire `citation_*` meta tags for that one work | Hidden COinS tags for every DOI found on the page |
| **Google Scholar** | Tells Scholar the page is that work and where your PDF is | Not used by Scholar |
| **Zotero browser button** | Saves the one work | Shows a folder icon with a pick-list of every work on the page |
| **Where the details come from** | The DOI you enter (Crossref, DataCite, doi.org), plus optional BibTeX | Each DOI on the page, looked up the same way |
| **Turned on** | Per post, by entering a DOI | Per post or page, with a checkbox (off by default) |

### Examples
- **One paper (Meta):** [The urban-tech feedback loop](https://dillonm.io/papers/the-urban-tech-feedback-loop/), a journal article in *Digital Geography & Society*. Google Scholar has what it needs to index the page as that article, and Zotero saves it as a journal article.
- **One book chapter (Meta):** [Land for AI](https://dillonm.io/papers/land-for-ai/), a chapter in *The Inner World of Artificial Intelligence* (CRC Press). Zotero saves it as a book section with the book title and publisher.
- **A list of works (Scanner):** [Papers + Writings](https://dillonm.io/papers/), a full publications list. Zotero offers every listed work with a DOI as a pick-list.
- **Both together:** [Doing Critical GIS](https://dillonm.io/papers/doing-critical-gis/), a special-issue introduction with the issue's other articles listed below it. Meta describes the introduction itself for Google Scholar. The Scanner gives Zotero a pick-list with the introduction first, followed by the other articles.

### Using both
The two plugins are built to run side by side. On a page with both:
- Google Scholar still reads Meta's tags, so the page is still described as its own work.
- Zotero prefers the Scanner's list, and the page's own work is put first in it (you can turn this off per page).
- If a DOI on a list page belongs to one of your own Meta posts, the Scanner uses that post's details, including any BibTeX fixes such as missing page numbers.

### Features
For each paper post, enter a DOI (and optionally a PDF URL or BibTeX). The plugin then:

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
| `[scholar_pdf lang="fr"]` | PDF icon + bold link to the French PDF, using that row's link text (e.g. **en français**). Hovering shows the translated title |
| `[scholar_title]` / `[scholar_title lang="fr"]` | The original / translated title as plain text: no icon, link or bold, styled like the surrounding text. Shows nothing if there's no such translation |
| `[scholar_translations]` | A list of every translation with its title and link (`titles="0"` for links only) |
| `[scholar_pdf url="https://…" text="en français" lang="fr"]` | A one-off link that isn't stored in the box |

## Translations
To offer a translated version of a work, fill in a row in the **Translations** table in the Scholar citation metadata box:

| Field | Example | Notes |
|---|---|---|
| Language | `fr` | A language code: `fr`, `es`, `de`, `pt-BR`, … |
| Translated title | La machine à rente foncière | Shown by `[scholar_title lang="fr"]` and `[scholar_translations]`, and as the link's tooltip |
| PDF URL | `https://dillonm.io/articles/grm_fr.pdf` | Gets an alias under the post's URL, like the main PDF |
| Link text | en français | Text of the `[scholar_pdf lang="fr"]` link. Defaults to "PDF (français)" |
| Translated page | `https://dillonm.io/fr/…/` | Optional. A separate page for the translation. Announced to search engines as a `hreflang` alternate |

Click **Add translation** for more languages. To remove a row, click ✕ and update the post.

Then put the links on the page:

    [scholar_pdf] · [scholar_pdf lang="fr" icon="0"]

which shows **Download PDF** · **en français**. Or list every translation with `[scholar_translations]`.

### What each shortcode shows
With the example row above:

| You type | The page shows |
|---|---|
| `[scholar_pdf]` | (PDF icon) **Download PDF**, linking to the original PDF |
| `[scholar_pdf lang="fr"]` | (PDF icon) **en français**, linking to the French PDF. Hovering shows "La machine à rente foncière" |
| `[scholar_pdf lang="fr" icon="0"]` | **en français**, without the icon |
| `Also available in French: [scholar_title lang="fr"]` | Also available in French: La machine à rente foncière |
| `[scholar_title lang="fr"] — [scholar_pdf lang="fr"]` | La machine à rente foncière — (PDF icon) **en français** |
| `[scholar_translations]` | A bulleted list with one line per language: the title, then its link, e.g. *La machine à rente foncière* — (PDF icon) **en français** |
| `[scholar_translations titles="0"]` | The same list with links only |

`[scholar_title]` is wrapped in `<span class="scholar-title" lang="fr">`, so screen readers pronounce it in the right language and you can style it with CSS. In `[scholar_translations]`, titles are in `<cite>` (usually italic) and the list has the class `scholar-translations`.

Links are marked with the right language (`lang` and `hreflang`), which helps screen readers and search engines. The Google Scholar tags still point only to the original PDF, because Scholar expects one main PDF per page. Scholar may find a translated PDF through its alias and list it as another version. To have a translation indexed as a separate work, give it its own post with its own Scholar Citation Meta details and put that post's address in **Translated page**.

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
- **1.8.0**: Translations: a Translations table in the editor box (language, translated title, PDF, link text, optional translated page), new `[scholar_pdf lang="fr"]`, `[scholar_title]` and `[scholar_translations]` shortcodes, PDF aliases for translated PDFs, and `hreflang` alternate links for translated pages. `[scholar_pdf]` also accepts `label`/`link` and a one-off `url="https://…"`.
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
