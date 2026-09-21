<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminUsersArchitectureTest extends TestCase
{
    public function test_admin_users_views_and_routes_are_registered(): void
    {
        $this->assertTrue(view()->exists('admin.users.index'));
        $this->assertTrue(Route::has('admin.users.index'));
        $this->assertTrue(Route::has('admin.users.store'));
        $this->assertTrue(Route::has('admin.users.update'));
        $this->assertTrue(Route::has('admin.users.destroy'));
    }
}
