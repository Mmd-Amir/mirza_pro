<?php

if (!defined('APP_ROOT_PATH')) {
    define('APP_ROOT_PATH', __DIR__);
}

$composerAutoload = APP_ROOT_PATH . '/vendor/autoload.php';
if (is_readable($composerAutoload)) {
    require_once $composerAutoload;
    unset($composerAutoload);
} else {
    error_log('Composer autoloader not found. Optional dependencies may be unavailable.');
    unset($composerAutoload);
}
require_once APP_ROOT_PATH . '/config.php';

ini_set('error_log', APP_ROOT_PATH . '/error_log');

function getDatabaseConnection()
{
    static $cachedPdo = null;

    if ($cachedPdo instanceof PDO) {
        return $cachedPdo;
    }

    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $cachedPdo = $GLOBALS['pdo'];
        return $cachedPdo;
    }

    $dsn = $GLOBALS['dsn'] ?? null;
    $username = $GLOBALS['usernamedb'] ?? null;
    $password = $GLOBALS['passworddb'] ?? null;
    $options = $GLOBALS['options'] ?? [];

    if (!is_string($dsn) || trim($dsn) === '') {
        error_log('getDatabaseConnection: DSN is not configured.');
        return null;
    }

    try {
        $newPdo = new PDO($dsn, (string) $username, (string) $password, is_array($options) ? $options : []);
        $GLOBALS['pdo'] = $newPdo;
        $cachedPdo = $newPdo;
        return $cachedPdo;
    } catch (PDOException $e) {
        error_log('getDatabaseConnection: Unable to create PDO instance. ' . $e->getMessage());
        return null;
    }
}

if (!defined('TRONADO_API_CONFIGURATION')) {
    $tronadoApiConfiguration = [
        'base_url' => 'https://bot.tronado.cloud',
        'order_token_path' => '/Order/GetOrderToken',
        'versions' => [
            'api/v1',
            'api/v2',
            'api/v3',
            'api',
            null,
        ],
    ];

    define('TRONADO_API_CONFIGURATION', $tronadoApiConfiguration);
    unset($tronadoApiConfiguration);
}

if (!defined('TRONADO_ORDER_TOKEN_ENDPOINTS')) {
    $tronadoConfig = TRONADO_API_CONFIGURATION;
    $baseUrl = rtrim((string) ($tronadoConfig['base_url'] ?? ''), '/');
    $path = '/' . ltrim((string) ($tronadoConfig['order_token_path'] ?? ''), '/');
    $versions = is_array($tronadoConfig['versions'] ?? null) ? $tronadoConfig['versions'] : [];

    $computedEndpoints = [];
    foreach ($versions as $version) {
        if ($baseUrl === '') {
            continue;
        }

        $versionSegment = $version !== null ? '/' . trim((string) $version, '/') : '';
        $computedEndpoints[] = $baseUrl . $versionSegment . $path;
    }

    if (!in_array(null, $versions, true)) {
        $computedEndpoints[] = $baseUrl . $path;
    }

    $computedEndpoints = array_values(array_unique(array_filter($computedEndpoints)));
    define('TRONADO_ORDER_TOKEN_ENDPOINTS', $computedEndpoints);
    unset($computedEndpoints, $baseUrl, $path, $versions, $tronadoConfig);
}

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\Label\LabelAlignment;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

#-----------shell helper utilities------------#
function isShellExecAvailable()
{
    static $isAvailable;

    if ($isAvailable !== null) {
        return $isAvailable;
    }

    if (!function_exists('shell_exec')) {
        $isAvailable = false;
        return $isAvailable;
    }

    $disabledFunctions = ini_get('disable_functions');
    if (!empty($disabledFunctions) && stripos($disabledFunctions, 'shell_exec') !== false) {
        $isAvailable = false;
        return $isAvailable;
    }

    $isAvailable = true;
    return $isAvailable;
}

if (!function_exists('safe_divide')) {

    function safe_divide($numerator, $denominator, $fallback = 0)
    {
        if (!is_numeric($numerator) || !is_numeric($denominator)) {
            return $fallback;
        }

        $denominator = (float) $denominator;
        if ($denominator == 0.0) {
            return $fallback;
        }

        $result = (float) $numerator / $denominator;

        if (!is_finite($result)) {
            return $fallback;
        }

        return $result;
    }
}

function generateReferralCode($length = 12)
{
    $length = max(1, (int) $length);
    $bytes = (int) ceil($length / 2);

    if (function_exists('random_bytes')) {
        try {
            $code = bin2hex(random_bytes($bytes));
            return substr($code, 0, $length);
        } catch (Exception $exception) {
            error_log('Falling back to pseudo-random referral code generator: ' . $exception->getMessage());
        } catch (Error $exception) {
            error_log('Falling back to pseudo-random referral code generator: ' . $exception->getMessage());
        }
    }

    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $maxIndex = strlen($characters) - 1;
    $code = '';

    for ($i = 0; $i < $length; ++$i) {
        if (function_exists('random_int')) {
            try {
                $index = random_int(0, $maxIndex);
            } catch (Exception $exception) {
                error_log('random_int failed, using mt_rand fallback: ' . $exception->getMessage());
                $index = mt_rand(0, $maxIndex);
            } catch (Error $exception) {
                error_log('random_int failed, using mt_rand fallback: ' . $exception->getMessage());
                $index = mt_rand(0, $maxIndex);
            }
        } else {
            $index = mt_rand(0, $maxIndex);
        }

        $code .= $characters[$index];
    }

    return $code;
}

function ensureUserInvitationCode($userId, $currentCode = null, $length = 12)
{
    if (!is_scalar($userId) || (string) $userId === '') {
        return null;
    }

    $currentCode = is_string($currentCode) ? trim($currentCode) : '';
    if ($currentCode !== '') {
        return $currentCode;
    }

    $newCode = generateReferralCode($length);
    update('user', 'codeInvitation', $newCode, 'id', (string) $userId);

    return $newCode;
}

if (!function_exists('applyConnectionPlaceholders')) {

    function applyConnectionPlaceholders($template, $subscriptionLink, $configList)
    {
        $trimmedSubscription = trim((string) $subscriptionLink);
        $trimmedConfigList = trim((string) $configList);

        $connectionSections = [];
        $configSection = '';
        $linksSection = '';

        if ($trimmedSubscription !== '') {
            $configSection = "🔗 لینک اتصال:\n\n<code>{$trimmedSubscription}</code>";
            $connectionSections['config'] = $configSection;
        }

        if ($trimmedConfigList !== '') {
            $linksSection = "🔐 کانفیگ اشتراک :\n\n<code>{$trimmedConfigList}</code>";
            $connectionSections['links'] = $linksSection;
        }

        $connectionLinksBlock = implode("\n\n", array_values($connectionSections));
        if ($connectionLinksBlock !== '') {
            $connectionLinksBlock .= "\n";
        }

        $hasConnectionLinksPlaceholder = strpos($template, '{connection_links}') !== false;
        $hasConfigPlaceholder = strpos($template, '{config}') !== false;
        $hasLinksPlaceholder = strpos($template, '{links}') !== false;

        $placeholderLabels = [
            '{config}' => [
                '🔗 لینک اتصال:',
                '🔗 لینک اتصال :',
                'لینک اتصال:',
                'لینک اتصال :',
            ],
            '{links}' => [
                '🔐 کانفیگ اشتراک:',
                '🔐 کانفیگ اشتراک :',
                'لینک اشتراک:',
                'لینک اشتراک :',
            ],
        ];

        $replacePlaceholder = function ($templateValue, $placeholder, $replacement) use ($placeholderLabels) {
            $wrappedPlaceholder = "<code>{$placeholder}</code>";
            $labels = $placeholderLabels[$placeholder] ?? [];
            $placeholderPattern = '(?:' . preg_quote($placeholder, '/') . '|' . preg_quote($wrappedPlaceholder, '/') . ')';

            foreach ($labels as $label) {
                $labelPattern = preg_quote($label, '/');
                $pattern = '/(^|\R)[^\S\r\n]*' . $labelPattern . '[^\S\r\n]*(?:\r?\n)?[^\S\r\n]*' . $placeholderPattern . '/u';
                $updatedTemplate = preg_replace($pattern, '$1' . $replacement, $templateValue, 1, $count);
                if ($count > 0) {
                    return $updatedTemplate;
                }
            }

            if (strpos($templateValue, $wrappedPlaceholder) !== false) {
                return str_replace($wrappedPlaceholder, $replacement, $templateValue);
            }

            return str_replace($placeholder, $replacement, $templateValue);
        };

        if ($hasConnectionLinksPlaceholder) {
            $template = str_replace('{connection_links}', $connectionLinksBlock, $template);

            if ($hasConfigPlaceholder) {
                $configReplacement = $configSection;
                if ($configReplacement !== '' && $linksSection !== '') {
                    $configReplacement .= "\n\n";
                }
                $template = $replacePlaceholder($template, '{config}', $configReplacement);
            }

            if ($hasLinksPlaceholder) {
                $template = $replacePlaceholder($template, '{links}', $linksSection);
            }
        } elseif ($hasConfigPlaceholder || $hasLinksPlaceholder) {
            if ($hasConfigPlaceholder && $hasLinksPlaceholder) {
                $configReplacement = $configSection;
                if ($configReplacement !== '' && $linksSection !== '') {
                    $configReplacement .= "\n\n";
                }

                $template = $replacePlaceholder($template, '{config}', $configReplacement);
                $template = $replacePlaceholder($template, '{links}', $linksSection);
            } elseif ($hasConfigPlaceholder) {
                $template = $replacePlaceholder($template, '{config}', $connectionLinksBlock);
            } else {
                $template = $replacePlaceholder($template, '{links}', $connectionLinksBlock);
            }
        }

        if (strpos($template, '{links2}') !== false) {
            $template = str_replace('{links2}', $trimmedSubscription, $template);
        }

        return $template;
    }
}

function getCrontabBinary()
{
    static $resolvedPath;

    if ($resolvedPath !== null) {
        return $resolvedPath ?: null;
    }

    $candidateDirectories = [
        '/usr/local/bin',
        '/usr/bin',
        '/bin',
        '/usr/sbin',
        '/sbin',
    ];

    $environmentPath = getenv('PATH');
    if ($environmentPath !== false && $environmentPath !== '') {
        foreach (explode(PATH_SEPARATOR, $environmentPath) as $pathDirectory) {
            $pathDirectory = trim($pathDirectory);
            if ($pathDirectory !== '' && !in_array($pathDirectory, $candidateDirectories, true)) {
                $candidateDirectories[] = $pathDirectory;
            }
        }
    }

    foreach ($candidateDirectories as $directory) {
        $executablePath = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'crontab';
        if (@is_file($executablePath) && @is_executable($executablePath)) {
            $resolvedPath = $executablePath;
            return $resolvedPath;
        }
    }

    if (isShellExecAvailable()) {
        $whichOutput = @shell_exec('command -v crontab 2>/dev/null');
        if (is_string($whichOutput)) {
            $whichOutput = trim($whichOutput);
            if ($whichOutput !== '' && @is_executable($whichOutput)) {
                $resolvedPath = $whichOutput;
                return $resolvedPath;
            }
        }
    }

    $resolvedPath = '';
    error_log('Unable to locate the crontab executable on this system.');

    return null;
}

function runShellCommand($command)
{
    if (!isShellExecAvailable()) {
        error_log('shell_exec is not available; unable to run command: ' . $command);
        return null;
    }

    if (getenv('PATH') === false || trim((string) getenv('PATH')) === '') {
        putenv('PATH=/usr/local/bin:/usr/bin:/bin');
    }

    return shell_exec($command);
}

function deleteDirectory($directory)
{
    if (!file_exists($directory)) {
        return true;
    }

    if (!is_dir($directory)) {
        return @unlink($directory);
    }

    $items = scandir($directory);
    if ($items === false) {
        return false;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            if (!deleteDirectory($path)) {
                return false;
            }
        } else {
            if (!@unlink($path)) {
                return false;
            }
        }
    }

    return @rmdir($directory);
}

