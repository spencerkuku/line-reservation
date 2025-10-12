<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ActivityLogger
{
    /**
     * 記錄活動
     */
    public static function log(
        string $action,
        string $module,
        string $description,
        ?object $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        string $status = 'success',
        ?string $errorMessage = null
    ): ?AdminActivityLog {
        try {
            $user = Auth::user();
            $request = request();

            $log = AdminActivityLog::create([
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'user_email' => $user?->email,
                'action' => $action,
                'module' => $module,
                'description' => $description,
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id' => $subject?->id ?? null,
                'subject_data' => $subject ? $subject->toArray() : null,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'status' => $status,
                'error_message' => $errorMessage,
            ]);

            // 同時寫入檔案日誌
            Log::channel('activity')->info($description, [
                'action' => $action,
                'module' => $module,
                'user_id' => $user?->id,
                'subject' => $subject ? get_class($subject) . '#' . ($subject->id ?? 'N/A') : null,
            ]);

            return $log;
        } catch (\Exception $e) {
            Log::channel('error')->error('Failed to log activity: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * 記錄建立操作
     */
    public static function created(object $model, string $module): void
    {
        self::log(
            'created',
            $module,
            sprintf('建立 %s: %s', $module, self::getModelIdentifier($model)),
            $model,
            null,
            $model->getAttributes()
        );
    }

    /**
     * 記錄更新操作
     */
    public static function updated(object $model, string $module, array $oldValues): void
    {
        self::log(
            'updated',
            $module,
            sprintf('更新 %s: %s', $module, self::getModelIdentifier($model)),
            $model,
            $oldValues,
            $model->getAttributes()
        );
    }

    /**
     * 記錄刪除操作
     */
    public static function deleted(object $model, string $module): void
    {
        self::log(
            'deleted',
            $module,
            sprintf('刪除 %s: %s', $module, self::getModelIdentifier($model)),
            $model,
            $model->getAttributes(),
            null
        );
    }

    /**
     * 記錄登入
     */
    public static function login(User $user): void
    {
        self::log(
            'login',
            'auth',
            sprintf('用戶登入: %s (%s)', $user->name, $user->email),
            $user
        );
    }

    /**
     * 記錄登出
     */
    public static function logout(): void
    {
        $user = Auth::user();
        self::log(
            'logout',
            'auth',
            sprintf('用戶登出: %s (%s)', $user?->name ?? 'Unknown', $user?->email ?? 'Unknown'),
            $user
        );
    }

    /**
     * 記錄批次操作
     */
    public static function bulkAction(string $action, string $module, array $ids, string $description): void
    {
        self::log(
            $action,
            $module,
            $description,
            null,
            null,
            ['affected_ids' => $ids, 'count' => count($ids)]
        );
    }

    /**
     * 記錄失敗操作
     */
    public static function failed(string $action, string $module, string $description, \Throwable $exception): void
    {
        self::log(
            $action,
            $module,
            $description,
            null,
            null,
            null,
            'failed',
            $exception->getMessage()
        );
    }

    /**
     * 記錄自訂操作
     */
    public static function custom(string $action, string $module, string $description, ?array $data = null): void
    {
        self::log(
            $action,
            $module,
            $description,
            null,
            null,
            $data
        );
    }

    /**
     * 取得 Model 識別字
     */
    private static function getModelIdentifier(object $model): string
    {
        if (isset($model->name)) {
            return $model->name;
        }
        if (isset($model->title)) {
            return $model->title;
        }
        if (isset($model->email)) {
            return $model->email;
        }
        return "ID: " . ($model->id ?? 'N/A');
    }
}
