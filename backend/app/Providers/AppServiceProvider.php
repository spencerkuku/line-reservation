<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Cache;
use App\Models\Reservation;
use App\Observers\ReservationObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost:3000';
            return $frontendUrl."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        // 註冊模型觀察者
        Reservation::observe(ReservationObserver::class);

        // 記錄應用啟動時間（如果尚未設置）
        if (!Cache::has('app_start_time')) {
            Cache::forever('app_start_time', now());
        }
    }
}
