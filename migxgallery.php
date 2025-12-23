<?php
/**
 * MigxGallery
 * MODX 2.8+
 */

/* ==========================================================
 * CONFIG
 * ======================================================== */

$tvName   = 'MigxGallery'; // MIGX TV
$quality  = 82;

$sizes = [
    ''   => [1440, 832], // оригинал (основной файл)
    '-L' => [645, 373],
    '-S' => [200, 116],
];

$tmpRelPath     = 'images/galleries/_tmp/';
$galleryRelPath = 'images/galleries/';

/* ==========================================================
 * HELPERS
 * ======================================================== */
 

function galleryResizeCover($src, $srcW, $srcH, $dstW, $dstH, $ext) {

    if ($srcH == 0 || $dstH == 0) return false;
    
    $srcRatio = $srcW / $srcH;
    $dstRatio = $dstW / $dstH;

    if ($srcRatio > $dstRatio) {
        $newH = $srcH;
        $newW = (int)($srcH * $dstRatio);
        $srcX = (int)(($srcW - $newW) / 2);
        $srcY = 0;
    } else {
        $newW = $srcW;
        $newH = (int)($srcW / $dstRatio);
        $srcX = 0;
        $srcY = (int)(($srcH - $newH) / 2);
    }

    $dst = imagecreatetruecolor($dstW, $dstH);

    if ($ext === 'png') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $transparent);
    }

    imagecopyresampled(
        $dst, $src,
        0, 0,
        $srcX, $srcY,
        $dstW, $dstH,
        $newW, $newH
    );

    return $dst;
}

/* ==========================================================
 * EVENT: Очистка _tmp при входе
 * ======================================================== */

if ($modx->event->name === 'OnDocFormPrerender') {

    $basePath = $modx->getOption('base_path');
    $tmpDir = $basePath . 'images/galleries/_tmp/';

    if (is_dir($tmpDir)) {
        foreach (glob($tmpDir . '*.{jpg,jpeg,png}', GLOB_BRACE) as $file) {
            @unlink($file);
        }
    }
    //$modx->log(modX::LOG_LEVEL_ERROR, $logPrefix . 'OnDocFormPrerender - очистил папку _tmp');


    return;
}


/* ==========================================================
 * EVENT: DELETE RESOURCE
 * ======================================================== */

