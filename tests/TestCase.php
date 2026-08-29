<?php

namespace Tests;

use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * @mixin BaseTestCase
 * @mixin MakesHttpRequests
 * @mixin InteractsWithAuthentication
 * @mixin InteractsWithDatabase
 * @mixin InteractsWithSession
 */
abstract class TestCase extends BaseTestCase
{
    //
}
