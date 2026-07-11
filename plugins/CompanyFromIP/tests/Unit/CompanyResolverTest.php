<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CompanyFromIP\tests\Unit;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Piwik\Plugins\CompanyFromIP\CompanyResolver;
use Piwik\Plugins\CompanyFromIP\Dao\CompanyCacheDao;
use Piwik\Plugins\CompanyFromIP\Lookup\IpInfoLookup;

/**
 * @group CompanyFromIP
 * @group CompanyResolverTest
 * @group Plugins
 */
class CompanyResolverTest extends TestCase
{
    /** @var CompanyCacheDao&MockObject */
    private MockObject $cacheDao;

    /** @var IpInfoLookup&MockObject */
    private MockObject $lookup;

    private CompanyResolver $resolver;

    protected function setUp(): void
    {
        $this->cacheDao = $this->createMock(CompanyCacheDao::class);
        $this->lookup   = $this->createMock(IpInfoLookup::class);
        $this->resolver = new CompanyResolver($this->cacheDao, $this->lookup, 30);
    }

    public function test_resolveCompany_returnsCachedResult_whenCacheIsFresh(): void
    {
        $this->cacheDao->expects($this->once())
            ->method('findByHash')
            ->willReturn(['company_name' => 'Acme Corp', 'lookup_date' => date('Y-m-d H:i:s')]);

        $this->lookup->expects($this->never())->method('lookup');

        $result = $this->resolver->resolveCompany('1.2.3.4');

        $this->assertSame('Acme Corp', $result);
    }

    public function test_resolveCompany_callsApi_whenCacheIsExpired(): void
    {
        $expiredDate = date('Y-m-d H:i:s', strtotime('-60 days'));

        $this->cacheDao->expects($this->once())
            ->method('findByHash')
            ->willReturn(['company_name' => 'Old Corp', 'lookup_date' => $expiredDate]);

        $this->lookup->expects($this->once())
            ->method('lookup')
            ->willReturn('New Corp');

        $this->cacheDao->expects($this->once())->method('upsert');

        $result = $this->resolver->resolveCompany('1.2.3.4');

        $this->assertSame('New Corp', $result);
    }

    public function test_resolveCompany_callsApi_whenNotInCache(): void
    {
        $this->cacheDao->expects($this->once())
            ->method('findByHash')
            ->willReturn(null);

        $this->lookup->expects($this->once())
            ->method('lookup')
            ->with('1.2.3.4')
            ->willReturn('Capgemini France');

        $this->cacheDao->expects($this->once())->method('upsert');

        $result = $this->resolver->resolveCompany('1.2.3.4');

        $this->assertSame('Capgemini France', $result);
    }

    public function test_resolveCompany_storesNullResult_whenApiReturnsNull(): void
    {
        $this->cacheDao->method('findByHash')->willReturn(null);
        $this->lookup->method('lookup')->willReturn(null);

        $this->cacheDao->expects($this->once())
            ->method('upsert')
            ->with($this->anything(), null);

        $result = $this->resolver->resolveCompany('1.2.3.4');

        $this->assertNull($result);
    }

    public function test_resolveCompany_hashesIpConsistently(): void
    {
        $expectedHash = hash('sha256', '1.2.3.4');

        $this->cacheDao->expects($this->once())
            ->method('findByHash')
            ->with($expectedHash)
            ->willReturn(['company_name' => 'Test Corp', 'lookup_date' => date('Y-m-d H:i:s')]);

        $this->resolver->resolveCompany('1.2.3.4');
    }

    public function test_resolveCompany_returnsNull_forPrivateIp(): void
    {
        $this->cacheDao->expects($this->never())->method('findByHash');
        $this->lookup->expects($this->never())->method('lookup');

        $result = $this->resolver->resolveCompany('192.168.1.1');

        $this->assertNull($result);
    }

    public function test_resolveCompany_returnsNull_forLocalhostIp(): void
    {
        $this->cacheDao->expects($this->never())->method('findByHash');
        $this->lookup->expects($this->never())->method('lookup');

        $result = $this->resolver->resolveCompany('127.0.0.1');

        $this->assertNull($result);
    }

    public function test_resolveCompany_returnsCachedNull_whenPreviousLookupFoundNoCompany(): void
    {
        // A previous lookup returned null (unknown company) and was cached
        $this->cacheDao->method('findByHash')
            ->willReturn(['company_name' => null, 'lookup_date' => date('Y-m-d H:i:s')]);

        // API should NOT be called again — null is a valid cached result
        $this->lookup->expects($this->never())->method('lookup');

        $result = $this->resolver->resolveCompany('1.2.3.4');

        $this->assertNull($result);
    }
}
