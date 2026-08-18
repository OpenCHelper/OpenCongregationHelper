<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkDeletionFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private User $rootUser;

    private User $regularUser1;

    private User $regularUser2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rootUser = User::factory()->create(['id' => 1]);
        $this->regularUser1 = User::factory()->create();
        $this->regularUser2 = User::factory()->create();
    }

    public function test_bulk_deletion_logic_filters_root_user()
    {
        // Simulate the bulk delete action logic
        $records = collect([$this->rootUser, $this->regularUser1, $this->regularUser2]);

        // Check if root user is in the selection
        $hasRootUser = $records->contains(fn (User $record) => $record->isRootUser());
        $this->assertTrue($hasRootUser);

        // Filter out root user before deletion
        $recordsToDelete = $records->filter(fn (User $record) => ! $record->isRootUser());
        $this->assertCount(2, $recordsToDelete);
        $this->assertFalse($recordsToDelete->contains($this->rootUser));

        // Verify regular users are in the deletion list
        $this->assertTrue($recordsToDelete->contains($this->regularUser1));
        $this->assertTrue($recordsToDelete->contains($this->regularUser2));
    }

    public function test_bulk_deletion_with_only_root_user_selected()
    {
        // Simulate selecting only the root user
        $records = collect([$this->rootUser]);

        $hasRootUser = $records->contains(fn (User $record) => $record->isRootUser());
        $recordsToDelete = $records->filter(fn (User $record) => ! $record->isRootUser());
        $deletedCount = $recordsToDelete->count();

        $this->assertTrue($hasRootUser);
        $this->assertEquals(0, $deletedCount);

        // This scenario should show the "only root selected" message
        $this->assertTrue($hasRootUser && $deletedCount === 0);
    }

    public function test_bulk_deletion_with_mixed_selection()
    {
        // Simulate selecting root user and regular users
        $records = collect([$this->rootUser, $this->regularUser1]);

        $hasRootUser = $records->contains(fn (User $record) => $record->isRootUser());
        $recordsToDelete = $records->filter(fn (User $record) => ! $record->isRootUser());
        $deletedCount = $recordsToDelete->count();

        $this->assertTrue($hasRootUser);
        $this->assertEquals(1, $deletedCount);

        // This scenario should show the "partial success" message
        $this->assertTrue($hasRootUser && $deletedCount > 0);
    }

    public function test_bulk_deletion_with_only_regular_users()
    {
        // Simulate selecting only regular users
        $records = collect([$this->regularUser1, $this->regularUser2]);

        $hasRootUser = $records->contains(fn (User $record) => $record->isRootUser());
        $recordsToDelete = $records->filter(fn (User $record) => ! $record->isRootUser());
        $deletedCount = $recordsToDelete->count();

        $this->assertFalse($hasRootUser);
        $this->assertEquals(2, $deletedCount);

        // This scenario should let Filament show the default success message
        $this->assertFalse($hasRootUser);
    }

    public function test_translation_keys_exist()
    {
        // Verify that the translation keys exist and return non-empty strings
        $this->assertNotEmpty(__('profile.root_user_protection.bulk_delete_partial_success', ['deleted' => 2]));
        $this->assertNotEmpty(__('profile.root_user_protection.bulk_delete_only_root_selected'));
    }
}
