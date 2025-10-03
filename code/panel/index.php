<?php
require '../src/ShygunWebServiceClient.php';

function panel_truthy($value)
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_null($value)) {
        return false;
    }
    $value = is_string($value) ? trim($value) : $value;
    $truthy = array('1', 'true', 'on', 'yes', 1, true);
    return in_array($value, $truthy, true);
}

function panel_prepare_param(ReflectionParameter $param, $rawValue)
{
    $name = $param->getName();
    $lowerName = strtolower($name);
    if ($name === 'apiVersionHeader') {
        return array(panel_truthy($rawValue), null);
    }
    if ($rawValue === null) {
        if ($param->isDefaultValueAvailable()) {
            return array($param->getDefaultValue(), null);
        }
        if ($lowerName === 'domain') {
            return array(array(), null);
        }
        return array(null, null);
    }
    if (is_array($rawValue)) {
        return array($rawValue, null);
    }
    $value = trim($rawValue);
    if ($value === '') {
        if ($param->isDefaultValueAvailable()) {
            return array($param->getDefaultValue(), null);
        }
        if ($lowerName === 'domain') {
            return array(array(), null);
        }
        return array(null, null);
    }

    $jsonDecoded = json_decode($value, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return array($jsonDecoded, null);
    }
    $firstChar = substr($value, 0, 1);
    if ($firstChar === '{' || $firstChar === '[') {
        return array(null, 'JSON نامعتبر برای پارامتر ' . $name);
    }
    $lower = strtolower($value);
    if ($lower === 'true' || $lower === 'false') {
        return array($lower === 'true', null);
    }
    if (is_numeric($value)) {
        if (strpos($value, '.') !== false) {
            return array((float)$value, null);
        }
        return array((int)$value, null);
    }
    return array($rawValue, null);
}

function panel_normalize_config($configValues)
{
    $meta = array(
        'Server' => 'string',
        'AllowRowSecurity' => 'bool',
        'Level' => 'int',
        'DBUserName' => 'string',
        'DBPassword' => 'string',
        'DataBaseName' => 'string',
        'Language' => 'int',
        'AuthUser' => 'string',
        'AuthPassword' => 'string',
        'ConnectionName' => 'string',
    );
    $normalized = array();
    foreach ($meta as $key => $type) {
        if (!isset($configValues[$key])) {
            continue;
        }
        $value = $configValues[$key];
        if ($type === 'bool') {
            $normalized[$key] = panel_truthy($value);
            continue;
        }
        if ($type === 'int') {
            if ($value === '' || is_null($value)) {
                continue;
            }
            $normalized[$key] = (int)$value;
            continue;
        }
        if (is_string($value)) {
            $value = trim($value);
        }
        if ($value === '') {
            if ($key === 'AuthUser' || $key === 'AuthPassword' || $key === 'ConnectionName') {
                continue;
            }
        }
        $normalized[$key] = $value;
    }
    return $normalized;
}

function panel_json($data)
{
    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

$defaultBaseUrl = 'http://81.16.121.68:2030/api';
$defaultConfig = array(
    'Server' => 'Web-Service',
    'AllowRowSecurity' => false,
    'Level' => 1,
    'DBUserName' => 'websrv',
    'DBPassword' => 'Sh357814**Sh',
    'DataBaseName' => 'cybazg09',
    'Language' => 3,
    'AuthUser' => '',
    'AuthPassword' => '',
    'ConnectionName' => '',
);

$configFields = array_keys($defaultConfig);
$baseUrl = $defaultBaseUrl;
$configValues = $defaultConfig;
$activeMethod = '';
$resultData = null;
$errorMessage = '';
$paramErrors = array();
$submittedParams = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['base_url'])) {
        $baseUrl = trim($_POST['base_url']);
        if ($baseUrl === '') {
            $baseUrl = $defaultBaseUrl;
        }
    }
    if (isset($_POST['config']) && is_array($_POST['config'])) {
        foreach ($configFields as $field) {
            if (isset($_POST['config'][$field])) {
                $configValues[$field] = $_POST['config'][$field];
            }
        }
    }
    if (isset($_POST['action']) && $_POST['action'] === 'call_method' && isset($_POST['method'])) {
        $activeMethod = $_POST['method'];
        $submittedParams = isset($_POST['params']) && is_array($_POST['params']) ? $_POST['params'] : array();
        $client = new ShygunWebServiceClient($baseUrl, panel_normalize_config($configValues));
        $reflection = new ReflectionClass('ShygunWebServiceClient');
        if ($reflection->hasMethod($activeMethod)) {
            $method = $reflection->getMethod($activeMethod);
            $args = array();
            foreach ($method->getParameters() as $param) {
                $name = $param->getName();
                $rawValue = isset($submittedParams[$name]) ? $submittedParams[$name] : null;
                $prepared = panel_prepare_param($param, $rawValue);
                if ($prepared[1] !== null) {
                    $paramErrors[] = $prepared[1];
                }
                $args[] = $prepared[0];
            }
            if (empty($paramErrors)) {
                try {
                    $resultData = call_user_func_array(array($client, $activeMethod), $args);
                } catch (Exception $ex) {
                    $errorMessage = $ex->getMessage();
                }
            }
        } else {
            $errorMessage = 'متد پیدا نشد';
        }
    }
}

