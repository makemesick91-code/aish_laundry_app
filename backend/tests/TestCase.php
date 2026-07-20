<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base test case.
 *
 * Tests run against the AUTHORITATIVE PostgreSQL configured in `.env`, never
 * SQLite. SQLite does not enforce the composite foreign keys that make a
 * cross-tenant relation structurally impossible, so a suite that passed on
 * SQLite would prove nothing about tenant isolation (see phpunit.xml).
 */
abstract class TestCase extends BaseTestCase
{
}