function ensureTableUtf8mb4($table)
{
    global $pdo;

    try {
        $stmt = $pdo->prepare('SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        $currentCollation = $stmt->fetchColumn();

        if ($currentCollation === false) {
            error_log("Failed to detect current collation for table {$table}");
            return false;
        }

        if (stripos((string) $currentCollation, 'utf8mb4') === 0) {
            return true;
        }

        $pdo->exec("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        return true;
    } catch (PDOException $e) {
        error_log('Failed to convert table to utf8mb4: ' . $e->getMessage());
        return false;
    }
}

function ensureCardNumberTableSupportsUnicode()
{
    global $connect;

    if (!isset($connect) || !($connect instanceof mysqli)) {
        return;
    }

    try {
        if (method_exists($connect, 'character_set_name') && $connect->character_set_name() !== 'utf8mb4') {
            if (!$connect->set_charset('utf8mb4')) {
                error_log('Failed to enforce utf8mb4 charset on mysqli connection: ' . $connect->error);
            }
        }

        if (!$connect->query("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'")) {
            error_log('Failed to execute SET NAMES utf8mb4 for card_number table: ' . $connect->error);
        }

        $createQuery = "CREATE TABLE IF NOT EXISTS card_number (" .
            "cardnumber varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci PRIMARY KEY," .
            "namecard varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL" .
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!$connect->query($createQuery)) {
            error_log('Failed to create card_number table with utf8mb4 charset: ' . $connect->error);
        }

        ensureTableUtf8mb4('card_number');

        $columnInfo = $connect->query("SHOW FULL COLUMNS FROM card_number WHERE Field IN ('cardnumber', 'namecard')");
        if ($columnInfo instanceof mysqli_result) {
            while ($column = $columnInfo->fetch_assoc()) {
                $collation = $column['Collation'] ?? '';
                if (!is_string($collation) || stripos($collation, 'utf8mb4') === false) {
                    $field = $column['Field'];
                    $type = $field === 'cardnumber' ? 'varchar(500)' : 'varchar(1000)';
                    $alter = sprintf(
                        "ALTER TABLE card_number MODIFY %s %s CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci%s",
                        $field,
                        $type,
                        $field === 'cardnumber' ? ' PRIMARY KEY' : ' NOT NULL'
                    );
                    if (!$connect->query($alter)) {
                        error_log('Failed to update card_number column collation: ' . $connect->error);
                    }
                }
            }
            $columnInfo->free();
        } else {
            error_log('Unable to inspect card_number column collations: ' . $connect->error);
        }
    } catch (\Throwable $e) {
        error_log('Unexpected error while ensuring card_number utf8mb4 compatibility: ' . $e->getMessage());
    }
}

function normaliseUpdateValue($value)
{
    if (is_array($value) || is_object($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return $value;
}

function copyDirectoryContents($source, $destination)
{
    if (!is_dir($source)) {
        return false;
    }

    if (!is_dir($destination) && !mkdir($destination, 0777, true) && !is_dir($destination)) {
        return false;
    }

    $items = scandir($source);
    if ($items === false) {
        return false;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $sourcePath = $source . DIRECTORY_SEPARATOR . $item;
        $destinationPath = $destination . DIRECTORY_SEPARATOR . $item;

        if (is_dir($sourcePath)) {
            if (!copyDirectoryContents($sourcePath, $destinationPath)) {
                return false;
            }
        } else {
            if (!@copy($sourcePath, $destinationPath)) {
                return false;
            }
        }
    }

    return true;
}

#-----------function------------#
function step($step, $from_id)
{
    global $pdo;
    $stmt = $pdo->prepare('UPDATE user SET step = ? WHERE id = ?');
    $stmt->execute([$step, $from_id]);
    clearSelectCache('user');
}
function determineColumnTypeFromValue($value)
{
    if (is_bool($value)) {
        return 'TINYINT(1)';
    }

    if (is_int($value)) {
        return 'INT(11)';
    }

    if (is_float($value)) {
        return 'DOUBLE';
    }

    if ($value === null) {
        return 'VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    if (is_string($value)) {
        if (function_exists('mb_strlen')) {
            $length = mb_strlen($value, 'UTF-8');
        } else {
            $length = strlen($value);
        }

        if ($length <= 191) {
            return 'VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        if ($length <= 500) {
            return 'VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        return 'TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    return 'TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
}
function ensureColumnExistsForUpdate($tableName, $fieldName, $valueSample = null)
{
    global $pdo;

    static $checkedColumns = [];

    $cacheKey = $tableName . '.' . $fieldName;
    if (isset($checkedColumns[$cacheKey])) {
        return;
    }

    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $stmt->execute([$tableName, $fieldName]);
        if ((int) $stmt->fetchColumn() > 0) {
            $checkedColumns[$cacheKey] = true;
            return;
        }

        $datatype = determineColumnTypeFromValue($valueSample);

        $defaultValue = null;
        if (is_bool($valueSample)) {
            $defaultValue = $valueSample ? '1' : '0';
        } elseif (is_scalar($valueSample) && $valueSample !== null) {
            $defaultValue = (string) $valueSample;
        }

        addFieldToTable($tableName, $fieldName, $defaultValue, $datatype);
        $checkedColumns[$cacheKey] = true;
    } catch (PDOException $e) {
        error_log('Failed to ensure column exists: ' . $e->getMessage());
        $checkedColumns[$cacheKey] = true;
    }
}
function update($table, $field, $newValue, $whereField = null, $whereValue = null)
{
    global $pdo, $user;

    $valueToStore = normaliseUpdateValue($newValue);
    $whereValueToStore = $whereField !== null ? normaliseUpdateValue($whereValue) : null;

    ensureColumnExistsForUpdate($table, $field, $valueToStore);
    if ($whereField !== null) {
        ensureColumnExistsForUpdate($table, $whereField, $whereValueToStore);
    }

    $executeUpdate = function ($value) use ($pdo, $table, $field, $whereField, $whereValueToStore) {
        if ($whereField !== null) {
            $stmt = $pdo->prepare("UPDATE $table SET $field = ? WHERE $whereField = ?");
            $stmt->execute([$value, $whereValueToStore]);
        } else {
            $stmt = $pdo->prepare("UPDATE $table SET $field = ?");
            $stmt->execute([$value]);
        }

        return isset($stmt) ? $stmt->rowCount() : 0;
    };

    $affectedRows = 0;

    try {
        $affectedRows = $executeUpdate($valueToStore);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Incorrect string value') !== false) {
            $tableConverted = ensureTableUtf8mb4($table);
            if ($tableConverted) {
                try {
                    $affectedRows = $executeUpdate($valueToStore);
                } catch (PDOException $retryException) {
                    error_log('Retry after charset conversion failed: ' . $retryException->getMessage());
                    throw $retryException;
                }
            } else {
                $fallbackValue = is_string($valueToStore) ? @iconv('UTF-8', 'UTF-8//IGNORE', $valueToStore) : $valueToStore;
                if ($fallbackValue === false) {
                    $fallbackValue = '';
                }
                $affectedRows = $executeUpdate($fallbackValue);
            }
        } else {
            throw $e;
        }
    }

    if ($whereField !== null && $affectedRows === 0) {
        if ($whereValueToStore === null) {
            $existsStmt = $pdo->prepare("SELECT 1 FROM $table WHERE $whereField IS NULL LIMIT 1");
            $existsStmt->execute();
        } else {
            $existsStmt = $pdo->prepare("SELECT 1 FROM $table WHERE $whereField = ? LIMIT 1");
            $existsStmt->execute([$whereValueToStore]);
        }

        $rowExists = $existsStmt->fetchColumn();

        if ($rowExists === false) {
            $columns = [$field];
            $values = [$valueToStore];

            if ($field !== $whereField) {
                $columns[] = $whereField;
                $values[] = $whereValueToStore;
            }

            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $columnList = implode(', ', array_map(function ($column) {
                return "`$column`";
            }, $columns));

            try {
                $insertStmt = $pdo->prepare("INSERT INTO $table ($columnList) VALUES ($placeholders)");
                $insertStmt->execute($values);
            } catch (PDOException $insertException) {
                error_log('Failed to insert missing row during update fallback: ' . $insertException->getMessage());
            }
        }
    }

    $date = date("Y-m-d H:i:s");
    if (!isset($user['step'])) {
        $user['step'] = '';
    }
    $logValue = is_scalar($valueToStore) ? $valueToStore : json_encode($valueToStore, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $logss = "{$table}_{$field}_{$logValue}_{$whereField}_{$whereValue}_{$user['step']}_$date";
    if ($field != "message_count" && $field != "last_message_time") {
        file_put_contents('log.txt', "\n" . $logss, FILE_APPEND);
    }

    clearSelectCache($table);
}
function &getSelectCacheStore()
{
    static $store = [
        'results' => [],
        'tableIndex' => [],
    ];

    return $store;
}

function clearSelectCache($table = null)
{
    $store =& getSelectCacheStore();

    if ($table === null) {
        $store['results'] = [];
        $store['tableIndex'] = [];
        return;
    }

    if (!isset($store['tableIndex'][$table])) {
        return;
    }

    foreach (array_keys($store['tableIndex'][$table]) as $cacheKey) {
        unset($store['results'][$cacheKey]);
    }

    unset($store['tableIndex'][$table]);
}

function select($table, $field, $whereField = null, $whereValue = null, $type = "select", $options = [])
{
    $pdo = getDatabaseConnection();

    if (!($pdo instanceof PDO)) {
        error_log('select: Database connection is unavailable.');

        switch ($type) {
            case 'count':
                return 0;
            case 'FETCH_COLUMN':
            case 'fetchAll':
                return [];
            default:
                return null;
        }
    }

    $useCache = true;
    if (is_array($options) && array_key_exists('cache', $options)) {
        $useCache = (bool) $options['cache'];
    }

    $cacheKey = null;
    if ($useCache) {
        $cacheKey = hash('sha256', json_encode([
            $table,
            $field,
            $whereField,
            $whereValue,
            $type,
        ], JSON_UNESCAPED_UNICODE));

        $store =& getSelectCacheStore();
        if (isset($store['results'][$cacheKey])) {
            return $store['results'][$cacheKey];
        }
    }

    $query = "SELECT $field FROM $table";

    if ($whereField !== null) {
        $query .= " WHERE $whereField = :whereValue";
    }

    try {
        $stmt = $pdo->prepare($query);
        if ($whereField !== null) {
            $stmt->bindParam(':whereValue', $whereValue, PDO::PARAM_STR);
        }

        $stmt->execute();
        if ($type == "count") {
            $result = $stmt->rowCount();
        } elseif ($type == "FETCH_COLUMN") {
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if ($table === 'admin' && $field === 'id_admin') {
                global $adminnumber;
                if (!is_array($results)) {
                    $results = [];
                }

                $results = array_values(array_unique(array_filter($results, function ($value) {
                    return $value !== null && $value !== '';
                })));

                if (empty($results) && isset($adminnumber) && $adminnumber !== '') {
                    $results[] = (string) $adminnumber;
                }
            }
            $result = $results;
        } elseif ($type == "fetchAll") {
            $result = $stmt->fetchAll();
        } else {
            $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
            $result = $fetched === false ? null : $fetched;
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
        die("Query failed: " . $e->getMessage());
    }

    if ($useCache && $cacheKey !== null) {
        $store =& getSelectCacheStore();
        $store['results'][$cacheKey] = $result;
        if (!isset($store['tableIndex'][$table])) {
            $store['tableIndex'][$table] = [];
        }
        $store['tableIndex'][$table][$cacheKey] = true;
    }

    return $result;
}

function getPaySettingValue($name, $default = null)
{
    $result = select("PaySetting", "ValuePay", "NamePay", $name, "select");
    if (!is_array($result) || !array_key_exists('ValuePay', $result)) {
        return $default;
    }

    return $result['ValuePay'];
}

function formatPaymentReportNote($rawNote)
{
    if ($rawNote === null) {
        return '';
    }

    if (is_array($rawNote)) {
        return json_encode($rawNote, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    if (!is_scalar($rawNote)) {
        return '';
    }

    $rawNote = trim((string) $rawNote);
    if ($rawNote === '') {
        return '';
    }

    $decoded = json_decode($rawNote, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        if (($decoded['gateway'] ?? '') === 'zarinpay') {
            $lines = ['زرین‌پی'];
            $fieldMap = [
                'payment_id' => 'شناسه پرداخت',
                'reference_id' => 'شماره پیگیری',
                'authority' => 'کد اعتبار',
                'order_id' => 'کد سفارش',
                'code' => 'کد تأیید',
            ];

            foreach ($fieldMap as $key => $label) {
                $value = $decoded[$key] ?? null;
                if ($value !== null && $value !== '') {
                    $lines[] = sprintf('%s: %s', $label, $value);
                }
            }

            if (!empty($decoded['amount'])) {
                $lines[] = 'مبلغ تراکنش (ریال): ' . number_format((int) $decoded['amount']);
            }

            if (!empty($decoded['card_pan'])) {
                $lines[] = 'کارت پرداخت‌کننده: ' . $decoded['card_pan'];
            }

            if (!empty($decoded['paid_at'])) {
                $lines[] = 'زمان پرداخت: ' . $decoded['paid_at'];
            }

            return implode("\n", array_filter($lines));
        }

        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    return $rawNote;
}
function generateUUID()
{
    $data = openssl_random_pseudo_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

    return $uuid;
}
function tronratee(array $requiredKeys = [])
{

    $normalizedKeys = [];
    foreach ($requiredKeys as $key) {
        $normalized = strtoupper(trim((string) $key));
        if ($normalized === '') {
            continue;
        }
        $normalizedKeys[$normalized] = true;
    }

    if (empty($normalizedKeys)) {
        $normalizedKeys = ['TRX' => true, 'TON' => true, 'USD' => true];
    }

    $needsTrx = isset($normalizedKeys['TRX']);
    $needsTon = isset($normalizedKeys['TON']);
    $needsUsd = isset($normalizedKeys['USD']);

    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
        ],
    ]);

    $result = [];
    $missingKeys = [];

    if (!$needsTrx && !$needsTon && !$needsUsd) {
        return ['ok' => true, 'result' => $result];
    }

    $usdToToman = null;

    $usdResponse = @file_get_contents('https://sarfe.erfjab.com/api/prices', false, $context);
    if ($usdResponse === false) {
        error_log('Failed to fetch USD price from Sarfe API');
    } else {
        $usdData = json_decode($usdResponse, true);
        if (!is_array($usdData)) {
            error_log('Invalid response received from Sarfe API');
        } else {
            $usdPrice = null;
            $usdRawValues = [];

            foreach (['usd1', 'usd2'] as $usdKey) {
                if (!array_key_exists($usdKey, $usdData)) {
                    continue;
                }

                $rawValue = $usdData[$usdKey];
                $usdRawValues[$usdKey] = $rawValue;

                if (is_string($rawValue)) {

                    $normalizedValue = preg_replace('/[^\d\.\-]/u', '', $rawValue);
                } elseif (is_numeric($rawValue)) {
                    $normalizedValue = (string) $rawValue;
                } else {
                    continue;
                }

                if (!is_numeric($normalizedValue)) {
                    continue;
                }

                $numericValue = abs((float) $normalizedValue);
                if ($numericValue > 0.0) {
                    $usdPrice = $numericValue;
                    break;
                }
            }

            if ($usdPrice === null) {
                $rawLog = '';
                if (!empty($usdRawValues)) {
                    $rawLog = ' Raw values: ' . json_encode($usdRawValues);
                }
                error_log('Missing USD price from Sarfe API.' . $rawLog);
            } else {

                $usdToToman = $usdPrice;
            }
        }
    }

    if ($usdToToman === null) {
        if ($needsTrx) {
            $missingKeys[] = 'TRX';
        }
        if ($needsTon) {
            $missingKeys[] = 'Ton';
        }
        if ($needsUsd) {
            $missingKeys[] = 'USD';
        }

        $ok = empty($missingKeys);
        return ['ok' => $ok, 'result' => $result];
    }

    $fetchCoinPrice = static function (string $id) use ($context) {
        $endpoint = 'https://api.coingecko.com/api/v3/simple/price?ids='
            . rawurlencode($id)
            . '&vs_currencies=usd';

        $response = @file_get_contents($endpoint, false, $context);
        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data[$id]['usd']) || !is_numeric($data[$id]['usd'])) {
            return null;
        }

        $value = (float) $data[$id]['usd'];
        if ($value <= 0.0 || !is_finite($value)) {
            return null;
        }

        return $value; 
    };

    if ($needsTrx) {

        $trxUsd = $fetchCoinPrice('tron');
        if ($trxUsd === null) {
            error_log('Missing or invalid TRX price from CoinGecko');
            $missingKeys[] = 'TRX';
        } else {
            $result['TRX'] = round($trxUsd * $usdToToman, 2); 
        }
    }

    if ($needsTon) {
        $tonUsd = $fetchCoinPrice('toncoin');
        if ($tonUsd === null) {
            error_log('Missing or invalid Ton price from CoinGecko');
            $missingKeys[] = 'Ton';
        } else {
            $result['Ton'] = round($tonUsd * $usdToToman, 2); 
        }
    }

    if ($needsUsd) {

        $usdtUsd = $fetchCoinPrice('tether');
        if ($usdtUsd === null) {
            error_log('Missing or invalid USDT price from CoinGecko');
            $missingKeys[] = 'USD';
        } else {
            $result['USD'] = round($usdtUsd * $usdToToman, 2); 
        }
    }

    $ok = empty($missingKeys);

    return ['ok' => $ok, 'result' => $result];
}

function requireTronRates(array $keys = [])
{
    $normalizedKeys = [];
    foreach ($keys as $key) {
        $upper = strtoupper(trim((string) $key));
        if ($upper === '') {
            continue;
        }
        $normalizedKeys[$upper] = true;
    }

    $requestedKeys = array_keys($normalizedKeys);
    $rates = tronratee($requestedKeys);

    if (!is_array($rates) || !isset($rates['result']) || !is_array($rates['result'])) {
        return null;
    }

    $result = $rates['result'];

    if (isset($result['USD']) && is_numeric($result['USD'])) {
        $result['USD'] = round(abs((float) $result['USD']), 2);
    }

    $validationKeys = [];
    if (empty($requestedKeys)) {
        $validationKeys = ['TRX', 'Ton', 'USD'];
    } else {
        foreach ($requestedKeys as $requestedKey) {
            if ($requestedKey === 'TON') {
                $validationKeys[] = 'Ton';
            } elseif ($requestedKey === 'TRX' || $requestedKey === 'USD') {
                $validationKeys[] = $requestedKey;
            } else {
                $validationKeys[] = $requestedKey;
            }
        }
    }

    foreach ($validationKeys as $key) {
        if (!isset($result[$key]) || (is_numeric($result[$key]) && (float) $result[$key] == 0.0)) {
            return null;
        }
    }

    return $result;
}

function updatePaymentMessageId($response, $orderId)
{
    if (!is_array($response)) {
        error_log("Failed to send payment message for order {$orderId}: unexpected response");
        return false;
    }

    if (empty($response['ok'])) {
        error_log("Failed to send payment message for order {$orderId}: " . json_encode($response));
        return false;
    }

    if (!isset($response['result']['message_id'])) {
        error_log("Missing message_id for order {$orderId}: " . json_encode($response));
        return false;
    }

    update("Payment_report", "message_id", intval($response['result']['message_id']), "id_order", $orderId);
    return true;
}
function nowPayments($payment, $price_amount, $order_id, $order_description)
{
    global $domainhosts;
    $apinowpayments = select("PaySetting", "*", "NamePay", "marchent_tronseller", "select")['ValuePay'];
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.nowpayments.io/v1/' . $payment,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT_MS => 7000,
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => 1,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            'x-api-key:' . $apinowpayments,
            'Content-Type: application/json'
        ),
    ));
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
        'price_amount' => $price_amount,
        'price_currency' => 'usd',
        'order_id' => $order_id,
        'order_description' => $order_description,
        'ipn_callback_url' => "https://" . $domainhosts . "/payment/nowpayment.php"
    ]));

    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true);
}
function StatusPayment($paymentid)
{
    $apinowpayments = select("PaySetting", "*", "NamePay", "marchent_tronseller", "select")['ValuePay'];
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.nowpayments.io/v1/payment/' . $paymentid,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'x-api-key:' . $apinowpayments
        ),
    ));
    $response = curl_exec($curl);
    $response = json_decode($response, true);
    curl_close($curl);
    return $response;
}
function channel(array $id_channel)
{
    global $from_id;
    $channel_link = array();
    foreach ($id_channel as $channel) {
        $response = telegram('getChatMember', [
            'chat_id' => $channel,
            'user_id' => $from_id
        ]);
        if ($response['ok']) {
            if (!in_array($response['result']['status'], ['member', 'creator', 'administrator'])) {
                $channel_link[] = $channel;
            }
        }
    }
    if (count($channel_link) == 0) {
        return [];
    } else {
        return $channel_link;
    }
}
function isValidDate($date)
{
    return (strtotime($date) != false);
}
function trnado($order_id, $price)
{
    global $domainhosts;

    $errorId = 'TRN-' . bin2hex(random_bytes(4));

    $apitronseller = select("PaySetting", "*", "NamePay", "apiternado", "select")['ValuePay'];
    $walletSetting = select("PaySetting", "*", "NamePay", "walletaddress", "select");
    $walletaddress = trim((string) ($walletSetting['ValuePay'] ?? ''));
    $configuredUrl = trim((string) (select("PaySetting", "*", "NamePay", "urlpaymenttron", "select")['ValuePay'] ?? ''));

    if ($configuredUrl === '') {
        $configuredUrl = 'https://bot.tronado.cloud/api/v3/GetOrderToken';
    }

    if ($walletaddress === '') {
        $lastErrorPayload = [
            'success'  => false,
            'error'    => 'آدرس کیف پول تنظیم نشده است',
            'error_id' => $errorId,
        ];
        error_log('[Tronado] ' . json_encode($lastErrorPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $lastErrorPayload;
    }

    $trxAmountStr = number_format((float)$price, 6, '.', '');

    $callbackUrl = 'https://' . $domainhosts . '/payment/tronado.php';

    $fields = [
        'PaymentID'                  => (string)$order_id,
        'WalletAddress'              => $walletaddress,
        'TronAmount'                 => $trxAmountStr,
        'CallbackUrl'                => $callbackUrl,
        'wageFromBusinessPercentage' => '0',
        'apiVersion'                 => '1',
    ];

    $ch = curl_init($configuredUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $fields, 
        CURLOPT_HTTPHEADER     => [
            'x-api-key: ' . $apitronseller,
        ],
        CURLOPT_TIMEOUT        => 20,
    ]);

    $response = curl_exec($ch);
    $errno    = curl_errno($ch);
    $errstr   = curl_error($ch);
    $status   = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($errno) {
        $lastErrorPayload = [
            'success'  => false,
            'error'    => "cURL error ($errno): $errstr",
            'http'     => $status,
            'error_id' => $errorId,
        ];
        error_log('[Tronado] ' . json_encode($lastErrorPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $lastErrorPayload;
    }

    if ($status < 200 || $status >= 300) {
        $lastErrorPayload = [
            'success'  => false,
            'error'    => "HTTP $status",
            'http'     => $status,
            'body'     => mb_substr((string)$response, 0, 500, 'UTF-8'),
            'error_id' => $errorId,
        ];
        error_log('[Tronado] ' . json_encode($lastErrorPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $lastErrorPayload;
    }

    $json = json_decode((string)$response, true);
    if (!is_array($json)) {
        $lastErrorPayload = [
            'success'  => false,
            'error'    => 'پاسخ نامعتبر از ترنادو',
            'body'     => mb_substr((string)$response, 0, 500, 'UTF-8'),
            'error_id' => $errorId,
        ];
        error_log('[Tronado] ' . json_encode($lastErrorPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $lastErrorPayload;
    }

    if (!empty($json['IsSuccessful'])) {
        $token = $json['Data']['Token'] ?? null;
        if (!$token) {
            $lastErrorPayload = [
                'success'  => false,
                'error'    => 'Token خالی در پاسخ موفق ترنادو',
                'raw'      => $json,
                'error_id' => $errorId,
            ];
            error_log('[Tronado] ' . json_encode($lastErrorPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return $lastErrorPayload;
        }

        return [
            'success'        => true,
            'IsSuccessful'   => true,
            'Data'           => ['Token' => $token],
            'FullPaymentUrl' => $json['Data']['FullPaymentUrl'] ?? null,
            'raw'            => $json,
            'error_id'       => $errorId,
        ];
    }

    $lastErrorPayload = [
        'success'  => false,
        'error'    => $json['Message'] ?? 'خطای نامشخص ترنادو',
        'code'     => $json['Code'] ?? null,
        'raw'      => $json,
        'error_id' => $errorId,
    ];
    error_log('[Tronado] ' . json_encode($lastErrorPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $lastErrorPayload;
}

function formatBytes($bytes, $precision = 2): string
{
    if ($bytes <= 0) return '0 بایت';
    $base = log($bytes, 1024);
    $power = floor($base);
    $suffixes = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت', 'ترابایت'];
    $power = min($power, count($suffixes) - 1);
    return round(pow(1024, $base - $power), $precision) . ' ' . $suffixes[$power];
}
function generateUsername($from_id, $Metode, $username, $randomString, $text, $namecustome, $usernamecustom)
{
    $setting = select("setting", "*", null, null, "select");
    $user = select("user", "*", "id", $from_id, "select");
    if ($user == false) {
        $user = array();
        $user = array(
            'number_username' => '',
        );
    }
    if ($Metode == "آیدی عددی + حروف و عدد رندوم") {
        return $from_id . "_" . $randomString;
    } elseif ($Metode == "نام کاربری + عدد به ترتیب") {
        if ($username == "NOT_USERNAME") {
            if (preg_match('/^\w{3,32}$/', $namecustome)) {
                $username = $namecustome;
            }
        }
        return $username . "_" . $user['number_username'];
    } elseif ($Metode == "نام کاربری دلخواه")
        return $text;
    elseif ($Metode == "نام کاربری دلخواه + عدد رندوم") {
        $random_number = rand(1000000, 9999999);
        return $text . "_" . $random_number;
    } elseif ($Metode == "متن دلخواه + عدد رندوم") {
        return $namecustome . "_" . $randomString;
    } elseif ($Metode == "متن دلخواه + عدد ترتیبی") {
        return $namecustome . "_" . $setting['numbercount'];
    } elseif ($Metode == "آیدی عددی+عدد ترتیبی") {
        return $from_id . "_" . $user['number_username'];
    } elseif ($Metode == "متن دلخواه نماینده + عدد ترتیبی") {
        if ($usernamecustom == "none") {
            return $namecustome . "_" . $setting['numbercount'];
        }
        return $usernamecustom . "_" . $user['number_username'];
    }
}
function outputlunk($text)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $text);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 6000);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return null;
    } else {
        curl_close($ch);
        return $response;
    }
}
function outputlunksub($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$url/info");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $headers = array();
    $headers[] = 'Accept: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('Error:' . curl_error($ch));
    }
    curl_close($ch);
    return $result;
}
function normalizeServiceConfigs($configs, $subscriptionUrl = null)
{
    $normalized = [];

    if (is_array($configs)) {
        foreach ($configs as $item) {
            if (!is_string($item)) {
                continue;
            }
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            $normalized[] = $item;
        }
    } elseif (is_string($configs)) {
        $parts = preg_split("/\r\n|\n|\r/", $configs);
        if (is_array($parts)) {
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                $normalized[] = $part;
            }
        }
    }

    $subscriptionUrl = is_string($subscriptionUrl) ? trim($subscriptionUrl) : '';
    if (empty($normalized) && $subscriptionUrl !== '') {
        if (preg_match('/^https?:/i', $subscriptionUrl)) {
            $fetched = outputlunk($subscriptionUrl);
            if (is_string($fetched) && $fetched !== '') {
                if (isBase64($fetched)) {
                    $fetched = base64_decode($fetched);
                }
                $parts = preg_split("/\r\n|\n|\r/", $fetched);
                if (is_array($parts)) {
                    foreach ($parts as $part) {
                        $part = trim($part);
                        if ($part === '') {
                            continue;
                        }
                        $normalized[] = $part;
                    }
                }
            }
        } else {
            $normalized[] = $subscriptionUrl;
        }
    }

    return array_values($normalized);
}
function DirectPayment($order_id, $image = 'images.jpg')
{
    global $pdo, $ManagePanel, $textbotlang, $keyboardextendfnished, $keyboard, $Confirm_pay, $from_id, $message_id, $datatextbot;
    $buyreport = select("topicid", "idreport", "report", "buyreport", "select")['idreport'];
    $admin_ids = select("admin", "id_admin", null, null, "FETCH_COLUMN");
    $otherservice = select("topicid", "idreport", "report", "otherservice", "select")['idreport'];
    $otherreport = select("topicid", "idreport", "report", "otherreport", "select")['idreport'];
    $errorreport = select("topicid", "idreport", "report", "errorreport", "select")['idreport'];
    $porsantreport = select("topicid", "idreport", "report", "porsantreport", "select")['idreport'];
    $setting = select("setting", "*");
    $Payment_report = select("Payment_report", "*", "id_order", $order_id, "select");
    $paymentNote = formatPaymentReportNote($Payment_report['dec_not_confirmed'] ?? null);
    $format_price_cart = number_format($Payment_report['price']);
    $Balance_id = select("user", "*", "id", $Payment_report['id_user'], "select");
    $steppay = explode("|", $Payment_report['id_invoice']);
    update("user", "Processing_value", "0", "id", $Balance_id['id']);
    update("user", "Processing_value_one", "0", "id", $Balance_id['id']);
    update("user", "Processing_value_tow", "0", "id", $Balance_id['id']);
    update("user", "Processing_value_four", "0", "id", $Balance_id['id']);
    if ($steppay[0] == "getconfigafterpay") {
        $stmt = $pdo->prepare("SELECT * FROM invoice WHERE username = :username AND Status = 'unpaid' LIMIT 1");
        $stmt->execute([':username' => $steppay[1]]);
        $get_invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare("SELECT * FROM product WHERE name_product = :name_product AND (Location = :location or Location = '/all')");
        $stmt->execute([':name_product' => $get_invoice['name_product'], ':location' => $get_invoice['Service_location']]);
        $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($get_invoice['name_product'] == "🛍 حجم دلخواه" || $get_invoice['name_product'] == "⚙️ سرویس دلخواه") {
            $info_product['data_limit_reset'] = "no_reset";
            $info_product['Volume_constraint'] = $get_invoice['Volume'];
            $info_product['name_product'] = $textbotlang['users']['customsellvolume']['title'];
            $info_product['code_product'] = "customvolume";
            $info_product['Service_time'] = $get_invoice['Service_time'];
            $info_product['price_product'] = $get_invoice['price_product'];
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE name_product = :name_product AND (Location = :location or Location = '/all')");
            $stmt->execute([':name_product' => $get_invoice['name_product'], ':location' => $get_invoice['Service_location']]);
            $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        $username_ac = $get_invoice['username'];
        $randomString = bin2hex(random_bytes(2));
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $get_invoice['Service_location'], "select");
        $date = strtotime("+" . $get_invoice['Service_time'] . "days");
        if (intval($get_invoice['Service_time']) == 0) {
            $timestamp = 0;
        } else {
            $timestamp = strtotime(date("Y-m-d H:i:s", $date));
        }
        $datac = array(
            'expire' => $timestamp,
            'data_limit' => $get_invoice['Volume'] * pow(1024, 3),
            'from_id' => $Balance_id['id'],
            'username' => $Balance_id['username'],
            'type' => 'buy'
        );
        $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], $info_product['code_product'], $username_ac, $datac);
        if ($dataoutput['username'] == null) {
            $dataoutput['msg'] = json_encode($dataoutput['msg']);
            $balance = $Balance_id['Balance'] + $Payment_report['price'];
            update("user", "Balance", $balance, "id", $Balance_id['id']);
            sendmessage($Balance_id['id'], $textbotlang['users']['sell']['ErrorConfig'], $keyboard, 'HTML');
            sendmessage($Balance_id['id'], "💎  کاربر عزیز بدلیل ساخته نشدن سرویس مبلغ $balance تومان به کیف پول شما اضافه گردید.", $keyboard, 'HTML');
            $texterros = "
⭕️ خطا در ساخت کانفیگ
✍️ دلیل خطا : 
{$dataoutput['msg']}
آیدی کابر : {$Balance_id['id']}
نام کاربری کاربر : @{$Balance_id['username']}
نام پنل : {$marzban_list_get['name_panel']}";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $texterros,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        $Shoppinginfo = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "📚 مشاهده آموزش استفاده ", 'callback_data' => "helpbtn"],
                ]
            ]
        ]);
        $output_config_link = "";
        $config = "";
        if ($marzban_list_get['config'] == "onconfig" && is_array($dataoutput['configs'])) {
            foreach ($dataoutput['configs'] as $link) {
                $config .= "\n" . $link;
            }
        }
        $output_config_link = $marzban_list_get['sublink'] == "onsublink" ? $dataoutput['subscription_url'] : "";
        $datatextbot['textafterpay'] = $marzban_list_get['type'] == "Manualsale" ? $datatextbot['textmanual'] : $datatextbot['textafterpay'];
        $datatextbot['textafterpay'] = $marzban_list_get['type'] == "WGDashboard" ? $datatextbot['text_wgdashboard'] : $datatextbot['textafterpay'];
        $datatextbot['textafterpay'] = $marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik" ? $datatextbot['textafterpayibsng'] : $datatextbot['textafterpay'];
        if (intval($get_invoice['Service_time']) == 0)
            $get_invoice['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
        $textcreatuser = str_replace('{username}', $dataoutput['username'], $datatextbot['textafterpay']);
        $textcreatuser = str_replace('{name_service}', $get_invoice['name_product'], $textcreatuser);
        $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
        $textcreatuser = str_replace('{day}', $get_invoice['Service_time'], $textcreatuser);
        $textcreatuser = str_replace('{volume}', $get_invoice['Volume'], $textcreatuser);
        $textcreatuser = applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
        if ($marzban_list_get['type'] == "Manualsale" || $marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik") {
            $textcreatuser = str_replace('{password}', $dataoutput['subscription_url'], $textcreatuser);
            update("invoice", "user_info", $dataoutput['subscription_url'], "id_invoice", $get_invoice['id_invoice']);
        }
        sendMessageService($marzban_list_get, $dataoutput['configs'], $output_config_link, $dataoutput['username'], $Shoppinginfo, $textcreatuser, $get_invoice['id_invoice'], $get_invoice['id_user'], $image);
        $partsdic = explode("_", $Balance_id['Processing_value_four']);
        if ($partsdic[0] == "dis") {
            $SellDiscountlimit = select("DiscountSell", "*", "codeDiscount", $partsdic[1], "select");
            $value = intval($SellDiscountlimit['usedDiscount']) + 1;
            update("DiscountSell", "usedDiscount", $value, "codeDiscount", $partsdic[1]);
            $stmt = $pdo->prepare("INSERT INTO Giftcodeconsumed (id_user,code) VALUES (:id_user,:code)");
            $stmt->bindParam(':id_user', $Balance_id['id']);
            $stmt->bindParam(':code', $partsdic[1]);
            $stmt->execute();
            $text_report = "⭕️ یک کاربر با نام کاربری @{$Balance_id['username']}  و آیدی عددی {$Balance_id['id']} از کد تخفیف {$partsdic[1]} استفاده کرد.";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherreport,
                    'text' => $text_report,
                ]);
            }
        }
        $affiliatescommission = select("affiliates", "*", null, null, "select");
        $marzbanporsant_one_buy = select("affiliates", "*", null, null, "select");
        $stmt = $pdo->prepare("SELECT * FROM invoice WHERE name_product != 'سرویس تست'  AND id_user = :id_user AND Status != 'Unpaid'");
        $stmt->bindParam(':id_user', $Balance_id['id']);
        $stmt->execute();
        $countinvoice = $stmt->rowCount();
        if ($affiliatescommission['status_commission'] == "oncommission" && ($Balance_id['affiliates'] != null && intval($Balance_id['affiliates']) != 0)) {
            if ($marzbanporsant_one_buy['porsant_one_buy'] == "on_buy_porsant") {
                if ($countinvoice <= 1) {
                    $result = ($Payment_report['price'] * $setting['affiliatespercentage']) / 100;
                    $user_Balance = select("user", "*", "id", $Balance_id['affiliates'], "select");
                    if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['affiliates'], $admin_ids)) {
                        sendmessage($Balance_id['affiliates'], "📌شما 2 امتیاز جدید کسب کردید.", null, 'html');
                        $scorenew = $user_Balance['score'] + 2;
                        update("user", "score", $scorenew, "id", $Balance_id['affiliates']);
                    }
                    $Balance_prim = $user_Balance['Balance'] + $result;
                    $dateacc = date('Y/m/d H:i:s');
                    update("user", "Balance", $Balance_prim, "id", $Balance_id['affiliates']);
                    $result = number_format($result);
                    $textadd = "🎁  پرداخت پورسانت 

        مبلغ $result تومان به حساب شما از طرف  زیر مجموعه تان به کیف پول شما واریز گردید";
                    $textreportport = "
مبلغ $result به کاربر {$Balance_id['affiliates']} برای پورسانت از کاربر {$Balance_id['id']} واریز گردید 
تایم : $dateacc";
                    if (strlen($setting['Channel_Report']) > 0) {
                        telegram('sendmessage', [
                            'chat_id' => $setting['Channel_Report'],
                            'message_thread_id' => $porsantreport,
                            'text' => $textreportport,
                            'parse_mode' => "HTML"
                        ]);
                    }
                    sendmessage($Balance_id['affiliates'], $textadd, null, 'HTML');
                }
            } else {

                $result = ($Payment_report['price'] * $setting['affiliatespercentage']) / 100;
                $user_Balance = select("user", "*", "id", $Balance_id['affiliates'], "select");
                if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['affiliates'], $admin_ids)) {
                    sendmessage($Balance_id['affiliates'], "📌شما 2 امتیاز جدید کسب کردید.", null, 'html');
                    $scorenew = $user_Balance['score'] + 2;
                    update("user", "score", $scorenew, "id", $Balance_id['affiliates']);
                }
                $Balance_prim = $user_Balance['Balance'] + $result;
                $dateacc = date('Y/m/d H:i:s');
                update("user", "Balance", $Balance_prim, "id", $Balance_id['affiliates']);
                $result = number_format($result);
                $textadd = "🎁  پرداخت پورسانت 

        مبلغ $result تومان به حساب شما از طرف  زیر مجموعه تان به کیف پول شما واریز گردید";
                $textreportport = "
