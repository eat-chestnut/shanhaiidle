<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquipmentSetResource\Pages;
use App\Models\EquipmentSet;
use App\Support\AdminOptions;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class EquipmentSetResource extends Resource
{
    protected static ?string $model = EquipmentSet::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = '套装配置';

    protected static ?string $pluralModelLabel = '套装配置';

    protected static ?string $modelLabel = '套装配置';

    protected static ?string $navigationGroup = '装备成长';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('EquipmentSetTabs')
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('基础信息')
                        ->schema([
                            Section::make('套装基础')
                                ->description('按 20 / 40 / 60 三级套装配置，阈值数量会在保存前严格校验。')
                                ->schema([
                                    TextInput::make('id')->label('套装 ID')->required()->maxLength(64)->unique(ignoreRecord: true)->disabled(fn (?EquipmentSet $record): bool => $record !== null),
                                    TextInput::make('set_line_id')->label('套装线 ID')->required()->maxLength(64),
                                    TextInput::make('name')->label('名称')->required()->maxLength(255),
                                    TextInput::make('sect')->label('所属宗门')->maxLength(64),
                                    Select::make('flow_tag')->label('流派')->options(AdminOptions::flowOptions())->searchable()->nullable(),
                                    Select::make('stage')->label('阶段')->required()->options([20 => '20级套装', 40 => '40级套装', 60 => '60级套装'])->live(),
                                    TextInput::make('piece_count')->label('套装总件数')->integer()->required()->default(4)->minValue(2)->maxValue(8),
                                    TextInput::make('max_pieces')->label('兼容总件数')->integer()->required()->default(4)->minValue(2)->maxValue(10)->helperText('旧字段兼容，建议与总件数一致。'),
                                    MultiSelect::make('slot_ids')->label('套装位')->options(AdminOptions::slotOptions())->searchable()->preload()->helperText('固定套装位请直接从预设部位中选择。'),
                                    Textarea::make('description')->label('说明')->rows(3)->columnSpanFull(),
                                    Toggle::make('is_enabled')->label('启用')->default(true),
                                    TextInput::make('sort_order')->label('排序')->required()->integer()->minValue(0)->default(0),
                                ])
                                ->columns(3),
                        ]),
                    Tab::make('阈值与加成')
                        ->schema([
                            Section::make('阈值配置')
                                ->description('20级建议 2/4，40级建议 2/4/6，60级建议 2/4/6/8。')
                                ->schema([
                                    Repeater::make('thresholds')
                                        ->label('阈值列表')
                                        ->default([])
                                        ->minItems(1)
                                        ->reorderableWithButtons()
                                        ->schema([
                                            TextInput::make('count')->label('阈值件数')->required()->integer()->minValue(1)->maxValue(fn (Get $get): int => max(1, (int) $get('../../../piece_count'))),
                                            Repeater::make('bonuses')
                                                ->label('加成列表')
                                                ->default([])
                                                ->minItems(1)
                                                ->schema([
                                                    Select::make('type')->label('加成类型')->required()->options(static::bonusTypeOptions()),
                                                    Select::make('stat')->label('属性')->options(AdminOptions::statOptions())->required(fn (Get $get): bool => $get('type') === 'stat')->hidden(fn (Get $get): bool => $get('type') !== 'stat')->dehydrated(fn (Get $get): bool => $get('type') === 'stat')->searchable(),
                                                    Select::make('skill_id')->label('技能')->options(fn (): array => AdminOptions::skillOptions())->searchable()->required(fn (Get $get): bool => $get('type') === 'skill_level')->hidden(fn (Get $get): bool => $get('type') !== 'skill_level')->dehydrated(fn (Get $get): bool => $get('type') === 'skill_level'),
                                                    TextInput::make('val')->label('数值')->required()->integer()->minValue(0)->default(0),
                                                ])
                                                ->columns(4)
                                                ->collapsible()
                                                ->columnSpanFull(),
                                        ])
                                        ->columns(2)
                                        ->collapsible()
                                        ->columnSpanFull(),
                                ]),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('套装 ID')->searchable()->sortable(),
                TextColumn::make('set_line_id')->label('套装线')->searchable()->sortable(),
                TextColumn::make('name')->label('名称')->searchable()->sortable(),
                TextColumn::make('flow_tag')->label('流派')->formatStateUsing(fn (?string $state): string => AdminOptions::optionLabel(AdminOptions::flowOptions(), $state))->toggleable(),
                TextColumn::make('stage')->label('阶段')->numeric()->sortable(),
                TextColumn::make('piece_count')->label('件数')->numeric()->sortable(),
                ToggleColumn::make('is_enabled')->label('启用')->sortable(),
                TextColumn::make('sort_order')->label('排序')->numeric()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('stage')->label('阶段')->options([20 => '20级', 40 => '40级', 60 => '60级']),
                Tables\Filters\SelectFilter::make('flow_tag')->label('流派')->options(AdminOptions::flowOptions()),
                Tables\Filters\TernaryFilter::make('is_enabled')->label('启用'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEquipmentSets::route('/'),
            'create' => Pages\CreateEquipmentSet::route('/create'),
            'edit' => Pages\EditEquipmentSet::route('/{record}/edit'),
        ];
    }

    public static function normalizeThresholdsInput(mixed $raw): array
    {
        $rows = [];
        if (! is_array($raw)) {
            return $rows;
        }

        foreach ($raw as $thresholdRaw) {
            if (! is_array($thresholdRaw)) {
                continue;
            }
            $count = (int) ($thresholdRaw['count'] ?? 0);
            $bonuses = [];
            $bonusRows = $thresholdRaw['bonuses'] ?? [];
            if (is_array($bonusRows)) {
                foreach ($bonusRows as $bonusRaw) {
                    if (! is_array($bonusRaw)) {
                        continue;
                    }
                    $type = trim((string) ($bonusRaw['type'] ?? ''));
                    $val = max(0, (int) ($bonusRaw['val'] ?? 0));
                    if ($type === 'stat') {
                        $stat = trim((string) ($bonusRaw['stat'] ?? ''));
                        if ($stat === '') {
                            continue;
                        }
                        $bonuses[] = [
                            'type' => 'stat',
                            'stat' => $stat,
                            'val' => $val,
                        ];
                    } elseif ($type === 'skill_level') {
                        $skillId = trim((string) ($bonusRaw['skill_id'] ?? ''));
                        if ($skillId === '') {
                            continue;
                        }
                        $bonuses[] = [
                            'type' => 'skill_level',
                            'skill_id' => $skillId,
                            'val' => $val,
                        ];
                    }
                }
            }

            $rows[] = [
                'count' => $count,
                'bonuses' => array_values($bonuses),
            ];
        }

        return array_values($rows);
    }

    public static function validateThresholdsOrFail(array $thresholds, int $maxPieces, ?int $stage = null): void
    {
        if ($thresholds === []) {
            throw ValidationException::withMessages([
                'thresholds' => '阈值至少需要 1 行。',
            ]);
        }

        if ($stage !== null) {
            $expectedPieces = match ($stage) {
                20 => 4,
                40 => 6,
                60 => 8,
                default => 0,
            };
            if ($expectedPieces > 0 && $maxPieces !== $expectedPieces) {
                throw ValidationException::withMessages([
                    'piece_count' => sprintf('%d级套装总件数必须为 %d。', $stage, $expectedPieces),
                ]);
            }
        }

        $statOptions = AdminOptions::statOptions();
        $seenCounts = [];
        $lastCount = 0;

        foreach ($thresholds as $idx => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages([
                    "thresholds.{$idx}" => '阈值行格式错误。',
                ]);
            }

            $count = (int) ($row['count'] ?? 0);
            if ($count < 1 || $count > $maxPieces) {
                throw ValidationException::withMessages([
                    "thresholds.{$idx}.count" => "阈值件数需在 1~{$maxPieces}。",
                ]);
            }
            if (in_array($count, $seenCounts, true)) {
                throw ValidationException::withMessages([
                    "thresholds.{$idx}.count" => '阈值件数不能重复。',
                ]);
            }
            if ($count <= $lastCount) {
                throw ValidationException::withMessages([
                    "thresholds.{$idx}.count" => '阈值件数必须按递增顺序填写。',
                ]);
            }
            $seenCounts[] = $count;
            $lastCount = $count;

            $bonuses = $row['bonuses'] ?? [];
            if (! is_array($bonuses) || count($bonuses) < 1) {
                throw ValidationException::withMessages([
                    "thresholds.{$idx}.bonuses" => '每个阈值至少配置 1 条加成。',
                ]);
            }

            foreach ($bonuses as $bonusIdx => $bonus) {
                if (! is_array($bonus)) {
                    throw ValidationException::withMessages([
                        "thresholds.{$idx}.bonuses.{$bonusIdx}" => '加成格式错误。',
                    ]);
                }
                $type = (string) ($bonus['type'] ?? '');
                $val = (int) ($bonus['val'] ?? -1);
                if ($val < 0) {
                    throw ValidationException::withMessages([
                        "thresholds.{$idx}.bonuses.{$bonusIdx}.val" => '加成数值必须大于等于 0。',
                    ]);
                }
                if ($type === 'stat') {
                    $stat = (string) ($bonus['stat'] ?? '');
                    if ($stat === '' || ! array_key_exists($stat, $statOptions)) {
                        throw ValidationException::withMessages([
                            "thresholds.{$idx}.bonuses.{$bonusIdx}.stat" => '请选择合法的属性。',
                        ]);
                    }
                } elseif ($type === 'skill_level') {
                    $skillId = trim((string) ($bonus['skill_id'] ?? ''));
                    if ($skillId === '') {
                        throw ValidationException::withMessages([
                            "thresholds.{$idx}.bonuses.{$bonusIdx}.skill_id" => '技能等级加成必须选择技能。',
                        ]);
                    }
                } else {
                    throw ValidationException::withMessages([
                        "thresholds.{$idx}.bonuses.{$bonusIdx}.type" => '请选择合法的加成类型。',
                    ]);
                }
            }
        }
    }

    private static function bonusTypeOptions(): array
    {
        return [
            'stat' => '属性加成',
            'skill_level' => '技能等级',
        ];
    }
}
