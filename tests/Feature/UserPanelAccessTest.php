<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Tests\TestCase;

class UserPanelAccessTest extends TestCase
{
    public function test_user_implements_filament_user_contract(): void
    {
        $this->assertInstanceOf(FilamentUser::class, new User);
    }

    public function test_can_access_panel_returns_true(): void
    {
        $user = new User;
        $panel = Panel::make()->id('admin');

        $this->assertTrue($user->canAccessPanel($panel));
    }
}
