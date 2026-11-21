<?php

namespace App\Filament\Resources\Territories\Actions;

use App\Models\TerritoryAssignment;
use App\Models\User;
use App\Support\S13TemplateStructure;
use App\Support\TerritoryAssignmentRecord;
use App\Support\TerritoryAssignmentRecordPdf;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download the Territory Assignment Record (S-13) of a service year, written on the official template.
 *
 * The template is uploaded by a super administrator, the first time or to replace it (e.g. with a newer edition).
 */
class DownloadTerritoryAssignmentRecordAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'download_s13';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('s13.action.label'))
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->modalHeading(__('s13.action.modal_heading'))
            ->modalDescription(__('s13.action.modal_description'))
            ->modalSubmitActionLabel(__('s13.action.submit'))
            ->modalWidth('lg')
            ->authorize(fn (): bool => Gate::allows('viewAny', TerritoryAssignment::class))
            ->schema([
                Callout::make(__('s13.template.missing_heading'))
                    ->description(fn (): string => self::canManageTemplate()
                        ? __('s13.template.missing_admin')
                        : __('s13.template.missing_user'))
                    ->warning()
                    ->visible(fn (): bool => ! TerritoryAssignmentRecordPdf::hasTemplate()),
                self::structureCallout(
                    'stored_structure_warning',
                    __('s13.structure.stored_heading'),
                    fn (): ?array => TerritoryAssignmentRecordPdf::hasTemplate() ? self::inspect(TerritoryAssignmentRecordPdf::template()) : null,
                ),
                Select::make('service_year')
                    ->label(__('s13.fields.service_year.label'))
                    ->helperText(__('s13.fields.service_year.helper'))
                    ->options(fn (): array => self::serviceYearOptions())
                    ->default(fn (): int => TerritoryAssignmentRecord::serviceYearOf())
                    ->required()
                    ->native(false),
                FileUpload::make('template')
                    ->label(fn (): string => TerritoryAssignmentRecordPdf::hasTemplate()
                        ? __('s13.fields.template.replace_label')
                        : __('s13.fields.template.label'))
                    ->helperText(__('s13.fields.template.helper'))
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(10240)
                    ->storeFiles(false)
                    ->required(fn (): bool => ! TerritoryAssignmentRecordPdf::hasTemplate())
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (! $value instanceof TemporaryUploadedFile) {
                            return;
                        }

                        if (! TerritoryAssignmentRecordPdf::isValidTemplate((string) $value->get())) {
                            $fail(__('s13.fields.template.invalid'));
                        }
                    })
                    ->live()
                    ->visible(fn (): bool => self::canManageTemplate()),
                self::structureCallout(
                    'uploaded_structure_warning',
                    __('s13.structure.uploaded_heading'),
                    fn (Get $get): ?array => self::inspectUpload($get('template')),
                ),
                Checkbox::make('accept_structure_changes')
                    ->label(__('s13.structure.accept'))
                    ->accepted()
                    ->validationMessages(['accepted' => __('s13.structure.accept_required')])
                    ->visible(fn (Get $get): bool => (self::inspectUpload($get('template'))['differences'] ?? []) !== []),
            ])
            ->action(function (array $data): ?StreamedResponse {
                $template = $data['template'] ?? null;

                if ($template instanceof TemporaryUploadedFile && self::canManageTemplate()) {
                    TerritoryAssignmentRecordPdf::storeTemplate((string) $template->get());
                }

                if (! TerritoryAssignmentRecordPdf::hasTemplate()) {
                    Notification::make()
                        ->title(__('s13.template.missing_heading'))
                        ->body(__('s13.template.missing_user'))
                        ->danger()
                        ->send();

                    return null;
                }

                $serviceYear = (int) $data['service_year'];
                $contents = TerritoryAssignmentRecordPdf::render($serviceYear);

                return response()->streamDownload(
                    function () use ($contents): void {
                        echo $contents;
                    },
                    TerritoryAssignmentRecordPdf::fileName($serviceYear),
                    ['Content-Type' => 'application/pdf'],
                );
            });
    }

    /**
     * A warning about a template whose structure differs from the expected one, with the link to report it.
     *
     * @param  Closure(Get): ?array{structure: array{pages: int, width: float, height: float, fields: list<string>}, differences: list<string>, file_name: ?string}  $inspection
     */
    protected static function structureCallout(string $key, string $heading, Closure $inspection): Callout
    {
        return Callout::make($heading)
            ->key($key)
            ->description(fn (Get $get): HtmlString => new HtmlString(
                e(__('s13.structure.description')).'<ul class="list-disc ps-5 mt-1">'
                .collect($inspection($get)['differences'] ?? [])->map(fn (string $difference): string => '<li>'.e($difference).'</li>')->implode('')
                .'</ul>'
            ))
            ->warning()
            ->actions([
                Action::make('report_structure_change')
                    ->label(__('s13.structure.report'))
                    ->icon(Heroicon::OutlinedBugAnt)
                    ->url(function (Get $get) use ($inspection): ?string {
                        $result = $inspection($get);

                        return $result !== null ? S13TemplateStructure::issueUrl($result['structure'], $result['differences'], $result['file_name']) : null;
                    })
                    ->openUrlInNewTab(),
            ])
            ->visible(fn (Get $get): bool => ($inspection($get)['differences'] ?? []) !== []);
    }

    /**
     * The structure of a template and its differences from the expected one (null if it is not a valid PDF).
     *
     * @return array{structure: array{pages: int, width: float, height: float, fields: list<string>}, differences: list<string>, file_name: ?string}|null
     */
    protected static function inspect(string $contents, ?string $fileName = null): ?array
    {
        // Parsed once per request, as the schema asks for it several times
        static $structures = [];
        $structure = $structures[md5($contents)] ??= TerritoryAssignmentRecordPdf::isValidTemplate($contents)
            ? S13TemplateStructure::fromPdf($contents)
            : false;

        if ($structure === false) {
            return null;
        }

        return ['structure' => $structure, 'differences' => S13TemplateStructure::differences($structure), 'file_name' => $fileName];
    }

    /**
     * Inspect the uploaded template, if any.
     *
     * @return array{structure: array{pages: int, width: float, height: float, fields: list<string>}, differences: list<string>, file_name: ?string}|null
     */
    protected static function inspectUpload(mixed $state): ?array
    {
        $file = is_array($state) ? collect($state)->first() : $state;

        return $file instanceof TemporaryUploadedFile ? self::inspect((string) $file->get(), $file->getClientOriginalName()) : null;
    }

    /**
     * Whether the current user can upload the template: only super administrators can.
     */
    public static function canManageTemplate(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->hasRole('super_admin');
    }

    /**
     * The service years from the one of the oldest assignment to the current one, newest first.
     *
     * @return array<int, string>
     */
    public static function serviceYearOptions(): array
    {
        $current = TerritoryAssignmentRecord::serviceYearOf();
        $oldestStart = TerritoryAssignment::query()->min('started_at');
        $oldest = $oldestStart !== null ? min($current, TerritoryAssignmentRecord::serviceYearOf(Carbon::parse($oldestStart))) : $current;

        return collect(range($current, $oldest))
            ->mapWithKeys(fn (int $year): array => [$year => __('s13.fields.service_year.option', [
                'year' => $year,
                'from' => TerritoryAssignmentRecord::serviceYearStart($year)->year,
                'to' => $year,
            ])])
            ->all();
    }
}
