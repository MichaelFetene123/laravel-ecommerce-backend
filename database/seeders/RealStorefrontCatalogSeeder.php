<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RealStorefrontCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = $this->seedCategories();
        $attributes = $this->seedAttributes();
        $this->seedProducts($categories, $attributes);
    }

    /**
     * Seed or update categories for Beauty, Personal Care, and Women's Essentials.
     *
     * @return array<string, Category>
     */
    protected function seedCategories(): array
    {
        $categories = [
            'Skincare & Cosmetics' => [
                'slug' => 'skincare-cosmetics',
                'description' => 'Botanical serums, hydrating moisturizers, gentle cleansers, and radiant makeup.',
                'image_path' => 'https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?w=800&auto=format&fit=crop&q=80',
            ],
            'Perfumes & Fragrances' => [
                'slug' => 'perfumes-fragrances',
                'description' => 'Artisanal personal fragrances, rare oud extraits, and signature floral mists.',
                'image_path' => 'https://images.unsplash.com/photo-1592945403244-b3fbafd7f539?w=800&auto=format&fit=crop&q=80',
            ],
            'Bras & Intimates' => [
                'slug' => 'bras-intimates',
                'description' => 'Cloud-soft wireless bras, lace plunge bralettes, and seamless everyday undergarments.',
                'image_path' => 'https://images.unsplash.com/photo-1516762689617-e1cffcef479d?w=800&auto=format&fit=crop&q=80',
            ],
            'Body Care & Lotions' => [
                'slug' => 'body-care',
                'description' => 'Whipped body butters, botanical body oils, and restorative moisturizing lotions.',
                'image_path' => 'https://images.unsplash.com/photo-1608248543803-ba4f8c70ae0b?w=800&auto=format&fit=crop&q=80',
            ],
        ];

        $map = [];
        $sort = 1;
        foreach ($categories as $name => $data) {
            $cat = Category::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $name,
                    'description' => $data['description'],
                    'image_path' => $data['image_path'],
                    'is_active' => true,
                    'sort_order' => $sort++,
                ]
            );
            $map[$name] = $cat;
        }

        // Subcategories
        $subcategories = [
            'Skincare & Cosmetics' => [
                'Serums & Treatments' => 'serums-treatments',
                'Moisturizers & Creams' => 'moisturizers-creams',
                'Cleansers & Toners' => 'cleansers-toners',
                'Lip & Face Glow' => 'lip-face-glow',
            ],
            'Perfumes & Fragrances' => [
                'Eau de Parfum' => 'eau-de-parfum',
                'Oud & Extraits' => 'oud-extraits',
                'Body & Hair Mists' => 'body-hair-mists',
            ],
            'Bras & Intimates' => [
                'Wireless Comfort Bras' => 'wireless-comfort-bras',
                'Lace & Plunge Bralettes' => 'lace-plunge-bralettes',
                'Seamless Underwear' => 'seamless-underwear',
            ],
            'Body Care & Lotions' => [
                'Whipped Body Creams' => 'whipped-body-creams',
                'Botanical Body Oils' => 'botanical-body-oils',
                'Exfoliating Scrubs' => 'exfoliating-scrubs',
            ],
        ];

        foreach ($subcategories as $parentName => $subs) {
            $parent = $map[$parentName];
            $subSort = 1;
            foreach ($subs as $subName => $subSlug) {
                $subCat = Category::updateOrCreate(
                    ['slug' => $subSlug],
                    [
                        'parent_id' => $parent->id,
                        'name' => $subName,
                        'description' => "Explore {$subName} essentials.",
                        'is_active' => true,
                        'sort_order' => $subSort++,
                    ]
                );
                $map[$subName] = $subCat;
            }
        }

        return $map;
    }

    /**
     * Seed attributes and values for beauty, personal care, and intimate apparel.
     *
     * @return array<string, array<string, AttributeValue>>
     */
    protected function seedAttributes(): array
    {
        $defs = [
            'Color' => [
                'Blush Rose', 'Nude / Beige', 'Midnight Black', 'Ivory White',
                'Champagne Gold', 'Warm Amber', 'Dusty Mauve', 'Terracotta',
            ],
            'Size' => [
                '30ml', '50ml', '100ml', '150ml', '200ml', '250g',
                '32B', '34B', '34C', '36B', '36C', 'S', 'M', 'L', 'XL',
            ],
        ];

        $attributes = [];
        foreach ($defs as $attrName => $values) {
            $attr = Attribute::updateOrCreate(
                ['slug' => Str::slug($attrName)],
                ['name' => $attrName]
            );

            $attributes[$attrName] = [];
            foreach ($values as $val) {
                $valSlug = Str::slug($val);
                $attrVal = AttributeValue::updateOrCreate(
                    [
                        'attribute_id' => $attr->id,
                        'slug' => $valSlug,
                    ],
                    [
                        'value' => $val,
                    ]
                );
                $attributes[$attrName][$val] = $attrVal;
            }
        }

        return $attributes;
    }

    /**
     * Seed real beauty, personal care, fragrance, and bra products.
     *
     * @param  array<string, Category>  $categories
     * @param  array<string, array<string, AttributeValue>>  $attributes
     */
    protected function seedProducts(array $categories, array $attributes): void
    {
        $products = [
            // 1. Skincare
            [
                'title' => 'Radiance Vitamin C + Niacinamide Serum',
                'slug' => 'radiance-vitamin-c-serum',
                'category_name' => 'Skincare & Cosmetics',
                'description' => 'A potent, lightweight daily antioxidant serum formulated with 15% active L-Ascorbic Acid, fermented green tea, and 4% Niacinamide. Designed to brighten hyperpigmentation, smooth fine texture, and defend against urban pollution.',
                'meta_title' => 'Radiance Vitamin C Serum | Clean Botanical Skincare',
                'meta_description' => '15% Active Vitamin C serum with niacinamide for radiant, even-toned skin.',
                'images' => [
                    'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?w=800&auto=format&fit=crop&q=80',
                    'https://images.unsplash.com/photo-1608248543803-ba4f8c70ae0b?w=800&auto=format&fit=crop&q=80',
                ],
                'variants' => [
                    [
                        'sku' => 'SKIN-VTC-30ML',
                        'price' => 1850.00,
                        'compare_at_price' => 2200.00,
                        'stock_quantity' => 28,
                        'weight' => 0.15,
                        'is_default' => true,
                        'color' => 'Champagne Gold',
                        'size' => '30ml',
                    ],
                    [
                        'sku' => 'SKIN-VTC-50ML',
                        'price' => 2650.00,
                        'compare_at_price' => 2950.00,
                        'stock_quantity' => 15,
                        'weight' => 0.22,
                        'is_default' => false,
                        'color' => 'Champagne Gold',
                        'size' => '50ml',
                    ],
                ],
            ],
            [
                'title' => 'Hyaluronic Dew Plumping Face Cream',
                'slug' => 'hyaluronic-dew-plumping-cream',
                'category_name' => 'Skincare & Cosmetics',
                'description' => 'A deeply comforting cloud cream packed with 5 molecular weights of hyaluronic acid, vegan squalane, and oat ceramides. Provides 72 hours of weightless dewy hydration without clogging pores.',
                'meta_title' => 'Hyaluronic Dew Plumping Face Cream | Barrier Repair',
                'meta_description' => 'Multi-molecular hyaluronic acid and ceramide face cream for supple hydration.',
                'images' => [
                    'https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?w=800&auto=format&fit=crop&q=80',
                ],
                'variants' => [
                    [
                        'sku' => 'SKIN-HYA-50ML',
                        'price' => 1650.00,
                        'compare_at_price' => 1900.00,
                        'stock_quantity' => 24,
                        'weight' => 0.20,
                        'is_default' => true,
                        'color' => 'Ivory White',
                        'size' => '50ml',
                    ],
                ],
            ],
            [
                'title' => 'Rosewater & Camellia Gentle Cleanser',
                'slug' => 'rosewater-camellia-gentle-cleanser',
                'category_name' => 'Skincare & Cosmetics',
                'description' => 'A gentle, low-pH gel-to-milk cleanser infused with steam-distilled Damask rosewater and Japanese camellia oil. Effortlessly dissolves waterproof makeup while preserving the acid mantle.',
                'meta_title' => 'Rosewater & Camellia Gentle Cleanser | Clean Face Wash',
                'meta_description' => 'Hydrating low-pH rosewater cleanser for sensitive, delicate skin.',
                'images' => [
                    'https://images.unsplash.com/photo-1556228720-195a672e8a03?w=800&auto=format&fit=crop&q=80',
                ],
                'variants' => [
                    [
                        'sku' => 'SKIN-CLN-150ML',
                        'price' => 1250.00,
                        'compare_at_price' => 1450.00,
                        'stock_quantity' => 32,
                        'weight' => 0.25,
                        'is_default' => true,
                        'color' => 'Blush Rose',
                        'size' => '150ml',
                    ],
                ],
            ],

            // 2. Perfumes & Fragrances
            [
                'title' => 'Velvet Rose & Smoked Amber Eau de Parfum',
                'slug' => 'velvet-rose-smoked-amber-edp',
                'category_name' => 'Perfumes & Fragrances',
                'description' => 'An opulent floral-oriental sillage opening with fresh lychee and bergamot, melting into Bulgarian velvet rose absolute, tonka bean, and smoked golden amber.',
                'meta_title' => 'Velvet Rose & Smoked Amber Eau de Parfum | Niche Perfume',
                'meta_description' => 'Luxurious long-lasting eau de parfum with Bulgarian rose and smoked amber.',
                'images' => [
                    'https://images.unsplash.com/photo-1547887537-6158d64c35b3?w=800&auto=format&fit=crop&q=80',
                    'https://images.unsplash.com/photo-1592945403244-b3fbafd7f539?w=800&auto=format&fit=crop&q=80',
                ],
                'variants' => [
                    [
                        'sku' => 'FRAG-VR-50ML',
                        'price' => 3400.00,
                        'compare_at_price' => 3850.00,
                        'stock_quantity' => 16,
                        'weight' => 0.28,
                        'is_default' => true,
                        'color' => 'Warm Amber',
                        'size' => '50ml',
                    ],
                    [
                        'sku' => 'FRAG-VR-100ML',
                        'price' => 4850.00,
                        'compare_at_price' => 5400.00,
                        'stock_quantity' => 10,
                        'weight' => 0.45,
                        'is_default' => false,
                        'color' => 'Warm Amber',
                        'size' => '100ml',
                    ],
                ],
            ],
            [
                'title' => 'Saffron & Oud Noir Extrait de Parfum',
                'slug' => 'saffron-oud-noir-extrait',
                'category_name' => 'Perfumes & Fragrances',
                'description' => 'A masterwork of artisanal perfumery featuring pure Persian red saffron, aged Cambodian oud, dark patchouli, and smoky labdanum. Unrivaled 24-hour projection.',
                'meta_title' => 'Saffron & Oud Noir Extrait de Parfum | Rare Scent',
                'meta_description' => 'High-concentration extrait de parfum blending Persian saffron and smoked oud.',
                'images' => [
                    'https://images.unsplash.com/photo-1594035910387-fea47794261f?w=800&auto=format&fit=crop&q=80',
                ],
                'variants' => [
                    [
                        'sku' => 'FRAG-OUD-50ML',
                        'price' => 4200.00,
                        'compare_at_price' => 4800.00,
                        'stock_quantity' => 12,
                        'weight' => 0.32,
                        'is_default' => true,
                        'color' => 'Midnight Black',
                        'size' => '50ml',
                    ],
                ],
            ],
            [
                'title' => 'Jasmine Blossom & Citrus Hair & Body Mist',
                'slug' => 'jasmine-blossom-citrus-mist',
                'category_name' => 'Perfumes & Fragrances',
                'description' => 'An alcohol-free hydrating mist for body and hair, combining night-blooming white jasmine, sparkling Sicilian blood orange, and aloe vera hydration.',
                'meta_title' => 'Jasmine Blossom & Citrus Mist | Hydrating Fragrance',
                'meta_description' => 'Alcohol-free refreshing body and hair fragrance mist with jasmine and blood orange.',
                'images' => [
                    'https://images.unsplash.com/photo-1592945403244-b3fbafd7f539?w=800&auto=format&fit=crop&q=80',
                ],
                'variants' => [
                    [
                        'sku' => 'FRAG-JAS-100ML',
                        'price' => 1750.00,
                        'compare_at_price' => 2100.00,
                        'stock_quantity' => 25,
                        'weight' => 0.20,
                        'is_default' => true,
                        'color' => 'Champagne Gold',
                        'size' => '100ml',
                    ],
                ],
            ],

            // 3. Bras & Women's Intimate Essentials
            [
                'title' => 'Seamless Cloud-Touch Wireless Bra',
                'slug' => 'seamless-cloud-touch-wireless-bra',
                'category_name' => 'Bras & Intimates',
                'description' => 'Engineered with ultra-fine microfiber and bonded jelly support ribs that lift and sculpt without underwires. Breathable molded cups disappear under thin t-shirts and silk shirts.',
                'meta_title' => 'Seamless Cloud-Touch Wireless Bra | Daily Comfort Intimates',
                'meta_description' => 'Second-skin wireless comfort bra with molded cups and invisible bonded edges.',
                'images' => [
                    'https://images.unsplash.com/photo-1583846783214-7229a91b20ed?w=800&auto=format&fit=crop&q=80',
                    'https://images.unsplash.com/photo-1516762689617-e1cffcef479d?w=800&auto=format&fit=crop&q=80',
                ],
                'variants' => [
                    [
                        'sku' => 'BRA-CLD-NDE-34B',
                        'price' => 1450.00,
                        'compare_at_price' => 1750.00,
                        'stock_quantity' => 20,
                        'weight' => 0.12,
                        'is_default' => true,
                        'color' => 'Nude / Beige',
                        'size' => '34B',
                    ],
                    [
                        'sku' => 'BRA-CLD-NDE-36C',
                        'price' => 1450.00,
                        'compare_at_price' => 1750.00,
                        'stock_quantity' => 18,
                        'weight' => 0.12,
                        'is_default' => false,
                        'color' => 'Nude / Beige',
                        'size' => '36C',
                    ],
                    [
                        'sku' => 'BRA-CLD-BLK-34B',
                        'price' => 1450.00,
                        'compare_at_price' => 1750.00,
                        'stock_quantity' => 15,
                        'weight' => 0.12,
                        'is_default' => false,
                        'color' => 'Midnight Black',
                        'size' => '34B',
                    ],
                ],
            ],
            [
                'title' => 'Silk Touch French Lace Plunge Bralette',
                'slug' => 'silk-touch-french-lace-bralette',
                'category_name' => 'Bras & Intimates',
                'description' => 'Intricate French eyelash lace layered over 100% pure mulberry silk lining. Plunging neckline with delicate gold hardware and a flexible underbust elastic band for romantic all-day ease.',
                'meta_title' => 'Silk Touch French Lace Plunge Bralette | Luxury Lingerie',
                'meta_description' => 'Handcrafted French lace bralette with silk lining and plunge neckline.',
                'images' => [
                    'https://images.unsplash.com/photo-1618354691373-d851c5c3a990?w=800&auto=format&fit=crop&q=80',
                ],
                'variants' => [
                    [
                        'sku' => 'BRA-LCE-BLK-M',
                        'price' => 1680.00,
                        'compare_at_price' => 1950.00,
                        'stock_quantity' => 14,
                        'weight' => 0.08,
                        'is_default' => true,
                        'color' => 'Midnight Black',
                        'size' => 'M',
                    ],
                    [
                        'sku' => 'BRA-LCE-ROSE-S',
                        'price' => 1680.00,
                        'compare_at_price' => 1950.00,
                        'stock_quantity' => 12,
                        'weight' => 0.08,
                        'is_default' => false,
                        'color' => 'Blush Rose',
                        'size' => 'S',
                    ],
                ],
            ],
            [
                'title' => 'Ergonomic Support Wirefree T-Shirt Bra',
                'slug' => 'ergonomic-support-wirefree-tshirt-bra',
                'category_name' => 'Bras & Intimates',
                'description' => 'Full-coverage everyday bra tailored with moisture-wicking modal fabric, wide cushioned straps that never dig into shoulders, and smooth side-smoothing panels.',
                'meta_title' => 'Ergonomic Support Wirefree T-Shirt Bra | Everyday Essentials',
                'meta_description' => 'Full-coverage wirefree t-shirt bra with cushioned straps and modal fabric.',
                'images' => [
                    'https://images.unsplash.com/photo-1516762689617-e1cffcef479d?w=800&auto=format&fit=crop&q=80',
                ],
                'variants' => [
                    [
                        'sku' => 'BRA-TSH-NDE-36B',
                        'price' => 1350.00,
                        'compare_at_price' => 1600.00,
                        'stock_quantity' => 22,
                        'weight' => 0.14,
                        'is_default' => true,
                        'color' => 'Nude / Beige',
                        'size' => '36B',
                    ],
                ],
            ],

            // 4. Body Care & Lotions
            [
                'title' => 'Whipped Shea & Vanilla Cashmere Body Butter',
                'slug' => 'whipped-shea-vanilla-body-butter',
                'category_name' => 'Body Care & Lotions',
                'description' => 'Hand-whipped raw Ethiopian and Ghanaian shea butter blended with cold-pressed jojoba, sweet almond oil, and Bourbon vanilla. Melts on contact to restore velvety softness to dry elbows, legs, and hands.',
                'meta_title' => 'Whipped Shea & Vanilla Body Butter | Deep Moisture Crème',
                'meta_description' => 'Decadent whipped body crème with organic shea butter and Madagascar vanilla.',
                'images' => [
                    'https://images.unsplash.com/photo-1556228720-195a672e8a03?w=800&auto=format&fit=crop&q=80',
                ],
                'variants' => [
                    [
                        'sku' => 'BODY-SHEA-250G',
                        'price' => 980.00,
                        'compare_at_price' => 1200.00,
                        'stock_quantity' => 45,
                        'weight' => 0.35,
                        'is_default' => true,
                        'color' => 'Ivory White',
                        'size' => '250g',
                    ],
                ],
            ],
            [
                'title' => 'Botanical Radiance Restorative Body Oil',
                'slug' => 'botanical-radiance-body-oil',
                'category_name' => 'Body Care & Lotions',
                'description' => 'A fast-absorbing, dry-touch body elixir formulated with cold-pressed rosehip, Kalahari melon seed, and golden jojoba oils. Infuses skin with a satin luminosity and subtle hints of neroli blossom.',
                'meta_title' => 'Botanical Radiance Restorative Body Oil | Satin Glow Elixir',
                'meta_description' => 'Illuminating dry body oil with rosehip and marula for radiant skin.',
                'images' => [
                    'https://images.unsplash.com/photo-1608248543803-ba4f8c70ae0b?w=800&auto=format&fit=crop&q=80',
                ],
                'variants' => [
                    [
                        'sku' => 'BODY-OIL-150ML',
                        'price' => 1250.00,
                        'compare_at_price' => 1500.00,
                        'stock_quantity' => 30,
                        'weight' => 0.24,
                        'is_default' => true,
                        'color' => 'Champagne Gold',
                        'size' => '150ml',
                    ],
                ],
            ],
            [
                'title' => 'Crushed Coffee & Cocoa Polishing Body Scrub',
                'slug' => 'crushed-coffee-cocoa-body-scrub',
                'category_name' => 'Body Care & Lotions',
                'description' => 'Freshly roasted Sidama coffee beans, organic brown sugar, and raw cocoa butter work synergistically to buff away dull skin cells, stimulate circulation, and leave skin irresistibly silky.',
                'meta_title' => 'Crushed Coffee & Cocoa Body Scrub | Natural Exfoliator',
                'meta_description' => 'Natural exfoliating body polish with Ethiopian coffee and raw cocoa butter.',
                'images' => [
                    'https://images.unsplash.com/photo-1571781926291-c477ebfd024b?w=800&auto=format&fit=crop&q=80',
                ],
                'variants' => [
                    [
                        'sku' => 'BODY-SCRUB-250G',
                        'price' => 890.00,
                        'compare_at_price' => 1100.00,
                        'stock_quantity' => 35,
                        'weight' => 0.35,
                        'is_default' => true,
                        'color' => 'Terracotta',
                        'size' => '250g',
                    ],
                ],
            ],
        ];

        foreach ($products as $data) {
            $cat = $categories[$data['category_name']];

            /** @var Product $product */
            $product = Product::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'category_id' => $cat->id,
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'is_active' => true,
                    'meta_title' => $data['meta_title'],
                    'meta_description' => $data['meta_description'],
                ]
            );

            // Images
            foreach ($data['images'] as $sortIdx => $imgUrl) {
                ProductImage::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'path' => $imgUrl,
                    ],
                    [
                        'sort_order' => $sortIdx + 1,
                    ]
                );
            }

            // Variants
            foreach ($data['variants'] as $vData) {
                $variant = ProductVariant::updateOrCreate(
                    ['sku' => $vData['sku']],
                    [
                        'product_id' => $product->id,
                        'price' => $vData['price'],
                        'compare_at_price' => $vData['compare_at_price'] ?? null,
                        'stock_quantity' => $vData['stock_quantity'],
                        'weight' => $vData['weight'],
                        'is_default' => $vData['is_default'],
                    ]
                );

                // Attach Color attribute
                if (!empty($vData['color']) && isset($attributes['Color'][$vData['color']])) {
                    $attrVal = $attributes['Color'][$vData['color']];
                    $variant->attributeValues()->syncWithoutDetaching([$attrVal->id]);
                }

                // Attach Size attribute
                if (!empty($vData['size']) && isset($attributes['Size'][$vData['size']])) {
                    $attrVal = $attributes['Size'][$vData['size']];
                    $variant->attributeValues()->syncWithoutDetaching([$attrVal->id]);
                }
            }
        }
    }
}
