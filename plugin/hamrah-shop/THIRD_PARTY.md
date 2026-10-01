# Third-party notices

## WordPress / WooCommerce
Required runtimes, not redistributed inside this installation ZIP. Obtain their supported official versions from WordPress.org. The extension uses their native APIs, hooks, templates, gallery, checkout, order stores and CSV interfaces. No payment-provider SDK or credentials are bundled.

## Bundled theme
`resources/hamrah-shop-theme.zip` contains this project's `hamrah-shop` theme, GPL-3.0-or-later. Its complete PHP/JS/CSS/theme.json sources and licenses are inside the nested archive. It also contains locally served Vazirmatn font subsets; see the theme notices and OFL license.

## Persian WooCommerce translation fallback
Files: `languages/woocommerce-fa_IR.mo` and complete editable source `languages/woocommerce-fa_IR.po`.

Source: Persian WooCommerce, WordPress.org plugin mirror at https://github.com/common-repository/persian-woocommerce/tree/main/languages (downloaded from its main branch archive). The original package declares **GPLv3**. The PO retains the original translator, WooCommerce.ir team and revision metadata (2024-08-15). It is a legacy fallback, not represented as the latest official WooCommerce language pack. The official installed translation is preferred; the project's own supplemental interface dictionary only translates missing strings.

The GPLv3 translation license is retained independently of the project's GPL-3.0-or-later code. The GPLv3 license text is supplied in LICENSE. No code, gateway, importer, calendar or business data from the Persian WooCommerce plugin is installed — only the translation and source PO are redistributed.

## Icons and missing-image state
Project-authored inline SVG UI symbols and the neutral missing-image SVG. They are not photographs, product images or brand logos, and create no media/business records.

## Development-only dependencies
PHP WASM / WordPress Playground, SQLite Database Integration, Playwright/Chromium, PostCSS and testing tools were used outside production directories. None of their runtimes, temporary databases, test fixtures, demo content or credentials are shipped in the installation archive. Testing environment and limits are described in docs/گزارش-آزمون.html.
