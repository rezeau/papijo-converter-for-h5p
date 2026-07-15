=== Papijo Package Converter for H5P ===
Contributors: papijo
Tags: h5p, export, conversion, admin, content
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 2.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Convert supported H5P packages into their matching Papi Jo content types. Use it from Tools > Papijo Package Converter for H5P.

== Description ==

Papijo Package Converter for H5P adds a Tools > Papijo Package Converter for H5P screen in WordPress admin. It scans the standard H5P exports folder, lists only supported default/source packages, and converts selected packages into their matching Papi Jo equivalents.

The original source .h5p files are never changed.

This independent plugin is not affiliated with or endorsed by the H5P project.

Source folder:

* wp-content/uploads/h5p/exports

Supported source H5P content types:

* Complex fill the blanks
* Dialog Cards
* Drag and Drop
* Drag the Words
* Mark the Words
* Multimedia Choice
* Question Set
* Timeline

Converted outputs:

* AdvancedBlanks Papi Jo
* DialogCards Papi Jo
* DragQuestion Papi Jo
* DragText Papi Jo
* MarkTheWords Papi Jo
* MultiMediaChoice Papi Jo
* QuestionSet Papi Jo
* NDLA Timeline Papi Jo

== Installation ==

1. Upload the papijo-converter-for-h5p folder to wp-content/plugins.
2. Activate Papijo Package Converter for H5P in WordPress admin.
3. Go to Tools > Papijo Package Converter for H5P.

== Requirements ==

* WordPress with H5P export packages in wp-content/uploads/h5p/exports.
* PHP ZipArchive extension.
* Administrator access.

== Notes ==

Converted packages are always downloaded as one ZIP archive. Temporary conversion files are cleaned up after each request.

== Changelog ==

= 2.3.0 =
Add conversion support for Timeline packages to NDLA Timeline Papi Jo 0.2.
Rename the plugin to Papijo Package Converter for H5P for clearer trademark attribution.

= 2.2.1 =
Hide the conversion progress indicator when the generated ZIP download is ready.

= 2.2.0 =
Remove the server-folder output option. Converted files are now always delivered as a ZIP archive.

= 2.1.1 =
Clarify that the server-folder output is an archive location with download links, not a folder that H5P upload dialogs can browse directly.

= 2.1.0 =
Add cleaner converted filenames, a lightweight conversion progress indicator, and a choice between download and server-folder output.

= 2.0.0 =
Merge H5P extraction and Papi Jo conversion into one WordPress admin workflow.

= 1.3.0 =
Clarify that the exporter downloads original/default H5P source packages only, not Papi Jo packages.

= 1.2.0 =
Only list and export H5P packages that match the supported source content type list.

= 1.1.0 =
Use wp-content/uploads/h5p/exports as the source for available .h5p packages.

= 1.0.0 =
Initial release.