مبلغ $result به کاربر {$Balance_id['affiliates']} برای پورسانت از کاربر {$Balance_id['id']} واریز گردید 
تایم : $dateacc";
                if (strlen($setting['Channel_Report']) > 0) {
                    telegram('sendmessage', [
                        'chat_id' => $setting['Channel_Report'],
                        'message_thread_id' => $porsantreport,
                        'text' => $textreportport,
                        'parse_mode' => "HTML"
                    ]);
                }
                sendmessage($Balance_id['affiliates'], $textadd, null, 'HTML');
            }
        }
        if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "نام کاربری + عدد به ترتیب" || $marzban_list_get['MethodUsername'] == "آیدی عددی+عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
            $value = intval($Balance_id['number_username']) + 1;
            update("user", "number_username", $value, "id", $Balance_id['id']);
            if ($marzban_list_get['MethodUsername'] == "متن دلخواه + عدد ترتیبی" || $marzban_list_get['MethodUsername'] == "متن دلخواه نماینده + عدد ترتیبی") {
                $value = intval($setting['numbercount']) + 1;
                update("setting", "numbercount", $value);
            }
        }
        $Balance_prims = $Balance_id['Balance'] - $get_invoice['price_product'];
        if ($Balance_prims <= 0)
            $Balance_prims = 0;
        update("user", "Balance", $Balance_prims, "id", $Balance_id['id']);
        $balanceformatsell = select("user", "Balance", "id", $get_invoice['id_user'], "select")['Balance'];
        $balanceformatsell = number_format($balanceformatsell, 0);
        $balancebefore = number_format($Balance_id['Balance'], 0);
        $timejalali = jdate('Y/m/d H:i:s');
        $textonebuy = "";
        if ($countinvoice == 1) {
            $textonebuy = "📌 خرید اول کاربر";
        }
        $Response = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $Balance_id['id']],
                ],
            ]
        ]);
        $text_report = "📣 جزئیات ساخت اکانت در ربات بعد پرداخت ثبت شد .

