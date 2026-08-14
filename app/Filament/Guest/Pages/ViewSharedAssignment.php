<?php

namespace App\Filament\Guest\Pages;

use Filament\Schemas\Schema;
use Filament\Panel;
use App\Models\SharedUrl;
use App\Models\TerritoryAssignment;
use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use Filament\Pages\Page;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Illuminate\Database\Eloquent\Model;

class ViewSharedAssignment extends Page implements HasInfolists
{
    use InteractsWithInfolists;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-share';
    
    protected string $view = 'filament.guest.pages.view-shared-assignment';
    
    protected static bool $shouldRegisterNavigation = false;

    public SharedUrl $sharedUrl;
    public TerritoryAssignment $assignment;

    public function mount(string $record): void
    {
        $this->sharedUrl = SharedUrl::where('uid', $record)->firstOrFail();
        
        if (!$this->sharedUrl->shareable instanceof TerritoryAssignment) {
            abort(404, 'Invalid shareable resource type.');
        }
        
        $this->assignment = $this->sharedUrl->shareable;
    }

    public function getTitle(): string
    {
        return ucfirst(__('filament-resources.territory_assignment.model_label') . ': ' . $this->assignment->territory->name);
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
