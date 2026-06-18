<?php
$textadmin = ["panel", "/panel", $textbotlang['Admin']['textpaneladmin']];
$text_panel_admin_login_template = "💎 | Version Debug Bot: %s
📌 | Version Debug Mini App: %s

<blockquote>🔹 | این ربات کاملاً رایگان است و توسط توسعه‌دهنده میرزا عرضه شده و توسط Mmd | Amir دیباگ شده است.</blockquote>

<blockquote><a href=\"https://github.com/Mmd-Amir/mirza_pro\" style=\"color:#1e88ff;\">گیت هاب دیباگ کننده</a></blockquote>

<blockquote>🔹 | هرگونه فروش یا دریافت وجه بابت این ربات تخلف محسوب می‌شود.</blockquote>

<blockquote>🔹 | در صورت مشاهدهٔ فروش یا دریافت وجه، لطفاً وجه خود را پیگیری کرده و بازپس‌گیری نمایید.</blockquote>
";

if (!in_array($from_id, $admin_ids))
    return;



function normalizeXuiSingleSubscriptionBaseUrl($rawLink)
{
    $trimmed = trim((string) $rawLink);
    if ($trimmed === '') {
        return '';
    }

    $parts = preg_split('/\s+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY);
    $candidate = trim((string) ($parts[0] ?? ''));
    if ($candidate === '') {
        return '';
    }

    $candidate = rtrim($candidate, '/');
    $urlForProcessing = $candidate;
    if (!preg_match('~^https?://~i', $urlForProcessing)) {
        $urlForProcessing = 'https://' . ltrim($urlForProcessing, '/');
    }

    if (!filter_var($urlForProcessing, FILTER_VALIDATE_URL)) {
        return $candidate;
    }

    $shouldTrim = false;
    $request = new CurlRequest($urlForProcessing);
    $response = $request->get();
    if (isset($response['status']) && $response['status'] >= 200 && $response['status'] < 400 && empty($response['error'])) {
        $body = $response['body'];
        if (isBase64($body)) {
            $body = base64_decode($body);
        }
        $protocols = ['vmess', 'vless', 'trojan', 'ss'];
        $sub_check = explode('://', $body)[0];
        if (in_array($sub_check, $protocols, true)) {
            $shouldTrim = true;
        }
    }

    if (!$shouldTrim) {
        $shouldTrim = hasLikelyXuiSubscriptionId($urlForProcessing);
    }

    $normalized = buildXuiSingleBaseUrl($urlForProcessing, $shouldTrim);
    if ($normalized === '' || preg_match('~^https?:$~i', $normalized)) {
        $normalized = buildXuiSingleBaseUrl($urlForProcessing, false);
    }

    return $normalized;
}

function buildXuiSingleBaseUrl($url, $dropLastSegment)
{
    $parsed = parse_url($url);
    if ($parsed === false) {
        return rtrim($url, '/');
    }

    $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
    $host = $parsed['host'] ?? '';
    $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
    $user = $parsed['user'] ?? '';
    $pass = $parsed['pass'] ?? '';

    $auth = '';
    if ($user !== '') {
        $auth = $user;
        if ($pass !== '') {
            $auth .= ':' . $pass;
        }
        $auth .= '@';
    }

    $path = $parsed['path'] ?? '';
    $path = trim($path, '/');
    if ($dropLastSegment && $path !== '') {
        $segments = explode('/', $path);
        array_pop($segments);
        $path = implode('/', $segments);
    }

    if ($path !== '') {
        $path = '/' . $path;
    }

    $query = isset($parsed['query']) && $parsed['query'] !== '' ? '?' . $parsed['query'] : '';
    $fragment = isset($parsed['fragment']) && $parsed['fragment'] !== '' ? '#' . $parsed['fragment'] : '';

    return rtrim($scheme . $auth . $host . $port . $path, '/') . $query . $fragment;
}

function hasLikelyXuiSubscriptionId($url)
{
    $parsed = parse_url($url);
    if ($parsed === false) {
        return false;
    }

    $candidates = [];

    $path = $parsed['path'] ?? '';
    $path = trim($path, '/');
    if ($path !== '') {
        $segments = explode('/', $path);
        if (!empty($segments)) {
            $lastSegment = $segments[count($segments) - 1];
            if ($lastSegment !== '') {
                $candidates[] = $lastSegment;
            }
        }
    }

    if (!empty($parsed['query'])) {
        parse_str($parsed['query'], $queryParams);
        foreach ($queryParams as $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    if (is_scalar($item)) {
                        $candidates[] = (string) $item;
                    }
                }
            } elseif (is_scalar($value)) {
                $candidates[] = (string) $value;
            }
        }
    }

    foreach ($candidates as $candidate) {
        if ($candidate === '') {
            continue;
        }
        if (preg_match('~^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$~i', $candidate)) {
            return true;
        }
        if (strlen($candidate) >= 16 && preg_match('~^[A-Za-z0-9=_-]+$~', $candidate)) {
            return true;
        }
    }

    return false;
}

function getPanelStateFromConfigFile($configPath)
{
    if (!is_string($configPath) || $configPath === '' || !is_readable($configPath)) {
        return null;
    }

    $configContents = file_get_contents($configPath);
    if ($configContents === false) {
        return null;
    }

    if (preg_match('/^\s*\/\/\/\s*\$new_marzban\s*=\s*true\s*;/m', $configContents)) {
        return 'marzban';
    }

    if (preg_match('/^\s*\$new_marzban\s*=\s*true\s*;/m', $configContents)) {
        return 'pasargad';
    }

    return null;
}

function getPanelStateLabel($state)
{
    switch ($state) {
        case 'pasargad':
            return 'پاسارگارد';
        case 'marzban':
            return 'مرزبان';
        default:
            return 'نامشخص';
    }
}

function buildPanelSelectionMessage($configPath)
{
    $currentState = getPanelStateFromConfigFile($configPath);
    $currentLabel = getPanelStateLabel($currentState);

    return "💠 لطفاً نوع پنل خود را انتخاب کنید 👇\n🧾 نوع فعلی پنل: {$currentLabel}";
}

function getPanelSelectionKeyboard()
{
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🧩 مرزبان', 'callback_data' => 'set_panel_marzban'],
                ['text' => '🏛 پاسارگارد', 'callback_data' => 'set_panel_pasargad'],
            ],
        ],
    ];

    return json_encode($keyboard, JSON_UNESCAPED_UNICODE);
}

function updatePanelStateInConfigFile($configPath, $state)
{
    if (!is_string($configPath) || $configPath === '' || !is_readable($configPath) || !is_writable($configPath)) {
        return false;
    }

    $configContents = file_get_contents($configPath);
    if ($configContents === false) {
        return false;
    }

    $activePattern = '/^\s*\$new_marzban\s*=\s*true\s*;/m';
    $commentPattern = '/^\s*\/\/\/\s*\$new_marzban\s*=\s*true\s*;/m';
    $replacementLine = $state === 'pasargad' ? '$new_marzban = true;' : '///$new_marzban = true;';

    $count = 0;
    $updatedContents = preg_replace($activePattern, $replacementLine, $configContents, 1, $count);
    if ($updatedContents === null) {
        return false;
    }

    if ($count === 0) {
        $updatedContents = preg_replace($commentPattern, $replacementLine, $updatedContents, 1, $count);
        if ($updatedContents === null) {
            return false;
        }
    }

    if ($count === 0) {
        $closingTagPattern = '/\?>\s*$/';
        if (preg_match($closingTagPattern, $updatedContents)) {
            $updatedContents = preg_replace($closingTagPattern, $replacementLine . PHP_EOL . '?>', $updatedContents, 1);
            if ($updatedContents === null) {
                return false;
            }
        } else {
            $updatedContents .= PHP_EOL . $replacementLine . PHP_EOL;
        }
    }

    $result = file_put_contents($configPath, $updatedContents);
    if ($result === false) {
        return false;
    }

    clearstatcache(true, $configPath);

    return true;
}

function buildCronJobsKeyboard(): string
{
    if (!function_exists('getCronJobDefinitions') || !function_exists('loadCronSchedules') || !function_exists('describeCronSchedule')) {
        return json_encode(['inline_keyboard' => []]);
    }

    $definitions = getCronJobDefinitions();
    $schedules = loadCronSchedules();
    $keyboard = ['inline_keyboard' => []];

    foreach ($definitions as $key => $definition) {
        if (empty($definition['admin_label']) || empty($definition['script'])) {
            continue;
        }
        $schedule = $schedules[$key] ?? $definition['default'];
        $keyboard['inline_keyboard'][] = [
            ['text' => '⚙️ تنظیمات', 'callback_data' => "cronjob_config-{$key}"],
            ['text' => describeCronSchedule($schedule), 'callback_data' => 'cronjob_display'],
            ['text' => $definition['admin_label'], 'callback_data' => 'cronjob_display'],
        ];
    }

    $keyboard['inline_keyboard'][] = [
        ['text' => '🔙 بازگشت به منوی وضعیت', 'callback_data' => 'admin'],
    ];

    return json_encode($keyboard, JSON_UNESCAPED_UNICODE);
}

function getCronUnitTitle(string $unit): string
{
    $labels = [
        'minute' => 'دقیقه',
        'hour' => 'ساعت',
        'day' => 'روز',
        'disabled' => 'غیرفعال',
    ];

    return $labels[$unit] ?? $labels['minute'];
}

if (!in_array($from_id, $admin_ids))
    return;

$users_ids = select('user', 'id', null, null, 'FETCH_COLUMN');
if (!is_array($users_ids)) {
    $users_ids = [];
}

$domainhostsEscaped = htmlspecialchars($domainhosts, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$cronInstructionBlock = '';
if (function_exists('buildCronInstructionDetails')) {
    $cronInstructionBlock = buildCronInstructionDetails($domainhostsEscaped);
} else {
    $cronInstructionBlock = <<<CRONHTML
<b>🕒 بررسی وضعیت روزانه — هر 15 دقیقه</b>
<code>curl https://{$domainhostsEscaped}/cronbot/statusday.php</code>

<b>🔔 سرویس اعلان‌ها (Notification Service) — هر 1 دقیقه</b>
<code>curl https://{$domainhostsEscaped}/cronbot/NoticationsService.php</code>

<b>💳 بررسی انقضای پرداخت‌ها — هر 5 دقیقه</b>
<code>curl https://{$domainhostsEscaped}/cronbot/payment_expire.php</code>

<b>📩 ارسال پیام‌ها — هر 1 دقیقه</b>
<code>curl https://{$domainhostsEscaped}/cronbot/sendmessage.php</code>

<b>💰 پردازش پرداخت‌های Plisio — هر 3 دقیقه</b>
<code>curl https://{$domainhostsEscaped}/cronbot/plisio.php</code>

<b>⚙️ فعال‌سازی تنظیمات جدید — هر 1 دقیقه</b>
<code>curl https://{$domainhostsEscaped}/cronbot/activeconfig.php</code>

<b>🚫 غیرفعال‌سازی تنظیمات قدیمی — هر 1 دقیقه</b>
<code>curl https://{$domainhostsEscaped}/cronbot/disableconfig.php</code>

<b>🇮🇷 بررسی وضعیت پرداخت ایران‌پی — هر 1 دقیقه</b>
<code>curl https://{$domainhostsEscaped}/cronbot/iranpay1.php</code>

<b>🗂 تهیه نسخه‌ی پشتیبان (Backup) — هر 5 ساعت</b>
<code>curl https://{$domainhostsEscaped}/cronbot/backupbot.php</code>

<b>🎁 ارسال هدایا (Gift System) — هر 2 دقیقه</b>
<code>curl https://{$domainhostsEscaped}/cronbot/gift.php</code>

<b>👥 بررسی انقضای نمایندگان — هر 30 دقیقه</b>
<code>curl https://{$domainhostsEscaped}/cronbot/expireagent.php</code>

<b>⏸ بررسی وضعیت سفارش‌های معلق — هر 15 دقیقه</b>
<code>curl https://{$domainhostsEscaped}/cronbot/on_hold.php</code>

<b>🧪 تست تنظیمات سیستم — هر 2 دقیقه</b>
<code>curl https://{$domainhostsEscaped}/cronbot/configtest.php</code>

<b>🌐 بررسی Uptime نودها — هر 15 دقیقه</b>
<code>curl https://{$domainhostsEscaped}/cronbot/uptime_node.php</code>

<b>🖥 بررسی Uptime پنل‌ها — هر 15 دقیقه</b>
<code>curl https://{$domainhostsEscaped}/cronbot/uptime_panel.php</code>

<b>💳 انجام تراکنش‌های کارت‌به‌کارت — هر 1 دقیقه</b>
<code>curl https://{$domainhostsEscaped}/cronbot/croncard.php</code>
CRONHTML;
}

$miniAppInstructionText = <<<HTML
📌 آموزش فعالسازی مینی اپ در ربات BotFather

/mybots > Select Bot > Bot Setting >  Configure Mini App > Enable Mini App  > Edit Mini App URL

مراحل بالا را طی کنید سپس آدرس زیر را ارسال نمایید :

<code>https://{$domainhostsEscaped}/app/</code>

➖➖➖➖➖➖➖➖➖➖➖➖
⚙️ تنظیم کرون‌جاب‌ها در هاست


<b>⏱ تنها کرون‌جاب موردنیاز به صورت

*/1

 یعنی هر 1 دقیقه باید تنظیم کنید
</b>

<code>curl https://{$domainhostsEscaped}/cron/cron.php</code>
HTML;

if (in_array($text, $textadmin) || $datain == "admin") {
    if ($datain == "admin")
        deletemessage($from_id, $message_id);
    if ($buyreport == "0" || $otherservice == "0" || $otherreport == "0" || $paymentreports == "0" || $reporttest == "0" || $errorreport == "0") {
        sendmessage($from_id, $textbotlang['Admin']['activebottext'], $active_panell, 'HTML');
        return;
    }
    $version_mini_app = file_get_contents('app/version');
    activecron();
    $text_admin = sprintf($text_panel_admin_login_template, $version, $version_mini_app);
    sendmessage($from_id, $text_admin, $keyboardadmin, 'HTML');
    $miniAppInstructionHidden = isset($user['hide_mini_app_instruction']) ? (string) $user['hide_mini_app_instruction'] : '0';
    if ($miniAppInstructionHidden !== '1') {
        sendmessage($from_id, $miniAppInstructionText, null, 'HTML');
    }
} elseif ($text == $textbotlang['Admin']['backadmin']) {
    if ($buyreport == "0" || $otherservice == "0" || $otherreport == "0" || $paymentreports == "0" || $reporttest == "0" || $errorreport == "0") {
        sendmessage($from_id, $textbotlang['Admin']['activebottext'], $active_panell, 'HTML');
        return;
    }
    $version_mini_app = file_get_contents('app/version');
    $text_admin = sprintf($text_panel_admin_login_template, $version, $version_mini_app);
    sendmessage($from_id, $text_admin, $keyboardadmin, 'HTML');
    step('home', $from_id);
    return;
} elseif ($datain == "hide_mini_app_instruction") {
    if (!in_array($from_id, $admin_ids))
        return;
    if (($user['hide_mini_app_instruction'] ?? '0') !== '1') {
        update("user", "hide_mini_app_instruction", "1", "id", $from_id);
        $user['hide_mini_app_instruction'] = '1';
    }
    $confirmationKeyboard = json_encode(['inline_keyboard' => []]);
    $confirmationText = $miniAppInstructionText . "\n\n✅ این پیام دیگر برای شما نمایش داده نخواهد شد.";
    Editmessagetext($from_id, $message_id, $confirmationText, $confirmationKeyboard, 'HTML');
    return;
} elseif ($text == $textbotlang['Admin']['backmenu']) {
    if ($buyreport == "0" || $otherservice == "0" || $otherreport == "0" || $paymentreports == "0" || $reporttest == "0" || $errorreport == "0") {
        sendmessage($from_id, $textbotlang['Admin']['activebottext'], $setting_panel, 'HTML');
        return;
    }
    $currentStep = isset($user['step']) ? (string) $user['step'] : '';
    step('home', $from_id);
    if (in_array($currentStep, ["updatetime", "val_usertest", "getlimitnew", "GetusernameNew", "GeturlNew", "protocolset", "updatemethodusername", "GetNameNew", "getprotocol", "getprotocolremove", "GetpaawordNew", "updateextendmethod", "setpricechangelocation"])) {
        $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
        outtypepanel($typepanel['type'], $textbotlang['Admin']['Back-menu']);
    } else {
        $financialStepKeyboardMap = [
            'apiternado' => $trnado,
            'changecard' => $CartManage,
            'getnamecard' => $CartManage,
            'getnamecarttocart' => $CartManage,
            'getnamenowpayment' => $nowpayment_setting_keyboard,
            'getnamecarttopaynotverify' => $CartManage,
            'gettextnowpayment' => $NowPaymentsManage,
            'gettextnowpaymentTRON' => $tronnowpayments,
            'gettextiranpay2' => $Swapinokey,
            'gettextstartelegram' => $Swapinokey,
            'gettextiranpay3' => $trnado,
            'gettextiranpay1' => $iranpaykeyboard,
            'gettextaqayepardakht' => $aqayepardakht,
            'gettextzarinpal' => $keyboardzarinpal,
            'gettextzarinpey' => $keyboardzarinpey,
            'token_zarinpey' => $keyboardzarinpey,
            'merchant_id_aqayepardakht' => $aqayepardakht,
            'merchant_zarinpal' => $keyboardzarinpal,
            'apinowpayment' => $NowPaymentsManage,
            'getcashcart' => $CartManage,
            'getcashahaypar' => $CartManage,
            'getcashiranpay2' => $trnado,
            'getcashiranpay4' => $CartManage,
            'getcashiranpay1' => $Swapinokey,
            'getcashplisio' => $CartManage,
            'getcashnowpayment' => $nowpayment_setting_keyboard,
            'getcashzarinpal' => $keyboardzarinpal,
            'getcashzarinpey' => $keyboardzarinpey,
            'getmaincart' => $CartManage,
            'getmaxcart' => $CartManage,
            'getmainplisio' => $NowPaymentsManage,
            'getmaxplisio' => $NowPaymentsManage,
            'getmaindigitaltron' => $tronnowpayments,
            'getmaxdigitaltron' => $tronnowpayments,
            'getmainiranpay1' => $Swapinokey,
            'getmaaxiranpay1' => $Swapinokey,
            'getmainiranpay2' => $trnado,
            'getmaaxiranpay2' => $Swapinokey,
            'getmainaqayepardakht' => $aqayepardakht,
            'getmaaxaqayepardakht' => $aqayepardakht,
            'getmainaqzarinpal' => $aqayepardakht,
            'getmaaxzarinpal' => $aqayepardakht,
            'getmainzarinpey' => $keyboardzarinpey,
            'getmaaxzarinpey' => $keyboardzarinpey,
            'helpzarinpey' => $keyboardzarinpey,
            'gethelpcart' => $CartManage,
            'gethelpnowpayment' => $nowpayment_setting_keyboard,
            'gethelpperfect' => $CartManage,
            'gethelpplisio' => $CartManage,
            'gethelpiranpay1' => $CartManage,
            'getmainaqstar' => $Startelegram,
            'maxbalancestar' => $Startelegram,
            'getmainaqnowpayment' => $nowpayment_setting_keyboard,
            'maxbalancenowpayment' => $nowpayment_setting_keyboard,
            'gethelpstar' => $Startelegram,
            'chashbackstar' => $Startelegram,
        ];

        $productStepKeyboardMap = [
            'change_price' => $change_product,
            'change_note' => $change_product,
            'change_categroy' => $change_product,
            'change_name' => $change_product,
            'change_type_agent' => $change_product,
            'change_reset_data' => $change_product,
            'change_loc_data' => $change_product,
            'getlistpanel' => $change_product,
            'change_val' => $change_product,
            'change_time' => $change_product,
        ];

        if ($currentStep === 'walletaddresssiranpay') {
            $processingData = [];
            if (isset($user['Processing_value'])) {
                $decodedProcessing = json_decode($user['Processing_value'], true);
                if (is_array($decodedProcessing)) {
                    $processingData = $decodedProcessing;
                }
            }
            $walletOrigin = $processingData['walletaddress_origin'] ?? 'general';
            $keyboard = $walletOrigin === 'trnado' ? $trnado : $keyboardadmin;
            sendmessage($from_id, $textbotlang['Admin']['Back-menu'], $keyboard, 'HTML');
            return;
        }

        if (isset($financialStepKeyboardMap[$currentStep])) {
            $targetKeyboard = $financialStepKeyboardMap[$currentStep];
            sendmessage($from_id, $textbotlang['Admin']['Back-menu'], $targetKeyboard, 'HTML');
            return;
        }

        if (isset($productStepKeyboardMap[$currentStep])) {
            $targetKeyboard = $productStepKeyboardMap[$currentStep];
            sendmessage($from_id, $textbotlang['Admin']['Back-menu'], $targetKeyboard, 'HTML');
            return;
        }

        $shopSteps = [
            "selectloc",
            "get_limit",
            "selectlocedite",
            "GetPriceExtra",
            "GetPriceexstratime",
            "GetPricecustomtime",
            "GetPricecustomvolume",
            "get_code",
            "get_codesell",
            "minbalancebulk",
            "get_agent",
            "get_location",
            "getcategory",
            "get_time",
            "get_price",
            "gettimereset",
            "getnote",
            "endstep",
            "gettypeextra",
            "gettypeextracustom",
            "gettypeextratime",
            "gettypeextratimecustom",
            "gettypeextramain",
            "gettypeextramax",
            "gettypeextramaintime",
            "gettypeextramaxtime",
        ];

        if (in_array($currentStep, $shopSteps, true)) {
            sendmessage($from_id, $textbotlang['Admin']['Back-menu'], $shopkeyboard, 'HTML');
            return;
        } elseif (in_array($currentStep, ["addchannel", "removechannel"])) {
            sendmessage($from_id, $textbotlang['Admin']['Back-menu'], $channelkeyboard, 'HTML');
        } else {
            sendmessage($from_id, $textbotlang['Admin']['Back-Admin'], $keyboardadmin, 'HTML');
        }
    }
    return;
} elseif ($text == $textbotlang['Admin']['channel']['title'] && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['channel']['changechannel'], $backadmin, 'HTML');
    step('addchannel', $from_id);
} elseif ($user['step'] == "addchannel") {
    savedata("clear", "link", $text);
    sendmessage($from_id, "📌 یک نام برای دکمه عضویت چنل انتخاب نمایید.", $backadmin, 'HTML');
    step('getremark', $from_id);
} elseif ($user['step'] == "getremark") {
    savedata("save", "remark", $text);
    sendmessage($from_id, "📌 لینک عضویت را ارسال کنید", $backadmin, 'HTML');
    step('getlinkjoin', $from_id);
} elseif ($user['step'] == "getlinkjoin") {
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        sendmessage($from_id, "آدرس عضویت صحیح نمی باشد", $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    if (!is_array($userdata)) {
        $userdata = [];
    }

    $remark = isset($userdata['remark']) ? (string) $userdata['remark'] : '';
    $link = isset($userdata['link']) ? (string) $userdata['link'] : '';

    sendmessage($from_id, "✅ کانال جوین اجباری با موفقیت ثبت گردید.", $channelkeyboard, 'HTML');
    step('home', $from_id);

    $insertChannel = function ($remarkValue) use ($pdo, $link, $text) {
        $stmt = $pdo->prepare("INSERT INTO channels (link, remark, linkjoin) VALUES (:link, :remark, :linkjoin)");
        $stmt->bindValue(':remark', $remarkValue, PDO::PARAM_STR);
        $stmt->bindValue(':link', $link, PDO::PARAM_STR);
        $stmt->bindValue(':linkjoin', $text, PDO::PARAM_STR);
        $stmt->execute();
    };

    try {
        $insertChannel($remark);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Incorrect string value') !== false) {
            ensureTableUtf8mb4('channels');
            try {
                $insertChannel($remark);
            } catch (PDOException $retryException) {
                if (strpos($retryException->getMessage(), 'Incorrect string value') === false) {
                    throw $retryException;
                }

                $sanitisedRemark = is_string($remark) ? @iconv('UTF-8', 'UTF-8//IGNORE', $remark) : '';
                if ($sanitisedRemark === false) {
                    $sanitisedRemark = '';
                }
                $insertChannel($sanitisedRemark);
            }
        } else {
            throw $e;
        }
    }
} elseif ($text == $textbotlang['Admin']['channel']['removechannelbtn'] && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['channel']['removechannel'], $list_channels_joins, 'HTML');
    step('removechannel', $from_id);
} elseif ($user['step'] == "removechannel") {
    sendmessage($from_id, $textbotlang['Admin']['channel']['removedchannel'], $channelkeyboard, 'HTML');
    step('home', $from_id);
    $stmt = $pdo->prepare("DELETE FROM channels WHERE link = :link");
    $stmt->bindParam(':link', $text, PDO::PARAM_STR);
    $stmt->execute();
} elseif ($datain == "addnewadmin" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['manageadmin']['getid'], $backadmin, 'HTML');
    step('addadmin', $from_id);
} elseif ($user['step'] == "addadmin") {
    $adminId = trim($text);
    if ($adminId === '') {
        sendmessage($from_id, $textbotlang['Admin']['manageadmin']['getid'], $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value", $adminId, "id", $from_id);
    sendmessage($from_id, $textbotlang['Admin']['manageadmin']['setrule'], $adminrule, 'HTML');
    step('getrule', $from_id);
} elseif ($user['step'] == "getrule") {
    $rule = ['administrator', 'Seller', 'support'];
    if (!in_array($text, $rule)) {
        sendmessage($from_id, $textbotlang['Admin']['manageadmin']['invalidrule'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['manageadmin']['addadminset'], $keyboardadmin, 'HTML');
    sendmessage($user['Processing_value'], $textbotlang['Admin']['manageadmin']['adminedsenduser'], null, 'HTML');
    step('home', $from_id);
    $usernamepanel = "root";
    $randomString = bin2hex(random_bytes(5));
    $stmt = $pdo->prepare("INSERT INTO admin (id_admin, username, password, rule) VALUES (:id_admin, :username, :password, :rule)");
    $stmt->bindParam(':id_admin', $user['Processing_value'], PDO::PARAM_STR);
    $stmt->bindParam(':username', $usernamepanel, PDO::PARAM_STR);
    $stmt->bindParam(':password', $randomString, PDO::PARAM_STR);
    $stmt->bindParam(':rule', $text, PDO::PARAM_STR);
    $stmt->execute();
    $text_report = sprintf($textbotlang['Admin']['reportgroup']['adminadded'], $username, $from_id, $text, $user['Processing_value']);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/limitusertest_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    sendmessage($from_id, $textbotlang['Admin']['getlimitusertest']['getid'], $backadmin, 'HTML');
    update("user", "Processing_value", $iduser, "id", $from_id);
    step('get_number_limit', $from_id);
} elseif ($user['step'] == "get_number_limit") {
    sendmessage($from_id, $textbotlang['Admin']['getlimitusertest']['setlimit'], $keyboardadmin, 'HTML');
    $id_user_set = $text;
    step('home', $from_id);
    update("user", "limit_usertest", $text, "id", $user['Processing_value']);
} elseif ($text == $textbotlang['Admin']['getlimitusertest']['setlimitbtn'] && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['getlimitusertest']['limitall'], $backadmin, 'HTML');
    step('limit_usertest_allusers', $from_id);
} elseif ($user['step'] == "limit_usertest_allusers") {
    sendmessage($from_id, $textbotlang['Admin']['getlimitusertest']['setlimitall'], $keyboardadmin, 'HTML');
    step('home', $from_id);
    update("user", "limit_usertest", $text);
    update("setting", "limit_usertest_all", $text);
} elseif ($text == "📯 تنظیمات کانال" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['channel']['description'], $channelkeyboard, 'HTML');
} elseif ($text == $textbotlang['Admin']['Status']['btn'] || $datain == "stat_all_bot") {
    $Balanceall = select("user", "SUM(Balance)", null, null, "select")['SUM(Balance)'];
    $statistics = select("user", "*", null, null, "count");
    $sumpanel = select("marzban_panel", "*", null, null, "count");
    $sql1 = "SELECT COUNT(id) AS count FROM user WHERE agent != 'f'";
    $stmt1 = $pdo->query($sql1);
    $agentsum = $stmt1->fetch(PDO::FETCH_ASSOC)['count'];
    $agentsumn = select("user", "COUNT(id)", "agent", "n", "select")['COUNT(id)'];
    $agentsumn2 = select("user", "COUNT(id)", "agent", "n2", "select")['COUNT(id)'];
    $sql1 = "SELECT COUNT(*) AS invoice_count FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') AND name_product != 'سرویس تست'";
    $stmt1 = $pdo->query($sql1);
    $invoiceactive = $stmt1->fetch(PDO::FETCH_ASSOC)['invoice_count'];
    $sqlall = "SELECT COUNT(*) AS invoice_count FROM invoice WHERE status != 'Unpaid' AND name_product != 'سرویس تست'";
    $sqlall = $pdo->query($sqlall);
    $invoice = $sqlall->fetch(PDO::FETCH_ASSOC)['invoice_count'];
    $sql2 = "SELECT SUM(price_product) AS total_price FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') AND name_product != 'سرویس تست'";
    $stmt2 = $pdo->query($sql2);
    $invoicesum = $stmt2->fetch(PDO::FETCH_ASSOC)['total_price'];
    $sql33 = "SELECT SUM(price_product) AS total_price FROM invoice WHERE status!= 'Unpaid' AND name_product != 'سرویس تست'";
    $sql33 = $pdo->query($sql33);
    $invoiceSumRow = $sql33->fetch(PDO::FETCH_ASSOC);
    $invoiceTotal = isset($invoiceSumRow['total_price']) ? (float) $invoiceSumRow['total_price'] : 0;
    $invoicesumall = number_format($invoiceTotal, 0);
    $sql3 = "SELECT SUM(price) AS total_extend FROM service_other WHERE type = 'extend_user'";
    $stmt3 = $pdo->query($sql3);
    $extendSumRow = $stmt3->fetch(PDO::FETCH_ASSOC);
    $extendsum = isset($extendSumRow['total_extend']) ? (float) $extendSumRow['total_extend'] : 0;
    $count_usertest = select("invoice", "*", "name_product", "سرویس تست", "count");
    $timeacc = jdate('H:i:s', time());
    $stmt2 = $pdo->prepare("SELECT COUNT(DISTINCT id_user) as count FROM `invoice` WHERE Status != 'Unpaid'");
    $stmt2->execute();
    $statisticsorder = $stmt2->fetch(PDO::FETCH_ASSOC)['count'];
    $sqlsum = "SELECT SUM(price) AS sumpay , Payment_Method,COUNT(price) AS countpay FROM Payment_report WHERE payment_Status = 'paid' AND Payment_Method NOT IN ('add balance by admin','low balance by admin') GROUP BY  Payment_Method;";
    $stmt = $pdo->prepare($sqlsum);
    $stmt->execute();
    $statispay = $stmt->fetchAll();
    $date = date("Y-m-d");
    $timeacc = jdate('H:i:s', time());
    $start_time = date('d.m.Y', strtotime("-1 days")) . " 00:00:00";
    $end_time = date('d.m.Y', strtotime("-1 days")) . " 23:59:59";
    $start_time_timestamp = strtotime($start_time);
    $end_time_timestamp = strtotime($end_time);
    $sql = "SELECT SUM(price_product) FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR Status = 'send_on_hold' OR Status = 'sendedwarn') AND name_product != 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $suminvoiceday = $stmt->fetch(PDO::FETCH_ASSOC)['SUM(price_product)'];
    $invoicesum = (float) ($invoicesum ?? 0);
    $extendsum = (float) ($extendsum ?? 0);
    $suminvoiceday = (float) ($suminvoiceday ?? 0);
    $statistics = (int) ($statistics ?? 0);
    $statisticsorder = (int) ($statisticsorder ?? 0);
    $paycount = "";
    $ratecustomer = round(safe_divide($statisticsorder * 100, $statistics, 0), 2);
    $averagePurchase = safe_divide($invoicesum, $statisticsorder, 0);
    $avgbuy_customer = $averagePurchase > 0 ? number_format($averagePurchase) : '0';
    $monthe_buy = number_format($suminvoiceday * 30);
    $percent_of_extend = round(safe_divide($extendsum * 100, $invoicesum, 0), 2);
    $percent_of_extend = $percent_of_extend > 100 ? 100 : $percent_of_extend;
    $extendsum = number_format($extendsum, 0);
    if (!empty($statispay)) {
        $statusLabels = [
            'cart to cart' => $datatextbot['carttocart'] ?? 'cart to cart',
            'aqayepardakht' => $datatextbot['aqayepardakht'] ?? 'aqayepardakht',
            'zarinpal' => $datatextbot['zarinpal'] ?? 'zarinpal',
            'zarinpey' => $datatextbot['zarinpey'] ?? 'zarinpey',
            'zarinpay' => $datatextbot['zarinpey'] ?? ($datatextbot['zarinpal'] ?? 'zarinpay'),
            'plisio' => $datatextbot['textnowpayment'] ?? 'plisio',
            'arze digital offline' => $datatextbot['textnowpaymenttron'] ?? 'arze digital offline',
            'Currency Rial 1' => $datatextbot['iranpay2'] ?? 'Currency Rial 1',
            'Currency Rial 2' => $datatextbot['iranpay3'] ?? 'Currency Rial 2',
            'Currency Rial 3' => $datatextbot['iranpay1'] ?? 'Currency Rial 3',
            'paymentnotverify' => $datatextbot['textpaymentnotverify'] ?? 'paymentnotverify',
            'Star Telegram' => $datatextbot['text_star_telegram'] ?? 'Star Telegram',
        ];

        foreach ($statispay as $tracepay) {
            $paymentMethod = $tracepay['Payment_Method'] ?? '';
            $status_var = $statusLabels[$paymentMethod] ?? $paymentMethod;
            $paycount .= "
📌 نام درگاه : <code>$status_var</code>
 - تعداد پرداخت موفق : <code>{$tracepay['countpay']}</code>
 - جمع پرداختی ها : <code>{$tracepay['sumpay']}</code>\n";
        }
    }
    $bot_ping = 'نامشخص';
    $ping_start_time = microtime(true);
    $ping_response = telegram('getMe');
    $ping_duration = (microtime(true) - $ping_start_time) * 1000;
    if (is_array($ping_response) && !empty($ping_response['ok'])) {
        $bot_ping = number_format(max($ping_duration, 0), 0) . ' میلی‌ثانیه';
    }

    $statisticsall = "📊 <b>آمار کلی ربات</b>
━━━━━━━━━━━━━━━━━━
👥 <b>تعداد کل کاربران:</b> <code>$statistics</code> نفر
💳 <b>کاربران دارای خرید:</b> <code>$statisticsorder</code> نفر
🧪 <b>اکانت‌های تست:</b> <code>$count_usertest</code> نفر
💰 <b>موجودی کل کاربران:</b> <code>$Balanceall</code> تومان  

🧾 <b>تعداد کل فروش:</b> <code>$invoice</code> عدد  
🧾 <b>تعداد کل فروش سرویس های فعال:</b> <code>$invoiceactive</code> عدد  
💵 <b>جمع کل فروش :</b> <code>$invoicesumall</code> تومان  
💵 <b>جمع کل فروش سرویس های فعال:</b> <code>$invoicesum</code> تومان  
🔄 <b>جمع کل تمدید:</b> <code>$extendsum</code> تومان  
📈 <b>نرخ تبدیل به مشتری:</b> <code>$ratecustomer</code>٪  
💳 <b>میانگین خرید هر مشتری:</b> <code>$avgbuy_customer</code> تومان  
📅 <b>درآمد پیش‌بینی‌شده ماهانه:</b> <code>$monthe_buy</code> تومان  
📊 <b>درصد تمدید از فروش:</b> <code>$percent_of_extend</code>٪  


👨‍💼 <b>تعداد کل نمایندگان:</b> <code>$agentsum</code> نفر  
🔹 <b>نمایندگان نوع N:</b> <code>$agentsumn</code> نفر
🔸 <b>نمایندگان نوع N2:</b> <code>$agentsumn2</code> نفر
🧩 <b>تعداد پنل‌ها:</b> <code>$sumpanel</code> عدد
📡 <b>پینگ ربات:</b> $bot_ping
$paycount
";
    if ($datain == "stat_all_bot") {
        Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
    } else {
        sendmessage($from_id, $statisticsall, $keyboard_stat, 'HTML');
    }
} elseif ($datain == "close_stat") {
    deletemessage($from_id, $message_id);
} elseif ($datain == "hoursago_stat") {
    $desired_date_time_start = time() - 3600;
    $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND Status != 'Unpaid'  AND name_product != 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $time_current = time();
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->bindParam(':requestedDateend', $time_current);
    $stmt->execute();
    $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_order = $statorder['count'];
    $sum_order = number_format($statorder['sum'], 0);
    $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->bindParam(':requestedDateend', $time_current);
    $stmt->execute();
    $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  time  >= NOW() - INTERVAL 1 HOUR AND type = 'extend_user' AND status != 'unpaid'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extend = $extend_stat['count'];
    $sum_extend = number_format($extend_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  time  >= NOW() - INTERVAL 1 HOUR AND type = 'extra_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_volume = $extra_volume_stat['count'];
    $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  time  >= NOW() - INTERVAL 1 HOUR AND type = 'extra_time_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_time = $extra_time_stat['count'];
    $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  time  >= NOW() - INTERVAL 1 HOUR AND type = 'change_location'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_change_location = $change_location_stat['count'];
    $sum_change_location = number_format($change_location_stat['sum'], 0);
    $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->bindParam(':requestedDateend', $time_current);
    $stmt->execute();
    $countextendday = $stmt->rowCount();
    $statisticsall = "
🕐 <b>آمار ۱ ساعت گذشته</b>


🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countextendday نفر
";
    Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
} elseif ($datain == "yesterday_stat") {
    $start_time = date('Y/m/d', strtotime("-1 days")) . " 00:00:00";
    $end_time = date('Y/m/d', strtotime("-1 days")) . " 23:59:59";
    $start_time_timestamp = strtotime($start_time);
    $end_time_timestamp = strtotime($end_time);
    $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND Status != 'Unpaid'  AND name_product != 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_order = $statorder['count'];
    $sum_order = number_format($statorder['sum'], 0);
    $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extend_user' AND status != 'unpaid'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extend = $extend_stat['count'];
    $sum_extend = number_format($extend_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_volume = $extra_volume_stat['count'];
    $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_time_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_time = $extra_time_stat['count'];
    $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'change_location'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_change_location = $change_location_stat['count'];
    $sum_change_location = number_format($change_location_stat['sum'], 0);
    $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $countuser_new = $stmt->rowCount();
    $statisticsall = "
🕐 <b>آمار روز گذشته</b>

⏳ بازه تایم  : $start_time تا$end_time

🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countuser_new نفر
";
    Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
} elseif ($datain == "today_stat") {
    $start_time = date('Y/m/d') . " 00:00:00";
    $end_time = date('Y/m/d H:i:s');
    $start_time_timestamp = strtotime($start_time);
    $end_time_timestamp = strtotime($end_time);
    $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND Status != 'Unpaid' AND name_product != 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_order = $statorder['count'];
    $sum_order = number_format($statorder['sum'], 0);
    $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extend_user' AND status != 'unpaid'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extend = $extend_stat['count'];
    $sum_extend = number_format($extend_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_volume = $extra_volume_stat['count'];
    $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_time_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_time = $extra_time_stat['count'];
    $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'change_location'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_change_location = $change_location_stat['count'];
    $sum_change_location = number_format($change_location_stat['sum'], 0);
    $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $countuser_new = $stmt->rowCount();
    $statisticsall = "
🕐 <b>آمار روز فعلی</b>

⏳ بازه تایم  : $start_time تا$end_time

🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countuser_new نفر
";
    Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
} elseif ($datain == "month_old_stat") {
    $firstDayLastMonth = new DateTime('first day of last month');
    $lastDayLastMonth = new DateTime('last day of last month');
    $start_time = $firstDayLastMonth->format('Y/m/d');
    $end_time = $lastDayLastMonth->format('Y/m/d');
    $start_time_timestamp = strtotime($start_time);
    $end_time_timestamp = strtotime($end_time);
    $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND Status != 'Unpaid'  AND name_product != 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_order = $statorder['count'];
    $sum_order = number_format($statorder['sum'], 0);
    $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extend_user' AND status != 'unpaid'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extend = $extend_stat['count'];
    $sum_extend = number_format($extend_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_volume = $extra_volume_stat['count'];
    $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_time_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_time = $extra_time_stat['count'];
    $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'change_location'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_change_location = $change_location_stat['count'];
    $sum_change_location = number_format($change_location_stat['sum'], 0);
    $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $countuser_new = $stmt->rowCount();
    $statisticsall = "
🕐 <b>آمار ماه گذشته</b>

⏳ بازه تایم  : $start_time تا$end_time

🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countuser_new نفر
";
    Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
} elseif ($datain == "month_current_stat") {
    $firstDayLastMonth = new DateTime('first day of this month');
    $lastDayLastMonth = new DateTime('last day of this month');
    $start_time = $firstDayLastMonth->format('Y/m/d');
    $end_time = $lastDayLastMonth->format('Y/m/d');
    $start_time_timestamp = strtotime($start_time);
    $end_time_timestamp = strtotime($end_time);
    $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND Status != 'Unpaid'  AND name_product != 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_order = $statorder['count'];
    $sum_order = number_format($statorder['sum'], 0);
    $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extend_user' AND status != 'unpaid'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extend = $extend_stat['count'];
    $sum_extend = number_format($extend_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_volume = $extra_volume_stat['count'];
    $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_time_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_time = $extra_time_stat['count'];
    $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'change_location'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_change_location = $change_location_stat['count'];
    $sum_change_location = number_format($change_location_stat['sum'], 0);
    $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $countuser_new = $stmt->rowCount();
    $statisticsall = "
🕐 <b>آمار ماه فعلی</b>

⏳ بازه تایم  : $start_time تا$end_time

🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countuser_new نفر
";
    Editmessagetext($from_id, $message_id, $statisticsall, $keyboard_stat, 'HTML');
} elseif ($datain == "view_stat_time") {
    sendmessage($from_id, sprintf($textbotlang['Admin']['getstats'], date('Y/m/d')), $backadmin, 'HTML');
    step("get_time_start", $from_id);
} elseif ($user['step'] == "get_time_start") {
    if (!isValidDate($text)) {
        sendmessage($from_id, "تاریخ باید معتبر باشد", null, 'HTML');
        return;
    }
    savedata("clear", "start_time", $text);
    sendmessage($from_id, "تاریخ پایان را ارسال کنید بطور مثال :  \n<code>2025/09/08</code>", $backadmin, 'HTML');
    step("get_time_end", $from_id);
} elseif ($user['step'] == "get_time_end") {
    if (!isValidDate($text)) {
        sendmessage($from_id, "تاریخ باید معتبر باشد", null, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $start_time = $userdata['start_time'] . " 00:00:00";
    $end_time = $text . " 23:59:00";
    $start_time_timestamp = strtotime($start_time);
    $end_time_timestamp = strtotime($end_time);
    $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND  Status != 'Unpaid' AND name_product != 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_order = $statorder['count'];
    $sum_order = number_format($statorder['sum'], 0);
    $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extend_user' AND status != 'unpaid'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extend = $extend_stat['count'];
    $sum_extend = number_format($extend_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_volume = $extra_volume_stat['count'];
    $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_time_user'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_extra_time = $extra_time_stat['count'];
    $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
    $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'change_location'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':requestedDate', $start_time);
    $stmt->bindParam(':requestedDateend', $end_time);
    $stmt->execute();
    $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
    $count_change_location = $change_location_stat['count'];
    $sum_change_location = number_format($change_location_stat['sum'], 0);
    $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
    $stmt->bindParam(':requestedDate', $start_time_timestamp);
    $stmt->bindParam(':requestedDateend', $end_time_timestamp);
    $stmt->execute();
    $countuser_new = $stmt->rowCount();
    $statisticsall = "
🕐 <b>آمار تاریخ انتخابی</b>

⏳ بازه تایم  : $start_time تا $end_time

🛍 تعداد سفارشات : $count_order عدد
💸 جمع مبلغ سفارشات  : $sum_order تومان

🧲 تعداد تمدید  : $count_extend عدد
💰 جمع مبلغ تمدید: $sum_extend تومان

📦 حجم‌های اضافه  :$count_extra_volume عدد
💰 مبلغ حجم‌های اضافه : $sum_extra_volume تومان

⏱️ زمان‌های اضافه  : $count_extra_time عدد
💰 مبلغ زمان‌های اضافه  : $sum_extrat_time تومان

📍 تغییر لوکیشن  : $count_change_location عدد
💰 مبلغ تغییر لوکیشن : $sum_change_location تومان

🔑 اکانت‌های تست  : $count_test عدد
👤 تعداد کاربران  : $countuser_new نفر
";
    step('home', $from_id);
    sendmessage($from_id, $statisticsall, $keyboardadmin, 'HTML');
} elseif ($datain == "settingaffiliatesf") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $affiliates, 'HTML');
} elseif ($text == $textbotlang['Admin']['btnkeyboardadmin']['addpanel'] && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['Inbound']['gettypepanel'], $keyboardtypepanel, 'HTML');
} elseif (preg_match('/typepanel#(.*)/', $datain, $dataget)) {
    $typepanel = $dataget[1];
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['addpanelname'], $backadmin, 'HTML');
    step("add_name_panel", $from_id);
    deletemessage($from_id, $message_id);
    savedata("clear", "type", $typepanel);
} elseif ($user['step'] == "add_name_panel") {
    if (in_array($text, $marzban_list)) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['Repeatpanel'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    savedata("save", "namepanel", $text);
    if ($userdata['type'] == "Manualsale") {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
        step('getlimitedpanel', $from_id);
        savedata("save", "url_panel", "null");
        savedata("save", "username", "null");
        savedata("save", "password", "null");
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['addpanelurl'], $backadmin, 'HTML');
    step('add_link_panel', $from_id);
} elseif ($user['step'] == "add_link_panel") {
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "url_panel", $text);
    $userdata = json_decode($user['Processing_value'], true);
    if ($userdata['type'] == "hiddify") {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
        step('getlimitedpanel', $from_id);
        savedata("save", "username", "null");
        savedata("save", "password", "null");
        return;
    } elseif ($userdata['type'] == "s_ui" || $userdata['type'] == "WGDashboard") {
        sendmessage($from_id, "📌 توکن را ارسال نمایید", $backadmin, 'HTML');
        step('add_password_panel', $from_id);
        savedata("save", "username", "null");
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
    step('add_username_panel', $from_id);
} elseif ($user['step'] == "add_username_panel") {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['getpassword'], $backadmin, 'HTML');
    step('add_password_panel', $from_id);
    savedata("save", "username", $text);
} elseif ($user['step'] == "add_password_panel") {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
    step('getlimitedpanel', $from_id);
    savedata("save", "password", $text);
} elseif ($user['step'] == "getlimitedpanel") {
    savedata("save", "limitpanel", $text);
    $userdata = json_decode($user['Processing_value'], true);
    $randomString = bin2hex(random_bytes(2));
    if ($userdata['type'] == "x-ui_single" || $userdata['type'] == "alireza") {
        $marzbanprotocol = $randomString;
        $protocols = "vmess";
        $settingpanel = json_encode(array(
            'network' => 'ws',
            'security' => 'none',
            'externalProxy' => array(),
            'wsSettings' => array(
                'acceptProxyProtocol' => false,
                'path' => '/',
                'host' => '',
                'headers' => array()

            ),
        ));
    }
    $sublink = "onsublink";
    $configstatus = "offconfig";
    $MethodUsername = "آیدی عددی + حروف و عدد رندوم";
    $status = "active";
    $ONTestAccount = "ONTestAccount";
    $extendtextadd = "ریست حجم و زمان";
    $namecustoms = "none";
    $type = "marzban";
    $conecton = "offconecton";
    $inboundid = 1;
    $agent = "all";
    $time = "1";
    $valume = "100";
    $changeloc = "offchangeloc";
    $value = json_encode(array(
        'f' => "4000",
        'n' => "4000",
        'n2' => "4000"
    ));
    $valuemain = json_encode(array(
        'f' => "1",
        'n' => "1",
        'n2' => "1"
    ));
    $valuemax = json_encode(array(
        'f' => "1000",
        'n' => "1000",
        'n2' => "1000"
    ));
    $VALUE = json_encode(array(
        'f' => '0',
        'n' => '0',
        'n2' => '0'
    ));
    $valuestatusin = "offinbounddisable";
    $statusextend = "on_extend";
    $subvip = "offsubvip";
    $stauts_on_holed = "1";
    $stmt = $pdo->prepare("INSERT INTO marzban_panel (code_panel,name_panel,sublink,config,MethodUsername,TestAccount,status,limit_panel,namecustom,Methodextend,type,conecton,inboundid,agent,inbound_deactive,inboundstatus,url_panel,username_panel,password_panel,time_usertest,val_usertest,linksubx,priceextravolume,priceextratime,pricecustomvolume,pricecustomtime,mainvolume,maxvolume,maintime,maxtime,status_extend,subvip,changeloc,customvolume,on_hold_test) VALUES (:code_panel,:name_panel,:sublink,:config,:MethodUsername,:TestAccount,:status,:limit_panel,:namecustom,:Methodextend,:type,:conecton,:inboundid,:agent,:inbound_deactive,:inboundstatus,:url_panel,:username_panel,:password_panel,:val_usertest,:time_usertest,:linksubx,:priceextravolume,:priceextratime,:pricecustomvolume,:pricecustomtime,:mainvolume,:maxvolume,:maintime,:maxtime,:status_extend,:subvip,:changeloc,:customvolume,:on_hold_test)");
    $stmt->bindParam(':code_panel', $randomString);
    $stmt->bindParam(':name_panel', $userdata['namepanel'], PDO::PARAM_STR);
    $stmt->bindParam(':sublink', $sublink);
    $stmt->bindParam(':config', $configstatus);
    $stmt->bindParam(':MethodUsername', $MethodUsername);
    $stmt->bindParam(':TestAccount', $ONTestAccount);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':limit_panel', $text);
    $stmt->bindParam(':namecustom', $namecustoms);
    $stmt->bindParam(':Methodextend', $extendtextadd);
    $stmt->bindParam(':type', $userdata['type'], PDO::PARAM_STR);
    $stmt->bindParam(':conecton', $conecton);
    $stmt->bindParam(':inboundid', $inboundid);
    $stmt->bindParam(':agent', $agent);
    $stmt->bindParam(':inbound_deactive', $inboundid);
    $stmt->bindParam(':inboundstatus', $valuestatusin);
    $stmt->bindParam(':url_panel', $userdata['url_panel']);
    $stmt->bindParam(':linksubx', $userdata['url_panel']);
    $stmt->bindParam(':username_panel', $userdata['username']);
    $stmt->bindParam(':password_panel', $userdata['password']);
    $stmt->bindParam(':val_usertest', $valume);
    $stmt->bindParam(':time_usertest', $time);
    $stmt->bindParam(':priceextravolume', $value);
    $stmt->bindParam(':priceextratime', $value);
    $stmt->bindParam(':pricecustomtime', $value);
    $stmt->bindParam(':pricecustomvolume', $value);
    $stmt->bindParam(':mainvolume', $valuemain);
    $stmt->bindParam(':maxvolume', $valuemax);
    $stmt->bindParam(':maintime', $valuemain);
    $stmt->bindParam(':maxtime', $valuemax);
    $stmt->bindParam(':status_extend', $statusextend);
    $stmt->bindParam(':subvip', $subvip);
    $stmt->bindParam(':changeloc', $changeloc);
    $stmt->bindParam(':customvolume', $VALUE);
    $stmt->bindParam(':on_hold_test', $stauts_on_holed);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['addedpanel'], $keyboardadmin, 'HTML');
    sendmessage($from_id, "🥳", $keyboardadmin, 'HTML');
    step("home", $from_id);
    if ($userdata['type'] == "x-ui_single" or $userdata['type'] == "alireza_single") {
        sendmessage($from_id, "❌ نکته :
برای فعالسازی پنل باید به منوی مدیریت پنل  رفته و گزینه های 
تنظیم شناسه اینباند و دامنه لینک ساب را حتما تنظیم نمایید در غیراینصورت کانفیگ ساخته نخواهد شد", null, 'HTML');
    } elseif ($userdata['type'] == "marzban") {
        sendmessage($from_id, "❌ نکته :
برای فعالسازی پنل باید به منوی مدیریت پنل  رفته و گزینه های 
تنظیم پروتکل و اینباند را تنظیم نمایید تا ربات کانفیگ دهد در غیراینصورت کانفیگ به  کاربر داده نمی شود", null, 'HTML');
    } elseif ($userdata['type'] == "WGDashboard") {
        sendmessage($from_id, "❌ نکته :
برای فعالسازی پنل باید به منوی مدیریت پنل  رفته و گزینه های 
منوی تنظیم شناسه اینباند رفته و نام کانفیگ را تنظیم نمایید در غیراینصورت ربات هیچ کانفیگی نمیسازد", null, 'HTML');
    } elseif ($userdata['type'] == "ibsng") {
        sendmessage($from_id, "❌ نکته :
برای فعالسازی باید از مدیریت پنل > تنظیم نام گروه یک نام پیشفرض گروه که در ibsng تعریف کردید در ربات بفرستید.", null, 'HTML');
    } elseif ($userdata['type'] == "mikrotik") {
        sendmessage($from_id, "❌ نکته :
۱ - حتما باید پلاگین اکانتینگ در میکروتیک شما نصب باشد
۲ - در بخش ip » servies » http or https باید فعال باشد ( اگر ssl تهیه کردید https روشن باشد در غیراینصورت http)", null, 'HTML');
    } elseif ($userdata['type'] == "hiddify") {
        sendmessage($from_id, "❌ نکته :
1 - از مدیریت پنل گزینه های زیر را تنظیم کنید

1 - uuid admin : uuid ادمین از پنل دریافت و ثبت کنید
2-  دامنه لینک ساب :‌ دامنه لینک ساب پنل هیدیفای را ارسال نمایید ", null, 'HTML');
    } elseif ($userdata['type'] == "s_ui") {
        sendmessage($from_id, "❌ نکته :
1 - از مسیر مدیریت پنل > تنظیم ⚙️ تنظیم پروتکل و اینباند یک نام کاربری کانفیگ را ارسال نمایید.", null, 'HTML');
    }
}
//_____________________[ message ]____________________________//
elseif ($datain == "systemsms") {
    $userslist = [];
    if (is_file('cronbot/users.json')) {
        $fileContent = file_get_contents('cronbot/users.json');
        if ($fileContent !== false && $fileContent !== '') {
            $decodedList = json_decode($fileContent, true);
            if (is_array($decodedList)) {
                $userslist = $decodedList;
            }
        }
    } else {
        file_put_contents('cronbot/users.json', json_encode([]));
    }
    if (count($userslist) != 0) {
        sendmessage($from_id, "❌ سیستم ارسال پیام درحال انجام عملیات است پس از پایان و اطلاع رسانی  می توانید پیام جدید را ارسال نمایید.", $keyboardadmin, 'HTML');
        return;
    }
    $listbtn = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "ارسال همگانی", 'callback_data' => 'typeservice-sendmessage'],
            ],
            [
                ['text' => "فوروارد همگانی", 'callback_data' => 'typeservice-forwardmessage'],
            ],
            [
                ['text' => "تعداد روزی که استفاده نکردند", 'callback_data' => 'typeservice-xdaynotmessage'],
            ],
            [
                ['text' => "لغو پیام های پین شده", 'callback_data' => 'typeservice-unpinmessage'],
            ],
            [
                ['text' => "بازگشت به منوی اصلی", 'callback_data' => 'backlistuser'],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['selectoption'], $listbtn);
} elseif (preg_match('/^typeservice-(\w+)/', $datain, $dataget)) {
    $type = $dataget[1];
    savedata("clear", "typeservice", $type);
    if ($type == "unpinmessage") {
        deletemessage($from_id, $message_id);
        $typesend = [
            "unpinmessage" => "لغو پیام پین شده"
        ][$type];
        $textconfirm = "📌 شما در حال انجام عملیات مربوط به ارسال پیام هستید با بررسی اطلاعات زیر و تایید دکمه زیر عملیات ارسال شروع خواهد شد.
⚙️ نوع عملیات : $typesend";
        $startaction = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "تایید و شروع عملیات", 'callback_data' => 'startaction'],
                ],
            ]
        ]);
        sendmessage($from_id, $textconfirm, $startaction, 'HTML');
        sendmessage($from_id, "با تایید گزینه بالا فرآیند ارسال شروع خواهد شد", $keyboardadmin, 'HTML');
        step("home", $from_id);
        return;
    }
    $listbtn = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "همه کاربران", 'callback_data' => 'typeusermessage-all'],
            ],
            [
                ['text' => "مشتریانی که خرید داشتند", 'callback_data' => 'typeusermessage-customer'],
            ],
            [
                ['text' => "کاربرانی که خرید نداشتند", 'callback_data' => 'typeusermessage-nonecustomer'],
            ],
            [
                ['text' => "بازگشت به منوی قبل", 'callback_data' => 'systemsms'],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 سرویس برای کدام گروه کاربری اعمال شود؟", $listbtn);
} elseif (preg_match('/^typeusermessage-(\w+)/', $datain, $dataget)) {
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "typeusermessage", $dataget[1]);
    $listbtn = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "همه کاربران", 'callback_data' => 'typeagent-all'],
            ],
            [
                ['text' => "کاربران گروه f", 'callback_data' => 'typeagent-f'],
            ],
            [
                ['text' => "کاربران گروه n", 'callback_data' => 'typeagent-n'],
            ],
            [
                ['text' => "کاربران گروه n2", 'callback_data' => 'typeagent-n2'],
            ],
            [
                ['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeservice-' . $userdata['typeservice']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 سرویس برای چه دسته از کاربران اعمال شود؟", $listbtn);
} elseif (preg_match('/^typeagent-(\w+)/', $datain, $dataget)) {
    $type = $dataget[1];
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "agent", $type);
    if ($userdata['typeusermessage'] == "customer") {
        $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE agent = :agent OR agent = 'all'");
        $stmt->bindParam(':agent', $type);
        $stmt->execute();
        $list_panel = ['inline_keyboard' => []];
        $list_panel['inline_keyboard'][] = [['text' => "تمامی پنل ها", 'callback_data' => 'locationmessage_all']];
        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $list_panel['inline_keyboard'][] = [
                ['text' => $result['name_panel'], 'callback_data' => "locationmessage_{$result['code_panel']}"]
            ];
        }
        $list_panel['inline_keyboard'][] = [['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeusermessage-' . $userdata['typeusermessage']],];
        Editmessagetext($from_id, $message_id, "📌 پیام برای کدام کاربران موجود در پنل های زیر ارسال شود.", json_encode($list_panel));
        return;
    }
    if ($userdata['typeservice'] == "xdaynotmessage" or $userdata['typeservice'] == "sendmessage" or $userdata['typeservice'] == "forwardmessage") {
        $listbtn = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "بله", 'callback_data' => 'typepinmessage-yes'],
                    ['text' => "خیر", 'callback_data' => 'typepinmessage-no'],
                ],
                [
                    ['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeusermessage-' . $userdata['typeusermessage']],
                ],
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 آیا می خواهید پیام ارسال شده پین شود یا خیر.", $listbtn);
        return;
    }
    if ($userdata['typeservice'] == "xdaynotmessage") {
        step("gettextday", $from_id);
        sendmessage($from_id, "📌 در این قابلیت پیام به کاربرانی ارسال میشود که تعیین  میکنید چند روز از ربات استفاده نکرده اند
تعداد روز خود را ارسال نمایید.", $backadmin, 'HTML');
        return;
    }
    step("gettextSystemMessage", $from_id);
    sendmessage($from_id, "📌 متن پیام خود را ارسال نمایید.", $backadmin, 'HTML');
} elseif (preg_match('/^locationmessage_(\w+)/', $datain, $dataget)) {
    $typeoanel = $dataget[1];
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "selectpanel", $typeoanel);
    if ($userdata['typeservice'] == "xdaynotmessage" or $userdata['typeservice'] == "sendmessage" or $userdata['typeservice'] == "forwardmessage") {
        $listbtn = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "بله", 'callback_data' => 'typepinmessage-yes'],
                    ['text' => "خیر", 'callback_data' => 'typepinmessage-no'],
                ],
                [
                    ['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeagent-' . $userdata['agent']],
                ],
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 آیا می خواهید پیام ارسال شده پین شود یا خیر.", $listbtn);
        return;
    }
    if ($userdata['typeservice'] == "xdaynotmessage") {
        step("gettextday", $from_id);
        sendmessage($from_id, "📌 در این قابلیت پیام به کاربرانی ارسال میشود که تعیین  میکنید چند روز از ربات استفاده نکرده اند
تعداد روز خود را ارسال نمایید.", $backadmin, 'HTML');
        return;
    }
    step("gettextSystemMessage", $from_id);
    sendmessage($from_id, "📌 متن پیام خود را ارسال نمایید.", $backadmin, 'HTML');
} elseif (preg_match('/^typepinmessage-(\w+)/', $datain, $dataget)) {
    $type = $dataget[1];
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "typepinmessage", $type);
    $listbtn = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "دکمه استارت", 'callback_data' => 'btntypemessage-start'],
                ['text' => "دکمه آموزش", 'callback_data' => 'btntypemessage-helpbtn'],
            ],
            [
                ['text' => "دکمه خرید", 'callback_data' => 'btntypemessage-buy'],
                ['text' => "دکمه اکانت تست", 'callback_data' => 'btntypemessage-usertestbtn'],
            ],
            [
                ['text' => "دکمه زیرمجموعه گیری ", 'callback_data' => 'btntypemessage-affiliatesbtn'],
                ['text' => "شارژ حساب کاربری", 'callback_data' => 'btntypemessage-addbalance'],
            ],
            [
                ['text' => "ارسال بدون دکمه", 'callback_data' => 'btntypemessage-none'],
            ],
            [
                ['text' => "بازگشت به منوی قبل", 'callback_data' => 'typeagent-' . $userdata['agent']],
            ],
        ]
    ]);
    if ($userdata['typeservice'] == "forwardmessage") {
        step("gettextSystemMessage", $from_id);
        sendmessage($from_id, "📌 متن پیام خود را ارسال نمایید.", $backadmin, 'HTML');
        return;
    }
    Editmessagetext($from_id, $message_id, "📌 اگر می خواهید زیر پیام دکمه ای نمایش داده شود از لیست زیر گزینه ای را انتخاب کنید در غیر اینصورت دکمه  ارسال بدون دکمه را بزنید", $listbtn);
} elseif (preg_match('/^btntypemessage-(\w+)/', $datain, $dataget)) {
    deletemessage($from_id, $message_id);
    $type = $dataget[1];
    savedata("save", "btntypemessage", $type);
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    if ($userdata['typeservice'] == "xdaynotmessage") {
        step("gettextday", $from_id);
        sendmessage($from_id, "📌 در این قابلیت پیام به کاربرانی ارسال میشود که تعیین  میکنید چند روز از ربات استفاده نکرده اند
تعداد روز خود را ارسال نمایید.", $backadmin, 'HTML');
        return;
    }
    step("gettextSystemMessage", $from_id);
    sendmessage($from_id, "📌 متن پیام خود را ارسال نمایید.", $backadmin, 'HTML');
} elseif ($user['step'] == "gettextday") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    savedata("save", "daynoyuse", $text);
    step("gettextSystemMessage", $from_id);
    sendmessage($from_id, "📌 متن پیام خود را ارسال نمایید.", $backadmin, 'HTML');
} elseif ($user['step'] == "gettextSystemMessage") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    if ($userdata['typeservice'] == "forwardmessage") {
        savedata("save", "message", $message_id);
    } elseif ($userdata['typeservice'] == "xdaynotmessage") {
        if ($text) {
            savedata("save", "message", $text);
        } else {
            sendmessage($from_id, "📌  در بخش کاربرانی که به تعداد روز تعیین شده استفاده نکردند فقط امکان ارسال متن وجود دارد.", $backadmin, 'HTML');
            return;
        }
    } elseif ($userdata['typeservice'] == "sendmessage") {
        if ($text) {
            savedata("save", "message", $text);
        } else {
            sendmessage($from_id, "📌  در بخش ارسال همگانی فقط امکان ارسال متن وجود دارد.", $backadmin, 'HTML');
            return;
        }
    }
    $typesend = [
        "xdaynotmessage" => "کاربرانی که به تعداد روز تعیین شده استفاده نکردند",
        "sendmessage" => "ارسال همگانی",
        "forwardmessage" => "فوروارد همگانی",
        "unpinmessage" => "لغو پیام پین شده"
    ][$userdata['typeservice']];
    $typeservice = [
        "all" => "ارسال به همه کاربران",
        "customer" => "مشتریان",
        "nonecustomer" => "کسانی که خرید نداشتند",
    ][$userdata['typeusermessage']];
    if ($userdata['typeservice'] == "xdaynotmessage") {
        $textday = "تعداد روزی که کاربر پیام نداده است : {$userdata['daynoyuse']}";
    } else {
        $textday = "";
    }
    $textconfirm = "📌 شما در حال انجام عملیات مربوط به ارسال پیام هستید با بررسی اطلاعات زیر و تایید دکمه زیر عملیات ارسال شروع خواهد شد.
⚙️ نوع عملیات : $typesend
🎛 نوع سرویس : $typeservice
🗂 نوع کاربری : {$userdata['agent']}
$textday
";
    $startaction = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "تایید و شروع عملیات", 'callback_data' => 'startaction'],
            ],
        ]
    ]);
    sendmessage($from_id, $textconfirm, $startaction, 'HTML');
    sendmessage($from_id, "با تایید گزینه بالا فرآیند ارسال شروع خواهد شد", $keyboardadmin, 'HTML');
    step("home", $from_id);
} elseif ($datain == "startaction") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typeservice'])) {
        sendmessage($from_id, "❌ خطایی رخ داده لطفا مراحل ارسال پیام از اول انجام دهید", $keyboardadmin, 'HTML');
        return;
    }
    $agent = $userdata['agent'];
    $typeservice = $userdata['typeservice'];
    $typeusermessage = $userdata['typeusermessage'];
    $text = $userdata['message'];
    $cancelmessage = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "لغو عملیات", 'callback_data' => 'cancel_sendmessage'],
            ],
        ]
    ]);

    if ($typeservice == "unpinmessage") {
        $userlist = json_encode(select("user", "id", null, null, "fetchAll"));
        $message_id = Editmessagetext($from_id, $message_id, "✅ عملیات آغاز گردید پس از پایان اطلاع رسانی خواهد شد.", $cancelmessage);
        $dataunpin = json_encode(array(
            "id_admin" => $from_id,
            'type' => "unpinmessage",
            "id_message" => $message_id['result']['message_id']
        ));
        file_put_contents("cronbot/users.json", $userlist);
        file_put_contents('cronbot/info', $dataunpin);
    } elseif ($typeservice == "sendmessage") {
        if ($agent == "all") {
            if ($typeusermessage == "all") {
                $userslist = json_encode(select("user", "id", "User_Status", "Active", "fetchAll"));
            } elseif ($typeusermessage == "customer") {
                if ($userdata['selectpanel'] == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}') AND u.User_Status = 'Active'");
                }
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        } else {
            if ($typeusermessage == "all") {
                $userslist = json_encode(select("user", "id", "agent", $agent, "fetchAll"));
            } elseif ($typeusermessage == "customer") {
                if ($userdata['selectpanel'] == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE  u.agent =  :agent AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}') AND u.User_Status = 'Active'");
                }
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        }
        $message_id = Editmessagetext($from_id, $message_id, "✅ عملیات آغاز گردید پس از پایان اطلاع رسانی خواهد شد.", $cancelmessage);
        $data = json_encode(array(
            "id_admin" => $from_id,
            'type' => "sendmessage",
            "id_message" => $message_id['result']['message_id'],
            "message" => $userdata['message'],
            "pingmessage" => $userdata['typepinmessage'],
            "btnmessage" => $userdata['btntypemessage']
        ));
        file_put_contents("cronbot/users.json", $userslist);
        file_put_contents('cronbot/info', $data);
    } elseif ($typeservice == "forwardmessage") {
        if ($agent == "all") {
            if ($typeusermessage == "all") {
                $userslist = json_encode(select("user", "id", "User_Status", "Active", "fetchAll"));
            } elseif ($typeusermessage == "customer") {
                if ($userdata['selectpanel'] == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}') AND u.User_Status = 'Active'");
                }
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        } else {
            if ($typeusermessage == "all") {
                $userslist = json_encode(select("user", "id", "agent", $agent, "fetchAll"));
            } elseif ($typeusermessage == "customer") {
                if ($userdata['selectpanel'] == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}') AND u.User_Status = 'Active'");
                }
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id) AND u.User_Status = 'Active'");
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        }
        $message_id = Editmessagetext($from_id, $message_id, "✅ عملیات آغاز گردید پس از پایان اطلاع رسانی خواهد شد.", $cancelmessage);
        $data = json_encode(array(
            "id_admin" => $from_id,
            'type' => "forwardmessage",
            "id_message" => $message_id['result']['message_id'],
            "message" => $userdata['message'],
            "pingmessage" => $userdata['typepinmessage'],
        ));
        file_put_contents("cronbot/users.json", $userslist);
        file_put_contents('cronbot/info', $data);
    } elseif ($typeservice == "xdaynotmessage") {
        $timedaystamp = intval($userdata['daynoyuse']) * 86400;
        $timenouser = time() - $timedaystamp;
        if ($agent == "all") {
            $stmt = $pdo->prepare("SELECT id FROM user  WHERE last_message_time < $timenouser");
            $stmt->execute();
            $userslist = json_encode($stmt->fetchAll());
        } else {
            if ($typeusermessage == "all") {
                if ($typeusermessage == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time");
                    $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                    $stmt->execute();
                    $userslist = json_encode($stmt->fetchAll());
                } elseif ($typeusermessage == "customer") {
                    if ($userdata['selectpanel'] == "all") {
                        $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);");
                    } else {
                        $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                        $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}');");
                    }
                    $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                    $stmt->execute();
                    $userslist = json_encode($stmt->fetchAll());
                } elseif ($typeusermessage == "nonecustomer") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.last_message_time < :time AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);");
                    $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                    $stmt->execute();
                    $userslist = json_encode($stmt->fetchAll());
                }
            } elseif ($typeusermessage == "customer") {
                if ($userdata['selectpanel'] == "all") {
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);");
                } else {
                    $panel = select("marzban_panel", "*", "code_panel", $userdata['selectpanel'], "select");
                    $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND u.last_message_time < :time AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id AND i.Service_location = '{$panel['name_panel']}');");
                }
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            } elseif ($typeusermessage == "nonecustomer") {
                $stmt = $pdo->prepare("SELECT u.id FROM user u WHERE u.agent =  :agent AND u.last_message_time < :time AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);");
                $stmt->bindParam(':agent', $agent, PDO::PARAM_STR);
                $stmt->bindParam(':time', $timenouser, PDO::PARAM_STR);
                $stmt->execute();
                $userslist = json_encode($stmt->fetchAll());
            }
        }
        $message_id = Editmessagetext($from_id, $message_id, "✅ عملیات آغاز گردید پس از پایان اطلاع رسانی خواهد شد.", $cancelmessage);
        $data = json_encode(array(
            "id_admin" => $from_id,
            'type' => "xdaynotmessage",
            "id_message" => $message_id['result']['message_id'],
            "message" => $userdata['message'],
            "pingmessage" => $userdata['typepinmessage'],
            "btnmessage" => $userdata['btntypemessage']
        ));
        file_put_contents("cronbot/users.json", $userslist);
        file_put_contents('cronbot/info', $data);
    }
} elseif ($datain == "cancel_sendmessage") {
    if (file_exists('cronbot/users.json')) unlink('cronbot/users.json');
    unlink('cronbot/info');
    deletemessage($from_id, $message_id);
    sendmessage($from_id, "📌 ارسال پیام لغو گردید.", null, 'HTML');
}
//_____________________[ text ]____________________________//
elseif ($text == "📝 تنظیم متن ربات" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $textbot, 'HTML');
} elseif ($text == "تنظیم متن شروع" && $adminrulecheck['rule'] == "administrator") {
    $textstart = $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_start']}</code>";
    sendmessage($from_id, $textstart, $backadmin, 'HTML');
    sendmessage($from_id, "📌 متغییر های قابل استفاده 

⚠️نام کاربری : 
 <blockquote>{username}</blockquote>

⚠️نام اکانت :‌
<blockquote>{first_name}</blockquote>

⚠️نام خانوادگی اکانت :‌
<blockquote>{last_name}</blockquote>

⚠️زمان فعلی : 
<blockquote>{time}</blockquote>

⚠️ نسخه فعلی ربات  : 
<blockquote>{version}</blockquote>", null, "html");
    step('changetextstart', $from_id);
} elseif ($user['step'] == "changetextstart") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_start");
    step('home', $from_id);
} elseif ($text == "دکمه سرویس خریداری شده" && $adminrulecheck['rule'] == "administrator") {
    $textstart = $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_Purchased_services']}</code>";
    sendmessage($from_id, $textstart, $backadmin, 'HTML');
    step('changetextinfo', $from_id);
} elseif ($user['step'] == "changetextinfo") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_Purchased_services");
    step('home', $from_id);
} elseif ($text == "دکمه اکانت تست" && $adminrulecheck['rule'] == "administrator") {
    $textstart = $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_usertest']}</code>";
    sendmessage($from_id, $textstart, $backadmin, 'HTML');
    step('changetextusertest', $from_id);
} elseif ($user['step'] == "changetextusertest") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_usertest");
    step('home', $from_id);
} elseif ($text == "متن دکمه 📚 آموزش" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_help']}</code>", $backadmin, 'HTML');
    step('text_help', $from_id);
} elseif ($user['step'] == "text_help") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_help");
    step('home', $from_id);
} elseif ($text == "متن درخواست نمایندگی" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textrequestagent']}</code>", $backadmin, 'HTML');
    step('textrequestagent', $from_id);
} elseif ($user['step'] == "textrequestagent") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "textrequestagent");
    step('home', $from_id);
} elseif ($text == "متن دکمه  نمایندگی" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textpanelagent']}</code>", $backadmin, 'HTML');
    step('textpanelagent', $from_id);
} elseif ($user['step'] == "textpanelagent") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "textpanelagent");
    step('home', $from_id);
} elseif ($text == "متن دکمه ☎️ پشتیبانی" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_support']}</code>", $backadmin, 'HTML');
    step('text_support', $from_id);
} elseif ($user['step'] == "text_support") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_support");
    step('home', $from_id);
} elseif ($text == "دکمه سوالات متداول" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_fq']}</code>", $backadmin, 'HTML');
    step('text_fq', $from_id);
} elseif ($user['step'] == "text_fq") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_fq");
    step('home', $from_id);
} elseif ($text == "📝 تنظیم متن توضیحات سوالات متداول" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_dec_fq']}</code>", $backadmin, 'HTML');
    step('text_dec_fq', $from_id);
} elseif ($user['step'] == "text_dec_fq") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_dec_fq");
    step('home', $from_id);
} elseif ($text == "📝 تنظیم متن توضیحات عضویت اجباری" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_channel']}</code>", $backadmin, 'HTML');
    step('text_channel', $from_id);
} elseif ($user['step'] == "text_channel") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_channel");
    step('home', $from_id);
} elseif ($text == "متن دکمه کیف پول" && $adminrulecheck['rule'] == "administrator") {
    $textstart = $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['accountwallet']}</code>";
    sendmessage($from_id, $textstart, $backadmin, 'HTML');
    step('accountwallet', $from_id);
} elseif ($user['step'] == "accountwallet") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "accountwallet");
    step('home', $from_id);
} elseif ($text == "متن دکمه کد هدیه" && $adminrulecheck['rule'] == "administrator") {
    $textstart = $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_Discount']}</code>";
    sendmessage($from_id, $textstart, $backadmin, 'HTML');
    step('text_Discount', $from_id);
} elseif ($user['step'] == "text_Discount") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_Discount");
    step('home', $from_id);
} elseif ($text == "دکمه افزایش موجودی" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_Add_Balance']}</code>", $backadmin, 'HTML');
    step('text_Add_Balance', $from_id);
} elseif ($user['step'] == "text_Add_Balance") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_Add_Balance");
    step('home', $from_id);
} elseif ($text == "متن دکمه خرید اشتراک" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_sell']}</code>", $backadmin, 'HTML');
    step('text_sell', $from_id);
} elseif ($user['step'] == "text_sell") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_sell");
    step('home', $from_id);
} elseif ($text == "متن دکمه زیرمجموعه گیری" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_affiliates']}</code>", $backadmin, 'HTML');
    step('text_affiliates', $from_id);
} elseif ($user['step'] == "text_affiliates") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_affiliates");
    step('home', $from_id);
} elseif ($text == "متن دکمه لیست تعرفه" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_Tariff_list']}</code>", $backadmin, 'HTML');
    step('text_Tariff_list', $from_id);
} elseif ($user['step'] == "text_Tariff_list") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_Tariff_list");
    step('home', $from_id);
} elseif ($text == "متن توضیحات لیست تعرفه" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_dec_Tariff_list']}</code>", $backadmin, 'HTML');
    step('text_dec_Tariff_list', $from_id);
} elseif ($user['step'] == "text_dec_Tariff_list") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_dec_Tariff_list");
    step('home', $from_id);
} elseif ($text == "متن انتخاب لوکیشن" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textselectlocation']}</code>", $backadmin, 'HTML');
    step('textselectlocation', $from_id);
} elseif ($user['step'] == "textselectlocation") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "textselectlocation");
    step('home', $from_id);
} elseif ($text == "متن پیش فاکتور" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_pishinvoice']}</code>", $backadmin, 'HTML');
    sendmessage($from_id, "نام های فارسی متغییر : 
username : نام کاربری کانفیگ 
name_product : نام محصول
Service_time : زمان سرویس
price : قیمت سرویس
Volume : حجم سرویس
userBalance : موجودی کاربر 
note : یادداشت

⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_pishinvoice', $from_id);
} elseif ($user['step'] == "text_pishinvoice") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_pishinvoice");
    step('home', $from_id);
} elseif ($text == "متن بعد خرید" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textafterpay']}</code>", $backadmin, 'HTML');
    sendmessage($from_id, "نام های فارسی متغییر : 
username : نام کاربری کانفیگ 
name_service : نام محصول
day : زمان سرویس
location : موقعیت سرویس
volume : حجم سرویس
config : لینک ساب
links : کانفیگ بدون کپی شدن
links2 : لینک ساب بدون کپی شدن

⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_afterpaytext', $from_id);
} elseif ($user['step'] == "text_afterpaytext") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "textafterpay");
    step('home', $from_id);
} elseif ($text == "متن بعد خرید ibsng" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textafterpayibsng']}</code>", $backadmin, 'HTML');
    sendmessage($from_id, "نام های فارسی متغییر : 
username : نام کاربری کانفیگ 
name_service : نام محصول
day : زمان سرویس
location : موقعیت سرویس
volume : حجم سرویس
config : لینک ساب
links : کانفیگ بدون کپی شدن
links2 : لینک ساب بدون کپی شدن

⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_afterpaytextibsng', $from_id);
} elseif ($user['step'] == "text_afterpaytextibsng") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "textafterpayibsng");
    step('home', $from_id);
} elseif ($text == "متن کارت به کارت" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_cart']}</code>", $backadmin, 'HTML');
    sendmessage($from_id, "نام های فارسی متغییر : 
price : مبلغ تراکنش
card_number : شماره کارت 
name_card : نام دارنده کارت
⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_cart', $from_id);
} elseif ($user['step'] == "text_cart") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_cart");
    step('home', $from_id);
} elseif ($text == "تنظیم متن کارت به کارت خودکار" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_cart_auto']}</code>", $backadmin, 'HTML');
    sendmessage($from_id, "نام های فارسی متغییر : 
price : مبلغ تراکنش
card_number : شماره کارت 
name_card : نام دارنده کارت
⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_cart_auto', $from_id);
} elseif ($user['step'] == "text_cart_auto") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_cart_auto");
    step('home', $from_id);
} elseif ($text == "متن بعد گرفتن اکانت تست" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textaftertext']}</code>", $backadmin, 'HTML');
    sendmessage($from_id, "نام های فارسی متغییر : 
username : نام کاربری کانفیگ 
name_service : نام محصول
day : زمان سرویس
location : موقعیت سرویس
volume : حجم سرویس
config : لینک اتصال
links : کانفیگ بدون کپی شدن
links2 : لینک ساب بدون کپی

⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_aftertesttext', $from_id);
} elseif ($user['step'] == "text_aftertesttext") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "textaftertext");
    step('home', $from_id);
} elseif ($text == "متن بعد گرفتن اکانت دستی" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textmanual']}</code>", $backadmin, 'HTML');
    sendmessage($from_id, "نام های فارسی متغییر : 
username : نام کاربری کانفیگ 
name_service : نام محصول
location : موقعیت سرویس
config : اطلاعات سرویس

⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_textmanual', $from_id);
} elseif ($text == "متن کرون تست" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['crontest']}</code>", $backadmin, 'HTML');
    sendmessage($from_id, "نام های فارسی متغییر : 
username : نام کاربری کانفیگ 

⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_crontest', $from_id);
} elseif ($user['step'] == "text_crontest") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "crontest");
    step('home', $from_id);
} elseif ($text == "متن بعد گرفتن اکانت دستی" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['textmanual']}</code>", $backadmin, 'HTML');
    sendmessage($from_id, "نام های فارسی متغییر : 
username : نام کاربری کانفیگ 
name_service : نام محصول
location : موقعیت سرویس
config : اطلاعات سرویس

⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_textmanual', $from_id);
} elseif ($user['step'] == "text_textmanual") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "textmanual");
    step('home', $from_id);
} elseif ($text == "متن بعد گرفتن اکانت WGDashboard" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_wgdashboard']}</code>", $backadmin, 'HTML');
    sendmessage($from_id, "نام های فارسی متغییر : 
username : نام کاربری کانفیگ 
name_service : نام محصول
day : زمان سرویس
location : موقعیت سرویس
volume : حجم سرویس

⚠️ حتما این نام ها باید داخل آکلاد باشند ", null, 'HTML');
    step('text_wgdashboard', $from_id);
} elseif ($user['step'] == "text_wgdashboard") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_wgdashboard");
    step('home', $from_id);
} elseif ($text == "دکمه تمدید" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_extend']}</code>", $backadmin, 'HTML');
    step('text_extend', $from_id);
} elseif ($user['step'] == "text_extend") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_extend");
    step('home', $from_id);
} elseif (preg_match('/sendmessageuser_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    savedata("clear", "iduser", $iduser);
    sendmessage($from_id, "📌 متن یا تصویر خود را ارسال نمایید", $backadmin, 'HTML');
    step('sendmessagetext', $from_id);
} elseif ($user['step'] == "sendmessagetext") {
    if ($photo) {
        savedata("save", "type", "photo");
        savedata("save", "photoid", $photoid);
        savedata("save", "text", $caption);
    } else {
        savedata("save", "text", $text);
        savedata("save", "type", "text");
    }
    $textb = "📌 کاربر بتواند پاسخ دهد یاخیر ؟
1 - بله  پاسخ دهد 
2 - خیر پاسخ ندهد
پاسخ را به عدد ارسال کنید";
    sendmessage($from_id, $textb, $backadmin, 'HTML');
    step('sendmessagetid', $from_id);
} elseif ($user['step'] == "sendmessagetid") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $textsendadmin = "
👤 یک پیام از طرف ادمین ارسال شده است  
متن پیام:

{$userdata['text']}";
    if (intval($text) == "1") {
        $Response = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Responseuser'],
                ],
            ]
        ]);
        if ($userdata['type'] == "photo") {
            telegram('sendphoto', [
                'chat_id' => $userdata['iduser'],
                'photo' => $userdata['photoid'],
                'caption' => $textsendadmin,
                'reply_markup' => $Response,
                'parse_mode' => "HTML",
            ]);
        } else {
            sendmessage($userdata['iduser'], $textsendadmin, $Response, 'HTML');
        }
    } else {
        if ($userdata['type'] == "photo") {
            telegram('sendphoto', [
                'chat_id' => $userdata['iduser'],
                'photo' => $userdata['photoid'],
                'caption' => $textsendadmin,
                'parse_mode' => "HTML",
            ]);
        } else {
            sendmessage($userdata['iduser'], $textsendadmin, null, 'HTML');
        }
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['MessageSent'], $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "📤 فوروارد پیام برای یک کاربر") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['GetText'], $backadmin, 'HTML');
    step('getmessageforward', $from_id);
} elseif ($user['step'] == "getmessageforward") {
    savedata("clear", "messageid", $message_id);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['GetIDMessage'], $backadmin, 'HTML');
    step('getbtnresponseforward', $from_id);
} elseif ($user['step'] == "getbtnresponseforward") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    forwardMessage($from_id, $userdata['messageid'], $text);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['MessageSent'], $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "📚 بخش آموزش" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboardhelpadmin, 'HTML');
} elseif ($text == "📚 اضافه کردن آموزش" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['Help']['GetAddNameHelp'], $backadmin, 'HTML');
    step('add_name_help', $from_id);
} elseif ($user['step'] == "add_name_help") {
    if (strlen($text) >= 150) {
        sendmessage($from_id, "❌ نام آموزش باید کمتر از 150 کاراکتر باشد", null, 'HTML');
        return;
    }
    $helpexits = select("help", "*", "name_os", $text, "count");
    if ($helpexits != 0) {
        sendmessage($from_id, "❌ نام آموزش وجود دارد از نام دیگری استفاده نمایید.", null, 'HTML');
        return;
    }
    $stmt = $connect->prepare("INSERT IGNORE INTO help (name_os) VALUES (?)");
    $stmt->bind_param("s", $text);
    $stmt->execute();
    update("user", "Processing_value", $text, "id", $from_id);
    if ($setting['categoryhelp'] == "0") {
        update("help", "category", "0", "name_os", $user['Processing_value']);
        sendmessage($from_id, $textbotlang['Admin']['Help']['GetAddDecHelp'], $backadmin, 'HTML');
        step('add_dec', $from_id);
        return;
    }
    sendmessage($from_id, "📌 نام دسته بندی برای آموزش را ارسال نمایید", $backadmin, 'HTML');
    step('getcatgoryhelp', $from_id);
} elseif ($user['step'] == "getcatgoryhelp") {
    update("help", "category", $text, "name_os", $user['Processing_value']);
    sendmessage($from_id, $textbotlang['Admin']['Help']['GetAddDecHelp'], $backadmin, 'HTML');
    step('add_dec', $from_id);
} elseif ($user['step'] == "add_dec") {
    if ($photo) {
        if (isset($photoid))
            update("help", "Media_os", $photoid, "name_os", $user['Processing_value']);
        if (isset($caption))
            update("help", "Description_os", $caption, "name_os", $user['Processing_value']);
        update("help", "type_Media_os", "photo", "name_os", $user['Processing_value']);
    } elseif ($text) {
        update("help", "Description_os", $text, "name_os", $user['Processing_value']);
    } elseif ($video) {
        if (isset($videoid))
            update("help", "Media_os", $videoid, "name_os", $user['Processing_value']);
        if (isset($caption))
            update("help", "Description_os", $caption, "name_os", $user['Processing_value']);
        update("help", "type_Media_os", "video", "name_os", $user['Processing_value']);
    } elseif ($document) {
        if (isset($fileid))
            update("help", "Media_os", $fileid, "name_os", $user['Processing_value']);
        if (isset($caption))
            update("help", "Description_os", $caption, "name_os", $user['Processing_value']);
        update("help", "type_Media_os", "document", "name_os", $user['Processing_value']);
    }
    sendmessage($from_id, $textbotlang['Admin']['Help']['SaveHelp'], $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "❌ حذف آموزش" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['Help']['SelectName'], $json_list_helpkey, 'HTML');
    step('remove_help', $from_id);
} elseif ($user['step'] == "remove_help") {
    $stmt = $pdo->prepare("DELETE FROM help WHERE name_os = :name_os");
    $stmt->bindParam(':name_os', $text, PDO::PARAM_STR);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['Help']['RemoveHelp'], $keyboardhelpadmin, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/Response_(\w+)/', $datain, $dataget) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "support")) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    step('getmessageAsAdmin', $from_id);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['GetTextResponse'], $backadmin, 'HTML');
} elseif ($user['step'] == "getmessageAsAdmin") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SendMessageuser'], null, 'HTML');
    $Respuseronse = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Responseuser'],
            ],
        ]
    ]);
    if ($text) {
        $textSendAdminToUser = "
📩 یک پیام از سمت مدیریت برای شما ارسال گردید.
                    
متن پیام : 
$text";
        sendmessage($user['Processing_value'], $textSendAdminToUser, $Respuseronse, 'HTML');
    }
    if ($photo) {
        $textSendAdminToUser = "
📩 یک پیام از سمت مدیریت برای شما ارسال گردید.
                    
متن پیام : 
$caption";
        telegram('sendphoto', [
            'chat_id' => $user['Processing_value'],
            'photo' => $photoid,
            'reply_markup' => $Respuseronse,
            'caption' => $textSendAdminToUser,
            'parse_mode' => "HTML",
        ]);
    }
    step('home', $from_id);
} elseif ($text == "⚙️ وضعیت قابلیت ها" && $adminrulecheck['rule'] == "administrator") {
    if ($setting['Bot_Status'] == "✅  ربات روشن است") {
        update("setting", "Bot_Status", "botstatuson");
    } elseif ($setting['Bot_Status'] == "❌ ربات خاموش است") {
        update("setting", "Bot_Status", "botstatusoff");
    }
    if ($setting['roll_Status'] == "✅ تایید قانون روشن است") {
        update("setting", "roll_Status", "rolleon");
    } elseif ($setting['roll_Status'] == "❌ تایید قوانین خاموش است") {
        update("setting", "roll_Status", "rolleoff");
    }
    if ($setting['get_number'] == "✅ تایید شماره موبایل روشن است") {
        update("setting", "get_number", "onAuthenticationphone");
    } elseif ($setting['get_number'] == "❌ احرازهویت شماره تماس غیرفعال است") {
        update("setting", "get_number", "offAuthenticationphone");
    }
    if ($setting['iran_number'] == "✅ احرازشماره ایرانی روشن است") {
        update("setting", "iran_number", "onAuthenticationiran");
    } elseif ($setting['iran_number'] == "❌ بررسی شماره ایرانی غیرفعال است") {
        update("setting", "iran_number", "offAuthenticationiran");
    }
    $status_cron = json_decode($setting['cron_status'], true);
    $setting = select("setting", "*", null, null, "select");
    $name_status = [
        'botstatuson' => $textbotlang['Admin']['Status']['statuson'],
        'botstatusoff' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Bot_Status']];
    $name_status_username = [
        'onnotuser' => $textbotlang['Admin']['Status']['statuson'],
        'offnotuser' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['NotUser']];
    $name_status_notifnewuser = [
        'onnewuser' => $textbotlang['Admin']['Status']['statuson'],
        'offnewuser' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusnewuser']];
    $name_status_showagent = [
        'onrequestagent' => $textbotlang['Admin']['Status']['statuson'],
        'offrequestagent' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusagentrequest']];
    $name_status_role = [
        'rolleon' => $textbotlang['Admin']['Status']['statuson'],
        'rolleoff' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['roll_Status']];
    $Authenticationphone = [
        'onAuthenticationphone' => $textbotlang['Admin']['Status']['statuson'],
        'offAuthenticationphone' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['get_number']];
    $Authenticationiran = [
        'onAuthenticationiran' => $textbotlang['Admin']['Status']['statuson'],
        'offAuthenticationiran' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['iran_number']];
    $statusinline = [
        'oninline' => $textbotlang['Admin']['Status']['statuson'],
        'offinline' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['inlinebtnmain']];
    $statusverify = [
        'onverify' => $textbotlang['Admin']['Status']['statuson'],
        'offverify' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['verifystart']];
    $statuspvsupport = [
        'onpvsupport' => $textbotlang['Admin']['Status']['statuson'],
        'offpvsupport' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statussupportpv']];
    $statusnameconfig = [
        'onnamecustom' => $textbotlang['Admin']['Status']['statuson'],
        'offnamecustom' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusnamecustom']];
    $statusnamebulk = [
        'onbulk' => $textbotlang['Admin']['Status']['statuson'],
        'offbulk' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['bulkbuy']];
    $statusverifybyuser = [
        'onverify' => $textbotlang['Admin']['Status']['statuson'],
        'offverify' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['verifybucodeuser']];
    $score = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['scorestatus']];
    $wheel_luck = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['wheelـluck']];
    $refralstatus = [
        'onaffiliates' => $textbotlang['Admin']['Status']['statuson'],
        'offaffiliates' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['affiliatesstatus']];
    $btnstatuscategory = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['categoryhelp']];
    $btnstatuslinkapp = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['linkappstatus']];
    $cronteststatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['test']];
    $crondaystatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['day']];
    $cronvolumestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['volume']];
    $cronremovestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['remove']];
    $cronremovevolumestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['remove_volume']];
    $cronuptime_nodestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['uptime_node']];
    $cronuptime_panelstatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['uptime_panel']];
    $cronon_holdtext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['on_hold']];
    $languagestatus = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['languageen']];
    $languagestatusru = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['languageru']];
    $wheelagent = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['wheelagent']];
    $Lotteryagent = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Lotteryagent']];
    $statusfirstwheel = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusfirstwheel']];
    $statuslimitchangeloc = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statuslimitchangeloc']];
    $statusDebtsettlement = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Debtsettlement']];
    $statusDice = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Dice']];
    $statusnotef = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusnoteforf']];
    $status_copy_cart = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statuscopycart']];
    $keyboard_config_text = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['status_keyboard_config']];
    $Bot_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['Status']['subject'], 'callback_data' => "subject"],
                ['text' => $textbotlang['Admin']['Status']['statussubject'], 'callback_data' => "subjectde"],
            ],
            [
                ['text' => $name_status, 'callback_data' => "editstsuts-statusbot-{$setting['Bot_Status']}"],
                ['text' => $textbotlang['Admin']['Status']['stautsbot'], 'callback_data' => "statusbot"],
            ],
            [
                ['text' => $name_status_username, 'callback_data' => "editstsuts-usernamebtn-{$setting['NotUser']}"],
                ['text' => $textbotlang['Admin']['Status']['statususernamebtn'], 'callback_data' => "usernamebtn"],
            ],
            [
                ['text' => $name_status_notifnewuser, 'callback_data' => "editstsuts-notifnew-{$setting['statusnewuser']}"],
                ['text' => $textbotlang['Admin']['Status']['statusnotifnewuser'], 'callback_data' => "statusnewuser"],
            ],
            [
                ['text' => $name_status_showagent, 'callback_data' => "editstsuts-showagent-{$setting['statusagentrequest']}"],
                ['text' => $textbotlang['Admin']['Status']['statusshowagent'], 'callback_data' => "statusnewuser"],
            ],
            [
                ['text' => $name_status_role, 'callback_data' => "editstsuts-role-{$setting['roll_Status']}"],
                ['text' => $textbotlang['Admin']['Status']['stautsrolee'], 'callback_data' => "stautsrolee"],
            ],
            [
                ['text' => $Authenticationphone, 'callback_data' => "editstsuts-Authenticationphone-{$setting['get_number']}"],
                ['text' => $textbotlang['Admin']['Status']['Authenticationphone'], 'callback_data' => "Authenticationphone"],
            ],
            [
                ['text' => $Authenticationiran, 'callback_data' => "editstsuts-Authenticationiran-{$setting['iran_number']}"],
                ['text' => $textbotlang['Admin']['Status']['Authenticationiran'], 'callback_data' => "Authenticationiran"],
            ],
            [
                ['text' => $statusinline, 'callback_data' => "editstsuts-inlinebtnmain-{$setting['inlinebtnmain']}"],
                ['text' => $textbotlang['Admin']['Status']['inlinebtns'], 'callback_data' => "inlinebtnmain"],
            ],
            [
                ['text' => $statusverify, 'callback_data' => "editstsuts-verifystart-{$setting['verifystart']}"],
                ['text' => "🔒 احراز هویت", 'callback_data' => "verify"],
            ],
            [
                ['text' => $statuspvsupport, 'callback_data' => "editstsuts-statussupportpv-{$setting['statussupportpv']}"],
                ['text' => "👤 پشتیبانی در پیوی", 'callback_data' => "statussupportpv"],
            ],
            [
                ['text' => $statusnameconfig, 'callback_data' => "editstsuts-statusnamecustom-{$setting['statusnamecustom']}"],
                ['text' => "📨 یادداشت کانفیگ", 'callback_data' => "statusnamecustom"],
            ],
            [
                ['text' => $statusnotef, 'callback_data' => "editstsuts-statusnamecustomf-{$setting['statusnoteforf']}"],
                ['text' => "📨 یادداشت کاربر عادی", 'callback_data' => "statusnamecustomf"],
            ],
            [
                ['text' => $statusnamebulk, 'callback_data' => "editstsuts-bulkbuy-{$setting['bulkbuy']}"],
                ['text' => "🛍 وضعیت خرید عمده", 'callback_data' => "bulkbuy"],
            ],
            [
                ['text' => $statusverifybyuser, 'callback_data' => "editstsuts-verifybyuser-{$setting['verifybucodeuser']}"],
                ['text' => "🔑 احراز هویت با لینک", 'callback_data' => "verifybyuser"],
            ],
            [
                ['text' => $btnstatuscategory, 'callback_data' => "editstsuts-btn_status_category-{$setting['categoryhelp']}"],
                ['text' => "📗دسته بندی آموزش", 'callback_data' => "btn_status_category"],
            ],
            [
                ['text' => $wheelagent, 'callback_data' => "editstsuts-wheelagent-{$setting['wheelagent']}"],
                ['text' => "🎲 گردونه شانس  نمایندگان", 'callback_data' => "wheelagent"],
            ],
            [
                ['text' => $keyboard_config_text, 'callback_data' => "editstsuts-keyconfig-{$setting['status_keyboard_config']}"],
                ['text' => "🔗 کیبورد کانفیگی", 'callback_data' => "keyconfig"],
            ],
            [
                ['text' => $statusDice, 'callback_data' => "editstsuts-Dice-{$setting['Dice']}"],
                ['text' => "🎰 نمایش تاس", 'callback_data' => "Dice"],
            ],
            [
                ['text' => $statusfirstwheel, 'callback_data' => "editstsuts-wheelagentfirst-{$setting['statusfirstwheel']}"],
                ['text' => "🎲 گردونه شانس خرید اول", 'callback_data' => "wheelagentfirst"],
            ],
            [
                ['text' => $Lotteryagent, 'callback_data' => "editstsuts-Lotteryagent-{$setting['Lotteryagent']}"],
                ['text' => "🎁 قرعه کشی نمایندگان", 'callback_data' => "Lotteryagent"],
            ],
            [
                ['text' => $statusDebtsettlement, 'callback_data' => "editstsuts-Debtsettlement-{$setting['Debtsettlement']}"],
                ['text' => "💎 تسویه بدهی", 'callback_data' => "Debtsettlement"],
            ],
            [
                ['text' => $status_copy_cart, 'callback_data' => "editstsuts-compycart-{$setting['statuscopycart']}"],
                ['text' => "💳 کپی شماره کارت", 'callback_data' => "copycart"],
            ],
            [
                ['text' => $cronteststatustext, 'callback_data' => "editstsuts-crontest-{$status_cron['test']}"],
                ['text' => "🔓کرون تست", 'callback_data' => "none"],
            ],
            [
                ['text' => $cronuptime_nodestatustext, 'callback_data' => "editstsuts-uptime_node-{$status_cron['uptime_node']}"],
                ['text' => "🎛 آپتایم نود", 'callback_data' => "none"],
            ],
            [
                ['text' => $cronuptime_panelstatustext, 'callback_data' => "editstsuts-uptime_panel-{$status_cron['uptime_panel']}"],
                ['text' => "🎛 آپتایم پنل", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ زمان هشدار", 'callback_data' => "settimecornday"],
                ['text' => $crondaystatustext, 'callback_data' => "editstsuts-cronday-{$status_cron['day']}"],
                ['text' => "🕚 کرون زمان", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ زمان اولین اتصال", 'callback_data' => "setting_on_holdcron"],
                ['text' => $cronon_holdtext, 'callback_data' => "editstsuts-on_hold-{$status_cron['on_hold']}"],
                ['text' => "🕚 کرون اولین اتصال", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ حجم هشدار", 'callback_data' => "settimecornvolume"],
                ['text' => $cronvolumestatustext, 'callback_data' => "editstsuts-cronvolume-{$status_cron['volume']}"],
                ['text' => "🔋 کرون حجم", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ زمان حذف", 'callback_data' => "settimecornremove"],
                ['text' => $cronremovestatustext, 'callback_data' => "editstsuts-notifremove-{$status_cron['remove']}"],
                ['text' => "❌ کرون حذف", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ زمان حذف", 'callback_data' => "settimecornremovevolume"],
                ['text' => $cronremovevolumestatustext, 'callback_data' => "editstsuts-notifremove_volume-{$status_cron['remove_volume']}"],
                ['text' => "❌ کرون حذف حجم", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ مدیریت", 'callback_data' => "cronjobs_settings"],
                ['text' => "⏱ نمایش لیست", 'callback_data' => "cronjobs_settings"],
                ['text' => "زمان‌بندی کرون‌ها", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "linkappsetting"],
                ['text' => $btnstatuslinkapp, 'callback_data' => "editstsuts-linkappstatus-{$setting['linkappstatus']}"],
                ['text' => "🔗لینک دانلود برنامه", 'callback_data' => "linkappstatus"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "scoresetting"],
                ['text' => $score, 'callback_data' => "editstsuts-score-{$setting['scorestatus']}"],
                ['text' => "🎁 قرعه کشی شبانه", 'callback_data' => "score"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "gradonhshans"],
                ['text' => $wheel_luck, 'callback_data' => "editstsuts-wheel_luck-{$setting['wheelـluck']}"],
                ['text' => "🎲 گردونه شانس", 'callback_data' => "wheel_luck"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "settingaffiliatesf"],
                ['text' => $refralstatus, 'callback_data' => "editstsuts-affiliatesstatus-{$setting['affiliatesstatus']}"],
                ['text' => "🎁زیرمجموعه", 'callback_data' => "affiliatesstatus"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "changeloclimit"],
                ['text' => $statuslimitchangeloc, 'callback_data' => "editstsuts-changeloc-{$setting['statuslimitchangeloc']}"],
                ['text' => "🌍 محدودیت تغییر لوکیشن", 'callback_data' => "changeloc"],
            ],
            [
                ['text' => "❌ بستن", 'callback_data' => 'close_stat']
            ]
        ]
    ]);
    sendmessage($from_id, $textbotlang['Admin']['Status']['BotTitle'], $Bot_Status, 'HTML');
} elseif (preg_match('/^editstsuts-(.*)-(.*)/', $datain, $dataget)) {
    $status_cron = json_decode($setting['cron_status'], true);
    $type = $dataget[1];
    $value = $dataget[2];
    if ($type == "statusbot") {
        if ($value == "botstatuson") {
            $valuenew = "botstatusoff";
        } else {
            $valuenew = "botstatuson";
        }
        update("setting", "Bot_Status", $valuenew);
    } elseif ($type == "usernamebtn") {
        if ($value == "onnotuser") {
            $valuenew = "offnotuser";
        } else {
            $valuenew = "onnotuser";
        }
        update("setting", "NotUser", $valuenew);
    } elseif ($type == "notifnew") {
        if ($value == "onnewuser") {
            $valuenew = "offnewuser";
        } else {
            $valuenew = "onnewuser";
        }
        update("setting", "statusnewuser", $valuenew);
    } elseif ($type == "showagent") {
        if ($value == "onrequestagent") {
            $valuenew = "offrequestagent";
        } else {
            $valuenew = "onrequestagent";
        }
        update("setting", "statusagentrequest", $valuenew);
    } elseif ($type == "role") {
        if ($value == "rolleon") {
            $valuenew = "rolleoff";
        } else {
            $valuenew = "rolleon";
        }
        update("setting", "roll_Status", $valuenew);
    } elseif ($type == "Authenticationphone") {
        if ($value == "onAuthenticationphone") {
            $valuenew = "offAuthenticationphone";
        } else {
            $valuenew = "onAuthenticationphone";
        }
        update("setting", "get_number", $valuenew);
    } elseif ($type == "Authenticationiran") {
        if ($value == "onAuthenticationiran") {
            $valuenew = "offAuthenticationiran";
        } else {
            $valuenew = "onAuthenticationiran";
        }
        update("setting", "iran_number", $valuenew);
    } elseif ($type == "inlinebtnmain") {
        if ($value == "oninline") {
            $valuenew = "offinline";
        } else {
            $valuenew = "oninline";
        }
        update("setting", "inlinebtnmain", $valuenew);
    } elseif ($type == "verifystart") {
        if ($value == "onverify") {
            $valuenew = "offverify";
        } else {
            $valuenew = "onverify";
        }
        update("setting", "verifystart", $valuenew);
    } elseif ($type == "statussupportpv") {
        if ($value == "onpvsupport") {
            $valuenew = "offpvsupport";
        } else {
            $valuenew = "onpvsupport";
        }
        update("setting", "statussupportpv", $valuenew);
    } elseif ($type == "statusnamecustom") {
        if ($value == "onnamecustom") {
            $valuenew = "offnamecustom";
        } else {
            $valuenew = "onnamecustom";
        }
        update("setting", "statusnamecustom", $valuenew);
    } elseif ($type == "bulkbuy") {
        if ($value == "onbulk") {
            $valuenew = "offbulk";
        } else {
            $valuenew = "onbulk";
        }
        update("setting", "bulkbuy", $valuenew);
    } elseif ($type == "verifybyuser") {
        if ($value == "onverify") {
            $valuenew = "offverify";
        } else {
            $valuenew = "onverify";
        }
        update("setting", "verifybucodeuser", $valuenew);
    } elseif ($type == "wheelagent") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "wheelagent", $valuenew);
    } elseif ($type == "keyconfig") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "status_keyboard_config", $valuenew);
    } elseif ($type == "Lotteryagent") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "Lotteryagent", $valuenew);
    } elseif ($type == "compycart") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "statuscopycart", $valuenew);
    } elseif ($type == "score") {
        if ($value == "1") {
            if (isShellExecAvailable()) {
                $crontabBinary = getCrontabBinary();
                if ($crontabBinary === null) {
                    error_log('Unable to locate crontab executable; cannot remove lottery cron job.');
                } else {
                    $currentCronJobs = runShellCommand(sprintf('%s -l 2>/dev/null', escapeshellarg($crontabBinary)));
                    $jobToRemove = "*/1 * * * * curl https://$domainhosts/cronbot/lottery.php";
                    $newCronJobs = preg_replace('/' . preg_quote($jobToRemove, '/') . '/', '', (string) $currentCronJobs);
                    $tempCronFile = '/tmp/crontab.txt';
                    file_put_contents($tempCronFile, trim($newCronJobs) . PHP_EOL);
                    runShellCommand(sprintf('%s %s', escapeshellarg($crontabBinary), escapeshellarg($tempCronFile)));
                    if (file_exists($tempCronFile)) {
                        unlink($tempCronFile);
                    }
                }
            } else {
                error_log('Unable to remove lottery cron job because shell_exec is unavailable.');
            }
            $valuenew = "0";
        } else {
            $phpFilePath = "https://$domainhosts/cronbot/lottery.php";
            $cronCommand = "*/1 * * * * curl $phpFilePath";
            if (!addCronIfNotExists($cronCommand)) {
                error_log('Unable to register lottery cron job because shell_exec is unavailable.');
            }
            $valuenew = "1";
        }
        update("setting", "scorestatus", $valuenew);
    } elseif ($type == "wheel_luck") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "wheelـluck", $valuenew);
    } elseif ($type == "affiliatesstatus") {
        if ($value == "onaffiliates") {
            $valuenew = "offaffiliates";
        } else {
            $valuenew = "onaffiliates";
        }
        update("setting", "affiliatesstatus", $valuenew);
    } elseif ($type == "verifybyuser") {
        if ($value == "onverify") {
            $valuenew = "offverify";
        } else {
            $valuenew = "onverify";
        }
        update("setting", "verifybucodeuser", $valuenew);
    } elseif ($type == "btn_status_category") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "categoryhelp", $valuenew);
    } elseif ($type == "linkappstatus") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "linkappstatus", $valuenew);
    } elseif ($type == "btnstautslanguage") {
        if ($setting['languageru'] == "1") {
            sendmessage($from_id, "زبان روسیه ای روشن است و نمی توانید زبان انگلیسی را تغییر وضعیت دهید", null, 'HTML');
            return;
        }
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "languageen", $valuenew);
    } elseif ($type == "btnstautslanguageru") {
        if ($setting['languageen'] == "1") {
            sendmessage($from_id, "زبان انگلیسی روشن است و نمی توانید زبان روسیه ای را تغییر وضعیت دهید", null, 'HTML');
            return;
        }
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "languageru", $valuenew);
    } elseif ($type == "wheelagentfirst") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "statusfirstwheel", $valuenew);
    } elseif ($type == "changeloc") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "statuslimitchangeloc", $valuenew);
    } elseif ($type == "Debtsettlement") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "Debtsettlement", $valuenew);
    } elseif ($type == "Dice") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "Dice", $valuenew);
    } elseif ($type == "statusnamecustomf") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "statusnoteforf", $valuenew);
    } elseif ($type == "crontest") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron['test'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "cronday") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron['day'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "cronvolume") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron['volume'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "notifremove") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron['remove'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "notifremove_volume") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron['remove_volume'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "uptime_node") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron['uptime_node'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "uptime_panel") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron['uptime_panel'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    } elseif ($type == "on_hold") {
        if ($value == true) {
            $valueneww = false;
        } else {
            $valueneww = true;
        }
        $status_cron['on_hold'] = $valueneww;
        update("setting", "cron_status", json_encode($status_cron));
    }
    $setting = select("setting", "*");
    $status_cron = json_decode($setting['cron_status'], true);
    $name_status = [
        'botstatuson' => $textbotlang['Admin']['Status']['statuson'],
        'botstatusoff' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Bot_Status']];
    $name_status_username = [
        'onnotuser' => $textbotlang['Admin']['Status']['statuson'],
        'offnotuser' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['NotUser']];
    $name_status_notifnewuser = [
        'onnewuser' => $textbotlang['Admin']['Status']['statuson'],
        'offnewuser' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusnewuser']];
    $name_status_showagent = [
        'onrequestagent' => $textbotlang['Admin']['Status']['statuson'],
        'offrequestagent' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusagentrequest']];
    $name_status_role = [
        'rolleon' => $textbotlang['Admin']['Status']['statuson'],
        'rolleoff' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['roll_Status']];
    $Authenticationphone = [
        'onAuthenticationphone' => $textbotlang['Admin']['Status']['statuson'],
        'offAuthenticationphone' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['get_number']];
    $Authenticationiran = [
        'onAuthenticationiran' => $textbotlang['Admin']['Status']['statuson'],
        'offAuthenticationiran' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['iran_number']];
    $statusinline = [
        'oninline' => $textbotlang['Admin']['Status']['statuson'],
        'offinline' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['inlinebtnmain']];
    $statusverify = [
        'onverify' => $textbotlang['Admin']['Status']['statuson'],
        'offverify' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['verifystart']];
    $statuspvsupport = [
        'onpvsupport' => $textbotlang['Admin']['Status']['statuson'],
        'offpvsupport' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statussupportpv']];
    $statusnameconfig = [
        'onnamecustom' => $textbotlang['Admin']['Status']['statuson'],
        'offnamecustom' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusnamecustom']];
    $statusnamebulk = [
        'onbulk' => $textbotlang['Admin']['Status']['statuson'],
        'offbulk' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['bulkbuy']];
    $statusverifybyuser = [
        'onverify' => $textbotlang['Admin']['Status']['statuson'],
        'offverify' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['verifybucodeuser']];
    $score = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['scorestatus']];
    $wheel_luck = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['wheelـluck']];
    $refralstatus = [
        'onaffiliates' => $textbotlang['Admin']['Status']['statuson'],
        'offaffiliates' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['affiliatesstatus']];
    $btnstatuscategory = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['categoryhelp']];
    $btnstatuslinkapp = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['linkappstatus']];
    $cronteststatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['test']];
    $crondaystatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['day']];
    $cronvolumestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['volume']];
    $cronremovestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['remove']];
    $cronremovevolumestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['remove_volume']];
    $cronuptime_nodestatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['uptime_node']];
    $cronuptime_panelstatustext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['uptime_panel']];
    $cronon_holdtext = [
        true => $textbotlang['Admin']['Status']['statuson'],
        false => $textbotlang['Admin']['Status']['statusoff']
    ][$status_cron['on_hold']];
    $languagestatus = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['languageen']];
    $languagestatusru = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['languageru']];
    $wheelagent = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['wheelagent']];
    $Lotteryagent = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Lotteryagent']];
    $statusfirstwheel = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusfirstwheel']];
    $statuslimitchangeloc = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statuslimitchangeloc']];
    $statusDebtsettlement = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Debtsettlement']];
    $statusDice = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['Dice']];
    $statusnotef = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statusnoteforf']];
    $status_copy_cart = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['statuscopycart']];
    $keyboard_config_text = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['status_keyboard_config']];
    $Bot_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['Status']['subject'], 'callback_data' => "subject"],
                ['text' => $textbotlang['Admin']['Status']['statussubject'], 'callback_data' => "subjectde"],
            ],
            [
                ['text' => $name_status, 'callback_data' => "editstsuts-statusbot-{$setting['Bot_Status']}"],
                ['text' => $textbotlang['Admin']['Status']['stautsbot'], 'callback_data' => "statusbot"],
            ],
            [
                ['text' => $name_status_username, 'callback_data' => "editstsuts-usernamebtn-{$setting['NotUser']}"],
                ['text' => $textbotlang['Admin']['Status']['statususernamebtn'], 'callback_data' => "usernamebtn"],
            ],
            [
                ['text' => $name_status_notifnewuser, 'callback_data' => "editstsuts-notifnew-{$setting['statusnewuser']}"],
                ['text' => $textbotlang['Admin']['Status']['statusnotifnewuser'], 'callback_data' => "statusnewuser"],
            ],
            [
                ['text' => $name_status_showagent, 'callback_data' => "editstsuts-showagent-{$setting['statusagentrequest']}"],
                ['text' => $textbotlang['Admin']['Status']['statusshowagent'], 'callback_data' => "statusnewuser"],
            ],
            [
                ['text' => $name_status_role, 'callback_data' => "editstsuts-role-{$setting['roll_Status']}"],
                ['text' => $textbotlang['Admin']['Status']['stautsrolee'], 'callback_data' => "stautsrolee"],
            ],
            [
                ['text' => $Authenticationphone, 'callback_data' => "editstsuts-Authenticationphone-{$setting['get_number']}"],
                ['text' => $textbotlang['Admin']['Status']['Authenticationphone'], 'callback_data' => "Authenticationphone"],
            ],
            [
                ['text' => $Authenticationiran, 'callback_data' => "editstsuts-Authenticationiran-{$setting['iran_number']}"],
                ['text' => $textbotlang['Admin']['Status']['Authenticationiran'], 'callback_data' => "Authenticationiran"],
            ],
            [
                ['text' => $statusinline, 'callback_data' => "editstsuts-inlinebtnmain-{$setting['inlinebtnmain']}"],
                ['text' => $textbotlang['Admin']['Status']['inlinebtns'], 'callback_data' => "inlinebtnmain"],
            ],
            [
                ['text' => $statusverify, 'callback_data' => "editstsuts-verifystart-{$setting['verifystart']}"],
                ['text' => "🔒 احراز هویت", 'callback_data' => "verify"],
            ],
            [
                ['text' => $statuspvsupport, 'callback_data' => "editstsuts-statussupportpv-{$setting['statussupportpv']}"],
                ['text' => "👤 پشتیبانی در پیوی", 'callback_data' => "statussupportpv"],
            ],
            [
                ['text' => $statusnameconfig, 'callback_data' => "editstsuts-statusnamecustom-{$setting['statusnamecustom']}"],
                ['text' => "📨 یادداشت کانفیگ", 'callback_data' => "statusnamecustom"],
            ],
            [
                ['text' => $statusnotef, 'callback_data' => "editstsuts-statusnamecustomf-{$setting['statusnoteforf']}"],
                ['text' => "📨 یادداشت کاربر عادی", 'callback_data' => "statusnamecustomf"],
            ],
            [
                ['text' => $statusnamebulk, 'callback_data' => "editstsuts-bulkbuy-{$setting['bulkbuy']}"],
                ['text' => "🛍 وضعیت خرید عمده", 'callback_data' => "bulkbuy"],
            ],
            [
                ['text' => $statusverifybyuser, 'callback_data' => "editstsuts-verifybyuser-{$setting['verifybucodeuser']}"],
                ['text' => "🔑 احراز هویت با لینک", 'callback_data' => "verifybyuser"],
            ],
            [
                ['text' => $btnstatuscategory, 'callback_data' => "editstsuts-btn_status_category-{$setting['categoryhelp']}"],
                ['text' => "📗دسته بندی آموزش", 'callback_data' => "btn_status_category"],
            ],
            [
                ['text' => $wheelagent, 'callback_data' => "editstsuts-wheelagent-{$setting['wheelagent']}"],
                ['text' => "🎲 گردونه شانس  نمایندگان", 'callback_data' => "wheelagent"],
            ],
            [
                ['text' => $keyboard_config_text, 'callback_data' => "editstsuts-keyconfig-{$setting['status_keyboard_config']}"],
                ['text' => "🔗 کیبورد کانفیگی", 'callback_data' => "keyconfig"],
            ],
            [
                ['text' => $statusDice, 'callback_data' => "editstsuts-Dice-{$setting['Dice']}"],
                ['text' => "🎰 نمایش تاس", 'callback_data' => "Dice"],
            ],
            [
                ['text' => $statusfirstwheel, 'callback_data' => "editstsuts-wheelagentfirst-{$setting['statusfirstwheel']}"],
                ['text' => "🎲 گردونه شانس خرید اول", 'callback_data' => "wheelagentfirst"],
            ],
            [
                ['text' => $Lotteryagent, 'callback_data' => "editstsuts-Lotteryagent-{$setting['Lotteryagent']}"],
                ['text' => "🎁 قرعه کشی نمایندگان", 'callback_data' => "Lotteryagent"],
            ],
            [
                ['text' => $statusDebtsettlement, 'callback_data' => "editstsuts-Debtsettlement-{$setting['Debtsettlement']}"],
                ['text' => "💎 تسویه بدهی", 'callback_data' => "Debtsettlement"],
            ],
            [
                ['text' => $status_copy_cart, 'callback_data' => "editstsuts-compycart-{$setting['statuscopycart']}"],
                ['text' => "💳 کپی شماره کارت", 'callback_data' => "copycart"],
            ],
            [
                ['text' => $cronteststatustext, 'callback_data' => "editstsuts-crontest-{$status_cron['test']}"],
                ['text' => "🔓کرون تست", 'callback_data' => "none"],
            ],
            [
                ['text' => $cronuptime_nodestatustext, 'callback_data' => "editstsuts-uptime_node-{$status_cron['uptime_node']}"],
                ['text' => "🎛 آپتایم نود", 'callback_data' => "none"],
            ],
            [
                ['text' => $cronuptime_panelstatustext, 'callback_data' => "editstsuts-uptime_panel-{$status_cron['uptime_panel']}"],
                ['text' => "🎛 آپتایم پنل", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ زمان هشدار", 'callback_data' => "settimecornday"],
                ['text' => $crondaystatustext, 'callback_data' => "editstsuts-cronday-{$status_cron['day']}"],
                ['text' => "🕚 کرون زمان", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ زمان اولین اتصال", 'callback_data' => "setting_on_holdcron"],
                ['text' => $cronon_holdtext, 'callback_data' => "editstsuts-on_hold-{$status_cron['on_hold']}"],
                ['text' => "🕚 کرون اولین اتصال", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ حجم هشدار", 'callback_data' => "settimecornvolume"],
                ['text' => $cronvolumestatustext, 'callback_data' => "editstsuts-cronvolume-{$status_cron['volume']}"],
                ['text' => "🔋 کرون حجم", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ زمان حذف", 'callback_data' => "settimecornremove"],
                ['text' => $cronremovestatustext, 'callback_data' => "editstsuts-notifremove-{$status_cron['remove']}"],
                ['text' => "❌ کرون حذف", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ زمان حذف", 'callback_data' => "settimecornremovevolume"],
                ['text' => $cronremovevolumestatustext, 'callback_data' => "editstsuts-notifremove_volume-{$status_cron['remove_volume']}"],
                ['text' => "❌ کرون حذف حجم", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ مدیریت", 'callback_data' => "cronjobs_settings"],
                ['text' => "⏱ نمایش لیست", 'callback_data' => "cronjobs_settings"],
                ['text' => "زمان‌بندی کرون‌ها", 'callback_data' => "none"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "linkappsetting"],
                ['text' => $btnstatuslinkapp, 'callback_data' => "editstsuts-linkappstatus-{$setting['linkappstatus']}"],
                ['text' => "🔗لینک دانلود برنامه", 'callback_data' => "linkappstatus"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "scoresetting"],
                ['text' => $score, 'callback_data' => "editstsuts-score-{$setting['scorestatus']}"],
                ['text' => "🎁 قرعه کشی شبانه", 'callback_data' => "score"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "gradonhshans"],
                ['text' => $wheel_luck, 'callback_data' => "editstsuts-wheel_luck-{$setting['wheelـluck']}"],
                ['text' => "🎲 گردونه شانس", 'callback_data' => "wheel_luck"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "settingaffiliatesf"],
                ['text' => $refralstatus, 'callback_data' => "editstsuts-affiliatesstatus-{$setting['affiliatesstatus']}"],
                ['text' => "🎁زیرمجموعه", 'callback_data' => "affiliatesstatus"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "changeloclimit"],
                ['text' => $statuslimitchangeloc, 'callback_data' => "editstsuts-changeloc-{$setting['statuslimitchangeloc']}"],
                ['text' => "🌍 محدودیت تغییر لوکیشن", 'callback_data' => "changeloc"],
            ],
            [
                ['text' => "❌ بستن", 'callback_data' => 'close_stat']
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['BotTitle'], $Bot_Status);
} elseif ($text == "⚖️ متن قانون" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . $datatextbot['text_roll'], $backadmin, 'HTML');
    step('text_roll', $from_id);
} elseif ($user['step'] == "text_roll") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_roll");
    step('home', $from_id);
} elseif ($text == "📣 گزارشات ربات" && $adminrulecheck['rule'] == "administrator") {
    $textreports = "📣در این بخش میتوانید آیدی عددی گروه را برای ارسال اعلان ارسال نمایید
آموزش تنظیم گروه :
1 - ابتدا یک گروه  بسازید 
2 - ربات  @myidbot را عضو گروه کنید و دستور /getgroupid@myidbot داخل گروه ارسال کنید 
3 - حالت تاپیک یا انجمن گروه را از تنظیمات گروه روشن کنید
4 - ربات خودتان را ادمین گروه کنید 
5 - آیدی عددی ارسال شده را در ربات ارسال کنید.

آیدی عددی فعلی شما: {$setting['Channel_Report']}";
    sendmessage($from_id, $textreports, $backadmin, 'HTML');
    step('addchannelid', $from_id);

} elseif ($user['step'] == "addchannelid") {
    $outputcheck = sendmessage($text, $textbotlang['Admin']['Channel']['TestChannel'], null, 'HTML');
    if (empty($outputcheck['ok'])) {
        $errorDescription = 'نامشخص';
        if (is_array($outputcheck) && isset($outputcheck['description'])) {
            $errorDescription = $outputcheck['description'];
        } elseif (is_string($outputcheck) && $outputcheck !== '') {
            $errorDescription = $outputcheck;
        }
        $texterror = "❌ اتصال به گروه با موفقیت انجام نشد

خطای دریافتی :  {$errorDescription}";
        sendmessage($from_id, $texterror, null, 'HTML');
        return;
    }

    if ($outputcheck['result']['chat']['is_forum'] == false) {
        $texterror = "❌ گروه انتخاب شده درحالت انجمن نیست از تنظیمات گروه حالت تاپیک گروه را روشن کرده سپس آیدی عددی گروه را مجددا تنظیم نمایید";
        sendmessage($from_id, $texterror, null, 'HTML');
        return;
    }

    // --- جدید: ریست کردن تاپیک‌های پورسانت/شبانه/اطلاع‌رسانی/بکاپ برای گروه جدید ---
    $resetReports = ['porsantreport', 'reportnight', 'reportcron', 'backupfile'];
    foreach ($resetReports as $reportKey) {
        update("topicid", "idreport", 0, "report", $reportKey);
    }

    // --- ساخت تاپیک‌ها با فاصله‌ی ۵ ثانیه ---

    // 🛍 گزارش های خرید
    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name'   => "🛍 گزارش های خرید"
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ ربات ادمین گروه نیست";
        sendmessage($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($buyreport != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "buyreport");
    }
    sleep(5);

    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name'   => "📌 گزارش خرید خدمات"
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ ربات ادمین گروه نیست";
        sendmessage($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($otherservice != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "otherservice");
    }
    sleep(5);

    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name'   => "🔑 گزارش اکانت تست"
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ ربات ادمین گروه نیست";
        sendmessage($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($reporttest != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "reporttest");
    }
    sleep(5);

    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name'   => "⚙️ سایر گزارشات"
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ ربات ادمین گروه نیست";
        sendmessage($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($otherreport != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "otherreport");
    }
    sleep(5);

    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name'   => "❌ گزارش خطا ها"
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ ربات ادمین گروه نیست";
        sendmessage($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($errorreport != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "errorreport");
    }
    sleep(5);

    $createForumTopic = telegram('createForumTopic', [
        'chat_id' => $text,
        'name'   => "💰 گزارش مالی"
    ]);
    if (!$createForumTopic['ok']) {
        $texterror = "❌ ربات ادمین گروه نیست";
        sendmessage($from_id, $texterror, null, 'HTML');
        return;
    }
    if ($paymentreports != $createForumTopic['result']['message_thread_id']) {
        update("topicid", "idreport", $createForumTopic['result']['message_thread_id'], "report", "paymentreport");
    }


    sendmessage($from_id, $textbotlang['Admin']['Channel']['SetChannelReport'], $setting_panel, 'HTML');
    update("setting", "Channel_Report", $text);
    step('home', $from_id);

} elseif ($text == "🏬 تنظیمات فروشگاه" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $shopkeyboard, 'HTML');
} elseif ($text == "🛍 اضافه کردن محصول" && $adminrulecheck['rule'] == "administrator") {
    $locationproduct = select("marzban_panel", "*", null, null, "count");
    if ($locationproduct == 0) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpaneladmin'], null, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['Product']['AddProductStepOne'], $backadmin, 'HTML');
    step('get_limit', $from_id);
} elseif ($user['step'] == "get_limit") {
    if (strlen($text) > 150) {
        sendmessage($from_id, "❌ نام محصول باید کمتر از 150 کاراکتر باشد", $backadmin, 'HTML');
        return;
    }
    if (in_array($text, $name_product)) {
        sendmessage($from_id, "❌ محصول با نام $text وجود دارد", $backadmin, 'HTML');
        return;
    }
    savedata("clear", "name_product", $text);
    sendmessage($from_id, $textbotlang['Admin']['agent']['setagentproduct'], $backadmin, 'HTML');
    step('get_agent', $from_id);
} elseif ($user['step'] == "get_agent") {
    $agent = ["n", "f", "n2"];
    if (!in_array($text, $agent)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "agent", $text);
    sendmessage($from_id, $textbotlang['Admin']['Product']['Service_location'], $json_list_marzban_panel, 'HTML');
    step('get_location', $from_id);
} elseif ($user['step'] == "get_location") {
    $marzban_list[] = '/all';
    if (!in_array($text, $marzban_list)) {
        sendmessage($from_id, "❌ پنل انتخابی اشتباه است", null, 'HTML');
        return;
    }
    savedata("save", "Location", $text);
    if ($setting['statuscategorygenral'] == "oncategorys") {
        sendmessage($from_id, "📌 نام دسته بندی خود را ارسال نمایید.", KeyboardCategoryadmin(), 'HTML');
        step("getcategory", $from_id);
        return;
    }
    $panel = $text === '/all' ? null : select("marzban_panel", "*", "name_panel", $text, "select");
    if ($text !== '/all' && !is_array($panel)) {
        sendmessage($from_id, "❌ پنل انتخابی در دسترس نیست", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    if (is_array($panel) && ($panel['type'] ?? '') == "Manualsale") {
        savedata("save", "Service_time", "0");
        savedata("save", "Volume_constraint", "0");
        sendmessage($from_id, $textbotlang['Admin']['Product']['GetPrice'], $backadmin, 'HTML');
        step('gettimereset', $from_id);
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['Product']['GetLimit'], $backadmin, 'HTML');
    step('get_time', $from_id);
} elseif ($user['step'] == "getcategory") {
    $category = select("category", "*", "remark", $text, "count");
    if ($category == 0) {
        sendmessage($from_id, "❌ دسته بندی انتخاب شده وجود ندارد از بخش پلن ها > اضافه کردن دسته بندی دسته بندی خود را اضافه کنید سپس محصول را اضافه نمایید.", KeyboardCategoryadmin(), 'HTML');
        return;
    }
    savedata("save", "category", $text);
    $userdata = json_decode($user['Processing_value'], true);
    $panel = $userdata['Location'] === '/all' ? null : select("marzban_panel", "*", "name_panel", $userdata['Location'], "select");
    if ($userdata['Location'] !== '/all' && !is_array($panel)) {
        sendmessage($from_id, "❌ پنل انتخابی در دسترس نیست", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    if (is_array($panel) && ($panel['type'] ?? '') == "Manualsale") {
        savedata("save", "Service_time", "0");
        savedata("save", "Volume_constraint", "0");
        sendmessage($from_id, $textbotlang['Admin']['Product']['GetPrice'], $backadmin, 'HTML');
        step('gettimereset', $from_id);
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['Product']['GetLimit'], $backadmin, 'HTML');
    step('get_time', $from_id);
} elseif ($user['step'] == "get_time") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "Volume_constraint", $text);
    sendmessage($from_id, $textbotlang['Admin']['Product']['GettIime'], $backadmin, 'HTML');
    step('get_price', $from_id);
} elseif ($user['step'] == "get_price") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['InvalidTime'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "Service_time", $text);
    sendmessage($from_id, $textbotlang['Admin']['Product']['GetPrice'], $backadmin, 'HTML');
    step('gettimereset', $from_id);
} elseif ($user['step'] == "gettimereset") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['InvalidPrice'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "price_product", $text);
    $userdata = json_decode($user['Processing_value'], true);
    $panel = $userdata['Location'] === '/all' ? null : select("marzban_panel", "*", "name_panel", $userdata['Location'], "select");
    if ($userdata['Location'] !== '/all' && !is_array($panel)) {
        sendmessage($from_id, "❌ پنل انتخابی در دسترس نیست", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $panelType = is_array($panel) ? ($panel['type'] ?? '') : '';
    if ($panelType == "marzban" || $panelType == "marzneshin") {
        sendmessage($from_id, $textbotlang['Admin']['Product']['gettimereset'], $keyboardtimereset, 'HTML');
        step('getnote', $from_id);
        return;
    }
    savedata("save", "data_limit_reset", "no_reset");
    sendmessage($from_id, " 🗒 یادداشت را برای محصول ارسال کنید. این یادداشت در پیش فاکتور کاربر نشان داده می شود.", $backadmin, 'HTML');
    step('endstep', $from_id);
} elseif ($user['step'] == "getnote") {
    savedata("save", "data_limit_reset", $text);
    sendmessage($from_id, " 🗒 یادداشت را برای محصول ارسال کنید.این یادداشت در پیش فاکتور کاربر نشان داده می شود.", $backadmin, 'HTML');
    step('endstep', $from_id);
} elseif ($user['step'] == "endstep") {
    $userdata = json_decode($user['Processing_value'], true);
    $randomString = bin2hex(random_bytes(2));
    $varhide_panel = "{}";
    if (!isset($userdata['category']))
        $userdata['category'] = null;
    $stmt = $pdo->prepare("INSERT IGNORE INTO product (name_product,code_product,price_product,Volume_constraint,Service_time,Location,agent,data_limit_reset,note,category,hide_panel,one_buy_status) VALUES (:name_product,:code_product,:price_product,:Volume_constraint,:Service_time,:Location,:agent,:data_limit_reset,:note,:category,:hide_panel,'0')");
    $stmt->bindParam(':name_product', $userdata['name_product']);
    $stmt->bindParam(':code_product', $randomString);
    $stmt->bindParam(':price_product', $userdata['price_product']);
    $stmt->bindParam(':Volume_constraint', $userdata['Volume_constraint']);
    $stmt->bindParam(':Service_time', $userdata['Service_time']);
    $stmt->bindParam(':Location', $userdata['Location']);
    $stmt->bindParam(':agent', $userdata['agent']);
    $stmt->bindParam(':data_limit_reset', $userdata['data_limit_reset']);
    $stmt->bindParam(':category', $userdata['category'], PDO::PARAM_STR);
    $stmt->bindParam(':note', $text, PDO::PARAM_STR);
    $stmt->bindParam(':hide_panel', $varhide_panel, PDO::PARAM_STR);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['Product']['SaveProduct'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "👨‍🔧 بخش ادمین" && $adminrulecheck['rule'] == "administrator") {
    $list_admin = select("admin", "*", null, null, "fetchAll");
    $keyboardadmin = ['inline_keyboard' => []];
    foreach ($list_admin as $admin) {
        $adminId = isset($admin['id_admin']) ? trim($admin['id_admin']) : '';
        if ($adminId === '') {
            continue;
        }
        $keyboardadmin['inline_keyboard'][] = [
            ['text' => "❌", 'callback_data' => "removeadmin_" . $adminId],
            ['text' => $adminId, 'callback_data' => "adminlist"],
        ];
    }
    $keyboardadmin['inline_keyboard'][] = [
        ['text' => "👨‍💻 اضافه کردن ادمین", 'callback_data' => "addnewadmin"],
    ];
    $keyboardadmin = json_encode($keyboardadmin);
    sendmessage($from_id, "📌 در بخش زیر می توانید لیست ادمین ها را مشاهده کنید همچنین با زدن دکمه ضربدر می توانید یک ادمین را حذف کنید", $keyboardadmin, 'HTML');
} elseif ($text == "⚙️ تنظیمات عمومی" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $setting_panel, 'HTML');
} elseif ($text == "🤙 بخش پشتیبانی" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $supportcenter, 'HTML');
} elseif (preg_match('/Confirm_pay_(\w+)/', $datain, $dataget) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $order_id = $dataget[1];
    $Payment_report = select("Payment_report", "*", "id_order", $order_id, "select");
    $Confirm_pay = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ تایید شده", 'callback_data' => "confirmpaid"],
            ],
            [
                ['text' => "⚙️ مدیریت کاربر", 'callback_data' => "manageuser_" . $Payment_report['id_user']],
            ]
        ]
    ]);
    if ($Payment_report == false) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "تراکنش حذف شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $sql = "SELECT * FROM Payment_report WHERE id_user = '{$Payment_report['id_user']}' AND payment_Status != 'paid' AND payment_Status != 'Unpaid' AND payment_Status != 'expire' AND payment_Status != 'reject' AND  (id_invoice  LIKE CONCAT('%','getconfigafterpay', '%') OR id_invoice  LIKE CONCAT('%','getextenduser', '%') OR id_invoice  LIKE CONCAT('%','getextravolumeuser', '%') OR id_invoice  LIKE CONCAT('%','getextratimeuser', '%'))";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $countpay = $stmt->rowCount();
    $typepay = explode('|', $Payment_report['id_invoice']);
    if ($countpay > 0 and !in_array($typepay[0], ['getconfigafterpay', 'getextenduser', 'getextravolumeuser', 'getextratimeuser'])) {
        sendmessage($from_id, "⚠️ برای تأیید درخواست‌های کاربر، ابتدا رسیدهای خرید یا تمدید اشتراک را بررسی و تأیید کنید. سپس رسید شارژ کیف پول را تأیید کنید. ", null, 'HTML');
        return;
    }
    $format_price_cart = number_format($Payment_report['price']);
    $Balance_id = select("user", "*", "id", $Payment_report['id_user'], "select");
    if ($Payment_report['payment_Status'] == "paid" || $Payment_report['payment_Status'] == "reject") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        $textconfrom = "✅. پرداخت توسط ادمین دیگری تایید شده
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💎 موجودی بعد از تایید : {$Balance_id['Balance']}
💸 مبلغ پرداختی: $format_price_cart تومان
";
        Editmessagetext($from_id, $message_id, $textconfrom, $Confirm_pay);
        return;
    }
    DirectPayment($order_id);
    $pricecashback = select("PaySetting", "ValuePay", "NamePay", "chashbackcart", "select")['ValuePay'];
    $Balance_id = select("user", "*", "id", $Payment_report['id_user'], "select");
    if ($pricecashback != "0") {
        $result = ($Payment_report['price'] * $pricecashback) / 100;
        $Balance_confrim = intval($Balance_id['Balance']) + $result;
        update("user", "Balance", $Balance_confrim, "id", $Balance_id['id']);
        $pricecashback = number_format($pricecashback);
        $text_report = "🎁 کاربر عزیز مبلغ $result تومان به عنوان هدیه واریز به حساب شما واریز گردید.";
        sendmessage($Balance_id['id'], $text_report, null, 'HTML');
    }
    $Payment_report['price'] = number_format($Payment_report['price']);
    $text_report = "📣 یک ادمین رسید پرداخت  را تایید کرد.
        
اطلاعات :
💸 روش پرداخت : {$Payment_report['Payment_Method']}
👤آیدی عددی  ادمین تایید کننده : $from_id
💰 مبلغ پرداخت : {$Payment_report['price']}
👤 ایدی عددی کاربر : <code>{$Payment_report['id_user']}</code>
👤 نام کاربری کاربر : @{$Balance_id['username']} 
        کد پیگیری پرداحت : $order_id";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
    update("Payment_report", "payment_Status", "paid", "id_order", $Payment_report['id_order']);
    update("user", "Processing_value_one", "none", "id", $Balance_id['id']);
    update("user", "Processing_value_tow", "none", "id", $Balance_id['id']);
    update("user", "Processing_value_four", "none", "id", $Balance_id['id']);
} elseif (preg_match('/reject_pay_(\w+)/', $datain, $datagetr) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $id_order = $datagetr[1];
    $Payment_report = select("Payment_report", "*", "id_order", $id_order, "select");
    if ($Payment_report == false) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "تراکنش حذف شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    update("user", "Processing_value", $Payment_report['id_user'], "id", $from_id);
    update("user", "Processing_value_one", $id_order, "id", $from_id);
    if ($Payment_report['payment_Status'] == "reject" || $Payment_report['payment_Status'] == "paid") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    update("Payment_report", "payment_Status", "reject", "id_order", $id_order);

    sendmessage($from_id, $textbotlang['Admin']['Payment']['Reasonrejecting'], $backadmin, 'HTML');
    step('reject-dec', $from_id);
    Editmessagetext($from_id, $message_id, $text_inline, null);
} elseif ($user['step'] == "reject-dec") {
    $Payment_report = select("Payment_report", "*", "id_order", $user['Processing_value_one'], "select");
    update("Payment_report", "dec_not_confirmed", $text, "id_order", $user['Processing_value_one']);
    $text_reject = "❌ کاربر گرامی پرداخت شما به دلیل زیر رد گردید.
✍️ $text
🛒 کد پیگیری پرداخت: {$user['Processing_value_one']}
                ";
    sendmessage($from_id, $textbotlang['Admin']['Payment']['Rejected'], $keyboardadmin, 'HTML');
    sendmessage($user['Processing_value'], $text_reject, null, 'HTML');
    step('home', $from_id);
    $text_report = "❌ یک ادمین رسید پرداخت را رد کرد.
        
اطلاعات :
💸 روش پرداخت : {$Payment_report['Payment_Method']}
👤آیدی عددی  ادمین تایید کننده : $from_id
نام کاربری ادمین تایید کننده : @$username
💰 مبلغ پرداخت : {$Payment_report['price']}
دلیل رد کردن : $text
👤 ایدی عددی کاربر: {$Payment_report['id_user']}";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif ($text == "❌ حذف محصول" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['Product']['Rmove_location'], $json_list_marzban_panel, 'HTML');
    step('selectloc', $from_id);
} elseif ($user['step'] == "selectloc") {
    update("user", "Processing_value", $text, "id", $from_id);
    step('remove-product', $from_id);
    sendmessage($from_id, $textbotlang['Admin']['Product']['selectRemoveProduct'], $json_list_product_list_admin, 'HTML');
} elseif ($user['step'] == "remove-product") {
    if (!in_array($text, $name_product)) {
        sendmessage($from_id, $textbotlang['users']['sell']['error-product'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM product WHERE name_product =:name_product AND (Location= :Location or Location= '/all')");
    $stmt->bindParam(':name_product', $text, PDO::PARAM_STR);
    $stmt->bindParam(':Location', $user['Processing_value'], PDO::PARAM_STR);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['Product']['RemoveedProduct'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "✏️ ویرایش محصول" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['Product']['Rmove_location'], $list_marzban_panel_edit_product, 'HTML');
} elseif (preg_match('/locationedit_(\w+)/', $datain, $dataget)) {
    $location = $dataget[1];
    $location = $location == "all" ? "/all" : $location;
    update("user", "Processing_value_one", $location, "id", $from_id);
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "کاربر عادی", 'callback_data' => 'typeagenteditproduct_f'],
            ],
            [
                ['text' => "نماینده پیشرفته", 'callback_data' => 'typeagenteditproduct_n2'],
                ['text' => "نماینده عادی", 'callback_data' => 'typeagenteditproduct_n'],
            ],
            [
                ['text' => "بازگشت", 'callback_data' => "admin"]
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 نوع کاربری را انتخاب کنید", $Response);
} elseif (preg_match('/^typeagenteditproduct_(\w+)/', $datain, $dataget)) {
    $typeagent = $dataget[1];
    update("user", "Processing_value_tow", $typeagent, "id", $from_id);
    $product = [];
    $escapedText = mysqli_real_escape_string($connect, $user['Processing_value_one']);
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $getdataproduct = mysqli_query($connect, "SELECT * FROM product WHERE (Location = '{$panel['name_panel']}' or Location = '/all') AND agent = '$typeagent'");
    $list_product = [
        'inline_keyboard' => [],
    ];
    if (isset($getdataproduct)) {
        while ($row = mysqli_fetch_assoc($getdataproduct)) {
            $list_product['inline_keyboard'][] = [
                ['text' => $row['name_product'], 'callback_data' => "productedit_" . $row['id']]
            ];
        }
        $list_product['inline_keyboard'][] = [
            ['text' => "🏠 بازگشت به منوی قبل", 'callback_data' => "locationedit_" . $user['Processing_value_one']],
        ];

        $json_list_product_list_admin = json_encode($list_product);
    }
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Product']['selectEditProduct'], $json_list_product_list_admin);
} elseif (preg_match('/^productedit_(\w+)/', $datain, $dataget)) {
    $id_product = $dataget[1];
    deletemessage($from_id, $message_id);
    update("user", "Processing_value", $id_product, "id", $from_id);
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $info_product = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM product WHERE id = '$id_product'  AND agent = '{$user['Processing_value_tow']}' AND (Location = '{$panel['name_panel']}' OR Location = '/all') LIMIT 1"));
    $count_invoice = select("invoice", "*", "name_product", $info_product['name_product'], "count");
    $infoproduct = "
📌 اطلاعات محصول در حال ویرایش:
نام محصول :  {$info_product['name_product']}
قیمت محصول : {$info_product['price_product']}
حجم محصول : {$info_product['Volume_constraint']}
موقعیت محصول : {$info_product['Location']}
زمان محصول : {$info_product['Service_time']}
نوع کاربری محصول : {$info_product['agent']}
ریست دوره ای حجم محصول : {$info_product['data_limit_reset']}
یادداشت محصول : {$info_product['note']}
دسته بندی محصول : {$info_product['category']}
تعداد محصول فروخته شده : $count_invoice عدد
    ";
    sendmessage($from_id, $infoproduct, $change_product, 'HTML');
    step('home', $from_id);
} elseif ($text == "قیمت" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "قیمت جدید را ارسال کنید", $backadmin, 'HTML');
    step('change_price', $from_id);
} elseif ($user['step'] == "change_price") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['InvalidPrice'], $backadmin, 'HTML');
        return;
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET price_product = :price_product WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':price_product', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    sendmessage($from_id, "✅ قیمت محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "یادداشت" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "یادداشت جدید را ارسال کنید", $backadmin, 'HTML');
    step('change_note', $from_id);
} elseif ($user['step'] == "change_note") {
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET note = :notes WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':notes', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    sendmessage($from_id, "✅ یادداشت محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "دسته بندی" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "نام دسته بندی جدید را انتخاب کنید", KeyboardCategoryadmin(), 'HTML');
    step('change_categroy', $from_id);
} elseif ($user['step'] == "change_categroy") {
    $category = select("category", "*", "remark", $text, "count");
    if ($category == 0) {
        sendmessage($from_id, "❌ دسته بندی انتخاب شده وجود ندارد از بخش پلن ها > اضافه کردن دسته بندی ُ دسته بندی خود را اضافه کنید سپس محصول را اضافه نمایید.", KeyboardCategoryadmin(), 'HTML');
        return;
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET category = :categroy WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':categroy', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    sendmessage($from_id, "✅ دسته بندی محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "نام محصول" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "نام جدید را ارسال کنید", $backadmin, 'HTML');
    step('change_name', $from_id);
} elseif ($user['step'] == "change_name") {
    if (strlen($text) > 150) {
        sendmessage($from_id, "❌ نام محصول باید کمتر از 150 کاراکتر باشد", $backadmin, 'HTML');
        return;
    }
    if (in_array($text, $name_product)) {
        sendmessage($from_id, "❌ محصول با نام $text وجود دارد", $backadmin, 'HTML');
        return;
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET name_product = :name_products WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':name_products', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    sendmessage($from_id, "✅نام محصول بروزرسانی شد", $change_product, 'HTML');
    step('home', $from_id);
} elseif ($text == "نوع کاربری" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "نوع کاربری جدید را ارسال کنید :
نوع کاربری ها :f , n , n2", $backadmin, 'HTML');
    step('change_type_agent', $from_id);
} elseif ($user['step'] == "change_type_agent") {
    if (!in_array($text, ['f', 'n', 'n2'])) {
        sendmessage($from_id, "❌ گروه کاربری نامعتبر می باشد", null, 'HTML');
        return;
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET agent = :agents WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':agents', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    sendmessage($from_id, "✅نام محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "نوع ریست حجم" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "نوع ریست حجم را ارسال کنید", $keyboardtimereset, 'HTML');
    step('change_reset_data', $from_id);
} elseif ($user['step'] == "change_reset_data") {
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET data_limit_reset = :data_limit_reset WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':data_limit_reset', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    sendmessage($from_id, "✅نام محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "موقعیت محصول" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 موقعیت جدید محصول را انتخاب کنید", $json_list_marzban_panel, 'HTML');
    step('change_loc_data', $from_id);
} elseif ($user['step'] == "change_loc_data") {
    if ($text == "/all") {
        sendmessage($from_id, "❌ نمی توانید محصول تعریف شده را به نام موقعیت /all تغییر دهید.", $shopkeyboard, 'HTML');
        return;
    }
    $product = select("product", "*", "name_product", $user['Processing_value']);
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET Location = :Location2 WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':Location2', $text);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    $stmt = $pdo->prepare("UPDATE invoice SET Service_location = :Service_location WHERE name_product = :name_product AND Service_location = :Location ");
    $stmt->bindParam(':Service_location', $text);
    $stmt->bindParam(':name_product', $product['name_product']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->execute();
    sendmessage($from_id, "✅موقعیت محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "حجم" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "حجم جدید را ارسال کنید", $backadmin, 'HTML');
    step('change_val', $from_id);
} elseif ($user['step'] == "change_val") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backadmin, 'HTML');
        return;
    }
    $product = select("product", "*", "id", $user['Processing_value']);
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one']);
    $stmt = $pdo->prepare("UPDATE product SET Volume_constraint = :Volume_constraint WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':Volume_constraint', $text);
    $stmt->bindParam(':name_product', $product['id']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['Product']['volumeUpdated'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "زمان" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['Product']['NewTime'], $backadmin, 'HTML');
    step('change_time', $from_id);
} elseif ($user['step'] == "change_time") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['InvalidTime'], $backadmin, 'HTML');
        return;
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET Service_time = :Service_time WHERE id = :id_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':Service_time', $text);
    $stmt->bindParam(':id_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['Product']['TimeUpdated'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($datain == "balanceaddall") {
    sendmessage($from_id, $textbotlang['Admin']['Balance']['addallbalance'], $backadmin, 'HTML');
    step('add_Balance_all', $from_id);
} elseif ($user['step'] == "add_Balance_all") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    step("home", $from_id);
    savedata("clear", "price", $text);
    $keyboardagent = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "همه کاربران", 'callback_data' => 'typebalanceall_all'],
            ],
            [
                ['text' => "کاربران گروه f", 'callback_data' => 'typebalanceall_f'],
                ['text' => "کاربران گروه n", 'callback_data' => 'typebalanceall_nl'],
                ['text' => "کاربران گروه n2", 'callback_data' => 'typebalanceall_n2'],
            ],
            [
                ['text' => "بازگشت به منوی اصلی", 'callback_data' => 'backuser'],
            ]
        ]
    ]);
    sendmessage($from_id, "📌 شارژ برای کدام یک از گروه کاربری زیر واریز شود.", $keyboardagent, 'HTML');
} elseif (preg_match('/typebalanceall_(\w+)/', $datain, $dataget)) {
    $typeagent = $dataget[1];
    savedata("save", "agent", $typeagent);
    $keyboardtypeuser = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "همه کاربران", 'callback_data' => 'typecustomer_all'],
            ],
            [
                ['text' => "کاربرانی که خرید داشتند", 'callback_data' => 'typecustomer_customer'],
            ],
            [
                ['text' => "کاربرانی که خرید نداشتند", 'callback_data' => 'typecustomer_notcustomer'],
            ],
            [
                ['text' => "بازگشت به منوی اصلی", 'callback_data' => 'backuser'],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 چه کاربر شارژ همگانی ارسال شود", $keyboardtypeuser);
} elseif (preg_match('/typecustomer_(\w+)/', $datain, $dataget)) {
    $typecustomer = $dataget[1];
    savedata("save", "typecustomer", $typecustomer);
    sendmessage($from_id, "📌 برای کاربران پیام ارسال شارژ ارسال شود یا خیر؟ 
بله : 1 
خیر : 0", $backadmin, 'HTML');
    step("getmeesagestatus", $from_id);
} elseif ($user['step'] == "getmeesagestatus") {
    $userdata = json_decode($user['Processing_value'], true);
    sendmessage($from_id, $textbotlang['Admin']['Balance']['AddBalanceUsers'], $keyboardadmin, 'HTML');
    $query_where = "";
    if ($userdata['agent'] == "all") {
        if ($userdata['typecustomer'] == "all") {
            $query_where = "";
        } elseif ($userdata['typecustomer'] == "customer") {
            $query_where = "WHERE EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);";
        } elseif ($userdata['typecustomer'] == "notcustomer") {
            $query_where = "WHERE  NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);";
        }
    } else {
        if ($userdata['typecustomer'] == "all") {
            $query_where = null;
            ;
        } elseif ($userdata['typecustomer'] == "customer") {
            $query_where = " WHERE u.agent =  '{$userdata['agent']}' AND EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);";
        } elseif ($userdata['typecustomer'] == "notcustomer") {
            $query_where = " WHERE u.agent =  '{$userdata['agent']}' AND NOT EXISTS ( SELECT 1 FROM invoice i WHERE i.id_user = u.id);";
        }
    }
    $stmt = $pdo->prepare("SELECT u.id FROM user u " . $query_where);
    $stmt->execute();
    $Balance_user = $stmt->fetchAll();
    $stmt = $pdo->prepare("UPDATE user as u SET  Balance = Balance + {$userdata['price']} " . $query_where);
    $stmt->execute();
    step('home', $from_id);
    if ($text == "1") {
        $cancelmessage = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "لغو عملیات", 'callback_data' => 'cancel_sendmessage'],
                ],
            ]
        ]);
        $textgift = "🎁 کاربر  عزیز مبلغ {$userdata['price']} تومان از طرف مدیریت به عنوان هدیه به کیف پول شما واریز گردید.";
        $message_id = sendmessage($from_id, "✅ عملیات ارسال پیام آغاز گردید پس از پایان اطلاع رسانی خواهد شد.", $cancelmessage, "html");
        $data = json_encode(array(
            "id_admin" => $from_id,
            'type' => "sendmessage",
            "id_message" => $message_id['result']['message_id'],
            "message" => $textgift,
            "pingmessage" => "no",
            "btnmessage" => "start"
        ));
        file_put_contents("cronbot/users.json", json_encode($Balance_user));
        file_put_contents('cronbot/info', $data);
    }
} elseif ($text == "⬇️ کم کردن موجودی") {
    sendmessage($from_id, $textbotlang['Admin']['Balance']['NegativeBalance'], $backadmin, 'HTML');
    step('Negative_Balance', $from_id);
} elseif ($user['step'] == "Negative_Balance") {
    if (!in_array($text, $users_ids)) {
        sendmessage($from_id, $textbotlang['Admin']['not-user'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['Balance']['PriceBalancek'], $backadmin, 'HTML');
    update("user", "Processing_value", $text, "id", $from_id);
    step('get_price_Negative', $from_id);
} elseif ($user['step'] == "get_price_Negative") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    if (intval($text) >= 100000000) {
        sendmessage($from_id, "📌 حداکثر مقدار 100 میلیون ریال است.", $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['Balance']['NegativeBalanceUser'], $keyboardadmin, 'HTML');
    $Balance_usersa = select("user", "*", "id", $user['Processing_value'], "select");
    $Balance_Low_userkam = $Balance_usersa['Balance'] - $text;
    update("user", "Balance", $Balance_Low_userkam, "id", $user['Processing_value']);
    $balances1 = number_format($text, 0);
    $Balance_user_afters = number_format(select("user", "*", "id", $user['Processing_value'], "select")['Balance']);
    $textkam = "❌ کاربر عزیز مبلغ $balances1 تومان از  موجودی کیف پول تان کسر گردید.";
    sendmessage($user['Processing_value'], $textkam, null, 'HTML');
    step('home', $from_id);
    if (strlen($setting['Channel_Report']) > 0) {
        $textaddbalance = "📌 یک ادمین موجودی کاربر را کم کرده است :
        
🪪 اطلاعات ادمین کم کننده موجودی : 
نام کاربری :@$username
آیدی عددی : $from_id
👤 اطلاعات کاربر  :
آیدی عددی کاربر  : {$user['Processing_value']}
مبلغ موجودی : $text
موجودی کاربر پس از کم کردن : $Balance_user_afters";
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $textaddbalance,
            'parse_mode' => "HTML"
        ]);
    }
} elseif ($datain == "searchuser") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['GetIdUserunblock'], $backadmin, 'HTML');
    step('show_info', $from_id);
} elseif ($user['step'] == "show_info" || preg_match('/manageuser_(\w+)/', $datain, $dataget) || preg_match('/updateinfouser_(\w+)/', $datain, $dataget) || strpos($text, "/user ") !== false || strpos($text, "/id ") !== false) {
    if ($user['step'] == "show_info") {
        $id_user = $text;
    } elseif (explode(" ", $text)[0] == "/user") {
        $id_user = explode(" ", $text)[1];
    } elseif (explode(" ", $text)[0] == "/id") {
        $id_user = explode(" ", $text)[1];
    } else {
        $id_user = $dataget[1];
    }
    if (!in_array($id_user, $users_ids)) {
        sendmessage($from_id, $textbotlang['Admin']['not-user'], null, 'HTML');
        return;
    }
    $date = date("Y-m-d");
    $dayListSell = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = '$id_user'"));
    $balanceall = mysqli_fetch_assoc(mysqli_query($connect, "SELECT SUM(price) FROM Payment_report WHERE payment_Status = 'paid' AND id_user = '$id_user' AND Payment_Method != 'low balance by admin'"));
    $subbuyuser = mysqli_fetch_assoc(mysqli_query($connect, "SELECT SUM(price_product) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = '$id_user'"));
    $invoicecount = select("invoice", '*', "id_user", $id_user, "count");
    if ($invoicecount == 0) {
        $sumvolume['SUM(Volume)'] = 0;
    } else {
        $sumvolume = mysqli_fetch_assoc(mysqli_query($connect, "SELECT SUM(Volume) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = '$id_user' AND name_product != 'سرویس تست'"));
    }
    $user = select("user", "*", "id", $id_user, "select");
    $roll_Status = [
        '1' => $textbotlang['Admin']['ManageUser']['Acceptedphone'],
        '0' => $textbotlang['Admin']['ManageUser']['Failedphone'],
    ][$user['roll_Status']];
    if ($subbuyuser['SUM(price_product)'] == null)
        $subbuyuser['SUM(price_product)'] = 0;
    $keyboardmanage = [
        'inline_keyboard' => [
            [['text' => "♻️  بروزرسانی اطلاعات", 'callback_data' => "updateinfouser_" . $id_user],],
            [['text' => $textbotlang['Admin']['ManageUser']['addbalanceuser'], 'callback_data' => "addbalanceuser_" . $id_user], ['text' => $textbotlang['Admin']['ManageUser']['lowbalanceuser'], 'callback_data' => "lowbalanceuser_" . $id_user],],
            [['text' => $textbotlang['Admin']['ManageUser']['banuserlist'], 'callback_data' => "banuserlist_" . $id_user], ['text' => $textbotlang['Admin']['ManageUser']['unbanuserlist'], 'callback_data' => "unbanuserr_" . $id_user]],
            [['text' => $textbotlang['Admin']['ManageUser']['addagent'], 'callback_data' => "addagent_" . $id_user], ['text' => $textbotlang['Admin']['ManageUser']['removeagent'], 'callback_data' => "removeagent_" . $id_user]],
            [['text' => $textbotlang['Admin']['ManageUser']['confirmnumber'], 'callback_data' => "confirmnumber_" . $id_user]],
            [['text' => "🎁 درصد تخفیف", 'callback_data' => "Percentlow_" . $id_user], ['text' => "✍️ ارسال پیام به کاربر", 'callback_data' => "sendmessageuser_" . $id_user]],
            [['text' => $textbotlang['Admin']['ManageUser']['vieworderuser'], 'callback_data' => "vieworderuser_" . $id_user]],
            [['text' => "👥 زیرمجموعه های کاربر", 'callback_data' => "affiliates-" . $id_user]],
            [['text' => "🔄 خارج کردن از زیرمجموعه", 'callback_data' => "removeaffiliate-" . $id_user], ['text' => "🔄 حذف زیرمجموعه های کاربر", 'callback_data' => "removeaffiliateuser-" . $id_user]],
            [['text' => "💳 فعالسازی شماره کارت", 'callback_data' => "showcarduser-" . $id_user]],
            [['text' => "احراز هویت کاربر", 'callback_data' => "verify_" . $id_user], ['text' => "عدم احراز کاربر", 'callback_data' => "unverify-" . $id_user]],
            [['text' => "💳  غیرفعالسازی شماره کارت", 'callback_data' => "carduserhide-" . $id_user]],
            [['text' => "🛒 افزودن سفارش", 'callback_data' => "addordermanualـ" . $id_user], ['text' => "➕ محدودیت اکانت تست", 'callback_data' => "limitusertest_" . $id_user]],
            [['text' => $textbotlang['Admin']['ManageUser']['viewpaymentuser'], 'callback_data' => "viewpaymentuser_" . $id_user], ['text' => "انتقال حساب کاربری ", 'callback_data' => "transferaccount_" . $id_user]],
            [['text' => "💡 خاموش کردن اکانت", 'callback_data' => "disableconfig-" . $id_user], ['text' => "💡 روشن کردن اکانت", 'callback_data' => "activeconfig-" . $id_user]],
            [['text' => "📑 احراز عضویت کانال", 'callback_data' => "confirmchannel-" . $id_user], ['text' => "0️⃣ صفر کردن موجودی", 'callback_data' => "zerobalance-" . $id_user]],
            [['text' => "🕚 وضعیت ارسال پیام های کرون", 'callback_data' => "statuscronuser-" . $id_user]],
        ]
    ];
    if ($user['agent'] == "n2")
        $keyboardmanage['inline_keyboard'][] = [['text' => "سقف خرید  نماینده", 'callback_data' => "maxbuyagent_" . $id_user]];
    if ($user['agent'] != "f") {
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "🤖 فعالسازی ربات فروش", 'callback_data' => "createbot_" . $id_user],
            ['text' => "❌ حذف ربات فروش", 'callback_data' => "removebotsell_" . $id_user]
        ];
    }
    if ($user['agent'] != "f") {
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "🔋 قیمت پایه حجم", 'callback_data' => "setvolumesrc_" . $id_user],
            ['text' => "⏳ قیمت پایه زمان", 'callback_data' => "settimepricesrc_" . $id_user]
        ];
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "❌ مخفی کردن یک پنل برای نماینده", 'callback_data' => "hidepanel_" . $id_user],
        ];
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "🗑 نمایش پنل های مخفی شده", 'callback_data' => "removehide_" . $id_user],
        ];
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "⏱️ زمان انقضا نمایندگی", 'callback_data' => "expireset_" . $id_user],
        ];
    }
    if (intval($setting['statuslimitchangeloc']) == 1) {
        $keyboardmanage['inline_keyboard'][] = [
            ['text' => "محدودیت تغییر لوکیشن", 'callback_data' => "changeloclimitbyuser_" . $id_user]
        ];
    }
    $keyboardmanage['inline_keyboard'][] = [
        ['text' => "❌ بستن", 'callback_data' => 'close_stat']
    ];
    $keyboardmanage = json_encode($keyboardmanage, JSON_UNESCAPED_UNICODE);
    $user['Balance'] = number_format($user['Balance']);
    if ($user['register'] != "none") {
        if ($user['register'] == null)
            return;
        $userjoin = jdate('Y/m/d H:i:s', $user['register']);
    } else {
        $userjoin = "نامشخص";
    }
    $userverify = [
        '0' => "احراز نشده",
        '1' => "احراز شده"
    ][$user['verify']];
    $showcart = [
        '0' => "مخفی",
        '1' => "نمایش داده می شود"
    ][$user['cardpayment']];
    if ($user['last_message_time'] == null) {
        $lastmessage = "";
    } else {
        $lastmessage = jdate('Y/m/d H:i:s', $user['last_message_time']);
    }
    $datefirst = time() - 86400;
    $desired_date_time_start = time() - 3600;
    $month_date_time_start = time() - 2592000;
    $sql = "SELECT * FROM invoice WHERE time_sell > :requestedDate AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->execute();
    $listhours = $stmt->rowCount();
    $sql = "SELECT SUM(price_product) FROM invoice WHERE time_sell > :requestedDate AND (Status = 'active' OR Status = 'end_of_time'  OR Status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->execute();
    $suminvoicehours = $stmt->fetchColumn();
    if ($suminvoicehours == null) {
        $suminvoicehours = "0";
    }
    $sql = "SELECT * FROM invoice WHERE time_sell > :requestedDate AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $month_date_time_start);
    $stmt->execute();
    $listmonth = $stmt->rowCount();
    $sql = "SELECT SUM(price_product) FROM invoice WHERE time_sell > :requestedDate AND (Status = 'active' OR Status = 'end_of_time'  OR Status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $month_date_time_start);
    $stmt->execute();
    $suminvoicemonth = $stmt->fetchColumn();
    if ($suminvoicemonth == null) {
        $suminvoicemonth = "0";
    }
    if ($user['agent'] != "f" && $user['expire'] != null) {
        $text_expie_agent = "⭕️ تاریخ پایان نمایندگی : " . jdate('Y/m/d H:i:s', $user['expire']);
    } else {
        $text_expie_agent = "";
    }
    $textinfouser = "👀 اطلاعات کاربر:

🔗 اطلاعات کاربری کاربر

⭕️ وضعیت کاربر : {$user['User_Status']}
⭕️ نام کاربری کاربر : @{$user['username']}
⭕️ آیدی عددی کاربر :  <a href = \"tg://user?id=$id_user\">$id_user</a>
⭕️ کد معرف کاربر : {$user['codeInvitation']}
⭕️ زمان عضویت کاربر : $userjoin
⭕️ آخرین زمان  استفاده کاربر از ربات : $lastmessage
⭕️ محدودیت اکانت تست :  {$user['limit_usertest']} 
⭕️ وضعیت تایید قانون : $roll_Status
⭕️ شماره موبایل : <code>{$user['number']}</code>
⭕️ نوع کاربری : {$user['agent']}
⭕️ تعداد زیرمجموعه کاربر : {$user['affiliatescount']}
⭕  معرف کاربر : {$user['affiliates']}
⭕  وضعیت احراز هویت: $userverify   
⭕  نمایش شماره کارت :‌$showcart
⭕ امتیاز کاربر : {$user['score']}
⭕️  مجموع حجم خریداری شده فعال ( برای آمار دقیق حجم باید کرون روشن باشد): {$sumvolume['SUM(Volume)']}
$text_expie_agent

💎 گزارشات مالی

🔰 موجودی کاربر : {$user['Balance']}
🔰 تعداد خرید کل کاربر : {$dayListSell['COUNT(*)']}
🔰️ مبلغ کل پرداختی  :  {$balanceall['SUM(price)']}
🔰 جمع کل خرید : {$subbuyuser['SUM(price_product)']}
🔰 درصد تخفیف کاربر : {$user['pricediscount']}
🔰 تعداد فروش یک ساعت گذشته : $listhours عدد
🔰 مجموع فروش یک ساعت گذشته : $suminvoicehours تومان
🔰 تعداد فروش یک ماه گذشته : $listmonth عدد
🔰 مجموع فروش یک ماه گذشته : $suminvoicemonth تومان

";
    if (is_string($datain) && isset($datain[0]) && $datain[0] == "u") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "اطلاعات بروزرسانی گردید",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        Editmessagetext($from_id, $message_id, $textinfouser, $keyboardmanage);
    } else {
        sendmessage($from_id, $textinfouser, $keyboardmanage, 'HTML');
        sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboardadmin, 'HTML');
    }
    step('home', $from_id);
} elseif ($text == "🎁 ساخت کد هدیه" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['Discount']['GetCode'], $backadmin, 'HTML');
    step('get_code', $from_id);
} elseif ($user['step'] == "get_code") {
    if (!preg_match('/^[A-Za-z\d]+$/', $text)) {
        sendmessage($from_id, $textbotlang['Admin']['Discount']['ErrorCode'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("INSERT INTO Discount (code, limitused) VALUES (:code, :limitused)");
    $value = "0";
    $stmt->bindParam(':code', $text, PDO::PARAM_STR);
    $stmt->bindParam(':limitused', $value, PDO::PARAM_STR);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['Discount']['PriceCode'], null, 'HTML');
    step('get_price_code', $from_id);
    update("user", "Processing_value", $text, "id", $from_id);
} elseif ($user['step'] == "get_price_code") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['Discount']['setlimituse'], $backadmin, 'HTML');
    update("Discount", "price", $text, "code", $user['Processing_value']);
    step('getlimitcodedis', $from_id);
} elseif ($user['step'] == "getlimitcodedis") {
    step("home", $from_id);
    update("Discount", "limituse", $text, "code", $user['Processing_value']);
    sendmessage($from_id, $textbotlang['Admin']['Discount']['SaveCode'], $keyboardadmin, 'HTML');
} elseif ($text == "❌ حذف کد هدیه" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['Discount']['RemoveCode'], $json_list_Discount_list_admin, 'HTML');
    step('remove-Discount', $from_id);
} elseif ($user['step'] == "remove-Discount") {
    if (!in_array($text, $code_Discount)) {
        sendmessage($from_id, $textbotlang['Admin']['Discount']['NotCode'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM Discount WHERE code = :code");
    $stmt->bindParam(':code', $text, PDO::PARAM_STR);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['Discount']['RemovedCode'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "🗑 حذف پروتکل" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['Protocol']['RemoveProtocol'], $keyboardprotocollist, 'HTML');
    step('removeprotocol', $from_id);
} elseif ($user['step'] == "removeprotocol") {
    if (!in_array($text, $protocoldata)) {
        sendmessage($from_id, $textbotlang['Admin']['Protocol']['invalidProtocol'], null, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['Protocol']['RemovedProtocol'], $optionMarzban, 'HTML');
    $stmt = $pdo->prepare("DELETE FROM protocol WHERE NameProtocol = :protocol");
    $stmt->bindParam(':protocol', $text, PDO::PARAM_STR);
    $stmt->execute();
    step('home', $from_id);
} elseif ($text == "💡 روش ساخت نام کاربری" && $adminrulecheck['rule'] == "administrator") {
    $text_username = "⭕️ روش ساخت نام کاربری برای اکانت ها را از دکمه زیر انتخاب نمایید.
        
⚠️ در صورتی که کاربری نام کاربری نداشته باشه کلمه انتخابی توسط شما ثبت خواهد شد جای نام کاربری اعمال خواهد شد.
        
⚠️ در صورتی که نام کاربری وجود داشته باشه یک عدد رندوم به نام کاربری اضافه خواهد شد";
    sendmessage($from_id, $text_username, $MethodUsername, 'HTML');
    step('updatemethodusername', $from_id);
} elseif ($user['step'] == "updatemethodusername") {
    update("marzban_panel", "MethodUsername", $text, "name_panel", $user['Processing_value']);
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($text == "متن دلخواه + عدد رندوم" || $text == "متن دلخواه + عدد ترتیبی" || $text == "متن دلخواه نماینده + عدد ترتیبی") {
        step('getnamecustom', $from_id);
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['customnamesend'], $backadmin, 'HTML');
        return;
    }
    if ($text == "نام کاربری + عدد به ترتیب") {
        step('getnamecustom', $from_id);
        sendmessage($from_id, "📌 در صورتی که کاربر نام کاربری نداشت چه اسمی ثبت شود؟", $backadmin, 'HTML');
        return;
    }
    outtypepanel($typepanel['type'], $textbotlang['Admin']['AlgortimeUsername']['SaveData']);
    step('home', $from_id);
} elseif ($user['step'] == "getnamecustom") {
    if (!preg_match('/^\w{3,32}$/', $text)) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['invalidname'], $backadmin, 'html');
        return;
    }
    update("marzban_panel", "namecustom", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['savedname']);
} elseif (($datain == "cartsetting" && $adminrulecheck['rule'] == "administrator") || $text == "▶️ بازگشت به منوی تظنیمات کارت") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $CartManage, 'HTML');
} elseif ($text == "💳 تنظیم شماره کارت" && $adminrulecheck['rule'] == "administrator") {
    $textcart = "💳 شماره کارت خود را ارسال کنید

⚠️ توجه داشته باشید شما می توانید چندین شماره کارت تعریف کنید در صورت تعریف چندین شماره کارت به کاربر یک شماره کارت از بین شماره کارت ها رندوم نشان خواهد داد";
    sendmessage($from_id, $textcart, $backadmin, 'HTML');
    step('changecard', $from_id);
} elseif ($user['step'] == "changecard") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, "❌شماره کارت باید حتما عدد باشد.", $backuser, 'HTML');
        return;
    }
    if (in_array($text, $listcard)) {
        sendmessage($from_id, "❌ شماره کارت در دیتابیس وجود دارد.", $backuser, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['SettingPayment']['getnamecard'], $backuser, 'HTML');
    update("user", "Processing_value", $text, "id", $from_id);
    step('getnamecard', $from_id);
} elseif ($user['step'] == "getnamecard") {
    try {
        if (function_exists('ensureCardNumberTableSupportsUnicode')) {
            ensureCardNumberTableSupportsUnicode();
        }

        $stmt = $connect->prepare("INSERT INTO card_number (cardnumber,namecard) VALUES (?,?)");
        $stmt->bind_param("ss", $user['Processing_value'], $text);
        $stmt->execute();
        $stmt->close();
        sendmessage($from_id, $textbotlang['Admin']['SettingPayment']['Savacard'], $CartManage, 'HTML');
        step('home', $from_id);
    } catch (\mysqli_sql_exception $e) {
        error_log('Failed to save card number: ' . $e->getMessage());
        if (stripos($e->getMessage(), 'Incorrect string value') !== false) {
            error_log('card_number insert failed due to charset mismatch. Please verify the table collation.');
        }
        sendmessage($from_id, "❌ ثبت شماره کارت ناموفق بود. لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.", $backadmin, 'HTML');
        step('home', $from_id);
    }
} elseif ($datain == "plisiosetting" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $NowPaymentsManage, 'HTML');
} elseif ($text == "🧩 api plisio" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "apinowpayment")['ValuePay'];
    $textcart = "⚙️ api سایت plisio.net.io را ارسال نمایید
        
        api plisio :$PaySetting";
    sendmessage($from_id, $textcart, $backadmin, 'HTML');
    step('apinowpayment', $from_id);
} elseif ($user['step'] == "apinowpayment") {
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $NowPaymentsManage, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "apinowpayment");
    step('home', $from_id);
} elseif ($datain == "iranpay1setting" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $Swapinokey, 'HTML');
} elseif ($text == "API NOWPAYMENT") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "marchent_tronseller")['ValuePay'];
    $texttronseller = "💳 API NOWPAMENT خود را دریافت و در این قسمت وارد کنید
        
 api فعلی شما : $PaySetting";
    sendmessage($from_id, $texttronseller, $backadmin, 'HTML');
    step('marchent_tronseller', $from_id);
} elseif ($user['step'] == "marchent_tronseller") {
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $keyboardadmin, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "marchent_tronseller");
    step('home', $from_id);
} elseif ($datain == "zarinpeysetting" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 یک گزینه را انتخاب کنید", $keyboardzarinpey, 'HTML');
} elseif ($datain == "aqayepardakhtsetting" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $aqayepardakht, 'HTML');
} elseif ($datain == "zarinpalsetting" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 یک گزینه را انتخاب کنید", $keyboardzarinpal, 'HTML');
} elseif ($text == "تنظیم مرچنت آقای پرداخت" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "merchant_id_aqayepardakht")['ValuePay'];
    $textaqayepardakht = "💳 مرچنت کد خود را ازآقای پرداخت دریافت و در این قسمت وارد کنید
        
مرچنت کد فعلی شما : $PaySetting";
    sendmessage($from_id, $textaqayepardakht, $backadmin, 'HTML');
    step('merchant_id_aqayepardakht', $from_id);
} elseif ($user['step'] == "merchant_id_aqayepardakht") {
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $aqayepardakht, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "merchant_id_aqayepardakht");
    step('home', $from_id);
} elseif ($text == "مرچنت زرین پال" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "merchant_zarinpal")['ValuePay'];
    $textaqayepardakht = "💳 مرچنت کد خود را از زرین پال دریافت و در این قسمت وارد کنید
        
مرچنت کد فعلی شما : $PaySetting";
    sendmessage($from_id, $textaqayepardakht, $backadmin, 'HTML');
    step('merchant_zarinpal', $from_id);
} elseif ($user['step'] == "merchant_zarinpal") {
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $keyboardzarinpal, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "merchant_zarinpal");
    step('home', $from_id);
} elseif ($text == "🗂 نام درگاه زرین پی") {
    sendmessage($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("gettextzarinpey", $from_id);
} elseif ($user['step'] == "gettextzarinpey") {
    sendmessage($from_id, "✅  متن با موفقیت تنظیم گردید.", $keyboardzarinpey, 'HTML');
    update("textbot", "text", $text, "id_text", "zarinpey");
    step("home", $from_id);
} elseif ($text == "🔑 توکن زرین پی" && $adminrulecheck['rule'] == "administrator") {
    $token = getPaySettingValue('token_zarinpey', '0');
    $message = "🔑 توکن دسترسی زرین پی خود را ارسال کنید.\n\nتوکن فعلی شما: {$token}";
    sendmessage($from_id, $message, $backadmin, 'HTML');
    step('token_zarinpey', $from_id);
} elseif ($user['step'] == "token_zarinpey") {
    update("PaySetting", "ValuePay", $text, "NamePay", "token_zarinpey");
    sendmessage($from_id, "✅ توکن با موفقیت ذخیره شد.", $keyboardzarinpey, 'HTML');
    step('home', $from_id);
} elseif ($text == "💰 کش بک زرین پی") {
    sendmessage($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashzarinpey", $from_id);
} elseif ($user['step'] == "getcashzarinpey") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackzarinpey");
    sendmessage($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $keyboardzarinpey, 'HTML');
    step('home', $from_id);
} elseif ($text == "🧑🏼‍💻 اموزش اتصال") {
    $inlineKeyboard = json_encode([
        'inline_keyboard' => [
            [
                [
                    'text' => '📞 دریافت API  مشاوره',
                    'url' => 'https://t.me/MiladRajabi2002',
                ],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $message = "🚀 درگاه کارت‌به‌کارت خودکار\n\nدرگاه هوشمند ZarinPay اکنون در میرزا بات نسخه پرو فعال است!\nتراکنش‌ها با خواندن پیامک بانکی به‌صورت خودکار و لحظه‌ای تأیید می‌شوند ⚡\nبدون نیاز به تأیید دستی، سریع، دقیق و ایمن 💳";

    sendmessage($from_id, $message, $inlineKeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "⬇️ حداقل مبلغ زرین پی") {
    sendmessage($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainzarinpey", $from_id);
} elseif ($user['step'] == "getmainzarinpey") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancezarinpey");
    sendmessage($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $keyboardzarinpey, 'HTML');
    step('home', $from_id);
} elseif ($text == "⬆️ حداکثر مبلغ زرین پی") {
    sendmessage($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaaxzarinpey", $from_id);
} elseif ($user['step'] == "getmaaxzarinpey") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancezarinpey");
    sendmessage($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $keyboardzarinpey, 'HTML');
    step('home', $from_id);
} elseif ($text == "📚 تنظیم آموزش زرین پی" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌آموزش خود را ارسال نمایید .\n۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید\n۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("helpzarinpey", $from_id);
} elseif ($user['step'] == "helpzarinpey") {
    if ($text) {
        if ((int) $text === 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpzarinpey");
        } else {
            $data = json_encode([
                'type' => 'text',
                'text' => $text,
            ], JSON_UNESCAPED_UNICODE);
            update("PaySetting", "ValuePay", $data, "NamePay", "helpzarinpey");
        }
    } elseif ($photo) {
        $data = json_encode([
            'type' => 'photo',
            'text' => $caption,
            'photoid' => $photoid,
        ], JSON_UNESCAPED_UNICODE);
        update("PaySetting", "ValuePay", $data, "NamePay", "helpzarinpey");
    } elseif ($video) {
        $data = json_encode([
            'type' => 'video',
            'text' => $caption,
            'videoid' => $videoid,
        ], JSON_UNESCAPED_UNICODE);
        update("PaySetting", "ValuePay", $data, "NamePay", "helpzarinpey");
    } else {
        sendmessage($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $keyboardzarinpey, 'HTML');
    step('home', $from_id);
} elseif ($text == $textbotlang['Admin']['btnkeyboardadmin']['managementpanel'] && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['getloc'], $json_list_marzban_panel, 'HTML');
    step('GetLocationEdit', $from_id);
} elseif ($user['step'] == "GetLocationEdit") {
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $text, "select");
    if (!is_array($marzban_list_get) || empty($marzban_list_get)) {
        $notFoundMessage = $textbotlang['Admin']['managepanel']['nullpanel'] ?? "❌ پنل مورد نظر یافت نشد.";
        sendmessage($from_id, $notFoundMessage, $json_list_marzban_panel, 'HTML');
        return;
    }
    if ($marzban_list_get['type'] == "marzban") {
        $Check_token = token_panel($marzban_list_get['code_panel'], false);
        if (isset($Check_token['access_token'])) {
            $System_Stats = Get_System_Stats($text);
            if ($new_marzban) {
                $active_users = $System_Stats['active_users']
                    ?? $System_Stats['users_active']
                    ?? $System_Stats['online_users']
                    ?? 0;
            } else {
                $active_users = $System_Stats['users_active']
                    ?? $System_Stats['active_users']
                    ?? $System_Stats['online_users']
                    ?? 0;
            }
            $total_user = $System_Stats['total_user'];
            $mem_total = formatBytes($System_Stats['mem_total']);
            $mem_used = formatBytes($System_Stats['mem_used']);
            $bandwidth = formatBytes($System_Stats['outgoing_bandwidth'] + $System_Stats['incoming_bandwidth']);
            $ListSell = number_format(mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND Service_location = '{$marzban_list_get['name_panel']}' AND name_product != 'سرویس تست'"))['COUNT(*)']);
            $ListSellSUM = number_format(mysqli_fetch_assoc(mysqli_query($connect, "SELECT SUM(price_product) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND Service_location = '{$marzban_list_get['name_panel']}' AND name_product != 'سرویس تست'"))['SUM(price_product)']);

            $Condition_marzban = "";
            $text_marzban = "
آمار پنل شما👇:
                             
🖥 وضعیت اتصال پنل مرزبان: ✅ پنل متصل است
👥  تعداد کل کاربران: $total_user
👤 تعداد کاربران فعال: $active_users
📡 نسخه پنل مرزبان :  {$System_Stats['version']}
💻 رم  کل سرور  : $mem_total
💻 مصرف رم پنل مرزبان  : $mem_used
🌐 ترافیک کل مصرف شده  ( آپلود / دانلود) : $bandwidth
🛍 تعداد فروش کل در این پنل : $ListSell
🛍 جمع فروش کل در این پنل : $ListSellSUM تومان
گروه کاربری :{$marzban_list_get['agent']}
        
⭕️ برای مدیریت پنل یکی از گزینه های زیر را انتخاب کنید";
            sendmessage($from_id, $text_marzban, $optionMarzban, 'HTML');
        } elseif (isset($Check_token['detail']) && $Check_token['detail'] == "Incorrect username or password") {
            $text_marzban = "❌ نام کاربری یا رمز عبور پنل اشتباه است";
            sendmessage($from_id, $text_marzban, $optionMarzban, 'HTML');
        } else {
            $errorDetails = json_encode($Check_token, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $text_marzban = $textbotlang['Admin']['managepanel']['errorstateuspanel'];
            if (!empty($errorDetails) && $errorDetails !== 'null') {
                $text_marzban .= PHP_EOL . "علت خطا: {$errorDetails}";
            }
            sendmessage($from_id, $text_marzban, $optionMarzban, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "x-ui_single") {
        $x_ui_check_connect = login($marzban_list_get['code_panel'], false);
        if ($x_ui_check_connect['success']) {
            sendmessage($from_id, $textbotlang['Admin']['managepanel']['connectx-ui'], $optionX_ui_single, 'HTML');
        } elseif (!empty($x_ui_check_connect['msg']) && $x_ui_check_connect['msg'] == "Invalid username or password.") {
            $text_marzban = "❌ نام کاربری یا رمز عبور پنل اشتباه است";
            sendmessage($from_id, $text_marzban, $optionX_ui_single, 'HTML');
        } else {
            $text_marzban = $textbotlang['Admin']['managepanel']['errorstateuspanel'];
            if (!empty($x_ui_check_connect['errror'])) {
                $text_marzban .= PHP_EOL . "علت خطا: {$x_ui_check_connect['errror']}";
            }
            sendmessage($from_id, $text_marzban, $optionX_ui_single, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "alireza_single") {
        $x_ui_check_connect = login($marzban_list_get['code_panel'], false);
        if ($x_ui_check_connect['success']) {
            sendmessage($from_id, $textbotlang['Admin']['managepanel']['connectx-ui'], $optionalireza_single, 'HTML');
        } elseif (!empty($x_ui_check_connect['msg']) && $x_ui_check_connect['msg'] == "The username or password is incorrect") {
            $text_marzban = "❌ نام کاربری یا رمز عبور پنل اشتباه است";
            sendmessage($from_id, $text_marzban, $optionalireza_single, 'HTML');
        } else {
            $text_marzban = $textbotlang['Admin']['managepanel']['errorstateuspanel'];
            if (!empty($x_ui_check_connect['errror'])) {
                $text_marzban .= PHP_EOL . "علت خطا: {$x_ui_check_connect['errror']}";
            }
            sendmessage($from_id, $text_marzban, $optionalireza_single, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "hiddify") {
        $System_Stats = serverstatus($marzban_list_get['name_panel']);
        if (!empty($System_Stats['status']) && $System_Stats['status'] != 200) {
            $text_marzban = "❌ خطایی در دریافت اطلاعات رخ داده است کد خطا : " . $System_Stats['status'];
            sendmessage($from_id, $text_marzban, $optionhiddfy, 'HTML');
        } elseif (!empty($System_Stats['error'])) {
            $text_marzban = "❌ خطایی در دریافت اطلاعات رخ داده است  خطا : " . $System_Stats['error'];
            sendmessage($from_id, $text_marzban, $optionhiddfy, 'HTML');
        } else {
            $System_Stats = json_decode($System_Stats['body'], true);
            if (isset($System_Stats['stats'])) {
                $mem_total = round($System_Stats['stats']['system']['ram_total'], 2);
                $mem_used = round($System_Stats['stats']['system']['ram_used'], 2);
                $outgoingBandwidth = 0;
                $incomingBandwidth = 0;

                if (isset($System_Stats['outgoing_bandwidth']) || isset($System_Stats['incoming_bandwidth'])) {
                    $outgoingBandwidth = (float) ($System_Stats['outgoing_bandwidth'] ?? 0);
                    $incomingBandwidth = (float) ($System_Stats['incoming_bandwidth'] ?? 0);
                } elseif (isset($System_Stats['stats']['outgoing_bandwidth']) || isset($System_Stats['stats']['incoming_bandwidth'])) {
                    $outgoingBandwidth = (float) ($System_Stats['stats']['outgoing_bandwidth'] ?? 0);
                    $incomingBandwidth = (float) ($System_Stats['stats']['incoming_bandwidth'] ?? 0);
                }

                $bandwidth = formatBytes($outgoingBandwidth + $incomingBandwidth);
                $text_marzban = "
آمار پنل شما👇:
                             
🖥 وضعیت اتصال پنل : ✅ پنل متصل است
💻 رم  کل سرور  : $mem_total
💻 مصرف رم پنل   : $mem_used
گروه کاربری :{$marzban_list_get['agent']}
⭕️ برای مدیریت پنل یکی از گزینه های زیر را انتخاب کنید";
                sendmessage($from_id, $text_marzban, $optionhiddfy, 'HTML');
            } elseif (isset($System_Stats['message']) && $System_Stats['message'] == "Unathorized") {
                $text_marzban = "❌  لینک پنل اشتباه ارسال شده است";
                sendmessage($from_id, $text_marzban, $optionhiddfy, 'HTML');
            } else {
                sendmessage($from_id, "پنل متصل نیست", $optionhiddfy, 'HTML');
            }
        }
    } elseif ($marzban_list_get['type'] == "Manualsale") {
        sendmessage($from_id, "یک گزینه را انتخاب نمایید", $optionManualsale, 'HTML');
    } elseif ($marzban_list_get['type'] == "marzneshin") {
        $Check_token = token_panelm($marzban_list_get['code_panel']);
        if (isset($Check_token['access_token'])) {
            $System_Stats = Get_System_Statsm($text);
            if (!empty($System_Stats['status']) && $System_Stats['status'] != 200) {
                $text_marzban = "❌ خطایی در دریافت اطلاعات رخ داده است کد خطا : " . $System_Stats['status'];
                sendmessage($from_id, $text_marzban, $optionMarzban, 'HTML');
                return;
            } elseif (!empty($System_Stats['error'])) {
                $text_marzban = "❌ خطایی در دریافت اطلاعات رخ داده است  خطا : " . $System_Stats['error'];
                sendmessage($from_id, $text_marzban, $optionMarzban, 'HTML');
                return;
            }
            $System_Stats = json_decode($System_Stats['body'], true);
            $active_users = $System_Stats['active'];
            $total_user = $System_Stats['total'];
            $ListSell = number_format(mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND Service_location = '{$marzban_list_get['name_panel']}' AND name_product != 'سرویس تست'"))['COUNT(*)']);
            $ListSellSUM = number_format(mysqli_fetch_assoc(mysqli_query($connect, "SELECT SUM(price_product) FROM invoice WHERE (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND Service_location = '{$marzban_list_get['name_panel']}' AND name_product != 'سرویس تست'"))['SUM(price_product)']);
            $Condition_marzban = "";
            $text_marzban = "
آمار پنل شما👇:
                             
🖥 وضعیت اتصال پنل مرزبان: ✅ پنل متصل است
👥  تعداد کل کاربران: $total_user
👤 تعداد کاربران فعال: $active_users
🛍 تعداد فروش کل در این پنل : $ListSell
🛍 جمع فروش کل در این پنل : $ListSellSUM تومان
گروه کاربری :{$marzban_list_get['agent']}
        
⭕️ برای مدیریت پنل یکی از گزینه های زیر را انتخاب کنید";
            sendmessage($from_id, $text_marzban, $optionmarzneshin, 'HTML');
        } elseif (isset($Check_token['detail']) && $Check_token['detail'] == "Incorrect username or password") {
            $text_marzban = "❌ نام کاربری یا رمز عبور پنل اشتباه است";
            sendmessage($from_id, $text_marzban, $optionMarzban, 'HTML');
        } else {
            $text_marzban = $textbotlang['Admin']['managepanel']['errorstateuspanel'] . json_encode($Check_token);
            sendmessage($from_id, $text_marzban, $optionMarzban, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "WGDashboard") {
        sendmessage($from_id, $textbotlang['users']['selectoption'], $optionwg, 'HTML');
    } elseif ($marzban_list_get['type'] == "s_ui") {
        sendmessage($from_id, $textbotlang['users']['selectoption'], $options_ui, 'HTML');
    } elseif ($marzban_list_get['type'] == "ibsng") {
        $result = loginIBsng($marzban_list_get['url_panel'], $marzban_list_get['username_panel'], $marzban_list_get['password_panel']);
        if ($result) {
            sendmessage($from_id, $result['msg'], $optionibsng, 'HTML');
        } else {
            sendmessage($from_id, "❌ خطا در اتصال به پنل IBSng", $optionibsng, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "mikrotik") {
        $result = login_mikrotik($marzban_list_get['url_panel'], $marzban_list_get['username_panel'], $marzban_list_get['password_panel']);
        if (isset($result['error'])) {
            sendmessage($from_id, json_encode($result), $option_mikrotik, 'HTML');
        } else {
            $free_hdd_space = round($result['free-hdd-space'] / pow(1024, 3), 2);
            $free_memory = round($result['free-memory'] / pow(1024, 3), 2);
            $total_hdd_space = round($result['total-hdd-space'] / pow(1024, 3), 2);
            $total_memory = round($result['total-memory'] / pow(1024, 3), 2);
            sendmessage($from_id, "<b>📡 اطلاعات سیستم MikroTik شما:</b>

<blockquote>
🖥 <b>پلتفرم:</b> {$result['platform']}  
🏷 <b>نسخه:</b> {$result['version']}  
🕰 <b>مدت زمان روشن بودن:</b> {$result['uptime']}  
</blockquote>

<blockquote>
💽 <b>نام معماری:</b> {$result['architecture-name']}  
📋 <b>مدل برد:</b> {$result['board-name']}  
🏗 <b>زمان ساخت سیستم:</b> {$result['build-time']}  
</blockquote>

<blockquote>
⚙️ <b>پردازنده:</b> {$result['cpu']}  
🔢 <b>تعداد هسته‌ها:</b> {$result['cpu-count']}  
🚀 <b>فرکانس CPU:</b> {$result['cpu-frequency']}  
📊 <b>میزان بار CPU:</b> {$result['cpu-load']} %
</blockquote>

<blockquote>
💾 <b>فضای کل هارد:</b> $total_hdd_space گیگ  
📂 <b>فضای آزاد هارد:</b> $free_hdd_space گیگ  
🧠 <b>حافظه کل رم:</b> $total_memory گیگ  
📉 <b>حافظه آزاد رم:</b> $free_memory گیگ
</blockquote>

<blockquote>
📝 <b>سکتورهای نوشته‌شده از زمان ریبوت:</b> {$result['write-sect-since-reboot']}  
🧮 <b>مجموع سکتورهای نوشته‌شده:</b> {$result['write-sect-total']}
</blockquote>
", $option_mikrotik, 'HTML');
        }
    } else {
        sendmessage($from_id, "یک گزینه را انتخاب نمایید", $optionMarzban, 'HTML');
    }
    update("user", "Processing_value", $text, "id", $from_id);
    step('home', $from_id);
} elseif ($text == "✍️ نام پنل" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['GetNameNew'], $backadmin, 'HTML');
    step('GetNameNew', $from_id);
} elseif ($user['step'] == "GetNameNew") {
    if (in_array($text, $marzban_list)) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['Repeatpanel'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedNmaePanel']);
    update("user", "Processing_value", $text, "id", $from_id);
    update("marzban_panel", "name_panel", $text, "name_panel", $user['Processing_value']);
    update("invoice", "Service_location", $text, "Service_location", $user['Processing_value']);
    update("product", "Location", $text, "Location", $user['Processing_value']);
    update("user", "Processing_value", $text, "id", $from_id);
    step('home', $from_id);
} elseif ($text == "🔗 ویرایش آدرس پنل" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['geturlnew'], $backadmin, 'HTML');
    step('GeturlNew', $from_id);
} elseif ($user['step'] == "GeturlNew") {
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedurlPanel']);
    update("marzban_panel", "url_panel", $text, "name_panel", $user['Processing_value']);
    update("marzban_panel", "datelogin", null, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "📍 تغییر گروه کاربری" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 نوع کاربری را ارسال کنید
گروه های کاربری : f,n,n2
❌ در صورتی که می خواهید پنل برای تمام گروه کاربری ها نمایش داده شود متن all را ارسال کنید", $backadmin, 'HTML');
    step('getagentpanel', $from_id);
} elseif ($user['step'] == "getagentpanel") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], "📌گروه کاربری با موفقیت تغییر کرد");
    update("marzban_panel", "agent", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "🔗 دامنه لینک ساب" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 اگر پنل ثنایی هستید یک لینک ساب کاربر را از پنل کپی کرده سپس در این بخش ارسال کنید .بقیه پنل ها باید طبق ساختارش ارسال نمایید.", $backadmin, 'HTML');
    step('GeturlNewx', $from_id);
} elseif ($user['step'] == "GeturlNewx") {
    $inputLink = trim($text);
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($typepanel['type'] !== "x-ui_single" && !filter_var($inputLink, FILTER_VALIDATE_URL)) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
        return;
    }
    if ($typepanel['type'] === "x-ui_single") {
        $text = normalizeXuiSingleSubscriptionBaseUrl($inputLink);
    } else {
        $text = $inputLink;
    }
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedurlPanel']);
    update("marzban_panel", "linksubx", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "🔗 uuid admin" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 uuid ادمین را ارسال کنید", $backadmin, 'HTML');
    step('getuuidadmin', $from_id);
} elseif ($user['step'] == "getuuidadmin") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], "✅ uuid ادمین ذخیره گردید");
    update("marzban_panel", "secret_code", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "🚨 محدودیت ساخت اکانت" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['setlimit'], $backadmin, 'HTML');
    step('getlimitnew', $from_id);
} elseif ($user['step'] == "getlimitnew") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['changedlimit']);
    update("marzban_panel", "limit_panel", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "⏳ زمان سرویس تست" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "🕰 مدت زمان سرویس تست را ارسال کنید.
⚠️ زمان بر حسب ساعت است.", $backadmin, 'HTML');
    step('updatetime', $from_id);
} elseif ($user['step'] == "updatetime") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['InvalidTime'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    update("marzban_panel", "time_usertest", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "💾 حجم اکانت تست" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "حجم سرویس تست را ارسال کنید.
⚠️ حجم بر حسب مگابایت است.", $backadmin, 'HTML');
    step('val_usertest', $from_id);
} elseif ($user['step'] == "val_usertest") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    update("marzban_panel", "val_usertest", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "💎 تنظیم شناسه اینباند" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 شناسه اینباندی که می خواهید کانفیگ ازآن ساخته شود راارسال نمایید.  شناسه اینباند یک عدد چند رقمی است که در پنل  در صفحه اینباند ها ستون id  نوشته شده است

⚠️ در صورتی که پنل wgdashboard هستید باید نام کانفیگ را ارسال نمایید", $backadmin, 'HTML');
    step('getinboundiid', $from_id);
} elseif ($user['step'] == "getinboundiid") {
    sendmessage($from_id, "✅ شناسه اینباند با موفقیت ذخیره گردید", $optionX_ui_single, 'HTML');
    update("marzban_panel", "inboundid", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "👤 ویرایش نام کاربری" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['getusernamenew'], $backadmin, 'HTML');
    step('GetusernameNew', $from_id);
} elseif ($user['step'] == "GetusernameNew") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedusernamePanel']);
    update("marzban_panel", "username_panel", $text, "name_panel", $user['Processing_value']);
    update("marzban_panel", "datelogin", null, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "⚙️ تنظیم پروتکل" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['Inbound']['GetProtocol'], $keyboardprotocol, 'HTML');
    step('getprotocolx_ui', $from_id);
} elseif ($user['step'] == "getprotocolx_ui") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['setprotocol']);
    $marzbanprotocol = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    update("x_ui", "protocol", $text, "codepanel", $marzbanprotocol['code_panel']);
    step('home', $from_id);
} elseif ($text == "🔐 ویرایش رمز عبور" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['getpasswordnew'], $backadmin, 'HTML');
    step('GetpaawordNew', $from_id);
} elseif ($user['step'] == "GetpaawordNew") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['ChangedpasswordPanel']);
    update("marzban_panel", "password_panel", $text, "name_panel", $user['Processing_value']);
    update("marzban_panel", "datelogin", null, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "❌ حذف پنل" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "در صورت تایید کلمه زیر را ارسال کنید.
<code>تایید</code>", $backadmin, 'HTML');
    step('confirmremovepanel', $from_id);
} elseif ($user['step'] == "confirmremovepanel") {
    if ($text == "تایید") {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['RemovedPanel'], $keyboardadmin, 'HTML');
        $marzban = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
        $stmt = $pdo->prepare("DELETE FROM marzban_panel WHERE name_panel = :name_panel");
        $stmt->bindParam(':name_panel', $user['Processing_value'], PDO::PARAM_STR);
        $stmt->execute();
    }
    step('home', $from_id);
} elseif ($text == $textbotlang['Admin']['btnkeyboardadmin']['managruser'] || $datain == "backlistuser") {
    $keyboardtypelistuser = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "لیست کاربرانی که موجودی دارند.", 'callback_data' => "balanceuserlist"],
            ],
            [
                ['text' => "لیست کاربرانی که زیرمجموعه دارند.", 'callback_data' => "listrefral"],
            ],
            [
                ['text' => "لیست کاربران شماره کارت فعال.", 'callback_data' => "cartuserlist"],
            ],
            [
                ['text' => "لیست کاربرانی که موجودی منفی دارند", 'callback_data' => "zerobalance"],
            ],
            [
                ['text' => "لیست نمایندگان", 'callback_data' => "agentlistusers"],
                ['text' => "لیست کل کاربران", 'callback_data' => "alllistusers"],
            ],
            [
                ['text' => "🛍 جستجو سفارش", 'callback_data' => "searchorder"],
                ['text' => "👥 شارژ همگانی", 'callback_data' => "balanceaddall"],
            ],
            [
                ['text' => "🔍 جستجو کاربر", 'callback_data' => "searchuser"],
                ['text' => "📨 بخش ارسال پیام", 'callback_data' => "systemsms"],
            ],
            [
                ['text' => "🔋 حجم یا زمان همگانی", 'callback_data' => "voloume_or_day_all"],
            ]
        ]
    ]);
    $text_list_users = "📌 از لیست زیر یک گزینه را انتخاب نمایید";
    if ($datain == "backlistuser") {
        Editmessagetext($from_id, $message_id, $text_list_users, $keyboardtypelistuser);
    } else {
        sendmessage($from_id, $text_list_users, $keyboardtypelistuser, 'html');
    }
} elseif ($datain == "alllistusers") {
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuser'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuser'
        ]
    ];
    $backbtn = [
        [
            'text' => "بازگشت به منوی قبل",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = [
        [
            'text' => '❌ بستن',
            'callback_data' => 'close_listusers'
        ]
    ];
    $keyboard_json = json_encode($keyboardlists);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'next_pageuser') {
    $numpage = select("user", "*", null, null, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuser'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = [
        [
            'text' => '❌ بستن',
            'callback_data' => 'close_listusers'
        ]
    ];
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'previous_pageuser') {
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuser'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = [
        [
            'text' => '❌ بستن',
            'callback_data' => 'close_listusers'
        ]
    ];
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'close_listusers') {
    deletemessage($from_id, $message_id);
} elseif ($datain == "agentlistusers") {
    $keyboardtypelistuser = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "n", 'callback_data' => "agenttypshowlist_n"],
                ['text' => "n2", 'callback_data' => "agenttypshowlist_n2"],
            ],
            [
                ['text' => "تمام نمایندگان", 'callback_data' => "agenttypshowlist_all"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 کدام گروه از نمایندگان می خواهید مشاهده کنید ؟", $keyboardtypelistuser);
} elseif (preg_match('/agenttypshowlist_(\w+)/', $datain, $datagetr)) {
    $typeagent = $datagetr[1];
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    if ($typeagent == "all") {
        $result = mysqli_query($connect, "SELECT * FROM user WHERE agent != 'f'  LIMIT $start_index, $items_per_page");
    } else {
        $result = mysqli_query($connect, "SELECT * FROM user WHERE agent = '$typeagent'  LIMIT $start_index, $items_per_page");
    }
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => "next_pageuseragent_$typeagent"
        ]
    ];
    $backbtn = [
        [
            'text' => "بازگشت به منوی قبل",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif (preg_match('/next_pageuseragent_(\w+)/', $datain, $datagetr)) {
    $typeagent = $datagetr[1];
    $numpage = select("user", "*", null, null, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    if ($typeagent == "all") {
        $result = mysqli_query($connect, "SELECT * FROM user WHERE agent != 'f'  LIMIT $start_index, $items_per_page");
    } else {
        $result = mysqli_query($connect, "SELECT * FROM user WHERE agent = '$typeagent'  LIMIT $start_index, $items_per_page");
    }
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => "next_pageuseragent_$typeagent"
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => "previous_pageuseragent_$typeagent"
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif (preg_match('/previous_pageuseragent_(\w+)/', $datain, $datagetr)) {
    $typeagent = $datagetr[1];
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    if ($typeagent == "all") {
        $result = mysqli_query($connect, "SELECT * FROM user WHERE agent != 'f'  LIMIT $start_index, $items_per_page");
    } else {
        $result = mysqli_query($connect, "SELECT * FROM user WHERE agent = '$typeagent'  LIMIT $start_index, $items_per_page");
    }
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => "next_pageuseragent_$typeagent"
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => "previous_pageuseragent_$typeagent"
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == "balanceuserlist") {
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user WHERE Balance != '0'  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserbalance'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserbalance'
        ]
    ];
    $backbtn = [
        [
            'text' => "بازگشت به منوی قبل",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'next_pageuserbalance') {
    $numpage = select("user", "*", null, null, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user WHERE Balance != '0'  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserbalance'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserbalance'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'previous_pageuserbalance') {
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user WHERE Balance != '0'  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserbalance'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserbalance'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == "listrefral") {
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user WHERE affiliatescount != '0'  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserrefral'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserrefral'
        ]
    ];
    $backbtn = [
        [
            'text' => "بازگشت به منوی قبل",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'next_pageuserrefral') {
    $numpage = select("user", "*", null, null, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user WHERE affiliatescount != '0'  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserrefral'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserrefral'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'previous_pageuserrefral') {
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user WHERE affiliatescount != '0'  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserrefral'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserrefral'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif (preg_match('/addbalanceuser_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    telegram('sendmessage', [
        'chat_id' => $from_id,
        'text' => $textbotlang['Admin']['ManageUser']['addbalanceuserdec'],
        'reply_markup' => $backadmin,
        'parse_mode' => "HTML",
        'reply_to_message_id' => $message_id,
    ]);
    step('addbalanceusercurrent', $from_id);
} elseif ($user['step'] == "addbalanceusercurrent") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    if ($text > 100000000) {
        sendmessage($from_id, "❌ حداکثر مبلغ 100 میلیون تومان می باشد", $backadmin, 'HTML');
        return;
    }
    $dateacc = date('Y/m/d H:i:s');
    $randomString = bin2hex(random_bytes(5));
    $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
    $payment_Status = "paid";
    $Payment_Method = "add balance by admin";
    $invoice = null;
    $stmt->bind_param("sssssss", $user['Processing_value'], $randomString, $dateacc, $text, $payment_Status, $Payment_Method, $invoice);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['addbalanced'], $keyboardadmin, 'html');
    $Balance_user = select("user", "*", "id", $user['Processing_value'], "select");
    $Balance_add_user = $Balance_user['Balance'] + $text;
    update("user", "Balance", $Balance_add_user, "id", $user['Processing_value']);
    $heibalanceuser = number_format($text, 0);
    $textadd = "💎 کاربر عزیز مبلغ $heibalanceuser تومان به موجودی کیف پول تان اضافه گردید.";
    sendmessage($user['Processing_value'], $textadd, null, 'HTML');
    step('home', $from_id);
    $Balance_user_after = number_format(select("user", "*", "id", $user['Processing_value'], "select")['Balance']);
    $pricadd = number_format($text);
    if (strlen($setting['Channel_Report']) > 0) {
        $textaddbalance = "📌 یک ادمین موجودی کاربر را افزایش داده است :
        
🪪 اطلاعات ادمین افزایش دهنده موجودی : 
نام کاربری :@$username
آیدی عددی : $from_id
👤 اطلاعات کاربر دریافت کننده موجودی :
آیدی عددی کاربر  : {$user['Processing_value']}
مبلغ موجودی : $pricadd
موجودی کاربر پس از افزایش : $Balance_user_after";
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $textaddbalance,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/lowbalanceuser_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    telegram('sendmessage', [
        'chat_id' => $from_id,
        'text' => $textbotlang['Admin']['ManageUser']['lowbalanceuserdec'],
        'reply_markup' => $backadmin,
        'parse_mode' => "HTML",
        'reply_to_message_id' => $message_id,
    ]);
    step('addbalanceuser', $from_id);
} elseif ($user['step'] == "addbalanceuser") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    if ($text > 100000000) {
        sendmessage($from_id, "❌ حداکثر مبلغ 100 میلیون تومان می باشد", $backadmin, 'HTML');
        return;
    }
    $dateacc = date('Y/m/d H:i:s');
    $randomString = bin2hex(random_bytes(5));
    $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
    $payment_Status = "paid";
    $Payment_Method = "low balance by admin";
    $invoice = null;
    $stmt->bind_param("sssssss", $user['Processing_value'], $randomString, $dateacc, $text, $payment_Status, $Payment_Method, $invoice);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['lowbalanced'], $keyboardadmin, 'html');
    $Balance_user = select("user", "*", "id", $user['Processing_value'], "select");
    $Balance_add_user = $Balance_user['Balance'] - $text;
    update("user", "Balance", $Balance_add_user, "id", $user['Processing_value']);
    $lowbalanceuser = number_format($text, 0);
    $textkam = "❌ کاربر عزیز مبلغ $lowbalanceuser تومان از  موجودی کیف پول تان کسر گردید.";
    sendmessage($user['Processing_value'], $textkam, null, 'HTML');
    step('home', $from_id);
    $Balance_user_afters = number_format(select("user", "*", "id", $user['Processing_value'], "select")['Balance']);
    if (strlen($setting['Channel_Report']) > 0) {
        $textaddbalance = "📌 یک ادمین موجودی کاربر را کم کرده است :
        
🪪 اطلاعات ادمین کم کننده موجودی : 
نام کاربری :@$username
آیدی عددی : $from_id
👤 اطلاعات کاربر  :
آیدی عددی کاربر  : {$user['Processing_value']}
مبلغ موجودی : $text
موجودی کاربر پس از کم کردن : $Balance_user_afters";
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $textaddbalance,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/banuserlist_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    $userdata = select("user", "*", "id", $iduser, "select");
    if ($userdata['User_Status'] == "block") {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['BlockedUser'], null, 'HTML');
        return;
    }
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "تایید", 'callback_data' => 'acceptblock_' . $iduser],
            ],
        ]
    ]);
    sendmessage($from_id, "در صورت تایید روی دکمه تایید کلیک کنید", $Response, 'HTML');
} elseif ($user['step'] == "adddecriptionblock") {
    update("user", "description_blocking", $text, "id", $user['Processing_value']);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['DescriptionBlock'], $keyboardadmin, 'HTML');
    step('home', $from_id);

} elseif ((preg_match('/acceptblock_(\w+)/', $datain, $dataget) || preg_match('/blockuserfake_(\w+)/', $datain, $dataget))) {

    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    update("user", "User_Status", "block", "id", $iduser);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['BlockUser'], $backadmin, 'HTML');
    step('adddecriptionblock', $from_id);
    $textblok = "کاربر با آیدی عددی
$iduser  در ربات مسدود گردید 
ادمین مسدود کننده : $from_id";
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $iduser],
            ],
        ]
    ]);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $textblok,
            'parse_mode' => "HTML",
            'reply_markup' => $Response
        ]);
    }
} elseif (preg_match('/verify_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "verify", "1", "id", $iduser);
    sendmessage($from_id, "✅ کاربر با موفقیت احراز گردید.", null, 'HTML');
    sendmessage($iduser, "💎 کاربر گرامی حساب کاربری شما توسط ادمین با موفقیت احراز هویت گردید و هم اکنون می توانیدخرید خود را انجام دهید", $keyboard, 'HTML');
} elseif (preg_match('/unverify-(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "verify", "0", "id", $iduser);
    sendmessage($from_id, "✅ کاربر با موفقیت از حالت احراز خارج گردید.", null, 'HTML');


} elseif (preg_match('/unbanuserr_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    $userdata = select("user", "*", "id", $iduser, "select");
    if ($userdata['User_Status'] == "Active") {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['UserNotBlock'], null, 'HTML');
        return;
    }
    $textblok = "کاربر با آیدی عددی
$iduser  در ربات  رفع مسدود گردید 
ادمین مسدود کننده : $from_id";
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $iduser],
            ],
        ]
    ]);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $textblok,
            'parse_mode' => "HTML",
            'reply_markup' => $Response
        ]);
    }
    update("user", "User_Status", "Active", "id", $iduser);
    update("user", "description_blocking", " ", "id", $iduser);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['UserUnblocked'], $keyboardadmin, 'HTML');
    sendmessage($iduser, "✳️ حساب کاربری شما از مسدودی خارج شد ✳️
اکنون میتوانید از ربات استفاده کنید ✔️", $keyboard, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/confirmnumber_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "number", "confrim number by admin", "id", $iduser);
    sendmessage($from_id, $textbotlang['Admin']['phone']['active'], $keyboardadmin, 'HTML');
} elseif (preg_match('/viewpaymentuser_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    $stmt_pay = $pdo->prepare("SELECT * FROM Payment_report WHERE id_user = :id_user");
    $stmt_pay->execute([':id_user' => $iduser]);
    $PaymentUsers = $stmt_pay->fetchAll(PDO::FETCH_ASSOC);
    foreach ($PaymentUsers as $paymentUser) {
        $text_order = "🛒 شماره پرداخت  :  <code>{$paymentUser['id_order']}</code>
🙍‍♂️ شناسه کاربر : <code>{$paymentUser['id_user']}</code>
💰 مبلغ پرداختی : {$paymentUser['price']} تومان
⚜️ وضعیت پرداخت : {$paymentUser['payment_Status']}
⭕️ روش پرداخت : {$paymentUser['Payment_Method']} 
📆 تاریخ خرید :  {$paymentUser['time']}";
        sendmessage($from_id, $text_order, null, 'HTML');
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['sendpayemntlist'], $keyboardadmin, 'HTML');
} elseif (preg_match('/affiliates-(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    $affiliatesUsers = select("user", "*", "affiliates", $iduser, "count");
    if ($affiliatesUsers == 0) {
        sendmessage($from_id, "❌ کاربر دارای زیرمجموعه نمی باشد.", null, 'HTML');
        return;
    }
    $affiliatesUsers = select("user", "*", "affiliates", $iduser, "fetchAll");
    $count = 0;
    $text_affiliates = "";
    foreach ($affiliatesUsers as $affiliatesUser) {
        $text_affiliates .= "<code>{$affiliatesUser['id']}</code>\n\r";
        $count++;
        if ($count == 10) {
            sendmessage($from_id, $text_affiliates, null, 'HTML');
            $count = 0;
            $text_affiliates = "";
        }
    }
    sendmessage($from_id, $text_affiliates, null, 'HTML');
    sendmessage($from_id, "📌 شناسه مربوط به زیرمجموعه های کاربر ارسال گردید.", $keyboardadmin, 'HTML');
} elseif (preg_match('/removeaffiliate-(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    $user2 = select("user", "*", "id", $iduser, "select");
    $user2 = select("user", "*", "id", $user2['affiliates'], "select");
    $affiliatescount = intval($user2['affiliatescount']) - 1;
    update("user", "affiliatescount", $affiliatescount, "id", $user2['id']);
    update("user", "affiliates", "0", "id", $iduser);
    sendmessage($from_id, "📌 کاربر از زیرمجموعه خارج شد.", $keyboardadmin, 'HTML');
} elseif (preg_match('/removeaffiliateuser-(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "affiliatescount", "0", "id", $iduser);
    update("user", "affiliates", "0", "affiliates", $iduser);
    sendmessage($from_id, "📌 زیرمجموعه های کاربر حذف شد.", $keyboardadmin, 'HTML');
} elseif (preg_match('/removeservice-(.*)/', $datain, $dataget)) {
    $username = $dataget[1];
    $info_product = select("invoice", "*", "id_invoice", $username, "select");
    $DataUserOut = $ManagePanel->DataUser($info_product['Service_location'], $info_product['username']);
    $ManagePanel->RemoveUser($info_product['Service_location'], $info_product['username']);
    update('invoice', 'status', 'removebyadmin', 'id_invoice', $username);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['RemovedService'], $keyboardadmin, 'HTML');
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    step('home', $from_id);
} elseif (preg_match('/removeserviceandback-(\w+)/', $datain, $dataget)) {
    $username = $dataget[1];
    $info_product = select("invoice", "*", "id_invoice", $username, "select");
    if ($info_product['Status'] == "removebyadmin") {
        sendmessage($from_id, "❌ سرویس از قبل حذف شده است", $keyboardadmin, 'HTML');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($info_product['Service_location'], $info_product['username']);
    if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") {
        sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
    } else {
        if ($DataUserOut['status'] == "Unsuccessful") {
            sendmessage($from_id, 'خطایی رخ داده است', $keyboardadmin, 'HTML');
        }
    }
    $ManagePanel->RemoveUser($info_product['Service_location'], $info_product['username']);
    update('invoice', 'status', 'removebyadmin', 'id_invoice', $username);
    $Balance_user = select("user", "*", "id", $info_product['id_user'], "select");
    $Balance_add_user = $Balance_user['Balance'] + $info_product['price_product'];
    update("user", "Balance", $Balance_add_user, "id", $info_product['id_user']);
    $textadd = "💎 کاربر عزیز مبلغ {$info_product['price_product']} تومان به موجودی کیف پول تان اضافه گردید.";
    sendmessage($info_product['id_user'], $textadd, null, 'HTML');
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['RemovedService'], $keyboardadmin, 'HTML');
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    step('home', $from_id);
} elseif ($text == "🎁 ساخت کد تخفیف" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['Discountsell']['GetCode'], $backadmin, 'HTML');
    step('get_codesell', $from_id);
} elseif ($user['step'] == "get_codesell") {
    if (!preg_match('/^[A-Za-z\d]+$/', $text)) {
        sendmessage($from_id, $textbotlang['Admin']['Discount']['ErrorCode'], null, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['Discount']['PriceCodesell'], null, 'HTML');
    step('get_price_codesell', $from_id);
    savedata("clear", "code", strtolower($text));
} elseif ($user['step'] == "get_price_codesell") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "price", $text);
    sendmessage($from_id, $textbotlang['Admin']['Discountsell']['getlimit'], $backadmin, 'HTML');
    step('getlimitcode', $from_id);
} elseif ($user['step'] == "getlimitcode") {
    savedata("save", "limitDiscount", $text);
    sendmessage($from_id, $textbotlang['Admin']['Discount']['agentcode'], $backadmin, 'HTML');
    step('gettypecodeagent', $from_id);
} elseif ($user['step'] == "gettypecodeagent") {
    $agentst = ["n", "n2", "f", "allusers"];
    if (!in_array($text, $agentst)) {
        sendmessage($from_id, $textbotlang['Admin']['Discount']['invalidagentcode'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "agent", $text);
    sendmessage($from_id, "📌 کد تخفیف برای چند ساعت فعال باشد . در صورتی که میخواهید نامحدود باشد عدد 0 را ارسال کنید", $backadmin, 'HTML');
    step('gettimediscount', $from_id);
} elseif ($user['step'] == "gettimediscount") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    if (intval($text) == 0) {
        $text = "0";
    } else {
        $text = time() + (intval($text) * 3600);
    }
    savedata("save", "time", $text);
    $keyboarddiscount = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "تمامی خرید ها", 'callback_data' => "discountlimitbuy_0"],
                ['text' => "خرید اول", 'callback_data' => "discountlimitbuy_1"],
            ],
        ]
    ]);
    sendmessage($from_id, $textbotlang['Admin']['Discount']['firstdiscount'], $keyboarddiscount, 'HTML');
    step('getfirstdiscount', $from_id);
} elseif (preg_match('/discountlimitbuy_(\w+)/', $datain, $dataget)) {
    $discountbuylimit = $dataget[1];
    savedata("save", "usefirst", $discountbuylimit);
    if (intval($discountbuylimit) == 1) {
        sendmessage($from_id, "📌محدودیت استفاده برای یک کاربر را ارسال نمایید.", $backadmin, 'HTML');
        step('getuseuser', $from_id);
        savedata("save", "typediscount", "all");
    } else {
        $keyboarddiscount = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "خرید", 'callback_data' => "discounttype_buy"],
                    ['text' => "تمدید", 'callback_data' => "discounttype_extend"],
                ],
                [
                    ['text' => "هردو", 'callback_data' => "discounttype_all"]
                ]
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 کد تخفیف برای کدوم بخش باشد", $keyboarddiscount);
    }
} elseif (preg_match('/discounttype_(\w+)/', $datain, $dataget)) {
    $discountbuytype = $dataget[1];
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    savedata("save", "typediscount", $discountbuytype);
    sendmessage($from_id, "📌محدودیت استفاده برای یک کاربر را ارسال نمایید.", $backadmin, 'HTML');
    step('getuseuser', $from_id);
} elseif ($user['step'] == "getuseuser") {
    $userdata = json_decode($user['Processing_value'], true);
    $numberlimit = $userdata['limitDiscount'];
    if (intval($text) > intval($userdata['limitDiscount'])) {
        sendmessage($from_id, "📌 تعداد استفاده برای یک کاربر باید کوچیک تر از محدودیت کل باشد", $backadmin, 'HTML');
        return;
    }
    step('getlocdiscount', $from_id);
    savedata("save", "useuser", $text);
    sendmessage($from_id, "📌 برای تنظیم  کد تخفیف مخصوص یک محصول ابتدا موقعیت محصول راانتخاب نمایید.
توجه : برای انتخاب تمام پنل ها کلمه<code>/all</code> را ارسال کنید", $json_list_marzban_panel, 'HTML');
    step('getlocdiscount', $from_id);
} elseif ($user['step'] == "getlocdiscount") {
    if ($text == "/all") {
        $panel['code_panel'] = "/all";
    } else {
        $panel = select("marzban_panel", "*", "name_panel", $text, "select");
    }
    if ($panel == false)
        return;
    savedata("save", "code_panel", $panel['code_panel']);
    savedata("save", "name_panel", $text);
    sendmessage($from_id, "📌  میخواهید کد تخفیف برای کدام محصول باشد. توجه داشتید درصورتی که میخواهید کد تخفیف برای تمامی محصولات باشد کلمه all را ارسال کنید", $json_list_product_list_admin, 'HTML');
    step('getproductdiscount', $from_id);
} elseif ($user['step'] == "getproductdiscount") {
    if ($text != "all") {
        $product = select("product", "*", "name_product", $text, "select");
    } else {
        $product['code_product'] = "all";
    }
    if ($product == false) {
        sendmessage($from_id, "❌ محصول انتخابی وجود ندارد", $keyboardadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $stmt = $pdo->prepare("INSERT INTO DiscountSell (codeDiscount, usedDiscount, price, limitDiscount, agent, usefirst, useuser, code_panel, code_product, time,type) VALUES (:codeDiscount, :usedDiscount, :price, :limitDiscount, :agent, :usefirst, :useuser, :code_panel, :code_product, :time,:type)");
    $values = "0";
    $values1 = "1";
    $code_product = "0";
    $stmt->bindParam(':codeDiscount', $userdata['code'], PDO::PARAM_STR);
    $stmt->bindParam(':usedDiscount', $values, PDO::PARAM_STR);
    $stmt->bindParam(':price', $userdata['price'], PDO::PARAM_STR);
    $stmt->bindParam(':limitDiscount', $userdata['limitDiscount'], PDO::PARAM_STR);
    $stmt->bindParam(':agent', $userdata['agent'], PDO::PARAM_STR);
    $stmt->bindParam(':usefirst', $userdata['usefirst'], PDO::PARAM_STR);
    $stmt->bindParam(':useuser', $userdata['useuser'], PDO::PARAM_STR);
    $stmt->bindParam(':code_panel', $userdata['code_panel'], PDO::PARAM_STR);
    $stmt->bindParam(':code_product', $product['code_product'], PDO::PARAM_STR);
    $stmt->bindParam(':time', $userdata['time'], PDO::PARAM_STR);
    $stmt->bindParam(':type', $userdata['typediscount'], PDO::PARAM_STR);
    $stmt->execute();
    $textdiscount = "
🎁 کد تخفیف شما با موفقیت ساخته شد.

📩 نام کد تخفیف: <code>{$userdata['code']}</code>
🧮 درصد کد تخفیف: {$userdata['price']}
🎛 پنل :  {$userdata['name_panel']}
📌  محصول : $text
♻️ نوع کاربری :‌ {$userdata['agent']}
🔴 محدودیت استفاده :‌ {$userdata['limitDiscount']}";
    sendmessage($from_id, $textdiscount, $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "❌ حذف کد تخفیف" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['Discount']['RemoveCode'], $json_list_Discount_list_admin_sell, 'HTML');
    step('remove-Discountsell', $from_id);
} elseif ($user['step'] == "remove-Discountsell") {
    if (!in_array($text, $SellDiscount)) {
        sendmessage($from_id, $textbotlang['Admin']['Discount']['NotCode'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM Giftcodeconsumed WHERE code = :code");
    $stmt->bindParam(':code', $text, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM DiscountSell WHERE codeDiscount = :codeDiscount");
    $stmt->bindParam(':codeDiscount', $text, PDO::PARAM_STR);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['Discount']['RemovedCode'], $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($text == "/end") {
    $userdata = json_decode($user['Processing_value'], true);
    $panel = select("marzban_panel", "*", "name_panel", $userdata['name_panel'], "select");
    if ($panel['type'] == "marzneshin") {
        update("user", "Processing_value", $userdata['name_panel'], "id", $from_id);
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['Inbound']['endInbound'], $optionmarzneshin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['Inbound']['endInbound'], $optionMarzban, 'HTML');
    step('home', $from_id);
    return;
} elseif ($text == "🧮 تنظیم درصد زیرمجموعه" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['affiliates']['setpercentage'], $backadmin, 'HTML');
    step('setpercentage', $from_id);
} elseif ($user['step'] == "setpercentage") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, "درصد نامعتبر", $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['users']['affiliates']['changedpercentage'], $affiliates, 'HTML');
    update("setting", "affiliatespercentage", $text);
    step('home', $from_id);
} elseif ($text == "🏞 تنظیم بنر زیرمجموعه گیری") {
    sendmessage($from_id, $textbotlang['users']['affiliates']['banner'], $backadmin, 'HTML');
    step('setbanner', $from_id);
} elseif ($user['step'] == "setbanner") {
    if (!$photo) {
        sendmessage($from_id, $textbotlang['users']['affiliates']['invalidbanner'], $backadmin, 'HTML');
        return;
    }
    update("affiliates", "id_media", $photoid);
    update("affiliates", "description", $caption);
    sendmessage($from_id, $textbotlang['users']['affiliates']['insertbanner'], $affiliates, 'HTML');
    step('home', $from_id);
} elseif ($text == "👤 آیدی پشتیبانی" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "CartDirect");
    $textcart = "📌 نام کاربری خود را بدون @ برای دریافت شماره کارت ارسال کنید\n\n{$PaySetting['ValuePay']}";
    sendmessage($from_id, $textcart, $backadmin, 'HTML');
    step('CartDirect', $from_id);
} elseif ($user['step'] == "CartDirect") {
    sendmessage($from_id, $textbotlang['Admin']['SettingPayment']['CartDirect'], $CartManage, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "CartDirect");
    step('home', $from_id);
} elseif ($text == "💳 درگاه آفلاین در پیوی" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "Cartstatuspv")['ValuePay'];
    $card_Statuspv = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $PaySetting, 'callback_data' => $PaySetting],
            ],
        ]
    ]);
    sendmessage($from_id, $textbotlang['Admin']['Status']['cardTitlepv'], $card_Statuspv, 'HTML');
} elseif ($datain == "oncardpv" && $adminrulecheck['rule'] == "administrator") {
    update("PaySetting", "ValuePay", "offcardpv", "NamePay", "Cartstatuspv");
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['cardStatusOffpv'], null);
} elseif ($datain == "offcardpv" && $adminrulecheck['rule'] == "administrator") {
    update("PaySetting", "ValuePay", "oncardpv", "NamePay", "Cartstatuspv");
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['cardStatusonpv'], null);
} elseif (preg_match('/addbalamceuser_(\w+)/', $datain, $datagetr) && ($adminrulecheck['rule'] == "administrator" || $adminrulecheck['rule'] == "Seller")) {
    $id_order = $datagetr[1];
    $Payment_report = select("Payment_report", "*", "id_order", $id_order, "select");
    update("user", "Processing_value", $id_order, "id", $from_id);
    if ($Payment_report['payment_Status'] == "paid" || $Payment_report['payment_Status'] == "reject") {
        $ff = telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    update("Payment_report", "payment_Status", "paid", "id_order", $id_order);

    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['addbalanceuserdec'], $backadmin, 'html');
    step('addbalancemanual', $from_id);
    Editmessagetext($from_id, $message_id, $text_inline, null);
} elseif ($user['step'] == "addbalancemanual") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['Balance']['AddBalanceUser'], $keyboardadmin, 'HTML');
    $Payment_report = select("Payment_report", "*", "id_order", $user['Processing_value'], "select");
    $Balance_user = select("user", "*", "id", $Payment_report['id_user'], "select");
    $Balance_add_user = $Balance_user['Balance'] + $text;
    $balanceusers = number_format($text, 0);
    update("user", "Balance", $Balance_add_user, "id", $Payment_report['id_user']);
    $textadd = "💎 کاربر عزیز مبلغ $balanceusers تومان به موجودی کیف پول تان اضافه گردید.";
    sendmessage($Payment_report['id_user'], $textadd, null, 'HTML');
    $text_report = "تایید رسید کارت به کارت و افزایش دستی موجودی توسط ادمین
        
آیدی عددی کاربر : {$Payment_report['id_user']}
نام کاربری کاربر : {$Balance_user['username']}
مبلغ تراکنش در فاکتور :  {$Payment_report['price']}
مبلغ تراکنش واریزی توسط ادمین : $text";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
    step('home', $from_id);
} elseif ($text == "🎁 پورسانت بعد از خرید" && $adminrulecheck['rule'] == "administrator") {
    $marzbancommission = select("affiliates", "*", null, null, "select");
    $keyboardcommission = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbancommission['status_commission'], 'callback_data' => $marzbancommission['status_commission']],
            ],
        ]
    ]);
    sendmessage($from_id, $textbotlang['Admin']['Status']['commission'], $keyboardcommission, 'HTML');
} elseif ($datain == "oncommission") {
    update("affiliates", "status_commission", "offcommission");
    $marzbancommission = select("affiliates", "*", null, null, "select");
    $keyboardcommission = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbancommission['status_commission'], 'callback_data' => $marzbancommission['status_commission']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['commissionStatusOff'], $keyboardcommission);
} elseif ($datain == "offcommission") {
    update("affiliates", "status_commission", "oncommission");
    $marzbancommission = select("affiliates", "*", null, null, "select");
    $keyboardcommission = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbancommission['status_commission'], 'callback_data' => $marzbancommission['status_commission']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['commissionStatuson'], $keyboardcommission);
} elseif ($text == "🎁 هدیه استارت" && $adminrulecheck['rule'] == "administrator") {
    $marzbanDiscountaffiliates = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanDiscountaffiliates['Discount'], 'callback_data' => $marzbanDiscountaffiliates['Discount']],
            ],
        ]
    ]);
    sendmessage($from_id, $textbotlang['Admin']['Status']['Discountaffiliates'], $keyboardDiscountaffiliates, 'HTML');
} elseif ($datain == "onDiscountaffiliates") {
    update("affiliates", "Discount", "offDiscountaffiliates");
    $marzbanDiscountaffiliates = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanDiscountaffiliates['Discount'], 'callback_data' => $marzbanDiscountaffiliates['Discount']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['DiscountaffiliatesStatusOff'], $keyboardDiscountaffiliates);
} elseif ($datain == "offDiscountaffiliates") {
    update("affiliates", "Discount", "onDiscountaffiliates");
    $marzbanDiscountaffiliates = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanDiscountaffiliates['Discount'], 'callback_data' => $marzbanDiscountaffiliates['Discount']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['DiscountaffiliatesStatuson'], $keyboardDiscountaffiliates);
} elseif ($text == "🌟 مبلغ هدیه استارت" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['affiliates']['priceDiscount'], $backadmin, 'HTML');
    step('getdiscont', $from_id);
} elseif ($user['step'] == "getdiscont") {
    sendmessage($from_id, $textbotlang['users']['affiliates']['changedpriceDiscount'], $affiliates, 'HTML');
    update("affiliates", "price_Discount", $text);
    step('home', $from_id);
} elseif ($datain == "mainbalanceaccount" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = json_decode(select("PaySetting", "ValuePay", "NamePay", "minbalance", "select")[$user['agent']], true);
    $textmin = "📌 حداقل مبلغی که می خواهید کاربر حساب خود را شارژ کند را تعیین کنید";
    sendmessage($from_id, $textmin, $backadmin, 'HTML');
    step('minbalance', $from_id);
} elseif ($user['step'] == "minbalance") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value", $text, "id", $from_id);
    step('getagentbalancemin', $from_id);
    sendmessage($from_id, "📌حداقل موجودی برای کدام گروه کاربری باشید.
f
n
n2", $backadmin, 'HTML');
} elseif ($user['step'] == "getagentbalancemin") {
    $agentst = ["n", "n2", "f", "allusers"];
    if (!in_array($text, $agentst)) {
        sendmessage($from_id, $textbotlang['Admin']['Discount']['invalidagentcode'], $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    $balancemaax = json_decode(select("PaySetting", "ValuePay", "NamePay", "minbalance", "select")['ValuePay'], true);
    $balancemaax[$text] = $user['Processing_value'];
    $balancemaax = json_encode($balancemaax);
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $keyboardadmin, 'HTML');
    update("PaySetting", "ValuePay", $balancemaax, "NamePay", "minbalance");
} elseif ($datain == "maxbalanceaccount" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "maxbalance", "select");
    $textmax = "📌 حداکثر مبلغی که می خواهید کاربر حساب خود را شارژ کند را تعیین کنید";
    sendmessage($from_id, $textmax, $backadmin, 'HTML');
    step('maxbalance', $from_id);
} elseif ($datain == "walletaddress" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "walletaddress", "select");
    $currentWallet = $PaySetting['ValuePay'] ?? '';
    $texttronseller = "💼 لطفاً آدرس ولت ترون (TRC20) خود را ارسال کنید.\n\nولت فعلی شما: {$currentWallet}";
    sendmessage($from_id, $texttronseller, $backadmin, 'HTML');

    savedata('clear', 'walletaddress_origin', 'general');

    step('walletaddresssiranpay', $from_id);
} elseif ($user['step'] == "maxbalance") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value", $text, "id", $from_id);
    step('getagentbalancemax', $from_id);
    sendmessage($from_id, "📌حداقل موجودی برای کدام گروه کاربری باشید.
f
n
n2", $backadmin, 'HTML');
} elseif ($user['step'] == "getagentbalancemax") {
    $agentst = ["n", "n2", "f", "allusers"];
    if (!in_array($text, $agentst)) {
        sendmessage($from_id, $textbotlang['Admin']['Discount']['invalidagentcode'], $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    $balancemaax = json_decode(select("PaySetting", "ValuePay", "NamePay", "maxbalance", "select")['ValuePay'], true);
    $balancemaax[$text] = $user['Processing_value'];
    $balancemaax = json_encode($balancemaax);
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $keyboardadmin, 'HTML');
    update("PaySetting", "ValuePay", $balancemaax, "NamePay", "maxbalance");
} elseif (preg_match('/removeagent_(\w+)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    telegram('sendmessage', [
        'chat_id' => $from_id,
        'text' => $textbotlang['Admin']['agent']['useragentremoved'],
        'parse_mode' => "HTML",
        'reply_to_message_id' => $message_id,
    ]);
    update("user", "agent", "f", "id", $id_user);
    update("user", "pricediscount", "0", "id", $id_user);
    update("user", "expire", null, "id", $id_user);
    $stmt = $pdo->prepare("DELETE FROM Requestagent WHERE id = :id_user");
    $stmt->execute([':id_user' => $id_user]);
    step('home', $from_id);
} elseif (preg_match('/addagent_(\w+)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    update("user", "Processing_value", $id_user, "id", $from_id);
    telegram('sendmessage', [
        'chat_id' => $from_id,
        'text' => $textbotlang['Admin']['agent']['gettypeagent'],
        'parse_mode' => "HTML",
        'reply_markup' => $backadmin,
        'reply_to_message_id' => $message_id,
    ]);
    step('gettypeagentoflist', $from_id);
} elseif ($user['step'] == "gettypeagentoflist") {
    $agentst = ["n", "n2"];
    if (!in_array($text, $agentst)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['agent']['useragented'], $keyboardadmin, 'HTML');
    update("user", "expire", null, "id", $user['Processing_value']);
    update("user", "agent", $text, "id", $user['Processing_value']);
    step('home', $from_id);
} elseif (preg_match('/Percentlow_(\w+)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    update("user", "Processing_value", $id_user, "id", $from_id);
    telegram('sendmessage', [
        'chat_id' => $from_id,
        'text' => "📌 تعداد درصدی که میخواهید در صورتی که کاربر هرگونه خریدی انجام داده است تخفیفی دریافت کند را ارسال نمایید.",
        'reply_markup' => $backadmin,
        'parse_mode' => "HTML",
        'reply_to_message_id' => $message_id,
    ]);
    step('getpercentuser', $from_id);
} elseif ($user['step'] == "getpercentuser") {
    if (intval($text) > 100 || intval($text) < 0 || !ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $keyboardadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "تغییرات با موفقیت اعمال شد", $keyboardadmin, 'HTML');
    update("user", "pricediscount", $text, "id", $user['Processing_value']);
    step('home', $from_id);
} elseif (preg_match('/maxbuyagent_(\w+)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    update("user", "Processing_value", $id_user, "id", $from_id);
    sendmessage($from_id, "📌 حداکثر مبلغی که کاربر می توانید موجودی  اش در زمان خرید منفی شود را ارسال نمایید
توجه : عدد بدون خط تیره یا نماد منفی باشد
در صورتی که می خواهید کاربر نامحدود خریداری کند عدد 0 ارسال کنید", $backadmin, 'HTML');
    step('getmaxbuyagent', $from_id);
} elseif ($user['step'] == "getmaxbuyagent") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "تغییرات با موفقیت اعمال شد", $keyboardadmin, 'HTML');
    update("user", "maxbuyagent", $text, "id", $user['Processing_value']);
    step('home', $from_id);
} elseif ($datain == "searchorder") {
    sendmessage($from_id, $textbotlang['Admin']['order']['vieworderusername'], $backadmin, 'HTML');
    step('GetusernameconfigAndOrdedrs', $from_id);
} elseif ($user['step'] == "GetusernameconfigAndOrdedrs" || strpos($text, "/config ") !== false || preg_match('/manageinvoice_(\w+)/', $datain, $datagetr)) {
    if ($user['step'] == "GetusernameconfigAndOrdedrs") {
        $usernameconfig = $text;
        $sql = "SELECT * FROM invoice WHERE username LIKE CONCAT('%', :username, '%') OR note  LIKE CONCAT('%', :notes, '%')";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernameconfig, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $usernameconfig, PDO::PARAM_STR);
    } elseif ($text[0] == "/") {
        $usernameconfig = explode(" ", $text)[1];
        $sql = "SELECT * FROM invoice WHERE username LIKE CONCAT('%', :username, '%') OR note  LIKE CONCAT('%', :notes, '%')";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernameconfig, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $usernameconfig, PDO::PARAM_STR);
    } else {
        $usernameconfig = select("invoice", "*", "id_invoice", $datagetr[1], "select")['username'];
        $sql = "SELECT * FROM invoice WHERE username = :username OR note  = :notes";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernameconfig, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $usernameconfig, PDO::PARAM_STR);
    }
    $stmt->execute();
    step("home", $from_id);
    if ($stmt->rowCount() > 1) {
        $keyboardlists = [
            'inline_keyboard' => [],
        ];
        $keyboardlists['inline_keyboard'][] = [
            ['text' => "عملیات", 'callback_data' => "action"],
            ['text' => "وضعیت سرویس", 'callback_data' => "Status"],
            ['text' => "نام کاربری", 'callback_data' => "username"],
        ];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "مشاهده اطلاعات",
                    'callback_data' => "manageinvoice_" . $row['id_invoice']
                ],
                [
                    'text' => $row['Status'],
                    'callback_data' => "username"
                ],
                [
                    'text' => $row['username'],
                    'callback_data' => $row['username']
                ],
            ];
        }
        $keyboardlists = json_encode($keyboardlists);
        sendmessage($from_id, "⚠️ بیشتر از یک سرویس یافت از لیست زیر سرویس صحیح را انتخاب کنید", $keyboardlists, 'HTML');
        return;
    }
    $OrderUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$OrderUser) {
        sendmessage($from_id, $textbotlang['Admin']['order']['notfound'], null, 'HTML');
        return;
    }
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "♻️ بروزرسانی", 'callback_data' => "manageinvoice_" . $OrderUser['id_invoice']],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => $textbotlang['Admin']['ManageUser']['removeservice'], 'callback_data' => "removeservice-" . $OrderUser['id_invoice']],
        ['text' => $textbotlang['Admin']['ManageUser']['removeserviceandback'], 'callback_data' => "removeserviceandback-" . $OrderUser['id_invoice']],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "🗑 حذف کامل سرویس", 'callback_data' => "removefull-" . $OrderUser['id_invoice']],
    ];
    if (isset($OrderUser['time_sell'])) {
        $datatime = jdate('Y/m/d H:i:s', $OrderUser['time_sell']);
    } else {
        $datatime = $textbotlang['Admin']['ManageUser']['dataorder'];
    }
    if ($OrderUser['name_product'] == "سرویس تست") {
        $OrderUser['Service_time'] = $OrderUser['Service_time'] . "ساعته";
        $OrderUser['Volume'] = $OrderUser['Volume'] . "مگابایت";
    } else {
        $OrderUser['Service_time'] = $OrderUser['Service_time'] . "روزه";
        $OrderUser['Volume'] = $OrderUser['Volume'] . "گیگابایت";
    }
    $stmt = $pdo->prepare("SELECT value FROM service_other WHERE username = :username AND type = 'extend_user' AND status = 'paid' ORDER BY time DESC LIMIT 20");
    $stmt->execute([
        ':username' => $OrderUser['username'],
    ]);
    if ($stmt->rowCount() != 0) {
        $service_other = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!($service_other == false || !(is_string($service_other['value']) && is_array(json_decode($service_other['value'], true))))) {
            $service_other = json_decode($service_other['value'], true);
            $codeproduct = select("product", "name_product", "code_product", $service_other['code_product'], "select");
            if ($codeproduct != false) {
                $OrderUser['name_product'] = $codeproduct['name_product'];
                $OrderUser['Volume'] = $codeproduct['Volume_constraint'];
                $OrderUser['Service_time'] = $codeproduct['Service_time'];
            }
        }
    }
    $text_order = "
🛒 شماره سفارش  :  <code>{$OrderUser['id_invoice']}</code>
🛒  وضعیت سفارش در ربات : <code>{$OrderUser['Status']}</code>
🙍‍♂️ شناسه کاربر : <code>{$OrderUser['id_user']}</code>
👤 نام کاربری اشتراک :  <code>{$OrderUser['username']}</code> 
📍 موقعیت سرویس :  {$OrderUser['Service_location']}
🛍 نام محصول :  {$OrderUser['name_product']}
💰 قیمت پرداختی سرویس : {$OrderUser['price_product']} تومان
⚜️ حجم سرویس خریداری شده : {$OrderUser['Volume']}
⏳ زمان سرویس خریداری شده : {$OrderUser['Service_time']} 
📆 تاریخ خرید : $datatime  
";
    $DataUserOut = $ManagePanel->DataUser($OrderUser['Service_location'], $OrderUser['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        $keyboard_json = json_encode($keyboardlists);
        sendmessage($from_id, "کاربر در پنل وجود ندارد", $keyboardadmin, 'html');
        sendmessage($from_id, $text_order, $keyboard_json, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($DataUserOut['online_at'] == "online") {
        $lastonline = 'آنلاین';
    } elseif ($DataUserOut['online_at'] == "offline") {
        $lastonline = 'آفلاین';
    } else {
        if (isset($DataUserOut['online_at']) && $DataUserOut['online_at'] !== null) {
            $dateString = $DataUserOut['online_at'];
            $lastonline = jdate('Y/m/d H:i:s', strtotime($dateString));
        } else {
            $lastonline = "متصل نشده";
        }
    }
    #-------------status----------------#
    $status = $DataUserOut['status'];
    $status_var = [
        'active' => $textbotlang['users']['stateus']['active'],
        'limited' => $textbotlang['users']['stateus']['limited'],
        'disabled' => $textbotlang['users']['stateus']['disabled'],
        'expired' => $textbotlang['users']['stateus']['expired'],
        'on_hold' => $textbotlang['users']['stateus']['on_hold'],
        'Unknown' => $textbotlang['users']['stateus']['Unknown'],
        'deactivev' => $textbotlang['users']['stateus']['disabled'],
    ][$status];
    #--------------[ expire ]---------------#
    $expirationDate = $DataUserOut['expire'] ? jdate('Y/m/d', $DataUserOut['expire']) : $textbotlang['users']['stateus']['Unlimited'];
    #-------------[ data_limit ]----------------#
    $LastTraffic = $DataUserOut['data_limit'] ? formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['stateus']['Unlimited'];
    #---------------[ RemainingVolume ]--------------#
    $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : "نامحدود";
    #---------------[ used_traffic ]--------------#
    $usedTrafficGb = $DataUserOut['used_traffic'] ? formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['stateus']['Notconsumed'];
    #--------------[ day ]---------------#
    $timeDiff = $DataUserOut['expire'] - time();
    $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['stateus']['day'] : $textbotlang['users']['stateus']['Unlimited'];
    #--------------[ subsupdate ]---------------#
    $lastupdate = "";
    if ($DataUserOut['sub_updated_at'] !== null) {
        $sub_updated = $DataUserOut['sub_updated_at'];
        $dateTime = new DateTime($sub_updated, new DateTimeZone('UTC'));
        $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
        $lastupdate = jdate('Y/m/d H:i:s', $dateTime->getTimestamp());
    }
    $limitValue = isset($DataUserOut['data_limit']) ? (float) $DataUserOut['data_limit'] : 0;
    $usedTrafficValue = isset($DataUserOut['used_traffic']) ? (float) $DataUserOut['used_traffic'] : 0;
    $Percent = safe_divide(($limitValue - $usedTrafficValue) * 100, $limitValue, 100);
    if ($Percent < 0) {
        $Percent = -$Percent;
    }
    $Percent = round($Percent, 2);
    $text_order .= "
  
 وضعیت سرویس : $status_var
        
🔋 حجم سرویس : $LastTraffic
📥 حجم مصرفی : $usedTrafficGb
💢 حجم باقی مانده : $RemainingVolume ($Percent%)

📅 فعال تا تاریخ : $expirationDate ($day)

لینک اشتراک کاربر : 
<code>{$DataUserOut['subscription_url']}</code>

📶 اخرین زمان اتصال  : $lastonline
🔄 اخرین زمان آپدیت لینک اشتراک  : $lastupdate
#️⃣ کلاینت متصل شده :<code>{$DataUserOut['sub_last_user_agent']}</code>";
    if ($DataUserOut['status'] == "active") {
        $namestatus = '❌ خاموش کردن اکانت';
    } else {
        $namestatus = '💡 روشن کردن اکانت';
    }
    $keyboardlists['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['extend']['title'], 'callback_data' => 'extendadmin_' . $OrderUser['id_invoice']],
        ['text' => $textbotlang['users']['stateus']['config'], 'callback_data' => 'config_' . $OrderUser['id_invoice']],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => $namestatus, 'callback_data' => 'changestatusadmin_' . $OrderUser['id_invoice']],
    ];
    $keyboard_json = json_encode($keyboardlists);
    sendmessage($from_id, $text_order, $keyboard_json, 'HTML');
    $stmt = $pdo->prepare("SELECT * FROM service_other s WHERE username = :username AND (status = 'paid' OR status IS NULL)");
    $stmt->execute([':username' => $usernameconfig]);
    $list_service = $stmt->fetchAll();
    if ($list_service) {
        foreach ($list_service as $extend) {
            $extend_type = [
                'extend_user' => "تمدید",
                'extend_user_by_admin' => 'تمدید شده توسط ادمین',
                'extra_user' => "حجم اضافه",
                "extra_time_user" => "زمان اضافه",
                "transfertouser" => "انتقال به حساب دیگر",
                "extends_not_user" => "تمدید از نوع نبودن یوزر در لیست",
                "change_location" => "تغییر لوکیشن",
                'gift_time' => 'هدیه همگانی زمان',
                'gift_volume' => 'هدیه همگانی حجم'
            ][$extend['type']];
            $time_jalali = jdate('Y/m/d H:i:s', strtotime($extend['time']));

            $extendtext = "
📌 گزارش سرویس 
🔗  نوع سرویس : $extend_type
🕰 زمان انجام سرویس : {$extend['time']} \n\n($time_jalali)
💰مبلغ انجام سرویس : {$extend['price']}
👤 آیدی عددی کاربر : {$extend['id_user']}
👤 نام کاربری کانفیگ: {$extend['username']}";
            sendmessage($from_id, $extendtext, null, 'HTML');
        }
    }
    step('home', $from_id);
} elseif ($text == "🛒 وضعیت قابلیت های فروشگاه" && $adminrulecheck['rule'] == "administrator") {
    $setting = select("setting", "*", null, null, "select") ?? [];

    $marzbanstatusextraRow = select("shopSetting", "*", "Namevalue", "statusextra", "select") ?? [];
    $marzbandirectpayRow = select("shopSetting", "*", "Namevalue", "statusdirectpabuy", "select") ?? [];
    $statustimeextraRow = select("shopSetting", "*", "Namevalue", "statustimeextra", "select") ?? [];
    $statusdisorderRow = select("shopSetting", "*", "Namevalue", "statusdisorder", "select") ?? [];
    $statuschangeserviceRow = select("shopSetting", "*", "Namevalue", "statuschangeservice", "select") ?? [];
    $statusshowpriceRow = select("shopSetting", "*", "Namevalue", "statusshowprice", "select") ?? [];
    $statusshowconfigRow = select("shopSetting", "*", "Namevalue", "configshow", "select") ?? [];
    $statusremoveserveiceRow = select("shopSetting", "*", "Namevalue", "backserviecstatus", "select") ?? [];

    $marzbanstatusextra = $marzbanstatusextraRow['value'] ?? 'offextra';
    $marzbandirectpay = $marzbandirectpayRow['value'] ?? 'offdirectbuy';
    $statustimeextra = $statustimeextraRow['value'] ?? 'offtimeextraa';
    $statusdisorder = $statusdisorderRow['value'] ?? 'offdisorder';
    $statuschangeservice = $statuschangeserviceRow['value'] ?? 'offstatus';
    $statusshowprice = $statusshowpriceRow['value'] ?? 'offshowprice';
    $statusshowconfig = $statusshowconfigRow['value'] ?? 'offconfig';
    $statusremoveserveice = $statusremoveserveiceRow['value'] ?? 'off';

    $categoryStatusGeneralKey = $setting['statuscategorygenral'] ?? 'offcategorys';
    if (!in_array($categoryStatusGeneralKey, ['oncategorys', 'offcategorys'], true)) {
        $categoryStatusGeneralKey = 'offcategorys';
    }

    $categoryStatusKey = $setting['statuscategory'] ?? 'offcategory';
    if (!in_array($categoryStatusKey, ['oncategory', 'offcategory'], true)) {
        $categoryStatusKey = 'offcategory';
    }

    $name_status_extra_Vloume = [
        'onextra' => $textbotlang['Admin']['Status']['statuson'],
        'offextra' => $textbotlang['Admin']['Status']['statusoff']
    ][$marzbanstatusextra] ?? $textbotlang['Admin']['Status']['statusoff'];
    $name_status_paydirect = [
        'ondirectbuy' => $textbotlang['Admin']['Status']['statuson'],
        'offdirectbuy' => $textbotlang['Admin']['Status']['statusoff']
    ][$marzbandirectpay] ?? $textbotlang['Admin']['Status']['statusoff'];
    $name_status_timeextra = [
        'ontimeextraa' => $textbotlang['Admin']['Status']['statuson'],
        'offtimeextraa' => $textbotlang['Admin']['Status']['statusoff']
    ][$statustimeextra] ?? $textbotlang['Admin']['Status']['statusoff'];
    $name_status_disorder = [
        'ondisorder' => $textbotlang['Admin']['Status']['statuson'],
        'offdisorder' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusdisorder] ?? $textbotlang['Admin']['Status']['statusoff'];
    $categorygenral = [
        'oncategorys' => $textbotlang['Admin']['Status']['statuson'],
        'offcategorys' => $textbotlang['Admin']['Status']['statusoff']
    ][$categoryStatusGeneralKey];
    $statustextchange = [
        'onstatus' => $textbotlang['Admin']['Status']['statuson'],
        'offstatus' => $textbotlang['Admin']['Status']['statusoff']
    ][$statuschangeservice] ?? $textbotlang['Admin']['Status']['statusoff'];
    $statusshowpricestext = [
        'onshowprice' => $textbotlang['Admin']['Status']['statuson'],
        'offshowprice' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusshowprice] ?? $textbotlang['Admin']['Status']['statusoff'];
    $statusshowconfigtext = [
        'onconfig' => $textbotlang['Admin']['Status']['statuson'],
        'offconfig' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusshowconfig] ?? $textbotlang['Admin']['Status']['statusoff'];
    $statusbackremovetext = [
        'on' => $textbotlang['Admin']['Status']['statuson'],
        'off' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusremoveserveice] ?? $textbotlang['Admin']['Status']['statusoff'];
    $name_status_categorytime = [
        'oncategory' => $textbotlang['Admin']['Status']['statuson'],
        'offcategory' => $textbotlang['Admin']['Status']['statusoff']
    ][$categoryStatusKey];
    $Bot_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['Status']['statussubject'], 'callback_data' => "subjectde"],
                ['text' => $textbotlang['Admin']['Status']['subject'], 'callback_data' => "subject"],
            ],
            [
                ['text' => $name_status_extra_Vloume, 'callback_data' => "editshops-extravolunme-$marzbanstatusextra"],
                ['text' => $textbotlang['Admin']['Status']['statusvolumeextra'], 'callback_data' => "extravolunme"],
            ],
            [
                ['text' => $name_status_paydirect, 'callback_data' => "editshops-paydirect-$marzbandirectpay"],
                ['text' => $textbotlang['Admin']['Status']['paydirect'], 'callback_data' => "paydirect"],
            ],
            [
                ['text' => $name_status_timeextra, 'callback_data' => "editshops-statustimeextra-$statustimeextra"],
                ['text' => $textbotlang['Admin']['Status']['statustimeextra'], 'callback_data' => "statustimeextra"],
            ],
            [
                ['text' => $name_status_disorder, 'callback_data' => "editshops-disorderss-$statusdisorder"],
                ['text' => "⚠️ ارسال گزارش اختلال", 'callback_data' => "disorderss"],
            ],
            [
                ['text' => $categorygenral, 'callback_data' => "editshops-categroygenral-" . $setting['statuscategorygenral']],
                ['text' => "🐛 دسته بندی ", 'callback_data' => "categroygenral"],
            ],
            [
                ['text' => $name_status_categorytime, 'callback_data' => "editshops-categorytime-{$setting['statuscategory']}"],
                ['text' => $textbotlang['Admin']['Status']['statuscategorytime'], 'callback_data' => "statuscategorytime"],
            ],
            [
                ['text' => $statustextchange, 'callback_data' => "editshops-changgestatus-" . $statuschangeservice],
                ['text' => "❓وضعیت غیرفعال کردن اکانت", 'callback_data' => "changgestatus"],
            ],
            [
                ['text' => $statusshowpricestext, 'callback_data' => "editshops-showprice-" . $statusshowprice],
                ['text' => "💰 نمایش قیمت محصول", 'callback_data' => "showprice"],
            ],
            [
                ['text' => $statusshowconfigtext, 'callback_data' => "editshops-showconfig-" . $statusshowconfig],
                ['text' => "🔗 دکمه دریافت کانفیگ", 'callback_data' => "config"],
            ],
            [
                ['text' => $statusbackremovetext, 'callback_data' => "editshops-removeservicebackbtn-" . $statusremoveserveice],
                ['text' => "💎 دکمه بازگشت وجه", 'callback_data' => "removeservicebackbtn"],
            ],
            [
                ['text' => "❌ بستن", 'callback_data' => 'close_stat']
            ],
        ]
    ]);
    sendmessage($from_id, $textbotlang['Admin']['Status']['BotTitle'], $Bot_Status, 'HTML');
} elseif (preg_match('/^editshops-(.*)-(.*)/', $datain, $dataget)) {
    $type = $dataget[1];
    $value = $dataget[2];
    if ($type == "extravolunme") {
        if ($value == "onextra") {
            $valuenew = "offextra";
        } else {
            $valuenew = "onextra";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "statusextra");
    } elseif ($type == "paydirect") {
        if ($value == "ondirectbuy") {
            $valuenew = "offdirectbuy";
        } else {
            $valuenew = "ondirectbuy";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "statusdirectpabuy");
    } elseif ($type == "statustimeextra") {
        if ($value == "ontimeextraa") {
            $valuenew = "offtimeextraa";
        } else {
            $valuenew = "ontimeextraa";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "statustimeextra");
    } elseif ($type == "disorderss") {
        if ($value == "ondisorder") {
            $valuenew = "offdisorder";
        } else {
            $valuenew = "ondisorder";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "statusdisorder");
    } elseif ($type == "categroygenral") {
        if ($value == "oncategorys") {
            $valuenew = "offcategorys";
        } else {
            $valuenew = "oncategorys";
        }
        update("setting", "statuscategorygenral", $valuenew, null, null);
    } elseif ($type == "changgestatus") {
        if ($value == "onstatus") {
            $valuenew = "offstatus";
        } else {
            $valuenew = "onstatus";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "statuschangeservice");
    } elseif ($type == "showprice") {
        if ($value == "onshowprice") {
            $valuenew = "offshowprice";
        } else {
            $valuenew = "onshowprice";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "statusshowprice");
    } elseif ($type == "showconfig") {
        if ($value == "onconfig") {
            $valuenew = "offconfig";
        } else {
            $valuenew = "onconfig";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "configshow");
    } elseif ($type == "removeservicebackbtn") {
        if ($value == "on") {
            $valuenew = "off";
        } else {
            $valuenew = "on";
        }
        update("shopSetting", "value", $valuenew, "Namevalue", "backserviecstatus");
    } elseif ($type == "categorytime") {
        if ($value == "oncategory") {
            $valuenew = "offcategory";
        } else {
            $valuenew = "oncategory";
        }
        update("setting", "statuscategory", $valuenew);
    }
    $setting = select("setting", "*", null, null, "select") ?? [];

    $marzbanstatusextraRow = select("shopSetting", "*", "Namevalue", "statusextra", "select") ?? [];
    $marzbandirectpayRow = select("shopSetting", "*", "Namevalue", "statusdirectpabuy", "select") ?? [];
    $statustimeextraRow = select("shopSetting", "*", "Namevalue", "statustimeextra", "select") ?? [];
    $statusdisorderRow = select("shopSetting", "*", "Namevalue", "statusdisorder", "select") ?? [];
    $statuschangeserviceRow = select("shopSetting", "*", "Namevalue", "statuschangeservice", "select") ?? [];
    $statusshowpriceRow = select("shopSetting", "*", "Namevalue", "statusshowprice", "select") ?? [];
    $statusshowconfigRow = select("shopSetting", "*", "Namevalue", "configshow", "select") ?? [];
    $statusremoveserveiceRow = select("shopSetting", "*", "Namevalue", "backserviecstatus", "select") ?? [];

    $marzbanstatusextra = $marzbanstatusextraRow['value'] ?? 'offextra';
    $marzbandirectpay = $marzbandirectpayRow['value'] ?? 'offdirectbuy';
    $statustimeextra = $statustimeextraRow['value'] ?? 'offtimeextraa';
    $statusdisorder = $statusdisorderRow['value'] ?? 'offdisorder';
    $statuschangeservice = $statuschangeserviceRow['value'] ?? 'offstatus';
    $statusshowprice = $statusshowpriceRow['value'] ?? 'offshowprice';
    $statusshowconfig = $statusshowconfigRow['value'] ?? 'offconfig';
    $statusremoveserveice = $statusremoveserveiceRow['value'] ?? 'off';

    $categoryStatusGeneralKey = $setting['statuscategorygenral'] ?? 'offcategorys';
    if (!in_array($categoryStatusGeneralKey, ['oncategorys', 'offcategorys'], true)) {
        $categoryStatusGeneralKey = 'offcategorys';
    }

    $categoryStatusKey = $setting['statuscategory'] ?? 'offcategory';
    if (!in_array($categoryStatusKey, ['oncategory', 'offcategory'], true)) {
        $categoryStatusKey = 'offcategory';
    }

    $name_status_extra_Vloume = [
        'onextra' => $textbotlang['Admin']['Status']['statuson'],
        'offextra' => $textbotlang['Admin']['Status']['statusoff']
    ][$marzbanstatusextra] ?? $textbotlang['Admin']['Status']['statusoff'];
    $name_status_paydirect = [
        'ondirectbuy' => $textbotlang['Admin']['Status']['statuson'],
        'offdirectbuy' => $textbotlang['Admin']['Status']['statusoff']
    ][$marzbandirectpay] ?? $textbotlang['Admin']['Status']['statusoff'];
    $name_status_timeextra = [
        'ontimeextraa' => $textbotlang['Admin']['Status']['statuson'],
        'offtimeextraa' => $textbotlang['Admin']['Status']['statusoff']
    ][$statustimeextra] ?? $textbotlang['Admin']['Status']['statusoff'];
    $name_status_disorder = [
        'ondisorder' => $textbotlang['Admin']['Status']['statuson'],
        'offdisorder' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusdisorder] ?? $textbotlang['Admin']['Status']['statusoff'];
    $categorygenral = [
        'oncategorys' => $textbotlang['Admin']['Status']['statuson'],
        'offcategorys' => $textbotlang['Admin']['Status']['statusoff']
    ][$categoryStatusGeneralKey];
    $statustextchange = [
        'onstatus' => $textbotlang['Admin']['Status']['statuson'],
        'offstatus' => $textbotlang['Admin']['Status']['statusoff']
    ][$statuschangeservice] ?? $textbotlang['Admin']['Status']['statusoff'];
    $statusshowpricestext = [
        'onshowprice' => $textbotlang['Admin']['Status']['statuson'],
        'offshowprice' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusshowprice] ?? $textbotlang['Admin']['Status']['statusoff'];
    $statusshowconfigtext = [
        'onconfig' => $textbotlang['Admin']['Status']['statuson'],
        'offconfig' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusshowconfig] ?? $textbotlang['Admin']['Status']['statusoff'];
    $statusbackremovetext = [
        'on' => $textbotlang['Admin']['Status']['statuson'],
        'off' => $textbotlang['Admin']['Status']['statusoff']
    ][$statusremoveserveice] ?? $textbotlang['Admin']['Status']['statusoff'];
    $name_status_categorytime = [
        'oncategory' => $textbotlang['Admin']['Status']['statuson'],
        'offcategory' => $textbotlang['Admin']['Status']['statusoff']
    ][$categoryStatusKey];
    $Bot_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['Status']['statussubject'], 'callback_data' => "subjectde"],
                ['text' => $textbotlang['Admin']['Status']['subject'], 'callback_data' => "subject"],
            ],
            [
                ['text' => $name_status_extra_Vloume, 'callback_data' => "editshops-extravolunme-$marzbanstatusextra"],
                ['text' => $textbotlang['Admin']['Status']['statusvolumeextra'], 'callback_data' => "extravolunme"],
            ],
            [
                ['text' => $name_status_paydirect, 'callback_data' => "editshops-paydirect-$marzbandirectpay"],
                ['text' => $textbotlang['Admin']['Status']['paydirect'], 'callback_data' => "paydirect"],
            ],
            [
                ['text' => $name_status_timeextra, 'callback_data' => "editshops-statustimeextra-$statustimeextra"],
                ['text' => $textbotlang['Admin']['Status']['statustimeextra'], 'callback_data' => "statustimeextra"],
            ],
            [
                ['text' => $name_status_disorder, 'callback_data' => "editshops-disorderss-$statusdisorder"],
                ['text' => "⚠️ ارسال گزارش اختلال", 'callback_data' => "disorderss"],
            ],
            [
                ['text' => $categorygenral, 'callback_data' => "editshops-categroygenral-" . $setting['statuscategorygenral']],
                ['text' => "🐛 دسته بندی ", 'callback_data' => "categroygenral"],
            ],
            [
                ['text' => $name_status_categorytime, 'callback_data' => "editshops-categorytime-{$setting['statuscategory']}"],
                ['text' => $textbotlang['Admin']['Status']['statuscategorytime'], 'callback_data' => "statuscategorytime"],
            ],
            [
                ['text' => $statustextchange, 'callback_data' => "editshops-changgestatus-" . $statuschangeservice],
                ['text' => "❓وضعیت غیرفعال کردن اکانت", 'callback_data' => "changgestatus"],
            ],
            [
                ['text' => $statusshowpricestext, 'callback_data' => "editshops-showprice-" . $statusshowprice],
                ['text' => "💰 نمایش قیمت محصول", 'callback_data' => "showprice"],
            ],
            [
                ['text' => $statusshowconfigtext, 'callback_data' => "editshops-showconfig-" . $statusshowconfig],
                ['text' => "🔗 دکمه دریافت کانفیگ", 'callback_data' => "config"],
            ],
            [
                ['text' => $statusbackremovetext, 'callback_data' => "editshops-removeservicebackbtn-" . $statusremoveserveice],
                ['text' => "💎 دکمه بازگشت وجه", 'callback_data' => "removeservicebackbtn"],
            ],
            [
                ['text' => "❌ بستن", 'callback_data' => 'close_stat']
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['BotTitle'], $Bot_Status);
} elseif ($text == "🪪 خروجی گرفتن اطلاعات" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboardexportdata, 'HTML');
} elseif ($text == "🕚 تنظیمات کرون جاب" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $setting_panel, 'HTML');
} elseif ($text == "خروجی کاربران" && $adminrulecheck['rule'] == "administrator") {
    $counttable = select("user", "*", null, null, "count");
    if ($counttable == 0) {
        sendmessage($from_id, "❌ دیتایی برای ارسال خروجی وجود ندارد", null, 'HTML');
        return;
    }
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sql = "SELECT * FROM user";
    $result = $connect->query($sql);

    $col = 1;
    $headers = array_keys($result->fetch_assoc());
    foreach ($headers as $header) {
        $sheet->setCellValue([$col, 1], $header);
        $col++;
    }
    mysqli_data_seek($result, 0);

    $row = 2;
    while ($row_data = $result->fetch_assoc()) {
        $col = 1;
        foreach ($row_data as $value) {
            $sheet->setCellValue([$col, $row], $value);
            $col++;
        }
        $row++;
    }
    $date = date("Y-m-d");
    $filename = "users_{$date}.xlsx";
    $writer = new Xlsx($spreadsheet);
    $writer->save($filename);
    sendDocument($from_id, $filename, "🪪 خروجی دیتای کاربران");
    unlink($filename);
} elseif ($text == "خروجی سفارشات" && $adminrulecheck['rule'] == "administrator") {
    $counttable = select("invoice", "*", null, null, "count");
    if ($counttable == 0) {
        sendmessage($from_id, "❌ دیتایی برای ارسال خروجی وجود ندارد", null, 'HTML');
        return;
    }
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sql = "SELECT * FROM invoice";
    $result = $connect->query($sql);

    $col = 1;
    $headers = array_keys($result->fetch_assoc());
    foreach ($headers as $header) {
        $sheet->setCellValue([$col, 1], $header);
        $col++;
    }
    mysqli_data_seek($result, 0);

    $row = 2;
    while ($row_data = $result->fetch_assoc()) {
        $col = 1;
        foreach ($row_data as $value) {
            $sheet->setCellValue([$col, $row], $value);
            $col++;
        }
        $row++;
    }
    $date = date("Y-m-d");
    $filename = "invoice_{$date}.xlsx";
    $writer = new Xlsx($spreadsheet);
    $writer->save($filename);
    sendDocument($from_id, $filename, "🪪 خروجی سفارشات کاربران");
    unlink($filename);
} elseif ($text == "خروجی گرفتن پرداخت ها" && $adminrulecheck['rule'] == "administrator") {
    $counttable = select("Payment_report", "*", null, null, "count");
    if ($counttable == 0) {
        sendmessage($from_id, "❌ دیتایی برای ارسال خروجی وجود ندارد", null, 'HTML');
        return;
    }
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sql = "SELECT * FROM Payment_report";
    $result = $connect->query($sql);

    $col = 1;
    $headers = array_keys($result->fetch_assoc());
    foreach ($headers as $header) {
        $sheet->setCellValue([$col, 1], $header);
        $col++;
    }
    mysqli_data_seek($result, 0);

    $row = 2;
    while ($row_data = $result->fetch_assoc()) {
        $col = 1;
        foreach ($row_data as $value) {
            $sheet->setCellValue([$col, $row], $value);
            $col++;
        }
        $row++;
    }
    $date = date("Y-m-d");
    $filename = "Payment_report_{$date}.xlsx";
    $writer = new Xlsx($spreadsheet);
    $writer->save($filename);
    sendDocument($from_id, $filename, "🪪 خروجی پرداختی های کاربران");
    unlink($filename);
} elseif (preg_match('/rejectremoceserviceadmin-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $invoice = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $requestcheck = select("cancel_service", "*", "username", $invoice['username'], "select");
    if ($requestcheck['status'] == "accept" || $requestcheck['status'] == "reject") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    step("descriptionsrequsts", $from_id);
    update("user", "Processing_value", $requestcheck['username'], "id", $from_id);
    sendmessage($from_id, $textbotlang['users']['stateus']['requestadmin'], $backuser, 'HTML');
} elseif ($user['step'] == "descriptionsrequsts") {
    sendmessage($from_id, $textbotlang['users']['stateus']['accecptreqests'], $keyboardadmin, 'HTML');
    $nameloc = select("invoice", "*", "username", $user['Processing_value'], "select");
    update("cancel_service", "status", "reject", "username", $user['Processing_value']);
    update("cancel_service", "description", $text, "username", $user['Processing_value']);
    step("home", $from_id);
    sendmessage($nameloc['id_user'], "❌ کاربری گرامی درخواست حذف شما با نام کاربری  {$user['Processing_value']} موافقت نگردید.
        
        دلیل عدم تایید : $text", null, 'HTML');
} elseif (preg_match('/remoceserviceadmin-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $invoice = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $requestcheck = select("cancel_service", "*", "username", $invoice['username'], "select");
    if ($requestcheck['status'] == "accept" || $requestcheck['status'] == "reject") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $nameloc = select("invoice", "*", "username", $requestcheck['username'], "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $requestcheck['username']);
    $stmt = $pdo->prepare("SELECT  SUM(price) FROM service_other WHERE username = :username AND type != 'change_location' AND type != 'extend_user' LIMIT 1");
    $stmt->bindParam(':username', $nameloc['username']);
    $stmt->execute();
    $sumproduct = $stmt->fetch(PDO::FETCH_ASSOC);
    if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") {
        sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
        step('home', $from_id);
        return;
    }
    if ($DataUserOut['data_limit'] == null && $DataUserOut['expire'] == null) {
        sendmessage($from_id, "❌ به دلیل نامحدود بودن حجم و زمان امکان حذف سرویس وجود ندارد. ", null, 'html');
        step('home', $from_id);
        return;
    }
    if ($DataUserOut['status'] == "on_hold") {
        $pricelast = $invoice['price_product'];
    } elseif ($DataUserOut['data_limit'] == null) {
        $serviceTime = (float) ($nameloc['Service_time'] ?? 0);
        if ($serviceTime > 0) {
            $pricetime = safe_divide($nameloc['price_product'], $serviceTime, 0) + intval($sumproduct['SUM(price)']);
            $pricelast = (($DataUserOut['expire'] - time()) / 86400) * $pricetime;
        } else {
            $pricelast = 0;
        }
    } elseif ($DataUserOut['expire'] == null) {
        $dataLimit = isset($DataUserOut['data_limit']) ? (float) $DataUserOut['data_limit'] : 0;
        if ($dataLimit > 0) {
            $volumelefts = ($dataLimit - (float) ($DataUserOut['used_traffic'] ?? 0)) / pow(1024, 3);
            $volumeDivisor = $dataLimit / pow(1024, 3);
            $volumeleft = $volumeDivisor > 0 ? safe_divide($volumelefts, $volumeDivisor, 0) : 0;
            $pricelast = round($volumeleft * ($nameloc['price_product'] + intval($sumproduct['SUM(price)'])), 2);
        } else {
            $pricelast = 0;
        }
    } else {
        $serviceTime = (float) ($nameloc['Service_time'] ?? 0);
        $dataLimit = isset($DataUserOut['data_limit']) ? (float) $DataUserOut['data_limit'] : 0;
        $volumeDivisor = $dataLimit / pow(1024, 3);
        if ($serviceTime > 0 && $volumeDivisor > 0) {
            $timeleft = safe_divide(round(($DataUserOut['expire'] - time()) / 86400, 0), $serviceTime, 0);
            $volumelefts = ($dataLimit - (float) ($DataUserOut['used_traffic'] ?? 0)) / pow(1024, 3);
            $volumeleft = safe_divide($volumelefts, $volumeDivisor, 0);
            $pricelast = round($timeleft * $volumeleft * ($nameloc['price_product'] + intval($sumproduct['SUM(price)'])), 2);
        } else {
            $pricelast = 0;
        }
    }
    $pricelast = intval($pricelast);
    if (intval($pricelast) != 0) {
        $Balance_id_cancel = select("user", "*", "id", $nameloc['id_user'], "select");
        $Balance_id_cancel_fee = intval($Balance_id_cancel['Balance']) + intval($pricelast);
        update("user", "Balance", $Balance_id_cancel_fee, "id", $nameloc['id_user']);
        sendmessage($nameloc['id_user'], "💰کاربر گرامی مبلغ $pricelast تومان به موجودی شما اضافه گردید.", null, 'HTML');
    }
    $ManagePanel->RemoveUser($nameloc['Service_location'], $requestcheck['username']);
    update("cancel_service", "status", "accept", "username", $requestcheck['username']);
    update("invoice", "status", "removedbyadmin", "username", $requestcheck['username']);
    sendmessage($from_id, "❌ مبلغ $pricelast تومان به موجودی کاربر اضافه گردید.", null, 'HTML');
    sendmessage($nameloc['id_user'], "✅ کاربری گرامی درخواست حذف شما با نام کاربری  {$nameloc['username']} موافقت گردید.", null, 'HTML');
    $text_report = "⭕️ یک ادمین سرویس کاربر که درخواست حذف داشت را تایید کرد
        
اطلاعات کاربر تایید کننده  : 

🪪 آیدی عددی : <code>$from_id</code>
💰 مبلغ بازگشتی : $pricelast تومان
👤 نام کاربری : {$requestcheck['username']}
        آیدی عددی درخواست کننده کنسل کردن : {$nameloc['id_user']}";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/remoceserviceadminmanual-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    update("user", "Processing_value", $id_invoice, "id", $from_id);
    $invoice = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $requestcheck = select("cancel_service", "*", "username", $invoice['username'], "select");
    if ($requestcheck['status'] == "accept" || $requestcheck['status'] == "reject") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $invoice['Service_location'], "select");
    $ManagePanel->RemoveUser($invoice['Service_location'], $requestcheck['username']);
    update("cancel_service", "status", "accept", "username", $requestcheck['username']);
    update("invoice", "status", "removedbyadmin", "username", $requestcheck['username']);
    sendmessage($invoice['id_user'], "✅ کاربری گرامی درخواست حذف شما با نام کاربری  {$invoice['username']} موافقت گردید.", null, 'HTML');
    sendmessage($from_id, "📌 مبلغ  برای بازگشت وجه را ارسال نمایید", $backadmin, 'HTML');
    step("getpricebackremove", $from_id);
} elseif ($user['step'] == "getpricebackremove") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $invoice = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
    $Balance_id_cancel = select("user", "*", "id", $invoice['id_user'], "select");
    $Balance_id_cancel_fee = intval($Balance_id_cancel['Balance']) + intval($text);
    update("user", "Balance", $Balance_id_cancel_fee, "id", $invoice['id_user']);
    sendmessage($invoice['id_user'], "💰کاربر گرامی مبلغ $text تومان به موجودی شما اضافه گردید.", null, 'HTML');
    sendmessage($from_id, "✅ مبلغ با موفقیت به حساب کاربر اضافه گردید.", $keyboardadmin, 'HTML');
    $text_report = "⭕️ یک ادمین سرویس کاربر که درخواست حذف داشت را تایید کرد
        
اطلاعات کاربر تایید کننده  : 

🪪 آیدی عددی : <code>$from_id</code>
💰 مبلغ بازگشتی : $text تومان
👤 نام کاربری : {$invoice['username']}
آیدی عددی درخواست کننده کنسل کردن : {$invoice['id_user']}";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif ($datain == "cronjobs_settings" && $adminrulecheck['rule'] == "administrator") {
    if (!function_exists('buildCronJobsKeyboard')) {
        sendmessage($from_id, "امکان مدیریت کرون‌ها در این نسخه فعال نشده است.", $backadmin, 'HTML');
        return;
    }
    $cronIntro = "برای تغییر زمان‌بندی هر کرون، دکمه «⚙️ تنظیمات» همان ردیف را انتخاب کنید.";
    sendmessage($from_id, $cronIntro, buildCronJobsKeyboard(), 'HTML');
} elseif (preg_match('/^cronjob_config-([A-Za-z0-9_]+)/', $datain, $cronMatches) && $adminrulecheck['rule'] == "administrator") {
    if (!function_exists('getCronJobDefinitions') || !function_exists('loadCronSchedules') || !function_exists('describeCronSchedule')) {
        sendmessage($from_id, "امکان مدیریت کرون‌ها در این نسخه فعال نشده است.", $backadmin, 'HTML');
        return;
    }
    $jobKey = $cronMatches[1];
    $definitions = getCronJobDefinitions();
    if (!isset($definitions[$jobKey])) {
        sendmessage($from_id, "کرون انتخابی یافت نشد.", $backadmin, 'HTML');
        return;
    }
    $schedules = loadCronSchedules();
    $currentSchedule = $schedules[$jobKey] ?? $definitions[$jobKey]['default'];
    $readableSchedule = describeCronSchedule($currentSchedule);
    $definitionLabel = $definitions[$jobKey]['admin_label'];
    $isDisabled = isset($currentSchedule['unit']) && $currentSchedule['unit'] === 'disabled';
    $toggleText = $isDisabled ? '✅ فعال‌سازی کرون' : '❌ غیرفعال‌سازی کرون';
    $toggleAction = $isDisabled ? 'enable' : 'disable';

    $unitKeyboard = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "دقیقه‌ای", 'callback_data' => "cronjob_unit-{$jobKey}-minute"],
                ['text' => "ساعتی", 'callback_data' => "cronjob_unit-{$jobKey}-hour"],
                ['text' => "روزانه", 'callback_data' => "cronjob_unit-{$jobKey}-day"],
            ],
            [
                ['text' => $toggleText, 'callback_data' => "cronjob_toggle-{$jobKey}-{$toggleAction}"],
            ],
            [
                ['text' => "🔙 بازگشت", 'callback_data' => "cronjobs_settings"],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);
    $message = "⏱ زمان‌بندی فعلی «{$definitionLabel}»: {$readableSchedule}\n\nواحد مورد نظر را انتخاب کنید.";
    sendmessage($from_id, $message, $unitKeyboard, 'HTML');
} elseif (preg_match('/^cronjob_toggle-([A-Za-z0-9_]+)-(enable|disable)$/', $datain, $cronMatches) && $adminrulecheck['rule'] == "administrator") {
    if (!function_exists('getCronJobDefinitions') || !function_exists('updateCronSchedule') || !function_exists('describeCronSchedule')) {
        sendmessage($from_id, "امکان مدیریت کرون‌ها در این نسخه فعال نشده است.", $backadmin, 'HTML');
        return;
    }
    $jobKey = $cronMatches[1];
    $action = $cronMatches[2];
    $definitions = getCronJobDefinitions();
    if (!isset($definitions[$jobKey])) {
        sendmessage($from_id, "کرون انتخابی یافت نشد.", $backadmin, 'HTML');
        return;
    }

    if ($action === 'disable') {
        $newSchedule = ['unit' => 'disabled', 'value' => 1];
        $statusText = "کرون «{$definitions[$jobKey]['admin_label']}» غیرفعال شد.";
    } else {
        $newSchedule = $definitions[$jobKey]['default'] ?? ['unit' => 'minute', 'value' => 1];
        $description = describeCronSchedule($newSchedule);
        $statusText = "کرون «{$definitions[$jobKey]['admin_label']}» فعال شد. زمان‌بندی فعلی: {$description}";
    }

    if (!updateCronSchedule($jobKey, $newSchedule)) {
        sendmessage($from_id, "خطا در ذخیره‌سازی تنظیمات کرون.", $backadmin, 'HTML');
        return;
    }

    sendmessage($from_id, $statusText, buildCronJobsKeyboard(), 'HTML');
} elseif (preg_match('/^cronjob_unit-([A-Za-z0-9_]+)-(minute|hour|day)$/', $datain, $cronMatches) && $adminrulecheck['rule'] == "administrator") {
    if (!function_exists('getCronJobDefinitions')) {
        sendmessage($from_id, "امکان مدیریت کرون‌ها در این نسخه فعال نشده است.", $backadmin, 'HTML');
        return;
    }
    $jobKey = $cronMatches[1];
    $unit = $cronMatches[2];
    $definitions = getCronJobDefinitions();
    if (!isset($definitions[$jobKey])) {
        sendmessage($from_id, "کرون انتخابی یافت نشد.", $backadmin, 'HTML');
        return;
    }
    $payload = json_encode(['cron_key' => $jobKey, 'unit' => $unit], JSON_UNESCAPED_UNICODE);
    update("user", "Processing_value", $payload, "id", $from_id);
    step("cronjob_set_value", $from_id);
    $unitTitle = getCronUnitTitle($unit);
    sendmessage($from_id, "🔢 مقدار جدید (به صورت عدد) برای بازه زمانی {$unitTitle} ارسال کنید.", $backadmin, 'HTML');
} elseif ($user['step'] == "cronjob_set_value") {
    $pending = json_decode($user['Processing_value'], true);
    if (!is_array($pending) || empty($pending['cron_key']) || empty($pending['unit'])) {
        sendmessage($from_id, "درخواست نامعتبر است.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    if (!ctype_digit($text) || intval($text) < 1) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    if (!function_exists('updateCronSchedule') || !function_exists('getCronJobDefinitions') || !function_exists('describeCronSchedule')) {
        sendmessage($from_id, "امکان ذخیره‌سازی تنظیمات کرون وجود ندارد.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $definitions = getCronJobDefinitions();
    $jobKey = $pending['cron_key'];
    if (!isset($definitions[$jobKey])) {
        sendmessage($from_id, "کرون انتخابی یافت نشد.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $value = intval($text);
    if (!updateCronSchedule($jobKey, ['unit' => $pending['unit'], 'value' => $value])) {
        sendmessage($from_id, "خطا در ذخیره‌سازی تنظیمات کرون.", $backadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $schedules = loadCronSchedules();
    $currentSchedule = $schedules[$jobKey] ?? ['unit' => $pending['unit'], 'value' => $value];
    $description = describeCronSchedule($currentSchedule);
    $label = $definitions[$jobKey]['admin_label'];
    sendmessage($from_id, "✅ زمان‌بندی «{$label}» به {$description} تغییر کرد.", buildCronJobsKeyboard(), 'HTML');
    update("user", "Processing_value", "", "id", $from_id);
    step('home', $from_id);
} elseif ($datain == "settimecornremovevolume" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['cronjob']['setvolumeremove'] . $setting['cronvolumere'] . "روز", $backadmin, 'HTML');
    step("getcronvolumere", $from_id);
} elseif ($user['step'] == "getcronvolumere") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['cronjob']['changeddata'], $setting_panel, 'HTML');
    step("home", $from_id);
    update("setting", "cronvolumere", $text);
} elseif ($datain == "setting_on_holdcron" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "در این بخش باید تغیین کنید که اگر کاربر بعد از چند روز به کانفیگ خود وصل نشد و در وضعیت on_hold بود به کاربر پیام دهد" . $setting['on_hold_day'] . "روز", $backadmin, 'HTML');
    step("on_hold_day", $from_id);
} elseif ($user['step'] == "on_hold_day") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['cronjob']['changeddata'], $setting_panel, 'HTML');
    step("home", $from_id);
    update("setting", "on_hold_day", $text);
}
if ($datain == "settimecornremove" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['cronjob']['setdayremove'] . $setting['removedayc'] . "روز", $backadmin, 'HTML');
    step("getdaycron", $from_id);
} elseif ($user['step'] == "getdaycron") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['cronjob']['changeddata'], $setting_panel, 'HTML');
    step("home", $from_id);
    update("setting", "removedayc", $text);
} elseif ($text == "🌐 ثبت آدرس API ترنادو" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "urlpaymenttron", "select");
    $currentUrl = is_array($PaySetting) && isset($PaySetting['ValuePay']) ? $PaySetting['ValuePay'] : 'تنظیم نشده';
    $recommendedUrl = (defined('TRONADO_ORDER_TOKEN_ENDPOINTS') && isset(TRONADO_ORDER_TOKEN_ENDPOINTS[0]))
        ? TRONADO_ORDER_TOKEN_ENDPOINTS[0]
        : 'https://bot.tronado.cloud/api/v1/Order/GetOrderToken';
    $texttronseller = "🌐 آدرس API مورد استفاده برای اتصال به ترنادو را ارسال کنید.\n\nآدرس فعلی: {$currentUrl}\n\nℹ️ پیشنهاد ویژه برای ترنادو:\n{$recommendedUrl}";
    sendmessage($from_id, $texttronseller, $backadmin, 'HTML');
    step('urlpaymenttron', $from_id);
} elseif ($user['step'] == "urlpaymenttron") {
    $submittedUrl = trim($text);
    $oldDomain = 'tronseller.storeddownloader.fun';
    if (stripos($submittedUrl, $oldDomain) !== false) {
        $warningMessage = "⚠️ دامنه قدیمی ترنادو هنوز استفاده می‌شود. لطفاً آدرس جدید را وارد کنید.";
        sendmessage($from_id, $warningMessage, $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $trnado, 'HTML');
    update("PaySetting", "ValuePay", $submittedUrl, "NamePay", "urlpaymenttron");
    step('home', $from_id);
} elseif ($text == "✏️ ویرایش آموزش" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['Help']['SelectName'], $json_list_helpkey, 'HTML');
    step("getnameforedite", $from_id);
} elseif ($user['step'] == "getnameforedite") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $helpedit, 'HTML');
    update("user", "Processing_value", $text, "id", $from_id);
    step("home", $from_id);
} elseif ($text == "ویرایش نام" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "نام جدید را ارسال کنید", $backadmin, 'HTML');
    step('changenamehelp', $from_id);
} elseif ($user['step'] == "changenamehelp") {
    if (strlen($text) >= 150) {
        sendmessage($from_id, "❌ نام آموزش باید کمتر از 150 کاراکتر باشد", null, 'HTML');
        return;
    }
    update("help", "name_os", $text, "name_os", $user['Processing_value']);
    sendmessage($from_id, "✅ نام آموزش بروزرسانی شد", $helpedit, 'HTML');
    step('home', $from_id);
} elseif ($text == "ویرایش دسته بندی" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "دسته بندی جدید خود را ارسال کنید", $backadmin, 'HTML');
    step('changecategoryhelp', $from_id);
} elseif ($user['step'] == "changecategoryhelp") {
    if (strlen($text) >= 150) {
        sendmessage($from_id, "❌ نام آموزش باید کمتر از 150 کاراکتر باشد", null, 'HTML');
        return;
    }
    update("help", "category", $text, "name_os", $user['Processing_value']);
    sendmessage($from_id, "✅ نام دسته آموزش بروزرسانی شد", $helpedit, 'HTML');
    step('home', $from_id);
} elseif ($text == "ویرایش توضیحات" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "توضیحات جدید را ارسال کنید", $backadmin, 'HTML');
    step('changedeshelp', $from_id);
} elseif ($user['step'] == "changedeshelp") {
    update("help", "Description_os", $text, "name_os", $user['Processing_value']);
    sendmessage($from_id, "✅ توضیحات  آموزش بروزرسانی شد", $helpedit, 'HTML');
    step('home', $from_id);
} elseif ($text == "ویرایش رسانه" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "تصویر یا فیلم جدید را ارسال کنید", $backadmin, 'HTML');
    step('changemedia', $from_id);
} elseif ($user['step'] == "changemedia") {
    if ($photo) {
        if (isset($photoid))
            update("help", "Media_os", $photoid, "name_os", $user['Processing_value']);
        update("help", "type_Media_os", "photo", "name_os", $user['Processing_value']);
    } elseif ($video) {
        if (isset($videoid))
            update("help", "Media_os", $videoid, "name_os", $user['Processing_value']);
        update("help", "type_Media_os", "video", "name_os", $user['Processing_value']);
    }
    sendmessage($from_id, "✅ توضیحات  آموزش بروزرسانی شد", $helpedit, 'HTML');
    step('home', $from_id);
} elseif ($text == "💰  غیرفعالسازی  نمایش شماره کارت") {
    sendmessage($from_id, "برای تمامی کاربران غیرفعال گردید یا کاربران جدید؟
    کاربران جدید 0 
    همه کاربران 1
    2 کاربران بجز نمایندگان", null, 'HTML');
    step('showcardallusers', $from_id);
} elseif ($user['step'] == "showcardallusers") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['disableshowcardstatus'], null, 'HTML');
    if (intval($text) == "1") {
        update("user", "cardpayment", "0");
        update("setting", "showcard", "0");
    } elseif (intval($text) == 2) {
        update("user", "cardpayment", "0", "agent", "f");
        update("setting", "showcard", "0");
    } else {
        update("setting", "showcard", "0");
    }
} elseif ($text == "💰 فعالسازی نمایش شماره کارت") {
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['activeshowcardstatus'], null, 'HTML');
    update("user", "cardpayment", "1");
    update("setting", "showcard", "1");
} elseif ($text == "🔋 روش تمدید سرویس" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $Methodextend, 'HTML');
    step('updateextendmethod', $from_id);
} elseif ($user['step'] == "updateextendmethod") {
    $aarayvalid = array(
        'ریست حجم و زمان',
        'اضافه شدن زمان و حجم به ماه بعد',
        'ریست زمان و اضافه کردن حجم قبلی',
        'ریست شدن حجم و اضافه شدن زمان',
        'اضافه شدن زمان و تبدیل حجم کل به حجم باقی مانده'
    );
    if (!in_array($text, $aarayvalid)) {
        sendmessage($from_id, "❌ روش تمدید نامعتبر می باشد از لیست زیر روش تمدید درست را انتخاب کنید", null, 'HTML');
        return;
    }
    update("marzban_panel", "Methodextend", $text, "name_panel", $user['Processing_value']);
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['Algortimeextend']['SaveData']);
    step('home', $from_id);
} elseif ($text == "♻️ تایید خودکار رسید" && $adminrulecheck['rule'] == "administrator") {
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "autoconfirmcart", "select")['ValuePay'];
    if ($paymentverify == "onauto") {
        sendmessage($from_id, "❌ ابتدا تایید خودکار بدون بررسی را خاموش کنید.", null, 'HTML');
        return;
    }
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "statuscardautoconfirm", "select")['ValuePay'];
    $card_Status_auto = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $PaySetting, 'callback_data' => $PaySetting],
            ],
        ]
    ]);
    sendmessage($from_id, $textbotlang['Admin']['Status']['autoconfirmcard'], $card_Status_auto, 'HTML');
} elseif ($datain == "onautoconfirm" && $adminrulecheck['rule'] == "administrator") {
    update("PaySetting", "ValuePay", "offautoconfirm", "NamePay", "statuscardautoconfirm");
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['cardStatusOffautoconfirmcard'], null);
} elseif ($datain == "offautoconfirm" && $adminrulecheck['rule'] == "administrator") {
    update("PaySetting", "ValuePay", "onautoconfirm", "NamePay", "statuscardautoconfirm");
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['cardStatusonautoconfirmcard'], null);
} elseif ($text == "/token") {
    $secret_key = select("admin", "*", "id_admin", $from_id, "select");
    $secret_key = base64_encode($secret_key['password']);
    sendmessage($from_id, "<code>$secret_key</code>", null, 'HTML');
} elseif ($text == "/token2") {
    $token = bin2hex(random_bytes(16));
    file_put_contents('api/hash.txt', $token);
    sendmessage($from_id, "توکن api شما : <code>$token</code>", null, 'HTML');
    sendDocument($from_id, 'api/documents.txt', "📌 داکیومنت api ربات 
نکات : 
۱ - در صورتی که به endpoint خاصی نیاز داشتید به اکانت پشتیبانی پیام دهید تا بررسی شود.");
} elseif ($text == "✅ فعالسازی پنل تحت وب" && $adminrulecheck['rule'] == "administrator") {
    $admin_select = select("admin", "*", "id_admin", $from_id, "select");
    $randomString = bin2hex(random_bytes(6));
    update("admin", "username", $from_id, "id_admin", $from_id);
    if ($admin_select['password'] == null) {
        update("admin", "password", $randomString, "id_admin", $from_id);
    } else {
        $randomString = $admin_select['password'];
    }
    $keyboardstatistics = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "تنظیم آیپی ورود", 'callback_data' => 'iploginset'],
            ],
        ]
    ]);
    sendmessage($from_id, "✅  پنل تحت وب شما با موفقیت فعال گردید.


🔗آدرس ورود : https://$domainhosts/panel
👤نام کاربری :  <code>$from_id</code>
🔑رمز عبور :  <code>$randomString</code>", $keyboardstatistics, 'HTML');
} elseif (preg_match('/addordermanualـ(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    sendmessage($from_id, $textbotlang['Admin']['addorder']['towstep'], $backadmin, 'HTML');
    step('getusernameconfig', $from_id);
} elseif ($user['step'] == "getusernameconfig") {
    $text = strtolower($text);
    if (!preg_match('/^\w{3,32}$/', $text)) {
        sendmessage($from_id, $textbotlang['users']['stateus']['Invalidusername'], $backuser, 'html');
        return;
    }
    if (in_array($text, $usernameinvoice)) {
        sendmessage($from_id, "❌ این نام کاربری از قبل داخل ربات وجود دارد.", null, 'HTML');
        return;
    }
    update("user", "Processing_value_one", $text, "id", $from_id);
    sendmessage($from_id, $textbotlang['Admin']['addorder']['threestep'], $json_list_marzban_panel, 'HTML');
    step('getnamepanelconfig', $from_id);
} elseif ($user['step'] == "getnamepanelconfig") {
    update("user", "Processing_value_tow", $text, "id", $from_id);
    sendmessage($from_id, $textbotlang['Admin']['addorder']['fourstep'], $json_list_product_list_admin, 'HTML');
    step('stependforaddorder', $from_id);
} elseif ($user['step'] == "stependforaddorder") {
    $sql = "SELECT * FROM product  WHERE name_product = :name_product AND (Location = :location OR Location = '/all') LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':name_product', $text, PDO::PARAM_STR);
    $stmt->bindParam(':location', $user['Processing_value_tow'], PDO::PARAM_STR);
    $stmt->execute();
    $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value_tow'], "select");
    $DataUserOut = $ManagePanel->DataUser($user['Processing_value_tow'], $user['Processing_value_one']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        $datetimestep = strtotime("+" . $info_product['Service_time'] . "days");
        if ($info_product['Service_time'] == 0) {
            $datetimestep = 0;
        } else {
            $datetimestep = strtotime(date("Y-m-d H:i:s", $datetimestep));
        }
        $datac = array(
            'expire' => $datetimestep,
            'data_limit' => $info_product['Volume_constraint'] * pow(1024, 3),
            'from_id' => $user['Processing_value'],
            'username' => "",
            'type' => 'buy'
        );
        $DataUserOut = $ManagePanel->createUser($user['Processing_value_tow'], $info_product['code_product'], $user['Processing_value_one'], $datac);
        if ($DataUserOut['username'] == null) {
            sendmessage($from_id, "❌ خطایی در ساخت اشتراک رخ داده است برای رفع مشکل علت خطا را در گروه گزارش تان بررسی کنید", null, 'HTML');
            $DataUserOut['msg'] = json_encode($DataUserOut['msg']);
            $texterros = "
خطا در ساخت کافنیگ از پنل ادمین
✍️ دلیل خطا : 
{$DataUserOut['msg']}
آیدی ادمین : $from_id
نام پنل : {$marzban_list_get['name_panel']}";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $texterros,
                    'parse_mode' => "HTML"
                ]);
                step("home", $from_id);
            }
            return;
        }
    } else {
        $DataUserOut['configs'] = $DataUserOut['links'];
    }
    $date = time();
    $randomString = bin2hex(random_bytes(4));
    $notifctions = json_encode(array(
        'volume' => false,
        'time' => false,
    ));
    $stmt = $pdo->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username, time_sell, Service_location, name_product, price_product, Volume, Service_time, Status,notifctions) VALUES (:id_user, :id_invoice, :username, :time_sell, :Service_location, :name_product, :price_product, :Volume, :Service_time, :Status,:notifctions)");
    $Status = "active";
    $stmt->bindParam(':id_user', $user['Processing_value'], PDO::PARAM_STR);
    $stmt->bindParam(':id_invoice', $randomString, PDO::PARAM_STR);
    $stmt->bindParam(':username', $user['Processing_value_one'], PDO::PARAM_STR);
    $stmt->bindParam(':time_sell', $date, PDO::PARAM_STR);
    $stmt->bindParam(':Service_location', $user['Processing_value_tow'], PDO::PARAM_STR);
    $stmt->bindParam(':name_product', $info_product['name_product'], PDO::PARAM_STR);
    $stmt->bindParam(':price_product', $info_product['price_product'], PDO::PARAM_STR);
    $stmt->bindParam(':Volume', $info_product['Volume_constraint'], PDO::PARAM_STR);
    $stmt->bindParam(':Service_time', $info_product['Service_time'], PDO::PARAM_STR);
    $stmt->bindParam(':Status', $Status, PDO::PARAM_STR);
    $stmt->bindParam(':notifctions', $notifctions, PDO::PARAM_STR);
    $stmt->execute();
    $output_config_link = $marzban_list_get['sublink'] == "onsublink" ? $DataUserOut['subscription_url'] : "";
    $config = "";
    if ($marzban_list_get['config'] == "onconfig" && is_array($DataUserOut['configs'])) {
        foreach ($DataUserOut['configs'] as $link) {
            $config .= "\n" . $link;
        }
    }
    $Shoppinginfo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['help']['btninlinebuy'], 'callback_data' => "helpbtn"],
            ]
        ]
    ]);
    $datatextbot['textafterpay'] = $marzban_list_get['type'] == "Manualsale" ? $datatextbot['textmanual'] : $datatextbot['textafterpay'];
    $datatextbot['textafterpay'] = $marzban_list_get['type'] == "WGDashboard" ? $datatextbot['text_wgdashboard'] : $datatextbot['textafterpay'];
    $datatextbot['textafterpay'] = $marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik" ? $datatextbot['textafterpayibsng'] : $datatextbot['textafterpay'];
    if (intval($info_product['Service_time']) == 0)
        $info_product['Service_time'] = $textbotlang['users']['stateus']['Unlimited'];
    if (intval($info_product['Volume_constraint']) == 0)
        $info_product['Volume_constraint'] = $textbotlang['users']['stateus']['Unlimited'];
    $textcreatuser = str_replace('{username}', "<code>{$DataUserOut['username']}</code>", $datatextbot['textafterpay']);
    $textcreatuser = str_replace('{name_service}', $info_product['name_product'], $textcreatuser);
    $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
    $textcreatuser = str_replace('{day}', $info_product['Service_time'], $textcreatuser);
    $textcreatuser = str_replace('{volume}', $info_product['Volume_constraint'], $textcreatuser);
    $textcreatuser = applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
    if (intval($info_product['Volume_constraint']) == 0) {
        $textcreatuser = str_replace('گیگابایت', "", $textcreatuser);
    }
    if ($marzban_list_get['type'] == "Manualsale" || $marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik") {
        $textcreatuser = str_replace('{password}', $DataUserOut['subscription_url'], $textcreatuser);
        update("invoice", "user_info", $DataUserOut['subscription_url'], "id_invoice", $randomString);
    }
    sendMessageService($marzban_list_get, $DataUserOut['configs'], $output_config_link, $DataUserOut['username'], $Shoppinginfo, $textcreatuser, $randomString, $user['Processing_value']);
    sendmessage($from_id, $textbotlang['Admin']['addorder']['fivestep'], $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == "⬇️ حداقل موجودی خرید عمده" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("shopSetting", "value", "Namevalue", "minbalancebuybulk", "select")['value'];
    $textmin = "📌 حداقل مبلغی که می خواهید کاربر  خرید انبوه کند را ارسال کنید.
        
مبلغ فعلی : $PaySetting";
    sendmessage($from_id, $textmin, $backadmin, 'HTML');
    step('minbalancebulk', $from_id);
} elseif ($user['step'] == "minbalancebulk") {
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $shopkeyboard, 'HTML');
    update("shopSetting", "value", $text, "Namevalue", "minbalancebuybulk");
    step('home', $from_id);
} elseif (preg_match('/showcarduser-(.*)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    sendmessage($id_user, "💳 کاربر عزیز شماره کارت برای شما فعال شد هم اکنون می توانید خرید خود را انجام دهید.", null, 'HTML');
    sendmessage($from_id, "✅  شماره کارت فعال گردید", null, 'HTML');
    update("user", "cardpayment", "1", "id", $id_user);
} elseif (preg_match('/carduserhide-(.*)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    sendmessage($from_id, "✅  شماره کارت غیرفعال گردید", null, 'HTML');
    update("user", "cardpayment", "0", "id", $id_user);
} elseif ($text == "❌ حذف شماره کارت" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 شماره کارتی که می خواهید حذف کنید را ارسال نمایید.", $list_card_remove, 'HTML');
    step('getcardremove', $from_id);
} elseif ($user['step'] == "getcardremove") {
    $stmt = $pdo->prepare("DELETE FROM card_number WHERE cardnumber = :cardnumber");
    $stmt->bindParam(':cardnumber', $text, PDO::PARAM_STR);
    $stmt->execute();
    sendmessage($from_id, "✅ شماره کارت با موفقیت حذف گردید.", $CartManage, 'HTML');
    step("home", $from_id);
} elseif (preg_match('/rejectrequesta_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    $request_agent = select("Requestagent", "*", "id", $id_user, "select");

    if (!$request_agent) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "درخواست مورد نظر یافت نشد.",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }

    if ($request_agent['status'] == "reject" || $request_agent['status'] == "accept") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE Requestagent SET status = :status, type = :type WHERE id = :id AND status = :expected_status");
        $stmt->execute([
            ':status' => 'reject',
            ':type' => 'None',
            ':id' => $id_user,
            ':expected_status' => 'waiting',
        ]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            telegram('answerCallbackQuery', array(
                'callback_query_id' => $callback_query_id,
                'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
                'show_alert' => true,
                'cache_time' => 5,
            ));
            return;
        }

        $stmtBalance = $pdo->prepare("UPDATE user SET Balance = Balance + :amount WHERE id = :id");
        $stmtBalance->execute([
            ':amount' => intval($setting['agentreqprice']),
            ':id' => $id_user,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $keyboardreject = json_encode([
        'inline_keyboard' => [
            [['text' => "✅درخواست رد شده.", 'callback_data' => "reject"]],
        ]
    ]);
    sendmessage($from_id, "✅ درخواست با موفقیت رد گردید.", null, 'HTML');
    sendmessage($id_user, "❌ کاربر گرامی درخواست نمایندگی شما رد گردید.", null, 'HTML');
    $textrequestagent = "📣 یک کاربر درخواست نمایندگی ثبت کرده لطفا اطلاعات را بررسی و وضعیت را مشخص کنید.\n\nآیدی عددی : $id_user\nنام کاربری : {$request_agent['username']}\nتوضیحات :  {$request_agent['Description']} ";
    $textrequestagent .= "\nوضعیت: رد شد.";
    Editmessagetext($from_id, $message_id, $textrequestagent, $keyboardreject);
    telegram('answerCallbackQuery', array(
        'callback_query_id' => $callback_query_id,
        'text' => "درخواست با موفقیت رد شد.",
        'show_alert' => false,
        'cache_time' => 5,
    ));
} elseif (preg_match('/addagentrequest_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    $request_agent = select("Requestagent", "*", "id", $id_user, "select");
    if (!$request_agent) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "درخواست مورد نظر یافت نشد.",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    if ($request_agent['status'] == "reject" || $request_agent['status'] == "accept") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $defaultAgentType = 'n';
    $agentTypeLabels = [
        'n' => 'نماینده عادی',
        'n2' => 'نماینده پیشرفته',
    ];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE Requestagent SET status = :status, type = :type WHERE id = :id AND status = :expected_status");
        $stmt->execute([
            ':status' => 'accept',
            ':type' => $defaultAgentType,
            ':id' => $id_user,
            ':expected_status' => 'waiting',
        ]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            telegram('answerCallbackQuery', array(
                'callback_query_id' => $callback_query_id,
                'text' => "این درخواست توسط ادمین دیگری بررسی شده است",
                'show_alert' => true,
                'cache_time' => 5,
            ));
            return;
        }

        $stmtUser = $pdo->prepare("UPDATE user SET agent = :agent, expire = NULL WHERE id = :id");
        $stmtUser->execute([
            ':agent' => $defaultAgentType,
            ':id' => $id_user,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    sendmessage($id_user, "✅ کاربر گرامی با درخواست نمایندگی شما موافقت و شما نماینده شدید.", null, 'HTML');
    sendmessage($from_id, $textbotlang['Admin']['agent']['useragented'], $keyboardadmin, 'HTML');
    $agentTypeButtons = [];
    foreach ($agentTypeLabels as $typeCode => $label) {
        $buttonText = ($typeCode === $defaultAgentType ? "✅ " : "") . $label;
        $agentTypeButtons[] = [
            'text' => $buttonText,
            'callback_data' => "setagenttype_{$typeCode}_{$id_user}"
        ];
    }
    $keyboardreject = json_encode([
        'inline_keyboard' => [
            [['text' => "✅درخواست تایید شده.", 'callback_data' => "accept"]],
            $agentTypeButtons,
            [['text' => "⏱️ زمان انقضا نمایندگی", 'callback_data' => 'expireset_' . $id_user]],
            [['text' => "مدیریت کاربر", 'callback_data' => 'manageuser_' . $id_user]]
        ]
    ], JSON_UNESCAPED_UNICODE);
    $textrequestagent = "📣 یک کاربر درخواست نمایندگی ثبت کرده لطفا اطلاعات را بررسی و وضعیت را مشخص کنید.\n\nآیدی عددی : $id_user\nنام کاربری : {$request_agent['username']}\nتوضیحات :  {$request_agent['Description']} ";
    $textrequestagent .= "\nوضعیت: تایید شد ({$agentTypeLabels[$defaultAgentType]})";
    $textrequestagent .= "\nبرای تغییر نوع نماینده از دکمه‌های زیر استفاده کنید.";
    Editmessagetext($from_id, $message_id, $textrequestagent, $keyboardreject);
    telegram('answerCallbackQuery', array(
        'callback_query_id' => $callback_query_id,
        'text' => "درخواست تایید شد و نماینده عادی فعال شد.",
        'show_alert' => false,
        'cache_time' => 5,
    ));
} elseif (preg_match('/^setagenttype_(n|n2)_(\w+)/', $datain, $datagetr)) {
    $selectedType = $datagetr[1];
    $id_user = $datagetr[2];
    $agentTypeLabels = [
        'n' => 'نماینده عادی',
        'n2' => 'نماینده پیشرفته',
    ];
    if (!array_key_exists($selectedType, $agentTypeLabels)) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['agent']['invalidtypeagent'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    update("user", "agent", $selectedType, "id", $id_user);
    update("Requestagent", "type", $selectedType, "id", $id_user);
    $request_agent = select("Requestagent", "*", "id", $id_user, "select");
    if ($request_agent) {
        $agentTypeButtons = [];
        foreach ($agentTypeLabels as $typeCode => $label) {
            $buttonText = ($typeCode === $selectedType ? "✅ " : "") . $label;
            $agentTypeButtons[] = [
                'text' => $buttonText,
                'callback_data' => "setagenttype_{$typeCode}_{$id_user}"
            ];
        }
        $keyboardreject = json_encode([
            'inline_keyboard' => [
                [['text' => "✅درخواست تایید شده.", 'callback_data' => "accept"]],
                $agentTypeButtons,
                [['text' => "⏱️ زمان انقضا نمایندگی", 'callback_data' => 'expireset_' . $id_user]],
                [['text' => "مدیریت کاربر", 'callback_data' => 'manageuser_' . $id_user]]
            ]
        ], JSON_UNESCAPED_UNICODE);
        $textrequestagent = "📣 یک کاربر درخواست نمایندگی ثبت کرده لطفا اطلاعات را بررسی و وضعیت را مشخص کنید.\n\nآیدی عددی : $id_user\nنام کاربری : {$request_agent['username']}\nتوضیحات :  {$request_agent['Description']} ";
        $textrequestagent .= "\nوضعیت: تایید شد ({$agentTypeLabels[$selectedType]})";
        $textrequestagent .= "\nبرای تغییر نوع نماینده از دکمه‌های زیر استفاده کنید.";
        Editmessagetext($from_id, $message_id, $textrequestagent, $keyboardreject);
    }
    telegram('answerCallbackQuery', array(
        'callback_query_id' => $callback_query_id,
        'text' => "نوع نماینده به {$agentTypeLabels[$selectedType]} تغییر کرد.",
        'show_alert' => false,
        'cache_time' => 5,
    ));
} elseif ($datain == "iranpay2setting" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $trnado, 'HTML');
} elseif ($datain == "iranpay3setting" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $iranpaykeyboard, 'HTML');
} elseif ($text == "وضعیت  درگاه ترونادو" && $adminrulecheck['rule'] == "administrator") {
    $statusternadoosql = select("PaySetting", "ValuePay", "NamePay", "statustarnado", "select");
    $statusternadoo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $statusternadoosql['ValuePay'], 'callback_data' => $statusternadoosql['ValuePay']],
            ],
        ]
    ]);
    $textternado = "در این بخش می توانید درگاه ترنادو را خاموش یا روشن کنید";
    sendmessage($from_id, $textternado, $statusternadoo, 'HTML');
} elseif ($datain == "onternado") {
    update("PaySetting", "ValuePay", "offternado", "NamePay", "statustarnado");
    $statusternadoosql = select("PaySetting", "ValuePay", "NamePay", "statustarnado", "select");
    $statusternadoo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $statusternadoosql['ValuePay'], 'callback_data' => $statusternadoosql['ValuePay']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "خاموش گردید", $statusternadoo);
} elseif ($datain == "offternado") {
    update("PaySetting", "ValuePay", "onternado", "NamePay", "statustarnado");
    $statusternadoosql = select("PaySetting", "ValuePay", "NamePay", "statustarnado", "select");
    $statusternadoo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $statusternadoosql['ValuePay'], 'callback_data' => $statusternadoosql['ValuePay']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "روشن گردید", $statusternadoo);
} elseif ($text == "🔑 ثبت API Key ترنادو" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "apiternado", "select");
    $currentKey = $PaySetting['ValuePay'] ?? 'ثبت نشده';
    $texttronseller = "🔑 کلید API ترنادو خود را اینجا وارد کنید.\n\nکلید فعلی شما: {$currentKey}";
    sendmessage($from_id, $texttronseller, $backadmin, 'HTML');
    step('apiternado', $from_id);
} elseif ($user['step'] == "apiternado") {
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $trnado, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "apiternado");
    step('home', $from_id);
} elseif ($datain == "affilnecurrencysetting") {
    sendmessage($from_id, "یک گزینه را انتخاب کنید", $tronnowpayments, 'HTML');
} elseif ($text == "🗂 نام درگاه کارت به کارت") {
    sendmessage($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("getnamecarttocart", $from_id);
} elseif ($user['step'] == "getnamecarttocart") {
    sendmessage($from_id, "✅  متن با موفقیت تنظیم گردید.", $CartManage, 'HTML');
    update("textbot", "text", $text, "id_text", "carttocart");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه nowpayment") {
    sendmessage($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("getnamenowpayment", $from_id);
} elseif ($user['step'] == "getnamenowpayment") {
    sendmessage($from_id, "✅  متن با موفقیت تنظیم گردید.", $nowpayment_setting_keyboard, 'HTML');
    update("textbot", "text", $text, "id_text", "textsnowpayment");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه ریالی بدون احراز") {
    sendmessage($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("getnamecarttopaynotverify", $from_id);
} elseif ($user['step'] == "getnamecarttopaynotverify") {
    sendmessage($from_id, "✅  متن با موفقیت تنظیم گردید.", $CartManage, 'HTML');
    update("textbot", "text", $text, "id_text", "textpaymentnotverify");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه   plisio") {
    sendmessage($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("gettextnowpayment", $from_id);
} elseif ($user['step'] == "gettextnowpayment") {
    sendmessage($from_id, "✅  متن با موفقیت تنظیم گردید.", $NowPaymentsManage, 'HTML');
    update("textbot", "text", $text, "id_text", "textnowpayment");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه رمز ارز آفلاین") {
    sendmessage($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("gettextnowpaymentTRON", $from_id);
} elseif ($user['step'] == "gettextnowpaymentTRON") {
    sendmessage($from_id, "✅  متن با موفقیت تنظیم گردید.", $tronnowpayments, 'HTML');
    update("textbot", "text", $text, "id_text", "textnowpaymenttron");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه ارزی ریالی") {
    sendmessage($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("gettextiranpay2", $from_id);
} elseif ($user['step'] == "gettextiranpay2") {
    sendmessage($from_id, "✅  متن با موفقیت تنظیم گردید.", $Swapinokey, 'HTML');
    update("textbot", "text", $text, "id_text", "iranpay2");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه استار") {
    sendmessage($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("gettextstartelegram", $from_id);
} elseif ($user['step'] == "gettextstartelegram") {
    sendmessage($from_id, "✅  متن با موفقیت تنظیم گردید.", $Swapinokey, 'HTML');
    update("textbot", "text", $text, "id_text", "text_star_telegram");
    step("home", $from_id);
} elseif ($text == "🏷️ نام نمایشی درگاه ترنادو") {
    $prompt = "🏷️ نام نمایشی دلخواه برای درگاه ترنادو را ارسال کنید.";
    sendmessage($from_id, $prompt, $backadmin, 'HTML');
    step("gettextiranpay3", $from_id);
} elseif ($user['step'] == "gettextiranpay3") {
    sendmessage($from_id, "✅  متن با موفقیت تنظیم گردید.", $trnado, 'HTML');
    update("textbot", "text", $text, "id_text", "iranpay3");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه ارزی ریالی سوم") {
    sendmessage($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("gettextiranpay1", $from_id);
} elseif ($user['step'] == "gettextiranpay1") {
    sendmessage($from_id, "✅  متن با موفقیت تنظیم گردید.", $iranpaykeyboard, 'HTML');
    update("textbot", "text", $text, "id_text", "iranpay1");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه آقای پرداخت") {
    sendmessage($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("gettextaqayepardakht", $from_id);
} elseif ($user['step'] == "gettextaqayepardakht") {
    sendmessage($from_id, "✅  متن با موفقیت تنظیم گردید.", $aqayepardakht, 'HTML');
    update("textbot", "text", $text, "id_text", "aqayepardakht");
    step("home", $from_id);
} elseif ($text == "🗂 نام درگاه زرین پال") {
    sendmessage($from_id, " 📌 نام درگاه را ارسال نمايید", $backadmin, 'HTML');
    step("gettextzarinpal", $from_id);
} elseif ($user['step'] == "gettextzarinpal") {
    sendmessage($from_id, "✅  متن با موفقیت تنظیم گردید.", $keyboardzarinpal, 'HTML');
    update("textbot", "text", $text, "id_text", "zarinpal");
    step("home", $from_id);
} elseif ($text == "⚙️  اینباند اکانت غیرفعال" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['Inbound']['GetProtocol'], $keyboardprotocol, 'HTML');
    step('getprotocoldisable', $from_id);
} elseif ($user['step'] == "getprotocoldisable") {
    global $json_list_marzban_panel_inbounds;
    $protocol = ["vless", "vmess", "trojan", "shadowsocks"];
    if (!in_array($text, $protocol)) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['Inbound']['invalidprotocol'], null, 'HTML');
        return;
    }
    $getinbounds = getinbounds($user['Processing_value'])[$text];
    $list_marzban_panel_inbounds = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($getinbounds as $button) {
        $list_marzban_panel_inbounds['keyboard'][] = [
            ['text' => $button['tag']]
        ];
    }
    $list_marzban_panel_inbounds['keyboard'][] = [
        ['text' => "🏠 بازگشت به منوی مدیریت"],
    ];
    $json_list_marzban_panel_inbounds = json_encode($list_marzban_panel_inbounds);
    update("user", "Processing_value_one", $text, "id", $from_id);
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['Inbound']['getInbound'], $json_list_marzban_panel_inbounds, 'HTML');
    step('getInbounddisable', $from_id);
} elseif ($user['step'] == "getInbounddisable") {
    sendmessage($from_id, "نام اینباند با موفقیت ذخیره گردید", $optionMarzban, 'HTML');
    $textpro = "{$user['Processing_value_one']}*$text";
    update("marzban_panel", "inbound_deactive", $textpro, "name_panel", $user['Processing_value']);
    step("home", $from_id);
} elseif ($text == "🗑 بهینه سازی ربات" && $adminrulecheck['rule'] == "administrator") {
    $textoptimize = "❌❌❌❌❌❌❌ متن زیر را با دقت بخوانید

📌 با تایید گزینه زیر عملیات زیر انجام خواهد شد. و قابل بازگشت نیستند

1 - سفارش های غیرفعال حذف خواهند شد
2 - سفارش  های پرداخت نشده حذف خواهند شد.
3 - سفارش های حذف شده توسط ادمین 
4- حذف سرویس های تست غیرفعال
5 - سفارش های حذف شده توسط کاربر 
6 - سفارشاتی که زمان یا حجم شان تمام شده باشد
";
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ تایید و  بهینه سازی", 'callback_data' => 'optimizebot'],
            ],
        ]
    ]);
    sendmessage($from_id, $textoptimize, $Response, 'HTML');
} elseif ($text == "💀 بازنشانی ربات" && $adminrulecheck['rule'] == "administrator") {
    global $adminnumber;
    $mainAdminId = trim((string) ($adminnumber ?? ''));
    $currentUserId = trim((string) $from_id);
    if ($mainAdminId !== '' && $currentUserId !== $mainAdminId) {
        sendmessage($from_id, "⚠️ فقط ادمین اصلی می‌تواند این بخش را مشاهده کند.", null, 'HTML');
        return;
    }
    $resetWarning = "⚠️ هشدار مهم\n\nبا تایید بازنشانی، تمامی جداول پایگاه داده حذف و مجدداً ساخته خواهند شد. این عملیات غیرقابل بازگشت است.\n\nآیا از انجام این کار مطمئن هستید؟";
    $resetKeyboard = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ بله، مطمئن هستم", 'callback_data' => 'resetbot_confirm'],
                ['text' => "❌ خیر", 'callback_data' => 'resetbot_cancel'],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);
    sendmessage($from_id, $resetWarning, $resetKeyboard, 'HTML');
} elseif ($datain == "resetbot_cancel") {
    telegram('answerCallbackQuery', array(
        'callback_query_id' => $callback_query_id,
        'text' => "عملیات لغو شد.",
        'show_alert' => false,
        'cache_time' => 5,
    ));
    Editmessagetext($from_id, $message_id, "❌ عملیات بازنشانی لغو شد.", null);
} elseif ($datain == "resetbot_confirm" && $adminrulecheck['rule'] == "administrator") {
    global $pdo, $domainhosts, $adminnumber;
    $mainAdminId = trim((string) ($adminnumber ?? ''));
    $currentUserId = trim((string) $from_id);
    if ($mainAdminId !== '' && $currentUserId !== $mainAdminId) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "❌ شما اجازه انجام این عملیات را ندارید.",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    telegram('answerCallbackQuery', array(
        'callback_query_id' => $callback_query_id,
        'text' => "⏳ در حال بازنشانی...",
        'show_alert' => false,
        'cache_time' => 5,
    ));
    Editmessagetext($from_id, $message_id, "⏳ عملیات بازنشانی ربات آغاز شد. لطفاً منتظر بمانید...", null);

    $dropError = null;
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($tables)) {
            foreach ($tables as $tableName) {
                $tableName = trim($tableName);
                if ($tableName !== '') {
                    $pdo->exec("DROP TABLE IF EXISTS `{$tableName}`;");
                }
            }
        }
    } catch (Throwable $exception) {
        $dropError = $exception;
    } finally {
        try {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        } catch (Throwable $ignored) {
        }
    }

    if ($dropError !== null) {
        file_put_contents(__DIR__ . '/resetbot_error.log', '[' . date('Y-m-d H:i:s') . "] DROP ERROR: " . $dropError->getMessage() . PHP_EOL, FILE_APPEND);
        Editmessagetext($from_id, $message_id, "❌ خطا در حذف جداول. لطفاً فایل resetbot_error.log را بررسی کنید.", null);
        sendmessage($from_id, "❌ عملیات بازنشانی به دلیل خطا در حذف جداول متوقف شد.", null, 'HTML');
        return;
    }

    $resetUrlUsed = '';
    $reinstallSuccess = false;
    $installerErrors = [];
    $candidateUrls = [];
    $normalizedHost = '';

    if (!empty($domainhosts)) {
        $normalizedHost = rtrim($domainhosts, '/');
        $candidateUrls[] = "https://{$normalizedHost}/table.php";
        $candidateUrls[] = "http://{$normalizedHost}/table.php";
    }

    $attemptInstallerRequest = function (string $url) use (&$resetUrlUsed, &$reinstallSuccess, &$installerErrors) {
        if ($reinstallSuccess || $url === '') {
            return;
        }

        $response = false;
        $httpCode = null;

        if (function_exists('curl_init')) {
            $curlHandle = @curl_init($url);
            if ($curlHandle !== false) {
                curl_setopt_array($curlHandle, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 20,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                ]);
                $response = curl_exec($curlHandle);
                if ($response === false) {
                    $installerErrors[] = 'cURL error: ' . curl_error($curlHandle) . " ({$url})";
                } else {
                    $httpCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
                }
                curl_close($curlHandle);
            }
        }

        if ($response === false) {
            $streamContext = stream_context_create([
                'http' => [
                    'timeout' => 20,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);
            $response = @file_get_contents($url, false, $streamContext);
            if ($response === false) {
                $installerErrors[] = 'stream error: unable to fetch ' . $url;
            } else {
                $httpCode = 200;
            }
        }

        if ($response !== false && ($httpCode === null || ($httpCode >= 200 && $httpCode < 400))) {
            $resetUrlUsed = $url;
            $reinstallSuccess = true;
        }
    };

    foreach ($candidateUrls as $candidateUrl) {
        $attemptInstallerRequest($candidateUrl);
        if ($reinstallSuccess) {
            break;
        }
    }

    if (!$reinstallSuccess) {
        $localTablePath = __DIR__ . '/table.php';
        if (is_file($localTablePath)) {
            try {
                include $localTablePath;
                $reinstallSuccess = true;
                $resetUrlUsed = 'local include';
            } catch (Throwable $tableError) {
                $installerErrors[] = 'local table include: ' . $tableError->getMessage();
                file_put_contents(__DIR__ . '/resetbot_error.log', '[' . date('Y-m-d H:i:s') . "] TABLE ERROR: " . $tableError->getMessage() . PHP_EOL, FILE_APPEND);
                Editmessagetext($from_id, $message_id, "⚠️ جداول حذف شدند اما اجرای table.php با خطا مواجه شد.", null);
                sendmessage($from_id, "⚠️ اجرای table.php با خطا مواجه شد. لطفاً فایل resetbot_error.log را بررسی کنید.", null, 'HTML');
                return;
            }
        }
    }

    if ($reinstallSuccess) {
        $successMessage = "✅ بازنشانی ربات با موفقیت انجام شد." . (!empty($resetUrlUsed) ? "\nمنبع اجرا: {$resetUrlUsed}" : '');
        Editmessagetext($from_id, $message_id, $successMessage, null);
        sendmessage($from_id, "✅ عملیات بازنشانی ربات با موفقیت انجام شد.", null, 'HTML');
    } else {
        if (!empty($installerErrors)) {
            file_put_contents(__DIR__ . '/resetbot_error.log', '[' . date('Y-m-d H:i:s') . "] INSTALL ERROR: " . implode(' | ', $installerErrors) . PHP_EOL, FILE_APPEND);
        }
        $manualUrlHint = !empty($normalizedHost) ? "لطفاً لینک https://{$normalizedHost}/table.php را به صورت دستی باز کنید." : "لطفاً فایل table.php را به صورت دستی اجرا کنید.";
        $warningText = "⚠️ جداول حذف شدند اما اجرای table.php انجام نشد. {$manualUrlHint}";
        Editmessagetext($from_id, $message_id, $warningText, null);
        sendmessage($from_id, $warningText, null, 'HTML');
    }
} elseif ($datain == "optimizebot") {
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE Status = 'unpaid' AND name_product != 'سرویس تست'");
    $stmt->execute();
    $countunpiadorder = $stmt->rowCount();
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE Status = 'disabled' AND name_product != 'سرویس تست'");
    $stmt->execute();
    $countdisableorder = $stmt->rowCount();
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE (Status = 'removebyadmin' or Status = 'removedbyadmin')");
    $stmt->execute();
    $countremoveadminorder = $stmt->rowCount();
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE Status = 'disabled' AND name_product = 'سرویس تست'");
    $stmt->execute();
    $countdisableordtester = $stmt->rowCount();
    #remove data
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'unpaid' AND name_product != 'سرویس تست'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'disabled' AND name_product != 'سرویس تست'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'removebyadmin'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'removedbyadmin'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'disabled' AND name_product = 'سرویس تست'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'removeTime'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'removevolume'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE Status = 'removebyuser' ");
    $stmt->execute();
    $optimizebot = "
✅ $countunpiadorder سفارش پرداخت نشده حذف گردید
✅ $countdisableorder عدد سفارش غیرفعال حذف گردید.
✅ $countremoveadminorder عدد سفارش حذف شده ادمین حذف گردید
✅ $countdisableordtester عدد سفارش تست حذف گردید.";
    Editmessagetext($from_id, $message_id, $optimizebot, null);
    $time = time();
    $logss = "optimize_{$countunpiadorder}_{$countdisableorder}_{$countremoveadminorder}_{$countdisableordtester}_$time";
    file_put_contents('log.txt', "\n" . $logss, FILE_APPEND);
} elseif ($datain == "settimecornvolume") {
    sendmessage($from_id, "📌 در این بخش می توانید تنظیم کنید که اگر حجم کاربر به x رسید پیام اخطار ارسال شود. حجم را براساس گیگ ارسال نمایید.", $backadmin, 'HTML');
    step("getvolumewarn", $from_id);
} elseif ($user['step'] == "getvolumewarn") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, "❌ مقدار نامعتبر", null, 'html');
        return;
    }
    update("setting", "volumewarn", $text);
    sendmessage($from_id, "✅ تغییرات با موفقیت ذخیره شد", $setting_panel, 'HTML');
    step("home", $from_id);
} elseif ($text == "🔧 ساخت کانفیگ دستی") {
    savedata("clear", "idpanel", $user['Processing_value']);
    sendmessage($from_id, "📌در این بخش میتوانید یک سفارش را بطور دستی ایجاد و دریافت کنید 
⚠️ در صورتی که می خواهید  کانفیگ به حساب کاربر اضافه شود و کاربر مدیریت کند باید از گزینه افزودن سفارش  استفاده نمایید.
- برای اضافه کردن کانفیگ ابتدا نام کاربری را ارسال نمایید.", $backadmin, 'HTML');
    step('getusernameconfigcr', $from_id);
} elseif ($user['step'] == "getusernameconfigcr") {
    if (!preg_match('~(?!_)^[a-z][a-z\d_]{2,32}(?<!_)$~i', $text)) {
        sendmessage($from_id, $textbotlang['users']['invalidusername'], $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value_one", $text, "id", $from_id);
    step('getcountcreate', $from_id);
    sendmessage($from_id, "📌 تعداد کانفیگی که میخواهید ساخته شود را ارسال کنید حداکثر ۱۰ تا می توانید ارسال کنید", $backadmin, 'HTML');
} elseif ($user['step'] == "getcountcreate") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    if (intval($text) > 10 or intval($text) < 0) {
        sendmessage($from_id, "❌ حداقل ۱ عدد و حداکثر می توانید ۱۰ عدد ارسال کنید.", $backadmin, 'HTML');
        return;
    }
    savedata("save", "count", $text);
    step('getvolumesconfig', $from_id);
    sendmessage($from_id, "📌 حجم مصرفی اکانت را ارسال نمایید . حجم براساس گیگابایت است.", $backadmin, 'HTML');
} elseif ($user['step'] == "getvolumesconfig") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, "❌ مقدار نامعتبر", null, 'html');
        return;
    }
    update("user", "Processing_value_tow", $text, "id", $from_id);
    sendmessage($from_id, "📌 زمان سرویس را ارسال نمایید زمان براساس روز است.", $backadmin, 'HTML');
    step("gettimeaccount", $from_id);
} elseif ($user['step'] == "gettimeaccount") {
    $userdata = json_decode($user['Processing_value'], true);
    if (!ctype_digit($text)) {
        sendmessage($from_id, "❌ مقدار نامعتبر", null, 'html');
        return;
    }
    if (intval($text) == 0) {
        $expire = 0;
    } else {
        $datetimestep = strtotime("+" . $text . "days");
        $expire = strtotime(date("Y-m-d H:i:s", $datetimestep));
    }
    $datac = array(
        'expire' => $expire,
        'data_limit' => $user['Processing_value_tow'] * pow(1024, 3),
        'from_id' => $from_id,
        'username' => "$username",
        'type' => "new by admin $from_id"
    );
    $panel = select("marzban_panel", "*", "name_panel", $userdata['idpanel'], "select");
    for ($i = 0; $i < $userdata['count']; $i++) {
        $usernameconfig = $user['Processing_value_one'] . "_" . $i;
        $dataoutput = $ManagePanel->createUser($userdata['idpanel'], "usertest", $usernameconfig, $datac);
        if ($dataoutput['username'] == null) {
            $dataoutput['msg'] = json_encode($dataoutput['msg']);
            sendmessage($from_id, $textbotlang['users']['sell']['ErrorConfig'], null, 'HTML');
            $texterros = "
⭕️ یک کاربر قصد دریافت اکانت داشت که ساخت کانفیگ با خطا مواجه شده و به کاربر کانفیگ داده نشد
✍️ دلیل خطا : 
{$dataoutput['msg']}
آیدی کابر : $from_id
نام کاربری کاربر : @$username
نام پنل : {$panel['name_panel']}";
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $texterros,
                    'parse_mode' => "HTML"
                ]);
                step("home", $from_id);
            }
            return;
        }
        $randomString = bin2hex(random_bytes(5));
        $output_config_link = $panel['sublink'] == "onsublink" ? $dataoutput['subscription_url'] : "";
        $config = "";
        if ($panel['config'] == "onconfig" && is_array($dataoutput['configs'])) {
            foreach ($dataoutput['configs'] as $link) {
                $config .= "\n" . $link;
            }
        }
        $datatextbot['textafterpay'] = $panel['type'] == "Manualsale" ? $datatextbot['textmanual'] : $datatextbot['textafterpay'];
        $datatextbot['textafterpay'] = $panel['type'] == "WGDashboard" ? $datatextbot['text_wgdashboard'] : $datatextbot['textafterpay'];
        $datatextbot['textafterpay'] = $panel['type'] == "ibsng" || $panel['type'] == "mikrotik" ? $datatextbot['textafterpayibsng'] : $datatextbot['textafterpay'];
        if (intval($text) == 0)
            $text = $textbotlang['users']['stateus']['Unlimited'];
        $textcreatuser = str_replace('{username}', "<code>{$dataoutput['username']}</code>", $datatextbot['textafterpay']);
        $textcreatuser = str_replace('{name_service}', "پلن دلخواه", $textcreatuser);
        $textcreatuser = str_replace('{location}', $panel['name_panel'], $textcreatuser);
        $textcreatuser = str_replace('{day}', $text, $textcreatuser);
        $textcreatuser = str_replace('{volume}', $user['Processing_value_tow'], $textcreatuser);
        $textcreatuser = applyConnectionPlaceholders($textcreatuser, $output_config_link, $config);
        if ($panel['type'] == "Manualsale" || $panel['type'] == "ibsng" || $panel['type'] == "mikrotik") {
            $textcreatuser = str_replace('{password}', $dataoutput['subscription_url'], $textcreatuser);
            update("invoice", "user_info", $dataoutput['subscription_url'], "id_invoice", $randomString);
        }
        sendMessageService($panel, $dataoutput['configs'], $output_config_link, $dataoutput['username'], null, $textcreatuser, $randomString);
    }
    sendmessage($from_id, $textbotlang['users']['selectoption'], $optionathmarzban, 'HTML');
    $text_report = "";
    if (strlen($setting['Channel_Report']) > 0) {
        $text_report = " 🛍 ساخت کانفیگ توسط ادمین 

نام کاربری کانفیگ : {$user['Processing_value_one']}
حجم کانفیگ  : {$user['Processing_value_tow']} گیگ
زمان کانفیگ : $text روز
آیدی عددی ادمین : $from_id
نام کاربری ادمین : $username
تعداد ساخت : {$userdata['count']}";
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $buyreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
    update("user", "Processing_value", $userdata['idpanel'], "id", $from_id);
    step("home", $from_id);
} elseif ($text == "📑 نوع مرزبان" && $adminrulecheck['rule'] == "administrator") {
    $configPath = __DIR__ . '/config.php';
    $selectionMessage = buildPanelSelectionMessage($configPath);
    $selectionKeyboard = getPanelSelectionKeyboard();
    sendmessage($from_id, $selectionMessage, $selectionKeyboard, 'HTML');
    step('home', $from_id);
} elseif ($datain == "set_panel_pasargad" || $datain == "set_panel_marzban") {
    if ($adminrulecheck['rule'] != "administrator") {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => '❌ شما به این بخش دسترسی ندارید.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }

    $configPath = __DIR__ . '/config.php';
    $desiredState = $datain == "set_panel_pasargad" ? 'pasargad' : 'marzban';

    deletemessage($from_id, $message_id);

    $updateResult = updatePanelStateInConfigFile($configPath, $desiredState);

    if ($updateResult) {
        $confirmationText = $desiredState === 'pasargad'
            ? "✅ نوع پنل با موفقیت روی «پاسارگارد» تنظیم شد.\n🔹 نوع فعلی پنل: پاسارگارد"
            : "✅ نوع پنل با موفقیت روی «مرزبان» تنظیم شد.\n🔹 نوع فعلی پنل: مرزبان";

        sendmessage($from_id, $confirmationText, null, 'HTML');

        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => '✅ تغییر با موفقیت انجام شد.',
            'show_alert' => false,
            'cache_time' => 5,
        ]);
    } else {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => '❌ در ذخیره تغییرات مشکلی رخ داد.',
            'show_alert' => true,
            'cache_time' => 5,
        ]);

        $errorMessage = "❌ ذخیره تغییرات با مشکل مواجه شد. لطفاً دوباره تلاش کنید.";
        sendmessage($from_id, $errorMessage, null, 'HTML');

        $selectionKeyboard = getPanelSelectionKeyboard();
        $selectionMessage = buildPanelSelectionMessage($configPath);
        sendmessage($from_id, $selectionMessage, $selectionKeyboard, 'HTML');
    }
} elseif ($text == "🛠 قابلیت های پنل") {
    sendmessage($from_id, "🪚 برای استفاده از این قابلیت یکی از پنل های زیر را انتخاب نمایید", $json_list_marzban_panel, 'HTML');
    step('getlocoption', $from_id);
} elseif ($user['step'] == "getlocoption") {
    update("user", "Processing_value", $text, "id", $from_id);
    $typepanel = select("marzban_panel", "*", "name_panel", $text, "select")['type'];
    if ($typepanel == "marzban") {
        sendmessage($from_id, $textbotlang['users']['selectoption'], $optionathmarzban, 'HTML');
    } elseif ($typepanel == "x-ui_single") {
        sendmessage($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
    } elseif ($typepanel == "hiddify") {
        sendmessage($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
    } elseif ($typepanel == "alireza") {
        sendmessage($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
    } elseif ($typepanel == "alireza_single") {
        sendmessage($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
    } elseif ($typepanel == "marzneshin") {
        sendmessage($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
    } elseif ($typepanel == "WGDashboard") {
        sendmessage($from_id, $textbotlang['users']['selectoption'], $optionathx_ui, 'HTML');
    }
    step("home", $from_id);
} elseif ($text == "🖥 مدیریت نود ها" || $datain == "bakcnode") {
    if ($adminnumber != $from_id) {
        sendmessage($from_id, "❌ این بخش فقط در دسترس ادمین اصلی است", null, 'HTML');
        return;
    }
    $nodes = Get_Nodes($user['Processing_value']);
    if (!empty($nodes['error'])) {
        sendmessage($from_id, $nodes['error'], null, 'HTML');
        return;
    }
    if (!empty($nodes['status']) && $nodes['status'] != 200) {
        sendmessage($from_id, "❌  خطایی رخ داده است کد خطا :  {$nodes['status']}", null, 'HTML');
        return;
    }
    $nodes = json_decode($nodes['body'], true);
    if (count($nodes) == 0) {
        sendmessage($from_id, "❌  امکان مشاهده تنظیمات نود ها وجود ندارد", null, 'HTML');
        return;
    }
    $keyboardlistsnode['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "actionnode"],
        ['text' => "نام", 'callback_data' => "namenode"]
    ];
    foreach ($nodes as $result) {
        if (!isset($result['id']))
            continue;
        $keyboardlistsnode['inline_keyboard'][] = [
            ['text' => "مدیریت", 'callback_data' => "node_{$result['id']}"],
            ['text' => $result['name'], 'callback_data' => "node_{$result['id']}"],
        ];
    }
    $keyboardlistsnode = json_encode($keyboardlistsnode);
    if ($datain == "bakcnode") {
        Editmessagetext($from_id, $message_id, "📌 در این بخش می توانید نود های پنل مرزبان مدیریت کنید.", $keyboardlistsnode);
    } else {
        sendmessage($from_id, "📌 در این بخش می توانید نود های پنل مرزبان مدیریت کنید.", $keyboardlistsnode, 'HTML');
    }
} elseif (preg_match('/^node_(.*)/', $datain, $dataget)) {
    $nodeid = $dataget[1];
    update("user", "Processing_value_one", $nodeid, "id", $from_id);
    $node = Get_Node($user['Processing_value'], $nodeid);
    if (!empty($node['error'])) {
        sendmessage($from_id, $node['error'], null, 'HTML');
        return;
    }
    if (!empty($node['status']) && $node['status'] != 200) {
        sendmessage($from_id, "❌  خطایی رخ داده است کد خطا :  {$node['status']}", null, 'HTML');
        return;
    }
    $nodeusage = Get_usage_Nodes($user['Processing_value']);
    if (!empty($nodeusage['error'])) {
        sendmessage($from_id, $nodeusage['error'], null, 'HTML');
        return;
    }
    if (!empty($nodeusage['status']) && $nodeusage['status'] != 200) {
        sendmessage($from_id, "❌  خطایی رخ داده است کد خطا :  {$nodeusage['status']}", null, 'HTML');
        return;
    }
    $node = json_decode($node['body'], true);
    $nodeusage = json_decode($nodeusage['body'], true);
    foreach ($nodeusage['usages'] as $nodeusages) {
        if ($nodeusages['node_id'] == $nodeid) {
            $nodeusage = $nodeusages;
            break;
        }
    }
    $sumvolume = formatBytes($nodeusage['downlink'] + $nodeusage['uplink']);
    $textnode = "📌 اطلاعات نود 

🖥 نام نود :  {$node['name']}
🌍 آیپی نود : {$node['address']}
🔻 پورت نود : {$node['port']}
🔺 پورت api نود : {$node['api_port']}
🔋جمع مصرف نود  : $sumvolume
🔄 ضریب مصرف نود : {$node['usage_coefficient']}
🔵 نسخه xray نود : {$node['xray_version']}
🟢 وضعیت نود : {$node['status']}
    ";
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🗂 تغییر نام نود", 'callback_data' => "changenamenode"],
                ['text' => "🔄 تغییر ضریب مصرف نود", 'callback_data' => "changecoefficient"],
            ],
            [
                ['text' => "🌍 تغییر آدرس ایپی نود", 'callback_data' => "changeipnode"],
                ['text' => "♻️ اتصال مجدد نود", 'callback_data' => "reconnectnode"],
            ],
            [
                ['text' => "❌ حذف نود", 'callback_data' => "removenode"],
            ],
            [
                ['text' => "🔙 بازگشت به لیست نود ها", 'callback_data' => "bakcnode"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
} elseif ($datain == "changecoefficient") {
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    $textnode = "📌 ضریب مصرف نودتان را ارسال نمایید.";
    Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
    step("getusage_coefficient", $from_id);
} elseif ($user['step'] == "getusage_coefficient") {
    $config = array(
        'usage_coefficient' => $text
    );
    Modifyuser_node($user['Processing_value'], $user['Processing_value_one'], $config);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    sendmessage($from_id, "✅ ضریب مصرف نود با موفقیت ذخیره گردید.", $backinfoss, 'HTML');
    step('home', $from_id);
} elseif ($datain == "changenamenode") {
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    $textnode = "📌 نام نودتان را ارسال نمانیید.";
    Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
    step("getnamenode", $from_id);
} elseif ($user['step'] == "getnamenode") {
    $config = array(
        'name' => $text
    );
    Modifyuser_node($user['Processing_value'], $user['Processing_value_one'], $config);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    sendmessage($from_id, "✅  نام نود با موفقیت ذخیره گردید.", $backinfoss, 'HTML');
    step('home', $from_id);
} elseif ($datain == "changeipnode") {
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    $textnode = "📌 آیپی نود را ارسال نمانیید.";
    Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
    step("getipnodeset", $from_id);
} elseif ($user['step'] == "getipnodeset") {
    $config = array(
        'address' => $text
    );
    Modifyuser_node($user['Processing_value'], $user['Processing_value_one'], $config);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    sendmessage($from_id, "✅  آدرس نود با موفقیت ذخیره گردید.", $backinfoss, 'HTML');
    step('home', $from_id);
} elseif ($datain == "reconnectnode") {
    reconnect_node($user['Processing_value'], $user['Processing_value_one']);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
            ]
        ]
    ]);
    $textnode = "✅ اتصال مجدد نود انجام گردید.";
    Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
} elseif ($datain == "removenode") {
    removenode($user['Processing_value'], $user['Processing_value_one']);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔙 بازگشت به نود ", 'callback_data' => "bakcnode"],
            ]
        ]
    ]);
    $textnode = "✅ نود با موفقیت حذف گردید";
    Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
} elseif ($text == "💎 مالی" && $adminrulecheck['rule'] == "administrator") {
    $cartotcart = getPaySettingValue('Cartstatus', 'offcard');
    $plisio = getPaySettingValue('nowpaymentstatus', 'offnowpayment');
    $arzireyali1 = getPaySettingValue('statusSwapWallet', 'offSwapinoBot');
    if ($arzireyali1 != "onSwapinoBot" && $arzireyali1 != "offSwapinoBot") {
        update("PaySetting", "ValuePay", "onSwapinoBot", "NamePay", "statusSwapWallet");
        $arzireyali1 = getPaySettingValue('statusSwapWallet', 'offSwapinoBot');
    }
    $arzireyali2 = getPaySettingValue('statustarnado', 'offternado');
    $arzireyali3 = getPaySettingValue('statusiranpay3', 'offiranpay3');
    $aqayepardakht = getPaySettingValue('statusaqayepardakht', 'offaqayepardakht');
    $zarinpal = getPaySettingValue('zarinpalstatus', 'offzarinpal');
    $zarinpey = getPaySettingValue('zarinpeystatus', 'offzarinpey');
    $affilnecurrency = getPaySettingValue('digistatus', 'offdigi');
    $paymentstatussnotverify = getPaySettingValue('paymentstatussnotverify', 'offpaymentstatus');
    $paymentsstartelegram = getPaySettingValue('statusstar', '0');
    $payment_status_nowpayment = getPaySettingValue('statusnowpayment', '0');
    $cartotcartstatus = [
        'oncard' => $textbotlang['Admin']['Status']['statuson'],
        'offcard' => $textbotlang['Admin']['Status']['statusoff']
    ][$cartotcart];
    $plisiostatus = [
        'onnowpayment' => $textbotlang['Admin']['Status']['statuson'],
        'offnowpayment' => $textbotlang['Admin']['Status']['statusoff']
    ][$plisio];
    $arzireyali1status = [
        'onSwapinoBot' => $textbotlang['Admin']['Status']['statuson'],
        'offSwapinoBot' => $textbotlang['Admin']['Status']['statusoff']
    ][$arzireyali1];
    $arzireyali2status = [
        'onternado' => $textbotlang['Admin']['Status']['statuson'],
        'offternado' => $textbotlang['Admin']['Status']['statusoff']
    ][$arzireyali2];
    $aqayepardakhtstatus = [
        'onaqayepardakht' => $textbotlang['Admin']['Status']['statuson'],
        'offaqayepardakht' => $textbotlang['Admin']['Status']['statusoff']
    ][$aqayepardakht];
    $zarinpalstatus = [
        'onzarinpal' => $textbotlang['Admin']['Status']['statuson'],
        'offzarinpal' => $textbotlang['Admin']['Status']['statusoff']
    ][$zarinpal];
    $zarinpeystatus = [
        'onzarinpey' => $textbotlang['Admin']['Status']['statuson'],
        'offzarinpey' => $textbotlang['Admin']['Status']['statusoff']
    ][$zarinpey];
    $affilnecurrencystatus = [
        'ondigi' => $textbotlang['Admin']['Status']['statuson'],
        'offdigi' => $textbotlang['Admin']['Status']['statusoff']
    ][$affilnecurrency];
    $arzireyali3text = [
        'oniranpay3' => $textbotlang['Admin']['Status']['statuson'],
        'offiranpay3' => $textbotlang['Admin']['Status']['statusoff']
    ][$arzireyali3];
    $paymentstar = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$paymentsstartelegram];
    $now_payment_status = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$payment_status_nowpayment];
    $Bot_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "عملیات", 'callback_data' => "actions"],
                ['text' => $textbotlang['Admin']['Status']['statussubject'], 'callback_data' => "subjectde"],
                ['text' => $textbotlang['Admin']['Status']['subject'], 'callback_data' => "subject"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "cartsetting"],
                ['text' => $cartotcartstatus, 'callback_data' => "editpayment-Cartstatus-$cartotcart"],
                ['text' => "🔌 کارت به کارت", 'callback_data' => "carttocart"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "plisiosetting"],
                ['text' => $plisiostatus, 'callback_data' => "editpayment-plisio-$plisio"],
                ['text' => "📌 plisio", 'callback_data' => "plisio"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "nowpaymentsetting"],
                ['text' => $now_payment_status, 'callback_data' => "editpayment-nowpayment-$payment_status_nowpayment"],
                ['text' => "📌 nowpayment", 'callback_data' => "nowpayment"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "iranpay1setting"],
                ['text' => $arzireyali1status, 'callback_data' => "editpayment-arzireyali1-$arzireyali1"],
                ['text' => "📌 ارزی ریالی اول", 'callback_data' => "arzireyali1"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "iranpay2setting"],
                ['text' => $arzireyali2status, 'callback_data' => "editpayment-arzireyali2-$arzireyali2"],
                ['text' => "📌 ارزی ریالی دوم", 'callback_data' => "arzireyali2"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "iranpay3setting"],
                ['text' => $arzireyali3text, 'callback_data' => "editpayment-oniranpay3-$arzireyali3"],
                ['text' => "📌ارزی ریالی سوم", 'callback_data' => "oniranpay3"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "zarinpeysetting"],
                ['text' => $zarinpeystatus, 'callback_data' => "editpayment-zarinpey-$zarinpey"],
                ['text' => "🟠 زرین پی", 'callback_data' => "zarinpey"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "aqayepardakhtsetting"],
                ['text' => $aqayepardakhtstatus, 'callback_data' => "editpayment-aqayepardakht-$aqayepardakht"],
                ['text' => "🔵 آقای پرداخت", 'callback_data' => "aqayepardakht"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "zarinpalsetting"],
                ['text' => $zarinpalstatus, 'callback_data' => "editpayment-zarinpal-$zarinpal"],
                ['text' => "🟡 زرین پال", 'callback_data' => "zarinpal"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "affilnecurrencysetting"],
                ['text' => $affilnecurrencystatus, 'callback_data' => "editpayment-affilnecurrency-$affilnecurrency"],
                ['text' => "💵ارزی آفلاین", 'callback_data' => "affilnecurrency"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "startelegram"],
                ['text' => $paymentstar, 'callback_data' => "editpayment-startelegram-$paymentsstartelegram"],
                ['text' => "💫Star Telegram", 'callback_data' => "none"],
            ],
            [
                ['text' => "⬆️ حداکثر شارژ موجودی", 'callback_data' => "maxbalanceaccount"],
                ['text' => "⬇️ حداقل شارژ موجودی", 'callback_data' => "mainbalanceaccount"],
            ],
            [
                ['text' => "آدرس ولت", 'callback_data' => "walletaddress"],
            ],
            [
                ['text' => "❌ بستن", 'callback_data' => 'close_stat']
            ],
        ]
    ]);
    sendmessage($from_id, "📌 از لیست زیر میتوانید درگاه ها را مدیریت کنید.

⚠️ تیم میرزا هیچ تضمینی برای درگاه ها نخواهد داشت و استفاده  و تمامی مسئولیت ها به عهده شما می باشد", $Bot_Status, 'HTML');
} elseif ($text == "🎁 کش بک تمدید" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 مقدار درصدی که می خواهید حساب کاربر بعد از تمدید به عنوان هدیه شارژ شود را ارسال کنید.
⚠️ در صورتی که میخواهید غیرفعال باشد عدد 0 را ارسال کنید", $backadmin, 'HTML');
    step('getpricecashback', $from_id);
} elseif ($user['step'] == "getpricecashback") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['InvalidTime'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "price_cashback", $text);
    sendmessage($from_id, "📌 نوع کاربری را انتخاب نمایید
f
n
n2", $backadmin, 'HTML');
    step('getagent', $from_id);
} elseif ($user['step'] == "getagent") {
    if (!in_array($text, ['f', 'n', 'n2'])) {
        sendmessage($from_id, "❌ گروه کاربری نامعتبر است", $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    if ($text == "f") {
        update("shopSetting", "value", $userdata['price_cashback'], "Namevalue", "chashbackextend");
    } else {
        $shop_cashbackagent = json_decode(select("shopSetting", "*", "Namevalue", "chashbackextend_agent")['value'], true);
        $shop_cashbackagent[$text] = $userdata['price_cashback'];
        update("shopSetting", "value", json_encode($shop_cashbackagent), "Namevalue", "chashbackextend_agent");
    }
    sendmessage($from_id, "✅ مبلغ با موفقیت تنظیم شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/^editpayment-(.*)-(.*)/', $datain, $dataget)) {
    $type = $dataget[1];
    $value = $dataget[2];
    if ($type == "Cartstatus") {
        if ($value == "oncard") {
            $valuenew = "offcard";
        } else {
            $valuenew = "oncard";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "Cartstatus");
    } elseif ($type == "plisio") {
        if ($value == "onnowpayment") {
            $valuenew = "offnowpayment";
        } else {
            $valuenew = "onnowpayment";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "nowpaymentstatus");
    } elseif ($type == "arzireyali1") {
        if ($value == "onSwapinoBot") {
            $valuenew = "offSwapinoBot";
        } else {
            $valuenew = "onSwapinoBot";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusSwapWallet");
    } elseif ($type == "arzireyali2") {
        if ($value == "onternado") {
            $valuenew = "offternado";
        } else {
            $valuenew = "onternado";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statustarnado");
    } elseif ($type == "aqayepardakht") {
        if ($value == "onaqayepardakht") {
            $valuenew = "offaqayepardakht";
        } else {
            $valuenew = "onaqayepardakht";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusaqayepardakht");
    } elseif ($type == "zarinpey") {
        if ($value == "onzarinpey") {
            $valuenew = "offzarinpey";
        } else {
            $valuenew = "onzarinpey";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "zarinpeystatus");
    } elseif ($type == "zarinpal") {
        if ($value == "onzarinpal") {
            $valuenew = "offzarinpal";
        } else {
            $valuenew = "onzarinpal";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "zarinpalstatus");
    } elseif ($type == "affilnecurrency") {
        if ($value == "ondigi") {
            $valuenew = "offdigi";
        } else {
            $valuenew = "ondigi";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "digistatus");
    } elseif ($type == "oniranpay3") {
        if ($value == "oniranpay3") {
            $valuenew = "offiranpay3";
        } else {
            $valuenew = "oniranpay3";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusiranpay3");
    } elseif ($type == "startelegram") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusstar");
    } elseif ($type == "nowpayment") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusnowpayment");
    }
    $zarinpal = getPaySettingValue('zarinpalstatus', 'offzarinpal');
    $cartotcart = getPaySettingValue('Cartstatus', 'offcard');
    $plisio = getPaySettingValue('nowpaymentstatus', 'offnowpayment');
    $arzireyali1 = getPaySettingValue('statusSwapWallet', 'offSwapinoBot');
    $arzireyali2 = getPaySettingValue('statustarnado', 'offternado');
    $aqayepardakht = getPaySettingValue('statusaqayepardakht', 'offaqayepardakht');
    $zarinpey = getPaySettingValue('zarinpeystatus', 'offzarinpey');
    $affilnecurrency = getPaySettingValue('digistatus', 'offdigi');
    $arzireyali3 = getPaySettingValue('statusiranpay3', 'offiranpay3');
    $paymentstatussnotverify = getPaySettingValue('paymentstatussnotverify', 'offpaymentstatus');
    $paymentsstartelegram = getPaySettingValue('statusstar', '0');
    $payment_status_nowpayment = getPaySettingValue('statusnowpayment', '0');
    $cartotcartstatus = [
        'oncard' => $textbotlang['Admin']['Status']['statuson'],
        'offcard' => $textbotlang['Admin']['Status']['statusoff']
    ][$cartotcart];
    $plisiostatus = [
        'onnowpayment' => $textbotlang['Admin']['Status']['statuson'],
        'offnowpayment' => $textbotlang['Admin']['Status']['statusoff']
    ][$plisio];
    $arzireyali1status = [
        'onSwapinoBot' => $textbotlang['Admin']['Status']['statuson'],
        'offSwapinoBot' => $textbotlang['Admin']['Status']['statusoff']
    ][$arzireyali1];
    $arzireyali2status = [
        'onternado' => $textbotlang['Admin']['Status']['statuson'],
        'offternado' => $textbotlang['Admin']['Status']['statusoff']
    ][$arzireyali2];
    $aqayepardakhtstatus = [
        'onaqayepardakht' => $textbotlang['Admin']['Status']['statuson'],
        'offaqayepardakht' => $textbotlang['Admin']['Status']['statusoff']
    ][$aqayepardakht];
    $zarinpeystatus = [
        'onzarinpey' => $textbotlang['Admin']['Status']['statuson'],
        'offzarinpey' => $textbotlang['Admin']['Status']['statusoff']
    ][$zarinpey];
    $zarinpalstatus = [
        'onzarinpal' => $textbotlang['Admin']['Status']['statuson'],
        'offzarinpal' => $textbotlang['Admin']['Status']['statusoff']
    ][$zarinpal];
    $affilnecurrencystatus = [
        'ondigi' => $textbotlang['Admin']['Status']['statuson'],
        'offdigi' => $textbotlang['Admin']['Status']['statusoff']
    ][$affilnecurrency];
    $arzireyali3text = [
        'oniranpay3' => $textbotlang['Admin']['Status']['statuson'],
        'offiranpay3' => $textbotlang['Admin']['Status']['statusoff']
    ][$arzireyali3];
    $paymentstar = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$paymentsstartelegram];
    $now_payment_status = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$payment_status_nowpayment];
    $Bot_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "عملیات", 'callback_data' => "actions"],
                ['text' => $textbotlang['Admin']['Status']['statussubject'], 'callback_data' => "subjectde"],
                ['text' => $textbotlang['Admin']['Status']['subject'], 'callback_data' => "subject"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "cartsetting"],
                ['text' => $cartotcartstatus, 'callback_data' => "editpayment-Cartstatus-$cartotcart"],
                ['text' => "🔌 کارت به کارت", 'callback_data' => "carttocart"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "plisiosetting"],
                ['text' => $plisiostatus, 'callback_data' => "editpayment-plisio-$plisio"],
                ['text' => "📌 plisio", 'callback_data' => "plisio"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "nowpaymentsetting"],
                ['text' => $now_payment_status, 'callback_data' => "editpayment-nowpayment-$payment_status_nowpayment"],
                ['text' => "📌 nowpayment", 'callback_data' => "nowpayment"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "iranpay1setting"],
                ['text' => $arzireyali1status, 'callback_data' => "editpayment-arzireyali1-$arzireyali1"],
                ['text' => "📌 ارزی ریالی اول", 'callback_data' => "arzireyali1"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "iranpay2setting"],
                ['text' => $arzireyali2status, 'callback_data' => "editpayment-arzireyali2-$arzireyali2"],
                ['text' => "📌 ارزی ریالی دوم", 'callback_data' => "arzireyali2"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "iranpay3setting"],
                ['text' => $arzireyali3text, 'callback_data' => "editpayment-oniranpay3-$arzireyali3"],
                ['text' => "📌ارزی ریالی سوم", 'callback_data' => "oniranpay3"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "zarinpeysetting"],
                ['text' => $zarinpeystatus, 'callback_data' => "editpayment-zarinpey-$zarinpey"],
                ['text' => "🟠 زرین پی", 'callback_data' => "zarinpey"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "aqayepardakhtsetting"],
                ['text' => $aqayepardakhtstatus, 'callback_data' => "editpayment-aqayepardakht-$aqayepardakht"],
                ['text' => "🔵 آقای پرداخت", 'callback_data' => "aqayepardakht"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "zarinpalsetting"],
                ['text' => $zarinpalstatus, 'callback_data' => "editpayment-zarinpal-$zarinpal"],
                ['text' => "🟡 زرین پال", 'callback_data' => "zarinpal"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "affilnecurrencysetting"],
                ['text' => $affilnecurrencystatus, 'callback_data' => "editpayment-affilnecurrency-$affilnecurrency"],
                ['text' => "💵ارزی آفلاین", 'callback_data' => "affilnecurrency"],
            ],
            [
                ['text' => "⚙️ تنظیمات", 'callback_data' => "startelegram"],
                ['text' => $paymentstar, 'callback_data' => "editpayment-startelegram-$paymentsstartelegram"],
                ['text' => "💫Star Telegram", 'callback_data' => "none"],
            ],
            [
                ['text' => "⬆️ حداکثر شارژ موجودی", 'callback_data' => "maxbalanceaccount"],
                ['text' => "⬇️ حداقل شارژ موجودی", 'callback_data' => "mainbalanceaccount"],
            ],
            [
                ['text' => "آدرس ولت", 'callback_data' => "walletaddress"],
            ],
            [
                ['text' => "❌ بستن", 'callback_data' => 'close_stat']
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 از لیست زیر میتوانید درگاه ها را مدیریت کنید.

⚠️ تیم میرزا هیچ تضمینی برای درگاه ها نخواهد داشت و استفاده  و تمامی مسئولیت ها به عهده شما می باشد", $Bot_Status);
} elseif ($text == "💰 کش بک کارت به کارت") {
    sendmessage($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashcart", $from_id);
} elseif ($user['step'] == "getcashcart") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackcart");
} elseif ($text == "💰 کش بک آقای پرداخت") {
    sendmessage($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashahaypar", $from_id);
} elseif ($user['step'] == "getcashahaypar") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackaqaypardokht");
} elseif ($text == "💰 کش بک ارزی ریالی دوم") {
    sendmessage($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashiranpay2", $from_id);
} elseif ($user['step'] == "getcashiranpay2") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $trnado, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackiranpay2");
} elseif ($text == "💰 کش بک ارزی ریالی سوم") {
    sendmessage($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashiranpay4", $from_id);
} elseif ($user['step'] == "getcashiranpay4") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackiranpay3");
} elseif ($text == "💰 کش بک ارزی ریالی") {
    sendmessage($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashiranpay1", $from_id);
} elseif ($user['step'] == "getcashiranpay1") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $Swapinokey, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackiranpay1");
} elseif ($text == "💰 کش بک plisio") {
    sendmessage($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashplisio", $from_id);
} elseif ($user['step'] == "getcashplisio") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackplisio");
} elseif ($text == "💰 کش بک nowpayment") {
    sendmessage($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashnowpayment", $from_id);
} elseif ($user['step'] == "getcashnowpayment") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $nowpayment_setting_keyboard, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "cashbacknowpayment");
} elseif ($text == "💰 کش بک زرین پال") {
    sendmessage($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
    step("getcashzarinpal", $from_id);
} elseif ($user['step'] == "getcashzarinpal") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $keyboardzarinpal, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackzarinpal");
} elseif ($text == "➕ اضافه کردن کانفیگ") {
    sendmessage($from_id, "📌 برای اضافه کردن کانفیگ ابتدا یک نام ارسال نمایید.", $backadmin, 'HTML');
    step('getnameconfigm', $from_id);
    savedata("clear", "namepanel", $user['Processing_value']);
} elseif ($user['step'] == "getnameconfigm") {
    $exitsname = select("manualsell", "*", "namerecord", $text, "count");
    if (intval($exitsname) != 0) {
        sendmessage($from_id, "این نام وجود دارد", null, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $product = [];
    savedata("save", "namerecord", $text);
    $stmt = $pdo->prepare("SELECT * FROM product WHERE Location = :text or Location = '/all' ");
    $stmt->bindParam(':text', $userdata['namepanel'], PDO::PARAM_STR);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $product[] = [$row['name_product']];
    }
    $list_product = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_product['keyboard'][] = [
        ['text' => "🏠 بازگشت به منوی مدیریت"],
    ];
    foreach ($product as $button) {
        $list_product['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_product_list_admin = json_encode($list_product);
    sendmessage($from_id, "📌 نام محصول خود را ارسال نمایید در صورتی که میخواهید  برای اکانت تست تنظیم کنید متن تست را ارسال کنید.", $json_list_product_list_admin, 'HTML');
    step('getnameproduct', $from_id);
    savedata("save", "namerecord", $text);
} elseif ($user['step'] == "getnameproduct") {
    if ($text != "تست") {
        $product = select("product", "*", "name_product", $text, "select");
        if ($product == false) {
            sendmessage($from_id, "محصول در ربات وجود ندارد", $backadmin, 'HTML');
            return;
        }
        savedata("save", "codeproduct", $product['code_product']);
    } else {
        savedata("save", "codeproduct", "usertest");
    }
    sendmessage($from_id, "📌 کانفیگ یا متن دیگر خود را ارسال نمایید", $backadmin, 'HTML');
    step('getconfigtext', $from_id);
} elseif ($user['step'] == "getconfigtext") {
    sendmessage($from_id, "✅ کانفیگ با موفقیت ذخیره گردید.", $optionManualsale, 'HTML');
    step('home', $from_id);
    $userdata = json_decode($user['Processing_value'], true);
    $panel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    $status = "active";
    $stmt = $pdo->prepare("INSERT IGNORE INTO manualsell (codepanel,namerecord,contentrecord,status,codeproduct) VALUES (:codepanel,:namerecord,:contentrecord,:status,:codeproduct)");
    $stmt->bindParam(':codepanel', $panel['code_panel']);
    $stmt->bindParam(':namerecord', $userdata['namerecord']);
    $stmt->bindParam(':contentrecord', $text);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':codeproduct', $userdata['codeproduct']);
    $stmt->execute();
    update("user", "Processing_value", $panel['name_panel'], "id", $from_id);
} elseif ($text == "❌ حذف کانفیگ") {
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $listconfig = [];
    $stmt = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = '{$panel['code_panel']}'");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $listconfig[] = [$row['namerecord']];
    }
    $list_configmanual = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_configmanual['keyboard'][] = [
        ['text' => "🏠 بازگشت به منوی مدیریت"],
    ];
    foreach ($listconfig as $button) {
        $list_configmanual['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_manualconfig_list = json_encode($list_configmanual);
    sendmessage($from_id, "📌 نام کانفیگی که میخواهید حذف نمایید را ارسال کنید ", $json_list_manualconfig_list, 'HTML');
    step("getnameremove", $from_id);
} elseif ($user['step'] == "getnameremove") {
    sendmessage($from_id, "✅ کانفیگ با موفقیت حذف گردید.", $optionManualsale, 'HTML');
    $stmt = $pdo->prepare("DELETE FROM manualsell WHERE namerecord = ?");
    $stmt->bindParam(1, $text);
    $stmt->execute();
    step("home", $from_id);
} elseif ($text == "🌍 قیمت تغییر لوکیشن" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 قیمت تغییر لوکیشن از سایر پنل‌ها به این پنل را ارسال کنید", $backadmin, 'HTML');
    step('setpricechangelocation', $from_id);
} elseif ($user['step'] == "setpricechangelocation") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], "📌قیمت تغییر لوکیشن با موفقیت تغییر کرد");
    update("marzban_panel", "priceChangeloc", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "➕ قیمت حجم اضافه" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 قیمت حجم اضافه برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('GetPriceExtra', $from_id);
} elseif ($user['step'] == "GetPriceExtra") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $user['Processing_value']);
    savedata("save", "price", $text);
    sendmessage($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'] . "\n" . "⚠️ در صورتی که می خواهید قیمت برای تمامی گروه های کاربری تنظیم شود متن <code>all</code> را ارسال کنید", $backuser, 'HTML');
    step('gettypeextra', $from_id);
} elseif ($user['step'] == "gettypeextra") {
    $agentst = ["n", "n2", "f", "all"];
    if (!in_array($text, $agentst)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    outtypepanel($typepanel['type'], $textbotlang['users']['Extra_volume']['ChangedPrice']);
    $eextraprice = json_decode($typepanel['priceextravolume'], true);
    if ($text == 'all') {
        $eextraprice["f"] = $userdata['price'];
        $eextraprice["n"] = $userdata['price'];
        $eextraprice["n2"] = $userdata['price'];
    } else {
        $eextraprice[$text] = $userdata['price'];
    }
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "priceextravolume", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "⚙️ قیمت حجم سرویس دلخواه" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 قیمت حجم اضافه دلخواه این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('GetPricecustomvo', $from_id);
} elseif ($user['step'] == "GetPricecustomvo") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $user['Processing_value']);
    savedata("save", "price", $text);
    sendmessage($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'] . "\n" . "⚠️ در صورتی که می خواهید قیمت برای تمامی گروه های کاربری تنظیم شود متن <code>all</code> را ارسال کنید", $backuser, 'HTML');
    step('gettypeextracustom', $from_id);
} elseif ($user['step'] == "gettypeextracustom") {
    $agentst = ["n", "n2", "f", "all"];
    if (!in_array($text, $agentst)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    outtypepanel($typepanel['type'], $textbotlang['users']['Extra_volume']['ChangedPrice']);
    $eextraprice = json_decode($typepanel['pricecustomvolume'], true);
    if ($text == 'all') {
        $eextraprice["f"] = $userdata['price'];
        $eextraprice["n"] = $userdata['price'];
        $eextraprice["n2"] = $userdata['price'];
    } else {
        $eextraprice[$text] = $userdata['price'];
    }
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "pricecustomvolume", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "⏳ قیمت زمان اضافه" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 قیمت زمان اضافه برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('GetPricetimeextra', $from_id);
} elseif ($user['step'] == "GetPricetimeextra") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $user['Processing_value']);
    savedata("save", "price", $text);
    sendmessage($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'] . "\n" . "⚠️ در صورتی که می خواهید قیمت برای تمامی گروه های کاربری تنظیم شود متن <code>all</code> را ارسال کنید", $backuser, 'HTML');
    step('gettypeextratime', $from_id);
} elseif ($user['step'] == "gettypeextratime") {
    $agentst = ["n", "n2", "f", "all"];
    if (!in_array($text, $agentst)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    outtypepanel($typepanel['type'], $textbotlang['users']['Extra_volume']['ChangedPrice']);
    $eextraprice = json_decode($typepanel['priceextratime'], true);
    if ($text == 'all') {
        $eextraprice["f"] = $userdata['price'];
        $eextraprice["n"] = $userdata['price'];
        $eextraprice["n2"] = $userdata['price'];
    } else {
        $eextraprice[$text] = $userdata['price'];
    }
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "priceextratime", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "⏳ قیمت زمان دلخواه" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 قیمت زمان دلخواه برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('GetPriceExtratime', $from_id);
} elseif ($user['step'] == "GetPriceExtratime") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $user['Processing_value']);
    savedata("save", "price", $text);
    sendmessage($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'] . "\n" . "⚠️ در صورتی که می خواهید قیمت برای تمامی گروه های کاربری تنظیم شود متن <code>all</code> را ارسال کنید", $backuser, 'HTML');
    step('gettypeextratimecustom', $from_id);
} elseif ($user['step'] == "gettypeextratimecustom") {
    $agentst = ["n", "n2", "f", "all"];
    if (!in_array($text, $agentst)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    outtypepanel($typepanel['type'], $textbotlang['users']['Extra_volume']['ChangedPrice']);
    $eextraprice = json_decode($typepanel['pricecustomtime'], true);
    if ($text == 'all') {
        $eextraprice["f"] = $userdata['price'];
        $eextraprice["n"] = $userdata['price'];
        $eextraprice["n2"] = $userdata['price'];
    } else {
        $eextraprice[$text] = $userdata['price'];
    }
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "pricecustomtime", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "🔒 نمایش کارت به کارت پس از اولین پرداخت" && $adminrulecheck['rule'] == "administrator") {
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "checkpaycartfirst", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
        ]
    ]);
    sendmessage($from_id, "📌 با روشن کردن این قابلیت پس از اولین پرداخت کاربر درگاه کارت به کارت برای کاربر فعال می شود", $keyboardverify, 'HTML');
} elseif ($datain == "onpayverify") {
    update("PaySetting", "ValuePay", "offpayverify", "NamePay", "checkpaycartfirst");
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "checkpaycartfirst", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "خاموش شد", $keyboardverify);
} elseif ($datain == "offpayverify") {
    update("PaySetting", "ValuePay", "onpayverify", "NamePay", "checkpaycartfirst");
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "checkpaycartfirst", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "روشن شد", $keyboardverify);
} elseif ($text == "✏️ ویرایش کانفیگ") {
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $listconfig = [];
    $stmt = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = '{$panel['code_panel']}'");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $listconfig[] = [$row['namerecord']];
    }
    $list_configmanual = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_configmanual['keyboard'][] = [
        ['text' => "🏠 بازگشت به منوی مدیریت"],
    ];
    foreach ($listconfig as $button) {
        $list_configmanual['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_manualconfig_list = json_encode($list_configmanual);
    sendmessage($from_id, "📌 نام کانفیگی که میخواهید ویرایش نمایید را ارسال کنید ", $json_list_manualconfig_list, 'HTML');
    step("getnameedit", $from_id);
} elseif ($user['step'] == "getnameedit") {
    sendmessage($from_id, "یکی از گزینه های زیر را انتخاب کنید ", $configedit, 'HTML');
    step("home", $from_id);
    update("user", "Processing_value_one", $text, "id", $from_id);
} elseif ($text == "مخشصات کانفیگ") {
    sendmessage($from_id, "محتوا جدید کانفیگ را ارسال کنید", $backadmin, 'HTML');
    step("getcontentedit", $from_id);
} elseif ($user['step'] == "getcontentedit") {
    sendmessage($from_id, "✅ ذخیره گردید.", $optionManualsale, 'HTML');
    update("manualsell", "contentrecord", $text, "namerecord", $user['Processing_value_one']);
} elseif ($text == "⬆️ افزایش گروهی قیمت") {
    sendmessage($from_id, "📌 محصولات کدام پنل میخواهید افزایش قیمت دهید؟
در صورتی که  موقع تعریف محصول /all زدید  اگر میخواید این دسته تغییر قیمت داشته باشد حتما باید /all ارسال شود", $json_list_marzban_panel, 'HTML');
    step("getaddpricepeoductloc", $from_id);
} elseif ($user['step'] == "getaddpricepeoductloc") {
    sendmessage($from_id, "📌 قیمت برای کدام گروه کاربری اعمال شود 
f,n.n2", $backadmin, 'HTML');
    savedata("clear", "namepanel", $text);
    step("getagentaddpriceproduct", $from_id);
} elseif ($user['step'] == "getagentaddpriceproduct") {
    $keyboard_type_price = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "درصدی", 'callback_data' => 'typeaddprice_percent'],
                ['text' => "ثابت", 'callback_data' => 'typeaddprice_static'],
            ],
        ]
    ]);
    sendmessage($from_id, "📌 مبلغ به صورت درصدی اضافه شود یا مبلغ ثابت", $keyboard_type_price, 'HTML');
    savedata("save", "agent", $text);
    step("home", $from_id);
} elseif (preg_match('/^typeaddprice_(\w+)/', $datain, $dataget)) {
    $type = $dataget[1];
    deletemessage($from_id, $message_id);
    if ($type == "static") {
        sendmessage($from_id, "📌 مبلغی که میخواهید اعمال شود را ارسال نمایید", $backadmin, 'HTML');
    } else {
        sendmessage($from_id, "📌 درصدی که میخواهید اعمال شود را ارسال نمایید", $backadmin, 'HTML');
    }
    savedata("save", "type_price", $type);
    step("getaddpricepeoduct", $from_id);
} elseif ($user['step'] == "getaddpricepeoduct") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $stmt = $pdo->prepare("SELECT * FROM product WHERE Location = '{$userdata['namepanel']}' AND agent = '{$userdata['agent']}'");
    $stmt->execute();
    $product = $stmt->fetchAll();
    if ($product == false) {
        sendmessage($from_id, "❌ محصولی برای تغییر قیمت یافت نشد", $shopkeyboard, 'HTML');
        step("home", $from_id);
        return;
    }
    if ($userdata['type_price'] == "static") {
        $stmt = $pdo->prepare("UPDATE  product set price_product = price_product + :price WHERE Location = '{$userdata['namepanel']}' AND agent = '{$userdata['agent']}'");
        $stmt->bindParam(':price', $text, PDO::PARAM_STR);
    } else {
        $stmt = $pdo->prepare("UPDATE  product set price_product = price_product + (price_product * :price / 100)  WHERE Location = '{$userdata['namepanel']}' AND agent = '{$userdata['agent']}'");
        $stmt->bindParam(':price', $text, PDO::PARAM_STR);
    }
    $stmt->execute();
    sendmessage($from_id, "✅ مبلغ با موفقیت برای تمامی محصولات اعمال شد", $shopkeyboard, 'HTML');
    step("home", $from_id);
} elseif ($text == "⬇️ کاهش  گروهی قیمت") {
    sendmessage($from_id, "📌 محصولات کدام پنل میخواهید کاهش قیمت دهید؟
در صورتی که  موقع تعریف محصول /all زدید  اگر میخواید این دسته تغییر قیمت داشته باشد حتما باید /all ارسال شود", $json_list_marzban_panel, 'HTML');
    step("getlowpricepeoductloc", $from_id);
} elseif ($user['step'] == "getlowpricepeoductloc") {
    sendmessage($from_id, "📌 قیمت برای کدام گروه کاربری اعمال شود 
f,n.n2", $backadmin, 'HTML');
    savedata("clear", "namepanel", $text);
    step("getkampricepeoductloc", $from_id);
} elseif ($user['step'] == "getkampricepeoductloc") {
    sendmessage($from_id, "📌 مبلغی که میخواهید اعمال شود را ارسال نمایید", $backadmin, 'HTML');
    savedata("save", "agent", $text);
    step("getkampricepeoduct", $from_id);
} elseif ($user['step'] == "getkampricepeoduct") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $stmt = $pdo->prepare("SELECT * FROM product WHERE Location = '{$userdata['namepanel']}' AND agent = '{$userdata['agent']}'");
    $stmt->execute();
    $product = $stmt->fetchAll();
    if ($product == false) {
        sendmessage($from_id, "❌ محصولی برای تغییر قیمت یافت نشد", $shopkeyboard, 'HTML');
        return;
    }
    foreach ($product as $products) {
        $result = $products['price_product'] - intval($text);
        update("product", "price_product", round($result), "code_product", $products['code_product']);
    }
    sendmessage($from_id, "✅ مبلغ با موفقیت برای تمامی محصولات اعمال شد", $shopkeyboard, 'HTML');
    step("home", $from_id);
} elseif ($text == "⬇️ حداقل مبلغ کارت به کارت") {
    sendmessage($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaincart", $from_id);
} elseif ($user['step'] == "getmaincart") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancecart");
} elseif ($text == "⬆️ حداکثر مبلغ کارت به کارت") {
    sendmessage($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaxcart", $from_id);
} elseif ($user['step'] == "getmaxcart") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $CartManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancecart");
} elseif ($text == "⬇️ حداقل مبلغ plisio") {
    sendmessage($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainplisio", $from_id);
} elseif ($user['step'] == "getmainplisio") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $NowPaymentsManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalanceplisio");
} elseif ($text == "⬆️ حداکثر مبلغ plisio") {
    sendmessage($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaxplisio", $from_id);
} elseif ($user['step'] == "getmaxplisio") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $NowPaymentsManage, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalanceplisio");
} elseif ($text == "⬇️ حداقل مبلغ رمزارز آفلاین") {
    sendmessage($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaindigitaltron", $from_id);
} elseif ($user['step'] == "getmaindigitaltron") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $tronnowpayments, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancedigitaltron");
} elseif ($text == "⬆️ حداکثر مبلغ رمزارز آفلاین") {
    sendmessage($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaxdigitaltron", $from_id);
} elseif ($user['step'] == "getmaxdigitaltron") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $tronnowpayments, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancedigitaltron");
} elseif ($text == "⬇️ حداقل مبلغ ارزی ریالی") {
    sendmessage($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainiranpay1", $from_id);
} elseif ($user['step'] == "getmainiranpay1") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $Swapinokey, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalanceiranpay1");
} elseif ($text == "⬆️ حداکثر مبلغ ارزی ریالی") {
    sendmessage($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaaxiranpay1", $from_id);
} elseif ($user['step'] == "getmaaxiranpay1") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $Swapinokey, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalanceiranpay1");
} elseif ($text == "⬇️ حداقل مبلغ ارزی ریالی دوم") {
    sendmessage($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainiranpay2", $from_id);
} elseif ($user['step'] == "getmainiranpay2") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $trnado, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalanceiranpay2");
} elseif ($text == "⬆️ حداکثر مبلغ ارزی ریالی دوم") {
    sendmessage($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaaxiranpay2", $from_id);
} elseif ($user['step'] == "getmaaxiranpay2") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $Swapinokey, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalanceiranpay2");
} elseif ($text == "⬇️ حداقل مبلغ آقای پرداخت") {
    sendmessage($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainaqayepardakht", $from_id);
} elseif ($user['step'] == "getmainaqayepardakht") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $aqayepardakht, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalanceaqayepardakht");
} elseif ($text == "⬆️ حداکثر مبلغ آقای پرداخت") {
    sendmessage($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaaxaqayepardakht", $from_id);
} elseif ($user['step'] == "getmaaxaqayepardakht") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $aqayepardakht, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalanceaqayepardakht");
} elseif ($text == "⬇️ حداقل مبلغ زرین پال") {
    sendmessage($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainaqzarinpal", $from_id);
} elseif ($user['step'] == "getmainaqzarinpal") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $aqayepardakht, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancezarinpal");
} elseif ($text == "⬆️ حداکثر مبلغ زرین پال") {
    sendmessage($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmaaxzarinpal", $from_id);
} elseif ($user['step'] == "getmaaxzarinpal") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $aqayepardakht, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancezarinpal");
} elseif ($user['step'] == "walletaddresssiranpay") {
    $walletInputSource = $text;
    if (isset($update) && is_array($update)) {
        if (isset($update['message']['text']) && is_string($update['message']['text'])) {
            $walletInputSource = $update['message']['text'];
        } elseif (isset($update['edited_message']['text']) && is_string($update['edited_message']['text'])) {
            $walletInputSource = $update['edited_message']['text'];
        }
    }

    $walletInput = trim((string) $walletInputSource);

    $userRecord = select("user", "*", "id", $from_id, "select");
    $processingData = [];
    if ($userRecord && isset($userRecord['Processing_value'])) {
        $decodedProcessing = json_decode($userRecord['Processing_value'], true);
        if (is_array($decodedProcessing)) {
            $processingData = $decodedProcessing;
        }
    }

    $walletOrigin = $processingData['walletaddress_origin'] ?? 'general';
    $invalidKeyboard = $walletOrigin === 'trnado' ? $trnado : $backadmin;

    if ($walletInput === '' || !preg_match('/^T[a-zA-Z0-9]{33}$/', $walletInput)) {
        sendmessage($from_id, "❌ آدرس ولت وارد شده نامعتبر است. لطفاً آدرس TRC20 معتبر ارسال کنید.", $invalidKeyboard, 'HTML');
        return;
    }

    $standardizedWallet = $walletInput;

    $successKeyboard = $walletOrigin === 'trnado' ? $trnado : $keyboardadmin;

    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $successKeyboard, 'HTML');
    update("PaySetting", "ValuePay", $standardizedWallet, "NamePay", "walletaddress");
    update("user", "Processing_value", '{}', "id", $from_id);
    step('home', $from_id);
} elseif ($text == "💼 ثبت آدرس ولت ترون (TRC20)" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "walletaddress", "select");
    $currentWallet = $PaySetting['ValuePay'] ?? '';
    $texttronseller = "💼 لطفاً آدرس ولت ترون (TRC20) مرتبط با درگاه ترنادو را ارسال کنید.\n\nولت فعلی شما: {$currentWallet}";
    sendmessage($from_id, $texttronseller, $trnado, 'HTML');
    savedata('clear', 'walletaddress_origin', 'trnado');
    step('walletaddresssiranpay', $from_id);
} elseif ($text == "api  درگاه ارزی ریالی" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "apiiranpay", "select")['ValuePay'];
    $texttronseller = "📌 کد api خود را ارسال نمایید.
        
        مرچنت فعلی شما : $PaySetting";
    sendmessage($from_id, $texttronseller, $backadmin, 'HTML');
    step('apiiranpay', $from_id);
} elseif ($user['step'] == "apiiranpay") {
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $iranpaykeyboard, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "apiiranpay");
    step('home', $from_id);
} elseif ($text == "⬇️ حداقل مبلغ ارزی ریالی سوم") {
    sendmessage($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("minbalanceiranpay", $from_id);
} elseif ($user['step'] == "minbalanceiranpay") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $iranpaykeyboard, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalanceiranpay");
} elseif ($text == "⬆️ حداکثر مبلغ ارزی ریالی سوم") {
    sendmessage($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("maxbalanceiranpay", $from_id);
} elseif ($user['step'] == "maxbalanceiranpay") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $iranpaykeyboard, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalanceiranpay");
} elseif ($text == "📍 حداقل حجم دلخواه" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 حداقل حجم که کاربر میتواند تهیه کند  برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('GetmaineExtra', $from_id);
} elseif ($user['step'] == "GetmaineExtra") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $user['Processing_value']);
    savedata("save", "mainvalume", $text);
    sendmessage($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], $backuser, 'HTML');
    step('gettypeextramain', $from_id);
} elseif ($user['step'] == "gettypeextramain") {
    $agentst = ["n", "n2", "f"];
    if (!in_array($text, $agentst)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    $eextraprice = json_decode($typepanel['mainvolume'], true);
    $eextraprice[$text] = $userdata['mainvalume'];
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "mainvolume", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "📍 حداکثر حجم دلخواه" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 حداکثر حجم که کاربر میتواند تهیه کند  برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('GetmaxeExtra', $from_id);
} elseif ($user['step'] == "GetmaxeExtra") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $user['Processing_value']);
    savedata("save", "maxvolume", $text);
    sendmessage($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], $backuser, 'HTML');
    step('gettypeextramax', $from_id);
} elseif ($user['step'] == "gettypeextramax") {
    $agentst = ["n", "n2", "f"];
    if (!in_array($text, $agentst)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    $eextraprice = json_decode($typepanel['maxvolume'], true);
    $eextraprice[$text] = $userdata['maxvolume'];
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "maxvolume", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "📍 حداقل زمان دلخواه" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 حداقل زمانی دلخواهی  که کاربر میتواند تهیه کند  برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('Getmaintime', $from_id);
} elseif ($user['step'] == "Getmaintime") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $user['Processing_value']);
    savedata("save", "maintime", $text);
    sendmessage($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], $backuser, 'HTML');
    step('gettypeextramaintime', $from_id);
} elseif ($user['step'] == "gettypeextramaintime") {
    $agentst = ["n", "n2", "f"];
    if (!in_array($text, $agentst)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    $eextraprice = json_decode($typepanel['maintime'], true);
    $eextraprice[$text] = $userdata['maintime'];
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "maintime", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "📍 حداکثر زمان دلخواه" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 حداکثر زمانی دلخواهی  که کاربر میتواند تهیه کند  برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('Getmaxtime', $from_id);
} elseif ($user['step'] == "Getmaxtime") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    savedata("clear", "namepanel", $user['Processing_value']);
    savedata("save", "maxtime", $text);
    sendmessage($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], $backuser, 'HTML');
    step('gettypeextramaxtime', $from_id);
} elseif ($user['step'] == "gettypeextramaxtime") {
    $agentst = ["n", "n2", "f"];
    if (!in_array($text, $agentst)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidtypeagent'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $typepanel = select("marzban_panel", "*", "name_panel", $userdata['namepanel'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['saveddata']);
    $eextraprice = json_decode($typepanel['maxtime'], true);
    $eextraprice[$text] = $userdata['maxtime'];
    $eextraprice = json_encode($eextraprice);
    update("marzban_panel", "maxtime", $eextraprice, "name_panel", $userdata['namepanel']);
    update("user", "Processing_value", $userdata['namepanel'], "id", $from_id);
    step('home', $from_id);
} elseif ($text == "🔼 اضافه کردن دپارتمان") {
    sendmessage($from_id, "📌 ایدی عددی ادمینی که میخواهید پیام ها به آن ادمین ارسال شود را بفرستید", $backadmin, 'HTML');
    step("getidadmindep", $from_id);
} elseif ($user['step'] == "getidadmindep") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata('clear', 'idadmin', $text);
    sendmessage($from_id, "📌 نام دپارتمان را ارسال نمایید", $backadmin, 'HTML');
    step("getdeparteman", $from_id);
} elseif ($user['step'] == "getdeparteman") {
    $userdata = json_decode($user['Processing_value'], true);
    $stmt = $pdo->prepare("INSERT IGNORE INTO departman (idsupport,name_departman) VALUES (:idsupport,:name_departman)");
    $stmt->bindParam(':idsupport', $userdata['idadmin']);
    $stmt->bindParam(':name_departman', $text);
    $stmt->execute();
    step("home", $from_id);
    sendmessage($from_id, "📌 دپارتمان با موفقیت اضافه گردید.", $supportcenter, 'HTML');
} elseif ($text == "🔽 حذف کردن دپارتمان") {
    $countdeparteman = select("departman", "*", null, null, "count");
    if ($countdeparteman == 0) {
        sendmessage($from_id, "❌ دپارتمانی برای حذف وجود ندارد.", $departemanslist, 'HTML');
        return;
    }
    sendmessage($from_id, "📌 نوع دپارتمان را برای حذف ارسال کنید.", $departemanslist, 'HTML');
    step("getremovedep", $from_id);
} elseif ($user['step'] == "getremovedep") {
    $stmt = $pdo->prepare("DELETE FROM departman WHERE name_departman = ?");
    $stmt->bindParam(1, $text);
    $stmt->execute();
    sendmessage($from_id, "📌 بخش مورد نظر حذف گردید.", $supportcenter, 'HTML');
    step("home", $from_id);
} elseif ($text == "⚙️ تنظیمات سرویس" && $adminrulecheck['rule'] == "administrator") {
    $textsetservice = "📌 برای تنظیم سرویس یک کانفیگ در پنل خود ساخته و  سرویس هایی که میخواهید فعال باشند. را داخل پنل فعال کرده و نام کاربری کانفیگ را ارسال نمایید";
    sendmessage($from_id, $textsetservice, $backadmin, 'HTML');
    step('getservceid', $from_id);
} elseif ($user['step'] == "getservceid") {
    $userdata = json_decode(getuserm($text, $user['Processing_value'])['body'], true);
    if (isset($userdata['detail']) and $userdata['detail'] == "User not found") {
        sendmessage($from_id, "کاربر در پنل وجود ندارد", null, 'HTML');
        return;
    }
    update("marzban_panel", "proxies", json_encode($userdata['service_ids']), "name_panel", $user['Processing_value']);
    step("home", $from_id);
    sendmessage($from_id, "✅ اطلاعات با موفقیت تنظیم گردید", $optionmarzneshin, 'HTML');
} elseif ($text == "👤 تنظیم آیدی پشتیبانی" && $adminrulecheck['rule'] == "administrator") {
    $textcart = "📌 نام کاربری خود را بدون @ برای پشتیبانی  ارسال کنید\n\n{$setting['id_support']}";
    sendmessage($from_id, $textcart, $backadmin, 'HTML');
    step('idsupportset', $from_id);
} elseif ($user['step'] == "idsupportset") {
    sendmessage($from_id, $textbotlang['Admin']['SettingPayment']['CartDirect'], $supportcenter, 'HTML');
    update("setting", "id_support", $text, null, null);
    step('home', $from_id);
} elseif ($text == "📚 تنظیم آموزش کارت به کارت" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("gethelpcart", $from_id);
} elseif ($user['step'] == "gethelpcart") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "2", "NamePay", "helpcart");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpcart");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpcart");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpcart");
    } else {
        sendmessage($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "📚 تنظیم آموزش nowpayment" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("gethelpnowpayment", $from_id);
} elseif ($user['step'] == "gethelpnowpayment") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "2", "NamePay", "helpnowpayment");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpnowpayment");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpnowpayment");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpnowpayment");
    } else {
        sendmessage($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $nowpayment_setting_keyboard, 'HTML');
} elseif ($text == "📚 تنظیم آموزش پرفکت مانی" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("gethelpperfect", $from_id);
} elseif ($user['step'] == "gethelpperfect") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpperfectmony");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpperfectmony");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpperfectmony");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpperfectmony");
    } else {
        sendmessage($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "📚 تنظیم آموزش plisio" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("gethelpplisio", $from_id);
} elseif ($user['step'] == "gethelpplisio") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpplisio");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpplisio");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpplisio");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpplisio");
    } else {
        sendmessage($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "📚 تنظیم آموزش ارزی ریالی اول" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("gethelpiranpay1", $from_id);
} elseif ($user['step'] == "gethelpiranpay1") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpiranpay1");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay1");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay1");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay1");
    } else {
        sendmessage($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "📚 تنظیم آموزش ارزی ریالی  دوم" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("helpiranpay2", $from_id);
} elseif ($user['step'] == "helpiranpay2") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpiranpay2");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay2");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay2");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay2");
    } else {
        sendmessage($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "📚 تنظیم آموزش ارزی ریالی سوم" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("helpiranpay3", $from_id);
} elseif ($user['step'] == "helpiranpay3") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpiranpay3");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay3");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay3");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay3");
    } else {
        sendmessage($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "📚 تنظیم آموزش درگاه اقای پرداخت" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("helpaqayepardakht", $from_id);
} elseif ($user['step'] == "helpaqayepardakht") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpaqayepardakht");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpaqayepardakht");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpaqayepardakht");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpaqayepardakht");
    } else {
        sendmessage($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "📚 تنظیم آموزش زرین پال" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("helpzarinpal", $from_id);
} elseif ($user['step'] == "helpzarinpal") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpzarinpal");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpzarinpal");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpzarinpal");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpzarinpal");
    } else {
        sendmessage($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "📚 تنظیم آموزش  ارزی افلاین" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("helpofflinearze", $from_id);
} elseif ($user['step'] == "helpofflinearze") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpofflinearze");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpofflinearze");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpofflinearze");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpofflinearze");
    } else {
        sendmessage($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
} elseif ($text == "💰 مبلغ عضویت نمایندگی") {
    sendmessage($from_id, "📌 قیمت درخواست  عضویت  برای نمایندگی را ارسال کنید.", $backadmin, 'HTML');
    step("getpricereqagent", $from_id);
} elseif ($user['step'] == "getpricereqagent") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ تغییرات با موفقیت ذخیره گردید", $setting_panel, 'HTML');
    step("home", $from_id);
    update("setting", "agentreqprice", $text, null, null);
} elseif ($text == "🤖 تایید رسید  بدون بررسی" && $adminrulecheck['rule'] == "administrator") {
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "statuscardautoconfirm", "select")['ValuePay'];
    if ($paymentverify == "onautoconfirm") {
        sendmessage($from_id, "❌ ابتدا تایید خودکار را خاموش کنید.", null, 'HTML');
        return;
    }
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "autoconfirmcart", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
        ]
    ]);
    sendmessage($from_id, "📌 با فعال کردن این قابلیت  در زمان هایی که آنلاین نیستید ربات بصورت خودکار تمامی تراکنش های کارت به کارت را تایید می کند سپس بعد از آنلاین شدن شما رسید ها را بررسی میکنید سپس اگر رسید فیک  ارسال شده تراکنش را کنسل میکنید", $keyboardverify, 'HTML');
} elseif ($datain == "onauto") {
    update("PaySetting", "ValuePay", "offauto", "NamePay", "autoconfirmcart");
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "autoconfirmcart", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "خاموش شد", $keyboardverify);
} elseif ($datain == "offauto") {
    update("PaySetting", "ValuePay", "onauto", "NamePay", "autoconfirmcart");
    $paymentverify = select("PaySetting", "ValuePay", "NamePay", "autoconfirmcart", "select")['ValuePay'];
    $keyboardverify = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $paymentverify, 'callback_data' => $paymentverify],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "روشن شد", $keyboardverify);
} elseif (preg_match('/transferaccount_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    sendmessage($from_id, "آیدی عددی کاربری که میخواهید تمامی اطلاعات به آن کاربر منتقل شود را ارسال نمایید
    توجه داشتید باشید در کاربر مقصد در صورت داشتن موجودی حذف خواهد شد", $backadmin, 'HTML');
    step("getidfortransfers", $from_id);
} elseif ($user['step'] == "getidfortransfers") {
    if (!in_array($text, $users_ids)) {
        sendmessage($from_id, $textbotlang['Admin']['not-user'], $backadmin, 'HTML');
        return;
    }
    if ($text == $user['Processing_value']) {
        sendmessage($from_id, "❌ شما نمی توانید اطلاعات به کاربر فعلی منتقل کنید", $keyboardadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "اطلاعات با موفقیت به حساب کاربری جدید منتقل گردید", $keyboardadmin, 'HTML');
    $stmt = $pdo->prepare("DELETE FROM user WHERE id = :id_user");
    $stmt->bindParam(':id_user', $text, PDO::PARAM_STR);
    $stmt->execute();
    update("user", "id", $text, "id", $user['Processing_value']);
    update("Payment_report", "id_user", $text, "id_user", $user['Processing_value']);
    update("invoice", "id_user", $text, "id_user", $user['Processing_value']);
    update("support_message", "iduser", $text, "iduser", $user['Processing_value']);
    update("service_other", "id_user", $text, "id_user", $user['Processing_value']);
    update("Giftcodeconsumed", "id_user", $text, "id_user", $user['Processing_value']);
    step("home", $from_id);
} elseif ($text == "🖼 پس زمینه کیوآرکد") {
    sendmessage($from_id, "تصویر خود را برای پس زمینه ارسال کنید", $backadmin, 'HTML');
    step("getimagebackgroundqr", $from_id);
} elseif ($user['step'] == "getimagebackgroundqr") {
    if (!$photo) {
        sendmessage($from_id, "تصویر نامعتبر است", $backadmin, 'HTML');
        return;
    }
    $response = getFileddire($photoid);
    if ($response['ok']) {
        $filePath = $response['result']['file_path'];
        $fileUrl = "https://api.telegram.org/file/bot$APIKEY/$filePath";
        $fileContent = file_get_contents($fileUrl);
        file_put_contents("custom.jpg", $fileContent);
        file_put_contents("images.jpg", $fileContent);
        sendmessage($from_id, "🖼 پس زمینه با موفقیت تنظیم گردید", $setting_panel, 'HTML');
        step("home", $from_id);
    }
} elseif ($text == "⚙️ تنظیم پروتکل و اینباند" || $text == "🎛 تنظیم نام گروه" || $text == "⚙️ تنظیم نود") {
    if ($text == "🎛 تنظیم نام گروه") {
        $textsetprotocol = "📌 نام گروهی که بصورت پیشفرض می خواهید از آن ساخته شود را ارسال نمایید.";
    } elseif ($text == "⚙️ تنظیم نود") {
        $textsetprotocol = "📌 برای تنظیم نود یک کاربر در پنل خود ساخته و  نودهایی که میخواهید فعال باشند. را داخل پنل فعال کرده و نام کاربری کاربر را ارسال نمایید";
    } else {
        $textsetprotocol = "📌 برای تنظیم اینباند  و پروتکل باید یک کانفیگ در پنل خود ساخته و  پروتکل و اینباند هایی که میخواهید فعال باشند. را داخل پنل فعال کرده و نام کاربری کانفیگ را ارسال نمایید";
    }
    sendmessage($from_id, $textsetprotocol, $backadmin, 'HTML');
    step("setinboundandprotocol", $from_id);
} elseif ($user['step'] == "setinboundandprotocol") {
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($panel['type'] == "marzban") {
        if ($new_marzban) {
            $DataUserOut = getuser($text, $user['Processing_value']);
            if (!empty($DataUserOut['error'])) {
                sendmessage($from_id, $DataUserOut['error'], null, 'HTML');
                return;
            }
            if (!empty($DataUserOut['status']) && $DataUserOut['status'] != 200) {
                sendmessage($from_id, "❌  خطایی رخ داده است کد خطا :  {$DataUserOut['status']}", null, 'HTML');
                return;
            }
            $DataUserOut = json_decode($DataUserOut['body'], true);
            if ((isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") or !isset($DataUserOut['proxy_settings'])) {
                sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
                return;
            }
            foreach ($DataUserOut['proxy_settings'] as $key => &$value) {
                if ($key == "shadowsocks") {
                    unset($DataUserOut['proxy_settings'][$key]['password']);
                } elseif ($key == "trojan") {
                    unset($DataUserOut['proxy_settings'][$key]['password']);
                } else {
                    unset($DataUserOut['proxy_settings'][$key]['id']);
                }
                if (count($DataUserOut['proxy_settings'][$key]) == 0) {
                    $DataUserOut['proxy_settings'][$key] = new stdClass();
                }
            }
            update("marzban_panel", "inbounds", json_encode($DataUserOut['group_ids']), "name_panel", $user['Processing_value']);
            update("marzban_panel", "proxies", json_encode($DataUserOut['proxy_settings'], true), "name_panel", $user['Processing_value']);
        } else {
            $DataUserOut = getuser($text, $user['Processing_value']);
            if (!empty($DataUserOut['error'])) {
                sendmessage($from_id, $DataUserOut['error'], null, 'HTML');
                return;
            }
            if (!empty($DataUserOut['status']) && $DataUserOut['status'] != 200) {
                sendmessage($from_id, "❌  خطایی رخ داده است کد خطا :  {$DataUserOut['status']}", null, 'HTML');
                return;
            }
            $DataUserOut = json_decode($DataUserOut['body'], true);
            if ((isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") or !isset($DataUserOut['proxies'])) {
                sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
                return;
            }
            foreach ($DataUserOut['proxies'] as $key => &$value) {
                if ($key == "shadowsocks") {
                    unset($DataUserOut['proxies'][$key]['password']);
                } elseif ($key == "trojan") {
                    unset($DataUserOut['proxies'][$key]['password']);
                } else {
                    unset($DataUserOut['proxies'][$key]['id']);
                }
                if (count($DataUserOut['proxies'][$key]) == 0) {
                    $DataUserOut['proxies'][$key] = new stdClass();
                }
            }
            update("marzban_panel", "inbounds", json_encode($DataUserOut['inbounds']), "name_panel", $user['Processing_value']);
            update("marzban_panel", "proxies", json_encode($DataUserOut['proxies'], true), "name_panel", $user['Processing_value']);
        }
    } elseif ($panel['type'] == "s_ui") {
        $data = GetClientsS_UI($text, $panel['name_panel']); {
            if (count($data) == 0) {
                sendmessage($from_id, "❌ یوزر در پنل وجود ندارد.", $options_ui, 'HTML');
                return;
            }
            $servies = [];
            foreach ($data['inbounds'] as $service) {
                $servies[] = $service;
            }
            update("marzban_panel", "proxies", json_encode($servies, true), "name_panel", $user['Processing_value']);
        }
    } elseif ($panel['type'] == "ibsng" || $panel['type'] == "mikrotik") {
        update("marzban_panel", "proxies", $text, "name_panel", $user['Processing_value']);
    }
    if ($panel['type'] == "ibsng") {
        sendmessage($from_id, "✅ نام گروه با موفقیت تنظیم گردید.", $optionibsng, 'HTML');
    } elseif ($panel['type'] == "mikrotik") {
        sendmessage($from_id, "✅ نام گروه با موفقیت تنظیم گردید.", $option_mikrotik, 'HTML');
    } else {
        sendmessage($from_id, "✅ اینباند و پروتکل های شما با موفقیت تنظیم گردیدند.", $optionMarzban, 'HTML');
    }
    step("home", $from_id);
} elseif ($text == "🔋 وضعیت تمدید" && $adminrulecheck['rule'] == "administrator") {
    $marzbanstatus = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $keyboardstatus = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanstatus['status_extend'], 'callback_data' => $marzbanstatus['status_extend']],
            ],
        ]
    ]);
    sendmessage($from_id, $textbotlang['Admin']['Status']['activepanel'], $keyboardstatus, 'HTML');
} elseif ($datain == "on_extend") {
    update("marzban_panel", "status_extend", "off_extend", "name_panel", $user['Processing_value']);
    $marzbanstatus = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $keyboardstatus = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanstatus['status_extend'], 'callback_data' => $marzbanstatus['status_extend']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['activepanelStatusOff'], $keyboardstatus);
} elseif ($datain == "off_extend") {
    update("marzban_panel", "status_extend", "on_extend", "name_panel", $user['Processing_value']);
    $marzbanstatus = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $keyboardstatus = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanstatus['status_extend'], 'callback_data' => $marzbanstatus['status_extend']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['activepaneltatuson'], $keyboardstatus);
} elseif ((preg_match('/confirmchannel-(\w+)/', $datain, $dataget))) {
    $iduser = $dataget[1];
    $userdata = select("user", "*", "id", $iduser, "select");
    if ($userdata['joinchannel'] == "active") {
        sendmessage($from_id, "✍️ کاربر از قبل تایید شده است", null, 'HTML');
        return;
    }
    update("user", "joinchannel", "active", "id", $iduser);
    sendmessage($from_id, "📌 کاربر از این پس بدون عضویت در کانال می تواند در ربات فعالیت داشته باشد", $keyboardadmin, 'HTML');
} elseif ((preg_match('/zerobalance-(\w+)/', $datain, $dataget))) {
    $iduser = $dataget[1];
    $userdata = select("user", "*", "id", $iduser, "select");
    update("user", "Balance", "0", "id", $iduser);
    sendmessage($from_id, "موجودی کاربر به مبلغ {$userdata['Balance']} صفر گردید", $keyboardadmin, 'HTML');
} elseif (preg_match('/removeadmin_(\w+)/', $datain, $dataget) && $adminrulecheck['rule'] == "administrator") {
    $idadmin = trim($dataget[1]);
    $mainAdminId = trim((string) $adminnumber);
    if ($idadmin === $mainAdminId) {
        sendmessage($from_id, "❌ امکان حذف ادمین اصلی وجود ندارد", null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM admin WHERE TRIM(id_admin) = :id_admin");
    $stmt->bindParam(':id_admin', $idadmin, PDO::PARAM_STR);
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        sendmessage($from_id, "⚠️ ادمینی با این شناسه یافت نشد.", null, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ ادمین با موفقیت حذف گردید", null, 'HTML');
}
// elseif (preg_match('/activeconfig-(\w+)/', $datain, $dataget)) {
//     $iduser = $dataget[1];
//     $checkexits = select("user", "*", "id", $iduser, "select");
//     if (intval($checkexits['checkstatus']) != 0) {
//         sendmessage($from_id, "❌ ربات درحال خاموش یا روشن کردن اکانت می باشد منتظر بمانید تا عملیات قبلی انجام سپس درخواست جدید ارسال کنید", null, 'HTML');
//         return;
//     }
//     update("user", "checkstatus", "1", "id", $iduser);
//     sendmessage($from_id, "✅  کانفیگ های کاربر در صف فعال شدن قرار گرفتند توجه داشتید این کار ممکن است بیشتر از ۲ ساعت طول بکشد زمان بستگی به تعداد کانفیگ دارد.", null, 'HTML');
// } elseif (preg_match('/disableconfig-(\w+)/', $datain, $dataget)) {
//     $iduser = $dataget[1];
//     $checkexits = select("user", "*", "id", $iduser, "select");
//     if (intval($checkexits['checkstatus']) != 0) {
//         sendmessage($from_id, "❌ ربات درحال خاموش یا روشن کردن اکانت می باشد منتظر بمانید تا عملیات قبلی انجام سپس درخواست جدید ارسال کنید", null, 'HTML');
//         return;
//     }
//     update("user", "checkstatus", "2", "id", $iduser);
//     sendmessage($from_id, "✅  کانفیگ های کاربر در صف غیرفعال شدن قرار گرفتند توجه داشتید این کار ممکن است بیشتر از ۲ ساعت طول بکشد زمان بستگی به تعداد کانفیگ دارد.", null, 'HTML');
// }
elseif ($text == "🫣 مخفی کردن پنل برای یک کاربر" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌آیدی عددی کاربر را برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('getuserhide', $from_id);
} elseif ($user['step'] == "getuserhide") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], "✅ پنل با موفقیت برای کاربر مخفی گردید");
    if ($typepanel['hide_user'] == null) {
        $hideuserid = [];
    } else {
        $hideuserid = json_decode($typepanel['hide_user'], true);
    }
    $hideuserid[] = $text;
    $hideuserid = json_encode($hideuserid);
    update("marzban_panel", "hide_user", $hideuserid, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == "❌  حذف کاربر از لیست مخفی شدگان" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌آیدی عددی کاربر را برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
    step('getuserhideforremove', $from_id);
} elseif ($user['step'] == "getuserhideforremove") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    step("home", $from_id);
    if ($typepanel['hide_user'] == null) {
        outtypepanel($typepanel['type'], "❌ هیچ کاربری در لیست مخفی شدگان وجود ندارد");
        return;
    }
    $hideuserid = json_decode($typepanel['hide_user'], true);
    if (count($hideuserid) == 0) {
        outtypepanel($typepanel['type'], "❌  کاربر در لیست وجود ندارد");
        return;
    }
    if (!in_array($text, $hideuserid)) {
        outtypepanel($typepanel['type'], "❌ کاربر در لیست وجود ندارد.");
        return;
    }
    $key = array_search($text, $hideuserid);
    if ($key !== false) {
        unset($hideuserid[$key]);
        $hideuserid = array_values($hideuserid);
    }
    $hideuserid = json_encode($hideuserid);
    update("marzban_panel", "hide_user", $hideuserid, "name_panel", $user['Processing_value']);
    outtypepanel($typepanel['type'], "✅  کاربر با موفقیت از لیست حذف گردید.");
} elseif ($datain == "scoresetting") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $lottery, 'HTML');
} elseif ($text == "1️⃣ تنظیم جایزه نفر اول") {
    sendmessage($from_id, "📌 مقدار مبلغی که می خواهید حساب کاربر شارژ شود را ارسال نمایید.", $lottery, 'HTML');
    step("getonelotary", $from_id);
} elseif ($user['step'] == "getonelotary") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ مبلغ جایزه با موفقیت تنظیم شد", $lottery, 'HTML');
    step("home", $from_id);
    $data = json_decode($setting['Lottery_prize'], true);
    $data['one'] = $text;
    $data = json_encode($data, true);
    update("setting", "Lottery_prize", $data, null, null);
} elseif ($text == "2️⃣ تنظیم جایزه نفر دوم") {
    sendmessage($from_id, "📌 مقدار مبلغی که می خواهید حساب کاربر شارژ شود را ارسال نمایید.", $lottery, 'HTML');
    step("getonelotary2", $from_id);
} elseif ($user['step'] == "getonelotary2") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ مبلغ جایزه با موفقیت تنظیم شد", $lottery, 'HTML');
    step("home", $from_id);
    $data = json_decode($setting['Lottery_prize'], true);
    $data['tow'] = $text;
    $data = json_encode($data, true);
    update("setting", "Lottery_prize", $data, null, null);
} elseif ($text == "3️⃣ تنظیم جایزه نفر سوم") {
    sendmessage($from_id, "📌 مقدار مبلغی که می خواهید حساب کاربر شارژ شود را ارسال نمایید.", $lottery, 'HTML');
    step("getonelotary3", $from_id);
} elseif ($user['step'] == "getonelotary3") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ مبلغ جایزه با موفقیت تنظیم شد", $lottery, 'HTML');
    step("home", $from_id);
    $data = json_decode($setting['Lottery_prize'], true);
    $data['theree'] = $text;
    $data = json_encode($data, true);
    update("setting", "Lottery_prize", $data, null, null);
} elseif ($datain == "gradonhshans") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $wheelkeyboard, 'HTML');
} elseif ($text == "🎲 مبلغ برنده شدن کاربر") {
    sendmessage($from_id, "📌 مقدار مبلغی که می خواهید حساب کاربر شارژ شود را ارسال نمایید.", $backadmin, 'HTML');
    step("getpricewheel", $from_id);
} elseif ($user['step'] == "getpricewheel") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ مبلغ جایزه با موفقیت تنظیم شد", $wheelkeyboard, 'HTML');
    step("home", $from_id);
    update("setting", "wheelـluck_price", $text, null, null);
} elseif ($text == "💵 رسید های تایید نشده") {
    $sql = "SELECT * FROM Payment_report WHERE Payment_Method = 'cart to cart' AND payment_Status = 'waiting'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $list_payment = $stmt->fetchAll();
    $list_payment_count = $stmt->rowCount();
    if ($list_payment_count == 0) {
        sendmessage($from_id, "❌ هیچ پرداخت تایید نشده ای ندارید.", null, 'HTML');
        return;
    }
    $list_pay = ['inline_keyboard' => []];
    foreach ($list_payment as $payment) {
        $list_pay['inline_keyboard'][] = [
            ['text' => $payment['id_user'], 'callback_data' => "checkpay"]
        ];
        $list_pay['inline_keyboard'][] = [
            ['text' => "✅", 'callback_data' => "Confirm_pay_{$payment['id_order']}"],
            ['text' => "❌", 'callback_data' => "reject_pay_{$payment['id_order']}"],
            ['text' => "📝", 'callback_data' => "showinfopay_{$payment['id_order']}"],
            ['text' => "🗑", 'callback_data' => "removeresid_{$payment['id_order']}"],
        ];
        $list_pay['inline_keyboard'][] = [
            ['text' => "💸💸💸💸💸💸💸💸💸", 'callback_data' => "checkpay"]
        ];
    }
    $list_pay['inline_keyboard'][] = [
        ['text' => "❌ حذف همه رسید ها", 'callback_data' => "removeresid"]
    ];
    $list_pay_json = json_encode($list_pay, JSON_UNESCAPED_UNICODE);
    if ($list_pay_json === false) {
        error_log('Failed to encode pending receipts keyboard: ' . json_last_error_msg());
        $list_pay_json = json_encode(['inline_keyboard' => []], JSON_UNESCAPED_UNICODE);
    }
    sendmessage($from_id, "📌 پرداخت های تایید نشده کارت به کارت
در این بخش میتوانید پرداخت های تایید نشده مشاهده و تایید یا رد نمایید.
❌ : رد کردن پرداخت
✅ : تایید پرداخت
📝 مشخصات پرداخت
🗑 : حذف رسید بدون اطلاع کاربر", $list_pay_json, 'HTML');
} elseif ($datain == "removeresid") {
    deletemessage($from_id, $message_id);
    sendmessage($from_id, "✅  تمامی رسید ها با موفقیت حذف شدند ", null, 'HTML');
    $sql = "UPDATE Payment_report SET payment_Status = 'reject',dec_not_confirmed = 'remove_all' WHERE Payment_Method = 'cart to cart' AND payment_Status = 'waiting'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
} elseif (preg_match('/showinfopay_(\w+)/', $datain, $dataget)) {
    $idorder = $dataget[1];
    $paymentUser = select("Payment_report", "*", "id_order", $idorder, "select");
    if ($paymentUser == false) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "تراکنش حذف شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $text_order = "🛒 شماره پرداخت  :  <code>{$paymentUser['id_order']}</code>
🙍‍♂️ شناسه کاربر : <code>{$paymentUser['id_user']}</code>
💰 مبلغ پرداختی : {$paymentUser['price']} تومان
⚜️ وضعیت پرداخت : {$paymentUser['payment_Status']}
⭕️ روش پرداخت : {$paymentUser['Payment_Method']} 
📆 تاریخ خرید :  {$paymentUser['time']}";
    sendmessage($from_id, $text_order, null, 'HTML');
} elseif ($text == "🎛 تنظیم اینباند") {
    sendmessage($from_id, "📌 در صورتی که پنل مرزبان  یا مرزنشین هستید یک نام کاربری کانفیگ از پنل کپی و ارسال نمایید در غیراینصورت برای پنل های ثنایی و علیرضا شناسه اینباند را ارسال نمایید", $backadmin, 'HTML');
    step("getdatainboundproduct", $from_id);
} elseif ($user['step'] == "getdatainboundproduct") {
    $marzban_list_get = select("marzban_panel", "*", "code_panel", $user['Processing_value_one']);
    $datainbound = "";
    if ($marzban_list_get['type'] == "marzban") {
        $DataUserOut = getuser($text, $marzban_list_get['name_panel']);
        if (!empty($DataUserOut['error'])) {
            sendmessage($from_id, $DataUserOut['error'], null, 'HTML');
            return;
        }
        if (!empty($DataUserOut['status']) && $DataUserOut['status'] != 200) {
            sendmessage($from_id, "❌  خطایی رخ داده است کد خطا :  {$DataUserOut['status']}", null, 'HTML');
            return;
        }
        $DataUserOut = json_decode($DataUserOut['body'], true);
        if ((isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") or !isset($DataUserOut['proxies'])) {
            sendmessage($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
            return;
        }
        foreach ($DataUserOut['proxies'] as $key => &$value) {
            if ($key == "shadowsocks") {
                unset($DataUserOut['proxies'][$key]['password']);
            } elseif ($key == "trojan") {
                unset($DataUserOut['proxies'][$key]['password']);
            } else {
                unset($DataUserOut['proxies'][$key]['id']);
            }
            if (count($DataUserOut['proxies'][$key]) == 0) {
                $DataUserOut['proxies'][$key] = new stdClass();
            }
        }
        $stmt = $pdo->prepare("UPDATE product SET proxies = :proxies WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
        $proxies_json = json_encode($DataUserOut['proxies']);
        $stmt->bindParam(':proxies', $proxies_json);
        $stmt->bindParam(':name_product', $user['Processing_value']);
        $stmt->bindParam(':Location', $marzban_list_get['name_panel']);
        $stmt->bindParam(':agent', $user['Processing_value_tow']);
        $stmt->execute();
        $datainbound = json_encode($DataUserOut['inbounds']);
    } elseif ($marzban_list_get['type'] == "marzneshin") {
        $userdata = json_decode(getuserm($text, $marzban_list_get['name_panel'])['body'], true);
        if (isset($userdata['detail']) and $userdata['detail'] == "User not found") {
            sendmessage($from_id, "کاربر در پنل وجود ندارد", null, 'HTML');
            return;
        }
        $datainbound = json_encode($userdata['service_ids'], true);
    } elseif ($marzban_list_get['type'] == "x-ui_single" || $marzban_list_get['type'] == "alireza_single") {
        $datainbound = $text;
    } elseif ($marzban_list_get['type'] == "s_ui") {
        $data = GetClientsS_UI($text, $marzban_list_get['name_panel']);
        if (count($data) == 0) {
            sendmessage($from_id, "❌ یوزر در پنل وجود ندارد.", $options_ui, 'HTML');
            return;
        }
        $servies = [];
        foreach ($data['inbounds'] as $service) {
            $servies[] = $service;
        }
        $datainbound = json_encode($servies);
    } elseif ($marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik") {
        $datainbound = $text;
    } else {
        sendmessage($from_id, "❌ برای این پنل قابلیت تعریف اینباند وجود ندارد", $shopkeyboard, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("UPDATE product SET inbounds = :inbounds WHERE id = :name_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':inbounds', $datainbound);
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $marzban_list_get['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    sendmessage($from_id, "✅محصول بروزرسانی شد", $shopkeyboard, 'HTML');
    step('home', $from_id);
} elseif ($datain == "iploginset") {
    sendmessage($from_id, "📌 جهت ورود به پنل تحت وب نیاز است حتما یک آیپی ثابت ثبت کنید تا ورود را با آن آیپی انجام دهید  لطفا آیپی خود را ارسال نمایید", $shopkeyboard, 'HTML');
    step("getiplogin", $from_id);
} elseif ($user['step'] == "getiplogin") {
    update("setting", "iplogin", $text, null, null);
    step("home", $from_id);
    sendmessage($from_id, "✅ آیپی با موفقیت تنظیم شد", $shopkeyboard, 'HTML');
} elseif (preg_match('/extendadmin_(\w+)/', $datain, $dataget) || strpos($text, "/extend ") !== false) {
    if ($text[0] == "/") {
        $usernameconfig = explode(" ", $text)[1];
        $id_invoice = select("invoice", "id_invoice", "username", $usernameconfig, 'select');
        if ($id_invoice == false) {
            sendmessage($from_id, "❌ کاربر وجو ندارد.", null, 'HTML');
            return;
        }
        $id_invoice = $id_invoice['id_invoice'];
    } else {
        $id_invoice = $dataget[1];
    }
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    if ($nameloc == false) {
        sendmessage($from_id, "❌ تمدید با خطا مواجه گردید مراحل تمدید را مجددا انجام دهید.", null, 'HTML');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    update("user", "Processing_value_one", $nameloc['id_invoice'], "id", $from_id);
    savedata("clear", "id_invoice", $nameloc['id_invoice']);
    $textcustom = "📌 حجم درخواستی خود را ارسال کنید.";
    sendmessage($from_id, $textcustom, $backuser, 'html');
    step('gettimecustomvolomforextendadmin', $from_id);
} elseif ($user['step'] == "gettimecustomvolomforextendadmin") {
    $userdate = json_decode($user['Processing_value'], true);
    $nameloc = select("invoice", "*", "id_invoice", $userdate['id_invoice'], "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    savedata("save", "volume", $text);
    $textcustom = "⌛️ زمان سرویس خود را انتخاب نمایید ";
    sendmessage($from_id, $textcustom, $backuser, 'html');
    step('getvolumecustomuserforextendadmin', $from_id);
} elseif ($user['step'] == "getvolumecustomuserforextendadmin") {
    $userdate = json_decode($user['Processing_value'], true);
    $nameloc = select("invoice", "*", "id_invoice", $userdate['id_invoice'], "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidtime'], $backuser, 'HTML');
        return;
    }
    $prodcut['name_product'] = $nameloc['name_product'];
    $prodcut['note'] = "";
    $prodcut['price_product'] = 0;
    $prodcut['Service_time'] = $text;
    $prodcut['Volume_constraint'] = $userdate['volume'];
    update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $userdate['id_invoice']);
    update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $userdate['id_invoice']);
    update("invoice", "Volume", $prodcut['Volume_constraint'], "id_invoice", $userdate['id_invoice']);
    update("invoice", "Service_time", $prodcut['Service_time'], "id_invoice", $userdate['id_invoice']);
    step("home", $from_id);
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserivceadmin-" . $nameloc['id_invoice']],
            ],
            [
                ['text' => "🏠 بازگشت به منوی اصلی", 'callback_data' => "backuser"]
            ]
        ]
    ]);
    $textextend = "📜 فاکتور تمدید شما برای نام کاربری {$nameloc['username']} ایجاد شد.
        
🛍 نام محصول :{$prodcut['name_product']}
⏱ مدت زمان تمدید :{$prodcut['Service_time']} روز
🔋 حجم تمدید :{$prodcut['Volume_constraint']} گیگ
✍️ توضیحات : {$prodcut['note']}
✅ برای تایید و تمدید سرویس روی دکمه زیر کلیک کنید";
    if ($user['step'] == "getvolumecustomuserforextendadmin") {
        sendmessage($from_id, $textextend, $keyboardextend, 'HTML');
    } else {
        Editmessagetext($from_id, $message_id, $textextend, $keyboardextend);
    }
} elseif (preg_match('/^confirmserivceadmin-(.*)/', $datain, $dataget)) {
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $prodcut['code_product'] = "custom_volume";
    $prodcut['name_product'] = $nameloc['name_product'];
    $prodcut['price_product'] = 0;
    $prodcut['Service_time'] = $nameloc['Service_time'];
    $prodcut['Volume_constraint'] = $nameloc['Volume'];
    if ($prodcut == false || !in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'])) {
        sendmessage($from_id, "❌ تمدید با خطا مواجه گردید مراحل تمدید را مجددا انجام دهید.", null, 'HTML');
        return;
    }
    deletemessage($from_id, $message_id);
    $extend = $ManagePanel->extend($marzban_list_get['Methodextend'], $prodcut['Volume_constraint'], $prodcut['Service_time'], $nameloc['username'], $prodcut['code_product'], $marzban_list_get['code_panel']);
    if ($extend['status'] == false) {
        $extend['msg'] = json_encode($extend['msg']);
        $textreports = "
        خطای تمدید سرویس
نام پنل : {$marzban_list_get['name_panel']}
نام کاربری سرویس : {$nameloc['username']}
دلیل خطا : {$extend['msg']}";
        sendmessage($from_id, "❌خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
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
    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price, output) VALUES (:id_user, :username, :value, :type, :time, :price, :output)");
    $dateacc = date('Y/m/d H:i:s');
    $value = $prodcut['Volume_constraint'] . "_" . $prodcut['Service_time'];
    $type = "extend_user_by_admin";
    $stmt->bindParam(':id_user', $from_id, PDO::PARAM_STR);
    $stmt->bindParam(':username', $nameloc['username'], PDO::PARAM_STR);
    $stmt->bindParam(':value', $value, PDO::PARAM_STR);
    $stmt->bindParam(':type', $type, PDO::PARAM_STR);
    $stmt->bindParam(':time', $dateacc, PDO::PARAM_STR);
    $stmt->bindParam(':price', $prodcut['price_product'], PDO::PARAM_STR);
    $output_json = json_encode($extend);
    $stmt->bindParam(':output', $output_json, PDO::PARAM_STR);
    $stmt->execute();
    update("invoice", "Status", "active", "id_invoice", $id_invoice);
    sendmessage($from_id, $textbotlang['users']['extend']['thanks'], null, 'HTML');
    $text_report = "⭕️ ادمین سرویس کاربر را تمدید کرد.
        
اطلاعات کاربر : 
        
🪪 آیدی عددی ادمین : <code>$from_id</code>
🪪 آیدی عددی : <code>{$nameloc['id_user']}</code>
🛍 نام محصول :  {$prodcut['name_product']}
👤 نام کاربری مشتری در پنل  : {$nameloc['username']}
موقعیت سرویس سرویس کاربر : {$nameloc['Service_location']}";
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/removeresid_(\w+)/', $datain, $dataget)) {
    $idorder = $dataget[1];
    $stmt = $pdo->prepare("DELETE FROM Payment_report WHERE id_order = :id_order");
    $stmt->bindParam(':id_order', $idorder, PDO::PARAM_STR);
    $stmt->execute();
    sendmessage($from_id, "✅ رسید با موفقیت حذف شد.", null, 'HTML');
}
if (isset($update["inline_query"])) {
    $sql = "SELECT * FROM invoice WHERE (username LIKE CONCAT('%', :username, '%') OR note  LIKE CONCAT('%', :notes, '%') OR Volume LIKE CONCAT('%',:Volume, '%') OR Service_time LIKE CONCAT('%',:Service_time, '%')) AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold')";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':username', $query, PDO::PARAM_STR);
    $stmt->bindParam(':Service_time', $query, PDO::PARAM_STR);
    $stmt->bindParam(':Volume', $query, PDO::PARAM_STR);
    $stmt->bindParam(':notes', $query, PDO::PARAM_STR);
    $stmt->execute();
    $invoices = $stmt->fetchAll();
    $results = [];
    foreach ($invoices as $OrderUser) {
        if (isset($OrderUser['time_sell'])) {
            $datatime = jdate('Y/m/d H:i:s', $OrderUser['time_sell']);
        } else {
            $datatime = $textbotlang['Admin']['ManageUser']['dataorder'];
        }
        if ($OrderUser['name_product'] == "سرویس تست") {
            $OrderUser['Service_time'] = $OrderUser['Service_time'] . "ساعته";
            $OrderUser['Volume'] = $OrderUser['Volume'] . "مگابایت";
        } else {
            $OrderUser['Service_time'] = $OrderUser['Service_time'] . "روزه";
            $OrderUser['Volume'] = $OrderUser['Volume'] . "گیگابایت";
        }
        $results[] = [
            "type" => "article",
            "id" => uniqid(),
            'cache_time' => 0,
            'is_personal' => true,
            "title" => $OrderUser['username'],
            "input_message_content" => [
                "message_text" => "
🛒 شماره سفارش  :  {$OrderUser['id_invoice']}
🛒  وضعیت سفارش در ربات : {$OrderUser['Status']}
🙍‍♂️ شناسه کاربر : {$OrderUser['id_user']}
👤 نام کاربری اشتراک :  {$OrderUser['username']}
📍 موقعیت سرویس :  {$OrderUser['Service_location']}
🛍 نام محصول :  {$OrderUser['name_product']}
💰 قیمت پرداختی سرویس : {$OrderUser['price_product']} تومان
⚜️ حجم سرویس خریداری شده : {$OrderUser['Volume']}
⏳ زمان سرویس خریداری شده : {$OrderUser['Service_time']} 
📆 تاریخ خرید : $datatime  
"
            ]
        ];
    }
    answerInlineQuery($inline_query_id, $results);
} elseif (preg_match('/vieworderuser_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM invoice WHERE id_user = '$id_user'  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "وضعیت سرویس", 'callback_data' => "Status"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => "مشاهده اطلاعات",
                'callback_data' => "manageinvoice_" . $row['id_invoice']
            ],
            [
                'text' => $row['Status'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['username'],
                'callback_data' => $row['username']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageinvoice_' . $id_user
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageinvoice_' . $id_user
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json, 'html');
} elseif (preg_match('/next_pageinvoice_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    $numpage = select("invoice", "*", "id_user", $id_user, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM invoice WHERE id_user = '$id_user'  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "وضعیت سرویس", 'callback_data' => "Status"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => "مشاهده اطلاعات",
                'callback_data' => "manageinvoice_" . $row['id_invoice']
            ],
            [
                'text' => $row['Status'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['username'],
                'callback_data' => $row['username']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageinvoice_' . $id_user
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageinvoice_' . $id_user
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif (preg_match('/previous_pageinvoice_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    $numpage = select("invoice", "*", "id_user", $id_user, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM invoice WHERE id_user = '$id_user'  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "وضعیت سرویس", 'callback_data' => "Status"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => "مشاهده اطلاعات",
                'callback_data' => "manageinvoice_" . $row['id_invoice']
            ],
            [
                'text' => $row['Status'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['username'],
                'callback_data' => $row['username']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageinvoice_' . $id_user
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageinvoice_' . $id_user
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($text == "متن دکمه گردونه شانس" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . $datatextbot['text_wheel_luck'], $backadmin, 'HTML');
    step('text_wheel_luck', $from_id);
} elseif ($user['step'] == "text_wheel_luck") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_wheel_luck");
    step('home', $from_id);
} elseif ($datain == "cartuserlist") {
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user WHERE cardpayment = '1'  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageusercart'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageusercart'
        ]
    ];
    $backbtn = [
        [
            'text' => "بازگشت به منوی قبل",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'next_pageusercart') {
    $numpage = select("user", "*", null, null, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user WHERE cardpayment = '1'  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageusercart'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageusercart'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'previous_pageusercart') {
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user WHERE cardpayment = '1'  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageusercart'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageusercart'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif (preg_match('/createbot_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    $checkbot = select("botsaz", "*", "id_user", $id_user, "count");
    $checkbots = select("botsaz", "*", null, null, "count");
    if ($checkbots >= 15) {
        sendmessage($from_id, "❌  درحال حاضر فقط محدود به ساختن 15 ربات برای نماینده های خود هستید.", $keyboardadmin, 'HTML');
        return;
    }
    if ($checkbot != 0) {
        $textexitsbot = "❌ این ربات از قبل نصب شده است امکان نصب مجدد وجود ندارد.";
        sendmessage($from_id, $textexitsbot, $keyboardadmin, 'HTML');
        return;
    }
    savedata("clear", "id_user", $id_user);
    $texbot = "📌  از طریق این بخش شما می توانید برای نماینده خود یک ربات فروش بسازید تا نماینده با ربات اختصاصی خودش فروش داشته باشد

- جهت ساخت ربات توکن ربات را ارسال نمایید.";
    sendmessage($from_id, $texbot, $backadmin, 'HTML');
    step("gettokenbot", $from_id);
} elseif ($user['step'] == "gettokenbot") {
    $getInfoToken = json_decode(file_get_contents("https://api.telegram.org/bot$text/getme"), true);
    if ($getInfoToken == false or !$getInfoToken['ok']) {
        sendmessage($from_id, "❌ توکن نامعتبر است", $backadmin, 'HTML');
        return;
    }
    $checkbot = select("botsaz", "*", "bot_token", $text, "count");
    if ($checkbot != 0) {
        sendmessage($from_id, "📌 این توکن از قبل ثبت شده است", null, 'HTML');
        return;
    }
    savedata("save", "token", $text);
    savedata("save", "username", $getInfoToken['result']['username']);
    $texbot = "📌 آیدی عددی ادمین را ارسال نمایید";
    sendmessage($from_id, $texbot, $backadmin, 'HTML');
    step("getadminidbot", $from_id);
} elseif ($user['step'] == "getadminidbot") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $userdate = json_decode($user['Processing_value'], true);
    step("home", $from_id);
    $admin_ids = json_encode(array(
        $userdate['id_user']
    ));
    $destination = getcwd();
    $dirsource = "$destination/vpnbot/{$userdate['id_user']}{$userdate['username']}";
    if (is_dir($dirsource) && !deleteDirectory($dirsource)) {
        error_log('Failed to remove existing bot directory: ' . $dirsource);
    }
    if (!copyDirectoryContents($destination . '/vpnbot/Default', $dirsource)) {
        error_log('Failed to copy default bot files into: ' . $dirsource);
    }
    $contentconfig = file_get_contents($dirsource . "/config.php");
    $new_code = str_replace('BotTokenNew', $userdate['token'], $contentconfig);
    file_put_contents($dirsource . "/config.php", $new_code);
    file_get_contents("https://api.telegram.org/bot{$userdate['token']}/setwebhook?url=https://$domainhosts/vpnbot/{$userdate['id_user']}{$userdate['username']}/index.php");
    file_get_contents("https://api.telegram.org/bot{$userdate['token']}/sendmessage?chat_id={$userdate['id_user']}&text=✅ کاربر عزیز ربات شما با موفقیت نصب گردید.");
    $datasetting = json_encode(array(
        "minpricetime" => 4000,
        "pricetime" => 4000,
        "minpricevolume" => 4000,
        "pricevolume" => 4000,
        "support_username" => "@support",
        "Channel_Report" => 0,
        "cart_info" => "جهت پرداخت مبلغ را به شماره کارت زیر واریز نمایید",
        'show_product' => true,
    ));
    $value = "{}";
    $stmt = $pdo->prepare("INSERT INTO botsaz (id_user,bot_token,admin_ids,username,time,setting,hide_panel) VALUES (:id_user,:bot_token,:admin_ids,:username,:time,:setting,:hide_panel)");
    $stmt->bindParam(':id_user', $userdate['id_user'], PDO::PARAM_STR);
    $stmt->bindParam(':bot_token', $userdate['token'], PDO::PARAM_STR);
    $stmt->bindParam(':admin_ids', $admin_ids);
    $stmt->bindParam(':username', $userdate['username'], PDO::PARAM_STR);
    $time = date('Y/m/d H:i:s');
    $stmt->bindParam(':time', $time, PDO::PARAM_STR);
    $stmt->bindParam(':setting', $datasetting, PDO::PARAM_STR);
    $stmt->bindParam(':hide_panel', $value, PDO::PARAM_STR);
    $stmt->execute();
    $texbot = "✅ ربات نماینده با موفقیت ساخته شد.
⚙️ نام کاربری ربات  : @{$userdate['username']}
🤠 توکن ربات : <code>{$userdate['token']}</code>";
    sendmessage($from_id, $texbot, $keyboardadmin, 'HTML');
} elseif (preg_match('/removebotsell_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    $contentbto = select("botsaz", "*", "id_user", $id_user, "select");
    $destination = getcwd();
    $dirsource = "$destination/vpnbot/$id_user{$contentbto['username']}";
    if (is_dir($dirsource) && !deleteDirectory($dirsource)) {
        error_log('Failed to remove bot directory: ' . $dirsource);
    }
    if (!empty($contentbto['bot_token'])) {
        file_get_contents("https://api.telegram.org/bot{$contentbto['bot_token']}/deletewebhook");
    }
    $stmt = $pdo->prepare("DELETE FROM botsaz WHERE id_user = :id_user");
    $stmt->bindParam(':id_user', $id_user, PDO::PARAM_STR);
    $stmt->execute();
    sendmessage($from_id, "❌ ربات فروش نماینده با موفقیت حذف گردید.", $keyboardadmin, 'HTML');
} elseif (preg_match('/setvolumesrc_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    savedata("clear", "id_user", $id_user);
    sendmessage($from_id, "📌 کمترین قیمتی که میخواهید نماینده بابت هر گیگ حجم بپردازد را تعیین کنید", $backadmin, 'HTML');
    step("getpricevolumesrc", $from_id);
} elseif ($user['step'] == "getpricevolumesrc") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    step("home", $from_id);
    $userdate = json_decode($user['Processing_value'], true);
    $botinfo = json_decode(select("botsaz", "setting", "id_user", $userdate['id_user'], "select")['setting'], true);
    $botinfo['minpricevolume'] = $text;
    update("botsaz", "setting", json_encode($botinfo), "id_user", $userdate['id_user']);
    sendmessage($from_id, "✅ قیمت با موفقیت ذخیره گردید.", $keyboardadmin, 'HTML');
} elseif (preg_match('/settimepricesrc_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    savedata("clear", "id_user", $id_user);
    sendmessage($from_id, "📌 کمترین قیمتی که میخواهید نماینده بابت هر روز زمان بپردازد را تعیین کنید", $backadmin, 'HTML');
    step("getpricetimesrc", $from_id);
} elseif ($user['step'] == "getpricetimesrc") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    step("home", $from_id);
    $userdate = json_decode($user['Processing_value'], true);
    $botinfo = json_decode(select("botsaz", "setting", "id_user", $userdate['id_user'], "select")['setting'], true);
    $botinfo['minpricetime'] = $text;
    update("botsaz", "setting", json_encode($botinfo), "id_user", $userdate['id_user']);
    sendmessage($from_id, "✅ قیمت با موفقیت ذخیره گردید.", $keyboardadmin, 'HTML');
}
if ($datain == "settimecornday" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌 در این بخش می توانید تعیین کنید چند روز مانده است به پایان اشتراک به کاربر اطلاع داده شود. زمان برحسب روز است" . $setting['daywarn'] . "روز", $backadmin, 'HTML');
    step("getdaywarn", $from_id);
} elseif ($user['step'] == "getdaywarn") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['cronjob']['changeddata'], $keyboardadmin, 'HTML');
    step("home", $from_id);
    update("setting", "daywarn", $text);
} elseif ($datain == "linkappsetting") {
    sendmessage($from_id, "📌 یک گزینه را انتخاب نمایید.", $keyboardlinkapp, 'HTML');
} elseif ($text == "🔗 اضافه کردن برنامه") {
    sendmessage($from_id, "📌 جهت اضافه کردن لینک دانلود برنامه  نام اپ یا نام دکمه را ارسال نمایید.", $backadmin, 'HTML');
    step("getnamebtnapp", $from_id);
} elseif ($user['step'] == "getnamebtnapp") {
    if (strlen($text) > 200) {
        sendmessage($from_id, "📌 نام باید کمتر از ۲۰۰ کاراکتر باشد.", $backadmin, 'HTML');
        return;
    }
    savedata("clear", "name", $text);
    sendmessage($from_id, "📌 لینک دانلود اپ را ارسال نمایید", $backadmin, 'HTML');
    step("geturlbtnapp", $from_id);
} elseif ($user['step'] == "geturlbtnapp") {
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
        return;
    }
    $userdate = json_decode($user['Processing_value'], true);
    $stmt = $pdo->prepare("INSERT INTO app (name, link) VALUES (:name, :link)");
    $stmt->bindParam(':name', $userdate['name'], PDO::PARAM_STR);
    $stmt->bindParam(':link', $text, PDO::PARAM_STR);
    $stmt->execute();
    sendmessage($from_id, "✅ لینک اپ شما با موفقیت اضافه گردید.", $keyboardlinkapp, 'HTML');
    step("home", $from_id);
} elseif ($text == "❌ حذف برنامه") {
    sendmessage($from_id, "📌 برای حذف برنامه از لیست زیر نام برنامه را انتخاب کنید", $json_list_remove_helpـlink, 'HTML');
    step("getnameappforremove", $from_id);
} elseif ($user['step'] == "getnameappforremove") {
    sendmessage($from_id, "✅ برنامه با موفقیت حذف گردید.", $keyboardlinkapp, 'HTML');
    step('home', $from_id);
    $stmt = $pdo->prepare("DELETE FROM app WHERE name = :name");
    $stmt->bindParam(':name', $text, PDO::PARAM_STR);
    $stmt->execute();
} elseif ($text == "⚙️ وضعیت قابلیت ها پنل" && $adminrulecheck['rule'] == "administrator") {
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (!in_array($panel['subvip'], ['offsubvip', 'onsubvip'])) {
        update("marzban_panel", "subvip", "offsubvip", "code_panel", $panel['code_panel']);
        $panel = select("marzban_panel", "*", "code_panel", $panel['code_panel'], "select");
    }
    $customvlume = json_decode($panel['customvolume'], true);
    $statusconfig = [
        'onconfig' => $textbotlang['Admin']['Status']['statuson'],
        'offconfig' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['config']];
    $statussublink = [
        'onsublink' => $textbotlang['Admin']['Status']['statuson'],
        'offsublink' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['sublink']];
    $statusshowbuy = [
        'active' => $textbotlang['Admin']['Status']['statuson'],
        'disable' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['status']];
    $statusshowtest = [
        'ONTestAccount' => $textbotlang['Admin']['Status']['statuson'],
        'OFFTestAccount' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['TestAccount']];
    $statusconnecton = [
        'onconecton' => $textbotlang['Admin']['Status']['statuson'],
        'offconecton' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['conecton']];
    $status_extend = [
        'on_extend' => $textbotlang['Admin']['Status']['statuson'],
        'off_extend' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['status_extend']];
    $changeloc = [
        'onchangeloc' => $textbotlang['Admin']['Status']['statuson'],
        'offchangeloc' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['changeloc']];
    $inbocunddisable = [
        'oninbounddisable' => $textbotlang['Admin']['Status']['statuson'],
        'offinbounddisable' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['inboundstatus']];
    $subvip = [
        'onsubvip' => $textbotlang['Admin']['Status']['statuson'],
        'offsubvip' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['subvip']];
    $customstatusf = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$customvlume['f']];
    $customstatusn = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$customvlume['n']];
    $customstatusn2 = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$customvlume['n2']];
    $on_hold_test = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$panel['on_hold_test']];
    $Bot_Status = [
        'inline_keyboard' => [
            [
                ['text' => $statusshowbuy, 'callback_data' => "editpanel-statusbuy-{$panel['status']}-{$panel['code_panel']}"],
                ['text' => "🖥 نمایش پنل", 'callback_data' => "none"],
            ],
            [
                ['text' => $statusshowtest, 'callback_data' => "editpanel-statustest-{$panel['TestAccount']}-{$panel['code_panel']}"],
                ['text' => "🎁 نمایش تست", 'callback_data' => "none"],
            ],
            [
                ['text' => $status_extend, 'callback_data' => "editpanel-stautsextend-{$panel['status_extend']}-{$panel['code_panel']}"],
                ['text' => "🔋 وضعیت تمدید", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusf, 'callback_data' => "editpanel-customstatusf-{$customvlume['f']}-{$panel['code_panel']}"],
                ['text' => "♻️ سرویس دلخواه گروه f", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusn, 'callback_data' => "editpanel-customstatusn-{$customvlume['n']}-{$panel['code_panel']}"],
                ['text' => "♻️ سرویس دلخواه گروه n", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusn2, 'callback_data' => "editpanel-customstatusn2-{$customvlume['n2']}-{$panel['code_panel']}"],
                ['text' => "♻️ سرویس دلخواه گروه n2", 'callback_data' => "none"],
            ]
        ]
    ];
    if (!in_array($panel['type'], ['Manualsale', "WGDashboard", 'hiddify'])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statusconfig, 'callback_data' => "editpanel-stautsconfig-{$panel['config']}-{$panel['code_panel']}"],
            ['text' => "⚙️ ارسال کانفیگ", 'callback_data' => "none"],
        ];
    }
    if (!in_array($panel['type'], ['Manualsale', "WGDashboard", 'hiddify'])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statussublink, 'callback_data' => "editpanel-sublink-{$panel['sublink']}-{$panel['code_panel']}"],
            ['text' => "⚙️ ارسال لینک اشتراک", 'callback_data' => "none"],
        ];
    }
    if (in_array($panel['type'], ['marzban', "x-ui_single", "marzneshin"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statusconnecton, 'callback_data' => "editpanel-connecton-{$panel['conecton']}-{$panel['code_panel']}"],
            ['text' => "📊 اولین اتصال", 'callback_data' => "none"],
        ];
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $on_hold_test, 'callback_data' => "editpanel-on_hold_Test-{$panel['on_hold_test']}-{$panel['code_panel']}"],
            ['text' => "📊 اولین اتصال اکانت تست", 'callback_data' => "none"],
        ];
    }
    if (!in_array($panel['type'], ["Manualsale", "WGDashboard"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $changeloc, 'callback_data' => "editpanel-changeloc-{$panel['changeloc']}-{$panel['code_panel']}"],
            ['text' => "🌍 تغییر لوکیشن", 'callback_data' => "none"],
        ];
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $subvip, 'callback_data' => "editpanel-subvip-{$panel['subvip']}-{$panel['code_panel']}"],
            ['text' => "💎 لینک ساب اختصاصی", 'callback_data' => "none"],
        ];
    }
    if (in_array($panel['type'], ["marzban"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $inbocunddisable, 'callback_data' => "editpanel-inbocunddisable-{$panel['inboundstatus']}-{$panel['code_panel']}"],
            ['text' => "📍 اکانت غیرفعال", 'callback_data' => "none"],
        ];
    }
    if ($panel['type'] == "ibsng" || $panel['type'] == "mikrotik") {
        unset($Bot_Status['inline_keyboard'][2]);
        unset($Bot_Status['inline_keyboard'][3]);
        unset($Bot_Status['inline_keyboard'][4]);
        unset($Bot_Status['inline_keyboard'][5]);
        unset($Bot_Status['inline_keyboard'][6]);
        unset($Bot_Status['inline_keyboard'][7]);
        unset($Bot_Status['inline_keyboard'][8]);
        unset($Bot_Status['inline_keyboard'][9]);
    }
    $Bot_Status['inline_keyboard'][] = [
        ['text' => "❌ بستن", 'callback_data' => 'close_stat']
    ];
    $Bot_Status['inline_keyboard'] = array_values($Bot_Status['inline_keyboard']);
    $Bot_Status = json_encode($Bot_Status);
    sendmessage($from_id, $textbotlang['Admin']['Status']['BotTitle'], $Bot_Status, 'HTML');
} elseif (preg_match('/^editpanel-(.*)-(.*)-(.*)/', $datain, $dataget)) {
    $type = $dataget[1];
    $value = $dataget[2];
    $code_panel = $dataget[3];
    if ($type == "stautsconfig") {
        if ($value == "onconfig") {
            $valuenew = "offconfig";
        } else {
            $valuenew = "onconfig";
        }
        update("marzban_panel", "config", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "sublink") {
        if ($value == "onsublink") {
            $valuenew = "offsublink";
        } else {
            $valuenew = "onsublink";
        }
        update("marzban_panel", "sublink", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "statusbuy") {
        if ($value == "active") {
            $valuenew = "disable";
        } else {
            $valuenew = "active";
        }
        update("marzban_panel", "status", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "statustest") {
        if ($value == "ONTestAccount") {
            $valuenew = "OFFTestAccount";
        } else {
            $valuenew = "ONTestAccount";
        }
        update("marzban_panel", "TestAccount", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "connecton") {
        if ($value == "onconecton") {
            $valuenew = "offconecton";
        } else {
            $valuenew = "onconecton";
        }
        update("marzban_panel", "conecton", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "stautsextend") {
        if ($value == "on_extend") {
            $valuenew = "off_extend";
        } else {
            $valuenew = "on_extend";
        }
        update("marzban_panel", "status_extend", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "changeloc") {
        if ($value == "onchangeloc") {
            $valuenew = "offchangeloc";
        } else {
            $valuenew = "onchangeloc";
        }
        update("marzban_panel", "changeloc", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "inbocunddisable") {
        if ($value == "oninbounddisable") {
            $valuenew = "offinbounddisable";
        } else {
            $valuenew = "oninbounddisable";
        }
        update("marzban_panel", "inboundstatus", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "subvip") {
        if ($value == "onsubvip") {
            $valuenew = "offsubvip";
        } else {
            $valuenew = "onsubvip";
        }
        update("marzban_panel", "subvip", $valuenew, "code_panel", $code_panel);
    } elseif ($type == "customstatusf") {
        $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
        $customvlume = json_decode($panel['customvolume'], true);
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        $customvlume['f'] = $valuenew;
        update("marzban_panel", "customvolume", json_encode($customvlume), "code_panel", $code_panel);
    } elseif ($type == "customstatusn") {
        $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
        $customvlume = json_decode($panel['customvolume'], true);
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        $customvlume['n'] = $valuenew;
        update("marzban_panel", "customvolume", json_encode($customvlume), "code_panel", $code_panel);
    } elseif ($type == "customstatusn2") {
        $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
        $customvlume = json_decode($panel['customvolume'], true);
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        $customvlume['n2'] = $valuenew;
        update("marzban_panel", "customvolume", json_encode($customvlume), "code_panel", $code_panel);
    } elseif ($type == "on_hold_Test") {
        if ($value == "0") {
            $valuenew = "1";
        } else {
            $valuenew = "0";
        }
        update("marzban_panel", "on_hold_test", $valuenew, "code_panel", $code_panel);
    }
    $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");

    $customvlume = json_decode($panel['customvolume'], true);
    if (!is_array($customvlume)) {
        $customvlume = [];
    }
    $customvlume = array_merge([
        'f' => '0',
        'n' => '0',
        'n2' => '0',
    ], $customvlume);

    $statusconfig = [
        'onconfig' => $textbotlang['Admin']['Status']['statuson'],
        'offconfig' => $textbotlang['Admin']['Status']['statusoff'],
    ][$panel['config'] ?? 'offconfig'] ?? $textbotlang['Admin']['Status']['statusoff'];

    $statussublink = [
        'onsublink' => $textbotlang['Admin']['Status']['statuson'],
        'offsublink' => $textbotlang['Admin']['Status']['statusoff'],
    ][$panel['sublink'] ?? 'offsublink'] ?? $textbotlang['Admin']['Status']['statusoff'];

    $statusshowbuy = [
        'active' => $textbotlang['Admin']['Status']['statuson'],
        'disable' => $textbotlang['Admin']['Status']['statusoff'],
    ][$panel['status'] ?? 'disable'] ?? $textbotlang['Admin']['Status']['statusoff'];

    $statusshowtest = [
        'ONTestAccount' => $textbotlang['Admin']['Status']['statuson'],
        'OFFTestAccount' => $textbotlang['Admin']['Status']['statusoff'],
    ][$panel['TestAccount'] ?? 'OFFTestAccount'] ?? $textbotlang['Admin']['Status']['statusoff'];

    $statusconnecton = [
        'onconecton' => $textbotlang['Admin']['Status']['statuson'],
        'offconecton' => $textbotlang['Admin']['Status']['statusoff'],
    ][$panel['conecton'] ?? 'offconecton'] ?? $textbotlang['Admin']['Status']['statusoff'];

    $status_extend = [
        'on_extend' => $textbotlang['Admin']['Status']['statuson'],
        'off_extend' => $textbotlang['Admin']['Status']['statusoff'],
    ][$panel['status_extend'] ?? 'off_extend'] ?? $textbotlang['Admin']['Status']['statusoff'];

    $changeloc = [
        'onchangeloc' => $textbotlang['Admin']['Status']['statuson'],
        'offchangeloc' => $textbotlang['Admin']['Status']['statusoff'],
    ][$panel['changeloc'] ?? 'offchangeloc'] ?? $textbotlang['Admin']['Status']['statusoff'];

    $inbocunddisable = [
        'oninbounddisable' => $textbotlang['Admin']['Status']['statuson'],
        'offinbounddisable' => $textbotlang['Admin']['Status']['statusoff'],
    ][$panel['inboundstatus'] ?? 'offinbounddisable'] ?? $textbotlang['Admin']['Status']['statusoff'];

    $subvip = [
        'onsubvip' => $textbotlang['Admin']['Status']['statuson'],
        'offsubvip' => $textbotlang['Admin']['Status']['statusoff'],
    ][$panel['subvip'] ?? 'offsubvip'] ?? $textbotlang['Admin']['Status']['statusoff'];

    $customstatusf = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff'],
    ][$customvlume['f'] ?? '0'] ?? $textbotlang['Admin']['Status']['statusoff'];

    $customstatusn = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff'],
    ][$customvlume['n'] ?? '0'] ?? $textbotlang['Admin']['Status']['statusoff'];

    $customstatusn2 = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff'],
    ][$customvlume['n2'] ?? '0'] ?? $textbotlang['Admin']['Status']['statusoff'];

    $on_hold_test = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff'],
    ][$panel['on_hold_test'] ?? '0'] ?? $textbotlang['Admin']['Status']['statusoff'];
    $Bot_Status = [
        'inline_keyboard' => [
            [
                ['text' => $statusshowbuy, 'callback_data' => "editpanel-statusbuy-{$panel['status']}-{$panel['code_panel']}"],
                ['text' => "🖥 نمایش پنل", 'callback_data' => "none"],
            ],
            [
                ['text' => $statusshowtest, 'callback_data' => "editpanel-statustest-{$panel['TestAccount']}-{$panel['code_panel']}"],
                ['text' => "🎁 نمایش تست", 'callback_data' => "none"],
            ],
            [
                ['text' => $status_extend, 'callback_data' => "editpanel-stautsextend-{$panel['status_extend']}-{$panel['code_panel']}"],
                ['text' => "🔋 وضعیت تمدید", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusf, 'callback_data' => "editpanel-customstatusf-{$customvlume['f']}-{$panel['code_panel']}"],
                ['text' => "♻️ سرویس دلخواه گروه f", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusn, 'callback_data' => "editpanel-customstatusn-{$customvlume['n']}-{$panel['code_panel']}"],
                ['text' => "♻️ سرویس دلخواه گروه n", 'callback_data' => "none"],
            ],
            [
                ['text' => $customstatusn2, 'callback_data' => "editpanel-customstatusn2-{$customvlume['n2']}-{$panel['code_panel']}"],
                ['text' => "♻️ سرویس دلخواه گروه n2", 'callback_data' => "none"],
            ]
        ]
    ];
    if (!in_array($panel['type'], ['Manualsale', "WGDashboard", 'hiddify'])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statusconfig, 'callback_data' => "editpanel-stautsconfig-{$panel['config']}-{$panel['code_panel']}"],
            ['text' => "⚙️ ارسال کانفیگ", 'callback_data' => "none"],
        ];
    }
    if (!in_array($panel['type'], ['Manualsale', "WGDashboard", 'hiddify'])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statussublink, 'callback_data' => "editpanel-sublink-{$panel['sublink']}-{$panel['code_panel']}"],
            ['text' => "⚙️ ارسال لینک اشتراک", 'callback_data' => "none"],
        ];
    }
    if (in_array($panel['type'], ['marzban', "x-ui_single", "marzneshin"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $statusconnecton, 'callback_data' => "editpanel-connecton-{$panel['conecton']}-{$panel['code_panel']}"],
            ['text' => "📊 اولین اتصال", 'callback_data' => "none"],
        ];
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $on_hold_test, 'callback_data' => "editpanel-on_hold_Test-{$panel['on_hold_test']}-{$panel['code_panel']}"],
            ['text' => "📊 اولین اتصال اکانت تست", 'callback_data' => "none"],
        ];
    }
    if (!in_array($panel['type'], ["Manualsale", "WGDashboard"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $changeloc, 'callback_data' => "editpanel-changeloc-{$panel['changeloc']}-{$panel['code_panel']}"],
            ['text' => "🌍 تغییر لوکیشن", 'callback_data' => "none"],
        ];
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $subvip, 'callback_data' => "editpanel-subvip-{$panel['subvip']}-{$panel['code_panel']}"],
            ['text' => "💎 لینک ساب اختصاصی", 'callback_data' => "none"],
        ];
    }
    if (in_array($panel['type'], ["marzban"])) {
        $Bot_Status['inline_keyboard'][] = [
            ['text' => $inbocunddisable, 'callback_data' => "editpanel-inbocunddisable-{$panel['inboundstatus']}-{$panel['code_panel']}"],
            ['text' => "📍 اکانت غیرفعال", 'callback_data' => "none"],
        ];
    }
    $Bot_Status['inline_keyboard'][] = [
        ['text' => "❌ بستن", 'callback_data' => 'close_stat']
    ];
    $Bot_Status = json_encode($Bot_Status);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['BotTitle'], $Bot_Status);
} elseif ($datain == "startelegram") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $Startelegram, 'HTML');
} elseif ($text == "⬇️ حداقل مبلغ استار") {
    sendmessage($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainaqstar", $from_id);
} elseif ($user['step'] == "getmainaqstar") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $Startelegram, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancestar");
} elseif ($text == "⬆️ حداکثر مبلغ استار") {
    sendmessage($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("maxbalancestar", $from_id);
} elseif ($user['step'] == "maxbalancestar") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $Startelegram, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancestar");
} elseif ($text == "⬇️ حداقل مبلغ nowpayment") {
    sendmessage($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("getmainaqnowpayment", $from_id);
} elseif ($user['step'] == "getmainaqnowpayment") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $nowpayment_setting_keyboard, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "minbalancenowpayment");
} elseif ($text == "⬆️ حداکثر مبلغ nowpayment") {
    sendmessage($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
    step("maxbalancenowpayment", $from_id);
} elseif ($user['step'] == "maxbalancenowpayment") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $nowpayment_setting_keyboard, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancenowpayment");
} elseif ($text == "📚 تنظیم آموزش استار" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
    step("gethelpstar", $from_id);
} elseif ($user['step'] == "gethelpstar") {
    if ($text) {
        if (intval($text) == 2) {
            update("PaySetting", "ValuePay", "0", "NamePay", "helpstar");
        } else {
            $data = json_encode(array(
                'type' => "text",
                'text' => $text
            ));
            update("PaySetting", "ValuePay", $data, "NamePay", "helpstar");
        }
    } elseif ($photo) {
        $data = json_encode(array(
            'type' => "photo",
            'text' => $caption,
            'photoid' => $photoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpstar");
    } elseif ($video) {
        $data = json_encode(array(
            'type' => "video",
            'text' => $caption,
            'videoid' => $videoid
        ));
        update("PaySetting", "ValuePay", $data, "NamePay", "helpstar");
    } else {
        sendmessage($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $Startelegram, 'HTML');
} elseif ($text == "💰 کش بک استار") {
    sendmessage($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید )", $backadmin, 'HTML');
    step("chashbackstar", $from_id);
} elseif ($user['step'] == "chashbackstar") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ مبلغ با موفقیت ذخیره گردید.", $Startelegram, 'HTML');
    step("home", $from_id);
    update("PaySetting", "ValuePay", $text, "NamePay", "chashbackstar");
} elseif ($text == "🔋 تنظیم سریع قیمت حجم") {
    sendmessage($from_id, "📌 قبل ارسال اطلاعات متن زیر را مطالعه فرمایید . 
۱ - این قابلیت برای سرویس دلخواه می باشد.
۲ - در صورتی که تمامی پنل های شما یک قیمت هستند و بجای تنظیم تک تک قیمت ها می توانید با استفاده از این قابلیت بصورت یکجا قیمت ها را تنظیم نمایید.
۳ - با تنظیم قیمت در این بخش قابل بازگشت نیست.


جهت تنظیم قیمت ابتدا قیمت گروه f را ارسال نمایید.", $backadmin, 'HTML');
    step("getpricef", $from_id);
} elseif ($user['step'] == "getpricef") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "pricef", $text);
    sendmessage($from_id, "📌 قیمت گروه n را ارسال نمایید.", $backadmin, 'HTML');
    step("getpricnn", $from_id);
} elseif ($user['step'] == "getpricnn") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "pricen", $text);
    sendmessage($from_id, "📌 قیمت گروه n2 را ارسال نمایید.", $backadmin, 'HTML');
    step("getpricnn2", $from_id);
} elseif ($user['step'] == "getpricnn2") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $pricelist = json_encode(array(
        'f' => $userdata['pricef'],
        'n' => $userdata['pricen'],
        'n2' => $text
    ));
    update("marzban_panel", "pricecustomvolume", $pricelist, null, null);
    sendmessage($from_id, "✅ قیمت با موفقیت تنظیم شد", $keyboardadmin, 'HTML');
    step("home", $from_id);
} elseif ($text == "⏳ تنظیم سریع قیمت زمان") {
    sendmessage($from_id, "📌 قبل ارسال اطلاعات متن زیر را مطالعه فرمایید . 
۱ - این قابلیت برای سرویس دلخواه می باشد.
۲ - در صورتی که تمامی پنل های شما یک قیمت هستند و بجای تنظیم تک تک قیمت ها می توانید با استفاده از این قابلیت بصورت یکجا قیمت ها را تنظیم نمایید.
۳ - با تنظیم قیمت در این بخش قابل بازگشت نیست.


جهت تنظیم قیمت ابتدا قیمت گروه f را ارسال نمایید.", $backadmin, 'HTML');
    step("getpriceftime", $from_id);
} elseif ($user['step'] == "getpriceftime") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("clear", "pricef", $text);
    sendmessage($from_id, "📌 قیمت گروه n را ارسال نمایید.", $backadmin, 'HTML');
    step("getpricnntime", $from_id);
} elseif ($user['step'] == "getpricnntime") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "pricen", $text);
    sendmessage($from_id, "📌 قیمت گروه n2 را ارسال نمایید.", $backadmin, 'HTML');
    step("getpricnn2time", $from_id);
} elseif ($user['step'] == "getpricnn2time") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    $userdata = json_decode($user['Processing_value'], true);
    $pricelist = json_encode(array(
        'f' => $userdata['pricef'],
        'n' => $userdata['pricen'],
        'n2' => $text
    ));
    update("marzban_panel", "pricecustomtime", $pricelist, null, null);
    sendmessage($from_id, "✅ قیمت با موفقیت تنظیم شد", $keyboardadmin, 'HTML');
    step("home", $from_id);
} elseif ($datain == "changeloclimit") {
    sendmessage($from_id, "📌 یک گزینه را انتخاب نمایید.
۱ - محدودیت کلی کاربر در کل چند بار می تواند تغییر لوکیشن انجام دهد.
۲ - محدودیت رایگان  کاربر از محدودیت کلی چند بار می تواند رایگان تغییر لوکیشن دهد.", $keyboardchangelimit, 'HTML');
} elseif ($text == "↙️ محدودیت کلی") {
    $limitnumber = json_decode($setting['limitnumber'], true);
    sendmessage($from_id, "📌  محدودیت کلی که کاربر می تواند تغییر لوکیشن انجام دهد را ارسال کنید توجه داشته باشید این محدودیت برای تمام کانفیگ ها  است
محدودیت فعلی : {$limitnumber['all']}", $backadmin, 'HTML');
    step("limitchangeall", $from_id);
} elseif ($user['step'] == "limitchangeall") {
    sendmessage($from_id, "✅ محدودیت با موفقیت تنظیم شد.", $keyboardchangelimit, 'HTML');
    step("home", $from_id);
    $value = json_decode($setting['limitnumber'], true);
    $value['all'] = intval($text);
    update("setting", "limitnumber", json_encode($value), null, null);
} elseif ($text == "🆓 محدودیت رایگان") {
    $limitnumber = json_decode($setting['limitnumber'], true);
    sendmessage($from_id, "📌  محدودیت رایگانی که کاربر می تواند تغییر لوکیشن انجام دهد را ارسال کنید توجه داشته باشید این محدودیت برای تمام کانفیگ ها  است
محدودیت فعلی : {$limitnumber['free']}", $backadmin, 'HTML');
    step("limitfreechangefree", $from_id);
} elseif ($user['step'] == "limitfreechangefree") {
    sendmessage($from_id, "✅ محدودیت با موفقیت تنظیم شد.", $keyboardchangelimit, 'HTML');
    step("home", $from_id);
    $value = json_decode($setting['limitnumber'], true);
    $value['free'] = intval($text);
    update("setting", "limitnumber", json_encode($value), null, null);
} elseif ($text == "🔄 ریست محدودیت کل کاربران") {
    $keyboarddata = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "تایید و صفر شدن", 'callback_data' => 'reasetchangeloc'],
            ],
        ]
    ]);
    sendmessage($from_id, "📌 با تأیید گزینه زیر، تمام تغییر لوکیشن هایی که توسط کاربر انجام شده است صفر خواهد شد. در صورت موافقت، روی گزینه زیر کلیک کنید.", $keyboarddata, 'HTML');
} elseif ($datain == "reasetchangeloc") {
    Editmessagetext($from_id, $message_id, "✅ تمامی محدودیت کاربران صفر شد.", null);
    update("user", "limitchangeloc", "0", null, null);
} elseif (preg_match('/changeloclimitbyuser_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    savedata("clear", "id_user", $id_user);
    sendmessage($from_id, "📌 محدودیت جدیدی که میخواهید برای کاربر تنظیم کنید را ارسال کنید توجه داشته باشید این قابلیت تعداد تعییر لوکیشن انجام شده را تغییر میدهد", $backadmin, 'HTML');
    step("getlimitchangenewbyuser", $from_id);
} elseif ($user['step'] == "getlimitchangenewbyuser") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    step("home", $from_id);
    $userdata = json_decode($user['Processing_value'], true);
    update("user", "limitchangeloc", $text, "id", $userdata['id_user']);
    sendmessage($from_id, "✅ تعداد استفاده کاربر با موفقیت ذخیره گردید.", $keyboardadmin, 'HTML');
} elseif (preg_match('/hidepanel_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    savedata("clear", "id_user", $id_user);
    sendmessage($from_id, "❌ پنل هایی که می خواهید برای این نماینده نشان داده نشود از دکمه  زیر انتخاب نمایید بعد از انتخاب دستور /finish را ارسال کنید تا ذخیره شود.", $json_list_marzban_panel, 'HTML');
    step("getpanelhidebotsaz", $from_id);
} elseif ($text == "/finish") {
    sendmessage($from_id, "✅ ذخیره پنل ها با موفقیت انجام و پنل های برای کاربر مخفی شد.", $keyboardadmin, 'HTML');
    step("home", $from_id);
} elseif ($user['step'] == "getpanelhidebotsaz") {
    $userdata = json_decode($user['Processing_value'], true);
    $list_panel = json_decode(select("botsaz", "hide_panel", "id_user", $userdata['id_user'], "select")['hide_panel'], true);
    if (in_array($text, $list_panel)) {
        sendmessage($from_id, "❌ پنل از قبل اضافه شده است", null, 'HTML');
        return;
    }
    $list_panel[] = $text;
    update("botsaz", "hide_panel", json_encode($list_panel), "id_user", $userdata['id_user']);
    sendmessage($from_id, "✅ پنل انتخاب شد  پس از اتمام دستور /finish را ارسال نمایید تا ذخیره نهایی شود.", null, 'HTML');
} elseif (preg_match('/removehide_(\w+)/', $datain, $datagetr)) {
    global $list_hide_panel;
    $id_user = $datagetr[1];
    savedata("clear", "id_user", $id_user);
    $list_panel = json_decode(select("botsaz", "hide_panel", "id_user", $id_user, "select")['hide_panel'], true);
    $list_hide_panel = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($list_panel as $panelname) {
        $list_hide_panel['keyboard'][] = [
            ['text' => $panelname]
        ];
    }
    $list_hide_panel['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backadmin']],
    ];
    $list_hide_panel = json_encode($list_hide_panel);
    sendmessage($from_id, "❌ از لیست زیر پنل هایی که میخواهید مجددا در ربات نماینده نشان داده شود را  انتخاب نمایید بعد از انتخاب تمامی پنل ها  دستور /remove را ارسال کنید تا ذخیره شود.", $list_hide_panel, 'HTML');
    step("getremovehidepanel", $from_id);
} elseif ($text == "/remove") {
    sendmessage($from_id, "✅ نمایش پنل ها با موفقیت انجام و پنل های برای کاربر فعال شد.", $keyboardadmin, 'HTML');
    step("home", $from_id);
} elseif ($user['step'] == "getremovehidepanel") {
    $userdata = json_decode($user['Processing_value'], true);
    $list_panel = json_decode(select("botsaz", "hide_panel", "id_user", $userdata['id_user'], "select")['hide_panel'], true);
    if (!in_array($text, $list_panel)) {
        sendmessage($from_id, "❌ پنل در لیست وجود ندارد", null, 'HTML');
        return;
    }
    $count = 0;
    foreach ($list_panel as $panel) {
        if ($panel == $text) {
            unset($list_panel[$count]);
            break;
        }
        $count += 1;
    }
    $list_panel = array_values($list_panel);
    update("botsaz", "hide_panel", json_encode($list_panel), "id_user", $userdata['id_user']);
    sendmessage($from_id, "✅ پنل انتخاب شد  پس از اتمام دستور /remove را ارسال نمایید تا ذخیره نهایی شود.", null, 'HTML');
} elseif ($datain == "voloume_or_day_all") {
    $userslistData = '[]';
    if (is_file('cronbot/username.json')) {
        $fileContents = file_get_contents('cronbot/username.json');
        if ($fileContents !== false && $fileContents !== '') {
            $userslistData = $fileContents;
        }
    }
    $userslist = json_decode($userslistData, true);
    if (is_array($userslist) && count($userslist) != 0) {
        sendmessage($from_id, "❌ سیستم ارسال هدیه درحال انجام عملیات است پس از پایان و اطلاع رسانی  می توانید پیام جدید را ارسال نمایید.", $keyboardadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "📌 برای سرویس های کدام پنل میخواهید حجم یا زمان هدیه دهید؟", $json_list_marzban_panel, "html");
    step("getpanelgift", $from_id);
} elseif ($user['step'] == "getpanelgift") {
    $panel = select("marzban_panel", "*", "name_panel", $text, "count");
    if ($panel == 0) {
        sendmessage($from_id, "❌ پنل وجود ندارد", null, "html");
        return;
    }
    savedata("clear", "name_panel", $text);
    $keyboardstatistics = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🔋 حجم", 'callback_data' => 'typegift_volume'],
                ['text' => "⏳ زمان", 'callback_data' => 'typegift_day'],
            ],
        ]
    ]);
    sendmessage($from_id, "📌 یکی از هدیه های زیر را انتخاب نمایید.", $keyboardstatistics, "html");
    step('home', $from_id);
} elseif (preg_match('/typegift_(\w+)/', $datain, $datagetr)) {
    $typegift = $datagetr[1];
    savedata("save", "typegift", $typegift);
    deletemessage($from_id, $message_id);
    if ($typegift == "volume") {
        sendmessage($from_id, "📌 چند گیگ حجم می خواهید به سرویس های کاربر اضافه شود", $backadmin, "html");
    } else {
        sendmessage($from_id, "📌 چند روز می خواهید به سرویس های کاربران اضافه شود", $backadmin, "html");
    }
    step("getvaluegift", $from_id);
} elseif ($user['step'] == "getvaluegift") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "value", $text);
    sendmessage($from_id, "📌 متنی که می خواهید برای کاربر ارسال شود را ارسال کنید", $backadmin, "html");
    step("gettextgift", $from_id);
} elseif ($user['step'] == "gettextgift") {
    savedata("save", "text", $text);
    savedata("save", "id_admin", $from_id);
    $keyboardstatistics = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ تایید و شروع فرآیند", 'callback_data' => 'startgift'],
            ],
        ]
    ]);
    sendmessage($from_id, "📌 ادمین عزیز با تایید بر روی گزینه زیر فرآیند اعمال هدیه ها آغاز خواهد شد توجه داشته باشید با توجه به محدودیت ها اعمال هدیه زمان بر خواهد بود.", $keyboardstatistics, "html");
    step("home", $from_id);
} elseif ($datain == "startgift") {
    $keyboardstatistics = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "❌ لفو ارسال هدیه", 'callback_data' => 'cancel_gift'],
            ],
        ]
    ]);
    $userdata = json_decode($user['Processing_value'], true);
    if (!isset($userdata['typegift'])) {
        sendmessage($from_id, "❌ خطایی رخ داده است مراحل را از اول طی کنید.", $keyboardstatistics, "html");
        return;
    }
    $message_id = Editmessagetext($from_id, $message_id, "✅ عملیات ارسال هدیه با موفقیت آغاز گردید پس از اضافه شدن و اتمام به شما اطلاع داده می شود.", $keyboardstatistics);
    $userdata['id_message'] = $message_id['result']['message_id'];
    $stmt = $pdo->prepare("SELECT username FROM invoice WHERE  (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND Service_location = '{$userdata['name_panel']}' AND name_product != 'سرویس تست'");
    $stmt->execute();
    $userslist = json_encode($stmt->fetchAll());
    file_put_contents('cronbot/gift', json_encode($userdata));
    file_put_contents('cronbot/username.json', $userslist);
} elseif ($datain == "cancel_gift") {
    if (file_exists('cronbot/username.json')) unlink('cronbot/username.json');
    if (file_exists('cronbot/gift')) unlink('cronbot/gift');
    deletemessage($from_id, $message_id);
    sendmessage($from_id, "📌 ارسال هدیه لغو گردید.", null, 'HTML');
} elseif (preg_match('/expireset_(\w+)/', $datain, $datagetr)) {
    $id_user = $datagetr[1];
    savedata("clear", "id_user", $id_user);
    sendmessage($from_id, "🕘 زمان انقضا نمایندگی را ارسال نمایید. پس از پایان تعداد روز تعیین شده کاربر از حالت نمایندگی خارج شده و گروه کاربر f خواهد شد.
توجه داشته باشید این قابلیت ارتباطی با قابلیت ربات ساز یا ربات فروش نماینده ندارد و فقط مربوط به ربات اصلی شما است

📌 تعداد روز را ارسال نمایید", $backadmin, 'HTML');
    step("gettime_expire_agent", $from_id);
} elseif ($user['step'] == "gettime_expire_agent") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    step("home", $from_id);
    $userdate = json_decode($user['Processing_value'], true);
    $timestamp = time() + (intval(value: $text) * 86400);
    update("user", "expire", $timestamp, "id", $userdate['id_user']);
    sendmessage($from_id, "✅ تاریخ انقضا تنظیم شد.
📌 پس از پایان زمان گروه کاربری کاربر به f تغییر داده می شود و به کاربر اطلاع داده می شود.", $keyboardadmin, 'HTML');
} elseif ($text == "♻️ نمایش گروهی شماره کارت") {
    sendmessage($from_id, "📌 لیست آیدی هایی که  می خواهید شماره کارت برایشان نشان داده شود را ارسال شود 
مثال : 
1234435423
23423131", $backadmin, 'HTML');
    step("getlistidcart", $from_id);
} elseif ($user['step'] == "getlistidcart") {
    $list = explode("\n", $text);
    foreach ($list as $id_user) {
        if (!in_array($id_user, $users_ids)) {
            sendmessage($from_id, "📌 کاربر با آیدی عددی $id_user در  دیتابیس وجود ندارد", $backadmin, 'HTML');
            continue;
        }
        update("user", "cardpayment", "1", "id", $id_user);
    }
    sendmessage($from_id, "✅ شماره کارت برای کاربران ارسال شده فعال گردید.", $CartManage, 'HTML');
    step("home", $from_id);
} elseif ($text == "📄 خروجی افراد شماره کارت فعال") {
    $listusers = select("user", "id", "cardpayment", "1", "fetchAll");
    if (!$listusers) {
        sendmessage($from_id, "📌 برای کاربری شماره کارت فعال نشده است", $CartManage, 'HTML');
        return;
    }
    $filename = 'cartlist.txt';
    foreach ($listusers as $id_user) {
        file_put_contents($filename, $id_user['id'] . "\n", FILE_APPEND);
    }
    sendDocument($from_id, $filename, "🪪 لیست کاربرانی که شماره کارت برای آنها فعال است");
    unlink($filename);
} elseif ($text == "🎉 پورسانت فقط برای خرید اول" && $adminrulecheck['rule'] == "administrator") {
    $marzbanporsant_one_buy = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanporsant_one_buy['porsant_one_buy'], 'callback_data' => $marzbanporsant_one_buy['porsant_one_buy']],
            ],
        ]
    ]);
    sendmessage($from_id, "می‌توانید تعیین کنید که پورسانت به کاربر فقط برای اولین خرید زیرمجموعه‌اش داده شود یا برای همه خریدهای او.", $keyboardDiscountaffiliates, 'HTML');
} elseif ($datain == "on_buy_porsant") {
    update("affiliates", "porsant_one_buy", "off_buy_porsant");
    $marzbanporsant_one_buy = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanporsant_one_buy['porsant_one_buy'], 'callback_data' => $marzbanporsant_one_buy['porsant_one_buy']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "می‌توانید تعیین کنید که پورسانت به کاربر فقط برای اولین خرید زیرمجموعه‌اش داده شود یا برای همه خریدهای او.", $keyboardDiscountaffiliates);
} elseif ($datain == "off_buy_porsant") {
    update("affiliates", "porsant_one_buy", "on_buy_porsant");
    $marzbanporsant_one_buy = select("affiliates", "*", null, null, "select");
    $keyboardDiscountaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $marzbanporsant_one_buy['porsant_one_buy'], 'callback_data' => $marzbanporsant_one_buy['porsant_one_buy']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "می‌توانید تعیین کنید که پورسانت به کاربر فقط برای اولین خرید زیرمجموعه‌اش داده شود یا برای همه خریدهای او.", $keyboardDiscountaffiliates);
} elseif ($text == "متن توضیحات درخواست نمایندگی" && $adminrulecheck['rule'] == "administrator") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . "<code>{$datatextbot['text_request_agent_dec']}</code>", $backadmin, 'HTML');
    step('text_request_agent_dec', $from_id);
} elseif ($user['step'] == "text_request_agent_dec") {
    if (!$text) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'], $textbot, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_request_agent_dec");
    step('home', $from_id);
} elseif (preg_match('/changestatusadmin_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "on_hold") {
        sendmessage($from_id, "❌ هنوز به کانفیگ متصل نشده است کانفیگ و امکان تغییر وضعیت سرویس وجود ندارد. بعد از متصل شدن به کانفیگ می توانید از این قابلیت استفاده نمایید.", null, 'html');
        return;
    }
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
        return;
    }
    if ($DataUserOut['status'] == "active") {
        $confirmdisableaccount = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '✅ تایید و غیرفعال کردن کانفیگ', 'callback_data' => "confirmaccountdisableadmin_" . $id_invoice],
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "manageinvoice_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 با تایید گزینه زیر کانفیگ شما خاموش و دیگر امکان اتصال به کانفیگ وجود ندارد.
⚠️ در صورتی که میخواهید مجدد کانفیگ فعال شود باید از بخش مدیریت سرویس دکمه <u>💡 روشن کردن اکانت</u> را کلیک کنید", $confirmdisableaccount);
    } else {
        $confirmdisableaccount = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '✅ تایید و فعال کردن کانفیگ', 'callback_data' => "confirmaccountdisableadmin_" . $id_invoice],
                ],
                [
                    ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "manageinvoice_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        Editmessagetext($from_id, $message_id, "📌 با تایید گزینه زیر کانفیگ شما روشن خواهد شد. و می توانید به کانفیگ خود متصل شوید
⚠️ در صورتی که میخواهید مجدد کانفیگ غیرفعال شود باید از بخش مدیریت سرویس دکمه <u>❌ خاموش کردن اکانت</u>را کلیک کنید", $confirmdisableaccount);
    }
} elseif (preg_match('/confirmaccountdisableadmin_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "manageinvoice_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    $dataoutput = $ManagePanel->Change_status($nameloc['username'], $nameloc['Service_location']);
    if ($dataoutput['status'] == "Unsuccessful") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['notchanged'], $bakinfos);
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "active") {
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['activedconfig'], $bakinfos);
    } else {
        update("invoice", "Status", "disablebyadmin", "id_invoice", $nameloc['id_invoice']);
        Editmessagetext($from_id, $message_id, $textbotlang['users']['stateus']['disabledconfig'], $bakinfos);
    }
} elseif (preg_match('/removefull-(.*)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "تایید و حذف ", 'callback_data' => "confirmremovefulls-" . $id_invoice],
            ],
            [
                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "manageinvoice_" . $id_invoice],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 با تایید بر روی گزینه زیر این سرویس بطور کامل از دیتابیس ربات حذف خواهد شد و دیگرجزء آمار حساب نخواهد شد ( این بخش سرویس را از پنل حذف نمی کند و فقط از دیتابیس ربات حذف می کند)", $bakinfos);
} elseif (preg_match('/confirmremovefulls-(.*)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $invocie = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = :id_invoice");
    $stmt->bindParam(':id_invoice', $id_invoice, PDO::PARAM_STR);
    $stmt->execute();
    Editmessagetext($from_id, $message_id, "✅ سرویس با موفقیت حذف گردید.", json_encode(['inline_keyboard' => []]));
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => "🔗 یک ادمین یک سرویس را از دیتابیس ربات حذف کرد.

- آیدی عددی ادمین :‌$from_id
- نام ادمین : $first_name
- نام کاربری سرویس :‌ {$invocie['username']}",
            'parse_mode' => "HTML"
        ]);
    }
} elseif ($text == "🛒 اضافه کردن دسته بندی") {
    sendmessage($from_id, "📌 جهت اضافه کردن دسته بندی نام دسته بندی را ارسال کنید.", $backadmin, 'HTML');
    step("getremarkcategory", $from_id);
} elseif ($user['step'] == "getremarkcategory") {
    sendmessage($from_id, "✅ دسته بندی با موفقیت اضافه گردید.", $shopkeyboard, 'HTML');
    step("home", $from_id);
    $stmt = $pdo->prepare("INSERT INTO category (remark) VALUES (?)");
    $stmt->bindParam(1, $text);
    $stmt->execute();
} elseif ($text == "❌ حذف دسته بندی") {
    sendmessage($from_id, "📌 دسته بندی خود را جهت حذف انتخاب کنید", KeyboardCategoryadmin(), 'HTML');
    step("removecategory", $from_id);
} elseif ($user['step'] == "removecategory") {
    sendmessage($from_id, "✅ دسته بندی با موفقیت حذف گردید.", $shopkeyboard, 'HTML');
    step("home", $from_id);
    $stmt = $pdo->prepare("DELETE FROM category WHERE remark = :remark ");
    $stmt->bindParam(':remark', $text);
    $stmt->execute();
} elseif ($text == "مخفی کردن پنل" && $adminrulecheck['rule'] == "administrator") {
    if ($user['Processing_value_one'] != "/all") {
        sendmessage($from_id, "📌 این قابلیت فقط زمانی کاربرد دارد که شما لوکیشن محصول را /all تعریف کرده باشید.", null, 'HTML');
        return;
    }
    sendmessage($from_id, "📌 در صورتی که لوکیشن پنل را /all انتخاب کرده باشید اما نیاز داشته باشید که یک پنل را نشان ندهید از این قابلیت می توانید استفاده نمایید

جهت مخفی کردن پنل  از لیست زیر پنل های خود را اتنخاب کنید سپس دستور /end_hide را ارسال نمایید.", $json_list_marzban_panel, 'HTML');
    step('getlistpanel', $from_id);
} elseif ($text == "/end_hide") {
    sendmessage($from_id, "✅ ذخیره پنل ها با موفقیت انجام و پنل ها برای محصول انتخابی مخفی شد.", $shopkeyboard, 'HTML');
    step("home", $from_id);
} elseif ($user['step'] == "getlistpanel") {
    $list_panel = json_decode(select("product", "hide_panel", "id", $user['Processing_value'], "select")['hide_panel'], true);
    if (in_array($text, $list_panel)) {
        sendmessage($from_id, "❌ پنل از قبل اضافه شده است", null, 'HTML');
        return;
    }
    $list_panel[] = $text;
    update("product", "hide_panel", json_encode($list_panel), "id", $user['Processing_value']);
    sendmessage($from_id, "✅ پنل انتخاب شد  پس از اتمام دستور /end_hide را ارسال نمایید تا ذخیره نهایی شود.", null, 'HTML');
} elseif ($text == "حذف کلی پنل های مخفی" && $adminrulecheck['rule'] == "administrator") {
    update("product", "hide_panel", "{}", "name_product", $user['Processing_value']);
    sendmessage($from_id, "✅ تمامی پنل های مخفی حذف شدند", null, 'HTML');
} elseif ($text == "🔗 وبهوک مجدد ربات های نماینده") {
    $bots_agent = select("botsaz", "*", null, null, "fetchAll");
    if (count($bots_agent) == 0) {
        sendmessage($from_id, "❌ رباتی وجود ندارد", null, 'HTML');
        return;
    }
    sendmessage($from_id, "📌 در انجام وبهوک ...", null, 'HTML');
    foreach ($bots_agent as $bot) {
        file_get_contents("https://api.telegram.org/bot{$bot['bot_token']}/setwebhook?url=https://$domainhosts/vpnbot/{$bot['id_user']}{$bot['username']}/index.php");
    }
    sendmessage($from_id, "✅ وبهوک با موفقیت انجام شد.", null, 'HTML');
} elseif (preg_match('/statuscronuser-(.*)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    $user_status = select("user", "*", "id", $id_user);
    if (intval($user_status['status_cron']) == 0) {
        update("user", "status_cron", "1", "id", $id_user);
        sendmessage($from_id, "✅ اطلاعیه های کرون برای کاربر فعال گردید.", null, 'HTML');
    } else {
        update("user", "status_cron", "0", "id", $id_user);
        sendmessage($from_id, "✅ اطلاعیه های کرون برای کاربر غیرفعال گردید.", null, 'HTML');
    }
} elseif ($text == "🗂 مدیریت دسته بندی") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard_Category_manage, 'HTML');
} elseif ($text == "⬅️ بازگشت به منوی فروشگاه") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $shopkeyboard, 'HTML');
} elseif ($text == "🛍 مدیریت محصولات" || $datain == "backproductadmin") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard_shop_manage, 'HTML');
} elseif ($text == "✏️ ویرایش دسته بندی") {
    sendmessage($from_id, "📌 دسته بندی خود را جهت ویرایش انتخاب کنید", KeyboardCategoryadmin(), 'HTML');
    step("editcategory_name", $from_id);
} elseif ($user['step'] == "editcategory_name") {
    savedata("clear", "category", $text);
    sendmessage($from_id, "📌  نام جدید دسته بندی را ارسال کنید", $backadmin, 'HTML');
    step("get_name_new_category", $from_id);
} elseif ($user['step'] == "get_name_new_category") {
    $userdata = json_decode($user['Processing_value'], true);
    sendmessage($from_id, "✅ نام دسته بندی با موفقیت تغییر کرد.", $keyboard_Category_manage, 'HTML');
    step("home", $from_id);
    update("category", "remark", $text, "remark", $userdata['category']);
    update("product", "category", $text, "category", $userdata['category']);
} elseif ($datain == "zerobalance") {
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user WHERE Balance < 0  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserzero'
        ]
    ];
    $backbtn = [
        [
            'text' => "بازگشت به منوی قبل",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboard_json = json_encode($keyboardlists);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'next_pageuserzero') {
    $numpage = select("user", "*", null, null, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user WHERE Balance < 0  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserzero'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserzero'
        ]
    ];
    $backbtn = [
        [
            'text' => "بازگشت به منوی قبل",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($datain == 'previous_pageuserzero') {
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = mysqli_query($connect, "SELECT * FROM user WHERE Balance < 0  LIMIT $start_index, $items_per_page");
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    $keyboardlists['inline_keyboard'][] = [
        ['text' => "عملیات", 'callback_data' => "action"],
        ['text' => "نام کاربری", 'callback_data' => "username"],
        ['text' => "شناسه", 'callback_data' => "iduser"]
    ];
    while ($row = mysqli_fetch_assoc($result)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'],
                'callback_data' => "manageuser_" . $row['id']
            ],
            [
                'text' => $row['username'],
                'callback_data' => "username"
            ],
            [
                'text' => $row['id'],
                'callback_data' => $row['id']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_pageuserzero'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_pageuserzero'
        ]
    ];
    $backbtn = [
        [
            'text' => "بازگشت به منوی قبل",
            'callback_data' => 'backlistuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backbtn;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['mangebtnuserdec'], $keyboard_json);
} elseif ($text == "✏️ ویرایش برنامه") {
    sendmessage($from_id, "📌 برای ویرایش برنامه از لیست زیر نام برنامه را انتخاب کنید", $json_list_remove_helpـlink, 'HTML');
    step("edit_app", $from_id);
} elseif ($user['step'] == "edit_app") {
    savedata("clear", "nameapp", $text);
    step("get_new_lin_app", $from_id);
    sendmessage($from_id, "📌 لینک جدید اپ را ارسال کنید", $backadmin, 'HTML');
} elseif ($user['step'] == "get_new_lin_app") {
    step("home", $from_id);
    $userdata = json_decode($user['Processing_value'], true);
    sendmessage($from_id, "✅ لینک برنامه با موفقیت بروزرسانی گردید.", $keyboardlinkapp, 'HTML');
    update("app", "link", $text, "name", $userdata['nameapp']);
} elseif ($datain == "nowpaymentsetting") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $nowpayment_setting_keyboard, 'HTML');
} elseif ($text == "⏳ زمان تایید خودکار بدون بررسی") {
    sendmessage($from_id, "📌 در این بخش می توانید تعیین کنید که قابلیت تایید خودکار بدون بررسی  بعد از چند دقیقه رسید را تایید کند.
زمان خود را بر حسب دقیقه ارسال کنید
زمان فعلی : {$setting['timeauto_not_verify']}", $backadmin, 'HTML');
    step("gettimeauto", $from_id);
} elseif ($user['step'] == "gettimeauto") {
    if (!is_numeric($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    update("setting", "timeauto_not_verify", $text);
    sendmessage($from_id, "✅ زمان با موفقیت ثبت گردید.", $CartManage, 'HTML');
    step("home", $from_id);
} elseif ($text == "نمایش برای خرید اول") {
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("SELECT * FROM product WHERE id = :name_product  AND agent = :agent AND (Location = :Location OR Location = '/all') LIMIT 1");
    $stmt->bindParam(':name_product', $user['Processing_value']);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    $status_name = [
        '0' => "خاموش",
        '1' => "روشن"
    ][$product['one_buy_status']];
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $status_name, 'callback_data' => 'status_on_buy-' . $product['code_product'] . "-" . $product['one_buy_status']],
            ],
        ]
    ]);
    sendmessage($from_id, "📌 از طریق این قابلیت می توانید تعیین کنید این محصول برای خرید اول باشد یا خیر", $Response, 'HTML');
} elseif (preg_match('/status_on_buy-(.*)-(.*)/', $datain, $dataget)) {
    $code_product = $dataget[1];
    $status_now = $dataget[2];
    if ($status_now == '0') {
        $status_now = '1';
    } else {
        $status_now = '0';
    }
    $panel = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $stmt = $pdo->prepare("UPDATE product SET one_buy_status = :one_buy_status WHERE code_product = :code_product AND (Location = :Location OR Location = '/all') AND agent = :agent");
    $stmt->bindParam(':one_buy_status', $status_now);
    $stmt->bindParam(':code_product', $code_product);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product  AND agent = :agent AND (Location = :Location OR Location = '/all') LIMIT 1");
    $stmt->bindParam(':code_product', $code_product);
    $stmt->bindParam(':Location', $panel['name_panel']);
    $stmt->bindParam(':agent', $user['Processing_value_tow']);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    $status_name = [
        '0' => "خاموش",
        '1' => "روشن"
    ][$product['one_buy_status']];
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $status_name, 'callback_data' => 'status_on_buy-' . $product['code_product'] . "-" . $product['one_buy_status']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, "📌 از طریق این قابلیت می توانید تعیین کنید این محصول برای خرید اول باشد یا خیر", $Response);
} elseif ($text == "💳 استثناء کردن کاربر از تایید خودکار") {
    sendmessage($from_id, "📌 یک گزینه را انتخاب کنید
⚠️ این بخش برای تایید خودکار بدون بررسی می باشد", $Exception_auto_cart_keyboard, 'HTML');
} elseif ($text == "➕ استثناء کردن کاربر") {
    sendmessage($from_id, "📌 آیدی عددی کاربر را ارسال کنید", $backadmin, 'HTML');
    step("getidExceptio", $from_id);
} elseif ($user['step'] == "getidExceptio") {
    if (!in_array($text, $users_ids)) {
        sendmessage($from_id, "❌ کاربر وجود ندارد.", $backadmin, 'HTML');
        return;
    }
    $list_Exceptions = select("PaySetting", "ValuePay", "NamePay", "Exception_auto_cart", "select")['ValuePay'];
    $list_Exceptions = is_string($list_Exceptions) ? json_decode($list_Exceptions, true) : [];
    if (in_array($text, $list_Exceptions)) {
        sendmessage($from_id, "❌ کاربر در لیست استثناء وجود دارد", $backadmin, 'HTML');
        return;
    }
    $list_Exceptions[] = $text;
    $list_Exceptions = array_values($list_Exceptions);
    sendmessage($from_id, "✅ کاربر با موفقیت به لیست اضافه گردید.", $Exception_auto_cart_keyboard, 'HTML');
    update("PaySetting", "ValuePay", json_encode($list_Exceptions), "NamePay", "Exception_auto_cart");
    step("home", $from_id);
} elseif ($text == "❌ حذف کاربر از لیست") {
    sendmessage($from_id, "📌 آیدی عددی کاربر را جهت حذف از لیست ارسال کنید", $backadmin, 'HTML');
    step("getidExceptioremove", $from_id);
} elseif ($user['step'] == "getidExceptioremove") {
    if (!in_array($text, $users_ids)) {
        sendmessage($from_id, "❌ کاربر وجود ندارد.", $backadmin, 'HTML');
        return;
    }
    $list_Exceptions = select("PaySetting", "ValuePay", "NamePay", "Exception_auto_cart", "select")['ValuePay'];
    $list_Exceptions = is_string($list_Exceptions) ? json_decode($list_Exceptions, true) : [];
    if (!in_array($text, $list_Exceptions)) {
        sendmessage($from_id, "❌ کاربر در لیست استثناء وجود ندارد", $backadmin, 'HTML');
        return;
    }
    $count = 0;
    foreach ($list_Exceptions as $list) {
        if ($list == $text) {
            unset($list_Exceptions[$count]);
            break;
        }
        $count += 1;
    }
    $list_Exceptions = array_values($list_Exceptions);
    sendmessage($from_id, "✅ کاربر با موفقیت از لیست حذف گردید.", $Exception_auto_cart_keyboard, 'HTML');
    update("PaySetting", "ValuePay", json_encode($list_Exceptions), "NamePay", "Exception_auto_cart");
    step("home", $from_id);
} elseif ($text == "👁 نمایش لیست افراد") {
    $list_Exceptions = select("PaySetting", "ValuePay", "NamePay", "Exception_auto_cart", "select")['ValuePay'];
    $list_Exceptions = is_string($list_Exceptions) ? json_decode($list_Exceptions, true) : [];
    if (count($list_Exceptions) == 0) {
        sendmessage($from_id, "❌ کاربری در لیست وجود ندارد", null, 'HTML');
        return;
    }
    $list = "";
    foreach ($list_Exceptions as $list_ex) {
        $list .= $list_ex . "\n";
    }
    sendmessage($from_id, "لیست افراد👇", null, 'HTML');
    sendmessage($from_id, $list, null, 'HTML');
} elseif ($text == "تنظیم api" && $adminrulecheck['rule'] == "administrator") {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "marchent_floypay")['ValuePay'];
    $textaqayepardakht = "api دریافت شده را در این بخش ارسال کنید
        
مرچنت کد فعلی شما : $PaySetting";
    sendmessage($from_id, $textaqayepardakht, $backadmin, 'HTML');
    step('marchent_floypay', $from_id);
} elseif ($user['step'] == "marchent_floypay") {
    sendmessage($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $Swapinokey, 'HTML');
    update("PaySetting", "ValuePay", $text, "NamePay", "marchent_floypay");
    step('home', $from_id);
}



