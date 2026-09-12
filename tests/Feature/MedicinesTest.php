<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

/**
 * Feature tests for the migrated Medicines module (herbal pharma companies,
 * brands, medicine detail sections, JSON API + scraper endpoints).
 *
 * The module is JSON-file driven (no DB). Tests write a small fixture dataset
 * into the shared uploads dir the service reads, exercise the routes, then
 * clean the fixture up.
 */
class MedicinesTest extends TestCase
{
    use WithoutMiddleware;

    protected string $medexDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->medexDir = base_path('public/uploads/medex');
        if (! is_dir($this->medexDir)) {
            mkdir($this->medexDir, 0755, true);
        }

        $fixture = [
            [
                'name' => 'Square Herbal & Nutraceuticals Ltd.',
                'url' => 'https://medex.com.bd/companies/1/square-herbal',
                'generics' => 42,
                'brands' => 87,
                'established' => '1998',
                'market_share' => '18.5%',
                'growth' => '+4.2%',
                'total_generics' => '120',
                'headquarter' => 'Dhaka, Bangladesh',
                'contact' => '+880-2-1234567',
                'fax' => '+880-2-7654321',
                'overview' => "Square Herbal is one of the leading herbal pharmaceutical companies in Bangladesh.\nIt produces a wide range of herbal medicines.",
                'top_brands' => [
                    [
                        'name' => 'Sikarol',
                        'generic' => 'Cissampelos pareira 500mg',
                        'url' => 'https://medex.com.bd/brands/101/sikarol',
                    ],
                    [
                        'name' => 'Syp Sikarol',
                        'generic' => 'Cissampelos pareira Syrup 250ml',
                        'url' => 'https://medex.com.bd/brands/102/syp-sikarol',
                    ],
                ],
            ],
            [
                'name' => 'Kumudini Herbal Co. Ltd.',
                'url' => 'https://medex.com.bd/companies/2/kumudini-herbal',
                'generics' => 15,
                'brands' => 30,
                'established' => '2001',
                'headquarter' => 'Narayanganj, Bangladesh',
                'top_brands' => [
                    [
                        'name' => 'Kuminol',
                        'generic' => 'Herbal Digestive Tonic 100ml',
                        'url' => 'https://medex.com.bd/brands/201/kuminol',
                    ],
                ],
            ],
            [
                'name' => 'Hamdard Laboratories Bangladesh',
                'url' => 'https://medex.com.bd/companies/3/hamdard-bd',
                'generics' => 28,
                'brands' => 55,
                'established' => '1953',
                'headquarter' => 'Meghna Ghat, Bangladesh',
                'top_brands' => [
                    [
                        'name' => 'Safi',
                        'generic' => 'Herbal Blood Purifier 200ml',
                        'url' => 'https://medex.com.bd/brands/301/safi',
                    ],
                ],
            ],
        ];

