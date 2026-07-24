<?php

namespace Leazycms\FLC\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Leazycms\FLC\Models\File;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class FileManagerController extends Controller implements HasMiddleware
{
    protected function privacyResponse()
    {
        $appName = get_option("site_title") ?? "LeazyCMS";
        $html = <<<HTML
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Akses Ditolak</title>
  <style>
    :root{--bg:#0b1220;--card:rgba(255,255,255,.06);--border:rgba(255,255,255,.12);--text:rgba(255,255,255,.92);--muted:rgba(255,255,255,.68);--primary:#60a5fa}
    *{box-sizing:border-box}
    body{margin:0;min-height:100vh;display:grid;place-items:center;background:radial-gradient(900px 600px at 20% 10%, rgba(96,165,250,.25), transparent 60%),radial-gradient(900px 600px at 80% 80%, rgba(56,189,248,.18), transparent 60%),var(--bg);color:var(--text);font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,Arial}
    .wrap{width:min(720px,92vw)}
    .card{background:linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.04));border:1px solid var(--border);border-radius:18px;padding:26px 22px;backdrop-filter: blur(10px)}
    .badge{display:inline-flex;align-items:center;gap:10px;padding:8px 12px;border-radius:999px;background:rgba(96,165,250,.12);border:1px solid rgba(96,165,250,.25);color:rgba(255,255,255,.88);font-size:13px}
    .dot{width:10px;height:10px;border-radius:999px;background:var(--primary);box-shadow:0 0 0 5px rgba(96,165,250,.18)}
    h1{margin:16px 0 8px;font-size:26px;letter-spacing:.2px}
    p{margin:0;color:var(--muted);line-height:1.6}
    .actions{margin-top:18px;display:flex;flex-wrap:wrap;gap:10px}
    .btn{appearance:none;border:1px solid var(--border);background:rgba(255,255,255,.06);color:var(--text);text-decoration:none;padding:10px 14px;border-radius:12px;font-weight:600}
    .btn.primary{border-color:rgba(96,165,250,.35);background:rgba(96,165,250,.16)}
    .btn:hover{filter:brightness(1.06)}
    .meta{margin-top:14px;font-size:12px;color:rgba(255,255,255,.55)}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="badge"><span class="dot"></span><span>{$appName}</span></div>
      <h1>File ini bersifat privasi</h1>
      <p>Untuk melihat atau mengunduh file ini, Anda harus login terlebih dahulu.</p>
      <div class="actions">
        <a class="btn" href="javascript:history.back()">Kembali</a>
      </div>
      <div class="meta">HTTP 403 • Private file</div>
    </div>
  </div>
</body>
</html>
HTML;
        return response(minify_all_one_line($html), 403)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public static function middleware(): array
    {
        return [
            new Middleware('auth', only: ['upload', 'destroy'])
        ];
    }

    public function favicon(Request $request)
    {
        abort_if(!media_exists(get_option('favicon')), 404);

        $media = media(get_option('favicon'))->getData();
        if (config('modules.multisite_enabled') && file_exists(public_path('favicon.ico'))) {
            unlink(public_path('favicon.ico'));
        }
        $masterKey = config('flc.encrypt_key');
        $shouldDecrypt = is_string($masterKey) && trim((string) $masterKey) !== '' && !empty($media->encrypt_key);
        if ($shouldDecrypt) {
            if (!Auth::check()) {
                return $this->privacyResponse();
            }
            return response()->stream(function () use ($media) {
                $raw = Storage::disk($media->file_disk)->get($media->file_path);
                $masterKey = config('flc.encrypt_key');
                $fileKey = decryptData($masterKey, $media->encrypt_key);
                $decrypted = is_string($fileKey) && $fileKey !== '' ? decryptData($fileKey, $raw) : false;
                abort_if(!is_string($decrypted) || $decrypted === '', 500, 'File tidak dapat didecrypt');
                echo $decrypted;
            }, 200, [
                'Content-Type' => $media->file_type,
                'Content-Disposition' => 'inline; filename="' . basename($media->file_path) . '"',
                'Cache-Control' => 'public, max-age=31536000, immutable',
                'Pragma' => 'public',
                'Expires' => gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT',
                'Accept-Ranges' => 'bytes',
            ]);
        }
        return response()->stream(function () use ($media) {
            $stream = Storage::disk($media->file_disk)->readStream($media->file_path);
            abort_if($stream === false, 404);
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $media->file_type,
            'Content-Disposition' => 'inline; filename="' . basename($media->file_path) . '"',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Pragma' => 'public',
            'Expires' => gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT',
            'Accept-Ranges' => 'bytes',
        ]);
    }

