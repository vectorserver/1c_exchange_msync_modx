<?php
ini_set('display_errors', 1);
ini_set('error_reporting', 1);



define('MODX_API_MODE', true);
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/index.php';

$modx->getService('error', 'error.modError');
$modx->getRequest();
$modx->setLogLevel(modX::LOG_LEVEL_ERROR);
$modx->setLogTarget('FILE');
$modx->error->message = null;

/* @var mSync $mSync */
$mSync = $modx->getService('msync', 'mSync', $modx->getOption('msync_core_path', null, $modx->getOption('core_path') . 'components/msync/') . 'model/msync/', array());
if ($modx->error->hasError() || !($mSync instanceof mSync)) {
    die('Error');
}
$mSync->initialize('web', array('json_response' => true));

if (php_sapi_name() == "cli") {

    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    ob_implicit_flush(true);

    set_time_limit(0);
    ini_set('memory_limit', '1512M');

    // Инициализация пользователя
    $modx->user = $modx->getObject('modUser', ['sudo' => 1]);
    $_SERVER['REQUEST_URI'] = "/assets/components/msync/1c_exchange.php";

    //Блокировка от параллельного запуска
    $lockFile = MODX_ASSETS_PATH . 'components/msync/1c_temp/sync.lock';
    $fp = fopen($lockFile, "w+");
    if (!flock($fp, LOCK_EX | LOCK_NB)) {
        exit("Скрипт уже запущен.\n");
    }

    $datafile = [
        'import.xml' => MODX_ASSETS_PATH . 'components/msync/1c_temp/import.xml',
        'offers.xml' => MODX_ASSETS_PATH . 'components/msync/1c_temp/offers.xml',
    ];

    $options = [
        'processors_path' => $modx->getOption('msync.core_path', null, $modx->getOption('core_path') . 'components/msync/') . 'processors/'
    ];

    echo "Начало импорта: " . date('Y-m-d H:i:s') . "\n";
    echo "-------------------------------------------\n";

    foreach ($datafile as $file => $fullPath) {
        if (!file_exists($fullPath)) {
            echo "Файл {$file} не найден, пропускаю...\n";
            continue;
        }

        $iter = 1;
        $processing = true;

        while ($processing) {
            $response = $modx->runProcessor('mgr/import/process', [
                'action'   => 'mgr/import/process',
                'mode'     => 'import',
                'filename' => $file
            ], $options);

            if ($response->isError()) {
                $errorMsg = $response->getMessage();
                echo "ОШИБКА ({$file}): " . $errorMsg . "\n";
                $modx->log(modX::LOG_LEVEL_ERROR, "[mSync Cron] Error in {$file}: " . $errorMsg);
                $processing = false;
            } else {
                $result = $response->getObject();
                $message = isset($result['result']) ? str_ireplace(["\n", "\r"], " ", strip_tags($result['result'])) : '';

                echo "{$iter}\t" . date('H:i:s') . "\t{$file}\t{$message}\n";

                if ($response->response['message'] !== 'progress') {
                    $processing = false;
                    echo "Завершен импорт файла: {$file}\n\n";
                }
            }
            $iter++;
        }
    }

    // Финализация
    $modx->cacheManager->refresh();
    echo "-------------------------------------------\n";
    echo "Синхронизация завершена. Кэш очищен.\n";

    flock($fp, LOCK_UN);
    fclose($fp);
    @unlink($lockFile);

    exit();
} else {
    if (empty($_REQUEST['type'])) {
        die('Access denied: empty type');
    } else {
        $type = $_REQUEST['type'];
        $mode = $_REQUEST['mode'];
    }

    if (!isset($_SERVER['PHP_AUTH_USER']) && !isset($_SERVER['PHP_AUTH_PW'])) {
        /*
         * Add support on FastCGI mode
         * RewriteCond %{HTTP:Authorization} !^$
         * RewriteRule ^(.*)$ $1?http_auth=%{HTTP:Authorization} [QSA]
         */
        if (isset($_GET['http_auth'])) {
            $d = base64_decode(substr($_GET['http_auth'], 6));
            list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', $d);
        }
    }
    $user = $_SERVER['PHP_AUTH_USER'];
    $password = $_SERVER['PHP_AUTH_PW'];


    $syncuser = $modx->getOption('msync_1c_sync_login');
    $syncpass = $modx->getOption('msync_1c_sync_pass');

    if (($user != $syncuser || $password != $syncpass)) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[mSync] Ошибка авторизации импорта, проверьте правильность логина и пароля.');
        echo "failure\n";
        exit;
    }


    switch ($type) {
        //Остатки
        case 'catalog':
            switch ($mode) {
                case 'checkauth':
                    $response = $mSync->catalog->checkauth();
                    break;
                case 'init':
                    $response = $mSync->catalog->init();
                    break;
                case 'file':
                    $response = $mSync->catalog->file(@$_REQUEST['filename'], @file_get_contents("php://input"));
                    break;
                case 'import':
                    $response = $mSync->catalog->import(@$_REQUEST['filename'], @file_get_contents("php://input"));
                    break;
                default:
            }
            break;

        //Заказы
        case 'sale':
            switch ($mode) {
                case 'checkauth':
                    $response = $mSync->sale->checkauth();
                    break;
                case 'init':
                    $response = $mSync->sale->init();
                    break;
                case 'query':
                    header("Content-type: text/xml; charset=windows-1251");
                    $response = $mSync->sale->query();
                    break;
                case 'success':
                    $response = $mSync->sale->success();
                    break;
                case 'file':
                    $response = $mSync->sale->file(@$_REQUEST['filename'], @file_get_contents("php://input"));
                    break;
                default:
            }
            break;
        default:
    }

    @session_write_close();
    exit($response);
}





