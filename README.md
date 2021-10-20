## Laravel Scout driver

Scout driver เพิ่มเติมสำหรับ Laravel

ตอนนี้มี driver ที่ support คือ
- Enterprise search - App Search
  - (https://www.elastic.co/app-search/)

## ติดตั้ง
```
composer require farzai/laravel-scout-driver
```


### Enterprise search

คุณต้องติดตั้ง Enterprise search client ก่อน

โดยรันคำสั่ง

```
php artisan farzai:scout:install app-search
```
หรือติดตั้งเองโดยใช้คำสั่ง
```
composer require elastic/enterprise-search
```

จากนั้นเปลี่ยน SCOUT_DRIVER ใน .env ของท่าน
```dotenv
SCOUT_DRIVER=app-search

APPSEARCH_ENDPOINT=<YOUR_ENDPOINT>
APPSEARCH_API_KEY=<YOUR_API_KEY>

# (Option)
APPSEARCH_ENGINE_LANGUAGE=th
```


### (Optional)
หากต้องการแก้ไข config คุณสามารถเพิ่ม Config ที่ config/scout.php
```php

return [

    // ....

    'app-search' => [
        'endpoint' => env('APPSEARCH_ENDPOINT'),
        'key' => env('APPSEARCH_API_KEY'),
        'engine' => [
            'language' => env('APPSEARCH_ENGINE_LANGUAGE'),
        ],
    ],
];
```