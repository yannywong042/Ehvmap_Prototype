<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LogViewer extends Page implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $title = 'Logs';

    protected string $view = 'filament.pages.log-viewer';

    private const ALLOWED_EMAIL = 'jordan.voets@gmail.com';

    public static function canAccess(): bool
    {
        return Str::lower(auth()->user()?->email ?? '') === self::ALLOWED_EMAIL;
    }

    /**
     * Only the tail of the log file is read, to avoid loading huge files into memory.
     */
    private const MAX_BYTES = 5 * 1024 * 1024;

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (
                ?string $search,
                array $filters,
                ?string $sortDirection,
                int $page,
                int $recordsPerPage,
            ): LengthAwarePaginator => $this->paginateEntries($search, $filters, $sortDirection, $page, $recordsPerPage))
            ->columns([
                TextColumn::make('logged_at')
                    ->label('Time')
                    ->sortable(),
                TextColumn::make('level')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'emergency', 'alert', 'critical', 'error' => 'danger',
                        'warning' => 'warning',
                        'notice', 'info' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('message')
                    ->limit(120)
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('level')
                    ->options([
                        'emergency' => 'Emergency',
                        'alert' => 'Alert',
                        'critical' => 'Critical',
                        'error' => 'Error',
                        'warning' => 'Warning',
                        'notice' => 'Notice',
                        'info' => 'Info',
                        'debug' => 'Debug',
                    ]),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::Eye)
                    ->modalHeading(fn (array $record): string => "{$record['level']} — {$record['logged_at']}")
                    ->modalContent(fn (array $record) => view('filament.pages.log-entry-modal', [
                        'entry' => $record['raw'],
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->searchable()
            ->defaultSort('logged_at', 'desc')
            ->paginated([25, 50, 100]);
    }

    private function paginateEntries(
        ?string $search,
        array $filters,
        ?string $sortDirection,
        int $page,
        int $recordsPerPage,
    ): LengthAwarePaginator {
        $entries = $this->readEntries();

        if (filled($level = $filters['level']['value'] ?? null)) {
            $entries = $entries->where('level', $level);
        }

        if (filled($search)) {
            $needle = Str::lower($search);
            $entries = $entries->filter(
                fn (array $entry): bool => str_contains(Str::lower($entry['raw']), $needle),
            );
        }

        $entries = $sortDirection === 'asc'
            ? $entries->sortBy('logged_at')
            : $entries->sortByDesc('logged_at');

        $entries = $entries->values();

        $page = max($page, 1);

        return new LengthAwarePaginator(
            $entries->forPage($page, $recordsPerPage)->values(),
            $entries->count(),
            $recordsPerPage,
            $page,
        );
    }

    /**
     * @return Collection<int, array{logged_at: string, level: string, message: string, raw: string}>
     */
    private function readEntries(): Collection
    {
        $path = storage_path('logs/laravel.log');

        if (! is_file($path)) {
            return collect();
        }

        $size = filesize($path);
        $handle = fopen($path, 'r');

        if ($size > self::MAX_BYTES) {
            fseek($handle, $size - self::MAX_BYTES);
        }

        $contents = stream_get_contents($handle);
        fclose($handle);

        $entries = collect();
        $current = null;

        foreach (explode("\n", $contents) as $line) {
            if (preg_match(
                '/^\[(?<date>\d{4}-\d{2}-\d{2}[^]]+)]\s+(?<channel>[\w-]+)\.(?<level>\w+):\s*(?<message>.*)$/',
                $line,
                $matches,
            )) {
                if ($current !== null) {
                    $entries->push($current);
                }

                $current = [
                    'logged_at' => $matches['date'],
                    'level' => Str::lower($matches['level']),
                    'message' => $matches['message'],
                    'raw' => $line,
                ];

                continue;
            }

            if ($current !== null) {
                $current['message'] .= "\n{$line}";
                $current['raw'] .= "\n{$line}";
            }
        }

        if ($current !== null) {
            $entries->push($current);
        }

        return $entries;
    }
}
