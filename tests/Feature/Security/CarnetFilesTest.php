<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CarnetFilesTest extends TestCase
{
    public function test_should_delete_only_old_carnets_when_purge_runs(): void
    {
        // Arrange
        Storage::fake('public');
        $disk = Storage::disk('public');
        $disk->put('carnets/viejo.pdf', 'pdf');
        $disk->put('carnets/nuevo.pdf', 'pdf');
        touch($disk->path('carnets/viejo.pdf'), now()->subDays(8)->getTimestamp());

        // Act
        $this->artisan('carnets:purge')->assertSuccessful();

        // Assert
        $disk->assertMissing('carnets/viejo.pdf');
        $disk->assertExists('carnets/nuevo.pdf');
    }
}
