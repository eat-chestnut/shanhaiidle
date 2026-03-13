<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SkillCatalogResource\Pages;
use App\Models\SkillCatalog;
use App\Support\AdminOptions;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class SkillCatalogResource extends Resource
{
    protected static ?string $model = SkillCatalog::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-bolt';
    protected static ?string $navigationLabel = '技能字典';
    protected static ?string $pluralModelLabel = '技能字典';
    protected static ?string $modelLabel = '技能';
    protected static string | \UnitEnum | null $navigationGroup = '基础配置';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Tabs::make('SkillCatalogTabs')
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('基础信息')
                        ->schema([
                            Section::make('技能基础字段')
                                ->schema([
                                    TextInput::make('id')->label('技能 ID')->required()->maxLength(64)->unique(ignoreRecord: true)->disabled(fn (?SkillCatalog $record): bool => $record !== null),
                                    TextInput::make('name')->label('技能名称')->required()->maxLength(255),
                                    Select::make('class')->label('宗门')->required()->options(AdminOptions::skillClassOptions())->searchable(),
                                    Select::make('type')->label('技能类型')->required()->options(AdminOptions::skillTypeOptions())->default('active'),
                                    TextInput::make('min_level')->label('开放等级')->required()->integer()->minValue(1)->default(1),
                                    TextInput::make('max_level')->label('可升级上限')->required()->integer()->minValue(1)->default(15),
                                    Select::make('target_rule')->label('目标规则')->options(AdminOptions::skillTargetRuleOptions())->searchable(),
                                    TextInput::make('cd_sec')->label('冷却（秒）')->required()->numeric()->minValue(0)->default(0),
                                    TextInput::make('cost_qi')->label('耗气')->required()->integer()->minValue(0)->default(0),
                                    TextInput::make('cast_range')->label('施法距离')->numeric()->minValue(0),
                                    MultiSelect::make('tags')->label('标签')->options(AdminOptions::skillTagOptions())->searchable()->preload()->columnSpanFull(),
                                    Toggle::make('is_enabled')->label('启用')->default(true),
                                    TextInput::make('sort_order')->label('排序')->required()->integer()->minValue(0)->default(0),
                                    Textarea::make('desc')->label('技能描述')->rows(4)->columnSpanFull(),
                                    Textarea::make('runtime_blocks')
                                        ->label('高级效果 JSON')
                                        ->rows(18)
                                        ->helperText('填写 damage、debuffs、milestones、shield、dot 等高级运行时结构；留空表示无扩展块。')
                                        ->formatStateUsing(fn ($state): string => static::encodeJsonState($state))
                                        ->dehydrateStateUsing(fn ($state): array => static::decodeJsonState($state))
                                        ->rule(static function (): \Closure {
                                            return function (string $attribute, mixed $value, \Closure $fail): void {
                                                $text = trim((string) $value);
                                                if ($text === '') {
                                                    return;
                                                }

                                                $decoded = json_decode($text, true);
                                                if (! is_array($decoded) || array_is_list($decoded)) {
                                                    $fail('高级效果 JSON 必须是对象。');
                                                }
                                            };
                                        })
                                        ->columnSpanFull(),
                                ])
                                ->columns(3),
                        ]),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('技能 ID')->searchable()->sortable(),
                TextColumn::make('name')->label('技能名称')->searchable()->sortable(),
                TextColumn::make('class')->label('宗门')->formatStateUsing(fn (?string $state): string => AdminOptions::skillClassOptions()[$state ?? ''] ?? (string) ($state ?? '—'))->sortable(),
                TextColumn::make('type')->label('类型')->formatStateUsing(fn (?string $state): string => AdminOptions::skillTypeOptions()[$state ?? ''] ?? (string) ($state ?? '—')),
                TextColumn::make('min_level')->label('开放等级')->numeric()->sortable(),
                TextColumn::make('max_level')->label('上限')->numeric()->sortable(),
                ToggleColumn::make('is_enabled')->label('启用')->sortable(),
                TextColumn::make('sort_order')->label('排序')->numeric()->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_enabled')->label('启用状态'),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSkillCatalogs::route('/'),
            'create' => Pages\CreateSkillCatalog::route('/create'),
            'edit' => Pages\EditSkillCatalog::route('/{record}/edit'),
        ];
    }

    private static function encodeJsonState(mixed $state): string
    {
        if (! is_array($state) || $state === []) {
            return '';
        }

        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? '' : $json;
    }

    private static function decodeJsonState(mixed $state): array
    {
        $text = trim((string) $state);
        if ($text === '') {
            return [];
        }

        $decoded = json_decode($text, true);

        return is_array($decoded) && ! array_is_list($decoded) ? $decoded : [];
    }
}
