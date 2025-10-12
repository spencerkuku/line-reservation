<?php

namespace App\Logging;

use Monolog\Formatter\LineFormatter;
use Monolog\LogRecord;

class CustomFormatter extends LineFormatter
{
    public function __construct()
    {
        // 自訂日誌格式
        $format = "[%datetime%] [%level_name%] [%channel%] %message% %context% %extra%\n";
        $dateFormat = "Y-m-d H:i:s";
        
        parent::__construct($format, $dateFormat, true, true);
    }

    public function format(LogRecord $record): string
    {
        // 加入自訂欄位
        $record->extra['ip'] = request()->ip() ?? 'CLI';
        $record->extra['user_id'] = auth()->id() ?? 'guest';
        $record->extra['url'] = request()->fullUrl() ?? 'N/A';
        $record->extra['method'] = request()->method() ?? 'N/A';

        return parent::format($record);
    }
}
