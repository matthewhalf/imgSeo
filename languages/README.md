# ImgSEO Plugin Translation Files

This directory contains translation files for the ImgSEO plugin.

## Available Translations

- English (en_US)

## How to Compile Translation Files

To use the translations, you need to compile the .po files into .mo files. There are several ways to do this:

### Option 1: Using Poedit (Recommended)

1. Download and install [Poedit](https://poedit.net/)
2. Open the .po file you want to compile
3. Click on "Save" or "Save As" - Poedit will automatically generate the .mo file

### Option 2: Using msgfmt (Command Line)

If you have gettext installed on your system:

```bash
msgfmt -o languages/imgseo-en_US.mo languages/imgseo-en_US.po
```

### Option 3: Using WordPress Development Environment

If you have a WordPress development environment, you can use the provided compile-mo.php script:

```bash
cd /path/to/wordpress/wp-content/plugins/imgseo
php languages/compile-mo.php
```

## Creating New Translations

1. Copy the `imgseo.pot` file to a new file named `imgseo-[locale].po` (e.g., `imgseo-fr_FR.po` for French)
2. Open the file in Poedit or a text editor
3. Translate all the strings
4. Compile the .po file to .mo using one of the methods above
5. Place both files in the languages directory

## Testing Translations

To test if your translations are working:

1. Make sure both .po and .mo files are in the languages directory
2. Set your WordPress site language to the language you've translated to
3. Visit the ImgSEO plugin settings pages to see the translations in action

## Contributing Translations

If you've created a translation for the ImgSEO plugin, consider sharing it with the community. Please contact the plugin author to have your translation included in the official release.