$textonebuy
▫️آیدی عددی کاربر : <code>{$Balance_id['id']}</code>
▫️نام کاربری کاربر :@{$Balance_id['username']}
▫️نام کاربری کانفیگ :$username_ac
▫️لوکیشن سرویس : {$get_invoice['Service_location']}
▫️زمان خریداری شده :{$get_invoice['Service_time']} روز
▫️نام محصول خریداری شده :{$get_invoice['name_product']}
▫️حجم خریداری شده : {$get_invoice['Volume']} GB
▫️موجودی قبل خرید : $balancebefore تومان
▫️موجودی بعد خرید : $balanceformatsell تومان
▫️کد پیگیری: {$get_invoice['id_invoice']}
▫️نوع کاربر : {$Balance_id['agent']}
▫️شماره تلفن کاربر : {$Balance_id['number']}
▫️قیمت محصول : {$get_invoice['price_product']} تومان
▫️قیمت نهایی : {$Payment_report['price']} تومان
▫️زمان خرید : $timejalali";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $buyreport,
                'text' => $text_report,
                'parse_mode' => "HTML",
                'reply_markup' => $Response
            ]);
        }
        if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['id'], $admin_ids)) {
            sendmessage($Balance_id['id'], "📌شما 1 امتیاز جدید کسب کردید.", null, 'html');
            $scorenew = $Balance_id['score'] + 1;
            update("user", "score", $scorenew, "id", $Balance_id['id']);
        }
        update("invoice", "Status", "active", "username", $get_invoice['username']);
        if ($Payment_report['Payment_Method'] == "cart to cart" or $Payment_report['Payment_Method'] == "arze digital offline") {
            update("invoice", "Status", "active", "id_invoice", $get_invoice['id_invoice']);
            $textconfrom = "✅ پرداخت تایید شده
🛍خرید سرویس
▫️نام کاربری کانفیگ :$username_ac
▫️لوکیشن سرویس : {$get_invoice['Service_location']}
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💎 موجودی قبل خرید  : {$Balance_id['Balance']}
💸 مبلغ پرداختی: $format_price_cart تومان
✍️ توضیحات : {$paymentNote}

";
            Editmessagetext($from_id, $message_id, $textconfrom, $Confirm_pay);
        }
    } elseif ($steppay[0] == "getextenduser") {
        $balanceformatsell = number_format(select("user", "Balance", "id", $Balance_id['id'], "select")['Balance'], 0);
        $partsdic = explode("%", $steppay[1]);
        $usernamepanel = $partsdic[0];
        $sql = "SELECT * FROM service_other WHERE username = :username  AND value  LIKE CONCAT('%', :value, '%') AND id_user = :id_user ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernamepanel, PDO::PARAM_STR);
        $stmt->bindParam(':value', $partsdic[1], PDO::PARAM_STR);
        $stmt->bindParam(':id_user', $Balance_id['id']);
        $stmt->execute();
        $data_order = $stmt->fetch(PDO::FETCH_ASSOC);
        $service_other = $data_order;
        if ($service_other == false) {
            sendmessage($Balance_id['id'], '❌ خطایی در هنگام تمدید رخ داده با پشتیبانی در ارتباط باشید', $keyboard, 'HTML');
            return;
        }
        $service_other = json_decode($service_other['value'], true);
        $codeproduct = $service_other['code_product'];
        $nameloc = select("invoice", "*", "username", $usernamepanel, "select");
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        if ($codeproduct == "custom_volume") {
            $prodcut['code_product'] = "custom_volume";
            $prodcut['name_product'] = $nameloc['name_product'];
            $prodcut['price_product'] = $data_order['price'];
            $prodcut['Service_time'] = $service_other['Service_time'];
            $prodcut['Volume_constraint'] = $service_other['volumebuy'];
        } else {
            $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all') AND code_product = :code_product");
            $stmt->execute([':location' => $nameloc['Service_location'], ':agent' => $Balance_id['agent'], ':code_product' => $codeproduct]);
            $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if ($nameloc['name_product'] == "سرویس تست") {
            update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $nameloc['id_invoice']);
            update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $nameloc['id_invoice']);
        }
        $dateacc = date('Y/m/d H:i:s');
        $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
        $Balance_Low_user = 0;
        update("user", "Balance", $Balance_Low_user, "id", $Balance_id['id']);
        $extend = $ManagePanel->extend($marzban_list_get['Methodextend'], $prodcut['Volume_constraint'], $prodcut['Service_time'], $nameloc['username'], $prodcut['code_product'], $marzban_list_get['code_panel']);
        if ($extend['status'] == false) {
            $balance = $Balance_id['Balance'] + $Payment_report['price'];
            update("user", "Balance", $balance, "id", $Balance_id['id']);
            sendmessage($Balance_id['id'], $textbotlang['users']['sell']['ErrorConfig'], $keyboard, 'HTML');
            sendmessage($Balance_id['id'], "💎  کاربر عزیز بدلیل تمدید نشدن سرویس مبلغ $balance تومان به کیف پول شما اضافه گردید.", $keyboard, 'HTML');
            $extend['msg'] = json_encode($extend['msg']);
            $textreports = "
        خطای تمدید سرویس
نام پنل : {$marzban_list_get['name_panel']}
نام کاربری سرویس : {$nameloc['username']}
دلیل خطا : {$extend['msg']}";
            sendmessage($nameloc['id_user'], "❌خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $textreports,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }

        update("service_other", "output", json_encode($extend), "id", $data_order['id']);
        update("service_other", "status", "paid", "id", $data_order['id']);
        $partsdic = explode("_", $Balance_id['Processing_value_four']);
        if ($partsdic[0] == "dis") {
            $SellDiscountlimit = select("DiscountSell", "*", "codeDiscount", $partsdic[1], "select");
            $value = intval($SellDiscountlimit['usedDiscount']) + 1;
            update("DiscountSell", "usedDiscount", $value, "codeDiscount", $partsdic[1]);
            $stmt = $pdo->prepare("INSERT INTO Giftcodeconsumed (id_user,code) VALUES (:id_user,:code)");
            $stmt->bindParam(':id_user', $Balance_id['id']);
            $stmt->bindParam(':code', $partsdic[1]);
            $stmt->execute();
            $text_report = "⭕️ یک کاربر با نام کاربری @{$Balance_id['username']}  و آیدی عددی {$Balance_id['id']} از کد تخفیف {$partsdic[1]} استفاده کرد.";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherreport,
                    'text' => $text_report,
                ]);
            }
        }
        $keyboardextendfnished = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => "backorder"],
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        if ($Balance_id['agent'] == "f") {
            $valurcashbackextend = select("shopSetting", "*", "Namevalue", "chashbackextend", "select")['value'];
        } else {
            $valurcashbackextend = json_decode(select("shopSetting", "*", "Namevalue", "chashbackextend_agent", "select")['value'], true)[$Balance_id['agent']];
        }
        if (intval($valurcashbackextend) != 0) {
            $result = ($prodcut['price_product'] * $valurcashbackextend) / 100;
            $pricelastextend = $result;
            update("user", "Balance", $pricelastextend, "id", $Balance_id['id']);
            sendmessage($Balance_id['id'], "تبریک 🎉
📌 به عنوان هدیه تمدید مبلغ $result تومان حساب شما شارژ گردید", null, 'HTML');
        }
        $priceproductformat = number_format($prodcut['price_product']);
        $textextend = "✅ تمدید برای سرویس شما با موفقیت صورت گرفت

▫️نام سرویس : $usernamepanel
▫️نام محصول : {$prodcut['name_product']}
▫️مبلغ تمدید $priceproductformat تومان
";
        sendmessage($Balance_id['id'], $textextend, $keyboardextendfnished, 'HTML');
        if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['id'], $admin_ids)) {
            sendmessage($Balance_id['id'], "📌شما 2 امتیاز جدید کسب کردید.", null, 'html');
            $scorenew = $Balance_id['score'] + 2;
            update("user", "score", $scorenew, "id", $Balance_id['id']);
        }
        $timejalali = jdate('Y/m/d H:i:s');
        $text_report = "📣 جزئیات تمدید اکانت در ربات شما ثبت شد .

