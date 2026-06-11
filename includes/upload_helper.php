<?php
// includes/upload_helper.php
// Handles profile picture uploads securely.
// Returns ['ok'=>true,'filename'=>'...'] or ['ok'=>false,'error'=>'...']

function handle_avatar_upload(array $file, int $user_id): array {
    $upload_dir = __DIR__ . '/../assets/img/avatars/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    if ($file['error'] !== UPLOAD_ERR_OK) {
        if ($file['error'] === UPLOAD_ERR_NO_FILE) return ['ok'=>false,'error'=>''];
        return ['ok'=>false,'error'=>'Upload failed (code '.$file['error'].').'];
    }

    $max_bytes = 2 * 1024 * 1024; // 2 MB
    if ($file['size'] > $max_bytes) return ['ok'=>false,'error'=>'Image must be under 2 MB.'];

    // Verify real image via GD
    $info = @getimagesize($file['tmp_name']);
    if (!$info) return ['ok'=>false,'error'=>'File is not a valid image.'];

    $allowed_mime = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    if (!isset($allowed_mime[$info['mime']])) return ['ok'=>false,'error'=>'Only JPG, PNG, GIF, or WEBP allowed.'];

    $ext      = $allowed_mime[$info['mime']];
    $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
    $dest     = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) return ['ok'=>false,'error'=>'Could not save image.'];

    return ['ok'=>true,'filename'=>$filename];
}

function avatar_url(?string $filename): string {
    if ($filename && file_exists(__DIR__ . '/../assets/img/avatars/' . $filename)) {
        return '/peer-tutoring/assets/img/avatars/' . $filename;
    }
    return ''; // empty = use initials fallback
}
