<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji dasar halaman web: root wajib login.
 */
class ExampleTest extends TestCase
{
    /**
     * Halaman root dilindungi login: tanpa sesi → redirect ke /login (audit Critical 1.x).
     */
    public function test_the_root_page_requires_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}
