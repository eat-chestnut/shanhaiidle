<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SkillCatalogResource\Pages;
use App\Models\SkillCatalog;
use App\Support\AdminOptions;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class SkillCatalogResource extends Resource
{
    protected static ?string $model = SkillCatalog::class;
    protected static ?string $navigationIcon = 'heroicon-o-bolt';
    protected static ?string $navigationLabel = '技能字典';
    protected static ?string $pluralModelLabel = '技能字典';
    protected static ?string $modelLabel = '技能';
    protected static ?string $navigationGroup = '基础配置';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('SkillCatalogTabs')
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('基础信息')
                        ->schema([
                            Section::make('技能基础字段')
                                ->schema([
                                    TextInput::make('id')->label('技能 ID')->required()->maxLength(64)->unique(ignoreRecord: true)->disabled(fn (?SkillCatalog $record): bool => $record !== null),
                                    TextInput::make('name')->label('技能名称')->required()->maxLength(255),
                                    TextInput::make('sect')->label('宗门/流派')->maxLength(255)->datalist(array_values(AdminOptions::sectOptions()))->helperText('如果已有同类宗门，请优先选择已有名称保持一致。'),
                                    Toggle::make('is_enabled')->label('启用')->default(true),
                                    TextInput::make('sort_order')->label('排序')->required()->integer()->minValue(0)->default(0),
                                    Textarea::make('desc')->label('技能描述')->rows(4)->columnSpanFull(),
                                ])
                                ->columns(2),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('技能 ID')->searchable()->sortable(),
                TextColumn::make('name')->label('技能名称')->searchable()->sortable(),
                TextColumn::make('sect')->label('宗门/流派')->placeholder('—')->sortable(),
                ToggleColumn::make('is_enabled')->label('启用')->sortable(),
                TextColumn::make('sort_order')->label('排序')->numeric()->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_enabled')->label('启用状态'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSkillCatalogs::route('/'),
            'create' => Pages\CreateSkillCatalog::route('/create'),
            'edit' => Pages\EditSkillCatalog::route('/{record}/edit'),
        ];
    }
}
