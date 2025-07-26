<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;

class RecalculateCustomerStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customers:recalculate-stats 
                           {--customer= : 指定客戶ID，僅重新計算特定客戶}
                           {--dry-run : 僅顯示結果，不實際更新資料庫}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '重新計算客戶統計數據（預約次數和總消費金額）';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $customerId = $this->option('customer');
        $dryRun = $this->option('dry-run');

        if ($customerId) {
            // 重新計算特定客戶
            $customer = Customer::find($customerId);
            
            if (!$customer) {
                $this->error("客戶 ID {$customerId} 不存在");
                return Command::FAILURE;
            }

            $this->recalculateCustomer($customer, $dryRun);
        } else {
            // 重新計算所有客戶
            $this->recalculateAllCustomers($dryRun);
        }

        return Command::SUCCESS;
    }

    /**
     * 重新計算單一客戶統計
     */
    private function recalculateCustomer(Customer $customer, bool $dryRun = false)
    {
        $oldReservations = $customer->total_reservations;
        $oldSpent = $customer->total_spent;

        // 計算正確的統計數據
        $confirmedReservations = $customer->reservations()
            ->where('status', 'confirmed')
            ->with('service')
            ->get();

        $newReservations = $confirmedReservations->count();
        $newSpent = $confirmedReservations->sum(function($reservation) {
            return $reservation->service ? $reservation->service->price : 0;
        });

        $this->info("客戶: {$customer->name} (ID: {$customer->id})");
        $this->line("  預約次數: {$oldReservations} -> {$newReservations}");
        $this->line("  總消費: NT$ " . number_format((float)$oldSpent, 2) . " -> NT$ " . number_format((float)$newSpent, 2));

        if (!$dryRun) {
            $customer->update([
                'total_reservations' => $newReservations,
                'total_spent' => $newSpent
            ]);
            $this->line("  ✓ 已更新");
        } else {
            $this->line("  [模擬模式] 未實際更新");
        }

        $this->line("");
    }

    /**
     * 重新計算所有客戶統計
     */
    private function recalculateAllCustomers(bool $dryRun = false)
    {
        $customers = Customer::with(['reservations.service'])->get();
        $total = $customers->count();

        $this->info("開始重新計算 {$total} 位客戶的統計數據...");
        $this->line("");

        if ($dryRun) {
            $this->warn("⚠️  模擬模式：僅顯示結果，不會實際更新資料庫");
            $this->line("");
        }

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        $updatedCount = 0;
        $errorCount = 0;

        foreach ($customers as $customer) {
            try {
                $oldReservations = $customer->total_reservations;
                $oldSpent = $customer->total_spent;

                // 計算正確的統計數據
                $confirmedReservations = $customer->reservations
                    ->where('status', 'confirmed');

                $newReservations = $confirmedReservations->count();
                $newSpent = $confirmedReservations->sum(function($reservation) {
                    return $reservation->service ? $reservation->service->price : 0;
                });

                // 只有數據不同時才更新
                if ($oldReservations != $newReservations || (float)$oldSpent != (float)$newSpent) {
                    if (!$dryRun) {
                        $customer->update([
                            'total_reservations' => $newReservations,
                            'total_spent' => $newSpent
                        ]);
                    }
                    $updatedCount++;
                }

                $progressBar->advance();
            } catch (\Exception $e) {
                $errorCount++;
                $this->error("更新客戶 {$customer->id} 失敗: " . $e->getMessage());
            }
        }

        $progressBar->finish();
        $this->line("");
        $this->line("");

        if ($dryRun) {
            $this->info("模擬完成!");
            $this->info("需要更新的客戶數量: {$updatedCount}");
        } else {
            $this->info("重新計算完成!");
            $this->info("已更新的客戶數量: {$updatedCount}");
        }

        if ($errorCount > 0) {
            $this->warn("發生錯誤的客戶數量: {$errorCount}");
        }
    }
}