$reflection = new ReflectionClass('ShygunWebServiceClient');
$methods = array();
foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
    if ($method->class !== 'ShygunWebServiceClient') {
        continue;
    }
    $name = $method->getName();
    if ($name === '__construct' || $name === 'setConfig') {
        continue;
    }
    $methods[] = $method;
}
?>
<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="utf-8">
    <title>پنل تست وب سرویس شایگان</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazir-font@v30.1.0/dist/font-face.css">
    <style>
        body {
            font-family: 'Vazir', sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 0;
            color: #222;
        }
        header {
            background: #283593;
            color: #fff;
            padding: 20px;
            text-align: center;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 20px;
        }
        .panel {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            padding: 20px;
            margin-bottom: 24px;
        }
        h2 {
            margin-top: 0;
        }
        form .form-row {
            display: flex;
            flex-direction: column;
            margin-bottom: 16px;
        }
        label {
            margin-bottom: 6px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="number"],
        input[type="password"],
        textarea,
        select {
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #ccd2d8;
            font-size: 14px;
            direction: ltr;
        }
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        .method-block {
            border: 1px solid #e0e4ef;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 18px;
            background: #fafbff;
        }
        .method-block h3 {
            margin-top: 0;
            margin-bottom: 12px;
        }
        .submit-btn {
            background: #3949ab;
            color: #fff;
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        .submit-btn:hover {
            background: #303f9f;
        }
        .errors {
            background: #fdecea;
            color: #c62828;
            border: 1px solid #f5c6c4;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 16px;
        }
        .results {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            padding: 20px;
            margin-bottom: 40px;
            direction: ltr;
        }
        .results h2 {
            margin-top: 0;
        }
        pre {
            background: #1a2333;
            color: #e0f2ff;
            padding: 12px;
            border-radius: 6px;
            overflow-x: auto;
            font-size: 13px;
            line-height: 1.5;
        }
        .copy-area {
            display: flex;
            flex-direction: column;
            margin-top: 16px;
        }
        .copy-area button {
            align-self: flex-start;
            margin-top: 8px;
            background: #00897b;
        }
        .copy-area button:hover {
            background: #00796b;
        }
        .checkbox-row {
            display: flex;
            align-items: center;
        }
        .checkbox-row label {
            margin-bottom: 0;
            margin-left: 8px;
            font-weight: normal;
        }
    </style>
</head>
<body>
<header>
    <h1>پنل تست وب سرویس شایگان</h1>
</header>
<div class="container">
    <div class="panel">
        <h2>آدرس و تنظیمات اتصال</h2>
        <form id="config-form" method="post">
            <input type="hidden" name="action" value="update_config">
            <div class="form-row">
                <label for="base_url">آدرس پایه وب سرویس</label>
                <input type="text" id="base_url" name="base_url" value="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <?php foreach ($configFields as $field): ?>
                <div class="form-row">
                    <label for="config_<?php echo $field; ?>"><?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?></label>
                    <?php if ($field === 'AllowRowSecurity'): ?>
                        <div class="checkbox-row">
                            <input type="checkbox" id="config_<?php echo $field; ?>" name="config[<?php echo $field; ?>]" value="1" <?php echo panel_truthy($configValues[$field]) ? 'checked' : ''; ?>>
                            <label for="config_<?php echo $field; ?>">فعال باشد؟</label>
                        </div>
                    <?php else: ?>
                        <input type="text" id="config_<?php echo $field; ?>" name="config[<?php echo $field; ?>]" value="<?php echo htmlspecialchars($configValues[$field], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <button class="submit-btn" type="submit">ذخیره تنظیمات</button>
        </form>
    </div>

    <div class="panel">
        <h2>متدها</h2>
        <?php foreach ($methods as $method): ?>
            <?php $methodName = $method->getName(); ?>
            <div class="method-block" id="method-<?php echo htmlspecialchars($methodName, ENT_QUOTES, 'UTF-8'); ?>">
                <h3><?php echo htmlspecialchars($methodName, ENT_QUOTES, 'UTF-8'); ?></h3>
                <form method="post">
                    <input type="hidden" name="action" value="call_method">
                    <input type="hidden" name="method" value="<?php echo htmlspecialchars($methodName, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="base_url" value="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>" data-config-hidden="base_url">
                    <?php foreach ($configFields as $field): ?>
                        <?php
                            $value = isset($configValues[$field]) ? $configValues[$field] : '';
                            if ($field === 'AllowRowSecurity') {
                                $value = panel_truthy($value) ? '1' : '0';
                            }
                        ?>
                        <input type="hidden" name="config[<?php echo $field; ?>]" value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" data-config-hidden="<?php echo $field; ?>">
                    <?php endforeach; ?>
                    <?php foreach ($method->getParameters() as $param): ?>
                        <?php
                            $pName = $param->getName();
                            $key = $pName;
                            $postedValue = '';
                            if ($activeMethod === $methodName && isset($submittedParams[$key])) {
                                $postedValue = $submittedParams[$key];
                            } elseif ($param->isDefaultValueAvailable()) {
                                $defaultVal = $param->getDefaultValue();
                                if (is_array($defaultVal) || is_object($defaultVal)) {
                                    $postedValue = panel_json($defaultVal);
                                } elseif (is_bool($defaultVal)) {
                                    $postedValue = $defaultVal ? 'true' : 'false';
                                } elseif ($defaultVal === null) {
                                    $postedValue = '';
                                } else {
                                    $postedValue = $defaultVal;
                                }
                            }
                        ?>
                        <div class="form-row">
                            <label for="<?php echo $methodName . '_' . $pName; ?>"><?php echo htmlspecialchars($pName, ENT_QUOTES, 'UTF-8'); ?></label>
                            <?php if ($pName === 'apiVersionHeader'): ?>
                                <div class="checkbox-row">
                                    <?php $checked = ($activeMethod === $methodName) ? panel_truthy($postedValue) : panel_truthy($postedValue); ?>
                                    <input type="checkbox" id="<?php echo $methodName . '_' . $pName; ?>" name="params[<?php echo $pName; ?>]" value="1" <?php echo $checked ? 'checked' : ''; ?>>
                                    <label for="<?php echo $methodName . '_' . $pName; ?>">ارسال api-version</label>
                                </div>
                            <?php else: ?>
                                <?php if (is_array($postedValue) || (is_string($postedValue) && (strpos($postedValue, '{') !== false || strpos($postedValue, '[') !== false))): ?>
                                    <textarea id="<?php echo $methodName . '_' . $pName; ?>" name="params[<?php echo $pName; ?>]" placeholder="{}"><?php echo is_array($postedValue) ? panel_json($postedValue) : htmlspecialchars($postedValue, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <?php elseif ($pName === 'domain'): ?>
                                    <textarea id="<?php echo $methodName . '_' . $pName; ?>" name="params[<?php echo $pName; ?>]" placeholder="{}"><?php echo htmlspecialchars($postedValue, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <?php else: ?>
                                    <input type="text" id="<?php echo $methodName . '_' . $pName; ?>" name="params[<?php echo $pName; ?>]" value="<?php echo htmlspecialchars($postedValue, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <button class="submit-btn" type="submit">اجرای متد</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($paramErrors)): ?>
        <div class="errors">
            <?php foreach ($paramErrors as $error): ?>
                <div><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage !== '' || $resultData !== null): ?>
        <div class="results" id="results">
            <h2>نتایج متد</h2>
            <?php if ($activeMethod !== ''): ?>
                <p><strong>متد اجرا شده:</strong> <?php echo htmlspecialchars($activeMethod, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if ($errorMessage !== ''): ?>
                <div class="errors">خطا: <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if (is_array($resultData)): ?>
                <p><strong>کد وضعیت:</strong> <?php echo isset($resultData['status']) ? htmlspecialchars($resultData['status'], ENT_QUOTES, 'UTF-8') : '-'; ?></p>
                <?php if (isset($resultData['url'])): ?>
                    <p><strong>آدرس درخواست:</strong> <?php echo htmlspecialchars($resultData['url'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <?php if (isset($resultData['request'])): ?>
                    <h3>بدنه درخواست</h3>
                    <pre><?php echo htmlspecialchars($resultData['request'], ENT_QUOTES, 'UTF-8'); ?></pre>
                <?php endif; ?>
                <?php if (isset($resultData['requestHeaders'])): ?>
                    <h3>هدرهای درخواست</h3>
                    <pre><?php echo htmlspecialchars(trim($resultData['requestHeaders']), ENT_QUOTES, 'UTF-8'); ?></pre>
                <?php endif; ?>
                <?php if (isset($resultData['responseHeaders'])): ?>
                    <h3>هدرهای پاسخ</h3>
                    <pre><?php echo htmlspecialchars(panel_json($resultData['responseHeaders']), ENT_QUOTES, 'UTF-8'); ?></pre>
                <?php endif; ?>
                <?php if (isset($resultData['rawBody'])): ?>
                    <h3>بدنه پاسخ</h3>
                    <pre><?php echo htmlspecialchars($resultData['rawBody'], ENT_QUOTES, 'UTF-8'); ?></pre>
                <?php endif; ?>
                <div class="copy-area">
                    <label for="result-copy">خروجی کامل (برای کپی)</label>
                    <textarea id="result-copy" readonly><?php echo htmlspecialchars(panel_json($resultData), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <button type="button" class="submit-btn" id="copy-btn">کپی</button>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<script>
(function() {
    var configInputs = document.querySelectorAll('#config-form [name^="config"], #config-form input[name="base_url"]');
    function updateHidden(key, value, isCheckbox) {
        var selector = '[data-config-hidden="' + key + '"]';
        var nodes = document.querySelectorAll(selector);
        for (var i = 0; i < nodes.length; i++) {
            if (isCheckbox) {
                nodes[i].value = value ? '1' : '0';
            } else {
                nodes[i].value = value;
            }
        }
    }
    for (var i = 0; i < configInputs.length; i++) {
        (function(input) {
            var key = input.name === 'base_url' ? 'base_url' : input.name.replace('config[', '').replace(']', '');
            var isCheckbox = input.type === 'checkbox';
            if (isCheckbox) {
                updateHidden(key, input.checked, true);
            } else {
                updateHidden(key, input.value, false);
            }
            input.addEventListener(isCheckbox ? 'change' : 'input', function() {
                if (isCheckbox) {
                    updateHidden(key, input.checked, true);
                } else {
                    updateHidden(key, input.value, false);
                }
            });
        })(configInputs[i]);
    }
    var copyBtn = document.getElementById('copy-btn');
    if (copyBtn) {
        copyBtn.addEventListener('click', function() {
            var textarea = document.getElementById('result-copy');
            textarea.select();
            document.execCommand('copy');
        });
    }
    var results = document.getElementById('results');
    if (results) {
        window.addEventListener('load', function() {
            if (typeof results.scrollIntoView === 'function') {
                results.scrollIntoView(true);
            } else {
                location.hash = '#results';
            }
        });
    }
})();
</script>
</body>
</html>
