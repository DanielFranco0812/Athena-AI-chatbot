<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_load(): void
    {
        $this->seed();
        foreach (['/', '/schedule', '/schedule?week=2', '/coaches', '/membership', '/contact', '/login', '/register'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/')->assertSee('Athena AI')->assertSee('Infliction');
    }
}
