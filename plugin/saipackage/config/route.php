<?php

use Webman\Route;

Route::group('/tool/install', function () {
    // 商店代理接口（在线安装）
    Route::get('/online/appList', [plugin\saipackage\app\controller\InstallController::class, 'appList']);
    Route::get('/online/storeCaptcha', [plugin\saipackage\app\controller\InstallController::class, 'storeCaptcha']);
    Route::post('/online/storeLogin', [plugin\saipackage\app\controller\InstallController::class, 'storeLogin']);
    Route::post('/online/storeUserInfo', [plugin\saipackage\app\controller\InstallController::class, 'storeUserInfo']);
    Route::post('/online/storePurchasedApps', [plugin\saipackage\app\controller\InstallController::class, 'storePurchasedApps']);
    Route::post('/online/storeAppVersions', [plugin\saipackage\app\controller\InstallController::class, 'storeAppVersions']);
    Route::post('/online/storeDownloadApp', [plugin\saipackage\app\controller\InstallController::class, 'storeDownloadApp']);
});
