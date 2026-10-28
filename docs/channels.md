# Channel hints: sources

The editor's "Suggested" list for a channel profile is a static list of the fields that channel commonly requires or recommends for every listing. Nothing is added to a rule set without a click, and every row a merchant adds starts with an empty attribute for them to map.

Each list was written from the channel's own current documentation, read on the date given. Channels change their specifications; when a list and a channel disagree, the channel is right and the hint is a starting point. Category-specific requirements (Amazon product type attributes, eBay item specifics, TikTok category attributes, Walmart product type specs) are out of scope: add them as rules yourself once you know which categories you list in.

Only the channels' own documentation was used. No merchant, client or store examples appear in these lists.

## Google Merchant Center

- Product data specification, https://support.google.com/merchants/answer/7052112, read 2026-10-22.
- Used: id, title (max 150), description (max 5,000), link, image_link (and up to 10 additional_image_link), availability (in_stock, out_of_stock, preorder, backorder), price (not 0), brand (max 70, required for new products except movies, books and music), gtin (UPC 12, EAN 13, JAN 8 or 13, ISBN 10 or 13, ITF-14 14; digits only), mpn (max 70, required without a GTIN), condition (new, refurbished, used), google_product_category, product_type, shipping_weight.
- Not hinted: shipping, tax, loyalty and the apparel attributes (color, size, gender, age_group, item_group_id), which depend on country and category.

## Microsoft Merchant Center

- Content API products resource (the product fields of a Microsoft Merchant Center offer), https://learn.microsoft.com/en-us/advertising/shopping-content/products-resource, read 2026-10-22. The help-centre feed specification it links to was not reachable without a session on the day; the API reference lists the same fields with their limits.
- Used: title (max 150, required), description (max 10,000), imageLink (required; bmp, gif, exif, jpg, png, tiff; max 3.9 MB), availability (in stock, out of stock, preorder; required), price (0.01 to 10,000,000.00; required), brand (required; keep to 70 characters), gtin (required when assigned), mpn (max 70; required when assigned), condition (new, refurbished, used; required), googleProductCategory (max 255), productType (max 750), shippingWeight (required for Germany only).

## Meta (Facebook and Instagram Shops)

- Commerce catalog fields reference, https://developers.facebook.com/docs/commerce-platform/catalog/fields, read 2026-10-22.
- Used: id (max 100), title (max 200; 65 recommended), description (max 9,999, plain text), availability (in stock, out of stock), condition (new, refurbished, used), price, link, image_link (at least 500 x 500 px; up to 50 additional), google_product_category or fb_product_category, brand (max 100; one of brand, gtin or mpn required), gtin (UPC 12, EAN/JAN 13 or 8, ISBN-13, ITF-14), mpn (max 100), shipping_weight (lb, oz, g, kg).
- Not hinted: item_group_id, color, size, gender, age_group, material, pattern (variant and apparel fields).

## Pinterest

- Catalogs data source specification, https://help.pinterest.com/en/business/article/data-source-specification, read 2026-10-22.
- Used: id (max 127), title (max 500), description (max 10,000), link, image_link (at least 1000 x 1500 px; up to 10 additional), price (ISO currency; not zero), availability (in stock, out of stock, preorder), brand (max 100), gtin, mpn (max 70), google_product_category, condition (new, used, refurbished), shipping_weight.
- Not hinted: item_group_id, color, size, gender, age_group.

## TikTok Shop

- Product Listing Policy, https://seller-us.tiktok.com/university/essay?knowledge_id=3196690250417921, read 2026-10-22: brand name mandatory; description no less than 30 words with 3 to 5 bullet points recommended; up to 9 square images, at least 600 x 600 px, main image on a pure white background; most accurate category required.
- Product Detail Pages and Listing Quality Guidelines, https://seller-us.tiktok.com/university/essay?knowledge_id=481891871868714, read 2026-10-22: title 25 to 255 characters; description more than 80 characters or an image; five or more images for the higher quality tiers; resolution above 600 x 600 px.
- Optimizing Product Titles, https://seller-us.tiktok.com/university/essay?knowledge_id=1664446336747310, read 2026-10-22: 40 to 150 characters recommended; no discount claims, superlatives, variant lists or stock counts.
- Product Listing: What You Need to Know, https://seller-us.tiktok.com/university/essay?knowledge_id=7073362639816491, read 2026-10-22: titles 25 to 200 characters; at least 5 images; descriptions at least 500 characters; brand chosen or "No brand".
- The hints take the floors: title at least 25, description at least 500, 5 images. The region pages differ in places; check the Seller Center of your region.