▫️آیدی عددی کاربر : <code>{$Balance_id['id']}</code>
▫️نام کاربری کاربر : @{$Balance_id['username']}
▫️نام کاربری کانفیگ :$usernamepanel
▫️موقعیت سرویس سرویس : {$nameloc['Service_location']}
▫️نام محصول : {$prodcut['name_product']}
▫️حجم محصول : {$prodcut['Volume_constraint']}
▫️زمان محصول : {$prodcut['Service_time']}
▫️مبلغ تمدید : $priceproductformat تومان
▫️موجودی قبل از خرید : $balanceformatsell تومان
▫️زمان خرید : $timejalali";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $otherservice,
                'text' => $text_report,
                'parse_mode' => "HTML"
            ]);
        }
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        if ($Payment_report['Payment_Method'] == "cart to cart" or $Payment_report['Payment_Method'] == "arze digital offline") {

            $textconfrom = "✅ پرداخت تایید شده
🔋 تمدید سرویس
🪪 نام کاربری کانفیگ : $usernamepanel
🛍 نام محصول : {$prodcut['name_product']}
🌏 نام لوکیشن : {$nameloc['Service_location']}
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💎 موجودی قبل تمدید  : {$Balance_id['Balance']}
💸 مبلغ پرداختی: $format_price_cart تومان
✍️ توضیحات : {$paymentNote}

";
            Editmessagetext($from_id, $message_id, $textconfrom, $Confirm_pay);
        }
    } elseif ($steppay[0] == "getextravolumeuser") {
        $steppay = explode("%", $steppay[1]);
        $volume = $steppay[1];
        $nameloc = select("invoice", "*", "username", $steppay[0], "select");
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $Balance_Low_user = 0;
        $inboundid = $marzban_list_get['inboundid'];
        if ($nameloc['inboundid'] != null) {
            $inboundid = $nameloc['inboundid'];
        }
        update("user", "Balance", $Balance_Low_user, "id", $Balance_id['id']);
        $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $steppay[0]);
        $data_for_database = json_encode(array(
            'volume_value' => $volume,
            'old_volume' => $DataUserOut['data_limit'],
            'expire_old' => $DataUserOut['expire']
        ));
        $dateacc = date('Y/m/d H:i:s');
        $type = "extra_user";
        $extra_volume = $ManagePanel->extra_volume($nameloc['username'], $marzban_list_get['code_panel'], $volume);
        if ($extra_volume['status'] == false) {
            $extra_volume['msg'] = json_encode($extra_volume['msg']);
            $textreports = "خطای خرید حجم اضافه
نام پنل : {$marzban_list_get['name_panel']}
نام کاربری سرویس : {$nameloc['username']}
دلیل خطا : {$extra_volume['msg']}";
            sendmessage($nameloc['id_user'], "❌خطایی در خرید حجم اضافه سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $textreports,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price,output) VALUES (:id_user,:username,:value,:type,:time,:price,:output)");
        $stmt->bindParam(':id_user', $Balance_id['id']);
        $stmt->bindParam(':username', $steppay[0]);
        $stmt->bindParam(':value', $data_for_database);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':time', $dateacc);
        $stmt->bindParam(':price', $Payment_report['price']);
        $stmt->bindParam(':output', json_encode($extra_volume));
        $stmt->execute();
        $keyboardextrafnished = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        $volumesformat = number_format($Payment_report['price'], 0);
        if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['id'], $admin_ids)) {
            sendmessage($Balance_id['id'], "📌شما 1 امتیاز جدید کسب کردید.", null, 'html');
            $scorenew = $Balance_id['score'] + 1;
            update("user", "score", $scorenew, "id", $Balance_id['id']);
        }
        $textvolume = "✅ افزایش حجم برای سرویس شما با موفقیت صورت گرفت

▫️نام سرویس  : {$steppay[0]}
▫️حجم اضافه : $volume گیگ

▫️مبلغ افزایش حجم : $volumesformat تومان";
        sendmessage($Balance_id['id'], $textvolume, $keyboardextrafnished, 'HTML');
        $volumes = $volume;
        if ($Payment_report['Payment_Method'] == "cart to cart") {
            $textconfrom = "✅ پرداخت تایید شده
🔋 خرید حجم اضافه
🛍 حجم خریداری شده  : $volumes گیگ
👤 نام کاربری کانفیگ {$steppay[0]}
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💎 موجودی قبل ازافزایش موجودی : {$Balance_id['Balance']}
💸 مبلغ پرداختی: $format_price_cart تومان
";
            Editmessagetext($from_id, $message_id, $textconfrom, $Confirm_pay);
        }
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        $text_report = "⭕️ یک کاربر حجم اضافه خریده است

اطلاعات کاربر : 
🪪 آیدی عددی : {$Balance_id['id']}
🛍 حجم خریداری شده  : $volumes گیگ
💰 مبلغ پرداختی : {$Payment_report['price']} تومان
👤 نام کاربری کانفیگ {$steppay[0]}
موجودی کاربر قبل خرید : {$Balance_id['Balance']}
";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $otherservice,
                'text' => $text_report,
                'parse_mode' => "HTML"
            ]);
        }
    } elseif ($steppay[0] == "getextratimeuser") {
        $steppay = explode("%", $steppay[1]);
        $tmieextra = $steppay[1];
        $nameloc = select("invoice", "*", "username", $steppay[0], "select");
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $Balance_Low_user = 0;
        $inboundid = $marzban_list_get['inboundid'];
        if ($nameloc['inboundid'] != false) {
            $inboundid = $nameloc['inboundid'];
        }
        update("user", "Balance", $Balance_Low_user, "id", $nameloc['id_user']);
        $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $steppay[0]);
        $data_for_database = json_encode(array(
            'day' => $tmieextra,
            'old_volume' => $DataUserOut['data_limit'],
            'expire_old' => $DataUserOut['expire']
        ));
        $dateacc = date('Y/m/d H:i:s');
        $type = "extra_time_user";
        $timeservice = $DataUserOut['expire'] - time();
        $day = floor($timeservice / 86400);
        $extra_time = $ManagePanel->extra_time($nameloc['username'], $marzban_list_get['code_panel'], $tmieextra);
        if ($extra_time['status'] == false) {
            $extra_time['msg'] = json_encode($extra_time['msg']);
            $textreports = "خطای خرید حجم اضافه
نام پنل : {$marzban_list_get['name_panel']}
نام کاربری سرویس : {$nameloc['username']}
دلیل خطا : {$extra_time['msg']}";
            sendmessage($from_id, "❌خطایی در خرید حجم اضافه سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $textreports,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price,output) VALUES (:id_user,:username,:value,:type,:time,:price,:output)");
        $stmt->bindParam(':id_user', $Balance_id['id']);
        $stmt->bindParam(':username', $steppay[0]);
        $stmt->bindParam(':value', $data_for_database);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':time', $dateacc);
        $stmt->bindParam(':price', $Payment_report['price']);
        $stmt->bindParam(':output', json_encode($extra_time));
        $stmt->execute();
        $keyboardextrafnished = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        $volumesformat = number_format($Payment_report['price']);
        if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['id'], $admin_ids)) {
            sendmessage($Balance_id['id'], "📌شما 1 امتیاز جدید کسب کردید.", null, 'html');
            $scorenew = $Balance_id['score'] + 1;
            update("user", "score", $scorenew, "id", $Balance_id['id']);
        }
        $textextratime = "✅ افزایش زمان برای سرویس شما با موفقیت صورت گرفت

▫️نام سرویس : {$steppay[0]}
▫️زمان اضافه : $tmieextra روز

▫️مبلغ افزایش زمان : $volumesformat تومان";
        sendmessage($Balance_id['id'], $textextratime, $keyboardextrafnished, 'HTML');
        if ($Payment_report['Payment_Method'] == "cart to cart") {
            $volumes = $tmieextra;
            $textconfrom = "✅ پرداخت تایید شده
🔋 خرید زمان اضافه
🛍 زمان خریداری شده  : $volumes روز
👤 نام کاربری کانفیگ {$steppay[0]}
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💎 موجودی قبل ازافزایش موجودی : {$Balance_id['Balance']}
💸 مبلغ پرداختی: $format_price_cart تومان
";
            Editmessagetext($from_id, $message_id, $textconfrom, $Confirm_pay);
        }
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        $text_report = "⭕️ یک کاربر زمان اضافه خریده است

