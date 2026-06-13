# Backend

LINE Reservation Platform 的 Laravel 12 API。

主要內容包含多租戶 middleware 與 global scope、Sanctum token 認證、LINE webhook、預約與客戶領域邏輯、資料庫 migration，以及 PHPUnit Feature tests。

## Setup

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

執行測試與格式檢查：

```bash
php artisan test
./vendor/bin/pint --test
```

完整專案說明請參閱 [根目錄 README](../README.md) 與 [API 文件](../docs/api/API_DOCS.md)。
