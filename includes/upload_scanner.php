<?php

function uploadScanEicar()
{
    return 'X5O!P%@AP[4\\PZX54(P^)7CC)7}' . '$EICAR-STANDARD' . '-ANTIVIRUS-TEST-FILE!$H+H*';
}

function uploadScanFindClamav()
{
    $candidates = [];
    $configured = getenv('SPORTMART_CLAMSCAN');
    if ($configured) {
        $candidates[] = $configured;
    }
    $candidates = array_merge($candidates, [
        'C:\\Program Files\\ClamAV\\clamscan.exe',
        'C:\\ClamAV\\clamscan.exe',
        '/usr/bin/clamscan',
        '/usr/local/bin/clamscan',
        '/opt/homebrew/bin/clamscan',
    ]);
    foreach ($candidates as $path) {
        if (is_file($path) && is_executable($path)) {
            return $path;
        }
    }
    return null;
}

function uploadScanRunClamav($binary, $path)
{
    if (!function_exists('proc_open')) {
        return 2;
    }
    $process = @proc_open([$binary, '--no-summary', $path], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        return 2;
    }
    stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return proc_close($process);
}

function uploadScanBuiltIn($path)
{
    $content = @file_get_contents($path);
    if ($content === false) {
        return 'The file could not be read.';
    }
    if (strpos($content, uploadScanEicar()) !== false) {
        return 'Antivirus test signature detected.';
    }
    $patterns = ['<?php', '<script', 'eval(', 'base64_decode(', 'shell_exec(', 'passthru(', 'proc_open(', 'popen('];
    $lower = strtolower($content);
    foreach ($patterns as $pattern) {
        if (strpos($lower, $pattern) !== false) {
            return 'The file contains executable code.';
        }
    }
    return null;
}

function uploadSanitizeImage($path, $mime)
{
    if (!extension_loaded('gd')) {
        return true;
    }
    $info = @getimagesize($path);
    if ($info === false || $info[0] > 6000 || $info[1] > 6000) {
        return false;
    }
    if ($mime === 'image/jpeg') {
        $image = @imagecreatefromjpeg($path);
    } elseif ($mime === 'image/png') {
        $image = @imagecreatefrompng($path);
    } elseif ($mime === 'image/webp') {
        if (!function_exists('imagecreatefromwebp') || !function_exists('imagewebp')) {
            return true;
        }
        $image = @imagecreatefromwebp($path);
    } else {
        return false;
    }
    if ($image === false) {
        return false;
    }
    if ($mime === 'image/jpeg') {
        return imagejpeg($image, $path, 90);
    }
    if ($mime === 'image/png') {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        return imagepng($image, $path, 6);
    }
    return imagewebp($image, $path, 90);
}

function scanUploadedFile($path, $mime)
{
    if (!is_file($path)) {
        return ['safe' => false, 'engine' => 'none', 'reason' => 'The file could not be found.'];
    }

    $builtIn = uploadScanBuiltIn($path);
    if ($builtIn !== null) {
        return ['safe' => false, 'engine' => 'builtin', 'reason' => $builtIn];
    }

    $engine = 'builtin';
    $clamav = uploadScanFindClamav();
    if ($clamav !== null) {
        $engine = 'clamav';
        $result = uploadScanRunClamav($clamav, $path);
        if ($result === 1) {
            return ['safe' => false, 'engine' => 'clamav', 'reason' => 'Virus detected by ClamAV.'];
        }
        if ($result !== 0) {
            return ['safe' => false, 'engine' => 'clamav', 'reason' => 'The virus scanner could not check this file.'];
        }
    }

    if (!uploadSanitizeImage($path, $mime)) {
        return ['safe' => false, 'engine' => $engine, 'reason' => 'The file is not a valid image.'];
    }

    return ['safe' => true, 'engine' => $engine, 'reason' => ''];
}
