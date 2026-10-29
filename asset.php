<?php
// Cache busting for CSS/JS links. In a page:
//     href="<php echo asset('header.css'); >"   (with the usual PHP tags)
// prints "header.css?v=1761734520", where the number is the file's last-change time.
// Browsers keep using their saved copy until the file changes; after an edit the
// number changes, so every visitor downloads the new version right away.

function asset(string $path): string
{
    $file = __DIR__ . '/' . preg_replace('#^\./#', '', $path);
    $url = is_file($file) ? $path . '?v=' . filemtime($file) : $path;

    return htmlspecialchars($url);
}
