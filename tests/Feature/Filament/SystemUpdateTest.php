<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\SystemUpdate;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class SystemUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_submit_update_action_is_disabled_initially(): void
    {
        Livewire::test(SystemUpdate::class)
            ->assertActionDisabled(TestAction::make('confirmUpdate'));
    }

    public function test_submit_update_action_is_disabled_with_only_purchase_code(): void
    {
        Livewire::test(SystemUpdate::class)
            ->set('purchaseCode', 'PURCHASE-CODE-12345')
            ->assertActionDisabled(TestAction::make('confirmUpdate'));
    }

    public function test_submit_update_action_is_disabled_with_only_update_file(): void
    {
        $file = UploadedFile::fake()->create('update.zip', 1024, 'application/zip');

        Livewire::test(SystemUpdate::class)
            ->set('updateFile', $file)
            ->assertActionDisabled(TestAction::make('confirmUpdate'));
    }

    public function test_submit_update_action_is_enabled_when_both_purchase_code_and_file_are_present(): void
    {
        $file = UploadedFile::fake()->create('update.zip', 1024, 'application/zip');

        Livewire::test(SystemUpdate::class)
            ->set('purchaseCode', 'PURCHASE-CODE-12345')
            ->set('updateFile', $file)
            ->assertActionEnabled(TestAction::make('confirmUpdate'));
    }

    public function test_submit_update_action_is_disabled_with_whitespace_purchase_code(): void
    {
        $file = UploadedFile::fake()->create('update.zip', 1024, 'application/zip');

        Livewire::test(SystemUpdate::class)
            ->set('purchaseCode', '   ')
            ->set('updateFile', $file)
            ->assertActionDisabled(TestAction::make('confirmUpdate'));
    }
}
