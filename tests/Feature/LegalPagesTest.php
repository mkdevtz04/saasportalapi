<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    public function test_the_terms_page_is_public_and_shows_the_live_withdrawal_fee(): void
    {
        config(['platform.withdrawal_fee_pct' => 7.5]);

        $this->get('/terms')
            ->assertOk()
            ->assertSee('Terms &amp; Conditions', false)
            ->assertSee('7.5% of the amount');
    }

    /** The checkbox an ISP ticks to finish onboarding has to link to what they are agreeing to. */
    public function test_the_pages_that_ask_for_agreement_link_to_the_terms(): void
    {
        $this->get('/register')->assertOk()->assertSee(route('terms'));
    }
}