اطلاعات کاربر : 
🪪 آیدی عددی : {$Balance_id['id']}
🛍 زمان خریداری شده  : $volumes روز
💰 مبلغ پرداختی : {$Payment_report['price']} تومان
👤 نام کاربری کانفیگ {$steppay[0]}";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $otherservice,
                'text' => $text_report,
            ]);
        }
    } else {
        $Balance_confrim = intval($Balance_id['Balance']) + intval($Payment_report['price']);
        update("user", "Balance", $Balance_confrim, "id", $Payment_report['id_user']);
        update("Payment_report", "payment_Status", "paid", "id_order", $Payment_report['id_order']);
        $Payment_report['price'] = number_format($Payment_report['price'], 0);
        $format_price_cart = $Payment_report['price'];
        if ($Payment_report['Payment_Method'] == "cart to cart" or $Payment_report['Payment_Method'] == "arze digital offline") {
            $textconfrom = "⭕️ یک پرداخت جدید انجام شده است
افزایش موجودی.
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💸 مبلغ پرداختی: $format_price_cart تومان
💎 موجودی قبل ازافزایش موجودی : {$Balance_id['Balance']}
✍️ توضیحات : {$paymentNote}";
            Editmessagetext($from_id, $message_id, $textconfrom, $Confirm_pay);
        }
        sendmessage($Payment_report['id_user'], "💎 کاربر گرامی مبلغ {$Payment_report['price']} تومان به کیف پول شما واریز گردید با تشکراز پرداخت شما.

🛒 کد پیگیری شما: {$Payment_report['id_order']}", null, 'HTML');
    }
}
function plisio($order_id, $price)
{
    $apinowpayments = select("PaySetting", "ValuePay", "NamePay", "apinowpayment", "select")['ValuePay'];
    $api_key = $apinowpayments;

    $url = 'https://api.plisio.net/api/v1/invoices/new';
    $url .= '?currency=TRX';
    $url .= '&amount=' . urlencode($price);
    $url .= '&order_number=' . urlencode($order_id);
    $url .= '&email=customer@plisio.net';
    $url .= '&order_name=plisio';
    $url .= '&language=fa';
    $url .= '&api_key=' . urlencode($api_key);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $raw = curl_exec($ch);
    curl_close($ch);
    $response = json_decode($raw, true);
    return $response['data'] ?? null;
}
function checkConnection($address, $port)
{
    $socket = @stream_socket_client("tcp://$address:$port", $errno, $errstr, 5);
    if ($socket) {
        fclose($socket);
        return true;
    } else {
        return false;
    }
}
function savedata($type, $namefiled, $valuefiled)
{
    global $from_id;
    if ($type == "clear") {
        $datauser = [];
        $datauser[$namefiled] = $valuefiled;
        $data = json_encode($datauser);
        update("user", "Processing_value", $data, "id", $from_id);
    } elseif ($type == "save") {
        $userdata = select("user", "*", "id", $from_id, "select");
        $dataperevieos = json_decode($userdata['Processing_value'], true);
        $dataperevieos[$namefiled] = $valuefiled;
        update("user", "Processing_value", json_encode($dataperevieos), "id", $from_id);
    }
}
function addFieldToTable($tableName, $fieldName, $defaultValue = null, $datatype = "VARCHAR(500)")
{
    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tableName"
    );
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($tableExists['count'] == 0)
        return;
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$pdo->query("SELECT DATABASE()")->fetchColumn(), $tableName, $fieldName]);
    $filedExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($filedExists['count'] != 0)
        return;
    $query = "ALTER TABLE $tableName ADD $fieldName $datatype";
    $statement = $pdo->prepare($query);
    $statement->execute();
    if ($defaultValue != null) {
        $stmt = $pdo->prepare("UPDATE $tableName SET $fieldName= ?");
        $stmt->bindParam(1, $defaultValue);
        $stmt->execute();
    }
    echo "The $fieldName field was added ✅";
}
function outtypepanel($typepanel, $message)
{
    global $from_id, $optionMarzban, $optionX_ui_single, $optionhiddfy, $optionalireza, $optionalireza_single, $optionmarzneshin, $option_mikrotik, $optionwg, $options_ui, $optioneylanpanel, $optionibsng;
    if ($typepanel == "marzban") {
        sendmessage($from_id, $message, $optionMarzban, 'HTML');
    } elseif ($typepanel == "x-ui_single") {
        sendmessage($from_id, $message, $optionX_ui_single, 'HTML');
    } elseif ($typepanel == "hiddify") {
        sendmessage($from_id, $message, $optionhiddfy, 'HTML');
    } elseif ($typepanel == "alireza_single") {
        sendmessage($from_id, $message, $optionalireza_single, 'HTML');
    } elseif ($typepanel == "marzneshin") {
        sendmessage($from_id, $message, $optionmarzneshin, 'HTML');
    } elseif ($typepanel == "WGDashboard") {
        sendmessage($from_id, $message, $optionwg, 'HTML');
    } elseif ($typepanel == "s_ui") {
        sendmessage($from_id, $message, $options_ui, 'HTML');
    } elseif ($typepanel == "ibsng") {
        sendmessage($from_id, $message, $optionibsng, 'HTML');
    } elseif ($typepanel == "mikrotik") {
        sendmessage($from_id, $message, $option_mikrotik, 'HTML');
    } elseif ($typepanel == "eylanpanel") {
        sendmessage($from_id, $message, $optioneylanpanel, 'HTML');
    }
}
function addBackgroundImage($urlimage, $qrCodeResult, $backgroundPath)
{
    if (!is_object($qrCodeResult) || !method_exists($qrCodeResult, 'getString')) {
        error_log('Invalid QR code data provided to addBackgroundImage.');
        return false;
    }

    $candidates = [];
    if (is_string($backgroundPath) && $backgroundPath !== '') {
        $candidates[] = $backgroundPath;
        $extension = strtolower(pathinfo($backgroundPath, PATHINFO_EXTENSION));
        if (in_array($extension, ['jpg', 'jpeg'], true)) {
            $base = substr($backgroundPath, 0, -strlen($extension) - 1);
            $candidates[] = $base . '.jpg';
            $candidates[] = $base . '.jpeg';
        } else {
            $candidates[] = $backgroundPath . '.jpg';
            $candidates[] = $backgroundPath . '.jpeg';
        }
    }

    $resolvedPath = null;
    foreach (array_unique($candidates) as $candidate) {
        $pathsToCheck = [$candidate];
        if ($candidate[0] !== DIRECTORY_SEPARATOR) {
            $pathsToCheck[] = __DIR__ . DIRECTORY_SEPARATOR . ltrim($candidate, DIRECTORY_SEPARATOR);
        }
        foreach ($pathsToCheck as $path) {
            if (is_file($path) && is_readable($path)) {
                $resolvedPath = $path;
                break 2;
            }
        }
    }

    if ($resolvedPath === null) {
        error_log("Background image not found for QR code generation: {$backgroundPath}");
        return false;
    }

    $qrCodeImage = @imagecreatefromstring($qrCodeResult->getString());
    if ($qrCodeImage === false) {
        error_log('Unable to create QR code image resource.');
        return false;
    }

    $backgroundData = @file_get_contents($resolvedPath);
    if ($backgroundData === false) {
        imagedestroy($qrCodeImage);
        error_log("Unable to read background image: {$resolvedPath}");
        return false;
    }

    $backgroundImage = @imagecreatefromstring($backgroundData);
    if ($backgroundImage === false) {
        imagedestroy($qrCodeImage);
        error_log("Unable to create background image resource from file: {$resolvedPath}");
        return false;
    }

    $qrCodeWidth = imagesx($qrCodeImage);
    $qrCodeHeight = imagesy($qrCodeImage);
    $backgroundWidth = imagesx($backgroundImage);
    $backgroundHeight = imagesy($backgroundImage);

    $x = ($backgroundWidth - $qrCodeWidth) / 2;
    $y = ($backgroundHeight - $qrCodeHeight) / 2;

    imagecopy($backgroundImage, $qrCodeImage, (int) $x, (int) $y, 0, 0, $qrCodeWidth, $qrCodeHeight);

    $result = imagepng($backgroundImage, $urlimage);

    imagedestroy($qrCodeImage);
    imagedestroy($backgroundImage);

    if ($result === false) {
        error_log("Failed to save QR code with background to {$urlimage}");
    }

    return $result !== false;
}
function checktelegramip()
{
    global $telegramStrictIpValidation;

    $strictValidation = $telegramStrictIpValidation;
    if (!is_bool($strictValidation)) {
        $strictValidation = true;
    }

    if ($strictValidation === false) {
        return true;
    }

    $clientIp = getClientIpConsideringProxies();
    if ($clientIp === null) {
        return false;
    }

    $telegramIpRanges = [
        ['lower' => '149.154.160.0', 'upper' => '149.154.175.255'],
        ['lower' => '91.108.4.0', 'upper' => '91.108.7.255'],
        ['lower' => '2001:67c:4e8::', 'upper' => '2001:67c:4e8:ffff:ffff:ffff:ffff:ffff'],
    ];

    foreach ($telegramIpRanges as $range) {
        if (isClientIpInRange($clientIp, $range['lower'], $range['upper'])) {
            return true;
        }
    }

    return false;
}

function getClientIpConsideringProxies()
{
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_TRUE_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
        'HTTP_FORWARDED',
    ];

    foreach ($headers as $header) {
        if (empty($_SERVER[$header]) || !is_string($_SERVER[$header])) {
            continue;
        }

        $rawValue = trim($_SERVER[$header]);
        if ($rawValue === '') {
            continue;
        }

        $candidateIps = extractClientIpsFromHeader($rawValue, $header);
        foreach ($candidateIps as $candidate) {
            $candidate = normaliseProxyIpCandidate($candidate);
            if ($candidate === null || $candidate === '') {
                continue;
            }

            if (!filter_var($candidate, FILTER_VALIDATE_IP)) {
                continue;
            }

            if (!isPublicIpAddress($candidate)) {
                continue;
            }

            return $candidate;
        }
    }

    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
    if (is_string($remoteAddr)) {
        $remoteAddr = trim($remoteAddr);
        if ($remoteAddr !== '' && filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
            return $remoteAddr;
        }
    }

    return null;
}

function extractClientIpsFromHeader($value, $header)
{
    switch ($header) {
        case 'HTTP_X_FORWARDED_FOR':
            $parts = preg_split('/\s*,\s*/', $value);
            return $parts !== false ? $parts : [];
        case 'HTTP_FORWARDED':
            $matches = [];
            preg_match_all('/for=([^;,"]+|"[^"]+")/i', $value, $matches);
            $results = [];
            foreach ($matches[1] ?? [] as $match) {
                $results[] = $match;
            }
            return $results;
        default:
            return [$value];
    }
}

function normaliseProxyIpCandidate($candidate)
{
    if (!is_string($candidate)) {
        return null;
    }

    $candidate = trim($candidate);
    if ($candidate === '') {
        return null;
    }

    $candidate = trim($candidate, "\"' ");

    if (stripos($candidate, 'for=') === 0) {
        $candidate = substr($candidate, 4);
        $candidate = ltrim($candidate, '=');
    }

    $candidate = trim($candidate, "\"' ");

    if (strpos($candidate, '[') === 0) {
        $closingBracket = strpos($candidate, ']');
        if ($closingBracket !== false) {
            $candidate = substr($candidate, 1, $closingBracket - 1);
        }
    }

    $candidate = trim($candidate, '[]');

    if (strpos($candidate, ':') !== false && substr_count($candidate, ':') === 1 && strpos($candidate, '.') !== false) {
        [$possibleIp, $possiblePort] = explode(':', $candidate, 2);
        $possiblePort = trim($possiblePort);
        if ($possiblePort === '' || ctype_digit(str_replace([' ', "\t"], '', $possiblePort))) {
            $candidate = $possibleIp;
        }
    }

    if (strpos($candidate, '%') !== false) {
        $candidateWithoutZone = preg_replace('/%.*$/', '', $candidate);
        if (is_string($candidateWithoutZone)) {
            $candidate = $candidateWithoutZone;
        }
    }

    $candidate = trim($candidate);

    return $candidate === '' ? null : $candidate;
}

function isPublicIpAddress($ipAddress)
{
    return filter_var(
        $ipAddress,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) !== false;
}

function isClientIpInRange($clientIp, $lowerBound, $upperBound)
{
    $clientPacked = inet_pton($clientIp);
    $lowerPacked = inet_pton($lowerBound);
    $upperPacked = inet_pton($upperBound);

    if ($clientPacked === false || $lowerPacked === false || $upperPacked === false) {
        return false;
    }

    $length = strlen($clientPacked);
    if ($length !== strlen($lowerPacked) || $length !== strlen($upperPacked)) {
        return false;
    }

    return strcmp($clientPacked, $lowerPacked) >= 0 && strcmp($clientPacked, $upperPacked) <= 0;
}

function resolveCronHttpDirectory(): string
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $configured = null;
    if (defined('CRON_HTTP_BASE_PATH')) {
        $configured = CRON_HTTP_BASE_PATH;
    } elseif (($env = getenv('CRON_HTTP_BASE_PATH')) !== false) {
        $configured = $env;
    }

    if (is_string($configured)) {
        $configured = trim($configured);
        if ($configured === '' || $configured === '/') {
            return $cache = '';
        }

        return $cache = trim($configured, "/\\");
    }

    $preferredOrder = ['cronbot', 'cron'];
    foreach ($preferredOrder as $candidate) {
        $candidate = trim($candidate, "/\\");
        if ($candidate === '') {
            continue;
        }

        if (is_dir(APP_ROOT_PATH . '/' . $candidate)) {
            return $cache = $candidate;
        }
    }

    return $cache = 'cronbot';
}

function getCronHttpRelativePrefix(): string
{
    $directory = resolveCronHttpDirectory();
    if ($directory === '') {
        return '';
    }

    return trim($directory, "/\\") . '/';
}

function buildCronScriptUrlByHost(string $host, string $script): string
{
    $host = trim($host);
    if ($host === '') {
        $host = 'localhost';
    }

    $base = preg_match('~^https?://~i', $host) ? rtrim($host, '/') : 'https://' . $host;
    $script = ltrim($script, '/');

    $prefix = getCronHttpRelativePrefix();
    if ($prefix !== '' && substr($prefix, -1) !== '/') {
        $prefix .= '/';
    }

    return $base . '/' . $prefix . $script;
}
function addCronIfNotExists($cronCommand)
{
    $commands = is_array($cronCommand) ? $cronCommand : [$cronCommand];
    $commands = array_values(array_filter(array_map('trim', $commands), static function ($command) {
        return $command !== '';
    }));

    if (empty($commands)) {
        return true;
    }

    $logContext = implode('; ', $commands);

    if (!isShellExecAvailable()) {
        error_log('shell_exec is not available; unable to register cron job(s): ' . $logContext);
        return false;
    }

    $crontabBinary = getCrontabBinary();
    if ($crontabBinary === null) {
        error_log('crontab executable not found; unable to register cron job(s): ' . $logContext);
        return false;
    }

    $existingCronJobs = runShellCommand(sprintf('%s -l 2>/dev/null', escapeshellarg($crontabBinary)));
    $existingCronJobs = trim((string) $existingCronJobs);
    $cronLines = $existingCronJobs === '' ? [] : preg_split('/\r?\n/', $existingCronJobs);
    $cronLines = array_values(array_filter(array_map('trim', $cronLines), static function ($line) {
        return $line !== '' && strpos($line, '#') !== 0;
    }));

    $newLineAdded = false;
    foreach ($commands as $command) {
        if (!in_array($command, $cronLines, true)) {
            $cronLines[] = $command;
            $newLineAdded = true;
        }
    }

    if (!$newLineAdded) {
        return true;
    }

    $cronLines = array_values(array_unique($cronLines));
    $cronContent = implode(PHP_EOL, $cronLines) . PHP_EOL;

    $temporaryFile = tempnam(sys_get_temp_dir(), 'cron');
    if ($temporaryFile === false) {
        error_log('Unable to create temporary file for cron job registration.');
        return false;
    }

    if (file_put_contents($temporaryFile, $cronContent) === false) {
        error_log('Unable to write cron configuration to temporary file: ' . $temporaryFile);
        unlink($temporaryFile);
        return false;
    }

    runShellCommand(sprintf('%s %s', escapeshellarg($crontabBinary), escapeshellarg($temporaryFile)));
    unlink($temporaryFile);

    return true;
}

function activecron()
{
    global $domainhosts;

    $host = null;
    if (isset($domainhosts) && is_string($domainhosts) && trim($domainhosts) !== '') {
        $host = $domainhosts;
    }

    if ($host === null || trim((string) $host) === '') {
        $host = $_SERVER['HTTP_HOST'] ?? null;
    }

    if ($host === null || trim((string) $host) === '') {
        $host = 'localhost';
    }

    $normalizedHost = preg_match('~^https?://~i', $host)
        ? rtrim($host, '/')
        : 'https://' . trim($host, '/');

    $cronEndpoint = $normalizedHost . '/cron/cron.php';

    $cronCommands = ["*/1 * * * * curl {$cronEndpoint}"];

    addCronIfNotExists($cronCommands);
}