if ($modx->event->name === 'OnBeforeEmptyTrash') {

    $ids = $modx->event->params['ids'] ?? [];
    if (!$ids) return;

    $basePath = $modx->getOption('base_path');

    foreach ($ids as $id) {
        $res = $modx->getObject('modResource', $id);
        if (!$res) continue;

        $alias = $res->get('alias');
        if (!$alias) continue;

        $dir = $basePath . $galleryRelPath . $alias;
        if (!is_dir($dir)) continue;

        foreach (glob($dir . '/*') as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }

    return;
}

/* ==========================================================
 * EVENT: SAVE RESOURCE
 * ======================================================== */

if ($modx->event->name !== 'OnDocFormSave') return;

/* защита от рекурсии */
/** @var modResource $resource */
if (empty($resource) || !($resource instanceof modResource)) return;

$alias = $resource->get('alias');
if (!$alias) return;

$basePath = $modx->getOption('base_path');

$tmpDir     = $basePath . $tmpRelPath;
$galleryDir = $basePath . $galleryRelPath . $alias . '/';

if (!is_dir($galleryDir)) {
    mkdir($galleryDir, 0755, true);
}

/* ==========================================================
 * LOAD MIGX
 * ======================================================== */

$tv = $modx->getObject('modTemplateVar', ['name' => $tvName]);
if (!$tv) return;

$raw = $tv->getValue($resource->get('id'));
$items = json_decode($raw, true);
if (!is_array($items)) return;

/* ==========================================================
 * PROCESS ITEMS
 * ======================================================== */

$newItems = [];
$index = 1;

// определяем, был ли reorder
$needRename = false;
foreach ($items as $i => $item) {
    if (empty($item['image'])) continue;

    if (strpos($item['image'], $alias . '-' . ($i + 1) . '.') === false) {
        $needRename = true;
        break;
    }
}

foreach ($items as $item) {

    if (empty($item['image'])) {
        continue;
    }

    $fileName = basename($item['image']);

    $tmpFile     = $basePath . $tmpRelPath . $fileName;
    $galleryFile = $basePath . $galleryRelPath . $alias . '/' . $fileName;

    $fromTmp = false;

    if (file_exists($tmpFile)) {
        $sourceFile = $tmpFile;
        $fromTmp = true;
    } elseif (file_exists($galleryFile)) {
        $sourceFile = $galleryFile;
    } else {
        // файла нет, но строку НЕ теряем
        $newItems[] = $item;
        $index++;
        continue;
    }

    $ext = strtolower(pathinfo($sourceFile, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
        $newItems[] = $item;
        $index++;
        continue;
    }

    $baseName = $alias . '-' . $index;
    $finalOriginal = $galleryDir . $baseName . '.' . $ext;

    // нужно ли реально трогать файл
    $needProcess =
        $fromTmp ||
        $needRename ||
        !file_exists($finalOriginal);

    if ($needProcess) {

        // load src
        $src = ($ext === 'png')
            ? imagecreatefrompng($sourceFile)
            : imagecreatefromjpeg($sourceFile);

        if ($src) {
            $srcW = imagesx($src);
            $srcH = imagesy($src);
            
            if ($srcW < 1 || $srcH < 1) { imagedestroy($src); continue; }

            foreach ($sizes as $suffix => [$w, $h]) {

                $target = $galleryDir . $baseName . $suffix . '.' . $ext;

                $dst = galleryResizeCover($src, $srcW, $srcH, $w, $h, $ext);

                if ($ext === 'png') {
                    imagepng($dst, $target, 6);
                } else {
                    imagejpeg($dst, $target, $quality);
                }

                imagedestroy($dst);
            }

            imagedestroy($src);
        }

        // tmp-файлы НЕ удаляем здесь (чистятся при OnDocFormPrerender)
    }

    // строку MIGX сохраняем ВСЕГДА
    $newItem = $item;
    $newItem['image'] = $galleryRelPath . $alias . '/' . $baseName . '.' . $ext;
    $newItems[] = $newItem;

    $index++;
}

$items = $newItems;


/* ==========================================================
 * CLEANUP REMOVED FILES
 * ======================================================== */

$allowed = [];

foreach ($items as $item) {

    if (empty($item['image'])) {
        continue;
    }

    // оригинал
    $base = basename($item['image']); // alias-1.jpg
    $allowed[] = $galleryDir . $base;

    // размеры (-L, -S)
    foreach ($sizes as $suffix => $_) {
        if ($suffix === '') continue;

        $allowed[] = $galleryDir . str_replace(
            '.',
            $suffix . '.',
            $base
        );
        // фикс на будущее если будут проблемы с точками в alias
        // $pi = pathinfo($base);
        // $allowed[] = $galleryDir . $pi['filename'] . $suffix . '.' . $pi['extension'];
    }
}

// убираем дубли
$allowed = array_unique($allowed);

// удаляем всё лишнее
foreach (glob($galleryDir . '*.{jpg,jpeg,png}', GLOB_BRACE) as $file) {

    if (!in_array($file, $allowed, true)) {
        @unlink($file);
    }
}


/* ==========================================================
 * SAVE MIGX
 * ======================================================== */

$tvr = $modx->getObject('modTemplateVarResource', [
    'tmplvarid' => $tv->get('id'),   // ← используем уже найденный $tv
    'contentid' => $resource->get('id'),
]);

if ($tvr) {
    $tvr->set('value', json_encode($items, JSON_UNESCAPED_UNICODE));
    $tvr->save(); // не вызывает OnDocFormSave
}
