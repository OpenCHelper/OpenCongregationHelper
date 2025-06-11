<x-filament-panels::page>
    <div class="space-y-6">
        <div class="fi-section-header-wrapper">
            <div class="fi-section-header flex flex-col gap-3">
                <div class="flex items-center gap-3">
                    <div class="grid flex-1 gap-y-1">
                        <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                            {{ __('filament-resources.shared_resource.details_title', ['resourceType' => $resourceType]) }}
                        </h3>
                        <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                            {{ __('filament-resources.shared_resource.shared_view_description', ['resourceType' => strtolower($resourceType)]) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        {{ $this->resourceInfolist }}
    </div>
</x-filament-panels::page>
