<?php
namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    //protected $seed   = true;
    //protected $seeder = \Database\Seeders\DemoDatabaseSeeder::class;
    use CreatesApplication;
}