function getCronJobDefinitions(): array
{
    return [
        'statusday' => [
            'script' => 'statusday.php',
            'admin_label' => 'کرون وضعیت روزانه',
            'instruction' => '🕒 بررسی وضعیت روزانه — %s',
            'default' => ['unit' => 'minute', 'value' => 15],
        ],
        'croncard' => [
            'script' => 'croncard.php',
            'admin_label' => 'کرون کارت‌به‌کارت',
            'instruction' => '💳 انجام تراکنش‌های کارت‌به‌کارت — %s',
            'default' => ['unit' => 'minute', 'value' => 1],
        ],
        'notifications' => [
            'script' => 'NoticationsService.php',
            'admin_label' => 'کرون اعلان‌ها',
            'instruction' => '🔔 سرویس اعلان‌ها (Notification Service) — %s',
            'default' => ['unit' => 'minute', 'value' => 1],
        ],
        'payment_expire' => [
            'script' => 'payment_expire.php',
            'admin_label' => 'کرون انقضای پرداخت',
            'instruction' => '💳 بررسی انقضای پرداخت‌ها — %s',
            'default' => ['unit' => 'minute', 'value' => 5],
        ],
        'sendmessage' => [
            'script' => 'sendmessage.php',
            'admin_label' => 'کرون ارسال پیام',
            'instruction' => '📩 ارسال پیام‌ها — %s',
            'default' => ['unit' => 'minute', 'value' => 1],
        ],
        'plisio' => [
            'script' => 'plisio.php',
            'admin_label' => 'کرون Plisio',
            'instruction' => '💰 پردازش پرداخت‌های Plisio — %s',
            'default' => ['unit' => 'minute', 'value' => 3],
        ],
        'activeconfig' => [
            'script' => 'activeconfig.php',
            'admin_label' => 'کرون فعال‌سازی تنظیمات',
            'instruction' => '⚙️ فعال‌سازی تنظیمات جدید — %s',
            'default' => ['unit' => 'minute', 'value' => 1],
        ],
        'disableconfig' => [
            'script' => 'disableconfig.php',
            'admin_label' => 'کرون غیرفعال‌سازی تنظیمات',
            'instruction' => '🚫 غیرفعال‌سازی تنظیمات قدیمی — %s',
            'default' => ['unit' => 'minute', 'value' => 1],
        ],
        'iranpay' => [
            'script' => 'iranpay1.php',
            'admin_label' => 'کرون ایران‌پی',
            'instruction' => '🇮🇷 بررسی وضعیت پرداخت ایران‌پی — %s',
            'default' => ['unit' => 'minute', 'value' => 1],
        ],
        'backup' => [
            'script' => 'backupbot.php',
            'admin_label' => 'کرون بکاپ',
            'instruction' => '🗂 تهیه نسخه‌ی پشتیبان (Backup) — %s',
            'default' => ['unit' => 'hour', 'value' => 5],
        ],
        'gift' => [
            'script' => 'gift.php',
            'admin_label' => 'کرون هدایا',
            'instruction' => '🎁 ارسال هدایا (Gift System) — %s',
            'default' => ['unit' => 'minute', 'value' => 2],
        ],
        'lottery' => [
            'script' => 'lottery.php',
            'admin_label' => 'قرعه‌کشی شبانه',
            'instruction' => '🎁 قرعه‌کشی شبانه — %s',
            'default' => ['unit' => 'minute', 'value' => 1],
        ],
        'expireagent' => [
            'script' => 'expireagent.php',
            'admin_label' => 'کرون انقضای نمایندگان',
            'instruction' => '👥 بررسی انقضای نمایندگان — %s',
            'default' => ['unit' => 'minute', 'value' => 30],
        ],
        'on_hold' => [
            'script' => 'on_hold.php',
            'admin_label' => 'کرون سرویس‌های معلق',
            'instruction' => '⏸ بررسی وضعیت سفارش‌های معلق — %s',
            'default' => ['unit' => 'minute', 'value' => 15],
        ],
        'configtest' => [
            'script' => 'configtest.php',
            'admin_label' => 'کرون تست تنظیمات',
            'instruction' => '🧪 تست تنظیمات سیستم — %s',
            'default' => ['unit' => 'minute', 'value' => 2],
        ],
        'uptime_node' => [
            'script' => 'uptime_node.php',
            'admin_label' => 'کرون Uptime نود',
            'instruction' => '🌐 بررسی Uptime نودها — %s',
            'default' => ['unit' => 'minute', 'value' => 15],
        ],
        'uptime_panel' => [
            'script' => 'uptime_panel.php',
            'admin_label' => 'کرون Uptime پنل',
            'instruction' => '🖥 بررسی Uptime پنل‌ها — %s',
            'default' => ['unit' => 'minute', 'value' => 15],
        ],
    ];
}

function getDefaultCronSchedules(): array
{
    $defaults = [];
    foreach (getCronJobDefinitions() as $key => $definition) {
        $defaults[$key] = $definition['default'];
    }

    return $defaults;
}

function normalizeCronScheduleConfig(array $config, array $default): array
{
    $unit = isset($config['unit']) ? strtolower((string) $config['unit']) : $default['unit'];
    $validUnits = ['minute', 'hour', 'day', 'disabled'];
    if (!in_array($unit, $validUnits, true)) {
        $unit = $default['unit'];
    }

    $value = isset($config['value']) ? (int) $config['value'] : $default['value'];
    if ($unit === 'disabled') {
        $value = 0;
    } elseif ($value < 1) {
        $value = $default['value'];
    }

    return [
        'unit' => $unit,
        'value' => $value,
    ];
}

function ensureCronRuntimeStateTable(PDO $pdo): void
{
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cron_runtime_state (
            job_key VARCHAR(255) PRIMARY KEY,
            last_run BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            unit VARCHAR(20) NOT NULL DEFAULT 'minute',
            value INT(10) UNSIGNED NOT NULL DEFAULT 1,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (PDOException $e) {
        error_log('ensureCronRuntimeStateTable: ' . $e->getMessage());
    }
}

function loadCronRuntimeState(PDO $pdo): array
{
    $state = [];
    try {
        $stmt = $pdo->query("SELECT job_key, last_run FROM cron_runtime_state");
        if ($stmt !== false) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $jobKey = isset($row['job_key']) ? trim((string) $row['job_key']) : '';
                if ($jobKey === '') {
                    continue;
                }
                $state[$jobKey] = isset($row['last_run']) ? (int) $row['last_run'] : 0;
            }
        }
    } catch (PDOException $e) {
        error_log('loadCronRuntimeState: ' . $e->getMessage());
    }

    return $state;
}

function setCronJobLastRun(PDO $pdo, string $jobKey, int $timestamp): void
{
    $jobKey = trim($jobKey);
    if ($jobKey === '') {
        return;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO cron_runtime_state (job_key, last_run) VALUES (:job_key, :last_run) ON DUPLICATE KEY UPDATE last_run = VALUES(last_run)");
        $stmt->bindValue(':job_key', $jobKey, PDO::PARAM_STR);
        $stmt->bindValue(':last_run', $timestamp, PDO::PARAM_INT);
        $stmt->execute();
    } catch (PDOException $e) {
        error_log('setCronJobLastRun: ' . $e->getMessage());
    }
}

function loadCronSchedules(): array
{
    $definitions = getCronJobDefinitions();
    $schedules = getDefaultCronSchedules();
    $pdo = getDatabaseConnection();
    if (!($pdo instanceof PDO)) {
        return $schedules;
    }

    ensureCronRuntimeStateTable($pdo);

    try {
        $stmt = $pdo->query("SELECT job_key, unit, value, enabled FROM cron_runtime_state");
        if ($stmt === false) {
            return $schedules;
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $jobKey = isset($row['job_key']) ? trim((string) $row['job_key']) : '';
            if ($jobKey === '' || !isset($definitions[$jobKey])) {
                continue;
            }

            $unit = $row['unit'] ?? $definitions[$jobKey]['default']['unit'] ?? 'minute';
            $value = isset($row['value']) ? (int) $row['value'] : ($definitions[$jobKey]['default']['value'] ?? 1);
            $enabled = isset($row['enabled']) && (int) $row['enabled'] === 0 ? false : true;
            $scheduleConfig = ['unit' => $unit, 'value' => $value];
            if (!$enabled) {
                $scheduleConfig['unit'] = 'disabled';
                $scheduleConfig['value'] = 1;
            }

            $schedules[$jobKey] = normalizeCronScheduleConfig($scheduleConfig, $definitions[$jobKey]['default']);
        }
    } catch (PDOException $e) {
        error_log('loadCronSchedules: ' . $e->getMessage());
    }

    return $schedules;
}