    public function upload(Request $request)
    {

        abort_if(!$request->user() || !$request->isMethod('post'), '404');
        $maxSizeBytes = \Illuminate\Http\UploadedFile::getMaxFilesize();
        $maxSizeKB = $maxSizeBytes / 1024;
        $maxSizeMB = floor($maxSizeKB / 1024);

        $request->validate([
            'media' => 'required|mimetypes:' . allow_mime() . '|max:' . floor($maxSizeKB),
        ], [
            'media.max' => 'Ukuran file terlalu besar. Batas maksimal server adalah ' . $maxSizeMB . ' MB.',
            'media.mimetypes' => 'Format file tidak diizinkan. Ekstensi yang diizinkan: ' . allow_mime(),
            'media.required' => 'Tidak ada file yang dipilih atau file terlalu besar melebihi batas server.'
        ]);

        if ($file = $request->file('media')) {
            $fileHash = md5_file($file->getRealPath());

            // Cek duplikasi menggunakan child_id sebagai penyimpan hash md5
            $currentHost = app()->has('tenant') && function_exists('tenant') && tenant() ? tenant()->domain : $request->getHost();

            $existingFile = \Leazycms\FLC\Models\File::where('child_id', $fileHash)
                ->where('purpose', 'upload-media')
                ->where('host', $currentHost)
                ->first();

            if ($existingFile) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Duplikasi terdeteksi: File ini sudah pernah diunggah.'
                    ], 400);
                }
                return back()->with('error', 'Duplikasi terdeteksi: File ini sudah pernah diunggah.');
            }

            $fileName = (new File)->addFile([
                'file' => $file,
                'purpose' => 'Upload Media',
                'child_id' => $fileHash,
                'mime_type' => explode(',', allow_mime()),
                'self_upload' => true
            ]);

            if ($fileName !== null) {
                if ($request->ajax() || $request->wantsJson()) {
                    $finalFile = File::where('file_name', $fileName)->first();
                    return response()->json([
                        'status' => 'success',
                        'file_name' => $fileName,
                        'file_size' => $finalFile ? (int) $finalFile->file_size : 0,
                        'message' => 'File berhasil diupload'
                    ]);
                }
                return back()->with('success', 'File berhasil diupload');
            }

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal mengupload file ke dalam sistem. Silakan periksa log atau hubungi administrator.'
                ], 500);
            }
            return back()->with('error', 'Gagal mengupload file ke dalam sistem.');
        }
    }
    public function download($slug, $session)
    {
        if ($slug && $session) {
            $slug = base64_decode(base64_decode($slug));
            if (!media_exists($slug) || $session != md5(request()->session()->getId())) {
                $requestId = Str::uuid(); // unik, seperti AWS RequestId
                $hostId = base64_encode(Str::random(32)); // mirip HostId AWS
                $key = base64_encode(base64_encode($slug)) . '-' . md5(request()->session()->getId());
                $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Error>
  <Code>NoSuchKey</Code>
  <Message>The specified key does not exist.</Message>
  <Key>{$key}</Key>
  <RequestId>{$requestId}</RequestId>
  <HostId>{$hostId}</HostId>
</Error>
XML;

                return response($xml, 404)
                    ->header('Content-Type', 'application/xml');
            }

            File::whereFileName($slug)->increment('file_hits');
            // Forget cache & buat instance baru agar getData() membaca file_hits terbaru dari DB
            Cache::forget(request()->getHost() . ":media:{$slug}");
            $media = (new \Leazycms\FLC\Inc\MediaHandler($slug))->getData();

            if (!$media || (isset($media->file_host) && request()->getHost() != $media->file_host)) {
                return $this->xmlNotFoundResponse(base64_encode(base64_encode($slug)) . '-' . md5(request()->session()->getId()));
            }

            $key = md5($slug) . "_" . $slug;
            $masterKey = config('flc.encrypt_key');
            $shouldDecrypt = is_string($masterKey) && trim((string) $masterKey) !== '' && !empty($media->encrypt_key);
            if ($shouldDecrypt) {
                if (!Auth::check()) {
                    return $this->privacyResponse();
                }
                return response()->streamDownload(function () use ($media) {
                    $raw = Storage::disk($media->file_disk)->get($media->file_path);
                    $masterKey = config('flc.encrypt_key');
                    $fileKey = decryptData($masterKey, $media->encrypt_key);
                    $decrypted = is_string($fileKey) && $fileKey !== '' ? decryptData($fileKey, $raw) : false;
                    abort_if(!is_string($decrypted) || $decrypted === '', 500, 'File tidak dapat didecrypt');
                    echo $decrypted;
                }, $key);
            }
            return response()->download(Storage::disk($media->file_disk)->path($media->file_path), $key);
        }
    }


    public function stream($slug, Request $request)
    {
        // cek referer
        $referer = $request->userAgent() . '|' . $request->headers->get('referer');

        if ($referer) {
            $allowedDomains = [
                parse_url(get_current_host(), PHP_URL_HOST), // domain web kamu
                'drive.google.com',                          // Google Docs Viewer
            ];

            $validReferer = false;
            foreach ($allowedDomains as $domain) {
                if (str_contains($referer, $domain)) {
                    $validReferer = true;
                    break;
                }
            }

            if (!$validReferer) {
                $requestId = Str::uuid(); // unik, seperti AWS RequestId
                $hostId = base64_encode(Str::random(32)); // mirip HostId AWS
                $key = base64_encode(base64_encode(md5(request()->session()->getId())));
                $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Error>
  <Code>NoSuchKey</Code>
  <Message>The specified key does not exist.</Message>
  <Key>{$key}</Key>
  <RequestId>{$requestId}</RequestId>
  <HostId>{$hostId}</HostId>
</Error>
XML;

                return response($xml, 404)
                    ->header('Content-Type', 'application/xml');
            }
        } else {
            // referer kosong (akses langsung / copy link) → tolak
            $requestId = Str::uuid(); // unik, seperti AWS RequestId
            $hostId = base64_encode(Str::random(32)); // mirip HostId AWS
            $key = base64_encode(base64_encode(md5(request()->session()->getId())));


            $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Error>
  <Code>NoSuchKey</Code>
  <Message>The specified key does not exist.</Message>
  <Key>{$key}</Key>
  <RequestId>{$requestId}</RequestId>
  <HostId>{$hostId}</HostId>
</Error>
XML;

            return response($xml, 404)
                ->header('Content-Type', 'application/xml');
        }

        // ambil file dari storage
        $slug = dec64(dec64($slug));
        $media = media($slug)->getData();

        if (!$media || (isset($media->file_host) && request()->getHost() != $media->file_host)) {
            return $this->xmlNotFoundResponse(base64_encode(base64_encode(md5(request()->session()->getId()))));
        }

        $masterKey = config('flc.encrypt_key');
        $shouldDecrypt = is_string($masterKey) && trim((string) $masterKey) !== '' && !empty($media->encrypt_key);
        if ($shouldDecrypt) {
            if (!Auth::check()) {
                return $this->privacyResponse();
            }
            return response()->stream(function () use ($media) {
                $raw = Storage::disk($media->file_disk)->get($media->file_path);
                $masterKey = config('flc.encrypt_key');
                $fileKey = decryptData($masterKey, $media->encrypt_key);
                $decrypted = is_string($fileKey) && $fileKey !== '' ? decryptData($fileKey, $raw) : false;
                abort_if(!is_string($decrypted) || $decrypted === '', 500, 'File tidak dapat didecrypt');
                echo $decrypted;
            }, 200, [
                'Content-Type' => $media->file_type,
                'Content-Disposition' => 'inline; filename="' . basename($media->file_path) . '"',
                'Cache-Control' => 'no-store, must-revalidate',
                'Pragma' => 'no-cache',
            ]);
        }
        return response()->stream(function () use ($media) {
            $stream = Storage::disk($media->file_disk)->readStream($media->file_path);
            abort_if($stream === false, 404);
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $media->file_type,
            'Content-Disposition' => 'inline; filename="' . basename($media->file_path) . '"',
            'Cache-Control' => 'no-store, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    public function destroy(Request $request)
    {
        abort_if(!$request->user() || !$request->isMethod('post'), 404);
        if ($request->media) {
            $media = $request->media;
            $fileName = basename($media);
            $data = File::whereFileName($fileName)->first();
            if ($data) {
                $inUsePosts = \Leazycms\Web\Models\Post::where('media', 'LIKE', '%' . $fileName . '%')
                    ->orWhere('content', 'LIKE', '%' . $fileName . '%')
                    ->orWhere('data_field', 'LIKE', '%' . $fileName . '%')
                    ->orWhere('data_loop', 'LIKE', '%' . $fileName . '%')
                    ->exists();

                $inUseOptions = \Leazycms\Web\Models\Option::where('value', 'LIKE', '%' . $fileName . '%')
                    ->exists();

                if ($inUsePosts || $inUseOptions) {
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'File tidak dapat dihapus karena sedang digunakan oleh post atau pengaturan lain.'
                        ], 400);
                    }
                    return back()->with('error', 'File tidak dapat dihapus karena sedang digunakan oleh post atau pengaturan lain.');
                }

                $deletedSize = $data->file_size;
                Cache::forget($data->host . ":media:" . $fileName);
                Storage::disk($data->disk)->delete($data->file_path);
                Log::channel('daily')->warning('File deleted: ' . $data->file_name, [
                    'path' => $data->file_path,
                    'ip' => get_client_ip(),
                    'user_id' => Auth::user()->email,
                    'referer' => request()->headers->get('referer'),
                ]);
                $data->forceDelete();
                
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'success', 'deleted_size' => (int) $deletedSize]);
                }
                return back()->with('success', 'File berhasil dihapus');
            }
        }
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'error'], 400);
        }
    }
    public function stream_by_id(string $slug)
    {
        $media = media($slug)->getData();

        if (!$media || (isset($media->file_host) && request()->getHost() != $media->file_host)) {
            $requestId = Str::uuid();
            $hostId = base64_encode(Str::random(32));
            $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Error>
  <Code>NoSuchKey</Code>
  <Message>The specified key does not exist.</Message>
  <Key>{$slug}</Key>
  <RequestId>{$requestId}</RequestId>
  <HostId>{$hostId}</HostId>
</Error>
XML;
            return response($xml, 404)->header('Content-Type', 'application/xml');
        }

        $auth = $media->file_auth ?? null;
        if ($auth === null) {
            // no auth needed
        } elseif ($auth == 0) {
            abort_if(!Auth::check(), 403, 'You need to be logged in to access this resource.');
        } elseif ($auth > 0) {
            abort_if($auth != Auth::id(), 403, 'You do not have permission to access this resource.');
        }


        // Cek apakah user minta size kecil
        $size = request('size');
        $isImage = str_contains($media->file_type, 'image');

        if ($isImage && $size == 'small') {
            $fileContent = Storage::disk($media->file_disk)->get($media->file_path);
            $masterKey = config('flc.encrypt_key');
            $shouldDecrypt = is_string($masterKey) && trim((string) $masterKey) !== '' && !empty($media->encrypt_key);
            if ($shouldDecrypt) {
                if (!Auth::check()) {
                    return $this->privacyResponse();
                }
                $fileKey = decryptData($masterKey, $media->encrypt_key);
                $decrypted = is_string($fileKey) && $fileKey !== '' ? decryptData($fileKey, $fileContent) : false;
                abort_if(!is_string($decrypted) || $decrypted === '', 500, 'File tidak dapat didecrypt');
                $fileContent = $decrypted;
            }
            $manager = new ImageManager(new Driver());
            $img = $manager->decode($fileContent);
            $img->scaleDown(width: 300);

            // Encode file ke format aslinya
            $encoded = $img->encodeUsingFileExtension(pathinfo($media->file_path, PATHINFO_EXTENSION), quality: 90);

            return response($encoded->toString(), 200, [
                'Content-Type' => $media->file_type,
                'Content-Disposition' => 'inline; filename="' . basename($media->file_path) . '"',
                'Cache-Control' => 'public, max-age=31536000, immutable',
                'Pragma' => 'public',
                'Expires' => gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT',
                'Accept-Ranges' => 'bytes'
            ]);
        }
        $masterKey = config('flc.encrypt_key');
        $shouldDecrypt = is_string($masterKey) && trim((string) $masterKey) !== '' && !empty($media->encrypt_key);
        if ($shouldDecrypt) {
            if (!Auth::check()) {
                return $this->privacyResponse();
            }
            return response()->stream(function () use ($media) {
                $raw = Storage::disk($media->file_disk)->get($media->file_path);
                $masterKey = config('flc.encrypt_key');
                $fileKey = decryptData($masterKey, $media->encrypt_key);
                $decrypted = is_string($fileKey) && $fileKey !== '' ? decryptData($fileKey, $raw) : false;
                abort_if(!is_string($decrypted) || $decrypted === '', 500, 'File tidak dapat didecrypt');
                echo $decrypted;
            }, 200, [
                'Content-Type' => $media->file_type,
                'Content-Disposition' => 'inline; filename="' . basename($media->file_path) . '"',
                'Cache-Control' => 'public, max-age=31536000, immutable',
                'Pragma' => 'public',
                'Expires' => gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT',
                'Accept-Ranges' => 'bytes',
            ]);
        }
        return response()->stream(function () use ($media) {
            $stream = Storage::disk($media->file_disk)->readStream($media->file_path);
            abort_if($stream === false, 404);
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $media->file_type,
            'Content-Disposition' => 'inline; filename="' . basename($media->file_path) . '"',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Pragma' => 'public',
            'Expires' => gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT',
            'Accept-Ranges' => 'bytes',
        ]);
    }

    private function xmlNotFoundResponse($key)
    {
        $requestId = \Illuminate\Support\Str::uuid();
        $hostId = base64_encode(\Illuminate\Support\Str::random(32));
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Error>
  <Code>NoSuchKey</Code>
  <Message>The specified key does not exist.</Message>
  <Key>{$key}</Key>
  <RequestId>{$requestId}</RequestId>
  <HostId>{$hostId}</HostId>
</Error>
XML;
        return response($xml, 404)->header('Content-Type', 'application/xml');
    }
}
