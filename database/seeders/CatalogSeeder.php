<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->generateSampleImages();
        $attributes = $this->seedAttributes();
        $categories = $this->seedCategories();
        $this->seedProducts($categories, $attributes);
    }

    /**
     * Generate SVG sample images in storage/app/public/.
     */
    protected function generateSampleImages(): void
    {
        $categoryImages = [
            'electronics.svg' => ['Electronics', '#3B82F6', '#1E40AF'],
            'laptops-computers.svg' => ['Laptops', '#6366F1', '#4338CA'],
            'smartphones-tablets.svg' => ['Smartphones', '#EC4899', '#BE185D'],
            'audio-headphones.svg' => ['Audio', '#8B5CF6', '#6D28D9'],
            'fashion-apparel.svg' => ['Fashion', '#F59E0B', '#D97706'],
            'mens-clothing.svg' => ["Men's Wear", '#10B981', '#047857'],
            'womens-clothing.svg' => ["Women's Wear", '#F43F5E', '#BE123C'],
            'footwear.svg' => ['Footwear', '#64748B', '#334155'],
            'home-living.svg' => ['Home & Living', '#14B8A6', '#0F766E'],
            'kitchen-appliances.svg' => ['Kitchen', '#F97316', '#C2410C'],
            'smart-home.svg' => ['Smart Home', '#06B6D4', '#0E7490'],
        ];

        foreach ($categoryImages as $file => $info) {
            $svg = $this->createSvgPlaceholder($info[0], $info[1], $info[2], 400, 300);
            Storage::disk('public')->put("categories/{$file}", $svg);
        }

        $productImages = [
            'macbook-pro-1.svg' => ['MacBook Pro M3 Max - Space Gray', '#1E293B', '#0F172A'],
            'macbook-pro-2.svg' => ['MacBook Pro M3 Max - Silver', '#64748B', '#475569'],
            'iphone-15-pro-1.svg' => ['iPhone 15 Pro Max - Titanium Black', '#18181B', '#27272A'],
            'iphone-15-pro-2.svg' => ['iPhone 15 Pro Max - Silver', '#94A3B8', '#64748B'],
            'sony-xm5-1.svg' => ['Sony WH-1000XM5 - Black', '#0F172A', '#1E293B'],
            'sony-xm5-2.svg' => ['Sony WH-1000XM5 - Silver', '#E2E8F0', '#94A3B8'],
            'dell-xps-1.svg' => ['Dell XPS 15 OLED', '#334155', '#1E293B'],
            'merino-sweater-1.svg' => ['Merino Wool Sweater - Black', '#18181B', '#27272A'],
            'merino-sweater-2.svg' => ['Merino Wool Sweater - Blue', '#1E3A8A', '#1E40AF'],
            'chelsea-boots-1.svg' => ['Italian Leather Chelsea Boots', '#451A03', '#78350F'],
            'espresso-machine-1.svg' => ['Espresso Machine Pro - Steel', '#475569', '#334155'],
            'samsung-s24-1.svg' => ['Galaxy S24 Ultra - Titanium Black', '#09090B', '#18181B'],
            'samsung-s24-2.svg' => ['Galaxy S24 Ultra - Space Gray', '#475569', '#334155'],
        ];

        foreach ($productImages as $file => $info) {
            $svg = $this->createSvgPlaceholder($info[0], $info[1], $info[2], 600, 600);
            Storage::disk('public')->put("products/{$file}", $svg);
        }
    }

    /**
     * Create an SVG placeholder string.
     */
    protected function createSvgPlaceholder(string $title, string $bgStart, string $bgEnd, int $width, int $height): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}">
    <defs>
        <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" style="stop-color:{$bgStart};stop-opacity:1" />
            <stop offset="100%" style="stop-color:{$bgEnd};stop-opacity:1" />
        </linearGradient>
    </defs>
    <rect width="100%" height="100%" fill="url(#grad)"/>
    <circle cx="{$width}/2" cy="{$height}/2" r="60" fill="rgba(255,255,255,0.15)"/>
    <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#FFFFFF" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="18" font-weight="600">
        {$safeTitle}
    </text>
    <text x="50%" y="60%" dominant-baseline="middle" text-anchor="middle" fill="rgba(255,255,255,0.7)" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="12">
        Sample Store Asset
    </text>
