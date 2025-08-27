=== ImgSEO – AI Bulk Image Alt Text Generator & SEO File Renamer for WordPress Accessibility ===

Contributors: pianoweb, jonathanwambua

Donate link: https://imgseo.net/

Tags: seo, image seo, alt text, accessibility, image renamer

Requires at least: 5.0

Tested up to: 6.8.1

Stable tag: 1.2.7

Requires PHP: 7.3

License: GPLv2 or later

License URI: http://www.gnu.org/licenses/gpl-2.0.html



Optimize your SEO & meet accessibility laws with AI-powered image alt text and intelligent file renaming - all in one powerful plugin.



== Description ==

**ImgSEO** transforms how you manage image accessibility and SEO in WordPress. Using advanced AI vision technology, it automatically generates:



* **Precise, SEO-optimized alt text** for every image

* **Search-friendly file names** that replace generic camera names (DSC_0001.jpg → descriptive-image-name.jpg)

* **Contextually relevant descriptions** by analyzing both visual content AND page context



With the European Accessibility Act (EAA) becoming mandatory on **June 28, 2025**, and similar regulations worldwide, proper image alt text is no longer optional. ImgSEO helps you stay compliant while simultaneously boosting your search rankings.



= Key Benefits =



* **Double Your SEO Impact**: Properly described images rank better in both regular and image search results

* **Ensure Legal Compliance**: Meet WCAG 2.1 AA, EAA, ADA, Section 508 and other global accessibility standards

* **Save Hours of Manual Work**: Process your entire media library with one click

* **Improve User Experience**: Help screen readers accurately describe images to visually impaired visitors

* **Enhance E-commerce Performance**: Better product image descriptions improve conversion rates



= Features in Detail =



* **Intelligent Context Analysis**: Uses page title, content, and existing filename for relevant descriptions

* **Real-time Generation**: Automatic alt text creation as you upload new images

* **Bulk Optimization**: Fix hundreds of legacy images with a single click

* **Individual Image Processing**: Generate or update alt text for specific images directly from the media library

* **Complete Metadata Update**: Automatically populate title, description, alt text, and caption fields from one AI generation

* **Field Selection Control**: Choose which metadata fields to update (title, alt text, caption, description) for each optimization

* **Custom AI Prompts**: Inject brand keywords and control the output style

* **Smart File Renaming**: Transform generic filenames into SEO powerhouses with automatic reference updates

* **Multilingual Support**: Generate alt text in 25+ languages without extra plugins

* **Customizable Character Limits**: Set maximum length for generated alt text (recommended ~125 characters)

* **Timeout Settings**: Control response times for alt text generation requests

