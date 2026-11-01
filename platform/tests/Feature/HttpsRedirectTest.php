<?php

namespace Tests\Feature;

use Tests\TestCase;

class HttpsRedirectTest extends TestCase
{
    public function test_production_http_requests_redirect_to_the_configured_origin_with_path_and_query(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.url' => 'https://streams.chelidze.ge']);

        $this->get('http://untrusted.example/login?next=%2Fdashboard')
            ->assertStatus(308)
            ->assertRedirect('https://streams.chelidze.ge/login?next=%2Fdashboard');
    }

    public function test_redirect_preserves_post_methods_instead_of_processing_insecure_credentials(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.url' => 'https://streams.chelidze.ge']);

        $this->post('http://streams.chelidze.ge/login')
            ->assertStatus(308)
            ->assertRedirect('https://streams.chelidze.ge/login');
    }

    public function test_production_https_requests_are_not_redirected(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.url' => 'https://streams.chelidze.ge']);

        $this->get('https://streams.chelidze.ge/up')->assertOk();
    }

    public function test_local_http_requests_are_not_redirected_even_with_an_https_app_url(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['app.url' => 'https://streams.chelidze.ge']);

        $this->get('http://localhost/up')->assertOk();
    }
}
