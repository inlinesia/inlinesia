<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProjectMapTest extends TestCase
{
    public function test_project_map_contains_every_named_route(): void
    {
        $map = file_get_contents(base_path('docs/peta-proyek.md'));

        Artisan::call('route:list', ['--json' => true, '--except-vendor' => true]);

        /** @var array<int, array<string, mixed>>|null $routes */
        $routes = json_decode(Artisan::output(), true);
        $routeNames = collect($routes ?? [])->pluck('name')->filter()->values();

        $this->assertIsString($map);
        $this->assertNotSame([], $routeNames->all());

        foreach ($routeNames as $routeName) {
            $this->assertStringContainsString('`'.$routeName.'`', $map);
        }
    }

    public function test_project_map_keeps_the_auto_generated_markers(): void
    {
        $map = file_get_contents(base_path('docs/peta-proyek.md'));

        $this->assertIsString($map);
        $this->assertStringContainsString('<!-- AUTO:START -->', $map);
        $this->assertStringContainsString('<!-- AUTO:END -->', $map);
    }
}
