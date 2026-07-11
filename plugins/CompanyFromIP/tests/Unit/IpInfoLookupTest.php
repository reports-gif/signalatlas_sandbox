<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CompanyFromIP\tests\Unit;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\CompanyFromIP\Lookup\IpInfoLookup;

/**
 * @group CompanyFromIP
 * @group IpInfoLookupTest
 * @group Plugins
 */
class IpInfoLookupTest extends TestCase
{
    private function callCleanOrgName(string $org): string
    {
        $lookup     = new IpInfoLookup('', 2);
        $reflection = new \ReflectionMethod(IpInfoLookup::class, 'cleanOrgName');
        $reflection->setAccessible(true);

        return (string) $reflection->invoke($lookup, $org);
    }

    public function test_cleanOrgName_stripsAsnPrefix(): void
    {
        $this->assertSame('Capgemini France',  $this->callCleanOrgName('AS12345 Capgemini France'));
        $this->assertSame('Free SAS',           $this->callCleanOrgName('AS12322 Free SAS'));
        $this->assertSame('Orange',             $this->callCleanOrgName('AS3215 Orange'));
        $this->assertSame('Thales Group',       $this->callCleanOrgName('AS199422 Thales Group'));
    }

    public function test_cleanOrgName_handlesStringWithNoAsnPrefix(): void
    {
        $this->assertSame('No ASN Here',   $this->callCleanOrgName('No ASN Here'));
        $this->assertSame('Company Name',  $this->callCleanOrgName('Company Name'));
    }

    public function test_cleanOrgName_trimsSurroundingWhitespace(): void
    {
        $this->assertSame('My Company', $this->callCleanOrgName('AS9999 My Company'));
        $this->assertSame('My Company', $this->callCleanOrgName('  My Company  '));
    }

    public function test_cleanOrgName_handlesEmptyString(): void
    {
        $this->assertSame('', $this->callCleanOrgName(''));
    }
}
