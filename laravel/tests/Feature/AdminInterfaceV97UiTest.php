<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminInterfaceV97UiTest extends TestCase
{
    public function test_the_user_modal_has_explicit_viewport_centering_rules(): void
    {
        $css = file_get_contents(public_path('admin/css/admin_users.css'));

        $this->assertStringContainsString('position: fixed !important', $css);
        $this->assertStringContainsString('inset: 0 !important', $css);
        $this->assertStringContainsString('margin: auto !important', $css);
        $this->assertStringContainsString('body.users-modal-open', $css);
    }

    public function test_the_submissions_page_does_not_use_the_old_wide_table(): void
    {
        $view = file_get_contents(resource_path('views/admin/submissions/indexs.blade.php'));

        $this->assertStringContainsString('submission-item', $view);
        $this->assertStringNotContainsString('min-width: 1450px', $view);
        $this->assertStringNotContainsString('submissions-table-wrap', $view);
    }

    public function test_sms_and_settings_stylesheets_are_connected(): void
    {
        $sms = file_get_contents(resource_path('views/admin/sms/index.blade.php'));
        $settings = file_get_contents(resource_path('views/admin/settings.blade.php'));

        $this->assertStringContainsString('admin/css/admin_sms.css', $sms);
        $this->assertStringContainsString('admin/css/admin_settings.css', $settings);
        $this->assertFileExists(public_path('admin/css/admin_sms.css'));
        $this->assertFileExists(public_path('admin/css/admin_settings.css'));
        $this->assertFileExists(public_path('admin/js/settings.js'));
    }
}
