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
     * Seed or update categories to align with Storefront.
     *
     * @return array<string, Category>
     */
    protected function seedCategories(): array
    {
        $categories = [
            'Fashion' => [
                'slug' => 'fashion',
                'description' => 'Discover the latest trends and timeless classics.',
                'image_path' => 'categories/fashion-apparel.svg',
            ],
            'Electronics' => [
                'slug' => 'electronics',
                'description' => 'Precision engineered hardware and studio accessories.',
                'image_path' => 'categories/electronics.svg',
            ],
            'Home' => [
                'slug' => 'home',
                'description' => 'Thoughtful additions crafted for modern workspaces and homes.',
                'image_path' => 'categories/home-living.svg',
            ],
            'Sports' => [
                'slug' => 'sports',
                'description' => 'Performance-driven equipment and lifestyle goods.',
                'image_path' => 'categories/footwear.svg',
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

        // Also ensure subcategories exist under them if needed
        $subcategories = [
            'Fashion' => [
                "Men's Clothing" => 'mens-clothing',
                "Women's Clothing" => 'womens-clothing',
                'Footwear' => 'footwear',
            ],
            'Electronics' => [
                'Audio & Headphones' => 'audio-headphones',
                'Laptops & Computers' => 'laptops-computers',
                'Smartphones & Tablets' => 'smartphones-tablets',
            ],
            'Home' => [
                'Kitchen Appliances' => 'kitchen-appliances',
                'Smart Home' => 'smart-home',
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
                        'description' => "Explore {$subName}.",
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
     * Seed attributes and values.
     *
     * @return array<string, array<string, AttributeValue>>
     */
    protected function seedAttributes(): array
    {
        $defs = [
            'Color' => [
                'Black', 'Navy Blue', 'Slate Gray', 'Silver / Black', 'Gold / Brown',
                'Chalk White', 'Charcoal Gray', 'Oatmeal Beige', 'Matte Black',
                'Silver White', 'Space Silver', 'Space Gray', 'Matte Obsidian',
            ],
            'Size' => [
                'S', 'M', 'L', 'XL', '38mm', '40mm', '40', '41', '42', '43',
                'Standard', '13"-16" compatible', '500ml', '750ml',
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
     * Seed real products with real image filenames and variants.
     *
     * @param  array<string, Category>  $categories
     * @param  array<string, array<string, AttributeValue>>  $attributes
     */
    protected function seedProducts(array $categories, array $attributes): void
    {
        $products = [
            [
                'title' => 'Premium Leather Tote',
                'slug' => 'premium-leather-tote',
                'category_name' => 'Fashion',
                'description' => 'A minimalist studio-crafted premium leather tote bag. The aesthetic is highly polished, using full-grain Italian leather with reinforced stitching, ergonomic drop handles, and silver-tone hardware.',
                'meta_title' => 'Premium Leather Tote | Handcrafted Luxury Bag',
                'meta_description' => 'Buy handcrafted Premium Leather Tote in full-grain Italian leather with silver-tone hardware.',
                'images' => [
                    'products/premium-leather-tote.jpg',
                    'products/premium-leather-tote-1.jpg',
                    'products/premium-leather-tote-2.jpg',
                    'products/premium-leather-tote-3.jpg',
                    'products/premium-leather-tote-4.jpg',
                ],
                'variants' => [
                    [
                        'sku' => 'LT-2024-BLK-M',
                        'price' => 2450.00,
                        'compare_at_price' => 2800.00,
                        'stock_quantity' => 18,
                        'weight' => 0.85,
                        'is_default' => true,
                        'color' => 'Black',
                        'size' => 'M',
                    ],
                    [
                        'sku' => 'LT-2024-NAVY-L',
                        'price' => 2450.00,
                        'compare_at_price' => 2800.00,
                        'stock_quantity' => 10,
                        'weight' => 0.85,
                        'is_default' => false,
                        'color' => 'Navy Blue',
                        'size' => 'L',
                    ],
                    [
                        'sku' => 'LT-2024-GRAY-S',
                        'price' => 2450.00,
                        'compare_at_price' => 2800.00,
                        'stock_quantity' => 5,
                        'weight' => 0.85,
                        'is_default' => false,
                        'color' => 'Slate Gray',
                        'size' => 'S',
                    ],
                ],
            ],
            [
                'title' => 'Minimalist Watch',
                'slug' => 'minimalist-watch',
                'category_name' => 'Fashion',
                'description' => 'Sleek, minimalist wristwatch with a genuine calfskin leather strap, Japanese quartz movement, and water resistance up to 30m.',
                'meta_title' => 'Minimalist Watch | Japanese Quartz Wristwatch',
                'meta_description' => 'Sleek, minimalist wristwatch with genuine leather strap and Japanese quartz movement.',
                'images' => [
                    'products/minimalist-watch.jpg',
                ],
                'variants' => [
                    [
                        'sku' => 'WT-1090-SIL-40',
                        'price' => 1890.00,
                        'compare_at_price' => 2100.00,
                        'stock_quantity' => 12,
                        'weight' => 0.15,
                        'is_default' => true,
                        'color' => 'Silver / Black',
                        'size' => '40mm',
                    ],
                    [
                        'sku' => 'WT-1090-GLD-38',
                        'price' => 1890.00,
                        'compare_at_price' => 2100.00,
                        'stock_quantity' => 8,
                        'weight' => 0.15,
                        'is_default' => false,
                        'color' => 'Gold / Brown',
                        'size' => '38mm',
                    ],
                ],
            ],
            [
                'title' => 'Classic White Sneakers',
                'slug' => 'classic-white-sneakers',
                'category_name' => 'Fashion',
                'description' => 'Crisp, handmade white designer sneakers engineered with memory foam cushioning and durable vulcanized rubber soles.',
                'meta_title' => 'Classic White Sneakers | Handmade Designer Shoes',
                'meta_description' => 'Handmade white designer sneakers with memory foam cushioning and vulcanized soles.',
                'images' => [
                    'products/classic-white-sneakers.jpg',
                ],
                'variants' => [
                    [
                        'sku' => 'SN-3200-WHT-42',
                        'price' => 3200.00,
                        'compare_at_price' => 3600.00,
                        'stock_quantity' => 3,
                        'weight' => 0.90,
                        'is_default' => true,
                        'color' => 'Chalk White',
                        'size' => '42',
                    ],
                    [
                        'sku' => 'SN-3200-WHT-41',
                        'price' => 3200.00,
                        'compare_at_price' => 3600.00,
                        'stock_quantity' => 5,
                        'weight' => 0.90,
                        'is_default' => false,
                        'color' => 'Chalk White',
                        'size' => '41',
                    ],
                ],
            ],
            [
                'title' => 'Cashmere Blend Sweater',
                'slug' => 'cashmere-blend-sweater',
                'category_name' => 'Fashion',
                'description' => 'Ultra-soft knitted crewneck sweater woven from a premium cashmere and merino wool blend for lightweight warmth.',
                'meta_title' => 'Cashmere Blend Sweater | Mongolian Cashmere Knit',
                'meta_description' => 'Ultra-soft crewneck sweater woven from Mongolian cashmere and merino wool blend.',
                'images' => [
                    'products/cashmere-blend-sweater.jpg',
                ],
                'variants' => [
                    [
                        'sku' => 'SW-4150-CHAR-M',
                        'price' => 4150.00,
                        'compare_at_price' => 4500.00,
                        'stock_quantity' => 14,
                        'weight' => 0.40,
                        'is_default' => true,
                        'color' => 'Charcoal Gray',
                        'size' => 'M',
                    ],
                    [
                        'sku' => 'SW-4150-OAT-L',
                        'price' => 4150.00,
                        'compare_at_price' => 4500.00,
                        'stock_quantity' => 9,
                        'weight' => 0.40,
                        'is_default' => false,
                        'color' => 'Oatmeal Beige',
                        'size' => 'L',
                    ],
                ],
            ],
            [
                'title' => 'Aura Studio Pro Headphones',
                'slug' => 'aura-studio-pro-headphones',
                'category_name' => 'Electronics',
                'description' => 'High-fidelity wireless studio headphones featuring active noise cancellation, custom 40mm titanium drivers, and 35-hour battery life.',
                'meta_title' => 'Aura Studio Pro Headphones | Wireless ANC Studio Audio',
                'meta_description' => 'High-fidelity wireless studio headphones with active noise cancellation and 35-hour battery.',
                'images' => [
                    'products/aura-studio-pro-headphones.jpg',
                ],
                'variants' => [
                    [
                        'sku' => 'HP-1800-BLK',
                        'price' => 1800.00,
                        'compare_at_price' => 2200.00,
                        'stock_quantity' => 25,
                        'weight' => 0.28,
                        'is_default' => true,
                        'color' => 'Matte Black',
                        'size' => 'Standard',
                    ],
                    [
                        'sku' => 'HP-1800-SLV',
                        'price' => 1800.00,
                        'compare_at_price' => 2200.00,
                        'stock_quantity' => 15,
                        'weight' => 0.28,
                        'is_default' => false,
                        'color' => 'Silver White',
                        'size' => 'Standard',
                    ],
                ],
            ],
            [
                'title' => 'Ergo Lift Aluminum Stand',
                'slug' => 'ergo-lift-aluminum-stand',
                'category_name' => 'Electronics',
                'description' => 'Precision CNC-machined aerospace-grade aluminum laptop riser with thermal ventilation and silicone anti-slip grip pads.',
                'meta_title' => 'Ergo Lift Aluminum Stand | Ergonomic Laptop Riser',
                'meta_description' => 'CNC-machined aluminum laptop riser with thermal ventilation and non-slip pads.',
                'images' => [
                    'products/ergo-lift-aluminum-stand.jpg',
                ],
                'variants' => [
                    [
                        'sku' => 'ST-0089-SLV',
                        'price' => 890.00,
                        'compare_at_price' => 1100.00,
                        'stock_quantity' => 40,
                        'weight' => 0.46,
                        'is_default' => true,
                        'color' => 'Space Silver',
                        'size' => '13"-16" compatible',
                    ],
                    [
                        'sku' => 'ST-0089-GRY',
                        'price' => 890.00,
                        'compare_at_price' => 1100.00,
                        'stock_quantity' => 20,
                        'weight' => 0.46,
                        'is_default' => false,
                        'color' => 'Space Gray',
                        'size' => '13"-16" compatible',
                    ],
                ],
            ],
            [
                'title' => 'Smart Water Bottle',
                'slug' => 'smart-water-bottle',
                'category_name' => 'Home',
                'description' => 'Double-walled vacuum insulated stainless steel smart water bottle with LED touch temperature display and 24h cold retention.',
                'meta_title' => 'Smart Water Bottle | Insulated Temperature Display',
                'meta_description' => 'Vacuum insulated smart water bottle with LED touch temperature cap and 24h cold retention.',
                'images' => [
                    'products/smart-water-bottle.jpg',
                ],
                'variants' => [
                    [
                        'sku' => 'WB-0650-OBS-500',
                        'price' => 650.00,
                        'compare_at_price' => 800.00,
                        'stock_quantity' => 30,
                        'weight' => 0.35,
                        'is_default' => true,
                        'color' => 'Matte Obsidian',
                        'size' => '500ml',
                    ],
                    [
                        'sku' => 'WB-0650-OBS-750',
                        'price' => 750.00,
                        'compare_at_price' => 900.00,
                        'stock_quantity' => 15,
                        'weight' => 0.45,
                        'is_default' => false,
                        'color' => 'Matte Obsidian',
                        'size' => '750ml',
                    ],
                ],
            ],
        ];

        foreach ($products as $pData) {
            $cat = $categories[$pData['category_name']] ?? $categories['Fashion'];

            $product = Product::updateOrCreate(
                ['slug' => $pData['slug']],
                [
                    'category_id' => $cat->id,
                    'title' => $pData['title'],
                    'description' => $pData['description'],
                    'meta_title' => $pData['meta_title'],
                    'meta_description' => $pData['meta_description'],
                    'is_active' => true,
                ]
            );

            // Seed images
            ProductImage::where('product_id', $product->id)->delete();
            $sortOrder = 0;
            foreach ($pData['images'] as $imgRelPath) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'product_variant_id' => null,
                    'path' => $imgRelPath,
                    'sort_order' => $sortOrder++,
                ]);
            }

            // Seed variants
            foreach ($pData['variants'] as $vData) {
                $variant = ProductVariant::updateOrCreate(
                    ['sku' => $vData['sku']],
                    [
                        'product_id' => $product->id,
                        'price' => $vData['price'],
                        'compare_at_price' => $vData['compare_at_price'],
                        'stock_quantity' => $vData['stock_quantity'],
                        'weight' => $vData['weight'],
                        'is_default' => $vData['is_default'],
                    ]
                );

                $pivotIds = [];
                if (isset($vData['color']) && isset($attributes['Color'][$vData['color']])) {
                    $pivotIds[] = $attributes['Color'][$vData['color']]->id;
                }
                if (isset($vData['size']) && isset($attributes['Size'][$vData['size']])) {
                    $pivotIds[] = $attributes['Size'][$vData['size']]->id;
                }

                if (! empty($pivotIds)) {
                    $variant->attributeValues()->sync(array_values(array_unique($pivotIds)));
                }
            }
        }
    }
}
