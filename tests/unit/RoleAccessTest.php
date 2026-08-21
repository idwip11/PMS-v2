<?php

use App\Libraries\RoleAccess;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class RoleAccessTest extends CIUnitTestCase
{
    public function testRoleMatrixMatchesApplicationRules(): void
    {
        $this->assertFalse(RoleAccess::allows('Admin', 'finance'));
        $this->assertTrue(RoleAccess::allows('Admin', 'settings'));
        $this->assertFalse(RoleAccess::allows('Manager', 'settings'));
        $this->assertTrue(RoleAccess::allows('Manager', 'finance'));
        $this->assertFalse(RoleAccess::allows('Finance', 'settings'));
        $this->assertFalse(RoleAccess::allows('Marketing', 'finance'));
        $this->assertFalse(RoleAccess::allows('Marketing', 'operations'));
        $this->assertFalse(RoleAccess::allows('Marketing', 'settings'));
        $this->assertTrue(RoleAccess::allows('Operational', 'finance'));
        $this->assertFalse(RoleAccess::allows('Operational', 'settings'));
        $this->assertTrue(RoleAccess::allows('Housekeeping', 'housekeeping'));
        $this->assertFalse(RoleAccess::allows('Housekeeping', 'dashboard'));
        $this->assertFalse(RoleAccess::allows('Staff', 'finance'));
        $this->assertFalse(RoleAccess::allows('Staff', 'operations'));
        $this->assertFalse(RoleAccess::allows('Staff', 'settings'));
    }

    public function testFinancialMaskAndLandingPageRules(): void
    {
        $this->assertTrue(RoleAccess::masksDashboardFinancials('Admin'));
        $this->assertTrue(RoleAccess::masksDashboardFinancials('Staff'));
        $this->assertFalse(RoleAccess::masksDashboardFinancials('Marketing'));
        $this->assertSame('housekeeping', RoleAccess::landingPath('Housekeeping'));
        $this->assertSame('dashboard', RoleAccess::landingPath('Manager'));
    }

    public function testOwnerGuesthouseRolesCanOnlyAccessTheirOwnOwnerReport(): void
    {
        $this->assertSame([
            'Owner GH 1',
            'Owner GH 2',
            'Owner GH 3',
            'Owner GH 4',
            'Owner GH 5',
        ], RoleAccess::OWNER_ROLES);
        $this->assertFalse(RoleAccess::allows('Owner GH 1', 'dashboard'));
        $this->assertFalse(RoleAccess::allows('Owner GH 5', 'finance'));
        $this->assertFalse(RoleAccess::allows('Owner GH 3', 'pending'));
        $this->assertTrue(RoleAccess::allows('Owner GH 3', 'owner_finance'));
        $this->assertTrue(RoleAccess::allows('Owner GH 1', 'payment_proof'));
        $this->assertTrue(RoleAccess::allows('Owner GH 5', 'payment_proof'));
        $this->assertTrue(RoleAccess::allows('Owner GH 3', 'expense_receipt'));
        $this->assertSame('finance/owner', RoleAccess::landingPath('Owner GH 3'));
        $this->assertSame(1, RoleAccess::ownerGuesthouse('Owner GH 1'));
        $this->assertSame(5, RoleAccess::ownerGuesthouse('Owner GH 5'));
        $this->assertNull(RoleAccess::ownerGuesthouse('Finance'));
    }
}
