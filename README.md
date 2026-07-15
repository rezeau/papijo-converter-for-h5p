# Papijo Package Converter for H5P

WordPress admin tool for converting supported default H5P export packages into their matching Papi Jo H5P equivalents.

**Official download and updates:** [WordPress.org Plugin Directory](https://wordpress.org/plugins/papijo-converter-for-h5p/)

The plugin adds a Tools > Papijo Package Converter for H5P screen. It scans the standard WordPress H5P exports folder, lists supported source packages, and downloads converted packages as a ZIP archive. Original `.h5p` files are not changed.

This independent plugin is not affiliated with or endorsed by the H5P project.

## Requirements

- WordPress 5.8 or later
- PHP 8.0 or later
- PHP `ZipArchive` extension
- Administrator access
- H5P export packages in `wp-content/uploads/h5p/exports`

## Supported Source Types

- Complex fill the blanks
- Dialog Cards
- Drag and Drop
- Drag the Words
- Mark the Words
- Multimedia Choice
- Question Set
- Timeline (`H5P.Timeline` → `H5P.NDLATimelinePapiJo 0.2`)

## Installation

1. In WordPress admin, go to Plugins > Add New Plugin.
2. Search for `Papijo Package Converter for H5P` and click Install Now.
3. Activate Papijo Package Converter for H5P.
4. Go to Tools > Papijo Package Converter for H5P.

## Repository Layout

- `papijo-converter-for-h5p/` - WordPress plugin source folder.
- `assets/` - WordPress.org plugin icons.

## Notes

GitHub is the development repository. Stable releases are published through the [WordPress.org Plugin Directory](https://wordpress.org/plugins/papijo-converter-for-h5p/).

## License

GPLv2 or later.
