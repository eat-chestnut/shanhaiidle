<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdminAuditLogResource\Pages;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Services\AdminAuditService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdminAuditLogResource extends Resource
{
    protected static ?string $model = AdminAuditLog::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = '审计日志';

    protected static ?string $modelLabel = '审计日志';

    protected static ?string $pluralModelLabel = '审计日志';

    protected static string | \UnitEnum | null $navigationGroup = '玩家与运营';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('created_at_display')
                    ->label('时间'),
                TextInput::make('admin_user_display')
                    ->label('管理员'),
                TextInput::make('action_type_display')
                    ->label('动作类型'),
                TextInput::make('target_type_display')
                    ->label('目标类型'),
                TextInput::make('target_id')
                    ->label('目标 ID'),
                TextInput::make('status_display')
                    ->label('状态'),
                TextInput::make('summary')
                    ->label('摘要')
                    ->columnSpanFull(),
                Textarea::make('status_message')
                    ->label('状态说明')
                    ->rows(3)
                    ->columnSpanFull(),
                Textarea::make('payload_pretty')
                    ->label('Payload JSON')
                    ->rows(18)
                    ->columnSpanFull(),
                TextInput::make('ip_address')
                    ->label('IP'),
                Textarea::make('user_agent')
                    ->label('User Agent')
                    ->rows(4)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                TextColumn::make('adminUser.email')
                    ->label('管理员')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('action_type')
                    ->label('动作类型')
                    ->formatStateUsing(fn (?string $state): string => AdminAuditService::actionTypeOptions()[$state] ?? (string) $state)
                    ->badge()
                    ->sortable(),
                TextColumn::make('target_type')
                    ->label('目标类型')
                    ->formatStateUsing(fn (?string $state): string => AdminAuditService::targetTypeOptions()[$state] ?? (string) $state)
                    ->sortable(),
                TextColumn::make('target_id')
                    ->label('目标 ID')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'success' ? 'success' : 'danger')
                    ->sortable(),
                TextColumn::make('summary')
                    ->label('摘要')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('admin_user_id')
                    ->label('管理员')
                    ->options(fn (): array => User::query()
                        ->orderBy('email')
                        ->get()
                        ->mapWithKeys(fn (User $user): array => [$user->getKey() => (string) $user->email])
                        ->all()),
                Tables\Filters\SelectFilter::make('action_type')
                    ->label('动作类型')
                    ->options(AdminAuditService::actionTypeOptions()),
                Tables\Filters\SelectFilter::make('target_type')
                    ->label('目标类型')
                    ->options(AdminAuditService::targetTypeOptions()),
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        'success' => '成功',
                        'failed' => '失败',
                    ]),
                Tables\Filters\Filter::make('target_id')
                    ->label('目标玩家')
                    ->form([
                        TextInput::make('target_id')
                            ->label('目标 ID')
                            ->placeholder('输入玩家 ID'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $targetId = trim((string) ($data['target_id'] ?? ''));
                        if ($targetId === '') {
                            return $query;
                        }

                        return $query->where('target_id', 'like', '%' . $targetId . '%');
                    }),
                Tables\Filters\Filter::make('created_at_range')
                    ->label('时间范围')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('created_from')->label('开始日期'),
                        \Filament\Forms\Components\DatePicker::make('created_until')->label('结束日期'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $createdFrom = $data['created_from'] ?? null;
                        $createdUntil = $data['created_until'] ?? null;

                        return $query
                            ->when($createdFrom, fn (Builder $builder): Builder => $builder->whereDate('created_at', '>=', $createdFrom))
                            ->when($createdUntil, fn (Builder $builder): Builder => $builder->whereDate('created_at', '<=', $createdUntil));
                    }),
            ])
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminAuditLogs::route('/'),
            'view' => Pages\ViewAdminAuditLog::route('/{record}'),
        ];
    }
}
