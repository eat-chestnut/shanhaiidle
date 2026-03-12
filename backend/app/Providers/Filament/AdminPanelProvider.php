<?php

namespace App\Providers\Filament;

use Filament\Support\Facades\FilamentView;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\App;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('山海巡厄录配置后台')
            ->maxContentWidth(Width::ScreenTwoExtraLarge)
            ->sidebarCollapsibleOnDesktop()
            ->bootUsing(function (): void {
                App::setLocale('zh_CN');

                FilamentView::registerRenderHook(
                    PanelsRenderHook::STYLES_AFTER,
                    fn (): string => <<<'HTML'
                        <style>
                            .compact-cost-items.fi-fo-repeater {
                                gap: .5rem;
                            }

                            .compact-cost-items .fi-fo-repeater-item {
                                border-radius: .75rem;
                                box-shadow: none;
                            }

                            .compact-cost-items .fi-fo-repeater-item-header {
                                padding: .375rem .625rem;
                                min-height: 2rem;
                            }

                            .compact-cost-items .fi-fo-repeater-item-content {
                                padding: .5rem .625rem;
                            }

                            .compact-cost-items .fi-fo-repeater-item-content .grid {
                                row-gap: .375rem;
                                column-gap: .5rem;
                            }

                            .compact-cost-items .fi-fo-field-wrp-helper-text {
                                display: none;
                            }
                        </style>
                    HTML,
                );
            })
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