function updateCronSchedule(string $jobKey, array $config): bool
{
    $definitions = getCronJobDefinitions();
    if (!isset($definitions[$jobKey])) {
        return false;
    }

    $normalized = normalizeCronScheduleConfig($config, $definitions[$jobKey]['default']);
    $pdo = getDatabaseConnection();
    if (!($pdo instanceof PDO)) {
        return false;
    }

    ensureCronRuntimeStateTable($pdo);
    $enabled = $normalized['unit'] === 'disabled' ? 0 : 1;

    try {
        $stmt = $pdo->prepare("INSERT INTO cron_runtime_state (job_key, unit, value, enabled) VALUES (:job_key, :unit, :value, :enabled) ON DUPLICATE KEY UPDATE unit = VALUES(unit), value = VALUES(value), enabled = VALUES(enabled)");
        $stmt->bindValue(':job_key', $jobKey, PDO::PARAM_STR);
        $stmt->bindValue(':unit', $normalized['unit'], PDO::PARAM_STR);
        $stmt->bindValue(':value', $normalized['value'], PDO::PARAM_INT);
        $stmt->bindValue(':enabled', $enabled, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log('updateCronSchedule: ' . $e->getMessage());
        return false;
    }
}

function describeCronSchedule(array $config): string
{
    $unitLabels = [
        'minute' => 'دقیقه',
        'hour' => 'ساعت',
        'day' => 'روز',
        'disabled' => 'غیرفعال',
    ];

    $unit = $config['unit'] ?? 'minute';
    $value = isset($config['value']) ? (int) $config['value'] : 1;
    if ($value < 1) {
        $value = 1;
    }

    if ($unit === 'disabled') {
        return $unitLabels['disabled'];
    }

    $unitLabel = $unitLabels[$unit] ?? $unitLabels['minute'];
    return sprintf('هر %d %s', $value, $unitLabel);
}

function shouldRunCronJob(array $config, int $minute, int $hour, int $dayOfYear): bool
{
    $unit = $config['unit'] ?? 'minute';
    $value = isset($config['value']) ? (int) $config['value'] : 1;
    if ($value < 1) {
        $value = 1;
    }

    if ($unit === 'disabled') {
        return false;
    }

    switch ($unit) {
        case 'minute':
            return $minute % $value === 0;
        case 'hour':
            return $minute === 0 && ($hour % $value === 0);
        case 'day':
            return $minute === 0 && $hour === 0 && ($dayOfYear % $value === 0);
        default:
            return false;
    }
}

function buildCronInstructionDetails(string $domainHost): string
{
    $definitions = getCronJobDefinitions();
    $schedules = loadCronSchedules();
    $parts = [];

    foreach ($definitions as $key => $definition) {
        if (!isset($definition['instruction'], $definition['script'])) {
            continue;
        }
        $schedule = $schedules[$key] ?? $definition['default'];
        $description = describeCronSchedule($schedule);
        $title = sprintf($definition['instruction'], $description);
        $endpoint = buildCronScriptUrlByHost($domainHost, $definition['script']);
        $parts[] = "<b>{$title}</b>\n<code>curl " . htmlspecialchars($endpoint, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</code>";
    }

    return implode("\n\n", $parts);
}

function inlineFixer($str, int $count_button = 1)
{
    $str = trim($str);
    if (preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}]/u', $str)) {
        if ($count_button >= 1) {
            switch ($count_button) {
                case 1:
                    $maxLength = 56;
                    break;
                case 2:
                    $maxLength = 24;
                    break;
                case 3:
                    $maxLength = 14;
                    break;
                default:
                    $maxLength = 2;
            }
            $visualLength = 2;
            $trimmedString = '';
            foreach (mb_str_split($str) as $char) {
                if (preg_match('/[\x{1F300}-\x{1F6FF}\x{1F900}-\x{1F9FF}\x{1F1E6}-\x{1F1FF}]/u', $char)) {
                    $visualLength += 2;
                } else
                    $visualLength++;

                if ($visualLength > $maxLength)
                    break;

                $trimmedString .= $char;
            }
            if ($visualLength > $maxLength) {
                return trim($trimmedString) . '..';
            }
        }
    }
    return trim($str);
}
function createInvoice($amount)
{
    global $from_id, $domainhosts;
    $PaySetting = select("PaySetting", "*", "NamePay", "apiiranpay", "select")['ValuePay'];
    $walletaddress = select("PaySetting", "*", "NamePay", "walletaddress", "select")['ValuePay'];

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://pay.melorinabeauty.ir/api/factor/create',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('amount' => $amount, 'address' => $walletaddress, 'base' => 'trx'),
        CURLOPT_HTTPHEADER => array(
            'Authorization: Token ' . $PaySetting
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    return json_decode($response, true);
}
function verifpay($id)
{
    global $from_id, $domainhosts;
    $PaySetting = select("PaySetting", "*", "NamePay", "apiiranpay", "select")['ValuePay'];
    $walletaddress = select("PaySetting", "*", "NamePay", "walletaddress", "select")['ValuePay'];
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://pay.melorinabeauty.ir/api/factor/status?id=' . $id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'Authorization: Token ' . $PaySetting
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    return $response;
}
function createInvoiceiranpay1($amount, $id_invoice)
{
    global $domainhosts;
    $PaySetting = select("PaySetting", "*", "NamePay", "marchent_floypay", "select")['ValuePay'];
    $curl = curl_init();
    $amount = intval($amount);
    $data = [
        "ApiKey" => $PaySetting,
        "Hash_id" => $id_invoice,
        "Amount" => $amount . "0",
        "CallbackURL" => "https://$domainhosts/payment/iranpay1.php"
    ];
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://tetra98.ir/api/create_order",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array(
            'accept: application/json',
            'Content-Type: application/json'
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true);
}
function verifyxvoocher($code)
{
    $PaySetting = select("PaySetting", "*", "NamePay", "apiiranpay", "select")['ValuePay'];
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://bot.donatekon.com/api/transaction/verify/" . $code,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'accept: application/json',
            'Content-Type: application/json',
            'Authorization: ' . $PaySetting
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true);
}
function sanitizeUserName($userName)
{
    $forbiddenCharacters = [
        "'",
        "\"",
        "<",
        ">",
        "--",
        "#",
        ";",
        "\\",
        "%",
        "(",
        ")"
    ];

    foreach ($forbiddenCharacters as $char) {
        $userName = str_replace($char, "", $userName);
    }

    return $userName;
}
function publickey()
{
    $randomBytes = static function (int $length) {
        if (function_exists('random_bytes')) {
            try {
                return random_bytes($length);
            } catch (Throwable $exception) {
                error_log('random_bytes failed: ' . $exception->getMessage());
            }
        }

        if (class_exists('\\ParagonIE_Sodium_Compat') && method_exists('\\ParagonIE_Sodium_Compat', 'randombytes_buf')) {
            try {
                return \ParagonIE_Sodium_Compat::randombytes_buf($length);
            } catch (Throwable $exception) {
                error_log('sodium_compat randombytes_buf failed: ' . $exception->getMessage());
            }
        }

        return null;
    };

    if (function_exists('sodium_crypto_box_keypair')) {
        try {
            $privateKey = sodium_crypto_box_keypair();
            $privateKeyEncoded = base64_encode(sodium_crypto_box_secretkey($privateKey));
            $publicKey = sodium_crypto_box_publickey($privateKey);
            $publicKeyEncoded = base64_encode($publicKey);
            $presharedBytes = $randomBytes(32);

            if ($presharedBytes === null) {
                throw new RuntimeException('Unable to generate secure preshared key.');
            }

            return [
                'private_key' => $privateKeyEncoded,
                'public_key' => $publicKeyEncoded,
                'preshared_key' => base64_encode($presharedBytes)
            ];
        } catch (Throwable $exception) {
            error_log('libsodium key generation failed: ' . $exception->getMessage());
        }
    }

    if (!class_exists('\\ParagonIE_Sodium_Compat')) {
        $sodiumCompatAutoloaders = [
            APP_ROOT_PATH . '/vendor/autoload.php',
            APP_ROOT_PATH . '/vendor/paragonie/sodium_compat/autoload.php'
        ];

        foreach ($sodiumCompatAutoloaders as $autoloadPath) {
            if (is_readable($autoloadPath)) {
                require_once $autoloadPath;
            }
        }
        unset($sodiumCompatAutoloaders, $autoloadPath);
    }

    if (class_exists('\\ParagonIE_Sodium_Compat') && method_exists('\\ParagonIE_Sodium_Compat', 'crypto_box_keypair')) {
        try {
            $privateKey = \ParagonIE_Sodium_Compat::crypto_box_keypair();
            $privateKeyEncoded = base64_encode(\ParagonIE_Sodium_Compat::crypto_box_secretkey($privateKey));
            $publicKey = \ParagonIE_Sodium_Compat::crypto_box_publickey($privateKey);
            $publicKeyEncoded = base64_encode($publicKey);
            $presharedBytes = $randomBytes(32);

            if ($presharedBytes === null) {
                throw new RuntimeException('Unable to generate secure preshared key.');
            }

            return [
                'private_key' => $privateKeyEncoded,
                'public_key' => $publicKeyEncoded,
                'preshared_key' => base64_encode($presharedBytes)
            ];
        } catch (Throwable $exception) {
            error_log('sodium_compat key generation failed: ' . $exception->getMessage());
        }
    }

    return [
        'status' => false,
        'msg' => 'Libsodium not available'
    ];
}
function languagechange($path_dir)
{
    $setting = select("setting", "*");
    $data = json_decode(file_get_contents($path_dir), true);
    if (intval($setting['languageen'] ?? 0) == 1) {
        return $data['en'];
    } elseif (intval($setting['languageru'] ?? 0) == 1) {
        return $data['ru'];
    } else {
        return $data['fa'];
    }
}
function generateAuthStr($length = 10)
{
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    return substr(str_shuffle(str_repeat($characters, ceil($length / strlen($characters)))), 0, $length);
}
function createqrcode($contents)
{
    $builder = new Builder(
        writer: new PngWriter(),
        writerOptions: [],
        data: $contents,
        encoding: new Encoding('UTF-8'),
        errorCorrectionLevel: ErrorCorrectionLevel::High,
        size: 500,
        margin: 10,
    );

    $result = $builder->build();
    return $result;
}
function sanitize_recursive(array $data): array
{
    $sanitized_data = [];
    foreach ($data as $key => $value) {
        $sanitized_key = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
        if (is_array($value)) {
            $sanitized_data[$sanitized_key] = sanitize_recursive($value);
        } elseif (is_string($value)) {
            $sanitized_data[$sanitized_key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        } elseif (is_int($value)) {
            $sanitized_data[$sanitized_key] = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
        } elseif (is_float($value)) {
            $sanitized_data[$sanitized_key] = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        } elseif (is_bool($value) || is_null($value)) {
            $sanitized_data[$sanitized_key] = $value;
        } else {
            $sanitized_data[$sanitized_key] = $value;
        }
    }
    return $sanitized_data;
}

function check_active_btn($keyboard, $text_var)
{
    $trace_keyboard = json_decode($keyboard, true)['keyboard'];
    $status = false;
    foreach ($trace_keyboard as $key => $callback_set) {
        foreach ($callback_set as $keyboard_key => $keyboard) {
            if ($keyboard['text'] == $text_var) {
                $status = true;
                break;
            }
        }
    }
    return $status;
}
function CreatePaymentNv($invoice_id, $amount)
{
    global $domainhosts;
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "marchentpaynotverify", "select")['ValuePay'];
    $data = [
        'api_key' => $PaySetting,
        'amount' => $amount,
        'callback_url' => "https://" . $domainhosts . "/payment/paymentnv/back.php",
        'desc' => $invoice_id
    ];
    $data = json_encode($data);
    $ch = curl_init("https://donatekon.com/pay/api/dargah/create");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data)
        )
    );
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}
function deleteFolder($folderPath)
{
    if (!is_dir($folderPath))
        return false;

    $files = array_diff(scandir($folderPath), ['.', '..']);

    foreach ($files as $file) {
        $filePath = $folderPath . DIRECTORY_SEPARATOR . $file;
        if (is_dir($filePath)) {
            deleteFolder($filePath);
        } else {
            unlink($filePath);
        }
    }

    return rmdir($folderPath);
}
function isBase64($string)
{
    if (base64_encode(base64_decode($string, true)) === $string) {
        return true;
    }
    return false;
}
function sendMessageService($panel_info, $config, $sub_link, $username_service, $reply_markup, $caption, $invoice_id, $user_id = null, $image = 'images.jpg')
{
    global $setting, $from_id;
    $config = normalizeServiceConfigs($config);
    if (!check_active_btn($setting['keyboardmain'], "text_help"))
        $reply_markup = null;
    $user_id = $user_id == null ? $from_id : $user_id;
    $STATUS_SEND_MESSAGE_PHOTO = $panel_info['config'] == "onconfig" && count($config) != 1 ? false : true;
    $out_put_qrcode = "";
    if ($panel_info['type'] == "Manualsale" || $panel_info['type'] == "ibsng" || $panel_info['type'] == "mikrotik") {
    }
    if ($panel_info['sublink'] == "onsublink" && $panel_info['config']) {
        $out_put_qrcode = $sub_link;
    } elseif ($panel_info['sublink'] == "onsublink") {
        $out_put_qrcode = $sub_link;
    } elseif ($panel_info['config'] == "onconfig") {
        $out_put_qrcode = $config[0];
    }
    if ($STATUS_SEND_MESSAGE_PHOTO) {
        $urlimage = "$user_id$invoice_id.png";
        $qrCode = createqrcode($out_put_qrcode);
        file_put_contents($urlimage, $qrCode->getString());
        if (!addBackgroundImage($urlimage, $qrCode, $image)) {
            error_log("Unable to apply background image for QR code using path '{$image}'");
        }
        telegram('sendphoto', [
            'chat_id' => $user_id,
            'photo' => new CURLFile($urlimage),
            'reply_markup' => $reply_markup,
            'caption' => $caption,
            'parse_mode' => "HTML",
        ]);
        unlink($urlimage);
        if ($panel_info['type'] == "WGDashboard") {
            $urlimage = "{$panel_info['inboundid']}_{$username_service}.conf";
            file_put_contents($urlimage, $sub_link);
            sendDocument($user_id, $urlimage, "⚙️ کانفیگ شما");
            unlink($urlimage);
        }
    } else {
        sendmessage($user_id, $caption, $reply_markup, 'HTML');
    }
    if ($panel_info['config'] == "onconfig" && $setting['status_keyboard_config'] == "1") {
        if (is_array($config)) {
            $validConfigs = array_values(array_filter($config, function ($item) {
                return is_string($item) && trim($item) !== '';
            }));

            if (!empty($validConfigs)) {
                $keyboardPayload = keyboard_config($validConfigs, $invoice_id, false);
                $configButtonCount = 0;
                $keyboardData = json_decode($keyboardPayload, true);

                if (is_array($keyboardData) && isset($keyboardData['inline_keyboard']) && is_array($keyboardData['inline_keyboard'])) {
                    foreach ($keyboardData['inline_keyboard'] as $row) {
                        if (!is_array($row)) {
                            continue;
                        }

                        foreach ($row as $button) {
                            if (!is_array($button)) {
                                continue;
                            }

                            $buttonText = $button['text'] ?? '';
                            $callbackData = $button['callback_data'] ?? '';

                            if ($buttonText === 'دریافت کانفیگ' && is_string($callbackData) && strpos($callbackData, 'configget_') === 0) {
                                ++$configButtonCount;
                            }
                        }
                    }
                } else {
                    error_log('Failed to decode keyboard payload for configuration prompt');
                }

                if ($configButtonCount > 1) {
                    sendmessage($user_id, "📌 جهت دریافت کانفیگ روی دکمه دریافت کانفیگ کلیک کنید", $keyboardPayload, 'HTML');
                }
            }
        }
    }
}
function isValidInvitationCode($setting, $fromId, $verfy_status)
{

    if ($setting['verifybucodeuser'] == "onverify" && $verfy_status != 1) {
        sendmessage($fromId, "حساب کاربری شما با موفقیت احرازهویت گردید", null, 'html');
        update("user", "verify", "1", "id", $fromId);
        update("user", "cardpayment", "1", "id", $fromId);
    }
}
function createPayZarinpal($price, $order_id)
{
    global $domainhosts;
    $marchent_zarinpal = select("PaySetting", "ValuePay", "NamePay", "merchant_zarinpal", "select")['ValuePay'];
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.zarinpal.com/pg/v4/payment/request.json',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Accept: application/json'
        ),
    ));
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
        "merchant_id" => $marchent_zarinpal,
        "currency" => "IRT",
        "amount" => $price,
        "callback_url" => "https://$domainhosts/payment/zarinpal.php",
        "description" => $order_id,
        "metadata" => array(
            "order_id" => $order_id
        )
    ]));
    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true);
}
function createPayZarinpey($price, $order_id, $userId)
{
    global $domainhosts;

    $token = getPaySettingValue('token_zarinpey');
    if (empty($token) || $token === '0') {
        return [
            'success' => false,
            'message' => 'توکن زرین پی تنظیم نشده است.',
        ];
    }

    $normalizedPrice = filter_var($price, FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => 1,
        ],
    ]);

    if ($normalizedPrice === false) {
        return [
            'success' => false,
            'message' => 'مبلغ تراکنش نامعتبر است.',
        ];
    }

    $amountRial = $normalizedPrice * 10;

    $baseHost = trim($domainhosts ?? '');
    $scheme = 'https';
    if ($baseHost === '') {
        $httpsFlag = $_SERVER['HTTPS'] ?? '';
        if ($httpsFlag === '' || strtolower($httpsFlag) === 'off') {
            $scheme = 'http';
        }
    }

    $host = filter_input(INPUT_SERVER, 'HTTP_HOST', FILTER_SANITIZE_SPECIAL_CHARS);
    if ($baseHost !== '') {
        $callbackBase = $scheme . '://' . ltrim($baseHost, '/');
    } elseif (!empty($host)) {
        $callbackBase = $scheme . '://' . $host;
    } else {
        return [
            'success' => false,
            'message' => 'امکان تعیین آدرس بازگشت وجود ندارد.',
        ];
    }

    $payload = [
        'amount' => $amountRial,
        'order_id' => $order_id,
        'callback_url' => rtrim($callbackBase, '/') . '/payment/ZarinPay/successful.php',
        'type' => 'card',
        'customer_user_id' => $userId,
        'description' => sprintf('پرداخت فاکتور %s', $order_id),
    ];

    $ch = curl_init('https://zarinpay.me/api/create-payment');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'success' => false,
            'message' => $error,
        ];
    }

    curl_close($ch);

    $result = json_decode($response, true);
    if (!is_array($result)) {
        return [
            'success' => false,
            'message' => 'پاسخ نامعتبر از زرین پی دریافت شد.',
        ];
    }

    if (empty($result['success'])) {
        return [
            'success' => false,
            'message' => $result['message'] ?? 'خطا در ایجاد پرداخت',
            'http_code' => $httpCode,
        ];
    }

    $data = $result['data'] ?? [];
    $authority = $result['authority'] ?? ($data['authority'] ?? null);
    $paymentLink = $result['payment_link']
        ?? ($result['payment_url'] ?? ($data['payment_link'] ?? ($data['payment_url'] ?? null)));

    if (empty($authority) || empty($paymentLink)) {
        return [
            'success' => false,
            'message' => 'پاسخ نامعتبر از زرین پی دریافت شد.',
        ];
    }

    return [
        'success' => true,
        'authority' => $authority,
        'payment_link' => $paymentLink,
        'amount_rial' => $amountRial,
        'raw_response' => $result,
    ];
}
function createPayaqayepardakht($price, $order_id)
{
    global $domainhosts;
    $merchant_aqayepardakht = select("PaySetting", "ValuePay", "NamePay", "merchant_id_aqayepardakht", "select")['ValuePay'];
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://panel.aqayepardakht.ir/api/v2/create',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Accept: application/json'
        ),
    ));
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
        'pin' => $merchant_aqayepardakht,
        'amount' => $price,
        'callback' => $domainhosts . "/payment/aqayepardakht.php",
        'invoice_id' => $order_id,
    ]));
    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true);
}
function deleteInvoiceFromList($invoiceId, $userId)
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = :invoiceId AND id_user = :userId");
        $stmt->bindParam(':invoiceId', $invoiceId);
        $stmt->bindParam(':userId', $userId);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log('Failed to delete invoice: ' . $e->getMessage());
        return false;
    }
}