</svg>
SVG;
    }

    /**
     * Seed attributes and values.
     *
     * @return array<string, array<string, AttributeValue>>
     */
    protected function seedAttributes(): array
    {
        $attributeDefinitions = [
            'Color' => ['Black', 'Silver', 'Space Gray', 'Midnight Blue', 'Alpine White'],
            'Storage' => ['128GB', '256GB', '512GB', '1TB'],
            'Size' => ['Small', 'Medium', 'Large', 'Extra Large'],
            'RAM' => ['8GB', '16GB', '32GB'],
        ];

        $attributes = [];

        foreach ($attributeDefinitions as $attrName => $values) {
            $attribute = Attribute::updateOrCreate(
                ['slug' => Str::slug($attrName)],
                ['name' => $attrName]
            );

            $attributes[$attrName] = [];

            foreach ($values as $val) {
                $valSlug = Str::slug($val);
                $attrValue = AttributeValue::updateOrCreate(
                    [
                        'attribute_id' => $attribute->id,
                        'slug' => $valSlug,
                    ],
                    [
                        'value' => $val,
                    ]
                );

                $attributes[$attrName][$val] = $attrValue;
            }
        }

        return $attributes;
    }

    /**
     * Seed parent and child categories.
     *
     * @return array<string, Category>
     */
    protected function seedCategories(): array
    {
        $categoriesMap = [];

        $hierarchy = [
            'Electronics' => [
                'slug' => 'electronics',
                'image' => 'categories/electronics.svg',
                'children' => [
                    'Laptops & Computers' => ['slug' => 'laptops-computers', 'image' => 'categories/laptops-computers.svg'],
                    'Smartphones & Tablets' => ['slug' => 'smartphones-tablets', 'image' => 'categories/smartphones-tablets.svg'],
                    'Audio & Headphones' => ['slug' => 'audio-headphones', 'image' => 'categories/audio-headphones.svg'],
                ],
            ],
            'Fashion & Apparel' => [
                'slug' => 'fashion-apparel',
                'image' => 'categories/fashion-apparel.svg',
                'children' => [
                    "Men's Clothing" => ['slug' => 'mens-clothing', 'image' => 'categories/mens-clothing.svg'],
                    "Women's Clothing" => ['slug' => 'womens-clothing', 'image' => 'categories/womens-clothing.svg'],
                    'Footwear' => ['slug' => 'footwear', 'image' => 'categories/footwear.svg'],
                ],
            ],
            'Home & Living' => [
                'slug' => 'home-living',
                'image' => 'categories/home-living.svg',
                'children' => [
                    'Kitchen Appliances' => ['slug' => 'kitchen-appliances', 'image' => 'categories/kitchen-appliances.svg'],
                    'Smart Home' => ['slug' => 'smart-home', 'image' => 'categories/smart-home.svg'],
                ],
            ],
        ];

        $sort = 1;
        foreach ($hierarchy as $parentName => $parentData) {
            $parent = Category::updateOrCreate(
                ['slug' => $parentData['slug']],
                [
                    'parent_id' => null,
                    'name' => $parentName,
                    'description' => "Explore our curated collection of {$parentName}.",
                    'image_path' => $parentData['image'],
                    'is_active' => true,
                    'sort_order' => $sort++,
                ]
            );

            $categoriesMap[$parentName] = $parent;

            $childSort = 1;
            foreach ($parentData['children'] as $childName => $childData) {
                $child = Category::updateOrCreate(
                    ['slug' => $childData['slug']],
                    [
                        'parent_id' => $parent->id,
                        'name' => $childName,
                        'description' => "Top quality {$childName} available with warranty and fast delivery.",
                        'image_path' => $childData['image'],
                        'is_active' => true,
                        'sort_order' => $childSort++,
                    ]
                );

                $categoriesMap[$childName] = $child;
            }
        }

        return $categoriesMap;
    }

    /**
     * Seed realistic products, variants, pivot attribute values, and images.
     *
     * @param  array<string, Category>  $categories
     * @param  array<string, array<string, AttributeValue>>  $attributes
     */
    protected function seedProducts(array $categories, array $attributes): void
    {
        $productsData = [
            [
                'category' => 'Laptops & Computers',
                'title' => 'MacBook Pro 16 M3 Max',
                'slug' => 'macbook-pro-16-m3-max',
                'description' => 'The most advanced Mac laptop ever, powered by the M3 Max chip with extreme performance and battery life.',
                'meta_title' => 'MacBook Pro 16 M3 Max | High Performance Laptop',
                'meta_description' => 'Buy MacBook Pro 16 M3 Max featuring Liquid Retina XDR display, up to 128GB unified memory, and industry leading efficiency.',
                'images' => ['products/macbook-pro-1.svg', 'products/macbook-pro-2.svg'],
                'variants' => [
                    [
                        'sku' => 'MBP16-512-16-SG',
                        'price' => 2499.00,
                        'compare_at_price' => 2699.00,
                        'stock_quantity' => 15,
                        'weight' => 2.16,
                        'is_default' => true,
                        'attributes' => [
                            $attributes['Storage']['512GB']->id,
                            $attributes['RAM']['16GB']->id,
                            $attributes['Color']['Space Gray']->id,
                        ],
                    ],
                    [
                        'sku' => 'MBP16-1TB-32-SG',
                        'price' => 3199.00,
                        'compare_at_price' => 3499.00,
                        'stock_quantity' => 8,
                        'weight' => 2.16,
                        'is_default' => false,
                        'attributes' => [
                            $attributes['Storage']['1TB']->id,
                            $attributes['RAM']['32GB']->id,
                            $attributes['Color']['Space Gray']->id,
                        ],
                    ],
                    [
                        'sku' => 'MBP16-1TB-32-SL',
                        'price' => 3199.00,
                        'compare_at_price' => 3499.00,
                        'stock_quantity' => 5,
                        'weight' => 2.16,
                        'is_default' => false,
                        'attributes' => [
                            $attributes['Storage']['1TB']->id,
                            $attributes['RAM']['32GB']->id,
                            $attributes['Color']['Silver']->id,
                        ],
                    ],
                ],
            ],
            [
                'category' => 'Smartphones & Tablets',
                'title' => 'iPhone 15 Pro Max',
                'slug' => 'iphone-15-pro-max',
                'description' => 'Forged in titanium and featuring the groundbreaking A17 Pro chip, customizable Action button, and versatile 5x Telephoto camera.',
                'meta_title' => 'iPhone 15 Pro Max | Premium Titanium Smartphone',
                'meta_description' => 'Get the iPhone 15 Pro Max with aerospace-grade titanium design, A17 Pro chip, and next-gen portrait photography.',
                'images' => ['products/iphone-15-pro-1.svg', 'products/iphone-15-pro-2.svg'],
                'variants' => [
                    [
                        'sku' => 'IPH15PM-256-BLK',
                        'price' => 1199.00,
                        'compare_at_price' => 1299.00,
                        'stock_quantity' => 25,
                        'weight' => 0.22,
                        'is_default' => true,
                        'attributes' => [
                            $attributes['Storage']['256GB']->id,
                            $attributes['Color']['Black']->id,
                        ],
                    ],
                    [
                        'sku' => 'IPH15PM-512-SG',
                        'price' => 1399.00,
                        'compare_at_price' => 1499.00,
                        'stock_quantity' => 12,
                        'weight' => 0.22,
                        'is_default' => false,
                        'attributes' => [
                            $attributes['Storage']['512GB']->id,
                            $attributes['Color']['Space Gray']->id,
                        ],
                    ],
                    [
                        'sku' => 'IPH15PM-1TB-SLV',
                        'price' => 1599.00,
                        'compare_at_price' => 1699.00,
                        'stock_quantity' => 7,
                        'weight' => 0.22,
                        'is_default' => false,
                        'attributes' => [
                            $attributes['Storage']['1TB']->id,
                            $attributes['Color']['Silver']->id,
                        ],
                    ],
                ],
            ],
            [
                'category' => 'Audio & Headphones',
                'title' => 'Sony WH-1000XM5 Wireless Headphones',
                'slug' => 'sony-wh-1000xm5',
                'description' => 'Industry-leading noise canceling with two processors, 8 microphones, exceptional sound quality, and crystal clear hands-free calling.',
                'meta_title' => 'Sony WH-1000XM5 Noise Canceling Headphones',
                'meta_description' => 'Experience supreme audio immersion with the Sony WH-1000XM5 wireless noise cancelling headphones.',
                'images' => ['products/sony-xm5-1.svg', 'products/sony-xm5-2.svg'],
                'variants' => [
                    [
                        'sku' => 'SONY-XM5-BLK',
                        'price' => 399.99,
                        'compare_at_price' => 449.99,
                        'stock_quantity' => 30,
                        'weight' => 0.25,
                        'is_default' => true,
                        'attributes' => [
                            $attributes['Color']['Black']->id,
                        ],
                    ],
                    [
                        'sku' => 'SONY-XM5-SLV',
                        'price' => 399.99,
                        'compare_at_price' => 449.99,
                        'stock_quantity' => 18,
                        'weight' => 0.25,
                        'is_default' => false,
                        'attributes' => [
                            $attributes['Color']['Silver']->id,
                        ],
                    ],
                    [
                        'sku' => 'SONY-XM5-MBLU',
                        'price' => 399.99,
                        'compare_at_price' => 449.99,
                        'stock_quantity' => 14,
                        'weight' => 0.25,
                        'is_default' => false,
                        'attributes' => [
                            $attributes['Color']['Midnight Blue']->id,
                        ],
                    ],
                ],
            ],
            [
                'category' => 'Laptops & Computers',
                'title' => 'Dell XPS 15 OLED',
                'slug' => 'dell-xps-15-oled',
                'description' => 'Stunning 3.5K OLED touchscreen display combined with 13th Gen Intel Core processors and NVIDIA GeForce RTX graphics.',
                'meta_title' => 'Dell XPS 15 OLED | Creator & Performance Laptop',
                'meta_description' => 'Power your creative projects on the Dell XPS 15 with 3.5K OLED display and dedicated RTX graphics.',
                'images' => ['products/dell-xps-1.svg'],
                'variants' => [
                    [
                        'sku' => 'XPS15-512-16-SLV',
                        'price' => 1899.00,
                        'compare_at_price' => 2099.00,
                        'stock_quantity' => 10,
                        'weight' => 1.92,
                        'is_default' => true,
                        'attributes' => [
                            $attributes['Storage']['512GB']->id,
                            $attributes['RAM']['16GB']->id,
                            $attributes['Color']['Silver']->id,
                        ],
                    ],
                    [
                        'sku' => 'XPS15-1TB-32-SLV',
                        'price' => 2399.00,
                        'compare_at_price' => 2599.00,
                        'stock_quantity' => 6,
                        'weight' => 1.92,
                        'is_default' => false,
                        'attributes' => [
                            $attributes['Storage']['1TB']->id,
                            $attributes['RAM']['32GB']->id,
                            $attributes['Color']['Silver']->id,
                        ],
                    ],
                ],
            ],
            [
                'category' => "Men's Clothing",
                'title' => 'Premium Merino Wool Crewneck Sweater',
                'slug' => 'merino-wool-crewneck-sweater',
                'description' => 'Crafted from 100% ultrafine Australian Merino wool for lightweight thermal regulation, breathable warmth, and all-day softness.',
                'meta_title' => 'Premium 100% Merino Wool Sweater for Men',
                'meta_description' => 'Upgrade your wardrobe with this luxuriously soft, odor-resistant, temperature-regulating Merino wool sweater.',
                'images' => ['products/merino-sweater-1.svg', 'products/merino-sweater-2.svg'],
                'variants' => [
                    [
                        'sku' => 'M-SWTR-BLK-M',
                        'price' => 89.00,
                        'compare_at_price' => 110.00,
                        'stock_quantity' => 45,
                        'weight' => 0.35,
                        'is_default' => true,
                        'attributes' => [
                            $attributes['Color']['Black']->id,
                            $attributes['Size']['Medium']->id,
                        ],
                    ],
                    [
                        'sku' => 'M-SWTR-BLK-L',
                        'price' => 89.00,
                        'compare_at_price' => 110.00,
                        'stock_quantity' => 30,
                        'weight' => 0.38,
                        'is_default' => false,
                        'attributes' => [
                            $attributes['Color']['Black']->id,
                            $attributes['Size']['Large']->id,
                        ],
                    ],
                    [
                        'sku' => 'M-SWTR-BLU-L',
                        'price' => 89.00,
                        'compare_at_price' => 110.00,
                        'stock_quantity' => 20,
                        'weight' => 0.38,
                        'is_default' => false,
                        'attributes' => [
                            $attributes['Color']['Midnight Blue']->id,
                            $attributes['Size']['Large']->id,
                        ],
                    ],
                ],
            ],
            [
                'category' => 'Footwear',
                'title' => 'Italian Handcrafted Chelsea Boots',
                'slug' => 'italian-handcrafted-chelsea-boots',
                'description' => 'Full-grain calfskin leather upper with Goodyear-welted durable rubber sole and elastic side gussets for effortless comfort.',
                'meta_title' => 'Italian Handcrafted Leather Chelsea Boots',
                'meta_description' => 'Classic timeless Chelsea boots made in Italy from full-grain calfskin leather with water-resistant finish.',
                'images' => ['products/chelsea-boots-1.svg'],
                'variants' => [
                    [
                        'sku' => 'BOOT-CHEL-BLK-M',
                        'price' => 175.00,
                        'compare_at_price' => 220.00,
                        'stock_quantity' => 18,
                        'weight' => 1.10,
                        'is_default' => true,
                        'attributes' => [
                            $attributes['Color']['Black']->id,
                            $attributes['Size']['Medium']->id,
                        ],
                    ],
                    [
                        'sku' => 'BOOT-CHEL-BLK-L',
                        'price' => 175.00,
                        'compare_at_price' => 220.00,
                        'stock_quantity' => 12,
                        'weight' => 1.15,
                        'is_default' => false,
                        'attributes' => [
                            $attributes['Color']['Black']->id,
                            $attributes['Size']['Large']->id,
                        ],
                    ],
                ],
            ],
            [
                'category' => 'Kitchen Appliances',
                'title' => 'Smart Espresso Machine Pro',
                'slug' => 'smart-espresso-machine-pro',
                'description' => 'Commercial-grade 15-bar Italian pump with digital PID temperature control, integrated burr grinder, and microfoam steam wand.',
                'meta_title' => 'Smart Espresso Machine Pro | Barista Grade Coffee',
                'meta_description' => 'Brew cafe-quality espresso, lattes, and cappuccinos at home with precision thermal stability and integrated grinder.',
                'images' => ['products/espresso-machine-1.svg'],
                'variants' => [
                    [
                        'sku' => 'ESPR-PRO-SS',
                        'price' => 649.00,
                        'compare_at_price' => 749.00,
                        'stock_quantity' => 14,
                        'weight' => 9.50,
                        'is_default' => true,
                        'attributes' => [
                            $attributes['Color']['Silver']->id,
                        ],
                    ],
                    [
                        'sku' => 'ESPR-PRO-BLK',
                        'price' => 649.00,
                        'compare_at_price' => 749.00,
                        'stock_quantity' => 9,
                        'weight' => 9.50,
                        'is_default' => false,
                        'attributes' => [
                            $attributes['Color']['Black']->id,
                        ],
                    ],
                ],
            ],
            [
                'category' => 'Smartphones & Tablets',
                'title' => 'Samsung Galaxy S24 Ultra',
                'slug' => 'samsung-galaxy-s24-ultra',
                'description' => 'Galaxy AI power in a titanium frame with 200MP camera resolution, built-in S Pen, and Snapdragon 8 Gen 3 processor.',
                'meta_title' => 'Samsung Galaxy S24 Ultra | AI Flagship Smartphone',
                'meta_description' => 'Shop Galaxy S24 Ultra with flat 6.8-inch Dynamic AMOLED 2X screen, titanium durability, and real-time AI translation.',
                'images' => ['products/samsung-s24-1.svg', 'products/samsung-s24-2.svg'],
                'variants' => [
                    [
                        'sku' => 'S24U-256-BLK',
                        'price' => 1299.00,
                        'compare_at_price' => 1399.00,
                        'stock_quantity' => 20,
                        'weight' => 0.23,
                        'is_default' => true,
                        'attributes' => [
                            $attributes['Storage']['256GB']->id,
                            $attributes['Color']['Black']->id,
                        ],
                    ],
                    [
                        'sku' => 'S24U-512-SG',
                        'price' => 1419.00,
                        'compare_at_price' => 1539.00,
                        'stock_quantity' => 14,
                        'weight' => 0.23,
                        'is_default' => false,
                        'attributes' => [
                            $attributes['Storage']['512GB']->id,
                            $attributes['Color']['Space Gray']->id,
                        ],
                    ],
                ],
            ],
        ];

        foreach ($productsData as $prod) {
            $category = $categories[$prod['category']];

            $product = Product::updateOrCreate(
                ['slug' => $prod['slug']],
                [
                    'category_id' => $category->id,
                    'title' => $prod['title'],
                    'description' => $prod['description'],
                    'is_active' => true,
                    'meta_title' => $prod['meta_title'],
                    'meta_description' => $prod['meta_description'],
                ]
            );

            // Images
            foreach ($prod['images'] as $imgIndex => $imgPath) {
                ProductImage::firstOrCreate(
                    [
                        'product_id' => $product->id,
                        'path' => $imgPath,
                    ],
                    [
                        'product_variant_id' => null,
                        'sort_order' => $imgIndex,
                    ]
                );
            }

            // Variants
            foreach ($prod['variants'] as $varData) {
                $variant = ProductVariant::updateOrCreate(
                    ['sku' => $varData['sku']],
                    [
                        'product_id' => $product->id,
                        'price' => $varData['price'],
                        'compare_at_price' => $varData['compare_at_price'],
                        'stock_quantity' => $varData['stock_quantity'],
                        'weight' => $varData['weight'],
                        'is_default' => $varData['is_default'],
                    ]
                );

                // Sync pivot attribute values
                $variant->attributeValues()->sync($varData['attributes']);
            }
        }
    }
}