* **Cloud Dashboard**: Track usage, manage tokens, and export detailed reports [dashboard.imgseo.net/login](https://dashboard.imgseo.net/login)

* **Team Collaboration**: Multiple user accounts and API token management

* **JSON-LD Structured Data**: Automatic Schema.org markup generation for enhanced SEO

* **Browser Extension**: Access ImgSEO features outside of WordPress (available separately)

* **Developer-Friendly**: Extensive hooks for customized integration



= Central Cloud Dashboard =



At [dashboard.imgseo.net](https://dashboard.imgseo.net) you can:



* Monitor available credits and usage statistics

* Purchase one-time credit packs or subscribe to a plan

* Manage API tokens for multiple sites or environments

* Review and export the complete history of generated alt texts

* Add team members with controlled access



*Register free at* [dashboard.imgseo.net/register](https://dashboard.imgseo.net/register) – get **30 credits** instantly **+ 10 new credits every day** whenever your balance drops below 10.



= Accessibility & Legal Compliance =



ImgSEO helps address:



* **WCAG 2.1 / ISO 40500** – Success Criterion 1.1.1 *Non-text Content*

* **European Accessibility Act (Directive 2019/882)** – Mandatory from **June 28, 2025**

* **ADA Title II (USA 2024 DOJ Final Rule)** & **Section 508 Refresh**

* **AODA & Accessible Canada Act**, **UK PSBAR 2018**, **BITV 2.0 (DE)**, **RGAA 4.1 (FR)**

* **JIS X 8341-3 (JP)**, **GB/T 37668-2019 (CN)**, **e-MAG 3.0 (BR)**



> *Best practice built-in:* ImgSEO limits alt text to ~125 characters, avoids redundant phrases like "image of...", and lets you mark decorative images appropriately.



= Pricing =

| Pack | Credits | Price | Cost/credit |

|------|---------|-------|-------------|

| Pro | 1 000 | € 9.90 | € 0.0099 |

| Elite | 5 000 | € 39.90 | € 0.0080 |

| Ultra | 20 000 | € 99.00 | € 0.0050 |

| Unlimited | 200 000 | € 499.90 | € 0.0025 |



*Free tier:* 30 starter credits + daily refill up to 10. One-time credit packs available – see [imgseo.net/#prices](https://imgseo.net/#prices).



== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/imgseo` or install via *Plugins → Add New*

2. Activate **ImgSEO**

3. Register at [dashboard.imgseo.net/register](https://dashboard.imgseo.net/register) and copy your API key

4. In *Settings → ImgSEO*, paste the key, choose your language and options

5. Upload a new image or run the bulk optimizer – done!



== Frequently Asked Questions ==



= Do I need an API key? =

Yes. Registration is free and supplies 30 initial credits.



= Which languages are supported? =

More than 25, including English, Italian, Spanish, German, French, Portuguese, Japanese and Arabic.



= Can I track what the AI generated? =

Yes – the dashboard stores a per-image log with search and export.



= How are credits consumed? =

1 credit = 1 alt text **or** 1 filename.



= How does the daily refill work? =

If your balance is below 10 at **00:00 server time**, we top you back up to 10.



= Can I exclude decorative images? =

NEXT UPGRADE: Sure – will mark decorative images or will set `alt=""` and ImgSEO will skip them.



= Is AI output editable? =

Always. You can tweak or overwrite the generated alt text in WordPress.



= How does the AI determine what's in the image? =

ImgSEO uses advanced computer vision to identify objects, scenes, people, and actions in images. It combines this visual analysis with the surrounding page context (title, content) to create more relevant descriptions.



= Will this plugin slow down my site? =

No. The AI processing happens on our servers, not yours. The generated alt text and filenames are stored in your WordPress database just like regular metadata.



= Can I use ImgSEO with WooCommerce? =

Absolutely! ImgSEO works exceptionally well with e-commerce sites, where proper product image descriptions can significantly impact conversion rates and SEO.



= Does ImgSEO work with page builders? =

Yes, ImgSEO is compatible (this upgrade still in beta) with major page builders including Elementor, Divi, WPBakery, and Gutenberg.



== Changelog ==

= 1.2.7 =
* **SECURITY**: Fixed potential API key exposure in error logs
* **SECURITY**: Added path traversal protection - File operations now validate paths are within upload directory
* **SECURITY**: Enhanced AJAX input validation - Added API key format validation and malicious pattern detection
* **SECURITY**: Implemented rate limiting - Maximum 5 API verification attempts per 10 minutes per user
* **SECURITY**: Added attachment ID validation helper - Ensures proper permission checks for all file operations
* **SECURITY**: Strengthened sitemap file generation - Additional path validation before writing sitemap files
* **Improved**: Enhanced security logging - Path traversal attempts are now logged without exposing sensitive data

= 1.2.6 =
* **NEW**: Redesigned Image Sitemap Management System - Replaced single "Generate Sitemap" button with intuitive ACTIVATE and REFRESH buttons for better user control
* **NEW**: Automatic Sitemap Updates - Added scheduled auto-refresh functionality with configurable intervals (hourly, daily, weekly)
* **Enhanced UX**: Smart notification system alerts users when sitemap needs updating after new image additions
* **Improved Performance**: Static sitemap generation with automatic permalink rule updates eliminates 4xx errors and reduces server load
* **Better Control**: Manual refresh capability combined with intelligent auto-updates ensures sitemaps stay current without constant manual intervention
* **Streamlined Interface**: Cleaner admin interface with status indicators and auto-refresh settings for optimal sitemap management

= 1.2.5 =
* **FIXED**: Resolved PHP 8.3 deprecation warnings - Added proper property declarations to IMGSEO_Init class
* **PHP 8.3 Compatibility**: Eliminated "Creation of dynamic property" deprecation notices
* **Improved Code Quality**: Enhanced class structure with explicit property declarations
* **Cleaner Logs**: No more PHP deprecation warnings filling up debug logs
* **Modern PHP Support**: Full compatibility with latest PHP versions and best practices

= 1.2.4 =
* **FIXED**: Resolved widget content loss issue during modifications - Removed interfering hook that caused widget data reset
* **FIXED**: Eliminated debug log spam in console - Implemented centralized debug control system (IMGSEO_DEBUG_MODE) to drastically reduce log messages
* **Improved Stability**: Widgets can now be modified without losing saved content
* **Clean Console**: No more excessive debug messages in logs (only activatable when needed)
* **Widget Compatibility**: Enhanced compatibility with all WordPress widget types
* **Performance**: Reduced system log load by eliminating unnecessary debug messages

= 1.2.3 =
* **NEW**: Implemented robust fallback mechanism - Automatically detects 403 Forbidden errors (hotlinking protection) and 5xx server errors (including 520 Cloudflare) and uses alternative method with base64 and WordPress thumbnails
* **NEW**: Added option to always force base64 method usage - Complete bypass of anti-hotlinking protections and Cloudflare blocks
* **Performance Optimization**: Uses WordPress thumbnails (large → medium_large → medium) instead of original images to reduce transmitted data size
* **Improved Compatibility**: Now works with sites implementing anti-hotlinking security measures or when remote servers have temporary issues
* **Enhanced Reliability**: Resilient system that ensures alt text generation even with connection errors or temporary server-side problems
* **User Control**: Option to always choose base64 method for situations where image access problems occur

= 1.2.2 =

* **NEW**: Added dedicated AI prompt for WooCommerce product images - Enhanced e-commerce optimization with specialized prompts that generate more accurate and conversion-focused alt text for product images
* **Enhanced WooCommerce Integration**: Improved product image recognition and context-aware descriptions for better SEO and accessibility compliance
* **E-commerce Optimization**: Specialized AI prompts now consider product attributes, categories, and commercial context for more effective product image descriptions

= 1.2.1 =

* **MAJOR FIX**: Resolved homepage image detection - now generates JSON-LD for ALL images on homepage (not just 2)
* **Enhanced Image Scanning**: New universal scanner detects images from posts, widgets, themes, and external sources
* **Improved Statistics**: Accurate JSON-LD statistics with clear quality metrics (complete vs partial data)
* **Simplified Admin Interface**: Streamlined structured data settings page for better user experience
* **Bug Fix**: Corrected PHP syntax error in universal scanner class
* **Performance**: Optimized scanning system with intelligent caching and conditional execution
* **Better Coverage**: Now detects images from page builders, CDN, FTP uploads, and CSS backgrounds

= 1.2.0 =

* Added JSON-LD structured data generation for images
* Enhanced SEO with automatic Schema.org ImageObject markup
* New admin settings for structured data configuration

= 1.1.9 =

* Added Image Sitemap Generation.


= 1.1.8 =

* Minor changes.


= 1.1.7 =

* Minor changes.

* Added checkbox for adding a complianz badge for alternative texts.



= 1.1.6 =

* Bulk mode upgraded.

* Improved compatibility with older MySQL versions.



= 1.1.5 =

* Minor changes.



= 1.1.4 =

* Minor bug fixes.



= 1.1.3 =

* Minor bug fixes.



= 1.1.2 =

* Minor bug fixes.



= 1.1.1 =

* Minor bug fixes.



= 1.1.0 =

* Major update – New API and many fixes.



= 1.0.9 =

* AI engine enabled for renamer.

* Added numerous options for renaming.

* Added renamer support for major builder plugins.

* Updated missing English strings.



= 1.0.8 =

* Added English as main language.



= 1.0.7 =

* Added support for major languages.

* UX improvements in bulk actions section.



= 1.0.6 =

* 10 free credits daily refill when balance < 10.



= 1.0.5 =

* New plugin structure and improvements.

* Enhanced image renamer with proper thumbnail handling.

* Added restore functionality.

* Fixed 404 prevention and detailed logging.

* Added custom prompt field.



= 1.0.4 =

* Bug fixes.



= 1.0.2 =

* Enhancing usability.



= 1.0.1 =

* Improved Max Characters option, Page Title option, and alt text column display.

* Optionally set image title, caption and description with generated alt text.



= 1.0.0 =

* Initial release with alt text generation and AI image renaming.



== Upgrade Notice ==

= 1.1.6 =

Recommended for all users – fixes bulk mode and MySQL compatibility.



== External Services ==

This plugin connects to **ImgSEO AI API** to analyse the submitted image (URL or binary) and produce alt text or a suggested filename. No personal data beyond the image itself is transmitted. Full terms: [https://imgseo.net/terms-of-service/](https://imgseo.net/terms-of-service/)



== Accessibility Statement ==

Our goal is to make ImgSEO usable by everyone. The plugin's admin screens follow WordPress core accessibility guidelines, and we test each release with screen readers and keyboard navigation. Please report issues via our support forum so we can improve further.
