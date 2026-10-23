<?php

namespace Tests;

use App\Models\Product\Subcategory;
use App\Services\Vies;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Vies::$pause = 0; // no waiting between VIES retries in tests
    }

    /** Id of a product subcategory by its code (the lists come with the migrations). */
    protected static function sub(string $code, string $category = ''): int
    {
        return (int) Subcategory::where('code', $code)
            ->when($category !== '', fn ($q) => $q->whereHas('category', fn ($c) => $c->where('code', $category)))->value('id');
    }
}
