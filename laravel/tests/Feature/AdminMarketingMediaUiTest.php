<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminMarketingMediaUiTest extends TestCase
{
    public function test_marketing_views_exist(): void
    {
        $this->assertTrue(view()->exists('admin.banners.index'));
        $this->assertTrue(view()->exists('admin.banners.create'));
        $this->assertTrue(view()->exists('admin.home-ads.index'));
        $this->assertTrue(view()->exists('admin.home-ads.create'));
        $this->assertTrue(view()->exists('admin.home-ads.edit'));
        $this->assertTrue(view()->exists('admin.home-ads._form'));
        $this->assertTrue(view()->exists('admin.home-ads.partials.preview-script'));
    }
}
