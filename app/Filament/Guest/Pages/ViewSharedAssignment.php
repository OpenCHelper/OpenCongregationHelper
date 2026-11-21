<?php

namespace App\Filament\Guest\Pages;

use App\Filament\Resources\TerritoryAssignments\Actions\RequestReturnAction;
use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use App\Models\SharedUrl;
use App\Models\TerritoryAssignment;
use Filament\Actions\Action;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ViewSharedAssignment extends Page implements HasInfolists
{
    use InteractsWithInfolists;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    protected string $view = 'filament.guest.pages.view-shared-assignment';

    protected static bool $shouldRegisterNavigation = false;

    public SharedUrl $sharedUrl;

    public TerritoryAssignment $assignment;

    public function mount(string $record): void
    {
        // The links are kept while sharing is disabled, so that enabling it again restores them
        abort_unless(SharedUrl::isSharingEnabled(), 404);

        $this->sharedUrl = SharedUrl::where('uid', $record)->firstOrFail();

        if (! $this->sharedUrl->shareable instanceof TerritoryAssignment) {
            abort(404, 'Invalid shareable resource type.');
        }

        $this->assignment = $this->sharedUrl->shareable;
    }

    public function getTitle(): string
    {
        // The territory is missing when it (or its zone) has been deleted while still assigned
        $territoryName = $this->assignment->territory?->name ?? __('filament-resources.territory_assignment.info_territory_placeholder');

        return ucfirst(__('filament-resources.territory_assignment.model_label').': '.$territoryName);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            RequestReturnAction::make()
                ->record($this->assignment)
                // A block, not an arrow function: what `after()` returns is sent back to the browser, and the refreshed
                // assignment would expose its personal data (assignee, notes) that the page hides
                ->after(function (): void {
                    $this->assignment->refresh();
                }),
        ];
    }

    public function assignmentInfolist(Schema $schema): Schema
    {
        return TerritoryAssignmentResource::infolist($schema)->record($this->assignment);
    }

    public static function getRoutePath(Panel $panel): string
    {
        return '/shared-assignment/{record}';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
