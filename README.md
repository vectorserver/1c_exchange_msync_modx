# MODX revo | mSync Cron Integration

Инструкция по настройке автоматического импорта товаров для компонента mSync на MODX Revolution https://modstore.pro/packages/import-and-export/msync.

## Установка

1. Перейдите в директорию: `assets/components/msync/`
2. Найдите там существующий файл `1c_exchange.php`.
3. **Замените** его содержимое новым кодом (обновленной версией скрипта с поддержкой CLI).

> **Важно:** Перед заменой рекомендуется сделать резервную копию оригинального файла.

## Настройка Cron

Для автоматического запуска синхронизации добавьте задачу в планировщик вашего хостинга (Cron). 

### Команда запуска:
```bash
php ~/www/assets/components/msync/1c_exchange.php

## Настройка путей к файлам

Внутри файла `1c_exchange.php` найдите массив `$datafile`. Вам необходимо убедиться, что пути соответствуют вашей структуре папок, куда 1С выгружает XML-файлы. 

По умолчанию настроено на стандартную временную папку mSync:

```php
$datafile = [
    // 'Имя_файла_в_процессоре' => 'Полный_путь_на_сервере'
    'import.xml' => MODX_ASSETS_PATH . 'components/msync/1c_temp/import.xml',
    'offers.xml' => MODX_ASSETS_PATH . 'components/msync/1c_temp/offers.xml',
];
