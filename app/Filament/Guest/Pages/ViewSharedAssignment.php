<?php

namespace App\Filament\Guest\Pages;

use App\Models\SharedUrl;
use App\Models\TerritoryAssignment;
use App\Filament\Resources\TerritoryAssignmentResource;
use Filament\Pages\Page;
use Filament\Infolists\Infolist;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Illuminate\Database\Eloquent\Model;

class ViewSharedAssignment extends Page implements HasInfolists
{
    use InteractsWithInfolists;

    protected static ?string $navigationIcon = 'heroicon-o-share';
    
    protected static string $view = 'filament.guest.pages.view-shared-assignment';
    
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

    public function assignmentInfolist(Infolist $infolist): Infolist
    {
        return TerritoryAssignmentResource::infolist($infolist)->record($this->assignment);
    }

    public static function getRoutePath(): string
    {
        return '/shared-assignment/{record}';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
