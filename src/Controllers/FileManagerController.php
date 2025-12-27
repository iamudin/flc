<?php

namespace Leazycms\FLC\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Leazycms\FLC\Models\File;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class FileManagerController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth', only: ['upload', 'destroy'])
        ];
    }

    public function upload(Request $request)
    {

        abort_if(!$request->user() || !$request->isMethod('post'), '404');
        $request->validate([
            'media' => 'required|mimetypes:' . allow_mime(),
        ]);

        if ($file = $request->file('media')) {

            if ((new File)->addFile([
                'file' => $file,
                'purpose' => 'Upload Media',
                'child_id' => str()->random(6),
                'mime_type' => explode(',', allow_mime()),
                'self_upload' => true
            ]) !== null) {
                return back()->with('success', 'File berhasil diupload');
            }
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
            $media = Cache::get("media_{$slug}");
            if ($media) {
                $media->file_hits = ($media->file_hits ?? 0) + 1;
                Cache::forever("media_{$slug}", $media);
            } else {
                $file = File::select('file_path', 'file_type', 'file_size', 'file_hits', 'file_auth', 'host', 'disk')
                    ->whereFileName($slug)
                    ->first();

                if ($file && Storage::disk($file->disk)->exists($file->file_path)) {
                    $media = json_decode(json_encode([
                        'file_path' => $file->file_path,
                        'file_type' => $file->file_type,
                        'file_host' => $file->host,
                        'file_auth' => $file->file_auth,
                        'file_size' => $file->file_size,
                        'file_hits' => $file->file_hits,
                        'file_disk' => $file->disk,
                    ]));

                    Cache::forever("media_{$slug}", $media);
                }
            }
            $key = md5(request()->session()->getId())."_".$slug;
            return response()->download(Storage::disk($media->file_disk)->path($media->file_path), $key);
        }
    }


    public function stream($slug, Request $request)
    {
        // cek referer
        $referer = $request->userAgent().'|'.$request->headers->get('referer');

        if ($referer) {
            $allowedDomains = [
                parse_url(config('app.url'), PHP_URL_HOST), // domain web kamu
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
        $media = Cache::get("media_{$slug}");
        abort_if(!$media, 404);

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
            $data = File::whereFileName(basename($media))->first();
            if ($data) {
                Cache::forget("media_" . basename($media));
                Storage::disk($data->disk)->delete($data->file_path);
                Log::channel('daily')->info('File deleted: ' . $data->file_name, [
                    'path' => $data->file_path,
                    'ip' => get_client_ip(),
                    'user_id' => auth()?->user()->email,
                    'referer' => request()->headers->get('referer'),
                ]);
                $data->forceDelete();
            }
        }
    }
    public function stream_by_id($slug)
    {
        $media = Cache::rememberForever("media_{$slug}", function () use ($slug) {
            $file = File::select('file_path', 'file_type', 'file_size', 'file_hits', 'file_auth', 'host', 'disk', 'file_hits')
                ->whereFileName($slug)
                ->first();

            if ($file && Storage::disk($file->disk)->exists($file->file_path)) {
                return json_decode(json_encode([
                    'file_path' => $file->file_path,
                    'file_type' => $file->file_type,
                    'file_host' => $file->host,
                    'file_auth' => $file->file_auth,
                    'file_size' => $file->file_size,
                    'file_hits' => $file->file_hits,
                    'file_disk' => $file->disk,
                ]));
            }
            return null;
        });

        if (empty($media) || (isset($media->file_host) && request()->getHost() != $media->file_host || !Storage::disk($media->file_disk)->exists($media->file_path))) {
            $requestId = Str::uuid(); // unik, seperti AWS RequestId
            $hostId = base64_encode(Str::random(32)); // mirip HostId AWS

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

            return response($xml, 404)
                ->header('Content-Type', 'application/xml');
        }


        $auth = $media->file_auth;
        if ($auth === null) {
            // no auth needed
        } elseif ($auth == 0) {
            abort_if(!auth()->check(), 403, 'You need to be logged in to access this resource.');
        } elseif ($auth > 0) {
            abort_if($auth != auth()->id(), 403, 'You do not have permission to access this resource.');
        }


        // Cek apakah user minta size kecil
        $size = request('size');
        $isImage = str_contains($media->file_type, 'image');

        if ($isImage && $size == 'small') {
            $fileContent = Storage::disk($media->file_disk)->get($media->file_path);
            $img = Image::make($fileContent)->resize(300, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            // Encode file ke format aslinya
            $img->encode(pathinfo($media->file_path, PATHINFO_EXTENSION), 90); // 90 = kualitas gambar

            return response($img, 200, [
                'Content-Type' => $media->file_type,
                'Content-Disposition' => 'inline; filename="' . basename($media->file_path) . '"',
                'Cache-Control' => 'public, max-age=31536000, immutable',
                'Pragma' => 'public',
                'Expires' => gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT',
                'Accept-Ranges' => 'bytes'
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
}
