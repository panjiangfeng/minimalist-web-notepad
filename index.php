<?php

// Base URL of the website, without trailing slash.
$base_url = 'https://notes.orga.cat';

// Path to the directory to save the notes in, without trailing slash.
// Should be outside of the document root, if possible.
$save_path = '_tmp';

// Maximum allowed image size, in bytes (10 MB).
$max_image_size = 10 * 1024 * 1024;

// Image lifetime in days. 0 = keep images forever (default).
// Set to e.g. 7 to automatically purge images older than a week on upload.
$image_ttl_days = 0;

// Disable caching.
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Allowed image types: extension => MIME type.
$image_types = [
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'svg'  => 'image/svg+xml',
];

// Serve an uploaded image: /index.php?img=name.png
if (isset($_GET['img'])) {
    $name = $_GET['img'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $dir = $save_path . '/i';
    if (isset($image_types[$ext])
        && preg_match('/^[a-zA-Z0-9_-]+\.' . $ext . '$/', $name)
        && is_file($dir . '/' . $name)) {
        header('Content-Type: ' . $image_types[$ext]);
        header('Cache-Control: public, max-age=31536000, immutable');
        header('X-Content-Type-Options: nosniff');
        // Prevent scripts embedded in SVG files from running.
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
        readfile($dir . '/' . $name);
        die;
    }
    header('HTTP/1.0 404 Not Found');
    die;
}

// If no name is provided or it contains invalid characters or it is too long.
if (!isset($_GET['note']) || !preg_match('/^[a-zA-Z0-9_-]+$/', $_GET['note']) || strlen($_GET['note']) > 64) {

    // Generate a name with 5 random unambiguous characters. Redirect to it.
    header("Location: $base_url/" . substr(str_shuffle('234579abcdefghjkmnpqrstwxyz'), -5));
    die;
}

$path = $save_path . '/' . $_GET['note'];

// Image upload (paste or drag & drop from the editor).
if (isset($_FILES['image'])) {

    header('Content-Type: application/json; charset=UTF-8');

    $file = $_FILES['image'];
    $error = null;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'upload error';
    } elseif ($file['size'] <= 0 || $file['size'] > $max_image_size) {
        $error = 'invalid size';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!isset($image_types[$ext])) {
            $error = 'unsupported type';
        } else {
            // Verify the real MIME type if possible.
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if ($mime !== $image_types[$ext]) {
                    $error = 'type mismatch';
                }
            }
        }
    }

    if ($error) {
        http_response_code(400);
        print json_encode(['error' => $error]);
        die;
    }

    $dir = $save_path . '/i';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Random 10-character name, extension preserved.
    $name = substr(str_shuffle('234579abcdefghjkmnpqrstwxyz'), -10) . '.' . $ext;

    if (move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {

        // Occasionally purge expired images if a TTL is configured.
        if ($image_ttl_days > 0 && mt_rand(1, 20) === 1) {
            $expire_before = time() - $image_ttl_days * 86400;
            foreach (glob($dir . '/*') ?: [] as $old) {
                if (is_file($old) && filemtime($old) < $expire_before) {
                    @unlink($old);
                }
            }
        }

        print json_encode(['url' => $base_url . '/index.php?img=' . $name]);
    } else {
        http_response_code(500);
        print json_encode(['error' => 'save failed']);
    }
    die;
}

if (isset($_POST['text'])) {

    // Update file.
    file_put_contents($path, $_POST['text']);

    // If provided input is empty, delete file.
    if (!strlen($_POST['text'])) {
        unlink($path);
    }
    die;
}

// Print raw file if the client is curl, wget, or when explicitly requested.
if (isset($_GET['raw']) || strpos($_SERVER['HTTP_USER_AGENT'], 'curl') === 0 || strpos($_SERVER['HTTP_USER_AGENT'], 'Wget') === 0) {
    if (is_file($path)) {
        header('Content-type: text/plain');
        print file_get_contents($path);
    } else {
        header('HTTP/1.0 404 Not Found');
    }
    die;
}
?><!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php print $_GET['note']; ?></title>
    <link rel="icon" href="<?php print $base_url; ?>/favicon.ico" sizes="any">
    <link rel="icon" href="<?php print $base_url; ?>/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?php print $base_url; ?>/styles.css">
</head>
<body>
    <div class="container">
        <div class="toolbar">
            <button type="button" class="tool-btn" data-action="bold" title="加粗 (**)**"><span class="glyph b">B</span></button>
            <button type="button" class="tool-btn" data-action="italic" title="斜体 (* *)"><span class="glyph i">I</span></button>
            <button type="button" class="tool-btn" data-action="strike" title="删除线 (~~~~)"><span class="glyph s">S</span></button>
            <span class="sep"></span>
            <button type="button" class="tool-btn" data-action="heading" title="标题 (##)"><span class="glyph b">H</span></button>
            <button type="button" class="tool-btn" data-action="ul" title="无序列表 (-)"><span class="glyph">&bull;</span></button>
            <button type="button" class="tool-btn" data-action="ol" title="有序列表 (1.)"><span class="glyph" style="font-size:12px">1.</span></button>
            <button type="button" class="tool-btn" data-action="quote" title="引用 (>)"><span class="glyph">&#10077;</span></button>
            <span class="sep"></span>
            <button type="button" class="tool-btn" data-action="code" title="行内代码 (`)"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg></button>
            <button type="button" class="tool-btn" data-action="codeblock" title="代码块 (```)"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg></button>
            <button type="button" class="tool-btn" data-action="table" title="表格"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="12" y1="3" x2="12" y2="21"/></svg></button>
            <span class="sep"></span>
            <button type="button" class="tool-btn" data-action="link" title="插入链接"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></button>
            <button type="button" class="tool-btn" data-action="image" title="插入图片（也可直接粘贴/拖入）"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></button>
            <span class="flex-space"></span>
            <span id="count"></span>
            <div class="mode-switch">
                <button type="button" class="mode-btn" data-mode="edit">编辑</button>
                <button type="button" class="mode-btn" data-mode="split">分屏</button>
                <button type="button" class="mode-btn" data-mode="preview">预览</button>
            </div>
        </div>
        <div class="workspace">
            <textarea id="content" placeholder="支持 Markdown，可直接粘贴或拖入图片"><?php
                if (is_file($path)) {
                    print htmlspecialchars(file_get_contents($path), ENT_QUOTES, 'UTF-8');
                }
            ?></textarea>
            <div id="preview"></div>
        </div>
        <input type="file" id="file-input" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml" hidden>
    </div>
    <pre id="printable"></pre>
    <div id="status"></div>
    <script src="<?php print $base_url; ?>/lib/marked.min.js"></script>
    <script src="<?php print $base_url; ?>/lib/purify.min.js"></script>
    <script src="<?php print $base_url; ?>/lib/highlight.min.js"></script>
    <script src="<?php print $base_url; ?>/script.js"></script>
</body>
</html>