## Amazon

- Seller Central announcement "New product title requirements effective January 21, 2025" (posted by Amazon), https://sellercentral.amazon.com/seller-forums/discussions/t/b2b15728-0d43-453e-974f-59eb63f73059, read 2026-10-22: titles at most 200 characters including spaces; the same word at most twice; the characters !, $, ?, _, {, }, ^, ¬ and ¦ not allowed unless part of the brand name.
- Product Type Definitions API (the per-product-type schema of required attributes), https://developer-docs.amazon.com/sp-api/docs/product-type-definitions-api-v2020-09-01-model, read 2026-10-22: attributes and their conditionality are defined per product type and store.
- The Product Detail Page Rules and the product image requirements pages in Seller Central require a seller session and could not be read on the day; the brand, product id, image, bullet point and description hints follow the attributes the product type definitions name for every product type (item_name, brand, externally assigned product identifier, main product image, bullet points, product description) and the title announcement. Verify the exact rules of your product types in Seller Central.

## eBay

- Picture policy, https://www.ebay.com/help/policies/listing-policies/picture-policy?id=4370, read 2026-10-22: at least one photo of 500 pixels or more on the longest side; no borders, added text, watermarks or placeholder images.
- Adding pictures to your listings, https://www.ebay.com/help/selling/listings/adding-pictures-listings?id=4148, read 2026-10-22: minimum 500 x 500 px, about 1600 x 1600 recommended, up to 24 pictures, 12 MB each.
- How to create a listing (eBay Export), https://export.ebay.com/en/first-steps/how-to-create-listing/, read 2026-10-22: title up to 80 characters; condition such as New, Used or Refurbished; required item specifics depend on the category; digital identifiers (GTIN, UPC, EAN, ISBN, MPN) highly recommended.
- Product identifiers (eBay Export), https://export.ebay.com/en/manage-listings/product-identifiers/, read 2026-10-22: product identifiers are required in most categories; "Does not apply" when a product has none.
- Item conditions by category, https://www.ebay.com/help/selling/listings/creating-managing-listings/item-conditions-category?id=4765, read 2026-10-22: the condition options differ by category; the hint lists the three generic values.

## Walmart Marketplace

- Item setup tips (Marketplace Learn), https://marketplacelearn.walmart.com/ca/guides/Item%20setup/Item%20setup/item-setup-tips, read 2026-10-22: required to add an item: SKU, Product Name, Product Type ID, Product ID, Description, Product Tax Code, Brand, Main Image URL, Shipping Weight; product name as Brand + Defining Qualities + Item Name + Pack Count; "Unbranded" when there is no brand; UPC 12, GTIN 14, EAN 13, ISBN 10 or 13 digits with check digit; main image 500 x 500 px minimum (2000 x 2000 at 300 ppi for zoom), JPEG preferred, 2 MB maximum.
- Item setup feed structure overview (developer documentation), https://developer.walmart.com/global-marketplace/docs/post-item-setup-by-product-type-feed-file-structure-overview, read 2026-10-22: the Orderable object holds the attributes required to create an offer (SKU, Product ID, pricing, inventory); the number of required secondary images varies by product type.
- Item setup schema key points and Get item setup requirements (developer documentation), https://developer.walmart.com/us-marketplace/docs/item-setup-schema-key-points and https://developer.walmart.com/us-marketplace/docs/get-item-setup-requirements, read 2026-10-22: the required attributes, allowed values and character limits of each product type come from that product type's JSON schema (required, minLength, maxLength).
- The Product detail page image guidelines page could not be read without a session on the day; the image hint uses the sizes from the item setup tips.
