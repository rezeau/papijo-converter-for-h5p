# Papijo H5P Converter

WordPress admin tool for converting supported default H5P export packages into their matching Papi Jo H5P equivalents.

The plugin adds a Tools > Papijo H5P Converter screen. It scans the standard WordPress H5P exports folder, lists supported source packages, and downloads converted packages as a ZIP archive. Original `.h5p` files are not changed.

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

## Installation

1. Download `papijo-h5p-converter.zip` from the latest GitHub release.
2. Install the ZIP from WordPress admin: Plugins > Add New > Upload Plugin.
3. Activate Papijo H5P Converter.
4. Go to Tools > Papijo H5P Converter.

## Repository Layout

- `papijo-h5p-converter/` - WordPress plugin source folder.
- `papijo-h5p-converter.zip` - installable plugin package.

## Notes

The GitHub repository name contains `wp`, but the WordPress plugin slug is `papijo-h5p-converter` because WordPress.org does not allow `wp` in plugin slugs.

## License

GPLv2 or later.