        file_put_contents($this->medexDir.'/medex_herbal_companies.json', json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Detailed data: gives brand 101 the 11 medication sections.
        $detailed = [
            [
                'brands_details' => [
                    [
                        '_id' => 101,
                        '_company_id' => 1,
                        'url' => 'https://medex.com.bd/brands/101/sikarol',
                        'details_en' => [
                            'indications' => 'Urinary disorders, kidney stones',
                            'pharmacology' => 'Acts as a diuretic and anti-inflammatory agent.',
                            'dosage' => '1 tablet twice daily after meals.',
                            'interactions' => 'No significant drug interactions reported.',
                            'contraindications' => 'Hypersensitivity to any component.',
                            'side_effects' => 'Nausea in rare cases.',
                            'pregnancy' => 'Use only under medical supervision.',
                            'precautions' => 'Drink plenty of water during treatment.',
                            'overdose' => 'No specific antidote; symptomatic treatment.',
                            'therapeutic_class' => 'Herbal preparations',
                            'storage' => 'Store in a cool, dry place away from light.',
                        ],
                        'details_bn' => [
                            'indications' => 'মূত্রজনিত রোগ, কিডনিতে পাথর',
                        ],
                    ],
                ],
            ],
        ];
        file_put_contents($this->medexDir.'/medex_herbal_companies_detailed.json', json_encode($detailed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    protected function tearDown(): void
    {
        @unlink($this->medexDir.'/medex_herbal_companies.json');
        @unlink($this->medexDir.'/medex_herbal_companies_detailed.json');

        parent::tearDown();
    }

    // ==================== PUBLIC PAGES ====================

    public function test_companies_list_renders(): void
    {
        $response = $this->get('/medicines');

        $response->assertOk();
        $response->assertSee('Herbal Pharmaceutical Companies in Bangladesh');
        $response->assertSee('Square Herbal & Nutraceuticals Ltd.');
        $response->assertSee('Hamdard Laboratories Bangladesh');
        $response->assertSee('Total Companies');
    }

    public function test_companies_list_respects_pagination(): void
    {
        $response = $this->get('/medicines?page=2');

        $response->assertOk();
        // 3 companies, 20/page → page 2 has no rows (empty state shows)
        $response->assertSee('Loading latest Medicines data');
    }

    public function test_company_detail_renders_with_brands(): void
    {
        $response = $this->get('/medicines/company/1');

        $response->assertOk();
        $response->assertSee('Square Herbal & Nutraceuticals Ltd.');
        $response->assertSee('Sikarol');
        $response->assertSee('Established');
        $response->assertSee('1998');
        $response->assertSee('Market Share');
        $response->assertSee('Products & Brands');
    }

    public function test_company_detail_missing_404s(): void
    {
        $response = $this->get('/medicines/company/99999');

        $response->assertNotFound();
    }

    public function test_brand_detail_renders_all_11_sections(): void
    {
        $response = $this->get('/medicines/brand/101');

        $response->assertOk();
        $response->assertSee('Sikarol');
        $response->assertSee('Square Herbal & Nutraceuticals Ltd.');

        $sectionKeys = [
            'indications',
            'pharmacology',
            'dosage',
            'interactions',
            'contraindications',
            'side_effects',
            'pregnancy',
            'precautions',
            'overdose',
            'therapeutic_class',
            'storage',
        ];
        foreach ($sectionKeys as $key) {
            $response->assertSee('id="section-'.$key.'"', false);
        }

        $response->assertSee('Indications');
        $response->assertSee('Urinary disorders, kidney stones');
        $response->assertSee('Therapeutic Class');
        $response->assertSee('Storage');
    }

    public function test_brand_detail_falls_back_to_basic_data_when_no_details(): void
    {
        // Brand 201 (Kuminol) has no detailed file entry → sections empty but page renders.
        $response = $this->get('/medicines/brand/201');

        $response->assertOk();
        $response->assertSee('Kuminol');
        $response->assertSee('Kumudini Herbal Co. Ltd.');
        $response->assertSee('Detailed information for this section is not yet available');
    }

    public function test_brand_detail_missing_404s(): void
    {
        $response = $this->get('/medicines/brand/99999');

        $response->assertNotFound();
    }

    public function test_details_dashboard_renders(): void
    {
        $response = $this->get('/medicines/details');

        $response->assertOk();
        $response->assertSee('Medicines Dataset Details');
        $response->assertSee('3'); // total companies
        $response->assertSee('Cache File');
        $response->assertSee('Refresh Lock');
        $response->assertSee('Browser-powered Collection');
    }

    public function test_companies_alias_redirects(): void
    {
        $response = $this->get('/medicines/companies');

        $response->assertRedirect('/medicines');
        $response->assertStatus(301);
    }

    // ==================== JSON API ====================

    public function test_api_companies_returns_json(): void
    {
        $response = $this->getJson('/api/medicines/companies');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'count' => 3,
            ])
            ->assertJsonPath('companies.0.name', 'Square Herbal & Nutraceuticals Ltd.');
        $this->assertStringContainsString('max-age=3600', $response->headers->get('Cache-Control', ''));
    }

    public function test_api_company_returns_brands(): void
    {
        $response = $this->getJson('/api/medicines/company/1');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('company.name', 'Square Herbal & Nutraceuticals Ltd.')
            ->assertJsonCount(2, 'company.brands');
    }

    public function test_api_company_missing_404s(): void
    {
        $response = $this->getJson('/api/medicines/company/99999');

        $response->assertNotFound()
            ->assertJsonPath('error', 'Company not found');
    }

    public function test_api_brand_returns_details(): void
    {
        $response = $this->getJson('/api/medicines/brand/101');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('brand.name', 'Sikarol')
            ->assertJsonPath('brand.details_en.indications', 'Urinary disorders, kidney stones');
    }

    public function test_api_brand_missing_404s(): void
    {
        $response = $this->getJson('/api/medicines/brand/99999');

        $response->assertNotFound()
            ->assertJsonPath('error', 'Brand not found');
    }

    // ==================== SCRAPER API (dual auth) ====================

    public function test_api_proxy_rejects_forbidden_domain(): void
    {
        $response = $this->postJson('/api/medicines/proxy', [
            'url' => 'https://evil.example.com/page',
            'token' => env('MEDEX_REFRESH_TOKEN', 'phpunit-medex-token'),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'forbidden_domain');
    }

    public function test_api_proxy_missing_url_rejected(): void
    {
        $response = $this->postJson('/api/medicines/proxy', [
            'token' => env('MEDEX_REFRESH_TOKEN', 'phpunit-medex-token'),
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error', 'Missing url');
    }

    public function test_api_fetch_page_missing_url_rejected(): void
    {
        $response = $this->postJson('/api/medicines/fetch-page', [
            'token' => env('MEDEX_REFRESH_TOKEN', 'phpunit-medex-token'),
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error', 'Missing url');
    }

    public function test_api_save_data_rejects_invalid_payload(): void
    {
        $response = $this->postJson('/api/medicines/save-data', [
            'foo' => 'bar',
            'token' => env('MEDEX_REFRESH_TOKEN', 'phpunit-medex-token'),
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error', 'Invalid payload: expected {data: [...] }');
    }

    public function test_api_rejects_wrong_token(): void
    {
        $response = $this->postJson('/api/medicines/save-data', [
            'data' => [['name' => 'x', 'url' => 'https://medex.com.bd/companies/1/x']],
            'token' => 'wrong-token',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error', 'Unauthorized');
    }

    public function test_api_save_data_persists_dataset_and_backs_up(): void
    {
        $newDataset = [
            [
                'name' => 'Test Herbal Ltd.',
                'url' => 'https://medex.com.bd/companies/9/test-herbal',
                'generics' => 5,
                'brands' => 9,
                'established' => '2010',
                'top_brands' => [
                    [
                        'name' => 'Testherb',
                        'generic' => 'Herbal Tonic 100ml',
                        'url' => 'https://medex.com.bd/brands/901/testherb',
                    ],
                ],
            ],
            [
                'name' => 'Test Herbal 2',
                'url' => 'https://medex.com.bd/companies/10/test-herbal-2',
                'generics' => 6,
                'brands' => 7,
                'established' => '2011',
            ],
            [
                'name' => 'Test Herbal 3',
                'url' => 'https://medex.com.bd/companies/11/test-herbal-3',
                'generics' => 6,
                'brands' => 7,
            ],
            [
                'name' => 'Test Herbal 4',
                'url' => 'https://medex.com.bd/companies/12/test-herbal-4',
                'generics' => 6,
                'brands' => 7,
            ],
            [
                'name' => 'Test Herbal 5',
                'url' => 'https://medex.com.bd/companies/13/test-herbal-5',
                'generics' => 6,
                'brands' => 7,
            ],
        ];

        $response = $this->postJson('/api/medicines/save-data', [
            'data' => $newDataset,
            'meta' => ['source' => 'phpunit-fixture'],
            'token' => env('MEDEX_REFRESH_TOKEN', 'phpunit-medex-token'),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('saved', 5)
            ->assertJsonPath('file', 'medex_herbal_companies.json');

        // The saved file now contains the new dataset.
        $saved = json_decode(file_get_contents($this->medexDir.'/medex_herbal_companies.json'), true);
        $this->assertCount(5, $saved);
        $this->assertSame('Test Herbal Ltd.', $saved[0]['name']);

        // Backup of the original fixture exists.
        $backups = glob($this->medexDir.'/medex_herbal_companies.json.bak-*');
        $this->assertNotEmpty($backups);

        // Restore the fixture so teardown/file state is predictable.
        file_put_contents(
            $this->medexDir.'/medex_herbal_companies.json',
            json_encode($this->fixture(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        foreach ($backups as $b) {
            @unlink($b);
        }
    }

    public function test_api_save_data_refuses_small_companies_dataset(): void
    {
        $response = $this->postJson('/api/medicines/save-data', [
            'data' => [
                ['name' => 'One', 'url' => 'https://medex.com.bd/companies/1/one'],
            ],
            'token' => env('MEDEX_REFRESH_TOKEN', 'phpunit-medex-token'),
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error', 'Refusing suspiciously small companies dataset');
    }

    /**
     * The 3-company fixture used by most tests.
     */
    protected function fixture(): array
    {
        return [
            [
                'name' => 'Square Herbal & Nutraceuticals Ltd.',
                'url' => 'https://medex.com.bd/companies/1/square-herbal',
                'generics' => 42,
                'brands' => 87,
                'established' => '1998',
                'market_share' => '18.5%',
                'growth' => '+4.2%',
                'total_generics' => '120',
                'headquarter' => 'Dhaka, Bangladesh',
                'contact' => '+880-2-1234567',
                'fax' => '+880-2-7654321',
                'overview' => "Square Herbal is one of the leading herbal pharmaceutical companies in Bangladesh.\nIt produces a wide range of herbal medicines.",
                'top_brands' => [
                    ['name' => 'Sikarol', 'generic' => 'Cissampelos pareira 500mg', 'url' => 'https://medex.com.bd/brands/101/sikarol'],
                    ['name' => 'Syp Sikarol', 'generic' => 'Cissampelos pareira Syrup 250ml', 'url' => 'https://medex.com.bd/brands/102/syp-sikarol'],
                ],
            ],
            [
                'name' => 'Kumudini Herbal Co. Ltd.',
                'url' => 'https://medex.com.bd/companies/2/kumudini-herbal',
                'generics' => 15,
                'brands' => 30,
                'established' => '2001',
                'headquarter' => 'Narayanganj, Bangladesh',
                'top_brands' => [
                    ['name' => 'Kuminol', 'generic' => 'Herbal Digestive Tonic 100ml', 'url' => 'https://medex.com.bd/brands/201/kuminol'],
                ],
            ],
            [
                'name' => 'Hamdard Laboratories Bangladesh',
                'url' => 'https://medex.com.bd/companies/3/hamdard-bd',
                'generics' => 28,
                'brands' => 55,
                'established' => '1953',
                'headquarter' => 'Meghna Ghat, Bangladesh',
                'top_brands' => [
                    ['name' => 'Safi', 'generic' => 'Herbal Blood Purifier 200ml', 'url' => 'https://medex.com.bd/brands/301/safi'],
                ],
            ],
        ];
    }
}