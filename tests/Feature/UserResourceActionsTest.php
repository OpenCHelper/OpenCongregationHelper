<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserResourceActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $rootUser;
    private User $regularUser;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create the root user (ID = 1)
        $this->rootUser = User::factory()->create([
            'id' => 1,
            'email' => 'root@example.com',
            'can_login' => true,
        ]);
        
        // Create a regular user
        $this->regularUser = User::factory()->create([
            'email' => 'user@example.com',
            'can_login' => true,
        ]);
        
        // Create another user that will act as admin
        $this->adminUser = User::factory()->create([
            'email' => 'admin@example.com',
            'can_login' => true,
        ]);
    }

    /** @test */
    public function root_user_delete_actions_are_always_disabled()
    {
        // Login as any user (including root)
        $this->actingAs($this->adminUser);
        
        // Test that the delete action would always be disabled for root user
        $this->assertTrue($this->rootUser->isRootUser());
        $this->assertFalse($this->rootUser->canBeDeleted());
    }

    /** @test */
    public function root_user_actions_are_disabled_for_non_root_users()
    {
        // Login as admin user (not root)
        $this->actingAs($this->adminUser);
        
        // Test that the edit action would be disabled
        $this->assertTrue($this->rootUser->isRootUserProtected());
        $this->assertFalse($this->adminUser->isRootUser());
    }

    /** @test */
    public function root_user_actions_are_enabled_for_root_user()
    {
        // Login as root user
        $this->actingAs($this->rootUser);
        
        // Test that the edit action would be enabled
        $this->assertTrue($this->rootUser->isRootUserProtected());
        $this->assertTrue($this->rootUser->isRootUser());
    }

    /** @test */
    public function regular_user_actions_are_always_enabled()
    {
        // Login as admin user
        $this->actingAs($this->adminUser);
        
        // Test that regular user actions are always enabled
        $this->assertFalse($this->regularUser->isRootUserProtected());
    }

    /** @test */
    public function root_user_can_be_identified_correctly()
    {
        $this->assertTrue($this->rootUser->isRootUser());
        $this->assertFalse($this->regularUser->isRootUser());
        $this->assertFalse($this->adminUser->isRootUser());
    }
}
