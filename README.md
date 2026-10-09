# Title Page Plugin

This plugin creates a title page on PDF files submitted to preprint servers. The title page is a page added to the beginning of the PDF file, containing a series of information about the preprint when it is posted.

After the preprint is posted, the title page is updated if the preprint relations are changed. The updating also takes place when the preprint is unposted and posted back again.

This BIREME customization generates a single A4 portrait (210 × 297 mm) title page with the LILACS Preprint identity: two institutional logos, version, title, optional subtitle, ordered authors (one per line for 1–5 authors; a centered, comma-separated block for 6 or more), linked DOI when available, peer-review and institutional notices, and submission/posting dates. Editorial text is available in Portuguese, Spanish and English. Relation status, journal DOI, endorsements, translation citation and version justification remain in OPS but are not printed.

The cover uses TCPDF's bundled DejaVu Sans Unicode font (regular, bold and italic), instead of the reference document's Arial/Calibri. The retained checklist generator uses Open Sans. Long metadata uses progressively smaller spacing and fonts, down to 18 pt for titles, 11 pt for subtitles and 9 pt for authors/body text. If the complete metadata still cannot fit on one page, generation reports an error and preserves the original PDF; it never truncates metadata or adds another cover page.

The manuscript header identifies LILACS Preprint without a DOI. An absent DOI appears on the cover as localized text without a hyperlink. The submission checklist page is currently not added: a new PDF contains the cover plus the original manuscript. Updates replace only the first page, preserving any final checklist already present in legacy PDFs.

## Compatibility

The latest release of this plugin is compatible with the following PKP applications:

* OPS 3.5.0

## Plugin Download

To download the plugin, go to the [Releases page](https://github.com/lepidus/titlePageForPreprint/releases) and download the tar.gz package of the latest release compatible with your website.

## Installation dependencies 
* [poppler-utils](https://poppler.freedesktop.org/)

This plugin requires the installation of the CPDF binary in your system. You can download it at the [GitHub repository](https://github.com/coherentgraphics/cpdf-binaries). After that, you should make it executable from the command line. In Linux systems it can be done by placing the binary in the `/usr/local/bin` directory and running `chmod +x /usr/local/bin/cpdf`.

Institutional logos are bundled in `resources/`; the title page does not depend on the general OPS website logo. `lilacs-logo.jpg` is a byte-for-byte copy of the official JPEG. `lilacs-preprint-logo.png` is derived from the official 1945 × 809 RGBA PNG by compositing onto white and saving as RGB without alpha or tRNS transparency. The reference originals remain unchanged. `reference/` is development material only and is not needed at runtime. Images with transparency must not be substituted into this renderer.

TCPDF is installed through the existing Composer dependency; CPDF remains required for stamping, merging and replacing the first page. The posting/update hooks are unchanged.

## Development dependencies
* [poppler-utils](https://poppler.freedesktop.org/)
* [php-imagick](https://www.php.net/manual/pt_BR/imagick.compareimages.php) - needed for unit tests.
* [phpunit](https://phpunit.de/) - use the version and `lib/pkp/tests/phpunit.xml` configuration supplied by your OPS checkout (the `ApplicationPlugins` suite). Tests require an isolated development installation, not a production database.

## Installation

1. Enter the administration area of ​​your OPS website through the __Dashboard__. In case OPS raise the file size error, check out the variables on ´php.ini´ file: `upload_max_filesize` and `post_max_size` wich the values must be at least 17M.
2. Navigate to `Settings`>` Website`> `Plugins`> `Upload a new plugin`.
3. Under __Upload file__ select the file __titlePageForPreprint.tar.gz__.
4. Click __Save__ and the plugin will be installed on your website.

## Installation for development
1. Install the development dependencies.
2. Clone the [repository](https://github.com/lepidus/titlePageForPreprint)
3. Switch branch, if needed.
4. Run `composer install` inside the repository.
5. Modify the file: `/etc/ImageMagick-6/policy.xml` , to allow read/write permissions to PDF, changing this specific line: `<policy domain=“coder” rights="none" pattern=“PDF” />` 
to this:            `<policy domain=“coder” rights=“read|write” pattern=“PDF” />`
6. Run `npm install` inside the repository to install module for the automated acceptance test.

# License

Since this plugin uses the CPDF library, make sure to check [its license](https://github.com/coherentgraphics/cpdf-binaries/blob/master/LICENSE) in order to know if your organization can use it.

__This plugin is licensed under the GNU General Public License v3.0__

__Copyright (c) 2020-2026 Lepidus Tecnologia__

__Copyright (c) 2020-2026 SciELO__