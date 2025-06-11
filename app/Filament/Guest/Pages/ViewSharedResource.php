<?php

namespace App\Filament\Guest\Pages;

use App\Models\SharedUrl;
use Filament\Pages\Page;
use Filament\Infolists\Infolist;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Generic page to view shared resources.
 * 
 * This page is used to display resources shared via a unique URL that
 * do not have the `HasGuestViewPage` trait.
 * 
 * @see \App\Traits\HasGuestViewPage
 */
class ViewSharedResource extends Page implements HasInfolists
{
    use InteractsWithInfolists;

    protected static ?string $navigationIcon = 'heroicon-o-share';
    
    protected static string $view = 'filament.guest.pages.view-shared-resource';
    
    protected static bool $shouldRegisterNavigation = false;

    public SharedUrl $sharedUrl;
    public Model $shareableResource;
    public string $resourceType;

    public function mount(string $record): void
    {
        $this->sharedUrl = SharedUrl::where('uid', $record)->firstOrFail();
        
        if (!$this->sharedUrl->shareable) {
            abort(404, 'Shareable resource not found.');
        }
        
        $this->shareableResource = $this->sharedUrl->shareable;
        $this->resourceType = $this->getResourceModelLabel();
    }

    protected function getResourceModelLabel(): string
    {
        // Get the translated model label based on the shareable resource type
        return match (get_class($this->shareableResource)) {
            \App\Models\TerritoryAssignment::class => 
                __('filament-resources.territory_assignment.model_label'),
            \App\Models\Territory::class => 
                __('filament-resources.territory.model_label'),
            default => strtolower(class_basename($this->shareableResource))
        };
    }

    public function getTitle(): string
    {
        return $this->getResourceTitle();
    }

    protected function getResourceTitle(): string
    {
        // If defined, use the custom title method from the shareable resource
        if (method_exists($this->shareableResource, 'getSharedTitle')) {
            return $this->shareableResource->getSharedTitle();
        }

        return match (get_class($this->shareableResource)) {
            \App\Models\TerritoryAssignment::class => 
                Str::ucfirst(__('filament-resources.territory_assignment.model_label') . ': ' . $this->shareableResource->territory->name),
            \App\Models\Territory::class => 
                Str::ucfirst(__('filament-resources.territory.model_label') . ': ' . $this->shareableResource->name),
            default => ucfirst($this->resourceType) . ' details'
        };
    }

    public function resourceInfolist(Infolist $infolist): Infolist
    {
        return $this->getResourceInfolist($infolist)->record($this->shareableResource);
    }

    protected function getResourceInfolist(Infolist $infolist): Infolist
    {
        // Try to get the resource class and use its infolist
        $resourceClass = $this->getResourceClass();
        
        if ($resourceClass && method_exists($resourceClass, 'infolist')) {
            return $resourceClass::infolist($infolist);
        }

        // Fallback to a basic infolist
        return $infolist->schema([
            \Filament\Infolists\Components\TextEntry::make('id')
                ->label('ID'),
        ]);
    }

    /**
     * Get the resource class based on the shareable resource type.
     * 
     * This method returns the appropriate resource class for the shared resource,
     * allowing us to use the Filament resources for displaying details.
     */
    protected function getResourceClass(): ?string
    {
        return match (get_class($this->shareableResource)) {
            \App\Models\TerritoryAssignment::class => \App\Filament\Resources\TerritoryAssignmentResource::class,
            \App\Models\Territory::class => \App\Filament\Resources\TerritoryResource::class,
            default => null
        };
    }

    public static function getRoutePath(): string
    {
        return '/shared-resource/{record}';
    }
}
