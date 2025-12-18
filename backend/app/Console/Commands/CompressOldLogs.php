<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class CompressOldLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:compress 
                            {--days=7 : 壓縮幾天前的日誌}
                            {--delete-compressed=30 : 刪除幾天前的壓縮檔}
                            {--dry-run : 模擬執行，不實際壓縮}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '壓縮舊的日誌檔案以節省空間';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $daysOld = $this->option('days');
        $deleteAfter = $this->option('delete-compressed');
        $dryRun = $this->option('dry-run');

        $this->info("開始處理日誌壓縮...");
        $this->info("壓縮 {$daysOld} 天前的日誌");
        $this->info("刪除 {$deleteAfter} 天前的壓縮檔");
        
        if ($dryRun) {
            $this->warn("【模擬模式】不會實際執行壓縮");
        }

        $logsPath = storage_path('logs');
        $compressedCount = 0;
        $deletedCount = 0;
        $savedSpace = 0;

        // 取得所有 .log 檔案
        $logFiles = File::glob($logsPath . '/*.log');

        // 處理日誌壓縮
        foreach ($logFiles as $logFile) {
            $fileName = basename($logFile);
            
            // 跳過當天的日誌檔案（例如：laravel.log, linebot.log）
            if (!preg_match('/\-\d{4}\-\d{2}\-\d{2}\.log$/', $fileName)) {
                continue;
            }

            // 檢查檔案修改時間
            $fileModified = Carbon::createFromTimestamp(filemtime($logFile));
            $daysSinceModified = $fileModified->diffInDays(now());

            if ($daysSinceModified >= $daysOld) {
                $gzFile = $logFile . '.gz';

                // 如果已經壓縮過，跳過
                if (File::exists($gzFile)) {
                    continue;
                }

                $originalSize = File::size($logFile);

                if (!$dryRun) {
                    // 壓縮檔案
                    $this->compressFile($logFile, $gzFile);
                    
                    // 驗證壓縮成功後刪除原檔案
                    if (File::exists($gzFile) && File::size($gzFile) > 0) {
                        File::delete($logFile);
                        $compressedSize = File::size($gzFile);
                        $savedSpace += ($originalSize - $compressedSize);
                        $compressedCount++;
                        
                        $this->line("✓ 壓縮: {$fileName} (" . $this->formatBytes($originalSize) . " → " . $this->formatBytes($compressedSize) . ")");
                    }
                } else {
                    $this->line("○ 將壓縮: {$fileName} (" . $this->formatBytes($originalSize) . ")");
                    $compressedCount++;
                }
            }
        }

        // 處理舊壓縮檔案刪除
        $gzFiles = File::glob($logsPath . '/*.gz');
        
        foreach ($gzFiles as $gzFile) {
            $fileModified = Carbon::createFromTimestamp(filemtime($gzFile));
            $daysSinceModified = $fileModified->diffInDays(now());

            if ($daysSinceModified >= $deleteAfter) {
                $fileName = basename($gzFile);
                $fileSize = File::size($gzFile);

                if (!$dryRun) {
                    File::delete($gzFile);
                    $deletedCount++;
                    $this->line("✗ 刪除: {$fileName} (" . $this->formatBytes($fileSize) . ")");
                } else {
                    $this->line("○ 將刪除: {$fileName} (" . $this->formatBytes($fileSize) . ")");
                    $deletedCount++;
                }
            }
        }

        // 統計資訊
        $this->newLine();
        $this->info("處理完成！");
        $this->table(
            ['項目', '數量'],
            [
                ['壓縮檔案', $compressedCount],
                ['刪除壓縮檔', $deletedCount],
                ['節省空間', $this->formatBytes($savedSpace)],
            ]
        );

        if ($dryRun) {
            $this->warn("這是模擬執行結果，實際執行請移除 --dry-run 選項");
        }

        return 0;
    }

    /**
     * 壓縮檔案
     */
    private function compressFile($source, $destination)
    {
        $bufferSize = 4096;
        $file = fopen($source, 'rb');
        $gzFile = gzopen($destination, 'wb9'); // 9 = 最高壓縮率

        while (!feof($file)) {
            gzwrite($gzFile, fread($file, $bufferSize));
        }

        fclose($file);
        gzclose($gzFile);
    }

    /**
     * 格式化檔案大小
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
