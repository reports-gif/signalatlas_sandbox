<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CompanyFromIP\tests\Integration;

use Piwik\API\Request;
use Piwik\NoAccessException;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group CompanyFromIP
 * @group ApiTest
 * @group Plugins
 */
class ApiTest extends IntegrationTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        Fixture::createSuperUser();
        Fixture::createWebsite('2024-01-01');
    }

    public function test_getCompanyVisits_throwsException_forAnonymousUser(): void
    {
        $this->expectException(NoAccessException::class);

        Request::processRequest('CompanyFromIP.getCompanyVisits', [
            'idSite' => 1,
            'period' => 'day',
            'date'   => 'today',
        ]);
    }

    public function test_getCompanyVisits_returnsDataTable_forSuperUser(): void
    {
        $result = Request::processRequest('CompanyFromIP.getCompanyVisits', [
            'idSite'     => 1,
            'period'     => 'day',
            'date'       => 'today',
            'token_auth' => Fixture::getTokenAuth(),
        ]);

        $this->assertInstanceOf(\Piwik\DataTable::class, $result);
    }
}
