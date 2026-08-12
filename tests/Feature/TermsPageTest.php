<?php

namespace Tests\Feature;

use Tests\TestCase;

class TermsPageTest extends TestCase
{
    public function test_terms_page_renders_in_english(): void
    {
        $response = $this->withSession(['locale' => 'en'])->get(route('terms'));

        $response->assertOk();
        $response->assertSeeText(__('terms.page_title', locale: 'en'));
        $response->assertSeeText(__('terms.section_1_title', locale: 'en'));
    }

    public function test_terms_page_renders_in_dutch(): void
    {
        $response = $this->withSession(['locale' => 'nl'])->get(route('terms'));

        $response->assertOk();
        $response->assertSeeText(__('terms.page_title', locale: 'nl'));
        $response->assertSeeText(__('terms.section_1_title', locale: 'nl'));
    }

    public function test_dutch_terms_alias_redirects_to_canonical_route(): void
    {
        $response = $this->get('/algemene-voorwaarden');

        $response->assertRedirect('/terms');
        $response->assertStatus(301);
    }
}
